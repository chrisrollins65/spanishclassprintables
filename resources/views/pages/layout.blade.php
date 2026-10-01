{{-- The public content pages: pricing and the three legal documents.
     Indexable, unlike the account pages — a buyer and Paddle's reviewer both
     have to be able to find these without logging in. --}}
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title') — Spanish Class Printables</title>
<meta name="description" content="@yield('description', 'Spanish Class Printables')">
<link rel="icon" href="/favicon.ico" sizes="48x48">
<link rel="icon" type="image/png" href="/favicon-32.png" sizes="32x32">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@500;600;700&family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
  :root {
    --paper: #FDF8F1; --card: #FFFFFF; --wash: #F9E3F0; --ink: #2D2D2D;
    --muted: #5A5A5A; --edge: #E8D2E0; --accent: #BE0087; --accent-dark: #980069;
    --pink: #E71F69; --teal: #00A89F; --gold: #F8B31A; --gold-soft: #FFF1CC;
    --radius: 18px; --shadow: 0 1px 2px rgba(80, 20, 60, .06), 0 10px 30px rgba(80, 20, 60, .08);
  }
  * { box-sizing: border-box; }
  body {
    margin: 0; min-height: 100vh; display: flex; flex-direction: column;
    background: linear-gradient(180deg, var(--wash), var(--paper) 320px);
    color: var(--ink); font: 400 1.0625rem/1.65 'Nunito', 'Segoe UI', system-ui, sans-serif;
  }
  a { color: var(--accent); }
  h1, h2, h3 { font-family: 'Fredoka', 'Trebuchet MS', sans-serif; line-height: 1.15; margin: 0; }
  h1 { font-size: clamp(1.8rem, 4vw, 2.4rem); font-weight: 600; }
  h2 { font-size: 1.3rem; font-weight: 600; margin-top: 30px; }
  h3 { font-size: 1.05rem; font-weight: 600; margin-top: 22px; }
  p, ul { margin: 10px 0 0; }
  ul { padding-left: 22px; }
  li + li { margin-top: 5px; }
  .wrap { width: 100%; max-width: 1120px; margin: 0 auto; padding-inline: 20px; }

  .top { border-bottom: 1px solid var(--edge); background: rgba(253, 248, 241, .92); }
  .top .wrap { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding-block: 12px; }
  .logo img { height: 44px; width: auto; display: block; }
  .nav { display: flex; align-items: center; gap: 18px; flex-wrap: wrap; }
  .nav a { color: var(--ink); text-decoration: none; font: 700 .98rem 'Nunito', sans-serif; white-space: nowrap; }
  .nav a:hover { color: var(--accent); }
  @media (max-width: 620px) {
    .logo img { height: 34px; }
    .top .wrap { flex-direction: column; gap: 10px; padding-block: 10px; }
    .nav { justify-content: center; }
  }

  main { flex: 1; padding-block: clamp(28px, 6vw, 56px); }
  .prose { max-width: 720px; }
  .prose .lead { color: var(--muted); margin-top: 10px; font-size: 1.1rem; }
  .updated { color: var(--muted); font-size: .9rem; margin-top: 6px; }
  .card { background: var(--card); border: 1px solid var(--edge); border-radius: var(--radius);
          box-shadow: var(--shadow); padding: clamp(22px, 4vw, 36px); }

  /* The one block that names the person behind the site, as the LSSI asks. */
  .legal-details { margin-top: 30px; background: var(--paper); border: 1px solid var(--edge);
                   border-radius: 14px; padding: 18px 22px; font-size: .95rem; }
  .legal-details address { font-style: normal; color: var(--muted); margin-top: 6px; }

  .btn { display: inline-flex; align-items: center; justify-content: center; gap: .5em;
         font: 700 1.05rem/1 'Nunito', sans-serif; text-decoration: none; cursor: pointer;
         border: 2px solid transparent; border-radius: 999px; padding: .9em 1.5em; }
  .btn-primary { background: var(--accent); color: #fff; box-shadow: 0 6px 18px rgba(190, 0, 135, .28); }
  .btn-primary:hover { background: var(--accent-dark); }
  .btn-ghost { background: transparent; color: var(--accent); border-color: var(--edge); }
  .btn-ghost:hover { border-color: var(--accent); }

  footer { color: var(--muted); font-size: .9rem; padding-block: 28px; border-top: 1px solid var(--edge); margin-top: 40px; }
  footer .wrap { display: flex; gap: 16px; flex-wrap: wrap; align-items: center; justify-content: space-between; }
  footer a { color: var(--muted); }
  footer nav { display: flex; gap: 16px; flex-wrap: wrap; }
  @stack('styles')
</style>
</head>
<body>

<header class="top">
  <div class="wrap">
    <a class="logo" href="/" aria-label="Spanish Class Printables home">
      <img src="/game/logo.png" alt="Spanish Class Printables" width="164" height="44">
    </a>
    <nav class="nav" aria-label="Main">
      <a href="/">Home</a>
      <a href="{{ route('pricing') }}">Pricing</a>
      @auth
        <a href="{{ route('my-games') }}">My games</a>
      @else
        <a href="{{ route('login') }}">Log in</a>
      @endauth
    </nav>
  </div>
</header>

<main>
  <div class="wrap">
    @yield('content')
  </div>
</main>

<footer>
  <div class="wrap">
    <small>© {{ date('Y') }} {{ config('site.legal.trading_as') }}</small>
    <nav aria-label="Legal">
      <a href="{{ route('pricing') }}">Pricing</a>
      <a href="{{ route('terms') }}">Terms</a>
      <a href="{{ route('privacy') }}">Privacy</a>
      <a href="{{ route('refunds') }}">Refunds</a>
      <a href="/#contact">Contact</a>
    </nav>
  </div>
</footer>

</body>
</html>
