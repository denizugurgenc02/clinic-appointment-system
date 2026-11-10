FROM php:8.2-apache

# Sistem araçları (composer paket indirme için git/unzip)
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*

# Aşama 3'te MySQL bağlantısı için PDO sürücüsü şimdiden kurulu durur
RUN docker-php-ext-install pdo_mysql

# .htaccess ile tüm istekleri public/index.php'ye yönlendirmek için
RUN a2enmod rewrite

# DocumentRoot = public/ (src/, config/, storage/ tarayıcıdan erişilemez)
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && printf '<Directory ${APACHE_DOCUMENT_ROOT}>\n    AllowOverride All\n</Directory>\n' > /etc/apache2/conf-available/app.conf \
    && a2enconf app

COPY docker/php.ini /usr/local/etc/php/conf.d/app.ini
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
