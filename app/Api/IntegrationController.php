<?php

namespace BuyGo\Core\Api;

use BuyGo\Core\App;
use WP_REST_Request;
use WP_REST_Response;

class IntegrationController extends BaseController {

    public function register_routes() {
        register_rest_route($this->namespace, '/fluentcrm/tags', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_tags'],
                'permission_callback' => [$this, 'check_permission'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_tag'],
                'permission_callback' => [$this, 'check_permission'],
            ]
        ]);
        
        register_rest_route($this->namespace, '/fluentcrm/lists', [
            'methods' => 'GET',
            'callback' => [$this, 'get_lists'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
    }

    /**
     * Get all FluentCRM tags.
     */
    public function get_tags() {
        if (!function_exists('FluentCrmApi')) {
            return new WP_REST_Response([], 200);
        }

        try {
            // FluentCRM API structure for tags: FluentCrmApi('tags')->all() might work depending on version,
            // or we use the model directly to be safe or standard API.
            // FluentCrmApi('tags') returns a Query Builder usually.
            
            $tags = FluentCrmApi('tags')->all(); // get() returns collection/array
            
            // Format for frontend
            $formatted = [];
            foreach ($tags as $tag) {
                $formatted[] = [
                    'id' => $tag->id,
                    'title' => $tag->title,
                    'slug' => $tag->slug
                ];
            }

            return new WP_REST_Response($formatted, 200);

        } catch (\Exception $e) {
            error_log('FluentCRM Tag Error: ' . $e->getMessage());
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }



    public function get_lists() {
        if (!function_exists('FluentCrmApi')) {
            return new WP_REST_Response([], 200);
        }

        try {
            $lists = FluentCrmApi('lists')->all();
            $formatted = [];
            foreach ($lists as $list) {
                $formatted[] = [
                    'id' => $list->id,
                    'title' => $list->title,
                    'slug' => $list->slug
                ];
            }
            return new WP_REST_Response($formatted, 200);

        } catch (\Exception $e) {
            error_log('FluentCRM List Error: ' . $e->getMessage());
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Create a new FluentCRM tag.
     */
    public function create_tag(WP_REST_Request $request) {
        if (!function_exists('FluentCrmApi')) {
            return new WP_REST_Response(['message' => 'FluentCRM is not active'], 400);
        }

        if (!class_exists('\FluentCrm\App\Models\Tag')) {
            return new WP_REST_Response(['message' => 'FluentCRM Tag model not found'], 500);
        }

        $params = $request->get_json_params();
        $title = sanitize_text_field($params['title'] ?? '');

        if (empty($title)) {
            return new WP_REST_Response(['message' => 'Tag title is required'], 400);
        }

        try {
            $data = [
                'title' => $title,
                'slug'  => sanitize_title($title)
            ];

            // Use the Model directly to bypass API wrapper limitations
            $tag = \FluentCrm\App\Models\Tag::create($data);

            if (!$tag) {
                return new WP_REST_Response(['message' => 'Failed to create tag (Database Error)'], 500);
            }

            return new WP_REST_Response([
                'id' => $tag->id,
                'title' => $tag->title
            ], 201);

        } catch (\Exception $e) {
            error_log('FluentCRM Exception: ' . $e->getMessage());
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }
}
