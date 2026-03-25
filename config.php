<?php
// config.php - настройки подключения к БД

// Отключаем вывод ошибок на экран
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Устанавливаем кодировку
header('Content-Type: text/html; charset=utf-8');

$host = 'localhost';
// ЗАМЕНИТЕ НА СВОИ ДАННЫЕ БАЗЫ ДАННЫХ!
$dbname = 'rasskas4_prodagi'; 
$username = 'rasskas4_prodagi';
$password = '157359258JJaa';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    // Логируем ошибку и возвращаем JSON ошибку
    error_log("Database connection error: " . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}