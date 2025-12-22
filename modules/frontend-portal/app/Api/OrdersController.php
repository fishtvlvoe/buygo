<?php
/**
 * OrdersController
 *
 * [AI Context]
 * - Handles order API requests
 * - Returns order list, single order, and handles order merging
 *
 * [Constraints]
 * - Must use OrdersService and MergeOrderService for data retrieval
 * - Must check permissions using BaseController
 * - Must verify Nonce
 */

namespace BuyGo\Modules\FrontendPortal\App\Api;

use WP_REST_Request;
use BuyGo\Modules\FrontendPortal\App\Services\OrdersService;
use BuyGo\Modules\FrontendPortal\App\Services\MergeOrderService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OrdersController extends BaseController {

	/**
	 * OrdersService instance
	 */
	protected $orders_service;

	/**
	 * MergeOrderService instance
	 */
	protected $merge_order_service;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->orders_service = new OrdersService();
		$this->merge_order_service = new MergeOrderService();
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
		register_rest_route( $this->namespace, '/orders', [
			[
				'methods' => 'GET',
				'callback' => [ $this, 'index' ],
				'permission_callback' => [ $this, 'check_read_permission' ],
			],
		] );

		register_rest_route( $this->namespace, '/orders/(?P<id>\d+)', [
			[
				'methods' => 'GET',
				'callback' => [ $this, 'show' ],
				'permission_callback' => [ $this, 'check_read_permission' ],
			],
		] );

		register_rest_route( $this->namespace, '/orders/merge', [
			[
				'methods' => 'POST',
				'callback' => [ $this, 'merge' ],
				'permission_callback' => [ $this, 'check_write_permission' ],
			],
		] );

		register_rest_route( $this->namespace, '/orders/(?P<id>\d+)/status', [
			[
				'methods' => 'PUT',
				'callback' => [ $this, 'update_status' ],
				'permission_callback' => [ $this, 'check_write_permission' ],
			],
			[
				'methods' => 'PATCH',
				'callback' => [ $this, 'update_status' ],
				'permission_callback' => [ $this, 'check_write_permission' ],
			],
		] );

		register_rest_route( $this->namespace, '/orders/(?P<id>\d+)', [
			[
				'methods' => 'DELETE',
				'callback' => [ $this, 'delete' ],
				'permission_callback' => [ $this, 'check_write_permission' ],
			],
		] );

		register_rest_route( $this->namespace, '/orders/(?P<id>\d+)/customer', [
			[
				'methods' => 'PUT',
				'callback' => [ $this, 'update_customer' ],
				'permission_callback' => [ $this, 'check_write_permission' ],
			],
			[
				'methods' => 'PATCH',
				'callback' => [ $this, 'update_customer' ],
				'permission_callback' => [ $this, 'check_write_permission' ],
			],
		] );
	}

	/**
	 * Get orders list
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

		$args = [
			'page' => $request->get_param( 'page' ) ?: 1,
			'per_page' => $request->get_param( 'per_page' ) ?: 20,
			'payment_status' => $request->get_param( 'payment_status' ) ?: '',
			'shipping_status' => $request->get_param( 'shipping_status' ) ?: '',
			'search' => $request->get_param( 'search' ) ?: '',
		];

		try {
			$data = $this->orders_service->getOrders( $user_id, $args );
			return $this->success( $data );
		} catch ( \Exception $e ) {
			return $this->error( $e->getMessage(), 500 );
		}
	}

	/**
	 * Get single order
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function show( WP_REST_Request $request ) {
		// Permission is already checked by permission_callback
		// WordPress REST API automatically verifies nonce from cookies
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return $this->error( 'User not authenticated', 401 );
		}

		$order_id = absint( $request->get_param( 'id' ) );

		try {
			$data = $this->orders_service->getOrder( $user_id, $order_id );
			if ( $data === null ) {
				return $this->error( 'Order not found', 404 );
			}
			return $this->success( $data );
		} catch ( \Exception $e ) {
			return $this->error( $e->getMessage(), 500 );
		}
	}

	/**
	 * Merge orders
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function merge( WP_REST_Request $request ) {
		// Permission is already checked by permission_callback
		// WordPress REST API automatically verifies nonce from cookies
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return $this->error( 'User not authenticated', 401 );
		}

		$order_ids = $request->get_param( 'order_ids' );
		$shipping_method = $request->get_param( 'shipping_method' ) ?: 'standard';

		if ( ! is_array( $order_ids ) || empty( $order_ids ) ) {
			return $this->error( 'Invalid order IDs', 400 );
		}

		try {
			$data = $this->merge_order_service->mergeOrders( $user_id, $order_ids, $shipping_method );
			if ( $data === false ) {
				return $this->error( 'Failed to merge orders', 400 );
			}
			return $this->success( $data );
		} catch ( \Exception $e ) {
			return $this->error( $e->getMessage(), 500 );
		}
	}

	/**
	 * Update order status
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function update_status( WP_REST_Request $request ) {
		// Permission is already checked by permission_callback
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return $this->error( 'User not authenticated', 401 );
		}

		$order_id = absint( $request->get_param( 'id' ) );
		$data = $request->get_json_params();

		// Check if order exists and user has permission
		$order = $this->orders_service->getOrder( $user_id, $order_id );
		if ( $order === null ) {
			return $this->error( 'Order not found', 404 );
		}

		global $wpdb;
		$table_orders = $wpdb->prefix . 'fct_orders';
		$update_data = [];
		$update_format = [];

		// Update payment status
		if ( isset( $data['payment_status'] ) ) {
			$allowed_payment_statuses = [ 'paid', 'pending', 'failed', 'refunded' ];
			if ( in_array( $data['payment_status'], $allowed_payment_statuses, true ) ) {
				$update_data['payment_status'] = $data['payment_status'];
				$update_format[] = '%s';
			}
		}

		// Update shipping status
		if ( isset( $data['shipping_status'] ) ) {
			$allowed_shipping_statuses = [ 'unshipped', 'shipped', 'delivered', 'cancelled' ];
			if ( in_array( $data['shipping_status'], $allowed_shipping_statuses, true ) ) {
				$update_data['shipping_status'] = $data['shipping_status'];
				$update_format[] = '%s';
			}
		}

		if ( empty( $update_data ) ) {
			return $this->error( 'No valid status to update', 400 );
		}

		$update_data['updated_at'] = current_time( 'mysql' );
		$update_format[] = '%s';

		$result = $wpdb->update(
			$table_orders,
			$update_data,
			[ 'id' => $order_id ],
			$update_format,
			[ '%d' ]
		);

		if ( $result === false ) {
			return $this->error( 'Failed to update order status', 500 );
		}

		// Get updated order
		$updated_order = $this->orders_service->getOrder( $user_id, $order_id );

		return $this->success( [
			'message' => 'Order status updated successfully',
			'order' => $updated_order,
		] );
	}

	/**
	 * Delete order
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function delete( WP_REST_Request $request ) {
		// Permission is already checked by permission_callback
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return $this->error( 'User not authenticated', 401 );
		}

		$order_id = absint( $request->get_param( 'id' ) );

		// Check if order exists and user has permission
		$order = $this->orders_service->getOrder( $user_id, $order_id );
		if ( $order === null ) {
			return $this->error( 'Order not found', 404 );
		}

		// Check if order is part of a merged order
		global $wpdb;
		$table_merged = $wpdb->prefix . 'buygo_merged_orders';
		$merged_order = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table_merged} WHERE JSON_CONTAINS(original_order_ids, %s)",
			json_encode( $order_id )
		) );

		if ( $merged_order ) {
			return $this->error( 'Cannot delete order that is part of a merged order. Please unmerge first.', 400 );
		}

		// Delete order using OrdersService
		$result = $this->orders_service->deleteOrder( $user_id, $order_id );
		if ( $result === false ) {
			return $this->error( 'Failed to delete order', 500 );
		}

		return $this->success( [
			'message' => 'Order deleted successfully',
			'order_id' => $order_id,
		] );
	}

	/**
	 * Update customer information
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function update_customer( WP_REST_Request $request ) {
		// Permission is already checked by permission_callback
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return $this->error( 'User not authenticated', 401 );
		}

		$order_id = absint( $request->get_param( 'id' ) );
		$data = $request->get_json_params();

		// Check if order exists and user has permission
		$order = $this->orders_service->getOrder( $user_id, $order_id );
		if ( $order === null ) {
			return $this->error( 'Order not found', 404 );
		}

		// Validate required fields
		if ( empty( $data['name'] ) && empty( $data['email'] ) ) {
			return $this->error( 'Name or email is required', 400 );
		}

		// Update customer info using OrdersService
		$result = $this->orders_service->updateCustomerInfo( $user_id, $order_id, $data );
		if ( $result === false ) {
			return $this->error( 'Failed to update customer information', 500 );
		}

		// Get updated order
		$updated_order = $this->orders_service->getOrder( $user_id, $order_id );

		return $this->success( [
			'message' => 'Customer information updated successfully',
			'order' => $updated_order,
		] );
	}
}
