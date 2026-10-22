<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'photo', 'email', 'phone', 'ssc_batch', 'roll_no', 'profession', 'company_name', 'linkedin_url', 'facebook_url', 'present_address', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_STUDENT = 'student';

    /** Opens the admin panel. A student keeps their alumni profile and portal on top of it. */
    public const ADMIN_LEVEL_ADMIN = 'admin';

    /** An admin who can also make students admins. */
    public const ADMIN_LEVEL_SUPER = 'super';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'ssc_batch' => 'integer',
        ];
    }

    /**
     * Normalise a Bangladeshi mobile number to 01XXXXXXXXX.
     *
     * Strips spaces, dashes and brackets and a leading +88 / 88 country code.
     * Anything that isn't a phone number is returned trimmed but otherwise unchanged;
     * non-string input (e.g. phone[]=x) is returned as-is so validation can reject it.
     */
    public static function normalizePhone(mixed $phone): mixed
    {
        if (! is_string($phone)) {
            return $phone;
        }

        $digits = preg_replace('/[\s\-()]/', '', trim($phone));

        return preg_replace('/^\+?88(?=01\d{9}$)/', '', $digits);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function fieldValues(): HasMany
    {
        return $this->hasMany(StudentFieldValue::class);
    }

    /**
     * Store answers to admin-defined student fields.
     *
     * @param  array<int|string, ?string>  $answers  keyed by student field id
     */
    public function saveFieldValues(array $answers): void
    {
        foreach ($answers as $fieldId => $value) {
            $this->fieldValues()->updateOrCreate(['student_field_id' => $fieldId], ['value' => $value]);
        }

        $this->unsetRelation('fieldValues');
    }

    /**
     * Answers keyed by student field id.
     *
     * @return array<int, ?string>
     */
    public function fieldAnswers(): array
    {
        return $this->fieldValues->pluck('value', 'student_field_id')->all();
    }

    /**
     * Active required fields this student has not answered yet.
     *
     * @return Collection<int, StudentField>
     */
    public function missingRequiredFields(): Collection
    {
        $answers = $this->fieldAnswers();

        return StudentField::active()->where('is_required', true)->get()
            ->filter(fn (StudentField $field) => blank($answers[$field->id] ?? null))
            ->values();
    }

    public function photoUrl(): ?string
    {
        return $this->photo ? Storage::disk('public')->url($this->photo) : null;
    }

    /** Can open the admin panel: an admin account, or a student made admin by a super admin. */
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN || $this->admin_level !== null;
    }

    public function isSuperAdmin(): bool
    {
        return $this->admin_level === self::ADMIN_LEVEL_SUPER;
    }

    /** The badge shown in the header after login: "Super admin", "Admin", or null for a plain student. */
    public function adminBadge(): ?string
    {
        return match (true) {
            $this->isSuperAdmin() => 'Super admin',
            $this->isAdmin() => 'Admin',
            default => null,
        };
    }

    /** An alumnus with a portal and a place in the directory, whether or not they are also an admin. */
    public function isStudent(): bool
    {
        return $this->role === self::ROLE_STUDENT;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function hasAnyCompletedPayment(): bool
    {
        return $this->payments()->where('status', Payment::STATUS_COMPLETED)->exists();
    }

    public function hasPaidFor(ReunionEvent $event): bool
    {
        return $this->payments()
            ->where('reunion_event_id', $event->id)
            ->where('status', Payment::STATUS_COMPLETED)
            ->exists();
    }
}
