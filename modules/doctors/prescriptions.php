<?php
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

// Get doctor information from users table (Changed: from doctors to users)
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$doctor_id]);
$doctor = $stmt->fetch(PDO::FETCH_ASSOC);



// Get doctor information
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$doctor_id]);
$doctor = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle status update
if(isset($_GET['status']) && isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare("UPDATE prescriptions SET status = ? WHERE id = ? AND doctor_id = ?");
        if($stmt->execute([$_GET['status'], $_GET['id'], $doctor_id])) {
            $message = "Prescription status updated successfully!";
            
            try {
                require_once '../../core/logger.php';
                $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
                logActivity($pdo, $user_id, 'Updated prescription status', 'Prescription ID: ' . $_GET['id'] . ' - Status: ' . $_GET['status']);
            } catch (Exception $e) {
                error_log("Failed to log activity: " . $e->getMessage());
            }
        }
    } catch(PDOException $e) {
        $error = 'Error updating status: ' . $e->getMessage();
    }
}

// Get prescriptions with filters
$status_filter = $_GET['status'] ?? '';
$date_filter = $_GET['date'] ?? '';
$search = $_GET['search'] ?? '';

$query = "SELECT p.*, pt.fullname as patient_name, pt.phone as patient_phone,
          pt.id as patient_id, m.name as medicine_name, m.category as medicine_category,
          m.unit_price as medicine_price
          FROM prescriptions p
          JOIN patients pt ON p.patient_id = pt.id
          JOIN medicines m ON p.medicine_id = m.id
          WHERE p.doctor_id = ?";
$params = [$doctor_id];

if($status_filter) {
    $query .= " AND p.status = ?";
    $params[] = $status_filter;
}

if($date_filter) {
    $query .= " AND DATE(p.prescribed_at) = ?";
    $params[] = $date_filter;
}

if($search) {
    $query .= " AND (pt.fullname LIKE ? OR m.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY p.prescribed_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM prescriptions WHERE doctor_id = ?");
