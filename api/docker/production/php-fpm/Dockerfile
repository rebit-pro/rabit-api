FROM php:8.4-fpm
RUN mv $PHP_INI_DIR/php.ini-production $PHP_INI_DIR/php.ini
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
    libfcgi-bin \
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
    && pecl install memcache amqp \
    && docker-php-ext-enable memcache amqp \
    && apt-get clean && rm -rf /var/lib/apt/lists/*
# Сменить UID/GID www-data на хостовый (1000) — чтобы FPM воркеры
# могли писать в bind-mounted volume (симлинки модулей, кеш и т.д.)
RUN usermod -u 1000 www-data && groupmod -g 1000 www-data
# Копируем конфигурации PHP (common, затем production-override поверх)
COPY docker/common/php/conf.d /usr/local/etc/php/conf.d
COPY docker/production/php/conf.d /usr/local/etc/php/conf.d
COPY docker/common/php-fpm/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/common/php/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
# 2. Установка Composer (v2)
ENV COMPOSER_ALLOW_SUPERUSER=1
COPY --from=composer:2.8 /usr/bin/composer /usr/local/bin/composer
WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts

COPY ./ ./
RUN composer dump-autoload --no-dev --optimize

# Создаём симлинки маршрутов модулей (routes.php → /local/routes/{module}.php)
RUN find /app/public/local/modules -maxdepth 2 -name "routes.php" -exec sh -c ' \
    for f; do \
        module=$(basename "$(dirname "$f")"); \
        ln -sf "$f" /app/public/local/routes/"$module".php; \
    done' _ {} +

RUN chmod +x /usr/local/bin/docker-entrypoint.sh \
    && mkdir -p /app /var/lib/php/sessions \
    && chown -R www-data:www-data /app /var/lib/php/sessions

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]
