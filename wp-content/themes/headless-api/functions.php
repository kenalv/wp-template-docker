<?php
/**
 * Headless API Theme
 * 
 * Minimal WordPress theme optimized for headless/API usage
 * This theme provides only the essential functions needed for API endpoints
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Theme setup
add_action('after_setup_theme', 'headless_api_setup');

function headless_api_setup() {
    // Add theme support for features needed by API
    add_theme_support('post-thumbnails');
    add_theme_support('title-tag');
    add_theme_support('custom-logo');
    add_theme_support('menus');
    
    // Register navigation menus
    register_nav_menus([
        'primary' => __('Primary Menu'),
        'footer' => __('Footer Menu'),
        'mobile' => __('Mobile Menu')
    ]);
    
    // Add image sizes for API responses
    add_image_size('api-thumbnail', 300, 300, true);
    add_image_size('api-medium', 600, 400, true);
    add_image_size('api-large', 1200, 800, true);
}

// Custom post types for API
add_action('init', 'headless_api_post_types');

function headless_api_post_types() {
    // Portfolio post type
    register_post_type('portfolio', [
        'labels' => [
            'name' => 'Portfolio',
            'singular_name' => 'Portfolio Item',
            'add_new' => 'Add New Item',
            'add_new_item' => 'Add New Portfolio Item',
            'edit_item' => 'Edit Portfolio Item',
            'new_item' => 'New Portfolio Item',
            'view_item' => 'View Portfolio Item',
            'search_items' => 'Search Portfolio',
            'not_found' => 'No portfolio items found',
            'not_found_in_trash' => 'No portfolio items found in trash',
        ],
        'public' => true,
        'show_in_rest' => true, // Enable REST API
        'rest_base' => 'portfolio',
        'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'custom-fields'],
        'has_archive' => true,
        'rewrite' => ['slug' => 'portfolio'],
        'menu_icon' => 'dashicons-portfolio',
        'menu_position' => 5
    ]);
    
    // Services post type
    register_post_type('service', [
        'labels' => [
            'name' => 'Services',
            'singular_name' => 'Service',
            'add_new' => 'Add New Service',
            'add_new_item' => 'Add New Service',
            'edit_item' => 'Edit Service',
            'new_item' => 'New Service',
            'view_item' => 'View Service',
            'search_items' => 'Search Services',
            'not_found' => 'No services found',
            'not_found_in_trash' => 'No services found in trash',
        ],
        'public' => true,
        'show_in_rest' => true,
        'rest_base' => 'services',
        'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'page-attributes', 'custom-fields'],
        'has_archive' => true,
        'rewrite' => ['slug' => 'services'],
        'menu_icon' => 'dashicons-admin-tools',
        'menu_position' => 6
    ]);
    
    // Testimonials post type
    register_post_type('testimonial', [
        'labels' => [
            'name' => 'Testimonials',
            'singular_name' => 'Testimonial',
            'add_new' => 'Add New Testimonial',
            'add_new_item' => 'Add New Testimonial',
            'edit_item' => 'Edit Testimonial',
            'new_item' => 'New Testimonial',
            'view_item' => 'View Testimonial',
            'search_items' => 'Search Testimonials',
            'not_found' => 'No testimonials found',
            'not_found_in_trash' => 'No testimonials found in trash',
        ],
        'public' => true,
        'show_in_rest' => true,
        'rest_base' => 'testimonials',
        'supports' => ['title', 'editor', 'thumbnail', 'custom-fields'],
        'has_archive' => true,
        'rewrite' => ['slug' => 'testimonials'],
        'menu_icon' => 'dashicons-format-quote',
        'menu_position' => 7
    ]);
}

// Custom taxonomies for API
add_action('init', 'headless_api_taxonomies');

function headless_api_taxonomies() {
    // Portfolio categories
    register_taxonomy('portfolio_category', 'portfolio', [
        'labels' => [
            'name' => 'Portfolio Categories',
            'singular_name' => 'Portfolio Category',
            'search_items' => 'Search Categories',
            'all_items' => 'All Categories',
            'parent_item' => 'Parent Category',
            'parent_item_colon' => 'Parent Category:',
            'edit_item' => 'Edit Category',
            'update_item' => 'Update Category',
            'add_new_item' => 'Add New Category',
            'new_item_name' => 'New Category Name',
            'menu_name' => 'Categories',
        ],
        'public' => true,
        'show_in_rest' => true,
        'rest_base' => 'portfolio_categories',
        'hierarchical' => true,
        'rewrite' => ['slug' => 'portfolio-category'],
    ]);
    
    // Service categories
    register_taxonomy('service_category', 'service', [
        'labels' => [
            'name' => 'Service Categories',
            'singular_name' => 'Service Category',
            'search_items' => 'Search Categories',
            'all_items' => 'All Categories',
            'parent_item' => 'Parent Category',
            'parent_item_colon' => 'Parent Category:',
            'edit_item' => 'Edit Category',
            'update_item' => 'Update Category',
            'add_new_item' => 'Add New Category',
            'new_item_name' => 'New Category Name',
            'menu_name' => 'Categories',
        ],
        'public' => true,
        'show_in_rest' => true,
        'rest_base' => 'service_categories',
        'hierarchical' => true,
        'rewrite' => ['slug' => 'service-category'],
    ]);
}

// API enhancements
add_action('rest_api_init', 'headless_api_rest_enhancements');

function headless_api_rest_enhancements() {
    // Add custom fields to post responses
    register_rest_field(['post', 'page', 'portfolio', 'service', 'testimonial'], 'meta_fields', [
        'get_callback' => 'get_post_meta_for_api',
        'schema' => [
            'description' => 'Post meta fields',
            'type' => 'object'
        ]
    ]);
    
    // Add featured image URL to responses
    register_rest_field(['post', 'page', 'portfolio', 'service', 'testimonial'], 'featured_image_url', [
        'get_callback' => 'get_featured_image_url',
        'schema' => [
            'description' => 'Featured image URL',
            'type' => 'string'
        ]
    ]);
    
    // Add author info to responses
    register_rest_field(['post', 'portfolio', 'testimonial'], 'author_info', [
        'get_callback' => 'get_author_info_for_api',
        'schema' => [
            'description' => 'Author information',
            'type' => 'object'
        ]
    ]);
}

function get_post_meta_for_api($post) {
    $meta = get_post_meta($post['id']);
    $cleaned_meta = [];
    
    foreach ($meta as $key => $value) {
        // Skip private meta fields
        if (substr($key, 0, 1) !== '_') {
            $cleaned_meta[$key] = is_array($value) && count($value) === 1 ? $value[0] : $value;
        }
    }
    
    return $cleaned_meta;
}

function get_featured_image_url($post) {
    $image_id = get_post_thumbnail_id($post['id']);
    if (!$image_id) {
        return null;
    }
    
    $image_urls = [];
    $sizes = ['thumbnail', 'medium', 'large', 'full', 'api-thumbnail', 'api-medium', 'api-large'];
    
    foreach ($sizes as $size) {
        $image_url = wp_get_attachment_image_url($image_id, $size);
        if ($image_url) {
            $image_urls[$size] = $image_url;
        }
    }
    
    return $image_urls;
}

function get_author_info_for_api($post) {
    $author_id = $post['author'];
    $author = get_userdata($author_id);
    
    if (!$author) {
        return null;
    }
    
    return [
        'id' => $author->ID,
        'name' => $author->display_name,
        'avatar' => get_avatar_url($author->ID),
        'bio' => get_user_meta($author->ID, 'description', true),
        'url' => $author->user_url
    ];
}

// Remove unnecessary head elements for API-only usage
remove_action('wp_head', 'wp_generator');
remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('wp_head', 'wp_print_styles', 8);
remove_action('wp_head', 'wp_print_head_scripts', 9);
remove_action('wp_head', 'feed_links', 2);
remove_action('wp_head', 'feed_links_extra', 3);
remove_action('wp_head', 'rsd_link');
remove_action('wp_head', 'wlwmanifest_link');

// Customize login for API users
add_filter('login_redirect', function($redirect_to, $request, $user) {
    if (!is_wp_error($user) && isset($user->roles) && in_array('api_user', $user->roles)) {
        return home_url('/wp-json/');
    }
    return $redirect_to;
}, 10, 3);
