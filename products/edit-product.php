<?php
session_start();
include "../config.php";

if (!isset($_GET['id'])) {
    header("Location: products.php");
    exit();
}

$id = intval($_GET['id']);
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    header("Location: products.php");
    exit();
}
$product = $result->fetch_assoc();
$stmt->close();
$formValues = [
    'name' => (string) ($product['name'] ?? ''),
    'sku' => (string) ($product['sku'] ?? ''),
    'category_id' => (string) ($product['category_id'] ?? ''),
    'price' => (string) ($product['price'] ?? $product['sale_price'] ?? ''),
    'stock' => (string) ($product['stock'] ?? '0'),
    'status' => (string) ($product['status'] ?? 'Active'),
    'description' => (string) ($product['description'] ?? ''),
];
$formErrors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    foreach ($formValues as $field => $value) {
        $postedValue = $_POST[$field] ?? $value;
        $formValues[$field] = is_string($postedValue) ? trim($postedValue) : $value;
    }

    $name = $formValues['name'];
    $submittedSku = $formValues['sku'];
    $sku = $submittedSku !== '' ? $submittedSku : (string) ($product['sku'] ?? '');
    $categoryId = filter_var($formValues['category_id'], FILTER_VALIDATE_INT);
    $price = $formValues['price'];
    $stock = $formValues['stock'];
    $status = $formValues['status'];
    $description = $formValues['description'];

    if ($name === '') {
        $formErrors['name'] = 'Product name is required.';
    } elseif (mb_strlen($name) > 255) {
        $formErrors['name'] = 'Product name must be 255 characters or fewer.';
    } else {
        $nameCheck = $conn->prepare('SELECT id FROM products WHERE name = ? AND id <> ? LIMIT 1');
        $nameCheck->bind_param('si', $name, $id);
        $nameCheck->execute();
        if ($nameCheck->get_result()->num_rows > 0) {
            $formErrors['name'] = 'A product with this name already exists.';
        }
        $nameCheck->close();
    }

    if ($submittedSku !== '' && mb_strlen($submittedSku) > 50) {
        $formErrors['sku'] = 'SKU must be 50 characters or fewer.';
    } elseif ($submittedSku !== '') {
        $skuCheck = $conn->prepare('SELECT id FROM products WHERE sku = ? AND id <> ? LIMIT 1');
        $skuCheck->bind_param('si', $submittedSku, $id);
        $skuCheck->execute();
        if ($skuCheck->get_result()->num_rows > 0) {
            $formErrors['sku'] = 'This SKU is already assigned to another product.';
        }
        $skuCheck->close();
    }

    if ($categoryId === false || $categoryId < 1) {
        $formErrors['category_id'] = 'Please select a category.';
    } else {
        $categoryCheck = $conn->prepare("SELECT id FROM categories WHERE id = ? AND status = 'Active' LIMIT 1");
        $categoryCheck->bind_param('i', $categoryId);
        $categoryCheck->execute();
        if ($categoryCheck->get_result()->num_rows === 0) {
            $formErrors['category_id'] = 'Please select an available category.';
        }
        $categoryCheck->close();
    }

    if ($price === '' || !is_numeric($price) || !is_finite((float) $price) || (float) $price < 0) {
        $formErrors['price'] = 'Enter a valid price of zero or more.';
    }

    if ($stock === '' || !ctype_digit($stock) || (float) $stock > PHP_INT_MAX) {
        $formErrors['stock'] = 'Enter a whole stock quantity of zero or more.';
    }

    if (!in_array($status, ['Active', 'Draft', 'Archived'], true)) {
        $formErrors['status'] = 'Please select a valid product status.';
    }

    $image = (string) ($product['image'] ?? '');
    $uploadedImagePath = null;
    $uploadedFile = $_FILES['image'] ?? null;
    if ($uploadedFile && $uploadedFile['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
            $formErrors['image'] = 'The image upload failed. Please try again.';
        } elseif ($uploadedFile['size'] > 2 * 1024 * 1024) {
            $formErrors['image'] = 'Product images must be 2 MB or smaller.';
        } else {
            $extension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                $formErrors['image'] = 'Choose a JPG, PNG, or WebP image.';
            } elseif (@getimagesize($uploadedFile['tmp_name']) === false) {
                $formErrors['image'] = 'The selected file is not a valid image.';
            }
        }
    }

    if (!$formErrors && $uploadedFile && $uploadedFile['error'] === UPLOAD_ERR_OK) {
        $target_dir = "../uploads/";
        if (!is_dir($target_dir)) { mkdir($target_dir, 0755, true); }

        $image_name = bin2hex(random_bytes(8)) . '.' . strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
        $target_file = $target_dir . $image_name;
        if (move_uploaded_file($uploadedFile['tmp_name'], $target_file)) {
            $image = $image_name;
            $uploadedImagePath = $target_file;
        } else {
            $formErrors['image'] = 'The image could not be saved. Please try again.';
        }
    }

    if (!$formErrors) {
        try {
            $priceValue = (float) $price;
            $stockValue = (int) $stock;
            $previousStock = (int) ($product['stock'] ?? 0);
            $stmt = $conn->prepare("UPDATE products SET name=?, sku=?, category_id=?, price=?, stock=?, status=?, description=?, image=? WHERE id=?");
            $stmt->bind_param('ssidssssi', $name, $sku, $categoryId, $priceValue, $stockValue, $status, $description, $image, $id);
            $stmt->execute();
            $stmt->close();
            notifyAdminStockTransition($conn, $name, $previousStock, $stockValue);

            $_SESSION['swal_success'] = 'Product updated successfully!';
            header('Location: products.php');
            exit();
        } catch (mysqli_sql_exception $e) {
            error_log('Product update failed: ' . $e->getMessage());
            if ($uploadedImagePath !== null && is_file($uploadedImagePath)) {
                unlink($uploadedImagePath);
            }
            if ((int) $e->getCode() === 1062) {
                $formErrors['sku'] = 'This SKU is already assigned to another product.';
            } else {
                $formErrors['form'] = 'Product update failed. Please try again.';
            }
        }
    }
}

