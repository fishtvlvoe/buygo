<?php

namespace BuyGo\Core\Services;

class RoleManager {

    public function __construct() {
        add_action('init', [$this, 'register_roles']);
    }

    public function register_roles() {
        // Register Buyer Role (default role for new LINE users in Plus One flow)
        if (!get_role('buygo_buyer')) {
            add_role(
                'buygo_buyer',
                __('BuyGo Buyer', 'buygo-role-permission'),
                [
                    'read' => true,
                ]
            );
        }

        // Register BuyGo Admin Role (can manage BuyGo plugin features only)
        if (!get_role('buygo_admin')) {
            add_role(
                'buygo_admin',
                __('BuyGo Admin', 'buygo-role-permission'),
                [
                    'read' => true,
                    'manage_buygo_shop' => true,
                    'manage_buygo_settings' => true, // Custom capability for BuyGo settings
                ]
            );
        }

        // Register Seller Role
        if (!get_role('buygo_seller')) {
            add_role(
                'buygo_seller',
                __('BuyGo Seller', 'buygo-role-permission'),
                [
                    'read' => true,
                    'upload_files' => true,
                    // Minimal permissions, mostly Custom Capabilities
                    'manage_buygo_shop' => true, 
                ]
            );
        }

        // Register Helper Role
        if (!get_role('buygo_helper')) {
            add_role(
                'buygo_helper',
                __('BuyGo Helper', 'buygo-role-permission'),
                [
                    'read' => true,
                    'manage_buygo_shop' => true, // Helper also needs to access shop features
                ]
            );
        }
    }

    /**
     * Check if a user has a specific role.
     * 
     * @param int $user_id
     * @param string $role
     * @return bool
     */
    public function user_has_role($user_id, $role) {
        $user = get_userdata($user_id);
        if (!$user) return false;
        return in_array($role, (array) $user->roles);
    }

    public function is_seller($user_id) {
        return $this->user_has_role($user_id, 'buygo_seller');
    }

    public function is_helper($user_id) {
        return $this->user_has_role($user_id, 'buygo_helper');
    }

    public function is_admin($user_id) {
        return $this->user_has_role($user_id, 'buygo_admin');
    }

    /**
     * Set user role (replaces existing roles)
     */
    public function set_user_role($user_id, $role) {
        $user = get_userdata($user_id);
        if (!$user) return false;
        
        $old_roles = $user->roles;
        
        // Remove all roles
        $user->set_role($role);
        
        // Trigger role sync to FluentCart and FluentCommunity
        $role_sync = App::instance()->make(Services\RoleSyncService::class);
        if ($role_sync) {
            $role_sync->sync_role_to_integrations($user_id, $role);
        }
        
        // Trigger hook for other plugins
        do_action('buygo_role_changed', $user_id, $old_roles, [$role]);
        
        return true;
    }

    /**
     * Add role to user
     */
    public function add_user_role($user_id, $role) {
        $user = get_userdata($user_id);
        if (!$user) return false;
        $user->add_role($role);
        return true;
    }

    /**
     * Remove role from user
     */
    public function remove_user_role($user_id, $role) {
        $user = get_userdata($user_id);
        if (!$user) return false;
        $user->remove_role($role);
        return true;
    }
    /**
     * Get all users with a specific role.
     * 
     * @param string $role
     * @return array List of WP_User objects
     */
    public function get_users_by_role($role) {
        $args = [
            'role'    => $role,
            'orderby' => 'user_nicename',
            'order'   => 'ASC'
        ];
        return get_users($args);
    }

    /**
     * Validate if user has specific permission.
     * 
     * @param int $user_id
     * @param string $permission
     * @return bool
     */
    public function validate_role_permission($user_id, $permission) {
        return user_can($user_id, $permission);
    }
}
