<?php

namespace App\Models;

use App\Enums\UserNotificationKind;
use App\Jobs\SendUserNotification;
use App\Notifications\AccountMailMessages;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Cashier\Billable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, Billable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'is_active',
        'google_id',
        'avatar',
        'email_verified_at',
        'notify_back_in_stock',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'notify_back_in_stock' => 'boolean',
        ];
    }

    /**
     * Get the attributes that should be appended to the model's array form.
     *
     * @var array
     */
    protected $appends = ['has_password'];

    /**
     * Determine if the user has a password set.
     *
     * @return bool
     */
    public function getHasPasswordAttribute(): bool
    {
        return !is_null($this->password);
    }

    // Relationships
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    public function wishlist(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeCustomers($query)
    {
        return $query->where('role', 'customer');
    }

    public function scopeAdmins($query)
    {
        return $query->where('role', 'admin');
    }

    /**
     * Brand the framework's password-reset and verification emails (localised, with plain-text part).
     */
    protected static function booted(): void
    {
        ResetPassword::toMailUsing(function (object $notifiable, string $token) {
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));

            return AccountMailMessages::passwordReset($notifiable, $url);
        });

        VerifyEmail::toMailUsing(fn (object $notifiable, string $url) => AccountMailMessages::verifyEmail($notifiable, $url));

        // Orders outlive the account (the FK sets orders.user_id to NULL). Copy who bought onto them first,
        // so the shop still knows whom to contact; the name stays on the order's billing address.
        // Address-book entries no order uses go with the account; addresses on orders are kept.
        static::deleting(function (User $user) {
            $user->orders()->whereNull('guest_email')->update(['guest_email' => $user->email]);

            if (filled($user->phone)) {
                $user->orders()->whereNull('guest_phone')->update(['guest_phone' => mb_substr($user->phone, 0, 20)]);
            }

            $user->addresses()
                ->whereDoesntHave('billingOrders')
                ->whereDoesntHave('shippingOrders')
                ->delete();

            // The reviews go with the account anyway (FK cascade), but the cascade skips model events: deleting
            // them one by one lets Review remove the customer's photo files from the public disk.
            $user->reviews()->get()->each->delete();
        });
    }

    /**
     * Queue the password-reset mail instead of sending it inside the request.
     * The locale is captured now: the worker has no request.
     */
    public function sendPasswordResetNotification($token): void
    {
        SendUserNotification::dispatch($this->id, UserNotificationKind::PasswordReset, (string) $token, app()->getLocale());
    }

    /**
     * Queue the email-verification mail instead of sending it inside the request.
     */
    public function sendEmailVerificationNotification(): void
    {
        SendUserNotification::dispatch($this->id, UserNotificationKind::VerifyEmail, null, app()->getLocale());
    }

    // Helper methods
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isCustomer(): bool
    {
        return $this->role === 'customer';
    }

    public function getDefaultShippingAddress(): ?Address
    {
        return $this->addresses()
            ->where('type', 'shipping')
            ->where('is_default', true)
            ->first();
    }

    /** Address-book size limit: the oldest non-default entry makes room for a new one. */
    public const MAX_SAVED_ADDRESSES = 10;

    /**
     * Save an address in the address book and make it the default (the one the customer just shipped to). An address
     * the book already has is left alone: the customer's own choice of default stays.
     */
    public function rememberShippingAddress(Address $address): void
    {
        $fields = $address->only(Address::CHECKOUT_FIELDS);

        if ($this->addresses()->shipping()->where($fields)->exists()) {
            return;
        }

        DB::transaction(function () use ($fields) {
            $book = $this->addresses()->shipping();

            if ((clone $book)->count() >= self::MAX_SAVED_ADDRESSES) {
                (clone $book)->where('is_default', false)->oldest('id')->first()?->delete();
            }

            $this->addresses()->shipping()->update(['is_default' => false]);
            $this->addresses()->create($fields + ['type' => 'shipping', 'is_default' => true]);
        });
    }

    /**
     * Orders placed as a guest with this (verified) email belong to the account now: they show up in the order
     * history, and when the address book is still empty the latest one's address becomes the default.
     * Idempotent; returns how many orders were attached.
     */
    public function claimGuestOrders(): int
    {
        if (! $this->hasVerifiedEmail()) {
            return 0;
        }

        $orders = Order::guestOrders()->whereRaw('lower(guest_email) = ?', [mb_strtolower($this->email)])->get();

        if ($orders->isEmpty()) {
            return 0;
        }

        $claimed = Order::whereKey($orders->modelKeys())->whereNull('user_id')->update(['user_id' => $this->id]);

        $address = $orders->sortByDesc('id')->first()->shippingAddress;
        if ($address && ! $this->addresses()->shipping()->exists()) {
            $this->rememberShippingAddress($address);
        }

        return $claimed;
    }

    public function getDefaultBillingAddress(): ?Address
    {
        return $this->addresses()
            ->where('type', 'billing')
            ->where('is_default', true)
            ->first();
    }

    public function getCurrentCart(): ?Cart
    {
        return $this->carts()->latest()->first();
    }

    public function cart()
    {
        return $this->hasOne(Cart::class)->latest();
    }

    public function getOrCreateCart(): Cart
    {
        return $this->getCurrentCart() ?? $this->carts()->create();
    }

    public function hasInWishlist(Product $product): bool
    {
        return Wishlist::isInWishlist($this, $product);
    }

    public function addToWishlist(Product $product): bool
    {
        return Wishlist::addToWishlist($this, $product);
    }

    public function removeFromWishlist(Product $product): bool
    {
        return Wishlist::removeFromWishlist($this, $product);
    }
}
