<?php

class UexcommEditor {

var $id = 'uexc_editor';
var $cap_name = 'uexc_editor';

function UexcommEditor(){
	add_action( 'uexc_activation', array(&$this, 'activation'));
	add_action( 'uexc_uninstall', array(&$this, 'uninstall'));
	add_action( 'uexc_loaded', array(&$this, 'loaded'));
}

function loaded(){
	global $uexc;
	$uexc->plugins->editor = $this;
	
	if( is_admin() ){
		add_action( 'uexc_admin_init', array(&$this, 'admin_init') );
		add_action( 'uexc_admin_menu', array(&$this, 'admin_menu') );
	}else{
		wp_register_style($this->id.'-editor', $uexc->url.'css/editor.css', '', $uexc->ver);
		wp_register_script($this->id.'-editor', $uexc->url.'js/editor.js', '', $uexc->ver);
		
		if( $this->is_enable() ){
			add_action( 'template_redirect', array(&$this, 'template_redirect') );
			add_filter( 'pre_comment_content', array(&$this, 'allowedtag_filter'), 1);
			
			// for editable component
			add_filter( 'uexc_edit_textarea_attr', array(&$this, 'uexc_edit_textarea_attr'));
			add_filter( 'uexc_edit_content_pre', array(&$this, 'uexc_edit_richedit_pre'));
			add_action( 'uexc_utils_footer_edit', array(&$this, 'uexc_edit_footer_edit'));
		}
	}
}

function template_redirect(){
	global $uexc;
	if( is_singular() AND comments_open() ){
		wp_enqueue_script('jquery');
		
		wp_deregister_script('comment-reply');
		wp_register_script('comment-reply', $uexc->url.'js/comment-reply.js', '', $uexc->ver);
		
		wp_enqueue_style($this->id.'-editor');
		wp_enqueue_script($this->id.'-editor');
		
		add_action( 'wp_footer', array(&$this, 'the_editor'), 100);
	}
}


/* for editable component
--------------------------------------------------------------------------------------- */
function uexc_edit_footer_edit(){
	add_filter($this->id.'_before_init', array(&$this, 'uexc_edit_before_mce_init'));
	wp_print_styles($this->id.'-editor');
	wp_print_scripts($this->id.'-editor');
	$this->the_editor();
}
function uexc_edit_before_mce_init($a){
	$a['width'] = '100%';
	return $a;
}
function uexc_edit_textarea_attr($attr){
	$attr['class'] .= ' theEditor ';
	return $attr;
}
function uexc_edit_richedit_pre($text){
	$text = convert_chars($text);
	$text = wpautop($text);
	return $text;
}









function is_enable(){
	global $is_iphone;
	
	if( $is_iphone )
		return false;
		
	$opts = $this->get_option();
	
	if( empty($opts['enable']) ) 
		return false;
	
	if( !empty($opts['roles']) )
		if( !current_user_can($this->cap_name) )
			return false;
	
	return true;
}

function allowedtag_filter($comment_content){
	global $allowedtags;
	$allowedtags = $this->allowed_tags();
	return $comment_content;
}

function allowed_tags(){
	global $default_allowedtags, $full_allowedtags;
	
	$r = array();
	$opts = $this->get_option();
	
	if( empty($opts['allowed_tags']) ){
		foreach($default_allowedtags as $tag)
			$r[$tag] = $full_allowedtags[$tag];
	}else{
		foreach($opts['allowed_tags'] as $tag)
			$r[$tag] = $full_allowedtags[$tag];
	}
	return $r;
}



function the_editor( ) {
	global $uexc, $wp_version, $tinymce_version;
	
	$opts = (object) $this->get_option();
	
	$baseurl = includes_url('js/tinymce');
	$mce_locale = ( '' == get_locale() ) ? 'en' : strtolower( substr(get_locale(), 0, 2) ); // only ISO 639-1
	$plugins = array( 'inlinepopups', 'tabfocus', 'paste', 'fullscreen', 'wordpress' );
	if( version_compare($wp_version, "3.2", ">=") )
		$plugins[] = 'media';
	$ext_plugins = $this->get_external_plugins(&$plugins, $mce_locale);
	
	$editor_style = !empty($opts->editor_style) ? $opts->editor_style : $uexc->url.'css/editor-content.css';
	
	$allowed_tags_array = array();
	$allowed_tags = $this->allowed_tags();
	foreach( $allowed_tags as $k=>$v)
		$allowed_tags_array[] = $k.'[*]';
	$allowed_tags = join(',', $allowed_tags_array);
	
	$width = absint($opts->width);
	$width = ($width > 100) ? $width.'px' : $width.'%';
	$height = max(100, absint($opts->height)).'px';
	
	$initArray = array (
		'mode' => 'specific_textareas',
		'editor_selector' => 'theEditor',
		'width' => $width,
		'height' => $height,
		'theme' => 'advanced',
		'skin' => $opts->skin,
		'theme_advanced_buttons1' => $opts->buttons1,
		'theme_advanced_buttons2' => $opts->buttons2,
		'theme_advanced_buttons3' => $opts->buttons3,
		'theme_advanced_buttons4' => $opts->buttons4,
		'language' => $mce_locale,
		'content_css' => $editor_style,
		'valid_elements' => $allowed_tags,
		'invalid_elements' => 'script,style,link',
		'theme_advanced_toolbar_location' => 'top',
		'theme_advanced_toolbar_align' => 'left',
		'theme_advanced_statusbar_location' => 'bottom',
		'theme_advanced_resizing' => true,
		'theme_advanced_resize_horizontal' => false,
		'theme_advanced_resizing_use_cookie' => true,
		'theme_advanced_disable' => 'code',
		'dialog_type' => 'modal',
		'relative_urls' => false,
		'remove_script_host' => false,
		'convert_urls' => false,
		'apply_source_formatting' => false,
		'remove_linebreaks' => true,
		'gecko_spellcheck' => true,
		'keep_styles' => false,
		'entities' => '38,amp,60,lt,62,gt',
		'accessibility_focus' => true,
		'tabfocus_elements' => 'major-publishing-actions',
		'media_strict' => false,
		'paste_remove_styles' => true,
		'paste_remove_spans' => true,
		'paste_strip_class_attributes' => 'all',
		'paste_text_use_dialog' => true,
		'wpeditimage_disable_captions' => true,
		'plugins' => implode( ',', $plugins ),
	);
	
	$formats = array('p','code','div','blockquote');
	for($i=1; $i<=6; $i++) if( in_array('h'.$i, $opts->allowed_tags) ) $formats[] = 'h'.$i;
	$formats = apply_filters($this->id.'_formats', join(',', $formats));
	if( !empty($formats) )
		$initArray['theme_advanced_blockformats'] = $formats;
		
	if( $fontsizes = apply_filters($this->id.'_fontsizes', "80%,100%,120%,150%,200%,300%"))
		$initArray['theme_advanced_font_sizes'] = $fontsizes;
		
	if( $fonts = apply_filters($this->id.'_fonts', ''))
		$initArray['theme_advanced_fonts'] = $fonts;
	
	$version = apply_filters('tiny_mce_version', '');
	$version = 'ver=' . $tinymce_version . $version;
	
	$language = $initArray['language'];
	if ( 'en' != $language )
		include_once(ABSPATH . WPINC . '/js/tinymce/langs/wp-langs.php');
	
	$initArray = apply_filters($this->id.'_before_init', $initArray);
	$mce_options = '';
	foreach ( $initArray as $k => $v ) {
		if ( is_bool($v) ) {
			$val = $v ? 'true' : 'false'; $mce_options .= $k . ':' . $val . ', '; continue;
		} elseif ( !empty($v) && is_string($v) && ( '{' == $v{0} || '[' == $v{0} ) ) {
			$mce_options .= $k . ':' . $v . ', '; continue;
		}
		$mce_options .= $k . ':"' . $v . '", ';
	}
	$mce_options = rtrim( trim($mce_options), '\n\r,' ); 
	?>

<style>textarea.theEditor { width: <?php echo $initArray['width']?> !important;}</style>

<script src="<?php echo $baseurl?>/tiny_mce.js?<?php echo $version?>"></script>
<?php
if ( 'en' != $language && isset($lang) )
	echo "<script type='text/javascript'>\n$lang\n</script>\n";
else
	echo "<script type='text/javascript' src='$baseurl/langs/wp-langs-en.js?$version'></script>\n";
?>

<script>
jQuery('textarea[name=comment]').each(function(){
	var f = jQuery(this).parents('form:eq(0)');
	var match = /\/wp-comments-post.php/.exec(f[0].action);
	if( match ){
		jQuery(this).addClass('theEditor');
		f.find('label[for=comment]').remove();
		return;
	}
});
jQuery('textarea.theEditor').each(function(){
	var toolbar = '';
	toolbar += '<span class="<?php echo $this->id?>-toolbar">';
	toolbar += '<a id="edButtonPreview" class="active" 	onclick="switchEditors.go(\''+this.id+'\', \'tinymce\');"><?php _e('Visual', $uexc->id)?></a>';
	toolbar += '<a id="edButtonHTML" 	class="" 		onclick="switchEditors.go(\''+this.id+'\', \'html\');"><?php _e('HTML', $uexc->id)?></a>';
	toolbar += '</span>';
	jQuery(this).wrap('<span class="<?php echo $this->id?>-wrap <?php echo $opts->skin?>"></span>').before(toolbar);
});

// overwrite for tinymce.plugins.WordPress
function getUserSetting( name, def ){ 
	if( name=='hidetb' ){
		return '1';
	}else if( name=='editor' ){
		return 'tinymce';
	}else if( typeof getAllUserSettings=='function'){
		var o = getAllUserSettings();
		if ( o.hasOwnProperty(name) )
			return o[name];
		if ( typeof def != 'undefined' )
			return def;
	}
	return '';
}

tinyMCEPreInit = {
	base : "<?php echo $baseurl; ?>",
	suffix : "",
	query : "<?php echo $version; ?>",
	mceInit : {<?php echo $mce_options; ?>},
	load_ext : function(url,lang){var sl=tinymce.ScriptLoader;sl.markDone(url+'/langs/'+lang+'.js');sl.markDone(url+'/langs/'+lang+'_dlg.js');}
};
<?php if ( $ext_plugins ) echo "$ext_plugins\n"; ?>
(function(){var t=tinyMCEPreInit,sl=tinymce.ScriptLoader,ln=t.mceInit.language,th=t.mceInit.theme,pl=t.mceInit.plugins;sl.markDone(t.base+'/langs/'+ln+'.js');sl.markDone(t.base+'/themes/'+th+'/langs/'+ln+'.js');sl.markDone(t.base+'/themes/'+th+'/langs/'+ln+'_dlg.js');tinymce.each(pl.split(','),function(n){if(n&&n.charAt(0)!='-'){sl.markDone(t.base+'/plugins/'+n+'/langs/'+ln+'.js');sl.markDone(t.base+'/plugins/'+n+'/langs/'+ln+'_dlg.js');}});})();
tinyMCE.init(tinyMCEPreInit.mceInit);
</script>
<?php
}


function get_external_plugins($plugins, $mce_locale){
	global $uexc;
	$opts = $this->get_option();
	
	$defaults = array('simpleimage', 'wpeditimage');
	$customs = preg_replace('/,\s*/',',',$opts['plugins']);
	$ext_plugins = array();
	$ret = '';
	
	foreach($defaults as $plugin){
		$ext_plugins[$plugin] = array(
			'url' => $uexc->url.'js/tiny_mce/plugins/'.$plugin.'/editor_plugin.js',
			'dir_path' => $uexc->path.'js/tiny_mce/plugins/'.$plugin.'/',
		);
	}
	
	if( !empty($customs) AND $opts['plugin_dir']){
		$customs = explode(',', $customs);
		foreach($customs as $plugin){
			$ext_plugins[$plugin] = array(
				'url' => WP_PLUGIN_URL.'/'.$opts['plugin_dir'].'/plugins/'.$plugin.'/editor_plugin.js',
				'dir_path' => WP_PLUGIN_DIR.'/'.$opts['plugin_dir'].'/plugins/'.$plugin.'/',
			);
		}
	}
	
	if( !empty($ext_plugins) ){	
		foreach ( $ext_plugins as $name => $v ) {
			if( $name=='media' ) 
				continue;
			
			if ( is_ssl() ) 
				$v['url'] = str_replace('http://', 'https://', $v['url']);
			
			$plugins[] = '-' . $name;
			
			$plugurl = dirname($v['url']);
			$path = $v['dir_path'] . 'langs/';
			$strings = $str1 = $str2 = '';

			if ( function_exists('realpath') )
				$path = trailingslashit( realpath($path) );

			if ( @is_file($path . $mce_locale . '.js') )
				$strings .= @file_get_contents($path . $mce_locale . '.js') . "\n";

			if ( @is_file($path . $mce_locale . '_dlg.js') )
				$strings .= @file_get_contents($path . $mce_locale . '_dlg.js') . "\n";

			if ( 'en' != $mce_locale && empty($strings) ) {
				if ( @is_file($path . 'en.js') ) {
					$str1 = @file_get_contents($path . 'en.js');
					$strings .= preg_replace( '/([\'"])en\./', '$1' . $mce_locale . '.', $str1, 1 ) . "\n";
				}

				if ( @is_file($path . 'en_dlg.js') ) {
					$str2 = @file_get_contents($path . 'en_dlg.js');
					$strings .= preg_replace( '/([\'"])en\./', '$1' . $mce_locale . '.', $str2, 1 ) . "\n";
				}
			}

			if ( ! empty($strings) )
				$ret .= "\n" . $strings . "\n";
		
			$ret .= 'tinyMCEPreInit.load_ext("' . $plugurl . '", "' . $mce_locale . '");' . "\n";
			$ret .= 'tinymce.PluginManager.load("' . $name . '", "' . $v['url'] . '");' . "\n";
		}
	}
	
	return $ret;
}








/* back-end
--------------------------------------------------------------------------------------- */

function get_option(){
	require 'allowed-tags.php';
	
	$options = array (
		'enable' => '',
		'buttons1' => $this->get_default_buttons1(),
		'buttons2' => $this->get_default_buttons2(),
		'buttons3' => '',
		'buttons4' => '',
		'plugins' => '', 
		'plugin_dir' => '',
		'width' => 100,
		'height' => 400,
		'skin' => 'wp_theme',
		'editor_style' => '',
		'allowed_tags' => $default_allowedtags,
		'roles' => array(),
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
		__('Editor', $uexc->id), 
		__('Editor', $uexc->id), 
		'manage_options', 
		$this->id, 
		array(&$this, 'admin_page') 
	);
}

function admin_page(){
	global $uexc, $default_allowedtags, $full_allowedtags;
	
	$opts = (object) $this->get_option();
	if( empty($opts->allowed_tags) ) $opts->allowed_tags = $default_allowedtags;
	$skins = array('default', 'highcontrast', 'o2k7', 'wp_theme');
	?>
	
	<div class="wrap">
		<?php screen_icon("options-general"); ?>
		<h2>U Ex-Comment &raquo; <?php _e('Editor', $uexc->id);?></h2>
		
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
				<th><?php _e('Editor Size', $uexc->id)?></th>
				<td>
					<?php _e('Width', $uexc->id)?> :
					<input type="text" name="<?php echo $this->id?>[width]" value="<?php echo $opts->width;?>" size="3" id="editor-width"> <span>px</span> &nbsp;&nbsp;
					<span class="description">0~100 =&gt; %, 101~ =&gt; px</span>
					<br>
					<?php _e('Height', $uexc->id)?> :
					<input type="text" name="<?php echo $this->id?>[height]" value="<?php echo $opts->height;?>" size="3"> px
				</td>
			</tr>
			<tr>
				<th><?php _e('Editor Skin', $uexc->id)?></th>
				<td>
					<select name="<?php echo $this->id?>[skin]">
						<?php foreach($skins as $skin){ ?>
						<option value="<?php echo $skin?>" <?php selected($opts->skin, $skin)?>><?php echo $skin?></option>
						<?php } ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><?php _e('Buttons group', $uexc->id)?> 1</th>
				<td>
					<input type="text" name="<?php echo $this->id?>[buttons1]" value="<?php echo $opts->buttons1;?>" class="widefat">
					<p class="description"><?php _e('Separate buttons with commas. Pipe character( | ) is visual separator.', $uexc->id)?></p>
				</td>
			</tr>
			<tr>
				<th><?php _e('Buttons group', $uexc->id)?> 2</th>
				<td>
					<input type="text" name="<?php echo $this->id?>[buttons2]" value="<?php echo $opts->buttons2;?>" class="widefat">
					<p class="description"><?php _e('Separate buttons with commas. Pipe character( | ) is visual separator.', $uexc->id)?></p>
				</td>
			</tr>
			<tr>
				<th><?php _e('Buttons group', $uexc->id)?> 3</th>
				<td>
					<input type="text" name="<?php echo $this->id?>[buttons3]" value="<?php echo $opts->buttons3;?>" class="widefat">
					<p class="description"><?php _e('Separate buttons with commas. Pipe character( | ) is visual separator.', $uexc->id)?></p>
				</td>
			</tr>
			<tr>
				<th><?php _e('Buttons group', $uexc->id)?> 4</th>
				<td>
					<input type="text" name="<?php echo $this->id?>[buttons4]" value="<?php echo $opts->buttons4;?>" class="widefat">
					<p class="description"><?php _e('Separate buttons with commas. Pipe character( | ) is visual separator.', $uexc->id)?></p>
				</td>
			</tr>
			<tr>
				<th></th>
				<td>
					<p><strong><?php _e('Available buttons', $uexc->id)?> :</strong></p>
					<p><code><?php echo $this->get_buttons_list();?></code></p>
					<br>
					
					<p><strong><?php _e('Extend TinyMCE Plugin', $uexc->id)?></strong> 
						<span style="color:red">(<?php _e('This is not required.', $uexc->id)?>)</span></p>
					
					<p><?php _e('Plugin directory', $uexc->id)?>:
						<span class="description"><?php echo WP_PLUGIN_URL?>/</span>
						<input type="text" name="<?php echo $this->id?>[plugin_dir]" value="<?php echo $opts->plugin_dir;?>" ></p>
					
					<p><?php _e('Plugin names', $uexc->id)?>:
						<input type="text" name="<?php echo $this->id?>[plugins]" value="<?php echo $opts->plugins;?>" >
						<span class="description"><?php _e('Separate plugin name with commas.', $uexc->id)?></span></p>
					
					<p><a href="http://urlless.com/extending-tinymce-plugin-for-u-buddypress-forum-editor/" target="_blank"><?php _e('How to extend TinyMCE plugin', $uexc->id)?></a></p>
				</td>
			</tr>
			
			<tr>
				<th><?php _e('Allowed Tags', $uexc->id)?></th>
				<td>
					<div class="allowed-tags default-tags">
					<strong><?php _e('Default allowed tags', $uexc->id)?>:</strong>
					<span><?php foreach($full_allowedtags as $k=>$v){ if(in_array($k, $default_allowedtags)){ ?>
					<label><input type="checkbox" name="<?php echo $this->id?>[allowed_tags][]" value="<?php echo $k?>" <?php checked(in_array($k, $opts->allowed_tags))?>><?php echo $k?></label>
					<?php }} ?></span>
					<br class="clear">
					</div>
					
					<div class="allowed-tags additional-tags">
					<strong><?php _e('Additional tags', $uexc->id)?>: &nbsp;
						<label><input type="checkbox" id="allow-all-additional-tags"><?php _e('Check all', $uexc->id)?></label></strong>
					<span><?php foreach($full_allowedtags as $k=>$v){ if(!in_array($k, $default_allowedtags)){ ?>
					<label><input type="checkbox" name="<?php echo $this->id?>[allowed_tags][]" value="<?php echo $k?>" <?php checked(in_array($k, $opts->allowed_tags))?>><?php echo $k?></label> 
					<?php }} ?></span>
					<br class="clear">
					</div>
					
					<p class="description"><?php _e('For instance, if you would embed Youtube, select <code>iframe</code>. and if you would use old embed code(Flash), select <code>object, embed and param</code>.', $uexc->id)?></p>
					<p class="description"><?php _e('Some tags are never allowed. script, style, link.', $uexc->id)?></p>
				</td>
			</tr>
			
			<tr>
				<th><?php _e('Editor Content CSS URL', $uexc->id)?></th>
				<td>
					<input type="text" name="<?php echo $this->id?>[editor_style]" value="<?php echo $opts->editor_style;?>" class="widefat">
					<p class="description"><?php _e('If you want to customize CSS of Editor content, enter your own stylesheet file URL.', $uexc->id)?></p>
					<p class="description"><?php printf(__('If you leave a blank, the %s CSS will be used.', $uexc->id), '<a href="'.$uexc->url.'css/editor-content.css">'.__('defaults', $uexc->id).'</a>')?></p>
				</td>
			</tr>
			
			</table>
			
			<p class="submit">
				<input name="submit" type="submit" class="button-primary" value="<?php esc_attr_e(__('Save Changes'))?>" />
			</p>
		</form>
		
		<style>
		.allowed-tags { margin-bottom: 15px;}
		.allowed-tags strong { display: block; margin-bottom: 5px;}
		.allowed-tags strong label { font-weight: normal; }
		.allowed-tags span label { float: left; margin-right: 10px; }
		.allowed-tags label input{ margin-right: 3px; }
		</style>
		
		<script>
		jQuery('#editor-width').keyup(function(){ var unit = jQuery(this).next('span'); if( Number(this.value)>100 ) unit.text('px'); else unit.text('%');}).trigger('keyup');
		jQuery('#allow-all-additional-tags').click(function(){ if( this.checked ){ jQuery('.additional-tags input').attr('checked', 'checked'); }else{ jQuery('.additional-tags input').removeAttr('checked'); }});
		</script>
		
	</div>
	<?php
}


function admin_page_vailidate($input){
	$r = array();
	$r['enable'] = !empty($input['enable']) ? '1' : '';
	$r['width'] = absint($input['width']) ? absint($input['width']) : 100;
	$r['height'] = absint($input['height']) ? absint($input['height']) : 300;
	$r['skin'] = $input['skin'];
	$r['buttons1'] = $input['buttons1'];
	$r['buttons2'] = $input['buttons2'];
	$r['buttons3'] = $input['buttons3'];
	$r['buttons4'] = $input['buttons4'];
	$r['plugins'] = $input['plugins'];
	$r['plugin_dir'] = untrailingslashit($input['plugin_dir']);
	$r['allowed_tags'] = $input['allowed_tags'];
	$r['editor_style'] = $input['editor_style'];
	$r['roles'] = !empty($input['roles']) ? $input['roles'] : '';
	
	uexc_set_cap($r['roles'], $this->cap_name);
	add_settings_error($this->id, 'settings_updated', __('Settings saved.'), 'updated');
	return $r;
}


function get_default_buttons1(){
	return 'formatselect, |, forecolor, |, bold, italic, underline, strikethrough, |, justifyleft, justifycenter, justifyright, | ,removeformat';
}

function get_default_buttons2(){
	return 'undo, redo,|, pastetext, pasteword, |, bullist, numlist, |, outdent, indent, |, link, unlink, charmap, image, |, fullscreen';
}

function get_buttons_list(){
	return 'formatselect, fontselect, fontsizeselect, forecolor, backcolor, bold, italic, underline, strikethrough, justifyleft, justifycenter, justifyright, justifyfull, sub, sup, removeformat, undo, redo, pastetext, pasteword, bullist, numlist, outdent, indent, blockquote, link, unlink, hr, image, media, charmap, fullscreen';
}

}

new UexcommEditor();
