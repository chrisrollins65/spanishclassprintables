{{-- Plain text, so nothing is escaped: HTML entities would show up literally. --}}
New message from the Spanish Class Printables website.

Topic: {!! $contactMessage->topicLabel() !!}
Name:  {!! $contactMessage->name ?: '(not given)' !!}
Email: {!! $contactMessage->email ?: '(not given — no way to reply)' !!}

{!! $contactMessage->message !!}
