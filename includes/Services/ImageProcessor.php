<?php

namespace BuyGo\Core\Services;

defined('ABSPATH') or die;

use BuyGo\Core\Contracts\ImageProcessorInterface;

/**
 * Image Processor
 * 
 * 處理商品圖片的下載、調整尺寸、壓縮與上傳
 */
class ImageProcessor implements ImageProcessorInterface
{
    /**
     * 目標尺寸
     */
    private const TARGET_WIDTH = 300;
    private const TARGET_HEIGHT = 300;

    /**
     * 最大檔案大小（1MB）
     */
    private const MAX_FILE_SIZE = 1048576;

    /**
     * 支援的圖片格式
     */
    private const SUPPORTED_FORMATS = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    /**
     * 處理圖片
     * 
     * @param string $imageSource 可以是 URL 或本地檔案路徑
     */
    public function processImage(string $imageSource): array
    {
        $logFile = WP_CONTENT_DIR . '/mygo-webhook.log';
        
        try {
            file_put_contents($logFile, "ImageProcessor - START\n", FILE_APPEND);
            file_put_contents($logFile, "ImageProcessor - imageSource: {$imageSource}\n", FILE_APPEND);
            
            // 判斷是本地檔案還是 URL
            if (file_exists($imageSource)) {
                // 本地檔案，直接使用
                $tempFile = $imageSource;
                file_put_contents($logFile, "ImageProcessor - using local file\n", FILE_APPEND);
            } else {
                // URL，需要下載
                file_put_contents($logFile, "ImageProcessor - downloading from URL\n", FILE_APPEND);
                $tempFile = $this->downloadImage($imageSource);
                if (!$tempFile) {
                    file_put_contents($logFile, "ImageProcessor - ERROR: download failed\n", FILE_APPEND);
                    return [
                        'success' => false,
                        'error' => '無法下載圖片',
                    ];
                }
            }

            // 驗證格式
            file_put_contents($logFile, "ImageProcessor - validating format\n", FILE_APPEND);
            if (!$this->validateFormat($tempFile)) {
                file_put_contents($logFile, "ImageProcessor - ERROR: invalid format\n", FILE_APPEND);
                @unlink($tempFile);
                return [
                    'success' => false,
                    'error' => '不支援的圖片格式，請使用 JPEG、PNG、GIF 或 WebP',
                ];
            }

            // 調整尺寸
            file_put_contents($logFile, "ImageProcessor - resizing image\n", FILE_APPEND);
            $resizedFile = $this->resizeImage($tempFile, self::TARGET_WIDTH, self::TARGET_HEIGHT);
            file_put_contents($logFile, "ImageProcessor - resized to: {$resizedFile}\n", FILE_APPEND);

            // 壓縮
            file_put_contents($logFile, "ImageProcessor - compressing image\n", FILE_APPEND);
            $compressedFile = $this->compressImage($resizedFile, self::MAX_FILE_SIZE);
            file_put_contents($logFile, "ImageProcessor - compressed to: {$compressedFile}\n", FILE_APPEND);

            // 上傳至 Media Library
            $filename = 'mygo-product-' . time() . '-' . wp_generate_password(6, false);
            file_put_contents($logFile, "ImageProcessor - uploading to media library: {$filename}\n", FILE_APPEND);
            $attachmentId = $this->uploadToMediaLibrary($compressedFile, $filename);
            file_put_contents($logFile, "ImageProcessor - attachment_id: {$attachmentId}\n", FILE_APPEND);

            // 清理暫存檔
            if ($tempFile !== $compressedFile) {
                @unlink($tempFile);
            }
            if ($resizedFile !== $compressedFile) {
                @unlink($resizedFile);
            }
            @unlink($compressedFile);

            if (!$attachmentId) {
                file_put_contents($logFile, "ImageProcessor - ERROR: upload failed\n", FILE_APPEND);
                return [
                    'success' => false,
                    'error' => '上傳圖片失敗',
                ];
            }

            $url = wp_get_attachment_url($attachmentId);
            file_put_contents($logFile, "ImageProcessor - SUCCESS: {$url}\n", FILE_APPEND);

            return [
                'success' => true,
                'attachment_id' => $attachmentId,
                'url' => $url,
            ];
            
        } catch (\Exception $e) {
            file_put_contents($logFile, "ImageProcessor - EXCEPTION: " . $e->getMessage() . "\n", FILE_APPEND);
            file_put_contents($logFile, "ImageProcessor - TRACE: " . $e->getTraceAsString() . "\n", FILE_APPEND);
            return [
                'success' => false,
                'error' => '圖片處理時發生錯誤：' . $e->getMessage(),
            ];
        }
    }

