<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkApproveTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_approves_ticked_students_at_once_and_is_recorded_as_reviewer(): void
    {
        $admin = User::factory()->admin()->create();
        [$first, $second] = User::factory()->pending()->count(2)->create();
        $untouched = User::factory()->pending()->create();

        $this->actingAs($admin)
            ->patch(route('admin.students.approve-many'), ['students' => [$first->id, $second->id]])
            ->assertSessionHas('success', 'Approved 2 students.');

        foreach ([$first, $second] as $student) {
            $this->assertSame(User::STATUS_APPROVED, $student->fresh()->status);
            $this->assertSame($admin->id, $student->fresh()->reviewed_by);
        }
        $this->assertSame(User::STATUS_PENDING, $untouched->fresh()->status);
    }

    public function test_bulk_approve_skips_already_approved_students_and_admin_accounts(): void
    {
        $admin = User::factory()->admin()->create();
        $approved = User::factory()->create(['status' => User::STATUS_APPROVED]);

        $this->actingAs($admin)
            ->patch(route('admin.students.approve-many'), ['students' => [$approved->id, $admin->id]])
            ->assertSessionHas('info', 'Those students were already approved.');

        $this->assertNull($approved->fresh()->reviewed_by);
    }

    public function test_bulk_approve_needs_at_least_one_student(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.students.approve-many'), [])
            ->assertSessionHasErrors(['students' => 'Tick at least one student to approve.']);
    }

    public function test_students_cannot_bulk_approve(): void
    {
        $pending = User::factory()->pending()->create();

        $this->actingAs(User::factory()->create())
            ->patch(route('admin.students.approve-many'), ['students' => [$pending->id]])
            ->assertForbidden();

        $this->assertSame(User::STATUS_PENDING, $pending->fresh()->status);
    }

    public function test_students_page_offers_bulk_approve_only_for_waiting_students(): void
    {
        $admin = User::factory()->admin()->create();
        $pending = User::factory()->pending()->create();
        $approved = User::factory()->create(['status' => User::STATUS_APPROVED]);

        $this->actingAs($admin)->get(route('admin.students.index'))
            ->assertOk()
            ->assertSee('Approve selected')
            ->assertSee('value="'.$pending->id.'" form="bulk-approve"', false)
            ->assertDontSee('value="'.$approved->id.'" form="bulk-approve"', false);
    }
}
