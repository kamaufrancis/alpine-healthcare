<?php
// modules/reception/patients.php - Patient Management
require_once '../../core/auth.php';
requireLogin();
require_once '../../config/database.php';

$user = getCurrentUser($pdo);

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);

// Get all patients
$stmt = $pdo->query("SELECT * FROM patients ORDER BY created_at DESC");
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stmt = $pdo->query("SELECT COUNT(*) as total FROM patients");
$total_patients = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM patients WHERE DATE(created_at) = CURDATE()");
$today_patients = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$page_title = 'Patient Management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Alpine Healthcare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        * { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; }
        body { background: #f4f6fb; }
        .sidebar .avatar img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover;
        }
        .stat-card { transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
        .stat-number { font-size: 2.2rem; font-weight: 700; line-height: 1.2; }
        .stat-change { font-size: 0.85rem; opacity: 0.85; margin-top: 8px; }
        .gender-badge { padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; }
        .gender-badge.Male { background: #dbeafe; color: #1d4ed8; }
        .gender-badge.Female { background: #fce7f3; color: #be185d; }
        .gender-badge.Other { background: #e0e7ff; color: #4338ca; }
        .btn-actions { padding: 2px 8px; font-size: 12px; }
        .table th { font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
        .badge-status { padding: 4px 14px; border-radius: 20px; font-weight: 600; font-size: 12px; display: inline-block; }
        .badge-status.scheduled { background: #cce5ff; color: #004085; }
        .badge-status.completed { background: #d4edda; color: #155724; }
        .badge-status.cancelled { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="container-fluid p-0">
        <div class="row g-0">
            <!-- Sidebar -->
            <nav class="col-md-2 sidebar">
                <div class="brand">
                    <h4>🏔️ <span>Alpine</span></h4>
                    <small>Reception Panel</small>
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
                        <div class="user-role"><i class="bi bi-shield-check"></i> <?php echo ucfirst($user_data['role'] ?? 'receptionist'); ?></div>
                    </div>
                </div>
                
                <div class="nav-section">Main Menu</div>
                <a href="rec_dashboard.php" class="nav-item">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <a href="patients.php" class="nav-item active">
                    <i class="bi bi-people"></i> Patients
                    <span class="badge"><?php echo $total_patients; ?></span>
                </a>
                <a href="appointments.php" class="nav-item">
                    <i class="bi bi-calendar-event"></i> Appointments
                </a>
                <a href="billing.php" class="nav-item">
                    <i class="bi bi-receipt"></i> Billing
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
                        <h1><i class="bi bi-people"></i> <?php echo $page_title; ?></h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item active"><?php echo $page_title; ?></li>
                            </ol>
                        </nav>
                    </div>
                    <div>
                        <a href="patients_add.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Register Patient
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
                                        <div class="stat-label">Total Patients</div>
                                        <h2 class="stat-number"><?php echo $total_patients; ?></h2>
                                    </div>
                                    <div class="stat-icon bg-white bg-opacity-25">
                                        <i class="bi bi-people"></i>
                                    </div>
                                </div>
                                <div class="stat-change">
                                    <i class="bi bi-arrow-up"></i> Registered patients
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card stat-card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stat-label">Today's Registrations</div>
                                        <h2 class="stat-number"><?php echo $today_patients; ?></h2>
                                    </div>
                                    <div class="stat-icon bg-white bg-opacity-25">
                                        <i class="bi bi-person-plus"></i>
                                    </div>
                                </div>
                                <div class="stat-change">
                                    <i class="bi bi-calendar"></i> Registered today
                                </div>
                            </div>
                        </div>
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
                        <i class="bi bi-list"></i> All Patients
                        <span class="badge bg-primary float-end"><?php echo count($patients); ?> patients</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="dataTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Full Name</th>
                                        <th>Gender</th>
                                        <th>DOB</th>
                                        <th>Phone</th>
                                        <th>Email</th>
                                        <th>Address</th>
                                        <th>Registered</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($patients)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4 text-muted">
                                                <i class="bi bi-people display-4 d-block"></i>
                                                <p class="mt-2">No patients registered yet</p>
                                                <a href="patients_add.php" class="btn btn-primary btn-sm">
                                                    <i class="bi bi-person-plus"></i> Register Patient
                                                </a>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach($patients as $patient): ?>
                                        <tr>
                                            <td><?php echo $patient['id']; ?></td>
                                            <td><strong><?php echo htmlspecialchars($patient['fullname']); ?></strong></td>
                                            <td>
                                                <span class="gender-badge <?php echo htmlspecialchars($patient['gender'] ?? 'Other'); ?>">
                                                    <?php echo htmlspecialchars($patient['gender'] ?? 'N/A'); ?>
                                                </span>
                                            </td>
                                            <td><?php echo !empty($patient['dob']) ? date('M d, Y', strtotime($patient['dob'])) : 'N/A'; ?></td>  
                                            <td><?php echo htmlspecialchars($patient['phone'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($patient['email'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars(substr($patient['address'] ?? '', 0, 30)); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($patient['created_at'])); ?></td>
                                            <td>
                                                <a href="patients_view.php?id=<?php echo $patient['id']; ?>" class="btn btn-sm btn-outline-info btn-actions" title="View">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="patients_edit.php?id=<?php echo $patient['id']; ?>" class="btn btn-sm btn-outline-primary btn-actions" title="Edit">
                                                    <i class="bi bi-pencil"></i>
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
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
</body>
</html>