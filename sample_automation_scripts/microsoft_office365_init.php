public $base_url = 'https://www.office.com/?auth=2';
public $username_selector = 'input[name="loginfmt"]:not([aria-hidden="true"])';
public $password_selector = 'input[name="passwd"], #formsAuthenticationAreaPassword #loginForm  input[name="Password"]';
public $remember_me_selector = 'input[name="KMSI"] + span';
public $submit_login_selector = 'input[type="submit"]#idSIButton9, #formsAuthenticationAreaPassword #loginForm #submitButton';
public $isNoInvoice = true;
public $account_type = 0;
public $lang = '';
public $phone_number = '';
public $recovery_email = '';

/**
	* Entry Method thats called for a portal
	* @param Integer $count Number of times portal is retried.
	*/

private function initPortal($count)
{
	$this->exts->log('Begin initPortal ' . $count);
	$this->phone_number = isset($this->exts->config_array["phone_number"]) ? $this->exts->config_array["phone_number"] : '';
	$this->recovery_email = isset($this->exts->config_array["recovery_email"]) ? $this->exts->config_array["recovery_email"] : '';
	$this->account_type = isset($this->exts->config_array["ACCOUNT_TYPE"]) ? (int) @$this->exts->config_array["ACCOUNT_TYPE"] : 0;
	$this->lang = isset($this->exts->config_array["lang"]) ? trim($this->exts->config_array["lang"]) : '';
	// $this->exts->loadCookiesFromFile();
	$this->exts->clearCookies();
	$this->exts->log('account_type' . $this->account_type);
	sleep(1);
	$this->exts->openUrl($this->base_url);
	sleep(10);
	$this->exts->capture('1-init-page');

	// If user hase not logged in from cookie, clear cookie, open the login url and do login
	if (!$this->isLoggedIn()) {
		$this->exts->log('NOT logged via cookie');

		if ($this->exts->urlContains('BoxError.aspx?aspxerrorpath') || $this->isExists('body > div > #message > p a[href*="status.office"]') || (!$this->isExists($this->username_selector) && !$this->isExists($this->password_selector))) {
			$this->exts->clearCookies();
		}
		$this->clearChrome();
		sleep(1);
		$this->exts->openUrl($this->base_url);
		$this->checkFillLogin();
		$this->exts->waitTillPresent($this->username_selector);
		if ($this->isExists($this->username_selector)) {
			$this->exts->capture("2-login-page-reload");
			$this->checkFillLogin();
			sleep(15);
		}
		$this->checkExternalFillLogin();
		$this->checkConfirmButton();

		$this->checkTwoFactorMethod();
		$this->checkConfirmButton();
	}

	$this->doAfterLogin();
}


