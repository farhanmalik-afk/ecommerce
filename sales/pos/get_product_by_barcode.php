<?php
include "../../config.php";
header('Content-Type: application/json');

$barcode = trim($_GET['barcode'] ?? '');

if (!empty($barcode)) {
    // Agar input number hai toh ID ya SKU dono par check karega, warna sirf SKU par
    $stmt = $conn->prepare("SELECT id, name, price, stock, sku, image FROM products WHERE sku = ? OR id = ?");
    $stmt->bind_param("si", $barcode, $barcode);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        echo json_encode([
            'status' => 'success', 
            'product' => [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'price' => (float)$row['price'],
                'stock' => (int)($row['stock'] ?? 0),
                'sku' => $row['sku'],
                'image' => $row['image']
            ]
        ]);
        exit;
    }
}

echo json_encode(['status' => 'error', 'message' => 'Product not found']);
exit;
?>