$stmt->execute([$doctor_id]);
$total_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM prescriptions WHERE doctor_id = ? AND status = 'pending'");
$stmt->execute([$doctor_id]);
$pending_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM prescriptions WHERE doctor_id = ? AND status = 'dispensed'");
$stmt->execute([$doctor_id]);
$dispensed_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM prescriptions WHERE doctor_id = ? AND status = 'cancelled'");
$stmt->execute([$doctor_id]);
$cancelled_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM prescriptions WHERE doctor_id = ? AND DATE(prescribed_at) = CURDATE()");
$stmt->execute([$doctor_id]);
$today_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Most prescribed medicine
$stmt = $pdo->prepare("SELECT m.name, COUNT(p.id) as count 
                       FROM prescriptions p 
                       JOIN medicines m ON p.medicine_id = m.id 
                       WHERE p.doctor_id = ? 
                       GROUP BY p.medicine_id 
                       ORDER BY count DESC LIMIT 1");
$stmt->execute([$doctor_id]);
$most_prescribed = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Prescriptions - Alpine Healthcare</title>
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
        .sidebar::-webkit-scrollbar-thumb { background: #2ecc71; border-radius: 2px; }
        
        .sidebar .brand {
            padding: 20px 20px 15px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            text-align: center;
        }
        
        .sidebar .brand h4 { color: white; font-weight: 700; margin: 0; }
        .sidebar .brand h4 span { color: #2ecc71; }
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
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 18px;
            flex-shrink: 0;
            overflow: hidden;
        }
        
        .sidebar .user-info .avatar img { width: 100%; height: 100%; object-fit: cover; }

        .sidebar .user-info .user-name { color: white; font-weight: 600; font-size: 14px; margin-bottom: 2px; }
        .sidebar .user-info .user-role {
            color: rgba(255,255,255,0.4);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
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
        
        .sidebar .nav-item:hover { background: rgba(255,255,255,0.05); color: white; border-left-color: #2ecc71; }
        .sidebar .nav-item.active { background: rgba(46, 204, 113, 0.1); color: #2ecc71; border-left-color: #2ecc71; }
        .sidebar .nav-item i { width: 20px; font-size: 16px; }
        .sidebar .nav-item .badge { margin-left: auto; background: #e74c3c; font-size: 10px; padding: 2px 8px; }
        
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
        .page-header h1 i { color: #2ecc71; margin-right: 10px; }
        
        .stat-card {
            border: none;
            border-radius: 12px;
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
        .stat-card .card-body { padding: 20px; }
        .stat-card .stat-label { font-size: 13px; font-weight: 500; opacity: 0.8; margin-bottom: 4px; }
        .stat-card .stat-number { font-size: 28px; font-weight: 700; margin: 0; }
        .stat-card .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.04);
            transition: all 0.3s ease;
        }
        
        .card:hover { box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
        .card-header {
            background: white;
            border-bottom: 1px solid #f0f2f5;
            padding: 15px 20px;
            border-radius: 12px 12px 0 0 !important;
            font-weight: 600;
            color: #1a2332;
        }
        .card-header i { margin-right: 8px; }
        
        .table th {
            font-weight: 600;
            color: #495057;
            border-top: none;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .table td { vertical-align: middle; font-size: 14px; }
        
        .badge-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 12px;
        }
        
        .badge-status.pending { background: #fff3cd; color: #856404; }
        .badge-status.dispensed { background: #d4edda; color: #155724; }
        .badge-status.cancelled { background: #f8d7da; color: #721c24; }
        
        .btn-primary {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            border: none;
            padding: 8px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(46, 204, 113, 0.3);
        }
        
        .btn-sm {
            padding: 4px 10px;
            font-size: 12px;
        }
        
        .prescription-detail {
            font-size: 12px;
            color: #6c757d;
        }
        
        .prescription-detail i {
            margin-right: 4px;
        }
        
        .most-prescribed-box {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
        }
        
        .most-prescribed-box .med-name {
            font-size: 20px;
            font-weight: 700;
        }
        
        @media (max-width: 768px) {
            .sidebar { min-height: auto; height: auto; position: relative; }
            .main-content { padding: 15px; }
            .page-header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .stat-card .stat-number { font-size: 22px; }
        }
        /* ============================================
   ADD THIS TO YOUR <style> SECTION
   ============================================ */
@media print {
    /* Hide sidebar, header, stats, filters, buttons */
    .sidebar,
    .page-header,
    .stat-card,
    .card.mb-4, /* Filter card */
    .btn,
    .alert,
    .breadcrumb,
    .no-print {
        display: none !important;
    }
    
    /* Show only the logs table card */
    .card:not(.stat-card):not(.mb-4) {
        display: block !important;
        border: none !important;
        box-shadow: none !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    
    .card-header {
        display: none !important;
    }
    
    .table {
        width: 100% !important;
        font-size: 12px !important;
    }
    
    .table th {
        background: #f8f9fa !important;
        color: #000 !important;
    }
    
    .table td {
        color: #000 !important;
    }
    
    body {
        background: white !important;
        padding: 20px !important;
    }
    
    .main-content {
        padding: 0 !important;
        background: white !important;
        min-height: auto !important;
    }
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
                <a href="dashboard.php" class="nav-item">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <a href="appointments.php" class="nav-item">
                    <i class="bi bi-calendar-event"></i> Appointments
                </a>
                <a href="patients.php" class="nav-item">
                    <i class="bi bi-people"></i> My Patients
                </a>
                <a href="prescribe.php" class="nav-item">
                    <i class="bi bi-prescription"></i> Prescribe
                </a>
                <a href="prescriptions.php" class="nav-item active">
                    <i class="bi bi-list-check"></i> Prescriptions
                    <span class="badge"><?php echo $pending_prescriptions; ?></span>
                </a>
                <a href="profile.php" class="nav-item">
                    <i class="bi bi-person-circle"></i> Profile
                </a>
                
                <?php if(isAdmin()): ?>
                <div class="nav-section">Administration</div>
                <a href="../admin/index.php" class="nav-item">
                    <i class="bi bi-gear"></i> Admin Panel
                </a>
                <?php endif; ?>
                
                <div class="nav-section">Account</div>
                <a href="../../logout.php" class="nav-item">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </nav>

            <!-- Main Content -->
            <main class="col-md-10 main-content">
                <div class="page-header">
                    <div>
                        <h1><i class="bi bi-list-check"></i> My Prescriptions</h1>
                    </div>
                    <div>
                        <a href="prescribe.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> New Prescription
                        </a>
                        <button onclick="window.print()" class="btn btn-secondary">
                            <i class="bi bi-printer"></i> Print
                        </button>
                    </div>
                </div>

                <?php if($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Statistics -->
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="card stat-card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stat-label">Total</div>
                                        <h2 class="stat-number"><?php echo $total_prescriptions; ?></h2>
                                    </div>
                                    <div class="stat-icon bg-white bg-opacity-25">
                                        <i class="bi bi-prescription"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card bg-warning text-dark">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stat-label">Pending</div>
                                        <h2 class="stat-number"><?php echo $pending_prescriptions; ?></h2>
                                    </div>
                                    <div class="stat-icon bg-white bg-opacity-25">
                                        <i class="bi bi-clock"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stat-label">Dispensed</div>
                                        <h2 class="stat-number"><?php echo $dispensed_prescriptions; ?></h2>
                                    </div>
                                    <div class="stat-icon bg-white bg-opacity-25">
                                        <i class="bi bi-check-circle"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stat-label">Today</div>
                                        <h2 class="stat-number"><?php echo $today_prescriptions; ?></h2>
                                    </div>
                                    <div class="stat-icon bg-white bg-opacity-25">
                                        <i class="bi bi-calendar3"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Most Prescribed Medicine -->
                <?php if($most_prescribed): ?>
                <div class="most-prescribed-box mb-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small><i class="bi bi-trophy"></i> Most Prescribed Medicine</small>
                            <div class="med-name"><?php echo htmlspecialchars($most_prescribed['name']); ?></div>
                            <small><?php echo $most_prescribed['count']; ?> prescriptions</small>
                        </div>
                        <i class="bi bi-capsule display-4 opacity-50"></i>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <input type="text" name="search" class="form-control" placeholder="Search patient or medicine..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-3">
                                <select name="status" class="form-select">
                                    <option value="">All Statuses</option>
                                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="dispensed" <?php echo $status_filter == 'dispensed' ? 'selected' : ''; ?>>Dispensed</option>
                                    <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($date_filter); ?>">
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search"></i> Filter
                                </button>
                                <a href="prescriptions.php" class="btn btn-secondary w-100 mt-1">
                                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Prescriptions Table -->
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-table"></i> Prescription List</span>
                            <span class="badge bg-primary"><?php echo count($prescriptions); ?> prescriptions</span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Patient</th>
                                        <th>Medicine</th>
                                        <th>Details</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($prescriptions)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted">
                                                <i class="bi bi-prescription display-4 d-block"></i>
                                                <p class="mt-2">No prescriptions found</p>
                                                <a href="prescribe.php" class="btn btn-primary btn-sm">
                                                    <i class="bi bi-plus-circle"></i> Create Prescription
                                                </a>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach($prescriptions as $prescription): ?>
                                        <tr>
                                            <td>
                                                <a href="../patients/view.php?id=<?php echo $prescription['patient_id']; ?>" class="text-decoration-none">
                                                    <strong><?php echo htmlspecialchars($prescription['patient_name']); ?></strong>
                                                </a>
                                                <br>
                                                <small class="text-muted">
                                                    <i class="bi bi-telephone"></i> <?php echo htmlspecialchars($prescription['patient_phone']); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($prescription['medicine_name']); ?></strong>
                                                <br>
                                                <small class="text-muted">
                                                    <i class="bi bi-tags"></i> <?php echo htmlspecialchars($prescription['medicine_category']); ?>
                                                </small>
                                                <br>
                                                <small class="text-muted">
                                                    <i class="bi bi-currency-dollar"></i> $<?php echo number_format($prescription['medicine_price'] ?? 0, 2); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="prescription-detail">
                                                    <div><i class="bi bi-box"></i> Qty: <?php echo $prescription['quantity']; ?></div>
                                                    <div><i class="bi bi-eyedropper"></i> <?php echo htmlspecialchars($prescription['dosage']); ?></div>
                                                    <div><i class="bi bi-clock"></i> <?php echo htmlspecialchars($prescription['frequency']); ?></div>
                                                    <?php if($prescription['duration']): ?>
                                                        <div><i class="bi bi-calendar3"></i> <?php echo htmlspecialchars($prescription['duration']); ?></div>
                                                    <?php endif; ?>
                                                    <?php if($prescription['instructions']): ?>
                                                        <div><i class="bi bi-file-text"></i> <?php echo htmlspecialchars(substr($prescription['instructions'], 0, 20)) . (strlen($prescription['instructions']) > 20 ? '...' : ''); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge-status <?php echo $prescription['status']; ?>">
                                                    <?php echo ucfirst($prescription['status']); ?>
                                                </span>
                                                <?php if($prescription['status'] == 'dispensed' && $prescription['dispensed_at']): ?>
                                                    <br>
                                                    <small class="text-muted">
                                                        <i class="bi bi-clock"></i> <?php echo date('M d, Y', strtotime($prescription['dispensed_at'])); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small><?php echo date('M d, Y', strtotime($prescription['prescribed_at'])); ?></small>
                                                <br>
                                                <small class="text-muted"><?php echo date('H:i', strtotime($prescription['prescribed_at'])); ?></small>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    
                                                    <?php if($prescription['status'] == 'pending'): ?>
                                                        <a href="?id=<?php echo $prescription['id']; ?>&status=dispensed" class="btn btn-success" title="Mark as Dispensed" 
                                                           onclick="return confirm('Mark this prescription as dispensed?')">
                                                            <i class="bi bi-check-circle"></i>
                                                        </a>
                                                        <a href="?id=<?php echo $prescription['id']; ?>&status=cancelled" class="btn btn-danger" title="Cancel" 
                                                           onclick="return confirm('Cancel this prescription?')">
                                                            <i class="bi bi-x-circle"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if($prescription['status'] == 'dispensed'): ?>
                                                        <button class="btn btn-secondary" disabled title="Already Dispensed">
                                                            <i class="bi bi-check-lg"></i>
                                                        </button>
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
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>