// TO DO: может всё таки вынести авторов и о проекте в хтмл
(function(){
	var calendarDate = false;
	var tooltip = function(tooltip){
		$(tooltip).hover(function(e){
			this.tt = this.title;
			this.title = '';
			this.t = $(this).children('img').attr('alt');
			$(this).children('img').attr('alt','');
			$("body").append("<p id='tooltip'>"+ this.tt +"</p>");
			$("#tooltip")
				.css("top",(e.pageY + 20) + "px")
				.css("left",(e.pageX + 20) + "px")
				.fadeIn("slow");
	    },
		function(){
			$(this).children('img').attr('alt',this.t);
			this.title = this.tt;
			$("#tooltip").remove();
	    });	
		$(tooltip).mousemove(function(e){
			$("#tooltip")
				.css("top",(e.pageY + 20) + "px")
				.css("left",(e.pageX + 20) + "px");
		});
	};
	// tooltip plugin written by Alen Grakalic (http://cssglobe.com) and modified for image and ajax-loaded tooltips by Dmitry Groza (http://dgroza.ru)
	jQuery.fn.fadeAppend = function(e){
		var parent = this;
		var opt = {
			selector: '.pseudoblock'
		}
		if ($(opt.selector).length < 1){
			var blockId = '#';
			blockId += $(e)
				.appendTo(parent)
				.attr('id');
			$(blockId).fadeIn();
		} else {
			$(opt.selector).fadeRemove(function(){
				$(parent).fadeAppend(e);
			})
		}
	}
	jQuery.fn.fadeRemove = function(fnct){
		var t = this;
		$(this).fadeOut(400,function(){
			$(t).remove();
			if (fnct){
				fnct();
			}
		});
	}
	// fadeAppend & fadeRemove plugin written by Dmitry Groza (http://dgroza.ru)

	Date.prototype.getMonthOne = function(){
		return this.getMonth() + 1;
	}
	Date.prototype.plain = function(type){
		if (type == 'month'){
			return this.getFullYear() + '_' + (this.getMonthOne());
		}
		if (type == 'day'){
			return this.getFullYear() + '_' + (this.getMonth() + 1) + '_' + this.getDate();
		}
	}
	Date.prototype.isViewFull = false;
	Date.prototype.setPlain = function(plain){
		var plainDateArr = plain.split('_');
		this.isViewFull = plainDateArr[2] ? true : false;
		this.setFullYear(plainDateArr[0], plainDateArr[1] - 1, this.isViewFull ? plainDateArr[2] : 1);
	}
	var clean = function(){
		$('.cleanable').fadeRemove();
		$('#tooltip').remove(); 
	}
	var showInfo = function(text){
		hideInfo();
		$('#wrap').append('<div id="info-block" class="loading cleanable">' + text +'</div>');
	}
	var hideInfo = function(){
		$('#info-block').remove();
	}
	var showViewFull = function(){
		$('#post-view').css({'height':'auto','width':'auto','display':'none','overflow':'visible','padding':''});
		$('#post-view img').unbind('load');
		hideInfo();
		$('#post-view').fadeIn();
	}
	var viewFull = function(href){
			showInfo(options.info.loading);
			imgComplete = true;
			if ($('#post-view').length < 1){
				var block = '<div id="post-view" class="cl cleanable faded postview"></div>';
				$('#wrap').append(block);
				$('#post-view').css({'height':'1px','width':'1px','overflow':'hidden','padding':'0'});
				
				var fImg = new Image();
				$('#post-view').append(fImg);
				fImg.src = href;
				
				if (fImg.complete){
					imgComplete = false;
					showViewFull();
				}
				$(fImg).load(function(){	// to do: do it if no error
					if (imgComplete) showViewFull();
				});
				$(fImg).error(function(){
					showInfo(options.info.noImage);
				});
			} else {
				$('#post-view').fadeRemove(function(){
					viewFull(href);
				});
			}
	}
	var appendCalendar = function(data){
		clean();
		$('#month-wrap').html(data);
		tooltip('a.tooltip');
		$('#calendar').hide();
		$('#calendar').fadeIn(1000);
	}
	var loadCalendar = function(date){
		var callback = arguments[1] ? arguments[1] : false;
		$.ajax({
			url : 'handler.php',
			type : 'post',
			timeout : 30000,
			success : function(data){
				hideInfo();
				clean();
				if ($('#calendar').length > 0) {
					$('#calendar').animate({opacity:"0"}, 400, function(){
						appendCalendar(data);
					});
				} else {
					appendCalendar(data);
				}
				if (callback) callback();
			},
			beforeSend : showInfo(options.info.loading),
			data : {
				month : date.getMonthOne(),
				year : date.getFullYear()
			},
			error : function(){
				hideInfo();
				showInfo(options.info.ajaxError);
			}
		});
	}
	$(document).ready(function(){
		tooltip('a.permtooltip');	// binds static tooltips
		
		var historyCallback = function(hash){
			if (!calendarDate) {
				calendarDate = new Date();
				var isFirstCalendarLoading = true;
			}
			hash = hash ? hash : 'd' + (new Date()).plain('month');
			if (hash.substr(0,1) == 'd'){
				hash = hash.substr(1);
				
				var hashDate = new Date();
				hashDate.setPlain(hash);
				
				var isMonthChanged = (isFirstCalendarLoading || ((calendarDate.getMonthOne()) != hashDate.getMonthOne()));
				calendarDate.setPlain(hash);
				
				if (calendarDate.isViewFull){
					var fullImageSrc = '/blog/' + calendarDate.getMonthOne() + '_' + calendarDate.getDate() + '_full.jpg';
					if (isMonthChanged){
						loadCalendar(calendarDate, function(){
							viewFull(fullImageSrc);
						});
					} else {
						viewFull(fullImageSrc);
					}
				} else {
					if (isMonthChanged){
						loadCalendar(calendarDate);
					} else {
						clean();
					}
				}
			}
		}
		$.history.init(historyCallback);
		
		$('.pseudo').click(function(){
			var blockId = $(this).attr('id') + 'block';
			$('#wrap').fadeAppend(options.pseudoblocks[blockId]);
			tooltip('a.pseudotooltip');
			return false;
		});
		
		$(".cl").live('click',function(){
			$(this).fadeRemove();
			if ($(this).hasClass('postview')){
				$.history.load('d' + calendarDate.plain('month'));
			}
		});
		$('.goto').live('click', function(){	// navigate
			$.history.load('d' + $(this).attr('id').substr(1));
			return false;
		});
	});
})();