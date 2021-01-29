/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2021 subtitulamos.tv
 */

import { $getAllEle, $getById, $getEle, easyFetch, onDomReady } from "./utils";
import timeago from "timeago.js";
import Vue from "vue";
import "./vue/comment.js";
import "../css/overview.scss";

let selectedPage = {
  paused: 1,
  modified: 1,
  uploads: 1,
  completed: 1,
  resyncs: 1,
};
let selectedTab = "uploads";
const CONTENT_PER_PAGE = 10;

onDomReady(() => {
  // Page navigation
  const $pages = $getAllEle("[data-page]");
  $pages.forEach(($button) => {
    $button.addEventListener("click", () => {
      const pageData = $button.dataset.page;
      const currentPage = selectedPage[selectedTab];

      if (pageData == "previous") {
        selectedPage[selectedTab] = Math.max(1, currentPage - 1);
      } else if (pageData == "next") {
        selectedPage[selectedTab] = currentPage + 1;
      } else {
        selectedPage[selectedTab] = Number(pageData);
      }

      if (selectedPage[selectedTab] === 1) {
        $getEle("[data-page='next'").classList.toggle("invisible", false);
      }
      $getEle(".page-group").classList.toggle("invisible", selectedPage[selectedTab] <= 1);

      loadTab(selectedTab, CONTENT_PER_PAGE);

      // Scroll up after changing pages
      $getEle(".navigation-list").scrollIntoView({
        behavior: "smooth",
        block: "start",
        inline: "nearest",
      });
    });
  });

  // Reload data on clicking the refresh button
  const $reloadButton = $getById("reload-search-content");
  if ($reloadButton) {
    $reloadButton.addEventListener("click", () => {
      loadTab(selectedTab, CONTENT_PER_PAGE);
    });
  }

  // Initial data load and automatic refresh
  const $subtitlesSearchContent = $getById("search-content");
  const $commentsContainer = $getById("comments-container");
  if ($subtitlesSearchContent) {
    loadTab(selectedTab, CONTENT_PER_PAGE);
    setInterval(() => loadTab(selectedTab, CONTENT_PER_PAGE), 60000);
  } else if ($commentsContainer) {
    loadComments(CONTENT_PER_PAGE);
    setInterval(() => loadComments(CONTENT_PER_PAGE), 60000);
  }

  // Changing subtitles tab
  $getAllEle("[data-search]").forEach(($tab) => {
    $tab.addEventListener("click", () => {
      $tab.parentElement.querySelector(".selected").classList.toggle("selected", false);
      $tab.classList.toggle("selected", true);
      selectedTab = $tab.dataset.search;
      loadTab(selectedTab, CONTENT_PER_PAGE);
    });
  });
});

function listRenderer($list, data) {
  $list.innerHTML = "";

  if (data.length > 0) {
    data.forEach((ep, idx) => {
      let $li = document.createElement("li");
      $li.classList.toggle("w-box-shadow", true);
      $li.innerHTML += `<div><a class="text blue-a bold" href="/episodes/${ep.id}/${ep.slug}">${ep.full_name}</a></div>`;
      $li.innerHTML += `<span class="text language small">${ep.lang} - ${ep.version}</span>`;
      const timeAgo = timeago().format(ep.time, "es");
      let rowDetail = timeAgo;
      if (ep.last_edited_by) {
        rowDetail += ` · <b>${ep.last_edited_by}</b>`;
      }
      if (ep.progress) {
        rowDetail += ` · ${ep.progress}%`;
      }
      $li.innerHTML += `<div class="time-ago text tiny">${rowDetail}</div>`;
      $list.appendChild($li);
    });
  } else {
    let $li = document.createElement("li");
    $li.innerHTML += "<div>No hay nada aqui en el momento</div>";
    $list.appendChild($li);
  }
}

function loadTab(selectedTab, count) {
  const $subtitlesSearchContent = $getEle("#search-content");
  const $list = $subtitlesSearchContent.querySelector("ul");

  easyFetch("/search/" + selectedTab, {
    params: {
      from: (selectedPage[selectedTab] - 1) * count,
      count,
    },
  })
    .then((res) => res.json())
    .then((data) => {
      listRenderer($list, data);
      $subtitlesSearchContent
        .querySelector("[data-page='next'")
        .classList.toggle("invisible", data.length < count);
      $getEle(".page-group").classList.toggle("invisible", selectedPage[selectedTab] <= 1);
    });
}

