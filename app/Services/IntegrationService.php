<?php

namespace BuyGo\Core\Services;

use BuyGo\Core\App;

class IntegrationService {

    public function __construct() {
        // Listen to Core Events
        add_action('buygo_line_binding_completed', [$this, 'sync_line_to_crm'], 10, 2);
    }

    /**
     * Sync LINE UID to FluentCRM when binding is completed.
     * 
     * @param int $user_id
     * @param string $line_uid
     */
    public function sync_line_to_crm($user_id, $line_uid) {
        if (!function_exists('FluentCrmApi')) {
            return;
        }

        $user = get_userdata($user_id);
        if (!$user) return;

        $contact_data = [
            'email' => $user->user_email,
            'custom_values' => [
                'line_uid' => $line_uid // Assuming 'line_uid' custom field exists in CRM
            ]
        ];

        // Update or Create Contact
        $contact = FluentCrmApi('contacts')->createOrUpdate($contact_data);
        
        // Add tag if configured
        if ($contact) {
            /** @var SettingsService */
            $settings = App::instance()->make(SettingsService::class);
            $tag_id = $settings->get('line_binding_fluentcrm_tag_id');
            
            if (!empty($tag_id)) {
                FluentCrmApi('contacts')->attachTags([$tag_id], $contact->id);
            }
        }
    }
}
