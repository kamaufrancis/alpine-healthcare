<?php
require_once '../../core/auth.php';
requireAdmin();
require_once '../../config/database.php';

$user = getCurrentUser($pdo);
$message = '';
$error = '';

// Handle log clearing
if(isset($_GET['clear'])) {
    try {
        $stmt = $pdo->prepare("TRUNCATE TABLE activity_logs");
        $stmt->execute();
        $message = "Activity logs cleared successfully!";
    } catch(PDOException $e) {
        $error = "Error clearing logs: " . $e->getMessage();
    }
}

$current_user = $_SESSION['user_name'] ?? 'Administrator';
$current_user_id = $_SESSION['user_id'] ?? 0;

$stmt = $pdo->prepare("SELECT photo FROM users WHERE id = ?");
$stmt->execute([$current_user_id]);
$user_photo = $stmt->fetchColumn();

// Get logs with filters
$filter_user = $_GET['user'] ?? '';
$filter_action = $_GET['action'] ?? '';
$filter_date = $_GET['date'] ?? '';

$query = "SELECT * FROM activity_logs WHERE 1=1";
$params = [];

if($filter_user) {
    $query .= " AND user_id = ?";
    $params[] = $filter_user;
}

if($filter_action) {
    $query .= " AND action LIKE ?";
    $params[] = "%$filter_action%";
}

if($filter_date) {
    $query .= " AND DATE(created_at) = ?";
    $params[] = $filter_date;
}

