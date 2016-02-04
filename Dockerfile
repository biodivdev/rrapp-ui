FROM diogok/php7

COPY . /var/www
RUN chown www-data.www-data /var/www -Rf

