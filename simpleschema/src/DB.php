<?php

namespace SimpleSchema;

use \PDO;

class DB {
    static $connection;
    static $masterConnection;
    // connection without database selected ;-)
    public static function getPdoMasterConnection() {
        if (self::$masterConnection) {
            return self::$masterConnection;
        }

        $_ENV = empty($_ENV) ? $_SERVER : $_ENV;
        $host = $_ENV['DB_HOST'];
        $user = $_ENV['DB_USERNAME'];
        $pass = $_ENV['DB_PASSWORD'];

        $charset = 'utf8mb4';

        $dsn = "mysql:host=$host;charset=$charset";
            
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new \PDO($dsn, $user, $pass, $options);
        } catch (\PDOException $e) {
            throw new \PDOException($e->getMessage(), (int)$e->getCode());
        }

        self::$masterConnection = $pdo;

        return $pdo;
    }

    public static function getPdoConnection() {
        if (self::$connection) {
            return self::$connection;
        }

        $_ENV = empty($_ENV) ? $_SERVER : $_ENV;
        $host = $_ENV['DB_HOST'];
        $port = $_ENV['DB_PORT'] ?? 3306;
        $db   = $_ENV['DB_DATABASE'];
        $user = $_ENV['DB_USERNAME'];
        $pass = $_ENV['DB_PASSWORD'];
        $charset = 'utf8mb4';

        $dsn = "mysql:host=$host:$port;dbname=$db;charset=$charset";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new \PDO($dsn, $user, $pass, $options);
        } catch (\PDOException $e) {
            throw new \PDOException($e->getMessage(), (int)$e->getCode());
        }

        self::$connection = $pdo;

        return $pdo;
    }

    static function table($table) {
        return new DBTableOperation($table, self::getPdoConnection());
    }


    // execute a query, fetch all results.
    static function fetchAll($query, $values = []) {
        $pdo = self::getPdoConnection();
        $stmt = $pdo->prepare($query);
        $stmt->execute($values);        

        $rows = [];
        while($row = $stmt->fetch()) {
            $rows[] = $row;
        }
        $stmt->closeCursor();
        return $rows;
    }


    // execute a query, fetch all results.
    static function fetchOne($query, $values = []) {
        $pdo = self::getPdoConnection();
        $stmt = $pdo->prepare($query);
        $stmt->execute($values);        

        $row = $stmt->fetch();
        $stmt->closeCursor();
        return $row;
    }
}

class DBTableOperation {
    function __construct($table, $connection) {
        $this->connection = $connection;
        $this->table = $table;
    }


    private function quoteField($field) {
        return join('.', array_map(function($field) { return str_replace('`', '', $field); }, explode('.', $field)));
    }

    function update($data) {
        $pdo = $this->connection;
        $table = $this->table;
    
        foreach ($data as $key=>$value) {
            $updateFields[] = $this->quoteField($key) . '=? ';
        }

        $baseQuery = "UPDATE " . $this->quoteField($table) . " ";
        $baseQuery .= "SET " . join(',', $updateFields) . " ";
        $baseQuery .= "WHERE id=? ";

        $values = array_merge(array_values($data), [$data['id']]);

        $stmt = $pdo->prepare($baseQuery);

        return ['status' => $stmt->execute($values)];
    }

    function insert($data) {
        $pdo = $this->connection;
        $table = $this->table;

        $quotedTable = $this->quoteField($table);

        $keys = array_keys($data);
        $values = array_values($data);

        $quotedFields = join(',', array_map([$this, 'quoteField'], $keys));
        $quotedValues = join(',', array_map(function(){return '?';}, $values));

        $baseQuery = "INSERT INTO $quotedTable ";
        $baseQuery .= " ($quotedFields)";
        $baseQuery .= " VALUES ($quotedValues)";

        $stmt = $pdo->prepare($baseQuery);
        $result = $stmt->execute($values);

        $lastInsertId = $pdo->lastInsertId();

        return $lastInsertId;
    }
    
    function delete($id) {
        $pdo = $this->connection;
        $table = $this->table;

        $quotedTable = $this->quotefield($table);

        $stmt = $pdo->prepare("DELETE FROM $quotedTable WHERE id = ?");

        return $stmt->execute([$id]);
    }
}
