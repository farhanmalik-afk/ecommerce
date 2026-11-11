<?php
error_reporting(0);
include "../config.php";
header('Content-Type: application/json');

$email = trim($_GET['email'] ?? '');
if (!empty($email)) {
    $stmt = $conn->prepare("SELECT id FROM customers WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    
    $exists = ($stmt->num_rows > 0);
    echo json_encode(['exists' => $exists]);
    
    $stmt->close();
} else {
    echo json_encode(['exists' => false]);
}
?>