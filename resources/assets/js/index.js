import Vue from 'vue';
import $ from 'jquery';
import { dateDiff } from './app.js';

let episodeList = new Vue({
    el: "#incategory_board",
    data: {
        category: '',
        episodes: []
    }, methods: {
        subURI: function(ep) {
            return "/episodes/"+ep.id+"/"+ep.slug;
        },

        update: function() {
            let self = this;
            let u = function() {
                self.episodes.forEach(function(ep, idx, arr) {
                    let diff = dateDiff(new Date(ep.time), new Date(Date.now()))/1000;
                    let unit = "";
                    if(diff >= 60) {
                        diff = Math.floor(diff / 60);
                        if(diff >= 60) {
                            diff = Math.floor(diff / 60);
                            if(diff >= 24) {
                                diff = Math.floor(diff / 24);
                                unit = diff > 1 ? "días" : "día";
                            } else {
                                unit = diff > 1 ? "horas" : "hora";
                            }
                        } else {
                            unit = diff > 1 ? "mins" : "min";
                        }
                    } else {
                        // < 60s, display every 10s
                        diff = Math.floor(diff/10) * 10;
                        unit = "seg";
                    }

                    if(diff != ep.time_ago) {
                        ep.time_ago = diff;
                        ep.time_unit = unit;
                        arr[idx] = ep;
                    }
                });
            };

            u(); // Insta update times
            this.interval = setInterval(u, 2000);
        }
    }, watch: {
        episodes: function(newEpisodes) {
            clearInterval(this.interval);
            this.update();
        }
    }
});

let categoryPage = {};
let rowsPerPage = 0;
function search(target, page) {
    $.ajax({
        url: "/search/"+target,
        method: "get",
        data: {
            page: page
        }
    }).done(function(data) {
        data.forEach(function(_, idx, data){
            data[idx].time_ago = 0;
            data[idx].time_unit = "sec";
        });

        episodeList.category = target;
        episodeList.episodes = data;
        categoryPage[target] = page;

        if(rowsPerPage == 0 || rowsPerPage < episodeList.episodes.length) {
            // First load? Let's guess the value
            rowsPerPage = episodeList.episodes.length;
        }

        let nextPageHidden = episodeList.episodes.length < rowsPerPage;
        let prevPageHidden = page <= 1;
        $("#next-page").toggleClass("hidden", nextPageHidden);
        $("#prev-page").toggleClass("hidden", prevPageHidden);
        $("#pages").toggleClass("hidden", nextPageHidden && prevPageHidden);
    });
}

$("#prev-page").on("click", function() {
    let targetPage = Math.max(categoryPage[episodeList.category] - 1, 1);
    if(targetPage == 1) {
        $(this).toggleClass("hidden", true);
    }

    $("#next-page").toggleClass("hidden", false);
    search(episodeList.category, targetPage);
});

$("#next-page").on("click", function() {
    let targetPage = Math.min(categoryPage[episodeList.category] + 1, 10);
    if(targetPage >= 10) {
        $(this).toggleClass("hidden", true);
    }

    $("#prev-page").toggleClass("hidden", false);
    search(episodeList.category, targetPage);
});

$(".category_navigation_item").on("click", function() {
    let $categoryClicked = $(this);
    let $mainState = $("#main_state");
    let $incategoryState = $("#incategory_state");
    let $categoryNavTitle = $("#category_navigation_title");
    let $searchBarContainer = $("#search");
    let $categoryNavList = $("#category_navigation_list");
    let $whiteLogoSearchBar = $("#white-logo-searchbar");

    $categoryNavTitle.toggleClass("hidden", true);
    $incategoryState.toggleClass("hidden", false);


    if ($(".category_navigation_item").hasClass("nvbi_active")){
        $(".category_navigation_item").toggleClass("nvbi_active", false);
        $categoryClicked.toggleClass("nvbi_active", true);
    }
    else {
        window.scrollTo(0, 0);
        $categoryClicked.toggleClass("nvbi_active", true);

        $mainState.toggleClass("fade_out", true);

        $searchBarContainer.toggleClass("move_up_searchbar",true);
        $categoryNavList.toggleClass("move_up_searchbar",true).toggleClass("fade_in", true);
        $incategoryState.toggleClass("move_up_searchbar",true).toggleClass("fade_in", true);
        $whiteLogoSearchBar.toggleClass("hidden", false);
        setTimeout(function(){
            $mainState.toggleClass("hidden", true);
            $searchBarContainer.toggleClass("move_up_searchbar", false);
            $categoryNavList.toggleClass("move_up_searchbar", false).toggleClass("fade_in", false);
            $incategoryState.toggleClass("move_up_searchbar", false).toggleClass("fade_in", false);
        }, 580);
    }

    let target;
    let id = $categoryClicked.attr("id");
    switch(id) {
        case "most-downloaded":
            target = 'popular';
            break;

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
    }

    if(!target) // Nothing to do
        return;

    search(target, 1);
});