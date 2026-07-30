<?php
// modules/admin/users.php - User Management
require_once __DIR__ . '/../../core/auth.php';
requireAdmin();
require_once __DIR__ . '/../../config/database.php';

// Get current user
$current_user = $_SESSION['user_name'] ?? 'Administrator';
$current_user_id = $_SESSION['user_id'] ?? 0;

$stmt = $pdo->prepare("SELECT photo FROM users WHERE id = ?");
$stmt->execute([$current_user_id]);
$user_photo = $stmt->fetchColumn();

$message = '';
$error = '';

// ============================================
// HANDLE USER DELETION
// ============================================
if(isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    
    // Prevent admin from deleting themselves
    if($delete_id == $current_user_id) {
        $error = 'You cannot delete your own account!';
    } else {
        try {
            // Check if user exists
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$delete_id]);
            $user_to_delete = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if($user_to_delete) {
                // If user is a doctor, check if they have appointments
                if($user_to_delete['role'] == 'doctor') {
                    // Find the doctor record linked to this user
                    $stmt = $pdo->prepare("
                        SELECT d.id FROM doctors d 
                        JOIN users u ON u.email = d.email 
                        WHERE u.id = ?
                    ");
                    $stmt->execute([$delete_id]);
                    $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if($doctor) {
                        // Check if doctor has appointments
                        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ?");
                        $stmt->execute([$doctor['id']]);
                        $appointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                        
                        if($appointments > 0) {
                            $error = 'Cannot delete this doctor. They have ' . $appointments . ' appointment(s).';
                        } else {
                            // Start transaction
                            $pdo->beginTransaction();
                            
                            // Delete doctor record
                            $stmt = $pdo->prepare("DELETE FROM doctors WHERE id = ?");
                            $stmt->execute([$doctor['id']]);
                            
                            // Delete user
                            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                            $stmt->execute([$delete_id]);
                            
                            $pdo->commit();
                            $message = 'User and doctor record deleted successfully!';
                        }
                    } else {
                        // No doctor record found, just delete user
                        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                        $stmt->execute([$delete_id]);
                        $message = 'User deleted successfully!';
                    }
                } else {
                    // Delete regular user
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$delete_id]);
                    $message = 'User deleted successfully!';
                }
            }
        } catch(PDOException $e) {
            $error = 'Error deleting user: ' . $e->getMessage();
            // Rollback if transaction was started
            if(isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }
    }
}

// ============================================
// GET FILTER PARAMETERS
// ============================================
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';

// ============================================
// BUILD USER QUERY
// ============================================
$query = "SELECT * FROM users WHERE 1=1";
$params = [];

if($search) {
    $query .= " AND (fullname LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if($role_filter) {
    $query .= " AND role = ?";
    $params[] = $role_filter;
}

