<?php
/*
  Plugin Name: User Account Auth
  Description: Login with facebook or original information using AJAX.
  Author: Lauren
  Author URI: https://github.com/mlauren
 */
 
add_action( 'plugins_loaded', array( 'UserAccoutHooks', 'get_instance' ) );

class UserAccoutHooks {

	public static $instance = null;
	
	public $nonce = '';
	
	// name of scripts
	public $name = 'useraccountajax';
	public $loggedin = 'loggedin';
	public $namefblogin = 'loginFacebook';
	private $fbKey = '';

  /**
   * Construct class
   */
  function __construct() {
		// Registration process form		
		add_action("wp_ajax_{$this->name}", array( $this, 'ajaxCb') );
		add_action( "wp_ajax_nopriv_{$this->name}", array( $this, 'ajaxCb' ) );
		
		// response_account_user_reset
		add_action( "wp_ajax_nopriv_response_account_user_reset", array( $this, 'ajaxSendPswdReset' ) );

		// ajax check logged in
		add_action('wp_ajax_is_user_logged_in', array( $this, 'ajax_check_user_logged_in'));
		add_action('wp_ajax_nopriv_is_user_logged_in', array( $this, 'ajax_check_user_logged_in'));
		
		// ajax login ajaxLogin
		add_action('wp_ajax_user_login', array( $this, 'ajaxLogin'));
		add_action('wp_ajax_nopriv_user_login', array( $this, 'ajaxLogin'));

		//facebook_login
		add_action('wp_ajax_action_fblogin', array( $this, 'facebookLoginRegister') );
		add_action('wp_ajax_nopriv_action_fblogin', array( $this, 'facebookLoginRegister') );

		//ajax validate password
		add_action('wp_ajax_password_val', array( $this, 'ajaxPswdReset'));
		add_action('wp_ajax_nopriv_password_val', array( $this, 'ajaxPswdReset'));

		// Could as well be: wp_enqueue_scripts or login_enqueue_scripts
		add_action( 'wp_enqueue_scripts', array( $this, 'scriptsRegister' ) );
		// add_action( 'login_enqueue_scripts', array( $this, 'scriptsEnqueue' ) );
		// add php variables to script
		add_action( 'wp_enqueue_scripts', array( $this, 'scriptsLocalize' ) );
		// Display the change email form
		add_action( 'init', array($this, 'addShortcode'));

		// Random stuff

		add_action('set_current_user', array($this, 'uaa_hide_admin_bar'));

		add_action( 'admin_menu', array($this, 'uaa_add_admin_menu') );
		add_action( 'admin_init', array($this, 'uaa_settings_init') );

		add_action( 'user_profile_update_errors', array($this, 'remove_empty_email_error') );

	}

  /**
   * Create self initializing classes through wordpress
   *
   * @return null|UserAccoutHooks
   */
  public static function get_instance()
  {
    // create an object
    NULL === self::$instance and self::$instance = new self;
    return self::$instance; // return the object
  }

  /**
   * Hide the Admin Bar
   */
  function uaa_hide_admin_bar() {
		if (!current_user_can('edit_posts')) {
			show_admin_bar(false);
		}
	}

  /**
   * Add Shortcode
   */
  public function addShortcode() {
		add_shortcode('password_form', array($this, 'changePasswordForm'));
	}


	/**
	 * Add admin menu
	 */
	function uaa_add_admin_menu(  ) {

		add_options_page( 'User Account Auth', 'User Account Auth', 'manage_options', 'user_account_auth', array($this, 'user_account_auth_options_page') );

	}

	/**
	 * Add plugin settings Fields for Facebook Token
	 */
	function uaa_settings_init(  ) {

		register_setting( 'pluginPage', 'uaa_settings' );

		add_settings_section(
			'uaa_pluginPage_section',
			__( 'Facebook App ID', 'wordpress' ),
			array($this, 'uaa_settings_section_callback'),
			'pluginPage'
		);

		add_settings_field(
			'uaa_text_field_0',
			__( 'App ID', 'wordpress' ),
			array($this, 'uaa_text_field_0_render'),
			'pluginPage',
			'uaa_pluginPage_section'
		);
	}

