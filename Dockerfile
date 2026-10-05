FROM php:8.3-apache

# PHP extensions and Apache modules
RUN docker-php-ext-install pdo pdo_mysql \
 && a2enmod rewrite headers

# Production PHP settings (hides errors from visitors)
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Copy the app
COPY . /var/www/html/

# Upload folder must be writable
RUN mkdir -p /var/www/html/uploads/documents \
 && chown -R www-data:www-data /var/www/html/uploads

# Block direct web access to source/SQL folders
RUN printf '<DirectoryMatch "^/var/www/html/(includes|database|docs)">\n    Require all denied\n</DirectoryMatch>\n' \
    > /etc/apache2/conf-available/protect.conf \
 && a2enconf protect

# Listen on the port Railway provides (falls back to 80)
CMD ["sh", "-c", "sed -i \"s/Listen 80/Listen ${PORT:-80}/\" /etc/apache2/ports.conf && sed -i \"s/:80>/:${PORT:-80}>/\" /etc/apache2/sites-enabled/000-default.conf && apache2-foreground"]