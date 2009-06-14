This README is incomplete.

# Pessimistic Database Locking

* Version: 0.5
* Author: Scott Tesoriere <http://github.com/scottkf/>
* Build Date: 2009-06-14
* Requirements: Symphony 2.0.2 (with jQuery 1.3), jQuery, **JAVASCRIPT**

## Installation

1. Download and move the 'pessimistic_db_locking' folder to your Symphony 'extensions' folder.
2. Enable it by selecting "Pessimistic DB Locking" in the list and choose Enable from the with-selected menu, then click Apply.

## Usage

This extension adds row-level, pessimistic (active) locking to symphony. It is automatically added to the backend once installed, and exposes a filter to the frontend that if selected adds the same ability to the front as it does the back. 

It works as follows:

1. User edits an entry and opens the edit page
2. User obtains a lock on the entry, the lock expires after X amount of time (configurable), and renews (via JS) after X amount of time (configurable)
3. After successful save, the lock is released OR the lock will be released after X amount of time (like if you went idle on the screen), also configurable

The times are configurable, but only through extension.driver.php currently, newer versions will add preferences.

### On the backend

All you have to do is enable the extension, and it works automatically.

### On the frontend (using an event filter called 'lock-entry')

This will only work on an event which is being edited, i.e., it has an `<input name="id" type="hidden" value="<something">` field. Unfortunately, all the leasing, and renewing must be handled by javascript, so include the following to deal with that on the page you're working on. This is only a basic example using jQuery:

    <script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.3.2/jquery.min.js"></script>
    <script type="text/javascript" src="{$root}/extensions/pessimistic_db_locking/assets/locking.js"></script>
    <script type="text/javascript">
    	<xsl:comment>
    		jQuery(document).ready(function() {

    			/* init takes 3 parameters
    					the path to your symphony site (http://example.com)
    					the id of the entry being edited
    					the id of the author */
    			Locking.init('<xsl:value-of select="$root"/>', '<xsl:value-of select="'1130'"/>','<xsl:value-of select="events/login-info/@id"/>' );

    			Locking.setupLock(respond);
    			function respond(response) {
    				switch(response) {

    				case '"expired-lifetime"':
    					/* offer the user an option to renew the lease incase they're not idle
    					 		it can be renewed by doing the following:
    							Locking.forceRenewCallback(function response(data) {})
    					  	where data is one of the following cases in this example */
    					break

    				case '"expired"':
    					/* this will occur after the "expired-lifetime" case, when no lock exists, 
    							so chances are they're still idle */
    					alert('no lock exists for this entry');
    					break

    				case '"true"':
    					/* renewLockCallback takes 2 parameters
    								how often to renew the lease
    								the callback to be used (in this example, it is 'respond') */
    					Locking.renewLockCallback('<xsl:value-of select="events/lock-entry/renew_every"/>', respond);
    					// we're just renewing here
    					break

    				default:
    					// if someone else owns the lease, do something. in this example, I disable all the form controls
    					Locking.disableForm();
    					alert('currently owned by ' + response);
    				} 
    			}
    		});
    	</xsl:comment>
    </script>

If a user tries to save an entry with this event attached, one of the following might result:

    <filter name="lock-entry" status="passed" />
    <filter name="lock-entry" status="failed">This lease is currently owned by "some authors name".</filter>

## Creation

This extension is based on many things, including Nick Dunn's Custom Admin extension, GitHub Voice, and Rowan's email template filter extension. Without any of them I would never been able to do this as easily.

## TODO

- Add preferences for which sections we should lock, and all the timing (how often it renews, how long it lasts, when it permanently expires)
- Add an unload event to release the entry

## Changelog

### June 14th, 2009
- Finished the frontend filter, how to use is available above

### June 12th, 2009
- Added check for locks on pre entry creation 
- Remove the lock on post entry creation)
