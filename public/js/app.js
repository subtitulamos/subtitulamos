function dateDiff(a, b) {
	let utcA = Date.UTC(a.getFullYear(), a.getMonth(), a.getDate(), a.getHours(), a.getMinutes(), a.getSeconds());
	let utcB = Date.UTC(b.getFullYear(), b.getMonth(), b.getDate(), b.getHours(), b.getMinutes(), b.getSeconds());

	return Math.floor(utcB - utcA);
}

function toggleAccessForm() {
	let $this = $(this);
	let formType = $this.attr("id");
	let $loginForm = $("#login_form");
	let $loginRegistry = $("#login_registry");
	let $regForm = $("#register_form");
	let $fadingPan = $("#login_registry_fade_pan");

	$fadingPan.toggleClass("hidden", false);
	
	if (formType == "login"){		
		//if Login Form is open and you click on Iniciar Sesion on navigation bar -- close it
		if (!$loginForm.hasClass("hidden") && !$loginRegistry.hasClass("hidden")){
			$loginRegistry.toggleClass("bounce", false);
			$loginRegistry.toggleClass("bounce_back", true);
			setTimeout(function(){
				$loginRegistry.toggleClass("hidden", true)
				$loginRegistry.toggleClass("bounce", true);
				$loginRegistry.toggleClass("bounce_back", false);}, 180);
		}
		//if Login Form is closed, just open it
		else if ($loginRegistry.hasClass("hidden")){
			$loginRegistry.toggleClass("hidden", false);
			$regForm.toggleClass("hidden", true);
			$loginForm.toggleClass("hidden", false);
		}
		//if Register Form is open and you click on Iniciar Sesion on navigation bar
		else{
			$regForm.toggleClass("sendleft_remove", true);
			setTimeout(function(){
				$regForm.toggleClass("hidden", true);
				$loginForm.toggleClass("hidden", false);
				$loginForm.toggleClass("sendleft", true);}, 250);				
			setTimeout(function(){
				$regForm.toggleClass("sendleft_remove", false);
				$loginForm.toggleClass("sendleft", false);}, 400);
		}
	}
	else if (formType == "register"){
		//if Register Form is open and you click on Registro on navigation bar -- close it
		if (!$regForm.hasClass("hidden") && !$loginRegistry.hasClass("hidden")){
			$loginRegistry.toggleClass("bounce", false);
			$loginRegistry.toggleClass("bounce_back", true);
			setTimeout(function(){
				$loginRegistry.toggleClass("hidden", true)
				$loginRegistry.toggleClass("bounce", true);
				$loginRegistry.toggleClass("bounce_back", false);}, 180);
		}
		//if Login Form is closed, just open it
		else if ($loginRegistry.hasClass("hidden")){
			$loginForm.toggleClass("hidden", true);
			$loginRegistry.toggleClass("hidden", false);
			$regForm.toggleClass("hidden", false);
		}
		//if Login Form is open and you click on Registro on navigation bar
		else{
			$loginForm.toggleClass("sendleft_remove", true);
			setTimeout(function(){
				$loginForm.toggleClass("hidden", true);
				$regForm.toggleClass("hidden", false);
				$regForm.toggleClass("sendleft", true);}, 250);
			setTimeout(function(){
				$loginForm.toggleClass("sendleft_remove", false);
				$regForm.toggleClass("sendleft", false);}, 400);
		}
	}		
}

function closeLogRegForm()
{
	let $loginRegistry = $("#login_registry");	
	let $fadingPan = $("#login_registry_fade_pan");

	$fadingPan.toggleClass("fade_out", true);
	setTimeout(function(){
		$fadingPan.toggleClass("hidden", true);
		$fadingPan.toggleClass("fade_out", false);
	}, 580);
	
	if ($loginRegistry.hasClass("hidden")){
		$loginRegistry.toggleClass("hidden", false);
	}
	else{
		$loginRegistry.toggleClass("bounce", false);
		$loginRegistry.toggleClass("bounce_back", true);
		setTimeout(function(){
			$loginRegistry.toggleClass("hidden", true)
			$loginRegistry.toggleClass("bounce", true);
			$loginRegistry.toggleClass("bounce_back", false);
		}, 380);
	}
}

function shortNumber(number)
{
	if(number > 1000) {
		number = Math.floor(number/100) / 10;
		number = number + "k";
	}

	return number
}

function shortTime(seconds)
{
	if(seconds < 3600) {
		let m = Math.floor(seconds / 60);
		return {number: m, unit: m > 1 ? "minutos" : "minuto"}
	}

	if(seconds < 86400) {
		let h = Math.floor(seconds / 3600);
		return {number: h, unit: h > 1 ? "horas" : "hora"}
	}

	let d = Math.floor(seconds / 86400);
	return {number: d, unit: d > 1 ? "días" : "día"}
}

