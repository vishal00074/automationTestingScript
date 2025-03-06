public $baseUrl = 'https://account.adobe.com/';
public $loginUrl = 'https://account.adobe.com/';
public $invoicePageUrl = 'https://account.adobe.com/';

public $username_selector = 'input#EmailPage-EmailField';
public $username_readonly_selector = 'form#adobeid_signin input#adobeid_username[readonly]';
public $next_button = 'button[data-id="EmailPage-ContinueButton"]';
public $password_selector = 'input#PasswordPage-PasswordField';
public $remember_me_selector = '';
public $submit_login_selector = 'button[data-id="PasswordPage-ContinueButton"]';

public $check_login_failed_selector = 'label[data-id="PasswordPage-PasswordField-Error"], label[data-id="EmailPage-EmailField-Error"]';
public $check_login_success_selector = 'a[data-profile="sign-out"], button[data-menu-id="profile"], main [data-e2e="plan-card-payment-invoice-btn"]';

public $isNoInvoice = true;
public $login_with_google = 0;

/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->login_with_google = isset($this->exts->config_array["login_with_google"]) ? (int)$this->exts->config_array["login_with_google"] : $this->login_with_google;

    $this->exts->openUrl($this->baseUrl);
    sleep(10);
    // Choose Account
    if ($this->exts->exists('[data-id="PP-ProfileChooser-Chooser"] [data-id="PP-ProfileChooser-AuthAccount"]')) {
        $this->exts->capture("x-profile-selection-page-3");

        $this->exts->moveToElementAndClick('div[data-id="PP-ProfileChooser-AuthAccount"] > div:last-child');
        sleep(10);
    }
    $this->exts->loadCookiesFromFile(true);
    sleep(5);
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->getElement($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->openUrl($this->loginUrl);
        sleep(3);
        $this->checkFillLogin();

        // Choose Account
        $this->exts->waitTillPresent('[data-id="PP-ProfileChooser-Chooser"] [data-id="PP-ProfileChooser-AuthAccount"]');

        if ($this->exts->exists('[data-id="PP-ProfileChooser-Chooser"] [data-id="PP-ProfileChooser-AuthAccount"]')) {
            $this->exts->capture("x-profile-selection-page-3");

            if ($this->exts->exists('div[data-id="PP-ProfileChooser-AuthAccount"] > div:last-child')) {
                $this->exts->moveToElementAndClick('div[data-id="PP-ProfileChooser-AuthAccount"] > div:last-child');
                sleep(10);
            }
        }
        
        sleep(5);
        $this->exts->two_factor_attempts++;
        $this->exts->notification_uid = "";
        $this->checkFillTwoFactor();
        sleep(15);
        if ($this->exts->getElement('button[id*="-accept-btn-handler"]') != null) {
            $this->exts->moveToElementAndClick('button[id*="-accept-btn-handler"]');
            sleep(2);
        }
        if ($this->exts->getElement('button#_evidon-accept-button') != null) {
            $this->exts->moveToElementAndClick('button#_evidon-accept-button');
            sleep(2);
        }
        if ($this->exts->getElement('button[data-id="PP-RecordMarketingConsent-ContinueBtn"]') != null) {
            $this->exts->moveToElementAndClick('button[data-id="PP-RecordMarketingConsent-ContinueBtn"]');
            sleep(5);
        }

        if ($this->exts->exists('iframe[name="external-action"]')) {
            $this->exts->makeFrameExecutable('iframe[name="external-action"]')->click_element('div[data-testid="footer-view-component-button-group"] > button:first-child');
            sleep(5);
        }

        if ($this->exts->getElement('button[data-id="PP-AddSecondaryEmail-skip-btn"]') != null) {
            $this->exts->moveToElementAndClick('button[data-id="PP-AddSecondaryEmail-skip-btn"]');
            sleep(5);
        }
        if ($this->exts->getElement('button[data-id="PP-TermsOfUse-ContinueBtn"]') != null) {
            $this->exts->moveToElementAndClick('button[data-id="PP-TermsOfUse-ContinueBtn"]');
            sleep(10);
        }
        if ($this->exts->exists('button[data-id="PasswordlessOptInPP-continue-button"]')) {
            $this->exts->moveToElementAndClick('button[data-id="PasswordlessOptInPP-continue-button"]');
            sleep(10);
        }
        if ($this->exts->getElement('button#cancelBtn') != null) {
            $this->exts->moveToElementAndClick('button#cancelBtn');
            sleep(10);
        }
        if ($this->exts->getElement('#tos button[name="Submit"], #tos .checkbox-mark.needsclick') != null) {
            $this->exts->moveToElementAndClick('#tos .checkbox-mark.needsclick');
            sleep(2);
            $this->exts->moveToElementAndClick('#tos button[name="Submit"]');
            sleep(10);
        }
        if ($this->exts->exists('[data-id="PP-T2E-AssetMigration-Introduction"] [data-id="PP-T2E-AssetMigration-Introduction-ContinueButton"]')) {
            $this->exts->moveToElementAndClick('[data-id="PP-T2E-AssetMigration-Introduction"] [data-id="PP-T2E-AssetMigration-Introduction-ContinueButton"]');
            sleep(5);
            $this->exts->moveToElementAndClick('[data-id="PP-T2E-AssetMigration"] .T2EAssetMigration__chooser .ActionList-Item');
            sleep(5);
           }
        if ($this->exts->exists('[data-id="PP-T2E-ProfilesSetup-Introduction-ContinueButton"]')) {
            $this->exts->moveToElementAndClick('[data-id="PP-T2E-ProfilesSetup-Introduction-ContinueButton"]');
            sleep(10);
        }
        if ($this->exts->exists('button[data-id="PP-T2EInviteIntroduction-SkipBtn"]')) {
            $this->exts->moveToElementAndClick('button[data-id="PP-T2EInviteIntroduction-SkipBtn"]');
            sleep(10);
        }

        if ($this->exts->exists('[data-id="PP-ProfileChooser-Chooser"] [data-id="PP-ProfileChooser-AuthAccount"], [data-id="AccountChooser-AccountList-individual"]')) {
            $this->exts->type_key_by_xdotool('Return');
            sleep(2);
            $this->exts->capture("x-profile-selection-page-4");
            $this->exts->click_by_xdotool('[data-id="PP-ProfileChooser-Chooser"] [data-id="PP-ProfileChooser-AuthAccount"], [data-id="AccountChooser-AccountList-individual"]');
            sleep(7);
        }


        // wait for user logging in
        for ($wait_count = 1; $wait_count <= 10 && !$this->exts->exists($this->check_login_success_selector); $wait_count++) {
            $this->exts->log('Waiting for login...');
            sleep(5);
        }
    }
    
    if ($this->exts->exists('button[data-id="PP-PasskeyEnroll-skip-btn"]')) {
        $this->exts->moveToElementAndClick('button[data-id="PP-PasskeyEnroll-skip-btn"]');
        sleep(10);
    }
    // then check user logged in or not
    if ($this->exts->getElement($this->check_login_success_selector) != null) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {

            $this->exts->triggerLoginSuccess();
        }

    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed: ' . $this->exts->getUrl());
        if ($this->exts->getElement($this->check_login_failed_selector) != null) {
            $this->exts->loginFailure(1);
        } else if ($this->exts->exists('[data-id="PasswordChangeRequiredPage-Continue"], [data-id="PasswordChangeOnFirstLoginPage-Description"], button[data-id="PasswordChangeOnFirstLoginPage-Continue"]')) {
            $this->exts->account_not_ready();
        } else if ($this->exts->exists('[data-id="PP-T2E-AssetMigration"] button[data-id="PP-T2E-AssetMigration-Confirmation-ConfirmMigrationButton"]')) {
            // All content stored in your Adobe cloud storage, including content related to any individual plan, will be moved to the business storage and accessible from your Business profile. Once your profiles are set up, you can always move content between them.
            // We can't confirm this case, Let users do. So return account not ready
            $this->exts->account_not_ready();
        } else if (strpos(strtolower($this->exts->extract('[data-id="EmailPage-Toaster"] .spectrum-Toast-content')), 'your personal account has been deactivated.') !== false) {
            $this->exts->account_not_ready();
        } else if ($this->exts->makeFrameExecutable('iframe[name="external-action"]')->exists('div[class*="TeamUpgradeDelegation__teamUpgradeDelegationTitle"]')) {
            $this->exts->account_not_ready();
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLogin()
{
    sleep(20);
    if ($this->exts->oneExists([$this->username_selector, $this->password_selector])) {
        sleep(3);
        $this->exts->capture("2-login-page");
        if ($this->login_with_google == 1) {
            $this->exts->moveToElementAndClick('[data-id="EmailPage-GoogleSignInButton"]');
            sleep(3);
            $this->loginGoogleIfRequired();
        } else {
            if ($this->exts->exists($this->username_selector)) {
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(2);

                $this->exts->capture("2-email-page-filled");
                $this->exts->moveToElementAndClick($this->next_button);
                sleep(5);
            }

            if($this->exts->exists('h1[data-id="PasskeyFactorPage-Title"]') && !$this->exts->exists($this->password_selector)){
                $this->exts->type_key_by_xdotool('Return');
                sleep(2);
                $this->exts->click_by_xdotool('button[data-id="Page-PrimaryButton"]');
                sleep(1);
            }


            if ($this->exts->exists('[data-id="PP-ProfileChooser-Chooser"] [data-id="PP-ProfileChooser-AuthAccount"], [data-id="AccountChooser-AccountList-individual"]')) {
                $this->exts->capture("x-profile-selection-page");
                $this->exts->moveToElementAndClick('[data-id="PP-ProfileChooser-Chooser"] [data-id="PP-ProfileChooser-AuthAccount"], [data-id="AccountChooser-AccountList-individual"]');
                sleep(7);
            }

            if ($this->exts->exists('[data-id="SocialOnlyPage-GoogleSignInButton"]')) {
                $this->exts->capture('before-click-login-with-google');
                $this->exts->moveToElementAndClick('[data-id="SocialOnlyPage-GoogleSignInButton"]');
                sleep(5);
                $this->exts->capture('after-click-login-with-google');
                $this->loginGoogleIfRequired();
                $this->exts->capture('after-login-with-google');
            } else if ($this->exts->exists('a[data-id="SocialOnlyPage-AppleSignInButton"]')) {
                $this->exts->capture('before-click-login-with-apple');
                $this->exts->moveToElementAndClick('a[data-id="SocialOnlyPage-AppleSignInButton"]');
                sleep(5);
                $this->exts->capture('after-click-login-with-apple');
                $this->loginAppleIfRequired();
                $this->exts->capture('after-login-with-apple');
            } else {
                // 2FA may be required right after inputing username
                // Maybe first confirm phone number, then enter code, so call 2FA two time
                $this->checkFillTwoFactor();
                $this->exts->update_process_lock();
                $this->checkFillTwoFactor();

                if (!$this->exts->exists($this->password_selector)) {
                    sleep(15);
                    if (!$this->exts->exists($this->password_selector)) {
                        sleep(15);
                    }
                }
                if ($this->exts->exists($this->password_selector)) {
                    $this->exts->log("Enter Password");
                    $this->exts->moveToElementAndType($this->password_selector, $this->password);
                    sleep(1);

                    if ($this->remember_me_selector != '')
                        $this->exts->moveToElementAndClick($this->remember_me_selector);
                    sleep(2);
                    $this->exts->capture("2-login-page-filled");

                    $this->checkFillRecaptcha();
                    sleep(3);
                    if ($this->exts->exists($this->submit_login_selector)) {
                        $this->exts->moveToElementAndClick($this->submit_login_selector);
                        sleep(12);
                    }
                    $this->exts->capture("2-password-submitted-1");
                  
                    if ($this->exts->exists($this->password_selector)) {
                        $this->exts->log("Enter Password again");
                        $this->exts->moveToElementAndType($this->password_selector, $this->password);
                        sleep(1);
                        $this->exts->capture("2-password-filled-again");
                        if ($this->exts->exists($this->submit_login_selector)) {
                            $this->exts->moveToElementAndClick($this->submit_login_selector);
                            sleep(12);
                        }
                        $this->exts->capture("2-password-submitted-2");
                    }
                    sleep(5);
                } else {
                    $this->exts->log(__FUNCTION__ . '::Login page not found');
                    $this->exts->capture("2-password-page-not-found");
                    // .IconMessage__icon [src="/mfa/S_Illu_Authenticate_58"]
                }
            }
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}
private function checkFillRecaptcha()
{
    $this->exts->log(__FUNCTION__);
    $recaptcha_iframe_selector = 'iframe[src*="/recaptcha/api2/anchor?"]';
    $recaptcha_textarea_selector = 'textarea[name="g-recaptcha-response"]';
    if ($this->exts->exists($recaptcha_iframe_selector)) {
        $iframeUrl = $this->exts->extract($recaptcha_iframe_selector, null, 'src');
        $data_siteKey = explode('&', end(explode("&k=", $iframeUrl)))[0];
        $this->exts->log("iframe url  - " . $iframeUrl);
        $this->exts->log("SiteKey - " . $data_siteKey);

        $isCaptchaSolved = $this->exts->processRecaptcha($this->exts->getUrl(), $data_siteKey, false);
        $this->exts->log("isCaptchaSolved - " . $isCaptchaSolved);

        if ($isCaptchaSolved) {
            // Step 1 fill answer to textarea
            $this->exts->log(__FUNCTION__ . "::filling reCaptcha response..");
            $recaptcha_textareas =  $this->exts->getElements($recaptcha_textarea_selector);
            for ($i = 0; $i < count($recaptcha_textareas); $i++) {
                $this->exts->execute_javascript("arguments[0].innerHTML = '" . $this->exts->recaptcha_answer . "';", [$recaptcha_textareas[$i]]);
            }
            sleep(2);
            $this->exts->capture('recaptcha-filled');

            // Step 2, check if callback function need executed
            $gcallbackFunction = $this->exts->execute_javascript('
            if(document.querySelector("[data-callback]") != null){
                document.querySelector("[data-callback]").getAttribute("data-callback");
            } else {
                var result = ""; var found = false;
                function recurse (cur, prop, deep) {
                    if(deep > 5 || found){ return;}console.log(prop);
                    try {
                        if(prop.indexOf(".callback") > -1){result = prop; found = true; return;
                        } else { if(cur == undefined || cur == null || cur instanceof Element || Object(cur) !== cur || Array.isArray(cur)){ return;}deep++;
                            for (var p in cur) { recurse(cur[p], prop ? prop + "." + p : p, deep);}
                        }
                    } catch(ex) { console.log("ERROR in function: " + ex); return; }
                }

                recurse(___grecaptcha_cfg.clients[0], "", 0);
                found ? "___grecaptcha_cfg.clients[0]." + result : null;
            }
        ');
            $this->exts->log('Callback function: ' . $gcallbackFunction);
            if ($gcallbackFunction != null) {
                $this->exts->execute_javascript($gcallbackFunction . '("' . $this->exts->recaptcha_answer . '");');
                sleep(10);
            }
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Not found reCaptcha');
    }
}
private function checkFillTwoFactor()
{
    if ($this->exts->exists('.PasswordlessWrongBingo a[data-id="Page-ChangeMethod"]') || ($this->exts->exists('a[data-id="PasswordlessSignInWait-ChangeMethod"]') && $this->exts->two_factor_attempts >= 1)) {
        // Updated 22-Dec-2020 If passwordless don't work, back to list to choose another method.
        $this->exts->moveToElementAndClick('.PasswordlessWrongBingo a[data-id="Page-ChangeMethod"], [data-id="PasswordlessSignInWait-ChangeMethod"]');
        sleep(5);
    }

    if ($this->exts->exists('[data-id="ChallengeChooser"] [data-id="AuthenticationFactor-phone"]')) {
        $this->exts->moveToElementAndClick('[data-id="ChallengeChooser"] [data-id="AuthenticationFactor-phone"]');
        sleep(5);
    } else if ($this->exts->exists('[data-id="ChallengeChooser"] [data-id="AuthenticationFactor-email"]')) {
        $this->exts->moveToElementAndClick('[data-id="ChallengeChooser"] [data-id="AuthenticationFactor-email"]');
        sleep(5);
    } else {
        $this->exts->moveToElementAndClick('[data-id="ChallengeChooser"] .ActionList-Item');
        sleep(5);
    }
    if ($this->exts->exists('.IconMessage__icon [src*="/mfa/S_Illu_MailTo_58"], [data-id="ChallengePushPage-EnterCode"], div.IconHeading img[src*="S_Illu_MailTo_"], button[data-id="AdditionalAccountDetailsPage-ContinueButton"]')) {
        // Confirm send code to email or to input code
        $this->exts->moveToElementAndClick('button[name="submit"][data-id="Page-PrimaryButton"], [data-id="ChallengePushPage-EnterCode"], button[data-id="AdditionalAccountDetailsPage-ContinueButton"]');
        sleep(5);
    }

    if ($this->exts->getElement('input[data-id*="CodeInput"]') != null) {
        $this->exts->log("Current URL - " . $this->exts->getUrl());
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor-" . $this->exts->two_factor_attempts);
        $this->exts->two_factor_notif_msg_en = $this->exts->extract('.ChallengeCode-Description', null, 'innerText');
        $this->exts->two_factor_notif_msg_de =  $this->exts->two_factor_notif_msg_en;

        if (strpos(strtolower($this->exts->extract('[data-id="ChallengeCodePage-Error"]')), ' code. try again') !== false) {
            if ($this->exts->exists('button[data-id="ChallengeCodePage-Resend"]')) {
                $this->exts->moveToElementAndClick('button[data-id="ChallengeCodePage-Resend"]');
                sleep(3);
                if ($this->exts->exists('.IconMessage__icon [src*="/mfa/S_Illu_MailTo_58"], [data-id="ChallengePushPage-EnterCode"], div.IconHeading img[src*="S_Illu_MailTo_"], button[data-id="AdditionalAccountDetailsPage-ContinueButton"]')) {
                    // Confirm send code to email or to input code
                    $this->exts->moveToElementAndClick('button[name="submit"][data-id="Page-PrimaryButton"], [data-id="ChallengePushPage-EnterCode"], button[data-id="AdditionalAccountDetailsPage-ContinueButton"]');
                    sleep(5);
                }
            }
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
        }

        $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
        $this->exts->notification_uid = "";
        $this->exts->two_factor_attempts++;
        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if (!empty($two_factor_code) && trim($two_factor_code) != '') {
            $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
            $resultCodes = str_split($two_factor_code);
            $code_inputs = $this->exts->querySelectorAll('input[data-id*="CodeInput"]');

            foreach ($code_inputs as $key => $code_input) {
                if (array_key_exists($key, $resultCodes)) {
                    $this->exts->log('"checkFillTwoFactor: Entering key ' . $resultCodes[$key] . 'to input #' . $code_input->getAttribute('data-id'));

                    $this->exts->moveToElementAndType('input[data-id*="CodeInput"]:nth-child(' . ($key + 1) . ')', $resultCodes[$key]);
                    sleep(1);
                } else {
                    $this->exts->log('"checkFillTwoFactor: Have no char for input #' . $code_input->getAttribute('data-id'));
                }
            }
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);
            if ($this->exts->exists('button[data-id="ChallengeCodePage-VerifyCode"], [data-id="ChallengeCodePage-Continue"]')) {
                $this->exts->moveToElementAndClick('button[data-id="ChallengeCodePage-VerifyCode"], [data-id="ChallengeCodePage-Continue"]');
            }
            sleep(7);
            $this->exts->capture("2.2-two-factor-submitted-" . $this->exts->two_factor_attempts);
        } else {
            $this->exts->log("Not received two factor code");
        }
    } else if ($this->exts->exists('.IconMessage__icon [src*="/mfa/S_Illu_Authenticate_58"]')) {
        $this->exts->capture("2.2-two-factor-approval");
        $message_selector = '.IconMessage__description';
        $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'innerText')) . "\n>>>Enter \"OK\" after confirmation on device";
        $this->exts->two_factor_notif_msg_de = trim($this->exts->extract($message_selector, null, 'innerText')) . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
        $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
        $this->exts->notification_uid = "";
        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if (!empty($two_factor_code) && trim($two_factor_code) != '') {
            sleep(5);
            $this->exts->capture("2.2-two-factor-approval-accepted");
            if ($this->exts->exists('.IconMessage__icon [src*="/mfa/S_Illu_Authenticate_58"]')) {
                sleep(10);
            }
        } else {
            $this->exts->log("Not received two factor code");
        }
    } else if ($this->exts->exists('[data-id="PasswordlessSignInWait-Description"]')) {
        $this->exts->capture("2.2-two-factor-passwordlesss");
        $message_selector = '[data-id="PasswordlessSignInWait-Description"]';
        $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'innerText')) . "\n>>>Enter \"OK\" after confirmation on device";
        $this->exts->two_factor_notif_msg_de = trim($this->exts->extract($message_selector, null, 'innerText')) . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
        $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
        $this->exts->notification_uid = "";
        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if (!empty($two_factor_code) && trim($two_factor_code) != '') {
            sleep(5);
            $this->exts->capture("2.2-two-factor-passwordlesss-accepted");
            if ($this->exts->exists('[data-id="PasswordlessSignInWait-Description"]')) {
                sleep(10);
            }
        } else {
            $this->exts->log("Not received two factor code");
        }
    }
}
private function checkConfirmPassword()
{
    $this->exts->log(__FUNCTION__);
    if ($this->exts->exists($this->password_selector) && $this->exts->exists($this->username_readonly_selector)) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . "::This is confirm password form");
        $this->exts->capture('confirm-password-page');

        $this->exts->log(__FUNCTION__ . "::Enter confirm password");
        $this->exts->moveToElementAndClick($this->password_selector);
        sleep(2);
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(3);
        $this->exts->capture("confirm-password-filled");
        $this->exts->moveToElementAndClick($this->submit_login_selector);
        $this->exts->log(__FUNCTION__ . "::Checking after confirm..");
        sleep(15);

        if ($this->exts->exists($this->password_selector)) {
            $this->exts->capture("failed-confirm-password");
            $this->exts->log(__FUNCTION__ . "::Confirm password failed");
            $this->exts->log(__FUNCTION__ . "::Exit progress to avoid locked");
            $this->exts->exitFinal();
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::No password confirm required');
    }
}

