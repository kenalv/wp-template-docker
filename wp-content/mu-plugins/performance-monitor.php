<?php
/**
 * Performance Monitor Plugin
 * 
 * Monitors WordPress performance metrics for headless API optimization
 * Tracks database queries, memory usage, and API response times
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WP_Performance_Monitor {
    
    private $start_time;
    private $start_memory;
    private $queries_before;
    
    public function __construct() {
        $this->start_time = microtime(true);
        $this->start_memory = memory_get_usage();
        $this->queries_before = get_num_queries();
        
        add_action('init', [$this, 'init']);
        add_action('wp_footer', [$this, 'display_stats']);
        add_action('rest_api_init', [$this, 'add_rest_stats']);
        add_filter('rest_post_dispatch', [$this, 'add_performance_headers'], 10, 3);
    }
    
    public function init() {
        // Only show stats for admin users in debug mode
        if (!WP_DEBUG || !current_user_can('manage_options')) {
            remove_action('wp_footer', [$this, 'display_stats']);
        }
    }
    
    public function get_performance_stats() {
        $end_time = microtime(true);
        $end_memory = memory_get_usage();
        $queries_after = get_num_queries();
        
        return [
            'execution_time' => round(($end_time - $this->start_time) * 1000, 2), // ms
            'memory_usage' => $this->format_bytes($end_memory - $this->start_memory),
            'peak_memory' => $this->format_bytes(memory_get_peak_usage()),
            'queries_count' => $queries_after - $this->queries_before,
            'timestamp' => current_time('mysql')
        ];
    }
    
    public function display_stats() {
        if (!WP_DEBUG) return;
        
        $stats = $this->get_performance_stats();
        
        echo "<!-- Performance Stats -->\n";
        echo "<!-- Execution Time: {$stats['execution_time']}ms -->\n";
        echo "<!-- Memory Usage: {$stats['memory_usage']} -->\n";
        echo "<!-- Peak Memory: {$stats['peak_memory']} -->\n";
        echo "<!-- Database Queries: {$stats['queries_count']} -->\n";
    }
    
    public function add_rest_stats() {
        register_rest_route('performance/v1', '/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_get_stats'],
            'permission_callback' => [$this, 'check_admin_permission']
        ]);
    }
    
    public function rest_get_stats($request) {
        $stats = $this->get_performance_stats();
        
        // Add server information
        $stats['server_info'] = [
            'php_version' => phpversion(),
            'wordpress_version' => get_bloginfo('version'),
            'mysql_version' => $this->get_mysql_version(),
            'server_load' => $this->get_server_load(),
        ];
        
        return rest_ensure_response($stats);
    }
    
    public function add_performance_headers($response, $server, $request) {
        $stats = $this->get_performance_stats();
        
        $response->header('X-Performance-Time', $stats['execution_time'] . 'ms');
        $response->header('X-Performance-Queries', $stats['queries_count']);
        $response->header('X-Performance-Memory', $stats['memory_usage']);
        
        return $response;
    }
    
    public function check_admin_permission() {
        return current_user_can('manage_options');
    }
    
    private function format_bytes($size, $precision = 2) {
        $base = log($size, 1024);
        $suffixes = ['B', 'KB', 'MB', 'GB', 'TB'];
        return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
    }
    
    private function get_mysql_version() {
        global $wpdb;
        return $wpdb->get_var("SELECT VERSION()");
    }
    
    private function get_server_load() {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return round($load[0], 2);
        }
        return 'N/A';
    }
}

// Initialize the performance monitor
new WP_Performance_Monitor();

/**
 * Log slow queries for optimization
 */
if (WP_DEBUG && defined('SAVEQUERIES') && SAVEQUERIES) {
    add_action('shutdown', function() {
        global $wpdb;
        
        $slow_queries = [];
        foreach ($wpdb->queries as $query) {
            if ($query[1] > 0.1) { // Queries slower than 100ms
                $slow_queries[] = [
                    'query' => $query[0],
                    'time' => $query[1],
                    'stack' => $query[2]
                ];
            }
        }
        
        if (!empty($slow_queries)) {
            error_log('Slow Queries Detected: ' . wp_json_encode($slow_queries));
        }
    });
}
