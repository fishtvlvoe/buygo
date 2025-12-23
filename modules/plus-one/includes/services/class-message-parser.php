<?php
/**
 * 訊息解析器類別
 *
 * @package BuyGo_LINE_FluentCart
 */

// 防止直接存取
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BuyGo_Plus_One_Message_Parser
 */
class BuyGo_Plus_One_Message_Parser {

	/**
	 * 必填欄位
	 *
	 * @var array
	 */
	private $required_fields = array( 'name', 'price' );

	/**
	 * Logger
	 *
	 * @var BuyGo_Plus_One_Logger
	 */
	private $logger;

	/**
	 * 建構函數
	 */
	public function __construct() {
		$this->logger = BuyGo_Plus_One_Logger::get_instance();
	}

	/**
	 * 解析訊息
	 *
	 * @param string $message 訊息內容
	 * @return array
	 */
	public function parse( $message ) {
		$data = array();

		// 分割訊息為多行
		$lines = explode( "\n", trim( $message ) );

		// 第一行為商品名稱
		if ( ! empty( $lines[0] ) ) {
			$data['name'] = trim( $lines[0] );
		}

		// 解析其他欄位
		foreach ( $lines as $line ) {
			$this->parse_line( $line, $data );
		}

		// 如果有多個數量、價格或原價，在解析完成後分配給 variations
		// 這樣可以確保即使這些欄位在分類之後解析，也能正確分配
		if ( ! empty( $data['variations'] ) && is_array( $data['variations'] ) ) {
			// 分配數量
			if ( ! empty( $data['quantities'] ) ) {
				foreach ( $data['variations'] as $index => $variation ) {
					if ( isset( $data['quantities'][ $index ] ) ) {
						$data['variations'][ $index ]['quantity'] = $data['quantities'][ $index ];
					}
				}
			}
			
			// 分配價格
			if ( ! empty( $data['prices'] ) ) {
				foreach ( $data['variations'] as $index => $variation ) {
					if ( isset( $data['prices'][ $index ] ) ) {
						$data['variations'][ $index ]['price'] = $data['prices'][ $index ];
					}
				}
			}
			
			// 分配原價
			if ( ! empty( $data['compare_prices'] ) ) {
				foreach ( $data['variations'] as $index => $variation ) {
					if ( isset( $data['compare_prices'][ $index ] ) ) {
						$data['variations'][ $index ]['compare_price'] = $data['compare_prices'][ $index ];
					}
				}
			}
		}

		$this->logger->debug( 'Parsed message', $data );

		return $data;
	}

