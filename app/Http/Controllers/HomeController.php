<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\ReunionEvent;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /** Alumni cards shown per directory page. */
    private const DIRECTORY_PAGE_SIZE = 24;

    /**
     * Public welcome page with a searchable alumni directory.
     *
     * Phone numbers are only shown to (and searchable by) approved members.
     */
    public function __invoke(Request $request): View
    {
        $canSeeContacts = (bool) $request->user()?->isApproved();
        $term = $request->string('q')->trim()->value();
        $batch = $request->integer('batch') ?: null;

        $approvedStudents = User::where('role', User::ROLE_STUDENT)->where('status', User::STATUS_APPROVED);

        $columns = ['id', 'name', 'photo', 'ssc_batch', 'profession', 'company_name', 'linkedin_url'];
        if ($canSeeContacts) {
            $columns[] = 'phone';
        }

        $students = (clone $approvedStudents)
            ->when($term !== '', fn ($query) => $query->where(fn ($where) => $where
                ->where('name', 'like', "%{$term}%")
                ->orWhere('profession', 'like', "%{$term}%")
                ->orWhere('company_name', 'like', "%{$term}%")
                ->orWhere('ssc_batch', 'like', "%{$term}%")
                ->when($canSeeContacts, fn ($contactWhere) => $contactWhere->orWhere('phone', 'like', "%{$term}%"))))
            ->when($batch, fn ($query) => $query->where('ssc_batch', $batch))
            ->orderByDesc('ssc_batch')->orderBy('name')
            ->paginate(self::DIRECTORY_PAGE_SIZE, $columns)
            ->withQueryString()
            ->fragment('alumni');

        return view('welcome', [
            'settings' => Setting::values(),
            'event' => ReunionEvent::current(),
            'announcements' => Announcement::feed()->take(3)->get(),
            'students' => $students,
            'totalAlumni' => (clone $approvedStudents)->count(),
            'batches' => (clone $approvedStudents)->whereNotNull('ssc_batch')->distinct()->orderByDesc('ssc_batch')->pluck('ssc_batch'),
            'search' => $term,
            'selectedBatch' => $batch,
            'canSeeContacts' => $canSeeContacts,
        ]);
    }
}
