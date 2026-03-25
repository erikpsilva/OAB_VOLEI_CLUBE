<?php
if (session_status() === PHP_SESSION_NONE) session_start();
unset($_SESSION['jogador']);
session_destroy();
header('Location: ' . BASE_URL . '/login');
exit;
