<?php
header('Content-Type: application/json');
require_once 'db.php';

$db = (new Database())->getConnection();

$stmt = $db->prepare("SELECT id, name FROM tags ORDER BY name");
$stmt->execute();
$tags = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($tags);
?>