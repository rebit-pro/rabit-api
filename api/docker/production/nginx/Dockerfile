FROM nginx:1.25-alpine

COPY docker/common/nginx/conf.d /etc/nginx/conf.d
COPY docker/production/nginx/auth.conf /etc/nginx/auth.conf
COPY docker/production/nginx/.htpasswd /etc/nginx/.htpasswd

WORKDIR /app

COPY ./public ./public
