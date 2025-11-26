<?php
/**
 * Message System Controller
 *
 * Handles AJAX requests for internal messaging between buyers and sellers.
 * Users can only message after purchasing contact access.
 *
 * @package    PremiumClassifieds
 * @subpackage Messaging
 * @since      2.1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Message System Class
 *
 * @since 2.1.0
 */
class PC_Message_System {
    
    /**
     * Messages database
     *
     * @var PC_DB_Messages
     */
    private static ?PC_DB_Messages $messages_db = null;
    
    /**
     * Get messages database instance
     *
     * @since 2.1.0
     * @return PC_DB_Messages
     */
    private static function get_db(): PC_DB_Messages {
        if (null === self::$messages_db) {
            self::$messages_db = new PC_DB_Messages();
        }
        return self::$messages_db;
    }
    
    /**
     * AJAX: Send message
     *
     * @since 2.1.0
     * @return void
     */
    public static function send_message(): void {
        // Validate required fields
        PC_Ajax_Handler::validate_required(['listing_id', 'message']);
        
        $user_id = get_current_user_id();
        $listing_id = PC_Ajax_Handler::get_post('listing_id', 0, 'int');
        $subject = PC_Ajax_Handler::get_post('subject', '', 'text');
        $message = PC_Ajax_Handler::get_post('message', '', 'textarea');
        
        // Validate listing exists
        $listing = get_post($listing_id);
        if (!$listing || $listing->post_type !== 'listing') {
            PC_Ajax_Handler::send_error(__('Invalid listing.', 'premium-classifieds'), 400);
        }
        
        $recipient_id = (int) $listing->post_author;
        
        // Check if user is trying to message themselves
        if ($user_id === $recipient_id) {
            PC_Ajax_Handler::send_error(__('You cannot send messages to yourself.', 'premium-classifieds'), 400);
        }
        
        // Check if user has access to contact (unless they are the listing owner)
        $access_db = new PC_DB_Access_Grants();
        if (!$access_db->check_access($user_id, $listing_id)) {
            PC_Ajax_Handler::send_error(
                __('You must purchase contact access before sending messages.', 'premium-classifieds'), 
                403
            );
        }
        
        // Validate message length
        if (strlen($message) < 10) {
            PC_Ajax_Handler::send_error(__('Message must be at least 10 characters long.', 'premium-classifieds'), 400);
        }
        
        if (strlen($message) > 5000) {
            PC_Ajax_Handler::send_error(__('Message is too long (max 5000 characters).', 'premium-classifieds'), 400);
        }
        
        // Rate limiting - max 5 messages per minute
        if (!PC_Security::check_rate_limit('send_message_' . $user_id, 5, 60)) {
            PC_Ajax_Handler::send_error(
                __('You are sending messages too quickly. Please wait a moment.', 'premium-classifieds'),
                429
            );
        }
        
        // Send message
        $db = self::get_db();
        $message_id = $db->send_message([
            'from_user_id' => $user_id,
            'to_user_id'   => $recipient_id,
            'listing_id'   => $listing_id,
            'subject'      => $subject,
            'message'      => $message,
        ]);
        
        if (!$message_id) {
            PC_Ajax_Handler::send_error(__('Failed to send message. Please try again.', 'premium-classifieds'), 500);
        }
        
        // Log event
        PC_Security::log_security_event('Message sent', [
            'message_id' => $message_id,
            'listing_id' => $listing_id,
            'recipient_id' => $recipient_id
        ]);
        
        PC_Ajax_Handler::send_success([
            'message' => __('Message sent successfully!', 'premium-classifieds'),
            'message_id' => $message_id,
        ]);
    }
    
    /**
     * AJAX: Load messages (inbox or conversation)
     *
     * @since 2.1.0
     * @return void
     */
    public static function load_messages(): void {
        $user_id = get_current_user_id();
        $view = PC_Ajax_Handler::get_post('view', 'inbox', 'text'); // inbox, sent, conversation
        $listing_id = PC_Ajax_Handler::get_post('listing_id', 0, 'int');
        $other_user_id = PC_Ajax_Handler::get_post('other_user_id', 0, 'int');
        $unread_only = PC_Ajax_Handler::get_post('unread_only', false, 'bool');
        
        $db = self::get_db();
        
        switch ($view) {
            case 'inbox':
                $messages = $db->get_inbox($user_id, [
                    'unread_only' => $unread_only,
                    'limit' => 50,
                ]);
                break;
                
            case 'sent':
                $messages = $db->get_sent_messages($user_id, [
                    'limit' => 50,
                ]);
                break;
                
            case 'conversation':
                if (!$other_user_id || !$listing_id) {
                    PC_Ajax_Handler::send_error(__('Missing conversation parameters.', 'premium-classifieds'), 400);
                }
                
                $messages = $db->get_conversation_history($user_id, $other_user_id, $listing_id);
                
                // Mark messages as read
                foreach ($messages as $msg) {
                    if ($msg->to_user_id == $user_id && !$msg->is_read) {
                        $db->mark_as_read($msg->id, $user_id);
                    }
                }
                break;
                
            default:
                PC_Ajax_Handler::send_error(__('Invalid view type.', 'premium-classifieds'), 400);
        }
        
        // Format messages for frontend
        $formatted_messages = array_map([self::class, 'format_message'], $messages);
        
        PC_Ajax_Handler::send_success([
            'messages' => $formatted_messages,
            'unread_count' => $db->get_unread_count($user_id),
        ]);
    }
    
