@extends('pages.layout')

@section('title', 'Pricing')
@section('description', 'One credit makes one Spanish classroom game — a bingo pack or a team quiz — yours to edit, play and print forever. $4.50 each, or 10 for $30.')

@push('styles')
  .tiers { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 26px; }
  @media (max-width: 680px) { .tiers { grid-template-columns: 1fr; } }
  .tier { position: relative; }
  .tier .price { font: 600 2.6rem 'Fredoka', sans-serif; color: var(--accent); line-height: 1; margin-top: 10px; }
  .tier .per { color: var(--muted); font-size: .95rem; margin-top: 6px; }
  .tier .btn { margin-top: 18px; width: 100%; }
  .flag { position: absolute; top: -12px; right: 18px; background: var(--gold); color: #4A3200;
          font: 800 .72rem 'Nunito', sans-serif; text-transform: uppercase; letter-spacing: .06em;
          border-radius: 999px; padding: 5px 12px; }
  .what { margin-top: 34px; }
  .what ul { list-style: none; padding: 0; }
  .what li { padding-left: 28px; position: relative; margin-top: 10px; }
  .what li::before { content: '✓'; position: absolute; left: 0; color: var(--teal); font-weight: 800; }
  .faq { margin-top: 38px; }
  .faq h3 { margin-top: 24px; }
  .note { margin-top: 26px; font-size: .95rem; color: var(--muted); }
@endpush

@section('content')
<div class="prose">
  <h1>Pricing</h1>
  <p class="lead">
    One credit makes one game — a bingo pack or a team quiz — that is yours to edit, play and
    print forever, as many times as you want.
  </p>
</div>

<div class="tiers">
  <div class="card tier">
    <h2 style="margin-top:0">One credit</h2>
    <div class="price">$4.50</div>
    <p class="per">One game.</p>
    <a class="btn btn-ghost" href="{{ route('credits') }}">Buy a credit</a>
  </div>

  <div class="card tier">
    <span class="flag">Save $15</span>
    <h2 style="margin-top:0">Ten credits</h2>
    <div class="price">$30</div>
    <p class="per">Ten games — $3 each.</p>
    <a class="btn btn-primary" href="{{ route('credits') }}">Buy ten credits</a>
  </div>
</div>

<div class="prose what">
  <h2>What a credit gets you</h2>
  <ul>
    <li><strong>A game you own.</strong> Write it yourself, or let AI write a first draft from a
      topic and change whatever you like — every word, clue and answer.</li>
    <li><strong>Played from this website</strong>, on the board or a projector. The site reads the
      clues aloud in Spanish and keeps score.</li>
    <li><strong>Printables.</strong> Bingo cards for your class, or answer sheets for each quiz
      team — reprinted free whenever you change the words.</li>
    <li><strong>No subscription.</strong> Credits are bought once and do not expire. A game you
      have made stays yours.</li>
  </ul>

  <p class="note">
    Prices are in US dollars. Your local currency and any tax are shown at checkout before you
    pay. Payments are handled by Paddle, who are the seller of record for every order.
  </p>
</div>

<div class="prose faq">
  <h2>Questions</h2>

  <h3>Do I need a credit to change a game I bought on TpT?</h3>
  <p>
    No. A game you bought from our TpT store can be added to your account with the code from its
    Play&nbsp;Online page, and edited as much as you like, free. Credits are only for making a
    new game from scratch.
  </p>

  <h3>What if I would rather write the game myself?</h3>
  <p>
    You can — the editor works the same whether AI wrote the first draft or you did. A credit
    covers the game either way, because what it pays for is the game, the website it is played
    on, and the printables.
  </p>

  <h3>Is there a limit on the AI?</h3>
  <p>
    Each credit comes with enough AI to write a game and then keep tuning it until it suits your
    class. Most teachers never come close. If you do reach it, you are told, and you can still
    change anything in the game yourself.
  </p>

  <h3>Can I get a refund?</h3>
  <p>
    Unused credits can be refunded within {{ config('site.legal.refund_days') }} days — see the
    <a href="{{ route('refunds') }}">refund policy</a>.
  </p>
</div>
@endsection
