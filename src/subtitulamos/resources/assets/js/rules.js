/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2020 subtitulamos.tv
 */

import "../css/rules.css";
import { onDomReady } from "./utils";

onDomReady(() => {
  for (const $spoilerWrapper of document.querySelectorAll(".spoiler-wrapper")) {
    $spoilerWrapper.addEventListener("click", function () {
      const $spoiler = this.querySelector(".spoiler-content");
      const $icon = this.querySelector("i");
      $icon.classList.toggle("fa-caret-down");
      $icon.classList.toggle("fa-caret-up");

      $spoiler.style.display =
        !$spoiler.style.display || $spoiler.style.display == "none" ? "block" : "none";
    });
  }
});
