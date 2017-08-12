import Vue from 'vue';
import $ from 'jquery';
import {sprintf, vsprintf} from 'sprintf-js';

function Subtitle(id, state, secondaryLang) {
    this.id = id;
    this.state = state;
    this.loaded = false;
    this.users = {};
    this.users[me.id] = {
        username: me.username,
        roles: me.roles
    };
    this.secondaryLang = secondaryLang;
};

Subtitle.prototype.wsOpen = function() {
    this.loadSequences();
    this.loadComments();
};

Subtitle.prototype.wsError = function() {
    this.loaded = false;
};

Subtitle.prototype.wsMessage = function(event) {
    try {
        let data = JSON.parse(event.data);
        switch(data.type) {
            case "seq-open":
                this.openSeq(data.num, data.user, data.openLockID);
                break;

            case "seq-close":
                this.closeSeq(data.num);
                break;

            case "seq-change":
                this.changeSeq(data.num, Number(data.nid), data.user, data.ntext);
                break;

            case "seq-lock":
                this.lockSeq(data.id, data.status);
                break;

            case "com-new":
                this.addComment(data.id, this.getUserObject(data.user), data.time, data.text);
                if(data.user != me.id) {
                    alertify.log(sprintf("<b>%s</b> ha publicado un comentario", this.getUsername(data.user)));
                }
                break;

            case "com-del":
                this.deleteComment(data.id);
                break;

            case "uinfo":
                this.users[data.id] = {
                    username: data.username,
                    roles: data.roles
                };

                break;
        }
    } catch (e) {
        console.error(e);
    }
}

Subtitle.prototype.getUserObject = function(uid) {
    return {
        id: uid,
        username: this.getUsername(uid),
        roles: this.getRoles(uid)
    };
}

Subtitle.prototype.getRoles = function(uid) {
    return this.users[uid] ? this.users[uid].roles : [""];
}

Subtitle.prototype.getUsername = function(uid) {
    return this.users[uid] ? this.users[uid].username : "u#"+uid;
}

Subtitle.prototype.loadSequences = function() {
    $.ajax({
        url: '/subtitles/'+subID+'/translate/load',
        method: 'GET',
        data: {
            secondaryLang: this.secondaryLang
        }
    }).done((reply) => {
        let sequenceList = reply.sequences;
        let userList = reply.users;

        this.loaded = true;
        Object.keys(sequenceList).forEach((k) => {
            let seq = sequenceList[k];
            let idx = this.findSeqIdxByNum(seq.number);
            if(idx == -1) {
                this.state.sequences.push(seq);
            } else {
                let existingSeq = this.state.sequences[idx];
                if(seq.id != existingSeq.id) {
                    existingSeq.id = seq.id;
                    existingSeq.text = seq.text;
                    existingSeq.author = seq.author;
                    existingSeq.openInfo = seq.openInfo;
                }
            }
        });

        Object.keys(userList).forEach((uid) => {
            if(!this.users[uid]) {
                this.users[uid] = userList[uid];
            }
        });

        this.state.curPage = 1;
    });
}

Subtitle.prototype.loadComments = function() {
    $.ajax({
        url: '/subtitles/'+subID+'/translate/comments',
        method: 'GET'
    }).done((reply) => {
        this.state.comments = reply;
    }).fail(function() {
        alertify.error("Ha ocurrido un error tratando de cargar los comentarios");
    });
}

Subtitle.prototype.findSeqIdxByID = function(seqID) {
    let key = -1;
    Object.keys(this.state.sequences).forEach((k) => {
        if(this.state.sequences[k].id == seqID) {
            key = k;
            return;
        }
    });

    return key;
}

Subtitle.prototype.findSeqIdxByNum = function(seqNum) {
    let key = -1;
    Object.keys(this.state.sequences).forEach((k) => {
        if(this.state.sequences[k].number == seqNum) {
            key = k;
            return;
        }
    });

    return key;
}

Subtitle.prototype.openSeq = function(seqNum, by, openLockID) {
    let idx = this.findSeqIdxByNum(seqNum);
    if(idx < 0) {
        console.error("Could not open sequence "+seqNum+" (not found)");
        return;
    }

    let openInfo = {
        lockID: openLockID,
        by: by,
        since: (new Date()).toISOString()
    };

    this.state.sequences[idx].openInfo = openInfo;
}

Subtitle.prototype.closeSeq = function(seqNum, by) {
    let idx = this.findSeqIdxByNum(seqNum);
    if(idx < 0) {
        console.log("Could not close sequence "+seqNum+" (not found)");
        return;
    }

    this.state.sequences[idx].openInfo = null;
}

Subtitle.prototype.lockSeq = function(seqID, status) {
    let idx = this.findSeqIdxByID(seqID);
    if(idx < 0) {
        console.log("Could not set lock status for sequence "+seqID+" (not found)");
        return;
    }

    this.state.sequences[idx].locked = status;
}


Subtitle.prototype.changeSeq = function(seqNum, newSeqID, newAuthorID, newText) {
    let idx = this.findSeqIdxByNum(seqNum);
    if(idx < 0) {
        console.log("Could not update sequence text for "+seqNum+" (not found)");
        return;
    }

    let seq = this.state.sequences[idx];

    if(seq.id) {
        if(seq.id == newSeqID) {
            // We already did this change!
            console.log("Ignoring update, no change for "+seqNum);
            return;
        }

        // Since we're overwriting a sequence, save current to history
        if(!seq.history) {
            seq.history = [];
        }

        seq.history.push({
            id: seq.id,
            author: seq.author,
            text: seq.text,
            tstart: seq.tstart,
            tend: seq.tend
        });
    }

    seq.id = newSeqID;
    seq.text = newText;
    seq.author = newAuthorID;
    seq.openInfo = null;
}

Subtitle.prototype.getDataByNum = function(seqNum) {
    let idx = this.findSeqIdxByNum(seqNum);
    if(idx < 0) {
        console.error("Could not find sequence "+seqNum+" to retrieve its data");
        return;
    }

    return this.state.sequences[idx];
}

Subtitle.prototype.addComment = function(id, user, published_at, text) {
    let exists = this.state.comments.some((c) => {
        return c.id == id;
    });

    if(!exists) {
        this.state.comments.push({
            id: id,
            user: user,
            published_at: published_at,
            text: text
        });

        this.state.comments.sort((a, b) => {
            if(a.published_at < b.published_at) {
                return 1;
            }

            if(a.published_at > b.published_at) {
                return -1;
            }

            return 0;
        });
    }
}

Subtitle.prototype.deleteComment = function(id) {
    let idx = this.state.comments.findIndex((c) => {
        return c.id == id
    });

    if(idx != -1) {
        this.state.comments.splice(idx, 1);
    }
};


module.exports = Subtitle;