<?php
/**
 * @package User Time Zone
 * @version 3.00
 */
/*
/*
Plugin Name: User Time Zone
Plugin URI:
Description: Allows users to set-up their local time zone on the profile page and view their local time on the website's frontend.
Version: 3.00
Author: Aimbox
Author URI: http://aimbox.com
*/


class UserTimezone
{
    protected $user_gmt_offset=null;
    protected $user_meta_timezone_key = 'utz_timezone';
	protected $user_meta_gmt_offset_key = 'utz_gmt_offset';
	protected $form_select_name = 'timezone_string';
    
    private static $instance;

    public static function getInstance()
	{
		if(!isset(self::$instance))self::$instance = new self();
		return self::$instance;
	}

    private function __construct()
    {
		add_action('personal_options', array(&$this, 'draw_options_page'));//on profile page (if current user)

        add_action('personal_options_update', array(&$this, 'update_time_zone'));
        add_action('edit_user_profile_update', array(&$this, 'update_time_zone'));

        add_action('init', array(&$this, 'onInit'), 1);
    }

	public function onInit(){
		global $current_user;
		$user_gmt_offset = $this->get_user_gmt_offset($current_user);
		$this->user_gmt_offset = $user_gmt_offset;
		if($user_gmt_offset!==null){
			add_filter('get_the_time', array(&$this,'on_get_the_time'), 1, 3);
			add_filter('get_the_date', array(&$this,'on_get_the_date'), 1, 2);
			
			add_filter('get_the_modified_time', array(&$this,'on_get_the_modified_time'), 1, 2);
			add_filter('get_the_modified_date', array(&$this, 'on_get_the_modified_date'), 1, 2);

			add_filter('get_comment_time', array(&$this,'on_get_comment_time'), 1, 4);
			add_filter('get_comment_date', array(&$this, 'on_get_comment_date'), 1, 2);
		}
	}
	    
	/**
	* Return gmt offset (in seconds) for current user (if user is logged and have 't-zone' value in user_meta table) 
	* or null (if user not logged or not set custom timezone in his profile)
	*/
    protected function get_user_gmt_offset($user){
		$gmt_offset = null;
		$user_id = $user->ID;
		if($user_id!=0){  
			$gmt_offset = get_usermeta($user_id,$this->user_meta_gmt_offset_key);
			if(empty($gmt_offset)) $gmt_offset=null; 
		}
		return $gmt_offset;	
    }

    //*********************************
    //Filted used functions
    //*********************************
	protected function fix_mysql_datetime($mysql_date)
	{
		$gmt_offset = $this->user_gmt_offset;
		
		$dtime = new DateTime($mysql_date);
		$dtime->setTimestamp($dtime->getTimestamp()+intval($gmt_offset));
		$date = $dtime->format('Y-m-d H:i:s');
		return $date;
	}
	

	//**************************************************
	// FILTER FUNCS
	//**************************************************

	function on_get_the_time( $time_string, $d, $post )
	{
		$post = get_post($post);
		if ( '' == $d )
			$the_time = $this->get_post_time_mod(get_option('time_format'), false, $post, true);
		else
			$the_time = $this->get_post_time_mod($d, false, $post, true);
		return $the_time;
	}

	function on_get_the_date($date_string, $d)
	{
		$post = get_post();
		$the_date = '';
		
		$post_date = $post->post_date;
		$post_date = $this->fix_mysql_datetime($post_date);
		
		if ( '' == $d )
			$the_date .= mysql2date(get_option('date_format'), $post_date);
		else
			$the_date .= mysql2date($d, $post_date);
		return $the_date;
	}

	function on_get_the_modified_time($date_string, $d = '')
	{
		if ( '' == $d ) $the_time = $this->get_post_modified_time_mod(get_option('time_format'), null, null, true);
		else $the_time = $this->get_post_modified_time_mod($d, null, null, true);
		return $the_time;
	}

	function on_get_the_modified_date($date_string, $time_format)
	{
		if ( '' == $d ) 
			$the_time = $this->get_post_modified_time_mod(get_option('date_format'), null, null, true);
		else 
			$the_time = $this->get_post_modified_time_mod($d, null, null, true);
		return $the_time;
	}
	
