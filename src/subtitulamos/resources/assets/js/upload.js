/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2020 subtitulamos.tv
 */

import { onDomReady } from "./utils";
import "../css/upload.css";

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
    error = "Falta el nombre del episodio";
  }

  if (error) {
    uploadInfo = {
      season: "",
      episode: "",
      name: "",
    };
  } else {
    uploadInfo = {
      season: match[1],
      episode: match[2],
      name: match[3].trim(),
    };
  }

  const isError = error !== "";
  document.getElementById("upload-button").disabled = isError;

  const $nameStatus = document.getElementById("name-status");
  $nameStatus.classList.toggle("hidden", !isError);
  $nameStatus.innerHTML = error;
}

onDomReady(function () {
  let $newShowTemplate = document.getElementById("new-show-tpl");
  let $newShow;
  document.getElementById("show-id").addEventListener("change", function () {
    if (this.value == "NEW") {
      if (!$newShow) {
        this.closest(".field-body").appendChild($newShowTemplate.content.cloneNode(true));
        $newShow = document.getElementById("new-show");
        $newShow.focus();
      }
    } else if ($newShow) {
      this.closest(".field-body").children[1].remove();
      $newShow = null;
    }
  });

  // Logic for splitting season/episode and name
  document.getElementById("name").addEventListener("keyup", splitSeasonAndEpisodeCallback);
  document.getElementById("name").addEventListener("change", splitSeasonAndEpisodeCallback);

  document.querySelectorAll("#show-id, #lang, #version, #comments, #new-show, #sub").forEach($ele => $ele.addEventListener("change", function () {
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

  document.getElementById("upload").addEventListener("submit", function (e) {
    e.preventDefault(); // Don't submit the form
  });

  document.getElementById("upload-button").addEventListener("click", function (e) {
    e.preventDefault();

    const form = this.closest("form");
    this.classList.toggle("is-loading", true);

    let data = new FormData(form);
    data.delete("name");
    data.append("title", uploadInfo.name);
    data.append("season", uploadInfo.season);
    data.append("episode", uploadInfo.episode);
    fetch("/upload", {
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
