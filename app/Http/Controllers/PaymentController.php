<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\ReunionEvent;
use App\Services\BkashService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaymentController extends Controller
{
    public function __construct(private BkashService $bkash) {}

    /** Student clicks "Pay with bKash". */
    public function pay(Request $request, ReunionEvent $event)
    {
        $user = $request->user();

        if (! $user->isApproved()) {
            return back()->with('error', 'You can pay once an admin approves your registration.');
        }
        if (! $event->isPaymentOpen()) {
            return back()->with('error', 'Payments for this event are closed.');
        }
        if ($user->hasPaidFor($event)) {
            return back()->with('success', 'You have already paid.');
        }

        $payment = Payment::create([
            'user_id' => $user->id,
            'reunion_event_id' => $event->id,
            'amount' => $event->fee,
            'invoice_no' => Payment::newInvoiceNo(),
            'status' => Payment::STATUS_INITIATED,
        ]);

        try {
            $data = $this->bkash->createPayment(
                amount: number_format((float) $payment->amount, 2, '.', ''),
                invoiceNo: $payment->invoice_no,
                payerReference: $user->phone,
                callbackUrl: route('payment.callback'),
            );
        } catch (Throwable $e) {
            $payment->update(['status' => Payment::STATUS_FAILED, 'gateway_response' => ['error' => $e->getMessage()]]);

            return back()->with('error', $e->getMessage());
        }

        $payment->update([
            'bkash_payment_id' => $data['paymentID'],
            'gateway_response' => $data,
        ]);

        return redirect()->away($data['bkashURL']);
    }

    /**
     * bKash redirects here: ?paymentID=...&status=success|failure|cancel
     * Not behind auth — the session may have expired while the user was on bKash.
     */
    public function callback(Request $request)
    {
        $paymentId = (string) $request->query('paymentID');
        $status = (string) $request->query('status');

        $payment = Payment::where('bkash_payment_id', $paymentId)->first();

        if (! $payment) {
            return $this->finish(null, 'error', 'Payment not found.');
        }

        // Lock the row so a double refresh can't execute the same payment twice.
        $result = DB::transaction(function () use ($paymentId, $status) {
            $payment = Payment::whereKey(Payment::where('bkash_payment_id', $paymentId)->value('id'))
                ->lockForUpdate()
                ->first();

            if ($payment->status === Payment::STATUS_COMPLETED) {
                return [$payment, 'success', 'Payment is already complete.'];
            }

            if ($status !== 'success') {
                $payment->update(['status' => $status === 'cancel' ? Payment::STATUS_CANCELLED : Payment::STATUS_FAILED]);

                return [$payment, 'error', $status === 'cancel' ? 'Payment was cancelled.' : 'Payment failed. Please try again.'];
            }

            $data = $this->bkash->executePayment($paymentId);

            // Execute can time out or report "already executed" — confirm with a status query.
            if (($data['transactionStatus'] ?? null) !== 'Completed') {
                $query = $this->bkash->queryPayment($paymentId);
                if (($query['transactionStatus'] ?? null) === 'Completed') {
                    $data = $query;
                }
            }

            $completed = ($data['transactionStatus'] ?? null) === 'Completed'
                && $this->sameAmount((string) ($data['amount'] ?? '0'), (string) $payment->amount)
                && ($data['merchantInvoiceNumber'] ?? $payment->invoice_no) === $payment->invoice_no;

            if (! $completed) {
                Log::warning('bKash payment not completed', ['payment' => $payment->id, 'response' => $data]);
                $payment->update(['status' => Payment::STATUS_FAILED, 'gateway_response' => $data]);

                return [$payment, 'error', $data['statusMessage'] ?? $data['errorMessage'] ?? 'Could not verify the payment.'];
            }

            $payment->update([
                'status' => Payment::STATUS_COMPLETED,
                'trx_id' => $data['trxID'] ?? null,
                'payer_msisdn' => $data['customerMsisdn'] ?? null,
                'gateway_response' => $data,
                'paid_at' => now(),
            ]);

            return [$payment, 'success', 'Payment successful! TrxID: '.($data['trxID'] ?? '-')];
        });

        return $this->finish(...$result);
    }

    private function finish(?Payment $payment, string $type, string $message)
    {
        return redirect()->route('portal.payments')->with($type, $message);
    }

    /** Compare two money strings to the paisa without float drift. */
    private function sameAmount(string $a, string $b): bool
    {
        return (int) round((float) $a * 100) === (int) round((float) $b * 100);
    }
}
