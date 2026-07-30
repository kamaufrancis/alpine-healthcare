<?php
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
$error = '';
$success = '';
$prescription_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$prescription = null;

if($prescription_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT p.*, pt.fullname as patient_name, pt.phone as patient_phone,
                               d.fullname as doctor_name, m.name as medicine_name, m.unit_price
                               FROM prescriptions p
                               JOIN patients pt ON p.patient_id = pt.id
                               JOIN doctors d ON p.doctor_id = d.id
                               JOIN medicines m ON p.medicine_id = m.id
                               WHERE p.id = ? AND p.status = 'pending'");
        $stmt->execute([$prescription_id]);
        $prescription = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if(!$prescription) {
            header('Location: index.php?error=notfound');
            exit();
        }
    } catch(PDOException $e) {
        $error = 'Error loading prescription: ' . $e->getMessage();
    }
} else {
    header('Location: index.php');
    exit();
}

if($_SERVER['REQUEST_METHOD'] == 'POST' && $prescription) {
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Update medicine stock
        $stmt = $pdo->prepare("UPDATE medicines SET quantity = quantity - ? WHERE id = ? AND quantity >= ?");
        $stmt->execute([$prescription['quantity'], $prescription['medicine_id'], $prescription['quantity']]);
        
        if($stmt->rowCount() > 0) {
            // Update prescription status
            $stmt = $pdo->prepare("UPDATE prescriptions SET status = 'dispensed', dispensed_at = NOW() WHERE id = ?");
            $stmt->execute([$prescription_id]);
            
            // Update appointment prescription status
            $stmt = $pdo->prepare("UPDATE appointments SET prescription_status = 'dispensed' WHERE id = ?");
            $stmt->execute([$prescription['appointment_id']]);
            
            // Record in dispensed_medicines
            $stmt = $pdo->prepare("INSERT INTO dispensed_medicines (patient_id, medicine_id, quantity, prescription_id) 
                                   VALUES (?, ?, ?, ?)");
            $stmt->execute([$prescription['patient_id'], $prescription['medicine_id'], $prescription['quantity'], $prescription_id]);
            
            // Commit transaction
            $pdo->commit();
            
            // Log activity
            try {
                require_once '../../core/logger.php';
                $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
                logActivity($pdo, $user_id, 'Dispensed prescription', 'Prescription ID: ' . $prescription_id . ' - Medicine: ' . $prescription['medicine_name']);
            } catch (Exception $e) {
                error_log("Failed to log activity: " . $e->getMessage());
            }
            
            $success = 'Prescription dispensed successfully!';
            
            // Redirect after short delay
            echo '<meta http-equiv="refresh" content="2;url=index.php">';
        } else {
            $error = 'Insufficient stock to dispense this prescription.';
            $pdo->rollBack();
        }
    } catch(PDOException $e) {
        $pdo->rollBack();
        $error = 'Database error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dispense Prescription - Alpine Healthcare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* Same sidebar styles as before */
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
        .sidebar::-webkit-scrollbar-thumb { background: #3498db; border-radius: 2px; }
        
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
            background: linear-gradient(135deg, #3498db, #2980b9);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 18px;
            flex-shrink: 0;
            overflow: hidden;
        }
        .sidebar .avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }
        
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
        
        .sidebar .nav-item:hover { background: rgba(255,255,255,0.05); color: white; border-left-color: #3498db; }
        .sidebar .nav-item.active { background: rgba(52, 152, 219, 0.1); color: #3498db; border-left-color: #3498db; }
        .sidebar .nav-item i { width: 20px; font-size: 16px; }
        
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
        .page-header h1 i { color: #3498db; margin-right: 10px; }
        
        .form-container { max-width: 800px; margin: 0 auto; }
        
        .form-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.04);
            padding: 30px;
            transition: all 0.3s ease;
        }
        
        .form-card:hover { box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
        
        .info-box {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 20px;
            border-left: 4px solid #3498db;
        }
        
        .info-box .info-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .info-box .info-item:last-child { border-bottom: none; }
        .info-box .label { font-weight: 600; color: #495057; }
        .info-box .value { color: #212529; }
        
        .btn-primary {
            background: linear-gradient(135deg, #3498db, #2980b9);
            border: none;
            padding: 10px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(52, 152, 219, 0.3);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            border: none;
            padding: 10px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(46, 204, 113, 0.3);
        }
        
        .badge-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 12px;
        }
        
        .badge-status.pending { background: #fff3cd; color: #856404; }
        .badge-status.dispensed { background: #d4edda; color: #155724; }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .alert-success { background: #d4edda; color: #155724; }
        .alert-danger { background: #f8d7da; color: #721c24; }
        
        @media (max-width: 768px) {
            .sidebar { min-height: auto; height: auto; position: relative; }
            .main-content { padding: 15px; }
            .page-header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .form-card { padding: 20px; }
            .info-box .info-item { flex-direction: column; gap: 4px; }
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
                    <small>Pharmacy Panel</small>
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
                        <div class="user-role"><i class="bi bi-shield-check"></i> <?php echo ucfirst($user_data['role'] ?? 'pharmacist'); ?></div>
                    </div>
                </div>
                
                <div class="nav-section">Main Menu</div>
                <a href="index.php" class="nav-item">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <a href="stock.php" class="nav-item">
                    <i class="bi bi-capsule"></i> Medicines
                </a>
                <a href="add.php" class="nav-item">
                    <i class="bi bi-plus-circle"></i> Add Medicine
                </a>
                <a href="dispense_prescription.php" class="nav-item active">
                    <i class="bi bi-prescription"></i> Dispense
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
                        <h1><i class="bi bi-prescription"></i> Dispense Prescription</h1>
                    </div>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Stock
                    </a>
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

                <?php if($prescription): ?>
                <div class="form-container">
                    <div class="form-card">
                        <!-- Prescription Details -->
                        <div class="info-box">
                            <h6><i class="bi bi-info-circle"></i> Prescription Details</h6>
                            <div class="info-item">
                                <span class="label">Prescription ID:</span>
                                <span class="value">#<?php echo str_pad($prescription['id'], 5, '0', STR_PAD_LEFT); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="label">Patient:</span>
                                <span class="value"><strong><?php echo htmlspecialchars($prescription['patient_name']); ?></strong></span>
                            </div>
                            <div class="info-item">
                                <span class="label">Phone:</span>
                                <span class="value"><?php echo htmlspecialchars($prescription['patient_phone']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="label">Doctor:</span>
                                <span class="value">Dr. <?php echo htmlspecialchars($prescription['doctor_name']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="label">Medicine:</span>
                                <span class="value"><strong><?php echo htmlspecialchars($prescription['medicine_name']); ?></strong></span>
                            </div>
                            <div class="info-item">
                                <span class="label">Quantity:</span>
                                <span class="value"><?php echo $prescription['quantity']; ?> units</span>
                            </div>
                            <div class="info-item">
                                <span class="label">Dosage:</span>
                                <span class="value"><?php echo htmlspecialchars($prescription['dosage']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="label">Frequency:</span>
                                <span class="value"><?php echo htmlspecialchars($prescription['frequency']); ?></span>
                            </div>
                            <?php if($prescription['duration']): ?>
                            <div class="info-item">
                                <span class="label">Duration:</span>
                                <span class="value"><?php echo htmlspecialchars($prescription['duration']); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if($prescription['instructions']): ?>
                            <div class="info-item">
                                <span class="label">Instructions:</span>
                                <span class="value"><?php echo htmlspecialchars($prescription['instructions']); ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="info-item">
                                <span class="label">Prescribed At:</span>
                                <span class="value"><?php echo date('M d, Y H:i', strtotime($prescription['prescribed_at'])); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="label">Status:</span>
                                <span class="value">
                                    <span class="badge-status <?php echo $prescription['status']; ?>">
                                        <?php echo ucfirst($prescription['status']); ?>
                                    </span>
                                </span>
                            </div>
                        </div>

                        <?php if($prescription['status'] == 'pending'): ?>
                        <form method="POST">
                            <div class="d-flex gap-2 mt-4">
                                <button type="submit" class="btn btn-success btn-lg px-5">
                                    <i class="bi bi-check-lg"></i> Confirm Dispense
                                </button>
                                <a href="index.php" class="btn btn-outline-danger btn-lg px-4">
                                    <i class="bi bi-x-lg"></i> Cancel
                                </a>
                            </div>
                            <small class="text-muted d-block mt-3">
                                <i class="bi bi-info-circle"></i> 
                                This will reduce the stock quantity and mark the prescription as dispensed.
                            </small>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>