<?php
/**
 * ProductsService
 *
 * [AI Context]
 * - Handles product data retrieval from FluentCart
 * - Filters products by user role (admin sees all, seller sees own, helper sees authorized)
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

class ProductsService {

	/**
	 * Convert price from cents to yuan for TWD
	 *
	 * @param float $price_in_cents Price in cents
	 * @param string $currency Currency code (default: TWD)
	 * @return float Price in yuan
	 */
	protected function convert_price( $price_in_cents, $currency = 'TWD' ) {
		if ( $currency === 'TWD' ) {
			// Convert from cents to yuan
			return (float) $price_in_cents / 100;
		}
		// For other currencies, keep as is (or implement conversion logic if needed)
		return (float) $price_in_cents;
	}

	/**
	 * Get products list
	 *
	 * @param int $user_id User ID
	 * @param array $args Query arguments
	 * @return array Products list with pagination
	 */
	public function getProducts( $user_id, $args = [] ) {
		global $wpdb;

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return [
				'products' => [],
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
			'status' => 'all',
			'search' => '',
		];

		$args = wp_parse_args( $args, $defaults );
		$page = absint( $args['page'] );
		$per_page = absint( $args['per_page'] );
		$offset = ( $page - 1 ) * $per_page;

		$table_posts = $wpdb->posts;
		$where = [
			"p.post_type = 'fluent-products'",
			"p.post_status IN ('publish', 'draft', 'pending', 'private')",
		];

		// Status filter
		if ( $args['status'] && $args['status'] !== 'all' ) {
			if ( $args['status'] === 'publish' ) {
				$where[] = "p.post_status = 'publish'";
			} elseif ( $args['status'] === 'draft' ) {
				$where[] = "p.post_status = 'draft'";
			}
		}

		// Search filter
		if ( $args['search'] ) {
			$where[] = $wpdb->prepare(
				"(p.post_title LIKE %s OR p.ID = %d)",
				'%' . $wpdb->esc_like( $args['search'] ) . '%',
				intval( $args['search'] )
			);
		}

		// Permission check: filter by role
		$role_manager = App::instance()->make( RoleManager::class );
		$is_admin = current_user_can( 'manage_options' ) || $role_manager->is_admin( $user_id );
		$is_seller = $role_manager->is_seller( $user_id );
		$is_helper = $role_manager->is_helper( $user_id );

		if ( ! $is_admin ) {
			if ( $is_seller ) {
				// Seller: only see own products
				$where[] = $wpdb->prepare( "p.post_author = %d", $user_id );
			} elseif ( $is_helper ) {
				// Helper: see authorized sellers' products
				// TODO: Implement helper authorization logic
				// For now, return empty (will be implemented later)
				return [
					'products' => [],
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
					'products' => [],
					'pagination' => [
						'total' => 0,
						'page' => $page,
						'per_page' => $per_page,
						'total_pages' => 0,
					],
				];
			}
		}

		$where_clause = implode( ' AND ', $where );

		// Get total count
		$total = $wpdb->get_var( "
			SELECT COUNT(DISTINCT p.ID)
			FROM {$table_posts} p
			WHERE {$where_clause}
		" );

		// Get products
		$sql = "
			SELECT p.ID, p.post_title, p.post_status, p.post_date, p.post_author,
				   u.display_name as seller_name, u.user_email as seller_email
			FROM {$table_posts} p
			LEFT JOIN {$wpdb->users} u ON p.post_author = u.ID
			WHERE {$where_clause}
			ORDER BY p.ID DESC
			LIMIT %d OFFSET %d
		";

		$sql = $wpdb->prepare( $sql, $per_page, $offset );
		$posts = $wpdb->get_results( $sql );

		// Enrich data
		$data = [];
		$table_details = $wpdb->prefix . 'fct_product_details';
		$table_variations = $wpdb->prefix . 'fct_product_variations';

		foreach ( $posts as $p ) {
			$product_id = $p->ID;

			// Get price from FluentCart (in cents)
			$price_in_cents = 0;
			$details = $wpdb->get_row( $wpdb->prepare(
				"SELECT min_price, max_price FROM {$table_details} WHERE post_id = %d LIMIT 1",
				$product_id
			) );

			if ( $details ) {
				$price_in_cents = (float) $details->min_price;
			} else {
				// Fallback to variation price
				$variation = $wpdb->get_row( $wpdb->prepare(
					"SELECT item_price FROM {$table_variations} WHERE post_id = %d ORDER BY serial_index ASC LIMIT 1",
					$product_id
				) );
				if ( $variation ) {
					$price_in_cents = (float) $variation->item_price;
				}
			}

			// Convert price from cents to yuan (for TWD)
			$price = $this->convert_price( $price_in_cents, 'TWD' );

			// Get stock
			$stock = 0;
			$stock_status = 'out-of-stock';
			$variation = $wpdb->get_row( $wpdb->prepare(
				"SELECT available, total_stock, stock_status FROM {$table_variations} WHERE post_id = %d ORDER BY serial_index ASC LIMIT 1",
				$product_id
			) );

			if ( $variation ) {
				$stock = (int) ( $variation->available ?? $variation->total_stock ?? 0 );
				$stock_status = $variation->stock_status ?? 'out-of-stock';
			}

			// Get thumbnail
			$thumbnail_id = get_post_thumbnail_id( $product_id );
			$image = $thumbnail_id ? wp_get_attachment_image_url( $thumbnail_id, 'thumbnail' ) : '';

			// Get supplier info
			$supplier_id = get_post_meta( $product_id, '_buygo_supplier_id', true );
			$supplier = null;
			if ( $supplier_id ) {
				$supplier_id = absint( $supplier_id );
				$suppliers_table = $wpdb->prefix . 'buygo_suppliers';
				$supplier_row = $wpdb->get_row( $wpdb->prepare(
					"SELECT id, name FROM {$suppliers_table} WHERE id = %d",
					$supplier_id
				) );
				if ( $supplier_row ) {
					$supplier = [
						'id' => (int) $supplier_row->id,
						'name' => $supplier_row->name,
					];
				}
			}

			// Status label
			$status_map = [
				'publish' => '已發布',
				'draft' => '草稿',
				'pending' => '審核中',
				'private' => '私人',
				'trash' => '垃圾桶',
			];

			$data[] = [
				'id' => $p->ID,
				'name' => $p->post_title,
				'price' => $price, // Already converted to yuan
				'formatted_price' => 'NT$ ' . number_format( $price, 0 ),
				'stock' => $stock,
				'status' => $p->post_status,
				'status_label' => $status_map[ $p->post_status ] ?? $p->post_status,
				'stock_status' => $stock_status,
				'image' => $image,
				'created_at' => $p->post_date,
				'supplier_id' => $supplier_id ? (int) $supplier_id : null,
				'supplier' => $supplier,
				'seller' => [
					'id' => $p->post_author,
					'name' => $p->seller_name ?: '',
					'email' => $p->seller_email ?: '',
				],
			];
		}

		return [
			'products' => $data,
			'pagination' => [
				'total' => (int) $total,
				'page' => $page,
				'per_page' => $per_page,
				'total_pages' => (int) ceil( $total / $per_page ),
			],
		];
	}

	/**
	 * Get single product
	 *
	 * @param int $user_id User ID
	 * @param int $product_id Product ID
	 * @return array|null Product data or null if not found
	 */
	public function getProduct( $user_id, $product_id ) {
		global $wpdb;

		$product_id = absint( $product_id );
		$product = get_post( $product_id );

		if ( ! $product || $product->post_type !== 'fluent-products' ) {
			return null;
		}

		// Check permission
		$role_manager = App::instance()->make( RoleManager::class );
		$is_admin = current_user_can( 'manage_options' ) || $role_manager->is_admin( $user_id );
		$is_seller = $role_manager->is_seller( $user_id );

		if ( ! $is_admin && $is_seller && $product->post_author != $user_id ) {
			// Seller can only see own products
			return null;
		}

		// Get price (in cents)
		$table_details = $wpdb->prefix . 'fct_product_details';
		$table_variations = $wpdb->prefix . 'fct_product_variations';

		$price_in_cents = 0;
		$details = $wpdb->get_row( $wpdb->prepare(
			"SELECT min_price, max_price FROM {$table_details} WHERE post_id = %d LIMIT 1",
			$product_id
		) );

		if ( $details ) {
			$price_in_cents = (float) $details->min_price;
		} else {
			$variation = $wpdb->get_row( $wpdb->prepare(
				"SELECT item_price FROM {$table_variations} WHERE post_id = %d ORDER BY serial_index ASC LIMIT 1",
				$product_id
			) );
			if ( $variation ) {
				$price_in_cents = (float) $variation->item_price;
			}
		}

		// Convert price from cents to yuan
		$price = $this->convert_price( $price_in_cents, 'TWD' );

		// Get all variations
		$variations = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, variation_title, variation_identifier, item_price, total_stock, available, stock_status, item_status, serial_index, media_id
			FROM {$table_variations}
			WHERE post_id = %d
			ORDER BY serial_index ASC",
			$product_id
		) );

		$variations_data = [];
		$total_stock = 0;
		$stock_status = 'out-of-stock';

		foreach ( $variations as $variation ) {
			$variation_price = $this->convert_price( (float) $variation->item_price, 'TWD' );
			$variation_stock = (int) ( $variation->available ?? $variation->total_stock ?? 0 );
			$total_stock += $variation_stock;

			// Get variation image
			$variation_image = '';
			if ( $variation->media_id ) {
				$variation_image = wp_get_attachment_image_url( $variation->media_id, 'thumbnail' );
			}
			if ( ! $variation_image ) {
				// Fallback to product thumbnail
				$thumbnail_id = get_post_thumbnail_id( $product_id );
				$variation_image = $thumbnail_id ? wp_get_attachment_image_url( $thumbnail_id, 'thumbnail' ) : '';
			}

			// Get variation-specific cost price (stored as post meta with variation ID)
			$variation_cost = get_post_meta( $product_id, '_buygo_variation_cost_' . $variation->id, true );

			$variations_data[] = [
				'id' => $variation->id,
				'variation_title' => $variation->variation_title ?: $product->post_title,
				'variation_identifier' => $variation->variation_identifier ?? '',
				'price' => $variation_price,
				'formatted_price' => 'NT$ ' . number_format( $variation_price, 0 ),
				'stock' => $variation_stock,
				'stock_status' => $variation->stock_status ?? 'out-of-stock',
				'item_status' => $variation->item_status ?? 'active',
				'serial_index' => (int) ( $variation->serial_index ?? 0 ),
				'image' => $variation_image,
				'cost_price' => $variation_cost ? (float) $variation_cost : null,
			];

			// Use first variation's stock_status as default
			if ( empty( $stock_status ) || $stock_status === 'out-of-stock' ) {
				$stock_status = $variation->stock_status ?? 'out-of-stock';
			}
		}

		// If no variations, use default values
		if ( empty( $variations_data ) ) {
			$stock = 0;
		} else {
			$stock = $total_stock;
		}

		// Get description
		$description = $product->post_content;

		// Get supplier and cost price (for supplier settlement feature)
		$supplier_id = get_post_meta( $product_id, '_buygo_supplier_id', true );
		$cost_price = get_post_meta( $product_id, '_buygo_cost_price', true );

		// Get supplier info if assigned
		$supplier = null;
		if ( $supplier_id ) {
			$supplier_id = absint( $supplier_id );
			$suppliers_table = $wpdb->prefix . 'buygo_suppliers';
			$supplier_row = $wpdb->get_row( $wpdb->prepare(
				"SELECT id, name FROM {$suppliers_table} WHERE id = %d",
				$supplier_id
			) );
			if ( $supplier_row ) {
				$supplier = [
					'id' => (int) $supplier_row->id,
					'name' => $supplier_row->name,
				];
			}
		}

		return [
			'id' => $product->ID,
			'name' => $product->post_title,
			'description' => $description,
			'price' => $price, // First variation price or min price
			'formatted_price' => 'NT$ ' . number_format( $price, 0 ),
			'stock' => $stock,
			'stock_status' => $stock_status,
			'status' => $product->post_status,
			'created_at' => $product->post_date,
			'variations' => $variations_data,
			'has_variations' => count( $variations_data ) > 1,
			'supplier_id' => $supplier_id ? (int) $supplier_id : null,
			'supplier' => $supplier,
			'cost_price' => $cost_price ? (float) $cost_price : null,
		];
	}

	/**
	 * Update product
	 *
	 * @param int $user_id User ID
	 * @param int $product_id Product ID
	 * @param array $data Product data to update
	 * @return array Result with success status and message
	 */
	public function updateProduct( $user_id, $product_id, $data ) {
		global $wpdb;

		$product_id = absint( $product_id );
		$product = get_post( $product_id );

		if ( ! $product || $product->post_type !== 'fluent-products' ) {
			return [
				'success' => false,
				'message' => 'Product not found',
			];
		}

		// Check permission
		$role_manager = App::instance()->make( RoleManager::class );
		$is_admin = current_user_can( 'manage_options' ) || $role_manager->is_admin( $user_id );
		$is_seller = $role_manager->is_seller( $user_id );

		if ( ! $is_admin && $is_seller && $product->post_author != $user_id ) {
			// Seller can only edit own products
			return [
				'success' => false,
				'message' => 'Permission denied',
			];
		}

		// Prepare update data
		$post_data = [
			'ID' => $product_id,
		];

		if ( isset( $data['name'] ) ) {
			$post_data['post_title'] = sanitize_text_field( $data['name'] );
		}

		if ( isset( $data['description'] ) ) {
			$post_data['post_content'] = sanitize_textarea_field( $data['description'] );
		}

		if ( isset( $data['status'] ) ) {
			$allowed_statuses = [ 'publish', 'draft', 'pending', 'private' ];
			if ( in_array( $data['status'], $allowed_statuses, true ) ) {
				$post_data['post_status'] = $data['status'];
			}
		}

		// Update post
		$result = wp_update_post( $post_data, true );
		if ( is_wp_error( $result ) ) {
			return [
				'success' => false,
				'message' => $result->get_error_message(),
			];
		}

		// Update supplier and cost price (for supplier settlement feature)
		if ( isset( $data['supplier_id'] ) ) {
			$supplier_id = absint( $data['supplier_id'] );
			if ( $supplier_id > 0 ) {
				update_post_meta( $product_id, '_buygo_supplier_id', $supplier_id );
			} else {
				delete_post_meta( $product_id, '_buygo_supplier_id' );
			}
		}

		if ( isset( $data['cost_price'] ) ) {
			$cost_price = floatval( $data['cost_price'] );
			if ( $cost_price > 0 ) {
				update_post_meta( $product_id, '_buygo_cost_price', $cost_price );
			} else {
				delete_post_meta( $product_id, '_buygo_cost_price' );
			}
		}

		// Update variations in FluentCart tables
		$table_variations = $wpdb->prefix . 'fct_product_variations';

		// If variations data is provided, update all variations
		if ( isset( $data['variations'] ) && is_array( $data['variations'] ) ) {
			$min_price = null;
			$max_price = null;

			foreach ( $data['variations'] as $variation_data ) {
				$variation_id = isset( $variation_data['id'] ) ? absint( $variation_data['id'] ) : 0;

				if ( ! $variation_id ) {
					continue; // Skip if no variation ID
				}

				$update_data = [];
				$update_data['updated_at'] = current_time( 'mysql' );

				// Update variation title
				if ( isset( $variation_data['variation_title'] ) ) {
					$update_data['variation_title'] = sanitize_text_field( $variation_data['variation_title'] );
				}

				// Update price
				if ( isset( $variation_data['price'] ) ) {
					$price_in_cents = intval( floatval( $variation_data['price'] ) * 100 );
					$update_data['item_price'] = $price_in_cents;

					if ( $min_price === null || $price_in_cents < $min_price ) {
						$min_price = $price_in_cents;
					}
					if ( $max_price === null || $price_in_cents > $max_price ) {
						$max_price = $price_in_cents;
					}
				}

				// Update stock
				if ( isset( $variation_data['stock'] ) ) {
					$stock = absint( $variation_data['stock'] );
					$stock_status = $stock > 0 ? 'in-stock' : 'out-of-stock';
					$update_data['total_stock'] = $stock;
					$update_data['available'] = $stock;
					$update_data['stock_status'] = $stock_status;
				}

				// Update item status
				if ( isset( $variation_data['item_status'] ) ) {
					$allowed_statuses = [ 'active', 'inactive' ];
					if ( in_array( $variation_data['item_status'], $allowed_statuses, true ) ) {
						$update_data['item_status'] = $variation_data['item_status'];
					}
				}

				// Update variation-specific cost price (stored as post meta)
				if ( isset( $variation_data['cost_price'] ) ) {
					$cost_price = floatval( $variation_data['cost_price'] );
					if ( $cost_price > 0 ) {
						update_post_meta( $product_id, '_buygo_variation_cost_' . $variation_id, $cost_price );
					} else {
						delete_post_meta( $product_id, '_buygo_variation_cost_' . $variation_id );
					}
				}

				if ( ! empty( $update_data ) ) {
					// Build format array based on update_data keys
					$update_format = [];
					foreach ( $update_data as $key => $value ) {
						if ( in_array( $key, [ 'item_price', 'total_stock', 'available' ], true ) ) {
							$update_format[] = '%d';
						} elseif ( in_array( $key, [ 'stock_status', 'item_status', 'variation_title', 'updated_at' ], true ) ) {
							$update_format[] = '%s';
						} else {
							$update_format[] = '%s'; // Default to string
						}
					}

					$wpdb->update(
						$table_variations,
						$update_data,
						[ 'id' => $variation_id ],
						$update_format,
						[ '%d' ]
					);
				}
			}

			// Update product details min_price and max_price
			if ( $min_price !== null && $max_price !== null ) {
				$table_details = $wpdb->prefix . 'fct_product_details';
				$details_exists = $wpdb->get_var( $wpdb->prepare(
					"SELECT id FROM {$table_details} WHERE post_id = %d LIMIT 1",
					$product_id
				) );

				if ( $details_exists ) {
					$wpdb->update(
						$table_details,
						[
							'min_price' => $min_price,
							'max_price' => $max_price,
							'updated_at' => current_time( 'mysql' ),
						],
						[ 'post_id' => $product_id ],
						[ '%d', '%d', '%s' ],
						[ '%d' ]
					);
				}
			}
		} else {
			// Fallback: Update first variation (for backward compatibility)
			if ( isset( $data['price'] ) || isset( $data['stock'] ) ) {
				$variation = $wpdb->get_row( $wpdb->prepare(
					"SELECT id FROM {$table_variations} WHERE post_id = %d ORDER BY serial_index ASC LIMIT 1",
					$product_id
				) );

				$update_data = [];
				$update_data['updated_at'] = current_time( 'mysql' );

				if ( isset( $data['price'] ) ) {
					$price_in_cents = intval( floatval( $data['price'] ) * 100 );
					$update_data['item_price'] = $price_in_cents;
				}

				if ( isset( $data['stock'] ) ) {
					$stock = absint( $data['stock'] );
					$stock_status = $stock > 0 ? 'in-stock' : 'out-of-stock';
					$update_data['total_stock'] = $stock;
					$update_data['available'] = $stock;
					$update_data['stock_status'] = $stock_status;
				}

				if ( $variation ) {
					$wpdb->update(
						$table_variations,
						$update_data,
						[ 'id' => $variation->id ],
						[ '%d', '%s', '%d', '%d', '%s' ],
						[ '%d' ]
					);
				}

				// Update product details
				$table_details = $wpdb->prefix . 'fct_product_details';
				if ( isset( $data['price'] ) ) {
					$price_in_cents = intval( floatval( $data['price'] ) * 100 );
					$details_exists = $wpdb->get_var( $wpdb->prepare(
						"SELECT id FROM {$table_details} WHERE post_id = %d LIMIT 1",
						$product_id
					) );

					if ( $details_exists ) {
						$wpdb->update(
							$table_details,
							[
								'min_price' => $price_in_cents,
								'max_price' => $price_in_cents,
								'updated_at' => current_time( 'mysql' ),
							],
							[ 'post_id' => $product_id ],
							[ '%d', '%d', '%s' ],
							[ '%d' ]
						);
					}
				}
			}
		}

		return [
			'success' => true,
			'message' => 'Product updated successfully',
		];
	}
}
