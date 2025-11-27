<?php
/**
 * Message System Class - FIXED VERSION
 *
 * @package Premium_Classifieds
 */

defined('ABSPATH') || exit;

class PC_Message_System {
    
    /**
     * Initialize hooks
     */
    public static function init(): void {
        add_action('wp_ajax_pc_send_message', [__CLASS__, 'ajax_send_message']);
        add_action('wp_ajax_pc_load_messages', [__CLASS__, 'ajax_load_messages']);
        add_action('wp_ajax_pc_mark_read', [__CLASS__, 'ajax_mark_read']);
        add_action('wp_ajax_pc_load_threads', [__CLASS__, 'ajax_load_threads']);
        add_action('wp_ajax_pc_get_unread_count', [__CLASS__, 'ajax_get_unread_count']);
    }

    /**
     * Validate if user has access to message about this listing
     */
    private static function validate_message_access(int $user_id, int $listing_id): bool {
        $listing = get_post($listing_id);
        
        if (!$listing || $listing->post_type !== 'listing') {
            return false;
        }
        
        // Owner can always message
        if ($user_id === (int) $listing->post_author) {
            return true;
        }
        
        // Others need access grant
        $access_db = new PC_DB_Access_Grants();
        return $access_db->check_access($user_id, $listing_id);
    }

    /**
     * Sanitize message content
     */
    private static function sanitize_message_content(string $content): string {
        // Remove all HTML except basic formatting
        $allowed_tags = '<br><p><strong><em><a>';
        $content = strip_tags($content, $allowed_tags);
        
        // Sanitize URLs in links
        $content = preg_replace_callback(
            '/<a\s+href=["\']([^"\']+)["\']/',
            function($matches) {
                return '<a href="' . esc_url($matches[1]) . '"';
            },
            $content
        );
        
        // Remove excessive whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);
        
        return $content;
    }

    /**
     * Send email notification for new message
     */
    private static function send_message_notification(int $message_id, array $message_data): void {
        if (get_option('pc_notify_new_messages', '1') !== '1') {
            return;
        }

        $sender = get_userdata($message_data['sender_id']);
        $recipient = get_userdata($message_data['recipient_id']);
        $listing = get_post($message_data['listing_id']);

        if (!$sender || !$recipient || !$listing) {
            return;
        }

        $subject = sprintf(
            __('[%s] New message about: %s', 'premium-classifieds'),
            get_bloginfo('name'),
            $listing->post_title
        );

        $message = sprintf(
            __("Hi %s,\n\nYou have received a new message from %s regarding your listing '%s'.\n\nMessage preview:\n%s\n\nView and reply: %s\n\n---\nThis is an automated notification from %s", 'premium-classifieds'),
            $recipient->display_name,
            $sender->display_name,
            $listing->post_title,
            wp_trim_words(wp_strip_all_tags($message_data['content']), 20),
            admin_url('admin.php?page=pc-dashboard&tab=inbox'),
            get_bloginfo('name')
        );

        $headers = [
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        ];

        wp_mail($recipient->user_email, $subject, $message, $headers);
    }

