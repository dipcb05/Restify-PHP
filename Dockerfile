FROM php:8.2-cli

WORKDIR /var/www/restify

COPY . /var/www/restify

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libpq-dev \
    && docker-php-ext-install pdo_mysql pdo_pgsql opcache \
    && rm -rf /var/lib/apt/lists/*

EXPOSE 8000

CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
