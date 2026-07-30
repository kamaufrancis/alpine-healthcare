<?php
// modules/reception/appointments.php - Appointment Management
require_once __DIR__ . '/../../core/auth.php';
requireLogin();
require_once __DIR__ . '/../../config/database.php';

$user = getCurrentUser($pdo);

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);

// Get filter parameters
$status = $_GET['status'] ?? '';
$date = $_GET['date'] ?? '';

// Build query
$sql = "
    SELECT a.id, a.patient_id, a.doctor_id, a.appointment_date, a.reason, a.status, a.created_at,
           COALESCE(p.fullname, 'Unknown Patient') AS patient_name,
           COALESCE(p.phone, 'N/A') AS patient_phone,
           COALESCE(d.fullname, 'Unassigned Doctor') AS doctor_name
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

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $appointments = [];
    $db_error = 'Unable to load appointments from the database.';
    error_log('Receptionist appointments query failed: ' . $e->getMessage());
}

// Get stats
$stmt = $pdo->query("SELECT COUNT(*) FROM appointments WHERE LOWER(status) = 'scheduled'");
$scheduled = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM appointments WHERE LOWER(status) = 'completed'");
$completed = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM appointments WHERE LOWER(status) = 'cancelled'");
$cancelled = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM appointments WHERE DATE(appointment_date) = CURDATE()");
$today_total = $stmt->fetchColumn();

$page_title = 'Appointment Management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Alpine Healthcare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        * { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; }
        body { background: #f4f6fb; }
        .sidebar .avatar img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; }
        .stat-card { transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
        .stat-number { font-size: 2.2rem; font-weight: 700; line-height: 1.2; }
        .stat-change { font-size: 0.85rem; opacity: 0.85; margin-top: 8px; }
        .badge-status { padding: 4px 14px; border-radius: 20px; font-weight: 600; font-size: 12px; display: inline-block; }
        .badge-status.scheduled { background: #cce5ff; color: #004085; }
        .badge-status.completed { background: #d4edda; color: #155724; }
        .badge-status.cancelled { background: #f8d7da; color: #721c24; }
        .btn-actions { padding: 2px 8px; font-size: 12px; }
        .table th { font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
        .filter-bar { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
        .mini-stat { text-align: center; padding: 8px; border-radius: 8px; background: #f8fafc; }
        .mini-stat .number { font-size: 20px; font-weight: 700; }
        .mini-stat .label { font-size: 11px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="container-fluid p-0">
        <div class="row g-0">
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
                <a href="patients.php" class="nav-item">
                    <i class="bi bi-people"></i> Patients
                </a>
                <a href="appointments.php" class="nav-item active">
                    <i class="bi bi-calendar-event"></i> Appointments
                    <span class="badge"><?php echo $scheduled; ?></span>
                </a>
                <a href="billing.php" class="nav-item">
                    <i class="bi bi-receipt"></i> Billing
                </a>
                <a href="profile.php" class="nav-item">
                    <i class="bi bi-person-circle"></i> Profile
                </a>    
                <div class="nav-section">Account</div>
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
                    <div>
                        <a href="appointments_add.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Book Appointment
                        </a>
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

                <?php if(!empty($db_error ?? '')): ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-database-exclamation"></i> <?php echo htmlspecialchars($db_error); ?>
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
                        <i class="bi bi-list"></i> All Appointments
                        <span class="badge bg-primary float-end"><?php echo count($appointments); ?> appointments</span>
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
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($appointments)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4 text-muted">
                                                <i class="bi bi-calendar-x display-4 d-block"></i>
                                                <p class="mt-2">No appointments found</p>
                                                <a href="appointments_add.php" class="btn btn-primary btn-sm">
                                                    <i class="bi bi-plus-circle"></i> Book Appointment
                                                </a>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach($appointments as $appointment): ?>
                                        <tr>
                                            <td>#<?php echo $appointment['id']; ?></td>
                                            <td><?php echo htmlspecialchars($appointment['patient_name']); ?></td>
                                            <td>Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?></td>
                                            <td><?php echo date('M d, Y H:i', strtotime($appointment['appointment_date'])); ?></td>
                                            <td><?php echo htmlspecialchars(substr($appointment['reason'] ?? '', 0, 30)); ?></td>
                                            <td>
                                                <span class="badge-status <?php echo strtolower($appointment['status']); ?>">
                                                    <?php echo ucfirst($appointment['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="appointments_view.php?id=<?php echo $appointment['id']; ?>" class="btn btn-sm btn-outline-info btn-actions" title="View">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="appointments_edit.php?id=<?php echo $appointment['id']; ?>" class="btn btn-sm btn-outline-primary btn-actions" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
</body>
</html>