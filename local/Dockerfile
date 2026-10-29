# Local play image (Phase 4 task 3). LOCAL USE ONLY, not for hosting: PHP's built-in server, no TLS, no mail.
# Built by `python3 scripts/local-play.py up` from the repository root (see local/compose.yaml).
FROM php:8.4-cli
# PHP's standard production php.ini, the configuration CI's setup-php uses (the image ships none). The game also runs
# without it (docs/PHASE4-OUTPUT-BUFFERING-FIX-20261029.md); this keeps local play on CI's settings. local/php.ini adds
# local settings.
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql \
 && groupadd --system --gid 10001 game \
 && useradd --system --uid 10001 --gid game --no-create-home --home-dir /nonexistent --shell /usr/sbin/nologin game \
 && mkdir -m 0700 /config && chown game:game /config
COPY local/php.ini /usr/local/etc/php/conf.d/zz-resurrection-local.ini
COPY local/setup.sh local/cron.sh /usr/local/lib/resurrection-local/
COPY . /srv/game
# The game reads dbconnect.php in its root. The install writes the real file to /config (a volume, outside the
# served directory, owner-only), and this link points the game at it. The local/ files are not served.
RUN rm -rf /srv/game/local \
 && ln -s /config/dbconnect.php /srv/game/dbconnect.php \
 && chmod -R a-w /srv/game
USER game
WORKDIR /srv/game
