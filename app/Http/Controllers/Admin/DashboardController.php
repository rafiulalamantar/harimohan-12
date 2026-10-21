<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\ReunionEvent;
use App\Models\User;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $event = ReunionEvent::current();
        $students = User::where('role', User::ROLE_STUDENT);

        return view('admin.dashboard', [
            'event' => $event,
            'totalStudents' => (clone $students)->count(),
            'pendingStudents' => (clone $students)->where('status', User::STATUS_PENDING)->count(),
            'paidCount' => $event ? $event->payments()->where('status', Payment::STATUS_COMPLETED)->count() : 0,
            'collected' => $event ? $event->payments()->where('status', Payment::STATUS_COMPLETED)->sum('amount') : 0,
            'byBatch' => (clone $students)->selectRaw('ssc_batch, count(*) as total')
                ->groupBy('ssc_batch')->orderByDesc('ssc_batch')->get(),
            'newStudents' => (clone $students)->latest()->take(8)->get(),
            'recentPayments' => Payment::with('user')->where('status', Payment::STATUS_COMPLETED)
                ->latest('paid_at')->take(8)->get(),
        ]);
    }
}
