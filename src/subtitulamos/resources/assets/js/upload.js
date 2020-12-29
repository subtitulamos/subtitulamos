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

function splitSeasonAndEpisodeCallback(element) {
  const val = element.value;
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

  element.setCustomValidity(error);
}

onDomReady(function () {
  const $uploadForm = document.getElementById("upload-form");
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
  ["keyup", "change"].forEach((event) =>
    $getEle("#ep-name").addEventListener(event, (e) =>
      splitSeasonAndEpisodeCallback(e.currentTarget)
    )
  );

  // SRT FILE
  // Always clear the file input on load
  let $sub = $getEle("#sub").cloneNode(true);
  $getEle("#sub").replaceWith($sub);

  // Update of SRT file selection
  $sub.addEventListener("change", (e) => {
    const $fileInput = e.target;
    let filename = $fileInput.value.split(/(\\|\/)/g).pop();

    if (filename.substring(filename.length - 3) !== "srt") {
      $fileInput.setCustomValidity("Tipo de fichero incorrecto");
      filename = "";
    } else {
      $fileInput.setCustomValidity("");
    }

    $getEle("#file-name").innerHTML = filename;
    $getEle("#file-upload-container").classList.toggle("has-file", filename !== "");

    const m = filename.match(/^([\w\s]+)\s-?\s*(\d{1,2})x(\d{1,2})\s*-\s*([^.]+)/);
    if (m) {
      const cleanShowName = m[1].trim().toLowerCase();
      const $showIdSelect = $getEle("#show-id");
      if (!$showIdSelect.value) {
        for (const opt of $showIdSelect.options) {
          if (opt.textContent.trim().toLowerCase() === cleanShowName) {
            $showIdSelect.value = opt.value;
          }
        }
      }
      const $epName = $getEle("#ep-name");
      if ($epName && !$epName.value) {
        $epName.value = `${Number(m[2])}x${m[3]} - ${m[4]}`;
      }
    }
  });

  $uploadForm.addEventListener("submit", function (e) {
    e.preventDefault(); // Don't submit the form

    $getEle("#uploading-overlay").classList.toggle("hidden", false);
    $uploadForm.classList.toggle("uploading", true);

    const $episodeNameField = $getEle("#ep-name");
    splitSeasonAndEpisodeCallback($episodeNameField);

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
        $getEle("#uploading-overlay").classList.toggle("hidden", true);
        $uploadForm.classList.toggle("uploading", false);

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
