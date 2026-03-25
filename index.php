<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$current_user = [
    'id' => $_SESSION['user_id'],
    'username' => $_SESSION['username'],
    'role' => $_SESSION['role'],
    'full_name' => $_SESSION['full_name']
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Учет продаж Ульяновск - Система учета продаж</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="css/styles.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Боковая панель -->
            <?php include 'sections/sidebar.php'; ?>

            <!-- Основной контент -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <!-- Дашборд -->
                <div id="dashboard" class="content-section">
                    <?php include 'sections/dashboard.php'; ?>
                </div>

                <!-- Все сделки -->
                <div id="deals" class="content-section" style="display: none;">
                    <?php include 'sections/deals.php'; ?>
                </div>

                <!-- Добавление/редактирование сделки -->
                <div id="add-deal" class="content-section" style="display: none;">
                    <?php include 'sections/add-deal.php'; ?>
                </div>

                <!-- Аналитика продаж (ТОЛЬКО ДЛЯ АДМИНОВ) -->
                <?php if ($current_user['role'] === 'admin'): ?>
                <div id="analytics" class="content-section" style="display: none;">
                    <?php include 'sections/analytics.php'; ?>
                </div>
                <?php endif; ?>

                <!-- Статистика по партнерам (ТОЛЬКО ДЛЯ АДМИНОВ) -->
                <?php if ($current_user['role'] === 'admin'): ?>
                <div id="partner-stats" class="content-section" style="display: none;">
                    <?php include 'sections/partner-stats.php'; ?>
                </div>
                <?php endif; ?>

                <!-- Логистика -->
                <div id="logistics" class="content-section" style="display: none;">
                    <?php include 'sections/logistics.php'; ?>
                </div>

                <!-- Управление партнерами (ТОЛЬКО ДЛЯ АДМИНОВ) -->
                <?php if ($current_user['role'] === 'admin'): ?>
                <div id="partners" class="content-section" style="display: none;">
                    <?php include 'sections/partners.php'; ?>
                </div>
                <?php endif; ?>

                <!-- Управление пользователями (только для админов) -->
                <?php if ($current_user['role'] === 'admin'): ?>
                <div id="user-management" class="content-section" style="display: none;">
                    <?php include 'sections/user-management.php'; ?>
                </div>
                <?php endif; ?>

                <!-- Настройки (ТОЛЬКО ДЛЯ АДМИНОВ) -->
                <?php if ($current_user['role'] === 'admin'): ?>
                <div id="settings" class="content-section" style="display: none;">
                    <?php include 'sections/settings.php'; ?>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        const currentUserRole = '<?php echo $current_user['role']; ?>';
    </script>
    <script src="js/main.js"></script>
    <script src="js/filters.js"></script>
    <script src="js/deals.js"></script>
    <script src="js/tables.js"></script>
    <script src="js/charts.js"></script>
    <script src="js/settings.js"></script>
    <script src="js/salary.js"></script>
    <script src="js/partner-stats.js"></script>
    <!-- ДОБАВЛЕН НОВЫЙ ФАЙЛ ДЛЯ ПОИСКА И ПАГИНАЦИИ -->
    <script src="js/search-pagination.js"></script>
</body>
</html>