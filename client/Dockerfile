FROM golang:1.22-alpine AS build
WORKDIR /src
COPY go.mod go.sum ./
RUN go mod download
COPY . .
RUN go build -o /out/pcc-client .

FROM alpine:3.20
WORKDIR /app
COPY --from=build /out/pcc-client /app/pcc-client
COPY config /app/config
ENTRYPOINT ["/app/pcc-client"]
