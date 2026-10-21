<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StudentField;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
        return view('admin.student-fields.form', ['field' => new StudentField(['type' => 'text', 'is_active' => true])]);
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

        $studentField->update($data);

        return redirect()->route('admin.student-fields.index')->with('success', 'Student field updated.');
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
            'options' => ['nullable', 'required_if:type,select', 'string', 'max:2000'],
            'help_text' => ['nullable', 'string', 'max:500'],
            'image' => ['nullable', 'image', 'max:4096'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ], ['options.required_if' => 'Add at least one choice for a dropdown field.']);

        $data['options'] = $data['type'] === 'select'
            ? collect(preg_split('/\r\n|\r|\n|,/', $data['options'] ?? ''))->map(fn ($o) => trim($o))->filter()->unique()->values()->all()
            : null;
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

    private function deleteImage(StudentField $field): void
    {
        if ($field->image) {
            Storage::disk('public')->delete($field->image);
        }
    }
}
