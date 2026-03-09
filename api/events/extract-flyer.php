<?php
/**
 * Extract Event Data from Flyer Image
 * Uses Tesseract OCR to extract text from an uploaded flyer image,
 * then parses it for event name, date, time, address, state, and price.
 *
 * Requires: tesseract-ocr installed on server (apt-get install tesseract-ocr)
 */
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../includes/middleware/auth.php';

// Only clients can use this (they create events)
$client_auth_id = checkAuth('client');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST method required']);
    exit;
}

if (empty($_FILES['flyer']) && !isset($_POST['extracted_text'])) {
    echo json_encode(['success' => false, 'message' => 'Please upload a flyer image or provide extracted text.']);
    exit;
}

// 1. If text is already extracted (Universal Browser-side OCR path)
if (isset($_POST['extracted_text']) && !empty($_POST['extracted_text'])) {
    $extractedText = $_POST['extracted_text'];
    $parsed = parseEventTextFromOCR($extractedText);

    echo json_encode([
        'success'        => true,
        'message'        => 'Flyer text parsed successfully.',
        'extracted_text' => $extractedText,
        'fields'         => $parsed
    ]);
    exit;
}

// 2. Legacy server-side OCR path (requires binary)
$file = $_FILES['flyer'];

// Validate file type
$allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/tiff'];
$actualType = mime_content_type($file['tmp_name']);
if (!in_array($actualType, $allowedTypes)) {
    echo json_encode(['success' => false, 'message' => 'Unsupported image type. Use JPG, PNG, WEBP, or TIFF.']);
    exit;
}

// Save temp file
$tmpDir = sys_get_temp_dir();
$tmpImage = $tmpDir . '/eventra_flyer_' . uniqid() . '.png';
$tmpTxt   = $tmpDir . '/eventra_flyer_' . uniqid();

// Convert to PNG for best OCR accuracy (handles JPEG/WEBP input)
$moveOk = move_uploaded_file($file['tmp_name'], $tmpImage);
if (!$moveOk) {
    echo json_encode(['success' => false, 'message' => 'Failed to process uploaded image.']);
    exit;
}

try {
    // Run Tesseract OCR
    $escapedImg = escapeshellarg($tmpImage);
    $escapedOut = escapeshellarg($tmpTxt);

    $tesseractCmd = "tesseract {$escapedImg} {$escapedOut} 2>&1";
    $output = shell_exec($tesseractCmd);

    $txtFile = $tmpTxt . '.txt';
    if (!file_exists($txtFile)) {
        // Tesseract may not be installed
        @unlink($tmpImage);
        echo json_encode([
            'success' => false,
            'message' => 'OCR engine not available. Please ensure Tesseract is installed on the server.',
            'debug'   => $output
        ]);
        exit;
    }

    $extractedText = file_get_contents($txtFile);

    // Clean up temp files
    @unlink($tmpImage);
    @unlink($txtFile);

    // Parse extracted text for event fields
    $parsed = parseEventTextFromOCR($extractedText);

    echo json_encode([
        'success'        => true,
        'message'        => 'Flyer analyzed successfully.',
        'extracted_text' => $extractedText,
        'fields'         => $parsed
    ]);

} catch (Exception $e) {
    @unlink($tmpImage);
    echo json_encode(['success' => false, 'message' => 'OCR processing error: ' . $e->getMessage()]);
}

/**
 * Refined Advanced Intelligent Event Information Extractor
 * Prioritizes Brand/Social handles for Event Name and sanitizes formats.
 */
