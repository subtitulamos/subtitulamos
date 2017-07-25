import $ from './jquery.js';

$(".spoiler-wrapper").on("click", function() {
    let $this = $(this);
    let $spoiler = $this.find('.spoiler-content');
    $this.find('i').toggleClass('fa-caret-down').toggleClass('fa-caret-up');

    $spoiler.css('display', $spoiler.css('display') == 'none' ? 'block' : 'none');
});