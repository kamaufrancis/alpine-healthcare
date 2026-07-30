<?php
// modules/doctor/profile.php - Doctor Profile (Using Unified Users Table)
require_once '../../core/auth.php';
requireDoctor();
require_once '../../config/database.php';

// Ensure doctor session is set
ensureDoctorSession($pdo);

$user = getCurrentUser($pdo);
$doctor_id = $_SESSION['user_id']; // Use the user_id as doctor_id since we're using unified table

// Get doctor information from users table (unified)
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$doctor_id]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);

$error = '';
$success = '';
$profile_message = '';
$active_profile = $_SESSION['active_profile'] ?? $user_data['active_profile'] ?? 'doctor';

// Handle profile update
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get data from form
    $fullname = trim($_POST['fullname'] ?? $user_data['fullname']);
    $email = trim($_POST['email'] ?? $user_data['email']);
    $phone = trim($_POST['phone'] ?? $user_data['phone'] ?? '');
    $specialization = trim($_POST['specialization'] ?? $user_data['specialization'] ?? '');
    $availability = trim($_POST['availability'] ?? $user_data['availability'] ?? '');
    $bio = trim($_POST['bio'] ?? $user_data['bio'] ?? '');
    $photo = trim($_POST['photo'] ?? $user_data['photo'] ?? '');
    $active_profile = $_POST['active_profile'] ?? 'doctor';
    
    if(empty($fullname)) {
        $error = 'Full name is required!';
    } elseif(empty($email)) {
        $error = 'Email is required!';
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address!';
    } else {
        try {
            // Update users table (unified)
            $stmt = $pdo->prepare("UPDATE users SET 
                                    fullname = ?, 
                                    email = ?, 
                                    phone = ?, 
                                    specialization = ?, 
                                    availability = ?, 
                                    bio = ?, 
                                    photo = ?,
                                    active_profile = ?,
                                    last_active = NOW()
                                   WHERE id = ?");
            
            $stmt->execute([
                $fullname, 
                $email, 
                $phone, 
                $specialization, 
                $availability, 
                $bio, 
                $photo,
                $active_profile, 
                $doctor_id
            ]);
            
            // Update session
            $_SESSION['user_name'] = $fullname;
            $_SESSION['doctor_name'] = $fullname;
            $_SESSION['specialization'] = $specialization;
            $_SESSION['active_profile'] = $active_profile;
            
            // Refresh user data
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$doctor_id]);
            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $success = 'Profile updated successfully!';
            
        } catch(PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Get statistics
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT patient_id) as total_patients FROM appointments WHERE doctor_id = ?");
$stmt->execute([$doctor_id]);
$total_patients = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$stmt = $pdo->prepare("SELECT COUNT(*) as total_appointments FROM appointments WHERE doctor_id = ?");
$stmt->execute([$doctor_id]);
$total_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$stmt = $pdo->prepare("SELECT COUNT(*) as completed FROM appointments WHERE doctor_id = ? AND status = 'Completed'");
$stmt->execute([$doctor_id]);
$completed_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['completed'] ?? 0;

$stmt = $pdo->prepare("SELECT COUNT(*) as pending FROM appointments WHERE doctor_id = ? AND status = 'Scheduled'");
$stmt->execute([$doctor_id]);
$pending_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['pending'] ?? 0;

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM prescriptions WHERE doctor_id = ?");
$stmt->execute([$doctor_id]);
$total_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$stmt = $pdo->prepare("SELECT COUNT(*) as pending FROM prescriptions WHERE doctor_id = ? AND status = 'pending'");
$stmt->execute([$doctor_id]);
$pending_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['pending'] ?? 0;

