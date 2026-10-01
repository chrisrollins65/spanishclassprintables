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
     * Who can reach the admin screens, by email address.
     *
     * A list here rather than a column on users: nothing a teacher can do to
     * their own row can make them an admin, and taking the rights away is an
     * edit to this environment's .env rather than a database write someone has
     * to remember to make.
     */
    'admins' => array_values(array_filter(array_map(
        fn (string $email): string => strtolower(trim($email)),
        explode(',', (string) env('ADMIN_EMAILS', '')),
    ))),

    /*
     * Rooms nobody may copy into their own account (TeacherGameController).
     * The demo games are free to everyone, so a private, editable copy of one
     * would be a free game once games are sold here.
     *
     * The demo this environment links to is included automatically, and the
     * demos that have ever been in circulation are listed by hand as well: an
     * environment with no DEMO_ROOM_CODE set must not quietly make them
     * claimable.
     */
    'unclaimable_codes' => array_values(array_unique(array_filter(array_map(
        fn ($code): string => strtoupper(trim((string) $code)),
        ['PRUEBA', 'DEMO1', 'DEMO2', env('DEMO_ROOM_CODE', '')],
    )))),

    /*
     * Who is behind the site, for the legal pages.
     *
     * Here rather than written into four Blade files, because it is the same
     * handful of facts on every one of them and a business that moves would
     * otherwise leave a stale address on whichever page nobody remembered.
     *
     * `trading_as` is the name the site uses everywhere — the brand. `name`
     * is the person legally behind it, which Spain's LSSI (Ley 34/2002,
     * art. 10) requires a commercial site to make findable along with the NIF
     * and an address. Paddle is the merchant of record, so the seller in the
     * purchase contract is Paddle and their details are on the invoice; this
     * is the site-operator disclosure, which is ours and stays ours.
     */
    /* Shown on the legal pages. Bumped by hand when one of them changes in a
     * way a reader should notice — a date that moves on every deploy tells
     * them nothing. */
    'legal_updated' => env('LEGAL_UPDATED', '29 September 2026'),

    'legal' => [
        'trading_as' => 'Spanish Class Printables',
        'name' => env('LEGAL_NAME', 'Arantxa León'),
        // Required by LSSI art. 10 alongside the name. Ask the gestor.
        'nif' => env('LEGAL_NIF', ''),
        'address' => [
            'ORCA — Calle Gil-Vernet 54/55',
            'Polígono Les Tàpies 1 #1181',
            "43890 Hospitalet de l'Infant, Tarragona",
            'Spain',
        ],
        'email' => env('LEGAL_EMAIL', 'ary@spanishclassprintables.com'),
        'jurisdiction' => 'Spain',
        // The window a teacher can change their mind in, and the line that
        // decides it: AI that has already run has already cost us.
        'refund_days' => (int) env('REFUND_DAYS', 14),
    ],

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
