<?php
header('Content-Type: application/json');
require_once 'db.php';

$db = (new Database())->getConnection();

// 确保没有输出任何内容在header之前
$video_id = $_POST['video_id'] ?? 0;
$selected_tags = $_POST['tags'] ?? [];

// 验证视频存在
$stmt = $db->prepare("SELECT id FROM videos WHERE id = ?");
$stmt->execute([$video_id]);
if (!$stmt->fetch()) {
    echo json_encode(['error' => '视频不存在']);
    exit;
}

try {
    $db->beginTransaction();
    
    // 删除旧的标签关联
    $stmt = $db->prepare("DELETE FROM video_tags WHERE video_id = ?");
    $stmt->execute([$video_id]);
    
    // 添加新的标签关联
    $stmt = $db->prepare("INSERT INTO video_tags (video_id, tag_id) VALUES (?, ?)");
    foreach ($selected_tags as $tag_id) {
        $stmt->execute([$video_id, $tag_id]);
    }
    
    $db->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $db->rollBack();
    // 确保错误信息也是JSON格式
    echo json_encode(['error' => '保存失败: ' . $e->getMessage()]);
}