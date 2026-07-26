<?php

namespace App\Console\Commands;

use App\Enums\SmsReceivedAssociationStatus;
use App\Models\SmsReceivedMessage;
use Illuminate\Console\Command;

class SmsReceivedDedupeUnmatchedCommand extends Command
{
    protected $signature = 'sms:received-dedupe-unmatched';

    protected $description = 'Mark duplicate unmatched received SMS (same sender+message) as ignored, keeping the oldest';

    public function handle(): int
    {
        $rows = SmsReceivedMessage::query()
            ->where('association_status', SmsReceivedAssociationStatus::Unmatched)
            ->whereNull('account_id')
            ->orderBy('id')
            ->get();

        $seen = [];
        $ignored = 0;

        foreach ($rows as $row) {
            $key = $row->sender.'|'.$row->message;
            if (isset($seen[$key])) {
                $row->forceFill([
                    'association_status' => SmsReceivedAssociationStatus::Ignored,
                ])->save();
                $ignored++;
            } else {
                $seen[$key] = $row->id;
            }
        }

        $this->info("Ignored {$ignored} duplicate unmatched message(s).");

        return self::SUCCESS;
    }
}
