/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2020 subtitulamos.tv
 */

import { sprintf } from "sprintf-js";
import { easyFetch } from "./utils";

function Subtitle(id, state, secondaryLang) {
  this.id = id;
  this.state = state;
  this.state.loaded = false;
  this.users = {};
  this.users[me.id] = {
    username: me.username,
    roles: me.roles,
  };
  this.secondaryLang = secondaryLang;
}

Subtitle.prototype.wsOpen = function () {
  if (this.state.loadedOnce) {
    Toasts.info.fire("Reconectado al servidor de traducción");
  }

  this.loadSequences();
  this.loadComments();
};

Subtitle.prototype.wsError = function () {
  this.state.loaded = false;
};

Subtitle.prototype.wsMessage = function (event) {
  try {
    let data = JSON.parse(event.data);
    switch (data.type) {
      case "seq-open":
        this.openSeq(data.num, data.user, data.openLockID);
        break;

      case "seq-close":
        this.closeSeq(data.num);
        break;

      case "seq-change":
        this.changeSeq(data.num, Number(data.nid), data.user, data.ntext, data.ntstart, data.ntend);
        break;

      case "seq-lock":
        this.lockSeq(data.id, data.status);
        break;

      case "seq-del":
        this.deleteSeq(data.id);
        break;

      case "com-new":
        this.addComment(data.id, this.getUserObject(data.user), data.time, data.text);
        if (data.user != me.id) {
          Toasts.info.fire(
            sprintf("<b>%s</b>&nbsp;ha publicado un comentario", this.getUsername(data.user))
          );
        }
        break;

      case "com-del":
        this.deleteComment(data.id);
        break;

      case "com-pin":
        this.setCommentPin(data.id, data.pinned);
        break;

      case "uinfo":
        this.users[data.id] = {
          username: data.username,
          roles: data.roles,
        };

        break;
    }
  } catch (e) {
    console.error(e);
  }
};

Subtitle.prototype.getUserObject = function (uid) {
  return {
    id: uid,
    username: this.getUsername(uid),
    roles: this.getRoles(uid),
  };
};

Subtitle.prototype.getRoles = function (uid) {
  return this.users[uid] ? this.users[uid].roles : [""];
};

Subtitle.prototype.getUsername = function (uid) {
  return this.users[uid] ? this.users[uid].username : "u#" + uid;
};

Subtitle.prototype.loadSequences = function () {
  function decode(s) {
    return s.replace(/[a-zA-Z]/g, function (c) {
      return String.fromCharCode((c <= "Z" ? 90 : 122) >= (c = c.charCodeAt(0) + 13) ? c : c - 26);
    });
  }

  easyFetch(`/subtitles/${subID}/translate/load`, {
    params: {
      secondaryLang: this.secondaryLang,
    },
  })
    .then((res) => res.json())
    .then((reply) => {
      const sequenceList = reply.sequences;
      const userList = reply.users;
      const isFirstLoad = !this.state.loadedOnce;

      this.state.loaded = true;
      this.state.loadedOnce = true;
      Object.keys(sequenceList).forEach((k) => {
        let seq = sequenceList[k];
        seq.text = decode(seq.text);
        seq.secondary_text = decode(seq.secondary_text);

        let idx = this.findSeqIdxByNum(seq.number);
        if (idx == -1) {
          this.state.sequences.push(seq);
        } else {
          let existingSeq = this.state.sequences[idx];
          if (seq.id != existingSeq.id) {
            existingSeq.id = seq.id;
            existingSeq.text = seq.text;
            existingSeq.author = seq.author;
            existingSeq.openInfo = seq.openInfo;
          }
        }
      });

      Object.keys(userList).forEach((uid) => {
        if (!this.users[uid]) {
          this.users[uid] = userList[uid];
        }
      });

      if (isFirstLoad) {
        this.state.curPage = 1;
      }

      if (window.location.hash) {
        this.state.jumpToUrlSequence();
      }
    })
    .catch(() => {
      Toasts.error.fire("Ha ocurrido un error al cargar el subtítulo");
    });
};

Subtitle.prototype.loadComments = function () {
  easyFetch("/subtitles/" + subID + "/translate/comments")
    .then((res) => res.json())
    .then((reply) => {
      this.state.comments = reply;
    })
    .catch(function () {
      Toasts.error.fire("Ha ocurrido un error tratando de cargar los comentarios");
    });
};

Subtitle.prototype.findSeqIdxByID = function (seqID) {
  let key = -1;
  Object.keys(this.state.sequences).forEach((k) => {
    if (this.state.sequences[k].id == seqID) {
      key = k;
      return;
    }
  });

  return key;
};

