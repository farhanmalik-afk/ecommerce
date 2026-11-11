<?php
session_start();
include "../config.php";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=products_export_' . date('Y-m-d') . '.csv');

$output = fopen('php://output', 'w');

// CSV Headings matching your exact table columns
fputcsv($output, array('ID', 'Category ID', 'SKU', 'Name', 'Description', 'Price', 'Original Price', 'Discount Percent', 'Sale Price', 'Stock', 'Status', 'Cost Price'));

$query = "SELECT id, category_id, sku, name, description, price, original_price, discount_percent, sale_price, stock, status, cost_price FROM products";
$result = mysqli_query($conn, $query);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        fputcsv($output, array(
            $row['id'], 
            $row['category_id'], 
            $row['sku'], 
            $row['name'], 
            $row['description'], 
            $row['price'], 
            $row['original_price'], 
            $row['discount_percent'], 
            $row['sale_price'], 
            $row['stock'], 
            $row['status'],
            $row['cost_price']
        ));
    }
}
fclose($output);
exit();
?>