<?php
require_once '../../core/auth.php';
requireLogin();
require_once '../../config/database.php';

$user = getCurrentUser($pdo);

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);

// Get statistics
$stmt = $pdo->query("SELECT COUNT(*) as total FROM patients");
$total_patients = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM appointments WHERE DATE(appointment_date) = CURDATE()");
$today_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM appointments WHERE status = 'scheduled'");
$pending_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM appointments WHERE status = 'completed'");
$completed_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM appointments WHERE DATE(appointment_date) = CURDATE() AND status = 'scheduled'");
$today_scheduled = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get today's appointments
$stmt = $pdo->query("SELECT a.*, p.fullname as patient_name, p.phone as patient_phone, d.fullname as doctor_name 
                     FROM appointments a 
                     JOIN patients p ON a.patient_id = p.id 
                     JOIN users d ON a.doctor_id = d.id 
                     WHERE DATE(a.appointment_date) = CURDATE() 
                     ORDER BY a.appointment_date ASC");
$today_appointments_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get upcoming appointments
$stmt = $pdo->query("SELECT a.*, p.fullname as patient_name, p.phone as patient_phone, d.fullname as doctor_name 
                     FROM appointments a 
                     JOIN patients p ON a.patient_id = p.id 
                     JOIN users d ON a.doctor_id = d.id 
                     WHERE a.appointment_date > NOW() AND a.status = 'scheduled'
                     ORDER BY a.appointment_date ASC LIMIT 5");
$upcoming_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent patients
$stmt = $pdo->query("SELECT * FROM patients ORDER BY created_at DESC LIMIT 5");
$recent_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reception Dashboard - Alpine Healthcare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        * { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; }
        body { background: #f4f6fb; }
        .sidebar .avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
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
                <a href="rec_dashboard.php" class="nav-item active">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <a href="patients.php" class="nav-item">
                    <i class="bi bi-people"></i> Patients
                    <span class="badge"><?php echo $total_patients; ?></span>
                </a>
                <a href="appointments.php" class="nav-item">
                    <i class="bi bi-calendar-event"></i> Appointments
                    <span class="badge"><?php echo $pending_appointments; ?></span>
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

            <!-- Main Content -->
            <main class="col-md-10 main-content">
                <!-- Page Header -->
                <div class="page-header">
                    <div>
                        <h1><i class="bi bi-speedometer2"></i> Reception Dashboard</h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item active">Dashboard</li>
                            </ol>
                        </nav>
                    </div>
                    <div>
                        <a href="appointments_add.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> New Appointment
                        </a>
                        <a href="patients_add.php" class="btn btn-success">
                            <i class="bi bi-person-plus"></i> Register Patient
                        </a>
                    </div>
                </div>

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
                                <div class="stat-change">
                                    <i class="bi bi-arrow-up"></i> Registered patients
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card stat-card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stat-label">Today's Appointments</div>
                                        <h2 class="stat-number"><?php echo $today_appointments; ?></h2>
                                    </div>
                                    <div class="stat-icon bg-white bg-opacity-25">
                                        <i class="bi bi-calendar-check"></i>
                                    </div>
                                </div>
                                <div class="stat-change">
                                    <i class="bi bi-calendar"></i> <?php echo $today_scheduled; ?> scheduled today
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
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
                                    <i class="bi bi-hourglass"></i> Awaiting confirmation
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
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

                <div class="row g-4">
                    <!-- Today's Schedule -->
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-calendar-event text-primary"></i> Today's Schedule
                                <span class="badge bg-primary float-end"><?php echo count($today_appointments_list); ?> appointments</span>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Time</th>
                                                <th>Patient</th>
                                                <th>Doctor</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if(empty($today_appointments_list)): ?>
                                                <tr>
                                                    <td colspan="4" class="text-center py-4 text-muted">
                                                        <i class="bi bi-calendar-x display-4 d-block"></i>
                                                        <p class="mt-2">No appointments scheduled for today</p>
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach($today_appointments_list as $appointment): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo date('H:i', strtotime($appointment['appointment_date'])); ?></strong>
                                                    </td>
                                                    <td>
                                                        <?php echo htmlspecialchars($appointment['patient_name']); ?>
                                                        <br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($appointment['patient_phone']); ?></small>
                                                    </td>
                                                    <td>Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?></td>
                                                    <td>
                                                        <span class="badge-status <?php echo $appointment['status']; ?>">
                                                            <?php echo ucfirst($appointment['status']); ?>
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
                
                        
                        <!-- Recent Patients -->
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-person-plus text-success"></i> Recent Patients
                            </div>
                            <div class="card-body p-0">
                                <div class="list-group list-group-flush">
                                    <?php if(empty($recent_patients)): ?>
                                        <div class="list-group-item text-center text-muted py-3">
                                            <i class="bi bi-people"></i> No patients registered yet
                                        </div>
                                    <?php else: ?>
                                        <?php foreach($recent_patients as $patient): ?>
                                        <a href="../patients/view.php?id=<?php echo $patient['id']; ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                            <div>
                                                <i class="bi bi-person-circle text-primary"></i>
                                                <?php echo htmlspecialchars($patient['fullname']); ?>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($patient['phone'] ?? 'No phone'); ?></small>
                                            </div>
                                            <span class="badge bg-light text-dark">
                                                <?php echo date('M d', strtotime($patient['created_at'])); ?>
                                            </span>
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
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>