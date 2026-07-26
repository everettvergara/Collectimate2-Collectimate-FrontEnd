<?php

namespace App\Console\Commands;

use App\Services\Sms\SmsDispatchService;
use Illuminate\Console\Command;

class SmsWorkCommand extends Command
{
    protected $signature = 'sms:work {--sleep=2 : Seconds between dispatch ticks}';

    protected $description = 'Continuously dispatch the Laravel-owned SMS queue';

    public function handle(SmsDispatchService $dispatch): int
    {
        $sleep = max(1, (int) $this->option('sleep'));
        $this->info("SMS worker started (sleep={$sleep}s). Ctrl+C to stop.");

        while (true) {
            $result = $dispatch->tick();
            if ($result['dispatched'] > 0 || $result['alert']) {
                $this->line(now()->toDateTimeString().' — '.$result['message']);
            }
            sleep($sleep);
        }
    }
}
