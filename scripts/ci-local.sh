#!/usr/bin/env bash
# Run CI on your machine before pushing: the `run:` steps of .github/workflows/ci.yml, read from the commit being
# checked, in order, each job in its own fresh git worktree (like a fresh CI checkout). Nothing comes from your
# working copy: not your .env, vendor/, node_modules/ or public/build (D-147: a copied .env once hid a CI failure).
#
# Usage: scripts/ci-local.sh [--at <commit>] [--mutation] [--browser] [--no-postgres] [--keep]
#   --at <commit>   check that commit (default: HEAD). Uncommitted changes are never included: commit first.
#   --mutation      also run the `mutation` job (needs the pcov PHP extension; about 2 minutes)
#   --browser       also run `browser-critical` and `browser-advisory` (installs Playwright's Chromium; about 3 minutes)
#   --no-postgres   skip the `postgres` job (not recommended: CI requires it)
#   --keep          keep the worktrees and logs for inspection
#
# Needs: git, PHP (the version CI tests: 8.4 or 8.5), Composer, Node and npm, and for the postgres job a
# PostgreSQL 16+ server and a role that may create databases (tests run in parallel, one database each):
#   CI_LOCAL_DB_HOST      default 127.0.0.1
#   CI_LOCAL_DB_PORT      default 5432
#   CI_LOCAL_DB_DATABASE  default votelab_ci (created by you once: createdb votelab_ci)
#   CI_LOCAL_DB_USERNAME  default: your user name
#   CI_LOCAL_DB_PASSWORD  default: empty
#
# Differences from CI, all about where it runs, never about order or files: your PHP, Node and Composer instead of
# the `uses:` setup actions (skipped); the postgres job's DB_* point at the server above; its "Server version" step,
# which queries CI's own service, is skipped.
set -uo pipefail

usage() { sed -n '2,23p' "$0" | sed 's/^# \{0,1\}//'; }

AT=HEAD MUTATION=0 BROWSER=0 POSTGRES=1 KEEP=0
while [ $# -gt 0 ]; do
    case "$1" in
        --at) AT="${2:?--at needs a commit}"; shift 2 ;;
        --mutation) MUTATION=1; shift ;;
        --browser) BROWSER=1; shift ;;
        --no-postgres) POSTGRES=0; shift ;;
        --keep) KEEP=1; shift ;;
        -h|--help) usage; exit 0 ;;
        *) echo "Unknown option: $1" >&2; usage >&2; exit 2 ;;
    esac
done

REPO="$(git rev-parse --show-toplevel 2>/dev/null)" || { echo "Run this inside the VoteLab repository." >&2; exit 2; }
COMMIT="$(git -C "$REPO" rev-parse --verify --quiet "$AT^{commit}")" || { echo "No commit \"$AT\"." >&2; exit 2; }
SHORT="$(git -C "$REPO" rev-parse --short "$COMMIT")"

for tool in git php composer node npm; do
    command -v "$tool" >/dev/null || { echo "Missing $tool: install it first." >&2; exit 2; }
done

DB_HOST="${CI_LOCAL_DB_HOST:-127.0.0.1}"
DB_PORT="${CI_LOCAL_DB_PORT:-5432}"
DB_DATABASE="${CI_LOCAL_DB_DATABASE:-votelab_ci}"
DB_USERNAME="${CI_LOCAL_DB_USERNAME:-${USER:-postgres}}"
DB_PASSWORD="${CI_LOCAL_DB_PASSWORD:-}"

if [ "$POSTGRES" = 1 ]; then
    if ! pg_error="$(php -r '
        [, $host, $port, $db, $user, $pass] = $argv;
        try { new PDO("pgsql:host={$host};port={$port};dbname={$db}", $user, $pass); }
        catch (Throwable $e) { echo $e->getMessage(); exit(1); }' \
        "$DB_HOST" "$DB_PORT" "$DB_DATABASE" "$DB_USERNAME" "$DB_PASSWORD" 2>&1)"; then
        echo "Can't reach PostgreSQL at $DB_USERNAME@$DB_HOST:$DB_PORT/$DB_DATABASE: $pg_error" >&2
        echo "Start PostgreSQL, run \`createdb $DB_DATABASE\`, set CI_LOCAL_DB_* (see --help), or pass --no-postgres." >&2
        exit 2
    fi
