<?php
/**
 * DashboardController
 *
 * [AI Context]
 * - Handles dashboard API requests
 * - Returns dashboard statistics data
 *
 * [Constraints]
 * - Must use DashboardService for data retrieval
 * - Must check permissions using BaseController
 * - Must verify Nonce
 */

namespace BuyGo\Modules\FrontendPortal\App\Api;

use WP_REST_Request;
use BuyGo\Modules\FrontendPortal\App\Services\DashboardService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DashboardController extends BaseController {

	/**
	 * DashboardService instance
	 */
	protected $dashboard_service;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->dashboard_service = new DashboardService();
	}

	/**
	 * Get singleton instance
	 */
	public static function instance() {
		static $instance = null;
		if ( null === $instance ) {
			$instance = new self();
		}
		return $instance;
	}

	/**
	 * Register routes
	 */
	public function register_routes() {
		register_rest_route( $this->namespace, '/dashboard', [
			[
				'methods' => 'GET',
				'callback' => [ $this, 'index' ],
				'permission_callback' => [ $this, 'check_read_permission' ],
			],
		] );
	}

	/**
	 * Get dashboard data
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function index( WP_REST_Request $request ) {
		// Permission is already checked by permission_callback
		// WordPress REST API automatically verifies nonce from cookies
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return $this->error( 'User not authenticated', 401 );
		}

		try {
			$data = $this->dashboard_service->getDashboardData( $user_id );
			return $this->success( $data );
		} catch ( \Exception $e ) {
			return $this->error( $e->getMessage(), 500 );
		}
	}
}
