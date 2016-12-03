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
$minisleepdur = 1;
$softlockdur = 5; //Frames are soft locked for 5 seconds to prevent multiple processes from attempting to read them
$e = PHP_EOL;

function runTriangulization($imagepath, $outpath, $datapoints) {
    $facesize = 0;
    $eyesstr = "-1 -1 -1 -1";
    $pathstr = $imagepath . " " . $outpath;
    $facestr = "";
    $space = "";
   
    if ($datapoints != null) {    
        $eyes = $datapoints[0];
        $face = $datapoints[1];

        $facesize = count($face);

        $lx = $eyes["lx"];
        $ly = $eyes["ly"];
        $rx = $eyes["rx"];
        $ry = $eyes["ry"];

        $eyesstr = $lx . " " . $ly . " " . $rx . " " . $ry;

        $facestr = "";
        for ($i = 0; $i < $facesize - 1; $i++) {
            $p = $face[$i];
            $px = $p["x"];
            $py = $p["y"];

            $facestr = $facestr . " " . $px . " " . $py . " ";
        }

        if ($facesize > 0) {
            $p = $face[$facesize - 1];
            $px = $p["x"];
            $py = $p["y"];

            $facestr = $facestr . " " . $px . " " . $py;
            
            $space = " ";
        }
    }
    
    $output = shell_exec("./lib/OpenCVDrawing/Triangulization " . $pathstr . " " . $eyesstr . " " . $facesize . $space . $facestr);
    
    return $output;
}

function haveTriangulization($output) {
    if (strcmp($output, "success") == 0)  {
        return true;
    }
    else {
        return false;
    }
}

echo 'VideoProcess_Triangulation has started...'.$e;

while (true) {
    //Look for triangulating videos to process
    $result = $db->execute("select_videos_status", array("triangulating"));
    $resultrows = $db->countResultRows($result);
    
    $branch = 0;
    if ($resultrows > 0) {
        $branch = 1;
        
        echo 'Searching through triangulating videos...'.$e
                .'------------------------------------------'.$e;
        
        //Look through triangulating videos until we find one with unfinished frames
        while ($row = $db->fetchArray($result)) {
            $videoid = $row['id'];
            $userid = $row['userid'];
            $fps = $row['frame_rate'];
            $width = $row['frame_width'];
            $height = $row['frame_height'];
            $framecount = $row['frame_count'];
            $title = $row['title'];
            $dir = $row['directory'];
            $tempfile = $row['tempfile'];
            
            echo 'Searched video #' . $videoid . ' for a queued frame...'.$e;
            
            $result2 = $db->execute("select_frames_videoid-status-lastread*LTEQ_orderby=lastread*ASC", array($videoid, "queued", time() - $softlockdur));
            $resultrows = $db->countResultRows($result2);
            
            if ($resultrows > 0) {
                //Get the first queued frame
                $row2 = $db->fetchArray($result2);
                $frameid = $row2["id"];
                
                //Update lastread for frame to soft lock it
                $db->execute("update_frames_lastread_id-videoid", array(time(), $frameid, $videoid));
                
                echo 'Processing frame #' . $frameid . ' for video #' . $videoid . '...'.$e;
                
                //Process that frame
                $framepath = $dir . $videoid . '.' . $frameid . '.png';
                $outpath = $dir . 'temp/' . $videoid . '.' . $frameid . '.png';
                
                //Retrieve datapoints
                $pointdata = $row2['pointdata'];
                $datapoints = json_decode($pointdata, true);
                
                //Run Triangulization
                $output = runTriangulization($framepath, $outpath, $datapoints);
                
                $error = false;
                if (!haveTriangulization($output)) {                    
                    $error = true;
                }
                
                if ($error) {
                    echo 'No triangulization for frame #' . $frameid . ' for video #' . $videoid . '...'.$e;
                }
                
                $db->execute("update_frames_status_id-videoid", array("processed", $frameid, $videoid));
                        
                echo 'Frame #' . $frameid . ' processed for video #' . $videoid . ' (' . ($resultrows - 1) . ' frames remaining)...'.$e;
                
                $db->freeResult($result2);
                
                break; //Exit loop because we only wanted to process one frame for this iteration
            }
            else {
                //Check to see if we only have softlocked frames left
                $result3 = $db->execute("select_frames_videoid-status_orderby=lastread*ASC", array($videoid, "queued"));
                $resultrows = $db->countResultRows($result3);
                $db->freeResult($result3);
                
                if ($resultrows <= 0) {
                    //No queued frames, change video status to 'triangulated'
                    $db->execute("update_videos_status_id", array("triangulated", $videoid));

                    echo '------------------------------------------'.$e
                            .'Video #' . $videoid . ' has finished triangulating...'.$e
                            .'------------------------------------------'.$e;
                }
                
                //All frames are currently softlocked, small sleep before checking again
                sleep($minisleepdur);
            }
        }
        
        $db->freeResult($result);
    }
    else {        
        //No videos currently 'triangulating', look for a landmarked one to flag        
        $result2 = $db->execute("select_videos_status", array("landmarked"));
        
        $resultrows = $db->countResultRows($result2);
        if ($resultrows > 0) {
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

            //Update frame lastread to be '0', and change status to queued
            $db->execute("update_frames_lastread_videoid", array(0, $videoid));
            $db->execute("update_frames_status_videoid", array("queued", $videoid));

            //Flag video for triangulating
            $db->execute("update_videos_status_id", array("triangulating", $videoid));

            $db->freeResult($result2);
            
            echo 'Video #' . $videoid . ' has been flagged for triangulating...'.$e;     
        }
    }
    
    if ($branch == 0) {
        echo 'No more videos to process, idling...'.$e;
        sleep($sleepdur);
    }
}
?>

