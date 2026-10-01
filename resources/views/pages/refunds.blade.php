@extends('pages.layout')

@section('title', 'Refund Policy')
@section('description', 'When credits can be refunded, and how to ask.')

@section('content')
@php($legal = config('site.legal'))
@php($days = $legal['refund_days'])
<div class="prose">
  <div class="card">
    <h1>Refund Policy</h1>
    <p class="updated">Last updated {{ config('site.legal_updated') }}</p>

    <p>
      If credits you bought are not what you expected, we would rather give your money back than
      have you keep something you will not use.
    </p>

    <h2>Unused credits: {{ $days }} days</h2>
    <p>
      Within {{ $days }} days of buying, any credit you have not spent is refundable in full,
      for any reason. You do not have to explain why.
    </p>

    <h2>Credits you have spent</h2>
    <p>
      A credit is spent when you publish a game with it. At that point the game has been written
      — usually with AI, which costs us money the moment it runs — and the game is yours to keep,
      play and print. Spent credits are therefore not refundable.
    </p>
    <p>
      A credit held by a <em>draft</em> still counts as unused: delete the draft and the credit
      comes back to you, and it can then be refunded like any other.
    </p>

    <h2>Your right to change your mind</h2>
    <p>
      As a consumer in the EU you normally have {{ $days }} days to withdraw from an online
      purchase. Digital content is an exception once you have started using it: by publishing a
      game you ask us to supply it immediately and accept that your withdrawal right for that
      credit ends. Every credit you have not spent keeps the full {{ $days }} days.
    </p>

    <h2>If something is broken</h2>
    <p>
      This policy is about changing your mind. If the service does not do what it says — a game
      will not publish, printables will not render, AI fails and takes your allowance with it —
      that is a fault, not a refund request. Tell us and we will fix it and put the credit back,
      whenever it happened. Your legal rights about faulty digital content are not affected by
      anything on this page.
    </p>

    <h2>How to ask</h2>
    <p>
      Email <a href="mailto:{{ $legal['email'] }}">{{ $legal['email'] }}</a> with the email
      address you bought with, or the order reference from your Paddle receipt. We answer within
      two working days.
    </p>
    <p>
      Refunds are processed by Paddle, the merchant of record, back to the payment method you
      used. Paddle usually takes three to five working days, and your bank may take a little
      longer to show it.
    </p>

    <h2>What happens to the games</h2>
    <p>
      Refunding a credit takes back what it paid for. An unspent credit simply goes. If a
      published game is refunded, it is removed from your games — we keep it rather than delete
      it, so it can be put back if the refund turns out to be a mistake. Drafts are left alone;
      they cost nothing to keep, and publishing one will ask for a credit.
    </p>

    @include('pages.legal-details')
  </div>
</div>
@endsection
