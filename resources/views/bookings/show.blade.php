<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking #{{ $booking->id }} | Ottodot</title>
    <style>
        :root { font-family: Inter, ui-sans-serif, system-ui, sans-serif; color: #18212f; background: #f5f7fb; }
        body { margin: 0; padding: 48px 20px; } main { max-width: 680px; margin: auto; }
        .card { padding: 28px; background: white; border-radius: 16px; box-shadow: 0 10px 32px #18212f12; }
        .status { display: inline-block; padding: 5px 10px; border-radius: 999px; color: #3f327f; background: #ece9ff; font-weight: 700; }
        .confirmed { color: #146c43; background: #dff7e9; } .payment_failed, .cancelled { color: #9e332a; background: #fff0ef; }
        button { margin: 10px 8px 0 0; padding: 11px 13px; border: 0; border-radius: 8px; background: #5546d6; color: white; font: inherit; font-weight: 700; cursor: pointer; }
        .failure { color: #9e332a; } dl { display: grid; grid-template-columns: 130px 1fr; gap: 12px; } dt { color: #667085; }
        form { display: inline; } .fail { background: #657184; }
    </style>
</head>
<body>
    <main>
        <p><a href="{{ route('booking.create') }}">← New booking</a></p>
        @if ($errors->any())
            <p class="failure">{{ $errors->first() }}</p>
        @endif
        <section class="card">
            <p class="status {{ $booking->status }}">{{ str_replace('_', ' ', $booking->status) }}</p>
            <h1>Trial booking #{{ $booking->id }}</h1>
            <dl>
                <dt>Child</dt><dd>{{ $booking->student->full_name }}</dd>
                <dt>Parent</dt><dd>{{ $booking->student->parent->name }}</dd>
                <dt>Class</dt><dd>{{ $booking->trialClass->title }}</dd>
                <dt>Starts</dt><dd>{{ $booking->trialClass->starts_at->format('l, F j · g:ia') }}</dd>
            </dl>

            @if ($booking->status === \App\Models\Booking::PENDING_PAYMENT)
                <h2>Mock payment</h2>
                <p>Choose a result to exercise the booking flow.</p>
                <form method="POST" action="{{ route('bookings.payment', $booking) }}">
                    @csrf
                    <input type="hidden" name="result" value="succeeded">
                    <button type="submit">Payment succeeds</button>
                </form>
                <form method="POST" action="{{ route('bookings.payment', $booking) }}">
                    @csrf
                    <input type="hidden" name="result" value="failed">
                    <button class="fail" type="submit">Payment fails</button>
                </form>
            @elseif ($booking->status === \App\Models\Booking::CONFIRMED)
                <p>Your child is on the confirmed roster.</p>
            @else
                <p class="failure">{{ $booking->failure_reason === 'seat_unavailable' ? 'The final seat was claimed while payment was in progress. No charge is retained.' : 'The mock payment was declined. No seat was added to the roster.' }}</p>
            @endif
        </section>
    </main>
</body>
</html>
