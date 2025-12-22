<?php

namespace BuyGo\Core\Services\Integrations\FluentCRM;

use FluentCrm\App\Services\Funnel\BaseTrigger;
use FluentCrm\App\Services\Funnel\FunnelProcessor;
use FluentCrm\App\Models\Funnel;

class SellerApprovedTrigger extends BaseTrigger
{
    public function __construct()
    {
        $this->triggerName = 'buygo_seller_approved';
        $this->priority = 10;
        $this->actionArgNum = 2; // Arguments passed to the hook: $user_id, $context
        parent::__construct();
    }

    public function getTrigger()
    {
        return [
            'category'    => __('WordPress Triggers', 'fluent-crm'),
            'label'       => 'BuyGo: 賣家審核通過', // Seller Approved
            'title'       => 'BuyGo: 賣家審核通過',
            'description' => '當 BuyGo 賣家申請被核准時，將啟動此漏斗流程。',
            'icon'        => 'fc-icon-wp',
        ];
    }

    public function getFunnelSettingsDefaults()
    {
        return [
            'is_run_once' => 'yes'
        ];
    }

    public function getFunnelConditionDefaults($funnel)
    {
        return [];
    }

    public function getConditionFields($funnel)
    {
        return [];
    }

    public function getSettingsFields($funnel)
    {
        return [
            'title'     => 'Trigger Settings',
            'sub_title' => 'Configure Trigger Settings',
            'fields'    => [
                'is_run_once' => [
                    'type'        => 'yes_no_check',
                    'label'       => 'Run Only Once Per User',
                    'check_label' => 'If checked, this automation will run only once for a contact.',
                    'default'     => 'yes'
                ]
            ]
        ];
    }

    public function handle($funnel, $originalArgs)
    {
        $userId = 0;

        // Case 1: Manual Trigger via do_action from SyncManager
        // In this case, $funnel holds the 1st argument from do_action ([$user_id])
        if (!is_object($funnel)) {
            if (is_array($funnel) && isset($funnel[0])) {
                $userId = $funnel[0];
            } elseif (is_numeric($funnel)) {
                $userId = $funnel;
            }

            if (!$userId) return;

            // Manually find funnels for this trigger
            $funnels = Funnel::where('trigger_name', $this->triggerName)
                ->where('status', 'published')
                ->get();

            if ($funnels->isEmpty()) return;

            foreach ($funnels as $realFunnel) {
                // Recursively call handle with the real Funnel Object
                // We recreate the args array as if it came from the hook
                $this->handle($realFunnel, [$userId]);
            }
            return;
        }

        // Case 2: Standard Execution (Called recursively or via FunnelHelper if working)
        if (isset($originalArgs[0])) {
             $userId = $originalArgs[0];
        }

        if (!$userId) return;

        $user = get_userdata($userId);
        if (!$user) return;

        // Prepare subscriber data
        $subscriberData = [
            'first_name' => $user->first_name,
            'last_name'  => $user->last_name,
            'email'      => $user->user_email,
            'user_id'    => $user->ID,
            'status'     => 'subscribed',
        ];

        // Start Funnel
        (new FunnelProcessor())->startFunnelSequence($funnel, $subscriberData, [
            'source_trigger_name' => $this->triggerName,
            'source_ref_id'       => $userId
        ]);
    }
}
