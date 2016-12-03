<?php
/*
Alexandria Tran Le
Jason Edward Springer
Maxim Tiourin
Gordon Zhang
*/

include '../public_html/includes/include.php';

set_time_limit(0);

$db = new Database();
$db->connectDefault();
$db->prepareDefault();

$sleepdur = 5;
$e = PHP_EOL;

echo 'VideoProcess_ExtractStillImages has started...'.$e;

while (true) {
    //Look for Queued Videos to Process
    $result = $db->execute("select_videos_status", array("queued"));
    $resultrows = $db->countResultRows($result);
    if ($resultrows >= 1) {
        echo 'Found ' . $resultrows . ' videos to process...'.$e;
        while ($row = $db->fetchArray($result)) {
            echo 'Processing next video...'.$e;
            
            $videoid = $row['id'];
            $userid = $row['userid'];
            $fps = $row['frame_rate'];
            $width = $row['frame_width'];
            $height = $row['frame_height'];
            $framecount = $row['frame_count'];
            $title = $row['title'];
            $dir = $row['directory'];
            $tempfile = $row['tempfile'];
            
            echo 'Processing video #' . $videoid . ' : "' . $title . '" for user #' . $userid . '. Frames = ' . $framecount . '.'.$e;
            
            //Extract Images
            VideoHandling::extractStillImages($dir . $tempfile, $videoid, $fps, $dir);
            
            //Update video row with new status
            $db->execute("update_videos_status_id", array("extracted", $videoid));
            
            echo 'Video processed...'.$e;
        }
        
        $db->freeResult($result);
    }
    else {
        echo 'No more videos to process, idling...'.$e;
    }
    
    sleep($sleepdur);
}

?>

