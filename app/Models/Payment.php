<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Payment extends Model
{
    public const STATUS_INITIATED = 'initiated';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id', 'reunion_event_id', 'amount', 'invoice_no', 'method', 'status',
        'bkash_payment_id', 'trx_id', 'payer_msisdn', 'gateway_response', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'gateway_response' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(ReunionEvent::class, 'reunion_event_id')->withTrashed();
    }

    public static function newInvoiceNo(): string
    {
        return 'RU'.now()->format('ymdHis').strtoupper(Str::random(5));
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            self::STATUS_COMPLETED => 'bg-green-100 text-green-800',
            self::STATUS_FAILED => 'bg-red-100 text-red-800',
            self::STATUS_CANCELLED => 'bg-gray-200 text-gray-700',
            default => 'bg-yellow-100 text-yellow-800',
        };
    }
}
