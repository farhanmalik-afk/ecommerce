<?php
session_start();
include "../../config.php";

$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Order details fetch karna
$order_query = $conn->prepare("SELECT o.*, c.name as customer_name, c.email as customer_email, c.phone as customer_phone FROM orders o LEFT JOIN customers c ON o.customer_id = c.id WHERE o.id = ?");
$order_query->bind_param("i", $order_id);
$order_query->execute();
$order_result = $order_query->get_result();

if ($order_result->num_rows === 0) {
    header("Location: orders.php");
    exit();
}
$order = $order_result->fetch_assoc();
$display_order_id = !empty($order['order_no']) ? $order['order_no'] : '#ORD-' . str_pad($order['id'], 4, '0', STR_PAD_LEFT);

// Order items fetch karna (Products table se join karke asli naam aur details ke liye)
$items_query = $conn->prepare("SELECT oi.*, p.name as prod_name FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
$items_query->bind_param("i", $order_id);
$items_query->execute();
$items_result = $items_query->get_result();
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(($appSettings['language'] ?? 'en') === 'ur' ? 'ur' : 'en', ENT_QUOTES, 'UTF-8') ?>"<?= ($appSettings['language'] ?? 'en') === 'ur' ? ' dir="rtl"' : '' ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?php echo $display_order_id; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        body { background: #f5f0e6; font-family: 'Inter', sans-serif; color: #263449; }
        .invoice-box { max-width: 800px; margin: 30px auto; background: #fff; padding: 40px; border: 1px solid #e8e1d5; border-radius: 12px; box-shadow: 0 12px 30px rgba(20,36,61,0.08); }
        .invoice-box h2 { font-family: 'Playfair Display', Georgia, serif; color: #14243d !important; }
        .invoice-box h4 { color: #14243d !important; }
        .invoice-box hr { border-color: #c6a15b; opacity: 0.5; }
        @media print {
            body { background: #fff; }
            .invoice-box { box-shadow: none; border: 0; margin: 0; padding: 20px; width: 100%; max-width: 100%; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body class="theme-<?= htmlspecialchars($appSettings['theme'] ?? 'luxury', ENT_QUOTES, 'UTF-8') ?>" data-bs-theme="<?= ($appSettings['theme'] ?? '') === 'dark' ? 'dark' : 'light' ?>">

<div class="container my-4">
    <!-- Search / Filter Bar (No-Print) -->
    <div class="row mb-3 no-print">
        <div class="col-md-6 mx-auto">
            <form action="orders.php" method="GET" class="input-group shadow-sm">
                <input type="text" name="search" class="form-control" placeholder="Search order by ID or Customer..." required>
                <button class="btn btn-dark" type="submit">Search Order</button>
            </form>
        </div>
    </div>

    <div class="invoice-box">
        <!-- Invoice Header -->
        <div class="row mb-4">
            <div class="col-6">
                <h2 class="text-dark fw-bold">INVOICE</h2>
                <p class="text-muted mb-0">Order ID: <strong><?php echo $display_order_id; ?></strong></p>
                
                <p class="text-muted mb-1">
                    Date: <?php echo (!empty($order['created_at']) && $order['created_at'] != '0000-00-00 00:00:00') ? date('M d, Y - h:i A', strtotime($order['created_at'])) : date('M d, Y'); ?>
                </p>
                
                <p class="text-muted mb-0">
                    Payment Method: <span class="badge bg-dark text-white"><?php echo htmlspecialchars($order['payment_method'] ?? 'Cash'); ?></span>
                </p>
            </div>
            <div class="col-6 text-end">
                <?php if (!empty($appSettings['logo'])): ?>
                    <img src="../../uploads/<?= rawurlencode($appSettings['logo']) ?>" alt="Dashboard logo" style="max-width:180px;max-height:60px;object-fit:contain">
                <?php else: ?>
                    <h4 class="fw-bold text-primary">WebCraft Agency</h4>
                <?php endif; ?>
                <p class="text-muted small mb-0">Khushab, Punjab, Pakistan</p>
                <p class="text-muted small">support@webcraft.com</p>
            </div>
        </div>

        <hr class="my-4">

        <!-- Client & Shipping Details -->
        <div class="row mb-4">
            <div class="col-6">
                <h6 class="text-uppercase text-muted fw-bold small">Billed To:</h6>
                <h5 class="mb-1"><?php echo htmlspecialchars($order['customer_name'] ?? 'Guest Customer'); ?></h5>
                <p class="mb-1 text-muted"><?php echo htmlspecialchars($order['customer_email'] ?? 'N/A'); ?></p>
                <p class="mb-0 text-muted"><?php echo htmlspecialchars($order['customer_phone'] ?? 'N/A'); ?></p>
            </div>
            <div class="col-6 text-end">
                <h6 class="text-uppercase text-muted fw-bold small">Shipping Address:</h6>
                <p class="mb-0 text-muted"><?php echo htmlspecialchars($order['shipping_address'] ?? 'N/A'); ?></p>
            </div>
        </div>

        <!-- Items Table (Actual Products List) -->
        <div class="table-responsive mb-4">
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>Item Description</th>
                        <th class="text-center">Qty</th>
                        <th class="text-end">Unit Price</th>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($items_result && $items_result->num_rows > 0): ?>
                        <?php while ($item = $items_result->fetch_assoc()): 
                            $item_name = !empty($item['product_name']) ? $item['product_name'] : ($item['prod_name'] ?? 'Product Item');
                            $unit_price = floatval($item['unit_price'] ?? $item['price'] ?? 0);
                            $quantity = intval($item['quantity'] ?? 1);
                            $line_total = floatval($item['line_total'] ?? ($unit_price * $quantity));
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item_name); ?></td>
                                <td class="text-center"><?php echo $quantity; ?></td>
                                <td class="text-end"><?php echo htmlspecialchars(formatAppCurrency($unit_price), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="text-end"><?php echo htmlspecialchars(formatAppCurrency($line_total), ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted">No items found for this order.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="text-end fw-bold">Subtotal:</td>
                        <td class="text-end fw-bold"><?php echo htmlspecialchars(formatAppCurrency($order['subtotal'] ?? $order['total'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                    <?php if (!empty($order['shipping_fee']) && $order['shipping_fee'] > 0): ?>
                    <tr>
                        <td colspan="3" class="text-end fw-bold">Shipping Fee:</td>
                        <td class="text-end fw-bold"><?php echo htmlspecialchars(formatAppCurrency($order['shipping_fee']), ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td colspan="3" class="text-end fw-bold fs-6">Grand Total:</td>
                        <td class="text-end text-success fw-bold fs-6"><?php echo htmlspecialchars(formatAppCurrency($order['total'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Action Buttons -->
        <div class="d-flex justify-content-between no-print">
            <a href="orders.php" class="btn btn-outline-secondary btn-sm">Back to Orders</a>
            <button onclick="window.print()" class="btn btn-dark btn-sm">Print Invoice</button>
        </div>
    </div>
</div>

</body>
</html>