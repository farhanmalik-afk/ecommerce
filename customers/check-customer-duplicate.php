<?php
include "../config.php";

if (isset($_POST['type']) && isset($_POST['value'])) {
    $type = $_POST['type']; // 'email' ya 'phone'
    $value = trim($_POST['value']);

    if (!empty($value)) {
        if ($type === 'email') {
            $stmt = $conn->prepare("SELECT id FROM customers WHERE email = ?");
        } else if ($type === 'phone') {
            $stmt = $conn->prepare("SELECT id FROM customers WHERE phone = ?");
        }

        if (isset($stmt)) {
            $stmt->bind_param("s", $value);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                echo "exists"; // Agar pehle se mojood hai
            } else {
                echo "available"; // Agar unique hai
            }
            $stmt->close();
        }
    }
}
?>