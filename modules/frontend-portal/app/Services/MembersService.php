<?php
/**
 * MembersService
 *
 * [AI Context]
 * - Handles member data retrieval from WordPress users
 * - Filters members by user role (admin sees all, seller sees limited, helper sees authorized)
 * - Formats user data with roles, LINE binding status, and seller application status
 *
 * [Constraints]
 * - Must use WordPress WP_User_Query for user queries
 * - Must check user permissions using BuyGo RoleManager
 * - Must sanitize all input data
 */

namespace BuyGo\Modules\FrontendPortal\App\Services;

use BuyGo\Core\App;
use BuyGo\Core\Services\RoleManager;
use WP_User_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MembersService {

	/**
	 * Get members list
	 *
	 * @param int $user_id User ID
	 * @param array $args Query arguments
	 * @return array Members list with pagination
	 */
	public function getMembers( $user_id, $args = [] ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return [
				'members' => [],
				'pagination' => [
					'total' => 0,
					'page' => 1,
					'per_page' => 20,
					'total_pages' => 0,
				],
			];
		}

		$defaults = [
			'page' => 1,
			'per_page' => 20,
			'search' => '',
			'role' => '',
		];

		$args = wp_parse_args( $args, $defaults );
		$page = absint( $args['page'] );
		$per_page = absint( $args['per_page'] );
		$search = sanitize_text_field( $args['search'] );
		$role = sanitize_text_field( $args['role'] );

		$role_manager = App::instance()->make( RoleManager::class );
		$is_admin = current_user_can( 'manage_options' ) || $role_manager->is_admin( $user_id );
		$is_seller = $role_manager->is_seller( $user_id );
		$is_helper = $role_manager->is_helper( $user_id );

		// Build query arguments
		$query_args = [
			'number' => $per_page,
			'paged' => $page,
			'orderby' => 'registered',
			'order' => 'DESC',
		];

		// Role-based filtering
		if ( ! $is_admin ) {
			// Non-admins can't see administrators or buygo_admin
			$query_args['role__not_in'] = [ 'administrator', 'buygo_admin' ];
			
			if ( $is_seller ) {
				// Sellers can see customers and their helpers
				// For now, show customers and helpers (TODO: filter helpers by seller relationship)
				if ( $role && $role !== 'buygo_helper' ) {
					// If filtering by specific role (not helper), only show customers
					if ( $role === 'subscriber' ) {
						$query_args['role__in'] = [ 'subscriber', 'customer', 'buygo_buyer' ];
					} else {
						$query_args['role'] = $role;
					}
				} else {
					// Default: show customers and helpers
					$query_args['role__in'] = [ 'subscriber', 'customer', 'buygo_buyer', 'buygo_helper' ];
				}
			} elseif ( $is_helper ) {
				// Helpers can see customers (for order management)
				if ( $role && $role !== 'buygo_helper' ) {
					if ( $role === 'subscriber' ) {
						$query_args['role__in'] = [ 'subscriber', 'customer', 'buygo_buyer' ];
					} else {
						$query_args['role'] = $role;
					}
				} else {
					$query_args['role__in'] = [ 'subscriber', 'customer', 'buygo_buyer' ];
				}
			}
		} else {
			// Admins can see all roles
			if ( $role ) {
				if ( $role === 'subscriber' ) {
					$query_args['role__in'] = [ 'subscriber', 'customer', 'buygo_buyer' ];
				} else {
					$query_args['role'] = $role;
				}
			}
		}

		// Search
		if ( ! empty( $search ) ) {
			$query_args['search'] = '*' . $search . '*';
			$query_args['search_columns'] = [ 'user_login', 'user_email', 'display_name' ];
		}

		// Execute query
		$user_query = new WP_User_Query( $query_args );
		$total_users = $user_query->get_total();
		$users = $user_query->get_results();

		// Format users
		$formatted_members = [];
		foreach ( $users as $user ) {
			$formatted_members[] = $this->formatMember( $user );
		}

		return [
			'members' => $formatted_members,
			'pagination' => [
				'total' => $total_users,
				'page' => $page,
				'per_page' => $per_page,
				'total_pages' => ceil( $total_users / $per_page ),
			],
		];
	}

	/**
	 * Format member data
	 *
	 * @param \WP_User $user WordPress user object
	 * @return array Formatted member data
	 */
	protected function formatMember( $user ) {
		// Get LINE UID
		$line_uid = $this->getLineUid( $user->ID );

		// Get role display name (Chinese)
		$role_key = $this->getPrimaryRole( $user );
		$role_display = $this->getRoleDisplayName( $role_key );

		// Get seller application status
		$seller_status = $this->getSellerApplicationStatus( $user->ID );

		// Get avatar URL
		$avatar_url = get_avatar_url( $user->ID, [ 'size' => 96 ] );

		return [
			'id' => $user->ID,
			'name' => $user->display_name ?: $user->user_login,
			'email' => $user->user_email,
			'avatar_url' => $avatar_url,
			'roles' => $user->roles,
			'role_key' => $role_key,
			'role_display' => $role_display,
			'line_uid' => $line_uid,
			'line_bound' => ! empty( $line_uid ),
			'seller_status' => $seller_status,
			'registered' => $user->user_registered,
			'registered_date' => date_i18n( 'Y-m-d H:i:s', strtotime( $user->user_registered ) ),
		];
	}

	/**
	 * Get primary role (highest priority)
	 *
	 * @param \WP_User $user WordPress user object
	 * @return string Role key
	 */
	protected function getPrimaryRole( $user ) {
		$roles = $user->roles;
		
		// Priority: administrator > buygo_admin > buygo_seller > buygo_helper > customer
		if ( in_array( 'administrator', $roles ) ) {
			return 'administrator';
		} elseif ( in_array( 'buygo_admin', $roles ) ) {
			return 'buygo_admin';
		} elseif ( in_array( 'buygo_seller', $roles ) ) {
			return 'buygo_seller';
		} elseif ( in_array( 'buygo_helper', $roles ) ) {
			return 'buygo_helper';
		} else {
			return reset( $roles ) ?: 'subscriber';
		}
	}

	/**
	 * Get role display name (Chinese)
	 *
	 * @param string $role_key Role key
	 * @return string Role display name
	 */
	protected function getRoleDisplayName( $role_key ) {
		$role_name_map = [
			'administrator' => 'WP 管理員',
			'buygo_admin' => 'BuyGo 管理員',
			'buygo_seller' => '賣家',
			'buygo_helper' => '小幫手',
			'subscriber' => '顧客',
			'customer' => '顧客',
			'buygo_buyer' => '顧客',
		];

		return $role_name_map[ $role_key ] ?? '顧客';
	}

	/**
	 * Get LINE UID for user
	 *
	 * @param int $user_id User ID
	 * @return string|null LINE UID or null
	 */
	protected function getLineUid( $user_id ) {
		// Check if LineService exists
		if ( class_exists( '\\BuyGo\\Core\\Services\\LineService' ) ) {
			$line_service = new \BuyGo\Core\Services\LineService();
			return $line_service->get_line_uid( $user_id );
		}

		// Fallback: check user meta
		return get_user_meta( $user_id, 'buygo_line_uid', true ) ?: null;
	}

	/**
	 * Get seller application status
	 *
	 * @param int $user_id User ID
	 * @return string|null Status (pending, approved, rejected) or null
	 */
	protected function getSellerApplicationStatus( $user_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'buygo_seller_applications';

		// Check if table exists
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) !== $table_name ) {
			return null;
		}

		$status = $wpdb->get_var( $wpdb->prepare(
			"SELECT status FROM {$table_name} WHERE user_id = %d ORDER BY id DESC LIMIT 1",
			$user_id
		) );

		return $status ?: null;
	}
}
