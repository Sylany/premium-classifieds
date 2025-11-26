<?php
/**
 * Inbox Template
 *
 * Displays message threads and conversations
 *
 * @package    PremiumClassifieds
 * @subpackage Templates/Dashboard
 * @since      2.1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$user_id = get_current_user_id();
?>

<div class="pc-section pc-inbox-section">
    <div class="pc-section-header">
        <h1 class="pc-section-title">
            <span class="dashicons dashicons-email"></span>
            <?php esc_html_e('Messages', 'premium-classifieds'); ?>
        </h1>
        <div class="pc-inbox-actions">
            <button class="pc-btn pc-btn-secondary" id="pc-mark-all-read">
                <span class="dashicons dashicons-yes-alt"></span>
                <?php esc_html_e('Mark All Read', 'premium-classifieds'); ?>
            </button>
        </div>
    </div>

    <div class="pc-inbox-container">
        
        <!-- Sidebar: Message Threads -->
        <aside class="pc-inbox-sidebar">
            <div class="pc-inbox-tabs">
                <button class="pc-inbox-tab active" data-view="threads">
                    <span class="dashicons dashicons-format-chat"></span>
                    <?php esc_html_e('Conversations', 'premium-classifieds'); ?>
                </button>
                <button class="pc-inbox-tab" data-view="inbox">
                    <span class="dashicons dashicons-email-alt"></span>
                    <?php esc_html_e('All Messages', 'premium-classifieds'); ?>
                </button>
                <button class="pc-inbox-tab" data-view="sent">
                    <span class="dashicons dashicons-upload"></span>
                    <?php esc_html_e('Sent', 'premium-classifieds'); ?>
                </button>
            </div>

            <!-- Threads List -->
            <div id="pc-threads-list" class="pc-threads-list">
                <div class="pc-loading-state">
                    <div class="pc-spinner"></div>
                    <p><?php esc_html_e('Loading messages...', 'premium-classifieds'); ?></p>
                </div>
            </div>
        </aside>

        <!-- Main: Message View -->
        <main class="pc-inbox-main">
            
            <!-- Empty State -->
            <div id="pc-inbox-empty" class="pc-inbox-empty-state">
                <div class="pc-empty-icon">
                    <span class="dashicons dashicons-email-alt"></span>
                </div>
                <h3><?php esc_html_e('No Messages Yet', 'premium-classifieds'); ?></h3>
                <p><?php esc_html_e('Select a conversation from the list or purchase contact access to a listing to start messaging.', 'premium-classifieds'); ?></p>
            </div>

            <!-- Conversation View -->
            <div id="pc-conversation-view" class="pc-conversation-view" style="display: none;">
                
                <!-- Conversation Header -->
                <div class="pc-conversation-header">
                    <div class="pc-conversation-info">
                        <h3 id="pc-conversation-title"></h3>
                        <p id="pc-conversation-meta"></p>
                    </div>
                    <div class="pc-conversation-actions">
                        <a href="#" id="pc-view-listing-link" target="_blank" class="pc-btn pc-btn-secondary">
                            <span class="dashicons dashicons-external"></span>
                            <?php esc_html_e('View Listing', 'premium-classifieds'); ?>
                        </a>
                    </div>
                </div>

                <!-- Messages Container -->
                <div id="pc-messages-container" class="pc-messages-container">
                    <!-- Messages will be loaded here via AJAX -->
                </div>

                <!-- Reply Form -->
                <div class="pc-reply-form">
                    <form id="pc-send-reply-form">
                        <input type="hidden" id="reply_listing_id" name="listing_id">
                        <input type="hidden" id="reply_to_user_id" name="to_user_id">
                        
                        <div class="pc-form-group">
                            <textarea id="reply_message" 
                                      name="message" 
                                      class="pc-textarea" 
                                      rows="3"
                                      placeholder="<?php esc_attr_e('Write your reply...', 'premium-classifieds'); ?>"
                                      required
                                      minlength="10"
                                      maxlength="5000"></textarea>
                            <div class="pc-char-counter">
                                <span id="char-count">0</span> / 5000
                            </div>
                        </div>
                        
                        <div class="pc-form-actions">
                            <button type="submit" class="pc-btn pc-btn-primary">
                                <span class="dashicons dashicons-email"></span>
                                <?php esc_html_e('Send Reply', 'premium-classifieds'); ?>
                            </button>
                        </div>
                    </form>
                </div>

            </div>

        </main>

    </div>
</div>

<style>
.pc-inbox-section {
    height: 100%;
}

.pc-inbox-container {
    display: grid;
    grid-template-columns: 350px 1fr;
    gap: 0;
    height: calc(100vh - 300px);
    min-height: 600px;
    border: 1px solid var(--pc-gray-200);
    border-radius: var(--pc-radius);
    overflow: hidden;
    background: var(--pc-white);
}

/* Sidebar */
.pc-inbox-sidebar {
    border-right: 1px solid var(--pc-gray-200);
    display: flex;
    flex-direction: column;
    background: var(--pc-gray-50);
}

