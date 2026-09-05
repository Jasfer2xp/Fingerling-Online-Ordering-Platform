<?php

/**
 * Database Configuration
 * Fingerling Online Ordering Platform System
 */

// Load environment variables
require_once __DIR__ . '/helpers.php';

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $port;
    private $driver;
    private $charset = 'utf8mb4';
    private $connectionError = null;

    public function __construct() {
        // Load database credentials from environment variables
        load_env();
        
        $dbUrl = env('DATABASE_URL', env('DB_URL', ''));
        if (!empty($dbUrl)) {
            $parsed = parse_url($dbUrl);
            if ($parsed && !empty($parsed['host'])) {
                $this->host = $parsed['host'];
                $this->port = !empty($parsed['port']) ? (string)$parsed['port'] : '';
                $this->username = isset($parsed['user']) ? urldecode($parsed['user']) : '';
                $this->password = isset($parsed['pass']) ? urldecode($parsed['pass']) : '';
                $this->db_name = isset($parsed['path']) ? ltrim($parsed['path'], '/') : '';
                $scheme = strtolower($parsed['scheme'] ?? '');
                $this->driver = (strpos($scheme, 'postgr') !== false || strpos($scheme, 'pgsql') !== false) ? 'pgsql' : 'mysql';
            }
        }
        
        if (empty($this->host)) {
            $this->host = env('DB_HOST', 'localhost');
            $this->db_name = env('DB_NAME', 'fingerlings');
            $this->username = env('DB_USER', 'root');
            $this->password = env('DB_PASS', '');
            $this->port = env('DB_PORT', '');
            $this->driver = strtolower((string) env('DB_DRIVER', ''));
        }
        
        if (empty($this->driver)) {
            if ($this->port === '5432' || $this->port === '6543' || stripos($this->host, 'supabase') !== false) {
                $this->driver = 'pgsql';
            } else {
                $this->driver = 'mysql';
            }
        }
        
        $this->connect();
    }
    
    private function connect() {
        try {
            if ($this->driver === 'pgsql') {
                $portPart = !empty($this->port) ? ";port={$this->port}" : ";port=5432";
                $dsn = "pgsql:host={$this->host}{$portPart};dbname={$this->db_name}";
            } else {
                $portPart = !empty($this->port) ? ";port={$this->port}" : "";
                $dsn = "mysql:host={$this->host}{$portPart};dbname={$this->db_name};charset={$this->charset}";
            }
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
            $this->connectionError = null;
        } catch (PDOException $e) {
            $this->pdo = null;
            $this->connectionError = $e->getMessage();
            error_log('Database connection error: ' . $e->getMessage());
        }
    }
    
    public function getConnection() {
        return $this->pdo;
    }
    
    public function getDriver() {
        return $this->driver;
    }
    
    public function getError() {
        return $this->connectionError;
    }
    
    public function query($sql, $params = []) {
        if ($this->pdo === null) {
            $this->connect();
            if ($this->pdo === null) {
                throw new PDOException("Database is not connected: " . ($this->connectionError ?? 'Unknown error'));
            }
        }
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new PDOException($e->getMessage(), (int)$e->getCode());
        }
    }
    
    public function fetch($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }
    
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    public function commit() {
        return $this->pdo->commit();
    }
    
    public function rollback() {
        return $this->pdo->rollback();
    }
    
    public function inTransaction() {
        return $this->pdo->inTransaction();
    }
}

// Global database instance
$database = new Database();
$pdo = $database->getConnection();
?>