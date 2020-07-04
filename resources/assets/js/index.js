/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2020 subtitulamos.tv
 */

import Vue from "vue";
import $ from "jquery";
import { dateDiff } from "./app.js";
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
function search(target, page) {
  /*document.getElementById('category_navigation_list').scrollIntoView();*/

  $.ajax({
    url: "/search/" + target,
    method: "get",
    data: {
      page: page,
    },
  }).done(function (data) {
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
    $("#next-page").toggleClass("hidden", nextPageHidden);
    $("#prev-page").toggleClass("hidden", prevPageHidden);
    $("#pages").toggleClass("hidden", nextPageHidden && prevPageHidden);
  });
}

$("#prev-page").on("click", function () {
  let targetPage = Math.max(categoryPage[episodeList.category] - 1, 1);
  if (targetPage == 1) {
    $(this).toggleClass("hidden", true);
  }

  $("#next-page").toggleClass("hidden", false);
  search(episodeList.category, targetPage);
});

$("#next-page").on("click", function () {
  let targetPage = Math.min(categoryPage[episodeList.category] + 1, 10);
  if (targetPage >= 10) {
    $(this).toggleClass("hidden", true);
  }

  $("#prev-page").toggleClass("hidden", false);
  search(episodeList.category, targetPage);
});

$(".category_navigation_item").on("click", function () {
  let $categoryClicked = $(this);
  let $largeSplash = $("#large_splash");
  let $incategoryState = $("#incategory_state");
  let $whiteLogoSearchBar = $("#white-logo-searchbar");

  $incategoryState.toggleClass("hidden", false);

  if ($(".category_navigation_item").hasClass("nvbi_active")) {
    $(".category_navigation_item").toggleClass("nvbi_active", false);
  }

  $categoryClicked.toggleClass("nvbi_active", true);
  var viewport_w = Math.max(document.documentElement.clientWidth, window.innerWidth || 0);
  if (viewport_w >= 840 && $largeSplash.hasClass("fade_out") == false) {
    // Only display the white logo for large viewports
    window.scrollTo(0, 0);
    $largeSplash.toggleClass("fade_out", true);
    $whiteLogoSearchBar.toggleClass("hidden", false).attr("style", "display:none");
    $whiteLogoSearchBar.fadeIn("slow");
    $largeSplash.slideUp({
      done: () => { },
    });
  }

  let target;
  let id = $categoryClicked.attr("id");
  switch (id) {
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

  search(target, 1);
});
