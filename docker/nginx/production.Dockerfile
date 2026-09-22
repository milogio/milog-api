FROM nginx:1.30.0-alpine

COPY public /var/www/public
COPY docker/nginx/production.conf /etc/nginx/conf.d/default.conf