fi

if [ "$MUTATION" = 1 ] && ! php -r 'exit(extension_loaded("pcov") ? 0 : 1);'; then
    echo "--mutation needs the pcov PHP extension (pecl install pcov). Never on a production server (D-146)." >&2
    exit 2
fi

RUN_DIR="$(mktemp -d "${TMPDIR:-/tmp}/votelab-ci-local.XXXXXX")"
WORKTREES=()
cleanup() {
    for wt in "${WORKTREES[@]+"${WORKTREES[@]}"}"; do
        if [ "$KEEP" = 1 ]; then git -C "$REPO" worktree lock "$wt" >/dev/null 2>&1 || true
        else git -C "$REPO" worktree remove --force "$wt" >/dev/null 2>&1 || true; fi
    done
    [ "$KEEP" = 1 ] && echo "Kept: $RUN_DIR (remove the worktrees with: git worktree remove --force <path>)" || rm -rf "$RUN_DIR"
}
trap cleanup EXIT

# Steps run in a clean environment, as on a CI runner: only these variables come from your shell.
BASE_ENV=(PATH="$PATH" HOME="$HOME" TMPDIR="${TMPDIR:-/tmp}" USER="${USER:-ci}" LANG="${LANG:-C.UTF-8}" CI=true)
PG_ENV=(DB_CONNECTION=pgsql DB_HOST="$DB_HOST" DB_PORT="$DB_PORT" DB_DATABASE="$DB_DATABASE"
    DB_USERNAME="$DB_USERNAME" DB_PASSWORD="$DB_PASSWORD" DB_URL=)

FAILED=()
# run_job <job> <required: 1|0> [extra env…]
run_job() {
    local job=$1 required=$2; shift 2
    local wt="$RUN_DIR/$job" log="$RUN_DIR/$job.log" steps
    echo "── $job"
    git -C "$REPO" worktree add --detach --quiet "$wt" "$COMMIT" || { echo "   could not create a worktree"; FAILED+=("$job"); return; }
    WORKTREES+=("$wt")

    # The commit's own workflow, read by the commit's own reader when it has one (older commits: this checkout's).
    local reader="$wt/scripts/ci-local-steps.php"
    [ -f "$reader" ] || reader="$REPO/scripts/ci-local-steps.php"
    steps="$(php "$reader" "$wt/.github/workflows/ci.yml" "$job")" || { echo "   can't read job $job"; FAILED+=("$job"); return; }

    while IFS=$'\t' read -r name encoded; do
        [ -z "$name" ] && continue
        local command
        command="$(printf '%s' "$encoded" | base64 --decode)"
        if [ "$name" = "Server version" ]; then
            echo "   skip  $name (queries CI's own PostgreSQL service)"; continue
        fi
        if [[ "$command" == *'${{'* ]]; then
            echo "   FAIL  $name: uses a GitHub expression (\${{ … }}) this script can't evaluate"; FAILED+=("$job"); return
        fi
        printf '\n### %s\n' "$name" >>"$log"
        if (cd "$wt" && env -i "${BASE_ENV[@]}" "$@" bash --noprofile --norc -eo pipefail -c "$command") >>"$log" 2>&1; then
            echo "   ok    $name"
        else
            echo "   FAIL  $name"
            tail -n 25 "$log" | sed 's/^/         /'
            echo "         full log: $log"
            if [ "$required" = 1 ]; then FAILED+=("$job"); else echo "   (advisory job, as in CI: not counted as a failure)"; fi
            return
        fi
    done <<<"$steps"
}

echo "CI on $SHORT, locally ($(php -r 'echo PHP_VERSION;'), Node $(node -v)); logs in $RUN_DIR"
run_job dependency-audit 1
run_job quality 1
[ "$POSTGRES" = 1 ] && run_job postgres 1 "${PG_ENV[@]}"
[ "$MUTATION" = 1 ] && run_job mutation 1
if [ "$BROWSER" = 1 ]; then
    run_job browser-critical 1
    run_job browser-advisory 0
fi

echo
if [ ${#FAILED[@]} -eq 0 ]; then
    echo "PASSED: $SHORT is ready to push."
else
    KEEP=1
    echo "FAILED: ${FAILED[*]} (worktrees and logs kept for inspection)."
    exit 1
fi
