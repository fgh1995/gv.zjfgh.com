<?php
header('Content-Type: application/json');
require_once 'db.php';

$user_id = $_GET['user_id'] ?? '';
$db = (new Database())->getConnection();

$stmt = $db->prepare("
    SELECT u.user_id, u.video_id, u.play_position, v.title, v.path 
    FROM user_watching u
    JOIN videos v ON u.video_id = v.id
    WHERE u.user_id = ? AND u.last_update > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
");
$stmt->execute([$user_id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if ($data) {
    echo json_encode([
        'video_id' => $data['video_id'],
        'video_title' => $data['title'],
        'video_path' => $data['path'],
        'position' => (float)$data['play_position']
    ]);
} else {
    echo json_encode(['error' => '用户不在线或未观看视频']);
}
?>