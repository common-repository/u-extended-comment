<?php

add_action( 'widgets_init', 'uexc_widgets_init' ); 

function uexc_widgets_init() { 
	register_widget( 'Uexc_Widget_Recent_Comments' ); 
}





class Uexc_Widget_Recent_Comments extends WP_Widget {
	
	function __construct() {
		global $uexc;
		$widget_ops = array('classname' => 'uexc_widget_recent_comments', 'description' => __( "The most recent comments for 'U Extended Comment'", $uexc->id ) );
		parent::__construct('uexc-recent-comments', __('Recent Comments for U Ex-comment', $uexc->id), $widget_ops);
		$this->alt_option_name = 'uexc_widget_recent_comments';

		add_action( 'comment_post', array(&$this, 'flush_widget_cache') );
		add_action( 'transition_comment_status', array(&$this, 'flush_widget_cache') );
	}

	function widget( $args, $instance ) {
		global $comments, $comment, $uexc;
		
		$cache = wp_cache_get('uexc_widget_recent_comments', 'widget');

		if ( ! is_array( $cache ) )
			$cache = array();

		if ( isset( $cache[$args['widget_id']] ) ) {
			echo $cache[$args['widget_id']];
			return;
		}

 		extract($args, EXTR_SKIP);
 		$output = '';
 		$title = apply_filters('widget_title', empty($instance['title']) ? __('Recent Comments') : $instance['title']);

		if ( ! $number = absint( $instance['number'] ) )
 			$number = 5;
		
		$comments = get_comments( array( 'number' => $number, 'status' => 'approve', 'post_status' => 'publish', 'type'=>'comment' ) );
		
		$output .= $before_widget;
		if ( $title )
			$output .= $before_title . $title . $after_title;
		
		$output .= '<ul id="uexc-recent-comments">';
		
		$private_icon = '<img src="'.$uexc->url.'images/lock-s.gif" class="uexc-private-icon"> ';
		
		if ( $comments) {
			foreach ( (array) $comments as $comment) {
				$comment_id = absint($comment->comment_ID);
				$post_title = strip_tags(get_the_title($comment->comment_post_ID));
				$post_title = $this->word_limit($post_title, $instance['content_length']);
				$post_title = '<a href="'.esc_url( get_comment_link($comment_id) ).'">'.$post_title.'</a> ';
				
				$comment_content = strip_tags(apply_filters( 'the_title', $comment->comment_content));
				$comment_content = $this->word_limit($comment_content, $instance['content_length']);
				$comment_content = '<a href="'.esc_url( get_comment_link($comment_id) ).'">'.$comment_content.'</a> ';
				
				$comment_author = get_comment_author();
				
				$comment_author_link = get_comment_author_link();
				
				$comment_date = date($instance['date_format'], strtotime($comment->comment_date));
				
				$a = $instance['list_format'];
				$a = str_ireplace('%content%', $comment_content, $a);
				$a = str_ireplace('%author%', $comment_author, $a);
				$a = str_ireplace('%author_link%', $comment_author_link, $a);
				$a = str_ireplace('%date%', $comment_date, $a);
				$a = str_ireplace('%post_title%', $post_title, $a);
				
				
				$is_private = $uexc->plugins->utils->is_private($comment_id);
				$user_can_read = $uexc->plugins->utils->user_can_read_private($comment_id);
				if( $is_private ){
					if( $user_can_read ){
						$a = $private_icon.$a;
					}else{
						$a = $private_icon.__('Private comment.', $uexc->id);
					}
				}
				
				$output .= '<li class="uexc-recent-comments">'.$a.'</li>';
			}
 		}
		$output .= '</ul>';
		$output .= $after_widget;

		echo $output;
		$cache[$args['widget_id']] = $output;
		wp_cache_set('uexc_widget_recent_comments', $cache, 'widget');
	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] 			= strip_tags($new_instance['title']);
		$instance['number'] 		= absint( $new_instance['number'] );
		$instance['list_format'] 	= $new_instance['list_format'];
		$instance['content_length'] = absint( $new_instance['content_length'] );
		$instance['date_format'] 	= $new_instance['date_format'];
		$this->flush_widget_cache();

		$alloptions = wp_cache_get( 'alloptions', 'options' );
		if ( isset($alloptions['uexc_widget_recent_comments']) )
			delete_option('uexc_widget_recent_comments');

		return $instance;
	}

	function form( $instance ) {
		global $uexc;
		$title 			= isset($instance['title']) ? esc_attr($instance['title']) : '';
		$number 		= isset($instance['number']) ? absint($instance['number']) : 5;
		$list_format 	= isset($instance['list_format']) ? $instance['list_format'] : '%author_link% on %post_title%';
		$content_length	= isset($instance['content_length']) ? absint($instance['content_length']) : 5;
		$date_format	= isset($instance['date_format']) ? $instance['date_format'] : 'F j, Y';
		?>
		<p>
			<label><?php _e('Title', $uexc->id); ?>:</label>
			<input type="text" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" value="<?php echo $title; ?>" class="widefat" />
		</p>

		<p>
			<label><?php _e('Number of comments to show', $uexc->id); ?>:</label>
			<input type="text" id="<?php echo $this->get_field_id('number'); ?>" name="<?php echo $this->get_field_name('number'); ?>" value="<?php echo $number; ?>" size="1" />
		</p>
		
		<p>
			<label><?php _e('List format', $uexc->id); ?>:</label>
			<input type="text" id="<?php echo $this->get_field_id('list_format'); ?>" name="<?php echo $this->get_field_name('list_format'); ?>" value="<?php echo $list_format?>" class="widefat"/>
		</p>
		
		<p>
			<?php _e('Replace Keywords', $uexc->id)?>:<br>
			<code>%content%, %date%, %author%, %author_link%, %post_title%</code>
		</p>
		
		<p>
			<label><?php _e('Comment content / Post title length', $uexc->id); ?>:</label>
			<input type="text" id="<?php echo $this->get_field_id('content_length'); ?>" name="<?php echo $this->get_field_name('content_length'); ?>" value="<?php echo $content_length?>" size="1" />
			<?php _e('words', $uexc->id)?>
		</p>
		
		<p>
			<label><?php _e('Date format', $uexc->id); ?>:</label>
			<input type="text" id="<?php echo $this->get_field_id('date_format'); ?>" name="<?php echo $this->get_field_name('date_format'); ?>" value="<?php echo $date_format?>" class="widefat" />
			<br><a href="http://codex.wordpress.org/Formatting_Date_and_Time" target="_blank"><?php _e('Documentation on date and time formatting.', $uexc->id)?></a>
		</p>
		<?php
	}
	
	function flush_widget_cache() {
		wp_cache_delete('uexc_widget_recent_comments', 'widget');
	}
	
	function word_limit($str, $count){
		if( !$count )
			return $str;
			
		$words = explode(' ', $str);
		if(count($words) > $count) {
			array_splice($words, $count);
			$str = implode(' ', $words);
		}
		return $str;
	}

	
}





























