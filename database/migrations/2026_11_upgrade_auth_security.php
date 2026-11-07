<?php
/**
 * Database Migration: Upgrade Auth & Security Schema
 * Adds IP tracking, rate-limiting indexes, and hashed token storage for password resets.
 */

require_once __DIR__ . '/../../config/database.php';

try {
    $pdo = getDBConnection();
    echo "Running Auth & Security Database Migration...\n";

    // 1. Upgrade user_otps table
    $stmt = $pdo->query("SHOW COLUMNS FROM user_otps LIKE 'ip_address'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE user_otps ADD COLUMN ip_address VARCHAR(45) NULL AFTER otp_type");
        echo "[APPLIED] Added 'ip_address' column to user_otps\n";
    } else {
        echo "[EXISTS] Column 'ip_address' already exists in user_otps\n";
    }

    // Add indexes to user_otps if not present
    $indices = $pdo->query("SHOW INDEX FROM user_otps")->fetchAll(PDO::FETCH_ASSOC);
    $indexNames = array_column($indices, 'Key_name');

    if (!in_array('idx_rate_limit', $indexNames)) {
        $pdo->exec("ALTER TABLE user_otps ADD INDEX idx_rate_limit (user_id, otp_type, created_at)");
        echo "[APPLIED] Added 'idx_rate_limit' index to user_otps\n";
    }
    if (!in_array('idx_ip_rate_limit', $indexNames)) {
        $pdo->exec("ALTER TABLE user_otps ADD INDEX idx_ip_rate_limit (ip_address, created_at)");
        echo "[APPLIED] Added 'idx_ip_rate_limit' index to user_otps\n";
    }

    // 2. Upgrade password_resets table
    $stmt = $pdo->query("SHOW COLUMNS FROM password_resets LIKE 'token_hash'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE password_resets ADD COLUMN token_hash VARCHAR(255) NULL AFTER user_id");
        echo "[APPLIED] Added 'token_hash' column to password_resets\n";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM password_resets LIKE 'ip_address'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE password_resets ADD COLUMN ip_address VARCHAR(45) NULL AFTER token_hash");
        echo "[APPLIED] Added 'ip_address' column to password_resets\n";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM password_resets LIKE 'is_used'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE password_resets ADD COLUMN is_used TINYINT(1) DEFAULT 0 AFTER ip_address");
        echo "[APPLIED] Added 'is_used' column to password_resets\n";
    }

    $prIndices = $pdo->query("SHOW INDEX FROM password_resets")->fetchAll(PDO::FETCH_ASSOC);
    $prIndexNames = array_column($prIndices, 'Key_name');

    if (!in_array('idx_token_hash', $prIndexNames)) {
        $pdo->exec("ALTER TABLE password_resets ADD INDEX idx_token_hash (token_hash)");
        echo "[APPLIED] Added 'idx_token_hash' index to password_resets\n";
    }
    if (!in_array('idx_user_expires', $prIndexNames)) {
        $pdo->exec("ALTER TABLE password_resets ADD INDEX idx_user_expires (user_id, expires_at)");
        echo "[APPLIED] Added 'idx_user_expires' index to password_resets\n";
    }

    echo "\nMigration completed successfully! All tables upgraded.\n";

} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

