FROM alpine:3.19
RUN apk add --no-cache ca-certificates tzdata
WORKDIR /app
COPY bin/pcc-client /app/pcc-client
COPY config.yaml /app/config/config.yaml
RUN mkdir -p /app/logs
VOLUME ["/app/config", "/app/logs"]
ENTRYPOINT ["/app/pcc-client"]
