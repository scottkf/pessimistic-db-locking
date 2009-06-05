var Admin;
(function($) {
	
	Admin = {

		User: {
			name: null,
			id: null
		},
		
		URL: {
			root: null,
			symphony_root: null,
			page: null,
			section: null,
			mode: null,
			entry: null,
			filter: { 'field': '', 'value': '' }
		},
		
		DOM: {
			h1: null,
			h2: null,
			nav: null,
			form: null,
			table: null,
			user_navigation: null,
			notice: null,
			pagination: null,
			filters: null
		},
		
		init: function() {
			
			// Rework DOM structure
			this.DOM.h1 = $('h1:first');
			this.DOM.h2 = $('h2:first');
			this.DOM.nav = $('ul#nav');
			this.DOM.form = $('form:first');
			this.DOM.table = $('table:first');
			this.DOM.pagination = $('ul.page');

			this.DOM.form.before(this.DOM.h1);
			this.DOM.form.wrap('<div id="wrapper"></div>');
			this.DOM.form.before(this.DOM.nav);
			this.DOM.form.wrap('<div id="form"></div>');
			
			this.DOM.user_navigation = $('ul#usr').attr('id', 'user');
			this.DOM.h1.after(this.DOM.user_navigation);
			
			$('h1, ul#user').wrapAll('<div id="header"></div>');
			
			// Page::Alert()
			this.DOM.notice = $('#notice');
			if (this.DOM.notice.length) {
				var border_colour = this.DOM.notice.css('border-top-color');
				this.DOM.notice.css('border-bottom-color', border_colour).css('border-top', 'none');
				this.DOM.notice.insertAfter(this.DOM.form.find('h2:first'));
			}
			
			// Configure URL parameters for context
			var root = this.DOM.h1.find('a').attr('href');
			var url = window.location.href.replace(root, '').split('/');
			
			this.URL.root = root;
			this.URL.symphony_root = root + 'symphony/';
			this.URL.page = url[1];
			
			switch(this.URL.page) {
				
				case 'publish':
					this.URL.section = url[2];
					
					// if set and no ? we are in a form page
					if (url[3] && url[3].indexOf('?') == -1) {
						this.URL.mode = url[3];
					}
					// otherwise we're on the publish index
					else {
						this.URL.mode = 'index';
						if (url[3].indexOf('?') != -1) {
							
							// parse ?filter=$field:$value values
							var matches = window.location.href.match(/filter=(([^:]+):(.*))?/);
							var field = ''; var value = '';

							if (matches && matches[2] != undefined && matches[3] != undefined) {
								this.URL.filter.field = decodeURI(matches[2]);
								this.URL.filter.value = decodeURI(matches[3]);
							}
							
						}
					};
					
					// entry ID is set
					if (url[4]) this.URL.entry = url[4];
					
				break;
				
			}
			
			// User details
			this.DOM.user_navigation = $('#user');
			var user = this.DOM.user_navigation.find('li:first a');
			this.User.name = user.html();
			this.User.id = user.attr('href').match(/(\/)([0-9]+)(\/)/)[2];
			
			this.manipulatePublishNavigation();
			this.manipulateFormLabels();
			this.manipulateH2();
			
		},
		
		manipulatePublishNavigation: function() {
			// find top level Navigation Groups
			this.DOM.nav.find('> li').each(function() {
				var text = $(this).get(0).childNodes[0];
				$(text).wrap('<span></span>');
				
				$(this).addClass('nav-' + General.createHandle(text.nodeValue));
				
				// highlight currently active section name
				$('li a', this).each(function() {
					if (window.location.href.indexOf($(this).attr('href')) != -1) {
						$(this).parent('li').addClass('selected');
					}
					
				});
				
			});
		},
		
		moveBlueprintsAndSystem: function() {
			
			this.DOM.user_navigation.after('<ul id="nav-system"></ul>');
			$('ul#nav-system').append(this.DOM.nav.find('li.nav-system ul li'));
			this.removeNavigationGroup('System');

			this.DOM.user_navigation.after('<ul id="nav-blueprints"></ul>');		
			$('ul#nav-blueprints').append(this.DOM.nav.find('li.nav-blueprints ul li'));
			this.removeNavigationGroup('Blueprints');

			$('ul#nav-system, ul#nav-blueprints').wrapAll('<div id="primary-navigation"></div>');
			
		},
		
		manipulateFormLabels: function() {
			// wrap label text in a span for easier styling of the label text only
			this.DOM.form.find('div.field').each(function(i) {
				var label = $('label:first', this);
				var label_textnode;
				
				if ($(this).hasClass('field-checkbox')) {
					label_textnode = label.get(0).childNodes[1];
					label.find('> *:first').after('<span class="label">' + label_textnode.nodeValue +'</span>');
				} else {
					label_textnode = label.get(0).childNodes[0];
					// TODO: regex to strip whitedspace only from end of string, not between words
					// replace(/^\-+|\-+$/g, '')
					label.find('> *:first').before('<span class="label">' + label_textnode.nodeValue.replace(/\s+/g,'') +'</span>');
				}
				
				label_textnode.nodeValue = '';
				
			});
			
			//star required fields
			this.DOM.form.find('.required .label').after('<span class="asterisk">*</span>');
		},
		
		manipulateH2: function() {
			
			// set our own class and remove defaults, easier than overriding CSS!
			this.DOM.h2.find('a').addClass('create-new').removeClass('create').removeClass('button');
			
			// add entry count on publish Index page
			if (this.URL.page == 'publish' && this.URL.mode == 'index') {
				
				var count = 0;
				if (this.DOM.pagination.length) {
					// when more than one page, parse count from title attribute on pagination
					var title = this.DOM.pagination.find("li[title~='Viewing']").attr('title');
					count = parseInt(title.split('of')[1].replace(/^\s+|\s+$/g,''));
				} else {
					count = this.DOM.table.find('tbody tr').length;
					if (count == 1 && this.DOM.table.find('tbody tr:first td').attr('colspan')) count = 0;
				}

				var h2 = this.DOM.h2.get(0);
				var index = 1;
				if (h2.childNodes[0].nodeType == 3) index = 0;
				$(h2.childNodes[index]).wrap('<span class="h2"></span>');
				this.DOM.h2.find('span.h2').after('<span class="count">' + count + ' entr' +  ((count == 1) ? 'y' : 'ies') + '</span>');
			}
			
			// make title mor interesting than "Untitled" for new entries
			if (this.URL.page == 'publish' && this.URL.mode == 'new') {
				this.DOM.h2.text('New entry in ' + this.DOM.nav.find('li.selected a').text());
			}
			
		},
		
		removeTableColumn: function(heading) {
			var index = null;
			var heading = General.createHandle(heading);
			this.DOM.form.find('thead tr th').each(function(i) {
				if (General.createHandle($(this).text()) == heading) index = i;
			});
			if (index) {
				this.DOM.form.find('tr').each(function() {
					$('th:eq('+index+'), td:eq('+index+')', this).remove();
				});
			}
		},
		
		addTableColumn: function(column) {
			var self = this;
			
			var handle = General.createHandle(column.title);
			var new_column_index = this.DOM.table.find('thead th').length;
			
			this.DOM.table.find('thead tr').append('<th>'+column.title+'</th>');
			this.DOM.table.find('tbody tr').each(function(i) {
				$(this).append('<td id="'+handle+'-'+i+'"></td>')
			});
			
			var data = 'handle=' + handle + '&section=' + column.section + '&fields=' + column.fields;
			
			data += '&result=' + column.result;
			
			for(var condition in column.conditions) {
				for (var p in column.conditions[condition]) {
					data += '&conditions[' + p + ']=' + column.conditions[condition][p];
				}
			}
			
			var entries = [];
			this.DOM.table.find('tbody tr').each(function(i) {
				
				var id = $('td:first a', this).attr('href').match(/(\/)([0-9]+)(\/)/)[2];
				entries.push(id);
				
				for(var filter in column.filters) {
					
					data += '&entry['+id+'][filter]';
					
					for (var p in column.filters[filter]) {
						data += '[' + p + ']';
					}

					var value = column.filters[filter][p];
					if (value == 'system:id') {
						value = id;
					} else {
						var column_handle = value;
						var type = null;
						var column_index = null;
						
						if (value.indexOf(':') != -1) {
							column_handle = value.split(':')[0];
							type = value.split(':')[1];
						}
						
						self.DOM.table.find('thead th').each(function(index) {
							if (General.createHandle($(this).text()) == column_handle) column_index = index;
						});
						
						if (column_index != null) {
							self.DOM.table.find('tbody tr:eq('+i+')').each(function() {
								var td = $('td:eq('+column_index+')', this);
								
								switch(type) {
									case null:
										value = td.text();
									break;
									case 'handle':
										value = General.createHandle(td.text());
										break;
									case 'id':
										value = td.find('a').attr('href').match(/(\/)([0-9]+)(\/)/)[2];
									break;
								}
								
							});
						}
					}
					
					data += '=' + value;
					
				}				
				
			});
			
			$.post(this.URL.symphony_root + 'extension/custom_admin/ajax_column/', data, function(response){
				console.log(response)
				var values = eval(response);
				for(entry in entries) {
					self.DOM.table.find('tbody tr:eq('+entry+') td:eq('+new_column_index+')').html(values[entry][1]);
				}
			});
		},
		
		removeNavigationGroup: function(heading) {
			var heading = General.createHandle(heading);
			this.DOM.nav.find('li').each(function(i) {
				if ($(this).hasClass('nav-' + heading)) $(this).remove();
			});
		},
		
		addFilterTabContainer: function() {
			if (!this.DOM.filters) {
				this.DOM.table.before('<ul id="filter-tabs"></ul>');
				var a = this.DOM.h2.find('a').wrap('<li class="right"></li>');
				this.DOM.h2.find('.right').appendTo('#filter-tabs');
				this.DOM.filters = $('#filter-tabs');				
			}			
		},
		
		addFilterTab: function(label, handle, filter, selected) {
			
			this.addFilterTabContainer();
			
			var selected_class = ((this.URL.filter.field == handle && this.URL.filter.value == filter) || selected) ? ' selected' : '';
			
			if (selected_class != '') {
				this.DOM.filters.find('li').removeClass('selected');
			}
			
			var filter_url = '&filter=' + handle + ':' + filter;
			if (handle == '' || filter == '') filter_url = '';
			
			this.DOM.filters.find('.right').before('<li class="' +
				General.createHandle(label) + selected_class+'"><a href="' +
				this.URL.symphony_root + this.URL.page + '/' + this.URL.section + '/?mode=tab' + filter_url +
				'">' + label + '</a>' + '</li>'
			);
			
			var tab_mode = '&mode=tab';
			$([this.DOM.table.find('thead th a'), this.DOM.pagination.find('a')]).each(function() {
				var href = $(this).attr('href');
				if (href.indexOf(tab_mode) == -1) {
					$(this).attr('href', href + tab_mode);
				}				
			});
			
			$('a.create-new').each(function() {
				var href = $(this).attr('href');
				$(this).attr('href', href.replace(/\?(.*)/,''))
			});
			
		},
		
		addSearchFilterTab: function(label, handle) {
			var self = this;
			
			this.addFilterTabContainer();
			
			var searched = (this.URL.filter.field.indexOf(',') != -1) ? true : false;
			
			var search_value = this.URL.filter.value;
			if (!searched) search_value = '';
			this.DOM.filters.after(
				'<div id="filter-tabs-search">' +
					'<input type="text" name="filter" class="text" value="' + search_value.replace(/regexp:/,'') + '" />' +
					'<input type="button" value="Search" class="button" />' +
				'</div>'
			);
			
			this.addFilterTab(label, handle, this.URL.filter.field, searched);

			if (searched) {
				$('#filter-tabs-search').show();
			}
			
			this.DOM.filters.find('li.' + General.createHandle(label)).click(function(e) {
				e.preventDefault();
				$(this).parent().find('li').removeClass('selected');
				var li = $(this);
				$('#filter-tabs-search').slideToggle('fast', function() {
					$(this).is(':visible') ? li.addClass('selected') : li.removeClass('selected');
				});
			});
			
			$('#filter-tabs-search input.text').keydown(function(e) {
				if (e.keyCode == 13) {
					$('#filter-tabs-search input.button').click();
					return false;
				}
			});
			
			$('#filter-tabs-search input.button').click(function(e) {
				window.location.href = self.URL.symphony_root + self.URL.page + '/' + self.URL.section + '/?mode=tab&filter=' + handle + ':regexp:' + $('#filter-tabs-search input.text').val();
			});
		},
		
		Fields: function(fields) {
			var self = this;
			
			this.DOM.form.find('.field').each(function(i, field) {
				var field = $(field);
				var handle = field.find('*[name*="fields["]').attr('name').replace(/fields\[/,'').replace(/\]/,'');
				
				for(f in fields) {
					
					if (!fields[f][handle]) continue;
						
					var label = $('label', field);
					
					// modify label text if specified
					if (fields[f][handle].label) label.find('.label').text(fields[f][handle].label);
					
					// modify field meta description if specified
					if (fields[f][handle].meta) {
						var i = label.find('i');
						if (i.length) {
							var i_text = i.text();
							i.html(fields[f][handle].meta + ' (' + i_text + ')');
						} else {
							label.append('<i>' + fields[f][handle].meta + '</i>');
						}
					}
					
					// set default value if specified
					if (fields[f][handle].default && self.URL.mode == 'new') {
						var default_value = fields[f][handle].default;
						var input = field.find('input[type="text"]');
						var select = field.find('select');
						
						if (input.length) {
							input.val(default_value)
						}
						else if (select.length) {
							select.val(default_value);
						}
					}
					
					// hide fields by positioning off page rather than hiding, to prevent any POST oddities
					if (fields[f][handle].hidden) field.css({ 'position': 'absolute', 'top': '-9999px', 'left': '-9999px'});

				}
				
			});
			
		}
		
	}
	
})(jQuery.noConflict());

