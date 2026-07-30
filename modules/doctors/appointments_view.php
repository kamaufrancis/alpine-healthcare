<?php
// modules/doctor/appointments_view.php - View Appointment Details
require_once '../../core/auth.php';
requireDoctor();
require_once '../../config/database.php';

// Ensure doctor session is set
ensureDoctorSession($pdo);

$doctor_id = $_SESSION['user_id'];
$appointment_id = $_GET['id'] ?? 0;

// Get appointment details
$stmt = $pdo->prepare("
    SELECT a.*, 
           p.fullname as patient_name, 
           p.phone as patient_phone, 
           p.email as patient_email,
           p.dob as patient_dob,
           p.gender as patient_gender,
           p.address as patient_address,
           u.fullname as doctor_name,
           u.specialization as doctor_specialization,
           u.photo as doctor_photo
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN users u ON a.doctor_id = u.id
    WHERE a.id = ? AND a.doctor_id = ?
");
$stmt->execute([$appointment_id, $doctor_id]);
$appointment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$appointment) {
    $_SESSION['error'] = 'Appointment not found or access denied.';
    header('Location: appointments.php');
    exit;
}

// Get patient history (past appointments)
$stmt = $pdo->prepare("
    SELECT a.*, u.fullname as doctor_name
    FROM appointments a
    JOIN users u ON a.doctor_id = u.id
    WHERE a.patient_id = ? AND a.id != ?
    ORDER BY a.appointment_date DESC
    LIMIT 5
");
$stmt->execute([$appointment['patient_id'], $appointment_id]);
$patient_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get prescriptions for this appointment
$stmt = $pdo->prepare("
    SELECT p.*, m.name as medicine_name
    FROM prescriptions p
    JOIN medicines m ON p.medicine_id = m.id
    WHERE p.appointment_id = ? AND p.doctor_id = ?
    ORDER BY p.prescribed_at DESC
");
$stmt->execute([$appointment_id, $doctor_id]);
$prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate age
function calculateAge($dob) {
    if (empty($dob)) return 'N/A';
    $birthDate = new \DateTime($dob);
    $today = new \DateTime('today');
    return $birthDate->diff($today)->y;
}

$page_title = 'Appointment Details';
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
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
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
            overflow: hidden;
        }
        .sidebar .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
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
        
        .card { border: 1px solid #e9eef4; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.04); background: #fff; }
        .card-header { background: transparent; border-bottom: 1px solid #e9eef4; padding: 1rem 1.1rem; font-weight: 600; display: flex; justify-content: space-between; align-items: center; }
        .card-body { padding: 1rem 1.1rem; }
        
        .info-label { color: #6b7280; font-size: 13px; font-weight: 600; }
        .info-value { font-size: 16px; font-weight: 500; }
        .detail-card { background: #f8fafc; border-radius: 10px; padding: 15px; border: 1px solid #e9eef4; }
        
        .badge-status { padding: 4px 14px; border-radius: 20px; font-weight: 600; font-size: 12px; display: inline-block; }
        .badge-status.scheduled { background: #cce5ff; color: #004085; }
        .badge-status.completed { background: #d4edda; color: #155724; }
        .badge-status.cancelled { background: #f8d7da; color: #721c24; }
        .badge-status.pending { background: #fff3cd; color: #856404; }
        
        .gender-badge { padding: 4px 16px; border-radius: 20px; font-weight: 600; display: inline-block; }
        .gender-badge.Male { background: #dbeafe; color: #1d4ed8; }
        .gender-badge.Female { background: #fce7f3; color: #be185d; }
        .gender-badge.Other { background: #e0e7ff; color: #4338ca; }
        
        .btn-primary { background: #2ecc71; border: none; }
        .btn-primary:hover { background: #27ae60; transform: translateY(-2px); box-shadow: 0 5px 20px rgba(46,204,113,0.3); }
        
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
            <!-- Sidebar -->
            <nav class="col-md-2 sidebar">
                <div class="brand">
                    <h4>🏔️ <span>Alpine</span></h4>
                    <small>Doctor Panel</small>
                </div>
                <div class="user-info d-flex align-items-center gap-3">
                    <div class="avatar">
                        <?php if(!empty($appointment['doctor_photo'])): ?>
                            <img src="<?php echo htmlspecialchars($appointment['doctor_photo']); ?>" alt="Profile">
                        <?php else: ?>
                            <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="user-name"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></div>
                        <div class="user-role"><i class="bi bi-shield-check"></i> Doctor</div>
                    </div>
                </div>
                <div class="nav-section">Main Menu</div>
                <a href="dashboard.php" class="nav-item">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <a href="appointments.php" class="nav-item active">
                    <i class="bi bi-calendar-event"></i> Appointments
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
                <div class="nav-section">Account</div>
                <a href="../../logout.php" class="nav-item">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </nav>

            <!-- Main Content -->
            <main class="col-md-10 main-content">
                <div class="page-header">
                    <div>
                        <h1><i class="bi bi-calendar-check"></i> <?php echo $page_title; ?></h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="appointments.php">Appointments</a></li>
                                <li class="breadcrumb-item active"><?php echo $page_title; ?></li>
                            </ol>
                        </nav>
                    </div>
                    <div>
                        <a href="appointments.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Back
                        </a>
                    </div>
                </div>

                <div class="row g-4">
                    <!-- Appointment Details -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <span><i class="bi bi-info-circle"></i> Appointment Information</span>
                                <span class="badge-status <?php echo strtolower($appointment['status']); ?>">
                                    <?php echo ucfirst($appointment['status']); ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <div class="detail-card">
                                    <div class="row mb-2">
                                        <div class="col-4 info-label"><i class="bi bi-person"></i> Patient</div>
                                        <div class="col-8 info-value">
                                            <a href="../patients/view.php?id=<?php echo $appointment['patient_id']; ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($appointment['patient_name']); ?>
                                            </a>
                                        </div>
                                    </div>
                                    <div class="row mb-2">
                                        <div class="col-4 info-label"><i class="bi bi-telephone"></i> Phone</div>
                                        <div class="col-8 info-value"><?php echo htmlspecialchars($appointment['patient_phone'] ?? 'N/A'); ?></div>
                                    </div>
                                    <div class="row mb-2">
                                        <div class="col-4 info-label"><i class="bi bi-envelope"></i> Email</div>
                                        <div class="col-8 info-value"><?php echo htmlspecialchars($appointment['patient_email'] ?? 'N/A'); ?></div>
                                    </div>
                                    <div class="row mb-2">
                                        <div class="col-4 info-label"><i class="bi bi-calendar"></i> Date</div>
                                        <div class="col-8 info-value"><?php echo date('l, M d, Y', strtotime($appointment['appointment_date'])); ?></div>
                                    </div>
                                    <div class="row mb-2">
                                        <div class="col-4 info-label"><i class="bi bi-clock"></i> Time</div>
                                        <div class="col-8 info-value"><?php echo date('h:i A', strtotime($appointment['appointment_date'])); ?></div>
                                    </div>
                                    <div class="row mb-2">
                                        <div class="col-4 info-label"><i class="bi bi-person-badge"></i> Doctor</div>
                                        <div class="col-8 info-value">Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?></div>
                                    </div>
                                    <div class="row">
                                        <div class="col-4 info-label"><i class="bi bi-briefcase"></i> Specialization</div>
                                        <div class="col-8 info-value"><?php echo htmlspecialchars($appointment['doctor_specialization'] ?? 'General Practice'); ?></div>
                                    </div>
                                    <?php if(!empty($appointment['reason'])): ?>
                                    <div class="row mt-2">
                                        <div class="col-4 info-label"><i class="bi bi-chat"></i> Reason</div>
                                        <div class="col-8 info-value"><?php echo htmlspecialchars($appointment['reason']); ?></div>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Quick Actions -->
                                <div class="d-grid gap-2 mt-3">
                                    <?php if($appointment['status'] != 'Completed' && $appointment['status'] != 'Cancelled'): ?>
                                        <a href="../appointments/edit.php?id=<?php echo $appointment['id']; ?>&status=Completed" class="btn btn-success">
                                            <i class="bi bi-check-circle"></i> Mark as Completed
                                        </a>
                                        <a href="prescribe.php?appointment_id=<?php echo $appointment['id']; ?>" class="btn btn-primary">
                                            <i class="bi bi-prescription"></i> Write Prescription
                                        </a>
                                    <?php endif; ?>
                                    
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Patient Information -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <span><i class="bi bi-person"></i> Patient Information</span>
                                <a href="../patients/view.php?id=<?php echo $appointment['patient_id']; ?>" class="btn btn-sm btn-primary">
                                    <i class="bi bi-arrow-right"></i> Full Profile
                                </a>
                            </div>
                            <div class="card-body">
                                <div class="text-center mb-3">
                                    <div class="avatar" style="width:80px;height:80px;font-size:32px;margin:0 auto;background:linear-gradient(135deg,#2ecc71,#27ae60);">
                                        <?php echo strtoupper(substr($appointment['patient_name'] ?? 'P', 0, 1)); ?>
                                    </div>
                                    <h5 class="mt-2"><?php echo htmlspecialchars($appointment['patient_name']); ?></h5>
                                    <span class="gender-badge <?php echo htmlspecialchars($appointment['patient_gender'] ?? 'Other'); ?>">
                                        <?php echo htmlspecialchars($appointment['patient_gender'] ?? 'N/A'); ?>
                                    </span>
                                    <span class="badge bg-secondary ms-2"><?php echo calculateAge($appointment['patient_dob'] ?? ''); ?> years</span>
                                </div>
                                <hr>
                                <div class="detail-card">
                                    <div class="row mb-2">
                                        <div class="col-4 info-label"><i class="bi bi-telephone"></i> Phone</div>
                                        <div class="col-8 info-value"><?php echo htmlspecialchars($appointment['patient_phone'] ?? 'N/A'); ?></div>
                                    </div>
                                    <div class="row mb-2">
                                        <div class="col-4 info-label"><i class="bi bi-envelope"></i> Email</div>
                                        <div class="col-8 info-value"><?php echo htmlspecialchars($appointment['patient_email'] ?? 'N/A'); ?></div>
                                    </div>
                                    <div class="row">
                                        <div class="col-4 info-label"><i class="bi bi-geo-alt"></i> Address</div>
                                        <div class="col-8 info-value"><?php echo htmlspecialchars($appointment['patient_address'] ?? 'N/A'); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Patient History -->
                <div class="row g-4 mt-2">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <span><i class="bi bi-clock-history"></i> Patient History</span>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Doctor</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if(empty($patient_history)): ?>
                                                <tr>
                                                    <td colspan="3" class="text-center py-3 text-muted">
                                                        <i class="bi bi-calendar-x"></i> No previous visits
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach($patient_history as $history): ?>
                                                <tr>
                                                    <td><?php echo date('M d, Y', strtotime($history['appointment_date'])); ?></td>
                                                    <td>Dr. <?php echo htmlspecialchars($history['doctor_name']); ?></td>
                                                    <td>
                                                        <span class="badge-status <?php echo strtolower($history['status']); ?>">
                                                            <?php echo ucfirst($history['status']); ?>
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

                    <!-- Prescriptions -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <span><i class="bi bi-prescription"></i> Prescriptions</span>
                                <a href="prescribe.php?appointment_id=<?php echo $appointment['id']; ?>" class="btn btn-sm btn-primary">
                                    <i class="bi bi-plus-circle"></i> Add Prescription
                                </a>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Medicine</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if(empty($prescriptions)): ?>
                                                <tr>
                                                    <td colspan="2" class="text-center py-3 text-muted">
                                                        <i class="bi bi-prescription"></i> No prescriptions
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach($prescriptions as $prescription): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($prescription['medicine_name']); ?></td>
                                                    <td>
                                                        <span class="badge-status <?php echo $prescription['status']; ?>">
                                                            <?php echo ucfirst($prescription['status']); ?>
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
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>