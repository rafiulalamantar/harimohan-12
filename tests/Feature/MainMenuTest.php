<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MainMenuTest extends TestCase
{
    use RefreshDatabase;

    private function memoriesMenuLink(): string
    {
        return 'href="'.route('memories.index').'"';
    }

    public function test_guest_sees_memories_link_left_of_login_on_welcome_page(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSeeInOrder([
                $this->memoriesMenuLink(),
                'Memories ⚡',
                'href="'.route('login').'"',
                'href="'.route('register').'"',
            ], false);

        $this->get(route('memories.index'))->assertOk();
    }

    public function test_member_menu_links_to_memories_page(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee($this->memoriesMenuLink(), false);

        $this->actingAs($member)->get(route('memories.index'))->assertOk();
    }

    public function test_admin_menu_links_to_memories_page(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee($this->memoriesMenuLink(), false);

        $this->actingAs($admin)->get(route('memories.index'))->assertOk();
    }

    public function test_student_admin_sees_portal_menu_on_portal_pages_and_admin_menu_elsewhere(): void
    {
        $studentAdmin = User::factory()->studentAdmin()->create();

        $this->actingAs($studentAdmin)->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee('Admin panel')
            ->assertDontSee('Student fields');

        $this->actingAs($studentAdmin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Student fields')
            ->assertSee('My portal');
    }
}
