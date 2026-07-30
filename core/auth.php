<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 1800,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

if (!isset($_SESSION['created_at'])) {
    $_SESSION['created_at'] = time();
}

if (!isset($_SESSION['last_activity'])) {
    $_SESSION['last_activity'] = time();
}

$sessionTimeoutSeconds = 1800;
if ((time() - $_SESSION['last_activity']) > $sessionTimeoutSeconds) {
    logoutUser();
    if (basename($_SERVER['PHP_SELF']) !== 'login.php') {
        header('Location: /alpine-healthcare/login.php?error=timeout');
        exit();
    }
}

$_SESSION['last_activity'] = time();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isDoctor() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'doctor';
}

function isReceptionist() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'receptionist';
}

function isPharmacist() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'pharmacist';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /alpine-healthcare/login.php');
        exit();
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: /alpine-healthcare/dashboard.php');
        exit();
    }
}

function requireDoctor() {
    requireLogin();
    if (!isDoctor()) {
        header('Location: /alpine-healthcare/dashboard.php');
        exit();
    }
}

function requireReceptionist() {
    requireLogin();
    if (!isReceptionist()) {
        header('Location: /alpine-healthcare/dashboard.php');
        exit();
    }
}

function requirePharmacist() {
    requireLogin();
    if (!isPharmacist()) {
        header('Location: /alpine-healthcare/dashboard.php');
        exit();
    }
}

function getCurrentUser($pdo) {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If user is a doctor, also get doctor data
        if ($user && $user['role'] == 'doctor') {
            $stmt = $pdo->prepare("SELECT * FROM doctors WHERE email = ?");
            $stmt->execute([$user['email']]);
            $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($doctor) {
                $_SESSION['doctor_id'] = $doctor['id'];
                $_SESSION['doctor_name'] = $doctor['fullname'];
                $_SESSION['specialization'] = $doctor['specialization'] ?? 'General Practice';
            }
        }
        
        return $user;
    } catch (PDOException $e) {
        error_log("Error fetching user: " . $e->getMessage());
        return null;
    }
}

