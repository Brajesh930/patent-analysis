<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/Logger.php';

Logger::info("User logged out: " . ($_SESSION['username'] ?? 'unknown'));
session_destroy();
header('Location: login.php');
exit;
