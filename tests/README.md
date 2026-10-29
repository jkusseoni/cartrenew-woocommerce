# Tests

No Composer / WordPress install needed (WP + WooCommerce are stubbed, DB is in-memory SQLite).

    php tests/run.php [name-filter]     # PHP suite (needs php-sqlite3/pdo_sqlite)
    node --test tests/js/consent.test.js   # phone normalisation

## Integration (plugin <-> real backend routes)

    BACKEND_DIR=/path/to/cartrenew bash tests/integration/run.sh

Runs the real plugin code over real HTTP against the real `register-store`,
`abandoned-cart` and `feedback` route handlers (branch with the trial cap + feedback
merged; `npm install --ignore-scripts` in the backend first). Supabase is an in-memory fake and the
WhatsApp send is stubbed. Free limit is set to 3 for the run.
Not covered: a real WordPress/WooCommerce/MySQL stack (not available in CI sandbox).
