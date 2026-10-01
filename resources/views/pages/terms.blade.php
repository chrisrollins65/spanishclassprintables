@extends('pages.layout')

@section('title', 'Terms of Service')
@section('description', 'The terms you agree to when you use Spanish Class Printables.')

@section('content')
@php($legal = config('site.legal'))
<div class="prose">
  <div class="card">
    <h1>Terms of Service</h1>
    <p class="updated">Last updated {{ config('site.legal_updated') }}</p>

    <p>
      These terms cover your use of {{ $legal['trading_as'] }} — the website at
      spanishclassprintables.com, the games played on it, and the printable materials it makes.
      By using the site or buying credits you agree to them.
    </p>

    <h2>Who you are buying from</h2>
    <p>
      Orders are sold and fulfilled by <strong>Paddle.com Market Ltd</strong>, who are the
      merchant of record for every purchase. Paddle handle payment, tax and invoicing, and their
      own terms apply to the transaction itself. Your receipt comes from Paddle.
      {{ $legal['trading_as'] }} runs the website and the service behind it, and is identified at
      the foot of this page.
    </p>

    <h2>Accounts</h2>
    <ul>
      <li>An account is for one teacher. Keep your password to yourself.</li>
      <li>You must give a working email address — we use it to verify the account and to reach
        you about your orders.</li>
      <li>You must be old enough to enter a contract where you live.</li>
      <li>You can delete your account at any time from your account page.</li>
    </ul>

    <h2>Credits and games</h2>
    <ul>
      <li>One credit makes one game — a bingo pack or a team quiz — chosen when you start it.</li>
      <li>The credit is taken when the game is created. While a game is still a draft you can
        delete it and the credit comes back to you.</li>
      <li>Publishing a game spends the credit for good. The game is then yours to play, edit and
        print for as long as the service runs.</li>
      <li>Credits do not expire and are not a subscription.</li>
      <li>Each credit includes an allowance of AI writing. It is generous enough to write a game
        and refine it; if you reach it, you can still edit the game yourself.</li>
      <li>A game bought from our TpT store can be added to your account with its code and edited
        free — no credit needed.</li>
    </ul>

    <h2>What you may do with what you make</h2>
    <p>
      The games and printables you create are yours to use in your own teaching, including in a
      school you work for. You may print and photocopy them for your own classes.
    </p>
    <p>You may not:</p>
    <ul>
      <li>resell, license or redistribute the games or printables, whether free or paid,
        including on resource-sharing sites;</li>
      <li>share your account so that others can create or edit games with your credits;</li>
      <li>copy the website, scrape it, or use it to build a competing service.</li>
    </ul>

    <h2>AI-written material</h2>
    <p>
      When you ask AI to write or change a game, the result is a first draft. It can be wrong —
      a clue that gives itself away, a word that is harder than it looks, an occasional mistake
      in Spanish. The editor shows you everything before you publish, and the checks panel
      flags what it can. <strong>Please read a game before you put it in front of a class.</strong>
      You are responsible for what you teach with.
    </p>

    <h2>Keeping the service running</h2>
    <p>
      We aim to keep the site available, but we do not promise it will never be down. We may
      change or improve it, and we may stop offering it — if we ever do, we will give notice and
      time to download the printables of games you have made.
    </p>

    <h2>If something goes wrong</h2>
    <p>
      Nothing here limits rights you have as a consumer under Spanish or EU law, including your
      rights about faulty digital content. Beyond those rights, our liability for any claim
      connected with the service is limited to what you paid us in the twelve months before it
      arose.
    </p>
    <p>
      We may suspend or close an account that breaks these terms. Where the breach is not
      serious we will tell you first and give you a chance to put it right.
    </p>

    <h2>Changes to these terms</h2>
    <p>
      If we change them in a way that matters, we will say so on this page and, for anything
      significant, by email. Continuing to use the site after a change means you accept it.
    </p>

    <h2>Law and jurisdiction</h2>
    <p>
      These terms are governed by the law of {{ $legal['jurisdiction'] }}. If you are a consumer,
      you can also bring proceedings in the country where you live, and you keep the protection
      of its mandatory consumer law. The European Commission's online dispute resolution platform
      is at <a href="https://ec.europa.eu/consumers/odr" target="_blank" rel="noopener">ec.europa.eu/consumers/odr</a>.
    </p>

    @include('pages.legal-details')
  </div>
</div>
@endsection
