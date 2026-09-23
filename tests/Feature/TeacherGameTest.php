<?php

namespace Tests\Feature;

use App\Jobs\RenderBingoCards;
use App\Models\Room;
use App\Models\TeacherGame;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Claiming a bought game and playing the copy (docs/teacher-games.md, Phase 1).
 */
class TeacherGameTest extends TestCase
{
    use RefreshDatabase;

    private function room(string $code = 'ABC123', string $game = 'jeopardy'): Room
    {
        return Room::create([
            'code' => $code,
            'theme' => 'Los Deportes',
            'payload' => json_encode([
                'code' => $code,
                'theme' => 'Los Deportes',
                'games' => [$game => ['title' => 'Los Deportes', 'categories' => []]],
            ]),
        ]);
    }

    public function test_claiming_copies_the_room_into_the_teachers_account(): void
    {
        $room = $this->room();
        $teacher = User::factory()->create();

        $this->actingAs($teacher)
            ->post('/my-games/claim', ['code' => ' abc123 '])
            ->assertRedirect('/my-games');

        $game = $teacher->teacherGames()->sole();
        $this->assertSame('ABC123', $game->source_code);
        $this->assertSame('Los Deportes', $game->theme);
        $this->assertSame(['Team quiz'], $game->gameLabels());
        $this->assertSame('Team quiz', $game->gamesLabel());

        // The copy carries its own code, so its saved progress in the browser
        // can never collide with the original room's.
        $payload = json_decode($game->payload, true);
        $this->assertSame($game->id, $payload['code']);
        $this->assertSame(json_decode($room->payload, true)['games'], $payload['games']);

        $this->get('/my-games')->assertSee('Los Deportes')->assertSee('Team quiz');
    }

    public function test_a_pack_holding_both_games_says_so_in_one_phrase(): void
    {
        $game = TeacherGame::factory()->create(['games' => 'bingo,jeopardy']);

        $this->assertSame('Bingo and team quiz', $game->gamesLabel());

        $this->actingAs($game->user)->get('/my-games')->assertSee('Bingo and team quiz');
    }

    public function test_a_code_carried_from_the_game_fills_the_box_in(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/my-games?code=abc123')
            ->assertOk()
            ->assertSee('value="ABC123"', false);
    }

    public function test_claiming_never_changes_the_room(): void
    {
        $room = $this->room();
        $before = $room->payload;

        $this->actingAs(User::factory()->create())->post('/my-games/claim', ['code' => 'ABC123']);

        $this->assertSame($before, $room->fresh()->payload);
    }

    public function test_an_unknown_code_is_explained(): void
    {
        $this->actingAs(User::factory()->create())
            ->from('/my-games')
            ->post('/my-games/claim', ['code' => 'NOPE99'])
            ->assertRedirect('/my-games')
            ->assertSessionHasErrors('code');

        $this->assertSame(0, TeacherGame::count());
    }

    public function test_a_malformed_code_is_refused(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/my-games/claim', ['code' => 'AB-1'])
            ->assertSessionHasErrors('code');
    }

    public function test_no_demo_room_can_be_claimed(): void
    {
        // Every demo that has been in circulation, not only the one this
        // environment happens to link to.
        foreach (['PRUEBA', 'DEMO1', 'DEMO2'] as $code) {
            $this->room($code);

            $this->actingAs(User::factory()->create())
                ->post('/my-games/claim', ['code' => strtolower($code)])
                ->assertSessionHasErrors('code');
        }

        $this->assertSame(0, TeacherGame::count());
    }

    public function test_a_logged_in_teacher_asking_for_a_guest_page_lands_on_their_games(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/login')
            ->assertRedirect('/my-games');
    }

    public function test_the_homepage_offers_the_way_in(): void
    {
        $this->get('/')->assertSee('Log in')->assertDontSee('My games');

        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertSee('My games');
    }

    public function test_claiming_the_same_game_twice_keeps_one_copy(): void
    {
        $this->room();
        $teacher = User::factory()->create();

        $this->actingAs($teacher)->post('/my-games/claim', ['code' => 'ABC123']);
        $this->actingAs($teacher)->post('/my-games/claim', ['code' => 'ABC123'])
            ->assertSessionHas('status');

        $this->assertSame(1, $teacher->teacherGames()->count());
    }

    public function test_two_teachers_with_one_code_each_get_their_own_copy(): void
    {
        $this->room();

        foreach (User::factory()->count(2)->create() as $teacher) {
            $this->actingAs($teacher)->post('/my-games/claim', ['code' => 'ABC123']);
        }

        $this->assertSame(2, TeacherGame::where('source_code', 'ABC123')->count());
    }

    public function test_the_owner_plays_their_game_in_the_room_shell(): void
    {
        $game = TeacherGame::factory()->create();

        $this->actingAs($game->user)
            ->get("/my-games/{$game->id}/play")
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('data-payload-url="'.route('my-games.payload', $game).'"', false)
            ->assertSee('/game/app.js', false);

        $this->get("/my-games/{$game->id}/payload.json")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json; charset=UTF-8')
            ->assertJsonPath('theme', 'Los Deportes');
    }

