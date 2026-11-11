<?php
session_start();
include "../config.php";

// Slug generate karne ka function
function createSlug($str) {
    // Lowercase karna aur special characters hatana
    $slug = strtolower(trim($str));
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    return trim($slug, '-');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    // Name se automatic slug banana
    $slug = createSlug($name);

    if (!empty($name)) {
        // Check karna ke slug pehle se database mein mojood toh nahi
        $check = $conn->prepare("SELECT id FROM categories WHERE slug = ?");
        $check->bind_param("s", $slug);
        $check->execute();
        $check->store_result();
        
        if ($check->num_rows > 0) {
            // Agar slug pehle se hai toh sath mein unique number lagana
            $slug = $slug . '-' . time();
        }
        $check->close();

        // Database mein insert ki query (slug column ke sath)
        $stmt = $conn->prepare("INSERT INTO categories (name, slug, status) VALUES (?, ?, 'Active')");
        $stmt->bind_param("ss", $name, $slug);
        
        if ($stmt->execute()) {
            $_SESSION['swal_success'] = "Category added successfully!";
            header("Location: categories.php");
            exit();
        } else {
            $error = "Failed to add category.";
        }
        $stmt->close();
    } else {
        $error = "Category name is required.";
    }
}
$pageTitle = 'Add Category';
$parent = ['Categories', 'categories.php'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Add Category - Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
  <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="app">
  <?php include "../layouts/sidebar.php"; ?>
  <main class="app-main">
    <?php include "../layouts/topbar.php"; ?>
    <section class="page-head">
      <h1>Add New Category</h1>
      <p>Create a category to group your products.</p>
    </section>

    <div class="row">
      <div class="col-md-6">
        <div class="card border-0 shadow-sm p-4">
          <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
          <?php endif; ?>
          <form method="POST" action="">
            <div class="mb-3">
              <label class="form-label fw-bold">Category Name *</label>
              <input type="text" name="name" class="form-control" placeholder="Enter category name (e.g. Mobile Accessories)" required>
            </div>
            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-dark">Save Category</button>
              <a href="categories.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>