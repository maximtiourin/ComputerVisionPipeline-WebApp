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

function runFacialRecognition($imagepath, $output) {
    shell_exec('./lib/FaceLandmarkImg -f "' . $imagepath . '" -of "' . $output . '"');
}

function haveFacialRecognition($checkpath, $id) {
    $file = $checkpath . $id . '_output_det_0.txt';
    
    if (file_exists($file)) {
        return true;
    }
    else {
        return false;
    }
}

function extractFacialData($datapath) {
    return file_get_contents($datapath);
}

function isValidFacialData($data) {
    $str = $data;
    $occurence = strpos($str, "npoints: 68");
    
    if ($occurence === FALSE) {
        return false;
    }
    else {
        return true;
    }
}

function getFacialDataArray($data) {
    $open = strpos($data, "{") + 2; //Offset the brace and the newline
    $close = strpos($data, "}") - 1; //Offset the brace
    
    $substr = substr($data, $open, $close - $open);
    
    $pointstrs = preg_split("/\r\n|\n|\r/", $substr);
    
    $points = array();
    
    $i = 0;
    foreach ($pointstrs as $str) {
        $psplit = explode(" ", $str);
        
        if (count($psplit == 2)) {
            $points["".$i] = array("x" => $psplit[0], "y" => $psplit[1]);

            $i = $i + 1;
        }
    }
    
    return $points;
}

echo 'VideoProcess_FaceLandmarking has started...'.$e
        .'------------------------------------------'.$e;

while (true) {
    //Look for Extracted Videos to Process
    $result = $db->execute("select_videos_status", array("landmarking"));
    $resultrows = $db->countResultRows($result);
    
    $branch = 0;
    if ($resultrows > 0) {
        $branch = 1;
        
        echo 'Searching through landmarking videos...'.$e
                .'------------------------------------------'.$e;;
        
        //Look through landmarking videos until we find one with unfinished frames
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
                $outputpath = $dir . $frameid .'_output.txt';
                runFacialRecognition($framepath, $outputpath);
                
                $error = false;
                if (haveFacialRecognition($dir, $frameid)) {
                    $data = extractFacialData($dir . $frameid . '_output_det_0.txt');
                    
                    if (isValidFacialData($data)) {
                        //We've detected a face, now lets grab pupils
                        
                        //runPupilRecognition()                        
                        $pupils = array(-1, -1, -1, -1); 
                                
                        $points = getFacialDataArray($data);
                        
                        $arr = array($pupils, $points);
                        
                        $json = json_encode($arr);
                        
                        $db->execute("update_frames_pointdata_id-videoid", array($json, $frameid, $videoid));
                    }
                    else {
                        $error = true;
                    }
                }
                else {
                    $error = true;
                }
                
                if ($error) {
                    echo 'No facial recognization for frame #' . $frameid . ' for video #' . $videoid . '...'.$e;
                }
                
                $db->execute("update_frames_status_id-videoid", array("processed", $frameid, $videoid));
                        
                echo 'Frame #' . $frameid . ' processed for video #' . $videoid . ' (' . ($resultrows - 1) . ' frames remaining)...'.$e;
                
                $db->freeResult($result2);
                
                //Delete all output txts for frame id
                FileHandling::deleteAllFilesMatchingPattern($dir . $frameid . '_output_det_*.txt');
                
                break; //Exit loop because we only wanted to process one frame for this iteration
            }
            else {
                //Check to see if we only have softlocked frames left
                $result3 = $db->execute("select_frames_videoid-status_orderby=lastread*ASC", array($videoid, "queued"));
                $resultrows = $db->countResultRows($result3);
                $db->freeResult($result3);
                
                if ($resultrows <= 0) {
                    //No queued frames, change video status to 'landmarked'
                    $db->execute("update_videos_status_id", array("landmarked", $videoid));

                    echo '------------------------------------------'.$e
                            .'Video #' . $videoid . ' has finished landmarking...'.$e
                            .'------------------------------------------'.$e;
                }
                
                //All frames are currently softlocked, small sleep before checking again
                sleep($minisleepdur);
            }
        }
        
        $db->freeResult($result);
    }
    else {        
        //No videos currently being landmarked, look for an extracted one to flag        
        $result2 = $db->execute("select_videos_status", array("extracted"));
        
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

            //Create frame container rows in frames table
            for ($i = 1; $i <= $framecount; $i++) {
                $db->execute("insert_frames_id-videoid", array($i, $videoid));
            }

            //Flag video for landmarking
            $db->execute("update_videos_status_id", array("landmarking", $videoid));

            $db->freeResult($result2);
            
            echo 'Video #' . $videoid . ' has been flagged for landmarking...'.$e;     
        }
    }
    
    if ($branch == 0) {
        echo 'No more videos to process, idling...'.$e;
        sleep($sleepdur);
    }
}

?>

