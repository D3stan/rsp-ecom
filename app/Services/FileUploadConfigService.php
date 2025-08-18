<?php

namespace App\Services;

class FileUploadConfigService
{
    /**
     * Get the maximum file upload size from PHP configuration
     * Returns the size in bytes
     */
    public static function getMaxFileUploadSize(): int
    {
        $uploadMaxFilesize = self::convertToBytes(ini_get('upload_max_filesize'));
        $postMaxSize = self::convertToBytes(ini_get('post_max_size'));
        $memoryLimit = self::convertToBytes(ini_get('memory_limit'));
        
        // Return the minimum of the three limits (most restrictive)
        return min($uploadMaxFilesize, $postMaxSize, $memoryLimit);
    }
    
    /**
     * Get the maximum file upload size in human readable format
     */
    public static function getMaxFileUploadSizeFormatted(): string
    {
        $bytes = self::getMaxFileUploadSize();
        return self::formatBytes($bytes);
    }
    
    /**
     * Get the maximum number of files that can be uploaded
     */
    public static function getMaxFileUploads(): int
    {
        return (int) ini_get('max_file_uploads');
    }
    
    /**
     * Convert PHP ini size notation to bytes
     */
    private static function convertToBytes(string $value): int
    {
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1]);
        $value = (int) $value;
        
        switch ($last) {
            case 'g':
                $value *= 1024;
                // no break
            case 'm':
                $value *= 1024;
                // no break
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }
    
    /**
     * Format bytes to human readable format
     */
    private static function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * Get validation rule for file size based on PHP configuration
     */
    public static function getFileValidationRule(): string
    {
        $maxSize = self::getMaxFileUploadSize();
        // Convert to kilobytes for Laravel validation
        $maxSizeKb = ceil($maxSize / 1024);
        
        return "max:{$maxSizeKb}";
    }
}
