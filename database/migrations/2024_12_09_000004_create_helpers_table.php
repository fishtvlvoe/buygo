<?php

class CreateHelpersTable {
    public function up() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'buygo_helpers';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            seller_id bigint(20) UNSIGNED NOT NULL,
            helper_id bigint(20) UNSIGNED NOT NULL,
            permissions longtext,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            assigned_at datetime DEFAULT CURRENT_TIMESTAMP,
            assigned_by bigint(20) UNSIGNED,
            can_view_orders tinyint(1) DEFAULT 0,
            can_update_orders tinyint(1) DEFAULT 0,
            can_manage_products tinyint(1) DEFAULT 0,
            can_reply_customers tinyint(1) DEFAULT 0,
            PRIMARY KEY  (id),
            KEY seller_id (seller_id),
            KEY helper_id (helper_id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }
}
