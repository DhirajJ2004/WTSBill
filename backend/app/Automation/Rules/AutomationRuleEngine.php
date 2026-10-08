<?php

namespace App\Automation\Rules;

use App\Models\AutomationEvent;
use App\Models\AutomationRule;
use App\Automation\Actions\ActionExecutor;

class AutomationRuleEngine
{
    /**
     * Evaluate business event against all active rules and execute actions.
     */
    public static function evaluateEvent(AutomationEvent $event): array
    {
        $companyId = $event->company_id;
        $eventType = $event->event_type;

        $rules = AutomationRule::where('company_id', $companyId)
            ->where('is_active', true)
            ->where(function ($query) use ($eventType) {
                $query->where('event_type', $eventType)
                      ->orWhere('event_type', '*');
            })
            ->orderBy('priority', 'desc')
            ->get();

        $executionResults = [];

        foreach ($rules as $rule) {
            $conditions = $rule->conditions_json ?: [];

            $isMatch = RuleConditionEvaluator::evaluate(
                conditions: $conditions,
                entityType: $event->entity_type,
                entityId: $event->entity_id,
                metadata: $event->metadata_json ?: [],
                companyId: $companyId
            );

            if ($isMatch) {
                $actionsExecuted = ActionExecutor::executeActions($rule, $event);
                $executionResults[] = [
                    'rule_id' => $rule->id,
                    'rule_name' => $rule->name,
                    'matched' => true,
                    'actions' => $actionsExecuted,
                ];
            } else {
                $executionResults[] = [
                    'rule_id' => $rule->id,
                    'rule_name' => $rule->name,
                    'matched' => false,
                ];
            }
        }

        return $executionResults;
    }
}
