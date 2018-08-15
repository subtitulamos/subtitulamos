/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2017-2018 subtitulamos.tv
 */

import Vue from 'vue';
import $ from 'jquery';

Vue.component('hammertarget', {
    template: "<li class='target' v-if='!deleted'><i class='fa fa-gavel hammer' aria-hidden='true' @click='hammer'></i> <a :href='\"/users/\"+id'>{{ username }}</a> ({{ count }} secuencias)</li>",
    props: ['id', 'username', 'count'],
    data: function() {
        return {
            deleted: false
        }
    },
    methods: {
        hammer: function() {
            let pthis = this;
            alertify.confirm('Estás a punto de eliminar todas las contribuciones de <b>'+pthis.username+'</b> en el subtítulo.<br/>¿Estás seguro de querer continuar?', function(){
                $.ajax({
                    url: '/subtitles/' + subID + '/hammer',
                    method: 'POST',
                    data: {
                        user: pthis.id
                    }
                }).done(function() {
                    pthis.deleted = true;
                    alertify.success("Poof! Las contribuciones de <b>"+pthis.username+"</b> han sido eliminadas");
                });
            });
        }
    }
});

let page = new Vue({
    el: '#hammer'
});