// -------------------- GOOGLE login
public $google_username_selector = 'input[name="identifier"]:not([aria-hidden="true"])';
public $google_submit_username_selector = '#identifierNext, input#submit, input#next';
public $google_password_selector = 'input[name="password"], input[name="Passwd"]';
public $google_submit_password_selector = '#passwordNext, #gaia_loginform input#signIn, #passwordNext button, input#submit';
private function loginGoogleIfRequired()
{
    if ($this->exts->urlContains('google.')) {
        if ($this->exts->urlContains('/webreauth')) {
            $this->exts->moveToElementAndClick('#identifierNext');
            sleep(6);
        }
        $this->googleCheckFillLogin();
        sleep(5);
        if ($this->exts->querySelector('input[name="password"][aria-invalid="true"], input[name="identifier"][aria-invalid="true"], input[name="Passwd"][aria-invalid="true"]') != null) {
            $this->exts->loginFailure(1);
        }
        if ($this->exts->querySelector('form[action*="/signin/v2/challenge/password/"] input[name="password"][aria-invalid="true"], input[name="identifier"][aria-invalid="true"], span#passwordError, input[name="Passwd"][aria-invalid="true"]') != null) {
            $this->exts->loginFailure(1);
        }

        // Click next if confirm form showed
        $this->exts->moveToElementAndClick('div[data-ownership-changed-phone-number] div:nth-child(2) > [role="button"]');
        $this->googleCheckTwoFactorMethod();

        if ($this->exts->exists('#smsauth-interstitial-remindbutton')) {
            $this->exts->moveToElementAndClick('#smsauth-interstitial-remindbutton');
            sleep(10);
        }
        if ($this->exts->exists('#tos_form input#accept')) {
            $this->exts->moveToElementAndClick('#tos_form input#accept');
            sleep(10);
        }
        if ($this->exts->exists('[wizard-step-uid="RecoveryOptionsCollectionWizard:starter"] div:last-child > [role="button"]')) {
            $this->exts->moveToElementAndClick('[wizard-step-uid="RecoveryOptionsCollectionWizard:starter"] div:last-child > [role="button"]');
            sleep(10);
        }
        if ($this->exts->exists('.action-button.signin-button + a.setup-button[href*="/two-step-verification/"]')) {
            // SKIP setup 2FA
            $this->exts->moveToElementAndClick('.action-button.signin-button');
            sleep(10);
        }
        if ($this->exts->exists('a.setup-button[href*="two-step-verification/enroll"] + button.signin-button')) {
            // SKIP setup 2FA
            $this->exts->moveToElementAndClick('a.setup-button[href*="two-step-verification/enroll"] + button.signin-button');
            sleep(10);
        }
        if ($this->exts->exists('[action="/signin/newfeatures/save"] #optionsButton ~ [role="button"]')) {
            $this->exts->moveToElementAndClick('[action="/signin/newfeatures/save"] #optionsButton ~ [role="button"]');
            sleep(10);
        }
        if ($this->exts->exists('input[name="later"]') && $this->exts->urlContains('/AddressNoLongerAvailable')) {
            $this->exts->moveToElementAndClick('input[name="later"]');
            sleep(7);
        }
        if ($this->exts->exists('#editLanguageAndContactForm a[href*="/adsense/app"]')) {
            $this->exts->moveToElementAndClick('#editLanguageAndContactForm a[href*="/adsense/app"]');
            sleep(7);
        }
        if ($this->exts->exists('[data-view-instance-id="/web/chip-V0"] [role="button"]:first-child [jsslot]')) {
            $this->exts->moveToElementAndClick('[data-view-instance-id="/web/chip-V0"] [role="button"]:first-child [jsslot]');
            sleep(10);
        }

        if ($this->exts->exists('#submit_approve_access')) {
            $this->exts->moveToElementAndClick('#submit_approve_access');
            sleep(10);
        } else if ($this->exts->exists('form #approve_button[name="submit_true"]')) {
            // An application is requesting permission to access your Google Account.
            // Click allow
            $this->exts->moveToElementAndClick('form #approve_button[name="submit_true"]');
            sleep(10);
        }
        $this->exts->capture("3-google-before-back-to-main-tab");
    } else {
        $this->exts->log(__FUNCTION__ . '::Not required google login.');
        $this->exts->capture("3-no-google-required");
    }
}
private function googleCheckFillLogin()
{
    if ($this->exts->exists('form ul li [role="link"][data-identifier]')) {
        $this->exts->moveToElementAndClick('form ul li [role="link"][data-identifier]');
        sleep(5);
    }

    if ($this->exts->urlContainsAny(['/ServiceLogin/identifier', '/ServiceLogin/webreauth']) && $this->exts->exists($this->google_submit_username_selector) && !$this->exts->exists($this->google_username_selector)) {
        $this->exts->capture("google-verify-it-you");
        // To help keep your account secure, Google needs to verify itâ€™s you. Please sign in again to continue to Google Ads
        $this->exts->moveToElementAndClick($this->google_submit_username_selector);
        sleep(5);
    }

    $this->exts->capture("2-google-login-page");
    if ($this->exts->exists($this->google_username_selector)) {
        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->google_username_selector, $this->username);
        sleep(1);
        $this->exts->moveToElementAndClick($this->google_submit_username_selector);
        sleep(5);
        if ($this->exts->exists('#captchaimg[src]') && !$this->exts->exists($this->google_password_selector) && $this->exts->exists($this->google_username_selector)) {
            $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
            $this->exts->moveToElementAndClick($this->google_submit_username_selector);
            sleep(5);
            if ($this->exts->exists('#captchaimg[src]') && !$this->exts->exists($this->google_password_selector) && $this->exts->exists($this->google_username_selector)) {
                $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                $this->exts->moveToElementAndClick($this->google_submit_username_selector);
                sleep(5);
            }
            if ($this->exts->exists('#captchaimg[src]') && !$this->exts->exists($this->google_password_selector) && $this->exts->exists($this->google_username_selector)) {
                $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                $this->exts->moveToElementAndClick($this->google_submit_username_selector);
                sleep(5);
            }
        } else if ($this->exts->urlContains('/challenge/recaptcha')) {
            $this->googlecheckFillRecaptcha();
            $this->exts->moveToElementAndClick('[data-primary-action-label] > div > div:first-child button');
            sleep(5);
        }

        // Which account do you want to use?
        if ($this->exts->exists('form[action*="/lookup"] button.account-chooser-button')) {
            $this->exts->moveToElementAndClick('form[action*="/lookup"] button.account-chooser-button');
            sleep(5);
        }
        if ($this->exts->exists('[data-view-id="prbTle"] form [role="link"][data-profileindex]')) {
            $this->exts->moveToElementAndClick('[data-view-id="prbTle"] form [role="link"][data-profileindex]');
            sleep(5);
        }
    }

    if ($this->exts->exists($this->google_password_selector)) {
        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
        sleep(1);
        if ($this->exts->exists('#captchaimg[src]')) {
            $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
        }

        $this->exts->capture("2-google-login-page-filled");
        $this->exts->moveToElementAndClick($this->google_submit_password_selector);
        sleep(5);
        if ($this->exts->exists('#captchaimg[src]') && !$this->exts->exists('input[name="password"][aria-invalid="true"]') && $this->exts->exists($this->google_password_selector)) {
            $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
            sleep(1);
            if ($this->exts->exists('#captchaimg[src]')) {
                $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
            }
            $this->exts->moveToElementAndClick($this->google_submit_password_selector);
            sleep(5);
            if ($this->exts->exists('#captchaimg[src]') && $this->exts->exists($this->google_password_selector)) {
                $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
                sleep(1);
                $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                $this->exts->capture("2-google-login-pageandcaptcha-filled");
                $this->exts->moveToElementAndClick($this->google_submit_password_selector);
            }
        } else {
            $this->googlecheckFillRecaptcha();
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Google password page not found');
        $this->exts->capture("2-google-password-page-not-found");
    }
}
private function googleCheckTwoFactorMethod()
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
    $this->exts->capture("2.0-before-check-two-factor-google");
    // STEP 0 (updated special case 28-Mar-2020): If we meet a unsolvable, click to back to method choosen list
    if ($this->exts->exists('#assistActionId') && $this->exts->exists('[data-illustration="securityKeyLaptopAnim"]')) {
        $this->exts->moveToElementAndClick('#assistActionId');
        sleep(5);
    } else if ($this->exts->urlContains('/challenge/wa') && strpos($this->exts->extract('form header h2'), 'QR-Code') !== false) {
        // (updated special case 28-Mar-2020): If we meet QR-Code, click 'Choose another option' to back to method choosen list
        $this->exts->moveToElementAndClick('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
        sleep(5);
        $this->exts->capture("2.0-backed-methods-list-google");
        if ($this->exts->urlContains('/challenge/wa') && strpos($this->exts->extract('form header h2'), 'QR-Code') !== false) {
            $this->exts->moveToElementAndClick('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
            sleep(5);
        }
    } else if ($this->exts->urlContains('/sk/webauthn')) {
        $node_name = !empty($this->exts->config_array['node_name']) ? $this->exts->config_array['node_name'] : "selenium-node-" . $this->exts->process_uid;
        exec("sudo docker exec " . $node_name . " bash -c 'xdotool key Return'");
        sleep(3);
        $this->exts->capture("2.0-cancel-security-usb-google");
        $this->exts->moveToElementAndClick('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
        sleep(5);
        $this->exts->capture("2.0-backed-methods-list-google");
    } else if ($this->exts->exists('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?')) {
        // (updated special case 09-May-2020): If Notification method showed immediately, This method often make user confused
        // So, We try to click 'Choose another option' in order to select easier method
        $this->exts->moveToElementAndClick('[data-view-id] > div > div:nth-child(2)  div:nth-child(2) > [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button, button#assistiveActionOutOfQuota');
        sleep(7);
        $this->exts->capture("2.0-backed-methods-list-google");
    } else if ($this->exts->exists('input[name="ootpPin"]')) {
        // (updated special case 11-Jun-2020): If "Verify by offline device" immediately, This method often make user confused and maybe they don't have device on hand
        // So, We try to click 'Choose another option' in order to select easier method
        $this->exts->moveToElementAndClick('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
        sleep(7);
        $this->exts->capture("2.0-backed-methods-list-google");
    }

    // STEP 1: Check if list of two factor methods showed, select first
    if ($this->exts->exists('li [data-challengetype]:not([data-challengeunavailable="true"])')) {
        // We most RECOMMEND confirm security phone or email, then other method
        if ($this->exts->exists('li [data-challengetype="13"]:not([data-challengeunavailable="true"])') && isset($this->security_phone_number) && $this->security_phone_number != '') {
            $this->exts->moveToElementAndClick('li [data-challengetype="13"]:not([data-challengeunavailable="true"])');
        } else if ($this->exts->exists('li [data-challengetype="12"]:not([data-challengeunavailable="true"])') && isset($this->recovery_email) && $this->recovery_email != '') {
            $this->exts->moveToElementAndClick('li [data-challengetype="12"]:not([data-challengeunavailable="true"])');
        } else if ($this->exts->exists('li [data-challengetype="6"]:not([data-challengeunavailable="true"])')) {
            // We RECOMMEND method type = 6 is get code from Google Authenticator
            $this->exts->moveToElementAndClick('li [data-challengetype="6"]:not([data-challengeunavailable="true"])');
        } else if ($this->exts->exists('li [data-challengetype][data-sendmethod="SMS"]:not([data-challengeunavailable="true"])')) {
            // We second RECOMMEND method type = 9 is get code from SMS
            $this->exts->moveToElementAndClick('li [data-challengetype][data-sendmethod="SMS"]:not([data-challengeunavailable="true"])');
        } else if ($this->exts->exists('li [data-challengetype="4"][data-sendauthzenprompt="true"]:not([data-challengeunavailable="true"]), li [data-challengetype="39"]:not([data-challengeunavailable="true"])')) {
            // We recommend method type = 4 and [data-sendauthzenprompt="true"] is  Tap YES on your smartphone or tablet
            $this->exts->moveToElementAndClick('li [data-challengetype="4"][data-sendauthzenprompt="true"]:not([data-challengeunavailable="true"]), li [data-challengetype="39"]:not([data-challengeunavailable="true"])');
        } else if ($this->exts->exists('li [data-challengetype="5"]:not([data-challengeunavailable="true"])')) {
            // Use a smartphone or tablet to receive a security code (even when offline)
            $this->exts->moveToElementAndClick('li [data-challengetype="5"]:not([data-challengeunavailable="true"])');
        } else if ($this->exts->exists('li [data-challengetype]:not([data-challengetype="4"]):not([data-challengetype="2"]):not([data-challengeunavailable="true"])')) {
            // We DONT recommend method is QR code OR is Security USB, we can not solve this type of 2FA
            $this->exts->moveToElementAndClick('li [data-challengetype]:not([data-challengetype="4"]):not([data-challengetype="2"]):not([data-challengeunavailable="true"])');
        } else {
            $this->exts->moveToElementAndClick('li [data-challengetype]:not([data-challengeunavailable="true"])');
        }
        sleep(10);
    } else if ($this->exts->exists('#smsButton, [data-illustration="accountRecoverySmsPin"]')) {
        // Sometime user must confirm before google send sms
        $this->exts->moveToElementAndClick('#smsButton, div:first-child > [role="button"], [data-secondary-action-label] > div > div:nth-child(1) button');
        sleep(10);
    } else if ($this->exts->exists('#authzenNext') && $this->exts->exists('[data-view-id*="authzenView"], [data-illustration*="authzen"]')) {
        $this->exts->moveToElementAndClick('[data-view-id] #authzenNext');
        sleep(10);
    } else if ($this->exts->exists('#idvpreregisteredemailNext') && !$this->exts->exists('form input:not([type="hidden"])')) {
        $this->exts->moveToElementAndClick('#idvpreregisteredemailNext');
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
            $this->exts->type_key_by_xdotool("Return");
            sleep(7);
        }
        if ($this->exts->exists($input_selector)) {
            $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
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
            $this->exts->type_key_by_xdotool("Return");
            sleep(5);
        }
        if ($this->exts->exists($input_selector)) {
            $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
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
            $this->exts->type_key_by_xdotool("Return");
            sleep(7);
        }
        if ($this->exts->exists($input_selector)) {
            $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
        }
    } else if ($this->exts->exists('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?')) {
        // Check your smartphone. Google has sent a notification to your smartphone. Tap Yes in the notification, then tap 91 on your smartphone to continue
        $this->exts->two_factor_attempts = 3;
        $message_selector = '[data-view-id*="authzenView"] form, [data-view-id] form[method="post"]';
        $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'innerText')) . "\n>>>Enter \"OK\" after confirmation on device";
        $this->exts->two_factor_notif_msg_de = trim($this->exts->extract($message_selector, null, 'innerText')) . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
        $this->googleFillTwoFactor(null, null, '');
        sleep(5);
    } else if ($this->exts->exists('[data-view-id*="securityKeyWebAuthnView"], [data-view-id*="securityKeyView"]')) {
        // Method: insert your security key and touch it
        $this->exts->two_factor_attempts = 3;
        $this->exts->two_factor_notif_msg_en = 'Use chrome, login then insert your security key and touch it' . "\n>>>Enter \"OK\" after confirmation on device";
        $this->exts->two_factor_notif_msg_de = '[Chrome] Stecken Sie den Sicherheitsschlussel in den USB-Anschluss Ihres Computers ein. Wenn er eine Taste hat, tippen Sie darauf.' . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
        $this->googleFillTwoFactor(null, null, '');
        sleep(5);
        // choose another option: #assistActionId
    }

    // STEP 3: (Optional)  After choose method and confirm email or phone or.., google may asked confirm one more time before send code
    if ($this->exts->exists('#smsButton, [data-illustration="accountRecoverySmsPin"]')) {
        // Sometime user must confirm before google send sms
        $this->exts->moveToElementAndClick('#smsButton, div:first-child > [role="button"], [data-secondary-action-label] > div > div:nth-child(1) button');
        sleep(10);
    } else if ($this->exts->exists('#authzenNext') && $this->exts->exists('[data-view-id*="authzenView"], [data-illustration*="authzen"]')) {
        $this->exts->moveToElementAndClick('[data-view-id] #authzenNext');
        sleep(10);
    } else if ($this->exts->exists('#idvpreregisteredemailNext') && !$this->exts->exists('form input:not([type="hidden"])')) {
        $this->exts->moveToElementAndClick('#idvpreregisteredemailNext');
        sleep(10);
    } else if (count($this->exts->getElements('li [data-challengetype]:not([data-challengeunavailable="true"]):not([data-challengetype="undefined"])')) > 0) {
        $this->exts->moveToElementAndClick('li [data-challengetype]:not([data-challengeunavailable="true"]):not([data-challengetype="undefined"])');
        sleep(7);
    }


    // STEP 4: input code
    if ($this->exts->exists('form input[name="idvPin"], form input[name="totpPin"], input[name="code"], input#backupCodePin')) {
        $input_selector = 'form input[name="idvPin"], form input[name="totpPin"], input[name="code"], input#backupCodePin';
        $message_selector = 'form > span > section > div > div > div:first-child';
        $submit_selector = '#idvPreregisteredPhoneNext, #idvpreregisteredemailNext, #totpNext, #idvanyphoneverifyNext, #backupCodeNext, #view_container div.pwWryf.bxPAYd div.zQJV3 div.qhFLie > div > div > button';
        $this->exts->two_factor_attempts = 0;
        $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector);
    } else if ($this->exts->exists('input[name="ootpPin"], input#securityKeyOtpInputId')) {
        $input_selector = 'input[name="ootpPin"], input#securityKeyOtpInputId';
        $message_selector = 'form > span > section > div > div > div:first-child';
        $submit_selector = '';
        $this->exts->two_factor_attempts = 0;
        $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
    } else if ($this->exts->exists('input[name="Pin"]')) {
        $input_selector = 'input[name="Pin"]';
        $message_selector = 'form > span > section > div > div > div:first-child';
        $submit_selector = '';
        $this->exts->two_factor_attempts = 0;
        $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
    } else if ($this->exts->exists('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?')) {
        // Check your smartphone. Google has sent a notification to your smartphone. Tap Yes in the notification, then tap 91 on your smartphone to continue
        $this->exts->two_factor_attempts = 3;
        $message_selector = '[data-view-id*="authzenView"] form, [data-view-id] form[method="post"]';
        $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'innerText')) . "\n>>>Enter \"OK\" after confirmation on device";
        $this->exts->two_factor_notif_msg_de = trim($this->exts->extract($message_selector, null, 'innerText')) . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
        $this->googleFillTwoFactor(null, null, '');
        sleep(5);
    } else if ($this->exts->exists('input[name="secretQuestionResponse"]')) {
        $input_selector = 'input[name="secretQuestionResponse"]';
        $message_selector = 'form > span > section > div > div > div:first-child';
        $submit_selector = '[data-secondary-action-label] > div > div:nth-child(1) button';
        $this->exts->two_factor_attempts = 0;
        $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector);
    }
}
private function googleFillTwoFactor($input_selector, $message_selector, $submit_selector, $submit_by_enter = false)
{
    $this->exts->log(__FUNCTION__);
    $this->exts->log("Google two factor page found.");
    $this->exts->capture("2.1-two-factor-google");

    if ($this->exts->querySelector($message_selector) != null) {
        $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'innerText'));
        $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
    }

    $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
    $this->exts->notification_uid = "";
    $two_factor_code = trim(trim($this->exts->fetchTwoFactorCode()));
    if (!empty($two_factor_code) && trim($two_factor_code) != '') {
        if ($this->exts->querySelector($input_selector) != null) {
            if (substr(trim($two_factor_code), 0, 2) === 'G-') {
                $two_factor_code = end(explode('G-', $two_factor_code));
            }
            if (substr(trim($two_factor_code), 0, 2) === 'g-') {
                $two_factor_code = end(explode('g-', $two_factor_code));
            }
            $this->exts->log(__FUNCTION__ . ": Entering two_factor_code: " . $two_factor_code);
            $this->exts->moveToElementAndType($input_selector, '');
            $this->exts->moveToElementAndType($input_selector, $two_factor_code);
            sleep(1);
            if ($this->exts->allExists(['input[type="checkbox"]:not(:checked) + div', 'input[name="Pin"]'])) {
                $this->exts->moveToElementAndClick('input[type="checkbox"]:not(:checked) + div');
                sleep(1);
            }
            $this->exts->capture("2.2-google-two-factor-filled-" . $this->exts->two_factor_attempts);

            if ($this->exts->exists($submit_selector)) {
                $this->exts->log(__FUNCTION__ . ": Clicking submit button.");
                $this->exts->moveToElementAndClick($submit_selector);
            } else if ($submit_by_enter) {
                $this->exts->type_key_by_xdotool("Return");
            }
            sleep(10);
            $this->exts->capture("2.2-google-two-factor-submitted-" . $this->exts->two_factor_attempts);
            if ($this->exts->querySelector($input_selector) == null) {
                $this->exts->log("Google two factor solved");
            } else {
                if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->notification_uid = '';
                    $this->exts->two_factor_attempts++;
                    $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector);
                } else {
                    $this->exts->log("Google Two factor can not solved");
                }
            }
        } else {
            $this->exts->log("Google not found two factor input");
        }
    } else {
        $this->exts->log("Google not received two factor code");
        $this->exts->two_factor_attempts = 3;
    }
}
private function googlecheckFillRecaptcha()
{
    $this->exts->log(__FUNCTION__);
    $recaptcha_iframe_selector = 'form iframe[src*="/recaptcha/"]';
    $recaptcha_textarea_selector = 'textarea[name="g-recaptcha-response"]';
    if ($this->exts->exists($recaptcha_iframe_selector)) {
        $iframeUrl = $this->exts->extract($recaptcha_iframe_selector, null, 'src');
        $data_siteKey = explode('&', end(explode("&k=", $iframeUrl)))[0];
        $this->exts->log("iframe url  - " . $iframeUrl);
        $this->exts->log("SiteKey - " . $data_siteKey);
        $url = reset(explode('?', $this->exts->getUrl()));
        $isCaptchaSolved = $this->exts->processRecaptcha($url, $data_siteKey, false);
        $this->exts->log("isCaptchaSolved - " . $isCaptchaSolved);

        if ($isCaptchaSolved) {
            // Step 1 fill answer to textarea
            $this->exts->log(__FUNCTION__ . "::filling reCaptcha response..");
            $recaptcha_textareas =  $this->exts->getElements($recaptcha_textarea_selector);
            for ($i = 0; $i < count($recaptcha_textareas); $i++) {
                $this->exts->execute_javascript("arguments[0].innerHTML = '" . $this->exts->recaptcha_answer . "';", [$recaptcha_textareas[$i]]);
            }
            sleep(2);
            $this->exts->capture('recaptcha-filled');

            // Step 2, check if callback function need executed
            $gcallbackFunction = $this->exts->execute_javascript('
            if(document.querySelector("[data-callback]") != null){
                document.querySelector("[data-callback]").getAttribute("data-callback");
            } else {
                var result = ""; var found = false;
                function recurse (cur, prop, deep) {
                    if(deep > 5 || found){ return;}console.log(prop);
                    try {
                        if(prop.indexOf(".callback") > -1){result = prop; found = true; return;
                        } else { if(cur == undefined || cur == null || cur instanceof Element || Object(cur) !== cur || Array.isArray(cur)){ return;}deep++;
                            for (var p in cur) { recurse(cur[p], prop ? prop + "." + p : p, deep);}
                        }
                    } catch(ex) { console.log("ERROR in function: " + ex); return; }
                }

                recurse(___grecaptcha_cfg.clients[0], "", 0);
                found ? "___grecaptcha_cfg.clients[0]." + result : null;
            }
        ');
            $this->exts->log('Callback function: ' . $gcallbackFunction);
            if ($gcallbackFunction != null) {
                $this->exts->execute_javascript($gcallbackFunction . '("' . $this->exts->recaptcha_answer . '");');
                sleep(10);
            }
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Not found reCaptcha');
    }
}
// End GOOGLE login
// ==================================BEGIN LOGIN WITH APPLE==================================
public $apple_username_selector = 'input#account_name_text_field';
public $apple_password_selector = '#stepEl:not(.hide) .password:not([aria-hidden="true"]) input#password_text_field';
public $apple_submit_login_selector = 'button#sign-in';
private function loginAppleIfRequired()
{
    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->urlContains('apple.com/auth/authorize')) {
        $this->checkFillAppleLogin();
        sleep(1);
        $this->exts->switchToDefault();
        if ($this->exts->exists('iframe[name="aid-auth-widget"]')) {
            $this->switchToFrame('iframe[name="aid-auth-widget"]');
        }
        if ($this->exts->exists('.signin-error #errMsg + a')) {
            $this->exts->loginFailure(1);
        } else if ($this->exts->exists('iframe[src*="/account/repair"], repair-missing-items, button[id*="unlock-account-"]')) {
            $this->exts->account_not_ready();
        }

        $this->exts->switchToDefault();
        $this->checkFillAppleTwoFactor();
        $this->exts->switchToDefault();
        if ($this->exts->exists('iframe[src*="/account/repair"]')) {
            $this->switchToFrame('iframe[src*="/account/repair"]');
            // If 2FA setting up page showed, click to cancel
            if ($this->exts->allExists(['.idms-step-content .icon-2fa', 'button.button-secondary.nav-cancel'])) {
                // Click "Other Option"
                $this->exts->moveToElementAndClick('button.button-secondary.nav-cancel');
                sleep(5);
                // Click "Dont upgrade"
                $this->exts->moveToElementAndClick('.confirmCancelContainer.fade-in button.button-secondary.nav-cancel');
                sleep(15);
            }
            $this->exts->switchToDefault();
        }

        // Click to accept consent temps, Must go inside 2 frame
        if ($this->exts->exists('iframe#aid-auth-widget-iFrame')) {
            $this->switchToFrame('iframe#aid-auth-widget-iFrame');
        }
        if ($this->exts->exists('iframe[src*="/account/repair"]')) {
            $this->switchToFrame('iframe[src*="/account/repair"]');
            // If 2FA setting up page showed, click to cancel
            if ($this->exts->allExists(['.idms-step-content .icon-2fa', 'button.button-secondary.nav-cancel'])) {
                // Click "Other Option"
                $this->exts->moveToElementAndClick('button.button-secondary.nav-cancel');
                sleep(5);
                // Click "Dont upgrade"
                $this->exts->moveToElementAndClick('.confirmCancelContainer.fade-in button.button-secondary.nav-cancel');
                sleep(15);
            }
            $this->exts->switchToDefault();
        }
        if ($this->exts->exists('.privacy-consent.fade-in button.nav-action')) {
            $this->exts->moveToElementAndClick('.privacy-consent.fade-in button.nav-action');
            sleep(15);
        }
        // end accept consent
    }
}
private function checkFillAppleLogin()
{
    $this->switchToFrame('iframe[name="aid-auth-widget"]');
    $this->exts->capture("2-apple_login-page");
    if ($this->exts->getElement($this->apple_username_selector) != null) {
        sleep(1);
        $this->exts->log("Enter apple_ Username");
        // $this->exts->getElement($this->apple_username_selector)->clear();
        $this->exts->moveToElementAndClick($this->apple_username_selector);
        sleep(2);
        $this->exts->moveToElementAndType($this->apple_username_selector, $this->username);
        sleep(1);
        $this->exts->moveToElementAndClick($this->apple_submit_login_selector);
        sleep(7);
        $this->exts->click_if_existed('button#continue-password');
    }

    if ($this->exts->getElement($this->apple_password_selector) != null) {
        $this->exts->log("Enter apple_ Password");
        $this->exts->moveToElementAndType($this->apple_password_selector, $this->password);
        sleep(1);
        if ($this->exts->exists('#remember-me:not(:checked)')) {
            $this->exts->moveToElementAndClick('label#remember-me-label');
            // sleep(2);
        }
        $this->exts->capture("2-apple_login-page-filled");
        $this->exts->moveToElementAndClick($this->apple_submit_login_selector);
        sleep(2);

        $this->exts->capture("2-apple_after-login-submit");
        $this->exts->switchToDefault();

        $this->exts->log(count($this->exts->getElements('iframe[name="aid-auth-widget"]')));
        $this->switchToFrame('iframe[name="aid-auth-widget"]');
        sleep(1);

        if ($this->exts->exists('iframe[src*="/account/repair"]')) {
            $this->switchToFrame('iframe[src*="/account/repair"]');
            // If 2FA setting up page showed, click to cancel
            if ($this->exts->allExists(['.idms-step-content .icon-2fa', 'button.button-secondary.nav-cancel'])) {
                // Click "Other Option"
                $this->exts->moveToElementAndClick('button.button-secondary.nav-cancel');
                sleep(5);
                // Click "Dont upgrade"
                $this->exts->moveToElementAndClick('.confirmCancelContainer.fade-in button.button-secondary.nav-cancel');
                sleep(15);
            }
            $this->exts->switchToDefault();
        }
    } else {
        $this->exts->capture("2-apple_password-page-not-found");
    }
}
private function checkFillAppleTwoFactor()
{
    $this->switchToFrame('#aid-auth-widget-iFrame');
    if ($this->exts->exists('.devices [role="list"] [role="button"][device-id]')) {
        $this->exts->moveToElementAndClick('.devices [role="list"] [role="button"][device-id]');
        sleep(5);
    }
    if ($this->exts->exists('div#stepEl div.phones div[class*="si-phone-name"]')) {
        $this->exts->log("Choose apple Phone");
        $this->exts->moveToElementAndClick('div#stepEl div.phones div[class*="si-phone-name"]');
        sleep(5);
    }
    if ($this->exts->getElement('input[id^="char"]') != null) {
        $this->exts->two_factor_notif_title_en = 'Apple login for ' . $this->exts->two_factor_notif_title_en;
        $this->exts->two_factor_notif_title_de = 'Apple login fur ' . $this->exts->two_factor_notif_title_de;

        $this->exts->log("Current apple URL - " . $this->exts->getUrl());
        $this->exts->log("Two apple factor page found.");
        $this->exts->capture("2.1-apple-two-factor");

        if ($this->exts->getElement('.verify-code .si-info, .verify-phone .si-info, .si-info') != null) {
            $this->exts->two_factor_notif_msg_en = trim($this->exts->getElement('.verify-code .si-info, .verify-phone .si-info, .si-info')->getAttribute('innerText'));
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
        }
        if ($this->exts->two_factor_attempts > 1) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
        }
        $this->exts->log("apple Message:\n" . $this->exts->two_factor_notif_msg_en);
        if ($this->exts->two_factor_attempts > 1) {
            $this->exts->moveToElementAndClick('.verify-device a#no-trstd-device-pop, .verify-phone a#didnt-get-code, a#didnt-get-code, a#no-trstd-device-pop');
            sleep(1);

            $this->exts->moveToElementAndClick('.verify-device .try-again a#try-again-link, .verify-phone a#try-again-link, .try-again a#try-again-link');
        }

        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if (!empty($two_factor_code) && trim($two_factor_code) != '') {
            $this->exts->log(__FUNCTION__ . ": Entering apple two_factor_code." . $two_factor_code);
            // $resultCodes = str_split($two_factor_code);
            // $code_inputs = $this->exts->getElements('input[id^="char"]');
            // foreach ($code_inputs as $key => $code_input) {
            //     if(array_key_exists($key, $resultCodes)){
            //         $this->exts->log(__FUNCTION__.': Entering apple key '. $resultCodes[$key] . 'to input #'.$code_input->getAttribute('id'));
            //         $code_input->sendKeys($resultCodes[$key]);
            //         $this->exts->capture("2.2-apple-two-factor-filled-".$this->exts->two_factor_attempts);
            //     } else {
            //         $this->exts->log(__FUNCTION__.': Have no char for input #'.$code_input->getAttribute('id'));
            //     }
            // }
            $this->exts->moveToElementAndClick('input[id^="char"]');

            sleep(15);
            $this->exts->capture("2.2-apple-two-factor-submitted-" . $this->exts->two_factor_attempts);
            $this->switchToFrame('#aid-auth-widget-iFrame');

            if ($this->exts->getElement('input[id^="char"]') != null && $this->exts->two_factor_attempts < 3) {
                $this->exts->two_factor_attempts++;
                $this->exts->notification_uid = "";

                $this->checkFillAppleTwoFactor();
            }

            if ($this->exts->exists('.button-bar button:last-child[id*="trust-browser-"]')) {
                $this->exts->moveToElementAndClick('.button-bar button:last-child[id*="trust-browser-"]');
                sleep(10);
            }
        } else {
            $this->exts->log("Not received apple two factor code");
        }
    }
}
// ==================================END LOGIN WITH APPLE==================================


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