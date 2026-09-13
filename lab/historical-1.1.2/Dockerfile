# UNTESTED research scaffold. PHP_BASE must be a reviewed image@sha256 digest.
ARG PHP_BASE
FROM ${PHP_BASE}
RUN docker-php-ext-configure mysql --with-mysql=mysqlnd \
 && docker-php-ext-configure mysqli --with-mysqli=mysqlnd \
 && docker-php-ext-install mysql mysqli
COPY lab.ini /usr/local/etc/php/conf.d/zz-resurrection-lab.ini
USER 10001:10001
WORKDIR /srv/game
# No EXPOSE or host port. PHP listens only on container loopback.
CMD ["sh", "-c", "cp -R /baseline/. /srv/game/ && exec php -S 127.0.0.1:8080 -t /srv/game >/tmp/historical-http.log 2>&1"]
