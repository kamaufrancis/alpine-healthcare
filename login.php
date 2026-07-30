<?php
// login.php - Updated with password reset in users table
require_once 'core/auth.php';
require_once 'config/database.php';

// If already logged in, redirect based on role
if(isset($_SESSION['user_id'])) {
    redirectBasedOnRole($_SESSION['role']);
    exit();
}

$error = '';

// Handle password reset request via AJAX
if(isset($_POST['action']) && $_POST['action'] === 'forgot_password') {
    header('Content-Type: application/json');
    $email = $_POST['email'] ?? '';
    
    if(empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
        exit;
    }
    
    // Check if user exists
    $stmt = $pdo->prepare("SELECT id, fullname FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($user) {
        // Generate reset token
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Update user record with token (NO extra table needed!)
        $stmt = $pdo->prepare("
            UPDATE users 
            SET reset_token = ?, reset_token_expires = ? 
            WHERE id = ?
        ");
        $stmt->execute([$token, $expires, $user['id']]);
        
        // Create reset link - FIXED to use correct path
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'];
        $reset_link = $protocol . $host . '/alpine-healthcare/reset_password.php?token=' . $token;
        // If your project is in root, use: $reset_link = $protocol . $host . '/reset_password.php?token=' . $token;
        
        // Log for debugging (in production, send email)
        error_log("Password reset link for {$user['fullname']}: " . $reset_link);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Password reset link sent to your email.',
            'debug_link' => $reset_link // Remove in production
        ]);
    } else {
        // Security: Don't reveal if email exists
        echo json_encode([
            'success' => true, 
            'message' => 'If your email is registered, you will receive a reset link.'
        ]);
    }
    exit;
}

