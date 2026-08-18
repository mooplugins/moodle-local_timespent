#!/usr/bin/env bash
# Run the same moodle-plugin-ci steps as .github/workflows/moodle-ci.yml (from plugin root).
# Requires moodle-plugin-ci on PATH.
set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "$0")/.." && pwd)"
SKIP_GRUNT=0
STRICT=0
MOODLE_DIR="${MOODLE_DIR:-}"

# Prefer an explicit Moodle root; otherwise try the parent public/ tree used in this monorepo.
if [[ -z "$MOODLE_DIR" && -f "$PLUGIN_DIR/../../../config.php" ]]; then
    MOODLE_DIR="$(cd "$PLUGIN_DIR/../../.." && pwd)"
fi

usage() {
    cat <<'EOF'
Usage: tools/ci-check.sh [options]

Runs moodle-plugin-ci lint steps against this plugin directory.

Options:
  --skip-grunt   Skip Grunt/ESLint
  --strict       Stop on first failure (default: continue, exit non-zero at end)
  -h, --help     Show this help

Environment:
  MOODLE_DIR     Moodle root containing config.php (auto-detected when possible)
EOF
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --skip-grunt) SKIP_GRUNT=1; shift ;;
        --strict) STRICT=1; shift ;;
        -h|--help) usage; exit 0 ;;
        *) echo "Unknown option: $1" >&2; usage; exit 1 ;;
    esac
done

if ! command -v moodle-plugin-ci >/dev/null 2>&1; then
    echo "moodle-plugin-ci not found. Install with:" >&2
    echo "  composer create-project -n --no-dev --prefer-dist moodlehq/moodle-plugin-ci ~/moodle-plugin-ci ^4.5" >&2
    echo "  export PATH=\"\$HOME/moodle-plugin-ci/bin:\$PATH\"" >&2
    exit 1
fi

FAILED=0

run_step() {
    local label="$1"
    shift
    echo "==> $label"
    set +e
    "$@"
    local status=$?
    set -e
    if [[ $status -eq 0 ]]; then
        echo "OK: $label"
        return 0
    fi
    FAILED=1
    echo "FAIL: $label" >&2
    if [[ $STRICT -eq 1 ]]; then
        exit $status
    fi
    return $status
}

MOODLE_ARGS=()
if [[ -n "$MOODLE_DIR" ]]; then
    MOODLE_ARGS=(--moodle "$MOODLE_DIR")
fi

run_step "phplint" moodle-plugin-ci phplint "$PLUGIN_DIR" || true
run_step "phpmd (informational)" moodle-plugin-ci phpmd "$PLUGIN_DIR" || true
run_step "codechecker" moodle-plugin-ci codechecker --max-warnings 0 "$PLUGIN_DIR"
run_step "phpdoc" moodle-plugin-ci phpdoc "${MOODLE_ARGS[@]}" "$PLUGIN_DIR"
run_step "validate" moodle-plugin-ci validate "${MOODLE_ARGS[@]}" "$PLUGIN_DIR"
run_step "savepoints" moodle-plugin-ci savepoints "$PLUGIN_DIR"
run_step "mustache" moodle-plugin-ci mustache "${MOODLE_ARGS[@]}" "$PLUGIN_DIR"

if [[ $SKIP_GRUNT -eq 0 ]]; then
    if [[ -f "$PLUGIN_DIR/tools/ci-grunt.sh" ]]; then
        run_step "grunt" bash "$PLUGIN_DIR/tools/ci-grunt.sh"
    else
        run_step "grunt" moodle-plugin-ci grunt --max-lint-warnings 0 "$PLUGIN_DIR"
    fi
else
    echo "SKIP: grunt (--skip-grunt)"
fi

if [[ $FAILED -ne 0 ]]; then
    echo "" >&2
    echo "CI check failed. Fix errors above before opening a PR." >&2
    exit 1
fi

echo ""
echo "CI check complete."
