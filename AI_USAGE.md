# AI usage

## Tools and tasks

I used OpenAI Codex to inspect the requirements and code, identify gaps, implement fixes, expand tests, and update the documentation. Laravel Boost provided project guidance. Laravel Sail, Composer and PHPUnit are development/verification tools, not AI tools.

AI assisted with the data model, transaction design, initial feature tests, README structure, capacity enforcement, repeatable seeding, and concurrency verification.

## Where AI helped

AI accelerated tracing the complete booking path from request validation through database writes to roster output. During hardening, it also helped construct a MySQL test that runs two independent PHP processes and waits until both contend for the same row lock, rather than treating sequential requests as evidence of concurrency safety.

## Corrections to AI-assisted output

The original notes describe rejecting UI availability as the source of truth for seat allocation. The final decision belongs in a backend transaction.

The follow-up audit also found that the implementation did not match its README: two seeded classes had capacities of five and six, reseeding reset counters without deleting confirmed bookings, and the sample environment file could not be parsed. These were corrected instead of accepting the existing “maximum four” claim. The old sequential last-seat test was kept for the required B-before-A ordering and supplemented with real MySQL contention coverage.

## What I would change about the workflow

Start with a short acceptance checklist and test plan, especially fixed capacity, fresh-clone setup, seed repeatability and concurrent writes. Review AI-generated changes in small steps and distinguish inspected code from behavior actually executed. Record elapsed time while working instead of reconstructing a timebox afterward.

## Verification

Commands for reproducible verification:

```bash
./vendor/bin/sail artisan test --compact
docker compose exec -e DB_CONNECTION=mysql -e DB_DATABASE=testing laravel.test php artisan test --compact
```

The SQLite suite covers the HTTP/HTML flow, duplicate and payment retries, capacity constraints and seed repeatability. It explicitly skips the MySQL-only contention test. The MySQL command uses a disposable database and runs the concurrent case too.

The follow-up environment initially lacked PHP test/database extensions and could not access Docker. Required extensions and a temporary MySQL server were extracted under `/tmp` to execute verification without changing system packages or touching the application's database. After Docker became available, both documented test commands were run through Sail. Final results: **21 tests passed, 112 assertions on MySQL 8.4**; **20 passed, 101 assertions and one intentional MySQL-only skip on SQLite**. The simultaneous-payment test also passed on temporary MySQL 8.0.46. Laravel Pint passed for the changed PHP files, Composer validation passed, and `.env.example` parsed successfully. The existing demo database was upgraded with `migrate --seed` without deleting its bookings.

The repo URL, recorded video and the submitter's actual total time cannot be established from this workspace alone. No public submission or video is claimed here.
