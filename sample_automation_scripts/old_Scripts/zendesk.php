<?php // migrated and updated login code
// Server-Portal-ID: 448 - Last modified: 05.09.2024 14:41:46 UTC - User: 1

/*Define constants used in script*/
public $custom_url = '';
public $loginUrl = '';

public $check_login_failed_selector = 'div.auth0-global-message.auth0-global-message-error, div.signin div.flash-error';
public $check_login_success_selector = 'a[href*="/LogOut"], a[href*="/logout"], .lotus-profile-menu [data-garden-id="avatars.avatar"], div[data-test-id="avatar-menu"]';
public $isNoInvoice = true;
public $login_with_google = 0;
public $init_count = 0;

private function initPortal($count) {
	$this->init_count = $count;
	sleep(1);
	if(isset($this->exts->config_array["custom_url"]) && trim($this->exts->config_array["custom_url"]) != ''){
		$this->custom_url = trim($this->exts->config_array["custom_url"]);
	} else if(isset($this->exts->config_array["customUrl"]) && trim($this->exts->config_array["customUrl"]) != ''){
		$this->custom_url = trim($this->exts->config_array["customUrl"]);
	}


	$this->login_with_google = isset($this->exts->config_array["login_with_google"]) ? (int)@$this->exts->config_array["login_with_google"] : (isset($this->exts->config_array["LOGIN_WITH_GOOGLE"]) ? (int)@$this->exts->config_array["LOGIN_WITH_GOOGLE"] : $this->login_with_google);

	// if (true) added to ease when collapse the long code block below.
	if (true){
		if (strpos($this->custom_url, 'https://') === false && strpos($this->custom_url, 'http://') === false){
			$this->custom_url = 'https://'.$this->custom_url;
		}
		//added fix for malformed url - ticket : 703
		if (preg_match('/^https?\:\/\/([^\/?#]+)(?:[\/?#]|$)/i', $this->custom_url, $matches) == 1){
			$this->custom_url = $matches[0];
		}
		if (substr($this->custom_url, strlen($this->custom_url) - 1) == '/'){
			$this->custom_url = substr($this->custom_url, 0, strlen($this->custom_url) - 1);
		}
		if (strpos($this->custom_url, '.zendesk.com') === false 
			&& strpos($this->custom_url, 'wesharebonds.com') === false ){
			$this->custom_url = $this->custom_url.".zendesk.com";
		}

		if (strpos($this->custom_url, 'zendesk.com') !== false) {
			$sub_domain = explode('.zendesk.com', end(explode('://', $this->custom_url)))[0];
			if (strpos($sub_domain, '%') !== false) {
				$sub_domain = str_replace('%', '', $sub_domain);
				$this->custom_url = explode('://', $this->custom_url)[0] . '://' . $sub_domain . '.zendesk.com' . end(explode('zendesk.com', $this->custom_url)) ;
			}
		}

		$loginUrl = $this->custom_url.'/auth/v2/login/signin';
		$this->loginUrl = $loginUrl;

		//justselling special case
		if (strpos($this->custom_url, 'justselling.') !== false){
			$this->custom_url = "https://justselling.zendesk.com";
			$this->loginUrl = 'https://www.justselling.de/customer/account/login/';
		}
		//aide.wesharebonds.com special case
		if (strpos($this->custom_url, 'wesharebonds.com') !== false){
			$this->custom_url = "https://wesharebonds.zendesk.com";
			$this->loginUrl = 'https://wesharebonds.zendesk.com/auth/v2/login/normal';
		}
		//cloudzone special case
		if (strpos($this->custom_url, 'cloudzone.') !== false){
			$this->custom_url = "https://cloudzone.zendesk.com";
			$this->loginUrl = 'https://cloudzone.zendesk.com/auth/v2/login/normal';
		}
		//shotscope special case
		if (strpos($this->custom_url, 'shotscope.') !== false){
			$this->custom_url = "https://support.shotscope.com";
			$this->loginUrl = 'https://support.shotscope.com/hc/en-us/signin?return_to=https%3A%2F%2Fsupport.shotscope.com%2Fhc%2Fen-us&locale=en-us';
		}
		//amavat same as cloudzone
		if (strpos($this->custom_url, 'amavat.') !== false){
			$this->custom_url = "https://amavat.zendesk.com";
			$this->loginUrl = 'https://amavat.zendesk.com/access/normal';
		}
		//rakutenlinkshare same as cloudzone
		if (strpos($this->custom_url, 'rakutenlinkshare.') !== false){
			$this->custom_url = "https://rakutenlinkshare.zendesk.com";
			$this->loginUrl = 'https://rakutenlinkshare.zendesk.com/auth/v2/login/normal';
		}
		if (strpos($this->custom_url, 'edufolios.') !== false){
			$this->custom_url = "https://edufolios.zendesk.com";
			$this->loginUrl = 'https://edufolios.org/wp-login.php?redirect_to=https%3A%2F%2Fedufolios.org%2Fwp-signup.php';
		}
		if (strpos($this->custom_url, 'simplehq.') !== false){
			$this->custom_url = "https://simplemrm.zendesk.com";
			$this->loginUrl = 'https://simplemrm.zendesk.com/access/normal/';
		}
		if (strpos($this->custom_url, 'clubmanager.') !== false){
			$this->custom_url = "https://clubmanager.zendesk.com";
			$this->loginUrl = 'https://secure.clubmanagercentral.com/Login.mvc/Login?ReturnUrl=%2F';
		}
		if (strpos($this->custom_url, 'zero1.co.uk.') !== false){
			$this->custom_url = "https://support.zero1.co.uk";
			$this->loginUrl = 'https://support.zero1.co.uk/hc/en-us/signin?return_to=https%3A%2F%2Fsupport.zero1.co.uk%2Fhc%2Fen-us&locale=en-us';
		}
		if (strpos($this->custom_url, 'unlatch.zendesk') !== false){
			$this->custom_url = "https://unlatch.zendesk.com";
			$this->loginUrl = 'https://unlatch.zendesk.com/auth/';
		}
		if (strpos($this->custom_url, 'united-promotion.eu') !== false){
			$this->custom_url = "https://united-promotion.zendesk.com";
			$this->loginUrl = 'https://united-promotion.zendesk.com/agent';
		}
		if (strpos($this->custom_url, 'emma-chloe@zendesk.com') !== false){
			$this->custom_url = "https://emma-chloe.zendesk.com";
			$this->loginUrl = 'https://emma-chloe.zendesk.com/access/normal/';
		}
		if (strpos($this->custom_url, 'zopim.com') !== false){
			$this->custom_url = "https://account.zopim.com/account/login?redirect_to=%2faccount%2f";
			$this->loginUrl = 'https://account.zopim.com/account/login?redirect_to=%2faccount%2f';
		}
		if (strpos($this->custom_url, 'redrickshawhelp.zendesk.com') !== false) {
			$this->custom_url = "https://redrickshawhelp.zendesk.com/auth/v2/login/normal";
			$this->loginUrl = 'https://redrickshawhelp.zendesk.com/auth/v2/login/normal';
		}

		if (strpos($this->custom_url, 'fleetmon.') !== false) {
			$this->custom_url = $this->loginUrl = 'https://www.fleetmon.com/users/login/?next=/help-support/auth%3Fbrand_id%3D45781%26locale_id%3D1%26return_to%3Dhttps%253A%252F%252Ffleetmon.zendesk.com%26timestamp%3D1621417614';

		}
		if (strpos($this->custom_url, 'designforme.') !== false) {
			$this->custom_url = "https://designfor-me.com/login/?redirect_to=https%3A%2F%2Fdesignfor-me.com%2Flogin%2F%3Faction%3Dzendesk-remote-login%26timestamp%3D1624331726%26return_to%3Dhttps%253A%252F%252Fdesignforme.zendesk.com%252Fhc%252Fen-us";
			$this->loginUrl = 'https://designfor-me.com/login/?redirect_to=https%3A%2F%2Fdesignfor-me.com%2Flogin%2F%3Faction%3Dzendesk-remote-login%26timestamp%3D1624331726%26return_to%3Dhttps%253A%252F%252Fdesignforme.zendesk.com%252Fhc%252Fen-us';

		}

		if (strpos($this->custom_url, 'sindup.') !== false) {
			$this->custom_url = "https://app.sindup.com/connection.html";
			$this->loginUrl = 'https://app.sindup.com/connection.html';

		}

		if (strpos($this->custom_url, 'manomind.') !== false) {
			$this->custom_url = "https://manomind.zendesk.com/access/normal";
			$this->loginUrl = 'https://manomind.zendesk.com/access/normal';

		}

		if (strpos($this->custom_url, 'valuebuildersystem.') !== false) {
			$this->custom_url = "https://valuebuildersystem.com/sign-in";
			$this->loginUrl = 'https://valuebuildersystem.com/sign-in';
		}
		
		// I added code to handle custom_url. please remove the below line on PROD
		//$this->custom_url = "https://mia4415.zendesk.com/auth/v2/login/";
		//$this->loginUrl = 'https://mia4415.zendesk.com/auth/v2/login/';

		$this->exts->log('******************* Zendesk URL: '. $this->custom_url);
		$this->exts->log('******************* Zendesk LOGIN URL: '. $this->loginUrl);
	}
	if ($this->exts->urlContains('https://zendesk.com.zendesk.com')) {
       // $this->exts->webdriver->manage()->timeouts()->pageLoadTimeout(200);
      
    }
       

	//======================================================================
	$this->exts->log('Begin initPortal '.$count);		
	// Load cookies
	$this->exts->loadCookiesFromFile();
	sleep(1);
	$this->exts->openUrl($this->loginUrl);
	sleep(10);
	if ($this->exts->urlContainsAny(['https://zendesk.com.zendesk.com', 'https://www.zendesk.com/']) ||
		($this->exts->exists('p[jsselect="summary"]') 
			&& (strpos($this->exts->extract('p[jsselect="summary"]', null, 'innerText'), 'server IP address could not be found') !== false || strpos($this->exts->extract('p[jsselect="summary"]', null, 'innerText'), 'server IP address could not be found') !== false || strpos($this->exts->extract('p[jsselect="summary"]', null, 'innerText'), 'uses an unsupported protocol') !== false))
	) {
		// trigger login fail confirm if bad custom_url argument passed.
		$this->exts->loginFailure(1);
	}
	$this->exts->capture('1-init-page');

	// If user hase not logged in from cookie, clear cookie, open the login url and do login
	if($this->exts->getElement($this->check_login_success_selector) == null) {
		$this->exts->log('NOT logged via cookie');
		$this->clearChrome();
		$this->exts->openUrl($this->loginUrl);
		sleep(15);
		$this->checkFillLogin();
		sleep(5);
		$this->exts->capture('1-after-first-login');
		if(trim($this->exts->extract('.box-error-center h2')) == 'Forbidden'){
			$this->exts->clearCookies();
			$this->exts->openUrl($this->loginUrl);
			sleep(15);
			$this->checkFillLogin();
			sleep(15);
		}
	}

	if($this->exts->getElement($this->check_login_success_selector) != null
		|| ($this->exts->urlContains('auth/v2/login/signed_in') == true 
			&& $this->exts->urlContains('/auth/v2/login/normal') == false)) {
		sleep(3);
		$this->exts->log(__FUNCTION__.'::User logged in');
		$this->exts->capture("3-login-success");

		// Open invoices url and download invoice
		if (strpos($this->custom_url, 'account.zopim.com') !== false) {
			$this->exts->openUrl('https://account.zopim.com/account/invoices');
		} else {
			$temArr = explode('/', $this->custom_url);
			$invoicePaUrl = $temArr[0] . '//' . $temArr[2] . '/admin/billing/invoices';
			$this->exts->openUrl($invoicePaUrl);
		}
		sleep(30);

		$this->processInvoices();
		
		// Final, check no invoice
		if($this->isNoInvoice){
			$this->exts->no_invoice();
		}
		$this->exts->success();
	} else {
		$this->exts->log(__FUNCTION__.'::Use login failed '.$this->exts->getUrl());
		if($this->exts->urlContains('help-center-closed')
			|| $this->exts->exists('div#error, div.flash-error, div#login_error, div.login-section div.error, div:not(.ng-hide) > div.alert-danger')) {
			$this->exts->loginFailure(1);
		} else if (strpos(strtolower($this->exts->extract('#content form#login_form p.bg-danger', null, 'innerText')), 'ihr benutzername und passwort stimmen nicht') !== false || strpos(strtolower($this->exts->extract('#content form#login_form p.bg-danger', null, 'innerText')), 'your username and password did not match') !== false || strpos(strtolower($this->exts->extract('div.alert-info', null, 'innerText')), 'not been recognised') !== false) {
			$this->exts->loginFailure(1);
		} else if($this->exts->exists('button[data-test-id="expired-trial-avatar-button"]') && $this->exts->urlContains('/expired-trial')){
			$this->exts->account_not_ready();
		} else {
			if ($this->exts->exists('form.search.search-full') && $this->exts->urlContains('getsafehelp')) {
				$this->exts->no_permission();
			}
			$this->exts->loginFailure();
		}
	}
}

