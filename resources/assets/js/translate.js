import Vue from 'vue';
import $ from 'jquery';
import timeago from 'timeago.js';
import './vue/comment.js';
import Subtitle from './subtitle.js';
import ReconnectingWebsocket from 'reconnecting-websocket';
import dateformat from 'dateformat';

// Components and such
Vue.component('seqlock', {
    template: `
        <li>#{{ seqnum }} por <a :href="'/users/'+uid">{{ username }}</a> [ {{ niceTime }} ] <i class='fa fa-times' aria-hidden='true' @click='release'></i></li>
    `,
    props: ["id", "seqnum", "uid", "time"],
    methods: {
        release: function() {
            let seq = sub.getDataByNum(this.seqnum);
            if(!seq) {
                console.error("Could not find sequence to release!", this.seqnum);
                return;
            }

            let seqInfoCopy = JSON.parse(JSON.stringify(seq.openInfo));
            sub.closeSeq(this.seqnum);

            $.ajax({
                url: '/subtitles/'+subID+'/translate/open-lock/'+this.id,
                method: 'DELETE',
                data: {}
            }).fail(() => {
                sub.openSeq(this.seqnum, seqInfoCopy.by, seqInfoCopy.lockID);
            });
        }
    },
    computed: {
        username: function() {
            return sub.getUsername(this.uid);
        },

        niceTime: function() {
            let d = new Date(this.time);
            return dateformat(d, "d/mmm HH:MM");
        }
    }
});

