/**
 * WorkCity Chat Admin JavaScript
 * Handles admin interface interactions and real-time updates
 */

(function($) {
    'use strict';

    // Admin object
    window.WorkCityAdmin = {
        config: {
            refreshInterval: 30000, // 30 seconds
            maxRetries: 3
        },
        
        intervals: {},
        
        // Initialize admin interface
        init: function() {
            this.bindEvents();
            this.initDashboard();
            this.initLiveChat();
            this.initReports();
            this.initSettings();
        },
        
        // Bind global events
        bindEvents: function() {
            var self = this;
            
            // Handle admin notices dismiss
            $(document).on('click', '.notice-dismiss', function() {
                var notice = $(this).closest('.notice');
                if (notice.hasClass('workcity-chat-notice')) {
                    // Handle custom notice dismissal if needed
                }
            });
            
            // Handle bulk actions
            $(document).on('change', '#bulk-action-selector-top, #bulk-action-selector-bottom', function() {
                var action = $(this).val();
                if (action.startsWith('workcity-')) {
                    // Handle custom bulk actions
                    self.handleBulkAction(action);
                }
            });
            
            // Handle quick edit
            $(document).on('click', '.editinline', function() {
                var postId = $(this).closest('tr').attr('id').replace('post-', '');
                if ($('#post-' + postId).hasClass('type-chat_session')) {
                    self.handleQuickEdit(postId);
                }
            });
        },
        
        // Initialize dashboard
        initDashboard: function() {
            if ($('.workcity-chat-dashboard').length === 0) return;
            
            this.loadDashboardStats();
            this.startDashboardRefresh();
        },
        
        // Load dashboard statistics
        loadDashboardStats: function() {
            var self = this;
            
            $.ajax({
                url: workcityChatAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'workcity_chat_admin_stats',
                    nonce: workcityChatAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.updateDashboardStats(response.data);
                    }
                },
                error: function() {
                    console.error('Failed to load dashboard stats');
                }
            });
        },
        
        // Update dashboard statistics
        updateDashboardStats: function(data) {
            // Update stat boxes
            $('#active-chats-count .loading').text(data.active_chats || 0);
            $('#pending-chats-count .loading').text(data.pending_chats || 0);
            $('#online-agents-count .loading').text(data.online_agents || 0);
            $('#todays-chats-count .loading').text(data.todays_chats || 0);
            
            // Update recent chats
            this.updateRecentChats(data.recent_chats || []);
            
            // Update agent status
            this.updateAgentStatus(data.agent_status || []);
        },
        
        // Update recent chats list
        updateRecentChats: function(chats) {
            var container = $('#recent-chats-list');
            var html = '';
            
            if (chats.length === 0) {
                html = '<p>' + workcityChatAdmin.strings.noRecentChats + '</p>';
            } else {
                html = '<ul>';
                chats.forEach(function(chat) {
                    html += '<li>';
                    html += '<a href="' + chat.edit_url + '">' + chat.title + '</a>';
                    html += '<span class="chat-meta">' + chat.status + ' - ' + chat.time_ago + '</span>';
                    html += '</li>';
                });
                html += '</ul>';
            }
            
            container.html(html);
        },
        
        // Update agent status list
        updateAgentStatus: function(agents) {
            var container = $('#agent-status-list');
            var html = '';
            
            if (agents.length === 0) {
                html = '<p>' + workcityChatAdmin.strings.noAgents + '</p>';
            } else {
                html = '<ul>';
                agents.forEach(function(agent) {
                    var statusClass = agent.is_online ? 'online' : 'offline';
                    html += '<li class="agent-status-item ' + statusClass + '">';
                    html += '<div>';
                    html += '<span class="agent-name">' + agent.name + '</span>';
                    html += '<span class="agent-chats">(' + agent.active_chats + ' chats)</span>';
                    html += '</div>';
                    html += '<span class="status-indicator"></span>';
                    html += '</li>';
                });
                html += '</ul>';
            }
            
            container.html(html);
        },
        
        // Start dashboard refresh
        startDashboardRefresh: function() {
            var self = this;
            
            this.intervals.dashboard = setInterval(function() {
                self.loadDashboardStats();
            }, this.config.refreshInterval);
        },
        
        // Initialize live chat interface
        initLiveChat: function() {
            if ($('.workcity-live-chat-interface').length === 0) return;
            
            this.loadChatSessions();
            this.bindLiveChatEvents();
            this.startSessionRefresh();
        },
        
        // Bind live chat events
        bindLiveChatEvents: function() {
            var self = this;
            
            // Session filter change
            $('#session-filter').on('change', function() {
                self.loadChatSessions();
            });
            
            // Session item click
            $(document).on('click', '.session-item', function() {
                var sessionId = $(this).data('session-id');
                self.loadChatSession(sessionId);
            });
            
            // Assign agent button
            $(document).on('click', '.assign-agent-btn', function() {
                var sessionId = $(this).data('session-id');
                self.showAssignAgentModal(sessionId);
            });
            
            // Close session button
            $(document).on('click', '.close-session-btn', function() {
                var sessionId = $(this).data('session-id');
                self.closeChatSession(sessionId);
            });
        },
        
        // Load chat sessions
        loadChatSessions: function() {
            var self = this;
            var filter = $('#session-filter').val() || 'all';
            
            $.ajax({
                url: workcityChatAdmin.restUrl + 'admin/sessions',
                type: 'GET',
                data: { filter: filter },
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', workcityChatAdmin.nonce);
                },
                success: function(sessions) {
                    self.renderSessionsList(sessions);
                },
                error: function() {
                    console.error('Failed to load chat sessions');
                }
            });
        },
        
        // Render sessions list
        renderSessionsList: function(sessions) {
            var container = $('#chat-sessions-list');
            var html = '';
            
            if (sessions.length === 0) {
                html = '<div class="no-sessions">' + workcityChatAdmin.strings.noSessions + '</div>';
            } else {
                sessions.forEach(function(session) {
                    var unreadClass = session.unread_count > 0 ? 'has-unread' : '';
                    
                    html += '<div class="session-item ' + unreadClass + '" data-session-id="' + session.id + '">';
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
            
            container.html(html);
        },
        
        // Load specific chat session
        loadChatSession: function(sessionId) {
            var self = this;
            
            // Update active session
            $('.session-item').removeClass('active');
            $('.session-item[data-session-id="' + sessionId + '"]').addClass('active');
            
            // Show loading
            $('#chat-session-container').html('<div class="loading">' + workcityChatAdmin.strings.loading + '</div>').show();
            $('.no-session-selected').hide();
            
            // Load chat interface
            this.loadChatInterface(sessionId);
        },
        
        // Load chat interface
        loadChatInterface: function(sessionId) {
            var chatUrl = workcityChatAdmin.chatInterfaceUrl || (workcityChatAdmin.adminUrl + 'admin.php?page=workcity-chat-interface&session_id=' + sessionId);
            
            var iframe = '<iframe src="' + chatUrl + '" width="100%" height="600" frameborder="0" class="chat-interface-frame"></iframe>';
            
            $('#chat-session-container').html(iframe);
        },
        
        // Start session refresh
        startSessionRefresh: function() {
            var self = this;
            
            this.intervals.sessions = setInterval(function() {
                self.loadChatSessions();
            }, this.config.refreshInterval);
        },
        
        // Show assign agent modal
        showAssignAgentModal: function(sessionId) {
            var self = this;
            
            // Load available agents
            $.ajax({
                url: workcityChatAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'workcity_chat_get_available_agents',
                    session_id: sessionId,
                    nonce: workcityChatAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.displayAssignAgentModal(sessionId, response.data);
                    }
                }
            });
        },
        
        // Display assign agent modal
        displayAssignAgentModal: function(sessionId, agents) {
            var html = '';
            html += '<div class="assign-agent-modal">';
            html += '<h3>' + workcityChatAdmin.strings.assignAgent + '</h3>';
            html += '<div class="agent-list">';
            
            if (agents.length === 0) {
                html += '<p>' + workcityChatAdmin.strings.noAvailableAgents + '</p>';
            } else {
                agents.forEach(function(agent) {
                    html += '<div class="agent-option" data-agent-id="' + agent.id + '">';
                    html += '<div class="agent-info">';
                    html += '<strong>' + agent.name + '</strong>';
                    html += '<span class="agent-load">(' + agent.current_chats + '/' + agent.max_chats + ' chats)</span>';
                    html += '</div>';
                    html += '<button class="button button-primary assign-btn">Assign</button>';
                    html += '</div>';
                });
            }
            
            html += '</div>';
            html += '<div class="modal-actions">';
            html += '<button class="button cancel-btn">Cancel</button>';
            html += '</div>';
            html += '</div>';
            
            // Show modal (implement modal system)
            this.showModal(html);
            
            // Bind assign events
            this.bindAssignAgentEvents(sessionId);
        },
        
        // Bind assign agent events
        bindAssignAgentEvents: function(sessionId) {
            var self = this;
            
            $('.assign-btn').on('click', function() {
                var agentId = $(this).closest('.agent-option').data('agent-id');
                self.assignAgent(sessionId, agentId);
            });
            
            $('.cancel-btn').on('click', function() {
                self.closeModal();
            });
        },
        
        // Assign agent to session
        assignAgent: function(sessionId, agentId) {
            var self = this;
            
            $.ajax({
                url: workcityChatAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'workcity_chat_admin_assign_agent',
                    session_id: sessionId,
                    agent_id: agentId,
                    nonce: workcityChatAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotification(response.data.message, 'success');
                        self.closeModal();
                        self.loadChatSessions();
                    } else {
                        self.showNotification(response.data || 'Failed to assign agent', 'error');
                    }
                },
                error: function() {
                    self.showNotification('Network error occurred', 'error');
                }
            });
        },
        
        // Close chat session
        closeChatSession: function(sessionId) {
            var self = this;
            
            if (!confirm(workcityChatAdmin.strings.confirmClose)) {
                return;
            }
            
            $.ajax({
                url: workcityChatAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'workcity_chat_admin_close_session',
                    session_id: sessionId,
                    nonce: workcityChatAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotification(response.data.message, 'success');
                        self.loadChatSessions();
                    } else {
                        self.showNotification(response.data || 'Failed to close session', 'error');
                    }
                },
                error: function() {
                    self.showNotification('Network error occurred', 'error');
                }
            });
        },
        
        // Initialize reports
        initReports: function() {
            if ($('.workcity-chat-reports').length === 0) return;
            
            this.bindReportEvents();
            this.loadReportData();
        },
        
        // Bind report events
        bindReportEvents: function() {
            var self = this;
            
            // Date range change
            $('#date-range').on('change', function() {
                if ($(this).val() === 'custom') {
                    $('#custom-date-range').show();
                } else {
                    $('#custom-date-range').hide();
                    self.loadReportData();
                }
            });
            
            // Custom date change
            $('#start-date, #end-date').on('change', function() {
                if ($('#date-range').val() === 'custom') {
                    self.loadReportData();
                }
            });
        },
        
        // Load report data
        loadReportData: function() {
            var self = this;
            var dateRange = $('#date-range').val();
            var startDate = $('#start-date').val();
            var endDate = $('#end-date').val();
            
            $.ajax({
                url: workcityChatAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'workcity_chat_get_reports',
                    date_range: dateRange,
                    start_date: startDate,
                    end_date: endDate,
                    nonce: workcityChatAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.renderReports(response.data);
                    }
                },
                error: function() {
                    console.error('Failed to load report data');
                }
            });
        },
        
        // Render reports
        renderReports: function(data) {
            // Render charts using Chart.js if available
            if (typeof Chart !== 'undefined') {
                this.renderChatVolumeChart(data.chat_volume);
                this.renderResponseTimeChart(data.response_times);
                this.renderChatTypesChart(data.chat_types);
            }
            
            // Render agent performance table
            this.renderAgentPerformance(data.agent_performance);
        },
        
        // Render chat volume chart
        renderChatVolumeChart: function(data) {
            var ctx = document.getElementById('chat-volume-chart');
            if (!ctx) return;
            
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Chat Sessions',
                        data: data.values,
                        borderColor: '#2271b1',
                        backgroundColor: 'rgba(34, 113, 177, 0.1)',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        },
        
        // Render response time chart
        renderResponseTimeChart: function(data) {
            var ctx = document.getElementById('response-time-chart');
            if (!ctx) return;
            
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Average Response Time (minutes)',
                        data: data.values,
                        backgroundColor: '#00a32a'
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        },
        
        // Render chat types chart
        renderChatTypesChart: function(data) {
            var ctx = document.getElementById('chat-types-chart');
            if (!ctx) return;
            
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: data.labels,
                    datasets: [{
                        data: data.values,
                        backgroundColor: [
                            '#2271b1',
                            '#00a32a',
                            '#ffb900',
                            '#d63638',
                            '#8c8f94'
                        ]
                    }]
                },
                options: {
                    responsive: true
                }
            });
        },
        
        // Render agent performance table
        renderAgentPerformance: function(data) {
            var container = $('#agent-performance-table');
            var html = '';
            
            if (data.length === 0) {
                html = '<p>No agent performance data available.</p>';
            } else {
                html += '<table class="wp-list-table widefat fixed striped">';
                html += '<thead>';
                html += '<tr>';
                html += '<th>Agent</th>';
                html += '<th>Chats Handled</th>';
                html += '<th>Avg Response Time</th>';
                html += '<th>Customer Rating</th>';
                html += '</tr>';
                html += '</thead>';
                html += '<tbody>';
                
                data.forEach(function(agent) {
                    html += '<tr>';
                    html += '<td>' + agent.name + '</td>';
                    html += '<td>' + agent.chats_handled + '</td>';
                    html += '<td>' + agent.avg_response_time + ' min</td>';
                    html += '<td>' + (agent.rating ? agent.rating + '/5' : 'N/A') + '</td>';
                    html += '</tr>';
                });
                
                html += '</tbody>';
                html += '</table>';
            }
            
            container.html(html);
        },
        
        // Initialize settings
        initSettings: function() {
            if ($('.workcity-chat-settings').length === 0) return;
            
            this.bindSettingsEvents();
        },
        
        // Bind settings events
        bindSettingsEvents: function() {
            // Handle color picker
            if ($.fn.wpColorPicker) {
                $('#primary_color').wpColorPicker();
            }
            
            // Handle settings validation
            $('form').on('submit', function(e) {
                // Add any validation logic here
            });
        },
        
        // Handle bulk actions
        handleBulkAction: function(action) {
            var selectedPosts = [];
            $('input[name="post[]"]:checked').each(function() {
                selectedPosts.push($(this).val());
            });
            
            if (selectedPosts.length === 0) {
                alert('Please select items to perform bulk action.');
                return;
            }
            
            switch (action) {
                case 'workcity-close-sessions':
                    this.bulkCloseSessions(selectedPosts);
                    break;
                case 'workcity-assign-agent':
                    this.bulkAssignAgent(selectedPosts);
                    break;
            }
        },
        
        // Bulk close sessions
        bulkCloseSessions: function(sessionIds) {
            var self = this;
            
            if (!confirm('Are you sure you want to close selected chat sessions?')) {
                return;
            }
            
            $.ajax({
                url: workcityChatAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'workcity_chat_bulk_close',
                    session_ids: sessionIds,
                    nonce: workcityChatAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Failed to close sessions: ' + response.data);
                    }
                }
            });
        },
        
        // Handle quick edit
        handleQuickEdit: function(postId) {
            // Add any quick edit customizations for chat sessions
            var row = $('#post-' + postId);
            var title = $('.row-title', row).text();
            var status = $('.status-' + postId).text();
            
            // Populate quick edit fields
            setTimeout(function() {
                $('.inline-edit-row [name="post_title"]').val(title);
                // Add custom fields if needed
            }, 100);
        },
        
        // Utility functions
        showModal: function(content) {
            if ($('#workcity-admin-modal').length === 0) {
                $('body').append('<div id="workcity-admin-modal" class="workcity-modal"></div>');
            }
            
            var modal = $('#workcity-admin-modal');
            modal.html('<div class="modal-content">' + content + '</div>').show();
            
            // Close on overlay click
            modal.on('click', function(e) {
                if (e.target === this) {
                    this.closeModal();
                }
            }.bind(this));
        },
        
        closeModal: function() {
            $('#workcity-admin-modal').hide();
        },
        
        showNotification: function(message, type) {
            type = type || 'info';
            
            var noticeClass = 'notice notice-' + type;
            if (type === 'error') noticeClass = 'notice notice-error';
            if (type === 'success') noticeClass = 'notice notice-success';
            
            var notice = $('<div class="' + noticeClass + ' is-dismissible workcity-chat-notice"><p>' + message + '</p></div>');
            
            $('.wrap h1').after(notice);
            
            // Auto dismiss after 5 seconds
            setTimeout(function() {
                notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        },
        
        // Cleanup
        destroy: function() {
            // Clear intervals
            for (var key in this.intervals) {
                clearInterval(this.intervals[key]);
            }
            
            // Remove event listeners
            $(document).off('.workcity-admin');
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        WorkCityAdmin.init();
    });
    
    // Add modal styles
    if ($('#workcity-admin-modal-styles').length === 0) {
        $('head').append(
            '<style id="workcity-admin-modal-styles">' +
            '.workcity-modal { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 160000; display: none; }' +
            '.workcity-modal .modal-content { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border-radius: 4px; max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto; }' +
            '.assign-agent-modal h3 { margin-top: 0; }' +
            '.agent-option { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #f0f0f1; }' +
            '.agent-option:last-child { border-bottom: none; }' +
            '.agent-info { flex: 1; }' +
            '.agent-load { font-size: 12px; color: #646970; margin-left: 8px; }' +
            '.modal-actions { margin-top: 20px; text-align: right; }' +
            '</style>'
        );
    }

})(jQuery);