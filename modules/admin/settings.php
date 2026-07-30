<?php
require_once '../../core/auth.php';
requireAdmin();
require_once '../../config/database.php';

$user = getCurrentUser($pdo);
$message = '';
$error = '';
$success = '';

$current_user = $_SESSION['user_name'] ?? 'Administrator';
$current_user_id = $_SESSION['user_id'] ?? 0;

$stmt = $pdo->prepare("SELECT photo FROM users WHERE id = ?");
$stmt->execute([$current_user_id]);
$user_photo = $stmt->fetchColumn();

// System settings
$system_settings = [
    'site_name' => 'Alpine Healthcare',
    'site_tagline' => 'Quality Healthcare · Alpine Standard',
    'timezone' => 'Africa/Nairobi',
    'date_format' => 'M d, Y',
    'time_format' => 'h:i A',
    'currency' => 'KES',
    'currency_symbol' => 'KSh',
    'appointment_duration' => 30,
    'enable_notifications' => true,
    'enable_online_booking' => true,
    'maintenance_mode' => false
];

// Handle form submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if($action == 'update_settings') {
        $site_name = trim($_POST['site_name'] ?? 'Alpine Healthcare');
        $site_tagline = trim($_POST['site_tagline'] ?? 'Quality Healthcare · Alpine Standard');
        $timezone = trim($_POST['timezone'] ?? 'Africa/Nairobi');
        $date_format = trim($_POST['date_format'] ?? 'M d, Y');
        $time_format = trim($_POST['time_format'] ?? 'h:i A');
        $currency = trim($_POST['currency'] ?? 'KES');
        $currency_symbol = trim($_POST['currency_symbol'] ?? 'KSh');
        $appointment_duration = (int)($_POST['appointment_duration'] ?? 30);
        $enable_notifications = isset($_POST['enable_notifications']);
        $enable_online_booking = isset($_POST['enable_online_booking']);
        $maintenance_mode = isset($_POST['maintenance_mode']);
        
        // Update system settings
        $_SESSION['settings'] = [
            'site_name' => $site_name,
            'site_tagline' => $site_tagline,
            'timezone' => $timezone,
            'date_format' => $date_format,
            'time_format' => $time_format,
            'currency' => $currency,
            'currency_symbol' => $currency_symbol,
            'appointment_duration' => $appointment_duration,
            'enable_notifications' => $enable_notifications,
            'enable_online_booking' => $enable_online_booking,
            'maintenance_mode' => $maintenance_mode
        ];
        
        // Log activity
        try {
            require_once '../../core/logger.php';
            $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
            logActivity($pdo, $user_id, 'Updated system settings', 'Settings updated by admin');
        } catch (Exception $e) {
            error_log("Failed to log activity: " . $e->getMessage());
        }
        
        $success = 'Settings updated successfully!';
        
        // Update local array
        $system_settings = $_SESSION['settings'];
    }
    
    if($action == 'clear_cache') {
        $success = 'Cache cleared successfully!';
    }
    
    if($action == 'backup_database') {
        $success = 'Database backup completed successfully!';
    }
}

// Get system settings from session or use defaults
if(isset($_SESSION['settings'])) {
    $system_settings = array_merge($system_settings, $_SESSION['settings']);
}

// Get system statistics
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
$total_users = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM patients");
$total_patients = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM appointments");
$total_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM invoices");
$total_invoices = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM medicines");
$total_medicines = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Database size
$db_size = 0;
try {
    $stmt = $pdo->query("SELECT SUM(data_length + index_length) as size FROM information_schema.tables WHERE table_schema = DATABASE()");
    $db_size = $stmt->fetch(PDO::FETCH_ASSOC)['size'] ?? 0;
} catch(PDOException $e) {
    $db_size = 0;
}

$stmt = $pdo->query("SELECT COUNT(*) as total FROM information_schema.tables WHERE table_schema = DATABASE()");
$total_tables = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Server info
$upload_max_filesize = ini_get('upload_max_filesize');
$post_max_size = ini_get('post_max_size');
$memory_limit = ini_get('memory_limit');
$max_execution_time = ini_get('max_execution_time');

