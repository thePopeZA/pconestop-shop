<?php
require_once __DIR__ . '/_bootstrap.php';
unset($_SESSION['admin']);
session_regenerate_id(true);
flash('You have been logged out.', 'info');
redirect('admin/login.php');
