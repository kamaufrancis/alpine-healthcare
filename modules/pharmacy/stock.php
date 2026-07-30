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
$message = '';
$error = '';

// Handle delete
if(isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM medicines WHERE id = ?");
    if($stmt->execute([$_GET['delete']])) {
        $message = "Medicine deleted successfully!";
    }
}

// Handle search
$search = $_GET['search'] ?? '';
if($search) {
    require_once '../../core/search.php';
    $medicines = searchMedicines($pdo, $search);
} else {
    $stmt = $pdo->query("SELECT * FROM medicines ORDER BY created_at DESC");
    $medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get category filter
$category_filter = $_GET['category'] ?? '';
if($category_filter) {
    $stmt = $pdo->prepare("SELECT * FROM medicines WHERE category = ? ORDER BY created_at DESC");
    $stmt->execute([$category_filter]);
    $medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get unique categories for filter
$stmt = $pdo->query("SELECT DISTINCT category FROM medicines ORDER BY category ASC");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pending prescriptions count
$stmt = $pdo->query("SELECT COUNT(*) as total FROM prescriptions WHERE status = 'pending'");
$pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medicine Stock - Alpine Healthcare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* Same sidebar styles as dashboard */
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
        
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.04);
            transition: all 0.3s ease;
        }
        
        .card:hover { box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
        .card-header {
            background: white;
            border-bottom: 1px solid #f0f2f5;
            padding: 15px 20px;
            border-radius: 12px 12px 0 0 !important;
            font-weight: 600;
            color: #1a2332;
        }
        
        .table th { font-weight: 600; color: #495057; border-top: none; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
        .table td { vertical-align: middle; font-size: 14px; }
        
        .badge-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 12px;
        }
        
        .badge-status.in-stock { background: #d4edda; color: #155724; }
        .badge-status.low-stock { background: #fff3cd; color: #856404; }
        .badge-status.out-of-stock { background: #f8d7da; color: #721c24; }
        
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
                <a href="stock.php" class="nav-item active">
                    <i class="bi bi-capsule"></i> Medicines
                </a>
                <a href="add.php" class="nav-item">
                    <i class="bi bi-plus-circle"></i> Add Medicine
                </a>
                <a href="dispense.php" class="nav-item">
                    <i class="bi bi-prescription"></i> Dispense
                    <?php if($pending_count > 0): ?>
                        <span class="badge"><?php echo $pending_count; ?></span>
                    <?php endif; ?>
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
                    <div>
                        <h1><i class="bi bi-capsule"></i> Medicine Stock</h1>
                    </div>
                    <div>
                        <a href="add.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Add Medicine
                        </a>
                        <a href="dispense.php" class="btn btn-success">
                            <i class="bi bi-prescription"></i> Dispense
                        </a>
                    </div>
                </div>

                <?php if($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                                    <input type="text" name="search" class="form-control" placeholder="Search medicines..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <select name="category" class="form-select">
                                    <option value="">All Categories</option>
                                    <?php foreach($categories as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat['category']); ?>" 
                                            <?php echo ($category_filter == $cat['category']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['category']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search"></i> Filter
                                </button>
                            </div>
                            <div class="col-md-3">
                                <a href="index.php" class="btn btn-secondary w-100">
                                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Stock Table -->
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-table"></i> Medicine Inventory</span>
                            <span class="badge bg-primary"><?php echo count($medicines); ?> items</span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Name</th>
                                        <th>Category</th>
                                        <th>Quantity</th>
                                        <th>Unit Price</th>
                                        <th>Expiry Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($medicines)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4 text-muted">
                                                <i class="bi bi-inbox display-4 d-block"></i>
                                                <p class="mt-2">No medicines found</p>
                                                <a href="add.php" class="btn btn-primary btn-sm">
                                                    <i class="bi bi-plus-circle"></i> Add Medicine
                                                </a>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach($medicines as $medicine): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($medicine['name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($medicine['category']); ?></td>
                                            <td>
                                                <span class="fw-bold <?php echo $medicine['quantity'] <= 10 ? 'text-danger' : ''; ?>">
                                                    <?php echo $medicine['quantity']; ?>
                                                </span>
                                            </td>
                                            <td>KSh <?php echo number_format($medicine['unit_price'], 2); ?></td>
                                            <td>
                                                <?php 
                                                $expiry = strtotime($medicine['expiry_date']);
                                                $now = time();
                                                $diff = $expiry - $now;
                                                $days = floor($diff / (60 * 60 * 24));
                                                ?>
                                                <span class="<?php echo $days < 30 ? 'text-danger' : ''; ?>">
                                                    <?php echo date('M d, Y', strtotime($medicine['expiry_date'])); ?>
                                                    <?php if($days < 30 && $days > 0): ?>
                                                        <small class="text-danger">(<?php echo $days; ?> days)</small>
                                                    <?php endif; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if($medicine['quantity'] <= 0): ?>
                                                    <span class="badge-status out-of-stock">Out of Stock</span>
                                                <?php elseif($medicine['quantity'] <= 10): ?>
                                                    <span class="badge-status low-stock">Low Stock</span>
                                                <?php else: ?>
                                                    <span class="badge-status in-stock">In Stock</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="edit.php?id=<?php echo $medicine['id']; ?>" class="btn btn-warning" title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <a href="dispense.php?id=<?php echo $medicine['id']; ?>" class="btn btn-success" title="Dispense">
                                                        <i class="bi bi-prescription"></i>
                                                    </a>
                                                    <a href="?delete=<?php echo $medicine['id']; ?>" class="btn btn-danger" title="Delete" 
                                                       onclick="return confirm('Are you sure you want to delete this medicine?')">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                </div>
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
</body>
</html>