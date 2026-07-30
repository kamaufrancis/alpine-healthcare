<?php
// modules/admin/appointments.php - Appointment Management
require_once __DIR__ . '/../../core/auth.php';
requireAdmin();
require_once __DIR__ . '/../../config/database.php';

// Get filter parameters
$status = $_GET['status'] ?? '';
$date = $_GET['date'] ?? '';


$current_user = $_SESSION['user_name'] ?? 'Administrator';
$current_user_id = $_SESSION['user_id'] ?? 0;

$stmt = $pdo->prepare("SELECT photo FROM users WHERE id = ?");
$stmt->execute([$current_user_id]);
$user_photo = $stmt->fetchColumn();

// Build query
$sql = "
    SELECT a.id, a.patient_id, a.doctor_id, a.appointment_date, a.reason, a.status, a.created_at,
           p.fullname AS patient_name,
           d.fullname AS doctor_name
    FROM appointments a
    LEFT JOIN patients p ON a.patient_id = p.id
    LEFT JOIN users d ON a.doctor_id = d.id
    WHERE 1=1
";
$params = [];

if (!empty($status)) {
    $sql .= " AND LOWER(a.status) = ?";
    $params[] = strtolower($status);
}

if (!empty($date)) {
    $sql .= " AND DATE(a.appointment_date) = ?";
    $params[] = $date;
}

$sql .= " ORDER BY a.appointment_date DESC";

$appointments = [];
$db_error = null;

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $db_error = 'Unable to load appointments from the database.';
    error_log('Appointments query failed: ' . $e->getMessage());
}

// Get stats
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM appointments WHERE LOWER(status) = 'scheduled'");
    $scheduled = $stmt->fetchColumn() ?: 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM appointments WHERE LOWER(status) = 'completed'");
    $completed = $stmt->fetchColumn() ?: 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM appointments WHERE LOWER(status) = 'cancelled'");
    $cancelled = $stmt->fetchColumn() ?: 0;
} catch (PDOException $e) {
    $scheduled = 0;
    $completed = 0;
    $cancelled = 0;
    error_log('Stats query failed: ' . $e->getMessage());
}