Subtitle.prototype.findSeqIdxByNum = function (seqNum) {
  let key = -1;
  Object.keys(this.state.sequences).forEach((k) => {
    if (this.state.sequences[k].number == seqNum) {
      key = k;
      return;
    }
  });

  return key;
};

Subtitle.prototype.openSeq = function (seqNum, by, openLockID) {
  let idx = this.findSeqIdxByNum(seqNum);
  if (idx < 0) {
    console.error("Could not open sequence " + seqNum + " (not found)");
    return;
  }

  let openInfo = {
    lockID: openLockID,
    by: by,
    since: new Date().toISOString(),
  };

  this.state.sequences[idx].openInfo = openInfo;
};

Subtitle.prototype.closeSeq = function (seqNum) {
  let idx = this.findSeqIdxByNum(seqNum);
  if (idx < 0) {
    console.log("Could not close sequence " + seqNum + " (not found)");
    return;
  }

  this.state.sequences[idx].openInfo = null;
};

Subtitle.prototype.lockSeq = function (seqID, status) {
  let idx = this.findSeqIdxByID(seqID);
  if (idx < 0) {
    console.log("Could not set lock status for sequence " + seqID + " (not found)");
    return;
  }

  this.state.sequences[idx].locked = status;
};

Subtitle.prototype.deleteSeq = function (seqID, status) {
  const removeFn = (s, hkey, replaceMain) => {
    if (hkey >= 0) {
      if (replaceMain && hkey == s.history.length - 1) {
        // We also have to replace the actual sequence
        let ns = s.history[hkey];
        s.id = ns.id;
        s.tstart = ns.tstart;
        s.tend = ns.tend;
        s.text = ns.text;
        s.author = ns.author;
      }

      s.history.splice(hkey, 1);
    } else {
      s.id = 0;
      s.author = 0;
      s.text = "";
    }
  };

  Object.keys(this.state.sequences).forEach((k) => {
    let s = this.state.sequences[k];
    if (s.id == seqID) {
      removeFn(s, s.history ? s.history.length - 1 : -1, true);
    } else if (s.history && s.history.length > 0) {
      s.history.forEach((hs, hkey) => {
        if (hs.id == seqID) {
          removeFn(s, hkey, false);
        }
      });
    }
  });
};

Subtitle.prototype.changeSeq = function (
  seqNum,
  newSeqID,
  newAuthorID,
  newText,
  newStartTime,
  newEndTime
) {
  let idx = this.findSeqIdxByNum(seqNum);
  if (idx < 0) {
    console.log("Could not update sequence text for " + seqNum + " (not found)");
    return;
  }

  let seq = this.state.sequences[idx];

  if (seq.id) {
    if (seq.id == newSeqID) {
      // We already did this change!
      console.log("Ignoring update, no change for " + seqNum);
      return;
    }

    // Since we're overwriting a sequence, save current to history
    if (!seq.history) {
      seq.history = [];
    }

    seq.history.push({
      id: seq.id,
      author: seq.author,
      text: seq.text,
      tstart: seq.tstart,
      tend: seq.tend,
    });
  }

  seq.id = newSeqID;
  seq.text = newText;
  seq.author = newAuthorID;
  seq.tstart = newStartTime;
  seq.tend = newEndTime;
  seq.openInfo = null;
};

Subtitle.prototype.getDataByNum = function (seqNum) {
  let idx = this.findSeqIdxByNum(seqNum);
  if (idx < 0) {
    console.error("Could not find sequence " + seqNum + " to retrieve its data");
    return;
  }

  return this.state.sequences[idx];
};

Subtitle.prototype.addComment = function (id, user, published_at, text) {
  let exists = this.state.comments.some((c) => {
    return c.id == id;
  });

  if (!exists) {
    this.state.comments.push({
      id: id,
      user: user,
      published_at: published_at,
      text: text,
      pinned: false,
    });

    this.sortComments();
  }
};

Subtitle.prototype.sortComments = function () {
  this.state.comments.sort((a, b) => {
    if (a.published_at < b.published_at) {
      if (a.pinned && !b.pinned) {
        // Pinned ones have preference
        return -1;
      }
      return 1;
    }

    if (a.published_at > b.published_at) {
      if (!a.pinned && b.pinned) {
        // Pinned ones have preference
        return 1;
      }
      return -1;
    }

    return 0;
  });
};

Subtitle.prototype.deleteComment = function (id) {
  let idx = this.state.comments.findIndex((c) => {
    return c.id == id;
  });

  if (idx != -1) {
    this.state.comments.splice(idx, 1);
  }
};

Subtitle.prototype.setCommentPin = function (id, pinned) {
  let idx = this.state.comments.findIndex((c) => {
    return c.id == id;
  });

  if (idx != -1) {
    this.state.comments[idx].pinned = pinned;
    this.sortComments();
  }
};

module.exports = Subtitle;
