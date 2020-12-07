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
      const targetCommentIdx = this.comments.findIndex((comment) => comment.id === id);
      if (targetCommentIdx < 0) {
        Toast.fire({
          type: "error",
          title: "Ha ocurrido un extraño error al borrar el comentario",
        });
        return;
      }

      const targetComment = this.comments[targetCommentIdx];
      this.comments.splice(targetCommentIdx, 1);

      const isEpisode = typeof targetComment.episode !== "undefined";
      const deleteUrl = isEpisode
        ? `/episodes/${targetComment.episode.id}/comments/${id}`
        : `/subtitles/${targetComment.subtitle.id}/translate/comments/${id}`;
      easyFetch(deleteUrl, {
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
          if (typeof targetCommentIdx !== "undefined") {
            // Insert the comment right back where it was
            this.comments.splice(targetCommentIdx, 0, targetComment);
          } else {
            loadComments(this.page);
          }
        });
    },

    pin: function (id) {
      const targetComment = this.comments.find((comment) => comment.id === id);
      if (!targetComment) {
        Toast.fire({
          type: "error",
          title: "Ha ocurrido un extraño error al fijar el comentario",
        });
        return;
      }

      const isEpisode = typeof targetComment.episode !== "undefined";
      const pinUrl = isEpisode
        ? `/episodes/${targetComment.episode.id}/comments/${id}/pin`
        : `/subtitles/${targetComment.subtitle.id}/translate/comments/${id}/pin`;

      easyFetch(pinUrl, {
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
    .then((reply) => reply.json())
    .then((reply) => {
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
