<?php
/*
Alexandria Tran Le
Jason Edward Springer
Maxim Tiourin
Gordon Zhang
*/

/*
 * This batch version of the FaceLandmarking process is just used for demoing/testing
 * purposes, so that images are processed faster by openface by batching them in a single process. In a production
 * environment, there would ideally be a **large** amount of FaceLandmarking processes operating at the same time, 
 * on multiple cores and machines, and in that situation batching would no longer be faster.
 */

include '../public_html/includes/include.php';

set_time_limit(0);

$db = new Database();
$db->connectDefault();
$db->prepareDefault();

$sleepdur = 5;
$minisleepdur = 1;
$softlockdur = 30; //Videos are soft locked for 30 seconds to prevent multiple processes from attempting to read them
$e = PHP_EOL;

function runFacialRecognition($inputdir, $outputdir) {    
    shell_exec('./lib/FaceLandmarkImg -fdir "' . $inputdir . '" -ofdir "' . $outputdir . '"');
}

function haveFacialRecognition($checkpath, $id) {
    $file = $checkpath . $id . '_det_0.pts';
    
    if (file_exists($file)) {
        return true;
    }
    else {
        return false;
    }
}

function extractPupilData($data) {
    $splitmid = explode(":", $data);
    $lhs = explode(",", $splitmid[0]);
    $rhs = explode(",", $splitmid[1]);
    
    $arr = array("lx" => $lhs[0], "ly" => $lhs[1], "rx" => $rhs[0], "ry" => $rhs[1]);
    return $arr;
}

function runPupilDetection($inputdir) {
    $output = shell_exec('./lib/EyeDetection/eyeLike ' . $inputdir);
    
    $data = extractPupilData($output);
    
    return $data;
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

echo 'VideoProcess_FaceLandmarkingBatch has started...'.$e
        .'------------------------------------------'.$e;

while (true) {
    //Look for 'batch landmarking' Videos
    $result = $db->execute("select_videos_status", array("batch landmarking"));
    $resultRows = $db->countResultRows($result);
    
    $branch = 0;
    if ($resultRows > 0) {
        $branch = 1;
        
        echo 'Searching through batch landmarking videos...'.$e
                .'------------------------------------------'.$e;
        
        $result2 = $db->execute("select_videos_status-lastread*LTEQ_orderby=lastread-id*ASC", array("batch landmarking", time() - $softlockdur));
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
            
            echo 'Batch Landmarking video #' . $videoid . '...'.$e;
            
            //Batch Landmark
            //BATCH()
            runFacialRecognition($dir, $dir);
            
            for ($frameid = 1; $frameid <= $framecount; $frameid++) {
                $error = false;
                if (haveFacialRecognition($dir, $videoid . '.' . $frameid)) {
                    $data = extractFacialData($dir . $videoid . '.' . $frameid . '_det_0.pts');
                    
                    if (isValidFacialData($data)) {
                        //We've detected a face, now lets grab pupils
                        
                        $pdata = runPupilRecognition($dir . $videoid . '.' . $frameid . ".png");                        
                        $pupils = array("lx" => $pdata['lx'], "ly" => $pdata['ly'], "rx" => $pdata['rx'], "ry" => $pdata['ry']); 
                                
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
                    //echo 'No facial recognization for frame #' . $frameid . ' for video #' . $videoid . '...'.$e;
                }
                
                FileHandling::deleteAllFilesMatchingPattern($dir . $videoid . '.' . $frameid . '_det_*.pts');
                
                $db->execute("update_frames_status_id-videoid", array("processed", $frameid, $videoid));
                        
                //echo 'Frame #' . $frameid . ' processed for video #' . $videoid . ' (' . ($resultrows - 1) . ' frames remaining)...'.$e;
            }
            
            $db->execute("update_videos_status_id", array("landmarked", $videoid));
            $db->execute("update_videos_lastread_id", array(0, $videoid)); //Reset video lastread for finalization

            echo '------------------------------------------'.$e
                    .'Video #' . $videoid . ' has finished batch landmarking...'.$e
                    .'------------------------------------------'.$e;
            
            $db->freeResult($result2);
        }
        else {
            //No unlocked videos to process
            echo 'No unlocked batch landmarking videos to currently process, idling...'.$e;
            sleep($minisleepdur);
        }
        
        $db->freeResult($result);
    }
    else {        
        //No videos currently being batch landmarked, look for a extracted one to flag        
        $result2 = $db->execute("select_videos_status", array("extracted"));
        
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

            //Create frame container rows in frames table
            for ($i = 1; $i <= $framecount; $i++) {
                $db->execute("insert_frames_id-videoid", array($i, $videoid));
            }
            
            //Flag video for batch landmarking
            $db->execute("update_videos_status_id", array("batch landmarking", $videoid));

            $db->freeResult($result2);
            
            echo 'Video #' . $videoid . ' has been flagged for batch landmarking...'.$e;     
        }
    }
    
    if ($branch == 0) {
        echo 'No more videos to process, idling...'.$e;
        sleep($sleepdur);
    }
}

?>
