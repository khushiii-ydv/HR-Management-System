<?php
/**
 * Google OAuth Redirect
 * HR Management System
 */
require_once __DIR__ . '/../config/database.php';
header("Location: " . BASE_URL . "auth/google_auth.php");
exit;
