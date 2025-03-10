<?php // added download code
// Server-Portal-ID: 391 - Last modified: 27.02.2025 12:47:13 UTC - User: 1

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
    $this->account_type = isset($this->exts->config_array["account_type"]) ? (int) @$this->exts->config_array["account_type"] : 0;
    $this->lang = isset($this->exts->config_array["lang"]) ? trim($this->exts->config_array["lang"]) : '';
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->base_url);
    sleep(10);
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if (!$this->isLoggedIn()) {
        $this->exts->log('NOT logged via cookie');
        if ($this->exts->urlContains('BoxError.aspx?aspxerrorpath') || $this->exts->exists('body > div > #message > p a[href*="status.office"]') || (!$this->exts->exists($this->username_selector) && !$this->exts->exists($this->password_selector))) {
            $this->exts->clearCookies();
        }
        sleep(1);
        $this->exts->openUrl($this->base_url);
        $this->checkFillLogin();
        $this->exts->waitTillPresent($this->username_selector);
        if ($this->exts->exists($this->username_selector)) {
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
    if ($this->exts->exists('[role="listbox"] .row #otherTile[role="option"], div#otherTile')) {
        $this->exts->click_by_xdotool('[role="listbox"] .row #otherTile[role="option"], div#otherTile');
        sleep(10);
    }

    $this->exts->capture("2-microsoft-login-page");
    if ($this->exts->querySelector($this->username_selector) != null) {
        sleep(3);
        $this->exts->log("Enter microsoft Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(1);
        $this->exts->click_by_xdotool($this->submit_login_selector);
        sleep(10);
    }

    //Some user need to approve login after entering username on the app
    if ($this->exts->exists('div#idDiv_RemoteNGC_PollingDescription')) {
        $this->exts->two_factor_timeout = 5;
        $polling_message_selector = '#idDiv_RemoteNGC_PollingDescription, #idRemoteNGC_DisplaySign, .confirmIdentityPageControl #iPollSessionDesc';
        $this->exts->two_factor_notif_msg_en = trim(join("\n", $this->exts->extract($polling_message_selector)));
        $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . "\n>>>Enter \"OK\" after confirming on device";
        $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en . "\n>>>Geben Sie danach hier unten \"OK\" ein.";

        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if (!empty($two_factor_code) && trim($two_factor_code) != '') {
            if ($this->exts->exists($this->remember_me_selector)) {
                $this->exts->click_by_xdotool($this->remember_me_selector);
            }
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);
            $this->exts->two_factor_timeout = 15;
        } else {
            if ($this->exts->exists('a#idA_PWD_SwitchToPassword')) {
                $this->exts->click_by_xdotool('a#idA_PWD_SwitchToPassword');
                sleep(5);
            } else {
                $this->exts->log("Not received two factor code");
            }
        }
    }
    if ($this->exts->exists('a[data-bind*="href: svr.urlSwitch"][href*="/logout"]')) {
        // if site show: Already login with .. account, click logout and login with other account
        $this->exts->click_by_xdotool('a[data-bind*="href: svr.urlSwitch"][href*="/logout"]');
        sleep(10);
    }
    if ($this->exts->exists('a#mso_account_tile_link, #aadTile, #msaTile')) {
        // if site show: This Email is being used with multiple Microsoft accounts, Select depending on account_type variable
        //if account type is 1 then only personal account will be selected otherwise business account.
        if ($this->account_type == 1) {
            $this->exts->click_by_xdotool('#msaTile');
        } else {
            $this->exts->click_by_xdotool('a#mso_account_tile_link, #aadTile');
        }
        sleep(10);
    }
    if ($this->exts->exists('form #idA_PWD_SwitchToPassword')) {
        $this->exts->click_by_xdotool('form #idA_PWD_SwitchToPassword');
        sleep(5);
    } else if ($this->exts->exists('#idA_PWD_SwitchToCredPicker')) {
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
        $this->exts->click_by_xdotool($this->submit_login_selector);
        sleep(10);
        $this->exts->capture("2-microsoft-after-submit-password");
    } else {
        $this->exts->log(__FUNCTION__ . '::microsoft Password page not found');
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

        if ($this->exts->exists('input#remember-me:not(:checked)'))
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
    if ($this->exts->exists('form input[name="DontShowAgain"] + span')) {
        // if site show: Do this to reduce the number of times you are asked to sign in. Click yes
        $this->exts->click_by_xdotool('form input[name="DontShowAgain"] + span');
        sleep(10);
    }
    if ($this->exts->exists('input#btnAskLater')) {
        $this->exts->click_by_xdotool('input#btnAskLater');
        sleep(10);
    }
    if ($this->exts->exists('a[data-bind*=SkipMfaRegistration]')) {
        $this->exts->click_by_xdotool('a[data-bind*=SkipMfaRegistration]');
        sleep(10);
    }
    if ($this->exts->exists('input#idSIButton9[aria-describedby="KmsiDescription"]')) {
        $this->exts->click_by_xdotool('input#idSIButton9[aria-describedby="KmsiDescription"]');
        sleep(10);
    }
    if ($this->exts->exists('input#idSIButton9[aria-describedby*="landingDescription"]')) {
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
    if ($this->exts->urlContains('mysignins.microsoft.com/register') && $this->exts->exists('#id__11')) {
        // Great job! Your security information has been successfully set up. Click "Done" to continue login.
        $this->exts->click_by_xdotool(' #id__11');
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
    if ($this->exts->exists('form[action*="/kmsi"] input[name="DontShowAgain"]')) {
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
    if (($this->exts->exists('[value="PhoneAppNotification"]') || $this->exts->exists('[value="CompanionAppsNotification"]')) && $this->exts->exists('a#signInAnotherWay')) {
        $this->exts->click_by_xdotool('a#signInAnotherWay');
        sleep(5);
    } else if ($this->exts->exists('#iTimeoutDesc') && $this->exts->exists('#iTimeoutOptionLink')) {
        $this->exts->click_by_xdotool('#iTimeoutOptionLink');
        sleep(5);
    } else if ($this->exts->exists('[data-bind*="login-confirm-send-view"] [type="submit"]')) {
        $this->exts->click_by_xdotool('[data-bind*="login-confirm-send-view"] [type="submit"]');
        sleep(5);
    }

    // STEP 1: Check if list of two factor methods showed, select first
    if ($this->exts->exists('#idDiv_SAOTCS_Proofs_Section #idDiv_SAOTCS_Proofs [role="option"]')) {
        if ($this->exts->exists('#idDiv_SAOTCS_Proofs_Section #idDiv_SAOTCS_Proofs [role="option"]:not([data-value*="Voice"])')) {
            $this->exts->click_by_xdotool('#idDiv_SAOTCS_Proofs_Section #idDiv_SAOTCS_Proofs [role="option"]:not([data-value*="Voice"])');
        } else {
            $this->exts->click_by_xdotool('#idDiv_SAOTCS_Proofs_Section #idDiv_SAOTCS_Proofs [role="option"]');
        }
        sleep(3);
    } else if ($this->exts->exists('#iProofList input[name="proof"]')) {
        $this->exts->click_by_xdotool('#iProofList input[name="proof"]');
        sleep(3);
    } else if ($this->exts->exists('#idDiv_SAOTCS_Proofs [role="listitem"]')) {
        // Updated 11-2020
        if ($this->exts->exists('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="OneWaySMS"]')) {// phone SMS
            $this->exts->click_by_xdotool('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="OneWaySMS"]');
        } else if ($this->exts->exists('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value^="3:"]')) {// phone SMS
            $this->exts->click_by_xdotool('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value^="3:"]');
        } else if ($this->exts->exists('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value^="1:"]')) {// Email 
            $this->exts->click_by_xdotool('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value^="1:"]');
        } else if ($this->exts->exists('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="PhoneAppOTP"]')) {
            $this->exts->click_by_xdotool('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="PhoneAppOTP"]');
        } else if ($this->exts->exists('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="PhoneAppNotification"]')) {
            $this->exts->click_by_xdotool('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="PhoneAppNotification"]');
        } else {
            $this->exts->click_by_xdotool('#idDiv_SAOTCS_Proofs [role="listitem"]');
        }
        sleep(5);
    }

    // STEP 2: (Optional)
    if ($this->exts->exists('#idDiv_RemoteNGC_PollingDescription, #idRemoteNGC_DisplaySign, .confirmIdentityPageControl #iPollSessionDesc')) {
        // If method is click some number on Microsoft Authenticator app, send 2FA to ask user to do click it
        $message_selector = '#idDiv_RemoteNGC_PollingDescription, #idRemoteNGC_DisplaySign, .confirmIdentityPageControl #iPollSessionDesc';
        $this->exts->two_factor_notif_msg_en = trim(join("\n", $this->exts->getElementsAttribute($message_selector, 'innerText')));
        $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . "\n>>>Enter \"OK\" after confirming on device";
        $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en . "\n>>>Geben Sie danach hier unten \"OK\" ein.";

        $this->exts->two_factor_attempts = 2;
        $this->fillTwoFactor('', '', '', '');
    } else if ($this->exts->exists('[data-bind*="Type.TOTPAuthenticatorV2"]')) {
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

        if ($this->exts->exists('a#idA_SAASTO_TOTP')) {
            $this->exts->click_by_xdotool('a#idA_SAASTO_TOTP');
            sleep(5);
        }
    } else if ($this->exts->exists('input[value="TwoWayVoiceOffice"]') && $this->exts->exists('div#idDiv_SAOTCC_Description')) {
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

    } else if ($this->exts->exists('input[name="ProofConfirmation"]:not([type="hidden"]), .confirmIdentityPageControl [id^="iProof"][style*="display: block"] >  input[type="email"][name^="iProof"], .confirmIdentityPageControl [id^="iProof"][style*="display: table"] >  input[name^="iProof"][type="email"]:not([type="hidden"])')) {
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
    } else if ($this->exts->exists('input[name="ProofConfirmation"]:not([type="hidden"]), .confirmIdentityPageControl [id^="iProof"][style*="display: block"] >  input[type="tel"][name^="iProof"], .confirmIdentityPageControl [id^="iProof"][style*="display: table"] >  input[name^="iProof"][type="tel"]:not([type="hidden"]), #iProofPhoneEntry:not([aria-hidden="true"]) input[name^="iProof"][type="tel"]:not([type="hidden"])')) {
        // If method is phone code, This site may be ask for confirm phone first, So send 2FA to ask user phone
        $input_selector = 'input[name="ProofConfirmation"]:not([type="hidden"]), .confirmIdentityPageControl [id^="iProof"][style*="display: block"] >  input[type="tel"][name^="iProof"], .confirmIdentityPageControl [id^="iProof"][style*="display: table"] >  input[name^="iProof"][type="tel"]:not([type="hidden"]), #iProofPhoneEntry:not([aria-hidden="true"]) input[name^="iProof"][type="tel"]:not([type="hidden"])';
        $message_selector = '#idDiv_SAOTCS_ProofConfirmationDesc, #iAdditionalProofInfo #iEnterProofDesc, #iAdditionalProofInfo #iEnterProofDesc ~ * #iConfirmProofEmailDomain';
        $remember_selector = '';
        $submit_selector = 'input#idSubmit_SAOTCS_SendCode, input#iSelectProofAction[type="submit"]';
        $this->exts->two_factor_attempts = 1;
        if ($this->phone_number != '' && is_numeric(trim(substr($this->phone_number,   - 1,  4)))) {
            $last4digit = substr($this->phone_number,   - 1,  4);
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
    if ($this->exts->exists('input[name="otc"], input[name="iOttText"]')) {
        $input_selector = 'input[name="otc"], input[name="iOttText"]';
        $message_selector = 'div#idDiv_SAOTCC_Description, .OTTLabel, #idDiv_SAOTCC_Description';
        $remember_selector = 'label#idLbl_SAOTCC_TD_Cb';
        $submit_selector = 'input#idSubmit_SAOTCC_Continue, input#iVerifyCodeAction';
        $this->exts->two_factor_attempts = 0;
        $this->fillTwoFactor($input_selector, $message_selector, $remember_selector, $submit_selector);
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
    $this->notification_uid = "";

    $two_factor_code = trim($this->exts->fetchTwoFactorCode());
    if (empty($two_factor_code) || trim($two_factor_code) == '') {
        $this->notification_uid = "";
        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
    }
    if (!empty($two_factor_code) && trim($two_factor_code) != '') {
        if ($this->exts->getElement($input_selector) != null) {
            $this->exts->log("fillTwoFactor: Entering two_factor_code." . $two_factor_code);
            $this->exts->moveToElementAndType($input_selector, $two_factor_code);
            sleep(2);
            if ($this->exts->exists($remember_selector)) {
                $this->exts->click_by_xdotool($remember_selector);
            }
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

            if ($this->exts->exists($submit_selector)) {
                $this->exts->log("fillTwoFactor: Clicking submit button.");
                $this->exts->click_by_xdotool($submit_selector);
            }
            sleep(15);

            if ($this->exts->getElement($input_selector) == null) {
                $this->exts->log("Two factor solved");
            } else if ($this->exts->two_factor_attempts < 3) {
                $this->exts->two_factor_attempts++;
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
    return $this->exts->exists('button#O365_MainLink_Me #O365_MainLink_MePhoto, div.msame_Drop_signOut a, a[href*="/logout"]:not(#footerSignout)') ||
        $this->exts->exists('ul[role="menubar"] li button[data-value="billing"]') || $this->exts->exists('ul[role="menubar"] li button[data-value="Billing"]');
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
    sleep(15);
    $this->exts->log(__FUNCTION__);
    // then check user logged in or not
    if ($this->isLoggedIn()) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");
        if ($this->exts->exists('#O365_NavHeader button#O365_MainLink_NavMenu')) {
            $this->exts->click_by_xdotool('#O365_NavHeader button#O365_MainLink_NavMenu');
            sleep(2);
        }
        sleep(15);
        

        if ($this->exts->exists('a#ShellAdmin_link[href*="admin.microsoft.com"], #appsModule a[href*="admin.microsoft.com"]')) {
            $this->exts->log('This user is office 365 user');
            $admin_url = $this->exts->extract('a#ShellAdmin_link[href*="admin.microsoft.com"], #appsModule a[href*="admin.microsoft.com"]', null, 'href');
            $this->exts->openUrl($admin_url);
            // $this->exts->openUrl('https://admin.microsoft.com/Adminportal/Home');
            sleep(10);
            if ($this->exts->exists($this->username_selector)) {
                $this->checkFillLogin();
                sleep(6);
            }
            $this->checkConfirmButton();
            if (!$this->exts->exists('ul[role="menubar"] li button[data-value="billing"], ul[role="menubar"] li button[data-value="Billing"]')) {
                $this->exts->click_by_xdotool('ul[role="menubar"] li button[data-value="showMoreClick"], button[data-value="ToggleNavCollapse"]');
                sleep(2);
            }
            $this->exts->click_by_xdotool('ul[role="menubar"] li button[data-value="billing"], ul[role="menubar"] li button[data-value="Billing"]');
            sleep(5);

            $this->exts->click_by_xdotool('li li a[data-value="billoverview"]');
            sleep(10);

            //Wait more if iframe is loading
            if (!$this->exts->exists('iframe#SFBIFrame, iframe#ContainerFrame, [data-automation-id="ListInvoiceList"]')) {
                sleep(30);
            }
            //Sometime we need to wait for more than a min.
            if (!$this->exts->exists('iframe#SFBIFrame, iframe#ContainerFrame, [data-automation-id="ListInvoiceList"]')) {
                $this->exts->update_process_lock();
                sleep(60);
            }

            if ($this->exts->exists('iframe#SFBIFrame')) {
                $this->downloadInvoices();
            } else if ($this->exts->exists('#adminAppRoot:not([class*="hide"]) iframe#ContainerFrame')) {
                $this->downloadInvoicesClassic();
            } else {
                if ($this->exts->config_array['restrictPages'] != '0') {
                    $this->processInvoicesNew();
                } else {
                    // Download min 2 year invoice if restrictpages == 0 (confirmation from Leader)

                    // Step 1: Choose custom date from date filter dropdown
                    $this->exts->click_by_xdotool('button[data-automation-id*="dateFilterCommandBarButton"]');
                    $this->exts->click_by_xdotool('button[data-automation-id*="CustomCommandBarButton"]');

                    // Invoices only displayed in a batch of maximum 6 months, So if we want to go back 2 year, We have to step by 4 batchs
                    // Calculate month included current month, so we minus 5,11,17,23 months instead of 6,12,18,24
                    $this->select_invoice_filter_month(strtotime('-5 months'));
                    $this->processInvoicesNew();
                    $this->select_invoice_filter_month(strtotime('-11 months'));
                    $this->processInvoicesNew();
                    $this->select_invoice_filter_month(strtotime('-17 months'));
                    $this->processInvoicesNew();
                    $this->select_invoice_filter_month(strtotime('-23 months'));
                    $this->processInvoicesNew();
                }
            }
        } else if ($this->exts->exists('a[href*="account.microsoft.com"]')) {
            $this->exts->log('This user is account.microsoft.com user');
            $this->exts->openUrl('https://account.microsoft.com/billing/orders/?lang=' . $this->lang);
            sleep(7);
            $this->checkFillLogin();
            sleep(5);
            $this->downloadAccountOrders();
        }
        
        
        $this->exts->openUrl('https://admin.cloud.microsoft/?#/homepage');

        $this->exts->waitTillPresent('button[data-automation-id="LeftNavBillingNavToggler"]');

        if($this->exts->exists('button[data-automation-id="LeftNavBillingNavToggler"]')){
            $this->exts->moveToElementAndClick('a[href="#/billoverview"]');
            $this->exts->waitTillPresent('div[class*="entityInnerHost"]');
            if($this->exts->exists('div[class*="entityInnerHost"]')){
                $this->exts->moveToElementAndClick('button[class*="closeButton"]');
            }

           $this->invoiceAndPayment();
        }
        // Final, check no invoice
        if ($this->isNoInvoice) {
            $this->exts->no_invoice();
        }
        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed ' . $this->exts->getUrl());
        if ($this->exts->getElementByText('div#passwordError', ['has been locked'], null, false)) {
            $this->exts->account_not_ready();
        }
        if ($this->exts->getElementByText('div#heading', ["You don't have access to this"], null, false)) {
            $this->exts->no_permission();
        }
        if ($this->exts->exists('input#newPassword, #AdditionalSecurityVerificationTabSpan, [data-automation-id="SecurityInfoRegister"], #idAccrualBirthdateSection[style*="display: block"]')) {
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

public function invoiceAndPayment()
{
    $this->exts->log(__FUNCTION__);
    $this->exts->waitTillPresent('div[class*="ms-DetailsRow-fields"]');

    $invoices = [];
    $rows = $this->exts->getElements('div[class*="ms-DetailsRow-fields"]');
    foreach ($rows as $row) {
        if ($this->exts->getElement('button[type="button"]', $row) != null) {
            $invoiceName = $this->exts->extract('data-automation-key="id"', $row, 'innerText');
            $invoiceDate = $this->exts->extract('div[data-automation-key="invoiceDate"]', $row, 'innerText');
            $invoiceAmount = $this->exts->extract('div[data-automation-key="totalAmount"]', $row, 'innerText');
            $downloadBtn = $this->exts->getElement('div[data-automation-key="downloadPdf"] button[type="button"]', $row);
           

            array_push($invoices, array(
                'invoiceName' => $invoiceName,
                'invoiceDate' => $invoiceDate,
                'invoiceAmount' => $invoiceAmount,
                'invoiceUrl' => $invoiceUrl,
                'downloadBtn' => $downloadBtn
            ));
            $this->isNoInvoice = false;
        } 
    }

    // Download all invoices
    $this->exts->log('Invoices found: ' . count($invoices));
    foreach ($invoices as $invoice) {
        $this->exts->log('--------------------------');
        $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
        $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
        $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
        $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

        $invoiceFileName = $invoice['invoiceName'] . '.pdf';
        $parsed_date = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
        if ($parsed_date == '') {
            $parsed_date = $this->exts->parse_date($invoice['invoiceDate'], 'm/d/Y', 'Y-m-d');
        }
        $this->exts->log('Date parsed: ' . $parsed_date);

        $downloaded_file = $this->exts->click_and_download($invoice['downloadBtn'], 'pdf', $invoiceFileName);

		if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
			$this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
			sleep(1);
		} else {
			$this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
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
private function downloadInvoices()
{
    $this->exts->log(__FUNCTION__);
    $this->waitTillPresent('iframe#SFBIFrame', 30);
    $this->switchToFrame('iframe#SFBIFrame');
    sleep(5);
    if ($this->exts->exists('app-invoice-list fab-dropdown .ms-Dropdown')) {
        $this->exts->click_by_xdotool('app-invoice-list fab-dropdown .ms-Dropdown');
        sleep(5);
        $this->exts->click_by_xdotool('[role="listbox"].ms-Dropdown-items button[title*="6 "]');
    }
    $this->exts->waitTillPresent('fab-details-list .ms-List-page > .ms-List-cell');
    $this->exts->capture("4-invoices");
    $invoices = [];

    $rows = count($this->exts->getElements('fab-details-list .ms-List-page > .ms-List-cell'));
    for ($i = 0; $i < $rows; $i++) {
        $row = $this->exts->getElements('fab-details-list .ms-List-page > .ms-List-cell')[$i];
        $tags = $this->exts->getElements('[role="gridcell"]', $row);
        if (count($tags) > 3) {
            $invoiceName = trim($tags[0]->getAttribute('innerText'));
            $invoiceFileName = $invoiceName . '.pdf';
            $invoiceDate = trim($this->exts->extract('[data-automation-key="invoiceDate"]', $row, 'innerText'));
            $amountText = trim($this->exts->extract('[data-automation-key="invoiceBilledAmount"]', $row, 'innerText'));
            $invoiceAmount = preg_replace('/[^\d\.\,]/', '', $amountText);
            if (stripos($amountText, 'A$') !== false) {
                $invoiceAmount = $invoiceAmount . ' AUD';
            } else if (stripos($amountText, '$') !== false) {
                $invoiceAmount = $invoiceAmount . ' USD';
            } else if (stripos(urlencode($amountText), '%C2%A3') !== false) {
                $invoiceAmount = $invoiceAmount . ' GBP';
            } else {
                $invoiceAmount = $invoiceAmount . ' EUR';
            }

            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoiceName);
            $this->exts->log('invoiceDate: ' . $invoiceDate);
            $this->exts->log('invoiceAmount: ' . $invoiceAmount);
            $parsed_date = $this->exts->parse_date($invoiceDate, 'd.m.y', 'Y-m-d');
            if ($parsed_date == '') {
                $parsed_date = $this->exts->parse_date($invoiceDate, 'n/j/y', 'Y-m-d');
            }
            if ($parsed_date == '') {
                $parsed_date = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');
            }
            $parsed_date == '' ? $this->exts->parse_date($invoiceDate, 'd/m/Y', 'Y-m-d') : $parsed_date;
            $this->exts->log('Date parsed: ' . $parsed_date);

            // Download invoice if it not exisited
            if ($this->exts->invoice_exists($invoiceName)) {
                $this->exts->log('Invoice existed ' . $invoiceFileName);
                continue;
            }

            // [data-automation-key="pdfDownload"]
            $more_vertical_button = $this->exts->getElement('fab-icon-button [data-icon-name="MoreVertical"]', $row);
            try {
                $this->exts->log('Click button More');
                $more_vertical_button->click();
            } catch (\Exception $exception) {
                $this->exts->log('Click button More by javascript');
                $this->exts->executeSafeScript("arguments[0].click()", [$more_vertical_button]);
            }
            sleep(5);
            $this->exts->click_by_xdotool('.ms-ContextualMenu-list.is-open button[data-bi-id="download_invoice"]');
            sleep(5);
            // then check if new tab opened, switch to new tab, then login if it required
            $handles = $this->exts->get_all_tabs();
            if (count($handles) > 1) {
                $this->exts->switchToTab(end($handles));
            }
            $this->checkFillLogin(0);

            $this->exts->wait_and_check_download('pdf');
            $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
            // $handles = $this->exts->webdriver->getWindowHandles();
            // $this->exts->webdriver->switchTo()->window($handles[0]);

            $this->exts->switchToInitTab();
            $this->exts->closeAllTabsButThis();
            sleep(1);
            $this->isNoInvoice = false;

            if ($this->exts->exists('iframe#SFBIFrame')) {
                $this->switchToFrame('iframe#SFBIFrame');
            }
        }
    }
}
private function downloadInvoicesClassic()
{
    $this->exts->log(__FUNCTION__);
    $this->exts->waitTillPresent('iframe#ContainerFrame', 30);
    if ($this->exts->exists('iframe#ContainerFrame')) {
        $this->switchToFrame('iframe#ContainerFrame');
    }
    $this->exts->click_by_xdotool('select#DateRangeDropDown');
    sleep(1);
    $this->exts->click_by_xdotool('select#DateRangeDropDown option[value="-7"]');

    // JS FALLBACK
    $this->exts->execute_javascript('
        let selectBox = document.querySelector("select#DateRangeDropDown");
        selectBox.value = "-7";
        selectBox.dispatchEvent(new Event("change"));
    ');
    // $this->exts->changeSelectbox('select#DateRangeDropDown', '-7');
    $this->exts->click_by_xdotool('a#View');
    $this->exts->waitTillPresent('#mainContent table > tbody > tr', 30);
    $this->exts->capture("4-invoices-classic");

    $invoices = [];
    $rows = $this->exts->getElements('#mainContent table > tbody > tr');
    foreach ($rows as $row) {
        if ($this->exts->getElement('tr', $row) == null) {
            if ($this->exts->getElement('a[id$="pdflink"]', $row) != null) {
                $invoiceName = '';
                $invoiceDate = '';
                $invoiceUrl = $this->exts->getElement('a[id$="pdflink"]', $row)->getAttribute("href");

                if ($this->exts->getElement('.//span[contains(@id, "_BillingDate")]/..', $row, 'xpath') != null) {
                    $invoiceDate = $this->exts->getElement('.//span[contains(@id, "_BillingDate")]/..', $row, 'xpath')->getAttribute('innerText');
                    $invoiceDate = trim(end(explode(':', $invoiceDate)));
                }
                if ($this->exts->getElement('.//span[contains(@id, "_OrderNumber")]/..', $row, 'xpath') != null) {
                    $invoiceName = $this->exts->getElement('.//span[contains(@id, "_OrderNumber")]/..', $row, 'xpath')->getAttribute('innerText');
                    $invoiceName = trim(end(explode(':', $invoiceName)));
                }
                if ($invoiceName != '' && $invoiceDate != '') {
                    $invoiceName = $invoiceName . '_' . preg_replace("/[^\w]/", '', $invoiceDate);
                } else {
                    $detail_text = $this->exts->extract('a[onclick*="showBillDetails"]', $row, 'onclick');
                    $detail_text = stripos("')", end(stripos('showBillDetails(', $detail_text)))[0];
                    $invoiceName = trim(end(stripos("'", $detail_text)));
                }
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->exts->extract('.BOX-MainTertiary', $row, 'innerText'))) . ' EUR';

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl
                ));
                $this->isNoInvoice = false;
            }
        }
    }

    // Download all invoices
    $this->exts->log('Invoices found: ' . count($invoices));
    foreach ($invoices as $invoice) {
        $this->exts->log('--------------------------');
        $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
        $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
        $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
        $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

        $invoiceFileName = $invoice['invoiceName'] . '.pdf';
        $parsed_date = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
        if ($parsed_date == '') {
            $parsed_date = $this->exts->parse_date($invoice['invoiceDate'], 'm/d/Y', 'Y-m-d');
        }
        $this->exts->log('Date parsed: ' . $parsed_date);

        $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
        if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
            $this->exts->new_invoice($invoice['invoiceName'], $parsed_date, $invoice['invoiceAmount'], $invoiceFileName);
            sleep(1);
        } else {
            $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
        }

        if ($this->exts->exists('iframe#ContainerFrame')) {
            $this->switchToFrame('iframe#ContainerFrame');
        }
        // sleep(7);
    }
}
private function downloadAccountOrders()
{
    $this->exts->log(__FUNCTION__);
    $this->exts->waitTillPresent('order-card a[href*="/orders/details?"][href*="orderId="]', 30);
    $this->exts->capture("4-account-orders");
    $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;
    if ($restrictPages == 0) {
        $this->exts->click_by_xdotool('li#AllTime');
        sleep(5);
        $this->exts->log('Trying to scroll to bottom');
        $this->exts->webdriver->executeScript('window.scrollTo(0,document.body.scrollHeight);');
        sleep(15);
    }

    $order_urls = $this->exts->getElementsAttribute('order-card a[href*="/orders/details?"][href*="orderId="]', 'href');
    foreach ($order_urls as $key => $order_url) {
        $this->exts->log('--------------------------');
        $this->exts->log('Order url: ' . $order_url);
        $this->exts->openUrl($order_url);
        sleep(10);
        // Check to make sure current content is order detail
        if ($this->exts->exists('a#order-details-print')) {
            $invoiceName = explode(
                '&',
                array_pop(explode('orderId=', $order_url))
            )[0];
            $invoiceFileName = $invoiceName . '.pdf';
            $invoiceDate = trim($this->exts->extract('.order-date h5', null, 'innerText'));
            $amountText = trim($this->exts->extract('tr:last-child td.cost.ng-binding', null, 'innerText'));
            $invoiceAmount = preg_replace('/[^\d\.\,]/', '', $amountText);
            if (stripos($amountText, 'A$') !== false) {
                $invoiceAmount = $invoiceAmount . ' AUD';
            } else if (stripos($amountText, '$') !== false) {
                $invoiceAmount = $invoiceAmount . ' USD';
            } else if (stripos(urlencode($amountText), '%C2%A3') !== false) {
                $invoiceAmount = $invoiceAmount . ' GBP';
            } else {
                $invoiceAmount = $invoiceAmount . ' EUR';
            }

            $this->exts->log('invoiceName: ' . $invoiceName);
            $this->exts->log('invoiceDate: ' . $invoiceDate);
            $this->exts->log('invoiceAmount: ' . $invoiceAmount);
            $parsed_date = $this->exts->parse_date($invoiceDate, 'j# F Y', 'Y-m-d');
            if ($parsed_date == '') {
                $parsed_date = $this->exts->parse_date($invoiceDate, 'F j# Y', 'Y-m-d');
            }
            $this->exts->log('Date parsed: ' . $parsed_date);

            if ($this->exts->invoice_exists($invoiceName)) {
                $this->exts->log('Invoice existed ' . $invoiceFileName);
                continue;
            }

            if ($this->exts->exists('a#orders-tax-invoice')) {
                $this->exts->click_by_xdotool('a#orders-tax-invoice');
                sleep(10);
                // then check if new tab opened, switch to new tab
                $handles = $this->exts->get_all_tabs();
                if (count($handles) > 1) {
                    $this->exts->switchToTab(end($handles));
                }
                $this->checkFillLogin(0);
                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                if (count($handles) > 1) {
                    $this->exts->webdriver->close();
                }
            } else {
                $downloaded_file = $this->exts->download_current($invoiceFileName, 1);
            }

            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
            $this->isNoInvoice = false;

            $handles = $this->exts->get_all_tabs();
            $this->exts->switchToTab($handles[0]);
            sleep(1);
        } else {
            $this->exts->log(__FUNCTION__ . '::Seem this is not invoice detail ' . $order_url);
        }
    }
}
private function processInvoicesNew($pageCount = 1)
{
    $this->exts->waitTillPresent('.ms-List-cell', 30);
    $this->exts->capture("4-invoices-page");
    $invoices = [];

    $rows = $this->exts->getElements('.ms-List-cell');
    foreach ($rows as $row) {
        $downloaded_button = $this->exts->getElement('div[data-automation-key="downloadPdf"] button', $row);

        $invoiceName = trim($this->exts->extract('span[class*="actionFieldNameWrapper"]', $row, 'innerText'));
        $invoiceFileName = $invoiceName . '.pdf';
        $invoiceDate = trim($this->exts->extract('div[data-automation-key="invoiceDate"]', $row, 'innerText'));

        $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->exts->extract('[data-automation-key="totalAmount"]', $row, 'innerText'))) . ' EUR';

        $this->exts->log('--------------------------');
        $this->exts->log('invoiceName: ' . $invoiceName);
        $this->exts->log('invoiceDate: ' . $invoiceDate);
        $this->exts->log('invoiceAmount: ' . $invoiceAmount);
        $parsed_date = is_null($invoiceDate) ? null : $this->exts->parse_date($invoiceDate, 'm/d/Y', 'Y-m-d');
        $this->exts->log('Date parsed: ' . $parsed_date);
        $this->isNoInvoice = false;
        // Download invoice if it not exisited
        if ($this->exts->invoice_exists($invoiceName)) {
            $this->exts->log('Invoice existed ' . $invoiceFileName);
        } else {
            $this->exts->click_element($downloaded_button);
            $this->exts->wait_and_check_download('pdf');
            $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
    }
}