<?php
// users/edit-user.php
require_once "../config.php";
require_once "../includes/rbac.php";

enforceAccess($conn, 'user_management', 'edit');

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: users.php");
    exit();
}

$error = '';
$successScript = '';

$stmt = $conn->prepare("SELECT a.*, r.name AS role_name FROM admins a JOIN roles r ON r.id = a.role_id WHERE a.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    header("Location: users.php");
    exit();
}
if (strtolower(trim((string) $user['role_name'])) === 'super admin' && !isSuperAdmin()) {
    sendAppError(403, 'Only the Super Admin can manage a Super Admin account.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $role_id = intval($_POST['role_id'] ?? 3);
    $status = $_POST['status'] ?? 'Active';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Refresh the page and try again.';
    } elseif (empty($name) || empty($email)) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif ($id === getCurrentAdminId() && $role_id !== (int) $user['role_id']) {
        $error = 'You cannot change your own role.';
    } elseif ($id === getCurrentAdminId() && $status !== 'Active') {
        $error = 'You cannot disable your own account.';
    } elseif (!in_array($status, ['Active', 'Inactive', 'Suspended'], true)) {
        $error = 'Select a valid account status.';
    } elseif (!empty($password) && $password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        $roleCheck = $conn->prepare('SELECT name FROM roles WHERE id = ? AND is_active = 1 LIMIT 1');
        $roleCheck->bind_param('i', $role_id);
        $roleCheck->execute();
        $selectedRoleName = $roleCheck->get_result()->fetch_column();
        $roleCheck->close();
        if ($selectedRoleName === false) {
            $error = 'Select an active user role.';
        } elseif (strtolower(trim((string) $selectedRoleName)) === 'super admin' && !isSuperAdmin()) {
            $error = 'Only the Super Admin can assign the Super Admin role.';
        } elseif (
            strtolower(trim((string) $user['role_name'])) === 'super admin'
            && ($status !== 'Active' || strtolower(trim((string) $selectedRoleName)) !== 'super admin')
        ) {
            $activeCount = $conn->query(
                "SELECT COUNT(*) AS total FROM admins a JOIN roles r ON r.id = a.role_id
                 WHERE a.status = 'Active' AND LOWER(r.name) = 'super admin'"
            );
            if ($activeCount === false) {
                error_log('Unable to verify active Super Admin accounts: ' . $conn->error);
                $error = 'The Super Admin account could not be verified. Please try again.';
            } elseif ((int) $activeCount->fetch_assoc()['total'] <= 1) {
                $error = 'The last active Super Admin account cannot be disabled.';
            }
        }

        if ($error !== '') {
            $emailCheck = null;
        } else {
        $emailCheck = $conn->prepare("SELECT id FROM admins WHERE email = ? AND id != ?");
        $emailCheck->bind_param("si", $email, $id);
        $emailCheck->execute();
        $emailCheck->store_result();

        if ($emailCheck->num_rows > 0) {
            $error = "An account with this email already exists.";
            $emailCheck->close();
        } else {
            $emailCheck->close();

            $avatarName = $user['avatar'];
            $uploadedAvatar = $_FILES['avatar'] ?? null;
            $uploadDir = __DIR__ . '/../uploads/avatars/';
            if ($uploadedAvatar && $uploadedAvatar['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($uploadedAvatar['error'] !== UPLOAD_ERR_OK) {
                    $error = 'The avatar upload failed. Please try again.';
                } elseif ($uploadedAvatar['size'] > 2 * 1024 * 1024) {
                    $error = 'Avatar image size must be no larger than 2 MB.';
                } else {
                    try {
                        $avatarName = safe_upload($uploadedAvatar, $uploadDir, 2 * 1024 * 1024);
                        if (!empty($user['avatar']) && file_exists($uploadDir . $user['avatar'])) {
                            @unlink($uploadDir . $user['avatar']);
                        }
                    } catch (RuntimeException $exception) {
                        $error = 'Choose a JPG, PNG, or WebP avatar image.';
                    }
                }
            }

            if ($error === '') {
                if (!empty($password)) {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $updateStmt = $conn->prepare("UPDATE admins SET name = ?, email = ?, password_hash = ?, phone = ?, address = ?, role_id = ?, status = ?, avatar = ? WHERE id = ?");
                    $updateStmt->bind_param("sssssissi", $name, $email, $hashedPassword, $phone, $address, $role_id, $status, $avatarName, $id);
                } else {
                    $updateStmt = $conn->prepare("UPDATE admins SET name = ?, email = ?, phone = ?, address = ?, role_id = ?, status = ?, avatar = ? WHERE id = ?");
                    $updateStmt->bind_param("ssssissi", $name, $email, $phone, $address, $role_id, $status, $avatarName, $id);
                }

                if ($updateStmt->execute()) {
                    logActivity($conn, getCurrentAdminId(), "Updated user ID: $id ($name)", "User Management");
                    $successScript = "
                        Swal.fire({
                            icon: 'success',
                            title: 'Updated!',
                            text: 'User details updated successfully!',
                            confirmButtonColor: '#343a40',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.href = 'users.php';
                        });
                    ";
                } else {
                    error_log('Unable to update user: ' . $updateStmt->error);
                    $error = 'The user could not be updated. Please try again.';
                }
                $updateStmt->close();
            }
        }
        }
    }
}

