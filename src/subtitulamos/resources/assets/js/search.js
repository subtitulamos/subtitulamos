/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2020 subtitulamos.tv
 */

import $ from "jquery";

$(function () {
  let $searchBar = $("#search_bar");
  let $searchResults = $("#search-results");
  let searchTimerHandle = null;
  let lastSearchedText = "";
  let linkResultList = [];

  function zeropad(n, width) {
    n = n + "";
    return n.length >= width ? n : new Array(width - n.length + 1).join("0") + n;
  }

  function search() {
    searchTimerHandle = null;

    let q = $searchBar.val();
    if (q == "" || q == lastSearchedText) {
      return;
    }

    lastSearchedText = q;
    linkResultList = [];

    if (q.length < 3) {
      $searchResults.html("").toggleClass("hidden", false);
      $searchResults.append(
        $("<li>")
          .attr("class", "info")
          .html("Sigue escribiendo...")
      );
      return;
    }

    $.ajax({
      url: "/search/query",
      method: "GET",
      data: {
        q: q,
      },
    }).done(function (reply) {
      $searchResults.html("").toggleClass("hidden", false);

      if (reply.length > 0) {
        reply.forEach(function (show) {
          let url = "/shows/" + show.id;
          let $link = $("<a>")
            .attr("href", url)
            .html(show.name);
          let $result = $("<li>").append($link);
          $searchResults.append($result);
          linkResultList.push(url);

          if (show.episodes) {
            show.episodes.forEach(function (ep) {
              let epURL = "/episodes/" + ep.id;
              $link = $("<a>")
                .attr("href", epURL)
                .html(show.name + " - " + ep.season + "x" + zeropad(ep.number, 2) + " " + ep.name);
              $result = $("<li>").append($link);
              $searchResults.append($result);

              linkResultList.push(epURL);
            });
          }
        });
      } else {
        $searchResults.append(
          $("<li>")
            .attr("class", "info")
            .html("Parece que no tenemos resultados para esta búsqueda")
        );
      }
    });
  }

  $searchBar.on("keyup", function (e) {
    if (e.which == 13 && !searchTimerHandle && linkResultList.length > 0) {
      window.location = linkResultList[0];
      e.preventDefault();
    }

    if (searchTimerHandle) {
      clearTimeout(searchTimerHandle);
    }

    searchTimerHandle = setTimeout(search, 200);
  });

  let hideTimeoutHandle = null;
  $searchBar.on("focusin", function () {
    clearTimeout(hideTimeoutHandle);
    $searchResults.toggleClass("hidden", $searchBar.val() == "");
  });
  $searchBar.on("focusout", function () {
    hideTimeoutHandle = setTimeout(function () {
      $searchResults.toggleClass("hidden", true);
    }, 500);
  });
  $searchBar.parent().on("submit", function (e) {
    e.preventDefault();
  });
});
