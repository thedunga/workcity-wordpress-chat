<?php
/**
 * Chat Roles Management
 * 
 * Handles user roles and permissions for the chat system
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WorkCity_Chat_Roles {
    
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
        add_action('init', array($this, 'init_roles'));
        add_action('show_user_profile', array($this, 'add_chat_role_fields'));
        add_action('edit_user_profile', array($this, 'add_chat_role_fields'));
        add_action('personal_options_update', array($this, 'save_chat_role_fields'));
        add_action('edit_user_profile_update', array($this, 'save_chat_role_fields'));
        add_filter('user_has_cap', array($this, 'modify_user_caps'), 10, 4);
    }
    
    /**
     * Initialize chat roles and capabilities
     */
    public function init_roles() {
        // Add custom capabilities to existing roles
        $this->add_chat_capabilities();
        
        // Create custom chat roles if they don't exist
        $this->create_chat_roles();
    }
    
    /**
     * Add chat capabilities to existing roles
     */
    private function add_chat_capabilities() {
        // Admin capabilities
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_role->add_cap('manage_chat_sessions');
            $admin_role->add_cap('view_all_chats');
            $admin_role->add_cap('moderate_chats');
            $admin_role->add_cap('assign_chat_agents');
        }
        
        // Editor capabilities
        $editor_role = get_role('editor');
        if ($editor_role) {
            $editor_role->add_cap('manage_chat_sessions');
            $editor_role->add_cap('view_all_chats');
            $editor_role->add_cap('moderate_chats');
        }
        
        // Customer/Subscriber capabilities
        $subscriber_role = get_role('subscriber');
        if ($subscriber_role) {
            $subscriber_role->add_cap('participate_in_chat');
            $subscriber_role->add_cap('create_chat_session');
        }
        
        // Customer role (if exists from WooCommerce)
        $customer_role = get_role('customer');
        if ($customer_role) {
            $customer_role->add_cap('participate_in_chat');
            $customer_role->add_cap('create_chat_session');
        }
    }
    
    /**
     * Create custom chat roles
     */
    private function create_chat_roles() {
        // Chat Agent role
        if (!get_role('chat_agent')) {
            add_role('chat_agent', __('Chat Agent', 'workcity-chat'), array(
                'read' => true,
                'participate_in_chat' => true,
                'handle_chat_sessions' => true,
                'view_assigned_chats' => true,
                'upload_files' => true,
            ));
        }
        
        // Senior Chat Agent role
        if (!get_role('senior_chat_agent')) {
            add_role('senior_chat_agent', __('Senior Chat Agent', 'workcity-chat'), array(
                'read' => true,
                'participate_in_chat' => true,
                'handle_chat_sessions' => true,
                'view_assigned_chats' => true,
                'view_all_chats' => true,
                'assign_chat_agents' => true,
                'moderate_chats' => true,
                'upload_files' => true,
            ));
        }
        
        // Chat Supervisor role
        if (!get_role('chat_supervisor')) {
            add_role('chat_supervisor', __('Chat Supervisor', 'workcity-chat'), array(
                'read' => true,
                'participate_in_chat' => true,
                'handle_chat_sessions' => true,
                'view_all_chats' => true,
                'manage_chat_sessions' => true,
                'assign_chat_agents' => true,
                'moderate_chats' => true,
                'upload_files' => true,
                'edit_posts' => true,
                'delete_posts' => true,
            ));
        }
    }
    
    /**
     * Add chat role fields to user profile
     */
    public function add_chat_role_fields($user) {
        if (!current_user_can('edit_users')) {
            return;
        }
        
        $chat_agent_types = get_user_meta($user->ID, '_workcity_chat_agent_types', true);
        $max_concurrent_chats = get_user_meta($user->ID, '_workcity_chat_max_concurrent', true);
        $is_available = get_user_meta($user->ID, '_workcity_chat_available', true);
        $working_hours = get_user_meta($user->ID, '_workcity_chat_working_hours', true);
        
        ?>
        <h3><?php _e('Chat System Settings', 'workcity-chat'); ?></h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="chat_agent_types"><?php _e('Agent Specializations', 'workcity-chat'); ?></label>
                </th>
                <td>
                    <fieldset>
                        <legend class="screen-reader-text">
                            <span><?php _e('Agent Specializations', 'workcity-chat'); ?></span>
                        </legend>
                        <?php
                        $agent_types = array(
                            'general' => __('General Support', 'workcity-chat'),
                            'product' => __('Product Support', 'workcity-chat'),
                            'order' => __('Order Support', 'workcity-chat'),
                            'design' => __('Design Consultation', 'workcity-chat'),
                            'merchant' => __('Merchant Support', 'workcity-chat'),
                            'technical' => __('Technical Support', 'workcity-chat'),
                            'billing' => __('Billing Support', 'workcity-chat'),
                        );
                        
                        $selected_types = is_array($chat_agent_types) ? $chat_agent_types : array();
                        
                        foreach ($agent_types as $type => $label) {
                            $checked = in_array($type, $selected_types) ? 'checked="checked"' : '';
                            echo '<label><input type="checkbox" name="chat_agent_types[]" value="' . esc_attr($type) . '" ' . $checked . '> ' . esc_html($label) . '</label><br>';
                        }
                        ?>
                        <p class="description"><?php _e('Select the types of chat sessions this user can handle.', 'workcity-chat'); ?></p>
                    </fieldset>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="chat_max_concurrent"><?php _e('Max Concurrent Chats', 'workcity-chat'); ?></label>
                </th>
                <td>
                    <input type="number" name="chat_max_concurrent" id="chat_max_concurrent" 
                           value="<?php echo esc_attr($max_concurrent_chats ?: 5); ?>" 
                           min="1" max="20" class="small-text">
                    <p class="description"><?php _e('Maximum number of simultaneous chat sessions this agent can handle.', 'workcity-chat'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="chat_available"><?php _e('Available for Chat', 'workcity-chat'); ?></label>
                </th>
                <td>
                    <fieldset>
                        <legend class="screen-reader-text">
                            <span><?php _e('Available for Chat', 'workcity-chat'); ?></span>
                        </legend>
                        <label>
                            <input type="checkbox" name="chat_available" value="1" <?php checked($is_available, '1'); ?>>
                            <?php _e('Agent is currently available to receive new chat assignments', 'workcity-chat'); ?>
                        </label>
                    </fieldset>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="chat_working_hours"><?php _e('Working Hours', 'workcity-chat'); ?></label>
                </th>
                <td>
                    <textarea name="chat_working_hours" id="chat_working_hours" rows="3" cols="50" class="regular-text"><?php echo esc_textarea($working_hours); ?></textarea>
                    <p class="description"><?php _e('Optional: Specify working hours for this agent (e.g., "Monday-Friday 9:00-17:00 UTC").', 'workcity-chat'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Save chat role fields
     */
    public function save_chat_role_fields($user_id) {
        if (!current_user_can('edit_users')) {
            return;
        }
        
        // Save agent types
        if (isset($_POST['chat_agent_types']) && is_array($_POST['chat_agent_types'])) {
            $agent_types = array_map('sanitize_text_field', $_POST['chat_agent_types']);
            update_user_meta($user_id, '_workcity_chat_agent_types', $agent_types);
        } else {
            delete_user_meta($user_id, '_workcity_chat_agent_types');
        }
        
        // Save max concurrent chats
        if (isset($_POST['chat_max_concurrent'])) {
            $max_concurrent = intval($_POST['chat_max_concurrent']);
            update_user_meta($user_id, '_workcity_chat_max_concurrent', max(1, $max_concurrent));
        }
        
        // Save availability status
        $is_available = isset($_POST['chat_available']) ? '1' : '0';
        update_user_meta($user_id, '_workcity_chat_available', $is_available);
        
        // Save working hours
        if (isset($_POST['chat_working_hours'])) {
            $working_hours = sanitize_textarea_field($_POST['chat_working_hours']);
            update_user_meta($user_id, '_workcity_chat_working_hours', $working_hours);
        }
    }
    
    /**
     * Modify user capabilities dynamically
     */
    public function modify_user_caps($allcaps, $caps, $args, $user) {
        // Don't modify if not our capability
        if (empty($args[0]) || strpos($args[0], 'workcity_chat_') !== 0) {
            return $allcaps;
        }
        
        $capability = $args[0];
        $user_id = $user->ID;
        
        switch ($capability) {
            case 'workcity_chat_view_session':
                // Check if user can view specific chat session
                if (isset($args[2])) {
                    $session_id = $args[2];
                    $allcaps[$capability] = $this->can_user_view_session($user_id, $session_id);
                }
                break;
                
            case 'workcity_chat_participate':
                // Check if user can participate in specific session
                if (isset($args[2])) {
                    $session_id = $args[2];
                    $allcaps[$capability] = $this->can_user_participate_in_session($user_id, $session_id);
                }
                break;
        }
        
        return $allcaps;
    }
    
    /**
     * Check if user can view a specific chat session
     */
    public function can_user_view_session($user_id, $session_id) {
        global $wpdb;
        
        // Admin can view all
        if (user_can($user_id, 'manage_options')) {
            return true;
        }
        
        // Check if user is participant
        $is_participant = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}workcity_chat_participants 
             WHERE chat_session_id = %d AND user_id = %d AND is_active = 1",
            $session_id,
            $user_id
        ));
        
        return $is_participant > 0;
    }
    
    /**
     * Check if user can participate in a specific chat session
     */
    public function can_user_participate_in_session($user_id, $session_id) {
        return $this->can_user_view_session($user_id, $session_id);
    }
    
    /**
     * Get available agents for a specific chat type
     */
    public function get_available_agents($chat_type = 'general', $exclude_user_ids = array()) {
        global $wpdb;
        
        $exclude_ids = !empty($exclude_user_ids) ? 
            "AND u.ID NOT IN (" . implode(',', array_map('intval', $exclude_user_ids)) . ")" : "";
        
        // Get users who can handle this chat type and are available
        $agents = $wpdb->get_results($wpdb->prepare(
            "SELECT u.ID, u.display_name, u.user_email,
                    um1.meta_value as agent_types,
                    um2.meta_value as max_concurrent,
                    um3.meta_value as is_available
             FROM {$wpdb->users} u
             LEFT JOIN {$wpdb->usermeta} um1 ON u.ID = um1.user_id AND um1.meta_key = '_workcity_chat_agent_types'
             LEFT JOIN {$wpdb->usermeta} um2 ON u.ID = um2.user_id AND um2.meta_key = '_workcity_chat_max_concurrent'
             LEFT JOIN {$wpdb->usermeta} um3 ON u.ID = um3.user_id AND um3.meta_key = '_workcity_chat_available'
             WHERE (um1.meta_value LIKE %s OR um1.meta_value LIKE %s)
             AND um3.meta_value = '1'
             {$exclude_ids}
             ORDER BY RAND()",
            '%' . $chat_type . '%',
            '%all%'
        ));
        
        $available_agents = array();
        
        foreach ($agents as $agent) {
            // Check current workload
            $current_chats = $this->get_agent_current_chat_count($agent->ID);
            $max_concurrent = intval($agent->max_concurrent ?: 5);
            
            if ($current_chats < $max_concurrent) {
                $available_agents[] = array(
                    'id' => $agent->ID,
                    'name' => $agent->display_name,
                    'email' => $agent->user_email,
                    'current_chats' => $current_chats,
                    'max_chats' => $max_concurrent,
                    'load_percentage' => round(($current_chats / $max_concurrent) * 100),
                );
            }
        }
        
        // Sort by workload (least busy first)
        usort($available_agents, function($a, $b) {
            return $a['load_percentage'] - $b['load_percentage'];
        });
        
        return $available_agents;
    }
    
    /**
     * Get agent's current active chat count
     */
    public function get_agent_current_chat_count($agent_id) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT p.chat_session_id)
             FROM {$wpdb->prefix}workcity_chat_participants p
             JOIN {$wpdb->posts} cs ON p.chat_session_id = cs.ID
             JOIN {$wpdb->postmeta} pm ON cs.ID = pm.post_id
             WHERE p.user_id = %d 
             AND p.is_active = 1
             AND cs.post_type = 'chat_session'
             AND pm.meta_key = '_chat_status'
             AND pm.meta_value IN ('active', 'pending')",
            $agent_id
        ));
    }
    
    /**
     * Auto-assign agent to a chat session
     */
    public function auto_assign_agent($session_id, $chat_type = 'general') {
        $available_agents = $this->get_available_agents($chat_type);
        
        if (empty($available_agents)) {
            return false;
        }
        
        // Get the least busy agent
        $agent = $available_agents[0];
        
        // Add agent as participant
        global $wpdb;
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'workcity_chat_participants',
            array(
                'chat_session_id' => $session_id,
                'user_id' => $agent['id'],
                'role' => 'agent',
                'joined_at' => current_time('mysql'),
                'last_seen' => current_time('mysql'),
                'is_active' => 1,
            ),
            array('%d', '%d', '%s', '%s', '%s', '%d')
        );
        
        if ($result) {
            // Send welcome message
            $wpdb->insert(
                $wpdb->prefix . 'workcity_chat_messages',
                array(
                    'chat_session_id' => $session_id,
                    'sender_id' => $agent['id'],
                    'message_content' => sprintf(
                        __('Hello! I\'m %s and I\'ll be assisting you today. How can I help you?', 'workcity-chat'),
                        $agent['name']
                    ),
                    'message_type' => 'text',
                    'is_read' => 0,
                    'created_at' => current_time('mysql'),
                ),
                array('%d', '%d', '%s', '%s', '%d', '%s')
            );
            
            // Trigger action for notifications
            do_action('workcity_chat_agent_assigned', $session_id, $agent['id']);
            
            return $agent['id'];
        }
        
        return false;
    }
    
    /**
     * Get user's role in chat system
     */
    public function get_user_chat_role($user_id, $session_id = null) {
        global $wpdb;
        
        if ($session_id) {
            // Get role for specific session
            $role = $wpdb->get_var($wpdb->prepare(
                "SELECT role FROM {$wpdb->prefix}workcity_chat_participants 
                 WHERE chat_session_id = %d AND user_id = %d AND is_active = 1",
                $session_id,
                $user_id
            ));
            
            return $role ?: 'none';
        }
        
        // Get general chat role
        $user = get_userdata($user_id);
        
        if (!$user) {
            return 'none';
        }
        
        // Check for chat-specific roles first
        if (in_array('chat_supervisor', $user->roles)) {
            return 'supervisor';
        } elseif (in_array('senior_chat_agent', $user->roles)) {
            return 'senior_agent';
        } elseif (in_array('chat_agent', $user->roles)) {
            return 'agent';
        } elseif (user_can($user_id, 'manage_options')) {
            return 'admin';
        } elseif (user_can($user_id, 'participate_in_chat')) {
            return 'customer';
        }
        
        return 'none';
    }
    
    /**
     * Check if user is online/available
     */
    public function is_user_online($user_id, $session_id = null) {
        if ($session_id) {
            // Check last seen in specific session
            global $wpdb;
            
            $last_seen = $wpdb->get_var($wpdb->prepare(
                "SELECT last_seen FROM {$wpdb->prefix}workcity_chat_participants 
                 WHERE chat_session_id = %d AND user_id = %d",
                $session_id,
                $user_id
            ));
            
            if ($last_seen) {
                $last_seen_time = strtotime($last_seen);
                $current_time = current_time('timestamp');
                
                // Consider user online if last seen within 5 minutes
                return ($current_time - $last_seen_time) < 300;
            }
        }
        
        // Check general online status
        $last_activity = get_user_meta($user_id, '_workcity_chat_last_activity', true);
        
        if ($last_activity) {
            $last_activity_time = strtotime($last_activity);
            $current_time = current_time('timestamp');
            
            return ($current_time - $last_activity_time) < 300;
        }
        
        return false;
    }
}