<?php
/**
 * Plugin Name: Custom API Extensions
 * Plugin URI: https://your-domain.com
 * Description: Custom API extensions for headless WordPress functionality. Add your project-specific API endpoints and features here.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://your-domain.com
 * Text Domain: custom-api
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.8
 * Requires PHP: 8.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('CUSTOM_API_VERSION', '1.0.0');
define('CUSTOM_API_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CUSTOM_API_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main Custom API Plugin Class
 */
class CustomApiPlugin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', [$this, 'init']);
        add_action('rest_api_init', [$this, 'register_api_routes']);
        
        // Plugin lifecycle hooks
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
        
        // Load plugin textdomain for internationalization
        add_action('plugins_loaded', [$this, 'load_textdomain']);
    }
    
    public function init() {
        // Initialize plugin functionality
        $this->load_dependencies();
        $this->setup_admin_hooks();
    }
    
    public function register_api_routes() {
        $namespace = 'custom-api/v1';
        
        // Example: Advanced portfolio endpoint with filtering
        register_rest_route($namespace, '/portfolio/advanced', [
            'methods' => 'GET',
            'callback' => [$this, 'get_advanced_portfolio'],
            'permission_callback' => '__return_true',
            'args' => [
                'category' => [
                    'description' => 'Filter by portfolio category',
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'featured' => [
                    'description' => 'Only featured items',
                    'type' => 'boolean',
                    'default' => false,
                ],
                'limit' => [
                    'description' => 'Number of items to return',
                    'type' => 'integer',
                    'default' => 10,
                    'minimum' => 1,
                    'maximum' => 100,
                ],
                'orderby' => [
                    'description' => 'Order by field',
                    'type' => 'string',
                    'enum' => ['date', 'title', 'menu_order', 'featured'],
                    'default' => 'date',
                ],
                'order' => [
                    'description' => 'Order direction',
                    'type' => 'string',
                    'enum' => ['ASC', 'DESC'],
                    'default' => 'DESC',
                ]
            ]
        ]);
        
        // Example: Search endpoint
        register_rest_route($namespace, '/search', [
            'methods' => 'GET',
            'callback' => [$this, 'search_content'],
            'permission_callback' => '__return_true',
            'args' => [
                'query' => [
                    'description' => 'Search query',
                    'type' => 'string',
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'post_types' => [
                    'description' => 'Post types to search',
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'default' => ['post', 'page', 'portfolio'],
                ],
                'limit' => [
                    'description' => 'Number of results',
                    'type' => 'integer',
                    'default' => 20,
                    'minimum' => 1,
                    'maximum' => 100,
                ]
            ]
        ]);
        
        // Example: Analytics endpoint
        register_rest_route($namespace, '/analytics', [
            'methods' => 'GET',
            'callback' => [$this, 'get_analytics'],
            'permission_callback' => [$this, 'check_admin_permission']
        ]);
        
        // Example: Bulk operations endpoint
        register_rest_route($namespace, '/bulk', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_bulk_operations'],
            'permission_callback' => [$this, 'check_edit_permission'],
            'args' => [
                'operation' => [
                    'description' => 'Bulk operation type',
                    'type' => 'string',
                    'enum' => ['publish', 'draft', 'delete', 'update_meta'],
                    'required' => true,
                ],
                'post_ids' => [
                    'description' => 'Post IDs to operate on',
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'required' => true,
                ],
                'data' => [
                    'description' => 'Additional data for operation',
                    'type' => 'object',
                ]
            ]
        ]);
    }
    
    public function get_advanced_portfolio($request) {
        $args = [
            'post_type' => 'portfolio',
            'post_status' => 'publish',
            'posts_per_page' => $request->get_param('limit'),
            'orderby' => $request->get_param('orderby'),
            'order' => $request->get_param('order'),
            'meta_query' => []
        ];
        
        // Filter by category
        if ($category = $request->get_param('category')) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'portfolio_category',
                    'field' => 'slug',
                    'terms' => $category,
                ]
            ];
        }
        
        // Filter by featured
        if ($request->get_param('featured')) {
            $args['meta_query'][] = [
                'key' => 'featured',
                'value' => '1',
                'compare' => '='
            ];
        }
        
        $query = new WP_Query($args);
        $items = [];
        
        foreach ($query->posts as $post) {
            $items[] = $this->prepare_portfolio_response($post);
        }
        
        $response = rest_ensure_response($items);
        $response->header('X-Total-Items', $query->found_posts);
        $response->header('X-Total-Pages', $query->max_num_pages);
        
        return $response;
    }
    
    public function search_content($request) {
        $search_query = $request->get_param('query');
        $post_types = $request->get_param('post_types');
        $limit = $request->get_param('limit');
        
        $args = [
            's' => $search_query,
            'post_type' => $post_types,
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'orderby' => 'relevance'
        ];
        
        $query = new WP_Query($args);
        $results = [];
        
        foreach ($query->posts as $post) {
            $results[] = [
                'id' => $post->ID,
                'title' => get_the_title($post->ID),
                'excerpt' => get_the_excerpt($post->ID),
                'permalink' => get_permalink($post->ID),
                'post_type' => $post->post_type,
                'date' => get_the_date('c', $post->ID),
                'relevance_score' => $this->calculate_relevance_score($post, $search_query)
            ];
        }
        
        return rest_ensure_response([
            'query' => $search_query,
            'results' => $results,
            'total_found' => $query->found_posts
        ]);
    }
    
    public function get_analytics($request) {
        global $wpdb;
        
        // Get basic site statistics
        $stats = [
            'posts' => wp_count_posts('post')->publish,
            'pages' => wp_count_posts('page')->publish,
            'portfolio' => wp_count_posts('portfolio')->publish ?? 0,
            'services' => wp_count_posts('service')->publish ?? 0,
            'comments' => wp_count_comments()->approved,
            'users' => count_users()['total_users']
        ];
        
        // Get API usage statistics (if logging is enabled)
        $api_stats = [];
        if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}api_logs'")) {
            $api_stats = [
                'total_requests' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}api_logs"),
                'requests_today' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}api_logs WHERE DATE(created_at) = CURDATE()"),
                'top_endpoints' => $wpdb->get_results("
                    SELECT endpoint, COUNT(*) as requests 
                    FROM {$wpdb->prefix}api_logs 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    GROUP BY endpoint 
                    ORDER BY requests DESC 
                    LIMIT 10
                ", ARRAY_A),
                'avg_response_time' => $wpdb->get_var("SELECT AVG(response_time) FROM {$wpdb->prefix}api_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")
            ];
        }
        
        return rest_ensure_response([
            'site_stats' => $stats,
            'api_stats' => $api_stats,
            'generated_at' => current_time('iso8601')
        ]);
    }
    
    public function handle_bulk_operations($request) {
        $operation = $request->get_param('operation');
        $post_ids = $request->get_param('post_ids');
        $data = $request->get_param('data') ?: [];
        
        $results = [];
        $errors = [];
        
        foreach ($post_ids as $post_id) {
            try {
                switch ($operation) {
                    case 'publish':
                        wp_update_post(['ID' => $post_id, 'post_status' => 'publish']);
                        $results[] = ['id' => $post_id, 'status' => 'published'];
                        break;
                        
                    case 'draft':
                        wp_update_post(['ID' => $post_id, 'post_status' => 'draft']);
                        $results[] = ['id' => $post_id, 'status' => 'drafted'];
                        break;
                        
                    case 'delete':
                        wp_delete_post($post_id, true);
                        $results[] = ['id' => $post_id, 'status' => 'deleted'];
                        break;
                        
                    case 'update_meta':
                        foreach ($data as $meta_key => $meta_value) {
                            update_post_meta($post_id, $meta_key, $meta_value);
                        }
                        $results[] = ['id' => $post_id, 'status' => 'meta_updated'];
                        break;
                }
            } catch (Exception $e) {
                $errors[] = ['id' => $post_id, 'error' => $e->getMessage()];
            }
        }
        
        return rest_ensure_response([
            'operation' => $operation,
            'results' => $results,
            'errors' => $errors,
            'total_processed' => count($results),
            'total_errors' => count($errors)
        ]);
    }
    
    public function check_admin_permission() {
        return current_user_can('manage_options');
    }
    
    public function check_edit_permission() {
        return current_user_can('edit_posts');
    }
    
    private function prepare_portfolio_response($post) {
        $featured_image = get_post_thumbnail_id($post->ID);
        
        return [
            'id' => $post->ID,
            'title' => get_the_title($post->ID),
            'content' => apply_filters('the_content', $post->post_content),
            'excerpt' => get_the_excerpt($post->ID),
            'slug' => $post->post_name,
            'date' => get_the_date('c', $post->ID),
            'modified' => get_the_modified_date('c', $post->ID),
            'featured_image' => $featured_image ? wp_get_attachment_image_url($featured_image, 'full') : null,
            'gallery' => $this->get_post_gallery($post->ID),
            'categories' => wp_get_post_terms($post->ID, 'portfolio_category', ['fields' => 'names']),
            'meta' => $this->get_public_meta($post->ID),
            'permalink' => get_permalink($post->ID)
        ];
    }
    
    private function get_post_gallery($post_id) {
        $gallery_ids = get_post_meta($post_id, 'gallery_images', true);
        if (!$gallery_ids) return [];
        
        $gallery = [];
        foreach (explode(',', $gallery_ids) as $image_id) {
            if ($url = wp_get_attachment_image_url($image_id, 'full')) {
                $gallery[] = [
                    'id' => $image_id,
                    'url' => $url,
                    'alt' => get_post_meta($image_id, '_wp_attachment_image_alt', true),
                    'caption' => wp_get_attachment_caption($image_id)
                ];
            }
        }
        
        return $gallery;
    }
    
    private function get_public_meta($post_id) {
        $all_meta = get_post_meta($post_id);
        $public_meta = [];
        
        foreach ($all_meta as $key => $value) {
            // Skip private meta fields (starting with _)
            if (substr($key, 0, 1) !== '_') {
                $public_meta[$key] = is_array($value) && count($value) === 1 ? $value[0] : $value;
            }
        }
        
        return $public_meta;
    }
    
    private function calculate_relevance_score($post, $query) {
        $score = 0;
        $query_lower = strtolower($query);
        
        // Title matches get higher score
        if (stripos($post->post_title, $query) !== false) {
            $score += 10;
        }
        
        // Content matches
        $content_matches = substr_count(strtolower($post->post_content), $query_lower);
        $score += $content_matches * 2;
        
        // Excerpt matches
        if (stripos($post->post_excerpt, $query) !== false) {
            $score += 5;
        }
        
        return $score;
    }
    
    public function activate() {
        // Create custom database tables if needed
        $this->create_custom_tables();
        
        // Set default options
        add_option('custom_api_version', CUSTOM_API_VERSION);
        add_option('custom_api_activated_time', current_time('mysql'));
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        // Clean up temporary data
        delete_transient('custom_api_cache');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    public function load_textdomain() {
        load_plugin_textdomain('custom-api', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    private function load_dependencies() {
        // Load additional plugin files here
        // require_once CUSTOM_API_PLUGIN_DIR . 'includes/class-custom-endpoint.php';
    }
    
    private function setup_admin_hooks() {
        if (is_admin()) {
            add_action('admin_menu', [$this, 'add_admin_menu']);
        }
    }
    
    public function add_admin_menu() {
        add_options_page(
            __('Custom API Settings', 'custom-api'),
            __('Custom API', 'custom-api'),
            'manage_options',
            'custom-api-settings',
            [$this, 'admin_page']
        );
    }
    
    public function admin_page() {
        echo '<div class="wrap">';
        echo '<h1>' . __('Custom API Settings', 'custom-api') . '</h1>';
        echo '<p>' . __('Configure your custom API endpoints and settings here.', 'custom-api') . '</p>';
        
        // Add your admin interface here
        echo '<h2>' . __('API Status', 'custom-api') . '</h2>';
        echo '<p>Plugin Version: ' . CUSTOM_API_VERSION . '</p>';
        echo '<p>REST API Base: <code>' . rest_url('custom-api/v1/') . '</code></p>';
        
        echo '</div>';
    }
    
    private function create_custom_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Example: Custom analytics table
        $table_name = $wpdb->prefix . 'custom_api_analytics';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            endpoint varchar(255) NOT NULL,
            hits bigint(20) NOT NULL DEFAULT 0,
            last_hit datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY endpoint (endpoint)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

// Initialize the plugin
CustomApiPlugin::get_instance();
