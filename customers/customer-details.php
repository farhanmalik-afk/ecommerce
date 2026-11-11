<?php
session_start();
include "../config.php";

// Check karein ke URL mein id mojood hai ya nahi
if (isset($_GET['id'])) {
    $customer_id = intval($_GET['id']);

    // Us specific customer ka data database se nikalne ki query
    $stmt = $conn->prepare("SELECT * FROM customers WHERE id = ? AND is_deleted = 0");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $customer = $result->fetch_assoc();
    } else {
        header("Location: customers.php");
        exit();
    }
    $stmt->close();
} else {
    header("Location: customers.php");
    exit();
}

// Status Toggle Action Handle Karna (Block/Unblock)
if (isset($_GET['toggle_status'])) {
    $t_id = intval($_GET['toggle_id']);
    
    $stmt = $conn->prepare("UPDATE customers SET status = CASE WHEN status = 'Active' THEN 'Inactive' ELSE 'Active' END WHERE id = ?");
    $stmt->bind_param("i", $t_id);
    
    if ($stmt->execute()) {
        $_SESSION['swal_success'] = "Customer status updated successfully!";
    } else {
        $_SESSION['swal_error'] = "Failed to update status!";
    }
    $stmt->close();
    
    header("Location: customer-details.php?id=" . $t_id);
    exit();
}

$pageTitle = 'Customer details';
$parent = ['Customers', 'customers.php'];
include __DIR__ . '/../layouts/header.php';

$name = $customer['name'] ?? '';
$email = $customer['email'] ?? '';
$phone = $customer['phone'] ?? 'N/A';
$city = $customer['city'] ?? 'N/A';
$country = $customer['country'] ?? 'Pakistan';
$address = $customer['address'] ?? 'N/A';
$customerGroup = $customer['customer_group'] ?? 'Retail';
$status = $customer['status'] ?? 'Active';
$credentialEmailCsrf = $_SESSION['credential_email_csrf'] ??= bin2hex(random_bytes(32));
$initial = strtoupper(substr($name, 0, 1));
$date = isset($customer['created_at']) ? date('d M Y', strtotime($customer['created_at'])) : 'N/A';

$status_badge_class = ($status == 'Active') ? 'status-active' : 'status-secondary';
$block_btn_text = ($status == 'Active') ? 'Block customer' : 'Unblock customer';
$block_btn_class = ($status == 'Active') ? 'btn-outline-danger' : 'btn-outline-success';
$block_icon = ($status == 'Active') ? 'bi-slash-circle' : 'bi-check-circle';
?>

<!-- SweetAlert2 CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <section class="page-head d-flex flex-wrap justify-content-between align-items-start gap-3">
      <div>
        <h1>Customer details</h1>
        <p>Member since <?php echo $date; ?></p>
      </div>
      <!-- Professional Buttons Group -->
      <div class="d-flex flex-wrap gap-2">
        <a href="customers.php" class="btn btn-outline-dark"><i class="bi bi-arrow-left me-1"></i>Back</a>
        <a href="edit-customer.php?id=<?php echo $customer['id']; ?>" class="btn btn-dark"><i class="bi bi-pencil-square me-1"></i>Edit Customer</a><!-- Send Email Button with Dark Styling -->
<button type="button" id="sendCredentialsButton" onclick="confirmSendEmail(<?php echo (int) $customer_id; ?>)" class="btn btn-dark">
    <i class="bi bi-envelope me-1"></i> Send email
</button>

