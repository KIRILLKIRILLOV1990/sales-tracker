<?php
session_start();

// Устанавливаем заголовки JSON по умолчанию
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Обработка preflight запросов
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Функция для отправки JSON ответа
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

// Функция для отправки ошибки
function sendError($message, $statusCode = 500) {
    sendJsonResponse(['error' => $message], $statusCode);
}

try {
    // Подключаем конфигурацию
    require_once 'config.php';
    
    // Получаем действие
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    // Проверяем авторизацию для защищенных действий
    $publicActions = ['login'];
    if (!in_array($action, $publicActions) && !isset($_SESSION['user_id'])) {
        sendError('Unauthorized', 401);
    }
    
    // Информация о текущем пользователе
    $current_user = isset($_SESSION['user_id']) ? [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'role' => $_SESSION['role'],
        'full_name' => $_SESSION['full_name']
    ] : null;
    
    // Функция проверки прав доступа
    function checkPermission($required_role) {
        global $current_user;
        
        if (!$current_user) return false;
        
        $role_hierarchy = [
            'manager' => 1,
            'logistics' => 2, 
            'admin' => 3
        ];
        
        return isset($role_hierarchy[$current_user['role']]) && 
               $role_hierarchy[$current_user['role']] >= $role_hierarchy[$required_role];
    }
    
    // Обработка действий
    switch ($action) {
        case 'login':
            login();
            break;
            
        case 'get_all_data':
            if (!checkPermission('manager') && $current_user['role'] != 'logistics') {
                sendError('Access denied', 403);
            }
            getAllData();
            break;
            
        case 'get_salaries':
            if (!checkPermission('manager')) {
                sendError('Access denied', 403);
            }
            getSalaries();
            break;
            
        // НОВЫЙ ACTION: Аналитика продаж
        case 'get_analytics':
            if (!checkPermission('admin')) {
                sendError('Access denied', 403);
            }
            getAnalytics();
            break;
            
        case 'get_pending_logistics_stats':
            getPendingLogisticsStats();
            break;
            
        case 'save_deal':
            if (!checkPermission('manager')) {
                sendError('Access denied', 403);
            }
            saveDeal();
            break;
            
        case 'update_deal_status':
            if (!checkPermission('logistics')) {
                sendError('Access denied', 403);
            }
            updateDealStatus();
            break;
            
        case 'add_partner':
        case 'add_bank':
        case 'add_legal_entity':
        case 'add_manager':
        case 'add_logistics':
        case 'add_country':
            // ЗАЩИТА: Только админы могут добавлять элементы в настройки
            if ($current_user['role'] !== 'admin') {
                sendError('Access denied to settings. Only administrators can modify settings.', 403);
            }
            // Исправляем названия таблиц
            $table_map = [
                'add_partner' => 'partners',
                'add_bank' => 'banks',
                'add_legal_entity' => 'legal_entities',
                'add_manager' => 'managers',
                'add_logistics' => 'logistics_types',
                'add_country' => 'countries'
            ];
            $table = $table_map[$action];
            addItem($table);
            break;
            
        case 'remove_item':
            // ЗАЩИТА: Только админы могут удалять элементы из настроек
            if ($current_user['role'] !== 'admin') {
                sendError('Access denied to settings. Only administrators can modify settings.', 403);
            }
            removeItem();
            break;
            
        // НОВЫЙ ACTION: Редактирование элементов настроек
        case 'update_item':
            // ЗАЩИТА: Только админы могут редактировать элементы в настройках
            if ($current_user['role'] !== 'admin') {
                sendError('Access denied to settings. Only administrators can modify settings.', 403);
            }
            updateItem();
            break;
            
        case 'delete_deal':
            if (!checkPermission('admin')) {
                sendError('Access denied', 403);
            }
            deleteDeal();
            break;
            
        case 'get_deal':
            if (!checkPermission('manager')) {
                sendError('Access denied', 403);
            }
            getDeal();
            break;
            
        case 'update_deal':
            if (!checkPermission('manager')) {
                sendError('Access denied', 403);
            }
            updateDeal();
            break;
            
        // Управление пользователями
        case 'get_users':
            if (!checkPermission('admin')) {
                sendError('Access denied', 403);
            }
            getUsers();
            break;
            
        case 'get_user':
            if (!checkPermission('admin')) {
                sendError('Access denied', 403);
            }
            getUser();
            break;
            
        case 'save_user':
            if (!checkPermission('admin')) {
                sendError('Access denied', 403);
            }
            saveUser();
            break;
            
        case 'delete_user':
            if (!checkPermission('admin')) {
                sendError('Access denied', 403);
            }
            deleteUser();
            break;
            
        default:
            sendError('Unknown action: ' . $action, 400);
            break;
    }
    
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    sendError('Internal server error: ' . $e->getMessage(), 500);
}

