<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The homepage a buyer lands on from the address printed on a packet.
 */
class HomePageTest extends TestCase
{
    public function test_the_homepage_leads_with_the_review_ask(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(config('site.review_url'), false)
            ->assertSee('TpT Credits');
    }

    public function test_the_room_code_box_submits_to_the_room_shell(): void
    {
        // public/game/app.js reads the code from ?code= on /j, so the form has
        // to GET exactly there with exactly that field name.
        $this->get('/')
            ->assertOk()
            ->assertSee('action="/j" method="get"', false)
            ->assertSee('name="code"', false);
    }

    public function test_the_demo_link_only_appears_when_a_demo_room_is_configured(): void
    {
        config(['site.demo_room_code' => '']);
        $this->get('/')->assertDontSee('Try the demo game');

        config(['site.demo_room_code' => 'DEMO1']);
        $this->get('/')->assertSee('href="/j/DEMO1"', false);
    }
}
