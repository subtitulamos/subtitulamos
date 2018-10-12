/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2017-2018 subtitulamos.tv
 */

import Vue from "vue";
import $ from "jquery";

function pad(n, width, z) {
  z = z || "0";
  n = n + "";
  return n.length >= width ? n : new Array(width - n.length + 1).join(z) + n;
}

let uploadInfo = {
  season: "",
  episode: "",
  name: ""
};

$(function() {
  let newShowTemplate = $("#new-show-tpl").prop("content");
  let $newShow;
  $("#show-id").on("change", function() {
    let val = $(this).val();

    if (val == "NEW") {
      if (!$newShow) {
        $(this)
          .closest(".field-body")
          .append($(newShowTemplate).clone());
        $newShow = $("#new-show");
        $newShow.focus();
      }
    } else if ($newShow) {
      $(this)
        .closest(".field-body")
        .children()[1]
        .remove();
      $newShow = null;
    }
  });

  // Logic for splitting season/episode and name
  $("#name").on("keyup input change", function() {
    let val = $(this)
      .val()
      .trim();
    let match = val.match(
      /^S?(\d+)(?:[xE](?:(\d+)(?:[\s-]+\s*([^-\s].*))?)?)?/
    );
    let error = "";
    if (val == "" || (!match && val == "S")) {
      error = "";
    } else if (!match && val != "S") {
      error =
        "Faltan la temporada y número de episodio (Formato: 0x00 - Nombre)";
    } else if (
      typeof match[1] != "undefined" &&
      typeof match[2] == "undefined"
    ) {
      error =
        "Faltan número de episodio y nombre del episodio (Formato: 0x00 - Nombre)";
    } else if (
      typeof match[1] != "undefined" &&
      typeof match[2] != "undefined" &&
      !match[3]
    ) {
      error = "Falta el nombre del episodio";
    }

    if (error) {
      uploadInfo = {
        season: "",
        episode: "",
        name: ""
      };
    } else {
      uploadInfo = {
        season: match[1],
        episode: match[2],
        name: match[3].trim()
      };
    }

    $("#upload-button").prop("disabled", error != "");
    $("#name-status")
      .toggleClass("hidden", error == "")
      .html(error);
  });

  $("#show-id, #lang, #version, #comments, #new-show, #sub").on(
    "change",
    function() {
      $("#" + $(this).attr("id") + "-status").toggleClass("hidden", true);
    }
  );

  // SRT FILE
  // Always clear the file input on load
  let $sub = $("#sub").clone();
  $("#sub").replaceWith($sub);

  // Update of SRT file selection
  $sub.on("change", function(e) {
    let files = $sub[0].files;
    if (files.length > 0) {
      $("#sub-name").html(files[0].name);
    }
  });

  $("form").on("submit", function(e) {
    e.preventDefault(); // Don't submit the form
  });

  $("#upload-button").on("click", function(e) {
    let form = $(this).closest("form")[0];
    let data = new FormData(form);
    data.delete("name");
    data.append("title", uploadInfo.name);
    data.append("season", uploadInfo.season);
    data.append("episode", uploadInfo.episode);
    $.ajax({
      url: "/upload",
      contentType: false,
      processData: false,
      method: "POST",
      data: data
    })
      .fail(function(jqXHR, textStatus, errorThrown) {
        $("[data-status]").toggleClass("hidden", true);

        if (jqXHR.status == 400 && jqXHR.responseJSON) {
          jqXHR.responseJSON.forEach(function(e, idx, arr) {
            let $status = $("#" + e[0] + "-status");
            if ($status) {
              console.log("Enabling " + e[0]);
              $status.toggleClass("hidden", false).html(e[1]);
            }
          });
        } else {
          alertify.error(
            "Ha ocurrido un error no identificado al intentar subir el subtítulo"
          );
        }
      })
      .done(function(data) {
        window.location.href = data;
      });
  });
});