    public function test_another_teachers_game_is_not_found(): void
    {
        $game = TeacherGame::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get("/my-games/{$game->id}/play")->assertNotFound();
        $this->actingAs($stranger)->get("/my-games/{$game->id}/payload.json")->assertNotFound();
        $this->actingAs($stranger)->delete("/my-games/{$game->id}")->assertNotFound();

        $this->assertNotSoftDeleted($game);
    }

    public function test_nobody_logged_out_reaches_a_game(): void
    {
        $game = TeacherGame::factory()->create();

        $this->get("/my-games/{$game->id}/play")->assertRedirect('/login');
        $this->get("/my-games/{$game->id}/payload.json")->assertRedirect('/login');
    }

    public function test_the_owner_can_remove_a_game(): void
    {
        $game = TeacherGame::factory()->create();

        $this->actingAs($game->user)
            ->delete("/my-games/{$game->id}")
            ->assertRedirect('/my-games');

        // Soft: a teacher who removes the wrong game can be helped.
        $this->assertSoftDeleted($game);
        $this->get('/my-games')->assertSee('No games yet');
    }

    public function test_the_editor_page_points_at_this_game(): void
    {
        $game = TeacherGame::factory()->create();

        $this->actingAs($game->user)
            ->get("/my-games/{$game->id}/edit")
            ->assertOk()
            ->assertSee('data-payload-url="'.route('my-games.payload', $game).'"', false)
            ->assertSee('data-save-url="'.route('my-games.update', $game).'"', false)
            // The builder's own validators, copied in by its sync script, and
            // every script stamped so a teacher never runs yesterday's editor.
            ->assertSee('/game/shared/bingoCards.js?v=', false)
            ->assertSee('/site/editor.js?v=', false);
    }

    public function test_saving_keeps_the_edited_game(): void
    {
        $game = TeacherGame::factory()->create();

        $this->actingAs($game->user)
            ->putJson("/my-games/{$game->id}", ['payload' => [
                'theme' => 'Los Deportes de Invierno',
                'items' => [['face' => 'esquí', 'article' => 'el', 'en' => 'ski']],
                'games' => ['jeopardy' => ['title' => 'Los Deportes', 'categories' => []]],
            ]])
            ->assertOk()
            ->assertJsonStructure(['saved_at']);

        $game->refresh();
        $this->assertSame('Los Deportes de Invierno', $game->theme);
        $this->assertSame('jeopardy', $game->games);
        $this->assertSame('esquí', json_decode($game->payload, true)['items'][0]['face']);
    }

    public function test_saving_keeps_the_parts_of_a_payload_the_editor_never_shows(): void
    {
        // clueTypes decides how bingo calls a word, and nothing in the editor
        // touches it. A save that kept only the fields it knows about left the
        // game uncallable.
        $game = TeacherGame::factory()->create();

        $this->actingAs($game->user)->putJson("/my-games/{$game->id}", ['payload' => [
            'theme' => 'Los Deportes',
            'clueTypes' => ['en', 'sentence', 'definition'],
            'grades' => '3-9',
            'games' => ['bingo' => ['cardSets' => [['size' => 4, 'count' => 48]], 'cards' => []]],
        ]])->assertOk();

        $saved = json_decode($game->fresh()->payload, true);
        $this->assertSame(['en', 'sentence', 'definition'], $saved['clueTypes']);
        $this->assertSame('3-9', $saved['grades']);
    }

    public function test_a_saved_game_always_carries_its_own_code(): void
    {
        $game = TeacherGame::factory()->create();

        $this->actingAs($game->user)->putJson("/my-games/{$game->id}", ['payload' => [
            'code' => 'PRUEBA',
            'theme' => 'Los Deportes',
            'games' => ['jeopardy' => ['categories' => []]],
        ]])->assertOk();

        $this->assertSame($game->id, json_decode($game->fresh()->payload, true)['code']);
    }

    public function test_a_game_nobody_can_play_is_refused(): void
    {
        $game = TeacherGame::factory()->create();
        $before = $game->payload;

        $this->actingAs($game->user)->putJson("/my-games/{$game->id}", ['payload' => [
            'theme' => 'Los Deportes',
            'games' => ['solitaire' => []],
        ]])->assertStatus(422);

        $this->actingAs($game->user)->putJson("/my-games/{$game->id}", ['payload' => [
            'theme' => '',
            'games' => ['jeopardy' => []],
        ]])->assertStatus(422);

        $this->assertSame($before, $game->fresh()->payload);
    }

