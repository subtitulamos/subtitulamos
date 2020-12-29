/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2020 subtitulamos.tv
 */

import { $getEle, onDomReady } from "./utils";
import "../css/upload.scss";
import "../css/rules.scss";

let uploadInfo = {
  season: "",
  episode: "",
  name: "",
};

function splitSeasonAndEpisodeCallback() {
  const val = this.value.trim();
  const match = val.match(/^S?(\d+)(?:[xE](?:(\d+)(?:[\s-]+\s*([^-\s].*))?)?)?/);
  let error = "";
  if (val == "" || (!match && val === "S")) {
    error = "";
  } else if (!match && val !== "S") {
    error = "Faltan la temporada y número de episodio (Formato: 0x00 - Nombre)";
  } else if (typeof match[1] !== "undefined" && typeof match[2] === "undefined") {
    error = "Faltan número de episodio y nombre del episodio (Formato: 0x00 - Nombre)";
  } else if (typeof match[1] !== "undefined" && typeof match[2] !== "undefined" && !match[3]) {
    error = "Falta el nombre del episodio (Formato: 0x00 - Nombre)";
  }

  uploadInfo = {
    season: error ? "" : match[1],
    episode: error ? "" : match[2],
    name: error ? "" : match[3].trim(),
  };

  this.setCustomValidity(error);
}

onDomReady(function () {
  let $newShow;
  document.getElementById("show-id").addEventListener("change", function () {
    if (this.value == "NEW") {
      if (!$newShow) {
        $newShow = $getEle("#new-show");
        $newShow.closest(".form-field").classList.toggle("hidden", false);
        $newShow.focus();
      }
    } else if ($newShow) {
      $newShow.closest(".form-field").classList.toggle("hidden", true);
      $newShow = null;
    }
  });

  // Logic for splitting season/episode and name
  $getEle("#name").addEventListener("keyup", splitSeasonAndEpisodeCallback);
  $getEle("#name").addEventListener("change", splitSeasonAndEpisodeCallback);

  // SRT FILE
  // Always clear the file input on load
  let $sub = $getEle("#sub").cloneNode(true);
  $getEle("#sub").replaceWith($sub);

  // Update of SRT file selection
  $sub.addEventListener("change", (e) => {
    const $fileInput = e.target;
    let $filename = $fileInput.value.split(/(\\|\/)/g).pop();

    if ($filename.substring($filename.length - 3) !== "srt") {
      $fileInput.setCustomValidity("Invalid file type");
      $filename = "";
    } else {
      $fileInput.setCustomValidity("");
    }

    $getEle("#file-name").innerHTML = $filename;
    $getEle("#file-upload-container").classList.toggle("has-file", $filename !== "");
  });

  document.getElementById("upload-form").addEventListener("submit", function (e) {
    e.preventDefault(); // Don't submit the form

    $getEle("#uploading-overlay").classList.toggle("hidden", false);
    $getEle("form").classList.toggle("uploading", true);

    const form = e.target;
    let data = new FormData(form);
    data.delete("name");
    data.append("title", uploadInfo.name);
    data.append("season", uploadInfo.season);
    data.append("episode", uploadInfo.episode);

    fetch("/upload", {
      method: "POST",
      body: data, // Already form-encoded
    })
      .then((res) => {
        $getEle("#uploading-overlay").classList.toggle("hidden", true);
        $getEle("form").classList.toggle("uploading", false);

        if (res.ok === false) {
          throw {
            error: true,
            response: res,
          };
        }
        return res;
      })
      .then((res) => res.text())
      .then((data) => {
        window.location.href = data;
      })
      .catch((err) => {
        const reportUnknownError = () =>
          Toasts.error.fire("Ha ocurrido un error no identificado al intentar subir el subtítulo");
        if (err.response) {
          err.response
            .json()
            .then((data) => {
              data.forEach(function (e, idx, arr) {
                document.getElementsByName(e[0])[0].setCustomValidity(err[1]);
              });
            })
            .catch(reportUnknownError);
        } else {
          reportUnknownError();
        }
      });
  });

  // Add style when dragging file over the file container
  $getEle("#file-upload-container").addEventListener("dragenter", (e) => {
    const $element = e.currentTarget;
    $element.classList.toggle("dragging", true);

    ["dragleave", "mouseleave", "mouseup"].forEach((event) =>
      $element.addEventListener(event, () => {
        $element.classList.toggle("dragging", false);
      })
    );
  });
});