Vue.component('sequence', {
    template: `
        <tr :class="{'locked':  locked, 'verified': verified, 'current': !history, 'history': history}">
            <td><span v-if="!history">{{ number }}</span></td>
            <td class="user"><a :href="'/users/' + author" tabindex="-1">{{ authorName }}</a></td>
            <td class="time">{{ tstart | nice_time }} <i class="fa fa-long-arrow-right"></i> {{ tend | nice_time }}</td>
            <td class="text"><pre>{{ secondaryText }}</pre></td>
            <td class="text" @click="openSequence" :class="{'translatable': !history && !openByOther, 'hint--left hint--bounce hint--rounded': openByOther}" :data-hint="textHint">
                <pre v-if="!editing && id">{{ text }}</pre>
                <pre v-if="!editing && !id" class="untranslated">- Sin traducir -</pre>

                <i class="fa fa-pencil-square-o open-other" aria-hidden="true" v-if='openByOther'></i>

                <textarea v-model="editingText" v-if="editing" @keyup.ctrl="keyboardActions"></textarea>
                <div class='fix-sequence' :class="{'warning': shouldFixLevel > 1, 'suggestion': shouldFixLevel == 1}" v-if="editing && shouldFixLevel > 0" @click="fix">
                    <i class="fa fa-wrench" aria-hidden="true"></i>
                </div>
                <div class="line-status" v-if="editing">
                    <span class="line-counter" :class="lineCounters[0] > 40 ? 'counter-error' : (lineCounters[0] > 35 ? 'counter-warning' : '')">{{ lineCounters[0] }}</span>
                    <span class="line-counter" v-if="lineCounters[1]" :class="lineCounters[1] > 40 ? 'counter-error' : (lineCounters[1] > 35 ? 'counter-warning' : '')">{{ lineCounters[1] }}</span>
                </div>
            </td>
            <td class="actions">
                <div v-if="!history && editing">
                    <i class="fa fa-floppy-o" :class="{'disabled': !canSave}" @click="save" tabindex="0" @keyup.enter="save"></i>
                    <i class="fa fa-times-circle-o" @click="discard" tabindex="0" @keyup.enter="discard"></i>
                </div>

                <div v-if="translated && !history && !editing">
                    <!--<i class="fa" @click="toggleVerify" :class="verified ? 'fa-check-circle' : 'fa-question-circle-o'" v-if="!locked"></i>-->
                    <i class="fa" @click="toggleLock" :class="locked ? 'fa-lock' : 'fa-unlock-alt'" v-if="canLock || locked"></i>
                </div>

                <div v-if="false && history">
                    <i class="fa fa-undo" aria-hidden="true"></i>
                </div>
            </td>
        </tr>
        `,

    props: {
        id: Number,
        locked: Boolean,
        verified: Boolean,
        number: Number,
        author: Number,
        tstart: Number,
        tend: Number,
        secondaryText: String,
        text: String,
        history: Boolean,
        openInfo: Object
    },
    data: function() {
        return {
            editingText: this.text
        }
    },
    filters: {
        nice_time: function (ms) {
            if (!ms)
                return '00:00:00.000';

            let h = 0, m = 0, s = 0;
            s = Math.floor(ms/1000);

            h = Math.floor(s/3600);
            s -= h * 3600;
            m = Math.floor(s/60);
            s -= m * 60;

            ms -= (s + m * 60 + h * 3600)*1000;

            let stime = "";
            if(h < 10) stime += "0";
            stime += h+":";
            if(m < 10) stime += "0";
            stime += m+":";
            if(s < 10) stime += "0";
            stime += s+".";
            if(ms < 100) stime += "0";
            if(ms < 10) stime += "0";
            stime += ms;

            return stime;
        }
    },
    computed: {
        openByOther: function() {
            return this.openInfo && this.openInfo.by && this.openInfo.by != me.id;
        },

        textHint: function() {
            return this.openByOther ? sub.getUsername(this.openInfo.by)+" está editando esta secuencia" : '';
        },

        editing: function() {
            return this.openInfo && this.openInfo.by == me.id;
        },

        lineCounters: function() {
            let lines = this.editingText.split("\n");
            let lineCounters = [];

            for(let i = 0; i < lines.length; ++i) {
                let text = lines[i].replace(/ +/g,' ');
                if(text.trim().length > 0) {
                    lineCounters[i] = text.trim().length;
                } else {
                    lineCounters[i] = text.length;
                }
            }

            return lineCounters;
        },

        canSave: function() {
            return !this.history && this.lineCounters.length > 0 && this.lineCounters[0] > 0 && this.lineCounters[0] <= 40
                        && (!this.lineCounters[1] || this.lineCounters[1] <= 40);
        },

        canLock: function() {
            return canLock && !this.history && this.id;
        },

        translated: function() {
            return this.id != 0
        },

        authorName: function() {
            return this.author ? sub.getUsername(this.author) : " - ";
        },

        shouldFixLevel: function() {
            let tlines = [];
            $.each(this.editingText.split("\n"), function (i, val) {
                tlines.push(val.trim());
            });

            let full = tlines.join('\n');
            let msg, hint;
            let dialogLineCount = (full.match(/(?:^|\s)-/g) || []).length;
            if (full.length > 40 || (dialogLineCount == 2 && full.match(/^\s*-/g))) {
                let unopinionatedMatch = this.balanceText(false).join('\n') == full;
                let opinionatedMatch = this.balanceText(true).join('\n') == full;

                return !unopinionatedMatch && !opinionatedMatch ? 2 : 0;
            } else if(tlines.length > 1 && tlines[0].length >= 0 && tlines[1].length > 0 && dialogLineCount != 2) {
                return 1;
            }

            return 0;
        }
    },
    methods: {
        openSequence: function() {
            if(this.editing || this.history || this.openByOther) {
                return true; // Already / no effect / can't open
            }

            this.editingText = this.text;
            sub.openSeq(this.number, me.id, 0);
            $.ajax({
                url: '/subtitles/'+subID+'/translate/open',
                method: 'POST',
                data: {
                    seqNum: this.number
                }
            }).done((reply) => {
                if(!reply.ok) {
                    sub.closeSeq(this.number);
                    alertify.error(reply.msg);
                }
            }).fail(() => {
                sub.closeSeq(this.number);
                alertify.error("Ha ocurrido un error desconocido al intentar editar");
            });
        },

        keyboardActions: function(e) {
            if(e.altKey && e.key == "f") {
                this.fix();
            } else if(e.key == "s") {
                this.save();
            }

            e.preventDefault();
        },

        save: function() {
            if(!this.canSave) {
                return false;
            }

            let ntext = this.editingText.trim().replace(/ +/g,' ');
            if(!ntext) {
                ntext = " ";
            }

            if(ntext == this.text) {
                this.discard();
            }

            if(this.id) {
                // Editing a sequence, save the changes
                $.ajax({
                    url: '/subtitles/'+subID+'/translate/save',
                    method: 'POST',
                    data: {
                        seqID: this.id,
                        text: ntext,
                    }
                }).done((newID) => {
                    sub.changeSeq(this.number, Number(newID), me.id, ntext);
                }).fail(() => {
                    alertify.error("Ha ocurrido un error al intentar guardar la secuencia");
                });
            } else {
                // Translating a sequence for the first time
                $.ajax({
                    url: '/subtitles/'+subID+'/translate/create',
                    method: 'POST',
                    data: {
                        number: this.number,
                        text: ntext
                    }
                }).done((newID) => {
                    sub.changeSeq(this.number, Number(newID), me.id, ntext);
                }).fail(() => {
                    alertify.error("Ha ocurrido un error al intentar guardar la secuencia");
                });
            }
        },

        discard: function() {
            if(!this.openInfo) {
                return;
            }

            let oLockID = this.openInfo.id;
            sub.closeSeq(this.number);

            $.ajax({
                url: '/subtitles/'+subID+'/translate/close',
                method: 'POST',
                data: {
                    seqNum: this.number
                }
            })
            .fail(() => {
                sub.openSeq(this.number, me.id, oLockID);
                alertify.error("Ha ocurrido un error al intentar cerrar la secuencia");
            });
        },

        toggleLock: function() {
            if(!canLock) {
                return false;
            }

            let preLock = this.locked;
            sub.lockSeq(this.id, !this.locked);

            $.ajax({
                url: '/subtitles/'+subID+'/translate/lock',
                method: 'POST',
                data: {
                    seqID: this.id
                }
            }).fail(() => {
                // Revert, the request failed
                sub.lockSeq(this.id, preLock);
                alertify.error("Error al intentar cambiar el estado de esta secuencia");
            });
        },

        fix: function() {
            if(this.shouldFixLevel <= 0) {
                return false;
            }

            let ntext = this.balanceText(true).join('\n');
            if(ntext != this.editingText) {
                this.editingText = ntext;
            } else {
                let tlines = [];
                $.each(this.editingText.split("\n"), function (i, val) {
                    tlines.push(val.trim());
                });

                let dialogLineCount = (ntext.match(/(?:^|\s)-/g) || []).length;
                if(tlines.length > 1 && tlines[0].length >= 0 && tlines[1].length > 0 && dialogLineCount != 2) {
                    this.editingText = tlines.join(' ');
                }
            }
        },

        balanceText: function(opinionated) {
            if (typeof opinionated == 'undefined') {
                // The default criteria is opinionated: if there's no difference between
                // leaving a word on either line, it's left on the lower one.
                opinionated = true;
            }

            let originalText = this.editingText;
            let text = originalText.replace(/[\n\r]/g, " "); // Delete line breaks, we'll do those

            let dialogLineCount = (text.match(/(?:^|\s)-/g) || []).length;
            let isDialog = text.match(/^\s*-/g) && dialogLineCount == 2;
            if (isDialog) {
                /*
                * Looks like we have a dialog. We must preserve each line separated unless that is not possible.
                */
                let fDialogPos = text.indexOf('-');
                let fDialogPos2 = text.substr(fDialogPos + 1).indexOf('-') + fDialogPos;
                if (fDialogPos2 - fDialogPos <= 40 && text.length - 1 - fDialogPos2 <= 40) { //len - 1 due to the space introduced by the line break
                    // If they fit in two separate lines, that's how it goes
                    let dialogLines = [];
                    dialogLines[0] = text.slice(0, fDialogPos2).trim();
                    dialogLines[1] = text.slice(fDialogPos2, text.length).trim();
                    return dialogLines;
                }
            }

            if (text.length <= 40) {
                // Nothing to divide
                return [originalText];
            }

            let behind = 0;
            let ahead = text.length;
            let curWordPos = 0;
            let ignoreSepUntilNextWord = false;
            for (let i = 0; i < text.length; ++i) {
                behind++;
                ahead--;

                let curChar = text[i];
                if (curChar == '-') {
                    ignoreSepUntilNextWord = true;
                    continue;
                }

                // If we find a word separator...
                if (curChar.match(/[ .,;?!-]/)) {
                    if (ignoreSepUntilNextWord)
                        continue;

                    let nextChar = i + 1 < text.length ? text[i + 1] : null;
                    let prevChar = i > 0 ? text[i - 1] : null;

                    /*
                    * If next char is also a separator (unless its a space), we do not split yet.
                    */
                    if (nextChar && nextChar.match(/[.,;?!-]/)) {
                        continue;
                    }

                    /*
                    * Numbers, format: 123.578.213
                    * If we're at a dot or space, look forward and backwards. If there's a number, and the
                    * next character also is a number, keep consuming characters.
                    */
                    if ((curChar == "." || curChar == " ") && nextChar && prevChar && $.isNumeric(nextChar) && $.isNumeric(prevChar)) {
                        continue;
                    }

                    if (behind >= ahead) {
                        let j;

                        // <dir>IfSplit counts the number of characters that we'd actually have if we ignore spaces
                        let aheadIfSplit = ahead;
                        if (aheadIfSplit > 0) {
                            j = i + 1;
                            while (j < text.length) {
                                if (text[j] != " ")
                                    break;

                                aheadIfSplit--;
                                j++;
                            }
                        }

                        let behindIfSplit = behind;
                        if (behindIfSplit > 0) {
                            j = i;
                            while (j >= 0) {
                                if (text[j] != " ") {
                                    break;
                                }

                                behindIfSplit--;
                                j--;
                            }
                        }

                        // Split
                        let curWordSize = i - curWordPos;
                        let aheadWithWord = aheadIfSplit + curWordSize;
                        let behindWithoutWord = behindIfSplit - curWordSize;
                        let splitAt = i;

                        let diff = Math.abs(aheadWithWord - behindWithoutWord) - Math.abs(aheadIfSplit - behindIfSplit);
                        if (diff < 0 || (opinionated && diff == 0)) {
                            // Should've split on the last separator
                            splitAt = curWordPos;
                        }

                        let lines = [];
                        lines[0] = text.slice(0, splitAt + 1).trim().replace(/\s\s+/, " ");
                        lines[1] = text.slice(splitAt + 1, text.length).trim().replace(/\s\s+/, " ");
                        return lines;
                    }

                    curWordPos = i;
                } else if (ignoreSepUntilNextWord) {
                    // We found a char that's not a separator so remove the flag and carry on
                    ignoreSepUntilNextWord = false;
                }
            }

            // No division
            return [text];
        }
    },
});

