/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2021 subtitulamos.tv
 */

import "../css/rules.scss";
import { $getAllEle, onDomReady } from "./utils";

onDomReady(() => {
  for (const $spoilerName of $getAllEle(".spoiler-name")) {
    $spoilerName.addEventListener("click", function () {
      const $spoilerWrapper = this.closest(".spoiler-wrapper");
      const $spoiler = $spoilerWrapper.querySelector(".spoiler-content");

      if ($spoilerName.innerHTML.includes("VER")) {
        $spoilerName.innerHTML = $spoilerName.innerHTML.replace("VER", "OCULTAR");
      } else if ($spoilerName.innerHTML.includes("OCULTAR")) {
        $spoilerName.innerHTML = $spoilerName.innerHTML.replace("OCULTAR", "VER");
      }
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
});
