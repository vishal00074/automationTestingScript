<?php
// Server-Portal-ID: 86018 - Last modified: 27.01.2025 14:46:43 UTC - User: 1

/*Define constants used in script*/
public $baseUrl = 'https://mein.ionos.de/invoices';
public $username_selector = 'input[name="identifier"]';
public $password_selector = 'input[name="password"]:not(.hidden), input[type="password]:not(.hidden)';
public $check_login_success_selector = 'li#io-ox-notifications-toggle,.oao-navi-navigation .oao-navi-flyout-notification, a[data-action="sign-out"], .breadcrumb__item[href*="/account-overview"]';

public $check_login_failed_selector = 'p[class*="input-byline--error"]';
public $isNoInvoice = true;

/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(20);
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->querySelector($this->check_login_success_selector) == null) {
        $this->exts->log(str: 'NOT logged via cookie');
         $this->exts->clearCookies();
        $this->exts->openUrl($this->baseUrl);
        sleep(15);
        $this->accept_cookies();
        $this->checkFillLogin();
        sleep(10);
        $this->exts->capture('2-after-first-login');

        if ($this->exts->urlContains('passwort.ionos.de') ) {
            $this->exts->account_not_ready();
        } else if ($this->exts->exists('iframe[src*="/account-locked"]')) {
            $this->exts->waitTillPresent('iframe[src*="/account-locked"]', 20);
            $this->switchToFrame('iframe[src*="/account-locked"]');

            if ($this->exts->exists('input[name="recaptcha.enableCaptcha"] ~ * button[type="submit"]')) {
                // If this site required confirm human by reCaptcha, click it and fill login form again
                $this->exts->moveToElementAndClick('input[name="recaptcha.enableCaptcha"] ~ * button[type="submit"]');
                sleep(15);
                $this->exts->switchToDefault();
                if ($this->exts->querySelector($this->password_selector) == null) {
                    $this->exts->openUrl($this->baseUrl);
                    sleep(10);
                }
                $this->checkFillLogin();
                sleep(10);
                if (!$this->exts->exists($this->check_login_success_selector) && !$this->exts->exists($this->check_login_failed_selector)) {
                    // try again if reCaptcha failed one time
                    $this->checkFillLogin();
                    sleep(10);
                }
            } else if ($this->exts->exists('input[name="email.sendUnlockEmail"] ~ * button[type="submit"]')) {
                // If this site required confirm human by sending confirm link to email, call 2FA to solve it.
                $this->exts->moveToElementAndClick('input[name="email.sendUnlockEmail"] ~ * button[type="submit"]');
                sleep(10);
                $this->exts->log("Sending 2FA request to ask user click on confirm link");
                $this->exts->two_factor_notif_msg_en = 'Click the confirm link that has sent to your email.' . "\n>>>Enter \"OK\" after confirmation on device";
                $this->exts->two_factor_notif_msg_de = urldecode('Klicken Sie auf den Best%C3%A4tigungslink, der an Ihre E-Mail gesendet wurde.') . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
                $two_factor_code = trim($this->exts->fetchTwoFactorCode());
                if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                    $this->exts->log("User clicked on confirm link");
                    sleep(5);
                }
                // login if needed
                $this->checkFillLogin();
                sleep(10);
            } else if ($this->exts->exists('form[action*="/account-locked"] button[type="submit"]')) {
                // If this site required confirm human by unknow method, click it, wait and capture screen, then we can investigate method.
                $this->exts->moveToElementAndClick('form[action*="/account-locked"] button[type="submit"]');
                sleep(10);
                $this->exts->capture('2-unknow-confirm-method');
            }
        }
        $this->checkFillTwoFactorNew();
        $this->checkFillTwoFactor();
        //Sometime after enter 2FA, it required user to login again.
        if ($this->exts->querySelector($this->username_selector) != null) {
            $this->checkFillLogin();
            sleep(10);
            $this->checkFillTwoFactor();
        }

        sleep(35);
    }

    // then check user logged in or not
    if ($this->exts->querySelector($this->check_login_success_selector) != null) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        // Open invoices url and download invoice
        $this->exts->openUrl($this->baseUrl);
        $this->accept_cookies();
        if ($this->exts->exists($this->username_selector)) {
            $this->exts->execute_javascript('history.back();');
            sleep(15);

            $this->exts->moveToElementAndClick('li[id*="settings"] a');
            sleep(5);

            $this->exts->moveToElementAndClick('[data-id="virtual/settings/io.ox/core/sub"]');
            sleep(12);
        }

        $this->processInvoices();

        // Final, check no invoice
        if ($this->isNoInvoice) {
            $this->exts->no_invoice();
        }
        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        if (stripos($this->exts->extract('h2.headline--critical'), 'Der Login mit diesen Zugangsdaten ist leider nicht mehr ') !== false) {
            $this->exts->account_not_ready();
        }
        if (stripos($this->exts->extract('p.input-byline--error'), 'Kein IONOS Konto mit dieser E-Mail-Adresse gefunden') !== false) {
            $this->exts->loginFailure(1);
        }

        if (stripos($this->exts->extract('p.input-byline--error'), 'Le mot de passe') !== false) {
            $this->exts->loginFailure(1);
        }

        if ($this->exts->exists('.form-input-group--error input[name="identifier"], .form-input-group--error input[name="password"]')) {
            $this->exts->loginFailure(1);
        } else if (strpos(strtolower($this->exts->extract('#login-error .notification-description')), 'passwor') !== false) {
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
private function checkFillLogin()
{
    $this->accept_cookies();
    $this->exts->capture("2-login-page");
    if ($this->exts->querySelector($this->username_selector) != null) {
        sleep(3);
        $this->exts->capture("2-username");
        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(1);
        $this->exts->capture("2-username-filled");
        $this->exts->moveToElementAndClick('form button[type="submit"]');
        sleep(10);
        $this->exts->capture("2-after-submit-username");
        if ($this->exts->exists('#login-form input#login-form-additionaldata, input[name="additionaldata"]')) {
            $this->exts->capture("additionaldata-required");
            // This site require to confirm credential and also additional infor: postal code. first name...
            $this->exts->log("Confirm additional infor page found.");
            if ($this->exts->querySelector('p.stripe__element') != null) {
                $this->exts->two_factor_notif_msg_en = trim($this->exts->extract('p.stripe__element'));
                $this->exts->two_factor_notif_msg_en = trim(str_replace('Mehr erfahren', '', $this->exts->two_factor_notif_msg_en));
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            }
            if ($this->exts->two_factor_attempts == 2) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
            }
            $this->exts->log("Message for additional:\n" . $this->exts->two_factor_notif_msg_en);
            $this->exts->notification_uid = '';
            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                $this->exts->log("checkFillTwoFactor: Entering additional data." . $two_factor_code);
                $this->exts->moveToElementAndType('#login-form input#login-form-additionaldata, input[name="additionaldata"]', $two_factor_code);
                sleep(3);
                $this->exts->capture("additionaldata-filled");
                $this->exts->moveToElementAndClick('form button[type="submit"]');
                sleep(7);

                $this->exts->capture("additionaldata-submitted");
            } else {
                $this->exts->log("Not received additionaldata");
            }
        } else if ($this->exts->exists('#button_submit_emailconfirmation')) {
            $this->exts->capture("additionaldata-required");
            $this->exts->log("Confirm additional infor page found.");
            $this->exts->two_factor_notif_msg_en = 'Click the confirm link that has sent to your email.' . "\n>>>Enter \"OK\" after confirmation on device";
            $this->exts->two_factor_notif_msg_de = urldecode('Klicken Sie auf den Best%C3%A4tigungslink, der an Ihre E-Mail gesendet wurde.') . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
            $this->exts->log("Message for additional:\n" . $this->exts->two_factor_notif_msg_en);

            $this->exts->moveToElementAndClick('#button_submit_emailconfirmation');
            $this->exts->notification_uid = '';
            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                $this->exts->log("User clicked on confirm link");
                sleep(5);
                $this->exts->capture("additionaldata-confirmed");
            } else {
                $this->exts->log("Not received additionaldata");
            }

            if ($this->exts->querySelector($this->username_selector) != null) {
                $this->exts->log("Re Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(1);
                $this->exts->capture("2-username-filled-2");
                $this->exts->moveToElementAndClick('form button[type="submit"]');
                sleep(10);
            }
        } else if ($this->exts->urlContains('wrong-mandant-redirect') && $this->exts->exists('input[name="identifier"]')) {
            $this->exts->moveToElementAndClick('form button[type="submit"]');
            sleep(5);
        }
    }

    $this->checkFillTwoFactor();
    $this->accept_cookies();
    // Maybe two type of users, difference password form
    if ($this->exts->querySelector($this->password_selector) != null) {
        $this->exts->capture("2-password");
        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(1);
        $this->checkFillRecaptcha();

        $this->exts->capture("2-password-filled");
        $this->exts->moveToElementAndClick('form button[type="submit"]');
    } else if ($this->exts->exists('input[name="oaologin.password"]')) {
        $this->exts->capture("2-password");
        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType('input[name="oaologin.password"]', $this->password);
        sleep(1);
        $this->exts->moveToElementAndClick('input#staysignedin-box:not(:checked)');
        $this->checkFillRecaptcha();

        $this->exts->capture("2-password-filled");
        $this->exts->moveToElementAndClick('form button[type="submit"]');
    } else {
        $this->exts->capture("2-password-field-not-found");
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
					return document.querySelector("[data-callback]").getAttribute("data-callback");
				}

				var result = ""; var found = false;
				function recurse (cur, prop, deep) {
				    if(deep > 5 || found){ return;}console.log(prop);
				    try {
				        if(cur == undefined || cur == null || cur instanceof Element || Object(cur) !== cur || Array.isArray(cur)){ return;}
				        if(prop.indexOf(".callback") > -1){result = prop; found = true; return;
				        } else { deep++;
				            for (var p in cur) { recurse(cur[p], prop ? prop + "." + p : p, deep);}
				        }
				    } catch(ex) { console.log("ERROR in function: " + ex); return; }
				}

				recurse(___grecaptcha_cfg.clients[0], "", 0);
				return found ? "___grecaptcha_cfg.clients[0]." + result : null;
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
    $this->exts->capture("2.1-two-factor-checking");
    $two_factor_message_selector = '//input[@id="passcode"]/../../../preceding-sibling::section//p[@class="stripe__element"]';
    if ($this->exts->exists('input#passcode')) {
        // Require enter 2 factor code
        $this->exts->log("Two factor page found.");
        if ($this->exts->getElement($two_factor_message_selector, null, 'xpath') != null) {
            $this->exts->two_factor_notif_msg_en = "";
            for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector, null, 'xpath')); $i++) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector, null, 'xpath')[$i]->getAttribute('innerText') . "\n";
            }
            $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
        }

        if ($this->exts->two_factor_attempts == 2) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
        }
        $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
        $this->exts->notification_uid = '';
        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if (!empty($two_factor_code) && trim($two_factor_code) != '') {
            $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
            $this->exts->moveToElementAndType('input#passcode', $two_factor_code);
            sleep(3);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);
            $this->exts->moveToElementAndClick('form button[type="submit"]');
            sleep(15);

            $this->exts->capture('after-submit-2fa');
            sleep(5);

            if ($this->exts->querySelector('input#passcode:not(.input-text--error)') == null && !$this->exts->exists('input#passcode') && strpos($this->exts->getUrl(), '/totp') === false) {
                $this->exts->log("Two factor solved");
            } else if ($this->exts->two_factor_attempts < 3) {
                $this->exts->two_factor_attempts++;
                $this->exts->notification_uid = '';
                $this->checkFillTwoFactor();
            } else {
                $this->exts->log("Two factor can not solved");
            }
        } else {
            $this->exts->log("Not received two factor code");
        }
    } else if ($this->exts->exists('img.mobilepush-animation')) {
        $two_factor_selector = 'img.mobilepush-animation';
        $two_factor_message_selector = '//img[@class="mobilepush-animation"]/../preceding-sibling::section//p[@class="stripe__element"]';
        $two_factor_submit_selector = 'form button.btn-primary';

        if ($this->exts->querySelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if ($this->exts->getElement($two_factor_message_selector, null, 'xpath') != null) {
                $this->exts->two_factor_notif_msg_en = "";
                for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector, null, 'xpath')); $i++) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector, null, 'xpath')[$i]->getAttribute('innerText') . "\n";
                }
                $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en) . ' Please type "OK" after confirmed mobile app';
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
                $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
            }
            if ($this->exts->two_factor_attempts == 2) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
            }

            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
                sleep(25);

                $this->exts->capture('after-submit-2fa');

                if ($this->exts->querySelector($two_factor_selector) == null) {
                    $this->exts->log("Two factor solved");
                } else if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->two_factor_attempts++;
                    $this->exts->notification_uid = '';
                    $this->checkFillTwoFactor();
                } else {
                    $this->exts->log("Two factor can not solved");
                }
            } else {
                $this->exts->log("Not received two factor code");
            }
        }
    }
}
private function checkFillTwoFactorNew()
{
    $two_factor_selector = 'input#additionaldata';
    $two_factor_message_selector = 'p.stripe__element';
    $two_factor_submit_selector = 'button#button--with-loader';

    if ($this->exts->querySelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        if ($this->exts->querySelector($two_factor_message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = "";
            for ($i = 0; $i < count($this->exts->querySelectorAll($two_factor_message_selector)); $i++) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->querySelectorAll($two_factor_message_selector)[$i]->getText() . "\n";
            }
            $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
        }
        if ($this->exts->two_factor_attempts == 2) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
        }

        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if (!empty($two_factor_code) && trim($two_factor_code) != '') {
            $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
            $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);

            $this->exts->log("checkFillTwoFactor: Clicking submit button.");
            sleep(3);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

            $this->exts->moveToElementAndClick($two_factor_submit_selector);
            sleep(15);

            if ($this->exts->querySelector($two_factor_selector) == null) {
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
private function accept_cookies()
{
    if ($this->exts->exists('div.consent-manager button#preferences_prompt_submit_all, .privacy-consent--modal-dialog  #selectAll')) {
        $this->exts->moveToElementAndClick('div.consent-manager button#preferences_prompt_submit_all, .privacy-consent--modal-dialog  #selectAll');
        sleep(2);
    }
}

private function processInvoices($paging_count = 1)
{
    sleep(25);
    $this->exts->capture("4-invoices-page");
    try {
        if ($this->exts->querySelectorAll('table > tbody > tr') != null) {
            $rows = count($this->exts->querySelectorAll('table > tbody > tr'));
        } else {
            $rows = 0;
        }

        if ($rows > 0) {
            for ($i = 0; $i < $rows; $i++) {
                $row = $this->exts->querySelectorAll('table > tbody > tr')[$i];
                $tags = $this->exts->querySelectorAll('td', $row);
                if (count($tags) >= 4 && $this->exts->querySelector('a[href*="download"]', $row) != null) {
                    $download_button = $this->exts->querySelector('a[href*="download"]', $row);
                    $invoiceName = trim($tags[3]->getAttribute('innerText'));
                    $invoiceFileName = $invoiceName . '.pdf';
                    $invoiceDate = trim($tags[0]->getAttribute('innerText'));
                    $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[1]->getAttribute('innerText'))) . ' EUR';
                    $parsed_date = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');

                    $this->exts->log('--------------------------');
                    $this->exts->log('invoiceName: ' . $invoiceName);
                    $this->exts->log('invoiceDate: ' . $invoiceDate);
                    $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                    $this->exts->log('Date parsed: ' . $parsed_date);
                    $this->isNoInvoice = false;
                    // Download invoice if it not exisited
                    if ($this->exts->invoice_exists($invoiceName)) {
                        $this->exts->log('Invoice existed ' . $invoiceFileName);
                    } else {
                        try {
                            $this->exts->log('Click download button');
                            $download_button->click();
                        } catch (\Exception $exception) {
                            $this->exts->log('Click download button by javascript');
                            $this->exts->execute_javascript("arguments[0].click()", [$download_button]);
                        }
                        sleep(5);
                        $this->exts->wait_and_check_download('pdf');
                        $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                        if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                            $pdf_content = file_get_contents($downloaded_file);
                            if (stripos($pdf_content, "%PDF") !== false) {
                                $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
                                sleep(1);
                            } else {
                                $this->exts->log(__FUNCTION__ . ":: Not Valid PDF - " . $downloaded_file);
                            }
                        } else {
                            $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                        }
                    }
                    sleep(3);
                }
            }

            $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
            if (
                $restrictPages == 0 &&
                $paging_count < 50 &&
                $this->exts->querySelector('#invoices-pagination a.next-item') != null
            ) {
                $paging_count++;
                $this->exts->moveToElementAndClick('#invoices-pagination a.next-item');
                sleep(5);
                $this->processInvoices($paging_count);
            }
        } else {
            $this->exts->no_invoice();
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception process processInvoices " . $exception->getMessage());
    }
}