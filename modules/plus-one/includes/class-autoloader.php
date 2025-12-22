<?php
/**
 * 自動載入器類別
 *
 * 自動載入外掛的類別檔案
 *
 * @package BuyGo_Plus_One
 */

// 防止直接存取
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BuyGo_Plus_One_Autoloader
 */
class BuyGo_Plus_One_Autoloader {

	/**
	 * 初始化自動載入器
	 */
	public static function init() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * 自動載入類別
	 *
	 * @param string $class 類別名稱
	 */
	public static function autoload( $class ) {
		// 只處理我們的類別
		if ( strpos( $class, 'BuyGo_Plus_One_' ) !== 0 ) {
			return;
		}

		// 轉換類別名稱為檔案路徑
		// BuyGo_Plus_One_Role_Manager -> class-role-manager.php
		$class_name = str_replace( 'BuyGo_Plus_One_', '', $class );
		$class_name = strtolower( str_replace( '_', '-', $class_name ) );
		$filename   = 'class-' . $class_name . '.php';

		// 可能的檔案位置
		$paths = array(
			BUYGO_PLUS_ONE_PATH . 'includes/' . $filename,
			BUYGO_PLUS_ONE_PATH . 'includes/services/' . $filename,
			BUYGO_PLUS_ONE_PATH . 'includes/admin/' . $filename,
			BUYGO_PLUS_ONE_PATH . 'includes/frontend/' . $filename,
			BUYGO_PLUS_ONE_PATH . 'includes/api/' . $filename, // API Controllers
		);

		// 嘗試載入檔案
		foreach ( $paths as $path ) {
			if ( file_exists( $path ) ) {
				require_once $path;
				return;
			}
		}
	}
}
