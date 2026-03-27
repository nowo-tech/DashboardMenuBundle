#!/usr/bin/env sh
set -eu

RAW_FILE="${1:-coverage-php.txt}"

# Color when useful: TTY, or FORCE_COLOR, and not NO_COLOR (https://no-color.org/)
use_color() {
  [ -z "${NO_COLOR:-}" ] && { [ -t 1 ] || [ -n "${FORCE_COLOR:-}" ]; }
}

if [ ! -f "$RAW_FILE" ]; then
  if use_color; then
    printf '\033[31m%s\033[0m\n' "ERROR: coverage output file not found: $RAW_FILE" >&2
  else
    echo "ERROR: coverage output file not found: $RAW_FILE" >&2
  fi
  exit 1
fi

# PHPUnit uses --color=always: summary lines look like
#   \x1b[30;42m  Lines:   99.60% (3450/3464)\x1b[0m
# so "Lines:" is NOT at column 1 after optional spaces — escapes come first.
strip_ansi() {
  sed 's/\x1b\[[0-9;]*m//g' "$@"
}

# Prefer the Summary block (global total), not per-class lines.
VALUE="$(
  strip_ansi "$RAW_FILE" | tr -d '\r' | awk '
    /^[[:space:]]*Summary:/{ want_lines = 1; next }
    want_lines && /^[[:space:]]*Lines:[[:space:]]+/ {
      if (match($0, /[0-9]+\.[0-9]+%|[0-9]+%/)) {
        s = substr($0, RSTART, RLENGTH)
        gsub(/%/, "", s)
        print s
        exit
      }
    }
  '
)"

# Fallback: first "Lines:" at line start (stripped file) — PHPUnit summary order.
if [ -z "${VALUE:-}" ]; then
  VALUE="$(
    strip_ansi "$RAW_FILE" | tr -d '\r' | awk '
      /^[[:space:]]*Lines:[[:space:]]+/ {
        if (match($0, /[0-9]+\.[0-9]+%|[0-9]+%/)) {
          s = substr($0, RSTART, RLENGTH)
          gsub(/%/, "", s)
          print s
          exit
        }
      }
    '
  )"
fi

if [ -z "${VALUE:-}" ]; then
  if use_color; then
    printf '\033[31m%s\033[0m\n' "ERROR: Could not extract PHP Lines coverage percentage from ${RAW_FILE}" >&2
  else
    echo "ERROR: Could not extract PHP Lines coverage percentage from ${RAW_FILE}" >&2
  fi
  exit 1
fi

if use_color; then
  printf '\033[32m%s\033[0m\n' "Global PHP coverage (Lines): ${VALUE}%"
else
  echo "Global PHP coverage (Lines): ${VALUE}%"
fi
