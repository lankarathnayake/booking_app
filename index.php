<?php
require_once __DIR__ . '/config.php';
header('Location: ' . rtrim(APP_URL, '/') . '/admin/signin.php');
exit;
