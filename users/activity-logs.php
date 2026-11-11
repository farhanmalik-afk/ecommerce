<?php
session_start();
require_once "../config.php";
require_once "../includes/rbac.php";

enforceAccess($conn, 'user_management', 'view');

$pageTitle = 'Activity Logs';
include "../layouts/header.php";
?>

<div class="app-main dashboard-main">
    <section class="page-head mb-4">
        <h1>Activity Logs</h1>
        <p class="text-muted">Monitor user actions and system security audit trails.</p>
    </section>

    <div class="card border-0 shadow-sm table-card dashboard-panel">
        <div class="table-responsive">
            <table class="table data-table align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-3">ID</th>
                        <th>User</th>
                        <th>Module</th>
                        <th>Action Performed</th>
                        <th>IP Address</th>
                        <th class="text-end pe-3">Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $logsQuery = $conn->query("SELECT al.*, u.name as user_name FROM activity_logs al LEFT JOIN users u ON al.user_id = u.id ORDER BY al.created_at DESC LIMIT 50");
                    if ($logsQuery && $logsQuery->num_rows > 0) {
                        while ($log = $logsQuery->fetch_assoc()) {
                            ?>
                            <tr>
                                <td class="ps-3 fw-bold">#<?php echo $log['id']; ?></td>
                                <td><?php echo htmlspecialchars($log['user_name'] ?? 'System / Guest'); ?></td>
                                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($log['module']); ?></span></td>
                                <td><?php echo htmlspecialchars($log['action']); ?></td>
                                <td><code><?php echo htmlspecialchars($log['ip_address']); ?></code></td>
                                <td class="text-end pe-3 text-muted"><?php echo $log['created_at']; ?></td>
                            </tr>
                            <?php
                        }
                    } else {
                        echo "<tr><td colspan='6' class='text-center text-muted py-3'>No activity logs recorded yet.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include "../layouts/footer.php"; ?>