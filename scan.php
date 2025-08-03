<?php
header('Content-Type: text/html; charset=utf-8');
require_once 'db.php';
require __DIR__ . '/vendor/autoload.php'; // 加载 Composer 依赖
    use Overtrue\Pinyin\Pinyin; // 引入拼音类
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
    
    // 扫描文件夹结构
    public function scanDirectory($incremental = true) {
        echo '<!DOCTYPE html>
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
                <h2 class="card-title">扫描进度</h2>
                <div class="progress-container">
                    <div class="progress-info">
                        <span id="current-file">准备开始扫描...</span>
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
        </div>';
        
        ob_flush();
        flush();
        
        if (!$incremental) {
            $this->clearExistingData();
            $this->logMessage("已清空现有数据", "warning");
        }
        
        // 先统计文件总数
        $this->countItems($this->base_path);
        $this->updateStats();
        
        // 清理不存在的分类和视频
        $this->cleanupMissingItems();
        
        // 扫描目录
        $this->processRootDirectory($this->base_path);
        
        echo '<script>
            document.getElementById("current-file").innerHTML = "扫描完成！";
            document.getElementById("progress-bar").style.width = "100%";
            document.getElementById("progress-percent").innerHTML = "100%";
        </script>';
        echo '</body></html>';
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
    
    // 更新统计信息
    private function updateStats() {
        $total = $this->file_count + $this->dir_count;
        $percent = $total > 0 ? round(($this->processed_files / $total) * 100) : 0;
        
        echo '<script>
            document.getElementById("stat-files").innerHTML = "' . $this->file_count . '";
            document.getElementById("stat-dirs").innerHTML = "' . $this->dir_count . '";
            document.getElementById("stat-processed").innerHTML = "' . $this->processed_files . '";
            document.getElementById("stat-remaining").innerHTML = "' . ($total - $this->processed_files) . '";
            document.getElementById("progress-bar").style.width = "' . $percent . '%";
            document.getElementById("progress-percent").innerHTML = "' . $percent . '%";
        </script>';
        ob_flush();
        flush();
    }
    
    // 清理不存在的分类和视频
    private function cleanupMissingItems() {
        // 清理不存在的视频文件
        $stmt = $this->db->query("SELECT id, path FROM videos");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!file_exists($row['path'])) {
                $this->db->prepare("DELETE FROM videos WHERE id = ?")->execute([$row['id']]);
                $this->logMessage("删除不存在的视频: " . $row['path'], "error");
            }
        }
        
        // 清理不存在的分类目录
        $stmt = $this->db->query("SELECT id, path FROM categories");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $full_path = $this->base_path . $row['path'];
            if (!file_exists($full_path) || !is_dir($full_path)) {
                $this->db->prepare("DELETE FROM categories WHERE id = ?")->execute([$row['id']]);
                $this->logMessage("删除不存在的分类: " . $row['path'], "error");
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
                $this->updateStats();
            } else {
                $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
                if (in_array($ext, ['mp4', 'avi', 'mkv', 'mov', 'wmv', 'flv', 'webm'])) {
                    $this->updateStatus("处理文件: " . $item);
                    $this->addVideo($item, $full_path, null);
                    $this->processed_files++;
                    $this->updateStats();
                }
            }
        }
    }
    
    private function processDirectory($path, $parent_id = null) {
        $relative_path = str_replace($this->base_path, '', $path);
        $dir_name = basename($path);
        
        $this->updateStatus("正在扫描目录: " . $relative_path);
        
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
                $this->updateStats();
            } else {
                $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
                if (in_array($ext, ['mp4', 'avi', 'mkv', 'mov', 'wmv', 'flv', 'webm'])) {
                    $this->updateStatus("处理文件: " . $item);
                    $this->addVideo($item, $full_path, $category_id);
                    $this->processed_files++;
                    $this->updateStats();
                }
            }
        }
    }
    
    private function updateStatus($message) {
        echo '<script>
            document.getElementById("current-file").innerHTML = "' . addslashes($message) . '";
        </script>';
        ob_flush();
        flush();
    }
    
    private function logMessage($message, $type = "success") {
        $time = date("H:i:s");
        echo '<script>
            var logEntry = document.createElement("div");
            logEntry.className = "log-entry";
            logEntry.innerHTML = \'<span class="log-time">\' + "' . $time . '" + \'</span>\' +
                               \'<span class="log-message log-' . $type . '">\' + "' . addslashes($message) . '" + \'</span>\';
            document.getElementById("log").prepend(logEntry);
            document.getElementById("log").scrollTop = 0;
        </script>';
        ob_flush();
        flush();
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
                $this->logMessage("更新分类名称: {$current_name} -> {$name}", "warning");
            }
            
            return $row['id'];
        } else {
            $stmt = $this->db->prepare("INSERT INTO categories (name, path, parent_id) VALUES (?, ?, ?)");
            $stmt->execute([$name, $path, $parent_id]);
            $this->logMessage("创建分类: " . $name . " (路径: " . $path . ")");
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
                $this->logMessage("更新视频信息: " . $title, "warning");
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
            $this->logMessage("添加视频: " . $title);
            
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
            $this->logMessage("创建标签: " . $name);
            
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

// 使用示例
$scanner = new VideoScanner('videos');
$scanner->scanDirectory();
?>