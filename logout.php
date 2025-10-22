<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

session_destroy();
header('Location: ' . BASE_URL . 'login.php');
exit();
?>
