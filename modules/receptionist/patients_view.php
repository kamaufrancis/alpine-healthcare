<?php
// modules/reception/patients_view.php - View Patient
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

$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
$stmt->execute([$id]);
$patient = $stmt->fetch();

if (!$patient) {
    $_SESSION['error'] = 'Patient not found.';
    header('Location: patients.php');
    exit;
}

// Get patient appointments
$stmt = $pdo->prepare("
    SELECT a.*, d.fullname as doctor_name 
    FROM appointments a 
    LEFT JOIN users d ON a.doctor_id = d.id 
    WHERE a.patient_id = ? 
    ORDER BY a.appointment_date DESC 
    LIMIT 10
");
$stmt->execute([$id]);
$appointments = $stmt->fetchAll();

// Get patient invoices
$stmt = $pdo->prepare("
    SELECT * FROM invoices 
    WHERE patient_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute([$id]);
$invoices = $stmt->fetchAll();

$page_title = 'Patient Details';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Alpine Healthcare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        * { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; }
        body { background: #f4f6fb; }
        .sidebar .avatar img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; }
        .info-label { color: #6b7280; font-size: 13px; font-weight: 600; }
        .info-value { font-size: 16px; font-weight: 500; }
        .detail-card { background: #f8fafc; border-radius: 10px; padding: 15px; border: 1px solid #e9eef4; }
        .gender-badge { padding: 4px 16px; border-radius: 20px; font-weight: 600; display: inline-block; }
        .gender-badge.Male { background: #dbeafe; color: #1d4ed8; }
        .gender-badge.Female { background: #fce7f3; color: #be185d; }
        .gender-badge.Other { background: #e0e7ff; color: #4338ca; }
        .badge-status { padding: 4px 14px; border-radius: 20px; font-weight: 600; font-size: 12px; display: inline-block; }
        .badge-status.scheduled { background: #cce5ff; color: #004085; }
        .badge-status.completed { background: #d4edda; color: #155724; }
        .badge-status.cancelled { background: #f8d7da; color: #721c24; }
        .badge-status.paid { background: #d4edda; color: #155724; }
        .badge-status.pending { background: #fff3cd; color: #856404; }
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
                <a href="patients.php" class="nav-item active">
                    <i class="bi bi-people"></i> Patients
                </a>
                <a href="appointments.php" class="nav-item">
                    <i class="bi bi-calendar-event"></i> Appointments
                </a>
                <a href="billing.php" class="nav-item">
                    <i class="bi bi-receipt"></i> Billing
                </a>
                <a href="profile.php" class="nav-item">
                    <i class="bi bi-person"></i> Profile
                </a>
                <div class="nav-section">Account</div>
                <a href="../../logout.php" class="nav-item">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </nav>

            <main class="col-md-10 main-content">
                <div class="page-header">
                    <div>
                        <h1><i class="bi bi-person-circle"></i> <?php echo $page_title; ?></h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item active"><?php echo $page_title; ?></li>
                            </ol>
                        </nav>
                    </div>
                    <div>
                        <a href="patients_edit.php?id=<?php echo $patient['id']; ?>" class="btn btn-primary">
                            <i class="bi bi-pencil"></i> Edit
                        </a>
                        <a href="patients.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Back
                        </a>
                    </div>
                </div>

                <div class="row g-4">
                    <!-- Patient Info -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-person"></i> Patient Information
                            </div>
                            <div class="card-body">
                                <div class="text-center mb-3">
                                    <div class="avatar" style="width:80px;height:80px;font-size:32px;margin:0 auto;background:linear-gradient(135deg,#2ecc71,#27ae60);">
                                        <?php echo strtoupper(substr($patient['fullname'] ?? 'P', 0, 1)); ?>
                                    </div>
                                    <h5 class="mt-2"><?php echo htmlspecialchars($patient['fullname']); ?></h5>
                                    <span class="gender-badge <?php echo htmlspecialchars($patient['gender'] ?? 'Other'); ?>">
                                        <?php echo htmlspecialchars($patient['gender'] ?? 'N/A'); ?>
                                    </span>
                                </div>
                                <hr>
                                <div class="detail-card">
                                    <div class="row mb-2">
                                        <div class="col-5 info-label"><i class="bi bi-telephone"></i> Phone</div>
                                        <div class="col-7 info-value"><?php echo htmlspecialchars($patient['phone'] ?? 'N/A'); ?></div>
                                    </div>
                                    <div class="row mb-2">
                                        <div class="col-5 info-label"><i class="bi bi-envelope"></i> Email</div>
                                        <div class="col-7 info-value"><?php echo htmlspecialchars($patient['email'] ?? 'N/A'); ?></div>
                                    </div>
                                    <div class="row mb-2">
                                        <div class="col-5 info-label"><i class="bi bi-calendar"></i> DOB</div>
                                        <div class="col-7 info-value"><?php echo htmlspecialchars($patient['dob'] ?? 'N/A'); ?></div>
                                    </div>
                                    <div class="row">
                                        <div class="col-5 info-label"><i class="bi bi-geo-alt"></i> Address</div>
                                        <div class="col-7 info-value"><?php echo htmlspecialchars($patient['address'] ?? 'N/A'); ?></div>
                                    </div>
                                    <div class="row mt-2">
                                        <div class="col-5 info-label"><i class="bi bi-calendar-plus"></i> Registered</div>
                                        <div class="col-7 info-value"><?php echo date('M d, Y', strtotime($patient['created_at'])); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Appointments -->
                    <div class="col-md-8">
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="bi bi-calendar-event"></i> Recent Appointments
                                
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0">
                                        <thead>
                                            <tr>
                                                <th>Doctor</th>
                                                <th>Date & Time</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if(empty($appointments)): ?>
                                                <tr>
                                                    <td colspan="3" class="text-center text-muted py-3">
                                                        <i class="bi bi-calendar-x"></i> No appointments found
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach($appointments as $appointment): ?>
                                                <tr>
                                                    <td>Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?></td>
                                                    <td><?php echo date('M d, Y H:i', strtotime($appointment['appointment_date'])); ?></td>
                                                    <td>
                                                        <span class="badge-status <?php echo strtolower($appointment['status']); ?>">
                                                            <?php echo ucfirst($appointment['status']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Invoices -->
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-receipt"></i> Recent Invoices
                                <a href="billing_create.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-sm btn-success">
                                    <i class="bi bi-plus-circle"></i> Create Invoice
                                </a>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0">
                                        <thead>
                                            <tr>
                                                <th>Invoice #</th>
                                                <th>Service</th>
                                                <th>Amount</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if(empty($invoices)): ?>
                                                <tr>
                                                    <td colspan="4" class="text-center text-muted py-3">
                                                        <i class="bi bi-receipt"></i> No invoices found
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach($invoices as $invoice): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($invoice['invoice_number']); ?></td>
                                                    <td><?php echo htmlspecialchars($invoice['service_name']); ?></td>
                                                    <td>KES <?php echo number_format($invoice['amount'], 2); ?></td>
                                                    <td>
                                                        <span class="badge-status <?php echo strtolower($invoice['payment_status']); ?>">
                                                            <?php echo ucfirst($invoice['payment_status']); ?>
                                                        </span>
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