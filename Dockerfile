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

# Configure Apache to listen on port 7860 (Hugging Face Spaces default)
RUN sed -i 's/80/7860/g' /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf

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

# Hugging Face Spaces port
EXPOSE 7860

CMD ["apache2-foreground"]