    /**
     * AJAX: Mark message as read
     *
     * @since 2.1.0
     * @return void
     */
    public static function mark_read(): void {
        PC_Ajax_Handler::validate_required(['message_id']);
        
        $message_id = PC_Ajax_Handler::get_post('message_id', 0, 'int');
        $user_id = get_current_user_id();
        
        $db = self::get_db();
        $result = $db->mark_as_read($message_id, $user_id);
        
        if (!$result) {
            PC_Ajax_Handler::send_error(__('Failed to mark message as read.', 'premium-classifieds'), 500);
        }
        
        PC_Ajax_Handler::send_success([
            'message' => __('Message marked as read.', 'premium-classifieds'),
            'unread_count' => $db->get_unread_count($user_id),
        ]);
    }
    
    /**
     * AJAX: Mark all messages as read
     *
     * @since 2.1.0
     * @return void
     */
    public static function mark_all_read(): void {
        $user_id = get_current_user_id();
        $listing_id = PC_Ajax_Handler::get_post('listing_id', 0, 'int');
        
        $db = self::get_db();
        $count = $db->mark_all_as_read($user_id, $listing_id);
        
        PC_Ajax_Handler::send_success([
            'message' => sprintf(__('%d messages marked as read.', 'premium-classifieds'), $count),
            'count' => $count,
        ]);
    }
    
    /**
     * AJAX: Delete message
     *
     * @since 2.1.0
     * @return void
     */
    public static function delete_message(): void {
        PC_Ajax_Handler::validate_required(['message_id']);
        
        $message_id = PC_Ajax_Handler::get_post('message_id', 0, 'int');
        $user_id = get_current_user_id();
        
        $db = self::get_db();
        $result = $db->delete_message($message_id, $user_id);
        
        if (!$result) {
            PC_Ajax_Handler::send_error(__('Failed to delete message.', 'premium-classifieds'), 500);
        }
        
        PC_Ajax_Handler::send_success([
            'message' => __('Message deleted successfully.', 'premium-classifieds'),
        ]);
    }
    
    /**
     * AJAX: Get message threads (conversations grouped by listing)
     *
     * @since 2.1.0
     * @return void
     */
    public static function get_threads(): void {
        global $wpdb;
        $user_id = get_current_user_id();
        
        // Get unique conversations
        $threads = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                m.listing_id,
                p.post_title as listing_title,
                CASE 
                    WHEN m.from_user_id = %d THEN m.to_user_id 
                    ELSE m.from_user_id 
                END as other_user_id,
                u.display_name as other_user_name,
                MAX(m.created_at) as last_message_at,
                COUNT(CASE WHEN m.to_user_id = %d AND m.is_read = 0 THEN 1 END) as unread_count,
                (SELECT message FROM {$wpdb->prefix}pc_messages 
                 WHERE listing_id = m.listing_id 
                 AND (
                     (from_user_id = %d AND to_user_id = other_user_id) 
                     OR (from_user_id = other_user_id AND to_user_id = %d)
                 )
                 ORDER BY created_at DESC LIMIT 1) as last_message_preview
            FROM {$wpdb->prefix}pc_messages m
            LEFT JOIN {$wpdb->posts} p ON m.listing_id = p.ID
            LEFT JOIN {$wpdb->users} u ON CASE 
                WHEN m.from_user_id = %d THEN m.to_user_id 
                ELSE m.from_user_id 
            END = u.ID
            WHERE m.from_user_id = %d OR m.to_user_id = %d
            GROUP BY m.listing_id, other_user_id
            ORDER BY last_message_at DESC",
            $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id
        ));
        
        // Format threads
        $formatted_threads = array_map(function($thread) {
            return [
                'listing_id' => (int) $thread->listing_id,
                'listing_title' => $thread->listing_title,
                'other_user_id' => (int) $thread->other_user_id,
                'other_user_name' => $thread->other_user_name,
                'last_message_at' => $thread->last_message_at,
                'last_message_preview' => wp_trim_words($thread->last_message_preview, 10),
                'unread_count' => (int) $thread->unread_count,
                'listing_url' => get_permalink($thread->listing_id),
            ];
        }, $threads);
        
        PC_Ajax_Handler::send_success([
            'threads' => $formatted_threads,
        ]);
    }
    
    /**
     * Format message for frontend
     *
     * @since 2.1.0
     * @param object $message Message object from database
     * @return array Formatted message
     */
    private static function format_message(object $message): array {
        $user_id = get_current_user_id();
        
        return [
            'id' => (int) $message->id,
            'from_user_id' => (int) $message->from_user_id,
            'to_user_id' => (int) $message->to_user_id,
            'listing_id' => (int) $message->listing_id,
            'subject' => $message->subject,
            'message' => $message->message,
            'is_read' => (bool) $message->is_read,
            'created_at' => $message->created_at,
            'read_at' => $message->read_at,
            'is_mine' => $message->from_user_id == $user_id,
            'sender_name' => $message->sender_name ?? $message->display_name ?? '',
            'listing_title' => $message->listing_title ?? '',
            'time_ago' => human_time_diff(strtotime($message->created_at), current_time('timestamp')),
        ];
    }
    
    /**
     * Get unread count for current user (used in dashboard badge)
     *
     * @since 2.1.0
     * @return int Unread message count
     */
    public static function get_unread_count(): int {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return 0;
        }
        
        $db = self::get_db();
        return $db->get_unread_count($user_id);
    }
}
