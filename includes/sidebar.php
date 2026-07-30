<?php
// Get current user for sidebar if not already set
if (!isset($user)) {
    $user = getCurrentUser($pdo);
}

// Determine the current module
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

// Get active profile from session
$active_profile = $_SESSION['active_profile'] ?? 'doctor';

// ALWAYS refresh doctor info from database if in doctors module
$doctor_id = $_SESSION['doctor_id'] ?? 0;
$doctor_display_name = $_SESSION['doctor_name'] ?? $user['fullname'] ?? 'User';
$doctor_specialization = $_SESSION['specialization'] ?? 'General Practice';
$doctor_phone = '';
$doctor_email = '';
$doctor_availability = '';
$doctor_fullname = '';

// Force refresh doctor data from database when in doctors module
if ($current_dir == 'doctors' && $doctor_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM doctors WHERE id = ?");
        $stmt->execute([$doctor_id]);
        $doctor_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($doctor_info) {
            // Update all session variables with fresh data
            $doctor_fullname = $doctor_info['fullname'];
            $doctor_display_name = $doctor_info['fullname'];
            $doctor_specialization = $doctor_info['specialization'] ?? 'General Practice';
            $doctor_phone = $doctor_info['phone'] ?? 'N/A';
            $doctor_email = $doctor_info['email'] ?? '';
            $doctor_availability = $doctor_info['availability'] ?? 'Mon-Fri 9AM-5PM';
            
            // Update session with fresh data
            $_SESSION['doctor_name'] = $doctor_fullname;
            $_SESSION['doctor_fullname'] = $doctor_fullname;
            $_SESSION['specialization'] = $doctor_specialization;
            $_SESSION['doctor_email'] = $doctor_email;
            
            // Also update the user session name for consistency
            $_SESSION['fullname'] = $doctor_fullname;
            
            // Update the user variable for display
            $user['fullname'] = $doctor_fullname;
        }
    } catch(PDOException $e) {
        // Handle error silently - use session values as fallback
    }
} else {
    // For non-doctor modules, use session values
    $doctor_display_name = $_SESSION['doctor_name'] ?? $user['fullname'] ?? 'User';
}

// Get doctor statistics for badges (only if in doctors module)
$pending_appointments = 0;
$total_patients = 0;
$pending_prescriptions = 0;
if ($current_dir == 'doctors' && $doctor_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM appointments WHERE doctor_id = ? AND status = 'Scheduled'");
        $stmt->execute([$doctor_id]);
        $pending_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT patient_id) as total FROM appointments WHERE doctor_id = ?");
        $stmt->execute([$doctor_id]);
        $total_patients = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM prescriptions WHERE doctor_id = ? AND status = 'pending'");
        $stmt->execute([$doctor_id]);
        $pending_prescriptions = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    } catch(PDOException $e) {
        // Handle error silently
    }
}

// Get pharmacy pending prescriptions count
$pharmacy_pending = 0;
if ($current_dir == 'pharmacy') {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM prescriptions WHERE status = 'pending'");
        $pharmacy_pending = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    } catch(PDOException $e) {
        // Handle error silently
    }
}

