#!/usr/bin/env bash
set -euo pipefail

REPO="$(cd "$(dirname "$0")/.." && pwd)"
TARGET_TEST='StatsnewsletterGraphTest::testBothSeriesSumsCustomerAndVisitorBuckets'
BACKUP="$(mktemp)"
chmod 0600 "$BACKUP"

restore() {
  cp "$BACKUP" "$REPO/statsnewsletter.php"
  rm -f "$BACKUP"
}
trap restore EXIT

cp "$REPO/statsnewsletter.php" "$BACKUP"
perl -0pi -e 's/\$this->_values\[2\]\[\$key\] = \$this->_values\[0\]\[\$key\] \+ \$this->_values\[1\]\[\$key\];/\$this->_values[2][\$key] = \$this->_values[0][\$key];/g' "$REPO/statsnewsletter.php"

set +e
OUT="$(STATSNEWSLETTER_SHUFFLE_SEED=20261108 "$REPO/tests/run-suite.sh" 2>&1)"
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
if echo "$OUT" | grep -Eq '(Parse error|Fatal error)'; then
  echo 'mutation proof failed: parse or fatal error' >&2
  exit 1
fi

echo 'mutation proof passed'
