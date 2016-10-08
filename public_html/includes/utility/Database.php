<?php
/*
Alexandria Tran Le
Jason Edward Springer
Maxim Tiourin
Gordon Zhang
*/

/**
 * Base class for creating a Database object that handles connections
 */
class Database {
    private $connection = null;
    
    public function connect($connectionString) {
        if ($this->connection != null) {
            $this->close();
        }
        
        $this->connection = pg_connect($connectionString);
        if (!$this->connection) {
            die('Could not connect: ' . pg_last_error());
        }
        
        return $this->connection;
    }
    
    public function connectDefault() {
        return $this->connect("user=maxim password=changeme dbname=maximtest");
    }
    
    public function query($query) {
        $result = pg_query($query);
        if (!$result) {
            die('Query failed: ' . pg_last_error());
        }
        return $result;
    }
    
    public function fetchArray($result) {
        return pg_fetch_array($result);
    }
    
    public function freeResult($result) {
        pg_free_result($result);
    }
    
    /*
     * Counts the amount of rows returned in the result
     */
    public function countResultRows($result) {
        return pg_num_rows($result);
    }
    
    public function isConnected() {
        return $this->connection != null;
    }
    
    public function connection() {
        return $this->connection;
    }
    
    public function close() {
        pg_close($this->connection);
        $this->connection = null;
    }
}
