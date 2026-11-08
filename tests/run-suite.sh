#!/usr/bin/env bash
set -euo pipefail

REPO="$(cd "$(dirname "$0")/.." && pwd)"
if [[ -n "${DOCKER:-}" ]]; then
  read -r -a DOCKER_CMD <<<"$DOCKER"
else
  DOCKER_CMD=()
  if docker info >/dev/null 2>&1; then
    DOCKER_CMD=(docker)
  elif sudo docker info >/dev/null 2>&1; then
    DOCKER_CMD=(sudo docker)
  else
    echo 'docker is not available' >&2
    exit 127
  fi
fi
PHP_IMAGE_DIGEST='php@sha256:6ce95208609dc66df163ab936c970b3b34cd901b85c747102c5999f08ade9143'
MARIADB_IMAGE='mariadb@sha256:6a12617127de7ad42895232407abf403ae79f465bb818a8ac7ffbb97b8b18937'
PHPUNIT_URL='https://phar.phpunit.de/phpunit-4.8.36.phar'
PHPUNIT_ASC_URL='https://phar.phpunit.de/phpunit-4.8.36.phar.asc'
PHPUNIT_SHA256='ab8bc3017a64cf75a2112f5e089a1d5b0c4cc2116556d60c1af5e3f584e85c5e'
PHPUNIT_FPR='D8406D0D82947747293778314AA394086372C20A'
SEED="${STATSNEWSLETTER_SHUFFLE_SEED:-20261108}"
DB_NAME='statsnewsletter'
DB_USER='statsnewsletter'
DB_PASS='statsnewsletter'
DB_ROOT_PASS='rootstatsnewsletter'
CONTAINER_DB='statsnewsletter-db'
IMAGE_PHP='statsnewsletter-php56'

cleanup() {
  if [[ -n "${PHAR_DIR:-}" && -d "$PHAR_DIR" ]]; then
    rm -rf "$PHAR_DIR"
  fi
  if [[ -n "${OUT_DIR:-}" && -d "$OUT_DIR" ]]; then
    rm -rf "$OUT_DIR"
  fi
  if [[ -n "${GEN_DIR:-}" && -d "$GEN_DIR" ]]; then
    rm -rf "$GEN_DIR"
  fi
}
trap cleanup EXIT

PHAR_DIR="$(mktemp -d)"
chmod 0700 "$PHAR_DIR"
OUT_DIR="$(mktemp -d)"
chmod 0700 "$OUT_DIR"
GEN_DIR="$(mktemp -d)"
chmod 0700 "$GEN_DIR"

GNUPGHOME="$(mktemp -d)"
chmod 0700 "$GNUPGHOME"
export GNUPGHOME
curl -fsSL "$PHPUNIT_URL" -o "$PHAR_DIR/phpunit-4.8.36.phar"
curl -fsSL "$PHPUNIT_ASC_URL" -o "$PHAR_DIR/phpunit-4.8.36.phar.asc"
curl -fsSL 'https://sebastian-bergmann.de/gpg.asc' | gpg --batch --import
gpg --batch --status-fd 1 --verify "$PHAR_DIR/phpunit-4.8.36.phar.asc" "$PHAR_DIR/phpunit-4.8.36.phar" 2>/dev/null | grep -q "\[GNUPG:\] VALIDSIG .*${PHPUNIT_FPR}"
echo "$PHPUNIT_SHA256  $PHAR_DIR/phpunit-4.8.36.phar" | sha256sum -c -
rm -rf "$GNUPGHOME"

"${DOCKER_CMD[@]}" build -t "$IMAGE_PHP" -f "$REPO/tests/docker/php-cli.Dockerfile" "$REPO/tests/docker"

if ! "${DOCKER_CMD[@]}" inspect -f '{{.State.Running}}' "$CONTAINER_DB" 2>/dev/null | grep -q true; then
  "${DOCKER_CMD[@]}" rm -f "$CONTAINER_DB" 2>/dev/null || true
  "${DOCKER_CMD[@]}" run -d --name "$CONTAINER_DB" \
    -e TZ=UTC \
    -e MYSQL_ROOT_PASSWORD="$DB_ROOT_PASS" \
    -e MYSQL_DATABASE="$DB_NAME" \
    -e MYSQL_USER="$DB_USER" \
    -e MYSQL_PASSWORD="$DB_PASS" \
    "$MARIADB_IMAGE" \
    --bind-address=127.0.0.1 --default-time-zone=+00:00
fi

ready=0
for _ in $(seq 1 60); do
  if "${DOCKER_CMD[@]}" exec "$CONTAINER_DB" mysqladmin ping -h127.0.0.1 -uroot -p"$DB_ROOT_PASS" --silent 2>/dev/null; then
    ready=1
    break
  fi
  sleep 0.2
done
if [[ "$ready" -ne 1 ]]; then
  echo 'MariaDB did not become ready' >&2
  exit 1
