<?php
include "../middleware/auth.php";
checkRole("admin");
?>

<h1>Admin Dashboard</h1>
<p>Manage users</p>
<a href="../auth/logout.php">Logout</a>
