<?php
// Server-Portal-ID: 5024 - Last modified: 21.01.2025 14:47:48 UTC - User: 1

public $baseUrl = 'https://account.microsoft.com/billing/orders';
// public $baseUrl = 'https://login.live.com/login.srf?wa=wsignin1.0&rpsnv=13&ct=1660203745&rver=7.0.6738.0&wp=MBI_SSL&wreply=https%3A%2F%2Faccount.microsoft.com%2Fauth%2Fcomplete-signin%3Fru%3Dhttps%253A%252F%252Faccount.microsoft.com%252Fbilling%252Forders&lc=1031&id=292666&lw=1&fl=easi2';
public $username_selector = 'input[name="loginfmt"]';
public $password_selector = 'input[name="passwd"]';
public $remember_me_selector = 'input[name="KMSI"] + span';
public $submit_login_selector = 'input[type="submit"]#idSIButton9, button[type="submit"]#idSIButton9';

public $invoice_only = 0;
public $phone_number = '';
public $recovery_email = '';
public $isNoInvoice = true;
/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);

    $this->phone_number = isset($this->exts->config_array["phone_number"]) ? $this->exts->config_array["phone_number"] : '';
    $this->recovery_email = isset($this->exts->config_array["recovery_email"]) ? $this->exts->config_array["recovery_email"] : '';
    $this->invoice_only = isset($this->exts->config_array["invoice_only"]) ? $this->exts->config_array["invoice_only"] : 0;
    // Load cookies
    $this->exts->loadCookiesFromFile();
    $this->exts->openUrl($this->baseUrl);
    sleep(2);
    if ($this->exts->urlContains('lumtest.com')) {
        $this->clearChrome();
        $this->exts->openUrl($this->baseUrl);
    }
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if (!$this->isLoggedIn()) {
        $this->exts->log('NOT logged via cookie');
        // $this->exts->clearCookies();
        // $this->exts->openUrl($this->baseUrl);
        // sleep(15);
        $this->checkFillLogin();
        sleep(17);
        $this->checkConfirmButton();
        $this->checkTwoFactorMethod();
        $this->checkConfirmButton();
        if ($this->exts->exists('button.ms-Dialog-button--close')) {
            $this->exts->click_by_xdotool('button.ms-Dialog-button--close');
            sleep(5);
        }
    }

    // then check user logged in or not
    if ($this->isLoggedIn()) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");
        $this->doAfterLogin();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        if ($this->exts->getElementByText('div#passwordError', ['has been locked'], null, false)) {
            $this->exts->account_not_ready();
        }
        if ($this->exts->getElementByText('div#heading', ["You don't have access to this"], null, false)) {
            $this->exts->no_permission();
        }
        if ($this->exts->exists('input#newPassword, #AdditionalSecurityVerificationTabSpan, [data-automation-id="SecurityInfoRegister"], #idAccrualBirthdateSection[style*="display: block"]')) {
            $this->exts->account_not_ready();
        }
        if ($this->exts->exists('input#newPassword')) {
            $this->exts->account_not_ready();
        } else if ($this->exts->exists('[action*="/profile/accrue"] #idAccrualBirthdateSection')) {
            $this->exts->account_not_ready();
        } else if ($this->exts->querySelector('#passwordError a[href*="ResetPassword"], input[name="loginfmt"]') != null) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}
