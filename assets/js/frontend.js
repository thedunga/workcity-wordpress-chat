/**
 * WorkCity Chat Frontend JavaScript
 * Handles real-time chat functionality, UI interactions, and AJAX communication
 */

(function($) {
    'use strict';

    // Global chat object
    window.WorkCityChat = {
        config: {
            refreshInterval: 3000,
            typingTimeout: 2000,
            maxRetries: 3,
            notificationPermission: false
        },
        
        activeChats: new Map(),
        notifications: [],
        isVisible: true,
        lastActivityTime: Date.now(),
        
        // Initialize chat system
        init: function() {
            this.bindEvents();
            this.checkNotificationPermission();
            this.startActivityTracking();
            this.loadPendingNotifications();
            
            // Initialize any existing chat widgets
            $('.workcity-chat-floating-widget').each(function() {
                WorkCityChat.initFloatingWidget($(this).attr('id'));
            });
            
            $('.workcity-chat-inline').each(function() {
                WorkCityChat.initInlineChat($(this).attr('id'));
            });
        },
        
        // Bind global events
        bindEvents: function() {
            var self = this;
            
            // Handle page visibility changes
            document.addEventListener('visibilitychange', function() {
                self.isVisible = !document.hidden;
                if (self.isVisible) {
                    self.loadPendingNotifications();
                }
            });
            
            // Handle window focus/blur
            $(window).on('focus', function() {
                self.isVisible = true;
                self.loadPendingNotifications();
            }).on('blur', function() {
                self.isVisible = false;
            });
            
            // Handle modal close buttons
            $(document).on('click', '.modal-close, .modal-overlay', function(e) {
                if (e.target === this) {
                    self.closeModal($(this).closest('.workcity-chat-modal'));
                }
            });
            
            // Handle keyboard shortcuts
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    $('.workcity-chat-modal:visible').each(function() {
                        self.closeModal($(this));
                    });
                }
            });
        },
        
        // Initialize floating widget
        initFloatingWidget: function(widgetId) {
            var widget = $('#' + widgetId);
            var self = this;
            
            if (!widget.length) return;
            
            var toggleBtn = widget.find('.chat-toggle-btn');
            var chatWindow = widget.find('.chat-window');
            var closeBtn = widget.find('.close-btn, .minimize-btn');
            
            // Toggle chat window
            toggleBtn.on('click', function() {
                if (chatWindow.is(':visible')) {
                    self.closeWidget(widget);
                } else {
                    self.openWidget(widget);
                }
            });
            
            // Close/minimize buttons
            closeBtn.on('click', function() {
                self.closeWidget(widget);
            });
            
            // Auto-open if configured
            if (widget.data('auto-open') === true || widget.data('auto-open') === 'true') {
                setTimeout(function() {
                    self.openWidget(widget);
                }, 2000);
            }
            
            // Initialize chat content if window is visible
            if (chatWindow.is(':visible')) {
                this.initChatInterface(widget);
            }
        },
        
        // Initialize inline chat
        initInlineChat: function(widgetId) {
            var widget = $('#' + widgetId);
            
            if (!widget.length) return;
            
            this.initChatInterface(widget);
            
            // Auto-load session if specified
            var sessionId = widget.data('session-id');
            if (sessionId) {
                this.loadChatSession(widget, sessionId);
            } else {
                this.showChatStart(widget);
            }
        },
        
        // Initialize chat interface
        initChatInterface: function(widget) {
            var self = this;
            var chatInput = widget.find('.chat-input');
            var chatForm = widget.find('.chat-input-form');
            var attachBtn = widget.find('.attach-btn');
            var fileInput = widget.find('.file-input');
            var refreshBtn = widget.find('.refresh-btn');
            
            // Auto-resize textarea
            chatInput.on('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });
            
            // Handle form submission
            chatForm.on('submit', function(e) {
                e.preventDefault();
                self.sendMessage(widget);
            });
            
            // Handle Enter key (send on Enter, new line on Shift+Enter)
            chatInput.on('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    chatForm.trigger('submit');
                }
            });
            
            // Handle typing indicator
            var typingTimer;
            chatInput.on('input', function() {
                clearTimeout(typingTimer);
                self.sendTypingStatus(widget, 'typing');
                
                typingTimer = setTimeout(function() {
                    self.sendTypingStatus(widget, 'stopped');
                }, 2000);
            });
            
            // Handle file attachment
            attachBtn.on('click', function() {
                fileInput.click();
            });
            
            fileInput.on('change', function() {
                if (this.files.length > 0) {
                    self.uploadFile(widget, this.files[0]);
                }
            });
            
            // Refresh button
            refreshBtn.on('click', function() {
                self.refreshMessages(widget);
            });
            
            // Start message polling if there's an active session
            var sessionId = widget.data('current-session-id');
            if (sessionId) {
                this.startMessagePolling(widget, sessionId);
            }
        },
        
        // Open widget
        openWidget: function(widget) {
            var chatWindow = widget.find('.chat-window');
            var toggleBtn = widget.find('.chat-toggle-btn');
            
            chatWindow.show();
            toggleBtn.addClass('active');
            
            // Initialize chat interface if not already done
            if (!widget.data('chat-initialized')) {
                this.initChatInterface(widget);
                widget.data('chat-initialized', true);
            }
            
            // Load chat content
            this.loadChatContent(widget);
            
            // Focus input
            setTimeout(function() {
                widget.find('.chat-input').focus();
            }, 300);
        },
        
        // Close widget
        closeWidget: function(widget) {
            var chatWindow = widget.find('.chat-window');
            var toggleBtn = widget.find('.chat-toggle-btn');
            
            chatWindow.hide();
            toggleBtn.removeClass('active');
            
            // Stop polling
            var sessionId = widget.data('current-session-id');
            if (sessionId) {
                this.stopMessagePolling(sessionId);
            }
        },
        
        // Load chat content
        loadChatContent: function(widget) {
            var contentArea = widget.find('.chat-content');
            
            if (contentArea.children().length === 0) {
                // Show loading state
                contentArea.html('<div class="loading-state"><p>' + workcityChat.strings.loading + '</p></div>');
                
                // Load chat sessions or show new chat form
                this.loadUserSessions(widget);
            }
        },
        
        // Load user's chat sessions
        loadUserSessions: function(widget) {
            var self = this;
            
            $.ajax({
                url: workcityChat.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'workcity_chat_get_sessions',
                    nonce: workcityChat.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.displaySessions(widget, response.data);
                    } else {
                        self.showError(widget, response.data || 'Failed to load sessions');
                    }
                },
                error: function() {
                    self.showError(widget, 'Network error occurred');
                }
            });
        },
        
        // Display chat sessions
        displaySessions: function(widget, sessions) {
            var contentArea = widget.find('.chat-content');
            var html = '';
            
            if (sessions.length === 0) {
                html = this.getNewChatHTML();
            } else {
                html += '<div class="chat-sessions-list">';
                html += '<div class="sessions-header">';
                html += '<h4>' + workcityChat.strings.yourChats + '</h4>';
                html += '<button class="btn btn-primary new-chat-btn">' + workcityChat.strings.newChat + '</button>';
                html += '</div>';
                html += '<div class="sessions-list">';
                
                sessions.forEach(function(session) {
                    html += '<div class="session-item" data-session-id="' + session.id + '">';
                    html += '<div class="session-header">';
                    html += '<h5>' + session.title + '</h5>';
                    html += '<span class="session-status status-' + session.status + '">' + session.status + '</span>';
                    html += '</div>';
                    
                    if (session.last_message) {
                        html += '<div class="last-message">';
                        html += '<span class="sender">' + session.last_message.sender_name + ':</span> ';
                        html += '<span class="content">' + session.last_message.content + '</span>';
                        html += '</div>';
                        html += '<div class="message-time">' + session.last_message.formatted_time + '</div>';
                    }
                    
                    if (session.unread_count > 0) {
                        html += '<div class="unread-badge">' + session.unread_count + '</div>';
                    }
                    
                    html += '</div>';
                });
                
                html += '</div>';
                html += '</div>';
            }
            
            contentArea.html(html);
            this.bindSessionEvents(widget);
        },
        
        // Bind session events
        bindSessionEvents: function(widget) {
            var self = this;
            
            // Session item clicks
            widget.find('.session-item').on('click', function() {
                var sessionId = $(this).data('session-id');
                self.loadChatSession(widget, sessionId);
            });
            
            // New chat button
            widget.find('.new-chat-btn').on('click', function() {
                self.showNewChatForm(widget);
            });
        },
        
        // Load specific chat session
        loadChatSession: function(widget, sessionId) {
            var self = this;
            var contentArea = widget.find('.chat-content');
            
            // Show loading
            contentArea.html('<div class="loading-state"><p>' + workcityChat.strings.loading + '</p></div>');
            
            // Store current session ID
            widget.data('current-session-id', sessionId);
            
            // Load session details and messages
            $.when(
                this.loadSessionDetails(sessionId),
                this.loadMessages(sessionId)
            ).done(function(sessionResponse, messagesResponse) {
                if (sessionResponse[0].success && messagesResponse[0].success) {
                    self.displayChatInterface(widget, sessionResponse[0].data, messagesResponse[0].data);
                    self.startMessagePolling(widget, sessionId);
                } else {
                    self.showError(widget, 'Failed to load chat session');
                }
            });
        },
        
        // Load session details
        loadSessionDetails: function(sessionId) {
            return $.ajax({
                url: workcityChat.restUrl + 'sessions/' + sessionId,
                type: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', workcityChat.nonce);
                }
            });
        },
        
        // Load messages
        loadMessages: function(sessionId, lastMessageId) {
            return $.ajax({
                url: workcityChat.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'workcity_chat_get_messages',
                    session_id: sessionId,
                    last_message_id: lastMessageId || 0,
                    nonce: workcityChat.nonce
                }
            });
        },
        
        // Display chat interface
        displayChatInterface: function(widget, session, messagesData) {
            var contentArea = widget.find('.chat-content');
            var html = '';
            
            html += '<div class="chat-session-header">';
            html += '<button class="back-btn">← ' + workcityChat.strings.back + '</button>';
            html += '<div class="session-info">';
            html += '<h4>' + session.title + '</h4>';
            html += '<span class="session-type">' + session.chat_type + '</span>';
            html += '</div>';
            html += '</div>';
            
            html += '<div class="chat-messages-container">';
            html += '<div class="chat-messages" id="messages-' + session.id + '">';
            // Messages will be populated by displayMessages
            html += '</div>';
            html += '</div>';
            
            html += '<div class="typing-indicator" style="display: none;">';
            html += '<span class="typing-dots"><span></span><span></span><span></span></span>';
            html += '<span class="typing-text">' + workcityChat.strings.typing + '</span>';
            html += '</div>';
            
            html += '<div class="chat-input-area">';
            html += '<form class="chat-input-form">';
            html += '<div class="input-group">';
            html += '<button type="button" class="attach-btn">';
            html += '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">';
            html += '<path d="M16.5 6v11.5c0 2.21-1.79 4-4 4s-4-1.79-4-4V5c0-1.38 1.12-2.5 2.5-2.5s2.5 1.12 2.5 2.5v10.5c0 .55-.45 1-1 1s-1-.45-1-1V6H10v9.5c0 1.38 1.12 2.5 2.5 2.5s2.5-1.12 2.5-2.5V5c0-2.21-1.79-4-4-4S7 2.79 7 5v12.5c0 3.04 2.46 5.5 5.5 5.5s5.5-2.46 5.5-5.5V6h-1.5z"/>';
            html += '</svg>';
            html += '</button>';
            html += '<textarea class="chat-input" placeholder="' + workcityChat.strings.sendMessage + '" rows="1"></textarea>';
            html += '<button type="submit" class="send-btn">';
            html += '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">';
            html += '<path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>';
            html += '</svg>';
            html += '</button>';
            html += '</div>';
            html += '</form>';
            html += '<input type="file" class="file-input" style="display: none;" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.txt,.zip">';
            html += '</div>';
            
            contentArea.html(html);
            
            // Display messages
            this.displayMessages(widget, messagesData.messages || []);
            
            // Store last message ID
            widget.data('last-message-id', messagesData.last_message_id || 0);
            
            // Bind events
            this.bindChatEvents(widget);
            
            // Mark messages as read
            this.markMessagesAsRead(session.id);
        },
        
        // Display messages
        displayMessages: function(widget, messages) {
            var sessionId = widget.data('current-session-id');
            var messagesContainer = widget.find('#messages-' + sessionId);
            var html = '';
            
            messages.forEach(function(message) {
                html += WorkCityChat.getMessageHTML(message);
            });
            
            messagesContainer.append(html);
            this.scrollToBottom(messagesContainer);
        },
        
        // Get message HTML
        getMessageHTML: function(message) {
            var isOwn = message.sender.is_current_user;
            var html = '';
            
            html += '<div class="message' + (isOwn ? ' own-message' : '') + '" data-message-id="' + message.id + '">';
            
            if (!isOwn) {
                html += '<div class="message-avatar">';
                html += '<img src="' + message.sender.avatar + '" alt="' + message.sender.name + '">';
                html += '</div>';
            }
            
            html += '<div class="message-content">';
            html += '<div class="message-bubble">';
            
            if (message.type === 'image') {
                html += '<div class="message-attachment">';
                html += '<img src="' + message.attachment_url + '" alt="Image" class="attachment-image">';
                html += '</div>';
            } else if (message.type === 'file') {
                html += '<div class="message-attachment">';
                html += '<div class="attachment-file">';
                html += '<svg class="attachment-icon" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">';
                html += '<path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>';
                html += '</svg>';
                html += '<div class="attachment-info">';
                html += '<div class="attachment-name">' + message.attachment_url.split('/').pop() + '</div>';
                html += '</div>';
                html += '</div>';
                html += '</div>';
            }
            
            if (message.content) {
                html += '<div class="message-text">' + message.content + '</div>';
            }
            
            html += '</div>';
            
            html += '<div class="message-meta">';
            html += '<span class="message-time">' + message.formatted_time + '</span>';
            if (isOwn) {
                html += '<span class="message-status">';
                if (message.is_read) {
                    html += '<svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M18 6l-8.5 8.5L6 11l1.4-1.4L10.5 12.6L16.6 6.6L18 6z"/></svg>';
                } else {
                    html += '<svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.2l-3.5-3.5L4 14.1l5 5L20.5 7.6L19 6.1L9 16.2z"/></svg>';
                }
                html += '</span>';
            }
            html += '</div>';
            
            html += '</div>';
            html += '</div>';
            
            return html;
        },
        
        // Bind chat events
        bindChatEvents: function(widget) {
            var self = this;
            
            // Back button
            widget.find('.back-btn').on('click', function() {
                self.loadUserSessions(widget);
                var sessionId = widget.data('current-session-id');
                if (sessionId) {
                    self.stopMessagePolling(sessionId);
                    widget.removeData('current-session-id');
                }
            });
            
            // Initialize chat interface bindings
            this.initChatInterface(widget);
        },
        
        // Send message
        sendMessage: function(widget) {
            var chatInput = widget.find('.chat-input');
            var content = chatInput.val().trim();
            var sessionId = widget.data('current-session-id');
            
            if (!content || !sessionId) return;
            
            var self = this;
            var sendBtn = widget.find('.send-btn');
            
            // Disable send button
            sendBtn.prop('disabled', true);
            
            $.ajax({
                url: workcityChat.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'workcity_chat_send_message',
                    session_id: sessionId,
                    message_content: content,
                    message_type: 'text',
                    nonce: workcityChat.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Clear input
                        chatInput.val('').trigger('input');
                        
                        // Add message to UI immediately
                        self.displayMessages(widget, [response.data.message]);
                        
                        // Update last message ID
                        widget.data('last-message-id', response.data.message.id);
                    } else {
                        self.showNotification('Error: ' + (response.data || 'Failed to send message'), 'error');
                    }
                },
                error: function() {
                    self.showNotification('Network error occurred', 'error');
                },
                complete: function() {
                    sendBtn.prop('disabled', false);
                    chatInput.focus();
                }
            });
        },
        
        // Upload file
        uploadFile: function(widget, file) {
            var self = this;
            var maxSize = 5 * 1024 * 1024; // 5MB
            
            if (file.size > maxSize) {
                this.showNotification('File size too large. Maximum 5MB allowed.', 'error');
                return;
            }
            
            var formData = new FormData();
            formData.append('action', 'workcity_chat_upload_file');
            formData.append('file', file);
            formData.append('nonce', workcityChat.nonce);
            
            // Show upload progress
            this.showNotification('Uploading file...', 'info');
            
            $.ajax({
                url: workcityChat.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        // Send file message
                        self.sendFileMessage(widget, response.data.url, file.type.startsWith('image/') ? 'image' : 'file');
                    } else {
                        self.showNotification('Upload failed: ' + (response.data || 'Unknown error'), 'error');
                    }
                },
                error: function() {
                    self.showNotification('Upload failed: Network error', 'error');
                }
            });
        },
        
        // Send file message
        sendFileMessage: function(widget, fileUrl, messageType) {
            var sessionId = widget.data('current-session-id');
            var self = this;
            
            $.ajax({
                url: workcityChat.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'workcity_chat_send_message',
                    session_id: sessionId,
                    message_content: '',
                    message_type: messageType,
                    attachment_url: fileUrl,
                    nonce: workcityChat.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.displayMessages(widget, [response.data.message]);
                        widget.data('last-message-id', response.data.message.id);
                        self.showNotification('File sent successfully', 'success');
                    } else {
                        self.showNotification('Failed to send file: ' + (response.data || 'Unknown error'), 'error');
                    }
                },
                error: function() {
                    self.showNotification('Network error occurred', 'error');
                }
            });
        },
        
        // Start message polling
        startMessagePolling: function(widget, sessionId) {
            var self = this;
            
            // Stop any existing polling for this session
            this.stopMessagePolling(sessionId);
            
            var pollMessages = function() {
                var lastMessageId = widget.data('last-message-id') || 0;
                
                $.ajax({
                    url: workcityChat.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'workcity_chat_get_messages',
                        session_id: sessionId,
                        last_message_id: lastMessageId,
                        nonce: workcityChat.nonce
                    },
                    success: function(response) {
                        if (response.success && response.data.messages.length > 0) {
                            self.displayMessages(widget, response.data.messages);
                            widget.data('last-message-id', response.data.last_message_id);
                            
                            // Mark new messages as read if widget is visible
                            if (self.isVisible) {
                                self.markMessagesAsRead(sessionId);
                            }
                            
                            // Show notification if page is not visible
                            if (!self.isVisible && response.data.messages.length > 0) {
                                var lastMessage = response.data.messages[response.data.messages.length - 1];
                                if (!lastMessage.sender.is_current_user) {
                                    self.showBrowserNotification('New message from ' + lastMessage.sender.name, lastMessage.content);
                                }
                            }
                        }
                    }
                });
            };
            
            // Start polling
            var intervalId = setInterval(pollMessages, this.config.refreshInterval);
            this.activeChats.set(sessionId, intervalId);
        },
        
        // Stop message polling
        stopMessagePolling: function(sessionId) {
            if (this.activeChats.has(sessionId)) {
                clearInterval(this.activeChats.get(sessionId));
                this.activeChats.delete(sessionId);
            }
        },
        
        // Mark messages as read
        markMessagesAsRead: function(sessionId) {
            $.ajax({
                url: workcityChat.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'workcity_chat_mark_read',
                    session_id: sessionId,
                    nonce: workcityChat.nonce
                }
            });
        },
        
        // Send typing status
        sendTypingStatus: function(widget, status) {
            var sessionId = widget.data('current-session-id');
            
            if (!sessionId) return;
            
            $.ajax({
                url: workcityChat.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'workcity_chat_update_status',
                    session_id: sessionId,
                    status: status,
                    nonce: workcityChat.nonce
                }
            });
        },
        
        // Show new chat form
        showNewChatForm: function(widget) {
            var modal = $('#workcity-new-chat-modal');
            if (modal.length === 0) return;
            
            this.openModal(modal);
            
            var self = this;
            var form = modal.find('#new-chat-form');
            
            form.off('submit').on('submit', function(e) {
                e.preventDefault();
                
                var formData = {
                    title: form.find('[name="title"]').val(),
                    chat_type: form.find('[name="chat_type"]').val(),
                    product_id: form.find('[name="product_id"]').val()
                };
                
                self.createNewChat(widget, formData);
            });
        },
        
        // Create new chat
        createNewChat: function(widget, formData) {
            var self = this;
            
            $.ajax({
                url: workcityChat.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'workcity_chat_create_session',
                    title: formData.title,
                    chat_type: formData.chat_type,
                    product_id: formData.product_id,
                    nonce: workcityChat.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.closeModal($('#workcity-new-chat-modal'));
                        self.loadChatSession(widget, response.data.session_id);
                        self.showNotification('Chat session created successfully', 'success');
                    } else {
                        self.showNotification('Failed to create chat: ' + (response.data || 'Unknown error'), 'error');
                    }
                },
                error: function() {
                    self.showNotification('Network error occurred', 'error');
                }
            });
        },
        
        // Open modal
        openModal: function(modal) {
            modal.show();
            $('body').addClass('modal-open');
        },
        
        // Close modal
        closeModal: function(modal) {
            modal.hide();
            $('body').removeClass('modal-open');
        },
        
        // Show notification
        showNotification: function(message, type) {
            type = type || 'info';
            
            var notification = $('<div class="workcity-notification notification-' + type + '">' + message + '</div>');
            
            if ($('.workcity-notifications').length === 0) {
                $('body').append('<div class="workcity-notifications"></div>');
            }
            
            $('.workcity-notifications').append(notification);
            
            setTimeout(function() {
                notification.addClass('show');
            }, 100);
            
            setTimeout(function() {
                notification.removeClass('show');
                setTimeout(function() {
                    notification.remove();
                }, 300);
            }, 3000);
        },
        
        // Check notification permission
        checkNotificationPermission: function() {
            if ('Notification' in window) {
                if (Notification.permission === 'granted') {
                    this.config.notificationPermission = true;
                } else if (Notification.permission !== 'denied') {
                    Notification.requestPermission().then(function(permission) {
                        WorkCityChat.config.notificationPermission = (permission === 'granted');
                    });
                }
            }
        },
        
        // Show browser notification
        showBrowserNotification: function(title, body) {
            if (!this.config.notificationPermission || this.isVisible) return;
            
            var notification = new Notification(title, {
                body: body,
                icon: workcityChat.iconUrl || '/wp-includes/images/w-logo-blue.png'
            });
            
            setTimeout(function() {
                notification.close();
            }, 5000);
        },
        
        // Start activity tracking
        startActivityTracking: function() {
            var self = this;
            
            // Track user activity
            $(document).on('mousemove keypress scroll touchstart', function() {
                self.lastActivityTime = Date.now();
            });
            
            // Update activity status periodically
            setInterval(function() {
                var now = Date.now();
                var timeSinceActivity = now - self.lastActivityTime;
                
                if (timeSinceActivity < 30000) { // Active within 30 seconds
                    // Update activity timestamp
                    $.ajax({
                        url: workcityChat.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'workcity_chat_update_activity',
                            nonce: workcityChat.nonce
                        }
                    });
                }
            }, 60000); // Check every minute
        },
        
        // Load pending notifications
        loadPendingNotifications: function() {
            var self = this;
            
            $.ajax({
                url: workcityChat.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'workcity_chat_get_notifications',
                    nonce: workcityChat.nonce
                },
                success: function(response) {
                    if (response.success && response.data.length > 0) {
                        response.data.forEach(function(notification) {
                            self.showBrowserNotification(notification.title, notification.body);
                        });
                    }
                }
            });
        },
        
        // Utility functions
        scrollToBottom: function(container) {
            container.scrollTop(container[0].scrollHeight);
        },
        
        showError: function(widget, message) {
            var contentArea = widget.find('.chat-content');
            contentArea.html('<div class="error-state"><p>' + message + '</p></div>');
        },
        
        getNewChatHTML: function() {
            return '<div class="new-chat-prompt">' +
                   '<h4>' + workcityChat.strings.startNewChat + '</h4>' +
                   '<p>' + workcityChat.strings.startNewChatDesc + '</p>' +
                   '<button class="btn btn-primary new-chat-btn">' + workcityChat.strings.startChat + '</button>' +
                   '</div>';
        },
        
        // Public methods for external use
        openNewChat: function(chatType, productId) {
            // Open new chat modal with pre-filled data
            var modal = $('#workcity-new-chat-modal');
            if (modal.length > 0) {
                if (chatType) {
                    modal.find('[name="chat_type"]').val(chatType);
                }
                if (productId) {
                    modal.find('[name="product_id"]').val(productId);
                }
                this.openModal(modal);
            }
        },
        
        refreshMessages: function(widget) {
            var sessionId = widget.data('current-session-id');
            if (sessionId) {
                // Clear messages and reload
                widget.find('.chat-messages').empty();
                widget.data('last-message-id', 0);
                this.startMessagePolling(widget, sessionId);
            }
        }
    };
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        WorkCityChat.init();
    });
    
    // Add CSS for notifications
    if ($('.workcity-notification-styles').length === 0) {
        $('head').append(
            '<style class="workcity-notification-styles">' +
            '.workcity-notifications { position: fixed; top: 20px; right: 20px; z-index: 10001; }' +
            '.workcity-notification { padding: 12px 16px; margin-bottom: 10px; border-radius: 4px; color: white; opacity: 0; transform: translateX(100%); transition: all 0.3s ease; max-width: 300px; }' +
            '.workcity-notification.show { opacity: 1; transform: translateX(0); }' +
            '.notification-success { background: #00a32a; }' +
            '.notification-error { background: #d63638; }' +
            '.notification-warning { background: #ffb900; }' +
            '.notification-info { background: #007cba; }' +
            'body.modal-open { overflow: hidden; }' +
            '</style>'
        );
    }

})(jQuery);