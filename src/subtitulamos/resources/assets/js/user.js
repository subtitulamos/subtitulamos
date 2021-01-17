/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2021 subtitulamos.tv
 */

import "../css/user.scss";
import timeago from "timeago.js";
import { $getAllEle, $getEle, $getById, easyFetch, showOverlayFromTpl, onDomReady } from "./utils";

const $roleChangeForm = $getEle("#reset-user-pwd");
if ($roleChangeForm) {
  let manualSubmit = false;
  $roleChangeForm.addEventListener("submit", function (e) {
    if (manualSubmit) {
      return;
    }

    e.preventDefault(); // Don't submit the form

    Swal.fire({
      type: "warning",
      confirmButtonText: "Reiniciar",
      cancelButtonText: "Cancelar",
      showCancelButton: true,
      html:
        "Estás a punto de reiniciar la contraseña de este usuario. Se generará una nueva contraseña, y su contraseña actual dejará de valer para acceder." +
        "<br/><br/>¿Estás seguro de querer continuar?",
    }).then((result) => {
      if (result.value) {
        manualSubmit = true;
        $roleChangeForm.submit();
      }
    });
  });
}

const $banButton = $getEle("#ban");
if ($banButton) {
  $banButton.addEventListener("click", () => {
    showOverlayFromTpl("ban-dialog");

    $getAllEle("input[type='radio']").forEach((radio) => {
      radio.addEventListener("change", () => {
        $getEle("#detailed-duration").classList.toggle("hidden", radio.value === "permanent");
      });
    });
  });
}

function setSeeMoreVisibility($targetList) {
  $targetList.parentElement
    .querySelector(".spoiler-name")
    .classList.toggle("hidden", $targetList.offsetHeight < 480);
}

const $epTemplate = $getById("subtitle-card");
function addEpisodes(target, startIdx, count) {
  let $subtitleCardsWrap;

  switch (target) {
    case "upload":
      $subtitleCardsWrap = $getById(`${target}-list`);
    case "collab":
      $subtitleCardsWrap = $getById(`${target}-list`);
  }

  $subtitleCardsWrap.innerHTML = "";

  for (let i = startIdx; i < startIdx + count; ++i) {
    const $node = document.importNode($epTemplate.content, true);
    const $targetDiv = $node.children[0];
    $targetDiv.dataset.idx = i;
    subsByTab[target].$episodes[i] = $targetDiv;
    $subtitleCardsWrap.appendChild($node);
  }
}

let subsByTab = {
  collab: {
    $episodes: [],
  },
  upload: {
    $episodes: [],
  },
};
const loadList = (target, msgs) => {
  easyFetch(`/users/${targetUserId}/${target}-list`, {
    method: "get",
  })
    .then((reply) => reply.json())
    .then((data) => {
      const count = data.length;
      subsByTab[target].loading = true;
      addEpisodes(target, 0, count);
      const $targetList = $getById(`${target}-list`);

      $getEle(`#${target}-count`).innerHTML = count;
      if (count > 0) {
        for (let idx = 0; idx < count; idx++) {
          const ep = data[idx];

          const $card = subsByTab[target].$episodes[idx];
          $card.querySelector(".episode-time-ago").classList.toggle("hidden", true);
          $card.innerHTML = $card.innerHTML.replace("{ep_show}", ep.show);
          $card.innerHTML = $card.innerHTML.replace("{ep_season}", ep.season);
          $card.innerHTML = $card.innerHTML.replace("{ep_num}", ep.episode_number);
          $card.innerHTML = $card.innerHTML.replace("{ep_name}", ep.name);
          $card.innerHTML = $card.innerHTML.replace("{ep_url}", ep.url);
          $card.querySelector(".metadata").classList.toggle("hidden", false);
          $card.querySelector(".loading").classList.toggle("hidden", true);
        }
      } else {
        $targetList.innerHTML = msgs.noResults;
      }
      setSeeMoreVisibility($targetList);
    })
    .catch((err) => {
      console.log(err, target);
      Toasts.error.fire(msgs.error);
    });
};

