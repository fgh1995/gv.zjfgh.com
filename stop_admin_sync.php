<?php
header('Content-Type: application/json');
require_once 'db.php';

$admin_id = $_GET['admin_id'] ?? '';
$db = (new Database())->getConnection();

$stmt = $db->prepare("DELETE FROM admin_sync WHERE admin_id = ?");
$stmt->execute([$admin_id]);

echo json_encode(['status' => 'success']);
?>