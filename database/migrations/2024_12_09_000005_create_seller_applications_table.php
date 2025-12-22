<?php

class CreateSellerApplicationsTable {
    public function up() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'buygo_seller_applications';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            real_name varchar(100) NOT NULL,
            phone varchar(50) NOT NULL,
            line_id varchar(100) NOT NULL,
            status varchar(20) DEFAULT 'pending',
            review_note text,
            submitted_at datetime DEFAULT CURRENT_TIMESTAMP,
            reviewed_at datetime,
            reviewer_id bigint(20) UNSIGNED,
            reason text,
            product_types text,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY status (status)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }
}
