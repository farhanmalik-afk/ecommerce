<?php
session_start();
include "../config.php";

$error_msg = "";

// Variables to retain form input values (Sticky Form)
$name            = $_POST['name'] ?? '';
$sku             = $_POST['sku'] ?? '';
$category_id     = $_POST['category_id'] ?? '';
$price           = $_POST['price'] ?? '';
$original_price  = $_POST['original_price'] ?? '';
$discount_percent = $_POST['discount_percent'] ?? '';
$sale_price      = $_POST['sale_price'] ?? '0.00';
$stock           = $_POST['stock'] ?? '';
$status          = $_POST['status'] ?? 'Active';
$desc            = $_POST['description'] ?? '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name             = trim($name);
    $sku              = trim($sku);
    $category_id      = !empty($category_id) ? intval($category_id) : null;
    $price            = trim($price);
    
    // Agar fields empty hon toh default 0 set karein taake negative/empty error na aaye
    $original_price   = ($_POST['original_price'] !== '') ? floatval($_POST['original_price']) : 0.00;
    $discount_percent = ($_POST['discount_percent'] !== '') ? floatval($_POST['discount_percent']) : 0.00;
    $sale_price       = ($_POST['sale_price'] !== '') ? floatval($_POST['sale_price']) : 0.00;
    $stock            = trim($stock);
    $status           = trim($status);
    $desc             = trim($desc);
    
    // 1. PHP Backend Validation
    if (empty($name)) {
        $error_msg = "Product name is required!";
    } elseif (empty($category_id)) {
        $error_msg = "Please select a product category!";
    } elseif ($price === "" || !is_numeric($price) || floatval($price) < 0) {
        $error_msg = "Price must be a valid positive number!";
    } elseif ($original_price < 0) {
        $error_msg = "Original price cannot be negative.";
    } elseif ($discount_percent < 0 || $discount_percent > 100) {
        $error_msg = "Discount percentage must be between 0 and 100.";
    } elseif ($stock === "" || !is_numeric($stock) || intval($stock) < 0) {
        $error_msg = "Stock quantity cannot be negative!";
    } else {
        $price = floatval($price);
        $stock = intval($stock);
    }

    // 2. Database Duplicate Check (Product Name & SKU)
    if (empty($error_msg)) {
        $check_name = $conn->prepare("SELECT id FROM products WHERE name = ?");
        $check_name->bind_param("s", $name);
        $check_name->execute();
        $check_name->store_result();

        if ($check_name->num_rows > 0) {
            $error_msg = "A product with this name already exists in the database!";
        }
        $check_name->close();

        if (empty($error_msg) && !empty($sku)) {
            $check_sku = $conn->prepare("SELECT id FROM products WHERE sku = ?");
            $check_sku->bind_param("s", $sku);
            $check_sku->execute();
            $check_sku->store_result();

            if ($check_sku->num_rows > 0) {
                $error_msg = "This SKU ('" . htmlspecialchars($sku) . "') already exists! Please use a different SKU.";
            }
            $check_sku->close();
        }
    }

    // 3. Image Upload Logic (Default fallback avatar agar upload na ho)
    $image = "https://cdn-icons-png.flaticon.com/512/1165/1165687.png";

    if (empty($error_msg) && isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $imageUpload = $_FILES['image'];
        if ($imageUpload['error'] !== UPLOAD_ERR_OK) {
            $error_msg = in_array($imageUpload['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? "Image size must be no larger than 2 MB."
                : "Failed to upload image file.";
        } elseif ($imageUpload['size'] > 2 * 1024 * 1024) {
            $error_msg = "Image size must be no larger than 2 MB.";
        } else {
            $target_dir = "../uploads/";
            if (!is_dir($target_dir)) { mkdir($target_dir, 0755, true); }

            $image_name = time() . "_" . basename($imageUpload["name"]);
            $target_file = $target_dir . $image_name;
            $fileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

            $allowed = array("jpg", "jpeg", "png", "webp");
            if (in_array($fileType, $allowed)) {
                if (move_uploaded_file($imageUpload["tmp_name"], $target_file)) {
                    $image = $image_name; 
                } else {
                    $error_msg = "Failed to upload image file.";
                }
            } else {
                $error_msg = "Only JPG, JPEG, PNG & WEBP formats are allowed.";
            }
        }
    }

    // 4. Insert into Database
    if (empty($error_msg)) {
        $stmt = $conn->prepare("INSERT INTO products (name, sku, category_id, price, original_price, discount_percent, sale_price, stock, status, description, image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->bind_param("ssiddddisss", $name, $sku, $category_id, $price, $original_price, $discount_percent, $sale_price, $stock, $status, $desc, $image);
        
        try {
            if ($stmt->execute()) {
                createAdminNotification(
                    $conn,
                    'New product added',
                    $name . ' has been added to the product catalog.',
                    'stock'
                );
                notifyAdminStockTransition($conn, $name, 6, (int) $stock);
                $_SESSION['swal_success'] = "Product added successfully!";
                header("Location: products.php");
                exit();
            }
        } catch (mysqli_sql_exception $e) {
            $error_msg = "Database Error: " . $e->getMessage();
        }
        $stmt->close();
    }
}

$pageTitle = 'Add Product';
$parent = ['Products', 'products.php'];
include __DIR__ . '/../layouts/header.php';
?>

<!-- SweetAlert2 CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- jQuery CDN -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<section class="page-head mb-4">
    <h1>Add New Product</h1>
    <p class="text-muted">Fill in the product details with real-time field validation and automatic avatar assignment.</p>
</section>

<div class="card border-0 shadow-sm p-4">
    <form action="" method="POST" enctype="multipart/form-data" id="productForm" novalidate>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Product Name *</label>
                <input type="text" name="name" id="productName" class="form-control" value="<?php echo htmlspecialchars($name); ?>">
                <div class="invalid-feedback" id="nameFeedback">Product name is required.</div>
            </div>
            
            <div class="col-md-6">
                <label class="form-label">SKU</label>
                <input type="text" name="sku" id="productSku" class="form-control" value="<?php echo htmlspecialchars($sku); ?>">
                <div class="invalid-feedback" id="skuFeedback">This SKU already exists.</div>
            </div>

            <div class="col-md-4">
                <label class="form-label fw-bold">Category *</label>
                <select name="category_id" id="productCategory" class="form-select" required>
                    <option value="">-- Choose Category --</option>
                    <?php
                    $cat_result = $conn->query("SELECT id, name FROM categories WHERE status = 'Active'");
                    while ($cat = $cat_result->fetch_assoc()) {
                        $selected = ($category_id == $cat['id']) ? 'selected' : '';
                        echo "<option value='{$cat['id']}' {$selected}>" . htmlspecialchars($cat['name']) . "</option>";
                    }
                    ?>
                </select>
                <div class="invalid-feedback">Please select a category.</div>
            </div>

            <div class="col-md-4">
                <label class="form-label">Price ($) *</label>
                <input type="number" min="0" step="0.01" onkeydown="return event.key !== '-' && event.key !== 'e' && event.key !== 'E'" name="price" id="productPrice" class="form-control clear-on-focus" value="<?php echo htmlspecialchars($price); ?>" placeholder="0.00">
                <div class="invalid-feedback" id="priceFeedback">Price must be a valid number.</div>
            </div>

            <div class="col-md-4">
                <label class="form-label">Stock Quantity *</label>
                <input type="number" min="0" onkeydown="if(event.key==='-' || event.key==='e' || event.key==='+' || event.key==='E') event.preventDefault();" id="productStock" name="stock" class="form-control clear-on-focus" value="<?php echo htmlspecialchars($stock); ?>" placeholder="0">
                <div class="invalid-feedback" id="stockFeedback">Please enter a valid stock quantity.</div>
            </div>

            <!-- Original Price -->
            <div class="col-md-4">
                <label for="original_price" class="form-label">Original Price ($) *</label>
                <input type="number" min="0" step="0.01" onkeydown="if(event.key==='-' || event.key==='e' || event.key==='+' || event.key==='E') event.preventDefault();" name="original_price" id="original_price" class="form-control clear-on-focus format-decimal" value="<?php echo htmlspecialchars($original_price); ?>" placeholder="0.00">
                <div class="invalid-feedback" id="origPriceFeedback">Original price cannot be negative.</div>
            </div>

            <!-- Discount Percentage -->
            <div class="col-md-4">
                <label for="discount_percent" class="form-label">Discount (%)</label>
                <input type="number" step="1" name="discount_percent" id="discount_percent" class="form-control clear-on-focus" value="<?php echo htmlspecialchars($discount_percent); ?>" onkeydown="if(event.key==='-' || event.key==='e' || event.key==='+' || event.key==='E') event.preventDefault();" min="0" max="100" placeholder="0">
                <div class="invalid-feedback" id="discountFeedback">Discount must be between 0 and 100.</div>
            </div>

            <!-- Sale Price -->
            <div class="col-md-4">
                <label for="sale_price" class="form-label">Sale Price ($)</label>
                <input type="text" name="sale_price" id="sale_price" class="form-control bg-light" readonly value="<?php echo htmlspecialchars($sale_price); ?>">
            </div>

            <div class="col-md-6">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="Active" <?php echo ($status == 'Active') ? 'selected' : ''; ?>>Active</option>
                    <option value="Draft" <?php echo ($status == 'Draft') ? 'selected' : ''; ?>>Draft</option>
                    <option value="Archived" <?php echo ($status == 'Archived') ? 'selected' : ''; ?>>Archived</option>
                </select>
            </div>
            
            <div class="col-md-6">
                <label class="form-label">Product Image (Max 2MB - Optional)</label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-image text-muted"></i></span>
                    <input type="file" id="productImage" name="image" class="form-control" accept="image/jpeg,image/png,image/webp" aria-describedby="productImageFeedback">
                </div>
                <div class="invalid-feedback" id="productImageFeedback" aria-live="polite"></div>
                <small class="text-muted">If no image is uploaded, a default avatar will be assigned automatically.</small>
            </div>

            <div class="col-12">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($desc); ?></textarea>
            </div>

            <div class="col-12 mt-4">
                <button type="submit" id="submitBtn" class="btn btn-dark">Save Product</button>
                <a href="products.php" class="btn btn-outline-secondary ms-2">Cancel</a>
            </div>
        </div>
    </form>
</div>

<!-- Real-Time Validation & Click-to-Clear Script -->
<script>
let isNameUnique = true;
let isSkuUnique = true;

const nameInput = document.getElementById('productName');
const skuInput = document.getElementById('productSku');
const categoryInput = document.getElementById('productCategory');
const priceInput = document.getElementById('productPrice');
const stockInput = document.getElementById('productStock');
const origPriceInput = document.getElementById('original_price');
const discountInput = document.getElementById('discount_percent');
const salePriceInput = document.getElementById('sale_price');
const productImageInput = document.getElementById('productImage');
const productImageFeedback = document.getElementById('productImageFeedback');

function validateProductImage() {
    const file = productImageInput.files[0];
    const message = file && file.size > 2 * 1024 * 1024
        ? 'Product image must be no larger than 2 MB.'
        : '';
    productImageInput.classList.toggle('is-invalid', Boolean(message));
    productImageInput.classList.toggle('is-valid', Boolean(file) && !message);
    productImageFeedback.textContent = message;
    productImageFeedback.classList.toggle('d-block', Boolean(message));
    return !message;
}

productImageInput.addEventListener('change', validateProductImage);

document.querySelectorAll('.clear-on-focus').forEach(input => {
    input.addEventListener('focus', function() {
        if (this.value === '0' || this.value === '0.00' || this.value === '') {
            if(this.value !== '') this.dataset.defaultVal = this.value;
            this.value = '';
        }
    });

    input.addEventListener('blur', function() {
        if (this.value.trim() === '') {
            this.value = this.dataset.defaultVal || '0.00';
        }
        if (this.classList.contains('format-decimal') || this.id === 'productPrice' || this.id === 'original_price') {
            let val = parseFloat(this.value);
            if (!isNaN(val) && val >= 0) {
                this.value = val.toFixed(2);
            } else {
                this.value = '0.00';
            }
        }
        calculateSalePrice();
    });
});

function validateNameField() {
    if (nameInput.value.trim() === "") {
        nameInput.classList.add('is-invalid');
        nameInput.classList.remove('is-valid');
        document.getElementById('nameFeedback').innerText = "Product name is required.";
        return false;
    } else if (!isNameUnique) {
        nameInput.classList.add('is-invalid');
        nameInput.classList.remove('is-valid');
        document.getElementById('nameFeedback').innerText = "A product with this name already exists!";
        return false;
    } else {
        nameInput.classList.remove('is-invalid');
        nameInput.classList.add('is-valid');
        return true;
    }
}

function validateSkuField() {
    let val = skuInput.value.trim();
    if (val !== "" && !isSkuUnique) {
        skuInput.classList.add('is-invalid');
        skuInput.classList.remove('is-valid');
        document.getElementById('skuFeedback').innerText = "This SKU already exists!";
        return false;
    } else {
        skuInput.classList.remove('is-invalid');
        if(val !== "") { skuInput.classList.add('is-valid'); } else { skuInput.classList.remove('is-valid'); }
        return true;
    }
}

function validateCategory() {
    if (categoryInput.value === "") {
        categoryInput.classList.add('is-invalid');
        categoryInput.classList.remove('is-valid');
        return false;
    } else {
        categoryInput.classList.remove('is-invalid');
        categoryInput.classList.add('is-valid');
        return true;
    }
}

function validatePrice() {
    let val = priceInput.value.trim();
    let numberRegex = /^\d+(\.\d+)?$/;

    if (val === "" || !numberRegex.test(val) || parseFloat(val) < 0) {
        priceInput.classList.add('is-invalid');
        priceInput.classList.remove('is-valid');
        document.getElementById('priceFeedback').innerText = "Price must be a valid number.";
        return false;
    } else {
        priceInput.classList.remove('is-invalid');
        priceInput.classList.add('is-valid');
        return true;
    }
}

function validateStock() {
    let val = stockInput.value.trim();
    let integerRegex = /^\d+$/;

    if (val === "" || !integerRegex.test(val) || parseInt(val) < 0) {
        stockInput.classList.add('is-invalid');
        stockInput.classList.remove('is-valid');
        return false;
    } else {
        stockInput.classList.remove('is-invalid');
        stockInput.classList.add('is-valid');
        return true;
    }
}

function calculateSalePrice() {
    if (!origPriceInput || !salePriceInput) return;

    let origPrice = parseFloat(origPriceInput.value) || 0;
    let discount = discountInput ? (parseFloat(discountInput.value) || 0) : 0;

    if (origPrice < 0) {
        origPriceInput.classList.add('is-invalid');
        origPriceInput.classList.remove('is-valid');
    } else {
        origPriceInput.classList.remove('is-invalid');
        if(origPriceInput.value.trim() !== '') origPriceInput.classList.add('is-valid');
    }

    if (discount < 0 || discount > 100) {
        discountInput.classList.add('is-invalid');
        discountInput.classList.remove('is-valid');
        if (discount < 0) discount = 0;
        if (discount > 100) discount = 100;
    } else {
        discountInput.classList.remove('is-invalid');
        if(discountInput.value.trim() !== '') discountInput.classList.add('is-valid');
    }

    let salePrice = origPrice - (origPrice * (discount / 100));
    salePriceInput.value = salePrice >= 0 ? salePrice.toFixed(2) : "0.00";
}

nameInput.addEventListener('input', function() { isNameUnique = true; validateNameField(); });
skuInput.addEventListener('input', function() { isSkuUnique = true; validateSkuField(); });
categoryInput.addEventListener('change', validateCategory);
priceInput.addEventListener('input', validatePrice);
stockInput.addEventListener('input', function() {
    let stockVal = stockInput.value.replace(/[^0-9]/g, '');
    stockInput.value = stockVal === "" ? "0" : stockVal;
    validateStock();
});

if (origPriceInput) origPriceInput.addEventListener('input', calculateSalePrice);
if (discountInput) discountInput.addEventListener('input', calculateSalePrice);
calculateSalePrice();

nameInput.addEventListener('blur', function() {
    let nameVal = this.value.trim();
    if (nameVal !== "") {
        $.ajax({
            url: 'check-duplicate.php',
            type: 'POST',
            data: { type: 'name', value: nameVal },
            success: function(response) {
                isNameUnique = (response.trim() !== "exists");
                validateNameField();
            }
        });
    }
});

skuInput.addEventListener('blur', function() {
    let skuVal = this.value.trim();
    if (skuVal !== "") {
        $.ajax({
            url: 'check-duplicate.php',
            type: 'POST',
            data: { type: 'sku', value: skuVal },
            success: function(response) {
                isSkuUnique = (response.trim() !== "exists");
                validateSkuField();
            }
        });
    }
});

document.getElementById('productForm').addEventListener('submit', function(e) {
    e.preventDefault();

    if (!validateProductImage()) {
        productImageInput.focus();
        productImageInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return false;
    }

    let isNameValid = validateNameField();
    let isSkuValid = validateSkuField();
    let isCategoryValid = validateCategory();
    let isPriceValid = validatePrice();
    let isStockValid = validateStock();

    if (!isNameValid || !isSkuValid || !isCategoryValid || !isPriceValid || !isStockValid) {
        let firstInvalid = document.querySelector('.form-control.is-invalid, .form-select.is-invalid');
        if (firstInvalid) {
            firstInvalid.focus();
            firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        return false;
    }

    let form = this;
    let nameVal = nameInput.value.trim();
    let skuVal = skuInput.value.trim();

    $.ajax({
        url: 'check-duplicate.php',
        type: 'POST',
        data: { type: 'name', value: nameVal },
        success: function(nameResponse) {
            if (nameResponse.trim() === "exists") {
                nameInput.classList.add('is-invalid');
                nameInput.classList.remove('is-valid');
                document.getElementById('nameFeedback').innerText = "A product with this name already exists!";
                nameInput.focus();
                return;
            }

            if (skuVal !== "") {
                $.ajax({
                    url: 'check-duplicate.php',
                    type: 'POST',
                    data: { type: 'sku', value: skuVal },
                    success: function(skuResponse) {
                        if (skuResponse.trim() === "exists") {
                            skuInput.classList.add('is-invalid');
                            skuInput.classList.remove('is-valid');
                            document.getElementById('skuFeedback').innerText = "This SKU already exists!";
                            skuInput.focus();
                            return;
                        }

                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: 'Product validated successfully!',
                            confirmButtonColor: '#333',
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            form.submit();
                        });
                    }
                });
            } else {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: 'Product validated successfully!',
                    confirmButtonColor: '#333',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    form.submit();
                });
            }
        }
    });
});
</script>

<!-- PHP Backend Validation Error Handler -->
<?php if (!empty($error_msg)): ?>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        Swal.fire({ 
            icon: 'error', 
            title: 'Validation Error', 
            text: '<?php echo addslashes($error_msg); ?>', 
            confirmButtonColor: '#d33' 
        });
    });
</script>
<?php endif; ?>

<?php include __DIR__ . '/../layouts/footer.php'; ?>