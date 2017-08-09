import Vue from 'vue';
import $ from 'jquery';
import timeago from 'timeago.js';
//TODO: Turn into a .vue file ^^

Vue.component('comment', {
    template: `
        <article class='comment'>
            <header>
                <ul>
                    <li class='comment-user'>
                        <a :href="'/users/' + user.id">{{ user.username }}</a>
                    </li>
                    <li class='comment-time'>
                        {{ date }}
                    </li>
                    <li class='comment-actions' v-if="canDelete">
                        <i class="fa fa-times" aria-hidden="true" @click="remove"></i>
                    </li>
                </ul>
            </header>
            <section class='comment-body' :class='bodyClasses'>
                {{ text }}
            </section>
            <section class='comment-actions'>
            </section>
        </article>
        `,

    props: ['id', 'user', 'text', 'published-at', 'type'],
    data: function() {
        return {
            date: '',
            canDelete: canDeleteComments
        }
    },
    computed: {
        bodyClasses: function () {
            let isTT = this.user.roles.includes('ROLE_TH');
            let isMod = this.user.roles.includes('ROLE_MOD');
            return {
                'role-tt': isTT && !isMod,
                'role-mod': isMod,
            }
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
            alertify.confirm("¿Seguro que deseas borrar este comentario?", function() {
                this.$emit('remove', this.id);
            }.bind(this));
        }
    }
});
