<?php

namespace BuyGo\Core\Services;

class SyncManager {

    public function __construct() {
        // According to FluentCRM docs, use fluent_crm/after_init to register triggers
        // Docs: 097_Custom_Automation_Trigger.md
        add_action('fluent_crm/after_init', [$this, 'init_fluentcrm_integrations']);
        
        // Bridge BuyGo Events to FluentCRM Triggers
        add_action('buygo_seller_approved', [$this, 'trigger_seller_approved_funnel'], 10, 2);
        add_action('buygo_helper_assigned', [$this, 'trigger_helper_assigned_funnel'], 10, 2);
        add_action('buygo_line_binding_completed', [$this, 'trigger_line_bound_funnel'], 10, 2);

        // Listen to WP Role changes -> trigger standard hook for automation
        add_action('set_user_role', [$this, 'on_wp_role_change'], 10, 3);
    }

    public function init_fluentcrm_integrations() {
        static $loaded = false;
        if ($loaded) return;

        $loaded = true;
        
        try {
            new \BuyGo\Core\Services\Integrations\FluentCRM\SellerApprovedTrigger();
            new \BuyGo\Core\Services\Integrations\FluentCRM\HelperAssignedTrigger();
            new \BuyGo\Core\Services\Integrations\FluentCRM\LineBoundTrigger();
        } catch (\Throwable $e) {
            error_log('[BuyGo] FluentCRM Trigger Registration Failed: ' . $e->getMessage());
        }
    }

    /**
     * Bridge: BuyGo Seller Approved -> FluentCRM Funnel
     */
    public function trigger_seller_approved_funnel($user_id, $context = []) {
        if (!defined('FLUENTCRM')) return;
        
        $trigger_name = 'buygo_seller_approved'; 
        // Use do_action to fire the hook. The Trigger class will handle the logic manually.
        do_action('fluentcrm_funnel_start_' . $trigger_name, [$user_id], []);
    }

    /**
     * Bridge: BuyGo Helper Assigned -> FluentCRM Funnel
     */
    public function trigger_helper_assigned_funnel($seller_id, $helper_id) {
        if (!defined('FLUENTCRM')) return;
        
        $trigger_name = 'buygo_helper_assigned';
        // Pass normalized args: helper_id first as it's the main contact
        do_action('fluentcrm_funnel_start_' . $trigger_name, [$helper_id, $seller_id], []);
    }

    /**
     * Bridge: BuyGo LINE Bound -> FluentCRM Funnel
     */
    public function trigger_line_bound_funnel($user_id, $line_profile) {
        if (!defined('FLUENTCRM')) return;
        
        $trigger_name = 'buygo_line_binding_completed';
        do_action('fluentcrm_funnel_start_' . $trigger_name, [$user_id, $line_profile], []);
    }
    
    /**
     * Handle WP native role change event
     */
    public function on_wp_role_change($user_id, $role, $old_roles) {
        // Implementation for role change if needed
    }
}
