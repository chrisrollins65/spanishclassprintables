<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title') — Spanish Class Printables</title>
{{-- Account pages are for one teacher, never for search. --}}
<meta name="robots" content="noindex">
<link rel="icon" href="/favicon.ico" sizes="48x48">
<link rel="icon" type="image/png" href="/favicon-32.png" sizes="32x32">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@500;600;700&family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
  /* The homepage's palette and form styles (home.blade.php), trimmed to what
     these pages use. Paper side, like the homepage: these are read on a
     laptop, not projected. */
  :root {
    --paper: #FDF8F1;
    --card: #FFFFFF;
    --wash: #F9E3F0;
    --ink: #2D2D2D;
    --muted: #5A5A5A;
    --edge: #E8D2E0;
    --accent: #BE0087;
    --accent-dark: #980069;
    --pink: #E71F69;
    --teal: #00A89F;
    --teal-dark: #007F78;
    --gold: #F8B31A;
    --gold-soft: #FFF1CC;
    --radius: 18px;
    --shadow: 0 1px 2px rgba(80, 20, 60, .06), 0 10px 30px rgba(80, 20, 60, .08);
  }
  * { box-sizing: border-box; }
  body {
    margin: 0; min-height: 100vh; display: flex; flex-direction: column;
    background: linear-gradient(180deg, var(--wash), var(--paper) 320px);
    color: var(--ink);
    font: 400 1.0625rem/1.6 'Nunito', 'Segoe UI', system-ui, sans-serif;
  }
  a { color: var(--accent); }
  h1, h2, h3 { font-family: 'Fredoka', 'Trebuchet MS', sans-serif; line-height: 1.15; margin: 0; }
  h1 { font-size: clamp(1.7rem, 4vw, 2.2rem); font-weight: 600; }
  h2 { font-size: 1.35rem; font-weight: 600; }
  p { margin: 0; }
  .wrap { width: 100%; max-width: 1120px; margin: 0 auto; padding-inline: 20px; }
  .visually-hidden { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }

  .top { border-bottom: 1px solid var(--edge); background: rgba(253, 248, 241, .92); }
  .top .wrap { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding-block: 12px; }
  .logo img { height: 44px; width: auto; display: block; }
  .nav { display: flex; align-items: center; gap: 18px; }
  /* nowrap because a link is a phrase: "My games" broken after "My" reads as
     two separate links. */
  .nav a, .nav button { color: var(--ink); text-decoration: none; font: 700 .98rem 'Nunito', sans-serif;
                        background: none; border: 0; cursor: pointer; padding: 0; white-space: nowrap; }
  .nav a:hover, .nav button:hover { color: var(--accent); }

  /* Below this the logo and four or five links stop fitting on one line, and
     the links were being squeezed until the words broke inside themselves.
     The header takes a second row instead: logo above, links centred under
     it, each with room to be read. A menu behind a button would hide four
     links to save one row. */
  @media (max-width: 620px) {
    .logo img { height: 34px; }
    .top .wrap { flex-direction: column; gap: 10px; padding-block: 10px; }
    .nav { gap: 18px; flex-wrap: wrap; justify-content: center; }
  }

  main { flex: 1; padding-block: clamp(28px, 6vw, 64px); }
  .narrow { max-width: 460px; }
  .medium { max-width: 760px; }
  .card { background: var(--card); border: 1px solid var(--edge); border-radius: var(--radius); box-shadow: var(--shadow); padding: clamp(22px, 4vw, 34px); position: relative; overflow: hidden; }
  .card.accent::before { content: ""; position: absolute; inset: 0 0 auto 0; height: 6px; background: linear-gradient(90deg, var(--accent), var(--pink), var(--gold), var(--teal)); }
  .card + .card { margin-top: 20px; }
  .lead { color: var(--muted); margin-top: 8px; }

  form.stack { display: grid; gap: 16px; margin-top: 22px; }
  label { display: block; font-weight: 700; font-size: .95rem; margin-bottom: 6px; }
  input[type=text], input[type=email], input[type=password] {
    width: 100%; font: inherit; color: var(--ink); background: var(--paper);
    border: 2px solid var(--edge); border-radius: 12px; padding: .7em .85em;
  }
  input:focus { border-color: var(--accent); outline: none; }
  .check { display: flex; gap: 8px; align-items: center; font-weight: 600; }
  .check input { width: 18px; height: 18px; accent-color: var(--accent); }
  .hint { font-size: .88rem; color: var(--muted); margin-top: 5px; }
  .error { color: #B3261E; font-size: .9rem; margin-top: 5px; font-weight: 700; }
  .notice { background: #E3F6F4; color: #0B4F4B; border-radius: 12px; padding: 12px 16px; font-weight: 600; margin-top: 18px; }
  .foot { margin-top: 20px; font-size: .95rem; color: var(--muted); text-align: center; }

  .btn {
    display: inline-flex; align-items: center; justify-content: center; gap: .5em;
    font: 700 1.05rem/1 'Nunito', sans-serif; text-decoration: none; cursor: pointer;
    border: 2px solid transparent; border-radius: 999px; padding: .9em 1.5em;
  }
  .btn:focus-visible, input:focus-visible { outline: 3px solid var(--gold); outline-offset: 2px; }
  .btn-primary { background: var(--accent); color: #fff; box-shadow: 0 6px 18px rgba(190, 0, 135, .28); }
  .btn-primary:hover { background: var(--accent-dark); }
  .btn-ghost { background: transparent; color: var(--accent); border-color: var(--edge); }
  .btn-ghost:hover { border-color: var(--accent); }
  .btn-block { width: 100%; }

  footer { color: var(--muted); font-size: .9rem; padding-block: 24px; text-align: center; }
  footer a { color: var(--muted); }
  footer nav { display: flex; gap: 16px; flex-wrap: wrap; justify-content: center; margin-top: 8px; }
  @stack('styles')
</style>
</head>
<body>

<header class="top">
  <div class="wrap">
    <a class="logo" href="/" aria-label="Spanish Class Printables home">
      <img src="/game/logo.png" alt="Spanish Class Printables" width="164" height="44">
    </a>
    <nav class="nav" aria-label="Account">
      @auth
        <a href="{{ route('my-games') }}">My games</a>
        <a href="{{ route('credits') }}">Credits</a>
        @can('admin')
          <a href="{{ route('admin.index') }}">Shop</a>
        @endcan
        <a href="{{ route('account') }}">Account</a>
        <form method="post" action="{{ route('logout') }}">
          @csrf
          <button type="submit">Log out</button>
        </form>
      @else
        <a href="{{ route('login') }}">Log in</a>
        <a href="{{ route('register') }}">Sign up</a>
      @endauth
    </nav>
  </div>
</header>

<main>
  @yield('content')
</main>

<footer>
  <a href="/">spanishclassprintables.com</a>
  <nav aria-label="Legal">
    <a href="{{ route('pricing') }}">Pricing</a>
    <a href="{{ route('terms') }}">Terms</a>
    <a href="{{ route('privacy') }}">Privacy</a>
    <a href="{{ route('refunds') }}">Refunds</a>
  </nav>
</footer>

{{-- Stops the second half of a double-tap posting a form twice. The courtesy
     half only: what a repeat would cost is guarded on the server too. --}}
<script src="/site/once.js{{ \App\Http\Controllers\RoomController::stamp('site/once.js') }}"></script>

</body>
</html>
