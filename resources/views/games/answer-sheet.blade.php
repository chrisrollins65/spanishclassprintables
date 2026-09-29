{{-- The team answer sheet for a teacher's own quiz: one per team, written on
     for the whole game.

     Ported from the builder's src/templates/jeopardyHoja.html and the way
     buildQuizPages fills it (src/gamePack.js) — the same grid as the board,
     a blank box under each value, and the final's strip across the foot.

     Two things are deliberately absent, both copied from the builder:

     · The final's CATEGORY is left blank. It is announced only when the board
       is empty, and a team reading it off their sheet all game has had the one
       thing the bet is meant to turn on.
     · No footer and no code. This is a page a child holds for a whole lesson
       and takes home; nothing on it needs to reach back here. --}}
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>{{ $title }}</title>
<style>
  /* The page's size is set here and the margin is the sheet's own padding,
     the way games/cards.blade.php does it. A percentage height needs a parent
     with a definite one, and neither html nor body has one — `height: 100%`
     quietly became "as tall as the boxes happen to be", which left the grid
     sitting in the top half of the paper. */
  @page { size: Letter landscape; margin: 0; }
  * { margin: 0; padding: 0; box-sizing: border-box; }
  html, body { height: 100%; }
  body { font-family: 'Trebuchet MS', 'Segoe UI', system-ui, sans-serif; color: #2d2d2d; background: #fff; }
  .sheet { width: 11in; height: 8.5in; padding: 0.35in; display: flex; flex-direction: column; }
  .title { font-size: 26px; font-weight: 700; text-align: center; letter-spacing: 1px; }
  .meta { display: flex; justify-content: space-between; align-items: baseline; font-size: 13px; margin: 4px 0 8px; }
  .meta .rule { display: inline-block; width: 2.2in; border-bottom: 1.5px solid #2d2d2d; }
  .kicker { letter-spacing: 1.5px; text-transform: uppercase; color: #5a5a5a; font-size: 11px; }
  .fill { flex: 1; }
  .board { width: 100%; height: 100%; border-collapse: separate; border-spacing: 4px; table-layout: fixed; }
  .board th {
    background: #F6DCEC; color: #2d2d2d; font-size: 12px; text-transform: uppercase;
    letter-spacing: .5px; padding: 5px 3px; border: 1.5px solid #2d2d2d; border-radius: 5px; line-height: 1.1;
  }
  .board td { border: 1.5px solid #2d2d2d; border-radius: 5px; padding: 3px 5px 5px; vertical-align: top; }
  .value { font-size: 12px; color: #BE0087; font-weight: 700; }
  /* Unruled on purpose: a rule inside the box marks off a strip as the part
     that counts, and children write around it or squeeze onto it. The border
     already says "write here"; this only reserves the height. */
  .write { height: .56in; }
  .final {
    display: flex; align-items: baseline; gap: .18in; margin-top: 7px;
    border: 1.5px solid #BE0087; border-radius: 6px; padding: 5px 8px; font-size: 12px;
  }
  .final-title { font-size: 15px; font-weight: 700; color: #BE0087; white-space: nowrap; }
  .final-box { display: flex; align-items: baseline; gap: 4px; white-space: nowrap; }
  .final-box.grow { flex: 1; }
  .final .money { color: #BE0087; font-weight: 700; }
  .final .rule { display: inline-block; width: 1in; border-bottom: 1.5px solid #2d2d2d; }
  .final .rule.wide { width: 1.6in; }
  /* A flex child, not width:100% — the box is the flex container, so the rule
     takes what is left of the strip. Same as the builder's jeopardyHoja.html;
     width:100% resolved against the wrong box and left the line a stub. */
  .final .rule.grow { width: auto; flex: 1; min-width: 1.5in; }
</style>
</head>
<body>
<div class="sheet">
  <h1 class="title">{{ $title }}</h1>
  <div class="meta">
    <span>Equipo / nombre: <span class="rule"></span></span>
    <span class="kicker">Escribe tu respuesta en la casilla que diga el maestro</span>
  </div>

  <div class="fill">
    <table class="board">
      <thead>
        <tr>@foreach ($categories as $name)<th>{{ $name }}</th>@endforeach</tr>
      </thead>
      <tbody>
        @foreach ($rows as $row)
          <tr>
            @foreach ($row as $value)
              <td>
                @if ($value !== null)
                  <div class="value">{{ $value }}</div>
                  <div class="write"></div>
                @endif
              </td>
            @endforeach
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  @if ($hasFinal)
    <div class="final">
      <span class="final-title">¡La Apuesta Final!</span>
      <span class="final-box">Categoría: <span class="rule wide"></span></span>
      <span class="final-box">Apostamos: <span class="money">$</span><span class="rule"></span></span>
      <span class="final-box grow">Respuesta: <span class="rule grow"></span></span>
    </div>
  @endif
</div>
</body>
</html>
