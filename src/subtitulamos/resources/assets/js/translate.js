/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2020 subtitulamos.tv
 */

import Vue from "vue";
import $ from "jquery";
import "./vue/comment.js";
import Subtitle from "./subtitle.js";
import ReconnectingWebsocket from "reconnecting-websocket";
import dateformat from "dateformat";
import accentFold from "./accent_fold.js";
import balanceText from "./translate/balance_text.js";
import "../css/translate.scss";

function removeWindowHash() {
  // Remove the hash (merely setting .hash to empty leaves the hash AND moves the scroll)
  history.replaceState("", document.title, window.location.pathname + window.location.search);
}

let bus = new Vue();
let modifiedSeqList = [];

window.onbeforeunload = function (e) {
  let ask = false;
  for (var i = 0; i < sessionStorage.length; i++) {
    if (sessionStorage.key(i).match("sub-" + subID + "-seqtext")) {
      ask = true;
    }
  }

  if (ask) {
    let msg = "Parece que tienes secuencias abiertas. ¿Estás seguro de querer salir?";
    (e || window.event).returnValue = msg;
    return msg;
  }
};

// Components and such
Vue.component("seqlock", {
  template: `
  <li>
    <a href='javascript:void(0)' @click='$emit("jump", seqnum)'>#{{ seqnum }}</a>
    por <a :href="'/users/'+uid">{{ username }}</a>
    [ {{ niceTime }} ] <i class='fa fa-times' aria-hidden='true' @click='release'></i>
  </li>
    `,
  props: ["id", "seqnum", "uid", "time"],
  methods: {
    release: function () {
      let seq = sub.getDataByNum(this.seqnum);
      if (!seq) {
        console.error("Could not find sequence to release!", this.seqnum);
        return;
      }

      let seqInfoCopy = JSON.parse(JSON.stringify(seq.openInfo));
      sub.closeSeq(this.seqnum);

      $.ajax({
        url: "/subtitles/" + subID + "/translate/open-lock/" + this.id,
        method: "DELETE",
        data: {},
      }).fail(() => {
        sub.openSeq(this.seqnum, seqInfoCopy.by, seqInfoCopy.lockID);
      });
    },
  },
  computed: {
    username: function () {
      return sub.getUsername(this.uid);
    },

    niceTime: function () {
      let d = new Date(this.time);
      return dateformat(d, "d/mmm HH:MM");
    },
  },
});

