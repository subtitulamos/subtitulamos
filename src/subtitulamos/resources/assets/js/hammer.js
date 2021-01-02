/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2020 subtitulamos.tv
 */

import Vue from "vue";
import "../css/hammer.scss";
import { easyFetch } from "./utils";

Vue.component("hammertarget", {
  template: `<div v-if='!deleted' class='grid-row'>
        <div>
          <a class="text small username" :class="userTypeClass" :href='\"/users/\"+id'>
            <i v-if="isMod" class="fas fa-gem"></i>
            <i v-else-if="isTT" class="fas fa-hand-sparkles"></i>
            <span>{{ username }}</span>
          </a>
        </div>
        <div>
            <div>
              <span class="text bold">{{ total }}</span>
              <span class="text small">({{ corrected }} corregidas)</span>
            </div>
            <a class="text tiny delete-button" href="javascript:void(0)" @click='completeHammer'>Borrar todas</a>
        </div>
        <div>
          <div class="text bold">{{ latest }}</div>
          <a class="text tiny delete-button" v-show="latest > 0"href="javascript:void(0)" @click='latestHammer'>Borrar</a>
        </div>
    </div>`,
  props: ["id", "username", "userRoles", "countCorrected", "countLatest"],
  data: function () {
    return {
      corrected: this.countCorrected * 1,
      latest: this.countLatest * 1,
      deleted: false,
      isMod: this.userRoles.includes("ROLE_MOD"),
      isTT: this.userRoles.includes("ROLE_TT"),
    };
  },
  computed: {
    total: function () {
      return this.latest + this.corrected;
    },
    userTypeClass: function () {
      return {
        "role-tt": this.isTT && !this.isMod,
        "role-mod": this.isMod,
      };
    },
  },
  methods: {
    completeHammer() {
      Swal.fire({
        type: "warning",
        confirmButtonText: "Borrar todas",
        cancelButtonText: "Cancelar",
        showCancelButton: true,
        html:
          "Estás a punto de eliminar TODAS las contribuciones de <b>" +
          this.username +
          "</b> en el subtítulo.<br/><br/>¿Estás seguro de querer continuar?",
      }).then((result) => {
        if (result.value) {
          easyFetch("/subtitles/" + subID + "/hammer", {
            method: "POST",
            rawBody: {
              user: this.id,
              type: "complete",
            },
          })
            .then(() => {
              this.deleted = true;
              this.corrected = 0;
              this.latest = 0;

              Toasts.success.fire(
                "¡Puf! Las contribuciones de&nbsp;<b>" +
                  this.username +
                  "</b>&nbsp;han sido eliminadas"
              );
            })
            .catch(() => {
              Toasts.error.fire(
                "Ha ocurrido un problema al intentar borrar las contribuciones. Por favor, inténtalo de nuevo"
              );
            });
        }
      });
    },

    latestHammer() {
      Swal.fire({
        type: "warning",
        confirmButtonText: "Borrar",
        cancelButtonText: "Cancelar",
        showCancelButton: true,
        html:
          "Estás a punto de eliminar las contribuciones sin corregir de <b>" +
          this.username +
          "</b> en el subtítulo.<br/><br/>¿Estás seguro de querer continuar?",
      }).then((result) => {
        if (result.value) {
          easyFetch("/subtitles/" + subID + "/hammer", {
            method: "POST",
            rawBody: {
              user: this.id,
              type: "latest",
            },
          })
            .then(() => {
              this.latest = 0;
              Toasts.success.fire(
                "Las contribuciones sin corregir de&nbsp;<b>" +
                  this.username +
                  "</b>&nbsp;han sido eliminadas"
              );
            })
            .catch(() => {
              Toasts.error.fire(
                "Ha ocurrido un problema al intentar borrar las contribuciones. Por favor, inténtalo de nuevo"
              );
            });
        }
      });
    },
  },
});

const page = new Vue({
  el: ".content",
});
