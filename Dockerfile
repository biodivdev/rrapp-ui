FROM diogok/apache

COPY . /var/www
RUN chown www-data.www-data /var/www -Rf

