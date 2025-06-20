<?php
// in file: htdocs/notifications.php
require_once __DIR__ . '/app/core/auth.php';
require_once __DIR__ . '/app/core/database.php';

require_login();

$user_id = get_current_user_id();

$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll();

$page_title = 'All Notifications';
include __DIR__ . '/app/templates/header.php';
?>
<style>
.notification-list-page { list-style: none; padding: 0; }
.notification-list-page li {
    background: #fff;
    border: 1px solid #ddd;
    padding: 15px;
    margin-bottom: 10px;
    border-radius: 5px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: background-color 0.2s;
}
.notification-list-page li:hover {
    background-color: #f5f5f5;
}
.notification-list-page li.unread { background-color: #eaf2ff; border-left: 4px solid #007bff; }
.notification-list-page a { text-decoration: none; color: inherit; flex-grow: 1; }
.notification-list-page .time { color: #6c757d; font-size: 0.9em; flex-shrink: 0; margin-left: 20px;}
</style>

<h1>All Notifications</h1>

<ul class="notification-list-page">
    <?php if (empty($notifications)): ?>
        <li>You have no notifications.</li>
    <?php else: ?>
        <?php foreach ($notifications as $notif): ?>
            <li class="<?= $notif['is_read'] ? '' : 'unread' ?>">
                <a href="<?= $notif['request_id'] ? '/requests/view.php?id='.$notif['request_id'] : '#' ?>">
                    <?= htmlspecialchars($notif['message']) ?>
                </a>
                <span class="time"><?= date('M d, Y, g:i a', strtotime($notif['created_at'])) ?></span>
            </li>
        <?php endforeach; ?>
    <?php endif; ?>
</ul>

<?php include __DIR__ . '/app/templates/footer.php'; ?>