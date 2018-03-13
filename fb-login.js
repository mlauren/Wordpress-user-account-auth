'use strict';

// Load the SDK asynchronously
(function(d, s, id) {
  var js, fjs = d.getElementsByTagName(s)[0];
  if (d.getElementById(id)) return;
  js = d.createElement(s); js.id = id;
  js.src = "//connect.facebook.net/en_US/sdk.js";
  fjs.parentNode.insertBefore(js, fjs);
}(document, 'script', 'facebook-jssdk'));

// Custom login-registration AJAX
( function( $, plugin ) {


  // This is called with the results from from FB.getLoginStatus().
  function statusChangeCallback(response, e) {
    // console.log('statusChangeCallback');
    // console.log(response);
    // The response object is returned with a status field that lets the
    // app know the current login status of the person.
    // Full docs on the response object can be found in the documentation
    // for FB.getLoginStatus().
    if (response.status === 'connected') {
      // Logged into your app and Facebook.
      testAPI(e);
    } else if (response.status === 'not_authorized') {
      // The person is logged into Facebook, but not your app.
      document.getElementById('status').innerHTML = 'Please log ' +
        'into this app.';
    } else {
      // The person is not logged into Facebook, so we're not sure if
      // they are logged into this app or not.
      document.getElementById('status').innerHTML = 'Please log ' +
        'into Facebook.';
    }
  }

  // This function is called when someone finishes with the Login
  // Button.  See the onlogin handler attached to it in the sample
  // code below.
  window.checkLoginState = function() {
    FB.getLoginStatus(function(response) {
      statusChangeCallback(response);
    });
  };

  function checkLoginState(e) {
    FB.getLoginStatus(function(response) {
      statusChangeCallback(response, e);
    });
  };

  window.fbAsyncInit = function() {
    FB.init({
      appId      : plugin.fbKey,
      cookie     : true,
      oauth      : true,
      status     : true,
      xfbml      : true,
      version    : 'v2.2'
    });
  };

  $(function () {
    $(".button-facebook").on("click", function (e) {
      var click = e;
      FB.login(function(response) {

        console.log(e);
        if (response.authResponse) {
          checkLoginState(e);
        }
      }, { scope: 'email,public_profile,user_friends', return_scopes: true });
    });
  });

  // Here we run a very simple test of the Graph API after login is
  // successful.  See statusChangeCallback() for when this call is made.
  function testAPI(e) {
    console.log('Welcome!  Fetching your information.... ');
    FB.api('/me', function(response) {
      
      var $status = $(e.target).parents('form').find('#status');
      $(e.target.parentElement).css('opacity', '.4');

      $status.empty().append('Logged in on facebook as ' + response.name);
      console.log('Logged in on facebook as ' + response.name);

      /* --------- Do Wordpress Stuff Here -------- */
      $.ajax({
        type : "POST",
        url  : plugin.url,
        dataType : "json",
        data : {
          action : plugin.action_fblogin,
          response: response
        }
      }).done( function(response) {
          // console.log(response);
          if ( response.data.message && response.success == true ) {
            $status.empty().append('Logging you in, redirecting...');

            setTimeout( function() {
              document.location.reload(true);
            }, 500 );
          }
          else {
            $status.empty().append('<p class="has-error">' + response.data.message + '</p>');
          }
      }).fail( function( jqXHR, textStatus, errorThrown ) {
        console.log( 'AJAX failed', jqXHR.getAllResponseHeaders(), textStatus, 'error here:', errorThrown );
      });
    });
  }

} )( jQuery, loginFacebookObject || {} );