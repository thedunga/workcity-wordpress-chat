<?php
/**
 * Chat Notifications
 * 
 * Handles email and push notifications for chat events
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WorkCity_Chat_Notifications {
    
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
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Chat event hooks
        add_action('workcity_chat_message_sent', array($this, 'handle_message_notification'), 10, 3);
        add_action('workcity_chat_agent_assigned', array($this, 'handle_agent_assignment'), 10, 2);
        add_action('workcity_chat_session_created', array($this, 'handle_session_created'), 10, 2);
        add_action('workcity_chat_session_closed', array($this, 'handle_session_closed'), 10, 2);
        
        // Email template hooks
        add_action('workcity_chat_send_email_notification', array($this, 'send_email_notification'), 10, 4);
        
        // Admin notification settings
        add_action('admin_menu', array($this, 'add_notification_settings_page'));
        add_action('admin_init', array($this, 'register_notification_settings'));
        
        // AJAX handlers for browser notifications
        add_action('wp_ajax_workcity_chat_register_device', array($this, 'register_device_for_notifications'));
        add_action('wp_ajax_workcity_chat_get_notifications', array($this, 'get_pending_notifications'));
    }
    
    /**
     * Handle new message notification
     */
    public function handle_message_notification($message_id, $session_id, $sender_id) {
        global $wpdb;
        
        // Get message details
        $message = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}workcity_chat_messages WHERE id = %d",
            $message_id
        ));
        
        if (!$message) {
            return;
        }
        
        // Get session participants (excluding sender)
        $participants = $wpdb->get_results($wpdb->prepare(
            "SELECT p.*, u.user_email, u.display_name, u.user_login
             FROM {$wpdb->prefix}workcity_chat_participants p
             JOIN {$wpdb->users} u ON p.user_id = u.ID
             WHERE p.chat_session_id = %d 
             AND p.user_id != %d 
             AND p.is_active = 1",
            $session_id,
            $sender_id
        ));
        
        $sender = get_userdata($sender_id);
        $session = get_post($session_id);
        
        foreach ($participants as $participant) {
            $this->send_message_notification($participant, $message, $sender, $session);
        }
    }
    
    /**
     * Handle agent assignment notification
     */
    public function handle_agent_assignment($session_id, $agent_id) {
        global $wpdb;
        
        // Get session details
        $session = get_post($session_id);
        if (!$session) {
            return;
        }
        
        // Get customer (session creator)
        $customer = get_userdata($session->post_author);
        $agent = get_userdata($agent_id);
        
        if ($customer && $agent) {
            // Notify customer about agent assignment
            $this->send_agent_assignment_notification($customer, $agent, $session);
            
            // Notify agent about new assignment
            $this->send_new_assignment_notification($agent, $customer, $session);
        }
    }
    
    /**
     * Handle session created notification
     */
    public function handle_session_created($session_id, $customer_id) {
        $session = get_post($session_id);
        $customer = get_userdata($customer_id);
        
        if (!$session || !$customer) {
            return;
        }
        
        // Notify available agents about new session
        $this->notify_available_agents($session, $customer);
        
        // Send confirmation to customer
        $this->send_session_confirmation($customer, $session);
    }
    
    /**
     * Handle session closed notification
     */
    public function handle_session_closed($session_id, $closed_by_id) {
        global $wpdb;
        
        $session = get_post($session_id);
        $closed_by = get_userdata($closed_by_id);
        
        if (!$session || !$closed_by) {
            return;
        }
        
        // Get all participants
        $participants = $wpdb->get_results($wpdb->prepare(
            "SELECT p.*, u.user_email, u.display_name
             FROM {$wpdb->prefix}workcity_chat_participants p
             JOIN {$wpdb->users} u ON p.user_id = u.ID
             WHERE p.chat_session_id = %d 
             AND p.user_id != %d 
             AND p.is_active = 1",
            $session_id,
            $closed_by_id
        ));
        
        foreach ($participants as $participant) {
            $this->send_session_closed_notification($participant, $session, $closed_by);
        }
    }
    
    /**
     * Send message notification
     */
    private function send_message_notification($participant, $message, $sender, $session) {
        $participant_user = get_userdata($participant->user_id);
        
        // Check if user wants email notifications
        $email_notifications = get_user_meta($participant->user_id, '_workcity_chat_email_notifications', true);
        
        if ($email_notifications !== 'disabled') {
            $subject = sprintf(
                __('New message in chat: %s', 'workcity-chat'),
                $session->post_title
            );
            
            $message_content = wp_strip_all_tags($message->message_content);
            if (strlen($message_content) > 100) {
                $message_content = substr($message_content, 0, 100) . '...';
            }
            
            $email_body = sprintf(
                __("Hello %s,\n\nYou have received a new message from %s in the chat session \"%s\":\n\n\"%s\"\n\nClick here to view and respond: %s\n\nBest regards,\nSupport Team", 'workcity-chat'),
                $participant_user->display_name,
                $sender->display_name,
                $session->post_title,
                $message_content,
                $this->get_chat_url($session->ID)
            );
            
            $this->send_email($participant_user->user_email, $subject, $email_body);
        }
        
        // Browser notification
        $this->send_browser_notification($participant->user_id, array(
            'title' => sprintf(__('New message from %s', 'workcity-chat'), $sender->display_name),
            'body' => wp_strip_all_tags($message->message_content),
            'icon' => get_avatar_url($sender->ID, array('size' => 64)),
            'url' => $this->get_chat_url($session->ID),
        ));
    }
    
    /**
     * Send agent assignment notification to customer
     */
    private function send_agent_assignment_notification($customer, $agent, $session) {
        $email_notifications = get_user_meta($customer->ID, '_workcity_chat_email_notifications', true);
        
        if ($email_notifications !== 'disabled') {
            $subject = sprintf(
                __('Support agent assigned to your chat: %s', 'workcity-chat'),
                $session->post_title
            );
            
            $email_body = sprintf(
                __("Hello %s,\n\nGood news! %s has been assigned to help you with your chat session \"%s\".\n\nYou can expect a response shortly. Click here to view your chat: %s\n\nBest regards,\nSupport Team", 'workcity-chat'),
                $customer->display_name,
                $agent->display_name,
                $session->post_title,
                $this->get_chat_url($session->ID)
            );
            
            $this->send_email($customer->user_email, $subject, $email_body);
        }
        
        // Browser notification
        $this->send_browser_notification($customer->ID, array(
            'title' => __('Support agent assigned', 'workcity-chat'),
            'body' => sprintf(__('%s will be helping you', 'workcity-chat'), $agent->display_name),
            'icon' => get_avatar_url($agent->ID, array('size' => 64)),
            'url' => $this->get_chat_url($session->ID),
        ));
    }
    
    /**
     * Send new assignment notification to agent
     */
    private function send_new_assignment_notification($agent, $customer, $session) {
        $email_notifications = get_user_meta($agent->ID, '_workcity_chat_email_notifications', true);
        
        if ($email_notifications !== 'disabled') {
            $subject = sprintf(
                __('New chat assignment: %s', 'workcity-chat'),
                $session->post_title
            );
            
            $email_body = sprintf(
                __("Hello %s,\n\nYou have been assigned to a new chat session:\n\nCustomer: %s\nSubject: %s\nType: %s\n\nPlease respond as soon as possible: %s\n\nBest regards,\nSupport Team", 'workcity-chat'),
                $agent->display_name,
                $customer->display_name,
                $session->post_title,
                ucfirst(get_post_meta($session->ID, '_chat_type', true)),
                $this->get_chat_url($session->ID)
            );
            
            $this->send_email($agent->user_email, $subject, $email_body);
        }
        
        // Browser notification
        $this->send_browser_notification($agent->ID, array(
            'title' => __('New chat assignment', 'workcity-chat'),
            'body' => sprintf(__('Chat with %s: %s', 'workcity-chat'), $customer->display_name, $session->post_title),
            'icon' => get_avatar_url($customer->ID, array('size' => 64)),
            'url' => $this->get_chat_url($session->ID),
        ));
    }
    
    /**
     * Notify available agents about new session
     */
    private function notify_available_agents($session, $customer) {
        $chat_type = get_post_meta($session->ID, '_chat_type', true);
        
        // Get available agents for this chat type
        $chat_roles = WorkCity_Chat_Roles::instance();
        $available_agents = $chat_roles->get_available_agents($chat_type);
        
        foreach ($available_agents as $agent_data) {
            $agent = get_userdata($agent_data['id']);
            
            // Check if agent wants these notifications
            $new_session_notifications = get_user_meta($agent->ID, '_workcity_chat_new_session_notifications', true);
            
            if ($new_session_notifications !== 'disabled') {
                $this->send_browser_notification($agent->ID, array(
                    'title' => __('New chat session available', 'workcity-chat'),
                    'body' => sprintf(__('%s needs help with: %s', 'workcity-chat'), $customer->display_name, $session->post_title),
                    'icon' => get_avatar_url($customer->ID, array('size' => 64)),
                    'url' => admin_url('edit.php?post_type=chat_session'),
                ));
            }
        }
    }
    
    /**
     * Send session confirmation to customer
     */
    private function send_session_confirmation($customer, $session) {
        $email_notifications = get_user_meta($customer->ID, '_workcity_chat_email_notifications', true);
        
        if ($email_notifications !== 'disabled') {
            $subject = sprintf(
                __('Chat session created: %s', 'workcity-chat'),
                $session->post_title
            );
            
            $email_body = sprintf(
                __("Hello %s,\n\nYour chat session \"%s\" has been created successfully.\n\nOur support team will respond to you shortly. You can view your chat here: %s\n\nBest regards,\nSupport Team", 'workcity-chat'),
                $customer->display_name,
                $session->post_title,
                $this->get_chat_url($session->ID)
            );
            
            $this->send_email($customer->user_email, $subject, $email_body);
        }
    }
    
    /**
     * Send session closed notification
     */
    private function send_session_closed_notification($participant, $session, $closed_by) {
        $participant_user = get_userdata($participant->user_id);
        $email_notifications = get_user_meta($participant->user_id, '_workcity_chat_email_notifications', true);
        
        if ($email_notifications !== 'disabled') {
            $subject = sprintf(
                __('Chat session closed: %s', 'workcity-chat'),
                $session->post_title
            );
            
            $email_body = sprintf(
                __("Hello %s,\n\nThe chat session \"%s\" has been closed by %s.\n\nIf you need further assistance, please feel free to start a new chat session.\n\nThank you for contacting us!\n\nBest regards,\nSupport Team", 'workcity-chat'),
                $participant_user->display_name,
                $session->post_title,
                $closed_by->display_name
            );
            
            $this->send_email($participant_user->user_email, $subject, $email_body);
        }
    }
    
    /**
     * Send email notification
     */
    private function send_email($to, $subject, $message) {
        $headers = array('Content-Type: text/plain; charset=UTF-8');
        
        // Use custom from address if set
        $from_email = get_option('workcity_chat_from_email');
        $from_name = get_option('workcity_chat_from_name', get_bloginfo('name'));
        
        if ($from_email) {
            $headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
        }
        
        wp_mail($to, $subject, $message, $headers);
    }
    
    /**
     * Send browser notification
     */
    private function send_browser_notification($user_id, $notification_data) {
        // Store notification for retrieval via AJAX
        $notifications = get_user_meta($user_id, '_workcity_chat_pending_notifications', true);
        if (!is_array($notifications)) {
            $notifications = array();
        }
        
        $notification_data['timestamp'] = current_time('timestamp');
        $notification_data['id'] = uniqid();
        
        $notifications[] = $notification_data;
        
        // Keep only last 10 notifications
        $notifications = array_slice($notifications, -10);
        
        update_user_meta($user_id, '_workcity_chat_pending_notifications', $notifications);
        
        // Trigger real-time notification if user is online
        if ($this->is_user_online($user_id)) {
            // This could be expanded to use WebSockets or Server-Sent Events
            do_action('workcity_chat_real_time_notification', $user_id, $notification_data);
        }
    }
    
    /**
     * Get chat URL
     */
    private function get_chat_url($session_id) {
        // This could be customized based on your frontend implementation
        return add_query_arg(array(
            'chat_session' => $session_id,
        ), home_url());
    }
    
    /**
     * Check if user is online
     */
    private function is_user_online($user_id) {
        $last_activity = get_user_meta($user_id, '_workcity_chat_last_activity', true);
        
        if ($last_activity) {
            $last_activity_time = strtotime($last_activity);
            $current_time = current_time('timestamp');
            
            return ($current_time - $last_activity_time) < 300; // 5 minutes
        }
        
        return false;
    }
    
    /**
     * Add notification settings page
     */
    public function add_notification_settings_page() {
        add_submenu_page(
            'workcity-chat',
            __('Notification Settings', 'workcity-chat'),
            __('Notifications', 'workcity-chat'),
            'manage_options',
            'workcity-chat-notifications',
            array($this, 'render_notification_settings_page')
        );
    }
    
    /**
     * Register notification settings
     */
    public function register_notification_settings() {
        register_setting('workcity_chat_notifications', 'workcity_chat_from_email');
        register_setting('workcity_chat_notifications', 'workcity_chat_from_name');
        register_setting('workcity_chat_notifications', 'workcity_chat_email_template');
        register_setting('workcity_chat_notifications', 'workcity_chat_enable_browser_notifications');
        register_setting('workcity_chat_notifications', 'workcity_chat_notification_frequency');
    }
    
    /**
     * Render notification settings page
     */
    public function render_notification_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Chat Notification Settings', 'workcity-chat'); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('workcity_chat_notifications'); ?>
                <?php do_settings_sections('workcity_chat_notifications'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="workcity_chat_from_email"><?php _e('From Email Address', 'workcity-chat'); ?></label>
                        </th>
                        <td>
                            <input type="email" 
                                   id="workcity_chat_from_email" 
                                   name="workcity_chat_from_email" 
                                   value="<?php echo esc_attr(get_option('workcity_chat_from_email')); ?>" 
                                   class="regular-text">
                            <p class="description"><?php _e('Email address for outgoing chat notifications', 'workcity-chat'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="workcity_chat_from_name"><?php _e('From Name', 'workcity-chat'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="workcity_chat_from_name" 
                                   name="workcity_chat_from_name" 
                                   value="<?php echo esc_attr(get_option('workcity_chat_from_name', get_bloginfo('name'))); ?>" 
                                   class="regular-text">
                            <p class="description"><?php _e('Name shown in outgoing emails', 'workcity-chat'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="workcity_chat_enable_browser_notifications"><?php _e('Browser Notifications', 'workcity-chat'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       id="workcity_chat_enable_browser_notifications" 
                                       name="workcity_chat_enable_browser_notifications" 
                                       value="1" 
                                       <?php checked(get_option('workcity_chat_enable_browser_notifications'), 1); ?>>
                                <?php _e('Enable browser push notifications', 'workcity-chat'); ?>
                            </label>
                            <p class="description"><?php _e('Allow users to receive browser notifications for new messages', 'workcity-chat'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="workcity_chat_notification_frequency"><?php _e('Email Frequency', 'workcity-chat'); ?></label>
                        </th>
                        <td>
                            <select id="workcity_chat_notification_frequency" name="workcity_chat_notification_frequency">
                                <option value="instant" <?php selected(get_option('workcity_chat_notification_frequency'), 'instant'); ?>><?php _e('Instant', 'workcity-chat'); ?></option>
                                <option value="digest_hourly" <?php selected(get_option('workcity_chat_notification_frequency'), 'digest_hourly'); ?>><?php _e('Hourly Digest', 'workcity-chat'); ?></option>
                                <option value="digest_daily" <?php selected(get_option('workcity_chat_notification_frequency'), 'digest_daily'); ?>><?php _e('Daily Digest', 'workcity-chat'); ?></option>
                            </select>
                            <p class="description"><?php _e('How often to send email notifications', 'workcity-chat'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * AJAX: Register device for notifications
     */
    public function register_device_for_notifications() {
        check_ajax_referer('workcity_chat_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_die('Unauthorized', 401);
        }
        
        $user_id = get_current_user_id();
        $endpoint = sanitize_text_field($_POST['endpoint'] ?? '');
        $key = sanitize_text_field($_POST['key'] ?? '');
        $auth = sanitize_text_field($_POST['auth'] ?? '');
        
        if (empty($endpoint)) {
            wp_send_json_error('Endpoint is required');
        }
        
        $devices = get_user_meta($user_id, '_workcity_chat_notification_devices', true);
        if (!is_array($devices)) {
            $devices = array();
        }
        
        $device_id = md5($endpoint);
        $devices[$device_id] = array(
            'endpoint' => $endpoint,
            'key' => $key,
            'auth' => $auth,
            'registered_at' => current_time('mysql'),
        );
        
        update_user_meta($user_id, '_workcity_chat_notification_devices', $devices);
        
        wp_send_json_success(array(
            'device_id' => $device_id,
            'message' => 'Device registered for notifications',
        ));
    }
    
    /**
     * AJAX: Get pending notifications
     */
    public function get_pending_notifications() {
        check_ajax_referer('workcity_chat_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_die('Unauthorized', 401);
        }
        
        $user_id = get_current_user_id();
        $notifications = get_user_meta($user_id, '_workcity_chat_pending_notifications', true);
        
        if (!is_array($notifications)) {
            $notifications = array();
        }
        
        // Clear notifications after retrieving
        delete_user_meta($user_id, '_workcity_chat_pending_notifications');
        
        // Update last activity
        update_user_meta($user_id, '_workcity_chat_last_activity', current_time('mysql'));
        
        wp_send_json_success($notifications);
    }
}