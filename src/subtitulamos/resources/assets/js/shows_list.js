/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2020 subtitulamos.tv
 */

import "../css/shows_list.scss";
import { get, get_all } from "./utils.js";

function showListInLetter(letterButton) {
  const $showsInLetterList = get("#" + letterButton.dataset.letter);
  $showsInLetterList.classList.toggle("hidden", false);

  get_all(".letter").forEach((letter) => {
    letter.classList.toggle("active-letter", false);
  });
  letterButton.classList.toggle("active-letter", true);
}

get_all(".letter").forEach((letter) => {
  letter.addEventListener("click", (e) => {
    get_all(".shows-in-letter").forEach((list) => {
      list.classList.toggle("hidden", true);
    });

    showListInLetter(e.currentTarget);
  });
});
