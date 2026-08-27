#!/usr/bin/env bash
#
# Deploy state: the exclusive deploy lock and the recorded deploy SHA.
#
# WHY THIS IS A SEPARATE, SOURCEABLE FILE
# ---------------------------------------
# `deploy/start-production.sh` migrates a database and binds port 5000, so it
# cannot be executed by a test. Keeping the lock and SHA logic here lets the real
# code be exercised against temporary directories and throwaway git repositories
# instead of being asserted on as text. Asserting on text cannot prove a lock
# excludes anything.
#
# WHERE STATE LIVES
# -----------------
# `/home/runner/workspace/.ops-backups` by default — the same persistent volume
# the database dumps use. Explicitly NOT `/tmp`: that is a separate device on
# this platform and does not survive a container restart, which is exactly how an
# earlier pre-migration backup was lost.
#
# `DEPLOY_STATE_DIR` overrides the location. It exists so tests can point at a
# temporary directory; production should leave it unset.
#
# shellcheck shell=bash

# Resolve (and create, privately) the directory holding deploy state.
deploy_state_dir() {
    local dir="${DEPLOY_STATE_DIR:-/home/runner/workspace/.ops-backups}"

    if [ ! -d "$dir" ]; then
        mkdir -p "$dir" || return 1
        chmod 700 "$dir" || return 1
    fi

    printf '%s\n' "$dir"
}

deploy_lock_file() {
    local dir
    dir="$(deploy_state_dir)" || return 1

    printf '%s/deploy.lock\n' "$dir"
}

deploy_sha_file() {
    local dir
    dir="$(deploy_state_dir)" || return 1

    printf '%s/current-deploy-sha\n' "$dir"
}

# Take the exclusive deploy lock, or fail.
#
# The lock is held on file descriptor 9 for the caller's shell. It covers the
# deployment-critical section — preflight, migration, startup preparation — and
# is released explicitly before the server takes over. Holding it across `exec`
# would leave the serving process owning the lock for its entire life, so no
# future deploy could ever acquire it.
#
# Bounded, never indefinite: a deploy that queues forever behind a stuck one is
# an outage with extra steps. `DEPLOY_LOCK_TIMEOUT` seconds, then fail closed.
acquire_deploy_lock() {
    local lock
    lock="$(deploy_lock_file)" || return 1

    # shellcheck disable=SC2093
    exec 9>"$lock" || return 1

    flock -w "${DEPLOY_LOCK_TIMEOUT:-30}" 9 || return 1
}

release_deploy_lock() {
    exec 9>&- || true
}

# The commit this release is serving.
#
# `DEPLOY_SHA` overrides the lookup for environments that ship without a `.git`
# directory. Absent that, the SHA comes from the repository and nowhere else —
# a deploy whose identity cannot be established is not recorded as anything.
resolve_deploy_sha() {
    local sha="${DEPLOY_SHA:-}"

    if [ -z "$sha" ]; then
        sha="$(git rev-parse HEAD 2>/dev/null)" || return 1
    fi

    # A short, ambiguous or empty value is worse than no record at all.
    case "$sha" in
        [0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f]) ;;
        *) return 1 ;;
    esac

    printf '%s\n' "$sha"
}

# Record the serving SHA atomically and privately.
#
# Written to a sibling temp file and renamed, so a reader can never observe a
# half-written SHA. Call this only once the deploy has earned it — after
# migrations succeeded, never before.
record_deploy_sha() {
    local sha target tmp

    sha="$(resolve_deploy_sha)" || return 1
    target="$(deploy_sha_file)" || return 1
    tmp="${target}.pending.$$"

    printf '%s\n' "$sha" > "$tmp" || return 1
    chmod 600 "$tmp" || return 1
    mv -f "$tmp" "$target" || return 1
}
