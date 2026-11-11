<?php
session_start();
include "../config.php";

$error_msg = "";
$customer_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($customer_id <= 0) {
    header("Location: customers.php");
    exit();
}

// Form variables default values
$name = "";
$email = "";
$phone = "";
$customer_group = "Retail";
$city = "";
$country = "Pakistan";
$status = "Active";
$address = "";
$existing_image = "";

// Fetch existing customer data
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    $stmt = $conn->prepare("SELECT name, email, phone, customer_group, address, city, country, status, profile_image FROM customers WHERE id = ? AND is_deleted = 0");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        $name = $row['name'];
        $email = $row['email'];
        $phone = $row['phone'];
        $customer_group = $row['customer_group'];
        $address = $row['address'];
        $city = $row['city'];
        $country = $row['country'] ? $row['country'] : 'Pakistan';
        $status = $row['status'];
        $existing_image = $row['profile_image'];
    } else {
        header("Location: customers.php");
        exit();
    }
    $stmt->close();
}

// Form Submit Handling for Update
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name             = trim($_POST['name'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $phone            = trim($_POST['phone'] ?? '');
    $customer_group   = trim($_POST['customer_group'] ?? 'Retail');
    $address          = trim($_POST['address'] ?? '');
    $city             = trim($_POST['city'] ?? '');
    $country          = trim($_POST['country'] ?? 'Pakistan');
    $status           = trim($_POST['status'] ?? 'Active');
    
    // Required Fields Validation
    $missing_fields = [];
    if (empty($name)) $missing_fields[] = "Full Name";
    if (empty($email)) $missing_fields[] = "Email Address";

    if (!empty($missing_fields)) {
        $error_msg = "Please fill in the required field(s): " . implode(", ", $missing_fields);
    }

    // Check Duplicate Email (excluding current customer)
    if (empty($error_msg)) {
        $check_stmt = $conn->prepare("SELECT id FROM customers WHERE email = ? AND id != ?");
        $check_stmt->bind_param("si", $email, $customer_id);
        $check_stmt->execute();
        $check_stmt->store_result();
        if ($check_stmt->num_rows > 0) {
            $error_msg = "This email address is already registered by another customer!";
        }
        $check_stmt->close();
    }

    $profile_image = $existing_image;

    // Image Upload Handling
    if (empty($error_msg) && isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $target_dir = "../uploads/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }

        $image_name = time() . "_" . basename($_FILES["profile_image"]["name"]);
        $target_file = $target_dir . $image_name;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        $allowed_types = array("jpg", "jpeg", "png", "webp");
        if (in_array($imageFileType, $allowed_types)) {
            if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_file)) {
                $profile_image = $image_name;
            } else {
                $error_msg = "There was an error uploading the image.";
            }
        } else {
            $error_msg = "Only JPG, JPEG, PNG, and WEBP files are allowed.";
        }
    }

    if (empty($error_msg)) {
        try {
            $stmt = $conn->prepare("UPDATE customers SET name = ?, email = ?, phone = ?, customer_group = ?, address = ?, city = ?, country = ?, status = ?, profile_image = ? WHERE id = ?");
            $stmt->bind_param("sssssssssi", $name, $email, $phone, $customer_group, $address, $city, $country, $status, $profile_image, $customer_id);
            
            if ($stmt->execute()) {
                $_SESSION['swal_success'] = "Customer details updated successfully!";
                header("Location: customers.php?id=" . $customer_id);
                exit();
            }
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() == 1062) {
                $error_msg = "This email address is already registered!";
            } else {
                $error_msg = "Database Error: " . $e->getMessage();
            }
        }
    }

    if (!empty($error_msg)) {
        $_SESSION['swal_error'] = $error_msg;
    }
}
    $pageTitle = 'Edit Customer';
    $parent = ['Customers', 'customers.php'];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Edit Customer - E-Commerce Dashboard</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
  
  <!-- SweetAlert2 CSS & JS CDN -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>

