<?php
// full_export.php - полный экспорт всех данных
session_start();
require_once 'config.php';

// Проверяем авторизацию
if (!isset($_SESSION['user_id'])) {
    die('Не авторизован');
}

// Получаем параметры фильтра
$dateFrom = $_GET['dateFrom'] ?? null;
$dateTo = $_GET['dateTo'] ?? null;

// Формируем SQL запрос
$sql = "SELECT * FROM deals WHERE 1=1";
$params = [];

if ($dateFrom) {
    $sql .= " AND auth_date >= ?";
    $params[] = $dateFrom;
}

if ($dateTo) {
    $sql .= " AND auth_date <= ?";
    $params[] = $dateTo;
}

$sql .= " ORDER BY auth_date DESC";

// Выполняем запрос
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$deals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Создаем имя файла
$filename = "deals_export_" . date('Y-m-d_H-i') . ".csv";

// Устанавливаем заголовки
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Создаем CSV содержимое
$csv = "\xEF\xBB\xBF"; // UTF-8 BOM

// Заголовки столбцов
$headers = [
    'ID',
    'Дата авторизации',
    'Партнер',
    'Юридическое лицо', 
    'Клиент',
    'Страна',
    'Банк',
    'Ставка %',
    'Срок рассрочки',
    'Номер договора',
    'Сумма договора',
    'Сумма информирования',
    'Сумма без информирования',
    'Логистика',
    'Трек номер',
    'Менеджер',
    'Сумма в валюте',
    'Email клиента',
    'Телефон клиента',
    'Статус'
];

$csv .= implode(';', $headers) . "\n";

// Данные
foreach ($deals as $deal) {
    $status = $deal['status'] == 'completed' ? 'Завершено' : 'В процессе';
    
    $row = [
        $deal['id'],
        $deal['auth_date'],
        $deal['partner'],
        $deal['legal_entity'],
        $deal['client_name'],
        $deal['country'],
        $deal['bank'],
        $deal['interest_rate'],
        $deal['installment_period'],
        $deal['contract_number'],
        $deal['contract_amount'],
        $deal['info_amount'],
        $deal['no_info_amount'],
        $deal['logistics'],
        $deal['track_number'] ?? '',
        $deal['manager'],
        $deal['currency_amount'] ?? 0,
        $deal['client_email'] ?? '',
        $deal['client_phone'] ?? '',
        $status
    ];
    
    // Экранируем значения
    $row = array_map(function($value) {
        if (strpos($value, ';') !== false || strpos($value, '"') !== false) {
            $value = '"' . str_replace('"', '""', $value) . '"';
        }
        return $value;
    }, $row);
    
    $csv .= implode(';', $row) . "\n";
}

// Выводим CSV
echo $csv;
exit;
?>