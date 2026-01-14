# jaeger-ui/Dockerfile
ARG JAEGER_IMAGE=cr.jaegertracing.io/jaegertracing/jaeger:2.14.1

FROM ${JAEGER_IMAGE} AS jaeger

# В некоторых образах Jaeger сборка идёт не под root, поэтому пишем в /tmp
RUN set -eux; \
    OUT_DIR="/tmp/out"; \
    mkdir -p "${OUT_DIR}"; \
    UI_INDEX="$(find / -type f -name index.html 2>/dev/null | grep -E 'jaeger|ui' | head -n 1)"; \
    echo "Found index: ${UI_INDEX}"; \
    UI_DIR="$(dirname "${UI_INDEX}")"; \
    echo "Using UI dir: ${UI_DIR}"; \
    cp -a "${UI_DIR}/." "${OUT_DIR}/"

FROM nginx:alpine
COPY --from=jaeger /tmp/out /usr/share/nginx/html
COPY nginx.conf /etc/nginx/conf.d/default.conf
