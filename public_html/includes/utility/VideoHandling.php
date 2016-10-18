<?php
class VideoHandling {
    /*
     * Performs a quick ffprobe check to see if the video file
     * contains invalid data
     */
    public static function verifyVideoIntegrity($file) {
        $json = self::getVideoMetadata($file);
        
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
     * Returns the video metadata as a associative array json structure
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
}
