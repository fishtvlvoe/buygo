<?php

namespace BuyGo\Core\Api;

use WP_REST_Request;
use WP_REST_Response;
use BuyGo\Core\App;

class DashboardController extends BaseController {

    public function register_routes() {
        register_rest_route($this->namespace, '/stats/seller-dashboard', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_seller_stats'],
                'permission_callback' => [$this, 'check_read_permission'],
            ],
        ]);

        register_rest_route($this->namespace, '/stats/seller-orders', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_seller_orders'],
                'permission_callback' => [$this, 'check_read_permission'],
            ]
        ]);
        
        register_rest_route($this->namespace, '/stats/seller-orders/export', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'export_seller_orders_csv'],
                'permission_callback' => [$this, 'check_read_permission'],
            ]
        ]);

        register_rest_route($this->namespace, '/stats/seller-orders/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_seller_order_detail'],
                'permission_callback' => [$this, 'check_read_permission'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'update_seller_order_status'],
                'permission_callback' => [$this, 'check_write_permission'],
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update_seller_order_status'],
                'permission_callback' => [$this, 'check_write_permission'],
            ]
        ]);
        
        register_rest_route($this->namespace, '/stats/seller-orders/(?P<id>\d+)/add-item', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'add_order_item'],
                'permission_callback' => [$this, 'check_write_permission'],
            ]
        ]);
        register_rest_route($this->namespace, '/stats/seller-orders/(?P<id>\d+)/payment', [
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update_payment_status'],
                'permission_callback' => [$this, 'check_write_permission'],
            ]
        ]);
        
        register_rest_route($this->namespace, '/stats/seller-orders/(?P<id>\d+)/shipment', [
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update_shipment_status'],
                'permission_callback' => [$this, 'check_write_permission'],
            ]
        ]);

        register_rest_route($this->namespace, '/stats/seller-orders/(?P<id>\d+)/notify', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'send_order_notification'],
                'permission_callback' => [$this, 'check_write_permission'],
            ]
        ]);

        // Product List (Read Only)
        register_rest_route($this->namespace, '/stats/seller-products', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_seller_products'],
                'permission_callback' => [$this, 'check_read_permission'],
            ]
        ]);

        // Sales Growth Chart Data
        register_rest_route($this->namespace, '/dashboard/sales-growth', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_sales_growth'],
                'permission_callback' => [$this, 'check_permission'],
            ]
        ]);

        // Recent Orders for Dashboard
        register_rest_route($this->namespace, '/dashboard/recent-orders', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_recent_orders'],
                'permission_callback' => [$this, 'check_permission'],
            ]
        ]);

        // Hot Selling Products
        register_rest_route($this->namespace, '/dashboard/hot-products', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_hot_products'],
                'permission_callback' => [$this, 'check_permission'],
            ]
        ]);

        // Dashboard Stats (Members, Revenue, etc.)
        register_rest_route($this->namespace, '/dashboard/stats', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_dashboard_stats'],
                'permission_callback' => [$this, 'check_permission'],
            ]
        ]);
    }

    /**
     * Update Payment Status
     */
    public function update_payment_status(WP_REST_Request $request) {
        global $wpdb;
        $order_id = $request->get_param('id');
        $status = $request->get_param('status'); // 'paid' or 'pending'
        
        $table_orders = $wpdb->prefix . 'fct_orders';
        
        // Validation
        if (!in_array($status, ['paid', 'pending', 'on-hold'])) {
             return new WP_REST_Response(['success' => false, 'message' => 'Invalid status'], 400); 
        }
        
        $data = ['payment_status' => $status];
        if ($status === 'paid') {
             $data['status'] = 'processing';
        } elseif ($status === 'pending') {
             $data['status'] = 'on-hold'; // Or pending?
        }
        
        $wpdb->update(
            $table_orders,
            $data,
            ['id' => $order_id],
            ['%s', '%s'],
            ['%d']
        );

        return new WP_REST_Response(['success' => true, 'status' => $status], 200);
    }

    /**
     * Update Shipment Status
     */
    public function update_shipment_status(WP_REST_Request $request) {
        global $wpdb;
        $order_id = $request->get_param('id');
        $status = $request->get_param('status'); // 'shipped' or 'pending'
        
        $table_orders = $wpdb->prefix . 'fct_orders';
        
        // Validation
        if (!in_array($status, ['shipped', 'pending'])) {
             return new WP_REST_Response(['success' => false, 'message' => 'Invalid status'], 400); 
        }

        // Check ownership (Optional but recommended)
        // For MVP assuming check_write_permission is enough (Admin/Seller)
        
        $wpdb->update(
            $table_orders,
            ['shipping_status' => $status],
            ['id' => $order_id],
            ['%s'],
            ['%d']
        );

        return new WP_REST_Response(['success' => true, 'status' => $status], 200);
    }

    /**
     * Update Seller Order Status
     */
    public function update_seller_order_status(WP_REST_Request $request) {
        global $wpdb;
        $order_id = $request->get_param('id');
        $body = $request->get_json_params();
        
        // Support both POST with _method: 'PUT' and direct PUT
        $status = $body['status'] ?? $request->get_param('status');
        
        $table_orders = $wpdb->prefix . 'fct_orders';
        $table_addresses = $wpdb->prefix . 'fct_order_addresses';
        $table_customers = $wpdb->prefix . 'fct_customers';
        $table_items = $wpdb->prefix . 'fct_order_items';
        
        // Validate order exists
        $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_orders} WHERE id = %d", $order_id));
        if (!$order) {
            return new WP_REST_Response(['success' => false, 'message' => 'Order not found'], 404);
        }
        
        // Check access control (seller can only update orders with their products)
        $user = wp_get_current_user();
        if (!$this->is_admin_user($user)) {
            $table_items = $wpdb->prefix . 'fct_order_items';
            $table_posts = $wpdb->posts;
            
            $has_access = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*) 
                FROM {$table_items} oi
                JOIN {$table_posts} p ON oi.post_id = p.ID
                WHERE oi.order_id = %d AND p.post_author = %d
            ", $order_id, $user->ID));
            
            if (!$has_access) {
                return new WP_REST_Response(['success' => false, 'message' => 'Unauthorized access'], 403);
            }
        }
        
        // Update order status if provided
        if (isset($status)) {
            $valid_statuses = ['pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed'];
            if (!in_array($status, $valid_statuses)) {
                return new WP_REST_Response(['success' => false, 'message' => 'Invalid status'], 400);
            }
            
            $update_data = ['status' => $status];
            $update_format = ['%s'];
            
            // Also update shipping_status if provided
            if (isset($body['shipping_status'])) {
                $update_data['shipping_status'] = $body['shipping_status'];
                $update_format[] = '%s';
            }
            
            $result = $wpdb->update(
                $table_orders,
                $update_data,
                ['id' => $order_id],
                $update_format,
                ['%d']
            );
            
            if ($result === false) {
                return new WP_REST_Response(['success' => false, 'message' => 'Update failed'], 500);
            }
        }
        
        // Update customer information if provided
        if (isset($body['customer_info']) && is_array($body['customer_info'])) {
            $customer_info = $body['customer_info'];
            
            // Update customer table if customer_id exists
            if (!empty($order->customer_id)) {
                $customer_update = [];
                $customer_format = [];
                
                if (isset($customer_info['email']) && !empty($customer_info['email'])) {
                    $customer_update['email'] = sanitize_email($customer_info['email']);
                    $customer_format[] = '%s';
                }
                
                if (isset($customer_info['phone']) && !empty($customer_info['phone'])) {
                    $customer_update['phone'] = sanitize_text_field($customer_info['phone']);
                    $customer_format[] = '%s';
                }
                
                // Update customer name if provided
                if (isset($customer_info['name']) && !empty($customer_info['name'])) {
                    // Parse name into first_name and last_name
                    $name_parts = explode(' ', trim($customer_info['name']), 2);
                    $customer_update['first_name'] = sanitize_text_field($name_parts[0] ?? '');
                    $customer_update['last_name'] = sanitize_text_field($name_parts[1] ?? '');
                    $customer_format[] = '%s';
                    $customer_format[] = '%s';
                }
                
                if (!empty($customer_update)) {
                    $wpdb->update(
                        $table_customers,
                        $customer_update,
                        ['id' => $order->customer_id],
                        $customer_format,
                        ['%d']
                    );
                }
            }
            
            // Update billing address
            $billing_address = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table_addresses} WHERE order_id = %d AND type = 'billing'",
                $order_id
            ));
            
            if ($billing_address) {
                $billing_update = [];
                $billing_format = [];
                
                if (isset($customer_info['name'])) {
                    $billing_update['name'] = sanitize_text_field($customer_info['name']);
                    $billing_format[] = '%s';
                } elseif (isset($customer_info['first_name']) || isset($customer_info['last_name'])) {
                    $first_name = sanitize_text_field($customer_info['first_name'] ?? '');
                    $last_name = sanitize_text_field($customer_info['last_name'] ?? '');
                    $billing_update['name'] = trim($first_name . ' ' . $last_name);
                    $billing_format[] = '%s';
                }
                
                // Update email and phone in meta (combine into single update)
                $meta = json_decode($billing_address->meta, true) ?: [];
                if (!is_array($meta)) $meta = [];
                $meta_updated = false;
                
                if (isset($customer_info['email']) && !empty($customer_info['email'])) {
                    $meta['email'] = sanitize_email($customer_info['email']);
                    $meta_updated = true;
                }
                
                if (isset($customer_info['phone']) && !empty($customer_info['phone'])) {
                    $meta['phone'] = sanitize_text_field($customer_info['phone']);
                    $meta_updated = true;
                }
                
                if ($meta_updated) {
                    $billing_update['meta'] = json_encode($meta, JSON_UNESCAPED_UNICODE);
                    $billing_format[] = '%s';
                }
                
                if (isset($customer_info['address']) && is_array($customer_info['address'])) {
                    $addr = $customer_info['address'];
                    
                    // 如果 address_1 已經包含完整地址（可能包含城市名稱），就只更新 address_1
                    // 避免重複添加城市名稱
                    if (isset($addr['address_1']) && !empty($addr['address_1'])) {
                        $address_1 = sanitize_text_field($addr['address_1']);
                        $billing_update['address_1'] = $address_1;
                        $billing_format[] = '%s';
                        
                        // 只有在 address_1 不包含城市名稱時，才更新 city 和 state
                        // 檢查 address_1 是否已經包含城市相關字詞
                        $has_city_in_address = false;
                        if (!empty($billing_address->city) && strpos($address_1, $billing_address->city) !== false) {
                            $has_city_in_address = true;
                        }
                        if (!empty($billing_address->state) && strpos($address_1, $billing_address->state) !== false) {
                            $has_city_in_address = true;
                        }
                        
                        // 如果 address_1 已經包含城市，就不更新 city 和 state，避免重複
                        if (!$has_city_in_address) {
                            if (isset($addr['city']) && !empty($addr['city'])) {
                                $billing_update['city'] = sanitize_text_field($addr['city']);
                                $billing_format[] = '%s';
                            }
                            if (isset($addr['state']) && !empty($addr['state'])) {
                                $billing_update['state'] = sanitize_text_field($addr['state']);
                                $billing_format[] = '%s';
                            }
                        } else {
                            // 如果 address_1 已經包含城市，清空 city 和 state 避免重複顯示
                            $billing_update['city'] = '';
                            $billing_update['state'] = '';
                            $billing_format[] = '%s';
                            $billing_format[] = '%s';
                        }
                    } else {
                        // 如果沒有 address_1，才更新其他欄位
                        if (isset($addr['city'])) {
                            $billing_update['city'] = sanitize_text_field($addr['city']);
                            $billing_format[] = '%s';
                        }
                        if (isset($addr['state'])) {
                            $billing_update['state'] = sanitize_text_field($addr['state']);
                            $billing_format[] = '%s';
                        }
                    }
                    
                    if (isset($addr['address_2'])) {
                        $billing_update['address_2'] = sanitize_text_field($addr['address_2']);
                        $billing_format[] = '%s';
                    }
                    if (isset($addr['postcode'])) {
                        $billing_update['postcode'] = sanitize_text_field($addr['postcode']);
                        $billing_format[] = '%s';
                    }
                    if (isset($addr['country'])) {
                        $billing_update['country'] = sanitize_text_field($addr['country']);
                        $billing_format[] = '%s';
                    }
                }
                
                if (!empty($billing_update)) {
                    $billing_update['updated_at'] = current_time('mysql');
                    $billing_format[] = '%s';
                    
                    $wpdb->update(
                        $table_addresses,
                        $billing_update,
                        ['id' => $billing_address->id],
                        $billing_format,
                        ['%d']
                    );
                }
            } else {
                // Create billing address if it doesn't exist
                $billing_data = [
                    'order_id' => $order_id,
                    'type' => 'billing',
                    'name' => sanitize_text_field($customer_info['name'] ?? ''),
                    'address_1' => sanitize_text_field($customer_info['address']['address_1'] ?? ''),
                    'address_2' => sanitize_text_field($customer_info['address']['address_2'] ?? ''),
                    'city' => sanitize_text_field($customer_info['address']['city'] ?? ''),
                    'state' => sanitize_text_field($customer_info['address']['state'] ?? ''),
                    'postcode' => sanitize_text_field($customer_info['address']['postcode'] ?? ''),
                    'country' => sanitize_text_field($customer_info['address']['country'] ?? 'TW'),
                    'meta' => json_encode([
                        'email' => sanitize_email($customer_info['email'] ?? ''),
                        'phone' => sanitize_text_field($customer_info['phone'] ?? '')
                    ], JSON_UNESCAPED_UNICODE),
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ];
                
                $wpdb->insert($table_addresses, $billing_data);
            }
            
            // Update shipping address (same as billing if not separately provided)
            $shipping_address = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table_addresses} WHERE order_id = %d AND type = 'shipping'",
                $order_id
            ));
            
            if ($shipping_address) {
                // Update shipping address with same data as billing
                $shipping_update = [];
                $shipping_format = [];
                
                if (isset($customer_info['name'])) {
                    $shipping_update['name'] = sanitize_text_field($customer_info['name']);
                    $shipping_format[] = '%s';
                }
                
                if (isset($customer_info['address']) && is_array($customer_info['address'])) {
                    $addr = $customer_info['address'];
                    if (isset($addr['address_1'])) {
                        $shipping_update['address_1'] = sanitize_text_field($addr['address_1']);
                        $shipping_format[] = '%s';
                    }
                    if (isset($addr['address_2'])) {
                        $shipping_update['address_2'] = sanitize_text_field($addr['address_2']);
                        $shipping_format[] = '%s';
                    }
                    if (isset($addr['city'])) {
                        $shipping_update['city'] = sanitize_text_field($addr['city']);
                        $shipping_format[] = '%s';
                    }
                    if (isset($addr['state'])) {
                        $shipping_update['state'] = sanitize_text_field($addr['state']);
                        $shipping_format[] = '%s';
                    }
                    if (isset($addr['postcode'])) {
                        $shipping_update['postcode'] = sanitize_text_field($addr['postcode']);
                        $shipping_format[] = '%s';
                    }
                }
                
                if (!empty($shipping_update)) {
                    $shipping_update['updated_at'] = current_time('mysql');
                    $shipping_format[] = '%s';
                    
                    $wpdb->update(
                        $table_addresses,
                        $shipping_update,
                        ['id' => $shipping_address->id],
                        $shipping_format,
                        ['%d']
                    );
                }
            }
        }
        
        // Delete removed items if provided
        if (isset($body['deleted_items']) && is_array($body['deleted_items']) && !empty($body['deleted_items'])) {
            $deleted_ids = array_map('intval', $body['deleted_items']);
            $deleted_ids = array_filter($deleted_ids, function($id) { return $id > 0; });
            
            if (!empty($deleted_ids)) {
                // Delete items one by one for safety
                foreach ($deleted_ids as $item_id) {
                    $wpdb->delete(
                        $table_items,
                        [
                            'id' => $item_id,
                            'order_id' => $order_id
                        ],
                        ['%d', '%d']
                    );
                }
            }
        }
        
        // Update order items if provided
        if (isset($body['order_items']) && is_array($body['order_items'])) {
            $items_updated = false;
            $new_total = 0;
            
            foreach ($body['order_items'] as $item_data) {
                if (!isset($item_data['id']) || !isset($item_data['quantity'])) {
                    continue;
                }
                
                $item_id = intval($item_data['id']);
                $new_quantity = intval($item_data['quantity']);
                
                if ($new_quantity < 1) {
                    continue; // Skip invalid quantities
                }
                
                // Get current item
                $current_item = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$table_items} WHERE id = %d AND order_id = %d",
                    $item_id,
                    $order_id
                ));
                
                if (!$current_item) {
                    continue; // Item not found, skip
                }
                
                // Calculate new line total
                // If price is provided, use it; otherwise preserve unit price
                $new_price = isset($item_data['price']) ? intval(floatval($item_data['price']) * 100) : null;
                $new_line_total = isset($item_data['line_total']) ? intval(floatval($item_data['line_total']) * 100) : null;
                
                if ($new_price !== null) {
                    // Use provided price
                    $new_line_total = $new_price * $new_quantity;
                } elseif ($new_line_total === null) {
                    // Preserve unit price
                    $unit_price = ($current_item->line_total ?? 0) / max($current_item->quantity ?? 1, 1);
                    $new_line_total = intval($unit_price * $new_quantity);
                }
                
                // Update item quantity, price, and total
                $update_fields = [
                    'quantity' => $new_quantity,
                    'line_total' => $new_line_total,
                    'updated_at' => current_time('mysql')
                ];
                $update_formats = ['%d', '%d', '%s'];
                
                // If price is being updated, we might need to update unit_price field if it exists
                // For now, we'll just update quantity and line_total
                
                $item_update_result = $wpdb->update(
                    $table_items,
                    $update_fields,
                    ['id' => $item_id, 'order_id' => $order_id],
                    $update_formats,
                    ['%d', '%d']
                );
                
                if ($item_update_result !== false) {
                    $items_updated = true;
                    $new_total += $new_line_total;
                }
            }
            
            // Recalculate order total if items were updated
            if ($items_updated) {
                // Get all items for this order to calculate new total
                $all_items = $wpdb->get_results($wpdb->prepare(
                    "SELECT line_total FROM {$table_items} WHERE order_id = %d",
                    $order_id
                ));
                
                $calculated_total = 0;
                foreach ($all_items as $item) {
                    $calculated_total += intval($item->line_total ?? 0);
                }
                
                // Apply discount if provided
                $discount_amount = 0;
                if (isset($body['discount_amount'])) {
                    $discount_amount = intval(floatval($body['discount_amount']) * 100);
                    $calculated_total = max(0, $calculated_total - $discount_amount);
                }
                
                // Update order total
                $wpdb->update(
                    $table_orders,
                    [
                        'total_amount' => $calculated_total,
                        'updated_at' => current_time('mysql')
                    ],
                    ['id' => $order_id],
                    ['%d', '%s'],
                    ['%d']
                );
                
                // Store discount code in order meta if provided
                if (isset($body['discount_code']) && !empty($body['discount_code'])) {
                    $table_meta = $wpdb->prefix . 'fct_order_meta';
                    // Check if meta exists
                    $existing_meta = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM {$table_meta} WHERE order_id = %d AND meta_key = 'discount_code'",
                        $order_id
                    ));
                    
                    if ($existing_meta) {
                        $wpdb->update(
                            $table_meta,
                            [
                                'meta_value' => sanitize_text_field($body['discount_code']),
                                'updated_at' => current_time('mysql')
                            ],
                            ['order_id' => $order_id, 'meta_key' => 'discount_code'],
                            ['%s', '%s'],
                            ['%d', '%s']
                        );
                    } else {
                        $wpdb->insert(
                            $table_meta,
                            [
                                'order_id' => $order_id,
                                'meta_key' => 'discount_code',
                                'meta_value' => sanitize_text_field($body['discount_code']),
                                'created_at' => current_time('mysql'),
                                'updated_at' => current_time('mysql')
                            ],
                            ['%d', '%s', '%s', '%s', '%s']
                        );
                    }
                }
            }
        }
        
        return new WP_REST_Response([
            'success' => true,
            'message' => 'Order updated successfully',
            'data' => [
                'id' => $order_id,
                'status' => $status ?? $order->status
            ]
        ], 200);
    }
    
    /**
     * Add item to existing order
     */
    public function add_order_item(WP_REST_Request $request) {
        global $wpdb;
        $order_id = $request->get_param('id');
        $body = $request->get_json_params();
        
        $table_orders = $wpdb->prefix . 'fct_orders';
        $table_items = $wpdb->prefix . 'fct_order_items';
        $table_posts = $wpdb->posts;
        $table_details = $wpdb->prefix . 'fct_product_details';
        $table_variations = $wpdb->prefix . 'fct_product_variations';
        
        // Validate order exists
        $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_orders} WHERE id = %d", $order_id));
        if (!$order) {
            return new WP_REST_Response(['success' => false, 'message' => 'Order not found'], 404);
        }
        
        // Check access control
        $user = wp_get_current_user();
        if (!$this->is_admin_user($user)) {
            $has_access = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*) 
                FROM {$table_items} oi
                JOIN {$table_posts} p ON oi.post_id = p.ID
                WHERE oi.order_id = %d AND p.post_author = %d
            ", $order_id, $user->ID));
            
            if (!$has_access) {
                return new WP_REST_Response(['success' => false, 'message' => 'Unauthorized access'], 403);
            }
        }
        
        // Validate product_id and quantity
        $product_id = intval($body['product_id'] ?? 0);
        $quantity = intval($body['quantity'] ?? 1);
        
        if ($product_id <= 0 || $quantity <= 0) {
            return new WP_REST_Response(['success' => false, 'message' => 'Invalid product_id or quantity'], 400);
        }
        
        // Get product info
        $product = get_post($product_id);
        if (!$product || $product->post_type !== 'fluent-products') {
            return new WP_REST_Response(['success' => false, 'message' => 'Product not found'], 404);
        }
        
        // Get product price
        $price = 0;
        $details = $wpdb->get_row($wpdb->prepare(
            "SELECT min_price, max_price FROM {$table_details} WHERE post_id = %d LIMIT 1",
            $product_id
        ));
        
        if ($details) {
            $price = intval($details->min_price); // Price in cents
        } else {
            $variation = $wpdb->get_row($wpdb->prepare(
                "SELECT item_price FROM {$table_variations} WHERE post_id = %d ORDER BY serial_index ASC LIMIT 1",
                $product_id
            ));
            if ($variation) {
                $price = intval($variation->item_price);
            }
        }
        
        if ($price <= 0) {
            return new WP_REST_Response(['success' => false, 'message' => 'Product price not found'], 400);
        }
        
        // Get product thumbnail
        $thumbnail_id = get_post_thumbnail_id($product_id);
        
        // Calculate totals
        $unit_price = $price;
        $subtotal = $price * $quantity;
        $line_total = $subtotal; // No discount/tax for now
        
        // Get max cart_index to append new item
        $max_cart_index = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(MAX(cart_index), 0) FROM {$table_items} WHERE order_id = %d",
            $order_id
        ));
        $cart_index = intval($max_cart_index) + 1;
        
        // Insert order item with correct column names
        $item_data = [
            'order_id' => $order_id,
            'post_id' => $product_id,
            'object_id' => $product_id, // For simple products, object_id = post_id
            'post_title' => $product->post_title,
            'title' => $product->post_title,
            'quantity' => $quantity,
            'unit_price' => $unit_price,
            'subtotal' => $subtotal,
            'line_total' => $line_total,
            'tax_amount' => 0,
            'discount_total' => 0,
            'cost' => 0,
            'shipping_charge' => 0,
            'refund_total' => 0,
            'rate' => 1,
            'cart_index' => $cart_index,
            'fulfillment_type' => 'physical',
            'payment_type' => 'onetime',
            'fulfilled_quantity' => 0,
            'line_meta' => json_encode(['thumbnail_id' => $thumbnail_id], JSON_UNESCAPED_UNICODE),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];
        
        // Prepare format array for wpdb->insert
        $item_format = [
            '%d', // order_id
            '%d', // post_id
            '%d', // object_id
            '%s', // post_title
            '%s', // title
            '%d', // quantity
            '%d', // unit_price
            '%d', // subtotal
            '%d', // line_total
            '%d', // tax_amount
            '%d', // discount_total
            '%d', // cost
            '%d', // shipping_charge
            '%d', // refund_total
            '%d', // rate
            '%d', // cart_index
            '%s', // fulfillment_type
            '%s', // payment_type
            '%d', // fulfilled_quantity
            '%s', // line_meta
            '%s', // created_at
            '%s'  // updated_at
        ];
        
        $result = $wpdb->insert($table_items, $item_data, $item_format);
        
        if ($result === false) {
            $error_msg = $wpdb->last_error ?: 'Database insert failed';
            return new WP_REST_Response([
                'success' => false, 
                'message' => 'Failed to add item: ' . $error_msg
            ], 500);
        }
        
        $new_item_id = $wpdb->insert_id;
        
        // Recalculate order total
        $all_items = $wpdb->get_results($wpdb->prepare(
            "SELECT line_total FROM {$table_items} WHERE order_id = %d",
            $order_id
        ));
        
        $new_total = 0;
        foreach ($all_items as $item) {
            $new_total += intval($item->line_total ?? 0);
        }
        
        // Update order total
        $wpdb->update(
            $table_orders,
            [
                'total_amount' => $new_total,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $order_id],
            ['%d', '%s'],
            ['%d']
        );
        
        return new WP_REST_Response([
            'success' => true,
            'message' => 'Item added successfully',
            'data' => [
                'item_id' => $new_item_id,
                'order_id' => $order_id,
                'new_total' => $new_total
            ]
        ], 200);
    }
    
    /**
     * Get Single Order Detail
     */
    /**
     * Get Single Order Detail
     */
    public function get_seller_order_detail(WP_REST_Request $request) {
        global $wpdb;
        $order_id = $request->get_param('id');
        $user = wp_get_current_user();
        
        $table_orders = $wpdb->prefix . 'fct_orders';
        $table_items = $wpdb->prefix . 'fct_order_items';
        $table_addresses = $wpdb->prefix . 'fct_order_addresses';
        $table_customers = $wpdb->prefix . 'fct_customers'; 
        $table_posts = $wpdb->posts;
        $table_postmeta = $wpdb->postmeta;

        $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_orders} WHERE id = %d", $order_id));
        
        if (!$order) {
            return new WP_REST_Response(['success' => false, 'message' => 'Order not found'], 404);
        }

        // Get Customer Info (Email/Phone)
        $customer_email = '';
        $customer_phone = '';
        
        // Helper to validate phone (at least 6 digits)
        $is_valid_phone = function($p) {
            return is_string($p) && !empty($p) && strlen(preg_replace('/[^0-9]/', '', $p)) > 5;
        };
        
        // 0. PRIORITY: FluentCRM (The Source of Truth)
        // Try to get email first from order/user, then query FluentCRM
        $lookup_email = '';
        
        // Get email from FluentCart Model first
        if (class_exists('\FluentCart\App\Models\Order')) {
            try {
                $fct_order = \FluentCart\App\Models\Order::find($order_id);
                if ($fct_order && $fct_order->customer) {
                    $lookup_email = $fct_order->customer->email;
                }
                if (empty($lookup_email) && $fct_order && $fct_order->billing_address) {
                    $lookup_email = $fct_order->billing_address['email'] ?? '';
                }
            } catch (\Exception $e) {
                // Continue to SQL fallback
            }
        }
        
        // SQL fallback for email
        if (empty($lookup_email) && !empty($order->customer_id)) {
            $customer = $wpdb->get_row($wpdb->prepare("SELECT email, phone FROM {$table_customers} WHERE id = %d", $order->customer_id));
            if ($customer) {
                $lookup_email = $customer->email;
            }
        }
        
        // If email still empty, try User ID
        if (empty($lookup_email) && !empty($order->user_id)) {
            $u = get_userdata($order->user_id);
            if ($u) $lookup_email = $u->user_email;
        }
        
        // Now query FluentCRM with email (if available)
        if (!empty($lookup_email) && function_exists('FluentCrmApi') && defined('FLUENTCRM')) {
            try {
                $contact = FluentCrmApi('contacts')->getContact($lookup_email);
                if ($contact) {
                    $customer_email = $lookup_email;
                    // Priority: phone from FluentCRM
                    if ($is_valid_phone($contact->phone)) {
                        $customer_phone = $contact->phone;
                    } elseif ($is_valid_phone($contact->mobile)) {
                        $customer_phone = $contact->mobile;
                    }
                }
            } catch (\Exception $e) {
                // Continue to fallback methods
            }
        }
        
        // 1. Fallback: FluentCart Native Model (if FluentCRM didn't provide phone)
        if (empty($customer_phone) && class_exists('\FluentCart\App\Models\Order')) {
            try {
                $fct_order = \FluentCart\App\Models\Order::find($order_id);
                if ($fct_order) {
                    if (empty($customer_email) && $fct_order->customer) {
                        $customer_email = $fct_order->customer->email;
                    }
                    if (empty($customer_phone) && $fct_order->customer) {
                        $customer_phone = $fct_order->customer->phone ?? '';
                    }
                    // If customer table empty, check billing address on model
                    if (empty($customer_phone) && $fct_order->billing_address) {
                        // Try direct phone field first
                        $customer_phone = $fct_order->billing_address['phone'] ?? '';
                        // If empty, try meta['other_data']['phone'] (FluentCart stores phone in meta)
                        if (empty($customer_phone) && is_array($fct_order->billing_address)) {
                            $meta = $fct_order->billing_address['meta'] ?? [];
                            if (is_array($meta) && isset($meta['other_data']['phone']) && !empty($meta['other_data']['phone'])) {
                                $customer_phone = $meta['other_data']['phone'];
                            }
                        }
                    }
                    if (empty($customer_email) && $fct_order->billing_address) {
                        $customer_email = $fct_order->billing_address['email'] ?? '';
                    }
                }
            } catch (\Exception $e) {
                // Squelch error, fallback to SQL
            }
        }

        // 2. SQL Fallback (if Model failed or empty)
        if (empty($customer_email) && !empty($order->customer_id)) {
            $customer = $wpdb->get_row($wpdb->prepare("SELECT email, phone FROM {$table_customers} WHERE id = %d", $order->customer_id));
            if ($customer) {
                if (empty($customer_email)) $customer_email = $customer->email;
                if (empty($customer_phone)) $customer_phone = $customer->phone ?? ''; 
            }
        }
        
        // If email still empty, try User ID
        if (empty($customer_email) && !empty($order->user_id)) {
            $u = get_userdata($order->user_id);
            if ($u) $customer_email = $u->user_email;
        }

        // Get Addresses
        $addresses = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table_addresses} WHERE order_id = %d", $order_id));
        $billing = null;
        $shipping = null;
        foreach ($addresses as $addr) {
            if ($addr->type === 'billing') $billing = $addr;
            if ($addr->type === 'shipping') $shipping = $addr;
        }

        // Get Items with Thumbnail ID
        // Note: Check if FluentCart stores product_id in 'product_id' or 'post_id' column.
        // Assuming 'post_id' correctly links to wp_posts type='product'
        $items_sql = $wpdb->prepare("
            SELECT oi.*, p.post_title as product_title, p.post_author,
                   (SELECT meta_value FROM {$table_postmeta} pm WHERE pm.post_id = p.ID AND pm.meta_key = '_thumbnail_id' LIMIT 1) as thumbnail_id
            FROM {$table_items} oi
            LEFT JOIN {$table_posts} p ON oi.post_id = p.ID
            WHERE oi.order_id = %d
        ", $order_id);
        
        $items = $wpdb->get_results($items_sql);

        // Access Control: 管理員（WP 管理員或 BuyGo 管理員）可以查看所有訂單詳情
        if (!$this->is_admin_user($user)) {
             $has_access = false;
             foreach ($items as $item) {
                 if ($item->post_author == $user->ID) {
                     $has_access = true;
                     break;
                 }
             }
             if (!$has_access) {
                 return new WP_REST_Response(['success' => false, 'message' => 'Unauthorized access'], 403);
             }
        }

        // Format Customer Name
        $customer_name = $billing ? $billing->name : 'Guest';
        if ($customer_name === 'Guest' && !empty($order->user_id)) {
             $u = get_userdata($order->user_id);
             if ($u) $customer_name = $u->display_name;
        }

        // Avatar: Try LINE Avatar first, then WP Avatar
        $avatar_url = '';
        if (!empty($order->user_id)) {
             // Check for LINE picture in user meta (assuming key 'buygo_line_picture_url' or similiar)
             $line_pic = get_user_meta($order->user_id, 'buygo_line_picture_url', true);
             if ($line_pic) {
                 $avatar_url = $line_pic;
             } else {
                 $avatar_url = get_avatar_url($order->user_id, ['size' => 128]);
             }
        } elseif (!empty($customer_email)) {
             $avatar_url = get_avatar_url($customer_email, ['size' => 128]);
        }

        $formatted_items = [];
        foreach ($items as $item) {
            $item_name = !empty($item->product_title) ? $item->product_title : $item->title;
            
            // Fix Variation Title (e.g. remove " - Default" or " - 預設") by using Parent Title
            // In SQL result, post_id is likely the variation ID if it was a variation purchase
            $v_post = get_post($item->post_id);
            if ($v_post && $v_post->post_type === 'product_variation' && $v_post->post_parent) {
                $p_post = get_post($v_post->post_parent);
                if ($p_post) {
                    $item_name = $p_post->post_title;
                }
            }
            
            // Thumbnail
            $thumb_url = '';
            if (!empty($item->thumbnail_id)) {
                $thumb_url = wp_get_attachment_image_url($item->thumbnail_id, 'thumbnail'); // 150x150
            }

            // Parse Meta (Variations)
            // FluentCart stores meta as serialized array
            $raw_meta = maybe_unserialize($item->line_meta);
            $display_meta = [];
            
            if (is_array($raw_meta)) {
                // Common variation keys
                foreach ($raw_meta as $k => $v) {
                    // Filter out internal keys starting with _
                    if (strpos($k, '_') !== 0) {
                         $display_meta[$k] = $v;
                    }
                }
            }

            $formatted_items[] = [
                'id' => $item->id,
                'name' => $item_name,
                'image' => $thumb_url ?: '', // Fallback in frontend
                'quantity' => $item->quantity,
                'price' => number_format(($item->line_total / 100) / max($item->quantity, 1), 0),
                'total' => number_format(($item->line_total ?? 0) / 100, 0),
                'meta' => $display_meta 
            ];
        }

        $formatted_date = date('Y-m-d H:i', strtotime($order->created_at));

        // Get Notification History
        $logs_table = $wpdb->prefix . 'buygo_notification_logs';
        $notification_history = [];
        // Check if table exists (optional, but safe)
        // Check if table exists (optional, but safe)
        if ($wpdb->get_var("SHOW TABLES LIKE '{$logs_table}'") === $logs_table) {
             $notification_history = $wpdb->get_results($wpdb->prepare("SELECT type, channel, status, sent_at FROM {$logs_table} WHERE order_id = %d", $order_id));
        }

        // Fallback: If customer phone/email empty, use billing address
        if (empty($customer_phone) && $billing) {
            // Try direct phone field first
            if (!empty($billing->phone)) {
                $customer_phone = $billing->phone;
            } else {
                // Try meta['other_data']['phone'] (FluentCart stores phone in meta JSON)
                $meta_json = $billing->meta ?? null;
                if ($meta_json) {
                    $meta = json_decode($meta_json, true);
                    if (is_array($meta) && isset($meta['other_data']['phone']) && !empty($meta['other_data']['phone'])) {
                        $customer_phone = $meta['other_data']['phone'];
                    }
                }
            }
        }
        if (empty($customer_email) && $billing && !empty($billing->email)) {
            $customer_email = $billing->email;
        }
        
        // Also check shipping address meta if billing didn't have phone
        if (empty($customer_phone) && $shipping) {
            if (!empty($shipping->phone)) {
                $customer_phone = $shipping->phone;
            } else {
                $meta_json = $shipping->meta ?? null;
                if ($meta_json) {
                    $meta = json_decode($meta_json, true);
                    if (is_array($meta) && isset($meta['other_data']['phone']) && !empty($meta['other_data']['phone'])) {
                        $customer_phone = $meta['other_data']['phone'];
                    }
                }
            }
        }
        
        // Check customer addresses table (fct_customer_addresses) for phone
        if (empty($customer_phone) && !empty($order->customer_id)) {
            $table_customer_addresses = $wpdb->prefix . 'fct_customer_addresses';
            if ($wpdb->get_var("SHOW TABLES LIKE '{$table_customer_addresses}'") === $table_customer_addresses) {
                $customer_address = $wpdb->get_row($wpdb->prepare("
                    SELECT phone FROM {$table_customer_addresses} 
                    WHERE customer_id = %d 
                    AND phone IS NOT NULL 
                    AND phone != '' 
                    ORDER BY id DESC LIMIT 1
                ", $order->customer_id));
                if ($customer_address && !empty($customer_address->phone)) {
                    $customer_phone = $customer_address->phone;
                }
            }
        }

        // Recursive Finder (for user meta fallback)
        $find_phone_recursive = null;
        $find_phone_recursive = function($data) use (&$find_phone_recursive, $is_valid_phone) {
            if (is_array($data) || is_object($data)) {
                foreach ($data as $k => $v) {
                    // Check logic: value is phone OR key is phone and value is valid
                    if (is_string($v) && $is_valid_phone($v)) {
                         // Heuristic: Key contains phone/mobile OR value looks like 09xx
                         if (preg_match('/phone|mobile|tel|cell/i', $k) || preg_match('/^09\d{8}$/', $v)) {
                             return $v;
                         }
                    }
                    $res = $find_phone_recursive($v);
                    if ($res) return $res;
                }
            }
            return null;
        };

        // If phone is still invalid/empty, try aggressive User Meta scan (last resort)
        if (!$is_valid_phone($customer_phone) && !empty($order->user_id)) {
             $all_meta = get_user_meta($order->user_id);
             foreach ($all_meta as $key => $values) {
                 foreach ($values as $val) {
                     // 1. Try Deserialize if serialized
                     $u_val = maybe_unserialize($val);
                     
                     // 2. Direct string check
                     if (is_string($u_val)) {
                         if (preg_match('/phone|mobile|tel|cell/i', $key) && $is_valid_phone($u_val)) {
                             $customer_phone = $u_val; 
                             break 2;
                         }
                     }
                     
                     // 3. Deep array scan (for JSON or serialized objects)
                     if (is_array($u_val) || is_object($u_val)) {
                         $found = $find_phone_recursive($u_val);
                         if ($found) {
                             $customer_phone = $found;
                             break 2;
                         }
                     }
                 }
             }
             // If still nothing, try specific LINE keys if known
             if (!$is_valid_phone($customer_phone)) {
                 $line_phone = get_user_meta($order->user_id, 'fct_billing_phone', true); // Check FluentCart specific
                 if ($is_valid_phone($line_phone)) $customer_phone = $line_phone;
             }
        }
        
        // Final check for email from User Object if somehow missed
        if (empty($customer_email) && !empty($order->user_id)) {
             $u = get_userdata($order->user_id);
             if ($u) $customer_email = $u->user_email;
        }

        $response_data = [
            'id' => (int)$order->id,
            'root_phone' => $customer_phone, // DIRECT DEBUG EXPOSURE
            'order_number' => '#' . $order->id,
            'status' => $order->status,
             'payment_status' => $order->payment_status ?? 'pending',
             'shipping_status' => $order->shipping_status ?? 'pending',
            'created_at' => $formatted_date,
            'customer' => [
                'id' => (int)($order->customer_id ?? 0),
                'user_id' => (int)($order->user_id ?? 0),
                'name' => $customer_name,
                'email' => $customer_email,
                'phone' => $customer_phone,
                'avatar_url' => $avatar_url,
            ],
            'billing_address' => $billing ? [
                'first_name' => $billing->first_name ?? '',
                'last_name' => $billing->last_name ?? '',
                'name' => $billing->name ?? '',
                'email' => $billing->email ?? '',
                'phone' => $billing->phone ?? '',
                'address_1' => $billing->address_1 ?? '',
                'address_2' => $billing->address_2 ?? '',
                'city' => $billing->city ?? '',
                'state' => $billing->state ?? '',
                'postcode' => $billing->postcode ?? '',
                'country' => $billing->country ?? ''
            ] : null,
            'shipping_address' => $shipping ? [
                'first_name' => $shipping->first_name ?? '',
                'last_name' => $shipping->last_name ?? '',
                'name' => $shipping->name ?? '',
                'email' => $shipping->email ?? '',
                'phone' => $shipping->phone ?? '',
                'address_1' => $shipping->address_1 ?? '',
                'address_2' => $shipping->address_2 ?? '',
                'city' => $shipping->city ?? '',
                'state' => $shipping->state ?? '',
                'postcode' => $shipping->postcode ?? '',
                'country' => $shipping->country ?? ''
            ] : null,
            'items' => $formatted_items,
            'totals' => [
                'total' => number_format(($order->total_amount ?? 0) / 100, 0), // No decimals
                'currency' => $order->currency ?? 'TWD'
            ],
            'payment_method_title' => $order->payment_method_title ?? 'Unknown',
            'notes' => $order->note,
            'notification_history' => $notification_history
        ];

        return new WP_REST_Response([
            'success' => true,
            'data' => $response_data
        ], 200);
    }

    /**
     * Get Seller Orders (With Polling Support)
     */
    public function get_seller_orders(WP_REST_Request $request) {
        global $wpdb;
        $user = wp_get_current_user();
        
        // Params
        $after_id = $request->get_param('after_id');
        $status = $request->get_param('status'); // optional filter
        $per_page = $request->get_param('per_page') ?: 20;
        $page = $request->get_param('page') ?: 1;
        $offset = ($page - 1) * $per_page;

        $table_orders = $wpdb->prefix . 'fct_orders';
        $table_items = $wpdb->prefix . 'fct_order_items';
        $table_posts = $wpdb->posts;
        $table_addresses = $wpdb->prefix . 'fct_order_addresses';

        $where_clause = "1=1";

        // Seller Filter: 管理員（WP 管理員或 BuyGo 管理員）可以查看所有訂單
        if (!$this->is_admin_user($user)) {
             $where_clause .= $wpdb->prepare(" 
                AND o.id IN (
                    SELECT DISTINCT oi.order_id 
                    FROM {$table_items} oi
                    JOIN {$table_posts} p ON oi.post_id = p.ID
                    WHERE p.post_author = %d
                )
            ", $user->ID);
        }

        // 1. Polling Mode
        if ($after_id) {
            $where_clause .= $wpdb->prepare(" AND o.id > %d", intval($after_id));
        }

        // Status Filter
        if ($status && $status !== 'all') {
            $where_clause .= $wpdb->prepare(" AND o.status = %s", $status);
        }

        // Revert to simple query (Safety First)
        $sql = "SELECT o.* 
                FROM {$table_orders} o 
                WHERE {$where_clause} 
                ORDER BY o.id DESC 
                LIMIT %d OFFSET %d";
        
        $orders = $wpdb->get_results($wpdb->prepare($sql, $per_page, $offset));
        
        // Batch fetch items
        $items_map = [];
        if (!empty($orders)) {
            $order_ids = [];
            foreach ($orders as $o) {
                $order_ids[] = (int)$o->id;
            }
            if (!empty($order_ids)) {
                $ids_str = implode(',', $order_ids);
                // Reverted to simple query to ensure data visibility
                $items_sql = "SELECT order_id, title, quantity FROM {$table_items} WHERE order_id IN ($ids_str)";
                $raw_items = $wpdb->get_results($items_sql);
                
                foreach ($raw_items as $ri) {
                    if (!isset($items_map[$ri->order_id])) {
                        $items_map[$ri->order_id] = [];
                    }
                    $items_map[$ri->order_id][] = $ri->title . ' x ' . $ri->quantity;
                }
            }
        }

        // Enrich Data
        $data = [];
        foreach ($orders as $row) {
             $customer_name = '';
             $avatar_url = '';
             $user_id = $row->user_id;
             $db_items_str = isset($items_map[$row->id]) ? implode(', ', $items_map[$row->id]) : '';
             $model_items_str = '';

             // 0. Connect FluentCart Model
             if (class_exists('\FluentCart\App\Models\Order')) {
                 try {
                     $fct_order = \FluentCart\App\Models\Order::find($row->id);
                     if ($fct_order) {
                         // Name
                         if ($fct_order->customer) {
                             $customer_name = $fct_order->customer->first_name . ' ' . $fct_order->customer->last_name;
                         }
                         if (empty(trim($customer_name)) && $fct_order->billing_address) {
                             $customer_name = ($fct_order->billing_address['first_name'] ?? '') . ' ' . ($fct_order->billing_address['last_name'] ?? '');
                         }
                         
                         // Items: Bypass Model, CORRECTED SQL
                         $i_parts = [];
                         
                         // Explicitly ensure DB access in this scope
                         global $wpdb;
                         $t_items = $wpdb->prefix . 'fct_order_items';
                         
                         // Fetch items direct using correct column (post_id) match Detail View
                         $raw_order_items = $wpdb->get_results($wpdb->prepare(
                             "SELECT post_id, title, quantity FROM {$t_items} WHERE order_id = %d", 
                             $row->id
                         ));
                         
                         if (!empty($raw_order_items)) {
                             foreach ($raw_order_items as $rio) {
                                 $pid = $rio->post_id;
                                 $p_title = $rio->title;
                                 
                                 // SUPER FIX: Resolve Parent Title
                                 if ($pid) {
                                      $item_post = get_post($pid);
                                      if ($item_post) {
                                          if ($item_post->post_parent) {
                                              $parent_post = get_post($item_post->post_parent);
                                              if ($parent_post) $p_title = $parent_post->post_title;
                                          } else {
                                              $p_title = $item_post->post_title; // Use Post Title instead of stored title
                                          }
                                      }
                                 }
                                 $i_parts[] = $p_title . ' x ' . $rio->quantity;
                             }
                         }
                         
                         if (!empty($i_parts)) {
                             $model_items_str = implode(', ', $i_parts);
                         }
                     }
                 } catch (\Exception $e) {}
             }

             // 1. Fallback Logic for Name
             if (empty(trim($customer_name))) {
                  // Try Address Table first (most accurate for this specific order)
                  if (!empty($row->ba_first_name)) {
                      $customer_name = $row->ba_first_name . ' ' . $row->ba_last_name;
                  }
                  // Try WP User
                  elseif (!empty($user_id)) {
                      $u = get_userdata($user_id);
                      if ($u) $customer_name = $u->display_name;
                  }
                  // Try Order Table text
                  elseif (!empty($row->billing_first_name)) {
                      $customer_name = $row->billing_first_name . ' ' . $row->billing_last_name;
                  } else {
                      // Try legacy billing info
                      $u = get_user_by('email', $row->billing_email);
                      if ($u) $customer_name = $u->display_name;
                  }
             }
             $final_name = trim($customer_name) ?: 'Guest';

             // Avatar
             $email_for_avatar = $row->ba_email ?? $row->billing_email;
             if (!empty($user_id)) {
                  $avatar_url = get_avatar_url($user_id, ['size' => 64]);
             } elseif (!empty($email_for_avatar)) {
                  $avatar_url = get_avatar_url($email_for_avatar, ['size' => 64]);
             }
    
             $current_status = $row->status;
             $raw_total = $row->total_amount ?? $row->total ?? 0;
             $formatted_total = number_format($raw_total / 100, 0); 
    
             $created_val = $row->created_at ?? $row->date ?? current_time('mysql');
             $formatted_date = date('Y-m-d H:i', strtotime($created_val));
             
             // Prefer Model items, then DB items
             $item_summary = !empty($model_items_str) ? $model_items_str : $db_items_str;
             
             if (mb_strlen($item_summary) > 50) {
                  $item_summary = mb_substr($item_summary, 0, 47) . '...';
             }

             $data[] = [
                 'id' => (int)$row->id,
                 'order_number' => '#' . $row->id,
                 'customer_name' => $final_name,
                 'customer_email' => $email_for_avatar ?? '', 
                 'item_summary' => $item_summary, 
                 'avatar_url' => $avatar_url,
                 'status' => $current_status, 
                 'total' => $formatted_total,
                 'currency' => $row->currency ?? 'TWD',
                 'created_at' => $formatted_date,
                 'items_count' => count($items_map[$row->id]??[]), // fallback count
             ];
        }

        return new WP_REST_Response([
            'success' => true,
            'data' => $data,
            'meta' => [
                 'polling' => (bool)$after_id
            ]
        ], 200);
    }

    public function check_read_permission($request) {
        $user = wp_get_current_user();
        
        // Only allow logged-in users with correct roles
        return in_array('administrator', (array)$user->roles) || 
               in_array('buygo_admin', (array)$user->roles) ||
               in_array('buygo_seller', (array)$user->roles) || 
               in_array('buygo_helper', (array)$user->roles);
    }

    /**
     * 檢查是否為管理員（WP 管理員或 BuyGo 管理員）
     */
    private function is_admin_user($user = null) {
        if (!$user) {
            $user = wp_get_current_user();
        }
        
        return in_array('administrator', (array)$user->roles) || 
               in_array('buygo_admin', (array)$user->roles);
    }

    /**
     * Get Seller Dashboard Stats
     */
    public function get_seller_stats(WP_REST_Request $request) {
        global $wpdb;
        
        $user = wp_get_current_user();
        
        if (!$user->ID) {
            return new WP_REST_Response(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        // Tables
        $table_orders = $wpdb->prefix . 'fct_orders';
        $table_items = $wpdb->prefix . 'fct_order_items';
        $table_posts = $wpdb->posts;

        // Base Query Condition: Orders containing products authored by this seller
        // 管理員（WP 管理員或 BuyGo 管理員）可以查看所有訂單和統計
        
        $where_clause = "1=1";
        
        // If not admin, restrict to seller's products
        if (!$this->is_admin_user($user)) {
            // Logic: Join posts to filter by post_author
            // This is expensive, so in production we might cache this or add seller_id to order table
            $where_clause .= $wpdb->prepare(" 
                AND o.id IN (
                    SELECT DISTINCT oi.order_id 
                    FROM {$table_items} oi
                    JOIN {$table_posts} p ON oi.post_id = p.ID
                    WHERE p.post_author = %d
                )
            ", $user->ID);
        }

        // 1. Orders Today
        // Use same status filter as backend dashboard: exclude cancelled, refunded, failed
        $today_start = current_time('Y-m-d 00:00:00');
        $today_end = current_time('Y-m-d 23:59:59');
        
        $sql_today = "SELECT COUNT(*) FROM {$table_orders} o WHERE {$where_clause} AND o.created_at BETWEEN %s AND %s AND o.status NOT IN ('cancelled', 'refunded', 'failed')";
        $orders_today = $wpdb->get_var($wpdb->prepare($sql_today, $today_start, $today_end));

        // 2. Revenue Today (Sum of total_amount)
        // Use same status filter as backend dashboard and divide by 100 (FluentCart stores in cents)
        $sql_revenue = "SELECT SUM(total_amount) FROM {$table_orders} o WHERE {$where_clause} AND o.created_at BETWEEN %s AND %s AND o.status NOT IN ('cancelled', 'refunded', 'failed')";
        $revenue_today_raw = $wpdb->get_var($wpdb->prepare($sql_revenue, $today_start, $today_end));
        // FluentCart stores amounts in cents, divide by 100 to get actual currency value
        $revenue_today = $revenue_today_raw ? ($revenue_today_raw / 100) : 0;

        // 3. Pending Orders (Total count with status 'pending' or 'processing')
        $sql_pending = "SELECT COUNT(*) FROM {$table_orders} o WHERE {$where_clause} AND o.status IN ('pending', 'processing')";
        $pending_count = $wpdb->get_var($sql_pending);

        // 4. Recent Activity (Simplified: Last 5 orders)
        $sql_recent = "SELECT o.id, o.billing_first_name, o.total_amount, o.status, o.created_at 
                       FROM {$table_orders} o 
                       WHERE {$where_clause} 
                       ORDER BY o.id DESC LIMIT 5";
        $recent_orders = $wpdb->get_results($sql_recent);

        $response_data = [
            'orders_today' => (int)$orders_today,
            'revenue_today' => (float)$revenue_today, // Frontend can format currency
            'pending_shipment' => (int)$pending_count,
            'recent_activity' => $recent_orders
        ];

        return new WP_REST_Response([
            'success' => true,
            'data' => $response_data
        ], 200);
    }

    /**
     * Send Order Notification (One-Click Push)
     */
    public function send_order_notification(WP_REST_Request $request) {
        global $wpdb;
        $order_id = $request->get_param('id');
        $type = $request->get_param('type'); // e.g. 'order_shipped'
        $channels = $request->get_param('channels'); // ['line', 'email']
        
        if (empty($channels) || !is_array($channels)) {
            return new WP_REST_Response(['success' => false, 'message' => 'No channels specified'], 400);
        }

        // 1. Get Order & Customer Info
        $table_orders = $wpdb->prefix . 'fct_orders';
        $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_orders} WHERE id = %d", $order_id));
        
        if (!$order) {
            return new WP_REST_Response(['success' => false, 'message' => 'Order not found'], 404);
        }

        // Get Customer User
        $user = null;
        if (!empty($order->user_id)) {
            $user = get_userdata($order->user_id);
        }

        // Prepare Template Args
        $args = [
            'order_id' => $order->id,
            'note' => $order->note ?: '無',
        ];

        // 2. Load Template
        $template_key = $type;
        if ($type === 'shipped') $template_key = 'order_shipped';
        if ($type === 'payment_received') $template_key = 'order_paid';
        
        $content = \BuyGo\Core\Services\NotificationTemplates::get($template_key, $args);
        
        if (!$content) {
            return new WP_REST_Response(['success' => false, 'message' => 'Invalid notification type'], 400);
        }

        // 3. Send & Log
        $logs_table = $wpdb->prefix . 'buygo_notification_logs';
        $results = [];

        foreach ($channels as $channel) {
            // Check duplicate
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$logs_table} WHERE order_id = %d AND type = %s AND channel = %s AND status = 'sent'",
                $order_id, $template_key, $channel
            ));

            if ($exists) {
                $results[$channel] = 'skipped_duplicate';
                continue;
            }

            $sent = false;
            $log_message = '';
            $log_title = '';

            if ($channel === 'email') {
                $email = $order->billing_email;
                if ($user && empty($email)) $email = $user->user_email;

                if ($email && !empty($content['email']['subject'])) {
                    $log_title = $content['email']['subject'];
                    $log_message = $content['email']['message'];
                    $sent = wp_mail($email, $log_title, $log_message);
                }
            } elseif ($channel === 'line') {
                $line_service = \BuyGo\Core\App::instance()->make(\BuyGo\Core\Services\LineService::class);
                $line_uid = '';
                
                if ($user) {
                    $line_uid = $line_service->get_line_uid($user->ID);
                }
                
                if ($line_uid && !empty($content['line'])) {
                    $log_title = 'LINE Push';
                    $log_message = $content['line']['text'] ?? '';
                    $sent = $line_service->send_push_message($line_uid, $content['line']);
                }
            }

            if ($sent || $log_message) {
                $wpdb->insert(
                    $logs_table,
                    [
                        'user_id' => $user ? $user->ID : 0,
                        'order_id' => $order_id,
                        'type' => $template_key,
                        'channel' => $channel,
                        'title' => substr($log_title, 0, 255),
                        'message' => $log_message,
                        'status' => $sent ? 'sent' : 'failed',
                        'sent_at' => current_time('mysql'),
                        'meta' => json_encode($args, JSON_UNESCAPED_UNICODE)
                    ],
                    ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
                );
                $results[$channel] = $sent ? 'sent' : 'failed';
            } else {
                 $results[$channel] = 'failed_no_contact_info';
            }
        }

        return new WP_REST_Response([
            'success' => true,
            'results' => $results
        ], 200);
    }

    public function check_write_permission($request) {
        $user = wp_get_current_user();
        if (in_array('administrator', (array)$user->roles) || 
            in_array('buygo_admin', (array)$user->roles) ||
            in_array('buygo_seller', (array)$user->roles) || 
            in_array('buygo_helper', (array)$user->roles)) {
            return true;
        }
        return false;
    }

    /**
     * Export Seller Orders to CSV (Direct Stream)
     */
    public function export_seller_orders_csv(WP_REST_Request $request) {
        global $wpdb;
        $user = wp_get_current_user();
        
        // Params (Status filter supported)
        $status = $request->get_param('status'); 

        $table_orders = $wpdb->prefix . 'fct_orders';
        $table_items = $wpdb->prefix . 'fct_order_items';
        $table_posts = $wpdb->posts;
        $table_customers = $wpdb->prefix . 'fct_customers';
        $table_addresses = $wpdb->prefix . 'fct_order_addresses';

        $where_clause = "1=1";

        // Seller Filter: 管理員（WP 管理員或 BuyGo 管理員）可以匯出所有訂單
        if (!$this->is_admin_user($user)) {
             $where_clause .= $wpdb->prepare(" 
                AND o.id IN (
                    SELECT DISTINCT oi.order_id 
                    FROM {$table_items} oi
                    JOIN {$table_posts} p ON oi.post_id = p.ID
                    WHERE p.post_author = %d
                )
            ", $user->ID);
        }

        if ($status && $status !== 'all') {
            $where_clause .= $wpdb->prepare(" AND o.status = %s", $status);
        }

        // Get Orders
        $sql = "SELECT o.* FROM {$table_orders} o WHERE {$where_clause} ORDER BY o.id DESC";
        $orders = $wpdb->get_results($sql);

        // Output CSV Headers
        $filename = 'orders_export_' . date('Y-m-d_His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');

        $fp = fopen('php://output', 'w');
        // Add BOM for Excel UTF-8
        fputs($fp, "\xEF\xBB\xBF");
        
        // Helper to validate phone
        $is_valid_phone = function($p) {
            return is_string($p) && !empty($p) && strlen(preg_replace('/[^0-9]/', '', $p)) > 5;
        };

        // Header Row
        fputcsv($fp, ['訂單編號', '日期', '顧客姓名', '電話', '收件地址', '商品明細 (名稱/規格/數量)', '總金額', '訂單狀態', '備註']);

        foreach ($orders as $order) {
            $name = '';
            $phone = '';
            $address = '';
            $items_str = '';
            
            // Identify Email for Lookup
            $lookup_email = $order->billing_email ?? '';
            
            // -1. First, check fct_customers table for updated data (highest priority)
            if (!empty($order->customer_id)) {
                $cust = $wpdb->get_row($wpdb->prepare(
                    "SELECT first_name, last_name, phone, email FROM {$table_customers} WHERE id = %d",
                    $order->customer_id
                ));
                if ($cust) {
                    $name = trim(($cust->first_name ?? '') . ' ' . ($cust->last_name ?? ''));
                    if (!empty($name)) {
                        // Use customer table name if available
                    }
                    if ($is_valid_phone($cust->phone)) {
                        $phone = $cust->phone;
                    }
                    if (!empty($cust->email)) {
                        $lookup_email = $cust->email;
                    }
                }
            }
            
            // -2. Check billing address for updated phone/email (from order_addresses table) - HIGHEST PRIORITY
            $billing_addr = $wpdb->get_row($wpdb->prepare(
                "SELECT name, meta, address_1, city, state, postcode FROM {$table_addresses} WHERE order_id = %d AND type = 'billing'",
                $order->id
            ));
            if ($billing_addr) {
                // Get phone and email from meta (where we store updated values)
                $meta = json_decode($billing_addr->meta ?? '{}', true);
                if (is_array($meta)) {
                    // Phone from meta has highest priority
                    if ($is_valid_phone($meta['phone'] ?? '')) {
                        $phone = $meta['phone'];
                    }
                    // Email from meta has highest priority
                    if (!empty($meta['email'] ?? '')) {
                        $lookup_email = $meta['email'];
                    }
                }
                // Use billing address name if customer name is empty
                if (empty($name) && !empty($billing_addr->name)) {
                    $name = $billing_addr->name;
                }
            }
            
            // -3. Try FluentCart Model (mainly for Items & fallback data)
            $fct_order = null;
            if (class_exists('\FluentCart\App\Models\Order')) {
                try {
                    $fct_order = \FluentCart\App\Models\Order::find($order->id);
                    if ($fct_order) {
                         if (empty($lookup_email) && $fct_order->customer) $lookup_email = $fct_order->customer->email;
                         
                         // Pre-fill from FCT Model only if not already set
                         if ($fct_order->customer) {
                             if (empty($name)) {
                                 $name = trim($fct_order->customer->first_name . ' ' . $fct_order->customer->last_name);
                             }
                             if (empty($phone) && $is_valid_phone($fct_order->customer->phone ?? '')) {
                                 $phone = $fct_order->customer->phone;
                             }
                         }
                         
                         // Items Logic (Fixing "Default" by checking Variation Parent)
                         foreach ($fct_order->items as $item) {
                             $p_title = $item->product->title ?? '';
                             
                             // Check if it's a variation and get Parent Title
                             if (!empty($item->product_id)) {
                                 $item_post = get_post($item->product_id);
                                 if ($item_post && $item_post->post_type === 'product_variation' && $item_post->post_parent) {
                                     $parent_post = get_post($item_post->post_parent);
                                     if ($parent_post) {
                                         $p_title = $parent_post->post_title;
                                     }
                                 }
                             }
                             
                             $items_str .= $p_title . ' x ' . $item->quantity . "\n";
                         }
                    }
                } catch (\Exception $e) {}
            }
            
            // 0. The FluentCRM OVERRIDE (Source of Truth) - but only if we don't have updated data
            // Skip FluentCRM if we already have phone/name from updated tables
            if (defined('FLUENTCRM') && !empty($lookup_email) && (empty($phone) || empty($name))) {
                try {
                    $contact = \FluentCrm\App\Models\Subscriber::where('email', $lookup_email)->first();
                    if ($contact) {
                        // Name - only use if not already set from customer table
                        if (empty($name)) {
                            $crm_name = trim($contact->first_name . ' ' . $contact->last_name);
                            if (!empty($crm_name)) $name = $crm_name;
                        }
                        
                        // Phone - only use if not already set from customer/address table
                        if (empty($phone)) {
                            if ($is_valid_phone($contact->phone)) $phone = $contact->phone;
                            elseif ($is_valid_phone($contact->mobile)) $phone = $contact->mobile;
                        }
                        
                        // Address - only use if not already set
                        if (empty($address)) {
                            $addr_parts = [
                                $contact->address_line_1,
                                $contact->address_line_2,
                                $contact->city,
                                $contact->state,
                                $contact->postal_code,
                                $contact->country
                            ];
                            $address = implode(' ', array_filter($addr_parts));
                        }
                    }
                } catch (\Exception $e) {}
            }

            // 1. Get Address from billing_address (order_addresses table) - highest priority
            if (empty($address) && isset($billing_addr)) {
                $addr_parts = [];
                if (!empty($billing_addr->postcode)) $addr_parts[] = $billing_addr->postcode;
                if (!empty($billing_addr->city)) $addr_parts[] = $billing_addr->city;
                if (!empty($billing_addr->state)) $addr_parts[] = $billing_addr->state;
                if (!empty($billing_addr->address_1)) $addr_parts[] = $billing_addr->address_1;
                $address = implode(' ', array_filter($addr_parts));
            }
            
            // 1b. Fallback Address (if order_addresses missed)
            if (empty($address)) {
                $address = trim(($order->billing_address_1 ?? '') . ' ' . ($order->billing_city ?? '') . ' ' . ($order->billing_state ?? ''));
                if (empty($address) && $fct_order && $fct_order->billing_address) {
                    $b = $fct_order->billing_address;
                    $address = implode(' ', array_filter([$b['address_1']??'', $b['city']??'', $b['state']??'', $b['postcode']??''])); 
                }
            }

            // 2. Fallback Name - only if not already set
            if (empty($name)) {
                $name = trim(($order->billing_first_name ?? '') . ' ' . ($order->billing_last_name ?? ''));
            }
            if (empty($name) && $order->user_id) {
                $u = get_userdata($order->user_id);
                if ($u) $name = $u->display_name;
            }
            if (empty($name)) $name = 'Guest';
            
            // Ensure phone is not empty (final fallback)
            if (empty($phone)) {
                $phone = '';
            }

            // 3. Fallback for Phone (SQL) - if not set by above methods
            if (!$is_valid_phone($phone)) {
                 $phone = $order->billing_phone ?? '';
            }

            // 4. Fallback for Phone (Meta Scan) - if not set by CRM, FCT Model, or SQL
            if (!$is_valid_phone($phone) && !empty($order->user_id)) {
                // Recursive Finder (moved here to be used as a fallback)
                $find_phone_recursive = null;
                $find_phone_recursive = function($data) use (&$find_phone_recursive, $is_valid_phone) {
                    if (is_array($data) || is_object($data)) {
                        foreach ($data as $k => $v) {
                            if (is_string($v) && $is_valid_phone($v)) {
                                 if (preg_match('/phone|mobile|tel|cell/i', $k) || preg_match('/^09\d{8}$/', $v)) {
                                     return $v;
                                 }
                            }
                            $res = $find_phone_recursive($v);
                            if ($res) return $res;
                        }
                    }
                    return null;
                };

                 $all_meta = get_user_meta($order->user_id);
                 foreach ($all_meta as $key => $values) {
                     foreach ($values as $val) {
                         $u_val = maybe_unserialize($val);
                         if (is_string($u_val)) {
                             if (preg_match('/phone|mobile|tel|cell/i', $key) && $is_valid_phone($u_val)) {
                                 $phone = $u_val; 
                                 break 2;
                             }
                         }
                         if (is_array($u_val) || is_object($u_val)) {
                             $found = $find_phone_recursive($u_val);
                             if ($found) {
                                 $phone = $found;
                                 break 2;
                             }
                         }
                     }
                 }
            }

            // 5. Fallback for Items (SQL)
            if (empty($items_str)) {
                $items = $wpdb->get_results($wpdb->prepare(
                    "SELECT title, quantity, post_id FROM {$table_items} WHERE order_id = %d", 
                    $order->id
                ));
                foreach ($items as $item) {
                    $i_title = $item->title;
                    if (in_array($i_title, ['預設', 'Default', 'default']) && $item->post_id) {
                        $p = get_post($item->post_id);
                        if ($p) $i_title = $p->post_title;
                    }
                    $items_str .= $i_title . ' x ' . $item->quantity . "\n";
                }
            }

            $formatted_total = number_format(($order->total_amount ?? 0) / 100, 0);
            
            // Map Status
            $status_map = [
                'completed' => '已完成',
                'processing' => '處理中 (已付款)',
                'pending' => '待處理',
                'shipped' => '已出貨',
                'on-hold' => '等待付款',
                'cancelled' => '已取消'
            ];
            $status_label = $status_map[$order->status] ?? $order->status;

            if (!empty($order->shipping_status) && $order->shipping_status === 'shipped') {
                $current_status .= ' (已出貨)';
            }

            fputcsv($fp, [
                '#' . $order->id,
                $order->created_at,
                $name,
                $phone,
                $address,
                trim($items_str),
                $formatted_total,
                $current_status,
                $order->note
            ]);
        }
        
        fclose($fp);
        exit; // Stop execution to return raw file
    }

    /**
     * Get Seller Products
     */
    public function get_seller_products(WP_REST_Request $request) {
        global $wpdb;
        $user = wp_get_current_user();
        
        $page = $request->get_param('page') ?: 1;
        $per_page = $request->get_param('per_page') ?: 20;
        $offset = ($page - 1) * $per_page;
        
        $table_posts = $wpdb->posts;
        
        // Query: Get products - FluentCart uses 'fluent-products' as post_type
        // 管理員（WP 管理員或 BuyGo 管理員）可以查看所有商品，賣家只能查看自己的商品
        if ($this->is_admin_user($user)) {
            $sql = $wpdb->prepare("
                SELECT p.ID, p.post_title, p.post_status, p.post_date, p.post_author
                FROM {$table_posts} p
                WHERE p.post_type = 'fluent-products' 
                AND p.post_status IN ('publish', 'draft', 'pending', 'private')
                ORDER BY p.ID DESC
                LIMIT %d OFFSET %d
            ", $per_page, $offset);
        } else {
            $sql = $wpdb->prepare("
                SELECT p.ID, p.post_title, p.post_status, p.post_date, p.post_author
                FROM {$table_posts} p
                WHERE p.post_type = 'fluent-products' 
                AND p.post_status IN ('publish', 'draft', 'pending', 'private')
                AND p.post_author = %d
                ORDER BY p.ID DESC
                LIMIT %d OFFSET %d
            ", $user->ID, $per_page, $offset);
        }
        
        $posts = $wpdb->get_results($sql);
        
        // Enrich Data
        $data = [];
        $table_details = $wpdb->prefix . 'fct_product_details';
        $table_variations = $wpdb->prefix . 'fct_product_variations';
        
        foreach ($posts as $p) {
            $product_id = $p->ID;
            
            // Get price from FluentCart product_details or variations
            $price = 0;
            $details = $wpdb->get_row($wpdb->prepare(
                "SELECT min_price, max_price FROM {$table_details} WHERE post_id = %d LIMIT 1",
                $product_id
            ));
            
            if ($details) {
                $price = (float)$details->min_price; // Price in cents
            } else {
                // Fallback to variation price
                $variation = $wpdb->get_row($wpdb->prepare(
                    "SELECT item_price FROM {$table_variations} WHERE post_id = %d ORDER BY serial_index ASC LIMIT 1",
                    $product_id
                ));
                if ($variation) {
                    $price = (float)$variation->item_price;
                }
            }
            
            // Get stock from variations
            $stock = 0;
            $stock_status = 'out-of-stock';
            $variation = $wpdb->get_row($wpdb->prepare(
                "SELECT available, total_stock, stock_status FROM {$table_variations} WHERE post_id = %d ORDER BY serial_index ASC LIMIT 1",
                $product_id
            ));
            
            if ($variation) {
                $stock = (int)($variation->available ?? $variation->total_stock ?? 0);
                $stock_status = $variation->stock_status ?? 'out-of-stock';
            }
            
            // Get thumbnail
            $thumbnail_id = get_post_thumbnail_id($product_id);
            $image = $thumbnail_id ? wp_get_attachment_image_url($thumbnail_id, 'thumbnail') : '';
            
            // Status Label
            $status_map = [
                'publish' => '已發布',
                'draft' => '草稿',
                'pending' => '審核中',
                'private' => '私人',
                'trash' => '垃圾桶'
            ];
            
            // Format price (FluentCart stores price in cents)
            $formatted_price = 'NT$ ' . number_format((float)$price / 100, 2);
            
            // Stock status label
            $stock_status_label = '缺貨';
            if ($stock_status === 'instock' || $stock > 0) {
                $stock_status_label = '現貨';
            } else if ($stock_status === 'onbackorder') {
                $stock_status_label = '補貨中';
            }

            $data[] = [
                'id' => $p->ID,
                'name' => $p->post_title,
                'price' => $price,
                'formatted_price' => $formatted_price,
                'stock' => $stock,
                'status' => $p->post_status,
                'status_label' => $status_map[$p->post_status] ?? $p->post_status,
                'stock_status' => $stock_status_label,
                'image' => $image,
                'created_at' => $p->post_date
            ];
        }
        
        return new WP_REST_Response([
            'success' => true,
            'data' => $data
        ], 200);
    }

    /**
     * Get Sales Growth Chart Data
     */
    public function get_sales_growth(WP_REST_Request $request) {
        global $wpdb;
        $table_orders = $wpdb->prefix . 'fct_orders';
        $table_items = $wpdb->prefix . 'fct_order_items';
        
        // Get date range (default: last 30 days)
        $days = $request->get_param('days') ?: 30;
        $start_date = date('Y-m-d', strtotime("-{$days} days"));
        $end_date = date('Y-m-d');
        
        // Get daily revenue and orders
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT 
                DATE(o.created_at) as date,
                SUM(o.total_amount) as revenue,
                COUNT(DISTINCT o.id) as orders
            FROM {$table_orders} o
            WHERE DATE(o.created_at) BETWEEN %s AND %s
            AND o.status NOT IN ('cancelled', 'refunded', 'failed')
            GROUP BY DATE(o.created_at)
            ORDER BY date ASC
        ", $start_date, $end_date));
        
        $labels = [];
        $revenue_data = [];
        $orders_data = [];
        
        // Fill in all dates (even if no orders)
        $current = strtotime($start_date);
        $end = strtotime($end_date);
        $data_map = [];
        
        foreach ($results as $row) {
            $data_map[$row->date] = [
                'revenue' => (float)$row->revenue,
                'orders' => (int)$row->orders
            ];
        }
        
        while ($current <= $end) {
            $date_str = date('Y-m-d', $current);
            $labels[] = date('M d', $current);
            $revenue_data[] = isset($data_map[$date_str]) ? $data_map[$date_str]['revenue'] : 0;
            $orders_data[] = isset($data_map[$date_str]) ? $data_map[$date_str]['orders'] : 0;
            $current = strtotime('+1 day', $current);
        }
        
        return new WP_REST_Response([
            'success' => true,
            'data' => [
                'labels' => $labels,
                'revenue' => $revenue_data,
                'orders' => $orders_data
            ]
        ], 200);
    }

    /**
     * Get Recent Orders for Dashboard
     */
    public function get_recent_orders(WP_REST_Request $request) {
        global $wpdb;
        $table_orders = $wpdb->prefix . 'fct_orders';
        $table_items = $wpdb->prefix . 'fct_order_items';
        
        $limit = $request->get_param('limit') ?: 5;
        
        $orders = $wpdb->get_results($wpdb->prepare("
            SELECT 
                o.id,
                o.order_number,
                o.billing_first_name,
                o.billing_last_name,
                o.total_amount,
                o.currency,
                o.created_at,
                COUNT(oi.id) as item_count
            FROM {$table_orders} o
            LEFT JOIN {$table_items} oi ON o.id = oi.order_id
            WHERE o.status NOT IN ('cancelled', 'refunded', 'failed')
            GROUP BY o.id
            ORDER BY o.id DESC
            LIMIT %d
        ", $limit));
        
        $data = [];
        foreach ($orders as $order) {
            $customer_name = trim(($order->billing_first_name ?? '') . ' ' . ($order->billing_last_name ?? ''));
            if (empty($customer_name)) {
                $customer_name = 'Guest';
            }
            
            $data[] = [
                'id' => $order->id,
                'order_number' => $order->order_number ?: '#' . $order->id,
                'customer' => $customer_name,
                'amount' => (float)$order->total_amount,
                'currency' => $order->currency ?: 'TWD',
                'item_count' => (int)$order->item_count,
                'created_at' => $order->created_at,
                'formatted_date' => $this->format_time($order->created_at)
            ];
        }
        
        return new WP_REST_Response([
            'success' => true,
            'data' => $data
        ], 200);
    }

    /**
     * Get Hot Selling Products
     */
    public function get_hot_products(WP_REST_Request $request) {
        global $wpdb;
        $table_orders = $wpdb->prefix . 'fct_orders';
        $table_items = $wpdb->prefix . 'fct_order_items';
        $table_posts = $wpdb->posts;
        
        $limit = $request->get_param('limit') ?: 5;
        
        $products = $wpdb->get_results($wpdb->prepare("
            SELECT 
                p.ID,
                p.post_title,
                SUM(oi.quantity) as total_sold,
                SUM(oi.line_total) as total_revenue
            FROM {$table_items} oi
            JOIN {$table_posts} p ON oi.post_id = p.ID
            JOIN {$table_orders} o ON oi.order_id = o.id
            WHERE p.post_type = 'product'
            AND p.post_status = 'publish'
            AND o.status NOT IN ('cancelled', 'refunded', 'failed')
            GROUP BY p.ID
            ORDER BY total_sold DESC
            LIMIT %d
        ", $limit));
        
        $data = [];
        foreach ($products as $product) {
            $image_id = get_post_thumbnail_id($product->ID);
            $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : null;
            
            $data[] = [
                'id' => $product->ID,
                'name' => $product->post_title,
                'total_sold' => (int)$product->total_sold,
                'total_revenue' => (float)$product->total_revenue,
                'image' => $image_url
            ];
        }
        
        return new WP_REST_Response([
            'success' => true,
            'data' => $data
        ], 200);
    }

    /**
     * Get Dashboard Stats (Members, Revenue, etc.)
     */
    public function get_dashboard_stats(WP_REST_Request $request) {
        global $wpdb;
        
        // Get total active members (users with any role except deleted/spam)
        $user_counts = count_users();
        $total_members = isset($user_counts['total_users']) ? (int)$user_counts['total_users'] : 0;
        
        // Get new members this week
        $week_start = date('Y-m-d 00:00:00', strtotime('monday this week'));
        $week_end = date('Y-m-d 23:59:59', strtotime('sunday this week'));
        
        $new_members_this_week = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->users} 
            WHERE user_registered BETWEEN %s AND %s
        ", $week_start, $week_end));
        
        // Get today's revenue
        $table_orders = $wpdb->prefix . 'fct_orders';
        $today_start = current_time('Y-m-d 00:00:00');
        $today_end = current_time('Y-m-d 23:59:59');
        
        $revenue_today_raw = $wpdb->get_var($wpdb->prepare("
            SELECT SUM(total_amount) 
            FROM {$table_orders} 
            WHERE created_at BETWEEN %s AND %s 
            AND status NOT IN ('cancelled', 'refunded', 'failed')
        ", $today_start, $today_end));
        // FluentCart stores amounts in cents, divide by 100 to get actual currency value
        $revenue_today = $revenue_today_raw ? ($revenue_today_raw / 100) : 0;
        
        // Get yesterday's revenue for comparison
        $yesterday_start = date('Y-m-d 00:00:00', strtotime('-1 day'));
        $yesterday_end = date('Y-m-d 23:59:59', strtotime('-1 day'));
        
        $revenue_yesterday_raw = $wpdb->get_var($wpdb->prepare("
            SELECT SUM(total_amount) 
            FROM {$table_orders} 
            WHERE created_at BETWEEN %s AND %s 
            AND status NOT IN ('cancelled', 'refunded', 'failed')
        ", $yesterday_start, $yesterday_end));
        // FluentCart stores amounts in cents, divide by 100 to get actual currency value
        $revenue_yesterday = $revenue_yesterday_raw ? ($revenue_yesterday_raw / 100) : 0;
        
        // Calculate revenue growth percentage
        $revenue_growth = 0;
        if ($revenue_yesterday > 0) {
            $revenue_growth = round((($revenue_today - $revenue_yesterday) / $revenue_yesterday) * 100, 1);
        } elseif ($revenue_today > 0) {
            $revenue_growth = 100; // 100% growth if yesterday was 0
        }
        
        // Get LINE message count for this month
        $logs_table = $wpdb->prefix . 'buygo_notification_logs';
        $month_start = date('Y-m-01 00:00:00');
        $month_end = date('Y-m-t 23:59:59');
        
        $line_messages_this_month = 0;
        if ($wpdb->get_var("SHOW TABLES LIKE '{$logs_table}'") === $logs_table) {
            $line_messages_this_month = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*) 
                FROM {$logs_table} 
                WHERE channel = 'line' 
                AND status = 'sent'
                AND sent_at BETWEEN %s AND %s
            ", $month_start, $month_end));
        }
        
        // Get monthly quota from settings (default: 20000)
        $monthly_quota = get_option('buygo_line_monthly_quota', 20000);
        
        return new WP_REST_Response([
            'success' => true,
            'data' => [
                'total_members' => $total_members,
                'new_members_this_week' => (int)$new_members_this_week,
                'revenue_today' => (float)$revenue_today,
                'revenue_growth' => $revenue_growth,
                'line_messages_this_month' => (int)$line_messages_this_month,
                'line_monthly_quota' => (int)$monthly_quota
            ]
        ], 200);
    }

    private function format_time($datetime) {
        if (empty($datetime)) {
            return '未知時間';
        }
        
        $timestamp = strtotime($datetime);
        $now = current_time('timestamp');
        $diff = $now - $timestamp;
        
        if ($diff < 60) {
            return '剛剛';
        } elseif ($diff < 3600) {
            return floor($diff / 60) . ' 分鐘前';
        } elseif ($diff < 86400) {
            return floor($diff / 3600) . ' 小時前';
        } elseif ($diff < 604800) {
            return floor($diff / 86400) . ' 天前';
        } else {
            return date('m月 d日 g:i A', $timestamp);
        }
    }
}
