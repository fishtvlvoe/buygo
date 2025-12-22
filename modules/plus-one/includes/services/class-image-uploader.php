<?php
/**
 * 圖片上傳器類別
 *
 * @package BuyGo_LINE_FluentCart
 */

// 防止直接存取
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BuyGo_Plus_One_Image_Uploader
 */
class BuyGo_Plus_One_Image_Uploader {

	/**
	 * LINE Channel Access Token
	 *
	 * @var string
	 */
	private $channel_access_token;

	/**
	 * Logger
	 *
	 * @var BuyGo_Plus_One_Logger
	 */
	private $logger;

	/**
	 * 建構函數
	 *
	 * @param string $channel_access_token Channel Access Token
	 */
	public function __construct( $channel_access_token ) {
		$this->channel_access_token = $channel_access_token;
		$this->logger               = BuyGo_Plus_One_Logger::get_instance();
	}

	/**
	 * 下載並上傳圖片
	 *
	 * @param string $message_id LINE 訊息 ID
	 * @param int    $user_id WordPress 使用者 ID
	 * @return int|WP_Error 附件 ID 或錯誤
	 */
	public function download_and_upload( $message_id, $user_id ) {
		// 驗證 user_id 是否有效
		if ( $user_id <= 0 ) {
			$this->logger->error(
				'Invalid user_id provided to download_and_upload',
				array(
					'message_id' => $message_id,
					'user_id'    => $user_id,
				)
			);
		}

		$this->logger->info( 'Starting image download', array(
			'message_id' => $message_id,
			'user_id'    => $user_id,
		) );

		// 1. 從 LINE 下載圖片
		$image_data = $this->download_from_line( $message_id );

		if ( is_wp_error( $image_data ) ) {
			return $image_data;
		}

		// 2. 上傳到 WordPress
		$attachment_id = $this->upload_to_wordpress( $image_data, $user_id );

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		// 3. 暫存圖片 ID
		$this->store_temp_image( $user_id, $attachment_id );

		$this->logger->info( 'Image uploaded successfully', array(
			'attachment_id' => $attachment_id,
		) );

		return $attachment_id;
	}

	/**
	 * 從 LINE 下載圖片
	 *
	 * @param string $message_id LINE 訊息 ID
	 * @return string|WP_Error 圖片資料或錯誤
	 */
	private function download_from_line( $message_id ) {
		if ( empty( $this->channel_access_token ) ) {
			return new WP_Error( 'no_token', 'Channel Access Token 未設定' );
		}

		$url = "https://api-data.line.me/v2/bot/message/{$message_id}/content";

		$this->logger->debug( 'Downloading from LINE', array( 'url' => $url ) );

		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->channel_access_token,
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->logger->error( 'Failed to download from LINE', array(
				'error' => $response->get_error_message(),
			) );
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( $status_code !== 200 ) {
			$this->logger->error( 'LINE API error', array(
				'status_code' => $status_code,
				'response'    => wp_remote_retrieve_body( $response ),
			) );
			return new WP_Error( 'line_api_error', 'LINE API 錯誤：' . $status_code );
		}

		$image_data = wp_remote_retrieve_body( $response );

		if ( empty( $image_data ) ) {
			return new WP_Error( 'empty_image', '圖片資料為空' );
		}

		$this->logger->debug( 'Image downloaded', array(
			'size' => strlen( $image_data ),
		) );

		return $image_data;
	}

