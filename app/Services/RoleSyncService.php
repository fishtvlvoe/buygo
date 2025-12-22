<?php

namespace BuyGo\Core\Services;

class RoleSyncService {

    public function __construct() {
        // Listen to role changes
        add_action('set_user_role', [$this, 'sync_on_role_change'], 10, 3);
        add_action('add_user_role', [$this, 'sync_on_role_add'], 10, 2);
        add_action('remove_user_role', [$this, 'sync_on_role_remove'], 10, 2);
    }

    /**
     * Sync BuyGo role to FluentCart and FluentCommunity
     */
    public function sync_role_to_integrations($user_id, $buygo_role) {
        // Get role mappings from settings
        $fluentcart_mapping = get_option('buygo_fluentcart_role_mapping', []);
        $fluentcommunity_mapping = get_option('buygo_fluentcommunity_role_mapping', []);

        // Sync to FluentCart (can have multiple roles)
        if (isset($fluentcart_mapping[$buygo_role]) && is_array($fluentcart_mapping[$buygo_role])) {
            $fluentcart_roles = $fluentcart_mapping[$buygo_role];
            // Use the first role as primary (FluentCart only supports one role per user)
            if (!empty($fluentcart_roles)) {
                $this->sync_to_fluentcart($user_id, $fluentcart_roles[0]);
            }
        }

        // Sync to FluentCommunity (can have multiple roles)
        if (isset($fluentcommunity_mapping[$buygo_role]) && is_array($fluentcommunity_mapping[$buygo_role])) {
            $fluentcommunity_roles = $fluentcommunity_mapping[$buygo_role];
            // Apply all selected roles
            foreach ($fluentcommunity_roles as $fc_role) {
                $this->sync_to_fluentcommunity($user_id, $fc_role);
            }
        }
    }

    /**
     * Sync to FluentCart
     */
    private function sync_to_fluentcart($user_id, $fluentcart_role) {
        if (!class_exists('\FluentCart\App\Models\User')) {
            return;
        }

        try {
            $user = \FluentCart\App\Models\User::find($user_id);
            if ($user) {
                $user->setStoreRole($fluentcart_role);
            }
        } catch (\Exception $e) {
            error_log('[BuyGo] FluentCart role sync failed: ' . $e->getMessage());
        }
    }

    /**
     * Sync to FluentCommunity
     */
    private function sync_to_fluentcommunity($user_id, $fluentcommunity_role) {
        if (!class_exists('\FluentCommunity\App\Services\Helper')) {
            return;
        }

        try {
            // FluentCommunity uses space-based roles
            // We need to get all spaces and set the role for each
            global $wpdb;
            $table_name = $wpdb->prefix . 'fcom_spaces';
            
            if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name) {
                $spaces = $wpdb->get_results(
                    "SELECT id FROM {$table_name} WHERE type = 'community' AND status = 'published'"
                );
                
                foreach ($spaces as $space) {
                    // Use FluentCommunity Helper API
                    \FluentCommunity\App\Services\Helper::addToSpace($space->id, $user_id, $fluentcommunity_role, 'by_admin');
                }
            }
        } catch (\Exception $e) {
            error_log('[BuyGo] FluentCommunity role sync failed: ' . $e->getMessage());
        }
    }

    /**
     * Handle WordPress role change
     */
    public function sync_on_role_change($user_id, $role, $old_roles) {
        // Check if it's a BuyGo role
        $buygo_roles = ['buygo_admin', 'buygo_seller', 'buygo_helper'];
        if (in_array($role, $buygo_roles)) {
            $this->sync_role_to_integrations($user_id, $role);
        }
    }

    /**
     * Handle role addition
     */
    public function sync_on_role_add($user_id, $role) {
        $buygo_roles = ['buygo_admin', 'buygo_seller', 'buygo_helper'];
        if (in_array($role, $buygo_roles)) {
            $this->sync_role_to_integrations($user_id, $role);
        }
    }

    /**
     * Handle role removal
     */
    public function sync_on_role_remove($user_id, $role) {
        $buygo_roles = ['buygo_admin', 'buygo_seller', 'buygo_helper'];
        if (in_array($role, $buygo_roles)) {
            // Remove from FluentCart
            if (class_exists('\FluentCart\App\Models\User')) {
                try {
                    $user = \FluentCart\App\Models\User::find($user_id);
                    if ($user) {
                        delete_user_meta($user_id, '_fluent_cart_admin_role');
                    }
                } catch (\Exception $e) {
                    error_log('[BuyGo] FluentCart role removal failed: ' . $e->getMessage());
                }
            }
        }
    }
}
