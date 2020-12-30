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
  let selectedElementIdx;

  function updateSelected($searchBar, offset) {
    const $searchResults = $getById($searchBar.dataset.searchBarTarget);
    const $listItems = $searchResults.querySelectorAll("li");
    let currentSelectedIdx = null;

    for (let idx = 0; idx < $listItems.length; idx++) {
      if ($listItems[idx].classList.contains("selected")) {
        currentSelectedIdx = idx;
      }
    }

    if (currentSelectedIdx === null) {
      $listItems[0].classList.toggle("selected");
      selectedElementIdx = 0;
    } else {
      $listItems[currentSelectedIdx].classList.toggle("selected");

      if (currentSelectedIdx === 0 && offset === -1) {
        $listItems[$listItems.length - 1].classList.toggle("selected");
        $listItems[$listItems.length - 1].scrollIntoView({
          behavior: "smooth",
          block: "end",
          inline: "nearest",
        });
        selectedElementIdx = $listItems.length - 1;
      } else if (currentSelectedIdx === $listItems.length - 1 && offset === 1) {
        $listItems[0].classList.toggle("selected");
        $listItems[0].scrollIntoView({ behavior: "smooth", block: "end", inline: "nearest" });
        selectedElementIdx = 0;
      } else {
        $listItems[currentSelectedIdx + offset].classList.toggle("selected");
        $listItems[currentSelectedIdx + offset].scrollIntoView({
          behavior: "smooth",
          block: "end",
          inline: "nearest",
        });
        selectedElementIdx = currentSelectedIdx + offset;
      }
    }
  }

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
      if (e.key === "ArrowDown") {
        updateSelected($searchBar, 1);
      } else if (e.key === "ArrowUp") {
        updateSelected($searchBar, -1);
      }

      if (e.which == 13 && !searchTimerHandle && linkResultList.length > 0) {
        window.location = linkResultList[selectedElementIdx];
        e.preventDefault();
      }

      if (searchTimerHandle) {
        clearTimeout(searchTimerHandle);
      }

      searchTimerHandle = setTimeout(() => search($searchBar), 200);
    });

    $searchBar.closest(".search-bar-container").addEventListener("click", (e) => {
      e.stopPropagation();
      const $searchContainer = e.currentTarget;
      const $searchResults = $getById($searchBar.dataset.searchBarTarget);
      if ($searchBar.value !== "") {
        $searchResults.classList.toggle("hidden", false);
      }

      document.addEventListener("click", (e) => {
        e.stopPropagation();
        if (e.currentTarget !== $searchContainer) {
          if ($searchBar.value !== "") {
            $searchResults.classList.toggle("hidden", true);
          }
        }
      });
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
