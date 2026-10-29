# Tests

No Composer / WordPress install needed (WP + WooCommerce are stubbed, DB is in-memory SQLite).

    php tests/run.php [name-filter]     # PHP suite (needs php-sqlite3/pdo_sqlite)
    node --test tests/js/consent.test.js   # phone normalisation
