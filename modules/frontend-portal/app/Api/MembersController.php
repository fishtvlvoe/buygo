<?php
/**
 * MembersController
 *
 * [AI Context]
 * - Handles member API requests
 * - Returns member list and single member data
 *
 * [Constraints]
 * - Must use MembersService for data retrieval
 * - Must check permissions using BaseController
 * - Must verify Nonce
 */

namespace BuyGo\Modules\FrontendPortal\App\Api;

use WP_REST_Request;
use BuyGo\Modules\FrontendPortal\App\Services\MembersService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MembersController extends BaseController {

	/**
	 * MembersService instance
	 */
	protected $members_service;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->members_service = new MembersService();
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
		register_rest_route( $this->namespace, '/members', [
			[
				'methods' => 'GET',
				'callback' => [ $this, 'index' ],
				'permission_callback' => [ $this, 'check_read_permission' ],
			],
		] );

		register_rest_route( $this->namespace, '/members/(?P<id>\d+)', [
			[
				'methods' => 'PUT',
				'callback' => [ $this, 'update' ],
				'permission_callback' => [ $this, 'check_write_permission' ],
			],
			[
				'methods' => 'PATCH',
				'callback' => [ $this, 'update' ],
				'permission_callback' => [ $this, 'check_write_permission' ],
			],
		] );
	}

	/**
	 * Get members list
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function index( WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return $this->error( 'User not authenticated', 401 );
		}

		$args = [
			'page' => $request->get_param( 'page' ) ?: 1,
			'per_page' => $request->get_param( 'per_page' ) ?: 20,
			'search' => $request->get_param( 'search' ) ?: '',
			'role' => $request->get_param( 'role' ) ?: '',
		];

		try {
			$data = $this->members_service->getMembers( $user_id, $args );
			return $this->success( $data );
		} catch ( \Exception $e ) {
			return $this->error( $e->getMessage(), 500 );
		}
	}

	/**
	 * Update member role
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function update( WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return $this->error( 'User not authenticated', 401 );
		}

		// Check if current user has permission to edit roles (only admins)
		if ( ! current_user_can( 'manage_options' ) && ! buygo_is_admin() ) {
			return $this->error( 'Insufficient permissions', 403 );
		}

		$member_id = $request->get_param( 'id' );
		$roles = $request->get_param( 'roles' );
		$post_channel_id = $request->get_param( 'post_channel_id' );

		if ( empty( $roles ) || ! is_array( $roles ) ) {
			return $this->error( 'Missing roles parameter', 400 );
		}

		$user = get_user_by( 'id', $member_id );
		if ( ! $user ) {
			return $this->error( 'User not found', 404 );
		}

		// Get old roles for comparison
		$old_roles = $user->roles;

		// Set primary role (replaces existing)
		$primary_role = $roles[0];
		$user->set_role( $primary_role );

		// Add additional roles if provided
		for ( $i = 1; $i < count( $roles ); $i++ ) {
			$user->add_role( $roles[ $i ] );
		}

		// Handle post channel ID for roles that support it
		$roles_with_channel = [ 'buygo_seller', 'buygo_admin', 'administrator', 'buygo_helper' ];
		if ( in_array( $primary_role, $roles_with_channel ) && isset( $post_channel_id ) ) {
			if ( $post_channel_id ) {
				update_user_meta( $member_id, 'buygo_post_channel_id', (int) $post_channel_id );
			} else {
				delete_user_meta( $member_id, 'buygo_post_channel_id' );
			}
		} elseif ( ! in_array( $primary_role, $roles_with_channel ) ) {
			delete_user_meta( $member_id, 'buygo_post_channel_id' );
		}

		// Sync role to FluentCart and FluentCommunity if it's a BuyGo role
		$buygo_roles = [ 'buygo_admin', 'buygo_seller', 'buygo_helper' ];
		if ( in_array( $primary_role, $buygo_roles ) ) {
			if ( class_exists( '\\BuyGo\\Core\\Services\\RoleSyncService' ) ) {
				$role_sync = \BuyGo\Core\App::instance()->make( \BuyGo\Core\Services\RoleSyncService::class );
				if ( $role_sync ) {
					$role_sync->sync_role_to_integrations( $member_id, $primary_role );
				}
			}
		}

		// Trigger action hook
		do_action( 'buygo_role_changed', $member_id, $old_roles, $roles );

		return $this->success( [
			'message' => 'Member role updated successfully',
			'user_id' => $member_id,
			'roles' => $roles,
		] );
	}
}
