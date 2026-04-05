<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../admin_login.php");
    exit();
}

header("Location: manage_profile.php");
exit();
?>
