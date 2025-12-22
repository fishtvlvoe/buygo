<?php

namespace BuyGo\Core\Api;

use BuyGo\Core\App;
use WP_REST_Request;
use WP_REST_Response;
use WP_User_Query;

class UserSearchController extends BaseController {

    public function register_routes() {
        register_rest_route($this->namespace, '/users/search', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'search_users'],
                'permission_callback' => [$this, 'check_search_permission'],
            ]
        ]);
    }

    /**
     * Check permission for searching users.
     * Allowed for: Admins, Sellers, Helpers (basically any BuyGo authorized role)
     */
    public function check_search_permission() {
        if (!is_user_logged_in()) {
            return false;
        }

        $user = wp_get_current_user();
        $allowed_roles = ['administrator', 'buygo_seller', 'buygo_helper'];
        
        foreach ($allowed_roles as $role) {
            if (in_array($role, (array) $user->roles)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Search Users Endpoint
     */
    public function search_users(WP_REST_Request $request) {
        $query = $request->get_param('q');
        $role = $request->get_param('role');
        $limit = max(1, min(50, intval($request->get_param('limit') ?: 10)));
        
        $args = [
            'number' => $limit,
            'orderby' => 'registered', // Default newest
            'order' => 'DESC',
            'fields' => 'all_with_meta' // Need meta for nickname
        ];

        // Role Filter
        if (!empty($role)) {
            $args['role'] = $role;
        }

        // Search Logic
        if (!empty($query)) {
            // Remove leading wildcard for performance
            $search_term = $query . '*';
            $args['search'] = $search_term;
            $args['search_columns'] = ['user_login', 'user_email', 'display_name', 'nicename'];
        } else {
             // Default list (no query): Just return latest
        }

        $user_query = new WP_User_Query($args);
        $users = $user_query->get_results();
        
        $results = [];
        foreach ($users as $user) {
            $results[] = [
                'id' => $user->ID,
                'display_name' => $user->display_name,
                'email' => $user->user_email,
                'avatar_url' => get_avatar_url($user->ID),
                'login' => $user->user_login
            ];
        }

        return new WP_REST_Response($results, 200);
    }
}
