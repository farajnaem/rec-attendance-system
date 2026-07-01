FROM php:8.2-apache-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    libzip-dev \
    curl \
    && docker-php-ext-install pdo pdo_mysql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite headers

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

COPY . /var/www/html

RUN chown -R www-data:www-data /var/www/html \
    && sed -i 's/\r$//' /var/www/html/docker/entrypoint.sh \
    && sed -i 's/\r$//' /var/www/html/docker/healthcheck.sh \
    && chmod +x /var/www/html/docker/entrypoint.sh \
    && chmod +x /var/www/html/docker/healthcheck.sh

ENV PORT=80
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

EXPOSE 80 3000

# Coolify يعيّن PORT=3000 — مهلة كافية لانتظار MySQL + الترحيلات
HEALTHCHECK --interval=15s --timeout=10s --start-period=120s --retries=8 \
    CMD /var/www/html/docker/healthcheck.sh

ENTRYPOINT ["/var/www/html/docker/entrypoint.sh"]
CMD ["apache2-foreground"]
