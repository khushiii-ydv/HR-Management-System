<?php
/**
 * Global Helper Functions
 * HR Management System
 */

if (
    PHP_SAPI !== 'cli' &&
    session_status() === PHP_SESSION_NONE &&
    !headers_sent()
) {
    session_start();
}

/**
 * Sanitize output
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Format currency using company settings
 */
function formatCurrency($amount, $currency = null) {
    if ($currency === null) {
        $currency = $_SESSION['company_currency'] ?? 'INR';
    }
    $symbol = ($currency === 'INR' || $currency === '₹') ? '₹' : '$';
    return $symbol . ' ' . number_format((float)$amount, 2);
}

/**
 * Format Date cleanly
 */
function formatDate($date, $format = 'M d, Y') {
    if (!$date || $date === '0000-00-00') return '-';
    return date($format, strtotime($date));
}

/**
 * Format Time cleanly
 */
function formatTime($time, $format = 'h:i A') {
    if (!$time) return '-';
    return date($format, strtotime($time));
}

/**
 * Format relative time (e.g. 2 hours ago)
 */
function timeAgo($datetime) {
    if (!$datetime) return '';
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;

    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' mins ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    return date('M d, Y', $timestamp);
}

/**
 * Render status badge with specified design palette
 */
function renderStatusBadge($status) {
    $statusLower = strtolower(trim((string)$status));
    
    // Mapping of statuses to colors
    $badges = [
        'active'      => ['bg' => '#E8F7EF', 'color' => '#16A36A', 'label' => 'Active', 'icon' => 'bi-check-circle-fill'],
        'paid'        => ['bg' => '#E8F7EF', 'color' => '#16A36A', 'label' => 'Paid', 'icon' => 'bi-check2-circle'],
        'approved'    => ['bg' => '#E8F7EF', 'color' => '#16A36A', 'label' => 'Approved', 'icon' => 'bi-check-lg'],
        'present'     => ['bg' => '#E8F7EF', 'color' => '#16A36A', 'label' => 'Present', 'icon' => 'bi-person-check-fill'],
        'selected'    => ['bg' => '#E8F7EF', 'color' => '#16A36A', 'label' => 'Selected', 'icon' => 'bi-check2-all'],
        'hired'       => ['bg' => '#E8F7EF', 'color' => '#16A36A', 'label' => 'Hired', 'icon' => 'bi-award-fill'],
        'completed'   => ['bg' => '#E8F7EF', 'color' => '#16A36A', 'label' => 'Completed', 'icon' => 'bi-check-circle'],
        
        'pending'     => ['bg' => '#FFF8E5', 'color' => '#D99A16', 'label' => 'Pending', 'icon' => 'bi-clock-history'],
        'on_hold'     => ['bg' => '#FFF8E5', 'color' => '#D99A16', 'label' => 'On Hold', 'icon' => 'bi-pause-circle'],
        'half_day'    => ['bg' => '#FFF8E5', 'color' => '#D99A16', 'label' => 'Half Day', 'icon' => 'bi-pie-chart'],
        'applied'     => ['bg' => '#FFF8E5', 'color' => '#D99A16', 'label' => 'Applied', 'icon' => 'bi-inbox'],
        
        'late'        => ['bg' => '#FFF4E5', 'color' => '#EA8A19', 'label' => 'Late', 'icon' => 'bi-exclamation-triangle-fill'],
        'on_leave'    => ['bg' => '#FFF4E5', 'color' => '#EA8A19', 'label' => 'On Leave', 'icon' => 'bi-calendar-event'],
        'scheduled'   => ['bg' => '#FFF4E5', 'color' => '#EA8A19', 'label' => 'Scheduled', 'icon' => 'bi-calendar-check'],
        
        'rejected'    => ['bg' => '#FDECEC', 'color' => '#DC4545', 'label' => 'Rejected', 'icon' => 'bi-x-circle-fill'],
        'absent'      => ['bg' => '#FDECEC', 'color' => '#DC4545', 'label' => 'Absent', 'icon' => 'bi-person-x-fill'],
        'inactive'    => ['bg' => '#F1F5F9', 'color' => '#64748B', 'label' => 'Inactive', 'icon' => 'bi-slash-circle'],
        'suspended'   => ['bg' => '#FDECEC', 'color' => '#DC4545', 'label' => 'Suspended', 'icon' => 'bi-ban'],
        'cancelled'   => ['bg' => '#F1F5F9', 'color' => '#64748B', 'label' => 'Cancelled', 'icon' => 'bi-x'],
        'terminated'  => ['bg' => '#FDECEC', 'color' => '#DC4545', 'label' => 'Terminated', 'icon' => 'bi-person-dash'],
        
        'shortlisted' => ['bg' => '#F2ECFF', 'color' => '#7C3AED', 'label' => 'Shortlisted', 'icon' => 'bi-star-fill'],
        'interview'   => ['bg' => '#F2ECFF', 'color' => '#7C3AED', 'label' => 'Interview', 'icon' => 'bi-camera-video'],
        'open'        => ['bg' => '#EAF2FF', 'color' => '#0454D9', 'label' => 'Open', 'icon' => 'bi-door-open'],
        'closed'      => ['bg' => '#F1F5F9', 'color' => '#64748B', 'label' => 'Closed', 'icon' => 'bi-door-closed']
    ];

    $cfg = $badges[$statusLower] ?? ['bg' => '#EAF2FF', 'color' => '#0454D9', 'label' => ucfirst($statusLower), 'icon' => 'bi-dot'];
    
    return '<span class="status-badge" style="background-color: ' . $cfg['bg'] . '; color: ' . $cfg['color'] . '; border: 1px solid ' . $cfg['color'] . '22;">'
         . '<i class="bi ' . $cfg['icon'] . ' me-1" style="font-size: 0.8em;"></i>'
         . htmlspecialchars($cfg['label'])
         . '</span>';
}

