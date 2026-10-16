# Integration Checks

Runs Landing Page Manager Pro against a disposable WordPress in Docker with the free Landing Page Manager plugin active, exercises the tracking endpoints, REST, admin screens, the link-row cleanup, and uninstall, then deletes the site.

## Requirements

- Docker with Compose, `bash`, `curl`, and Python 3 (for parsing JSON responses).
- A checkout of Landing Page Manager. By default it is expected next to this repository (`../landing-page-manager`); set `LPM_FREE_PLUGIN_PATH` to use another path.

## Run

```bash
bash tests/integration/run.sh
```

Options: `LPM_TEST_PORT` (default `8089`) and `KEEP=1` to leave the site running at `http://127.0.0.1:8089` (admin / `integration-admin`) for manual testing. Stop it with `docker compose -f tests/integration/compose.yml down -v`.

The script exits non-zero when any check fails. Both plugins are mounted read-only; the database lives in memory and the site is removed afterwards. Only fake data is used.

## Not covered

Browser-side behavior of `assets/js/tracking.js` (beacons, consent API, CTA observer), PHP 7.4 at runtime, multisite, page caches, consent plugins, and persistent object caches.
