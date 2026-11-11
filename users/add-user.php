<?php
// users/add-user.php
session_start();
require_once "../config.php";
require_once "../includes/rbac.php";

enforceAccess($conn, 'user_management', 'create');

$error_msg = "";

// AJAX Request Handler for Realtime Duplicate Checks (Email)
if (isset($_POST['type']) && isset($_POST['value'])) {
    $type = trim($_POST['type']);
    $value = trim($_POST['value']);
    
    if ($type === 'email' && !empty($value)) {
        $stmt = $conn->prepare("SELECT id FROM admins WHERE email = ?");
        $stmt->bind_param("s", $value);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            echo "exists";
        } else {
            echo "unique";
        }
        $stmt->close();
        exit();
    }
}

// Form Submission Handling (PHP Server-Side Validation)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name             = trim($_POST['name'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $password         = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role_id          = !empty($_POST['role_id']) ? intval($_POST['role_id']) : null;
    $phone            = trim($_POST['phone'] ?? '');
    $address          = trim($_POST['address'] ?? '');
    $status           = trim($_POST['status'] ?? 'Active');

    // Regex for Strong Password: min 8 chars, 1 uppercase, 1 lowercase, 1 number, 1 special char
    $strongPasswordPattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/';

    // PHP Backend Validation
    if (empty($name)) {
        $error_msg = "User full name is required!";
    } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = "Please enter a valid email address!";
    } elseif (empty($password) || !preg_match($strongPasswordPattern, $password)) {
        $error_msg = "Password must be at least 8 characters long and include uppercase, lowercase, number, and special character.";
    } elseif ($password !== $confirm_password) {
        $error_msg = "Passwords do not match!";
    } elseif (empty($role_id)) {
        $error_msg = "Please select a user role!";
    } else {
        // Database Duplicate Check for Email
        $check_email = $conn->prepare("SELECT id FROM admins WHERE email = ?");
        $check_email->bind_param("s", $email);
        $check_email->execute();
        $check_email->store_result();

        if ($check_email->num_rows > 0) {
            $error_msg = "This email ('" . htmlspecialchars($email) . "') is already registered!";
        }
        $check_email->close();
    }

    // Avatar Upload Logic
    $avatar = "default-avatar.png";

    if (empty($error_msg) && isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
        $avatarUpload = $_FILES['avatar'];
        if ($avatarUpload['error'] !== UPLOAD_ERR_OK) {
            $error_msg = "Failed to upload avatar image file.";
        } elseif ($avatarUpload['size'] > (2 * 1024 * 1024)) { // Fixed size check multiplication
            $error_msg = "Avatar image size must be no larger than 2 MB.";
        } else {
            $target_dir = "../uploads/avatars/";
            if (!is_dir($target_dir)) { mkdir($target_dir, 0755, true); }

            // Clean filename to remove spaces and special characters
            $safe_filename = preg_replace("/[^a-zA-Z0-9\._-]/", "_", basename($avatarUpload["name"]));
            $avatar_name = time() . "_" . $safe_filename;
            $target_file = $target_dir . $avatar_name;
            $fileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

            $allowed = array("jpg", "jpeg", "png", "webp");
            if (in_array($fileType, $allowed)) {
                if (move_uploaded_file($avatarUpload["tmp_name"], $target_file)) {
                    $avatar = $avatar_name; 
                } else {
                    $error_msg = "Failed to upload avatar image file.";
                }
            } else {
                $error_msg = "Only JPG, JPEG, PNG & WEBP formats are allowed for avatar.";
            }
        }
    }

    if (empty($avatar)) {
        $avatar = "default-avatar.png";
    }

    // Insert into Database
    if (empty($error_msg)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("INSERT INTO admins (name, email, password_hash, role_id, phone, address, status, avatar) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssissss", $name, $email, $hashedPassword, $role_id, $phone, $address, $status, $avatar);
        
        try {
            if ($stmt->execute()) {
                logActivity($conn, getCurrentAdminId(), "Created new user: $name", "User Management");
                $_SESSION['swal_success'] = "New user added successfully!";
                header("Location: users.php");
                exit();
            }
        } catch (mysqli_sql_exception $e) {
            $error_msg = "Database Error: " . $e->getMessage();
        }
        $stmt->close();
    }
}

$pageTitle = 'Add New User';
include "../layouts/header.php";
?>