$query .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// GET STATISTICS
// ============================================
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
$total_users = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'admin'");
$total_admins = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'doctor'");
$total_doctors = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'receptionist'");
$total_receptionists = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'pharmacist'");
$total_pharmacists = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE DATE(created_at) = CURDATE()");
$today_users = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$page_title = 'User Management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Alpine Healthcare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; }
        body { background: #f4f6fb; }
        
        /* Sidebar */
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, #1a2332 0%, #111827 100%);
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0,0,0,0.08);
        }
        .sidebar .brand { padding: 20px 20px 15px; border-bottom: 1px solid rgba(255,255,255,0.08); text-align: center; }
        .sidebar .brand h4 { color: #fff; font-weight: 700; margin: 0; }
        .sidebar .brand h4 span { color: #e74c3c; }
        .sidebar .brand small { color: rgba(255,255,255,0.65); font-size: 11px; letter-spacing: 1px; text-transform: uppercase; }
        .sidebar .user-info { padding: 15px 20px; border-bottom: 1px solid rgba(255,255,255,0.08); }
        .sidebar .avatar {
            width: 42px; height: 42px; border-radius: 50%;
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            display: flex !important; align-items: center !important; justify-content: center !important;
            color: #fff; font-weight: 700; font-size: 18px; flex-shrink: 0;
        }  

        .sidebar .avatar img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; }  

        .sidebar .user-name { color: #fff; font-weight: 600; font-size: 14px; }
        .sidebar .user-role { color: rgba(255,255,255,0.6); font-size: 12px; }
        .sidebar .nav-section { padding: 15px 20px 5px; color: rgba(255,255,255,0.3); font-size: 10px; text-transform: uppercase; letter-spacing: 1px; font-weight: 600; }
        .sidebar .nav-item {
            color: rgba(255,255,255,0.72); text-decoration: none; padding: 10px 20px;
            display: flex; align-items: center; gap: 12px;
            transition: all 0.2s ease; border-left: 3px solid transparent;
            font-size: 14px; font-weight: 500; cursor: pointer;
        }
        .sidebar .nav-item:hover { background: rgba(255,255,255,0.06); color: #fff; border-left-color: #e74c3c; }
        .sidebar .nav-item.active { background: rgba(231,76,60,0.12); color: #fff; border-left-color: #e74c3c; }
        .sidebar .nav-item .badge { margin-left: auto; background: rgba(255,255,255,0.14); color: #fff; border-radius: 999px; padding: 2px 8px; font-size: 11px; }
        .sidebar .nav-item i { width: 20px; text-align: center; }
        
        /* Main Content */
        .main-content { padding: 20px 30px; background: #f4f6fb; min-height: 100vh; }
        .page-header {
            background: #fff; padding: 20px 25px; border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.04); margin-bottom: 25px;
            display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;
        }
        .page-header h1 { margin: 0; font-size: 1.5rem; font-weight: 700; }
        .page-header .breadcrumb { margin: 0; padding: 0; background: transparent; }
        
        /* Stats */
        .stat-card { transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
        .stat-number { font-size: 2.2rem; font-weight: 700; line-height: 1.2; }
        .stat-change { font-size: 0.85rem; opacity: 0.85; margin-top: 8px; }
        .stat-icon { background: rgba(255,255,255,0.2); padding: 10px; border-radius: 12px; }
        
        /* Cards */
        .card { border: 1px solid #e9eef4; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.04); background: #fff; }
        .card-header { background: transparent; border-bottom: 1px solid #e9eef4; padding: 1rem 1.1rem; font-weight: 600; display: flex; justify-content: space-between; align-items: center; }
        .card-body { padding: 1rem 1.1rem; }
        
        /* Table */
        .table th { font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
        .btn-actions { padding: 2px 8px; font-size: 12px; }
        
        /* Role Badges */
        .role-badge { padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .role-badge.admin { background: #e74c3c; color: #fff; }
        .role-badge.doctor { background: #3498db; color: #fff; }
        .role-badge.receptionist { background: #2ecc71; color: #fff; }
        .role-badge.pharmacist { background: #9b59b6; color: #fff; }
        
        .user-avatar {
            width: 36px; height: 36px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-weight: 700; font-size: 14px;
        }
        .user-avatar.admin { background: linear-gradient(135deg, #e74c3c, #c0392b); }
        .user-avatar.doctor { background: linear-gradient(135deg, #3498db, #2980b9); }
        .user-avatar.receptionist { background: linear-gradient(135deg, #2ecc71, #27ae60); }
        .user-avatar.pharmacist { background: linear-gradient(135deg, #9b59b6, #8e44ad); }
        
        .status-badge { padding: 2px 10px; border-radius: 12px; font-size: 10px; font-weight: 600; }
        .status-badge.active { background: #d4edda; color: #155724; }
        .status-badge.inactive { background: #f8d7da; color: #721c24; }
        
        @media (max-width: 768px) {
            .sidebar { min-height: auto; height: auto; position: relative; }
            .main-content { padding: 15px; }
            .page-header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .stat-number { font-size: 1.5rem; }
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
                        <div class="user-name"><?php echo htmlspecialchars($current_user); ?></div>
                        <div class="user-role"><i class="bi bi-shield-check"></i> Admin</div>
                    </div>
                </div>
                <div class="nav-section">Main Menu</div>
                <a href="../../dashboard.php" class="nav-item">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <a href="users.php" class="nav-item active">
                    <i class="bi bi-people"></i> Users
                    <span class="badge"><?php echo $total_users; ?></span>
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
                <a href="pharmacy.php" class="nav-item">
                    <i class="bi bi-capsule"></i> Pharmacy
                </a>

                <div class="nav-section">System</div>
                <a href="settings.php" class="nav-item">
                    <i class="bi bi-gear"></i> Settings
                </a>
                <a href="logs.php" class="nav-item">
                    <i class="bi bi-activity"></i> Activity Logs
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
                        <h1><i class="bi bi-people"></i> <?php echo $page_title; ?></h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item active"><?php echo $page_title; ?></li>
                            </ol>
                        </nav>
                    </div>
                    <div>
                        <a href="add_user.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Add User
                        </a>
                    </div>
                </div>

                <?php if($message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="bi bi-check-circle"></i> <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Statistics -->
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="card stat-card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stat-label">Total Users</div>
                                        <h2 class="stat-number"><?php echo $total_users; ?></h2>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="bi bi-people fs-4"></i>
                                    </div>
                                </div>
                                <div class="stat-change">
                                    <i class="bi bi-arrow-up"></i> <?php echo $today_users; ?> new today
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card stat-card bg-danger text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stat-label">Administrators</div>
                                        <h2 class="stat-number"><?php echo $total_admins; ?></h2>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="bi bi-shield-lock fs-4"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card stat-card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stat-label">Doctors</div>
                                        <h2 class="stat-number"><?php echo $total_doctors; ?></h2>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="bi bi-person-badge fs-4"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card stat-card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stat-label">Support Staff</div>
                                        <h2 class="stat-number"><?php echo $total_receptionists + $total_pharmacists; ?></h2>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="bi bi-headset fs-4"></i>
                                    </div>
                                </div>
                                <div class="stat-change">
                                    <?php echo $total_receptionists; ?> Receptionists, <?php echo $total_pharmacists; ?> Pharmacists
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Search & Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-5">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                                    <input type="text" name="search" class="form-control" placeholder="Search by name or email..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <select name="role" class="form-select">
                                    <option value="">All Roles</option>
                                    <option value="admin" <?php echo $role_filter == 'admin' ? 'selected' : ''; ?>>Administrator</option>
                                    <option value="doctor" <?php echo $role_filter == 'doctor' ? 'selected' : ''; ?>>Doctor</option>
                                    <option value="receptionist" <?php echo $role_filter == 'receptionist' ? 'selected' : ''; ?>>Receptionist</option>
                                    <option value="pharmacist" <?php echo $role_filter == 'pharmacist' ? 'selected' : ''; ?>>Pharmacist</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-search"></i> Filter
                                </button>
                                <a href="users.php" class="btn btn-secondary">
                                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Users Table -->
                <div class="card">
                    <div class="card-header">
                        <span><i class="bi bi-table"></i> User List</span>
                        <span class="badge bg-primary"><?php echo count($users); ?> users</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="dataTable">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($users)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted">
                                                <i class="bi bi-people fs-2 d-block mb-2"></i>
                                                No users found
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach($users as $user_row): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="user-avatar <?php echo $user_row['role']; ?>">
                                                        <?php echo strtoupper(substr($user_row['fullname'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($user_row['fullname']); ?></strong>
                                                        <?php if($user_row['id'] == $current_user_id): ?>
                                                            <span class="badge bg-primary ms-1">You</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <a href="mailto:<?php echo htmlspecialchars($user_row['email']); ?>">
                                                    <?php echo htmlspecialchars($user_row['email']); ?>
                                                </a>
                                            </td>
                                            <td>
                                                <span class="role-badge <?php echo $user_row['role']; ?>">
                                                    <?php echo ucfirst($user_row['role']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="status-badge active">Active</span>
                                            </td>
                                            <td>
                                                <small><?php echo date('M d, Y', strtotime($user_row['created_at'])); ?></small>
                                                <br>
                                                <small class="text-muted"><?php echo date('h:i A', strtotime($user_row['created_at'])); ?></small>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="users_edit.php?id=<?php echo $user_row['id']; ?>" class="btn btn-outline-primary btn-actions" title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <?php if($user_row['id'] != $current_user_id): ?>
                                                        <a href="?delete=<?php echo $user_row['id']; ?>" class="btn btn-outline-danger btn-actions" title="Delete" 
                                                           onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.')">
                                                            <i class="bi bi-trash"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <button class="btn btn-outline-secondary btn-actions" disabled title="Cannot delete your own account">
                                                            <i class="bi bi-lock"></i>
                                                        </button>
                                                    <?php endif; ?>
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
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
</body>
</html>