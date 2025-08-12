<?php

namespace App\Console\Commands;

use App\Models\PendingUserVerification;
use App\Notifications\PendingUserEmailVerification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

class SendTestVerificationEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'auth:test-verification {email : The email address to send test verification to}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test verification email';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid email address provided.');
            return 1;
        }

        // Create a temporary pending verification for testing
        $pendingUser = PendingUserVerification::create([
            'name' => 'Test User',
            'email' => $email,
            'password' => Hash::make('test-password'),
            'verification_token' => PendingUserVerification::generateToken(),
            'token_expires_at' => now()->addHours(24),
        ]);

        try {
            Notification::route('mail', $email)
                ->notify(new PendingUserEmailVerification($pendingUser));

            $this->info("Test verification email sent successfully to {$email}");

            if ($this->confirm('Do you want to delete the test pending verification record?', true)) {
                $pendingUser->delete();
                $this->info('Test pending verification record deleted.');
            }

        } catch (\Exception $e) {
            $this->error("Failed to send email: {$e->getMessage()}");
            $pendingUser->delete(); // Clean up
            return 1;
        }

        return 0;
    }
}
