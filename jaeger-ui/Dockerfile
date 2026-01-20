# ---------- build stage ----------
FROM node:24-alpine AS build

ARG JAEGER_UI_VERSION=v2.14.1

RUN apk add --no-cache git python3 make g++

RUN git clone --depth 1 --branch ${JAEGER_UI_VERSION} https://github.com/jaegertracing/jaeger-ui.git /src
WORKDIR /src

RUN corepack enable

# у jaeger-ui сейчас может быть npm lockfile; используем yarn без frozen
RUN yarn install

WORKDIR /src/packages/jaeger-ui
RUN yarn build

# ---------- runtime stage ----------
FROM nginx:alpine
COPY --from=build /src/packages/jaeger-ui/build/ /usr/share/nginx/html/
COPY nginx.conf /etc/nginx/conf.d/default.conf
EXPOSE 8080
