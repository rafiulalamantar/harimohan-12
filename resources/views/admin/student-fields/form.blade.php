@extends('layouts.app')
@section('title', $field->exists ? 'Edit student field' : 'New student field')

@section('content')
<div class="max-w-3xl mx-auto bg-white rounded-2xl border p-6 md:p-8">
    <h1 class="text-2xl font-bold">{{ $field->exists ? 'Edit student field' : 'New student field' }}</h1>
    <form method="POST" enctype="multipart/form-data" class="mt-6 space-y-4"
          action="{{ $field->exists ? route('admin.student-fields.update', $field) : route('admin.student-fields.store') }}">
        @csrf
        @if($field->exists) @method('PUT') @endif
        <x-field name="label" label="Label" :value="$field->label" required placeholder="Polo T-shirt size" />
        <div>
            <label for="type" class="block text-sm font-medium mb-1">Answer type <span class="text-red-600">*</span></label>
            <select id="type" name="type" class="w-full rounded-lg border-gray-300 border px-3 py-2">
                @foreach (\App\Models\StudentField::TYPES as $value => $name)
                    <option value="{{ $value }}" @selected(old('type', $field->type) === $value)>{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <x-field name="options" label="Choices" type="textarea" :value="implode(PHP_EOL, $field->options ?? [])"
                 hint="Only for dropdown, radio button and checkbox fields. Put each choice on its own line (a choice may contain commas)." />
        <x-field name="help_text" label="Help text (optional)" :value="$field->help_text" placeholder="Check the size chart and pick your chest size." />
        @if ($field->image)
            <div>
                <img src="{{ $field->imageUrl() }}" alt="" class="max-h-64 rounded-lg border">
                <label class="flex items-center gap-2 text-sm mt-2"><input type="checkbox" name="remove_image" value="1"> Remove image</label>
            </div>
        @endif
        <x-field name="image" :label="$field->image ? 'Change reference image' : 'Reference image (optional)'" type="file" accept="image/jpeg,image/png,image/webp,image/gif,image/heic,image/heif,.heic,.heif"
                 hint="Shown to students next to the field, e.g. a T-shirt size chart. PNG/JPG, max 4 MB" />
        <x-field name="sort_order" label="Order" type="number" min="0" :value="$field->sort_order ?? 0" hint="Lower numbers appear first." />
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_required" value="1" @checked(old('is_required', $field->is_required))> Required (students must answer before saving their profile)
        </label>
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $field->is_active))> Active (shown to students)
        </label>
        <button class="bg-brand-600 hover:bg-brand-700 text-white font-semibold rounded-lg px-6 py-2.5">Save</button>
    </form>
</div>
@endsection
