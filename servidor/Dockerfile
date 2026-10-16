# Servidor de armazenamento das assinaturas: PHP 8.3 + Apache + SQLite (sem dependências externas).
FROM php:8.3-apache

# DocumentRoot aponta para public/ (src/ e data/ ficam fora do alcance do navegador).
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/000-default.conf /etc/apache2/apache2.conf \
 && a2enmod headers rewrite \
 && printf 'ServerTokens Prod\nServerSignature Off\n# repassa o header Authorization (Bearer) ao PHP\nSetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1\n# permite o .htaccess (URLs sem .php)\n<Directory /var/www/html/public>\n  AllowOverride All\n</Directory>\n' > /etc/apache2/conf-available/seguranca.conf \
 && a2enconf seguranca

COPY php.ini /usr/local/etc/php/conf.d/assinaturas.ini
COPY . /var/www/html
RUN mkdir -p /var/www/html/data && chown -R www-data:www-data /var/www/html/data \
 && chmod +x /var/www/html/entrypoint.sh

VOLUME ["/var/www/html/data"]
EXPOSE 80
ENTRYPOINT ["/var/www/html/entrypoint.sh"]
