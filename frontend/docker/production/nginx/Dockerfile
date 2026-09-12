FROM node:24-alpine AS build
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY . .
ARG VITE_APP_VERSION="dev"
ARG VITE_API_MOCKS_ENABLED="false"
ENV VITE_API_URL="/api" \
    VITE_APP_VERSION=$VITE_APP_VERSION \
    VITE_API_MOCKS_ENABLED=$VITE_API_MOCKS_ENABLED
RUN npm run build

FROM nginx:1.28-alpine
ENV NGINX_ENVSUBST_FILTER="^(API_UPSTREAM|API_HOST)$" \
    API_UPSTREAM="http://site_api" \
    API_HOST="api.rebit-pro.ru"
COPY docker/production/nginx/conf.d/default.conf /etc/nginx/templates/default.conf.template
COPY --from=build /app/dist /usr/share/nginx/html
HEALTHCHECK --interval=10s --timeout=3s --start-period=10s --retries=3 \
    CMD wget -q -O /dev/null http://127.0.0.1/health || exit 1
