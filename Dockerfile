FROM php:8.2-apache

# pdo_mysql is the only extension the app actually needs installed: mbstring,
# dom and simplexml are already compiled into the php:8.2-apache base image
# (verify with `docker run --rm php:8.2-apache php -m`). They are named in the
# comment rather than passed to docker-php-ext-install because re-installing a
# built-in extension fails the build.
RUN docker-php-ext-install pdo pdo_mysql

# Composer, so `docker compose exec app composer test` works and nobody has to
# install PHP 8.2 on the host just to run the suite. Copied from the official
# image rather than curl'd, so the version is pinned and the download is not an
# unverified script piped into php.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# git and unzip let Composer install from source and extract dist archives;
# without them it falls back to slower paths and warns on every run.
RUN apt-get update \
 && apt-get install -y --no-install-recommends git unzip default-mysql-client \
 && rm -rf /var/lib/apt/lists/*

# Point Apache at the public directory, and allow the .htaccess in there to take
# effect. Without AllowOverride the file is parsed by nobody: its rules blocking
# error_log/.env/*.sql from being served silently do nothing.
RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf \
 && printf '<Directory /var/www/html/public>\n    AllowOverride All\n    Require all granted\n</Directory>\n' \
      > /etc/apache2/conf-available/typhon-public.conf \
 && a2enconf typhon-public

# headers: required by the security-header block in public/.htaccess.
RUN a2enmod rewrite headers

# Keep the server from advertising its exact version in every response.
RUN printf 'ServerTokens Prod\nServerSignature Off\n' > /etc/apache2/conf-available/typhon-hardening.conf \
 && a2enconf typhon-hardening

# src/ is bind-mounted from the host, so its ownership is the host user's, not
# www-data's. bootstrap.php writes the application log under storage/logs and
# skips the redirect when that directory is not writable — which on a bind mount
# means every error silently goes to the container log instead. Adding www-data
# to the host user's gid keeps the app log working in development. On the cPanel
# host the account user owns the files and this is a no-op.
RUN usermod -a -G 1000 www-data 2>/dev/null || groupadd -g 1000 hostuser && usermod -a -G 1000 www-data

EXPOSE 80
