<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
require_once 'db.php';

// 生成唯一用户ID
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = uniqid('user_', true);
}

$db = (new Database())->getConnection();

// 获取目录结构
function getCategories($parent_id = null) {
    global $db;
    
    $sql = "SELECT * FROM categories WHERE parent_id " . ($parent_id ? "= ?" : "IS NULL") . " ORDER BY name";
    $stmt = $db->prepare($sql);
    $params = $parent_id ? [$parent_id] : [];
    $stmt->execute($params);
    
    $categories = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['children'] = getCategories($row['id']);
        $categories[] = $row;
    }
    
    return $categories;
}

$categories = getCategories();

// 获取当前目录下的视频
$current_category_id = isset($_GET['category']) ? (int)$_GET['category'] : null;
$current_tag = isset($_GET['tag']) ? (int)$_GET['tag'] : null;
$videoList = [];

if ($current_category_id) {
    $sql = "
        SELECT v.id, v.title, v.path, 
               GROUP_CONCAT(t.name ORDER BY t.name SEPARATOR ', ') AS tags
        FROM videos v
        LEFT JOIN video_tags vt ON v.id = vt.video_id
        LEFT JOIN tags t ON vt.tag_id = t.id
        WHERE v.category_id = ?
    ";
    
    $params = [$current_category_id];
    
    if ($current_tag) {
        $sql .= " AND EXISTS (
            SELECT 1 FROM video_tags vt2 
            WHERE vt2.video_id = v.id AND vt2.tag_id = ?
        )";
        $params[] = $current_tag;
    }
    
    $sql .= " GROUP BY v.id ORDER BY v.title";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $videoList = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 获取当前分类下的所有标签
