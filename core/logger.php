<?php
function logActivity($pdo, $user_id, $action, $details = null) {
    // Create logs table if not exists with proper structure
    $pdo->exec("CREATE TABLE IF NOT EXISTS activity_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        user_email VARCHAR(100) NULL,
        action VARCHAR(255),
        details TEXT,
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_created_at (created_at)
    )");
    
    // Get user agent
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    // If user_id is provided, get user email
    $user_email = null;
    if ($user_id) {
        try {
            $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $user_email = $user['email'];
            }
        } catch (Exception $e) {
            // User table might not exist or user not found
        }
    }
    
    // Insert log entry
    $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, user_email, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $user_email, $action, $details, $ip, $user_agent]);
    
    return $pdo->lastInsertId();
}

// Helper function to get recent logs
function getRecentLogs($pdo, $limit = 50) {
    $stmt = $pdo->prepare("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT ?");
    $stmt->execute([$limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Helper function to get logs by user
function getUserLogs($pdo, $user_id, $limit = 20) {
    $stmt = $pdo->prepare("SELECT * FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->execute([$user_id, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Helper function to clear old logs
function clearOldLogs($pdo, $days = 30) {
    $stmt = $pdo->prepare("DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
    $stmt->execute([$days]);
    return $stmt->rowCount();
}
?>