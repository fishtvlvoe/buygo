<?php

namespace BuyGo\Core\Api;

use BuyGo\Core\App;
use BuyGo\Core\Services\SellerApplicationService;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class SellerApplicationController extends BaseController {

    private $service;

    public function __construct() {
        $this->service = App::instance()->make(SellerApplicationService::class);
        // Fallback if not bound yet (during dev)
        if (!$this->service) {
            $this->service = new SellerApplicationService(); // Or better handle dependency injection
        }
    }

    public function register_routes() {
        // 提交申請 (Public/Logged-in User)
        register_rest_route($this->namespace, '/applications', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'submit_application'],
                'permission_callback' => [$this, 'can_submit_application'],
            ],
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_applications'], // Admin List
                'permission_callback' => [$this, 'check_permission'], // BaseController default (manage_options)
            ]
        ]);

        // 查詢自己狀態
        register_rest_route($this->namespace, '/applications/me', [
            'methods' => 'GET',
            'callback' => [$this, 'get_my_application'],
            'permission_callback' => function() {
                return is_user_logged_in();
            }
        ]);

        // 審核操作
        register_rest_route($this->namespace, '/applications/(?P<id>\d+)/approve', [
            'methods' => 'POST',
            'callback' => [$this, 'approve_application'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route($this->namespace, '/applications/(?P<id>\d+)/reject', [
            'methods' => 'POST',
            'callback' => [$this, 'reject_application'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
    }

    public function can_submit_application() {
        return is_user_logged_in();
    }

    public function submit_application(WP_REST_Request $request) {
        $user_id = get_current_user_id();
        $params = $request->get_params();

        // 簡單驗證
        if (empty($params['real_name']) || empty($params['phone'])) {
            return new WP_Error('missing_params', '請填寫所有必填欄位 (姓名, 電話)', ['status' => 400]);
        }
        
        // 如果沒有提供 line_id，嘗試從使用者的 LINE 綁定中取得
        if (empty($params['line_id'])) {
            $line_service = \BuyGo\Core\App::instance()->make(\BuyGo\Core\Services\LineService::class);
            $line_id = $line_service->get_line_uid($user_id);
            if ($line_id) {
                $params['line_id'] = $line_id;
            } else {
                // LINE ID 不是必填，可以為空
                $params['line_id'] = '';
            }
        }

        $result = $this->service->submit($user_id, $params);

        if (is_wp_error($result)) {
            return $result;
        }

        return new WP_REST_Response([
            'success' => true,
            'id' => $result,
            'message' => '申請已提交，請等待審核。'
        ], 201);
    }

    public function get_applications(WP_REST_Request $request) {
        $page = $request->get_param('page') ?: 1;
        $status = $request->get_param('status');

        $result = $this->service->get_applications([
            'page' => $page,
            'status' => $status
        ]);

        return new WP_REST_Response($result, 200);
    }

    public function get_my_application(WP_REST_Request $request) {
        $user_id = get_current_user_id();
        $application = $this->service->get_user_application($user_id);

        if (!$application) {
            return new WP_REST_Response(['status' => 'none'], 200);
        }

        return new WP_REST_Response($application, 200);
    }

    public function approve_application(WP_REST_Request $request) {
        $id = $request->get_param('id');
        $note = $request->get_param('note');
        $admin_id = get_current_user_id();

        $result = $this->service->approve($id, $admin_id, $note);

        if (is_wp_error($result)) {
            return $result;
        }

        return new WP_REST_Response(['success' => true, 'message' => '申請已核准'], 200);
    }

    public function reject_application(WP_REST_Request $request) {
        $id = $request->get_param('id');
        $note = $request->get_param('note');
        $admin_id = get_current_user_id();

        $result = $this->service->reject($id, $admin_id, $note);

        if (is_wp_error($result)) {
            return $result;
        }

        return new WP_REST_Response(['success' => true, 'message' => '申請已拒絕'], 200);
    }
}