<script>
const credentialEmailCsrf = <?= json_encode($credentialEmailCsrf, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;

function confirmSendEmail(id) {
    Swal.fire({
        title: 'Send Login Credentials?',
        text: "Are you sure you want to send the login credentials to this customer via email?",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, send it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            const button = document.getElementById('sendCredentialsButton');
            button.disabled = true;
            Swal.fire({
                title: 'Sending Email...',
                text: 'Please wait',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => Swal.showLoading()
            });

            fetch('send-credentials.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-Token': credentialEmailCsrf
                },
                body: JSON.stringify({ id: id })
            })
            .then(async (response) => {
                const data = await response.json();
                if (!response.ok || data.status !== 'success') {
                    throw new Error(data.message || 'The email could not be sent.');
                }
                return data;
            })
            .then((data) => {
                Swal.fire({
                    icon: 'success',
                    title: 'Email Sent',
                    text: data.message,
                    confirmButtonColor: '#14243d'
                });
            })
            .catch((error) => {
                Swal.fire({
                    icon: 'error',
                    title: 'Email Not Sent',
                    text: error.message || 'Please try again.',
                    confirmButtonColor: '#d33'
                });
            })
            .finally(() => {
                button.disabled = false;
            });
        }
    });
}
</script>
      </div>
    </section>

    <section class="card mb-4">
      <div class="card-body d-flex flex-wrap align-items-center gap-3">
        <span class="avatar avatar-lg bg-a"><?php echo $initial; ?></span>
        <div class="me-auto">
          <h5 class="mb-0"><?php echo htmlspecialchars($name); ?></h5>
          <p class="text-muted mb-0"><?php echo htmlspecialchars($email); ?></p>
        </div>
        <span class="status <?php echo $status_badge_class; ?>"><?php echo $status; ?></span>
        <button type="button" class="btn <?php echo $block_btn_class; ?>" onclick="confirmStatusChange(<?php echo $customer['id']; ?>, '<?php echo $status; ?>')">
          <i class="bi <?php echo $block_icon; ?> me-1"></i><?php echo $block_btn_text; ?>
        </button>
      </div>
    </section>

    <!-- Flexbox Layout for Details Sections -->
    <section class="d-flex flex-wrap gap-3 mb-4">
      
      <!-- Contact Card -->
      <div class="flex-fill" style="min-width: 300px;">
        <div class="card h-100">
          <div class="card-body">
            <h6 class="card-title mb-3">Contact Information</h6>
            <ul class="info-list list-unstyled mb-0">
              <li class="mb-2"><b>Email:</b> <?php echo htmlspecialchars($email); ?></li>
              <li class="mb-2"><b>Phone:</b> <?php echo htmlspecialchars($phone); ?></li>
              <li class="mb-2"><b>Group:</b> <?php echo htmlspecialchars($customerGroup); ?></li>
              <li class="mb-2"><b>Last login:</b> N/A</li>
              <li class="mb-0"><b>Email verified:</b> Yes</li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Address Card -->
      <div class="flex-fill" style="min-width: 300px;">
        <div class="card h-100">
          <div class="card-body">
            <h6 class="card-title mb-3">Shipping Address</h6>
            <p class="mb-0 text-muted">
              <strong class="text-dark">Default Address</strong> <span class="status status-active">Default</span><br>
              <?php echo htmlspecialchars($address); ?><br>
              <?php echo htmlspecialchars($city); ?>, <?php echo htmlspecialchars($country); ?>
            </p>
          </div>
        </div>
      </div>

    </section>

<script>
function confirmStatusChange(id, currentStatus) {
    let targetStatus = (currentStatus === 'Active') ? 'Inactive' : 'Active';
    let messageText = 'Are you sure you want to make this customer ' + targetStatus + '?';

    Swal.fire({
        title: 'Change Status?',
        text: messageText,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, change it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'customer-details.php?id=' + id + '&toggle_status=1&toggle_id=' + id;
        }
    });
}
</script>

<?php if (isset($_SESSION['swal_success'])): ?>
<script>
    Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: '<?php echo $_SESSION['swal_success']; ?>',
        timer: 2500,
        showConfirmButton: false
    });
</script>
<?php unset($_SESSION['swal_success']); endif; ?>

<?php include __DIR__ . '/../layouts/footer.php'; ?>