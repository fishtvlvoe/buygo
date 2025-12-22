<?php

namespace BuyGo\Core\Services\Integrations\FluentCRM;

use FluentCrm\App\Services\Funnel\BaseTrigger;
use FluentCrm\App\Services\Funnel\FunnelProcessor;
use FluentCrm\App\Models\Funnel;

class LineBoundTrigger extends BaseTrigger
{
    public function __construct()
    {
        $this->triggerName = 'buygo_line_binding_completed';
        $this->priority = 10;
        $this->actionArgNum = 2; // Arguments: $user_id, $line_uid
        parent::__construct();
    }

    public function getTrigger()
    {
        return [
            'category'    => __('WordPress Triggers', 'fluent-crm'),
            'label'       => 'BuyGo: LINE 綁定完成', // LINE Bound
            'title'       => 'BuyGo: LINE 綁定完成',
            'description' => '當使用者成功綁定他們的 LINE 帳號時，將啟動此漏斗流程。',
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
                     'check_label' => 'If checked, this automation will run only once.',
                     'default'     => 'yes'
                 ]
             ]
         ];
    }

    public function handle($funnel, $originalArgs)
    {
        $userId = 0;

        // Case 1: Manual Trigger via do_action
        // $funnel is the args array: [$user_id, $line_uid]
        if (!is_object($funnel)) {
            if (is_array($funnel) && isset($funnel[0])) {
                $userId = $funnel[0];
            }

            if (!$userId) return;

            // Find Funnels
            $funnels = Funnel::where('trigger_name', $this->triggerName)
                ->where('status', 'published')
                ->get();

            if ($funnels->isEmpty()) return;

            foreach ($funnels as $realFunnel) {
                // Recursively call handle with real Funnel Object and args
                $this->handle($realFunnel, is_array($funnel) ? $funnel : [$userId]);
            }
            return;
        }

        // Case 2: Standard Execution
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

        (new FunnelProcessor())->startFunnelSequence($funnel, $subscriberData, [
            'source_trigger_name' => $this->triggerName,
            'source_ref_id'       => $userId
        ]);
    }
}
