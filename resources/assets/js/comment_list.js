import Vue from 'vue';
import $ from 'jquery';
import './vue/comment.js';

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


let comments = new Vue({
    el: '#comment_content',
    data: {
        newComment: '',
        comments: [
        ],
        page: 1
    },
    methods: {
        refresh: function() {
            loadComments(this.page);
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
                url: '/episodes/'+epId+'/comments/'+id,
                method: 'DELETE'
            }).done(() => {
                loadComments();
            }).fail(() => {
                alertify.error('Se ha encontrado un error al borrar el comentario');
                if(typeof cidx !== 'undefined') {
                    // Insert the comment right back where it was
                    this.comments.splice(cidx, 0, c);
                } else {
                    loadComments(this.page);
                }
            });
        },

        nextPage: function() {
            this.page++;
            loadComments(this.page);
            
            document.getElementById('comments').scrollIntoView();
        },

        prevPage: function() {
            this.page--;
            loadComments(this.page);
            
            document.getElementById('comments').scrollIntoView();
        }
    }
});

function loadComments(page)
{
    $.ajax({
        url: '/comments/'+ commentType +'/load?page='+page,
        method: 'GET'
    }).done(function(reply) {
        comments.comments = reply;
    }).fail(function() {
        alertify.error("Ha ocurrido un error tratando de cargar los comentarios");
    })
}

// Start by loading the comments, and set a timer to do so frequently
loadComments(1);
setInterval(() => { loadComments(comments.page) }, 60000);