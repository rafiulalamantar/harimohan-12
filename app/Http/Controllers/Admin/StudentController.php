<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StudentField;
use App\Models\StudentFieldValue;
use App\Models\User;
use App\Services\CsvWriter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $fields = StudentField::active()->get();
        $choiceFields = $fields->filter(fn (StudentField $field) => $field->hasChoices())->keyBy('id');
        $answerFilters = $this->answerFilters($request, $choiceFields);

        $query = User::where('role', User::ROLE_STUDENT)
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->batch, fn ($q, $b) => $q->where('ssc_batch', $b))
            ->when($request->q, fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")));

        foreach ($answerFilters as $fieldId => $choice) {
            $query->whereIn('id', $this->studentsWhoChose($choiceFields[$fieldId], $choice));
        }

        $students = (clone $query)
            ->with(['fieldValues' => fn ($q) => $q->whereIn('student_field_id', $fields->modelKeys())])
            ->withCount(['payments as paid_count' => fn ($q) => $q->where('status', 'completed')])
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $batches = User::where('role', User::ROLE_STUDENT)->whereNotNull('ssc_batch')
            ->distinct()->orderByDesc('ssc_batch')->pluck('ssc_batch');

        return view('admin.students.index', [
            'students' => $students,
            'batches' => $batches,
            'fields' => $fields,
            'choiceFields' => $choiceFields,
            'answerFilters' => $answerFilters,
            'choiceCounts' => $this->choiceCounts($choiceFields, $query),
        ]);
    }

    /**
     * Answer filters from ?answer[field id]=choice, keeping only real choices of active choice fields.
     *
     * @param  Collection<int, StudentField>  $choiceFields  keyed by field id
     * @return array<int, string>
     */
    private function answerFilters(Request $request, Collection $choiceFields): array
    {
        $requested = $request->input('answer');

        return collect(is_array($requested) ? $requested : [])
            ->filter(fn ($choice, $fieldId) => is_string($choice)
                && in_array($choice, $choiceFields->get($fieldId)?->options ?? [], true))
            ->all();
    }

    /**
     * Ids of students whose answer to $field includes $choice (checkbox answers hold several choices).
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

    /**
     * How many of the listed students picked each choice, e.g. T-shirt size XL: 12.
     *
     * @param  Collection<int, StudentField>  $choiceFields  keyed by field id
     * @return array<int, array<string, int>> choice => count, keyed by field id
     */
    private function choiceCounts(Collection $choiceFields, Builder $students): array
    {
        if ($choiceFields->isEmpty()) {
            return [];
        }

        $counts = $choiceFields->map(fn (StudentField $field) => array_fill_keys($field->options ?? [], 0))->all();

        StudentFieldValue::whereIn('student_field_id', $choiceFields->keys())
            ->whereIn('user_id', (clone $students)->select('id'))
            ->whereNotNull('value')
            ->get(['student_field_id', 'value'])
            ->each(function (StudentFieldValue $answer) use (&$counts, $choiceFields) {
                foreach ((array) $choiceFields[$answer->student_field_id]->answerInput($answer->value) as $choice) {
                    if (isset($counts[$answer->student_field_id][$choice])) {
                        $counts[$answer->student_field_id][$choice]++;
                    }
                }
            });

        return $counts;
    }

    public function show(User $student)
    {
        abort_unless($student->isStudent(), 404);

        $student->load('payments.event', 'fieldValues');

        return view('admin.students.show', [
            'student' => $student,
            'fields' => StudentField::orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function updateStatus(Request $request, User $student)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([User::STATUS_PENDING, User::STATUS_APPROVED, User::STATUS_REJECTED])],
        ]);

        abort_unless($student->isStudent(), 403);

        if ($data['status'] !== User::STATUS_APPROVED && $student->isAdmin()) {
            return back()->with('error', "{$student->name} is an admin. Remove their admin access first.");
        }

        if ($data['status'] === User::STATUS_REJECTED && $student->hasAnyCompletedPayment()) {
            return back()->with('error', "{$student->name} has already paid, so they cannot be rejected.");
        }

        // Another admin may have handled this registration since the page was loaded.
        if ($data['status'] === $student->status && $student->reviewer) {
            return back()->with('info', "{$student->name} is already {$student->status} by {$student->reviewer->name}.");
        }

        $reviewed = $data['status'] !== User::STATUS_PENDING;

        $student->forceFill([
            'status' => $data['status'],
            'reviewed_by' => $reviewed ? $request->user()->id : null,
            'reviewed_at' => $reviewed ? now() : null,
        ]);

        // Invalidate "remember me" logins so a rejected student can't come back in.
        if ($data['status'] === User::STATUS_REJECTED) {
            $student->setRememberToken(Str::random(60));
        }

        $student->save();

        return back()->with('success', "{$student->name}'s status: {$data['status']}");
    }

    /** Super admins make an approved student an admin, or take admin access away again. */
    public function updateAdminAccess(Request $request, User $student)
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        abort_unless($student->isStudent(), 404);

        $makeAdmin = $request->validate(['admin' => ['required', 'boolean']])['admin'];

        if ($makeAdmin && ! $student->isApproved()) {
            return back()->with('error', "Approve {$student->name} before making them an admin.");
        }

        $student->forceFill(['admin_level' => $makeAdmin ? User::ADMIN_LEVEL_ADMIN : null])->save();

        return back()->with('success', $makeAdmin ? "{$student->name} is now an admin." : "{$student->name} is no longer an admin.");
    }

    public function export(Request $request)
    {
        $filename = 'students-'.now()->format('Ymd-His').'.csv';

        $fields = StudentField::orderBy('sort_order')->orderBy('id')->get();

        return response()->streamDownload(function () use ($fields) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows Unicode correctly
            fputcsv($out, ['Name', 'Email', 'Phone', 'SSC Batch', 'Blood Group', 'Profession', 'Company', 'LinkedIn', 'Facebook', 'Status', 'Paid', 'Registered At',
                ...$fields->pluck('label')]);

            User::where('role', User::ROLE_STUDENT)
                ->with('fieldValues')
                ->withCount(['payments as paid_count' => fn ($q) => $q->where('status', 'completed')])
                ->orderBy('ssc_batch')->orderBy('name')
                ->chunk(500, function ($users) use ($out, $fields) {
                    foreach ($users as $u) {
                        $answers = $u->readableFieldAnswers($fields);
                        CsvWriter::writeRow($out, [$u->name, $u->email, $u->phone, $u->ssc_batch, $u->blood_group,
                            $u->profession, $u->company_name, $u->linkedin_url, $u->facebook_url, $u->status, $u->paid_count > 0 ? 'Yes' : 'No', $u->created_at,
                            ...$fields->map(fn ($f) => $answers[$f->id] ?? '')]);
                    }
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
