<?php
// modules/pharmacy/profile.php - Pharmacy Profile
require_once '../../core/auth.php';
requireLogin();
require_once '../../config/database.php';

// Ensure only pharmacy staff can access
if ($_SESSION['role'] !== 'pharmacist' && $_SESSION['role'] !== 'admin') {
    header('Location: ../../dashboard.php');
    exit;
}

$user = getCurrentUser($pdo);
$message = '';
$error = '';

// Get user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $fullname = trim($_POST['fullname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $photo = trim($_POST['photo'] ?? '');
        
        if (empty($fullname) || empty($email)) {
            $error = 'Name and email are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            // Check if email exists (excluding current user)
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $_SESSION['user_id']]);
            if ($stmt->fetch()) {
                $error = 'Email already exists.';
            } else {
                $stmt = $pdo->prepare("UPDATE users SET fullname = ?, email = ?, phone = ?, photo = ? WHERE id = ?");
                $stmt->execute([$fullname, $email, $phone, $photo, $_SESSION['user_id']]);
                
                $_SESSION['user_name'] = $fullname;
                
                $message = 'Profile updated successfully.';
                
                // Refresh user data
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        }
    }
    
    // Handle password change
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = 'All password fields are required.';
        } elseif (strlen($new_password) < 6) {
            $error = 'New password must be at least 6 characters.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } else {
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (password_verify($current_password, $user['password']) || $current_password === $user['password']) {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed, $_SESSION['user_id']]);
                
                $message = 'Password changed successfully.';
            } else {
                $error = 'Current password is incorrect.';
            }
        }
    }
}