// Функции API

function login() {
    global $pdo;
    
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        sendError('Заполните все поля', 400);
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = TRUE");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            
            sendJsonResponse(['success' => true, 'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'role' => $user['role'],
                'full_name' => $user['full_name']
            ]]);
        } else {
            sendError('Неверный логин или пароль', 401);
        }
    } catch (PDOException $e) {
        sendError('Database error in login: ' . $e->getMessage());
    }
}

function getAllData() {
    global $pdo, $current_user;
    
    $data = [];
    
    try {
        // Получаем все справочники
        $tables = [
            'partners', 'banks', 'legal_entities', 
            'managers', 'logistics_types', 'countries'
        ];
        
        foreach ($tables as $table) {
            $stmt = $pdo->query("SELECT * FROM $table ORDER BY name");
            $data[$table] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // Получаем сделки
        $sql = "SELECT * FROM deals ORDER BY auth_date DESC, created_at DESC";
        $stmt = $pdo->query($sql);
        $data['deals'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Добавляем информацию о текущем пользователе
        $data['current_user'] = $current_user;
        
        sendJsonResponse($data);
        
    } catch (PDOException $e) {
        sendError('Database error in getAllData: ' . $e->getMessage());
    }
}

// НОВАЯ ФУНКЦИЯ: Аналитика продаж
function getAnalytics() {
    global $pdo;
    
    try {
        $startMonth = $_GET['start_month'] ?? null;
        $endMonth = $_GET['end_month'] ?? null;
        
        if (!$startMonth || !$endMonth) {
            sendError('Не указан период анализа', 400);
        }
        
        // Получаем всех уникальных партнеров
        $partnersStmt = $pdo->query("SELECT DISTINCT partner FROM deals WHERE partner IS NOT NULL AND partner != ''");
        $partners = $partnersStmt->fetchAll(PDO::FETCH_COLUMN);
        
        $analyticsData = [];
        
        foreach ($partners as $partner) {
            $monthlyData = [];
            $totalSales = 0;
            
            // Получаем данные по месяцам
            $sql = "SELECT 
                        DATE_FORMAT(auth_date, '%Y-%m') as month,
                        COALESCE(SUM(contract_amount), 0) as monthly_sales
                    FROM deals 
                    WHERE partner = ? 
                    AND auth_date BETWEEN ? AND LAST_DAY(?)
                    GROUP BY DATE_FORMAT(auth_date, '%Y-%m')
                    ORDER BY month";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$partner, $startMonth . '-01', $endMonth . '-01']);
            $monthlySales = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Создаем массив для всех месяцев периода
            $currentDate = new DateTime($startMonth . '-01');
            $endDate = new DateTime($endMonth . '-01');
            $monthsInPeriod = [];
            
            while ($currentDate <= $endDate) {
                $monthsInPeriod[] = $currentDate->format('Y-m');
                $currentDate->modify('+1 month');
            }
            
            // Заполняем данные по месяцам
            foreach ($monthsInPeriod as $month) {
                $found = false;
                foreach ($monthlySales as $sale) {
                    if ($sale['month'] === $month) {
                        $monthlyData[] = floatval($sale['monthly_sales']);
                        $totalSales += floatval($sale['monthly_sales']);
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $monthlyData[] = 0;
                }
            }
            
            // Рассчитываем динамику
            $firstMonth = $monthlyData[0] ?? 0;
            $lastMonth = $monthlyData[count($monthlyData) - 1] ?? 0;
            $growth = $firstMonth > 0 ? (($lastMonth - $firstMonth) / $firstMonth * 100) : 0;
            
            if ($totalSales > 0) { // Добавляем только партнеров с продажами
                $analyticsData[] = [
                    'partner' => $partner,
                    'monthlyData' => $monthlyData,
                    'total' => $totalSales,
                    'growth' => $growth,
                    'trend' => $growth >= 0 ? 'up' : 'down'
                ];
            }
        }
        
        // Сортируем по убыванию общей суммы продаж
        usort($analyticsData, function($a, $b) {
            return $b['total'] - $a['total'];
        });
        
        sendJsonResponse([
            'success' => true,
            'data' => $analyticsData,
            'period' => [
                'start' => $startMonth,
                'end' => $endMonth,
                'months' => $monthsInPeriod
            ]
        ]);
        
    } catch (PDOException $e) {
        sendError('Database error in getAnalytics: ' . $e->getMessage());
    }
}

function getPendingLogisticsStats() {
    global $pdo, $current_user;
    
    try {
        $stats = [];
        
        // Общее количество ожидающих сделок (для всех)
        $sql_total = "SELECT COUNT(*) as total_count FROM deals WHERE status = 'pending'";
        $stmt_total = $pdo->query($sql_total);
        $result_total = $stmt_total->fetch(PDO::FETCH_ASSOC);
        $stats['total_pending'] = intval($result_total['total_count']);
        
        // Для менеджера - его персональные ожидающие сделки
        if ($current_user['role'] === 'manager') {
            $manager_name = $current_user['full_name'];
            $sql_user = "SELECT COUNT(*) as user_count FROM deals WHERE status = 'pending' AND manager = ?";
            $stmt_user = $pdo->prepare($sql_user);
            $stmt_user->execute([$manager_name]);
            $result_user = $stmt_user->fetch(PDO::FETCH_ASSOC);
            $stats['user_pending'] = intval($result_user['user_count']);
        } else {
            // Для админа и логиста не показываем персональные
            $stats['user_pending'] = 0;
        }
        
        sendJsonResponse(['success' => true, 'stats' => $stats]);
        
    } catch (PDOException $e) {
        sendError('Database error in getPendingLogisticsStats: ' . $e->getMessage());
    }
}

function getSalaries() {
    global $pdo, $current_user;
    
    try {
        $date_from = $_GET['date_from'] ?? null;
        $date_to = $_GET['date_to'] ?? null;
        
        // Если даты не указаны, используем текущую неделю
        if (!$date_from || !$date_to) {
            $monday = date('Y-m-d', strtotime('monday this week'));
            $sunday = date('Y-m-d', strtotime('sunday this week'));
            $date_from = $monday;
            $date_to = $sunday;
        }
        
        $salary_data = [];
        
        if ($current_user['role'] === 'admin') {
            // Для администратора - зарплаты всех менеджеров из справочника managers
            $managers_stmt = $pdo->query("SELECT name FROM managers");
            $managers = $managers_stmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($managers as $manager) {
                $salary_data[] = calculateManagerSalary($manager, $date_from, $date_to);
            }
        } else {
            // Для менеджера - только его зарплата
            // Пробуем найти менеджера по полному имени пользователя
            $manager_name = $current_user['full_name'];
            $salary_data[] = calculateManagerSalary($manager_name, $date_from, $date_to);
            
            // Если не нашли по полному имени, пробуем найти по имени пользователя
            if ($salary_data[0]['deals_count'] === 0) {
                $salary_data[0] = calculateManagerSalary($current_user['username'], $date_from, $date_to);
            }
        }
        
        sendJsonResponse([
            'success' => true,
            'data' => $salary_data,
            'period' => [
                'from' => $date_from,
                'to' => $date_to
            ]
        ]);
        
    } catch (PDOException $e) {
        sendError('Database error in getSalaries: ' . $e->getMessage());
    }
}

function calculateManagerSalary($manager_name, $date_from, $date_to) {
    global $pdo;
    
    // Базовая ставка 1% + бонус 0.5% для ОТП (ЕКОМ)
    // Считаем ВСЕ сделки (не только completed), так как зарплата должна начисляться за все заключенные договоры
    $sql = "
        SELECT 
            COALESCE(SUM(contract_amount), 0) as total_amount,
            COALESCE(SUM(CASE WHEN bank = 'ОТП (ЕКОМ)' THEN contract_amount ELSE 0 END), 0) as otp_amount,
            COUNT(*) as deals_count
        FROM deals 
        WHERE manager = ? 
        AND auth_date BETWEEN ? AND ?
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$manager_name, $date_from, $date_to]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $total_amount = floatval($data['total_amount']);
    $otp_amount = floatval($data['otp_amount']);
    $deals_count = intval($data['deals_count']);
    
    $base_salary = $total_amount * 0.01;  // 1% базовая ставка
    $bonus_salary = $otp_amount * 0.005;  // 0.5% бонус для ОТП (ЕКОМ)
    $total_salary = $base_salary + $bonus_salary;
    
    return [
        'manager' => $manager_name,
        'total_amount' => round($total_amount, 2),
        'otp_amount' => round($otp_amount, 2),
        'deals_count' => $deals_count,
        'base_salary' => round($base_salary, 2),
        'bonus_salary' => round($bonus_salary, 2),
        'total_salary' => round($total_salary, 2)
    ];
}

function saveDeal() {
    global $pdo;
    
    try {
        $sql = "INSERT INTO deals (
            partner, legal_entity, client_name, country, auth_date, 
            bank, interest_rate, installment_period, contract_number, 
            contract_amount, info_amount, no_info_amount, logistics, 
            track_number, manager, currency_amount, client_email, client_phone, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        
        $status = ($_POST['logistics'] === 'СМС подписание') ? 'completed' : 'pending';
        
        $stmt->execute([
            $_POST['partner'], $_POST['legalEntity'], $_POST['clientName'], 
            $_POST['country'], $_POST['authDate'], $_POST['bank'], 
            floatval($_POST['interestRate']), intval($_POST['installmentPeriod']), 
            $_POST['contractNumber'], floatval($_POST['contractAmount']), 
            floatval($_POST['infoAmount']), floatval($_POST['noInfoAmount']), 
            $_POST['logistics'], $_POST['trackNumber'], $_POST['manager'], 
            floatval($_POST['currencyAmount'] || 0), $_POST['clientEmail'], 
            $_POST['clientPhone'], $status
        ]);
        
        sendJsonResponse(['success' => true, 'id' => $pdo->lastInsertId()]);
        
    } catch (PDOException $e) {
        sendError('Database error in saveDeal: ' . $e->getMessage());
    }
}

function updateDealStatus() {
    global $pdo;
    
    try {
        $dealId = intval($_POST['dealId']);
        $status = $_POST['status'];
        
        $stmt = $pdo->prepare("UPDATE deals SET status = ? WHERE id = ?");
        $stmt->execute([$status, $dealId]);
        
        sendJsonResponse(['success' => true]);
        
    } catch (PDOException $e) {
        sendError('Database error in updateDealStatus: ' . $e->getMessage());
    }
}

function addItem($table) {
    global $pdo;
    
    try {
        $name = trim($_POST['name']);
        
        if (empty($name)) {
            sendError('Empty name', 400);
        }
        
        $stmt = $pdo->prepare("INSERT IGNORE INTO $table (name) VALUES (?)");
        $stmt->execute([$name]);
        
        if ($stmt->rowCount() > 0) {
            sendJsonResponse(['success' => true, 'id' => $pdo->lastInsertId()]);
        } else {
            sendError('Item already exists', 400);
        }
        
    } catch (PDOException $e) {
        sendError('Database error in addItem: ' . $e->getMessage());
    }
}

function removeItem() {
    global $pdo;
    
    try {
        $table = $_POST['table'];
        $id = intval($_POST['id']);
        
        // Проверяем, используется ли элемент в сделках
        $field_map = [
            'partners' => 'partner',
            'banks' => 'bank',
            'legal_entities' => 'legal_entity',
            'managers' => 'manager',
            'logistics_types' => 'logistics',
            'countries' => 'country'
        ];
        
        $field_name = $field_map[$table] ?? $table;
        
        // Получаем название элемента который пытаемся удалить
        $stmt = $pdo->prepare("SELECT name FROM $table WHERE id = ?");
        $stmt->execute([$id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$item) {
            sendError('Элемент не найден', 404);
        }
        
        $item_name = $item['name'];
        
        // Проверяем использование в сделках
        $checkStmt = $pdo->prepare("SELECT COUNT(*) as count FROM deals WHERE $field_name = ?");
        $checkStmt->execute([$item_name]);
        $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            sendError('Этот элемент используется в сделках и не может быть удален', 400);
        }
        
        // Удаляем элемент
        $stmt = $pdo->prepare("DELETE FROM $table WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
            sendJsonResponse(['success' => true]);
        } else {
            sendError('Элемент не был удален', 400);
        }
        
    } catch (PDOException $e) {
        sendError('Database error in removeItem: ' . $e->getMessage());
    }
}

// ОБНОВЛЕННАЯ ФУНКЦИЯ: Редактирование элемента с обновлением сделок
function updateItem() {
    global $pdo;
    
    try {
        $table = $_POST['table'];
        $id = intval($_POST['id']);
        $newName = trim($_POST['name']);
        
        if (empty($newName)) {
            sendError('Empty name', 400);
        }
        
        // Проверяем существование элемента
        $checkStmt = $pdo->prepare("SELECT name FROM $table WHERE id = ?");
        $checkStmt->execute([$id]);
        $oldItem = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$oldItem) {
            sendError('Элемент не найден', 404);
        }
        
        $oldName = $oldItem['name'];
        
        // Проверяем, не существует ли уже элемента с таким названием
        $checkDuplicateStmt = $pdo->prepare("SELECT id FROM $table WHERE name = ? AND id != ?");
        $checkDuplicateStmt->execute([$newName, $id]);
        
        if ($checkDuplicateStmt->fetch()) {
            sendError('Элемент с таким названием уже существует', 400);
        }
        
        // ОБНОВЛЯЕМ ЭЛЕМЕНТ
        $stmt = $pdo->prepare("UPDATE $table SET name = ? WHERE id = ?");
        $stmt->execute([$newName, $id]);
        
        if ($stmt->rowCount() > 0) {
            // ЕСЛИ ИЗМЕНИЛИСЬ ПАРТНЕРЫ, БАНКИ ИЛИ ДРУГИЕ СПРАВОЧНИКИ - ОБНОВЛЯЕМ СДЕЛКИ
            $field_map = [
                'partners' => 'partner',
                'banks' => 'bank', 
                'legal_entities' => 'legal_entity',
                'managers' => 'manager',
                'logistics_types' => 'logistics',
                'countries' => 'country'
            ];
            
            $updatedDealsCount = 0;
            
            if (isset($field_map[$table])) {
                $field_name = $field_map[$table];
                
                // ОБНОВЛЯЕМ ВСЕ СДЕЛКИ С СТАРЫМ НАЗВАНИЕМ
                $updateDealsStmt = $pdo->prepare("UPDATE deals SET $field_name = ? WHERE $field_name = ?");
                $updateDealsStmt->execute([$newName, $oldName]);
                
                $updatedDealsCount = $updateDealsStmt->rowCount();
            }
            
            sendJsonResponse([
                'success' => true, 
                'updated_deals' => $updatedDealsCount,
                'message' => $updatedDealsCount > 0 
                    ? "Элемент обновлен! Обновлено сделок: $updatedDealsCount" 
                    : "Элемент успешно обновлен!"
            ]);
        } else {
            sendError('Элемент не был обновлен', 400);
        }
        
    } catch (PDOException $e) {
        sendError('Database error in updateItem: ' . $e->getMessage());
    }
}

function deleteDeal() {
    global $pdo;
    
    try {
        $dealId = intval($_POST['dealId']);
        
        $stmt = $pdo->prepare("DELETE FROM deals WHERE id = ?");
        $stmt->execute([$dealId]);
        
        if ($stmt->rowCount() > 0) {
            sendJsonResponse(['success' => true]);
        } else {
            sendError('Сделка не найдена', 404);
        }
        
    } catch (PDOException $e) {
        sendError('Database error in deleteDeal: ' . $e->getMessage());
    }
}

function getDeal() {
    global $pdo;
    
    try {
        $dealId = intval($_GET['dealId']);
        
        $stmt = $pdo->prepare("SELECT * FROM deals WHERE id = ?");
        $stmt->execute([$dealId]);
        $deal = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($deal) {
            sendJsonResponse(['success' => true, 'deal' => $deal]);
        } else {
            sendError('Сделка не найдена', 404);
        }
        
    } catch (PDOException $e) {
        sendError('Database error in getDeal: ' . $e->getMessage());
    }
}

function updateDeal() {
    global $pdo;
    
    try {
        $dealId = intval($_POST['dealId']);
        
        // Сначала проверим существование сделки
        $checkStmt = $pdo->prepare("SELECT id FROM deals WHERE id = ?");
        $checkStmt->execute([$dealId]);
        
        if (!$checkStmt->fetch()) {
            sendError('Сделка не найдена', 404);
        }
        
        $sql = "UPDATE deals SET 
            partner = ?, legal_entity = ?, client_name = ?, country = ?, auth_date = ?, 
            bank = ?, interest_rate = ?, installment_period = ?, contract_number = ?, 
            contract_amount = ?, info_amount = ?, no_info_amount = ?, logistics = ?, 
            track_number = ?, manager = ?, currency_amount = ?, client_email = ?, client_phone = ?, status = ?
            WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        
        $status = ($_POST['logistics'] === 'СМС подписание') ? 'completed' : 'pending';
        
        $stmt->execute([
            $_POST['partner'], $_POST['legalEntity'], $_POST['clientName'], 
            $_POST['country'], $_POST['authDate'], $_POST['bank'], 
            floatval($_POST['interestRate']), intval($_POST['installmentPeriod']), 
            $_POST['contractNumber'], floatval($_POST['contractAmount']), 
            floatval($_POST['infoAmount']), floatval($_POST['noInfoAmount']), 
            $_POST['logistics'], $_POST['trackNumber'], $_POST['manager'], 
            floatval($_POST['currencyAmount'] || 0), $_POST['clientEmail'], 
            $_POST['clientPhone'], $status, $dealId
        ]);
        
        sendJsonResponse(['success' => true]);
        
    } catch (PDOException $e) {
        sendError('Database error in updateDeal: ' . $e->getMessage());
    }
}

// Функции для управления пользователями
function getUsers() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT id, username, role, full_name, is_active FROM users ORDER BY full_name");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        sendJsonResponse(['users' => $users]);
    } catch (PDOException $e) {
        sendError('Database error: ' . $e->getMessage());
    }
}

