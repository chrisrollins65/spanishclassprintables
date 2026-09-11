<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The pin drop-box the TpT builder uploads to.
 *
 * The builder's client (src/pinUpload.js) is shared with Eat Well Planner's
 * copy of this endpoint, so these pin down the contract it relies on: the
 * header, the field names, and a reply whose `url` really serves the file.
 */
class InternalPinAssetTest extends TestCase
{
    private const SECRET = 'test-pin-secret';

    // A real 1x1 PNG. `mimes:png` sniffs the bytes, so a file merely named
    // .png would be rejected — which is the point of the rule.
    private const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config(['services.pin_assets.secret' => self::SECRET, 'services.pin_assets.retention_days' => 14]);
    }

    private function png(string $name = 'pin.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode(self::PNG_BASE64));
    }

    private function upload(array $fields, ?string $secret = self::SECRET)
    {
        $headers = ['Accept' => 'application/json'];
        if ($secret !== null) {
            $headers['X-Pin-Asset-Secret'] = $secret;
        }

        return $this->post('/api/internal/pin-asset', $fields, $headers);
    }

    public function test_refuses_a_missing_or_wrong_secret(): void
    {
        $this->upload(['image' => $this->png()], null)->assertForbidden();
        $this->upload(['image' => $this->png()], 'wrong')->assertForbidden();
        $this->assertSame([], Storage::disk('public')->files('tpt-pins'));
    }

    public function test_refuses_everything_when_no_secret_is_configured(): void
    {
        // A deploy that forgets PIN_ASSET_SECRET must fail closed, even for a
        // caller that sends an empty header to match the empty value.
        config(['services.pin_assets.secret' => '']);

        $this->upload(['image' => $this->png()], '')->assertForbidden();
    }

    public function test_rejects_anything_that_is_not_a_png(): void
    {
        // A real UploadedFile over a real temp file, not UploadedFile::fake():
        // the fakes report their type from the NAME, so a text file called
        // pin.png passes as a PNG in a test and nowhere else. A real upload is
        // sniffed by its bytes, which is the behaviour worth pinning down.
        $path = tempnam(sys_get_temp_dir(), 'pin');
        file_put_contents($path, 'not an image, whatever the name says');
        $notPng = new UploadedFile($path, 'pin.png', null, null, true);

        $this->upload(['image' => $notPng])->assertUnprocessable();
        $this->assertSame([], Storage::disk('public')->files('tpt-pins'));
    }

    public function test_stores_the_pin_under_a_readable_name_and_returns_its_public_url(): void
    {
        $response = $this->upload([
            'image' => $this->png(),
            'name' => 'El_Día_de_la_Independencia_de_México_Bingo',
        ])->assertOk();

        $filename = $response->json('filename');
        // Accents flattened, so the URL survives being decoded one time too
        // many somewhere between here and Pinterest.
        $this->assertMatchesRegularExpression('/^el-dia-de-la-independencia-de-mexico-bingo-[0-9a-f]{8}\.png$/', $filename);
        Storage::disk('public')->assertExists('tpt-pins/' . $filename);
        $this->assertSame(Storage::disk('public')->url('tpt-pins/' . $filename), $response->json('url'));
        $this->assertStringEndsWith('.png', $response->json('url'));
    }

    public function test_sweeps_pins_older_than_the_retention_window(): void
    {
        $disk = Storage::disk('public');
        $disk->put('tpt-pins/old.png', 'x');
        $disk->put('tpt-pins/recent.png', 'x');
        touch($disk->path('tpt-pins/old.png'), now()->subDays(15)->getTimestamp());
        touch($disk->path('tpt-pins/recent.png'), now()->subDays(2)->getTimestamp());

        $this->upload(['image' => $this->png(), 'name' => 'Nuevo'])
            ->assertOk()
            ->assertJsonPath('swept', 1);

        $disk->assertMissing('tpt-pins/old.png');
        $disk->assertExists('tpt-pins/recent.png');
    }
}