.pc-inbox-tabs {
    display: flex;
    border-bottom: 1px solid var(--pc-gray-200);
    background: var(--pc-white);
}

.pc-inbox-tab {
    flex: 1;
    padding: 1rem;
    border: none;
    background: transparent;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.75rem;
    color: var(--pc-gray-600);
    transition: all 0.2s;
}

.pc-inbox-tab:hover {
    background: var(--pc-gray-50);
    color: var(--pc-primary);
}

.pc-inbox-tab.active {
    color: var(--pc-primary);
    border-bottom: 2px solid var(--pc-primary);
}

.pc-inbox-tab .dashicons {
    font-size: 20px;
    width: 20px;
    height: 20px;
}

.pc-threads-list {
    overflow-y: auto;
    flex: 1;
}

.pc-thread-item {
    padding: 1rem;
    border-bottom: 1px solid var(--pc-gray-200);
    cursor: pointer;
    transition: background 0.2s;
    background: var(--pc-white);
}

.pc-thread-item:hover {
    background: var(--pc-gray-50);
}

.pc-thread-item.active {
    background: var(--pc-primary);
    color: var(--pc-white);
}

.pc-thread-item.unread {
    background: #eff6ff;
    border-left: 3px solid var(--pc-primary);
}

.pc-thread-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.5rem;
}

.pc-thread-title {
    font-weight: 600;
    font-size: 0.875rem;
    margin: 0;
    color: inherit;
}

.pc-thread-time {
    font-size: 0.75rem;
    color: var(--pc-gray-600);
    white-space: nowrap;
}

.pc-thread-item.active .pc-thread-time {
    color: rgba(255, 255, 255, 0.8);
}

.pc-thread-preview {
    font-size: 0.875rem;
    color: var(--pc-gray-600);
    margin: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.pc-thread-item.active .pc-thread-preview {
    color: rgba(255, 255, 255, 0.9);
}

.pc-thread-meta {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.5rem;
    font-size: 0.75rem;
}

.pc-unread-badge {
    background: var(--pc-danger);
    color: white;
    padding: 0.125rem 0.5rem;
    border-radius: 9999px;
    font-weight: 600;
}

/* Main Area */
.pc-inbox-main {
    display: flex;
    flex-direction: column;
    position: relative;
}

.pc-inbox-empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    padding: 2rem;
    text-align: center;
}

.pc-conversation-view {
    display: flex;
    flex-direction: column;
    height: 100%;
}

.pc-conversation-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--pc-gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--pc-white);
}

.pc-conversation-info h3 {
    margin: 0 0 0.25rem;
    font-size: 1.125rem;
}

.pc-conversation-info p {
    margin: 0;
    font-size: 0.875rem;
    color: var(--pc-gray-600);
}

/* Messages */
.pc-messages-container {
    flex: 1;
    overflow-y: auto;
    padding: 1.5rem;
    background: var(--pc-gray-50);
}

.pc-message-item {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.pc-message-item.mine {
    flex-direction: row-reverse;
}

.pc-message-avatar {
    flex-shrink: 0;
}

.pc-message-avatar img {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    border: 2px solid var(--pc-gray-200);
}

.pc-message-content {
    max-width: 70%;
}

.pc-message-item.mine .pc-message-content {
    align-items: flex-end;
}

.pc-message-bubble {
    background: var(--pc-white);
    padding: 1rem;
    border-radius: 1rem;
    box-shadow: var(--pc-shadow-sm);
}

.pc-message-item.mine .pc-message-bubble {
    background: var(--pc-primary);
    color: var(--pc-white);
}

.pc-message-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
}