$pageTitle = 'Edit Product';
$parent = ['Products', 'products.php'];
include __DIR__ . '/../layouts/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<section class="page-head mb-4">
    <h1>Edit Product</h1>
    <p class="text-muted">Update product information.</p>
</section>

<div class="card border-0 shadow-sm p-4">
    <form action="" method="POST" enctype="multipart/form-data" id="editProductForm" novalidate>
        <?php if (isset($formErrors['form'])): ?>
            <div class="alert alert-danger" role="alert"><?= htmlspecialchars($formErrors['form'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Product Name *</label>
                <input type="text" name="name" class="form-control<?= isset($formErrors['name']) ? ' is-invalid' : '' ?>" value="<?= htmlspecialchars($formValues['name'], ENT_QUOTES, 'UTF-8') ?>" maxlength="255" required aria-describedby="name-error">
                <div class="invalid-feedback" id="name-error"><?= htmlspecialchars($formErrors['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div class="col-md-6">
                <label class="form-label">SKU</label>
                <input type="text" name="sku" class="form-control<?= isset($formErrors['sku']) ? ' is-invalid' : '' ?>" value="<?= htmlspecialchars($formValues['sku'], ENT_QUOTES, 'UTF-8') ?>" maxlength="50" aria-describedby="sku-help sku-error">
                <div class="form-text" id="sku-help">Leave blank to keep the current SKU.</div>
                <div class="invalid-feedback" id="sku-error"><?= htmlspecialchars($formErrors['sku'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div class="col-md-4">
    <label class="form-label fw-bold">Category *</label>
    <select name="category_id" class="form-select<?= isset($formErrors['category_id']) ? ' is-invalid' : '' ?>" required aria-describedby="category-error">
        <option value="">-- Choose Category --</option>
        <?php
        $cat_result = $conn->query("SELECT id, name FROM categories WHERE status = 'Active'");
        while ($cat = $cat_result->fetch_assoc()) {
            $selected = ($formValues['category_id'] === (string) $cat['id']) ? 'selected' : '';
            echo "<option value='" . (int) $cat['id'] . "' {$selected}>" . htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') . "</option>";
        }
        ?>
    </select>
    <div class="invalid-feedback" id="category-error"><?= htmlspecialchars($formErrors['category_id'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
</div>
            <div class="col-md-4">
                <label class="form-label">Price ($) *</label>
                <input type="number" min="0" step="0.01" name="price" class="form-control<?= isset($formErrors['price']) ? ' is-invalid' : '' ?>" value="<?= htmlspecialchars($formValues['price'], ENT_QUOTES, 'UTF-8') ?>" required aria-describedby="price-error">
                <div class="invalid-feedback" id="price-error"><?= htmlspecialchars($formErrors['price'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Stock Quantity *</label>
                <input type="number" min="0" step="1" name="stock" class="form-control<?= isset($formErrors['stock']) ? ' is-invalid' : '' ?>" value="<?= htmlspecialchars($formValues['stock'], ENT_QUOTES, 'UTF-8') ?>" required aria-describedby="stock-error">
                <div class="invalid-feedback" id="stock-error"><?= htmlspecialchars($formErrors['stock'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Status</label>
                <select name="status" class="form-select<?= isset($formErrors['status']) ? ' is-invalid' : '' ?>" required aria-describedby="status-error">
                    <option value="Active" <?= $formValues['status'] === 'Active' ? 'selected' : '' ?>>Active</option>
                    <option value="Draft" <?= $formValues['status'] === 'Draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="Archived" <?= $formValues['status'] === 'Archived' ? 'selected' : '' ?>>Archived</option>
                </select>
                <div class="invalid-feedback" id="status-error"><?= htmlspecialchars($formErrors['status'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Product Image</label>
                <input type="file" name="image" class="form-control<?= isset($formErrors['image']) ? ' is-invalid' : '' ?>" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" aria-describedby="image-error">
                <?php if(!empty($product['image'])): ?>
                    <small class="text-muted">Current Image: <?= htmlspecialchars($product['image'], ENT_QUOTES, 'UTF-8') ?></small>
                <?php endif; ?>
                <div class="invalid-feedback" id="image-error"><?= htmlspecialchars($formErrors['image'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div class="col-12">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="3" maxlength="5000"><?= htmlspecialchars($formValues['description'], ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
            <div class="col-12 mt-4">
                <button type="submit" class="btn btn-dark">Update Product</button>
                <a href="products.php" class="btn btn-outline-secondary ms-2">Cancel</a>
            </div>
        </div>
    </form>
</div>

<script>
(() => {
    const form = document.getElementById('editProductForm');
    const fields = {
        name: {
            validate: (value) => value.trim() ? '' : 'Product name is required.'
        },
        sku: {
            validate: (value) => value.length <= 50 ? '' : 'SKU must be 50 characters or fewer.'
        },
        category_id: {
            validate: (value) => value ? '' : 'Please select a category.'
        },
        price: {
            validate: (value, input) => {
                if (!value) return 'Price is required.';
                if (input.validity.badInput || !Number.isFinite(Number(value)) || Number(value) < 0) {
                    return 'Enter a valid price of zero or more.';
                }
                return '';
            }
        },
        stock: {
            validate: (value, input) => {
                if (!value) return 'Stock quantity is required.';
                if (input.validity.badInput || !/^\d+$/.test(value) || !Number.isSafeInteger(Number(value))) {
                    return 'Enter a whole stock quantity of zero or more.';
                }
                return '';
            }
        },
        status: {
            validate: (value) => ['Active', 'Draft', 'Archived'].includes(value) ? '' : 'Please select a valid product status.'
        },
        image: {
            validate: (value, input) => {
                const file = input.files[0];
                if (!file) return '';
                if (file.size > 2 * 1024 * 1024) return 'Product images must be 2 MB or smaller.';
                if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                    return 'Choose a JPG, PNG, or WebP image.';
                }
                return '';
            }
        }
    };

    const validateField = (name, showEmpty = false) => {
        const input = form.elements.namedItem(name);
        const message = document.getElementById(`${name}-error`);
        if (!input || !message) return true;

        const error = fields[name].validate(input.value, input);
        input.setCustomValidity(error);
        input.classList.toggle('is-invalid', Boolean(error));
        input.classList.toggle('is-valid', !error && (showEmpty || input.value !== ''));
        input.setAttribute('aria-invalid', String(Boolean(error)));
        message.textContent = error;
        return !error;
    };

    Object.keys(fields).forEach((name) => {
        const input = form.elements.namedItem(name);
        if (!input) return;
        ['input', 'change', 'blur'].forEach((eventName) => {
            input.addEventListener(eventName, () => validateField(name));
        });
    });

    form.addEventListener('submit', (event) => {
        const invalidFields = Object.keys(fields)
            .filter((name) => !validateField(name, true))
            .map((name) => form.elements.namedItem(name));

        if (invalidFields.length) {
            event.preventDefault();
            invalidFields[0].focus();
            invalidFields[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });

    const firstServerError = form.querySelector('.is-invalid');
    if (firstServerError) {
        firstServerError.focus();
        firstServerError.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
})();
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>