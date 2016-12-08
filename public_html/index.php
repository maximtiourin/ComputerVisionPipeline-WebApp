<?php
/*
Alexandria Tran Le
Jason Edward Springer
Maxim Tiourin
Gordon Zhang
*/

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
 * ''
 * 
 * Allows empty string so that the error can be eaten further down the line
 */
function isStringValidLogin($str) {
    if (preg_match("/^[a-zA-Z0-9]*$/", $str)) {
        return true;
    }
    else {
        return false;
    }
}


/////////////////////////
// ~--~ ~--~ ~--~ ~--~ //
/////////////////////////

//Determine Login State
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
            
            $data['userid'] = $userid;
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
         * 5 = Username/Password can only contain: a-z, A-Z, 0-9
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

//Determine State after Login
if (filter_has_var(INPUT_GET, "upload")) {
    /*
     * Upload Error Reference
     * 1 = File size exceeds limit of 4GB
     * 2 = Invalid file type (Allowed types: avi, mp4, wmv)
     * 3 = Video data is invalid or corrupted.
     */
    
    $flag['displayUploadForm'] = true;
    $data['maxVideoByteSize'] = FileHandling::getBytesForGigabytes(4); //4GB
    $videodir = '../videos/';
    $tempdir = $videodir . 'temp/';
    $title = sanitize(filter_input(INPUT_POST, "title", FILTER_SANITIZE_STRING));
    
    //Create directories if they dont exist
    FileHandling::ensureDirectory($videodir);
    FileHandling::ensureDirectory($tempdir);
    
    //Check to see if video was uploaded
    if (filter_has_var(INPUT_POST, "submitUpload")) {
        //Upload has been submitted
        $flag['submitUpload'] = true;
        
        //Determine temp file data
        $tempid = FileHandling::generateTempFileIdentifier($data['userid'] . $data['sid']);
        $tempname = "temp";
        $extension = FileHandling::getFileExtension($_FILES['video']['name']);
        $validExtensions = array("avi", "mp4", "wmv");
        $size = $_FILES['video']['size'];
        $maxSize = $data['maxVideoByteSize'];
        $type = $_FILES['video']['type'];
        $validTypes = array("application/x-troff-msvideo", "video/avi", "video/msvideo", "video/x-msvideo", "video/avs-video",
                            "video/mp4", "video/mpeg", "video/x-mpeg",
                            "video/x-ms-wmv");
        
        //Get file metadata
        $metadata = VideoHandling::getVideoMetadata($_FILES['video']['tmp_name']);
        
        //Validate file, then upload to server
        $haveError = false;
        if (!FileHandling::isValidSize($size, $maxSize)) {
            $error['upload'] = 1;
            $haveError = true;
        }
        else if (!FileHandling::isValidExtension($extension, $validExtensions)
                || !FileHandling::isValidMimeType($type, $validTypes)) {
            $error['upload'] = 2;
            $haveError = true;
        }
        else if (!VideoHandling::verifyVideoIntegrity($metadata)) {
            $error['upload'] = 3;
            $haveError = true;
        }
        
        if (!$haveError) {
            //The filepath to temporarily store the video in, before it gets processed
            $userdir = $videodir . $data['userid'] . '/';
            FileHandling::ensureDirectory($userdir);
            $filedir = $userdir . $tempid . '/';
            FileHandling::ensureDirectory($filedir);
            $usertempdir = $filedir . 'temp/';
            FileHandling::ensureDirectory($usertempdir);
            $filename = $tempname . '.' . $extension;
            $file = $filedir . $filename;
            
            //Move file to directory with correct identifier
            $uploaded = move_uploaded_file($_FILES['video']['tmp_name'], $file);
            if ($uploaded) {            
                //Update permissions for files
                FileHandling::ensureDirectoryPermissionsRecursively($filedir);
                
                //Determine video metadata
                $fps = VideoHandling::getFrameRate($metadata);
                $framesize = VideoHandling::getFrameResolution($metadata);
                $framecount = VideoHandling::getFrameCount($metadata);
                
                //TEMP:: Extract still images from video and store them
                //VideoHandling::extractStillImages($file, "temp", $fps, $filedir);
                
                //Create a database entry for the video and its metadata, and flag for processing
                $db->execute("insert_videos_userid-frame_rate-frame_width-frame_height-frame_count-title-directory-tempfile",
                        array($data['userid'], $fps, $framesize['width'], $framesize['height'], $framecount, $title, $filedir, $filename));
                
                //Flag file uploaded confirmation
                $flag['videoUploaded'] = true;
            }
            else {
                //Possible Upload attack, Flag generic error
                $flag['videoNotUploaded'] = true;
            }
        }
    }
}
else if (filter_has_var(INPUT_GET, "view")) {
    $flag['displayVideos'] = true;
}
else if (filter_has_var(INPUT_GET, "play")) {
    $flag['playVideo'] = true;
    $data['playVideoId'] = sanitize(filter_input(INPUT_GET, "play", FILTER_SANITIZE_STRING));
}
else if (filter_has_var(INPUT_GET, "delete")) {
    $flag['deleteVideo'] = true;
    $deleteId = sanitize(filter_input(INPUT_GET, "delete", FILTER_SANITIZE_STRING));
    
    $result = $db->execute("select_videos_id-userid", array($deleteId, $data['userid']));
    $resultRows = $db->countResultRows($result);
    
    if ($resultRows > 0) {
        //Valid video to delete
        $row = $db->fetchArray($result);
        $dir = $row['directory'];
        
        $db->execute("delete_frames_videoid", array($deleteId));
        $db->execute("delete_videos_id", array($deleteId));
        FileHandling::deleteDirectoryAndContents($dir);
    }
    
    $db->freeResult($result);
    
    //Redirect to view videos
    redirect(Session::buildSessionUrl("index.php", $data["sid"], "view"));
}
?>

