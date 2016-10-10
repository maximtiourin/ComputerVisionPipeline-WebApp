<?php
include 'includes/include.php';

//Connect to postgres db
$db = new Database();
$db->connectDefault();
$db->prepareDefault();

/////////////////////////
//Define utility functions
/////////////////////////
/*
 * Redirects the browser to the given url and stops execution of the current php script
 */
function redirect($url) {
    header('Location: '.$url);
    die();
}

/*
 * Sanitizes the input string
 */
function sanitize($string) {
    $data = htmlspecialchars(stripslashes(strip_tags(trim($string))));
    return $data;
}

/*
 * Repopulates the form element with the given name, searching POST for the value
 */
function repopulatePost($name) {
    if (filter_has_var(INPUT_POST, $name)) {
        return htmlspecialchars_decode(sanitize(filter_input(INPUT_POST, $name, FILTER_SANITIZE_STRING)));
    }
    else {
        return '';
    }
}

/*
 * Checks to see if the string contains only characters that are allowed
 * for a login input string.
 * Allowed characters:
 * 'a-z'
 * 'A-Z'
 * '0-9'
 * '_'     (only one in a row, and only when between other valid characters)
 */
function isStringValidLogin($str) {
    if (preg_match("^[a-zA-Z0-9]+(_[a-zA-Z0-9]+)*$", $str) == 1) {
        return true;
    }
    else {
        return false;
    }
}


/////////////////////////
// ~--~ ~--~ ~--~ ~--~ //
/////////////////////////

//Determine State
if (filter_has_var(INPUT_GET, "sid")) {
    //Session id is provided
    $flag['sid'] = true;
    
    //Validate session
    $sid = sanitize(filter_input(INPUT_GET, "sid", FILTER_SANITIZE_ENCODED));
    
    $result = $db->execute("select_sessions_sessionid", array($sid));
    if ($db->countResultRows($result) == 1) {
        while ($row = $db->fetchArray($result)) {
            $userid = $row['userid'];
            $expiration = $row['expiration'];
            $ip = $row['ipaddress'];
            
            if ($expiration <= time()) {
                //Session has expired, invalidate
                $invalid = true;
            }
            else if (strcmp($ip, Session::getIPAddress()) !== 0) {
                //Different ip logging into existing session id, prevent login
                $invalid = true;
            }
            else {
                //Session has been refreshed, set new expiration
                $result2 = $db->execute("update_sessions_expiration_userid-sessionid", 
                        array(Session::generateExpirationTime(), $userid, $sid));
            }
        }
        
        $db->freeResult($result);
    }
    else {
        $invalid = true;
    }
    
    if (!$invalid) {
        //Grab user information
        $result = $db->execute("select_users_id", array($userid));
        
        if ($db->countResultRows($result) == 1) {
            $row = $db->fetchArray($result);
            
            $data['sid'] = $sid;
            $data['username'] = $row['username'];
            $data['firstname'] = htmlspecialchars_decode($row['firstname']);
            $data['lastname'] = htmlspecialchars_decode($row['lastname']);

            //Check if should logout one or all
            if (filter_has_var(INPUT_GET, "logout")) {
                //Logout session
                $db->execute("delete_sessions_userid-sessionid", array($userid, $sid));
                redirect('index.php?login');
            }
            else if (filter_has_var(INPUT_GET, "logoutAll")) {
                //Logout all sessions
                $db->execute("delete_sessions_userid", array($userid));
                redirect('index.php?login');
            }
            
            //Set logged in
            $flag['loggedIn'] = true;
        }
    }
}
else if (filter_has_var(INPUT_GET, "login")) {
    //Login should be displayed
    $flag['displayLogin'] = true;
    
    //Check to see if recently registered
    if (filter_has_var(INPUT_GET, "registered")) {
        $flag['recentlyRegistered'] = true;
    }
    
    //Check to see if form was already submitted
    if (filter_has_var(INPUT_POST, "submitLogin")) {
        /*
         * Login Error Reference
         * 1 = Username/Password was incorrect.
         */
        
        //Login has been submitted
        $flag['submitLogin'] = true;
        
        //Validate Form
        $username = sanitize(filter_input(INPUT_POST, "username", FILTER_SANITIZE_STRING));
        $password = sanitize(filter_input(INPUT_POST, "password", FILTER_SANITIZE_STRING));
        
        $result = $db->execute("select_users_username", array($username));
        
        $haveError = false;
        if ($db->countResultRows($result) !== 1) {
            $error['login'] = 1;
            $haveError = true;
        }
        else {
            $row = $db->fetchArray($result);
            $hash = $row['password'];
            $userid = $row['id'];
            
            if (!password_verify($password, $hash)) {
                $error['login'] = 1;
                $haveError = true;
            }
        }
        
        if (!$haveError) {
            //Log in user
            //Purge Expired Sessions for user
            Session::purgeExpiredSessions($userid, $db);
            
            //Start new session id
            $sid = Session::startNewSession($userid, $db);
            
            //Redirect using new sid
            redirect('index.php?sid='.$sid);
        }
    }
}
else if (filter_has_var(INPUT_GET, "register")) {
    //Registration should be displayed
    $flag['displayRegister'] = true;
    
    //Check to see if form was already submitted
    if (filter_has_var(INPUT_POST, "submitRegister")) {
        /*
         * Registration Error Reference
         * 1 = Please enter a username
         * 2 = Please enter a password
         * 3 = Passwords do not match
         * 4 = Username is already taken
         * 5 = Username/Password can only contain: a-z, A-Z, 0-9, and/or _ as a separator
         */

        //Register has been submitted
        $flag['submitRegister'] = true;

        //Validate Form
        $username = sanitize(filter_input(INPUT_POST, "username", FILTER_SANITIZE_STRING));
        $password = sanitize(filter_input(INPUT_POST, "password", FILTER_SANITIZE_STRING));
        $retypePassword = sanitize(filter_input(INPUT_POST, "retypePassword", FILTER_SANITIZE_STRING));
        $firstname = sanitize(filter_input(INPUT_POST, "firstname", FILTER_SANITIZE_STRING));
        $lastname = sanitize(filter_input(INPUT_POST, "lastname", FILTER_SANITIZE_STRING));

        $result = $db->execute("select_users_username", array($username));

        $haveError = false;
        if (!isStringValidLogin($username) || !isStringValidLogin($password)) {
            //Error 5
            $error['register'] = 5;
            $haveError = true;
        }
        else if (strlen($username) <= 0) {
            //Error 1
            $error['register'] = 1;
            $haveError = true;
        }
        else if (strlen($password) <= 0) {
            //Error 2
            $error['register'] = 2;
            $haveError = true;
        }
        else if (strcmp($password, $retypePassword) !== 0) {
            //Error 3
            $error['register'] = 3;
            $haveError = true;
        }
        else if ($db->countResultRows($result) > 0) {
            //Error 4
            $error['register'] = 4;
            $haveError = true;
        }

        if (!$haveError) {
            //Register user
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $db->execute("insert_users_username-password-firstname-lastname", 
                    array($username, $hash, $firstname, $lastname));

            //Redirect to Login
            redirect('index.php?login&registered');
        }
    }
}
else {
    //Invalid destination
    $invalid = true;
}