// Get the active profile for display
$active_profile_display = $active_profile;
if ($active_profile == 'doctor') {
    $active_profile_display = '';
} else {
    $active_profile_display = ' (' . ucfirst($active_profile) . ')';
}
?>
<nav class="col-md-2 sidebar">
    <div class="brand">
        <h4>🏔️ <span>Alpine</span></h4>
        <small>
            <?php 
            if ($current_dir == 'receptionist') {
                echo 'Reception Panel';
            } elseif ($current_dir == 'pharmacy') {
                echo 'Pharmacy Management';
            } elseif ($current_dir == 'doctors') {
                echo 'Doctor Panel';
                if ($active_profile != 'doctor') {
                    echo ' <span class="badge bg-info ms-1" style="font-size:9px;">' . ucfirst($active_profile) . '</span>';
                }
            } else {
                echo 'Clinic Management';
            }
            ?>
        </small>
    </div>
    
    <?php if ($current_dir == 'doctors'): ?>
    <div class="doctor-info-card">
        <div class="doctor-avatar-large">
            <?php echo strtoupper(substr($doctor_display_name, 0, 1)); ?>
        </div>
        <div class="doctor-name-display">
            <?php 
            $name_to_display = $doctor_display_name;
            if (stripos($name_to_display, 'Dr.') === 0 || stripos($name_to_display, 'Dr ') === 0) {
                echo htmlspecialchars($name_to_display);
            } else {
                echo 'Dr. ' . htmlspecialchars($name_to_display);
            }
            ?>
        </div>
        <div class="doctor-specialization-display">
            <i class="bi bi-briefcase"></i> <?php echo htmlspecialchars($doctor_specialization); ?>
        </div>
    </div>
    <?php else: ?>
    <!-- Regular User Info -->
    <div class="user-info d-flex align-items-center gap-3">
        <div class="avatar"><?php echo strtoupper(substr($doctor_display_name, 0, 1)); ?></div>
        <div>
            <div class="user-name"><?php echo htmlspecialchars($doctor_display_name); ?></div>
            <div class="user-role">
                <i class="bi bi-shield-check"></i> 
                <?php 
                $role_display = ucfirst($user['role'] ?? 'user');
                echo $role_display;
                ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="nav-section">Main Menu</div>
    
    <?php if ($current_dir == 'receptionist'): ?>
        <a href="../receptionist/dashboard.php" class="nav-item <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>
        <a href="../patients/index.php" class="nav-item <?php echo $current_page == 'index.php' && $current_dir == 'patients' ? 'active' : ''; ?>">
            <i class="bi bi-people"></i> Patients
        </a>
        <a href="../receptionist/appointments.php" class="nav-item <?php echo $current_page == 'appointments.php' ? 'active' : ''; ?>">
            <i class="bi bi-calendar-event"></i> Appointments
        </a>
        <a href="../billing/index.php" class="nav-item <?php echo $current_page == 'index.php' && $current_dir == 'billing' ? 'active' : ''; ?>">
            <i class="bi bi-receipt"></i> Billing
        </a>
        
    <?php elseif ($current_dir == 'pharmacy'): ?>
        <a href="dashboard.php" class="nav-item <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="bi bi-speedometer2"></i> Dashboard
            <?php if($pharmacy_pending > 0): ?>
                <span class="badge"><?php echo $pharmacy_pending; ?></span>
            <?php endif; ?>
        </a>
        <a href="index.php" class="nav-item <?php echo $current_page == 'index.php' ? 'active' : ''; ?>">
            <i class="bi bi-capsule"></i> Medicines
        </a>
        <a href="add.php" class="nav-item <?php echo $current_page == 'add.php' ? 'active' : ''; ?>">
            <i class="bi bi-plus-circle"></i> Add Medicine
        </a>
        <a href="dispense.php" class="nav-item <?php echo $current_page == 'dispense.php' ? 'active' : ''; ?>">
            <i class="bi bi-prescription"></i> Dispense
            <?php if($pharmacy_pending > 0): ?>
                <span class="badge"><?php echo $pharmacy_pending; ?></span>
            <?php endif; ?>
        </a>
        <a href="dispense_prescription.php" class="nav-item <?php echo $current_page == 'dispense_prescription.php' ? 'active' : ''; ?>">
            <i class="bi bi-prescription2"></i> Prescriptions
            <?php if($pharmacy_pending > 0): ?>
                <span class="badge"><?php echo $pharmacy_pending; ?></span>
            <?php endif; ?>
        </a>
        
    <?php elseif ($current_dir == 'doctors'): ?>
        <a href="dashboard.php" class="nav-item <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="bi bi-speedometer2"></i> Dashboard
            <span class="badge"><?php echo $pending_appointments; ?></span>
        </a>
        <a href="appointments.php" class="nav-item <?php echo $current_page == 'appointments.php' ? 'active' : ''; ?>">
            <i class="bi bi-calendar-event"></i> Appointments
            <span class="badge"><?php echo $pending_appointments; ?></span>
        </a>
        <a href="patients.php" class="nav-item <?php echo $current_page == 'patients.php' ? 'active' : ''; ?>">
            <i class="bi bi-people"></i> My Patients
            <span class="badge"><?php echo $total_patients; ?></span>
        </a>
        <a href="prescribe.php" class="nav-item <?php echo $current_page == 'prescribe.php' ? 'active' : ''; ?>">
            <i class="bi bi-prescription"></i> Prescribe
            <span class="badge"><?php echo $pending_prescriptions; ?></span>
        </a>
        <a href="prescriptions.php" class="nav-item <?php echo $current_page == 'prescriptions.php' ? 'active' : ''; ?>">
            <i class="bi bi-list-check"></i> Prescriptions
            <span class="badge"><?php echo $pending_prescriptions; ?></span>
        </a>
        <a href="profile.php" class="nav-item <?php echo $current_page == 'profile.php' ? 'active' : ''; ?>">
            <i class="bi bi-person-circle"></i> Profile
        </a>
        
        <?php if($active_profile != 'doctor'): ?>
        <div class="nav-section">Profile Views</div>
        <a href="profile.php?switch_profile=doctor&doctor_id=<?php echo $doctor_id; ?>" class="nav-item <?php echo $active_profile == 'doctor' ? 'active' : ''; ?>">
            <i class="bi bi-person"></i> Doctor View
        </a>
        <a href="profile.php?switch_profile=specialist&doctor_id=<?php echo $doctor_id; ?>" class="nav-item <?php echo $active_profile == 'specialist' ? 'active' : ''; ?>">
            <i class="bi bi-star"></i> Specialist View
        </a>
        <a href="profile.php?switch_profile=consultant&doctor_id=<?php echo $doctor_id; ?>" class="nav-item <?php echo $active_profile == 'consultant' ? 'active' : ''; ?>">
            <i class="bi bi-briefcase"></i> Consultant View
        </a>
        <a href="profile.php?switch_profile=researcher&doctor_id=<?php echo $doctor_id; ?>" class="nav-item <?php echo $active_profile == 'researcher' ? 'active' : ''; ?>">
            <i class="bi bi-graph-up"></i> Researcher View
        </a>
        <?php endif; ?>
        
        <div class="nav-section">Account</div>
        <a href="../../logout.php" class="nav-item">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
        
    <?php else: ?>
        <a href="../dashboard.php" class="nav-item <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>
        <a href="../patients/index.php" class="nav-item">
            <i class="bi bi-people"></i> Patients
        </a>
        <a href="../doctors/index.php" class="nav-item">
            <i class="bi bi-person-badge"></i> Doctors
        </a>
        <a href="../appointments/index.php" class="nav-item">
            <i class="bi bi-calendar-event"></i> Appointments
        </a>
        <a href="../billing/index.php" class="nav-item">
            <i class="bi bi-receipt"></i> Billing
        </a>
        <a href="../pharmacy/index.php" class="nav-item">
            <i class="bi bi-capsule"></i> Pharmacy
        </a>
        <div class="nav-section">Account</div>
        <a href="../logout.php" class="nav-item">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    <?php endif; ?>
    
    <?php if(isAdmin() && $current_dir != 'doctors'): ?>
    <div class="nav-section">Administration</div>
    <a href="../admin/index.php" class="nav-item">
        <i class="bi bi-gear"></i> Admin Panel
    </a>
    <?php endif; ?>
