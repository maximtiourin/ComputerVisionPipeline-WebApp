<?php
class FileHandling {
    
    
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
