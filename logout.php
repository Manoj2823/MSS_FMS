<?php require __DIR__ . '/includes/bootstrap.php';
if (!empty($_SESSION['uid']))
    audit('logout', 'user', (int) $_SESSION['uid']);
$_SESSION = [];
session_destroy();
header('Location: ' . url('login.php'));
