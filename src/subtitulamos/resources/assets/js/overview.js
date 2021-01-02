import { $getAllEle, $getById, easyFetch } from "./utils";
import timeago from "timeago.js";
import Vue from "vue";
import "./vue/comment.js";

const $overview = $getById("overview-grid");
if ($overview) {
  loadOverview();
  setInterval(() => {
    loadOverview();
  }, 60000);
}

function loadOverview() {
  loadOverviewGridCell("paused", 10);
  loadOverviewGridCell("last-modified", 10);
  loadOverviewGridCell("last-uploads", 10);
  loadOverviewGridCell("last-completed", 10);
  loadComments("subtitles", 1);
}

function listRenderer($list, data) {
  $list.innerHTML = "";

  if (data) {
    data.forEach((ep, idx) => {
      let $li = document.createElement("li");
      $li.innerHTML += `<div><a href="/episodes/${ep.id}/${ep.slug}">${ep.name}</a></div>`;
      const timeAgo = timeago().format(ep.time, "es");
      $li.innerHTML += `<div class="time-ago text tiny">${timeAgo}</div>`;
      $list.appendChild($li);
    });
  }
}

function loadOverviewGridCell(targetId, count, page = 1) {
  const $targetContainer = $getById(targetId);
  const $list = $targetContainer.querySelector("ul");
  const $count = $targetContainer.querySelector(".count");

  const searchPath = $targetContainer.dataset.searchPath;

  easyFetch("/search/" + searchPath, {
    params: {
      from: (page - 1) * count,
      count,
    },
  })
    .then((res) => res.json())
    .then((data) => {
      listRenderer($list, data);
      $count.innerHTML = data.length;
    });
}

const $pages = $getAllEle(".page");
$pages.forEach(($button) => {
  $button.addEventListener("click", () => {
    const targetId = $button.closest(".pages").dataset.targetId;

    $pages.forEach(($page) => $page.classList.toggle("selected", false));
    $button.classList.toggle("selected", true);

    loadOverviewGridCell(targetId, 10, $button.dataset.page);
  });
});

const $commentTypes = $getAllEle("[data-comments-type]");
$commentTypes.forEach(($button) => {
  console.log("here");
  $button.addEventListener("click", () => {
    const commentsType = $button.dataset.commentsType;
    $commentTypes.forEach(($page) => $page.classList.toggle("selected", false));
    $button.classList.toggle("selected", true);

    loadComments(commentsType, 1);
  });
});

let comments = new Vue({
  el: "#comments-container",
  data: {
    comments: [],
    page: 1,
  },
  methods: {
    refresh: function () {
      loadComments(this.page);
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
          loadComments();
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
            loadComments(this.page);
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
          loadComments();
        })
        .catch(() => {
          Toasts.error.fire("Ha ocurrido un error al intentar fijar el comentario");
          loadComments();
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

    nextPage: function () {
      this.page++;
      loadComments(this.page);

      document.getElementById("comments").scrollIntoView();
    },

    prevPage: function () {
      this.page--;
      loadComments(this.page);

      document.getElementById("comments").scrollIntoView();
    },
  },
});

function loadComments(commentType, page) {
  const $commentsContainer = $getById("last-comments");
  easyFetch(`/comments/${commentType}/load?page=${page}`)
    .then((data) => data.json())
    .then((data) => {
      comments.comments = data;
      const $count = $commentsContainer.querySelector(".count");
      $count.innerHTML = data.length;
    })
    .catch(() => {
      Toasts.error.fire("Ha ocurrido un error tratando de cargar los comentarios");
    });
}