private function checkFillLogin()
{
    $this->exts->log(__FUNCTION__);
    // When open login page, sometime it show previous logged user, select login with other user.
    if ($this->exts->exists('[role="listbox"] .row #otherTile[role="option"]')) {
        $this->exts->click_by_xdotool('[role="listbox"] .row #otherTile[role="option"]');
        sleep(10);
    }
    $this->exts->capture("2-login-page");
    if ($this->exts->querySelector($this->username_selector) != null) {
        sleep(3);
        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(4);
        $this->exts->click_by_xdotool($this->submit_login_selector);
        sleep(10);

        if ($this->exts->exists('button[type="submit"][aria-describedby="confirmSendTitle"]')) {
            $this->exts->click_by_xdotool('button[type="submit"][aria-describedby="confirmSendTitle"]');
        }

        if ($this->exts->exists('a#mso_account_tile_link, #aadTile, #msaTile')) {
            // if site show: This Email is being used with multiple Microsoft accounts, Select personal account
            $this->exts->click_by_xdotool('#msaTile');
            sleep(10);
        }

        //Some user need to approve login after entering username on the app
        if ($this->exts->exists('div#idDiv_RemoteNGC_PollingDescription')) {
            $this->exts->two_factor_timeout = 5;
            $polling_message_selector = '#idDiv_RemoteNGC_PollingDescription, #idRemoteNGC_DisplaySign, .confirmIdentityPageControl #iPollSessionDesc';
            $this->exts->two_factor_notif_msg_en = trim(join("\n", $this->exts->getElementsAttribute($polling_message_selector, 'innerText')));
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
        if ($this->exts->exists('form[action*="/common/login"] [role="listbox"] .row [role="option"]')) {
            // if site show: This Email is being used with multiple Microsoft accounts, Select first account
            $this->exts->click_by_xdotool('form[action*="/common/login"] [role="listbox"] .row [role="option"]');
            sleep(10);
        }
    }

    //If show verify email after enter username, choose login with password.
    if ($this->exts->allExists(['input#proofConfirmationText', 'a#idA_PWD_SwitchToCredPicker'])) {
        $this->exts->click_by_xdotool('a#idA_PWD_SwitchToCredPicker');
        sleep(2);
        $this->exts->click_by_xdotool('img[src*="password"]');
        sleep(3);
    }
    if ($this->exts->exists('span#idA_PWD_SwitchToCredPicker')) {
        $this->exts->click_by_xdotool('span#idA_PWD_SwitchToCredPicker');
        sleep(2);
        $this->exts->click_by_xdotool('img[src*="password"]');
        sleep(3);
    }
    if ($this->exts->exists('[id="idA_PWD_SwitchToPassword"]')) {
        $this->exts->click_by_xdotool('[id="idA_PWD_SwitchToPassword"]');
        sleep(3);
    }

    if ($this->exts->querySelector($this->password_selector) != null) {
        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(1);

        $this->exts->click_by_xdotool($this->remember_me_selector);
        sleep(2);

        $this->exts->capture("2-password-page-filled");
        $this->exts->click_by_xdotool($this->submit_login_selector);
        sleep(10);
    } else {
        $this->exts->log(__FUNCTION__ . '::Password page not found');
        // $this->exts->capture("2-password-page-not-found");
    }
}
private function checkConfirmButton()
{
    // After submit password, It have many button can be showed, check and click it
    if ($this->exts->exists('form input[name="DontShowAgain"] + span')) {
        // if site show: Do this to reduce the number of times you are asked to sign in. Click yes
        $this->exts->click_by_xdotool('form input[name="DontShowAgain"] + span');
        sleep(7);
    }
    if ($this->exts->exists('input#btnAskLater')) {
        $this->exts->click_by_xdotool('input#btnAskLater');
        sleep(7);
    }
    if ($this->exts->exists('a[data-bind*=SkipMfaRegistration]')) {
        $this->exts->click_by_xdotool('a[data-bind*=SkipMfaRegistration]');
        sleep(7);
    }
    if ($this->exts->exists('input#idSIButton9[aria-describedby="KmsiDescription"]')) {
        $this->exts->click_by_xdotool('input#idSIButton9[aria-describedby="KmsiDescription"]');
        sleep(7);
    }
    if ($this->exts->exists('input#idSIButton9[aria-describedby*="landingDescription"]')) {
        $this->exts->click_by_xdotool('input#idSIButton9[aria-describedby*="landingDescription"]');
        sleep(3);
    }
    if ($this->exts->querySelector("#verifySetup a#verifySetupCancel") != null) {
        $this->exts->click_by_xdotool("#verifySetup a#verifySetupCancel");
        sleep(7);
    }
    if ($this->exts->querySelector('#authenticatorIntro a#iCancel') != null) {
        $this->exts->click_by_xdotool('#authenticatorIntro a#iCancel');
        sleep(7);
    }
    if ($this->exts->querySelector("input#iLooksGood") != null) {
        $this->exts->click_by_xdotool("input#iLooksGood");
        sleep(7);
    }
    if ($this->exts->exists("input#StartAction") && !$this->exts->urlContains('/Abuse?')) {
        $this->exts->click_by_xdotool("input#StartAction");
        sleep(7);
    }
    if ($this->exts->querySelector(".recoveryCancelPageContainer input#iLandingViewAction") != null) {
        $this->exts->click_by_xdotool(".recoveryCancelPageContainer input#iLandingViewAction");
        sleep(7);
    }
    if ($this->exts->querySelector("input#idSubmit_ProofUp_Redirect") != null) {
        $this->exts->click_by_xdotool("input#idSubmit_ProofUp_Redirect");
        sleep(7);
    }
    if ($this->exts->urlContains('mysignins.microsoft.com/register') && $this->exts->exists('#id__11')) {
        // Great job! Your security information has been successfully set up. Click "Done" to continue login.
        $this->exts->click_by_xdotool(' #id__11');
        sleep(7);
    }
    if ($this->exts->querySelector('div input#iNext') != null) {
        $this->exts->click_by_xdotool('div input#iNext');
        sleep(7);
    }
    if ($this->exts->querySelector('input[value="Continue"]') != null) {
        $this->exts->click_by_xdotool('input[value="Continue"]');
        sleep(5);
    }
    if ($this->exts->getElement('//button//*[contains(text(), "Continue")]', null, 'xpath')) {
        $this->exts->click_element('//button//*[contains(text(), "Continue")]');
        sleep(5);
    }
    if ($this->exts->querySelector('form[action="/kmsi"] input#idSIButton9, form[action*="/ProcessAuth"] input#idSIButton9') != null) {
        $this->exts->click_by_xdotool('form[action="/kmsi"] input#idSIButton9, form[action*="/ProcessAuth"] input#idSIButton9');
        sleep(5);
    }
    if ($this->exts->querySelector('a#CancelLinkButton') != null) {
        $this->exts->click_by_xdotool('a#CancelLinkButton');
        sleep(5);
    }
    if ($this->exts->exists('form[action*="/kmsi"] input[name="DontShowAgain"]')) {
        // if site show: Do this to reduce the number of times you are asked to sign in. Click yes
        $this->exts->click_by_xdotool('form[action*="/kmsi"] input[name="DontShowAgain"] + span');
        sleep(3);
        $this->exts->click_by_xdotool('form[action*="/kmsi"] input#idSIButton9');
        sleep(5);
    }
    if ($this->exts->exists('button#acceptButton')) {
        $this->exts->click_by_xdotool('button#acceptButton');
        sleep(5);
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
    if (($this->exts->exists('[value="PhoneAppNotification"]') || $this->exts->exists('[data-bind*="session-approval-view"]')) && $this->exts->exists('a#signInAnotherWay')) {
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
        if ($this->exts->exists('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="OneWaySMS"]')) { // phone SMS
            $this->exts->click_by_xdotool('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="OneWaySMS"]');
        } else if ($this->exts->exists('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value^="3:"]')) { // phone SMS
            $this->exts->click_by_xdotool('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value^="3:"]');
        } else if ($this->exts->exists('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value^="1:"]')) { // Email 
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
    if ($this->exts->exists('#idDiv_RemoteNGC_PollingDescription, #idRemoteNGC_DisplaySign, .confirmIdentityPageControl #iPollSessionDesc, div[id="pollingDescription"]')) {
        // If method is click some number on Microsoft Authenticator app, send 2FA to ask user to do click it
        $message_selector = '#idDiv_RemoteNGC_PollingDescription, #idRemoteNGC_DisplaySign, .confirmIdentityPageControl #iPollSessionDesc, span[id="displaySign"], div[id="pollingDescription"]';
        $this->exts->two_factor_notif_msg_en = trim(join("\n", $this->exts->getElementsAttribute($message_selector, 'innerText')));
        $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . "\n>>>Enter \"OK\" after confirming on device";
        $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en . "\n>>>Geben Sie danach hier unten \"OK\" ein.";

        $this->exts->two_factor_attempts = 2;
        $this->fillTwoFactor('', '', '', '');
    } else if ($this->exts->exists('[data-bind*="Type.TOTPAuthenticatorV2"]') || $this->exts->exists('[data-bind*="session-approval-view"]')) {
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
        if ($this->exts->exists('input[id="iProofEmail"]')) {
            $email = $this->username;
            $parts = explode('@', $email);

            $username = $parts[0];
            sleep(5);
            $this->exts->moveToElementAndType('input[id="iProofEmail"]', $username);
            sleep(3);
            $this->exts->click_by_xdotool('input[id="iSelectProofAction"]');
        }
        // If method is email code or phone code, This site may be ask for confirm phone/email first, So send 2FA to ask user phone/email
        $input_selector = 'input[name="ProofConfirmation"]:not([type="hidden"]), .confirmIdentityPageControl [id^="iProof"][style*="display: block"] >  input[type="email"][name^="iProof"], .confirmIdentityPageControl [id^="iProof"][style*="display: table"] >  input[name^="iProof"][type="email"]:not([type="hidden"]), input[id="iOttText"]';
        $message_selector = '#idDiv_SAOTCS_ProofConfirmationDesc, #iAdditionalProofInfo #iEnterProofDesc, #iAdditionalProofInfo #iEnterProofDesc ~ * #iConfirmProofEmailDomain, div[id="iEnterSubhead"]';
        $remember_selector = '';
        $submit_selector = 'input#idSubmit_SAOTCS_SendCode, input#iSelectProofAction[type="submit"], input[type="submit"], input[id="iVerifyCodeAction"]';
        $this->exts->two_factor_attempts = 1;
        if ($this->recovery_email != '' && filter_var($this->recovery_email, FILTER_VALIDATE_EMAIL) !== false) {
            $this->exts->moveToElementAndType($input_selector, $this->recovery_email);
            sleep(1);
            $this->exts->click_by_xdotool($submit_selector);
            sleep(5);
            if ($this->exts->exists('div#proofConfirmationErrorMsg, div#iProofInputError')) {
                $this->exts->two_factor_attempts = 1;
                $this->fillTwoFactor($input_selector, $message_selector, $remember_selector, $submit_selector);
            }
        } else {
            $this->exts->two_factor_attempts = 1;
            $this->fillTwoFactor($input_selector, $message_selector, $remember_selector, $submit_selector);
        }
    } else if ($this->exts->exists('input[name="ProofConfirmation"]:not([type="hidden"]), .confirmIdentityPageControl [id^="iProof"][style*="display: block"] >  input[type="tel"][name^="iProof"], .confirmIdentityPageControl [id^="iProof"][style*="display: table"] >  input[name^="iProof"][type="tel"]:not([type="hidden"]), #iProofPhoneEntry:not([aria-hidden="true"]) input[name^="iProof"][type="tel"]:not([type="hidden"])')) {
        // If method is phone code, This site may be ask for confirm phone first, So send 2FA to ask user phone
        $input_selector = 'input[name="ProofConfirmation"]:not([type="hidden"]), .confirmIdentityPageControl [id^="iProof"][style*="display: block"] >  input[type="tel"][name^="iProof"], .confirmIdentityPageControl [id^="iProof"][style*="display: table"] >  input[name^="iProof"][type="tel"]:not([type="hidden"]), #iProofPhoneEntry:not([aria-hidden="true"]) input[name^="iProof"][type="tel"]:not([type="hidden"])';
        $message_selector = '#idDiv_SAOTCS_ProofConfirmationDesc, #iAdditionalProofInfo #iEnterProofDesc, #iAdditionalProofInfo #iEnterProofDesc ~ * #iConfirmProofEmailDomain';
        $remember_selector = '';
        $submit_selector = 'input#idSubmit_SAOTCS_SendCode, input#iSelectProofAction[type="submit"], input[type="submit"]';
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
        $message_selector = 'div#idDiv_SAOTCC_Description, .OTTLabel, #idDiv_SAOTCC_Description, span#otcDesc';
        $remember_selector = 'label#idLbl_SAOTCC_TD_Cb';
        $submit_selector = 'input#idSubmit_SAOTCC_Continue, input#iVerifyCodeAction, input[type="submit"]';
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
    if ($this->exts->querySelector($message_selector) != null) {
        $this->exts->two_factor_notif_msg_en = join("\n", $this->exts->getElementsAttribute($message_selector, 'innerText'));
        $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
        $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
    }
    $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
    $this->notification_uid = "";

    $two_factor_code = trim($this->exts->fetchTwoFactorCode());
    if (!empty($two_factor_code) && trim($two_factor_code) != '') {
        if ($this->exts->querySelector($input_selector) != null) {
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

            if ($this->exts->querySelector($input_selector) == null) {
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
    return $this->exts->exists('button#O365_MainLink_Me #O365_MainLink_MePhoto, div.msame_Drop_signOut a, a[href*="/logout"]:not(#footerSignout), header a[href*="/Logoff"], div[id*="headerPicture"]');
}
private function clearChrome()
{
    $this->exts->log("Clearing browser history, cookie, cache");
    $this->exts->openUrl('chrome://settings/clearBrowserData');
    sleep(10);
    $this->exts->capture("clear-page");
    for ($i = 0; $i < 2; $i++) {
        $this->exts->type_key_by_xdotool('Tab');
    }
    $this->exts->type_key_by_xdotool('Tab');
    $this->exts->type_key_by_xdotool('Return');
    $this->exts->type_key_by_xdotool('a');
    sleep(1);
    $this->exts->type_key_by_xdotool('Return');
    sleep(3);
    $this->exts->capture("clear-page");
    for ($i = 0; $i < 5; $i++) {
        $this->exts->type_key_by_xdotool('Tab');
    }
    $this->exts->type_key_by_xdotool('Return');
    sleep(15);
    $this->exts->capture("after-clear");
}

private function doAfterLogin()
{
    $this->exts->log(__FUNCTION__);
    $this->exts->openUrl('https://account.microsoft.com/billing/orders/?period=AllTime&type=All');
    sleep(20);

     if (!$this->isLoggedIn()) {
        $this->checkFillLogin();
            sleep(17);
        $this->checkConfirmButton();
        $this->checkTwoFactorMethod();
        $this->checkConfirmButton();
        if ($this->exts->exists('button.ms-Dialog-button--close')) {
            $this->exts->click_by_xdotool('button.ms-Dialog-button--close');
            sleep(5);
        }
    }

    $this->downloadInvoices();

    //Download personal invoices
    $this->exts->openUrl('https://admin.cloud.microsoft/?#/billoverview/invoice-list');
    $this->processInvoicesInAdmin();
    //Download organization invoices
    $this->exts->click_by_xdotool('button[data-automation-id="BillsAndPayments,InvoiceList,ShowBillingAccountPickerFlyoutLink"]');
    $this->exts->waitTillPresent('div[data-automationid="DetailsRowCheck"]', 30);
    if (count($this->exts->querySelectorAll('div[data-automationid="DetailsRowCheck"]')) > 1) {
        $this->exts->click_element($this->exts->querySelectorAll('div[data-automationid="DetailsRowCheck"]')[1]);
        sleep(3);
        $this->exts->click_by_xdotool('button[data-automation-id="BillsAndPayments,InvoiceList,BillingAccountPickerFlyoutRegion,BillingAccountPickerFlyout_PrimaryButtonDetailsPanelV2"]');
        sleep(15);
        $this->processInvoicesInAdmin();
    }

    // Final, check no invoice
    if ($this->isNoInvoice) {
        $this->exts->no_invoice();
    }

    $this->exts->success();
}
private function downloadInvoices()
{
    $this->exts->log(__FUNCTION__);
    sleep(20);
    $this->exts->capture("4-invoices");
    $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
    if ($restrictPages == 0) {
        $this->exts->log('Trying to scroll to bottom');
        $this->exts->execute_javascript('window.scrollTo(0,document.body.scrollHeight);');
        sleep(15);
    }

    // $order_urls = $this->exts->getElementsAttribute('order-card a[href*="/orders/details?"][href*="orderId="]', 'href');
    // UPDATED 2020-Jul-10
    // Since unknow reason, selenium webdriver get element face error "element is not attached" even when current page do not reload.


    // (function () {
    //     var data = [];
    //     var links = document.querySelectorAll('order-card a[href*="/orders/details?"][href*="orderId="]');
    //     for (var i = 0; i < links.length; i++) {
    //         data.push(links[i].href);
    //     }
    //     console.log(data); // or return data if needed
    // })();

    $getUrls = $this->exts->evaluate('(function () {
        var data = [];
        var links = document.querySelectorAll("order-card a[href*=\'/orders/details?\'][href*=\'orderId=\']");
        for (var i = 0; i < links.length; i++) {
            data.push(links[i].href);
        }
        return data;
        })();');

    $orderData = json_decode($getUrls, true);

  
    // $order_urls = $orderData['result']['result']['value'];
    $order_urls = $orderData['result']['result']['value'];
     


    $this->exts->log('order_urls count: '. count($order_urls));

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
                $this->exts->switchToNewestActiveTab();
                $this->checkFillLogin();
                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                $this->exts->switchToInitTab();
                sleep(2);
                $this->exts->closeAllTabsButThis();
            } else if ((int)@$this->invoice_only != 1) {
                $downloaded_file = $this->exts->download_current($invoiceFileName, 1);
            }

            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
            $this->isNoInvoice = false;

            $this->exts->switchToInitTab();
            sleep(2);
            $this->exts->closeAllTabsButThis();
        } else {
            $this->exts->log(__FUNCTION__ . '::Seem this is not invoice detail ' . $order_url);
        }
    }

    // Updated 2022-01-31
    if (count($order_urls) == 0) {
        $orders = $this->exts->getElements('#order-history-wrapper #order-history-page-header + div + div > div[id]');
        foreach ($orders as $order) {
            $invoiceName = $order->getAttribute('id');
            $invoiceFileName = $invoiceName . '.pdf';
            $invoiceDate = '';

            $this->exts->log('invoiceName: ' . $invoiceName);
            $this->exts->log('invoiceDate: ' . $invoiceDate);
            $this->isNoInvoice = false;
            if ($this->exts->invoice_exists($invoiceName)) {
                $this->exts->log('Invoice existed ' . $invoiceFileName);
                continue;
            }

            $tax_invoice_button = $this->exts->getElement('[data-bi-id="orders-tax-invoice"]', $order);
            if ($tax_invoice_button != null) {
                $this->exts->click_element($tax_invoice_button);
                sleep(5);
                // then check if new tab opened, switch to new tab
                $this->exts->switchToNewestActiveTab();
                $this->checkFillLogin();
                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceName, '', '', $downloaded_file);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
                $this->exts->switchToInitTab();
                sleep(2);
                $this->exts->closeAllTabsButThis();
            } else if ((int)@$this->invoice_only != 1) {
                $print_order_detail_button = $this->exts->getElement('[data-bi-id="order-history-print-invoice"]', $order);
                if ($print_order_detail_button = null) {
                    $this->exts->click_element('[id^="order-details-"] [data-icon-name="ChevronDownMed"]');
                    sleep(2);
                }
                $print_order_detail_button = $this->exts->getElement('[data-bi-id="order-history-print-invoice"]', $order);
                if ($print_order_detail_button != null) {
                    $this->exts->click_element($print_order_detail_button);
                    sleep(5);
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, '', '', $downloaded_file);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
                }
            }
        }
    }
}

private function processInvoicesInAdmin()
{
    $this->exts->waitTillPresent('div[data-automation-id="ListInvoiceList"] div[data-automationid="DetailsRow"]');
    $this->exts->capture("4-admin-invoices-page");
    $invoices = [];

    $rows = $this->exts->querySelectorAll('div[data-automation-id="ListInvoiceList"] div[data-automationid="DetailsRow"]');
    foreach ($rows as $index => $row) {
        $tags = $this->exts->querySelectorAll('div[data-automationid="DetailsRowCell"]', $row);
        if (count($tags) >= 6 && $this->exts->querySelector('div[data-automation-key="downloadPdf"] button', $row) != null) {
            $invoiceSelector = $this->exts->querySelector('div[data-automation-key="downloadPdf"] button', $row);
            $this->exts->execute_javascript("arguments[0].setAttribute('id', 'custom-pdf-download-button-" . $index . "');", [$invoiceSelector]);

            $invoiceName = trim($tags[0]->getText());
            $invoiceDate = trim($tags[1]->getText());
            $this->exts->log('Date before parsed: ' . $invoiceDate);
            $invoiceDate = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');
            $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[2]->getText())) . ' EUR';

            $this->isNoInvoice = false;
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoiceName);
            $this->exts->log('invoiceDate: ' . $invoiceDate);
            $this->exts->log('invoiceAmount: ' . $invoiceAmount);
            $invoiceFileName = $invoiceName . '.pdf';

            $this->exts->click_by_xdotool("button#custom-pdf-download-button-" . $index);
            $this->exts->wait_and_check_download('pdf');
            $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                // Create new invoice if it not exisited
                if ($this->exts->invoice_exists($invoiceName)) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                } else {
                    $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                    sleep(1);
                }
            } else {
                $this->exts->log('Timeout when download ' . $invoiceFileName);
            }
        }
    }
}