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
    header('Location: index.php?error=invalid');
    exit();
}

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $expiry = trim($_POST['expiry'] ?? '');
    
    // Validation
    if(empty($name)) {
        $error = 'Medicine name is required!';
    } elseif(empty($category)) {
        $error = 'Category is required!';
    } elseif($quantity < 0) {
        $error = 'Valid quantity is required!';
    } elseif($price < 0) {
        $error = 'Valid price is required!';
    } elseif(empty($expiry)) {
        $error = 'Expiry date is required!';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE medicines SET name = ?, category = ?, quantity = ?, unit_price = ?, expiry_date = ? WHERE id = ?");
            if ($stmt->execute([$name, $category, $quantity, $price, $expiry, $id])) {
                // Log activity
                require_once '../../core/logger.php';
                $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
                logActivity($pdo, $user_id, 'Updated medicine', $name . ' (ID: ' . $id . ')');
                
                $success = 'Medicine updated successfully!';
                
                // Refresh medicine data
                $stmt = $pdo->prepare("SELECT * FROM medicines WHERE id = ?");
                $stmt->execute([$id]);
                $medicine = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $error = 'Failed to update medicine!';
            }
        } catch(PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Medicine - Alpine Healthcare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* Sidebar Styles */
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
        .page-header h1 i { color: #f39c12; margin-right: 10px; }
        
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
        
        .btn-warning {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            border: none;
            padding: 10px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
            color: white;
        }
        
        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(243, 156, 18, 0.3);
            color: white;
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
        
        .medicine-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 20px;
            border-left: 4px solid #f39c12;
        }
        
        .medicine-info .info-item {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .medicine-info .info-item span {
            font-size: 14px;
        }
        
        .medicine-info .label {
            font-weight: 600;
            color: #495057;
        }
        
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
        
        @media (max-width: 768px) {
            .sidebar { min-height: auto; height: auto; position: relative; }
            .main-content { padding: 15px; }
            .page-header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .form-card { padding: 20px; }
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
                <a href="dispense.php" class="nav-item">
                    <i class="bi bi-prescription"></i> Dispense
                </a>
                <a href="edit.php?id=<?php echo $id; ?>" class="nav-item active">
                    <i class="bi bi-pencil"></i> Edit Medicine
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
                    <h1><i class="bi bi-pencil"></i> Edit Medicine</h1>
                    <div>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Stock
                        </a>
                        <a href="dispense.php?id=<?php echo $id; ?>" class="btn btn-success">
                            <i class="bi bi-prescription"></i> Dispense
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

                <div class="form-container">
                    <!-- Medicine Info Summary -->
                    <div class="medicine-info">
                        <div class="info-item">
                            <span><span class="label">Medicine ID:</span> #<?php echo $medicine['id']; ?></span>
                            <span><span class="label">Current Stock:</span> <strong><?php echo $medicine['quantity']; ?></strong> units</span>
                            <span>
                                <span class="label">Status:</span>
                                <?php if($medicine['quantity'] <= 0): ?>
                                    <span class="stock-status out-of-stock">Out of Stock</span>
                                <?php elseif($medicine['quantity'] <= 10): ?>
                                    <span class="stock-status low-stock">Low Stock</span>
                                <?php else: ?>
                                    <span class="stock-status in-stock">In Stock</span>
                                <?php endif; ?>
                            </span>
                            <span><span class="label">Created:</span> <?php echo date('M d, Y', strtotime($medicine['created_at'])); ?></span>
                        </div>
                    </div>

                    <div class="form-card">
                        <form method="POST">
                            <div class="row">
                                <div class="col-12 mb-3">
                                    <label for="name" class="form-label required-field">
                                        <i class="bi bi-capsule"></i> Medicine Name
                                    </label>
                                    <div class="input-icon-wrapper">
                                        <i class="bi bi-capsule input-icon"></i>
                                        <input type="text" class="form-control form-control-lg" id="name" name="name" 
                                               placeholder="Enter medicine name" required
                                               value="<?php echo htmlspecialchars($medicine['name']); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="category" class="form-label required-field">
                                        <i class="bi bi-tags"></i> Category
                                    </label>
                                    <div class="input-icon-wrapper">
                                        <i class="bi bi-tags input-icon"></i>
                                        <input type="text" class="form-control form-control-lg" id="category" name="category" 
                                               placeholder="e.g., Antibiotic, Analgesic" required
                                               value="<?php echo htmlspecialchars($medicine['category']); ?>">
                                    </div>
                                    <small class="text-muted">Enter a category or use existing</small>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="quantity" class="form-label required-field">
                                        <i class="bi bi-box"></i> Quantity
                                    </label>
                                    <div class="input-icon-wrapper">
                                        <i class="bi bi-box input-icon"></i>
                                        <input type="number" class="form-control form-control-lg" id="quantity" name="quantity" 
                                               placeholder="Enter quantity" required min="0"
                                               value="<?php echo htmlspecialchars($medicine['quantity']); ?>">
                                    </div>
                                    <small class="text-muted">Current stock: <strong><?php echo $medicine['quantity']; ?></strong> units</small>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="price" class="form-label required-field">
                                        <i class="bi bi-currency-shiling"></i> Unit Price (KES)
                                    </label>
                                    <div class="input-icon-wrapper">
                                        <i class="bi bi-currency-shiling input-icon"></i>
                                        <input type="number" step="0.01" class="form-control form-control-lg" id="price" name="price" 
                                               placeholder="0.00" required min="0"
                                               value="<?php echo htmlspecialchars($medicine['unit_price']); ?>">
                                    </div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="expiry" class="form-label required-field">
                                        <i class="bi bi-calendar-event"></i> Expiry Date
                                    </label>
                                    <div class="input-icon-wrapper">
                                        <i class="bi bi-calendar-event input-icon"></i>
                                        <input type="date" class="form-control form-control-lg" id="expiry" name="expiry" required
                                               value="<?php echo htmlspecialchars($medicine['expiry_date']); ?>">
                                    </div>
                                    <small class="text-muted">
                                        <i class="bi bi-info-circle"></i> 
                                        <?php 
                                        $expiry = strtotime($medicine['expiry_date']);
                                        $now = time();
                                        $diff = $expiry - $now;
                                        $days = floor($diff / (60 * 60 * 24));
                                        if($days < 0) {
                                            echo '<span class="text-danger">Expired ' . abs($days) . ' days ago</span>';
                                        } elseif($days < 30) {
                                            echo '<span class="text-warning">Expires in ' . $days . ' days</span>';
                                        } else {
                                            echo 'Expires in ' . $days . ' days';
                                        }
                                        ?>
                                    </small>
                                </div>
                            </div>

                            <div class="d-flex gap-2 mt-4">
                                <button type="submit" class="btn btn-warning btn-lg px-5">
                                    <i class="bi bi-check-lg"></i> Update Medicine
                                </button>
                                <button type="reset" class="btn btn-outline-secondary btn-lg px-4">
                                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                                </button>
                                <a href="index.php" class="btn btn-outline-danger btn-lg px-4">
                                    <i class="bi bi-x-lg"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>

                    <!-- Danger Zone -->
                    <div class="card mt-4 border-danger">
                        <div class="card-header bg-danger text-white">
                            <i class="bi bi-exclamation-triangle"></i> Danger Zone
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">Delete this medicine</h6>
                                    <p class="text-muted mb-0 small">This action cannot be undone. All data associated with this medicine will be permanently removed.</p>
                                </div>
                                <a href="?delete=<?php echo $medicine['id']; ?>" class="btn btn-danger" 
                                   onclick="return confirm('Are you sure you want to delete this medicine? This action cannot be undone.')">
                                    <i class="bi bi-trash"></i> Delete Medicine
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();
            const category = document.getElementById('category').value.trim();
            const quantity = document.getElementById('quantity').value.trim();
            const price = document.getElementById('price').value.trim();
            const expiry = document.getElementById('expiry').value.trim();
            
            let isValid = true;
            
            // Reset validation
            document.querySelectorAll('.form-control, .form-select').forEach(el => {
                el.classList.remove('is-invalid');
            });
            
            if (!name) {
                document.getElementById('name').classList.add('is-invalid');
                isValid = false;
            }
            
            if (!category) {
                document.getElementById('category').classList.add('is-invalid');
                isValid = false;
            }
            
            if (!quantity || parseInt(quantity) < 0) {
                document.getElementById('quantity').classList.add('is-invalid');
                isValid = false;
            }
            
            if (!price || parseFloat(price) < 0) {
                document.getElementById('price').classList.add('is-invalid');
                isValid = false;
            }
            
            if (!expiry) {
                document.getElementById('expiry').classList.add('is-invalid');
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

        // Remove invalid class on input
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