<?php
class Database {
    private $conn;

    public function __construct() {
        $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($this->conn->connect_error) {
            throw new Exception("Connection failed: " . $this->conn->connect_error);
        }
        $this->conn->set_charset("utf8");
    }

    public function checkSourceTable() {
        $sql = "SHOW TABLES LIKE '" . SOURCE_TABLE . "'";
        $result = $this->conn->query($sql);
        if ($result->num_rows == 0) {
            throw new Exception("Source table " . SOURCE_TABLE . " does not exist.");
        }

        $sql = "SHOW COLUMNS FROM " . SOURCE_TABLE . " LIKE '" . SOURCE_ASIN_COLUMN . "'";
        $result = $this->conn->query($sql);
        if ($result->num_rows == 0) {
            throw new Exception("Source column " . SOURCE_ASIN_COLUMN . " does not exist in table " . SOURCE_TABLE);
        }
    }

    public function checkTargetTable() {
        $sql = "SHOW TABLES LIKE '" . TARGET_TABLE . "'";
        $result = $this->conn->query($sql);
        return $result->num_rows > 0;
    }

    public function createTargetTable() {
        $sql = "CREATE TABLE " . TARGET_TABLE . " (
            id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            asin VARCHAR(10) NOT NULL,
            date DATE NOT NULL,
            rank INT(11) UNSIGNED NOT NULL,
            UNIQUE KEY unique_asin_date (asin, date)
        )";
        if (!$this->conn->query($sql)) {
            throw new Exception("Failed to create target table: " . $this->conn->error);
        }
    }

    public function getCreateTableStatement() {
        return "CREATE TABLE " . TARGET_TABLE . " (
            id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            asin VARCHAR(10) NOT NULL,
            date DATE NOT NULL,
            rank INT(11) UNSIGNED NOT NULL,
            UNIQUE KEY unique_asin_date (asin, date)
        )";
    }

    public function getASINsToUpdate() {
        $sql = "SELECT DISTINCT tm." . SOURCE_ASIN_COLUMN . " 
                FROM " . SOURCE_TABLE . " tm
                LEFT JOIN " . TARGET_TABLE . " r ON tm." . SOURCE_ASIN_COLUMN . " = r.asin AND r.date = CURDATE()
                WHERE r.asin IS NULL
                LIMIT 1000";
        $result = $this->conn->query($sql);
        
        if ($result === false) {
            throw new Exception("Database query error: " . $this->conn->error);
        }
        
        $asins = [];
        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $asins[] = $row[SOURCE_ASIN_COLUMN];
            }
        }
        return $asins;
    }

    public function updateRank($asin, $rank) {
        $sql = "INSERT INTO " . TARGET_TABLE . " (asin, date, rank) VALUES (?, CURDATE(), ?)";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Prepare failed: " . $this->conn->error);
        }
        $stmt->bind_param("si", $asin, $rank);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        $stmt->close();
    }
}