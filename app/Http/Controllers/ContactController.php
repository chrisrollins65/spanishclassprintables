<?php

namespace App\Http\Controllers;

use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * The homepage's contact form.
 */
class ContactController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        // Back to the form itself, not the top of the page: the default
        // redirect would drop the teacher at the hero, with their errors (or
        // the thank-you) a long scroll away.
        $back = url('/').'#contact';

        // Honeypot. A field no person can see, so anything in it is a bot. It
        // gets the same thank-you as a real message, so it learns nothing.
        if (filled($request->input('website'))) {
            return redirect($back)->with('contact_sent', true);
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

        if ($validator->fails()) {
            return redirect($back)->withErrors($validator)->withInput();
        }

        $message = ContactMessage::create($validator->validated());

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

        return redirect($back)->with('contact_sent', true);
    }
}
