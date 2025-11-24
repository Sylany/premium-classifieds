<?php
/**
 * Roles and Capabilities Management
 *
 * Manages custom user roles and capabilities for the listing system.
 * Ensures proper permission separation between regular users, listing
 * creators, and administrators.
 *
 * @package    PremiumClassifieds
 * @subpackage Core
 * @since      2.1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Roles and Capabilities Class
 *
 * @since 2.1.0
 */
class PC_Roles_Capabilities {
    
    /**
     * Custom role slug
     *
     * @var string
     */
    private const ROLE_LISTING_CREATOR = 'listing_creator';
    
    /**
     * Constructor - Register hooks
     *
     * @since 2.1.0
     */
    public function __construct() {
        // Add capabilities on plugin activation (handled by PC_Activator)
        // This class provides methods for dynamic capability management
        
        add_filter('map_meta_cap', [$this, 'map_meta_capabilities'], 10, 4);
        add_action('user_register', [$this, 'assign_default_role']);
    }
    
    /**
     * Create custom role with listing capabilities
     *
     * Called by PC_Activator during plugin activation
     *
     * @since 2.1.0
     * @return void
     */
    public static function create_roles(): void {
        // Remove role if exists (for clean reinstall)
        remove_role(self::ROLE_LISTING_CREATOR);
        
        // Create listing creator role
        add_role(
            self::ROLE_LISTING_CREATOR,
            __('Listing Creator', 'premium-classifieds'),
            [
                // WordPress core capabilities
                'read' => true,
                'upload_files' => true,
                'edit_posts' => false,
                'delete_posts' => false,
                
                // Custom listing capabilities
                'create_listings' => true,
                'edit_listings' => true,
                'edit_published_listings' => true,
                'delete_listings' => true,
                'delete_published_listings' => true,
                'publish_listings' => true,
            ]
        );
    }
    
    /**
     * Add listing capabilities to administrator role
     *
     * Called by PC_Activator during plugin activation
     *
     * @since 2.1.0
     * @return void
     */
    public static function add_admin_capabilities(): void {
        $admin_role = get_role('administrator');
        
        if (!$admin_role) {
            return;
        }
        
        $capabilities = [
            'create_listings',
            'edit_listings',
            'edit_others_listings',
            'edit_published_listings',
            'edit_private_listings',
            'delete_listings',
            'delete_others_listings',
            'delete_published_listings',
            'delete_private_listings',
            'publish_listings',
            'read_private_listings',
            'moderate_listings',
        ];
        
        foreach ($capabilities as $cap) {
            $admin_role->add_cap($cap);
        }
    }
    
    /**
     * Remove all plugin capabilities from roles
     *
     * Called by uninstall.php when plugin is deleted
     *
     * @since 2.1.0
     * @return void
     */
    public static function remove_capabilities(): void {
        global $wp_roles;
        
        if (!isset($wp_roles)) {
            $wp_roles = new WP_Roles();
        }
        
        $capabilities = [
            'create_listings',
            'edit_listings',
            'edit_others_listings',
            'edit_published_listings',
            'edit_private_listings',
            'delete_listings',
            'delete_others_listings',
            'delete_published_listings',
            'delete_private_listings',
            'publish_listings',
            'read_private_listings',
            'moderate_listings',
        ];
        
        foreach ($wp_roles->role_objects as $role) {
            foreach ($capabilities as $cap) {
                $role->remove_cap($cap);
            }
        }
        
        // Remove custom role
        remove_role(self::ROLE_LISTING_CREATOR);
    }
    
    /**
     * Map meta capabilities for listings
     *
     * Ensures users can only edit/delete their own listings unless they're admin
     *
     * @since 2.1.0
     * @param array $caps Required capabilities
     * @param string $cap Capability being checked
     * @param int $user_id User ID
     * @param array $args Additional arguments
     * @return array Modified capabilities
     */
    public function map_meta_capabilities(array $caps, string $cap, int $user_id, array $args): array {
        // Check if this is a listing capability
        if (!in_array($cap, ['edit_listing', 'delete_listing', 'read_listing'])) {
            return $caps;
        }
        
        // Get the post
        $post = get_post($args[0]);
        if (!$post || $post->post_type !== 'listing') {
            return $caps;
        }
        
        $post_type = get_post_type_object('listing');
        
        // Map to appropriate primitive capability
        switch ($cap) {
            case 'edit_listing':
                if ($user_id == $post->post_author) {
                    // Own listing
                    $caps = [$post_type->cap->edit_posts];
                } else {
                    // Someone else's listing
                    $caps = [$post_type->cap->edit_others_posts];
                }
                break;
                
            case 'delete_listing':
                if ($user_id == $post->post_author) {
                    // Own listing
                    $caps = [$post_type->cap->delete_posts];
                } else {
                    // Someone else's listing
                    $caps = [$post_type->cap->delete_others_posts];
                }
                break;
                
            case 'read_listing':
                if ('private' === $post->post_status) {
                    if ($user_id == $post->post_author) {
                        $caps = ['read'];
                    } else {
                        $caps = [$post_type->cap->read_private_posts];
                    }
                } else {
                    $caps = ['read'];
                }
                break;
        }
        
        return $caps;
    }
    
