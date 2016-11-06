<?php
include '../public_html/includes/include.php';

set_time_limit(0);

$db = new Database();
$db->connectDefault();
$db->prepareDefault();

$sleepdur = 5;
$e = PHP_EOL;

function runFacialRecognition($imagepath, $output) {
    shell_exec('FaceLandmarkImg -f "' . $imagepath . '" -of "' . $output . '"');
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
    $open = strpos($data, "{");
    $close = strpos($data, "}");
    
    $substr = substr($data, $open, $close - $open);
    
    $pointstrs = preg_split("/\r\n|\n|\r/", $substr);
    
    $points = array();
    
    $i = 0;
    foreach ($pointstrs as $str) {
        $psplit = split(" ", $str);
        
        $points[$i] = array("x" => $psplit[0], "y" => $psplit[1]);
        
        $i = $i + 1;
    }
    
    return $points;
}

echo 'VideoProcess_FaceLandmarking has started...'.$e;

while (true) {
    //Look for Extracted Videos to Process
    $result = $db->execute("select_videos_status", array("landmarking"));
    $resultrows = $db->countResultRows($result);
    
    $branch = 0;
    if ($resultrows > 0) {
        $branch = 1;
        
        echo 'Searching through landmarking videos...'.$e;
        
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
            
            $result2 = $db->execute("select_frames_videoid-status", array($videoid, "queued"));
            $resultrows = $db->countResultRows($result2);
            
            if ($resultrows > 0) {
                //Get the first queued frame that isnt locked
                $row2 = $db->fetchArray($result2);
                $frameid = $row2["id"];
                
                echo 'Processing frame #' . $frameid . ' for video #' . $videoid . '...'.$e;
                
                //Process that frame
                $framepath = $dir . $videoid . '.' . $frameid . '.png';
                $outputpath = $dir . $frameid .'_output.txt';
                runFacialRecognition($framepath, $outputpath);
                
                $error = false;
                if (haveFacialRecognition($dir, $frameid)) {
                    $data = extractFacialData($dir . $id . '_output_det_0.txt');
                    
                    if (isValidFacialData($data)) {
                        $points = getFacialDataArray($data);
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
            }
        }
    }
    else {        
        //No videos currently being landmarked, look for an extracted one to flag        
        $result2 = $db->execute("select_videos_status", array("extracted"));
        
        $resultrows = $db->countResultRows($result2);
        if (resultrows > 0) {
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
            for ($i = 1; i <= $framecount; $i++) {
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

