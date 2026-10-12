<?php
// password_reset.php - helpers for admin password reset (token based)

const RESET_TOKEN_TTL_MINUTES = 30;

// Adds admins.email and the password_resets table if they don't exist yet.
function ensureResetSchema(mysqli $conn): void {
    $res = $conn->query("SHOW COLUMNS FROM admins LIKE 'email'");
    if ($res && $res->num_rows === 0) {
        $conn->query("ALTER TABLE admins ADD COLUMN email VARCHAR(190) NULL");
    }
    $conn->query("CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NOT NULL,
        token_hash CHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        used_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX (token_hash),
        INDEX (admin_id)
    ) CHARACTER SET utf8mb4");
}

// Creates a single-use token for the admin and returns the full reset URL.
function createResetLink(mysqli $conn, int $adminId): string {
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);

    $del = $conn->prepare("DELETE FROM password_resets WHERE admin_id = ?");
    $del->bind_param('i', $adminId);
    $del->execute();

    $expires = date('Y-m-d H:i:s', time() + RESET_TOKEN_TTL_MINUTES * 60);
    $ins = $conn->prepare("INSERT INTO password_resets (admin_id, token_hash, expires_at) VALUES (?, ?, ?)");
    $ins->bind_param('iss', $adminId, $hash, $expires);
    $ins->execute();

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $dir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    return $scheme . '://' . $_SERVER['HTTP_HOST'] . $dir . '/reset_password.php?token=' . $token;
}

// Returns the admin row for a valid, unused, unexpired token, or null.
function findResetByToken(mysqli $conn, string $token): ?array {
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;
    $hash = hash('sha256', $token);
    $stmt = $conn->prepare("SELECT pr.id AS reset_id, a.id, a.name, a.username
                            FROM password_resets pr JOIN admins a ON a.id = pr.admin_id
                            WHERE pr.token_hash = ? AND pr.used_at IS NULL AND pr.expires_at > NOW()
                            LIMIT 1");
    $stmt->bind_param('s', $hash);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

// Emails the reset link. Returns false if no mailer/SMTP is available.
function sendResetEmail(string $email, string $name, string $link): bool {
    $mailerPath = __DIR__ . '/../../mailer/mailer.php';
    if (!file_exists($mailerPath)) return false;
    require_once $mailerPath;
    $msg = '<div style="padding:25px;line-height:1.6;color:#333">'
         . '<h2>Reset your admin password</h2>'
         . '<p>Hello <strong>' . htmlspecialchars($name) . '</strong>,</p>'
         . '<p>Click the button below to set a new password. This link is valid for ' . RESET_TOKEN_TTL_MINUTES . ' minutes and can be used once.</p>'
         . '<p><a href="' . htmlspecialchars($link) . '" style="background:#1e3a8a;color:#fff;padding:12px 22px;border-radius:8px;text-decoration:none;display:inline-block">Reset Password</a></p>'
         . '<p style="font-size:13px;color:#666">If you did not request this, you can ignore this email.</p></div>';
    return sendCustomEmail($email, $name, 'Reset your admin password - Perfect Institute', $msg);
}
