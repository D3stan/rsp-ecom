<?php

namespace App\Console\Commands;

use App\Services\EmailService;
use Illuminate\Console\Command;

class TestEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:test {email : The email address to send test email to}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test email configuration by sending a test email';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        
        $this->info('Testing email configuration...');
        $this->info('Sending test email to: ' . $email);
        
        $emailService = app(EmailService::class);
        
        if ($emailService->testEmailConfiguration($email)) {
            $this->info('✅ Test email sent successfully!');
            $this->info('Check your email inbox (and spam folder) for the test message.');
            return Command::SUCCESS;
        } else {
            $this->error('❌ Failed to send test email.');
            $this->error('Check your email configuration and logs for more details.');
            return Command::FAILURE;
        }
    }
}