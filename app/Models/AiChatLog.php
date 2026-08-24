<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiChatLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'api_key_id',
        'api_key',
        'store_url',
        'session_id',
        'role',
        'message',
        'actions',
        'has_audio',
        'audio_url',
        'metadata',
    ];

    protected $casts = [
        'actions' => 'array',
        'metadata' => 'array',
        'has_audio' => 'boolean',
    ];

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(AiApiKey::class, 'api_key_id');
    }
}
