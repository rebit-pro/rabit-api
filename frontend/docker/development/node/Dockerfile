FROM node:24-alpine

WORKDIR /app
RUN mkdir -p /app/node_modules /home/node/.npm \
    && chown -R node:node /app /home/node/.npm
USER node

HEALTHCHECK --interval=5s --timeout=3s --start-period=30s --retries=12 \
    CMD node -e "fetch('http://127.0.0.1:5173/').then(r=>process.exit(r.ok?0:1)).catch(()=>process.exit(1))"
