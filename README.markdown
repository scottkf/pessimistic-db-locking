*WARNING* this is unreleased code and is only on Github for sharing with colleagues. An actual release will follow.

# Custom Admin Extension

* Version: 0.01
* Author: Nick Dunn <http://github.com/nickdunn/>
* Build Date: 2009-06-03
* Requirements: Symphony 2.0.2 (with jQuery 1.3)

## Installation

1. Download and upload the 'custom_admin' folder to your Symphony 'extensions' folder.
2. Enable it by selecting "Custom Admin" in the list and choose Enable from the with-selected menu, then click Apply.

## Usage

This extension reskins the Symphony backend. It does not touch Symphony's existing CSS files, rather it resets and overrides instead. Much of the original Symphony layout remains, but the Custom Admin CSS allows for greater flexibility. In addition to styling, the extension performs significant DOM manipulation to the administration pages, adding wrappers and addition markup both for structure and styling.

Much of this JavaScript is packaged to run on initialisation of the `Admin` object on page load. However there are various functions exposed as an API for your own DOM manipulation.

### Initialising `Admin`

The magic begins with initialising `Admin` on page load. Note the use of `jQuery` over `$` since Symphony invokes jQuery in `noConflict` mode.

	jQuery(document).ready(function() {
		Admin.init();
	});

### Re-organising Blueprints and System

The supplied stylesheet makes provision for moving the Blueprints and System navigation to the header bar, separating them from the Publish groups. To perform this action:

	Admin.moveBlueprintsAndSystem();

### Section-specific formatting (`Admin.URL`)

The `Admin` object provides a set URL properties to reflect the current URL. This allows us to execute code when specific sections or pages are being rendered. The URL object provides the following properties:

* `URL.root`: The protocol and domain root of your site
* `URL.symphony_root`: The root of the Symphony backend
* `URL.page`: Which page is being viewed, such as `publish`, `blueprints`, `system`
* `URL.section`: When viewing `publish` pages, this is the handle of the Section being viewed
* `URL.mode`: When viewing `publish` pages, this is the form mode, either `new` or `edit`
* `URL.entry`: When viewing `publish` pages in `edit` mode, this is the Entry System ID
* `URL.filter.field`: The field name of a filter applied to the `publish` index
* `URL.filter.value`: The value of a filter applied to the `publish` index

These can be used as follows:

	if (Admin.URL.section == 'articles' && Admin.URL.mode == 'index') {
		// run this only when viewing the Publish index table for the Articles section
	}
	
	if (Admin.URL.section == 'comments' && (Admin.URL.mode == 'new' || Admin.URL.mode == 'edit')) {
		// run this only when viewing a New or Edit form in the Comments section
	}

### User-specific formatting (`Admin.User`)

We can also obtain basic information about the current Author/Developer user. The `User` object exposes two properties:

* `User.id`: The ID of this user
* `User.name`: User's full name (First name + Last name)

For example:

	if (Admin.User.id == 1) {
		alert('Welcome '+ Admin.User.name);
	}

### Changing the Section navigation

"Navigation Groups" were added in Symphony 2.0.2/2.0.3, allowing the developer to add Sections to names groups. These are converted to a vertical navigation in the left column. You can remove specific groups:

	Admin.removeNavigationGroup('Blueprints');

Adding additional items to the navigation may follow in a future release.

### Publish Filtering

The [Publish Filtering extension]() is great for most situations, but it isn't easy to customise. The `Admin` object however allows the developer to add "Filter Tabs" along the top of the Publish index table. These act the same way as the Publish Filtering extension, but allow for pre-defined filters in the form:

	Admin.addFilterTab(label, handle, filter);

For example:	

	Admin.addFilterTab('Approved', 'moderation', 'approved');

Results in a filter URL in the form (which could be used to filter a Select Box named "Moderation" for "Approved" entries only)

	?filter=moderation:approved

Filters can be removed by adding a filter tab with no filters:

	Admin.addFilterTab('All Entries', '', '');

A search filter can be appended, providing free-text searching in pre-defined fields. The following example adds a "Search" tab which will search within both the Title and Body fields only.

	Admin.addSearchFilterTab('Search', 'title,body');

### Removing table columns

Sometimes the Publish index table can become unwieldy if there are many fields. In particular, some flavours of the Select Box Link do not allow you to disable the column of entry counts. Individual columns can be removed from the DOM by referencing their full column heading label:

	// remove Images and Comments column from the Articles publish index
	if(Admin.URL.section == 'articles' && Admin.URL.mode == 'index') {	
		Admin.removeTableColumn('Images');
		Admin.removeTableColumn('Comments');
	}

### Adding table columns

A scarily powerful feature is the ability to add new columns to the publish table. Using Paul Garrett's [Database Manipulator extension]() (which provides a simplified API for querying Sections) you can define a new column and what data it should contain. DBM queries Symphony, and the results are returned via a lightweight AJAX request.

Adding a column requires the developer to build a `column` object with the following properties:

* `title`: The column heading text
* `section`: handle of the Section to query
* `fields`: array of fields to return from this section (usually only one)
* `filters`: 'WHERE' clauses for the query (name/value pair where value can be resolved from the DOM)
* `conditions`: additional conditions supported by DBM (name/value pair)
* `result`: type of result, currently only `count` and `time` supported

For example:

	Admin.addTableColumn({
		'title': 'Approved Comments',
		'section': 'comments',
		'fields': ['system:id'],
		'filters': [
			{'article': 'row:system:id'},
			{'moderation': 'approved'}
		],
		'conditions': [
			{'limit': 999}
		],
		'result': 'count'
	});

Imagine this column added to an Articles publish index. This will add a column with a heading of "Approved Comments" with the intention of showing a count of related Comments entries which have a Select Box named "Moderation" with the value "Approved". In pseudo-SQL this is the equivalent to:

	SELECT count(system:id) FROM comments WHERE article='row:system:id' AND moderation='approved' LIMIT 999;

The two filters above are subtly different. The "moderation" filter accepts a string value of "approved", whereas the "article" value needs to be resolved by looking at the DOM of each table row on the page. This is indicated by prepending "row:" to the string. The following are supported:

* `row:system:id` returns the row System ID
* `row:{handle}` returns the full value of 

### User-specific features
* Filter by user
* Hide Author select