$category_tags = [];
if ($current_category_id) {
    $stmt = $db->prepare("
        SELECT t.id, t.name, COUNT(vt.video_id) as video_count
        FROM tags t
        JOIN video_tags vt ON t.id = vt.tag_id
        JOIN videos v ON vt.video_id = v.id
        WHERE v.category_id = ?
        GROUP BY t.id
        ORDER BY video_count DESC, t.name
    ");
    $stmt->execute([$current_category_id]);
    $category_tags = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 更新用户当前观看状态
if ($current_category_id && !empty($videoList)) {
    $current_video_id = $videoList[0]['id'];
    $stmt = $db->prepare("REPLACE INTO user_watching (user_id, video_id, last_update, play_position) VALUES (?, ?, NOW(), 0)");
    $stmt->execute([$_SESSION['user_id'], $current_video_id]);
}

// 递归渲染目录结构
function renderCategories($categories, $level = 0) {
    echo '<ul style="padding-left: ' . ($level * 15) . 'px;">';
    foreach ($categories as $category) {
        $hasChildren = !empty($category['children']);
        echo '<li class="category" data-level="' . $level . '">';
        echo '<a href="?category=' . $category['id'] . '" style="display: block; padding: 8px 16px; text-decoration: none; color: var(--text-color); border-left: ' . ($level * 4) . 'px solid var(--border-color);">';
        if ($hasChildren) {
            echo '<span class="folder-icon">📁</span> ';
        } else {
            echo '<span class="file-icon">📄</span> ';
        }
        echo htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8') . '</a>';
        if ($hasChildren) {
            renderCategories($category['children'], $level + 1);
        }
        echo '</li>';
    }
    echo '</ul>';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GV视频播放器</title>
    <style>
        :root {
            --primary-color: #4285f4;
            --secondary-color: #f1f3f4;
            --text-color: #202124;
            --light-text: #5f6368;
            --border-color: #dadce0;
            --folder-color: #fbbc04;
            --file-color: #34a853;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Roboto', 'Noto Sans SC', sans-serif;
            color: var(--text-color);
            line-height: 1.6;
            background-color: #f8f9fa;
            height: 100vh;
            overflow: hidden;
            touch-action: pan-y;
        }
        
        .container {
            display: flex;
            flex-direction: column;
            height: 100vh;
        }
        
        .header {
            background-color: var(--primary-color);
            color: white;
            padding: 12px 16px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            flex-shrink: 0;
            display: flex;
            align-items: center;
        }
        
        .header h1 {
            font-size: 1.4rem;
            font-weight: 500;
        }
        
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            margin-right: 10px;
            cursor: pointer;
        }
        
        .main-content {
            display: flex;
            flex: 1;
            overflow: hidden;
        }
        
        .sidebar {
            width: 280px;
            background: white;
            border-right: 1px solid var(--border-color);
            overflow-y: auto;
            transition: transform 0.3s ease;
            z-index: 90;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
        }
        
        .sidebar-header {
            padding: 12px 16px;
            font-size: 1.2rem;
            border-bottom: 1px solid var(--border-color);
            background-color: var(--secondary-color);
            position: sticky;
            top: 0;
        }
        
        .sidebar-content {
            flex: 1;
            overflow-y: auto;
        }
        
        .sidebar ul {
            list-style: none;
        }
        
        .sidebar li.category {
            position: relative;
        }
        
        .sidebar li.category > a {
            display: flex;
            align-items: center;
            padding: 10px 16px;
            text-decoration: none;
            color: var(--text-color);
            transition: background-color 0.2s;
            border-left: 4px solid transparent;
        }
        
        .sidebar li.category > a:hover {
            background-color: var(--secondary-color);
        }
        
        .folder-icon {
            color: var(--folder-color);
            margin-right: 8px;
            font-size: 1.1em;
        }
        
        .file-icon {
            color: var(--file-color);
            margin-right: 8px;
            font-size: 1.1em;
        }
        
        .video-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background: white;
        }
        
        .video-info {
            padding: 16px;
            flex-shrink: 0;
            max-height: 40vh;
            overflow-y: auto;
            border-bottom: 1px solid var(--border-color);
        }
        
        .video-content h2 {
            margin-bottom: 16px;
            font-size: 1.3rem;
            color: var(--primary-color);
        }
        
        .current-video-title {
            padding: 12px 16px;
            background-color: var(--secondary-color);
            border-radius: 4px;
            margin-bottom: 16px;
            font-weight: 500;
            text-align: center;
        }
        
        .sync-status {
            display: none;
            padding: 8px;
            background-color: #e6f7ff;
            border-radius: 4px;
            margin-bottom: 10px;
            text-align: center;
            color: #1890ff;
        }
        
        .video-player-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 0;
            background: #000;
            position: relative;
            width: 100%;
        }

        .video-player-container {
            flex: 1;
            display: flex;
            min-height: 0;
            position: relative;
            width: 100%;

        }

        #video-player {
            width: 100% !important;
            height: 88% !important; /* 强制高度 100% */
            object-fit: contain; /* 保持视频比例 */
            background: black;
            }
        
        .toast-notification {
            position: fixed;
            bottom: 30%;
            left: 50%;
            transform: translateX(-50%);
            background-color: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 12px 24px;
            border-radius: 4px;
            font-size: 16px;
            z-index: 1000;
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none;
        }
        
        .toast-notification.show {
            opacity: 1;
        }
        
        .tag {
            display: inline-block;
            background: #e0e0e0;
            padding: 2px 6px;
            border-radius: 10px;
            margin-right: 5px;
            font-size: 0.8em;
        }
        
        .tag-filter {
            margin-bottom: 15px;
            padding-bottom: 5px;
        }
        
        .tag-filter-container {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-top: 5px;
        }
        
        .tag-filter a {
            display: inline-block;
            padding: 3px 8px;
            background: #e0e0e0;
            color: inherit;
            border-radius: 10px;
            text-decoration: none;
            white-space: nowrap;
        }
        
        .tag-filter a.active {
            background: #4285f4;
            color: white;
        }
        
        video::-webkit-media-controls-panel {
            display: flex !important;
            opacity: 1 !important;
        }
        
        @media (max-width: 767px) {
            .sidebar {
                position: fixed;
                top: 60px;
                left: 0;
                width: 280px;
                height: calc(100vh - 60px);
                transform: translateX(-100%);
                transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }
            
            .sidebar.active {
                transform: translateX(0);
                box-shadow: 2px 0 10px rgba(0,0,0,0.2);
            }
            
            .menu-toggle {
                display: block;
            }
            
            .video-info {
                max-height: 30vh;
            }
            
            .sidebar.swipe-out {
                transform: translateX(-100%);
                transition: transform 0.2s ease-out;
            }
            
            .tag-filter-container {
                overflow-x: auto;
                padding-bottom: 5px;
                flex-wrap: nowrap;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <button class="menu-toggle" id="menuToggle">☰</button>
            <h1>←点这里选择分类</h1>
        </header>
        
        <div class="main-content">
            <aside class="sidebar" id="sidebar">
                <div class="sidebar-header">视频分类</div>
                <div class="sidebar-content">
                    <?php renderCategories($categories); ?>
                </div>
            </aside>
            
            <section class="video-content">
                <div class="video-info">
                    <div class="sync-status" id="sync-status"></div>
                    
                    <?php if ($current_category_id): ?>
                        <?php if (!empty($videoList)): ?>
                            <div class="video-switch-tip" style="margin-bottom: 15px; padding: 8px; text-align: center; background-color: #f0f8ff; border-radius: 4px; color: #1e88e5; font-size: 14px; font-weight: 500;">
                                📱 手机: 上下滑动切换视频 | 💻 电脑: 方向键↑↓或滚轮切换视频
                            </div>
                            
                            <?php if (!empty($category_tags)): ?>
                                <div class="tag-filter">
                                    <strong>标签筛选:</strong>
                                    <div class="tag-filter-container">
                                        <a href="?category=<?= $current_category_id ?>" 
                                           class="<?= !$current_tag ? 'active' : '' ?>">
                                            全部
                                        </a>
                                        <?php foreach ($category_tags as $tag): ?>
                                            <a href="?category=<?= $current_category_id ?>&tag=<?= $tag['id'] ?>" 
                                               class="<?= $current_tag == $tag['id'] ? 'active' : '' ?>">
                                                <?= htmlspecialchars($tag['name']) ?> (<?= $tag['video_count'] ?>)
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="current-video-title" id="currentVideoTitle">
                                <?php 
                                    $title = htmlspecialchars($videoList[0]['title'] ?? '', ENT_QUOTES, 'UTF-8');
                                    // 去除第一个 [] 及其内容
                                    $title = preg_replace('/\[[^\]]+\]/', '', $title, 1);
                                    // 去除可能残留的空格
                                    $title = trim($title);
                                    echo $title;
                                ?>
                                <?php if (!empty($videoList[0]['tags'])): ?>
                                    <div class="video-tags" style="margin-top: 5px;">
                                        <?php foreach (explode(', ', $videoList[0]['tags']) as $tag): ?>
                                            <span class="tag"><?= htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <p>此分类下没有视频</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p>请从左侧选择分类</p>
                    <?php endif; ?>
                </div>
                
                <?php if ($current_category_id && !empty($videoList)): ?>
                <div class="video-player-wrapper">
                    <div class="video-player-container">
                        <video id="video-player" controls playsinline>
                            <source src="<?= htmlspecialchars($videoList[0]['path'] ?? '', ENT_QUOTES, 'UTF-8') ?>" type="video/mp4">
                            您的浏览器不支持 HTML5 视频。
                        </video>
                    </div>
                </div>
                <?php endif; ?>
            </section>
        </div>
        
        <div class="toast-notification" id="toastNotification"></div>
    </div>

    <script>
        // 移动端菜单切换
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        let touchStartX = 0;
        let touchEndX = 0;
        let isSwiping = false;
        
        menuToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            sidebar.classList.toggle('active');
        });
        
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 767 && !sidebar.contains(e.target) && e.target !== menuToggle) {
                closeSidebar();
            }
        });
        
        sidebar.addEventListener('touchstart', function(e) {
            touchStartX = e.changedTouches[0].clientX;
            isSwiping = true;
        }, {passive: true});
        
        sidebar.addEventListener('touchmove', function(e) {
            if (!isSwiping) return;
            
            touchEndX = e.changedTouches[0].clientX;
            const diffX = touchStartX - touchEndX;
            
            if (diffX < 0) return;
            
            const translateX = Math.min(0, -diffX);
            sidebar.style.transform = `translateX(${translateX}px)`;
        }, {passive: false});
        
        sidebar.addEventListener('touchend', function(e) {
            if (!isSwiping) return;
            
            touchEndX = e.changedTouches[0].clientX;
            const diffX = touchStartX - touchEndX;
            const threshold = 50;
            
            if (diffX > threshold) {
                closeSidebar();
            } else {
                sidebar.style.transform = '';
            }
            
            isSwiping = false;
        }, {passive: true});
        
        function closeSidebar() {
            sidebar.classList.add('swipe-out');
            setTimeout(() => {
                sidebar.classList.remove('active', 'swipe-out');
                sidebar.style.transform = '';
            }, 200);
        }
        
        function showToast(message, duration = 2000) {
            const toast = document.getElementById('toastNotification');
            toast.textContent = message;
            toast.classList.add('show');
            
            setTimeout(() => {
                toast.classList.remove('show');
            }, duration);
        }
        
        if (document.getElementById('video-player')) {
            const videoPlayer = document.getElementById('video-player');
            const currentVideoTitle = document.getElementById('currentVideoTitle');
            const videoList = <?= json_encode($videoList) ?>;
            const playerContainer = document.querySelector('.video-player-container');
            const syncStatus = document.getElementById('sync-status');
            
            let currentIndex = 0;
            let touchStartY = 0;
            let isInteractingWithPlayer = false;
            let lastPositionUpdateTime = 0;
            let isSyncing = false;
            
            function playVideo(index, position = 0) {
                if (index < 0 || index >= videoList.length) return;
                
                currentIndex = index;
                const video = videoList[currentIndex];
                
                if (videoPlayer.src !== video.path) {
                    videoPlayer.src = video.path;
                    videoPlayer.currentTime = position;
                }
                
                videoPlayer.load();
                videoPlayer.play().catch(e => console.log('播放错误:', e));
                
                // 处理标题：去除第一个 [] 及其内容
                let displayTitle = video.title.replace(/\[[^\]]+\]/, '').trim();
                
                currentVideoTitle.innerHTML = `
                    ${displayTitle}
                    ${video.tags ? `<div class="video-tags" style="margin-top: 5px;">
                        ${video.tags.split(', ').map(tag => `<span class="tag">${tag}</span>`).join('')}
                    </div>` : ''}
                `;
                
                updateWatchingStatus(video.id, position);
            }
            
            function updateWatchingStatus(videoId, position = 0) {
                const now = Date.now();
                if (now - lastPositionUpdateTime < 1000 && position !== 0) return;
                
                lastPositionUpdateTime = now;
                fetch('update_watching.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        user_id: '<?= $_SESSION['user_id'] ?>',
                        video_id: videoId,
                        position: position
                    })
                }).catch(e => console.error('更新状态失败:', e));
            }
            
            function checkAdminSync() {
                fetch('check_admin_sync.php?user_id=<?= $_SESSION['user_id'] ?>')
                    .then(response => response.json())
                    .then(data => {
                        if (data.is_syncing) {
                            syncStatus.textContent = '管理员正在同步观看';
                            syncStatus.style.display = 'block';
                            isSyncing = true;
                        } else {
                            syncStatus.style.display = 'none';
                            isSyncing = false;
                        }
                        
                        setTimeout(checkAdminSync, 5000);
                    });
            }
            
            checkAdminSync();
            
            videoPlayer.addEventListener('timeupdate', function() {
                if (!isSyncing) {
                    updateWatchingStatus(videoList[currentIndex].id, videoPlayer.currentTime);
                }
            });
            
            videoPlayer.addEventListener('play', function() {
                updateWatchingStatus(videoList[currentIndex].id, videoPlayer.currentTime);
            });
            
            videoPlayer.addEventListener('pause', function() {
                updateWatchingStatus(videoList[currentIndex].id, videoPlayer.currentTime);
            });
            
            videoPlayer.addEventListener('seeked', function() {
                updateWatchingStatus(videoList[currentIndex].id, videoPlayer.currentTime);
            });
            
            videoPlayer.addEventListener('touchstart', function(e) {
                isInteractingWithPlayer = true;
                touchStartY = e.touches[0].clientY;
            }, {passive: true});
            
            videoPlayer.addEventListener('touchmove', function(e) {
                if (!isInteractingWithPlayer) return;
                
                const touchY = e.touches[0].clientY;
                const diff = touchStartY - touchY;
                
                if (Math.abs(diff) > 10) {
                    e.preventDefault();
                }
            }, {passive: false});
            
            videoPlayer.addEventListener('touchend', function(e) {
                if (!isInteractingWithPlayer) return;
                
                const touchEndY = e.changedTouches[0].clientY;
                const diff = touchStartY - touchEndY;
                const threshold = 50;
                
                if (diff > threshold) {
                    if (currentIndex < videoList.length - 1) {
                        playVideo(currentIndex + 1);
                    } else {
                        showToast('已经是最后一个视频了');
                    }
                } 
                else if (diff < -threshold) {
                    if (currentIndex > 0) {
                        playVideo(currentIndex - 1);
                    } else {
                        showToast('已经是第一个视频了');
                    }
                }
                
                isInteractingWithPlayer = false;
            }, {passive: true});
            
            videoPlayer.addEventListener('keydown', function(e) {
                if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    if (currentIndex > 0) {
                        playVideo(currentIndex - 1);
                    } else {
                        showToast('已经是第一个视频了');
                    }
                } else if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    if (currentIndex < videoList.length - 1) {
                        playVideo(currentIndex + 1);
                    } else {
                        showToast('已经是最后一个视频了');
                    }
                }
            });
            
            // 滚轮切换视频
            playerContainer.addEventListener('wheel', function(e) {
                e.preventDefault();
                
                if (e.deltaY > 0) {
                    // 向下滚动 - 下一个视频
                    if (currentIndex < videoList.length - 1) {
                        playVideo(currentIndex + 1);
                    } else {
                        showToast('已经是最后一个视频了');
                    }
                } else if (e.deltaY < 0) {
                    // 向上滚动 - 上一个视频
                    if (currentIndex > 0) {
                        playVideo(currentIndex - 1);
                    } else {
                        showToast('已经是第一个视频了');
                    }
                }
            }, { passive: false });
            
            videoPlayer.addEventListener('loadeddata', function() {
                videoPlayer.play().catch(e => console.log('自动播放错误:', e));
            });
            
            videoPlayer.addEventListener('click', function() {
                this.focus();
            });
            
            function adjustPlayerSize() {
                const videoRatio = videoPlayer.videoWidth / videoPlayer.videoHeight || 16/9;
                const containerRatio = playerContainer.clientWidth / playerContainer.clientHeight;
                
                if (videoRatio > containerRatio) {
                    videoPlayer.style.width = '100%';
                    videoPlayer.style.height = 'auto';
                } else {
                    videoPlayer.style.width = 'auto';
                    videoPlayer.style.height = '100%';
                }
            }
            
            videoPlayer.addEventListener('loadedmetadata', adjustPlayerSize);
            window.addEventListener('resize', adjustPlayerSize);
        }
    </script>
</body>
</html>