    public function test_only_the_owner_can_save(): void
    {
        $game = TeacherGame::factory()->create();

        $this->actingAs(User::factory()->create())
            ->putJson("/my-games/{$game->id}", ['payload' => [
                'theme' => 'Taken',
                'games' => ['jeopardy' => ['categories' => []]],
            ]])
            ->assertNotFound();

        $this->assertSame('Los Deportes', $game->fresh()->theme);
    }

    /** A bingo game with two card sets, as the builder publishes one. */
    private function bingoGame(): TeacherGame
    {
        return TeacherGame::factory()->create([
            'games' => 'bingo',
            'payload' => json_encode([
                'theme' => 'Los Deportes',
                'games' => ['bingo' => ['title' => 'Los Deportes', 'cards' => [
                    ['id' => 1, 'size' => 4, 'grid' => [['a', 'b', 'c', 'd']]],
                    ['id' => 2, 'size' => 3, 'grid' => [['a', 'b', 'c']]],
                ]]],
            ]),
        ]);
    }

    public function test_asking_for_cards_queues_one_render(): void
    {
        Queue::fake();
        Storage::fake('local');
        $game = $this->bingoGame();

        $this->actingAs($game->user)
            ->postJson("/my-games/{$game->id}/cards", ['size' => 4])
            ->assertStatus(202)
            ->assertJsonPath('status', 'working');

        Queue::assertPushed(RenderBingoCards::class, fn (RenderBingoCards $job): bool => $job->gameId === $game->id && $job->size === 4);
    }

    public function test_cards_already_rendered_are_not_rendered_again(): void
    {
        Queue::fake();
        Storage::fake('local');
        $game = $this->bingoGame();

        // Whatever the controller would have asked the worker to make.
        $this->actingAs($game->user)->postJson("/my-games/{$game->id}/cards", ['size' => 4]);
        $job = Queue::pushed(RenderBingoCards::class)->first();
        Storage::disk('local')->put(RenderBingoCards::pathFor($game->id, 4, $job->stamp), 'pretend pdf');

        $this->actingAs($game->user)
            ->postJson("/my-games/{$game->id}/cards", ['size' => 4])
            ->assertOk()
            ->assertJsonPath('status', 'ready');

        $this->actingAs($game->user)
            ->get("/my-games/{$game->id}/cards/4.pdf")
            ->assertOk()
            ->assertDownload('los-deportes-cards-4x4.pdf');
    }

    public function test_editing_a_word_means_new_cards_rather_than_yesterdays(): void
    {
        Queue::fake();
        Storage::fake('local');
        $game = $this->bingoGame();

        $this->actingAs($game->user)->postJson("/my-games/{$game->id}/cards", ['size' => 4]);
        $before = Queue::pushed(RenderBingoCards::class)->first()->stamp;
        Storage::disk('local')->put(RenderBingoCards::pathFor($game->id, 4, $before), 'pretend pdf');

        // The editor rebuilds the cards whenever a word changes; here that is
        // simply a different card.
        $payload = json_decode($game->payload, true);
        $payload['games']['bingo']['cards'][0]['grid'] = [['x', 'y', 'z', 'w']];
        $this->actingAs($game->user)->putJson("/my-games/{$game->id}", ['payload' => $payload])->assertOk();

        $this->actingAs($game->user)
            ->postJson("/my-games/{$game->id}/cards", ['size' => 4])
            ->assertStatus(202);

        $this->actingAs($game->user)->get("/my-games/{$game->id}/cards/4.pdf")->assertNotFound();
    }

    public function test_a_size_the_game_does_not_have_is_refused(): void
    {
        Queue::fake();
        $game = $this->bingoGame();

        $this->actingAs($game->user)
            ->postJson("/my-games/{$game->id}/cards", ['size' => 5])
            ->assertStatus(422);

        Queue::assertNothingPushed();
    }

    public function test_only_the_owner_can_ask_for_or_download_cards(): void
    {
        Queue::fake();
        Storage::fake('local');
        $game = $this->bingoGame();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->postJson("/my-games/{$game->id}/cards", ['size' => 4])->assertNotFound();
        $this->actingAs($stranger)->get("/my-games/{$game->id}/cards/4/status")->assertNotFound();
        $this->actingAs($stranger)->get("/my-games/{$game->id}/cards/4.pdf")->assertNotFound();

        Queue::assertNothingPushed();
    }

    public function test_the_card_page_prints_the_site_but_never_a_code(): void
    {
        $game = $this->bingoGame();
        $data = RenderBingoCards::pageData($game->fresh(), 4);
        $html = view('games.cards', $data)->render();

        $this->assertStringContainsString($data['siteHome'], $html);
        // A card is cut up and taken home; the code opens every answer.
        $this->assertStringNotContainsString($game->id, $html);
        $this->assertStringNotContainsString('/j/', $html);
    }

    public function test_room_pages_still_serve_the_shell_without_a_payload_url(): void
    {
        $this->get('/j/ABC123')
            ->assertOk()
            ->assertSee('<main id="app">', false);
    }
}
