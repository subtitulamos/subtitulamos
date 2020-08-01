/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2020 subtitulamos.tv
 */

import Vue from "vue";
import { dateDiff, easyFetch } from "./utils.js";
import '../css/index.css';

let episodeList = new Vue({
  el: "#incategory_board",
  data: {
    category: "",
    episodes: [],
  },
  methods: {
    subURI: function (ep) {
      return "/episodes/" + ep.id + "/" + ep.slug;
    },

    update: function () {
      let self = this;
      let u = function () {
        self.episodes.forEach(function (ep, idx, arr) {
          let diff = dateDiff(new Date(ep.time), new Date(Date.now())) / 1000;
          let unit = "";
          if (diff >= 60) {
            diff = Math.floor(diff / 60);
            if (diff >= 60) {
              diff = Math.floor(diff / 60);
              if (diff >= 24) {
                diff = Math.floor(diff / 24);
                unit = diff > 1 ? "días" : "día";
              } else {
                unit = diff > 1 ? "horas" : "hora";
              }
            } else {
              unit = diff > 1 ? "mins" : "min";
            }
          } else {
            // < 60s, display every 10s
            diff = Math.floor(diff / 10) * 10;
            unit = "seg";
          }

          if (diff != ep.time_ago) {
            ep.time_ago = diff;
            ep.time_unit = unit;
            arr[idx] = ep;
          }
        });
      };

      u(); // Insta update times
      this.interval = setInterval(u, 2000);
    },
  },
  watch: {
    episodes: function (newEpisodes) {
      clearInterval(this.interval);
      this.update();
    },
  },
});

let categoryPage = {};
let rowsPerPage = 0;
function loadTab(target, page) {
  easyFetch("/search/" + target, {
    params: {
      page: page,
    },
  }).then(res => res.json())
    .then(data => {
      data.forEach(function (_, idx, data) {
        data[idx].time_ago = 0;
        data[idx].time_unit = "sec";
      });

      episodeList.category = target;
      episodeList.episodes = data;
      categoryPage[target] = page;

      if (rowsPerPage == 0 || rowsPerPage < episodeList.episodes.length) {
        // First load? Let's guess the value
        rowsPerPage = episodeList.episodes.length;
      }

      let nextPageHidden = episodeList.episodes.length < rowsPerPage;
      let prevPageHidden = page <= 1;
      document.getElementById("next-page").classList.toggle("hidden", nextPageHidden);
      document.getElementById("prev-page").classList.toggle("hidden", prevPageHidden);
      document.getElementById("pages").classList.toggle("hidden", nextPageHidden && prevPageHidden);
    });
}

document.getElementById("prev-page").addEventListener("click", function () {
  let targetPage = Math.max(categoryPage[episodeList.category] - 1, 1);
  if (targetPage == 1) {
    this.classList.toggle("hidden", true);
  }

  document.getElementById("next-page").classList.toggle("hidden", false);
  loadTab(episodeList.category, targetPage);
});

document.getElementById("next-page").addEventListener("click", function () {
  let targetPage = Math.min(categoryPage[episodeList.category] + 1, 10);
  if (targetPage >= 10) {
    this.classList.toggle("hidden", true);
  }

  document.getElementById("prev-page").classList.toggle("hidden", false);
  loadTab(episodeList.category, targetPage);
});

document.querySelectorAll(".category_navigation_item").forEach($ele => $ele.addEventListener("click", function () {
  const $largeSplash = document.getElementById("large_splash");
  const $incategoryState = document.getElementById("incategory_state");
  const $whiteLogoSearchBar = document.getElementById("white-logo-searchbar");
  $incategoryState.classList.toggle("hidden", false);

  document.querySelectorAll(".category_navigation_item").forEach($ele => {
    $ele.classList.toggle("nvbi_active", false);
  });
  this.classList.toggle("nvbi_active", true);

  var viewport_w = Math.max(document.documentElement.clientWidth, window.innerWidth || 0);
  if (viewport_w >= 840 && !$largeSplash.dataset.hidden) {
    // We only display the white logo for large viewports
    window.scrollTo(0, 0);
    $whiteLogoSearchBar.classList.toggle("hidden", false);
    $largeSplash.style.maxHeight = 0;
    $largeSplash.style.marginBottom = 0;
    $largeSplash.style.opacity = 0;
    $largeSplash.dataset.hidden = true;
    setTimeout(() => {
      $largeSplash.style.display = "none";
    }, 1000);
  }

  let target;
  switch (this.id) {
    case "most-downloaded":
      target = "popular";
      break;

    case "last-uploaded":
      target = "uploads";
      break;

    case "last-completed":
      target = "completed";
      break;

    case "last-edited":
      target = "modified";
      break;

    case "paused":
      target = "paused";
      break;

    case "last-resynced":
      target = "resyncs";
      break;
  }

  if (!target)
    // Nothing to do
    return;

  loadTab(target, 1);
}));
