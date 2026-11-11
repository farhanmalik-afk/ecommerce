<?php
session_start();
include "../config.php";

// Delete Category
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    try {
        $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->bind_param("i", $del_id);
        if ($stmt->execute()) {
            $_SESSION['swal_success'] = "Category deleted successfully!";
        }
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        $_SESSION['swal_error'] = "Cannot delete this category because products are linked to it!";
    }
    header("Location: categories.php");
    exit();
}

// Status Toggle
if (isset($_GET['toggle_id'])) {
    $t_id = intval($_GET['toggle_id']);
    $stmt = $conn->prepare("UPDATE categories SET status = CASE WHEN status = 'Active' THEN 'Inactive' ELSE 'Active' END WHERE id = ?");
    $stmt->bind_param("i", $t_id);
    if ($stmt->execute()) {
        $_SESSION['swal_success'] = "Category status updated successfully!";
    } else {
        $_SESSION['swal_error'] = "Failed to update status!";
    }
    $stmt->close();
    header("Location: categories.php");
    exit();
}

$search = trim($_GET['search'] ?? '');
$pageTitle = 'Categories';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Categories Management - Dashboard</title>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
  <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>

<div class="app">
  <?php include "../layouts/sidebar.php"; ?>

  <main class="app-main">
    <?php include "../layouts/topbar.php"; ?>

    <section class="page-head">
      <h1>Categories Management</h1>
      <p>Manage product categories for your store and POS system.</p>
    </section>

    <!-- Search Card -->
    <div class="card border-0 shadow-sm p-3 mb-4">
        <form method="GET" action="" class="row g-3 align-items-center">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Search category name..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-dark w-100">Search</button>
                <?php if (!empty($search)): ?>
                    <a href="categories.php" class="btn btn-outline-secondary">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Categories List Table -->
    <section class="row mb-4">
      <div class="col-12">
        <div class="card table-card border-0 shadow-sm">
          <div class="table-card-header d-flex justify-content-between align-items-center p-3 border-bottom bg-white">
            <h6 class="card-title mb-0 fw-bold">All Categories</h6>
            <a href="add-category.php" class="btn btn-dark"><i class="bi bi-plus-lg me-1"></i>Add Category</a>
          </div>

          <div class="table-responsive">
            <table class="table data-table align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th class="ps-4">ID</th>
                  <th>Category Name</th>
                  <th>Description</th>
                  <th>Status</th>
                  <th>Created Date</th>
                  <th class="text-end pe-4">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $sql = "SELECT * FROM categories WHERE 1=1";
                $params = [];
                $types = "";

                if (!empty($search)) {
                    $sql .= " AND name LIKE ?";
                    $params[] = "%" . $search . "%";
                    $types .= "s";
                }

                $sql .= " ORDER BY id DESC";
                $stmt = $conn->prepare($sql);
                if (!empty($params)) {
                    $stmt->bind_param($types, ...$params);
                }
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $status = $row['status'] ?? 'Active';
                        $status_badge = ($status == 'Active') ? 'bg-success' : 'bg-secondary';
                        $date = date('d M Y', strtotime($row['created_at']));

                        echo "<tr>
                                <td class='ps-4'>#{$row['id']}</td>
                                <td><strong>" . htmlspecialchars($row['name']) . "</strong></td>
                                <td class='text-muted'>" . htmlspecialchars($row['description'] ?? 'N/A') . "</td>
                                <td><span class='badge {$status_badge}'>{$status}</span></td>
                                <td><small class='text-muted'>{$date}</small></td>
                                <td class='text-end pe-4'>
                                  <a href='edit-category.php?id=" . $row['id'] . "' class='text-secondary me-2' title='Edit'><i class='bi bi-pencil-square'></i></a>
                                  <a href='#' onclick='confirmStatusChange(" . $row['id'] . ", \"" . $status . "\")' class='text-warning me-2' title='Toggle Status'><i class='bi bi-slash-circle'></i></a>
                                  <a href='#' onclick='confirmDelete(" . $row['id'] . ")' class='text-danger' title='Delete'><i class='bi bi-trash'></i></a>
                                </td>
                              </tr>";
                    }
                } else {
                    echo "<tr><td colspan='6' class='text-center text-muted py-4'>No categories found.</td></tr>";
                }
                ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </section>
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function confirmDelete(id) {
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'categories.php?delete_id=' + id;
        }
    });
}

function confirmStatusChange(id, currentStatus) {
    let targetStatus = (currentStatus === 'Active') ? 'Inactive' : 'Active';
    Swal.fire({
        title: 'Change Status?',
        text: 'Make this category ' + targetStatus + '?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, change it!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'categories.php?toggle_id=' + id;
        }
    });
}
</script>

<?php if (isset($_SESSION['swal_success'])): ?>
<script>
    Swal.fire({ icon: 'success', title: 'Success!', text: '<?php echo $_SESSION['swal_success']; ?>', timer: 2000, showConfirmButton: false });
</script>
<?php unset($_SESSION['swal_success']); endif; ?>

<?php if (isset($_SESSION['swal_error'])): ?>
<script>
    Swal.fire({ icon: 'error', title: 'Notice', text: '<?php echo $_SESSION['swal_error']; ?>', confirmButtonColor: '#d33' });
</script>
<?php unset($_SESSION['swal_error']); endif; ?>

</body>
</html>