$page_title = 'My Profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Alpine Healthcare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; }
        body { background: #f4f6fb; }
        
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, #1a2332 0%, #111827 100%);
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0,0,0,0.08);
        }
        .sidebar .brand { padding: 20px 20px 15px; border-bottom: 1px solid rgba(255,255,255,0.08); text-align: center; }
        .sidebar .brand h4 { color: #fff; font-weight: 700; margin: 0; }
        .sidebar .brand h4 span { color: #2ecc71; }
        .sidebar .brand small { color: rgba(255,255,255,0.4); font-size: 11px; letter-spacing: 1px; text-transform: uppercase; }
        .sidebar .user-info { padding: 15px 20px; border-bottom: 1px solid rgba(255,255,255,0.5); }
        .sidebar .avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            color: #fff;
            font-weight: 700;
            font-size: 18px;
            flex-shrink: 0;
            overflow: hidden;
            background: linear-gradient(135deg, #2ecc71, #27ae60);
        }
        .sidebar .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .sidebar .user-name { color: #fff; font-weight: 600; font-size: 14px; }
        .sidebar .user-role { color: rgba(255,255,255,0.4); font-size: 12px; }
        .sidebar .nav-section { padding: 15px 20px 5px; color: rgba(255,255,255,0.3); font-size: 10px; text-transform: uppercase; letter-spacing: 1px; font-weight: 600; }
        .sidebar .nav-item {
            color: rgba(255,255,255,0.6); text-decoration: none; padding: 10px 20px;
            display: flex; align-items: center; gap: 12px;
            transition: all 0.2s ease; border-left: 3px solid transparent;
            font-size: 14px; font-weight: 500; cursor: pointer;
        }
        .sidebar .nav-item:hover { background: rgba(255,255,255,0.72); color: #fff; border-left-color: #2ecc71; }
        .sidebar .nav-item.active { background: rgba(46, 204, 113, 0.12); color: #fff; border-left-color: #2ecc71; }
        .sidebar .nav-item .badge { margin-left: auto; background: rgba(255, 255, 255, 0.14); color: #fff; border-radius: 999px; padding: 2px 8px; font-size: 11px; }
        .sidebar .nav-item i { width: 20px; text-align: center; }
        
        .main-content { padding: 20px 30px; background: #f4f6fb; min-height: 100vh; }
        .page-header {
            background: #fff; padding: 20px 25px; border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.04); margin-bottom: 25px;
            display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;
            border: 1px solid #e9eef4;
        }
        .page-header h1 { margin: 0; font-size: 1.5rem; font-weight: 700; }
        .page-header .breadcrumb { margin: 0; padding: 0; background: transparent; }
        
        .card { border: 1px solid #e9eef4; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.04); background: #fff; }
        .card-header { background: transparent; border-bottom: 1px solid #e9eef4; padding: 1rem 1.1rem; font-weight: 600; display: flex; justify-content: space-between; align-items: center; }
        .card-body { padding: 1rem 1.1rem; }
        
        .form-label { font-weight: 600; font-size: 14px; }
        .form-control:focus { border-color: #2ecc71; box-shadow: 0 0 0 0.2rem rgba(155, 89, 182, 0.15); }
        .required { color: #e74c3c; }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            font-weight: 700;
            color: #fff;
            margin: 0 auto 15px;
            overflow: hidden;
            border: 4px solid #fff;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            background: linear-gradient(135deg, #2ecc71, #27ae60);
        }
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .btn-primary { 
            background: linear-gradient(135deg, #2ecc71, #27ae60); 
            border: none; 
        }
        .btn-primary:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 5px 20px rgba(46, 204, 113, 0.3); 
        }
        
        .stat-box {
            text-align: center; padding: 15px 10px;
            background: #f8fafc; border-radius: 10px;
            transition: all 0.3s ease;
            border: 1px solid #e9eef4;
        }
        .stat-box:hover { background: #fff; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .stat-box .number { font-size: 24px; font-weight: 700; color: #2ecc71; }
        .stat-box .label { font-size: 12px; color: #6c757d; margin-top: 4px; }
        .stat-box .icon { font-size: 20px; margin-bottom: 5px; display: block; }
        
        @media (max-width: 768px) {
            .sidebar { min-height: auto; height: auto; position: relative; }
            .main-content { padding: 15px; }
            .page-header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .profile-avatar { width: 80px; height: 80px; font-size: 32px; }
        }
    </style>
</head>
<body>
    <div class="container-fluid p-0">
        <div class="row g-0">
            <!-- Sidebar -->
            <nav class="col-md-2 sidebar">
                <div class="brand">
                    <h4>🏔️ <span>Alpine</span></h4>
                    <small>Pharmacy Management</small>
                </div>
                
                <div class="user-info d-flex align-items-center gap-3">
                    <div class="avatar">
                        <?php if(!empty($user_data['photo'])): ?>
                            <img src="<?php echo htmlspecialchars($user_data['photo']); ?>" alt="Profile">
                        <?php else: ?>
                            <?php echo strtoupper(substr($user_data['fullname'] ?? 'U', 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="user-name"><?php echo htmlspecialchars($user_data['fullname'] ?? 'User'); ?></div>
                        <div class="user-role"><i class="bi bi-shield-check"></i> <?php echo ucfirst($user_data['role'] ?? 'pharmacist'); ?></div>
                    </div>
                </div>
                
                 <div class="nav-section">Main Menu</div>
                <a href="index.php" class="nav-item">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <a href="stock.php" class="nav-item">
                    <i class="bi bi-capsule"></i> Medicines
                </a>
                <a href="add.php" class="nav-item">
                    <i class="bi bi-plus-circle"></i> Add Medicine
                </a>
                <a href="dispense.php" class="nav-item">
                    <i class="bi bi-prescription"></i> Dispense
                </a>
                <a href="profile.php" class="nav-item active">
                    <i class="bi bi-person-circle"></i> Profile
                </a>
                <div class="nav-section">Account</div>
                <a href="../../logout.php" class="nav-item">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </nav>

            <!-- Main Content -->
            <main class="col-md-10 main-content">
                <div class="page-header">
                    <div>
                        <h1><i class="bi bi-person-circle"></i> <?php echo $page_title; ?></h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item active"><?php echo $page_title; ?></li>
                            </ol>
                        </nav>
                    </div>
                    <div>
                        <span class="text-muted">
                            <i class="bi bi-clock"></i> <?php echo date('l, F j, Y - h:i A'); ?>
                        </span>
                    </div>
                </div>

                <?php if($message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="bi bi-check-circle"></i> <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Pharmacy Statistics -->
                <div class="row g-3 mb-4">
                    <div class="col-4 col-md-3">
                        <div class="stat-box">
                            <span class="icon"><i class="bi bi-capsule text-primary"></i></span>
                            <?php
                            $stmt = $pdo->query("SELECT COUNT(*) as total FROM medicines");
                            $total_medicines = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                            ?>
                            <div class="number"><?php echo $total_medicines; ?></div>
                            <div class="label">Total Medicines</div>
                        </div>
                    </div>
                    <div class="col-4 col-md-3">
                        <div class="stat-box">
                            <span class="icon"><i class="bi bi-box text-success"></i></span>
                            <?php
                            $stmt = $pdo->query("SELECT SUM(quantity) as total FROM medicines");
                            $total_stock = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
                            ?>
                            <div class="number"><?php echo $total_stock; ?></div>
                            <div class="label">Total Stock</div>
                        </div>
                    </div>
                    <div class="col-4 col-md-3">
                        <div class="stat-box">
                            <span class="icon"><i class="bi bi-exclamation-triangle text-warning"></i></span>
                            <?php
                            $stmt = $pdo->query("SELECT COUNT(*) as total FROM medicines WHERE quantity <= 10 AND quantity > 0");
                            $low_stock = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                            ?>
                            <div class="number"><?php echo $low_stock; ?></div>
                            <div class="label">Low Stock</div>
                        </div>
                    </div>
                    <div class="col-4 col-md-3">
                        <div class="stat-box">
                            <span class="icon"><i class="bi bi-x-circle text-danger"></i></span>
                            <?php
                            $stmt = $pdo->query("SELECT COUNT(*) as total FROM medicines WHERE quantity <= 0");
                            $out_of_stock = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                            ?>
                            <div class="number"><?php echo $out_of_stock; ?></div>
                            <div class="label">Out of Stock</div>
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <!-- Profile Information -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <span><i class="bi bi-person"></i> Profile Information</span>
                            </div>
                            <div class="card-body">
                                <div class="text-center mb-4">
                                    <div class="profile-avatar">
                                        <?php if(!empty($user_data['photo'])): ?>
                                            <img src="<?php echo htmlspecialchars($user_data['photo']); ?>" alt="Profile Photo">
                                        <?php else: ?>
                                            <?php echo strtoupper(substr($user_data['fullname'] ?? 'U', 0, 1)); ?>
                                        <?php endif; ?>
                                    </div>
                                    <h5><?php echo htmlspecialchars($user_data['fullname'] ?? 'User'); ?></h5>
                                    <span class="badge bg-primary"><?php echo ucfirst($user_data['role'] ?? 'pharmacist'); ?></span>
                                </div>

                                <form method="POST">
                                    <div class="mb-3">
                                        <label class="form-label">Full Name <span class="required">*</span></label>
                                        <input type="text" name="fullname" class="form-control" 
                                               value="<?php echo htmlspecialchars($user_data['fullname'] ?? ''); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Email <span class="required">*</span></label>
                                        <input type="email" name="email" class="form-control" 
                                               value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Phone</label>
                                        <input type="tel" name="phone" class="form-control" 
                                               value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Profile Photo URL</label>
                                        <input type="text" name="photo" class="form-control" 
                                               value="<?php echo htmlspecialchars($user_data['photo'] ?? ''); ?>"
                                               placeholder="https://example.com/photo.jpg">
                                        <small class="text-muted">Enter a URL for your profile photo</small>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Role</label>
                                        <input type="text" class="form-control" 
                                               value="<?php echo ucfirst($user_data['role'] ?? 'pharmacist'); ?>" disabled>
                                        <small class="text-muted">Role cannot be changed</small>
                                    </div>
                                    <button type="submit" name="update_profile" class="btn btn-primary">
                                        <i class="bi bi-check-circle"></i> Update Profile
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Change Password -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <span><i class="bi bi-lock"></i> Change Password</span>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="mb-3">
                                        <label class="form-label">Current Password <span class="required">*</span></label>
                                        <div class="input-group">
                                            <input type="password" name="current_password" class="form-control" 
                                                   placeholder="Enter current password" required>
                                            <button type="button" class="btn btn-outline-secondary" 
                                                    onclick="togglePassword('current_password')">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">New Password <span class="required">*</span></label>
                                        <div class="input-group">
                                            <input type="password" name="new_password" class="form-control" 
                                                   placeholder="Enter new password (min 6 characters)" required>
                                            <button type="button" class="btn btn-outline-secondary" 
                                                    onclick="togglePassword('new_password')">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Confirm New Password <span class="required">*</span></label>
                                        <div class="input-group">
                                            <input type="password" name="confirm_password" class="form-control" 
                                                   placeholder="Confirm new password" required>
                                            <button type="button" class="btn btn-outline-secondary" 
                                                    onclick="togglePassword('confirm_password')">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <button type="submit" name="change_password" class="btn btn-warning text-white">
                                        <i class="bi bi-key"></i> Change Password
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Account Activity -->
                <div class="row g-4 mt-2">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <span><i class="bi bi-clock-history"></i> Account Activity</span>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3 text-center">
                                        <div class="p-3 bg-light rounded">
                                            <h5><?php echo date('M d, Y', strtotime($user_data['created_at'] ?? 'now')); ?></h5>
                                            <small class="text-muted">Account Created</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <div class="p-3 bg-light rounded">
                                            <h5><?php echo date('M d, Y H:i', strtotime($user_data['updated_at'] ?? 'now')); ?></h5>
                                            <small class="text-muted">Last Updated</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <div class="p-3 bg-light rounded">
                                            <h5><?php echo date('M d, Y H:i'); ?></h5>
                                            <small class="text-muted">Last Login</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <div class="p-3 bg-light rounded">
                                            <h5><?php echo ucfirst($user_data['role'] ?? 'pharmacist'); ?></h5>
                                            <small class="text-muted">Account Role</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        function togglePassword(fieldId) {
            const field = document.querySelector(`[name="${fieldId}"]`);
            const icon = field.closest('.input-group').querySelector('.btn i');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.className = 'bi bi-eye-slash';
            } else {
                field.type = 'password';
                icon.className = 'bi bi-eye';
            }
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>