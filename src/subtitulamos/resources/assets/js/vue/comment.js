import Vue from "vue";
import timeago from "timeago.js";
//TODO: Turn into a .vue file

Vue.component("comment", {
  template: `
  <article class="comment" :class="{'comment-pinned': pinned}">
    <div v-if="isMod || isTT"class="by-special-user-icon" :class="userTypeClass">
      <i v-if="isMod" class="fas fa-gem"></i>
      <i v-else-if="isTT" class="fas fa-hand-sparkles"></i>
    </div>

    <div class="comment-body">
      <section class="comment-info">
        <a :href="'/users/' + user.id">
          <span class="comment-creator text small bold" :class="userTypeClass">

            {{ user.username }}
          </span>
        </a>
        <span class="comment-date text tiny">{{ date }}</span>
        <span class='comment-episode' v-if="episode">
          <a :href="'/episodes/'+ episode.id">{{ episode.name }}</a>
        </span>
        <span class='comment-episode' v-if="subtitle">
          <a :href="'/subtitles/'+ subtitle.id + '/translate'">{{ subtitle.name }}</a>
        </span>
      </section>

      <section class="comment-content">
        <p v-if="!editing" v-html="formattedText"></p>
        <div class="open-comment" v-else>
          <textarea rows="4" v-if="editing" v-model="text"></textarea>
          <button class="save-comment" @click="save">
            <i class="fab fa-telegram-plane"></i>
            <span class="text mini spaced">GUARDAR</span>
          </button>
        </div>
      </section>

      <section class='comment-actions'>
        <span class="text tiny" aria-hidden="true" @click="remove" v-if="canDelete">Borrar</span>
        <span class="text tiny" aria-hidden="true" @click="edit" v-if="canEdit">Editar</span>
        <span aria-hidden="true" @click="$emit('pin', id)" v-if="canPin">
          <span class="text tiny" v-if="pinned">Desfijar</span>
          <span class="text tiny" v-else>Fijar</span>
        </span>
      </section>
    </div>

    <div class="pin" v-if="pinned">
      <i class="fas fa-map-pin"></i>
    </div>
  </article> `,
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
    isMod() {
      return this.user.roles.includes("ROLE_MOD");
    },
    isTT() {
      return this.user.roles.includes("ROLE_TT");
    },
    userTypeClass: function () {
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