	/**
	 * Render text fields for Facebook Token Customization
	 */
	function uaa_text_field_0_render(  ) {

		$options = get_option( 'uaa_settings' );
		?>
		<input type='text' name='uaa_settings[uaa_text_field_0]' value='<?php echo $options['uaa_text_field_0']; ?>'>
		<?php

	}

	/**
	 * More callback for Facebook Token Customization Settings Page
	 */
	function uaa_settings_section_callback(  ) {

		echo __( 'Copy and paste your Facebook App ID from the facebook developers site. This will enable facebook Login.', 'wordpress' );

	}

	/**
	 * Facebook Token Customization Settings Page layout.
	 */
	function user_account_auth_options_page(  ) {


		?>
		<form action='options.php' method='post'>

			<h2>User Account Auth</h2>

			<?php
			settings_fields( 'pluginPage' );
			do_settings_sections( 'pluginPage' );
			submit_button();
			?>

		</form>
		<?php

	}

	/**
   * Register Scripts
   *
	 * @param $page
	 */
	public function scriptsRegister( $page )
	{
		$file = 'user-account-auth.js';
		wp_enqueue_script(
			$this->name,
			plugins_url( $file, __FILE__ ),
			array('jquery'),
			'1.0',
			true
		);
		$fbFile = 'fb-login.js';
		wp_enqueue_script(
			$this->namefblogin,
			plugins_url( $fbFile, __FILE__ ),
			array('jquery'),
			'1.0',
			true
		);
	}

  /**
   * Enqueue the scripts
   *
   * @param $page
   */
  public function scriptsEnqueue( $page )
	{
		add_action('wp_enqueue_scripts', array($this, 'scriptsRegister'));
	}

  /**
   * Localize plugin variables to script
   *
   * @param $page
   */
  public function scriptsLocalize( $page )
	{
		// Todo turn this into shortcode so I can print it all over the place
		$logged_in_content = '<a href="'.wp_logout_url('/').'">'.__('Logout').'</a>';
		$logged_in_content .= '<a href="">'.__('My Account').'</a>';
		
		$this->nonce = wp_create_nonce( "{$this->name}_nonce" );
		
		wp_localize_script( $this->name, "{$this->name}Object", array(
			'url' => admin_url( 'admin-ajax.php' ),
			'nonce' => $this->nonce,
			'action' => "{$this->name}",
			'action_loggedin' => 'is_user_logged_in',
			'action_send_password_reset' => "response_account_user_reset",
			'action_check_validate_pswd' => "password_val",
			'action_user_login' => 'user_login',
			'logged_in_content' => $logged_in_content
		) );

		$fb_token = get_option( 'uaa_settings' );
		$this->fbnonce = wp_create_nonce( "{$this->namefblogin}_nonce" );

		wp_localize_script( $this->namefblogin, "{$this->namefblogin}Object", array(
			'url' => admin_url( 'admin-ajax.php' ),
			'nonce' => $this->fbnonce,
			'fbKey' => $fb_token['uaa_text_field_0'],
			'action_fblogin' => 'action_fblogin'
		) );
	}
	
  /**
   * AJAX function to check if user is logged in
   *
   * @return bool
   */
  function ajax_check_user_logged_in() {
    echo is_user_logged_in() ? 1 : 0;
    die();
	}

	function remove_empty_email_error( $arg ) {
		if ( !empty( $arg->errors['empty_email'] ) ) unset( $arg->errors['empty_email'] );
	}

