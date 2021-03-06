/**
 * This file is covered by the AGPLv3 license, which can be found at the LICENSE file in the root of this project.
 * @copyright 2021 subtitulamos.tv
 */

import { dateDiff, easyFetch, $getEle, $getAllEle, $getById } from "./utils.js";
import timeago from "timeago.js";
import "../css/index.scss";

const INITIAL_CARD_LOAD_SIZE = 15;
const SUBSEQUENT_LOAD_SIZE = 7;
const START_PRELOADING_WHEN_N_CARDS_AWAY = 7;
let curTab = "uploads"; // Default tab
let subsByTab = {};

function loadTabData(target, startIdx, count) {
  if (subsByTab[target].loading) {
    return;
  }

  // Mark as loading so we don't do accidental parallel loads from the events
  subsByTab[target].loading = true;

  // Prefill with some loading cards
  subsByTab[target].maxCardIdx += count;
  addEpisodes(target, startIdx, count);

  let loadSucceeded = false;
  easyFetch("/search/" + target, {
    params: {
      from: startIdx,
      count: count,
    },
  })
    .then((res) => res.json())
    .then((data) => {
      if (data.length < count) {
        // Got less data than we requested - we've reached the end
        if (!subsByTab[target].endFound) {
          subsByTab[target].maxCardIdx = startIdx + data.length - 1;
        }

        subsByTab[target].endFound = true;

        for (let i = startIdx + data.length; i < startIdx + count; ++i) {
          subsByTab[target].$episodes[i].remove();
        }

        subsByTab[target].$episodes.splice(startIdx + data.length);
      }

      data.forEach(function (ep, idx) {
        ep.time_ago = 0;
        ep.time_unit = "sec";

        const i = startIdx + idx;
        const $card = subsByTab[target].$episodes[i];
        $card.innerHTML = $card.innerHTML.replace("{ep_timeago}", timeago().format(ep.time, "es"));
        $card.innerHTML = $card.innerHTML.replace("{ep_show}", ep.show);
        $card.innerHTML = $card.innerHTML.replace("{ep_season}", ep.season);
        $card.innerHTML = $card.innerHTML.replace("{ep_num}", ep.episode_num);
        $card.innerHTML = $card.innerHTML.replace("{ep_name}", ep.name);
        $card.innerHTML = $card.innerHTML.replace("{ep_url}", `/episodes/${ep.id}/${ep.slug}`);
        $card.querySelector(".metadata").classList.toggle("hidden", false);
        $card.querySelector(".loading").classList.toggle("hidden", true);
      });

      loadSucceeded = true;
    })
    .finally(() => {
      subsByTab[target].loading = false;

      if (loadSucceeded) {
        // If highest card is still visible (and we didn't change tabs!), continue loading
        if (target == curTab) {
          const [_, highestVisibleIdx] = getVisibleCardIndexes();
          if (
            subsByTab[target].maxCardIdx > 0 &&
            highestVisibleIdx > subsByTab[target].maxCardIdx - START_PRELOADING_WHEN_N_CARDS_AWAY &&
            !subsByTab[target].endFound
          ) {
            loadTab(target, /* loadMore */ true);
          }
        }
      }
    });
}

function getVisibleCardIndexes() {
  let highestVisibleIdx = 0;
  let smallestVisibleIdx = Infinity;
  for (let card of visibleCards) {
    smallestVisibleIdx = Math.min(smallestVisibleIdx, Number(card.dataset.idx));
    highestVisibleIdx = Math.max(highestVisibleIdx, Number(card.dataset.idx));
  }

  return [smallestVisibleIdx, highestVisibleIdx];
}

function updateNavigationArrowsVisibility(target) {
  let [smallestVisibleIdx, highestVisibleIdx] = getVisibleCardIndexes();

  const isFirstCardVisible = smallestVisibleIdx === 0 || smallestVisibleIdx === Infinity;
  const isLastCardVisible =
    highestVisibleIdx === subsByTab[target].maxCardIdx && subsByTab[target].endFound;
  $getEle("#category-container").classList.toggle("first-page", isFirstCardVisible);
  $getEle("#category-container").classList.toggle("last-page", isLastCardVisible);
}

const $subtitleCardsWrap = $getById("subtitle-cards-wrap");
let visibleCards = [];
let observer = new IntersectionObserver(
  function (events) {
    for (let ev of events) {
      if (ev.isIntersecting) {
        visibleCards.push(ev.target);
      } else {
        visibleCards = visibleCards.filter((card) => card !== ev.target);
      }
    }

    updateNavigationArrowsVisibility(curTab);
    const [_, highestVisibleIdx] = getVisibleCardIndexes();
    if (
      highestVisibleIdx >= subsByTab[curTab].maxCardIdx - START_PRELOADING_WHEN_N_CARDS_AWAY &&
      !subsByTab[curTab].endFound
    ) {
      loadTab(curTab, /* loadMore */ true);
    }
  },
  {
    rootMargin: "0px",
    threshold: 0.9,
  }
);

