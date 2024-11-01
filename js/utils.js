var UexcommUtils = function(opts){
	var $ = jQuery;
	
	var html = '';
	html += '<div id="uexc_utils-dialog">';
	html += '   <div class="uexc-bg"></div>';
	html += '   <div class="uexc-bd">';
	html += '      <a href="#" title="Close" class="uexc-close-button"><br></a>';
	html += '      <iframe frameborder="0" src="about:blank"></iframe>';
	html += '   </div>';
	html += '</div>';
		
	var dialog = $(html).appendTo('body');
	var dialog_bg = dialog.find('.uexc-bg');
	var dialog_body = dialog.find('.uexc-bd');
	var iframe = dialog.find('iframe');
	var close_button = dialog.find('.uexc-close-button');
	var action, comment_id, is_updated, is_deleted;
	
	var open_dialog = function(href){
		is_updated = is_deleted = false;
		iframe[0].src = href;
		
		if( action=='edit' ){
			var w = 640;
			var h = $(window).height()-60;
		}else{
			var w = 260;
			var h = 180;
		}
		dialog_body.css({width: w, marginLeft: -(w/2), height: h, marginTop: -(h/2)});
		dialog.show();
	}
	
	var close_dialog = function(){
		iframe[0].src = 'about:blank';
		dialog.hide();
		
		if( is_updated || is_deleted ){
			location.hash = '#comment-'+comment_id;
			location.reload();
		}
	}

	$('a.uexc-edit-link').click(function(){
		action = 'edit';
		var match = /uexc_edit=(\d+)/.exec(this.href);
		comment_id = match ? match[1] : '';
		open_dialog(this.href);
		return false;
	});
	
	$('a.uexc-delete-link').click(function(){
		action = 'delete';
		var match = /uexc_delete=(\d+)/.exec(this.href);
		comment_id = match ? match[1] : '';
		open_dialog(this.href);
		return false;
	});
	
	close_button.click(function(){
		close_dialog();
		return false;
	});
	
	this.close_dialog = close_dialog;
	
	this.update_complete = function(){
		is_updated = true;
	}
	this.delete_complete = function(){
		is_deleted = true;
		//close_dialog();
	}
	
}


jQuery(function(){
	window.uexc_utils = new UexcommUtils();
	
	// move form-submit to the end
	jQuery('.form-submit').each(function(){
		var f = jQuery(this).parents('form:eq(0)');
		var match = /\/wp-comments-post.php/.exec(f[0].action);
		if( match ){
			jQuery(this).appendTo( f );
			return;
		}
	});
});




