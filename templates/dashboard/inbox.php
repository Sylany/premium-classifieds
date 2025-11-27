<?php
/**
 * Dashboard Inbox Template - ENHANCED WITH REAL-TIME
 *
 * @package Premium_Classifieds
 */

defined('ABSPATH') || exit;

$user_id = get_current_user_id();
$db = new PC_DB_Messages();
$threads = $db->get_user_threads($user_id);
?>

<div class="pc-inbox-container">
    <!-- Sidebar with Threads -->
    <div class="pc-inbox-sidebar">
        <div class="pc-inbox-header">
            <h3><?php esc_html_e('Messages', 'premium-classifieds'); ?></h3>
            <button type="button" class="pc-btn-icon" id="pc-refresh-threads" title="<?php esc_attr_e('Refresh', 'premium-classifieds'); ?>">
                <span class="dashicons dashicons-update"></span>
            </button>
        </div>

        <div id="pc-inbox-threads" class="pc-inbox-threads">
            <?php if (empty($threads)): ?>
                <div class="pc-empty-state">
                    <div class="pc-empty-icon">
                        <span class="dashicons dashicons-email-alt"></span>
                    </div>
                    <p><?php esc_html_e('No messages yet', 'premium-classifieds'); ?></p>
                    <small><?php esc_html_e('Messages will appear here when someone contacts you about your listings.', 'premium-classifieds'); ?></small>
                </div>
            <?php else: ?>
                <?php foreach ($threads as $thread): 
                    $other_user = get_userdata($thread->other_user_id);
                    $listing = get_post($thread->listing_id);
                    
                    if (!$other_user || !$listing) continue;
                    
                    $unread_class = $thread->unread_count > 0 ? 'pc-thread-unread' : '';
                ?>
                    <div class="pc-inbox-thread <?php echo esc_attr($unread_class); ?>" 
                         data-listing-id="<?php echo esc_attr($thread->listing_id); ?>"
                         data-user-id="<?php echo esc_attr($thread->other_user_id); ?>">
                        <div class="pc-thread-avatar">
                            <?php echo get_avatar($thread->other_user_id, 48); ?>
                            <?php if ($thread->unread_count > 0): ?>
                                <span class="pc-unread-badge"><?php echo esc_html($thread->unread_count); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="pc-thread-content">
                            <div class="pc-thread-header">
                                <strong><?php echo esc_html($other_user->display_name); ?></strong>
                                <span class="pc-thread-time">
                                    <?php echo esc_html(human_time_diff(strtotime($thread->last_message_at), current_time('timestamp'))); ?> ago
                                </span>
                            </div>
                            <div class="pc-thread-listing"><?php echo esc_html($listing->post_title); ?></div>
                            <div class="pc-thread-preview">
                                <?php echo esc_html(wp_trim_words(wp_strip_all_tags($thread->last_message), 10)); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Main Conversation Area -->
    <div class="pc-inbox-main">
        <div id="pc-inbox-empty" class="pc-empty-state" style="display: block;">
            <div class="pc-empty-icon">
                <span class="dashicons dashicons-format-chat"></span>
            </div>
            <h3><?php esc_html_e('Select a conversation', 'premium-classifieds'); ?></h3>
            <p><?php esc_html_e('Choose a message from the left to start chatting.', 'premium-classifieds'); ?></p>
        </div>

        <div id="pc-inbox-conversation" style="display: none;">
            <div class="pc-conversation-header">
                <div class="pc-conversation-info">
                    <div id="pc-conversation-avatar"></div>
                    <div>
                        <strong id="pc-conversation-name"></strong>
                        <div class="pc-conversation-listing" id="pc-conversation-listing"></div>
                    </div>
                </div>
                <div class="pc-conversation-actions">
                    <button type="button" class="pc-btn-icon" id="pc-refresh-conversation" title="<?php esc_attr_e('Refresh', 'premium-classifieds'); ?>">
                        <span class="dashicons dashicons-update"></span>
                    </button>
                    <button type="button" class="pc-btn-icon" id="pc-close-conversation" title="<?php esc_attr_e('Close', 'premium-classifieds'); ?>">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
            </div>

            <div id="pc-messages-container" class="pc-messages-container"></div>

            <form id="pc-send-message-form" class="pc-send-message-form">
                <input type="hidden" name="listing_id" id="pc-message-listing-id">
                <input type="hidden" name="recipient_id" id="pc-message-recipient-id">
                
                <div class="pc-message-input-wrapper">
                    <textarea 
                        name="content" 
                        id="pc-message-content" 
                        class="pc-message-input" 
                        placeholder="<?php esc_attr_e('Type your message...', 'premium-classifieds'); ?>"
                        rows="3"
                        maxlength="2000"
                        required
                    ></textarea>
                    <div class="pc-message-input-footer">
                        <span class="pc-char-count">
                            <span id="pc-char-current">0</span> / 2000
                        </span>
                        <button type="submit" class="pc-btn pc-btn-primary" id="pc-send-message-btn">
                            <span class="dashicons dashicons-paperclip" style="display:none;"></span>
                            <?php esc_html_e('Send', 'premium-classifieds'); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.pc-inbox-container {
    display: grid;
    grid-template-columns: 350px 1fr;
    gap: 0;
    height: 600px;
    border: 1px solid #ddd;
    border-radius: 8px;
    overflow: hidden;
    background: #fff;
}