Vue.component("sequence", {
  template: `
        <tr
          :id="!history ? 'seqn-'+number : null"
          :class="{
            'highlighted': !history && highlighted,
            'locked':  locked,
            'verified': verified,
            'current': !history,
            'history': history
          }">
            <td><span v-if="!history"><span class='seq-num-clickable' @click="seqNumClick">{{ number }}</span></span></td>
            <td class="user"><a :href="'/users/' + author" tabindex="-1">{{ authorName }}</a></td>
            <td class="time" @click="openSequence">
                <div v-if="!editing || !canEditTimes">
                    {{ tstart | timeFmt }} <i class="fa fa-long-arrow-right"></i> {{ tend | timeFmt }}
                </div>

                <div v-if="editing && canEditTimes">
                    <input type='text' v-model='editingTimeStart' :tabindex="this.parsedStartTime != this.tstart ? '0' : '-1'" :class="{'edited': this.parsedStartTime != this.tstart}">
                    <i class="fa fa-long-arrow-right"></i>
                    <input type='text' v-model='editingTimeEnd' :tabindex="this.parsedEndTime != this.tend ? '0' : '-1'" :class="{'edited': this.parsedEndTime != this.tend}">
                </div>
            </td>
            <td class="text"><pre>{{ secondaryText }}</pre></td>
            <td class="text" @click="openSequence" :class="{'translatable': !history && !openByOther, 'hint--left hint--bounce hint--rounded': openByOther}" :data-hint="textHint">
                <pre v-if="!editing && id">{{ text }}</pre>
                <pre v-if="!editing && !id" class="untranslated">- Sin traducir -</pre>

                <i class="fa fa-pencil-square-o open-other" aria-hidden="true" v-if='openByOther'></i>

                <textarea v-model="editingText" v-if="editing" @keyup.ctrl="keyboardActions" autocomplete="new-password"></textarea>
                <div class='fix-sequence' :class="{'warning': shouldFixLevel > 1, 'suggestion': shouldFixLevel == 1}" v-if="editing && shouldFixLevel > 0" @click="fix">
                    <i class="fa fa-wrench" aria-hidden="true"></i>
                </div>
                <div class="line-status" v-if="editing">
                    <span class="line-counter" :class="lineCounters[0] > 40 ? 'counter-error' : (lineCounters[0] > 35 ? 'counter-warning' : '')">{{ lineCounters[0] }}</span>
                    <span class="line-counter" v-if="lineCounters[1]" :class="lineCounters[1] > 40 ? 'counter-error' : (lineCounters[1] > 35 ? 'counter-warning' : '')">{{ lineCounters[1] }}</span>
                </div>
            </td>
            <td class="actions">
                <template v-if="!saving">
                    <template v-if="!history && editing">
                        <i class="fa fa-floppy-o" :class="{'disabled': !canSave}" @click="save" tabindex="0" @keyup.enter="save"></i>
                        <i class="fa fa-times-circle-o" @click="discard" tabindex="0" @keyup.enter="discard"></i>
                    </template>

                    <template v-if="translated && !history && !editing">
                        <!--<i class="fa" @click="toggleVerify" :class="verified ? 'fa-check-circle' : 'fa-question-circle-o'" v-if="!locked"></i>-->
                        <i class="fa" @click="toggleLock(!locked)" :class="locked ? 'fa-lock' : 'fa-unlock-alt'" v-if="canLock || locked"></i>
                    </template>

                    <template v-if="false && history">
                        <i class="fa fa-undo" aria-hidden="true"></i>
                    </template>
                </template>

                <template v-if="saving">
                    <i class="fa fa-spinner fa-pulse fa-3x fa-fw"></i>
                </template>
            </td>
        </tr>
        `,

  props: {
    id: Number,
    locked: Boolean,
    verified: Boolean,
    highlighted: {
      type: Boolean,
      default: false,
    },
    number: Number,
    author: Number,
    tstart: Number,
    tend: Number,
    secondaryText: String,
    text: String,
    history: Boolean,
    openInfo: Object,
  },

  data: function () {
    return {
      editingTime: false,
      editingText: this.text,
      editingTimeEnd: this.$options.filters.timeFmt(this.tend),
      editingTimeStart: this.$options.filters.timeFmt(this.tstart),
      saving: false,
      window: window,
    };
  },

  mounted: function () {
    let savedText = sessionStorage.getItem(
      "sub-" + subID + "-seqtext-" + this.number + "-" + this.id
    );
    if (savedText) {
      this.editingText = savedText;
    }
  },

  beforeDestroy: function () {
    bus.$off("open-" + this.number);
    bus.$off("close-" + this.number);
    bus.$off("save-" + this.number);
    bus.$off("fix-" + this.number);
    bus.$off("lock-" + this.number);
  },

  created: function () {
    if (!this.history) {
      bus.$on("open-" + this.number, this.openSequence);
      bus.$on("close-" + this.number, this.discard);
      bus.$on("save-" + this.number, this.save);
      bus.$on("fix-" + this.number, this.fix);
      bus.$on("lock-" + this.number, this.toggleLock);
    }
  },

  filters: {
    timeFmt: function (ms) {
      if (!ms) return "00:00:00.000";

      let h = 0,
        m = 0,
        s = 0;
      s = Math.floor(ms / 1000);

      h = Math.floor(s / 3600);
      s -= h * 3600;
      m = Math.floor(s / 60);
      s -= m * 60;

      ms -= (s + m * 60 + h * 3600) * 1000;

      let stime = "";
      if (h < 10) stime += "0";
      stime += h + ":";
      if (m < 10) stime += "0";
      stime += m + ":";
      if (s < 10) stime += "0";
      stime += s + ".";
      if (ms < 100) stime += "0";
      if (ms < 10) stime += "0";
      stime += ms;

      return stime;
    },
  },

  watch: {
    editingText: function (nText) {
      if (nText != this.text) {
        if (!modifiedSeqList.includes(this.number)) {
          modifiedSeqList.push(this.number);
        }
      } else {
        // Try to remove it form the modified seq list if it's there
        let modIdx = modifiedSeqList.indexOf(this.number);
        if (modIdx !== -1) {
          modifiedSeqList.splice(modIdx, 1);
        }
      }

      sessionStorage.setItem("sub-" + subID + "-seqtext-" + this.number + "-" + this.id, nText);
    },
  },

  computed: {
    canEditTimes: function () {
      return canEditTimes;
    },

    openByOther: function () {
      return this.openInfo && this.openInfo.by && this.openInfo.by != me.id;
    },

    textHint: function () {
      return this.openByOther
        ? sub.getUsername(this.openInfo.by) + " está editando esta secuencia"
        : "";
    },

    editing: function () {
      return this.openInfo && this.openInfo.by == me.id;
    },

    edited: function () {
      return this.editing && this.originalText != this.editingText;
    },

    parsedStartTime: function () {
      return this.parseTime(this.editingTimeStart);
    },

    parsedEndTime: function () {
      return this.parseTime(this.editingTimeEnd);
    },

    lineCounters: function () {
      let lines = this.editingText.split("\n");
      let lineCounters = [];

      for (let i = 0; i < lines.length; ++i) {
        let text = lines[i].replace(/ +/g, " ");
        if (text.trim().length > 0) {
          lineCounters[i] = text.trim().length;
        } else {
          lineCounters[i] = text.length;
        }
      }

      return lineCounters;
    },

    canSave: function () {
      return (
        !this.history &&
        this.lineCounters.length > 0 &&
        this.lineCounters.length <= 2 &&
        this.lineCounters[0] > 0 &&
        this.lineCounters[0] <= 40 &&
        (!this.lineCounters[1] || this.lineCounters[1] <= 40) &&
        (this.parsedStartTime && this.parsedEndTime && this.parsedStartTime < this.parsedEndTime)
      );
    },

    canLock: function () {
      return canLock && !this.history && this.id != 0;
    },

    translated: function () {
      return this.id > 0;
    },

    authorName: function () {
      return this.author ? sub.getUsername(this.author) : " - ";
    },

    shouldFixLevel: function () {
      let tlines = [];
      $.each(this.editingText.split("\n"), function (i, val) {
        tlines.push(val.trim());
      });

      let full = tlines.join("\n");
      let dialogLineCount = (full.match(/(?:^|\s)-/g) || []).length;
      if (full.length > 40 || (dialogLineCount == 2 && full.match(/^\s*-/g))) {
        let unopinionatedMatch = balanceText(this.editingText, false).join("\n") == full;
        let opinionatedMatch = balanceText(this.editingText, true).join("\n") == full;

        return !unopinionatedMatch && !opinionatedMatch ? 2 : 0;
      } else if (
        tlines.length > 1 &&
        tlines[0].length >= 0 &&
        tlines[1].length > 0 &&
        dialogLineCount != 2
      ) {
        return 1;
      }

      return 0;
    },
  },

  methods: {
    openSequence: function () {
      if (
        this.editing ||
        this.history ||
        this.openByOther ||
        this.saving ||
        (this.locked && !canReleaseOpenLock)
      ) {
        return true; // Already / no effect / can't open
      }

      this.editingText = this.text;
      this.editingTimeStart = this.$options.filters.timeFmt(this.tstart);
      this.editingTimeEnd = this.$options.filters.timeFmt(this.tend);

      sub.openSeq(this.number, me.id, 0);
      $.ajax({
        url: "/subtitles/" + subID + "/translate/open",
        method: "POST",
        data: {
          seqNum: this.number,
        },
      })
        .done(reply => {
          if (!reply.ok) {
            sub.closeSeq(this.number);
            Toasts.error.fire(reply.msg);
          }
        })
        .fail(() => {
          sub.closeSeq(this.number);
          Toasts.error.fire("Ha ocurrido un error desconocido al intentar editar");
        });
    },

    keyboardActions: function (e) {
      if (e.altKey && e.key == "f") {
        this.fix();
      } else if (e.key == "s") {
        this.save();
      }

      e.preventDefault();
    },

    save: function () {
      if (!this.canSave || this.saving) {
        return false;
      }

      this.saving = true;

      // Process text for spaces and proceed to save/create/cancel
      let ntext = this.editingText.trim().replace(/ +/g, " ");
      if (!ntext) {
        ntext = " ";
      }

      // Clean out some characters that may end up here by accident but we know how to substitute
      // NOTE: If this is modified, please modify the server's list as well
      let replacements = [
        [/…/g, "..."],
        [/“/g, '"'],
        [/”/g, '"'],
        [/[\u200B-\u200D]/g, ""], //(0-width space: https://stackoverflow.com/a/11305926/2205532)
      ];

      for (let pair of replacements) {
        ntext = ntext.replace(pair[0], pair[1]);
      }

      // Make sure there are no characters outside ISO-8859-1
      if (/[^\u0000-\u00ff]/g.test(ntext) === true) {
        Toasts.error.fire(
          "En las secuencias solo se permiten caracteres que pertenezcan a la codificación ISO-8859-1"
        );
        this.saving = false;
        return;
      }

      // Detect if anything changed at all
      let modifiedText = ntext != this.text;
      let modifiedTime = this.parsedEndTime != this.tend || this.parsedStartTime != this.tstart;
      if (!modifiedText && !modifiedTime) {
        this.discard();
        return;
      }

      // Build payload to send
      let action = this.id ? "save" : "create";
      let postData = {
        seqID: this.id,
        number: this.number,
        text: ntext,
      };

      let nStartTime = this.parsedStartTime;
      let nEndTime = this.parsedEndTime;

      if (modifiedTime) {
        postData.tstart = nStartTime;
        postData.tend = nEndTime;
      }

      // Save ID to delete the text key afterwards
      let previousId = this.id;
      $.ajax({
        url: "/subtitles/" + subID + "/translate/" + action,
        method: "POST",
        data: postData,
      })
        .done(newID => {
          // Update sequence
          // (though in normal behaviour we will have received this update via websocket faster)
          sub.changeSeq(this.number, Number(newID), me.id, ntext, nStartTime, nEndTime);
          this.saving = false;

          // Delete storage key
          sessionStorage.removeItem("sub-" + subID + "-seqtext-" + this.number + "-" + previousId);

          // Try to remove it form the modified seq list if it's there
          let modIdx = modifiedSeqList.indexOf(this.number);
          if (modIdx !== -1) {
            modifiedSeqList.splice(modIdx, 1);
          }
        })
        .fail((_, status) => {
          Toasts.error.fire(
            "Ha ocurrido un error al intentar guardar la secuencia: (" + status + ")"
          );
          this.saving = false;
        });
    },

    discard: function () {
      if (!this.openInfo) {
        return;
      }

      this.saving = true;

      // Preserve lock id in case we need to undo this sequence close
      let oLockID = this.openInfo.id;
      sub.closeSeq(this.number);

      $.ajax({
        url: "/subtitles/" + subID + "/translate/close",
        method: "POST",
        data: {
          seqNum: this.number,
        },
      })
        .done(() => {
          this.saving = false;

          // Discard text cache if saved
          sessionStorage.removeItem("sub-" + subID + "-seqtext-" + this.number + "-" + this.id);

          // Try to remove it form the modified seq list if it's there
          let modIdx = modifiedSeqList.indexOf(this.number);
          if (modIdx !== -1) {
            modifiedSeqList.splice(modIdx, 1);
          }
        })
        .fail((_, status) => {
          sub.openSeq(this.number, me.id, oLockID);
          Toasts.error.fire(
            "Ha ocurrido un error al intentar cerrar la secuencia  (" + status + ")"
          );
        });
    },

    toggleLock: function (newState) {
      if (!canLock) {
        return false;
      }

      if (this.locked == newState) {
        return false; // Nothing to do
      }

      sub.lockSeq(this.id, newState);
      $.ajax({
        url: "/subtitles/" + subID + "/translate/lock",
        method: "POST",
        data: {
          seqID: this.id,
        },
      }).fail(() => {
        // Revert, the request failed
        sub.lockSeq(this.id, !newState);
        Toasts.error.fire("Error al intentar cambiar el estado de bloqueo de #" + this.number);
      });
    },

    fix: function () {
      if (this.shouldFixLevel <= 0) {
        return false;
      }

      let ntext = balanceText(this.editingText, true).join("\n");
      if (ntext != this.editingText) {
        this.editingText = ntext;
      } else {
        let tlines = [];
        $.each(this.editingText.split("\n"), function (i, val) {
          tlines.push(val.trim());
        });

        let dialogLineCount = (ntext.match(/(?:^|\s)-/g) || []).length;
        if (
          tlines.length > 1 &&
          tlines[0].length >= 0 &&
          tlines[1].length > 0 &&
          dialogLineCount != 2
        ) {
          this.editingText = tlines.join(" ");
        }
      }
    },

    parseTime: function (t) {
      let matches = /^(?:(\d{1,2}):)?(\d{1,2}):(\d{1,2})[\.,](\d{1,3})$/.exec(t);
      if (!matches || matches.length < 4) {
        return null;
      }

      let hs = matches[1] ? Number(matches[1]) * 3600 : 0;
      return (hs + Number(matches[2]) * 60 + Number(matches[3])) * 1000 + Number(matches[4]);
    },

    seqNumClick: function () {
      if (window.location.hash.includes(this.number)) {
        removeWindowHash();
        this.$emit("highlight-off");
      } else {
        window.location.hash = "#" + this.number;
        this.$emit("highlight-on");
      }
    },
  },
});

