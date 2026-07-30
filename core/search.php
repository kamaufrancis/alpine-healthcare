<?php
function searchPatients($pdo, $query) {
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE fullname LIKE ? OR phone LIKE ? OR address LIKE ?");
    $search = "%$query%";
    $stmt->execute([$search, $search, $search]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function searchDoctors($pdo, $query) {
    $stmt = $pdo->prepare("SELECT * FROM doctors WHERE fullname LIKE ? OR specialization LIKE ? OR email LIKE ?");
    $search = "%$query%";
    $stmt->execute([$search, $search, $search]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function searchMedicines($pdo, $query) {
    $stmt = $pdo->prepare("SELECT * FROM medicines WHERE name LIKE ? OR category LIKE ?");
    $search = "%$query%";
    $stmt->execute([$search, $search]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>