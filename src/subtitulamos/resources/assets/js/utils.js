/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2021 subtitulamos.tv
 */

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

export function eById(id) {
  return document.getElementById(id);
}

export function onDomReady(callback) {
  // See if DOM is already available
  if (document.readyState === "complete" || document.readyState === "interactive") {
    // call on next available tick
    setTimeout(callback, 1);
  } else {
    document.addEventListener("DOMContentLoaded", callback);
  }
}

export function raiseFetchErrors(response) {
  if (!response.ok) {
    throw {
      error: true,
      response: response,
    };
  }
  return response;
}

export function naiveDeepClone(obj) {
  // A naive deep clone. Doesn't work well with circular references or complex objects
  return JSON.parse(JSON.stringify(obj));
}

export function easyFetch(url, baseOpts) {
  const opts = baseOpts ? naiveDeepClone(baseOpts) : {};
  const method = opts.method ? opts.method.toUpperCase() : "";
  if (method === "POST" && opts.rawBody instanceof Object) {
    opts.body = JSON.stringify(opts.rawBody);
    if (!opts.headers) {
      opts.headers = {};
    }

    if (!opts.headers["Content-Type"]) {
      opts.headers["Content-Type"] = "application/json";
    }
  } else if (method === "POST" && baseOpts.body) {
    opts.body = baseOpts.body; // Copy reference to body, if present
  } else if ((method === "GET" || !method) && opts.params) {
    url += "?" + new URLSearchParams(opts.params).toString();
  }

  return fetch(url, opts).then(raiseFetchErrors);
}

export const $getById = document.getElementById.bind(document);
export const $getEle = document.querySelector.bind(document);
export const $getAllEle = document.querySelectorAll.bind(document);

export function showOverlayWithContentId(contendId) {
  const $overlayContent = document.getElementById(contendId);
  const $parentOverlayWrap = $overlayContent.closest(".overlay-wrap");
  $parentOverlayWrap.classList.toggle("hidden");
}

function getOverlayNode() {
  return document.getElementById("overlay");
}

export function showOverlayFromTpl(tplId) {
  const $template = document.getElementById(tplId);
  if (!$template) {
    console.error("Template with ID", tplId, "doesn't exist");
    return;
  }

  const $node = document.importNode($template.content, true);

  const $overlay = getOverlayNode();
  $overlay.classList.remove("hidden");
  const $overlayContent = document.getElementById("overlay-content");
  $overlayContent.innerHTML = "";
  $overlayContent.appendChild($node);
}

export function closeOverlay(e) {
  const $overlay = getOverlayNode();
  $overlay.classList.add("hidden");
}

export function invertDropdown(e) {
  e.stopPropagation();
  const $dropdown = e.currentTarget.querySelector(".dropdown");
  const $isArrowDown = $dropdown.classList.contains("fa-chevron-down");
  $dropdown.classList.toggle("fa-chevron-down", !$isArrowDown);
  $dropdown.classList.toggle("fa-chevron-up", $isArrowDown);
}

export function invertCheckbox(e) {
  const $checkbox = e.currentTarget;
  $checkbox.classList.toggle("checked");
}

export function invertRadio(e) {
  const $radio = e.currentTarget;
  $radio
    .closest(".radio-field")
    .querySelectorAll(".checked")
    .forEach((radio) => radio.classList.toggle("checked", false));
  $radio.classList.toggle("checked");
}

export function isElementInViewport(el) {
  var rect = el.getBoundingClientRect();

  return (
    rect.top >= 0 &&
    rect.left >= 0 &&
    rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
    rect.right <= (window.innerWidth || document.documentElement.clientWidth)
  );
}

const isFirefox = navigator.userAgent.indexOf("Firefox");
let runningFormValidityTimeout;
export function crossBrowserFormValidityReport($formEle) {
  if (isFirefox) {
    // So Firefox is fun. As of v84, if you doubleclick the report validation event while validation is visible
    // it will consistently hide the validation errors FOREVER. Running validation on a delay fixes this, so...
    if (runningFormValidityTimeout) {
      clearTimeout(runningFormValidityTimeout);
    }

    runningFormValidityTimeout = setTimeout($formEle.reportValidity.bind($formEle), 200);
  } else {
    $formEle.reportValidity();
  }
}
