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

// Get statistics
$stmt = $pdo->query("SELECT COUNT(*) as total FROM medicines");
$total_medicines = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->query("SELECT SUM(quantity) as total FROM medicines");
$total_stock = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$stmt = $pdo->query("SELECT COUNT(*) as total FROM medicines WHERE quantity <= 10");
$low_stock = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM medicines WHERE quantity <= 0");
$out_of_stock = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get pending prescriptions count
$stmt = $pdo->query("SELECT COUNT(*) as total FROM prescriptions WHERE status = 'pending'");
$pending_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get recent prescriptions
$stmt = $pdo->query("SELECT p.*, pt.fullname as patient_name, d.fullname as doctor_name, m.name as medicine_name
                     FROM prescriptions p
                     JOIN patients pt ON p.patient_id = pt.id
                     JOIN users d ON p.doctor_id = d.id
                     JOIN medicines m ON p.medicine_id = m.id
                     WHERE p.status = 'pending'
                     ORDER BY p.prescribed_at DESC LIMIT 10");
$pending_rx_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query("SELECT SUM(quantity) as total FROM dispensed_medicines WHERE DATE(dispensed_at) = CURDATE()");
$today_dispensed = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Get recent dispenses
$stmt = $pdo->query("SELECT d.*, p.fullname as patient_name, m.name as medicine_name 
                     FROM dispensed_medicines d 
                     JOIN patients p ON d.patient_id = p.id 
                     JOIN medicines m ON d.medicine_id = m.id 
                     ORDER BY d.dispensed_at DESC LIMIT 10");
$recent_dispenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get low stock items
$stmt = $pdo->query("SELECT * FROM medicines WHERE quantity <= 10 ORDER BY quantity ASC LIMIT 5");
$low_stock_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Dashboard - Alpine Healthcare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        * { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; }
        body { background: #f4f6fb; }
        
        /* Sidebar Styles */
        .sidebar {
            min-height: 100vh;
            background: #1a2332;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        
        .sidebar::-webkit-scrollbar {
            width: 4px;
        }
        
        .sidebar::-webkit-scrollbar-thumb {
            background: #3498db;
            border-radius: 2px;
        }
        
        .sidebar .brand {
            padding: 20px 20px 15px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            text-align: center;
        }
        
        .sidebar .brand h4 {
            color: white;
            font-weight: 700;
            margin: 0;
        }
        
        .sidebar .brand h4 span {
            color: #2ecc71;
        }
        
        .sidebar .brand small {
            color: rgba(255,255,255,0.4);
            font-size: 11px;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        
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
        .sidebar .avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }
        .sidebar .user-info .user-name {
            color: white;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 2px;
        }
        
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
        
        .sidebar .nav-item:hover {
            background: rgba(255,255,255,0.05);
            color: white;
            border-left-color: #2ecc71;
        }
        
        .sidebar .nav-item.active {
            background: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
            border-left-color: #2ecc71;
        }
        
        .sidebar .nav-item i {
            width: 20px;
            font-size: 16px;
        }
        
        .sidebar .nav-item .badge {
            margin-left: auto;
            background: #e74c3c;
            font-size: 10px;
            padding: 2px 8px;
        }
        
        /* Main Content */
        .main-content {
            padding: 20px 30px;
        }
        
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
        
        .page-header h1 {
            font-size: 24px;
            font-weight: 700;
            color: #1a2332;
            margin: 0;
        }
        
        .page-header h1 i {
            color: #2ecc71;
            margin-right: 10px;
        }
        
        .page-header .breadcrumb {
            background: none;
            padding: 0;
            margin: 0;
        }
        
        .page-header .breadcrumb-item a {
            color: #6c757d;
            text-decoration: none;
        }
        
        .page-header .breadcrumb-item.active {
            color: #1a2332;
            font-weight: 600;
        }
        
        /* Stats Cards */
        .stat-card {
            border: none;
            border-radius: 12px;
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .stat-card .card-body {
            padding: 20px;
        }
        
        .stat-card .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .stat-card .stat-label {
            font-size: 13px;
            font-weight: 500;
            opacity: 0.8;
            margin-bottom: 4px;
        }
        
        .stat-card .stat-number {
            font-size: 28px;
            font-weight: 700;
            margin: 0;
        }
        
        .stat-card .stat-change {
            font-size: 12px;
            font-weight: 600;
            margin-top: 8px;
        }
        
        /* Cards */
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.04);
            transition: all 0.3s ease;
        }
        
        .card:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
        
        .card-header {
            background: white;
            border-bottom: 1px solid #f0f2f5;
            padding: 15px 20px;
            border-radius: 12px 12px 0 0 !important;
            font-weight: 600;
            color: #1a2332;
        }
        
        .card-header i {
            margin-right: 8px;
        }
        
        .table th {
            font-weight: 600;
            color: #495057;
            border-top: none;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .table td {
            vertical-align: middle;
            font-size: 14px;
        }
        
        .badge-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 12px;
        }
        
        .badge-status.in-stock {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-status.low-stock {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-status.out-of-stock {
            background: #f8d7da;
            color: #721c24;
        }
        
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
        
        .btn-outline-primary {
            border-color: #2ecc71;
            color: #2ecc71;
        }
        
        .btn-outline-primary:hover {
            background: #2ecc71;
            border-color: #2ecc71;
            color: white;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                min-height: auto;
                height: auto;
                position: relative;
            }
            
            .main-content {
                padding: 15px;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .stat-card .stat-number {
                font-size: 22px;
            }
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
                <a href="index.php" class="nav-item active">
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
                        <h1><i class="bi bi-speedometer2"></i> Dashboard</h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item active">Dashboard</li>
                            </ol>
                        </nav>
                    </div>
                    <div>
                        <a href="add.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Add Medicine
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
                                        <div class="stat-label">Total Medicines</div>
                                        <h2 class="stat-number"><?php echo $total_medicines; ?></h2>
                                    </div>
                                    <div class="stat-icon bg-white bg-opacity-25">
                                        <i class="bi bi-capsule"></i>
                                    </div>
                                </div>
                                <div class="stat-change">
                                    <i class="bi bi-arrow-up"></i> Active inventory
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card stat-card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stat-label">Total Stock</div>
                                        <h2 class="stat-number"><?php echo number_format($total_stock); ?></h2>
                                    </div>
                                    <div class="stat-icon bg-white bg-opacity-25">
                                        <i class="bi bi-box-seam"></i>
                                    </div>
                                </div>
                                <div class="stat-change">
                                    <i class="bi bi-box"></i> Units available
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card stat-card bg-warning text-dark">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stat-label">Low Stock</div>
                                        <h2 class="stat-number"><?php echo $low_stock; ?></h2>
                                    </div>
                                    <div class="stat-icon bg-white bg-opacity-25">
                                        <i class="bi bi-exclamation-triangle"></i>
                                    </div>
                                </div>
                                <div class="stat-change text-danger">
                                    <i class="bi bi-arrow-down"></i> Need restock
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card stat-card bg-warning text-dark">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stat-label">Pending Prescriptions</div>
                                        <h2 class="stat-number"><?php echo $pending_prescriptions; ?></h2>
                                    </div>
                                    <div class="stat-icon bg-white bg-opacity-25">
                                        <i class="bi bi-prescription"></i>
                                    </div>
                                </div>
                                <div class="stat-change">
                                    <i class="bi bi-clock"></i> Awaiting dispensing
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card stat-card bg-danger text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stat-label">Out of Stock</div>
                                        <h2 class="stat-number"><?php echo $out_of_stock; ?></h2>
                                    </div>
                                    <div class="stat-icon bg-white bg-opacity-25">
                                        <i class="bi bi-x-circle"></i>
                                    </div>
                                </div>
                                <div class="stat-change">
                                    <i class="bi bi-arrow-up"></i> Urgent attention
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <!-- Today's Activity -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-clock-history text-primary"></i> Recent Dispenses
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Patient</th>
                                                <th>Medicine</th>
                                                <th>Qty</th>
                                                <th>Time</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if(empty($recent_dispenses)): ?>
                                                <tr>
                                                    <td colspan="4" class="text-center py-3 text-muted">
                                                        <i class="bi bi-inbox"></i> No dispenses yet
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach($recent_dispenses as $dispense): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($dispense['patient_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($dispense['medicine_name']); ?></td>
                                                    <td><span class="badge bg-primary"><?php echo $dispense['quantity']; ?></span></td>
                                                    <td><small><?php echo date('H:i', strtotime($dispense['dispensed_at'])); ?></small></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Low Stock Items -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-exclamation-triangle text-warning"></i> Low Stock Items
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Medicine</th>
                                                <th>Stock</th>
                                                <th>Status</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if(empty($low_stock_items)): ?>
                                                <tr>
                                                    <td colspan="4" class="text-center py-3 text-muted">
                                                        <i class="bi bi-check-circle text-success"></i> All items well stocked
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach($low_stock_items as $item): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                                                    <td><strong><?php echo $item['quantity']; ?></strong></td>
                                                    <td>
                                                        <?php if($item['quantity'] <= 0): ?>
                                                            <span class="badge-status out-of-stock">Out of Stock</span>
                                                        <?php else: ?>
                                                            <span class="badge-status low-stock">Low Stock</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <a href="edit.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                            <i class="bi bi-pencil"></i> Restock
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
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>