  /**
   * Login / Register through facebook
   *
   * @param $data
   * @return wp_send_json
   */
  public function facebookLoginRegister( $data ) {

    $currUser = wp_get_current_user();
    if( $currUser->ID > 0 )
    {
      wp_send_json_error('Looks like you are already logged in another browser session.');
    }
		$loginData = $_POST['response'];

    // @todo apply filters so this aciton can be hooked into
    if ( $loginData['verified'] == true) {

  		if ( isset($loginData['email']) && $loginData['email'] !== '' ) {
  			$email = $loginData['email'];


        $userlLogin = htmlspecialchars( $loginData['first_name'] . $loginData['last_name'] );
        $displayName = $loginData['name'];
        $userUrl = $loginData['link'];

        // get user by
        // question-- how does one authenticate and save user data when a user might have the same login as another
        // Create user if there is none
        if ( null == email_exists( $email ) && null == username_exists($userlLogin) ) {
           // login and return
          $password = wp_generate_password( $length = 12, $include_standard_special_chars = false );
          // only create the user in if there are no errors
          $user_id = wp_insert_user(array(
              'user_login' => $email,
              'user_pass' => $password, // wp_generate_password hashes the password for me
              'user_email' => $email,
              'user_registered' => date('Y-m-d H:i:s'),
              'role' => 'subscriber'
            )
          );

          if ( !is_wp_error( $user_id ) && $user_id !== 0 ) {
            $user = get_user_by('id', $user_id);

            // Create unique Identifiers for facebook
            $fbID = add_user_meta( $user_id, 'user_account_facebook_id', $loginData['id'] );
            $fbURL = add_user_meta( $user_id, 'user_account_facebook_url', $loginData['link'] );
            if ( $fbID && $fbURL ) {
              // Send Notification email
              $url = 'https://forms.hubspot.com/uploads/form/v2/97965/3367105d-8f86-4cc6-8bf0-dd5f307685cb';
              $response = wp_remote_post( $url, array(
                'method' => 'POST',
                'timeout' => 45,
                'redirection' => 5,
                'httpversion' => '1.0',
                'blocking' => false,
                'headers' => array(),
                'body' => array("email" => $user->user_email, "firstname" => $user->user_login, "company" => "Vintage Oaks Realty New Signup", "facebookregistration" => "with Facebook"),
                'cookies' => array()
                )
              );

              // Set current user
              wp_set_current_user($user_id, $email,$user);
              wp_set_auth_cookie($user_id);
              do_action('wp_login', $email, $user);
              wp_send_json_success(array('message' => 'User created.'));
            }
          }
        }
        else {
          $user = get_user_by_email($email);
          if (is_wp_error($user) || $user->ID === 0 || $user === false) {
            $user = get_user_by('login', $userlLogin);
          }


          if (!is_wp_error($user) && $user->ID !== 0 && $user !== false) {
            // find the user and log them in
            // check to make sure user has facebook meta login that user
            $meta = get_user_meta($user->ID, 'user_account_facebook_id');
            if ( $meta ) {
                // Set current user
                $t = time();
                update_user_meta( $user->ID, 'logged_in_with_facebook', "Yes");
                update_user_meta( $user->ID,'last_login', date("Y-m-d",$t));
                $url = 'https://forms.hubspot.com/uploads/form/v2/97965/e599e1d1-cce2-4cc3-bd5e-626c71ec0552';
                $response = wp_remote_post( $url, array(
                  'method' => 'POST',
                  'timeout' => 45,
                  'redirection' => 5,
                  'httpversion' => '1.0',
                  'blocking' => false,
                  'headers' => array(),
                  'body' => array("email" => $user->user_email, "firstname" => $user->user_login, "company" => "Vintage Oaks Realty Signin", "facebook" => "with Facebook"),
                  'cookies' => array()
                      )
                );
                wp_set_current_user($user->ID, $user->user_login);
                wp_set_auth_cookie($user->ID);
                do_action('wp_login', $email, $user);
                wp_send_json_success(array('message' => 'User has been logged in.'));
            }
            else {
              $fbID = add_user_meta( $user->ID, 'user_account_facebook_id', $loginData['id'] );
              $fbURL = add_user_meta( $user->ID, 'user_account_facebook_url', $loginData['link'] );
              if ( $fbID && $fbURL ) {
                wp_set_current_user($user->ID, $user->user_login);
                wp_set_auth_cookie($user->ID);
                do_action('wp_login', $email, $user);
                wp_send_json_success(array('message' => 'User has been created with Facebook and logged in.'));
              }
              else {
                wp_send_json_error(array('message' => 'Something went wrong!', 'user' => $user ));
              }
            }
          }
          else {
            wp_send_json_error(array('message' => 'Failed to retrieve user!'));
          }
        }
      }
      else {
        wp_send_json_error(array(
          'message' => 'An email address is not associated with your Facebook account!'
        ));
      }
    }
    else {
      wp_send_json_error( array(
        'message' => 'Sorry, It looks like your email address is not verified through facebook.'
      ));
    }
		die();
	}

