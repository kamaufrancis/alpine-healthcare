<?php
// modules/reception/appointments_view.php - View Appointment
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

$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("
    SELECT a.*, p.fullname as patient_name, p.phone, d.fullname as doctor_name, d.specialization
    FROM appointments a 
    JOIN patients p ON a.patient_id = p.id 
    JOIN users d ON a.doctor_id = d.id 
    WHERE a.id = ?
");
$stmt->execute([$id]);
$appointment = $stmt->fetch();

if (!$appointment) {
    $_SESSION['error'] = 'Appointment not found.';
    header('Location: appointments.php');
    exit;
}

$page_title = 'Appointment Details';
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
        .info-label { color: #6b7280; font-size: 13px; font-weight: 600; }
        .info-value { font-size: 16px; font-weight: 500; }
        .detail-card { background: #f8fafc; border-radius: 10px; padding: 15px; border: 1px solid #e9eef4; }
        .badge-status { padding: 4px 14px; border-radius: 20px; font-weight: 600; font-size: 12px; display: inline-block; }
        .badge-status.scheduled { background: #cce5ff; color: #004085; }
        .badge-status.completed { background: #d4edda; color: #155724; }
        .badge-status.cancelled { background: #f8d7da; color: #721c24; }
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
                        <h1><i class="bi bi-calendar-check"></i> <?php echo $page_title; ?></h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item active"><?php echo $page_title; ?></li>
                            </ol>
                        </nav>
                    </div>
                    <div>
                        <a href="appointments_edit.php?id=<?php echo $appointment['id']; ?>" class="btn btn-primary">
                            <i class="bi bi-pencil"></i> Edit
                        </a>
                        <a href="appointments.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Back
                        </a>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-calendar-event"></i> Appointment Information
                            </div>
                            <div class="card-body">
                                <div class="detail-card">
                                    <div class="row mb-2">
                                        <div class="col-5 info-label"><i class="bi bi-person"></i> Patient</div>
                                        <div class="col-7 info-value"><?php echo htmlspecialchars($appointment['patient_name']); ?></div>
                                    </div>
                                    <div class="row mb-2">
                                        <div class="col-5 info-label"><i class="bi bi-telephone"></i> Phone</div>
                                        <div class="col-7 info-value"><?php echo htmlspecialchars($appointment['phone'] ?? 'N/A'); ?></div>
                                    </div>
                                    <div class="row mb-2">
                                        <div class="col-5 info-label"><i class="bi bi-person-badge"></i> Doctor</div>
                                        <div class="col-7 info-value">Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?></div>
                                    </div>
                                    <div class="row mb-2">
                                        <div class="col-5 info-label"><i class="bi bi-clipboard"></i> Specialization</div>
                                        <div class="col-7 info-value"><?php echo htmlspecialchars($appointment['specialization'] ?? 'General'); ?></div>
                                    </div>
                                    <div class="row mb-2">
                                        <div class="col-5 info-label"><i class="bi bi-calendar"></i> Date & Time</div>
                                        <div class="col-7 info-value"><?php echo date('M d, Y H:i', strtotime($appointment['appointment_date'])); ?></div>
                                    </div>
                                    <div class="row">
                                        <div class="col-5 info-label"><i class="bi bi-info-circle"></i> Status</div>
                                        <div class="col-7 info-value">
                                            <span class="badge-status <?php echo strtolower($appointment['status']); ?>">
                                                <?php echo ucfirst($appointment['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <?php if(!empty($appointment['reason'])): ?>
                                    <div class="row mt-2">
                                        <div class="col-12 info-label"><i class="bi bi-chat"></i> Reason</div>
                                        <div class="col-12 info-value"><?php echo htmlspecialchars($appointment['reason']); ?></div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-clock-history"></i> Quick Actions
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="appointments_edit.php?id=<?php echo $appointment['id']; ?>" class="btn btn-primary">
                                        <i class="bi bi-pencil"></i> Edit Appointment
                                    </a>
                                    <a href="patients_view.php?id=<?php echo $appointment['patient_id']; ?>" class="btn btn-info text-white">
                                        <i class="bi bi-person"></i> View Patient Profile
                                    </a>
                                    <a href="billing_create.php?patient_id=<?php echo $appointment['patient_id']; ?>" class="btn btn-success">
                                        <i class="bi bi-receipt"></i> Create Invoice
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>