    /**
     * AJAX: Send Message
     */
    public static function ajax_send_message(): void {
        try {
            PC_Ajax_Handler::verify_nonce('pc_dashboard');

            $user_id = get_current_user_id();
            if (!$user_id) {
                PC_Ajax_Handler::send_error(__('You must be logged in.', 'premium-classifieds'), 401);
            }

            $listing_id = absint($_POST['listing_id'] ?? 0);
            $recipient_id = absint($_POST['recipient_id'] ?? 0);
            $content = sanitize_textarea_field($_POST['content'] ?? '');

            // Validation
            if (!$listing_id || !$recipient_id || empty($content)) {
                PC_Ajax_Handler::send_error(__('Missing required fields.', 'premium-classifieds'), 400);
            }

            if ($user_id === $recipient_id) {
                PC_Ajax_Handler::send_error(__('You cannot message yourself.', 'premium-classifieds'), 400);
            }

            if (strlen($content) > 2000) {
                PC_Ajax_Handler::send_error(__('Message is too long (max 2000 characters).', 'premium-classifieds'), 400);
            }

            // CRITICAL: Validate access
            if (!self::validate_message_access($user_id, $listing_id)) {
                PC_Ajax_Handler::send_error(
                    __('You must purchase contact access before sending messages.', 'premium-classifieds'),
                    403
                );
            }

            // Sanitize content
            $content = self::sanitize_message_content($content);

            // Save message
            $db = new PC_DB_Messages();
            $message_id = $db->create_message([
                'listing_id' => $listing_id,
                'sender_id' => $user_id,
                'recipient_id' => $recipient_id,
                'content' => $content
            ]);

            if (!$message_id) {
                PC_Ajax_Handler::send_error(__('Failed to send message.', 'premium-classifieds'), 500);
            }

            // Send email notification
            self::send_message_notification($message_id, [
                'sender_id' => $user_id,
                'recipient_id' => $recipient_id,
                'listing_id' => $listing_id,
                'content' => $content
            ]);

            $sender = get_userdata($user_id);

            PC_Ajax_Handler::send_success([
                'message_id' => $message_id,
                'html' => sprintf(
                    '<div class="pc-message pc-message-sent" data-message-id="%d">
                        <div class="pc-message-avatar">
                            %s
                        </div>
                        <div class="pc-message-content">
                            <div class="pc-message-header">
                                <strong>%s</strong>
                                <span class="pc-message-time">%s</span>
                            </div>
                            <div class="pc-message-body">%s</div>
                        </div>
                    </div>',
                    $message_id,
                    get_avatar($user_id, 32),
                    esc_html($sender->display_name),
                    esc_html__('Just now', 'premium-classifieds'),
                    wp_kses_post($content)
                ),
                'notice' => __('Message sent successfully!', 'premium-classifieds')
            ]);

        } catch (Exception $e) {
            error_log('PC Message Send Error: ' . $e->getMessage());
            PC_Ajax_Handler::send_error(__('An error occurred.', 'premium-classifieds'), 500);
        }
    }

    /**
     * AJAX: Load Messages
     */
    public static function ajax_load_messages(): void {
        try {
            PC_Ajax_Handler::verify_nonce('pc_dashboard');

            $user_id = get_current_user_id();
            if (!$user_id) {
                PC_Ajax_Handler::send_error(__('Unauthorized.', 'premium-classifieds'), 401);
            }

            $listing_id = absint($_POST['listing_id'] ?? 0);
            $other_user_id = absint($_POST['other_user_id'] ?? 0);
            $silent = isset($_POST['silent']) && $_POST['silent'] === 'true';

            if (!$listing_id || !$other_user_id) {
                PC_Ajax_Handler::send_error(__('Invalid parameters.', 'premium-classifieds'), 400);
            }

            // Verify user is part of this conversation
            $listing = get_post($listing_id);
            if (!$listing || ($user_id !== (int)$listing->post_author && $user_id !== $other_user_id)) {
                PC_Ajax_Handler::send_error(__('Access denied.', 'premium-classifieds'), 403);
            }

            $db = new PC_DB_Messages();
            $messages = $db->get_conversation($user_id, $other_user_id, $listing_id);

            // Mark as read
            $db->mark_thread_as_read($user_id, $other_user_id, $listing_id);

            $html = '';
            foreach ($messages as $msg) {
                $is_sent = (int)$msg->sender_id === $user_id;
                $sender = get_userdata($msg->sender_id);
                
                $html .= sprintf(
                    '<div class="pc-message %s" data-message-id="%d">
                        <div class="pc-message-avatar">
                            %s
                        </div>
                        <div class="pc-message-content">
                            <div class="pc-message-header">
                                <strong>%s</strong>
                                <span class="pc-message-time">%s</span>
                            </div>
                            <div class="pc-message-body">%s</div>
                        </div>
                    </div>',
                    $is_sent ? 'pc-message-sent' : 'pc-message-received',
                    $msg->id,
                    get_avatar($msg->sender_id, 32),
                    esc_html($sender->display_name),
                    esc_html(human_time_diff(strtotime($msg->created_at), current_time('timestamp')) . ' ago'),
                    wp_kses_post($msg->content)
                );
            }

            PC_Ajax_Handler::send_success([
                'html' => $html,
                'count' => count($messages)
            ]);

        } catch (Exception $e) {
            error_log('PC Load Messages Error: ' . $e->getMessage());
            PC_Ajax_Handler::send_error(__('Failed to load messages.', 'premium-classifieds'), 500);
        }
    }

