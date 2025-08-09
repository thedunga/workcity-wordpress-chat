<?php
/**
 * Admin Interface
 * 
 * Handles the WordPress admin interface for the chat system
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WorkCity_Chat_Admin {
    
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
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('admin_notices', array($this, 'admin_notices'));
        
        // Dashboard widgets
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widgets'));
        
        // Admin AJAX handlers
        add_action('wp_ajax_workcity_chat_admin_stats', array($this, 'get_admin_stats'));
        add_action('wp_ajax_workcity_chat_admin_assign_agent', array($this, 'assign_agent_to_session'));
        add_action('wp_ajax_workcity_chat_admin_close_session', array($this, 'close_chat_session'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            __('WorkCity Chat', 'workcity-chat'),
            __('Chat System', 'workcity-chat'),
            'manage_options',
            'workcity-chat',
            array($this, 'admin_dashboard_page'),
            'dashicons-format-chat',
            30
        );
        
        // Dashboard submenu
        add_submenu_page(
            'workcity-chat',
            __('Dashboard', 'workcity-chat'),
            __('Dashboard', 'workcity-chat'),
            'manage_options',
            'workcity-chat',
            array($this, 'admin_dashboard_page')
        );
        
        // Live Chat submenu
        add_submenu_page(
            'workcity-chat',
            __('Live Chat', 'workcity-chat'),
            __('Live Chat', 'workcity-chat'),
            'handle_chat_sessions',
            'workcity-chat-live',
            array($this, 'admin_live_chat_page')
        );
        
        // Reports submenu
        add_submenu_page(
            'workcity-chat',
            __('Reports', 'workcity-chat'),
            __('Reports', 'workcity-chat'),
            'view_all_chats',
            'workcity-chat-reports',
            array($this, 'admin_reports_page')
        );
        
        // Settings submenu
        add_submenu_page(
            'workcity-chat',
            __('Settings', 'workcity-chat'),
            __('Settings', 'workcity-chat'),
            'manage_options',
            'workcity-chat-settings',
            array($this, 'admin_settings_page')
        );
    }
    
    /**
     * Admin initialization
     */
    public function admin_init() {
        // Register settings
        register_setting('workcity_chat_settings', 'workcity_chat_general_settings');
        register_setting('workcity_chat_settings', 'workcity_chat_appearance_settings');
        register_setting('workcity_chat_settings', 'workcity_chat_business_hours');
        register_setting('workcity_chat_settings', 'workcity_chat_auto_assignment');
        
        // Add settings sections
        add_settings_section(
            'workcity_chat_general',
            __('General Settings', 'workcity-chat'),
            array($this, 'general_settings_section_callback'),
            'workcity_chat_settings'
        );
        
        add_settings_section(
            'workcity_chat_appearance',
            __('Appearance Settings', 'workcity-chat'),
            array($this, 'appearance_settings_section_callback'),
            'workcity_chat_settings'
        );
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
            array('jquery', 'wp-api'),
            WORKCITY_CHAT_VERSION,
            true
        );
        
        // Chart.js for reports
        if ($hook === 'chat-system_page_workcity-chat-reports') {
            wp_enqueue_script(
                'chart-js',
                'https://cdn.jsdelivr.net/npm/chart.js',
                array(),
                '3.9.1',
                true
            );
        }
        
        // Localize script
        wp_localize_script('workcity-chat-admin', 'workcityChatAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('workcity-chat/v1/'),
            'nonce' => wp_create_nonce('workcity_chat_admin_nonce'),
            'strings' => array(
                'assignAgent' => __('Assign Agent', 'workcity-chat'),
                'closeSession' => __('Close Session', 'workcity-chat'),
                'confirmClose' => __('Are you sure you want to close this chat session?', 'workcity-chat'),
                'loading' => __('Loading...', 'workcity-chat'),
                'error' => __('An error occurred', 'workcity-chat'),
            )
        ));
    }
    
    /**
     * Admin notices
     */
    public function admin_notices() {
        // Check if WooCommerce is installed
        if (!class_exists('WooCommerce')) {
            echo '<div class="notice notice-warning"><p>';
            echo __('WorkCity Chat works best with WooCommerce for product-context chats. ', 'workcity-chat');
            echo '<a href="' . admin_url('plugin-install.php?s=woocommerce&tab=search&type=term') . '">';
            echo __('Install WooCommerce', 'workcity-chat');
            echo '</a>';
            echo '</p></div>';
        }
    }
    
    /**
     * Dashboard page
     */
    public function admin_dashboard_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Chat System Dashboard', 'workcity-chat'); ?></h1>
            
            <div class="workcity-chat-dashboard">
                <div class="dashboard-stats">
                    <div class="stat-boxes">
                        <div class="stat-box">
                            <h3><?php _e('Active Chats', 'workcity-chat'); ?></h3>
                            <div class="stat-number" id="active-chats-count">
                                <span class="loading">—</span>
                            </div>
                        </div>
                        
                        <div class="stat-box">
                            <h3><?php _e('Pending Chats', 'workcity-chat'); ?></h3>
                            <div class="stat-number" id="pending-chats-count">
                                <span class="loading">—</span>
                            </div>
                        </div>
                        
                        <div class="stat-box">
                            <h3><?php _e('Online Agents', 'workcity-chat'); ?></h3>
                            <div class="stat-number" id="online-agents-count">
                                <span class="loading">—</span>
                            </div>
                        </div>
                        
                        <div class="stat-box">
                            <h3><?php _e('Today\'s Chats', 'workcity-chat'); ?></h3>
                            <div class="stat-number" id="todays-chats-count">
                                <span class="loading">—</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-content">
                    <div class="dashboard-left">
                        <div class="dashboard-widget">
                            <h3><?php _e('Recent Chat Sessions', 'workcity-chat'); ?></h3>
                            <div id="recent-chats-list">
                                <div class="loading"><?php _e('Loading...', 'workcity-chat'); ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-right">
                        <div class="dashboard-widget">
                            <h3><?php _e('Agent Status', 'workcity-chat'); ?></h3>
                            <div id="agent-status-list">
                                <div class="loading"><?php _e('Loading...', 'workcity-chat'); ?></div>
                            </div>
                        </div>
                        
                        <div class="dashboard-widget">
                            <h3><?php _e('Quick Actions', 'workcity-chat'); ?></h3>
                            <div class="quick-actions">
                                <a href="<?php echo admin_url('edit.php?post_type=chat_session'); ?>" class="button button-primary">
                                    <?php _e('View All Chats', 'workcity-chat'); ?>
                                </a>
                                <a href="<?php echo admin_url('admin.php?page=workcity-chat-live'); ?>" class="button">
                                    <?php _e('Live Chat Interface', 'workcity-chat'); ?>
                                </a>
                                <a href="<?php echo admin_url('admin.php?page=workcity-chat-reports'); ?>" class="button">
                                    <?php _e('View Reports', 'workcity-chat'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Load dashboard stats
            loadDashboardStats();
            
            // Auto-refresh every 30 seconds
            setInterval(loadDashboardStats, 30000);
            
            function loadDashboardStats() {
                $.ajax({
                    url: workcityChatAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'workcity_chat_admin_stats',
                        nonce: workcityChatAdmin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            updateDashboardStats(response.data);
                        }
                    }
                });
            }
            
            function updateDashboardStats(data) {
                $('#active-chats-count .loading').text(data.active_chats || 0);
                $('#pending-chats-count .loading').text(data.pending_chats || 0);
                $('#online-agents-count .loading').text(data.online_agents || 0);
                $('#todays-chats-count .loading').text(data.todays_chats || 0);
                
                // Update recent chats list
                if (data.recent_chats && data.recent_chats.length > 0) {
                    let html = '<ul>';
                    data.recent_chats.forEach(function(chat) {
                        html += '<li>';
                        html += '<a href="' + chat.edit_url + '">' + chat.title + '</a>';
                        html += '<span class="chat-meta">' + chat.status + ' - ' + chat.time_ago + '</span>';
                        html += '</li>';
                    });
                    html += '</ul>';
                    $('#recent-chats-list').html(html);
                } else {
                    $('#recent-chats-list').html('<p>' + '<?php _e('No recent chats', 'workcity-chat'); ?>' + '</p>');
                }
                
                // Update agent status
                if (data.agent_status && data.agent_status.length > 0) {
                    let html = '<ul>';
                    data.agent_status.forEach(function(agent) {
                        let statusClass = agent.is_online ? 'online' : 'offline';
                        html += '<li class="agent-status-item ' + statusClass + '">';
                        html += '<span class="agent-name">' + agent.name + '</span>';
                        html += '<span class="agent-chats">(' + agent.active_chats + ' chats)</span>';
                        html += '<span class="status-indicator"></span>';
                        html += '</li>';
                    });
                    html += '</ul>';
                    $('#agent-status-list').html(html);
                } else {
                    $('#agent-status-list').html('<p>' + '<?php _e('No agents available', 'workcity-chat'); ?>' + '</p>');
                }
            }
        });
        </script>
        <?php
    }
    
    /**
     * Live chat page
     */
    public function admin_live_chat_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Live Chat Interface', 'workcity-chat'); ?></h1>
            
            <div class="workcity-live-chat-interface">
                <div class="chat-sessions-sidebar">
                    <div class="sidebar-header">
                        <h3><?php _e('Chat Sessions', 'workcity-chat'); ?></h3>
                        <div class="session-filters">
                            <select id="session-filter">
                                <option value="all"><?php _e('All Sessions', 'workcity-chat'); ?></option>
                                <option value="active"><?php _e('Active', 'workcity-chat'); ?></option>
                                <option value="pending"><?php _e('Pending', 'workcity-chat'); ?></option>
                                <option value="my"><?php _e('My Sessions', 'workcity-chat'); ?></option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="sessions-list" id="chat-sessions-list">
                        <div class="loading"><?php _e('Loading sessions...', 'workcity-chat'); ?></div>
                    </div>
                </div>
                
                <div class="chat-interface-main">
                    <div class="no-session-selected">
                        <div class="empty-state">
                            <h3><?php _e('Select a Chat Session', 'workcity-chat'); ?></h3>
                            <p><?php _e('Choose a chat session from the sidebar to start chatting.', 'workcity-chat'); ?></p>
                        </div>
                    </div>
                    
                    <div class="chat-session-container" id="chat-session-container" style="display: none;">
                        <!-- Chat interface will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            let currentSessionId = null;
            
            // Load sessions on page load
            loadChatSessions();
            
            // Filter change handler
            $('#session-filter').on('change', function() {
                loadChatSessions();
            });
            
            // Auto-refresh sessions every 10 seconds
            setInterval(loadChatSessions, 10000);
            
            function loadChatSessions() {
                const filter = $('#session-filter').val();
                
                $.ajax({
                    url: workcityChatAdmin.restUrl + 'admin/sessions',
                    type: 'GET',
                    data: { filter: filter },
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', workcityChatAdmin.nonce);
                    },
                    success: function(sessions) {
                        renderSessionsList(sessions);
                    }
                });
            }
            
            function renderSessionsList(sessions) {
                let html = '';
                
                if (sessions.length === 0) {
                    html = '<div class="no-sessions"><?php _e('No chat sessions found', 'workcity-chat'); ?></div>';
                } else {
                    sessions.forEach(function(session) {
                        let unreadClass = session.unread_count > 0 ? 'has-unread' : '';
                        let activeClass = session.id == currentSessionId ? 'active' : '';
                        
                        html += '<div class="session-item ' + unreadClass + ' ' + activeClass + '" data-session-id="' + session.id + '">';
                        html += '<div class="session-header">';
                        html += '<h4>' + session.title + '</h4>';
                        html += '<span class="session-status status-' + session.status + '">' + session.status + '</span>';
                        html += '</div>';
                        html += '<div class="session-meta">';
                        html += '<span class="session-type">' + session.chat_type + '</span>';
                        html += '<span class="session-time">' + session.formatted_date + '</span>';
                        html += '</div>';
                        if (session.last_message) {
                            html += '<div class="last-message">';
                            html += '<span class="sender">' + session.last_message.sender_name + ':</span> ';
                            html += '<span class="content">' + session.last_message.content + '</span>';
                            html += '</div>';
                        }
                        if (session.unread_count > 0) {
                            html += '<div class="unread-badge">' + session.unread_count + '</div>';
                        }
                        html += '</div>';
                    });
                }
                
                $('#chat-sessions-list').html(html);
                
                // Add click handlers
                $('.session-item').on('click', function() {
                    const sessionId = $(this).data('session-id');
                    loadChatSession(sessionId);
                });
            }
            
            function loadChatSession(sessionId) {
                currentSessionId = sessionId;
                
                $('.session-item').removeClass('active');
                $('.session-item[data-session-id="' + sessionId + '"]').addClass('active');
                
                $('.no-session-selected').hide();
                $('#chat-session-container').show().html('<div class="loading"><?php _e('Loading chat...', 'workcity-chat'); ?></div>');
                
                // Load chat interface via AJAX or iframe
                $('#chat-session-container').html(
                    '<iframe src="' + workcityChatAdmin.chatInterfaceUrl + '?session_id=' + sessionId + '" ' +
                    'width="100%" height="600px" frameborder="0"></iframe>'
                );
            }
        });
        </script>
        <?php
    }
    
    /**
     * Reports page
     */
    public function admin_reports_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Chat Reports', 'workcity-chat'); ?></h1>
            
            <div class="workcity-chat-reports">
                <div class="reports-filters">
                    <form method="get" action="">
                        <input type="hidden" name="page" value="workcity-chat-reports">
                        
                        <label for="date-range"><?php _e('Date Range:', 'workcity-chat'); ?></label>
                        <select name="date_range" id="date-range">
                            <option value="7"><?php _e('Last 7 days', 'workcity-chat'); ?></option>
                            <option value="30"><?php _e('Last 30 days', 'workcity-chat'); ?></option>
                            <option value="90"><?php _e('Last 90 days', 'workcity-chat'); ?></option>
                            <option value="custom"><?php _e('Custom Range', 'workcity-chat'); ?></option>
                        </select>
                        
                        <div id="custom-date-range" style="display: none;">
                            <input type="date" name="start_date" id="start-date">
                            <input type="date" name="end_date" id="end-date">
                        </div>
                        
                        <button type="submit" class="button"><?php _e('Update Report', 'workcity-chat'); ?></button>
                    </form>
                </div>
                
                <div class="reports-content">
                    <div class="report-section">
                        <h3><?php _e('Chat Volume', 'workcity-chat'); ?></h3>
                        <canvas id="chat-volume-chart" width="400" height="200"></canvas>
                    </div>
                    
                    <div class="report-section">
                        <h3><?php _e('Response Times', 'workcity-chat'); ?></h3>
                        <canvas id="response-time-chart" width="400" height="200"></canvas>
                    </div>
                    
                    <div class="report-section">
                        <h3><?php _e('Agent Performance', 'workcity-chat'); ?></h3>
                        <div id="agent-performance-table">
                            <!-- Agent performance data will be loaded here -->
                        </div>
                    </div>
                    
                    <div class="report-section">
                        <h3><?php _e('Chat Types Distribution', 'workcity-chat'); ?></h3>
                        <canvas id="chat-types-chart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#date-range').on('change', function() {
                if ($(this).val() === 'custom') {
                    $('#custom-date-range').show();
                } else {
                    $('#custom-date-range').hide();
                }
            });
            
            // Load reports data
            loadReportsData();
            
            function loadReportsData() {
                // This would load actual report data and render charts
                // For now, showing placeholder implementation
                console.log('Loading reports data...');
            }
        });
        </script>
        <?php
    }
    
    /**
     * Settings page
     */
    public function admin_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Chat Settings', 'workcity-chat'); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('workcity_chat_settings'); ?>
                <?php do_settings_sections('workcity_chat_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="enable_chat"><?php _e('Enable Chat System', 'workcity-chat'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="enable_chat" name="workcity_chat_general_settings[enable_chat]" value="1" <?php checked(1, $this->get_setting('general_settings', 'enable_chat', 1)); ?>>
                            <p class="description"><?php _e('Enable or disable the entire chat system', 'workcity-chat'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="auto_assign_agents"><?php _e('Auto-assign Agents', 'workcity-chat'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="auto_assign_agents" name="workcity_chat_general_settings[auto_assign_agents]" value="1" <?php checked(1, $this->get_setting('general_settings', 'auto_assign_agents', 1)); ?>>
                            <p class="description"><?php _e('Automatically assign available agents to new chat sessions', 'workcity-chat'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="max_file_size"><?php _e('Max File Upload Size', 'workcity-chat'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="max_file_size" name="workcity_chat_general_settings[max_file_size]" value="<?php echo esc_attr($this->get_setting('general_settings', 'max_file_size', 5)); ?>" min="1" max="50">
                            <span class="description"><?php _e('MB', 'workcity-chat'); ?></span>
                            <p class="description"><?php _e('Maximum file size for chat attachments', 'workcity-chat'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="chat_theme"><?php _e('Default Theme', 'workcity-chat'); ?></label>
                        </th>
                        <td>
                            <select id="chat_theme" name="workcity_chat_appearance_settings[theme]">
                                <option value="light" <?php selected('light', $this->get_setting('appearance_settings', 'theme', 'light')); ?>><?php _e('Light', 'workcity-chat'); ?></option>
                                <option value="dark" <?php selected('dark', $this->get_setting('appearance_settings', 'theme', 'light')); ?>><?php _e('Dark', 'workcity-chat'); ?></option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="primary_color"><?php _e('Primary Color', 'workcity-chat'); ?></label>
                        </th>
                        <td>
                            <input type="color" id="primary_color" name="workcity_chat_appearance_settings[primary_color]" value="<?php echo esc_attr($this->get_setting('appearance_settings', 'primary_color', '#007cba')); ?>">
                            <p class="description"><?php _e('Primary color for the chat interface', 'workcity-chat'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Add dashboard widgets
     */
    public function add_dashboard_widgets() {
        wp_add_dashboard_widget(
            'workcity_chat_dashboard_widget',
            __('Chat System Overview', 'workcity-chat'),
            array($this, 'dashboard_widget_content')
        );
    }
    
    /**
     * Dashboard widget content
     */
    public function dashboard_widget_content() {
        ?>
        <div class="workcity-chat-dashboard-widget">
            <div class="chat-stats">
                <div class="stat-item">
                    <span class="stat-label"><?php _e('Active Chats:', 'workcity-chat'); ?></span>
                    <span class="stat-value" id="widget-active-chats">—</span>
                </div>
                <div class="stat-item">
                    <span class="stat-label"><?php _e('Pending:', 'workcity-chat'); ?></span>
                    <span class="stat-value" id="widget-pending-chats">—</span>
                </div>
                <div class="stat-item">
                    <span class="stat-label"><?php _e('Today:', 'workcity-chat'); ?></span>
                    <span class="stat-value" id="widget-todays-chats">—</span>
                </div>
            </div>
            
            <div class="widget-actions">
                <a href="<?php echo admin_url('admin.php?page=workcity-chat'); ?>" class="button button-primary">
                    <?php _e('Chat Dashboard', 'workcity-chat'); ?>
                </a>
                <a href="<?php echo admin_url('edit.php?post_type=chat_session'); ?>" class="button">
                    <?php _e('All Chats', 'workcity-chat'); ?>
                </a>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Load widget stats
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'workcity_chat_admin_stats',
                    nonce: '<?php echo wp_create_nonce('workcity_chat_admin_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        $('#widget-active-chats').text(response.data.active_chats || 0);
                        $('#widget-pending-chats').text(response.data.pending_chats || 0);
                        $('#widget-todays-chats').text(response.data.todays_chats || 0);
                    }
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * General settings section callback
     */
    public function general_settings_section_callback() {
        echo '<p>' . __('Configure general chat system settings.', 'workcity-chat') . '</p>';
    }
    
    /**
     * Appearance settings section callback
     */
    public function appearance_settings_section_callback() {
        echo '<p>' . __('Customize the appearance of the chat interface.', 'workcity-chat') . '</p>';
    }
    
    /**
     * Get setting value
     */
    private function get_setting($group, $key, $default = '') {
        $settings = get_option('workcity_chat_' . $group, array());
        return isset($settings[$key]) ? $settings[$key] : $default;
    }
    
    /**
     * AJAX: Get admin statistics
     */
    public function get_admin_stats() {
        check_ajax_referer('workcity_chat_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized', 403);
        }
        
        global $wpdb;
        
        // Get statistics
        $stats = array();
        
        // Active chats
        $stats['active_chats'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} p 
             JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
             WHERE p.post_type = 'chat_session' 
             AND pm.meta_key = '_chat_status' 
             AND pm.meta_value = 'active'"
        );
        
        // Pending chats
        $stats['pending_chats'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} p 
             JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
             WHERE p.post_type = 'chat_session' 
             AND pm.meta_key = '_chat_status' 
             AND pm.meta_value = 'pending'"
        );
        
        // Today's chats
        $stats['todays_chats'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = 'chat_session' 
             AND DATE(post_date) = %s",
            current_time('Y-m-d')
        ));
        
        // Online agents (simplified - check last activity)
        $stats['online_agents'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} 
             WHERE meta_key = '_workcity_chat_last_activity' 
             AND meta_value > %s",
            date('Y-m-d H:i:s', current_time('timestamp') - 300) // 5 minutes ago
        ));
        
        // Recent chats
        $recent_chats = $wpdb->get_results(
            "SELECT p.ID, p.post_title, p.post_date, pm.meta_value as status 
             FROM {$wpdb->posts} p 
             JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
             WHERE p.post_type = 'chat_session' 
             AND pm.meta_key = '_chat_status' 
             ORDER BY p.post_date DESC 
             LIMIT 5"
        );
        
        $stats['recent_chats'] = array();
        foreach ($recent_chats as $chat) {
            $stats['recent_chats'][] = array(
                'id' => $chat->ID,
                'title' => $chat->post_title,
                'status' => $chat->status,
                'time_ago' => human_time_diff(strtotime($chat->post_date), current_time('timestamp')) . ' ago',
                'edit_url' => admin_url('post.php?post=' . $chat->ID . '&action=edit'),
            );
        }
        
        // Agent status
        $agents = get_users(array(
            'meta_key' => '_workcity_chat_agent_types',
            'meta_compare' => 'EXISTS'
        ));
        
        $stats['agent_status'] = array();
        foreach ($agents as $agent) {
            $last_activity = get_user_meta($agent->ID, '_workcity_chat_last_activity', true);
            $is_online = false;
            
            if ($last_activity) {
                $last_activity_time = strtotime($last_activity);
                $is_online = (current_time('timestamp') - $last_activity_time) < 300;
            }
            
            $active_chats = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}workcity_chat_participants p
                 JOIN {$wpdb->posts} cs ON p.chat_session_id = cs.ID
                 JOIN {$wpdb->postmeta} pm ON cs.ID = pm.post_id
                 WHERE p.user_id = %d 
                 AND p.is_active = 1
                 AND pm.meta_key = '_chat_status'
                 AND pm.meta_value IN ('active', 'pending')",
                $agent->ID
            ));
            
            $stats['agent_status'][] = array(
                'id' => $agent->ID,
                'name' => $agent->display_name,
                'is_online' => $is_online,
                'active_chats' => intval($active_chats),
            );
        }
        
        wp_send_json_success($stats);
    }
    
    /**
     * AJAX: Assign agent to session
     */
    public function assign_agent_to_session() {
        check_ajax_referer('workcity_chat_admin_nonce', 'nonce');
        
        if (!current_user_can('assign_chat_agents')) {
            wp_die('Unauthorized', 403);
        }
        
        $session_id = intval($_POST['session_id']);
        $agent_id = intval($_POST['agent_id']);
        
        // Add agent as participant
        global $wpdb;
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'workcity_chat_participants',
            array(
                'chat_session_id' => $session_id,
                'user_id' => $agent_id,
                'role' => 'agent',
                'joined_at' => current_time('mysql'),
                'last_seen' => current_time('mysql'),
                'is_active' => 1,
            ),
            array('%d', '%d', '%s', '%s', '%s', '%d')
        );
        
        if ($result) {
            // Trigger agent assignment action
            do_action('workcity_chat_agent_assigned', $session_id, $agent_id);
            
            wp_send_json_success(array(
                'message' => __('Agent assigned successfully', 'workcity-chat')
            ));
        } else {
            wp_send_json_error(__('Failed to assign agent', 'workcity-chat'));
        }
    }
    
    /**
     * AJAX: Close chat session
     */
    public function close_chat_session() {
        check_ajax_referer('workcity_chat_admin_nonce', 'nonce');
        
        if (!current_user_can('moderate_chats')) {
            wp_die('Unauthorized', 403);
        }
        
        $session_id = intval($_POST['session_id']);
        
        // Update session status
        update_post_meta($session_id, '_chat_status', 'closed');
        
        // Trigger session closed action
        do_action('workcity_chat_session_closed', $session_id, get_current_user_id());
        
        wp_send_json_success(array(
            'message' => __('Chat session closed successfully', 'workcity-chat')
        ));
    }
}