$query .= " ORDER BY created_at DESC LIMIT 100";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get users for filter
$stmt = $pdo->query("SELECT id, fullname FROM users ORDER BY fullname ASC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stmt = $pdo->query("SELECT COUNT(*) as total FROM activity_logs");
$total_logs = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM activity_logs WHERE DATE(created_at) = CURDATE()");
$today_logs = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - Alpine Healthcare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
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
        .sidebar::-webkit-scrollbar-thumb { background: #e74c3c; border-radius: 2px; }
        
        .sidebar .brand {
            padding: 20px 20px 15px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            text-align: center;
        }
        
        .sidebar .brand h4 { color: white; font-weight: 700; margin: 0; }
        .sidebar .brand h4 span { color: #e74c3c; }
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
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 18px;
            flex-shrink: 0;
        }
        .sidebar .user-info .avatar img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; }
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
        
        .sidebar .nav-item:hover { background: rgba(255,255,255,0.05); color: white; border-left-color: #e74c3c; }
        .sidebar .nav-item.active { background: rgba(231, 76, 60, 0.1); color: #e74c3c; border-left-color: #e74c3c; }
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
        .page-header h1 i { color: #e74c3c; margin-right: 10px; }
        
        .stat-card {
            border: none;
            border-radius: 12px;
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
        .stat-card .card-body { padding: 20px; }
        .stat-card .stat-number { font-size: 28px; font-weight: 700; margin: 0; }
        .stat-card .stat-label { font-size: 13px; font-weight: 500; opacity: 0.8; margin-bottom: 4px; }
        
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
        
        .table th {
            font-weight: 600;
            color: #495057;
            border-top: none;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .table td { vertical-align: middle; font-size: 14px; }
        
        .btn-primary {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            border: none;
            padding: 8px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(231, 76, 60, 0.3);
        }
        
        @media (max-width: 768px) {
            .sidebar { min-height: auto; height: auto; position: relative; }
            .main-content { padding: 15px; }
            .page-header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .stat-card .stat-number { font-size: 22px; }
        }

      
        @media print {
            /* Hide sidebar, header, stats, filters, buttons */
            .sidebar,
            .page-header,
            .stat-card,
            .card.mb-4, /* Filter card */
            .btn,
            .alert,
            .breadcrumb,
            .no-print {
                display: none !important;
            }
            
            /* Show only the logs table card */
            .card:not(.stat-card):not(.mb-4) {
                display: block !important;
                border: none !important;
                box-shadow: none !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            
            .card-header {
                display: none !important;
            }
            
            .table {
                width: 100% !important;
                font-size: 12px !important;
            }
            
            .table th {
                background: #f8f9fa !important;
                color: #000 !important;
            }
            
            .table td {
                color: #000 !important;
            }
            
            body {
                background: white !important;
                padding: 20px !important;
            }
            
            .main-content {
                padding: 0 !important;
                background: white !important;
                min-height: auto !important;
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
                    <small>Admin Panel</small>
                </div>
                
                <div class="user-info d-flex align-items-center gap-3">
                    <div class="avatar">
                        <?php if(!empty($user_photo)): ?>
                            <img src="<?php echo htmlspecialchars($user_photo); ?>" alt="Profile">
                        <?php else: ?>
                            <?php echo strtoupper(substr($current_user, 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="user-name"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Administrator'); ?></div>
                        <div class="user-role"><i class="bi bi-shield-check"></i> <?php echo ucfirst($_SESSION['role'] ?? 'admin'); ?></div>
                    </div>
                </div>
                <div class="nav-section">Main Menu</div>
                <?php if($_SESSION['role'] === 'admin'): ?>
                <a href="../../dashboard.php" class="nav-item">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <a href="users.php" class="nav-item">
                    <i class="bi bi-people"></i> Users
                </a>
                <a href="patients.php" class="nav-item">
                    <i class="bi bi-person"></i> Patients
                </a>
                <a href="appointments.php" class="nav-item">
                    <i class="bi bi-calendar-event"></i> Appointments
                </a>
                <a href="billing.php" class="nav-item">
                    <i class="bi bi-receipt"></i> Billing
                </a>
                <?php endif; ?>
                <a href="pharmacy.php" class="nav-item">
                    <i class="bi bi-capsule"></i> Pharmacy
                </a>
                <div class="nav-section">System</div>
                <a href="settings.php" class="nav-item">
                    <i class="bi bi-gear"></i> Settings
                </a>
                <a href="logs.php" class="nav-item active">
                    <i class="bi bi-activity"></i> Activity Logs
                    <span class="badge bg-danger ms-auto"><?php echo $total_logs; ?></span>
                </a>
                <div class="nav-section">Account</div>
                <a href="profile.php" class="nav-item">
                    <i class="bi bi-person-circle"></i> Profile
                </a>
                <a href="../../logout.php" class="nav-item">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </nav>

            <!-- Main Content -->
            <main class="col-md-10 main-content">
                <div class="page-header">
                    <div>
                        <h1><i class="bi bi-activity"></i> Activity Logs</h1>
                    </div>
                    <div>
                        <a href="?clear=1" class="btn btn-danger" onclick="return confirm('Clear all activity logs? This action cannot be undone.')">
                            <i class="bi bi-trash"></i> Clear Logs
                        </a>
                        <button onclick="window.print()" class="btn btn-secondary">
                            <i class="bi bi-printer"></i> Print
                        </button>
                    </div>
                </div>

                <?php if($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Statistics -->
                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <div class="card stat-card bg-primary text-white">
                            <div class="card-body">
                                <div class="stat-label">Total Logs</div>
                                <h2 class="stat-number"><?php echo $total_logs; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stat-card bg-success text-white">
                            <div class="card-body">
                                <div class="stat-label">Today's Logs</div>
                                <h2 class="stat-number"><?php echo $today_logs; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stat-card bg-info text-white">
                            <div class="card-body">
                                <div class="stat-label">Unique Users</div>
                                <h2 class="stat-number">
                                    <?php 
                                    $stmt = $pdo->query("SELECT COUNT(DISTINCT user_id) as total FROM activity_logs");
                                    echo $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                                    ?>
                                </h2>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <select name="user" class="form-select">
                                    <option value="">All Users</option>
                                    <?php foreach($users as $u): ?>
                                        <option value="<?php echo $u['id']; ?>" <?php echo ($filter_user == $u['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($u['fullname']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <input type="text" name="action" class="form-control" placeholder="Search action..." value="<?php echo htmlspecialchars($filter_action); ?>">
                            </div>
                            <div class="col-md-3">
                                <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($filter_date); ?>">
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search"></i> Filter
                                </button>
                                <a href="logs.php" class="btn btn-secondary w-100 mt-1">
                                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Logs Table -->
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-table"></i> Activity Logs</span>
                            <span class="badge bg-primary"><?php echo count($logs); ?> entries</span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>User</th>
                                        <th>Action</th>
                                        <th>Details</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($logs)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-4 text-muted">
                                                <i class="bi bi-inbox display-4 d-block"></i>
                                                <p class="mt-2">No activity logs found</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach($logs as $log): ?>
                                        <tr>
                                            <td>
                                                <?php 
                                                $log_user = '';
                                                if($log['user_id']) {
                                                    $stmt = $pdo->prepare("SELECT fullname FROM users WHERE id = ?");
                                                    $stmt->execute([$log['user_id']]);
                                                    $u = $stmt->fetch(PDO::FETCH_ASSOC);
                                                    if($u) {
                                                        $log_user = $u['fullname'];
                                                    }
                                                }
                                                echo htmlspecialchars($log_user ?: 'System');
                                                ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($log['action']); ?></td>
                                            <td><?php echo htmlspecialchars($log['details'] ?? 'N/A'); ?></td>
                                            <td>
                                                <small><?php echo date('M d, Y', strtotime($log['created_at'])); ?></small>
                                                <br>
                                                <small class="text-muted"><?php echo date('h:i A', strtotime($log['created_at'])); ?></small>
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