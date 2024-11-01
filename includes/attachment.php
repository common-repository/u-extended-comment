<?php

class UexcommAttachment {

var $id = 'uexc_attach';
var $cap_name = 'uexc_attach';
var $thumbnail_size = array(100, 100);

function UexcommAttachment(){
	add_action( 'uexc_activation', array(&$this, 'activation'));
	add_action( 'uexc_uninstall', array(&$this, 'uninstall'));
	add_action( 'uexc_loaded', array(&$this, 'loaded'));
	
	add_action( 'wp_ajax_'.$this->id.'_ajax', array(&$this, 'ajax') );
	add_action( 'wp_ajax_nopriv_'.$this->id.'_ajax', array(&$this, 'ajax') );
}

function loaded(){
	global $uexc;
	$uexc->plugins->attachment = $this;
	$opts = $this->get_option();
	
	if( is_admin() ){
		add_action( 'uexc_admin_init', array(&$this, 'admin_init') );
		add_action( 'uexc_admin_menu', array(&$this, 'admin_menu') );
		add_action( 'admin_action_'.$this->id.'_delete_file', array(&$this, 'delete_file_n_update_meta'));
	
	}else{
		add_action( 'init', array(&$this, 'request') );
	
		if( $this->is_enable() ){
			add_action( 'comment_post', array(&$this, 'update_comment_meta'));
			add_action( 'edit_comment', array(&$this, 'update_comment_meta'));
			add_action( 'template_redirect', array(&$this, 'template_redirect') );
		}
	
		wp_register_style( $this->id.'-style', $uexc->url.'css/attachment.css', '', $uexc->ver);
		wp_register_script( $this->id.'-script', $uexc->url.'js/attachment.js', array('jquery'), $uexc->ver);
		wp_localize_script( $this->id.'-script', $this->id.'_vars', array(
			'ajaxurl' 			=> admin_url( 'admin-ajax.php' ), 
			'nonce' 			=> wp_create_nonce( $this->id.'_nonce' ),
			'plugin_id' 		=> $this->id,
			'max_num' 			=> $opts['max_num'],
			'insert_link' 		=> __('Insert into editor', $uexc->id),
			'delete_link' 		=> __('Delete', $uexc->id),
			'delete_success' 	=> __('Attachment deleted', $uexc->id),
			'delete_confirm'	=> __('Are you sure you want to delete?', $uexc->id),
			'processing' 		=> __('Processing', $uexc->id),
		));
	}
}

function is_enable(){
	
	$opts = $this->get_option();
	if( empty($opts['enable']) ) 
		return false;
	
	return true;
}

function current_user_can_upload(){
	$opts = $this->get_option();
	if( !empty($opts['roles']) ){
		if( !current_user_can($this->cap_name) )
			return false;
	}
	return true;
}

function request(){
	if( !$this->is_enable() )
		return false;
		
	if( $this->current_user_can_upload() ){
		if( isset($_POST[$this->id.'_upload']) AND ($_POST[$this->id.'_upload']==='true') ){
			$this->_do_upload();
			exit;
		}
	}
	
	// do not check capability
	if( isset($_GET[$this->id.'_download']) AND ($_GET[$this->id.'_download']==='true') ){
		$this->_do_download();
		exit;
	}
}

function template_redirect(){
	global $uexc;
	
	if( !is_singular() || !comments_open() ) 
		return false;
	
	$opts = $this->get_option();
	
	wp_enqueue_style( $this->id.'-style');
	wp_enqueue_script( $this->id.'-script');
	
	if( $this->current_user_can_upload() ){
		add_action( 'wp_footer', array(&$this, 'create_upload_form'));
		add_action( 'comment_form', array(&$this, 'create_uploader'), 90 );
		
		// for edit
		add_action( 'uexc_utils_footer_edit', array(&$this, 'create_upload_form'));
		add_action( 'uexc_utils_head_edit', array(&$this, 'uexc_edit_script_action'));
		add_action( 'uexc_edit_after_content_field', array(&$this, 'uexc_edit_create_uploader') );
		add_filter( 'uexc_edit_textarea_attr', array(&$this, 'uexc_edit_textarea_attr'));
	}
	
	// do not check capability
	add_filter( 'comment_text',array(&$this, 'create_filebox'), 90);
}

function uexc_edit_create_uploader($comment){
	$meta_id = $this->get_meta_id($comment->comment_ID);
	$this->create_uploader('', $meta_id);
}
function uexc_edit_script_action(){
	wp_print_styles( $this->id.'-style');
	wp_print_scripts( $this->id.'-script');
}
function uexc_edit_textarea_attr($attr){
	$attr['class'] .= ' '.$this->id.'_comment_field ';
	return $attr;
}


function create_upload_form(){
	?>
<form action="" method="post" enctype="multipart/form-data" id="<?php echo $this->id?>-form" target="<?php echo $this->id?>_target">
	<input type="hidden" name="<?php echo $this->id?>_upload" value="true">
	<?php wp_nonce_field($this->id.'_nonce', $this->id.'_nonce')?>
</form>
<iframe id="<?php echo $this->id?>-target" name="<?php echo $this->id?>_target" frameborder="0" src="about:blank"></iframe>
	<?php
}


function create_uploader( $post_id, $meta_id='' ){
	global $uexc, $is_iphone;
	
	if( $is_iphone ) return false;
	
	$opts = (object) $this->get_option();
	?>
<div id="<?php echo $this->id?>-uploader">
	<span id="<?php echo $this->id?>-button"><span><?php _e('Attach a file', $uexc->id)?></span></span>
	<span id="<?php echo $this->id?>-progress"><?php echo _e('Uploading', $uexc->id)?></span>
	<span id="<?php echo $this->id?>-info">
		<?php _e('File types', $uexc->id)?>: <strong><?php echo $opts->allowed_file_type?></strong>,
		<?php _e('Max size', $uexc->id)?>: <strong><?php echo $opts->max_size;?>Mbytes</strong>,
		<?php _e('Max count', $uexc->id)?>: <strong><?php echo $opts->max_num;?></strong>
	</span>
	<div id="<?php echo $this->id?>-message"></div>
	<table id="<?php echo $this->id?>-list" class="<?php echo $this->id?>-filelist"></table>
</div>

<script>jQuery(function(){ window.<?php echo $this->id?> = new UexcommAttachment('<?php echo $meta_id?>'); });</script>
	<?php
}


function create_filebox($comment_text){
	global $comment, $uexc;
	
	if( !isset($comment->comment_ID) ) 
		return $comment_text;
	
	$rows = get_comment_meta($comment->comment_ID, $this->id.'-attachments', true);
	$upload_dir_path = $this->get_upload_dir_path();
	$upload_dir_url = $this->get_upload_dir_url();
	
	$ret = '';
	if( !empty($rows) AND $rows = json_decode($rows) ){
		$i=0;
		$ret = '<table class="'.$this->id.'-attachments '.$this->id.'-filelist">';
		foreach($rows as $row){
			$x = explode('.', $row->filename);
			$ext = end($x);
			$is_image = ( $ext=='jpg' || $ext=='jpeg' || $ext=='gif' || $ext=='png' ) ? true : false;
			
			$download_url = add_query_arg(array(
				$this->id.'_download' => 'true',
				'_wpnonce' => wp_create_nonce($this->id.'_nonce'),
				'filename' => urlencode($row->filename),
			), '');
			
			$thumbnail = $row->url;
			if( !empty($row->thumbnail_filename) AND file_exists($upload_dir_path.$row->thumbnail_filename) ){
				$thumbnail = $upload_dir_url.$row->thumbnail_filename;
			}
			
			$even = ($i++%2==0) ? 'even' : '';
			$ret .= '<tr class="'.$even.'">';
			if( $is_image ) {
				$ret .= '<td class="thumb"><img src="'.$thumbnail.'" class="thumb">'.$t.'</td>';
			}else{
				$ret .= '<td class="thumb empty"></td>';
			}
			$ret .= '<td class="filename">'.$row->filename.'</td>';
			$ret .= '<td class="links"><a href="'.$download_url.'">'.__('Download', $uexc->id).'</a>';
			if( $is_image ) {
				$ret .= ' <span class="pipe"> | </span> ';
				$ret .= '<a href="'.$row->url.'" target="_blank" title="'.__('Open Image in New Window', $uexc->id).'">'.__('View', $uexc->id).'</a>';
			}
			$ret .= '</td></tr>';
		}
		$ret .= '</table>';
	}
	
	if( isset($comment->uexc_private) ){
		if( $comment->uexc_private AND !$comment->uexc_private_readable )
			$ret = '';
	}
	
	return $comment_text.$ret;
}

function update_comment_meta($comment_id){
	delete_comment_meta( $comment_id, $this->id.'-attachments' );
	
	$attachments = isset($_POST[$this->id.'-attachments']) ? $_POST[$this->id.'-attachments'] : '';
	if( !empty($attachments) AND is_array($attachments) ){
		$clean = array();
		foreach($attachments as $k=>$v)
			$clean[$k] = $v;
		
		$attachments = json_encode($clean);
		add_comment_meta( $comment_id, $this->id.'-attachments', $attachments, true );
	}
}

function get_all_mime_types(){
	include 'mimes.php';
	return $mimes;
}

function get_upload_dir_path(){
	$opts = get_option($this->id);
	$wp_upload_dir = wp_upload_dir();
	return $wp_upload_dir['basedir'].'/'.$opts['upload_dir'].'/';
}

function get_upload_dir_url(){
	$opts = get_option($this->id);
	$wp_upload_dir = wp_upload_dir();
	return $wp_upload_dir['baseurl'].'/'.$opts['upload_dir'].'/';
}





















function _upload_dir($a){
	$opts = $this->get_option();
	$subdir = '/'.$opts['upload_dir'];
	$a['path'] = str_replace($a['subdir'], $subdir, $a['path']);
	$a['url'] = str_replace($a['subdir'], $subdir, $a['url']);
	$a['subdir'] = $subdir;
	return $a;
}

function _sanitize_file_name($filename){
	$info = pathinfo($filename);
	$ext = $info['extension'];
	$filename = str_replace('.'.$ext, '', $filename);
	$filename = strtolower($filename);
	$filename = preg_replace('|[^a-z0-9_-]|', '', $filename);
	if( preg_replace('|[^a-z0-9]|', '', $filename)=='' )
		$filename = time();
	$filename = $filename.'.'.$ext;
	return $filename;
}

function _upload_mimes($_mimes=''){
	$mimes = $this->get_all_mime_types();
	$opts = $this->get_option();
	$exts = explode(',', preg_replace('/,\s*/', ',', $opts['allowed_file_type']));
	$allowed_mimes = array();
	foreach ( $exts as $ext ) {
		foreach ( $mimes as $ext_pattern => $mime ) {
			if ( $ext != '' && strpos( $ext_pattern, $ext ) !== false )
				$allowed_mimes[$ext_pattern] = $mime;
		}
	}
	return $allowed_mimes;
}

function _check_filetype( $filename ) {
	$mimes = $this->_upload_mimes();
	$type = false;
	$ext = false;
	foreach ( $mimes as $ext_preg => $mime_match ) {
		$ext_preg = '!\.(' . $ext_preg . ')$!i';
		if ( preg_match( $ext_preg, $filename, $ext_matches ) ) {
			$type = $mime_match;
			$ext = $ext_matches[1];
			break;
		}
	}
	return compact( 'ext', 'type' );
}

function _return_upload_error($error){
	$error = esc_js($error);
	echo "<script>parent.{$this->id}.upload_error('{$error}');</script>";
	exit;
}

function _do_upload(){
	global $uexc;
	
	$opts = $this->get_option();
	
	if ( !wp_verify_nonce($_POST[$this->id.'_nonce'], $this->id.'_nonce') )
		$this->_return_upload_error( __('Your nonce did not verify.', $uexc->id) );
	
	if( empty($_FILES['file']['size']) )
		$this->_return_upload_error( __('Please select a file to upload', $uexc->id) );
		
	if( $_FILES['file']['size'] > ($opts['max_size'] * 1024 * 1024) )
		$this->_return_upload_error( sprintf(__('For uploading, file size must be less than %s Mbytes', $uexc->id), $opts['max_size']) );
	
	add_filter( 'upload_dir', array(&$this, '_upload_dir') ); 
	add_filter( 'sanitize_file_name', array(&$this, '_sanitize_file_name') ); 
	add_filter( 'upload_mimes', array(&$this, '_upload_mimes') );
	
	$upload = wp_upload_bits($_FILES['file']['name'], null, file_get_contents($_FILES['file']['tmp_name']));
	if( !empty($upload['error']) )
		$this->_return_upload_error($upload['error']);

	$url = esc_js($upload['url']);
	$filename = basename($url);
	$message = esc_js(sprintf(__('[%s] is successfully uploaded.', $uexc->id), $filename));
	
	$thumbnail = $this->_create_thumbnail($upload);
	if( !empty($thumbnail['error']) || empty($thumbnail['url'])){
		$thumbnail_url = '';
		$thumbnail_filename = '';
	}else{
		$thumbnail_url = esc_js($thumbnail['url']);
		$thumbnail_filename = basename($thumbnail_url);
	}
	?>
	<script>
	var args = {
		url: '<?php echo $url?>',
		filename: '<?php echo $filename?>',
		thumbnail_url: '<?php echo $thumbnail_url?>',
		thumbnail_filename: '<?php echo $thumbnail_filename?>',
		message: '<?php echo $message?>'
	}
	parent.<?php echo $this->id?>.upload_complete(args);
	</script>
	<?php
	exit;
}

function _create_thumbnail($upload){
	@ini_set('memory_limit', '256M');
	$return = array();
	$filepath = $upload['file'];
	$path_parts = pathinfo( $filepath );
	$baseurl = str_replace($path_parts['basename'], '', $upload['url']);
	$imagesize = getimagesize($filepath);
	$mime_type = $imagesize['mime'];
	
	switch ( $mime_type ) {
		case 'image/jpeg':
			$img = imagecreatefromjpeg($filepath);
			break;
		case 'image/png':
			$img = imagecreatefrompng($filepath);
			break;
		case 'image/gif':
			$img = imagecreatefromgif($filepath);
			break;
		default:
			$img = false;
			break;
	}
	
	if ( is_resource($img) && function_exists('imagealphablending') && function_exists('imagesavealpha') ) {
		imagealphablending($img, false);
		imagesavealpha($img, true);
	} else {
		$return['error'] = __('Unable to create sub-size images.');
		return $return;
	}
	
	$resized = image_make_intermediate_size($filepath, $this->thumbnail_size[0], $this->thumbnail_size[1], true);
	if( empty($resized) ){
		$return['url'] = '';
	}else{
		$return['url'] = $baseurl.$resized['file'];
	}
	
	imagedestroy($img);
	return $return;
}


function _do_download(){
	global $uexc;
	
	if ( !wp_verify_nonce($_GET['_wpnonce'], $this->id.'_nonce') )
		wp_die(__('Your nonce did not verify.', $uexc->id)); 
	
	$filename = basename($_GET['filename']);
	$filepath = $this->get_upload_dir_path().$filename;
	
	if( empty($filename) || !file_exists($filepath) ) {
		wp_die(__('File does not exist', $uexc->id));
	}else{
		$this->_force_download($filename, file_get_contents($filepath));
	}
}

function _force_download($filename = '', $data = ''){
	global $uexc;
	
	if ($filename == '' OR $data == '')	
		return false;
	
	if (FALSE === strpos($filename, '.')) 
		return false;
	
	$rs = $this->_check_filetype($filename);
	$mime_type = $rs['type'];
	
	if( empty($mime_type) ){
		wp_die(__('Invalid file type'));
	
	}else{
		if (strpos($_SERVER['HTTP_USER_AGENT'], "MSIE") !== FALSE){
			header('Content-Type: "'.$mime_type.'"');
			header('Content-Disposition: attachment; filename="'.$filename.'"');
			header('Expires: 0');
			header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
			header("Content-Transfer-Encoding: binary");
			header('Pragma: public');
			header("Content-Length: ".strlen($data));
		}else{
			header('Content-Type: "'.$mime_type.'"');
			header('Content-Disposition: attachment; filename="'.$filename.'"');
			header("Content-Transfer-Encoding: binary");
			header('Expires: 0');
			header('Pragma: no-cache');
			header("Content-Length: ".strlen($data));
		}
		exit($data);
	}
}













function ajax(){
	if( !defined('DOING_AJAX') ) die('-1');
	check_ajax_referer( $this->id.'_nonce' );
	
	switch( $_REQUEST['action_scope'] ){
		case 'get_meta':
			$r =$this->get_meta_value( $_REQUEST['meta_id'] );
			if( is_array($r) )
				echo json_encode($r);
			break;
		
		case 'update_meta':
			$this->update_meta_value($_REQUEST['meta_id'], $_POST['files']);
			break;
			
		case 'delete_file_n_update_meta':
			$this->delete_file_n_update_meta();
			break;
		
		case 'delete_unattached_files':
			if( isset($_POST['filename']))
				$this->unlink_file($_POST['filename']);
			if( isset($_POST['thumbnail_filename']))
				$this->unlink_file($_POST['thumbnail_filename']);
			break;
		
		case 'get_unattached_files':
			$r = $this->get_unattached_files();
			echo json_encode($r);
			break;
	}
	die();
}

function get_meta_value($meta_id){
	global $wpdb;
	if( empty($meta_id) ) return false;
	$sql = $wpdb->prepare("SELECT meta_value FROM {$wpdb->commentmeta} WHERE meta_id=%d LIMIT 1", $meta_id);
	if( $r = $wpdb->get_var($sql) ){
		return (array) json_decode($r);
	}else{
		return false;
	}
}

function get_meta_id($comment_id){
	global $wpdb;
	if( empty($comment_id) ) return '';
	$sql = $wpdb->prepare("SELECT meta_id FROM {$wpdb->commentmeta} WHERE meta_key=%s AND comment_id=%d", $this->id.'-attachments', $comment_id);
	$meta_id = $wpdb->get_var($sql);
	return $meta_id;
}

function get_meta_total(){
	global $wpdb;
	return $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->commentmeta} WHERE meta_key=%s", $this->id.'-attachments'));
}

