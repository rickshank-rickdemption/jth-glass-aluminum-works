FROM php:8.3-cli

WORKDIR /app

RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev libonig-dev ca-certificates \
    && docker-php-ext-install curl mbstring \
    && rm -rf /var/lib/apt/lists/*

COPY . /app

EXPOSE 8080

CMD ["sh", "-c", "php -S 0.0.0.0:$PORT -t /app"]
