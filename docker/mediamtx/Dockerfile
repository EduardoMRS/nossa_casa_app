FROM bluenviron/mediamtx:1.20.1 AS mediamtx

FROM alpine:3.24.1

RUN apk add --no-cache curl

COPY --from=mediamtx /mediamtx /mediamtx
COPY ./docker/mediamtx/mediamtx.yml /mediamtx.template.yml
COPY ./docker/mediamtx/.env.example /mediamtx.env.example
COPY ./docker/mediamtx/entrypoint.sh /usr/local/bin/mediamtx-entrypoint.sh

RUN chmod +x /usr/local/bin/mediamtx-entrypoint.sh

ENTRYPOINT ["/usr/local/bin/mediamtx-entrypoint.sh"]
