/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2020 subtitulamos.tv
 */

import Vue from "vue";
import "./vue/comment.js";
import "../css/episode.scss";
import { onDomReady, easyFetch, $getAllEle, $getEle } from "./utils.js";

const $newTranslationButton = document.querySelector(".translate-subtitle");
// $newTranslationButton.addEventListener("click", function () {
//   document.getElementById("new-translation-opts").classList.toggle("hidden");
// });

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
  // let lastLangVal = localStorage.getItem("last-selected-translation-lang");

  // if (lastLangVal !== null) {
  //   document.getElementById("translate-to-lang").value = lastLangVal;
  // }

  // Expanding / collapsing a language's container
  $getAllEle(".language-name").forEach((dropdown) => {
    dropdown.addEventListener("click", (e) => {
      const $element = e.currentTarget;
      const $languageContainer = $element.closest(".language-container");
      const isExpanded = $languageContainer
        .querySelector(".language-content")
        .classList.contains("expanded");

      $languageContainer
        .querySelector(".collapser-button i")
        .classList.toggle("fa-chevron-down", !isExpanded);
      $languageContainer
        .querySelector(".collapser-button i")
        .classList.toggle("fa-chevron-up", isExpanded);

      const $languageContent = $languageContainer.querySelector(".language-content");
      $languageContent.classList.toggle("expanded");

      // prevents the language content showing too early
      // the overflow class is needed otherwise the subtitle options
      // menu wont be visible since it overflows
      setTimeout(
        () => {
          $languageContent.classList.toggle("overflow");
        },
        isExpanded ? 0 : 100
      );
    });
  });

  // Show subtitle options
  $getAllEle(".ellipsis-wrapper").forEach((moreOptionsButton) => {
    moreOptionsButton.addEventListener("click", (e) => {
      const $moreOptionsContainer = e.currentTarget.closest(".more-options");
      const $optionsList = $moreOptionsContainer.querySelector(".more-options-list");
      const $fadePan = $moreOptionsContainer.querySelector(".fade-pan");

      $fadePan.classList.toggle("hidden");
      $optionsList.classList.toggle("open");

      $fadePan.addEventListener("click", () => {
        $fadePan.classList.toggle("hidden", true);
        $optionsList.classList.toggle("open", false);
      });
    });
  });
});

// document.getElementById("translate-to-lang").addEventListener("change", function () {
//   localStorage.setItem("last-selected-translation-lang", this.value);
// });

let comments = new Vue({
  el: "#comments-container",
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
    save: function (id, text) {
      easyFetch("/episodes/" + epId + "/comments/" + id + "/edit", {
        method: "POST",
        rawBody: {
          text,
        },
      }).catch(() => {
        Toasts.error.fire("Ha ocurrido un error al intentar editar el comentario");
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
