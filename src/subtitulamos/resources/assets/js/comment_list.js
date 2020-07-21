/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2020 subtitulamos.tv
 */

import Vue from "vue";
import "./vue/comment.js";
import "../css/comment_list.css";
import { easyFetch } from "./utils.js";

let comments = new Vue({
  el: "#comment_content",
  data: {
    comments: [],
    page: 1,
  },
  methods: {
    refresh: function () {
      loadComments(this.page);
    },

    remove: function (id) {
      let c, cidx;
      for (let i = 0; i < this.comments.length; ++i) {
        if (this.comments[i].id == id) {
          // Save comment and remove it from the list
          c = this.comments[i];
          cidx = i;
          this.comments.splice(cidx, 1);
          break;
        }
      }

      easyFetch(`/episodes/${epId}/comments/${id}`, {
        method: "DELETE",
      })
        .then(() => {
          loadComments();
        })
        .catch(() => {
          Toast.fire({
            type: "error",
            title: "Ha ocurrido un error al borrar el comentario",
          });
          if (typeof cidx !== "undefined") {
            // Insert the comment right back where it was
            this.comments.splice(cidx, 0, c);
          } else {
            loadComments(this.page);
          }
        });
    },

    pin: function (id) {
      easyFetch(`/episodes/${epId}/comments/${id}`, {
        method: "POST",
      })
        .then(() => {
          loadComments();
        })
        .catch(() => {
          Toasts.error.fire("Ha ocurrido un error al intentar fijar el comentario");
          loadComments();
        });
    },

    nextPage: function () {
      this.page++;
      loadComments(this.page);

      document.getElementById("comments").scrollIntoView();
    },

    prevPage: function () {
      this.page--;
      loadComments(this.page);

      document.getElementById("comments").scrollIntoView();
    },
  },
});

function loadComments(page) {
  easyFetch(`/comments/${commentType}/load?page=${page}`)
    .then(reply => reply.json())
    .then(reply => {
      comments.comments = reply;
    })
    .catch(() => {
      Toasts.error.fire("Ha ocurrido un error tratando de cargar los comentarios");
    });
}

// Start by loading the comments, and set a timer to do so frequently
loadComments(1);
setInterval(() => {
  loadComments(comments.page);
}, 60000);
