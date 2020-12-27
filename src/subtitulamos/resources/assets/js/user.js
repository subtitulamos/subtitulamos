/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2020 subtitulamos.tv
 */

import Vue from "vue";
import "../css/user.scss";

let page = new Vue({
  el: "#user-settings",
  data: {
    newpwd: "",
  },
});

let page_2 = new Vue({
  el: "#user-profile",
  data: {
    mode: "normal",
    duration: "",
  },
});
