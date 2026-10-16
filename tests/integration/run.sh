#!/usr/bin/env bash
# Runs the integration checks against a disposable WordPress in Docker, then removes it.
# Usage: bash tests/integration/run.sh            (set KEEP=1 to leave the site running)
set -uo pipefail

cd "$(dirname "$0")"
export MSYS_NO_PATHCONV=1
PORT="${LPM_TEST_PORT:-8089}"
SITE="http://127.0.0.1:$PORT"
AJAX="$SITE/wp-admin/admin-ajax.php"
UA='Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36'
FAILED=0

wp() { docker compose run --rm -T cli "$@" 2>/dev/null | tr -d '\r'; }
check() { # label, expected substring, actual
    if [[ "$3" == *"$2"* ]]; then echo "PASS $1"; else echo "FAIL $1 (got: ${3:0:160})"; FAILED=$((FAILED + 1)); fi
}
post() { curl -s -A "$UA" "$@" "$AJAX"; }
field() { python -c "import sys,json; print(json.load(sys.stdin).get('data',{}).get('$1',''))"; }
cleanup() { [ "${KEEP:-0}" = 1 ] || docker compose down -v >/dev/null 2>&1; }
trap cleanup EXIT

docker compose up -d >/dev/null 2>&1 || { echo "docker compose up failed"; exit 1; }
# -f treats the "Error establishing a database connection" page (HTTP 500) as not ready.
for _ in $(seq 1 60); do curl -sf -o /dev/null "$SITE/wp-admin/install.php" && break; sleep 2; done
wp core install --url="$SITE" --title=Integration --admin_user=admin --admin_password=integration-admin --admin_email=admin@example.test --skip-email >/dev/null
docker compose run --rm -T cli core is-installed >/dev/null 2>&1 || { echo "FAIL WordPress did not install"; exit 1; }

check "missing dependency blocks activation" "requires" "$(docker compose run --rm -T cli plugin activate landing-page-manager-pro 2>&1)"
wp plugin activate landing-page-manager landing-page-manager-pro >/dev/null
wp config set WP_DEBUG_LOG true --raw >/dev/null
wp config set WP_DEBUG_DISPLAY false --raw >/dev/null
curl -s -o /dev/null "$SITE/"   # applies first-activation defaults
PID=$(wp post create --post_type=landing_page --post_status=draft --post_title="Integration Page" --porcelain)
OTHER=$(wp post create --post_type=landing_page --post_status=draft --post_title="Other Page" --porcelain)
# Landing Page Manager keeps incomplete pages as drafts on publish; these bare fixtures are published directly.
wp eval "global \$wpdb; foreach ( [ $PID, $OTHER ] as \$id ) { \$wpdb->update( \$wpdb->posts, [ 'post_status' => 'publish', 'post_name' => 'lpm-fixture-' . \$id ], [ 'ID' => \$id ] ); clean_post_cache( \$id ); }" >/dev/null
check "fixture pages published" "publish" "$(wp post get "$PID" --field=post_status)"
URL=$(wp post url "$PID")
check "activation defaults applied" "1" "$(wp option get lpmanager_pro_defaults_applied)"

PAGE=$(curl -s -A "$UA" "$URL")
check "front end loads lpmanager-pro-tracking" "lpmanager-pro-tracking-js" "$PAGE"
[[ "$PAGE" == *"id='lpmanager-tracking-js'"* ]] && { echo "FAIL legacy handle printed"; FAILED=$((FAILED + 1)); } || echo "PASS legacy handle absent"