	/**
	 * 解析單行
	 *
	 * @param string $line 行內容
	 * @param array  $data 資料陣列（參考傳遞）
	 */
	private function parse_line( $line, &$data ) {
		$line = trim( $line );

		// 幣別識別：日幣：1200 或 台幣：350
		if ( preg_match( '/(日幣|美金|台幣|人民幣|港幣)\s*[：:]\s*(.+)/u', $line, $matches ) ) {
			$currency = trim( $matches[1] );
			$price_string = trim( $matches[2] );
			
			// 儲存幣別
			$currency_map = array(
				'日幣' => 'JPY',
				'美金' => 'USD',
				'台幣' => 'TWD',
				'人民幣' => 'CNY',
				'港幣' => 'HKD',
			);
			$data['currency'] = isset( $currency_map[ $currency ] ) ? $currency_map[ $currency ] : 'TWD';
			
			// 檢查是否包含斜線（多個價格）
			if ( strpos( $price_string, '/' ) !== false ) {
				// 多個價格
				$prices = explode( '/', $price_string );
				$prices = array_map( 'trim', $prices );
				
				$parsed_prices = array();
				foreach ( $prices as $price_str ) {
					$temp_data = array();
					$this->parse_price( $price_str, $temp_data );
					if ( isset( $temp_data['price'] ) ) {
						$parsed_prices[] = $temp_data['price'];
					} else {
						$parsed_prices[] = 0;
					}
				}
				
				$data['prices'] = $parsed_prices;
				$data['price'] = ! empty( $parsed_prices[0] ) ? $parsed_prices[0] : 0;
			} else {
				// 單一價格
				$this->parse_price( $price_string, $data );
			}
		}
		// 價格：350 (支援全形和半形冒號)
		// 支援格式：價格：350 或 價格：NT$350
		// 支援多個價格：價格：1000/1200/1500（用斜線分隔，對應多款式商品）
		else if ( preg_match( '/價格\s*[：:]\s*(.+)/u', $line, $matches ) ) {
			$price_string = trim( $matches[1] );
			
			// 預設幣別為台幣
			if ( ! isset( $data['currency'] ) ) {
				$data['currency'] = 'TWD';
			}
			
			// 檢查是否包含斜線（多個價格）
			if ( strpos( $price_string, '/' ) !== false ) {
				// 多個價格：價格：1000/1200/1500
				$prices = explode( '/', $price_string );
				$prices = array_map( 'trim', $prices );
				
				// 解析每個價格
				$parsed_prices = array();
				foreach ( $prices as $price_str ) {
					$temp_data = array();
					$this->parse_price( $price_str, $temp_data );
					if ( isset( $temp_data['price'] ) ) {
						$parsed_prices[] = $temp_data['price'];
					} else {
						$parsed_prices[] = 0;
					}
				}
				
				// 儲存到 data 中，供後續分配給 variations
				$data['prices'] = $parsed_prices;
				
				// 也設定主價格（取第一個，作為預設值）
				$data['price'] = ! empty( $parsed_prices[0] ) ? $parsed_prices[0] : 0;
			} else {
				// 單一價格：價格：350
				$this->parse_price( $price_string, $data );
			}
		}

		// 原價：500
		// 支援多個原價：原價：1500/1800/2000（用斜線分隔，對應多款式商品）
		if ( preg_match( '/原價\s*[：:]\s*(.+)/u', $line, $matches ) ) {
			$price_string = trim( $matches[1] );
			
			// 檢查是否包含斜線（多個原價）
			if ( strpos( $price_string, '/' ) !== false ) {
				// 多個原價：原價：1500/1800/2000
				$compare_prices = explode( '/', $price_string );
				$compare_prices = array_map( 'trim', $compare_prices );
				
				// 解析每個原價
				$parsed_compare_prices = array();
				foreach ( $compare_prices as $price_str ) {
					$temp_data = array();
					$this->parse_price( $price_str, $temp_data );
					if ( isset( $temp_data['price'] ) ) {
						$parsed_compare_prices[] = $temp_data['price'];
					} else {
						$parsed_compare_prices[] = 0;
					}
				}
				
				// 儲存到 data 中，供後續分配給 variations
				$data['compare_prices'] = $parsed_compare_prices;
				
				// 也設定主原價（取第一個，作為預設值）
				$data['compare_price'] = ! empty( $parsed_compare_prices[0] ) ? $parsed_compare_prices[0] : 0;
			} else {
				// 單一原價：原價：500
				$temp_data = array();
				$this->parse_price( $price_string, $temp_data );
				if ( isset( $temp_data['price'] ) ) {
					$data['compare_price'] = $temp_data['price'];
				}
			}
		}

		// 庫存：20 或 數量：20
		// 支援多個數量：數量：5/10/3（用斜線分隔，對應多款式商品）
		if ( preg_match( '/(庫存|數量)\s*[：:]\s*(.+)/u', $line, $matches ) ) {
			$quantity_string = trim( $matches[2] );
			
			// 檢查是否包含斜線（多個數量）
			if ( strpos( $quantity_string, '/' ) !== false ) {
				// 多個數量：數量：5/10/3
				$quantities = explode( '/', $quantity_string );
				$quantities = array_map( 'trim', $quantities );
				$quantities = array_map( 'intval', $quantities );
				
				// 儲存到 data 中，供後續分配給 variations
				$data['quantities'] = $quantities;
				
				// 也設定主數量（取第一個，作為預設值）
				$data['quantity'] = ! empty( $quantities[0] ) ? $quantities[0] : 0;
			} else {
				// 單一數量：數量：20
				$data['quantity'] = intval( $quantity_string );
			}
		}

		// 分類：服飾
		if ( preg_match( '/分類\s*[：:]\s*(.+)/u', $line, $matches ) ) {
			$category_string = trim( $matches[1] );
			
			// 檢查是否為多樣式商品 (包含 / 符號)
			if ( strpos( $category_string, '/' ) !== false ) {
				$data['is_variable'] = true;
				$data['category'] = '多樣式商品'; // 這裡可能需要一個更好的預設分類名稱，或取第一個分類
				
				$categories = explode( '/', $category_string );
				$variations = array();
				$letters = range( 'A', 'Z' ); // A, B, C...
				
				// 計算有效的 variation 索引（跳過空的分類）
				$variation_index = 0;
				
				foreach ( $categories as $index => $cat_name ) {
					$cat_name = trim( $cat_name );
					if ( empty( $cat_name ) ) {
						continue;
					}
					
					$code = isset( $letters[ $variation_index ] ) ? $letters[ $variation_index ] : 'Z' . $variation_index; // 超過 Z 的處理
					
					$variation = array(
						'code' => $code,
						'name' => $cat_name,
						'variation_title' => sprintf( '(%s) %s', $code, $cat_name ), // (A) 漢頓
					);
					
					// 如果有多個數量，分配給對應的 variation（使用原始分類索引，因為數量是對應原始分類順序）
					if ( ! empty( $data['quantities'] ) && isset( $data['quantities'][ $index ] ) ) {
						$variation['quantity'] = $data['quantities'][ $index ];
					}
					
					// 如果有多個價格，分配給對應的 variation
					if ( ! empty( $data['prices'] ) && isset( $data['prices'][ $index ] ) ) {
						$variation['price'] = $data['prices'][ $index ];
					}
					
					// 如果有多個原價，分配給對應的 variation
					if ( ! empty( $data['compare_prices'] ) && isset( $data['compare_prices'][ $index ] ) ) {
						$variation['compare_price'] = $data['compare_prices'][ $index ];
					}
					
					$variations[] = $variation;
					$variation_index++; // 只有當成功建立 variation 時才增加索引
				}
				
				$data['variations'] = $variations;
				
				// 若有多個分類，取第一個非空分類作為主分類，以便後續歸類
				if ( ! empty( $variations ) ) {
					$data['category'] = $variations[0]['name'];
				}
				
				// 注意：如果數量是在分類之後解析的，需要在 parse 方法的最後再次分配數量
				// 這個邏輯會在 parse 方法的最後處理（見下方）
				
			} else {
				$data['category'] = $category_string;
			}
		}

		// 到貨：01/25 或 到貨：2025/01/25 或 到貨：01-25
		if ( preg_match( '/到貨\s*[：:]\s*(\d{1,4}[\/\-]\d{1,2}(?:[\/\-]\d{1,2})?)/u', $line, $matches ) ) {
			$parsed_date = $this->parse_date( $matches[1] );
			if ( ! empty( $parsed_date ) ) {
				$data['arrival_date'] = $parsed_date;
			}
		}

		// 預購：01/20 或 預購：2025/01/20 或 預購：01-20
		if ( preg_match( '/預購\s*[：:]\s*(\d{1,4}[\/\-]\d{1,2}(?:[\/\-]\d{1,2})?)/u', $line, $matches ) ) {
			$parsed_date = $this->parse_date( $matches[1] );
			if ( ! empty( $parsed_date ) ) {
				$data['preorder_date'] = $parsed_date;
			}
		}

		// 描述：商品描述
		if ( preg_match( '/描述\s*[：:]\s*(.+)/u', $line, $matches ) ) {
			$data['description'] = trim( $matches[1] );
		}
	}

