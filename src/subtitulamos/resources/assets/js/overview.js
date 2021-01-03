import { $getAllEle, $getById, $getEle, easyFetch, onDomReady } from "./utils";
import timeago from "timeago.js";
import Vue from "vue";
import "./vue/comment.js";

let selectedPage = {
  paused: 1,
  modified: 1,
  uploads: 1,
  completed: 1,
};
const contentPerPage = 5;

onDomReady(() => {
  // Page navigation
  const $pages = $getAllEle("[data-page]");
  $pages.forEach(($button) => {
    $button.addEventListener("click", () => {
      const $pagesWrap = $button.closest(".pages");
      const target = $pagesWrap.dataset.target;
      const pageData = $button.dataset.page;
      const currentPage = selectedPage[target];

      if (pageData == "previous") {
        selectedPage[target] = Math.max(1, currentPage - 1);
      } else if (pageData == "next") {
        selectedPage[target] = currentPage + 1;
      } else {
        selectedPage[target] = Number(pageData);
      }

      if (selectedPage[target] === 1) {
        $pagesWrap.querySelector("[data-page='next'").classList.toggle("invisible", false);
      }

      $pagesWrap
        .querySelector(".page-group")
        .classList.toggle("invisible", selectedPage[target] <= 1);

      loadOverviewGridCell(target, contentPerPage);
    });
  });

  // Reload data on clicking the refresh button
  const $reloadButtons = $getAllEle("[data-reload]");
  $reloadButtons.forEach(($button) => {
    $button.addEventListener("click", () => {
      const target = $button.dataset.reload;
      loadOverviewGridCell(target, contentPerPage);
    });
  });

  // Initial data load and automatic refresh
  const $overview = $getById("overview-grid");
  if ($overview) {
    loadOverviewData();
    setInterval(loadOverviewData, 60000);
  }

  // Expanding overview cards on mobile
  $getAllEle(".grid-cell-title").forEach(($cellTitle) => {
    const $cell = $cellTitle.closest(".grid-cell");

    $cellTitle.addEventListener("click", () => {
      $cell.classList.toggle("collapsed");
      $cell.querySelector(".grid-content").classList.toggle("expanded");
    });
  });
});

function loadOverviewData() {
  loadOverviewGridCell("paused", contentPerPage);
  loadOverviewGridCell("modified", contentPerPage);
  loadOverviewGridCell("uploads", contentPerPage);
  loadOverviewGridCell("completed", contentPerPage);
  loadComments(contentPerPage, true);
}

function listRenderer($list, data) {
  $list.innerHTML = "";

  if (data.length > 0) {
    data.forEach((ep, idx) => {
      let $li = document.createElement("li");
      $li.innerHTML += `<div><a class="text blue-a bold" href="/episodes/${ep.id}/${ep.slug}">${ep.full_name}</a></div>`;
      $li.innerHTML += `<span class="text language small">${ep.lang} - ${ep.version}</span>`;
      const timeAgo = timeago().format(ep.time, "es");
      $li.innerHTML += `<div class="time-ago text tiny">${timeAgo}</div>`;
      $list.appendChild($li);
    });
  } else {
    let $li = document.createElement("li");
    $li.innerHTML += "<div>No hay nada aqui en el momento</div>";
    $list.appendChild($li);
  }
}

function loadOverviewGridCell(target, count) {
  const $targetContainer = $getEle(`[data-search-path="${target}"]`);
  const $list = $targetContainer.querySelector("ul");

  const searchPath = $targetContainer.dataset.searchPath;
  easyFetch("/search/" + searchPath, {
    params: {
      from: (selectedPage[target] - 1) * count,
      count,
    },
  })
    .then((res) => res.json())
    .then((data) => {
      listRenderer($list, data);
      if (data.length < count) {
        $targetContainer.querySelector("[data-page='next'").classList.toggle("invisible", true);
      }
    });
}

let comments = new Vue({
  el: "#comments-container",
  data: {
    comments: [],
    page: 1,
    commentType: "subtitles",
  },
  methods: {
    refresh: function (hardRefresh = false) {
      loadComments(contentPerPage, hardRefresh);
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
      return !this.comments.length || this.comments.length % contentPerPage;
    },
  },
});

function loadComments(count, hardRefresh = false) {
  if (hardRefresh) {
    comments.comments = [];
  }
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
    });
}
