@extends('account.layout')

@section('title', 'Credits')

@push('styles')
  .balance { font: 700 2.6rem 'Fredoka', sans-serif; color: var(--accent); line-height: 1; }
  .packs { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 22px; }
  @media (max-width: 620px) { .packs { grid-template-columns: 1fr; } }
  .pack { border: 2px solid var(--edge); border-radius: 16px; padding: 22px; background: var(--paper); display: flex; flex-direction: column; gap: 6px; }
  .pack .tag:empty { visibility: hidden; }
  /* Buttons on one line across the row, whatever the text above them does. */
  .pack .btn { margin-top: auto; }
  .pack.best { border-color: var(--accent); background: var(--card); }
  .pack h3 { font-size: 1.4rem; }
  .pack .price { font: 700 1.9rem 'Fredoka', sans-serif; color: var(--ink); line-height: 1.1; }
  .pack .per { display: block; font: 600 .82rem 'Nunito', sans-serif; color: var(--muted); }
  .pack .btn { margin-top: 12px; }
  .tag { align-self: flex-start; background: var(--gold-soft); color: #7A5200; font-weight: 800; font-size: .72rem;
         text-transform: uppercase; letter-spacing: .06em; border-radius: 999px; padding: .3em .8em; }
  .waiting { display: none; }
  .waiting.on { display: block; }
@endpush

@section('content')
<div class="wrap narrow">
  <div class="card accent">
    <h1>Credits</h1>
    <p class="lead">One credit makes one game — a bingo pack or a team quiz — that is yours to edit, play and print forever, as many times as you want.</p>

    <p class="balance" id="balance">{{ $credits }}</p>
    <p class="hint">{{ $credits === 1 ? 'credit' : 'credits' }} on your account</p>

    @if ($justBought)
      <p class="notice waiting on" id="waiting">Thank you! Your credits land here the moment the payment clears — usually a second or two.</p>
    @endif

    @if (! $token || ! $packs)
      {{-- Paddle is not set up in this environment yet; say so plainly rather
           than showing buttons that cannot work. --}}
      <p class="notice">Buying isn't switched on here yet.</p>
    @else
      <div class="packs">
        @foreach ($packs as $pack)
          <div class="pack {{ $pack['credits'] > 1 ? 'best' : '' }}" data-credits="{{ $pack['credits'] }}">
            {{-- Kept on both cards, invisible on one, so the two are the same
                 height and the tag does not shove one of them down. --}}
            <span class="tag" @unless ($pack['credits'] > 1) aria-hidden="true" @endunless>{{ $pack['credits'] > 1 ? 'Best value' : '' }}</span>
            <h3>{{ $pack['label'] }}</h3>
            {{-- Filled in by Paddle's own price preview: the price a teacher is
                 shown is the one their checkout will charge, in their currency
                 and with their tax, rather than a number we keep a second copy
                 of here. --}}
            <p class="price" data-price-for="{{ $pack['price_id'] }}">&nbsp;</p>
            <button class="btn btn-primary buy" type="button" data-price="{{ $pack['price_id'] }}">Buy</button>
          </div>
        @endforeach
      </div>
      {{-- The terms and the refund policy belong HERE, not only in the footer:
           a consumer is entitled to them before they are bound, not after they
           have gone looking. This is the last screen before the checkout. --}}
      <p class="hint" style="margin-top:14px">
        Paddle handles the payment and the receipt, and any tax due where you are. Credits never
        expire. Buying means you accept our <a href="{{ route('terms') }}">terms</a>; unspent
        credits can be refunded within {{ config('site.legal.refund_days') }} days, as set out in
        our <a href="{{ route('refunds') }}">refund policy</a>.
      </p>
    @endif
  </div>
</div>

