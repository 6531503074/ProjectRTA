<?php
include "../config/db.php";

if (!isset($_SESSION["user"])) {
    header("Location: ../auth/login.php");
    exit;
}

function checkRole($role) {
    if ($_SESSION["user"]["role"] !== $role) {
        die("Access denied");
    }
}
?>