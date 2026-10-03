# Development image: Composer + PHP with the extensions the app needs (intl for money and date formatting).
FROM composer:latest
RUN apk add --no-cache icu-dev icu-data-full && docker-php-ext-install intl