.pc-inbox-sidebar {
    border-right: 1px solid #ddd;
    display: flex;
    flex-direction: column;
    background: #f9f9f9;
}

.pc-inbox-header {
    padding: 20px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #fff;
}

.pc-inbox-header h3 {
    margin: 0;
    font-size: 18px;
}

.pc-inbox-threads {
    flex: 1;
    overflow-y: auto;
}

.pc-inbox-thread {
    display: flex;
    gap: 12px;
    padding: 16px 20px;
    cursor: pointer;
    transition: background-color 0.2s;
    border-bottom: 1px solid #eee;
    background: #fff;
}

.pc-inbox-thread:hover {
    background: #f5f5f5;
}

.pc-inbox-thread.active {
    background: #e3f2fd;
    border-left: 3px solid #2196F3;
}

.pc-inbox-thread.pc-thread-unread {
    background: #fff9e6;
    font-weight: 600;
}

.pc-thread-avatar {
    position: relative;
    flex-shrink: 0;
}

.pc-thread-avatar img {
    border-radius: 50%;
}

.pc-unread-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #f44336;
    color: #fff;
    font-size: 11px;
    font-weight: 600;
    padding: 2px 6px;
    border-radius: 10px;
    min-width: 18px;
    text-align: center;
}

.pc-thread-content {
    flex: 1;
    min-width: 0;
}

.pc-thread-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 4px;
}

.pc-thread-header strong {
    font-size: 14px;
}

.pc-thread-time {
    font-size: 12px;
    color: #666;
}

.pc-thread-listing {
    font-size: 12px;
    color: #2196F3;
    margin-bottom: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pc-thread-preview {
    font-size: 13px;
    color: #666;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pc-inbox-main {
    display: flex;
    flex-direction: column;
    background: #fff;
}

#pc-inbox-conversation {
    display: flex;
    flex-direction: column;
    height: 100%;
}

.pc-conversation-header {
    padding: 20px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #fff;
}

.pc-conversation-info {
    display: flex;
    gap: 12px;
    align-items: center;
}

.pc-conversation-info img {
    border-radius: 50%;
}

.pc-conversation-listing {
    font-size: 12px;
    color: #666;
}

.pc-conversation-actions {
    display: flex;
    gap: 8px;
}

.pc-messages-container {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
    background: #f9f9f9;
}

.pc-message {
    display: flex;
    gap: 12px;
    margin-bottom: 16px;
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.pc-message-avatar img {
    border-radius: 50%;
}

.pc-message-content {
    flex: 1;
    max-width: 70%;
}

.pc-message-sent {
    flex-direction: row-reverse;
}

.pc-message-sent .pc-message-content {
    text-align: right;
}

.pc-message-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 4px;
}

.pc-message-sent .pc-message-header {
    flex-direction: row-reverse;
}

.pc-message-time {
    font-size: 11px;
    color: #999;
}

