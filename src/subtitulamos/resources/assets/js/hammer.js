/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2020 subtitulamos.tv
 */

import Vue from "vue";
import "../css/hammer.css";
import { easyFetch } from "./utils";

Vue.component("hammertarget", {
  template: `<div v-if='!deleted' class='hammer-user-block'>
        <a :href='\"/users/\"+id'>{{ username }}</a><br/>
            {{ total }} entradas totales ({{ corrected }} corregidas) - <a href="javascript:void(0)" @click='completeHammer'>Borrar todas</a><br/>
            {{ latest }} secuencias sin corregir <span v-show="latest > 0">- <a href="javascript:void(0)" @click='latestHammer'>Borrar</a></span>
    </div>`,
  props: ["id", "username", "countCorrected", "countLatest"],
  data: function () {
    return {
      corrected: this.countCorrected * 1,
      latest: this.countLatest * 1,
      deleted: false,
    };
  },
  computed: {
    total: function () {
      return this.latest + this.corrected;
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
      }).then(result => {
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
                "Poof! Las contribuciones de&nbsp;<b>" + this.username + "</b>&nbsp;han sido eliminadas"
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
      }).then(result => {
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
                "Las contribuciones sin corregir de&nbsp;<b>" + this.username + "</b>&nbsp;han sido eliminadas"
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
  el: "#hammer",
});
