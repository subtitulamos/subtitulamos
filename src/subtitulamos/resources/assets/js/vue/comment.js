import Vue from "vue";
import timeago from "timeago.js";
//TODO: Turn into a .vue file

Vue.component("comment", {
  template: `
        <article class='comment' :class="{'comment-pinned': pinned}">
          <header>
            <ul>
              <li class='comment-user'>
                <a :href="'/users/' + user.id">{{ user.username }}</a>
              </li>
              <li class='comment-time'>
                {{ date }}
              </li>
              <li class='comment-episode' v-if="episode">
                <a :href="'/episodes/'+ episode.id">{{ episode.name }}</a>
              </li>
              <li class='comment-episode' v-if="subtitle">
                <a :href="'/subtitles/'+ subtitle.id + '/translate'">{{ subtitle.name }}</a>
              </li>
              <li class='comment-actions'>
                <i class="fa fa-times" aria-hidden="true" @click="remove" v-if="canDelete"></i>
                <i class="fas fa-pencil-alt" aria-hidden="true" @click="edit" v-if="canEdit"></i>
                <i class="fas fa-thumbtack" aria-hidden="true" @click="$emit('pin', id)" v-if="canPin"></i>
              </li>
            </ul>
          </header>
          <section class='comment-body' :class='bodyClasses'>
            <p v-if="!editing" v-html="formattedText"></p>
            <div v-else>
              <textarea v-if="editing" v-model="text"></textarea>
              <button @click="save">Save</button>
            </div>
          </section>
        </article>
        `,

  props: [
    "id",
    "user",
    "base-text",
    "episode",
    "subtitle",
    "published-at",
    "type",
    "pinned",
    "create-sequence-jumps",
  ],
  data: function () {
    return {
      date: "",
      isRecentMessage: false,
      editing: false,
      text: this.baseText,
    };
  },
  computed: {
    isMyMessage() {
      return this.user.id === userId || (typeof me !== "undefined" && this.user.id === me.id);
    },
    canDelete() {
      return !!(canDeleteComments || (this.isMyMessage && this.isRecentMessage));
    },
    canEdit() {
      return !!(canEditComments || (this.isMyMessage && this.isRecentMessage));
    },
    canPin() {
      return canPinComments;
    },
    bodyClasses: function () {
      let isTT = this.user.roles.includes("ROLE_TT");
      let isMod = this.user.roles.includes("ROLE_MOD");
      return {
        "role-tt": isTT && !isMod,
        "role-mod": isMod,
      };
    },
    formattedText: function () {
      let text = this.text;
      if (this.createSequenceJumps) {
        text = text.replace(
          /#(\d+)/g,
          "<a href='javascript:void(0)' onclick='translation.jumpToSequence($1)'>$&</a>"
        );
      }

      return text;
    },
  },
  created: function () {
    this.update = setInterval(this.updateDate, 10000);
    this.updateDate();
  },
  methods: {
    updateDate: function () {
      this.date = timeago().format(this.publishedAt, "es");

      // And update this (to force recalculation of computed props)
      const secsSincePublish = (new Date().getTime() - new Date(this.publishedAt).getTime()) / 1000;
      this.isRecentMessage = secsSincePublish < MAX_USER_EDIT_SECONDS;
    },

    remove: function () {
      Swal.fire({
        type: "warning",
        confirmButtonText: "Borrar",
        cancelButtonText: "Cancelar",
        showCancelButton: true,
        title: "Borrar comentario",
        html: "Esta acción es irreversible. <br/> ¿Seguro que deseas borrar este comentario?",
      }).then((result) => {
        if (result.value) {
          this.$emit("remove", this.id);
        }
      });
    },

    edit: function () {
      this.editing = true;
    },

    save: function () {
      this.editing = false;
      this.$emit("save", this.id, this.text);
    },
  },
});
