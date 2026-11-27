FROM php:8.2-cli-bookworm

COPY docker/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

# Evita prompts interativos durante instalacao de pacotes apt
ENV DEBIAN_FRONTEND=noninteractive

# Instala utilitarios de sistema e bibliotecas nativas das extensoes PHP
# (Nota: Os 6 Spiders do projeto rodam 100% em PHP via RoachPHP + Smalot PdfParser,
# mantendo a imagem enxuta sem necessidade de instalar Python ou Chromium pesado)
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    git \
    unzip \
    zip \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libicu-dev \
    libcurl4-openssl-dev \
    default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        zip \
        mbstring \
        xml \
        bcmath \
        curl \
        intl \
        gd \
        pcntl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Instala o Composer oficial a partir da imagem composer:2
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copia arquivos de dependencia primeiro para aproveitar cache de camadas Docker
COPY composer.json composer.lock* ./
RUN composer install --no-interaction --no-scripts --no-autoloader --prefer-dist || true

# Copia o restante do codigo-fonte do Back-end
COPY . .

# Finaliza o autoload otimizado do Composer e garante estrutura de pastas de storage
RUN composer dump-autoload --optimize --no-scripts || true \
    && mkdir -p storage/framework/cache/data \
                storage/framework/sessions \
                storage/framework/views \
                storage/logs \
                storage/app/public/kanban_anexos \
                bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Copia o entrypoint garantindo quebras de linha LF (anti-erro CRLF no Windows) e permissao de execucao
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh \
    && chmod +x /usr/local/bin/entrypoint.sh \
    && ( [ -f /var/www/html/docker/entrypoint.sh ] && sed -i 's/\r$//' /var/www/html/docker/entrypoint.sh && chmod +x /var/www/html/docker/entrypoint.sh || true )

EXPOSE 8000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
