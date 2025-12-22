<?php
/**
 * 產品頻道選擇 Meta Box
 *
 * @package BuyGo_Plus_One
 */

// 防止直接存取
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BuyGo_Plus_One_Product_Channel_Meta_Box
 */
class BuyGo_Plus_One_Product_Channel_Meta_Box {

	/**
	 * 初始化
	 */
	public function init() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_meta_box' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * 添加 Meta Box
	 */
	public function add_meta_box() {
		// 只顯示給特定角色
		if ( ! $this->can_use_feature() ) {
			return;
		}

		add_meta_box(
			'buygo_product_channel',
			__( 'FluentCommunity 頻道設定', 'buygo-plus-one' ),
			array( $this, 'render_meta_box' ),
			'fluent-products',
			'side',
			'default'
		);
	}

	/**
	 * 檢查使用者是否有權限使用此功能
	 *
	 * @return bool
	 */
	private function can_use_feature() {
		$user = wp_get_current_user();
		
		// 只允許 WP 管理員、BuyGo 管理員、buygo_seller
		return in_array( 'administrator', (array) $user->roles ) ||
			   in_array( 'buygo_admin', (array) $user->roles ) ||
			   in_array( 'buygo_seller', (array) $user->roles );
	}

	/**
	 * 渲染 Meta Box
	 *
	 * @param WP_Post $post 文章物件
	 */
	public function render_meta_box( $post ) {
		wp_nonce_field( 'buygo_product_channel_meta_box', 'buygo_product_channel_meta_box_nonce' );

		// 取得已儲存的頻道 ID
		$selected_space_id = get_post_meta( $post->ID, '_buygo_community_space_id', true );
		
		// 取得所有頻道
		$spaces = $this->get_spaces();
		
		// 取得當前使用者 ID
		$current_user_id = get_current_user_id();
		
		?>
		<div class="buygo-channel-meta-box">
			<div class="form-field">
				<label for="buygo_community_space_id" class="screen-reader-text">
					<?php esc_html_e( '選擇頻道', 'buygo-plus-one' ); ?>
				</label>
				<select 
					name="buygo_community_space_id" 
					id="buygo_community_space_id" 
					class="postbox"
					style="width: 100%;"
				>
					<option value=""><?php esc_html_e( '請選擇頻道', 'buygo-plus-one' ); ?></option>
					<?php foreach ( $spaces as $space ) : ?>
						<?php
						// 檢查當前使用者是否有權限使用此頻道
						$can_use = $this->can_user_use_space( $current_user_id, $space->id );
						$disabled = ! $can_use ? 'disabled' : '';
						$warning = ! $can_use ? ' (無權限)' : '';
						?>
						<option 
							value="<?php echo esc_attr( $space->id ); ?>" 
							<?php selected( $selected_space_id, $space->id ); ?>
							<?php echo esc_attr( $disabled ); ?>
							data-can-use="<?php echo esc_attr( $can_use ? '1' : '0' ); ?>"
						>
							<?php echo esc_html( $space->title . $warning ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description">
					<?php esc_html_e( '選擇要發布商品貼文的 FluentCommunity 頻道。只有您有權限使用的頻道才會顯示為可選。', 'buygo-plus-one' ); ?>
				</p>
			</div>

			<div id="buygo-channel-permission-warning" class="notice notice-error inline" style="display: none; margin: 10px 0;">
				<p>
					<strong><?php esc_html_e( '錯誤：', 'buygo-plus-one' ); ?></strong>
					<span id="buygo-channel-permission-warning-text"></span>
				</p>
			</div>
		</div>

		<script>
		jQuery(document).ready(function($) {
			var $select = $('#buygo_community_space_id');
			var $warning = $('#buygo-channel-permission-warning');
			var $warningText = $('#buygo-channel-permission-warning-text');
			
			$select.on('change', function() {
				var selectedOption = $(this).find('option:selected');
				var canUse = selectedOption.data('can-use') === '1' || selectedOption.data('can-use') === 1;
				var spaceTitle = selectedOption.text().replace(' (無權限)', '');
				
				if ($(this).val() && !canUse) {
					$warningText.text('您沒有權限使用頻道「' + spaceTitle + '」。請聯繫管理員設定賣家映射或選擇其他頻道。');
					$warning.show();
				} else {
					$warning.hide();
				}
			});
			
			// 觸發一次以檢查初始狀態
			$select.trigger('change');
		});
		</script>
		<?php
	}

	/**
	 * 儲存 Meta Box 資料
	 *
	 * @param int     $post_id 文章 ID
	 * @param WP_Post $post    文章物件
	 */
	public function save_meta_box( $post_id, $post ) {
		// 檢查權限
		if ( ! $this->can_use_feature() ) {
			return;
		}

		// 只處理 fluent-products
		if ( $post->post_type !== 'fluent-products' ) {
			return;
		}

		// 檢查 nonce
		if ( ! isset( $_POST['buygo_product_channel_meta_box_nonce'] ) ||
			 ! wp_verify_nonce( $_POST['buygo_product_channel_meta_box_nonce'], 'buygo_product_channel_meta_box' ) ) {
			return;
		}

		// 檢查是否為自動儲存
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// 檢查使用者權限
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// 取得選擇的頻道 ID
		$space_id = isset( $_POST['buygo_community_space_id'] ) ? intval( $_POST['buygo_community_space_id'] ) : 0;

		// 驗證當前使用者是否有權限使用選擇的頻道
		$current_user_id = get_current_user_id();
		if ( $space_id > 0 ) {
			if ( ! $this->can_user_use_space( $current_user_id, $space_id ) ) {
				wp_die(
					esc_html__( '錯誤：您沒有權限使用選擇的頻道。請聯繫管理員設定賣家映射或選擇其他頻道。', 'buygo-plus-one' ),
					esc_html__( '儲存失敗', 'buygo-plus-one' ),
					array( 'back_link' => true )
				);
			}
		}

		// 儲存頻道 ID
		if ( $space_id > 0 ) {
			update_post_meta( $post_id, '_buygo_community_space_id', $space_id );
		} else {
			delete_post_meta( $post_id, '_buygo_community_space_id' );
		}
	}

	/**
	 * 取得所有頻道
	 *
	 * @return array
	 */
	private function get_spaces() {
		if ( ! class_exists( '\FluentCommunity\App\Models\Space' ) ) {
			return array();
		}

		try {
			$spaces = \FluentCommunity\App\Models\Space::all();
			return $spaces ? $spaces : array();
		} catch ( Exception $e ) {
			return array();
		}
	}

	/**
	 * 取得使用者的 LINE UID
	 *
	 * @param int $user_id 使用者 ID
	 * @return string
	 */
	private function get_line_uid( $user_id ) {
		if ( class_exists( '\BuyGo\Core\Services\LineService' ) ) {
			$line_service = \BuyGo\Core\App::instance()->make( \BuyGo\Core\Services\LineService::class );
			return $line_service->get_line_uid( $user_id );
		}

		// Fallback: 從 user meta 取得
		return get_user_meta( $user_id, 'buygo_line_uid', true );
	}

	/**
	 * 檢查使用者是否有權限使用指定的頻道
	 *
	 * @param int $user_id 使用者 ID
	 * @param int $space_id 頻道 ID
	 * @return bool
	 */
	private function can_user_use_space( $user_id, $space_id ) {
		if ( $user_id <= 0 || $space_id <= 0 ) {
			return false;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		// 管理員和 BuyGo 管理員可以使用任何頻道
		if ( $user->has_cap( 'administrator' ) || in_array( 'buygo_admin', (array) $user->roles, true ) ) {
			return true;
		}

		// 檢查賣家映射
		$mappings = get_option( 'buygo_plus_one_seller_mappings', array() );
		if ( is_array( $mappings ) ) {
			foreach ( $mappings as $mapping ) {
				if ( isset( $mapping['user_id'] ) && intval( $mapping['user_id'] ) === $user_id ) {
					if ( isset( $mapping['space_id'] ) && intval( $mapping['space_id'] ) === $space_id ) {
						if ( isset( $mapping['is_active'] ) && $mapping['is_active'] ) {
							return true;
						}
					}
				}
			}
		}

		// 如果沒有明確的映射，檢查是否為賣家且使用預設頻道
		if ( in_array( 'buygo_seller', (array) $user->roles, true ) ) {
			$global_space_id = get_option( 'buygo_plus_one_default_space_id' );
			if ( ! empty( $global_space_id ) && intval( $global_space_id ) === $space_id ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * 載入腳本
	 */
	public function enqueue_scripts() {
		$screen = get_current_screen();
		if ( ! $screen || $screen->post_type !== 'fluent-products' ) {
			return;
		}

		// 如果需要額外的 CSS 或 JS，可以在這裡載入
	}
}
