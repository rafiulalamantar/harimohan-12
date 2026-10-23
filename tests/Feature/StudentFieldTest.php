<?php

namespace Tests\Feature;

use App\Models\StudentField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\TestWith;
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
            ->assertSessionHas('success', 'Student field updated. Cleared 1 saved answer that no longer fit this field; those students will be asked to answer again.');

        $this->assertSame([$field->id => 'L'], $keptChoiceStudent->fresh()->fieldAnswers());
        $this->assertSame([$field->id => null], $removedChoiceStudent->fresh()->fieldAnswers());
        $this->assertSame(['Size'], $removedChoiceStudent->fresh()->missingRequiredFields()->pluck('label')->all());
    }

    public function test_changing_text_field_to_number_clears_non_numeric_answers(): void
    {
        $field = StudentField::factory()->create(['label' => 'Guests', 'type' => 'text']);
        $numericStudent = User::factory()->create();
        $textStudent = User::factory()->create();
        $numericStudent->saveFieldValues([$field->id => '3']);
        $textStudent->saveFieldValues([$field->id => 'three']);

        $this->actingAs(User::factory()->admin()->create())->put(route('admin.student-fields.update', $field), [
            'label' => 'Guests',
            'type' => 'number',
            'is_active' => '1',
        ])->assertSessionHas('success', 'Student field updated. Cleared 1 saved answer that no longer fit this field; those students will be asked to answer again.');

        $this->assertSame([$field->id => '3'], $numericStudent->fresh()->fieldAnswers());
        $this->assertSame([$field->id => null], $textStudent->fresh()->fieldAnswers());
    }

    public function test_dropdown_field_needs_choices(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.student-fields.store'), ['label' => 'Size', 'type' => 'select', 'options' => ''])
            ->assertSessionHasErrors('options');
    }

    #[TestWith(['radio'])]
    #[TestWith(['checkboxes'])]
    public function test_radio_and_checkbox_fields_need_choices(string $type): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.student-fields.store'), ['label' => 'Meals', 'type' => $type, 'options' => ''])
            ->assertSessionHasErrors(['options' => 'Add at least one choice for a dropdown, radio button or checkbox field.']);
    }

    public function test_admin_can_create_checkbox_field(): void
    {
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.student-fields.store'), [
            'label' => 'Meals',
            'type' => 'checkboxes',
            'options' => "Lunch\nDinner",
            'is_active' => '1',
        ])->assertRedirect(route('admin.student-fields.index'));

        $this->assertSame(['Lunch', 'Dinner'], StudentField::sole()->options);
        $this->get(route('admin.student-fields.index'))->assertSee('Lunch, Dinner');
    }

    public function test_student_saves_and_clears_checkbox_answers(): void
    {
        $field = StudentField::factory()->checkboxes()->create(['label' => 'Meals']);
        $student = User::factory()->create();

        $this->actingAs($student)->put(route('portal.profile.update'), $this->profileData($student, [$field->id => ['Lunch', 'Cultural night']]))
            ->assertSessionHas('success');
        $this->assertSame([$field->id => "Lunch\nCultural night"], $student->fresh()->fieldAnswers());

        $this->actingAs($student)->get(route('portal.profile'))
            ->assertSee('value="Lunch" checked', false)
            ->assertDontSee('value="Dinner" checked', false)
            ->assertSee('Only admins see this answer.');

        $this->actingAs($student)->put(route('portal.profile.update'), $this->profileData($student))
            ->assertSessionHas('success');
        $this->assertSame([$field->id => null], $student->fresh()->fieldAnswers());
    }

    public function test_profile_rejects_unknown_checkbox_choice_and_missing_required_choice(): void
    {
        $field = StudentField::factory()->checkboxes()->required()->create(['label' => 'Meals']);
        $student = User::factory()->create();

        $this->actingAs($student)->put(route('portal.profile.update'), $this->profileData($student, [$field->id => ['Lunch', 'Breakfast']]))
            ->assertSessionHasErrors(["fields.{$field->id}.1" => 'The selected Meals is invalid.']);

        $this->actingAs($student)->put(route('portal.profile.update'), $this->profileData($student))
            ->assertSessionHasErrors(["fields.{$field->id}" => 'The Meals field is required.']);

        $this->assertSame([], $student->fresh()->fieldAnswers());
    }

    public function test_radio_field_accepts_only_its_choices(): void
    {
        $field = StudentField::factory()->create(['label' => 'Attending', 'type' => 'radio', 'options' => ['Yes', 'No']]);
        $student = User::factory()->create();

        $this->actingAs($student)->put(route('portal.profile.update'), $this->profileData($student, [$field->id => 'Maybe']))
            ->assertSessionHasErrors("fields.{$field->id}");

        $this->actingAs($student)->put(route('portal.profile.update'), $this->profileData($student, [$field->id => 'Yes']))
            ->assertSessionHas('success');
        $this->assertSame([$field->id => 'Yes'], $student->fresh()->fieldAnswers());
    }

    public function test_date_field_rejects_invalid_dates_and_admin_sees_it_spelled_out(): void
    {
        $field = StudentField::factory()->create(['label' => 'Arrival date', 'type' => 'date']);
        $student = User::factory()->create();

        $this->actingAs($student)->put(route('portal.profile.update'), $this->profileData($student, [$field->id => '2026-02-30']))
            ->assertSessionHasErrors("fields.{$field->id}");

        $this->actingAs($student)->put(route('portal.profile.update'), $this->profileData($student, [$field->id => '2026-12-05']))
            ->assertSessionHas('success');

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.students.show', $student))->assertSee('05 Dec 2026');
    }

    public function test_removing_a_checkbox_choice_clears_answers_that_used_it(): void
    {
        $field = StudentField::factory()->checkboxes(['Lunch', 'Dinner'])->create(['label' => 'Meals']);
        $keptChoicesStudent = User::factory()->create();
        $removedChoiceStudent = User::factory()->create();
        $keptChoicesStudent->saveFieldValues([$field->id => 'Lunch']);
        $removedChoiceStudent->saveFieldValues([$field->id => "Lunch\nDinner"]);

        $this->actingAs(User::factory()->admin()->create())->put(route('admin.student-fields.update', $field), [
            'label' => 'Meals',
            'type' => 'checkboxes',
            'options' => 'Lunch',
            'is_active' => '1',
        ])->assertSessionHas('success', 'Student field updated. Cleared 1 saved answer that no longer fit this field; those students will be asked to answer again.');

        $this->assertSame([$field->id => 'Lunch'], $keptChoicesStudent->fresh()->fieldAnswers());
        $this->assertSame([$field->id => null], $removedChoiceStudent->fresh()->fieldAnswers());
    }

    public function test_alumni_directory_never_shows_field_answers(): void
    {
        $field = StudentField::factory()->create(['label' => 'Polo T-shirt size']);
        User::factory()->create()->saveFieldValues([$field->id => 'XXL']);

        $this->get(route('home'))->assertDontSee('Polo T-shirt size')->assertDontSee('XXL');
        $this->actingAs(User::factory()->create())->get(route('home'))->assertDontSee('Polo T-shirt size')->assertDontSee('XXL');
    }

    public function test_students_page_lists_answers_and_choice_totals(): void
    {
        $size = StudentField::factory()->select(['M', 'XL'])->create(['label' => 'Polo T-shirt size']);
        $meals = StudentField::factory()->checkboxes()->create(['label' => 'Meals']);
        User::factory()->create(['name' => 'Karim'])->saveFieldValues([$size->id => 'XL', $meals->id => "Lunch\nDinner"]);
        User::factory()->create(['name' => 'Rahim'])->saveFieldValues([$size->id => 'XL', $meals->id => 'Lunch']);
        User::factory()->create(['name' => 'Salma'])->saveFieldValues([$size->id => 'M']);

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.students.index'))
            ->assertSee('Polo T-shirt size')
            ->assertSee('Lunch, Dinner')
            ->assertSeeInOrder(['Answer totals', 'Polo T-shirt size:', 'M', '<strong>1</strong>', 'XL', '<strong>2</strong>', 'Meals:', 'Lunch', '<strong>2</strong>', 'Dinner', '<strong>1</strong>', 'Cultural night', '<strong>0</strong>'], false);
    }

    public function test_students_page_filters_by_dropdown_and_checkbox_answers(): void
    {
        $size = StudentField::factory()->select(['M', 'XL'])->create();
        $meals = StudentField::factory()->checkboxes()->create();
        User::factory()->create(['email' => 'karim@example.com'])->saveFieldValues([$size->id => 'XL', $meals->id => "Lunch\nDinner"]);
        User::factory()->create(['email' => 'rahim@example.com'])->saveFieldValues([$size->id => 'XL', $meals->id => 'Lunch']);
        User::factory()->create(['email' => 'salma@example.com'])->saveFieldValues([$size->id => 'M', $meals->id => 'Dinner']);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.students.index', ['answer' => [$size->id => 'XL', $meals->id => 'Dinner']]))
            ->assertSee('karim@example.com')->assertDontSee('rahim@example.com')->assertDontSee('salma@example.com');

        $this->actingAs($admin)->get(route('admin.students.index', ['answer' => [$size->id => 'Huge']]))
            ->assertSee('karim@example.com')->assertSee('rahim@example.com')->assertSee('salma@example.com');
    }

    public function test_image_only_field_needs_an_image_and_is_never_required(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->admin()->create());

        $this->post(route('admin.student-fields.store'), ['label' => 'T-shirt size chart', 'type' => 'image', 'is_active' => '1'])
            ->assertSessionHasErrors(['image' => 'Upload the image students should see.']);

        $this->post(route('admin.student-fields.store'), [
            'label' => 'T-shirt size chart',
            'type' => 'image',
            'image' => UploadedFile::fake()->image('chart.png'),
            'is_required' => '1',
            'is_active' => '1',
        ])->assertRedirect(route('admin.student-fields.index'));

        $field = StudentField::sole();
        $this->assertFalse($field->is_required);

        $this->put(route('admin.student-fields.update', $field), ['label' => 'Size chart', 'type' => 'image', 'is_active' => '1'])
            ->assertSessionDoesntHaveErrors();
        $this->put(route('admin.student-fields.update', $field), ['label' => 'Size chart', 'type' => 'image', 'is_active' => '1', 'remove_image' => '1'])
            ->assertSessionHasErrors('image');
        $this->assertNotNull($field->fresh()->image);
    }

    public function test_students_see_image_only_field_in_portal_without_answering(): void
    {
        $chart = StudentField::factory()->create(['label' => 'T-shirt size chart', 'type' => 'image', 'image' => 'student-fields/chart.png']);
        $student = User::factory()->create();

        $this->actingAs($student)->get(route('portal.profile'))
            ->assertSee('T-shirt size chart')->assertSee($chart->imageUrl())
            ->assertDontSee('name="fields['.$chart->id.']"', false);
        $this->actingAs($student)->get(route('portal.dashboard'))->assertDontSee($chart->imageUrl());

        $this->actingAs($student)->put(route('portal.profile.update'), $this->profileData($student, [$chart->id => 'x']))
            ->assertSessionHas('info', 'No changes to save.');
        $this->assertSame([], $student->fresh()->fieldAnswers());

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.students.index'))->assertDontSee('T-shirt size chart');
    }

    public function test_inactive_image_only_field_is_hidden_from_students(): void
    {
        $chart = StudentField::factory()->inactive()->create(['label' => 'T-shirt size chart', 'type' => 'image', 'image' => 'student-fields/chart.png']);
        $student = User::factory()->create();

        $this->actingAs($student)->get(route('portal.profile'))->assertDontSee($chart->imageUrl());
        $this->get(route('register'))->assertDontSee($chart->imageUrl());
    }

    public function test_saving_an_unchanged_profile_says_nothing_changed(): void
    {
        $field = StudentField::factory()->select()->create();
        $student = User::factory()->create();
        $student->saveFieldValues([$field->id => 'M']);

        $this->actingAs($student)->put(route('portal.profile.update'), $this->profileData($student, [$field->id => 'M']))
            ->assertSessionHas('info', 'No changes to save.')
            ->assertSessionMissing('success');

        $this->actingAs($student)->put(route('portal.profile.update'), $this->profileData($student, [$field->id => 'L']))
            ->assertSessionHas('success', 'Profile updated.');
        $this->assertSame([$field->id => 'L'], $student->fresh()->fieldAnswers());
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
            ->assertSessionHas('info', 'No changes to save.');

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

    public function test_export_lists_checkbox_answers_separated_by_commas(): void
    {
        $field = StudentField::factory()->checkboxes()->create(['label' => 'Meals']);
        User::factory()->create()->saveFieldValues([$field->id => "Lunch\nDinner"]);

        $csv = $this->actingAs(User::factory()->admin()->create())->get(route('admin.students.export'))->streamedContent();

        $this->assertStringContainsString('"Lunch, Dinner"', $csv);
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
        $this->actingAs(User::factory()->create())->get(route('admin.student-fields.index'))->assertRedirect(route('portal.dashboard'));
    }
}
