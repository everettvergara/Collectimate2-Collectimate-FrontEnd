<?php

namespace App\Console\Commands;

use App\Services\Sms\SmsDispatchService;
use Illuminate\Console\Command;

class SmsDispatchCommand extends Command
{
    protected $signature = 'sms:dispatch';

    protected $description = 'Dispatch one tick of the Laravel-owned SMS queue to available devices';

    public function handle(SmsDispatchService $dispatch): int
    {
        $result = $dispatch->tick();

        $this->info($result['message']);
        $this->line('Dispatched: '.$result['dispatched']);
        $this->line('Available devices: '.$result['available_devices']);
        $this->line('Pending: '.$result['pending']);

        if ($result['alert']) {
            $this->warn('Alert: '.$result['alert']);
        }

        return self::SUCCESS;
    }
}
