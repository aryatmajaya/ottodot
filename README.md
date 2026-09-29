# Ottodot trial booking

A small Laravel application for trial bookings only: choose a child and class, submit a booking, record a mock payment, and inspect the confirmed roster. Every trial class has exactly four seats. Regular enrollment is deliberately excluded.

## Run from a fresh clone

Prerequisites: PHP 8.3+, Composer 2, Docker with Compose, and the PHP extensions required by Composer (including DOM/XML, mbstring, PDO MySQL and PDO SQLite). Docker must be running. The application containers use PHP 8.3 and MySQL 8.4.

From the repository root:

```bash
cp .env.example .env
composer install
composer check-platform-reqs
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
```

Open http://localhost. If port 80 or 3306 is occupied, set `APP_PORT=8080` or `FORWARD_DB_PORT=3307` in `.env` before starting Sail. For port 8080, also set `APP_URL=http://localhost:8080`. Database connections inside Sail still use `DB_HOST=mysql` and port 3306.

The UI uses Blade and inline CSS; no npm build is needed. Run Artisan through Sail for the demo because the hostname `mysql` resolves inside Docker.

For an existing installation, run `./vendor/bin/sail artisan migrate --seed`. The capacity migration normalizes old capacities to four and recalculates counters from confirmed bookings. It stops with an actionable error if any class already has more than four confirmed students; it never silently cancels those bookings.

Seeding again preserves existing students, booking/payment history, counters, and dates. It initializes example bookings only for classes with no bookings. To return a **disposable demo database** to its starting state, use the following command, which deletes its existing data:

```bash
./vendor/bin/sail artisan migrate:fresh --seed
```

## Demo data and verification steps

A fresh seed creates eight parents/children and two classes:

| Class | Confirmed | Seats remaining | Example |
| --- | --- | --- | --- |
| Maths Problem Solvers | Sofia Morris, Ida Foster | 2 | Maya Wilson has a failed payment, outside the roster |
| Science Lab: Forces | Noah Chen, Ava Patel, Leo Brooks | 1 | Mira Rahman and Eli Rivera can compete for the last seat |

Use the roster links on the home page to see HTML output. The equivalent JSON endpoint is `/admin/trial-classes/{id}/roster`.

1. **Successful payment:** select Mira + Maths, submit, observe `pending_payment`, then choose “Payment succeeds”. The status becomes `confirmed` and the roster gains one student.
2. **Duplicate:** submit Ida + Maths. The UI shows a conflict message; JSON clients receive HTTP 409. No extra booking is created.
3. **Failed payment:** select Eli + Maths and choose “Payment fails”. The status becomes `payment_failed`; the roster and seat count stay unchanged. Submitting the same pair again reopens the existing booking and preserves payment history.
4. **Last-seat race:** open two tabs. In tab A submit Mira + Science, and in tab B submit Eli + Science. Both can reach payment. Complete B first: B becomes `confirmed`. Complete A next: A becomes `cancelled` with `seat_unavailable`, and its mock attempt is `voided`. The Science roster contains exactly four children.

Pending bookings do not reserve seats. Displayed availability can become stale; the confirmation transaction makes the final decision.

## Tests

Fast suite using SQLite in memory:

```bash
./vendor/bin/sail artisan test --compact
```

The MySQL-only concurrent test is explicitly skipped on SQLite. To run all tests, including real lock contention, use the dedicated `testing` database created by Sail's MySQL initialization script:

```bash
docker compose exec -e DB_CONNECTION=mysql -e DB_DATABASE=testing laravel.test php artisan test --compact
```

Use this command only with a disposable test database. Feature tests rebuild tables. If the MySQL volume predates the Sail initialization script, create a separate database named `testing` and grant the Sail database user access before running it. Do not point tests at the demo database `ottodot`.

`MySqlLastSeatRaceTest` starts two independent PHP processes/connections. The test holds the class row lock and waits until MySQL's process list shows **both** payment queries waiting on that row, then releases it. It asserts one winner, one cancelled/voided loser, four roster entries, and four claimed seats. It does not merely send two sequential requests. The ordered B-before-A scenario is covered separately by `TrialBookingTest`.

Verified in Sail: **21 tests passed / 112 assertions on MySQL 8.4**; SQLite: **20 passed / 101 assertions**, with the one MySQL-only test skipped.

Other tests cover duplicate confirmed bookings, repeated submissions/payment results, failed payments and retries, rejecting a full class, invalid payment input, the HTML flow, confirmed-only roster output, repeatable seed data, and database capacity/count constraints.

## Backend design

### Data model

| Table | Important fields and responsibility |
| --- | --- |
| `parents` | Synthetic contact records; unique email |
| `students` | Child identity and parent foreign key |
| `trial_classes` | Title, subject, start time, fixed capacity 4, `confirmed_count` |
| `bookings` | Student/class foreign keys, status, `seat_claimed_at`, failure reason; unique student/class pair |
| `payment_attempts` | Booking, unique mock transaction ID, outcome, amount, failure code and processing timestamp |

