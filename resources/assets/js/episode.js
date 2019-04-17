/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2017-2018 subtitulamos.tv
 */

import Vue from "vue";
import $ from "jquery";
import "./vue/comment.js";

let $newTranslationButton = $(".translate_subtitle");
$newTranslationButton.on("click", function() {
  $("#new-translation-opts").toggleClass("hidden");
});

$("a[disabled]").on("click", function(e) {
  e.preventDefault();
  return false;
});

$("a[data-action='delete']").on("click", function(e) {
  let subId = $(this).data("id");
  alertify.confirm(
    "¿Estás seguro de querer borrar este subtítulo? Esta acción no es reversible.",
    function() {
      window.location = "/subtitles/" + subId + "/delete";
    }
  );
});

$(function() {
  let lastLangVal = localStorage.getItem("last-selected-translation-lang");

  if (lastLangVal !== null) {
    $("#translate-to-lang").val(lastLangVal);
  }
});

$("#translate-to-lang").on("change", function() {
  localStorage.setItem("last-selected-translation-lang", $(this).val());
});

let comments = new Vue({
  el: "#subtitle-comments",
  data: {
    newComment: "",
    submittingComment: false,
    comments: [],
  },
  methods: {
    publishComment: function() {
      if (this.submittingComment) {
        return false;
      }

      this.submittingComment = true;
      $.ajax({
        url: "/episodes/" + epId + "/comments",
        method: "POST",
        data: {
          text: this.newComment,
        },
      })
        .done(() => {
          // Cheap solution: reload the entire comment box
          this.newComment = "";
          this.submittingComment = false;
          loadComments();
        })
        .fail(jqXHR => {
          this.submittingComment = false;
          if (jqXHR.responseText) {
            alertify.error(jqXHR.responseText);
          } else {
            alertify.error("Ha ocurrido un error al enviar tu comentario");
          }
        });
    },
    refresh: function() {
      loadComments();
    },

    remove: function(id) {
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

      $.ajax({
        url: "/episodes/" + epId + "/comments/" + id,
        method: "DELETE",
      })
        .done(function() {
          loadComments();
        })
        .fail(
          function() {
            alertify.error("Ha ocurrido un error al borrar el comentario");
            if (typeof cidx !== "undefined") {
              // Insert the comment right back where it was
              this.comments.splice(cidx, 0, c);
            } else {
              loadComments();
            }
          }.bind(this)
        );
    },

    pin: function(id) {
      $.ajax({
        url: "/episodes/" + epId + "/comments/" + id + "/pin",
        method: "POST",
      })
        .done(function() {
          loadComments();
        })
        .fail(function() {
          alertify.error("Ha ocurrido un error al intentar fijar el comentario");
        });
    },
  },
});

function loadComments() {
  $.ajax({
    url: "/episodes/" + epId + "/comments",
    method: "GET",
  })
    .done(function(reply) {
      comments.comments = reply;
    })
    .fail(function() {
      alertify.error("Ha ocurrido un error tratando de cargar los comentarios");
    });
}

// Start by loading the comments, and set a timer to do so frequently
loadComments();
setInterval(loadComments, 60000);
