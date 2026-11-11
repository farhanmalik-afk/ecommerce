<?php
session_start();
include "../../config.php";

$error = '';
$field_errors = [];
$order_added_success = false;
$default_order_no = 'ORD-' . strtoupper(substr(uniqid(), -6));
$form_values = [
    'customer_id' => '',
    'subtotal' => '0.00',
    'discount' => '0.00',
    'shipping_fee' => '0.00',
    'shipping_address' => '',
    'status' => 'Delivered',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($form_values as $field => $default_value) {
        $posted_value = $_POST[$field] ?? $default_value;
        $form_values[$field] = is_scalar($posted_value) ? trim((string)$posted_value) : '';
    }

    $customer_id = filter_var($form_values['customer_id'], FILTER_VALIDATE_INT);
    if ($customer_id === false || $customer_id <= 0) {
        $field_errors['customer_id'] = 'Please select a customer.';
    } else {
        $customer_stmt = $conn->prepare("SELECT id FROM customers WHERE id = ? AND is_deleted = 0");
        $customer_stmt->bind_param("i", $customer_id);
        $customer_stmt->execute();
        if ($customer_stmt->get_result()->num_rows === 0) {
            $field_errors['customer_id'] = 'Please select a valid customer.';
        }
        $customer_stmt->close();
    }

    $amounts = [];
    foreach (['subtotal' => 'Subtotal', 'discount' => 'Discount', 'shipping_fee' => 'Shipping fee'] as $field => $label) {
        $value = $form_values[$field];
        if ($value === '' || !is_numeric($value) || !is_finite((float)$value) || (float)$value < 0) {
            $field_errors[$field] = $label . ' must be a valid amount greater than or equal to zero.';
        } else {
            $amounts[$field] = (float)$value;
        }
    }

    if ($form_values['shipping_address'] === '') {
        $field_errors['shipping_address'] = 'Please enter the shipping address.';
    }

    $allowed_statuses = ['Delivered'];
    if (!in_array($form_values['status'], $allowed_statuses, true)) {
        $field_errors['status'] = 'Please select a valid order status.';
    }

    if (empty($field_errors)) {
        $order_no = $default_order_no;
        $subtotal = $amounts['subtotal'];
        $discount = $amounts['discount'];
        $shipping_fee = $amounts['shipping_fee'];
        $total = max(0, ($subtotal - $discount) + $shipping_fee);
        $shipping_address = $form_values['shipping_address'];
        $status = $form_values['status'];
        $stmt = $conn->prepare("INSERT INTO orders (order_no, customer_id, subtotal, discount, shipping_fee, total, shipping_address, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

        if ($stmt) {
            $stmt->bind_param("siddddss", $order_no, $customer_id, $subtotal, $discount, $shipping_fee, $total, $shipping_address, $status);
            if ($stmt->execute()) {
                $order_added_success = true;
                createAdminNotification(
                    $conn,
                    'New order received',
                    'Order ' . $order_no . ' has been created.',
                    'order'
                );
            } else {
                $error = "Database Error: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error = "Database Error: " . $conn->error;
        }
    }

    if (!$order_added_success && empty($error) && !empty($field_errors)) {
        $error = 'Please correct the highlighted fields.';
    }
}

$pageTitle = 'Add New Order';
include "../../layouts/header.php";
?>

<!-- SweetAlert2 CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="app-main">
    <section class="page-head mb-4">
        <h1>Receive & Create New Order</h1>
        <p class="text-muted">Manually process and record a new customer order.</p>
    </section>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if ($order_added_success): ?>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                Swal.fire({
                    icon: 'success',
                    title: 'Order Created!',
                    text: 'The new order has been successfully saved.',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    window.location.href = 'orders.php';
                });
            });
        </script>
    <?php endif; ?>

    <div class="card border-0 shadow-sm col-lg-8 mx-auto">
        <div class="card-body p-4">
            <form action="add-order.php" method="POST" novalidate>
                <div class="mb-3">
                    <label class="form-label font-weight-bold">Order Number</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($default_order_no, ENT_QUOTES, 'UTF-8'); ?>" readonly>
                </div>

                <div class="mb-3">
                    <label for="customer_id" class="form-label font-weight-bold">Select Customer</label>
                    <select name="customer_id" id="customer_id" class="form-select<?php echo isset($field_errors['customer_id']) ? ' is-invalid' : ''; ?>" aria-describedby="customer_id_error" aria-invalid="<?php echo isset($field_errors['customer_id']) ? 'true' : 'false'; ?>">
                        <option value="">-- Choose Customer --</option>
                        <?php
                        $cust_res = $conn->query("SELECT id, name, email FROM customers WHERE is_deleted = 0");
                        while ($c = $cust_res->fetch_assoc()) {
                            $selected = (string)$c['id'] === $form_values['customer_id'] ? ' selected' : '';
                            echo "<option value='" . (int)$c['id'] . "'" . $selected . ">" . htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') . " (" . htmlspecialchars($c['email'], ENT_QUOTES, 'UTF-8') . ")</option>";
                        }
                        ?>
                    </select>
                    <div id="customer_id_error" class="invalid-feedback"<?php echo isset($field_errors['customer_id']) ? '' : ' hidden'; ?>><?php echo htmlspecialchars($field_errors['customer_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="subtotal" class="form-label">Subtotal ($)</label>
                        <input type="number" step="0.01" min="0" name="subtotal" id="subtotal" class="form-control decimal-input<?php echo isset($field_errors['subtotal']) ? ' is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($form_values['subtotal'], ENT_QUOTES, 'UTF-8'); ?>" aria-describedby="subtotal_error" aria-invalid="<?php echo isset($field_errors['subtotal']) ? 'true' : 'false'; ?>">
                        <div id="subtotal_error" class="invalid-feedback"<?php echo isset($field_errors['subtotal']) ? '' : ' hidden'; ?>><?php echo htmlspecialchars($field_errors['subtotal'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="discount" class="form-label">Discount ($)</label>
                        <input type="number" step="0.01" min="0" name="discount" id="discount" class="form-control decimal-input<?php echo isset($field_errors['discount']) ? ' is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($form_values['discount'], ENT_QUOTES, 'UTF-8'); ?>" aria-describedby="discount_error" aria-invalid="<?php echo isset($field_errors['discount']) ? 'true' : 'false'; ?>">
                        <div id="discount_error" class="invalid-feedback"<?php echo isset($field_errors['discount']) ? '' : ' hidden'; ?>><?php echo htmlspecialchars($field_errors['discount'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="shipping_fee" class="form-label">Shipping Fee ($)</label>
                        <input type="number" step="0.01" min="0" name="shipping_fee" id="shipping_fee" class="form-control decimal-input<?php echo isset($field_errors['shipping_fee']) ? ' is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($form_values['shipping_fee'], ENT_QUOTES, 'UTF-8'); ?>" aria-describedby="shipping_fee_error" aria-invalid="<?php echo isset($field_errors['shipping_fee']) ? 'true' : 'false'; ?>">
                        <div id="shipping_fee_error" class="invalid-feedback"<?php echo isset($field_errors['shipping_fee']) ? '' : ' hidden'; ?>><?php echo htmlspecialchars($field_errors['shipping_fee'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label font-weight-bold">Total Amount ($)</label>
                    <input type="number" step="0.01" min="0" id="total" class="form-control bg-light" value="0.00" readonly>
                </div>

                <div class="mb-3">
                    <label for="shipping_address" class="form-label">Shipping Address</label>
                    <textarea name="shipping_address" id="shipping_address" class="form-control<?php echo isset($field_errors['shipping_address']) ? ' is-invalid' : ''; ?>" rows="3" placeholder="Enter full delivery address..." aria-describedby="shipping_address_error" aria-invalid="<?php echo isset($field_errors['shipping_address']) ? 'true' : 'false'; ?>"><?php echo htmlspecialchars($form_values['shipping_address'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                    <div id="shipping_address_error" class="invalid-feedback"<?php echo isset($field_errors['shipping_address']) ? '' : ' hidden'; ?>><?php echo htmlspecialchars($field_errors['shipping_address'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                </div>

                <div class="mb-4">
                    <label class="form-label">Order Status</label>
                    <select name="status" class="form-select<?php echo isset($field_errors['status']) ? ' is-invalid' : ''; ?>" aria-describedby="status_error" aria-invalid="<?php echo isset($field_errors['status']) ? 'true' : 'false'; ?>">
                        <option value="Delivered" selected>✓ Delivered</option>
                    </select>
                    <div id="status_error" class="invalid-feedback"<?php echo isset($field_errors['status']) ? '' : ' hidden'; ?>><?php echo htmlspecialchars($field_errors['status'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                </div>

                <div class="d-flex justify-content-between">
                    <a href="orders.php" class="btn btn-secondary">Back to Orders</a>
                    <button type="submit" class="btn btn-dark px-4">Save Order</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const orderForm = document.querySelector('form[action="add-order.php"]');
const requiredOrderFields = [
    document.getElementById('customer_id'),
    document.getElementById('subtotal'),
    document.getElementById('discount'),
    document.getElementById('shipping_fee'),
    document.getElementById('shipping_address')
];

function validateOrderField(field) {
    const feedback = document.getElementById(field.id + '_error');
    let message = '';

    if (!field.value.trim()) {
        message = field.id === 'customer_id'
            ? 'Please select a customer.'
            : field.id === 'shipping_address'
                ? 'Please enter the shipping address.'
                : 'Please enter an amount.';
    } else if (field.type === 'number' && (!Number.isFinite(Number(field.value)) || Number(field.value) < 0)) {
        message = 'Enter a valid amount greater than or equal to zero.';
    }

    field.classList.toggle('is-invalid', Boolean(message));
    field.setAttribute('aria-invalid', String(Boolean(message)));
    if (feedback) {
        feedback.textContent = message;
        feedback.hidden = !message;
    }
    return !message;
}

function calculateTotal() {
    let subtotal = parseFloat(document.getElementById('subtotal').value) || 0;
    let discount = parseFloat(document.getElementById('discount').value) || 0;
    let shipping = parseFloat(document.getElementById('shipping_fee').value) || 0;
    
    let total = (subtotal - discount) + shipping;
    if (total < 0) total = 0;
    
    document.getElementById('total').value = total.toFixed(2);
}

document.addEventListener("DOMContentLoaded", function() {
    const decimalInputs = document.querySelectorAll('.decimal-input');
    
    calculateTotal();

    requiredOrderFields.forEach(field => {
        field.addEventListener(field.tagName === 'SELECT' ? 'change' : 'input', function() {
            validateOrderField(field);
        });
        field.addEventListener('change', function() {
            validateOrderField(field);
        });
        field.addEventListener('blur', function() {
            validateOrderField(field);
        });
    });

    orderForm.addEventListener('submit', function(event) {
        const invalidFields = requiredOrderFields.filter(field => !validateOrderField(field));
        if (invalidFields.length > 0) {
            event.preventDefault();
            invalidFields[0].focus();
            invalidFields[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });

    const serverInvalidField = orderForm.querySelector('.is-invalid');
    if (serverInvalidField) {
        serverInvalidField.focus();
        serverInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    decimalInputs.forEach(input => {
        input.addEventListener('keydown', function(e) {
            if (e.key === '-' || e.key === 'e') {
                e.preventDefault();
            }
        });

        input.addEventListener('focus', function() {
            if (this.value === '0.00') {
                this.value = '';
            }
        });

        input.addEventListener('input', function() {
            calculateTotal();
        });

        input.addEventListener('blur', function() {
            let val = parseFloat(this.value);
            if (!isNaN(val) && val >= 0 && this.value.trim() !== '') {
                this.value = val.toFixed(2);
            }
            validateOrderField(this);
            calculateTotal();
        });
    });
});
</script>

<?php include "../../layouts/footer.php"; ?>