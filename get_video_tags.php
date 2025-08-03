<?php
header('Content-Type: application/json');
require_once 'db.php';

$video_id = $_GET['video_id'] ?? 0;
$db = (new Database())->getConnection();

$stmt = $db->prepare("
    SELECT t.id 
    FROM video_tags vt
    JOIN tags t ON vt.tag_id = t.id
    WHERE vt.video_id = ?
");
$stmt->execute([$video_id]);
$tags = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id');

echo json_encode($tags);
?>