onDomReady(() => {
  loadList("upload", {
    noResults: "El usuario no ha colaborado en ningún capítulo",
    error: "Ha ocurrido un error al cargar los capítulos en los que ha colaborado",
  });
  loadList("collab", {
    noResults: "El usuario no ha subido ningún capítulo",
    error: "Ha ocurrido un error al cargar los capítulos subidos",
  });

  $getAllEle(".option").forEach((option) => {
    option.addEventListener("click", (e) => {
      const $option = e.currentTarget;
      const $parent = $option.closest(".option-wrapper");
      const $toggleTargetsClass = $option.dataset.toggleTargets;
      const $enableElementId = $option.dataset.enable;

      $parent
        .querySelectorAll(".option.selected")
        .forEach((element) => element.classList.toggle("selected", false));
      $option.classList.toggle("selected");

      $getAllEle("." + $toggleTargetsClass).forEach((element) =>
        element.classList.toggle("hidden", true)
      );

      const $spoilerWrapper = $getById($enableElementId);
      $spoilerWrapper.classList.toggle("hidden", false);
      setSeeMoreVisibility($spoilerWrapper.querySelector(".spoiler-content"));
    });
  });

  for (const $spoilerName of $getAllEle(".spoiler-name")) {
    $spoilerName.addEventListener("click", function () {
      const $spoilerWrapper = this.closest(".spoiler-wrapper");
      const $spoiler = $spoilerWrapper.querySelector(".spoiler-content");

      if ($spoilerName.innerHTML.includes("MÁS")) {
        $spoilerName.innerHTML = $spoilerName.innerHTML.replace("MÁS", "MENOS");
      } else if ($spoilerName.innerHTML.includes("MENOS")) {
        $spoilerName.innerHTML = $spoilerName.innerHTML.replace("MENOS", "MÁS");
      }

      const $icon = this.querySelector(".spoiler-name i");
      $icon.classList.toggle("fa-chevron-down");
      $icon.classList.toggle("fa-chevron-up");

      $spoiler.classList.toggle("expanded");
    });
  }

  const $settingsForm = $getEle("#settings-form");
  const checkPwdValidity = () => {
    let err = "";
    const $pwdConfirm = $settingsForm.querySelector("[name=password-confirmation]");
    const $pwd = $settingsForm.querySelector("[name=password-new]");
    if ($pwdConfirm.value && $pwdConfirm.value != $pwd.value) {
      err = "Las contraseñas no coinciden";
    }

    $pwdConfirm.setCustomValidity(err);
  };

  if ($settingsForm) {
    $settingsForm.querySelectorAll("[name^=password]").forEach(($ele) => {
      $ele.addEventListener("keyup", checkPwdValidity);
      $ele.addEventListener("blur", checkPwdValidity);
    });
  }

  const $fontFamilyPreview = $getById("font-family-preview");
  if ($fontFamilyPreview) {
    $getById("font-family").addEventListener("change", (e) => {
      $fontFamilyPreview.classList.value = $fontFamilyPreview.classList.value.replace(
        new RegExp("font-family-([a-z]|-)*"),
        "font-family-" + e.target.value
      );
    });
  }

  const $fontThicknessPreview = $getById("font-thickness-preview");
  if ($fontThicknessPreview) {
    $getById("font-thickness").addEventListener("change", (e) => {
      $fontThicknessPreview.classList.value = $fontThicknessPreview.classList.value.replace(
        new RegExp("font-thickness-([a-z]|-)*"),
        "font-thickness-" + e.target.value
      );
    });
  }

  const $colorSwatchPreview = $getById("color-swatch-preview");
  if ($colorSwatchPreview) {
    $getById("color-swatch").addEventListener("change", (e) => {
      $colorSwatchPreview.classList.value = $colorSwatchPreview.classList.value.replace(
        new RegExp("color-swatch-([a-z]|-)*"),
        "color-swatch-" + e.target.value
      );
    });
  }
});
