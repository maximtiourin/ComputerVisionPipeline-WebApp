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
$minisleepdur = 5;
$softlockdur = 30; //Videos are soft locked for 30 seconds to prevent multiple processes from attempting to read them
$e = PHP_EOL;

function combineFramesIntoVideo($fps, $videoid, $framedir, $outdir, $outname) {
    shell_exec('ffmpeg -r ' . $fps . ' -start_number 1 -f image2 -i ' . $framedir . $videoid . '.%d.png -c:v libx264 ' . $outdir . $outname . '.mp4');
}

function haveFinalizedVideo($videodir, $videoname) {
    if (file_exists($videodir . $videoname . '.mp4')) {
        return true;
    }
    else {
        return false;
    }
}

echo 'VideoProcess_Finalization has started...'.$e
        .'------------------------------------------'.$e;

while (true) {
    //Look for 'finalizing' Videos
    $result = $db->execute("select_videos_status", array("finalizing"));
    $resultRows = $db->countResultRows($result);
    
    $branch = 0;
    if ($resultRows > 0) {
        $branch = 1;
        
        echo 'Searching through finalizing videos...'.$e
                .'------------------------------------------'.$e;
        
        $result2 = $db->execute("select_videos_status-lastread*LTEQ_orderby=lastread-id*ASC", array("finalizing", time() - $softlockdur));
        $resultRows2 = $db->countResultRows($result2);
        
        if ($resultRows2 > 0) {
            //Process video
            $row = $db->fetchArray($result2);
               
            $videoid = $row['id'];
            $userid = $row['userid'];
            $fps = $row['frame_rate'];
            $width = $row['frame_width'];
            $height = $row['frame_height'];
            $framecount = $row['frame_count'];
            $title = $row['title'];
            $dir = $row['directory'];
            $tempfile = $row['tempfile'];
            
            //Soft lock video
            $db->execute("update_videos_lastread_id", array(time(), $videoid));
            
            //Frame combination TODO
            //COMBINE()
            $videoname = "output";
            combineFramesIntoVideo($fps, $videoid, $dir . 'temp/', $dir, $videoname);
            
            $error = false;
            if (haveFinalizedVideo($dir, $videoname)) {
                //Finalize
                $db->execute("update_videos_status_id", array("finalized", $videoid));
                        
                echo '------------------------------------------'.$e
                        .'Video #' . $videoid . "processed (" . $resultRows . " videos remaining)...".$e
                        .'------------------------------------------'.$e;
            }
            else {
                $error = true;
            }
            
            if ($error) {
                echo 'ERROR: Unable to finalize video #' . $videoid . '...'.$e;
            }
            
            $db->freeResult($result2);
        }
        else {
            //No unlocked videos to process
            echo 'No unlocked finalizing videos to currently process, idling...'.$e;
            sleep($minisleepdur);
        }
        
        $db->freeResult($result);
    }
    else {        
        //No videos currently being finalized, look for a triangulated one to flag        
        $result2 = $db->execute("select_videos_status", array("triangulated"));
        
        $resultRows2 = $db->countResultRows($result2);
        if ($resultRows2 > 0) {
            $branch = 2;
            
            $row = $db->fetchArray($result2);
            
            $videoid = $row['id'];
            $userid = $row['userid'];
            $fps = $row['frame_rate'];
            $width = $row['frame_width'];
            $height = $row['frame_height'];
            $framecount = $row['frame_count'];
            $title = $row['title'];
            $dir = $row['directory'];
            $tempfile = $row['tempfile'];

            //Flag video for finalizing
            $db->execute("update_videos_status_id", array("finalizing", $videoid));

            $db->freeResult($result2);
            
            echo 'Video #' . $videoid . ' has been flagged for finalizing...'.$e;     
        }
    }
    
    if ($branch == 0) {
        echo 'No more videos to process, idling...'.$e;
        sleep($sleepdur);
    }
}

?>