	function on_get_comment_time($time_string, $d = '', $gmt = false, $translate = true )
	{
		global $comment;
		$comment_date = $gmt ? $comment->comment_date_gmt : $comment->comment_date;
		if(!$gmt) $comment_date = $this->fix_mysql_datetime($comment_date);
		if ( '' == $d )
			$date = mysql2date(get_option('time_format'), $comment_date, $translate);
		else
			$date = mysql2date($d, $comment_date, $translate);
		return $date;
	}
	
	function on_get_comment_date($date_string, $d)
	{
		$comment = get_comment( $comment_ID );
		$comment_date = $comment->comment_date;
		$comment_date = $this->fix_mysql_datetime($comment_date);
		if ( '' == $d )
			$date = mysql2date(get_option('date_format'), $comment_date);
		else
			$date = mysql2date($d, $comment_date);
		return $date;
	}

	//*****************************************
	// Replaced WP functions
	//*****************************************
	function get_post_time_mod( $d = 'U', $gmt = false, $post = null, $translate = false ) { // returns timestamp
		$current_user_gmt_offset = $this->user_gmt_offset;
		//if($current_user_gmt_offset===null) return get_post_time($d,$gmt,$post,$translate);
		
		$post = get_post($post);

		if ( $gmt )
			$time = $post->post_date_gmt;
		else{
			$time = $post->post_date;
			$time = $this->fix_mysql_datetime($time);
		}

		$time = mysql2date($d, $time, $translate);
		return apply_filters('get_post_time', $time, $d, $gmt);
	}
 
	 function get_post_modified_time_mod( $d = 'U', $gmt = false, $post = null, $translate = false ) {
	 	 
		$post = get_post($post);

		if ( $gmt )
			$time = $post->post_modified_gmt;
		else{
			$time = $post->post_modified;
			$time = $this->fix_mysql_datetime($time);
		}
		$time = mysql2date($d, $time, $translate);

		return apply_filters('get_post_modified_time', $time, $d, $gmt);
	}