    /**
     * 下載圖片
     */
    private function downloadImage(string $imageUrl): ?string
    {
        $response = wp_remote_get($imageUrl, [
            'timeout' => 30,
            'sslverify' => false,
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            return null;
        }

        $tempFile = wp_tempnam('mygo_img_');
        if (!$tempFile) {
            return null;
        }

        file_put_contents($tempFile, $body);
        return $tempFile;
    }

    /**
     * 驗證圖片格式
     */
    public function validateFormat(string $filePath): bool
    {
        if (!file_exists($filePath)) {
            return false;
        }

        $mimeType = mime_content_type($filePath);
        return in_array($mimeType, self::SUPPORTED_FORMATS, true);
    }

    /**
     * 調整圖片尺寸
     */
    public function resizeImage(string $filePath, int $width, int $height): string
    {
        $logFile = WP_CONTENT_DIR . '/mygo-webhook.log';
        
        try {
            file_put_contents($logFile, "resizeImage - START: {$filePath}\n", FILE_APPEND);
            
            // 載入必要的 WordPress 函數
            if (!function_exists('wp_get_image_editor')) {
                require_once ABSPATH . 'wp-admin/includes/image.php';
            }
            
            $editor = wp_get_image_editor($filePath);
            file_put_contents($logFile, "resizeImage - editor loaded\n", FILE_APPEND);
            
            if (is_wp_error($editor)) {
                file_put_contents($logFile, "resizeImage - ERROR: " . $editor->get_error_message() . "\n", FILE_APPEND);
                return $filePath;
            }

            $size = $editor->get_size();
            file_put_contents($logFile, "resizeImage - original size: {$size['width']}x{$size['height']}\n", FILE_APPEND);
            
            // 如果圖片已經小於目標尺寸，不需要調整
            if ($size['width'] <= $width && $size['height'] <= $height) {
                file_put_contents($logFile, "resizeImage - no resize needed\n", FILE_APPEND);
                return $filePath;
            }

            // 裁切為正方形（從中心）
            $minDimension = min($size['width'], $size['height']);
            $cropX = ($size['width'] - $minDimension) / 2;
            $cropY = ($size['height'] - $minDimension) / 2;

            file_put_contents($logFile, "resizeImage - cropping: {$cropX},{$cropY} {$minDimension}x{$minDimension}\n", FILE_APPEND);
            $editor->crop($cropX, $cropY, $minDimension, $minDimension, $width, $height);

            $uploadDir = wp_upload_dir();
            $newFile = $uploadDir['path'] . '/mygo_resized_' . uniqid() . '.jpg';
            file_put_contents($logFile, "resizeImage - saving to: {$newFile}\n", FILE_APPEND);
            
            $saved = $editor->save($newFile);

            if (is_wp_error($saved)) {
                file_put_contents($logFile, "resizeImage - save ERROR: " . $saved->get_error_message() . "\n", FILE_APPEND);
                return $filePath;
            }

            file_put_contents($logFile, "resizeImage - SUCCESS: {$saved['path']}\n", FILE_APPEND);
            return $saved['path'];
            
        } catch (\Exception $e) {
            file_put_contents($logFile, "resizeImage - EXCEPTION: " . $e->getMessage() . "\n", FILE_APPEND);
            return $filePath;
        }
    }

    /**
     * 壓縮圖片
     */
    public function compressImage(string $filePath, int $maxSize): string
    {
        $currentSize = filesize($filePath);
        
        if ($currentSize <= $maxSize) {
            return $filePath;
        }

        $editor = wp_get_image_editor($filePath);
        
        if (is_wp_error($editor)) {
            return $filePath;
        }

        // 逐步降低品質直到檔案大小符合要求
        $quality = 90;
        $newFile = $filePath;

        while ($currentSize > $maxSize && $quality >= 30) {
            $editor->set_quality($quality);
            
            $tempFile = wp_tempnam('mygo_compressed_');
            $saved = $editor->save($tempFile);

            if (!is_wp_error($saved)) {
                if ($newFile !== $filePath) {
                    @unlink($newFile);
                }
                $newFile = $saved['path'];
                $currentSize = filesize($newFile);
            }

            $quality -= 10;
        }

        return $newFile;
    }

    /**
     * 上傳至 WordPress Media Library
     */
    public function uploadToMediaLibrary(string $filePath, string $filename): int
    {
        if (!file_exists($filePath)) {
            return 0;
        }

        $mimeType = mime_content_type($filePath);
        $extension = $this->getExtensionFromMime($mimeType);
        $fullFilename = $filename . '.' . $extension;

        $uploadDir = wp_upload_dir();
        $targetPath = $uploadDir['path'] . '/' . $fullFilename;

        // 複製檔案到上傳目錄
        if (!copy($filePath, $targetPath)) {
            return 0;
        }

        $attachment = [
            'post_mime_type' => $mimeType,
            'post_title' => sanitize_file_name($filename),
            'post_content' => '',
            'post_status' => 'inherit',
        ];

        $attachmentId = wp_insert_attachment($attachment, $targetPath);

        if (!$attachmentId) {
            @unlink($targetPath);
            return 0;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attachmentData = wp_generate_attachment_metadata($attachmentId, $targetPath);
        wp_update_attachment_metadata($attachmentId, $attachmentData);

        return $attachmentId;
    }

    /**
     * 從 MIME 類型取得副檔名
     */
    private function getExtensionFromMime(string $mimeType): string
    {
        return match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }

    /**
     * 取得圖片資訊
     */
    public function getImageInfo(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return [];
        }

        $size = getimagesize($filePath);
        
        return [
            'width' => $size[0] ?? 0,
            'height' => $size[1] ?? 0,
            'mime' => $size['mime'] ?? '',
            'file_size' => filesize($filePath),
        ];
    }
}
