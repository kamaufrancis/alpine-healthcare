<?php
require_once '../../core/auth.php';
requireLogin();
require_once '../../config/database.php';

try {
    require_once '../../core/logger.php';
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    logActivity($pdo, $user_id, 'Action', 'Details');
} catch (Exception $e) {
    // Logging failed, but we don't want to break the application
    error_log("Failed to log activity: " . $e->getMessage());
}

$user = getCurrentUser($pdo);
$user_data = $user ?: [];
if (empty($user_data)) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}
$error = '';
$success = '';
$medicine = null;
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get medicine details
if($id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM medicines WHERE id = ?");
        $stmt->execute([$id]);
        $medicine = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if(!$medicine) {
            header('Location: index.php?error=notfound');
            exit();
        }
    } catch(PDOException $e) {
        $error = 'Error loading medicine: ' . $e->getMessage();
    }
} else {
    // If no ID, show all medicines for selection
    $stmt = $pdo->query("SELECT id, name, quantity FROM medicines WHERE quantity > 0 ORDER BY name ASC");
    $medicines_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if($_SERVER['REQUEST_METHOD'] == 'POST' && $medicine) {
    $quantity = (int)($_POST['quantity'] ?? 0);
    $patient_id = (int)($_POST['patient_id'] ?? 0);
    $medicine_id = $medicine['id'];
    
    if($quantity <= 0) {
        $error = 'Please enter a valid quantity!';
    } elseif($quantity > $medicine['quantity']) {
        $error = 'Insufficient stock! Available: ' . $medicine['quantity'];
    } elseif($patient_id <= 0) {
        $error = 'Please select a patient!';
    } else {
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("UPDATE medicines SET quantity = quantity - ? WHERE id = ? AND quantity >= ?");
            $stmt->execute([$quantity, $medicine_id, $quantity]);
            
            if($stmt->rowCount() > 0) {
                $stmt = $pdo->prepare("INSERT INTO dispensed_medicines (patient_id, medicine_id, quantity) VALUES (?, ?, ?)");
                $stmt->execute([$patient_id, $medicine_id, $quantity]);
                
                $pdo->commit();
                
                require_once '../../core/logger.php';
                $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
                logActivity($pdo, $user_id, 'Dispensed medicine', $medicine['name'] . ' x' . $quantity . ' to patient ID: ' . $patient_id);
                
                $success = 'Medicine dispensed successfully!';
                
                // Refresh medicine data
                $stmt = $pdo->prepare("SELECT * FROM medicines WHERE id = ?");
                $stmt->execute([$medicine_id]);
                $medicine = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $error = 'Failed to update stock. Please try again.';
                $pdo->rollBack();
            }
        } catch(PDOException $e) {
            $pdo->rollBack();
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Get patients for dropdown
$patients = [];
try {
    $stmt = $pdo->query("SELECT id, fullname, phone FROM patients ORDER BY fullname ASC");
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $patients = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dispense Medicine - Alpine Healthcare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* Same styles as dashboard */
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
        
        .sidebar .nav-item:hover { background: rgba(255,255,255,0.05); color: white; border-left-color: #2ecc71; }
        .sidebar .nav-item.active { background: rgba(46, 204, 113, 0.1); color: #2ecc71; border-left-color: #2ecc71; }
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
        
        .medicine-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .medicine-info .info-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .medicine-info .info-item:last-child { border-bottom: none; }
        .medicine-info .label { font-weight: 600; color: #495057; }
        .medicine-info .value { color: #212529; }
        
        .stock-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        
        .stock-status.in-stock { background: #d4edda; color: #155724; }
        .stock-status.low-stock { background: #fff3cd; color: #856404; }
        .stock-status.out-of-stock { background: #f8d7da; color: #721c24; }
        
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
        
        @media (max-width: 768px) {
            .sidebar { min-height: auto; height: auto; position: relative; }
            .main-content { padding: 15px; }
            .page-header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .form-card { padding: 20px; }
            .medicine-info .info-item { flex-direction: column; gap: 4px; }
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
                    <small>Pharmacy Management</small>
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
                <a href="dispense.php" class="nav-item active">
                    <i class="bi bi-prescription"></i> Dispense
                </a>
                
                <a href="profile.php" class="nav-item">
                    <i class="bi bi-person"></i> Profile
                </a>
                
                <div class="nav-section">Account</div>
                <a href="../../logout.php" class="nav-item">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </nav>

            <!-- Main Content -->
            <main class="col-md-10 main-content">
                <div class="page-header">
                    <h1><i class="bi bi-prescription"></i> Dispense Medicine</h1>
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

                <?php if(!$medicine): ?>
                    <!-- Medicine Selection -->
                    <div class="form-container">
                        <div class="form-card">
                            <h5><i class="bi bi-search"></i> Select Medicine to Dispense</h5>
                            <?php if(empty($medicines_list)): ?>
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle"></i> No medicines available in stock.
                                    <a href="add.php" class="alert-link">Add medicine</a>
                                </div>
                            <?php else: ?>
                                <div class="list-group">
                                    <?php foreach($medicines_list as $item): ?>
                                        <a href="dispense.php?id=<?php echo $item['id']; ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                            <div>
                                                <i class="bi bi-capsule text-primary"></i>
                                                <?php echo htmlspecialchars($item['name']); ?>
                                            </div>
                                            <span class="badge bg-primary rounded-pill"><?php echo $item['quantity']; ?> units</span>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Dispense Form -->
                    <div class="form-container">
                        <!-- Medicine Info -->
                        <div class="medicine-info">
                            <h5><i class="bi bi-info-circle"></i> Medicine Details</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="info-item">
                                        <span class="label">Medicine Name:</span>
                                        <span class="value"><strong><?php echo htmlspecialchars($medicine['name']); ?></strong></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="label">Category:</span>
                                        <span class="value"><?php echo htmlspecialchars($medicine['category']); ?></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-item">
                                        <span class="label">Current Stock:</span>
                                        <span class="value">
                                            <strong><?php echo $medicine['quantity']; ?></strong> units
                                            <?php if($medicine['quantity'] <= 0): ?>
                                                <span class="stock-status out-of-stock">Out of Stock</span>
                                            <?php elseif($medicine['quantity'] <= 10): ?>
                                                <span class="stock-status low-stock">Low Stock</span>
                                            <?php else: ?>
                                                <span class="stock-status in-stock">In Stock</span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <div class="info-item">
                                        <span class="label">Unit Price (KES):</span>
                                        <span class="value">KSh <?php echo number_format($medicine['unit_price'], 2); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-card">
                            <form method="POST">
                                <div class="row">
                                    <div class="col-12 mb-3">
                                        <label for="patient_id" class="form-label required-field">
                                            <i class="bi bi-person"></i> Select Patient
                                        </label>
                                        <div class="input-icon-wrapper">
                                            <i class="bi bi-person input-icon"></i>
                                            <select class="form-select form-select-lg" id="patient_id" name="patient_id" required>
                                                <option value="">Select a patient...</option>
                                                <?php foreach($patients as $patient): ?>
                                                    <option value="<?php echo $patient['id']; ?>" 
                                                        <?php echo (isset($_POST['patient_id']) && $_POST['patient_id'] == $patient['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($patient['fullname']); ?>
                                                        <?php if(!empty($patient['phone'])): ?>
                                                            (<?php echo htmlspecialchars($patient['phone']); ?>)
                                                        <?php endif; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <?php if(empty($patients)): ?>
                                            <small class="text-danger">
                                                <i class="bi bi-exclamation-circle"></i> 
                                                No patients found. Please <a href="../patients/add.php">add a patient</a> first.
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-12 mb-3">
                                        <label for="quantity" class="form-label required-field">
                                            <i class="bi bi-box"></i> Quantity to Dispense
                                        </label>
                                        <div class="input-icon-wrapper">
                                            <i class="bi bi-box input-icon"></i>
                                            <input type="number" class="form-control form-control-lg" id="quantity" name="quantity" 
                                                   placeholder="Enter quantity to dispense" required min="1" 
                                                   max="<?php echo $medicine['quantity']; ?>"
                                                   value="<?php echo htmlspecialchars($_POST['quantity'] ?? 1); ?>">
                                        </div>
                                        <small class="text-muted">
                                            <i class="bi bi-info-circle"></i> 
                                            Available stock: <strong><?php echo $medicine['quantity']; ?></strong> units
                                        </small>
                                    </div>
                                </div>

                                <?php if($medicine['quantity'] <= 0): ?>
                                    <div class="alert alert-warning">
                                        <i class="bi bi-exclamation-triangle"></i>
                                        This medicine is currently out of stock. Please restock before dispensing.
                                    </div>
                                <?php endif; ?>

                                <div class="d-flex gap-2 mt-4">
                                    <button type="submit" class="btn btn-success btn-lg px-5" 
                                            <?php echo ($medicine['quantity'] <= 0) ? 'disabled' : ''; ?>>
                                        <i class="bi bi-check-lg"></i> Confirm Dispense
                                    </button>
                                    <a href="dispense.php" class="btn btn-outline-secondary btn-lg px-4">
                                        <i class="bi bi-arrow-counterclockwise"></i> Change Medicine
                                    </a>
                                    <a href="index.php" class="btn btn-outline-danger btn-lg px-4">
                                        <i class="bi bi-x-lg"></i> Cancel
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('dispenseForm')?.addEventListener('submit', function(e) {
            const patient_id = document.getElementById('patient_id').value;
            const quantity = document.getElementById('quantity').value.trim();
            const maxStock = <?php echo $medicine['quantity'] ?? 0; ?>;
            
            let isValid = true;
            
            document.querySelectorAll('.form-control, .form-select').forEach(el => {
                el.classList.remove('is-invalid');
            });
            
            if (!patient_id) {
                document.getElementById('patient_id').classList.add('is-invalid');
                isValid = false;
            }
            
            if (!quantity || parseInt(quantity) < 1) {
                document.getElementById('quantity').classList.add('is-invalid');
                isValid = false;
            } else if (parseInt(quantity) > maxStock) {
                document.getElementById('quantity').classList.add('is-invalid');
                document.getElementById('quantity').nextElementSibling.textContent = 'Insufficient stock! Available: ' + maxStock;
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
                const firstInvalid = document.querySelector('.is-invalid');
                if (firstInvalid) firstInvalid.focus();
                return false;
            }
            
            if (!confirm('Are you sure you want to dispense this medicine?')) {
                e.preventDefault();
                return false;
            }
        });
    </script>
</body>
</html>