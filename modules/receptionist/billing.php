<?php
// modules/reception/billing.php - Billing Management
require_once '../../core/auth.php';
requireLogin();
require_once '../../config/database.php';

$user = getCurrentUser($pdo);

// ============================================
// ADD THIS BLOCK - GET USER DATA FOR PROFILE PIC
// ============================================
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);
// ============================================

// Get filter
$status = $_GET['status'] ?? '';


$sql = "
    SELECT i.*, p.fullname as patient_name 
    FROM invoices i 
    JOIN patients p ON i.patient_id = p.id 
    WHERE 1=1
";
$params = [];

if (!empty($status)) {
    $sql .= " AND LOWER(i.payment_status) = ?";
    $params[] = strtolower($status);
}

$sql .= " ORDER BY i.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll();

// Get stats
$stmt = $pdo->query("SELECT COUNT(*) FROM invoices WHERE LOWER(payment_status) = 'paid'");
$paid = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM invoices WHERE LOWER(payment_status) = 'pending'");
$pending = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT SUM(amount) FROM invoices WHERE LOWER(payment_status) = 'paid'");
$total_revenue = $stmt->fetchColumn() ?? 0;

$stmt = $pdo->query("SELECT COUNT(*) FROM invoices WHERE DATE(created_at) = CURDATE()");
$today_invoices = $stmt->fetchColumn();

$page_title = 'Billing Management';
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
        .sidebar .avatar img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; }
        .stat-card { transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
        .stat-number { font-size: 2.2rem; font-weight: 700; line-height: 1.2; }
        .stat-change { font-size: 0.85rem; opacity: 0.85; margin-top: 8px; }
        .badge-status { padding: 4px 14px; border-radius: 20px; font-weight: 600; font-size: 12px; display: inline-block; }
        .badge-status.paid { background: #d4edda; color: #155724; }
        .badge-status.pending { background: #fff3cd; color: #856404; }
        .btn-actions { padding: 2px 8px; font-size: 12px; }
        .table th { font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
        .filter-bar { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
        .mini-stat { text-align: center; padding: 8px; border-radius: 8px; background: #f8fafc; }
        .mini-stat .number { font-size: 20px; font-weight: 700; }
        .mini-stat .label { font-size: 11px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="container-fluid p-0">
        <div class="row g-0">
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
                <a href="patients.php" class="nav-item">
                    <i class="bi bi-people"></i> Patients
                </a>
                <a href="appointments.php" class="nav-item">
                    <i class="bi bi-calendar-event"></i> Appointments
                </a>
                <a href="billing.php" class="nav-item active">
                    <i class="bi bi-receipt"></i> Billing
                    <span class="badge"><?php echo $pending; ?></span>
                </a>
                <a href="profile.php" class="nav-item">
                    <i class="bi bi-person-circle"></i> Profile
                </a>
                <div class="nav-section">Account</div>
                <a href="../../logout.php" class="nav-item">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </nav>

            <main class="col-md-10 main-content">
                <div class="page-header">
                    <div>
                        <h1><i class="bi bi-receipt"></i> <?php echo $page_title; ?></h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item active"><?php echo $page_title; ?></li>
                            </ol>
                        </nav>
                    </div>
                    <div>
                        <a href="billing_create.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Create Invoice
                        </a>
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

                <!-- Mini Stats -->
                <div class="row g-3 mb-4">
                    <div class="col-4 col-md-3">
                        <div class="mini-stat">
                            <div class="number" style="color:#155724;"><?php echo $paid; ?></div>
                            <div class="label">Paid Invoices</div>
                        </div>
                    </div>
                    <div class="col-4 col-md-3">
                        <div class="mini-stat">
                            <div class="number" style="color:#856404;"><?php echo $pending; ?></div>
                            <div class="label">Pending Invoices</div>
                        </div>
                    </div>
                    <div class="col-4 col-md-3">
                        <div class="mini-stat">
                            <div class="number" style="color:#1a56db;">KES <?php echo number_format($total_revenue, 0); ?></div>
                            <div class="label">Total Revenue</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-3">
                        <form method="GET" class="filter-bar">
                            <select name="status" class="form-select form-select-sm" style="width:140px;">
                                <option value="">All Status</option>
                                <option value="paid" <?php echo $status == 'paid' ? 'selected' : ''; ?>>Paid</option>
                                <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            </select>
                            <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                            <a href="billing.php" class="btn btn-sm btn-secondary">Reset</a>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-list"></i> All Invoices
                        <span class="badge bg-primary float-end"><?php echo count($invoices); ?> invoices</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="dataTable">
                                <thead>
                                    <tr>
                                        <th>Invoice #</th>
                                        <th>Patient</th>
                                        <th>Service</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($invoices)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4 text-muted">
                                                <i class="bi bi-receipt display-4 d-block"></i>
                                                <p class="mt-2">No invoices found</p>
                                                <a href="billing_create.php" class="btn btn-primary btn-sm">
                                                    <i class="bi bi-plus-circle"></i> Create Invoice
                                                </a>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach($invoices as $invoice): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($invoice['invoice_number']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($invoice['patient_name']); ?></td>
                                            <td><?php echo htmlspecialchars($invoice['service_name']); ?></td>
                                            <td><strong>KES <?php echo number_format($invoice['amount'], 2); ?></strong></td>
                                            <td>
                                                <span class="badge-status <?php echo strtolower($invoice['payment_status']); ?>">
                                                    <?php echo ucfirst($invoice['payment_status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($invoice['created_at'])); ?></td>
                                            <td>
                                                <a href="billing_view.php?id=<?php echo $invoice['id']; ?>" class="btn btn-sm btn-outline-info btn-actions" title="View">
                                                    <i class="bi bi-eye"></i>
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