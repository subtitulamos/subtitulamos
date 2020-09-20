/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2013 The Gorilla WebSocket Authors
 * @copyright 2017 subtitulamos.tv
 */

package main

import (
	"fmt"
	"log"
	"net/http"
	"strconv"
	"time"

	"github.com/gorilla/websocket"
)

const (
	// Time allowed to write a message to the peer.
	writeWait = 10 * time.Second

	// Time allowed to read the next pong message from the peer.
	pongWait = 60 * time.Second

	// Send pings to peer with this period. Must be less than pongWait.
	pingPeriod = (pongWait * 9) / 10

	// Maximum message size allowed from peer.
	maxMessageSize = 512
)

var upgrader = websocket.Upgrader{
	ReadBufferSize:  1024,
	WriteBufferSize: 1024,
}

// connection is an middleman between the websocket connection and the hub.
type connection struct {
	// The websocket connection.
	ws *websocket.Conn

	// The hub it belongs to
	hub *hub

	// Buffered channel of outbound messages.
	send chan []byte
}

// write writes a message with the given message type and payload.
func (c *connection) write(mt int, payload []byte) error {
	c.ws.SetWriteDeadline(time.Now().Add(writeWait))
	return c.ws.WriteMessage(mt, payload)
}

// readPump listens to messages from client (such as disconnection)
func (c *connection) readPump() {
	defer func() {
		c.hub.leave <- c
		c.ws.Close()
	}()
	c.ws.SetReadLimit(maxMessageSize)
	c.ws.SetReadDeadline(time.Now().Add(pongWait))
	c.ws.SetPongHandler(func(string) error { c.ws.SetReadDeadline(time.Now().Add(pongWait)); return nil })
	for {
		_, _, err := c.ws.ReadMessage()
		if err != nil {
			if websocket.IsUnexpectedCloseError(err, websocket.CloseGoingAway) {
				log.Printf("error: %v", err)
			}
			break
		}
	}
}

// writePump pumps messages from the hub to the websocket connection.
func (c *connection) writePump() {
	ticker := time.NewTicker(pingPeriod)
	defer func() {
		ticker.Stop()
		c.ws.Close()
	}()
	for {
		select {
		case message, ok := <-c.send:
			if !ok {
				c.write(websocket.CloseMessage, []byte{})
				return
			}
			if err := c.write(websocket.TextMessage, message); err != nil {
				return
			}
		case <-ticker.C:
			if err := c.write(websocket.PingMessage, []byte{}); err != nil {
				return
			}
		}
	}
}

// serveWs handles websocket requests from the peer.
func serveWs(w http.ResponseWriter, r *http.Request) {
	subID, _ := strconv.Atoi(r.URL.Query().Get("subID"))
	token := r.URL.Query().Get("token")

	log.Printf("Client logging in with token <%s> on sub#%d", token, subID)
	if subID == 0 || len(token) == 0 {
		log.Println("Invalid access arguments")
		return
	}

	auths := fmt.Sprintf("authtok-%s-%s", redisEnvPrefix, token)
	v, err := redisClient.Get(auths).Result()
	if err != nil {
		log.Printf("(%s, %d) pair fail, not found <%s>. err: %v", token, subID, auths, err)
		return
	}

	expectedSubID, err := strconv.Atoi(v)
	if err != nil || expectedSubID != subID {
		log.Printf("(%s, %d) pair fail, wrong sub ID", token, subID)
		return
	}

	log.Printf("(%s, %d) pair OK", token, subID)

	ws, err := upgrader.Upgrade(w, r, nil)
	if err != nil {
		log.Println(err)
		return
	}

	sub, ok := subtitles[subID]
	if !ok {
		sub = newSubtitle(subID)
		subtitles[subID] = sub

		log.Printf("Created subtitle instance for %d", subID)
	}

	c := &connection{send: make(chan []byte, 256), ws: ws, hub: sub.hub}
	sub.hub.join <- c
	go c.writePump()
	c.readPump()
}
