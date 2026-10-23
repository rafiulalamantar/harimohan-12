<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StudentField;
use App\Models\User;
use App\Services\CsvWriter;
use App\Services\PhotoThumbnail;
use App\Services\StudentAnswers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Admins review registrations, manage admin access and fix card photos. */
class StudentController extends Controller
{
    /** The student list with search, filters, a column per extra field and choice totals. */
    public function index(Request $request): View
    {
        $fields = StudentField::active()->get()->filter->takesAnswer();
        $answers = new StudentAnswers($fields);
        $answerFilters = $answers->filtersFrom($request->input('answer'));

        $query = $answers->applyFilters(User::students()
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->batch, fn ($q, $batch) => $q->where('ssc_batch', $batch))
            ->when($request->q, fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%"))), $answerFilters);

        $students = (clone $query)
            ->with(['fieldValues' => fn ($q) => $q->whereIn('student_field_id', $fields->modelKeys())])
            ->withPaidCount()
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.students.index', [
            'students' => $students,
            'batches' => User::students()->whereNotNull('ssc_batch')->distinct()->orderByDesc('ssc_batch')->pluck('ssc_batch'),
            'fields' => $fields,
            'choiceFields' => $answers->choiceFields,
            'answerFilters' => $answerFilters,
            'choiceCounts' => $answers->choiceCounts($query),
        ]);
    }

    public function show(User $student): View
    {
        abort_unless($student->isStudent(), 404);

        $student->load('payments.event', 'fieldValues');

        return view('admin.students.show', [
            'student' => $student,
            'fields' => StudentField::orderBy('sort_order')->orderBy('id')->get()->filter->takesAnswer(),
        ]);
    }

    /** Approve, reject or reset a registration, recording which admin did it. */
    public function updateStatus(Request $request, User $student): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(User::STATUSES)],
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
    public function updateAdminAccess(Request $request, User $student): RedirectResponse
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

    /**
     * Replace the card/avatar copy of a student's photo with one an admin framed from the original
     * (resources/js/photos/reframe.js, data-reframe-photo). The original photo is kept, so it can be framed again.
     */
    public function updateCardPhoto(Request $request, User $student): RedirectResponse
    {
        abort_unless($student->isStudent() && $student->photo, 404);

        $thumbnail = $request->validate([
            'photo_thumbnail' => ['required', 'image', 'mimes:jpg,jpeg', 'max:512'],
        ], ['photo_thumbnail.required' => 'Frame the photo before saving.'])['photo_thumbnail'];

        $newThumb = PhotoThumbnail::storeReframed($thumbnail, $student->photo);
        abort_unless($newThumb, 500, 'The framed photo could not be stored.');

        $oldThumb = $student->photo_thumb;
        $student->forceFill(['photo_thumb' => $newThumb])->save();

        if ($oldThumb && $oldThumb !== $newThumb) {
            Storage::disk('public')->delete($oldThumb);
        }

        return back()->with('success', "Updated {$student->name}'s card photo.");
    }

    /** Every student with their answers to the extra fields, as a UTF-8 CSV that Excel opens correctly. */
    public function export(Request $request): StreamedResponse
    {
        $filename = 'students-'.now()->format('Ymd-His').'.csv';

        $fields = StudentField::orderBy('sort_order')->orderBy('id')->get()->filter->takesAnswer();

        return response()->streamDownload(function () use ($fields) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows Unicode correctly
            fputcsv($out, ['Name', 'Email', 'Phone', 'SSC Batch', 'Blood Group', 'Profession', 'Company', 'LinkedIn', 'Facebook', 'Status', 'Paid', 'Registered At',
                ...$fields->pluck('label')]);

            User::students()
                ->with('fieldValues')
                ->withPaidCount()
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
