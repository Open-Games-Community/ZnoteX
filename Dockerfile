FROM php:8.5-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
		libzip-dev \
		libpng-dev \
		libjpeg62-turbo-dev \
		libfreetype6-dev \
		libcurl4-openssl-dev \
		libonig-dev \
		unzip \
		git \
	&& docker-php-ext-configure gd --with-jpeg --with-freetype \
	&& docker-php-ext-install mysqli pdo_mysql zip gd curl \
	&& pecl install apcu \
	&& docker-php-ext-enable apcu \
	&& a2enmod rewrite \
	&& apt-get purge -y --auto-remove git \
	&& rm -rf /var/lib/apt/lists/*

COPY docker/php.ini /usr/local/etc/php/conf.d/znotex.ini
COPY docker/apache-htaccess.conf /etc/apache2/conf-enabled/z-znotex.conf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .
RUN composer install --no-dev --no-interaction --no-scripts --optimize-autoloader \
	&& chown -R www-data:www-data /var/www/html \
	&& chmod -R u+rwX,g+rX engine/cache engine/img/theme 2>/dev/null || true

COPY docker/entrypoint.sh /usr/local/bin/znotex-entrypoint.sh
RUN chmod +x /usr/local/bin/znotex-entrypoint.sh

ENTRYPOINT ["/usr/local/bin/znotex-entrypoint.sh"]
CMD ["apache2-foreground"]
