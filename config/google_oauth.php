<?php
/**
 * Official Google OAuth 2.0 Configuration & Helper
 * HR Management System
 *
 * Credential resolution priority (highest → lowest):
 *   1. getenv() — set by .env loader in config/database.php
 *   2. company_settings DB row (configurable via Admin → Settings)
 *   3. Hard-coded placeholder (triggers "not configured" state)
 */

require_once __DIR__ . '/database.php'; // Also runs .env loader

// Fetch DB-level overrides (allow Admin to update creds via UI)
$pdo = getDBConnection();
$cs  = $pdo->query("SELECT google_client_id, google_client_secret, google_redirect_uri FROM company_settings LIMIT 1")
           ->fetch(PDO::FETCH_ASSOC);

define('GOOGLE_CLIENT_ID',
    getenv('GOOGLE_CLIENT_ID')
    ?: ($cs['google_client_id'] ?? 'YOUR_GOOGLE_CLIENT_ID_HERE.apps.googleusercontent.com')
);

define('GOOGLE_CLIENT_SECRET',
    getenv('GOOGLE_CLIENT_SECRET')
    ?: ($cs['google_client_secret'] ?? 'YOUR_GOOGLE_CLIENT_SECRET_HERE')
);

// REDIRECT_URI: env → DB → hard-coded local default
define('GOOGLE_REDIRECT_URI',
    getenv('GOOGLE_REDIRECT_URI')
    ?: ($cs['google_redirect_uri'] ?: 'http://localhost/hr-management-system/auth/google_callback.php')
);

define('GOOGLE_AUTH_ENDPOINT',  'https://accounts.google.com/o/oauth2/v2/auth');
define('GOOGLE_TOKEN_ENDPOINT', 'https://oauth2.googleapis.com/token');
define('GOOGLE_USERINFO_ENDPOINT', 'https://www.googleapis.com/oauth2/v3/userinfo');

/**
 * Check if Google OAuth credentials have been configured
 */
function isGoogleOAuthConfigured() {
    $cid = GOOGLE_CLIENT_ID;
    $sec = GOOGLE_CLIENT_SECRET;
    return !empty($cid) && !empty($sec) && strpos($cid, 'YOUR_GOOGLE_CLIENT_ID') === false;
}

/**
 * Generate Google OAuth 2.0 Authorization URL
 */
function getGoogleAuthUrl($state = 'login') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Cryptographic CSRF state token
    $csrfToken = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $csrfToken;
    $_SESSION['oauth_action'] = $state; // 'login' or 'link'

    $params = [
        'client_id'       => GOOGLE_CLIENT_ID,
        'redirect_uri'    => GOOGLE_REDIRECT_URI,
        'response_type'   => 'code',
        'scope'           => 'openid email profile',
        'access_type'     => 'online',
        'prompt'          => 'select_account',
        'state'           => $csrfToken . ':' . $state
    ];

    return GOOGLE_AUTH_ENDPOINT . '?' . http_build_query($params);
}

/**
 * Perform an HTTP request using cURL if available, otherwise file_get_contents with stream context
 */
function makeOAuthHttpRequest($url, $method = 'GET', array $headers = [], $postData = null) {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($postData !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($postData) ? http_build_query($postData) : $postData);
            }
        }
        $formattedHeaders = [];
        foreach ($headers as $k => $v) {
            $formattedHeaders[] = is_numeric($k) ? $v : "$k: $v";
        }
        if (!empty($formattedHeaders)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $formattedHeaders);
        }
        // Use system certs or allow fallback on Windows dev environments
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new Exception("HTTP request failed: " . $curlError);
        }
        return ['status' => $httpCode, 'body' => $response];
    }

    // Resilient fallback using PHP stream context (when curl extension is disabled in php.ini)
    $headerLines = [];
    foreach ($headers as $k => $v) {
        $headerLines[] = is_numeric($k) ? $v : "$k: $v";
    }

    $httpOpts = [
        'method'        => $method,
        'header'        => implode("\r\n", $headerLines) . "\r\n",
        'ignore_errors' => true,
        'timeout'       => 15
    ];

    if ($method === 'POST' && $postData !== null) {
        $httpOpts['content'] = is_array($postData) ? http_build_query($postData) : $postData;
    }

    $context = stream_context_create([
        'http' => $httpOpts,
        'ssl'  => [
            'verify_peer'      => false,
            'verify_peer_name' => false,
        ]
    ]);

    $response = @file_get_contents($url, false, $context);
    $httpCode = 0;
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $hdr) {
            if (preg_match('#HTTP/\S+\s+(\d+)#', $hdr, $m)) {
                $httpCode = (int)$m[1];
            }
        }
    }

    if ($response === false && $httpCode === 0) {
        throw new Exception("HTTP request failed. Please enable php_curl in your php.ini or check your internet connection.");
    }

    return ['status' => $httpCode, 'body' => $response];
}

/**
 * Exchange Google Authorization Code for Access & ID Tokens
 */
function exchangeGoogleCodeForToken($code) {
    $postData = [
        'code'          => $code,
        'client_id'     => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri'  => GOOGLE_REDIRECT_URI,
        'grant_type'    => 'authorization_code'
    ];

    $res = makeOAuthHttpRequest(GOOGLE_TOKEN_ENDPOINT, 'POST', [
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json'
    ], $postData);

    $data = json_decode($res['body'], true);
    if ($res['status'] !== 200 || empty($data['access_token'])) {
        $msg = $data['error_description'] ?? ($data['error'] ?? 'Token exchange returned HTTP ' . $res['status']);
        throw new Exception("Google Token Error: " . $msg);
    }

    return $data;
}

/**
 * Fetch verified user profile from Google's Userinfo API
 */
function getGoogleUserProfile($accessToken) {
    $res = makeOAuthHttpRequest(GOOGLE_USERINFO_ENDPOINT, 'GET', [
        'Authorization: Bearer ' . $accessToken,
        'Accept: application/json'
    ]);

    $data = json_decode($res['body'], true);
    if ($res['status'] !== 200 || empty($data['email'])) {
        throw new Exception("Failed to retrieve verified Google profile: " . ($data['error_description'] ?? 'HTTP ' . $res['status']));
    }

    return [
        'google_id'      => $data['sub'] ?? null,
        'email'          => strtolower(trim($data['email'])),
        'email_verified' => $data['email_verified'] ?? false,
        'name'           => $data['name'] ?? '',
        'picture'        => $data['picture'] ?? null
    ];
}

