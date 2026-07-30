<?php
// reset_password_direct.php - Simple Password Reset (No Email)
require_once 'core/auth.php';
require_once 'config/database.php';

$error = '';
$success = '';

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate
    if (empty($email)) {
        $error = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($new_password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        // Check if user exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            $error = 'No account found with this email address.';
        } else {
            // Update password directly
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed, $user['id']]);
            
            $success = 'Password reset successfully! You can now login.';
            
            // Redirect after 2 seconds
            header('Refresh: 2; url=login.php');
        }
    }
}

$page_title = 'Reset Password';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Alpine Healthcare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; }
        body { 
            background: linear-gradient(135deg, #0a1628 0%, #1a2332 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }
        .card { 
            border: none; 
            border-radius: 16px; 
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            background: #1a1a1a;
            color: white;
            width: 100%;
            max-width: 440px;
        }
        .card-header { 
            background: transparent; 
            border-bottom: 1px solid rgba(255,255,255,0.05); 
            padding: 1.5rem;
            text-align: center;
        }
        .card-header h3 { margin: 0; font-weight: 700; }
        .card-header h3 span { color: #2ecc71; }
        .card-header p { color: rgba(255,255,255,0.5); margin: 4px 0 0; }
        .card-body { padding: 1.5rem; }
        
        .form-control {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.08);
            color: white;
            padding: 12px 16px;
            border-radius: 10px;
            width: 100%;
            font-size: 15px;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            outline: none;
            border-color: #2ecc71;
            box-shadow: 0 0 0 3px rgba(46, 204, 113, 0.15);
            background: rgba(255,255,255,0.08);
            color: white;
        }
        .form-control::placeholder { color: rgba(255,255,255,0.25); }
        .form-label { 
            font-weight: 600; 
            font-size: 14px; 
            color: rgba(255,255,255,0.7);
            display: block;
            margin-bottom: 6px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            border: none;
            padding: 12px;
            font-weight: 600;
            border-radius: 10px;
            color: white;
            width: 100%;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-primary:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 8px 25px rgba(46, 204, 113, 0.3); 
        }
        .btn-secondary { 
            background: rgba(255,255,255,0.05); 
            border: 1px solid rgba(255,255,255,0.08);
            color: rgba(255,255,255,0.6);
            padding: 12px;
            border-radius: 10px;
            width: 100%;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            text-decoration: none;
            display: block;
            margin-top: 10px;
        }
        .btn-secondary:hover { background: rgba(255,255,255,0.1); color: white; }
        
        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 16px;
        }
        .alert-danger { background: rgba(231, 76, 60, 0.12); border: 1px solid rgba(231, 76, 60, 0.2); color: #e74c3c; }
        .alert-success { background: rgba(46, 204, 113, 0.12); border: 1px solid rgba(46, 204, 113, 0.2); color: #2ecc71; }
        .alert-info { background: rgba(52, 152, 219, 0.12); border: 1px solid rgba(52, 152, 219, 0.2); color: #3498db; }
        
        .text-muted { color: rgba(255,255,255,0.5); }
        .text-center { text-align: center; }
        .mb-3 { margin-bottom: 1rem; }
        .mt-3 { margin-top: 1rem; }
        .mt-2 { margin-top: 0.5rem; }
        
        .demo-credentials {
            margin-top: 16px;
            padding: 12px;
            background: rgba(255,255,255,0.03);
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.05);
        }
        .demo-credentials small { color: rgba(255,255,255,0.3); }
        .demo-credentials code { 
            background: rgba(46,204,113,0.1); 
            color: #2ecc71; 
            padding: 1px 8px; 
            border-radius: 4px; 
            font-size: 12px; 
        }
        
        @media (max-width: 480px) {
            .card-header { padding: 1rem; }
            .card-body { padding: 1rem; }
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="card-header">
            <h3>🏔️ <span>Alpine</span> Healthcare</h3>
            <p>Reset Your Password</p>
        </div>
        <div class="card-body">
            
            <?php if($success): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle"></i> <?php echo $success; ?>
                    <br>
                    <small>Redirecting to login...</small>
                </div>
            <?php endif; ?>
            
            <?php if($error): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if(!$success): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> 
                    Enter your email and new password. No email confirmation needed.
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" 
                           placeholder="Enter your registered email" 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                           required <?php echo $success ? 'disabled' : ''; ?>>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">New Password</label>
                    <input type="password" name="new_password" class="form-control" 
                           placeholder="Enter new password (min 6 characters)" 
                           required minlength="6" <?php echo $success ? 'disabled' : ''; ?>>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Confirm Password</label>
                    <input type="password" name="confirm_password" class="form-control" 
                           placeholder="Confirm new password" 
                           required <?php echo $success ? 'disabled' : ''; ?>>
                </div>
                
                <button type="submit" class="btn-primary" <?php echo $success ? 'disabled' : ''; ?>>
                    <i class="bi bi-check-circle"></i> Reset Password
                </button>
                
                <a href="login.php" class="btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Login
                </a>
            </form>
            
            </div>
        </div>
    </div>
</body>
</html>