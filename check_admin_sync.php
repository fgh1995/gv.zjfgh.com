<?php
header('Content-Type: application/json');
require_once 'db.php';

$user_id = $_GET['user_id'] ?? '';
$db = (new Database())->getConnection();

$stmt = $db->prepare("
    SELECT 1 FROM admin_sync 
    WHERE target_user_id = ? AND last_update > DATE_SUB(NOW(), INTERVAL 1 MINUTE)
");
$stmt->execute([$user_id]);

echo json_encode([
    'is_syncing' => $stmt->fetch() !== false
]);
?>