<?php
/**
 * Chat AJAX Handler
 * 
 * Handles AJAX requests for real-time chat functionality
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WorkCity_Chat_AJAX {
    
    /**
     * Single instance
     */
    private static $instance = null;
    
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
        add_action('wp_ajax_workcity_chat_get_messages', array($this, 'get_messages'));
        add_action('wp_ajax_workcity_chat_send_message', array($this, 'send_message'));
        add_action('wp_ajax_workcity_chat_mark_read', array($this, 'mark_messages_read'));
        add_action('wp_ajax_workcity_chat_update_status', array($this, 'update_user_status'));
        add_action('wp_ajax_workcity_chat_get_sessions', array($this, 'get_chat_sessions'));
        add_action('wp_ajax_workcity_chat_create_session', array($this, 'create_chat_session'));
        add_action('wp_ajax_workcity_chat_upload_file', array($this, 'upload_file'));
        add_action('wp_ajax_workcity_chat_get_online_users', array($this, 'get_online_users'));
        
        // Public AJAX for non-logged in users (for guest chat if needed)
        add_action('wp_ajax_nopriv_workcity_chat_check_support', array($this, 'check_support_availability'));
    }
    
    /**
     * Get messages for a chat session
     */
    public function get_messages() {
        check_ajax_referer('workcity_chat_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_die('Unauthorized', 401);
        }
        
        global $wpdb;
        
        $session_id = intval($_POST['session_id']);
        $last_message_id = intval($_POST['last_message_id'] ?? 0);
        $user_id = get_current_user_id();
        
        // Verify user can access this session
        if (!$this->can_access_session($session_id, $user_id)) {
            wp_die('Forbidden', 403);
        }
        
        // Get new messages since last check
        $where_clause = $last_message_id > 0 ? 
            $wpdb->prepare("AND m.id > %d", $last_message_id) : "";
        
        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT m.*, u.display_name, u.user_email, um.meta_value as avatar_url
             FROM {$wpdb->prefix}workcity_chat_messages m
             JOIN {$wpdb->users} u ON m.sender_id = u.ID
             LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = 'workcity_chat_avatar'
             WHERE m.chat_session_id = %d {$where_clause}
             ORDER BY m.created_at ASC
             LIMIT 50",
            $session_id
        ));
        
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
                    'avatar' => $message->avatar_url ?: $this->get_gravatar_url($message->user_email),
                    'is_current_user' => $message->sender_id == $user_id,
                ),
                'is_read' => (bool) $message->is_read,
                'created_at' => $message->created_at,
                'formatted_time' => $this->format_message_time($message->created_at),
            );
        }
        
        // Update last seen
        $this->update_last_seen($session_id, $user_id);
        
        wp_send_json_success(array(
            'messages' => $formatted_messages,
            'last_message_id' => !empty($messages) ? end($messages)->id : $last_message_id,
        ));
    }
    
    /**
     * Send a message
     */
    public function send_message() {
        check_ajax_referer('workcity_chat_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_die('Unauthorized', 401);
        }
        
        global $wpdb;
        
        $session_id = intval($_POST['session_id']);
        $content = wp_kses_post($_POST['message_content']);
        $type = sanitize_text_field($_POST['message_type'] ?? 'text');
        $attachment_url = esc_url_raw($_POST['attachment_url'] ?? '');
        $user_id = get_current_user_id();
        
        // Verify user can access this session
        if (!$this->can_access_session($session_id, $user_id)) {
            wp_die('Forbidden', 403);
        }
        
        // Validate message content
        if (empty($content) && empty($attachment_url)) {
            wp_send_json_error('Message content cannot be empty');
        }
        
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
            wp_send_json_error('Failed to send message');
        }
        
        $message_id = $wpdb->insert_id;
        
        // Update last seen
        $this->update_last_seen($session_id, $user_id);
        
        // Get the full message data to return
        $message = $wpdb->get_row($wpdb->prepare(
            "SELECT m.*, u.display_name, u.user_email
             FROM {$wpdb->prefix}workcity_chat_messages m
             JOIN {$wpdb->users} u ON m.sender_id = u.ID
             WHERE m.id = %d",
            $message_id
        ));
        
        $formatted_message = array(
            'id' => $message->id,
            'content' => $message->message_content,
            'type' => $message->message_type,
            'attachment_url' => $message->attachment_url,
            'sender' => array(
                'id' => $message->sender_id,
                'name' => $message->display_name,
                'email' => $message->user_email,
                'avatar' => $this->get_gravatar_url($message->user_email),
                'is_current_user' => true,
            ),
            'is_read' => false,
            'created_at' => $message->created_at,
            'formatted_time' => $this->format_message_time($message->created_at),
        );
        
        // Trigger notification action
        do_action('workcity_chat_message_sent', $message_id, $session_id, $user_id);
        
        wp_send_json_success(array(
            'message' => $formatted_message,
            'success_message' => 'Message sent successfully',
        ));
    }
    
    /**
     * Mark messages as read
     */
    public function mark_messages_read() {
        check_ajax_referer('workcity_chat_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_die('Unauthorized', 401);
        }
        
        global $wpdb;
        
        $session_id = intval($_POST['session_id']);
        $user_id = get_current_user_id();
        
        // Verify user can access this session
        if (!$this->can_access_session($session_id, $user_id)) {
            wp_die('Forbidden', 403);
        }
        
        // Mark messages as read
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}workcity_chat_messages 
             SET is_read = 1 
             WHERE chat_session_id = %d 
             AND sender_id != %d 
             AND is_read = 0",
            $session_id,
            $user_id
        ));
        
        wp_send_json_success(array(
            'updated_count' => $updated,
            'message' => 'Messages marked as read',
        ));
    }
    
    /**
     * Update user status (typing, online, etc.)
     */
    public function update_user_status() {
        check_ajax_referer('workcity_chat_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_die('Unauthorized', 401);
        }
        
        $session_id = intval($_POST['session_id']);
        $status = sanitize_text_field($_POST['status']);
        $user_id = get_current_user_id();
        
        // Verify user can access this session
        if (!$this->can_access_session($session_id, $user_id)) {
            wp_die('Forbidden', 403);
        }
        
        // Valid statuses
        $valid_statuses = array('online', 'typing', 'away', 'offline');
        if (!in_array($status, $valid_statuses)) {
            wp_send_json_error('Invalid status');
        }
        
        // Update last seen
        $this->update_last_seen($session_id, $user_id);
        
        // Store status in transient (expires after 30 seconds for typing, 5 minutes for others)
        $expiry = $status === 'typing' ? 30 : 300;
        set_transient("workcity_chat_status_{$session_id}_{$user_id}", $status, $expiry);
        
        wp_send_json_success(array(
            'status' => $status,
            'message' => 'Status updated',
        ));
    }
    
    /**
     * Get chat sessions for current user
     */
    public function get_chat_sessions() {
        check_ajax_referer('workcity_chat_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_die('Unauthorized', 401);
        }
        
        global $wpdb;
        
        $user_id = get_current_user_id();
        
        // Get sessions where user is a participant
        $sessions = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT cs.ID, cs.post_title, cs.post_date, cs.post_status,
                    m1.meta_value as chat_type,
                    m2.meta_value as chat_status,
                    m3.meta_value as product_id
             FROM {$wpdb->posts} cs
             JOIN {$wpdb->prefix}workcity_chat_participants p ON cs.ID = p.chat_session_id
             LEFT JOIN {$wpdb->postmeta} m1 ON cs.ID = m1.post_id AND m1.meta_key = '_chat_type'
             LEFT JOIN {$wpdb->postmeta} m2 ON cs.ID = m2.post_id AND m2.meta_key = '_chat_status'
             LEFT JOIN {$wpdb->postmeta} m3 ON cs.ID = m3.post_id AND m3.meta_key = '_product_id'
             WHERE cs.post_type = 'chat_session' 
             AND p.user_id = %d 
             AND p.is_active = 1
             AND cs.post_status = 'publish'
             ORDER BY cs.post_date DESC",
            $user_id
        ));
        
        $formatted_sessions = array();
        
        foreach ($sessions as $session) {
            // Get last message
            $last_message = $wpdb->get_row($wpdb->prepare(
                "SELECT message_content, created_at, sender_id, u.display_name
                 FROM {$wpdb->prefix}workcity_chat_messages m
                 JOIN {$wpdb->users} u ON m.sender_id = u.ID
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
                'formatted_date' => human_time_diff(strtotime($session->post_date), current_time('timestamp')) . ' ago',
                'last_message' => $last_message ? array(
                    'content' => $this->truncate_message($last_message->message_content, 50),
                    'created_at' => $last_message->created_at,
                    'sender_id' => $last_message->sender_id,
                    'sender_name' => $last_message->display_name,
                    'formatted_time' => human_time_diff(strtotime($last_message->created_at), current_time('timestamp')) . ' ago',
                ) : null,
                'unread_count' => intval($unread_count),
                'participants' => $participants,
            );
        }
        
        wp_send_json_success($formatted_sessions);
    }
    
    /**
     * Create new chat session
     */
    public function create_chat_session() {
        check_ajax_referer('workcity_chat_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_die('Unauthorized', 401);
        }
        
        global $wpdb;
        
        $user_id = get_current_user_id();
        $title = sanitize_text_field($_POST['title']);
        $chat_type = sanitize_text_field($_POST['chat_type']);
        $product_id = intval($_POST['product_id'] ?? 0);
        
        // Validate required fields
        if (empty($title) || empty($chat_type)) {
            wp_send_json_error('Title and chat type are required');
        }
        
        // Create chat session post
        $session_id = wp_insert_post(array(
            'post_title' => $title,
            'post_type' => 'chat_session',
            'post_status' => 'publish',
            'post_author' => $user_id,
        ));
        
        if (is_wp_error($session_id)) {
            wp_send_json_error('Failed to create chat session');
        }
        
        // Save meta data
        update_post_meta($session_id, '_chat_type', $chat_type);
        update_post_meta($session_id, '_chat_status', 'active');
        update_post_meta($session_id, '_chat_priority', 'normal');
        
        if ($product_id > 0) {
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
        
        // Auto-assign support agent if available
        $this->auto_assign_agent($session_id, $chat_type);
        
        wp_send_json_success(array(
            'session_id' => $session_id,
            'message' => 'Chat session created successfully',
        ));
    }
    
    /**
     * Upload file for chat
     */
    public function upload_file() {
        check_ajax_referer('workcity_chat_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_die('Unauthorized', 401);
        }
        
        if (!function_exists('wp_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        
        if (empty($_FILES['file'])) {
            wp_send_json_error('No file provided');
        }
        
        $file = $_FILES['file'];
        
        // Check file size (5MB limit)
        if ($file['size'] > 5 * 1024 * 1024) {
            wp_send_json_error('File size too large. Maximum 5MB allowed.');
        }
        
        // Check file type
        $allowed_types = array('jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'zip');
        $file_type = wp_check_filetype($file['name']);
        
        if (!in_array($file_type['ext'], $allowed_types)) {
            wp_send_json_error('File type not allowed');
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
            wp_send_json_error($uploaded_file['error']);
        }
        
        wp_send_json_success(array(
            'url' => str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $uploaded_file['file']),
            'filename' => basename($uploaded_file['file']),
            'size' => filesize($uploaded_file['file']),
        ));
    }
    
    /**
     * Get online users for a session
     */
    public function get_online_users() {
        check_ajax_referer('workcity_chat_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_die('Unauthorized', 401);
        }
        
        global $wpdb;
        
        $session_id = intval($_POST['session_id']);
        $user_id = get_current_user_id();
        
        // Verify user can access this session
        if (!$this->can_access_session($session_id, $user_id)) {
            wp_die('Forbidden', 403);
        }
        
        // Get participants and their statuses
        $participants = $wpdb->get_results($wpdb->prepare(
            "SELECT p.user_id, p.role, u.display_name, p.last_seen
             FROM {$wpdb->prefix}workcity_chat_participants p
             JOIN {$wpdb->users} u ON p.user_id = u.ID
             WHERE p.chat_session_id = %d AND p.is_active = 1",
            $session_id
        ));
        
        $online_users = array();
        $current_time = current_time('timestamp');
        
        foreach ($participants as $participant) {
            $last_seen = strtotime($participant->last_seen);
            $is_online = ($current_time - $last_seen) < 300; // 5 minutes
            
            // Check for specific status
            $status = get_transient("workcity_chat_status_{$session_id}_{$participant->user_id}");
            
            $online_users[] = array(
                'user_id' => $participant->user_id,
                'name' => $participant->display_name,
                'role' => $participant->role,
                'is_online' => $is_online,
                'status' => $status ?: ($is_online ? 'online' : 'offline'),
                'last_seen' => $participant->last_seen,
                'is_current_user' => $participant->user_id == $user_id,
            );
        }
        
        wp_send_json_success($online_users);
    }
    
    /**
     * Check support availability (for non-logged users)
     */
    public function check_support_availability() {
        // This can be used to show if support is available before login
        $business_hours = get_option('workcity_chat_business_hours', array(
            'enabled' => false,
            'timezone' => 'UTC',
            'days' => array(
                'monday' => array('start' => '09:00', 'end' => '17:00'),
                'tuesday' => array('start' => '09:00', 'end' => '17:00'),
                'wednesday' => array('start' => '09:00', 'end' => '17:00'),
                'thursday' => array('start' => '09:00', 'end' => '17:00'),
                'friday' => array('start' => '09:00', 'end' => '17:00'),
                'saturday' => array('start' => '10:00', 'end' => '15:00'),
                'sunday' => array('start' => 'closed', 'end' => 'closed'),
            ),
        ));
        
        $is_available = true; // Default to available
        
        if ($business_hours['enabled']) {
            // Check if current time is within business hours
            $current_day = strtolower(current_time('l'));
            $current_time = current_time('H:i');
            
            if (isset($business_hours['days'][$current_day])) {
                $day_hours = $business_hours['days'][$current_day];
                
                if ($day_hours['start'] === 'closed') {
                    $is_available = false;
                } else {
                    $is_available = ($current_time >= $day_hours['start'] && $current_time <= $day_hours['end']);
                }
            }
        }
        
        wp_send_json_success(array(
            'is_available' => $is_available,
            'message' => $is_available ? 
                __('Support is available now', 'workcity-chat') : 
                __('Support is currently offline', 'workcity-chat'),
        ));
    }
    
    /**
     * Check if user can access a chat session
     */
    private function can_access_session($session_id, $user_id) {
        global $wpdb;
        
        $is_participant = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}workcity_chat_participants 
             WHERE chat_session_id = %d AND user_id = %d AND is_active = 1",
            $session_id,
            $user_id
        ));
        
        return $is_participant > 0 || current_user_can('manage_options');
    }
    
    /**
     * Update user's last seen timestamp
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
     * Auto-assign support agent
     */
    private function auto_assign_agent($session_id, $chat_type) {
        global $wpdb;
        
        // Get available agents based on chat type
        $role_meta_key = '_workcity_chat_agent_types';
        
        $agents = $wpdb->get_results($wpdb->prepare(
            "SELECT u.ID, u.display_name
             FROM {$wpdb->users} u
             JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
             WHERE um.meta_key = %s
             AND (um.meta_value LIKE %s OR um.meta_value LIKE %s)
             ORDER BY RAND()
             LIMIT 1",
            $role_meta_key,
            '%' . $chat_type . '%',
            '%all%'
        ));
        
        if (!empty($agents)) {
            $agent = $agents[0];
            
            // Add agent as participant
            $wpdb->insert(
                $wpdb->prefix . 'workcity_chat_participants',
                array(
                    'chat_session_id' => $session_id,
                    'user_id' => $agent->ID,
                    'role' => 'agent',
                    'joined_at' => current_time('mysql'),
                    'last_seen' => current_time('mysql'),
                    'is_active' => 1,
                ),
                array('%d', '%d', '%s', '%s', '%s', '%d')
            );
            
            // Send automatic welcome message
            $wpdb->insert(
                $wpdb->prefix . 'workcity_chat_messages',
                array(
                    'chat_session_id' => $session_id,
                    'sender_id' => $agent->ID,
                    'message_content' => sprintf(
                        __('Hello! I\'m %s and I\'ll be assisting you today. How can I help you?', 'workcity-chat'),
                        $agent->display_name
                    ),
                    'message_type' => 'text',
                    'is_read' => 0,
                    'created_at' => current_time('mysql'),
                ),
                array('%d', '%d', '%s', '%s', '%d', '%s')
            );
        }
    }
    
    /**
     * Get Gravatar URL
     */
    private function get_gravatar_url($email, $size = 32) {
        return 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($email))) . '?s=' . $size . '&d=mp';
    }
    
    /**
     * Format message time
     */
    private function format_message_time($datetime) {
        $time = strtotime($datetime);
        $now = current_time('timestamp');
        $diff = $now - $time;
        
        if ($diff < 60) {
            return __('Just now', 'workcity-chat');
        } elseif ($diff < 3600) {
            return sprintf(__('%d minutes ago', 'workcity-chat'), floor($diff / 60));
        } elseif ($diff < 86400) {
            return sprintf(__('%d hours ago', 'workcity-chat'), floor($diff / 3600));
        } else {
            return date('M j, Y g:i A', $time);
        }
    }
    
    /**
     * Truncate message content
     */
    private function truncate_message($content, $length = 50) {
        return strlen($content) > $length ? substr($content, 0, $length) . '...' : $content;
    }
}