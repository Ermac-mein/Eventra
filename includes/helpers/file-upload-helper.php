<?php

/**
 * Secure File Upload Helper
 * Provides validation and safe file handling
 */

require_once __DIR__ . '/../../config/env-loader.php';

class FileUploadValidator
{
    // Allowed file extensions (whitelist approach)
    private static $allowedExtensions = [
        'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'video' => ['mp4', 'webm'],
        'pdf' => ['pdf'],
        'document' => ['doc', 'docx'],
    ];

    // Allowed MIME types (whitelist)
    private static $allowedMimes = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'video/mp4',
        'video/webm',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    // Maximum file size (default 15MB from env)
    private static $maxFileSize = null;

    /**
     * Get max file size from environment (in bytes)
     */
    private static function getMaxFileSize()
    {
        if (self::$maxFileSize === null) {
            $sizeStr = $_ENV['UPLOAD_MAX_SIZE'] ?? '15M';
            self::$maxFileSize = self::convertToBytes($sizeStr);
        }
        return self::$maxFileSize;
    }

    /**
     * Convert size string (e.g., "15M") to bytes
     */
    private static function convertToBytes($value)
    {
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1]);
        $value = (int)$value;

        switch ($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }

        return $value;
    }

    /**
     * Validate file before upload
     *
     * @param array $file $_FILES['field'] array
     * @param array $options Validation options ['allowed_types' => ['image', 'pdf'], 'allow_all_extensions' => false]
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public static function validateFile($file, $options = [])
    {
        // Check if file was uploaded
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['valid' => false, 'error' => 'File was not properly uploaded'];
        }

        // Check file size
        $maxSize = self::getMaxFileSize();
        if ($file['size'] > $maxSize) {
            return ['valid' => false, 'error' => 'File size exceeds maximum allowed (' . self::formatBytes($maxSize) . ')'];
        }

        if ($file['size'] === 0) {
            return ['valid' => false, 'error' => 'File is empty'];
        }

        // Get file extension
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (empty($ext)) {
            return ['valid' => false, 'error' => 'File has no extension'];
        }

        // Validate extension against whitelist
        $allowedTypes = $options['allowed_types'] ?? array_keys(self::$allowedExtensions);
        $allAllowedExts = [];
        foreach ($allowedTypes as $type) {
            if (isset(self::$allowedExtensions[$type])) {
                $allAllowedExts = array_merge($allAllowedExts, self::$allowedExtensions[$type]);
            }
        }

        if (!in_array($ext, $allAllowedExts, true)) {
            return ['valid' => false, 'error' => 'File type not allowed: .' . $ext];
        }

        // Verify MIME type using finfo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, self::$allowedMimes, true)) {
            return ['valid' => false, 'error' => 'Invalid file content (MIME type: ' . $mimeType . ')'];
        }

        // Additional checks for specific file types
        if (strpos($mimeType, 'image') === 0) {
            // Verify image integrity
            if (!getimagesize($file['tmp_name'])) {
                return ['valid' => false, 'error' => 'Invalid image file'];
            }
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Generate safe filename
     */
    public static function generateSafeFilename($originalName, $prefix = '')
    {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        
        // Sanitize base name (remove special chars, limit length)
        $baseName = preg_replace('/[^a-z0-9._-]/i', '', $baseName);
        $baseName = substr($baseName, 0, 50);
        
        // Generate unique name
        $uniqueName = ($prefix ? $prefix . '_' : '') . time() . '_' . uniqid() . '.' . $ext;
        return $uniqueName;
    }

    /**
     * Format bytes to human-readable size
     */
    private static function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
