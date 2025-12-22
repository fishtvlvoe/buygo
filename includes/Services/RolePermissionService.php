<?php

namespace BuyGo\Core\Services;

defined('ABSPATH') or die;

/**
 * Role Permission Service
 * 
 * 管理使用者角色與權限
 */
class RolePermissionService
{
    /**
     * 角色定義
     */
    private const ROLES = [
        'buyer' => [
            'name' => '買家',
            'capabilities' => [
                'browse_products',
                'plus_one_order',
                'view_own_orders',
            ],
        ],
        'seller' => [
            'name' => '賣家',
            'capabilities' => [
                'browse_products',
                'plus_one_order',
                'view_own_orders',
                'upload_products',
                'manage_own_products',
                'manage_own_orders',
            ],
        ],
        'helper' => [
            'name' => '小幫手',
            'capabilities' => [
                'browse_products',
                'plus_one_order',
                'view_own_orders',
                'update_order_status',
            ],
        ],
        'admin' => [
            'name' => '管理員',
            'capabilities' => [
                'browse_products',
                'plus_one_order',
                'view_own_orders',
                'upload_products',
                'manage_own_products',
                'manage_own_orders',
                'update_order_status',
                'manage_all_products',
                'manage_all_orders',
                'manage_users',
            ],
        ],
    ];

    /**
     * 操作對應的權限
     */
    private const ACTION_CAPABILITIES = [
        'browse_products' => 'browse_products',
        'plus_one_order' => 'plus_one_order',
        'view_own_orders' => 'view_own_orders',
        'upload_products' => 'upload_products',
        'manage_own_products' => 'manage_own_products',
        'manage_own_orders' => 'manage_own_orders',
        'update_order_status' => 'update_order_status',
        'manage_all_products' => 'manage_all_products',
        'manage_all_orders' => 'manage_all_orders',
        'manage_users' => 'manage_users',
    ];