/**************************
 *        PAGINATION
 ***************************/
Vue.component("pagelist", {
  template: `
        <ul class="pagination">
            <li class="change-page" @click="prevPage" :class="{ disabled: curPage == 1 }"><i class="fa fa-chevron-left" aria-hidden="true"></i></li>
            <li v-for="page in pages" class="target-page" :class="page == curPage ? 'active' : ''" @click="toPage(page)">{{ page }}</li>
            <li class="change-page" @click="nextPage" :class="{ disabled: curPage == lastPage }"><i class="fa fa-chevron-right" aria-hidden="true"></i></a></li>
        </ul>
    `,
  props: ["curPage", "pages", "lastPage"],
  methods: {
    nextPage: function () {
      if (this.curPage < this.lastPage) this.toPage(this.curPage + 1);
    },

    prevPage: function () {
      if (this.curPage > 1) this.toPage(this.curPage - 1);
    },

    toPage: function (page) {
      document.getElementById("translation").scrollIntoView();
      this.$emit("change-page", page);
    },
  },
});

/**
 * Boot
 */
function getSeqNumFromHash() {
  let seqNum = window.location.hash.substr(1);
  return Number(seqNum);
}

function isElementInViewport(el) {
  var rect = el.getBoundingClientRect();

  return (
    rect.top >= 0 &&
    rect.left >= 0 &&
    rect.bottom <=
    (window.innerHeight || document.documentElement.clientHeight) /*or $(window).height() */ &&
    rect.right <=
    (window.innerWidth || document.documentElement.clientWidth) /*or $(window).width() */
  );
}

