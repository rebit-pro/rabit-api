FROM nginx:1.28-alpine

ENV NGINX_ENVSUBST_FILTER="^(API_UPSTREAM|API_HOST)$"
COPY ./development/nginx/conf.d/default.conf /etc/nginx/templates/default.conf.template
HEALTHCHECK --interval=10s --timeout=3s --start-period=10s --retries=3 \
    CMD wget -q -O /dev/null http://127.0.0.1/health || exit 1
