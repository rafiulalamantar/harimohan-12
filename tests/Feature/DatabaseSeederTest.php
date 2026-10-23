<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use Database\Seeders\DemoAlumniSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_seed_creates_admin_without_demo_student_or_payment(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

        $this->assertDatabaseHas('users', ['email' => 'admin@school.test', 'role' => User::ROLE_ADMIN, 'admin_level' => User::ADMIN_LEVEL_SUPER]);
        $this->assertDatabaseMissing('users', ['email' => 'student@school.test']);
        $this->assertDatabaseCount(Payment::class, 0);
    }

    public function test_local_seed_creates_demo_student_with_completed_payment(): void
    {
        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

        $student = User::where('email', 'student@school.test')->firstOrFail();
        $this->assertDatabaseHas('payments', ['user_id' => $student->id, 'status' => Payment::STATUS_COMPLETED]);
    }

    public function test_demo_alumni_seeder_adds_120_students_once(): void
    {
        $this->artisan('db:seed', ['--force' => true]);
        $this->artisan('db:seed', ['--class' => DemoAlumniSeeder::class, '--force' => true])->assertSuccessful();
        $this->artisan('db:seed', ['--class' => DemoAlumniSeeder::class, '--force' => true])->assertSuccessful();

        $demoStudents = User::where('email', 'like', 'alumni%@example.com');
        $this->assertSame(DemoAlumniSeeder::STUDENT_COUNT, $demoStudents->count());
        $this->assertGreaterThan(100, (clone $demoStudents)->where('status', User::STATUS_APPROVED)->count());
        $this->assertTrue((clone $demoStudents)->where('status', User::STATUS_PENDING)->exists());
    }

    public function test_demo_alumni_seeder_never_runs_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->artisan('db:seed', ['--class' => DemoAlumniSeeder::class, '--force' => true]);

        $this->assertDatabaseCount(User::class, 0);
    }
}
