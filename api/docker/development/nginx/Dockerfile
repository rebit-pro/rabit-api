FROM nginx:1.25-alpine

# J1: the worker reads private originals and archives written by PHP (www-data, 1000:1000, mode 0600) for X-Accel-Redirect.
RUN sed -i 's/^nginx:x:101:101:/nginx:x:1000:1000:/' /etc/passwd \
    && sed -i 's/^nginx:x:101:/nginx:x:1000:/' /etc/group

COPY ./common/nginx/conf.d /etc/nginx/conf.d
COPY ./common/nginx/auth.conf /etc/nginx/auth.conf

WORKDIR /app
