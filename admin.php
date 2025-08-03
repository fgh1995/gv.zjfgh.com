<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
require_once 'db.php';

$db = (new Database())->getConnection();

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
function getOnlineUsers() {
    global $db;
    $stmt = $db->prepare("
        SELECT u.user_id, v.id as video_id, v.title, v.path, u.play_position, u.last_update 
        FROM user_watching u
        JOIN videos v ON u.video_id = v.id
        WHERE u.last_update > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ORDER BY u.last_update DESC
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$onlineUsers = getOnlineUsers();

// 获取所有视频
$stmt = $db->prepare("SELECT id, title FROM videos ORDER BY title");
$stmt->execute();
$allVideos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>视频同步控制面板</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <style>
        * {
            box-sizing: border-box;
        }
        body { 
            font-family: Arial, sans-serif; 
            margin: 10px; 
            padding: 0;
            font-size: 14px;
        }
        table { 
            border-collapse: collapse; 
            width: 100%; 
            font-size: 12px;
        }
        th, td { 
            border: 1px solid #ddd; 
            padding: 6px; 
            text-align: left; 
            word-break: break-word;
        }
        th { 
            background-color: #f2f2f2; 
            position: sticky;
            top: 0;
        }
        .online { color: green; }
        .offline { color: gray; }
        .syncing { background-color: #e6f7ff; }
        #player-container { 
            margin: 10px 0; 
            position: relative;
            width: 100%;
        }
        #admin-player { 
            width: 100%; 
            height: auto;
            max-height: 200px;
        }
        .sync-info { 
            background: #e6f7ff; 
            padding: 8px; 
            margin-bottom: 10px;
            border-radius: 5px;
        }
        .btn {
            display: inline-block;
            padding: 6px 12px;
            background-color: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 12px;
            margin: 2px 0;
        }
        .btn-stop {
            background-color: #f44336;
        }
        .user-id {
            font-weight: bold;
        }
        .video-title {
            color: #333;
        }
        .last-update {
            color: #666;
            font-size: 11px;
        }
        .action-cell {
            min-width: 80px;
        }
        
        /* 响应式调整 */
        @media (min-width: 768px) {
            body {
                font-size: 16px;
                margin: 20px;
            }
            th, td {
                padding: 8px;
                font-size: 14px;
            }
            #admin-player {
                max-height: 400px;
            }
            .btn {
                padding: 8px 16px;
                font-size: 14px;
            }
        }
        
        /* 移动设备上的表格滚动 */
        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <h1>视频同步控制面板</h1>
    
    <?php if (isset($_SESSION['syncing_user'])): ?>
    <div class="sync-info">
        <div>正在同步观看用户: <strong><?= htmlspecialchars($_SESSION['syncing_user']) ?></strong></div>
        <a href="?stop_sync=1" class="btn btn-stop">停止同步</a>
    </div>
    
    <div id="player-container">
        <video id="admin-player" controls playsinline></video>
        <div>同步状态: <span id="sync-status">正在连接...</span></div>
    </div>
    <?php endif; ?>
    
    <h2>在线用户 (最近5分钟活跃)</h2>
    <div class="table-container">
        <table>
            <tr>
                <th>用户ID</th>
                <th>正在观看</th>
                <th>播放位置</th>
                <th>最后活跃</th>
                <th class="action-cell">操作</th>
            </tr>
            <?php foreach ($onlineUsers as $user): ?>
            <tr class="<?= isset($_SESSION['syncing_user']) && $_SESSION['syncing_user'] == $user['user_id'] ? 'syncing' : '' ?>">
                <td class="user-id"><?= htmlspecialchars($user['user_id']) ?></td>
                <td class="video-title"><?= htmlspecialchars($user['title']) ?></td>
                <td><?= gmdate("H:i:s", $user['play_position']) ?></td>
                <td class="last-update"><?= $user['last_update'] ?></td>
                <td class="action-cell">
                    <?php if (!isset($_SESSION['syncing_user']) || $_SESSION['syncing_user'] != $user['user_id']): ?>
                    <a href="?sync_with=<?= urlencode($user['user_id']) ?>" class="btn">同步</a>
                    <?php else: ?>
                    <span style="color: #2196F3;">同步中</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <script>
        <?php if (isset($_SESSION['syncing_user'])): ?>
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
            fetch('get_user_status.php?user_id=<?= urlencode($_SESSION['syncing_user']) ?>')
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        syncStatus.textContent = '错误: ' + data.error;
                        clearInterval(syncInterval);
                        return;
                    }
                    
                    // 更新数据库中的同步状态时间戳
                    fetch('update_admin_sync.php', {
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
            fetch('stop_admin_sync.php?admin_id=<?= session_id() ?>');
        });
        <?php endif; ?>
        
        // 防止iOS设备上的橡皮筋效果
        document.addEventListener('touchmove', function(e) {
            if (e.target === adminPlayer) {
                e.preventDefault();
            }
        }, { passive: false });
    </script>
</body>
</html>