<?php
/**
 * Chat Shortcode
 * 
 * Handles the chat widget shortcode for embedding
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WorkCity_Chat_Shortcode {
    
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
        add_action('init', array($this, 'register_shortcodes'));
        add_action('wp_footer', array($this, 'add_chat_modal'));
    }
    
    /**
     * Register shortcodes
     */
    public function register_shortcodes() {
        add_shortcode('workcity_chat', array($this, 'chat_widget_shortcode'));
        add_shortcode('workcity_chat_button', array($this, 'chat_button_shortcode'));
        add_shortcode('workcity_chat_inline', array($this, 'inline_chat_shortcode'));
    }
    
    /**
     * Main chat widget shortcode
     */
    public function chat_widget_shortcode($atts) {
        $atts = shortcode_atts(array(
            'type' => 'floating', // floating, inline, button
            'position' => 'bottom-right', // bottom-right, bottom-left, top-right, top-left
            'theme' => 'light', // light, dark
            'size' => 'medium', // small, medium, large
            'auto_open' => 'false',
            'product_id' => '',
            'chat_type' => 'general',
            'agent_types' => '', // comma-separated list of agent types
            'show_offline' => 'true',
            'greeting_message' => '',
            'placeholder' => '',
        ), $atts, 'workcity_chat');
        
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return $this->render_login_prompt($atts);
        }
        
        $widget_id = 'workcity-chat-widget-' . uniqid();
        
        // Enqueue scripts and styles if not already done
        if (!wp_script_is('workcity-chat-frontend', 'enqueued')) {
            wp_enqueue_script('workcity-chat-frontend');
            wp_enqueue_style('workcity-chat-frontend');
        }
        
        switch ($atts['type']) {
            case 'inline':
                return $this->render_inline_chat($widget_id, $atts);
            case 'button':
                return $this->render_chat_button($widget_id, $atts);
            default:
                return $this->render_floating_widget($widget_id, $atts);
        }
    }
    
    /**
     * Chat button shortcode
     */
    public function chat_button_shortcode($atts) {
        $atts = shortcode_atts(array(
            'text' => __('Start Chat', 'workcity-chat'),
            'class' => 'btn btn-primary',
            'icon' => 'dashicons-format-chat',
            'chat_type' => 'general',
            'product_id' => '',
        ), $atts, 'workcity_chat_button');
        
        if (!is_user_logged_in()) {
            return $this->render_login_prompt($atts);
        }
        
        $button_id = 'chat-button-' . uniqid();
        
        ob_start();
        ?>
        <button 
            id="<?php echo esc_attr($button_id); ?>"
            class="workcity-chat-trigger-btn <?php echo esc_attr($atts['class']); ?>"
            data-chat-type="<?php echo esc_attr($atts['chat_type']); ?>"
            data-product-id="<?php echo esc_attr($atts['product_id']); ?>"
            type="button"
        >
            <?php if ($atts['icon']): ?>
                <span class="<?php echo esc_attr($atts['icon']); ?>"></span>
            <?php endif; ?>
            <?php echo esc_html($atts['text']); ?>
        </button>
        
        <script>
        jQuery(document).ready(function($) {
            $('#<?php echo esc_js($button_id); ?>').on('click', function() {
                var chatType = $(this).data('chat-type');
                var productId = $(this).data('product-id');
                
                if (typeof WorkCityChat !== 'undefined') {
                    WorkCityChat.openNewChat(chatType, productId);
                }
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Inline chat shortcode
     */
    public function inline_chat_shortcode($atts) {
        $atts = shortcode_atts(array(
            'height' => '400px',
            'session_id' => '',
            'chat_type' => 'general',
            'product_id' => '',
            'theme' => 'light',
        ), $atts, 'workcity_chat_inline');
        
        if (!is_user_logged_in()) {
            return $this->render_login_prompt($atts);
        }
        
        $widget_id = 'inline-chat-' . uniqid();
        
        return $this->render_inline_chat($widget_id, $atts);
    }
    
    /**
     * Render floating widget
     */
    private function render_floating_widget($widget_id, $atts) {
        $position_class = 'position-' . str_replace('-', ' ', $atts['position']);
        $theme_class = 'theme-' . $atts['theme'];
        $size_class = 'size-' . $atts['size'];
        
        ob_start();
        ?>
        <div 
            id="<?php echo esc_attr($widget_id); ?>"
            class="workcity-chat-floating-widget <?php echo esc_attr($position_class . ' ' . $theme_class . ' ' . $size_class); ?>"
            data-chat-type="<?php echo esc_attr($atts['chat_type']); ?>"
            data-product-id="<?php echo esc_attr($atts['product_id']); ?>"
            data-auto-open="<?php echo esc_attr($atts['auto_open']); ?>"
            data-agent-types="<?php echo esc_attr($atts['agent_types']); ?>"
            data-show-offline="<?php echo esc_attr($atts['show_offline']); ?>"
        >
            <!-- Chat Toggle Button -->
            <div class="chat-toggle-btn">
                <span class="chat-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/>
                    </svg>
                </span>
                <span class="close-icon" style="display: none;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                    </svg>
                </span>
                <?php if ($this->get_unread_count() > 0): ?>
                    <span class="unread-badge"><?php echo $this->get_unread_count(); ?></span>
                <?php endif; ?>
            </div>
            
            <!-- Chat Window -->
            <div class="chat-window" style="display: none;">
                <div class="chat-header">
                    <div class="chat-title">
                        <h4><?php _e('Chat Support', 'workcity-chat'); ?></h4>
                        <div class="status-indicator">
                            <span class="status-dot online"></span>
                            <span class="status-text"><?php _e('Online', 'workcity-chat'); ?></span>
                        </div>
                    </div>
                    <div class="chat-actions">
                        <button class="minimize-btn" title="<?php esc_attr_e('Minimize', 'workcity-chat'); ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M19 13H5v-2h14v2z"/>
                            </svg>
                        </button>
                        <button class="close-btn" title="<?php esc_attr_e('Close', 'workcity-chat'); ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <div class="chat-content">
                    <!-- This will be populated by JavaScript -->
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            if (typeof WorkCityChat !== 'undefined') {
                WorkCityChat.initFloatingWidget('<?php echo esc_js($widget_id); ?>');
                
                <?php if ($atts['auto_open'] === 'true'): ?>
                    setTimeout(function() {
                        WorkCityChat.openWidget('<?php echo esc_js($widget_id); ?>');
                    }, 2000);
                <?php endif; ?>
            }
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render inline chat
     */
    private function render_inline_chat($widget_id, $atts) {
        ob_start();
        ?>
        <div 
            id="<?php echo esc_attr($widget_id); ?>"
            class="workcity-chat-inline theme-<?php echo esc_attr($atts['theme']); ?>"
            style="height: <?php echo esc_attr($atts['height']); ?>;"
            data-session-id="<?php echo esc_attr($atts['session_id']); ?>"
            data-chat-type="<?php echo esc_attr($atts['chat_type']); ?>"
            data-product-id="<?php echo esc_attr($atts['product_id']); ?>"
        >
            <div class="chat-container">
                <div class="chat-header">
                    <h4><?php _e('Chat Support', 'workcity-chat'); ?></h4>
                    <div class="chat-actions">
                        <button class="refresh-btn" title="<?php esc_attr_e('Refresh', 'workcity-chat'); ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <div class="chat-messages">
                    <!-- Messages will be loaded here -->
                </div>
                
                <div class="chat-input-area">
                    <div class="typing-indicator" style="display: none;">
                        <span class="typing-dots">
                            <span></span>
                            <span></span>
                            <span></span>
                        </span>
                        <span class="typing-text"><?php _e('Someone is typing...', 'workcity-chat'); ?></span>
                    </div>
                    
                    <form class="chat-input-form">
                        <div class="input-group">
                            <button type="button" class="attach-btn" title="<?php esc_attr_e('Attach File', 'workcity-chat'); ?>">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M16.5 6v11.5c0 2.21-1.79 4-4 4s-4-1.79-4-4V5c0-1.38 1.12-2.5 2.5-2.5s2.5 1.12 2.5 2.5v10.5c0 .55-.45 1-1 1s-1-.45-1-1V6H10v9.5c0 1.38 1.12 2.5 2.5 2.5s2.5-1.12 2.5-2.5V5c0-2.21-1.79-4-4-4S7 2.79 7 5v12.5c0 3.04 2.46 5.5 5.5 5.5s5.5-2.46 5.5-5.5V6h-1.5z"/>
                                </svg>
                            </button>
                            <textarea 
                                class="chat-input" 
                                placeholder="<?php echo esc_attr($atts['placeholder'] ?: __('Type your message...', 'workcity-chat')); ?>"
                                rows="1"
                            ></textarea>
                            <button type="submit" class="send-btn">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                                </svg>
                            </button>
                        </div>
                    </form>
                    
                    <input type="file" class="file-input" style="display: none;" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.txt,.zip">
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            if (typeof WorkCityChat !== 'undefined') {
                WorkCityChat.initInlineChat('<?php echo esc_js($widget_id); ?>');
            }
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render chat button
     */
    private function render_chat_button($widget_id, $atts) {
        ob_start();
        ?>
        <button 
            id="<?php echo esc_attr($widget_id); ?>"
            class="workcity-chat-button"
            data-chat-type="<?php echo esc_attr($atts['chat_type']); ?>"
            data-product-id="<?php echo esc_attr($atts['product_id']); ?>"
            type="button"
        >
            <span class="button-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/>
                </svg>
            </span>
            <span class="button-text"><?php _e('Start Chat', 'workcity-chat'); ?></span>
        </button>
        
        <script>
        jQuery(document).ready(function($) {
            $('#<?php echo esc_js($widget_id); ?>').on('click', function() {
                var chatType = $(this).data('chat-type');
                var productId = $(this).data('product-id');
                
                if (typeof WorkCityChat !== 'undefined') {
                    WorkCityChat.openNewChat(chatType, productId);
                }
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render login prompt
     */
    private function render_login_prompt($atts) {
        $login_url = wp_login_url(get_permalink());
        $register_url = wp_registration_url();
        
        ob_start();
        ?>
        <div class="workcity-chat-login-prompt">
            <div class="prompt-content">
                <div class="prompt-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/>
                    </svg>
                </div>
                <h3><?php _e('Start a Conversation', 'workcity-chat'); ?></h3>
                <p><?php _e('Please log in to start chatting with our support team.', 'workcity-chat'); ?></p>
                <div class="prompt-actions">
                    <a href="<?php echo esc_url($login_url); ?>" class="btn btn-primary">
                        <?php _e('Login', 'workcity-chat'); ?>
                    </a>
                    <?php if (get_option('users_can_register')): ?>
                        <a href="<?php echo esc_url($register_url); ?>" class="btn btn-secondary">
                            <?php _e('Register', 'workcity-chat'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Add chat modal to footer
     */
    public function add_chat_modal() {
        if (!is_user_logged_in()) {
            return;
        }
        
        ?>
        <!-- WorkCity Chat Modal -->
        <div id="workcity-chat-modal" class="workcity-chat-modal" style="display: none;">
            <div class="modal-overlay"></div>
            <div class="modal-content">
                <div class="modal-header">
                    <h3><?php _e('Chat Sessions', 'workcity-chat'); ?></h3>
                    <button class="modal-close">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                        </svg>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="chat-sessions-list">
                        <!-- Sessions will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
        
        <!-- New Chat Modal -->
        <div id="workcity-new-chat-modal" class="workcity-chat-modal" style="display: none;">
            <div class="modal-overlay"></div>
            <div class="modal-content">
                <div class="modal-header">
                    <h3><?php _e('Start New Chat', 'workcity-chat'); ?></h3>
                    <button class="modal-close">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                        </svg>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="new-chat-form">
                        <div class="form-group">
                            <label for="chat-title"><?php _e('Chat Subject', 'workcity-chat'); ?></label>
                            <input type="text" id="chat-title" name="title" required placeholder="<?php esc_attr_e('What can we help you with?', 'workcity-chat'); ?>">
                        </div>
                        <div class="form-group">
                            <label for="chat-type"><?php _e('Type of Support', 'workcity-chat'); ?></label>
                            <select id="chat-type" name="chat_type" required>
                                <option value="general"><?php _e('General Support', 'workcity-chat'); ?></option>
                                <option value="product"><?php _e('Product Question', 'workcity-chat'); ?></option>
                                <option value="order"><?php _e('Order Support', 'workcity-chat'); ?></option>
                                <option value="design"><?php _e('Design Consultation', 'workcity-chat'); ?></option>
                                <option value="merchant"><?php _e('Merchant Support', 'workcity-chat'); ?></option>
                            </select>
                        </div>
                        <div class="form-group" id="product-selection" style="display: none;">
                            <label for="product-id"><?php _e('Related Product', 'workcity-chat'); ?></label>
                            <select id="product-id" name="product_id">
                                <option value=""><?php _e('Select a product (optional)', 'workcity-chat'); ?></option>
                                <!-- Products will be loaded via AJAX -->
                            </select>
                        </div>
                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary modal-close"><?php _e('Cancel', 'workcity-chat'); ?></button>
                            <button type="submit" class="btn btn-primary"><?php _e('Start Chat', 'workcity-chat'); ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get unread message count for current user
     */
    private function get_unread_count() {
        if (!is_user_logged_in()) {
            return 0;
        }
        
        global $wpdb;
        $user_id = get_current_user_id();
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT m.id)
             FROM {$wpdb->prefix}workcity_chat_messages m
             JOIN {$wpdb->prefix}workcity_chat_participants p ON m.chat_session_id = p.chat_session_id
             WHERE p.user_id = %d 
             AND p.is_active = 1
             AND m.sender_id != %d 
             AND m.is_read = 0",
            $user_id,
            $user_id
        ));
        
        return intval($count);
    }
}