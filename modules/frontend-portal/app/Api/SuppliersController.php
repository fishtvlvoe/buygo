<?php
/**
 * SuppliersController
 *
 * [AI Context]
 * - Handles supplier management API endpoints
 * - Provides supplier list, detail, and settlement operations
 *
 * [Constraints]
 * - Must check user permissions using BuyGo RoleManager
 * - Must verify Nonce for all requests
 */

namespace BuyGo\Modules\FrontendPortal\App\Api;

use BuyGo\Modules\FrontendPortal\App\Models\Supplier;
use BuyGo\Modules\FrontendPortal\App\Services\SuppliersService;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SuppliersController extends BaseController {

	/**
	 * Register routes
	 */
	public function register_routes() {
		register_rest_route( $this->namespace, '/suppliers', [
			[
				'methods' => 'GET',
				'callback' => [ $this, 'get_suppliers' ],
				'permission_callback' => [ $this, 'check_read_permission' ],
			],
			[
				'methods' => 'POST',
				'callback' => [ $this, 'create_supplier' ],
				'permission_callback' => [ $this, 'check_write_permission' ],
			],
		] );

		register_rest_route( $this->namespace, '/suppliers/(?P<id>\d+)', [
			[
				'methods' => 'GET',
				'callback' => [ $this, 'get_supplier' ],
				'permission_callback' => [ $this, 'check_read_permission' ],
			],
			[
				'methods' => 'PUT',
				'callback' => [ $this, 'update_supplier' ],
				'permission_callback' => [ $this, 'check_write_permission' ],
			],
			[
				'methods' => 'DELETE',
				'callback' => [ $this, 'delete_supplier' ],
				'permission_callback' => [ $this, 'check_write_permission' ],
			],
		] );

		register_rest_route( $this->namespace, '/suppliers/(?P<id>\d+)/detail', [
			[
				'methods' => 'GET',
				'callback' => [ $this, 'get_supplier_detail' ],
				'permission_callback' => [ $this, 'check_read_permission' ],
				'args' => [
					'period_start' => [
						'type' => 'string',
						'format' => 'date',
						'default' => null,
					],
					'period_end' => [
						'type' => 'string',
						'format' => 'date',
						'default' => null,
					],
				],
			],
		] );

		register_rest_route( $this->namespace, '/suppliers/(?P<id>\d+)/settle', [
			[
				'methods' => 'POST',
				'callback' => [ $this, 'create_settlement' ],
				'permission_callback' => [ $this, 'check_write_permission' ],
				'args' => [
					'period_start' => [
						'type' => 'string',
						'format' => 'date',
						'required' => true,
					],
					'period_end' => [
						'type' => 'string',
						'format' => 'date',
						'required' => true,
					],
					'notes' => [
						'type' => 'string',
						'default' => '',
					],
				],
			],
		] );

		register_rest_route( $this->namespace, '/suppliers/(?P<id>\d+)/export', [
			[
				'methods' => 'GET',
				'callback' => [ $this, 'export_supplier_detail' ],
				'permission_callback' => [ $this, 'check_read_permission' ],
				'args' => [
					'format' => [
						'type' => 'string',
						'enum' => [ 'csv', 'pdf' ],
						'default' => 'csv',
					],
					'period_start' => [
						'type' => 'string',
						'format' => 'date',
					],
					'period_end' => [
						'type' => 'string',
						'format' => 'date',
					],
				],
			],
		] );

		register_rest_route( $this->namespace, '/suppliers/recalculate', [
			[
				'methods' => 'POST',
				'callback' => [ $this, 'recalculate_order_costs' ],
				'permission_callback' => [ $this, 'check_write_permission' ],
			],
		] );
	}

	/**
	 * Get suppliers list
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_suppliers( $request ) {
		$user_id = get_current_user_id();
		$service = new SuppliersService();

		$args = [
			'page' => (int) $request->get_param( 'page' ) ?: 1,
			'per_page' => (int) $request->get_param( 'per_page' ) ?: 20,
			'search' => sanitize_text_field( $request->get_param( 'search' ) ?: '' ),
			'status' => sanitize_text_field( $request->get_param( 'status' ) ?: 'all' ),
		];

		$result = $service->getSuppliers( $user_id, $args );

		return rest_ensure_response( [
			'success' => true,
			'data' => $result,
		] );
	}

	/**
	 * Get single supplier
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_supplier( $request ) {
		$supplier_id = (int) $request->get_param( 'id' );
		$supplier = Supplier::find( $supplier_id );

		if ( ! $supplier ) {
			return new WP_Error( 'not_found', 'Supplier not found', [ 'status' => 404 ] );
		}

		return rest_ensure_response( [
			'success' => true,
			'data' => $supplier,
		] );
	}

	/**
	 * Create supplier
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_supplier( $request ) {
		$data = $request->get_json_params();

		// Validate required fields
		$errors = [];
		$required_fields = [
			'name' => '供應商名稱',
			'contact_name' => '聯絡人姓名',
			'phone' => '電話',
			'email' => 'Email',
			'line_id' => 'Line ID',
			'address' => '地址',
			'bank_account' => '銀行帳號',
			'bank_name' => '銀行名稱',
			'bank_branch' => '分行',
			'tax_id' => '統一編號',
		];

		foreach ( $required_fields as $field => $label ) {
			if ( empty( $data[ $field ] ) || trim( $data[ $field ] ) === '' ) {
				$errors[ $field ] = "請輸入{$label}";
			}
		}

		// Validate email format
		if ( ! empty( $data['email'] ) && ! is_email( $data['email'] ) ) {
			$errors['email'] = 'Email 格式不正確';
		}

		// Validate phone format (basic)
		if ( ! empty( $data['phone'] ) && ! preg_match( '/^[\d\s\-+()]+$/', $data['phone'] ) ) {
			$errors['phone'] = '電話格式不正確';
		}

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'validation_failed', '表單驗證失敗', [
				'status' => 400,
				'errors' => $errors,
			] );
		}

		$supplier_data = [
			'name' => sanitize_text_field( $data['name'] ?? '' ),
			'contact_name' => sanitize_text_field( $data['contact_name'] ?? '' ),
			'phone' => sanitize_text_field( $data['phone'] ?? '' ),
			'email' => sanitize_email( $data['email'] ?? '' ),
			'line_id' => sanitize_text_field( $data['line_id'] ?? '' ),
			'tax_id' => sanitize_text_field( $data['tax_id'] ?? '' ),
			'address' => sanitize_textarea_field( $data['address'] ?? '' ),
			'bank_account' => sanitize_text_field( $data['bank_account'] ?? '' ),
			'bank_name' => sanitize_text_field( $data['bank_name'] ?? '' ),
			'bank_branch' => sanitize_text_field( $data['bank_branch'] ?? '' ),
			'notes' => sanitize_textarea_field( $data['notes'] ?? '' ),
			'status' => sanitize_text_field( $data['status'] ?? 'active' ),
		];

		$supplier_id = Supplier::create( $supplier_data );

		if ( ! $supplier_id ) {
			return new WP_Error( 'create_failed', 'Failed to create supplier', [ 'status' => 500 ] );
		}

		$supplier = Supplier::find( $supplier_id );

		return rest_ensure_response( [
			'success' => true,
			'data' => $supplier,
		] );
	}

	/**
	 * Update supplier
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_supplier( $request ) {
		$supplier_id = (int) $request->get_param( 'id' );
		$data = $request->get_json_params();

		$supplier = Supplier::find( $supplier_id );
		if ( ! $supplier ) {
			return new WP_Error( 'not_found', 'Supplier not found', [ 'status' => 404 ] );
		}

		$update_data = [];
		if ( isset( $data['name'] ) ) {
			$update_data['name'] = sanitize_text_field( $data['name'] );
		}
		if ( isset( $data['contact_name'] ) ) {
			$update_data['contact_name'] = sanitize_text_field( $data['contact_name'] );
		}
		if ( isset( $data['phone'] ) ) {
			$update_data['phone'] = sanitize_text_field( $data['phone'] );
		}
		if ( isset( $data['email'] ) ) {
			$update_data['email'] = sanitize_email( $data['email'] );
		}
		if ( isset( $data['line_id'] ) ) {
			$update_data['line_id'] = sanitize_text_field( $data['line_id'] );
		}
		if ( isset( $data['tax_id'] ) ) {
			$update_data['tax_id'] = sanitize_text_field( $data['tax_id'] );
		}
		if ( isset( $data['address'] ) ) {
			$update_data['address'] = sanitize_textarea_field( $data['address'] );
		}
		if ( isset( $data['bank_account'] ) ) {
			$update_data['bank_account'] = sanitize_text_field( $data['bank_account'] );
		}
		if ( isset( $data['bank_name'] ) ) {
			$update_data['bank_name'] = sanitize_text_field( $data['bank_name'] );
		}
		if ( isset( $data['bank_branch'] ) ) {
			$update_data['bank_branch'] = sanitize_text_field( $data['bank_branch'] );
		}
		if ( isset( $data['notes'] ) ) {
			$update_data['notes'] = sanitize_textarea_field( $data['notes'] );
		}
		if ( isset( $data['status'] ) ) {
			$update_data['status'] = sanitize_text_field( $data['status'] );
		}

		$result = Supplier::update( $supplier_id, $update_data );

		if ( ! $result ) {
			return new WP_Error( 'update_failed', 'Failed to update supplier', [ 'status' => 500 ] );
		}

		$supplier = Supplier::find( $supplier_id );

		return rest_ensure_response( [
			'success' => true,
			'data' => $supplier,
		] );
	}

	/**
	 * Delete supplier
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_supplier( $request ) {
		$supplier_id = (int) $request->get_param( 'id' );

		$supplier = Supplier::find( $supplier_id );
		if ( ! $supplier ) {
			return new WP_Error( 'not_found', 'Supplier not found', [ 'status' => 404 ] );
		}

		$result = Supplier::delete( $supplier_id );

		if ( ! $result ) {
			return new WP_Error( 'delete_failed', 'Failed to delete supplier', [ 'status' => 500 ] );
		}

		return rest_ensure_response( [
			'success' => true,
			'message' => 'Supplier deleted successfully',
		] );
	}

	/**
	 * Get supplier detail with product sales summary
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_supplier_detail( $request ) {
		$user_id = get_current_user_id();
		$supplier_id = (int) $request->get_param( 'id' );
		$service = new SuppliersService();

		$period_start = $request->get_param( 'period_start' );
		$period_end = $request->get_param( 'period_end' );

		$detail = $service->getSupplierDetail( $supplier_id, $user_id, $period_start, $period_end );

		if ( ! $detail ) {
			return new WP_Error( 'not_found', 'Supplier not found or access denied', [ 'status' => 404 ] );
		}

		return rest_ensure_response( [
			'success' => true,
			'data' => $detail,
		] );
	}

	/**
	 * Create settlement
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_settlement( $request ) {
		$user_id = get_current_user_id();
		$supplier_id = (int) $request->get_param( 'id' );
		$service = new SuppliersService();

		$period_start = sanitize_text_field( $request->get_param( 'period_start' ) );
		$period_end = sanitize_text_field( $request->get_param( 'period_end' ) );
		$notes = sanitize_textarea_field( $request->get_param( 'notes' ) ?: '' );

		if ( ! $period_start || ! $period_end ) {
			return new WP_Error( 'invalid_data', 'Period start and end dates are required', [ 'status' => 400 ] );
		}

		$settlement_id = $service->createSettlement( $supplier_id, $user_id, $period_start, $period_end, $notes );

		if ( ! $settlement_id ) {
			return new WP_Error( 'create_failed', 'Failed to create settlement', [ 'status' => 500 ] );
		}

		return rest_ensure_response( [
			'success' => true,
			'data' => [
				'settlement_id' => $settlement_id,
			],
		] );
	}

	/**
	 * Export supplier detail (CSV or PDF)
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function export_supplier_detail( $request ) {
		$user_id = get_current_user_id();
		$supplier_id = (int) $request->get_param( 'id' );
		$format = sanitize_text_field( $request->get_param( 'format' ) ?: 'csv' );
		$service = new SuppliersService();

		$period_start = $request->get_param( 'period_start' );
		$period_end = $request->get_param( 'period_end' );

		$detail = $service->getSupplierDetail( $supplier_id, $user_id, $period_start, $period_end );

		if ( ! $detail ) {
			return new WP_Error( 'not_found', 'Supplier not found or access denied', [ 'status' => 404 ] );
		}

		if ( $format === 'csv' ) {
			return $this->export_csv( $detail );
		} elseif ( $format === 'pdf' ) {
			return $this->export_pdf( $detail );
		}

		return new WP_Error( 'invalid_format', 'Invalid export format', [ 'status' => 400 ] );
	}

	/**
	 * Export supplier detail as CSV
	 *
	 * @param array $detail Supplier detail data
	 * @return WP_REST_Response
	 */
	protected function export_csv( $detail ) {
		$supplier = $detail['supplier'];
		$filename = sprintf(
			'供應商結算_%s_%s_%s.csv',
			sanitize_file_name( $supplier->name ),
			$detail['period_start'],
			$detail['period_end']
		);

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// Add BOM for UTF-8 Excel compatibility
		echo "\xEF\xBB\xBF";

		$output = fopen( 'php://output', 'w' );

		// Header
		fputcsv( $output, [ '供應商結算報表' ] );
		fputcsv( $output, [ '供應商名稱', $supplier->name ] );
		fputcsv( $output, [ '聯繫人', $supplier->contact_name ] );
		fputcsv( $output, [ '電話', $supplier->phone ] );
		fputcsv( $output, [ 'Email', $supplier->email ] );
		fputcsv( $output, [ '期間', $detail['period_start'] . ' ~ ' . $detail['period_end'] ] );
		fputcsv( $output, [] ); // Empty row

		// Products table header
		fputcsv( $output, [ '產品名稱', '規格', '銷售數量', '單價', '總成本' ] );

		// Products data
		foreach ( $detail['all_products'] as $product ) {
			fputcsv( $output, [
				$product['product_name'],
				$product['variation_title'],
				$product['has_sales'] ? $product['total_qty'] : '無銷售',
				$product['cost_per_unit'] > 0 ? number_format( $product['cost_per_unit'], 2 ) : '-',
				$product['has_sales'] ? number_format( $product['total_cost'], 2 ) : '-',
			] );
		}

		fputcsv( $output, [] ); // Empty row
		fputcsv( $output, [ '總應付金額', number_format( $detail['total_payable'], 2 ) ] );

		fclose( $output );
		exit;
	}

	/**
	 * Export supplier detail as PDF
	 *
	 * @param array $detail Supplier detail data
	 * @return WP_REST_Response
	 */
	protected function export_pdf( $detail ) {
		// For now, return JSON data that frontend can convert to PDF using browser print
		// In the future, we can use a PDF library like TCPDF or mPDF
		$supplier = $detail['supplier'];
		
		// Return HTML that can be printed as PDF
		$html = $this->generate_pdf_html( $detail );

		header( 'Content-Type: text/html; charset=utf-8' );
		echo $html;
		exit;
	}

	/**
	 * Generate HTML for PDF export
	 *
	 * @param array $detail Supplier detail data
	 * @return string HTML content
	 */
	protected function generate_pdf_html( $detail ) {
		$supplier = $detail['supplier'];
		
		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
			<title>供應商結算報表</title>
			<style>
				body {
					font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
					padding: 20px;
					color: #111827;
				}
				h1 {
					font-size: 24px;
					margin-bottom: 20px;
				}
				.info-section {
					margin-bottom: 30px;
				}
				.info-row {
					margin: 8px 0;
				}
				table {
					width: 100%;
					border-collapse: collapse;
					margin: 20px 0;
				}
				th, td {
					padding: 12px;
					text-align: left;
					border-bottom: 1px solid #e5e7eb;
				}
				th {
					background: #f9fafb;
					font-weight: 600;
				}
				.total {
					font-size: 18px;
					font-weight: 600;
					margin-top: 20px;
					text-align: right;
				}
			</style>
		</head>
		<body>
			<h1>供應商結算報表</h1>
			
			<div class="info-section">
				<div class="info-row"><strong>供應商名稱：</strong><?php echo esc_html( $supplier->name ); ?></div>
				<div class="info-row"><strong>聯繫人：</strong><?php echo esc_html( $supplier->contact_name ); ?></div>
				<div class="info-row"><strong>電話：</strong><?php echo esc_html( $supplier->phone ); ?></div>
				<div class="info-row"><strong>Email：</strong><?php echo esc_html( $supplier->email ); ?></div>
				<div class="info-row"><strong>期間：</strong><?php echo esc_html( $detail['period_start'] . ' ~ ' . $detail['period_end'] ); ?></div>
			</div>

			<table>
				<thead>
					<tr>
						<th>產品名稱</th>
						<th>規格</th>
						<th>銷售數量</th>
						<th>單價</th>
						<th>總成本</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $detail['all_products'] as $product ): ?>
					<tr>
						<td><?php echo esc_html( $product['product_name'] ); ?></td>
						<td><?php echo esc_html( $product['variation_title'] ); ?></td>
						<td><?php echo $product['has_sales'] ? esc_html( $product['total_qty'] ) : '無銷售'; ?></td>
						<td><?php echo $product['cost_per_unit'] > 0 ? 'NT$ ' . number_format( $product['cost_per_unit'], 2 ) : '-'; ?></td>
						<td><?php echo $product['has_sales'] ? 'NT$ ' . number_format( $product['total_cost'], 2 ) : '-'; ?></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<div class="total">
				<strong>總應付金額：NT$ <?php echo number_format( $detail['total_payable'], 2 ); ?></strong>
			</div>

			<script>
				window.onload = function() {
					window.print();
				};
			</script>
		</body>
		</html>
		<?php
		return ob_get_clean();
	}

	/**
	 * Recalculate order costs for existing orders
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function recalculate_order_costs( $request ) {
		$user_id = get_current_user_id();
		$service = new SuppliersService();

		$stats = $service->recalculateOrderCosts( $user_id );

		return rest_ensure_response( [
			'success' => true,
			'data' => $stats,
			'message' => sprintf(
				'已處理 %d 個訂單，共 %d 個訂單項目，其中 %d 個項目已更新供應商資訊',
				$stats['processed_orders'],
				$stats['processed_items'],
				$stats['updated_items']
			),
		] );
	}
}
