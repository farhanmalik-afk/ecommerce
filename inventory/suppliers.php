<?php
session_start();
include "../config.php";

// Create suppliers table if not exists
@$conn->query("CREATE TABLE IF NOT EXISTS suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150),
    phone VARCHAR(50),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
$supplier_search = trim($_GET['search'] ?? '');

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if (empty($name)) {
        $error = "Supplier name is required.";
    } else {
        $stmt = $conn->prepare("INSERT INTO suppliers (name, email, phone, address) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $name, $email, $phone, $address);
        if ($stmt->execute()) {
            $success = true;
        } else {
            $error = "Database Error: " . $conn->error;
        }
        $stmt->close();
    }
}

$pageTitle = 'Suppliers Management';
include "../layouts/header.php";
?>

<!-- SweetAlert2 CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="app-main">
    <section class="page-head mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h1>Suppliers Management</h1>
            <p class="text-muted">Manage product vendors and supplier contact details.</p>
        </div>
        <div>
            <a href="inventory.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to Inventory
            </a>
        </div>
    </section>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                Swal.fire({
                    icon: 'success',
                    title: 'Supplier Added!',
                    text: 'New supplier registered successfully.',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    window.location.href = 'suppliers.php';
                });
            });
        </script>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Add Supplier Form -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 font-weight-bold text-dark">Add New Supplier</h6>
                </div>
                <div class="card-body">
                    <form action="suppliers.php" method="POST">
                        <div class="mb-3">
                            <label class="form-label font-weight-bold">Supplier Name</label>
                            <input type="text" name="name" class="form-control" placeholder="Company or person name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-control" placeholder="supplier@example.com">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone Number</label>
                            <input type="text" name="phone" class="form-control" placeholder="+92 300 1234567">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="2" placeholder="Office location..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-dark w-100">Save Supplier</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Suppliers Table -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h6 class="m-0 font-weight-bold text-dark">Supplier List</h6>
                    <form method="GET" action="suppliers.php" class="d-flex gap-2">
                        <input type="search" name="search" class="form-control form-control-sm" placeholder="Search suppliers..." value="<?php echo htmlspecialchars($supplier_search, ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="submit" class="btn btn-dark btn-sm" aria-label="Search suppliers"><i class="bi bi-search"></i></button>
                    </form>
                </div>
                <div class="table-responsive">
                    <table class="table data-table align-middle mb-0" style="width: 100%;">
                        <thead>
                            <tr>
                                <th class="ps-3">Name</th>
                                <th>Contact</th>
                                <th>Phone</th>
                                <th>Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($supplier_search !== '') {
                                $supplier_stmt = $conn->prepare("SELECT * FROM suppliers WHERE name LIKE ? OR email LIKE ? OR phone LIKE ? OR address LIKE ? ORDER BY id DESC");
                                $supplier_pattern = '%' . $supplier_search . '%';
                                $supplier_stmt->bind_param("ssss", $supplier_pattern, $supplier_pattern, $supplier_pattern, $supplier_pattern);
                                $supplier_stmt->execute();
                                $sup_res = $supplier_stmt->get_result();
                                $supplier_stmt->close();
                            } else {
                                $sup_res = $conn->query("SELECT * FROM suppliers ORDER BY id DESC");
                            }
                            if ($sup_res && $sup_res->num_rows > 0) {
                                while ($s = $sup_res->fetch_assoc()) {
                                    ?>
                                    <tr>
                                        <td class="ps-3"><strong><?php echo htmlspecialchars($s['name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($s['email'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($s['phone'] ?? 'N/A'); ?></td>
                                        <td><small class="text-muted"><?php echo htmlspecialchars($s['address'] ?? 'N/A'); ?></small></td>
                                    </tr>
                                    <?php
                                }
                            } else {
                                echo "<tr><td colspan='4' class='text-center text-muted py-3'>No suppliers found.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include "../layouts/footer.php"; ?>