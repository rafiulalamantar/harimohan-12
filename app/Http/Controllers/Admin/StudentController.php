<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StudentField;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $students = User::where('role', User::ROLE_STUDENT)
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->batch, fn ($q, $b) => $q->where('ssc_batch', $b))
            ->when($request->q, fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")))
            ->withCount(['payments as paid_count' => fn ($q) => $q->where('status', 'completed')])
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $batches = User::where('role', User::ROLE_STUDENT)->whereNotNull('ssc_batch')
            ->distinct()->orderByDesc('ssc_batch')->pluck('ssc_batch');

        return view('admin.students.index', compact('students', 'batches'));
    }

    public function show(User $student)
    {
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

        abort_if($student->isAdmin(), 403);

        if ($data['status'] === User::STATUS_REJECTED && $student->hasAnyCompletedPayment()) {
            return back()->with('error', "{$student->name} has already paid, so they cannot be rejected.");
        }

        $student->forceFill(['status' => $data['status']])->save();

        return back()->with('success', "{$student->name}'s status: {$data['status']}");
    }

    public function export(Request $request)
    {
        $filename = 'students-'.now()->format('Ymd-His').'.csv';

        $fields = StudentField::orderBy('sort_order')->orderBy('id')->get();

        return response()->streamDownload(function () use ($fields) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows Unicode correctly
            fputcsv($out, ['Name', 'Email', 'Phone', 'SSC Batch', 'Roll', 'Profession', 'Company', 'LinkedIn', 'Facebook', 'Status', 'Paid', 'Registered At',
                ...$fields->pluck('label')]);

            User::where('role', User::ROLE_STUDENT)
                ->with('fieldValues')
                ->withCount(['payments as paid_count' => fn ($q) => $q->where('status', 'completed')])
                ->orderBy('ssc_batch')->orderBy('name')
                ->chunk(500, function ($users) use ($out, $fields) {
                    foreach ($users as $u) {
                        $answers = $u->fieldAnswers();
                        fputcsv($out, [$u->name, $u->email, $u->phone, $u->ssc_batch, $u->roll_no,
                            $u->profession, $u->company_name, $u->linkedin_url, $u->facebook_url, $u->status, $u->paid_count > 0 ? 'Yes' : 'No', $u->created_at,
                            ...$fields->map(fn ($f) => $answers[$f->id] ?? '')]);
                    }
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
