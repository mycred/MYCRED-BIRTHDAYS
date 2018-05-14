<?php
/**
 * Plugin Name: myCRED Birthdays
 * Description: Reward your users with points on their birthdays.
 * Version: 1.0.1
 * Tags: points, tokens, credit, management, reward, charge, birthday
 * Author: Gabriel S Merovingi
 * Author URI: http://www.merovingi.com
 * Author Email: support@mycred.me
 * Requires at least: WP 4.0
 * Tested up to: WP 4.7
 * Text Domain: mycred_bday
 * Domain Path: /lang
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
if ( ! class_exists( 'myCRED_Birthdays' ) ) :
	final class myCRED_Birthdays {

		// Plugin Version
		public $version             = '1.0.1';

		// Instnace
		protected static $_instance = NULL;

		// Current session
		public $session             = NULL;

		public $slug                = '';
		public $domain              = '';
		public $plugin              = NULL;
		public $plugin_name         = '';
		protected $update_url       = 'http://mycred.me/api/plugins/';

		/**
		 * Setup Instance
		 * @since 1.0
		 * @version 1.0
		 */
		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		/**
		 * Not allowed
		 * @since 1.0
		 * @version 1.0
		 */
		public function __clone() { _doing_it_wrong( __FUNCTION__, 'Cheatin&#8217; huh?', '1.0' ); }

		/**
		 * Not allowed
		 * @since 1.0
		 * @version 1.0
		 */
		public function __wakeup() { _doing_it_wrong( __FUNCTION__, 'Cheatin&#8217; huh?', '1.0' ); }

		/**
		 * Define
		 * @since 1.0
		 * @version 1.0
		 */
		private function define( $name, $value, $definable = true ) {
			if ( ! defined( $name ) )
				define( $name, $value );
		}

		/**
		 * Require File
		 * @since 1.0
		 * @version 1.0
		 */
		public function file( $required_file ) {
			if ( file_exists( $required_file ) )
				require_once $required_file;
		}

		/**
		 * Construct
		 * @since 1.0
		 * @version 1.0
		 */
		public function __construct() {

			$this->slug        = 'mycred-birthdays';
			$this->plugin      = plugin_basename( __FILE__ );
			$this->domain      = 'mycred_bday';
			$this->plugin_name = 'myCRED Birthdays';

			$this->define_constants();
			$this->plugin_updates();

			add_filter( 'mycred_setup_hooks',    array( $this, 'register_hook' ) );
			add_action( 'mycred_init',           array( $this, 'load_textdomain' ) );
			add_action( 'mycred_all_references', array( $this, 'add_badge_support' ) );
			add_action( 'mycred_load_hooks',    'mycred_birthdays_load_hook' );

		}

		/**
		 * Define Constants
		 * @since 1.0
		 * @version 1.0
		 */
		public function define_constants() {

			$this->define( 'MYCRED_BP_COMPLIMENTS_VER',  $this->version );
			$this->define( 'MYCRED_BP_COMPLIMENTS_SLUG', $this->slug );
			$this->define( 'MYCRED_DEFAULT_TYPE_KEY',    'mycred_default' );

		}

		/**
		 * Includes
		 * @since 1.0
		 * @version 1.0
		 */
		public function includes() { }

		/**
		 * Load Textdomain
		 * @since 1.0
		 * @version 1.0
		 */
		public function load_textdomain() {

			// Load Translation
			$locale = apply_filters( 'plugin_locale', get_locale(), $this->domain );

			load_textdomain( $this->domain, WP_LANG_DIR . '/' . $this->slug . '/' . $this->domain . '-' . $locale . '.mo' );
			load_plugin_textdomain( $this->domain, false, dirname( $this->plugin ) . '/lang/' );

		}

		/**
		 * Plugin Updates
		 * @since 1.0
		 * @version 1.0
		 */
		public function plugin_updates() {

			add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_plugin_update' ), 390 );
			add_filter( 'plugins_api',                           array( $this, 'plugin_api_call' ), 390, 3 );
			add_filter( 'plugin_row_meta',                       array( $this, 'plugin_view_info' ), 390, 3 );

		}

		/**
		 * Register Hook
		 * @since 1.0
		 * @version 1.0
		 */
		public function register_hook( $installed ) {

			$installed['birthday'] = array(
				'title'         => __( '%plural% for Birthdays', 'mycred_bday' ),
				'description'   => __( 'Reward users with points on their birthday', 'mycred_bday' ),
				'documentation' => '',
				'callback'      => array( 'myCRED_Birthday_Hook' )
			);

			return $installed;

		}

		/**
		 * Add Badge Support
		 * @since 1.0
		 * @version 1.0
		 */
		public function add_badge_support( $references ) {

			$references['birthday'] = __( 'Birthday', 'mycred_bday' );

			return $references;

		}

		/**
		 * Plugin Update Check
		 * @since 1.0
		 * @version 1.0
		 */
		public function check_for_plugin_update( $checked_data ) {

			global $wp_version;

			if ( empty( $checked_data->checked ) )
				return $checked_data;

			$args = array(
				'slug'    => $this->slug,
				'version' => $this->version,
				'site'    => site_url()
			);
			$request_string = array(
				'body'       => array(
					'action'     => 'version', 
					'request'    => serialize( $args ),
					'api-key'    => md5( get_bloginfo( 'url' ) )
				),
				'user-agent' => 'WordPress/' . $wp_version . '; ' . get_bloginfo( 'url' )
			);

			// Start checking for an update
			$response = wp_remote_post( $this->update_url, $request_string );

			if ( ! is_wp_error( $response ) ) {

				$result = maybe_unserialize( $response['body'] );

				if ( is_object( $result ) && ! empty( $result ) )
					$checked_data->response[ $this->slug . '/' . $this->slug . '.php' ] = $result;

			}

			return $checked_data;

		}

		/**
		 * Plugin View Info
		 * @since 1.0
		 * @version 1.0
		 */
		public function plugin_view_info( $plugin_meta, $file, $plugin_data ) {

			if ( $file != $this->plugin ) return $plugin_meta;

			$plugin_meta[] = sprintf( '<a href="%s" class="thickbox" aria-label="%s" data-title="%s">%s</a>',
				esc_url( network_admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . $this->slug .
				'&TB_iframe=true&width=600&height=550' ) ),
				esc_attr( __( 'More information about this plugin', 'mycred_bday' ) ),
				esc_attr( $this->plugin_name ),
				__( 'View details', 'mycred_bday' )
			);

			return $plugin_meta;

		}

		/**
		 * Plugin New Version Update
		 * @since 1.0
		 * @version 1.0
		 */
		public function plugin_api_call( $result, $action, $args ) {

			global $wp_version;

			if ( ! isset( $args->slug ) || ( $args->slug != $this->slug ) )
				return $result;

			// Get the current version
			$args = array(
				'slug'    => $this->slug,
				'version' => $this->version,
				'site'    => site_url()
			);
			$request_string = array(
				'body'       => array(
					'action'     => 'info', 
					'request'    => serialize( $args ),
					'api-key'    => md5( get_bloginfo( 'url' ) )
				),
				'user-agent' => 'WordPress/' . $wp_version . '; ' . get_bloginfo( 'url' )
			);

			$request = wp_remote_post( $this->update_url, $request_string );

			if ( ! is_wp_error( $request ) )
				$result = maybe_unserialize( $request['body'] );

			return $result;

		}

	}
