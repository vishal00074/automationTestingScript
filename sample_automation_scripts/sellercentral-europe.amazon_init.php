public $baseUrl = 'https://sellercentral-europe.amazon.com/home';

public $username_selector = 'form[name="signIn"] input[name="email"]:not([type="hidden"])';
public $password_selector = 'form[name="signIn"] input[name="password"]';
public $submit_login_selector = 'form[name="signIn"] input[type="submit"],form[name="signIn"] input#signInSubmit';
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

    // assign value 1 to invoices hard coded for testing engine
    $this->seller_invoice = 1;
    $this->payment_settlements = 1;
    $this->transaction_invoices = 1; 
    $this->seller_fees = 1; 
    $this->no_advertising_bills = 1;  

    $this->exts->log('CONFIG seller_invoice: ' . $this->seller_invoice);
    $this->exts->log('CONFIG payment_settlements: ' . $this->payment_settlements);
    $this->exts->log('CONFIG transaction view: ' . $this->transaction_invoices);
    $this->exts->log('CONFIG seller fees: ' . $this->seller_fees);
    $this->exts->log('CONFIG No Advert Invoices: ' . $this->no_advertising_bills);

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

    if ($this->exts->exists('.picker-app .picker-item-column button.picker-button')) {
        $totalSelectorButtons = $this->exts->getElements('.picker-app .picker-item-column button.picker-button');
        try {
            $totalSelectorButtons[count($totalSelectorButtons) - 2]->click();
        } catch (\Exception $exception) {
            $this->exts->execute_javascript('arguments[0].click();', [$totalSelectorButtons[count($totalSelectorButtons) - 2]]);
        }
        sleep(1);
        if ($this->exts->exists('.picker-app button.picker-switch-accounts-button:not([disabled])')) {
            $this->exts->moveToElementAndClick('.picker-app button.picker-switch-accounts-button:not([disabled])');
            sleep(10);
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

    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed ' . $this->exts->getUrl());
        if ($this->isIncorrectCredential()) {
            $this->exts->loginFailure(1);
        } else if ($this->exts->exists('form[name="forgotPassword"]')) {
            $this->exts->account_not_ready();
        } else if (strpos($this->exts->extract('div#auth-error-message-box div.a-alert-content', null, 'innerText'), 'The credentials you provided were incorrect. Check them and try again.') !== false) {
            $this->exts->loginFailure(1);
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


        if ($this->exts->exists('input#auth-captcha-guess')) {
            $this->exts->processCaptcha('img#auth-captcha-image', 'input#auth-captcha-guess');
        }

        $this->exts->capture("2-login-email-filled");
        $this->exts->moveToElementAndClick($this->submit_login_selector);
        sleep(7);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(1);
        $this->exts->moveToElementAndClick('form[name="signIn"] input[name="rememberMe"]:not(:checked)');

        if ($this->exts->exists('input#auth-captcha-guess')) {
            $this->exts->processCaptcha('img#auth-captcha-image', 'input#auth-captcha-guess');
        }
        $this->exts->capture("2-login-page-filled");
        $this->exts->moveToElementAndClick($this->submit_login_selector);
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}
private function checkFillTwoFactor()
{
    $this->exts->capture("2.0-two-factor-checking");
    if ($this->exts->exists('div.auth-SMS input[type="radio"]')) {
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

    if ($this->exts->exists('input[name="otpCode"]')) {
        $two_factor_selector = 'input[name="otpCode"]';
        $two_factor_message_selector = '#auth-mfa-form h1 + p';
        $two_factor_submit_selector = '#auth-signin-button';
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        if ($this->exts->getElement($two_factor_message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = "";
            for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getText() . "\n";
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
            $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
            if ($this->exts->exists('label[for="auth-mfa-remember-device"] input[name="rememberDevice"]:not(:checked)')) {
                $this->exts->moveToElementAndClick('label[for="auth-mfa-remember-device"]');
            }
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

            $this->exts->log("checkFillTwoFactor: Clicking submit button.");
            sleep(1);
            $this->exts->moveToElementAndClick($two_factor_submit_selector);
            sleep(10);
        } else {
            $this->exts->log("Not received two factor code");
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
        'One Time Password (OTP) you entered is not valid.'
    ];
    $error_message = $this->exts->extract('#auth-error-message-box');
    foreach ($incorrect_credential_keys as $incorrect_credential_key) {
        if (strpos(strtolower($error_message), strtolower($incorrect_credential_key)) !== false) {
            return true;
        }
    }
    return false;
}
private function captcha_required()
{
    // Supporting de, fr, en, es, it, nl language
    $captcha_required_keys = [
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
    return $this->exts->exists('.nav-right-section [data-test-tag="nav-settings-button"], li.sc-logout-quicklink, .sc-header #partner-switcher button.dropdown-button, #sc-quicklinks #sc-quicklink-logout, .authenticated-header a[href*="/logout.html"], .picker-app .picker-item-column button.picker-button') && !$this->exts->exists($this->password_selector);
}