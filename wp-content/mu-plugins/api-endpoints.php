<?php
/**
 * Custom API Endpoints
 * 
 * Registers custom REST API endpoints for headless WordPress
 * Extend this file to add your project-specific endpoints
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WP_Custom_API_Endpoints {
    
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_endpoints']);
    }
    
    public function register_endpoints() {
        // Custom namespace for your project
        $namespace = 'custom/v1';
        
        // Health check endpoint
        register_rest_route($namespace, '/health', [
            'methods' => 'GET',
            'callback' => [$this, 'health_check'],
            'permission_callback' => '__return_true'
        ]);
        
        // Site information endpoint
        register_rest_route($namespace, '/site-info', [
            'methods' => 'GET',
            'callback' => [$this, 'get_site_info'],
            'permission_callback' => '__return_true'
        ]);
        
        // Portfolio endpoints (example custom post type)
        register_rest_route($namespace, '/portfolio', [
            'methods' => 'GET',
            'callback' => [$this, 'get_portfolio_items'],
            'permission_callback' => '__return_true',
            'args' => $this->get_collection_params()
        ]);
        
        register_rest_route($namespace, '/portfolio/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_portfolio_item'],
            'permission_callback' => '__return_true',
            'args' => [
                'id' => [
                    'validate_callback' => function($param, $request, $key) {
                        return is_numeric($param);
                    }
                ],
            ],
        ]);
        
        // Services endpoints (example)
        register_rest_route($namespace, '/services', [
            'methods' => 'GET',
            'callback' => [$this, 'get_services'],
            'permission_callback' => '__return_true',
            'args' => $this->get_collection_params()
        ]);
        
        // Contact form endpoint
        register_rest_route($namespace, '/contact', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_contact_form'],
            'permission_callback' => '__return_true',
            'args' => [
                'name' => [
                    'required' => true,
                    'validate_callback' => function($param, $request, $key) {
                        return !empty($param) && strlen($param) <= 100;
                    }
                ],
                'email' => [
                    'required' => true,
                    'validate_callback' => function($param, $request, $key) {
                        return is_email($param);
                    }
                ],
                'message' => [
                    'required' => true,
                    'validate_callback' => function($param, $request, $key) {
                        return !empty($param) && strlen($param) <= 1000;
                    }
                ]
            ]
        ]);
        
        // Menu endpoints
        register_rest_route($namespace, '/menus', [
            'methods' => 'GET',
            'callback' => [$this, 'get_menus'],
            'permission_callback' => '__return_true'
        ]);
        
        register_rest_route($namespace, '/menus/(?P<location>[a-zA-Z0-9_-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_menu_by_location'],
            'permission_callback' => '__return_true'
        ]);
    }
    
    public function health_check($request) {
        global $wpdb;
        
        // Basic health checks
        $health = [
            'status' => 'healthy',
            'timestamp' => current_time('iso8601'),
            'version' => get_bloginfo('version'),
            'php_version' => phpversion(),
            'checks' => []
        ];
        
        // Database connectivity check
        try {
            $wpdb->get_var("SELECT 1");
            $health['checks']['database'] = 'OK';
        } catch (Exception $e) {
            $health['checks']['database'] = 'FAILED';
            $health['status'] = 'unhealthy';
        }
        
        // Filesystem write check
        if (is_writable(wp_upload_dir()['path'])) {
            $health['checks']['uploads'] = 'OK';
        } else {
            $health['checks']['uploads'] = 'FAILED';
            $health['status'] = 'unhealthy';
        }
        
        return rest_ensure_response($health);
    }
    
    public function get_site_info($request) {
        $info = [
            'name' => get_bloginfo('name'),
            'description' => get_bloginfo('description'),
            'url' => home_url(),
            'language' => get_locale(),
            'timezone' => get_option('timezone_string'),
            'date_format' => get_option('date_format'),
            'time_format' => get_option('time_format'),
            'admin_email' => get_option('admin_email'),
            'users_can_register' => get_option('users_can_register'),
            'start_of_week' => get_option('start_of_week')
        ];
        
        return rest_ensure_response($info);
    }
    
    public function get_portfolio_items($request) {
        $args = [
            'post_type' => 'portfolio',
            'post_status' => 'publish',
            'posts_per_page' => $request->get_param('per_page') ?: 10,
            'paged' => $request->get_param('page') ?: 1,
            'orderby' => $request->get_param('orderby') ?: 'date',
            'order' => $request->get_param('order') ?: 'DESC'
        ];
        
        $query = new WP_Query($args);
        $items = [];
        
        foreach ($query->posts as $post) {
            $items[] = $this->prepare_portfolio_item($post);
        }
        
        $response = rest_ensure_response($items);
        $response->header('X-WP-Total', $query->found_posts);
        $response->header('X-WP-TotalPages', $query->max_num_pages);
        
        return $response;
    }
    
    public function get_portfolio_item($request) {
        $post = get_post($request['id']);
        
        if (!$post || $post->post_type !== 'portfolio') {
            return new WP_Error('not_found', 'Portfolio item not found', ['status' => 404]);
        }
        
        return rest_ensure_response($this->prepare_portfolio_item($post));
    }
    
    public function get_services($request) {
        $args = [
            'post_type' => 'service',
            'post_status' => 'publish',
            'posts_per_page' => $request->get_param('per_page') ?: -1,
            'orderby' => 'menu_order',
            'order' => 'ASC'
        ];
        
        $query = new WP_Query($args);
        $services = [];
        
        foreach ($query->posts as $post) {
            $services[] = $this->prepare_service_item($post);
        }
        
        return rest_ensure_response($services);
    }
    
    public function handle_contact_form($request) {
        $name = sanitize_text_field($request->get_param('name'));
        $email = sanitize_email($request->get_param('email'));
        $message = sanitize_textarea_field($request->get_param('message'));
        
        // Here you can add your contact form handling logic
        // For example: send email, save to database, integrate with CRM, etc.
        
        // Example: Send email to admin
        $to = get_option('admin_email');
        $subject = sprintf('[%s] New Contact Form Submission', get_bloginfo('name'));
        $body = sprintf(
            "Name: %s\nEmail: %s\nMessage:\n%s",
            $name,
            $email,
            $message
        );
        
        $sent = wp_mail($to, $subject, $body, [
            'Reply-To: ' . $email
        ]);
        
        if ($sent) {
            return rest_ensure_response([
                'status' => 'success',
                'message' => 'Your message has been sent successfully!'
            ]);
        } else {
            return new WP_Error('mail_failed', 'Failed to send message', ['status' => 500]);
        }
    }
    
    public function get_menus($request) {
        $menus = wp_get_nav_menus();
        $menu_data = [];
        
        foreach ($menus as $menu) {
            $menu_data[] = [
                'id' => $menu->term_id,
                'name' => $menu->name,
                'slug' => $menu->slug,
                'locations' => array_keys(get_nav_menu_locations(), $menu->term_id)
            ];
        }
        
        return rest_ensure_response($menu_data);
    }
    
    public function get_menu_by_location($request) {
        $location = $request['location'];
        $locations = get_nav_menu_locations();
        
        if (!isset($locations[$location])) {
            return new WP_Error('menu_not_found', 'Menu not found for this location', ['status' => 404]);
        }
        
        $menu_id = $locations[$location];
        $menu_items = wp_get_nav_menu_items($menu_id);
        
        if (!$menu_items) {
            return rest_ensure_response([]);
        }
        
        $menu_tree = $this->build_menu_tree($menu_items);
        
        return rest_ensure_response($menu_tree);
    }
    
    private function prepare_portfolio_item($post) {
        $featured_media = get_post_thumbnail_id($post->ID);
        
        return [
            'id' => $post->ID,
            'title' => get_the_title($post->ID),
            'content' => apply_filters('the_content', $post->post_content),
            'excerpt' => get_the_excerpt($post->ID),
            'slug' => $post->post_name,
            'date' => get_the_date('c', $post->ID),
            'modified' => get_the_modified_date('c', $post->ID),
            'featured_media' => $featured_media ? wp_get_attachment_url($featured_media) : null,
            'meta' => get_post_meta($post->ID),
            'link' => get_permalink($post->ID),
            'status' => $post->post_status
        ];
    }
    
    private function prepare_service_item($post) {
        $featured_media = get_post_thumbnail_id($post->ID);
        
        return [
            'id' => $post->ID,
            'title' => get_the_title($post->ID),
            'content' => apply_filters('the_content', $post->post_content),
            'excerpt' => get_the_excerpt($post->ID),
            'slug' => $post->post_name,
            'featured_media' => $featured_media ? wp_get_attachment_url($featured_media) : null,
            'meta' => get_post_meta($post->ID),
            'menu_order' => $post->menu_order
        ];
    }
    
    private function build_menu_tree($items, $parent_id = 0) {
        $tree = [];
        
        foreach ($items as $item) {
            if ($item->menu_item_parent == $parent_id) {
                $menu_item = [
                    'id' => $item->ID,
                    'title' => $item->title,
                    'url' => $item->url,
                    'target' => $item->target,
                    'classes' => implode(' ', $item->classes),
                    'children' => $this->build_menu_tree($items, $item->ID)
                ];
                $tree[] = $menu_item;
            }
        }
        
        return $tree;
    }
    
    private function get_collection_params() {
        return [
            'page' => [
                'description' => 'Current page of the collection.',
                'type' => 'integer',
                'default' => 1,
                'sanitize_callback' => 'absint',
            ],
            'per_page' => [
                'description' => 'Maximum number of items to be returned in result set.',
                'type' => 'integer',
                'default' => 10,
                'sanitize_callback' => 'absint',
            ],
            'orderby' => [
                'description' => 'Sort collection by post attribute.',
                'type' => 'string',
                'default' => 'date',
                'enum' => ['date', 'title', 'menu_order'],
            ],
            'order' => [
                'description' => 'Order sort attribute ascending or descending.',
                'type' => 'string',
                'default' => 'DESC',
                'enum' => ['ASC', 'DESC'],
            ]
        ];
    }
}

// Initialize custom endpoints
new WP_Custom_API_Endpoints();
