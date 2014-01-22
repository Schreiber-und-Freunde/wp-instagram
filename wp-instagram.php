<?
/*
Plugin Name: WP instagram
Description: instagram API Integration for Wordpress
Version: 0.1
Author: Schreiber & Freunde GmbH
Author URI: http://www.schreiber-freunde.de
*/

class SfWpInstagram
{
	// singleton instance
	private static $instance;

	private $url;
	private $url_oauth;
	private $client_id;
	private $client_secret;
	private $oauth_redirect_url;
	private $access_token;
	private $result;
	private $is_ready = true;

	public static function instance() {
		if ( isset( self::$instance ) )
			return self::$instance;

		self::$instance = new SfWpInstagram;
		return self::$instance;
	}

	function __construct() {
		add_action( 'init', array(&$this, 'init'));
		add_action( 'admin_menu', array( &$this, 'add_pages' ), 30 );		
	}

	function init() {

		if( isset($_REQUEST['sfwp_instagram_action']) ) {
			if( $_REQUEST['sfwp_instagram_action'] == 'save_options' ) {
				$this->save_options();
			}
		}

		$this->oauth_redirect_url = get_bloginfo('url') . '/wp-admin/options-general.php?page=sfwp_instagram_options&sfwp_instagram_action=oauth';

		$client_id = get_option('instagram_client_id');
		$client_secret = get_option('instagram_client_secret');
		
		if( $client_id === false || $client_secret === false ) {
			$this->is_ready = false;
			add_action('admin_notices', array( &$this, 'admin_notice_missing_account_data'));
			return;
		}

		$this->client_id = $client_id;
		$this->client_secret = $client_secret;

		$access_token = get_option('instagram_access_token');

		if( $access_token === false ) {
			$this->is_ready = false;
			add_action('admin_notices', array( &$this, 'admin_notice_missing_access_token'));
			return;
		}
		$this->access_token = $access_token;
		$this->url = 'https://api.instagram.com/v1/';
		$this->url_oauth = 'https://api.instagram.com/oauth/';
		

		if( isset($_REQUEST['sfwp_instagram_action']) ) {
			if( $_REQUEST['sfwp_instagram_action'] == 'test' ) {
				$this->test();
			}

			if( $_REQUEST['sfwp_instagram_action'] == 'oauth' ) {
				$this->oauth();
			}
		}
	}

	function admin_notice_missing_account_data() {
		echo '<div class="error"><p>' . __('WP instagram: Please go to the options page and fill in your account details.', 'sf_wp_instagram') . '</p></div>';
	}

	function admin_notice_missing_access_token() {
		echo '<div class="error"><p>' . __('WP instagram: Please go to the options page and authenticate with instagram.', 'sf_wp_instagram') . '</p></div>';
	}

	function add_pages() {
		add_options_page( 'instagram', 'instagram', 'manage_options', 'sfwp_instagram_options', array( &$this, 'page_options'));
	}

	function save_options() {
		if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'] , 'sfwp_instagram_action_save_options' ) ) {
			wp_die( __('Nonce check failed', 'sf_wp_instagram') );
			return;
		}

		if( isset($_REQUEST['instagram_client_id']) ) {
			update_option('instagram_client_id', trim($_REQUEST['instagram_client_id']) );
		}