.pc-message-body {
    background: #fff;
    padding: 12px 16px;
    border-radius: 12px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.1);
    word-wrap: break-word;
}

.pc-message-sent .pc-message-body {
    background: #2196F3;
    color: #fff;
}

.pc-send-message-form {
    padding: 20px;
    border-top: 1px solid #ddd;
    background: #fff;
}

.pc-message-input-wrapper {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.pc-message-input {
    width: 100%;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 8px;
    resize: vertical;
    min-height: 60px;
    font-family: inherit;
}

.pc-message-input:focus {
    outline: none;
    border-color: #2196F3;
}

.pc-message-input-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.pc-char-count {
    font-size: 12px;
    color: #666;
}

.pc-btn-icon {
    background: none;
    border: none;
    cursor: pointer;
    padding: 8px;
    border-radius: 4px;
    color: #666;
    transition: all 0.2s;
}

.pc-btn-icon:hover {
    background: #f5f5f5;
    color: #2196F3;
}

.pc-btn-icon .dashicons {
    width: 20px;
    height: 20px;
    font-size: 20px;
}

@media (max-width: 768px) {
    .pc-inbox-container {
        grid-template-columns: 1fr;
        height: auto;
    }
    
    .pc-inbox-sidebar {
        border-right: none;
        border-bottom: 1px solid #ddd;
    }
    
    .pc-message-content {
        max-width: 85%;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    const Inbox = {
        currentThread: null,
        pollInterval: null,
        pollDelay: 30000, // 30 seconds
        
        init: function() {
            this.bindEvents();
            this.startPolling();
            this.updateUnreadCount();
        },
        
        bindEvents: function() {
            // Thread selection
            $(document).on('click', '.pc-inbox-thread', (e) => {
                const $thread = $(e.currentTarget);
                const listingId = $thread.data('listing-id');
                const userId = $thread.data('user-id');
                
                this.loadConversation(listingId, userId);
            });
            
            // Send message
            $('#pc-send-message-form').on('submit', (e) => {
                e.preventDefault();
                this.sendMessage();
            });
            
            // Close conversation
            $('#pc-close-conversation').on('click', () => {
                this.closeConversation();
            });
            
            // Refresh threads
            $('#pc-refresh-threads').on('click', () => {
                this.loadThreads();
            });
            
            // Refresh conversation
            $('#pc-refresh-conversation').on('click', () => {
                if (this.currentThread) {
                    this.loadConversation(
                        this.currentThread.listingId,
                        this.currentThread.userId,
                        true
                    );
                }
            });
            
            // Character counter
            $('#pc-message-content').on('input', function() {
                const count = $(this).val().length;
                $('#pc-char-current').text(count);
                
                if (count > 1900) {
                    $('#pc-char-current').css('color', '#f44336');
                } else {
                    $('#pc-char-current').css('color', '#666');
                }
            });
        },
        
        loadThreads: function() {
            const $btn = $('#pc-refresh-threads');
            $btn.prop('disabled', true).find('.dashicons').addClass('pc-spin');
            
            $.ajax({
                url: pcData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pc_load_threads',
                    nonce: pcData.nonce
                },
                success: (response) => {
                    if (response.success) {
                        $('#pc-inbox-threads').html(response.data.html);
                        this.updateUnreadCount();
                    }
                },
                complete: () => {
                    $btn.prop('disabled', false).find('.dashicons').removeClass('pc-spin');
                }
            });
        },
        
        loadConversation: function(listingId, userId, silent = false) {
            $('.pc-inbox-thread').removeClass('active');
            $(`.pc-inbox-thread[data-listing-id="${listingId}"][data-user-id="${userId}"]`).addClass('active');
            
            if (!silent) {
                $('#pc-messages-container').html('<div class="pc-loading"><div class="pc-spinner"></div></div>');
            }
            
            $.ajax({
                url: pcData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pc_load_messages',
                    nonce: pcData.nonce,
                    listing_id: listingId,
                    other_user_id: userId,
                    silent: silent
                },
                success: (response) => {
                    if (response.success) {
                        if (!silent) {
                            this.showConversation(listingId, userId);
                            $('#pc-messages-container').html(response.data.html);
                        } else {
                            // Silent update - only add new messages
                            const currentCount = $('.pc-message').length;
                            if (response.data.count > currentCount) {
                                $('#pc-messages-container').html(response.data.html);
                            }
                        }
                        
                        this.scrollToBottom();
                        this.currentThread = { listingId, userId };
                        this.updateUnreadCount();
                    }
                },
                error: (xhr) => {
                    if (!silent) {
                        pcUtils.showNotice('Failed to load conversation', 'error');
                    }
                }
            });
        },
        
        showConversation: function(listingId, userId) {
            const $thread = $(`.pc-inbox-thread[data-listing-id="${listingId}"][data-user-id="${userId}"]`);
            const name = $thread.find('strong').text();
            const listing = $thread.find('.pc-thread-listing').text();
            const avatar = $thread.find('.pc-thread-avatar img').clone();
            
            $('#pc-conversation-avatar').html(avatar);
            $('#pc-conversation-name').text(name);
            $('#pc-conversation-listing').text(listing);
            
            $('#pc-message-listing-id').val(listingId);
            $('#pc-message-recipient-id').val(userId);
            
            $('#pc-inbox-empty').hide();
            $('#pc-inbox-conversation').show();
            
            // Remove unread badge
            $thread.removeClass('pc-thread-unread');
            $thread.find('.pc-unread-badge').remove();
        },
        
        closeConversation: function() {
            $('#pc-inbox-conversation').hide();
            $('#pc-inbox-empty').show();
            $('.pc-inbox-thread').removeClass('active');
            this.currentThread = null;
            $('#pc-message-content').val('');
            $('#pc-char-current').text('0');
        },
        
        sendMessage: function() {
            const $form = $('#pc-send-message-form');
            const $btn = $('#pc-send-message-btn');
            const content = $('#pc-message-content').val().trim();
            
            if (!content) return;
            
            $btn.prop('disabled', true).text('<?php esc_html_e('Sending...', 'premium-classifieds'); ?>');
            
            $.ajax({
                url: pcData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pc_send_message',
                    nonce: pcData.nonce,
                    listing_id: $('#pc-message-listing-id').val(),
                    recipient_id: $('#pc-message-recipient-id').val(),
                    content: content
                },
                success: (response) => {
                    if (response.success) {
                        $('#pc-messages-container').append(response.data.html);
                        $('#pc-message-content').val('');
                        $('#pc-char-current').text('0');
                        this.scrollToBottom();
                        this.loadThreads(); // Refresh sidebar
                        
                        if (response.data.notice) {
                            pcUtils.showNotice(response.data.notice, 'success');
                        }
                    } else {
                        pcUtils.showNotice(response.data || 'Failed to send message', 'error');
                    }
                },
                error: (xhr) => {
                    const error = xhr.responseJSON?.data || 'Failed to send message';
                    pcUtils.showNotice(error, 'error');
                },
                complete: () => {
                    $btn.prop('disabled', false).text('<?php esc_html_e('Send', 'premium-classifieds'); ?>');
                }
            });
        },
        
        scrollToBottom: function() {
            const $container = $('#pc-messages-container');
            $container.animate({
                scrollTop: $container[0].scrollHeight
            }, 300);
        },
        
        startPolling: function() {
            if (this.pollInterval) {
                clearInterval(this.pollInterval);
            }
            
            this.pollInterval = setInterval(() => {
                // Update threads list
                this.loadThreads();
                
                // Update current conversation if open
                if (this.currentThread) {
                    this.loadConversation(
                        this.currentThread.listingId,
                        this.currentThread.userId,
                        true // Silent reload
                    );
                }
            }, this.pollDelay);
        },
        
        updateUnreadCount: function() {
            $.ajax({
                url: pcData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pc_get_unread_count',
                    nonce: pcData.nonce
                },
                success: (response) => {
                    if (response.success && response.data.count > 0) {
                        const $badge = $('.pc-tab-link[data-tab="inbox"] .pc-tab-badge');
                        $badge.text(response.data.count).show();
                    }
                }
            });
        }
    };
    
    Inbox.init();
});
</script>