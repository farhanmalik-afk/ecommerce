<?php
session_start();
include "../config.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require '../vendor/autoload.php';

$error_msg = "";

$name = "";
$email = "";
$phone = "";
$customer_group = "Retail";
$city = "";
$country = "Pakistan";
$status = "Active";
$address = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name             = trim($_POST['name'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $phone            = trim($_POST['phone'] ?? '');
    $password         = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $customer_group   = trim($_POST['customer_group'] ?? 'Retail');
    $address          = trim($_POST['address'] ?? '');
    $city             = trim($_POST['city'] ?? '');
    $country          = trim($_POST['country'] ?? 'Pakistan');
    $status           = trim($_POST['status'] ?? 'Active');
    
    $missing_fields = [];
    if (empty($name)) $missing_fields[] = "Full Name";
    if (empty($email)) $missing_fields[] = "Email Address";
    if (empty($password)) $missing_fields[] = "Password";
    if (empty($confirm_password)) $missing_fields[] = "Confirm Password";

    if (!empty($missing_fields)) {
        $error_msg = "Please fill in the required field(s): " . implode(", ", $missing_fields);
    } 
    elseif (!preg_match("/^[A-Za-z\s]{3,}$/", $name)) {
        $error_msg = "Name must contain only letters and be at least 3 characters long.";
    }
    elseif (!preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/", $password)) {
        $error_msg = "Password must be at least 8 characters long, include uppercase, lowercase, number, and special character.";
    }
    elseif ($password !== $confirm_password) {
        $error_msg = "Passwords do not match!";
    }

    if (empty($error_msg)) {
        $check_stmt = $conn->prepare("SELECT id FROM customers WHERE email = ?");
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_stmt->store_result();
        if ($check_stmt->num_rows > 0) {
            $error_msg = "This email address is already registered!";
        }
        $check_stmt->close();
    }

    $profile_image = "";

    if (empty($error_msg) && isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $profileImageUpload = $_FILES['profile_image'];
        if ($profileImageUpload['error'] !== UPLOAD_ERR_OK) {
            $error_msg = in_array($profileImageUpload['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? "Profile image must be no larger than 2 MB."
                : "There was an error uploading the image.";
        } elseif ($profileImageUpload['size'] > 2 * 1024 * 1024) {
            $error_msg = "Profile image must be no larger than 2 MB.";
        } else {
            $target_dir = "../uploads/";
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0755, true);
            }

            $image_name = time() . "_" . basename($profileImageUpload["name"]);
            $target_file = $target_dir . $image_name;
            $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

            $allowed_types = array("jpg", "jpeg", "png", "webp");
            if (in_array($imageFileType, $allowed_types)) {
                if (move_uploaded_file($profileImageUpload["tmp_name"], $target_file)) {
                    $profile_image = $image_name;
                } else {
                    $error_msg = "There was an error uploading the image.";
                }
            } else {
                $error_msg = "Only JPG, JPEG, PNG, and WEBP files are allowed.";
            }
        }
    }

    if (empty($error_msg)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        try {
            $stmt = $conn->prepare("INSERT INTO customers (name, email, phone, address, city, country, status, profile_image, password, customer_group) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssssss", $name, $email, $phone, $address, $city, $country, $status, $profile_image, $hashed_password, $customer_group);
            
            if ($stmt->execute()) {
                createAdminNotification(
                    $conn,
                    'New customer registered',
                    $name . ' has registered as a customer.',
                    'customer'
                );
                
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'fm3023098@gmail.com';
                    $mail->Password   = 'agvv xlon chwh tvrx';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587;

                    $mail->SMTPOptions = array(
                        'ssl' => array(
                            'verify_peer' => false,
                            'verify_peer_name' => false,
                            'allow_self_signed' => true
                        )
                    );

                    $mail->setFrom('fm3023098@gmail.com', 'E-Commerce Dashboard');
                    $mail->addAddress($email, $name);

                    $mail->isHTML(true);
                    $mail->Subject = "Welcome to Our Store! - Account Details";
                    $mail->Body    = "
                        <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #ddd; border-radius: 5px;'>
                            <h2 style='color: #333;'>Welcome, " . htmlspecialchars($name) . "!</h2>
                            <p>Thank you for registering with us. Your account has been successfully created.</p>
                            
                            <p>Here are your login details:</p>
                            <ul style='background: #f9f9f9; padding: 15px; border-radius: 5px; list-style: none;'>
                                <li><strong>Email Address:</strong> " . htmlspecialchars($email) . "</li>
                                <li><strong>Password:</strong> " . htmlspecialchars($password) . "</li>
                            </ul>
                            
                            <p>You can now log in to your dashboard using your email and password.</p>
                            <br>
                            <p>Best Regards,<br><strong>E-Commerce Team</strong></p>
                        </div>
                    ";

                    $mail->send();
                } catch (Exception $e) {
                    // PHPMailer exception
                }

                $_SESSION['swal_success'] = "Customer added and email sent successfully!";
                header("Location: customers.php");
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
    $pageTitle = 'Add Customer';
    $parent = ['Customers', 'customers.php'];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Add Customer - E-Commerce Dashboard</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>

<div class="app">
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
                  <img id="profilePreview" src="" alt="Preview" class="w-100 h-100" style="object-fit: cover; display: none;">
                  <div id="defaultIcon" class="w-100 h-100 d-flex align-items-center justify-content-center text-muted" style="font-size: 2rem;">
                    <i class="bi bi-person"></i>
                  </div>
                </div>
                <label for="profileImageInput" class="position-absolute bottom-0 end-0 bg-dark text-white rounded-circle p-1 shadow" style="cursor: pointer; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center;" title="Upload Photo">
                  <i class="bi bi-camera-fill" style="font-size: 0.75rem;"></i>
                </label>
              </div>
              <div>
                <h5 class="mb-1 fw-bold text-dark"><i class="bi bi-person-plus-fill text-primary me-2"></i> Add New Customer</h5>
                <p class="text-muted mb-0" style="font-size: 0.85rem;">Upload profile picture and fill in the details below.</p>
                <div id="profileImageFeedback" class="text-danger small" role="alert" aria-live="polite"></div>
              </div>
            </div>

            <form id="customerForm" action="" method="POST" enctype="multipart/form-data" autocomplete="off" novalidate>
              <input type="file" id="profileImageInput" name="profile_image" class="d-none" accept="image/*" onchange="previewImage(event)">

              <div class="row g-3">
                
                <div class="col-md-4">
                  <label class="form-label fw-semibold" style="font-size: 0.8rem;">Full Name <span class="text-danger">*</span></label>
                  <input type="text" name="name" id="name" class="form-control" placeholder="e.g. Ali Khan" autocomplete="off" value="<?php echo htmlspecialchars($name); ?>">
                  <div class="invalid-feedback">Name must contain only letters and be at least 3 characters long.</div>
                </div>

                <div class="col-md-4">
                  <label class="form-label fw-semibold" style="font-size: 0.8rem;">Email Address <span class="text-danger">*</span></label>
                  <input type="email" name="email" id="email" class="form-control" placeholder="e.g. ali@example.com" autocomplete="off" value="<?php echo htmlspecialchars($email); ?>">
                  <div class="invalid-feedback" id="emailFeedback">Please enter a valid email address.</div>
                </div>

                <div class="col-md-4">
                  <label class="form-label fw-semibold" style="font-size: 0.8rem;">Phone Number</label>
                  <input type="number" name="phone" id="phone" class="form-control" placeholder="e.g. 3001234567" maxlength="11" autocomplete="off" value="<?php echo htmlspecialchars($phone); ?>" oninput="if(this.value < 0) this.value = '';">
                  <div class="invalid-feedback">Phone number cannot be negative.</div>
                </div>

                <div class="col-md-4">
                  <label class="form-label fw-semibold" style="font-size: 0.8rem;">Password <span class="text-danger">*</span></label>
                  <div class="input-group">
                    <input type="password" name="password" id="password" class="form-control" placeholder="Enter secure password" autocomplete="new-password">
                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                      <i class="bi bi-eye" id="passwordIcon"></i>
                    </button>
                    <div class="invalid-feedback">Password must be 8+ chars with uppercase, lowercase, number, & special char.</div>
                  </div>
                  <small class="form-text text-muted d-block mt-1" style="font-size: 0.75rem;">
                      <i class="bi bi-info-circle"></i> Must be 8+ chars with uppercase, lowercase, number, & special char.
                  </small>
                </div>

                <div class="col-md-4">
                  <label class="form-label fw-semibold" style="font-size: 0.8rem;">Confirm Password <span class="text-danger">*</span></label>
                  <div class="input-group">
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Re-enter password" autocomplete="new-password">
                    <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                      <i class="bi bi-eye" id="confirmPasswordIcon"></i>
                    </button>
                    <div class="invalid-feedback">Passwords must match identically.</div>
                  </div>
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
                  <input type="text" name="city" id="city" class="form-control" placeholder="e.g. Lahore" autocomplete="off" value="<?php echo htmlspecialchars($city); ?>">
                </div>

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
                  <input type="text" name="address" id="address" class="form-control" placeholder="Street, House #, Area..." autocomplete="off" value="<?php echo htmlspecialchars($address); ?>">
                </div>

                <div class="col-12 mt-4 pt-2 border-top">
                  <button type="submit" class="btn btn-dark px-4 py-2" style="background: var(--dark-gradient); border: 0; font-weight: 500;">
                    <i class="bi bi-check-circle me-1"></i> Save Customer
                  </button>
                  <button type="reset" class="btn btn-outline-danger ms-2 px-4 py-2" onclick="resetFormValidation()">
                    <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                  </button>
                  <a href="customers.php" class="btn btn-outline-secondary ms-2 px-4 py-2">Cancel</a>
                </div>

              </div>
            </form>

          </div>
        </div>
      </div>
    </section>
  </main>
</div>

<script>
function previewImage(event) {
    const input = event.target;
    const feedback = document.getElementById('profileImageFeedback');
    const file = input.files[0];
    if (file && file.size > 2 * 1024 * 1024) {
        feedback.textContent = 'Profile image must be no larger than 2 MB.';
        input.setAttribute('aria-invalid', 'true');
        document.getElementById('profilePreview').style.display = 'none';
        document.getElementById('defaultIcon').style.display = 'flex';
        return;
    }

    feedback.textContent = '';
    input.setAttribute('aria-invalid', 'false');
    if (!file) return;

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

function resetFormValidation() {
    document.getElementById('customerForm').reset();
    document.querySelectorAll('.form-control, .form-select').forEach(input => {
        input.classList.remove('is-valid', 'is-invalid');
    });
    document.getElementById('profilePreview').style.display = 'none';
    document.getElementById('defaultIcon').style.display = 'flex';
    document.getElementById('profileImageFeedback').textContent = '';
    document.getElementById('profileImageInput').setAttribute('aria-invalid', 'false');
}

document.getElementById('togglePassword').addEventListener('click', function () {
    const passwordInput = document.getElementById('password');
    const passwordIcon = document.getElementById('passwordIcon');
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        passwordIcon.classList.remove('bi-eye');
        passwordIcon.classList.add('bi-eye-slash');
    } else {
        passwordInput.type = 'password';
        passwordIcon.classList.remove('bi-eye-slash');
        passwordIcon.classList.add('bi-eye');
    }
});

document.getElementById('toggleConfirmPassword').addEventListener('click', function () {
    const confirmInput = document.getElementById('confirm_password');
    const confirmIcon = document.getElementById('confirmPasswordIcon');
    if (confirmInput.type === 'password') {
        confirmInput.type = 'text';
        confirmIcon.classList.remove('bi-eye');
        confirmIcon.classList.add('bi-eye-slash');
    } else {
        confirmInput.type = 'password';
        confirmIcon.classList.remove('bi-eye-slash');
        confirmIcon.classList.add('bi-eye');
    }
});

let emailIsRegistered = false;

document.addEventListener("DOMContentLoaded", function () {
    const nameInput = document.getElementById('name');
    const emailInput = document.getElementById('email');
    const phoneInput = document.getElementById('phone');
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');

    function liveValidate(input, condition) {
        if (!input.value.trim()) {
            input.classList.remove('is-valid', 'is-invalid');
            return false;
        }
        if (condition) {
            input.classList.remove('is-invalid');
            input.classList.add('is-valid');
            return true;
        } else {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            return false;
        }
    }

    // 1. Name Live Validation
    if (nameInput) {
        nameInput.addEventListener('input', () => {
            liveValidate(nameInput, /^[A-Za-z\s]{3,}$/.test(nameInput.value.trim()));
        });
    }

    // 2. Email Live Validation & AJAX Duplicate Check
    let emailTimeout;
    if (emailInput) {
        const emailRegex = /^[a-zA-Z0-9._%+-]+@(gmail|yahoo|outlook|hotmail|live)\.com$/i;
        
        emailInput.addEventListener('input', () => {
            clearTimeout(emailTimeout);
            const emailVal = emailInput.value.trim();
            
            if (!emailVal) {
                emailInput.classList.remove('is-valid', 'is-invalid');
                emailIsRegistered = false;
                return;
            }

            if (!emailRegex.test(emailVal)) {
                emailInput.classList.add('is-invalid');
                emailInput.classList.remove('is-valid');
                document.getElementById('emailFeedback').innerText = "Please enter a valid email (e.g., @gmail.com, @yahoo.com).";
                emailIsRegistered = false;
                return;
            }

            emailInput.classList.remove('is-invalid', 'is-valid');

            emailTimeout = setTimeout(() => {
                fetch('check-email.php?email=' + encodeURIComponent(emailVal))
                    .then(response => response.json())
                    .then(data => {
                        if (data.exists) {
                            emailInput.classList.add('is-invalid');
                            emailInput.classList.remove('is-valid');
                            document.getElementById('emailFeedback').innerText = "This email address is already registered!";
                            emailIsRegistered = true;
                        } else {
                            emailInput.classList.remove('is-invalid');
                            emailInput.classList.add('is-valid');
                            emailIsRegistered = false;
                        }
                    })
                    .catch(() => {
                        emailInput.classList.remove('is-invalid');
                        emailInput.classList.add('is-valid');
                        emailIsRegistered = false;
                    });
            }, 300);
        });
    }

    // 3. Phone Real-time Validation (Max 11 digits, numbers only)
    if (phoneInput) {
        phoneInput.addEventListener('input', () => {
            let phoneVal = phoneInput.value.trim();
            phoneInput.value = phoneVal.replace(/[^0-9]/g, '');
            phoneVal = phoneInput.value.trim();

            if (phoneVal.length > 11) {
                phoneInput.value = phoneVal.slice(0, 11);
                phoneVal = phoneInput.value.trim();
            }

            if (!phoneVal) {
                phoneInput.classList.remove('is-valid', 'is-invalid');
                return;
            }

            const phoneRegex = /^[0-9]{10,11}$/;
            liveValidate(phoneInput, phoneRegex.test(phoneVal));
        });
    }

    // 4. Password Live Validation
    if (passwordInput) {
        passwordInput.addEventListener('input', () => {
            const strongPasswordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/;
            liveValidate(passwordInput, strongPasswordRegex.test(passwordInput.value));
            if (confirmPasswordInput.value.trim() !== '') {
                liveValidate(confirmPasswordInput, confirmPasswordInput.value === passwordInput.value);
            }
        });
    }

    if (confirmPasswordInput && passwordInput) {
        confirmPasswordInput.addEventListener('input', () => {
            liveValidate(confirmPasswordInput, confirmPasswordInput.value === passwordInput.value && confirmPasswordInput.value.trim() !== '');
        });
    }
});

// 5. Form Submit Validation
document.getElementById('customerForm').addEventListener('submit', function(e) {
    const profileImageInput = document.getElementById('profileImageInput');
    if (profileImageInput.files[0] && profileImageInput.files[0].size > 2 * 1024 * 1024) {
        e.preventDefault();
        document.getElementById('profileImageFeedback').textContent = 'Profile image must be no larger than 2 MB.';
        profileImageInput.setAttribute('aria-invalid', 'true');
        document.getElementById('profileImageFeedback').scrollIntoView({ behavior: 'smooth', block: 'center' });
        return false;
    }

    const nameInput = document.getElementById('name');
    const emailInput = document.getElementById('email');
    const phoneInput = document.getElementById('phone');
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');

    const name = nameInput.value.trim();
    const email = emailInput.value.trim();
    const phone = phoneInput.value.trim();
    const password = passwordInput.value;
    const confirmPassword = confirmPasswordInput.value;

    let hasError = false;
    let firstErrorField = null;

    function markInvalid(input) {
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');
        if (!firstErrorField) {
            firstErrorField = input;
        }
        hasError = true;
    }

    // Check Empty Fields
    if (!name) markInvalid(nameInput);
    if (!email) markInvalid(emailInput);
    if (!phone) markInvalid(phoneInput);
    if (!password) markInvalid(passwordInput);
    if (!confirmPassword) markInvalid(confirmPasswordInput);

    // Validate Name
    const nameRegex = /^[A-Za-z\s]{3,}$/;
    if (name && !nameRegex.test(name)) {
        markInvalid(nameInput);
    }

    // Validate Email & Duplicate Status
    const emailRegex = /^[a-zA-Z0-9._%+-]+@(gmail|yahoo|outlook|hotmail|live)\.com$/i;
    if (email && (!emailRegex.test(email) || emailIsRegistered)) {
        markInvalid(emailInput);
        if (emailIsRegistered) {
            document.getElementById('emailFeedback').innerText = "This email address is already registered!";
        }
    }

    // Validate Phone (10 or 11 digits)
    const phoneRegex = /^[0-9]{10,11}$/;
    if (phone && !phoneRegex.test(phone)) {
        markInvalid(phoneInput);
        let feedbackEl = phoneInput.nextElementSibling;
        if(feedbackEl && feedbackEl.classList.contains('invalid-feedback')) {
            feedbackEl.innerText = "Please enter a valid phone number (10 or 11 digits, numbers only).";
        }
    }

    // Validate Password Strength
    const strongPasswordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/;
    if (password && !strongPasswordRegex.test(password)) {
        markInvalid(passwordInput);
    }

    // Validate Password Match
    if (confirmPassword && password !== confirmPassword) {
        markInvalid(confirmPasswordInput);
    }

    // Stop submission if errors exist
    if (hasError || document.querySelectorAll('.is-invalid').length > 0) {
        e.preventDefault();
        if (firstErrorField) {
            firstErrorField.focus();
            firstErrorField.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        return false;
    }
});
</script>

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