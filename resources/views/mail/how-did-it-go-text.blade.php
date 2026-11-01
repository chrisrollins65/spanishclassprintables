{{-- Plain text, so nothing is escaped: HTML entities would show up literally. --}}
Hi {!! $teacher->name !!},

How did {!! $theme ? '“'.$theme.'”' : 'your game' !!} go with your class? Pick the one that fits:

@foreach ($faces as $face)
{!! $face['label'] !!}: {!! $face['url'] !!}

@endforeach
If something went wrong, just reply to this email. A real person reads it.

We'll only ask this once.

Spanish Class Printables
