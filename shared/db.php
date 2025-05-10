<?php
/**
 * Database Connection File
 * 
 * Provides centralized database access for all components.
 * Single PDO connection instance shared across all files.
 */

// Database configuration
$db_host = 'localhost';
$db_name = 'backzvsg_crm';
$db_user = 'backzvsg_crm_access'; // Replace with actual database user
$db_pass = 'zwciD6$t;W0&'; // Replace with actual database password
$db_charset = 'utf8mb4';

// Connection options
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

// Establish PDO connection
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=$db_charset", $db_user, $db_pass, $options);
    
    // Helper functions for common database operations
    
    /**
     * Execute a query with parameters and return the result
     * 
     * @param string $query SQL query with placeholders
     * @param array $params Parameters to bind to the query
     * @return array|false Result set or false on failure
     */
    function db_query($query, $params = []) {
        global $pdo;
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Execute a query and return a single row
     * 
     * @param string $query SQL query with placeholders
     * @param array $params Parameters to bind to the query
     * @return array|false Single row or false if no results
     */
    function db_query_row($query, $params = []) {
        global $pdo;
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetch();
    }
    
    /**
     * Execute a query and return a single value
     * 
     * @param string $query SQL query with placeholders
     * @param array $params Parameters to bind to the query
     * @return mixed Single value or false if no results
     */
    function db_query_value($query, $params = []) {
        global $pdo;
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
    
    /**
     * Insert data into a table
     * 
     * @param string $table Table name
     * @param array $data Associative array of column => value
     * @return int|false Last insert ID or false on failure
     */
    function db_insert($table, $data) {
        global $pdo;
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $stmt = $pdo->prepare("INSERT INTO $table ($columns) VALUES ($placeholders)");
        $result = $stmt->execute(array_values($data));
        return $result ? $pdo->lastInsertId() : false;
    }
    
    /**
     * Update data in a table
     * 
     * @param string $table Table name
     * @param array $data Associative array of column => value
     * @param string $where WHERE clause
     * @param array $params Parameters for WHERE clause
     * @return int|false Number of affected rows or false on failure
     */
    function db_update($table, $data, $where, $params = []) {
        global $pdo;
        $set = [];
        foreach (array_keys($data) as $column) {
            $set[] = "$column = ?";
        }
        $set_clause = implode(', ', $set);
        $stmt = $pdo->prepare("UPDATE $table SET $set_clause WHERE $where");
        $result = $stmt->execute(array_merge(array_values($data), $params));
        return $result ? $stmt->rowCount() : false;
    }
    
    /**
     * Delete from a table
     * 
     * @param string $table Table name
     * @param string $where WHERE clause
     * @param array $params Parameters for WHERE clause
     * @return int|false Number of affected rows or false on failure
     */
    function db_delete($table, $where, $params = []) {
        global $pdo;
        $stmt = $pdo->prepare("DELETE FROM $table WHERE $where");
        $result = $stmt->execute($params);
        return $result ? $stmt->rowCount() : false;
    }
    
} catch (PDOException $e) {
    // Log error and display user-friendly message
    error_log("Database Connection Error: " . $e->getMessage());
    die("Database connection failed. Please contact the administrator.");
}
