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
    private $charset = 'utf8mb4';
    private $pdo;

    public function __construct() {
        // Load database credentials from environment variables
        load_env();
        
        $this->host = env('DB_HOST', 'localhost');
        $this->db_name = env('DB_NAME', 'fingerlings');
        $this->username = env('DB_USER', 'root');
        $this->password = env('DB_PASS', '');
        
        $this->connect();
    }
    
    private function connect() {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset={$this->charset}";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            throw new PDOException($e->getMessage(), (int)$e->getCode());
        }
    }
    
    public function getConnection() {
        return $this->pdo;
    }
    
    public function query($sql, $params = []) {
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