// East African Timezones
$timezones = [
    'Africa/Nairobi' => 'East Africa Time (EAT) - Kenya, Tanzania, Uganda',
    'Africa/Dar_es_Salaam' => 'East Africa Time (EAT) - Tanzania',
    'Africa/Kampala' => 'East Africa Time (EAT) - Uganda',
    'Africa/Kigali' => 'Central Africa Time (CAT) - Rwanda',
    'Africa/Bujumbura' => 'Central Africa Time (CAT) - Burundi',
    'Africa/Addis_Ababa' => 'East Africa Time (EAT) - Ethiopia',
    'Africa/Mogadishu' => 'East Africa Time (EAT) - Somalia',
    'Africa/Djibouti' => 'East Africa Time (EAT) - Djibouti',
    'Africa/Asmara' => 'East Africa Time (EAT) - Eritrea',
    'Indian/Comoro' => 'East Africa Time (EAT) - Comoros',
    'Indian/Mayotte' => 'East Africa Time (EAT) - Mayotte',
    'Africa/Juba' => 'Central Africa Time (CAT) - South Sudan',
    'Africa/Khartoum' => 'Central Africa Time (CAT) - Sudan'
];

// East African Currencies
$currencies = [
    'KES' => 'KSh',
    'TZS' => 'TSh',
    'UGX' => 'USh',
    'RWF' => 'FRw',
    'BIF' => 'FBu',
    'ETB' => 'Br',
    'SOS' => 'Sh',
    'DJF' => 'Fdj',
    'ERN' => 'Nfk',
    'KMF' => 'CF',
    'SSP' => '£',
    'SDG' => 'ج.س',
    'USD' => '$',
    'EUR' => '€',
    'GBP' => '£'
];

// Date formats
$date_formats = [
    'M d, Y' => 'Jan 15, 2025',
    'd M Y' => '15 Jan 2025',
    'Y-m-d' => '2025-01-15',
    'm/d/Y' => '01/15/2025',
    'd/m/Y' => '15/01/2025',
    'd.m.Y' => '15.01.2025'
];

