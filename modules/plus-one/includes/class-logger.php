<?php
/**
 * 日誌記錄類別
 *
 * @package BuyGo_Plus_One
 */

// 防止直接存取
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BuyGo_Plus_One_Logger
 */
class BuyGo_Plus_One_Logger {

	/**
	 * 單例實例
	 *
	 * @var BuyGo_Plus_One_Logger
	 */
	private static $instance = null;

	/**
	 * 日誌檔案路徑
	 *
	 * @var string
	 */
	private $log_file;

	/**
	 * 建構函數
	 */
	private function __construct() {
		$this->log_file = WP_CONTENT_DIR . '/buygo-plus-one.log';
	}

	/**
	 * 取得單例實例
	 *
	 * @return BuyGo_Plus_One_Logger
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * 記錄資訊級別日誌
	 *
	 * @param string $message 訊息
	 * @param array  $context 上下文資料
	 */
	public function info( $message, $context = array() ) {
		$this->log( 'INFO', $message, $context );
	}

	/**
	 * 記錄錯誤級別日誌
	 *
	 * @param string $message 訊息
	 * @param array  $context 上下文資料
	 */
	public function error( $message, $context = array() ) {
		$this->log( 'ERROR', $message, $context );
	}

	/**
	 * 記錄警告級別日誌
	 *
	 * @param string $message 訊息
	 * @param array  $context 上下文資料
	 */
	public function warning( $message, $context = array() ) {
		$this->log( 'WARNING', $message, $context );
	}

	/**
	 * 記錄除錯級別日誌
	 *
	 * @param string $message 訊息
	 * @param array  $context 上下文資料
	 */
	public function debug( $message, $context = array() ) {
		// 只在除錯模式下記錄
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$this->log( 'DEBUG', $message, $context );
		}
	}

	/**
	 * 寫入日誌
	 *
	 * @param string $level   級別
	 * @param string $message 訊息
	 * @param array  $context 上下文資料
	 */
	private function log( $level, $message, $context = array() ) {
		$timestamp = current_time( 'Y-m-d H:i:s' );
		$log_entry = "[{$timestamp}] [{$level}] {$message}";

		if ( ! empty( $context ) ) {
			$log_entry .= ' ' . wp_json_encode( $context, JSON_UNESCAPED_UNICODE );
		}

		$log_entry .= "\n";

		// 寫入檔案
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $this->log_file, $log_entry, FILE_APPEND );
	}

	/**
	 * 清除日誌檔案
	 */
	public function clear() {
		if ( file_exists( $this->log_file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			unlink( $this->log_file );
		}
	}

	/**
	 * 取得日誌內容
	 *
	 * @param int $lines 行數（預設 100 行）
	 * @return string
	 */
	public function get_logs( $lines = 100 ) {
		if ( ! file_exists( $this->log_file ) ) {
			return '';
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$content = file_get_contents( $this->log_file );
		$log_lines = explode( "\n", $content );
		
		// 取得最後 N 行
		$log_lines = array_slice( $log_lines, -$lines );
		
		return implode( "\n", $log_lines );
	}
}
