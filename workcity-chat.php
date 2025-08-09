<?php
/**
 * Plugin Name: WorkCity Chat System
 * Plugin URI: https://github.com/workcityafrica/workcity-wordpress-chat
 * Description: A robust, scalable chat system for eCommerce platforms with WooCommerce integration. Supports multi-role conversations between buyers, merchants, designers, and agents.
 * Version: 1.0.0
 * Author: WorkCity Africa
 * Author URI: https://workcityafrica.com
 * Text Domain: workcity-chat
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WORKCITY_CHAT_VERSION', '1.0.0');
define('WORKCITY_CHAT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WORKCITY_CHAT_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WORKCITY_CHAT_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main WorkCity Chat Plugin Class
 */
class WorkCityChat {
    
    /**
     * Single instance of the plugin
     */
    private static $instance = null;
    
    /**
     * Get single instance
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
        $this->include_files();
        $this->init_components();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        
        // Plugin activation/deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Include required files
     */
    private function include_files() {
        // Core classes
        require_once WORKCITY_CHAT_PLUGIN_PATH . 'includes/class-chat-post-type.php';
        require_once WORKCITY_CHAT_PLUGIN_PATH . 'includes/class-chat-api.php';
        require_once WORKCITY_CHAT_PLUGIN_PATH . 'includes/class-chat-ajax.php';
        require_once WORKCITY_CHAT_PLUGIN_PATH . 'includes/class-chat-shortcode.php';
        require_once WORKCITY_CHAT_PLUGIN_PATH . 'includes/class-chat-roles.php';
        require_once WORKCITY_CHAT_PLUGIN_PATH . 'includes/class-woocommerce-integration.php';
        require_once WORKCITY_CHAT_PLUGIN_PATH . 'includes/class-notifications.php';
        
        // Admin classes
        if (is_admin()) {
            require_once WORKCITY_CHAT_PLUGIN_PATH . 'admin/class-admin.php';
        }
    }
    
    /**
     * Initialize components
     */
    private function init_components() {
        // Initialize core components
        WorkCity_Chat_Post_Type::instance();
        WorkCity_Chat_API::instance();
        WorkCity_Chat_AJAX::instance();
        WorkCity_Chat_Shortcode::instance();
        WorkCity_Chat_Roles::instance();
        WorkCity_WooCommerce_Integration::instance();
        WorkCity_Chat_Notifications::instance();
        
        // Initialize admin if in admin area
        if (is_admin()) {
            WorkCity_Chat_Admin::instance();
        }
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Load textdomain for translations
        load_plugin_textdomain('workcity-chat', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Initialize database tables
        $this->create_tables();
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_scripts() {
        wp_enqueue_style(
            'workcity-chat-frontend',
            WORKCITY_CHAT_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            WORKCITY_CHAT_VERSION
        );
        
        wp_enqueue_script(
            'workcity-chat-frontend',
            WORKCITY_CHAT_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery', 'wp-api'),
            WORKCITY_CHAT_VERSION,
            true
        );
        
        // Localize script with AJAX URL and nonce
        wp_localize_script('workcity-chat-frontend', 'workcityChat', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('workcity-chat/v1/'),
            'nonce' => wp_create_nonce('workcity_chat_nonce'),
            'currentUserId' => get_current_user_id(),
            'strings' => array(
                'sendMessage' => __('Send Message', 'workcity-chat'),
                'typing' => __('is typing...', 'workcity-chat'),
                'online' => __('Online', 'workcity-chat'),
                'offline' => __('Offline', 'workcity-chat'),
            )
        ));
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function admin_enqueue_scripts($hook) {
        // Only load on our admin pages
        if (strpos($hook, 'workcity-chat') === false) {
            return;
        }
        
        wp_enqueue_style(
            'workcity-chat-admin',
            WORKCITY_CHAT_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WORKCITY_CHAT_VERSION
        );
        
        wp_enqueue_script(
            'workcity-chat-admin',
            WORKCITY_CHAT_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            WORKCITY_CHAT_VERSION,
            true
        );
    }
    
    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Chat messages table
        $table_name = $wpdb->prefix . 'workcity_chat_messages';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            chat_session_id bigint(20) NOT NULL,
            sender_id bigint(20) NOT NULL,
            message_content longtext NOT NULL,
            message_type varchar(50) DEFAULT 'text',
            attachment_url varchar(255) DEFAULT NULL,
            is_read tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY chat_session_id (chat_session_id),
            KEY sender_id (sender_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Chat participants table
        $participants_table = $wpdb->prefix . 'workcity_chat_participants';
        
        $sql .= "CREATE TABLE $participants_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            chat_session_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            role varchar(50) NOT NULL,
            joined_at datetime DEFAULT CURRENT_TIMESTAMP,
            last_seen datetime DEFAULT CURRENT_TIMESTAMP,
            is_active tinyint(1) DEFAULT 1,
            PRIMARY KEY (id),
            UNIQUE KEY unique_participant (chat_session_id, user_id),
            KEY user_id (user_id),
            KEY role (role)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create tables
        $this->create_tables();
        
        // Create upload directory for chat files
        $upload_dir = wp_upload_dir();
        $chat_dir = $upload_dir['basedir'] . '/workcity-chat';
        
        if (!file_exists($chat_dir)) {
            wp_mkdir_p($chat_dir);
            
            // Add .htaccess for security
            $htaccess_content = "Options -Indexes\n";
            $htaccess_content .= "<Files *.php>\n";
            $htaccess_content .= "deny from all\n";
            $htaccess_content .= "</Files>\n";
            
            file_put_contents($chat_dir . '/.htaccess', $htaccess_content);
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}

// Initialize the plugin
function workcity_chat() {
    return WorkCityChat::instance();
}

// Start the plugin
workcity_chat();