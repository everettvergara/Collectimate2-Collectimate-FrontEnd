<?php

namespace App\Support;

use App\Models\Account;
use App\Models\AgentProfile;

class AccountTemplateTokens
{
    /**
     * @return array<string, string>
     */
    public static function build(Account $account, string $assignedAgent = ''): array
    {
        $fromCustom = [];
        $custom = $account->custom_fields;
        if (is_array($custom)) {
            foreach ($custom as $key => $value) {
                if (is_string($key) && preg_match('/^[a-zA-Z0-9_]+$/', $key)) {
                    $fromCustom[$key] = self::tokenValue($value);
                }
            }
        }

        return array_merge($fromCustom, [
            'account_name' => self::tokenValue($account->account_name),
            'account_number' => self::tokenValue($account->account_number),
            'entity_name' => self::tokenValue($account->campaign?->entity?->name),
            'campaign_name' => self::tokenValue($account->campaign?->name),
            'date_acquired' => self::tokenValue($account->date_acquired),
            'assigned_agent' => self::tokenValue($assignedAgent),
            'notes' => self::tokenValue($account->notes),
            'product' => self::tokenValue($account->product),
            'balance' => self::tokenValue($account->balance),
            'due_date' => self::tokenValue($account->due_date),
            'external_reference' => self::tokenValue($account->external_reference),
            'entity_status' => self::tokenValue($account->entityStatus?->name),
            'entity_status_code' => self::tokenValue($account->entityStatus?->code),
            'entity_action_code' => self::tokenValue($account->entityActionCode?->name),
            'entity_action_code_code' => self::tokenValue($account->entityActionCode?->code),
            'last_reference_amount' => self::tokenValue($account->last_reference_amount),
            'last_reference_date' => self::tokenValue($account->last_reference_date),
            'last_reference_time' => self::tokenValue($account->last_reference_time),
            'last_reference_text' => self::tokenValue($account->last_reference_text),
        ]);
    }

    /**
     * @param  array<string, string>  $tokens
     */
    public static function resolve(?string $body, array $tokens): string
    {
        if ($body === null || $body === '') {
            return '';
        }

        return (string) preg_replace_callback(
            '/\{([a-zA-Z0-9_]+)\}/',
            static function (array $matches) use ($tokens): string {
                $name = $matches[1];

                return array_key_exists($name, $tokens) ? $tokens[$name] : $matches[0];
            },
            $body,
        );
    }

    public static function agentLabel(?AgentProfile $profile): string
    {
        if (! $profile) {
            return '';
        }

        return $profile->display_name
            ?: trim("{$profile->first_name} {$profile->last_name}")
            ?: '';
    }

    private static function tokenValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return (string) $value;
    }
}
