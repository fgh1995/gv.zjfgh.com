<?php
header('Content-Type: application/json');
require_once 'db.php';

$db = (new Database())->getConnection();

$id = $_POST['id'] ?? null;
$name = trim($_POST['name'] ?? '');

if (empty($name)) {
    echo json_encode(['error' => '标签名称不能为空']);
    exit;
}

// 创建slug
function createSlug($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    return $text ?: 'untagged';
}

$slug = createSlug($name);

try {
    if ($id) {
        // 更新标签
        $stmt = $db->prepare("UPDATE tags SET name = ?, slug = ? WHERE id = ?");
        $stmt->execute([$name, $slug, $id]);
    } else {
        // 创建新标签
        $stmt = $db->prepare("INSERT INTO tags (name, slug) VALUES (?, ?)");
        $stmt->execute([$name, $slug]);
    }
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    if ($e->errorInfo[1] == 1062) { // 重复键错误
        echo json_encode(['error' => '标签名称已存在']);
    } else {
        echo json_encode(['error' => '保存失败: ' . $e->getMessage()]);
    }
}
?>