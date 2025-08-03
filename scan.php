<?php
header('Content-Type: text/html; charset=utf-8');
require_once 'db.php';
require __DIR__ . '/vendor/autoload.php'; // 加载 Composer 依赖
use Overtrue\Pinyin\Pinyin; // 引入拼音类

// 如果是AJAX请求，处理扫描逻辑
if (isset($_GET['action']) && $_GET['action'] == 'scan') {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    
    $incremental = isset($_GET['incremental']) && $_GET['incremental'] == 'true';
    $base_path = isset($_GET['path']) ? $_GET['path'] : 'videos';
    
    $scanner = new VideoScanner($base_path);
    $scanner->scanDirectorySSE($incremental);
    exit;
}

class VideoScanner {
    private $db;
    private $base_path;
    private $file_count = 0;
    private $dir_count = 0;
    private $processed_files = 0;
    
    public function __construct($base_path) {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->base_path = rtrim($base_path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }
    
    // 清空现有数据
    public function clearExistingData() {
        // 禁用外键检查以便清空表
        $this->db->exec('SET FOREIGN_KEY_CHECKS = 0');
        $this->db->exec('TRUNCATE TABLE videos');
        $this->db->exec('TRUNCATE TABLE categories');
        $this->db->exec('TRUNCATE TABLE tags');
        $this->db->exec('TRUNCATE TABLE video_tags');
        $this->db->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
    
    // 使用SSE进行扫描
    public function scanDirectorySSE($incremental = true) {
        if (!$incremental) {
            $this->clearExistingData();
            $this->sendMessage("已清空现有数据", "warning");
        }
        
        // 先统计文件总数
        $this->countItems($this->base_path);
        $this->sendStats();
        
        // 清理不存在的分类和视频
        $this->cleanupMissingItems();
        
        // 扫描目录
        $this->processRootDirectory($this->base_path);
        
        $this->sendMessage("扫描完成！", "success");
        $this->sendEvent("complete", ["message" => "扫描完成"]);
    }
    
    // 发送SSE消息
    private function sendMessage($message, $type = "success") {
        $data = [
            'type' => 'log',
            'message' => $message,
            'log_type' => $type,
            'time' => date("H:i:s")
        ];
        echo "data: " . json_encode($data) . "\n\n";
        ob_flush();
        flush();
    }
    
    // 发送统计信息
    private function sendStats() {
        $total = $this->file_count + $this->dir_count;
        $percent = $total > 0 ? round(($this->processed_files / $total) * 100) : 0;
        
        $data = [
            'type' => 'stats',
            'files' => $this->file_count,
            'dirs' => $this->dir_count,
            'processed' => $this->processed_files,
            'remaining' => ($total - $this->processed_files),
            'percent' => $percent,
            'current_file' => "正在扫描..."
        ];
        
        echo "data: " . json_encode($data) . "\n\n";
        ob_flush();
        flush();
    }
    
    // 发送自定义事件
    private function sendEvent($event, $data) {
        $data['type'] = $event;
        echo "event: $event\n";
        echo "data: " . json_encode($data) . "\n\n";
        ob_flush();
        flush();
    }
    
    // 统计文件和目录数量
    private function countItems($path) {
        $items = scandir($path);
        foreach ($items as $item) {
            if ($item == '.' || $item == '..') continue;
            
            $full_path = $path . DIRECTORY_SEPARATOR . $item;
            
            if (is_dir($full_path)) {
                $this->dir_count++;
                $this->countItems($full_path);
            } else {
                $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
                if (in_array($ext, ['mp4', 'avi', 'mkv', 'mov', 'wmv', 'flv', 'webm'])) {
                    $this->file_count++;
                }
            }
        }
    }
    
    // 清理不存在的分类和视频
    private function cleanupMissingItems() {
        // 清理不存在的视频文件
        $stmt = $this->db->query("SELECT id, path FROM videos");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!file_exists($row['path'])) {
                $this->db->prepare("DELETE FROM videos WHERE id = ?")->execute([$row['id']]);
                $this->sendMessage("删除不存在的视频: " . $row['path'], "error");
            }
        }
        
        // 清理不存在的分类目录
        $stmt = $this->db->query("SELECT id, path FROM categories");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $full_path = $this->base_path . $row['path'];
            if (!file_exists($full_path) || !is_dir($full_path)) {
                $this->db->prepare("DELETE FROM categories WHERE id = ?")->execute([$row['id']]);
                $this->sendMessage("删除不存在的分类: " . $row['path'], "error");
            }
        }
    }
    
    // 处理根目录下的子目录
    private function processRootDirectory($path) {
        $items = scandir($path);
        foreach ($items as $item) {
            if ($item == '.' || $item == '..') continue;
            
            $full_path = $path . DIRECTORY_SEPARATOR . $item;
            
            if (is_dir($full_path)) {
                $this->processDirectory($full_path, null);
                $this->processed_files++;
                $this->sendStats();
            } else {
                $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
                if (in_array($ext, ['mp4', 'avi', 'mkv', 'mov', 'wmv', 'flv', 'webm'])) {
                    $this->sendMessage("处理文件: " . $item);
                    $this->addVideo($item, $full_path, null);
                    $this->processed_files++;
                    $this->sendStats();
                }
            }
        }
    }
    
    private function processDirectory($path, $parent_id = null) {
        $relative_path = str_replace($this->base_path, '', $path);
        $dir_name = basename($path);
        
        $this->sendMessage("正在扫描目录: " . $relative_path);
        
        // 插入或获取目录ID
        $category_id = $this->getOrCreateCategory($dir_name, $relative_path, $parent_id);
        
        // 扫描子目录
        $items = scandir($path);
        foreach ($items as $item) {
            if ($item == '.' || $item == '..') continue;
            
            $full_path = $path . DIRECTORY_SEPARATOR . $item;
            
            if (is_dir($full_path)) {
                $this->processDirectory($full_path, $category_id);
                $this->processed_files++;
                $this->sendStats();
            } else {
                $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
                if (in_array($ext, ['mp4', 'avi', 'mkv', 'mov', 'wmv', 'flv', 'webm'])) {
                    $this->sendMessage("处理文件: " . $item);
                    $this->addVideo($item, $full_path, $category_id);
                    $this->processed_files++;
                    $this->sendStats();
                }
            }
        }
    }
    
    private function getOrCreateCategory($name, $path, $parent_id) {
        $stmt = $this->db->prepare("SELECT id FROM categories WHERE path = ?");
        $stmt->execute([$path]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            // 检查分类名称是否变化
            $stmt = $this->db->prepare("SELECT name FROM categories WHERE id = ?");
            $stmt->execute([$row['id']]);
            $current_name = $stmt->fetchColumn();
            
            if ($current_name != $name) {
                $this->db->prepare("UPDATE categories SET name = ? WHERE id = ?")
                    ->execute([$name, $row['id']]);
                $this->sendMessage("更新分类名称: {$current_name} -> {$name}", "warning");
            }
            
            return $row['id'];
        } else {
            $stmt = $this->db->prepare("INSERT INTO categories (name, path, parent_id) VALUES (?, ?, ?)");
            $stmt->execute([$name, $path, $parent_id]);
            $this->sendMessage("创建分类: " . $name . " (路径: " . $path . ")");
            return $this->db->lastInsertId();
        }
    }
    
    private function addVideo($title, $path, $category_id) {
        // 从文件名提取标签
        $tags = $this->extractTagsFromFilename($title);
        // 检查是否已存在（按路径查找）
        $stmt = $this->db->prepare("SELECT id, title, category_id FROM videos WHERE path = ?");
        $stmt->execute([$path]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            // 更新已存在的视频记录
            $updates = [];
            $params = [];
            
            if ($row['title'] != $title) {
                $updates[] = "title = ?";
                $params[] = $title;
            }
            
            if ($row['category_id'] != $category_id) {
                $updates[] = "category_id = ?";
                $params[] = $category_id;
            }
            
            if (!empty($updates)) {
                $params[] = $row['id'];
                $sql = "UPDATE videos SET " . implode(", ", $updates) . " WHERE id = ?";
                $this->db->prepare($sql)->execute($params);
                $this->sendMessage("更新视频信息: " . $title, "warning");
            }
            
            // 更新标签
            $this->updateVideoTags($row['id'], $tags);
        } else {
            // 插入新视频
            $stmt = $this->db->prepare("
                INSERT INTO videos (title, path, category_id, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$title, $path, $category_id]);
            $video_id = $this->db->lastInsertId();
            $this->sendMessage("添加视频: " . $title);
            
            // 添加标签
            $this->updateVideoTags($video_id, $tags);
        }
    }
    
    // 从文件名提取标签
    private function extractTagsFromFilename($filename) {
        // 移除扩展名
        $filename = pathinfo($filename, PATHINFO_FILENAME);
        
        // 尝试提取第一个[]内的内容（修改正则表达式以支持中文字符）
        preg_match('/\[([^\]]+)\]/u', $filename, $matches);
        
        if (isset($matches[1])) {
            // 有[]标签的情况
            $tag_content = $matches[1];
            
            // 使用逗号或空格分割标签
            $tags = preg_split('/[\s,]+/u', $tag_content);
            
            // 过滤无效标签
            $tags = array_filter($tags, function($tag) {
                $tag = trim($tag);
                return !empty($tag) && strlen($tag) > 1;
            });
            
            // 转换为小写并去重
            $tags = array_unique(array_map('strtolower', $tags));
            
            return array_values($tags);
        }
        
        // 没有[]标签的情况 - 直接返回"其他"
        return ['其他'];
    }
    
    // 更新视频标签
    private function updateVideoTags($video_id, $tags) {
        // 先删除旧标签
        $this->db->prepare("DELETE FROM video_tags WHERE video_id = ?")->execute([$video_id]);
        
        foreach ($tags as $tag_name) {
            // 获取或创建标签
            $tag_id = $this->getOrCreateTag($tag_name);
            
            // 关联视频和标签
            $stmt = $this->db->prepare("
                INSERT IGNORE INTO video_tags (video_id, tag_id) 
                VALUES (?, ?)
            ");
            $stmt->execute([$video_id, $tag_id]);
        }
    }
    
    // 获取或创建标签
    private function getOrCreateTag($name) {
        $slug = $this->createSlug($name);
        // 检查是否已存在
        $stmt = $this->db->prepare("SELECT id FROM tags WHERE slug = ?");
        $stmt->execute([$slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            return $row['id'];
        } else {
            $stmt = $this->db->prepare("INSERT INTO tags (name, slug) VALUES (?, ?)");
            $stmt->execute([$name, $slug]);
            $this->sendMessage("创建标签: " . $name);
            
            return $this->db->lastInsertId();
        }
    }
    
    private function createSlug($name) {
        // 中文转拼音（如 "女记者采访" → "nv-ji-zhe-cai-fang"）
        $slug = (new Pinyin())->permalink($name);
        
        // 如果转换失败（比如非中文），再尝试其他方式
        if (empty($slug)) {
            $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($name));
            $slug = trim($slug, '-');
        }
        
        // 最终回退值（避免空值）
        return $slug ?: 'untagged';
    }
}