endif;

function mycred_birthdays_plugin() {
	return myCRED_Birthdays::instance();
}
mycred_birthdays_plugin();

/**
 * Load Birthday Hook
 * @since 1.0
 * @version 1.0
 */
if ( ! function_exists( 'mycred_birthdays_load_hook' ) ) :
	function mycred_birthdays_load_hook() {

		class myCRED_Birthday_Hook extends myCRED_Hook {

			protected $check_id  = NULL;
			protected $now       = 0;
			protected $this_year = 0;

			/**
			 * Construct
			 */
			function __construct( $hook_prefs, $type = MYCRED_DEFAULT_TYPE_KEY ) {

				parent::__construct( array(
					'id'       => 'birthday',
					'defaults' => array(
						'use'      => '',
						'field_id' => '',
						'creds'    => 1,
						'log'      => 'Birthday %plural%'
					)
				), $hook_prefs, $type );

				$this->check_id  = 'mycred-birthday-hook-run-' . $type;
				$this->now       = current_time( 'timestamp' );
				$this->this_year = date( 'Y', $this->now );

			}

			/**
			 * Run
			 * Runs if the hook is enabled.
			 * @since 1.0
			 * @version 1.0
			 */
			public function run() {

				if ( $this->prefs['use'] == 'buddypress' )
					add_action( 'bp_init', array( $this, 'check_today' ) );

				elseif ( $this->prefs['use'] == 'wordpress' )
					$this->check_today();

			}

			/**
			 * Daily Check
			 * @since 1.0
			 * @version 1.0
			 */
			public function check_today() {

				$today    = date( 'Ymd', $this->now );
				$last_run = get_option( $this->check_id, false );
				if ( $last_run === false || $last_run != $today ) {

					update_option( $this->check_id, $today );

					$this->birthday_check();

				}

			}

			/**
			 * Get BuddyPress Birthdays
			 * @since 1.0
			 * @version 1.0
			 */
			public function get_buddypress_birthdays() {

				global $wpdb, $bp;

				return $wpdb->get_col( $wpdb->prepare( "
					SELECT bpdata.user_id 
					FROM {$bp->profile->table_name_data} bpdata 
					LEFT JOIN {$bp->profile->table_name_fields} bpfield 
						ON ( bpfield.id = bpdata.field_id ) 
					WHERE bpfield.name = %s 
					AND bpdata.value LIKE %s;", $this->prefs['field_id'], $this->get_todays_like_date() ) );

			}

			/**
			 * Get WordPress Birthdays
			 * @since 1.0
			 * @version 1.0
			 */
			public function get_wordpress_birthdays() {

				global $wpdb;

				return $wpdb->get_col( $wpdb->prepare( "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value LIKE %s;", $this->prefs['field_id'], $this->get_todays_like_date() ) );

			}

			/**
			 * Prep Format for LIKE
			 * Supported formats:
			 * - Y-m-d
			 * - m/d/Y
			 * - d/m/Y
			 * @since 1.0
			 * @version 1.0
			 */
			public function get_todays_like_date() {

				return apply_filters( 'mycred-format-dob-field', date( '%-m-d%', $this->now ), $this );

			}

			/**
			 * Every day, check if anyone has a birthday today.
			 * Users can only get points if they are not excluded and if
			 * they have not recevied points already this year.
			 * @since 1.0
			 * @version 1.0
			 */
			public function birthday_check() {

				if ( $this->prefs['use'] == 'buddypress' )
					$birthdays = $this->get_buddypress_birthdays();
				else
					$birthdays = $this->get_wordpress_birthdays();

				if ( ! empty( $birthdays ) ) {
					foreach ( $birthdays as $user_id ) {

						// Excluded
						if ( $this->core->exclude_user( $user_id ) ) continue;

						// Make sure we only get points once a year if users can change their date of birth
						if ( ! $this->has_entry( 'birthday', $this->this_year, $user_id ) )
							$this->core->add_creds(
								'birthday',
								$user_id,
								$this->prefs['creds'],
								$this->prefs['log'],
								$this->this_year,
								'',
								$this->mycred_type
							);

					}
				}

			}

			/**
			 * Hook Settings
			 * @since 1.0
			 * @version 1.1
			 */
			public function preferences() {

				$prefs = $this->prefs;

?>
<div class="hook-instance">
	<div class="row">
		<div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( 'creds' ); ?>"><?php echo $this->core->plural(); ?></label>
				<input type="text" name="<?php echo $this->field_name( 'creds' ); ?>" id="<?php echo $this->field_id( 'creds' ); ?>" value="<?php echo $this->core->number( $prefs['creds'] ); ?>" class="form-control" />
			</div>
		</div>
		<div class="col-lg-8 col-md-8 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( 'log' ); ?>"><?php _e( 'Log Template', 'mycred' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( 'log' ); ?>" id="<?php echo $this->field_id( 'log' ); ?>" placeholder="<?php _e( 'required', 'mycred' ); ?>" value="<?php echo esc_attr( $prefs['log'] ); ?>" class="form-control" />
				<span class="description"><?php echo $this->available_template_tags( array( 'general' ) ); ?></span>
			</div>
		</div>
	</div>
</div>
<div class="hook-instance">
	<h3><?php _e( 'Date of Birth Location', 'mycred_bday' ); ?></h3>
	<div class="row">
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<div class="checkbox">
					<label for="<?php echo $this->field_id( 'use' ); ?>bp"><input type="radio" name="<?php echo $this->field_name( 'use' ); ?>"<?php checked( $prefs['use'], 'buddypress' ); ?> id="<?php echo $this->field_id( 'use' ); ?>bp" value="buddypress" /> <?php _e( 'BuddyPress Profile Field', 'mycred_bday' ); ?></label>
				</div>
				<div class="checkbox">
					<label for="<?php echo $this->field_id( 'use' ); ?>wp"><input type="radio" name="<?php echo $this->field_name( 'use' ); ?>"<?php checked( $prefs['use'], 'wordpress' ); ?> id="<?php echo $this->field_id( 'use' ); ?>wp" value="wordpress" /> <?php _e( 'Custom WordPress User Meta', 'mycred_bday' ); ?></label>
				</div>
			</div>
		</div>
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
			<div class="form-group">
				<label for="<?php echo $this->field_id( 'field_id' ); ?>"><?php _e( 'Field Name / ID', 'mycred_bday' ); ?></label>
				<input type="text" name="<?php echo $this->field_name( 'field_id' ); ?>" id="<?php echo $this->field_id( 'field_id' ); ?>" value="<?php echo esc_attr( $prefs['field_id'] ); ?>" class="form-control" />
				<span class="description"><?php _e( 'The BuddyPress field name or the custom user meta key, that identifies where the users date of birth is stored. Must be exact!', 'mycred_bday' ); ?></span>
			</div>
		</div>
	</div>
</div>
<?php

			}

		}

	}
endif;
