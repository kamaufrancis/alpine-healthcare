<?php
require_once 'core/auth.php';
logoutUser();
header('Location: login.php');
exit();
?>