private function clearChrome()
{
    $this->exts->log("Clearing browser history, cookie, cache");
    $this->exts->openUrl('chrome://settings/clearBrowserData');
    sleep(10);
    $this->exts->capture("clear-page");
    for ($i = 0; $i < 2; $i++) {
        $this->exts->type_key_by_xdotool('Tab');
        sleep(1);
    }
    $this->exts->type_key_by_xdotool('Tab');
        sleep(1);
    $this->exts->type_key_by_xdotool('Return');
        sleep(1);
    $this->exts->type_key_by_xdotool('a');
    sleep(1);
    $this->exts->type_key_by_xdotool('Return');
    sleep(3);
    $this->exts->capture("clear-page");
    for ($i = 0; $i < 5; $i++) {
        $this->exts->type_key_by_xdotool('Tab');
        sleep(1);
    }
    $this->exts->type_key_by_xdotool('Return');
    sleep(15);
    $this->exts->capture("after-clear");
}

private function checkFillLogin() {
	if (!filter_var($this->username, FILTER_VALIDATE_EMAIL)){
		$this->exts->log("Username is not a valid email address");
		$this->exts->loginFailure(1);
	}

	$this->switchToFrame('iframe[src*="auth/v2/login"]');
	if ($this->login_with_google == 1 && $this->exts->exists('a[href*="accounts.google.com/"]')){
		$this->exts->moveToElementAndClick('a[href*="accounts.google.com/"]');
		sleep(10);
		$this->loginGoogleIfRequired();
	} else if (strpos($this->custom_url, 'clubmanager.') !== false){
		if($this->exts->exists('input#password')) {
			sleep(3);
			$this->exts->capture("2-clubmanager-pre-login");

			$this->exts->log("Enter Username");
			$this->exts->moveToElementAndType('input#username', $this->username);
			sleep(1);

			$this->exts->log("Enter Password");
			$this->exts->moveToElementAndType('input#password', $this->password);
			sleep(1);

			$this->exts->capture("2-clubmanager-login-page-filled");
			$this->exts->moveToElementAndClick('button#submit');
		} else {
			$this->exts->log(__FUNCTION__.'::Login page not found');
			$this->exts->capture("2-clubmanager-login-page-not-found");
		}
	} else if (strpos($this->custom_url, 'edufolios.') !== false){
		if($this->exts->exists('form#loginform')){
			sleep(3);
			$this->exts->capture("2-login-page");

			$this->exts->log("Enter Username");
			$this->exts->moveToElementAndType('input#user_login', $this->username);
			sleep(1);

			$this->exts->log("Enter Password");
			$this->exts->moveToElementAndType('input#user_pass', $this->password);
			sleep(1);

			$this->exts->moveToElementAndClick('input#rememberme');
			sleep(2);

			$this->exts->moveToElementAndClick('button#ap-cookiesConfirm__accept');
			sleep(1);

			$this->exts->capture("2-login-page-filled");
			$this->exts->moveToElementAndClick('wp-submit');

			sleep(3);
			if ($this->exts->exists('#one-time-password-form')) {
				$this->checkFillTwoFactor();
			}
		}
	} else if (strpos($this->custom_url, 'unlatch.') !== false){
		if($this->exts->exists('input[name="email"]')) {
			sleep(3);
			$this->exts->capture("2-unlatch-pre-login");

			$this->exts->log("Enter Username");
			$this->exts->moveToElementAndType('input[name="email"]', $this->username);
			sleep(2);
			$this->exts->moveToElementAndClick('#login form button[type="submit"]');
			sleep(10);

			$this->exts->log("Enter Password");
			$this->exts->moveToElementAndType('input[name="password"]', $this->password);
			sleep(2);

			$this->exts->capture("2-unlatch-login-page-filled");
			$this->exts->moveToElementAndClick('#login form button[type="submit"]');
		} else {
			$this->exts->log(__FUNCTION__.'::Login page not found');
			$this->exts->capture("2-unlatch-login-page-not-found");
		}
	} else if (strpos($this->custom_url, 'fleetmon.') !== false) {
		if($this->exts->exists('#content form#login_form input#id_username')) {
			sleep(3);
			$this->exts->capture("2-login-fleetmon-page");

			$this->exts->log("Enter Username");
			$this->exts->moveToElementAndType(
				'#content form#login_form input#id_username',
					$this->username);
			sleep(1);

			$this->exts->log("Enter Password");
			$this->exts->moveToElementAndType(
				'#content form#login_form input#id_password',
					$this->password);
			sleep(1);

			$this->exts->moveToElementAndClick('#content form#login_form input#id_rememberme:not(:checked)');
			sleep(2);

			$this->exts->capture("2-login-page-filled");
			$this->exts->moveToElementAndClick('#content form#login_form button#button-sign-in');

			sleep(13);
		} else {
			$this->exts->log(__FUNCTION__.'::Login page not found');
			$this->exts->capture("2-login-page-not-found");
		}
	} else if (strpos($this->custom_url, 'designforme.') !== false) {
		if($this->exts->exists('#user_login')) {
			sleep(3);
			$this->exts->capture("2-login-designforme-page");

			$this->exts->log("Enter Username");
			$this->exts->moveToElementAndType(
				'#user_login',
					$this->username);
			sleep(1);

			$this->exts->log("Enter Password");
			$this->exts->moveToElementAndType(
				'#user_pass',
					$this->password);
			sleep(1);

			$this->exts->moveToElementAndClick('input#rememberme:not(:checked)');
			sleep(2);

			$this->exts->capture("2-login-page-filled");
			$this->exts->moveToElementAndClick('#wp-submit');

			sleep(13);
		} else {
			$this->exts->log(__FUNCTION__.'::Login page not found');
			$this->exts->capture("2-login-page-not-found");
		}
	} else if (strpos($this->custom_url, 'sindup.') !== false) {
		if($this->exts->exists('[name="login"]')) {
			sleep(3);
			$this->exts->capture("2-login-designforme-page");

			$this->exts->log("Enter Username");
			$this->exts->moveToElementAndType(
				'[name="login"]',
					$this->username);
			sleep(1);

			$this->exts->log("Enter Password");
			$this->exts->moveToElementAndType(
				'[name="password"]',
					$this->password);
			sleep(1);

			$this->exts->moveToElementAndClick('input[name="remember"]:not(:checked)');
			sleep(2);

			$this->exts->capture("2-login-page-filled");
			$this->exts->moveToElementAndClick('button[name="submit"]');

			sleep(13);
		} else {
			$this->exts->log(__FUNCTION__.'::Login page not found');
			$this->exts->capture("2-login-page-not-found");
		}
	} else if (strpos($this->custom_url, 'manomind.') !== false){
		if($this->exts->exists('input#user_password')) {
			sleep(3);
			$this->exts->capture("2-manomind-pre-login");

			$this->exts->log("Enter Username");
			$this->exts->moveToElementAndType('input#user_email', $this->username);
			sleep(1);

			$this->exts->log("Enter Password");
			$this->exts->moveToElementAndType('input#user_password', $this->password);
			sleep(1);

			$this->exts->capture("2-manomind-login-page-filled");
			$this->exts->moveToElementAndClick('input#sign-in-submit-button');
		} else {
			$this->exts->log(__FUNCTION__.'::Login page not found');
			$this->exts->capture("2-manomind-login-page-not-found");
		}
	} else if (strpos($this->custom_url, 'valuebuildersystem.') !== false){
		if($this->exts->exists('input#username')) {
			sleep(3);
			$this->exts->capture("2-manomind-pre-login");

			$this->exts->log("Enter Username");
			$this->exts->moveToElementAndType('input#username', $this->username);
			sleep(1);

			$this->exts->log("Enter Password");
			$this->exts->moveToElementAndType('input#password', $this->password);
			sleep(1);

			$this->exts->capture("2-manomind-login-page-filled");
			$this->exts->moveToElementAndClick('form#signin-form button.btn-login');
		} else {
			$this->exts->log(__FUNCTION__.'::Login page not found');
			$this->exts->capture("2-manomind-login-page-not-found");
		}
	} else {
		if($this->exts->exists('form#login-form, form#login_form')) {
			sleep(3);
			$this->exts->capture("2-login-page");

			$this->exts->log("Enter Username");
			$this->exts->moveToElementAndType(
				'form#login-form input[name="login[username]"],
					input#user_email,
					form#login_form input[name="email"]',
					$this->username);
			sleep(1);

			$this->exts->log("Enter Password");
			$this->exts->moveToElementAndType(
				'form#login-form input[name="login[password]"],
					input#user_password,
					form#login_form input[name="password"]',
					$this->password);
			sleep(1);

			$this->exts->moveToElementAndClick('input#remember_me');
			sleep(2);

			$this->exts->capture("2-login-page-filled");
			$this->exts->moveToElementAndClick('form#login-form input[type="submit"], button#send2[type="submit"], form#login_form button[type="submit"], input#sign-in-submit-button, button#sign-in-submit-button');

			sleep(3);
			if ($this->exts->exists('#one-time-password-form input#password, input[data-testid="mfa-challenge-input"]')) {
				$this->checkFillTwoFactor();
			}
		} else {
			$this->exts->log(__FUNCTION__.'::Login page not found');
			$this->exts->capture("2-login-page-not-found");
		}
	}
}
private function checkFillTwoFactor() {
	$two_factor_selector = '#one-time-password-form input#password, input[data-testid="mfa-challenge-input"]';
	$two_factor_submit_selector = '#one-time-password-form [name="commit"], button[data-testid="mfa-challenge-submit"]';
	$two_factor_message_selector = '.login-main div.notification, div[data-garden-id="notifications.title"] div[data-garden-id="typography.font"]';

	if($this->exts->exists($two_factor_selector) != null && $this->exts->two_factor_attempts < 3){
		$this->exts->log("Two factor page found.");
		$this->exts->capture("2.1-two-factor");
		
		if($this->exts->getElement($two_factor_message_selector) != null){
			$this->exts->two_factor_notif_msg_en = "";
			for ($i=0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
				$this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en.$this->exts->getElements($two_factor_message_selector)[$i]->getText()."\n";
			}
			$this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
			$this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
			$this->exts->log("Message:\n".$this->exts->two_factor_notif_msg_en);
		}
		if($this->exts->two_factor_attempts == 2) {
			$this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en .' '. $this->exts->two_factor_notif_msg_retry_en;
			$this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de .' '. $this->exts->two_factor_notif_msg_retry_de;
		}
		
		$two_factor_code = trim($this->exts->fetchTwoFactorCode());
		if(!empty($two_factor_code) && trim($two_factor_code) != '') {
			$this->exts->log("checkFillTwoFactor: Entering two_factor_code.".$two_factor_code);
			$this->exts->getElement($two_factor_selector)->sendKeys($two_factor_code);
			
			$this->exts->log("checkFillTwoFactor: Clicking submit button.");
			sleep(3);
			$this->exts->capture("2.2-two-factor-filled-".$this->exts->two_factor_attempts);
			
			$this->exts->moveToElementAndClick($two_factor_submit_selector);
			sleep(15);
			
			if($this->exts->getElement($two_factor_selector) == null){
				$this->exts->log("Two factor solved");
			} else if ($this->exts->two_factor_attempts < 3) {
				$this->exts->two_factor_attempts++;
				$this->checkFillTwoFactor();
			} else {
				$this->exts->log("Two factor can not solved");
			}
		} else {
			$this->exts->log("Not received two factor code");
		}
	}
}

 // -------------------- GOOGLE login