	/**
	 * 解析價格
	 *
	 * @param string $price_string 價格字串
	 * @param array  $data 資料陣列（參考傳遞）
	 */
	private function parse_price( $price_string, &$data ) {
		// 移除所有空白和貨幣符號以便解析
		$clean_string = preg_replace( '/\s+/', '', $price_string );
		
		// 移除常見的貨幣符號和代碼（NT$, $, ¥, TWD, JPY, USD 等）
		$clean_string = preg_replace( '/NT\$|[¥\$]|TWD|JPY|USD/iu', '', $clean_string );
		
		// 提取數字
		if ( preg_match( '/(\d+(?:\.\d+)?)/u', $clean_string, $matches ) ) {
			$price = floatval( $matches[1] );
		} else {
			$price = 0;
		}

		$data['price'] = $price;

		$this->logger->debug(
			'Price parsed',
			array(
				'input' => $price_string,
				'price' => $price,
			)
		);
	}

	/**
	 * 解析日期
	 *
	 * @param string $date_string 日期字串
	 * @return string YYYY-MM-DD 格式
	 */
	private function parse_date( $date_string ) {
		// 移除空白
		$date_string = trim( $date_string );

		// 分割日期
		$parts = preg_split( '/[\/\-]/', $date_string );

		if ( count( $parts ) === 2 ) {
			// MM/DD 格式
			$month = str_pad( $parts[0], 2, '0', STR_PAD_LEFT );
			$day   = str_pad( $parts[1], 2, '0', STR_PAD_LEFT );
			$year  = date( 'Y' );

			// 如果月份小於當前月份，表示是明年
			if ( intval( $month ) < intval( date( 'm' ) ) ) {
				$year++;
			}

			return "{$year}-{$month}-{$day}";
		} elseif ( count( $parts ) === 3 ) {
			// YYYY/MM/DD 或 MM/DD/YYYY 格式
			if ( strlen( $parts[0] ) === 4 ) {
				// YYYY/MM/DD
				$year  = $parts[0];
				$month = str_pad( $parts[1], 2, '0', STR_PAD_LEFT );
				$day   = str_pad( $parts[2], 2, '0', STR_PAD_LEFT );
			} else {
				// MM/DD/YYYY
				$month = str_pad( $parts[0], 2, '0', STR_PAD_LEFT );
				$day   = str_pad( $parts[1], 2, '0', STR_PAD_LEFT );
				$year  = $parts[2];
			}

			return "{$year}-{$month}-{$day}";
		}

		// 無法解析，返回原始字串
		return $date_string;
	}