General = {
	createHandle: function(text) {
		var str = new String(text);
		str = str.replace(/([\\.\'"]+)/g, '');	// Remove punctuation
		str = str.replace(/([\s]+)/g, '-'); // Replace spaces (tab, newline etc) with the delimiter
		str = str.replace(/[<>?@:!-\/\[-`ëí;‘’]+/g, '-'); //Replace underscores and other non-word, non-digit characters with delim
		str = str.replace(/^\-+|\-+$/g, ''); // Remove leading or trailing delim characters
		return str.toLowerCase();
	}	
}

jQuery(document).ready(function() {
	
	Admin.init();
	
	Admin.moveBlueprintsAndSystem();
	
	if(Admin.URL.section == 'articles' && Admin.URL.mode == 'index') {
		
		Admin.removeTableColumn('Images');
		Admin.removeTableColumn('Comments');
		Admin.removeTableColumn('Publish');
		Admin.removeTableColumn('Categories');
		Admin.removeTableColumn('Author');
		Admin.removeTableColumn('Tags');
		
		Admin.addFilterTab('All', '', '');
		Admin.addFilterTab(Admin.User.name.split(' ')[0] + '\'s Articles', 'author', Admin.User.id);
		Admin.addFilterTab('Published', 'publish', 'yes');
		Admin.addFilterTab('Un-published', 'publish', 'no');
		Admin.addSearchFilterTab('Search', 'title,body');
		
	/*	Admin.addTableColumn({
			'title': 'Time',
			'section': 'articles',
			'fields': ['date'],
			'filters': [
				{'system:id': 'system:id'}
			],
			'conditions': [
				{'limit': 1}
			],
			'result': 'date:h:i'
		});
		
		Admin.addTableColumn({
			'title': 'Approved Comments',
			'section': 'comments',
			'fields': ['system:id'],
			'filters': [
				{'article': 'system:id'},
				{'authorised': 'yes'}
			],
			'conditions': [
				{'limit': 999}
			],
			'result': 'count'
		});
		*/
		
		
		Admin.addTableColumn({
			'title': 'All Comments',
			'section': 'comments',
			'fields': ['author'],
			'filters': [
				{'article': 'system:id'}
			],
			'conditions': [
				{'limit': 1}
			],
			'result': '<span>{row:system:id}</span>'
		});
		
		/*Admin.addTableColumn({
			'title': 'Find this Article By Title Handle',
			'section': 'articles',
			'fields': ['system:id'],
			'filters': [
				{'title': 'title:handle'}
			],
			'conditions': [
				{'limit': 1}
			],
			'result': 'count'
		});*/
		
		
	}
	
	if(Admin.URL.section == 'articles' && (Admin.URL.mode == 'edit' || Admin.URL.mode == 'new')) {
		Admin.Fields([
			{'title': { label: 'Post Title', meta: 'This will be the URL as well' }},
			{'body': { meta: '<span id="word-limit">300</span> words remaining' }},
			{'author': { default: Admin.User.id, hidden: true }}
		]);
	}
		
});