function update_meta_value($meta_id, $files){
	global $wpdb;
	
	if( empty($meta_id) )
		return false;
	
	$files = !empty($files) ? (array) $files : array();
	
	$clean = array();
	foreach($files as $k=>$v) 
		$clean[$k] = $v;
	$clean = json_encode( $clean );
	$wpdb->update($wpdb->commentmeta, array('meta_value'=>$clean), array('meta_id'=>$meta_id));
}

function delete_file_n_update_meta(){
	if( defined('DOING_AJAX') ){
		check_ajax_referer( $this->id.'_nonce' );
	}else{
		check_admin_referer($this->id.'_nonce'); 
	}
	
	$meta_ids = $_REQUEST['meta_id'];
	if( is_string($meta_ids) )
		$meta_ids = array($meta_ids);
	
	foreach( $meta_ids as $meta_id ){
		$tmp = explode('|', $meta_id);
		if( count($tmp)!= 2 )
			continue;
		
		$meta_id = absint($tmp[0]);
		$file_index = stripcslashes($tmp[1]);
		
		$files = $this->get_meta_value( $meta_id );
		$file = isset($files[$file_index]) ? $files[$file_index] : '';
		unset($files[$file_index]);
		
		if( $file ){
			if( isset($file->url) )
				$this->unlink_file($file->url);
			
			if( isset($file->thumbnail_url) )
				$this->unlink_file($file->thumbnail_url);
		}
		
		$this->update_meta_value($meta_id, $files);
	}
	
	if( !defined('DOING_AJAX') ){
		$goback = wp_get_referer();
		wp_redirect( $goback );
	}
	exit;
}

