<?php
// modules/admin/patients.php - Patient Management
require_once '../../core/auth.php';
requireAdmin();
require_once '../../config/database.php';

// Get all patients
$stmt = $pdo->query("SELECT * FROM patients ORDER BY created_at DESC");
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

$current_user = $_SESSION['user_name'] ?? 'Administrator';
$current_user_id = $_SESSION['user_id'] ?? 0;

$stmt = $pdo->prepare("SELECT photo FROM users WHERE id = ?");
$stmt->execute([$current_user_id]);
$user_photo = $stmt->fetchColumn();

$page_title = 'Patient Management';
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
    <style>
        * { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; }
        body { background: #f4f6fb; }
        .stat-card { transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
        .stat-number { font-size: 2.2rem; font-weight: 700; line-height: 1.2; }
        .stat-change { font-size: 0.85rem; opacity: 0.85; margin-top: 8px; }
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
        .main-content { padding: 20px 30px; background: #f4f6fb; min-height: 100vh; }
        .page-header {
            background: #fff; padding: 20px 25px; border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.04); margin-bottom: 25px;
            display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;
        }
        .page-header h1 { margin: 0; font-size: 1.5rem; font-weight: 700; }
        .page-header .breadcrumb { margin: 0; padding: 0; background: transparent; }
        .card { border: 1px solid #e9eef4; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.04); background: #fff; }
        .card-header { background: transparent; border-bottom: 1px solid #e9eef4; padding: 1rem 1.1rem; font-weight: 600; display: flex; justify-content: space-between; align-items: center; }
        .card-body { padding: 1rem 1.1rem; }
        .table th { font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
        .gender-badge { padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; }
        .gender-badge.Male { background: #dbeafe; color: #1d4ed8; }
        .gender-badge.Female { background: #fce7f3; color: #be185d; }
        .gender-badge.Other { background: #e0e7ff; color: #4338ca; }
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
                        <div class="user-name"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Administrator'); ?></div>
                        <div class="user-role"><i class="bi bi-shield-check"></i> <?php echo ucfirst($_SESSION['role'] ?? 'admin'); ?></div>
                    </div>
                </div>
                <div class="nav-section">Main Menu</div>
                <a href="../../dashboard.php" class="nav-item">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <a href="users.php" class="nav-item">
                    <i class="bi bi-people"></i> Users
                </a>
                <a href="patients.php" class="nav-item active">
                    <i class="bi bi-person"></i> Patients
                    <span class="badge"><?php echo count($patients); ?></span>
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
                        <h1><i class="bi bi-person"></i> <?php echo $page_title; ?></h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item active"><?php echo $page_title; ?></li>
                            </ol>
                        </nav>
                    </div>
                    
                </div>

                <?php if(isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="bi bi-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if(isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="bi bi-exclamation-triangle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <span><i class="bi bi-list"></i> All Patients</span>
                        <span class="text-muted">Total: <?php echo count($patients); ?></span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="dataTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Full Name</th>
                                        <th>Gender</th>
                                        <th>Phone</th>
                                        <th>Address</th>
                                        <th>Registered</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($patients)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4 text-muted">
                                                <i class="bi bi-people fs-2 d-block mb-2"></i>
                                                No patients registered yet
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach($patients as $patient): ?>
                                        <tr>
                                            <td>#<?php echo $patient['id']; ?></td>
                                            <td><strong><?php echo htmlspecialchars($patient['fullname']); ?></strong></td>
                                            <td>
                                                <span class="gender-badge <?php echo htmlspecialchars($patient['gender'] ?? 'Other'); ?>">
                                                    <?php echo htmlspecialchars($patient['gender'] ?? 'N/A'); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($patient['phone'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars(substr($patient['address'] ?? '', 0, 30)); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($patient['created_at'])); ?></td>
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