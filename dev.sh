#!/usr/bin/env sh
docker-compose up &
P1=$!
npm run dev &
P2=$!
wait $P1 $P2