// 如果不是AJAX请求，显示HTML界面
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <title>视频库扫描工具</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --success-color: #4cc9f0;
            --danger-color: #f72585;
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
            padding: 20px;
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
        
        .progress-container {
            margin-bottom: 20px;
        }
        
        .progress-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .progress-bar {
            height: 20px;
            background-color: #e9ecef;
            border-radius: var(--border-radius);
            overflow: hidden;
            margin-bottom: 10px;
        }
        
        .progress {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-color), var(--success-color));
            width: 0;
            transition: width 0.6s ease;
            border-radius: var(--border-radius);
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 15px;
            text-align: center;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        .stat-value {
            font-size: 1.8rem;
            font-weight: 500;
            color: var(--primary-color);
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: var(--gray-color);
        }
        
        .log-container {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: var(--border-radius);
            padding: 15px;
            background-color: #f8f9fa;
        }
        
        .log-entry {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
            animation: fadeIn 0.3s ease;
        }
        
        .log-entry:last-child {
            border-bottom: none;
        }
        
        .log-time {
            color: var(--gray-color);
            font-size: 0.8rem;
            margin-right: 10px;
        }
        
        .log-message {
            word-break: break-all;
        }
        
        .log-success {
            color: var(--success-color);
        }
        
        .log-warning {
            color: #ffc107;
        }
        
        .log-error {
            color: var(--danger-color);
        }
        
        .scan-controls {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
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
        }
        
        .btn:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
        }
        
        .btn:disabled {
            background-color: var(--gray-color);
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-danger {
            background-color: var(--danger-color);
        }
        
        .btn-success {
            background-color: var(--success-color);
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
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @media (max-width: 768px) {
            .stats {
                grid-template-columns: 1fr;
            }
            
            .container {
                padding: 10px;
            }
            
            .card {
                padding: 15px;
            }
            
            .scan-controls {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>视频库扫描工具</h1>
        <p>自动扫描并整理您的视频文件</p>
    </div>
    
    <div class="card">
        <h2 class="card-title">扫描设置</h2>
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
        <div class="scan-controls">
            <button id="start-scan" class="btn btn-success">开始扫描</button>
            <button id="stop-scan" class="btn btn-danger" disabled>停止扫描</button>
        </div>
    </div>
    
    <div class="card">
        <h2 class="card-title">扫描进度</h2>
        <div class="progress-container">
            <div class="progress-info">
                <span id="current-file">等待开始扫描...</span>
                <span id="progress-percent">0%</span>
            </div>
            <div class="progress-bar">
                <div class="progress" id="progress-bar"></div>
            </div>
        </div>
        
        <div class="stats">
            <div class="stat-card">
                <div class="stat-value" id="stat-files">0</div>
                <div class="stat-label">视频文件</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="stat-dirs">0</div>
                <div class="stat-label">分类目录</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="stat-processed">0</div>
                <div class="stat-label">已处理</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="stat-remaining">0</div>
                <div class="stat-label">剩余</div>
            </div>
        </div>
    </div>
    
    <div class="card">
        <h2 class="card-title">操作日志</h2>
        <div class="log-container" id="log">
            <!-- 日志内容将通过JavaScript动态添加 -->
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const startBtn = document.getElementById('start-scan');
        const stopBtn = document.getElementById('stop-scan');
        const logContainer = document.getElementById('log');
        let eventSource = null;
        let isScanning = false;
        
        // 开始扫描
        startBtn.addEventListener('click', function() {
            if (isScanning) return;
            
            const scanPath = document.getElementById('scan-path').value;
            const incremental = document.getElementById('incremental-scan').checked;
            
            // 清空日志
            logContainer.innerHTML = '';
            
            // 重置进度
            document.getElementById('current-file').textContent = '正在初始化扫描...';
            document.getElementById('progress-bar').style.width = '0%';
            document.getElementById('progress-percent').textContent = '0%';
            document.getElementById('stat-files').textContent = '0';
            document.getElementById('stat-dirs').textContent = '0';
            document.getElementById('stat-processed').textContent = '0';
            document.getElementById('stat-remaining').textContent = '0';
            
            // 禁用开始按钮，启用停止按钮
            startBtn.disabled = true;
            stopBtn.disabled = false;
            isScanning = true;
            
            // 使用SSE连接
            eventSource = new EventSource(`?action=scan&path=${encodeURIComponent(scanPath)}&incremental=${incremental}`);
            
            eventSource.onmessage = function(e) {
                const data = JSON.parse(e.data);
                
                if (data.type === 'log') {
                    addLogEntry(data.message, data.log_type, data.time);
                } else if (data.type === 'stats') {
                    updateStats(data);
                } else if (data.type === 'complete') {
                    completeScan();
                }
            };
            
            eventSource.onerror = function() {
                if (eventSource.readyState === EventSource.CLOSED) {
                    completeScan();
                } else {
                    addLogEntry('扫描过程中发生错误', 'error', new Date().toLocaleTimeString());
                    completeScan();
                }
            };
        });
        
        // 停止扫描
        stopBtn.addEventListener('click', function() {
            if (eventSource) {
                eventSource.close();
                addLogEntry('扫描已手动停止', 'warning', new Date().toLocaleTimeString());
                completeScan();
            }
        });
        
        // 添加日志条目
        function addLogEntry(message, type, time) {
            const logEntry = document.createElement('div');
            logEntry.className = 'log-entry';
            
            const timeSpan = document.createElement('span');
            timeSpan.className = 'log-time';
            timeSpan.textContent = time;
            
            const messageSpan = document.createElement('span');
            messageSpan.className = `log-message log-${type}`;
            messageSpan.textContent = message;
            
            logEntry.appendChild(timeSpan);
            logEntry.appendChild(messageSpan);
            
            logContainer.prepend(logEntry);
            logContainer.scrollTop = 0;
        }
        
        // 更新统计信息
        function updateStats(data) {
            document.getElementById('stat-files').textContent = data.files;
            document.getElementById('stat-dirs').textContent = data.dirs;
            document.getElementById('stat-processed').textContent = data.processed;
            document.getElementById('stat-remaining').textContent = data.remaining;
            document.getElementById('progress-bar').style.width = `${data.percent}%`;
            document.getElementById('progress-percent').textContent = `${data.percent}%`;
            document.getElementById('current-file').textContent = data.current_file || '正在扫描...';
        }
        
        // 完成扫描
        function completeScan() {
            if (eventSource) {
                eventSource.close();
            }
            
            startBtn.disabled = false;
            stopBtn.disabled = true;
            isScanning = false;
            
            document.getElementById('current-file').textContent = isScanning ? '正在扫描...' : '扫描完成';
        }
    });
</script>
</body>
</html>