The service updates the counter and confirmed booking in the same transaction. The database enforces capacity 4 and `0 <= confirmed_count <= capacity`: MySQL CHECK constraints, with equivalent SQLite insert/update triggers for the fast suite. These constraints do not independently prove that the counter equals the number of confirmed booking rows; application writes must use the booking service. Direct SQL changes to booking status require reconciliation.

### Endpoints and statuses

| Endpoint | Behavior |
| --- | --- |
| `GET /` | Child/class selection and roster links |
| `POST /bookings` | Validate IDs, create or reuse a pending booking |
| `GET /bookings/{booking}` | Booking status and payment form/history in JSON |
| `POST /bookings/{booking}/payment` | Record `result=succeeded` or `result=failed` |
| `GET /admin/trial-classes/{class}/roster` | Confirmed-only JSON roster |
| `GET /admin/trial-classes/{class}/roster/view` | HTML roster |

Send `Accept: application/json` for JSON booking responses. POST routes are Laravel web routes with CSRF protection; browser forms include the token. They are not stateless public payment webhooks.

- `pending_payment` → `confirmed` when approval claims a seat.
- `pending_payment` → `payment_failed` on a decline, without changing the counter.
- `pending_payment` → `cancelled` with `seat_unavailable` when approval arrives after the last seat is taken.
- Resubmitting a failed/cancelled pair can reopen the same booking if seats are available. Past attempts remain recorded.
- Resubmitting an already pending pair returns the existing booking. A confirmed pair returns HTTP 409.
- A repeated payment result for a confirmed booking returns that booking unchanged, without another attempt or seat increment. Other non-pending payments return HTTP 409 until the booking is reopened.

### Last-seat approach and tradeoffs

Both submission and payment lock in the same order: class, then booking. Payment confirmation uses an InnoDB transaction, `SELECT ... FOR UPDATE`, and a guarded increment equivalent to:

```sql
UPDATE trial_classes
SET confirmed_count = confirmed_count + 1
WHERE id = ? AND confirmed_count < capacity AND confirmed_count < 4;
```

The winning transaction changes the count from 3 to 4, records approval, and confirms its booking. The next transaction sees no capacity, records a voided attempt, and cancels its pending booking. A failure while writing either the attempt or booking rolls the transaction back, including the seat increment.

I chose confirmation-time allocation because it keeps the model small and avoids expiring seat holds. The accepted tradeoff is that a parent can reach payment and then lose the seat. Payments are mocked with amount zero: no real charge, refund, or provider call occurs. A real integration would need authorization/capture or refund handling, webhook authentication, stable event idempotency keys, and reconciliation. Reopened bookings would also need per-attempt IDs so delayed callbacks from an earlier attempt cannot confirm a newer attempt.

Class-level locking serializes requests for one class, which is acceptable for four-seat trials. Different classes can proceed independently.

### Responsibilities

- **UI:** show availability, disable full classes, include CSRF tokens, display outcomes. Availability is informational.
- **Backend:** validate that IDs exist and payment input is allowed; enforce transitions, duplicate handling, locking, and final capacity allocation. This demo does not authenticate parents or verify child ownership.
- **Database:** foreign keys, unique child/class and payment transaction identifiers, capacity/count constraints, atomic transactions and row locks.
- **Background jobs:** deliberately omitted. A real provider integration needs reconciliation of payments, voids/refunds and counter/roster mismatches.

## Scope, assumptions and time

This is a local demo using synthetic children. There is no authentication, parent scoping, admin authorization, real gateway, regular enrollment, seat reservation expiry, cancellation of confirmed bookings, or frontend build pipeline. The roster is openly accessible in the demo. Those cuts keep attention on booking correctness; authentication and provider integration are necessary before a public production deployment.

The original implementation notes estimated approximately three hours. A subsequent AI-assisted review and hardening pass fixed capacity, setup and seeding problems and added verification. That original estimate is not a verified total for both sessions; the submitter should report their actual total against the four-hour timebox.

## Monitoring and next steps

Monitor confirmed counters versus actual roster rows, any class over four, duplicate attempts, payment failure and seat-unavailable rates, transaction failures, deadlocks and lock wait time. With more time: parent/admin authorization, authenticated provider events with per-attempt idempotency, durable refund/reconciliation jobs, and CI against the deployment MySQL version. Seat holds would be a product decision, with explicit expiry and recovery behavior.

## Walkthrough and submission

Suggested 5–8 minute recording:

- 0:00–1:00: explain scope, setup and seed data.
- 1:00–2:30: show success, duplicate rejection, failure and the roster.
- 2:30–4:00: demonstrate B-before-A in two tabs.
- 4:00–6:00: show the service transaction, schema constraints and test output, including the concurrent MySQL test.
- 6:00–7:00: explain tradeoffs, AI corrections and next steps.

Before submission, publish this implementation in a public GitHub repository and supply its URL plus a real 5–8 minute walkthrough URL. Those external links are not yet available in this workspace. Ensure `.env` and database files stay out of the repository. See `AI_USAGE.md` for the AI workflow and verification record.
