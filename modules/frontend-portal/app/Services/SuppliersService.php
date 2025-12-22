<?php
/**
 * SuppliersService
 *
 * [AI Context]
 * - Handles supplier data and settlement calculations
 * - Calculates payable amounts based on order item cost snapshots
 * - Returns product-level sales summary (not order-level) for supplier detail view
 *
 * [Constraints]
 * - Must use WordPress $wpdb for database operations
 * - Must check user permissions using BuyGo RoleManager
 * - Must sanitize all input data
 * - Prices are stored in "yuan" (元) for TWD
 */

namespace BuyGo\Modules\FrontendPortal\App\Services;

use BuyGo\Core\App;
use BuyGo\Core\Services\RoleManager;
use BuyGo\Modules\FrontendPortal\App\Models\Supplier;
use BuyGo\Modules\FrontendPortal\App\Models\SupplierSettlement;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SuppliersService {

	/**
	 * Get suppliers list with payable amounts
	 *
	 * @param int $user_id User ID
	 * @param array $args Query arguments
	 * @return array Suppliers list with pagination and statistics
	 */
	public function getSuppliers( $user_id, $args = [] ) {
		global $wpdb;

		$role_manager = App::instance()->make( RoleManager::class );

		// Check permissions
		// Allow administrator, BuyGo admin, seller, and helper
		// Also allow users who have created suppliers (even if not assigned seller role yet)
		$has_permission = $role_manager->is_seller( $user_id ) || 
		                 $role_manager->is_helper( $user_id ) || 
		                 current_user_can( 'manage_options' ) || 
		                 buygo_is_admin();
		
		// If user doesn't have standard permission, check if they have any suppliers
		if ( ! $has_permission ) {
			global $wpdb;
			$table = $wpdb->prefix . 'buygo_suppliers';
			$has_suppliers = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE seller_id = %d",
				$user_id
			) );
			
			// If user has suppliers, allow them to see (they created them)
			if ( ! $has_suppliers ) {
				return [
					'suppliers' => [],
					'pagination' => [
						'total' => 0,
						'page' => 1,
						'per_page' => 20,
						'total_pages' => 0,
					],
				];
			}
		}

		$defaults = [
			'per_page' => 20,
			'page' => 1,
			'search' => '',
			'status' => 'all',
		];

		$args = wp_parse_args( $args, $defaults );

		// Get seller ID (admin sees all, seller sees own)
		$seller_id = null;
		$is_admin = current_user_can( 'manage_options' ) || buygo_is_admin();
		
		if ( $role_manager->is_seller( $user_id ) ) {
			$seller_id = $user_id;
		} elseif ( $role_manager->is_helper( $user_id ) ) {
			// Helper sees authorized sellers' suppliers
			// TODO: Implement helper authorization logic
			$seller_id = $user_id; // Placeholder
		} elseif ( $is_admin ) {
			// Admin can see all, seller_id remains null
			$seller_id = null;
		} else {
			// If user is not seller/helper/admin but has suppliers, allow them to see their own
			// This handles cases where suppliers were created before role assignment
			$seller_id = $user_id;
		}

		// Get suppliers
		$supplier_args = [
			'per_page' => $args['per_page'],
			'page' => $args['page'],
			'status' => $args['status'],
		];

		if ( $is_admin ) {
			// Admin sees all suppliers (query all suppliers, not filtered by seller_id)
			global $wpdb;
			$table = $wpdb->prefix . 'buygo_suppliers';
			$offset = ( $supplier_args['page'] - 1 ) * $supplier_args['per_page'];
			
			$where = [];
			if ( $supplier_args['status'] !== 'all' ) {
				$where[] = $wpdb->prepare( 'status = %s', $supplier_args['status'] );
			}
			
			$where_clause = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';
			
			$suppliers = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM {$table} {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$supplier_args['per_page'],
				$offset
			) );
		} elseif ( $seller_id ) {
			$suppliers = Supplier::getBySellerId( $seller_id, $supplier_args );
		} else {
			// No permission or no seller_id
			$suppliers = [];
		}

		// Calculate payable amounts for each supplier
		foreach ( $suppliers as $supplier ) {
			// For admin, use the supplier's seller_id; for seller, use their own user_id
			$calc_seller_id = $is_admin ? ( $supplier->seller_id ?? $user_id ) : ( $seller_id ?: $user_id );
			$supplier->payable_amount = $this->calculatePayableAmount( $supplier->id, $calc_seller_id );
			$supplier->product_count = $this->getProductCount( $supplier->id );
			$supplier->sold_qty = $this->getSoldQuantity( $supplier->id, $calc_seller_id );
		}

		// Get total count
		if ( $is_admin ) {
			global $wpdb;
			$table = $wpdb->prefix . 'buygo_suppliers';
			$where = [];
			if ( $supplier_args['status'] !== 'all' ) {
				$where[] = $wpdb->prepare( 'status = %s', $supplier_args['status'] );
			}
			$where_clause = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} {$where_clause}" );
		} else {
			// Simplified count for seller (should implement proper count query)
			$total = count( $suppliers );
		}

		return [
			'suppliers' => $suppliers,
			'pagination' => [
				'total' => $total,
				'page' => $args['page'],
				'per_page' => $args['per_page'],
				'total_pages' => ceil( $total / $args['per_page'] ),
			],
		];
	}

	/**
	 * Get supplier detail with product sales summary
	 *
	 * @param int $supplier_id Supplier ID
	 * @param int $user_id User ID
	 * @param string $period_start Start date (Y-m-d)
	 * @param string $period_end End date (Y-m-d)
	 * @return array Supplier detail with product sales summary
	 */
	public function getSupplierDetail( $supplier_id, $user_id, $period_start = null, $period_end = null ) {
		global $wpdb;

		$role_manager = App::instance()->make( RoleManager::class );

		// Check permissions
		if ( ! $role_manager->is_seller( $user_id ) && ! $role_manager->is_helper( $user_id ) && ! current_user_can( 'manage_options' ) && ! buygo_is_admin() ) {
			return null;
		}

		$supplier = Supplier::find( $supplier_id );
		if ( ! $supplier ) {
			return null;
		}

		// Get seller ID
		$seller_id = null;
		if ( $role_manager->is_seller( $user_id ) ) {
			$seller_id = $user_id;
		}

		// Default to current month if period not specified
		if ( ! $period_start ) {
			$period_start = date( 'Y-m-01' );
		}
		if ( ! $period_end ) {
			$period_end = date( 'Y-m-t' );
		}

		// Get all products for this supplier (even if no sales)
		$all_products = $this->getAllProducts( $supplier_id );

		// Get product sales summary (product-level, not order-level)
		$product_summary = $this->getProductSalesSummary( $supplier_id, $seller_id ?: $user_id, $period_start, $period_end );

		// Create a map of product sales by product_id + variation_id
		$sales_map = [];
		foreach ( $product_summary as $product ) {
			$key = $product->product_id . '_' . ( $product->variation_id ?: 0 );
			$sales_map[ $key ] = $product;
		}

		// Merge all products with sales data
		$products_with_sales = [];
		foreach ( $all_products as $product ) {
			$key = $product->product_id . '_' . ( $product->variation_id ?: 0 );
			$sales_data = $sales_map[ $key ] ?? null;

			$products_with_sales[] = [
				'product_id' => $product->product_id,
				'variation_id' => $product->variation_id ?: 0,
				'product_name' => $product->product_name,
				'variation_title' => $product->variation_title ?: '預設',
				'product_image' => $product->product_image ?? '',
				'product_price' => (float) ( $product->product_price ?? 0 ),
				'has_sales' => $sales_data !== null,
				'total_qty' => $sales_data ? (int) $sales_data->total_qty : 0,
				'cost_per_unit' => $sales_data ? (float) $sales_data->cost_per_unit : (float) ( $product->cost_price ?: 0 ),
				'total_cost' => $sales_data ? (float) $sales_data->total_cost : 0,
			];
		}

		// Calculate total payable
		$total_payable = 0;
		foreach ( $product_summary as $product ) {
			$total_payable += (float) $product->total_cost;
		}

		return [
			'supplier' => $supplier,
			'period_start' => $period_start,
			'period_end' => $period_end,
			'product_summary' => $product_summary,
			'all_products' => $products_with_sales,
			'total_payable' => $total_payable,
		];
	}

	/**
	 * Get all products associated with a supplier
	 *
	 * @param int $supplier_id Supplier ID
	 * @return array All products with supplier_id meta
	 */
	protected function getAllProducts( $supplier_id ) {
		global $wpdb;

		// Get all products (FluentCart uses 'fluent-products' post_type)
		$query = "SELECT DISTINCT
				p.ID as product_id,
				p.post_title as product_name,
				0 as variation_id,
				'' as variation_title,
				CAST(pm_cost.meta_value AS DECIMAL(10,2)) as cost_price
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm_supplier ON p.ID = pm_supplier.post_id
				AND pm_supplier.meta_key = '_buygo_supplier_id'
				AND pm_supplier.meta_value = %d
			LEFT JOIN {$wpdb->postmeta} pm_cost ON p.ID = pm_cost.post_id
				AND pm_cost.meta_key = '_buygo_cost_price'
			WHERE p.post_type = 'fluent-products'
				AND p.post_status IN ('publish', 'draft', 'pending')
			ORDER BY p.post_title";

		$query = $wpdb->prepare( $query, $supplier_id );
		$results = $wpdb->get_results( $query );

		// Add product image URL and other details
		foreach ( $results as $result ) {
			$result->product_image = get_the_post_thumbnail_url( $result->product_id, 'thumbnail' );
			if ( ! $result->product_image ) {
				// Try to get from FluentCart product detail meta
				$detail_meta = get_post_meta( $result->product_id, 'fluent-products-detail', true );
				if ( $detail_meta && is_array( $detail_meta ) && isset( $detail_meta['featured_media']['url'] ) ) {
					$result->product_image = $detail_meta['featured_media']['url'];
				} else {
					// Try to get from fct_product_details table
					$details_table = $wpdb->prefix . 'fct_product_details';
					$default_media = $wpdb->get_var( $wpdb->prepare(
						"SELECT default_media FROM {$details_table} WHERE post_id = %d",
						$result->product_id
					) );
					if ( $default_media ) {
						$media_data = json_decode( $default_media, true );
						if ( $media_data && isset( $media_data['url'] ) ) {
							$result->product_image = $media_data['url'];
						} elseif ( is_numeric( $default_media ) ) {
							$result->product_image = wp_get_attachment_image_url( (int) $default_media, 'thumbnail' );
						}
					}
					if ( ! $result->product_image ) {
						$result->product_image = ''; // No image
					}
				}
			}
			
			// Get product price from FluentCart product details table (stored in cents, need to divide by 100)
			$details_table = $wpdb->prefix . 'fct_product_details';
			$product_price = $wpdb->get_var( $wpdb->prepare(
				"SELECT min_price FROM {$details_table} WHERE post_id = %d",
				$result->product_id
			) );
			$result->product_price = $product_price ? (float) $product_price / 100 : 0;
		}

		// Also get variations if they exist
		$variations_table = $wpdb->prefix . 'fct_product_variations';
		$variations_query = "SELECT DISTINCT
				v.product_id,
				p.post_title as product_name,
				v.id as variation_id,
				v.title as variation_title,
				CAST(pm_cost.meta_value AS DECIMAL(10,2)) as cost_price
			FROM {$variations_table} v
			INNER JOIN {$wpdb->posts} p ON v.product_id = p.ID
			INNER JOIN {$wpdb->postmeta} pm_supplier ON p.ID = pm_supplier.post_id
				AND pm_supplier.meta_key = '_buygo_supplier_id'
				AND pm_supplier.meta_value = %d
			LEFT JOIN {$wpdb->postmeta} pm_cost ON p.ID = pm_cost.post_id
				AND pm_cost.meta_key = '_buygo_cost_price'
			WHERE p.post_type = 'fluent-products'
				AND p.post_status IN ('publish', 'draft', 'pending')
			ORDER BY p.post_title, v.title";

		$variations_query = $wpdb->prepare( $variations_query, $supplier_id );
		$variations = $wpdb->get_results( $variations_query );

		// Add product image URL and other details for variations
		foreach ( $variations as $variation ) {
			$variation->product_image = get_the_post_thumbnail_url( $variation->product_id, 'thumbnail' );
			if ( ! $variation->product_image ) {
				$detail_meta = get_post_meta( $variation->product_id, 'fluent-products-detail', true );
				if ( $detail_meta && is_array( $detail_meta ) && isset( $detail_meta['featured_media']['url'] ) ) {
					$variation->product_image = $detail_meta['featured_media']['url'];
				} else {
					$details_table = $wpdb->prefix . 'fct_product_details';
					$default_media = $wpdb->get_var( $wpdb->prepare(
						"SELECT default_media FROM {$details_table} WHERE post_id = %d",
						$variation->product_id
					) );
					if ( $default_media ) {
						$media_data = json_decode( $default_media, true );
						if ( $media_data && isset( $media_data['url'] ) ) {
							$variation->product_image = $media_data['url'];
						} elseif ( is_numeric( $default_media ) ) {
							$variation->product_image = wp_get_attachment_image_url( (int) $default_media, 'thumbnail' );
						}
					}
					if ( ! $variation->product_image ) {
						$variation->product_image = '';
					}
				}
			}
			
			// Get variation price from FluentCart variations table (stored in cents, need to divide by 100)
			$variation_price = $wpdb->get_var( $wpdb->prepare(
				"SELECT item_price FROM {$wpdb->prefix}fct_product_variations WHERE id = %d",
				$variation->variation_id
			) );
			$variation->product_price = $variation_price ? (float) $variation_price / 100 : 0;
		}

		// Merge results
		$all_results = array_merge( $results ?: [], $variations ?: [] );

		return $all_results;
	}

	/**
	 * Recalculate order costs for existing orders
	 * This function processes all completed orders and captures supplier costs
	 *
	 * @param int $seller_id Optional. Seller ID to filter orders. If null, processes all orders.
	 * @return array Statistics about the recalculation
	 */
	public function recalculateOrderCosts( $seller_id = null ) {
		global $wpdb;

		$order_items_table = $wpdb->prefix . 'fc_order_items';
		$order_itemmeta_table = $wpdb->prefix . 'fc_order_itemmeta';
		$orders_table = $wpdb->prefix . 'fc_orders';

		// Get all completed orders
		$where = [ "o.status = 'completed'" ];
		if ( $seller_id ) {
			$where[] = $wpdb->prepare( 'o.seller_id = %d', $seller_id );
		}

		$where_clause = 'WHERE ' . implode( ' AND ', $where );

		$orders = $wpdb->get_results( "SELECT o.id FROM {$orders_table} o {$where_clause}" );

		$stats = [
			'total_orders' => count( $orders ),
			'processed_orders' => 0,
			'processed_items' => 0,
			'updated_items' => 0,
			'errors' => [],
		];

		foreach ( $orders as $order ) {
			try {
				// Use the existing capture_order_costs method
				\BuyGo\Modules\FrontendPortal\App\Hooks\OrderCostSnapshot::capture_order_costs( $order->id );
				$stats['processed_orders']++;

				// Count items processed
				$items = $wpdb->get_results( $wpdb->prepare(
					"SELECT * FROM {$order_items_table} WHERE order_id = %d",
					$order->id
				) );
				$stats['processed_items'] += count( $items );
			} catch ( \Exception $e ) {
				$stats['errors'][] = [
					'order_id' => $order->id,
					'error' => $e->getMessage(),
				];
			}
		}

		// Count updated items (items with supplier_id meta)
		$stats['updated_items'] = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT order_item_id) FROM {$order_itemmeta_table} WHERE meta_key = '_buygo_supplier_id'"
		);

		return $stats;
	}

	/**
	 * Get product sales summary (product-level aggregation)
	 *
	 * @param int $supplier_id Supplier ID
	 * @param int $seller_id Seller ID
	 * @param string $period_start Start date
	 * @param string $period_end End date
	 * @return array Product sales summary
	 */
	protected function getProductSalesSummary( $supplier_id, $seller_id, $period_start, $period_end ) {
		global $wpdb;

		// Get all order items that belong to this supplier within the period
		// We need to join with FluentCart orders and order items
		$order_items_table = $wpdb->prefix . 'fc_order_items';
		$order_itemmeta_table = $wpdb->prefix . 'fc_order_itemmeta';
		$orders_table = $wpdb->prefix . 'fc_orders';

		// 先用 esc_sql 處理表名，避免 wpdb->prepare 誤判
		$order_items_table = esc_sql( $order_items_table );
		$order_itemmeta_table = esc_sql( $order_itemmeta_table );
		$orders_table = esc_sql( $orders_table );

		// Query: Get order items with supplier_id meta, grouped by product
		$query = "SELECT 
				oi.product_id,
				oi.variation_id,
				oi.product_name,
				oi.variation_title,
				SUM(oi.quantity) as total_qty,
				AVG(CAST(im_cost.meta_value AS DECIMAL(10,2))) as cost_per_unit,
				SUM(CAST(im_cost.meta_value AS DECIMAL(10,2)) * oi.quantity) as total_cost
			FROM {$order_items_table} oi
			INNER JOIN {$orders_table} o ON oi.order_id = o.id
			INNER JOIN {$order_itemmeta_table} im_supplier ON oi.id = im_supplier.order_item_id 
				AND im_supplier.meta_key = '_buygo_supplier_id' 
				AND im_supplier.meta_value = %d
			INNER JOIN {$order_itemmeta_table} im_cost ON oi.id = im_cost.order_item_id 
				AND im_cost.meta_key = '_buygo_cost_snapshot'
			WHERE o.status = 'completed'
				AND DATE(o.created_at) BETWEEN %s AND %s
				AND o.seller_id = %d
			GROUP BY oi.product_id, oi.variation_id, oi.product_name, oi.variation_title
			ORDER BY total_cost DESC";
		
		// 現在用 prepare 處理參數部分
		$query = $wpdb->prepare( $query, $supplier_id, $period_start, $period_end, $seller_id );

		$results = $wpdb->get_results( $query );

		return $results ?: [];
	}

	/**
	 * Calculate total payable amount for a supplier
	 *
	 * @param int $supplier_id Supplier ID
	 * @param int $seller_id Seller ID
	 * @return float Total payable amount
	 */
	protected function calculatePayableAmount( $supplier_id, $seller_id ) {
		global $wpdb;

		$order_items_table = $wpdb->prefix . 'fc_order_items';
		$order_itemmeta_table = $wpdb->prefix . 'fc_order_itemmeta';
		$orders_table = $wpdb->prefix . 'fc_orders';
		$settlements_table = $wpdb->prefix . 'buygo_supplier_settlements';

		// 先用 esc_sql 處理表名，避免 wpdb->prepare 誤判
		$order_items_table = esc_sql( $order_items_table );
		$order_itemmeta_table = esc_sql( $order_itemmeta_table );
		$orders_table = esc_sql( $orders_table );
		$settlements_table = esc_sql( $settlements_table );

		// Get sum of all unpaid costs for this supplier
		$query = "SELECT SUM(CAST(im_cost.meta_value AS DECIMAL(10,2)) * oi.quantity) as total
			FROM {$order_items_table} oi
			INNER JOIN {$orders_table} o ON oi.order_id = o.id
			INNER JOIN {$order_itemmeta_table} im_supplier ON oi.id = im_supplier.order_item_id 
				AND im_supplier.meta_key = '_buygo_supplier_id' 
				AND im_supplier.meta_value = %d
			INNER JOIN {$order_itemmeta_table} im_cost ON oi.id = im_cost.order_item_id 
				AND im_cost.meta_key = '_buygo_cost_snapshot'
			WHERE o.status = 'completed'
				AND o.seller_id = %d
				AND NOT EXISTS (
					SELECT 1 FROM {$settlements_table} ss
					WHERE ss.supplier_id = %d
						AND ss.status = 'settled'
						AND DATE(o.created_at) BETWEEN ss.period_start AND ss.period_end
				)";
		
		// 現在用 prepare 處理參數部分
		$query = $wpdb->prepare( $query, $supplier_id, $seller_id, $supplier_id );

		$result = $wpdb->get_var( $query );

		return (float) ( $result ?: 0 );
	}

	/**
	 * Get product count for a supplier
	 *
	 * @param int $supplier_id Supplier ID
	 * @return int Product count
	 */
	protected function getProductCount( $supplier_id ) {
		global $wpdb;

		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(DISTINCT post_id) 
			FROM {$wpdb->postmeta} 
			WHERE meta_key = '_buygo_supplier_id' 
				AND meta_value = %d",
			$supplier_id
		) );

		return (int) ( $count ?: 0 );
	}

	/**
	 * Get sold quantity for a supplier
	 *
	 * @param int $supplier_id Supplier ID
	 * @param int $seller_id Seller ID
	 * @return int Sold quantity
	 */
	protected function getSoldQuantity( $supplier_id, $seller_id ) {
		global $wpdb;

		$order_items_table = $wpdb->prefix . 'fc_order_items';
		$order_itemmeta_table = $wpdb->prefix . 'fc_order_itemmeta';
		$orders_table = $wpdb->prefix . 'fc_orders';

		// 先用 esc_sql 處理表名，避免 wpdb->prepare 誤判
		$order_items_table = esc_sql( $order_items_table );
		$order_itemmeta_table = esc_sql( $order_itemmeta_table );
		$orders_table = esc_sql( $orders_table );

		$query = "SELECT SUM(oi.quantity) as total
			FROM {$order_items_table} oi
			INNER JOIN {$orders_table} o ON oi.order_id = o.id
			INNER JOIN {$order_itemmeta_table} im_supplier ON oi.id = im_supplier.order_item_id 
				AND im_supplier.meta_key = '_buygo_supplier_id' 
				AND im_supplier.meta_value = %d
			WHERE o.status = 'completed'
				AND o.seller_id = %d";
		
		// 現在用 prepare 處理參數部分
		$query = $wpdb->prepare( $query, $supplier_id, $seller_id );

		$result = $wpdb->get_var( $query );

		return (int) ( $result ?: 0 );
	}

	/**
	 * Create settlement record
	 *
	 * @param int $supplier_id Supplier ID
	 * @param int $user_id User ID
	 * @param string $period_start Start date
	 * @param string $period_end End date
	 * @param string $notes Notes
	 * @return int|false Settlement ID or false on failure
	 */
	public function createSettlement( $supplier_id, $user_id, $period_start, $period_end, $notes = '' ) {
		// Get supplier detail to calculate total payable
		$detail = $this->getSupplierDetail( $supplier_id, $user_id, $period_start, $period_end );
		if ( ! $detail ) {
			return false;
		}

		$settlement_data = [
			'supplier_id' => $supplier_id,
			'seller_id' => get_current_user_id(),
			'period_start' => $period_start,
			'period_end' => $period_end,
			'total_payable' => $detail['total_payable'],
			'status' => 'pending',
			'notes' => $notes,
		];

		return SupplierSettlement::create( $settlement_data );
	}

	/**
	 * Mark settlement as settled
	 *
	 * @param int $settlement_id Settlement ID
	 * @return bool True on success, false on failure
	 */
	public function markSettlementAsSettled( $settlement_id ) {
		return SupplierSettlement::update( $settlement_id, [
			'status' => 'settled',
		] );
	}
}
