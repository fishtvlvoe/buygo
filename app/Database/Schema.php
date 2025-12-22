<?php

namespace BuyGo\Core\Database;

class Schema {

    public static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $tables = [
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}buygo_seller_applications (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                real_name varchar(100) NOT NULL,
                phone varchar(50) NOT NULL,
                line_id varchar(100) NOT NULL,
                status varchar(20) DEFAULT 'pending',
                review_note text,
                submitted_at datetime DEFAULT CURRENT_TIMESTAMP,
                reviewer_id bigint(20),
                reviewed_at datetime,
                PRIMARY KEY  (id),
                KEY user_id (user_id),
                KEY status (status)
            ) $charset_collate;",

            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}buygo_helpers (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                seller_id bigint(20) NOT NULL,
                helper_id bigint(20) NOT NULL,
                permissions longtext,
                status varchar(20) DEFAULT 'active',
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY seller_id (seller_id),
                KEY helper_id (helper_id)
            ) $charset_collate;",

            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}buygo_line_bindings (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                line_uid varchar(100) NOT NULL,
                binding_code varchar(20),
                binding_code_expires_at datetime,
                status varchar(20) DEFAULT 'unbound',
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY user_id (user_id),
                KEY line_uid (line_uid),
                KEY binding_code (binding_code)
            ) $charset_collate;"
        ];

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        foreach ($tables as $sql) {
            dbDelta($sql);
        }
    }
}