<!-- SweetAlert2 CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- jQuery CDN -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<div class="app-main dashboard-main">
    <section class="page-head mb-4">
        <h1>Add New User</h1>
        <p class="text-muted">Create a new system user with strong password validation and secure hashing.</p>
    </section>

    <div class="card border-0 shadow-sm p-4 dashboard-panel">
        <form action="" method="POST" enctype="multipart/form-data" id="addUserForm" autocomplete="off" novalidate>
            <div class="row g-3">
                <!-- Full Name -->
                <div class="col-md-6">
                    <label class="form-label fw-bold">Full Name *</label>
                    <input type="text" name="name" id="userName" class="form-control" value="" placeholder="Enter full name" autocomplete="off">
                    <div class="invalid-feedback" id="nameFeedback">Full name is required.</div>
                </div>
                
                <!-- Email Address -->
                <div class="col-md-6">
                    <label class="form-label fw-bold">Email Address *</label>
                    <input type="email" name="email" id="userEmail" class="form-control" value="" placeholder="name@example.com" autocomplete="off">
                    <div class="invalid-feedback" id="emailFeedback">Please enter a valid, unique email address.</div>
                </div>

                <!-- Password with View Toggle Button -->
                <div class="col-md-6">
                    <label class="form-label fw-bold">Password *</label>
                    <div class="input-group">
                        <input type="password" name="password" id="userPassword" class="form-control" placeholder="Min 8 chars, uppercase, lowercase, number & symbol" autocomplete="new-password">
                        <button type="button" class="btn btn-outline-secondary" id="togglePassword" tabindex="-1" title="Show/Hide Password">
                            <i class="bi bi-eye" id="passwordIcon"></i>
                        </button>
                    </div>
                    <small class="text-muted" style="font-size: 0.8rem;">Min 8 chars, uppercase, lowercase, number & symbol.</small>
                    <div class="invalid-feedback" id="passwordFeedback">Password must contain at least 8 characters including uppercase, lowercase, number, and special character.</div>
                </div>

                <!-- Confirm Password with View Toggle Button -->
                <div class="col-md-6">
                    <label class="form-label fw-bold">Confirm Password *</label>
                    <div class="input-group">
                        <input type="password" name="confirm_password" id="confirmPassword" class="form-control" placeholder="Re-enter password" autocomplete="new-password">
                        <button type="button" class="btn btn-outline-secondary" id="toggleConfirmPassword" tabindex="-1" title="Show/Hide Confirm Password">
                            <i class="bi bi-eye" id="confirmPasswordIcon"></i>
                        </button>
                    </div>
                    <div class="invalid-feedback" id="confirmPasswordFeedback">Passwords do not match.</div>
                </div>

                <!-- Phone Number -->
                <div class="col-md-6">
                    <label class="form-label">Phone Number</label>
                    <input type="text" name="phone" id="userPhone" class="form-control" value="" placeholder="e.g. +923001234567" autocomplete="off">
                </div>

                <!-- Role Selection -->
                <div class="col-md-6">
                    <label class="form-label fw-bold">User Role *</label>
                    <select name="role_id" id="userRole" class="form-select" required>
                        <option value="">-- Choose Role --</option>
                        <?php
                        $roles_result = $conn->query("SELECT id, name FROM roles");
                        if ($roles_result && $roles_result->num_rows > 0) {
                            while ($role = $roles_result->fetch_assoc()) {
                                echo "<option value='{$role['id']}'>" . htmlspecialchars($role['name']) . "</option>";
                            }
                        }
                        ?>
                    </select>
                    <div class="invalid-feedback">Please select a user role.</div>
                </div>

                <!-- Address -->
                <div class="col-12">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control" rows="2" placeholder="Enter residential address" autocomplete="off"></textarea>
                </div>

                <!-- Status -->
                <div class="col-md-6">
                    <label class="form-label">Account Status</label>
                    <select name="status" class="form-select">
                        <option value="Active" selected>Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
                
                <!-- Profile Avatar -->
                <div class="col-md-6">
                    <label class="form-label">Profile Avatar (Max 2MB - Optional)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-image text-muted"></i></span>
                        <input type="file" id="userAvatar" name="avatar" class="form-control" accept="image/jpeg,image/png,image/webp">
                    </div>
                    <div class="invalid-feedback" id="avatarFeedback"></div>
                    <small class="text-muted">If no avatar is uploaded, a default initial icon will be assigned.</small>
                </div>

                <!-- Form Action Buttons -->
                <div class="col-12 mt-4">
                    <button type="submit" id="submitBtn" class="btn btn-dark">Save User</button>
                    <a href="users.php" class="btn btn-outline-secondary ms-2">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Real-Time Validation & Eye Toggle Script -->
<script>
let isEmailUnique = true;

const nameInput = document.getElementById('userName');
const emailInput = document.getElementById('userEmail');
const passwordInput = document.getElementById('userPassword');
const confirmPasswordInput = document.getElementById('confirmPassword');
const roleSelect = document.getElementById('userRole');
const userAvatarInput = document.getElementById('userAvatar');
const userAvatarFeedback = document.getElementById('avatarFeedback');

window.addEventListener('pageshow', function() {
    document.getElementById('addUserForm').reset();
    document.querySelectorAll('.form-control, .form-select').forEach(el => {
        el.classList.remove('is-valid', 'is-invalid');
    });
});

document.getElementById('togglePassword').addEventListener('click', function() {
    const icon = document.getElementById('passwordIcon');
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        icon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        passwordInput.type = 'password';
        icon.classList.replace('bi-eye-slash', 'bi-eye');
    }
});

document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
    const icon = document.getElementById('confirmPasswordIcon');
    if (confirmPasswordInput.type === 'password') {
        confirmPasswordInput.type = 'text';
        icon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        confirmPasswordInput.type = 'password';
        icon.classList.replace('bi-eye-slash', 'bi-eye');
    }
});

