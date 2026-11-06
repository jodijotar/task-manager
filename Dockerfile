FROM php:8.2-apache
RUN docker-php-ext-install pdo_mysql \
 && a2enmod rewrite \
 && sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf
COPY docker-entrypoint-lab.sh /usr/local/bin/docker-entrypoint-lab.sh
RUN chmod +x /usr/local/bin/docker-entrypoint-lab.sh
ENTRYPOINT ["docker-entrypoint-lab.sh"]
CMD ["apache2-foreground"]
