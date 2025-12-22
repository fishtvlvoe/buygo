<?php
/**
 * Global Helper Functions for BuyGo Role Permission Plugin
 * 
 * This file provides a simple API for other plugins and themes to interact 
 * with the BuyGo core functionality without needing to instantiate classes directly.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BuyGo\Core\App;
use BuyGo\Core\Services\RoleManager;
use BuyGo\Core\Services\LineService;

/**
 * Get the current user's primary BuyGo role.
 *
 * @param int $user_id Optional. User ID. Defaults to current user.
 * @return string|false Role name or false if not found.
 */
function buygo_get_user_role($user_id = 0) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    if (!$user_id) return false;

    // Check specifically for BuyGo roles first
    $role_manager = App::instance()->make(RoleManager::class);
    
    if ($role_manager->is_seller($user_id)) return 'buygo_seller';
    if ($role_manager->is_helper($user_id)) return 'buygo_helper';
    
    // Fallback to primary WP role
    $user = get_userdata($user_id);
    return is_array($user->roles) ? reset($user->roles) : false;
}

/**
 * Check if the user is a BuyGo Seller.
 *
 * @param int $user_id Optional. User ID. Defaults to current user.
 * @return bool
 */
function buygo_is_seller($user_id = 0) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    return App::instance()->make(RoleManager::class)->is_seller($user_id);
}

/**
 * Check if the user is a BuyGo Helper.
 *
 * @param int $user_id Optional. User ID. Defaults to current user.
 * @return bool
 */
function buygo_is_helper($user_id = 0) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    return App::instance()->make(RoleManager::class)->is_helper($user_id);
}

/**
 * Check if the user is a BuyGo Admin.
 *
 * @param int $user_id Optional. User ID. Defaults to current user.
 * @return bool
 */
function buygo_is_admin($user_id = 0) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    return App::instance()->make(RoleManager::class)->user_has_role($user_id, 'buygo_admin');
}

/**
 * Check if user has specific BuyGo permission.
 *
 * @param int $user_id User ID.
 * @param string $capability Capability or permission string.
 * @return bool
 */
function buygo_check_permission($user_id, $capability) {
    return user_can($user_id, $capability);
}

/**
 * Get the LINE UID bound to the user.
 *
 * @param int $user_id Optional. User ID. Defaults to current user.
 * @return string|null LINE UID or null if not bound.
 */
function buygo_get_user_line_uid($user_id = 0) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    $line_service = App::instance()->make(LineService::class);
    return $line_service->get_line_uid($user_id);
}

/**
 * Verify Nonce (Wrapper)
 * Task 8.1
 * 
 * @param string $nonce
 * @param string $action
 * @return bool
 */
function buygo_verify_nonce($nonce, $action = -1) {
    return wp_verify_nonce($nonce, $action);
}

/**
 * Sanitize Order Note (Task 8.1)
 * Removes URLs and domain-like strings to prevent platform leakage.
 *
 * @param string $note
 * @return string
 */
function buygo_sanitize_order_note($note) {
    // Remove Standard URLs (http/https/ftp)
    $note = preg_replace('/\b((https?|ftp|file):\/\/|www\.)[-A-Z0-9+&@#\/%?=~_|$!:,.;]*[A-Z0-9+&@#\/%=~_|$]/i', '[連結已遮蔽]', $note);
    
    // Remove domain-like patterns (example.com, etc.)
    // Very basic regex to catch common patterns without being too aggressive on normal text
    $note = preg_replace('/[a-zA-Z0-9.-]+\.[a-zA-Z]{2,6}(\/[^\s]*)?/i', '[連結已遮蔽]', $note);
    
    return sanitize_text_field($note);
}
