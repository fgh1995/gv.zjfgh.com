<?php
header('Content-Type: application/json');
session_start();
require_once 'db.php';

$data = json_decode(file_get_contents('php://input'), true);
$db = (new Database())->getConnection();

$stmt = $db->prepare("REPLACE INTO user_watching (user_id, video_id, last_update) VALUES (?, ?, NOW())");
$stmt->execute([$data['user_id'], $data['video_id']]);

echo json_encode(['status' => 'success']);
?>