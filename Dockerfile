FROM php:8.2-cli

ENV DOCKER=1

RUN apt-get update && apt-get install -y \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install dom xml zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

WORKDIR /app

EXPOSE 8080

CMD ["php", "-S", "0.0.0.0:8080", "-t", "public", "public/router.php"]
