<?php

namespace App\Console\Commands;

use App\Services\ReminderSchedulerService;
use Illuminate\Console\Command;

/**
 * Command Artisan untuk memproses jadwal pengingat (reminder) jatuh tempo dan pengingat berkala.
 */
class ProsesReminderBerkalaCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reminder:proses';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Memproses jadwal pengingat berkala kontrak/pembayaran dan mengirim notifikasi + email untuk jadwal yang telah jatuh tempo';

    /**
     * Execute the console command.
     */
    public function handle(ReminderSchedulerService $scheduler): int
    {
        $summary = $scheduler->processDueReminders();

        $this->info(sprintf(
            'Reminder diproses: %d | Notifikasi dikirim: %d | Jadwal selesai/dinonaktifkan: %d',
            $summary['processed'],
            $summary['dispatched_notifications'],
            $summary['deactivated']
        ));

        return self::SUCCESS;
    }
}
