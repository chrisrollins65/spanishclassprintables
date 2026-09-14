<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Spanish Class Printables — No-prep Spanish worksheets and classroom games</title>
<meta name="description" content="No-prep Spanish worksheets and classroom games for teachers. Play your packet's game with its room code, leave a review, or tell us how we can make things better.">
<meta property="og:title" content="Spanish Class Printables">
<meta property="og:description" content="No-prep Spanish worksheets and classroom games for teachers.">
<meta property="og:image" content="{{ url('/site/cover-la-familia.jpg') }}">
<link rel="icon" href="/favicon.ico" sizes="48x48">
<link rel="icon" type="image/png" href="/favicon-32.png" sizes="32x32">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
@if ($turnstile)
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
@endif
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@500;600;700&family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
  /* The store's palette (tptwsbuilder's src/brand.js): cream paper, the logo's
     deep magenta as the accent, its teal and gold as the only other colours.
     The games are dark because they are projected; this page is read on a
     laptop, so it takes the paper side, like the worksheets themselves. */
  :root {
    --paper: #FDF8F1;
    --card: #FFFFFF;
    --wash: #F9E3F0;
    --band: #F6DCEC;
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
  html { scroll-behavior: smooth; }
  body {
    margin: 0;
    background: var(--paper);
    color: var(--ink);
    font: 400 1.0625rem/1.6 'Nunito', 'Segoe UI', system-ui, sans-serif;
  }
  img { max-width: 100%; height: auto; display: block; }
  a { color: var(--accent); }
  h1, h2, h3 { font-family: 'Fredoka', 'Trebuchet MS', sans-serif; line-height: 1.15; margin: 0; }
  h2 { font-size: clamp(1.8rem, 3.6vw, 2.5rem); font-weight: 600; }
  h3 { font-size: 1.3rem; font-weight: 600; }
  p { margin: 0; }
  .wrap { width: 100%; max-width: 1120px; margin: 0 auto; padding-inline: 20px; }
  /* Every anchor target clears the sticky header, not just sections: the play
     card is a div inside the hero, and it was landing under the nav. */
  [id] { scroll-margin-top: 96px; }
  .visually-hidden { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }

  /* ---- Buttons ---- */
  .btn {
    display: inline-flex; align-items: center; justify-content: center; gap: .5em;
    font: 700 1.05rem/1 'Nunito', sans-serif; text-decoration: none; cursor: pointer;
    border: 2px solid transparent; border-radius: 999px; padding: .95em 1.6em; white-space: nowrap;
    transition: transform .15s ease, background-color .15s ease, box-shadow .15s ease;
  }
  .btn:hover { transform: translateY(-1px); }
  .btn:focus-visible, input:focus-visible, select:focus-visible, textarea:focus-visible {
    outline: 3px solid var(--gold); outline-offset: 2px;
  }
  .btn-primary { background: var(--accent); color: #fff; box-shadow: 0 6px 18px rgba(190, 0, 135, .28); }
  .btn-primary:hover { background: var(--accent-dark); }
  .btn-teal { background: var(--teal); color: #fff; }
  .btn-teal:hover { background: var(--teal-dark); }
  .btn-ghost { background: transparent; color: var(--accent); border-color: var(--edge); }
  .btn-ghost:hover { border-color: var(--accent); }
  .btn:disabled { cursor: progress; opacity: .8; transform: none; }
  .spinner {
    width: 1em; height: 1em; border-radius: 50%;
    border: 2.5px solid rgba(255, 255, 255, .45); border-top-color: #fff;
    animation: spin .7s linear infinite;
  }
  .spinner[hidden] { display: none; }
  @keyframes spin { to { transform: rotate(360deg); } }

  /* ---- Header ---- */
  .top { position: sticky; top: 0; z-index: 10; background: rgba(253, 248, 241, .92); backdrop-filter: blur(8px); border-bottom: 1px solid var(--edge); }
  .top .wrap { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding-block: 12px; }
  .logo img { height: 44px; width: auto; }
  .nav { display: flex; align-items: center; gap: 22px; }
  .nav a { color: var(--ink); text-decoration: none; font-weight: 700; font-size: .98rem; }
  .nav a:hover { color: var(--accent); }
  .nav .btn { padding: .6em 1.1em; font-size: .95rem; color: var(--accent); }
  @media (max-width: 760px) {
    .nav a:not(.btn) { display: none; }
    .logo img { height: 36px; }
  }

  /* ---- Hero ---- */
  .hero {
    background:
      radial-gradient(circle at 12% 20%, rgba(248, 179, 26, .18), transparent 40%),
      radial-gradient(circle at 90% 10%, rgba(0, 168, 159, .12), transparent 38%),
      linear-gradient(180deg, var(--wash), var(--paper));
    padding-block: clamp(40px, 7vw, 88px);
  }
  .hero .wrap { display: grid; grid-template-columns: 1.15fr .85fr; gap: clamp(28px, 5vw, 64px); align-items: center; }
  @media (max-width: 900px) { .hero .wrap { grid-template-columns: 1fr; } }
  .eyebrow {
    display: inline-block; background: var(--gold-soft); color: #7A5200; font-weight: 800;
    font-size: .9rem; letter-spacing: .02em; padding: .35em .9em; border-radius: 999px; margin-bottom: 18px;
  }
  .hero h1 { font-size: clamp(2.3rem, 5.4vw, 3.8rem); font-weight: 700; letter-spacing: -.01em; }
  .hero h1 em { font-style: normal; color: var(--accent); }
  .hero .lead { font-size: clamp(1.1rem, 1.8vw, 1.25rem); color: var(--muted); margin-top: 18px; max-width: 34em; }
  .hero-actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 28px; }
  .stars { color: var(--gold); letter-spacing: .1em; }
  .hero-note { margin-top: 16px; font-size: .95rem; color: var(--muted); }
  .hero-note strong { color: var(--ink); }

  .card { background: var(--card); border: 1px solid var(--edge); border-radius: var(--radius); box-shadow: var(--shadow); }

  /* ---- Play card ---- */
  .play { padding: clamp(22px, 3vw, 32px); position: relative; overflow: hidden; }
  .play::before { content: ""; position: absolute; inset: 0 0 auto 0; height: 6px; background: linear-gradient(90deg, var(--accent), var(--pink), var(--gold), var(--teal)); }
  .play h2 { font-size: 1.7rem; }
  .play > p { color: var(--muted); margin-top: 6px; }
  .code-form { display: flex; gap: 10px; margin-top: 18px; }
  .code-form input {
    flex: 1; min-width: 0; font: 700 1.5rem/1 'Fredoka', sans-serif; letter-spacing: .18em; text-transform: uppercase;
    text-align: center; padding: .6em .4em; border: 2px solid var(--edge); border-radius: 14px; background: var(--paper); color: var(--ink);
  }
  .code-form input::placeholder { color: #BBAAB5; letter-spacing: .12em; }
  .code-form input:focus { border-color: var(--teal); }
  .code-form .btn { border-radius: 14px; }
  @media (max-width: 420px) { .code-form { flex-direction: column; } }
  .play-steps { list-style: none; padding: 0; margin: 20px 0 0; display: grid; gap: 10px; }
  .play-steps li { display: flex; gap: 12px; align-items: flex-start; font-size: .97rem; }
  .play-steps .ico { flex: none; width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; background: var(--wash); font-size: 1.05rem; }
  .play-foot { margin-top: 16px; padding-top: 14px; border-top: 1px dashed var(--edge); font-size: .93rem; color: var(--muted); }

  /* ---- Sections ---- */
  .section { padding-block: clamp(56px, 8vw, 96px); }
  .section-head { max-width: 720px; margin: 0 auto 40px; text-align: center; }
  .section-head p { color: var(--muted); margin-top: 12px; font-size: 1.1rem; }
  .kicker { display: block; font-weight: 800; text-transform: uppercase; letter-spacing: .12em; font-size: .8rem; color: var(--teal-dark); margin-bottom: 10px; }

  /* ---- Reviews ---- */
  .review { background: var(--card); border-block: 1px solid var(--edge); }
  .why { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
  @media (max-width: 860px) { .why { grid-template-columns: 1fr; } }
  .why .card { padding: 26px; box-shadow: none; background: var(--paper); }
  .why .emoji { font-size: 2rem; line-height: 1; }
  .why h3 { margin-top: 12px; }
  .why p { color: var(--muted); margin-top: 8px; }

  .how { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-top: 28px; }
  @media (max-width: 860px) { .how { grid-template-columns: 1fr; } }
  .how > div { padding: 28px; }
  .steps { counter-reset: step; list-style: none; margin: 18px 0 0; padding: 0; display: grid; gap: 14px; }
  .steps li { counter-increment: step; display: flex; gap: 14px; align-items: flex-start; }
  .steps li::before {
    content: counter(step); flex: none; width: 32px; height: 32px; border-radius: 50%;
    background: var(--accent); color: #fff; font: 700 1rem/32px 'Fredoka', sans-serif; text-align: center;
  }
  .tips { list-style: none; margin: 18px 0 0; padding: 0; display: grid; gap: 10px; }
  .tips li { padding-left: 30px; position: relative; }
  .tips li::before { content: "✓"; position: absolute; left: 0; top: 0; color: var(--teal); font-weight: 800; }
  .tips-example { margin-top: 18px; background: var(--gold-soft); border-radius: 14px; padding: 16px 18px; font-style: italic; color: #5B4200; }
  .review-cta { text-align: center; margin-top: 36px; }
  .review-cta .fine { margin-top: 14px; color: var(--muted); font-size: .95rem; }

  /* ---- Shop ---- */
  .covers { display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; }
  @media (max-width: 860px) { .covers { grid-template-columns: repeat(2, 1fr); } }
  .covers a { border-radius: 14px; overflow: hidden; box-shadow: var(--shadow); transition: transform .2s ease; }
  .covers a:hover { transform: translateY(-4px) rotate(-.5deg); }
  .features { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; margin-top: 32px; list-style: none; padding: 0; }
  .features li { background: var(--card); border: 1px solid var(--edge); border-radius: 999px; padding: .45em 1em; font-weight: 700; font-size: .95rem; }
  .center { text-align: center; margin-top: 32px; }

  /* ---- Newsletter (deliberately quiet) ---- */
  .newsletter .card { display: flex; align-items: center; justify-content: space-between; gap: 24px; padding: 26px 30px; background: linear-gradient(90deg, var(--wash), var(--card)); box-shadow: none; }
  .newsletter h3 { font-size: 1.25rem; }
  .newsletter p { color: var(--muted); margin-top: 4px; }
  @media (max-width: 700px) { .newsletter .card { flex-direction: column; align-items: flex-start; } }

  /* ---- Contact ---- */
  .contact .wrap { display: grid; grid-template-columns: .8fr 1.2fr; gap: clamp(28px, 5vw, 64px); align-items: start; }
  @media (max-width: 860px) { .contact .wrap { grid-template-columns: 1fr; } }
  .contact-intro p { color: var(--muted); margin-top: 14px; font-size: 1.08rem; }
  .contact-intro ul { margin: 20px 0 0; padding: 0; list-style: none; display: grid; gap: 10px; }
  .contact-intro li { display: flex; gap: 10px; }
  .contact-form { padding: clamp(22px, 3vw, 34px); display: grid; gap: 16px; }
  .row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
  @media (max-width: 560px) { .row { grid-template-columns: 1fr; } }
  label { display: block; font-weight: 700; font-size: .95rem; margin-bottom: 6px; }
  label .opt { font-weight: 400; color: var(--muted); }
  input[type=text], input[type=email], select, textarea {
    width: 100%; font: inherit; color: var(--ink); background: var(--paper);
    border: 2px solid var(--edge); border-radius: 12px; padding: .7em .85em;
  }
  input:focus, select:focus, textarea:focus { border-color: var(--accent); outline: none; }
  textarea { min-height: 150px; resize: vertical; }
  .hint { font-size: .88rem; color: var(--muted); margin-top: 5px; }
  .error { color: #B3261E; font-size: .9rem; margin-top: 5px; font-weight: 700; }
  .honey { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; }
  .sent { padding: clamp(28px, 4vw, 44px); text-align: center; }
  .sent .emoji { font-size: 2.6rem; }
  .sent h3 { margin-top: 10px; font-size: 1.6rem; }
  .sent p { color: var(--muted); margin-top: 8px; }

  /* ---- Footer ---- */
  footer { background: #231A26; color: #E9DDE6; padding-block: 40px; margin-top: clamp(56px, 8vw, 96px); }
  footer .wrap { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 18px; align-items: center; }
  footer a { color: #fff; text-decoration: none; font-weight: 700; }
  footer a:hover { color: var(--gold); }
  footer nav { display: flex; flex-wrap: wrap; gap: 20px; }
  footer small { color: #B7A6B3; }
</style>
</head>
<body>

<header class="top">
  <div class="wrap">
    <a class="logo" href="/" aria-label="Spanish Class Printables home">
      <img src="/game/logo.png" alt="Spanish Class Printables" width="164" height="44">
    </a>
    <nav class="nav" aria-label="Main">
      <a href="#play">Play a game</a>
      <a href="#review">Leave a review</a>
      <a href="#contact">Contact</a>
      <a class="btn btn-ghost" href="{{ config('site.store_url') }}" target="_blank" rel="noopener">Shop on TpT</a>
    </nav>
  </div>
</header>

<main>
  <section class="hero">
    <div class="wrap">
      <div>
        <span class="eyebrow">¡Muchas gracias! 💛</span>
        <h1>Thank you for teaching with <em>Spanish Class Printables.</em></h1>
        <p class="lead">
          If you picked up one of our packets, thank you — truly. Every worksheet and game comes from a small,
          independent shop that loves Spanish class as much as you do, and we hope it gave you an easy, happy lesson.
        </p>
        <div class="hero-actions">
          <a class="btn btn-primary" href="{{ config('site.review_url') }}" target="_blank" rel="noopener"><span class="stars" aria-hidden="true">★</span> Loved it? Leave a review</a>
          <a class="btn btn-ghost" href="#play">Play your game</a>
        </div>
        <p class="hero-note">A review takes about a minute, helps other teachers find us, <strong>and earns you TpT Credits</strong> toward your next resource.</p>
      </div>

      <div id="play" class="card play">
        <h2>Play your game</h2>
        <p>Type the room code printed in your packet.</p>

        {{-- The room shell reads ?code= itself (public/game/app.js), so a plain
             GET is all this needs — no JavaScript, and it works offline-first
             the same way as scanning the QR code. --}}
        <form class="code-form" action="/j" method="get">
          <label class="visually-hidden" for="room-code">Room code</label>
          <input id="room-code" name="code" type="text" placeholder="CODE" maxlength="8"
                 pattern="[A-Za-z0-9]{4,8}" title="4 to 8 letters and numbers"
                 autocomplete="off" autocapitalize="characters" spellcheck="false" required>
          <button class="btn btn-teal" type="submit">Play ▸</button>
        </form>

        <ul class="play-steps">
          <li><span class="ico" aria-hidden="true">📄</span><span>Find the code on your <strong>Teacher Script</strong> or <strong>Caller Sheet</strong> — or just scan its QR code.</span></li>
          <li><span class="ico" aria-hidden="true">📽️</span><span>Put this page on your projector or smartboard.</span></li>
          <li><span class="ico" aria-hidden="true">🎉</span><span>Play team quiz or bingo. The site keeps score, reads clues aloud and checks the cards.</span></li>
        </ul>

        <p class="play-foot">
          No accounts, no logins, nothing for students to install.
          @if (config('site.demo_room_code'))
            <br>No code yet? <a href="/j/{{ config('site.demo_room_code') }}">Try the demo game</a>.
          @endif
        </p>
      </div>
    </div>
  </section>

  <section id="review" class="section review">
    <div class="wrap">
      <div class="section-head">
        <span class="kicker">If it worked for your class</span>
        <h2>Your review makes a bigger difference than you'd think</h2>
        <p>
          We're a small shop, not a big publisher. When a teacher who used a packet takes a minute to say so,
          it's the single most helpful thing anyone can do for us.
        </p>
      </div>

      <div class="why">
        <div class="card">
          <div class="emoji" aria-hidden="true">🧑‍🏫</div>
          <h3>It helps another teacher</h3>
          <p>Spanish teachers pick resources by reading what other teachers say. Yours might be the review that saves someone their Sunday afternoon.</p>
        </div>
        <div class="card">
          <div class="emoji" aria-hidden="true">🌱</div>
          <h3>It helps us keep making more</h3>
          <p>Reviews help TpT show our resources to more teachers. Every one helps us grow and put more time into new topics and games.</p>
        </div>
        <div class="card">
          <div class="emoji" aria-hidden="true">🎁</div>
          <h3>It earns you TpT Credits</h3>
          <p>TpT gives you credits for leaving feedback on resources you've bought. They add up to money off your next purchase — from any seller.</p>
        </div>
      </div>

      <div class="how">
        <div class="card">
          <h3>How to leave a review (about a minute)</h3>
          <ol class="steps">
            <li><span>Go to <a href="{{ config('site.review_url') }}" target="_blank" rel="noopener">My Purchases on TpT</a> and sign in.</span></li>
            <li><span>Find your Spanish Class Printables packet and click <strong>Leave a Review</strong>.</span></li>
            <li><span>Pick your stars and write a sentence or two about how it went.</span></li>
          </ol>
        </div>
        <div class="card">
          <h3>What makes a review really helpful</h3>
          <ul class="tips">
            <li>The <strong>grade or level</strong> you teach</li>
            <li><strong>How you used it</strong> — a warm-up, review day, sub plans, early finishers</li>
            <li>What your <strong>students enjoyed</strong> most</li>
          </ul>
          <p class="tips-example">For example: “Used the bingo with my 6th graders before our family unit test. They begged to play again, and it took me zero prep!”</p>
        </div>
      </div>

      <div class="review-cta">
        <a class="btn btn-primary" href="{{ config('site.review_url') }}" target="_blank" rel="noopener"><span class="stars" aria-hidden="true">★★★★★</span> Leave a review on TpT</a>
        <p class="fine">Something not quite right? <a href="#contact">Tell us first</a> — we read every message and we'll make it right.</p>
      </div>
    </div>
  </section>

  <section class="section">
    <div class="wrap">
      <div class="section-head">
        <span class="kicker">More for your Spanish class</span>
        <h2>No-prep printables, ready when you are</h2>
        <p>Themed vocabulary packets and classroom games for seasons, holidays, culture and everyday topics. Print and go.</p>
      </div>

      <div class="covers">
        <a href="{{ config('site.store_url') }}" target="_blank" rel="noopener"><img src="/site/cover-la-familia.jpg" alt="La Familia vocabulary packet" width="600" height="600" loading="lazy"></a>
        <a href="{{ config('site.store_url') }}" target="_blank" rel="noopener"><img src="/site/cover-dia-de-los-muertos.jpg" alt="Día de los Muertos vocabulary packet" width="600" height="600" loading="lazy"></a>
        <a href="{{ config('site.store_url') }}" target="_blank" rel="noopener"><img src="/site/cover-picasso.jpg" alt="Pablo Picasso y el arte vocabulary packet" width="600" height="600" loading="lazy"></a>
        <a href="{{ config('site.store_url') }}" target="_blank" rel="noopener"><img src="/site/cover-pascua.jpg" alt="La Pascua vocabulary packet" width="600" height="600" loading="lazy"></a>
      </div>

      <ul class="features">
        <li>📚 Bilingual vocabulary lists</li>
        <li>🔎 Word searches</li>
        <li>✏️ Crosswords</li>
        <li>🔐 Secret message puzzles</li>
        <li>🎲 Bingo &amp; team quiz games</li>
        <li>✅ Answer keys</li>
      </ul>

      <div class="center">
        <a class="btn btn-ghost" href="{{ config('site.store_url') }}" target="_blank" rel="noopener">Browse the store on TpT →</a>
      </div>
    </div>
  </section>

  <section class="newsletter">
    <div class="wrap">
      <div class="card">
        <div>
          <h3>Want fresh Spanish activities in your inbox?</h3>
          <p>About once a month: new printables, seasonal ideas and the occasional freebie. No spam, unsubscribe anytime.</p>
        </div>
        {{-- MailerLite's popup form. universal.js finds this button by its
             ml-onclick-form class and fetches the form named in its onclick,
             reading it as the second ", "-separated piece — so the onclick's
             shape is load-bearing. The href is the fallback for when the form
             never arrived (script blocked, MailerLite down). --}}
        <a class="btn btn-ghost ml-onclick-form" href="https://spanishclassprintables.subscribepage.io/" target="_blank" rel="noopener"
           onclick="return openSignupForm(this, '{{ config('site.mailerlite_form') }}', true)">Join the mailing list</a>
      </div>
    </div>
  </section>

  <section id="contact" class="section contact">
    <div class="wrap">
      <div class="contact-intro">
        <span class="kicker">Get in touch</span>
        <h2>Help us make them better</h2>
        <p>Tell us what worked, what didn't, or what you wish existed. Every message is read by the people who make the packets.</p>
        <ul>
          <li><span aria-hidden="true">💡</span><span>A topic or holiday you'd love a packet for</span></li>
          <li><span aria-hidden="true">🎮</span><span>Ideas for the games, or anything that tripped your class up</span></li>
          <li><span aria-hidden="true">🛠️</span><span>A typo, a wrong answer, or a code that won't open</span></li>
        </ul>
      </div>

      @if (session('contact_sent'))
        <div class="card sent" role="status">
          <div class="emoji" aria-hidden="true">💌</div>
          <h3>¡Gracias! We got your message.</h3>
          <p>If you left your email, we'll get back to you soon.</p>
        </div>
      @else
        <form class="card contact-form" action="/contact" method="post" novalidate>
          @csrf
          <div class="honey" aria-hidden="true">
            <label for="website">Leave this empty</label>
            <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
          </div>
          {{-- When the form was shown, for ContactController's too-fast check.
               A resend after an error keeps the first one. --}}
          <input type="hidden" name="started" value="{{ old('started') ?: $contactStarted }}">
          {{-- Filled in by the script below on the first touch of the form. --}}
          <input type="hidden" name="human" value="">

          <div class="row">
            <div>
              <label for="name">Name <span class="opt">(optional)</span></label>
              <input id="name" name="name" type="text" maxlength="100" autocomplete="name" value="{{ old('name') }}">
              @error('name') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
              <label for="email">Email <span class="opt">(optional)</span></label>
              <input id="email" name="email" type="email" maxlength="255" autocomplete="email" value="{{ old('email') }}">
              <p class="hint">Only if you'd like a reply.</p>
              @error('email') <p class="error">{{ $message }}</p> @enderror
            </div>
          </div>

          <div>
            <label for="topic">What's it about?</label>
            <select id="topic" name="topic">
              @foreach ($topics as $value => $label)
                <option value="{{ $value }}" @selected(old('topic', 'idea') === $value)>{{ $label }}</option>
              @endforeach
            </select>
            @error('topic') <p class="error">{{ $message }}</p> @enderror
          </div>

          <div>
            <label for="message">Your message</label>
            <textarea id="message" name="message" maxlength="5000" required
                      placeholder="Which packet or game, and what would make it better?">{{ old('message') }}</textarea>
            @error('message') <p class="error">{{ $message }}</p> @enderror
          </div>

          {{-- Without JavaScript the script can't answer the human check, so
               the visitor does. With Turnstile on there is no way through
               without JavaScript, so say so instead. --}}
          <noscript>
            @if ($turnstile)
              <p class="error">Please turn on JavaScript to send a message.</p>
            @else
              <div>
                <label for="human_answer">What color is the sky? <span class="opt">(type “blue” — it shows you're not a robot)</span></label>
                <input id="human_answer" name="human_answer" type="text" maxlength="20" autocomplete="off">
              </div>
            @endif
          </noscript>
          @error('human') <p class="error">{{ $message }}</p> @enderror

          @if ($turnstile)
            <div>
              <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}" data-theme="light"></div>
              @error('turnstile') <p class="error">{{ $message }}</p> @enderror
            </div>
          @endif

          <div>
            <button class="btn btn-primary" type="submit">
              <span class="spinner" hidden aria-hidden="true"></span>
              <span class="label">Send message</span>
            </button>
          </div>
        </form>
      @endif
    </div>
  </section>
</main>

<footer>
  <div class="wrap">
    <small>© {{ date('Y') }} Spanish Class Printables</small>
    <nav aria-label="Footer">
      <a href="#play">Play a game</a>
      <a href="{{ config('site.review_url') }}" target="_blank" rel="noopener">Leave a review</a>
      <a href="{{ config('site.store_url') }}" target="_blank" rel="noopener">Our TpT store</a>
      <a href="#contact">Contact</a>
    </nav>
  </div>
</footer>

<script>
  /* Contact form: one press sends once. The button is disabled in the submit
     event, after the browser has already collected the form, so disabling it
     costs nothing. */
  (function () {
    var form = document.querySelector('.contact-form');
    if (!form) return;
    var button = form.querySelector('button[type=submit]');

    /* The human check, as on Eat Well Planner: a real visitor focuses, clicks
       or types somewhere in the form before sending, and that fills in the
       answer. A bot that posts the form without running the page never does. */
    function markHuman() { form.elements.human.value = @js(\App\Http\Controllers\ContactController::HUMAN_ANSWER); }
    ['focusin', 'pointerdown', 'keydown'].forEach(function (type) {
      form.addEventListener(type, markHuman, { once: true });
    });

    function reset() {
      button.disabled = false;
      button.removeAttribute('aria-busy');
      button.querySelector('.spinner').hidden = true;
      button.querySelector('.label').textContent = 'Send message';
    }

    form.addEventListener('submit', function (e) {
      if (button.disabled) { e.preventDefault(); return; }
      button.disabled = true;
      button.setAttribute('aria-busy', 'true');
      button.querySelector('.spinner').hidden = false;
      button.querySelector('.label').textContent = 'Sending…';
    });

    // Back/forward cache restores the page exactly as it was left — mid-send,
    // with a dead button — so put it back.
    window.addEventListener('pageshow', function (e) { if (e.persisted) reset(); });
  })();

  /* The mailing list button. Opens MailerLite's popup when the form has been
     fetched; otherwise returns true so the link opens the signup page instead
     of the click silently vanishing into MailerLite's queue. */
  function openSignupForm(link, slug, force) {
    var ml = window.ml;
    if (ml && ml.fn && ml.fn.popups && ml.fn.popups[slug]) {
      ml('show', slug, force);
      return false;
    }
    return true;
  }

  /* MailerLite's standard loader. The account has to be queued BEFORE
     universal.js runs: it reads the account once, at load, to fetch the
     click-to-open forms, and an account set afterwards fetches nothing.
     Popups are switched off so the account's own triggers (this form is set to
     appear after 5 seconds) never put the mailing list in front of the review
     ask — here it opens only when asked. */
  (function (w, d, e, u, f, l, n) {
    w[f] = w[f] || function () { (w[f].q = w[f].q || []).push(arguments); };
    l = d.createElement(e); l.async = 1; l.src = u;
    n = d.getElementsByTagName(e)[0]; n.parentNode.insertBefore(l, n);
  })(window, document, 'script', 'https://assets.mailerlite.com/js/universal.js', 'ml');
  ml('account', @js(config('site.mailerlite_account')));
  ml('enablePopups', false);
</script>
</body>
</html>
