# WorkCity Chat System

A robust, scalable, and user-friendly chat system plugin for WordPress with seamless WooCommerce integration. This plugin provides real-time messaging capabilities for eCommerce platforms, supporting multiple user roles and contexts.

![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-blue.svg)
![License](https://img.shields.io/badge/License-GPL%20v2-blue.svg)

## 🚀 Features

### Core Features
- **Custom Post Type**: Chat Session management with metadata
- **REST API**: Full API endpoints for chat integration
- **AJAX Real-time Messaging**: Live chat without page refresh
- **Role-based Access Control**: Support for customers, agents, merchants, designers
- **WooCommerce Integration**: Product-context conversations
- **Shortcode Support**: Easy embedding anywhere on your site
- **Read/Unread Status**: Message tracking with timestamps

### Bonus Features
- **Typing Indicators**: Real-time typing status
- **Online Status**: See who's available
- **File Upload Support**: Images, documents, and attachments
- **Email Notifications**: Automated notifications for new messages
- **Browser Push Notifications**: Desktop notifications
- **Dark/Light Mode**: Theme switching capability
- **Responsive Design**: Modern, mobile-friendly interface

### WooCommerce Integration
- Product page chat buttons
- Order-specific support chats
- Customer account integration
- Admin order management
- Email integration
- Product context in conversations

## 📋 Requirements

- **WordPress**: 5.0 or higher
- **PHP**: 7.4 or higher
- **MySQL**: 5.6 or higher
- **WooCommerce**: 3.0 or higher (optional, for eCommerce features)

## 🔧 Installation

1. **Download the Plugin**
   ```bash
   git clone https://github.com/workcityafrica/workcity-wordpress-chat.git
   cd workcity-wordpress-chat
   ```

2. **Upload to WordPress**
   - Upload the plugin folder to `/wp-content/plugins/`
   - Or install via WordPress admin: Plugins → Add New → Upload Plugin

3. **Activate the Plugin**
   - Go to Plugins → Installed Plugins
   - Find "WorkCity Chat System" and click "Activate"

4. **Initial Setup**
   - Navigate to Chat System → Settings
   - Configure your preferences
   - Set up user roles and permissions

## ⚙️ Configuration

### Basic Setup

1. **Enable Chat System**
   ```php
   // In wp-config.php or functions.php
   define('WORKCITY_CHAT_ENABLED', true);
   ```

2. **Configure Settings**
   - Go to Chat System → Settings
   - Set up notification preferences
   - Configure file upload limits
   - Customize appearance

### User Roles Setup

1. **Assign Chat Roles**
   - Go to Users → All Users
   - Edit user profiles to assign chat specializations
   - Set availability and working hours

2. **Agent Configuration**
   - Set maximum concurrent chats
   - Define agent specializations (general, product, order, design, merchant)
   - Configure auto-assignment rules

### WooCommerce Integration

1. **Enable Product Chats**
   ```php
   add_action('woocommerce_single_product_summary', 'workcity_add_product_chat', 35);
   ```

2. **Customize Product Page**
   ```php
   // Add chat button to specific products
   if (is_product() && get_the_ID() == 123) {
       echo do_shortcode('[workcity_chat_button chat_type="product" product_id="123"]');
   }
   ```

## 🎯 Usage

### Shortcodes

#### Basic Chat Widget
```php
[workcity_chat]
```

#### Floating Chat Widget
```php
[workcity_chat type="floating" position="bottom-right" theme="light" auto_open="false"]
```

#### Inline Chat
```php
[workcity_chat type="inline" height="400px" chat_type="general"]
```

#### Chat Button
```php
[workcity_chat_button text="Start Chat" chat_type="product" product_id="123"]
```

#### Product-Specific Chat
```php
[workcity_chat chat_type="product" product_id="123" agent_types="product,design"]
```

### Programmatic Usage

#### Create Chat Session
```php
$chat_session = WorkCity_Chat_API::create_session([
    'title' => 'Product Support',
    'chat_type' => 'product',
    'product_id' => 123,
    'participants' => [
        ['user_id' => 1, 'role' => 'customer'],
        ['user_id' => 2, 'role' => 'agent']
    ]
]);
```

#### Send Message
```php
WorkCity_Chat_API::send_message([
    'session_id' => $session_id,
    'sender_id' => get_current_user_id(),
    'message_content' => 'Hello, I need help with this product.',
    'message_type' => 'text'
]);
```

#### Get Chat Sessions
```php
$sessions = WorkCity_Chat_API::get_user_sessions(get_current_user_id());
```

## 🎨 Customization

### Styling

The plugin includes comprehensive CSS that can be customized:

```css
/* Custom primary color */
:root {
    --wc-primary: #your-brand-color;
    --wc-primary-dark: #your-darker-shade;
}

/* Custom chat widget styling */
.workcity-chat-floating-widget {
    /* Your custom styles */
}
```

### JavaScript Hooks

```javascript
// Custom message handling
jQuery(document).on('workcity_chat_message_sent', function(event, messageData) {
    // Handle custom message processing
});

// Custom notification handling
WorkCityChat.onNotification = function(notification) {
    // Custom notification logic
};
```

### PHP Hooks

```php
// Customize agent assignment
add_filter('workcity_chat_auto_assign_agent', function($agent_id, $session_id, $chat_type) {
    // Custom assignment logic
    return $agent_id;
}, 10, 3);

// Customize notification content
add_filter('workcity_chat_notification_content', function($content, $notification_type) {
    // Customize notification messages
    return $content;
}, 10, 2);
```

## 📊 Admin Features

### Dashboard
- Real-time chat statistics
- Active/pending chat counts
- Agent status monitoring
- Recent chat sessions
- Quick action buttons

### Live Chat Interface
- Session management
- Real-time message handling
- Agent assignment
- Session status updates

### Reports
- Chat volume analytics
- Response time metrics
- Agent performance
- Customer satisfaction

### Settings
- General configuration
- Appearance customization
- Notification preferences
- Role management

## 🔌 API Reference

### REST API Endpoints

#### Sessions
```http
GET    /wp-json/workcity-chat/v1/sessions
POST   /wp-json/workcity-chat/v1/sessions
GET    /wp-json/workcity-chat/v1/sessions/{id}
PUT    /wp-json/workcity-chat/v1/sessions/{id}
```

#### Messages
```http
GET    /wp-json/workcity-chat/v1/sessions/{session_id}/messages
POST   /wp-json/workcity-chat/v1/sessions/{session_id}/messages
POST   /wp-json/workcity-chat/v1/sessions/{session_id}/read
```

#### Status
```http
POST   /wp-json/workcity-chat/v1/status
```

#### File Upload
```http
POST   /wp-json/workcity-chat/v1/upload
```

### AJAX Actions

#### Frontend Actions
- `workcity_chat_get_sessions`
- `workcity_chat_send_message`
- `workcity_chat_get_messages`
- `workcity_chat_mark_read`
- `workcity_chat_update_status`
- `workcity_chat_upload_file`

#### Admin Actions
- `workcity_chat_admin_stats`
- `workcity_chat_admin_assign_agent`
- `workcity_chat_admin_close_session`

## 🛠️ Development

### Technologies Used
- **Backend**: PHP 7.4+, WordPress API, MySQL
- **Frontend**: JavaScript (ES6+), jQuery, CSS3
- **Real-time**: AJAX polling, WebSocket ready
- **Charts**: Chart.js for analytics
- **UI**: Modern CSS Grid/Flexbox, responsive design

### Architecture
```
workcity-chat/
├── workcity-chat.php          # Main plugin file
├── includes/                  # Core classes
│   ├── class-chat-post-type.php
│   ├── class-chat-api.php
│   ├── class-chat-ajax.php
│   ├── class-chat-shortcode.php
│   ├── class-chat-roles.php
│   ├── class-woocommerce-integration.php
│   └── class-notifications.php
├── admin/                     # Admin interface
│   └── class-admin.php
├── assets/                    # Frontend assets
│   ├── css/
│   │   ├── frontend.css
│   │   └── admin.css
│   └── js/
│       ├── frontend.js
│       └── admin.js
├── languages/                 # Translations
└── README.md
```

### Database Schema

#### Chat Messages Table
```sql
CREATE TABLE wp_workcity_chat_messages (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    chat_session_id bigint(20) NOT NULL,
    sender_id bigint(20) NOT NULL,
    message_content longtext NOT NULL,
    message_type varchar(50) DEFAULT 'text',
    attachment_url varchar(255) DEFAULT NULL,
    is_read tinyint(1) DEFAULT 0,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
);
```

#### Chat Participants Table
```sql
CREATE TABLE wp_workcity_chat_participants (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    chat_session_id bigint(20) NOT NULL,
    user_id bigint(20) NOT NULL,
    role varchar(50) NOT NULL,
    joined_at datetime DEFAULT CURRENT_TIMESTAMP,
    last_seen datetime DEFAULT CURRENT_TIMESTAMP,
    is_active tinyint(1) DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY unique_participant (chat_session_id, user_id)
);
```

## 🧪 Testing

### Manual Testing Checklist

1. **Basic Functionality**
   - [ ] Plugin activation/deactivation
   - [ ] Chat session creation
   - [ ] Message sending/receiving
   - [ ] File upload functionality

2. **User Roles**
   - [ ] Customer chat creation
   - [ ] Agent assignment
   - [ ] Admin management
   - [ ] Permission checks

3. **WooCommerce Integration**
   - [ ] Product page chat buttons
   - [ ] Order-specific chats
   - [ ] Cart integration
   - [ ] Email notifications

4. **Responsive Design**
   - [ ] Mobile compatibility
   - [ ] Tablet optimization
   - [ ] Desktop functionality
   - [ ] Cross-browser testing

### Automated Testing

```bash
# PHPUnit tests (if implemented)
composer test

# JavaScript tests (if implemented)
npm test
```

## 🐛 Troubleshooting

### Common Issues

1. **Chat not loading**
   - Check if plugin is activated
   - Verify user permissions
   - Check browser console for errors

2. **Messages not sending**
   - Verify AJAX URL configuration
   - Check nonce verification
   - Ensure user is logged in

3. **File uploads failing**
   - Check file size limits
   - Verify upload directory permissions
   - Check allowed file types

4. **Notifications not working**
   - Verify notification settings
   - Check browser notification permissions
   - Ensure email configuration

### Debug Mode

Enable debug mode in wp-config.php:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WORKCITY_CHAT_DEBUG', true);
```

## 📞 Support

### Getting Help

1. **Documentation**: Check this README and inline code comments
2. **Issues**: Report bugs on GitHub Issues
3. **Feature Requests**: Submit via GitHub Issues
4. **Community**: Join our WordPress Slack channel

### Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Write tests if applicable
5. Submit a pull request

## 📄 License

This plugin is licensed under the GPL v2 or later.

```
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
```

## 🙏 Acknowledgments

- WordPress community for excellent documentation
- WooCommerce team for integration examples
- Chart.js for beautiful analytics
- Dribbble designers for UI inspiration

## 📈 Roadmap

### Version 1.1
- [ ] WebSocket support for real-time messaging
- [ ] Mobile app API
- [ ] Advanced analytics
- [ ] Multi-language support

### Version 1.2
- [ ] Video/voice chat integration
- [ ] AI chatbot integration
- [ ] Advanced file sharing
- [ ] Chat transcripts export

### Version 2.0
- [ ] Multi-site support
- [ ] Third-party integrations (Slack, Teams)
- [ ] Advanced automation
- [ ] Custom branding options

## 📧 Contact

- **Website**: [workcityafrica.com](https://workcityafrica.com)
- **Email**: careers@workcityafrica.com
- **GitHub**: [github.com/workcityafrica](https://github.com/workcityafrica)

---

**Made with ❤️ by the WorkCity Africa Team**