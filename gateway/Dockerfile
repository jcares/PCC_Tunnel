FROM golang:1.22-alpine AS build
WORKDIR /src
COPY go.mod go.sum ./
RUN go mod download
COPY . .
RUN go build -o /out/pcc-gateway .

FROM alpine:3.20
WORKDIR /app
COPY --from=build /out/pcc-gateway /app/pcc-gateway
COPY config /app/config
RUN mkdir -p /app/logs
EXPOSE 8080 8081
ENV PCC_CONTROL_ADDR=:8080
ENV PCC_PUBLIC_ADDR=:8081
ENV PCC_AUTH_TOKEN=
ENV PCC_LOG_FILE=/app/logs/gateway.log
ENTRYPOINT ["/app/pcc-gateway"]
