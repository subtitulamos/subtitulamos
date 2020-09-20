FROM golang:1.14-alpine
ARG REDIS_PUBSUB_ENV
ENV REDIS_PUBSUB_ENV ${REDIS_PUBSUB_ENV}

WORKDIR /go/src/subtitulamos-translate
COPY ./src/translation-server .
RUN go install

CMD ["subtitulamos-translate"]
