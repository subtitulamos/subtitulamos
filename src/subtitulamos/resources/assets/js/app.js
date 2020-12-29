/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2020 subtitulamos.tv
 */
import "./search";
import {
  onDomReady,
  easyFetch,
  $getEle,
  showOverlayFromTpl,
  closeOverlay,
  $getAllEle,
  invertDropdown,
} from "./utils";

function doLogin(e) {
  e.preventDefault();

  const $loginError = document.getElementById("login-error");
  const $pwdField = document.getElementById("password");
  const username = document.getElementById("username").value.trim();
  const pwd = $pwdField.value;

  const $loginBtn = document.getElementById("login-button");
  $loginBtn.classList.toggle("is-loading", true);
  $loginError.innerHTML = ""; // Clear previous errors, just so it's clearer they're new

  // Login the user via ajax
  easyFetch("/login", {
    method: "post",
    rawBody: {
      username: username,
      password: pwd,
      // remember: document.getElementById("login_remember_me").checked, // FIXME: This should exist?
    },
  })
    .then(function () {
      window.location.reload();
    })
    .catch((err) => {
      $loginBtn.classList.toggle("is-loading", false);
      $pwdField.classList.toggle("is-danger", true);
      $loginError.classList.toggle("hidden", false);

      err.response
        .json()
        .then((cleanErr) => {
          $loginError.innerHTML = cleanErr[0];
        })
        .catch(() => {
          $loginError.innerHTML =
            "Error desconocido al intentar acceder. Por favor, inténtalo de nuevo.";
        });
    });
}

function register(e) {
  e.preventDefault();

  // Mark button as loading
  const $regButton = document.getElementById("register-button");
  $regButton.classList.toggle("is-loading", true);

  // Clean up old errors
  document
    .querySelectorAll("#register-form .is-danger")
    .forEach(($ele) => $ele.classList.toggle("is-danger"));
  document.querySelectorAll("#register-form [data-reg-error]").forEach(($ele) => $ele.remove());

  // Login the user via ajax
  easyFetch("/register", {
    method: "post",
    rawBody: {
      username: document.getElementById("reg_username").value,
      password: document.getElementById("reg_password").value,
      password_confirmation: document.getElementById("reg_password_confirmation").value,
      email: document.getElementById("reg_email").value,
      terms: document.getElementById("reg_terms").checked,
    },
  })
    .then(function () {
      window.location.reload(true);
    })
    .catch((err) => {
      $regButton.classList.toggle("is-loading", false);

      err.response
        .json()
        .then((cleanErrList) => {
          cleanErrList.forEach((curErr) => {
            const field = Object.keys(curErr)[0];
            const $field = document.getElementById("reg_" + field);
            $field.classList.toggle("is-danger", true);

            const $error = document.createElement("p");
            $error.classList.add("help", "is-danger");
            $error.dataset["regError"] = "";
            $error.innerHTML = curErr[field];
            $field.parentNode.parentNode.appendChild($error);
          });
        })
        .catch(() => {
          alert(
            "Error desconocido al intentar completar el registro. Por favor, inténtalo de nuevo."
          );
        });
    });
}

function showLoginForm() {
  showOverlayFromTpl("tpl-login");

  const $loginForm = document.getElementById("login-form");
  $loginForm.addEventListener("submit", doLogin);
  document.getElementById("username").focus();
}

onDomReady(function () {
  const $loginBtn = document.getElementById("login");
  if ($loginBtn) {
    $loginBtn.addEventListener("click", showLoginForm);
  }

  // Set up overlay logic
  const $overlayClose = document.getElementById("overlay-close");
  const $overlayFade = document.getElementById("overlay-fade");
  $overlayClose.addEventListener("click", closeOverlay);
  $overlayFade.addEventListener("click", closeOverlay);

  // if ($registerForm) {
  //   $registerForm.addEventListener("submit", register);
  // }
});

import Vue from "vue";

if (document.getElementById("control-panel")) {
  new Vue({
    el: "#control-panel",
    data: {
      isOpen: localStorage.getItem("menu-open") === "true" || false,
    },
    methods: {
      updateControlPanelStatus() {
        this.isOpen = !this.isOpen;
        localStorage.setItem("menu-open", this.isOpen);
        this.updateElementsStatus();
      },
      updateElementsStatus() {
        this.$el.classList.toggle("open", this.isOpen);
        document
          .getElementById("page-container")
          .classList.toggle("control-panel-is-open", this.isOpen);
        document
          .getElementById("page-container")
          .classList.toggle("control-panel-is-closed", !this.isOpen);
      },
      focusSearchInput() {
        this.updateControlPanelStatus();

        setTimeout(() => {
          if (this.isOpen) {
            this.$refs.cpSearchInput.focus();
          }
        }, 200);
      },
    },
    mounted() {
      this.updateElementsStatus();

      const $currentPath = window.location.pathname;
      const $ele = $getEle(`a[href="/${$currentPath.split("/")[1]}"]`);
      if ($ele) {
        $ele.classList.toggle("selected", true);
      }
    },
  });
}

$getAllEle(".dropdown-field").forEach((dropdown) => {
  dropdown.addEventListener("click", invertDropdown);
});
