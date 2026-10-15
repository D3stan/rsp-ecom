<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\EmailService;
use App\Services\Reviews\ReviewRequests;
use Illuminate\Console\Command;

/**
 * Sends the consented review-request email for every eligible delivered order (see ReviewRequests). Idempotent:
 * OrderEmail dedupe guarantees at most one request per order, so running it again is safe.
 */
class SendReviewRequests extends Command
{
    protected $signature = 'reviews:send-requests';

    protected $description = 'Email a review request for delivered orders, to consented customers who have not reviewed their products';

    public function handle(ReviewRequests $requests, EmailService $email): int
    {
        if (! $requests->isEnabled()) {
            $this->info('Review requests are switched off.');

            return self::SUCCESS;
        }

        $sent = 0;

        $requests->eligibleOrdersQuery()
            ->with('user')
            ->orderBy('id')
            ->chunkById(100, function ($orders) use ($requests, $email, &$sent) {
                foreach ($orders as $order) {
                    /** @var Order $order */
                    if (! $requests->maySend($order)) {
                        continue;
                    }

                    if ($email->sendReviewRequest($order)) {
                        $sent++;
                    }
                }
            });

        $this->info("Queued {$sent} review request(s).");

        return self::SUCCESS;
    }
}
