<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\ReunionEvent;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $payments = Payment::with(['user', 'event'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->event, fn ($q, $e) => $q->where('reunion_event_id', $e))
            ->when($request->q, fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('trx_id', 'like', "%{$term}%")
                ->orWhere('invoice_no', 'like', "%{$term}%")
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%"))))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('admin.payments.index', [
            'payments' => $payments,
            'events' => ReunionEvent::withTrashed()->orderByDesc('event_date')->get(['id', 'title']),
        ]);
    }

    public function export(Request $request)
    {
        $filename = 'payments-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($request) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Invoice', 'Name', 'Phone', 'SSC Batch', 'Event', 'Amount', 'Status', 'bKash TrxID', 'Payer', 'Paid At']);

            Payment::with(['user', 'event'])
                ->when($request->status, fn ($q, $s) => $q->where('status', $s))
                ->when($request->event, fn ($q, $e) => $q->where('reunion_event_id', $e))
                ->orderBy('id')
                ->chunk(500, function ($rows) use ($out) {
                    foreach ($rows as $p) {
                        fputcsv($out, [$p->invoice_no, $p->user?->name, $p->user?->phone, $p->user?->ssc_batch,
                            $p->event?->title, $p->amount, $p->status, $p->trx_id, $p->payer_msisdn, $p->paid_at]);
                    }
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
