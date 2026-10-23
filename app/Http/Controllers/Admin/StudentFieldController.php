<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StudentField;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class StudentFieldController extends Controller
{
    public function index()
    {
        return view('admin.student-fields.index', [
            'fields' => StudentField::withCount(['values as answered_count' => fn ($q) => $q->whereNotNull('value')->where('value', '!=', '')])
                ->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.student-fields.form', ['field' => new StudentField(['type' => 'text', 'is_active' => true, 'visibility' => 'admins'])]);
    }

    public function store(Request $request)
    {
        StudentField::create($this->validated($request));

        return redirect()->route('admin.student-fields.index')->with('success', 'Student field created.');
    }

    public function edit(StudentField $studentField)
    {
        return view('admin.student-fields.form', ['field' => $studentField]);
    }

    public function update(Request $request, StudentField $studentField)
    {
        $data = $this->validated($request);

        if (isset($data['image']) || $request->boolean('remove_image')) {
            $this->deleteImage($studentField);
            $data['image'] ??= null;
        }

        $clearedAnswers = DB::transaction(function () use ($studentField, $data) {
            $studentField->update($data);

            return $this->clearInvalidAnswers($studentField);
        });

        $message = 'Student field updated.';
        if ($clearedAnswers > 0) {
            $message .= " Cleared {$clearedAnswers} saved ".str('answer')->plural($clearedAnswers)
                .' that no longer fit this field; those students will be asked to answer again.';
        }

        return redirect()->route('admin.student-fields.index')->with('success', $message);
    }

    public function destroy(StudentField $studentField)
    {
        $this->deleteImage($studentField);
        $studentField->delete();

        return back()->with('success', 'Student field deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(array_keys(StudentField::TYPES))],
            'options' => ['nullable', Rule::requiredIf(in_array($request->input('type'), StudentField::CHOICE_TYPES, true)), 'string', 'max:2000'],
            'help_text' => ['nullable', 'string', 'max:500'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
            'visibility' => ['nullable', Rule::in(array_keys(StudentField::VISIBILITIES))],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ], ['options.required' => 'Add at least one choice for a dropdown, radio button or checkbox field.']);

        $data['options'] = in_array($data['type'], StudentField::CHOICE_TYPES, true)
            ? collect(preg_split('/\r\n|\r|\n/', $data['options'] ?? ''))->map(fn ($o) => trim($o))->filter()->unique()->values()->all()
            : null;
        $data['visibility'] ??= 'admins';
        $data['is_required'] = $request->boolean('is_required');
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('student-fields', 'public');
        } else {
            unset($data['image']);
        }

        return $data;
    }

    /**
     * Clear saved answers that no longer pass the field's rules (a removed choice,
     * text left behind after switching to a number, etc.), so the student is prompted
     * to answer again instead of being stuck with an invalid value.
     *
     * @return int number of answers cleared
     */
    private function clearInvalidAnswers(StudentField $field): int
    {
        $rules = $field->rules('value');

        $invalidIds = $field->values()
            ->whereNotNull('value')
            ->get(['id', 'value'])
            ->filter(fn ($answer) => Validator::make(['value' => $field->answerInput($answer->value)], $rules)->fails())
            ->pluck('id');

        if ($invalidIds->isEmpty()) {
            return 0;
        }

        return $field->values()->whereIn('id', $invalidIds)->update(['value' => null]);
    }

    private function deleteImage(StudentField $field): void
    {
        if ($field->image) {
            Storage::disk('public')->delete($field->image);
        }
    }
}
