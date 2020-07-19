/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2020 subtitulamos.tv
 */

import $ from "jquery";
import '../css/rules.css';

$(function () {
  $(".spoiler-wrapper").on("click", function () {
    let $this = $(this);
    let $spoiler = $this.find(".spoiler-content");
    $this
      .find("i")
      .toggleClass("fa-caret-down")
      .toggleClass("fa-caret-up");

    $spoiler.css("display", $spoiler.css("display") == "none" ? "block" : "none");
  });
});
