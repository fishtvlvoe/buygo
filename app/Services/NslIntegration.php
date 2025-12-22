<?php

namespace BuyGo\Core\Services;

use BuyGo\Core\App;

/**
 * Handles integration with Nextend Social Login (NSL) plugin.
 */
class NslIntegration {

    public function __construct() {
        // Hook into NSL's link user event for LINE provider
        add_action('nsl_line_link_user', [$this, 'handle_line_link'], 10, 2);
    }

    /**
     * Handle the event when a user links their LINE account via NSL.
     *
     * @param int $user_id The WordPress User ID.
     * @param string $provider_id The Provider ID (should be 'line').
     */
    public function handle_line_link($user_id, $provider_id) {
        if ($provider_id !== 'line') {
            return;
        }

        // We need to fetch the LINE UID.
        // NSL stores this in wp_social_users table, but helper method exists.
        // Or we can get it from the provider instance if available?
        // Let's use NSL's internal API if possible, or query their table.
        // However, the action hook args are just ($user_id, $provider_id).
        
        $line_uid = $this->get_nsl_line_uid($user_id);

        if ($line_uid) {
            /** @var LineService */
            $line_service = App::instance()->make(LineService::class);
            $line_service->manual_bind($user_id, $line_uid);
        }
    }

    /**
     * Retrieve the LINE UID for a user from NSL's storage.
     * 
     * @param int $user_id
     * @return string|false
     */
    private function get_nsl_line_uid($user_id) {
        global $wpdb;
        // NSL table: wp_social_users
        // Columns: ID (user_id), type (provider_id), identifier (uid)
        
        $table_name = $wpdb->prefix . 'social_users';
        
        // Ensure table exists to avoid errors if NSL is not active/installed properly
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return false;
        }

        $uid = $wpdb->get_var($wpdb->prepare(
            "SELECT identifier FROM $table_name WHERE ID = %d AND type = 'line'",
            $user_id
        ));

        return $uid;
    }
}