$pageTitle = 'Edit User';
include "../layouts/header.php";
?>

<div class="app-main dashboard-main">
    <section class="page-head mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h1>Edit User</h1>
            <p class="text-muted">Update system user details, roles, and permissions.</p>
        </div>
        <?php if (appUserCan('user_management')): ?><div>
            <a href="users.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to Users
            </a>
        </div><?php endif; ?>
    </section>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm dashboard-panel">
                <div class="card-body p-4">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger py-2"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>

                    <form id="editUserForm" action="" method="POST" enctype="multipart/form-data" autocomplete="off" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">New Password <span class="text-muted small">(Leave blank to keep current)</span></label>
                                <div class="input-group">
                                    <input type="password" name="password" id="userPassword" class="form-control" autocomplete="new-password">
                                    <button class="btn btn-outline-secondary toggle-password" type="button" data-target="userPassword">
                                        <i class="bi bi-eye" id="toggleIcon_userPassword"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Confirm New Password</label>
                                <div class="input-group">
                                    <input type="password" name="confirm_password" id="userConfirmPassword" class="form-control" autocomplete="new-password">
                                    <button class="btn btn-outline-secondary toggle-password" type="button" data-target="userConfirmPassword">
                                        <i class="bi bi-eye" id="toggleIcon_userConfirmPassword"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Phone Number</label>
                                <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">User Role <span class="text-danger">*</span></label>
                                <select name="role_id" class="form-select" required>
                                    <?php
                                    $roleFilter = isSuperAdmin() ? '' : " AND LOWER(name) <> 'super admin'";
                                    $rolesQuery = $conn->query("SELECT id, name FROM roles WHERE is_active = 1 $roleFilter ORDER BY id");
                                    if ($rolesQuery) {
                                        while ($role = $rolesQuery->fetch_assoc()) {
                                            $selected = ($role['id'] == $user['role_id']) ? 'selected' : '';
                                            echo "<option value='" . (int) $role['id'] . "' $selected>" . htmlspecialchars($role['name'], ENT_QUOTES, 'UTF-8') . "</option>";
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Address</label>
                            <textarea name="address" class="form-control" rows="2"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Account Status</label>
                                <select name="status" class="form-select">
                                    <option value="Active" <?php echo ($user['status'] === 'Active') ? 'selected' : ''; ?>>Active</option>
                                    <option value="Inactive" <?php echo ($user['status'] === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="Suspended" <?php echo ($user['status'] === 'Suspended') ? 'selected' : ''; ?>>Suspended</option>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Profile Avatar</label>
                                <div class="mb-2">
                                    <?php 
                                    $currentAvatar = isset($user['avatar']) ? trim($user['avatar']) : '';
                                    $avatarPreviewPath = '../uploads/default-avatar.png';

                                    if (!empty($currentAvatar)) {
                                        $serverPath = __DIR__ . '/../uploads/avatars/' . $currentAvatar;
                                        if (file_exists($serverPath)) {
                                            $avatarPreviewPath = '../uploads/avatars/' . rawurlencode($currentAvatar);
                                        }
                                    }
                                    ?>
                                    <img src="<?php echo $avatarPreviewPath; ?>" class="rounded-circle border" width="40" height="40" style="object-fit: cover;" alt="Current Avatar">
                                </div>
                                <input type="file" name="avatar" class="form-control" accept="image/*">
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <?php if (appUserCan('user_management')): ?><a href="users.php" class="btn btn-light border">Cancel</a><?php endif; ?>
                            <?php if (appUserCan('user_management', 'edit')): ?><button type="submit" class="btn btn-dark px-4">Update User</button><?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (!empty($successScript)): ?>
        <?php echo $successScript; ?>
    <?php endif; ?>

    document.querySelectorAll('.toggle-password').forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const targetInput = document.getElementById(targetId);
            const icon = document.getElementById('toggleIcon_' + targetId);
            if (targetInput.type === 'password') {
                targetInput.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                targetInput.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        });
    });
});
</script>

<?php include "../layouts/footer.php"; ?>