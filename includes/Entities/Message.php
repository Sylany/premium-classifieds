<?php

namespace PremiumClassifieds\Entities;

/**
 * Class Message
 *
 * Represents a private message exchanged between users.
 * Table: wp_pc_messages
 *
 * Fields:
 *  - id
 *  - sender_id
 *  - receiver_id
 *  - listing_id (optional)
 *  - content
 *  - status: unread | read
 *  - created_at
 *  - meta (json)
 */
class Message
{
    public int $id;
    public int $sender_id;
    public int $receiver_id;
    public ?int $listing_id;
    public string $content;
    public string $status;
    public string $created_at;
    public array $meta = [];

    public function __construct(array $data = [])
    {
        $this->id          = (int)($data['id'] ?? 0);
        $this->sender_id   = (int)($data['sender_id'] ?? 0);
        $this->receiver_id = (int)($data['receiver_id'] ?? 0);
        $this->listing_id  = isset($data['listing_id']) ? (int)$data['listing_id'] : null;

        $this->content     = wp_kses_post($data['content'] ?? '');
        $this->status      = $data['status'] ?? 'unread';
        $this->created_at  = $data['created_at'] ?? current_time('mysql');

        $this->meta = is_array($data['meta'] ?? null)
            ? $data['meta']
            : json_decode($data['meta'] ?? '[]', true);
    }

    /**
     * Save or update the message
     */
    public function save(): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pc_messages';

        $data = [
            'sender_id'   => $this->sender_id,
            'receiver_id' => $this->receiver_id,
            'listing_id'  => $this->listing_id,
            'content'     => $this->content,
            'status'      => $this->status,
            'created_at'  => $this->created_at,
            'meta'        => wp_json_encode($this->meta),
        ];

        if ($this->id > 0) {
            $wpdb->update($table, $data, ['id' => $this->id]);
            return $this->id;
        }

        $wpdb->insert($table, $data);
        $this->id = (int)$wpdb->insert_id;

        return $this->id;
    }

    /**
     * Get message by ID
     */
    public static function get(int $id): ?self
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pc_messages';

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE id = %d LIMIT 1", $id),
            ARRAY_A
        );

        return $row ? new self($row) : null;
    }

    /**
     * Get entire conversation between 2 users
     */
    public static function get_conversation(int $user1, int $user2, int $limit = 200): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pc_messages';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table
                 WHERE (sender_id = %d AND receiver_id = %d)
                    OR (sender_id = %d AND receiver_id = %d)
                 ORDER BY created_at ASC
                 LIMIT %d",
                $user1, $user2, $user2, $user1, $limit
            ),
            ARRAY_A
        );

        return array_map(fn($row) => new self($row), $rows);
    }

    /**
     * Inbox: list of messages received by user
     */
    public static function inbox(int $user_id, int $limit = 50): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pc_messages';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table
                 WHERE receiver_id = %d
                 ORDER BY created_at DESC
                 LIMIT %d",
                $user_id, $limit
            ),
            ARRAY_A
        );

        return array_map(fn($row) => new self($row), $rows);
    }

    /**
     * Mark message as read
     */
    public function mark_read(): bool
    {
        $this->status = 'read';
        return $this->save() > 0;
    }

    /**
     * Delete a message
     */
    public static function delete(int $id): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pc_messages';

        return (bool)$wpdb->delete($table, ['id' => $id]);
    }
}
