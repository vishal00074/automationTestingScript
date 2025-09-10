<?php // migrated 2nd
// Server-Portal-ID: 88448 - Last modified: 05.06.2024 14:12:05 UTC - User: 1

/*Define constants used in script*/
public $baseUrl = 'https://my.stripo.email/cabinet/';
public $loginUrl = 'https://my.stripo.email/login';
public $invoicePageUrl = '';

public $username_selector = 'input#emailInput, input#login_field';
public $password_selector = 'input#passwordInput, input#password';
public $remember_me_selector = '';
public $submit_login_selector = 'button.loginButton, button[data-testid="sign-in-button"]';

public $check_login_failed_selector = '.notifications .alert.alert-danger';
public $check_login_success_selector = 'div.company-avatar, acc-user-menu acc-user-avatar';

public $isNoInvoice = true;
/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->openUrl($this->baseUrl);
    sleep(1);

    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(10);
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->getElement($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        // Sometimes user does not gets logged in, so try aupto 5 times before trigerring login failure
        for ($i = 0; $i < 5; $i++) {
            $this->exts->openUrl($this->loginUrl);
            sleep(15);
            $this->checkFillLogin();
            sleep(20);
            if ($this->exts->getElement($this->check_login_success_selector) != null) {
                break;
            }
        }
    }

    if ($this->exts->exists('div.modal-dialog')) {
        $this->exts->moveToElementAndClick('label > input[type=checkbox]');
        sleep(3);
        $this->exts->moveToElementAndClick('div.modal-footer-privacy-policy a.privacy-policy-accept');
        sleep(5);
    }
    // then check user logged in or not
    if ($this->exts->getElement($this->check_login_success_selector) != null) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        // Open invoices url and download invoice
        // if($this->exts->getElement('div.user-avatar') != null) {
        // 	$this->exts->moveToElementAndClick('div.user-avatar');
        // 	sleep(5);
        // 	$this->exts->moveToElementAndClick('a[href*="/billing/"]');
        // }
        $this->exts->openUrl('https://my.stripo.email/account/settings/billing/history/company');
        sleep(5);
        $this->processInvoices();

        // Final, check no invoice
        if ($this->isNoInvoice) {
            $this->exts->no_invoice();
        }
        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        $this->exts->loginFailure();
    }
}

private function checkFillLogin()
{
    if ($this->exts->getElement($this->password_selector) != null) {
        sleep(3);
        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(1);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(1);

        if ($this->remember_me_selector != '')
            $this->exts->moveToElementAndClick($this->remember_me_selector);
        sleep(2);
        $this->exts->capture("2-login-page-filled");
        $this->checkFillRecaptcha();

        if ($this->exts->waitTillPresent(".notification-content .spans-text, .ca-notification--item.error", 10)) {
            $msg_error = $this->exts->extract('.notification-content .spans-text, .ca-notification--item.error', null, 'innerText');
            $this->exts->log('toast error: ' . $msg_error);
            if (strpos(strtolower($msg_error), 'passwor') !== false) {
                $this->exts->loginFailure(1);
            }
        }

        $this->exts->moveToElementAndClick($this->submit_login_selector);
        if ($this->exts->waitTillPresent(".notification-content .spans-text, .ca-notification--item.error", 10)) {
            $msg_error = $this->exts->extract('.notification-content .spans-text, .ca-notification--item.error', null, 'innerText');
            $this->exts->log('toast error: ' . $msg_error);
            if (strpos(strtolower($msg_error), 'passwor') !== false) {
                $this->exts->loginFailure(1);
            }
        }
        $this->checkFillRecaptcha();
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}

private function checkFillRecaptcha($count = 1)
{
    $this->exts->log(__FUNCTION__);
    $recaptcha_iframe_selector = 'iframe[src*="/recaptcha/api2/anchor?"]';
    $recaptcha_textarea_selector = 'textarea[name="g-recaptcha-response"]';
    if ($this->exts->exists($recaptcha_iframe_selector)) {
        $iframeUrl = $this->exts->extract($recaptcha_iframe_selector, null, 'src');
        $data_siteKey = explode('&', end(explode("&k=", $iframeUrl)))[0];
        $this->exts->log("iframe url  - " . $iframeUrl);
        $this->exts->log("SiteKey - " . $data_siteKey);

        $isCaptchaSolved = $this->exts->processRecaptcha(urlencode($this->exts->getUrl()), $data_siteKey, false);
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
                if ($this->exts->waitTillPresent(".notification-content .spans-text", 10)) {
                    $msg_error = $this->exts->extract('.notification-content .spans-text', null, 'innerText');
                    $this->exts->log('toast error: ' . $msg_error);
                    if (strpos(strtolower($msg_error), 'passwor') !== false) {
                        $this->exts->loginFailure(1);
                    }
                }
                sleep(10);
            }
        } else {
            if ($count < 4) {
                $count++;
                $this->checkFillRecaptcha($count);
            }
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Not found reCaptcha');
    }
}

private function processInvoices($paging_count = 1)
{
    sleep(25);
    $this->exts->capture("4-invoices-page");
    $invoices = [];
    $this->exts->moveToElementAndClick('ca-page-content div.ca-lazy-load--wrapper button[ca-button]');
    sleep(1);
    $rows = count($this->exts->getElements('table > tbody > tr'));
    for ($i = 0; $i < $rows; $i++) {
        $row = $this->exts->getElements('table > tbody > tr')[$i];
        $tags = $this->exts->getElements('td', $row);
        if (count($tags) >= 7 && $this->exts->getElement('.btn span[class*="icon-download"], button ca-icon[icon=download]', $tags[6]) != null) {
            $this->isNoInvoice = false;
            $download_button = $this->exts->getElement('.btn span[class*="icon-download"], button ca-icon[icon=download]', $tags[6]);
            $invoiceName = trim($tags[3]->getAttribute('innerText'));
            $invoiceFileName = $invoiceName . '.pdf';
            $invoiceDate = trim($tags[1]->getAttribute('innerText'));
            $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[5]->getAttribute('innerText'))) . ' USD';

            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoiceName);
            $this->exts->log('invoiceDate: ' . $invoiceDate);
            $this->exts->log('invoiceAmount: ' . $invoiceAmount);
            $parsed_date = $this->exts->parse_date($invoiceDate, 'd/m/Y H:i:s', 'Y-m-d');
            if ($parsed_date == '') {
                $parsed_date = $this->exts->parse_date($invoiceDate, 'd F Y, H:i:s', 'Y-m-d');
            }
            $this->exts->log('Date parsed: ' . $parsed_date);

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
                    $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            }
        }
    }

    $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
    if (
        $restrictPages == 0 &&
        $paging_count < 50 &&
        $this->exts->getElement('a.cursor-pointer span.es-icon-chevron-left') != null
    ) {
        $paging_count++;
        $this->exts->moveToElementAndClick('a.cursor-pointer span.es-icon-chevron-left');
        sleep(5);
        $this->processInvoices($paging_count);
    }
}