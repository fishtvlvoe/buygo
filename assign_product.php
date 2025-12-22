<?php
/**
 * Assign Product Author Script
 * Usage: Access https://test.buygo.me/wp-content/plugins/buygo-role-permission/assign_product.php
 */
require_once dirname(dirname(dirname(__DIR__))) . '/wp-load.php';

if (!current_user_can('manage_options')) {
    die('Access Denied');
}

$product_id = 285;
$user_id = 11;

echo "<h1>Assigning Product {$product_id} to User {$user_id}</h1>";

// 1. Update wp_posts (Standard method)
// FluentCart usually creates a corresponding CPT in wp_posts for each product
$updated = wp_update_post([
    'ID' => $product_id,
    'post_author' => $user_id
]);

if ($updated && !is_wp_error($updated)) {
    echo "<p>✅ Successfully updated <b>wp_posts</b> via wp_update_post.</p>";
} else {
    echo "<p>⚠️ wp_update_post failed or ID not found in wp_posts. Trying direct query...</p>";
}

// 2. Direct Update (Force update if it's not a standard post type status or something)
global $wpdb;
$result = $wpdb->query($wpdb->prepare(
    "UPDATE {$wpdb->posts} SET post_author = %d WHERE ID = %d",
    $user_id,
    $product_id
));

if ($result !== false) {
    echo "<p>✅ Direct SQL Update executed on <b>wp_posts</b>. Rows affected: {$result}</p>";
} else {
    echo "<p>❌ Direct SQL Update failed.</p>";
}

// 3. Verify
$post = get_post($product_id);
if ($post) {
    echo "<h3>Verification:</h3>";
    echo "Product ID: " . $post->ID . "<br>";
    echo "Title: " . $post->post_title . "<br>";
    echo "Current Author ID: <b>" . $post->post_author . "</b> (Expected: {$user_id})";
} else {
    echo "<p>❌ verification failed: Product not found.</p>";
}
