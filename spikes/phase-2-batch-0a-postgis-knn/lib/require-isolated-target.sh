# shellcheck shell=bash
#
# Stage 0 spike runners: refuse any target that is not explicitly named and isolated.
#
# Sourced by run_spike.sh and provider-validation/run_provider_spike.sh. Call
# `stage0_require_isolated_target` before anything else touches psql.
#
# WHY
# ---
# Both runners used to take their target from libpq's own variables (PGHOST, PGDATABASE, ...).
# In the Replit workspace those are injected for EVERY shell and point at the production
# application database (PGHOST=helium, PGDATABASE=heliumdb). `${PGHOST:-172.17.0.2}` and a
# "PGHOST is required" check are therefore both satisfied by production, and step 00 of the
# spike runs DROP TABLE / CREATE EXTENSION.
#
# WHAT THIS DOES (deliberately small; it is not a copy of App\Support\Safeguards\ProductionDatabaseGuard)
# -----------------------------------------------------------------------------------------------------
#   1. The target must be named in SPIKE_PGHOST and SPIKE_PGDATABASE, spike-specific variables
#      nothing injects. The ambient PG* variables are never consulted for the target.
#   2. Refuses a Replit deployment (REPLIT_DEPLOYMENT) and APP_ENV=production outright.
#   3. Refuses the known production identity even when it is named explicitly: host `helium`
#      (or `helium.*`, anywhere in a comma-separated host list, with or without a port), and any
#      database whose name contains `heliumdb`.
#   4. Scrubs every inherited libpq variable that could redirect the connection (PGHOSTADDR
#      overrides -h; PGSERVICE pulls a target from a service file), then exports PGHOST and
#      PGDATABASE from the checked values.
#
# A refusal exits 3 (the same status as the PHP guard) before any psql command runs.

stage0_refuse() {
    {
        echo
        echo "=============================================================================="
        echo "STAGE 0 SPIKE REFUSED: $1"
        echo "=============================================================================="
        echo "Nothing ran. No psql command was executed."
        echo
        echo "Name a disposable, non-production target explicitly, e.g.:"
        echo "  SPIKE_PGHOST=172.17.0.2 SPIKE_PGDATABASE=spike <runner>"
        echo "The ambient PGHOST / PGDATABASE are never used: in the Replit workspace they are production."
        echo
    } >&2
    exit 3
}

stage0_lower() {
    printf '%s' "$1" | tr '[:upper:]' '[:lower:]'
}

stage0_trim() {
    local value="$1"
    value="${value#"${value%%[![:space:]]*}"}"
    value="${value%"${value##*[![:space:]]}"}"
    printf '%s' "$value"
}

stage0_require_isolated_target() {
    if [ -n "$(stage0_trim "${REPLIT_DEPLOYMENT:-}")" ]; then
        stage0_refuse "REPLIT_DEPLOYMENT is set; this is a Replit deployment (production runtime)."
    fi

    if [ "$(stage0_lower "$(stage0_trim "${APP_ENV:-}")")" = "production" ]; then
        stage0_refuse "APP_ENV=production."
    fi

    local host database
    host="$(stage0_trim "${SPIKE_PGHOST:-}")"
    database="$(stage0_trim "${SPIKE_PGDATABASE:-}")"

    if [ -z "$host" ]; then
        stage0_refuse "SPIKE_PGHOST is not set. The inherited PGHOST ('${PGHOST:-}') is deliberately not used."
    fi

    if [ -z "$database" ]; then
        stage0_refuse "SPIKE_PGDATABASE is not set. The inherited PGDATABASE ('${PGDATABASE:-}') is deliberately not used."
    fi

    local entry candidate
    local -a hosts
    IFS=',' read -r -a hosts <<< "$host"
    for entry in "${hosts[@]}"; do
        candidate="$(stage0_lower "$(stage0_trim "$entry")")"
        candidate="${candidate#[}"
        candidate="${candidate%%]*}"
        # Strip a trailing :port from a name or IPv4 address (not from a bare IPv6 literal).
        case "$candidate" in
            *:*:*) ;;
            *:*) candidate="${candidate%%:*}" ;;
        esac
        case "$candidate" in
            helium|helium.*)
                stage0_refuse "SPIKE_PGHOST names '${entry}', the production database host."
                ;;
        esac
    done

    case "$(stage0_lower "$database")" in
        *heliumdb*)
            stage0_refuse "SPIKE_PGDATABASE is '${database}', the production application database."
            ;;
    esac

    # Nothing inherited may redirect psql away from the checked target.
    unset PGHOST PGHOSTADDR PGPORT PGDATABASE PGUSER PGPASSWORD PGSERVICE PGSERVICEFILE PGOPTIONS

    export PGHOST="$host"
    export PGDATABASE="$database"
}
