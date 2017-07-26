import Vue from 'vue';
import $ from 'jquery';

$(function() {
    let $newShow = $("#new-show");
    $("#showId").on("change", function(){
        let val = $(this).val();
        $newShow.parent().toggleClass("hidden", val != "NEW");
        if(val == "NEW") {
            $newShow.focus();
        }
    })
});