<div class="app">

  <!-- ================= SIDEBAR ================= -->
  <?php include "../layouts/sidebar.php"; ?>

  <main class="app-main">
    <?php include "../layouts/topbar.php"; ?>

  <section class="row mb-4">
      <div class="col-12">
        <div class="card shadow-sm border-0">
          <div class="card-body p-4">
            
            <div class="d-flex align-items-center mb-4 pb-3 border-bottom">
              <div class="position-relative me-3">
                <div class="rounded-circle overflow-hidden border border-3 border-light shadow-sm" style="width: 85px; height: 85px; background: #e9ecef;">
                  <img id="profilePreview" src="<?php echo !empty($existing_image) ? '../uploads/' . htmlspecialchars($existing_image) : ''; ?>" alt="Preview" class="w-100 h-100" style="object-fit: cover; <?php echo empty($existing_image) ? 'display: none;' : ''; ?>">
                  <div id="defaultIcon" class="w-100 h-100 d-flex align-items-center justify-content-center text-muted" style="font-size: 2rem; <?php echo !empty($existing_image) ? 'display: none;' : ''; ?>">
                    <i class="bi bi-person"></i>
                  </div>
                </div>
                <label for="profileImageInput" class="position-absolute bottom-0 end-0 bg-dark text-white rounded-circle p-1 shadow" style="cursor: pointer; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center;" title="Change Photo">
                  <i class="bi bi-camera-fill" style="font-size: 0.75rem;"></i>
                </label>
              </div>
              <div>
                <h5 class="mb-1 fw-bold text-dark"><i class="bi bi-pencil-square text-primary me-2"></i> Edit Customer Profile</h5>
                <p class="text-muted mb-0" style="font-size: 0.85rem;">Update customer information and settings below.</p>
              </div>
            </div>

            <!-- Edit Form with Country Dropdown & Sticky Values -->
            <form id="customerForm" action="" method="POST" enctype="multipart/form-data" novalidate>
              
              <input type="file" id="profileImageInput" name="profile_image" class="d-none" accept="image/*" onchange="previewImage(event)">

              <div class="row g-3">
                
                <div class="col-md-4">
                  <label class="form-label fw-semibold" style="font-size: 0.8rem;">Full Name <span class="text-danger">*</span></label>
                  <input type="text" name="name" id="name" class="form-control" value="<?php echo htmlspecialchars($name); ?>">
                </div>

                <div class="col-md-4">
                  <label class="form-label fw-semibold" style="font-size: 0.8rem;">Email Address <span class="text-danger">*</span></label>
                  <input type="email" name="email" id="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>">
                </div>

                <div class="col-md-4">
                  <label class="form-label fw-semibold" style="font-size: 0.8rem;">Phone Number</label>
                  <input type="number" name="phone" id="phone" class="form-control" value="<?php echo htmlspecialchars($phone); ?>" oninput="if(this.value < 0) this.value = '';">
                </div>

                <div class="col-md-4">
                  <label class="form-label fw-semibold" style="font-size: 0.8rem;">Customer Group</label>
                  <select name="customer_group" class="form-select">
                    <option value="Retail" <?php echo ($customer_group == 'Retail') ? 'selected' : ''; ?>>Retail Customer</option>
                    <option value="Wholesale" <?php echo ($customer_group == 'Wholesale') ? 'selected' : ''; ?>>Wholesale Customer</option>
                    <option value="VIP" <?php echo ($customer_group == 'VIP') ? 'selected' : ''; ?>>VIP Customer</option>
                  </select>
                </div>

                <div class="col-md-4">
                  <label class="form-label fw-semibold" style="font-size: 0.8rem;">City</label>
                  <input type="text" name="city" class="form-control" value="<?php echo htmlspecialchars($city); ?>">
                </div>

                <!-- Country Dropdown with Sticky Selection -->
                <div class="col-md-4">
                  <label class="form-label fw-semibold" style="font-size: 0.8rem;">Country</label>
                  <select name="country" class="form-select">
                    <option value="Pakistan" <?php echo ($country == 'Pakistan') ? 'selected' : ''; ?>>Pakistan</option>
                    <option value="India" <?php echo ($country == 'India') ? 'selected' : ''; ?>>India</option>
                    <option value="United States" <?php echo ($country == 'United States') ? 'selected' : ''; ?>>United States</option>
                    <option value="United Kingdom" <?php echo ($country == 'United Kingdom') ? 'selected' : ''; ?>>United Kingdom</option>
                    <option value="United Arab Emirates" <?php echo ($country == 'United Arab Emirates') ? 'selected' : ''; ?>>United Arab Emirates</option>
                    <option value="Saudi Arabia" <?php echo ($country == 'Saudi Arabia') ? 'selected' : ''; ?>>Saudi Arabia</option>
                    <option value="Canada" <?php echo ($country == 'Canada') ? 'selected' : ''; ?>>Canada</option>
                    <option value="Australia" <?php echo ($country == 'Australia') ? 'selected' : ''; ?>>Australia</option>
                  </select>
                </div>

                <div class="col-md-4">
                  <label class="form-label fw-semibold" style="font-size: 0.8rem;">Account Status</label>
                  <select name="status" class="form-select">
                    <option value="Active" <?php echo ($status == 'Active') ? 'selected' : ''; ?>>Active</option>
                    <option value="Inactive" <?php echo ($status == 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                  </select>
                </div>

                <div class="col-12">
                  <label class="form-label fw-semibold" style="font-size: 0.8rem;">Shipping Address</label>
                  <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($address); ?>">
                </div>

                <div class="col-12 mt-4 pt-2 border-top">
                  <button type="submit" class="btn btn-dark px-4 py-2" style="background: var(--dark-gradient); border: 0; font-weight: 500;">
                    <i class="bi bi-check-circle me-1"></i> Update Customer
                  </button>
                  <a href="customer-details.php?id=<?php echo $customer_id; ?>" class="btn btn-outline-secondary ms-2 px-4 py-2">Cancel</a>
                </div>

              </div>
            </form>

          </div>
        </div>
      </div>
    </section>
  </main>
</div>

<!-- JavaScript for Image Preview and Validations -->
<script>
function previewImage(event) {
    const reader = new FileReader();
    reader.onload = function(){
        const output = document.getElementById('profilePreview');
        const defaultIcon = document.getElementById('defaultIcon');
        output.src = reader.result;
        output.style.display = 'block';
        if(defaultIcon) defaultIcon.style.display = 'none';
    };
    reader.readAsDataURL(event.target.files[0]);
}

document.getElementById('customerForm').addEventListener('submit', function(e) {
    const name = document.getElementById('name').value.trim();
    const email = document.getElementById('email').value.trim();
    const phone = document.getElementById('phone').value;

    let missing = [];
    if (!name) missing.push("Full Name");
    if (!email) missing.push("Email Address");

    if (missing.length > 0) {
        e.preventDefault();
        Swal.fire({
            icon: 'warning',
            title: 'Required Fields Missing',
            text: 'Please fill in the required field(s): ' + missing.join(', '),
            confirmButtonColor: '#3085d6'
        });
        return false;
    }

    if (phone !== "" && parseFloat(phone) < 0) {
        e.preventDefault();
        Swal.fire({
            icon: 'error',
            title: 'Invalid Input',
            text: 'Phone number cannot be negative (-)!',
            confirmButtonColor: '#d33'
        });
        return false;
    }
});
</script>

<!-- SweetAlert Error Popup Handler for Backend Messages -->
<?php if (isset($_SESSION['swal_error'])): ?>
<script>
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: '<?php echo $_SESSION['swal_error']; ?>',
        confirmButtonColor: '#d33'
    });
</script>
<?php unset($_SESSION['swal_error']); endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>