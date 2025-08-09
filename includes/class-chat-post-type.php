<?php
/**
 * Chat Session Post Type
 * 
 * Handles the custom post type for chat sessions
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WorkCity_Chat_Post_Type {
    
    /**
     * Single instance
     */
    private static $instance = null;
    
    /**
     * Post type name
     */
    const POST_TYPE = 'chat_session';
    
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
        add_action('init', array($this, 'register_post_type'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_data'));
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', array($this, 'custom_columns'));
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', array($this, 'custom_column_content'), 10, 2);
    }
    
    /**
     * Register the chat session post type
     */
    public function register_post_type() {
        $labels = array(
            'name'                  => _x('Chat Sessions', 'Post type general name', 'workcity-chat'),
            'singular_name'         => _x('Chat Session', 'Post type singular name', 'workcity-chat'),
            'menu_name'             => _x('Chat Sessions', 'Admin Menu text', 'workcity-chat'),
            'name_admin_bar'        => _x('Chat Session', 'Add New on Toolbar', 'workcity-chat'),
            'add_new'               => __('Add New', 'workcity-chat'),
            'add_new_item'          => __('Add New Chat Session', 'workcity-chat'),
            'new_item'              => __('New Chat Session', 'workcity-chat'),
            'edit_item'             => __('Edit Chat Session', 'workcity-chat'),
            'view_item'             => __('View Chat Session', 'workcity-chat'),
            'all_items'             => __('All Chat Sessions', 'workcity-chat'),
            'search_items'          => __('Search Chat Sessions', 'workcity-chat'),
            'parent_item_colon'     => __('Parent Chat Sessions:', 'workcity-chat'),
            'not_found'             => __('No chat sessions found.', 'workcity-chat'),
            'not_found_in_trash'    => __('No chat sessions found in Trash.', 'workcity-chat'),
            'featured_image'        => _x('Chat Session Cover Image', 'Overrides the "Featured Image" phrase', 'workcity-chat'),
            'set_featured_image'    => _x('Set cover image', 'Overrides the "Set featured image" phrase', 'workcity-chat'),
            'remove_featured_image' => _x('Remove cover image', 'Overrides the "Remove featured image" phrase', 'workcity-chat'),
            'use_featured_image'    => _x('Use as cover image', 'Overrides the "Use as featured image" phrase', 'workcity-chat'),
            'archives'              => _x('Chat Session archives', 'The post type archive label', 'workcity-chat'),
            'insert_into_item'      => _x('Insert into chat session', 'Overrides the "Insert into post" phrase', 'workcity-chat'),
            'uploaded_to_this_item' => _x('Uploaded to this chat session', 'Overrides the "Uploaded to this post" phrase', 'workcity-chat'),
            'filter_items_list'     => _x('Filter chat sessions list', 'Screen reader text for the filter links', 'workcity-chat'),
            'items_list_navigation' => _x('Chat sessions list navigation', 'Screen reader text for the pagination', 'workcity-chat'),
            'items_list'            => _x('Chat sessions list', 'Screen reader text for the items list', 'workcity-chat'),
        );
        
        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => 'workcity-chat',
            'query_var'          => true,
            'rewrite'            => array('slug' => 'chat-session'),
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => null,
            'menu_icon'          => 'dashicons-format-chat',
            'supports'           => array('title', 'custom-fields'),
            'show_in_rest'       => true,
            'rest_base'          => 'chat-sessions',
            'rest_controller_class' => 'WP_REST_Posts_Controller',
        );
        
        register_post_type(self::POST_TYPE, $args);
    }
    
    /**
     * Add meta boxes
     */
    public function add_meta_boxes() {
        add_meta_box(
            'chat_session_details',
            __('Chat Session Details', 'workcity-chat'),
            array($this, 'meta_box_callback'),
            self::POST_TYPE,
            'normal',
            'high'
        );
        
        add_meta_box(
            'chat_participants',
            __('Chat Participants', 'workcity-chat'),
            array($this, 'participants_meta_box_callback'),
            self::POST_TYPE,
            'side',
            'default'
        );
    }
    
    /**
     * Meta box callback
     */
    public function meta_box_callback($post) {
        wp_nonce_field('chat_session_meta_box', 'chat_session_meta_box_nonce');
        
        $chat_type = get_post_meta($post->ID, '_chat_type', true);
        $product_id = get_post_meta($post->ID, '_product_id', true);
        $status = get_post_meta($post->ID, '_chat_status', true);
        $priority = get_post_meta($post->ID, '_chat_priority', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="chat_type"><?php _e('Chat Type', 'workcity-chat'); ?></label>
                </th>
                <td>
                    <select name="chat_type" id="chat_type" class="regular-text">
                        <option value="general" <?php selected($chat_type, 'general'); ?>><?php _e('General Support', 'workcity-chat'); ?></option>
                        <option value="product" <?php selected($chat_type, 'product'); ?>><?php _e('Product Inquiry', 'workcity-chat'); ?></option>
                        <option value="order" <?php selected($chat_type, 'order'); ?>><?php _e('Order Support', 'workcity-chat'); ?></option>
                        <option value="design" <?php selected($chat_type, 'design'); ?>><?php _e('Design Consultation', 'workcity-chat'); ?></option>
                        <option value="merchant" <?php selected($chat_type, 'merchant'); ?>><?php _e('Merchant Support', 'workcity-chat'); ?></option>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="product_id"><?php _e('Related Product ID', 'workcity-chat'); ?></label>
                </th>
                <td>
                    <input type="number" name="product_id" id="product_id" value="<?php echo esc_attr($product_id); ?>" class="regular-text" />
                    <p class="description"><?php _e('Leave empty for non-product related chats', 'workcity-chat'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="chat_status"><?php _e('Status', 'workcity-chat'); ?></label>
                </th>
                <td>
                    <select name="chat_status" id="chat_status" class="regular-text">
                        <option value="active" <?php selected($status, 'active'); ?>><?php _e('Active', 'workcity-chat'); ?></option>
                        <option value="pending" <?php selected($status, 'pending'); ?>><?php _e('Pending', 'workcity-chat'); ?></option>
                        <option value="resolved" <?php selected($status, 'resolved'); ?>><?php _e('Resolved', 'workcity-chat'); ?></option>
                        <option value="closed" <?php selected($status, 'closed'); ?>><?php _e('Closed', 'workcity-chat'); ?></option>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="chat_priority"><?php _e('Priority', 'workcity-chat'); ?></label>
                </th>
                <td>
                    <select name="chat_priority" id="chat_priority" class="regular-text">
                        <option value="low" <?php selected($priority, 'low'); ?>><?php _e('Low', 'workcity-chat'); ?></option>
                        <option value="normal" <?php selected($priority, 'normal'); ?>><?php _e('Normal', 'workcity-chat'); ?></option>
                        <option value="high" <?php selected($priority, 'high'); ?>><?php _e('High', 'workcity-chat'); ?></option>
                        <option value="urgent" <?php selected($priority, 'urgent'); ?>><?php _e('Urgent', 'workcity-chat'); ?></option>
                    </select>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Participants meta box callback
     */
    public function participants_meta_box_callback($post) {
        global $wpdb;
        
        $participants = $wpdb->get_results($wpdb->prepare(
            "SELECT p.*, u.display_name, u.user_email 
             FROM {$wpdb->prefix}workcity_chat_participants p 
             JOIN {$wpdb->users} u ON p.user_id = u.ID 
             WHERE p.chat_session_id = %d AND p.is_active = 1",
            $post->ID
        ));
        
        if ($participants) {
            echo '<ul>';
            foreach ($participants as $participant) {
                echo '<li>';
                echo '<strong>' . esc_html($participant->display_name) . '</strong> ';
                echo '<span class="participant-role">(' . esc_html($participant->role) . ')</span><br>';
                echo '<small>' . esc_html($participant->user_email) . '</small>';
                echo '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p>' . __('No participants found.', 'workcity-chat') . '</p>';
        }
    }
    
    /**
     * Save meta data
     */
    public function save_meta_data($post_id) {
        // Check if nonce is valid
        if (!isset($_POST['chat_session_meta_box_nonce']) || 
            !wp_verify_nonce($_POST['chat_session_meta_box_nonce'], 'chat_session_meta_box')) {
            return;
        }
        
        // Check if user has permission to edit
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Check if this is an autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Save meta fields
        $fields = array('chat_type', 'product_id', 'chat_status', 'chat_priority');
        
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, '_' . $field, sanitize_text_field($_POST[$field]));
            }
        }
    }
    
    /**
     * Custom columns for admin list
     */
    public function custom_columns($columns) {
        $new_columns = array();
        
        foreach ($columns as $key => $title) {
            $new_columns[$key] = $title;
            
            if ($key === 'title') {
                $new_columns['chat_type'] = __('Type', 'workcity-chat');
                $new_columns['participants'] = __('Participants', 'workcity-chat');
                $new_columns['status'] = __('Status', 'workcity-chat');
                $new_columns['last_message'] = __('Last Message', 'workcity-chat');
            }
        }
        
        return $new_columns;
    }
    
    /**
     * Custom column content
     */
    public function custom_column_content($column, $post_id) {
        global $wpdb;
        
        switch ($column) {
            case 'chat_type':
                $type = get_post_meta($post_id, '_chat_type', true);
                echo esc_html(ucfirst($type));
                break;
                
            case 'participants':
                $count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}workcity_chat_participants 
                     WHERE chat_session_id = %d AND is_active = 1",
                    $post_id
                ));
                echo intval($count);
                break;
                
            case 'status':
                $status = get_post_meta($post_id, '_chat_status', true);
                $priority = get_post_meta($post_id, '_chat_priority', true);
                
                $status_class = 'status-' . $status;
                echo '<span class="' . esc_attr($status_class) . '">' . esc_html(ucfirst($status)) . '</span>';
                
                if ($priority === 'high' || $priority === 'urgent') {
                    echo ' <span class="priority-' . esc_attr($priority) . '">!' . esc_html(ucfirst($priority)) . '</span>';
                }
                break;
                
            case 'last_message':
                $last_message = $wpdb->get_row($wpdb->prepare(
                    "SELECT created_at, sender_id FROM {$wpdb->prefix}workcity_chat_messages 
                     WHERE chat_session_id = %d ORDER BY created_at DESC LIMIT 1",
                    $post_id
                ));
                
                if ($last_message) {
                    $user = get_userdata($last_message->sender_id);
                    echo esc_html($user->display_name) . '<br>';
                    echo '<small>' . esc_html(human_time_diff(strtotime($last_message->created_at), current_time('timestamp'))) . ' ago</small>';
                } else {
                    echo '—';
                }
                break;
        }
    }
}