if ($invalid) {
    //Invalidated Session
    redirect('index.php?login'); //Send to default index to login
}
?>

<html>
<head>
<title>CS160</title>
<link rel="stylesheet" type="text/css" href="styles/login.css">
</head>
<body>
    <?php
    if ($flag['loggedIn']) {
        echo '
            Control Panel for User '.$data['username'].'
            <br><br>
            <a href="'.Session::buildSessionUrl("index.php", $data["sid"], "upload").'">Upload New Video</a>
            <br><br>
            <a href="'.Session::buildSessionUrl("index.php", $data["sid"], "view").'">Play Existing Videos</a>
                <br><br>
            <a href="'.Session::buildSessionUrl("index.php", $data["sid"], "logout").'">Logout</a>
            <br><br>
            <a href="'.Session::buildSessionUrl("index.php", $data["sid"], "logoutAll").'">Logout All Sessions</a>
        ';
    }
    else if ($flag['displayLogin']) {
        echo '
            <form action="index.php?login" method="post">
        ';
        if ($flag['recentlyRegistered']) {
            echo '
                <a class="success">Account registered successfully, please log in.</a>
                <br><br>
            ';
        }
        if ($error['login'] == 1) {
            echo '
                <a class="error">Invalid username/password combination.</a>
                <br><br>
            ';
        }
        echo '
                <label>Username: <input type="text" name="username" maxlength="128"></label>
                <br><br>
                <label>Password: <input type="password" name="password" maxlength="256"></label>
                <br><br>
                <button name="submitLogin" type="submit" value="Submit">Login</button>
                <br><br>
                <a href="index.php?register">Register new account.</a>
            </form>
        ';
    }
    else if ($flag['displayRegister']) {
        echo '
            <form action="index.php?register" method="post">
        ';
        if ($error['register'] == 1) {
            echo '
                <a class="error">Please enter a username.</a>
                <br><br>
            ';
        }
        else if ($error['register'] == 2) {
            echo '
                <a class="error">Please enter a Password.</a>
                <br><br>
            ';
        }
        else if ($error['register'] == 3) {
            echo '
                <a class="error">Passwords do not match.</a>
                <br><br>
            ';
        }
        else if ($error['register'] == 4) {
            echo '
                <a class="error">Username is already taken.</a>
                <br><br>
            ';
        }
        else if ($error['register'] == 5) {
            echo '
                <a class="error">Username/Password can only contain: a-z, A-Z, 0-9, and/or _ as a separator.</a>
                <br><br>
            ';
        }
        echo '
                <label>Username*: <input type="text" name="username" maxlength="128" value="'.repopulatePost("username").'"></label>
                <br><br>
                <label>Password*: <input type="password" name="password" maxlength="256"></label>
                <br><br>
                <label>Retype Password*: <input type="password" name="retypePassword" maxlength="256"></label>
                <br><br>
                <label>First Name: <input type="text" name="firstname" maxlength="256" value="'.repopulatePost("firstname").'"></label>
                <br><br>
                <label>Last Name: <input type="text" name="lastname" maxlength="256" value="'.repopulatePost("lastname").'"></label>
                <br><br>
                <button name="submitRegister" type="submit" value="Submit">Register</button>
                <br><br>
                <a href="index.php?login">Back to Login.</a>
            </form>
        ';
    }
    ?>
</body>
</html>

<?php
//Close database connection
$db->close();
?>
