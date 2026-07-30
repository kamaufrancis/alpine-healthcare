<?php

// MySQLi connection (used by some parts of the app)
$conn = new mysqli(
	"localhost",
	"root",
	"",
	"alpine_healthcare"
);

if ($conn->connect_error) {
	die("Database connection failed: " . $conn->connect_error);
}

// PDO connection (preferred for prepared statements across core files)
try {
	$dsn = 'mysql:host=localhost;dbname=alpine_healthcare;charset=utf8mb4';
	$pdo = new PDO($dsn, 'root', '', [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		PDO::ATTR_EMULATE_PREPARES => false,
	]);
} catch (PDOException $e) {
	die('PDO connection failed: ' . $e->getMessage());
}

?>