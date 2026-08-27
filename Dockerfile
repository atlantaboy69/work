FROM php:8.2-apache

# Install Python 3, pip, PostgreSQL client & dev libraries, git, etc.
RUN apt-get update && apt-get install -y \
    python3 \
    python3-pip \
    python3-venv \
    libpq-dev \
    git \
    unzip \
    gcc \
    ca-certificates \
    && docker-php-ext-install pdo pdo_pgsql pgsql \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set Apache DocumentRoot
ENV APACHE_DOCUMENT_ROOT /var/www/html
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html/

# Install Python dependencies
RUN pip3 install --no-cache-dir --break-system-packages -r requirements.txt

# Ensure required directories exist with permissions
RUN mkdir -p /var/www/html/sessions /var/www/html/uploads /var/www/html/logs \
    && chmod -R 777 /var/www/html/sessions /var/www/html/uploads /var/www/html/logs \
    && chown -R www-data:www-data /var/www/html

# Default env var for PHP gateway to find local FastAPI
ENV FASTAPI_URL=http://127.0.0.1:8000/api/chat

EXPOSE 80

# Start FastAPI backend in background, then run Apache in foreground
CMD ["sh", "-c", "uvicorn main:app --host 127.0.0.1 --port 8000 & apache2-foreground"]