S=$(post -d "action=lpmanager_pro_session&post_id=$PID&count_view=1")
check "session counts the view" '"counted":true' "$S"
NONCE=$(echo "$S" | field nonce)
SESSION=$(echo "$S" | field session)
AUTH="post_id=$PID&nonce=$NONCE&session=$SESSION"
check "click recorded" "Click recorded" "$(post -d "action=lpmanager_record_click&$AUTH" --data-urlencode 'href=https://example.org/out?email=a@example.com#top' --data-urlencode 'text=Out')"
check "missing token rejected" "Invalid session" "$(post -d "action=lpmanager_record_click&post_id=$PID&nonce=$NONCE" --data-urlencode 'href=https://example.org/x')"
check "other-page token rejected" "Invalid session" "$(post -d "action=lpmanager_record_click&post_id=$OTHER&nonce=$NONCE&session=$SESSION" --data-urlencode 'href=https://example.org/x')"
check "tampered token rejected" "Invalid session" "$(post -d "action=lpmanager_record_click&post_id=$PID&nonce=$NONCE&session=${SESSION%?}0" --data-urlencode 'href=https://example.org/x')"
check "old nonce action rejected" "403" "$(post -o /dev/null -w '%{http_code}' -d "action=lpmanager_record_click&post_id=$PID&nonce=deadbeef00&session=$SESSION")"
check "conversion recorded" "Conversion recorded" "$(post -d "action=lpmanager_record_conversion&$AUTH")"
check "CTA view recorded" "Interaction recorded" "$(post -d "action=lpmanager_record_interaction&type=cta_view&$AUTH")"
check "engagement recorded" "Engagement recorded" "$(post -d "action=lpmanager_record_engagement&$AUTH&seconds=30&scroll_depth=50")"
post -d "action=lpmanager_record_engagement&$AUTH&seconds=7200" >/dev/null
check "heatmap recorded" "Heatmap recorded" "$(post -d "action=lpmanager_record_heatmap&$AUTH" --data-urlencode 'cells=[0,1,1,55,99]')"
check "rage click recorded" "Rage click recorded" "$(post -d "action=lpmanager_record_rage_click&$AUTH")"
check "bots excluded" '"track":false' "$(curl -s -A 'Mozilla/5.0 (compatible; Googlebot/2.1)' -d "action=lpmanager_pro_session&post_id=$PID&count_view=1" "$AJAX")"
check "Global Privacy Control honored" '"track":false' "$(post -H 'Sec-GPC: 1' -d "action=lpmanager_pro_session&post_id=$PID&count_view=1")"
check "Cubot phone tracked" '"track":true' "$(curl -s -A 'Mozilla/5.0 (Linux; Android 10; CUBOT X30) AppleWebKit/537.36 Chrome/120.0.0.0 Mobile Safari/537.36' -d "action=lpmanager_pro_session&post_id=$PID&count_view=0" "$AJAX")"
check "unpublished page rejected" "Invalid post" "$(post -d "action=lpmanager_pro_session&post_id=999999&count_view=1")"

# Old Conversions screen links redirect to Pro Analytics (R15).
JAR=$(mktemp)
curl -s -c "$JAR" -b "$JAR" -o /dev/null "$SITE/wp-login.php"
curl -s -c "$JAR" -b "$JAR" -o /dev/null -d "log=admin&pwd=integration-admin&wp-submit=Log+In&testcookie=1" "$SITE/wp-login.php"
check "Conversions screen redirects to Pro Analytics" "page=lp_pro_analytics" "$(curl -s -b "$JAR" -o /dev/null -w '%{redirect_url}' "$SITE/wp-admin/admin.php?page=lp_conversions")"
rm -f "$JAR"

docker compose run --rm -T cli eval-file /opt/lpm-integration/checks.php "$PID" 2>/dev/null | tr -d '\r' | tee /tmp/lpm-checks.$$
grep -q '^FAIL' /tmp/lpm-checks.$$ && FAILED=$((FAILED + 1))
# A crash before the summary line would otherwise hide every server-side check.
grep -q '^All server-side checks passed' /tmp/lpm-checks.$$ || { grep -q '^FAILED' /tmp/lpm-checks.$$ || { echo "FAIL server-side checks did not complete"; FAILED=$((FAILED + 1)); }; }
rm -f /tmp/lpm-checks.$$

# Real uninstall through WP-CLI (plugin files are mounted read-only, so they are not deleted).
wp plugin activate landing-page-manager-pro >/dev/null
wp option update lpmanager_pro_settings '{"delete_data_on_uninstall":true}' --format=json >/dev/null
wp option update lpmanager_pro_defaults_applied 1 >/dev/null
wp plugin deactivate landing-page-manager-pro >/dev/null
check "wp plugin uninstall runs" "Success" "$(docker compose run --rm -T cli plugin uninstall landing-page-manager-pro --skip-delete 2>&1)"
check "real uninstall removes Pro settings when opted in" "MISSING" "$(wp option get lpmanager_pro_settings >/dev/null 2>&1 && echo PRESENT || echo MISSING)"
check "real uninstall keeps shared analytics" "PRESENT" "$(wp option get lpmanager_link_clicks >/dev/null 2>&1 && echo PRESENT || echo MISSING)"

LOG=$(docker compose exec -T wp sh -c 'cat /var/www/html/wp-content/debug.log 2>/dev/null | grep -i "landing-page-manager-pro" || true')
check "no PHP notices or warnings from Pro" "" "" ; [ -n "$LOG" ] && { echo "FAIL debug.log: ${LOG:0:300}"; FAILED=$((FAILED + 1)); }

echo
[ "$FAILED" -eq 0 ] && echo "All integration checks passed." || echo "$FAILED integration check(s) failed."
exit "$FAILED"
