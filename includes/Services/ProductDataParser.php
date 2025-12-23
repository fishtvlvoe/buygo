<?php

namespace BuyGo\Core\Services;

defined('ABSPATH') or die;

/**
 * Product Data Parser
 * 
 * 解析 LINE 訊息中的商品資料
 */
class ProductDataParser
{
    /**
     * 必要欄位
     */
    private const REQUIRED_FIELDS = ['name', 'price', 'quantity'];

    /**
     * 欄位對應的關鍵字
     */
    private const FIELD_PATTERNS = [
        'currency' => [
            'patterns' => ['/(日幣|美金|台幣|人民幣|港幣)[：:]/u'],
            'type' => 'currency',
        ],
        'price' => [
            'patterns' => ['/(?:日幣|美金|台幣|人民幣|港幣|價格|售價)[：:]\s*(\d+)/u', '/NT\$?\s*(\d+)/iu', '/(\d+)\s*元/u', '/原價[：:]\s*(\d+)/u'],
            'type' => 'int',
        ],
        'quantity' => [
            'patterns' => ['/數量[：:]\s*(\d+)/u', '/庫存[：:]\s*(\d+)/u', '/(\d+)\s*[個件組份]/u'],
            'type' => 'int',
        ],
        'arrival_date' => [
            'patterns' => ['/到貨[：:]\s*([\d\-\/]+)/u', '/到貨日期[：:]\s*([\d\-\/]+)/u'],
            'type' => 'date',
        ],
        'preorder_date' => [
            'patterns' => ['/預購[：:]\s*([\d\-\/]+)/u', '/預購日期[：:]\s*([\d\-\/]+)/u', '/預購截止[：:]\s*([\d\-\/]+)/u'],
            'type' => 'date',
        ],
        'type' => [
            'patterns' => ['/類型[：:]\s*(.+)/u', '/分類[：:]\s*(.+)/u', '/種類[：:]\s*(.+)/u'],
            'type' => 'string',
        ],
    ];

    /**
     * 解析商品資料
     *
     * @param string $message LINE 訊息內容
     * @param string|null $imageUrl 圖片 URL
     * @return array 解析結果
     */
    public function parse(string $message, ?string $imageUrl = null): array
    {
        $message = trim($message);
        $data = [
            'name' => null,
            'price' => null,
            'quantity' => null,
            'currency' => 'TWD', // 預設台幣
            'arrival_date' => null,
            'preorder_date' => null,
            'type' => null,
            'description' => null,
            'image_url' => $imageUrl,
        ];

        // 先從整個訊息中提取欄位（支援沒有換行的情況）
        foreach (self::FIELD_PATTERNS as $field => $config) {
            foreach ($config['patterns'] as $pattern) {
                if (preg_match($pattern, $message, $matches)) {
                    $value = trim($matches[1]);
                    $data[$field] = $this->castValue($value, $config['type']);
                    break;
                }
            }
        }

        // 提取商品名稱（第一個欄位關鍵字之前的文字）
        $namePattern = '/^(.+?)(?:價格|售價|數量|庫存|到貨|預購|類型|分類|NT\$)/u';
        if (preg_match($namePattern, $message, $matches)) {
            $name = trim($matches[1]);
            if (!empty($name)) {
                $data['name'] = $name;
            }
        }

        // 如果上面沒找到名稱，嘗試用換行方式解析
        if (empty($data['name'])) {
            $lines = preg_split('/[\r\n]+/', $message);
            if (!empty($lines[0])) {
                $firstLine = trim($lines[0]);
                if (!$this->containsFieldKeyword($firstLine)) {
                    $data['name'] = $firstLine;
                }
            }
        }

        // 提取描述（移除已識別的欄位後剩餘的文字）
        $descriptionText = $message;
        // 移除名稱
        if (!empty($data['name'])) {
            $descriptionText = str_replace($data['name'], '', $descriptionText);
        }
        // 移除已識別的欄位
        $removePatterns = [
            '/價格[：:]\s*\d+/u',
            '/售價[：:]\s*\d+/u',
            '/數量[：:]\s*\d+/u',
            '/庫存[：:]\s*\d+/u',
            '/到貨[：:]\s*[\d\-\/]+/u',
            '/預購[：:]\s*[\d\-\/]+/u',
            '/類型[：:]\s*.+/u',
        ];
        foreach ($removePatterns as $pattern) {
            $descriptionText = preg_replace($pattern, '', $descriptionText);
        }
        $descriptionText = trim($descriptionText);
        if (!empty($descriptionText) && $descriptionText !== $data['name']) {
            $data['description'] = $descriptionText;
        }

        return $data;
    }

    /**
     * 驗證商品資料
     *
     * @param array $data 商品資料
     * @return array ['valid' => bool, 'missing' => array]
     */
    public function validate(array $data): array
    {
        $missing = [];

        foreach (self::REQUIRED_FIELDS as $field) {
            if (empty($data[$field])) {
                $missing[] = $field;
            }
        }

        // 價格必須大於 0
        if (isset($data['price']) && $data['price'] <= 0) {
            $missing[] = 'price';
        }

        // 數量必須大於 0
        if (isset($data['quantity']) && $data['quantity'] <= 0) {
            $missing[] = 'quantity';
        }

        return [
            'valid' => empty($missing),
            'missing' => array_unique($missing),
        ];
    }

    /**
     * 取得缺失欄位的中文名稱
     *
     * @param array $missing 缺失欄位
     * @return array 中文名稱
     */
    public function getMissingFieldNames(array $missing): array
    {
        $names = [
            'name' => '商品名稱',
            'price' => '價格',
            'quantity' => '數量',
            'arrival_date' => '到貨時間',
            'preorder_date' => '預購時間',
            'type' => '類型',
        ];

        return array_map(fn($field) => $names[$field] ?? $field, $missing);
    }

    /**
     * 檢查是否包含欄位關鍵字
     */
    private function containsFieldKeyword(string $line): bool
    {
        $keywords = ['價格', '售價', '數量', '庫存', '到貨', '預購', '類型', '分類', 'NT$'];
        foreach ($keywords as $keyword) {
            if (mb_strpos($line, $keyword) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * 轉換值的類型
     */
    private function castValue(string $value, string $type): mixed
    {
        return match ($type) {
            'int' => (int) preg_replace('/[^\d]/', '', $value),
            'date' => $this->parseDate($value),
            'currency' => $this->parseCurrency($value),
            default => $value,
        };
    }

    /**
     * 解析幣別
     */
    private function parseCurrency(string $value): string
    {
        // 移除前後空白和符號
        $value = trim($value, " \t\n\r\0\x0B/:：");
        
        // 中文轉標準代碼
        $currencyMap = [
            '日幣' => 'JPY',
            '美金' => 'USD',
            '台幣' => 'TWD',
            '人民幣' => 'CNY',
            '港幣' => 'HKD',
        ];
        
        return $currencyMap[$value] ?? 'TWD'; // 預設台幣
    }

    /**
     * 解析日期
     */
    private function parseDate(string $value): ?string
    {
        // 支援格式：2025-01-15, 2025/01/15, 01-15, 01/15
        $value = str_replace('/', '-', $value);
        
        // 如果只有月日，補上年份
        if (preg_match('/^(\d{1,2})-(\d{1,2})$/', $value, $matches)) {
            $year = date('Y');
            $month = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $day = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
            return "{$year}-{$month}-{$day}";
        }

        // 完整日期
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value, $matches)) {
            $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
            $day = str_pad($matches[3], 2, '0', STR_PAD_LEFT);
            return "{$matches[1]}-{$month}-{$day}";
        }

        return null;
    }
}
