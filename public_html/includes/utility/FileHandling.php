<?php
/*
Alexandria Tran Le
Jason Edward Springer
Maxim Tiourin
Gordon Zhang
*/

class FileHandling {
    public static function generateTempFileIdentifier($seed) {
        return hash("sha256", "".$seed.Session::getIPAddress().time());
    }
    
    /*
     * Gives chmod permissions to the file at the given path
     */
    public static function ensurePermissions($file, $chmod = 0777) {
        chmod($file, $chmod);
    }
    
    public static function ensureDirectoryPermissionsRecursively($file, $chmod = 0777) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($file));

        foreach($iterator as $item) {
            chmod($item, $chmod);
        }
    }
    
    public static function deleteAllFilesMatchingPattern($pattern) {
        array_map("unlink", glob($pattern));
    }
    
    /*
     * Ensures the existence of the directory, by creating it if it doesn't exist
     * at the given path, and/or isn't a valid directory
     * 
     * Uses recursive mkdir
     */
    public static function ensureDirectory($path, $chmod = 0777) {
        if (!file_exists($path) || !is_dir($path)) {
            mkdir($path, $chmod, true);
            self::ensurePermissions($path, $chmod);
        }
    }
    
    public static function getFileExtension($filepath) {
        return pathinfo($filepath, PATHINFO_EXTENSION);
    }
    
    public static function isValidExtension($ext, $validexts) {
        return in_array($ext, $validexts);
    }
    
    public static function isValidMimeType($type, $validtypes) {
        return in_array($type, $validtypes);
    }
    
    public static function isValidSize($size, $maxSize) {
        return $size <= $maxSize;
    }
    
    public static function getBytesForKilobytes($kilobytes) {
        return $kilobytes * 1024;
    }
    
    public static function getBytesForMegabytes($megabytes) {
        return self::getBytesForKilobytes($megabytes * 1024);
    }
    
    public static function getBytesForGigabytes($gigabytes) {
        return self::getBytesForMegabytes($gigabytes * 1024);
    }
    
    public static function getBytesForTerabytes($terabytes) {
        return self::getBytesForGigabytes($terabytes * 1024);
    }
}