<html>
<head>
<title>CS160</title>
<link rel="stylesheet" type="text/css" href="styles/main.css">
</head>
<body>
<center>
    <header>CS 160: Computer Vision Pipeline Project</header>
    <br><br>
</center>
    <?php
    if ($flag['loggedIn']) {
        echo '
        <div class="center">
        <center>
        ';
        echo '
            Control Panel for User '.$data['username'].'
            <br><br>
        ';
        
        if ($flag['videoUploaded']) {
            echo '
                <a class="success">Video successfully uploaded.</a>
                <br><br>
            ';
        }
        else if ($flag['videoNotUploaded']) {
            echo '
                <a class="error">Video could not be uploaded, please try again.</a>
                <br><br>
            ';
        }
        
        if ($error['upload'] == 1) {
            echo '
                <a class="error">Video file size exceeds allowed limit of 4GB.</a>
                <br><br>
            ';
        }
        else if ($error['upload'] == 2) {
            echo '
                <a class="error">Video type is not supported. Supported types: avi, mp4, wmv</a>
                <br><br>
            ';
        }
        else if ($error['upload'] == 3) {
            echo '
                <a class="error">Video data is corrupted or invalid, please try again.</a>
                <br><br>
            ';
        }
        
        /*
         * CONTROL PANEL BUTTONS
         */
        if ($flag['displayUploadForm']) {
            echo '
            <button name="uploadVideo" class="bigbuttontab" type="button" value="btn" onclick="window.location.href=\''
            .Session::buildSessionUrl("index.php", $data["sid"], "upload").'\'"><span>Upload New Video</span></button>
            <button name="playVideos" class="bigbutton" type="button" value="btn" onclick="window.location.href=\''
                .Session::buildSessionUrl("index.php", $data["sid"], "view").'\'"><span>Play Existing Videos</span></button>
            <br>
            <button name="logout" class="smallbutton" type="button" value="btn" onclick="window.location.href=\''
                .Session::buildSessionUrl("index.php", $data["sid"], "logout").'\'"><span>Logout</span></button>
            <button name="logoutAll" class="smallbutton" type="button" value="btn" onclick="window.location.href=\''
                .Session::buildSessionUrl("index.php", $data["sid"], "logoutAll").'\'"><span>Logout All Sessions</span></button>
            ';
        }
        else if ($flag['displayVideos']) {
            echo '
            <button name="uploadVideo" class="bigbutton" type="button" value="btn" onclick="window.location.href=\''
            .Session::buildSessionUrl("index.php", $data["sid"], "upload").'\'"><span>Upload New Video</span></button>
            <button name="playVideos" class="bigbuttontab" type="button" value="btn" onclick="window.location.href=\''
                .Session::buildSessionUrl("index.php", $data["sid"], "view").'\'"><span>Play Existing Videos</span></button>
            <br>
            <button name="logout" class="smallbutton" type="button" value="btn" onclick="window.location.href=\''
                .Session::buildSessionUrl("index.php", $data["sid"], "logout").'\'"><span>Logout</span></button>
            <button name="logoutAll" class="smallbutton" type="button" value="btn" onclick="window.location.href=\''
                .Session::buildSessionUrl("index.php", $data["sid"], "logoutAll").'\'"><span>Logout All Sessions</span></button>
            ';
        }
        else {
            echo '
            <button name="uploadVideo" class="bigbutton" type="button" value="btn" onclick="window.location.href=\''
            .Session::buildSessionUrl("index.php", $data["sid"], "upload").'\'"><span>Upload New Video</span></button>
            <button name="playVideos" class="bigbutton" type="button" value="btn" onclick="window.location.href=\''
                .Session::buildSessionUrl("index.php", $data["sid"], "view").'\'"><span>Play Existing Videos</span></button>
            <br>
            <button name="logout" class="smallbutton" type="button" value="btn" onclick="window.location.href=\''
                .Session::buildSessionUrl("index.php", $data["sid"], "logout").'\'"><span>Logout</span></button>
            <button name="logoutAll" class="smallbutton" type="button" value="btn" onclick="window.location.href=\''
                .Session::buildSessionUrl("index.php", $data["sid"], "logoutAll").'\'"><span>Logout All Sessions</span></button>
            ';
        }
        
        /*
         * INDIVIDUAL PAGES
         */
        if ($flag['displayUploadForm']) {
            echo '
                <br><br>
                <div class="submitform">
                <form action="" method="post" enctype="multipart/form-data">
                    <label>Title:<br><br><input name="title" type="text" size="64" maxlength="64"/></label>
                    <br><br>
                    <input type="hidden" name="MAX_FILE_SIZE" value="'.$data['maxVideoByteSize'].'"/>
                    <input name="video" type="file" />
                    <br><br>
                    <button name="submitUpload" class="smallbutton" type="submit" value="Submit"><span>Upload</span></button>
                </form>
                </div>
                <br><br>
            ';
        }
        else if ($flag['displayVideos']) {
            echo '
                <br><br>
                <div class="submitform">
                <div class="videothumbnailContainer">
            ';
                //SHOW VIDEOS HERE
            
            $result = $db->execute("select_videos_userid_orderby=id*DESC", array($data['userid']));
            $resultRows = $db->countResultRows($result);
            
            if ($resultRows > 0) {
                $videosPerRow = 5;
                $videoWidth = 160;
                $videoHeight = 100;
                
                //Display videos
                while ($row = $db->fetchArray($result)) {
                    $videoid = $row['id'];
                    $userid = $row['userid'];
                    $fps = $row['frame_rate'];
                    $width = $row['frame_width'];
                    $height = $row['frame_height'];
                    $framecount = $row['frame_count'];
                    $status = $row['status'];
                    $title = $row['title'];
                    $dir = $row['directory'];
                    $tempfile = $row['tempfile'];
                    
                    echo '
                        <div class="videothumbnail">
                    ';
                    
                    if (strcmp($status, "finalized") == 0) {
                        echo '
                        <a href="'.Session::buildSessionUrl("index.php", $data["sid"], "play=".$videoid).'">
                        <img src="image.php?href='.$dir.'temp/'.$videoid.'.1.png" width="'.$videoWidth.'" height="'.$videoHeight.'"/>
                        </a>
                        ';
                    }
                    else {
                        echo '
                        <img src="/images/processing.gif" width="'.$videoWidth.'" height="'.$videoHeight.'"/>
                        ';
                    }
                    
                    echo '
                        <br><br>
                        '.htmlspecialchars_decode($title).'
                    ';
                    
                    echo '
                        </div>
                    ';
                }
            }
            else {
                //No videos for user!
                echo 'No videos uploaded yet!';
            }
            
            $db->freeResult($result);
            
            echo '
                </div>
                </div>
                <br><br>
            ';
        }
        else if ($flag['playVideo']) {
            echo '
                <br><br>
                <div class="playvideo">
            ';
            
            $result = $db->execute("select_videos_id-userid", array($data['playVideoId'], $data['userid']));
            $resultRows = $db->countResultRows($result);
            
            if ($resultRows > 0) {
                $row = $db->fetchArray($result);
                
                $videoid = $row['id'];
                $userid = $row['userid'];
                $fps = $row['frame_rate'];
                $width = $row['frame_width'];
                $height = $row['frame_height'];
                $framecount = $row['frame_count'];
                $status = $row['status'];
                $title = $row['title'];
                $dir = $row['directory'];
                $tempfile = $row['tempfile'];
                
                echo '
                 <a class="videotitle">'.htmlspecialchars_decode($title).'</a>
                 <br><br>
                 <video controls>
                    <source src="video.php?href='.$dir.'output.mp4" type="video/mp4">
                 </video>
                 <br><br><br><br>
                 <button name="deleteVideo" class="smallbutton" type="button" value="btn" onclick="window.location.href=\''
                .Session::buildSessionUrl("index.php", $data["sid"], "delete=".$videoid).'\'"><span>Delete Video</span></button>
                ';
            }
            else {
                //Video id invalid for user
                echo '
                    This video does not exist!
                ';
            }
            
            $db->freeResult($result);
            
            echo '
                </div>
                <br><br>
            ';
        }
        else {
            
        }
        
        echo '
        </center>
        </div>
        ';
    }
    else if ($flag['displayLogin']) {
        echo '
            <center><img src="/images/youtube-style-play-button-hi.png" width="510" height="358"/></center>
            <br><br>
            <form class="credentials" action="index.php?login" method="post">
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
                <button name="submitLogin" class="bigbutton" type="submit" value="Submit"><span>Login</span></button>
                <br>
                <button name="logout" class="smallbutton" type="button" value="btn" onclick="window.location.href=\'index.php?register\'"><span>Register New Account</span></button>
            </form>
            <footer>Maxim Tiourin, Alexandria Le, Jason Springer, and Gordon Zhang</footer>
        ';
    }
    else if ($flag['displayRegister']) {
        echo '
            <form class="credentials" action="index.php?register" method="post">
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
                <a class="error">Username/Password can only contain: a-z, A-Z, 0-9</a>
                <br><br>
            ';
        }
        echo '
                <div class="submitform">
                <br><br>
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
                </div>
                <br><br>
                <button name="submitRegister" class="bigbutton" type="submit" value="Submit"><span>Register</span></button>
                <br>
                <button name="logout" class="smallbutton" type="button" value="btn" onclick="window.location.href=\'index.php?login\'"><span>Back to Login</span></button>
            </form>
            <footer>Maxim Tiourin, Alexandria Le, Jason Springer, and Gordon Zhang</footer>
        ';
    }
    ?>
</body>
</html>

<?php
//Close database connection
$db->close();
?>
