<?php
require_once '../../core/auth.php';
requireDoctor();
require_once '../../config/database.php';

// Ensure doctor session is set
ensureDoctorSession($pdo);

$user = getCurrentUser($pdo);
$doctor_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$doctor_id]);
$doctor = $stmt->fetch(PDO::FETCH_ASSOC);


$message = '';
$error = '';
$active_profile = $_SESSION['active_profile'] ?? 'doctor';

// Handle search
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Get patients - ONLY those with appointments for this doctor
$query = "SELECT p.*, 
          COUNT(a.id) as appointment_count,
          SUM(CASE WHEN a.status = 'Completed' THEN 1 ELSE 0 END) as completed_count,
          MAX(a.appointment_date) as last_visit,
          COUNT(pr.id) as prescription_count
          FROM patients p
          LEFT JOIN appointments a ON p.id = a.patient_id AND a.doctor_id = ?
          LEFT JOIN prescriptions pr ON p.id = pr.patient_id AND pr.doctor_id = ?
          WHERE EXISTS (SELECT 1 FROM appointments WHERE patient_id = p.id AND doctor_id = ?)
          GROUP BY p.id";
$params = [$doctor_id, $doctor_id, $doctor_id];

if($search) {
    $query .= " HAVING p.fullname LIKE ? OR p.phone LIKE ?";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if($status_filter == 'active') {
    $query .= " HAVING appointment_count > 0";
} elseif($status_filter == 'completed') {
    $query .= " HAVING completed_count > 0";
}

$query .= " ORDER BY last_visit DESC, appointment_count DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT patient_id) as total FROM appointments WHERE doctor_id = ?");
$stmt->execute([$doctor_id]);
$total_patients = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$stmt = $pdo->prepare("SELECT COUNT(DISTINCT patient_id) as total FROM appointments WHERE doctor_id = ? AND DATE(appointment_date) = CURDATE()");
$stmt->execute([$doctor_id]);
$today_patients = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$stmt = $pdo->prepare("SELECT COUNT(DISTINCT patient_id) as total FROM appointments WHERE doctor_id = ? AND status = 'Completed'");
$stmt->execute([$doctor_id]);
$completed_patients = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$stmt = $pdo->prepare("SELECT COUNT(DISTINCT patient_id) as total FROM prescriptions WHERE doctor_id = ?");
$stmt->execute([$doctor_id]);
$prescribed_patients = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Get top patients
$stmt = $pdo->prepare("SELECT p.*, COUNT(a.id) as visit_count 
                       FROM patients p 
                       JOIN appointments a ON p.id = a.patient_id 
                       WHERE a.doctor_id = ? 
                       GROUP BY p.id 
                       ORDER BY visit_count DESC LIMIT 5");
$stmt->execute([$doctor_id]);
$top_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get patients with pending prescriptions
$stmt = $pdo->prepare("SELECT DISTINCT p.* 
                       FROM patients p 
                       JOIN prescriptions pr ON p.id = pr.patient_id 
                       WHERE pr.doctor_id = ? AND pr.status = 'pending'
                       ORDER BY pr.prescribed_at DESC LIMIT 5");
$stmt->execute([$doctor_id]);
$pending_prescription_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Patients - Alpine Healthcare</title>
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
        }

        .sidebar .user-info .avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
        
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
        
        .patient-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3498db, #2980b9);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 16px;
            flex-shrink: 0;
        }
        
        .gender-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .gender-badge.Male { background: #cce5ff; color: #004085; }
        .gender-badge.Female { background: #f8d7da; color: #721c24; }
        .gender-badge.Other { background: #e2e3e5; color: #383d41; }
        
        .top-patient-card {
            border-left: 4px solid #f39c12;
            transition: all 0.3s ease;
        }
        
        .top-patient-card:hover {
            transform: translateX(5px);
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .top-patient-card .rank {
            font-size: 24px;
            font-weight: 700;
            color: #f39c12;
        }
        
        @media (max-width: 768px) {
            .sidebar { min-height: auto; height: auto; position: relative; }
            .main-content { padding: 15px; }
            .page-header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .stat-card .stat-number { font-size: 22px; }
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
                <a href="patients.php" class="nav-item active">
                    <i class="bi bi-people"></i> My Patients
                    <span class="badge"><?php echo $total_patients; ?></span>
                </a>
                <a href="prescribe.php" class="nav-item">
                    <i class="bi bi-prescription"></i> Prescribe
                </a>
                <a href="prescriptions.php" class="nav-item">
                    <i class="bi bi-list-check"></i> Prescriptions
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
                        <h1><i class="bi bi-people"></i> My Patients</h1>
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
                                        <div class="stat-label">Total Patients</div>
                                        <h2 class="stat-number"><?php echo $total_patients; ?></h2>
                                    </div>
                                    <div class="stat-icon bg-white bg-opacity-25">
                                        <i class="bi bi-people"></i>
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
                                        <div class="stat-label">Today's Patients</div>
                                        <h2 class="stat-number"><?php echo $today_patients; ?></h2>
                                    </div>
                                    <div class="stat-icon bg-white bg-opacity-25">
                                        <i class="bi bi-calendar-check"></i>
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
                                        <div class="stat-label">Completed Visits</div>
                                        <h2 class="stat-number"><?php echo $completed_patients; ?></h2>
                                    </div>
                                    <div class="stat-icon bg-white bg-opacity-25">
                                        <i class="bi bi-check-circle"></i>
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
                                        <div class="stat-label">With Prescriptions</div>
                                        <h2 class="stat-number"><?php echo $prescribed_patients; ?></h2>
                                    </div>
                                    <div class="stat-icon bg-white bg-opacity-25">
                                        <i class="bi bi-prescription"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Search & Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-5">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                                    <input type="text" name="search" class="form-control" placeholder="Search by name or phone..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <select name="status" class="form-select">
                                    <option value="">All Patients</option>
                                    <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active (Scheduled)</option>
                                    <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed Visits</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-search"></i> Filter
                                </button>
                                <a href="patients.php" class="btn btn-secondary">
                                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Top Patients -->
                <?php if(!empty($top_patients)): ?>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-trophy text-warning"></i> Top Patients
                            </div>
                            <div class="card-body p-0">
                                <div class="list-group list-group-flush">
                                    <?php foreach($top_patients as $index => $patient): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="d-flex align-items-center gap-3">
                                                <span class="rank"><?php echo $index + 1; ?></span>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($patient['fullname']); ?></strong>
                                                    <br>
                                                    <small class="text-muted">
                                                        <i class="bi bi-calendar-check"></i> <?php echo $patient['visit_count']; ?> visits
                                                    </small>
                                                </div>
                                            </div>
                                            
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Patients with Pending Prescriptions -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-prescription text-warning"></i> Pending Prescriptions
                            </div>
                            <div class="card-body p-0">
                                <?php if(empty($pending_prescription_patients)): ?>
                                    <div class="text-center py-3 text-muted">
                                        <i class="bi bi-check-circle text-success"></i> No pending prescriptions
                                    </div>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach($pending_prescription_patients as $patient): ?>
                                        <div class="list-group-item">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($patient['fullname']); ?></strong>
                                                    <br>
                                                    <small class="text-muted">
                                                        <i class="bi bi-telephone"></i> <?php echo htmlspecialchars($patient['phone'] ?? 'N/A'); ?>
                                                    </small>
                                                </div>
                                                <a href="../patients/view.php?id=<?php echo $patient['id']; ?>" class="btn btn-sm btn-success">
                                                    <i class="bi bi-eye"></i> View
                                                </a>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Patients Table -->
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-table"></i> Patient List</span>
                            <span class="badge bg-primary"><?php echo count($patients); ?> patients</span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Patient</th>
                                        <th>Gender</th>
                                        <th>Phone</th>
                                        <th>Visits</th>
                                        <th>Completed</th>
                                        <th>Prescriptions</th>
                                        <th>Last Visit</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($patients)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-4 text-muted">
                                                <i class="bi bi-people display-4 d-block"></i>
                                                <p class="mt-2">No patients found</p>
                                                <small>Patients will appear here after their first appointment with you.</small>
                                                <br>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach($patients as $patient): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="patient-avatar">
                                                        <?php echo strtoupper(substr($patient['fullname'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($patient['fullname']); ?></strong>
                                                        <?php if($patient['appointment_count'] > 0 && $patient['last_visit']): ?>
                                                            <br>
                                                            <small class="text-muted">
                                                                <i class="bi bi-clock"></i> 
                                                                <?php 
                                                                $last_visit = strtotime($patient['last_visit']);
                                                                $days_ago = floor((time() - $last_visit) / (60 * 60 * 24));
                                                                if($days_ago == 0) {
                                                                    echo 'Today';
                                                                } elseif($days_ago == 1) {
                                                                    echo 'Yesterday';
                                                                } else {
                                                                    echo $days_ago . ' days ago';
                                                                }
                                                                ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="gender-badge <?php echo $patient['gender']; ?>">
                                                    <?php echo htmlspecialchars($patient['gender']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($patient['phone'] ?? 'N/A'); ?></td>
                                            <td>
                                                <span class="badge bg-primary"><?php echo $patient['appointment_count'] ?? 0; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-success"><?php echo $patient['completed_count'] ?? 0; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $patient['prescription_count'] ?? 0; ?></span>
                                            </td>
                                            <td>
                                                <?php if($patient['last_visit']): ?>
                                                    <small><?php echo date('M d, Y', strtotime($patient['last_visit'])); ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted">Never</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    
                                                    <a href="prescribe.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-success" title="Prescribe">
                                                        <i class="bi bi-prescription"></i>
                                                    </a>
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