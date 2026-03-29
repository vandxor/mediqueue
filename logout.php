<?php
// logout.php — Destroy session and redirect to login
include 'auth.php';

// Clear everything
session_unset();
session_destroy();

header("Location: login.php");
exit();
