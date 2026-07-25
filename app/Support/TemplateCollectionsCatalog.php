<?php

namespace App\Support;

use App\Enums\ActionCodeClassification;
use App\Enums\TemplateChannel;
use App\Models\Entity;
use App\Models\EntityActionCode;
use App\Models\EntityStatus;
use App\Models\EntityTemplate;

class TemplateCollectionsCatalog
{
    public const ENTITY_CODE = 'TMP-COLLECTIONS';

    public const ENTITY_NAME = 'Template - Collections';

    /**
     * @return list<array{code: string, name: string, color: string, text_color: string, sort_order: int, is_active: bool}>
     */
    public static function statuses(): array
    {
        return [
            ['code' => 'CP', 'name' => 'Common Pool', 'color' => '#d1d1d1', 'text_color' => '#000000', 'sort_order' => 10, 'is_active' => true],
            ['code' => 'NEW', 'name' => 'Newly Endorsed', 'color' => '#d1d1d1', 'text_color' => '#000000', 'sort_order' => 20, 'is_active' => true],
            ['code' => 'NEGO', 'name' => 'Negotiation', 'color' => '#d1d1d1', 'text_color' => '#000000', 'sort_order' => 30, 'is_active' => true],
            ['code' => 'PTP', 'name' => 'Promise-to-pay', 'color' => '#0080ff', 'text_color' => '#ffffff', 'sort_order' => 40, 'is_active' => true],
            ['code' => 'BPTP', 'name' => 'Broken Promise-to-pay', 'color' => '#991b1b', 'text_color' => '#ffffff', 'sort_order' => 50, 'is_active' => true],
            ['code' => 'PAYING', 'name' => 'Paying', 'color' => '#166534', 'text_color' => '#ffffff', 'sort_order' => 60, 'is_active' => true],
            ['code' => 'FULL', 'name' => 'Fully Paid', 'color' => '#166534', 'text_color' => '#ffffff', 'sort_order' => 70, 'is_active' => true],
            ['code' => 'PULLOUT', 'name' => 'Pullout', 'color' => '#166534', 'text_color' => '#ffffff', 'sort_order' => 80, 'is_active' => true],
        ];
    }

    /**
     * @return list<string>
     */
    public static function campaignNames(): array
    {
        return array_column(self::statuses(), 'name');
    }

    /**
     * @return list<array{name: string, code: string, classification: ActionCodeClassification, sort_order: int, is_active: bool}>
     */
    public static function actionCodes(): array
    {
        $positive = [
            'CONFIRMATION OF PAYMENT',
            'CONFIRMATION OF PROMISE TO PAY',
            'FULLY PAID',
            'PROMISE TO PAY',
            'RPC - PROMISED TO PAY',
            'FOREBEARANCE',
        ];

        $negative = [
            'CLIENT DO NOT KNOW (W/ ATTACHED PROOF)',
            'CLIENT DO NOT KNOW (UNCONFIRMED)',
            'REFUSED TO PAY',
            'RPC - REFUSED TO PAY',
            'ATP - REFUSED TO PAY',
            'SCH - RPC - Insolvency/Bankruptcy',
            'SCH - TP - DECEASED',
            'SCH - TP - Wrong Number',
            'DISTRESSED',
            'UNLOCATED NUMBER 404',
            'OUT OF COVERAGE',
        ];

        $names = [
            'CALLBACK LATER',
            'COMPLICATED SITUATION',
            'CONFIRMATION OF PAYMENT',
            'CONFIRMATION OF PROMISE TO PAY',
            'CONNECTION WAS LOST',
            'CLIENT DO NOT KNOW (W/ ATTACHED PROOF)',
            'CLIENT DO NOT KNOW (UNCONFIRMED)',
            'EMAIL_PAYMENT REMINDER / PROMO',
            'EMAIL_RESPONSE',
            'FULLY PAID',
            'HANG UP',
            'INFO NOT TRANSMITTED',
            'INFO TRANSMITTED',
            'KEEP ON RINGING',
            'DEMANDED OF PAYMENT',
            'PROMISE TO PAY',
            'REFUSED TO PAY',
            'SILENCE',
            'VOICE MACHINE',
            'SMS',
            'EMAIL',
            'RPC - DEMAND FOR PAYMENT',
            'RPC - PROMISED TO PAY',
            'RPC - REFUSED TO PAY',
            'RPC - REQUEST FOR CALLBACK',
            'RPC - GHOST CALL',
            'RPC - FOR INVESTIGATION',
            'ATP - REFUSED TO PAY',
            'TP - REQUEST FOR CALLBACK',
            'SCH - RPC - Insolvency/Bankruptcy',
            'SCH - TP - DECEASED',
            'SCH - TP - Wrong Number',
            'SKIP',
            'TP - CUSTOMER NOT THERE AT TIME OF CALL',
            'TP - GHOST CALL',
            'BUSY',
            'DROPPED BY CUSTOMER',
            'NO ANSWER FROM THE USER 480',
            'USER BUSY 486',
            'CALL ENDED',
            'FAILED PID PROCESS',
            'NO CIRCUIT AVAILABLE 503',
            'UNKNOWN',
            'UNLOCATED NUMBER 404',
            'DISTRESSED',
            'FOREBEARANCE',
            'OUT OF COVERAGE',
        ];

        $positiveLookup = array_fill_keys($positive, true);
        $negativeLookup = array_fill_keys($negative, true);
        $rows = [];
        $order = 10;

        foreach ($names as $name) {
            $classification = ActionCodeClassification::Neutral;
            if (isset($positiveLookup[$name])) {
                $classification = ActionCodeClassification::Positive;
            } elseif (isset($negativeLookup[$name])) {
                $classification = ActionCodeClassification::Negative;
            }

            $rows[] = [
                'name' => $name,
                'code' => self::codeFromName($name),
                'classification' => $classification,
                'sort_order' => $order,
                'is_active' => true,
            ];
            $order += 10;
        }

        return $rows;
    }

