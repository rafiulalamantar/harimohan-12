<?php

namespace App\Models;

use Database\Factories\MemberEmailFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberEmail extends Model
{
    /** @use HasFactory<MemberEmailFactory> */
    use HasFactory;

    /** Approved and pending members; rejected accounts never get member emails. */
    public const AUDIENCE_ALL = 'all';

    public const AUDIENCE_APPROVED = 'approved';

    public const AUDIENCE_PENDING = 'pending';

    /** @var array<string, string> */
    public const AUDIENCES = [
        self::AUDIENCE_ALL => 'All members (approved + pending)',
        self::AUDIENCE_APPROVED => 'Approved members only',
        self::AUDIENCE_PENDING => 'Pending members only',
    ];

    /** Brevo's free plan sends at most 300 emails a day across the whole account. */
    public const DAILY_LIMIT = 300;

    protected $fillable = ['user_id', 'subject', 'body', 'audience', 'ssc_batch', 'recipient_count'];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Members in the chosen group (optionally one SSC batch) who have an email address.
     *
     * @return Builder<User>
     */
    public static function recipients(string $audience, ?int $sscBatch = null): Builder
    {
        $statuses = match ($audience) {
            self::AUDIENCE_APPROVED => [User::STATUS_APPROVED],
            self::AUDIENCE_PENDING => [User::STATUS_PENDING],
            default => [User::STATUS_APPROVED, User::STATUS_PENDING],
        };

        return User::where('role', User::ROLE_STUDENT)
            ->whereIn('status', $statuses)
            ->whereNotNull('email')
            ->when($sscBatch, fn (Builder $query) => $query->where('ssc_batch', $sscBatch));
    }

    /** Bcc recipients per message; each message also sends one copy to the site's own "To" address. */
    public const RECIPIENTS_PER_MESSAGE = 50;

    /** Emails Brevo counts for one send to this many members: every member plus the "To" copy of each message. */
    public static function brevoEmailsFor(int $members): int
    {
        return $members + (int) ceil($members / self::RECIPIENTS_PER_MESSAGE);
    }

    /** Emails still allowed today under {@see self::DAILY_LIMIT}. */
    public static function remainingToday(): int
    {
        $usedToday = self::whereDate('created_at', today())->pluck('recipient_count')
            ->sum(fn (int $members) => self::brevoEmailsFor($members));

        return max(0, self::DAILY_LIMIT - $usedToday);
    }
}
