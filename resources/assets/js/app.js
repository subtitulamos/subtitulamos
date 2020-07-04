/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2020 subtitulamos.tv
 */

import $ from "jquery";
import './search';

export function dateDiff(a, b) {
  let utcA = Date.UTC(
    a.getFullYear(),
    a.getMonth(),
    a.getDate(),
    a.getHours(),
    a.getMinutes(),
    a.getSeconds()
  );
  let utcB = Date.UTC(
    b.getFullYear(),
    b.getMonth(),
    b.getDate(),
    b.getHours(),
    b.getMinutes(),
    b.getSeconds()
  );

  return Math.floor(utcB - utcA);
}

function toggleAccessForm() {
  let $this = $(this);
  let formType = $this.attr("id");
  let $loginForm = $("#login-form");
  let $loginRegistry = $("#header-popup-wrapper");
  let $regForm = $("#register-form");
  let $fadingPan = $("#fade-pan");

  $fadingPan.toggleClass("hidden", false);

  if (formType == "login") {
    //if Login Form is open and you click on Iniciar Sesion on navigation bar -- close it
    if (!$loginForm.hasClass("hidden") && !$loginRegistry.hasClass("hidden")) {
      $loginRegistry.toggleClass("bounce", false);
      $loginRegistry.toggleClass("bounce_back", true);
      setTimeout(function () {
        $loginRegistry.toggleClass("hidden", true);
        $loginRegistry.toggleClass("bounce", true);
        $loginRegistry.toggleClass("bounce_back", false);
      }, 180);
    }
    //if Login Form is closed, just open it
    else if ($loginRegistry.hasClass("hidden")) {
      $loginRegistry.toggleClass("hidden", false);
      $regForm.toggleClass("hidden", true);
      $loginForm.toggleClass("hidden", false);
    }
    //if Register Form is open and you click on Iniciar Sesion on navigation bar
    else {
      $regForm.toggleClass("sendleft_remove", true);
      setTimeout(function () {
        $regForm.toggleClass("hidden", true);
        $loginForm.toggleClass("hidden", false);
        $loginForm.toggleClass("sendleft", true);
      }, 250);
      setTimeout(function () {
        $regForm.toggleClass("sendleft_remove", false);
        $loginForm.toggleClass("sendleft", false);
      }, 400);
    }
  } else if (formType == "register") {
    //if Register Form is open and you click on Registro on navigation bar -- close it
    if (!$regForm.hasClass("hidden") && !$loginRegistry.hasClass("hidden")) {
      $loginRegistry.toggleClass("bounce", false);
      $loginRegistry.toggleClass("bounce_back", true);
      setTimeout(function () {
        $loginRegistry.toggleClass("hidden", true);
        $loginRegistry.toggleClass("bounce", true);
        $loginRegistry.toggleClass("bounce_back", false);
      }, 180);
    }
    //if Login Form is closed, just open it
    else if ($loginRegistry.hasClass("hidden")) {
      $loginForm.toggleClass("hidden", true);
      $loginRegistry.toggleClass("hidden", false);
      $regForm.toggleClass("hidden", false);
    }
    //if Login Form is open and you click on Registro on navigation bar
    else {
      $loginForm.toggleClass("sendleft_remove", true);
      setTimeout(function () {
        $loginForm.toggleClass("hidden", true);
        $regForm.toggleClass("hidden", false);
        $regForm.toggleClass("sendleft", true);
      }, 250);
      setTimeout(function () {
        $loginForm.toggleClass("sendleft_remove", false);
        $regForm.toggleClass("sendleft", false);
      }, 400);
    }
  }
}

function closeLogRegForm() {
  let $loginRegistry = $("#header-popup-wrapper");
  let $fadingPan = $("#fade-pan");

  $fadingPan.toggleClass("fade_out", true);
  setTimeout(function () {
    $fadingPan.toggleClass("hidden", true);
    $fadingPan.toggleClass("fade_out", false);
  }, 580);

  if ($loginRegistry.hasClass("hidden")) {
    $loginRegistry.toggleClass("hidden", false);
  } else {
    $loginRegistry.toggleClass("bounce", false);
    $loginRegistry.toggleClass("bounce_back", true);
    setTimeout(function () {
      $loginRegistry.toggleClass("hidden", true);
      $loginRegistry.toggleClass("bounce", true);
      $loginRegistry.toggleClass("bounce_back", false);
    }, 380);
  }
}

function doLogin(e) {
  e.preventDefault();

  const $loginError = $("#login-error");
  const $pwdField = $("#login_password");
  let username = $("#login_username")
    .val()
    .trim();
  let pwd = $pwdField.val();

  if (!username.length || !pwd.length) {
    $loginError.toggleClass("hidden", false);
    $pwdField.toggleClass("is-danger", true);
    $loginError.html("Ni el usuario ni la contraseña pueden estar vacíos");
    return;
  }

  const $loginBtn = $("#login-button");
  $loginBtn.toggleClass("is-loading", true);
  $loginError.html(""); // Clear previous errors, just so it's clearer they're new

  // Login the user via ajax
  $.ajax({
    url: "/login",
    method: "post",
    data: {
      username: username,
      password: pwd,
      remember: $("#login_remember_me").is(":checked"),
    },
  })
    .done(function () {
      window.location.reload(true);
    })
    .fail(function (data) {
      $loginBtn.toggleClass("is-loading", false);
      $pwdField.toggleClass("is-danger", true);
      $loginError.toggleClass("hidden", false);
      try {
        let d = JSON.parse(data.responseText);
        $loginError.html(d[0]);
      } catch (e) {
        $loginError.html("Error desconocido al intentar acceder. Por favor, inténtalo de nuevo.");
      }
    });
}

function register(e) {
  e.preventDefault();

  // Mark button as loading
  const $regButton = $("#register-button");
  $regButton.toggleClass("is-loading", true);

  // Clean up old errors
  $("#register-form")
    .find(".is-danger")
    .toggleClass("is-danger");

  $("#register-form")
    .find("[data-reg-error]")
    .remove();

  // Login the user via ajax
  $.ajax({
    url: "/register",
    method: "post",
    data: {
      username: $("#reg_username").val(),
      password: $("#reg_password").val(),
      password_confirmation: $("#reg_password_confirmation").val(),
      email: $("#reg_email").val(),
      terms: $("#reg_terms").is(":checked"),
    },
  })
    .done(function () {
      window.location.reload(true);
    })
    .fail(function (data) {
      $regButton.toggleClass("is-loading", false);

      let d;
      try {
        d = JSON.parse(data.responseText);
      } catch (e) {
        alert(
          "Error desconocido al intentar completar el registro. Por favor, inténtalo de nuevo."
        );
      }

      for (let err of d) {
        Object.keys(err).forEach(field => {
          const $field = $("#reg_" + field);
          console.log(field, $field);
          $field.toggleClass("is-danger", true);
          $field
            .parent()
            .parent()
            .append("<p class='help is-danger' data-reg-error=''>" + err[field] + "</p>");
        });
      }
    });
}

$(function () {
  $("#close_logreg_form, #fade-pan").on("click", function () {
    closeLogRegForm();
  });
  $("#login, #register").on("click", toggleAccessForm);
  $("#login-form").on("submit", doLogin);
  $("#register-form").on("submit", register);

  if (window.openLogin) {
    setTimeout(toggleAccessForm.bind($("#login")), 1500);
  }
});
