{{-- The site-operator disclosure, in one partial so all three documents carry
     the same facts and a move changes one file.

     Spain's LSSI (Ley 34/2002, art. 10) asks a commercial site established
     here to make its operator findable: the name, an address, and a tax
     number. The brand is used everywhere else on the site; this is the one
     place the person behind it is named.

     The NIF is optional here and currently unset, so the sentence ends after
     the name rather than showing a gap where a number should be. Setting
     LEGAL_NIF is all it takes to add it. --}}
@php($legal = config('site.legal'))
<div class="legal-details">
  <strong>Legal details</strong>
  <address>
    {{ $legal['trading_as'] }} is operated by {{ $legal['name'] }}@if ($legal['nif']), NIF {{ $legal['nif'] }}@endif.<br>
    @foreach ($legal['address'] as $line)
      {{ $line }}@if (! $loop->last)<br>@endif
    @endforeach
    <br>
    <a href="mailto:{{ $legal['email'] }}">{{ $legal['email'] }}</a>
  </address>
  <p style="margin-top:10px; color: var(--muted); font-size: .92rem">
    Purchases are sold by Paddle.com Market Ltd as merchant of record.
  </p>
</div>
