<?php
/**
 * JWT Authentication for WordPress API
 * 
 * Provides JWT token-based authentication for headless WordPress
 * Based on firebase/jwt library integration
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WP_JWT_Authentication {
    
    private $secret_key;
    
    public function __construct() {
        $this->secret_key = defined('JWT_AUTH_SECRET_KEY') ? JWT_AUTH_SECRET_KEY : wp_salt('auth');
        
        add_action('rest_api_init', [$this, 'register_endpoints']);
        add_filter('rest_authentication_errors', [$this, 'jwt_auth_handler']);
        add_action('init', [$this, 'add_cors_support']);
    }
    
    public function register_endpoints() {
        $namespace = 'jwt-auth/v1';
        
        // Token generation endpoint
        register_rest_route($namespace, '/token', [
            'methods' => 'POST',
            'callback' => [$this, 'generate_token'],
            'permission_callback' => '__return_true',
            'args' => [
                'username' => [
                    'required' => true,
                    'validate_callback' => function($param, $request, $key) {
                        return !empty($param);
                    }
                ],
                'password' => [
                    'required' => true,
                    'validate_callback' => function($param, $request, $key) {
                        return !empty($param);
                    }
                ]
            ]
        ]);
        
        // Token validation endpoint
        register_rest_route($namespace, '/validate', [
            'methods' => 'POST',
            'callback' => [$this, 'validate_token'],
            'permission_callback' => '__return_true'
        ]);
        
        // Token refresh endpoint
        register_rest_route($namespace, '/refresh', [
            'methods' => 'POST',
            'callback' => [$this, 'refresh_token'],
            'permission_callback' => [$this, 'jwt_auth_permission']
        ]);
        
        // User info endpoint (requires valid token)
        register_rest_route($namespace, '/me', [
            'methods' => 'GET',
            'callback' => [$this, 'get_user_info'],
            'permission_callback' => [$this, 'jwt_auth_permission']
        ]);
    }
    
    public function generate_token($request) {
        $username = $request->get_param('username');
        $password = $request->get_param('password');
        
        // Authenticate user
        $user = wp_authenticate($username, $password);
        
        if (is_wp_error($user)) {
            return new WP_Error('invalid_credentials', 'Invalid username or password', ['status' => 401]);
        }
        
        // Generate token
        $issued_at = time();
        $expiration = $issued_at + (DAY_IN_SECONDS * 7); // 7 days
        
        $token_data = [
            'iss' => home_url(),
            'iat' => $issued_at,
            'exp' => $expiration,
            'data' => [
                'user' => [
                    'id' => $user->ID,
                    'username' => $user->user_login,
                    'email' => $user->user_email,
                    'roles' => $user->roles
                ]
            ]
        ];
        
        $token = $this->jwt_encode($token_data);
        
        if (!$token) {
            return new WP_Error('token_generation_failed', 'Could not generate token', ['status' => 500]);
        }
        
        return rest_ensure_response([
            'token' => $token,
            'user_id' => $user->ID,
            'user_login' => $user->user_login,
            'user_email' => $user->user_email,
            'user_roles' => $user->roles,
            'expires' => $expiration
        ]);
    }
    
    public function validate_token($request) {
        $token = $this->get_token_from_request($request);
        
        if (!$token) {
            return new WP_Error('no_token', 'No token provided', ['status' => 400]);
        }
        
        $decoded = $this->jwt_decode($token);
        
        if (is_wp_error($decoded)) {
            return $decoded;
        }
        
        return rest_ensure_response([
            'valid' => true,
            'user_id' => $decoded->data->user->id,
            'expires' => $decoded->exp
        ]);
    }
    
    public function refresh_token($request) {
        $current_user = wp_get_current_user();
        
        if (!$current_user || !$current_user->ID) {
            return new WP_Error('invalid_token', 'Invalid token', ['status' => 401]);
        }
        
        // Generate new token
        $issued_at = time();
        $expiration = $issued_at + (DAY_IN_SECONDS * 7); // 7 days
        
        $token_data = [
            'iss' => home_url(),
            'iat' => $issued_at,
            'exp' => $expiration,
            'data' => [
                'user' => [
                    'id' => $current_user->ID,
                    'username' => $current_user->user_login,
                    'email' => $current_user->user_email,
                    'roles' => $current_user->roles
                ]
            ]
        ];
        
        $token = $this->jwt_encode($token_data);
        
        return rest_ensure_response([
            'token' => $token,
            'expires' => $expiration
        ]);
    }
    
    public function get_user_info($request) {
        $current_user = wp_get_current_user();
        
        return rest_ensure_response([
            'id' => $current_user->ID,
            'username' => $current_user->user_login,
            'email' => $current_user->user_email,
            'display_name' => $current_user->display_name,
            'roles' => $current_user->roles,
            'capabilities' => array_keys($current_user->allcaps, true)
        ]);
    }
    
    public function jwt_auth_handler($result) {
        if (!empty($result)) {
            return $result;
        }
        
        $token = $this->get_token_from_headers();
        
        if (!$token) {
            return $result; // No token, continue with other auth methods
        }
        
        $decoded = $this->jwt_decode($token);
        
        if (is_wp_error($decoded)) {
            return $decoded;
        }
        
        // Set current user
        $user_id = $decoded->data->user->id;
        wp_set_current_user($user_id);
        
        return true;
    }
    
    public function jwt_auth_permission() {
        return wp_get_current_user()->ID > 0;
    }
    
    public function add_cors_support() {
        if (defined('JWT_AUTH_CORS_ENABLE') && JWT_AUTH_CORS_ENABLE) {
            add_filter('rest_pre_serve_request', function($served, $result, $request, $server) {
                header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With');
                return $served;
            }, 15, 4);
        }
    }
    
    private function get_token_from_headers() {
        $auth_header = null;
        
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth_header = $_SERVER['HTTP_AUTHORIZATION'];
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $auth_header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        } elseif (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            if (isset($headers['Authorization'])) {
                $auth_header = $headers['Authorization'];
            }
        }
        
        if (!$auth_header) {
            return null;
        }
        
        list($token) = sscanf($auth_header, 'Bearer %s');
        
        return $token;
    }
    
    private function get_token_from_request($request) {
        $token = $this->get_token_from_headers();
        
        if (!$token) {
            $token = $request->get_param('token');
        }
        
        return $token;
    }
    
    private function jwt_encode($payload) {
        // Simple JWT implementation - in production, use firebase/jwt library
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode($payload);
        
        $base64_header = $this->base64url_encode($header);
        $base64_payload = $this->base64url_encode($payload);
        
        $signature = hash_hmac('sha256', $base64_header . '.' . $base64_payload, $this->secret_key, true);
        $base64_signature = $this->base64url_encode($signature);
        
        return $base64_header . '.' . $base64_payload . '.' . $base64_signature;
    }
    
    private function jwt_decode($token) {
        $token_parts = explode('.', $token);
        
        if (count($token_parts) !== 3) {
            return new WP_Error('invalid_token', 'Invalid token format', ['status' => 401]);
        }
        
        list($base64_header, $base64_payload, $base64_signature) = $token_parts;
        
        // Verify signature
        $signature = $this->base64url_decode($base64_signature);
        $expected_signature = hash_hmac('sha256', $base64_header . '.' . $base64_payload, $this->secret_key, true);
        
        if (!hash_equals($expected_signature, $signature)) {
            return new WP_Error('invalid_token', 'Invalid token signature', ['status' => 401]);
        }
        
        // Decode payload
        $payload = json_decode($this->base64url_decode($base64_payload));
        
        if (!$payload) {
            return new WP_Error('invalid_token', 'Invalid token payload', ['status' => 401]);
        }
        
        // Check expiration
        if (isset($payload->exp) && $payload->exp < time()) {
            return new WP_Error('token_expired', 'Token has expired', ['status' => 401]);
        }
        
        return $payload;
    }
    
    private function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    private function base64url_decode($data) {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }
}

// Initialize JWT authentication
new WP_JWT_Authentication();