    /**
     * AJAX: Load Threads
     */
    public static function ajax_load_threads(): void {
        try {
            PC_Ajax_Handler::verify_nonce('pc_dashboard');

            $user_id = get_current_user_id();
            if (!$user_id) {
                PC_Ajax_Handler::send_error(__('Unauthorized.', 'premium-classifieds'), 401);
            }

            $db = new PC_DB_Messages();
            $threads = $db->get_user_threads($user_id);

            $html = '';
            foreach ($threads as $thread) {
                $other_user = get_userdata($thread->other_user_id);
                $listing = get_post($thread->listing_id);
                
                if (!$other_user || !$listing) continue;

                $unread_class = $thread->unread_count > 0 ? 'pc-thread-unread' : '';
                
                $html .= sprintf(
                    '<div class="pc-inbox-thread %s" data-listing-id="%d" data-user-id="%d">
                        <div class="pc-thread-avatar">
                            %s
                            %s
                        </div>
                        <div class="pc-thread-content">
                            <div class="pc-thread-header">
                                <strong>%s</strong>
                                <span class="pc-thread-time">%s</span>
                            </div>
                            <div class="pc-thread-listing">%s</div>
                            <div class="pc-thread-preview">%s</div>
                        </div>
                    </div>',
                    $unread_class,
                    $thread->listing_id,
                    $thread->other_user_id,
                    get_avatar($thread->other_user_id, 48),
                    $thread->unread_count > 0 ? '<span class="pc-unread-badge">' . $thread->unread_count . '</span>' : '',
                    esc_html($other_user->display_name),
                    esc_html(human_time_diff(strtotime($thread->last_message_at), current_time('timestamp')) . ' ago'),
                    esc_html($listing->post_title),
                    esc_html(wp_trim_words(wp_strip_all_tags($thread->last_message), 10))
                );
            }

            if (empty($html)) {
                $html = '<div class="pc-empty-state"><p>' . __('No messages yet.', 'premium-classifieds') . '</p></div>';
            }

            PC_Ajax_Handler::send_success([
                'html' => $html,
                'total' => count($threads)
            ]);

        } catch (Exception $e) {
            error_log('PC Load Threads Error: ' . $e->getMessage());
            PC_Ajax_Handler::send_error(__('Failed to load threads.', 'premium-classifieds'), 500);
        }
    }

    /**
     * AJAX: Get Unread Count
     */
    public static function ajax_get_unread_count(): void {
        try {
            PC_Ajax_Handler::verify_nonce('pc_dashboard');

            $user_id = get_current_user_id();
            if (!$user_id) {
                PC_Ajax_Handler::send_success(['count' => 0]);
            }

            $db = new PC_DB_Messages();
            $count = $db->get_unread_count($user_id);

            PC_Ajax_Handler::send_success(['count' => $count]);

        } catch (Exception $e) {
            PC_Ajax_Handler::send_success(['count' => 0]);
        }
    }

    /**
     * AJAX: Mark as Read
     */
    public static function ajax_mark_read(): void {
        try {
            PC_Ajax_Handler::verify_nonce('pc_dashboard');

            $user_id = get_current_user_id();
            if (!$user_id) {
                PC_Ajax_Handler::send_error(__('Unauthorized.', 'premium-classifieds'), 401);
            }

            $listing_id = absint($_POST['listing_id'] ?? 0);
            $other_user_id = absint($_POST['other_user_id'] ?? 0);

            if (!$listing_id || !$other_user_id) {
                PC_Ajax_Handler::send_error(__('Invalid parameters.', 'premium-classifieds'), 400);
            }

            $db = new PC_DB_Messages();
            $db->mark_thread_as_read($user_id, $other_user_id, $listing_id);

            PC_Ajax_Handler::send_success(['message' => __('Marked as read.', 'premium-classifieds')]);

        } catch (Exception $e) {
            error_log('PC Mark Read Error: ' . $e->getMessage());
            PC_Ajax_Handler::send_error(__('Failed to mark as read.', 'premium-classifieds'), 500);
        }
    }
}

// Initialize
PC_Message_System::init();