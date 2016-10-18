<?php
class VideoHandling {
    /*
     * Performs a quick ffprobe check to see if the video file
     * contains invalid data
     */
    public static function verifyVideoIntegrity($file) {
        $json = getVideoMetadata($file);
        
        if (!$json) {
            return false;
        }
        
        if (empty($json)) {
            return false;
        }
        
        return true;
    }
    
    /*
     * Returns the video metadata as a associative array json structure
     * An empty structure signifies an error
     */
    public static function getVideoMetadata($file) {
        $output = shell_exec('ffprove -v quiet -print_format json -show_format -show_streams "'.$file.'"');
        return json_decode($output, true);
    }
}
