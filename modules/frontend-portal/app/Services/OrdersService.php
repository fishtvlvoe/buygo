<?php
/**
 * OrdersService
 *
 * [AI Context]
 * - Handles order data retrieval from FluentCart
 * - Filters orders by user role (admin sees all, seller sees own, helper sees authorized)
 * - Converts prices from "cents" (分) to "yuan" (元) for TWD
 *
 * [Constraints]
 * - Must use WordPress $wpdb for database operations
 * - Must check user permissions using BuyGo RoleManager
 * - Must sanitize all input data
 */

namespace BuyGo\Modules\FrontendPortal\App\Services;

use BuyGo\Core\App;
use BuyGo\Core\Services\RoleManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OrdersService {

	/**
	 * Convert price from cents to yuan for TWD
	 *
	 * @param float $price_in_cents Price in cents
	 * @param string $currency Currency code (default: TWD)
	 * @return float Price in yuan
	 */
	protected function convert_price( $price_in_cents, $currency = 'TWD' ) {
		if ( $currency === 'TWD' ) {
			return (float) $price_in_cents / 100;
		}
		return (float) $price_in_cents;
	}

	/**
	 * Get orders list
	 *
	 * @param int $user_id User ID
	 * @param array $args Query arguments
	 * @return array Orders list with pagination
	 */
	public function getOrders( $user_id, $args = [] ) {
		global $wpdb;

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return [
				'orders' => [],
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
			'payment_status' => '',
			'shipping_status' => '',
			'search' => '',
		];

		$args = wp_parse_args( $args, $defaults );
		$page = absint( $args['page'] );
		$per_page = absint( $args['per_page'] );
		$offset = ( $page - 1 ) * $per_page;

		$table_orders = $wpdb->prefix . 'fct_orders';
		$table_items = $wpdb->prefix . 'fct_order_items';
		$table_posts = $wpdb->posts;
		$table_customers = $wpdb->prefix . 'fct_customers';

		// Check if tables exist
		$customers_table_exists = ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_customers}'" ) === $table_customers );

		// Permission check
		$role_manager = App::instance()->make( RoleManager::class );
		$is_admin = current_user_can( 'manage_options' ) || $role_manager->is_admin( $user_id );
		$is_seller = $role_manager->is_seller( $user_id );
		$is_helper = $role_manager->is_helper( $user_id );

		$where_conditions = [ '1=1' ];

		// Payment status filter
		if ( $args['payment_status'] ) {
			$where_conditions[] = $wpdb->prepare( "o.payment_status = %s", $args['payment_status'] );
		}

		// Shipping status filter
		if ( $args['shipping_status'] ) {
			$where_conditions[] = $wpdb->prepare( "o.shipping_status = %s", $args['shipping_status'] );
		}

		// Search filter
		if ( $args['search'] && is_numeric( $args['search'] ) ) {
			$where_conditions[] = $wpdb->prepare( "o.id = %d", intval( $args['search'] ) );
		}

		$where_conditions = [ '1=1' ];
		$where_params = [];

		// Payment status filter
		if ( $args['payment_status'] ) {
			$where_conditions[] = "o.payment_status = %s";
			$where_params[] = $args['payment_status'];
		}

		// Shipping status filter
		if ( $args['shipping_status'] ) {
			$where_conditions[] = "o.shipping_status = %s";
			$where_params[] = $args['shipping_status'];
		}

		// Search filter
		if ( $args['search'] && is_numeric( $args['search'] ) ) {
			$where_conditions[] = "o.id = %d";
			$where_params[] = intval( $args['search'] );
		}

		$where_clause = implode( ' AND ', $where_conditions );

		// Build query based on role
		if ( $is_admin ) {
			// Admin: Get all orders
			$count_sql = "SELECT COUNT(DISTINCT o.id) FROM {$table_orders} o";
			$data_sql = "SELECT DISTINCT o.id, o.customer_id, o.total_amount, o.status, o.payment_status, 
						   o.shipping_status, o.currency, o.created_at";

			if ( $customers_table_exists ) {
				$count_sql .= " LEFT JOIN {$table_customers} c ON o.customer_id = c.id";
				$data_sql .= ", c.first_name, c.last_name, c.email, c.contact_id as user_id
							   FROM {$table_orders} o
							   LEFT JOIN {$table_customers} c ON o.customer_id = c.id";
			} else {
				$data_sql .= ", NULL as first_name, NULL as last_name, NULL as email, NULL as user_id
							   FROM {$table_orders} o";
			}

			$count_sql .= " WHERE {$where_clause}";
			$data_sql .= " WHERE {$where_clause}";

			if ( $args['search'] && ! is_numeric( $args['search'] ) && $customers_table_exists ) {
				$search_like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
				$count_sql .= $wpdb->prepare(
					" AND (c.first_name LIKE %s OR c.last_name LIKE %s OR c.email LIKE %s)",
					$search_like, $search_like, $search_like
				);
				$data_sql .= $wpdb->prepare(
					" AND (c.first_name LIKE %s OR c.last_name LIKE %s OR c.email LIKE %s)",
					$search_like, $search_like, $search_like
				);
			}

			$data_sql .= " ORDER BY o.id DESC";

			// Get total count
			if ( ! empty( $where_params ) ) {
				$total = $wpdb->get_var( $wpdb->prepare( $count_sql, ...$where_params ) );
			} else {
				$total = $wpdb->get_var( $count_sql );
			}

			// Get data
			$all_params = $where_params;
			if ( ! empty( $all_params ) ) {
				$sql = $wpdb->prepare( $data_sql . " LIMIT %d OFFSET %d", ...array_merge( $all_params, [ $per_page, $offset ] ) );
			} else {
				$sql = $data_sql . $wpdb->prepare( " LIMIT %d OFFSET %d", $per_page, $offset );
			}

		} elseif ( $is_seller ) {
			// Seller: Only get orders with products from this seller
			$where_conditions[] = "p.post_type = 'fluent-products'";
			$where_conditions[] = "p.post_author = %d";
			$where_params[] = $user_id;

			$where_clause = implode( ' AND ', $where_conditions );

			$count_sql = "SELECT COUNT(DISTINCT o.id) 
						  FROM {$table_orders} o
						  INNER JOIN {$table_items} oi ON o.id = oi.order_id
						  INNER JOIN {$table_posts} p ON oi.post_id = p.ID";
			$data_sql = "SELECT DISTINCT o.id, o.customer_id, o.total_amount, o.status, o.payment_status,
						   o.shipping_status, o.currency, o.created_at";

			if ( $customers_table_exists ) {
				$count_sql .= " LEFT JOIN {$table_customers} c ON o.customer_id = c.id";
				$data_sql .= ", c.first_name, c.last_name, c.email, c.contact_id as user_id
							   FROM {$table_orders} o
							   LEFT JOIN {$table_customers} c ON o.customer_id = c.id
							   INNER JOIN {$table_items} oi ON o.id = oi.order_id
							   INNER JOIN {$table_posts} p ON oi.post_id = p.ID";
			} else {
				$data_sql .= ", NULL as first_name, NULL as last_name, NULL as email, NULL as user_id
							   FROM {$table_orders} o
							   INNER JOIN {$table_items} oi ON o.id = oi.order_id
							   INNER JOIN {$table_posts} p ON oi.post_id = p.ID";
			}

			$count_sql .= " WHERE {$where_clause}";
			$data_sql .= " WHERE {$where_clause}";

			if ( $args['search'] && ! is_numeric( $args['search'] ) && $customers_table_exists ) {
				$search_like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
				$count_sql .= $wpdb->prepare(
					" AND (c.first_name LIKE %s OR c.last_name LIKE %s OR c.email LIKE %s)",
					$search_like, $search_like, $search_like
				);
				$data_sql .= $wpdb->prepare(
					" AND (c.first_name LIKE %s OR c.last_name LIKE %s OR c.email LIKE %s)",
					$search_like, $search_like, $search_like
				);
			}

			$data_sql .= " ORDER BY o.id DESC";

			// Get total count
			$total = $wpdb->get_var( $wpdb->prepare( $count_sql, ...$where_params ) );

			// Get data
			$sql = $wpdb->prepare( $data_sql . " LIMIT %d OFFSET %d", ...array_merge( $where_params, [ $per_page, $offset ] ) );

		} elseif ( $is_helper ) {
			// Helper: See authorized sellers' orders
			// TODO: Implement helper authorization logic
			return [
				'orders' => [],
				'pagination' => [
					'total' => 0,
					'page' => $page,
					'per_page' => $per_page,
					'total_pages' => 0,
				],
			];
		} else {
			// No permission
			return [
				'orders' => [],
				'pagination' => [
					'total' => 0,
					'page' => $page,
					'per_page' => $per_page,
					'total_pages' => 0,
				],
			];
		}

		$orders = $wpdb->get_results( $sql );

		// Format orders
		$data = [];
		foreach ( $orders as $order ) {
			// Convert price from cents to yuan
			$total_amount = $this->convert_price( $order->total_amount, $order->currency ?? 'TWD' );

			$data[] = [
				'id' => $order->id,
				'order_number' => 'FC-' . $order->id,
				'customer_name' => trim( ( $order->first_name ?? '' ) . ' ' . ( $order->last_name ?? '' ) ),
				'customer_phone' => '', // Will be retrieved from order meta if needed
				'customer_email' => $order->email ?? '',
				'payment_status' => $order->payment_status ?? '',
				'shipping_status' => $order->shipping_status ?? '',
				'total_amount' => $total_amount,
				'formatted_total' => 'NT$ ' . number_format( $total_amount, 0 ),
				'currency' => $order->currency ?? 'TWD',
				'created_at' => $order->created_at,
			];
		}

		return [
			'orders' => $data,
			'pagination' => [
				'total' => (int) $total,
				'page' => $page,
				'per_page' => $per_page,
				'total_pages' => (int) ceil( $total / $per_page ),
			],
		];
	}

	/**
	 * Get single order
	 *
	 * @param int $user_id User ID
	 * @param int $order_id Order ID
	 * @return array|null Order data or null if not found
	 */
	public function getOrder( $user_id, $order_id ) {
		global $wpdb;

		$order_id = absint( $order_id );
		$table_orders = $wpdb->prefix . 'fct_orders';
		$table_customers = $wpdb->prefix . 'fct_customers';

		$order = $wpdb->get_row( $wpdb->prepare(
			"SELECT o.*, c.first_name, c.last_name, c.email, c.contact_id as user_id
			FROM {$table_orders} o
			LEFT JOIN {$table_customers} c ON o.customer_id = c.id
			WHERE o.id = %d",
			$order_id
		) );

		if ( ! $order ) {
			return null;
		}

		// Check permission
		$role_manager = App::instance()->make( RoleManager::class );
		$is_admin = current_user_can( 'manage_options' ) || $role_manager->is_admin( $user_id );
		$is_seller = $role_manager->is_seller( $user_id );

		if ( ! $is_admin && $is_seller ) {
			// Check if order contains seller's products
			$table_items = $wpdb->prefix . 'fct_order_items';
			$table_posts = $wpdb->posts;

			$has_seller_product = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*)
				FROM {$table_items} oi
				INNER JOIN {$table_posts} p ON oi.post_id = p.ID
				WHERE oi.order_id = %d
				AND p.post_type = 'fluent-products'
				AND p.post_author = %d",
				$order_id,
				$user_id
			) );

			if ( ! $has_seller_product ) {
				return null;
			}
		}

		// Convert price from cents to yuan
		$total_amount = $this->convert_price( $order->total_amount, $order->currency ?? 'TWD' );

		// Get shipping address and phone
		$table_addresses = $wpdb->prefix . 'fct_order_addresses';
		$shipping_address = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table_addresses} WHERE order_id = %d AND type = 'shipping' LIMIT 1",
			$order_id
		) );

		$customer_phone = '';
		$customer_address = '';
		$payment_method = '';

		if ( $shipping_address ) {
			$meta = $shipping_address->meta ? json_decode( $shipping_address->meta, true ) : [];
			$customer_phone = $meta['phone'] ?? '';
			$customer_address = trim( 
				( $shipping_address->postcode ?? '' ) . ' ' .
				( $shipping_address->address_1 ?? '' ) . ' ' . 
				( $shipping_address->address_2 ?? '' ) . ' ' .
				( $shipping_address->city ?? '' ) . ' ' .
				( $shipping_address->state ?? '' )
			);
		} else {
			// Fallback: try billing address
			$billing_address = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM {$table_addresses} WHERE order_id = %d AND type = 'billing' LIMIT 1",
				$order_id
			) );
			if ( $billing_address ) {
				$meta = $billing_address->meta ? json_decode( $billing_address->meta, true ) : [];
				$customer_phone = $meta['phone'] ?? '';
				$customer_address = trim( 
					( $billing_address->postcode ?? '' ) . ' ' .
					( $billing_address->address_1 ?? '' ) . ' ' . 
					( $billing_address->address_2 ?? '' ) . ' ' .
					( $billing_address->city ?? '' ) . ' ' .
					( $billing_address->state ?? '' )
				);
			}
		}

		// Get payment method from order meta
		$table_order_meta = $wpdb->prefix . 'fct_order_meta';
		$payment_method_meta = $wpdb->get_var( $wpdb->prepare(
			"SELECT meta_value FROM {$table_order_meta} WHERE order_id = %d AND meta_key = 'payment_method_title' LIMIT 1",
			$order_id
		) );
		$payment_method = $payment_method_meta ?: 'Gateway';

		// Get order items
		$table_items = $wpdb->prefix . 'fct_order_items';
		$table_posts = $wpdb->posts;
		
		$items = $wpdb->get_results( $wpdb->prepare(
			"SELECT oi.*, p.post_title as product_name
			FROM {$table_items} oi
			LEFT JOIN {$table_posts} p ON oi.post_id = p.ID
			WHERE oi.order_id = %d
			ORDER BY oi.cart_index ASC",
			$order_id
		) );

		$order_items = [];
		foreach ( $items as $item ) {
			// Get product thumbnail
			$thumbnail_id = get_post_thumbnail_id( $item->post_id );
			$image = $thumbnail_id ? wp_get_attachment_image_url( $thumbnail_id, 'thumbnail' ) : '';

			// Convert prices from cents to yuan
			$unit_price = $this->convert_price( $item->unit_price ?? 0, $order->currency ?? 'TWD' );
			$line_total = $this->convert_price( $item->line_total ?? 0, $order->currency ?? 'TWD' );

			$order_items[] = [
				'id' => $item->id,
				'product_id' => $item->post_id,
				'name' => $item->post_title ?: $item->title ?: 'Unknown Product',
				'variation_title' => $item->title ?? '',
				'quantity' => (int) ( $item->quantity ?? 1 ),
				'unit_price' => $unit_price,
				'formatted_unit_price' => 'NT$ ' . number_format( $unit_price, 0 ),
				'line_total' => $line_total,
				'formatted_line_total' => 'NT$ ' . number_format( $line_total, 0 ),
				'image' => $image,
			];
		}

		return [
			'id' => $order->id,
			'order_number' => 'FC-' . $order->id,
			'customer_name' => trim( ( $order->first_name ?? '' ) . ' ' . ( $order->last_name ?? '' ) ),
			'customer_email' => $order->email ?? '',
			'customer_phone' => $customer_phone,
			'customer_address' => $customer_address,
			'payment_status' => $order->payment_status ?? '',
			'shipping_status' => $order->shipping_status ?? '',
			'payment_method' => $payment_method,
			'total_amount' => $total_amount,
			'formatted_total' => 'NT$ ' . number_format( $total_amount, 0 ),
			'currency' => $order->currency ?? 'TWD',
			'created_at' => $order->created_at,
			'items' => $order_items,
		];
	}

	/**
	 * Delete order
	 *
	 * @param int $user_id User ID
	 * @param int $order_id Order ID
	 * @return bool True on success, false on failure
	 */
	public function deleteOrder( $user_id, $order_id ) {
		global $wpdb;

		// Check if order exists and user has permission
		$order = $this->getOrder( $user_id, $order_id );
		if ( $order === null ) {
			return false;
		}

		$table_orders = $wpdb->prefix . 'fct_orders';
		$table_items = $wpdb->prefix . 'fct_order_items';
		$table_addresses = $wpdb->prefix . 'fct_order_addresses';
		$table_meta = $wpdb->prefix . 'fct_order_meta';

		// Check if order status allows deletion (completed orders should not be deleted)
		$order_status = $wpdb->get_var( $wpdb->prepare(
			"SELECT status FROM {$table_orders} WHERE id = %d",
			$order_id
		) );

		if ( $order_status === 'completed' ) {
			return false; // Cannot delete completed orders
		}

		// Delete order-related data
		$wpdb->delete( $table_meta, [ 'order_id' => $order_id ], [ '%d' ] );
		$wpdb->delete( $table_addresses, [ 'order_id' => $order_id ], [ '%d' ] );
		$wpdb->delete( $table_items, [ 'order_id' => $order_id ], [ '%d' ] );
		$wpdb->delete( $table_orders, [ 'id' => $order_id ], [ '%d' ] );

		return true;
	}

	/**
	 * Update customer information
	 *
	 * @param int $user_id User ID
	 * @param int $order_id Order ID
	 * @param array $data Customer data (name, phone, email, address)
	 * @return bool True on success, false on failure
	 */
	public function updateCustomerInfo( $user_id, $order_id, $data ) {
		global $wpdb;

		// Check if order exists and user has permission
		$order = $this->getOrder( $user_id, $order_id );
		if ( $order === null ) {
			return false;
		}

		$table_addresses = $wpdb->prefix . 'fct_order_addresses';
		$table_customers = $wpdb->prefix . 'fct_customers';
		$table_orders = $wpdb->prefix . 'fct_orders';

		// Get shipping address (or create if not exists)
		$shipping_address = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table_addresses} WHERE order_id = %d AND type = 'shipping' LIMIT 1",
			$order_id
		) );

		// Update or create shipping address
		$address_data = [
			'order_id' => $order_id,
			'type' => 'shipping',
		];

		// Parse name into first_name and last_name
		$name = sanitize_text_field( $data['name'] ?? '' );
		$name_parts = explode( ' ', $name, 2 );
		$first_name = $name_parts[0] ?? '';
		$last_name = $name_parts[1] ?? '';

		// Parse address (assuming format: "郵遞區號 地址")
		$address = sanitize_text_field( $data['address'] ?? '' );
		$address_parts = explode( ' ', $address, 2 );
		$postcode = $address_parts[0] ?? '';
		$address_1 = $address_parts[1] ?? $address;

		$address_data['first_name'] = $first_name;
		$address_data['last_name'] = $last_name;
		$address_data['postcode'] = $postcode;
		$address_data['address_1'] = $address_1;
		$address_data['meta'] = json_encode( [
			'phone' => sanitize_text_field( $data['phone'] ?? '' ),
		] );

		if ( $shipping_address ) {
			// Update existing address
			$wpdb->update(
				$table_addresses,
				$address_data,
				[ 'id' => $shipping_address->id ],
				[ '%d', '%s', '%s', '%s', '%s', '%s', '%s' ],
				[ '%d' ]
			);
		} else {
			// Create new address
			$wpdb->insert( $table_addresses, $address_data, [ '%d', '%s', '%s', '%s', '%s', '%s', '%s' ] );
		}

		// Update customer email in orders table if provided
		if ( ! empty( $data['email'] ) ) {
			$wpdb->update(
				$table_orders,
				[ 'email' => sanitize_email( $data['email'] ) ],
				[ 'id' => $order_id ],
				[ '%s' ],
				[ '%d' ]
			);
		}

		return true;
	}
}
