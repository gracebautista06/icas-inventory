<?php
// ============================================================
//  auth_check.php — Session guard for protected pages
//  Usage: require_once '../includes/auth_check.php';
//         require_role('admin');   // or 'instructor'
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function require_role(string $role): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: ../index.php?error=Please+log+in+to+continue');
        exit;
    }
    if ($_SESSION['user_role'] !== $role) {
        header('Location: ../index.php?error=Access+denied');
        exit;
    }
}

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function current_user(): array
{
    return [
        'id'   => $_SESSION['user_id']   ?? null,
        'name' => $_SESSION['full_name'] ?? '',
        'role' => $_SESSION['user_role'] ?? '',
    ];
}
?>