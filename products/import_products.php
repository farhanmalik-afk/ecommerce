<?php
session_start();
include "../config.php";

if (isset($_POST['import_submit'])) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
        $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
        
        // Skip header row
        $header = fgetcsv($file); 

        $successCount = 0;
        while (($row = fgetcsv($file, 10000, ",")) !== FALSE) {
            // Check headers skip karne ke liye
            if (empty($row[2]) || $row[2] == 'SKU') {
                continue;
            }

            // CSV Mapping:
            // [0] ID, [1] Category ID, [2] SKU, [3] Name, [4] Description, [5] Price, [6] Original Price, [7] Discount, [8] Sale Price, [9] Stock, [10] Status, [11] Cost Price
            
            $raw_cat_id       = !empty($row[1]) ? intval($row[1]) : NULL;
            $sku              = mysqli_real_escape_string($conn, $row[2] ?? '');
            $name             = mysqli_real_escape_string($conn, $row[3] ?? '');
            $description      = mysqli_real_escape_string($conn, $row[4] ?? '');
            $price            = floatval($row[5] ?? 0);
            $original_price   = floatval($row[6] ?? 0);
            $discount_percent = intval($row[7] ?? 0);
            $sale_price       = floatval($row[8] ?? 0);
            $stock            = intval($row[9] ?? 0);
            
            // Status ko hamesha 'Active' rakha hai
            $status           = 'Active';
            
            $cost_price       = floatval($row[11] ?? 0);

            // Category ID check
            $cat_id = NULL;
            if ($raw_cat_id !== NULL) {
                $chk_cat = $conn->prepare("SELECT id FROM categories WHERE id = ?");
                $chk_cat->bind_param("i", $raw_cat_id);
                $chk_cat->execute();
                $cat_res = $chk_cat->get_result();
                if ($cat_res->num_rows > 0) {
                    $cat_id = $raw_cat_id;
                }
                $chk_cat->close();
            }

            if (!empty($name) && !empty($sku)) {
                // Insert or Update query
                $sql = "INSERT INTO products (category_id, sku, name, description, price, original_price, discount_percent, sale_price, stock, status, cost_price) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE 
                        category_id = VALUES(category_id),
                        name = VALUES(name),
                        description = VALUES(description),
                        price = VALUES(price),
                        original_price = VALUES(original_price),
                        discount_percent = VALUES(discount_percent),
                        sale_price = VALUES(sale_price),
                        stock = VALUES(stock),
                        status = 'Active',
                        cost_price = VALUES(cost_price)";
                
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("isssdddidds", $cat_id, $sku, $name, $description, $price, $original_price, $discount_percent, $sale_price, $stock, $status, $cost_price);
                    if ($stmt->execute()) {
                        $successCount++;
                    }
                    $stmt->close();
                }
            }
        }
        fclose($file);

        // ⭐️ Jaise hi import khatam ho, yeh query automatically chal kar saare '0' ya blank status ko 'Active' kar degi ⭐️
        mysqli_query($conn, "UPDATE products SET status = 'Active' WHERE status = '0' OR status = '' OR status IS NULL");
        
        $_SESSION['swal_success'] = "$successCount products imported successfully and all statuses set to Active!";
        header("Location: products.php");
        exit();
    } else {
        $_SESSION['swal_error'] = "Please upload a valid CSV file!";
        header("Location: products.php");
        exit();
    }
}
?>