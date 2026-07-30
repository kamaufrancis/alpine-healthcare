<?php
require_once '../../core/auth.php';
requireAdmin();
require_once '../../config/database.php';

$user = getCurrentUser($pdo);
$error = '';
$success = '';

$current_user = $_SESSION['user_name'] ?? 'Administrator';
$current_user_id = $_SESSION['user_id'] ?? 0;

$stmt = $pdo->prepare("SELECT photo FROM users WHERE id = ?");
$stmt->execute([$current_user_id]);
$user_photo = $stmt->fetchColumn();

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $specialization = trim($_POST['specialization'] ?? '');
    
    // Validation
    if(empty($fullname)) {
        $error = 'Full name is required!';
    } elseif(empty($email)) {
        $error = 'Email is required!';
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address!';
    } elseif(empty($password)) {
        $error = 'Password is required!';
    } elseif(strlen($password) < 4) {
        $error = 'Password must be at least 4 characters!';
    } elseif(empty($role)) {
        $error = 'Role is required!';
    } elseif($role == 'doctor' && empty($specialization)) {
        $error = 'Specialization is required for doctors!';
    } else {
        try {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if($stmt->rowCount() > 0) {
                $error = 'Email already exists!';
            } else {
                // Insert user
                $stmt = $pdo->prepare("INSERT INTO users (fullname, email, password, role) VALUES (?, ?, ?, ?)");
                if ($stmt->execute([$fullname, $email, $password, $role])) {
                    $user_id = $pdo->lastInsertId();
                    
                    // If role is doctor, also create doctor record
                    if($role == 'doctor') {
                        $stmt = $pdo->prepare("INSERT INTO doctors (fullname, email, specialization, phone, availability) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$fullname, $email, $specialization, 'N/A', 'Mon-Fri 9AM-5PM']);
                    }
                    
                    // Log activity
                    try {
                        require_once '../../core/logger.php';
                        $user_id_session = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
                        logActivity($pdo, $user_id_session, 'Added new user', $fullname . ' - Role: ' . $role);
                    } catch (Exception $e) {
                        error_log("Failed to log activity: " . $e->getMessage());
                    }
                    
                    header('Location: users.php?added=1');
                    exit;
                } else {
                    $error = 'Failed to add user!';
                }
            }
        } catch(PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add User - Alpine Healthcare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; }
        body { background: #f0f2f5; }
        
        .sidebar {
            min-height: 100vh;
            background: #1a2332;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        
        .sidebar::-webkit-scrollbar { width: 4px; }
        .sidebar::-webkit-scrollbar-thumb { background: #e74c3c; border-radius: 2px; }
        
        .sidebar .brand {
            padding: 20px 20px 15px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            text-align: center;
        }
        
        .sidebar .brand h4 { color: white; font-weight: 700; margin: 0; }
        .sidebar .brand h4 span { color: #e74c3c; }
        .sidebar .brand small { color: rgba(255,255,255,0.4); font-size: 11px; letter-spacing: 1px; text-transform: uppercase; }
        
        .sidebar .user-info {
            padding: 15px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            margin-bottom: 10px;
        }
        
        .sidebar .user-info .avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 18px;
            flex-shrink: 0;
        }
        .sidebar .avatar img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; }
        .sidebar .user-info .user-name { color: white; font-weight: 600; font-size: 14px; margin-bottom: 2px; }
        .sidebar .user-info .user-role { color: rgba(255,255,255,0.4); font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; }
        
        .sidebar .nav-section {
            padding: 10px 20px 5px;
            color: rgba(255,255,255,0.3);
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }
        
        .sidebar .nav-item {
            color: rgba(255,255,255,0.6);
            text-decoration: none;
            padding: 10px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
            font-size: 14px;
            font-weight: 500;
        }
        
        .sidebar .nav-item:hover { background: rgba(255,255,255,0.05); color: white; border-left-color: #e74c3c; }
        .sidebar .nav-item.active { background: rgba(231, 76, 60, 0.1); color: #e74c3c; border-left-color: #e74c3c; }
        .sidebar .nav-item i { width: 20px; font-size: 16px; }
        
        .main-content { padding: 20px 30px; }
        
        .page-header {
            background: white;
            padding: 20px 25px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.04);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .page-header h1 { font-size: 24px; font-weight: 700; color: #1a2332; margin: 0; }
        .page-header h1 i { color: #e74c3c; margin-right: 10px; }
        
        .form-container { max-width: 600px; margin: 0 auto; }
        .form-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.04);
            padding: 30px;
            transition: all 0.3s ease;
        }
        
        .form-card:hover { box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
        
        .required-field::after { content: " *"; color: #dc3545; }
        
        .input-icon-wrapper { position: relative; }
        .input-icon-wrapper .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        .input-icon-wrapper .form-control,
        .input-icon-wrapper .form-select {
            padding-left: 45px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            border: none;
            padding: 10px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(231, 76, 60, 0.3);
        }
        
        .role-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 12px;
        }
        
        .role-badge.admin { background: #e74c3c; color: white; }
        .role-badge.doctor { background: #3498db; color: white; }
        .role-badge.receptionist { background: #2ecc71; color: white; }
        .role-badge.pharmacist { background: #9b59b6; color: white; }
        
        @media (max-width: 768px) {
            .sidebar { min-height: auto; height: auto; position: relative; }
            .main-content { padding: 15px; }
            .form-card { padding: 20px; }
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
                    <small>Admin Panel</small>
                </div>
                
                <div class="user-info d-flex align-items-center gap-3">
                    <div class="avatar">
                        <?php if(!empty($user_photo)): ?>
                            <img src="<?php echo htmlspecialchars($user_photo); ?>" alt="Profile">
                        <?php else: ?>
                            <?php echo strtoupper(substr($current_user, 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="user-name"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Administrator'); ?></div>
                        <div class="user-role"><i class="bi bi-shield-check"></i> <?php echo ucfirst($_SESSION['role'] ?? 'admin'); ?></div>
                    </div>
                </div>
                
                <div class="nav-section">Main Menu</div>
                <a href="../../dashboard.php" class="nav-item">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <a href="users.php" class="nav-item">
                    <i class="bi bi-people"></i> User Management
                </a>
                <a href="add_user.php" class="nav-item active">
                    <i class="bi bi-person-plus"></i> Add User
                </a>
                <a href="patients.php" class="nav-item">
                    <i class="bi bi-person"></i> Patients
                </a>
                
                <a href="appointments.php" class="nav-item">
                    <i class="bi bi-calendar-event"></i> Appointments
                </a>
                <a href="billing.php" class="nav-item">
                    <i class="bi bi-receipt"></i> Billing
                </a>
                <a href="pharmacy.php" class="nav-item">
                    <i class="bi bi-capsule"></i> Pharmacy
                </a>
                <a href="profile.php" class="nav-item">
                    <i class="bi bi-person"></i> Profile
                </a>
                <div class="nav-section">Account</div>
                <a href="profile.php" class="nav-item">
                    <i class="bi bi-person-circle"></i> Profile
                </a>
                <a href="../../logout.php" class="nav-item">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </nav>

            <!-- Main Content -->
            <main class="col-md-10 main-content">
                <div class="page-header">
                    <h1><i class="bi bi-person-plus"></i> Add New User</h1>
                    <a href="users.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Users
                    </a>
                </div>

                <?php if($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="form-container">
                    <div class="form-card">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="fullname" class="form-label required-field">
                                        <i class="bi bi-person"></i> Full Name
                                    </label>
                                    <div class="input-icon-wrapper">
                                        <i class="bi bi-person input-icon"></i>
                                        <input type="text" class="form-control form-control-lg" id="fullname" name="fullname" 
                                               placeholder="Enter full name" required
                                               value="<?php echo htmlspecialchars($_POST['fullname'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="email" class="form-label required-field">
                                        <i class="bi bi-envelope"></i> Email Address
                                    </label>
                                    <div class="input-icon-wrapper">
                                        <i class="bi bi-envelope input-icon"></i>
                                        <input type="email" class="form-control form-control-lg" id="email" name="email" 
                                               placeholder="Enter email address" required
                                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="password" class="form-label required-field">
                                        <i class="bi bi-lock"></i> Password
                                    </label>
                                    <div class="input-icon-wrapper">
                                        <i class="bi bi-lock input-icon"></i>
                                        <input type="password" class="form-control form-control-lg" id="password" name="password" 
                                               placeholder="Enter password (min 4 characters)" required>
                                    </div>
                                    <small class="text-muted">Password must be at least 4 characters</small>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="role" class="form-label required-field">
                                        <i class="bi bi-tag"></i> Role
                                    </label>
                                    <div class="input-icon-wrapper">
                                        <i class="bi bi-tag input-icon"></i>
                                        <select class="form-select form-select-lg" id="role" name="role" required>
                                            <option value="">Select Role</option>
                                            <option value="admin" <?php echo (isset($_POST['role']) && $_POST['role'] == 'admin') ? 'selected' : ''; ?>>Administrator</option>
                                            <option value="doctor" <?php echo (isset($_POST['role']) && $_POST['role'] == 'doctor') ? 'selected' : ''; ?>>Doctor</option>
                                            <option value="receptionist" <?php echo (isset($_POST['role']) && $_POST['role'] == 'receptionist') ? 'selected' : ''; ?>>Receptionist</option>
                                            <option value="pharmacist" <?php echo (isset($_POST['role']) && $_POST['role'] == 'pharmacist') ? 'selected' : ''; ?>>Pharmacist</option>
                                        </select>
                                    </div>
                                    <small class="text-muted">
                                        <i class="bi bi-info-circle"></i> 
                                        <span class="role-badge admin">Admin</span> Full system access
                                        <span class="role-badge doctor ms-2">Doctor</span> Medical access
                                        <span class="role-badge receptionist ms-2">Receptionist</span> Front desk access
                                        <span class="role-badge pharmacist ms-2">Pharmacist</span> Pharmacy access
                                    </small>
                                </div>
                            </div>

                            <div class="row" id="specialization-row" style="display: none;">
                                <div class="col-md-12 mb-3">
                                    <label for="specialization" class="form-label required-field">
                                        <i class="bi bi-briefcase"></i> Specialization
                                    </label>
                                    <div class="input-icon-wrapper">
                                        <i class="bi bi-briefcase input-icon"></i>
                                        <select class="form-select form-select-lg" id="specialization" name="specialization">
                                            <option value="">Select Specialization</option>
                                            <option value="General Practice" <?php echo ((isset($_POST['specialization']) && $_POST['specialization'] == 'General Practice') ? 'selected' : ''); ?>>General Practice</option>
                                            <option value="Cardiology" <?php echo ((isset($_POST['specialization']) && $_POST['specialization'] == 'Cardiology') ? 'selected' : ''); ?>>Cardiology</option>
                                            <option value="Dermatology" <?php echo ((isset($_POST['specialization']) && $_POST['specialization'] == 'Dermatology') ? 'selected' : ''); ?>>Dermatology</option>
                                            <option value="Pediatrics" <?php echo ((isset($_POST['specialization']) && $_POST['specialization'] == 'Pediatrics') ? 'selected' : ''); ?>>Pediatrics</option>
                                            <option value="Orthopedics" <?php echo ((isset($_POST['specialization']) && $_POST['specialization'] == 'Orthopedics') ? 'selected' : ''); ?>>Orthopedics</option>
                                            <option value="Neurology" <?php echo ((isset($_POST['specialization']) && $_POST['specialization'] == 'Neurology') ? 'selected' : ''); ?>>Neurology</option>
                                            <option value="Obstetrics & Gynecology" <?php echo ((isset($_POST['specialization']) && $_POST['specialization'] == 'Obstetrics & Gynecology') ? 'selected' : ''); ?>>Obstetrics & Gynecology</option>
                                            <option value="Psychiatry" <?php echo ((isset($_POST['specialization']) && $_POST['specialization'] == 'Psychiatry') ? 'selected' : ''); ?>>Psychiatry</option>
                                            <option value="Dental" <?php echo ((isset($_POST['specialization']) && $_POST['specialization'] == 'Dental') ? 'selected' : ''); ?>>Dental</option>
                                            <option value="ENT" <?php echo ((isset($_POST['specialization']) && $_POST['specialization'] == 'ENT') ? 'selected' : ''); ?>>ENT</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex gap-2 mt-4">
                                <button type="submit" class="btn btn-primary btn-lg px-5">
                                    <i class="bi bi-check-lg"></i> Add User
                                </button>
                                <button type="reset" class="btn btn-outline-secondary btn-lg px-4">
                                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                                </button>
                                <a href="users.php" class="btn btn-outline-danger btn-lg px-4">
                                    <i class="bi bi-x-lg"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script>
        const roleSelect = document.getElementById('role');
        const specializationRow = document.getElementById('specialization-row');
        const specializationSelect = document.getElementById('specialization');

        function toggleSpecializationField() {
            const isDoctor = roleSelect.value === 'doctor';
            specializationRow.style.display = isDoctor ? 'block' : 'none';
            if (!isDoctor) {
                specializationSelect.value = '';
            }
        }

        roleSelect.addEventListener('change', toggleSpecializationField);
        toggleSpecializationField();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>