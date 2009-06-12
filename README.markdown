This README is incomplete.

# Pessimistic Database Locking

* Version: 0.1
* Author: Scott Tesoriere <http://github.com/scottkf/>
* Build Date: 2009-06-12
* Requirements: Symphony 2.0.2 (with jQuery 1.3)
								**JAVASCRIPT JAVASCRIPT JAVASCRIPT**

## Installation

1. Download and move the 'pessimistic_db_locking' folder to your Symphony 'extensions' folder.
2. Enable it by selecting "Pessimistic DB Locking" in the list and choose Enable from the with-selected menu, then click Apply.

## Usage

This extension adds row-level, pessimistic (active) locking to symphony. It is automatically added to the backend once installed, and exposes a filter to the frontend that if selected adds the same ability to the front as it does the back. 

It works as follows:

1. User edits an entry and opens the edit page
2. User obtains a lock on the entry, the lock expires after X amount of time (configurable), and renews (via JS) after X amount of time (configurable)
3. After successful save, the lock is released OR the lock will be released after X amount of time (like if you went idle on the screen), also configurable


## Creation

This extension is based on many things, including Nick Dunn's Custom Admin extension, GitHub Voice, and Rowan's email template filter extension. Without any of them I would never been able to do this as easily.

## TODO

- Add an unload event to release the entry
- Finish the frontend filter that does the same crap as the backend (figure out how to inject the JS)

## Changelog

### June 12th, 2009
- Added check for locks on pre entry creation 
- Remove the lock on post entry creation)
