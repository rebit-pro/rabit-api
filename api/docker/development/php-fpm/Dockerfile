FROM php:8.4-fpm

# Устанавливаем системные зависимости
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libonig-dev \
    libicu-dev \
    libxml2-dev \
    libpq-dev \
    librabbitmq-dev \
    libcurl4-openssl-dev \
    curl \
    libldap2-dev \
    unzip \
    git \
    gnupg2 \
    msmtp \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        gd \
        bcmath \
        curl \
        exif \
        mysqli \
        opcache \
        pdo \
        pdo_mysql \
        intl \
        soap \
        sockets \
        zip \
    && pecl install memcache xdebug amqp \
    && docker-php-ext-enable memcache xdebug amqp \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN apt update && apt install -y iputils-ping

RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
 && apt-get install -y nodejs \
 && npm install -g npm@latest \
 && npm cache clean --force

# Включаем нужные настройки PHP
RUN mv $PHP_INI_DIR/php.ini-development $PHP_INI_DIR/php.ini

# Копируем конфигурации PHP
COPY ./common/php/conf.d /usr/local/etc/php/conf.d
COPY ./development/php/conf.d /usr/local/etc/php/conf.d
COPY ./common/php-fpm/www.conf /usr/local/etc/php-fpm.d/www.conf

# Сменить UID/GID www-data на хостовый (1000) — чтобы FPM воркеры
# могли писать в bind-mounted volume (симлинки модулей, кеш и т.д.)
RUN usermod -u 1000 www-data && groupmod -g 1000 www-data

WORKDIR /app

COPY ./common/php/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

RUN mkdir -p /var/lib/php/sessions && chown -R www-data:www-data /var/lib/php/sessions

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]
