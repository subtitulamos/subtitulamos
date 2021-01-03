/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2021 subtitulamos.tv
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
  invertCheckbox,
  $getById,
  invertRadio,
  crossBrowserFormValidityReport,
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

function doRegister(e) {
  e.preventDefault();
  const form = this.parentElement;
  if (!form.checkValidity()) {
    crossBrowserFormValidityReport(form);
    return;
  }

  // Mark button as loading
  const $regButton = document.getElementById("register-button");
  $regButton.classList.toggle("is-loading", true);

  // Clean up old errors
  document
    .querySelectorAll("#register-form .is-danger")
    .forEach(($ele) => $ele.classList.toggle("is-danger"));
  document.querySelectorAll("#register-form [data-reg-error]").forEach(($ele) => $ele.remove());

  // Register via AJAX
  easyFetch("/register", {
    method: "post",
    body: new FormData(form), // Urlencoded form values
  })
    .then(function () {
      window.location.reload();
    })
    .catch((err) => {
      $regButton.classList.toggle("is-loading", false);

      err.response
        .json()
        .then((cleanErrList) => {
          let validitySet = 0;
          cleanErrList.forEach(function (e, idx, arr) {
            const $target = form.querySelector(`[name='${e[0]}']`);
            if ($target) {
              $target.setCustomValidity(e[1]);
            }
            ++validitySet;
          });

          if (!validitySet) {
            return; // FIXME
            throw "Couldn't set any validity message, but reg failed!";
          }

          crossBrowserFormValidityReport(form);
        })
        .catch((e) => {
          console.error(e);
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
  setTimeout(() => document.getElementById("username").focus(), 500);
}

function showRegisterForm() {
  showOverlayFromTpl("tpl-register");

  const $registerForm = document.getElementById("register-form");
  $registerForm.querySelector("#register-button").addEventListener("click", doRegister);

  $getAllEle(".checkbox").forEach((checkbox) => {
    checkbox.addEventListener("click", invertCheckbox);
  });

  const clearValidity = (e) => e.target.setCustomValidity(""); // Reset error, user might've fixed it
  const validityChecker = (e) => {
    clearValidity(e);
    if (e.key == "Enter") {
      $registerForm.querySelector("[type=submit]").click();
    }
  };
  $registerForm.querySelectorAll("input, select").forEach(($ele) => {
    $ele.addEventListener("change", clearValidity);
    $ele.addEventListener("keydown", validityChecker);
  });

  const checkPwdValidity = () => {
    let err = "";
    const $pwdConfirm = $registerForm.querySelector("[name=password-confirmation]");
    const $pwd = $registerForm.querySelector("[name=password]");
    if ($pwdConfirm.value && $pwdConfirm.value != $pwd.value) {
      err = "Las contraseñas no coinciden";
    }

    $pwdConfirm.setCustomValidity(err);
  };
  $registerForm.querySelectorAll("[name^=password]").forEach(($ele) => {
    $ele.removeEventListener("keydown", validityChecker); // Make sure we don't use the base clear validity
    $ele.addEventListener("keyup", checkPwdValidity);
    $ele.addEventListener("blur", checkPwdValidity);
  });
}

onDomReady(function () {
  const $loginBtn = document.getElementById("login");
  const $loginBtnControlPanel = document.getElementById("login-cp");
  if ($loginBtn) {
    $loginBtn.addEventListener("click", showLoginForm);
  }

  if ($loginBtnControlPanel) {
    $loginBtnControlPanel.addEventListener("click", showLoginForm);
  }

  const $registerBtnControlPanel = document.getElementById("register");
  const $registerBtn = document.getElementById("register-cp");
  if ($registerBtn) {
    $registerBtn.addEventListener("click", showRegisterForm);
  }

  if ($registerBtnControlPanel) {
    $registerBtnControlPanel.addEventListener("click", showRegisterForm);
  }

  // Set up overlay logic
  const $overlayClose = document.getElementById("overlay-close");
  const $overlayFade = document.getElementById("overlay-fade");
  $overlayClose.addEventListener("click", closeOverlay);
  $overlayFade.addEventListener("click", closeOverlay);
});

const $ctrlPanel = document.getElementById("control-panel");
const $pageContainer = $getById("page-container");
if ($ctrlPanel) {
  let openStatus = localStorage.getItem("menu-open") === "true";

  $ctrlPanel.classList.toggle("opening", !openStatus);

  const updateDomWithStatus = () => {
    $pageContainer.classList.toggle("control-panel-is-open", openStatus);
    $pageContainer.classList.toggle("control-panel-is-closed", !openStatus);

    $ctrlPanel.classList.toggle("open", openStatus);

    const $currentPath = window.location.pathname;
    const $ele = $getEle(`a[page="/${$currentPath.split("/")[1]}"]`);
    if ($ele) {
      $ele.classList.toggle("selected", true);
    }
    const $subEle = $getEle(`.control-panel-sub-section > a[href="${$currentPath}"]`);
    if ($subEle) {
      $subEle.classList.toggle("selected", true);
    }
  };

  const togglePanelStatus = (save) => {
    $pageContainer.classList.toggle("opening", true);
    $ctrlPanel.classList.toggle("opening", true);
    openStatus = !openStatus;
    if (save) {
      localStorage.setItem("menu-open", openStatus);
    }
    updateDomWithStatus();
  };

  updateDomWithStatus(); // Update on load, make sure local prefs are respected
  $getAllEle(".control-panel-minimize-toggle").forEach(($toggle) => {
    $toggle.addEventListener("click", () => {
      togglePanelStatus(true /* save status */);
    });
  });

  $getEle("#search-icon").addEventListener("click", () => {
    if (!openStatus) {
      togglePanelStatus(false /* dont save status */);
    }
    setTimeout(() => $getEle("#search-input").focus(), 200);
  });
}

$getAllEle(".dropdown-field").forEach((dropdown) => {
  dropdown.addEventListener("click", invertDropdown);
});

$getAllEle(".checkbox").forEach((checkbox) => {
  checkbox.addEventListener("click", invertCheckbox);
});

$getAllEle(".radio").forEach((checkbox) => {
  checkbox.addEventListener("click", invertRadio);
});

$getAllEle(".global-alert .close-alert").forEach(($button) => {
  const $alert = $button.closest(".global-alert");
  $button.addEventListener("click", () => {
    $alert.classList.toggle("dismissing", true);
    setTimeout(() => $alert.remove(), 450);
  });
});
