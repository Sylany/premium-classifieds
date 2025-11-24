<?php
/**
 * Messages Database Model
 *
 * Handles internal messaging system between buyers and sellers.
 * Messages are only accessible after payment for contact access.
 *
 * @package    PremiumClassifieds
 * @subpackage Database
 * @since      2.1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Messages Database Class
 *
 * @since 2.1.0
 */
class PC_DB_Messages {
    
    /**
     * Table name (without prefix)
     *
     * @var string
     */
    private string $table_name;
    
    /**
     * WordPress database object
     *
     * @var wpdb
     */
    private $wpdb;
    
    /**
     * Constructor
     *
     * @since 2.1.0
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'pc_messages';
    }
    
    /**
     * Send a message
     *
     * @since 2.1.0
     * @param array $args {
     *     Message data
     *     @type int    $from_user_id User ID sending the message
     *     @type int    $to_user_id   User ID receiving the message
     *     @type int    $listing_id   Related listing ID
     *     @type string $subject      Message subject (optional)
     *     @type string $message      Message content
     * }
     * @return int|false Message ID on success, false on failure
     */
    public function send_message(array $args) {
        // Validate required fields
        $required = ['from_user_id', 'to_user_id', 'listing_id', 'message'];
        foreach ($required as $field) {
            if (empty($args[$field])) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("PC_DB_Messages: Missing required field '{$field}'");
                }
                return false;
            }
        }
        
        // Verify users exist
        $from_user = get_userdata($args['from_user_id']);
        $to_user = get_userdata($args['to_user_id']);
        
        if (!$from_user || !$to_user) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('PC_DB_Messages: Invalid user IDs');
            }
            return false;
        }
        
        // Verify listing exists
        $listing = get_post($args['listing_id']);
        if (!$listing || $listing->post_type !== 'listing') {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('PC_DB_Messages: Invalid listing ID');
            }
            return false;
        }
        
        // Check if sender has access to contact (unless they're the listing owner)
        if ($args['from_user_id'] != $listing->post_author) {
            $access_grants = new PC_DB_Access_Grants();
            if (!$access_grants->check_access($args['from_user_id'], $args['listing_id'])) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('PC_DB_Messages: User does not have access to contact');
                }
                return false;
            }
        }
        
        // Sanitize message content
        $message_content = wp_kses_post($args['message']);
        if (strlen($message_content) < 10) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('PC_DB_Messages: Message too short');
            }
            return false;
        }
        
        // Prepare data
        $data = [
            'from_user_id' => absint($args['from_user_id']),
            'to_user_id'   => absint($args['to_user_id']),
            'listing_id'   => absint($args['listing_id']),
            'subject'      => isset($args['subject']) ? sanitize_text_field($args['subject']) : '',
            'message'      => $message_content,
            'is_read'      => 0,
            'created_at'   => current_time('mysql'),
        ];
        
        // Insert message
        $result = $this->wpdb->insert(
            $this->table_name,
            $data,
            ['%d', '%d', '%d', '%s', '%s', '%d', '%s']
        );
        
        if ($result === false) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('PC_DB_Messages: Insert failed - ' . $this->wpdb->last_error);
            }
            return false;
        }
        
        $message_id = $this->wpdb->insert_id;
        
        // Send email notification
        $this->send_notification_email($message_id);
        
        return $message_id;
    }
    
    /**
     * Get user's inbox messages
     *
     * @since 2.1.0
     * @param int $user_id User ID
     * @param array $args {
     *     Optional query arguments
     *     @type bool   $unread_only Only unread messages (default: false)
     *     @type int    $listing_id  Filter by listing ID
     *     @type int    $limit       Number of results (default: 50)
     *     @type int    $offset      Query offset (default: 0)
     * }
     * @return array Array of message objects with sender info
     */
    public function get_inbox(int $user_id, array $args = []): array {
        $defaults = [
            'unread_only' => false,
            'listing_id'  => 0,
            'limit'       => 50,
            'offset'      => 0,
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        // Build WHERE clause
        $where = $this->wpdb->prepare('m.to_user_id = %d', $user_id);
        
        if ($args['unread_only']) {
            $where .= ' AND m.is_read = 0';
        }
        
        if ($args['listing_id'] > 0) {
            $where .= $this->wpdb->prepare(' AND m.listing_id = %d', $args['listing_id']);
        }
        
        $sql = "SELECT m.*, 
                       u.display_name as sender_name,
                       u.user_email as sender_email,
                       p.post_title as listing_title
                FROM {$this->table_name} m
                LEFT JOIN {$this->wpdb->users} u ON m.from_user_id = u.ID
                LEFT JOIN {$this->wpdb->posts} p ON m.listing_id = p.ID
                WHERE {$where}
                ORDER BY m.created_at DESC
                LIMIT %d OFFSET %d";
        
        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $args['limit'], $args['offset'])
        );
    }
    
    /**
     * Get user's sent messages
     *
     * @since 2.1.0
     * @param int $user_id User ID
     * @param array $args Query arguments (same as get_inbox)
     * @return array Array of message objects with recipient info
     */
    public function get_sent_messages(int $user_id, array $args = []): array {
        $defaults = [
            'listing_id' => 0,
            'limit'      => 50,
            'offset'     => 0,
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        // Build WHERE clause
        $where = $this->wpdb->prepare('m.from_user_id = %d', $user_id);
        
        if ($args['listing_id'] > 0) {
            $where .= $this->wpdb->prepare(' AND m.listing_id = %d', $args['listing_id']);
        }
        
        $sql = "SELECT m.*, 
                       u.display_name as recipient_name,
                       u.user_email as recipient_email,
                       p.post_title as listing_title
                FROM {$this->table_name} m
                LEFT JOIN {$this->wpdb->users} u ON m.to_user_id = u.ID
                LEFT JOIN {$this->wpdb->posts} p ON m.listing_id = p.ID
                WHERE {$where}
                ORDER BY m.created_at DESC
                LIMIT %d OFFSET %d";
        
        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $args['limit'], $args['offset'])
        );
    }
    
    /**
     * Get conversation history between two users for a listing
     *
     * @since 2.1.0
     * @param int $user1_id First user ID
     * @param int $user2_id Second user ID
     * @param int $listing_id Listing ID
     * @return array Array of messages ordered by date
     */
    public function get_conversation_history(int $user1_id, int $user2_id, int $listing_id): array {
        $sql = "SELECT m.*, 
                       u.display_name as sender_name,
                       u.user_email as sender_email
                FROM {$this->table_name} m
                LEFT JOIN {$this->wpdb->users} u ON m.from_user_id = u.ID
                WHERE m.listing_id = %d
                AND (
                    (m.from_user_id = %d AND m.to_user_id = %d)
                    OR
                    (m.from_user_id = %d AND m.to_user_id = %d)
                )
                ORDER BY m.created_at ASC";
        
        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $listing_id, $user1_id, $user2_id, $user2_id, $user1_id)
        );
    }
    
    /**
     * Mark message as read
     *
     * @since 2.1.0
     * @param int $message_id Message ID
     * @param int $user_id User ID (for security check)
     * @return bool True on success, false on failure
     */
    public function mark_as_read(int $message_id, int $user_id): bool {
        // Verify user is the recipient
        $message = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d",
                $message_id
            )
        );
        
        if (!$message || (int) $message->to_user_id !== $user_id) {
            return false;
        }
        
        $result = $this->wpdb->update(
            $this->table_name,
            [
                'is_read' => 1,
                'read_at' => current_time('mysql'),
            ],
            ['id' => $message_id],
            ['%d', '%s'],
            ['%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Mark all messages as read for a user
     *
     * @since 2.1.0
     * @param int $user_id User ID
     * @param int $listing_id Optional listing ID filter
     * @return int Number of messages updated
     */
    public function mark_all_as_read(int $user_id, int $listing_id = 0): int {
        $where = ['to_user_id' => $user_id, 'is_read' => 0];
        $where_format = ['%d', '%d'];
        
        if ($listing_id > 0) {
            $where['listing_id'] = $listing_id;
            $where_format[] = '%d';
        }
        
        $result = $this->wpdb->update(
            $this->table_name,
            [
                'is_read' => 1,
                'read_at' => current_time('mysql'),
            ],
            $where,
            ['%d', '%s'],
            $where_format
        );
        
        return (int) $result;
    }
    
    /**
     * Get unread message count
     *
     * @since 2.1.0
     * @param int $user_id User ID
     * @return int Number of unread messages
     */
    public function get_unread_count(int $user_id): int {
        $count = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} 
                 WHERE to_user_id = %d AND is_read = 0",
                $user_id
            )
        );
        
        return (int) $count;
    }
    
    /**
     * Delete message
     *
     * @since 2.1.0
     * @param int $message_id Message ID
     * @param int $user_id User ID (must be sender or recipient)
     * @return bool True on success, false on failure
     */
    public function delete_message(int $message_id, int $user_id): bool {
        // Verify user is involved in the message
        $message = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d",
                $message_id
            )
        );
        
        if (!$message) {
            return false;
        }
        
        $is_participant = (
            (int) $message->from_user_id === $user_id || 
            (int) $message->to_user_id === $user_id
        );
        
        if (!$is_participant && !current_user_can('manage_options')) {
            return false;
        }
        
        $result = $this->wpdb->delete(
            $this->table_name,
            ['id' => $message_id],
            ['%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Get message thread count for a listing
     *
     * @since 2.1.0
     * @param int $listing_id Listing ID
     * @return int Number of unique conversations
     */
    public function get_thread_count(int $listing_id): int {
        $count = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(DISTINCT CONCAT(LEAST(from_user_id, to_user_id), '-', GREATEST(from_user_id, to_user_id)))
                 FROM {$this->table_name}
                 WHERE listing_id = %d",
                $listing_id
            )
        );
        
        return (int) $count;
    }
    
    /**
     * Get users who messaged about a listing
     *
     * @since 2.1.0
     * @param int $listing_id Listing ID
     * @return array Array of user IDs
     */
    public function get_messaging_users(int $listing_id): array {
        $results = $this->wpdb->get_col(
            $this->wpdb->prepare(
                "SELECT DISTINCT from_user_id 
                 FROM {$this->table_name} 
                 WHERE listing_id = %d",
                $listing_id
            )
        );
        
        return array_map('intval', $results);
    }
    
    /**
     * Send email notification about new message
     *
     * @since 2.1.0
     * @param int $message_id Message ID
     * @return void
     */
    private function send_notification_email(int $message_id): void {
        if (get_option('pc_notify_new_message', '1') !== '1') {
            return;
        }
        
        $message = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT m.*, 
                        u1.display_name as sender_name,
                        u2.user_email as recipient_email,
                        p.post_title as listing_title
                 FROM {$this->table_name} m
                 LEFT JOIN {$this->wpdb->users} u1 ON m.from_user_id = u1.ID
                 LEFT JOIN {$this->wpdb->users} u2 ON m.to_user_id = u2.ID
                 LEFT JOIN {$this->wpdb->posts} p ON m.listing_id = p.ID
                 WHERE m.id = %d",
                $message_id
            )
        );
        
        if (!$message) {
            return;
        }
        
        $subject = sprintf(
            __('[%s] New message from %s', 'premium-classifieds'),
            get_bloginfo('name'),
            $message->sender_name
        );
        
        $message_body = sprintf(
            __("You have received a new message about your listing '%s':\n\n%s\n\nView and reply: %s", 'premium-classifieds'),
            $message->listing_title,
            wp_strip_all_tags($message->message),
            add_query_arg('tab', 'inbox', home_url('/dashboard/'))
        );
        
        wp_mail($message->recipient_email, $subject, $message_body);
    }
    
    /**
     * Cleanup old messages (admin tool)
     *
     * @since 2.1.0
     * @param int $days Delete messages older than X days
     * @return int Number of deleted rows
     */
    public function cleanup_old_messages(int $days = 180): int {
        $date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE created_at < %s",
                $date
            )
        );
        
        return (int) $result;
    }
    
    /**
     * Get message by ID
     *
     * @since 2.1.0
     * @param int $message_id Message ID
     * @return object|null Message object or null if not found
     */
    public function get_message(int $message_id): ?object {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT m.*, 
                        u1.display_name as sender_name,
                        u2.display_name as recipient_name,
                        p.post_title as listing_title
                 FROM {$this->table_name} m
                 LEFT JOIN {$this->wpdb->users} u1 ON m.from_user_id = u1.ID
                 LEFT JOIN {$this->wpdb->users} u2 ON m.to_user_id = u2.ID
                 LEFT JOIN {$this->wpdb->posts} p ON m.listing_id = p.ID
                 WHERE m.id = %d",
                $message_id
            )
        ) ?: null;
    }
}
