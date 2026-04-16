<?php

/**
 * Database Singleton Class
 * Provides a unified way to get a PDO database connection
 */
class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        $this->pdo = getPDO();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }

    /**
     * Test the database connection and return details
     */
    public static function testConnection() {
        try {
            $db = self::getInstance();
            $conn = $db->getConnection();
            $stmt = $conn->query("SELECT VERSION() as version");
            $row = $stmt->fetch();
            
            return [
                'success' => true,
                'message' => 'Connected successfully',
                'version' => $row['version'],
                'config' => [
                    'host' => DB_HOST,
                    'port' => DB_PORT,
                    'database' => DB_NAME,
                    'user' => DB_USER
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'config' => [
                    'host' => defined('DB_HOST') ? DB_HOST : 'Not defined',
                    'port' => defined('DB_PORT') ? DB_PORT : 'Not defined',
                    'database' => defined('DB_NAME') ? DB_NAME : 'Not defined',
                    'user' => defined('DB_USER') ? DB_USER : 'Not defined'
                ]
            ];
        }
    }
}
