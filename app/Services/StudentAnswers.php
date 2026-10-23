<?php

namespace App\Services;

use App\Models\StudentField;
use App\Models\StudentFieldValue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Answers to the admin's choice fields (dropdown, radio buttons, checkboxes) across many students,
 * for the admin Students page: filter the list by an answer, and count how many picked each choice.
 */
class StudentAnswers
{
    /** @var Collection<int, StudentField> choice fields keyed by field id */
    public readonly Collection $choiceFields;

    /**
     * @param  Collection<int, StudentField>  $fields  the fields shown on the page
     */
    public function __construct(Collection $fields)
    {
        $this->choiceFields = $fields->filter(fn (StudentField $field) => $field->hasChoices())->keyBy('id');
    }

    /**
     * Filters from ?answer[field id]=choice, keeping only real choices of the choice fields.
     *
     * @return array<int, string> choice keyed by field id
     */
    public function filtersFrom(mixed $requested): array
    {
        return collect(is_array($requested) ? $requested : [])
            ->filter(fn ($choice, $fieldId) => is_string($choice)
                && in_array($choice, $this->choiceFields->get($fieldId)?->options ?? [], true))
            ->all();
    }

    /**
     * Keep only students who picked each filtered choice.
     *
     * @param  array<int, string>  $filters  from {@see filtersFrom()}
     */
    public function applyFilters(Builder $students, array $filters): Builder
    {
        foreach ($filters as $fieldId => $choice) {
            $students->whereIn('id', $this->studentsWhoChose($this->choiceFields[$fieldId], $choice));
        }

        return $students;
    }

    /**
     * How many of the given students picked each choice, e.g. T-shirt size XL: 12.
     *
     * @return array<int, array<string, int>> choice => count, keyed by field id
     */
    public function choiceCounts(Builder $students): array
    {
        if ($this->choiceFields->isEmpty()) {
            return [];
        }

        $counts = $this->choiceFields->map(fn (StudentField $field) => array_fill_keys($field->options ?? [], 0))->all();

        StudentFieldValue::whereIn('student_field_id', $this->choiceFields->keys())
            ->whereIn('user_id', (clone $students)->select('id'))
            ->whereNotNull('value')
            ->get(['student_field_id', 'value'])
            ->each(function (StudentFieldValue $answer) use (&$counts) {
                // A checkbox answer holds several choices, one per line.
                foreach ((array) $this->choiceFields[$answer->student_field_id]->answerInput($answer->value) as $choice) {
                    if (isset($counts[$answer->student_field_id][$choice])) {
                        $counts[$answer->student_field_id][$choice]++;
                    }
                }
            });

        return $counts;
    }

    /**
     * Ids of students whose answer to $field includes $choice.
     *
     * @return Collection<int, int>
     */
    private function studentsWhoChose(StudentField $field, string $choice): Collection
    {
        if ($field->type !== 'checkboxes') {
            return $field->values()->where('value', $choice)->pluck('user_id');
        }

        return $field->values()->whereNotNull('value')->get(['user_id', 'value'])
            ->filter(fn ($answer) => in_array($choice, $field->answerInput($answer->value), true))
            ->pluck('user_id');
    }
}
