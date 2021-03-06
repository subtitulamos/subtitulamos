/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2021 subtitulamos.tv
 */

import { sprintf } from "sprintf-js";
import { easyFetch } from "./utils";

function Subtitle(id, state, secondaryLang) {
  this.maxRenderKey = 0; // Key counter that provides as unique rendering keys for sequences
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

      case "seq-add-original":
        this.addSequenceToSubtitle(
          data.original_id,
          data.original_user,
          data.num,
          data.original_text,
          data.tstart,
          data.tend
        );
        break;

      case "seq-del-original":
        this.deleteSequenceFromSubtitle(data.num);
        break;

      case "seq-change-original":
        this.changeSeqOriginal(
          data.original_id,
          data.num,
          data.original_text,
          data.original_tstart,
          data.original_tend,
          data.prev_tstart,
          data.prev_tend
        );
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

      case "com-edit":
        this.editComment(data.id, data.time, data.text);
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
        seq.render_key = this.maxRenderKey++;
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
    .catch((err) => {
      console.error(err);
      Toasts.error.fire("Ha ocurrido un error al cargar el subtítulo");
    });
};

Subtitle.prototype.loadComments = function () {
  easyFetch("/subtitles/" + subID + "/translate/comments")
    .then((res) => res.json())
    .then((reply) => {
      this.state.comments = reply;

      for (const comment of reply) {
        if (!this.users[comment.user.id]) {
          this.users[comment.user.id] = {
            username: comment.user.username,
            roles: comment.user.roles,
          };
        }
      }
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
    console.error("Could not close sequence " + seqNum + " (not found)");
    return;
  }

  this.state.sequences[idx].openInfo = null;
};

Subtitle.prototype.lockSeq = function (seqID, status) {
  let idx = this.findSeqIdxByID(seqID);
  if (idx < 0) {
    console.error("Could not set lock status for sequence " + seqID + " (not found)");
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

Subtitle.prototype.changeSeqOriginal = function (
  originalId,
  number,
  originalText,
  originalStartTime,
  originalEndTime,
  prevStartTime,
  prevEndTime
) {
  const seqHereIdx = this.findSeqIdxByNum(number);
  if (seqHereIdx < 0) {
    console.error("Could not match original sequence change to sequence here");
    return;
  }

  const seqHere = this.state.sequences[seqHereIdx];
  seqHere.secondary_text = originalText;

  // Time changes are only "really" propagated if tstart/tend in origin matches this translation's tstart/tend
  // (any modifications to times made exclusively for this translation aren't propagated)
  if (!seqHere.id || (seqHere.tstart == prevStartTime && seqHere.tend == prevEndTime)) {
    seqHere.tstart = originalStartTime;
    seqHere.tend = originalEndTime;
  }
};

Subtitle.prototype.addSequenceToSubtitle = function (
  originalId,
  originalUser,
  number,
  originalText,
  tStart,
  tEnd
) {
  const maxSeqNum = this.state.sequences.length;
  const newSeq = {
    render_key: this.maxRenderKey++,
    id: isOriginalSub ? originalId : null,
    number: number,
    author: isOriginalSub ? originalUser : null,
    openInfo: null,
    tstart: tStart,
    tend: tEnd,
    locked: false,
    verified: false,
    secondary_text: originalText,
    text: isOriginalSub ? originalText : "",
  };

  // Migrate all temporary work to the new numbers that we'll assign sequences
  for (let i = this.state.sequences.length - 1; i >= 0; --i) {
    const seq = this.state.sequences[i];
    if (seq.number < newSeq.number) {
      break; // We don't need to change these
    }
  }

  if (number > maxSeqNum) {
    this.state.sequences.push(newSeq);
  } else {
    for (let i = this.state.sequences.length - 1; i >= 0; --i) {
      const seq = this.state.sequences[i];
      if (seq.number < number) {
        break; // We don't need to change these
      }

      const workInSeq = Subtitle.getSavedWorkInSequence(seq.number, seq.id);
      if (workInSeq) {
        Subtitle.saveWorkInSequence(seq.number + 1, seq.id, workInSeq);
      }

      Subtitle.deleteWorkInSequence(seq.number, seq.id);
      ++seq.number;
    }

    this.state.sequences.splice(number - 1, 0, newSeq);
  }
};

Subtitle.prototype.deleteSequenceFromSubtitle = function (number) {
  // Delete the one sequence
  this.state.sequences = this.state.sequences.filter((seq) => {
    return seq.number !== number;
  });

  // Update working status
  for (let i = number; i < this.state.sequences.length; ++i) {
    const seq = this.state.sequences[i];
    const workInSeq = Subtitle.getSavedWorkInSequence(seq.number, seq.id);
    if (workInSeq) {
      Subtitle.saveWorkInSequence(seq.number - 1, seq.id, workInSeq);
    }

    Subtitle.deleteWorkInSequence(seq.number, seq.id);
    --seq.number;
  }
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
    console.error("Could not update sequence text for " + seqNum + " (not found)");
    return;
  }

  let seq = this.state.sequences[idx];

  if (seq.id) {
    if (seq.id == newSeqID) {
      // We already did this change!
      console.info("Ignoring update, no change for " + seqNum);
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

Subtitle.prototype.editComment = function (id, time, text) {
  let idx = this.state.comments.findIndex((c) => {
    return c.id == id;
  });

  if (idx != -1) {
    const c = this.state.comments[idx];
    c.text = text;
    c.edited_at = time;
  }
};

Subtitle.saveWorkInSequence = function (seqNum, seqId, text) {
  sessionStorage.setItem("sub-" + subID + "-seqtext-" + seqNum + "-" + seqId, text);
};

Subtitle.deleteWorkInSequence = function (seqNum, seqId) {
  sessionStorage.removeItem("sub-" + subID + "-seqtext-" + seqNum + "-" + seqId);
};

Subtitle.getSavedWorkInSequence = function (seqNum, seqId) {
  return sessionStorage.getItem("sub-" + subID + "-seqtext-" + seqNum + "-" + seqId);
};

module.exports = Subtitle;
