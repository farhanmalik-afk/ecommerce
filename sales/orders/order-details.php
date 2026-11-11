<?php
session_start();
include "../../config.php";

$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

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

$items_query = $conn->prepare("SELECT oi.*, p.name AS catalog_product_name FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ? ORDER BY oi.id ASC");
$items_query->bind_param("i", $order_id);
$items_query->execute();
$items_result = $items_query->get_result();

$subtotal = (float)($order['subtotal'] ?? $order['total'] ?? 0);
$discount = (float)($order['discount'] ?? 0);
$shipping_fee = (float)($order['shipping_fee'] ?? 0);
$grand_total = (float)($order['total'] ?? 0);
$tax = max(0, $grand_total - ($subtotal - $discount + $shipping_fee));
$status = $order['status'] ?? 'Pending';
$status_badge = $status === 'Delivered' ? 'bg-success' : ($status === 'Cancelled' ? 'bg-danger' : 'bg-primary');

$pageTitle = 'Order Details ' . $display_order_id;
include "../../layouts/header.php";
?>

<div class="app-main">
    <section class="page-head mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h1>Order Details <?php echo $display_order_id; ?></h1>
            <p class="text-muted">Complete breakdown and shipping details for this order.</p>
        </div>
        <div>
            <a href="invoice.php?id=<?php echo $order['id']; ?>" class="btn btn-secondary btn-sm" target="_blank">
                <i class="bi bi-receipt me-1"></i> View Invoice
            </a>
            <a href="orders.php" class="btn btn-outline-dark btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to Orders
            </a>
        </div>
    </section>

    <div class="row">
        <!-- Customer & Order Info -->
        <div class="col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 font-weight-bold">Customer Information</h6>
                </div>
                <div class="card-body">
                    <p class="mb-2"><strong>Order ID:</strong> <span class="text-primary fw-bold"><?php echo htmlspecialchars($display_order_id, ENT_QUOTES, 'UTF-8'); ?></span></p>
                    <p class="mb-2"><strong>Customer Name:</strong> <?php echo htmlspecialchars($order['customer_name'] ?? 'Guest Customer'); ?></p>
                    <p class="mb-2"><strong>Email:</strong> <?php echo htmlspecialchars($order['customer_email'] ?? 'N/A'); ?></p>
                    <p class="mb-2"><strong>Phone:</strong> <?php echo htmlspecialchars($order['customer_phone'] ?? 'N/A'); ?></p>
                    <p class="mb-0"><strong>Payment Method:</strong> <?php echo htmlspecialchars($order['payment_method'] ?? 'N/A'); ?></p>
                </div>
            </div>
        </div>

        <div class="col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 font-weight-bold">Shipping & Metadata</h6>
                </div>
                <div class="card-body">
                    <p class="mb-2"><strong>Shipping Address:</strong> <?php echo htmlspecialchars($order['shipping_address'] ?? 'N/A'); ?></p>
                    <p class="mb-2"><strong>Order Date:</strong> <?php echo !empty($order['created_at']) ? date('F d, Y - h:i A', strtotime($order['created_at'])) : 'N/A'; ?></p>
                    <p class="mb-0"><strong>Current Status:</strong> <span class="badge <?php echo $status_badge; ?>"><?php echo htmlspecialchars($status); ?></span></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Order Items Breakdown -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h6 class="m-0 font-weight-bold">Ordered Items</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Product Name</th>
                            <th class="text-center">Quantity</th>
                            <th class="text-end">Unit Price</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($items_result && $items_result->num_rows > 0): ?>
                            <?php while ($item = $items_result->fetch_assoc()):
                                $item_name = !empty($item['product_name']) ? $item['product_name'] : (!empty($item['catalog_product_name']) ? $item['catalog_product_name'] : 'Product Item');
                                $quantity = max(1, (int)($item['quantity'] ?? 1));
                                $unit_price = (float)($item['unit_price'] ?? $item['price'] ?? 0);
                                $line_total = (float)($item['line_total'] ?? $unit_price * $quantity);
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item_name, ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="text-center"><?php echo $quantity; ?></td>
                                    <td class="text-end"><?php echo htmlspecialchars(formatAppCurrency($unit_price), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="text-end"><?php echo htmlspecialchars(formatAppCurrency($line_total), ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">No product items found for this order.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Financial Summary -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h6 class="m-0 font-weight-bold">Financial Summary</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered mb-0">
                    <tbody>
                        <tr>
                            <td><strong>Subtotal</strong></td>
                            <td class="text-end"><?php echo htmlspecialchars(formatAppCurrency($subtotal), ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                        <?php if ($discount > 0): ?>
                        <tr>
                            <td><strong>Discount</strong></td>
                            <td class="text-end">−<?php echo htmlspecialchars(formatAppCurrency($discount), ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($shipping_fee > 0): ?>
                        <tr>
                            <td><strong>Shipping Fee</strong></td>
                            <td class="text-end"><?php echo htmlspecialchars(formatAppCurrency($shipping_fee), ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($tax > 0): ?>
                        <tr>
                            <td><strong>Tax</strong></td>
                            <td class="text-end"><?php echo htmlspecialchars(formatAppCurrency($tax), ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr class="table-active">
                            <td><strong>Grand Total</strong></td>
                            <td class="text-end text-success"><strong><?php echo htmlspecialchars(formatAppCurrency($grand_total), ENT_QUOTES, 'UTF-8'); ?></strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include "../../layouts/footer.php"; ?>