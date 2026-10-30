FROM node:23-alpine AS frontend

WORKDIR /app

COPY web/package.json web/package-lock.json ./

RUN npm ci

COPY web/ ./

ARG VITE_API_URL=/api
ENV VITE_API_URL=${VITE_API_URL}

RUN npm run build

FROM nginx:1.29-alpine

COPY docker/web/default.conf /etc/nginx/conf.d/default.conf
COPY --from=frontend /app/dist /usr/share/nginx/html
