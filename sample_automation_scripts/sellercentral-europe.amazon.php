<?php // migrated and update download code // updated login failure code and download code // updated login code
// Server-Portal-ID: 156697 - Last modified: 22.08.2024 14:42:23 UTC - User: 1

/*Define constants used in script*/
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

        // country picker
        if ($this->exts->exists('button.full-page-account-switcher-account-details')) {
            $countrySelectorButtons = $this->exts->getElements('button.full-page-account-switcher-account-details');
            try {
                $countrySelectorButtons[count($countrySelectorButtons) - 2]->click();
            } catch (\Exception $exception) {
                $this->exts->execute_javascript('arguments[0].click();', [$countrySelectorButtons[count($countrySelectorButtons) - 2]]);
            }
            sleep(1);
            if ($this->exts->exists('button[class*="kat-button"]:not([disabled])')) {
                $this->exts->moveToElementAndClick('button[class*="kat-button"]:not([disabled])');
                sleep(10);
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
        $this->doAfterLogin();
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
    if($this->exts->execute_javascript('document.body.innerHTML.includes("/gp/sign-in/logout.html");')){
        return true;
    }
    if($this->exts->execute_javascript('document.body.innerHTML.includes("/sign-out");')){
        return true;
    }
    
    return $this->exts->exists('.nav-right-section [data-test-tag="nav-settings-button"], li.sc-logout-quicklink, .sc-header #partner-switcher button.dropdown-button, #sc-quicklinks #sc-quicklink-logout, .authenticated-header a[href*="/logout.html"], .picker-app .picker-item-column button.picker-button') && !$this->exts->exists($this->password_selector);
}

private function doAfterLogin()
{
    if ($this->exts->exists('#remind-me-later span.a-button')) {
        $this->exts->moveToElementAndClick('#remind-me-later span.a-button');
        sleep(10);
    }

    // Download from seller-vat-invoices
    if ((int)@$this->seller_invoice == 1) {
        $this->exts->openUrl('https://sellercentral-europe.amazon.com/tax/vatreports/bulkdownload');
        $this->downloadSellerVATInvoice(1);

        //Download Order Invoices
        $this->exts->moveToElementAndClick('#navbar .nav-button[role="button"]');
        sleep(2);
        if ($this->exts->exists('.side-nav a[href*="/orders-v3/ref=xx_"]')) {
            $this->exts->moveToElementAndClick('.side-nav a[href*="/orders-v3/ref=xx_"]');
            sleep(10);

            $this->exts->moveToElementAndClick('a[data-test-id="tab-/mfn/shipped"], a[href*="/orders-v3/mfn/shipped?"]');
        } else {
            $this->exts->openUrl('https://sellercentral-europe.amazon.com/orders-v3/mfn/shipped?page=1');
        }
        sleep(10);
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        if ($restrictPages == '0') {
            // $this->exts->changeSelectbox('select[name="myo-table-date-range"]', 'last-365', 15);

                $this->exts->execute_javascript('
                let selectBox = document.querySelector("select[name=\'myo-table-date-range\']");
                if (selectBox) {
                    selectBox.value = "last-365";
                    selectBox.dispatchEvent(new Event("change"));
                }
            ');

        } else {
            // $this->exts->changeSelectbox('select[name="myo-table-date-range"]', 'last-90', 15);

            $this->exts->execute_javascript('
                let selectBox = document.querySelector("select[name=\'myo-table-date-range\']");
                if (selectBox) {
                    selectBox.value = "last-90";
                    selectBox.dispatchEvent(new Event("change"));
                }
            ');

        }
        sleep(5);
        // $this->exts->changeSelectbox('select[name="myo-table-results-per-page"]', '100', 15);
        $this->exts->execute_javascript('
            let selectBox = document.querySelector("select[name=\'myo-table-results-per-page\']");
            if (selectBox) {
                selectBox.value = "100";
                selectBox.dispatchEvent(new Event("change"));
            }
        ');

        if (!$this->exts->exists('#orders-table tbody tr td')) {
            sleep(15);
        }
        $this->downloadOrderInvoices(1);
    }

    $this->exts->openUrl('https://sellercentral-europe.amazon.com/home');
    sleep(15);

    $Urldomain = "sellercentral-europe.amazon.com";

    if ($this->exts->getElement('a[href*="/gp/payments-account/settlement-summary.html/ref=xx_payments_tnav_xx"]')) {
        $this->exts->moveToElementAndClick('a[href*="/gp/payments-account/settlement-summary.html/ref=xx_payments_tnav_xx"]');
        sleep(15);
    } else if ($this->exts->getElement('a[href*="/gp/payments-account/settlement-summary.html/ref=xx_payments_"]')) {
        $this->exts->moveToElementAndClick('a[href*="/gp/payments-account/settlement-summary.html/ref=xx_payments_"]');
        sleep(15);
    } else {
        $this->exts->openUrl('https://' . $Urldomain . '/payments/reports/summary');
        sleep(15);
    }

    $advertDocsExists = false;
    if ($this->exts->exists('a[href*="/gp/advertiser/transactions/transactions.html"]')) {
        $advertDocsExists = true;
    }
    if ((int)@$this->transaction_invoices == 1) {
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        // Download from transaction page
        if ($restrictPages == 0) {
            $startDate = strtotime('-1 years') . '000';
        } else {
            $startDate = strtotime('-2 months') . '000';
        }
        $endDate = strtotime('now') . '000';
        $transaction_url = 'https://sellercentral-europe.amazon.com/payments/event/view?startDate=' . $startDate . '&endDate=' . $endDate . '&resultsPerPage=50&pageNumber=1';
        $this->exts->log('TRANSACTION URL: ' . $transaction_url);
        $this->exts->openUrl($transaction_url);
        $this->downloadTransaction();
    }

    // Download from advertiser invoices
    if ((int)@$this->no_advertising_bills != 1 && $advertDocsExists) {
        $this->exts->openUrl('https://' . $Urldomain . '/gp/advertiser/transactions/transactions.html');
        $this->downloadAdvertiserInvoices();
    }

    // Download from statement page
    if ((int)@$this->payment_settlements == 1) {
        $this->exts->openUrl('https://sellercentral-europe.amazon.com/payments/dashboard/index.html');
        sleep(7);
        $this->exts->moveToElementAndClick('[tab-id="ALL_STATEMENTS"]');
        $this->exts->refresh();

        $this->downloadStatements();
    }

    // Download from seller-fee-invoices
    if ((int)@$this->seller_fees == 1) {
        $this->exts->openUrl('https://' . $Urldomain . '/tax/seller-fee-invoices');
        sleep(15);
        $this->downloadSellerFeeInvoice();
    }

    // Final, check no invoice
    if ($this->isNoInvoice) {
        $this->exts->no_invoice();
    }
}
private function downloadTransaction($pageCount = 1)
{
    sleep(10);
    if ($this->exts->exists('[aria-modal="true"] button[data-action="close"]')) {
        $this->exts->moveToElementAndClick('[aria-modal="true"] button[data-action="close"]');
    }
    $this->exts->capture("4-transaction-page");
    // 2021-12, maybe code in below if block is no longer work since this site changed, but still keep it as Mukesh request.
    if ($this->exts->exists('table > tbody > tr a[href*="/transaction-details.html?"]')) {
        $invoices = [];
        $rows = $this->exts->getElements('table > tbody > tr');
        $this->exts->log("Number of transactions rows - " . count($rows));
        foreach ($rows as $row) {
            $tags = $this->exts->getElements('td', $row);
            if (count($tags) >= 9 && $this->exts->getElement('a[href*="/transaction-details.html?"]', end($tags)) != null) {
                $invoiceUrl = $this->exts->getElement('a[href*="/transaction-details.html?"]', end($tags))->getAttribute("href");
                $invoiceName = explode(
                    '&',
                    array_pop(explode('transaction_id=', $invoiceUrl))
                )[0];
                $invoiceName = preg_replace("/[^\w]/", '', $invoiceName);
                $invoiceDate = trim($tags[0]->getAttribute('innerText'));
                $amountText = trim(end($tags)->getAttribute('innerText'));
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

                $invoiceAltName = trim($tags[2]->getAttribute('innerText'));
                $checkText = preg_replace('/[^\d\.\,]/', '', $invoiceAltName);
                if ($invoiceAltName == "---" || empty($checkText) || trim($checkText) == "") {
                    $invoiceAltName = $invoiceName;
                }
                if (!$this->exts->invoice_exists($invoiceName) || !$this->exts->invoice_exists($invoiceAltName)) {
                    array_push($invoices, array(
                        'invoiceName' => ($invoiceAltName != "" && $invoiceAltName != "---") ? $invoiceAltName : $invoiceName,
                        'invoiceDate' => $invoiceDate,
                        'invoiceAmount' => $invoiceAmount,
                        'invoiceUrl' => $invoiceUrl
                    ));
                } else {
                    $this->exts->log('Invoice existed ' . $invoiceName);
                }
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
            $parsed_date = $this->exts->parse_date($invoice['invoiceDate'], 'd M Y', 'Y-m-d');
            if ($parsed_date == '') {
                $parsed_date = $this->exts->parse_date($invoice['invoiceDate'], 'd#m#Y', 'Y-m-d');
            }
            if ($parsed_date == '') {
                $parsed_date = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
            }
            if ($parsed_date == '') {
                $parsed_date = $this->exts->parse_date($invoice['invoiceDate'], 'j M# y', 'Y-m-d');
            }
            $this->exts->log('Date parsed: ' . $parsed_date);

            $this->exts->open_new_window();
            $this->exts->openUrl($invoice['invoiceUrl']);
            sleep(5);

            //Check and Fill login page
            if ($this->exts->getElement($this->password_selector) != null) {
                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(1);

                if ($this->exts->getElement($this->remember_me) != null) {
                    $checkboxElements = $this->exts->getElements($this->remember_me);
                    // $bValue = false;
                    // if (count($checkboxElements) > 0) {
                    //     $bValue = $checkboxElements[0]->isSelected();
                    //     if ($bValue == false) {
                    //         $checkboxElements[0]->sendKeys(WebDriverKeys::SPACE);
                    //     }
                    // }
                }

                $this->exts->capture("2-login-page-filled");
                $this->exts->moveToElementAndClick($this->submit_login_selector);
                sleep(5);
            }
            if (!$this->isLoginSuccess()) {
                $this->exts->init_required();
            }
            sleep(5);
            $this->exts->execute_javascript('
				document.querySelectorAll(\'div#container div#predictive-help\')[0].remove();
				document.querySelectorAll(\'div#sc-top-nav\')[0].remove();
				document.querySelectorAll(\'div#sc-footer-container\')[0].remove();
				document.querySelectorAll(\'div#left-side\')[0].setAttribute("style","float:left; text-align:left; width:100%;");
			');

            $downloaded_file = $this->exts->download_current($invoiceFileName, 3);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $parsed_date, $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }

            $handles = $this->exts->get_all_tabs();
            if (count($handles) > 1) {
                $lastTab = end($handles); 
                $this->exts->closeTab($lastTab);

                $handles = $this->exts->get_all_tabs();

                if (!empty($handles)) {
                    $this->exts->switchToTab($handles[0]); 
                }
            }
        }


        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        if ($restrictPages == 0 && $pageCount < 100 && $this->exts->getElement('.currentpagination + a') != null) {
            if (count($invoices) == 0) {
                $this->exts->update_process_lock();
            }
            $pageCount++;
            $this->exts->execute_javascript('
				document.querySelectorAll(\'.currentpagination + a\')[0].click();
			');
            //$this->exts->moveToElementAndClick('.currentpagination + a');
            sleep(5);
            $this->downloadTransaction($pageCount);
        }
    } else if ($this->exts->exists('.transactions-table-content [role="row"]')) {
        // Huy added 2021-12
        for ($paging_count = 1; $paging_count < 100; $paging_count++) {
            $invoices = [];
            $rows = count($this->exts->getElements('.transactions-table-content [role="row"]'));
            for ($i = 0; $i < $rows; $i++) {
                $row = $this->exts->getElements('.transactions-table-content [role="row"]')[$i];
                $detail_button = $this->exts->getElement('a#link-target', $row);
                if ($detail_button != null) {
                    $this->isNoInvoice = false;
                    $invoiceName =  $this->exts->extract('[role="cell"]:nth-child(3)', $row);
                    $invoiceName = trim($invoiceName);
                    $invoiceFileName = $invoiceName . '.pdf';
                    $invoiceDate = $this->exts->extract('[role="cell"]:nth-child(1)', $row);
                    $amountText = $this->exts->extract('a#link-target', $row);
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
                    $parsed_date = $this->exts->parse_date($invoiceDate, 'd-M-Y', 'Y-m-d');
                    $this->exts->log('Date parsed: ' . $parsed_date);

                    // Download invoice if it not exisited
                    if ($this->exts->invoice_exists($invoiceName) || $this->exts->document_exists($invoiceFileName)) {
                        $this->exts->log('Invoice existed ' . $invoiceFileName);
                    } else {
                        try {
                            $this->exts->log('Click detail button');
                            $detail_button->click();
                        } catch (\Exception $exception) {
                            $this->exts->log('Click detail button by javascript');
                            $this->exts->execute_javascript("arguments[0].click()", [$detail_button]);
                        }
                        sleep(1);
                        $this->exts->waitTillPresent('#sc-content-container .transaction-details-body-section .event-details-body');
                        if ($this->exts->exists('#sc-content-container .transaction-details-body-section .event-details-body')) {
                            // Clear some alert, popup..etc
                            $this->exts->execute_javascript('
				            	if(document.querySelector("kat-alert") != null){
								   document.querySelector("kat-alert").shadowRoot.querySelector("[part=alert-dismiss-button]").click();
								}
				            ');
                            $this->exts->moveToElementAndClick('.katHmdCancelBtn');
                            // END clearing alert..

                            // Capture page if detail displayed
                            $this->exts->execute_javascript('
								var divs = document.querySelectorAll("body > div > *:not(#sc-content-container)");
								for( var i = 0; i < divs.length; i++){
									divs[i].style.display = "none";
								}
							');

                            $downloaded_file = $this->exts->download_current($invoiceFileName, 0);
                            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                                $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $downloaded_file);
                            } else {
                                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                            }
                        } else {
                            $this->exts->capture("4-transaction-detail-error");
                        }

                        // back to transaction list
                        $this->exts->moveToElementAndClick('.transaction-details-footer-section a#link-target');
                        sleep(2);
                    }
                    $this->isNoInvoice = false;
                }
            }

            // Process next page
            // This page using shadow element, We must process via JS
            $is_next = $this->exts->execute_javascript('
				try {
					document.querySelector("kat-pagination").shadowRoot.querySelector("[part=pagination-nav-right]:not(.end)").click();
					return true;
				} catch(ex){
					return false;	
				}
			');
            if ($is_next && $this->exts->config_array["restrictPages"] == '0') {
                sleep(7);
            } else {
                break;
            }
        }
    }
}
private function downloadSellerFeeInvoice()
{
    sleep(25);
    $this->exts->capture("4-seller-fee-invoices-page");

    $total_fee_downloaded = 0;
    $rows = $this->exts->getElements('table > tbody > tr');
    $this->exts->log("Number of seller fees rows - " . count($rows));
    foreach ($rows as $row) {
        $tags = $this->exts->getElements('td', $row);
        if (count($tags) >= 14 && $this->exts->getElement('button[data-invoice]', end($tags)) != null) {
            $invoice_button = $this->exts->getElement('button[data-invoice]', end($tags));
            $invoiceName = $invoice_button->getAttribute('data-invoice');
            $invoiceFileName = $invoiceName . '.pdf';
            $invoiceDate = trim($tags[count($tags) - 3]->getText());
            $invoiceAmount = '';

            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoiceName);
            $this->exts->log('invoiceDate: ' . $invoiceDate);
            $this->exts->log('invoiceAmount: ' . $invoiceAmount);
            $invoiceDate = $this->exts->parse_date($invoiceDate, 'D M d H:i:s * Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoiceDate);

            // Download invoice if it not exisited
            if ($this->exts->invoice_exists($invoiceName)) {
                $this->exts->log('Invoice existed ' . $invoiceFileName);
            } else {
                try {
                    $this->exts->log('Click download button');
                    $invoice_button->click();
                } catch (\Exception $exception) {
                    $this->exts->log('Click download button by javascript');
                    $this->exts->execute_javascript("arguments[0].click()", [$invoice_button]);
                }
                sleep(10);
                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                    sleep(1);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No pdf ' . $invoiceFileName);
                    // Invoice maybe old and it is html, implement code if user require them
                }

                // close new tab too avoid too much tabs
                $handles = $this->exts->get_all_tabs();
                if (count($handles) > 1) {
                    $lastTab = end($handles);
                    $this->exts->closeTab($lastTab);

                    $handles = $this->exts->get_all_tabs();

                    if (!empty($handles)) {
                        $this->exts->switchToTab($handles[0]);
                    }
                }
            }
            $total_fee_downloaded++;
            $this->isNoInvoice = false;
        }
        if ($total_fee_downloaded >= 50) break;
    }
}
private function downloadSellerVATInvoice($pageCount = 1)
{
    sleep(10);
    $date_format = "m.d.Y";
    $startDate = date($date_format, strtotime('-7 days'));
    if ($this->exts->exists('select[name="reportType"]') && $pageCount == 1) {
        // $this->exts->changeSelectbox('select[name="reportType"]', "VAT Invoices");
        $this->exts->execute_javascript('let selectBox = document.querySelector("select[name="reportType"]");
        selectBox.value = "VAT Invoices";
        selectBox.dispatchEvent(new Event("change"));');

        sleep(10);
        if ($this->exts->exists('li#vtr-start-date2 input#vtr-start-date-calendar2')) {
            $currentStart_date = $this->exts->getElement('li#vtr-start-date2 input#vtr-start-date-calendar2')->getAttribute('aria-label');

            if (stripos($currentStart_date, "m/d") !== false || stripos($currentStart_date, "d/y") !== false) {
                $date_format = "m/d/Y";
            } else if (stripos($currentStart_date, "m-d") !== false || stripos($currentStart_date, "d-y") !== false) {
                $date_format = "m-d-Y";
            }
            $this->exts->log(__FUNCTION__ . '::Date format ' . $date_format);
            $startDate = date($date_format, strtotime('-7 days'));
            $endDate = date($date_format);

            $this->exts->moveToElementAndType('li#vtr-start-date2 input#vtr-start-date-calendar2', $startDate);
            sleep(1);
            $this->exts->moveToElementAndType('li#vtr-end-date1 input#vtr-end-date-calendar1', $endDate);
            sleep(1);
            $this->exts->capture("4-seller-vat-1-filter");
            $this->exts->moveToElementAndClick('form#vtr-request-report-form-Bulk-Downlaod input#generate-report-button[type="submit"]');
            sleep(15);
            $this->exts->capture("4-seller-vat-2-submitted");
            $this->exts->openUrl('https://sellercentral-europe.amazon.com/tax/vatreports/bulkdownload');
            sleep(60);
        }
    }

    $this->exts->capture("4-seller-vat-invoices-page");

    $invoices = [];
    $rows = $this->exts->getElements('table > tbody > tr');
    $this->exts->log("Number of VAT Invoice rows - " . count($rows));
    foreach ($rows as $row) {
        $tags = $this->exts->getElements('td', $row);
        if (count($tags) >= 4 && $this->exts->getElement('a[href*="/invoice/download/id/"]', $tags[3]) != null) {
            $invoiceUrl = $this->exts->getElement('a[href*="/invoice/download/id/"]', $tags[3])->getAttribute("href");
            $invoiceName = explode(
                '/',
                array_pop(explode('/id/', $invoiceUrl))
            )[0];
            $invoiceName = preg_replace('/[^\w]/', '', $invoiceName);
            $invoiceDate = trim($tags[1]->getText());
            $invoiceAmount = '';

            $downloadBtn = $this->exts->getElement('a[href*="/invoice/download/id/"]', $tags[3]);

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
    $this->exts->log('Seller VAT Invoices found: ' . count($invoices));
    foreach ($invoices as $invoice) {
        $this->exts->log('--------------------------');
        $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
        $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
        $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
        $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

        $invoiceFileName = $invoice['invoiceName'] . '.zip';
        $parsed_date = $this->exts->parse_date($invoice['invoiceDate'], 'd M Y', 'Y-m-d');
        if ($parsed_date == '') {
            $parsed_date = $this->exts->parse_date($invoice['invoiceDate'], 'd# M Y', 'Y-m-d');
        }
        if ($parsed_date == '') {
            $parsed_date = $this->exts->parse_date($invoice['invoiceDate'], 'M d# Y', 'Y-m-d');
        }
        $this->exts->log('Date parsed: ' . $parsed_date);

        $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'zip', $invoiceFileName);
        if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
            $this->exts->new_invoice($invoice['invoiceName'], $parsed_date, $invoice['invoiceAmount'], $downloaded_file);
            sleep(1);
        } else {
            try {
                $this->exts->log('Click download button');
                $invoice['downloadBtn']->click();
            } catch (\Exception $exception) {
                $this->exts->log('Click download button by javascript');
                $this->exts->execute_javascript("arguments[0].click()", [$invoice['downloadBtn']]);
            }
            sleep(15);
            $this->exts->wait_and_check_download('pdf');
            $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $parsed_date, $invoice['invoiceAmount'], $downloaded_file);
                sleep(1);
            } else {
                sleep(15);
                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoice['invoiceName'], $parsed_date, $invoice['invoiceAmount'], $downloaded_file);
                    sleep(1);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            }
        }
    }

    $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
    if ($restrictPages == 0 && $pageCount < 25 && $this->exts->getElement('#formForNextPage button#nextButton') != null) {
        if (count($invoices) == 0) {
            $this->exts->update_process_lock();
        }
        $pageCount++;
        $this->exts->moveToElementAndClick('#formForNextPage button#nextButton');
        sleep(5);
        $this->downloadSellerVATInvoice($pageCount);
    }
}
private function downloadStatements($pageCount = 1)
{
    sleep(15);
    if ($this->exts->exists('[aria-modal="true"] button[data-action="close"]')) {
        $this->exts->moveToElementAndClick('[aria-modal="true"] button[data-action="close"]');
    }
    $this->exts->capture("4-statements-page");
    if ($this->exts->exists('table > tbody > tr a[href*="/settlement-summary"]')) {
        $invoices = [];
        $rows = $this->exts->getElements('table > tbody > tr');
        foreach ($rows as $row) {
            $tags = $this->exts->getElements('td', $row);
            if (count($tags) >= 7 && $this->exts->getElement('a[href*="/settlement-summary"]', end($tags)) != null && $this->exts->getElement('a[href*="/payments/reports/download?"]', end($tags)) != null) {
                $invoiceUrl = $this->exts->getElement('a[href*="/settlement-summary"]', end($tags))->getAttribute("href");
                $invoiceName = explode(
                    '&',
                    array_pop(explode('groupId=', $invoiceUrl))
                )[0];
                $invoiceName = preg_replace("/[^\w]/", '', $invoiceName);
                $invoiceDate = trim(end(explode(' - ', $tags[0]->getText())));
                $amountText = trim($tags[count($tags) - 2]->getText());
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

                $invoiceAltName = "Seller-Invoice" . $invoiceDate;
                if (!$this->exts->invoice_exists($invoiceName) && !$this->exts->invoice_exists($invoiceAltName)) {
                    array_push($invoices, array(
                        'invoiceName'   => $invoiceName,
                        'invoiceDate'   => $invoiceDate,
                        'invoiceAmount' => $invoiceAmount,
                        'invoiceUrl'    => $invoiceUrl
                    ));
                    $this->isNoInvoice = false;
                } else {
                    $this->exts->log("Invoice exists - " . $invoiceName);
                }
            }
        }

        // Download all invoices
        $this->exts->log('Statements found: ' . count($invoices));
        foreach ($invoices as $invoice) {
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoiceFileName = $invoice['invoiceName'] . '.pdf';
            $parsed_date = $this->exts->parse_date($invoice['invoiceDate'], 'd M Y', 'Y-m-d');
            if ($parsed_date == '') {
                $parsed_date = $this->exts->parse_date($invoice['invoiceDate'], 'd#m#Y', 'Y-m-d');
            }
            if ($parsed_date == '') {
                $parsed_date = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
            }
            if ($parsed_date == '') {
                $parsed_date = $this->exts->parse_date($invoice['invoiceDate'], 'j M# y', 'Y-m-d');
            }
            $this->exts->log('Date parsed: ' . $parsed_date);

            $this->exts->open_new_window();
            $this->exts->openUrl($invoice['invoiceUrl']);
            sleep(5);

            $this->checkFillLogin();
            if (!$this->isLoginSuccess()) {
                $this->checkFillTwoFactor();

                if (!$this->isLoginSuccess()) {
                    $this->exts->init_required();
                }
            }

            if (count($this->exts->getElements('#printableSections')) > 0) {
                $this->exts->execute_javascript('
					var printableView = document.getElementById("printableSections");
					var allLinks = document.getElementsByTagName("link");
					var allStyles = document.getElementsByTagName("style");
					var printableHTML = Array.from(allLinks).map(link => link.outerHTML).join("")
										+ Array.from(allStyles).map(link => link.outerHTML).join("")
										+ printableView.outerHTML;
					document.body.innerHTML = printableHTML;
				');

                $downloaded_file = $this->exts->download_current($invoiceFileName, 3);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoice['invoiceName'], $parsed_date, $invoice['invoiceAmount'], $downloaded_file);
                    sleep(1);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            } else if (count($this->exts->getElements('#sc-navbar-container')) > 0) {
                $this->exts->execute_javascript('
					document.querySelectorAll("#sc-navbar-container")[0].remove();
					document.querySelectorAll("article.dashboard-header")[0].remove();
					document.querySelectorAll(".sc-footer")[0].remove();
				');

                $downloaded_file = $this->exts->download_current($invoiceFileName, 3);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoice['invoiceName'], $parsed_date, $invoice['invoiceAmount'], $downloaded_file);
                    sleep(1);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            } else {
                $this->exts->log(__FUNCTION__ . '::Page design is changed for print ' . $invoiceFileName);
            }

            // close new tab too avoid too much tabs
            $handles = $this->exts->get_all_tabs();
            if (count($handles) > 1) {
                $lastTab = end($handles);
                $this->exts->closeTab($lastTab);

                $handles = $this->exts->get_all_tabs();

                if (!empty($handles)) {
                    $this->exts->switchToTab($handles[0]);
                }
            }
        }

        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        if (
            $restrictPages == 0 &&
            $pageCount < 100 &&
            $this->exts->getElement('.currentpagination + a') != null
        ) {
            if (count($invoices) == 0) {
                $this->exts->update_process_lock();
            }

            $pageCount++;
            //$this->exts->moveToElementAndClick('.currentpagination + a');
            $this->exts->execute_javascript('
				document.querySelectorAll(\'.currentpagination + a\')[0].click();
			');
            sleep(15);
            $this->downloadStatements($pageCount);
        }
    } else if ($this->exts->exists('kat-data-table tbody tr kat-link[href*="/detail"], kat-data-table tbody tr .dashboard-link kat-link[href*="/"]')) { // updated 202203
        // Huy added this 2021-12
        if ($this->exts->config_array["restrictPages"] == '0') {
            $currentPageHeight = 0;
            for ($i = 0; $i < 15 && $currentPageHeight != $this->exts->execute_javascript('return document.body.scrollHeight;'); $i++) {
                $this->exts->log('Scroll to bottom ' . $currentPageHeight);
                $currentPageHeight = $this->exts->execute_javascript('return document.body.scrollHeight;');
                $this->exts->execute_javascript('window.scrollTo(0,document.body.scrollHeight);');
                sleep(7);
            }
            sleep(5);
        }

        // It using shadow root, so collect invoice detail by JS
        $invoices = $this->exts->execute_javascript('
			var data = [];
			var trs = document.querySelectorAll("kat-data-table tbody tr .dashboard-link kat-link[href*=groupId]");
		
			// Skip first row because it is current period, do not get it
			for (var i = 1; i < trs.length; i ++) {
				var link = trs[i].shadowRoot.querySelector("a");
                var url = link.href;

				data.push({
					invoiceName: url.split("groupId=").pop().split("&")[0],
					invoiceDate: "",
					invoiceAmount: "",
					invoiceUrl: url
				});
			}
			return data;
		');
        // Download all invoices
        $this->exts->log('Statements found: ' . count($invoices));
        foreach ($invoices as $invoice) {
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);
            $invoiceFileName = $invoice['invoiceName'] . '.pdf';
            $this->isNoInvoice = false;

            // Download invoice if it not exisited
            if ($this->exts->invoice_exists($invoice['invoiceName']) || $this->exts->document_exists($invoiceFileName)) {
                $this->exts->log('Invoice existed ' . $invoiceFileName);
            } else {
                $this->exts->open_new_window();
                $this->exts->openUrl($invoice['invoiceUrl']);
                sleep(2);
                $this->checkFillLogin();
                if (!$this->isLoginSuccess()) {
                    $this->checkFillTwoFactor();
                }

                if ($this->exts->exists('.dashboard-content #print-this-page-link')) {
                    // Clear some alert, popup..etc
                    $this->exts->execute_javascript('
		            	if(document.querySelector("kat-alert") != null){
						   document.querySelector("kat-alert").shadowRoot.querySelector("[part=alert-dismiss-button]").click();
						}
		            ');
                    $this->exts->moveToElementAndClick('.katHmdCancelBtn');
                    // END clearing alert..

                    $this->exts->moveToElementAndClick('.dashboard-content #print-this-page-link');
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoice['invoiceName'], '', '', $downloaded_file);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
                } else {
                    $this->exts->capture('statement-detail-error');
                }

                // close new tab too avoid too much tabs
                $handles = $this->exts->get_all_tabs();
                if (count($handles) > 1) {
                    $lastTab = end($handles);
                    $this->exts->closeTab($lastTab);

                    $handles = $this->exts->get_all_tabs();

                    if (!empty($handles)) {
                        $this->exts->switchToTab($handles[0]);
                    }
                }
                $handles = $this->exts->get_all_tabs();
                if (count($handles) > 1) {
                    $lastTab = end($handles);
                    $this->exts->closeTab($lastTab);

                    $handles = $this->exts->get_all_tabs();

                    if (!empty($handles)) {
                        $this->exts->switchToTab($handles[0]);
                    }
                }
            }
        }
    }
}
private function downloadAdvertiserInvoices($partnerId = '')
{
    sleep(25);
    if ($this->exts->exists('button[class*="CloseButton"]')) {
        $this->exts->moveToElementAndClick('button[class*="CloseButton"]');
        sleep(2);
    }
    $this->exts->capture("4-advertiser-invoices-page" . $partnerId);

    //if($this->exts->getElement('select#sc-mkt-picker-switcher-select option.sc-mkt-picker-switcher-select-option[value*="'.trim($this->currentSelectedMarketPlace).'"]') != null || (int)@$this->no_marketplace == 0) {
    /* With New Design Marketplace check is not possible
    if((int)@$this->no_marketplace != 0) {
        $this->exts->log("Checking marketplace is swtiched correctly");
        $selectedMarketplace = $this->exts->getElement('select#sc-mkt-picker-switcher-select option.sc-mkt-picker-switcher-select-option[value*="'.trim($this->currentSelectedMarketPlace).'"]')->getAttribute('selected');
        $this->exts->log("Selected Marketplace - ".$selectedMarketplace);
    } else {
        $this->exts->log("Selected Marketplace - No MarketPlace");
    }*/
    $selectedMarketplace = $this->currentSelectedMarketPlace;
    $this->exts->log("Selected Marketplace - " . $selectedMarketplace);
    $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
    //Remove this check because now checking marketplace is not possible in billing page
    //if($selectedMarketplace != null || (int)@$this->no_marketplace == 0) {
    if ($this->exts->exists('.calendarsContainer input#startCal')) {
        $currentStart_date = $this->exts->getElement('.calendarsContainer input#startCal')->getAttribute('aria-label');
        $date_format = "d.m.Y";
        if (stripos($currentStart_date, "d/m") !== false || stripos($currentStart_date, "m/y") !== false) {
            $date_format = "d/m/Y";
        } else if (stripos($currentStart_date, "d-m") !== false || stripos($currentStart_date, "m-y") !== false) {
            $date_format = "d-m-Y";
        }
        $this->exts->log(__FUNCTION__ . '::Date format ' . $date_format);
        // if restrictpages == 0 then 2 years otherwise 2 month
        $endDate = date($date_format);
        if ($restrictPages == 0) {
            $startDate = date($date_format, strtotime('-2 years'));
        } else {
            $startDate = date($date_format, strtotime('-2 months'));
        }

        $this->exts->moveToElementAndType('.calendarsContainer input#startCal', $startDate);
        sleep(1);
        $this->exts->moveToElementAndType('.calendarsContainer input#endCal', $endDate);
        sleep(1);
        $this->exts->capture("4-advertiser-invoices-1-filter");
        $this->exts->moveToElementAndClick('.calendarsContainer [type="submit"]');
        sleep(15);
        $this->exts->capture("4-advertiser-invoices-2-submitted");
    } else if ($this->exts->exists('.calendarsContainer button')) {
        $this->exts->moveToElementAndClick('.calendarsContainer button');
        sleep(1);
        if ($restrictPages != 0) {
            $this->exts->moveToElementAndClick('.calendarsContainer button[value="last90Days"]');
            sleep(15);
        }
    }

    // get invoice
    for ($paging_count = 1; $paging_count < 100; $paging_count++) {
        $invoices = [];
        $rows = count($this->exts->getElements('table#paidTable > tbody > tr'));
        for ($i = 0; $i < $rows; $i++) {
            $row = $this->exts->getElements('table#paidTable > tbody > tr')[$i];
            $download_button = $this->exts->getElement('.dwnld-icon-alignment .dwnld-btn-enb', $row);
            if ($download_button != null) {
                $invoiceName =  trim($this->exts->extract('td[id^="invoice-number"]', $row));
                $invoiceFileName = $invoiceName . '.pdf';
                $invoiceDate = trim($this->exts->extract('td[id^="invoice-date"]', $row));
                $amountText = trim($this->exts->extract('td[id^="invoice-total"]', $row));
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
                $parsed_date = $this->exts->parse_date($invoiceDate, 'd-M-Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $parsed_date);

                // Download invoice if it not exisited
                if ($this->exts->invoice_exists($invoiceName) || $this->exts->document_exists($invoiceFileName)) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                } else {
                    try {
                        $this->exts->log('Click download button');
                        $download_button->click();
                    } catch (\Exception $exception) {
                        $this->exts->log('Click download button by javascript');
                        $this->exts->execute_javascript("arguments[0].click()", [$download_button]);
                    }
                    sleep(1);
                    $this->exts->moveToElementAndClick('.a-popover[aria-hidden="false"] a[id$="invoicePDF"]');
                    sleep(5);
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $downloaded_file);
                        sleep(1);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
                }
                $this->isNoInvoice = false;
            }
        }

        // Process next page
        if ($this->exts->exists('ul.a-pagination li.a-last:not(.a-disabled)')) {
            $this->exts->moveToElementAndClick('ul.a-pagination li.a-last:not(.a-disabled)');
            sleep(10);
        } else {
            break;
        }
    }
}
private function downloadOrderInvoices($pagecount = 1)
{
    $this->exts->capture('order-page');

    $rows = $this->exts->getElements('#orders-table tbody tr');
    foreach ($rows as $row) {
        $tags = $this->exts->getElements('td', $row);
        $invoice_manage_button = $this->exts->getElement('[data-test-id="manage-idu-invoice-button"] input[type="submit"]', $row);
        if ($invoice_manage_button != null) {
            try {
                $invoice_manage_button->click();
            } catch (\Exception $exception) {
                $this->exts->execute_javascript("arguments[0].click();", [$invoice_manage_button]);
            }
            sleep(3);

            $popRows = $this->exts->getElements('.a-popover-modal[aria-hidden="false"] table tbody tr');
            foreach ($popRows as $popRow) {
                $invoice_link = $this->exts->getElement('a[href*="/invoice/download"]', $popRow);
                if ($invoice_link != null) {
                    $this->isNoInvoice = false;
                    $invoiceName = trim($this->exts->extract('td:nth-child(3)', $popRow));
                    if (!$this->exts->invoice_exists($invoiceName)) {
                        $invoiceFileName = $invoiceName . '.pdf';
                        $invoiceAmount = '';
                        $invoiceDate = '';
                        $invoiceUrl = $invoice_link->getAttribute('href');

                        $this->exts->log('--------------------------');
                        $this->exts->log('invoiceName: ' . $invoiceName);
                        $this->exts->log('invoiceDate: ' . $invoiceDate);
                        $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                        $this->exts->log('invoiceUrl: ' . $invoiceUrl);
                        $this->exts->log('invoiceFileName: ' . $invoiceFileName);

                        $this->exts->open_new_window();
                        $this->exts->openUrl($invoiceUrl);
                        sleep(3);
                        if ($this->exts->exists($this->password_selector)) {
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
                            // after login, open pdf url again if needed
                            $this->exts->openUrl($invoiceUrl);
                        }
                        $this->exts->wait_and_check_download('pdf');
                        $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                        if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                            $this->exts->new_invoice($invoiceName, '', '', $downloaded_file);
                            sleep(1);
                        } else {
                            $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                        }

                        // close new tab too avoid too much tabs
                        $handles = $this->exts->get_all_tabs();
                        if (count($handles) > 1) {
                            $lastTab = end($handles);
                            $this->exts->closeTab($lastTab);

                            $handles = $this->exts->get_all_tabs();

                            if (!empty($handles)) {
                                $this->exts->switchToTab($handles[0]);
                            }
                        }
                    } else {
                        $this->exts->log('Invoice existed - ' . $invoiceName);
                    }
                }
            }
        }
    }

    $have_next_page = $this->exts->exists('.footer .pagination-controls .a-pagination .a-last a');
    if ($have_next_page && $pagecount < 10) {
        $this->exts->moveToElementAndClick('.footer .pagination-controls .a-pagination .a-last a');
        sleep(15);
        $pagecount++;
        $this->downloadOrderInvoices($pagecount);
    }
}