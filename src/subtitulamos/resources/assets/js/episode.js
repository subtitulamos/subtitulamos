/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2021 subtitulamos.tv
 */

import Vue from "vue";
import "./vue/comment.js";
import "../css/episode.scss";
import {
  onDomReady,
  easyFetch,
  $getAllEle,
  $getEle,
  showOverlayFromTpl,
  invertDropdown,
} from "./utils.js";

const lastLangVal = localStorage.getItem("last-selected-translation-lang");
$getEle(".translate-subtitle").addEventListener("click", function () {
  showOverlayFromTpl("new-translation");

  $getAllEle(".dropdown-field select").forEach((dropdown) => {
    dropdown.addEventListener("click", invertDropdown);
    dropdown.addEventListener("blur", (e) => invertDropdown(e, false));
  });

  const $translateToLangField = $getEle("#translate-to-lang");
  if (lastLangVal !== null && $translateToLangField.options.length) {
    const optionsArray = Array.apply(null, $translateToLangField.options);
    if (optionsArray.filter(({ value }) => value === lastLangVal).length) {
      $translateToLangField.value = lastLangVal;
    } else {
      $translateToLangField.value = "";
    }
  }
  $translateToLangField.addEventListener("change", function () {
    localStorage.setItem("last-selected-translation-lang", this.value);
  });
});

if (canEditProperties) {
  $getEle("#episode-name").addEventListener("click", () => {
    showOverlayFromTpl("episode-properties");
  });

  $getEle("#show-name").addEventListener("click", () => {
    showOverlayFromTpl("show-properties");
  });
}

$getAllEle(".subtitle-properties-button").forEach((button) => {
  button.addEventListener("click", (e) => {
    const $subId = e.currentTarget.dataset.subtitleId;
    showOverlayFromTpl("subtitle-properties-" + $subId);
  });
});

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
  // Expanding / collapsing a language's container
  $getAllEle(".language-name").forEach((dropdown) => {
    const expandLanguage = () => {
      const $languageContainer = dropdown.closest(".language-container");
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

      localStorage.setItem("expand-lang-" + dropdown.dataset.langId, !isExpanded);
    };

    dropdown.addEventListener("click", expandLanguage);
    if (localStorage.getItem("expand-lang-" + dropdown.dataset.langId) === "true") {
      expandLanguage();
    }
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
        .catch((err) => {
          this.submittingComment = false;

          err.response
            .text()
            .then((response) => {
              if (response) {
                Toasts.error.fire(response);
              } else {
                throw new Exception();
              }
            })
            .catch(() => {
              Toasts.error.fire("Ha ocurrido un error al enviar tu comentario");
            });
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
