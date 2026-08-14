FROM bluenviron/mediamtx:1.19.3 AS mediamtx

FROM alpine:3.22

RUN apk add --no-cache curl

COPY --from=mediamtx /mediamtx /mediamtx
COPY ./docker/mediamtx/mediamtx.yml /mediamtx.yml

ENTRYPOINT ["/mediamtx"]
