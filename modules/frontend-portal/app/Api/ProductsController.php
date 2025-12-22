<?php
/**
 * ProductsController
 *
 * [AI Context]
 * - Handles product API requests
 * - Returns product list and single product data
 *
 * [Constraints]
 * - Must use ProductsService for data retrieval
 * - Must check permissions using BaseController
 * - Must verify Nonce
 */

namespace BuyGo\Modules\FrontendPortal\App\Api;

use WP_REST_Request;
use BuyGo\Modules\FrontendPortal\App\Services\ProductsService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ProductsController extends BaseController {

	/**
	 * ProductsService instance
	 */
	protected $products_service;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->products_service = new ProductsService();
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
		register_rest_route( $this->namespace, '/products', [
			[
				'methods' => 'GET',
				'callback' => [ $this, 'index' ],
				'permission_callback' => [ $this, 'check_read_permission' ],
			],
		] );

		register_rest_route( $this->namespace, '/products/(?P<id>\d+)', [
			[
				'methods' => 'GET',
				'callback' => [ $this, 'show' ],
				'permission_callback' => [ $this, 'check_read_permission' ],
			],
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

		// Supplier list for product assignment (smart search)
		register_rest_route( $this->namespace, '/products/suppliers', [
			[
				'methods' => 'GET',
				'callback' => [ $this, 'get_suppliers' ],
				'permission_callback' => [ $this, 'check_read_permission' ],
			],
		] );
	}

	/**
	 * Get products list
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
			'status' => $request->get_param( 'status' ) ?: 'all',
			'search' => $request->get_param( 'search' ) ?: '',
		];

		try {
			$data = $this->products_service->getProducts( $user_id, $args );
			return $this->success( $data );
		} catch ( \Exception $e ) {
			return $this->error( $e->getMessage(), 500 );
		}
	}

	/**
	 * Get single product
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

		$product_id = absint( $request->get_param( 'id' ) );

		try {
			$data = $this->products_service->getProduct( $user_id, $product_id );
			if ( $data === null ) {
				return $this->error( 'Product not found', 404 );
			}
			return $this->success( $data );
		} catch ( \Exception $e ) {
			return $this->error( $e->getMessage(), 500 );
		}
	}

	/**
	 * Update product
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function update( WP_REST_Request $request ) {
		// Permission is already checked by permission_callback
		// WordPress REST API automatically verifies nonce from cookies
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return $this->error( 'User not authenticated', 401 );
		}

		$product_id = absint( $request->get_param( 'id' ) );
		$data = $request->get_json_params();

		try {
			$result = $this->products_service->updateProduct( $user_id, $product_id, $data );
			if ( $result['success'] ) {
				return $this->success( $result );
			} else {
				return $this->error( $result['message'], 400 );
			}
		} catch ( \Exception $e ) {
			return $this->error( $e->getMessage(), 500 );
		}
	}

	/**
	 * Get suppliers list for product assignment (smart search dropdown)
	 *
	 * [AI Context]
	 * - Returns suppliers for the current seller (or all for admin)
	 * - Supports search query for smart search functionality
	 * - Used in product edit modal for assigning supplier
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_suppliers( WP_REST_Request $request ) {
		global $wpdb;

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return $this->error( 'User not authenticated', 401 );
		}

		$search = sanitize_text_field( $request->get_param( 'search' ) ?: '' );
		$limit = absint( $request->get_param( 'limit' ) ?: 20 );

		$table = $wpdb->prefix . 'buygo_suppliers';

		// Build query
		$where = [];
		$values = [];

		// Permission check: Sellers see only their own suppliers, admins see all
		$is_admin = current_user_can( 'manage_options' );
		if ( ! $is_admin ) {
			$where[] = 'created_by = %d';
			$values[] = $user_id;
		}

		// Search filter
		if ( $search ) {
			$where[] = '(name LIKE %s OR contact_name LIKE %s)';
			$values[] = '%' . $wpdb->esc_like( $search ) . '%';
			$values[] = '%' . $wpdb->esc_like( $search ) . '%';
		}

		$where_clause = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

		// Sanitize table name
		$safe_table = esc_sql( $table );
		$sql = "SELECT id, name, contact_name FROM {$safe_table} {$where_clause} ORDER BY name ASC LIMIT %d";
		$values[] = $limit;

		$prepared_sql = $wpdb->prepare( $sql, $values );
		$suppliers = $wpdb->get_results( $prepared_sql );

		$data = [];
		foreach ( $suppliers as $supplier ) {
			$data[] = [
				'id' => (int) $supplier->id,
				'name' => $supplier->name,
				'contact_name' => $supplier->contact_name,
				'label' => $supplier->name . ( $supplier->contact_name ? ' (' . $supplier->contact_name . ')' : '' ),
			];
		}

		return $this->success( [ 'suppliers' => $data ] );
	}
}
