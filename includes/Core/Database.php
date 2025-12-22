<?php

namespace BuyGo\Core\Core;

defined('ABSPATH') or die;

/**
 * Database Helper
 * 
 * 提供資料庫操作的輔助方法
 */
class Database
{
    /**
     * 取得訂單狀態記錄表名
     */
    public static function getOrderStatusLogTable(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'mygo_order_status_log';
    }

    /**
     * 取得 +1 訂單表名
     */
    public static function getPlusOneOrdersTable(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'mygo_plus_one_orders';
    }

    /**
     * 記錄訂單狀態變更
     */
    public static function logOrderStatusChange(
        int $orderId,
        string $statusType,
        bool $oldValue,
        bool $newValue,
        int $changedBy
    ): bool {
        global $wpdb;

        return (bool) $wpdb->insert(
            self::getOrderStatusLogTable(),
            [
                'order_id' => $orderId,
                'status_type' => $statusType,
                'old_value' => $oldValue ? 1 : 0,
                'new_value' => $newValue ? 1 : 0,
                'changed_by' => $changedBy,
                'changed_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%d', '%d', '%d', '%s']
        );
    }

    /**
     * 取得或建立 +1 訂單記錄
     */
    public static function getOrCreatePlusOneOrder(
        int $userId,
        int $productId,
        int $feedId
    ): ?object {
        global $wpdb;
        $table = self::getPlusOneOrdersTable();

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d AND product_id = %d AND feed_id = %d",
            $userId,
            $productId,
            $feedId
        ));

        if ($existing) {
            return $existing;
        }

        $wpdb->insert(
            $table,
            [
                'user_id' => $userId,
                'product_id' => $productId,
                'feed_id' => $feedId,
                'quantity' => 0,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['%d', '%d', '%d', '%d', '%s', '%s']
        );

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $wpdb->insert_id
        ));
    }

    /**
     * 累加 +1 訂單數量
     */
    public static function incrementPlusOneQuantity(
        int $userId,
        int $productId,
        int $feedId,
        int $quantity,
        ?string $variant = null
    ): bool {
        global $wpdb;
        $table = self::getPlusOneOrdersTable();

        $record = self::getOrCreatePlusOneOrder($userId, $productId, $feedId);
        if (!$record) {
            return false;
        }

        $updateData = [
            'quantity' => $record->quantity + $quantity,
            'updated_at' => current_time('mysql'),
        ];
        $updateFormat = ['%d', '%s'];

        if ($variant !== null) {
            $updateData['variant'] = $variant;
            $updateFormat[] = '%s';
        }

        return (bool) $wpdb->update(
            $table,
            $updateData,
            ['id' => $record->id],
            $updateFormat,
            ['%d']
        );
    }

    /**
     * 取得使用者在特定貼文的訂單
     */
    public static function getUserFeedOrder(int $userId, int $feedId): ?object
    {
        global $wpdb;
        $table = self::getPlusOneOrdersTable();

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d AND feed_id = %d",
            $userId,
            $feedId
        ));
    }

    /**
     * 取得貼文的所有訂單
     */
    public static function getFeedOrders(int $feedId): array
    {
        global $wpdb;
        $table = self::getPlusOneOrdersTable();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE feed_id = %d ORDER BY created_at DESC",
            $feedId
        ));
    }

    /**
     * 更新 FluentCart 訂單 ID
     */
    public static function updateFluentCartOrderId(int $plusOneOrderId, int $fluentCartOrderId): bool
    {
        global $wpdb;
        $table = self::getPlusOneOrdersTable();

        return (bool) $wpdb->update(
            $table,
            ['fluentcart_order_id' => $fluentCartOrderId],
            ['id' => $plusOneOrderId],
            ['%d'],
            ['%d']
        );
    }
}
