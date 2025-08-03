<?php
header('Content-Type: application/json');
require_once 'db.php';

$db = (new Database())->getConnection();

$video_id = $_POST['video_id'] ?? 0;
$selected_tags = $_POST['tags'] ?? [];

// 验证视频存在
$stmt = $db->prepare("SELECT id FROM videos WHERE id = ?");
$stmt->execute([$video_id]);
if (!$stmt->fetch()) {
    echo json_encode(['error' => '视频不存在']);
    exit;
}

// 开始事务
$db->beginTransaction();

try {
    // 删除旧的标签关联
    $stmt = $db->prepare("DELETE FROM video_tags WHERE video_id = ?");
    $stmt->execute([$video_id]);
    
    // 添加新的标签关联
    $stmt = $db->prepare("INSERT INTO video_tags (video_id, tag_id) VALUES (?, ?)");
    foreach ($selected_tags as $tag_id) {
        $stmt->execute([$video_id, $tag_id]);
    }
    
    $db->commit();
    echo json_encode(['success' => true, 'redirect' => 'tags.php']);
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['error' => '保存失败: ' . $e->getMessage()]);
}
?>