if ($getById("comments-container")) {
  var comments = new Vue({
    el: "#comments-container",
    data: {
      comments: [],
      page: 1,
      commentType: "episodes",
      firstLoad: true,
    },
    methods: {
      refresh: function () {
        loadComments(CONTENT_PER_PAGE);
      },

      remove: function (id) {
        const targetCommentIdx = this.comments.findIndex((comment) => comment.id === id);
        if (targetCommentIdx < 0) {
          Toast.fire({
            type: "error",
            title: "Ha ocurrido un extraño error al borrar el comentario",
          });
          return;
        }

        const targetComment = this.comments[targetCommentIdx];
        this.comments.splice(targetCommentIdx, 1);

        const isEpisode = typeof targetComment.episode !== "undefined";
        const deleteUrl = isEpisode
          ? `/episodes/${targetComment.episode.id}/comments/${id}`
          : `/subtitles/${targetComment.subtitle.id}/translate/comments/${id}`;
        easyFetch(deleteUrl, {
          method: "DELETE",
        })
          .then(() => {
            this.refresh();
          })
          .catch(() => {
            Toast.fire({
              type: "error",
              title: "Ha ocurrido un error al borrar el comentario",
            });
            if (typeof targetCommentIdx !== "undefined") {
              // Insert the comment right back where it was
              this.comments.splice(targetCommentIdx, 0, targetComment);
            } else {
              this.refresh();
            }
          });
      },

      pin: function (id) {
        const targetComment = this.comments.find((comment) => comment.id === id);
        if (!targetComment) {
          Toast.fire({
            type: "error",
            title: "Ha ocurrido un extraño error al fijar el comentario",
          });
          return;
        }

        const isEpisode = typeof targetComment.episode !== "undefined";
        const pinUrl = isEpisode
          ? `/episodes/${targetComment.episode.id}/comments/${id}/pin`
          : `/subtitles/${targetComment.subtitle.id}/translate/comments/${id}/pin`;

        easyFetch(pinUrl, {
          method: "POST",
        })
          .then(() => {
            this.refresh();
          })
          .catch(() => {
            Toasts.error.fire("Ha ocurrido un error al intentar fijar el comentario");
            this.refresh();
          });
      },

      save: function (id, text) {
        const targetComment = this.comments.find((comment) => comment.id === id);
        if (!targetComment) {
          Toast.fire({
            type: "error",
            title: "Ha ocurrido un extraño error al editar el comentario",
          });
          return;
        }

        const isEpisode = typeof targetComment.episode !== "undefined";
        const editUrl = isEpisode
          ? `/episodes/${targetComment.episode.id}/comments/${id}/edit`
          : `/subtitles/${targetComment.subtitle.id}/translate/comments/${id}/edit`;

        easyFetch(editUrl, {
          method: "POST",
          rawBody: {
            text,
          },
        }).catch(() => {
          Toasts.error.fire("Ha ocurrido un error al intentar editar el comentario");
        });
      },

      setCommentType: function (type) {
        this.commentType = type;
        this.page = 1;
        this.refresh();
      },

      firstPage: function () {
        this.page = 1;
        this.refresh();
      },

      nextPage: function () {
        this.page++;
        this.refresh();
      },

      prevPage: function () {
        this.page--;
        this.refresh();
      },
    },
    computed: {
      nextPageInvisible: function () {
        return !this.comments.length || this.comments.length % CONTENT_PER_PAGE;
      },
    },
  });
}

function loadComments(count) {
  easyFetch(`/comments/${comments.commentType}/load`, {
    params: {
      from: (comments.page - 1) * count,
      count,
    },
  })
    .then((data) => data.json())
    .then((data) => {
      comments.comments = data;
    })
    .catch(() => {
      Toasts.error.fire("Ha ocurrido un error tratando de cargar los comentarios");
    })
    .finally(() => {
      if (comments.firstLoad) {
        comments.firstLoad = false;
      }
    });
}
