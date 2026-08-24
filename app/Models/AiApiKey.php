<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AiApiKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_name',
        'contact_email',
        'contact_phone',
        'key',
        'type',
        'plan',
        'is_active',
        'voice_enabled',
        'query_count',
        'last_used_at',
        'expires_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'voice_enabled' => 'boolean',
        'query_count' => 'integer',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Generate a cryptographically secure token
     */
    public static function generateToken(string $type = 'live'): string
    {
        $prefix = $type === 'test' ? 'mn_test_' : 'mn_live_';
        return $prefix . bin2hex(random_bytes(16));
    }
}