function getUser() {
    global $pdo;
    
    try {
        $userId = intval($_GET['user_id']);
        $stmt = $pdo->prepare("SELECT id, username, role, full_name, is_active FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            sendJsonResponse(['user' => $user]);
        } else {
            sendError('User not found', 404);
        }
    } catch (PDOException $e) {
        sendError('Database error: ' . $e->getMessage());
    }
}

function saveUser() {
    global $pdo;
    
    try {
        $userId = $_POST['user_id'] ? intval($_POST['user_id']) : null;
        $fullName = trim($_POST['full_name']);
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $role = $_POST['role'];
        $isActive = $_POST['is_active'] === '1';
        
        // Валидация
        if (empty($fullName) || empty($username) || empty($role)) {
            sendError('Все поля обязательны для заполнения');
        }
        
        if (!$userId && empty($password)) {
            sendError('Пароль обязателен для нового пользователя');
        }
        
        if ($userId) {
            // Обновление пользователя
            if (!empty($password)) {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET full_name = ?, username = ?, password_hash = ?, role = ?, is_active = ? WHERE id = ?";
                $params = [$fullName, $username, $passwordHash, $role, $isActive, $userId];
            } else {
                $sql = "UPDATE users SET full_name = ?, username = ?, role = ?, is_active = ? WHERE id = ?";
                $params = [$fullName, $username, $role, $isActive, $userId];
            }
        } else {
            // Создание пользователя
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (full_name, username, password_hash, role, is_active) VALUES (?, ?, ?, ?, ?)";
            $params = [$fullName, $username, $passwordHash, $role, $isActive];
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        sendJsonResponse(['success' => true]);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { // duplicate entry
            sendError('Пользователь с таким логином уже существует');
        } else {
            sendError('Database error: ' . $e->getMessage());
        }
    }
}

function deleteUser() {
    global $pdo;
    
    try {
        $userId = intval($_POST['user_id']);
        
        // Нельзя удалить самого себя
        if ($userId == $_SESSION['user_id']) {
            sendError('Нельзя удалить собственный аккаунт');
        }
        
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        
        if ($stmt->rowCount() > 0) {
            sendJsonResponse(['success' => true]);
        } else {
            sendError('Пользователь не найден');
        }
    } catch (PDOException $e) {
        sendError('Database error: ' . $e->getMessage());
    }
}
?>