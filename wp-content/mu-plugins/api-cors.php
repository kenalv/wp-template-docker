<?php
/**
 * API CORS Configuration
 * 
 * Handles Cross-Origin Resource Sharing for WordPress REST API
 * Optimized for headless WordPress usage
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WP_API_CORS {
    
    public function __construct() {
        add_action('init', [$this, 'init']);
        add_action('rest_api_init', [$this, 'add_cors_headers']);
        add_filter('rest_pre_serve_request', [$this, 'handle_preflight'], 10, 4);
    }
    
    public function init() {
        // Handle preflight requests early
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->send_cors_headers();
            status_header(204);
            exit();
        }
    }
    
    public function add_cors_headers() {
        add_filter('rest_pre_serve_request', function($served, $result, $request, $server) {
            $this->send_cors_headers();
            return $served;
        }, 10, 4);
    }
    
    public function handle_preflight($served, $result, $request, $server) {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->send_cors_headers();
            return true;
        }
        return $served;
    }
    
    private function send_cors_headers() {
        $allowed_origins = $this->get_allowed_origins();
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        // Check if origin is allowed
        if ($this->is_origin_allowed($origin, $allowed_origins)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
        } else {
            // Fallback for development
            if (WP_DEBUG) {
                header('Access-Control-Allow-Origin: *');
            }
        }
        
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With, Accept, Origin, X-WP-Nonce');
        header('Access-Control-Expose-Headers: X-WP-Total, X-WP-TotalPages, Link, X-Performance-Time, X-Performance-Queries');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400'); // 24 hours
    }
    
    private function get_allowed_origins() {
        // Default allowed origins - customize for your frontend applications
        $default_origins = [
            'http://localhost:3000',  // React/Next.js dev
            'http://localhost:3001',  // Alternative dev port
            'http://localhost:4321',  // Astro dev
            'http://localhost:5173',  // Vite dev
            'http://localhost:8080',  // Vue dev
        ];
        
        // Get custom origins from WordPress options or environment
        $custom_origins = get_option('api_cors_origins', []);
        if (defined('API_CORS_ORIGINS')) {
            $env_origins = explode(',', API_CORS_ORIGINS);
            $custom_origins = array_merge($custom_origins, array_map('trim', $env_origins));
        }
        
        return array_merge($default_origins, $custom_origins);
    }
    
    private function is_origin_allowed($origin, $allowed_origins) {
        if (empty($origin)) {
            return false;
        }
        
        return in_array($origin, $allowed_origins);
    }
}

// Initialize CORS handler
new WP_API_CORS();

/**
 * Add CORS support for file uploads
 */
add_filter('upload_dir', function($uploads) {
    // Ensure uploads directory exists and is writable
    if (!file_exists($uploads['path'])) {
        wp_mkdir_p($uploads['path']);
    }
    return $uploads;
});

/**
 * Enable CORS for media uploads
 */
add_action('wp_ajax_nopriv_upload_media', function() {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
});

add_action('wp_ajax_upload_media', function() {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
});
