<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../admin_login.php");
    exit();
}

header("Location: all_applications.php");
exit();
?>
