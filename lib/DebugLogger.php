<?php
/**
 * Debug Logger for Blackwall Module
 * Creates detailed logs for troubleshooting
 */

class BlackwallDebugLogger {
    
    private static $log_file = null;
    private static $enabled = true;
    
    /**
     * Initialize logger
     */
    public static function init() {
        if (self::$log_file === null) {
            // Create log file in WHMCS root or tmp directory
            $log_dir = dirname(dirname(__DIR__)) . '/'; // WHMCS root
            if (!is_writable($log_dir)) {
                $log_dir = sys_get_temp_dir() . '/';
            }
            
            self::$log_file = $log_dir . 'blackwall_debug.log';
        }
    }
    
    /**
     * Log debug message
     */
    public static function log($level, $message, $data = []) {
        if (!self::$enabled) return;
        
        self::init();
        
        $timestamp = date('Y-m-d H:i:s');
        $caller = self::getCaller();
        
        $log_entry = [
            'timestamp' => $timestamp,
            'level' => strtoupper($level),
            'caller' => $caller,
            'message' => $message,
            'data' => $data
        ];
        
        $log_line = "[{$timestamp}] [{$level}] [{$caller}] {$message}";
        if (!empty($data)) {
            $log_line .= " | Data: " . json_encode($data);
        }
        $log_line .= "\n";
        
        // Write to file
        file_put_contents(self::$log_file, $log_line, FILE_APPEND | LOCK_EX);
        
        // Also write to PHP error log
        error_log("BLACKWALL [{$level}] {$message} | " . json_encode($data));
    }
    
    /**
     * Get caller information
     */
    private static function getCaller() {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4);
        
        // Skip our own functions
        $caller = $trace[2] ?? $trace[1] ?? $trace[0];
        
        $file = basename($caller['file'] ?? 'unknown');
        $line = $caller['line'] ?? '?';
        $function = $caller['function'] ?? 'unknown';
        
        return "{$file}:{$line}:{$function}";
    }
    
    /**
     * Log info level
     */
    public static function info($message, $data = []) {
        self::log('INFO', $message, $data);
    }
    
    /**
     * Log error level
     */
    public static function error($message, $data = []) {
        self::log('ERROR', $message, $data);
    }
    
    /**
     * Log debug level
     */
    public static function debug($message, $data = []) {
        self::log('DEBUG', $message, $data);
    }
    
    /**
     * Log warning level
     */
    public static function warning($message, $data = []) {
        self::log('WARNING', $message, $data);
    }
    
    /**
     * Get log file path
     */
    public static function getLogFile() {
        self::init();
        return self::$log_file;
    }
    
    /**
     * Get recent logs
     */
    public static function getRecentLogs($lines = 50) {
        self::init();
        
        if (!file_exists(self::$log_file)) {
            return "No log file found at: " . self::$log_file;
        }
        
        $file_lines = file(self::$log_file);
        $recent_lines = array_slice($file_lines, -$lines);
        
        return implode('', $recent_lines);
    }
    
    /**
     * Clear log file
     */
    public static function clearLogs() {
        self::init();
        
        if (file_exists(self::$log_file)) {
            file_put_contents(self::$log_file, '');
            return true;
        }
        
        return false;
    }
    
    /**
     * Enable/disable logging
     */
    public static function setEnabled($enabled) {
        self::$enabled = (bool) $enabled;
    }
}