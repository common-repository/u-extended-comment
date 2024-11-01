<?php

class UexcommUtils {

var $id = 'uexc_utils';
var $editable_cap_name = 'uexc_editable';
var $deletable_cap_name = 'uexc_deletable';
var $private_readable_cap_name = 'uexc_private_readable';

function UexcommUtils(){
	add_action( 'uexc_activation', array(&$this, 'activation'));
	add_action( 'uexc_uninstall', array(&$this, 'uninstall'));
	add_action( 'uexc_loaded', array(&$this, 'loaded'));
}

function loaded(){
	global $uexc;
	$uexc->plugins->utils = $this;
	$opts = $this->get_option();
	
	if( is_admin() ){
		add_action( 'uexc_admin_init', array(&$this, 'admin_init') );
		add_action( 'uexc_admin_menu', array(&$this, 'admin_menu') );
		
	}else{
		add_action( 'template_redirect', array(&$this, 'template_redirect') );
		add_filter( 'comment_text', array(&$this, 'add_links_automatically'), 100);
		
		// edit & delete
		add_action( 'wp', array(&$this, 'update_comment'));
		add_action( 'wp', array(&$this, 'delete_comment'));
		add_filter( 'template_include', array(&$this, 'ed_template_include'));
		add_action( 'uexc_edit_before_content_field', array(&$this, 'check_form_error'));
		add_action( 'uexc_delete_before_submit_field', array(&$this, 'check_form_error'));
	
		// private
		add_filter( 'get_comment_text', array(&$this, 'private_comment_text'));
		if( !empty($opts['privatable']) ) {
			add_action( 'comment_post', array(&$this, 'add_private_meta'));
			add_action( 'uexc_edit_update', array(&$this, 'add_private_meta'));
			add_action( 'comment_form', array(&$this, 'add_privatable_field'), 100 );
			add_action( 'uexc_edit_before_submit_field', array(&$this, 'add_privatable_field') );
		}
		
		wp_register_style($this->id.'-editor-content', $uexc->url.'css/editor-content.css', '', $uexc->ver);
		wp_register_style($this->id.'-utils', $uexc->url.'css/utils.css', '', $uexc->ver);
		wp_register_script($this->id.'-utils', $uexc->url.'js/utils.js', array('jquery'), $uexc->ver);
	}
}

function template_redirect(){
	global $uexc;
	if( is_singular() AND comments_open() ){
		wp_enqueue_style($this->id.'-utils');
		wp_enqueue_script($this->id.'-utils');
	}
}


/* edit & delete
--------------------------------------------------------------------------------------- */

function ed_template_include($t){
	global $uexc;
	if( isset($_GET['uexc_edit']) AND absint($_GET['uexc_edit']) ){
		$this->edit_form();
		return false;
	}
	if( isset($_GET['uexc_delete']) AND absint($_GET['uexc_delete']) ) {
		$this->delete_form();
		return false;
	}
	return $t;
}


function edit_form(){
	global $uexc, $post;
	
	$comment_id = absint($_GET['uexc_edit']);
	$comment = get_comment($comment_id);
	
	if( isset($_POST['uexc_edit_submit']) ){
		$content = $_POST['uexc_edit_content'];
	}else{
		$content = $comment->comment_content;
	}
	$content = stripcslashes($content);
	$content = apply_filters('uexc_edit_content_pre', $content);
	
	$comment_link = get_comment_link($comment_id);
	$post_link = get_permalink($post->ID);
	$cancel_link = '<a href="'.$post_link.'" class="cancel">'.__('Cancel').'</a>';
	
	$textarea_attrs = array(
		'name' 	=> 'uexc_edit_content',
		'id' 	=> 'uexc_edit_content',
		'cols' 	=> '60',
		'rows' 	=> '14',
		'class' => '',
	);
	$textarea_attrs = apply_filters('uexc_edit_textarea_attr', $textarea_attrs);
	$textarea_attr = '';
	foreach($textarea_attrs as $k=>$v)
		$textarea_attr .= " {$k}=\"{$v}\" ";
	
	$this->get_header('edit'); 
	?>
	
	<h1>Edit Comment</h1>
	
	<?php
	if( !$this->user_can_edit($comment_id) ) {
		echo '<p class="'.$this->id.'-error">'.__("You do not have sufficient permissions to edit this comment.", $uexc->id).'</p>';
		echo $cancel_link;
		$this->get_footer();
	}
	if( !empty($_POST[$this->id.'_updated']) ){
		echo '<p class="'.$this->id.'-message">'.$_POST[$this->id.'_updated'].'</p>';
		echo '<script>update_complete()</script>';
	}
	?>
	
	<form method="post" id="uexc_edit-form" class="<?php echo $this->id?>-form">
		<?php wp_nonce_field($this->id.'_nonce'); ?>
		<input type="hidden" name="uexc_edit_id" value="<?php echo $comment_id; ?>" />
		<input type="hidden" name="uexc_redirect_to" id="uexc_redirect_to" value="<?php echo $comment_link?>" />
		
		<?php do_action('uexc_edit_before_content_field', $comment); ?>
		
		<textarea <?php echo $textarea_attr?>><?php echo esc_textarea($content)?></textarea>
		
		<?php do_action('uexc_edit_after_content_field', $comment); ?>
		<?php do_action('uexc_edit_before_submit_field', $comment); ?>
	
		<div class="form-submit">
			<input type="submit" name="uexc_edit_submit" id="uexc_edit_submit" value="<?php _e('Update');?>" /> 
			<span class="or">or</span>
			<?php echo $cancel_link?>
		</div>
	</form>
	
	<?php 
	$this->get_footer('edit');
}


function delete_form(){
	global $uexc, $post;
	
	$comment_id = absint($_GET['uexc_delete']);
	$comment = get_comment($comment_id);
	$replies = get_comments( array('parent'=>$comment_id, 'count'=>true) );
	$post_link = get_permalink($post->ID);
	$cancel_link = '<a href="'.$post_link.'" class="cancel">'.__('Cancel').'</a>';
	
	$this->get_header('delete'); 
	?>
	
	<h1>Delete Comment</h1>
	
	<?php
	if( !empty($_POST[$this->id.'_deleted']) ){
		echo '<p class="'.$this->id.'-message">'.$_POST[$this->id.'_deleted'].'</p>';
		echo $cancel_link;
		echo '<script>delete_complete()</script>';
		$this->get_footer();
	}
	
	if( !$this->user_can_delete($comment_id) ) {
		echo '<p class="'.$this->id.'-error">'.__("You do not have sufficient permissions to delete this comment.", $uexc->id).'</p>';
		echo $cancel_link;
		$this->get_footer();
	}
	
	if( $replies ){ 
		echo '<p class="'.$this->id.'-error">'.__("The replied comment can't be deleted", $uexc->id).'</p>';
		echo $cancel_link;
		$this->get_footer();
	}
	?>
		
	<form method="post" id="uexc_delete-form" class="<?php echo $this->id?>-form">
		<?php wp_nonce_field($this->id.'_nonce'); ?>
		<input type="hidden" name="uexc_delete_id" value="<?php echo $comment_id?>" />
		<input type="hidden" name="uexc_redirect_to" id="uexc_redirect_to" value="<?php echo $post_link?>" />
		
		<p class="<?php echo $this->id?>-message"><?php _e('You are about to delete this comment.', $uexc->id)?></p>
		
		<?php do_action('uexc_delete_before_submit_field', $comment); ?>
		
		<div class="form-submit">
			<input type="submit" name="uexc_delete_submit" id="uexc_delete_submit" value="<?php _e('Delete');?>" />
			<span class="or">or</span>
			<?php echo $cancel_link?>
		</div>
	</form>
	
	<?php
	$this->get_footer('delete');
}


function get_header($page=''){
global $uexc;
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>" />
<?php wp_print_styles($this->id.'-editor-content');?>
<?php wp_print_styles($this->id.'-utils');?>
<?php wp_print_scripts('jquery');?>
<?php do_action($this->id.'_head_'.$page); ?>
<script>
if( top.uexc_utils ){
	jQuery(function(){
		jQuery('#uexc_redirect_to').val('');
		jQuery('a.cancel').text('<?php _e('Close', $uexc->id)?>').click(function(){
			top.uexc_utils.close_dialog();
			return false;
		});
	});
	var update_complete = function(){
		top.uexc_utils.update_complete();
	}
	var delete_complete = function(){
		top.uexc_utils.delete_complete();
	}
}
</script>
</head>
<body class="<?php echo $this->id?>">
<?php
}

function get_footer($page=''){
?>
<?php do_action($this->id.'_footer_'.$page); ?>
</body>
</html>
<?php
exit();
}


function check_form_error(){
	if( isset($_POST[$this->id.'_errors']) )
		echo "<p class='{$this->id}-error'>".$_POST[$this->id.'_errors']."</p>";
}

function update_comment(){
	global $post, $uexc;
	
	if( empty($_POST['uexc_edit_submit']) )
		return false;
	
	$comment_id = !empty($_POST['uexc_edit_id']) ? absint($_POST['uexc_edit_id']) : '';
	$comment_content = !empty($_POST['uexc_edit_content']) ? $_POST['uexc_edit_content'] : '';
		
	if( ! wp_verify_nonce($_POST['_wpnonce'], $this->id.'_nonce') ) {
		$_POST[$this->id.'_errors'] = __('Error: your nonce did not verify.', $uexc->id);
		return false;
	}
	
	if( ! $this->user_can_edit($comment_id) ){
		$_POST[$this->id.'_errors'] = __('Error: you do not have permission to edit this comment.', $uexc->id);
		return false;
	}
	
	if( ! $comment_content ) {
		$_POST[$this->id.'_errors'] = __('Error: please enter content.', $uexc->id);
		return false;
	}		
	
	$comment_data = array('comment_ID' => $comment_id, 'comment_content' => $comment_content);
	wp_update_comment( $comment_data );
	
	do_action('uexc_edit_update', $comment_id);
				
	if( !empty($_POST['uexc_redirect_to']) ){
		wp_redirect( $_POST['uexc_redirect_to'] );
	}else{
		$_POST[$this->id.'_updated'] = __('Comment updated.', $uexc->id);
	}
}


function delete_comment(){
	global $post, $uexc;
	
	if( empty($_POST['uexc_delete_submit']) || empty($_POST['uexc_delete_id']) )
		return false;
	
	$comment_id = absint($_POST['uexc_delete_id']);
	
	if( ! wp_verify_nonce($_POST['_wpnonce'], $this->id.'_nonce') ) {
		$_POST[$this->id.'_errors'] = __('Error: your nonce did not verify.', $uexc->id);
		return false;
	}
	
	if( ! $this->user_can_delete($comment_id) ){
		$_POST[$this->id.'_errors'] = __('Error: you do not have permission to delete this comment.', $uexc->id);
		return false;
	}
	
	wp_delete_comment($comment_id, true);
	
	do_action('uexc_edit_delete', $comment_id);
	
	if( !empty($_POST['uexc_redirect_to']) ){
		wp_redirect( $_POST['uexc_redirect_to'] );
	}else{
		$_POST[$this->id.'_deleted'] = __('Comment deleted.', $uexc->id);
	}
}








/* links
--------------------------------------------------------------------------------------- */

function add_links_automatically($comment_text){
	global $comment, $uexc;
		
	if( isset($comment->comment_ID) ) {
		$opts = $this->get_option();
		if( !empty($opts['auto_insert_links']) ){
			$links = '';
			$links.= $this->get_edit_link(__('Edit', $uexc->id));
			$links.= $this->get_delete_link(__('Delete', $uexc->id));
			if( $links ) 
				$comment_text .= '<p class="'.$this->id.'-links">'.$links.'</p>';
		}
	}
	
	return $comment_text;
}

function get_edit_link($label='Edit', $before=' ', $after=' '){
	if( $url = $this->get_link_url('edit') )
		return $before."<a href='{$url}' class='uexc-edit-link'>{$label}</a>".$after;
}

function get_delete_link($label='Delete', $before=' ', $after=' '){
	if( $url = $this->get_link_url('delete') )
		return $before."<a href='{$url}' class='uexc-delete-link'>{$label}</a>".$after;
}

function get_link_url($action){
	global $comment;
	
	if( ! $this->user_can($action, $comment->comment_ID) ) 
		return false;
	
	$url = get_comment_link($comment->comment_ID);
	$url = add_query_arg('uexc_'.$action, $comment->comment_ID, $url);
	return $url;
}









/* permission
--------------------------------------------------------------------------------------- */

function user_can_edit($comment_id){
	return $this->user_can('edit', $comment_id);
}

function user_can_delete($comment_id){
	return $this->user_can('delete', $comment_id);
}

function user_can($action, $comment_id){
	global $comment, $current_user;
	
	if( empty($comment_id) ) 
		return false;
	
	if( empty($comment) ) 
		$comment = get_comment($comment_id);
	
	if( empty($current_user) )
		$current_user = wp_get_current_user();
	
	$commenter = wp_get_current_commenter();
	
	$opts = $this->get_option();
	
	switch($action){
		case 'edit':
			if( empty($opts['editable']) )
				return false;
			
			if( empty($opts['editable_roles']) || (!empty($opts['editable_roles']) AND current_user_can($this->editable_cap_name)) ){
				if( $current_user->user_email==$comment->comment_author_email ) 
					return true;
			}
			break;
	
		case 'delete':
			if( empty($opts['deletable']) )
				return false;
			
			if( empty($opts['deletable_roles']) || (!empty($opts['deletable_roles']) AND current_user_can($this->deletable_cap_name)) ){
				if( $current_user->user_email==$comment->comment_author_email ) 
					return true;
			}
			break;
	
		case 'private':
			if( current_user_can($this->private_readable_cap_name) )
				return true;
			
			if( $current_user->user_email==$comment->comment_author_email ) 
				return true;
			
			if( $comment->comment_parent>0 ){
				if( $comment_parent = get_comment($comment->comment_parent) ){
					if( $p_user_id = $comment_parent->user_id ){
						$p_user = get_userdata($p_user_id);
						if( $p_user->user_email==$current_user->user_email ) 
							return true;
					}else{
						if( $commenter 
							AND $commenter['comment_author']==$comment_parent->comment_author 
							AND $commenter['comment_author_email']==$comment_parent->comment_author_email ) 
							return true;
					}
				}
			}
			break;
	}
	
	if( current_user_can('edit_comment', $comment_id) ) 
		return true;
	
	if( $comment 
		AND $commenter['comment_author']==$comment->comment_author 
		AND $commenter['comment_author_email']==$comment->comment_author_email ) 
		return true;
	
	return false;
}











/* private
--------------------------------------------------------------------------------------- */

function add_privatable_field($comment){
	global $uexc;
	$key = $this->id.'_private';
	$is_private = '';
	if( isset($_POST[$key]) ){
		$is_private = $_POST[$key];
	}else{
		if( isset($comment->comment_ID) )
			$is_private = get_comment_meta($comment->comment_ID, $key, true);
	}
	?>
	<p class="<?php echo $this->id?>_private">
		<label><input id="<?php echo $key?>" name="<?php echo $key?>" type="checkbox" value="1" <?php checked(!empty($is_private))?> />
		<?php _e('Private', $uexc->id)?></label>
	</p>
	<?php
}

function add_private_meta($comment_id){
	$key = $this->id.'_private';
	delete_comment_meta($comment_id, $key);
	if( !empty($_POST[$key]) )
		add_comment_meta($comment_id, $key, $_POST[$key], true);
}

function is_private($comment_id=''){
	global $comment;
	$comment_id = $comment_id ? $comment_id : $comment->comment_ID;
	return (get_comment_meta($comment_id, $this->id.'_private', true)) ? true : false;
}

function user_can_read_private($comment_id){
	return $this->user_can('private', $comment_id);
}

function private_comment_text($comment_text){	
	global $comment, $uexc;
	if( isset($comment->comment_ID) ){
		$comment_id = absint($comment->comment_ID);
		$comment->uexc_private = false;
		$comment->uexc_private_readable = false;
		
		if( $this->is_private() ){
			$comment->uexc_private = true;
			$private_icon = "<span class='{$this->id}-private-icon'></span>";
			
			if( $this->user_can_read_private($comment_id) ) {
				$comment->uexc_private_readable = true;
				$comment_text = $private_icon.$comment_text;
			} else {
				$comment_text = "<p class='{$this->id}-private'>{$private_icon}".__('Private comment.', $uexc->id)."</p>";
			}
		}
	}
	return $comment_text;
}











/* back-end
--------------------------------------------------------------------------------------- */

function get_option(){
	$options = array (
		'editable' => '',
		'deletable' => '',
		'privatable' => '',
		'auto_insert_links' => '1',
		'editable_roles' => array(),
		'deletable_roles' => array(),
		'private_readable_roles' => array('administrator'),
	);
	
	$saved = get_option($this->id);
	
	if ( !empty($saved) ) 
		foreach ($options as $key=>$val) 
			$options[$key] = isset($saved[$key]) ? $saved[$key] : $val;
		
	if( $saved != $options )
		update_option($this->id, $options);
	
	return $options;
}

function activation(){
	$this->get_option();
}

function uninstall(){
	delete_option($this->id);
}

function admin_init(){
	register_setting($this->id.'_options', $this->id, array( &$this, 'admin_page_vailidate'));
}

function admin_menu(){
	global $uexc;
	
	add_submenu_page( 
		$uexc->id, 
		__('Utils', $uexc->id), 
		__('Utils', $uexc->id), 
		'manage_options', 
		$this->id, 
		array(&$this, 'admin_page') 
	);
}

function admin_page(){
	global $uexc;
	
	$opts = (object) $this->get_option();
	?>
	<div class="wrap">
		<?php screen_icon("options-general"); ?>
		
		<h2>U Ex-Comment &raquo; <?php _e('Utils', $uexc->id)?></h2>
		
		<?php settings_errors( $this->id ) ?>
		
		<form action="options.php" method="post">
			<?php settings_fields($this->id.'_options'); ?>
			<table class="form-table">
			<tr>
				<th><strong><?php _e('Editable', $uexc->id)?></strong></th>
				<td>
					<label><input type="checkbox" name="<?php echo $this->id?>[editable]" id="editable" value="1" <?php checked($opts->editable, '1')?> > 
					<strong><?php _e('Enable', $uexc->id)?></strong></label>
					
					<div class="description-box">
						<?php _e('Permission restriction', $uexc->id)?> : <?php uexc_role_checklist($this->id.'[editable_roles]', $opts->editable_roles)?>
						<p class="description"><?php _e('If you want to allow all users and visitors to use, please uncheck all.', $uexc->id);?></p>
					</div>
				</td>
			</tr>
			<tr>
				<th><strong><?php _e('Deletable', $uexc->id)?></strong></th>
				<td>
					<label><input type="checkbox" name="<?php echo $this->id?>[deletable]" id="deletable" value="1" <?php checked($opts->deletable, '1')?> > 
					<strong><?php _e('Enable', $uexc->id)?></strong></label>
					
					<div class="description-box">
						<?php _e('Permission restriction', $uexc->id)?> : <?php uexc_role_checklist($this->id.'[deletable_roles]', $opts->deletable_roles)?>
						<p class="description"><?php _e('If you want to allow all users and visitors to use, please uncheck all.', $uexc->id);?></p>
						<p class="description"><?php _e('If comment is an ancestor(replied comment), this function would be disabled.', $uexc->id)?></p>
					</div>
				</td>
			</tr>
			<tr>
				<th></th>
				<td>
					<strong><?php _e('How to insert links', $uexc->id)?> :</strong>
					<label><input type="checkbox" name="<?php echo $this->id?>[auto_insert_links]" value="1" <?php checked($opts->auto_insert_links, '1')?> > 
					<?php _e('Automatically', $uexc->id)?></label>
					
					<div class="description-box description">
						<p><strong><?php _e('Using template tags', $uexc->id)?></strong></p>
						<p>
							<?php _e("If you'd like to insert links manually, add the following codes in your comments template.", $uexc->id)?>
							<br><code>&lt;?php if ( function_exists('<?php echo $uexc->id?>_edit_link') ){ <?php echo $uexc->id?>_edit_link(); } ?&gt;</code>
							<br><code>&lt;?php if ( function_exists('<?php echo $uexc->id?>_delete_link') ){ <?php echo $uexc->id?>_delete_link(); } ?&gt;</code>
						</p>
						<br>
						
						<p><strong><?php _e('Show Usage &amp; Parameters', $uexc->id)?></strong></p>
						
						<?php _e('Edit link', $uexc->id)?> : <code><?php echo $uexc->id?>_edit_link($label, $before, $after, $echo);</code>
						<p class="description">
							$lable: (string)(optional) Default: 'Edit'
							<br>$before: (string)(optional) Default: None
							<br>$after: (string)(optional) Default: None
							<br>$echo: (boolean)(optional) Default: true
						</p>
						
						<?php _e('Delete link', $uexc->id)?> : <code><?php echo $uexc->id?>_delete_link($label, $before, $after, $echo);</code>
						<p class="description">
							$lable: (string)(optional) Default: 'Delete'
							<br>$before: (string)(optional) Default: None
							<br>$after: (string)(optional) Default: None
							<br>$echo: (boolean)(optional) Default: true
						</p>
					</div>
				</td>
			</tr>
			<tr>
				<th><strong><?php _e('Privatable', $uexc->id)?></strong></th>
				<td>
					<label><input type="checkbox" name="<?php echo $this->id?>[privatable]" value="1"  <?php checked($opts->privatable, '1')?> >
					<strong><?php _e('Enable', $uexc->id)?></strong></label>
					
					<div class="description-box">
						<p><strong><?php _e('Who can read private comments?', $uexc->id)?></strong></p>
						<ol>
							<li><?php _e('Commenter', $uexc->id)?>.</li>
							<li><?php _e('Parent comment author', $uexc->id)?>.</li>
							<li><?php _e('Post author', $uexc->id)?>.</li>
							<li><?php uexc_role_checklist($this->id.'[private_readable_roles]', $opts->private_readable_roles)?></li>
						</ol>
					</div>
					
					<div class="description-box description">
						- <?php _e("Unless deactivate or delete this plugin, even if you would change from 'enable' to 'disable', private property is retained.", $uexc->id)?>
						<br>
						- <?php printf(__('If you are using [%1$s] Widget, I recommend [%2$s] that this plugin provide. This widget protect the private comment shown.', $uexc->id), __('Recent Comments'), __('Recent Comments for U Ex-comment', $uexc->id))?>
					</div>
				</td>
			</tr>
			
			</table>
			
			<p class="submit">
				<input name="Submit" type="submit" class="button-primary" value="<?php esc_attr_e(__('Save Changes')); ?>" />
			</p>
		</form>
		
	</div>
	
	<style>
	.children { margin-left: 17px; margin-top: 5px; }
	.description-box { border: 1px dashed #ccc; background:#f8f8f8; padding: 6px 10px; margin:10px 0; }
	</style>
	<?php
}


function admin_page_vailidate($input){
	$r = array();
	$r['editable'] = !empty($input['editable']) ? '1' : '';
	$r['deletable'] = !empty($input['deletable']) ? '1' : '';
	$r['auto_insert_links'] = !empty($input['auto_insert_links']) ? '1' : '';
	$r['privatable'] = !empty($input['privatable']) ? '1' : '';
	$r['editable_roles'] = !empty($input['editable_roles']) ? $input['editable_roles'] : '';
	$r['deletable_roles'] = !empty($input['deletable_roles']) ? $input['deletable_roles'] : '';
	$r['private_readable_roles'] = !empty($input['private_readable_roles']) ? $input['private_readable_roles'] : '';
	
	uexc_set_cap($r['editable_roles'], $this->editable_cap_name);
	uexc_set_cap($r['deletable_roles'], $this->deletable_cap_name);
	uexc_set_cap($r['private_readable_roles'], $this->private_readable_cap_name);
	
	add_settings_error($this->id, 'settings_updated', __('Settings saved.'), 'updated');
	return $r;
}


}

new UexcommUtils();



/* template tags
--------------------------------------------------------------------------------------- */

function uexc_edit_link($label='Edit', $before='', $after='', $echo=true){
	global $uexc;
	$link = $uexc->plugins->utils->get_edit_link($label, $before, $after);
	if( $echo ){
		echo $link;
	}else{
		return $link;
	}
}

function uexc_delete_link($label='Delete', $before='', $after='', $echo=true){
	global $uexc;
	$link = $uexc->plugins->utils->get_delete_link($label, $before, $after);
	if( $echo ){
		echo $link;
	}else{
		return $link;
	}
}





