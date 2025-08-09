<?php
/**
 * WooCommerce Integration
 * 
 * Handles integration with WooCommerce for product-context chats
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WorkCity_WooCommerce_Integration {
    
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
        // Only initialize if WooCommerce is active
        if (!$this->is_woocommerce_active()) {
            return;
        }
        
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Product page integration
        add_action('woocommerce_single_product_summary', array($this, 'add_product_chat_button'), 35);
        add_action('woocommerce_after_single_product_summary', array($this, 'add_product_chat_widget'), 15);
        
        // Cart and checkout integration
        add_action('woocommerce_proceed_to_checkout', array($this, 'add_cart_chat_button'), 5);
        add_action('woocommerce_checkout_before_customer_details', array($this, 'add_checkout_chat_widget'));
        
        // Order integration
        add_action('woocommerce_view_order', array($this, 'add_order_chat_section'), 20);
        add_action('woocommerce_thankyou', array($this, 'add_order_success_chat'), 10);
        
        // My Account integration
        add_action('woocommerce_account_dashboard', array($this, 'add_account_chat_section'));
        
        // Admin order integration
        add_action('add_meta_boxes', array($this, 'add_order_chat_meta_box'));
        
        // AJAX handlers for WooCommerce-specific functionality
        add_action('wp_ajax_workcity_get_woocommerce_products', array($this, 'get_products_ajax'));
        add_action('wp_ajax_workcity_get_customer_orders', array($this, 'get_customer_orders_ajax'));
        add_action('wp_ajax_workcity_create_product_chat', array($this, 'create_product_chat_ajax'));
        
        // Email integration
        add_action('woocommerce_email_before_order_table', array($this, 'add_chat_link_to_email'), 10, 4);
        
        // Chat session meta integration
        add_filter('workcity_chat_session_meta', array($this, 'add_woocommerce_meta'), 10, 2);
    }
    
    /**
     * Check if WooCommerce is active
     */
    private function is_woocommerce_active() {
        return class_exists('WooCommerce');
    }
    
    /**
     * Add chat button to product page
     */
    public function add_product_chat_button() {
        global $product;
        
        if (!$product || !is_user_logged_in()) {
            return;
        }
        
        $product_id = $product->get_id();
        $product_title = $product->get_name();
        
        echo '<div class="workcity-product-chat-section">';
        echo do_shortcode('[workcity_chat_button text="' . sprintf(__('Ask about %s', 'workcity-chat'), $product_title) . '" chat_type="product" product_id="' . $product_id . '" class="btn btn-outline-primary workcity-product-chat-btn"]');
        echo '</div>';
    }
    
    /**
     * Add chat widget to product page
     */
    public function add_product_chat_widget() {
        global $product;
        
        if (!$product || !is_user_logged_in()) {
            return;
        }
        
        $product_id = $product->get_id();
        
        echo '<div class="workcity-product-chat-widget">';
        echo '<h3>' . __('Need Help with This Product?', 'workcity-chat') . '</h3>';
        echo '<p>' . __('Our product specialists are here to help. Start a chat to get expert advice.', 'workcity-chat') . '</p>';
        echo do_shortcode('[workcity_chat type="inline" chat_type="product" product_id="' . $product_id . '" height="300px"]');
        echo '</div>';
    }
    
    /**
     * Add chat button to cart page
     */
    public function add_cart_chat_button() {
        if (!is_user_logged_in()) {
            return;
        }
        
        echo '<div class="workcity-cart-chat-section">';
        echo '<p class="cart-chat-help">' . __('Need help with your order?', 'workcity-chat') . '</p>';
        echo do_shortcode('[workcity_chat_button text="' . __('Chat with Support', 'workcity-chat') . '" chat_type="order" class="btn btn-secondary"]');
        echo '</div>';
    }
    
    /**
     * Add chat widget to checkout page
     */
    public function add_checkout_chat_widget() {
        if (!is_user_logged_in()) {
            return;
        }
        
        echo '<div class="workcity-checkout-chat-widget">';
        echo '<div class="checkout-chat-toggle">';
        echo '<button type="button" class="checkout-chat-btn">';
        echo '<span class="dashicons dashicons-format-chat"></span>';
        echo __('Need Help?', 'workcity-chat');
        echo '</button>';
        echo '</div>';
        echo '<div class="checkout-chat-content" style="display: none;">';
        echo do_shortcode('[workcity_chat type="inline" chat_type="order" height="250px"]');
        echo '</div>';
        echo '</div>';
        
        // Add JavaScript for toggle
        ?>
        <script>
        jQuery(document).ready(function($) {
            $('.checkout-chat-btn').on('click', function() {
                $('.checkout-chat-content').slideToggle();
                $(this).toggleClass('active');
            });
        });
        </script>
        <?php
    }
    
    /**
     * Add chat section to order view page
     */
    public function add_order_chat_section($order_id) {
        if (!is_user_logged_in()) {
            return;
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        // Check if there's already a chat for this order
        $existing_chat = $this->get_order_chat_session($order_id);
        
        echo '<div class="workcity-order-chat-section">';
        echo '<h2>' . __('Order Support', 'workcity-chat') . '</h2>';
        
        if ($existing_chat) {
            echo '<p>' . __('You have an active chat session for this order:', 'workcity-chat') . '</p>';
            echo '<div class="existing-chat-link">';
            echo '<a href="#" class="btn btn-primary open-chat-session" data-session-id="' . $existing_chat->ID . '">';
            echo __('Continue Chat', 'workcity-chat') . '</a>';
            echo '</div>';
        } else {
            echo '<p>' . __('Need help with this order? Start a chat with our support team.', 'workcity-chat') . '</p>';
            echo do_shortcode('[workcity_chat_button text="' . __('Start Order Support Chat', 'workcity-chat') . '" chat_type="order" class="btn btn-primary"]');
        }
        
        echo '</div>';
    }
    
    /**
     * Add chat option to order success page
     */
    public function add_order_success_chat($order_id) {
        if (!is_user_logged_in()) {
            return;
        }
        
        echo '<div class="workcity-order-success-chat">';
        echo '<div class="chat-success-notice">';
        echo '<h3>' . __('Questions About Your Order?', 'workcity-chat') . '</h3>';
        echo '<p>' . __('Our team is here to help if you have any questions about your order or need assistance.', 'workcity-chat') . '</p>';
        echo do_shortcode('[workcity_chat_button text="' . __('Contact Support', 'workcity-chat') . '" chat_type="order" class="btn btn-outline-primary"]');
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Add chat section to My Account dashboard
     */
    public function add_account_chat_section() {
        if (!is_user_logged_in()) {
            return;
        }
        
        $user_id = get_current_user_id();
        $recent_chats = $this->get_user_recent_chats($user_id, 3);
        
        echo '<div class="workcity-account-chat-section">';
        echo '<h3>' . __('Support & Chat', 'workcity-chat') . '</h3>';
        
        if ($recent_chats) {
            echo '<div class="recent-chats">';
            echo '<h4>' . __('Recent Conversations', 'workcity-chat') . '</h4>';
            echo '<ul class="chat-list">';
            
            foreach ($recent_chats as $chat) {
                $chat_meta = get_post_meta($chat->ID, '_chat_type', true);
                $unread_count = $this->get_session_unread_count($chat->ID, $user_id);
                
                echo '<li class="chat-item">';
                echo '<a href="#" class="chat-link" data-session-id="' . $chat->ID . '">';
                echo '<span class="chat-title">' . esc_html($chat->post_title) . '</span>';
                echo '<span class="chat-type">' . esc_html(ucfirst($chat_meta)) . '</span>';
                if ($unread_count > 0) {
                    echo '<span class="unread-badge">' . $unread_count . '</span>';
                }
                echo '</a>';
                echo '</li>';
            }
            
            echo '</ul>';
            echo '</div>';
        }
        
        echo '<div class="chat-actions">';
        echo do_shortcode('[workcity_chat_button text="' . __('Start New Chat', 'workcity-chat') . '" class="btn btn-primary"]');
        echo '<a href="#" class="view-all-chats">' . __('View All Conversations', 'workcity-chat') . '</a>';
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Add chat meta box to order admin page
     */
    public function add_order_chat_meta_box() {
        add_meta_box(
            'workcity-order-chat',
            __('Order Chat Sessions', 'workcity-chat'),
            array($this, 'render_order_chat_meta_box'),
            'shop_order',
            'side',
            'default'
        );
    }
    
    /**
     * Render order chat meta box
     */
    public function render_order_chat_meta_box($post) {
        $order = wc_get_order($post->ID);
        if (!$order) {
            return;
        }
        
        $customer_id = $order->get_customer_id();
        $order_chats = $this->get_order_chat_sessions($post->ID);
        
        if ($order_chats) {
            echo '<div class="order-chat-sessions">';
            foreach ($order_chats as $chat) {
                $chat_status = get_post_meta($chat->ID, '_chat_status', true);
                $last_message = $this->get_last_message($chat->ID);
                
                echo '<div class="chat-session-item">';
                echo '<h4><a href="' . admin_url('post.php?post=' . $chat->ID . '&action=edit') . '">' . esc_html($chat->post_title) . '</a></h4>';
                echo '<p class="chat-status">Status: <strong>' . esc_html(ucfirst($chat_status)) . '</strong></p>';
                
                if ($last_message) {
                    echo '<p class="last-message">';
                    echo '<small>Last message: ' . human_time_diff(strtotime($last_message->created_at), current_time('timestamp')) . ' ago</small>';
                    echo '</p>';
                }
                
                echo '</div>';
            }
            echo '</div>';
        } else {
            echo '<p>' . __('No chat sessions for this order.', 'workcity-chat') . '</p>';
        }
        
        if ($customer_id) {
            echo '<div class="create-order-chat">';
            echo '<a href="#" class="button create-order-chat-btn" data-order-id="' . $post->ID . '" data-customer-id="' . $customer_id . '">';
            echo __('Start Chat with Customer', 'workcity-chat');
            echo '</a>';
            echo '</div>';
        }
    }
    
    /**
     * AJAX: Get WooCommerce products
     */
    public function get_products_ajax() {
        check_ajax_referer('workcity_chat_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_die('Unauthorized', 401);
        }
        
        $search = sanitize_text_field($_POST['search'] ?? '');
        $limit = intval($_POST['limit'] ?? 20);
        
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'meta_query' => array(
                array(
                    'key' => '_visibility',
                    'value' => array('visible', 'catalog'),
                    'compare' => 'IN'
                )
            )
        );
        
        if (!empty($search)) {
            $args['s'] = $search;
        }
        
        $products = get_posts($args);
        $formatted_products = array();
        
        foreach ($products as $product_post) {
            $product = wc_get_product($product_post->ID);
            if ($product) {
                $formatted_products[] = array(
                    'id' => $product->get_id(),
                    'title' => $product->get_name(),
                    'sku' => $product->get_sku(),
                    'price' => $product->get_price_html(),
                    'image' => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail'),
                    'permalink' => $product->get_permalink(),
                );
            }
        }
        
        wp_send_json_success($formatted_products);
    }
    
    /**
     * AJAX: Get customer orders
     */
    public function get_customer_orders_ajax() {
        check_ajax_referer('workcity_chat_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_die('Unauthorized', 401);
        }
        
        $customer_id = intval($_POST['customer_id'] ?? get_current_user_id());
        $limit = intval($_POST['limit'] ?? 10);
        
        // Only allow users to see their own orders unless they're admin
        if ($customer_id !== get_current_user_id() && !current_user_can('manage_woocommerce')) {
            wp_send_json_error('Not authorized to view these orders');
        }
        
        $orders = wc_get_orders(array(
            'customer_id' => $customer_id,
            'limit' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
        ));
        
        $formatted_orders = array();
        
        foreach ($orders as $order) {
            $formatted_orders[] = array(
                'id' => $order->get_id(),
                'number' => $order->get_order_number(),
                'status' => $order->get_status(),
                'total' => $order->get_formatted_order_total(),
                'date' => $order->get_date_created()->date('Y-m-d H:i:s'),
                'formatted_date' => $order->get_date_created()->date_i18n(get_option('date_format')),
                'items_count' => $order->get_item_count(),
            );
        }
        
        wp_send_json_success($formatted_orders);
    }
    
    /**
     * AJAX: Create product-specific chat
     */
    public function create_product_chat_ajax() {
        check_ajax_referer('workcity_chat_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_die('Unauthorized', 401);
        }
        
        $product_id = intval($_POST['product_id']);
        $message = wp_kses_post($_POST['message'] ?? '');
        
        if (!$product_id) {
            wp_send_json_error('Product ID is required');
        }
        
        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error('Product not found');
        }
        
        $user_id = get_current_user_id();
        
        // Create chat session
        $session_id = wp_insert_post(array(
            'post_title' => sprintf(__('Product Question: %s', 'workcity-chat'), $product->get_name()),
            'post_type' => 'chat_session',
            'post_status' => 'publish',
            'post_author' => $user_id,
        ));
        
        if (is_wp_error($session_id)) {
            wp_send_json_error('Failed to create chat session');
        }
        
        // Save meta data
        update_post_meta($session_id, '_chat_type', 'product');
        update_post_meta($session_id, '_chat_status', 'active');
        update_post_meta($session_id, '_chat_priority', 'normal');
        update_post_meta($session_id, '_product_id', $product_id);
        
        // Add user as participant
        global $wpdb;
        
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
        
        // Add initial message if provided
        if (!empty($message)) {
            $wpdb->insert(
                $wpdb->prefix . 'workcity_chat_messages',
                array(
                    'chat_session_id' => $session_id,
                    'sender_id' => $user_id,
                    'message_content' => $message,
                    'message_type' => 'text',
                    'is_read' => 0,
                    'created_at' => current_time('mysql'),
                ),
                array('%d', '%d', '%s', '%s', '%d', '%s')
            );
        }
        
        // Auto-assign product specialist
        $chat_roles = WorkCity_Chat_Roles::instance();
        $agent_id = $chat_roles->auto_assign_agent($session_id, 'product');
        
        wp_send_json_success(array(
            'session_id' => $session_id,
            'agent_assigned' => $agent_id ? true : false,
            'message' => 'Product chat session created successfully',
        ));
    }
    
    /**
     * Add chat link to WooCommerce emails
     */
    public function add_chat_link_to_email($order, $sent_to_admin, $plain_text, $email) {
        if ($sent_to_admin || $plain_text) {
            return;
        }
        
        if (!in_array($email->id, array('customer_processing_order', 'customer_completed_order', 'customer_on_hold_order'))) {
            return;
        }
        
        $order_id = $order->get_id();
        $customer_id = $order->get_customer_id();
        
        if (!$customer_id) {
            return;
        }
        
        echo '<div style="margin: 20px 0; padding: 15px; background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px;">';
        echo '<h3 style="margin-top: 0;">' . __('Need Help?', 'workcity-chat') . '</h3>';
        echo '<p>' . __('If you have any questions about your order, our support team is here to help.', 'workcity-chat') . '</p>';
        
        $chat_url = add_query_arg(array(
            'action' => 'start_order_chat',
            'order_id' => $order_id,
            'nonce' => wp_create_nonce('order_chat_' . $order_id),
        ), home_url());
        
        echo '<a href="' . esc_url($chat_url) . '" style="display: inline-block; padding: 10px 20px; background-color: #007cba; color: white; text-decoration: none; border-radius: 4px;">';
        echo __('Chat with Support', 'workcity-chat');
        echo '</a>';
        echo '</div>';
    }
    
    /**
     * Add WooCommerce meta to chat sessions
     */
    public function add_woocommerce_meta($meta, $session_id) {
        $product_id = get_post_meta($session_id, '_product_id', true);
        $order_id = get_post_meta($session_id, '_order_id', true);
        
        if ($product_id) {
            $product = wc_get_product($product_id);
            if ($product) {
                $meta['product'] = array(
                    'id' => $product->get_id(),
                    'name' => $product->get_name(),
                    'sku' => $product->get_sku(),
                    'price' => $product->get_price_html(),
                    'permalink' => $product->get_permalink(),
                    'image' => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail'),
                );
            }
        }
        
        if ($order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
                $meta['order'] = array(
                    'id' => $order->get_id(),
                    'number' => $order->get_order_number(),
                    'status' => $order->get_status(),
                    'total' => $order->get_formatted_order_total(),
                    'date' => $order->get_date_created()->date('Y-m-d H:i:s'),
                );
            }
        }
        
        return $meta;
    }
    
    /**
     * Get chat session for an order
     */
    private function get_order_chat_session($order_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT p.* FROM {$wpdb->posts} p
             JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = 'chat_session'
             AND pm.meta_key = '_order_id'
             AND pm.meta_value = %s
             AND p.post_status = 'publish'
             ORDER BY p.post_date DESC
             LIMIT 1",
            $order_id
        ));
    }
    
    /**
     * Get all chat sessions for an order
     */
    private function get_order_chat_sessions($order_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT p.* FROM {$wpdb->posts} p
             JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = 'chat_session'
             AND pm.meta_key = '_order_id'
             AND pm.meta_value = %s
             AND p.post_status = 'publish'
             ORDER BY p.post_date DESC",
            $order_id
        ));
    }
    
    /**
     * Get user's recent chats
     */
    private function get_user_recent_chats($user_id, $limit = 5) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT p.* FROM {$wpdb->posts} p
             JOIN {$wpdb->prefix}workcity_chat_participants cp ON p.ID = cp.chat_session_id
             WHERE p.post_type = 'chat_session'
             AND cp.user_id = %d
             AND cp.is_active = 1
             AND p.post_status = 'publish'
             ORDER BY p.post_date DESC
             LIMIT %d",
            $user_id,
            $limit
        ));
    }
    
    /**
     * Get unread message count for a session
     */
    private function get_session_unread_count($session_id, $user_id) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}workcity_chat_messages
             WHERE chat_session_id = %d
             AND sender_id != %d
             AND is_read = 0",
            $session_id,
            $user_id
        ));
    }
    
    /**
     * Get last message for a chat session
     */
    private function get_last_message($session_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}workcity_chat_messages
             WHERE chat_session_id = %d
             ORDER BY created_at DESC
             LIMIT 1",
            $session_id
        ));
    }
}