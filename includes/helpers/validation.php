<?php

/**
 * Validation Helper Functions
 * Password validation, identity verification, and data validation utilities
 */

/**
 * Validate password strength
 * Requirements: minimum 8 characters.
 *
 * @param string $password
 * @return array ['valid' => bool, 'errors' => []]
 */
function validatePasswordStrength($password)
{
    $errors = [];

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long';
    }

    if (strlen($password) > 128) {
        $errors[] = 'Password is too long (max 128 characters)';
    }

    return [
        'valid' => count($errors) === 0,
        'errors' => $errors
    ];
}

/**
 * Normalize phone number for storage/comparison
 * Handles Nigerian formats: 080xxx → 234 80xxx
 *
 * @param string $phone
 * @return string Normalized phone number
 */
function normalizePhoneNumber($phone)
{
    $cleaned = preg_replace('/[^\d]/', '', $phone);
    
    // Normalize Nigerian numbers
    if (strlen($cleaned) === 11 && strpos($cleaned, '0') === 0) {
        // Convert 080xxx to 234 80xxx
        $cleaned = '234' . substr($cleaned, 1);
    } elseif (strlen($cleaned) === 10 && !preg_match('/^234/', $cleaned)) {
        // Add default country code for bare 10-digit numbers
        $cleaned = '234' . $cleaned;
    }
    
    return $cleaned;
}

/**
 * Validate phone number format (flexible, accepts various formats)
 *
 * @param string $phone
 * @return array ['valid' => bool, 'normalized' => string|null, 'error' => string|null]
 */
function validatePhoneNumber($phone)
{
    // Remove all non-numeric characters
    $cleaned = preg_replace('/[^\d]/', '', $phone);

    // Must be between 10 and 15 digits
    if (strlen($cleaned) < 10 || strlen($cleaned) > 15) {
        return [
            'valid' => false,
            'normalized' => null,
            'error' => 'Phone number must be between 10 and 15 digits'
        ];
    }

    // Normalize Nigerian numbers
    if (strlen($cleaned) === 11 && strpos($cleaned, '0') === 0) {
        // Convert 080xxx to 234 80xxx
        $cleaned = '234' . substr($cleaned, 1);
    } elseif (strlen($cleaned) === 10 && !preg_match('/^234/', $cleaned)) {
        // Add default country code for bare 10-digit numbers
        $cleaned = '234' . $cleaned;
    }

    return [
        'valid' => true,
        'normalized' => $cleaned,
        'error' => null
    ];
}

/**
 * Validate NIN (National Identification Number) - Nigeria
 * NIN format: 11 digits
 *
 * @param string $nin
 * @return array ['valid' => bool, 'error' => string|null]
 */
function validateNIN($nin)
{
    $nin = preg_replace('/[^\d]/', '', $nin);

    if (strlen($nin) !== 11) {
        return [
            'valid' => false,
            'error' => 'NIN must be exactly 11 digits'
        ];
    }

    if (!is_numeric($nin)) {
        return [
            'valid' => false,
            'error' => 'NIN must contain only numbers'
        ];
    }

    // Cannot be all zeros
    if ($nin === '00000000000') {
        return [
            'valid' => false,
            'error' => 'Invalid NIN format'
        ];
    }

    return ['valid' => true, 'error' => null];
}

/**
 * Validate BVN (Bank Verification Number) - Nigeria
 * BVN format: 11 digits
 *
 * @param string $bvn
 * @return array ['valid' => bool, 'error' => string|null]
 */
function validateBVN($bvn)
{
    $bvn = preg_replace('/[^\d]/', '', $bvn);

    if (strlen($bvn) !== 11) {
        return [
            'valid' => false,
            'error' => 'BVN must be exactly 11 digits'
        ];
    }

    if (!is_numeric($bvn)) {
        return [
            'valid' => false,
            'error' => 'BVN must contain only numbers'
        ];
    }

    // Cannot be all zeros
    if ($bvn === '00000000000') {
        return [
            'valid' => false,
            'error' => 'Invalid BVN format'
        ];
    }

    return ['valid' => true, 'error' => null];
}

/**
 * Validate account number
 * Flexible: accepts 10-13 digits
 *
 * @param string $accountNumber
 * @return array ['valid' => bool, 'normalized' => string|null, 'error' => string|null]
 */
function validateAccountNumber($accountNumber)
{
    $cleaned = preg_replace('/[^\d]/', '', $accountNumber);

    if (strlen($cleaned) < 10 || strlen($cleaned) > 13) {
        return [
            'valid' => false,
            'normalized' => null,
            'error' => 'Account number must be between 10 and 13 digits'
        ];
    }

    if (!is_numeric($cleaned)) {
        return [
            'valid' => false,
            'normalized' => null,
            'error' => 'Account number must contain only numbers'
        ];
    }

    return [
        'valid' => true,
        'normalized' => $cleaned,
        'error' => null
    ];
}

/**
 * Validate email format
 *
 * @param string $email
 * @return bool
 */
function validateEmail($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Sanitize text input
 *
 * @param string $input
 * @return string
 */
function sanitizeText($input)
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