public $google_username_selector = 'input[name="identifier"]';
public $google_submit_username_selector = '#identifierNext';
public $google_password_selector = 'input[name="password"], input[name="Passwd"]';
public $google_submit_password_selector = '#passwordNext, #passwordNext button';
public $google_solved_rejected_browser = false;
private function loginGoogleIfRequired()
{
    if ($this->exts->urlContains('google.')) {
        $this->checkFillGoogleLogin();
        sleep(10);
        $this->check_solve_rejected_browser();

        if ($this->exts->querySelector('input[name="password"][aria-invalid="true"], input[name="identifier"][aria-invalid="true"], input[aria-invalid="true"][name="Passwd"]') != null) {
            $this->exts->loginFailure(1);
        }

        if ($this->exts->exists('form #approve_button[name="submit_true"]')) {
            // An application is requesting permission to access your Google Account.
            // Click allow
            $this->exts->click_by_xdotool('form #approve_button[name="submit_true"]');
            sleep(10);
        }
        // Click next if confirm form showed
        $this->exts->click_by_xdotool('div[data-ownership-changed-phone-number] div:nth-child(2) > [role="button"]');
        $this->checkGoogleTwoFactorMethod();
        sleep(10);
        if ($this->exts->exists('#smsauth-interstitial-remindbutton')) {
            $this->exts->click_by_xdotool('#smsauth-interstitial-remindbutton');
            sleep(10);
        }
        if ($this->exts->exists('#tos_form input#accept')) {
            $this->exts->click_by_xdotool('#tos_form input#accept');
            sleep(10);
        }
        if ($this->exts->exists('[wizard-step-uid="RecoveryOptionsCollectionWizard:starter"] div:last-child > [role="button"]')) {
            $this->exts->click_by_xdotool('[wizard-step-uid="RecoveryOptionsCollectionWizard:starter"] div:last-child > [role="button"]');
            sleep(10);
        }
        if ($this->exts->exists('.action-button.signin-button + a.setup-button[href*="/two-step-verification/"]')) {
            // SKIP setup 2FA
            $this->exts->click_by_xdotool('.action-button.signin-button');
            sleep(10);
        }
        if ($this->exts->exists('[action="/signin/newfeatures/save"] #optionsButton ~ [role="button"]')) {
            $this->exts->click_by_xdotool('[action="/signin/newfeatures/save"] #optionsButton ~ [role="button"]');
            sleep(10);
        }
        if ($this->exts->exists('input[name="later"]') && $this->exts->urlContains('/AddressNoLongerAvailable')) {
            $this->exts->click_by_xdotool('input[name="later"]');
            sleep(7);
        }
        if ($this->exts->exists('#editLanguageAndContactForm a[href*="/adsense/app"]')) {
            $this->exts->click_by_xdotool('#editLanguageAndContactForm a[href*="/adsense/app"]');
            sleep(7);
        }
        if ($this->exts->exists('[data-view-instance-id="/web/chip-V0"] [role="button"]:first-child [jsslot]')) {
            $this->exts->click_by_xdotool('[data-view-instance-id="/web/chip-V0"] [role="button"]:first-child [jsslot]');
            sleep(10);
        }
        if ($this->exts->urlContains('gds.google.com/web/chip')) {
            $this->exts->click_by_xdotool('[role="button"]:first-child [jsslot]');
            sleep(10);
        }

        if ($this->exts->exists('form #approve_button[name="submit_true"]')) {
            // An application is requesting permission to access your Google Account.
            // Click allow
            $this->exts->click_by_xdotool('form #approve_button[name="submit_true"]');
            sleep(10);
        }


        $this->exts->log('URL before back to main tab: ' . $this->exts->getUrl());
        $this->exts->capture("google-login-before-back-maintab");
        if (
            $this->exts->querySelector('input[name="password"][aria-invalid="true"], input[name="identifier"][aria-invalid="true"], input[aria-invalid="true"][name="Passwd"]') != null
        ) {
            $this->exts->loginFailure(1);
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Not required google login.');
        $this->exts->capture("3-no-google-required");
    }
}
private function checkFillGoogleLogin()
{
    if ($this->exts->exists('[data-view-id*="signInChooserView"] li [data-identifier]')) {
        $this->exts->click_by_xdotool('[data-view-id*="signInChooserView"] li [data-identifier]');
        sleep(10);
    } else if ($this->exts->exists('form li [role="link"][data-identifier]')) {
        $this->exts->click_by_xdotool('form li [role="link"][data-identifier]');
        sleep(10);
    }
    if ($this->exts->exists('form [data-profileindex]')) {
        $this->exts->click_by_xdotool('form [data-profileindex]');
        sleep(5);
    }
    $this->exts->capture("2-google-login-page");
    if ($this->exts->querySelector($this->google_username_selector) != null) {
        // $this->fake_user_agent();
        // $this->exts->refresh();
        // sleep(5);

        $this->exts->log("Enter Google Username");
        $this->exts->moveToElementAndType($this->google_username_selector, $this->username);
        sleep(1);
        $this->exts->click_by_xdotool($this->google_submit_username_selector);
        sleep(7);
        if ($this->exts->exists('#captchaimg[src]') && !$this->exts->exists($this->google_password_selector) && $this->exts->exists($this->google_username_selector)) {
            $this->exts->moveToElementAndType($this->google_username_selector, $this->username);
            sleep(1);
            $this->exts->processCaptcha('#captchaimg[src]', '#captchaimg[src] ~ * input[type="text"]');
            $this->exts->click_by_xdotool($this->google_submit_username_selector);
            sleep(5);
        }

        // Which account do you want to use?
        if ($this->exts->exists('form[action*="/lookup"] button.account-chooser-button')) {
            $this->exts->click_by_xdotool('form[action*="/lookup"] button.account-chooser-button');
            sleep(5);
        }
        if ($this->exts->exists('[data-view-id="prbTle"] form [role="link"][data-profileindex]')) {
            $this->exts->click_by_xdotool('[data-view-id="prbTle"] form [role="link"][data-profileindex]');
            sleep(5);
        }
    }

    if ($this->exts->querySelector($this->google_password_selector) != null) {
        $this->exts->log("Enter Google Password");
        $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
        sleep(1);

        if ($this->exts->exists('#captchaimg[src]')) {
            $this->exts->processCaptcha('#captchaimg[src]', '#captchaimg[src] ~ * input[type="text"]');
        }

        $this->exts->capture("2-google-password-filled");
        $this->exts->click_by_xdotool($this->google_submit_password_selector);
        sleep(5);
        if ($this->exts->exists('#captchaimg[src]') && $this->exts->exists($this->google_password_selector)) {
            $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
            sleep(1);
            $this->exts->processCaptcha('#captchaimg[src]', '#captchaimg[src] ~ * input[type="text"]');
            $this->exts->capture("2-login-google-pageandcaptcha-filled");
            $this->exts->click_by_xdotool($this->google_submit_password_selector);
            sleep(10);
            if ($this->exts->exists('#captchaimg[src]') && $this->exts->exists($this->google_password_selector)) {
                $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
                sleep(1);
                $this->exts->processCaptcha('#captchaimg[src]', '#captchaimg[src] ~ * input[type="text"]');
                $this->exts->capture("2-login-google-pageandcaptcha-filled");
                $this->exts->click_by_xdotool($this->google_submit_password_selector);
                sleep(10);
            }
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::google Password page not found');
        $this->exts->capture("2-google-password-page-not-found");
    }
}
private function check_solve_rejected_browser()
{
    $this->exts->log(__FUNCTION__);
    $root_user_agent = $this->exts->evaluate('(function() { return navigator.userAgent; })();');

    if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) {
        $this->overwrite_user_agent('Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:105.0) Gecko/20100101 Firefox/105.0');
        if ($this->exts->urlContains('/v3/')) {
            $this->exts->click_by_xdotool('a[href*="/restart"]');
        } else {
            $this->exts->refresh();
        }
        sleep(7);
        $this->exts->capture("2-login-alternative-page");
        $this->checkFillLogin_undetected_mode();
    }
    if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) {
        $this->overwrite_user_agent('Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:105.0) Gecko/20100101 Firefox/105.0');
        if ($this->exts->urlContains('/v3/')) {
            $this->exts->click_by_xdotool('a[href*="/restart"]');
        } else {
            $this->exts->refresh();
        }
        sleep(7);
        $this->exts->capture("2-login-alternative-page");
        $this->checkFillLogin_undetected_mode();
    }
    if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) {
        $this->overwrite_user_agent('Mozilla/5.0 (Macintosh; Intel Mac OS X 12.6; rv:105.0) Gecko/20100101 Firefox/105.0');
        if ($this->exts->urlContains('/v3/')) {
            $this->exts->click_by_xdotool('a[href*="/restart"]');
        } else {
            $this->exts->refresh();
        }
        sleep(7);
        $this->exts->capture("2-login-alternative-page");
        $this->checkFillLogin_undetected_mode();
    }
    if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) {
        $this->overwrite_user_agent('Mozilla/5.0 (Macintosh; Intel Mac OS X 12_6) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Safari/605.1.15');
        if ($this->exts->urlContains('/v3/')) {
            $this->exts->click_by_xdotool('a[href*="/restart"]');
        } else {
            $this->exts->refresh();
        }
        sleep(7);
        $this->exts->capture("2-login-alternative-page");
        $this->checkFillLogin_undetected_mode();
    }
    if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) {
        $this->overwrite_user_agent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/105.0.0.0 Safari/537.36 OPR/90.0.4480.107');
        if ($this->exts->urlContains('/v3/')) {
            $this->exts->click_by_xdotool('a[href*="/restart"]');
        } else {
            $this->exts->refresh();
        }
        sleep(7);
        $this->exts->capture("2-login-alternative-page");
        $this->checkFillLogin_undetected_mode();
    }
    if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) {
        $this->overwrite_user_agent('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/105.0.0.0 Safari/537.36 OPR/90.0.4480.107');
        if ($this->exts->urlContains('/v3/')) {
            $this->exts->click_by_xdotool('a[href*="/restart"]');
        } else {
            $this->exts->refresh();
        }
        sleep(7);
        $this->exts->capture("2-login-alternative-page");
        $this->checkFillLogin_undetected_mode();
    }

    if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) { // If all above failed, using DN user agent as last solution
        $this->overwrite_user_agent($root_user_agent);
        if ($this->exts->urlContains('/v3/')) {
            $this->exts->click_by_xdotool('a[href*="/restart"]');
        } else {
            $this->exts->refresh();
        }
        sleep(7);
        $this->checkFillLogin_undetected_mode($root_user_agent);
    }
    if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) { // If all above failed, using DN user agent as last solution
        $this->overwrite_user_agent($root_user_agent);
        if ($this->exts->urlContains('/v3/')) {
            $this->exts->click_by_xdotool('a[href*="/restart"]');
        } else {
            $this->exts->refresh();
        }
        sleep(7);
        $this->checkFillLogin_undetected_mode($root_user_agent);
    }
    if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) { // If all above failed, using DN user agent as last solution
        if ($this->exts->urlContains('/v3/')) {
            $this->exts->click_by_xdotool('a[href*="/restart"]');
        } else {
            $this->exts->refresh();
        }
        sleep(7);
        $this->checkFillLogin_undetected_mode($root_user_agent);
    }
    if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) { // If all above failed, using DN user agent as last solution
        if ($this->exts->urlContains('/v3/')) {
            $this->exts->click_by_xdotool('a[href*="/restart"]');
        } else {
            $this->exts->refresh();
        }
        sleep(7);
        $this->checkFillLogin_undetected_mode($root_user_agent);
    }
    if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) { // If all above failed, using DN user agent as last solution
        if ($this->exts->urlContains('/v3/')) {
            $this->exts->click_by_xdotool('a[href*="/restart"]');
        } else {
            $this->exts->refresh();
        }
        sleep(7);
        $this->checkFillLogin_undetected_mode($root_user_agent);
    }
    if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) { // If all above failed, using DN user agent as last solution
        if ($this->exts->urlContains('/v3/')) {
            $this->exts->click_by_xdotool('a[href*="/restart"]');
        } else {
            $this->exts->refresh();
        }
        sleep(7);
        $this->checkFillLogin_undetected_mode($root_user_agent);
    }
}
private function overwrite_user_agent($user_agent_string = 'DN')
{
    $userAgentScript = "
        (function() {
            if ('userAgentData' in navigator) {
                navigator.userAgentData.getHighEntropyValues({}).then(() => {
                    Object.defineProperty(navigator, 'userAgent', { 
                        value: '{$user_agent_string}', 
                        configurable: true 
                    });
                });
            } else {
                Object.defineProperty(navigator, 'userAgent', { 
                    value: '{$user_agent_string}', 
                    configurable: true 
                });
            }
        })();
    ";
    $this->exts->execute_javascript($userAgentScript);
}

