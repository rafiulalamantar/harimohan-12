<?php

namespace Tests\Feature;

use App\Models\StudentField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentFieldTest extends TestCase
{
    use RefreshDatabase;

    private function profileData(User $student, array $fields = []): array
    {
        return ['name' => $student->name, 'phone' => $student->phone, 'fields' => $fields];
    }

    public function test_admin_can_create_dropdown_field_with_reference_image(): void
    {
        Storage::fake('public');

        $this->actingAs(User::factory()->admin()->create())->post(route('admin.student-fields.store'), [
            'label' => 'Polo T-shirt size',
            'type' => 'select',
            'options' => "S\nM\nL\nXL\n\nM",
            'help_text' => 'Check the chart.',
            'image' => UploadedFile::fake()->image('chart.png'),
            'is_required' => '1',
            'is_active' => '1',
        ])->assertRedirect(route('admin.student-fields.index'));

        $field = StudentField::sole();
        $this->assertSame(['S', 'M', 'L', 'XL'], $field->options);
        $this->assertTrue($field->is_required);
        Storage::disk('public')->assertExists($field->image);

        $this->get(route('admin.student-fields.index'))->assertSee('Polo T-shirt size')->assertSee('S, M, L, XL');
        $this->get(route('admin.student-fields.edit', $field))->assertOk()->assertSee($field->imageUrl());
        $this->get(route('admin.student-fields.create'))->assertOk();
    }

    public function test_dropdown_choice_may_contain_a_comma(): void
    {
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.student-fields.store'), [
            'label' => 'Pickup point',
            'type' => 'select',
            'options' => "Dhaka, Mirpur\nChattogram",
            'is_active' => '1',
        ])->assertRedirect(route('admin.student-fields.index'));

        $this->assertSame(['Dhaka, Mirpur', 'Chattogram'], StudentField::sole()->options);
    }

    public function test_removing_a_dropdown_choice_clears_answers_that_used_it(): void
    {
        $field = StudentField::factory()->select(['M', 'L'])->required()->create(['label' => 'Size']);
        $keptChoiceStudent = User::factory()->create();
        $removedChoiceStudent = User::factory()->create();
        $keptChoiceStudent->saveFieldValues([$field->id => 'L']);
        $removedChoiceStudent->saveFieldValues([$field->id => 'M']);

        $this->actingAs(User::factory()->admin()->create())->put(route('admin.student-fields.update', $field), [
            'label' => 'Size',
            'type' => 'select',
            'options' => "Medium\nL",
            'is_required' => '1',
            'is_active' => '1',
        ])->assertRedirect(route('admin.student-fields.index'))
            ->assertSessionHas('success', 'Student field updated. Cleared 1 saved answer that used a removed choice; those students will be asked to choose again.');

        $this->assertSame([$field->id => 'L'], $keptChoiceStudent->fresh()->fieldAnswers());
        $this->assertSame([$field->id => null], $removedChoiceStudent->fresh()->fieldAnswers());
        $this->assertSame(['Size'], $removedChoiceStudent->fresh()->missingRequiredFields()->pluck('label')->all());
    }

    public function test_dropdown_field_needs_choices(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.student-fields.store'), ['label' => 'Size', 'type' => 'select', 'options' => ''])
            ->assertSessionHasErrors('options');
    }

    public function test_student_sees_field_with_image_on_profile_and_saves_answer(): void
    {
        $field = StudentField::factory()->select()->required()->create(['label' => 'Polo T-shirt size', 'image' => 'student-fields/chart.png']);
        $student = User::factory()->create();

        $this->actingAs($student)->get(route('portal.profile'))
            ->assertSee('Polo T-shirt size')->assertSee($field->imageUrl());

        $this->actingAs($student)->put(route('portal.profile.update'), $this->profileData($student, [$field->id => 'XL']))
            ->assertSessionHas('success');

        $this->assertSame([$field->id => 'XL'], $student->fresh()->fieldAnswers());
    }

    public function test_profile_rejects_missing_required_answer_and_invalid_choice(): void
    {
        $field = StudentField::factory()->select()->required()->create(['label' => 'Polo T-shirt size']);
        $student = User::factory()->create();

        $this->actingAs($student)->put(route('portal.profile.update'), $this->profileData($student))
            ->assertSessionHasErrors(['fields.'.$field->id => 'The Polo T-shirt size field is required.']);

        $this->actingAs($student)->put(route('portal.profile.update'), $this->profileData($student, [$field->id => 'Huge']))
            ->assertSessionHasErrors('fields.'.$field->id);

        $this->assertSame([], $student->fresh()->fieldAnswers());
    }

    public function test_inactive_and_unknown_fields_are_not_shown_or_saved(): void
    {
        $inactive = StudentField::factory()->inactive()->create(['label' => 'Old question']);
        $student = User::factory()->create();

        $this->actingAs($student)->get(route('portal.profile'))->assertDontSee('Old question');

        $this->actingAs($student)->put(route('portal.profile.update'), $this->profileData($student, [$inactive->id => 'x', 999 => 'y']))
            ->assertSessionHas('success');

        $this->assertSame([], $student->fresh()->fieldAnswers());
    }

    public function test_registration_saves_field_answers(): void
    {
        $field = StudentField::factory()->create(['label' => 'Chest (inches)', 'type' => 'number']);

        $this->get(route('register'))->assertSee('Chest (inches)');

        $this->post(route('register'), [
            'name' => 'Rahim', 'email' => 'rahim@example.com', 'phone' => '01712345678', 'ssc_batch' => 2010,
            'password' => 'secret123', 'password_confirmation' => 'secret123', 'fields' => [$field->id => '40'],
        ])->assertRedirect(route('portal.dashboard'));

        $this->assertSame([$field->id => '40'], User::where('email', 'rahim@example.com')->sole()->fieldAnswers());
    }

    public function test_dashboard_reminds_students_with_missing_required_fields(): void
    {
        $field = StudentField::factory()->required()->create(['label' => 'Polo T-shirt size']);
        $student = User::factory()->create();

        $this->actingAs($student)->get(route('portal.dashboard'))->assertSee('Please complete your profile: Polo T-shirt size.');

        $student->saveFieldValues([$field->id => 'M']);

        $this->actingAs($student)->get(route('portal.dashboard'))->assertDontSee('Please complete your profile');
    }

    public function test_admin_sees_answers_on_student_page_and_in_export(): void
    {
        $field = StudentField::factory()->create(['label' => 'Polo T-shirt size']);
        $student = User::factory()->create();
        $student->saveFieldValues([$field->id => 'XXL']);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.students.show', $student))->assertSee('Polo T-shirt size')->assertSee('XXL');

        $csv = $this->actingAs($admin)->get(route('admin.students.export'))->streamedContent();
        $this->assertStringContainsString('"Polo T-shirt size"', $csv);
        $this->assertStringContainsString('XXL', $csv);
    }

    public function test_deleting_field_removes_its_answers(): void
    {
        $field = StudentField::factory()->create();
        User::factory()->create()->saveFieldValues([$field->id => 'M']);

        $this->actingAs(User::factory()->admin()->create())->delete(route('admin.student-fields.destroy', $field));

        $this->assertModelMissing($field);
        $this->assertDatabaseCount('student_field_values', 0);
    }

    public function test_student_cannot_manage_fields(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.student-fields.index'))->assertForbidden();
    }
}
