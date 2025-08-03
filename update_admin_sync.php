<?php
header('Content-Type: application/json');
require_once 'db.php';

$data = json_decode(file_get_contents('php://input'), true);
$db = (new Database())->getConnection();

$stmt = $db->prepare("UPDATE admin_sync SET last_update = NOW() WHERE admin_id = ? AND target_user_id = ?");
$stmt->execute([$data['admin_id'], $data['target_user_id']]);

echo json_encode(['status' => 'success']);
?>