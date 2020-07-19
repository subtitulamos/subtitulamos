FROM golang:1.14-alpine
ARG TRANSLATE_SV_PORT
ENV TRANSLATE_HTTP_ADDR ":${TRANSLATE_SV_PORT}"
ARG REDIS_PUBSUB_ENV
ENV REDIS_PUBSUB_ENV ${REDIS_PUBSUB_ENV}
ARG REDIS_ADDR
ENV REDIS_ADDR ${REDIS_ADDR}

WORKDIR /go/src/subtitulamos-translate
COPY ./src/translation-server .
RUN go install

CMD ["subtitulamos-translate"]
