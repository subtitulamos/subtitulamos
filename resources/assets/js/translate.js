import Vue from 'vue';
import $ from 'jquery';
import timeago from 'timeago.js';
import './vue/comment.js';

let textFilter = "";
let authorFilter = 0;
let untranslated = 0;
function loadPage(pageNum, secondaryLang) {
    $.ajax({
        url: '/subtitles/'+subID+'/translate/page/' + pageNum,
        method: 'GET',
        data: {
            textFilter: textFilter,
            authorFilter: authorFilter,
            untranslated: untranslated,
            secondaryLang: secondaryLang
        }
    }).done(function(pageData) {
        // Prepare sequences
        let sequences = [];
        Object.keys(pageData).forEach(function(k) {
            let sequence = pageData[k];

            // Add some state variables
            sequence.editing = false;
            sequence.canSave = false;
            sequence.lineCounters = [];

            sequences.push(sequence);
        });

        translation.sequences = sequences;

        // Update pagelist
        translation.curPage = pageNum;
    });
}

Vue.component('seqlock', {
    template: `
        <li>#{{ seqnum }} por <a :href="'/users/'+user.id">{{ user.username }}</a> [{{ time }}] <i class='fa fa-times' aria-hidden='true' @click='release'></i></li>
    `,
    props: ["id", "seqnum", "user", "time"],
    methods: {
        release: function() {
            $.ajax({
                url: '/subtitles/'+subID+'/translate/open-list/'+this.id,
                method: 'DELETE',
                data: {}
            }).done(function() {
                loadOpenLocks();
            });
        }
    }
});

