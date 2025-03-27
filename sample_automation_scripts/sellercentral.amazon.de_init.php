public $baseUrl = 'https://sellercentral.amazon.de/home';
public $username_selector = 'form[name="signIn"] input[name="email"]:not([type="hidden"])';
public $password_selector = 'form[name="signIn"] input[name="password"]';
public $submit_login_selector = 'form[name="signIn"] input#signInSubmit';
public $remember_me = 'form[name="signIn"] input[name="rememberMe"]:not(:checked)';
public $isNoInvoice = true;

public $payment_settlements = 0;
public $seller_invoice = 0;
public $transaction_invoices = 0;
public $seller_fees = 0;
public $no_advertising_bills = 0;
public $language_code = 'de_DE';
public $currentSelectedMarketPlace = "";
public $no_marketplace = 1;

public $start_date = '';
/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->seller_invoice = isset($this->exts->config_array["seller_invoice"]) ? (int)@$this->exts->config_array["seller_invoice"] : $this->seller_invoice;
    $this->payment_settlements = isset($this->exts->config_array["payment_settlements"]) ? (int)@$this->exts->config_array["payment_settlements"] : $this->payment_settlements;
    $this->transaction_invoices = isset($this->exts->config_array["transaction_invoices"]) ? (int)@$this->exts->config_array["transaction_invoices"] : $this->transaction_invoices;
    $this->seller_fees = isset($this->exts->config_array["seller_fees"]) ? (int)@$this->exts->config_array["seller_fees"] : $this->seller_fees;
    $this->no_advertising_bills = isset($this->exts->config_array["advertising_bills"]) ? (int)@$this->exts->config_array["advertising_bills"] : $this->no_advertising_bills;
    $this->start_date = isset($this->exts->config_array["start_date"]) ? trim($this->exts->config_array["advertising_bills"]) : $this->start_date;
    $this->exts->log('CONFIG seller_invoice: ' . $this->seller_invoice);
    $this->exts->log('CONFIG payment_settlements: ' . $this->payment_settlements);
    $this->exts->log('CONFIG transaction view: ' . $this->transaction_invoices);
    $this->exts->log('CONFIG seller fees: ' . $this->seller_fees);
    $this->exts->log('CONFIG No Advert Invoices: ' . $this->no_advertising_bills);

    if (!empty($this->start_date)) {
        $this->start_date = strtotime($this->start_date);
    }

    $this->exts->two_factor_attempts = 0;
    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(10);
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if (!$this->isLoginSuccess()) {
        $this->exts->log('NOT logged via cookie');
        // $this->exts->clearCookies();
        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        if (!$this->exts->exists($this->password_selector)) {
            $this->exts->capture("2-login-exception");
            $this->exts->clearCookies();
            $this->exts->openUrl($this->baseUrl);
            sleep(10);
        }
        if ($this->exts->exists('input#auth-captcha-guess, input[name="cvf_captcha_input"], input[name="field-keywords"]')) {
            $captcha_inputted = $this->processCaptcha('img#auth-captcha-image, img[alt="captcha"], img[src*="captcha"]', 'input#auth-captcha-guess, input[name="cvf_captcha_input"], input[name="field-keywords"]');
            if ($captcha_inputted == false) {
                $this->processCaptcha('img#auth-captcha-image', 'input#auth-captcha-guess');
            }
        }
        // Login, retry few time since it show captcha
        $this->checkFillLogin();
        sleep(5);
        // retry if captcha showed
        if ($this->exts->allExists([$this->password_selector, 'input#auth-captcha-guess']) && !$this->isIncorrectCredential()) {
            $this->checkFillLogin();
            sleep(5);
        }
        if ($this->exts->allExists([$this->password_selector, 'input#auth-captcha-guess']) && !$this->isIncorrectCredential()) {
            $this->checkFillLogin();
            sleep(5);
        }
        if ($this->exts->allExists([$this->password_selector, 'input#auth-captcha-guess']) && !$this->isIncorrectCredential()) {
            $this->checkFillLogin();
            sleep(5);
            if ($this->exts->allExists([$this->password_selector, 'input#auth-captcha-guess']) && $this->captcha_required() && !$this->isIncorrectCredential()) {
                $this->checkFillLogin();
                sleep(5);
            }
            if ($this->exts->allExists([$this->password_selector, 'input#auth-captcha-guess']) && $this->captcha_required() && !$this->isIncorrectCredential()) {
                $this->checkFillLogin();
                sleep(5);
            }
            if ($this->exts->allExists([$this->password_selector, 'input#auth-captcha-guess']) && $this->captcha_required() && !$this->isIncorrectCredential()) {
                $this->checkFillLogin();
                sleep(5);
            }
        }
        // End handling login form
        $this->checkFillTwoFactor();


        if ($this->exts->exists('form#auth-account-fixup-phone-form a#ap-account-fixup-phone-skip-link')) {
            $this->exts->moveToElementAndClick('form#auth-account-fixup-phone-form a#ap-account-fixup-phone-skip-link');
            sleep(2);
        }
    }

    if ($this->exts->exists('button.full-page-account-switcher-account-details')) {
        // This portal is for Germany so select UK first, else select default
        $target_selection = $this->exts->getElementByText('button.full-page-account-switcher-account-details', ['Germany', 'Deutschland', 'Allemagne'], null, true);
        if ($target_selection == null) {
            //Sometime we need to expand to see the list
            $this->exts->moveToElementAndClick('button.full-page-account-switcher-account-details');
            sleep(10);
            $target_selection = $this->exts->getElementByText('button.full-page-account-switcher-account-details', ['Germany', 'Deutschland', 'Allemagne'], null, true);
        }

        if ($target_selection == null && count($this->exts->getElements('button.full-page-account-switcher-account-details')) > 1) { // If do not found, get default picker
            $target_selection = $this->exts->getElements('button.full-page-account-switcher-account-details')[1];
        }
        if ($target_selection != null) {
            $this->exts->click_element($target_selection);
        }
        sleep(1);
        if ($this->exts->exists('button.kat-button--primary:not([disabled])')) {
            $this->exts->moveToElementAndClick('button.kat-button--primary:not([disabled])');
            sleep(10);
        } else {
            $this->exts->account_not_ready();
        }
    }

    // then check user logged in or not
    if ($this->isLoginSuccess()) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
			$this->exts->triggerLoginSuccess();
		}

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed ' . $this->exts->getUrl());
        $error_text = strtolower($this->exts->extract('div#auth-email-invalid-claim-alert div.a-alert-content'));

        $this->exts->log('::Error text' . $error_text);

        if ($this->isIncorrectCredential()) {
            $this->exts->loginFailure(1);
        } else if (stripos($error_text, strtolower('Wrong or Invalid e-mail address or mobile phone number. Please correct and try again.')) !== false) {
            $this->exts->loginFailure(1);
        } else if ($this->exts->exists('form[name="forgotPassword"]')) {
            $this->exts->account_not_ready();
        } else if ($this->exts->urlContains('/forgotpassword/reverification')) {
            $this->exts->account_not_ready();
        } else {
            $this->exts->loginFailure();
        }
    }
}
private function checkFillLogin()
{
    if ($this->exts->exists($this->password_selector)) {
        sleep(3);
        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(1);

        $this->exts->moveToElementAndClick('input#continue');
        sleep(5);

        if ($this->exts->exists($this->password_selector)) {
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);
            $this->exts->moveToElementAndClick('form[name="signIn"] input[name="rememberMe"]:not(:checked)');

            if ($this->exts->exists('input#auth-captcha-guess, input[name="cvf_captcha_input"], input[name="field-keywords"]')) {
                $captcha_inputted = $this->processCaptcha('img#auth-captcha-image, img[alt="captcha"], img[src*="captcha"]', 'input#auth-captcha-guess, input[name="cvf_captcha_input"], input[name="field-keywords"]');
                if ($captcha_inputted == false) {
                    $this->processCaptcha('img#auth-captcha-image', 'input#auth-captcha-guess');
                }
            }
            $this->exts->capture("2-login-page-filled");
            $this->exts->moveToElementAndClick($this->submit_login_selector);
            sleep(3);
            $this->exts->waitTillPresent('#auth-error-message-box', 30);
            if ($this->exts->exists('#auth-error-message-box')) {
                $this->exts->loginFailure(1);
            }
        }

        if ($this->exts->exists('input#auth-captcha-guess, input[name="cvf_captcha_input"], input[name="field-keywords"]')) {
            $captcha_inputted = $this->processCaptcha('img#auth-captcha-image, img[alt="captcha"], img[src*="captcha"]', 'input#auth-captcha-guess, input[name="cvf_captcha_input"], input[name="field-keywords"]');
            if ($captcha_inputted == false) {
                $this->processCaptcha('img#auth-captcha-image', 'input#auth-captcha-guess');
            }
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}
private function checkFillTwoFactor()
{
    $this->exts->capture("2.0-two-factor-checking");
    if ($this->exts->exists('#auth-select-device-form .auth-TOTP [name="otpDeviceContext"]')) { // Authenticator
        $this->exts->moveToElementAndClick('#auth-select-device-form .auth-TOTP [name="otpDeviceContext"]');
        sleep(2);
        $this->exts->moveToElementAndClick('input#auth-send-code');
        sleep(5);
    } else if ($this->exts->exists('div.auth-SMS input[type="radio"]')) {
        $this->exts->moveToElementAndClick('div.auth-SMS input[type="radio"]:not(:checked)');
        sleep(2);
        $this->exts->moveToElementAndClick('input#auth-send-code');
        sleep(5);
    } else if ($this->exts->exists('div.auth-TOTP input[type="radio"]')) {
        $this->exts->moveToElementAndClick('div.auth-TOTP input[type="radio"]:not(:checked)');
        sleep(2);
        $this->exts->moveToElementAndClick('input#auth-send-code');
        sleep(5);
    } else if ($this->exts->allExists(['input[type="radio"]', 'input#auth-send-code'])) {
        $this->exts->moveToElementAndClick('input[type="radio"]:not(:checked)');
        sleep(2);
        $this->exts->moveToElementAndClick('input#auth-send-code');
        sleep(5);
    }

    if ($this->exts->exists('input[name="otpCode"]:not([type="hidden"]), input[name="code"], input#input-box-otp')) {
        $two_factor_selector = 'input[name="otpCode"]:not([type="hidden"]), input[name="code"], input#input-box-otp';
        $two_factor_message_selector = '#auth-mfa-form h1 + p, #verification-code-form > .a-spacing-small > .a-spacing-none, #channelDetailsForOtp';
        $two_factor_submit_selector = '#auth-signin-button, #verification-code-form input[type="submit"]';
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        if ($this->exts->getElement($two_factor_message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = "";
            for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getAttribute('innerText') . "\n";
            }
            $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
        }
        if ($this->exts->two_factor_attempts == 2) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
        }
        $this->exts->notification_uid = "";
        $this->exts->two_factor_attempts++;
        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if (!empty($two_factor_code) && trim($two_factor_code) != '') {
            $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);

            // Press enter sometimes get change your password popup
            $this->exts->type_key_by_xdotool('Return');
            sleep(8);

            if ($this->exts->exists('input[name="otpCode"]:not([type="hidden"]), input[id="input-box-otp"]')) {
                $this->exts->moveToElementAndType('input[name="otpCode"]:not([type="hidden"]), input[id="input-box-otp"]', $two_factor_code);
            } else if ($this->exts->exists('input[name="otc-1"]')) {
                $this->exts->moveToElementAndClick('input[name="otc-1"]');
                $this->exts->type_text_by_xdotool($two_factor_code);
            }
            if ($this->exts->exists('label[for="auth-mfa-remember-device"] input[name="rememberDevice"]:not(:checked)')) {
                $this->exts->moveToElementAndClick('label[for="auth-mfa-remember-device"]');
            }
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

            $this->exts->log("checkFillTwoFactor: Clicking submit button.");
            sleep(1);

            if ($this->exts->exists('#cvf-submit-otp-button input[type="submit"]')) {
                $this->exts->moveToElementAndClick('#cvf-submit-otp-button input[type="submit"]');
            } else {
                $this->exts->moveToElementAndClick($two_factor_submit_selector);
            }
            sleep(10);
        } else {
            $this->exts->log("Not received two factor code");
        }

        // Huy added this 2022-12 Retry if incorrect code inputted
        if ($this->exts->exists($two_factor_selector)) {
            if (
                stripos($this->exts->extract('#auth-error-message-box .a-alert-content', null, 'innerText'), 'Der eingegebene Code ist ung') !== false ||
                stripos($this->exts->extract('#auth-error-message-box .a-alert-content', null, 'innerText'), 'you entered is not valid') !== false
            ) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
                for ($t = 2; $t <= 3; $t++) {
                    $this->exts->log("Retry 2FA Message:\n" . $this->exts->two_factor_notif_msg_en);
                    $this->exts->notification_uid = "";
                    $two_factor_code = trim($this->exts->fetchTwoFactorCode());
                    if (!empty($two_factor_code)) {
                        $this->exts->log("Retry 2FA: Entering two_factor_code: " . $two_factor_code);
                        $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
                        if ($this->exts->exists('label[for="auth-mfa-remember-device"] input[name="rememberDevice"]:not(:checked)')) {
                            $this->exts->moveToElementAndClick('label[for="auth-mfa-remember-device"]');
                        }
                        sleep(1);
                        $this->exts->capture("2.2-two-factor-filled-" . $t);
                        $this->exts->moveToElementAndClick($two_factor_submit_selector);
                        sleep(10);
                    } else {
                        $this->exts->log("Not received Retry two factor code");
                    }
                }
            }
        }
    } else if ($this->exts->exists('[name="transactionApprovalStatus"], form[action*="/approval/poll"]')) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");
        $message_selector = '.transaction-approval-word-break, #channelDetails, #channelDetailsWithImprovedLayout';
        $this->exts->two_factor_notif_msg_en = join(' ', $this->exts->getElementsAttribute($message_selector, 'innerText'));
        $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
        $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . "\n>>>Enter \"OK\" after confirmation";
        $this->exts->log($this->exts->two_factor_notif_msg_en);

        $this->exts->notification_uid = "";
        $this->exts->two_factor_attempts++;
        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if (!empty($two_factor_code) && trim($two_factor_code) != '') {
            $this->exts->moveToElementAndClick('#resend_notification_expander a[data-action="a-expander-toggle"]');
            sleep(1);
            // Click refresh page if user confirmed
            $this->exts->moveToElementAndClick('a.a-link-normal[href*="/ap/cvf/approval"], a#resend-approval-link');
        }
    }
}
private function isIncorrectCredential()
{
    $incorrect_credential_keys = [
        'Es konnte kein Konto mit dieser',
        'dass die eingegebene Nummer korrekt ist oder melde dich',
        't find an account with that',
        'Falsches Passwort',
        'password is incorrect',
        'password was incorrect',
        'Passwort war nicht korrekt',
        'Impossible de trouver un compte correspondant',
        'Votre mot de passe est incorrect',
        'Je wachtwoord is onjuist',
        'La tua password non',
        'a no es correcta',
        'The credentials you provided were incorrect. Check them and try again.'
    ];
    $error_message = $this->exts->extract('#auth-error-message-box');
    foreach ($incorrect_credential_keys as $incorrect_credential_key) {
        if (strpos(strtolower($error_message), strtolower($incorrect_credential_key)) !== false) {
            return true;
        }
    }
    return false;
}
private function processCaptcha($captcha_image_selector, $captcha_input_selector)
{
    $this->exts->waitTillPresent('img#auth-captcha-image, img[alt="captcha"], img[src*="captcha"]', 50);
    $this->exts->log("--IMAGE CAPTCHA--");
    if ($this->exts->exists($captcha_image_selector)) {
        $image_path = $this->exts->captureElement($this->exts->process_uid, $captcha_image_selector);
        $source_image = imagecreatefrompng($image_path);
        imagejpeg($source_image, $this->exts->screen_capture_location . $this->exts->process_uid . '.jpg', 90);

        if (!empty($this->exts->config_array['captcha_shell_script'])) {
            $cmd = $this->exts->config_array['captcha_shell_script'] . " --PROCESS_UID::" . $this->exts->process_uid;
            $this->exts->log('Executing command : ' . $cmd);
            exec($cmd, $output, $return_var);
            $this->exts->log('Command Result : ' . print_r($output, true));

            if (!empty($output)) {
                $output = $output[0];
                if (stripos($output, 'OK|') !== false) {
                    $captcha_code = trim(end(explode("OK|", $output)));
                } else {
                    $this->exts->log('1:processCaptcha::ERROR when get response:' . $output);
                }
            }
            if ($captcha_code == '') {
                $this->exts->log("Can not get result from API");
            } else {
                $this->exts->moveToElementAndType($captcha_input_selector, $captcha_code);
                return true;
            }
        }
    } else {
        $this->exts->log("Image does not found!");
    }

    return false;
}
private function captcha_required()
{
    // Supporting de, fr, en, es, it, nl language
    $captcha_required_keys = [
        'wie sie auf dem Bild erscheinen',
        'die in der Abbildung unten gezeigt werden',
        'Geben Sie die Zeichen so ein, wie sie auf dem Bild erscheinen',
        'the characters as they are shown in the image',
        'Enter the characters as they are given',
        'luego introduzca los caracteres que aparecen en la imagen',
        'Introduce los caracteres tal y como aparecen en la imagen',
        "dans l'image ci-dessous",
        "apparaissent sur l'image",
        'quindi digita i caratteri cos',
        'Inserire i caratteri cos',
        'en voer de tekens in zoals deze worden weergegeven in de afbeelding hieronder om je account',
        'Voer de tekens in die je uit veiligheidsoverwegingen moet'
    ];
    $error_message = $this->exts->extract('#auth-error-message-box, #auth-warning-message-box');
    foreach ($captcha_required_keys as $captcha_required_key) {
        if (strpos(strtolower($error_message), strtolower($captcha_required_key)) !== false) {
            return true;
        }
    }
    return false;
}
private function isLoginSuccess()
{
    $this->exts->waitTillPresent('a[href="/messaging/inbox"]');
    return $this->exts->exists('a[href="/messaging/inbox"]');
}
