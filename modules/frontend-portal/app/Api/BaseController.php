<?php
/**
 * BaseController for Frontend Portal API
 *
 * [AI Context]
 * - Base class for all Frontend Portal API controllers
 * - Handles permission checking and common functionality
 *
 * [Constraints]
 * - Must check user permissions using BuyGo RoleManager
 * - Must verify Nonce for all requests
 */

namespace BuyGo\Modules\FrontendPortal\App\Api;

use BuyGo\Core\App;
use BuyGo\Core\Services\RoleManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BaseController {

	protected $namespace = 'buygo/v1/portal';

	/**
	 * Register routes (to be implemented by child controllers)
	 */
	public function register_routes() {
		// To be implemented by child controllers
	}

	/**
	 * Check if user has permission to access portal
	 *
	 * @return bool
	 */
	public function check_permission() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}

		// WP Administrator has full access
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		// BuyGo Admin can access
		if ( buygo_is_admin() ) {
			return true;
		}

		// Seller can access
		if ( buygo_is_seller() ) {
			return true;
		}

		// Helper can access
		if ( buygo_is_helper() ) {
			return true;
		}

		return false;
	}

	/**
	 * Check read permission
	 *
	 * [AI Context]
	 * - WordPress REST API automatically handles cookie-based nonce verification
	 * - We only need to check user permissions here
	 * - Similar to BuyGo\Core\Api\DashboardController::check_read_permission()
	 *
	 * @param WP_REST_Request $request
	 * @return bool
	 */
	public function check_read_permission( $request ) {
		// Check if user is logged in first
		if ( ! is_user_logged_in() ) {
			return false;
		}
		
		$user = wp_get_current_user();
		
		// Only allow logged-in users with correct roles
		return in_array( 'administrator', (array) $user->roles ) || 
		       in_array( 'buygo_admin', (array) $user->roles ) ||
		       in_array( 'buygo_seller', (array) $user->roles ) || 
		       in_array( 'buygo_helper', (array) $user->roles );
	}

	/**
	 * Check write permission
	 *
	 * [AI Context]
	 * - WordPress REST API automatically handles cookie-based nonce verification
	 * - We only need to check user permissions here
	 * - Similar to BuyGo\Core\Api\DashboardController::check_write_permission()
	 *
	 * @param WP_REST_Request $request
	 * @return bool
	 */
	public function check_write_permission( $request ) {
		// Check if user is logged in first
		if ( ! is_user_logged_in() ) {
			return false;
		}
		
		$user = wp_get_current_user();
		
		// Only allow logged-in users with correct roles
		return in_array( 'administrator', (array) $user->roles ) || 
		       in_array( 'buygo_admin', (array) $user->roles ) ||
		       in_array( 'buygo_seller', (array) $user->roles ) || 
		       in_array( 'buygo_helper', (array) $user->roles );
	}

	/**
	 * Send success response
	 *
	 * @param mixed $data Response data
	 * @param int $status HTTP status code
	 * @return WP_REST_Response
	 */
	protected function success( $data, $status = 200 ) {
		return new \WP_REST_Response( [
			'success' => true,
			'data' => $data,
		], $status );
	}

	/**
	 * Send error response
	 *
	 * @param string $message Error message
	 * @param int $status HTTP status code
	 * @return WP_REST_Response
	 */
	protected function error( $message, $status = 400 ) {
		return new \WP_REST_Response( [
			'success' => false,
			'message' => $message,
		], $status );
	}
}
