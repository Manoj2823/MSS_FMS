<?php require __DIR__ . "/includes/bootstrap.php";
header("Location: " . url(empty($_SESSION["uid"]) ? "login.php" : "dashboard.php"));