function unlink_file($filename){
	if( empty($filename) )
		return false;
	$upload_dir_path = $this->get_upload_dir_path();
	$filename = basename($filename);
	$filepath = $upload_dir_path.$filename;
	if( !empty($filename) AND file_exists($filepath) )
		@unlink($filepath);
}

function get_unattached_files(){
	global $wpdb;
	
	$unattached = array();
	$attached = array();
	
	$upload_dir_path = $this->get_upload_dir_path();
	$upload_dir_url = $this->get_upload_dir_url();
	
	$handler = opendir($upload_dir_path);
	while($file = readdir($handler)){
		if($file != '.' AND $file != '..'){
			$unattached[] = $file;
		}
	}
	closedir($handler);
	
	if( empty($unattached) )
		return null;
	
	$metas = $wpdb->get_results($wpdb->prepare("SELECT meta_value FROM {$wpdb->commentmeta} WHERE meta_key=%s", $this->id.'-attachments'));
	foreach($metas as $meta){
		if( empty($meta->meta_value) ) continue;
		$files = json_decode($meta->meta_value);
		foreach( $files as $file ){
			if( !empty($file->filename) )
				$attached[] = $file->filename;
			if( !empty($file->thumbnail_filename) )
				$attached[] = $file->thumbnail_filename;
		}
	}
	
	$rs = array();
	foreach( $unattached as $filename ){
		if( !in_array($filename, $attached) )
			$rs[] = $filename;
	}
	
	return $rs;
}












