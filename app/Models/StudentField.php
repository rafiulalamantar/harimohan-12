<?php

namespace App\Models;

use Database\Factories\StudentFieldFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
    ];

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

    public function imageUrl(): ?string
    {
        return $this->image ? Storage::disk('public')->url($this->image) : null;
    }

    /** Validation rules for this field's answer, keyed as fields.{id}. */
    public function rules(): array
    {
        return [
            $this->is_required ? 'required' : 'nullable',
            ...match ($this->type) {
                'number' => ['numeric', 'min:0', 'max:99999'],
                'select' => [Rule::in($this->options ?? [])],
                'textarea' => ['string', 'max:2000'],
                default => ['string', 'max:255'],
            },
        ];
    }

    /**
     * Validation rules for every active field.
     *
     * @param  Collection<int, self>  $fields
     * @return array<string, array<int, mixed>>
     */
    public static function rulesFor(Collection $fields): array
    {
        return $fields->mapWithKeys(fn (self $field) => ["fields.{$field->id}" => $field->rules()])->all();
    }

    /**
     * Friendly attribute names so errors read "Polo T-shirt size is required".
     *
     * @param  Collection<int, self>  $fields
     * @return array<string, string>
     */
    public static function attributeNamesFor(Collection $fields): array
    {
        return $fields->mapWithKeys(fn (self $field) => ["fields.{$field->id}" => $field->label])->all();
    }
}
