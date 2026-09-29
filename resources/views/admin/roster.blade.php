<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Roster | {{ $trialClass->title }}</title>
    <style>
        :root { font-family: Inter, ui-sans-serif, system-ui, sans-serif; color: #18212f; background: #f5f7fb; }
        body { margin: 0; padding: 48px 20px; }
        main { max-width: 900px; margin: auto; }
        .card { background: white; border-radius: 16px; padding: 28px; box-shadow: 0 10px 32px #18212f12; }
        table { width: 100%; border-collapse: collapse; margin-top: 18px; }
        th, td { text-align: left; padding: 12px; border-bottom: 1px solid #edf0f6; }
        th { color: #667085; font-size: 13px; text-transform: uppercase; letter-spacing: 0.04em; }
        .meta { display: flex; gap: 20px; flex-wrap: wrap; color: #475467; }
        .pill { display: inline-block; background: #ece9ff; color: #3f327f; border-radius: 999px; padding: 6px 10px; font-weight: 700; }
        .empty { color: #667085; }
        a { color: #3d2fc8; text-decoration: none; }
    </style>
</head>
<body>
    <main>
        <p><a href="{{ route('booking.create') }}">← Back to booking form</a></p>

        <section class="card">
            <h1>{{ $trialClass->title }}</h1>
            <div class="meta">
                <span>{{ $trialClass->starts_at->format('D, M j · g:ia') }}</span>
                <span>{{ $trialClass->confirmed_count }}/{{ $trialClass->capacity }} confirmed</span>
                <span class="pill">{{ $trialClass->available_seats }} seats left</span>
            </div>

            @if ($roster->isEmpty())
                <p class="empty">No confirmed students yet.</p>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Parent</th>
                            <th>Confirmed at</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($roster as $entry)
                            <tr>
                                <td>{{ $entry['student'] }}</td>
                                <td>{{ $entry['parent'] }}</td>
                                <td>{{ \Carbon\Carbon::parse($entry['confirmed_at'])->format('M j, Y · g:ia') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    </main>
</body>
</html>
