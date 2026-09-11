<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Drop-box for the Pinterest pin images the TpT builder renders locally.
 *
 * Pinterest's bulk uploader fetches each pin image from a URL rather than
 * accepting the file, so an image rendered on a laptop is useless to it until
 * something public is serving it. This endpoint is that something: push the
 * PNG, get back a URL on this site, put the URL in the CSV.
 *
 * Ported unchanged from Eat Well Planner, where the builder's pins were hosted
 * first, so the builder's client (src/pinUpload.js) works against either site
 * and moving between them is one line of its .env — PIN_ASSET_URL. Same field
 * names, same header, same reply.
 *
 * Stateless auth via a shared secret from the encrypted env, like the room
 * publish endpoint. Its own secret rather than the room one: a key that lives in
 * a second codebase should only ever open one door, and the room door writes
 * what every buyer's QR code resolves to.
 *
 * Nothing here touches the database. These files are a delivery mechanism with
 * a short life, not content the site owns.
 */
class InternalPinAssetController extends Controller
{
    /**
     * Where the pins land on the public disk, and so under /storage/<dir>/.
     * `storage` is a Capistrano linked dir and `storage:link` runs on every
     * deploy, so a pin pushed before a deploy is still there after it.
     */
    private const DIRECTORY = 'tpt-pins';

    // Pinterest's bulk uploader only accepts URLs ending .png or .jpg, and the
    // builder renders PNG, so anything else is a caller bug worth rejecting.
    private const MAX_BYTES = 10 * 1024 * 1024;

    public function apiPostPinAsset(Request $request): JsonResponse
    {
        $this->authorizeSecret($request);

        $request->validate([
            'image' => ['required', 'file', 'mimes:png', 'max:' . (self::MAX_BYTES / 1024)],
            // The packet name, used to make the URL readable. Optional: a pin
            // still works with a hash for a name, it is just harder to eyeball
            // against the CSV when something looks wrong.
            'name' => ['nullable', 'string', 'max:120'],
        ]);

        /** @var UploadedFile $image */
        $image = $request->file('image');
        $filename = $this->filenameFor($image, (string)$request->input('name', ''));

        Storage::disk('public')->putFileAs(self::DIRECTORY, $image, $filename);

        return response()->json([
            'url' => Storage::disk('public')->url(self::DIRECTORY . '/' . $filename),
            'filename' => $filename,
            'swept' => $this->sweepExpired(),
        ]);
    }

    /**
     * A readable, URL-safe name that changes when the image does.
     *
     * Packet folders are named in Spanish and carry accents and brackets
     * ("El_Día_de_la_Independencia_de_México", "..._(Aladdin)"). Percent-encoding
     * those into the CSV works right up until something in the chain decodes it
     * once too often, so the accents are flattened here instead. The content
     * hash keeps a re-rendered pin from being served from a stale cache under a
     * name it already used.
     */
    private function filenameFor(UploadedFile $image, string $name): string
    {
        $slug = Str::limit(Str::slug($name), 60, '');
        $hash = substr(hash_file('sha1', $image->getPathname()), 0, 8);

        return ($slug !== '' ? $slug . '-' : 'pin-') . $hash . '.png';
    }

    /**
     * Delete pins older than the retention window and report how many went.
     *
     * Swept on write rather than on a schedule: uploads are the only thing that
     * ever touches this directory, so there is no need for a cron entry that
     * would have to be remembered and monitored for a folder holding a few
     * megabytes.
     *
     * The window has to outlast the gap between pushing an image and Pinterest
     * actually importing the CSV — Pinterest fetches the image at import, so a
     * file deleted before then produces no pin, and says nothing about why.
     * Two weeks is deliberately far more than that gap.
     */
    private function sweepExpired(): int
    {
        $disk = Storage::disk('public');
        $cutoff = now()->subDays($this->retentionDays())->getTimestamp();

        $deleted = 0;
        foreach ($disk->files(self::DIRECTORY) as $path) {
            if ($disk->lastModified($path) < $cutoff) {
                $disk->delete($path);
                $deleted++;
            }
        }

        return $deleted;
    }

    private function retentionDays(): int
    {
        return max(1, (int)config('services.pin_assets.retention_days', 14));
    }

    private function authorizeSecret(Request $request): void
    {
        $secret = (string)config('services.pin_assets.secret');

        // An unset secret refuses everything rather than accepting everything:
        // a deploy that forgets the env line fails closed.
        abort_unless(
            $secret !== '' && hash_equals($secret, (string)$request->header('X-Pin-Asset-Secret')),
            403,
        );
    }
}