// Get next appointment
$stmt = $pdo->prepare("SELECT a.*, p.fullname as patient_name 
                       FROM appointments a 
                       JOIN patients p ON a.patient_id = p.id 
                       WHERE a.doctor_id = ? AND a.appointment_date > NOW() AND a.status = 'Scheduled'
                       ORDER BY a.appointment_date ASC LIMIT 1");
$stmt->execute([$doctor_id]);
$next_appointment = $stmt->fetch(PDO::FETCH_ASSOC);

// Get profile views and last active from users table
$profile_views = $user_data['profile_views'] ?? 0;
$last_active = $user_data['last_active'] ?? null;

// Increment profile views
if(!isset($_SESSION['profile_viewed'])) {
    try {
        $stmt = $pdo->prepare("UPDATE users SET profile_views = profile_views + 1 WHERE id = ?");
        $stmt->execute([$doctor_id]);
        $_SESSION['profile_viewed'] = true;
        $profile_views += 1;
    } catch(PDOException $e) {
        // Ignore
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
        .sidebar::-webkit-scrollbar { width: 4px; }
        .sidebar::-webkit-scrollbar-thumb { background: #2ecc71; border-radius: 2px; }
        
        .sidebar .brand { padding: 20px 20px 15px; border-bottom: 1px solid rgba(255,255,255,0.08); text-align: center; }
        .sidebar .brand h4 { color: #fff; font-weight: 700; margin: 0; }
        .sidebar .brand h4 span { color: #2ecc71; }
        .sidebar .brand small { color: rgba(255,255,255,0.65); font-size: 11px; letter-spacing: 1px; text-transform: uppercase; }
        
        .sidebar .user-info { padding: 15px 20px; border-bottom: 1px solid rgba(255,255,255,0.08); }
        .sidebar .avatar {
            width: 42px; height: 42px; border-radius: 50%;
            display: flex !important; align-items: center !important; justify-content: center !important;
            color: #fff; font-weight: 700; font-size: 18px; flex-shrink: 0;
            background: linear-gradient(135deg, #2ecc71, #27ae60);
        }
        .sidebar .user-name { color: #fff; font-weight: 600; font-size: 14px; }
        .sidebar .user-role { color: rgba(255,255,255,0.6); font-size: 12px; }
        .sidebar .nav-section { padding: 15px 20px 5px; color: rgba(255,255,255,0.3); font-size: 10px; text-transform: uppercase; letter-spacing: 1px; font-weight: 600; }
        .sidebar .nav-item {
            color: rgba(255,255,255,0.72); text-decoration: none; padding: 10px 20px;
            display: flex; align-items: center; gap: 12px;
            transition: all 0.2s ease; border-left: 3px solid transparent;
            font-size: 14px; font-weight: 500; cursor: pointer;
        }
        .sidebar .nav-item:hover { background: rgba(255,255,255,0.06); color: #fff; border-left-color: #2ecc71; }
        .sidebar .nav-item.active { background: rgba(46,204,113,0.12); color: #fff; border-left-color: #2ecc71; }
        .sidebar .nav-item .badge { margin-left: auto; background: rgba(255,255,255,0.14); color: #fff; border-radius: 999px; padding: 2px 8px; font-size: 11px; }
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
        
        .form-container { max-width: 900px; margin: 0 auto; }
        .form-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.04);
            padding: 30px;
            border: 1px solid #e9eef4;
            transition: all 0.3s ease;
        }
        .form-card:hover { box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
        
        .required-field::after { content: " *"; color: #e74c3c; font-weight: 700; }
        
        .input-icon-wrapper { position: relative; }
        .input-icon-wrapper .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            z-index: 5;
            pointer-events: none;
        }
        .input-icon-wrapper .input-icon.textarea-icon {
            top: 25px;
        }
        .input-icon-wrapper .form-control,
        .input-icon-wrapper .form-select {
            padding-left: 45px;
        }
        .input-icon-wrapper textarea.form-control {
            padding-left: 45px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            border: none;
            padding: 10px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
            color: #fff;
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 20px rgba(46,204,113,0.3); color: #fff; }
        
        .profile-avatar-large {
            width: 120px; height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            font-size: 48px;
            margin: 0 auto 20px;
            border: 4px solid #fff;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .profile-avatar-large img {
            width: 100%;
            height: 100%;
            object-fit: cover;
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
        
        .section-title {
            font-size: 14px; font-weight: 600; color: #1a2332;
            padding-bottom: 10px; border-bottom: 2px solid #f0f2f5;
            margin-bottom: 20px;
        }
        .section-title i { color: #2ecc71; margin-right: 8px; }
        
        .next-appointment-box {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            color: #fff;
            padding: 15px 20px;
            border-radius: 10px;
            margin-top: 15px;
        }
        .next-appointment-box .appointment-time { font-size: 14px; opacity: 0.9; }
        .next-appointment-box .appointment-patient { font-size: 18px; font-weight: 600; }
        
        .alert { border-radius: 10px; border: none; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-danger { background: #f8d7da; color: #721c24; }
        .alert-info { background: #cce5ff; color: #004085; }
        
        @media (max-width: 768px) {
            .sidebar { min-height: auto; height: auto; position: relative; }
            .main-content { padding: 15px; }
            .page-header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .form-card { padding: 20px; }
            .profile-avatar-large { width: 80px; height: 80px; font-size: 32px; }
            .stat-box { padding: 10px; }
            .stat-box .number { font-size: 20px; }
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
                    <small>Doctor Panel</small>
                </div>
                
                <div class="user-info d-flex align-items-center gap-3">
                    <div class="avatar">
                        <?php if(!empty($user_data['photo'])): ?>
                            <img src="<?php echo htmlspecialchars($user_data['photo']); ?>" alt="Profile" style="width:42px;height:42px;border-radius:50%;object-fit:cover;">
                        <?php else: ?>
                            <?php echo strtoupper(substr($user_data['fullname'] ?? 'U', 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="user-name"><?php echo htmlspecialchars($user_data['fullname'] ?? 'User'); ?></div>
                        <div class="user-role">
                            <i class="bi bi-shield-check"></i> <?php echo ucfirst($user_data['role'] ?? 'doctor'); ?>
                            <?php if($active_profile != 'doctor'): ?>
                                <span class="badge bg-info ms-1"><?php echo ucfirst($active_profile); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="nav-section">Main Menu</div>
                <a href="dashboard.php" class="nav-item">
                    <i class="bi bi-speedometer2"></i> Dashboard
                    <span class="badge"><?php echo $pending_appointments; ?></span>
                </a>
                <a href="appointments.php" class="nav-item">
                    <i class="bi bi-calendar-event"></i> Appointments
                    <span class="badge"><?php echo $pending_appointments; ?></span>
                </a>
                <a href="patients.php" class="nav-item">
                    <i class="bi bi-people"></i> My Patients
                    <span class="badge"><?php echo $total_patients; ?></span>
                </a>
                <a href="prescribe.php" class="nav-item">
                    <i class="bi bi-prescription"></i> Prescribe
                    <span class="badge"><?php echo $pending_prescriptions; ?></span>
                </a>
                <a href="prescriptions.php" class="nav-item">
                    <i class="bi bi-list-check"></i> Prescriptions
                    <span class="badge"><?php echo $pending_prescriptions; ?></span>
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
                            <i class="bi bi-eye"></i> Views: <?php echo $profile_views; ?> | 
                            <i class="bi bi-clock"></i> Last active: <?php echo $last_active ? date('M d, Y H:i', strtotime($last_active)) : 'Never'; ?>
                        </span>
                    </div>
                </div>

                <?php if(isset($_GET['switched'])): ?>
                    <div class="alert alert-info alert-dismissible fade show">
                        <i class="bi bi-info-circle"></i> Profile switched successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="bi bi-check-circle"></i> <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="form-container">
                    <div class="form-card">
                        <!-- Profile Avatar -->
                        <div class="profile-avatar-large">
                            <?php if(!empty($user_data['photo'])): ?>
                                <img src="<?php echo htmlspecialchars($user_data['photo']); ?>" alt="Profile Photo">
                            <?php else: ?>
                                <?php echo strtoupper(substr($user_data['fullname'] ?? 'U', 0, 1)); ?>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Statistics -->
                        <div class="row g-3 mb-4">
                            <div class="col-3">
                                <div class="stat-box">
                                    <span class="icon"><i class="bi bi-people text-primary"></i></span>
                                    <div class="number"><?php echo $total_patients; ?></div>
                                    <div class="label">Total Patients</div>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="stat-box">
                                    <span class="icon"><i class="bi bi-calendar-check text-success"></i></span>
                                    <div class="number"><?php echo $total_appointments; ?></div>
                                    <div class="label">Appointments</div>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="stat-box">
                                    <span class="icon"><i class="bi bi-prescription text-warning"></i></span>
                                    <div class="number"><?php echo $total_prescriptions; ?></div>
                                    <div class="label">Prescriptions</div>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="stat-box">
                                    <span class="icon"><i class="bi bi-clock text-info"></i></span>
                                    <div class="number"><?php echo $pending_appointments; ?></div>
                                    <div class="label">Pending</div>
                                </div>
                            </div>
                        </div>

                        <!-- Next Appointment -->
                        <?php if($next_appointment): ?>
                        <div class="next-appointment-box">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="appointment-time">
                                        <i class="bi bi-calendar3"></i> Next Appointment
                                    </div>
                                    <div class="appointment-patient">
                                        <?php echo htmlspecialchars($next_appointment['patient_name']); ?>
                                    </div>
                                    <div class="appointment-time">
                                        <i class="bi bi-clock"></i> <?php echo date('M d, Y h:i A', strtotime($next_appointment['appointment_date'])); ?>
                                    </div>
                                </div>
                                <a href="../appointments/view.php?id=<?php echo $next_appointment['id']; ?>" class="btn btn-light">
                                    <i class="bi bi-eye"></i> View
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>

                        <form method="POST" class="mt-4">
                            <input type="hidden" name="active_profile" id="active_profile" value="<?php echo $active_profile; ?>">
                            
                            <!-- Personal Information -->
                            <div class="section-title">
                                <i class="bi bi-person"></i> Personal Information
                            </div>
                            
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="fullname" class="form-label required-field">
                                        <i class="bi bi-person"></i> Full Name
                                    </label>
                                    <div class="input-icon-wrapper">
                                        <i class="bi bi-person input-icon"></i>
                                        <input type="text" class="form-control form-control-lg" id="fullname" name="fullname" 
                                               placeholder="Enter your full name" required
                                               value="<?php echo htmlspecialchars($user_data['fullname'] ?? ''); ?>">
                                    </div>
                                    <small class="text-muted">Enter name without "Dr." prefix - it will be added automatically</small>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="specialization" class="form-label">
                                        <i class="bi bi-briefcase"></i> Specialization
                                    </label>
                                    <div class="input-icon-wrapper">
                                        <i class="bi bi-briefcase input-icon"></i>
                                        <input type="text" class="form-control form-control-lg" id="specialization" name="specialization" 
                                               placeholder="e.g., Cardiology, Pediatrics"
                                               value="<?php echo htmlspecialchars($user_data['specialization'] ?? ''); ?>">
                                    </div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">
                                        <i class="bi bi-telephone"></i> Phone Number
                                    </label>
                                    <div class="input-icon-wrapper">
                                        <i class="bi bi-telephone input-icon"></i>
                                        <input type="tel" class="form-control form-control-lg" id="phone" name="phone" 
                                               placeholder="Enter phone number"
                                               value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label required-field">
                                        <i class="bi bi-envelope"></i> Email Address
                                    </label>
                                    <div class="input-icon-wrapper">
                                        <i class="bi bi-envelope input-icon"></i>
                                        <input type="email" class="form-control form-control-lg" id="email" name="email" 
                                               placeholder="Enter email address" required
                                               value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>">
                                    </div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="availability" class="form-label">
                                        <i class="bi bi-clock"></i> Availability
                                    </label>
                                    <div class="input-icon-wrapper">
                                        <i class="bi bi-clock input-icon"></i>
                                        <input type="text" class="form-control form-control-lg" id="availability" name="availability" 
                                               placeholder="e.g., Mon-Fri 9AM-5PM"
                                               value="<?php echo htmlspecialchars($user_data['availability'] ?? ''); ?>">
                                    </div>
                                    <small class="text-muted">Enter your working hours and days</small>
                                </div>
                            </div>

                            <!-- Professional Information -->
                            <div class="section-title mt-4">
                                <i class="bi bi-briefcase"></i> Professional Information
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="bio" class="form-label">
                                        <i class="bi bi-file-text"></i> Bio / About
                                    </label>
                                    <div class="input-icon-wrapper">
                                        <i class="bi bi-file-text input-icon textarea-icon"></i>
                                        <textarea class="form-control form-control-lg" id="bio" name="bio" rows="3" 
                                                  placeholder="Write a brief bio about yourself - your experience, approach to patient care, etc."><?php echo htmlspecialchars($user_data['bio'] ?? ''); ?></textarea>
                                    </div>
                                    <small class="text-muted">A brief description about your experience and practice</small>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="photo" class="form-label">
                                        <i class="bi bi-image"></i> Profile Photo URL
                                    </label>
                                    <div class="input-icon-wrapper">
                                        <i class="bi bi-image input-icon"></i>
                                        <input type="text" class="form-control form-control-lg" id="photo" name="photo" 
                                               placeholder="https://example.com/photo.jpg"
                                               value="<?php echo htmlspecialchars($user_data['photo'] ?? ''); ?>">
                                    </div>
                                    <small class="text-muted">Enter a URL for your profile photo</small>
                                </div>
                            </div>

                            <div class="d-flex gap-2 mt-4">
                                <button type="submit" class="btn btn-primary btn-lg px-5">
                                    <i class="bi bi-check-lg"></i> Update Profile
                                </button>
                                <button type="reset" class="btn btn-outline-secondary btn-lg px-4">
                                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                                </button>
                                <a href="dashboard.php" class="btn btn-outline-danger btn-lg px-4">
                                    <i class="bi bi-x-lg"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelector('form').addEventListener('submit', function(e) {
            const fullname = document.getElementById('fullname').value.trim();
            const email = document.getElementById('email').value.trim();
            
            let isValid = true;
            
            document.querySelectorAll('.form-control, .form-select').forEach(el => {
                el.classList.remove('is-invalid');
            });
            
            if (!fullname) {
                document.getElementById('fullname').classList.add('is-invalid');
                isValid = false;
            }
            
            if (!email) {
                document.getElementById('email').classList.add('is-invalid');
                isValid = false;
            } else if (!email.includes('@') || !email.includes('.')) {
                document.getElementById('email').classList.add('is-invalid');
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
                const firstInvalid = document.querySelector('.is-invalid');
                if (firstInvalid) {
                    firstInvalid.focus();
                }
                return false;
            }
            
            const btn = document.querySelector('button[type="submit"]');
            btn.innerHTML = '<i class="bi bi-spinner bi-spin"></i> Updating...';
            btn.disabled = true;
        });

        document.querySelectorAll('.form-control, .form-select').forEach(element => {
            element.addEventListener('input', function() {
                if (this.value.trim()) {
                    this.classList.remove('is-invalid');
                }
            });
            
            element.addEventListener('change', function() {
                if (this.value.trim()) {
                    this.classList.remove('is-invalid');
                }
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('fullname').focus();
        });
    </script>
</body>
</html>