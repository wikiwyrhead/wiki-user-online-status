<?php
/**
 * Plugin Name: Wiki User Online Status
 * Plugin URI: https://github.com/wikiwyrhead/wiki-user-online-status
 * Description: Track and display user online/offline status similar to WP User Online plugin.
 * Version: 1.0.2
 * Author: Arnel Go
 * Author URI: https://arnelbg.com/
 * License: GPLv2 or later
 * Text Domain: wiki-user-online-status
 * 
 * Changelog:
 * 1.0.2 - Added GPL-2.0-or-later LICENSE file
 * 1.0.1 - Fixed admin page warnings and improved user data handling
 * 1.0.0 - Initial release
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WikiUserOnlineStatus {
    
    private $table_name;
    private $online_timeout = 300; // 5 minutes in seconds
    private $cache_key_prefix = 'wusr_online_';
    private $cache_expiration = 300; // 5 minutes in seconds
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'user_online_status';
        
        // Hook into WordPress
        add_action('init', array($this, 'init'));
        add_action('wp_ajax_user_heartbeat', array($this, 'ajax_heartbeat'));
        add_action('wp_ajax_nopriv_user_heartbeat', array($this, 'ajax_heartbeat'));
        add_action('wp_login', array($this, 'user_login'), 10, 2);
        add_action('wp_logout', array($this, 'user_logout'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        
        // Admin hooks
        add_filter('manage_users_columns', array($this, 'add_online_status_column'));
        add_filter('manage_users_custom_column', array($this, 'show_online_status_column'), 10, 3);
        add_action('admin_head-users.php', array($this, 'admin_css'));
        
        // Database setup
        register_activation_hook(__FILE__, array($this, 'create_table'));
        register_deactivation_hook(__FILE__, array($this, 'cleanup_plugin'));
        
        // Cleanup old entries
        add_action('wp_scheduled_delete', array($this, 'cleanup_old_entries'));
    }
    
    public function init() {
        // Track user activity on every page load
        if (is_user_logged_in()) {
            $this->update_user_status();
        }
    }
    
    public function create_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->table_name} (
            id int(11) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            last_activity datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            ip_address varchar(45) NOT NULL,
            user_agent text,
            page_url varchar(255),
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id),
            KEY last_activity (last_activity)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    public function update_user_status($force = false) {
        if (!is_user_logged_in()) {
            return;
        }
        
        $user_id = get_current_user_id();
        $cache_key = $this->cache_key_prefix . $user_id;
        
        // Check if we've updated recently (within 30 seconds)
        if (!$force && false !== get_transient($cache_key)) {
            return;
        }
        
        global $wpdb;
        $ip_address = $this->get_user_ip();
        
        // Sanitize user agent and page URL
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? 
            sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
            
        $page_url = isset($_SERVER['REQUEST_URI']) ? 
            esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        
        // Set transient to prevent frequent updates
        set_transient($cache_key, '1', 30); // Cache for 30 seconds
        
        // Use INSERT ... ON DUPLICATE KEY UPDATE for better performance
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$this->table_name} (user_id, last_activity, ip_address, user_agent, page_url)
            VALUES (%d, %s, %s, %s, %s)
            ON DUPLICATE KEY UPDATE 
                last_activity = VALUES(last_activity),
                ip_address = VALUES(ip_address),
                user_agent = VALUES(user_agent),
                page_url = VALUES(page_url)",
            $user_id,
            current_time('mysql'),
            $ip_address,
            $user_agent,
            $page_url
        ));
    }
    
    public function ajax_heartbeat() {
        // Verify nonce for logged-in users
        if (is_user_logged_in() && !check_ajax_referer('user_heartbeat_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Invalid nonce'), 403);
            wp_die();
        }
        
        // For logged-out users, just return without updating status
        if (!is_user_logged_in()) {
            wp_send_json_success(array('status' => 'offline'));
            wp_die();
        }
        
        $this->update_user_status();
        wp_send_json_success(array('status' => 'online'));
    }
    
    public function user_login($user_login, $user) {
        $this->update_user_status();
    }
    
    public function user_logout() {
        if (is_user_logged_in()) {
            global $wpdb;
            $user_id = get_current_user_id();
            
            $wpdb->delete(
                $this->table_name,
                array('user_id' => $user_id),
                array('%d')
            );
        }
    }
    
    public function enqueue_admin_scripts($hook) {
        if ($hook === 'users.php' || $hook === 'user-edit.php' || $hook === 'profile.php') {
            wp_enqueue_script('user-online-status', plugin_dir_url(__FILE__) . 'assets/admin.js', array('jquery'), '1.0.0', true);
            wp_localize_script('user-online-status', 'userOnlineAjax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('user_heartbeat_nonce')
            ));
        }
    }
    
    public function enqueue_frontend_scripts() {
        if (is_user_logged_in()) {
            wp_enqueue_script('user-online-heartbeat', plugin_dir_url(__FILE__) . 'assets/frontend.js', array('jquery'), '1.0.0', true);
            wp_localize_script('user-online-heartbeat', 'userOnlineAjax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('user_heartbeat_nonce')
            ));
        }
    }
    
    public function add_online_status_column($columns) {
        $columns['user_online_status'] = 'User Online Status';
        return $columns;
    }
    
    public function show_online_status_column($value, $column_name, $user_id) {
        if ($column_name === 'user_online_status') {
            $is_online = $this->is_user_online($user_id);
            $last_seen = $this->get_user_last_seen($user_id);
            
            if ($is_online) {
                $value = '<span class="user-online-indicator online" title="User is online">●</span>';
            } else {
                $offline_time = $last_seen ? human_time_diff(strtotime($last_seen), current_time('timestamp')) . ' ago' : 'Never';
                $value = '<span class="user-online-indicator offline" title="Last seen: ' . $offline_time . '">●</span>';
                if ($last_seen) {
                    $value .= '<br><small>' . $offline_time . '</small>';
                }
            }
        }
        return $value;
    }
    
    public function admin_css() {
        echo '<style>
            .user-online-indicator {
                font-size: 16px;
                line-height: 1;
            }
            .user-online-indicator.online {
                color: #00a32a;
            }
            .user-online-indicator.offline {
                color: #d63638;
            }
            .column-user_online_status {
                width: 150px;
            }
        </style>';
    }
    
    public function is_user_online($user_id) {
        $cache_key = $this->cache_key_prefix . 'status_' . $user_id;
        $cached_status = get_transient($cache_key);
        
        // Return cached status if available and fresh (within 30 seconds)
        if (false !== $cached_status) {
            return 'online' === $cached_status;
        }
        
        global $wpdb;
        $last_activity = $wpdb->get_var($wpdb->prepare(
            "SELECT last_activity FROM {$this->table_name} WHERE user_id = %d",
            $user_id
        ));
        
        $is_online = false;
        
        if ($last_activity) {
            $time_diff = current_time('timestamp') - strtotime($last_activity);
            $is_online = $time_diff <= $this->online_timeout;
        }
        
        // Cache the status for 30 seconds
        set_transient($cache_key, $is_online ? 'online' : 'offline', 30);
        
        return $is_online;
    }
    
    public function get_online_users() {
        global $wpdb;
        $timeout = date('Y-m-d H:i:s', current_time('timestamp') - $this->online_timeout);
        
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * 
                FROM {$this->table_name} 
                WHERE last_activity > %s 
                ORDER BY last_activity DESC",
                $timeout
            )
        );
        
        // Add user data to each result
        foreach ($results as &$row) {
            $user_data = get_userdata($row->user_id);
            if ($user_data) {
                $row->display_name = $user_data->display_name;
                $row->user_email = $user_data->user_email;
                $row->roles = $user_data->roles;
            } else {
                $row->display_name = 'Guest';
                $row->user_email = '';
                $row->roles = array('none');
            }
            
            // Ensure all expected properties exist
            $row->ip_address = $row->ip_address ?? 'N/A';
            $row->page_url = $row->page_url ?? 'N/A';
            $row->ID = $row->user_id; // For backward compatibility
        }
        
        return $results;
    }
    
    public function get_user_last_seen($user_id) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT last_activity FROM {$this->table_name} WHERE user_id = %d",
            $user_id
        ));
    }
    
    public function get_online_users_list() {
        global $wpdb;
        $time_limit = date('Y-m-d H:i:s', current_time('timestamp') - $this->online_timeout);
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT u.*, uo.last_activity, uo.ip_address, uo.page_url 
             FROM {$wpdb->users} u 
             INNER JOIN {$this->table_name} uo ON u.ID = uo.user_id 
             WHERE uo.last_activity > %s 
             ORDER BY uo.last_activity DESC",
            $time_limit
        ));
    }
    
    public function get_online_users_count() {
        global $wpdb;
        $time_limit = date('Y-m-d H:i:s', current_time('timestamp') - $this->online_timeout);
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE last_activity > %s",
            $time_limit
        ));
    }
    
    public function cleanup_old_entries() {
        global $wpdb;
        $timeout = date('Y-m-d H:i:s', current_time('timestamp') - $this->online_timeout);
        
        // Get users to be cleaned up
        $expired_users = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM {$this->table_name} WHERE last_activity < %s",
            $timeout
        ));
        
        if (!empty($expired_users)) {
            // Delete old entries in chunks to avoid long-running queries
            $chunks = array_chunk($expired_users, 100);
            foreach ($chunks as $chunk) {
                $placeholders = implode(',', array_fill(0, count($chunk), '%d'));
                $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM {$this->table_name} WHERE user_id IN ($placeholders)",
                        $chunk
                    )
                );
                
                // Clear cache for these users
                foreach ($chunk as $user_id) {
                    delete_transient($this->cache_key_prefix . 'status_' . $user_id);
                    delete_transient($this->cache_key_prefix . $user_id);
                }
                
                // Give the database a small break
                if (count($chunks) > 1) {
                    usleep(100000); // 100ms
                }
            }
        }
    }
    
    public function cleanup_plugin() {
        // Clean up when plugin is deactivated
        $this->cleanup_old_entries();
    }
    
    private function get_user_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}

// Initialize the plugin
new WikiUserOnlineStatus();

// Shortcode to display online users
function display_online_users_shortcode($atts) {
    $plugin = new WikiUserOnlineStatus();
    $online_users = $plugin->get_online_users();
    
    if (empty($online_users)) {
        return '<p>No users currently online.</p>';
    }
    
    $output = '<div class="online-users-list">';
    $output .= '<h3>Users Currently Online (' . count($online_users) . ')</h3>';
    $output .= '<ul>';
    
    foreach ($online_users as $user) {
        $output .= '<li>';
        $output .= get_avatar($user->ID, 32) . ' ';
        $output .= '<strong>' . $user->display_name . '</strong> ';
        $output .= '<small>(' . human_time_diff(strtotime($user->last_activity), current_time('timestamp')) . ' ago)</small>';
        $output .= '</li>';
    }
    
    $output .= '</ul></div>';
    
    return $output;
}
add_shortcode('online_users', 'display_online_users_shortcode');

// Widget for online users count
function online_users_count_shortcode() {
    $plugin = new WikiUserOnlineStatus();
    $count = $plugin->get_online_users_count();
    
    return '<span class="online-users-count">Users Online: ' . $count . '</span>';
}
add_shortcode('online_users_count', 'online_users_count_shortcode');

// Admin menu for online users
function add_online_users_admin_menu() {
    add_users_page(
        'Online Users',
        'Online Users',
        'manage_options',
        'online-users',
        'online_users_admin_page'
    );
}
add_action('admin_menu', 'add_online_users_admin_menu');

function online_users_admin_page() {
    $plugin = new WikiUserOnlineStatus();
    $online_users = $plugin->get_online_users();
    
    echo '<div class="wrap">';
    echo '<h1>Users Currently Online</h1>';
    
    if (empty($online_users)) {
        echo '<p>No users are currently online.</p>';
    } else {
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>Avatar</th><th>User</th><th>Role</th><th>Last Activity</th><th>IP Address</th><th>Current Page</th>';
        echo '</tr></thead><tbody>';
        
        foreach ($online_users as $user) {
            echo '<tr>';
            echo '<td>' . get_avatar($user->user_id, 32) . '</td>';
            echo '<td><strong>' . esc_html($user->display_name) . '</strong><br><small>' . esc_html($user->user_email) . '</small></td>';
            echo '<td>' . esc_html(implode(', ', (array)$user->roles)) . '</td>';
            echo '<td>' . esc_html(human_time_diff(strtotime($user->last_activity), current_time('timestamp'))) . ' ago</td>';
            echo '<td>' . esc_html($user->ip_address) . '</td>';
            echo '<td><small>' . esc_html($user->page_url) . '</small></td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    }
    
    echo '</div>';
}

?>