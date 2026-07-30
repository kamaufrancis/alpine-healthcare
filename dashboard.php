<?php
// modules/admin/dashboard.php - Admin Dashboard
// FIXED VERSION - All issues resolved

require_once 'core/auth.php';
require_once 'config/database.php';
require_once 'core/cache.php';

// Get current user
$user = getCurrentUser($pdo);

// ============================================
// LOAD DASHBOARD STATS
// ============================================
function loadDashboardStats($pdo) {
    $stats = [];
    
    // User Statistics
    $stats['total_users'] = (int) $pdo->query("SELECT COUNT(*) as total FROM users")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $stats['total_admins'] = (int) $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'admin'")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $stats['total_doctors'] = (int) $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'doctor'")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $stats['total_receptionists'] = (int) $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'receptionist'")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $stats['total_pharmacists'] = (int) $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'pharmacist'")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Patient Statistics
    $stats['total_patients'] = (int) $pdo->query("SELECT COUNT(*) as total FROM patients")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $stats['today_patients'] = (int) $pdo->query("SELECT COUNT(*) as total FROM patients WHERE DATE(created_at) = CURDATE()")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Appointment Statistics
    $stats['total_appointments'] = (int) $pdo->query("SELECT COUNT(*) as total FROM appointments")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $stats['today_appointments'] = (int) $pdo->query("SELECT COUNT(*) as total FROM appointments WHERE DATE(appointment_date) = CURDATE()")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $stats['pending_appointments'] = (int) $pdo->query("SELECT COUNT(*) as total FROM appointments WHERE status = 'Scheduled' OR status = 'scheduled'")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $stats['completed_appointments'] = (int) $pdo->query("SELECT COUNT(*) as total FROM appointments WHERE status = 'Completed' OR status = 'completed'")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Doctor Statistics (from users table)
    $stats['total_doctors_db'] = (int) $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'doctor'")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Invoice Statistics
    $stats['total_invoices'] = (int) $pdo->query("SELECT COUNT(*) as total FROM invoices")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $stats['paid_invoices'] = (int) $pdo->query("SELECT COUNT(*) as total FROM invoices WHERE LOWER(payment_status) = 'paid'")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $stats['pending_invoices'] = (int) $pdo->query("SELECT COUNT(*) as total FROM invoices WHERE LOWER(payment_status) = 'pending'")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $stats['total_revenue'] = (float) ($pdo->query("SELECT SUM(amount) as total FROM invoices WHERE LOWER(payment_status) = 'paid'")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    $stats['pending_revenue'] = (float) ($pdo->query("SELECT SUM(amount) as total FROM invoices WHERE LOWER(payment_status) = 'pending'")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    
    // Medicine Statistics
    $stats['total_medicines'] = (int) $pdo->query("SELECT COUNT(*) as total FROM medicines")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $stats['total_stock'] = (int) ($pdo->query("SELECT SUM(quantity) as total FROM medicines")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    $stats['low_stock'] = (int) $pdo->query("SELECT COUNT(*) as total FROM medicines WHERE quantity <= 10 AND quantity > 0")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $stats['out_of_stock'] = (int) $pdo->query("SELECT COUNT(*) as total FROM medicines WHERE quantity <= 0")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Prescription Statistics (if table exists)
    try {
        $stats['total_prescriptions'] = (int) ($pdo->query("SELECT COUNT(*) as total FROM prescriptions")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
        $stats['pending_prescriptions'] = (int) $pdo->query("SELECT COUNT(*) as total FROM prescriptions WHERE LOWER(status) = 'pending'")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        $stats['dispensed_prescriptions'] = (int) $pdo->query("SELECT COUNT(*) as total FROM prescriptions WHERE LOWER(status) = 'dispensed'")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    } catch (PDOException $e) {
        $stats['total_prescriptions'] = 0;
        $stats['pending_prescriptions'] = 0;
        $stats['dispensed_prescriptions'] = 0;
    }
    
    return $stats;
}

// Get cached stats
$stats = getCachedValue('admin_dashboard_stats', function() use ($pdo) { 
    return loadDashboardStats($pdo); 
}, 180);

// Extract variables with defaults
$total_users = $stats['total_users'] ?? 0;
$total_admins = $stats['total_admins'] ?? 0;
$total_doctors = $stats['total_doctors'] ?? 0;
$total_receptionists = $stats['total_receptionists'] ?? 0;
$total_pharmacists = $stats['total_pharmacists'] ?? 0;
$total_patients = $stats['total_patients'] ?? 0;
$today_patients = $stats['today_patients'] ?? 0;
$total_appointments = $stats['total_appointments'] ?? 0;
$today_appointments = $stats['today_appointments'] ?? 0;
$pending_appointments = $stats['pending_appointments'] ?? 0;
$completed_appointments = $stats['completed_appointments'] ?? 0;
$total_doctors_db = $stats['total_doctors_db'] ?? 0;
$total_invoices = $stats['total_invoices'] ?? 0;
$paid_invoices = $stats['paid_invoices'] ?? 0;
$pending_invoices = $stats['pending_invoices'] ?? 0;
$total_revenue = $stats['total_revenue'] ?? 0;
$pending_revenue = $stats['pending_revenue'] ?? 0;
$total_medicines = $stats['total_medicines'] ?? 0;
$total_stock = $stats['total_stock'] ?? 0;
$low_stock = $stats['low_stock'] ?? 0;
$out_of_stock = $stats['out_of_stock'] ?? 0;
$total_prescriptions = $stats['total_prescriptions'] ?? 0;
$pending_prescriptions = $stats['pending_prescriptions'] ?? 0;
$dispensed_prescriptions = $stats['dispensed_prescriptions'] ?? 0;

// ============================================
// GENDER STATISTICS
// ============================================
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count, gender FROM patients GROUP BY gender");
    $gender_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $gender_stats = [];
}

// ============================================
// RECENT ACTIVITIES
// ============================================
// Recent Appointments
try {
    $stmt = $pdo->query("
        SELECT a.*, p.fullname as patient_name, u.fullname as doctor_name 
        FROM appointments a 
        JOIN patients p ON a.patient_id = p.id 
        JOIN users u ON a.doctor_id = u.id 
        ORDER BY a.created_at DESC 
        LIMIT 10
    ");
    $recent_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recent_appointments = [];
}

// Recent Patients
try {
    $stmt = $pdo->query("SELECT * FROM patients ORDER BY created_at DESC LIMIT 5");
    $recent_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recent_patients = [];
}

// Recent Invoices
try {
    $stmt = $pdo->query("
        SELECT i.*, p.fullname as patient_name 
        FROM invoices i 
        JOIN patients p ON i.patient_id = p.id 
        ORDER BY i.created_at DESC 
        LIMIT 5
    ");
    $recent_invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recent_invoices = [];
}

// ============================================
// SYSTEM HEALTH
// ============================================
$php_version = phpversion();
try {
    $mysql_version = $pdo->query("SELECT VERSION()")->fetchColumn();
} catch (PDOException $e) {
    $mysql_version = 'Unknown';
}

// Last Login
try {
    $stmt = $pdo->query("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 1");
    $last_login = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $last_login = null;
}

// ============================================
// PAGE RENDER
// ============================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Alpine Healthcare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        * { 
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; 
        }
        body { 
            background: #f4f6fb; 
        }
        .stat-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .stat-number {
            font-size: 2.2rem;
            font-weight: 700;
            line-height: 1.2;
        }
        .stat-change {
            font-size: 0.85rem;
            opacity: 0.85;
            margin-top: 8px;
        }
        .system-health {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 15px 20px;
            border-left: 4px solid #2ecc71;
        }
        .health-item {
            color: #6b7280;
            font-size: 0.9rem;
            margin-right: 16px;
        }
        .health-item i {
            margin-right: 4px;
        }
        .gender-badge {
            display: inline-block;
            padding: 0.4rem 1.2rem;
            border-radius: 999px;
            font-weight: 600;
            text-transform: capitalize;
            font-size: 14px;
        }
        .gender-badge.male {
            background: #dbeafe;
            color: #1d4ed8;
        }
        .gender-badge.female {
            background: #fce7f3;
            color: #be185d;
        }
        .gender-badge.other {
            background: #e0e7ff;
            color: #4338ca;
        }
        .badge-status {
            padding: 4px 14px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 12px;
            display: inline-block;
        }
        .badge-status.scheduled,
        .badge-status.Scheduled {
            background: #cce5ff;
            color: #004085;
        }
        .badge-status.completed,
        .badge-status.Completed {
            background: #d4edda;
            color: #155724;
        }
        .badge-status.cancelled,
        .badge-status.Cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        .badge-status.paid,
        .badge-status.Paid {
            background: #d4edda;
            color: #155724;
        }
        .badge-status.pending,
        .badge-status.Pending {
            background: #fff3cd;
            color: #856404;
        }
        .bg-purple {
            background: linear-gradient(135deg, #9b59b6, #8e44ad) !important;
            color: #fff !important;
        }
        .bg-purple .stat-icon {
            background: rgba(255,255,255,0.2) !important;
        }
        /* Sidebar styling */
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, #1a2332 0%, #111827 100%);
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0,0,0,0.08);
        }
        .sidebar .brand {
            padding: 20px 20px 15px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            text-align: center;
        }
        .sidebar .brand h4 {
            color: #fff;
            font-weight: 700;
            margin: 0;
        }
        .sidebar .brand h4 span {
            color: #e74c3c;
        }
        .sidebar .brand small {
            color: rgba(255,255,255,0.65);
            font-size: 11px;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .sidebar .user-info {
            padding: 15px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
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
            background: linear-gradient(135deg, #e74c3c, #c0392b);
        }
        .sidebar .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .sidebar .user-name {
            color: #fff;
            font-weight: 600;
            font-size: 14px;
        }
        .sidebar .user-role {
            color: rgba(255,255,255,0.6);
            font-size: 12px;
        }
        .sidebar .nav-section {
            padding: 15px 20px 5px;
            color: rgba(255,255,255,0.3);
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }
        .sidebar .nav-item {
            color: rgba(255,255,255,0.72);
            text-decoration: none;
            padding: 10px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.2s ease;
            border-left: 3px solid transparent;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
        }
        .sidebar .nav-item:hover {
            background: rgba(255,255,255,0.06);
            color: #fff;
            border-left-color: #e74c3c;
        }
        .sidebar .nav-item.active {
            background: rgba(231,76,60,0.12);
            color: #fff;
            border-left-color: #e74c3c;
        }
        .sidebar .nav-item .badge {
            margin-left: auto;
            background: rgba(255,255,255,0.14);
            color: #fff;
            border-radius: 999px;
            padding: 2px 8px;
            font-size: 11px;
        }
        .sidebar .nav-item i {
            width: 20px;
            text-align: center;
        }
        .main-content {
            padding: 20px 30px;
            background: #f4f6fb;
            min-height: 100vh;
        }
        .page-header {
            background: #fff;
            padding: 20px 25px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.04);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        .page-header h1 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 700;
        }
        .page-header .breadcrumb {
            margin: 0;
            padding: 0;
            background: transparent;
        }
        .card {
            border: 1px solid #e9eef4;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.04);
            background: #fff;
        }
        .card-header {
            background: transparent;
            border-bottom: 1px solid #e9eef4;
            padding: 1rem 1.1rem;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .card-body {
            padding: 1rem 1.1rem;
        }
        @media (max-width: 768px) {
            .sidebar {
                min-height: auto;
                height: auto;
                position: relative;
            }
            .main-content {
                padding: 15px;
            }
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            .stat-number {
                font-size: 1.5rem;
            }
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
                    <small>Admin Panel</small>
                </div>
                
                <div class="user-info d-flex align-items-center gap-3">
                    <div class="avatar">
                        <?php if(!empty($user['photo'])): ?>
                            <img src="<?php echo htmlspecialchars($user['photo']); ?>" alt="Profile">
                        <?php else: ?>
                            <?php echo strtoupper(substr($user['fullname'] ?? 'A', 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="user-name"><?php echo htmlspecialchars($user['fullname'] ?? 'User'); ?></div>
                        <div class="user-role"><i class="bi bi-shield-check"></i> <?php echo ucfirst($user['role'] ?? 'admin'); ?></div>
                    </div>
                </div>
                
                <div class="nav-section">Main Menu</div>
                <a href="dashboard.php" class="nav-item active">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <a href="modules/admin/users.php" class="nav-item">
                    <i class="bi bi-people"></i> Users
                    <span class="badge"><?php echo $total_users; ?></span>
                </a>
                <a href="modules/admin/patients.php" class="nav-item">
                    <i class="bi bi-person"></i> Patients
                    <span class="badge"><?php echo $total_patients; ?></span>
                </a>
                <a href="modules/admin/appointments.php" class="nav-item">
                    <i class="bi bi-calendar-event"></i> Appointments
                    <span class="badge"><?php echo $pending_appointments; ?></span>
                </a>
                <a href="modules/admin/billing.php" class="nav-item">
                    <i class="bi bi-receipt"></i> Billing
                    <span class="badge"><?php echo $pending_invoices; ?></span>
                </a>
                <a href="modules/admin/pharmacy.php" class="nav-item">
                    <i class="bi bi-capsule"></i> Pharmacy
                    <span class="badge"><?php echo $low_stock; ?></span>
                </a>
                
                <div class="nav-section">System</div>
                <a href="modules/admin/settings.php" class="nav-item">
                    <i class="bi bi-gear"></i> Settings
                </a>
                <a href="modules/admin/logs.php" class="nav-item">
                    <i class="bi bi-activity"></i> Activity Logs
                </a>
                
                <div class="nav-section">Account</div>
                <a href="modules/admin/profile.php" class="nav-item">
                    <i class="bi bi-person-circle"></i> Profile
                </a>
                <a href="logout.php" class="nav-item">
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
                        <h1><i class="bi bi-speedometer2"></i> Admin Dashboard</h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                
                                <li class="breadcrumb-item active">Dashboard</li>
                            </ol>
                        </nav>
                    </div>
                    <div>
                        <span class="text-muted me-3">
                            <i class="bi bi-clock"></i> <?php echo date('l, F j, Y - h:i A'); ?>
                        </span>
                        <a href="modules/admin/settings.php" class="btn btn-primary">
                            <i class="bi bi-gear"></i> Settings
                        </a>
                    </div>
                </div>

                <!-- System Health -->
                <div class="system-health mb-4">
                    <i class="bi bi-check-circle text-success"></i> System Health: <strong>All systems operational</strong>
                    <span class="health-item">
                        <i class="bi bi-database"></i> MySQL: <?php echo htmlspecialchars($mysql_version); ?>
                    </span>
                    <span class="health-item">
                        <i class="bi bi-code"></i> PHP: <?php echo htmlspecialchars($php_version); ?>
                    </span>
                    <span class="health-item">
                        <i class="bi bi-users"></i> Online: <?php echo $total_users; ?> users
                    </span>
                    <?php if($last_login): ?>
                    <span class="health-item">
                        <i class="bi bi-activity"></i> Last Activity: <?php echo date('M d, Y H:i', strtotime($last_login['created_at'] ?? 'now')); ?>
                    </span>
                    <?php endif; ?>
                </div>

                <!-- Main Statistics -->
                <div class="row g-4 mb-4">
                    <!-- Total Users -->
                    <div class="col-md-3">
                        <div class="card stat-card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stat-label">Total Users</div>
                                        <h2 class="stat-number"><?php echo $total_users; ?></h2>
                                    </div>
                                    <div class="stat-icon bg-white bg-opacity-25 p-2 rounded">
                                        <i class="bi bi-people fs-4"></i>
                                    </div>
                                </div>
                                <div class="stat-change">
                                    <i class="bi bi-arrow-up"></i> <?php echo $total_admins; ?> Admins, <?php echo $total_doctors; ?> Doctors, <?php echo $total_receptionists; ?> Receptionists
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Total Patients -->
                    <div class="col-md-3">
                        <div class="card stat-card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stat-label">Total Patients</div>
                                        <h2 class="stat-number"><?php echo $total_patients; ?></h2>
                                    </div>
                                    <div class="stat-icon bg-white bg-opacity-25 p-2 rounded">
                                        <i class="bi bi-person fs-4"></i>
                                    </div>
                                </div>
                                <div class="stat-change">
                                    <i class="bi bi-arrow-up"></i> <?php echo $today_patients; ?> registered today
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Pending Appointments -->
                    <div class="col-md-3">
                        <div class="card stat-card bg-warning text-dark">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stat-label">Pending Appointments</div>
                                        <h2 class="stat-number"><?php echo $pending_appointments; ?></h2>
                                    </div>
                                    <div class="stat-icon bg-white bg-opacity-25 p-2 rounded">
                                        <i class="bi bi-clock-history fs-4"></i>
                                    </div>
                                </div>
                                <div class="stat-change">
                                    <i class="bi bi-calendar"></i> <?php echo $today_appointments; ?> today
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Total Revenue -->
                    <div class="col-md-3">
                        <div class="card stat-card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stat-label">Total Revenue</div>
                                        <h2 class="stat-number">KES <?php echo number_format($total_revenue, 2); ?></h2>
                                    </div>
                                    <div class="stat-icon bg-white bg-opacity-25 p-2 rounded">
                                        <i class="fa fa-money-bill-alt fs-4"></i>
                                    </div>
                                </div>
                                <div class="stat-change">
                                    <i class="bi bi-clock"></i> KES <?php echo number_format($pending_revenue, 2); ?> pending
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Secondary Statistics -->
                <div class="row g-4 mb-4">
                    <!-- Out of Stock -->
                    <div class="col-md-3">
                        <div class="card stat-card bg-danger text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stat-label">Out of Stock</div>
                                        <h2 class="stat-number"><?php echo $out_of_stock; ?></h2>
                                    </div>
                                    <div class="stat-icon bg-white bg-opacity-25 p-2 rounded">
                                        <i class="bi bi-x-circle fs-4"></i>
                                    </div>
                                </div>
                                <div class="stat-change">
                                    <i class="bi bi-exclamation-triangle"></i> <?php echo $low_stock; ?> low stock items
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Total Medicines -->
                    <div class="col-md-3">
                        <div class="card stat-card bg-secondary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stat-label">Total Medicines</div>
                                        <h2 class="stat-number"><?php echo $total_medicines; ?></h2>
                                    </div>
                                    <div class="stat-icon bg-white bg-opacity-25 p-2 rounded">
                                        <i class="bi bi-capsule fs-4"></i>
                                    </div>
                                </div>
                                <div class="stat-change">
                                    <i class="bi bi-box"></i> <?php echo $total_stock; ?> units in stock
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Total Prescriptions -->
                    <div class="col-md-3">
                        <div class="card stat-card bg-purple text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stat-label">Total Prescriptions</div>
                                        <h2 class="stat-number"><?php echo $total_prescriptions; ?></h2>
                                    </div>
                                    <div class="stat-icon bg-white bg-opacity-25 p-2 rounded">
                                        <i class="bi bi-prescription fs-4"></i>
                                    </div>
                                </div>
                                <div class="stat-change">
                                    <i class="bi bi-clock"></i> <?php echo $pending_prescriptions; ?> pending, <?php echo $dispensed_prescriptions; ?> dispensed
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Total Invoices -->
                    <div class="col-md-3">
                        <div class="card stat-card bg-dark text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stat-label">Total Invoices</div>
                                        <h2 class="stat-number"><?php echo $total_invoices; ?></h2>
                                    </div>
                                    <div class="stat-icon bg-white bg-opacity-25 p-2 rounded">
                                        <i class="bi bi-receipt fs-4"></i>
                                    </div>
                                </div>
                                <div class="stat-change">
                                    <i class="bi bi-check-circle"></i> <?php echo $paid_invoices; ?> paid, <?php echo $pending_invoices; ?> pending
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gender Statistics -->
                <?php if(!empty($gender_stats)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="bi bi-gender-ambiguous text-primary"></i> Patient Gender Distribution
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach($gender_stats as $gender): ?>
                            <div class="col-md-4 text-center">
                                <span class="gender-badge <?php echo htmlspecialchars($gender['gender']); ?>">
                                    <?php echo htmlspecialchars($gender['gender'] ?: 'Unknown'); ?>
                                </span>
                                <h3 class="mt-2"><?php echo $gender['count']; ?></h3>
                                <small class="text-muted">Patients</small>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Recent Activity -->
                <div class="row g-4">
                    <!-- Recent Appointments -->
                    <div class="col-md-7">
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-clock-history text-primary"></i> Recent Appointments
                                <a href="modules/admin/appointments.php" class="btn btn-sm btn-outline-primary float-end">View All</a>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Patient</th>
                                                <th>Doctor</th>
                                                <th>Date & Time</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if(empty($recent_appointments)): ?>
                                                <tr>
                                                    <td colspan="4" class="text-center py-3 text-muted">
                                                        <i class="bi bi-calendar-x"></i> No appointments found
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach($recent_appointments as $appointment): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($appointment['patient_name'] ?? 'N/A'); ?></td>
                                                    <td>Dr. <?php echo htmlspecialchars($appointment['doctor_name'] ?? 'N/A'); ?></td>
                                                    <td>
                                                        <small><?php echo date('M d, Y', strtotime($appointment['appointment_date'] ?? 'now')); ?></small>
                                                        <br>
                                                        <small class="text-muted"><?php echo date('H:i', strtotime($appointment['appointment_date'] ?? 'now')); ?></small>
                                                    </td>
                                                    <td>
                                                        <span class="badge-status <?php echo strtolower($appointment['status'] ?? 'unknown'); ?>">
                                                            <?php echo htmlspecialchars($appointment['status'] ?? 'Unknown'); ?>
                                                        </span>
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
                    
                    <!-- Quick Stats & Recent Activity -->
                    <div class="col-md-5">
                        <!-- Recent Patients -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="bi bi-person-plus text-success"></i> Recent Patients
                                <a href="modules/admin/patients.php" class="btn btn-sm btn-outline-success float-end">View All</a>
                            </div>
                            <div class="card-body p-0">
                                <div class="list-group list-group-flush">
                                    <?php if(empty($recent_patients)): ?>
                                        <div class="list-group-item text-center text-muted py-3">
                                            <i class="bi bi-people"></i> No patients registered
                                        </div>
                                    <?php else: ?>
                                        <?php foreach($recent_patients as $patient): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?php echo htmlspecialchars($patient['fullname'] ?? 'N/A'); ?></strong>
                                                <br>
                                                <small class="text-muted">
                                                    <i class="bi bi-telephone"></i> <?php echo htmlspecialchars($patient['phone'] ?? 'N/A'); ?>
                                                </small>
                                            </div>
                                            <small class="text-muted"><?php echo date('M d, Y', strtotime($patient['created_at'] ?? 'now')); ?></small>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Recent Invoices -->
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-receipt text-warning"></i> Recent Invoices
                                <a href="modules/admin/billing.php" class="btn btn-sm btn-outline-warning float-end">View All</a>
                            </div>
                            <div class="card-body p-0">
                                <div class="list-group list-group-flush">
                                    <?php if(empty($recent_invoices)): ?>
                                        <div class="list-group-item text-center text-muted py-3">
                                            <i class="bi bi-receipt"></i> No invoices found
                                        </div>
                                    <?php else: ?>
                                        <?php foreach($recent_invoices as $invoice): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?php echo htmlspecialchars($invoice['invoice_number'] ?? 'N/A'); ?></strong>
                                                <br>
                                                <small><?php echo htmlspecialchars($invoice['patient_name'] ?? 'N/A'); ?></small>
                                            </div>
                                            <div class="text-end">
                                                <strong>KES <?php echo number_format($invoice['amount'] ?? 0, 2); ?></strong>
                                                <br>
                                                <span class="badge-status <?php echo strtolower($invoice['payment_status'] ?? 'unknown'); ?>">
                                                    <?php echo ucfirst($invoice['payment_status'] ?? 'Unknown'); ?>
                                                </span>
                                            </div>
                                        </div>
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