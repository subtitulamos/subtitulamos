FROM golang:1.14-alpine
# Listen internally on :8065 - we don't forward this port outside
ENV TRANSLATE_HTTP_ADDR ":8065"
ARG REDIS_PUBSUB_ENV
ENV REDIS_PUBSUB_ENV ${REDIS_PUBSUB_ENV}
ARG REDIS_ADDR
ENV REDIS_ADDR ${REDIS_ADDR}

WORKDIR /go/src/subtitulamos-translate
COPY ./src/translation-server .
RUN go install

CMD ["subtitulamos-translate"]
