<?php

namespace App\Console\Commands;

use App\Mail\OrderConfirmation;
use App\Models\Order;
use App\Models\User;
use App\Models\Product;
use App\Models\Address;
use App\Models\OrderItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;

class SendTestOrderConfirmation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:test-order-confirmation 
                            {email : The email address to send the test email to}
                            {--create-mock-data : Create mock data if no orders exist}
                            {--preview : Preview email content without sending}
                            {--force-send : Force send with smtp even if log driver is configured}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test order confirmation email to the specified email address';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid email address format.');
            return 1;
        }

        $this->info("Preparing to send test order confirmation email to: {$email}");

        try {
            // Try to get an existing order, or create mock data
            $order = $this->getOrCreateTestOrder();
            
            if (!$order) {
                $this->error('Could not find or create a test order.');
                return 1;
            }

            $this->info("Using order: {$order->order_number}");

            // Create a temporary customer for the email
            $testCustomer = new User([
                'name' => 'Test Customer',
                'email' => $email,
            ]);

            // Override the order's customer for this test
            $order->setRelation('user', $testCustomer);

            // Preview option
            if ($this->option('preview')) {
                $this->info("📧 Generating email preview...");
                $this->previewEmail($order);
                return 0;
            }

            // Debug: Check current mail configuration
            $this->info("📧 Current mail driver: " . config('mail.default'));
            $this->info("📧 Mail from address: " . config('mail.from.address'));
            $this->info("📧 Mail from name: " . config('mail.from.name'));

            // Send the email
            $this->info("🚀 Attempting to send email...");
            Mail::to($email)->send(new OrderConfirmation($order));

            $this->info("✅ Test order confirmation email sent successfully to: {$email}");

            // Additional information based on mail driver
            if (config('mail.default') === 'log') {
                $this->warn("⚠️  Mail driver is set to 'log' - emails are written to storage/logs/laravel.log instead of being sent.");
                $this->info("💡 To actually send emails, change MAIL_MAILER in your .env file to 'smtp' and configure mail settings.");
            } else {
                $this->info("📧 Check your inbox for the order confirmation email.");
            }

            // Display order details
            $this->displayOrderSummary($order);

        } catch (\Exception $e) {
            $this->error("Failed to send email: " . $e->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * Get an existing order or create mock test data
     */
    private function getOrCreateTestOrder(): ?Order
    {
        // Try to get the most recent order
        $order = Order::with(['orderItems.product', 'shippingAddress', 'billingAddress', 'user'])
                     ->latest()
                     ->first();

        if ($order) {
            $this->info('Using existing order from database.');
            return $order;
        }

        // If no orders exist and user wants to create mock data
        if ($this->option('create-mock-data')) {
            $this->info('No orders found. Creating mock test data...');
            return $this->createMockOrder();
        }

        $this->warn('No orders found in database. Use --create-mock-data flag to create test data.');
        return null;
    }

    /**
     * Create mock order data for testing
     */
    private function createMockOrder(): Order
    {
        return DB::transaction(function () {
            // Create a test user
            $user = User::create([
                'name' => 'Test Customer',
                'email' => 'test@example.com',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]);

            // Create shipping address
            $shippingAddress = Address::create([
                'user_id' => $user->id,
                'type' => 'shipping',
                'first_name' => 'Mario',
                'last_name' => 'Rossi',
                'address_line_1' => 'Via Roma 123',
                'address_line_2' => 'Appartamento 4B',
                'city' => 'Milano',
                'state' => 'Lombardia',
                'postal_code' => '20121',
                'country' => 'Italia',
                'phone' => '+39 02 1234567',
            ]);

            // Create billing address
            $billingAddress = Address::create([
                'user_id' => $user->id,
                'type' => 'billing',
                'first_name' => 'Mario',
                'last_name' => 'Rossi',
                'address_line_1' => 'Via Roma 123',
                'city' => 'Milano',
                'state' => 'Lombardia',
                'postal_code' => '20121',
                'country' => 'Italia',
                'phone' => '+39 02 1234567',
            ]);

            // Create test products if they don't exist
            $products = collect();
            for ($i = 1; $i <= 3; $i++) {
                $product = Product::create([
                    'name' => "Prodotto Test {$i}",
                    'slug' => "prodotto-test-{$i}",
                    'description' => "Descrizione del prodotto test numero {$i}. Questo è un prodotto di alta qualità per il testing.",
                    'price' => 29.99 + ($i * 10),
                    'compare_price' => 39.99 + ($i * 10),
                    'stock_quantity' => 100,
                    'sku' => "TEST-PROD-{$i}",
                    'status' => 'active',
                    'featured' => $i === 1,
                    'images' => ['product-placeholder.jpg'],
                ]);
                $products->push($product);
            }

            // Create the order
            $order = Order::create([
                'user_id' => $user->id,
                'billing_address_id' => $billingAddress->id,
                'shipping_address_id' => $shippingAddress->id,
                'status' => 'processing',
                'subtotal' => 129.97,
                'tax_amount' => 28.59,
                'shipping_amount' => 9.99,
                'total_amount' => 168.55,
                'currency' => 'EUR',
                'payment_status' => 'succeeded',
                'payment_method' => 'card',
                'stripe_payment_intent_id' => 'pi_test_' . uniqid(),
                'notes' => 'Test order created by command for email testing',
            ]);

            // Create order items
            foreach ($products as $index => $product) {
                $quantity = $index + 1;
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $quantity,
                    'price' => $product->price,
                    'total' => $product->price * $quantity,
                ]);
            }

            // Load relationships
            $order->load(['orderItems.product', 'shippingAddress', 'billingAddress', 'user']);

            $this->info('✅ Mock test data created successfully.');
            
            return $order;
        });
    }

    /**
     * Display order summary in the console
     */
    private function displayOrderSummary(Order $order): void
    {
        $this->info("\n📋 Order Summary:");
        $this->table(
            ['Field', 'Value'],
            [
                ['Order Number', $order->order_number],
                ['Status', ucfirst($order->status)],
                ['Payment Status', ucfirst($order->payment_status)],
                ['Customer', $order->user->name ?? 'Guest'],
                ['Total Items', $order->orderItems->count()],
                ['Subtotal', '€' . number_format($order->subtotal, 2)],
                ['Tax', '€' . number_format($order->tax_amount, 2)],
                ['Shipping', '€' . number_format($order->shipping_amount, 2)],
                ['Total', '€' . number_format($order->total_amount, 2)],
                ['Created', $order->created_at->format('d/m/Y H:i')],
            ]
        );

        $this->info("\n📦 Order Items:");
        $orderItemsData = $order->orderItems->map(function ($item) {
            return [
                $item->product_name,
                $item->quantity,
                '€' . number_format($item->price, 2),
                '€' . number_format($item->total, 2),
            ];
        })->toArray();

        $this->table(
            ['Product', 'Qty', 'Price', 'Total'],
            $orderItemsData
        );

        if ($order->shippingAddress) {
            $this->info("\n🏠 Shipping Address:");
            $this->line("   {$order->shippingAddress->first_name} {$order->shippingAddress->last_name}");
            $this->line("   {$order->shippingAddress->address_line_1}");
            if ($order->shippingAddress->address_line_2) {
                $this->line("   {$order->shippingAddress->address_line_2}");
            }
            $this->line("   {$order->shippingAddress->postal_code} {$order->shippingAddress->city}");
            $this->line("   {$order->shippingAddress->state}, {$order->shippingAddress->country}");
        }
    }

    /**
     * Preview the email content
     */
    private function previewEmail(Order $order): void
    {
        try {
            $mailable = new OrderConfirmation($order);
            $rendered = $mailable->render();
            
            $this->info("📧 Email Subject: " . $mailable->envelope()->subject);
            $this->info("📧 Email Preview (first 500 characters):");
            $this->line("─────────────────────────────────────────────────────────");
            $this->line(substr(strip_tags($rendered), 0, 500) . "...");
            $this->line("─────────────────────────────────────────────────────────");
            
            // Save preview to file
            $previewPath = storage_path('logs/email_preview_' . date('Y-m-d_H-i-s') . '.html');
            file_put_contents($previewPath, $rendered);
            $this->info("💾 Full HTML preview saved to: {$previewPath}");
            
        } catch (\Exception $e) {
            $this->error("Failed to preview email: " . $e->getMessage());
        }
    }
}