</nav>

<style>
    .sidebar .nav-item .badge {
        margin-left: auto;
        background: #e74c3c;
        font-size: 10px;
        padding: 2px 8px;
        border-radius: 12px;
    }
    
    .sidebar .nav-item.active .badge {
        background: #fff;
        color: #2ecc71;
    }
    
    .sidebar .brand .badge {
        font-size: 9px;
        padding: 2px 6px;
        background: rgba(255,255,255,0.2);
        color: white;
        border-radius: 12px;
    }
    
    /* Doctor Info Card Styles */
    .sidebar .doctor-info-card {
        padding: 15px 20px;
        border-bottom: 1px solid rgba(255,255,255,0.05);
        text-align: center;
        margin-bottom: 5px;
    }
    
    .sidebar .doctor-avatar-large {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: linear-gradient(135deg, #2ecc71, #27ae60);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 700;
        font-size: 24px;
        margin: 0 auto 8px;
        border: 3px solid rgba(255,255,255,0.1);
    }
    
    .sidebar .doctor-name-display {
        color: white;
        font-weight: 600;
        font-size: 15px;
        margin-bottom: 2px;
    }
    
    .sidebar .doctor-specialization-display {
        color: rgba(255,255,255,0.6);
        font-size: 12px;
        margin-bottom: 8px;
    }
    
    .sidebar .doctor-contact-display {
        color: rgba(255,255,255,0.4);
        font-size: 11px;
        line-height: 1.6;
    }
    
    .sidebar .doctor-contact-display i {
        margin-right: 4px;
        font-size: 10px;
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
</style>

<script>
// Add a function to force refresh the sidebar
function refreshSidebar() {
    // Reload the page to refresh all data
    window.location.reload();
}

// Check if we should refresh (for profile switching)
document.addEventListener('DOMContentLoaded', function() {
    // Check if the URL has a profile switch parameter
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('switched')) {
        // Remove the parameter to prevent loop
        const newUrl = window.location.pathname + window.location.search.replace(/[?&]switched=1/, '');
        window.history.replaceState({}, document.title, newUrl);
    }
});
</script>