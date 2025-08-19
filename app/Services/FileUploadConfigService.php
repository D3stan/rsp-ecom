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
        
        // Filter out invalid values (0 or negative)
        $validSizes = array_filter([$uploadMaxFilesize, $postMaxSize, $memoryLimit], function($size) {
            return $size > 0;
        });
        
        // If no valid sizes found, return a reasonable default (2MB)
        if (empty($validSizes)) {
            return 2 * 1024 * 1024; // 2MB default
        }
        
        // Return the minimum of the valid limits (most restrictive)
        return min($validSizes);
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
        
        // Handle special cases
        if (empty($value) || $value === '0') {
            return 0;
        }
        
        // Handle unlimited memory (-1)
        if ($value === '-1') {
            return PHP_INT_MAX; // Return maximum possible value for unlimited
        }
        
        $last = strtolower($value[strlen($value) - 1]);
        $numericValue = (int) $value;
        
        // Handle negative values (except -1 which is handled above)
        if ($numericValue < 0) {
            return 0;
        }
        
        switch ($last) {
            case 'g':
                $numericValue *= 1024;
                // no break
            case 'm':
                $numericValue *= 1024;
                // no break
            case 'k':
                $numericValue *= 1024;
        }
        
        return $numericValue;
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
        
        // Ensure minimum reasonable size (at least 100KB)
        $maxSizeKb = max($maxSizeKb, 100);
        
        // Log the configuration for debugging
        \Log::info('File upload configuration', [
            'max_size_bytes' => $maxSize,
            'max_size_kb' => $maxSizeKb,
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'memory_limit' => ini_get('memory_limit'),
            'max_file_uploads' => ini_get('max_file_uploads')
        ]);
        
        return "max:{$maxSizeKb}";
    }
}
