<?php
// simple_export.php - простой экспорт сделок
session_start();
require_once 'config.php';

// Проверяем авторизацию
if (!isset($_SESSION['user_id'])) {
    die('Не авторизован');
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="deals.csv"');

// Получаем данные
$stmt = $pdo->query("SELECT * FROM deals ORDER BY auth_date DESC");
$deals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Выводим данные
$output = fopen('php://output', 'w');
fwrite($output, "\xEF\xBB\xBF"); // BOM

// Заголовки
fputcsv($output, ['ID', 'Дата', 'Клиент', 'Партнер', 'Сумма', 'Статус'], ';');

// Данные
foreach ($deals as $deal) {
    $status = $deal['status'] == 'completed' ? 'Завершено' : 'В процессе';
    fputcsv($output, [
        $deal['id'],
        $deal['auth_date'],
        $deal['client_name'],
        $deal['partner'],
        $deal['contract_amount'],
        $status
    ], ';');
}

fclose($output);
exit;
?>