/**************************
*        PAGINATION
***************************/
Vue.component('pagelist', {
    template: `
        <ul class="pagination">
            <li class="change-page" @click="prevPage" :class="{ disabled: curPage == 1 }"><i class="fa fa-chevron-left" aria-hidden="true"></i></li>
            <li v-for="page in pages" class="target-page" :class="page == curPage ? 'active' : ''" @click="toPage(page)">{{ page }}</li>
            <li class="change-page" @click="nextPage" :class="{ disabled: curPage == lastPage }"><i class="fa fa-chevron-right" aria-hidden="true"></i></a></li>
        </ul>
    `,
    props: ["curPage", "pages", "lastPage"],
    methods: {
        nextPage: function() {
            if(this.curPage < this.lastPage)
                this.toPage(this.curPage + 1);
        },

        prevPage: function() {
            if(this.curPage > 1)
                this.toPage(this.curPage - 1);
        },

        toPage: function(page) {
            document.getElementById('translation').scrollIntoView();
            this.$emit("change-page", page);
        }
    }
});

/**
* Boot
*/
const SEQS_PER_PAGE = 20;
let translation = new Vue({
    el: '#translation',
    data: {
        sequences: [],
        curPage: 1,
        filters: {
            onlyUntranslated: false,
            author: 0,
            text: ''
        },
        comments: [],
        loaded: false,
        loadedOnce: false,
        newComment: '',
        canReleaseOpenLock: canReleaseOpenLock
    },
    computed: {
        lastPage: function() {
            return Math.ceil(this.visibleSequences.length / SEQS_PER_PAGE);
        },

        pages: function() {
            let pages = [];
            for(let i = 1; i <= this.lastPage; ++i) {
                pages.push(i);
            }

            return pages;
        },

        visibleSequences: function() {
            return this.sequences.filter((seq) => {
                if(this.filters.onlyUntranslated && seq.id) {
                    return false;
                }

                if(this.filters.author != 0) {
                    let authorFilterFn = (seq) => {
                        return seq.author && this.filters.author == seq.author;
                    }

                    if(!authorFilterFn(seq) && (!seq.history || !seq.history.some(authorFilterFn))) {
                        return false;
                    }
                }

                if(this.filters.text != '') {
                    let textFilterFn = (seq) => {
                        let match = this.filters.text.toLowerCase();
                        return seq.text.toLowerCase().includes(match) || (seq.secondaryText && seq.secondaryText.toLowerCase().includes(match));
                    };

                    if(!textFilterFn(seq) && (!seq.history || !seq.history.some(textFilterFn))) {
                        return false;
                    }
                }

                return true;
            });
        },

        pageSequences: function() {
            return this.visibleSequences.filter((ele, idx) => {
                return Math.floor(idx/SEQS_PER_PAGE) == this.curPage - 1;
            });
        },

        openLocks: function() {
            let locks = [];
            this.sequences.forEach((seq) => {
                if(seq.openInfo) {
                    locks.push({
                        id: seq.openInfo.lockID,
                        uid: seq.openInfo.by,
                        time: seq.openInfo.since,
                        seq_number: seq.number
                    });
                }
            });

            return locks;
        },

        authors: function() {
            let authors = {};
            this.sequences.forEach((seq) => {
                if(seq.author && !authors[seq.author]) {
                    authors[seq.author] = sub.getUsername(seq.author);
                }
            });

            return authors;
        }
    },
    methods: {
        onChangePage: function(page) {
            this.curPage = page;
        },

        toggleUntranslatedFilter: function() {
            this.curPage = 1;
            this.filters.onlyUntranslated = !this.filters.onlyUntranslated;
        },

        updateAuthorFilter: function(e) {
            this.curPage = 1;
            this.filters.author = Number(e.target.value);
        },

        updateTextFilter: function(e) {
            this.curPage = 1;
            this.filters.text = e.target.value;
        },

        publishComment: function() {
            let comment = this.newComment;
            this.newComment = '';

            $.ajax({
                url: '/subtitles/'+subID+'/translate/comments/submit',
                method: 'POST',
                data: {
                    text: comment
                }
            }).done(function(id) {
                sub.addComment(id, sub.getUserObject(me.id), (new Date()).toISOString(), comment);
            }).fail(function() {
                alertify.error("Ha ocurrido un error al enviar tu comentario");
            });
        },

        remove: function(id) {
            let c, cidx;
            for(let i = 0; i < this.comments.length; ++i) {
                if(this.comments[i].id == id) {
                    // Save comment and remove it from the list
                    c = this.comments[i];
                    cidx = i;
                    this.comments.splice(cidx, 1);
                    break;
                }
            }

            $.ajax({
                url: '/subtitles/'+subID+'/translate/comments/'+id,
                method: 'DELETE'
            }).fail(function() {
                alertify.error('Se ha encontrado un error al borrar el comentario');
                if(typeof cidx !== 'undefined') {
                    // Insert the comment right back where it was
                    this.comments.splice(cidx, 0, c);
                }
            }.bind(this));
        }
    }
});

let sub = new Subtitle(subID, translation, availSecondaryLangs[0]);

// Set up websocket (which will itself load the sub)
const wsProtocol = window.location.protocol == 'https:' ? 'wss' : 'ws';
const ws = new ReconnectingWebsocket(wsProtocol + '://'+ window.location.hostname + "/translation-rt?subID="+subID+"&token="+wsAuthToken);
ws.onopen = () => { sub.wsOpen() };
ws.onmessage = (e) => { sub.wsMessage(e) };
ws.onerror = (e) => { sub.wsError(e) };

// Absorb and block default Ctrl+S behaviour
$(document).bind('keydown', function(e) {
  if(e.ctrlKey && (e.which == 83)) {
    e.preventDefault();
  }
});