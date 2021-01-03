/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2021 subtitulamos.tv
 */

import { $getEle, crossBrowserFormValidityReport, onDomReady } from "./utils";
import "../css/upload.scss";
import "../css/rules.scss";

function splitSeasonAndName(val) {
  const match = val.match(/^S?(\d+)(?:[xE](?:(\d+)(?:[\s-]+\s*([^-\s].*))?)?)?/);
  let error = "";

  if (val == "" || (!match && val === "S")) {
    error = "Formato: 0x00 - Nombre";
  } else if (!match && val !== "S") {
    error = "Faltan la temporada y número de episodio (Formato: 0x00 - Nombre)";
  } else if (typeof match[1] !== "undefined" && typeof match[2] === "undefined") {
    error = "Faltan número de episodio y nombre del episodio (Formato: 0x00 - Nombre)";
  } else if (typeof match[1] !== "undefined" && typeof match[2] !== "undefined" && !match[3]) {
    error = "Falta el nombre del episodio (Formato: 0x00 - Nombre)";
  }

  const hasError = error !== "";
  return {
    season: hasError ? "" : match[1],
    episode: hasError ? "" : match[2],
    name: hasError ? "" : match[3].trim(),
    error,
  };
}

onDomReady(function () {
  const $uploadForm = document.getElementById("upload-form");
  let $newShow = $getEle("#new-show");
  document.getElementById("show-id").addEventListener("change", function () {
    const isNewShow = this.value == "NEW";
    $newShow.parentElement.classList.toggle("hidden", !isNewShow);
    if (isNewShow) {
      $newShow.focus();
    } else {
      $newShow.value = "";
    }
  });

  // Makes sure all input fields have a validation clear when they change
  // MUST GO BEFORE THE OTHER VALIDATORS (otherwise they will do nothing :) )
  $uploadForm.querySelectorAll("input, select").forEach(($ele) => {
    const clearValidity = (e) => {
      e.target.setCustomValidity(""); // Reset error, user might've fixed it
    };
    $ele.addEventListener("change", clearValidity);
    $ele.addEventListener("keyup", (e) => {
      clearValidity(e);
      if (e.key == "Enter") {
        $uploadForm.querySelector("[type=submit]").click();
      }
    });
  });

  // Logic for splitting season/episode and name
  ["keyup", "change"].forEach((eventType) => {
    $getEle("#ep-name").addEventListener(eventType, (e) => {
      const splitInfo = splitSeasonAndName(e.target.value);
      e.target.setCustomValidity(splitInfo.error);
    });
  });

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

  $uploadForm.querySelector("[type=submit]").addEventListener("click", function (e) {
    e.preventDefault(); // Don't submit the form
    if (!$uploadForm.checkValidity()) {
      crossBrowserFormValidityReport($uploadForm);
      return;
    }

    $getEle("#uploading-overlay").classList.toggle("hidden", false);
    $uploadForm.classList.toggle("uploading", true);

    const $episodeNameField = $getEle("#ep-name");
    const uploadInfo = splitSeasonAndName($episodeNameField.value);

    let data = new FormData($uploadForm);

    data.delete("name");
    data.append("title", uploadInfo.name);
    data.append("season", uploadInfo.season);
    data.append("episode", uploadInfo.episode);

    fetch(window.location.pathname, {
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
                document.getElementsByName(e[0])[0].setCustomValidity(e[1]);
              });

              $uploadForm.reportValidity();
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
