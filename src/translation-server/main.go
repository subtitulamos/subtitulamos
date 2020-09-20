/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2013 The Gorilla WebSocket Authors
 * @copyright 2017 subtitulamos.tv
 */

package main

import (
	"log"
	"net/http"
	"os"

	"github.com/go-redis/redis"
)

var redisClient *redis.Client
var psConn *redis.PubSub
var redisEnvPrefix string

func env(key string, defaultTo string) string {
	val := os.Getenv(key)
	if val == "" {
		return defaultTo
	}

	return val
}

func main() {
	addr := env("TRANSLATE_HTTP_ADDR", ":8080")     // http servicing address
	redisAddr := env("REDIS_ADDR", ":6379")         // redis service address
	redisEnvPrefix = env("REDIS_PUBSUB_ENV", "dev") // redis pub/sub environment prefix

	redisClient = redis.NewClient(&redis.Options{
		Addr: redisAddr,
		DB:   0,
	})

	_, err := redisClient.Ping().Result()
	if err != nil {
		log.Fatal("pinging redis failed: ", err)
	}

	psConn = redisClient.Subscribe("") // Empty subscription
	go redisListener()

	log.Println("Redis listener started, starting HTTP server...")
	http.HandleFunc("/", serveWs)
	err = http.ListenAndServe(addr, nil)
	if err != nil {
		log.Fatal("ListenAndServe failed: ", err)
	}
}