    /**
     * 檢查使用者是否有權限執行操作
     *
     * @param int $userId 使用者 ID
     * @param string $action 操作名稱
     * @param array $context 額外的上下文（如資源擁有者 ID）
     * @return bool 是否有權限
     */
    public function canPerform(int $userId, string $action, array $context = []): bool
    {
        $role = $this->getUserRole($userId);
        $capabilities = $this->getRoleCapabilities($role);

        // 檢查基本權限
        $requiredCapability = self::ACTION_CAPABILITIES[$action] ?? $action;
        
        if (!in_array($requiredCapability, $capabilities, true)) {
            return false;
        }

        // 檢查資源所有權（針對 manage_own_* 權限）
        if (strpos($action, 'manage_own_') === 0) {
            $ownerId = $context['owner_id'] ?? null;
            if ($ownerId !== null && $ownerId !== $userId) {
                // 檢查是否有 manage_all_* 權限
                $allAction = str_replace('manage_own_', 'manage_all_', $action);
                if (!in_array($allAction, $capabilities, true)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * 取得使用者角色
     *
     * @param int $userId 使用者 ID
     * @return string 角色名稱
     */
    public function getUserRole(int $userId): string
    {
        $role = get_user_meta($userId, '_mygo_role', true);
        
        // 預設為買家
        if (empty($role) || !isset(self::ROLES[$role])) {
            return 'buyer';
        }

        return $role;
    }

    /**
     * 設定使用者角色
     *
     * @param int $userId 使用者 ID
     * @param string $role 角色名稱
     * @return bool 是否成功
     */
    public function setUserRole(int $userId, string $role): bool
    {
        if (!isset(self::ROLES[$role])) {
            return false;
        }

        update_user_meta($userId, '_mygo_role', $role);
        
        do_action('mygo/user/role_changed', $userId, $role);
        
        return true;
    }

    /**
     * 取得角色的權限列表
     *
     * @param string $role 角色名稱
     * @return array 權限列表
     */
    public function getRoleCapabilities(string $role): array
    {
        return self::ROLES[$role]['capabilities'] ?? [];
    }

    /**
     * 取得角色的中文名稱
     *
     * @param string $role 角色名稱
     * @return string 中文名稱
     */
    public function getRoleName(string $role): string
    {
        return self::ROLES[$role]['name'] ?? $role;
    }

    /**
     * 取得所有角色
     *
     * @return array 角色列表
     */
    public function getAllRoles(): array
    {
        return self::ROLES;
    }

    /**
     * 檢查使用者是否為賣家
     */
    public function isSeller(int $userId): bool
    {
        $role = $this->getUserRole($userId);
        return in_array($role, ['seller', 'admin'], true);
    }

    /**
     * 檢查使用者是否為管理員
     */
    public function isAdmin(int $userId): bool
    {
        return $this->getUserRole($userId) === 'admin';
    }

    /**
     * 檢查使用者是否可以管理商品
     */
    public function canManageProduct(int $userId, int $productId): bool
    {
        // 管理員可以管理所有商品
        if ($this->canPerform($userId, 'manage_all_products')) {
            return true;
        }

        // 賣家只能管理自己的商品
        if ($this->canPerform($userId, 'manage_own_products')) {
            $productOwnerId = $this->getProductOwner($productId);
            return $productOwnerId === $userId;
        }

        return false;
    }

    /**
     * 檢查使用者是否可以管理訂單
     */
    public function canManageOrder(int $userId, int $orderId): bool
    {
        // 管理員可以管理所有訂單
        if ($this->canPerform($userId, 'manage_all_orders')) {
            return true;
        }

        // 小幫手可以更新訂單狀態
        if ($this->canPerform($userId, 'update_order_status')) {
            return true;
        }

        // 賣家只能管理自己商品的訂單
        if ($this->canPerform($userId, 'manage_own_orders')) {
            $orderOwnerId = $this->getOrderProductOwner($orderId);
            return $orderOwnerId === $userId;
        }

        // 買家只能查看自己的訂單
        if ($this->canPerform($userId, 'view_own_orders')) {
            $orderCustomerId = $this->getOrderCustomer($orderId);
            return $orderCustomerId === $userId;
        }

        return false;
    }

    /**
     * 取得商品擁有者
     */
    private function getProductOwner(int $productId): ?int
    {
        $lineUid = get_post_meta($productId, '_mygo_line_user_id', true);
        if (empty($lineUid)) {
            return null;
        }

        $validator = new UserProfileValidator();
        return $validator->findUserByLineUid($lineUid);
    }

    /**
     * 取得訂單商品的擁有者
     */
    private function getOrderProductOwner(int $orderId): ?int
    {
        // 從訂單取得商品 ID，再取得商品擁有者
        $order = get_post($orderId);
        if (!$order) {
            return null;
        }

        // 這裡需要根據 FluentCart 的資料結構調整
        $productId = get_post_meta($orderId, '_product_id', true);
        if (!$productId) {
            return null;
        }

        return $this->getProductOwner($productId);
    }

    /**
     * 取得訂單客戶
     */
    private function getOrderCustomer(int $orderId): ?int
    {
        return (int) get_post_meta($orderId, '_customer_id', true) ?: null;
    }

    /**
     * 權限不足時的錯誤訊息
     */
    public function getPermissionDeniedMessage(string $action): string
    {
        $messages = [
            'upload_products' => '您沒有上傳商品的權限',
            'manage_own_products' => '您沒有管理此商品的權限',
            'manage_own_orders' => '您沒有管理此訂單的權限',
            'manage_all_products' => '您沒有管理所有商品的權限',
            'manage_all_orders' => '您沒有管理所有訂單的權限',
            'manage_users' => '您沒有管理使用者的權限',
            'update_order_status' => '您沒有更新訂單狀態的權限',
        ];

        return $messages[$action] ?? '權限不足';
    }
}
