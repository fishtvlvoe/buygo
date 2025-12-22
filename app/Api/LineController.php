<?php

namespace BuyGo\Core\Api;

use BuyGo\Core\App;
use BuyGo\Core\Services\LineService;
use WP_REST_Request;
use WP_REST_Response;

class LineController extends BaseController {

    private $line_service;

    public function __construct() {
        $this->line_service = App::instance()->make(LineService::class);
    }

    public function register_routes() {
        register_rest_route($this->namespace, '/line/bind/generate', [
            'methods' => 'POST',
            'callback' => [$this, 'generate_code'],
            'permission_callback' => function() {
                return is_user_logged_in();
            }
        ]);

        register_rest_route($this->namespace, '/line/bind/status', [
            'methods' => 'GET',
            'callback' => [$this, 'get_status'],
            'permission_callback' => function() {
                return is_user_logged_in();
            }
        ]);
    }

    public function generate_code(WP_REST_Request $request) {
        $user_id = get_current_user_id();
        $code = $this->line_service->generate_binding_code($user_id);

        if (is_wp_error($code)) {
            return new WP_REST_Response(['message' => $code->get_error_message()], 500);
        }

        return new WP_REST_Response([
            'code' => $code,
            'expires_in' => 600 // 10 minutes
        ], 200);
    }

    public function get_status(WP_REST_Request $request) {
        $user_id = get_current_user_id();
        $line_uid = $this->line_service->get_line_uid($user_id);

        return new WP_REST_Response([
            'is_bound' => !empty($line_uid),
            'line_uid' => $line_uid // Maybe hide part of it for privacy?
        ], 200);
    }
}
