<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\Payment;
use App\Models\ReunionEvent;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrNew(['email' => env('ADMIN_EMAIL', 'admin@school.test')]);
        $admin->forceFill([
            'name' => env('ADMIN_NAME', 'Event Admin'),
            'phone' => env('ADMIN_PHONE', '01700000000'),
            'password' => env('ADMIN_PASSWORD', 'ChangeMe@123'),
            'role' => User::ROLE_ADMIN,
            'admin_level' => User::ADMIN_LEVEL_SUPER,
            'status' => User::STATUS_APPROVED,
        ])->save();

        $event = ReunionEvent::firstOrCreate(
            ['title' => 'Grand Reunion 2027'],
            [
                'description' => 'A grand reunion for ex-students of every batch. The registration fee covers lunch, a T-shirt, and a souvenir.',
                'event_date' => now()->addMonths(3)->toDateString(),
                'venue' => 'School Field',
                'fee' => 1500,
                'payment_deadline' => now()->addMonths(2)->toDateString(),
                'is_active' => true,
            ]
        );

        Announcement::firstOrCreate(
            ['title' => 'Reunion registration is now open!'],
            [
                'user_id' => $admin->id,
                'body' => "The registration fee is {$event->fee} BDT. bKash payment from the portal is coming soon. Deadline: {$event->payment_deadline->format('d M Y')}.",
                'is_pinned' => true,
                'published_at' => now(),
            ]
        );

        // The demo student has a public password and a fake payment, so it must never reach a live database.
        if (app()->isProduction()) {
            return;
        }

        $student = User::firstOrNew(['email' => 'student@school.test']);
        $student->forceFill([
            'name' => 'Test Student',
            'photo' => Storage::disk('public')->exists('photos/test-student.png') ? 'photos/test-student.png' : null,
            'phone' => '01811111111',
            'ssc_batch' => 2012,
            'roll_no' => '42',
            'profession' => 'Software Engineer',
            'company_name' => 'Grameenphone',
            'linkedin_url' => 'https://www.linkedin.com/in/test-student',
            'facebook_url' => 'https://www.facebook.com/test.student',
            'present_address' => 'Dhanmondi, Dhaka',
            'password' => 'Student@123',
            'role' => User::ROLE_STUDENT,
            'status' => User::STATUS_APPROVED,
        ])->save();

        Payment::firstOrCreate(
            ['user_id' => $student->id, 'reunion_event_id' => $event->id, 'status' => Payment::STATUS_COMPLETED],
            [
                'amount' => $event->fee,
                'invoice_no' => Payment::newInvoiceNo(),
                'method' => 'bkash',
                'bkash_payment_id' => 'TEST'.strtoupper(Str::random(10)),
                'trx_id' => 'TRX'.strtoupper(Str::random(7)),
                'payer_msisdn' => $student->phone,
                'paid_at' => now(),
            ]
        );
    }
}