// Handle regular login
if($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['action'])) {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if(empty($email) || empty($password)) {
        $error = 'Please enter both email and password';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $passwordMatches = $user && (password_verify($password, $user['password']) || $password === $user['password']);
            if($passwordMatches) {
                if (!empty($user['password']) && !str_starts_with($user['password'], '$2')) {
                    $updatedHash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$updatedHash, $user['id']]);
                }

                initializeUserSession($user);
                
                if($remember) {
                    setcookie('remember_email', $email, time() + (86400 * 30), '/', '', true, true);
                } else {
                    setcookie('remember_email', '', time() - 3600, '/', '', true, true);
                }
                
                redirectBasedOnRole($user['role']);
            } else {
                $error = 'Invalid email or password';
            }
        } catch(PDOException $e) {
            $error = 'Login failed: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alpine Healthcare - Login</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <style>
        /* ==== YOUR EXISTING STYLES ==== */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: #0a0a0a;
            color: #ffffff;
            position: relative;
            overflow: hidden;
        }
        
        .login-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            background: 
                linear-gradient(135deg, 
                    rgba(3, 37, 65, 0.92) 0%, 
                    rgba(8, 57, 120, 0.85) 30%,
                    rgba(59, 130, 246, 0.65) 60%,
                    rgba(2, 20, 50, 0.88) 100%
                ),
                url('https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?w=1920&q=80');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
        }
        
        .login-bg::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(rgba(2,6,23,0.68), rgba(2,6,23,0.68)),
                        radial-gradient(ellipse at 50% 80%, rgba(59, 130, 246, 0.06) 0%, transparent 70%);
            animation: pulseGlow 6s ease-in-out infinite alternate;
            z-index: 1;
        }
        
        @keyframes pulseGlow {
            0% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
            pointer-events: none;
            overflow: hidden;
        }
        
        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: rgba(46, 204, 113, 0.3);
            border-radius: 50%;
            animation: floatParticle 20s infinite linear;
        }
        
        @keyframes floatParticle {
            0% { transform: translateY(100vh) scale(0); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { transform: translateY(-10vh) scale(1); opacity: 0; }
        }
        
        .login-container {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 440px;
            padding: 20px;
            animation: fadeInUp 0.8s ease-out;
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(40px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.06);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 24px;
            padding: 40px 36px 32px;
            box-shadow: 0 35px 110px rgba(0, 0, 0, 0.6);
            transition: all 0.3s ease;
        }
        
        .login-card:hover {
            border-color: rgba(46, 204, 113, 0.15);
            box-shadow: 0 30px 100px rgba(0, 0, 0, 0.6);
        }
        
        .brand { text-align: center; margin-bottom: 32px; }
        .brand .logo-icon { font-size: 52px; display: block; margin-bottom: 6px; animation: floatIcon 3s ease-in-out infinite; }
        @keyframes floatIcon { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-6px); } }
        .brand h1 { font-size: 28px; font-weight: 800; letter-spacing: -0.5px; margin: 0; }
        .brand h1 span { color: #2ecc71; }
        .brand .subtitle { display: block; font-size: 13px; color: rgba(255, 255, 255, 0.4); font-weight: 500; letter-spacing: 1px; text-transform: uppercase; margin-top: 2px; }
        .brand .medical-cross { display: inline-block; color: #e74c3c; font-size: 18px; font-weight: 700; background: rgba(255, 255, 255, 0.9); padding: 0 6px; border-radius: 4px; line-height: 1.2; margin-left: 4px; }
        .brand .tagline { font-size: 13px; color: rgba(255, 255, 255, 0.35); margin-top: 6px; letter-spacing: 0.5px; }
        
        .login-form { margin-top: 8px; }
        
        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            animation: slideDown 0.4s ease;
        }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .alert i { margin-top: 2px; flex-shrink: 0; }
        .alert-danger { background: rgba(231, 76, 60, 0.12); border: 1px solid rgba(231, 76, 60, 0.2); color: #e74c3c; }
        .alert-success { background: rgba(46, 204, 113, 0.12); border: 1px solid rgba(46, 204, 113, 0.2); color: #2ecc71; }
        .alert-info { background: rgba(52, 152, 219, 0.12); border: 1px solid rgba(52, 152, 219, 0.2); color: #3498db; }
        
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: rgba(255, 255, 255, 0.7); margin-bottom: 6px; }
        .form-group label i { margin-right: 6px; color: rgba(255, 255, 255, 0.3); }
        
        .input-wrapper { position: relative; }
        .input-wrapper input {
            width: 100%;
            padding: 14px 16px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            color: white;
            font-size: 15px;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
        }
        .input-wrapper input::placeholder { color: rgba(255, 255, 255, 0.2); }
        .input-wrapper input:focus {
            outline: none;
            border-color: #2ecc71;
            box-shadow: 0 0 0 4px rgba(46, 204, 113, 0.12);
            background: rgba(255, 255, 255, 0.08);
        }
        .input-wrapper .input-icon {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.2);
            transition: color 0.3s ease;
            pointer-events: none;
        }
        .input-wrapper .toggle-password {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: rgba(255, 255, 255, 0.25);
            cursor: pointer;
            font-size: 16px;
            transition: color 0.3s ease;
            padding: 4px;
        }
        .input-wrapper .toggle-password:hover { color: rgba(255, 255, 255, 0.6); }
        
        .options-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 22px;
            font-size: 13px;
        }
        .options-row .remember {
            display: flex;
            align-items: center;
            gap: 8px;
            color: rgba(255, 255, 255, 0.5);
            cursor: pointer;
        }
        .options-row .remember input[type="checkbox"] { width: 16px; height: 16px; accent-color: #2ecc71; cursor: pointer; }
        .options-row .forgot-link {
            color: rgba(255, 255, 255, 0.4);
            text-decoration: none;
            transition: color 0.3s ease;
            cursor: pointer;
        }
        .options-row .forgot-link:hover { color: #2ecc71; }
        
        .btn-login {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Inter', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            position: relative;
            overflow: hidden;
        }
        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            transition: left 0.5s ease;
        }
        .btn-login:hover::before { left: 100%; }
        .btn-login:hover { transform: translateY(-2px); box-shadow: 0 8px 35px rgba(46, 204, 113, 0.35); }
        .btn-login:active { transform: translateY(0); }
        .btn-login:disabled { opacity: 0.7; cursor: not-allowed; transform: none; }
        
        .demo-credentials {
            margin-top: 24px;
            padding: 16px 18px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.04);
        }
        .demo-credentials .demo-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.4);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }
        .demo-credentials .demo-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px 16px;
        }
        .demo-credentials .cred-item {
            display: flex;
            flex-direction: column;
            padding: 6px 8px;
            background: rgba(255, 255, 255, 0.02);
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.03);
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .demo-credentials .cred-item:hover {
            background: rgba(255, 255, 255, 0.06);
            border-color: rgba(46, 204, 113, 0.2);
        }
        .demo-credentials .cred-item .role { font-size: 11px; font-weight: 600; color: rgba(255, 255, 255, 0.5); display: flex; align-items: center; gap: 6px; }
        .demo-credentials .cred-item .email { font-size: 11px; color: rgba(255, 255, 255, 0.3); font-family: 'Inter', monospace; margin-top: 2px; }
        .demo-credentials .cred-item .password { font-size: 10px; color: rgba(46, 204, 113, 0.4); font-family: 'Inter', monospace; }
        .demo-credentials .cred-item .badge-icon { width: 6px; height: 6px; border-radius: 50%; display: inline-block; }
        .badge-icon.admin { background: #e74c3c; }
        .badge-icon.doctor { background: #3498db; }
        .badge-icon.receptionist { background: #2ecc71; }
        .badge-icon.pharmacist { background: #9b59b6; }
        
        .login-footer { text-align: center; margin-top: 20px; font-size: 12px; color: rgba(255, 255, 255, 0.2); }
        .login-footer a { color: rgba(255, 255, 255, 0.3); text-decoration: none; transition: color 0.3s ease; }
        .login-footer a:hover { color: rgba(255, 255, 255, 0.6); }
        .login-footer .heart { color: #e74c3c; }
        
        .back-home {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: rgba(255, 255, 255, 0.3);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s ease;
            margin-bottom: 16px;
        }
        .back-home:hover { color: rgba(255, 255, 255, 0.7); transform: translateX(-4px); }
        
        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 9999;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(10px);
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease;
        }
        .modal-overlay.active { display: flex; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        
        .modal-content {
            background: #1a1a1a;
            border-radius: 20px;
            padding: 40px 36px 32px;
            max-width: 420px;
            width: 100%;
            position: relative;
            animation: slideUp 0.4s ease;
            border: 1px solid rgba(255, 255, 255, 0.06);
            box-shadow: 0 35px 110px rgba(0, 0, 0, 0.6);
        }
        @keyframes slideUp { from { opacity: 0; transform: translateY(30px) scale(0.95); } to { opacity: 1; transform: translateY(0) scale(1); } }
        
        .modal-content .close-btn {
            position: absolute;
            top: 16px;
            right: 20px;
            background: none;
            border: none;
            color: rgba(255, 255, 255, 0.4);
            font-size: 24px;
            cursor: pointer;
            transition: color 0.3s ease;
        }
        .modal-content .close-btn:hover { color: white; }
        
        .modal-content .modal-header { text-align: center; margin-bottom: 24px; }
        .modal-content .modal-header .icon { font-size: 40px; display: block; margin-bottom: 8px; }
        .modal-content .modal-header h2 { font-size: 22px; font-weight: 700; color: white; }
        .modal-content .modal-header h2 span { color: #2ecc71; }
        .modal-content .modal-header p { color: rgba(255, 255, 255, 0.5); font-size: 14px; margin-top: 4px; }
        
        .modal-content .form-group { margin-bottom: 18px; }
        .modal-content .form-group label { display: block; font-size: 13px; font-weight: 600; color: rgba(255, 255, 255, 0.7); margin-bottom: 6px; }
        .modal-content .form-group input {
            width: 100%;
            padding: 12px 16px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 10px;
            color: white;
            font-size: 15px;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
        }
        .modal-content .form-group input:focus {
            outline: none;
            border-color: #2ecc71;
            box-shadow: 0 0 0 3px rgba(46, 204, 113, 0.15);
            background: rgba(255, 255, 255, 0.08);
        }
        .modal-content .form-group input::placeholder { color: rgba(255, 255, 255, 0.25); }
        
        .modal-content .btn-reset {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            border: none;
            border-radius: 10px;
            color: white;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Inter', sans-serif;
        }
        .modal-content .btn-reset:hover { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(46, 204, 113, 0.3); }
        .modal-content .btn-reset:disabled { opacity: 0.7; cursor: not-allowed; transform: none; }
        
        .modal-content .btn-secondary {
            width: 100%;
            padding: 14px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 10px;
            color: rgba(255, 255, 255, 0.6);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Inter', sans-serif;
            margin-top: 10px;
        }
        .modal-content .btn-secondary:hover { background: rgba(255, 255, 255, 0.1); color: white; }
        .modal-content .alert { margin-top: 12px; }
        
        @media (max-width: 480px) {
            .login-card { padding: 28px 20px 24px; border-radius: 20px; }
            .brand h1 { font-size: 24px; }
            .brand .logo-icon { font-size: 40px; }
            .demo-credentials .demo-grid { grid-template-columns: 1fr; }
            .options-row { flex-direction: column; gap: 10px; align-items: flex-start; }
            .login-container { padding: 16px; }
            .input-wrapper input { padding: 12px 14px; font-size: 14px; }
            .modal-content { padding: 28px 20px 24px; margin: 20px; }
        }
        @media (max-width: 360px) {
            .login-card { padding: 20px 16px; }
            .brand h1 { font-size: 20px; }
        }
    </style>
</head>
<body>

<div class="login-bg"></div>
<div class="particles" id="particles"></div>

<div class="login-container">
    
    <a href="index.php" class="back-home">
        <i class="fas fa-arrow-left"></i> Back to Home
    </a>
    
    <div class="login-card">
        
        <div class="brand">
            <span class="logo-icon">🏔️</span>
            <h1><span>Alpine</span> Healthcare</h1>
            <span class="subtitle">Clinic Management System</span>
            <div class="tagline">
                Quality Healthcare · Alpine Standard 
                <span class="medical-cross">✚</span>
            </div>
        </div>
        
        <?php if($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>
        
        <?php if(isset($_GET['logout']) && $_GET['logout'] == 'success'): ?>
            <div class="alert alert-info">
                <i class="fas fa-sign-out-alt"></i>
                <span>You have been logged out successfully.</span>
            </div>
        <?php endif; ?>
        
        <?php if(isset($_GET['reset']) && $_GET['reset'] == 'success'): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span>Password reset successfully! Please login with your new password.</span>
            </div>
        <?php endif; ?>
        
        <?php if(isset($_GET['error']) && $_GET['error'] === 'login'): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <span>Please sign in to continue.</span>
            </div>
        <?php endif; ?>
        
        <form class="login-form" id="loginForm" method="POST" action="">
            <div class="form-group">
                <label for="loginEmail">
                    <i class="fas fa-envelope"></i> Email Address
                </label>
                <div class="input-wrapper">
                    <input 
                        type="email" 
                        id="loginEmail" 
                        name="email" 
                        placeholder="Enter your email" 
                        required 
                        autofocus
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ($_COOKIE['remember_email'] ?? '')); ?>"
                    >
                    <span class="input-icon"><i class="fas fa-envelope"></i></span>
                </div>
            </div>
            
            <div class="form-group">
                <label for="loginPassword">
                    <i class="fas fa-lock"></i> Password
                </label>
                <div class="input-wrapper">
                    <input 
                        type="password" 
                        id="loginPassword" 
                        name="password" 
                        placeholder="Enter your password" 
                        required
                        value="<?php echo htmlspecialchars($_COOKIE['remember_password'] ?? ''); ?>"
                    >
                    <button type="button" class="toggle-password" onclick="togglePassword()">
                        <i class="fas fa-eye" id="passwordIcon"></i>
                    </button>
                </div>
            </div>
            
            <div class="options-row">
                <label class="remember">
                    <input type="checkbox" name="remember" id="remember" 
                        <?php echo isset($_COOKIE['remember_email']) ? 'checked' : ''; ?>>
                    Remember Me
                </label>
                <div class="d-flex gap-3">
                    <a class="forgot-link" onclick="openForgotPassword()">
                        <i class="fas fa-key"></i> Forgot Password?
                    </a>
                    
                </div>
            </div>
            
            <button type="submit" class="btn-login" id="loginBtn">
                <i class="fas fa-sign-in-alt"></i> Sign In
            </button>
        </form>
        
        <div class="login-footer">
            <p>
                © 2026 <a href="#">Alpine Healthcare</a> — 
                Built with <span class="heart">❤️</span> for better healthcare
            </p>
            <p style="margin-top:4px; font-size:11px; color:rgba(255,255,255,0.12);">
                <i class="fas fa-shield-alt"></i> Secure · Encrypted · Private
            </p>
        </div>
    </div>
</div>

<!-- ============================================
     FORGOT PASSWORD MODAL
     ============================================ -->
<div class="modal-overlay" id="forgotModal">
    <div class="modal-content">
        <button class="close-btn" onclick="closeForgotPassword()">
            <i class="fas fa-times"></i>
        </button>
        
        <div class="modal-header">
            <span class="icon">🔑</span>
            <h2>Reset <span>Password</span></h2>
            <p>Enter your email address and we'll send you a password reset link</p>
        </div>
        
        <div id="forgotMessage"></div>
        
        <form id="forgotForm">
            <div class="form-group">
                <label for="resetEmail">Email Address</label>
                <input 
                    type="email" 
                    id="resetEmail" 
                    placeholder="Enter your registered email" 
                    required
                >
            </div>
            
            <button type="button" class="btn-reset" id="resetBtn" onclick="handleForgotPassword()">
                <i class="fas fa-paper-plane"></i> Send Reset Link
            </button>
            
            <button type="button" class="btn-secondary" onclick="closeForgotPassword()">
                <i class="fas fa-arrow-left"></i> Back to Login
            </button>
        </form>
    </div>
</div>

<script>
    // ============================================
    // PARTICLES
    // ============================================
    function createParticles() {
        const container = document.getElementById('particles');
        const count = 25;
        for (let i = 0; i < count; i++) {
            const particle = document.createElement('div');
            particle.className = 'particle';
            particle.style.left = Math.random() * 100 + '%';
            particle.style.width = (Math.random() * 4 + 2) + 'px';
            particle.style.height = particle.style.width;
            particle.style.animationDuration = (Math.random() * 20 + 15) + 's';
            particle.style.animationDelay = (Math.random() * 15) + 's';
            particle.style.opacity = Math.random() * 0.4 + 0.1;
            container.appendChild(particle);
        }
    }
    createParticles();
    
    // ============================================
    // TOGGLE PASSWORD
    // ============================================
    function togglePassword() {
        const passwordInput = document.getElementById('loginPassword');
        const icon = document.getElementById('passwordIcon');
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            icon.className = 'fas fa-eye-slash';
        } else {
            passwordInput.type = 'password';
            icon.className = 'fas fa-eye';
        }
    }
    
    // ============================================
    // FORGOT PASSWORD MODAL
    // ============================================
    function openForgotPassword() {
        document.getElementById('forgotModal').classList.add('active');
        document.body.style.overflow = 'hidden';
        setTimeout(() => document.getElementById('resetEmail').focus(), 300);
        document.getElementById('forgotMessage').innerHTML = '';
    }
    
    function closeForgotPassword() {
        document.getElementById('forgotModal').classList.remove('active');
        document.body.style.overflow = '';
        document.getElementById('forgotMessage').innerHTML = '';
        document.getElementById('resetBtn').disabled = false;
        document.getElementById('resetBtn').innerHTML = '<i class="fas fa-paper-plane"></i> Send Reset Link';
    }
    
    document.getElementById('forgotModal').addEventListener('click', function(e) {
        if (e.target === this) closeForgotPassword();
    });
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') { closeForgotPassword(); }
    });
    
    // ============================================
    // HANDLE FORGOT PASSWORD (AJAX)
    // ============================================
    function handleForgotPassword() {
        const email = document.getElementById('resetEmail').value.trim();
        const btn = document.getElementById('resetBtn');
        const messageDiv = document.getElementById('forgotMessage');
        
        if (!email) {
            messageDiv.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <span>Please enter your email address.</span>
                </div>
            `;
            return;
        }
        
        if (!email.includes('@') || !email.includes('.')) {
            messageDiv.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <span>Please enter a valid email address.</span>
                </div>
            `;
            return;
        }
        
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
        btn.disabled = true;
        messageDiv.innerHTML = '';
        
        // AJAX request to login.php
        fetch('login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'forgot_password',
                email: email
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                messageDiv.innerHTML = `
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <span>${data.message}</span>
                        ${data.debug_link ? `<br><small style="color:#888;font-size:11px;">🔗 Debug: <a href="${data.debug_link}" target="_blank" style="color:#2ecc71;">${data.debug_link}</a></small>` : ''}
                    </div>
                `;
                btn.innerHTML = '<i class="fas fa-check"></i> Sent!';
                
                // Close after 5 seconds
                setTimeout(() => closeForgotPassword(), 5000);
            } else {
                messageDiv.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <span>${data.message}</span>
                    </div>
                `;
                btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Reset Link';
                btn.disabled = false;
            }
        })
        .catch(error => {
            messageDiv.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <span>An error occurred. Please try again.</span>
                </div>
            `;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Reset Link';
            btn.disabled = false;
        });
    }
    
    // ============================================
    // FILL DEMO CREDENTIALS
    // ============================================
    function fillCredentials(email, password) {
        document.getElementById('loginEmail').value = email;
        document.getElementById('loginPassword').value = password;
        
        const items = document.querySelectorAll('.cred-item');
        items.forEach(item => {
            item.style.borderColor = 'rgba(46, 204, 113, 0.3)';
            setTimeout(() => {
                item.style.borderColor = 'rgba(255, 255, 255, 0.03)';
            }, 1500);
        });
        
        setTimeout(() => {
            document.getElementById('loginForm').submit();
        }, 300);
    }
    
    // ============================================
    // LOGIN FORM SUBMIT
    // ============================================
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        const email = document.getElementById('loginEmail').value.trim();
        const password = document.getElementById('loginPassword').value.trim();
        const btn = document.getElementById('loginBtn');
        
        if (!email || !password) {
            e.preventDefault();
            showError('Please fill in all fields');
            return;
        }
        
        if (!email.includes('@') || !email.includes('.')) {
            e.preventDefault();
            showError('Please enter a valid email address');
            return;
        }
        
        if (password.length < 4) {
            e.preventDefault();
            showError('Password must be at least 4 characters');
            return;
        }
        
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing in...';
        btn.disabled = true;
    });
    
    function showError(message) {
        const existingAlerts = document.querySelectorAll('.alert-danger');
        existingAlerts.forEach(el => el.remove());
        
        const alert = document.createElement('div');
        alert.className = 'alert alert-danger';
        alert.innerHTML = `
            <i class="fas fa-exclamation-circle"></i>
            <span>${message}</span>
        `;
        
        const brand = document.querySelector('.brand');
        brand.parentNode.insertBefore(alert, brand.nextSibling);
        
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.5s ease';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    }
    
    // ============================================
    // KEYBOARD SHORTCUTS
    // ============================================
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.shiftKey && e.key === 'H') {
            e.preventDefault();
            window.location.href = 'index.php';
        }
        if (e.key === 'Escape') {
            document.activeElement?.blur();
            closeForgotPassword();
        }
    });
    
    // ============================================
    // AUTO FOCUS
    // ============================================
    const hasError = document.querySelector('.alert-danger');
    if (hasError) {
        document.getElementById('loginEmail').focus();
    } else {
        const savedEmail = document.getElementById('loginEmail').value;
        if (!savedEmail) {
            document.getElementById('loginEmail').focus();
        } else {
            document.getElementById('loginPassword').focus();
        }
    }
</script>

</body>
</html>