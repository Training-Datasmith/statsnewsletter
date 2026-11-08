#!/usr/bin/env bash
set -euo pipefail

REPO="$(cd "$(dirname "$0")/.." && pwd)"
TARGET_TEST='StatsnewsletterGraphTest::testBothSeriesSumsCustomerAndVisitorBuckets'
WORK="$(mktemp -d)"
chmod 0700 "$WORK"

cleanup() {
  rm -rf "$WORK"
}
trap cleanup EXIT

tar -C "$REPO" --exclude='.git' -cf - . | tar -C "$WORK" -xf -

perl -0pi -e 's/\$this->_values\[2\]\[\$key\] = \$this->_values\[0\]\[\$key\] \+ \$this->_values\[1\]\[\$key\];/\$this->_values[2][\$key] = \$this->_values[0][\$key];/g' "$WORK/statsnewsletter.php"

set +e
OUT="$(cd "$WORK" && USE_SUDO_DOCKER="${USE_SUDO_DOCKER:-0}" STATSNEWSLETTER_SHUFFLE_SEED=20261108 tests/run-suite.sh 2>&1)"
STATUS=$?
set -e

if [[ "$STATUS" -eq 0 ]]; then
  echo 'mutation proof failed: suite still green' >&2
  exit 1
fi

if ! echo "$OUT" | grep -q "$TARGET_TEST"; then
  echo "mutation proof failed: expected failure in $TARGET_TEST" >&2
  exit 1
fi

if ! echo "$OUT" | awk -v t="$TARGET_TEST" '
  $0 ~ t { inblock=1 }
  inblock && /Failed asserting/ { found=1; exit 0 }
  inblock && /^[0-9]+\) / && $0 !~ t { exit 1 }
  END { exit found ? 0 : 1 }
'; then
  echo 'mutation proof failed: target test did not fail with Failed asserting' >&2
  exit 1
fi

if echo "$OUT" | grep -Eq '(Parse error|Fatal error|ERRORS!|There was [0-9]+ error)'; then
  echo 'mutation proof failed: error-class PHPUnit or PHP failure' >&2
  exit 1
fi

echo 'mutation proof passed'
