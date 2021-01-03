/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2021 subtitulamos.tv
 */

import "../css/shows_list.scss";
import { $getEle, $getAllEle } from "./utils.js";

function showListInLetter(letterButton) {
  const $showsInLetterList = $getEle("#" + letterButton.dataset.letter);
  $showsInLetterList.classList.toggle("hidden", false);

  $getAllEle(".letter").forEach((letter) => {
    letter.classList.toggle("active-letter", false);
  });
  letterButton.classList.toggle("active-letter", true);
}

$getAllEle(".letter").forEach((letter) => {
  letter.addEventListener("click", (e) => {
    $getAllEle(".shows-in-letter").forEach((list) => {
      list.classList.toggle("hidden", true);
    });

    showListInLetter(e.currentTarget);
  });
});
