<!doctype html>
<html lang="en">
<body style="margin:0;padding:24px;background:#FDF8F1;color:#2D2D2D;font:16px/1.6 'Segoe UI',system-ui,sans-serif;">
  <div style="max-width:520px;margin:0 auto;">
    <p>Hi {{ $teacher->name }},</p>
    <p>How did {{ $theme ? '“'.$theme.'”' : 'your game' }} go with your class? Pick the one that fits:</p>
    <p>
      @foreach ($faces as $face)
        <a href="{{ $face['url'] }}" style="display:inline-block;margin:0 6px 8px 0;padding:10px 14px;border:1px solid #E8D2E0;border-radius:12px;background:#fff;color:#2D2D2D;text-decoration:none;font-weight:700;">{{ $face['face'] }} {{ $face['label'] }}</a>
      @endforeach
    </p>
    <p>If something went wrong, just reply to this email. A real person reads it.</p>
    <p style="color:#5A5A5A;font-size:14px;">We'll only ask this once.<br>Spanish Class Printables</p>
  </div>
</body>
</html>
