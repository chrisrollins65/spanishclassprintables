@extends('pages.layout')

@section('title', 'Privacy Policy')
@section('description', 'What Spanish Class Printables collects, why, and what your rights are.')

@section('content')
@php($legal = config('site.legal'))
<div class="prose">
  <div class="card">
    <h1>Privacy Policy</h1>
    <p class="updated">Last updated {{ config('site.legal_updated') }}</p>

    <p>
      This explains what {{ $legal['trading_as'] }} collects, why, and what you can ask us to do
      about it. The data controller is named at the foot of this page.
    </p>

    <h2>What we collect</h2>
    <h3>When you make an account</h3>
    <ul>
      <li>Your name and email address.</li>
      <li>A hashed password — we never store the password itself and cannot read it.</li>
      <li>The games you make or claim, and the words and clues in them.</li>
      <li>How many credits you have, and how much AI writing each one has used.</li>
    </ul>

    <h3>When you buy credits</h3>
    <p>
      Payment is taken by <strong>Paddle</strong>, the merchant of record. Your card details go
      to Paddle and never reach us — we never see or store them. Paddle tells us that an order
      completed, with the order reference and the email used, so we can add your credits.
    </p>

    <h3>When you use the site</h3>
    <ul>
      <li>A session cookie, so you stay logged in. It is necessary for the site to work.</li>
      <li>Ordinary server logs, which include IP addresses, kept for security and debugging.</li>
      <li>If you send the contact form: your message, and the name and email you put on it.</li>
    </ul>
    <p>
      We do not use advertising or tracking cookies, and we do not sell or share your data for
      marketing.
    </p>

    <h2>Why we are allowed to hold it</h2>
    <ul>
      <li><strong>To provide the service you asked for</strong> — your account, your games, your
        credits. This is performance of our contract with you.</li>
      <li><strong>To meet our legal obligations</strong> — keeping records of sales.</li>
      <li><strong>Our legitimate interests</strong> — keeping the site secure and working, and
        answering messages you send us.</li>
      <li><strong>Your consent</strong> — the email list, which you opt into and can leave from
        any email we send.</li>
    </ul>

    <h2>Who else handles it</h2>
    <ul>
      <li><strong>Paddle</strong> — payment, tax and invoicing, as merchant of record.</li>
      <li><strong>DigitalOcean</strong> — the server the site runs on.</li>
      <li><strong>Google (Gemini)</strong> and <strong>OpenAI</strong> — only when you ask AI to
        write or change a game. What is sent is the topic and the game's own words; your name,
        email and payment details are never sent.</li>
      <li><strong>MailerLite</strong> — the email list, if you join it.</li>
    </ul>
    <p>
      Some of these are outside the EU. Where they are, transfers rely on the European
      Commission's standard contractual clauses or an adequacy decision.
    </p>

    <h2>How long we keep it</h2>
    <ul>
      <li>Your account and games: until you delete them, or you ask us to close your account.</li>
      <li>Records of sales: as long as Spanish tax law requires.</li>
      <li>Contact messages: up to two years.</li>
      <li>Server logs: up to 90 days.</li>
    </ul>

    <h2>Your rights</h2>
    <p>
      You can ask us for a copy of your data, to correct it, to delete it, to limit what we do
      with it, or to object to it — and you can ask for it in a portable form. Email
      <a href="mailto:{{ $legal['email'] }}">{{ $legal['email'] }}</a> and we will reply within a
      month.
    </p>
    <p>
      If you think we have handled your data badly you can complain to Spain's data protection
      authority, the <a href="https://www.aepd.es" target="_blank" rel="noopener">Agencia Española
      de Protección de Datos</a>, or to the authority where you live.
    </p>

    <h2>Children</h2>
    <p>
      Accounts are for teachers, not pupils. We do not knowingly collect data from children. A
      class plays a game on the teacher's screen without an account, and nothing about a pupil is
      collected.
    </p>

    @include('pages.legal-details')
  </div>
</div>
@endsection
