/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2020 subtitulamos.tv
 */

import "../css/upload.css";
import { onDomReady } from "./utils";

onDomReady(function () {
  document.querySelectorAll("#lang, #version, #comments, #sub").forEach($ele => $ele.addEventListener("change", function () {
    document.getElementById(`${this.id}-status`).classList.toggle("hidden", true);
  }));

  // SRT FILE
  // Always clear the file input on load
  let $sub = document.getElementById("sub").cloneNode(true);
  document.getElementById("sub").replaceWith($sub);

  // Update of SRT file selection
  $sub.addEventListener("change", function (e) {
    let files = $sub.files;
    if (files.length > 0) {
      document.getElementById("sub-name").innerHTML = files[0].name;
    }
  });


  document.getElementById("upload-form").addEventListener("submit", function (e) {
    e.preventDefault(); // Don't submit the form
  });

  document.getElementById("upload-button").addEventListener("click", function (e) {
    const form = this.closest("form");
    this.classList.toggle("is-loading", true);

    const data = new FormData(form);
    fetch(window.location.pathname, {
      method: "POST",
      body: data, // Already form-encoded
    })
      .then(res => {
        if (res.ok === false) {
          throw {
            error: true,
            response: res
          }
        }

        return res;
      })
      .then(res => res.text())
      .then(data => {
        window.location.href = data;
      })
      .catch(err => {
        this.classList.toggle("is-loading", false);
        document.querySelectorAll("[data-status]").forEach($ele => $ele.classList.toggle("hidden", true));

        const reportUnknownError = () => Toasts.error.fire("Ha ocurrido un error no identificado al intentar subir el subtítulo");
        if (err.response) {
          err.response.json().then(data => {
            data.forEach(function (e, idx, arr) {
              let $status = document.getElementById(`${e[0]}-status`);
              if ($status) {
                $status.classList.toggle("hidden", false);
                $status.innerHTML = e[1];
              }
            })
          }).catch(reportUnknownError);
        } else {
          reportUnknownError();
        }
      });
  });
});
