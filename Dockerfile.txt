FROM klongchu/php-nginx:latest
WORKDIR /var/www/html
USER root
# RUN apt-get update && apt-get install -y curl
COPY . .
RUN cp .env.example .env
# RUN composer install --optimize-autoloader --ignore-platform-reqs --no-scripts
RUN composer install
RUN composer dump-autoload

RUN npm install -g yarn \
    yarn install \
    yarn run production

RUN php artisan route:cache
RUN php artisan view:cache
RUN php artisan event:cache
# RUN php artisan config:cache
USER www-data
