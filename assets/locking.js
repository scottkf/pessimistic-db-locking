var Locking;
var lockingBox;
(function($) {
	/* thank you github-voice :) */

	lockingBox = {

		Options: {
			html: '\
	    <div id="locking-wrapper"> \
	      <h1>Alert!</h1> \
	      <div id="locking"> \
	        <p class="description"></p> \
	        <p class="call-to-action"><a href="#"></a></p> \
	      </div> \
	    </div>',
			text: {
				description: "The lock on this entry just expired!",
	      callToAction: "&raquo; Renew your lease"
			}
	
		},

		init: function(msg) {
			$('body')
        .append('<div id="locking-overlay"></div>')
        .find('#locking-overlay')
          .css({
            width   : $(window).width(),
            height  : $(document).height(),
            opacity : 0.75
          });
					
		  if (msg == null) {
				msg = this.Options.text.description;
			}
      $('body')
        .append(this.Options.html)
        .find('#locking')
          .find('p.description').html(msg).end()
          .find('p.call-to-action a')
            .html(this.Options.text.callToAction)
						.attr('onclick', "javascript:Locking.forceRenew('"+Locking.Data.entry_id+"','"+Locking.Data.author_id+"');")
						.attr('href', "javascript:Locking.forceRenew('"+Locking.Data.entry_id+"','"+Locking.Data.author_id+"');").end();
            // .attr('href', '#');

			this.updatePosition();
		},
		
		updateMessage: function(msg) {
			
			if ($('#locking-overlay').length <= 0)
				this.init();
			$('#locking > p.description').html(msg).end();
			
		},
		updatePosition: function() {
	    $('#locking-wrapper').css('margin-top', -1 * ($('#locking-wrapper').height() / 2));
	  }

		
	},
	
	/* thank you nick dunn ;p */
	Locking = {

    URL: {
      root: null,
      symphony_root: null,
    },

		Data: {
			entry_id: null,
			author_id: null
		},
	
		init: function() {
			
			var h1 = $('h1:first');
			var root = h1.find('a').attr('href');
      var url = window.location.href.replace(root, '').split('/');
      
      this.URL.root = root;
      this.URL.symphony_root = this.URL.root + 'symphony/';
      this.URL.page = url[1];

		},

		disableForm: function() {
			$("button").attr("disabled", true);
			$("input").attr("disabled", true);
			$("textarea").attr("disabled", true);
		},
			
		renewLock: function(entry_id, author_id, time) {
			var url = this.URL.symphony_root;
			this.Data.entry_id = entry_id;
			this.Data.author_id = author_id;
			interval = setInterval(function() {
				data = "entry_id="+entry_id+"&author_id="+author_id;
	      $.post(url + 'extension/pessimistic_db_locking/ajax_locking/', data, function(response){
					if (response == '"expired"') {
						lockingBox.updateMessage("The lock on this entry doesn't exist (did you leave this window open?)");
					}
					else if (response == '"expired-lifetime"') {
						lockingBox.updateMessage('Your lock expired (did you leave this window open?)');
					}
					else if (response == '"true"'){
						// we renewed
					}
					// someone else owns it
					else {
						lockingBox.updateMessage(response+' owns the lease now!');
					}
	        console.log(response);
	      });				
			}, time*1000);
		},
		
		forceRenew: function(entry_id, author_id, time) {
			data = "entry_id="+entry_id+"&author_id="+author_id+"&force=true";
			$.post(this.URL.symphony_root + 'extension/pessimistic_db_locking/ajax_locking/', data, function(response) {
	      $('#locking-overlay, #locking-wrapper').remove();
			})			
		}
	
		
	}
	
})(jQuery.noConflict());

jQuery(document).ready(function() {
	Locking.init();
	

		
});