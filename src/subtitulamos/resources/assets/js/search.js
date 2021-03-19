/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2021 subtitulamos.tv
 */

import { onDomReady, easyFetch, $getById } from "./utils";

let selectedElementIdx = null;

function createResultRow(contents, index) {
  const $row = document.createElement("li");
  selectedElementIdx = selectedElementIdx === null ? 0 : selectedElementIdx;
  $row.classList.toggle("selected", index === selectedElementIdx);
  if (contents instanceof HTMLElement) {
    $row.append(contents);
  } else {
    $row.innerHTML = contents;
  }
  return $row;
}

onDomReady(function () {
  let $searchBars = document.querySelectorAll("[data-search-bar-target]");
  let searchTimerHandle = null;
  let lastSearchedText = "";
  let linkResultList = [];

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

    if (searchQuery === lastSearchedText) {
      return;
    }

    linkResultList = [];
    lastSearchedText = searchQuery;

    if (searchQuery === "") {
      selectedElementIdx = null;
      $searchResults.classList.toggle("hidden", true);
      return;
    } else if (searchQuery.length < 3) {
      $searchResults.innerHTML = "";
      $searchResults.classList.toggle("hidden", false);
      $searchResults.append(createResultRow("Sigue escribiendo..."));
      selectedElementIdx = null;
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
          reply.forEach(function (result, index) {
            const showUrl = "/shows/" + result.show_id;
            let $link = document.createElement("a");
            $link.href = showUrl;
            $link.innerHTML = result.show_name;

            $searchResults.append(createResultRow($link, index));
            linkResultList.push(showUrl);
          });
        } else {
          selectedElementIdx = null;
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
      if ($searchBar.value !== "") {
        if (e.key === "ArrowDown") {
          updateSelected($searchBar, 1);
        } else if (e.key === "ArrowUp") {
          updateSelected($searchBar, -1);
        }

        if (e.key == "Enter" && !searchTimerHandle && linkResultList.length > 0) {
          window.location = linkResultList[selectedElementIdx];
          e.preventDefault();
        }
      }

      if (searchTimerHandle) {
        clearTimeout(searchTimerHandle);
      }
      searchTimerHandle = setTimeout(() => search($searchBar), 50);
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
});
