<?php
// in file: logout.php
session_start();
session_unset();
session_destroy();
header('Location: /login.php'); // Adjust path if needed
exit();