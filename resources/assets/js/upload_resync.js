/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2020 subtitulamos.tv
 */

import $ from "jquery";

function pad(n, width, z) {
  z = z || "0";
  n = n + "";
  return n.length >= width ? n : new Array(width - n.length + 1).join(z) + n;
}

$(function () {
  $("#lang, #version, #comments, #sub").on("change", function () {
    $("#" + $(this).attr("id") + "-status").toggleClass("hidden", true);
  });

  // SRT FILE
  // Always clear the file input on load
  let $sub = $("#sub").clone();
  $("#sub").replaceWith($sub);

  // Update of SRT file selection
  $sub.on("change", function (e) {
    let files = $sub[0].files;
    if (files.length > 0) {
      $("#sub-name").html(files[0].name);
    }
  });

  $("form").on("submit", function (e) {
    e.preventDefault(); // Don't submit the form
  });

  $("#upload-button").on("click", function (e) {
    const $this = $(this);
    let form = $this.closest("form")[0];
    $this.toggleClass("is-loading", true);

    let data = new FormData(form);
    $.ajax({
      url: window.location.pathname,
      contentType: false,
      processData: false,
      method: "POST",
      data: data,
    })
      .fail(function (jqXHR, textStatus, errorThrown) {
        $this.toggleClass("is-loading", false);
        $("[data-status]").toggleClass("hidden", true);

        if (jqXHR.status == 400 && jqXHR.responseJSON) {
          jqXHR.responseJSON.forEach(function (e, idx, arr) {
            let $status = $("#" + e[0] + "-status");
            if ($status) {
              console.log("Enabling " + e[0]);
              $status.toggleClass("hidden", false).html(e[1]);
            }
          });
        } else {
          Toasts.error.fire("Ha ocurrido un error no identificado al intentar subir el subtítulo");
        }
      })
      .done(function (data) {
        window.location.href = data;
      });
  });
});
