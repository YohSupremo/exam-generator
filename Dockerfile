FROM php:8.2-apache

# Install system dependencies (Python 3, pip, SQLite3, procps for process monitoring)
RUN apt-get update && apt-get install -y --no-install-recommends \
    python3 \
    python3-pip \
    sqlite3 \
    procps \
    && rm -rf /var/lib/apt/lists/*

# Install PyMuPDF for high-speed PDF text and bookmarks extraction
RUN pip3 install --no-cache-dir pymupdf --break-system-packages

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Configure PHP settings for PDF uploads and processing
RUN echo "upload_max_filesize = 50M\n\
post_max_size = 55M\n\
memory_limit = 512M\n\
max_execution_time = 300\n\
display_errors = Off\n\
log_errors = On" > /usr/local/etc/php/conf.d/custom.ini

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . /var/www/html/

# Ensure data directory and exams subfolder exist and are fully writable
RUN mkdir -p /var/www/html/data/exams \
    && chown -R www-data:www-data /var/www/html/data \
    && chmod -R 777 /var/www/html/data

# Dynamic port binding for Render ($PORT) or default port 80
RUN echo '#!/bin/sh\n\
PORT="${PORT:-80}"\n\
sed -i "s/Listen [0-9]*/Listen $PORT/g" /etc/apache2/ports.conf\n\
sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost \*:$PORT>/g" /etc/apache2/sites-available/000-default.conf\n\
exec apache2-foreground' > /usr/local/bin/start-app.sh \
    && chmod +x /usr/local/bin/start-app.sh

EXPOSE 80 10000

CMD ["/usr/local/bin/start-app.sh"]
