<?php
// Server-Portal-ID: 228714 - Last modified: 05.11.2024 13:22:46 UTC - User: 1

/*Define constants used in script*/
public $baseUrl = 'https://directory.swile.co/signin';
public $loginUrl = 'https://directory.swile.co/signin';
public $username_selector = 'input#email, input#username';
public $password_selector = 'input#password';
public $remember_me_selector = '';
public $submit_login_selector = 'button[type="submit"]';
public $check_login_success_selector = 'ul.UserMenu__Menu, ul.MuiList-root, a[href="/profile"], a[href="/maps"], a[href*="/invoices"],div[data-intercom-target="TeamMySwileLayoutUserMenu"]';

public $isNoInvoice = true;
/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(5);

    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(5);
    //$this->exts->webdriver->get($this->baseUrl);

    $this->checkFillLogin();
    sleep(5);
    $this->exts->capture_by_chromedevtool('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->exists($this->check_login_success_selector)) {
        $this->exts->log('NOT logged via cookie');
        //$this->exts->webdriver->get($this->loginUrl);
        $this->exts->openUrl($this->loginUrl);
        sleep(15);
        if (strpos(strtolower($this->exts->extract("h1")), '504 error') !== false) {
            $this->exts->refresh();
            sleep(15);
        }
        if (strpos(strtolower($this->exts->extract("h1")), '504 error') !== false) {
            $this->exts->loginFailure(1);
        }
        $this->checkFillLogin();
        sleep(20);
        for ($i = 0; $i < 5 && $this->exts->getElement('//span[contains(text(), "Everything did not go as planned")]', null, 'xpath') != null; $i++) {
            $this->checkFillLogin();
            sleep(20);
        }
        $this->checkFillTwoFactor();
        //agree cook
        if ($this->exts->exists('div#axeptio_overlay button.ButtonGroup__BtnStyle-sc-1usw1pe-0.iVYBhe')) {
            $this->exts->moveToElementAndClick('div.MuiGrid-justify-xs-flex-end .MuiBadge-root');
            sleep(3);
        }
    }

    //Remove block start
    if ($this->exts->exists('div[data-overlay-container] button:not([color="#ffffff"])')) {
        $this->exts->moveToElementAndClick('div[data-overlay-container] button:not([color="#ffffff"])');
        sleep(5);
    }

    if ($this->exts->exists('div[id="root"]>div:first-child>button')) {
        $this->exts->moveToElementAndClick('div[id="root"]>div:first-child>button');
        sleep(5);
    }
    //Remove block close


    if (!$this->exts->exists($this->check_login_success_selector)) {
        $this->exts->moveToElementAndClick('div[data-intercom-target="TeamMySwileLayoutUserMenu"]');
        sleep(5);
    }




    if ($this->exts->getElement($this->check_login_success_selector) != null) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        if ($this->exts->exists('button#onetrust-accept-btn-handler')) {
            $this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler');
            sleep(5);
        }

        // Open invoices url and download invoice


        if ($this->exts->exists('a[href*="/invoices"]')) {
            // // sleep(10);
            // $this->exts->moveToElementAndClick('a[href*="/invoices"]');
            $this->exts->openUrl('https://affiliates.swile.co/invoices');
            sleep(15);
            $this->processAffiliatesInvoice();
        }


        if ($this->exts->exists('div[data-intercom-target="TeamMySwileLayoutUserMenu"]')) {
            $this->exts->moveToElementAndClick('div[data-intercom-target="TeamMySwileLayoutUserMenu"]');
            sleep(2);
            $this->exts->moveToElementAndClick('div.MuiPaper-root a:nth-child(2) h3');
            sleep(10);
            $this->exts->moveToElementAndClick('li[data-intercom-target="MvoNavHistory"]');
            sleep(2);
            $this->processInvoices();
        }

        $this->exts->openUrl('https://corpo.swile.co');
        sleep(15);

        if ($this->exts->exists('a[href*="/history"]')) {
            $this->exts->moveToElementAndClick('a[href*="/history"]');
            sleep(2);

            $this->processInvoices();
        }
        $this->exts->log("==================== Download completed: " . $this->totalInvoices . " invoices");
        // Final, check no invoice
        if ($this->isNoInvoice) {
            $this->exts->no_invoice();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        if ($this->exts->exists('form[name="sign-in-form"]')) {
            $form = $this->exts->getElement('form[name="sign-in-form"]');
            if ($this->exts->getElementByText('span', 'password is incorrect', $form, false) != null) {
                $this->exts->loginFailure(1);
            }
        }

        $err_msg1 = $this->exts->extract('div[class="sc-gsTDqH gStKln"]');
        $lowercase_err_msg = strtolower($err_msg1);
        $substrings = array('password is incorrect', 'try again', 'incorrect');
        foreach ($substrings as $substring) {
            if (strpos($lowercase_err_msg, strtolower($substring)) !== false) {
                $this->exts->log($err_msg1);
                $this->exts->loginFailure(1);
                break;
            }
        }

        if ($this->exts->getElementByText('span', 'password is incorrect', null, false) != null) {
            $this->exts->loginFailure(1);
        }
        if (strpos(strtolower($this->exts->extract('form[method="POST"]')), 'de passe est incorrect') !== false || strpos(strtolower($this->exts->extract('form[method="POST"]')), 'your password is incorrect') !== false) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLogin()
{
    if($this->exts->exists($this->username_selector)){
        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(2);
        
        $this->exts->capture("1-username-filled");
        $this->exts->moveToElementAndClick($this->submit_login_selector);
        sleep(6);
        $this->exts->capture("1-username-submitted");
    } else {
        $this->exts->log(__FUNCTION__.'::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }

    if($this->exts->exists($this->password_selector)){
        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(2);
        $this->exts->capture("1-password-filled");
        $this->exts->moveToElementAndClick($this->submit_login_selector);
        sleep(6);
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

private function checkFillTwoFactor()
{
    $two_factor_selector = 'form[name="sign-in-mfa-form"] input#authenticationCode';
    $two_factor_message_selector = 'form[name="sign-in-mfa-form"] span';
    $two_factor_submit_selector = 'form[name="sign-in-mfa-form"] button[type=submit]';

    if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
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

        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if (!empty($two_factor_code) && trim($two_factor_code) != '') {
            $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
            $this->exts->getElement($two_factor_selector)->sendKeys($two_factor_code);

            $this->exts->log("checkFillTwoFactor: Clicking submit button.");
            sleep(3);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

            $this->exts->moveToElementAndClick($two_factor_submit_selector);
            sleep(15);

            if ($this->exts->getElement($two_factor_selector) == null) {
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
public $totalFiles = 0;
private function processInvoices()
{
    sleep(25);
    $this->exts->capture("4-invoices-page");
    $invoices = [];

    $rows = $this->exts->getElements('table > tbody > tr');
    for ($i = 0; $i < count($rows); $i++) {
        $tags = $this->exts->getElements('td', $rows[$i]);
        if (count($tags) >= 7) {
            $invoiceName = $this->exts->extract('span', $tags[0], 'innerText');
            $invoiceDate = '';
            $invoiceAmount = '' . ' EUR';
            $invoiceFileName = $invoiceName . '.pdf';
            if ($this->exts->exists('button#onetrust-accept-btn-handler')) {
                $this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler');
                sleep(3);
            }
            // Click row to detail page
            try {
                $this->exts->log("Click row ");
                $rows[$i]->click();
            } catch (Exception $e) {
                $this->exts->log("Click row by javascript ");
                $this->exts->execute_javascript('arguments[0].click()', [$rows[$i]]);
            }
            sleep(5);

            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoiceName);
            if ($this->exts->invoice_exists($invoiceName)) {
                $this->exts->log('Invoice existed ' . $invoiceFileName);
            } else {
                $downloaded_file = $this->exts->click_and_download('a[href*="order-invoices"]', 'pdf', $invoiceFileName, 'CSS', 5);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                    sleep(1);
                    $this->totalInvoices++;
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            }
            if ($this->exts->exists('a[href*="/history"]')) {
                $this->exts->moveToElementAndClick('a[href*="/history"]');
                sleep(2);
            }
            $rows = $this->exts->getElements('table > tbody > tr');
            $this->isNoInvoice = false;
        }
    }
}

private function processAffiliatesInvoice()
{
    sleep(25);
    $this->exts->capture("4-invoices-page");
    $invoices = [];

    $rows = $this->exts->getElements('table > tbody > tr');
    foreach ($rows as $row) {
        $tags = $this->exts->getElements('td', $row);
        if (count($tags) >= 7 && $this->exts->getElement('a[href*="invoice"]', $tags[7]) != null) {
            $invoiceUrl = $this->exts->getElement('a[href*="invoice"]', $tags[7])->getAttribute("href");
            $invoiceName = $this->exts->getElement('a[href*="invoice"]', $tags[7])->getAttribute("download");
            $invoiceName = str_replace(".pdf", "", $invoiceName);
            $invoiceDate = trim($tags[2]->getAttribute('innerText'));
            $invoiceDate = trim(array_pop(explode("to ", $invoiceDate)));
            $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[6]->getAttribute('innerText'))) . ' EUR';

            array_push($invoices, array(
                'invoiceName' => $invoiceName,
                'invoiceDate' => $invoiceDate,
                'invoiceAmount' => $invoiceAmount,
                'invoiceUrl' => $invoiceUrl
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
        $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'M d, Y', 'Y-m-d');
        $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

        $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
        if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
            $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $downloaded_file);
            sleep(1);
        } else {
            $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
        }
    }
}