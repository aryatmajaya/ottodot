<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ottodot | Trial booking</title>
    <style>
        :root { color-scheme: light; font-family: Inter, ui-sans-serif, system-ui, sans-serif; color: #18212f; background: #f5f7fb; }
        body { margin: 0; padding: 48px 20px; }
        main { max-width: 680px; margin: auto; }
        .eyebrow { color: #6154d9; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; font-size: .75rem; }
        .card { margin-top: 24px; padding: 28px; background: #fff; border-radius: 16px; box-shadow: 0 10px 32px #18212f12; }
        label { display: block; margin: 18px 0 7px; font-weight: 650; }
        select, button { font: inherit; padding: 11px 12px; border-radius: 8px; width: 100%; box-sizing: border-box; }
        select { border: 1px solid #cdd4df; background: #fff; }
        button { margin-top: 24px; border: 0; background: #5546d6; color: white; font-weight: 700; cursor: pointer; }
        button:hover { background: #4435bd; }
        .seat { color: #596579; font-size: .9rem; }
        .error { padding: 12px; background: #fff0ef; color: #a33a30; border-radius: 8px; }
    </style>
</head>
<body>
    <main>
        <p class="eyebrow">Ottodot trial class</p>
        <h1>Book a trial class</h1>
        <p>Choose a child, choose a class, then record a mock payment result.</p>

        <section class="card">
            @if ($errors->any())
                <div class="error">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('bookings.store') }}">
                @csrf
                <label for="student_id">Child</label>
                <select id="student_id" name="student_id" required>
                    <option value="">Select a child</option>
                    @foreach ($students as $student)
                        <option value="{{ $student->id }}" @selected(old('student_id') == $student->id)>
                            {{ $student->full_name }} — {{ $student->parent->name }}
                        </option>
                    @endforeach
                </select>

                <label for="trial_class_id">Available trial class</label>
                <select id="trial_class_id" name="trial_class_id" required>
                    <option value="">Select a class</option>
                    @foreach ($trialClasses as $trialClass)
                        <option value="{{ $trialClass->id }}" @disabled($trialClass->available_seats === 0) @selected(old('trial_class_id') == $trialClass->id)>
                            {{ $trialClass->title }} · {{ $trialClass->starts_at->format('D, M j g:ia') }}
                            ({{ $trialClass->available_seats }}/{{ $trialClass->capacity }} seats left)
                        </option>
                    @endforeach
                </select>

                <button type="submit">Continue to mock payment</button>
            </form>
        </section>

        <section class="card">
            <h2>Teacher / admin rosters</h2>
            <ul>
                @foreach ($trialClasses as $trialClass)
                    <li><a href="{{ route('trial-classes.roster.view', $trialClass) }}">{{ $trialClass->title }}</a></li>
                @endforeach
            </ul>
        </section>

        <p class="seat">The seats displayed here are informational. The backend remains authoritative when payment completes.</p>
    </main>
</body>
</html>