@if ($token && $packs)
<script src="https://cdn.paddle.com/paddle/v2/paddle.js"></script>
<script>
  /* Paddle's own checkout, opened over this page.
   *
   * The card never touches this site. What we send along is the teacher's id
   * as custom data: the webhook reads it to know whose credits these are, and
   * it is the only link between a payment and an account. */
  (function () {
    'use strict';
    var environment = @json($environment);
    if (environment !== 'production') Paddle.Environment.set(environment);
    Paddle.Initialize({
      token: @json($token),
      eventCallback: function (event) {
        // Provisioning is the webhook's job; this only tells the teacher.
        if (event.name === 'checkout.completed') waitForCredits();
      },
    });

    showPrices();

    /* What each pack costs, from Paddle rather than from us.
     *
     * Paddle works out the currency and the tax for wherever the teacher is,
     * and hands back a formatted string — so the page never does money maths,
     * never stores an amount, and cannot show one thing while the checkout
     * charges another. Without an address it infers from the connection, which
     * is the same thing checkout will do a moment later.
     */
    function showPrices() {
      var slots = [].slice.call(document.querySelectorAll('[data-price-for]'));
      if (!slots.length) return;

      Paddle.PricePreview({
        items: slots.map(function (slot) { return { priceId: slot.dataset.priceFor, quantity: 1 }; }),
      }).then(function (preview) {
        // The currency is on the preview, not on each line.
        var currency = preview.data.currencyCode;

        preview.data.details.lineItems.forEach(function (line) {
          var slot = document.querySelector('[data-price-for="' + line.price.id + '"]');
          if (!slot) return;

          // Already formatted for this teacher's currency and locale — never
          // reformat it, and never do the maths ourselves.
          slot.textContent = line.formattedTotals.total;

          var credits = Number(slot.closest('.pack').dataset.credits || 1);
          var per = document.createElement('span');
          per.className = 'per';
          per.textContent = eachOne(line.totals.total, currency, credits) + ' a game';
          slot.append(per);
        });
      }).catch(function () {
        // Paddle unreachable: the checkout still shows the price, so say
        // nothing rather than guessing at one.
        slots.forEach(function (slot) { slot.textContent = ''; });
      });
    }

    /* What one game works out at, which is ours to say rather than Paddle's.
     *
     * Amounts arrive in the currency's smallest unit, except in the three that
     * have no smaller unit — yen, won and Chilean pesos — where dividing by a
     * hundred would be out by a hundred.
     */
    function eachOne(total, currency, credits) {
      var whole = ['JPY', 'KRW', 'CLP'].indexOf(currency) !== -1;
      var each = (Number(total) / (whole ? 1 : 100)) / credits;
      try {
        return new Intl.NumberFormat(navigator.language, { style: 'currency', currency: currency }).format(each);
      } catch (e) {
        return each.toFixed(whole ? 0 : 2);
      }
    }

    document.querySelectorAll('.buy').forEach(function (button) {
      button.onclick = function () {
        Paddle.Checkout.open({
          items: [{ priceId: button.dataset.price, quantity: 1 }],
          customer: { email: @json(auth()->user()->email) },
          customData: { user_id: @json((string) auth()->id()) },
          settings: {
            variant: 'one-page',
            successUrl: @json(route('credits', ['bought' => 1])),
          },
        });
      };
    });

    /* The webhook arrives on its own connection, so the page watches its own
     * balance rather than assuming the money has landed. */
    function waitForCredits() {
      var waiting = document.getElementById('waiting');
      if (waiting) waiting.classList.add('on');
      var started = @json($credits);
      var tries = 0;

      (function check() {
        if (tries++ > 20) return;
        setTimeout(function () {
          fetch(@json(route('credits.balance')), { credentials: 'same-origin', cache: 'no-store' })
            .then(function (res) { return res.json(); })
            .then(function (body) {
              if (body.credits > started) {
                document.getElementById('balance').textContent = body.credits;
                if (waiting) waiting.textContent = 'Your credits are here. Go and make a game.';
              } else {
                check();
              }
            })
            .catch(function () {});
        }, tries < 5 ? 800 : 2000);
      })();
    }
  })();
</script>
@endif
@endsection