Vue.component('sequence', {
    template: `
        <tr :class="{'locked':  locked, 'verified': verified, 'current': !history, 'history': history}">
            <td><span v-if="!history">{{ number }}</span></td>
            <td class="user"><a :href="'/users/' + author.id" tabindex="-1">{{ authorName }}</a></td>
            <td class="time">{{ tstart | nice_time }} <i class="fa fa-long-arrow-right"></i> {{ tend | nice_time }}</td>
            <td class="text"><pre>{{ secondaryText }}</pre></td>
            <td class="text" @click="editSequence" :class="{'translatable': !history}">
                <pre v-if="!editing && text">{{ text }}</pre>
                <pre v-if="!editing && !text" class="untranslated">- Sin traducir -</pre>

                <textarea v-model="text" v-if="editing" @keyup.ctrl="keyboardActions"></textarea>
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

    props: ['originalId', 'pLocked', 'pVerified', 'number', 'originalAuthor', 'tstart', 'tend', 'secondaryText', 'originalText', 'history'],
    data: function() {
        return {
            id: this.originalId,
            author: this.originalAuthor,
            text: this.originalText,
            locked: this.pLocked,
            verified: this.pVerified,
            editing: false
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
        lineCounters: function() {
            let lines = this.text.split("\n");
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
            return this.author.name ? this.author.name : " - ";
        },

        shouldFixLevel: function() {
            let tlines = [];
            $.each(this.text.split("\n"), function (i, val) {
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
        editSequence: function() {
            if(this.editing || this.history) {
                return true; // Already , no effect
            }

            $.ajax({
                url: '/subtitles/'+subID+'/translate/open',
                method: 'POST',
                data: {
                    seqNum: this.number
                }
            }).done(function(reply) {
                if(reply.ok) {
                    this.editing = true;
                    this.curText = this.text;
                    if(reply.text !== null) {
                        this.text = reply.text;
                    }
                    if(reply.id !== null) {
                        this.id = reply.id;
                    }
                } else {
                    alertify.error(reply.msg);
                }
            }.bind(this));
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
            if(!this.canSave)
                return false;

            let pthis = this;
            if(this.id) {
                // Editing a sequence, save the changes
                $.ajax({
                    url: '/subtitles/'+subID+'/translate/save',
                    method: 'POST',
                    data: {
                        seqID: this.id,
                        text: this.text,
                    }
                }).done(function(newId){
                    pthis.id = newId;
                    pthis.text = pthis.text.trim().replace(/ +/g,' ');
                    if(!pthis.text) {
                        pthis.text = " ";
                    }
                })
                .fail(function() {
                    alertify.error("Ha ocurrido un error al intentar guardar la secuencia");
                    pthis.editing = true;
                });
            } else {
                // Translating a sequence for the first time
                $.ajax({
                    url: '/subtitles/'+subID+'/translate/create',
                    method: 'POST',
                    data: {
                        number: this.number,
                        text: this.text
                    }
                }).done(function(newId){
                    pthis.id = newId;
                    pthis.text = pthis.text.trim().replace(/ +/g,' ');
                    if(!pthis.text) {
                        pthis.text = " ";
                    }

                    pthis.author = {
                        id: myId,
                        name: myName
                    };
                })
                .fail(function() {
                    alertify.error("Ha ocurrido un error al intentar guardar la secuencia");
                    pthis.editing = true;
                });
            }

            this.editing = false;
        },

        discard: function() {
            // Discard sequence changes
            this.text = this.curText;
            this.editing = false;

                $.ajax({
                url: '/subtitles/'+subID+'/translate/close',
                method: 'POST',
                data: {
                    seqNum: this.number
                }
            })
            .fail(function() {
                alertify.error("Ha ocurrido un error al intentar cerrar la secuencia");
                pthis.editing = true;
            });
        },

        toggleLock: function() {
            if(!canLock) {
                return false;
            }

            let preLock = this.locked;
            this.locked = !this.locked;

            $.ajax({
                url: '/subtitles/'+subID+'/translate/lock',
                method: 'POST',
                data: {
                    seqID: this.id
                }
            }).fail(function() {
                // Only revert if the request failed
                this.locked = preLock;
            }.bind(this));
        },

        fix: function() {
            if(this.shouldFixLevel <= 0) {
                return false;
            }

            let ntext = this.balanceText(true).join('\n');
            if(ntext != this.text) {
                this.text = ntext;
            } else {
                let tlines = [];
                $.each(this.text.split("\n"), function (i, val) {
                    tlines.push(val.trim());
                });

                let dialogLineCount = (ntext.match(/(?:^|\s)-/g) || []).length;
                if(tlines.length > 1 && tlines[0].length >= 0 && tlines[1].length > 0 && dialogLineCount != 2) {
                    this.text = tlines.join(' ');
                }
            }
        },

        balanceText: function(opinionated) {
            if (typeof opinionated == 'undefined') {
                // The default criteria is opinionated: if there's no difference between
                // leaving a word on either line, it's left on the lower one.
                opinionated = true;
            }

            let originalText = this.text;
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
            loadPage(page, availSecondaryLangs[0]);
            document.getElementById('translation').scrollIntoView();
        }
    }
});

/**************************
*        COMMENTS
***************************/

let comments = new Vue({
    el: '#translation-comments',
    data: {
        newComment: '',
        comments: [
        ]
    },
    methods: {
        publishComment: function() {
            let comment = this.newComment;
            this.newComment = '';

            $.ajax({
                url: '/subtitles/'+subID+'/translate/comments/submit',
                method: 'POST',
                data: {
                    text: comment
                }
            }).done(function() {
                // Cheap solution: reload the entire comment box
                loadComments();
            }).fail(function() {
                alertify.error("Ha ocurrido un error al enviar tu comentario");
            });
        },

        refresh: function() {
            loadComments();
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
            }).done(function() {
                loadComments();
            }).fail(function() {
                alertify.error('Se ha encontrado un error al borrar el comentario');
                if(typeof cidx !== 'undefined') {
                    // Insert the comment right back where it was
                    this.comments.splice(cidx, 0, c);
                } else {
                    loadComments();
                }
            }.bind(this));
        }
    }
});

function loadComments()
{
    $.ajax({
        url: '/subtitles/'+subID+'/translate/comments',
        method: 'GET'
    }).done(function(reply) {
        comments.comments = reply;
    }).fail(function() {
        alertify.error("Ha ocurrido un error tratando de cargar los comentarios");
    })
}

function loadOpenLocks()
{
    $.ajax({
        url: '/subtitles/'+subID+'/translate/open-list',
        method: 'GET'
    }).done(function(reply) {
        translation.openLocks = reply;
    }).fail(function() {
        alertify.error("Ha ocurrido un error tratando de cargar las secuencias abiertas");
    });
}

/**
* Boot
*/
let translation = new Vue({
    el: '#translation-details',
    data: {
        sequences: [],
        pages: [],
        curPage: 0,
        lastPage: pageCount,
        openLocks: []
    },
    methods: {
        applyFilter: function() {
            let newTextFilter = $("#text-filter").val();
            let newAuthorFilter = $("#author-filter").val();
            let newUntranslated = $("#untranslated-filter").is(":checked");
            let reload = newTextFilter != textFilter || newAuthorFilter != authorFilter || newUntranslated != untranslated;

            textFilter = newTextFilter;
            authorFilter = newAuthorFilter;
            untranslated = newUntranslated;

            if(reload) {
                loadPage(1, availSecondaryLangs[0]);
            }
        }
    }
});

for(let p = 1; p < pageCount + 1; ++p) {
    translation.pages.push(p);
}

loadPage(1, availSecondaryLangs[0]); // Load first page by default
loadComments();
if(canReleaseOpenLock) {
    loadOpenLocks();
    setInterval(loadOpenLocks, 45000);
}

setInterval(loadComments, 30000);

// Absorb and block default Ctrl+S behaviour
$(document).bind('keydown', function(e) {
  if(e.ctrlKey && (e.which == 83)) {
    e.preventDefault();
  }
});