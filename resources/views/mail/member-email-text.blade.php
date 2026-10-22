{{-- Plain-text part: unescaped on purpose, since there is no HTML to inject into. --}}
{!! $messageSubject !!}

{!! $messageBody !!}

--
Sent by {!! $sender->name !!} to members of {!! config('app.name') !!}. Reply to this email to answer them.
{{ url('/') }}
