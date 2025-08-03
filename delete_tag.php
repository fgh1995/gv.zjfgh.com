<?php
header('Content-Type: application/json');
require_once 'db.php';

$db = (new Database())->getConnection();

$id = $_POST['id'] ?? null;

if (!$id) {
    echo json_encode(['error' => '缺少标签ID']);
    exit;
}

try {
    $db->beginTransaction();
    
    // 先删除关联
    $stmt = $db->prepare("DELETE FROM video_tags WHERE tag_id = ?");
    $stmt->execute([$id]);
    
    // 再删除标签
    $stmt = $db->prepare("DELETE FROM tags WHERE id = ?");
    $stmt->execute([$id]);
    
    $db->commit();
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    $db->rollBack();
    echo json_encode(['error' => '删除失败: ' . $e->getMessage()]);
}
?>