	/**
	 * 上傳到 WordPress
	 *
	 * @param string $image_data 圖片資料
	 * @param int    $user_id WordPress 使用者 ID
	 * @return int|WP_Error 附件 ID 或錯誤
	 */
	private function upload_to_wordpress( $image_data, $user_id ) {
		// 取得上傳目錄
		$upload_dir = wp_upload_dir();

		if ( ! empty( $upload_dir['error'] ) ) {
			return new WP_Error( 'upload_dir_error', $upload_dir['error'] );
		}

		// 生成檔案名稱
		$filename  = 'line-product-' . time() . '-' . $user_id . '.jpg';
		$file_path = $upload_dir['path'] . '/' . $filename;

		// 儲存檔案
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$result = file_put_contents( $file_path, $image_data );

		if ( false === $result ) {
			return new WP_Error( 'file_write_error', '無法寫入檔案' );
		}

		$this->logger->debug( 'File saved', array( 'path' => $file_path ) );

		// 取得檔案類型
		$filetype = wp_check_filetype( $filename, null );

		// 驗證 user_id，確保不是 0
		$original_user_id = $user_id;
		if ( $user_id <= 0 ) {
			$admin_users = get_users( array(
				'role'   => 'administrator',
				'number' => 1,
			) );

			if ( ! empty( $admin_users ) ) {
				$user_id = $admin_users[0]->ID;
			} else {
				$user_id = 1;
			}

			$this->logger->warning(
				'Invalid user_id provided for image upload, using default admin',
				array(
					'provided_user_id' => $original_user_id,
					'fallback_user_id' => $user_id,
				)
			);
		}

		// 建立附件
		$attachment = array(
			'post_mime_type' => $filetype['type'],
			'post_title'     => sanitize_file_name( $filename ),
			'post_content'   => '',
			'post_status'    => 'inherit',
			'post_author'    => $user_id,
		);

		$attachment_id = wp_insert_attachment( $attachment, $file_path );

		if ( is_wp_error( $attachment_id ) ) {
			// 刪除已上傳的檔案
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			unlink( $file_path );
			return $attachment_id;
		}

		// 生成縮圖
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attach_data = wp_generate_attachment_metadata( $attachment_id, $file_path );
		wp_update_attachment_metadata( $attachment_id, $attach_data );

		$this->logger->debug( 'Attachment created', array(
			'attachment_id' => $attachment_id,
			'metadata'      => $attach_data,
		) );

		// 檢查是否使用 Media Cloud
		if ( $this->is_media_cloud_active() ) {
			$this->logger->info( 'Media Cloud is active, image will be uploaded to cloud storage' );
		}

		return $attachment_id;
	}

	/**
	 * 檢查 Media Cloud 是否啟用
	 *
	 * @return bool
	 */
	private function is_media_cloud_active() {
		// 檢查 Media Cloud 外掛是否啟用
		return class_exists( 'MediaCloud\Plugin\Tools\Storage\StorageTool' );
	}

	/**
	 * 暫存圖片 ID
	 *
	 * @param int $user_id WordPress 使用者 ID
	 * @param int $attachment_id 附件 ID
	 */
	private function store_temp_image( $user_id, $attachment_id ) {
		$temp_images = get_user_meta( $user_id, '_buygo_temp_images', true );

		if ( ! is_array( $temp_images ) ) {
			$temp_images = array();
		}

		$temp_images[] = $attachment_id;

		update_user_meta( $user_id, '_buygo_temp_images', $temp_images );

		$this->logger->debug( 'Image stored in temp', array(
			'user_id'       => $user_id,
			'attachment_id' => $attachment_id,
			'total_images'  => count( $temp_images ),
		) );
	}

	/**
	 * 取得暫存圖片
	 *
	 * @param int $user_id WordPress 使用者 ID
	 * @return array
	 */
	public function get_temp_images( $user_id ) {
		$temp_images = get_user_meta( $user_id, '_buygo_temp_images', true );
		return is_array( $temp_images ) ? $temp_images : array();
	}

	/**
	 * 清除暫存圖片
	 *
	 * @param int $user_id WordPress 使用者 ID
	 */
	public function clear_temp_images( $user_id ) {
		delete_user_meta( $user_id, '_buygo_temp_images' );

		$this->logger->debug( 'Temp images cleared', array(
			'user_id' => $user_id,
		) );
	}
}
