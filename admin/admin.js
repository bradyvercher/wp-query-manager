jQuery(function($) {
	var initialRun = true;
	
	$('.wp-query-manager-repeater').on('change', 'select.archive-type', function(e) {
		// don't clear filter values on first run
		if ( ! initialRun )
			$(this).siblings().val('');
		
		$(this).siblings().hide().filter( $('.filter-' + $(this).val()) ).show();
	}).find('select.archive-type').trigger('change');
	
	initialRun = false;
	
	/**
	 * Tab switching functionality
	 */
	var updateTabs = function() {
		var hash = window.location.hash,
			form = $('#post-body-content form');
		
		form.attr('action', hash);
		$('.nav-tab-wrapper .nav-tab').removeClass('nav-tab-active').filter('[href="' + hash + '"]').addClass('nav-tab-active');
		$('.tab-panel').removeClass('tab-panel-active').filter(hash).addClass('tab-panel-active').trigger('showTabPanel');
		
		if ( $('.nav-tab-wrapper .nav-tab-active').length < 1 ) {
			var href = $('.nav-tab-wrapper .nav-tab:eq(0)').addClass('nav-tab-active').attr('href');
			$('.tab-panel').removeClass('tab-panel-active').filter(href).addClass('tab-panel-active');
		}
	}
	
	if ($('.nav-tab').length) {
		$(window).on('hashchange', updateTabs);
		updateTabs();
	}
	window.scrollTo(0, 0);
	
	
	$('.wp-query-manager-repeater').wpQueryManagerRepeater();
	$('.wp-query-manager-column-tog').on('click', function() {
		var $this = $(this),
			column = $this.val();
		
		if ( $this.prop('checked') ) {
			$('.column-' + column).show();
			$('div.wp-query-manager-section[data-column="' + column + '"]').show();
		} else {
			$('.column-' + column).hide();
			$('div.wp-query-manager-section[data-column="' + column + '"]').hide();
		}
		
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'wp_query_manager_preferences',
				hidden: $('.wp-query-manager-column-tog:not(:checked)').map(function() { return this.value; }).get().join(','),
				screenoptionnonce: $('#screenoptionnonce').val(),
			}
		});
	});
	
	/**
	 * Non-hierarchical taxonomy term autocomplete
	 */
	$('.repeater-items').on('focus', '.column-template input', function() {
		var $this = $(this);
		
		if ( ! $this.hasClass('ui-autocomplete-input')) {
			$this.autocomplete({
				source: function( request, response ) {
					$.ajax({
						url: ajaxurl,
						data: {
							action: 'wp_query_manager_get_terms',
							name: $this.val(),
							taxonomy: $this.siblings('select.archive-type').val().replace('tax_', '')
						},
						dataType: 'JSON',
						success: function( data ) {
							$this.removeClass('ui-autocomplete-loading');
							response( data );
						}
					});
				},
				minLength: 2
			});
		}
	});
});


(function($) {
	// .clear-on-add will clear the value of a form element in a newly added row
	// .hide-on-add will hide the element in a newly added row
	// .remove-on-add will remove an element from a newly added row
	
	var methods = {
		init : function( options ) {
			var settings = { };
			if (options) $.extend(settings, options);

			return this.each(function() {
				var repeater = $(this)
					firstItem = repeater.find('.repeater-item:eq(0)');
				
				firstItem.parent().sortable({
					axis: 'y',
					forceHelperSize: true,
					forcePlaceholderSize: true,
					helper: function(e, ui) {
						var $helper = ui.clone();
						$helper.children().each(function(index) {
						  $(this).width(ui.children().eq(index).width())
						});
						
						return $helper;
					},
					update: function(e, ui) {
						repeater.wpQueryManagerRepeater('updateIndex');
					}
				});
				
				repeater.data('itemIndex', repeater.find('.repeater-item').length).data('itemTemplate', firstItem.clone());
				
				repeater.find('.repeater-add-item').on('click', function(e) {
					e.preventDefault();
					$(this).closest('.wp-query-manager-repeater').wpQueryManagerRepeater('addItem');
				});
				
				repeater.on('click', '.repeater-remove-item', function(e) {
					var repeater = $(this).closest('.wp-query-manager-repeater');
					e.preventDefault();
					$(this).closest('.repeater-item').remove();
					repeater.wpQueryManagerRepeater('updateIndex');
				});
				
				repeater.on('blur', 'input', function() {
					$(this).closest('.wp-query-manager-repeater').find('.repeater-item').removeClass('repeater-active-item');
				}).on('focus', 'input', function() {
					$(this).closest('.repeater-item').addClass('repeater-active-item').siblings().removeClass('repeater-active-item');
				});
			});
		},
		
		addItem : function() {
			var repeater = $(this),
				itemIndex = repeater.data('itemIndex'),
				itemTemplate = repeater.data('itemTemplate');
			
			repeater.find('.repeater-items').append(itemTemplate.clone()).children(':last-child').find('input,select,textarea').each(function(e) {
				var $this = $(this);
				$this.attr('name', $this.attr('name').replace('[0]', '[' + itemIndex + ']') );
			}).end().find('.clear-on-add').val('').end().find('.remove-on-add').remove().end().find('.show-on-add').show().end().find('.hide-on-add').hide();
			
			repeater.data('itemIndex', itemIndex+1 ).wpQueryManagerRepeater('updateIndex');
		},
			
		updateIndex : function() {
			$('.repeater-index', this).each(function(i) {
				$(this).text(i + 1 + '.');
			});
		}
	};	
	
	$.fn.wpQueryManagerRepeater = function(method) {
		if ( methods[method] ) {
			return methods[method].apply(this, Array.prototype.slice.call(arguments, 1));
		} else if ( typeof method === 'object' || ! method) {
			return methods.init.apply(this, arguments);
		} else {
			$.error('Method ' +  method + ' does not exist on jQuery.wpQueryManagerRepeater');
		}    
	};
})(jQuery);