import $ from "jquery";
import '../../css/panel/panel_alerts.css';

$(() => {
  $(".unhide-alert").on("click", function () {
    let $this = $(this);
    let $cardBody = $this.parents(".card").find(".card-content");
    let $icon = $this.children("i");
    $cardBody.toggleClass("hidden");
    $icon.toggleClass("fa-chevron-down fa-chevron-up");
  });
});