const $epTemplate = $getById("subtitle-card");
export function addEpisodes(target, startIdx, count) {
  for (let i = startIdx; i < startIdx + count; ++i) {
    const $node = document.importNode($epTemplate.content, true);
    const $targetDiv = $node.children[0];
    $targetDiv.dataset.idx = i;
    subsByTab[target].$episodes[i] = $targetDiv;
    observer.observe($targetDiv);
    $subtitleCardsWrap.appendChild($node);
  }
}

function loadTab(target, loadMore) {
  if (!subsByTab[target]) {
    subsByTab[target] = {
      $episodes: [],
      maxCardIdx: -1,
      endFound: false,
      loading: false,
      scrollPos: 0,
    };

    loadMore = true;
  }

  if (curTab != target) {
    // Stop observing
    observer.disconnect();

    // Reset scroll to last known scroll for this tab
    subsByTab[curTab].scrollPos = $subtitleCardsWrap.scrollLeft;
    let setScrollTo = subsByTab[target].scrollPos;

    // Do the actual swap, (re)introduce childs to DOM
    curTab = target;

    visibleCards = [];
    $subtitleCardsWrap.innerHTML = "";
    for (let $ep of subsByTab[target].$episodes) {
      $subtitleCardsWrap.appendChild($ep);
      observer.observe($ep);
    }

    // Needs to be after all the subtitles have been (re)added to DOM
    $subtitleCardsWrap.scrollLeft = setScrollTo;
  }

  updateNavigationArrowsVisibility(target);

  if (loadMore) {
    const curLoadCount = subsByTab[target].$episodes.length;
    loadTabData(
      target,
      curLoadCount,
      curLoadCount > 0 ? SUBSEQUENT_LOAD_SIZE : INITIAL_CARD_LOAD_SIZE
    );
  }
}

$getEle("#previous-page").addEventListener("click", function () {
  const firstCardInVisibleStack = visibleCards[0];
  const lastCardInVisibleStack = visibleCards[visibleCards.length - 1];
  const leftmostVisibleCard =
    firstCardInVisibleStack.getBoundingClientRect().left <
    lastCardInVisibleStack.getBoundingClientRect().left
      ? firstCardInVisibleStack
      : lastCardInVisibleStack;

  const firstIdx = Number(leftmostVisibleCard.dataset.idx);
  const visibleCount = visibleCards.length;

  const nextTargetIdx = Math.max(0, firstIdx - visibleCount);
  const $nextLastCard = subsByTab[curTab].$episodes[nextTargetIdx];
  $nextLastCard.scrollIntoView({ behavior: "smooth", block: "nearest", inline: "start" });
});

$getEle("#next-page").addEventListener("click", function () {
  const firstCardInVisibleStack = visibleCards[0];
  const lastCardInVisibleStack = visibleCards[visibleCards.length - 1];
  const rightmostVisibleCard =
    firstCardInVisibleStack.getBoundingClientRect().left >
    lastCardInVisibleStack.getBoundingClientRect().left
      ? firstCardInVisibleStack
      : lastCardInVisibleStack;

  const lastIdx = Number(rightmostVisibleCard.dataset.idx);
  const visibleCount = visibleCards.length;

  const maxIdx = subsByTab[curTab].$episodes.length - 1;
  const nextTargetIdx = Math.min(lastIdx + visibleCount, maxIdx);
  const $nextLastCard = subsByTab[curTab].$episodes[nextTargetIdx];
  $nextLastCard.scrollIntoView({ behavior: "smooth", block: "nearest", inline: "end" });
});

$getAllEle(".navigation-item").forEach(($ele) => {
  $ele.addEventListener("click", function () {
    $getAllEle(".navigation-item").forEach(($otherEle) =>
      $otherEle.classList.toggle("selected", false)
    );

    this.classList.toggle("selected", true);

    let target;
    switch (this.id) {
      case "last-uploaded":
        target = "uploads";
        break;

      case "last-completed":
        target = "completed";
        break;

      case "last-edited":
        target = "modified";
        break;

      case "paused":
        target = "paused";
        break;

      case "last-resynced":
        target = "resyncs";
        break;
    }

    if (!target)
      // Nothing to do
      return;

    loadTab(target);
  });
});

loadTab(curTab);
