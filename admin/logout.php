<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../common/session.php';

start_app_session();
$_SESSION = [];
session_destroy();

header('Location: ' . rtrim(APP_URL, '/') . '/admin/signin.php');
exit;
