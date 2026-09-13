<?php

return [
    /*
     * The store. ui.js in public/game carries the same two TpT links for the
     * game screens; change one, change both.
     */
    'store_url' => 'https://www.teacherspayteachers.com/store/spanish-class-printables',

    // Every purchase on one page, each with its own review button — the only
    // TpT page that works for a buyer whatever they bought.
    'review_url' => 'https://www.teacherspayteachers.com/My-Purchases',

    /*
     * The demo room, offered on the homepage to a visitor with no code of
     * their own. Hidden when unset, rather than linking a room that may not
     * exist on this environment.
     */
    'demo_room_code' => env('DEMO_ROOM_CODE', ''),

    /*
     * MailerLite's popup signup form, from the old subscribepage.io landing
     * page. The homepage only opens it; the form itself is edited in MailerLite.
     */
    'mailerlite_account' => env('MAILERLITE_ACCOUNT', '2158844'),
    'mailerlite_form' => env('MAILERLITE_FORM', 'F0paUf'),

    /*
     * Where contact form messages are emailed. Every message is saved to the
     * contact_messages table whether or not this is set, so an unset inbox or
     * a mail failure never loses one.
     */
    'contact_inbox' => env('CONTACT_INBOX', ''),
];
