#!/usr/bin/env bash
# Run moodle-plugin-ci grunt; temporarily hide nested .git so mirror backup/restore works.
set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "$0")/.." && pwd)"
GIT_BAK=""

find_moodle_plugin_ci() {
    if command -v moodle-plugin-ci >/dev/null 2>&1; then
        return 0
    fi
    local moodleroot="${MOODLE_ROOT:-$(cd "$PLUGIN_DIR/../../.." && pwd)}"
    for candidate in \
        "$moodleroot/tools/moodle-plugin-ci" \
        "${PLUGIN_CI_HOME:-}" \
        "$HOME/moodle-plugin-ci" \
        "$HOME/tools/moodle-plugin-ci"; do
        if [[ -n "$candidate" && -x "$candidate/bin/moodle-plugin-ci" ]]; then
            export PATH="$candidate/bin:$PATH"
            return 0
        fi
    done
    echo "moodle-plugin-ci not found on PATH." >&2
    return 1
}

ensure_node() {
    if [[ -s "${NVM_DIR:-$HOME/.nvm}/nvm.sh" ]]; then
        # shellcheck disable=SC1090
        source "${NVM_DIR:-$HOME/.nvm}/nvm.sh"
        nvm use 22 2>/dev/null || nvm use 20 2>/dev/null || true
    fi
    if ! command -v node >/dev/null 2>&1; then
        echo "Node.js is required for Grunt. Install Node 20+." >&2
        return 1
    fi
    local nodemajor
    nodemajor=$(node -p "process.versions.node.split('.')[0]")
    if [[ "$nodemajor" -lt 18 ]]; then
        echo "Node.js 18+ required for Grunt (found $(node -v))." >&2
        echo "Run: nvm install 22 && nvm use 22" >&2
        return 1
    fi
}

cleanup() {
    if [[ -n "$GIT_BAK" && -d "$GIT_BAK" ]]; then
        mv "$GIT_BAK" "$PLUGIN_DIR/.git"
    fi
}
trap cleanup EXIT

ensure_node
find_moodle_plugin_ci

if [[ -d "$PLUGIN_DIR/.git" ]]; then
    GIT_BAK="$(mktemp -d)/timespent-git"
    mv "$PLUGIN_DIR/.git" "$GIT_BAK"
fi

exec moodle-plugin-ci grunt --max-lint-warnings 0 "$PLUGIN_DIR"
