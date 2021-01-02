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
  invertCheckbox,
  $getById,
  invertRadio,
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
  const form = e.target;

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
            console.log();
            const $target = form.querySelector(`[name='${e[0]}']`);
            if ($target) {
              $target.setCustomValidity(e[1]);
              ++validitySet;
            } else {
              console.error("BAD ERROR", form, $target, e); // FIXME: Drop this logic (shouldnt be necessary)
            }
          });

          if (!validitySet) {
            throw "Couldn't set any validity message, but reg failed anwyay";
          }

          // Force submit again to trigger the custom validation
          form.querySelector("[type=submit]").click();
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
  $registerForm.addEventListener("submit", doRegister);

  $getAllEle(".checkbox").forEach((checkbox) => {
    checkbox.addEventListener("click", invertCheckbox);
  });
}

onDomReady(function () {
  const $loginBtn = document.getElementById("login");
  if ($loginBtn) {
    $loginBtn.addEventListener("click", showLoginForm);
  }

  const $registerBtn = document.getElementById("register");
  if ($registerBtn) {
    $registerBtn.addEventListener("click", showRegisterForm);
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
  $getEle("#control-panel-minimize-toggle").addEventListener("click", () =>
    togglePanelStatus(true /* save status */)
  );

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
