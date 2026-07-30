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

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$doctor_id]);
$doctor = $stmt->fetch(PDO::FETCH_ASSOC);

$error = '';
$success = '';
$appointment_id = isset($_GET['appointment_id']) ? (int)$_GET['appointment_id'] : 0;
$patient_filter = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$appointment = null;



// Get doctor information
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$doctor_id]);
$doctor = $stmt->fetch(PDO::FETCH_ASSOC);

// If appointment_id is provided, verify it belongs to this doctor
if($appointment_id > 0) {
    $stmt = $pdo->prepare("SELECT a.*, p.fullname as patient_name, p.phone as patient_phone, p.address as patient_address,
                           p.id as patient_id
                           FROM appointments a 
                           JOIN patients p ON a.patient_id = p.id 
                           WHERE a.id = ? AND a.doctor_id = ?");
    $stmt->execute([$appointment_id, $doctor_id]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if(!$appointment) {
        $error = 'Appointment not found or you do not have access to it.';
        header('Location: appointments.php?error=access_denied');
        exit();
    }
    $patient_id = $appointment['patient_id'];
} elseif($patient_filter > 0) {
    // Verify patient belongs to this doctor
    $stmt = $pdo->prepare("SELECT id FROM appointments WHERE patient_id = ? AND doctor_id = ? LIMIT 1");
    $stmt->execute([$patient_filter, $doctor_id]);
    if($stmt->rowCount() > 0) {
        $patient_id = $patient_filter;
    } else {
        $error = 'You do not have access to this patient.';
    }
}

// Get patients - ONLY those who have appointments with this doctor
$stmt = $pdo->prepare("SELECT DISTINCT p.* 
                       FROM patients p 
                       JOIN appointments a ON p.id = a.patient_id 
                       WHERE a.doctor_id = ? 
                       ORDER BY p.fullname ASC");
$stmt->execute([$doctor_id]);
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get medicines
$medicines = [];
try {
    $stmt = $pdo->query("SELECT id, name, category, quantity, unit_price FROM medicines WHERE quantity > 0 ORDER BY name ASC");
    $medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error = 'Error loading medicines: ' . $e->getMessage();
}

// Get existing prescriptions for this appointment
$existing_prescriptions = [];
if($appointment_id > 0) {
    $stmt = $pdo->prepare("SELECT p.*, m.name as medicine_name, m.category 
                           FROM prescriptions p 
                           JOIN medicines m ON p.medicine_id = m.id 
                           WHERE p.appointment_id = ? AND p.doctor_id = ?");
    $stmt->execute([$appointment_id, $doctor_id]);
    $existing_prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $patient_id = (int)($_POST['patient_id'] ?? 0);
    $medicine_id = (int)($_POST['medicine_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 0);
    $dosage = trim($_POST['dosage'] ?? '');
    $frequency = trim($_POST['frequency'] ?? '');
    $duration = trim($_POST['duration'] ?? '');
    $instructions = trim($_POST['instructions'] ?? '');
    $prescription_status = $_POST['prescription_status'] ?? 'pending';
    $appointment_id = (int)($_POST['appointment_id'] ?? 0);
    
    // Validation
    if($patient_id <= 0) {
        $error = 'Please select a patient!';
    } elseif($medicine_id <= 0) {
        $error = 'Please select a medicine!';
    } elseif($quantity <= 0) {
        $error = 'Please enter a valid quantity!';
    } elseif(empty($dosage)) {
        $error = 'Please enter dosage information!';
    } elseif(empty($frequency)) {
        $error = 'Please enter frequency!';
    } else {
        try {
            // Verify patient belongs to this doctor
            $stmt = $pdo->prepare("SELECT id FROM appointments WHERE patient_id = ? AND doctor_id = ? LIMIT 1");
            $stmt->execute([$patient_id, $doctor_id]);
            if($stmt->rowCount() == 0) {
                $error = 'You do not have access to this patient!';
            } else {
                // Check medicine stock
                $stmt = $pdo->prepare("SELECT quantity FROM medicines WHERE id = ?");
                $stmt->execute([$medicine_id]);
                $stock = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if($stock['quantity'] < $quantity) {
                    $error = 'Insufficient stock! Available: ' . $stock['quantity'];
                } else {
                    $pdo->beginTransaction();
                    
                    // In the INSERT query, if appointment_id is 0, set it to NULL
                        $appointment_id_for_db = $appointment_id > 0 ? $appointment_id : null;

                        $stmt = $pdo->prepare("INSERT INTO prescriptions (appointment_id, patient_id, doctor_id, medicine_id, quantity, dosage, frequency, duration, instructions, status) 
                                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$appointment_id_for_db, $patient_id, $doctor_id, $medicine_id, $quantity, $dosage, $frequency, $duration, $instructions, $prescription_status]);
                                            
                    if($appointment_id > 0) {
                        $stmt = $pdo->prepare("UPDATE appointments SET prescription_status = ? WHERE id = ? AND doctor_id = ?");
                        $stmt->execute([$prescription_status, $appointment_id, $doctor_id]);
                    }
                    
                    $pdo->commit();
                    
                    try {
                        require_once '../../core/logger.php';
                        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
                        $medicine_name = '';
                        foreach($medicines as $m) {
                            if($m['id'] == $medicine_id) {
                                $medicine_name = $m['name'];
                                break;
                            }
                        }
                        logActivity($pdo, $user_id, 'Prescribed medicine', 'Patient ID: ' . $patient_id . ' - Medicine: ' . $medicine_name);
                    } catch (Exception $e) {
                        error_log("Failed to log activity: " . $e->getMessage());
                    }
                    
                    $success = 'Prescription created successfully! The pharmacy has been notified.';
                    
                    if($appointment_id > 0) {
                        header('Location: appointments.php?prescribed=1');
                    } else {
                        header('Location: prescriptions.php?added=1');
                    }
                    exit;
                }
            }
        } catch(PDOException $e) {
            $pdo->rollBack();
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}



$active_profile = $_SESSION['active_profile'] ?? 'doctor';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescribe Medicine - Alpine Healthcare</title>
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
        
        .form-container { max-width: 800px; margin: 0 auto; }
        .form-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.04);
            padding: 30px;
            transition: all 0.3s ease;
        }
        
        .form-card:hover { box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
        
        .required-field::after { content: " *"; color: #dc3545; }
        
        .input-icon-wrapper { position: relative; }
        .input-icon-wrapper .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        .input-icon-wrapper .form-control,
        .input-icon-wrapper .form-select {
            padding-left: 45px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            border: none;
            padding: 10px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(46, 204, 113, 0.3);
        }
        
        .patient-info-box {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 20px;
            border-left: 4px solid #2ecc71;
        }
        
        .patient-info-box .info-item {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .patient-info-box .info-item span {
            font-size: 14px;
        }
        
        .patient-info-box .label {
            font-weight: 600;
            color: #495057;
        }
        
        .badge-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 12px;
        }
        
        .badge-status.Scheduled { background: #cce5ff; color: #004085; }
        .badge-status.Completed { background: #d4edda; color: #155724; }
        .badge-status.pending { background: #fff3cd; color: #856404; }
        .badge-status.dispensed { background: #d4edda; color: #155724; }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .alert-success { background: #d4edda; color: #155724; }
        .alert-danger { background: #f8d7da; color: #721c24; }
        .alert-warning { background: #fff3cd; color: #856404; }
        
        @media (max-width: 768px) {
            .sidebar { min-height: auto; height: auto; position: relative; }
            .main-content { padding: 15px; }
            .page-header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .form-card { padding: 20px; }
            .patient-info-box .info-item { flex-direction: column; gap: 4px; }
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
                <a href="prescribe.php" class="nav-item active">
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
                        <h1><i class="bi bi-prescription"></i> Prescribe Medicine</h1>
                    </div>
                    <div>
                        <a href="appointments.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Back
                        </a>
                        <a href="prescriptions.php" class="btn btn-info">
                            <i class="bi bi-list-check"></i> View All Prescriptions
                        </a>
                    </div>
                </div>

                <?php if($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if($appointment): ?>
                <!-- Patient Information -->
                <div class="patient-info-box">
                    <div class="info-item">
                        <span><span class="label">Patient:</span> <strong><?php echo htmlspecialchars($appointment['patient_name']); ?></strong></span>
                        <span><span class="label">Phone:</span> <?php echo htmlspecialchars($appointment['patient_phone']); ?></span>
                        <span><span class="label">Appointment:</span> <?php echo date('M d, Y H:i', strtotime($appointment['appointment_date'])); ?></span>
                        <span><span class="label">Status:</span> 
                            <span class="badge-status <?php echo $appointment['status']; ?>">
                                <?php echo htmlspecialchars($appointment['status']); ?>
                            </span>
                        </span>
                    </div>
                </div>
                <?php endif; ?>

                <div class="form-container">
                    <div class="form-card">
                        <form method="POST">
                            <input type="hidden" name="appointment_id" value="<?php echo $appointment_id; ?>">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="patient_id" class="form-label required-field">
                                        <i class="bi bi-person"></i> Select Patient
                                    </label>
                                    <div class="input-icon-wrapper">
                                        <i class="bi bi-person input-icon"></i>
                                        <select class="form-select form-select-lg" id="patient_id" name="patient_id" required <?php echo $appointment ? 'disabled' : ''; ?>>
                                            <option value="">Select a patient...</option>
                                            <?php if($appointment): ?>
                                                <option value="<?php echo $appointment['patient_id']; ?>" selected>
                                                    <?php echo htmlspecialchars($appointment['patient_name']); ?>
                                                </option>
                                            <?php else: ?>
                                                <?php foreach($patients as $patient): ?>
                                                    <option value="<?php echo $patient['id']; ?>" 
                                                        <?php echo (isset($patient_filter) && $patient_filter == $patient['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($patient['fullname']); ?>
                                                        <?php if(!empty($patient['phone'])): ?>
                                                            (<?php echo htmlspecialchars($patient['phone']); ?>)
                                                        <?php endif; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                        <?php if($appointment): ?>
                                            <input type="hidden" name="patient_id" value="<?php echo $appointment['patient_id']; ?>">
                                        <?php endif; ?>
                                    </div>
                                    <?php if(empty($patients) && !$appointment): ?>
                                        <small class="text-danger">
                                            <i class="bi bi-exclamation-circle"></i> 
                                            No patients found. Please book an appointment first.
                                        </small>
                                    <?php endif; ?>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="medicine_id" class="form-label required-field">
                                        <i class="bi bi-capsule"></i> Select Medicine
                                    </label>
                                    <div class="input-icon-wrapper">
                                        <i class="bi bi-capsule input-icon"></i>
                                        <select class="form-select form-select-lg" id="medicine_id" name="medicine_id" required>
                                            <option value="">Select a medicine...</option>
                                            <?php foreach($medicines as $medicine): ?>
                                                <option value="<?php echo $medicine['id']; ?>" 
                                                    <?php echo (isset($_POST['medicine_id']) && $_POST['medicine_id'] == $medicine['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($medicine['name']); ?> 
                                                    (<?php echo $medicine['quantity']; ?> in stock - $<?php echo number_format($medicine['unit_price'], 2); ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <?php if(empty($medicines)): ?>
                                        <small class="text-danger">
                                            <i class="bi bi-exclamation-circle"></i> No medicines available. Please contact pharmacy.
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="quantity" class="form-label required-field">
                                        <i class="bi bi-box"></i> Quantity
                                    </label>
                                    <div class="input-icon-wrapper">
                                        <i class="bi bi-box input-icon"></i>
                                        <input type="number" class="form-control form-control-lg" id="quantity" name="quantity" 
                                               placeholder="Enter quantity" required min="1"
                                               value="<?php echo htmlspecialchars($_POST['quantity'] ?? 1); ?>">
                                    </div>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="dosage" class="form-label required-field">
                                        <i class="bi bi-eyedropper"></i> Dosage
                                    </label>
                                    <div class="input-icon-wrapper">
                                        <i class="bi bi-eyedropper input-icon"></i>
                                        <input type="text" class="form-control form-control-lg" id="dosage" name="dosage" 
                                               placeholder="e.g., 500mg" required
                                               value="<?php echo htmlspecialchars($_POST['dosage'] ?? ''); ?>">
                                    </div>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="frequency" class="form-label required-field">
                                        <i class="bi bi-clock"></i> Frequency
                                    </label>
                                    <div class="input-icon-wrapper">
                                        <i class="bi bi-clock input-icon"></i>
                                        <input type="text" class="form-control form-control-lg" id="frequency" name="frequency" 
                                               placeholder="e.g., Twice daily" required
                                               value="<?php echo htmlspecialchars($_POST['frequency'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="duration" class="form-label">
                                        <i class="bi bi-calendar3"></i> Duration
                                    </label>
                                    <div class="input-icon-wrapper">
                                        <i class="bi bi-calendar3 input-icon"></i>
                                        <input type="text" class="form-control form-control-lg" id="duration" name="duration" 
                                               placeholder="e.g., 5 days"
                                               value="<?php echo htmlspecialchars($_POST['duration'] ?? ''); ?>">
                                    </div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="prescription_status" class="form-label">
                                        <i class="bi bi-tag"></i> Status
                                    </label>
                                    <div class="input-icon-wrapper">
                                        <i class="bi bi-tag input-icon"></i>
                                        <select class="form-select form-select-lg" id="prescription_status" name="prescription_status">
                                            <option value="pending" selected>Pending (Send to Pharmacy)</option>
                                            <option value="dispensed">Dispensed (Patient received)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="instructions" class="form-label">
                                        <i class="bi bi-file-text"></i> Special Instructions
                                    </label>
                                    <div class="input-icon-wrapper">
                                        <i class="bi bi-file-text input-icon" style="top: 25px;"></i>
                                        <textarea class="form-control form-control-lg" id="instructions" name="instructions" rows="3" 
                                                  placeholder="Additional instructions for the patient..."><?php echo htmlspecialchars($_POST['instructions'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <?php if(!empty($medicines)): ?>
                                <div class="d-flex gap-2 mt-4">
                                    <button type="submit" class="btn btn-primary btn-lg px-5" <?php echo empty($medicines) ? 'disabled' : ''; ?>>
                                        <i class="bi bi-prescription"></i> Prescribe Medicine
                                    </button>
                                    <button type="reset" class="btn btn-outline-secondary btn-lg px-4">
                                        <i class="bi bi-arrow-counterclockwise"></i> Reset
                                    </button>
                                    <a href="appointments.php" class="btn btn-outline-danger btn-lg px-4">
                                        <i class="bi bi-x-lg"></i> Cancel
                                    </a>
                                </div>
                            <?php endif; ?>
                        </form>
                    </div>

                    <!-- Existing Prescriptions -->
                    <?php if(!empty($existing_prescriptions)): ?>
                    <div class="card mt-4">
                        <div class="card-header">
                            <i class="bi bi-list-check text-primary"></i> Existing Prescriptions
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php foreach($existing_prescriptions as $prescription): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong><?php echo htmlspecialchars($prescription['medicine_name']); ?></strong>
                                            <br>
                                            <small>
                                                <i class="bi bi-eyedropper"></i> <?php echo htmlspecialchars($prescription['dosage']); ?>
                                                <span class="ms-2"><i class="bi bi-clock"></i> <?php echo htmlspecialchars($prescription['frequency']); ?></span>
                                                <?php if($prescription['duration']): ?>
                                                    <span class="ms-2"><i class="bi bi-calendar3"></i> <?php echo htmlspecialchars($prescription['duration']); ?></span>
                                                <?php endif; ?>
                                                <span class="ms-2"><i class="bi bi-box"></i> Qty: <?php echo $prescription['quantity']; ?></span>
                                            </small>
                                            <?php if($prescription['instructions']): ?>
                                                <br>
                                                <small class="text-muted"><i class="bi bi-file-text"></i> <?php echo htmlspecialchars($prescription['instructions']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <span class="badge-status <?php echo $prescription['status']; ?>">
                                                <?php echo ucfirst($prescription['status']); ?>
                                            </span>
                                            <br>
                                            <small class="text-muted"><?php echo date('M d, Y H:i', strtotime($prescription['prescribed_at'])); ?></small>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelector('form').addEventListener('submit', function(e) {
            const patient_id = document.getElementById('patient_id');
            const medicine_id = document.getElementById('medicine_id').value;
            const quantity = document.getElementById('quantity').value.trim();
            const dosage = document.getElementById('dosage').value.trim();
            const frequency = document.getElementById('frequency').value.trim();
            
            let isValid = true;
            
            document.querySelectorAll('.form-control, .form-select').forEach(el => {
                el.classList.remove('is-invalid');
            });
            
            if (patient_id && !patient_id.value) {
                patient_id.classList.add('is-invalid');
                isValid = false;
            }
            
            if (!medicine_id) {
                document.getElementById('medicine_id').classList.add('is-invalid');
                isValid = false;
            }
            
            if (!quantity || parseInt(quantity) < 1) {
                document.getElementById('quantity').classList.add('is-invalid');
                isValid = false;
            }
            
            if (!dosage) {
                document.getElementById('dosage').classList.add('is-invalid');
                isValid = false;
            }
            
            if (!frequency) {
                document.getElementById('frequency').classList.add('is-invalid');
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
                const firstInvalid = document.querySelector('.is-invalid');
                if (firstInvalid) {
                    firstInvalid.focus();
                }
                return false;
            }
        });

        document.querySelectorAll('.form-control, .form-select').forEach(element => {
            element.addEventListener('input', function() {
                if (this.value.trim()) {
                    this.classList.remove('is-invalid');
                }
            });
            
            element.addEventListener('change', function() {
                if (this.value.trim()) {
                    this.classList.remove('is-invalid');
                }
            });
        });
    </script>
</body>
</html>