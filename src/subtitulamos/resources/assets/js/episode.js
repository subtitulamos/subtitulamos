/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2020 subtitulamos.tv
 */

import Vue from "vue";
import "./vue/comment.js";
import "../css/episode.css";
import { onDomReady, easyFetch } from "./utils.js";

const $newTranslationButton = document.querySelector(".translate_subtitle");
$newTranslationButton.addEventListener("click", function () {
  document.getElementById("new-translation-opts").classList.toggle("hidden");
});

document.querySelectorAll("a[disabled]").forEach(($ele) =>
  $ele.addEventListener("click", function (e) {
    e.preventDefault();
    return false;
  })
);

document.querySelectorAll("a[data-action='delete']").forEach(($ele) =>
  $ele.addEventListener("click", function (e) {
    const subId = this.dataset.id;

    Swal.fire({
      type: "warning",
      cancelButtonText: "Cancelar",
      showCancelButton: true,
      text: "¿Estás seguro de querer borrar este subtítulo? Esta acción no es reversible.",
    }).then((result) => {
      if (result.value) {
        window.location = "/subtitles/" + subId + "/delete";
      }
    });
  })
);

onDomReady(function () {
  let lastLangVal = localStorage.getItem("last-selected-translation-lang");

  if (lastLangVal !== null) {
    document.getElementById("translate-to-lang").value = lastLangVal;
  }
});

document.getElementById("translate-to-lang").addEventListener("change", function () {
  localStorage.setItem("last-selected-translation-lang", this.value);
});

let comments = new Vue({
  el: "#subtitle-comments",
  data: {
    newComment: "",
    submittingComment: false,
    comments: [],
    maxCommentLength: 600, // Max char limit!
  },
  methods: {
    publishComment() {
      if (this.submittingComment) {
        return false;
      }

      if (this.newComment.length > this.maxCommentLength) {
        Toasts.error.fire(
          "Por favor, escribe un comentario más corto (de hasta " +
            this.maxCommentLength +
            " caracteres)"
        );
        return false;
      }

      this.submittingComment = true;
      easyFetch("/episodes/" + epId + "/comments", {
        method: "POST",
        rawBody: {
          text: this.newComment,
        },
      })
        .then(() => {
          // Cheap solution: reload the entire comment box
          this.newComment = "";
          this.submittingComment = false;
          loadComments();
        })
        .catch((e) => {
          this.submittingComment = false;
          Toasts.error.fire("Ha ocurrido un error al enviar tu comentario");
        });
    },
    refresh: function () {
      loadComments();
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

      easyFetch("/episodes/" + epId + "/comments/" + id, {
        method: "DELETE",
      })
        .then(function () {
          loadComments();
        })
        .catch(
          function () {
            Toasts.error.fire("Ha ocurrido un error al borrar el comentario");
            if (typeof cidx !== "undefined") {
              // Insert the comment right back where it was
              this.comments.splice(cidx, 0, c);
            } else {
              loadComments();
            }
          }.bind(this)
        );
    },

    pin: function (id) {
      easyFetch("/episodes/" + epId + "/comments/" + id + "/pin", {
        method: "POST",
      })
        .then(() => {
          loadComments();
        })
        .catch(() => {
          Toasts.error.fire("Ha ocurrido un error al intentar fijar el comentario");
        });
    },
  },
});

function loadComments() {
  easyFetch("/episodes/" + epId + "/comments")
    .then((response) => response.json())
    .then((reply) => {
      comments.comments = reply;
    })
    .catch(() => {
      Toasts.error.fire("Ha ocurrido un error tratando de cargar los comentarios");
    });
}

// Start by loading the comments, and set a timer to do so frequently
loadComments();
setInterval(loadComments, 60000);