function currentUserId() {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function currentUserRole() {
    return $_SESSION['role'] ?? null;
}

function currentUserFullname() {
    return $_SESSION['fullname'] ?? null;
}

function currentUserEmail() {
    return $_SESSION['email'] ?? null;
}

function getCurrentDoctorId() {
    return isset($_SESSION['doctor_id']) ? (int)$_SESSION['doctor_id'] : 0;
}

function getCurrentDoctor($pdo) {
    $doctor_id = getCurrentDoctorId();
    if ($doctor_id <= 0) {
        return null;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM doctors WHERE id = ?");
        $stmt->execute([$doctor_id]);
        $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Update session with doctor data
        if ($doctor) {
            $_SESSION['doctor_name'] = $doctor['fullname'] ?? $_SESSION['fullname'] ?? 'Doctor';
            $_SESSION['specialization'] = $doctor['specialization'] ?? 'General Practice';
            $_SESSION['doctor_email'] = $doctor['email'] ?? $_SESSION['email'] ?? '';
        }
        
        return $doctor;
    } catch (PDOException $e) {
        error_log("Error fetching doctor: " . $e->getMessage());
        return null;
    }
}

function ensureDoctorSession($pdo) {
    if (!isDoctor()) {
        return;
    }
    
    // If doctor_id is not in session, try to get it
    if (!isset($_SESSION['doctor_id']) || $_SESSION['doctor_id'] <= 0) {
        $user = getCurrentUser($pdo);
        if ($user) {
            $stmt = $pdo->prepare("SELECT id, fullname, specialization FROM doctors WHERE email = ?");
            $stmt->execute([$user['email']]);
            $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($doctor) {
                $_SESSION['doctor_id'] = $doctor['id'];
                $_SESSION['doctor_name'] = $doctor['fullname'] ?? $user['fullname'] ?? 'Doctor';
                $_SESSION['specialization'] = $doctor['specialization'] ?? 'General Practice';
                $_SESSION['doctor_email'] = $user['email'];
            } else {
                // If no doctor record, create one
                $stmt = $pdo->prepare("INSERT INTO doctors (fullname, email, specialization, phone, availability) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$user['fullname'] ?? 'Doctor', $user['email'], 'General Practice', 'N/A', 'Mon-Fri 9AM-5PM']);
                $doctor_id = $pdo->lastInsertId();
                
                $_SESSION['doctor_id'] = $doctor_id;
                $_SESSION['doctor_name'] = $user['fullname'] ?? 'Doctor';
                $_SESSION['specialization'] = 'General Practice';
                $_SESSION['doctor_email'] = $user['email'];
            }
        }
    }
    
    // Ensure doctor_name is set
    if (!isset($_SESSION['doctor_name']) || empty($_SESSION['doctor_name'])) {
        $_SESSION['doctor_name'] = $_SESSION['fullname'] ?? 'Doctor';
    }
}

function redirectBasedOnRole($role) {
    switch($role) {
        case 'admin':
            header('Location: /alpine-healthcare/dashboard.php');
            break;
        case 'doctor':
            header('Location: /alpine-healthcare/modules/doctors/dashboard.php');
            break;
        case 'receptionist':
            header('Location: /alpine-healthcare/modules/receptionist/rec_dashboard.php');
            break;
        case 'pharmacist':
            header('Location: /alpine-healthcare/modules/pharmacy/index.php');
            break;
        default:
            header('Location: /alpine-healthcare/dashboard.php');
    }
    exit();
}

function initializeUserSession($user) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['fullname'] = $user['fullname'] ?? 'User';
    $_SESSION['email'] = $user['email'] ?? '';
    $_SESSION['role'] = $user['role'] ?? 'user';
    $_SESSION['created_at'] = time();
    $_SESSION['last_activity'] = time();
    session_regenerate_id(true);
    
    // If user is a doctor, set doctor session
    if($user['role'] == 'doctor') {
        try {
            global $pdo;
            $stmt = $pdo->prepare("SELECT * FROM doctors WHERE email = ?");
            $stmt->execute([$user['email']]);
            $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if($doctor) {
                $_SESSION['doctor_id'] = $doctor['id'];
                $_SESSION['doctor_name'] = $doctor['fullname'] ?? $user['fullname'];
                $_SESSION['doctor_fullname'] = $doctor['fullname'] ?? $user['fullname'];
                $_SESSION['specialization'] = $doctor['specialization'] ?? 'General Practice';
                $_SESSION['doctor_email'] = $doctor['email'] ?? $user['email'];
                $_SESSION['active_profile'] = $doctor['active_profile'] ?? 'doctor';
            } else {
                // Create doctor record if not exists
                $stmt = $pdo->prepare("INSERT INTO doctors (fullname, email, specialization, phone, availability) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$user['fullname'], $user['email'], 'General Practice', 'N/A', 'Mon-Fri 9AM-5PM']);
                $doctor_id = $pdo->lastInsertId();
                
                $_SESSION['doctor_id'] = $doctor_id;
                $_SESSION['doctor_name'] = $user['fullname'];
                $_SESSION['doctor_fullname'] = $user['fullname'];
                $_SESSION['specialization'] = 'General Practice';
                $_SESSION['doctor_email'] = $user['email'];
                $_SESSION['active_profile'] = 'doctor';
            }
        } catch(PDOException $e) {
            error_log("Failed to load doctor data: " . $e->getMessage());
            // Set default values
            $_SESSION['doctor_name'] = $user['fullname'] ?? 'Doctor';
            $_SESSION['specialization'] = 'General Practice';
            $_SESSION['doctor_email'] = $user['email'] ?? '';
            $_SESSION['active_profile'] = 'doctor';
        }
    }
}

function logoutUser() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

function refreshSession() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['last_activity'] = time();
        session_regenerate_id(true);
    }
}

function regenerateSession() {
    session_regenerate_id(true);
}

// Helper function to get doctor name with fallback
function getDoctorName() {
    return $_SESSION['doctor_name'] ?? $_SESSION['fullname'] ?? 'Doctor';
}

// Helper function to get doctor specialization with fallback
function getDoctorSpecialization() {
    return $_SESSION['specialization'] ?? 'General Practice';
}

// Helper function to get active profile with fallback
function getActiveProfile() {
    return $_SESSION['active_profile'] ?? 'doctor';
}
?>