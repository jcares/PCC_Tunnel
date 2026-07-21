FROM golang:1.22-alpine AS builder
WORKDIR /src
COPY go.mod go.sum ./
RUN go mod download
COPY . .
RUN CGO_ENABLED=0 go build -ldflags="-s -w" -o /bin/pcc-client .

FROM alpine:3.19
RUN apk add --no-cache ca-certificates tzdata
WORKDIR /app
COPY --from=builder /bin/pcc-client /app/pcc-client
COPY config/config.yaml /app/config/config.yaml
RUN mkdir -p /app/logs
VOLUME ["/app/config", "/app/logs"]
ENTRYPOINT ["/app/pcc-client"]