function parseEventTextFromOCR(string $text): array
{
    // Stage 12: Handling OCR Errors & Pre-cleaning
    $errorMap = [
        '/\bPARTV\b/i'     => 'PARTY',
        '/\bSATURDAV\b/i'  => 'SATURDAY',
        '/\bLAGO5\b/i'     => 'LAGOS',
        '/\bABU0A\b/i'     => 'ABUJA',
        '/\bENTRV\b/i'     => 'ENTRY',
        '/\bTICKEI\b/i'    => 'TICKET',
        '/\bVENOE\b/i'     => 'VENUE',
        '/\|/'             => 'I', 
        '/0/'              => 'O'  
    ];
    
    $cleanText = $text;
    foreach ($errorMap as $pattern => $replacement) {
        if ($pattern === '/0/') continue; 
        $cleanText = preg_replace($pattern, $replacement, $cleanText);
    }

    $lines = array_values(array_filter(array_map('trim', explode("\n", $cleanText))));
    $fullText = implode(' ', $lines);

    $result = [
        'event_name' => '',
        'event_date' => '',
        'event_time' => '',
        'address'    => '',
        'state'      => '',
        'price'      => '',
        'phone'      => ''
    ];

    // --- STAGE 2: Refined Event Name Detection ---
    $eventKeywords = [
        'Trade Fair', 'Festival', 'Concert', 'Summit', 'Conference', 'Night', 'Show', 'Expo', 
        'Gala', 'Tour', 'Workshop', 'Meetup', 'Celebration', 'Carnival', 'Exhibition'
    ];
    // Blacklist: These are operational titles, not the event name itself
    $blacklist = ['Call for', 'Vendors', 'Exhibitors', 'Registration', 'Apply Now', 'Sponsorship'];

    // 2a. Look for Social Handles first (@username)
    $handle = '';
    if (preg_match('/@([a-z0-9_]+)\b/i', $fullText, $hm)) {
        $handle = str_replace('_', ' ', $hm[1]);
    }

    // 2b. Evaluate lines based on handle match and keyword priority
    $bestScore = -1;
    $bestLine = '';

    foreach (array_slice($lines, 0, 15) as $line) {
        $score = 0;
        $isBlacklisted = false;
        foreach ($blacklist as $bl) {
            if (stripos($line, $bl) !== false) { $isBlacklisted = true; break; }
        }
        if ($isBlacklisted) $score -= 10;

        foreach ($eventKeywords as $kw) {
            if (stripos($line, $kw) !== false) $score += 20;
        }

        if ($handle && stripos($line, explode(' ', $handle)[0]) !== false) {
            $score += 30; // High priority if it matches the social handle brand
        }

        if (mb_strtoupper($line) === $line && strlen($line) > 5) $score += 10;

        if ($score > $bestScore && strlen(preg_replace('/[^a-z0-9]/i', '', $line)) > 3) {
            $bestScore = $score;
            $bestLine = $line;
        }
    }

    if ($bestLine) {
        $result['event_name'] = ucwords(strtolower(trim($bestLine)));
    }

    // --- STAGE 3 & 4: Date & Time Recognition ---
    // Date: Support ranges and common distortions
    $datePatterns = [
        '/\b(\d{1,2}(?:st|nd|rd|th)?\s+(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\.?,?\s+20\d{2})\b/i',
        '/\b((?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\.?\s+\d{1,2}(?:st|nd|rd|th)?,?\s+20\d{2})\b/i',
        '/\b(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]20\d{2})\b/',
    ];
    foreach ($datePatterns as $pattern) {
        if (preg_match($pattern, $fullText, $m)) {
            $ts = strtotime(preg_replace('/\b(\d{1,2})(st|nd|rd|th)\b/i', '$1', $m[1]));
            $result['event_date'] = $ts ? date('Y-m-d', $ts) : $m[1];
            break;
        }
    }

    // Time: STRICT HH:mm sanitization for HTML5 inputs
    if (preg_match('/\b(\d{1,2})(?::(\d{2}))?\s*(AM|PM|am|pm)\b/i', $fullText, $m)) {
        $hour = intval($m[1]);
        $min = isset($m[2]) ? intval($m[2]) : 0;
        $ampm = strtoupper($m[3]);
        
        if ($ampm === 'PM' && $hour < 12) $hour += 12;
        if ($ampm === 'AM' && $hour === 12) $hour = 0;
        
        $result['event_time'] = sprintf("%02d:%02d", $hour, $min);
    } elseif (preg_match('/\b([01]?\d|2[0-3]):([0-5]\d)\b/', $fullText, $m)) {
        $result['event_time'] = sprintf("%02d:%02d", $m[1], $m[2]);
    }

    // --- STAGE 5 & 6 & 7: Venue & Address & State ---
    $venueKeywords = ['Venue', 'Location', 'Place', 'Holding at', 'Center', 'Hall', 'Arena', 'Hotel', 'Club', 'Stadium', 'Plaza', 'Park', 'Auditorium', 'Theatre'];
    $addressKeywords = ['Street', 'Road', 'Rd', 'Avenue', 'Ave', 'Drive', 'Close', 'Lane', 'Way', 'Boulevard', 'Layout', 'Estate', 'Junction', 'Crescent'];
    
    $detectedVenue = '';
    $detectedStreet = '';

    foreach ($lines as $i => $line) {
        foreach ($venueKeywords as $kw) {
            if (stripos($line, $kw) !== false) {
                if (preg_match('/' . $kw . '[:\-\s]+(.*)/i', $line, $vm)) {
                    $detectedVenue = trim($vm[1]);
                } else {
                    $detectedVenue = isset($lines[$i+1]) ? $lines[$i+1] : $line;
                }
                break 2;
            }
        }
    }
    
    foreach ($lines as $line) {
        foreach ($addressKeywords as $kw) {
            if (stripos($line, $kw) !== false) {
                $detectedStreet = $line;
                break 2;
            }
        }
    }

    if ($detectedVenue && $detectedStreet && $detectedVenue !== $detectedStreet) {
        $result['address'] = $detectedVenue . " - " . $detectedStreet;
    } else {
        $result['address'] = $detectedVenue ?: $detectedStreet;
    }

    // State Recognition (Nigerian States)
    $nigerianStates = [
        'Abia','Adamawa','Akwa Ibom','Anambra','Bauchi','Bayelsa','Benue','Borno',
        'Cross River','Delta','Ebonyi','Edo','Ekiti','Enugu','FCT','Gombe','Imo',
        'Jigawa','Kaduna','Kano','Katsina','Kebbi','Kogi','Kwara','Lagos','Nasarawa',
        'Niger','Ogun','Ondo','Osun','Oyo','Plateau','Rivers','Sokoto','Taraba',
        'Yobe','Zamfara','Abuja'
    ];
    foreach ($nigerianStates as $state) {
        if (stripos($fullText, $state) !== false) {
            $result['state'] = $state;
            break;
        }
    }

    // --- STAGE 8: Phone Contact Detection ---
    $contactKeywords = ['Call', 'Contact', 'Enquiries', 'Info', 'WhatsApp', 'RSVP'];
    $phonePattern = '/(?:\+?234|0)[789]\d{9,10}/';
    
    if (preg_match_all($phonePattern, str_replace([' ', '-'], '', $fullText), $pm)) {
        $result['phone'] = $pm[0][0]; 
    } else {
        foreach ($lines as $line) {
            foreach ($contactKeywords as $kw) {
                if (stripos($line, $kw) !== false) {
                    if (preg_match('/\b(\d[0-9\s\-]{7,}\d)\b/', $line, $pm)) {
                        $result['phone'] = trim($pm[1]);
                        break 2;
                    }
                }
            }
        }
    }

    // --- STAGE 9: Ticket Price Detection (Strictly Numeric) ---
    if (preg_match('/\b(FREE|COMPLIMENTARY|NO COVER)\b/i', $fullText)) {
        $result['price'] = '0';
    } else {
        $pricePattern = '/(?:₦|N|#|\$|NGN|ENTRY|PRICE)[:\s]*([\d,]+(?:\.\d{2})?)/i';
        if (preg_match($pricePattern, $fullText, $m)) {
            $val = str_replace(',', '', $m[1]);
            if (is_numeric($val)) $result['price'] = $val;
        }
    }

    foreach ($result as $key => $val) {
        $result[$key] = trim((string)$val);
    }

    return $result;
}