const SEQS_PER_PAGE = 20;
window.translation = new Vue({
  el: "#translation",
  data() {
    const defaultFilterValues = {
      onlyUntranslated: false,
      author: 0,
      text: "",
      preciseTextMatching: false
    };

    let data = {
      sequences: [],
      curPage: 1,
      highlightedSequence: 0,
      filters: {},
      defaultFilterValues: defaultFilterValues,
      comments: [],
      loaded: false,
      loadedOnce: false,
      newComment: "",
      maxCommentLength: 600,
      submittingComment: false,
      canReleaseOpenLock: canReleaseOpenLock,
      hasAdvancedTools: hasAdvancedTools,
    };

    for (let filter of Object.keys(defaultFilterValues)) {
      data.filters[filter] = defaultFilterValues[filter];
    }

    return data;
  },
  computed: {
    lastPage: function () {
      return Math.ceil(this.visibleSequences.length / SEQS_PER_PAGE);
    },

    pages: function () {
      let pages = [];
      for (let i = 1; i <= this.lastPage; ++i) {
        pages.push(i);
      }

      return pages;
    },

    visibleSequences: function () {
      return this.sequences.filter(seq => {
        if (this.filters.onlyUntranslated && seq.id) {
          return false;
        }

        const author_filter = Number(this.filters.author);
        if (author_filter != 0) {
          let authorFilterFn = seq => {
            return seq.author && author_filter == seq.author;
          };

          if (!authorFilterFn(seq) && (!seq.history || !seq.history.some(authorFilterFn))) {
            return false;
          }
        }

        if (this.filters.text != "") {
          let textFilterFn = seq => {
            let textToMatch = this.filters.text;

            if (this.filters.preciseTextMatching) {
              // Precise text matching means we need to find the exact string,
              // accents and all, but always we strip the linebreaks and turn
              // them into spaces so they're searchable.
              return (
                seq.text.includes(textToMatch) ||
                (seq.secondary_text &&
                  seq.secondary_text.replace(/\r?\n|\r/g, " ").includes(textToMatch))
              );
            }

            // We're not being precise about diacritics, we're doing simple matching
            // Case doesn't matter, line breaks are of course replaced as in strict search.
            textToMatch = textToMatch.toLocaleLowerCase().replace(/\r?\n|\r/g, " ");
            textToMatch = accentFold(textToMatch);
            return (
              accentFold(seq.text.toLocaleLowerCase()).includes(textToMatch) ||
              (seq.secondary_text &&
                accentFold(
                  seq.secondary_text.replace(/\r?\n|\r/g, " ").toLocaleLowerCase()
                ).includes(textToMatch))
            );
          };

          if (!textFilterFn(seq) && (!seq.history || !seq.history.some(textFilterFn))) {
            return false;
          }
        }

        return true;
      });
    },

    pageSequences: function () {
      return this.visibleSequences.filter((ele, idx) => {
        return Math.floor(idx / SEQS_PER_PAGE) == this.curPage - 1;
      });
    },

    openLocks: function () {
      let locks = [];
      this.sequences.forEach(seq => {
        if (seq.openInfo && seq.openInfo.lockID) {
          locks.push({
            id: seq.openInfo.lockID,
            uid: seq.openInfo.by,
            time: seq.openInfo.since,
            seq_number: seq.number,
          });
        }
      });

      return locks;
    },

    authors: function () {
      let authors = {};
      this.sequences.forEach(seq => {
        if (seq.author && !authors[seq.author]) {
          authors[seq.author] = sub.getUsername(seq.author);
        }

        if (seq.history) {
          seq.history.forEach(hseq => {
            if (!authors[hseq.author]) {
              authors[hseq.author] = sub.getUsername(hseq.author);
            }
          });
        }
      });

      return authors;
    },
  },
  methods: {
    resetFilters() {
      let changed = false;
      for (let filter of Object.keys(this.defaultFilterValues)) {
        if (this.filters[filter] != this.defaultFilterValues[filter]) {
          this.filters[filter] = this.defaultFilterValues[filter];
          changed = true;
        }
      }

      return changed;
    },

    onChangePage: function (page) {
      this.curPage = page;
      this.highlightedSequence = 0; // Clear out the highlight
      removeWindowHash();
    },

    togglePreciseTextMatching: function () {
      this.onChangePage(1);
      this.filters.preciseTextMatching = !this.filters.preciseTextMatching;
    },

    highlight(seqNum) {
      this.jumpToSequence(seqNum);
    },

    publishComment() {
      if (this.submittingComment) {
        return;
      }

      if (this.newComment.length > this.maxCommentLength) {
        Toasts.error.fire(
          "Por favor, escribe un comentario más corto (de hasta " +
          this.maxCommentLength +
          " caracteres)"
        );
        return false;
      }

      this.submittingComment = true;
      let commentSent = this.newComment;
      $.ajax({
        url: "/subtitles/" + subID + "/translate/comments",
        method: "POST",
        data: {
          text: commentSent,
        },
      })
        .done(id => {
          this.newComment = "";
          this.submittingComment = false;
          sub.addComment(id, sub.getUserObject(me.id), new Date().toISOString(), commentSent);
        })
        .fail(jqXHR => {
          this.submittingComment = false;
          if (jqXHR.responseText) {
            Toasts.error.fire(jqXHR.responseText);
          } else {
            Toasts.error.fire("Ha ocurrido un error al enviar tu comentario");
          }
        });
    },

    remove: function (id) {
      let c, cidx;
      for (let i = 0; i < this.comments.length; ++i) {
        if (this.comments[i].id == id) {
          // Save comment and remove it from the list
          c = this.comments[i];
          cidx = i;
          this.comments.splice(cidx, 1);
          break;
        }
      }

      $.ajax({
        url: "/subtitles/" + subID + "/translate/comments/" + id,
        method: "DELETE",
      }).fail(
        function () {
          Toasts.error.fire("Ha ocurrido un error al borrar el comentario");
          if (typeof cidx !== "undefined") {
            // Insert the comment right back where it was
            this.comments.splice(cidx, 0, c);
          }
        }.bind(this)
      );
    },

    pin: function (id) {
      $.ajax({
        url: "/subtitles/" + subID + "/translate/comments/" + id + "/pin",
        method: "POST",
      }).fail(function () {
        Toasts.error.fire("Ha ocurrido un error al fijar el comentario");
      });
    },

    openPage: function () {
      this.pageSequences.forEach(function (s) {
        if (!s.openInfo) {
          bus.$emit("open-" + s.number);
        }
      });
    },

    openUntranslatedPage: function () {
      this.pageSequences.forEach(function (s) {
        if (!s.id) {
          bus.$emit("open-" + s.number);
        }
      });
    },

    closePage: function (ev, skipModifiedCheck) {
      if (!skipModifiedCheck) {
        let modTextList = [];
        this.pageSequences.forEach(function (s) {
          if (modifiedSeqList.includes(s.number)) {
            modTextList.push("#" + s.number);
          }
        });

        if (modTextList.length > 0) {
          let pthis = this;
          Swal.fire({
            type: "warning",
            confirmButtonText: "Descartar",
            cancelButtonText: "No",
            showCancelButton: true,
            title: "¿Descartar cambios?",
            text:
              "Algunas secuencias de esta página han sido modificadas, pero no guardadas: " +
              modTextList.join(", ") +
              ". ¿Estás seguro de querer descartar los cambios en estas?",
          }).then(result => {
            if (result.value) {
              pthis.closePage(ev, true);
            }
          });
          return;
        }
      }

      this.pageSequences.forEach(function (s) {
        if (s.openInfo && s.openInfo.by == me.id) {
          bus.$emit("close-" + s.number);
        }
      });
    },

    savePage: function () {
      this.pageSequences.forEach(function (s) {
        if (s.openInfo && s.openInfo.by == me.id) {
          bus.$emit("save-" + s.number);
        }
      });
    },

    lockPage: function (state) {
      this.pageSequences.forEach(function (s) {
        if (!s.openInfo && s.id) {
          bus.$emit("lock-" + s.number, state);
        }
      });
    },

    fixPage: function () {
      this.pageSequences.forEach(function (s) {
        if (s.openInfo && s.openInfo.by == me.id) {
          bus.$emit("fix-" + s.number);
        }
      });
    },

    alertMod: function () {
      Swal.fire({
        confirmButtonText: "Enviar",
        cancelButtonText: "Cancelar",
        showCancelButton: true,
        input: "textarea",
        title: "Avisar a un moderador",
        text: "Añade un comentario explicando la situación",
        showLoaderOnConfirm: true,
        preConfirm: msg => {
          return $.ajax({
            url: "/subtitles/" + subID + "/alert",
            method: "POST",
            data: {
              message: msg,
            },
          })
            .done(reply => {
              if (!reply.ok) {
                Swal.showValidationMessage(reply.msg);
              }
            })
            .fail((_, status) => {
              Swal.showValidationMessage(
                "Ha ocurrido un error al intentar enviar la alerta: (" + status + ")"
              );
            });
        },
      }).then(result => {
        if (result.value && result.value.ok) {
          Toasts.success.fire("Aviso enviado correctamente.");
        }
      });
    },

    goTo() {
      Swal.fire({
        confirmButtonText: "Ir",
        cancelButtonText: "Cancelar",
        showCancelButton: true,
        input: "text",
        text: "Escribe el número de secuencia al que navegar",
        inputValidator: val => {
          if (Number(val) <= 0) {
            return "Por favor, introduce un número positivo";
          }
        },
      }).then(result => {
        if (result.value) {
          this.jumpToSequence(Number(result.value));
        }
      });
    },

    jumpToSequence(seqn) {
      const filtersReset = this.resetFilters();

      let totalPages = Math.ceil(this.sequences.length / SEQS_PER_PAGE);
      let targetPage = Math.ceil(seqn / SEQS_PER_PAGE);

      if (seqn <= 0 || targetPage > totalPages) {
        return;
      }

      this.curPage = targetPage;
      this.highlightedSequence = seqn;
      window.location.hash = "#" + seqn;

      let seqEle = $("#sequences").children("#seqn-" + seqn)[0];
      if (!seqEle || !isElementInViewport(seqEle) || filtersReset) {
        // Delay this a little bit so Vue can run the rerender
        setTimeout(() => {
          let isFirstSequence =
            Math.ceil(seqn / SEQS_PER_PAGE) != Math.ceil((seqn - 1) / SEQS_PER_PAGE);
          if (isFirstSequence) {
            $("#sequences")[0].scrollIntoView({ behavior: "instant" });
          } else {
            $("#sequences")
              .children("#seqn-" + seqn)[0]
              .scrollIntoView({ behavior: "instant" });
          }

          let scrolledY = window.scrollY;
          window.scroll(0, scrolledY - $("#translation-tools").height() * 2.5);
        }, 10);
      }
    },

    jumpToUrlSequence() {
      let seqNum = getSeqNumFromHash();
      if (seqNum) {
        this.jumpToSequence(Number(seqNum));
      }
    },
  },
});

