<?php

namespace BuyGo\Core\Api;

use WP_REST_Request;
use WP_REST_Response;

class ProductController extends BaseController {

    public function register_routes() {
        register_rest_route($this->namespace, '/products', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_items'],
                'permission_callback' => [$this, 'check_read_permission'],
            ]
        ]);
    }

    /**
     * Check read permission - allow admin, buygo_admin, buygo_seller, and buygo_helper
     */
    public function check_read_permission($request) {
        $user = wp_get_current_user();
        
        return in_array('administrator', (array)$user->roles) || 
               in_array('buygo_admin', (array)$user->roles) ||
               in_array('buygo_seller', (array)$user->roles) || 
               in_array('buygo_helper', (array)$user->roles);
    }

    /**
     * Get Products List
     */
    public function get_items(WP_REST_Request $request) {
        global $wpdb;
        
        $user = wp_get_current_user();
        
        $page = $request->get_param('page') ?: 1;
        $per_page = $request->get_param('per_page') ?: 50;
        $offset = ($page - 1) * $per_page;
        $status = $request->get_param('status');
        $search = $request->get_param('search');
        
        $table_posts = $wpdb->posts;
        
        // Build SQL query - FluentCart uses 'fluent-products' as post_type
        $where = [
            "p.post_type = 'fluent-products'",
            "p.post_status IN ('publish', 'draft', 'pending', 'private')"
        ];
        
        // Status filter
        if ($status && $status !== 'all') {
            if ($status === 'publish') {
                $where[] = "p.post_status = 'publish'";
            } else if ($status === 'draft') {
                $where[] = "p.post_status = 'draft'";
            }
        }
        
        // Search filter
        if ($search) {
            $where[] = $wpdb->prepare("(p.post_title LIKE %s OR p.ID = %d)", '%' . $wpdb->esc_like($search) . '%', intval($search));
        }
        
        // For non-admin users, filter by author
        // 管理員（WP 管理員或 BuyGo 管理員）可以查看所有商品
        $is_admin = in_array('administrator', (array)$user->roles) || in_array('buygo_admin', (array)$user->roles);
        if (!$is_admin) {
            $where[] = $wpdb->prepare("p.post_author = %d", $user->ID);
        }
        
        $where_clause = implode(' AND ', $where);
        
        $sql = "
            SELECT p.ID, p.post_title, p.post_status, p.post_date, p.post_author,
                   u.display_name as seller_name, u.user_email as seller_email
            FROM {$table_posts} p
            LEFT JOIN {$wpdb->users} u ON p.post_author = u.ID
            WHERE {$where_clause}
            ORDER BY p.ID DESC
            LIMIT %d OFFSET %d
        ";
        
        $sql = $wpdb->prepare($sql, $per_page, $offset);
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
            
            // Get seller info
            $seller = null;
            if ($p->post_author) {
                $seller_user = get_userdata($p->post_author);
                if ($seller_user) {
                    $seller = [
                        'id' => $seller_user->ID,
                        'name' => $seller_user->display_name ?: $seller_user->user_login,
                        'email' => $seller_user->user_email
                    ];
                }
            }
            
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
            
            $data[] = [
                'id' => $p->ID,
                'name' => $p->post_title,
                'price' => $price,
                'formatted_price' => $formatted_price,
                'stock' => $stock,
                'status' => $p->post_status,
                'status_label' => $status_map[$p->post_status] ?? $p->post_status,
                'stock_status' => $stock_status,
                'image' => $image,
                'created_at' => $p->post_date,
                'seller' => $seller
            ];
        }
        
        return new WP_REST_Response([
            'success' => true,
            'data' => $data
        ], 200);
    }
}
