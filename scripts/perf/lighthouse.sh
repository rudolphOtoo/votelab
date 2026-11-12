#!/usr/bin/env bash
# Comparable Lighthouse (mobile) numbers for VoteLab's public pages, behind a production-like server:
# nginx with HTTP/2 + TLS, gzip (static + dynamic), year-long immutable caching of hashed assets, php-fpm with
# OPcache, APP_ENV=production-like settings, and a production asset build. Never touches the developer's own
# database, .env or Vite dev server: it exports the working tree to a scratch folder and seeds a fresh SQLite
# file there (demo program + one payment in each result state). Paystack is pointed at an unroutable address,
# so the "pending" result page is real and nothing leaves the machine.
#
# Usage: scripts/perf/lighthouse.sh [output-dir]    (default: storage/perf/<timestamp>)
# Needs: nginx, php-fpm, openssl, Google Chrome (or CHROME_PATH), node (npx downloads lighthouse@13.5.0).
set -euo pipefail

REPO="$(cd "$(dirname "$0")/../.." && pwd)"
STAMP="$(date +%Y%m%d-%H%M%S)"
OUT="${1:-$REPO/storage/perf/$STAMP}"
mkdir -p "$OUT" && OUT="$(cd "$OUT" && pwd)"
RUN="$(mktemp -d "${TMPDIR:-/tmp}/votelab-perf.XXXXXX")"
APP="$RUN/app"
PORT="${PERF_PORT:-8843}"
BASE="https://127.0.0.1:$PORT"
LIGHTHOUSE="lighthouse@13.5.0"
export CHROME_PATH="${CHROME_PATH:-/Applications/Google Chrome.app/Contents/MacOS/Google Chrome}"
mkdir -p "$OUT" "$APP"

cleanup() {
    [[ -f "$RUN/nginx.pid" ]] && kill "$(cat "$RUN/nginx.pid")" 2>/dev/null || true
    [[ -n "${FPM_PID:-}" ]] && kill "$FPM_PID" 2>/dev/null || true
    rm -rf "$RUN"
}
trap cleanup EXIT

echo "→ Exporting working tree and building production assets"
cd "$REPO"
# Tracked files deleted in the working tree are skipped (the export mirrors what's on disk).
git ls-files -co --exclude-standard -z \
    | while IFS= read -r -d '' file; do [ -e "$file" ] && printf '%s\0' "$file"; done \
    | rsync -a --from0 --files-from=- ./ "$APP/"
cp -R vendor "$APP/vendor"
rm -f "$APP/public/hot"
( cd "$REPO" && npm run build >/dev/null ) && rm -rf "$APP/public/build" && cp -R "$REPO/public/build" "$APP/public/build"
find "$APP/public/build" -type f \( -name '*.js' -o -name '*.css' -o -name '*.svg' -o -name '*.json' \) -exec gzip -9 -k -f {} \;

cat > "$APP/.env" <<ENV
APP_NAME=VoteLab
APP_ENV=staging
APP_KEY=base64:$(openssl rand -base64 32)
APP_DEBUG=false
APP_URL=$BASE
DB_CONNECTION=sqlite
DB_DATABASE=$RUN/app.sqlite
CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=sync
LOG_CHANNEL=single
LOG_LEVEL=error
MAIL_MAILER=array
PAYMENTS_DEFAULT_PROVIDER=paystack
PAYMENTS_LOGGING_ENABLED=false
PAYSTACK_SECRET_KEY=sk_test_perf_offline
PAYSTACK_BASE_URL=http://127.0.0.1:9
VOTING_PHONE_HASH_KEY=perf-only
ENV
: > "$RUN/app.sqlite"
cd "$APP"
php artisan migrate --seed --force >/dev/null
php artisan storage:link >/dev/null 2>&1 || true
php artisan tinker --execute="$(cat "$REPO/scripts/perf/seed-payment-states.php")" >/dev/null
php artisan config:cache >/dev/null && php artisan route:cache >/dev/null && php artisan view:cache >/dev/null

echo "→ Starting php-fpm and nginx (HTTP/2, TLS, gzip) on $BASE"
openssl req -x509 -newkey rsa:2048 -nodes -days 1 -subj "/CN=127.0.0.1" -keyout "$RUN/key.pem" -out "$RUN/cert.pem" >/dev/null 2>&1
NGINX_CONF_DIR="$(dirname "$(nginx -V 2>&1 | tr ' ' '\n' | grep -- '--conf-path=' | cut -d= -f2)")"
sed -e "s#__RUN__#$RUN#g" "$REPO/scripts/perf/php-fpm.conf" > "$RUN/php-fpm.conf"
sed -e "s#__RUN__#$RUN#g" -e "s#__ROOT__#$APP/public#g" -e "s#__PORT__#$PORT#g" \
    -e "s#__MIME__#$NGINX_CONF_DIR/mime.types#g" -e "s#__FASTCGI__#$NGINX_CONF_DIR/fastcgi_params#g" \
    "$REPO/scripts/perf/nginx.conf" > "$RUN/nginx.conf"
mkdir -p "$RUN"/tmp-{body,fastcgi,proxy,uwsgi,scgi}
php-fpm -y "$RUN/php-fpm.conf" -p "$RUN" & FPM_PID=$!
nginx -c "$RUN/nginx.conf" -p "$RUN" &
for _ in $(seq 1 40); do curl -sk -o /dev/null "$BASE/login" && break; sleep 0.25; done

PROGRAM="accra-campus-creatives/campus-excellence-awards-2026"
CATEGORY="$(cd "$APP" && php artisan tinker --execute='echo App\Models\AwardCategory::query()->where("status","open")->orderBy("id")->value("slug");')"
CODE="$(cd "$APP" && php artisan tinker --execute='echo App\Models\Nominee::query()->whereNull("archived_at")->orderBy("id")->value("nominee_code");')"
PAGES=(
    "landing|$BASE/$PROGRAM"
    "category|$BASE/$PROGRAM/categories/$CATEGORY"
    "nominee-deep-link|$BASE/$PROGRAM/$CODE"
    "payment-success|$BASE/payments/callback?reference=VL_PERF_SUCCESS"
    "payment-pending|$BASE/payments/callback?reference=VL_PERF_PENDING"
    "payment-failed|$BASE/payments/callback?reference=VL_PERF_FAILED"
    "sign-in|$BASE/login"
)

echo "→ Lighthouse (mobile, simulated throttling) → $OUT"
printf "| Page | Perf | A11y | Best pr. | SEO | LCP | CLS | TBT | Transfer |\n|---|---|---|---|---|---|---|---|---|\n" > "$OUT/summary.md"
for entry in "${PAGES[@]}"; do
    name="${entry%%|*}"; url="${entry#*|}"
    npx --yes "$LIGHTHOUSE" "$url" --quiet --form-factor=mobile --throttling-method=simulate \
        --chrome-flags="--headless=new --no-sandbox --ignore-certificate-errors" \
        --output=json --output=html --output-path="$OUT/$name" >/dev/null 2>&1 || {
            echo "  ! $name failed (HTTP $(curl -sk -o /dev/null -w '%{http_code}' "$url")); last log lines:"
            grep -E '\.(ERROR|CRITICAL|EMERGENCY)' "$APP/storage/logs/laravel.log" 2>/dev/null | tail -n 1 | cut -c1-400 | sed 's/^/    /'
        }
    node "$REPO/scripts/perf/summarize.mjs" "$OUT/$name.report.json" "$name" >> "$OUT/summary.md"
done
cat "$OUT/summary.md"
echo
node "$REPO/scripts/perf/summarize.mjs" --bfcache "$OUT"/*.report.json
