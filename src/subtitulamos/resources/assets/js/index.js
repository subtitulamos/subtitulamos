/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2020 subtitulamos.tv
 */

import Vue from "vue";
import { dateDiff, easyFetch, get, get_all } from "./utils.js";
import "../css/index.scss";

let episodeList = new Vue({
  el: "#subtitle-cards-wrap",
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
const subsPerPage = 5;
function loadTab(target, page) {
  easyFetch("/search/" + target, {
    params: {
      page: page,
    },
  })
    .then((res) => res.json())
    .then((data) => {
      data.forEach(function (_, idx, data) {
        data[idx].time_ago = 0;
        data[idx].time_unit = "sec";
      });

      episodeList.category = target;
      episodeList.episodes = data;
      categoryPage[target] = page;

      const isFirstPage = page <= 1;
      const isLastPage = episodeList.episodes.length < subsPerPage;

      get("#category-container").classList.toggle("first-page", isFirstPage);
      get("#category-container").classList.toggle("last-page", isLastPage);
    });
}

get("#previous-page").addEventListener("click", function () {
  let targetPage = Math.max(categoryPage[episodeList.category] - 1, 1);

  loadTab(episodeList.category, targetPage);
});

get("#next-page").addEventListener("click", function () {
  let targetPage = Math.min(categoryPage[episodeList.category] + 1, subsPerPage);

  loadTab(episodeList.category, targetPage);
});

get_all(".navigation-item").forEach(($ele) => {
  $ele.addEventListener("click", function () {
    get_all(".navigation-item").forEach(($otherEle) =>
      $otherEle.classList.toggle("selected", false)
    );

    this.classList.toggle("selected", true);

    let target;
    switch (this.id) {
      case "highlighted":
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
  });
});