	/**
	 * 驗證資料
	 *
	 * @param array $data 商品資料
	 * @return array
	 */
	public function validate( $data ) {
		$errors = array();

		// 檢查必填欄位
		foreach ( $this->required_fields as $field ) {
			if ( empty( $data[ $field ] ) ) {
				$errors[] = $field;
			}
		}

		// 驗證價格
		if ( isset( $data['price'] ) && $data['price'] <= 0 ) {
			$errors[] = 'price_invalid';
		}

		// 驗證庫存
		if ( isset( $data['quantity'] ) && $data['quantity'] < 0 ) {
			$errors[] = 'quantity_invalid';
		}

		// 驗證日期格式
		if ( isset( $data['arrival_date'] ) && ! $this->is_valid_date( $data['arrival_date'] ) ) {
			$errors[] = 'arrival_date_invalid';
		}

		if ( isset( $data['preorder_date'] ) && ! $this->is_valid_date( $data['preorder_date'] ) ) {
			$errors[] = 'preorder_date_invalid';
		}

		$result = array(
			'valid'   => empty( $errors ),
			'missing' => $errors,
		);

		$this->logger->debug( 'Validation result', $result );

		return $result;
	}

	/**
	 * 檢查日期格式是否有效
	 *
	 * @param string $date 日期字串
	 * @return bool
	 */
	private function is_valid_date( $date ) {
		$d = \DateTime::createFromFormat( 'Y-m-d', $date );
		return $d && $d->format( 'Y-m-d' ) === $date;
	}

	/**
	 * 檢查是否為指令
	 *
	 * @param string $message 訊息內容
	 * @return bool
	 */
	public function is_command( $message ) {
		// 先從資料庫讀取動態關鍵字
		$keywords = get_option( 'buygo_line_keywords', [] );
		$message_trimmed = trim( $message );
		
		// 檢查是否匹配任何關鍵字或別名
		foreach ( $keywords as $keyword_data ) {
			$keyword = trim( $keyword_data['keyword'] ?? '' );
			$aliases = $keyword_data['aliases'] ?? [];
			
			if ( $message_trimmed === $keyword ) {
				return true;
			}
			
			foreach ( $aliases as $alias ) {
				if ( $message_trimmed === trim( $alias ) ) {
					return true;
				}
			}
		}
		
		// 保留原有的固定指令（向後相容）
		$legacy_commands = array(
			'/help',
			'/幫助',
			'/分類',
			'?help',
			'?幫助',
			'?分類',
			'幫助',
			'分類列表',
		);
		return in_array( $message_trimmed, $legacy_commands, true );
	}

	/**
	 * 檢查是否為 +1 訊息
	 *
	 * @param string $message 訊息內容
	 * @return bool
	 */
	public function is_plus_one( $message ) {
		$message = trim( $message );
		
		// 1. 純數字喊單: +1, +2, +10 (隱含商品 ID)
		if ( preg_match( '/^\+(\d+)$/', $message ) ) {
			return true;
		}

		// 2. 指定商品喊單: P001+1, Jacket+1
		if ( preg_match( '/^([a-zA-Z0-9_\-\p{Han}]+)\s*\+(\d+)$/u', $message ) ) {
			return true;
		}

		return false;
	}

	/**
	 * 解析 +1 訊息
	 *
	 * @param string $message 訊息內容
	 * @return array
	 */
	public function parse_plus_one( $message ) {
		$message = trim( $message );
		$result = array(
			'product_id' => '',
			'quantity'   => 1,
			'is_standalone' => false, // 標記是否為純數字喊單
		);

		// 1. 純數字喊單: +1, +2
		if ( preg_match( '/^\+(\d+)$/', $message, $matches ) ) {
			$result['quantity'] = intval( $matches[1] );
			$result['is_standalone'] = true;
			return $result;
		}

		// 2. 指定商品喊單: P001+1
		if ( preg_match( '/^([a-zA-Z0-9_\-\p{Han}]+)\s*\+(\d+)$/u', $message, $matches ) ) {
			$result['product_id'] = $matches[1];
			$result['quantity']   = intval( $matches[2] );
		}

		$this->logger->debug( 'Parsed +1 message', $result );

		return $result;
	}
}
