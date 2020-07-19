/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2013 The Gorilla WebSocket Authors
 * @copyright 2017 subtitulamos.tv
 */

package main

import (
	"fmt"
	"log"
	"strconv"
	"strings"
)

var subtitles = make(map[int]*subtitle)

type subtitle struct {
	id  int
	hub *hub
}

func newSubtitle(subID int) *subtitle {
	var h = hub{
		broadcast:   make(chan []byte),
		join:        make(chan *connection),
		leave:       make(chan *connection),
		connections: make(map[*connection]bool),
	}

	var s = subtitle{
		id:  subID,
		hub: &h,
	}

	psConn.Subscribe(s.getRedisChannelName())
	go s.run()
	return &s
}

func (s *subtitle) getRedisChannelName() string {
	return fmt.Sprintf("%s-translate-%d", redisEnvPrefix, s.id)
}

func (s *subtitle) close() {
	delete(subtitles, s.id)
}

func (s *subtitle) run() {
	h := s.hub

	for {
		select {
		case c := <-h.join:
			h.connections[c] = true
		case c := <-h.leave:
			if _, ok := h.connections[c]; ok {
				log.Printf("Client leaving from sub#%d", s.id)

				delete(h.connections, c)
				close(c.send)

				if len(h.connections) == 0 {
					s.close()
					return
				}
			}
		case m := <-h.broadcast:
			for c := range h.connections {
				select {
				case c.send <- m:
				default:
					log.Printf("Client leaving from sub#%d (fail to send)", s.id)

					close(c.send)
					delete(h.connections, c)

					if len(h.connections) == 0 {
						s.close()
						return
					}
				}
			}
		}
	}
}

func redisListener() {
	defer psConn.Close()

	for {
		msg, err := psConn.ReceiveMessage()
		if err != nil {
			log.Println(err)
			continue
		}

		idx := strings.LastIndex(msg.Channel, "-")
		if idx < 0 {
			continue
		}

		subID, err := strconv.Atoi(msg.Channel[idx+1:])
		if err != nil || subID <= 0 {
			continue
		}

		s, ok := subtitles[subID]
		if !ok {
			continue
		}

		s.hub.broadcast <- []byte(msg.Payload)
	}
}