/**
 * Flash messages
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type, // success, danger, warning, info
        'message' => $message
    ];
}

function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $flash;
    }
    return null;
}

/**
 * CSRF Protection
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
}

/**
 * Log action into audit_logs table
 */
function logAudit($pdo, $userId, $action, $module, $description = null) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, module, description, ip_address, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$userId, $action, $module, $description, $ip]);
    } catch (Exception $e) {
        // Silently continue if audit log fails
    }
}

/**
 * Create a notification for a user
 */
function createNotification($pdo, $userId, $title, $message, $type = 'info') {
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, notification_type, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
        $stmt->execute([$userId, $title, $message, $type]);
    } catch (Exception $e) {
        // Silently continue
    }
}

/**
 * Convert number to words for payslips
 */
function convertNumberToWords($number) {
    $hyphen      = '-';
    $conjunction = ' and ';
    $separator   = ', ';
    $negative    = 'negative ';
    $decimal     = ' point ';
    $dictionary  = [
        0                   => 'zero',
        1                   => 'one',
        2                   => 'two',
        3                   => 'three',
        4                   => 'four',
        5                   => 'five',
        6                   => 'six',
        7                   => 'seven',
        8                   => 'eight',
        9                   => 'nine',
        10                  => 'ten',
        11                  => 'eleven',
        12                  => 'twelve',
        13                  => 'thirteen',
        14                  => 'fourteen',
        15                  => 'fifteen',
        16                  => 'sixteen',
        17                  => 'seventeen',
        18                  => 'eighteen',
        19                  => 'nineteen',
        20                  => 'twenty',
        30                  => 'thirty',
        40                  => 'forty',
        50                  => 'fifty',
        60                  => 'sixty',
        70                  => 'seventy',
        80                  => 'eighty',
        90                  => 'ninety',
        100                 => 'hundred',
        1000                => 'thousand',
        1000000             => 'million',
        1000000000          => 'billion'
    ];

    if (!is_numeric($number)) return '';

    if (($number >= 0 && (int) $number < 0) || (int) $number < 0 - PHP_INT_MAX) {
        return $number;
    }

    if ($number < 0) {
        return $negative . convertNumberToWords(abs($number));
    }

    $string = $fraction = null;

    if (strpos((string)$number, '.') !== false) {
        list($number, $fraction) = explode('.', (string)$number);
    }

    switch (true) {
        case $number < 21:
            $string = $dictionary[$number];
            break;
        case $number < 100:
            $tens   = ((int) ($number / 10)) * 10;
            $units  = $number % 10;
            $string = $dictionary[$tens];
            if ($units) {
                $string .= $hyphen . $dictionary[$units];
            }
            break;
        case $number < 1000:
            $hundreds  = $number / 100;
            $remainder = $number % 100;
            $string = $dictionary[$hundreds] . ' ' . $dictionary[100];
            if ($remainder) {
                $string .= $conjunction . convertNumberToWords($remainder);
            }
            break;
        default:
            $baseUnit = pow(1000, floor(log($number, 1000)));
            $numBaseUnits = (int) ($number / $baseUnit);
            $remainder = $number % $baseUnit;
            $string = convertNumberToWords($numBaseUnits) . ' ' . $dictionary[$baseUnit];
            if ($remainder) {
                $string .= $remainder < 100 ? $conjunction : $separator;
                $string .= convertNumberToWords($remainder);
            }
            break;
    }

    if (null !== $fraction && is_numeric($fraction) && (int)$fraction > 0) {
        $string .= $decimal;
        $words = [];
        foreach (str_split((string) $fraction) as $number) {
            $words[] = $dictionary[$number];
        }
        $string .= implode(' ', $words);
    }

    return ucwords($string);
}

/**
 * CSRF Protection Helpers
 */
function getCsrfToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function getCsrfInput() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function validateCsrfToken($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (empty($sessionToken) || empty($token)) {
        return false;
    }
    return hash_equals($sessionToken, (string)$token);
}

/**
 * Get accurate client IP address
 */
function getClientIp() {
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return $_SERVER['HTTP_CF_CONNECTING_IP'];
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $candidate = trim($ipList[0]);
        if (filter_var($candidate, FILTER_VALIDATE_IP)) {
            return $candidate;
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

/**
 * Safe enterprise logger: automatically scrubs sensitive data
 */
function secureLog($level, $message, array $context = []) {
    $logDir = dirname(__DIR__) . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $logFile = $logDir . '/app.log';

    // Scrub sensitive keys
    $sensitiveKeys = ['password', 'confirm_password', 'otp', 'otp_code', 'raw_otp', 'secret', 'client_secret', 'token', 'access_token', 'mail_password'];
    array_walk_recursive($context, function (&$val, $key) use ($sensitiveKeys) {
        if (in_array(strtolower((string)$key), $sensitiveKeys, true)) {
            $val = '***REDACTED***';
        }
    });

    $entry = sprintf(
        "[%s] [%s] %s %s\n",
        date('Y-m-d H:i:s'),
        strtoupper($level),
        $message,
        !empty($context) ? json_encode($context) : ''
    );
    @file_put_contents($logFile, $entry, FILE_APPEND);
}

