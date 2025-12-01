<?php

namespace PremiumClassifieds\Core;

/**
 * Class Updater
 *
 * Handles database versioning and incremental migrations.
 */
class Updater
{
    /**
     * Option key in wp_options for DB version.
     */
    const OPTION_KEY = 'pc_db_version';

    /**
     * Current required database version.
     */
    const CURRENT_VERSION = '1.0.0';

    /**
     * Run updater on plugin load.
     */
    public static function init(): void
    {
        $installed_version = get_option(self::OPTION_KEY, null);

        if ($installed_version === null) {
            // First install, installer handles initial setup
            update_option(self::OPTION_KEY, self::CURRENT_VERSION);
            return;
        }

        if (version_compare($installed_version, self::CURRENT_VERSION, '<')) {
            self::run_migrations($installed_version);
            update_option(self::OPTION_KEY, self::CURRENT_VERSION);
        }
    }

    /**
     * Run migrations from old version → to new version.
     *
     * @param string $from_version
     */
    private static function run_migrations(string $from_version): void
    {
        global $wpdb;

        // Example migrations — expand as project grows
        if (version_compare($from_version, '1.0.0', '<')) {
            self::migration_100();
        }

        // Future example:
        // if (version_compare($from_version, '1.1.0', '<')) {
        //     self::migration_110();
        // }
    }

    /**
     * Migration to v1.0.0
     * Adds missing columns, indexes or adjusts schema safely.
     */
    private static function migration_100(): void
    {
        global $wpdb;

        /**
         * Example: ensure "favorite" table has proper index
         */
        $table = $wpdb->prefix . 'pc_favorites';
        $index_name = "{$wpdb->prefix}pc_fav_user_post_idx";

        $has_index = $wpdb->get_var(
            $wpdb->prepare(
                "SHOW INDEX FROM `$table` WHERE Key_name = %s",
                $index_name
            )
        );

        if (!$has_index) {
            $wpdb->query("ALTER TABLE `$table` ADD INDEX `$index_name` (`user_id`, `listing_id`)");
        }

        /**
         * Example: ensure messages table has "is_read"
         */
        $messages_table = $wpdb->prefix . 'pc_messages';
        $has_column = $wpdb->get_var(
            "SHOW COLUMNS FROM `$messages_table` LIKE 'is_read'"
        );

        if (!$has_column) {
            $wpdb->query(
                "ALTER TABLE `$messages_table`
                    ADD `is_read` TINYINT(1) NOT NULL DEFAULT 0 AFTER `body`"
            );
        }
    }
}
