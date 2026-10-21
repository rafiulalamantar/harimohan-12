<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReunionEvent extends Model
{
    use SoftDeletes;

    protected $fillable = ['title', 'description', 'event_date', 'venue', 'fee', 'payment_deadline', 'is_active'];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'payment_deadline' => 'date',
            'fee' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public static function current(): ?self
    {
        return static::where('is_active', true)->latest('event_date')->first();
    }

    public function isPaymentOpen(): bool
    {
        return $this->is_active
            && ($this->payment_deadline === null || $this->payment_deadline->endOfDay()->isFuture());
    }
}
