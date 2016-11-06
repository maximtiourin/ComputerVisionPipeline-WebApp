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
    
    /*
     * Prepares a query statement with the given name,
     * for the current connection, if there is one.
     * All prepared statements on this connection can then be executed using
     * execute($name, $vararray) without having to worry about escaping parameters
     */
    public function prepare($name, $query) {
        if ($this->connection != null) {
            $e = pg_prepare($this->connection, $name, $query);
            if (!$e) {
                die("prepare error (".$name.")");
            }
            return $e;
        }
    }
    
    /*
     * Prepares all default queries that are used throughout the app
     */
    public function prepareDefault() {
        if ($this->connection != null) {
            $this->prepare("delete_sessions_userid",
                    'DELETE FROM sessions WHERE userid = $1');
            $this->prepare("delete_sessions_userid-sessionid",
                    'DELETE FROM sessions WHERE userid = $1 AND sessionid = $2');
            $this->prepare("delete_sessions_userid-expiration*LTEQ",
                    'DELETE FROM sessions WHERE userid = $1 AND expiration <= $2');

            $this->prepare("insert_sessions_userid-sessionid-expiration-ipaddress",
                    'INSERT INTO sessions VALUES ($1, $2, $3, $4)');
            $this->prepare("insert_users_username-password-firstname-lastname",
                    'INSERT INTO users (username, password, firstname, lastname) VALUES ($1, $2, $3, $4)');
            $this->prepare("insert_videos_userid-frame_rate-frame_width-frame_height-frame_count-title-directory-tempfile",
                    'INSERT INTO videos (userid, frame_rate, frame_width, frame_height, frame_count, title, directory, tempfile) VALUES ($1, $2, $3, $4, $5, $6, $7, $8)');
            $this->prepare("insert_frames_id-videoid", 'INSERT INTO frames (id, videoid) VALUES ($1, $2)');
            
            
            $this->prepare("select_sessions_sessionid", 
                    'SELECT * FROM sessions WHERE sessionid = $1');
            $this->prepare("select_users_id",
                    'SELECT * FROM users WHERE id = $1');
            $this->prepare("select_users_username",
                    'SELECT * FROM users WHERE username = $1');
            $this->prepare("select_videos_status",
                    'SELECT * FROM videos WHERE status = $1');
            $this->prepare("select_frames_videoid-status",
                    'SELECT * FROM frames WHERE videoid = $1 AND status = $2');

            $this->prepare("update_sessions_expiration_userid-sessionid", 
                    'UPDATE sessions SET expiration = $1 WHERE userid = $2 AND sessionid = $3');
            $this->prepare("update_videos_status_id", 
                    'UPDATE videos SET status = $1 WHERE id = $2');
            $this->prepare("update_frames_status_id-videoid",
                    'UPDATE frames SET status = $1 WHERE id = $2 AND videoid = $3');
        }
    }
    
    /*
     * Executes the prepared query with the given variable array
     */
    public function execute($name, $vararray) {
        if ($this->connection != null) {
            $result = pg_execute($this->connection, $name, $vararray);
            if (!$result) {
                die("execute error (".$name.")");
            }
            return $result;
        }
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
