/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2013 The Gorilla WebSocket Authors
 * @copyright 2017 subtitulamos.tv
 */

package main

type hub struct {
	connections map[*connection]bool
	broadcast   chan []byte
	join        chan *connection
	leave       chan *connection
}