    /**
     * Assign default role to new users
     *
     * Automatically assigns listing_creator role to new registrations
     * if no other role is specified
     *
     * @since 2.1.0
     * @param int $user_id User ID
     * @return void
     */
    public function assign_default_role(int $user_id): void {
        $user = get_userdata($user_id);
        
        // Only assign if user has subscriber role (WordPress default)
        if ($user && in_array('subscriber', $user->roles)) {
            $assign_listing_role = apply_filters('pc_assign_listing_role_on_register', true, $user_id);
            
            if ($assign_listing_role) {
                $user->remove_role('subscriber');
                $user->add_role(self::ROLE_LISTING_CREATOR);
            }
        }
    }
    
    /**
     * Check if user can create listings
     *
     * @since 2.1.0
     * @param int|null $user_id User ID (defaults to current user)
     * @return bool True if user can create listings
     */
    public static function user_can_create_listings(?int $user_id = null): bool {
        if (null === $user_id) {
            $user_id = get_current_user_id();
        }
        
        return user_can($user_id, 'create_listings');
    }
    
    /**
     * Check if user can moderate listings
     *
     * @since 2.1.0
     * @param int|null $user_id User ID (defaults to current user)
     * @return bool True if user can moderate
     */
    public static function user_can_moderate(?int $user_id = null): bool {
        if (null === $user_id) {
            $user_id = get_current_user_id();
        }
        
        return user_can($user_id, 'moderate_listings');
    }
    
    /**
     * Check if user owns a listing
     *
     * @since 2.1.0
     * @param int $listing_id Listing post ID
     * @param int|null $user_id User ID (defaults to current user)
     * @return bool True if user owns the listing
     */
    public static function user_owns_listing(int $listing_id, ?int $user_id = null): bool {
        if (null === $user_id) {
            $user_id = get_current_user_id();
        }
        
        $post = get_post($listing_id);
        
        if (!$post || $post->post_type !== 'listing') {
            return false;
        }
        
        return (int) $post->post_author === $user_id;
    }
    
    /**
     * Get user's listing count
     *
     * @since 2.1.0
     * @param int|null $user_id User ID (defaults to current user)
     * @param string $status Post status (default: any)
     * @return int Number of listings
     */
    public static function get_user_listing_count(?int $user_id = null, string $status = 'any'): int {
        if (null === $user_id) {
            $user_id = get_current_user_id();
        }
        
        $args = [
            'post_type' => 'listing',
            'author' => $user_id,
            'post_status' => $status,
            'posts_per_page' => -1,
            'fields' => 'ids',
        ];
        
        $query = new WP_Query($args);
        return $query->found_posts;
    }
    
    /**
     * Get role display name
     *
     * @since 2.1.0
     * @param string $role Role slug
     * @return string Role display name
     */
    public static function get_role_display_name(string $role): string {
        $wp_roles = wp_roles();
        $role_names = $wp_roles->get_names();
        
        return $role_names[$role] ?? $role;
    }
    
    /**
     * Check if user has reached listing limit
     *
     * @since 2.1.0
     * @param int|null $user_id User ID (defaults to current user)
     * @return bool True if limit reached
     */
    public static function has_reached_listing_limit(?int $user_id = null): bool {
        if (null === $user_id) {
            $user_id = get_current_user_id();
        }
        
        // Admins have no limit
        if (user_can($user_id, 'manage_options')) {
            return false;
        }
        
        // Get user's active listing count
        $active_count = self::get_user_listing_count($user_id, 'publish');
        
        // Get limit from settings (0 = unlimited)
        $limit = (int) apply_filters('pc_user_listing_limit', 0, $user_id);
        
        if ($limit === 0) {
            return false;
        }
        
        return $active_count >= $limit;
    }
    
    /**
     * Upgrade user to listing creator
     *
     * @since 2.1.0
     * @param int $user_id User ID
     * @return bool True on success
     */
    public static function upgrade_to_listing_creator(int $user_id): bool {
        $user = get_userdata($user_id);
        
        if (!$user) {
            return false;
        }
        
        // Remove subscriber role if present
        if (in_array('subscriber', $user->roles)) {
            $user->remove_role('subscriber');
        }
        
        // Add listing creator role
        $user->add_role(self::ROLE_LISTING_CREATOR);
        
        return true;
    }
    
    /**
     * Get all users with listing creator role
     *
     * @since 2.1.0
     * @return array Array of WP_User objects
     */
    public static function get_listing_creators(): array {
        return get_users([
            'role' => self::ROLE_LISTING_CREATOR,
            'orderby' => 'registered',
            'order' => 'DESC',
        ]);
    }
    
    /**
     * Get role slug
     *
     * @since 2.1.0
     * @return string Role slug
     */
    public static function get_role_slug(): string {
        return self::ROLE_LISTING_CREATOR;
    }
}