.pc-message-sender {
    font-weight: 600;
    font-size: 0.875rem;
}

.pc-message-time {
    font-size: 0.75rem;
    color: var(--pc-gray-600);
}

.pc-message-item.mine .pc-message-time {
    color: rgba(255, 255, 255, 0.8);
}

.pc-message-text {
    margin: 0;
    line-height: 1.5;
    word-wrap: break-word;
}

/* Reply Form */
.pc-reply-form {
    padding: 1.5rem;
    border-top: 1px solid var(--pc-gray-200);
    background: var(--pc-white);
}

.pc-reply-form .pc-textarea {
    resize: vertical;
    min-height: 80px;
}

.pc-char-counter {
    text-align: right;
    font-size: 0.75rem;
    color: var(--pc-gray-600);
    margin-top: 0.25rem;
}

.pc-loading-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem 1rem;
    text-align: center;
}

/* Responsive */
@media (max-width: 768px) {
    .pc-inbox-container {
        grid-template-columns: 1fr;
        height: auto;
    }
    
    .pc-inbox-sidebar {
        border-right: none;
        border-bottom: 1px solid var(--pc-gray-200);
        max-height: 300px;
    }
    
    .pc-message-content {
        max-width: 85%;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    const Inbox = {
        currentView: 'threads',
        currentThread: null,
        
        init: function() {
            this.bindEvents();
            this.loadThreads();
        },
        
        bindEvents: function() {
            // Tab switching
            $('.pc-inbox-tab').on('click', function() {
                $('.pc-inbox-tab').removeClass('active');
                $(this).addClass('active');
                Inbox.currentView = $(this).data('view');
                Inbox.loadView();
            });
            
            // Mark all as read
            $('#pc-mark-all-read').on('click', () => this.markAllRead());
            
            // Send reply
            $('#pc-send-reply-form').on('submit', (e) => this.sendReply(e));
            
            // Character counter
            $('#reply_message').on('input', function() {
                $('#char-count').text($(this).val().length);
            });
        },
        
        loadThreads: function() {
            $.ajax({
                url: pcData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pc_get_threads',
                    nonce: pcData.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.renderThreads(response.data.threads);
                    }
                }
            });
        },
        
        renderThreads: function(threads) {
            const $list = $('#pc-threads-list');
            
            if (threads.length === 0) {
                $list.html(`
                    <div class="pc-loading-state">
                        <p>${pcData.strings.noMessages || 'No conversations yet'}</p>
                    </div>
                `);
                return;
            }
            
            let html = '';
            threads.forEach(thread => {
                const unreadClass = thread.unread_count > 0 ? 'unread' : '';
                const unreadBadge = thread.unread_count > 0 
                    ? `<span class="pc-unread-badge">${thread.unread_count}</span>` 
                    : '';
                
                html += `
                    <div class="pc-thread-item ${unreadClass}" data-thread='${JSON.stringify(thread)}'>
                        <div class="pc-thread-header">
                            <h4 class="pc-thread-title">${thread.listing_title}</h4>
                            <span class="pc-thread-time">${this.timeAgo(thread.last_message_at)}</span>
                        </div>
                        <p class="pc-thread-preview">${thread.last_message_preview}</p>
                        <div class="pc-thread-meta">
                            <span>${thread.other_user_name}</span>
                            ${unreadBadge}
                        </div>
                    </div>
                `;
            });
            
            $list.html(html);
            
            // Bind click handlers
            $('.pc-thread-item').on('click', function() {
                const thread = $(this).data('thread');
                Inbox.openConversation(thread);
            });
        },
        
        openConversation: function(thread) {
            this.currentThread = thread;
            
            // Update active state
            $('.pc-thread-item').removeClass('active');
            $(event.currentTarget).addClass('active');
            
            // Show conversation view
            $('#pc-inbox-empty').hide();
            $('#pc-conversation-view').show();
            
            // Update header
            $('#pc-conversation-title').text(thread.listing_title);
            $('#pc-conversation-meta').text(`Conversation with ${thread.other_user_name}`);
            $('#pc-view-listing-link').attr('href', thread.listing_url);
            
            // Set form values
            $('#reply_listing_id').val(thread.listing_id);
            $('#reply_to_user_id').val(thread.other_user_id);
            
            // Load messages
            this.loadConversation(thread);
        },
        
        loadConversation: function(thread) {
            $.ajax({
                url: pcData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pc_load_messages',
                    nonce: pcData.nonce,
                    view: 'conversation',
                    listing_id: thread.listing_id,
                    other_user_id: thread.other_user_id
                },
                success: (response) => {
                    if (response.success) {
                        this.renderMessages(response.data.messages);
                        
                        // Update unread count in badge
                        this.updateUnreadCount(response.data.unread_count);
                    }
                }
            });
        },
        
        renderMessages: function(messages) {
            const $container = $('#pc-messages-container');
            
            if (messages.length === 0) {
                $container.html('<p style="text-align: center; color: #999;">No messages yet. Start the conversation!</p>');
                return;
            }
            
            let html = '';
            messages.forEach(msg => {
                const mineClass = msg.is_mine ? 'mine' : '';
                const avatar = msg.is_mine 
                    ? pcData.currentUserAvatar || '' 
                    : `https://www.gravatar.com/avatar/${msg.from_user_id}?s=40&d=mp`;
                
                html += `
                    <div class="pc-message-item ${mineClass}">
                        <div class="pc-message-avatar">
                            <img src="${avatar}" alt="${msg.sender_name}">
                        </div>
                        <div class="pc-message-content">
                            <div class="pc-message-bubble">
                                <div class="pc-message-header">
                                    <span class="pc-message-sender">${msg.sender_name}</span>
                                    <span class="pc-message-time">${msg.time_ago} ago</span>
                                </div>
                                <p class="pc-message-text">${msg.message}</p>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            $container.html(html);
            
            // Scroll to bottom
            $container.scrollTop($container[0].scrollHeight);
        },
        
        sendReply: function(e) {
            e.preventDefault();
            
            const $form = $(e.target);
            const $button = $form.find('button[type="submit"]');
            const $textarea = $('#reply_message');
            const originalText = $button.html();
            
            $button.prop('disabled', true).html('<span class="dashicons dashicons-update pc-spin"></span> Sending...');
            
            $.ajax({
                url: pcData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pc_send_message',
                    nonce: pcData.nonce,
                    listing_id: $('#reply_listing_id').val(),
                    message: $textarea.val()
                },
                success: (response) => {
                    if (response.success) {
                        // Clear form
                        $textarea.val('');
                        $('#char-count').text('0');
                        
                        // Reload conversation
                        this.loadConversation(this.currentThread);
                        
                        // Show toast
                        if (typeof window.showToast === 'function') {
                            window.showToast(response.data.message, 'success');
                        }
                    } else {
                        alert(response.data.message || 'Failed to send message');
                    }
                },
                error: () => {
                    alert('Network error. Please try again.');
                },
                complete: () => {
                    $button.prop('disabled', false).html(originalText);
                }
            });
        },
        
        markAllRead: function() {
            $.ajax({
                url: pcData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pc_mark_all_read',
                    nonce: pcData.nonce
                },
                success: (response) => {
                    if (response.success) {
                        $('.pc-thread-item').removeClass('unread');
                        $('.pc-unread-badge').remove();
                        this.updateUnreadCount(0);
                    }
                }
            });
        },
        
        updateUnreadCount: function(count) {
            const $badge = $('.pc-nav-item.active .pc-badge');
            if (count > 0) {
                $badge.text(count).show();
            } else {
                $badge.hide();
            }
        },
        
        timeAgo: function(dateString) {
            const now = new Date();
            const date = new Date(dateString);
            const seconds = Math.floor((now - date) / 1000);
            
            if (seconds < 60) return 'just now';
            if (seconds < 3600) return Math.floor(seconds / 60) + 'm';
            if (seconds < 86400) return Math.floor(seconds / 3600) + 'h';
            return Math.floor(seconds / 86400) + 'd';
        },
        
        loadView: function() {
            // Future: handle inbox/sent views
            console.log('Loading view:', this.currentView);
        }
    };
    
    Inbox.init();
});
</script>
