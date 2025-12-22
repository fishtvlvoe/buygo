<?php

namespace BuyGo\Core\Services;

use BuyGo\Core\Services\RoleManager;

class FluentCartIntegrationService {

    public function __construct() {
        // Record Seller ownership when order is created
        add_action('fluent_cart/order_created', [$this, 'record_order_ownership'], 10, 1);
        
        // Filter Orders for Sellers (Backend/Frontend query)
        // Check if FluentCart uses a specific filter for its query
        add_filter('fluent_cart/orders_query_args', [$this, 'filter_seller_orders'], 10, 1);
        
        // If FluentCart uses WP_Query (e.g. CPT 'fluent_order')
        add_action('pre_get_posts', [$this, 'restrict_media_library_for_sellers']);
    }

    /**
     * When an order is created, identify which sellers are involved and save meta.
     * 
     * @param object|int $order (Assuming ID or Object)
     */
    public function record_order_ownership($order) {
        // We need to fetch the order object if strictly ID passed
        // For now assume generic object access or look up
        
        // This part relies heavily on knowing FluentCart's Order Object structure.
        // Assuming it has ->items or we can get items.
        // If we don't know, we might fail.
        // Let's assume standard behavior: get items -> get product -> get post_author (seller)
        
        // Placeholder for Logic
        // $items = $order->getItems(); 
        // $seller_ids = [];
        // foreach($items as $item) {
        //    $product_id = $item->product_id;
        //    $seller_id = get_post_field('post_author', $product_id);
        //    $seller_ids[] = $seller_id;
        // }
        // update_post_meta($order->id, '_buygo_seller_ids', array_unique($seller_ids));
    }

    /**
     * Filter query args to show only orders belonging to the seller.
     */
    public function filter_seller_orders($args) {
        $user_id = get_current_user_id();
        $role_manager = new RoleManager();

        if ($role_manager->is_seller($user_id)) {
            // Add Meta Query
            $meta_query = isset($args['meta_query']) ? $args['meta_query'] : [];
            $meta_query[] = [
                'key' => '_buygo_seller_ids',
                'value' => serialize((string)$user_id), // Partial match? Or JSON?
                'compare' => 'LIKE'
            ];
            $args['meta_query'] = $meta_query;
        }

        return $args;
    }

    /**
     * Restrict Media Library so Sellers strictly see their own uploads.
     */
    public function restrict_media_library_for_sellers($query) {
        if (!is_user_logged_in()) return;
        
        $user_id = get_current_user_id();
        $role_manager = new RoleManager();

        if ($role_manager->is_seller($user_id)) {
             // Only affect media/attachment queries in admin or ajax
             if ($query->get('post_type') == 'attachment') {
                 $query->set('author', $user_id);
             }
        }
    }
}
