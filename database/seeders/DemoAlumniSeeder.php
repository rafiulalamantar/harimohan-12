<?php

namespace Database\Seeders;

use App\Models\Payment;
use App\Models\ReunionEvent;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * 120 realistic alumni for local development, so the directory, admin lists and paging feel like the live site.
 *
 * Run with `php artisan db:seed --class=DemoAlumniSeeder`. Safe to re-run (students are matched by email),
 * and it refuses to run in production. Every demo student logs in with the password "password".
 */
class DemoAlumniSeeder extends Seeder
{
    public const STUDENT_COUNT = 120;

    private const FIRST_NAMES = [
        'Rafiul', 'Tanvir', 'Mahmudul', 'Shakil', 'Sabbir', 'Arif', 'Nayeem', 'Rakib', 'Fahim', 'Imran',
        'Sohel', 'Rashed', 'Mehedi', 'Tareq', 'Nafis', 'Raihan', 'Sajid', 'Ashik', 'Habib', 'Kamrul',
        'Nusrat', 'Farzana', 'Tasnim', 'Sadia', 'Jannatul', 'Sumaiya', 'Rumana', 'Afrin', 'Nabila', 'Mim',
        'Shirin', 'Tahmina', 'Lamia', 'Sharmin', 'Fatema', 'Anika', 'Ishrat', 'Moumita', 'Rupa', 'Priyanka',
    ];

    private const LAST_NAMES = [
        'Hossain', 'Rahman', 'Islam', 'Ahmed', 'Alam', 'Uddin', 'Khan', 'Chowdhury', 'Sarker', 'Mia',
        'Haque', 'Karim', 'Sikder', 'Talukder', 'Biswas', 'Das', 'Saha', 'Roy', 'Akter', 'Begum',
    ];

    private const JOBS = [
        ['Software Engineer', 'Brain Station 23'], ['Senior Software Engineer', 'Enosis Solutions'], ['QA Engineer', 'Therap BD'],
        ['Assistant Manager', 'BRAC Bank'], ['Senior Officer', 'Dutch-Bangla Bank'], ['Executive Officer', 'Islami Bank Bangladesh'],
        ['Lecturer', 'Jahangirnagar University'], ['Assistant Professor', 'Rajshahi University'], ['Teacher', 'Harimohan Govt. High School'],
        ['Medical Officer', 'Dhaka Medical College Hospital'], ['Doctor', 'Square Hospital'], ['Pharmacist', 'Beximco Pharmaceuticals'],
        ['Civil Engineer', 'Roads and Highways Department'], ['Electrical Engineer', 'Bangladesh Power Development Board'],
        ['Assistant Superintendent of Police', 'Bangladesh Police'], ['Upazila Agriculture Officer', 'Department of Agricultural Extension'],
        ['Product Manager', 'bKash Limited'], ['Business Analyst', 'Grameenphone'], ['Network Engineer', 'Robi Axiata'],
        ['Merchandiser', 'Beximco Textiles'], ['Business Owner', null], ['Freelance Graphic Designer', null], ['Entrepreneur', null],
    ];

    private const PLACES = [
        'Chapainawabganj Sadar', 'Shibganj, Chapainawabganj', 'Rajshahi', 'Bogura', 'Mirpur, Dhaka', 'Dhanmondi, Dhaka',
        'Uttara, Dhaka', 'Mohammadpur, Dhaka', 'Chattogram', 'Khulna', 'Sylhet', 'Gazipur',
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('DemoAlumniSeeder only adds fake students to local databases; it never runs in production.');

            return;
        }

        mt_srand(1895); // Same students on every run.
        $password = Hash::make('password');
        $event = ReunionEvent::where('is_active', true)->latest('event_date')->first();

        for ($number = 1; $number <= self::STUDENT_COUNT; $number++) {
            $name = $this->pick(self::FIRST_NAMES).' '.$this->pick(self::LAST_NAMES);
            [$profession, $company] = mt_rand(1, 10) <= 8 ? $this->pick(self::JOBS) : [null, null];
            $handle = Str::slug($name, '.').$number;

            $student = User::firstOrNew(['email' => sprintf('alumni%03d@example.com', $number)]);
            $student->forceFill([
                'name' => $name,
                'phone' => '0195'.sprintf('%07d', $number),
                'ssc_batch' => $this->batch(),
                'blood_group' => mt_rand(1, 10) <= 7 ? $this->pick(User::BLOOD_GROUPS) : null,
                'profession' => $profession,
                'company_name' => $company,
                'present_address' => mt_rand(1, 10) <= 7 ? $this->pick(self::PLACES) : null,
                'linkedin_url' => mt_rand(1, 10) <= 4 ? "https://www.linkedin.com/in/{$handle}" : null,
                'facebook_url' => mt_rand(1, 10) <= 6 ? "https://www.facebook.com/{$handle}" : null,
                'password' => $password,
                'role' => User::ROLE_STUDENT,
                'status' => match (true) {
                    $number % 40 === 0 => User::STATUS_REJECTED,
                    $number % 12 === 0 => User::STATUS_PENDING,
                    default => User::STATUS_APPROVED,
                },
                'created_at' => now()->subDays(mt_rand(0, 30))->subMinutes(mt_rand(0, 1439)),
            ])->save();

            // About a third of approved members have already paid for the active event.
            if ($event && $student->isApproved() && mt_rand(1, 3) === 1) {
                Payment::firstOrCreate(
                    ['user_id' => $student->id, 'reunion_event_id' => $event->id, 'status' => Payment::STATUS_COMPLETED],
                    [
                        'amount' => $event->fee,
                        'invoice_no' => Payment::newInvoiceNo(),
                        'method' => 'bkash',
                        'bkash_payment_id' => 'DEMO'.strtoupper(Str::random(10)),
                        'trx_id' => 'TRX'.strtoupper(Str::random(7)),
                        'payer_msisdn' => $student->phone,
                        'paid_at' => now()->subDays(mt_rand(0, 14)),
                    ]
                );
            }
        }
    }

    /** Mostly the 2012 batch, like the live site, with a spread of neighbouring years. */
    private function batch(): int
    {
        return mt_rand(1, 10) <= 7 ? 2012 : $this->pick([2008, 2009, 2010, 2011, 2013, 2014, 2015, 2016]);
    }

    /**
     * @template T
     *
     * @param  array<int, T>  $items
     * @return T
     */
    private function pick(array $items): mixed
    {
        return $items[mt_rand(0, count($items) - 1)];
    }
}
