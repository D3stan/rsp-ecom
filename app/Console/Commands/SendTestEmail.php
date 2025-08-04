<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendTestEmail extends Command
{
    protected $signature = 'mail:send-test {to}';
    protected $description = 'Send a test email to a specific address';

    public function handle()
    {
        $to = $this->argument('to');

        Mail::raw('Attenzione, Catanzaro sta per essere gemellata con Capannastruzzo, EMERGENZA.', function ($message) use ($to) {
            $message->to($to)
                    ->subject('EMERGENZA CATANZARO');
        });

        $this->info("Test email sent to {$to}");
    }
}