  /**
   * Register userdata
   *
   * @param $data
   */
  public function ajaxCb( $data )
	{
		// TODO validate nonce
		$nonce = wp_verify_nonce($_POST['security'], "{$this->name}_nonce");
		if ($nonce != true) {
			wp_send_json_error(array('loggedin'=>false, 'message'=>__('Sorry, something went wrong, maybe try reloading the page?') ));
		}
		$formdata = $_POST['formdata'];
		$username = $formdata['account_user_email'];
		$email = $formdata['account_user_email'];
		$password = $formdata['account_user_password'];
		$password_again = $formdata['account_user_password'];
		// if any of the fields are empty return an error
		$empty = array();
		foreach ( $formdata as $key => $element ) {
			if (empty($element)) {
				$empty[] = $key;
			}
		}
		if ( !empty($empty) ) {
			wp_send_json( array(
				'response' => 'error',
				'error_code' => 'empty',
				'field' => $empty,
				'message' => __('Please do not leave this field Blank.')
			));
		}
		// Check to see if email is valid (if they are not using chrome)
		if(username_exists($username)) {
			wp_send_json( array(
				'response' => 'error',
				'error_code' => 'username_unavailable',
				'field' => 'account_user_email',
				'message' => __('This username/email is already taken!')
			));
		}
		if(!validate_username($username)) {
			wp_send_json( array(
				'response' => 'error',
				'error_code' => 'username_invalid',
				'field' => 'account_user_email',
				'message' => __('Invalid username')
			));
		}
		if ( !is_email($email)) {
			wp_send_json( array(
				'response' => 'error',
				'error_code' => 'invalid_email',
				'field' => 'account_user_email',
				'message' => __('Please make sure that you use a valid email address.')
			));
		}
		if(email_exists($email)) {
			wp_send_json( array(
				'response' => 'error',
				'error_code' => 'email_unavailable',
				'field' => 'account_user_email',
				'message' => __('Email already registered.')
			));
		}
		if ( $password != $password_again ) {
			wp_send_json( array(
				'response' => 'error',
				'error_code' => 'password_mismatch',
				'field' => 'account_user_password_again',
				'message' => __('Passwords do not match.')
			));	
		}
		
		// only create the user in if there are no errors
		$new_user_id = wp_insert_user(array(
				'user_login'		=> $username,
				'user_pass'	 		=> $password, // This function hashes the password for me
				'user_email'		=> $email,
				'user_registered'	=> date('Y-m-d H:i:s'),
				'role'				=> 'subscriber'
			)
		);
		if($new_user_id) {
			// -- May 1 This works -- //
			// log the new user in
			$user = wp_signon( array('user_login' => $username, 'user_password' => $formdata['account_user_password'], 'remember' => false), false );
			
			if (is_wp_error($user)) {
				wp_send_json( array(
					'response' => 'error',
					'field' => false,
					'error_code' => $user->get_error_messages(),
					'message' => __('something went wrong')
				));	
			}
			// send the newly created user to the home page after logging them in
			wp_send_json( array(
				'response' => 'success',
				'user_email' => $email,
				'message' => '<div align="center"><p>Congrats!<br> Your account has been created.</p> <div id="form-account-lead"></div><h4>OR</h4> <a href="/profile/" class="button btn">Continue to Account</a></div>',
			));	
		}
		
		die();
	}