function validateAvatar() {
    const file = userAvatarInput.files[0];
    const message = file && file.size > (2 * 1024 * 1024) // Fixed size check multiplication
        ? 'Avatar image must be no larger than 2 MB.'
        : '';
    userAvatarInput.classList.toggle('is-invalid', Boolean(message));
    userAvatarInput.classList.toggle('is-valid', Boolean(file) && !message);
    userAvatarFeedback.textContent = message;
    userAvatarFeedback.classList.toggle('d-block', Boolean(message));
    return !message;
}

if(userAvatarInput) {
    userAvatarInput.addEventListener('change', validateAvatar);
}

function validateName() {
    if (nameInput.value.trim() === "") {
        nameInput.classList.add('is-invalid');
        nameInput.classList.remove('is-valid');
        document.getElementById('nameFeedback').innerText = "Full name is required.";
        return false;
    } else {
        nameInput.classList.remove('is-invalid');
        nameInput.classList.add('is-valid');
        return true;
    }
}

function validateEmail() {
    let val = emailInput.value.trim();
    let emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if (val === "" || !emailRegex.test(val)) {
        emailInput.classList.add('is-invalid');
        emailInput.classList.remove('is-valid');
        document.getElementById('emailFeedback').innerText = "Please enter a valid email address.";
        return false;
    } else if (!isEmailUnique) {
        emailInput.classList.add('is-invalid');
        emailInput.classList.remove('is-valid');
        document.getElementById('emailFeedback').innerText = "This email is already registered!";
        return false;
    } else {
        emailInput.classList.remove('is-invalid');
        emailInput.classList.add('is-valid');
        return true;
    }
}

function validatePassword() {
    let val = passwordInput.value;
    let strongRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/;

    if (!strongRegex.test(val)) {
        passwordInput.classList.add('is-invalid');
        passwordInput.classList.remove('is-valid');
        return false;
    } else {
        passwordInput.classList.remove('is-invalid');
        passwordInput.classList.add('is-valid');
        return true;
    }
}

function validateConfirmPassword() {
    let pass = passwordInput.value;
    let confirmPass = confirmPasswordInput.value;
    if (confirmPass === "" || confirmPass !== pass) {
        confirmPasswordInput.classList.add('is-invalid');
        confirmPasswordInput.classList.remove('is-valid');
        return false;
    } else {
        confirmPasswordInput.classList.remove('is-invalid');
        confirmPasswordInput.classList.add('is-valid');
        return true;
    }
}

function validateRole() {
    if (roleSelect.value === "") {
        roleSelect.classList.add('is-invalid');
        roleSelect.classList.remove('is-valid');
        return false;
    } else {
        roleSelect.classList.remove('is-invalid');
        roleSelect.classList.add('is-valid');
        return true;
    }
}

nameInput.addEventListener('input', validateName);
passwordInput.addEventListener('input', function() {
    validatePassword();
    if(confirmPasswordInput.value !== "") validateConfirmPassword();
});
confirmPasswordInput.addEventListener('input', validateConfirmPassword);
roleSelect.addEventListener('change', validateRole);

emailInput.addEventListener('input', function() {
    isEmailUnique = true;
    validateEmail();
});

emailInput.addEventListener('blur', function() {
    let emailVal = this.value.trim();
    let emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (emailVal !== "" && emailRegex.test(emailVal)) {
        $.ajax({
            url: 'add-user.php',
            type: 'POST',
            data: { type: 'email', value: emailVal },
            success: function(response) {
                isEmailUnique = (response.trim() !== "exists");
                validateEmail();
            }
        });
    }
});

document.getElementById('addUserForm').addEventListener('submit', function(e) {
    e.preventDefault();

    if (userAvatarInput && !validateAvatar()) {
        userAvatarInput.focus();
        userAvatarInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return false;
    }

    let isNameValid = validateName();
    let isEmailValid = validateEmail();
    let isPassValid = validatePassword();
    let isConfirmPassValid = validateConfirmPassword();
    let isRoleValid = validateRole();

    if (!isNameValid || !isEmailValid || !isPassValid || !isConfirmPassValid || !isRoleValid) {
        let firstInvalid = document.querySelector('.form-control.is-invalid, .form-select.is-invalid');
        if (firstInvalid) {
            firstInvalid.focus();
            firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        return false;
    }

    let form = this;
    let emailVal = emailInput.value.trim();

    $.ajax({
        url: 'add-user.php',
        type: 'POST',
        data: { type: 'email', value: emailVal },
        success: function(emailResponse) {
            if (emailResponse.trim() === "exists") {
                emailInput.classList.add('is-invalid');
                emailInput.classList.remove('is-valid');
                document.getElementById('emailFeedback').innerText = "This email is already registered!";
                emailInput.focus();
                emailInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }

            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: 'User validated successfully!',
                confirmButtonColor: '#333',
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                form.submit();
            });
        }
    });
});
</script>

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

<?php include "../layouts/footer.php"; ?>