let sub = new Subtitle(subID, translation, availSecondaryLangs[0]);

// Set up websocket (which will itself load the sub)
const wsProtocol = window.location.protocol == "https:" ? "wss" : "ws";
const route = window.location.port ? window.location.hostname + ":" + window.location.port : window.location.hostname;
const ws = new ReconnectingWebsocket(
  wsProtocol +
  "://" +
  route +
  "/translation-rt?subID=" +
  subID +
  "&token=" +
  wsAuthToken
);

let pingInterval = null;
ws.addEventListener("open", () => {
  sub.wsOpen();

  if (pingInterval) {
    clearInterval(pingInterval);
  }

  pingInterval = setInterval(() => {
    ws.send("ping");
  }, 47500);
});

ws.addEventListener("message", e => {
  if (e.data === "pong") {
    return;
  }

  sub.wsMessage(e);
});

ws.addEventListener("error", e => {
  sub.wsError(e);
});
ws.addEventListener("close", e => {
  sub.wsError(e);

  if (pingInterval) {
    clearInterval(pingInterval);
  }
});

// Absorb and block default Ctrl+S / Ctrl+G behaviour
$(document).on("keydown", function (e) {
  if (e.ctrlKey && e.which == 83) {
    if (e.shiftKey) {
      translation.savePage();
    }

    e.preventDefault();
  }

  if (e.ctrlKey && !e.altKey && e.which == 71) {
    translation.goTo();
    e.preventDefault();
  }
});
