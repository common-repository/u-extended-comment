<?php

class UexcommAdmin {

function UexcommAdmin(){
	add_action( 'uexc_loaded', array(&$this, 'loaded'));
}

function loaded(){
	add_action( 'admin_init', array(&$this, 'admin_init') );
	add_action( 'admin_menu', array(&$this, 'admin_menu') );
}

function admin_init(){
	do_action( 'uexc_admin_init' );
}

function admin_menu(){
	global $uexc;
	
	if( !is_super_admin() ) 
		return false;
		
	add_menu_page(
		'U Extended Comment', 
		'U Ex-Comment', 
		'manage_options', 
		$uexc->id, 
		array(&$this, 'admin_page')
	);
	
	do_action( 'uexc_admin_menu' );
}

function admin_page(){
	global $uexc;
	$utils = $uexc->plugins->utils;
	$utils_opts = get_option($utils->id);
	
	$editor = $uexc->plugins->editor;
	$editor_opts = get_option($editor->id);
	
	$attachment = $uexc->plugins->attachment;
	$attachment_opts = get_option($attachment->id);
	
	$on = '<span class="uexc-on">On</span>';
	$off = '<span class="uexc-off">Off</span>';
	
	$url = admin_url('admin.php?page=uexc_');
	?>
	<div class="wrap">
		<?php screen_icon("options-general"); ?>
		
		<h2>U Extended Comment</h2>
		<br>
		
		<table class="widefat" style="width:auto;">
			<thead>
				<tr>
					<th><?php _e('Component', $uexc->id)?></th>
					<th><?php _e('Status', $uexc->id)?></th>
					<th><?php _e('Setting', $uexc->id)?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><?php _e('Editable', $uexc->id)?></td>
					<td><?php echo ($utils_opts['editable']) ? $on : $off?></td>
					<td><a href="<?php echo $url.'utils'?>"><?php _e('Setting', $uexc->id)?></a></td>
				</tr>
				<tr>
					<td><?php _e('Deletable', $uexc->id)?></td>
					<td><?php echo ($utils_opts['deletable']) ? $on : $off?></td>
					<td><a href="<?php echo $url.'utils'?>"><?php _e('Setting', $uexc->id)?></a></td>
				</tr>
				<tr>
					<td><?php _e('Privatable', $uexc->id)?></td>
					<td><?php echo ($utils_opts['privatable']) ? $on : $off?></td>
					<td><a href="<?php echo $url.'utils'?>"><?php _e('Setting', $uexc->id)?></a></td>
				</tr>
				<tr>
					<td><?php _e('Editor', $uexc->id)?></td>
					<td><?php echo ($editor_opts['enable']) ? $on : $off?></td>
					<td><a href="<?php echo $url.'editor'?>"><?php _e('Setting', $uexc->id)?></a></td>
				</tr>
				<tr>
					<td><?php _e('Attachment', $uexc->id)?></td>
					<td><?php echo ($attachment_opts['enable']) ? $on : $off?></td>
					<td><a href="<?php echo $url.'attach'?>"><?php _e('Setting', $uexc->id)?></a></td>
				</tr>
			</tbody>
		</table>
		<br>
		
		<p>Hi, I'm Taehan Lee. Thanks for using this plugin.<br>
		If anything does not work, please leave a comment at 
		<a href="http://urlless.com/u-extended-comment/">http://urlless.com/u-extended-comment/</a></p>
		
		<br>
		<p><strong>== Screenshots ==</strong></p>
		<div id="uexc-screenshots" onclick="this.style.width='auto'">
			<img src="<?php echo $uexc->url?>screenshot-1.png" >
			<img src="<?php echo $uexc->url?>screenshot-2.png" >
			<img src="<?php echo $uexc->url?>screenshot-3.png" >
		</div>
		
	</div>
	<style type="text/css">
	#uexc-screenshots { width: 200px;cursor: pointer;}
	#uexc-screenshots img { display: block; max-width: 100%; margin-bottom: 30px; }
	</style>
	<?php
}
	
}

new UexcommAdmin();



function uexc_role_checklist($name, $saved){
	global $wp_roles;
	$roles = $wp_roles->role_names;
	foreach($roles as $key=>$val){
		$checked = (!empty($saved) AND in_array($key, $saved)) ? 'checked="checked"' : '';
		?>
		<label><input type="checkbox" name="<?php echo $name?>[]" value="<?php echo $key?>" <?php echo $checked?>> <?php echo $val?></label> &nbsp; 
	<?php 
	}
}


function uexc_set_cap($_roles, $_cap){
	global $wp_roles;
	if( empty($_roles) || empty($_cap) )
		return false;
		
	$roles = array_keys($wp_roles->role_names);
	
	foreach($roles as $role) 
		$wp_roles->remove_cap($role, $_cap);
	
	foreach($_roles as $role) 
		$wp_roles->add_cap($role, $_cap);
}