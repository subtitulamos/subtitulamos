/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2020 subtitulamos.tv
 */

import { onDomReady, easyFetch, $getById } from "./utils";

function createResultRow(contents) {
  const $row = document.createElement("li");
  if (contents instanceof HTMLElement) {
    $row.append(contents);
  } else {
    $row.classList.add("info");
    $row.innerHTML = contents;
  }
  return $row;
}

onDomReady(function () {
  let $searchBars = document.querySelectorAll("[data-search-bar-target]");
  let searchTimerHandle = null;
  let lastSearchedText = "";
  let linkResultList = [];

  function search($searchBar) {
    const $searchResults = $getById($searchBar.dataset.searchBarTarget);
    searchTimerHandle = null;

    let searchQuery = $searchBar.value;
    if (searchQuery === "" || searchQuery === lastSearchedText) {
      return;
    }

    lastSearchedText = searchQuery;
    linkResultList = [];

    if (searchQuery.length < 3) {
      $searchResults.innerHTML = "";
      $searchResults.classList.toggle("hidden", false);
      $searchResults.append(createResultRow("Sigue escribiendo..."));
      return;
    }

    easyFetch("/search/query", {
      params: {
        q: searchQuery,
      },
    })
      .then((response) => response.json())
      .then(function (reply) {
        $searchResults.innerHTML = "";
        $searchResults.classList.toggle("hidden", false);

        if (reply.length > 0) {
          reply.forEach(function (show) {
            const showUrl = "/shows/" + show.id;
            let $link = document.createElement("a");
            $link.href = showUrl;
            $link.innerHTML = show.name;

            $searchResults.append(createResultRow($link));
            linkResultList.push(showUrl);
          });
        } else {
          $searchResults.append(
            createResultRow("Parece que no tenemos resultados para esta búsqueda")
          );
        }
      })
      .catch(() => {
        $searchResults.innerHTML = "";
        $searchResults.classList.toggle("hidden", false);
        $searchResults.append(
          createResultRow("Ha ocurrido un error durante la búsqueda. Por favor, inténtalo de nuevo")
        );
      });
  }

  for (const $searchBar of $searchBars) {
    $searchBar.addEventListener("keyup", function (e) {
      if (e.which == 13 && !searchTimerHandle && linkResultList.length > 0) {
        window.location = linkResultList[0];
        e.preventDefault();
      }

      if (searchTimerHandle) {
        clearTimeout(searchTimerHandle);
      }

      searchTimerHandle = setTimeout(() => search($searchBar), 200);
    });

    $searchBar.addEventListener("blur", (e) => {
      const $searchResults = $getById($searchBar.dataset.searchBarTarget);
      $searchResults.classList.toggle("hidden", true);
    });

    $searchBar.addEventListener("focus", (e) => {
      const $searchResults = $getById($searchBar.dataset.searchBarTarget);
      $searchResults.classList.toggle("hidden", false);
    });
  }

  // let hideTimeoutHandle = null;
  // $searchBar.addEventListener("focusin", function () {
  //   clearTimeout(hideTimeoutHandle);
  //   $searchResults.classList.toggle("hidden", $searchBar.value == "");
  // });
  // $searchBar.addEventListener("focusout", function () {
  //   hideTimeoutHandle = setTimeout(function () {
  //     $searchResults.classList.toggle("hidden", true);
  //   }, 500);
  // });
  // $searchBar.parentElement.addEventListener("submit", function (e) {
  //   e.preventDefault(); // Prevent form submit
  // });
});
