{{-- Printable bingo cards for a teacher's own game.
     Laid out like the builder's own card sheets (renderCardSet and cardStyles
     in tptwsbuilder's src/gamePack.js): six to a sheet, two across and three
     down, each card bounded by the dashed line it is cut along. Two columns
     rather than three keeps the cell WIDTH, which is the dimension a long word
     needs; the height is there anyway.

     No room code anywhere. A card is photocopied, cut up and taken home, so
     the site's address is worth printing on it and the code never is. --}}
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>{{ $title }}</title>
<style>
  @page { size: Letter portrait; margin: 0; }
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: 'Trebuchet MS', 'Segoe UI', system-ui, sans-serif; color: #2d2d2d; background: #fff; }
  .sheet {
    width: 8.5in; height: 11in; padding: 0.3in; break-after: page;
    display: grid; grid-template-columns: 1fr 1fr; grid-template-rows: repeat(3, 1fr); gap: 0;
  }
  .sheet:last-child { break-after: auto; }
  /* The dashed rule IS the cut line — cards are meant to be separated. */
  .card { border: 1px dashed #b9ac99; padding: 0.12in; display: flex; flex-direction: column; overflow: hidden; }
  .card-head { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 3px; }
  .card-title { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #5a5a5a; }
  .card-id { font-size: 17px; font-weight: 700; color: #BE0087; line-height: 1; }
  .card-grid { width: 100%; flex: 1; border-collapse: collapse; table-layout: fixed; }
  .card-cell {
    border: 1.5px solid #2d2d2d; text-align: center; vertical-align: middle;
    padding: 2px; overflow: hidden; width: {{ number_format(100 / $size, 3) }}%;
  }
  .face { display: block; font-size: 13px; line-height: 1.1; }
  .free .face { color: #BE0087; font-weight: bold; letter-spacing: 1px; }
  .card-foot { font-size: 6px; color: #9a8b7e; text-align: center; margin-top: 3px; letter-spacing: 0.3px; }
</style>
</head>
<body>
@foreach ($sheets as $sheet)
  <div class="sheet">
    @foreach ($sheet as $card)
      <div class="card">
        <div class="card-head">
          <span class="card-title">{{ $title }}</span>
          <span class="card-id">{{ $card['id'] }}</span>
        </div>
        <table class="card-grid">
          @foreach ($card['grid'] as $row)
            <tr>
              @foreach ($row as $face)
                @if ($face === null)
                  <td class="card-cell free"><span class="face">GRATIS</span></td>
                @else
                  <td class="card-cell"><span class="face">{{ $face }}</span></td>
                @endif
              @endforeach
            </tr>
          @endforeach
        </table>
        <div class="card-foot">{{ $siteHome }}</div>
      </div>
    @endforeach
  </div>
@endforeach
</body>
</html>