private function checkFillLogin()
{
	$this->exts->log(__FUNCTION__);
	// When open login page, sometime it show previous logged user, select login with other user.
	$this->exts->waitTillPresent('[role="listbox"] .row #otherTile[role="option"], div#otherTile', 20);
	if ($this->isExists('[role="listbox"] .row #otherTile[role="option"], div#otherTile')) {
		$this->exts->click_by_xdotool('[role="listbox"] .row #otherTile[role="option"], div#otherTile');
		sleep(10);
	}

	$this->exts->capture("2-microsoft-login-page");
	if ($this->exts->querySelector($this->username_selector) != null) {
		sleep(3);
		$this->exts->log("Enter microsoft Username");
		$this->exts->moveToElementAndType($this->username_selector, $this->username);
		sleep(1);
		$this->exts->moveToElementAndClick($this->submit_login_selector);
		sleep(10);
	}

	//Some user need to approve login after entering username on the app
	sleep(5);
	$this->approvetwofactorcode();

	if ($this->isExists('a[data-bind*="href: svr.urlSwitch"][href*="/logout"]')) {
		// if site show: Already login with .. account, click logout and login with other account
		$this->exts->click_by_xdotool('a[data-bind*="href: svr.urlSwitch"][href*="/logout"]');
		sleep(10);
	}
	if ($this->isExists('a#mso_account_tile_link, #aadTile, #msaTile')) {
		// if site show: This Email is being used with multiple Microsoft accounts, Select depending on account_type variable
		//if account type is 1 then only personal account will be selected otherwise business account.
		if ($this->account_type == 1) {
			$this->exts->click_by_xdotool('#msaTile');
		} else {
			$this->exts->click_by_xdotool('a#mso_account_tile_link, #aadTile');
		}
		sleep(10);
	}
	if ($this->isExists('form #idA_PWD_SwitchToPassword')) {
		$this->exts->click_by_xdotool('form #idA_PWD_SwitchToPassword');
		sleep(5);
	} else if ($this->isExists('#idA_PWD_SwitchToCredPicker')) {
		$this->exts->moveToElementAndClick('#idA_PWD_SwitchToCredPicker');
		sleep(5);
		$this->exts->moveToElementAndClick('[role="listitem"] img[src*="password"]');
		sleep(3);
	}


	if ($this->exts->querySelector($this->password_selector) != null) {
		$this->exts->log("Enter microsoft Password");
		$this->exts->moveToElementAndType($this->password_selector, $this->password);
		sleep(1);
		$this->exts->click_by_xdotool($this->remember_me_selector);
		sleep(2);
		$this->exts->capture("2-microsoft-password-page-filled");
		$this->exts->moveToElementAndClick($this->submit_login_selector);
		sleep(10);
		$this->exts->capture("2-microsoft-after-submit-password");
	} else {
		$this->exts->log(__FUNCTION__ . '::microsoft Password page not found');
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

private function approvetwofactorcode()
{
	//Some user need to approve login after entering username on the app
	if ($this->isExists('#idDiv_SAASTO_Title , div#idDiv_RemoteNGC_PollingDescription')) {
		$this->exts->two_factor_timeout = 5;
		$polling_message_selector = '#idDiv_SAASTO_Description , #idDiv_RemoteNGC_PollingDescription, #idRemoteNGC_DisplaySign, .confirmIdentityPageControl #iPollSessionDesc';
		$this->exts->two_factor_notif_msg_en = trim(join("\n", $this->exts->extract($polling_message_selector)));
		$this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . "\n>>>Enter \"OK\" after confirming on device";
		$this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en . "\n>>>Geben Sie danach hier unten \"OK\" ein.";

		$two_factor_code = trim($this->exts->fetchTwoFactorCode());
		if (!empty($two_factor_code) && trim($two_factor_code) != '') {
			if ($this->isExists($this->remember_me_selector)) {
				$this->exts->click_by_xdotool($this->remember_me_selector);
			}
			$this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);
			$this->exts->two_factor_timeout = 15;
		} else {
			if ($this->isExists('a#idA_PWD_SwitchToPassword')) {
				$this->exts->click_by_xdotool('a#idA_PWD_SwitchToPassword');
				sleep(5);
			} else {
				$this->exts->log("Not received two factor code");
			}
		}
	}
}
private function checkExternalFillLogin()
{
	$this->exts->log(__FUNCTION__);
	if ($this->exts->urlContains('balassalabs.com/')) {
		$this->exts->capture("2-login-external-page");
		if ($this->exts->getElement('input#userNameInput') != null) {
			sleep(3);
			$this->exts->log("Enter balassalabs Username");
			$this->exts->moveToElementAndType('input#userNameInput', $this->username);
			sleep(1);
			$this->exts->log("Enter balassalabs Password");
			$this->exts->moveToElementAndType('input#passwordInput', $this->password);
			sleep(1);
			$this->exts->capture("2-login-external-filled");
			$this->exts->click_by_xdotool('#submitButton');
			sleep(5);

			if ($this->exts->extract('#error #errorText') != '') {
				$this->exts->loginFailure(1);
			}
			sleep(15);
		}
	} else if ($this->exts->urlContains('idaptive.app/login')) {
		$this->exts->capture("2-login-external-page");
		if ($this->exts->getElement('#usernameForm:not(.hidden) input[name="username"]') != null) {
			sleep(3);
			$this->exts->log("Enter idaptive Username");
			$this->exts->moveToElementAndType('#usernameForm:not(.hidden) input[name="username"]', $this->username);
			sleep(1);
			$this->exts->click_by_xdotool('#usernameForm:not(.hidden) [type="submit"]');
			sleep(5);
		}
		if ($this->exts->getElement('#passwordForm:not(.hidden) input[name="answer"][type="password"]') != null) {
			$this->exts->log("Enter idaptive Password");
			$this->exts->moveToElementAndType('#passwordForm:not(.hidden) input[name="answer"][type="password"]', $this->password);
			sleep(1);
			$this->exts->click_by_xdotool('#passwordForm:not(.hidden ) [name="rememberMe"]');
			$this->exts->capture("2-login-external-filled");
			$this->exts->click_by_xdotool('#passwordForm:not(.hidden) [type="submit"]');
			sleep(5);
		}

		if ($this->exts->extract('#errorForm:not(.hidden) .error-message, #usernameForm:not(.hidden ) .error-message:not(.hidden )') != '') {
			$this->exts->loginFailure(1);
		}
		sleep(15);
	} else if ($this->exts->urlContains('noveldo.onelogin.com')) {
		$this->exts->capture("2-login-external-page");
		if ($this->exts->getElement('input[name="username"]') != null) {
			sleep(3);
			$this->exts->log("Enter noveldo Username");
			$this->exts->moveToElementAndType('input[name="username"]', $this->username);
			sleep(1);
			$this->exts->click_by_xdotool('button[type="submit"]');
			sleep(3);
		}
		if ($this->exts->getElement('input#password') != null) {
			$this->exts->log("Enter noveldo Password");
			$this->exts->moveToElementAndType('input#password', $this->password);
			sleep(1);
			$this->exts->capture("2-login-external-filled");
			$this->exts->click_by_xdotool('button[type="submit"]');
			sleep(3);
		}
		sleep(15);
	} else if ($this->exts->urlContains('godaddy.') && $this->exts->getElement('input#password') != null) {
		// $devTools = new Chrome\ChromeDevToolsDriver($this->exts->webdriver);
		// $data_siteKey = $devTools->execute( // This website getting redirect error when loading on linux-selenium environment, then we must do this command
		// 	'Network.setUserAgentOverride',
		// 	['userAgent' => 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/104.0.0.0 Safari/537.36', 'platform' => 'Win32']
		// );
		$this->exts->capture("2-godaddy-login-page");

		$this->exts->log("Enter godaddy Username");
		$this->exts->moveToElementAndType('input#username', $this->username);
		sleep(1);

		$this->exts->log("Enter godaddy Password");
		$this->exts->moveToElementAndType('input#password', $this->password);
		sleep(1);

		if ($this->isExists('input#remember-me:not(:checked)'))
			$this->exts->click_by_xdotool('label[for="remember-me"]');
		sleep(2);

		$this->exts->capture("2-login-godaddy-page-filled");
		$this->exts->click_by_xdotool('button#submitBtn');
		sleep(15);
	}
}
private function checkConfirmButton()
{
	// After submit password, It have many button can be showed, check and click it
	if ($this->isExists('form input[name="DontShowAgain"] + span')) {
		// if site show: Do this to reduce the number of times you are asked to sign in. Click yes
		$this->exts->click_by_xdotool('form input[name="DontShowAgain"] + span');
		sleep(10);
	}
	if ($this->isExists('input#btnAskLater')) {
		$this->exts->click_by_xdotool('input#btnAskLater');
		sleep(10);
	}
	if ($this->isExists('a[data-bind*=SkipMfaRegistration]')) {
		$this->exts->click_by_xdotool('a[data-bind*=SkipMfaRegistration]');
		sleep(10);
	}
	if ($this->isExists('input#idSIButton9[aria-describedby="KmsiDescription"]')) {
		$this->exts->click_by_xdotool('input#idSIButton9[aria-describedby="KmsiDescription"]');
		sleep(10);
	}
	if ($this->isExists('input#idSIButton9[aria-describedby*="landingDescription"]')) {
		$this->exts->click_by_xdotool('input#idSIButton9[aria-describedby*="landingDescription"]');
		sleep(3);
	}
	if ($this->exts->getElement("#verifySetup a#verifySetupCancel") != null) {
		$this->exts->click_by_xdotool("#verifySetup a#verifySetupCancel");
		sleep(10);
	}
	if ($this->exts->getElement('#authenticatorIntro a#iCancel') != null) {
		$this->exts->click_by_xdotool('#authenticatorIntro a#iCancel');
		sleep(10);
	}
	if ($this->exts->getElement("input#iLooksGood") != null) {
		$this->exts->click_by_xdotool("input#iLooksGood");
		sleep(10);
	}
	if ($this->exts->getElement("input#StartAction") != null) {
		$this->exts->click_by_xdotool("input#StartAction");
		sleep(10);
	}
	if ($this->exts->getElement(".recoveryCancelPageContainer input#iLandingViewAction") != null) {
		$this->exts->click_by_xdotool(".recoveryCancelPageContainer input#iLandingViewAction");
		sleep(10);
	}
	if ($this->exts->getElement("input#idSubmit_ProofUp_Redirect") != null) {
		$this->exts->click_by_xdotool("input#idSubmit_ProofUp_Redirect");
		sleep(10);
	}
	// if ($this->exts->urlContains('mysignins.microsoft.com/register') && $this->isExists('#id__11')) {
	//     // Great job! Your security information has been successfully set up. Click "Done" to continue login.
	//     $this->exts->click_by_xdotool(' #id__11');
	//     sleep(10);
	// }
	if ($this->exts->urlContains('mysignins.microsoft.com/register')) {
		$this->exts->account_not_ready();
		sleep(10);
	}

	if ($this->exts->getElement('div input#iNext') != null) {
		$this->exts->click_by_xdotool('div input#iNext');
		sleep(10);
	}
	if ($this->exts->getElement('input[value="Continue"]') != null) {
		$this->exts->click_by_xdotool('input[value="Continue"]');
		sleep(10);
	}
	if ($this->exts->getElement('form[action="/kmsi"] input#idSIButton9') != null) {
		$this->exts->click_by_xdotool('form[action="/kmsi"] input#idSIButton9');
		sleep(10);
	}
	if ($this->exts->getElement('a#CancelLinkButton') != null) {
		$this->exts->click_by_xdotool('a#CancelLinkButton');
		sleep(10);
	}
	if ($this->isExists('form[action*="/kmsi"] input[name="DontShowAgain"]')) {
		// if site show: Do this to reduce the number of times you are asked to sign in. Click yes
		$this->exts->click_by_xdotool('form[action*="/kmsi"] input[name="DontShowAgain"] + span');
		sleep(3);
		$this->exts->click_by_xdotool('form[action*="/kmsi"] input#idSIButton9');
		sleep(10);
	}
}
private function checkTwoFactorMethod()
{
	// Currently we met 4 two factor methods
	// - Email
	// - Text Message
	// - Approve request in Microsoft Authenticator app
	// - Use verification code from mobile app
	$this->exts->log(__FUNCTION__);
	// sleep(5);
	$this->exts->capture("2.0-two-factor-checking");
	// STEP 0 if it's hard to solve, so try back to choose list
	if (($this->isExists('[value="PhoneAppNotification"]') || $this->isExists('[value="CompanionAppsNotification"]')) && $this->isExists('a#signInAnotherWay')) {
		$this->exts->click_by_xdotool('a#signInAnotherWay');
		sleep(5);
	} else if ($this->isExists('#iTimeoutDesc') && $this->isExists('#iTimeoutOptionLink')) {
		$this->exts->click_by_xdotool('#iTimeoutOptionLink');
		sleep(5);
	} else if ($this->isExists('[data-bind*="login-confirm-send-view"] [type="submit"]')) {
		$this->exts->click_by_xdotool('[data-bind*="login-confirm-send-view"] [type="submit"]');
		sleep(5);
	}

	// STEP 1: Check if list of two factor methods showed, select first
	if ($this->isExists('#idDiv_SAOTCS_Proofs_Section #idDiv_SAOTCS_Proofs [role="option"]')) {
		if ($this->isExists('#idDiv_SAOTCS_Proofs_Section #idDiv_SAOTCS_Proofs [role="option"]:not([data-value*="Voice"])')) {
			$this->exts->click_by_xdotool('#idDiv_SAOTCS_Proofs_Section #idDiv_SAOTCS_Proofs [role="option"]:not([data-value*="Voice"])');
		} else {
			$this->exts->click_by_xdotool('#idDiv_SAOTCS_Proofs_Section #idDiv_SAOTCS_Proofs [role="option"]');
		}
		sleep(3);
	} else if ($this->isExists('#iProofList input[name="proof"]')) {
		$this->exts->click_by_xdotool('#iProofList input[name="proof"]');
		sleep(3);
	} else if ($this->isExists('#idDiv_SAOTCS_Proofs [role="listitem"]')) {
		// Updated 11-2020
		if ($this->isExists('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="PhoneAppOTP"]')) { // phone SMS
			$this->exts->click_by_xdotool('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="PhoneAppOTP"]');
		} else if ($this->isExists('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="OneWaySMS"]')) { // phone SMS
			$this->exts->click_by_xdotool('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="OneWaySMS"]');
		} else if ($this->isExists('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value^="3:"]')) { // phone SMS
			$this->exts->click_by_xdotool('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value^="3:"]');
		} else if ($this->isExists('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value^="1:"]')) { // Email 
			$this->exts->click_by_xdotool('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value^="1:"]');
		} else if ($this->isExists('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="PhoneAppOTP"]')) {
			$this->exts->click_by_xdotool('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="PhoneAppOTP"]');
		} else if ($this->isExists('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="PhoneAppNotification"]')) {
			$this->exts->click_by_xdotool('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="PhoneAppNotification"]');
		} else {
			$this->exts->click_by_xdotool('#idDiv_SAOTCS_Proofs [role="listitem"]');
		}
		sleep(5);
	}

	// STEP 2: (Optional)
	if ($this->isExists('#idDiv_RemoteNGC_PollingDescription, #idRemoteNGC_DisplaySign, .confirmIdentityPageControl #iPollSessionDesc')) {
		// If method is click some number on Microsoft Authenticator app, send 2FA to ask user to do click it
		$message_selector = '#idDiv_RemoteNGC_PollingDescription, #idRemoteNGC_DisplaySign, .confirmIdentityPageControl #iPollSessionDesc';
		$this->exts->two_factor_notif_msg_en = trim(join("\n", $this->exts->getElementsAttribute($message_selector, 'innerText')));
		$this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . "\n>>>Enter \"OK\" after confirming on device";
		$this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en . "\n>>>Geben Sie danach hier unten \"OK\" ein.";

		$this->exts->two_factor_attempts = 2;
		$this->fillTwoFactor('', '', '', '');
	} else if ($this->isExists('[data-bind*="Type.TOTPAuthenticatorV2"]')) {
		// If method is Microsoft Authenticator app: send 2FA to ask user approve on Microsoft app.
		// Then wait. If not success, click to select two factor by code from mobile app
		$input_selector = '';
		$message_selector = 'div#idDiv_SAOTCAS_Description';
		$remember_selector = 'label#idLbl_SAOTCAS_TD_Cb, #idChkBx_SAOTCAS_TD:not(:checked)';
		$submit_selector = '';
		$this->exts->two_factor_notif_msg_en = $this->exts->extract($message_selector, null, 'innerText');
		$this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . "\n>>>Enter \"OK\" after confirming on device";
		$this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
		$this->exts->two_factor_attempts = 2;
		$this->exts->two_factor_timeout = 5;
		$this->fillTwoFactor('', '', $remember_selector, $submit_selector);
		// sleep(30);

		if ($this->isExists('a#idA_SAASTO_TOTP')) {
			$this->exts->click_by_xdotool('a#idA_SAASTO_TOTP');
			sleep(5);
		}
	} else if ($this->isExists('input[value="TwoWayVoiceOffice"]') && $this->isExists('div#idDiv_SAOTCC_Description')) {
		// If method is Microsoft Authenticator app: send 2FA to ask user approve on Microsoft app.
		// Then wait. If not success, click to select two factor by code from mobile app
		$input_selector = '';
		$message_selector = 'div#idDiv_SAOTCC_Description';
		$this->exts->two_factor_notif_msg_en = $this->exts->extract($message_selector, null, 'innerText');
		$this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . "\n>>>Enter \"OK\" after confirming on device";
		$this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
		$this->exts->two_factor_attempts = 2;
		$this->exts->two_factor_timeout = 5;
		$this->fillTwoFactor('', '', '', '');
	} else if ($this->isExists('input[name="ProofConfirmation"]:not([type="hidden"]), .confirmIdentityPageControl [id^="iProof"][style*="display: block"] >  input[type="email"][name^="iProof"], .confirmIdentityPageControl [id^="iProof"][style*="display: table"] >  input[name^="iProof"][type="email"]:not([type="hidden"])')) {
		// If method is email code or phone code, This site may be ask for confirm phone/email first, So send 2FA to ask user phone/email
		$input_selector = 'input[name="ProofConfirmation"]:not([type="hidden"]), .confirmIdentityPageControl [id^="iProof"][style*="display: block"] >  input[type="email"][name^="iProof"], .confirmIdentityPageControl [id^="iProof"][style*="display: table"] >  input[name^="iProof"][type="email"]:not([type="hidden"])';
		$message_selector = '#idDiv_SAOTCS_ProofConfirmationDesc, #iAdditionalProofInfo #iEnterProofDesc, #iAdditionalProofInfo #iEnterProofDesc ~ * #iConfirmProofEmailDomain';
		$remember_selector = '';
		$submit_selector = 'input#idSubmit_SAOTCS_SendCode, input#iSelectProofAction[type="submit"]';
		$this->exts->two_factor_attempts = 1;
		if ($this->recovery_email != '' && filter_var($this->recovery_email, FILTER_VALIDATE_EMAIL) !== false) {
			$this->exts->moveToElementAndType($input_selector, $this->recovery_email);
			sleep(1);
			$this->exts->click_by_xdotool($submit_selector);
			sleep(5);
		} else {
			$this->exts->two_factor_attempts = 1;
			$this->fillTwoFactor($input_selector, $message_selector, $remember_selector, $submit_selector);
		}
	} else if ($this->isExists('input[name="ProofConfirmation"]:not([type="hidden"]), .confirmIdentityPageControl [id^="iProof"][style*="display: block"] >  input[type="tel"][name^="iProof"], .confirmIdentityPageControl [id^="iProof"][style*="display: table"] >  input[name^="iProof"][type="tel"]:not([type="hidden"]), #iProofPhoneEntry:not([aria-hidden="true"]) input[name^="iProof"][type="tel"]:not([type="hidden"])')) {
		// If method is phone code, This site may be ask for confirm phone first, So send 2FA to ask user phone
		$input_selector = 'input[name="ProofConfirmation"]:not([type="hidden"]), .confirmIdentityPageControl [id^="iProof"][style*="display: block"] >  input[type="tel"][name^="iProof"], .confirmIdentityPageControl [id^="iProof"][style*="display: table"] >  input[name^="iProof"][type="tel"]:not([type="hidden"]), #iProofPhoneEntry:not([aria-hidden="true"]) input[name^="iProof"][type="tel"]:not([type="hidden"])';
		$message_selector = '#idDiv_SAOTCS_ProofConfirmationDesc, #iAdditionalProofInfo #iEnterProofDesc, #iAdditionalProofInfo #iEnterProofDesc ~ * #iConfirmProofEmailDomain';
		$remember_selector = '';
		$submit_selector = 'input#idSubmit_SAOTCS_SendCode, input#iSelectProofAction[type="submit"]';
		$this->exts->two_factor_attempts = 1;
		if ($this->phone_number != '' && is_numeric(trim(substr($this->phone_number, -1, 4)))) {
			$last4digit = substr($this->phone_number, -1, 4);
			$this->exts->moveToElementAndType($input_selector, $last4digit);
			sleep(3);
			$this->exts->click_by_xdotool($submit_selector);
			sleep(5);
		} else {
			$this->exts->two_factor_attempts = 1;
			$this->fillTwoFactor($input_selector, $message_selector, $remember_selector, $submit_selector);
		}
	}

	// STEP 3: input code
	if ($this->isExists('input[name="otc"], input[name="iOttText"]')) {
		$input_selector = 'input[name="otc"], input[name="iOttText"]';
		$message_selector = 'div#idDiv_SAOTCC_Description, .OTTLabel, #idDiv_SAOTCC_Description';
		$remember_selector = 'label#idLbl_SAOTCC_TD_Cb';
		$submit_selector = 'input#idSubmit_SAOTCC_Continue, input#iVerifyCodeAction';
		$this->exts->two_factor_attempts = 0;
		$this->fillTwoFactor($input_selector, $message_selector, $remember_selector, $submit_selector);
	}
	// STEP 4: input code
	if ($this->isExists('#idDiv_SAASTO_Title , div#idDiv_RemoteNGC_PollingDescription')) {
		$this->approvetwofactorcode();
	}
}
private function fillTwoFactor($input_selector, $message_selector, $remember_selector, $submit_selector)
{
	$this->exts->log(__FUNCTION__);
	$this->exts->log("Two factor page found.");
	$this->exts->capture("2.1-two-factor-page");
	$this->exts->log($message_selector);
	if ($this->exts->getElement($message_selector) != null) {
		$this->exts->two_factor_notif_msg_en = join("\n", $this->exts->getElementsAttribute($message_selector, 'innerText'));
		$this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
		$this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
	}
	$this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
	$this->exts->notification_uid = "";

	$two_factor_code = trim($this->exts->fetchTwoFactorCode());
	if (empty($two_factor_code) || trim($two_factor_code) == '') {
		$this->exts->notification_uid = "";
		$two_factor_code = trim($this->exts->fetchTwoFactorCode());
	}
	if (!empty($two_factor_code) && trim($two_factor_code) != '') {
		if ($this->exts->getElement($input_selector) != null) {
			$this->exts->log("fillTwoFactor: Entering two_factor_code." . $two_factor_code);
			$this->exts->moveToElementAndType($input_selector, $two_factor_code);
			sleep(2);
			if ($this->isExists($remember_selector)) {
				$this->exts->click_by_xdotool($remember_selector);
			}
			$this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

			if ($this->isExists($submit_selector)) {
				$this->exts->log("fillTwoFactor: Clicking submit button.");
				$this->exts->click_by_xdotool($submit_selector);
			}
			sleep(15);

			if ($this->exts->getElement($input_selector) == null) {
				$this->exts->log("Two factor solved");
			} else if ($this->exts->two_factor_attempts < 3) {
				$this->exts->two_factor_attempts++;
				$this->exts->notification_uid = "";
				$this->fillTwoFactor($input_selector, $message_selector, $remember_selector, $submit_selector);
			} else {
				$this->exts->log("Two factor can not solved");
			}
		} else {
			$this->exts->log("Not found two factor input");
		}
	} else {
		$this->exts->log("Not received two factor code");
	}
}
private function isLoggedIn()
{
	sleep(50);
	$isLoggedIn = false;
	if ($this->exts->querySelector('button#O365_MainLink_Me #O365_MainLink_MePhoto, div.msame_Drop_signOut a, a[href*="/logout"]:not(#footerSignout), button#Admin') != null) {
		$isLoggedIn = true;
	} else if ($this->exts->querySelector('ul[role="menubar"] li button[data-value="billing"]') != null) {
		$isLoggedIn = true;
	} else if ($this->exts->querySelector('ul[role="menubar"] li button[data-value="Billing"]') != null) {
		$isLoggedIn = true;
	} else if ($this->exts->querySelector('button[aria-label="Settings and more"]') != null || $this->exts->querySelector('button[aria-label="Einstellungen und mehr"]') != null) {
		$isLoggedIn = true;
	} else if ($this->exts->querySelector('a[href="#/homepage"]') != null) {
		$isLoggedIn = true;
	}
	// if ($this->isExists('button#Admin')) {
	//     $this->exts->click_element('button#Admin');
	//     sleep(5);
	//     $tabs = $this->exts->get_all_tabs();
	//     if (count($tabs) > 1) {
	//         $this->exts->switchToNewestActiveTab();
	//         sleep(3);
	//     }
	// }
	return $isLoggedIn;
}

// Custom Exists function to check element found or not
private function isExists($selector = '')
{
	$safeSelector = addslashes($selector);
	$this->exts->log('Element:: ' . $safeSelector);
	$isElement = $this->exts->execute_javascript('!!document.querySelector("' . $safeSelector . '")');
	if ($isElement) {
		$this->exts->log('Element Found');
		return true;
	} else {
		$this->exts->log('Element not Found');
		return false;
	}
}

function select_invoice_filter_month($select_date)
{
	$this->exts->log('Selecting month: ' . Date('m-Y', $select_date));
	$this->exts->click_element('//button[contains(@data-automation-id, "dateFilterCommandBarButton")]/../following-sibling::*[1]//button');
	sleep(2);
	$current_year = (int) $this->exts->extract('button[class*="currentItemButton"]', null, 'innerText');
	if ((int) (Date('Y', $select_date)) < $current_year) {
		$this->exts->click_element('button[class*="currentItemButton"]');
		sleep(4);
		$this->exts->click_element('//button[contains(text(), "' . Date('Y', $select_date) . '")]');
		sleep(4);
	}
	$select_month = $this->exts->getElements('div[class*="monthPicker"] button[role="gridcell"]')[(int) (Date('n', $select_date)) - 1];
	$this->exts->click_element($select_month);
}

private function doAfterLogin()
{
	$this->exts->log(__FUNCTION__);
	if ($this->isExists('button#Admin')) {
		$this->exts->click_element('button#Admin');
		sleep(5);
		$tabs = $this->exts->get_all_tabs();
		if (count($tabs) > 1) {
			$this->exts->switchToNewestActiveTab();
			sleep(3);
		}
	}

	sleep(10);
	$titleText = strtolower($this->exts->extract('#idDiv_SAOTCAS_Title'));
	if (stripos($titleText, strtolower('Approve sign in request')) !== false) {
		$this->exts->click_element('#signInAnotherWay');
		sleep(7);
	}

	if ($this->exts->querySelector('a[id="signInAnotherWay"]') != null) {
		$this->exts->execute_javascript('document.querySelector("a#signInAnotherWay")?.click();');
		sleep(7);
	}

	sleep(5);
	if ($this->isExists('div[role="listitem"]:nth-child(2) .table-cell.text-left.content > div')) {
		$this->exts->click_element('div[role="listitem"]:nth-child(2) .table-cell.text-left.content > div');
	} else {
		sleep(5);
		if ($this->isExists('div[role="listitem"]:nth-child(3) .table-cell.text-left.content > div')) {
			$this->exts->click_element('div[role="listitem"]:nth-child(3) .table-cell.text-left.content > div');
		}
	}
	sleep(10);
	if ($this->isExists('input[name="otc"], input[name="iOttText"]')) {
		$input_selector = 'input[name="otc"], input[name="iOttText"]';
		$message_selector = 'div#idDiv_SAOTCC_Description, .OTTLabel, #idDiv_SAOTCC_Description';
		$remember_selector = 'label#idLbl_SAOTCC_TD_Cb';
		$submit_selector = 'input#idSubmit_SAOTCC_Continue, input#iVerifyCodeAction';
		$this->exts->two_factor_attempts = 0;
		$this->fillTwoFactor($input_selector, $message_selector, $remember_selector, $submit_selector);
	}

	sleep(30);
	$this->exts->log(__FUNCTION__);
	// then check user logged in or not
	if ($this->isLoggedIn()) {
		sleep(3);
		$this->exts->log(__FUNCTION__ . '::User logged in');
		$this->exts->capture("3-login-success");

		if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }
		
		$this->exts->success();
	} else {
		$this->exts->log(__FUNCTION__ . '::Use login failed ' . $this->exts->getUrl());


		$extractedMessage = $this->exts->extract('div[id="heading"].row.text-title');

		$this->exts->log(__FUNCTION__ . '::Error text: ' . $extractedMessage);
		if (stripos($extractedMessage, strtolower("account secure")) !== false) {
			$this->exts->account_not_ready();
		}

		if ($this->exts->getElementByText('div#passwordError', ['has been locked'], null, false)) {
			$this->exts->account_not_ready();
		}
		if ($this->exts->getElementByText('div#heading', ["You don't have access to this"], null, false)) {
			$this->exts->no_permission();
		}
		if ($this->isExists('input#newPassword, #AdditionalSecurityVerificationTabSpan, [data-automation-id="SecurityInfoRegister"], #idAccrualBirthdateSection[style*="display: block"]')) {
			$this->exts->account_not_ready();
		} else if ($this->exts->getElement('#passwordError a[href*="ResetPassword"], #passwordError a[href*="passwordreset"], #usernameError') != null) {
			$this->exts->loginFailure(1);
		} else if (strpos($this->exts->extract('#passwordError'), 'incorrect account or password') !== false || strpos($this->exts->extract('div[role="alert"]'), "code didn't work") !== false || strpos($this->exts->extract('div[role="alert"]'), "didn't enter the expected verification code") !== false) {
			$this->exts->loginFailure(1);
		} else {
			$this->exts->loginFailure();
		}
	}
}



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