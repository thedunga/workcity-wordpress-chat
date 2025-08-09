<?php
/**
 * Chat REST API
 * 
 * Handles REST API endpoints for the chat system
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WorkCity_Chat_API {
    
    /**
     * Single instance
     */
    private static $instance = null;
    
    /**
     * API namespace
     */
    const NAMESPACE = 'workcity-chat/v1';
    
    /**
     * Get instance
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Chat sessions
        register_rest_route(self::NAMESPACE, '/sessions', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_chat_sessions'),
                'permission_callback' => array($this, 'check_permission'),
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_chat_session'),
                'permission_callback' => array($this, 'check_permission'),
                'args' => array(
                    'title' => array(
                        'required' => true,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'chat_type' => array(
                        'required' => true,
                        'type' => 'string',
                        'enum' => array('general', 'product', 'order', 'design', 'merchant'),
                    ),
                    'product_id' => array(
                        'type' => 'integer',
                        'minimum' => 1,
                    ),
                    'participants' => array(
                        'type' => 'array',
                        'items' => array(
                            'type' => 'object',
                            'properties' => array(
                                'user_id' => array('type' => 'integer'),
                                'role' => array('type' => 'string'),
                            ),
                        ),
                    ),
                ),
            ),
        ));
        
        // Single chat session
        register_rest_route(self::NAMESPACE, '/sessions/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_chat_session'),
                'permission_callback' => array($this, 'check_session_permission'),
                'args' => array(
                    'id' => array(
                        'validate_callback' => function($param, $request, $key) {
                            return is_numeric($param);
                        }
                    ),
                ),
            ),
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_chat_session'),
                'permission_callback' => array($this, 'check_session_permission'),
            ),
        ));
        
        // Messages
        register_rest_route(self::NAMESPACE, '/sessions/(?P<session_id>\d+)/messages', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_messages'),
                'permission_callback' => array($this, 'check_session_permission'),
                'args' => array(
                    'page' => array(
                        'default' => 1,
                        'type' => 'integer',
                        'minimum' => 1,
                    ),
                    'per_page' => array(
                        'default' => 20,
                        'type' => 'integer',
                        'minimum' => 1,
                        'maximum' => 100,
                    ),
                ),
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'send_message'),
                'permission_callback' => array($this, 'check_session_permission'),
                'args' => array(
                    'message_content' => array(
                        'required' => true,
                        'type' => 'string',
                        'sanitize_callback' => 'wp_kses_post',
                    ),
                    'message_type' => array(
                        'default' => 'text',
                        'type' => 'string',
                        'enum' => array('text', 'image', 'file'),
                    ),
                    'attachment_url' => array(
                        'type' => 'string',
                        'format' => 'uri',
                    ),
                ),
            ),
        ));
        
        // Mark messages as read
        register_rest_route(self::NAMESPACE, '/sessions/(?P<session_id>\d+)/read', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'mark_messages_read'),
            'permission_callback' => array($this, 'check_session_permission'),
        ));
        
        // User status
        register_rest_route(self::NAMESPACE, '/status', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'update_user_status'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'session_id' => array(
                    'required' => true,
                    'type' => 'integer',
                ),
                'status' => array(
                    'required' => true,
                    'type' => 'string',
                    'enum' => array('online', 'typing', 'away'),
                ),
            ),
        ));
        
        // File upload
        register_rest_route(self::NAMESPACE, '/upload', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'upload_file'),
            'permission_callback' => array($this, 'check_permission'),
        ));
    }
    
    /**
     * Get chat sessions for current user
     */
    public function get_chat_sessions($request) {
        global $wpdb;
        
        $user_id = get_current_user_id();
        
        // Get sessions where user is a participant
        $sessions = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT cs.ID, cs.post_title, cs.post_date, cs.post_status,
                    m1._chat_type as chat_type,
                    m2._chat_status as chat_status,
                    m3._product_id as product_id
             FROM {$wpdb->posts} cs
             JOIN {$wpdb->prefix}workcity_chat_participants p ON cs.ID = p.chat_session_id
             LEFT JOIN {$wpdb->postmeta} m1 ON cs.ID = m1.post_id AND m1.meta_key = '_chat_type'
             LEFT JOIN {$wpdb->postmeta} m2 ON cs.ID = m2.post_id AND m2.meta_key = '_chat_status'
             LEFT JOIN {$wpdb->postmeta} m3 ON cs.ID = m3.post_id AND m3.meta_key = '_product_id'
             WHERE cs.post_type = 'chat_session' 
             AND p.user_id = %d 
             AND p.is_active = 1
             ORDER BY cs.post_date DESC",
            $user_id
        ));
        
        $formatted_sessions = array();
        
        foreach ($sessions as $session) {
            // Get last message
            $last_message = $wpdb->get_row($wpdb->prepare(
                "SELECT message_content, created_at, sender_id 
                 FROM {$wpdb->prefix}workcity_chat_messages 
                 WHERE chat_session_id = %d 
                 ORDER BY created_at DESC LIMIT 1",
                $session->ID
            ));
            
            // Get unread count
            $unread_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) 
                 FROM {$wpdb->prefix}workcity_chat_messages 
                 WHERE chat_session_id = %d 
                 AND sender_id != %d 
                 AND is_read = 0",
                $session->ID,
                $user_id
            ));
            
            // Get participants
            $participants = $wpdb->get_results($wpdb->prepare(
                "SELECT p.user_id, p.role, u.display_name, u.user_email
                 FROM {$wpdb->prefix}workcity_chat_participants p
                 JOIN {$wpdb->users} u ON p.user_id = u.ID
                 WHERE p.chat_session_id = %d AND p.is_active = 1",
                $session->ID
            ));
            
            $formatted_sessions[] = array(
                'id' => $session->ID,
                'title' => $session->post_title,
                'chat_type' => $session->chat_type,
                'status' => $session->chat_status,
                'product_id' => $session->product_id,
                'created_at' => $session->post_date,
                'last_message' => $last_message ? array(
                    'content' => $last_message->message_content,
                    'created_at' => $last_message->created_at,
                    'sender_id' => $last_message->sender_id,
                ) : null,
                'unread_count' => intval($unread_count),
                'participants' => $participants,
            );
        }
        
        return rest_ensure_response($formatted_sessions);
    }
    
    /**
     * Create new chat session
     */
    public function create_chat_session($request) {
        global $wpdb;
        
        $user_id = get_current_user_id();
        $title = $request->get_param('title');
        $chat_type = $request->get_param('chat_type');
        $product_id = $request->get_param('product_id');
        $participants = $request->get_param('participants') ?: array();
        
        // Create chat session post
        $session_id = wp_insert_post(array(
            'post_title' => $title,
            'post_type' => 'chat_session',
            'post_status' => 'publish',
            'post_author' => $user_id,
        ));
        
        if (is_wp_error($session_id)) {
            return new WP_Error('create_failed', 'Failed to create chat session', array('status' => 500));
        }
        
        // Save meta data
        update_post_meta($session_id, '_chat_type', $chat_type);
        update_post_meta($session_id, '_chat_status', 'active');
        update_post_meta($session_id, '_chat_priority', 'normal');
        
        if ($product_id) {
            update_post_meta($session_id, '_product_id', $product_id);
        }
        
        // Add current user as participant
        $wpdb->insert(
            $wpdb->prefix . 'workcity_chat_participants',
            array(
                'chat_session_id' => $session_id,
                'user_id' => $user_id,
                'role' => 'customer',
                'joined_at' => current_time('mysql'),
                'last_seen' => current_time('mysql'),
                'is_active' => 1,
            ),
            array('%d', '%d', '%s', '%s', '%s', '%d')
        );
        
        // Add other participants
        foreach ($participants as $participant) {
            if (isset($participant['user_id']) && isset($participant['role'])) {
                $wpdb->insert(
                    $wpdb->prefix . 'workcity_chat_participants',
                    array(
                        'chat_session_id' => $session_id,
                        'user_id' => $participant['user_id'],
                        'role' => $participant['role'],
                        'joined_at' => current_time('mysql'),
                        'last_seen' => current_time('mysql'),
                        'is_active' => 1,
                    ),
                    array('%d', '%d', '%s', '%s', '%s', '%d')
                );
            }
        }
        
        return rest_ensure_response(array(
            'id' => $session_id,
            'message' => 'Chat session created successfully',
        ));
    }
    
    /**
     * Get single chat session
     */
    public function get_chat_session($request) {
        $session_id = $request->get_param('id');
        $user_id = get_current_user_id();
        
        // Update last seen
        $this->update_last_seen($session_id, $user_id);
        
        // Get session data
        $session = get_post($session_id);
        if (!$session || $session->post_type !== 'chat_session') {
            return new WP_Error('not_found', 'Chat session not found', array('status' => 404));
        }
        
        return rest_ensure_response(array(
            'id' => $session->ID,
            'title' => $session->post_title,
            'chat_type' => get_post_meta($session->ID, '_chat_type', true),
            'status' => get_post_meta($session->ID, '_chat_status', true),
            'product_id' => get_post_meta($session->ID, '_product_id', true),
            'created_at' => $session->post_date,
        ));
    }
    
    /**
     * Get messages for a chat session
     */
    public function get_messages($request) {
        global $wpdb;
        
        $session_id = $request->get_param('session_id');
        $page = $request->get_param('page');
        $per_page = $request->get_param('per_page');
        $offset = ($page - 1) * $per_page;
        
        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT m.*, u.display_name, u.user_email
             FROM {$wpdb->prefix}workcity_chat_messages m
             JOIN {$wpdb->users} u ON m.sender_id = u.ID
             WHERE m.chat_session_id = %d
             ORDER BY m.created_at DESC
             LIMIT %d OFFSET %d",
            $session_id,
            $per_page,
            $offset
        ));
        
        // Reverse the order for proper display
        $messages = array_reverse($messages);
        
        $formatted_messages = array();
        foreach ($messages as $message) {
            $formatted_messages[] = array(
                'id' => $message->id,
                'content' => $message->message_content,
                'type' => $message->message_type,
                'attachment_url' => $message->attachment_url,
                'sender' => array(
                    'id' => $message->sender_id,
                    'name' => $message->display_name,
                    'email' => $message->user_email,
                ),
                'is_read' => (bool) $message->is_read,
                'created_at' => $message->created_at,
            );
        }
        
        return rest_ensure_response($formatted_messages);
    }
    
    /**
     * Send a message
     */
    public function send_message($request) {
        global $wpdb;
        
        $session_id = $request->get_param('session_id');
        $user_id = get_current_user_id();
        $content = $request->get_param('message_content');
        $type = $request->get_param('message_type');
        $attachment_url = $request->get_param('attachment_url');
        
        // Insert message
        $result = $wpdb->insert(
            $wpdb->prefix . 'workcity_chat_messages',
            array(
                'chat_session_id' => $session_id,
                'sender_id' => $user_id,
                'message_content' => $content,
                'message_type' => $type,
                'attachment_url' => $attachment_url,
                'is_read' => 0,
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%d', '%s', '%s', '%s', '%d', '%s')
        );
        
        if ($result === false) {
            return new WP_Error('send_failed', 'Failed to send message', array('status' => 500));
        }
        
        $message_id = $wpdb->insert_id;
        
        // Update last seen
        $this->update_last_seen($session_id, $user_id);
        
        // Trigger notification action
        do_action('workcity_chat_message_sent', $message_id, $session_id, $user_id);
        
        return rest_ensure_response(array(
            'id' => $message_id,
            'message' => 'Message sent successfully',
        ));
    }
    
    /**
     * Mark messages as read
     */
    public function mark_messages_read($request) {
        global $wpdb;
        
        $session_id = $request->get_param('session_id');
        $user_id = get_current_user_id();
        
        // Mark all messages in this session as read for this user
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}workcity_chat_messages 
             SET is_read = 1 
             WHERE chat_session_id = %d 
             AND sender_id != %d 
             AND is_read = 0",
            $session_id,
            $user_id
        ));
        
        return rest_ensure_response(array('message' => 'Messages marked as read'));
    }
    
    /**
     * Update user status
     */
    public function update_user_status($request) {
        global $wpdb;
        
        $session_id = $request->get_param('session_id');
        $user_id = get_current_user_id();
        $status = $request->get_param('status');
        
        // Update last seen and status
        $this->update_last_seen($session_id, $user_id);
        
        // Store status in transient (expires after 30 seconds)
        set_transient("workcity_chat_status_{$session_id}_{$user_id}", $status, 30);
        
        return rest_ensure_response(array('message' => 'Status updated'));
    }
    
    /**
     * Upload file
     */
    public function upload_file($request) {
        if (!function_exists('wp_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        
        $files = $request->get_file_params();
        
        if (empty($files['file'])) {
            return new WP_Error('no_file', 'No file provided', array('status' => 400));
        }
        
        $file = $files['file'];
        
        // Check file type
        $allowed_types = array('jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt');
        $file_type = wp_check_filetype($file['name']);
        
        if (!in_array($file_type['ext'], $allowed_types)) {
            return new WP_Error('invalid_file_type', 'File type not allowed', array('status' => 400));
        }
        
        // Set upload directory
        $upload_dir = wp_upload_dir();
        $chat_dir = $upload_dir['basedir'] . '/workcity-chat';
        
        if (!file_exists($chat_dir)) {
            wp_mkdir_p($chat_dir);
        }
        
        // Handle upload
        $upload_overrides = array(
            'test_form' => false,
            'upload_path' => $chat_dir,
        );
        
        $uploaded_file = wp_handle_upload($file, $upload_overrides);
        
        if (isset($uploaded_file['error'])) {
            return new WP_Error('upload_failed', $uploaded_file['error'], array('status' => 500));
        }
        
        return rest_ensure_response(array(
            'url' => $uploaded_file['url'],
            'file' => $uploaded_file['file'],
        ));
    }
    
    /**
     * Update last seen timestamp
     */
    private function update_last_seen($session_id, $user_id) {
        global $wpdb;
        
        $wpdb->update(
            $wpdb->prefix . 'workcity_chat_participants',
            array('last_seen' => current_time('mysql')),
            array('chat_session_id' => $session_id, 'user_id' => $user_id),
            array('%s'),
            array('%d', '%d')
        );
    }
    
    /**
     * Check general permission
     */
    public function check_permission() {
        return is_user_logged_in();
    }
    
    /**
     * Check session-specific permission
     */
    public function check_session_permission($request) {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $session_id = $request->get_param('session_id') ?: $request->get_param('id');
        if (!$session_id) {
            return false;
        }
        
        global $wpdb;
        $user_id = get_current_user_id();
        
        // Check if user is participant in this session
        $is_participant = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}workcity_chat_participants 
             WHERE chat_session_id = %d AND user_id = %d AND is_active = 1",
            $session_id,
            $user_id
        ));
        
        return $is_participant > 0 || current_user_can('manage_options');
    }
}