function cleanShowName(name) {
	return name.toLowerCase().replace(/ /g, '-').replace(/[^a-z0-9-]/, '');
}

function openCategory(){
	let $categoryClicked = $(this);	
	let $mainState = $("#main_state");
	let $incategoryState = $("#incategory_state");
	let $categoryNavTitle = $("#category_navigation_title");
	let $searchBar = $("#search_bar_container");
	let $categoryNavList = $("#category_navigation_list");
	let $whiteLogoSearchBar = $("#white_logo_searchbar");
	
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

		$searchBar.toggleClass("move_up_searchbar",true);
		$categoryNavList.toggleClass("move_up_searchbar",true).toggleClass("fade_in", true);
		$incategoryState.toggleClass("move_up_searchbar",true).toggleClass("fade_in", true);
		$whiteLogoSearchBar.toggleClass("hidden", false);
		setTimeout(function(){		
			$mainState.toggleClass("hidden", true);
			$searchBar.toggleClass("move_up_searchbar", false);
			$categoryNavList.toggleClass("move_up_searchbar", false).toggleClass("fade_in", false);
			$incategoryState.toggleClass("move_up_searchbar", false).toggleClass("fade_in", false);
		}, 580);
	}
	
	let target;
	let id = $categoryClicked.attr("id");
	switch(id) {
		case "most_downloaded":
			target = 'popular';
			break;

		case "last_uploaded":
			target = "uploads";
			break;
	}

	if(!target) // Nothing to do
		return;

	$.ajax({
		url: "/search/"+target,
		method: "get"
	}).done(function(data) {
		data.forEach(function(_, idx, data){
			data[idx].time_ago = 0;
			data[idx].time_unit = "sec";
		});

		episodeList.episodes = data;
	});
}

function doLogin() {
	let $notificationBar = $("#display_notification");

	// Login the user via ajax
	$.ajax({
		url: "/login",
		method: "post",
		data: {
			username: $("#login_username").val(),
			password: $("#login_password").val(),
			remember: $("#login_remember_me").val()
		}
	}).done(function(data) {
		window.location.reload(true);
	}).fail(function(data) {
		try {
			let d = JSON.parse(data.responseText);
			Object.keys(d).forEach(function(k) {
				alertify.error(d[k]);
			}, this);
		} catch (e) {
			alertify.error("Error desconocido al intentar acceder. Por favor, inténtalo de nuevo.");
		}
	});
}

function closeNotification(){
	let $notificationBar = $("#display_notification");
	$notificationBar.toggleClass("fade_slide_out", true);
	
	setTimeout(function(){
		$notificationBar.toggleClass("hidden", true).toggleClass("fade_slide_out", false);
	}, 350);
}

function clickReactionsAnimate(){
	let $this = $(this);
	
	$this.toggleClass("button_bounce_click", true);
	
	setTimeout(function(){
		$this.toggleClass("button_bounce_click", false);
	}, 500);
}

function clickHomepageEpisode() {
	window.location = $(this).data("target");
}

function register() {
	var $regForm = $('#fregister');

	if(!$regForm[0].checkValidity()) { // If the form is invalid, submit it to display error messages
		$regForm.find(':submit').click();
		return;
	}

	// Login the user via ajax
	$.ajax({
		url: "/register",
		method: "post",
		data: {
			username: $("#reg_username").val(),
			password: $("#reg_password").val(),
			password_confirmation: $("#reg_password_confirmation").val(),
			email: $("#reg_email").val(),
			terms: $("#reg_terms").val()
		}
	}).done(function(data) {
		window.location.reload(true);
	}).fail(function(data) {
		try {
			let d = JSON.parse(data.responseText);
			Object.keys(d).forEach(function(k) {
				d[k].forEach(function(err){
					alertify.error(err);
				});
			}, this);
		} catch (e) {
			alertify.error("Error desconocido al intentar completar el registro. Por favor, inténtalo de nuevo.");
		}
	});
}

$(function() {
	$("#close_logreg_form, #login_registry_fade_pan").on("click", function(){closeNotification(); closeLogRegForm();});
	$("#login, #register").on("click", toggleAccessForm);
	$(".category_navigation_item").on("click", openCategory);
	$("#login_button .sign_button").on("click", doLogin);
	$("#register_button .sign_button").on("click", register);
	$("#close_notification, #display_notification").on("click", closeNotification);
	$("#incategory_board").on("click", ".love_reaction, .share_reaction", clickReactionsAnimate);
	$("#incategory_board").on("click", ".clip_info_row", clickHomepageEpisode);
});