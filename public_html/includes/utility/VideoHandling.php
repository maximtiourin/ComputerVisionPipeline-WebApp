<?php
/*
Alexandria Tran Le
Jason Edward Springer
Maxim Tiourin
Gordon Zhang
*/

class VideoHandling {    
    /*
     * Returns the video metadata as an associative array json structure
     * An empty structure signifies an error
     */
    public static function getVideoMetadata($file) {
        $output = shell_exec('ffprobe -v quiet -print_format json -show_format -show_streams "'.$file.'"');
        if (!$output) {
            return null;
        }
        else {
            return json_decode($output, true);
        }
    }
    
    /*
     * Extracts still png images from the file given an fps, and stores them
     * in the dest directory using the naming scheme:
     * 
     * 'id.frame#.png  
     */
    public static function extractStillImages($file, $id, $fps, $dest) {
        shell_exec('ffmpeg -i '.$file.' -vf fps='.$fps.' '.$dest.$id.'.%d.png');
    }
    
    /*
     * Performs a quick ffprobe check to see if the video
     * contains invalid data
     * 
     * Returns false if the video is invalid or corrupted
     */
    public static function verifyVideoIntegrity($json) {        
        if (!$json) {
            return false;
        }        
        else if (self::countVideoStreams($json) == 0) {
            return false;
        }
        else {
            return true;
        }
    }
    
    /*
     * Counts the number of video streams for the video
     * 
     * This is the amount of streams that contain the codec_type of 'video'
     */
    public static function countVideoStreams($json) {
        $streams = $json['streams'];
        $count = 0;
        
        foreach ($streams as $stream) {
            if (strcmp($stream['codec_type'], "video") == 0) {
                $count = $count + 1;
            }
        }
        
        return $count;
    }
    
    /*
     * Returns the frame count for the first video stream, or 0 if no video streams
     */
    public static function getFrameCount($json) {
        $streams = $json['streams'];
        
        foreach ($streams as $stream) {
            if (strcmp($stream['codec_type'], "video") == 0) {
                return $stream['duration_ts'];
            }
        }
        
        return 0;
    }
    
    /*
     * Returns the average rate of frames per second for the first video stream,
     * or 0 if no video streams
     */
    public static function getFrameRate($json) {
        $streams = $json['streams'];
        
        foreach ($streams as $stream) {
            if (strcmp($stream['codec_type'], "video") == 0) {
                $rate = $stream['avg_frame_rate'];
                list($frames, $seconds) = explode("/", $rate);
                $fps = (int) (((float) $frames) / ((float) $seconds));
                return $fps;
            }
        }
        
        return 0;
    }
    
    /*
     * Returns an associative array of the frame resolution for the first video stream
     * ex:
     * "width" => 1280
     * "height" => 780
     * 
     * returns 0 for both values if no video streams
     */
    public static function getFrameResolution($json) {
        $streams = $json['streams'];
        
        $arr = array("width" => 0, "height" => 0);
        
        foreach ($streams as $stream) {
            if (strcmp($stream['codec_type'], "video") == 0) {
                $arr['width'] = $stream['width'];
                $arr['height'] = $stream['height'];
                
                return $arr;
            }
        }
        
        return $arr;
    }
}
