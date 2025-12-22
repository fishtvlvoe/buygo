<?php

namespace BuyGo\Core\Contracts;

defined('ABSPATH') or die;

/**
 * Image Processor Interface
 * 
 * 處理商品圖片的上傳、壓縮與調整尺寸
 */
interface ImageProcessorInterface
{
    /**
     * 處理圖片（下載、調整尺寸、壓縮）
     *
     * @param string $imageUrl 圖片 URL
     * @return array ['success' => bool, 'attachment_id' => int, 'url' => string, 'error' => string]
     */
    public function processImage(string $imageUrl): array;

    /**
     * 調整圖片尺寸
     *
     * @param string $filePath 檔案路徑
     * @param int $width 目標寬度
     * @param int $height 目標高度
     * @return string 處理後的檔案路徑
     */
    public function resizeImage(string $filePath, int $width, int $height): string;

    /**
     * 壓縮圖片
     *
     * @param string $filePath 檔案路徑
     * @param int $maxSize 最大檔案大小（bytes）
     * @return string 處理後的檔案路徑
     */
    public function compressImage(string $filePath, int $maxSize): string;

    /**
     * 上傳至 WordPress Media Library
     *
     * @param string $filePath 檔案路徑
     * @param string $filename 檔案名稱
     * @return int Attachment ID
     */
    public function uploadToMediaLibrary(string $filePath, string $filename): int;

    /**
     * 驗證圖片格式
     *
     * @param string $filePath 檔案路徑
     * @return bool 是否為支援的格式
     */
    public function validateFormat(string $filePath): bool;
}
