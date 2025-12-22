<?php

namespace BuyGo\Core\Services;

defined('ABSPATH') or die;

/**
 * Order Command Parser
 * 
 * 解析 +1 下單指令
 * 支援格式：+1, +2, +3, +2 紅色, +1紅色
 */
class OrderCommandParser
{
    /**
     * 解析下單指令
     *
     * @param string $message 留言內容
     * @return array|null 解析結果 ['quantity' => int, 'variant' => string|null] 或 null
     */
    public function parse(string $message): ?array
    {
        $message = trim($message);
        
        if (empty($message)) {
            return null;
        }

        // 模式 1: +N 規格（有空格）
        // 例如: +2 紅色, +1 大號
        if (preg_match('/^\+(\d+)\s+(.+)$/u', $message, $matches)) {
            $quantity = (int) $matches[1];
            $variant = trim($matches[2]);
            
            if ($quantity > 0 && !empty($variant)) {
                return [
                    'quantity' => $quantity,
                    'variant' => $variant,
                ];
            }
        }

        // 模式 2: +N規格（無空格）
        // 例如: +2紅色, +1大號
        if (preg_match('/^\+(\d+)([^\d\s].*)$/u', $message, $matches)) {
            $quantity = (int) $matches[1];
            $variant = trim($matches[2]);
            
            if ($quantity > 0 && !empty($variant)) {
                return [
                    'quantity' => $quantity,
                    'variant' => $variant,
                ];
            }
        }

        // 模式 3: 純 +N
        // 例如: +1, +2, +10
        if (preg_match('/^\+(\d+)$/', $message, $matches)) {
            $quantity = (int) $matches[1];
            
            if ($quantity > 0) {
                return [
                    'quantity' => $quantity,
                    'variant' => null,
                ];
            }
        }

        // 模式 4: 訊息中包含 +N（不在開頭）
        // 例如: 我要+1, 下單+2
        if (preg_match('/\+(\d+)(?:\s+(.+))?$/u', $message, $matches)) {
            $quantity = (int) $matches[1];
            $variant = isset($matches[2]) ? trim($matches[2]) : null;
            
            if ($quantity > 0) {
                return [
                    'quantity' => $quantity,
                    'variant' => $variant ?: null,
                ];
            }
        }

        return null;
    }

    /**
     * 檢查訊息是否包含下單指令
     *
     * @param string $message 留言內容
     * @return bool
     */
    public function isOrderCommand(string $message): bool
    {
        return $this->parse($message) !== null;
    }

    /**
     * 從訊息中提取數量
     *
     * @param string $message 留言內容
     * @return int 數量，無效則回傳 0
     */
    public function extractQuantity(string $message): int
    {
        $result = $this->parse($message);
        return $result['quantity'] ?? 0;
    }

    /**
     * 從訊息中提取規格
     *
     * @param string $message 留言內容
     * @return string|null 規格名稱
     */
    public function extractVariant(string $message): ?string
    {
        $result = $this->parse($message);
        return $result['variant'] ?? null;
    }

    /**
     * 驗證數量是否在有效範圍內
     *
     * @param int $quantity 數量
     * @param int $maxQuantity 最大數量（庫存）
     * @return array ['valid' => bool, 'message' => string|null, 'available' => int]
     */
    public function validateQuantity(int $quantity, int $maxQuantity): array
    {
        if ($quantity <= 0) {
            return [
                'valid' => false,
                'message' => '數量必須大於 0',
                'available' => $maxQuantity,
            ];
        }

        if ($maxQuantity <= 0) {
            return [
                'valid' => false,
                'message' => '商品已售完',
                'available' => 0,
            ];
        }

        if ($quantity > $maxQuantity) {
            return [
                'valid' => false,
                'message' => "庫存不足，目前可購買數量為 {$maxQuantity} 個",
                'available' => $maxQuantity,
            ];
        }

        return [
            'valid' => true,
            'message' => null,
            'available' => $maxQuantity,
        ];
    }

    /**
     * 驗證規格是否存在
     *
     * @param string|null $variant 規格名稱
     * @param array $availableVariants 可用規格列表
     * @return array ['valid' => bool, 'message' => string|null]
     */
    public function validateVariant(?string $variant, array $availableVariants): array
    {
        // 如果沒有指定規格且商品有規格選項，需要選擇
        if ($variant === null && !empty($availableVariants)) {
            return [
                'valid' => false,
                'message' => '請選擇規格：' . implode('、', $availableVariants),
                'needs_selection' => true,
            ];
        }

        // 如果指定了規格但不在可用列表中
        if ($variant !== null && !empty($availableVariants)) {
            if (!in_array($variant, $availableVariants, true)) {
                return [
                    'valid' => false,
                    'message' => '找不到指定規格，可選規格：' . implode('、', $availableVariants),
                    'needs_selection' => false,
                ];
            }
        }

        return [
            'valid' => true,
            'message' => null,
            'needs_selection' => false,
        ];
    }
}
