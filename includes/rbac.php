<?php
// includes/rbac.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Temporary session sets Super Admin (Role 1) for local testing
$_SESSION['admin_id'] = 1; // Admin ID jiska role_id = 1 ho

require_once __DIR__ . '/../config.php';

// Check if admin is logged in
function checkAuth() {
    if (!isset($_SESSION['admin_id']) && !isset($_SESSION['user_id'])) {
        header("Location: " . getBaseUrl() . "login.php");
        exit();
    }
}

// Get current logged in admin ID
function getCurrentAdminId() {
    return $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 1;
}

// Log admin activity
function logActivity($conn, $adminId, $action, $module) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $stmt = $conn->prepare("INSERT INTO activity_logs (admin_id, action, module, ip_address) VALUES (?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("isss", $adminId, $action, $module, $ip);
        $stmt->execute();
        $stmt->close();
    }
}

// Check if admin has specific permission
function hasPermission($conn, $adminId, $module, $action = 'view') {
    // Fetch admin role
    $stmt = $conn->prepare("SELECT role_id FROM admins WHERE id = ? AND status = 'Active'");
    if (!$stmt) return true; // Fallback if column missing yet
    $stmt->bind_param("i", $adminId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $roleId = $row['role_id'] ?? 1;
        
        // Super Admin (Role ID 1) has full access
        if ($roleId == 1) return true;

        // Check role permissions
        $permQuery = $conn->prepare("
            SELECT 1 FROM role_permissions rp
            JOIN permissions p ON rp.permission_id = p.id
            WHERE rp.role_id = ? AND p.module_name = ? AND p.action_name = ?
        ");
        if ($permQuery) {
            $permQuery->bind_param("iss", $roleId, $module, $action);
            $permQuery->execute();
            $permQuery->store_result();
            return $permQuery->num_rows > 0;
        }
    }
    return true; // Default fallback to prevent lockouts during setup
}

// Global check function used in sidebar (`appUserCan`)
if (!function_exists('appUserCan')) {
    function appUserCan($module) {
        global $conn;
        $adminId = getCurrentAdminId();
        return hasPermission($conn, $adminId, $module, 'view');
    }
}

// Enforce access control on restricted pages
function enforceAccess($conn, $module, $action = 'view') {
    checkAuth();
    $adminId = getCurrentAdminId();
    if (!hasPermission($conn, $adminId, $module, $action)) {
        logActivity($conn, $adminId, "Unauthorized Access Attempt to {$module} ({$action})", "Security");
        header("Location: " . getBaseUrl() . "erorrs/403.php");
        exit();
    }
}

function getBaseUrl() {
    return "/ecommerce/"; 
}
?>