<?php

namespace MyProject;

class Database
{
    private $conn;

    public function __construct()
    {
        $this->conn = new \mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($this->conn->connect_error) {
            throw new \Exception("Connection failed: " . $this->conn->connect_error);
        }
        $this->conn->set_charset("utf8");
    }

    public function checkSourceTable(): void
    {
        $sql = "SHOW TABLES LIKE '" . SOURCE_TABLE . "'";
        $result = $this->conn->query($sql);

        if (!$result instanceof \mysqli_result) {
            throw new \Exception("Database query error: " . $this->conn->error);
        }

        if ($result->num_rows == 0) {
            throw new \Exception("Source table " . SOURCE_TABLE . " does not exist.");
        }

        $sql = "SHOW COLUMNS FROM " . SOURCE_TABLE . " LIKE '" . SOURCE_ASIN_COLUMN . "'";
        $result = $this->conn->query($sql);

        if (!$result instanceof \mysqli_result) {
            throw new \Exception("Database query error: " . $this->conn->error);
        }

        if ($result->num_rows == 0) {
            throw new \Exception("Source column " . SOURCE_ASIN_COLUMN . " does not exist in table " . SOURCE_TABLE);
        }
    }

    public function checkTargetTable(): bool
    {
        $sql = "SHOW TABLES LIKE '" . TARGET_TABLE . "'";
        $result = $this->conn->query($sql);
        if (!$result instanceof \mysqli_result) {
            throw new \Exception("Database query error: " . $this->conn->error);
        }
        return $result->num_rows > 0;
    }

    public function getCreateTableStatement(): string
    {
        return "CREATE TABLE " . TARGET_TABLE . " (
            id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            asin VARCHAR(10) NOT NULL,
            title VARCHAR(255) NOT NULL,  -- New column for product title
            date DATE NOT NULL,
            rank INT(11) UNSIGNED NOT NULL,
            UNIQUE KEY unique_asin_date (asin, date)
        )";
    }

    public function createTargetTable(): void
    {
        $sql = $this->getCreateTableStatement();
        if (!$this->conn->query($sql)) {
            throw new \Exception("Failed to create target table: " . $this->conn->error);
        }
    }

    /**
     * @return string[] List of ASINs to update
     * @throws \Exception
     */
    public function getASINsToUpdate(): array
    {
        $sql = "SELECT DISTINCT tm." . SOURCE_ASIN_COLUMN . " 
                FROM " . SOURCE_TABLE . " tm
                LEFT JOIN " . TARGET_TABLE . " r ON tm." . SOURCE_ASIN_COLUMN . " = r.asin AND r.date = CURDATE()
                WHERE r.asin IS NULL";
        $result = $this->conn->query($sql);

        if (!$result instanceof \mysqli_result) {
            throw new \Exception("Database query error: " . $this->conn->error);
        }

        $asins = [];
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $asins[] = (string)$row[SOURCE_ASIN_COLUMN];
            }
        }
        return $asins;
    }

    public function updateRank(string $asin, string $title, ?int $rank): void
    {
        $sql = "INSERT INTO " . TARGET_TABLE . " (asin, title, date, rank) VALUES (?, ?, CURDATE(), ?)";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            throw new \Exception("Prepare failed: " . $this->conn->error);
        }
        $stmt->bind_param("ssi", $asin, $title, $rank);
        if (!$stmt->execute()) {
            throw new \Exception("Execute failed: " . $stmt->error);
        }
        $stmt->close();
    }
}
