<?php
// modules/reception/billing_view.php - View Invoice
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
$stmt = $pdo->prepare("
    SELECT i.*, p.fullname as patient_name, p.phone, p.address 
    FROM invoices i 
    JOIN patients p ON i.patient_id = p.id 
    WHERE i.id = ?
");
$stmt->execute([$id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    $_SESSION['error'] = 'Invoice not found.';
    header('Location: billing.php');
    exit;
}

$page_title = 'Invoice Details';
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
        .badge-status { padding: 4px 14px; border-radius: 20px; font-weight: 600; font-size: 12px; display: inline-block; }
        .badge-status.paid { background: #d4edda; color: #155724; }
        .badge-status.pending { background: #fff3cd; color: #856404; }
        .receipt-box {
            background: #fff;
            border: 2px dashed #d1d5db;
            border-radius: 12px;
            padding: 30px;
            max-width: 500px;
            margin: 0 auto;
        }
        .receipt-box .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 15px; margin-bottom: 15px; }
        .receipt-box .header h3 { margin: 0; }
        .receipt-box .header h3 span { color: #2ecc71; }
        .receipt-box .details { margin: 15px 0; }
        .receipt-box .details p { margin: 5px 0; }
        .receipt-box .total { font-size: 18px; font-weight: 700; border-top: 2px solid #000; padding-top: 10px; margin-top: 10px; }
        .receipt-box .footer { text-align: center; margin-top: 15px; font-size: 12px; color: #6b7280; }
        
        /* ============================================
  
   ============================================ */
@media print {
    /* Hide everything */
    body * {
        visibility: hidden !important;
    }
    
    /* Show only the receipt box and its children */
    .receipt-box,
    .receipt-box * {
        visibility: visible !important;
    }
    
    /* Position receipt at top center */
    .receipt-box {
        position: fixed !important;
        top: 50% !important;
        left: 50% !important;
        transform: translate(-50%, -50%) !important;
        width: 100% !important;
        max-width: 500px !important;
        background: white !important;
        padding: 30px !important;
        border: none !important;
        box-shadow: none !important;
    }
    
    /* Hide the print button and back button */
    .no-print {
        display: none !important;
    }
    
    /* Hide sidebar, header, and other elements */
    .sidebar,
    .page-header,
    .card-header,
    .btn,
    .alert,
    .breadcrumb,
    .nav-section,
    .user-info {
        display: none !important;
    }
}
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
                        <h1><i class="bi bi-receipt"></i> <?php echo $page_title; ?></h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item active"><?php echo $page_title; ?></li>
                            </ol>
                        </nav>
                    </div>
                    <div>
                        <a href="billing.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Back
                        </a>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-info-circle"></i> Invoice Information
                            </div>
                            <div class="card-body">
                                <div class="detail-card">
                                    <div class="row mb-2">
                                        <div class="col-5 info-label"><i class="bi bi-receipt"></i> Invoice #</div>
                                        <div class="col-7 info-value"><strong><?php echo htmlspecialchars($invoice['invoice_number']); ?></strong></div>
                                    </div>
                                    <div class="row mb-2">
                                        <div class="col-5 info-label"><i class="bi bi-person"></i> Patient</div>
                                        <div class="col-7 info-value"><?php echo htmlspecialchars($invoice['patient_name']); ?></div>
                                    </div>
                                    <div class="row mb-2">
                                        <div class="col-5 info-label"><i class="bi bi-telephone"></i> Phone</div>
                                        <div class="col-7 info-value"><?php echo htmlspecialchars($invoice['phone'] ?? 'N/A'); ?></div>
                                    </div>
                                    <div class="row mb-2">
                                        <div class="col-5 info-label"><i class="bi bi-tag"></i> Service</div>
                                        <div class="col-7 info-value"><?php echo htmlspecialchars($invoice['service_name']); ?></div>
                                    </div>
                                    <div class="row mb-2">
                                        <div class="col-5 info-label"><i class="bi bi-currency-dollar"></i> Amount</div>
                                        <div class="col-7 info-value"><strong>KES <?php echo number_format($invoice['amount'], 2); ?></strong></div>
                                    </div>
                                    <div class="row mb-2">
                                        <div class="col-5 info-label"><i class="bi bi-check-circle"></i> Status</div>
                                        <div class="col-7 info-value">
                                            <span class="badge-status <?php echo strtolower($invoice['payment_status']); ?>">
                                                <?php echo ucfirst($invoice['payment_status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-5 info-label"><i class="bi bi-calendar"></i> Created</div>
                                        <div class="col-7 info-value"><?php echo date('M d, Y H:i', strtotime($invoice['created_at'])); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-receipt"></i> Receipt Preview
                            </div>
                            <div class="card-body">
                                <div class="receipt-box" id="receipt">
                                    <div class="header">
                                        <h3>🏔️ <span>Alpine</span> Healthcare</h3>
                                        <p>Nairobi, Kenya</p>
                                        <p>Tel: +254 700 000000</p>
                                    </div>
                                    <div class="details">
                                        <p><strong>Invoice:</strong> <?php echo htmlspecialchars($invoice['invoice_number']); ?></p>
                                        <p><strong>Patient:</strong> <?php echo htmlspecialchars($invoice['patient_name']); ?></p>
                                        <p><strong>Service:</strong> <?php echo htmlspecialchars($invoice['service_name']); ?></p>
                                        <p><strong>Date:</strong> <?php echo date('M d, Y', strtotime($invoice['created_at'])); ?></p>
                                    </div>
                                    <div class="total">
                                        <p>Total: KES <?php echo number_format($invoice['amount'], 2); ?></p>
                                        <p>Status: <?php echo ucfirst($invoice['payment_status']); ?></p>
                                    </div>
                                    <div class="footer">
                                        <p>Thank you for choosing Alpine Healthcare</p>
                                    </div>
                                </div>
                                <div class="d-grid gap-2 mt-3 no-print">
                                    <button class="btn btn-primary" onclick="window.print()">
                                        <i class="bi bi-printer"></i> Print Receipt
                                    </button>
                                    <a href="billing.php" class="btn btn-secondary">
                                        <i class="bi bi-arrow-left"></i> Back to Billing
                                    </a>
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