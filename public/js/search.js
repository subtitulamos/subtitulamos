$(function(){
    let $searchBar = $("#search_bar");
    let $searchResults = $("#search-results");
    let searchTimerHandle = null;

    function zeropad(n, width) {
        n = n + '';
        return n.length >= width ? n : new Array(width - n.length + 1).join('0') + n;
    }

    function search() {
        searchTimerHandle = null;
        if($searchBar.val() == '') {
            return;
        }

        let q = $searchBar.val();
        $.ajax({
            url: '/search/query',
            method: 'GET',
            data: {
                q: q
            }
        }).done(function(reply) {
            $searchResults.html('').toggleClass('hidden', false);

            if(reply.length > 0) {
                reply.forEach(function(show) {
                    let $link = $('<a>').attr('href', '/shows/'+show.id).html(show.name);
                    let $result = $('<li>').append($link);
                    $searchResults.append($result);

                    if(show.episodes) {
                        show.episodes.forEach(function(ep) {
                            $link = $('<a>').attr('href', '/episodes/'+ep.id).html(show.name + ' - ' + ep.season+ 'x' + zeropad(ep.number, 2) + ' '+ep.name);
                            $result = $('<li>').append($link);
                            $searchResults.append($result);
                        });
                    }
                });
            } else if(q.length <= 3) {
                $searchResults.append($('<li>').attr('class', 'info').html('Sigue escribiendo...'));
            } else if(q.length <= 15) {
                $searchResults.append($('<li>').attr('class', 'info').html('No hay resultados de momento. Sigue escribiendo...'));
            } else {
                $searchResults.append($('<li>').attr('class', 'info').html('Parece que no tenemos resultados para esta búsqueda'));
            }
        });
    }

    $searchBar.on("keyup", function(e) {
        if(searchTimerHandle) {
            clearTimeout(searchTimerHandle);
        }

        searchTimerHandle = setTimeout(search, 200);
    });

    let hideTimeoutHandle = null;
    $searchBar.on("focusin", function() { clearTimeout(hideTimeoutHandle); $searchResults.toggleClass('hidden', $searchBar.val() == ''); });
    $searchBar.on("focusout", function() { hideTimeoutHandle = setTimeout(function(){$searchResults.toggleClass('hidden', true);}, 500); });
});