		if( isset($_REQUEST['instagram_client_secret']) ) {
			update_option('instagram_client_secret', trim($_REQUEST['instagram_client_secret']) );
		}
	}

	function page_options() {
		?>
		<div class="wrap">
			<h2><? _e('Settings', 'sf_wp_instagram'); ?> › <? _e('mite.', 'sf_wp_instagram') ?></h2>
			<form action="<?php echo $_SERVER['REQUEST_URI']; ?>" method="post">
				<input type="hidden" name="sfwp_instagram_action" value="save_options" />
				<input type="hidden" name="_wpnonce" value="<? echo wp_create_nonce( 'sfwp_instagram_action_save_options' ) ?>" />
				<table class="form-table">
					<tr>
						<th><label for="instagram_client_id"><? _e('API Client Id', 'sf_wp_instagram') ?></label></th>
						<td><input name="instagram_client_id" id="instagram_client_id" type="text" value="<? echo get_option('instagram_client_id') ?>" /></td>
					</tr>
					<tr>
						<th><label for="instagram_client_secret"><? _e('API Client Secret', 'sf_wp_instagram') ?></label></th>
						<td><input name="instagram_client_secret" id="instagram_client_secret" type="text" value="<? echo get_option('instagram_client_secret') ?>" /></td>
					</tr>
				</table>
				<p class="submit"><input type="submit" value="<? _e('Save Settings', 'sf_wp_instagram') ?>" class="button-primary" /></p>
			</form>
			<?
			$client_id = get_option('instagram_client_id');
			$client_secret = get_option('instagram_client_secret');
			if( $client_id !== false && $client_secret !== false ) : ?>
			<h3><? _e('Authenticate with instagram', 'sf_wp_instagram') ?></h3>
			<a href="<? echo $this->url_oauth ?>authorize/?client_id=<? echo $client_id ?>&redirect_uri=<? echo $htis->oauth_redirect_url ?>&response_type=code" class="button-primary"><? _e('Authenticate', 'sf_wp_instagram') ?></a>
			<? endif; ?>
			<? if(false) : ?>
				<h3><? _e('Test', 'sf_wp_instagram') ?></h3>
				<form action="<?php echo $_SERVER['REQUEST_URI']; ?>" method="post">
					<input type="hidden" name="sfwp_instagram_action" value="test" />
					<input type="hidden" name="_wpnonce" value="<? echo wp_create_nonce( 'sfwp_instagram_action_test' ) ?>" />
					<p class="submit"><input type="submit" value="<? _e('Test Settings', 'sf_wp_instagram') ?>" class="button-primary" /></p>
				</form>
				<? if( isset($this->result) ) : ?>
				<h3><? _e('Test Result', 'sf_wp_instagram') ?></h3>
				<? echo '<pre>' . print_r( $this->result, true) . '</pre>'; ?>
				<? endif; ?>
			<? endif; ?>
		</div>
		<?
	}

	private function test() {
		if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'] , 'sfwp_instagram_action_test' ) ) {
			wp_die( __('Nonce check failed', 'sf_wp_instagram') );
			return;
		}

		if( !$this->is_ready ) {
			return false;
		}

		$this->result = $this->do_request('time_entries.json?group_by=year');
	}

	private function oauth() {
		echo '<pre>oauth()</pre>';
		if( !isset($_REQUEST['error']) ) {
			
			if( isset($_REQUEST['code']) ) {
				echo '<pre>oauth() has code</pre>';
				$code = $_REQUEST['code'];

				$result = json_decode( $this->do_request( 'access_token', array( 'client_id' => $this->client_id, 'client_secret' => $this->client_secret, 'grant_type' => 'authorization_code', 'redirect_uri' => $this->oauth_redirect_url, 'code' => $code ), $this->url_oauth ) );

				update_option('instagram_access_token', $result->access_token );
			}

		} else {
			echo '<pre>oauth() has error</pre>';
		}
	}

	public function do_request( $endpoint, $data, $url = null ) {
		if( !$this->is_ready ) {
			return false;
		}

		if( $url === null ) {
			$url = $this->url;
		}
		
		$curl = curl_init();

		curl_setopt($curl, CURLOPT_POST, 1);

		if ($data) {
			curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
		}
		
		curl_setopt( $curl, CURLOPT_URL, $url . $method );
		curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );

		return curl_exec($curl);
	}
}
$sf_wp_instagram = SfWpInstagram::instance();
function mite_get_time_entries_by_year() {
	return json_decode( SfWpInstagram::instance()->do_request( 'time_entries.json?group_by=year' ) );
}
?>