<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FAQPageTest extends TestCase
{
    /**
     * Test that the FAQ page loads correctly.
     */
    public function test_faq_page_loads(): void
    {
        $response = $this->get('/faq');

        $response->assertStatus(200);
    }

    /**
     * Test that the FAQ route exists and is properly named.
     */
    public function test_faq_route_exists(): void
    {
        $response = $this->get(route('faq'));

        $response->assertStatus(200);
    }

    /**
     * Test that the FAQ page renders the correct Inertia component.
     */
    public function test_faq_page_renders_correct_component(): void
    {
        $response = $this->get('/faq');

        $response->assertInertia(fn ($page) => 
            $page->component('faq')
        );
    }
}
