import $ from './jquery.js';
import Vue from './vue.js';
import timeago from './timeago.min.js';

$newTranslationButton.on("click", function() {
    $("#new-translation-opts").toggleClass("hidden");
});

$("a[disabled]").on("click", function(e) {
    e.preventDefault();
    return false;
});

$("a[data-action='delete']").on("click", function(e) {
    let subId = $(this).data("id");
    alertify.confirm("¿Estás seguro de querer borrar este subtítulo? Esta acción no es reversible.", function() {
        window.location = "/subtitles/"+subId+"/delete";
    });
});

Vue.component('comment', {
    template: `
        <article class='comment'>
            <header>
                <ul>
                    <li class='comment-user'>
                        <a :href="'/users/' + user.id">{{ user.name }}</a>
                    </li>
                    <li class='comment-time'>
                        {{ date }}
                    </li>
                    <li class='comment-actions' v-if="canDelete">
                        <i class="fa fa-times" aria-hidden="true" @click="remove"></i>
                    </li>
                </ul>
            </header>
            <section class='comment-body'>
                {{ text }}
            </section>
            <section class='comment-actions'>
            </section>
        </article>
        `,
    
    props: ['id', 'user', 'text', 'published-at'],
    data: function() {
        return {
            date: '',
            canDelete: canDeleteComments
        }
    },
    created: function() {
        this.update = setInterval(this.updateDate, 10000);
        this.updateDate();
    },
    methods: {
        updateDate: function() {
            this.date = timeago().format(this.publishedAt, 'es')
        },

        remove: function() {
            $.ajax({
                url: '/episodes/'+epId+'/comments/'+this.id,
                method: 'DELETE'
            }).done(function() {
                loadComments();
            })
        }
    }
});

let comments = new Vue({
    el: '#subtitle-comments',
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
                url: '/episodes/'+epId+'/comments/submit',
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
        }
    }
});

function loadComments()
{
    $.ajax({
        url: '/episodes/'+epId+'/comments',
        method: 'GET'
    }).done(function(reply) {
        comments.comments = reply;
    }).fail(function() {
        alertify.error("Ha ocurrido un error tratando de cargar los comentarios");
    })
}

// Start by loading the comments, and set a timer to do so frequently
loadComments();
setInterval(loadComments, 60000);