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

// Get doctor information
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$doctor_id]);
$doctor = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle status update
if(isset($_GET['status']) && isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare("UPDATE appointments SET status = ? WHERE id = ? AND doctor_id = ?");
        if($stmt->execute([$_GET['status'], $_GET['id'], $doctor_id])) {
            $message = "Appointment status updated successfully!";
            
            try {
                require_once '../../core/logger.php';
                $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
                logActivity($pdo, $user_id, 'Updated appointment status', 'Appointment ID: ' . $_GET['id'] . ' - Status: ' . $_GET['status']);
            } catch (Exception $e) {
                error_log("Failed to log activity: " . $e->getMessage());
            }
        }
    } catch(PDOException $e) {
        $error = 'Error updating status: ' . $e->getMessage();
    }
}

// Get appointments with filters
$status_filter = $_GET['status'] ?? '';
$date_filter = $_GET['date'] ?? '';

$query = "SELECT a.*, p.fullname as patient_name, p.phone as patient_phone, p.address as patient_address,
          p.id as patient_id
          FROM appointments a 
          JOIN patients p ON a.patient_id = p.id 
          WHERE a.doctor_id = ?";
$params = [$doctor_id];

if($status_filter) {
    $query .= " AND a.status = ?";
    $params[] = $status_filter;
}

if($date_filter) {
    $query .= " AND DATE(a.appointment_date) = ?";
    $params[] = $date_filter;
}

$query .= " ORDER BY a.appointment_date DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM appointments WHERE doctor_id = ?");
$stmt->execute([$doctor_id]);
$total_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM appointments WHERE doctor_id = ? AND DATE(appointment_date) = CURDATE()");
$stmt->execute([$doctor_id]);
$today_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM appointments WHERE doctor_id = ? AND status = 'Scheduled'");
$stmt->execute([$doctor_id]);
$pending_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM appointments WHERE doctor_id = ? AND status = 'Completed'");
$stmt->execute([$doctor_id]);
$completed_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$active_profile = $_SESSION['active_profile'] ?? 'doctor';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments - Alpine Healthcare</title>
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
        
        .sidebar .user-info .avatar img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; }

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
        
        .badge-status.Scheduled { background: #cce5ff; color: #004085; }
        .badge-status.Completed { background: #d4edda; color: #155724; }
        .badge-status.Cancelled { background: #f8d7da; color: #721c24; }
        .badge-status.Pending { background: #fff3cd; color: #856404; }
        
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
                <a href="appointments.php" class="nav-item active">
                    <i class="bi bi-calendar-event"></i> Appointments
                    <span class="badge"><?php echo $pending_appointments; ?></span>
                </a>
                <a href="patients.php" class="nav-item">
                    <i class="bi bi-people"></i> My Patients
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
                        <h1><i class="bi bi-calendar-event"></i> My Appointments</h1>
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
                                <div class="stat-label">Total</div>
                                <h2 class="stat-number"><?php echo $total_appointments; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card bg-success text-white">
                            <div class="card-body">
                                <div class="stat-label">Today</div>
                                <h2 class="stat-number"><?php echo $today_appointments; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card bg-warning text-dark">
                            <div class="card-body">
                                <div class="stat-label">Pending</div>
                                <h2 class="stat-number"><?php echo $pending_appointments; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card bg-info text-white">
                            <div class="card-body">
                                <div class="stat-label">Completed</div>
                                <h2 class="stat-number"><?php echo $completed_appointments; ?></h2>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <select name="status" class="form-select">
                                    <option value="">All Statuses</option>
                                    <option value="Scheduled" <?php echo $status_filter == 'Scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                    <option value="Completed" <?php echo $status_filter == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="Cancelled" <?php echo $status_filter == 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    <option value="Pending" <?php echo $status_filter == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($date_filter); ?>">
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-search"></i> Filter
                                </button>
                                <a href="appointments.php" class="btn btn-secondary">
                                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Appointments Table -->
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-table"></i> Appointment List</span>
                            <span class="badge bg-primary"><?php echo count($appointments); ?> appointments</span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Patient</th>
                                        <th>Phone</th>
                                        <th>Date & Time</th>
                                        <th>Reason</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($appointments)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted">
                                                <i class="bi bi-calendar-x display-4 d-block"></i>
                                                <p class="mt-2">No appointments found</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach($appointments as $appointment): ?>
                                        <tr>
                                            <td>
                                                <a href="../patients/view.php?id=<?php echo $appointment['patient_id']; ?>" class="text-decoration-none">
                                                    <strong><?php echo htmlspecialchars($appointment['patient_name']); ?></strong>
                                                </a>
                                            </td>
                                            <td><?php echo htmlspecialchars($appointment['patient_phone']); ?></td>
                                            <td>
                                                <?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?>
                                                <br>
                                                <small><?php echo date('h:i A', strtotime($appointment['appointment_date'])); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars(substr($appointment['reason'], 0, 30)) . (strlen($appointment['reason']) > 30 ? '...' : ''); ?></td>
                                            <td>
                                                <span class="badge-status <?php echo $appointment['status']; ?>">
                                                    <?php echo htmlspecialchars($appointment['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <?php if($appointment['status'] != 'Completed'): ?>
                                                        <a href="?id=<?php echo $appointment['id']; ?>&status=Completed" class="btn btn-success" title="Mark Complete">
                                                            <i class="bi bi-check"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if($appointment['status'] != 'Cancelled'): ?>
                                                        <a href="?id=<?php echo $appointment['id']; ?>&status=Cancelled" class="btn btn-danger" title="Cancel" 
                                                           onclick="return confirm('Are you sure you want to cancel this appointment?')">
                                                            <i class="bi bi-x"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    <a href="prescribe.php?appointment_id=<?php echo $appointment['id']; ?>" class="btn btn-primary" title="Prescribe">
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