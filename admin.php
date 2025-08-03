<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
require_once './db.php';

$db = (new Database())->getConnection();

// 获取当前活动标签
$active_tab = $_GET['tab'] ?? 'sync';
$allowed_tabs = ['sync', 'tags', 'scan'];
if (!in_array($active_tab, $allowed_tabs)) {
    $active_tab = 'sync';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <title>视频管理系统 - 控制面板</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --success-color: #4cc9f0;
            --danger-color: #f72585;
            --warning-color: #ffc107;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --gray-color: #6c757d;
            --border-radius: 0.375rem;
            --box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            --transition: all 0.3s ease;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: "Roboto", "PingFang SC", "Microsoft YaHei", sans-serif;
            line-height: 1.6;
            color: var(--dark-color);
            background-color: #f5f7fa;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }
        
        .tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            border: 1px solid transparent;
            border-bottom: none;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            margin-right: 5px;
            transition: var(--transition);
        }
        
        .tab:hover {
            background-color: rgba(67, 97, 238, 0.1);
        }
        
        .tab.active {
            background-color: white;
            border-color: #dee2e6;
            border-bottom-color: white;
            color: var(--primary-color);
            font-weight: 500;
        }
        
        .tab-content {
            display: none;
            background: white;
            border-radius: 0 var(--border-radius) var(--border-radius) var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        
        .card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 25px;
            margin-bottom: 30px;
            transition: var(--transition);
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 1rem 1.5rem rgba(0, 0, 0, 0.1);
        }
        
        .card-title {
            font-size: 1.5rem;
            margin-bottom: 20px;
            color: var(--primary-color);
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 10px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: var(--border-radius);
            background-color: var(--primary-color);
            color: white;
            cursor: pointer;
            font-size: 1rem;
            transition: var(--transition);
            text-decoration: none;
            display: inline-block;
        }
        
        .btn:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background-color: var(--danger-color);
        }
        
        .btn-success {
            background-color: var(--success-color);
        }
        
        .btn-warning {
            background-color: var(--warning-color);
        }
        
        .table-responsive {
            overflow-x: auto;
            margin-bottom: 20px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            border: 1px solid #dee2e6;
            padding: 12px;
            text-align: left;
        }
        
        th {
            background-color: #f8f9fa;
            font-weight: 500;
        }
        
        tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        .syncing-row {
            background-color: rgba(67, 97, 238, 0.1) !important;
        }
        
        .player-container {
            margin: 20px 0;
        }
        
        .sync-info {
            background-color: rgba(67, 97, 238, 0.1);
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
        }
        
        .tag {
            display: inline-block;
            background-color: var(--primary-color);
            color: white;
            padding: 3px 10px;
            border-radius: 20px;
            margin: 3px;
            font-size: 0.9rem;
        }
        
        .tag-count {
            font-size: 0.8em;
            background-color: rgba(255, 255, 255, 0.3);
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: 5px;
        }
        
        .tag-cloud {
            margin: 20px 0;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #dee2e6;
            border-radius: var(--border-radius);
            font-size: 1rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }
        
        /* 模态框样式 */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
            display: none;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            padding: 25px;
            border-radius: var(--border-radius);
            max-width: 800px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--box-shadow);
        }
        
        .modal-title {
            font-size: 1.5rem;
            margin-bottom: 20px;
            color: var(--primary-color);
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 10px;
        }
        
        .checkbox-list {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .checkbox-item {
            margin: 8px 0;
            display: flex;
            align-items: center;
        }
        
        .checkbox-label {
            margin-left: 10px;
        }
        
        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .card {
                padding: 15px;
            }
            
            .tabs {
                flex-direction: column;
            }
            
            .tab {
                border-radius: 0;
                border: none;
                border-bottom: 1px solid #dee2e6;
            }
            
            .tab.active {
                border-radius: 0;
                border: none;
                border-bottom: 2px solid var(--primary-color);
            }
            
            .modal-content {
                width: 95%;
                padding: 15px;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>视频管理系统控制面板</h1>
        <p>集中管理视频同步、标签和扫描功能</p>
    </div>
    
    <div class="tabs">
        <div class="tab <?= $active_tab === 'sync' ? 'active' : '' ?>" onclick="switchTab('sync')">同步观看</div>
        <div class="tab <?= $active_tab === 'tags' ? 'active' : '' ?>" onclick="switchTab('tags')">标签管理</div>
        <div class="tab <?= $active_tab === 'scan' ? 'active' : '' ?>" onclick="switchTab('scan')">视频扫描</div>
    </div>
    
    <!-- 同步观看标签页 -->
    <div id="sync-tab" class="tab-content <?= $active_tab === 'sync' ? 'active' : '' ?>">
        <?php
        // 开始同步观看某个用户
        if (isset($_GET['sync_with'])) {
            $target_user = $_GET['sync_with'];
            $_SESSION['syncing_user'] = $target_user;
            
            // 记录同步状态到数据库
            $stmt = $db->prepare("REPLACE INTO admin_sync (admin_id, target_user_id, last_update) VALUES (?, ?, NOW())");
            $stmt->execute([session_id(), $target_user]);
        }
        
        // 停止同步
        if (isset($_GET['stop_sync'])) {
            unset($_SESSION['syncing_user']);
            
            // 从数据库删除同步状态
            $stmt = $db->prepare("DELETE FROM admin_sync WHERE admin_id = ?");
            $stmt->execute([session_id()]);
        }
        
        // 获取所有在线用户
        $stmt = $db->prepare("
            SELECT u.user_id, v.id as video_id, v.title, v.path, u.play_position, u.last_update 
            FROM user_watching u
            JOIN videos v ON u.video_id = v.id
            WHERE u.last_update > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            ORDER BY u.last_update DESC
        ");
        $stmt->execute();
        $onlineUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        
        <?php if (isset($_SESSION['syncing_user'])): ?>
        <div class="sync-info">
            <div>正在同步观看用户: <strong><?= htmlspecialchars($_SESSION['syncing_user']) ?></strong></div>
            <a href="?tab=sync&stop_sync=1" class="btn btn-danger">停止同步</a>
        </div>
        
        <div class="player-container">
            <video id="admin-player" controls playsinline style="width: 100%; max-height: 400px;"></video>
            <div>同步状态: <span id="sync-status">正在连接...</span></div>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <h2 class="card-title">在线用户 (最近5分钟活跃)</h2>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>用户ID</th>
                            <th>正在观看</th>
                            <th>播放位置</th>
                            <th>最后活跃</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($onlineUsers as $user): ?>
                        <tr class="<?= isset($_SESSION['syncing_user']) && $_SESSION['syncing_user'] == $user['user_id'] ? 'syncing-row' : '' ?>">
                            <td><?= htmlspecialchars($user['user_id']) ?></td>
                            <td><?= htmlspecialchars($user['title']) ?></td>
                            <td><?= gmdate("H:i:s", $user['play_position']) ?></td>
                            <td><?= $user['last_update'] ?></td>
                            <td>
                                <?php if (!isset($_SESSION['syncing_user']) || $_SESSION['syncing_user'] != $user['user_id']): ?>
                                <a href="?tab=sync&sync_with=<?= urlencode($user['user_id']) ?>" class="btn btn-success">同步</a>
                                <?php else: ?>
                                <span style="color: var(--primary-color);">同步中</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <?php if (isset($_SESSION['syncing_user'])): ?>
        <script>
            const adminPlayer = document.getElementById('admin-player');
            const syncStatus = document.getElementById('sync-status');
            let lastVideoId = '';
            let lastPosition = 0;
            let isAdminControlling = false;
            let syncInterval;
            
            function formatTime(seconds) {
                return new Date(seconds * 1000).toISOString().substr(11, 8);
            }
            
            function updateSyncStatus() {
                fetch('../get_user_status.php?user_id=<?= urlencode($_SESSION['syncing_user']) ?>')
                    .then(response => response.json())
                    .then(data => {
                        if (data.error) {
                            syncStatus.textContent = '错误: ' + data.error;
                            clearInterval(syncInterval);
                            return;
                        }
                        
                        // 更新数据库中的同步状态时间戳
                        fetch('../update_admin_sync.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                admin_id: '<?= session_id() ?>',
                                target_user_id: '<?= $_SESSION['syncing_user'] ?>'
                            })
                        });
                        
                        if (data.video_id && data.position !== undefined) {
                            // 如果视频变了
                            if (data.video_id !== lastVideoId) {
                                lastVideoId = data.video_id;
                                adminPlayer.src = data.video_path;
                                adminPlayer.load();
                                adminPlayer.currentTime = data.position;
                                adminPlayer.play().catch(e => {
                                    console.log('播放错误:', e);
                                    syncStatus.textContent = '播放错误: ' + e.message;
                                });
                                syncStatus.textContent = `同步中: ${data.video_title} (${formatTime(data.position)})`;
                            } 
                            // 如果只是位置更新
                            else if (Math.abs(data.position - lastPosition) > 2) {
                                // 只有当差异较大时才更新，避免频繁跳动
                                if (!isAdminControlling) {
                                    adminPlayer.currentTime = data.position;
                                }
                                syncStatus.textContent = `同步中: ${data.video_title} (${formatTime(data.position)})`;
                            }
                            
                            lastPosition = data.position;
                        }
                    })
                    .catch(error => {
                        console.error('同步错误:', error);
                        syncStatus.textContent = '同步错误: ' + error.message;
                    });
            }
            
            // 监听管理员操作
            adminPlayer.addEventListener('play', () => {
                isAdminControlling = true;
            });
            
            adminPlayer.addEventListener('pause', () => {
                isAdminControlling = true;
            });
            
            adminPlayer.addEventListener('seeking', () => {
                isAdminControlling = true;
            });
            
            // 当管理员停止操作后恢复自动同步
            adminPlayer.addEventListener('seeked', () => {
                setTimeout(() => {
                    isAdminControlling = false;
                }, 3000);
            });
            
            // 启动状态检查
            syncInterval = setInterval(updateSyncStatus, 2000);
            updateSyncStatus();
            
            // 页面卸载时停止同步
            window.addEventListener('beforeunload', function() {
                fetch('../stop_admin_sync.php?admin_id=<?= session_id() ?>');
            });
        </script>
        <?php endif; ?>
    </div>
    
    <!-- 标签管理标签页 -->
    <div id="tags-tab" class="tab-content <?= $active_tab === 'tags' ? 'active' : '' ?>">
        <?php
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
        
        <div class="card">
            <h2 class="card-title">标签管理</h2>
            
            <div class="form-group">
                <label class="form-label">添加/编辑标签</label>
                <div style="display: flex; gap: 10px;">
                    <input type="text" id="tag-name" class="form-control" placeholder="输入标签名称">
                    <input type="hidden" id="tag-id">
                    <button id="save-tag" class="btn">保存</button>
                    <button id="delete-tag" class="btn btn-danger" style="display: none;">删除</button>
                </div>
            </div>
            
            <div class="tag-cloud">
                <h3>标签云</h3>
                <?php foreach ($tags as $tag): ?>
                    <span class="tag" onclick="editTag(<?= $tag['id'] ?>, '<?= htmlspecialchars($tag['name']) ?>')">
                        <?= htmlspecialchars($tag['name']) ?>
                        <span class="tag-count"><?= $tag['video_count'] ?></span>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="card">
            <h2 class="card-title">视频标签关联</h2>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>视频标题</th>
                            <th>标签</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
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
                                    <button class="btn" onclick="openEditTagsModal(<?= $video['id'] ?>, '<?= htmlspecialchars(addslashes($video['title'])) ?>')">编辑标签</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <script>
            function editTag(id, name) {
                document.getElementById('tag-id').value = id;
                document.getElementById('tag-name').value = name;
                document.getElementById('delete-tag').style.display = 'inline-block';
                document.getElementById('tag-name').focus();
            }
            
            document.getElementById('save-tag').addEventListener('click', function() {
                const tagId = document.getElementById('tag-id').value;
                const tagName = document.getElementById('tag-name').value.trim();
                
                if (!tagName) {
                    alert('请输入标签名称');
                    return;
                }
                
                const formData = new FormData();
                if (tagId) formData.append('id', tagId);
                formData.append('name', tagName);
                
                fetch('../save_tag.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.error || '保存失败');
                    }
                })
                .catch(error => {
                    alert('保存失败: ' + error);
                });
            });
            
            document.getElementById('delete-tag').addEventListener('click', function() {
                if (confirm('确定要删除这个标签吗？')) {
                    const tagId = document.getElementById('tag-id').value;
                    
                    fetch('../delete_tag.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'id=' + tagId
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
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
        </script>
    </div>
    
    <!-- 视频扫描标签页 -->
    <div id="scan-tab" class="tab-content <?= $active_tab === 'scan' ? 'active' : '' ?>">
        <div class="card">
            <h2 class="card-title">视频扫描</h2>
            
            <div class="form-group">
                <label for="scan-path" class="form-label">扫描路径</label>
                <input type="text" id="scan-path" class="form-control" value="videos">
            </div>
            
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" id="incremental-scan" checked>
                    <span>增量扫描（保留已有数据）</span>
                </label>
            </div>
            
            <div style="display: flex; gap: 10px; margin-bottom: 20px;">
                <button id="start-scan" class="btn btn-success">开始扫描</button>
                <button id="stop-scan" class="btn btn-danger" disabled>停止扫描</button>
            </div>
        </div>
        
        <div class="card">
            <h2 class="card-title">扫描进度</h2>
            
            <div style="margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                    <span id="current-file">等待开始扫描...</span>
                    <span id="progress-percent">0%</span>
                </div>
                <div style="height: 20px; background-color: #e9ecef; border-radius: var(--border-radius); overflow: hidden;">
                    <div id="progress-bar" style="height: 100%; background: linear-gradient(90deg, var(--primary-color), var(--success-color)); width: 0%; transition: width 0.6s ease;"></div>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px;">
                <div style="background: white; border-radius: var(--border-radius); padding: 15px; text-align: center; box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075));">
                    <div id="stat-files" style="font-size: 1.8rem; font-weight: 500; color: var(--primary-color);">0</div>
                    <div style="font-size: 0.9rem; color: var(--gray-color);">视频文件</div>
                </div>
                <div style="background: white; border-radius: var(--border-radius); padding: 15px; text-align: center; box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075));">
                    <div id="stat-dirs" style="font-size: 1.8rem; font-weight: 500; color: var(--primary-color);">0</div>
                    <div style="font-size: 0.9rem; color: var(--gray-color);">分类目录</div>
                </div>
                <div style="background: white; border-radius: var(--border-radius); padding: 15px; text-align: center; box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075));">
                    <div id="stat-processed" style="font-size: 1.8rem; font-weight: 500; color: var(--primary-color);">0</div>
                    <div style="font-size: 0.9rem; color: var(--gray-color);">已处理</div>
                </div>
                <div style="background: white; border-radius: var(--border-radius); padding: 15px; text-align: center; box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075));">
                    <div id="stat-remaining" style="font-size: 1.8rem; font-weight: 500; color: var(--primary-color);">0</div>
                    <div style="font-size: 0.9rem; color: var(--gray-color);">剩余</div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <h2 class="card-title">操作日志</h2>
            <div id="log" style="max-height: 400px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: var(--border-radius); padding: 15px; background-color: #f8f9fa;">
                <!-- 日志内容将通过JavaScript动态添加 -->
            </div>
        </div>
        
        <script>
            document.getElementById('start-scan').addEventListener('click', function() {
                const scanPath = document.getElementById('scan-path').value;
                const incremental = document.getElementById('incremental-scan').checked;
                
                // 清空日志
                document.getElementById('log').innerHTML = '';
                
                // 重置进度
                document.getElementById('current-file').textContent = '正在初始化扫描...';
                document.getElementById('progress-bar').style.width = '0%';
                document.getElementById('progress-percent').textContent = '0%';
                document.getElementById('stat-files').textContent = '0';
                document.getElementById('stat-dirs').textContent = '0';
                document.getElementById('stat-processed').textContent = '0';
                document.getElementById('stat-remaining').textContent = '0';
                
                // 禁用开始按钮，启用停止按钮
                document.getElementById('start-scan').disabled = true;
                document.getElementById('stop-scan').disabled = false;
                
                // 使用SSE连接
                const eventSource = new EventSource(`../scan.php?action=scan&path=${encodeURIComponent(scanPath)}&incremental=${incremental}`);
                
                eventSource.onmessage = function(e) {
                    const data = JSON.parse(e.data);
                    
                    if (data.type === 'log') {
                        addLogEntry(data.message, data.log_type, data.time);
                    } else if (data.type === 'stats') {
                        updateStats(data);
                    } else if (data.type === 'complete') {
                        completeScan(eventSource);
                    }
                };
                
                eventSource.onerror = function() {
                    if (eventSource.readyState === EventSource.CLOSED) {
                        completeScan(eventSource);
                    } else {
                        addLogEntry('扫描过程中发生错误', 'error', new Date().toLocaleTimeString());
                        completeScan(eventSource);
                    }
                };
                
                document.getElementById('stop-scan').onclick = function() {
                    eventSource.close();
                    addLogEntry('扫描已手动停止', 'warning', new Date().toLocaleTimeString());
                    completeScan(eventSource);
                };
            });
            
            function addLogEntry(message, type, time) {
                const logEntry = document.createElement('div');
                logEntry.style.padding = '8px 0';
                logEntry.style.borderBottom = '1px solid #eee';
                logEntry.style.animation = 'fadeIn 0.3s ease';
                
                const timeSpan = document.createElement('span');
                timeSpan.style.color = 'var(--gray-color)';
                timeSpan.style.fontSize = '0.8rem';
                timeSpan.style.marginRight = '10px';
                timeSpan.textContent = time;
                
                const messageSpan = document.createElement('span');
                messageSpan.style.wordBreak = 'break-all';
                if (type === 'success') messageSpan.style.color = 'var(--success-color)';
                else if (type === 'warning') messageSpan.style.color = 'var(--warning-color)';
                else if (type === 'error') messageSpan.style.color = 'var(--danger-color)';
                messageSpan.textContent = message;
                
                logEntry.appendChild(timeSpan);
                logEntry.appendChild(messageSpan);
                
                const logContainer = document.getElementById('log');
                logContainer.prepend(logEntry);
                logContainer.scrollTop = 0;
            }
            
            function updateStats(data) {
                document.getElementById('stat-files').textContent = data.files;
                document.getElementById('stat-dirs').textContent = data.dirs;
                document.getElementById('stat-processed').textContent = data.processed;
                document.getElementById('stat-remaining').textContent = data.remaining;
                document.getElementById('progress-bar').style.width = `${data.percent}%`;
                document.getElementById('progress-percent').textContent = `${data.percent}%`;
                document.getElementById('current-file').textContent = data.current_file || '正在扫描...';
            }
            
            function completeScan(eventSource) {
                if (eventSource) {
                    eventSource.close();
                }
                
                document.getElementById('start-scan').disabled = false;
                document.getElementById('stop-scan').disabled = true;
                document.getElementById('current-file').textContent = '扫描完成';
            }
        </script>
    </div>
</div>

<!-- 编辑标签模态框 -->
<div id="edit-tags-modal" class="modal">
    <div class="modal-content">
        <h2 class="modal-title" id="modal-title">编辑视频标签</h2>
        <form id="tags-form">
            <input type="hidden" id="modal-video-id">
            <div class="checkbox-list" id="tags-checkbox-list">
                <!-- 标签列表将通过JavaScript动态填充 -->
            </div>
            <div class="modal-actions">
                <button type="submit" class="btn">保存</button>
                <button type="button" class="btn btn-danger" onclick="closeModal()">取消</button>
            </div>
        </form>
    </div>
</div>

<script>
    function switchTab(tabName) {
        // 更新URL
        const url = new URL(window.location);
        url.searchParams.set('tab', tabName);
        window.history.pushState({}, '', url);
        
        // 切换活动标签
        document.querySelectorAll('.tab').forEach(tab => {
            tab.classList.remove('active');
        });
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.remove('active');
        });
        
        document.querySelector(`.tab[onclick="switchTab('${tabName}')"]`).classList.add('active');
        document.getElementById(`${tabName}-tab`).classList.add('active');
    }

    // 打开编辑标签模态框
    function openEditTagsModal(videoId, videoTitle) {
        document.getElementById('modal-title').textContent = `编辑视频标签: ${videoTitle}`;
        document.getElementById('modal-video-id').value = videoId;
        
        // 获取所有标签
        fetch('../get_all_tags.php')
            .then(response => response.json())
            .then(allTags => {
                // 获取视频的当前标签
                fetch(`../get_video_tags.php?video_id=${videoId}`)
                    .then(response => response.json())
                    .then(currentTags => {
                        const container = document.getElementById('tags-checkbox-list');
                        container.innerHTML = '';
                        
                        allTags.forEach(tag => {
                            const div = document.createElement('div');
                            div.className = 'checkbox-item';
                            
                            const checkbox = document.createElement('input');
                            checkbox.type = 'checkbox';
                            checkbox.id = `tag-${tag.id}`;
                            checkbox.name = 'tags[]';
                            checkbox.value = tag.id;
                            if (currentTags.includes(tag.id)) {
                                checkbox.checked = true;
                            }
                            
                            const label = document.createElement('label');
                            label.className = 'checkbox-label';
                            label.htmlFor = `tag-${tag.id}`;
                            label.textContent = tag.name;
                            
                            div.appendChild(checkbox);
                            div.appendChild(label);
                            container.appendChild(div);
                        });
                        
                        document.getElementById('edit-tags-modal').style.display = 'flex';
                    });
            });
    }
    
    // 关闭模态框
    function closeModal() {
        document.getElementById('edit-tags-modal').style.display = 'none';
    }
    
    // 提交标签表单
    document.getElementById('tags-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const videoId = document.getElementById('modal-video-id').value;
        const checkboxes = document.querySelectorAll('#tags-checkbox-list input[type="checkbox"]:checked');
        const selectedTags = Array.from(checkboxes).map(cb => cb.value);
        
        // 使用FormData发送数据
        const formData = new FormData();
        formData.append('video_id', videoId);
        selectedTags.forEach(tag => formData.append('tags[]', tag));
        
        fetch('../update_video_tags.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            // 首先检查响应状态
            if (!response.ok) {
                return response.text().then(text => {
                    throw new Error(text);
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                alert('标签更新成功');
                closeModal();
                location.reload();
            } else {
                alert(data.error || '更新失败');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('更新失败: ' + error.message);
        });
    });
</script>
</body>
</html>