  /**
   * Form for User to Reset his or her password and get an email
   *
   * @param $data
   */
  public function ajaxSendPswdReset( $data ) {
		$nonce = wp_verify_nonce($_POST['nonce'], "{$this->name}_nonce");
		if ($nonce != true) {
			wp_send_json_error(array('loggedin'=>false, 'message'=>__('Sorry, something went wrong, maybe try reloading the page?') ));
		}
		$getformdata = $_POST['formdata'];

		$empty = array();
		$formdata = array();
		foreach($getformdata as $key => $item) {
			$formdata[$getformdata[$key]['name']] = $getformdata[$key]['value'];
			if (empty($getformdata[$key]['value'])) {
				$empty[] = $getformdata[$key];
			}
		}
		// Set the email validation
		$email = $formdata['account_user_email_reset'];
		
		// validate user email
		if ( !empty($empty) ) {
			wp_send_json_error( array(
				'response' => 'error',
				'error_code' => 'empty',
				'field' => $empty,
				'message' => __('Please do not leave this field Blank.')
			));
		}
		if ( !is_email($email)) {
			wp_send_json_error( array(
				'response' => 'error',
				'error_code' => 'invalid_email',
				'field' => 'account_user_email_reset',
				'message' => __('Please make sure that you use a valid email address.')
			));
		}
		if(!email_exists($email)) {
			wp_send_json_error( array(
				'error_code' => 'email_not_available',
				'field' => 'account_user_email_reset',
				'message' => __('Looks like you do not have an account with us. <a href="#popup-registration" class="popup-opener"><strong>Register for an account?</strong></a>')
			));
		}
		else {
			$user = get_user_by('email', $email);
			if ($user) {
        $code = sha1( $user->ID . time() );

        $activation_link = add_query_arg( array( 'user' => $user->ID, 'key' => $code ), get_permalink( 526 ));

        if ( get_user_meta( $user->ID, 'has_to_be_activated', true) ) {
					update_user_meta( $user->ID, 'has_to_be_activated', $code, false);
        }
        else {
					add_user_meta( $user->ID, 'has_to_be_activated', $code, true );
        }

      	$subject = 'Vintage Reality Password Reset';
				$headers = 'From: Vintage Reality' .  "\r\n";
				$message = 'Please visit this url in order to reset your password ' . $activation_link;

				$mail =  wp_mail( $user->user_email, $subject, $message, $headers );

				if (!$mail) {
					wp_send_json_error( array(
						'error_code' => 'email_not_available',
						'message' => __('We had some difficulties sending you your reset password. Please reload and try again.')
					));
				}
				else {
					wp_send_json_success( array(
						'message' => __('Check your email, we have sent you a link to change your password.')
					));
				}
			} 
			wp_send_json_error( array(
				'error_code' => 'something_bad',
				'message' => __("We couldn't find you in our records, please check your email address and try again.")
			));
		}
		die();
	}

  /**
   * Allow user to login
   *
   * @param $data
   */
  public function ajaxLogin($data) {
		$getformdata = $_POST['formdata'];

		$nonce = wp_verify_nonce($_POST['nonce'], "{$this->name}_nonce");
		if ($nonce != true) {
			wp_send_json(array('response' => 'error', 'loggedin'=>false, 'message'=>__('Sorry, something went wrong, maybe try reloading the page?') ));
		}
		$empty = array();
		$formdata = array();
		foreach($getformdata as $key => $item) {
			$formdata[$getformdata[$key]['name']] = $getformdata[$key]['value'];
			if (empty($getformdata[$key]['value'])) {
				$empty[] = $getformdata[$key];
			}
		}
		//wp_send_json($formdata);
		if ( is_email($formdata['log']) ) {
			$email_user = get_user_by('email', $formdata['log']);
			// wp_send_json($email_user);
			if ( $email_user ) {
				$user_logon = $email_user->user_login;
			}
			else {
				// this is really stupid
				$user_logon = $formdata['log'];	
			}
		}
		else {
			$user_logon = $formdata['log'];
		}
		
		$user_signon = wp_signon( array('user_login' => $user_logon, 'user_password' => $formdata['pwd'], 'remember' => false), false );
		if ( is_wp_error($user_signon) ){
			wp_send_json(array('response' => 'error', 'loggedin'=>false, 'message'=>__('Incorrect Login or Password.') ));
		} else {
			wp_send_json(array('response' => 'success', 'loggedin'=>true, 'message'=>__('Login successful, redirecting...')));
		}
		die();
	}

