<?php
class Database {
    private $host = 'localhost';
    private $db_name = 'gvideo';
    private $username = 'gvideo';
    private $password = 'f360967847';
    private $conn;
    
    public function __construct() {
        try {
            $this->conn = new PDO(
                "mysql:host={$this->host};dbname={$this->db_name}", 
                $this->username, 
                $this->password,
                [
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
            
            // 检查并创建表（如果不存在）
            $this->createTablesIfNotExist();
        } catch (PDOException $e) {
            // 如果数据库不存在，尝试创建
            if ($e->getCode() === 1049) {
                $this->createDatabaseAndTables();
            } else {
                die("数据库连接失败: " . $e->getMessage());
            }
        }
    }
    
    private function createDatabaseAndTables() {
        try {
            // 临时连接（不带数据库名）
            $tempConn = new PDO(
                "mysql:host={$this->host}", 
                $this->username, 
                $this->password
            );
            
            // 创建数据库
            $tempConn->exec("CREATE DATABASE IF NOT EXISTS {$this->db_name} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $tempConn->exec("USE {$this->db_name}");
            
            // 创建表
            $this->createTables($tempConn);
            
            // 重新连接正式数据库
            $this->conn = new PDO(
                "mysql:host={$this->host};dbname={$this->db_name}", 
                $this->username, 
                $this->password,
                [
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        } catch (PDOException $e) {
            die("数据库初始化失败: " . $e->getMessage());
        }
    }
    
    private function createTablesIfNotExist() {
        // 1. 创建 categories 表（目录分类）
        $this->conn->exec("
            CREATE TABLE IF NOT EXISTS categories (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                path VARCHAR(512) NOT NULL,
                parent_id INT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // 2. 创建 videos 表（视频信息）
        $this->conn->exec("
            CREATE TABLE IF NOT EXISTS videos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                path VARCHAR(512) NOT NULL,
                category_id INT NOT NULL,
                file_size BIGINT,
                duration INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // 3. 创建 user_watching 表（用户观看记录）
        $this->conn->exec("
            CREATE TABLE IF NOT EXISTS user_watching (
                user_id VARCHAR(64) NOT NULL,
                video_id INT NOT NULL,
                play_position FLOAT DEFAULT 0,
                last_update TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (user_id),
                FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // 4. 创建 admin_sync 表（管理员同步状态）
        $this->conn->exec("
            CREATE TABLE IF NOT EXISTS admin_sync (
                admin_id VARCHAR(64) NOT NULL,
                target_user_id VARCHAR(64) NOT NULL,
                last_update TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (admin_id),
                INDEX (target_user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // 5. 创建 tags 表（标签）
        $this->conn->exec("
            CREATE TABLE IF NOT EXISTS tags (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                slug VARCHAR(100) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY (slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // 6. 创建 video_tags 表（视频标签关联）
        $this->conn->exec("
            CREATE TABLE IF NOT EXISTS video_tags (
                video_id INT NOT NULL,
                tag_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (video_id, tag_id),
                FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
                FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
    
    public function getConnection() {
        return $this->conn;
    }
}