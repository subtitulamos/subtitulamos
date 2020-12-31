import { onDomReady } from "../utils";

onDomReady(() => {
  document.querySelectorAll(".card-header").forEach(($alertCollapsible) => {
    $alertCollapsible.addEventListener("click", function () {
      let $cardBody = this.parentNode.querySelector(".card-content");
      let $icon = this.querySelector(".dropdown");
      $cardBody.classList.toggle("expanded");
      $icon.classList.toggle("fa-chevron-down");
      $icon.classList.toggle("fa-chevron-up");
    });
  });
});
