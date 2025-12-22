<?php

namespace BuyGo\Core\Core;

defined('ABSPATH') or die;

class Activator
{
    public static function activate(): void
    {
        self::createTables();
        self::createRoles();
        
        update_option('mygo_plugin_version', MYGO_PLUGIN_VERSION);
        flush_rewrite_rules();
    }

    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }

    private static function createTables(): void
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = [];

        // 訂單狀態歷史記錄
        $table_name = $wpdb->prefix . 'mygo_order_status_log';
        $sql[] = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_id BIGINT UNSIGNED NOT NULL,
            status_type VARCHAR(20) NOT NULL,
            old_value TINYINT(1) DEFAULT 0,
            new_value TINYINT(1) DEFAULT 1,
            changed_by BIGINT UNSIGNED NOT NULL,
            changed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_order_id (order_id),
            INDEX idx_changed_at (changed_at)
        ) {$charset_collate};";

        // +1 訂單累加記錄
        $table_name = $wpdb->prefix . 'mygo_plus_one_orders';
        $sql[] = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            feed_id BIGINT UNSIGNED NOT NULL,
            fluentcart_order_id BIGINT UNSIGNED,
            quantity INT UNSIGNED DEFAULT 1,
            variant VARCHAR(100),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY idx_user_product_feed (user_id, product_id, feed_id),
            INDEX idx_feed_id (feed_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        foreach ($sql as $query) {
            dbDelta($query);
        }
    }

    private static function createRoles(): void
    {
        // 角色將在 FluentCommunity 中建立
        // 這裡只記錄角色定義供後續使用
        update_option('mygo_roles', [
            'buyer' => [
                'name' => '買家',
                'capabilities' => ['browse_products', 'plus_one_order', 'view_own_orders']
            ],
            'seller' => [
                'name' => '賣家',
                'capabilities' => ['upload_products', 'manage_own_products', 'manage_own_orders']
            ],
            'helper' => [
                'name' => '小幫手',
                'capabilities' => ['update_order_status']
            ],
            'admin' => [
                'name' => '管理員',
                'capabilities' => ['manage_all_products', 'manage_all_orders', 'manage_users']
            ]
        ]);
    }
}
