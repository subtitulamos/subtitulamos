import Vue from 'vue';
import $ from 'jquery';
import './vue/comment.js';

let $newTranslationButton = $(".translate_subtitle");
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

$(function() {
    let lastLangVal = localStorage.getItem("last-selected-translation-lang");
    
    if(lastLangVal !== null) {
        $("#translate-to-lang").val(lastLangVal);
    }
});

$("#translate-to-lang").on("change", function() {
    localStorage.setItem("last-selected-translation-lang", $(this).val());
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