<?php

namespace App\Support;

use Illuminate\Support\Facades\View;
use Spatie\Browsershot\Browsershot;

/**
 * One place that knows how to turn a printable page into a PDF.
 *
 * Which Chrome, which node, and whether the sandbox can be used are a property
 * of the machine, not of the page being printed — the droplet runs this as the
 * web user and may not have the kernel namespaces Chrome's sandbox wants. Each
 * render job finding that out for itself is the same four ifs copied as many
 * times as there are printables.
 */
class PrintablePdf
{
    /**
     * A short hash of the template a printable is drawn with.
     *
     * Rendered PDFs are cached under a name built from the game's own data, so
     * asking twice for an unedited game is a download rather than a second
     * render. That name has to cover the DESIGN as well, or a change to the
     * page is invisible: the file on disk still matches the data, so it is
     * served forever and the fix never reaches a teacher who already has one.
     *
     * The file's contents rather than its timestamp, so a deploy that does not
     * change the page does not re-render every PDF on the site.
     *
     * @param  string  $view  a view name, e.g. "games.answer-sheet"
     */
    public static function templateStamp(string $view): string
    {
        // Not memoised: it is one hash of one small file, and only on the way
        // to spending seconds in a headless browser.
        return substr(sha1_file(View::getFinder()->find($view)) ?: '', 0, 8);
    }

    /**
     * @param  string  $html  a whole document, with its own @page rule
     */
    public static function render(string $html): string
    {
        // Margins and paper size live in the page's own CSS: a printable here
        // is a design, and the one that knows it is landscape is the one that
        // draws it.
        $pdf = Browsershot::html($html)
            ->format('Letter')
            ->margins(0, 0, 0, 0)
            ->showBackground()
            ->timeout(90);

        if ($chrome = config('browsershot.chrome_path')) {
            $pdf->setChromePath($chrome);
        }
        if ($node = config('browsershot.node_binary')) {
            $pdf->setNodeBinary($node);
        }
        if ($npm = config('browsershot.npm_binary')) {
            $pdf->setNpmBinary($npm);
        }
        if (config('browsershot.no_sandbox')) {
            $pdf->noSandbox();
        }

        return $pdf->pdf();
    }
}
