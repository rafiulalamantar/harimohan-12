<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StudentFieldRequest;
use App\Models\StudentField;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/** Admins manage the extra questions students answer on registration and My Profile. */
class StudentFieldController extends Controller
{
    public function index(): View
    {
        return view('admin.student-fields.index', [
            'fields' => StudentField::withCount(['values as answered_count' => fn ($q) => $q->whereNotNull('value')->where('value', '!=', '')])
                ->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.student-fields.form', ['field' => new StudentField(['type' => 'text', 'is_active' => true])]);
    }

    public function store(StudentFieldRequest $request): RedirectResponse
    {
        StudentField::create($this->fieldData($request));

        return redirect()->route('admin.student-fields.index')->with('success', 'Student field created.');
    }

    public function edit(StudentField $studentField): View
    {
        return view('admin.student-fields.form', ['field' => $studentField]);
    }

    public function update(StudentFieldRequest $request, StudentField $studentField): RedirectResponse
    {
        $data = $this->fieldData($request);

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

    /** Deleting a field also deletes every answer to it; turning it off keeps them. */
    public function destroy(StudentField $studentField): RedirectResponse
    {
        $this->deleteImage($studentField);
        $studentField->delete();

        return back()->with('success', 'Student field deleted.');
    }

    /**
     * The field's columns, with the uploaded image stored when there is one.
     *
     * @return array<string, mixed>
     */
    private function fieldData(StudentFieldRequest $request): array
    {
        $data = $request->fieldData();

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('student-fields', 'public');
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