fi

"${DOCKER_CMD[@]}" exec "$CONTAINER_DB" mysql -h127.0.0.1 -uroot -p"$DB_ROOT_PASS" -e 'SELECT 1' >/dev/null

"${DOCKER_CMD[@]}" run --rm --network "container:${CONTAINER_DB}" \
  -v "$REPO:/src:ro" -w /src \
  -e STATSNEWSLETTER_DB_HOST=127.0.0.1 \
  -e STATSNEWSLETTER_DB_NAME="$DB_NAME" \
  -e STATSNEWSLETTER_DB_USER="$DB_USER" \
  -e STATSNEWSLETTER_DB_PASSWORD="$DB_PASS" \
  -e TZ=UTC \
  "$IMAGE_PHP" \
  php -d date.timezone=UTC tests/support/Schema.php

forbidden='(\?\?|<=>|::class|function test[^(]*:|\bfn\(|\byield\b|anonymous\s+class)'
while IFS= read -r -d '' f; do
  if grep -Eq "$forbidden" "$f"; then
    echo "Forbidden PHP token in $f" >&2
    exit 1
  fi
  rel="${f#"$REPO"/}"
  "${DOCKER_CMD[@]}" run --rm \
    -v "$REPO:/src:ro" -w /src \
    "$IMAGE_PHP" php -l "$rel"
done < <(find "$REPO/tests" -name '*.php' -print0)

MANIFEST="$REPO/tests/support/suite-manifest.php"
BOOTSTRAP="$REPO/tests/bootstrap.php"
PHPUNIT_XML="$OUT_DIR/phpunit.xml"

"${DOCKER_CMD[@]}" run --rm \
  -v "$REPO:/src:ro" \
  -v "$OUT_DIR:/out" \
  -e STATSNEWSLETTER_SHUFFLE_SEED="$SEED" \
  "$IMAGE_PHP" php -r '
$manifest = require "/src/tests/support/suite-manifest.php";
$files = $manifest["test_files"];
srand((int) getenv("STATSNEWSLETTER_SHUFFLE_SEED"));
shuffle($files);
$abs = array();
foreach ($files as $f) {
    $abs[] = "/src/" . $f;
}
$xml = new DOMDocument("1.0", "UTF-8");
$phpunit = $xml->createElement("phpunit");
$phpunit->setAttribute("bootstrap", "/src/tests/bootstrap.php");
$phpunit->setAttribute("convertErrorsToExceptions", "true");
$phpunit->setAttribute("convertNoticesToExceptions", "true");
$phpunit->setAttribute("convertWarningsToExceptions", "true");
$phpunit->setAttribute("beStrictAboutTestsThatDoNotTestAnything", "true");
$xml->appendChild($phpunit);
$suite = $xml->createElement("testsuite");
$suite->setAttribute("name", "module-tests");
$phpunit->appendChild($suite);
foreach ($abs as $path) {
    $file = $xml->createElement("file", $path);
    $suite->appendChild($file);
}
$xml->save("/out/phpunit.xml");
'

set +e
OUTPUT="$("${DOCKER_CMD[@]}" run --rm --network "container:${CONTAINER_DB}" \
  -v "$REPO:/src:ro" -w /src \
  -v "$PHAR_DIR:/tools:ro" \
  -v "$OUT_DIR:/out" \
  -e STATSNEWSLETTER_DB_HOST=127.0.0.1 \
  -e STATSNEWSLETTER_DB_NAME="$DB_NAME" \
  -e STATSNEWSLETTER_DB_USER="$DB_USER" \
  -e STATSNEWSLETTER_DB_PASSWORD="$DB_PASS" \
  -e TZ=UTC \
  "$IMAGE_PHP" \
  php -d error_reporting=-1 -d display_errors=1 -d display_startup_errors=1 \
      -d log_errors=0 -d date.timezone=UTC \
      /tools/phpunit-4.8.36.phar --configuration /out/phpunit.xml --log-junit /out/junit.xml 2>&1)"
STATUS=$?
set -e

printf '%s\n' "$OUTPUT"

if [[ "$STATUS" -ne 0 ]]; then
  exit "$STATUS"
fi
if echo "$OUTPUT" | grep -Eq '(Notice|Warning|Deprecated|Strict Standards|Parse error|Fatal error)'; then
  echo 'PHP diagnostics in PHPUnit output' >&2
  exit 1
fi
if echo "$OUTPUT" | grep -Eq 'FAILURES!|Skipped:|Incomplete:|Risky|Warnings:'; then
  echo 'PHPUnit reported failures or warnings' >&2
  exit 1
fi
if ! echo "$OUTPUT" | grep -Eq '^OK \(24 tests, [0-9]+ assertions\)$'; then
  echo 'Unexpected PHPUnit summary' >&2
  exit 1
fi
