<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
require_once 'db.php';

// 检查管理员权限
// if (!isset($_SESSION['admin'])) {
//     die('请先登录管理员账号');
// }

$db = (new Database())->getConnection();

// 获取所有标签及其使用次数
$stmt = $db->prepare("
    SELECT t.id, t.name, t.slug, COUNT(vt.video_id) as video_count
    FROM tags t
    LEFT JOIN video_tags vt ON t.id = vt.tag_id
    GROUP BY t.id
    ORDER BY video_count DESC, t.name
");
$stmt->execute();
$tags = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 获取所有视频
$stmt = $db->prepare("SELECT id, title FROM videos ORDER BY title");
$stmt->execute();
$videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>标签管理</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .tag { 
            display: inline-block; 
            background: #e0e0e0; 
            padding: 3px 8px; 
            border-radius: 10px; 
            margin: 3px;
            cursor: pointer;
        }
        .tag-count {
            font-size: 0.8em;
            color: #666;
            margin-left: 5px;
        }
        .tag-cloud {
            margin: 20px 0;
        }
        .tag-form {
            margin: 20px 0;
            padding: 15px;
            background: #f5f5f5;
            border-radius: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"] {
            padding: 8px;
            width: 300px;
            max-width: 100%;
        }
        button {
            padding: 8px 16px;
            background-color: #4285f4;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #3367d6;
        }
        .delete-btn {
            background-color: #f44336;
            margin-left: 10px;
        }
        .delete-btn:hover {
            background-color: #d32f2f;
        }
    </style>
</head>
<body>
    <h1>标签管理</h1>
    
    <div class="tag-form">
        <h2>添加/编辑标签</h2>
        <form method="post" action="save_tag.php" id="tag-form">
            <input type="hidden" name="id" id="tag-id">
            <div class="form-group">
                <label for="tag-name">标签名称:</label>
                <input type="text" id="tag-name" name="name" required>
            </div>
            <button type="submit">保存</button>
            <button type="button" class="delete-btn" id="delete-btn" style="display: none;">删除</button>
        </form>
    </div>
    
    <div class="tag-cloud">
        <h2>标签云</h2>
        <?php foreach ($tags as $tag): ?>
            <span class="tag" onclick="editTag(<?= $tag['id'] ?>, '<?= htmlspecialchars($tag['name']) ?>')">
                <?= htmlspecialchars($tag['name']) ?>
                <span class="tag-count">(<?= $tag['video_count'] ?>)</span>
            </span>
        <?php endforeach; ?>
    </div>
    
    <h2>视频标签关联</h2>
    <table>
        <tr>
            <th>视频标题</th>
            <th>标签</th>
            <th>操作</th>
        </tr>
        <?php foreach ($videos as $video): ?>
            <?php 
            $stmt = $db->prepare("
                SELECT t.id, t.name 
                FROM video_tags vt
                JOIN tags t ON vt.tag_id = t.id
                WHERE vt.video_id = ?
                ORDER BY t.name
            ");
            $stmt->execute([$video['id']]);
            $video_tags = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <tr>
                <td><?= htmlspecialchars($video['title']) ?></td>
                <td>
                    <?php foreach ($video_tags as $tag): ?>
                        <span class="tag"><?= htmlspecialchars($tag['name']) ?></span>
                    <?php endforeach; ?>
                </td>
                <td>
                    <a href="edit_video_tags.php?id=<?= $video['id'] ?>">编辑标签</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
    
    <script>
        function editTag(id, name) {
            document.getElementById('tag-id').value = id;
            document.getElementById('tag-name').value = name;
            document.getElementById('delete-btn').style.display = 'inline-block';
            document.getElementById('tag-name').focus();
        }
        
        document.getElementById('delete-btn').addEventListener('click', function() {
            if (confirm('确定要删除这个标签吗？')) {
                const tagId = document.getElementById('tag-id').value;
                fetch('delete_tag.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'id=' + tagId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('标签已删除');
                        location.reload();
                    } else {
                        alert('删除失败: ' + (data.error || '未知错误'));
                    }
                })
                .catch(error => {
                    alert('删除失败: ' + error);
                });
            }
        });
        
        // 重置表单
        document.getElementById('tag-form').addEventListener('reset', function() {
            document.getElementById('delete-btn').style.display = 'none';
        });
    </script>
</body>
</html>