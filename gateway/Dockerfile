FROM golang:1.22-alpine AS build
WORKDIR /src
COPY go.mod ./
RUN go mod download
COPY . .
RUN go build -o /out/pcc-gateway .

FROM alpine:3.20
WORKDIR /app
COPY --from=build /out/pcc-gateway /app/pcc-gateway
EXPOSE 8080 8081
ENV PCC_CONTROL_ADDR=:8080
ENV PCC_PUBLIC_ADDR=:8081
ENTRYPOINT ["/app/pcc-gateway"]
