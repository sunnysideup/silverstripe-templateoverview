jQuery(document).ready(
	function(){

		var checker = {

			useJSTest: false,

			totalResponseTime: 0,

			numberOfTests: 0,

			list: jQuery('.checker-list .link-item').toArray(),

			baseURL: '/dev/tasks/smoketest/',

			item: null,

			stop: true,

			init: function() {
				jQuery('a.start').on('click',
					function() {
						if (checker.stop === true) {
							jQuery(this).text('Stop');
							checker.stop = false;

							if (!checker.item) {
								checker.item = checker.list.shift();
							}

							if (checker.item) {
								checker.checkURL();
							} else {
								jQuery(this).addClass('disabled').text('Complete');
							}
						} else {
							jQuery(this).text('Start');
							checker.stop = true;
						}
					}
				);
			},

			checkURL: function(){
				if(!checker.stop) {
					var linkItem = jQuery(checker.item);
					if (checker.useJSTest) {
						var data = {};
						var baseLink = checker.item.dataset.link;
					} else {
						var baseLink = checker.baseURL;
						var isAdmin = linkItem.data('is-admin');
						var testLink = linkItem.data('link');
						var data = {'test': testLink, 'admin': isAdmin}
					}
					var rowID = linkItem.attr('ID');
					var tableRow = jQuery('#' + rowID);
					tableRow.addClass('loading');

					jQuery.ajax({
						url: baseLink,
						type: 'get',
						data: data,
						success: function(data, textStatus){
							checker.item = null;

							checker.item = checker.list.shift();

							var splitData = data.split('</h1>');
							var jsonData = null;

							if (splitData.length > 1 && splitData[1]) {
								jsonData = JSON.parse(splitData[1]);

								tableRow.removeClass('loading').addClass(jsonData.status);

								tableRow.find('td.response-time').text(jsonData.responseTime);
								tableRow.find('td.http-response').text(jsonData.httpResponse);
								tableRow.find('td.w3-check').text(jsonData.w3Content);
								tableRow.find('td.content').text(jsonData.content);

								if (jsonData.responseTime && typeof jsonData.responseTime !== 'undefined') {
									checker.numberOfTests++;
									checker.totalResponseTime = checker.totalResponseTime + jsonData.responseTime;
									jQuery('#NumberOfTests').text(checker.numberOfTests);
									jQuery('#AverageResponseTime').text(Math.round(100 * (checker.totalResponseTime / checker.numberOfTests)) / 100);
								}
							} else {
								tableRow
									.removeClass('loading')
									.addClass('error')
									.find('td.content').html('Error');
							}

							if (checker.item) {
								window.setTimeout(
									function() {checker.checkURL();},
									250
								);
							} else {
								jQuery('a.start').addClass('disabled').text('Complete');
							}
						},
						error: function(error){
							checker.item = null;

							checker.item = checker.list.shift();

							tableRow
								.removeClass('loading')
								.addClass('error')
								.find('td.content').html('Error');

							if (checker.item) {
								window.setTimeout(
									function() {checker.checkURL();},
									250
								);
							} else {
								jQuery('a.start').addClass('disabled').text('Complete');
							}
						},
						dataType: 'html'
					});
				}
			}
		}

		checker.init();
	}
);
