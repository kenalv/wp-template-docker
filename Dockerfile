FROM wordpress:6.8.2-php8.3-apache

# Install required PHP extensions for MySQL and Azure connectivity
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Install additional utilities
RUN apt-get update && apt-get install -y \
    curl \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Copy PHP configuration for uploads and performance
COPY ./config/uploads.ini /usr/local/etc/php/conf.d/uploads.ini
COPY ./config/apache-config.conf /etc/apache2/conf-available/custom.conf

# Enable Apache modules and custom configuration
RUN a2enmod rewrite headers deflate expires
RUN a2enconf custom

# Download Azure SSL certificate for MySQL connectivity
RUN curl -o /var/www/html/DigiCertGlobalRootCA.crt.pem \
    https://www.digicert.com/CACerts/DigiCertGlobalRootCA.crt \
    && chmod 644 /var/www/html/DigiCertGlobalRootCA.crt.pem

# Copy must-use plugins (core API functionality)
COPY ./wp-content/mu-plugins/ /var/www/html/wp-content/mu-plugins/

# Copy custom plugins and themes
COPY ./wp-content/plugins/ /var/www/html/wp-content/plugins/
COPY ./wp-content/themes/ /var/www/html/wp-content/themes/

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html/wp-content
RUN chmod -R 755 /var/www/html/wp-content

# Health check for container monitoring
#HEALTHCHECK --interval=30s --timeout=10s --start-period=30s --retries=3 \
#    CMD curl -f http://localhost/wp-admin/admin-ajax.php || exit 1

EXPOSE 80

CMD ["apache2-foreground"]
