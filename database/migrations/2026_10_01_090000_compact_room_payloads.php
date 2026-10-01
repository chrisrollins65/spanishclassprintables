<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Squeeze the indentation out of the room payloads already published.
 *
 * `RoomController::publish` used to pretty-print before storing, which made
 * roughly two thirds of the column spaces — on 32 rooms, 1.9MB of which 1.2MB
 * was indentation. It now stores compact, like the teacher games always have,
 * but rooms are only ever rewritten when their packet is republished, and a
 * code printed on paper years ago may never be. So the existing rows are
 * re-encoded here rather than left to drift.
 *
 * Row by row, because the whole point is that these are large; and re-encoded
 * rather than string-stripped, because whitespace inside a Spanish clue is
 * content. A row that does not parse is left exactly as it is: an unreadable
 * payload is a dead room link, and this migration is a disk saving, not
 * something worth breaking a printed QR code over.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('rooms')->select('code', 'payload')->orderBy('code')->chunk(50, function ($rooms) {
            foreach ($rooms as $room) {
                $data = json_decode((string) $room->payload, true);

                if (! is_array($data)) {
                    continue;
                }

                $compact = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                if ($compact === false || $compact === $room->payload) {
                    continue;
                }

                DB::table('rooms')->where('code', $room->code)->update(['payload' => $compact]);
            }
        });
    }

    /**
     * Deliberately empty. The payload is the same JSON either way, so there is
     * nothing to put back — re-indenting it would only spend the disk again.
     */
    public function down(): void {}
};
