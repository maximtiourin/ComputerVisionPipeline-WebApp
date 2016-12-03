<?php
/*
Alexandria Tran Le
Jason Edward Springer
Maxim Tiourin
Gordon Zhang
*/

class Session {
    public static function generateSessionId($userid) {
        return hash("sha256", "".$userid.self::getIPAddress().time());
    }
    
    public static function generateExpirationTime($timeInSeconds = 3600) {
        return time() + $timeInSeconds;
    }
    
    public static function getIPAddress() {
        return $_SERVER['REMOTE_ADDR'];
    }
    
    /*
     * Utility function that creates a new url containing the given session id
     * lhs - the left hand side of the url, before the session or other variables are added
     * sid - the session id to insert
     * vars - the variables to append to the end, if any
     * return (string)  of structure {$lhs}?sid={$sid}[&{$vars}]
     */
    public static function buildSessionUrl($lhs, $sid, $vars = "") {
        if (strlen($vars) > 0) {
            return $lhs.'?sid='.$sid.'&'.$vars;
        }
        else {
            return $lhs.'?sid='.$sid;
        }
    }
    
    /*
    * Starts a new session, updating the sessions table, returning the session id
    */
    public static function startNewSession($userid, $dbconnection) {
        $hash = self::generateSessionId($userid);
        $expiration = self::generateExpirationTime();

        $dbconnection->execute("insert_sessions_userid-sessionid-expiration-ipaddress",
                array($userid, $hash, $expiration, self::getIPAddress()));

        return $hash;
    }
    
    /*
    * Purges any sessions that are expired for the userid from the database
    */
    function purgeExpiredSessions($userid, $dbconnection) {
        $dbconnection->execute("delete_sessions_userid-expiration*LTEQ", array($userid, time()));
    }
}