  /**
   * Form to allow user to reset password
   *
   * @param $post
   */
  public function ajaxPswdReset($data) {
		$nonce = wp_verify_nonce($_POST['nonce'], "{$this->name}_nonce");
		if ($nonce != true) {
			wp_send_json_error(array('loggedin'=>false, 'message'=>__('Sorry, something went wrong, maybe try reloading the page?') ));
		}
		$getformdata = $_POST['formdata'];
		global $wpdb;
		$empty = array();
		$formdata = array();
		foreach($getformdata as $key => $item) {
			$formdata[$getformdata[$key]['name']] = $getformdata[$key]['value'];
			if (empty($getformdata[$key]['value'])) {
				$empty[] = $getformdata[$key];
			}
		}
		if ( $formdata['account_user_pass_reset'] !=  $formdata['account_user_pass_reset_confirm'] ) {
			wp_send_json_error( array(
				'error_code' => 'password_mismatch',
				'field' => 'account_user_pass_reset_confirm',
				'message' => __('Passwords do not match.')
			));	
		}
		if ( !empty($empty) ) {
			wp_send_json_error( array(
				'error_code' => 'password_mismatch',
				'field' => 'account_user_pass_reset_confirm',
				'message' => __('Please do not leave any fields blank.')
			));
		}
		$user = get_user_by('id', (int)$formdata['uah_username']);

		if ($user && isset($formdata['uah_action'])) {

			// Update password the proper way.
			$new_pass = $formdata['account_user_pass_reset'];

			$allow = apply_filters('allow_password_reset', true, $user->ID);

			if ( ! $allow )
				wp_send_json_error( array(
					'message' => __('Something went wrong! Please try again.')
				));
			else if ( is_wp_error($allow) )
				wp_send_json_error( array(
					'message' => __('Something went wrong! Please try again.')
				));

			// reset password
			do_action( 'password_reset', $user, $new_pass );

			wp_set_password( $new_pass, $user->ID );
			// update_user_option( $user->ID, 'default_password_nag', true, true );
			// reset password

			if (get_user_meta( $user->ID, 'has_to_be_activated', true)) {
				$delete_user_meta = delete_user_meta($user->ID, 'has_to_be_activated');
			}

			wp_send_json_success( array(
				'message' => __('Your password has been updated. Please Login.')
			));
		} else {
			wp_send_json_error( array(
				'message' => __('Something went wrong! Please try again.')
			));
		}
		die();
	}

  /**
   * Change password in page
   *
   * @param $uid
   */
  function showChangePasswordForm($uid) {
		?>
		<h2>Change Your Password</h2>
    <div class="form-control form-container grid-col-4">
      <form id="change_password_form" method="POST" action="">
        <fieldset>
          <p>
            <label for="account_user_pass_reset"><?php echo __('New Password'); ?></label>
            <input name="account_user_pass_reset" id="account_user_pass_reset" class="required" type="password"/>
          </p>
          <p>
            <label for="account_user_pass_reset_confirm"><?php echo __('Password Confirm'); ?></label>
            <input name="account_user_pass_reset_confirm" id="account_user_pass_reset_confirm" class="required" type="password"/>
          </p>
          <p>
            <input type="hidden" name="uah_action" value="reset-password"/>
            <input type="hidden" name="uah_redirect" value="/"/>
            <input type="hidden" name="uah_username" value="<?php echo $uid; ?>" />
            <input type="hidden" name="uah_password_nonce" value="<?php echo wp_create_nonce('reset-password-nonce'); ?>"/>
            <input id="uah_password_submit" type="submit" value="<?php echo __('Change Password'); ?>"/>
          </p>
        </fieldset>
      </form>
    </div>
    
	<?php
	}

  /**
   * Shortcode to show change password form based on permissions / logged in
   *
   * @return string|void
   */
  public function changePasswordForm() {
		// Get the query in the url for the id and match it to the custom parameter
		$query_vars = $_GET;
		if (!is_user_logged_in()) {
			if (!empty($query_vars)) {
				// Lets process the user
				$user = get_user_by('id', $query_vars['user']);
				// check to make sure the query url in the query matches the user meta
				if (!is_wp_error($user)) {
					$activation =  get_user_meta($user->ID, 'has_to_be_activated');
					if ( null != $activation && $activation[0] == $query_vars['key'] ) {
						return $this->showChangePasswordForm($user->ID);
					}
				}
			}
			return 'you are not authorized to view this page.';
		}
		else {
			$user = wp_get_current_user();
			return $this->showChangePasswordForm($user->ID);
		}
	}
}
