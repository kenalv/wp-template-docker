<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php bloginfo('name'); ?> - Headless WordPress API</title>
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>

<div class="api-info">
    <h1><?php bloginfo('name'); ?> REST API</h1>
    
    <p>This is a headless WordPress installation optimized for REST API usage. The frontend is handled by external applications.</p>
    
    <h2>Available Endpoints</h2>
    
    <div class="api-endpoint method-get">
        <h3>GET /wp-json/wp/v2/posts</h3>
        <p>Retrieve blog posts</p>
    </div>
    
    <div class="api-endpoint method-get">
        <h3>GET /wp-json/wp/v2/pages</h3>
        <p>Retrieve pages</p>
    </div>
    
    <div class="api-endpoint method-get">
        <h3>GET /wp-json/custom/v1/portfolio</h3>
        <p>Retrieve portfolio items</p>
    </div>
    
    <div class="api-endpoint method-get">
        <h3>GET /wp-json/custom/v1/services</h3>
        <p>Retrieve services</p>
    </div>
    
    <div class="api-endpoint method-post">
        <h3>POST /wp-json/custom/v1/contact</h3>
        <p>Submit contact form</p>
        <code>{ "name": "John Doe", "email": "john@example.com", "message": "Hello!" }</code>
    </div>
    
    <div class="api-endpoint method-post">
        <h3>POST /wp-json/jwt-auth/v1/token</h3>
        <p>Generate JWT authentication token</p>
        <code>{ "username": "admin", "password": "password" }</code>
    </div>
    
    <div class="api-endpoint method-get">
        <h3>GET /wp-json/custom/v1/health</h3>
        <p>API health check</p>
    </div>
    
    <h2>Authentication</h2>
    <p>Use JWT tokens for authenticated requests:</p>
    <pre><code>Authorization: Bearer YOUR_JWT_TOKEN</code></pre>
    
    <h2>Admin Access</h2>
    <p><a href="<?php echo admin_url(); ?>">WordPress Admin</a></p>
    
    <h2>API Documentation</h2>
    <p><a href="<?php echo rest_url(); ?>">Browse API</a></p>
</div>

<?php wp_footer(); ?>
</body>
</html>
