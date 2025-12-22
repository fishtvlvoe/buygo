<?php
/**
 * ShippingController
 *
 * [AI Context]
 * - Handles shipping management API requests
 * - Returns shipping list and handles order merging
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

class ShippingController extends BaseController {

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
		register_rest_route( $this->namespace, '/shipping', [
			[
				'methods' => 'GET',
				'callback' => [ $this, 'index' ],
				'permission_callback' => [ $this, 'check_read_permission' ],
			],
		] );

		register_rest_route( $this->namespace, '/shipping/export-csv', [
			[
				'methods' => 'GET',
				'callback' => [ $this, 'export_csv' ],
				'permission_callback' => [ $this, 'check_read_permission' ],
			],
		] );

		register_rest_route( $this->namespace, '/shipping/merged/(?P<id>\d+)', [
			[
				'methods' => 'GET',
				'callback' => [ $this, 'get_merged_order' ],
				'permission_callback' => [ $this, 'check_read_permission' ],
			],
		] );

		register_rest_route( $this->namespace, '/shipping/unmerge/(?P<id>\d+)', [
			[
				'methods' => 'POST',
				'callback' => [ $this, 'unmerge_order' ],
				'permission_callback' => [ $this, 'check_write_permission' ],
			],
		] );
	}

	/**
	 * Get shipping list
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
			'shipping_status' => $request->get_param( 'status' ) ?: '',
			'search' => $request->get_param( 'search' ) ?: '',
		];

		try {
			// Get orders with shipping info
			$orders_data = $this->orders_service->getOrders( $user_id, $args );

			// Get all merged order IDs to filter out already merged orders
			global $wpdb;
			$table_merged = $wpdb->prefix . 'buygo_merged_orders';
			$merged_order_ids = [];
			$merged_records = $wpdb->get_results( "SELECT original_order_ids FROM {$table_merged}" );
			foreach ( $merged_records as $record ) {
				$original_ids = json_decode( $record->original_order_ids, true );
				if ( is_array( $original_ids ) ) {
					$merged_order_ids = array_merge( $merged_order_ids, $original_ids );
				}
			}
			$merged_order_ids = array_unique( $merged_order_ids );

			// Enrich with customer info and merge capability
			$filtered_orders = [];
			foreach ( $orders_data['orders'] as $order ) {
				// Skip orders that are already merged
				if ( in_array( $order['id'], $merged_order_ids ) ) {
					continue;
				}

				$order['can_merge'] = true; // Can be merged if not already merged
				$order['can_select'] = true; // Can be selected for operations
				$order['is_merged'] = false; // Mark as regular order
				// Get customer info from order
				$order_detail = $this->orders_service->getOrder( $user_id, $order['id'] );
				if ( $order_detail ) {
					$order['customer_info'] = [
						'name' => $order_detail['customer_name'] ?? '',
						'email' => $order_detail['customer_email'] ?? '',
					];
				}
				$filtered_orders[] = $order;
			}
			$orders_data['orders'] = $filtered_orders;

			// Get merged orders
			$role_manager = \BuyGo\Core\App::instance()->make( \BuyGo\Core\Services\RoleManager::class );
			$is_admin = current_user_can( 'manage_options' ) || $role_manager->is_admin( $user_id );
			$is_seller = $role_manager->is_seller( $user_id );

			$merged_orders = [];
			if ( $is_admin || $is_seller ) {
				// Get merged orders
				global $wpdb;
				$table = $wpdb->prefix . 'buygo_merged_orders';
				
				if ( $is_admin ) {
					// Admin sees all merged orders
					$merged_orders_list = $wpdb->get_results(
						"SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 100"
					);
				} else {
					// Seller sees own merged orders
					$merged_orders_list = $wpdb->get_results( $wpdb->prepare(
						"SELECT * FROM {$table} WHERE seller_id = %d ORDER BY created_at DESC LIMIT 100",
						$user_id
					) );
				}

				foreach ( $merged_orders_list as $merged_order ) {
					// Decode JSON if needed
					if ( is_string( $merged_order->original_order_ids ?? null ) ) {
						$merged_order->original_order_ids = json_decode( $merged_order->original_order_ids, true );
					}
					
					// Get customer info - ensure we get actual customer data
					$customer_info = $this->merge_order_service->getCustomerInfo( $merged_order->customer_id );
					
					// If customer info is empty, try to get from first original order
					if ( empty( $customer_info['name'] ) && ! empty( $merged_order->original_order_ids ) && is_array( $merged_order->original_order_ids ) ) {
						$first_order_id = $merged_order->original_order_ids[0];
						$first_order = $this->orders_service->getOrder( $user_id, $first_order_id );
						if ( $first_order ) {
							$customer_info['name'] = $first_order['customer_name'] ?? '';
							$customer_info['email'] = $first_order['customer_email'] ?? '';
						}
					}
					
					$merged_orders[] = [
						'id' => 'merged-' . $merged_order->id,
						'merged_id' => $merged_order->id,
						'order_number' => '合併訂單 #' . $merged_order->id,
						'is_merged' => true,
						'can_merge' => false, // Cannot merge again
						'can_select' => true, // Can be selected for edit/delete operations
						'original_order_ids' => $merged_order->original_order_ids ?? [],
						'customer_name' => $customer_info['name'] ?? '',
						'customer_email' => $customer_info['email'] ?? '',
						'customer_info' => $customer_info,
						'shipping_method' => $this->get_shipping_method_label( $merged_order->shipping_method ?? 'standard' ),
						'shipping_status' => $merged_order->shipping_status ?? 'unshipped',
						'total_amount' => (float) $merged_order->total_amount,
						'formatted_total' => 'NT$ ' . number_format( (float) $merged_order->total_amount, 0 ),
						'created_at' => $merged_order->created_at,
					];
				}
			}

			// Combine regular orders and merged orders
			$all_orders = array_merge( $orders_data['orders'], $merged_orders );

			// Sort by created_at DESC
			usort( $all_orders, function( $a, $b ) {
				$date_a = $a['created_at'] ?? '';
				$date_b = $b['created_at'] ?? '';
				return strcmp( $date_b, $date_a );
			} );

			$orders_data['orders'] = $all_orders;

			return $this->success( $orders_data );
		} catch ( \Exception $e ) {
			return $this->error( $e->getMessage(), 500 );
		}
	}

	/**
	 * Export shipping orders to CSV
	 *
	 * @param WP_REST_Request $request
	 * @return void (sends CSV file directly)
	 */
	public function export_csv( WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_die( 'User not authenticated', 401 );
		}

		$args = [
			'page' => 1,
			'per_page' => 1000, // Get all orders for export
			'shipping_status' => $request->get_param( 'status' ) ?: '',
			'search' => $request->get_param( 'search' ) ?: '',
		];

		try {
			// Get orders with shipping info (same logic as index)
			$orders_data = $this->orders_service->getOrders( $user_id, $args );

			// Enrich with customer info, address, phone, and products
			global $wpdb;
			$table_addresses = $wpdb->prefix . 'fct_order_addresses';
			$table_items = $wpdb->prefix . 'fct_order_items';
			$table_posts = $wpdb->posts;

			foreach ( $orders_data['orders'] as &$order ) {
				$order['is_merged'] = false;
				$order_detail = $this->orders_service->getOrder( $user_id, $order['id'] );
				
				if ( $order_detail ) {
					$order['customer_info'] = [
						'name' => $order_detail['customer_name'] ?? '',
						'email' => $order_detail['customer_email'] ?? '',
					];

					// Get shipping address and phone
					$shipping_address = $wpdb->get_row( $wpdb->prepare(
						"SELECT * FROM {$table_addresses} WHERE order_id = %d AND type = 'shipping' LIMIT 1",
						$order['id']
					) );

					if ( $shipping_address ) {
						$meta = $shipping_address->meta ? json_decode( $shipping_address->meta, true ) : [];
						$order['customer_phone'] = $meta['phone'] ?? '';
						$order['customer_address'] = trim( 
							( $shipping_address->address_1 ?? '' ) . ' ' . 
							( $shipping_address->address_2 ?? '' ) . ' ' .
							( $shipping_address->city ?? '' ) . ' ' .
							( $shipping_address->state ?? '' ) . ' ' .
							( $shipping_address->postcode ?? '' )
						);
					} else {
						// Fallback: try billing address
						$billing_address = $wpdb->get_row( $wpdb->prepare(
							"SELECT * FROM {$table_addresses} WHERE order_id = %d AND type = 'billing' LIMIT 1",
							$order['id']
						) );
						if ( $billing_address ) {
							$meta = $billing_address->meta ? json_decode( $billing_address->meta, true ) : [];
							$order['customer_phone'] = $meta['phone'] ?? '';
							$order['customer_address'] = trim( 
								( $billing_address->address_1 ?? '' ) . ' ' . 
								( $billing_address->address_2 ?? '' ) . ' ' .
								( $billing_address->city ?? '' ) . ' ' .
								( $billing_address->state ?? '' ) . ' ' .
								( $billing_address->postcode ?? '' )
							);
						}
					}

					// Get order items (products)
					$items = $wpdb->get_results( $wpdb->prepare(
						"SELECT oi.*, p.post_title as product_name
						FROM {$table_items} oi
						LEFT JOIN {$table_posts} p ON oi.post_id = p.ID
						WHERE oi.order_id = %d
						ORDER BY oi.cart_index ASC",
						$order['id']
					) );

					$product_list = [];
					foreach ( $items as $item ) {
						$product_name = $item->product_name ?: $item->title ?: 'Unknown Product';
						$variation_title = $item->title ?? '';
						$quantity = (int) ( $item->quantity ?? 1 );
						
						if ( $variation_title && $variation_title !== $product_name ) {
							$product_list[] = $product_name . ' - ' . $variation_title . ' x' . $quantity;
						} else {
							$product_list[] = $product_name . ' x' . $quantity;
						}
					}
					$order['products'] = implode( '; ', $product_list );
				}
			}

			// Get merged orders
			$role_manager = \BuyGo\Core\App::instance()->make( \BuyGo\Core\Services\RoleManager::class );
			$is_admin = current_user_can( 'manage_options' ) || $role_manager->is_admin( $user_id );
			$is_seller = $role_manager->is_seller( $user_id );

			$merged_orders = [];
			if ( $is_admin || $is_seller ) {
				global $wpdb;
				$table = $wpdb->prefix . 'buygo_merged_orders';
				
				if ( $is_admin ) {
					$merged_orders_list = $wpdb->get_results(
						"SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 1000"
					);
				} else {
					$merged_orders_list = $wpdb->get_results( $wpdb->prepare(
						"SELECT * FROM {$table} WHERE seller_id = %d ORDER BY created_at DESC LIMIT 1000",
						$user_id
					) );
				}

				foreach ( $merged_orders_list as $merged_order ) {
					// Decode JSON if needed
					if ( is_string( $merged_order->original_order_ids ?? null ) ) {
						$merged_order->original_order_ids = json_decode( $merged_order->original_order_ids, true );
					}
					
					$customer_info = $this->merge_order_service->getCustomerInfo( $merged_order->customer_id );
					
					// If customer info is empty, try to get from first original order
					if ( empty( $customer_info['name'] ) && ! empty( $merged_order->original_order_ids ) && is_array( $merged_order->original_order_ids ) ) {
						$first_order_id = $merged_order->original_order_ids[0];
						$first_order = $this->orders_service->getOrder( $user_id, $first_order_id );
						if ( $first_order ) {
							$customer_info['name'] = $first_order['customer_name'] ?? '';
							$customer_info['email'] = $first_order['customer_email'] ?? '';
						}
					}

					// Get products from all original orders
					$all_products = [];
					if ( ! empty( $merged_order->original_order_ids ) && is_array( $merged_order->original_order_ids ) ) {
						foreach ( $merged_order->original_order_ids as $original_order_id ) {
							$original_order = $this->orders_service->getOrder( $user_id, $original_order_id );
							if ( $original_order && ! empty( $original_order['items'] ) ) {
								foreach ( $original_order['items'] as $item ) {
									$product_name = $item['name'] ?? '';
									$variation_title = $item['variation_title'] ?? '';
									$quantity = $item['quantity'] ?? 1;
									
									if ( $variation_title && $variation_title !== $product_name ) {
										$all_products[] = $product_name . ' - ' . $variation_title . ' x' . $quantity;
									} else {
										$all_products[] = $product_name . ' x' . $quantity;
									}
								}
							}
						}
					}

					// Get phone and address from first original order
					$customer_phone = '';
					$customer_address = '';
					if ( ! empty( $merged_order->original_order_ids ) && is_array( $merged_order->original_order_ids ) ) {
						$first_order_id = $merged_order->original_order_ids[0];
						$shipping_address = $wpdb->get_row( $wpdb->prepare(
							"SELECT * FROM {$table_addresses} WHERE order_id = %d AND type = 'shipping' LIMIT 1",
							$first_order_id
						) );
						if ( $shipping_address ) {
							$meta = $shipping_address->meta ? json_decode( $shipping_address->meta, true ) : [];
							$customer_phone = $meta['phone'] ?? '';
							$customer_address = trim( 
								( $shipping_address->address_1 ?? '' ) . ' ' . 
								( $shipping_address->address_2 ?? '' ) . ' ' .
								( $shipping_address->city ?? '' ) . ' ' .
								( $shipping_address->state ?? '' ) . ' ' .
								( $shipping_address->postcode ?? '' )
							);
						}
					}
					
					$merged_orders[] = [
						'id' => 'merged-' . $merged_order->id,
						'merged_id' => $merged_order->id,
						'order_number' => '合併訂單 #' . $merged_order->id,
						'is_merged' => true,
						'can_merge' => false, // Cannot merge again, but can be selected for edit/delete
						'can_select' => true, // Can be selected for operations
						'customer_name' => $customer_info['name'] ?? '',
						'customer_email' => $customer_info['email'] ?? '',
						'customer_phone' => $customer_phone,
						'customer_address' => $customer_address,
						'products' => implode( '; ', $all_products ),
						'shipping_method' => $this->get_shipping_method_label( $merged_order->shipping_method ?? 'standard' ),
						'shipping_status' => $merged_order->shipping_status ?? 'unshipped',
						'total_amount' => (float) $merged_order->total_amount,
						'formatted_total' => 'NT$ ' . number_format( (float) $merged_order->total_amount, 0 ),
						'created_at' => $merged_order->created_at,
					];
				}
			}

			$all_orders = array_merge( $orders_data['orders'], $merged_orders );

			// Generate CSV
			// Use English filename to avoid encoding issues
			$filename = 'shipping_orders_' . date( 'Y-m-d_His' ) . '.csv';
			
			// Clear any output buffers
			if ( ob_get_level() ) {
				ob_end_clean();
			}
			
			// Set headers for CSV download
			nocache_headers();
			header( 'Content-Type: text/csv; charset=UTF-8' );
			header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
			header( 'Content-Transfer-Encoding: binary' );
			header( 'Pragma: public' );
			header( 'Expires: 0' );
			header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );

			// Add BOM for Excel UTF-8 support
			echo "\xEF\xBB\xBF";

			$output = fopen( 'php://output', 'w' );

			// CSV Headers
			fputcsv( $output, [
				'訂單編號',
				'類型',
				'買家姓名',
				'買家 Email',
				'買家電話',
				'買家地址',
				'產品',
				'運送方式',
				'運送狀態',
				'訂單總額',
				'建立日期',
			] );

			// CSV Data
			foreach ( $all_orders as $order ) {
				$type = $order['is_merged'] ? '合併訂單' : '一般訂單';
				$shipping_status_label = $this->get_shipping_status_label( $order['shipping_status'] ?? '' );

				fputcsv( $output, [
					$order['order_number'] ?? '',
					$type,
					$order['customer_name'] ?? $order['customer_info']['name'] ?? '',
					$order['customer_email'] ?? $order['customer_info']['email'] ?? '',
					$order['customer_phone'] ?? '',
					$order['customer_address'] ?? '',
					$order['products'] ?? '',
					$this->get_shipping_method_label( $order['shipping_method'] ?? 'standard' ),
					$shipping_status_label,
					$order['formatted_total'] ?? 'NT$ 0',
					$order['created_at'] ?? '',
				] );
			}

			fclose( $output );
			exit;
		} catch ( \Exception $e ) {
			wp_die( 'Export failed: ' . $e->getMessage(), 500 );
		}
	}

	/**
	 * Get merged order details
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_merged_order( WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return $this->error( 'User not authenticated', 401 );
		}

		$merged_order_id = absint( $request->get_param( 'id' ) );
		$merged_order = $this->merge_order_service->getMergedOrder( $merged_order_id );

		if ( ! $merged_order ) {
			return $this->error( 'Merged order not found', 404 );
		}

		// Get customer info
		$customer_info = $this->merge_order_service->getCustomerInfo( $merged_order->customer_id );

		// If customer info is empty, try to get from first original order
		if ( empty( $customer_info['name'] ) && ! empty( $merged_order->original_order_ids ) && is_array( $merged_order->original_order_ids ) ) {
			$first_order_id = $merged_order->original_order_ids[0];
			$first_order = $this->orders_service->getOrder( $user_id, $first_order_id );
			if ( $first_order ) {
				$customer_info['name'] = $first_order['customer_name'] ?? '';
				$customer_info['email'] = $first_order['customer_email'] ?? '';
			}
		}

		// Get original orders details
		$original_orders = [];
		if ( ! empty( $merged_order->original_order_ids ) && is_array( $merged_order->original_order_ids ) ) {
			foreach ( $merged_order->original_order_ids as $order_id ) {
				$order = $this->orders_service->getOrder( $user_id, $order_id );
				if ( $order ) {
					$original_orders[] = $order;
				}
			}
		}

		$data = [
			'id' => $merged_order->id,
			'merged_order_id' => $merged_order->id,
			'order_number' => '合併訂單 #' . $merged_order->id,
			'customer_info' => $customer_info,
			'shipping_method' => $this->get_shipping_method_label( $merged_order->shipping_method ?? 'standard' ),
			'shipping_status' => $merged_order->shipping_status ?? 'unshipped',
			'total_amount' => (float) $merged_order->total_amount,
			'formatted_total' => 'NT$ ' . number_format( (float) $merged_order->total_amount, 0 ),
			'original_order_ids' => $merged_order->original_order_ids ?? [],
			'original_orders' => $original_orders,
			'created_at' => $merged_order->created_at,
		];

		return $this->success( $data );
	}

	/**
	 * Get shipping method label (convert English to Chinese)
	 *
	 * @param string $method Shipping method code
	 * @return string Shipping method label
	 */
	private function get_shipping_method_label( $method ) {
		$labels = [
			'standard' => '標準運送',
			'express' => '快速運送',
			'overnight' => '隔夜送達',
			'pickup' => '自取',
			'free' => '免運',
		];

		return $labels[ strtolower( $method ) ] ?? $method;
	}

	/**
	 * Get shipping status label
	 *
	 * @param string $status Status code
	 * @return string Status label
	 */
	private function get_shipping_status_label( $status ) {
		$labels = [
			'unshipped' => '未出貨',
			'shipped' => '已出貨',
			'delivered' => '已送達',
		];
		return $labels[ $status ] ?? $status;
	}

	/**
	 * Unmerge a merged order
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function unmerge_order( WP_REST_Request $request ) {
		// Permission is already checked by permission_callback
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return $this->error( 'User not authenticated', 401 );
		}

		$merged_order_id = absint( $request->get_param( 'id' ) );

		// Unmerge order using MergeOrderService
		$result = $this->merge_order_service->unmergeOrder( $user_id, $merged_order_id );
		if ( $result === false ) {
			return $this->error( 'Failed to unmerge order', 500 );
		}

		return $this->success( [
			'message' => 'Order unmerged successfully',
			'original_order_ids' => $result['original_order_ids'] ?? [],
		] );
	}
}