/* back-end
--------------------------------------------------------------------------------------- */

function get_option(){
	$options = array (
		'enable' => '',
		'max_size' => 1,
		'max_num' => 3,
		'allowed_file_type' => 'jpg, png, gif, zip',
		'upload_dir' => $this->id,
		'roles' => array(),
		'fm_number' => 20,
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

function admin_menu(){
	global $uexc;
	
	add_submenu_page( 
		$uexc->id, 
		__('Attachment', $uexc->id), 
		__('Attachment', $uexc->id), 
		'manage_options', 
		$this->id, 
		array(&$this, 'admin_options_page') 
	);
	
	add_submenu_page( 
		$uexc->id, 
		__('Attachment Manager', $uexc->id), 
		__('Attachment Manager', $uexc->id), 
		'manage_options', 
		$this->id.'_files', 
		array(&$this, 'admin_file_manager') 
	);
}


function admin_init(){
	register_setting($this->id.'_options', $this->id, array( &$this, 'admin_options_vailidate'));
}

function admin_options_page(){
	global $uexc;
	
	$opts = (object) $this->get_option();
	
	if( is_multisite() AND defined('BP_ROOT_BLOG') AND BP_ROOT_BLOG!=1){
		switch_to_blog(BP_ROOT_BLOG);
		$wp_upload_dir = wp_upload_dir();
		restore_current_blog();
	}else{
		$wp_upload_dir = wp_upload_dir();
	}
	
	$all_mimes = array_keys($this->get_all_mime_types());
	foreach($all_mimes as $i=>$mime) 
		$all_mimes[$i] = '['.str_replace('|', ',', $mime).']';
	$all_mimes = implode(', ', $all_mimes);
	?>
	
	<div class="wrap">
		<?php screen_icon("options-general"); ?>
		
		<h2>U Ex-Comment &raquo; <?php _e('Attachment', $uexc->id);?></h2>
		
		<?php settings_errors( $this->id ) ?>
		
		<form action="<?php echo admin_url('options.php')?>" method="post">
			<?php settings_fields($this->id.'_options'); ?>
			<table class="form-table">
			<tr>
				<th><strong><?php _e('Enable', $uexc->id)?></strong></th>
				<td>
					<label><input type="checkbox" name="<?php echo $this->id?>[enable]" id="enable_cb" value="1" <?php checked($opts->enable, '1')?>> 
					<strong><?php _e('Enable', $uexc->id)?></strong></label>
					<p>&nbsp;</p>
				</td>
			</tr>
			<tr>
				<th><?php _e('Permission restriction', $uexc->id)?></th>
				<td>
					<?php uexc_role_checklist($this->id.'[roles]', $opts->roles)?>
					<p class="description"><?php _e('If you want to allow all users and visitors to use, please uncheck all.', $uexc->id);?></p>
				</td>
			</tr>
			<tr>
				<th><?php _e('Upload directory', $uexc->id)?></th>
				<td>
					<?php echo $wp_upload_dir['basedir']?>/
					<input type="text" name="<?php echo $this->id?>[upload_dir]" value="<?php echo $opts->upload_dir;?>">
				</td>
			</tr>
			<tr>
				<th><?php _e('Max file size per file', $uexc->id)?></th>
				<td>
					<input type="text" name="<?php echo $this->id?>[max_size]" value="<?php echo $opts->max_size;?>" size="1"> Mbytes
				</td>
			</tr>
			<tr>
				<th><?php _e('Max file count per post', $uexc->id)?></th>
				<td>
					<input type="text" name="<?php echo $this->id?>[max_num]" value="<?php echo $opts->max_num;?>" size="1">
					<span class="description"><?php _e('How many files can be attached per post.', $uexc->id)?></span>
				</td>
			</tr>
			<tr>
				<th><?php _e('Upload file types', $uexc->id)?></th>
				<td>
					<input type="text" name="<?php echo $this->id?>[allowed_file_type]" value="<?php echo $opts->allowed_file_type;?>" class="regular-text">
					<p class="description"><?php _e('Separate file extentions with commas.', $uexc->id)?></p>
					<br>
					<p><strong>Available file types</strong></p>
					<p class="description"><?php _e("Extention(s) in the square brackets is same type each other. so, for example, if you inputted 'jpg', you don't need to input 'jpeg' or 'jpe'.", $uexc->id)?></p>
					<p><code style="font-size:10px;"><?php echo $all_mimes?></code></p>
				</td>
			</tr>
			<tr>
				<th><?php _e('Number of comments to show in Attachment Manager', $uexc->id)?></th>
				<td>
					<input type="text" name="<?php echo $this->id?>[fm_number]" value="<?php echo $opts->fm_number;?>" size="1">
				</td>
			</tr>
			</table>
			
			<p class="submit">
				<input name="submit" type="submit" class="button-primary" value="<?php esc_attr_e(__('Save Changes')); ?>" />
			</p>
		</form>
		
	</div>
	<?php
}


function admin_options_vailidate($input){
	$saved = get_option($this->id);
	$r = array();
	$r['enable'] 		= !empty($input['enable']) ? '1' : '';
	$r['upload_dir'] 	= preg_replace( '/[^a-z0-9_\-\.\/]/', '', untrailingslashit($input['upload_dir']) );
	$r['upload_dir'] 	= !empty($r['upload_dir']) ? $r['upload_dir'] : $saved['upload_dir'];
	$r['max_size'] 		= floatval($input['max_size']) ? floatval($input['max_size']) : $saved['max_size'];
	$r['max_num'] 		= absint($input['max_num']) ? absint($input['max_num']) : $saved['max_num'];
	$r['allowed_file_type'] = trim($input['allowed_file_type']) ? trim($input['allowed_file_type']) : $saved['allowed_file_type'];
	$r['roles'] 		= !empty($input['roles']) ? $input['roles'] : '';
	$r['fm_number'] 	= absint($input['fm_number']) ? absint($input['fm_number']) : $saved['fm_number'];
	
	uexc_set_cap($r['roles'], $this->cap_name);
	add_settings_error($this->id, 'settings_updated', __('Settings saved.'), 'updated');
	return $r;
}


function admin_file_manager(){
	global $uexc, $wpdb;
	$opts = $this->get_option();
	
	$paged = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
	$items_per_page = absint($opts['fm_number']) ? absint($opts['fm_number']) : 20;
	$total_item = $this->get_meta_total();
	$total_page = ceil($total_item/$items_per_page);
	$offset =  ($paged * $items_per_page) - $items_per_page;
	
	$pager_args = array(
		'base' => @add_query_arg('paged','%#%'),
		'format' => '',
		'total' => $total_page,
		'current' => $paged,
	);
	$page_links = paginate_links( $pager_args );
	
	$upload_dir_path = $this->get_upload_dir_path();
	$upload_dir_url = $this->get_upload_dir_url();
	$base_url = add_query_arg(array('page'=>$_GET['page'], 'paged'=>$paged), network_admin_url('admin.php'));
	
	$sql = $wpdb->prepare("SELECT * FROM {$wpdb->commentmeta} WHERE meta_key=%s ORDER BY meta_id DESC LIMIT %d, %d", $this->id.'-attachments', $offset, $items_per_page);
	$meta_rows = $wpdb->get_results($sql);
	$meta_index = 0;
	$rows = array();
	
	foreach( $meta_rows as $meta_row){
		if( empty($meta_row->meta_value) ) 
			continue;
		
		$files = (array) json_decode($meta_row->meta_value);
		if( !count($files) ) 
			continue;
		
		$comment_id = $meta_row->comment_id;
		$comment = get_comment($comment_id);
		if( !$comment )	
			continue;
			
		$post_title = $wpdb->get_var($wpdb->prepare("SELECT post_title FROM {$wpdb->posts} WHERE ID=%d", $comment->comment_post_ID));
		$post_title = '<a href="'.get_comment_link($comment_id).'" target="_blank">@'.$post_title.'</a>';
		$status = $comment->comment_approved=='trash' ? __('(Trash)', $this->id) : '';
		$author = get_comment_author($comment);
		$date = date('Y/m/d', strtotime($comment->comment_date));
		$excerpt = '#'.implode(' ', array_slice(explode(' ', strip_tags($comment->comment_content)), 0, 10)).'...';
		
		$row = array();
		foreach( $files as $file_index=>$file ){
			$file_index = (string) $file_index;
			
			// for under version 1.x
			if( $file_index=='0' || absint($file_index)>0 ){
				$file_index = $file->filename;
				$_files = array();
				foreach($files as $_file)
					$_files[$_file->filename] = $_file;
				$this->update_meta_value($meta_row->meta_id, $_files);
			}
			
			$x = explode('.', $file->filename);
			$ext = end($x);
			$is_image = ( $ext=='jpg' || $ext=='jpeg' || $ext=='gif' || $ext=='png' ) ? true : false;
			$thumbnail = '';
			if( $is_image ){
				$thumbnail = $file->url;
				if( !empty($file->thumbnail_filename) AND file_exists($upload_dir_path.$file->thumbnail_filename) )
					$thumbnail = $upload_dir_url.$file->thumbnail_filename;
			}else{
				$ext = preg_replace('/^.+?\.([^.]+)$/', '$1', $file->filename);
				if ( !empty($ext) AND $mime=wp_ext2type($ext) ) 
					$thumbnail = wp_mime_type_icon($mime);
			}
			$thumbnail = "<img src='$thumbnail' width='40'>";
					
			$delete_url = add_query_arg(array(
				'action' => $this->id.'_delete_file',
				'meta_id' => $meta_row->meta_id.'|'.$file_index,
				'_wpnonce' => wp_create_nonce($this->id.'_nonce'), 
				'_wp_http_referer' => urlencode($base_url),
			), '');
			
			$row[] = (object) array(
				'meta_id' => $meta_row->meta_id,
				'file_index' => $file_index,
				'file' => $file,
				'thumbnail' => $thumbnail,
				'post_title' => $post_title,
				'delete_url' => $delete_url,
				'status' => $status,
				'author' => $author,
				'date' => $date,
				'excerpt' => $excerpt,
			);
		}
		$rows[] = $row;
	}
	?>
	
	<div class="wrap">
		<?php screen_icon("options-general"); ?>
		
		<h2>U Ex-Comment &raquo; <?php _e('Attachment Manager', $uexc->id)?></h2>
		<br>
		
		<form action="" method="get" id="files-form">
		<?php wp_nonce_field($this->id.'_nonce')?>
	
		<div class="tablenav top">
			<div class="alignleft">
				<select class="action" name="action">
					<option value="-1" selected="selected"><?php _e('Bulk Actions')?></option>
					<option value="<?php echo $this->id?>_delete_file"><?php _e('Delete Permanently')?></option>
					<input type="submit" value="<?php _e('Apply')?>" class="button">
				</select>
			</div>
			<div class="tablenav-pages">
				<div class="pagination-link"><?php echo $page_links?></div>
			</div>
		</div>
		
		<table class="widefat fixed" id="files-table">
			<thead>
				<tr>
					<th class="check-column"><input type="checkbox"></th>
					<th class="thumb-column"></th>
					<th class="file-column"><?php _e('File', $uexc->id)?></th>
					<th class="attached-to-column"><?php _e('Attached to', $uexc->id)?></th>
					<th class="author-column"><?php _e('Commenter', $uexc->id)?></th>
					<th class="date-column"><?php _e('Date', $uexc->id)?></th>
				</tr>
			</thead>
			<tbody>
			<?php 
			$i = 0;
			foreach( $rows as $row){
				$alternate = (($i++)%2==0) ? 'alternate' : '';
				$row_count = count($row);
				$j = 0;
				foreach( $row as $r ){ ?>
				<tr class="<?php echo $alternate?>">
					<td class="check-column">
						<input type="checkbox" name="meta_id[]" value="<?php echo $r->meta_id?>|<?php echo $r->file_index?>">
					</td>
					<td class="thumb-column">
						<?php echo $r->thumbnail?>
					</td>
					<td class="file-column">
						<strong><?php echo $r->file->filename?></strong>
						<div class="row-actions">
							<span class="delete"><a href="<?php echo $r->delete_url?>" class="submitdelete"><?php _e('Delete Permanently')?></a></span> |
							<span class="view"><a href="<?php echo $r->file->url?>" target="_blank"><?php _e('View')?></a>
						</div>
					</td>
					<?php if( ($j++)==0 ): ?>
					<td class="attached-to-column rowspan" rowspan="<?php echo $row_count?>"><?php echo $r->status?> <?php echo $r->post_title?><br><em><?php echo $r->excerpt?></em></td>
					<td class="author-column rowspan" rowspan="<?php echo $row_count?>"><?php echo $r->author?></td>
					<td class="date-column rowspan" rowspan="<?php echo $row_count?>"><?php echo $r->date?></td>
					<?php endif; ?>
				</tr>
			<?php }} ?>
			</tbody>
		</table>
		
		<div class="tablenav bottom"></div>
		
		</form>
		
		
		<p>&nbsp;</p>
		<a href="#" id="show-unattached-files"><?php _e('Show Unattached Files', $uexc->id)?></a>
		<div id="unattached-files">
			<h3><?php _e('Unattached Files', $uexc->id)?></h3>
			<table class="widefat">
				<thead>
					<tr>
						<th><?php _e('Filename', $uexc->id)?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td class="2"><img class="status" src="<?php echo $uexc->url?>images/ajax-loader.gif"> <?php _e('Loading', $uexc->id)?></td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
	
	<style>
	#files-table td { vertical-align: middle; }
	#files-table .check-column input { margin-left: 8px;}
	#files-table .thumb-column {width: 65px;}
	#files-table .author-column {width: 120px;}
	#files-table .date-column {width: 100px;}
	#files-table td.rowspan {border-left: 1px dashed #ddd;}
	#files-form .tablenav.bottom .tablenav-pages { float: none;}
	#unattached-files {display: none;}
	#unattached-files table{width: auto;}
	#unattached-files h3 { margin-top: 0; }
	#unattached-files img.status { vertical-align: middle;}
	#unattached-files td.actions { text-align: right; }
	</style>
	
	<script>
	(function($) { $(function(){
	
	$('<tfoot/>').html($('#files-table thead').html()).insertAfter($('#files-table thead'));
	$('#files-table th input[type=checkbox]').change(function(){ 
		$('#files-table tb input[type=checkbox]').attr('checked', this.checked);
	});
	$('#files-form .tablenav-pages').clone().appendTo('#files-form .tablenav.bottom');
	$('#files-form').submit(function(){ 
		if( $(this).find('select.action').val()=='-1' ) 
			return false; 
	});
	$('a#show-unattached-files').click( function(){
		$(this).hide();
		$('#unattached-files').show();
		var args = {
			action: '<?php echo $this->id?>_ajax',
			action_scope: 'get_unattached_files',
			_ajax_nonce: '<?php echo wp_create_nonce( $this->id.'_nonce' )?>'
		}
		$.post('<?php echo admin_url('admin-ajax.php')?>', args, function(res){
			var t = $('#unattached-files');
			t.find('img.status').hide();
			var tbody = t.find('table tbody');
			
			if( !res || res.length==0 ){
				tbody.find('td').html('<?php _e('No unattached files', $uexc->id)?>');
				return;
			}
			tbody.find('tr').remove();
			for( var i in res ){
				var filename = res[i];
				var file_type = filename.substr(filename.lastIndexOf('.')+1);
				var is_image = (file_type=='jpg'||file_type=='jpeg'||file_type=='gif'||file_type=='png') ? true : false;
				var view_link = is_image ? '<a href="<?php echo $upload_dir_url?>'+filename+'" target="_blank"><?php _e('View')?></a> | ' : '&nbsp;';
			
				var delete_link = $('<a href="#"><?php _e('Delete')?></a>');
				$.data(delete_link[0], 'filename', filename);
				
				delete_link.click( function(){
					var t = $(this).hide();
					var args = {
						action: '<?php echo $this->id?>_ajax',
						action_scope: 'delete_unattached_files',
						_ajax_nonce: '<?php echo wp_create_nonce( $this->id.'_nonce' )?>',
						filename: $.data(this, 'filename')
					}
					$.post('<?php echo admin_url('admin-ajax.php')?>', args, function(r){
						t.parents('tr:eq(0)').fadeOut(200);
					});
					return false;
				} );
				var tr = $('<tr><td class="filename">'+filename+'</td><td class="actions"></td></tr>');
				tr.find('td.actions').append( view_link, delete_link );
				tr.appendTo( tbody );
			}
		}, 'json');
		return false;
	});
	
	}); })(jQuery);
	</script>
	<?php
}



}

new UexcommAttachment();