private function checkFillLogin_undetected_mode($root_user_agent = '')
{
    if ($this->exts->exists('form [data-profileindex]')) {
        $this->exts->click_by_xdotool('form [data-profileindex]');
        sleep(5);
    } else if ($this->exts->urlContainsAny(['/ServiceLogin/identifier', '/ServiceLogin/webreauth']) && $this->exts->exists($this->google_submit_username_selector) && !$this->exts->exists($this->google_username_selector)) {
        $this->exts->capture("2-google-verify-it-you");
        // To help keep your account secure, Google needs to verify it’s you. Please sign in again to continue to Google Ads
        $this->exts->click_by_xdotool($this->google_submit_username_selector);
        sleep(5);
    }

    $this->exts->capture("2-google-login-page");
    if ($this->exts->check_exist_by_chromedevtool($this->google_username_selector)) {
        if (!empty($root_user_agent)) {
            $this->overwrite_user_agent('DN'); // using DN (DONT KNOW) user agent, last solution
        }
        $this->exts->type_key_by_xdotool("F5");
        sleep(5);
        $current_useragent = $this->exts->evaluate('(function() { return navigator.userAgent; })();');

        $this->exts->log('current_useragent: ' . $current_useragent);
        $this->exts->log("Enter Username");
        $this->exts->click_by_xdotool($this->google_username_selector);
        $this->exts->click_by_xdotool($this->google_username_selector);
        $this->exts->type_key_by_xdotool("ctrl+a");
        $this->exts->type_key_by_xdotool("Delete");
        $this->exts->type_text_by_xdotool($this->username);
        sleep(1);
        $this->exts->capture_by_chromedevtool("2-google-username-filled");
        $this->exts->click_by_xdotool($this->google_submit_username_selector);
        sleep(7);
        if ($this->exts->check_exist_by_chromedevtool('#captchaimg[src]') && !$this->exts->check_exist_by_chromedevtool($this->google_password_selector) && $this->exts->check_exist_by_chromedevtool($this->google_username_selector)) {
            $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
            $this->exts->click_by_xdotool($this->google_submit_username_selector);
            sleep(5);
            if ($this->exts->check_exist_by_chromedevtool('#captchaimg[src]') && !$this->exts->check_exist_by_chromedevtool($this->google_password_selector) && $this->exts->check_exist_by_chromedevtool($this->google_username_selector)) {
                $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                $this->exts->click_by_xdotool($this->google_submit_username_selector);
                sleep(5);
            }
            if ($this->exts->check_exist_by_chromedevtool('#captchaimg[src]') && !$this->exts->check_exist_by_chromedevtool($this->google_password_selector) && $this->exts->check_exist_by_chromedevtool($this->google_username_selector)) {
                $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                $this->exts->click_by_xdotool($this->google_submit_username_selector);
                sleep(5);
            }
        }

        if (!empty($root_user_agent)) { // If using DN user agent, we must revert back to root user agent before continue
            $this->overwrite_user_agent($root_user_agent);
            if ($this->exts->check_exist_by_chromedevtool($this->google_password_selector)) {
                $this->exts->type_key_by_xdotool("F5");
                sleep(3);
                $this->exts->type_key_by_xdotool("F5");
                sleep(3);
                $this->exts->type_key_by_xdotool("F5");
                sleep(6);
                $this->exts->capture_by_chromedevtool("2-google-login-reverted-UA");
            }
        }

        // Which account do you want to use?
        if ($this->exts->check_exist_by_chromedevtool('form[action*="/lookup"] button.account-chooser-button')) {
            $this->exts->click_by_xdotool('form[action*="/lookup"] button.account-chooser-button');
            sleep(5);
        }
        if ($this->exts->check_exist_by_chromedevtool('[data-view-id="prbTle"] form [role="link"][data-profileindex]')) {
            $this->exts->click_by_xdotool('[data-view-id="prbTle"] form [role="link"][data-profileindex]');
            sleep(5);
        }
    }

    if ($this->exts->check_exist_by_chromedevtool($this->google_password_selector)) {
        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
        sleep(1);
        if ($this->exts->exists('#captchaimg[src]')) {
            $this->exts->processCaptcha('#captchaimg[src]', '#captchaimg[src] ~ * input[type="text"]');
        }

        $this->exts->capture("2-google-password-filled");
        $this->exts->click_by_xdotool($this->google_submit_password_selector);
        sleep(5);
        if ($this->exts->exists('#captchaimg[src]') && !$this->exts->exists('input[name="password"][aria-invalid="true"]') && $this->exts->exists($this->google_password_selector)) {
            $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
            sleep(1);
            if ($this->exts->exists('#captchaimg[src]')) {
                $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
            }
            $this->exts->click_by_xdotool($this->google_submit_password_selector);
            sleep(5);
            if ($this->exts->exists('#captchaimg[src]') && $this->exts->exists($this->google_password_selector)) {
                $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
                sleep(1);
                $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                $this->exts->capture("2-lgoogle-ogin-pageandcaptcha-filled");
                $this->exts->click_by_xdotool($this->google_submit_password_selector);
            }
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Password page not found');
        $this->exts->capture("2-google-password-page-not-found");
    }
}
private function checkGoogleTwoFactorMethod()
{
    // Currently we met many two factor methods
    // - Confirm email account for account recovery
    // - Confirm telephone number for account recovery
    // - Call to your assigned phone number
    // - confirm sms code
    // - Solve the notification has sent to smart phone
    // - Use security key usb
    // - Use your phone or tablet to get a security code (EVEN IF IT'S OFFLINE)
    $this->exts->log(__FUNCTION__);
    sleep(5);
    $this->exts->capture("2.0-before-check-two-factor");
    // STEP 0 (updated special case 28-Mar-2020): If we meet a unsolvable, click to back to method choosen list
    if ($this->exts->exists('#assistActionId') && $this->exts->exists('[data-illustration="securityKeyLaptopAnim"]')) {
        $this->exts->click_by_xdotool('#assistActionId');
        sleep(5);
    } else if ($this->exts->urlContains('/challenge/wa') && strpos($this->exts->extract('form header h2'), 'QR-Code') !== false) {
        // (updated special case 28-Mar-2020): If we meet QR-Code, click 'Choose another option' to back to method choosen list
        $this->exts->click_by_xdotool('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
        sleep(5);
        $this->exts->capture("2.0-backed-methods-list");
        if ($this->exts->urlContains('/challenge/wa') && strpos($this->exts->extract('form header h2'), 'QR-Code') !== false) {
            $this->exts->click_by_xdotool('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
            sleep(5);
        }
    } else if ($this->exts->urlContains('/sk/webauthn') || $this->exts->urlContains('/challenge/pk')) {
        // CURRENTLY THIS CASE CAN NOT BE SOLVED
        $node_name = !empty($this->exts->config_array['node_name']) ? $this->exts->config_array['node_name'] : "selenium-node-" . $this->exts->process_uid;
        exec("sudo docker exec " . $node_name . " bash -c 'sudo apt-get clean'");
        exec("sudo docker exec " . $node_name . " bash -c 'sudo apt-get -y update'");
        exec("sudo docker exec " . $node_name . " bash -c 'sudo apt-get install -y xdotool'");
        exec("sudo docker exec " . $node_name . " bash -c 'xdotool key Return'");
        sleep(3);
        $this->exts->capture("2.0-cancel-security-usb");
        $this->exts->click_by_xdotool('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
        sleep(5);
        $this->exts->capture("2.0-backed-methods-list");
    } else if ($this->exts->exists('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?')) {
        // (updated special case 09-May-2020): If Notification method showed immediately, This method often make user confused
        // So, We try to click 'Choose another option' in order to select easier method
        $this->exts->click_by_xdotool('[data-view-id] > div > div:nth-child(2)  div:nth-child(2) > [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button, button#assistiveActionOutOfQuota');
        sleep(7);
    } else if ($this->exts->exists('input[name="ootpPin"]')) {
        // (updated special case 11-Jun-2020): If "Verify by offline device" immediately, This method often make user confused and maybe they don't have device on hand
        // So, We try to click 'Choose another option' in order to select easier method
        $this->exts->click_by_xdotool('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
        sleep(7);
    } else if ($this->exts->urlContains('/challenge/') && !$this->exts->urlContains('/challenge/pwd') && !$this->exts->urlContains('/challenge/totp')) { // totp is authenticator app code method
        // if this is not password form AND this is two factor form BUT it is not Authenticator app code method, back to selection list anyway in order to choose Authenticator app method if available
        $supporting_languages = [
            "Try another way",
            "Andere Option w",
            "Essayer une autre m",
            "Probeer het op een andere manier",
            "Probar otra manera",
            "Prova un altro metodo"
        ];
        $back_button_xpath = '//*[contains(text(), "Try another way") or contains(text(), "Andere Option w") or contains(text(), "Essayer une autre m")';
        $back_button_xpath = $back_button_xpath . ' or contains(text(), "Probeer het op een andere manier") or contains(text(), "Probar otra manera") or contains(text(), "Prova un altro metodo")';
        $back_button_xpath = $back_button_xpath . ']/..';
        $back_button = $this->exts->getElement($back_button_xpath, null, 'xpath');
        if ($back_button != null) {
            try {
                $this->exts->log(__FUNCTION__ . ' back to method list to find Authenticator app.');
                $this->exts->execute_javascript("arguments[0].click();", [$back_button]);
            } catch (\Exception $exception) {
                $this->exts->executeSafeScript("arguments[0].click()", [$back_button]);
            }
        }
        sleep(5);
        $this->exts->capture("2.0-backed-methods-list");
    }

    // STEP 1: Check if list of two factor methods showed, select first
    if ($this->exts->exists('li [data-challengetype]:not([data-challengeunavailable="true"])')) {
        $this->exts->capture("2.1-2FA-method-list");

        // Updated 03-2023 since we setup sub-system to get authenticator code without request to end-user. So from now, We priority for code from Authenticator app top 1, sms code or email code 2st, then other methods
        if ($this->exts->exists('li [data-challengetype="6"]:not([data-challengeunavailable="true"])')) {
            // We RECOMMEND TOP 1 method type = 6 is get code from Google Authenticator
            $this->exts->click_by_xdotool('li [data-challengetype="6"]:not([data-challengeunavailable="true"])');
        } else if ($this->exts->exists('li [data-challengetype="13"]:not([data-challengeunavailable="true"])') && isset($this->security_phone_number) && $this->security_phone_number != '') {
            $this->exts->click_by_xdotool('li [data-challengetype="13"]:not([data-challengeunavailable="true"])');
        } else if ($this->exts->exists('li [data-challengetype="12"]:not([data-challengeunavailable="true"])') && isset($this->recovery_email) && $this->recovery_email != '') {
            $this->exts->click_by_xdotool('li [data-challengetype="12"]:not([data-challengeunavailable="true"])');
        } else if ($this->exts->exists('li [data-challengetype="1"]:not([data-challengeunavailable="true"])')) {
            // Select enter your passowrd, if only option is passkey
            $this->exts->click_by_xdotool('li [data-challengetype="1"]:not([data-challengeunavailable="true"])');
            sleep(3);
            $this->checkFillGoogleLogin();
            sleep(3);
            $this->checkGoogleTwoFactorMethod();
        } else if ($this->exts->exists('li [data-challengetype="6"]:not([data-challengeunavailable="true"])')) {
            // We RECOMMEND method type = 6 is get code from Google Authenticator
            $this->exts->click_by_xdotool('li [data-challengetype="6"]:not([data-challengeunavailable="true"])');
        } else if ($this->exts->exists('li [data-challengetype][data-sendmethod="SMS"]:not([data-challengeunavailable="true"])') && (isset($this->security_phone_number) && $this->security_phone_number != '')) {
            // We second RECOMMEND method type = 9 is get code from SMS
            $this->exts->click_by_xdotool('li [data-challengetype][data-sendmethod="SMS"]:not([data-challengeunavailable="true"])');
        } else if ($this->exts->exists('li [data-challengetype="4"][data-sendauthzenprompt="true"]:not([data-challengeunavailable="true"]), li [data-challengetype="39"][data-challengeid="10"]:not([data-challengeunavailable="true"])')) {
            // We recommend method type = 4 and [data-sendauthzenprompt="true"] is  Tap YES on your smartphone or tablet
            $this->exts->click_by_xdotool('li [data-challengetype="4"][data-sendauthzenprompt="true"]:not([data-challengeunavailable="true"]), li [data-challengetype="39"]:not([data-challengeunavailable="true"])');
        }
        else if ($this->exts->exists('li [data-challengetype="4"][data-sendauthzenprompt="true"]:not([data-challengeunavailable="true"]), li [data-challengetype="39"][data-challengeid="12"]:not([data-challengeunavailable="true"])')) {
            // We recommend method type = 4 and [data-sendauthzenprompt="true"] is  Tap YES on your smartphone or tablet
            $this->exts->click_by_xdotool('li [data-challengetype="4"][data-sendauthzenprompt="true"]:not([data-challengeunavailable="true"]), li [data-challengetype="39"]:not([data-challengeunavailable="true"])');
        } else if ($this->exts->exists('li [data-challengetype="5"]:not([data-challengeunavailable="true"])')) {
            // Use a smartphone or tablet to receive a security code (even when offline)
            $this->exts->click_by_xdotool('li [data-challengetype="5"]:not([data-challengeunavailable="true"])');
        } else if ($this->exts->exists('li [data-challengetype]:not([data-challengetype="4"]):not([data-challengetype="2"]):not([data-challengeunavailable="true"])')) {
            // We DONT recommend method is QR code OR is Security USB, we can not solve this type of 2FA
            $this->exts->click_by_xdotool('li [data-challengetype]:not([data-challengetype="4"]):not([data-challengetype="2"]):not([data-challengeunavailable="true"])');
        } else {
            $this->exts->click_by_xdotool('li [data-challengetype]:not([data-challengeunavailable="true"])');
        }
        sleep(10);
    } else if ($this->exts->exists('#smsButton, [data-illustration="accountRecoverySmsPin"]')) {
        // Sometime user must confirm before google send sms
        $this->exts->click_by_xdotool('#smsButton, div:first-child > [role="button"], [data-secondary-action-label] > div > div:nth-child(1) button');
        sleep(10);
    } else if ($this->exts->exists('#authzenNext') && $this->exts->exists('[data-view-id*="authzenView"], [data-illustration*="authzen"]')) {
        $this->exts->click_by_xdotool('[data-view-id] #authzenNext');
        sleep(10);
    } else if ($this->exts->exists('#idvpreregisteredemailNext') && !$this->exts->exists('form input:not([type="hidden"])')) {
        $this->exts->click_by_xdotool('#idvpreregisteredemailNext');
        sleep(10);
    }

    // STEP 2: (Optional)
    if ($this->exts->exists('input#knowledge-preregistered-email-response, input[name="knowledgePreregisteredEmailResponse"]')) {
        // If methos is recovery email, send 2FA to ask for email
        $this->exts->two_factor_attempts = 2;
        $input_selector = 'input#knowledge-preregistered-email-response, input[name="knowledgePreregisteredEmailResponse"]';
        $message_selector = '[data-view-id] form section div > div[jsslot] > div:first-child';
        $submit_selector = '';
        if (isset($this->recovery_email) && $this->recovery_email != '') {
            $this->exts->moveToElementAndType($input_selector, $this->recovery_email);
            $this->exts->type_key_by_xdotool('Return');
            sleep(7);
        }
        if ($this->exts->exists($input_selector)) {
            $this->fillGoogleTwoFactor($input_selector, $message_selector, $submit_selector, true);
            sleep(5);
        }
    } else if ($this->exts->exists('[data-view-id*="knowledgePreregisteredPhoneView"] input[type="tel"]')) {
        // If methos confirm recovery phone number, send 2FA to ask
        $this->exts->two_factor_attempts = 3;
        $input_selector = '[data-view-id*="knowledgePreregisteredPhoneView"] input[type="tel"]';
        $message_selector = '[data-view-id] form section div > div[jsslot] > div:first-child';
        $submit_selector = '';
        if (isset($this->security_phone_number) && $this->security_phone_number != '') {
            $this->exts->moveToElementAndType($input_selector, $this->security_phone_number);
            $this->exts->type_key_by_xdotool('Return');
            sleep(5);
        }
        if ($this->exts->exists($input_selector)) {
            $this->fillGoogleTwoFactor($input_selector, $message_selector, $submit_selector, true);
            sleep(5);
        }
    } else if ($this->exts->exists('input#phoneNumberId')) {
        // Enter a phone number to receive an SMS with a confirmation code.
        $this->exts->two_factor_attempts = 3;
        $input_selector = 'input#phoneNumberId';
        $message_selector = '[data-view-id] form section > div > div > div:first-child';
        $submit_selector = '';
        if (isset($this->security_phone_number) && $this->security_phone_number != '') {
            $this->exts->moveToElementAndType($input_selector, $this->security_phone_number);
            $this->exts->type_key_by_xdotool('Return');
            sleep(7);
        }
        if ($this->exts->exists($input_selector)) {
            $this->fillGoogleTwoFactor($input_selector, $message_selector, $submit_selector, true);
        }
    } else if ($this->exts->exists('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?')) {
        // Check your smartphone. Google has sent a notification to your smartphone. Tap Yes in the notification, then tap 91 on your smartphone to continue
        $this->exts->two_factor_attempts = 3;
        $message_selector = '[data-view-id*="authzenView"] form, [data-view-id] form[method="post"]';
        $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'text')) . "\n>>>Enter \"OK\" after confirmation on device";
        $this->exts->two_factor_notif_msg_de = trim($this->exts->extract($message_selector, null, 'text')) . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
        $this->fillGoogleTwoFactor(null, null, '');
        sleep(5);
    } else if ($this->exts->exists('[data-view-id*="securityKeyWebAuthnView"], [data-view-id*="securityKeyView"]')) {
        // Method: insert your security key and touch it
        $this->exts->two_factor_attempts = 3;
        $this->exts->two_factor_notif_msg_en = 'Use chrome, login then insert your security key and touch it' . "\n>>>Enter \"OK\" after confirmation on device";
        $this->exts->two_factor_notif_msg_de = '[Chrome] Stecken Sie den Sicherheitsschlussel in den USB-Anschluss Ihres Computers ein. Wenn er eine Taste hat, tippen Sie darauf.' . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
        $this->fillGoogleTwoFactor(null, null, '');
        sleep(5);
        // choose another option: #assistActionId
    }

    // STEP 3: (Optional)  After choose method and confirm email or phone or.., google may asked confirm one more time before send code
    if ($this->exts->exists('#smsButton, [data-illustration="accountRecoverySmsPin"]')) {
        // Sometime user must confirm before google send sms
        $this->exts->click_by_xdotool('#smsButton, div:first-child > [role="button"], [data-secondary-action-label] > div > div:nth-child(1) button');
        sleep(10);
    } else if ($this->exts->exists('#authzenNext') && $this->exts->exists('[data-view-id*="authzenView"], [data-illustration*="authzen"]')) {
        $this->exts->click_by_xdotool('[data-view-id] #authzenNext');
        sleep(10);
    } else if ($this->exts->exists('#idvpreregisteredemailNext') && !$this->exts->exists('form input:not([type="hidden"])')) {
        $this->exts->click_by_xdotool('#idvpreregisteredemailNext');
        sleep(10);
    } else if (count($this->exts->querySelectorAll('li [data-challengetype]:not([data-challengeunavailable="true"]):not([data-challengetype="undefined"])')) > 0) {
        $this->exts->click_by_xdotool('li [data-challengetype]:not([data-challengeunavailable="true"]):not([data-challengetype="undefined"])');
        sleep(7);
    }


    // STEP 4: input code
    if ($this->exts->exists('form input[name="idvPin"], form input[name="totpPin"], input[name="code"], input#backupCodePin, input#idvPin, input#idvPinId')) {
        $input_selector = 'form input[name="idvPin"], form input[name="totpPin"], input[name="code"], input#backupCodePin, input#idvPin, input#idvPinId';
        $message_selector = 'form > span > section > div > div > div:first-child';
        $submit_selector = '#idvPreregisteredPhoneNext, #idvpreregisteredemailNext, #totpNext, #idvanyphoneverifyNext, #backupCodeNext';
        $this->exts->two_factor_attempts = 3;
        $this->fillGoogleTwoFactor($input_selector, $message_selector, $submit_selector, true);
    } else if ($this->exts->exists('input[name="ootpPin"], input#securityKeyOtpInputId')) {
        $input_selector = 'input[name="ootpPin"], input#securityKeyOtpInputId';
        $message_selector = 'form > span > section > div > div > div:first-child';
        $submit_selector = '';
        $this->exts->two_factor_attempts = 0;
        $this->fillGoogleTwoFactor($input_selector, $message_selector, $submit_selector, true);
    } else if ($this->exts->exists('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?')) {
        // Check your smartphone. Google has sent a notification to your smartphone. Tap Yes in the notification, then tap 91 on your smartphone to continue
        $this->exts->two_factor_attempts = 3;
        $message_selector = '[data-view-id*="authzenView"] form, [data-view-id] form[method="post"]';
        $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'text')) . "\n>>>Enter \"OK\" after confirmation on device";
        $this->exts->two_factor_notif_msg_de = trim($this->exts->extract($message_selector, null, 'text')) . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
        $this->fillGoogleTwoFactor(null, null, '');
        sleep(5);
    } else if ($this->exts->exists('[data-view-id*="securityKeyWebAuthnView"], [data-view-id*="securityKeyView"]')) {
        // Method: insert your security key and touch it
        $this->exts->two_factor_attempts = 3;
        $this->exts->two_factor_notif_msg_en = 'Use chrome, login then insert your security key and touch it' . "\n>>>Enter \"OK\" after confirmation on device";
        $this->exts->two_factor_notif_msg_de = '[Chrome] Stecken Sie den Sicherheitsschlussel in den USB-Anschluss Ihres Computers ein. Wenn er eine Taste hat, tippen Sie darauf.' . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
        $this->fillGoogleTwoFactor(null, null, '');
        sleep(5);
        // choose another option: #assistActionIdk
    } else if ($this->exts->exists('input[name="secretQuestionResponse"]')) {
        $input_selector = 'input[name="secretQuestionResponse"]';
        $message_selector = 'form > span > section > div > div > div:first-child';
        $submit_selector = '[data-secondary-action-label] > div > div:nth-child(1) button';
        $this->exts->two_factor_attempts = 0;
        $this->fillGoogleTwoFactor($input_selector, $message_selector, $submit_selector);
    }
}
private function fillGoogleTwoFactor($input_selector, $message_selector, $submit_selector, $submit_by_enter = false)
{
    $this->exts->log(__FUNCTION__);
    $this->exts->log("Two factor page found.");
    $this->exts->capture("2.1-two-factor");

    if ($this->exts->querySelector($message_selector) != null) {
        $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'text'));
        $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
        if ($this->exts->two_factor_attempts > 1) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
        }
    }

    $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
    $this->exts->notification_uid = "";
    $two_factor_code = trim($this->exts->fetchTwoFactorCode());
    if (!empty($two_factor_code) && trim($two_factor_code) != '') {
        if ($this->exts->querySelector($input_selector) != null) {
            if (substr(trim($two_factor_code), 0, 2) === 'G-') {
                $two_factor_code = end(explode('G-', $two_factor_code));
            }
            if (substr(trim($two_factor_code), 0, 2) === 'g-') {
                $two_factor_code = end(explode('g-', $two_factor_code));
            }
            $this->exts->log("fillTwoFactor: Entering two_factor_code: " . $two_factor_code);
            $this->exts->moveToElementAndType($input_selector, '');
            $this->exts->moveToElementAndType($input_selector, $two_factor_code);
            sleep(2);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

            if ($this->exts->exists($submit_selector)) {
                $this->exts->log("fillTwoFactor: Clicking submit button.");
                $this->exts->click_by_xdotool($submit_selector);
            } else if ($submit_by_enter) {
                $this->exts->type_key_by_xdotool('Return');
            }
            sleep(10);
            $this->exts->capture("2.2-two-factor-submitted-" . $this->exts->two_factor_attempts);
            if ($this->exts->querySelector($input_selector) == null) {
                $this->exts->log("Two factor solved");
            } else {
                if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->notification_uid = '';
                    $this->exts->two_factor_attempts++;
                    if ($this->exts->exists('form input[name="idvPin"], form input[name="totpPin"], input[name="code"], input#backupCodePin')) {
                        // if(strpos(strtoupper($this->exts->extract('div:last-child[style*="visibility: visible;"] [role="button"]')), 'CODE') !== false){
                        $this->exts->click_by_xdotool('[aria-relevant="additions"] + [style*="visibility: visible;"] [role="button"]');
                        sleep(2);
                        $this->exts->capture("2.2-two-factor-resend-code-" . $this->exts->two_factor_attempts);
                        // }
                    }

                    $this->fillGoogleTwoFactor($input_selector, $message_selector, $submit_selector);
                } else {
                    $this->exts->log("Two factor can not solved");
                }
            }
        } else {
            $this->exts->log("Not found two factor input");
        }
    } else {
        $this->exts->log("Not received two factor code");
    }
}
// -------------------- GOOGLE login END

