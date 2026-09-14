<?php

namespace App\Http\Controllers;

use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * The homepage's contact form.
 *
 * Spam is stopped in layers, cheapest first, and what each layer does depends
 * on how sure it can be. A signal only a bot gives (the honeypot, a form that
 * was never rendered) is thrown away behind the usual thank-you. A signal a
 * person could give (no JavaScript, a failed captcha) is a validation error,
 * so the person can fix it. A signal that is only suspicious (too fast, a pile
 * of links) still saves the message but marks it and doesn't email it, so a
 * teacher who pastes three TpT links is held back rather than lost.
 */
class ContactController extends Controller
{
    /** The answer the human check wants. The page's script fills it in on first touch. */
    public const HUMAN_ANSWER = 'blue';

    /** Faster than this from page load to send, and nobody read the form. */
    private const MIN_SECONDS = 3;

    /** More links than this, and it reads like SEO spam, not a teacher. */
    private const MAX_LINKS = 2;

    private const TURNSTILE_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /** The form's hidden start time, encrypted so a bot can't backdate it. */
    public static function startedToken(): string
    {
        return Crypt::encryptString((string) time());
    }

    /** Turnstile is on only when both keys are set, so dev and tests need neither. */
    public static function turnstileEnabled(): bool
    {
        return filled(config('services.turnstile.site_key'))
            && filled(config('services.turnstile.secret_key'));
    }

    public function store(Request $request): RedirectResponse
    {
        // Back to the form itself, not the top of the page: the default
        // redirect would drop the teacher at the hero, with their errors (or
        // the thank-you) a long scroll away.
        $back = url('/').'#contact';
        $thanks = redirect($back)->with('contact_sent', true);

        // Honeypot. A field no person can see, so anything in it is a bot. It
        // gets the same thank-you as a real message, so it learns nothing.
        if (filled($request->input('website'))) {
            return $thanks;
        }

        // Every rendered form carries this, so a post without a readable one
        // was put together without the page.
        $startedAt = $this->startedAt($request->input('started'));
        if ($startedAt === null) {
            return $thanks;
        }

        $validator = Validator::make($request->all(), [
            'name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'topic' => ['required', Rule::in(array_keys(ContactMessage::TOPICS))],
            'message' => ['required', 'string', 'min:5', 'max:5000'],
        ], [
            'message.required' => 'Please write a message before sending.',
            'message.min' => 'Please write a little more so we can help.',
            'email.email' => 'That email address doesn’t look quite right.',
        ]);

        $spamReason = null;

        $validator->after(function ($validator) use ($request, &$spamReason) {
            // Filled by the page's script the moment someone touches the form;
            // a visitor without JavaScript types it into the question instead.
            // Most bots post without running the page, so this is the layer
            // that stops the bulk of them.
            $answer = $request->input('human') ?: $request->input('human_answer');
            if (strtolower(trim((string) $answer)) !== self::HUMAN_ANSWER) {
                $validator->errors()->add('human', 'Please type “'.self::HUMAN_ANSWER.'” in the box so we know you’re not a robot.');
            }

            if (self::turnstileEnabled()) {
                $verified = $this->verifyTurnstile($request);
                if ($verified === false) {
                    $validator->errors()->add('turnstile', 'The security check didn’t finish. Please wait a moment for it, then send again.');
                } elseif ($verified === null) {
                    // Cloudflare itself was unreachable. That says nothing about
                    // the sender, so the message is held rather than refused.
                    $spamReason = 'captcha unavailable';
                }
            }
        });

        if ($validator->fails()) {
            // withInput carries the start time back too, so fixing an error
            // and resending quickly isn't mistaken for a bot.
            return redirect($back)->withErrors($validator)->withInput();
        }

        $fields = $validator->validated();
        $spamReason ??= $this->suspicion($fields, $startedAt);

        $message = ContactMessage::create($fields + ['spam_reason' => $spamReason]);

        // Held messages stay in the table for a look, but never reach the inbox.
        if ($spamReason !== null) {
            return $thanks;
        }

        // Saved first, mailed second: a mail failure is reported but never
        // costs the teacher their message or shows them an error for it.
        $inbox = (string) config('site.contact_inbox');
        if ($inbox !== '') {
            try {
                Mail::to($inbox)->send(new ContactMessageReceived($message));
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $thanks;
    }

    private function startedAt(mixed $token): ?int
    {
        if (! is_string($token) || $token === '') {
            return null;
        }

        try {
            $time = Crypt::decryptString($token);
        } catch (DecryptException) {
            return null;
        }

        return ctype_digit($time) ? (int) $time : null;
    }

    /** Why a message that passed every check still looks like spam, or null. */
    private function suspicion(array $fields, int $startedAt): ?string
    {
        if (time() - $startedAt < self::MIN_SECONDS) {
            return 'sent too fast';
        }

        if (preg_match_all('~https?://|www\.~i', $fields['message']) > self::MAX_LINKS) {
            return 'too many links';
        }

        // Nobody's name is a web address; a bot's "name" often is.
        if (preg_match('~https?://|www\.~i', (string) ($fields['name'] ?? ''))) {
            return 'link in name';
        }

        return null;
    }

    /**
     * True when Cloudflare vouches for the visitor, false when it doesn't, and
     * null when Cloudflare couldn't be asked. The same call as Eat Well
     * Planner's TurnstileApiClient.
     */
    private function verifyTurnstile(Request $request): ?bool
    {
        $token = $request->input('cf-turnstile-response');
        if (! is_string($token) || $token === '') {
            return false;
        }

        try {
            $response = Http::asForm()->timeout(10)->post(self::TURNSTILE_URL, [
                'secret' => config('services.turnstile.secret_key'),
                'response' => $token,
                'remoteip' => $request->ip(),
            ]);
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        return $response->json('success') === true;
    }
}
