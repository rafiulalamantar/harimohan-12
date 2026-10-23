<?php

namespace App\Models;

use Database\Factories\StudentFieldFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** An extra question the admin asks students, e.g. "Polo T-shirt size". */
class StudentField extends Model
{
    /** @use HasFactory<StudentFieldFactory> */
    use HasFactory;

    public const TYPES = [
        'text' => 'Short text',
        'number' => 'Number',
        'textarea' => 'Long text',
        'select' => 'Dropdown (choose one)',
        'radio' => 'Radio buttons (choose one)',
        'checkboxes' => 'Checkboxes (choose any)',
        'date' => 'Date',
        'image' => 'Image only (no answer)',
    ];

    /** Types whose answers must be one (or, for checkboxes, several) of the admin's choices. */
    public const CHOICE_TYPES = ['select', 'radio', 'checkboxes'];

    protected $fillable = ['label', 'type', 'options', 'help_text', 'image', 'is_required', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function values(): HasMany
    {
        return $this->hasMany(StudentFieldValue::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    /** Image-only fields show students a picture and collect no answer. */
    public function takesAnswer(): bool
    {
        return $this->type !== 'image';
    }

    public function hasChoices(): bool
    {
        return in_array($this->type, self::CHOICE_TYPES, true);
    }

    public function imageUrl(): ?string
    {
        return $this->image ? Storage::disk('public')->url($this->image) : null;
    }

    /**
     * Validation rules for this field's answer as submitted under $key
     * (checkboxes submit a list, so their choices are checked under "$key.*").
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(string $key): array
    {
        $rules = [$key => [
            $this->is_required ? 'required' : 'nullable',
            ...match ($this->type) {
                'number' => ['numeric', 'min:0', 'max:99999'],
                'select', 'radio' => [Rule::in($this->options ?? [])],
                'checkboxes' => ['array'],
                'date' => ['date_format:Y-m-d'],
                'textarea' => ['string', 'max:2000'],
                default => ['string', 'max:255'],
            },
        ]];

        if ($this->type === 'checkboxes') {
            $rules["{$key}.*"] = ['string', 'distinct', Rule::in($this->options ?? [])];
        }

        return $rules;
    }

    /**
     * Turn a stored answer back into form input: checkbox choices are stored one per line.
     *
     * @return string|array<int, string>|null
     */
    public function answerInput(?string $storedAnswer): string|array|null
    {
        if ($this->type !== 'checkboxes') {
            return $storedAnswer;
        }

        return $storedAnswer === null ? [] : array_values(array_filter(explode("\n", $storedAnswer), 'strlen'));
    }

    /**
     * Turn submitted input into the text that is stored.
     *
     * @param  string|array<int, string>|null  $input
     */
    public function storedAnswer(string|array|null $input): ?string
    {
        if (is_array($input)) {
            return $input === [] ? null : implode("\n", $input);
        }

        return $input;
    }

    /** A stored answer formatted for people to read. */
    public function displayAnswer(?string $storedAnswer): ?string
    {
        if (blank($storedAnswer)) {
            return null;
        }

        return match ($this->type) {
            'checkboxes' => implode(', ', $this->answerInput($storedAnswer)),
            'date' => rescue(fn () => Carbon::createFromFormat('Y-m-d', $storedAnswer)->format('d M Y'), $storedAnswer, report: false),
            default => $storedAnswer,
        };
    }

    /**
     * Validation rules for every active field.
     *
     * @param  Collection<int, self>  $fields
     * @return array<string, array<int, mixed>>
     */
    public static function rulesFor(Collection $fields): array
    {
        return $fields->filter->takesAnswer()->flatMap(fn (self $field) => $field->rules("fields.{$field->id}"))->all();
    }

    /**
     * Stored answers for every given field, keyed by field id. A field left out of the input
     * (e.g. checkboxes with nothing ticked) is saved as no answer.
     *
     * @param  Collection<int, self>  $fields
     * @param  array<int|string, string|array<int, string>|null>  $input  validated answers keyed by field id
     * @return array<int, ?string>
     */
    public static function answersFrom(Collection $fields, array $input): array
    {
        return $fields->filter->takesAnswer()
            ->mapWithKeys(fn (self $field) => [$field->id => $field->storedAnswer($input[$field->id] ?? null)])->all();
    }

    /**
     * Friendly attribute names so errors read "Polo T-shirt size is required".
     *
     * @param  Collection<int, self>  $fields
     * @return array<string, string>
     */
    public static function attributeNamesFor(Collection $fields): array
    {
        return $fields->flatMap(fn (self $field) => ["fields.{$field->id}" => $field->label, "fields.{$field->id}.*" => $field->label])->all();
    }
}
