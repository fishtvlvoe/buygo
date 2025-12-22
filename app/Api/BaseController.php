<?php

namespace BuyGo\Core\Api;

class BaseController {

    protected $namespace = 'buygo/v1';

    public function register_routes() {
        // To be implemented by child controllers
    }

    public function check_permission() {
        // WP Administrator has full access
        if (current_user_can('manage_options')) {
            return true;
        }
        
        // BuyGo Admin can access BuyGo plugin features
        $user = wp_get_current_user();
        if ($user && in_array('buygo_admin', (array) $user->roles)) {
            return true;
        }
        
        return false;
    }
}
