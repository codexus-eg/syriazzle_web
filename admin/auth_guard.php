<?php
require_once __DIR__ . '/../php/db_connect.php';
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || !isset($_SESSION['admin_permissions'])) {
    
    session_unset();
    session_destroy();
    
    $redirect_url = '/admin/index.php?error=unauthorized';

    header('Location: ' . $redirect_url);
    exit;
}

$admin_permissions = $_SESSION['admin_permissions'] ?? [];
$admin_governorate_id = $_SESSION['admin_governorate_id'] ?? null;
$is_super_admin = (isset($_SESSION['admin_role_id']) && $_SESSION['admin_role_id'] == 1);

if (!function_exists('hasPermission')) {
    function hasPermission($permission_name) {
        global $admin_permissions, $is_super_admin;
        
        if ($is_super_admin) {
            return true;
        }
        return in_array($permission_name, $admin_permissions);
    }
}
?>