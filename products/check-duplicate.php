<?php
include "../config.php";

if (isset($_POST['type']) && isset($_POST['value'])) {
    $type = $_POST['type']; // 'name' ya 'sku'
    $value = trim($_POST['value']);

    if (!empty($value)) {
        if ($type === 'name') {
            $stmt = $conn->prepare("SELECT id FROM products WHERE name = ?");
        } else if ($type === 'sku') {
            $stmt = $conn->prepare("SELECT id FROM products WHERE sku = ?");
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