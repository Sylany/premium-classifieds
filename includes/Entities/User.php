<?php

namespace PremiumClassifieds\Entities;

use WP_User;

/**
 * Class User
 *
 * Represents a PC platform user with extended metadata:
 * - listing limits
 * - purchased contacts (Pay-to-Contact)
 * - subscriptions
 * - verification status
 * - inbox settings
 */
class User
{
    public int $id;
    public string $email;
    public string $display_name;
    public array $meta = [];

    /**
     * User constructor
     */
    public function __construct(int $user_id)
    {
        $wp_user = get_user_by('id', $user_id);

        if (!$wp_user instanceof WP_User) {
            throw new \Exception("User not found: $user_id");
        }

        $this->id = $user_id;
        $this->email = sanitize_email($wp_user->user_email);
        $this->display_name = sanitize_text_field($wp_user->display_name);
        $this->meta = $this->load_meta();
    }

    /**
     * Load all user meta under namespace pc_*
     */
    private function load_meta(): array
    {
        $keys = [
            'pc_subscription',          // active subscription plan
            'pc_subscription_expires',  // expiration timestamp
            'pc_verified',              // boolean
            'pc_listings_used',         // number of created listings
            'pc_listings_limit',        // allowed number of listings
            'pc_photos_limit',          // max photos per listing
            'pc_featured_limit',        // monthly featured
            'pc_boost_limit',           // monthly boost
            'pc_purchased_contacts',    // pay-per-contact unlocks
        ];

        $meta = [];

        foreach ($keys as $key) {
            $meta[$key] = get_user_meta($this->id, $key, true);
        }

        // Normalize empty values
        if (empty($meta['pc_purchased_contacts'])) {
            $meta['pc_purchased_contacts'] = [];
        } elseif (is_string($meta['pc_purchased_contacts'])) {
            $meta['pc_purchased_contacts'] = json_decode($meta['pc_purchased_contacts'], true) ?: [];
        }

        return $meta;
    }

    /**
     * Save user meta array back to database
     */
    private function save_meta(): void
    {
        foreach ($this->meta as $key => $value) {
            if (is_array($value)) {
                $value = wp_json_encode($value);
            }
            update_user_meta($this->id, $key, $value);
        }
    }

    /**
     * Check if the user has access to contact data for given listing/owner
     */
    public function has_unlocked_contact(int $target_user_id): bool
    {
        $list = $this->meta['pc_purchased_contacts'] ?? [];
        return in_array($target_user_id, $list, true);
    }

    /**
     * Grant Pay-to-Contact access
     */
    public function unlock_contact(int $target_user_id): void
    {
        $list = $this->meta['pc_purchased_contacts'] ?? [];

        if (!in_array($target_user_id, $list, true)) {
            $list[] = $target_user_id;
        }

        $this->meta['pc_purchased_contacts'] = $list;
        $this->save_meta();
    }

    /**
     * Set subscription (plan ID: free, premium_monthly, premium_yearly, pro)
     */
    public function set_subscription(string $plan_id, string $expires_timestamp): void
    {
        $this->meta['pc_subscription'] = sanitize_text_field($plan_id);
        $this->meta['pc_subscription_expires'] = $expires_timestamp;

        // Set limits according to plan
        $this->apply_plan_limits($plan_id);

        $this->save_meta();
    }

    /**
     * Apply limits based on subscription plan
     */
    private function apply_plan_limits(string $plan): void
    {
        switch ($plan) {
            case 'pro':
                $this->meta['pc_listings_limit'] = -1;
                $this->meta['pc_photos_limit'] = 20;
                $this->meta['pc_featured_limit'] = -1;
                $this->meta['pc_boost_limit'] = -1;
                break;

            case 'premium_yearly':
                $this->meta['pc_listings_limit'] = 50;
                $this->meta['pc_photos_limit'] = 10;
                $this->meta['pc_featured_limit'] = 5;
                $this->meta['pc_boost_limit'] = 10;
                break;

            case 'premium_monthly':
                $this->meta['pc_listings_limit'] = 50;
                $this->meta['pc_photos_limit'] = 10;
                $this->meta['pc_featured_limit'] = 3;
                $this->meta['pc_boost_limit'] = 5;
                break;

            default: // free
                $this->meta['pc_listings_limit'] = 5;
                $this->meta['pc_photos_limit'] = 3;
                $this->meta['pc_featured_limit'] = 0;
                $this->meta['pc_boost_limit'] = 0;
        }
    }

    /**
     * Mark user as verified
     */
    public function verify(): void
    {
        $this->meta['pc_verified'] = 'yes';
        $this->save_meta();
    }

    /**
     * Increment the number of listings created
     */
    public function increment_listing_count(): void
    {
        $count = (int) ($this->meta['pc_listings_used'] ?? 0);
        $this->meta['pc_listings_used'] = $count + 1;
        $this->save_meta();
    }

    /**
     * Check listing limit
     */
    public function can_create_listing(): bool
    {
        $limit = (int) ($this->meta['pc_listings_limit'] ?? 0);
        $used = (int) ($this->meta['pc_listings_used'] ?? 0);

        // -1 = unlimited
        if ($limit === -1) {
            return true;
        }

        return $used < $limit;
    }

    /**
     * Static helper: load user
     */
    public static function load(int $user_id): ?User
    {
        try {
            return new self($user_id);
        } catch (\Exception $e) {
            return null;
        }
    }
}
