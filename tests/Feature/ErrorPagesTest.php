<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_pages_show_a_friendly_message(): void
    {
        $this->get('/no-such-page')
            ->assertNotFound()
            ->assertSee("We couldn't find that page")
            ->assertSee('Go to homepage');
    }

    public function test_every_error_page_explains_what_happened_in_plain_words(): void
    {
        $titles = [
            '403' => "You don't have access to this page",
            '419' => 'This page expired',
            '429' => 'Too many tries',
            '500' => 'Something went wrong on our side',
            '503' => "We'll be right back",
        ];

        foreach ($titles as $code => $title) {
            $html = view("errors.{$code}", ['exception' => null])->render();

            $this->assertStringContainsString(e($title), $html, "errors.{$code}");
            $this->assertStringContainsString("ERROR {$code}", $html);
        }
    }

    public function test_pages_carry_the_offline_notice_and_vercel_serves_the_offline_files(): void
    {
        $this->get('/')->assertSee('data-network-status', false);

        $this->assertStringContainsString("You're offline", file_get_contents(public_path('offline.html')));
        $this->assertStringContainsString('offline.html', file_get_contents(public_path('sw.js')));

        $routes = json_decode(file_get_contents(base_path('vercel.json')), true)['routes'];
        $this->assertStringContainsString('sw\\.js', $routes[1]['src']);
        $this->assertStringContainsString('offline\\.html', $routes[1]['src']);
    }
}