public function switchToFrame($query_string)
{
    $this->exts->log(__FUNCTION__ . " Begin with " . $query_string);
    $frame = null;
    if (is_string($query_string)) {
        $frame = $this->exts->queryElement($query_string);
    }

    if ($frame != null) {
        $frame_context = $this->exts->get_frame_excutable_context($frame);
        if ($frame_context != null) {
            $this->exts->current_context = $frame_context;
            return true;
        }
    } else {
        $this->exts->log(__FUNCTION__ . " Frame not found " . $query_string);
    }

    return false;
}

private function processInvoices() {
	sleep(25);
	$this->exts->capture("4-invoices-page");
	$invoices = [];
	if ($this->exts->exists('iframe[data-testid="mountpoint-iframe"]')) {
		$this->switchToFrame('iframe[data-testid="mountpoint-iframe"]');
	}
	$this->switchToFrame('iframe[src*="/invoices"]');
	$rows = $this->exts->getElements('.invoices-table .row');
	foreach ($rows as $row) {
		$tags = $this->exts->getElements('.cell:not([class*="cell_header"]', $row);
		if(count($tags) >= 5 && $this->exts->getElement('a[href*="/invoice?"]', $tags[4]) != null) {
			$invoiceUrl = $this->exts->getElement('a[href*="/invoice?"]',$tags[4])->getAttribute("href");
			$invoiceName = trim($tags[1]->getAttribute('innerText'));
			$invoiceDate = trim($tags[0]->getAttribute('innerText'));
			$invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[2]->getAttribute('innerText')));

			if (strpos(urlencode(trim($tags[2]->getAttribute('innerText'))), '%C2%A3') !== false)
				$invoiceAmount = $invoiceAmount.' GBP'; 
			elseif (strpos(urlencode(trim($tags[2]->getAttribute('innerText'))), '%E2%82%AC') !== false)
				$invoiceAmount = $invoiceAmount.' EUR';
			else
				$invoiceAmount = $invoiceAmount.' USD';

			array_push($invoices, array(
				'invoiceName'=>$invoiceName,
				'invoiceDate'=>$invoiceDate,
				'invoiceAmount'=>$invoiceAmount,
				'invoiceUrl'=>$invoiceUrl
			));
			$this->isNoInvoice = false;
		}
	}

	if (count($rows) == 0) {
		$rows = $this->exts->getElements('div.configure-invoices-tab-component div.row');
		foreach ($rows as $row) {
			$tags = $this->exts->getElements('div.column', $row);
			if(count($tags) >= 5 && $this->exts->getElement('a[href*="/invoice?"]', $tags[4]) != null) {
				$invoiceUrl = $this->exts->getElement('a[href*="/invoice?"]',$tags[4])->getAttribute("href");
				$invoiceName = trim($tags[1]->getAttribute('innerText'));
				$invoiceDate = trim($tags[0]->getAttribute('innerText'));
				$invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[2]->getAttribute('innerText')));

				if (strpos(urlencode(trim($tags[2]->getAttribute('innerText'))), '%C2%A3') !== false)
					$invoiceAmount = $invoiceAmount.' GBP'; 
				elseif (strpos(urlencode(trim($tags[2]->getAttribute('innerText'))), '%E2%82%AC') !== false)
					$invoiceAmount = $invoiceAmount.' EUR';
				else
					$invoiceAmount = $invoiceAmount.' USD';

				array_push($invoices, array(
					'invoiceName'=>$invoiceName,
					'invoiceDate'=>$invoiceDate,
					'invoiceAmount'=>$invoiceAmount,
					'invoiceUrl'=>$invoiceUrl
				));
				$this->isNoInvoice = false;
			}
		}
	}

	if (count($rows) == 0) {
		$rows = $this->exts->getElements('tbody tr');
		foreach ($rows as $row) {
			$tags = $this->exts->getElements('td', $row);
			if(count($tags) >= 5 && $this->exts->getElement('a[href*="/invoice?"]', $tags[4]) != null) {
				$invoiceUrl = $this->exts->getElement('a[href*="/invoice?"]',$tags[4])->getAttribute("href");
				$invoiceName = trim($tags[1]->getAttribute('innerText'));
				$invoiceDate = trim($tags[0]->getAttribute('innerText'));
				$invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[2]->getAttribute('innerText')));

				if (strpos(urlencode(trim($tags[2]->getAttribute('innerText'))), '%C2%A3') !== false)
					$invoiceAmount = $invoiceAmount.' GBP'; 
				elseif (strpos(urlencode(trim($tags[2]->getAttribute('innerText'))), '%E2%82%AC') !== false)
					$invoiceAmount = $invoiceAmount.' EUR';
				else
					$invoiceAmount = $invoiceAmount.' USD';

				array_push($invoices, array(
					'invoiceName'=>$invoiceName,
					'invoiceDate'=>$invoiceDate,
					'invoiceAmount'=>$invoiceAmount,
					'invoiceUrl'=>$invoiceUrl
				));
				$this->isNoInvoice = false;
			}
		}
	}

	// Download all invoices
	$this->exts->log('Invoices found: '.count($invoices));
	foreach ($invoices as $invoice) {
		$this->exts->log('--------------------------');
		$this->exts->log('invoiceName: '.$invoice['invoiceName']);
		$this->exts->log('invoiceDate: '.$invoice['invoiceDate']);
		$this->exts->log('invoiceAmount: '.$invoice['invoiceAmount']);
		$this->exts->log('invoiceUrl: '.$invoice['invoiceUrl']);

		$invoiceFileName = $invoice['invoiceName'].'.pdf';
		
		$downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
		if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
			$this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
			sleep(1);
		} else {
			$this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
		}
	}
}