    public static function codeFromName(string $name): string
    {
        $code = strtoupper($name);
        $code = preg_replace('/[^A-Z0-9]+/', '_', $code) ?? '';
        $code = trim($code, '_');

        return $code !== '' ? $code : 'ACTION';
    }

    /**
     * @return list<array{slug: string, types: list<string>, body: string, is_active: bool}>
     */
    public static function templates(): array
    {
        return [
            [
                'slug' => 'account-transfer-notice',
                'types' => [
                    TemplateChannel::Chat->value,
                    TemplateChannel::Email->value,
                    TemplateChannel::Sms->value,
                ],
                'body' => 'Hello {account_name}, your account is now transferred to us. Expect a call anytime soon.',
                'is_active' => true,
            ],
        ];
    }

    public static function applyToEntity(Entity $entity): void
    {
        foreach (self::statuses() as $row) {
            EntityStatus::query()->updateOrCreate(
                [
                    'entity_id' => $entity->id,
                    'name' => $row['name'],
                ],
                [
                    'code' => $row['code'],
                    'color' => $row['color'],
                    'text_color' => $row['text_color'],
                    'sort_order' => $row['sort_order'],
                    'is_active' => $row['is_active'],
                ],
            );
        }

        foreach (self::actionCodes() as $row) {
            EntityActionCode::query()->updateOrCreate(
                [
                    'entity_id' => $entity->id,
                    'name' => $row['name'],
                ],
                [
                    'code' => $row['code'],
                    'classification' => $row['classification'],
                    'sort_order' => $row['sort_order'],
                    'is_active' => $row['is_active'],
                ],
            );
        }

        foreach (self::templates() as $row) {
            EntityTemplate::query()->updateOrCreate(
                [
                    'entity_id' => $entity->id,
                    'slug' => $row['slug'],
                ],
                [
                    'types' => $row['types'],
                    'body' => $row['body'],
                    'is_active' => $row['is_active'],
                ],
            );
        }
    }

    public static function copyCatalogsTo(Entity $source, Entity $target): array
    {
        $existingStatusNames = EntityStatus::query()
            ->where('entity_id', $target->id)
            ->pluck('name')
            ->all();
        $statusLookup = array_fill_keys($existingStatusNames, true);

        $statusesCopied = 0;
        $statusesSkipped = 0;
        foreach (
            EntityStatus::query()
                ->where('entity_id', $source->id)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get() as $sourceStatus
        ) {
            if (isset($statusLookup[$sourceStatus->name])) {
                $statusesSkipped++;

                continue;
            }

            EntityStatus::query()->create([
                'entity_id' => $target->id,
                'name' => $sourceStatus->name,
                'code' => $sourceStatus->code,
                'color' => $sourceStatus->color,
                'text_color' => $sourceStatus->text_color ?: '#ffffff',
                'sort_order' => $sourceStatus->sort_order,
                'is_active' => $sourceStatus->is_active,
            ]);
            $statusesCopied++;
        }

        $existingActionNames = EntityActionCode::query()
            ->where('entity_id', $target->id)
            ->pluck('name')
            ->all();
        $actionLookup = array_fill_keys($existingActionNames, true);

        $actionsCopied = 0;
        $actionsSkipped = 0;
        foreach (
            EntityActionCode::query()
                ->where('entity_id', $source->id)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get() as $sourceAction
        ) {
            if (isset($actionLookup[$sourceAction->name])) {
                $actionsSkipped++;

                continue;
            }

            EntityActionCode::query()->create([
                'entity_id' => $target->id,
                'name' => $sourceAction->name,
                'code' => $sourceAction->code,
                'classification' => $sourceAction->classification ?? ActionCodeClassification::Neutral,
                'sort_order' => $sourceAction->sort_order,
                'is_active' => $sourceAction->is_active,
            ]);
            $actionsCopied++;
        }

        $existingTemplateSlugs = EntityTemplate::query()
            ->where('entity_id', $target->id)
            ->pluck('slug')
            ->all();
        $templateLookup = array_fill_keys($existingTemplateSlugs, true);

        $templatesCopied = 0;
        $templatesSkipped = 0;
        foreach (
            EntityTemplate::query()
                ->where('entity_id', $source->id)
                ->orderBy('slug')
                ->get() as $sourceTemplate
        ) {
            if (isset($templateLookup[$sourceTemplate->slug])) {
                $templatesSkipped++;

                continue;
            }

            EntityTemplate::query()->create([
                'entity_id' => $target->id,
                'types' => $sourceTemplate->types ?? [],
                'slug' => $sourceTemplate->slug,
                'body' => $sourceTemplate->body,
                'is_active' => $sourceTemplate->is_active,
            ]);
            $templatesCopied++;
        }

        return [
            'statuses_copied' => $statusesCopied,
            'statuses_skipped' => $statusesSkipped,
            'actions_copied' => $actionsCopied,
            'actions_skipped' => $actionsSkipped,
            'templates_copied' => $templatesCopied,
            'templates_skipped' => $templatesSkipped,
        ];
    }
}
