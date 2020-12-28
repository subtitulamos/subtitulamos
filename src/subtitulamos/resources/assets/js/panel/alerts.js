import "../../css/panel/panel_alerts.css";
import { onDomReady } from "../utils";

onDomReady(() => {
  document.querySelectorAll(".unhide-alert").forEach(($alertCollapsible) => {
    $alertCollapsible.addEventListener("click", function () {
      let $cardBody = this.parentNode.parentNode.querySelector(".card-content");
      let $icon = this.querySelector("i");
      $cardBody.classList.toggle("hidden");
      $icon.classList.toggle("fa-chevron-down");
      $icon.classList.toggle("fa-chevron-up");
    });
  });
});
