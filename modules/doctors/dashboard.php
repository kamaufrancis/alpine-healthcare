<?php
// modules/doctor/dashboard.php - Doctor Dashboard (Using Users Table)
require_once '../../core/auth.php';
requireDoctor();
require_once '../../config/database.php';

// Ensure doctor session is set
ensureDoctorSession($pdo);

$user = getCurrentUser($pdo);
$doctor_id = $_SESSION['user_id'];

$message = '';
$error = '';
$active_profile = $_SESSION['active_profile'] ?? 'doctor';

// Get user information from users table
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$doctor_id]);
$doctor = $stmt->fetch(PDO::FETCH_ASSOC);

// Get doctor additional info from doctors table (if exists)
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$doctor_id]);
$doctor_info = $stmt->fetch(PDO::FETCH_ASSOC);

// If no doctor record exists, create a basic one
if(!$doctor_info) {
    try {
        $stmt = $pdo->prepare("INSERT INTO users (id, fullname, email, specialization, phone, availability) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$doctor_id, $doctor['fullname'], $doctor['email'], 'General Practice', '', 'Mon-Fri 9AM-5PM']);
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$doctor_id]);
        $doctor_info = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        $doctor_info = [
            'id' => $doctor_id,
            'fullname' => $doctor['fullname'],
            'specialization' => 'General Practice',
            'phone' => '',
            'email' => $doctor['email'],
            'availability' => 'Mon-Fri 9AM-5PM',
            'bio' => '',
            'qualifications' => '',
            'experience' => '',
            'consultation_fee' => 0,
            'room_number' => ''
        ];
    }
}

// Set session variables
$_SESSION['doctor_id'] = $doctor_id;
$_SESSION['doctor_name'] = $doctor_info['fullname'] ?? $doctor['fullname'];
$_SESSION['specialization'] = $doctor_info['specialization'] ?? 'General Practice';