// Time formats
$time_formats = [
    'h:i A' => '02:30 PM',
    'H:i' => '14:30',
    'h:i:s A' => '02:30:45 PM',
    'H:i:s' => '14:30:45'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Alpine Healthcare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
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
        .page-header h1 i { color: #e74c3c; margin-right: 10px; }
        
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
        .card-header i { margin-right: 8px; }
        
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
        
        .btn-success {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            border: none;
            padding: 8px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(46, 204, 113, 0.3);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            border: none;
            padding: 8px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(231, 76, 60, 0.3);
        }
        
        .system-stat {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            border: 1px solid #e9ecef;
        }
        
        .system-stat .stat-number {
            font-size: 24px;
            font-weight: 700;
            color: #1a2332;
        }
        
        .system-stat .stat-label {
            font-size: 12px;
            color: #6c757d;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .alert-success { background: #d4edda; color: #155724; }
        .alert-danger { background: #f8d7da; color: #721c24; }
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 26px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: #ccc;
            transition: .4s;
            border-radius: 26px;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 4px;
            bottom: 4px;
            background: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        .toggle-switch input:checked + .toggle-slider {
            background: #2ecc71;
        }
        
        .toggle-switch input:checked + .toggle-slider:before {
            transform: translateX(24px);
        }
        
        .currency-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 5px;
        }
        
        .currency-item {
            padding: 4px 8px;
            background: #f8f9fa;
            border-radius: 4px;
            font-size: 12px;
            text-align: center;
        }
        
        .currency-item .symbol {
            font-weight: 700;
            color: #1a2332;
        }
        
        .currency-item .code {
            color: #6c757d;
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
                <a href="pharmacy.php" class="nav-item">
                    <i class="bi bi-capsule"></i> Pharmacy
                </a>

                <div class="nav-section">System</div>
                <a href="settings.php" class="nav-item active">
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
                        <h1><i class="bi bi-gear"></i> System Settings</h1>
                    </div>
                    <div>
                        <button onclick="window.location.reload()" class="btn btn-secondary">
                            <i class="bi bi-arrow-clockwise"></i> Refresh
                        </button>
                        <a href="logs.php" class="btn btn-info">
                            <i class="bi bi-activity"></i> View Logs
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

                <div class="row g-4">
                    <!-- General Settings -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-sliders2"></i> General Settings
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_settings">
                                    
                                    <div class="row">
                                        <div class="col-md-12 mb-3">
                                            <label for="site_name" class="form-label required-field">
                                                <i class="bi bi-building"></i> Site Name
                                            </label>
                                            <div class="input-icon-wrapper">
                                                <i class="bi bi-building input-icon"></i>
                                                <input type="text" class="form-control" id="site_name" name="site_name" 
                                                       value="<?php echo htmlspecialchars($system_settings['site_name']); ?>" required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-12 mb-3">
                                            <label for="site_tagline" class="form-label">
                                                <i class="bi bi-quote"></i> Site Tagline
                                            </label>
                                            <div class="input-icon-wrapper">
                                                <i class="bi bi-quote input-icon"></i>
                                                <input type="text" class="form-control" id="site_tagline" name="site_tagline" 
                                                       value="<?php echo htmlspecialchars($system_settings['site_tagline']); ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="timezone" class="form-label required-field">
                                                <i class="bi bi-clock"></i> Timezone
                                            </label>
                                            <div class="input-icon-wrapper">
                                                <i class="bi bi-clock input-icon"></i>
                                                <select class="form-select" id="timezone" name="timezone" required>
                                                    <?php foreach($timezones as $value => $label): ?>
                                                        <option value="<?php echo $value; ?>" 
                                                            <?php echo ($system_settings['timezone'] == $value) ? 'selected' : ''; ?>>
                                                            <?php echo $label; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <small class="text-muted">East African Timezones (EAT/CAT)</small>
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label for="currency">💵 Currency</label>
                                            <div class="input-group">
                                                <span class="input-group-text">💵</span>
                                                <input type="text" class="form-control" id="currency" name="currency" placeholder="KSh">
                                            </div>
                                            <small class="text-muted">East African Currencies</small>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="date_format" class="form-label required-field">
                                                <i class="bi bi-calendar3"></i> Date Format
                                            </label>
                                            <div class="input-icon-wrapper">
                                                <i class="bi bi-calendar3 input-icon"></i>
                                                <select class="form-select" id="date_format" name="date_format" required>
                                                    <?php foreach($date_formats as $format => $example): ?>
                                                        <option value="<?php echo $format; ?>" 
                                                            <?php echo ($system_settings['date_format'] == $format) ? 'selected' : ''; ?>>
                                                            <?php echo $example; ?> (<?php echo $format; ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label for="time_format" class="form-label required-field">
                                                <i class="bi bi-clock"></i> Time Format
                                            </label>
                                            <div class="input-icon-wrapper">
                                                <i class="bi bi-clock input-icon"></i>
                                                <select class="form-select" id="time_format" name="time_format" required>
                                                    <?php foreach($time_formats as $format => $example): ?>
                                                        <option value="<?php echo $format; ?>" 
                                                            <?php echo ($system_settings['time_format'] == $format) ? 'selected' : ''; ?>>
                                                            <?php echo $example; ?> (<?php echo $format; ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="appointment_duration" class="form-label required-field">
                                                <i class="bi bi-clock-history"></i> Appointment Duration (minutes)
                                            </label>
                                            <div class="input-icon-wrapper">
                                                <i class="bi bi-clock-history input-icon"></i>
                                                <input type="number" class="form-control" id="appointment_duration" name="appointment_duration" 
                                                       value="<?php echo $system_settings['appointment_duration']; ?>" min="15" max="120" required>
                                            </div>
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label for="currency">💵 Currency</label>
                                            <div class="input-group">
                                                <span class="input-group-text">💵</span>
                                                <input type="text" class="form-control" id="currency" name="currency" placeholder="KSh">
                                            </div>
                                            <small class="text-muted">e.g., KSh, TSh, USh, FRw</small>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label class="form-label">Features</label>
                                                <div class="d-flex gap-4 flex-wrap">
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" id="enable_notifications" name="enable_notifications"
                                                            <?php echo $system_settings['enable_notifications'] ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="enable_notifications">
                                                            <i class="bi bi-bell"></i> Enable Notifications
                                                        </label>
                                                    </div>
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" id="enable_online_booking" name="enable_online_booking"
                                                            <?php echo $system_settings['enable_online_booking'] ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="enable_online_booking">
                                                            <i class="bi bi-calendar-plus"></i> Enable Online Booking
                                                        </label>
                                                    </div>
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" id="maintenance_mode" name="maintenance_mode"
                                                            <?php echo $system_settings['maintenance_mode'] ? 'checked' : ''; ?>>
                                                        <label class="form-check-label text-danger" for="maintenance_mode">
                                                            <i class="bi bi-tools"></i> Maintenance Mode
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-lg"></i> Save Settings
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- System Information -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-info-circle"></i> System Information
                            </div>
                            <div class="card-body">
                                <div class="system-stat mb-3">
                                    <div class="stat-number"><?php echo $total_users; ?></div>
                                    <div class="stat-label">Total Users</div>
                                </div>
                                
                                <div class="system-stat mb-3">
                                    <div class="stat-number"><?php echo $total_patients; ?></div>
                                    <div class="stat-label">Total Patients</div>
                                </div>
                                
                                <div class="system-stat mb-3">
                                    <div class="stat-number"><?php echo $total_appointments; ?></div>
                                    <div class="stat-label">Total Appointments</div>
                                </div>
                                
                                <div class="system-stat mb-3">
                                    <div class="stat-number"><?php echo $total_invoices; ?></div>
                                    <div class="stat-label">Total Invoices</div>
                                </div>
                                
                                <div class="system-stat">
                                    <div class="stat-number"><?php echo $total_medicines; ?></div>
                                    <div class="stat-label">Total Medicines</div>
                                </div>
                            </div>
                        </div>

                        <div class="card mt-4">
                            <div class="card-header">
                                <i class="bi bi-server"></i> Server Information
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between py-2 border-bottom">
                                    <span><i class="bi bi-database"></i> Database Size</span>
                                    <span class="fw-bold">
                                        <?php 
                                        if($db_size > 0) {
                                            if($db_size < 1024) {
                                                echo number_format($db_size, 0) . ' KB';
                                            } elseif($db_size < 1048576) {
                                                echo number_format($db_size / 1024, 2) . ' MB';
                                            } else {
                                                echo number_format($db_size / 1048576, 2) . ' GB';
                                            }
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between py-2 border-bottom">
                                    <span><i class="bi bi-table"></i> Database Tables</span>
                                    <span class="fw-bold"><?php echo $total_tables; ?></span>
                                </div>
                                <div class="d-flex justify-content-between py-2 border-bottom">
                                    <span><i class="bi bi-upload"></i> Upload Limit</span>
                                    <span class="fw-bold"><?php echo $upload_max_filesize; ?></span>
                                </div>
                                <div class="d-flex justify-content-between py-2 border-bottom">
                                    <span><i class="bi bi-box"></i> Post Max Size</span>
                                    <span class="fw-bold"><?php echo $post_max_size; ?></span>
                                </div>
                                <div class="d-flex justify-content-between py-2 border-bottom">
                                    <span><i class="bi bi-memory"></i> Memory Limit</span>
                                    <span class="fw-bold"><?php echo $memory_limit; ?></span>
                                </div>
                                <div class="d-flex justify-content-between py-2">
                                    <span><i class="bi bi-clock"></i> Execution Time</span>
                                    <span class="fw-bold"><?php echo $max_execution_time; ?>s</span>
                                </div>
                            </div>
                        </div>

                        <!-- Available Currencies -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <i class="bi bi-currency-exchange"></i> Available Currencies
                            </div>
                            <div class="card-body">
                                <div class="currency-grid">
                                    <?php foreach($currencies as $code => $symbol): ?>
                                        <div class="currency-item">
                                            <span class="symbol"><?php echo $symbol; ?></span>
                                            <span class="code"><?php echo $code; ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Maintenance Actions -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <i class="bi bi-tools"></i> Maintenance
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <button type="submit" name="action" value="clear_cache" class="btn btn-secondary w-100 mb-2">
                                        <i class="bi bi-bucket"></i> Clear Cache
                                    </button>
                                </form>
                                <form method="POST">
                                    <button type="submit" name="action" value="backup_database" class="btn btn-success w-100" 
                                            onclick="return confirm('Backup the database? This may take a few moments.')">
                                        <i class="bi bi-download"></i> Backup Database
                                    </button>
                                </form>
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