$page_title = 'Appointment Management';
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
            position: sticky; top: 0; height: 100vh; overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0,0,0,0.08);
        }
        .sidebar .brand { padding: 20px 20px 15px; border-bottom: 1px solid rgba(255,255,255,0.08); text-align: center; }
        .sidebar .brand h4 { color: #fff; font-weight: 700; margin: 0; }
        .sidebar .brand h4 span { color: #e74c3c; }
        .sidebar .brand small { color: rgba(255,255,255,0.65); font-size: 11px; letter-spacing: 1px; text-transform: uppercase; }
        .sidebar .user-info { padding: 15px 20px; border-bottom: 1px solid rgba(255,255,255,0.08); }
        .sidebar .avatar {
            width: 42px; height: 42px; border-radius: 50%;
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            display: flex !important; align-items: center !important; justify-content: center !important;
            color: #fff; font-weight: 700; font-size: 18px; flex-shrink: 0;
        }

        .sidebar .avatar img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; }  

        .sidebar .user-name { color: #fff; font-weight: 600; font-size: 14px; }
        .sidebar .user-role { color: rgba(255,255,255,0.6); font-size: 12px; }
        .sidebar .nav-section { padding: 15px 20px 5px; color: rgba(255,255,255,0.3); font-size: 10px; text-transform: uppercase; letter-spacing: 1px; font-weight: 600; }
        .sidebar .nav-item {
            color: rgba(255,255,255,0.72); text-decoration: none; padding: 10px 20px;
            display: flex; align-items: center; gap: 12px;
            transition: all 0.2s ease; border-left: 3px solid transparent;
            font-size: 14px; font-weight: 500; cursor: pointer;
        }
        .sidebar .nav-item:hover { background: rgba(255,255,255,0.06); color: #fff; border-left-color: #e74c3c; }
        .sidebar .nav-item.active { background: rgba(231,76,60,0.12); color: #fff; border-left-color: #e74c3c; }
        .sidebar .nav-item .badge { margin-left: auto; background: rgba(255,255,255,0.14); color: #fff; border-radius: 999px; padding: 2px 8px; font-size: 11px; }
        .sidebar .nav-item i { width: 20px; text-align: center; }
        .main-content { padding: 20px 30px; background: #f4f6fb; min-height: 100vh; }
        .page-header {
            background: #fff; padding: 20px 25px; border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.04); margin-bottom: 25px;
            display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;
        }
        .page-header h1 { margin: 0; font-size: 1.5rem; font-weight: 700; }
        .page-header .breadcrumb { margin: 0; padding: 0; background: transparent; }
        .card { border: 1px solid #e9eef4; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.04); background: #fff; }
        .card-header { background: transparent; border-bottom: 1px solid #e9eef4; padding: 1rem 1.1rem; font-weight: 600; display: flex; justify-content: space-between; align-items: center; }
        .card-body { padding: 1rem 1.1rem; }
        .table th { font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
        .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; }
        .status-badge.scheduled { background: #cce5ff; color: #004085; }
        .status-badge.completed { background: #d4edda; color: #155724; }
        .status-badge.cancelled { background: #f8d7da; color: #721c24; }
        .filter-bar { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
        .mini-stat { text-align: center; padding: 8px; border-radius: 8px; background: #f8fafc; }
        .mini-stat .number { font-size: 20px; font-weight: 700; }
        .mini-stat .label { font-size: 11px; color: #6b7280; }
        @media (max-width: 768px) {
            .sidebar { min-height: auto; height: auto; position: relative; }
            .main-content { padding: 15px; }
            .page-header { flex-direction: column; align-items: flex-start; gap: 10px; }
        }
    </style>
</head>
<body>
    <div class="container-fluid p-0">
        <div class="row g-0">
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
                    <i class="bi bi-people"></i> Users
                </a>
                <a href="patients.php" class="nav-item">
                    <i class="bi bi-person"></i> Patients
                </a>
                <a href="appointments.php" class="nav-item active">
                    <i class="bi bi-calendar-event"></i> Appointments
                    <span class="badge"><?php echo $scheduled; ?></span>
                </a>
                <a href="billing.php" class="nav-item">
                    <i class="bi bi-receipt"></i> Billing
                </a>
                <a href="pharmacy.php" class="nav-item">
                    <i class="bi bi-capsule"></i> Pharmacy
                </a>
                
                <div class="nav-section">System</div>
                <a href="settings.php" class="nav-item">
                    <i class="bi bi-gear"></i> Settings
                </a>
                <a href="logs.php" class="nav-item">
                    <i class="bi bi-activity"></i> Activity Logs
                </a>
                
                <div class="nav-section">Account</div>
                <a href="profile.php" class="nav-item">
                    <i class="bi bi-person-circle"></i> Profile
                </a>
                <a href="../../logout.php" class="nav-item">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </nav>

            <main class="col-md-10 main-content">
                <div class="page-header">
                    <div>
                        <h1><i class="bi bi-calendar-event"></i> <?php echo $page_title; ?></h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item active"><?php echo $page_title; ?></li>
                            </ol>
                        </nav>
                    </div>
                    
                </div>

                <?php if(isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="bi bi-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if(isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="bi bi-exclamation-triangle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if($db_error): ?>
                    <div class="alert alert-warning alert-dismissible fade show">
                        <i class="bi bi-exclamation-triangle"></i> <?php echo $db_error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Mini Stats -->
                <div class="row g-3 mb-4">
                    <div class="col-4 col-md-2">
                        <div class="mini-stat">
                            <div class="number" style="color:#004085;"><?php echo $scheduled; ?></div>
                            <div class="label">Scheduled</div>
                        </div>
                    </div>
                    <div class="col-4 col-md-2">
                        <div class="mini-stat">
                            <div class="number" style="color:#155724;"><?php echo $completed; ?></div>
                            <div class="label">Completed</div>
                        </div>
                    </div>
                    <div class="col-4 col-md-2">
                        <div class="mini-stat">
                            <div class="number" style="color:#721c24;"><?php echo $cancelled; ?></div>
                            <div class="label">Cancelled</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <form method="GET" class="filter-bar">
                            <input type="date" name="date" class="form-control form-control-sm" 
                                   value="<?php echo htmlspecialchars($date); ?>" style="width:160px;">
                            <select name="status" class="form-select form-select-sm" style="width:140px;">
                                <option value="">All Status</option>
                                <option value="scheduled" <?php echo $status == 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                <option value="completed" <?php echo $status == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $status == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                            <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                            <a href="appointments.php" class="btn btn-sm btn-secondary">Reset</a>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <span><i class="bi bi-list"></i> All Appointments</span>
                        <span class="text-muted">Total: <?php echo count($appointments); ?></span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="dataTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Patient</th>
                                        <th>Doctor</th>
                                        <th>Date & Time</th>
                                        <th>Reason</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($appointments)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4 text-muted">
                                                <i class="bi bi-calendar-x fs-2 d-block mb-2"></i>
                                                No appointments found
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach($appointments as $appointment): ?>
                                        <tr>
                                            <td>#<?php echo $appointment['id']; ?></td>
                                            <td><?php echo htmlspecialchars($appointment['patient_name'] ?? 'Unknown Patient'); ?></td>
                                            <td>Dr. <?php echo htmlspecialchars($appointment['doctor_name'] ?? 'Unassigned'); ?></td>
                                            <td><?php echo date('M d, Y H:i', strtotime($appointment['appointment_date'])); ?></td>
                                            <td><?php echo htmlspecialchars(substr($appointment['reason'] ?? '', 0, 30)); ?></td>
                                            <td>
                                                <span class="status-badge <?php echo strtolower($appointment['status'] ?? 'unknown'); ?>">
                                                    <?php echo ucfirst($appointment['status'] ?? 'Unknown'); ?>
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
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
</body>
</html>