// Get doctor's appointments
$stmt = $pdo->prepare("SELECT a.*, p.fullname as patient_name, p.phone as patient_phone, p.address as patient_address,
                       p.id as patient_id
                       FROM appointments a 
                       JOIN patients p ON a.patient_id = p.id 
                       WHERE a.doctor_id = ? 
                       ORDER BY a.appointment_date DESC 
                       LIMIT 10");
$stmt->execute([$doctor_id]);
$recent_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get today's appointments
$stmt = $pdo->prepare("SELECT a.*, p.fullname as patient_name, p.phone as patient_phone
                       FROM appointments a 
                       JOIN patients p ON a.patient_id = p.id 
                       WHERE a.doctor_id = ? AND DATE(a.appointment_date) = CURDATE() 
                       ORDER BY a.appointment_date ASC");
$stmt->execute([$doctor_id]);
$today_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get upcoming appointments
$stmt = $pdo->prepare("SELECT a.*, p.fullname as patient_name, p.phone as patient_phone
                       FROM appointments a 
                       JOIN patients p ON a.patient_id = p.id 
                       WHERE a.doctor_id = ? AND a.appointment_date > NOW() AND a.status != 'Cancelled'
                       ORDER BY a.appointment_date ASC 
                       LIMIT 5");
$stmt->execute([$doctor_id]);
$upcoming_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM appointments WHERE doctor_id = ?");
$stmt->execute([$doctor_id]);
$total_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM appointments WHERE doctor_id = ? AND DATE(appointment_date) = CURDATE() AND status = 'Scheduled'");
$stmt->execute([$doctor_id]);
$today_scheduled = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM appointments WHERE doctor_id = ? AND status = 'Scheduled'");
$stmt->execute([$doctor_id]);
$pending_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM appointments WHERE doctor_id = ? AND status = 'Completed'");
$stmt->execute([$doctor_id]);
$completed_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->prepare("SELECT COUNT(DISTINCT patient_id) as total FROM appointments WHERE doctor_id = ?");
$stmt->execute([$doctor_id]);
$total_patients = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get prescription statistics
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM prescriptions WHERE doctor_id = ?");
$stmt->execute([$doctor_id]);
$total_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM prescriptions WHERE doctor_id = ? AND status = 'pending'");
$stmt->execute([$doctor_id]);
$pending_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Get top patients
$stmt = $pdo->prepare("SELECT p.*, COUNT(a.id) as appointment_count 
                       FROM patients p 
                       JOIN appointments a ON p.id = a.patient_id 
                       WHERE a.doctor_id = ? 
                       GROUP BY p.id 
                       ORDER BY appointment_count DESC 
                       LIMIT 5");
$stmt->execute([$doctor_id]);
$top_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

$active_profile = $_SESSION['active_profile'] ?? 'doctor';
$page_title = 'Doctor Dashboard';
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
        /* ============================================
           ALPINE HEALTHCARE - DOCTOR DASHBOARD CSS
           ============================================ */
        
        * { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; }
        body { background: #f4f6fb; }
        
        /* ---------- SIDEBAR ---------- */
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
        
        .sidebar .brand { padding: 20px 20px 15px; border-bottom: 1px solid rgba(255,255,255,0.05); text-align: center; }
        .sidebar .brand h4 { color: #fff; font-weight: 700; margin: 0; }
        .sidebar .brand h4 span { color: #2ecc71; }
        .sidebar .brand small { color: rgba(255,255,255,0.4); font-size: 11px; letter-spacing: 1px; text-transform: uppercase; }
        
        .sidebar .user-info { padding: 15px 20px; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .sidebar .user-info .avatar {
            width: 42px; height: 42px; border-radius: 50%;
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            color: #fff;
            font-weight: 700;
            font-size: 18px;
            flex-shrink: 0;
        }

        .sidebar .user-info .avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
        .sidebar .user-info .user-name { color: #fff; font-weight: 600; font-size: 14px; }
        .sidebar .user-info .user-role { color: rgba(255,255,255,0.4); font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; }
        
        .sidebar .nav-section { padding: 10px 20px 5px; color: rgba(255,255,255,0.3); font-size: 10px; text-transform: uppercase; letter-spacing: 1px; font-weight: 600; }
        .sidebar .nav-item {
            color: rgba(255,255,255,0.6);
            text-decoration: none;
            padding: 10px 20px;
            display: flex !important;
            align-items: center !important;
            gap: 12px;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
            font-size: 14px;
            font-weight: 500;
        }
        .sidebar .nav-item:hover { background: rgba(255,255,255,0.05); color: #fff; border-left-color: #2ecc71; }
        .sidebar .nav-item.active { background: rgba(46, 204, 113, 0.1); color: #2ecc71; border-left-color: #2ecc71; }
        .sidebar .nav-item i { width: 20px; font-size: 16px; }
        .sidebar .nav-item .badge { margin-left: auto; background: #e74c3c; font-size: 10px; padding: 2px 8px; border-radius: 999px; }
        
        /* ---------- MAIN CONTENT ---------- */
        .main-content { padding: 20px 30px; background: #f4f6fb; min-height: 100vh; }
        
        /* ---------- PAGE HEADER ---------- */
        .page-header {
            background: #fff;
            padding: 20px 25px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.04);
            margin-bottom: 25px;
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            flex-wrap: wrap;
            border: 1px solid #e9eef4;
        }
        .page-header h1 { font-size: 24px; font-weight: 700; color: #1a2332; margin: 0; }
        .page-header h1 i { color: #2ecc71; margin-right: 10px; }
        .page-header .breadcrumb { background: none; padding: 0; margin: 0; }
        .page-header .breadcrumb-item a { color: #6c757d; text-decoration: none; }
        .page-header .breadcrumb-item.active { color: #1a2332; font-weight: 600; }
        
        /* ---------- STAT CARDS ---------- */
        .stat-card {
            border: none;
            border-radius: 12px;
            transition: all 0.3s ease;
            overflow: hidden;
            height: 100%;
        }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.12); }
        .stat-card .card-body { padding: 20px; }
        .stat-card .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-size: 24px;
            flex-shrink: 0;
        }
        .stat-card .stat-label { font-size: 13px; font-weight: 500; opacity: 0.85; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-card .stat-number { font-size: 28px; font-weight: 700; margin: 0; line-height: 1.2; }
        .stat-card .stat-change { font-size: 12px; font-weight: 600; margin-top: 8px; opacity: 0.85; }
        
        /* ---------- CARDS ---------- */
        .card {
            border: 1px solid #e9eef4;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.04);
            transition: all 0.3s ease;
            background: #fff;
            height: 100%;
        }
        .card:hover { box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
        .card-header {
            background: transparent;
            border-bottom: 1px solid #e9eef4;
            padding: 15px 20px;
            font-weight: 600;
            color: #1a2332;
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            flex-wrap: wrap;
            gap: 8px;
        }
        .card-header i { margin-right: 8px; }
        .card-body { padding: 0; }
        
        /* ---------- TABLES ---------- */
        .table th {
            font-weight: 600;
            color: #6b7280;
            border-top: none;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 12px 16px;
        }
        .table td { 
            vertical-align: middle; 
            font-size: 14px; 
            padding: 10px 16px;
        }
        .table tbody tr:hover { background: #f8fafc; }
        
        /* ---------- BADGES ---------- */
        .badge-status {
            padding: 4px 14px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 12px;
            display: inline-block;
        }
        .badge-status.Scheduled { background: #cce5ff; color: #004085; }
        .badge-status.Completed { background: #d4edda; color: #155724; }
        .badge-status.Cancelled { background: #f8d7da; color: #721c24; }
        .badge-status.Pending { background: #fff3cd; color: #856404; }
        
        /* ---------- BUTTONS ---------- */
        .btn-primary {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            border: none;
            padding: 8px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
            color: #fff;
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 20px rgba(46, 204, 113, 0.3); color: #fff; }
        
        .btn-outline-primary {
            border-color: #2ecc71;
            color: #2ecc71;
        }
        .btn-outline-primary:hover { background: #2ecc71; border-color: #2ecc71; color: #fff; }
        
        .btn-sm { padding: 4px 10px; font-size: 12px; }
        .btn-actions { padding: 2px 8px; font-size: 12px; }
        
        /* ---------- DOCTOR PROFILE CARD ---------- */
        .doctor-profile-card {
            background: linear-gradient(135deg, #1a2332, #2c3e50);
            color: #fff;
            padding: 24px 28px;
            border-radius: 12px;
            margin-bottom: 25px;
            border: 1px solid rgba(255,255,255,0.05);
        }
        .doctor-profile-card .doctor-name { font-size: 22px; font-weight: 700; }
        .doctor-profile-card .doctor-specialization { opacity: 0.85; font-size: 14px; margin-top: 2px; }
        .doctor-profile-card .doctor-specialization i { margin-right: 6px; }
        .doctor-profile-card .doctor-info { margin-top: 10px; font-size: 13px; opacity: 0.75; }
        .doctor-profile-card .doctor-info i { margin-right: 6px; width: 18px; }
        .doctor-profile-card .active-profile-badge {
            background: rgba(255,255,255,0.15);
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 12px;
            display: inline-block;
            margin-left: 10px;
        }
        .doctor-profile-card .btn-light {
            color: #1a2332;
            font-weight: 600;
        }
        .doctor-profile-card .btn-light:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(255,255,255,0.2); }
        
        /* ---------- LIST GROUP ---------- */
        .list-group-item {
            border-color: #e9eef4;
            background: transparent;
            padding: 12px 20px;
        }
        .list-group-item:hover { background: #f8fafc; }
        .list-group-item .fw-bold { font-weight: 600; }
        
        /* ---------- RESPONSIVE ---------- */
        @media (max-width: 992px) {
            .main-content { padding: 15px; }
            .doctor-profile-card { padding: 18px 20px; }
            .doctor-profile-card .doctor-name { font-size: 18px; }
        }
        
        @media (max-width: 768px) {
            .sidebar { min-height: auto; height: auto; position: relative; }
            .main-content { padding: 12px; }
            .page-header { flex-direction: column !important; align-items: flex-start !important; gap: 10px; }
            .stat-card .stat-number { font-size: 22px; }
            .doctor-profile-card { padding: 15px; text-align: center; }
            .doctor-profile-card .text-md-end { text-align: center !important; margin-top: 12px; }
            .card-header { flex-direction: column !important; align-items: flex-start !important; }
            .table { font-size: 13px; }
            .table th, .table td { padding: 8px 10px; }
            .badge-status { font-size: 10px; padding: 3px 10px; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 8px; }
            .page-header { padding: 12px 16px; }
            .page-header h1 { font-size: 18px; }
            .stat-card .stat-number { font-size: 18px; }
            .stat-card .card-body { padding: 14px; }
            .doctor-profile-card .doctor-name { font-size: 16px; }
            .doctor-profile-card .doctor-info { font-size: 12px; }
        }
    </style>
</head>
<body>
    <div class="container-fluid p-0">
        <div class="row g-0">
            <!-- ============================================
            SIDEBAR
            ============================================ -->
            <nav class="col-md-2 sidebar">
                <div class="brand">
                    <h4>🏔️ <span>Alpine</span></h4>
                    <small>Doctor Panel</small>
                </div>
                
                <div class="user-info d-flex align-items-center gap-3">
                    <div class="avatar">
                        <?php if(!empty($doctor['photo'])): ?>
                            <img src="<?php echo htmlspecialchars($doctor['photo']); ?>" alt="Profile">
                        <?php else: ?>
                            <?php echo strtoupper(substr($doctor['fullname'] ?? 'U', 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="user-name"><?php echo htmlspecialchars($doctor['fullname'] ?? 'User'); ?></div>
                        <div class="user-role">
                            <i class="bi bi-shield-check"></i> <?php echo ucfirst($doctor['role'] ?? 'doctor'); ?>
                            <?php if($active_profile != 'doctor'): ?>
                                <span class="badge bg-info ms-1"><?php echo ucfirst($active_profile); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="nav-section">Main Menu</div>
                <a href="dashboard.php" class="nav-item active">
                    <i class="bi bi-speedometer2"></i> Dashboard
                    <span class="badge"><?php echo $today_scheduled; ?></span>
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
                <a href="profile.php" class="nav-item">
                    <i class="bi bi-person-circle"></i> Profile
                </a>
                
                <div class="nav-section">Account</div>
                <a href="../../logout.php" class="nav-item">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </nav>

            <!-- ============================================
            MAIN CONTENT
            ============================================ -->
            <main class="col-md-10 main-content">
                <!-- Page Header -->
                <div class="page-header">
                    <div>
                        <h1><i class="bi bi-speedometer2"></i> <?php echo $page_title; ?></h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item active">Dashboard</li>
                            </ol>
                        </nav>
                    </div>
                    <div>
                        <span class="text-muted">
                            <i class="bi bi-clock"></i> <?php echo date('l, F j, Y - h:i A'); ?>
                        </span>
                    </div>
                </div>

                <!-- Doctor Profile Card -->
                <div class="doctor-profile-card">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <div class="doctor-name">Dr. <?php echo htmlspecialchars($doctor_info['fullname'] ?? $doctor['fullname']); ?></div>
                            <div class="doctor-specialization">
                                <i class="bi bi-briefcase"></i> <?php echo htmlspecialchars($doctor_info['specialization'] ?? 'General Practice'); ?>
                                <?php if($active_profile != 'doctor'): ?>
                                    <span class="active-profile-badge">
                                        <i class="bi bi-arrow-right"></i> <?php echo ucfirst($active_profile); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="doctor-info">
                                <i class="bi bi-telephone"></i> <?php echo htmlspecialchars($doctor_info['phone'] ?? $doctor['phone'] ?? 'N/A'); ?>
                                <span class="ms-3"><i class="bi bi-envelope"></i> <?php echo htmlspecialchars($doctor_info['email'] ?? $doctor['email'] ?? 'N/A'); ?></span>
                                <span class="ms-3"><i class="bi bi-clock"></i> <?php echo htmlspecialchars($doctor_info['availability'] ?? 'Mon-Fri 9AM-5PM'); ?></span>
                            </div>
                        </div>
                        <div class="col-md-4 text-md-end mt-3 mt-md-0">
                            <a href="profile.php" class="btn btn-light">
                                <i class="bi bi-pencil"></i> Edit Profile
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Statistics -->
                <div class="row g-4 mb-4">
                    <div class="col-6 col-xl-3">
                        <div class="card stat-card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stat-label">Total Patients</div>
                                        <h2 class="stat-number"><?php echo $total_patients; ?></h2>
                                    </div>
                                    <div class="stat-icon bg-white bg-opacity-25">
                                        <i class="bi bi-people"></i>
                                    </div>
                                </div>
                                <div class="stat-change">
                                    <i class="bi bi-arrow-up"></i> Active patients
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-6 col-xl-3">
                        <div class="card stat-card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stat-label">Today's Appointments</div>
                                        <h2 class="stat-number"><?php echo $today_scheduled; ?></h2>
                                    </div>
                                    <div class="stat-icon bg-white bg-opacity-25">
                                        <i class="bi bi-calendar-check"></i>
                                    </div>
                                </div>
                                <div class="stat-change">
                                    <i class="bi bi-calendar"></i> Scheduled today
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-6 col-xl-3">
                        <div class="card stat-card bg-warning text-dark">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stat-label">Pending Appointments</div>
                                        <h2 class="stat-number"><?php echo $pending_appointments; ?></h2>
                                    </div>
                                    <div class="stat-icon bg-white bg-opacity-25">
                                        <i class="bi bi-clock-history"></i>
                                    </div>
                                </div>
                                <div class="stat-change">
                                    <i class="bi bi-hourglass"></i> Awaiting attention
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-6 col-xl-3">
                        <div class="card stat-card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stat-label">Completed</div>
                                        <h2 class="stat-number"><?php echo $completed_appointments; ?></h2>
                                    </div>
                                    <div class="stat-icon bg-white bg-opacity-25">
                                        <i class="bi bi-check-circle"></i>
                                    </div>
                                </div>
                                <div class="stat-change">
                                    <i class="bi bi-check"></i> Completed appointments
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================
                TODAY'S SCHEDULE - FULL WIDTH (12 COLUMNS)
                ============================================ -->
                <div class="row g-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <span><i class="bi bi-calendar-event text-primary"></i> Today's Schedule</span>
                                <span class="badge bg-primary"><?php echo count($today_appointments); ?> appointments</span>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th style="width:12%;">Time</th>
                                                <th style="width:25%;">Patient</th>
                                                <th style="width:18%;">Phone</th>
                                                <th style="width:15%;">Status</th>
                                                <th style="width:30%;">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if(empty($today_appointments)): ?>
                                                <tr>
                                                    <td colspan="5" class="text-center py-4 text-muted">
                                                        <i class="bi bi-calendar-x fs-2 d-block mb-2"></i>
                                                        No appointments scheduled for today
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach($today_appointments as $appointment): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo date('H:i', strtotime($appointment['appointment_date'])); ?></strong>
                                                    </td>
                                                    <td>
                                                        <a href="../patients/view.php?id=<?php echo $appointment['patient_id']; ?>" class="text-decoration-none fw-medium">
                                                            <?php echo htmlspecialchars($appointment['patient_name']); ?>
                                                        </a>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($appointment['patient_phone']); ?></td>
                                                    <td>
                                                        <span class="badge-status <?php echo $appointment['status']; ?>">
                                                            <?php echo htmlspecialchars($appointment['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="../appointments/view.php?id=<?php echo $appointment['id']; ?>" class="btn btn-outline-info btn-actions" title="View">
                                                                <i class="bi bi-eye"></i>
                                                            </a>
                                                            <?php if($appointment['status'] != 'Completed' && $appointment['status'] != 'Cancelled'): ?>
                                                                <a href="prescribe.php?appointment_id=<?php echo $appointment['id']; ?>" class="btn btn-outline-primary btn-actions" title="Prescribe">
                                                                    <i class="bi bi-prescription"></i>
                                                                </a>
                                                                <a href="../appointments/edit.php?id=<?php echo $appointment['id']; ?>&status=Completed" class="btn btn-outline-success btn-actions" title="Complete">
                                                                    <i class="bi bi-check"></i>
                                                                </a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================
                UPCOMING APPOINTMENTS & TOP PATIENTS
                ============================================ -->
                <div class="row g-4 mt-2">
                    <!-- Upcoming Appointments -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <span><i class="bi bi-clock text-info"></i> Upcoming Appointments</span>
                                <span class="badge bg-info"><?php echo count($upcoming_appointments); ?></span>
                            </div>
                            <div class="card-body p-0">
                                <div class="list-group list-group-flush">
                                    <?php if(empty($upcoming_appointments)): ?>
                                        <div class="list-group-item text-center text-muted py-3">
                                            <i class="bi bi-calendar-check fs-4 d-block mb-1"></i>
                                            No upcoming appointments
                                        </div>
                                    <?php else: ?>
                                        <?php foreach($upcoming_appointments as $appointment): ?>
                                        <a href="../appointments/view.php?id=<?php echo $appointment['id']; ?>" class="list-group-item list-group-item-action">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($appointment['patient_name']); ?></div>
                                                    <small class="text-muted">
                                                        <i class="bi bi-calendar3"></i> <?php echo date('M d, Y H:i', strtotime($appointment['appointment_date'])); ?>
                                                    </small>
                                                </div>
                                                <span class="badge-status <?php echo $appointment['status']; ?>">
                                                    <?php echo htmlspecialchars($appointment['status']); ?>
                                                </span>
                                            </div>
                                        </a>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Top Patients -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <span><i class="bi bi-people text-success"></i> Top Patients</span>
                                <a href="patients.php" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body p-0">
                                <div class="list-group list-group-flush">
                                    <?php if(empty($top_patients)): ?>
                                        <div class="list-group-item text-center text-muted py-3">
                                            <i class="bi bi-people fs-4 d-block mb-1"></i>
                                            No patients yet
                                        </div>
                                    <?php else: ?>
                                        <?php foreach($top_patients as $patient): ?>
                                        <a href="../patients/view.php?id=<?php echo $patient['id']; ?>" class="list-group-item list-group-item-action">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($patient['fullname']); ?></div>
                                                    <small class="text-muted">
                                                        <i class="bi bi-telephone"></i> <?php echo htmlspecialchars($patient['phone'] ?? 'N/A'); ?>
                                                    </small>
                                                </div>
                                                <span class="badge bg-secondary"><?php echo $patient['appointment_count']; ?> visits</span>
                                            </div>
                                        </a>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>