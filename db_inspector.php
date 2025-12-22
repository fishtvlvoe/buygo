<?php
/**
 * DB Inspector v2: Deep Dive into Order Relationships
 * Usage: Access https://test.buygo.me/wp-content/plugins/buygo-role-permission/db_inspector.php
 */
require_once dirname(dirname(dirname(__DIR__))) . '/wp-load.php';

if (!current_user_can('manage_options')) {
    die('Access Denied');
}

global $wpdb;
echo "<h1>Database Inspector v2</h1>";

// 1. Get Latest Order from wp_fct_orders
$order_table = $wpdb->prefix . 'fct_orders';
$latest_order = $wpdb->get_row("SELECT * FROM {$order_table} ORDER BY id DESC LIMIT 1");

if (!$latest_order) {
    die("No orders found in {$order_table}.");
}

echo "<h2>1. Latest Order (ID: {$latest_order->id})</h2>";
echo "<pre style='background:#f0f0f0; padding:10px;'>" . print_r($latest_order, true) . "</pre>";


// 2. Get Order Items from wp_fct_order_items
$item_table = $wpdb->prefix . 'fct_order_items';
$items = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$item_table} WHERE order_id = %d", $latest_order->id));

echo "<h2>2. Order Items</h2>";
if ($items) {
    echo "Found " . count($items) . " items.<br>";
    foreach ($items as $item) {
        echo "<hr>";
        echo "<b>Item ID:</b> {$item->id} | <b>Product ID:</b> {$item->product_id}<br>";
        echo "<pre style='background:#e0e0e0; padding:5px;'>" . print_r($item, true) . "</pre>";
        
        // 3. Inspect the Product in wp_posts
        $product_id = $item->product_id;
        $post = get_post($product_id);
        
        echo "<h3>-> Associated Product (ID: {$product_id})</h3>";
        if ($post) {
            echo "Title: " . $post->post_title . "<br>";
            echo "Post Type: <b>" . $post->post_type . "</b><br>";
             // Highlight Author
            $color = ($post->post_author == 11) ? 'green' : 'red';
            echo "Author ID: <b style='color:{$color}'>" . $post->post_author . "</b> (Current User ID is " . get_current_user_id() . ")<br>";
        } else {
             echo "❌ Product NOT found in wp_posts!<br>";
        }
    }
} else {
    echo "❌ No items found for this order in {$item_table}.";
}

// 3. Simulate OrderController Logic
echo "<h2>3. API Simulation (OrderController Logic)</h2>";
$user_id = get_current_user_id();
echo "Current User ID: {$user_id}<br>";

$table_orders = $wpdb->prefix . 'fct_orders';
$table_items = $wpdb->prefix . 'fct_order_items';
$table_posts = $wpdb->posts;

$sql = "
    SELECT DISTINCT o.* 
    FROM {$table_orders} o
    INNER JOIN {$table_items} oi ON o.id = oi.order_id
    INNER JOIN {$table_posts} p ON oi.post_id = p.ID
    WHERE p.post_author = %d
    ORDER BY o.id DESC LIMIT 50
";

$prepared_sql = $wpdb->prepare($sql, $user_id);
echo "SQL Query: <pre style='background:#ddd; padding:5px;'>{$prepared_sql}</pre>";

$results = $wpdb->get_results($prepared_sql);

if ($results) {
    echo "✅ API Simulation Found " . count($results) . " orders.<br>";
    echo "<pre style='background:#e0ffe0; padding:10px;'>" . print_r($results, true) . "</pre>";
} else {
    echo "❌ API Simulation Found NO orders. check criteria.<br>";
    // Debug: Check if any items exist for this author at all
    $check_author = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table_posts} WHERE post_author = %d AND post_type='fluent-products'", $user_id));
    echo "Debug: User {$user_id} owns {$check_author} fluent-products.<br>";
}

