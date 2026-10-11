<?php

class Database {

    private $host = 'mysql.hostinger.in';
    private $db_name = 'u554540856_shiva';
    private $username = 'u554540856_shiva';
    private $password = '+4Ik2y+k!X';

    private static $instance = null;
    private $conn;

    // 🔒 Private constructor (Singleton support)
    private function __construct() {}

    // ✅ Singleton access (for old / other files)
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    // ✅ PDO connection (main method)
    public function getConnection() {
        if ($this->conn === null) {
            try {
                $this->conn = new PDO(
                    "mysql:host={$this->host};dbname={$this->db_name}",
                    $this->username,
                    $this->password,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false
                    ]
                );
                $this->conn->exec("SET NAMES utf8mb4");
            } catch (PDOException $e) {
                die("Database connection failed");
            }
        }
        return $this->conn;
    }

    // ✅ Registration ID generator (unchanged logic)
    public function generateRegistrationId($course_name) {
        $prefix = 'PI';
        $course_code = substr(
            strtoupper(preg_replace('/[^A-Z]/', '', $course_name)),
            0,
            3
        );
        $date = date('Ymd');
        $random = rand(1000, 9999);

        return $prefix . $course_code . $date . $random;
    }
}
