<?php
header('Content-Type: text/html; charset=utf-8');
require_once 'db.php';

$db = (new Database())->getConnection();
$video_id = $_GET['id'] ?? 0;

// 获取视频信息
$stmt = $db->prepare("SELECT id, title FROM videos WHERE id = ?");
$stmt->execute([$video_id]);
$video = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$video) {
    die('视频不存在');
}

// 获取所有标签
$stmt = $db->prepare("SELECT id, name FROM tags ORDER BY name");
$stmt->execute();
$all_tags = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 获取视频的当前标签
$stmt = $db->prepare("
    SELECT t.id 
    FROM video_tags vt
    JOIN tags t ON vt.tag_id = t.id
    WHERE vt.video_id = ?
");
$stmt->execute([$video_id]);
$current_tags = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id');
?>
<!DOCTYPE html>
<html>
<head>
    <title>编辑视频标签 - <?= htmlspecialchars($video['title']) ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .tag-item { margin: 5px 0; }
        .tag-label { margin-left: 5px; }
        button { margin-top: 10px; padding: 5px 10px; }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        h1 {
            color: #4285f4;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .checkbox-list {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 4px;
        }
        .actions {
            margin-top: 20px;
        }
        .btn {
            padding: 8px 16px;
            background-color: #4285f4;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn:hover {
            background-color: #3367d6;
        }
        .btn-back {
            background-color: #f1f3f4;
            color: #202124;
            margin-left: 10px;
        }
        .btn-back:hover {
            background-color: #e0e0e0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>编辑视频标签: <?= htmlspecialchars($video['title']) ?></h1>
        
        <form method="post" action="update_video_tags.php">
            <input type="hidden" name="video_id" value="<?= $video['id'] ?>">
            
            <div class="form-group">
                <h3>选择标签:</h3>
                <div class="checkbox-list">
                    <?php foreach ($all_tags as $tag): ?>
                        <div class="tag-item">
                            <input type="checkbox" name="tags[]" id="tag-<?= $tag['id'] ?>" 
                                   value="<?= $tag['id'] ?>" <?= in_array($tag['id'], $current_tags) ? 'checked' : '' ?>>
                            <label class="tag-label" for="tag-<?= $tag['id'] ?>"><?= htmlspecialchars($tag['name']) ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="actions">
                <button type="submit" class="btn">保存</button>
                <a href="tags.php" class="btn btn-back">返回</a>
            </div>
        </form>
    </div>
</body>
</html>