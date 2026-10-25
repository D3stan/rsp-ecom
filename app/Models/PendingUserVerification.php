<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PendingUserVerification extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'password',
        'verification_token',
        'token_expires_at',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public static function generateToken(): string
    {
        return Str::random(64);
    }

    public function isExpired(): bool
    {
        return $this->token_expires_at->isPast();
    }

    public function createVerifiedUser(): User
    {
        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
            'email_verified_at' => now(),
            'role' => 'customer',
            'is_active' => true,
        ]);

        $this->delete();

        return $user;
    }

    public function regenerateToken(): void
    {
        $this->update([
            'verification_token' => self::generateToken(),
            'token_expires_at' => now()->addHours(24),
        ]);
    }
}
