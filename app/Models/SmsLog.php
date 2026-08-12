<?php

namespace App\Models;

use Database\Factories\SmsLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['phone', 'message', 'direction', 'device_type', 'status', 'external_id', 'raw_response', 'user_id', 'device_token_id'])]
class SmsLog extends Model
{
    /** @use HasFactory<SmsLogFactory> */
    use HasFactory, HasUuids;

    /**
     * Get the user that initiated the SMS.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the device that processed the SMS.
     */
    public function deviceToken(): BelongsTo
    {
        return $this->belongsTo(DeviceToken::class);
    }

    /**
     * Get the replies to this SMS, matched by their external_id.
     */
    public function replies(): HasMany
    {
        return $this->hasMany(SmsLog::class, 'external_id')->where('direction', 'reply');
    }

    /**
     * Scope a query to only include sent messages.
     */
    public function scopeSent($query)
    {
        return $query->where('direction', 'sent');
    }

    /**
     * Scope a query to only include replies.
     */
    public function scopeReplies($query)
    {
        return $query->where('direction', 'reply');
    }
}
