/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2020 subtitulamos.tv
 */
import "./search";
import { onDomReady, easyFetch } from "./utils";

function toggleAccessForm() {
  const formType = this.id;
  const $loginForm = document.getElementById("login-form");
  const $loginRegistry = document.getElementById("header-popup-wrapper");
  const $fadingPan = document.getElementById("fade-pan");
  const $regForm = document.getElementById("register-form");

  $fadingPan.classList.toggle("hidden", false);

  if (formType == "login") {
    //if Login Form is open and you click on Iniciar Sesion on navigation bar -- close it
    if (!$loginForm.classList.contains("hidden") && !$loginRegistry.classList.contains("hidden")) {
      $loginRegistry.classList.toggle("bounce", false);
      $loginRegistry.classList.toggle("bounce_back", true);
      setTimeout(() => {
        $loginRegistry.classList.toggle("hidden", true);
        $loginRegistry.classList.toggle("bounce", true);
        $loginRegistry.classList.toggle("bounce_back", false);
      }, 180);
    }
    //if Login Form is closed, just open it
    else if ($loginRegistry.classList.contains("hidden")) {
      $loginRegistry.classList.toggle("hidden", false);
      $regForm.classList.toggle("hidden", true);
      $loginForm.classList.toggle("hidden", false);
    }
    //if Register Form is open and you click on Iniciar Sesion on navigation bar
    else {
      $regForm.classList.toggle("sendleft_remove", true);
      setTimeout(function () {
        $regForm.classList.toggle("hidden", true);
        $loginForm.classList.toggle("hidden", false);
        $loginForm.classList.toggle("sendleft", true);
      }, 250);
      setTimeout(function () {
        $regForm.classList.toggle("sendleft_remove", false);
        $loginForm.classList.toggle("sendleft", false);
      }, 400);
    }
  } else if (formType == "register") {
    //if Register Form is open and you click on Registro on navigation bar -- close it
    if (!$regForm.classList.contains("hidden") && !$loginRegistry.classList.contains("hidden")) {
      $loginRegistry.classList.toggle("bounce", false);
      $loginRegistry.classList.toggle("bounce_back", true);
      setTimeout(function () {
        $loginRegistry.classList.toggle("hidden", true);
        $loginRegistry.classList.toggle("bounce", true);
        $loginRegistry.classList.toggle("bounce_back", false);
      }, 180);
    }
    //if Login Form is closed, just open it
    else if ($loginRegistry.classList.contains("hidden")) {
      $loginForm.classList.toggle("hidden", true);
      $loginRegistry.classList.toggle("hidden", false);
      $regForm.classList.toggle("hidden", false);
    }
    //if Login Form is open and you click on Registro on navigation bar
    else {
      $loginForm.classList.toggle("sendleft_remove", true);
      setTimeout(function () {
        $loginForm.classList.toggle("hidden", true);
        $regForm.classList.toggle("hidden", false);
        $regForm.classList.toggle("sendleft", true);
      }, 250);
      setTimeout(function () {
        $loginForm.classList.toggle("sendleft_remove", false);
        $regForm.classList.toggle("sendleft", false);
      }, 400);
    }
  }
}

function closeLogRegForm() {
  let $loginRegistry = document.getElementById("header-popup-wrapper");
  let $fadingPan = document.getElementById("fade-pan");

  $fadingPan.classList.toggle("fade_out", true);
  setTimeout(function () {
    $fadingPan.classList.toggle("hidden", true);
    $fadingPan.classList.toggle("fade_out", false);
  }, 580);

  if ($loginRegistry.classList.contains("hidden")) {
    $loginRegistry.classList.toggle("hidden", false);
  } else {
    $loginRegistry.classList.toggle("bounce", false);
    $loginRegistry.classList.toggle("bounce_back", true);
    setTimeout(function () {
      $loginRegistry.classList.toggle("hidden", true);
      $loginRegistry.classList.toggle("bounce", true);
      $loginRegistry.classList.toggle("bounce_back", false);
    }, 380);
  }
}

function doLogin(e) {
  e.preventDefault();

  const $loginError = document.getElementById("login-error");
  const $pwdField = document.getElementById("login_password");
  const username = document.getElementById("login_username").value.trim();
  const pwd = $pwdField.value;

  if (!username.length || !pwd.length) {
    $loginError.classList.toggle("hidden", false);
    $pwdField.classList.toggle("is-danger", true);
    $loginError.innerHTML = "Ni el usuario ni la contraseña pueden estar vacíos";
    return;
  }

  const $loginBtn = document.getElementById("login-button");
  $loginBtn.classList.toggle("is-loading", true);
  $loginError.innerHTML = ""; // Clear previous errors, just so it's clearer they're new

  // Login the user via ajax
  easyFetch("/login", {
    method: "post",
    rawBody: {
      username: username,
      password: pwd,
      remember: document.getElementById("login_remember_me").checked,
    },
  })
    .then(function () {
      window.location.reload(true);
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

onDomReady(function () {
  document
    .querySelectorAll("#close_logreg_form, #fade-pan")
    .forEach(($ele) => $ele.addEventListener("click", closeLogRegForm));
  document
    .querySelectorAll("#login, #register")
    .forEach(($ele) => $ele.addEventListener("click", toggleAccessForm));
  const $loginForm = document.getElementById("login-form");
  const $registerForm = document.getElementById("register-form");
  if ($loginForm) {
    $loginForm.addEventListener("submit", doLogin);
  }

  if ($registerForm) {
    $registerForm.addEventListener("submit", register);
  }

  if (window.openLogin) {
    setTimeout(toggleAccessForm.bind(document.getElementById("login")), 1500);
  }
});

import Vue from "vue";

new Vue({
  el: "#control-panel",
  data: {
    isOpen: true,
  },
  methods: {
    updateControlPanelStatus: async function () {
      this.isOpen = !this.isOpen;
      this.updateElementsStatus();
    },
    updateElementsStatus: function () {
      this.$el.classList.toggle("open", this.isOpen);
      document
        .getElementById("page-container")
        .classList.toggle("control-panel-is-open", this.isOpen);
      document
        .getElementById("page-container")
        .classList.toggle("control-panel-is-closed", !this.isOpen);
    },
    focusSearchInput: function () {
      await this.updateControlPanelStatus();
      document.querySelector("#control-panel #control-panel-search input").focus();
    },
  },
  mounted() {
    this.updateElementsStatus();
  },
});
