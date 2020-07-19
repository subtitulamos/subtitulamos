#!/usr/bin/env sh
docker-compose up &
P1=$!
cd src/subtitulamos && npm run dev &
P2=$!
wait $P1 $P2
