FROM php@sha256:6ce95208609dc66df163ab936c970b3b34cd901b85c747102c5999f08ade9143

COPY --from=debian@sha256:734a72b3d6142a71993b4bc580ca01d8ca687f77a2c422d96a961d3376b179bc \
  /usr/share/keyrings/debian-archive-keyring.gpg /usr/share/keyrings/debian-archive-keyring.gpg

RUN set -eux; \
  sed -i 's|deb.debian.org/debian|archive.debian.org/debian|g' /etc/apt/sources.list; \
  sed -i 's|security.debian.org/debian-security|archive.debian.org/debian-security|g' /etc/apt/sources.list; \
  sed -i '/stretch-updates/d' /etc/apt/sources.list; \
  printf 'Acquire::Check-Valid-Until "false";\n' > /etc/apt/apt.conf.d/99no-check-valid; \
  apt-get update; \
  docker-php-ext-install pdo_mysql; \
  apt-get clean; \
  rm -rf /var/lib/apt/lists/*
