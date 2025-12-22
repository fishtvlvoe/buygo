<?php
/**
 * Cleanup Cron Job
 * 
 * Automatically cleans up temporary files older than 1 hour in the mygo-temp directory.
 */

namespace BuyGo\Core\Cron;

if (!defined('ABSPATH')) {
    exit;
}

class CleanupCron {
    
    public function __construct() {
        // 註冊每日清理任務
        if (!wp_next_scheduled('mygo_cleanup_temp_files')) {
            wp_schedule_event(time(), 'daily', 'mygo_cleanup_temp_files');
        }
        
        add_action('mygo_cleanup_temp_files', [$this, 'cleanupTempFiles']);
    }
    
    /**
     * Clean up temporary files older than 1 hour
     */
    public function cleanupTempFiles() {
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/mygo-temp';
        
        if (!file_exists($temp_dir)) {
            return;
        }
        
        $files = glob($temp_dir . '/*.jpg');
        $oneHourAgo = time() - 3600; // 1 hour
        $deletedCount = 0;
        
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $oneHourAgo) {
                @unlink($file);
                $deletedCount++;
            }
        }
        
        if ($deletedCount > 0) {
            error_log("MYGO Cron: Cleaned up {$deletedCount} temporary files");
        }
    }
}

// Initialize the Cron Job
new CleanupCron();
