<?php

namespace PremiumClassifieds\Entities;

/**
 * Class Transaction
 *
 * Represents a single payment transaction.
 * Stored in DB table: wp_pc_transactions
 */
class Transaction
{
    public int $id;
    public int $user_id;
    public ?int $listing_id;
    public string $provider;        // stripe | paypal
    public string $type;            // contact_unlock | highlight | subscription | renewal | boost
    public float $amount;
    public string $currency;
    public string $status;          // pending | completed | failed | refunded
    public string $created_at;
    public array $meta = [];

    /**
     * Constructor
     */
    public function __construct(array $data = [])
    {
        $this->id          = (int)($data['id'] ?? 0);
        $this->user_id     = (int)($data['user_id'] ?? 0);
        $this->listing_id  = isset($data['listing_id']) ? (int)$data['listing_id'] : null;
        $this->provider    = $data['provider'] ?? '';
        $this->type        = $data['type'] ?? '';
        $this->amount      = isset($data['amount']) ? (float)$data['amount'] : 0.0;
        $this->currency    = $data['currency'] ?? 'PLN';
        $this->status      = $data['status'] ?? 'pending';
        $this->created_at  = $data['created_at'] ?? current_time('mysql');
        $this->meta        = is_array($data['meta'] ?? null)
            ? $data['meta']
            : json_decode($data['meta'] ?? '[]', true);
    }

    /**
     * Save to database
     */
    public function save(): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pc_transactions';

        $data = [
            'user_id'     => $this->user_id,
            'listing_id'  => $this->listing_id,
            'provider'    => $this->provider,
            'type'        => $this->type,
            'amount'      => $this->amount,
            'currency'    => $this->currency,
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
     * Get transaction by ID
     */
    public static function get(int $id): ?Transaction
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pc_transactions';

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE id = %d LIMIT 1", $id),
            ARRAY_A
        );

        return $row ? new self($row) : null;
    }

    /**
     * Get all transactions for user
     */
    public static function get_by_user(int $user_id, int $limit = 50): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pc_transactions';

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
                $user_id,
                $limit
            ),
            ARRAY_A
        );

        return array_map(fn($row) => new self($row), $results);
    }

    /**
     * Delete transaction
     */
    public static function delete(int $id): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pc_transactions';

        return (bool)$wpdb->delete($table, ['id' => $id]);
    }

    /**
     * Mark as completed
     */
    public function complete(): bool
    {
        $this->status = 'completed';
        return $this->save() > 0;
    }

    /**
     * Mark as failed
     */
    public function fail(): bool
    {
        $this->status = 'failed';
        return $this->save() > 0;
    }
}
