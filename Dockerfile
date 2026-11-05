# DRE System — imagem de produção (PHP 8.3 + Apache)
FROM php:8.3-apache

# Extensões que o sistema usa: pdo_mysql (banco) e zip (planilhas .xlsx). mbstring/iconv já vêm.
RUN apt-get update \
 && apt-get install -y --no-install-recommends libzip-dev curl \
 && docker-php-ext-install pdo_mysql zip \
 && a2enmod rewrite headers remoteip \
 && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY docker/php.ini /usr/local/etc/php/conf.d/dre.ini
COPY docker/apache.conf /etc/apache2/conf-enabled/dre.conf

WORKDIR /var/www/html
COPY --chown=www-data:www-data . .
RUN rm -rf .git .github infra tests docker scripts *.md .env \
 && mkdir -p storage/sessions storage/imports storage/notas storage/comprovantes \
            uploads/documentos uploads/fornecedores uploads/parceiros uploads/perfil \
 && chown -R www-data:www-data storage uploads

ARG APP_REVISION=dev
LABEL org.opencontainers.image.source="https://github.com/nicollylemos/DRE" \
      org.opencontainers.image.revision=$APP_REVISION

EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
  CMD curl -fsS http://localhost/health || exit 1
