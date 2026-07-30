<?php
// modules/reception/patients_add.php - Add Patient
require_once '../../core/auth.php';
requireLogin();
require_once '../../config/database.php';

$user = getCurrentUser($pdo);
$user_data = $user ?: [];
if (empty($user_data)) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $dob = $_POST['dob'] ?? null;
    $email = trim($_POST['email'] ?? '');
    
    if (empty($fullname) || empty($gender) || empty($phone)) {
        $error = 'Name, gender, and phone are required.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM patients WHERE phone = ?");
        $stmt->execute([$phone]);
        if ($stmt->fetch()) {
            $error = 'Patient with this phone number already exists.';
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO patients (fullname, gender, phone, address, dob, email) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$fullname, $gender, $phone, $address, $dob, $email]);
            
            $_SESSION['success'] = 'Patient registered successfully.';
            header('Location: patients.php');
            exit;
        }
    }
}

$page_title = 'Register Patient';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Alpine Healthcare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        * { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; }
        body { background: #f4f6fb; }
        .sidebar .avatar img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; }
        .form-label { font-weight: 600; font-size: 14px; }
        .form-control:focus { border-color: #2ecc71; box-shadow: 0 0 0 0.2rem rgba(46, 204, 113, 0.15); }
        .required { color: #e74c3c; }
    </style>
</head>
<body>
    <div class="container-fluid p-0">
        <div class="row g-0">
            <!-- Sidebar -->
            <nav class="col-md-2 sidebar">
                <div class="brand">
                    <h4>🏔️ <span>Alpine</span></h4>
                    <small>Reception Panel</small>
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
                        <div class="user-role"><i class="bi bi-shield-check"></i> <?php echo ucfirst($user_data['role'] ?? 'receptionist'); ?></div>
                    </div>
                </div>
                <div class="nav-section">Main Menu</div>
                <a href="rec_dashboard.php" class="nav-item">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <a href="patients.php" class="nav-item active">
                    <i class="bi bi-people"></i> Patients
                </a>
                <a href="appointments.php" class="nav-item">
                    <i class="bi bi-calendar-event"></i> Appointments
                </a>
                <a href="billing.php" class="nav-item">
                    <i class="bi bi-receipt"></i> Billing
                </a>
                <a href="profile.php" class="nav-item">
                    <i class="bi bi-person"></i> Profile
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
                        <h1><i class="bi bi-person-plus"></i> <?php echo $page_title; ?></h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item active"><?php echo $page_title; ?></li>
                            </ol>
                        </nav>
                    </div>
                    <div>
                        <a href="patients.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Back
                        </a>
                    </div>
                </div>

                <?php if($error): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-person-plus"></i> Patient Information
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Full Name <span class="required">*</span></label>
                                    <input type="text" name="fullname" class="form-control" 
                                           value="<?php echo htmlspecialchars($_POST['fullname'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Gender <span class="required">*</span></label>
                                    <select name="gender" class="form-select" required>
                                        <option value="">Select Gender</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Phone <span class="required">*</span></label>
                                    <input type="tel" name="phone" class="form-control" 
                                           value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Date of Birth</label>
                                    <input type="date" name="dob" class="form-control" 
                                           value="<?php echo htmlspecialchars($_POST['dob'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" 
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Address</label>
                                    <textarea name="address" class="form-control" rows="2"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-circle"></i> Register Patient
                                    </button>
                                    <a href="patients.php" class="btn btn-secondary">Cancel</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>