	//-------------------------------------
	//Admin settings
	//-------------------------------------
    public function draw_options_page($user)
    {
    	$timezone_format = _x('Y-m-d G:i:s', 'timezone date format');

    	$user_tzstring = get_usermeta($user->ID,$this->user_meta_timezone_key);
    	$user_gmt_offset = get_usermeta($user->ID,$this->user_meta_gmt_offset_key);
		
		$global_tzstring = $tzstring;
    	$global_offset = $current_offset;

    	if($user_gmt_offset!==null){
    		$result_offset = $user_gmt_offset;
    		$result_tzstring = $user_tzstring;
    	}else{
    		$result_offset = get_option('gmt_offset')*3600;
    		$result_tzstring = get_option('timezone_string');
    	}
		
    	$tzstring =  $result_tzstring;
		$current_offset = $result_offset;
		
		$check_zone_info = true;
		// Remove old Etc mappings. Fallback to gmt_offset.
		if ( false !== strpos($tzstring,'Etc/GMT') )
			$tzstring = '';

		if ( empty($tzstring) ) { // Create a UTC+- zone if no timezone string exists
			$check_zone_info = false;
			if ( 0 == $current_offset )
				$tzstring = 'UTC+0';
			elseif ($current_offset < 0)
				$tzstring = 'UTC' . $current_offset;
			else
				$tzstring = 'UTC+' . $current_offset;
		}

    	?>
    	<tr>
    		<th scope="row"><label for="timezone_string"><?php _e('Timezone') ?></label></th>
    		<td>
    			<select id="timezone_string" name="timezone_string">
    				<?php echo $this->wp_timezone_choice($user_tzstring); ?>
				</select>
				<p class="description"><?php _e('Choose a city in the same timezone as you.'); ?></p>
 				
 				<?php 
 					$time = time();
 					$localtime = $time + $result_offset;
 				 ?>
 				<span id="utc-time">
 					<?php printf(__('<abbr title="Coordinated Universal Time">UTC</abbr> time is <code>%s</code>'), date_i18n($timezone_format, $time, 'gmt')); ?>
 				</span>

 				<?php if ( $result_tzstring || !empty($result_offset) ) : ?>
					<span id="local-time"><?php printf(__('Local time is <code>%1$s</code>'), date_i18n($timezone_format,$localtime)); ?></span>
				<?php endif; ?>

				<?php if ($check_zone_info && $tzstring) : ?>
				<br />
				<span>
					<?php
					// Set TZ so localtime works.
					date_default_timezone_set($tzstring);
					$now = localtime(time(), true);
					if ( $now['tm_isdst'] )
						_e('This timezone is currently in daylight saving time.');
					else
						_e('This timezone is currently in standard time.');
					?>
					<br />
					<?php
					$allowed_zones = timezone_identifiers_list();

					if ( in_array( $tzstring, $allowed_zones) ) {
						$found = false;
						$date_time_zone_selected = new DateTimeZone($tzstring);
						$tz_offset = timezone_offset_get($date_time_zone_selected, date_create());
						$right_now = time();
						foreach ( timezone_transitions_get($date_time_zone_selected) as $tr) {
							if ( $tr['ts'] > $right_now ) {
								$found = true;
								break;
							}
						}

						if ( $found ) {
							echo ' ';
							$message = $tr['isdst'] ?
								__('Daylight saving time begins on: <code>%s</code>.') :
								__('Standard time begins on: <code>%s</code>.');
							// Add the difference between the current offset and the new offset to ts to get the correct transition time from date_i18n().
							printf( $message, date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $tr['ts'] + ($tz_offset - $tr['offset']) ) );
						} else {
							_e('This timezone does not observe daylight saving time.');
						}
					}
					// Set back to UTC.
					date_default_timezone_set('UTC');
					?>
					</span>
				<?php endif; ?>
    		</td>
    	</tr>
    	<?php
    }
    
	 /**
	 * Gives a nicely formatted list of timezone strings. // temporary! Not in final
	 *
	 * @since 2.9.0
	 *
	 * @param string $selected_zone Selected Zone
	 * @return string
	 */
	protected function wp_timezone_choice( $selected_zone ) {
		static $mo_loaded = false;

		$continents = array( 'Africa', 'America', 'Antarctica', 'Arctic', 'Asia', 'Atlantic', 'Australia', 'Europe', 'Indian', 'Pacific');

		// Load translations for continents and cities
		if ( !$mo_loaded ) {
			$locale = get_locale();
			$mofile = WP_LANG_DIR . '/continents-cities-' . $locale . '.mo';
			load_textdomain( 'continents-cities', $mofile );
			$mo_loaded = true;
		}

		$zonen = array();
		foreach ( timezone_identifiers_list() as $zone ) {
			$zone = explode( '/', $zone );
			if ( !in_array( $zone[0], $continents ) ) {
				continue;
			}

			// This determines what gets set and translated - we don't translate Etc/* strings here, they are done later
			$exists = array(
				0 => ( isset( $zone[0] ) && $zone[0] ),
				1 => ( isset( $zone[1] ) && $zone[1] ),
				2 => ( isset( $zone[2] ) && $zone[2] ),
			);
			$exists[3] = ( $exists[0] && 'Etc' !== $zone[0] );
			$exists[4] = ( $exists[1] && $exists[3] );
			$exists[5] = ( $exists[2] && $exists[3] );

			$zonen[] = array(
				'continent'   => ( $exists[0] ? $zone[0] : '' ),
				'city'        => ( $exists[1] ? $zone[1] : '' ),
				'subcity'     => ( $exists[2] ? $zone[2] : '' ),
				't_continent' => ( $exists[3] ? translate( str_replace( '_', ' ', $zone[0] ), 'continents-cities' ) : '' ),
				't_city'      => ( $exists[4] ? translate( str_replace( '_', ' ', $zone[1] ), 'continents-cities' ) : '' ),
				't_subcity'   => ( $exists[5] ? translate( str_replace( '_', ' ', $zone[2] ), 'continents-cities' ) : '' )
			);
		}
		usort( $zonen, '_wp_timezone_choice_usort_callback' );

		$structure = array();

		/*if ( empty( $selected_zone ) ) {
			$structure[] = '<option selected="selected" value="">' . __( 'Select a city' ) . '</option>';
		}*/
		$default_selected =  empty( $selected_zone ) ? '' : ' selected="selected"';
		$default_gmt_offset_in_sec = floatval(get_option('gmt_offset'))*3600;
		$default_gmt_offset_str = ($default_gmt_offset_in_sec<0) ? '-' : '';
		$default_gmt_offset_str .= gmdate("H:i",abs($default_gmt_offset_in_sec));
		$timezone = get_option('timezone_string');
		if (empty($timezone)) $timezone = 'Manual offset';
		$default_option = '<option value=""'.$default_selected.' >Use website defaults (Timezone:'.$timezone.', GMT-offset:'.$default_gmt_offset_str.')</option>';
		
        $structure[] = $default_option;
		
		foreach ( $zonen as $key => $zone ) {
			// Build value in an array to join later
			$value = array( $zone['continent'] );

			if ( empty( $zone['city'] ) ) {
				// It's at the continent level (generally won't happen)
				$display = $zone['t_continent'];
			} else {
				// It's inside a continent group

				// Continent optgroup
				if ( !isset( $zonen[$key - 1] ) || $zonen[$key - 1]['continent'] !== $zone['continent'] ) {
					$label = $zone['t_continent'];
					$structure[] = '<optgroup label="'. esc_attr( $label ) .'">';
				}

				// Add the city to the value
				$value[] = $zone['city'];

				$display = $zone['t_city'];
				if ( !empty( $zone['subcity'] ) ) {
					// Add the subcity to the value
					$value[] = $zone['subcity'];
					$display .= ' - ' . $zone['t_subcity'];
				}
			}

			// Build the value
			$value = join( '/', $value );
			$selected = '';
			if ( $value === $selected_zone ) {
				$selected = 'selected="selected" ';
			}
			$structure[] = '<option ' . $selected . 'value="' . esc_attr( $value ) . '">' . esc_html( $display ) . "</option>";

			// Close continent optgroup
			if ( !empty( $zone['city'] ) && ( !isset($zonen[$key + 1]) || (isset( $zonen[$key + 1] ) && $zonen[$key + 1]['continent'] !== $zone['continent']) ) ) {
				$structure[] = '</optgroup>';
			}
		}

		// Do UTC
		$structure[] = '<optgroup label="'. esc_attr__( 'UTC' ) .'">';
		$selected = '';
		if ( 'UTC' === $selected_zone )
			$selected = 'selected="selected" ';
		$structure[] = '<option ' . $selected . 'value="' . esc_attr( 'UTC' ) . '">' . __('UTC') . '</option>';
		$structure[] = '</optgroup>';

		// Do manual UTC offsets
		$structure[] = '<optgroup label="'. esc_attr__( 'Manual Offsets' ) .'">';
		$offset_range = array (-12, -11.5, -11, -10.5, -10, -9.5, -9, -8.5, -8, -7.5, -7, -6.5, -6, -5.5, -5, -4.5, -4, -3.5, -3, -2.5, -2, -1.5, -1, -0.5,
			0, 0.5, 1, 1.5, 2, 2.5, 3, 3.5, 4, 4.5, 5, 5.5, 5.75, 6, 6.5, 7, 7.5, 8, 8.5, 8.75, 9, 9.5, 10, 10.5, 11, 11.5, 12, 12.75, 13, 13.75, 14);
		foreach ( $offset_range as $offset ) {
			if ( 0 <= $offset )
				$offset_name = '+' . $offset;
			else
				$offset_name = (string) $offset;

			$offset_value = $offset_name;
			$offset_name = str_replace(array('.25','.5','.75'), array(':15',':30',':45'), $offset_name);
			$offset_name = 'UTC' . $offset_name;
			$offset_value = 'UTC' . $offset_value;
			$selected = '';
			if ( $offset_value === $selected_zone )
				$selected = 'selected="selected" ';
			$structure[] = '<option ' . $selected . 'value="' . esc_attr( $offset_value ) . '">' . esc_html( $offset_name ) . "</option>";

		}
		$structure[] = '</optgroup>';

		return join( "\n", $structure );
	}
    
	public function update_time_zone($user_id)
	{
		$gmt_offset = null;

		if (isset($_POST[$this->form_select_name])) {
			$timezone_name = $_POST[$this->form_select_name];
			if(empty($timezone_name)){
				delete_user_meta($user_id,$this->user_meta_gmt_offset_key);		
				delete_user_meta($user_id,$this->user_meta_timezone_key);
			}else{
				$this->user_timezone = $timezone_name;
				if(preg_match('/^UTC[+-]/', $timezone_name)) {
					$gmt_offset = preg_replace('/UTC\+?/', '', $timezone_name);
					$gmt_offset *=3600;
				}
				else{
					$dtz = new DateTimeZone($timezone_name);
					$time_zone_time = new DateTime('now', $dtz);
					$gmt_offset = $dtz->getOffset($time_zone_time);
				}
				update_user_meta($user_id, $this->user_meta_timezone_key, $timezone_name);
				update_user_meta($user_id,$this->user_meta_gmt_offset_key,$gmt_offset);
			}
		}
	}
}

UserTimezone::getInstance();

?>