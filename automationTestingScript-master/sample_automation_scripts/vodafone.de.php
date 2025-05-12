<?php // migrated
// Server-Portal-ID: 329 - Last modified: 24.01.2025 13:55:13 UTC - User: 1

// Script here
public $baseUrl = "https://www.vodafone.de/meinvodafone/account/login";
public $username_selector = 'input#txtUsername';
public $password_selector = 'input#txtPassword';
public $submit_btn = '.login-onelogin [type=submit]';
public $logout_btn = 'div.dashboard-module';
public $totalInvoices = 0;
public $itemized_bill = 0;


/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);

    $isCookieLoaded = false;
    if ($this->exts->loadCookiesFromFile()) {
        sleep(1);
        $isCookieLoaded = true;
    }

    $this->exts->openUrl($this->baseUrl);
    sleep(3);
    $this->exts->waitTillAnyPresent([$this->logout_btn, $this->username_selector, 'form[action*="/captcha"] img.captcha'], 30);

    if ($isCookieLoaded) {
        $this->exts->capture("Home-page-with-cookie");
    } else {
        $this->exts->capture("Home-page-without-cookie");
    }

    $this->cookieConsent();
    $this->checkSolveCaptcha();

    if (!$this->checkLogin()) {
        $this->cookieConsent();
        $this->exts->capture("after-login-clicked");
        $this->checkSolveCaptcha();
        $this->fillForm(0);
        sleep(3);
        $this->exts->waitTillAnyPresent([$this->logout_btn, 'form#totpWrapper input#totpcontrol', 'div.login-onelogin div.error div.alert-content']);
        $this->checkFillTwoFactor();
        if (!$this->checkLogin() && !$this->exts->exists('div.login-onelogin div.error div.alert-content, .alert-old div.alert.error')) {
            $this->exts->refresh();
            sleep(3);
            $this->exts->waitTillAnyPresent([$this->logout_btn, 'form#totpWrapper input#totpcontrol', $this->password_selector, 'form[action*="/captcha"] img.captcha']);
            $this->checkSolveCaptcha();
            if ($this->exts->exists($this->password_selector)) {
                $this->fillForm(1);
                $this->exts->waitTillAnyPresent([$this->logout_btn, 'form#totpWrapper input#totpcontrol', 'div.login-onelogin div.error div.alert-content']);
            }
            $this->checkFillTwoFactor();
        }

        $this->cookieConsent();

        $err_msg = $this->exts->extract('div.login-onelogin div.error div.alert-content');
        // if ($this->exts->getElement("div.login-onelogin div.error div.alert-content") != null) {
        //  $err_msg = trim($this->exts->getElements("div.login-onelogin div.error div.alert-content")[0]->getAttribute('innerText'));
        // }

        if ($err_msg != "" && $err_msg != null && $this->exts->exists('div.login-onelogin div.error div.alert-content')) {
            $this->exts->log($err_msg);
            $this->exts->loginFailure(1);
        }
    }


    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        if ($this->exts->urlContains('/captcha')) {
            $this->exts->processCaptcha('form[action*="/captcha"] img.captcha', 'form[action*="/captcha"] input#captchaField');
            $this->exts->capture('captcha-filled');
            $this->exts->moveToElementAndClick('form[action*="/captcha"] [type="submit"]');
            sleep(10);
        }
        $this->exts->moveToElementAndClick('#ds-consent-modal button');
        sleep(3);
        $this->exts->moveToElementAndClick('#personalOfferModal button.btn--submit');
        sleep(3);


        $this->cookieConsent();

        for ($i = 0; $i < 3; $i++) {
            if ($this->exts->exists('div[ng-if*="overlayPromotions"] #ejmOverlay [ng-if="canClose"]')) {
                $this->exts->moveToElementAndClick('div[ng-if*="overlayPromotions"] #ejmOverlay [ng-if="canClose"]');
                sleep(3);
            }
        }

        if ($this->exts->exists('div#overlayId a.btn-alt')) {
            $this->exts->moveToElementAndClick('div#overlayId a.btn-alt');
            sleep(5);
        }

        if ($this->exts->exists('.notification-message-container [class*="icon-close"]')) {
            $this->exts->moveToElementAndClick('.notification-message-container [class*="icon-close"]');
            sleep(2);
        }
        $this->exts->capture("LoginSuccess");

        if ($this->exts->exists('div.simple-accord form.standard-form button[type="submit"]')) {
            if ($this->exts->exists('[formcontrolname="privacyPermissionFlagField"]')) {
                $this->exts->account_not_ready();
            } else {
                $this->exts->moveToElementAndClick('div.simple-accord form.standard-form button[type="submit"]');
                sleep(15);

                if ($this->exts->exists('app-submit-security-questions input[id*="answer"]')) {
                    $this->exts->account_not_ready();
                }
            }
        }
        $this->exts->openUrl('https://www.vodafone.de/meinvodafone/services/notifizierung/dokumente');
        sleep(20);
        if ($this->exts->exists('button#dip-consent-summary-accept-all')) {
            $this->exts->moveToElementAndClick('button#dip-consent-summary-accept-all');
            sleep(10);
        }
        $this->exts->capture('3.1-open-documents');
        if (!$this->exts->exists('div:not(.ng-hide) > .alert-old .error h4, div.doc-inbox-container .error h4')) {
            $this->processMultiContractsForDocument();
        }
        //If can't get invoices from document page, try get it from service page.
        $this->exts->openUrl('https://www.vodafone.de/meinvodafone/services/');
        sleep(20);
        $this->exts->capture('3.2-open-services');
        if ($this->exts->exists('div:not(.ng-hide) > .alert-old .error h4, div.doc-inbox-container .error h4')) {
            $this->exts->refresh();
            sleep(20);
            $this->exts->capture('3.2-re-open-services');
        }
        $this->processMultiContractsInServicePage();
        // finally check total invoices
        if ($this->totalInvoices == 0) {
            $this->exts->no_invoice();
        }
        $this->exts->success();
    } else {
        $this->exts->log("LoginFailed " . $this->exts->getUrl());
        if ($this->exts->exists('.alert.error') && $this->exts->exists('.alert.error') && $this->exts->exists($this->username_selector)) {
            $this->exts->loginFailure(1);
        } else if (strpos(strtolower($this->exts->extract('form p')), 'bestätige bitte deine e-mail-adresse oder nenn uns eine andere. nur so können wir dir helfen, wenn du deine zugangsdaten vergessen hast') !== false) {
            $this->exts->account_not_ready();
        } else {
            $this->exts->loginFailure();
        }
    }
}
private function clearChrome(){
    $this->exts->log("Clearing browser history, cookie, cache");
    $this->exts->openUrl('chrome://settings/clearBrowserData');
    sleep(10);
    $this->exts->capture("clear-page");
    for ($i=0; $i < 2; $i++) { 
        $this->exts->type_key_by_xdotool('Tab');
    }
    $this->exts->type_key_by_xdotool('Tab');
    $this->exts->type_key_by_xdotool('Return');
    $this->exts->type_key_by_xdotool('a');
    sleep(1);
    $this->exts->type_key_by_xdotool('Return');
    sleep(3);
    $this->exts->capture("clear-page");
    for ($i=0; $i < 5; $i++) { 
        $this->exts->type_key_by_xdotool('Tab');
    }
    $this->exts->type_key_by_xdotool('Return');
    sleep(15);
    $this->exts->capture("after-clear");
}
function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
    try {

        if ($this->exts->exists($this->username_selector)) {
            sleep(2);
            $this->exts->capture("1-pre-login");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(3);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);

            $this->exts->capture("1-pre-login-1");
            $this->checkFillRecaptcha();
            $this->checkFillRecaptcha();

            $this->exts->moveToElementAndClick($this->submit_btn);
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}
private function checkFillTwoFactor()
{
    $two_factor_selector = 'form#totpWrapper input#totpcontrol';
    $two_factor_message_selector = 'p[automation-id="totpcodeTxt_tv"]';
    $two_factor_submit_selector = 'div[automation-id="SUBMITCODEBTN_btn"] button[type="submit"]';

    if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
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

        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if (!empty($two_factor_code) && trim($two_factor_code) != '') {
            $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
            $this->exts->click_by_xdotool($two_factor_selector);
            $this->exts->type_text_by_xdotool($two_factor_code);

            $this->exts->log("checkFillTwoFactor: Clicking submit button.");
            sleep(3);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

            $this->exts->moveToElementAndClick($two_factor_submit_selector);
            $this->exts->waitTillPresent($this->logout_btn);

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
function checkFillRecaptcha()
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
function checkSolveCaptcha()
{
    if ($this->exts->urlContains('/captcha')) {
        $this->cookieConsent();
        $this->exts->processCaptcha('form[action*="/captcha"] img.captcha', 'form[action*="/captcha"] input#captchaField');
        $this->exts->capture('captcha-filled');
        $this->exts->moveToElementAndClick('form[action*="/captcha"] [type="submit"]');
        sleep(15);
    }

    for ($i = 0; $i < 10 && $this->exts->urlContains('/captcha') && $this->exts->exists('form[action*="/captcha"] input#captchaField'); $i++) {
        $this->cookieConsent();
        $this->exts->getElement('form[action*="/captcha"] input#captchaField')->clear();
        $this->exts->processCaptcha('form[action*="/captcha"] img.captcha', 'form[action*="/captcha"] input#captchaField');
        $this->exts->capture('captcha-filled');
        $this->exts->moveToElementAndClick('form[action*="/captcha"] [type="submit"]');
        sleep(5);
    }
    sleep(10);
}
function cookieConsent()
{
    if ($this->exts->exists('#dip-consent .dip-consent-btn.red-btn, [show-overlay="true"] a[class="btn"], button[id="dip-consent-summary-accept-all"]')) {
        $this->exts->moveToElementAndClick('#dip-consent .dip-consent-btn.red-btn, [show-overlay="true"] a[class="btn"], button[id="dip-consent-summary-accept-all"]');
        sleep(3);
    }
}
function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        if ($this->exts->exists($this->logout_btn) && !$this->exts->exists($this->password_selector)) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $isLoggedIn = true;
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception checking loggedin " . $exception);
    }

    return $isLoggedIn;
}
function processMultiContractsForDocument()
{
    $numberOfContract = count($this->exts->getElements('div.mod-multi-select-tags li#option_child'));
    $this->exts->log('Number of contracts: ' . $numberOfContract);
    if ($numberOfContract > 1) {
        for ($i = 1; $i < $numberOfContract + 1; $i++) {
            $this->exts->update_process_lock();
            $this->exts->moveToElementAndClick('div.mod-multi-select-tags button.select-toggle');
            sleep(2);
            $contractNumber = trim(preg_replace('/[^\d\.\,]/', '', $this->exts->extract('div.mod-multi-select-tags li#option_child:nth-child(' . $i . ')', null, 'innerText')));
            $this->exts->log('Process contract number: ' . $contractNumber);
            $this->exts->moveToElementAndClick('div.mod-multi-select-tags li#option_child:nth-child(' . $i . ')');
            $this->exts->waitTillPresent('div.filter_doc ul', 25);
            $this->exts->moveToElementAndClick('div.filter_doc ul');
            sleep(1);
            if ($this->exts->exists('select#category option[value="Rechnung"]')) {
                $this->exts->execute_javascript("{let selectElement = document.querySelector('#category');
                    selectElement.value = 'Rechnung';
                    selectElement.dispatchEvent(new Event('input', { bubbles: true }));
                    selectElement.dispatchEvent(new Event('change', { bubbles: true }));
                    selectElement.dispatchEvent(new MouseEvent('click', { bubbles: true }));}");
                sleep(2);
                $this->exts->execute_javascript("{let selectElement = document.querySelector('#subCategory');
                    selectElement.value = 'Rechnung';
                    selectElement.dispatchEvent(new Event('input', { bubbles: true }));
                    selectElement.dispatchEvent(new Event('change', { bubbles: true }));
                    selectElement.dispatchEvent(new MouseEvent('click', { bubbles: true }));}");
                sleep(2);
                $this->exts->moveToElementAndClick('a[automation-id="documentsInboxes_applyFilterBtn_btn"]');
                sleep(10);
                $this->processInvoiceInbox($contractNumber);
            } elseif (!$this->exts->exists('select#category')) {
                $this->processInvoiceInbox($contractNumber);
            }
            $this->exts->execute_javascript('window.scrollTo(0, 0);');
            sleep(1);
        }
    } else {
        $this->exts->waitTillPresent('div.filter_doc ul', 25);
        $this->exts->moveToElementAndClick('div.filter_doc ul');
        sleep(1);
        if ($this->exts->exists('select#category option[value="Rechnung"]')) {
            $this->exts->execute_javascript("{let selectElement = document.querySelector('#category');
                selectElement.value = 'Rechnung';
                selectElement.dispatchEvent(new Event('input', { bubbles: true }));
                selectElement.dispatchEvent(new Event('change', { bubbles: true }));
                selectElement.dispatchEvent(new MouseEvent('click', { bubbles: true }));}");
            sleep(2);
            $this->exts->execute_javascript("{let selectElement = document.querySelector('#subCategory');
                selectElement.value = 'Rechnung';
                selectElement.dispatchEvent(new Event('input', { bubbles: true }));
                selectElement.dispatchEvent(new Event('change', { bubbles: true }));
                selectElement.dispatchEvent(new MouseEvent('click', { bubbles: true }));}");
            sleep(2);
            $this->exts->moveToElementAndClick('a[automation-id="documentsInboxes_applyFilterBtn_btn"]');
            sleep(10);
            $this->processInvoiceInbox($contractNumber);
        } elseif (!$this->exts->exists('select#category')) {
            $this->processInvoiceInbox($contractNumber);
        }
    }
}
function processMultiContractsInServicePage()
{
    $contract_len = count($this->exts->getElements('li[ng-repeat*="sortedContracts"], li[automation-id*="contract"]'));
    for ($i = 0; $i < $contract_len; $i++) {
        $contract = $this->exts->getElements('li[ng-repeat*="sortedContracts"], li[automation-id*="contract"]')[$i];
        if ($contract != null) {
            $this->exts->update_process_lock();
            $contract_button = $this->exts->getElement('a[ng-click*="openContract"], a.ac-head.accordion-anchor', $contract);

            $contract_name = $this->exts->extract('h2.contractName', $contract_button, 'innerText');
            $this->exts->log('======================== Get Contract ========================');
            $this->exts->log('contract name:' . $contract_name);
            $contract_num = trim(end(explode('Nr.', $this->exts->extract('div.contract-info', $contract_button, 'innerText'))));
            $this->exts->log('contract num:' . $contract_num);

            if ($contract_len != 1) {
                $this->exts->click_element($contract_button);
                sleep(5);
            }
            // continue if contract ended
            $mes = strtolower($contract_button->getAttribute('innerText'));
            if (strpos($mes, 'ist beendet') !== false) {
                continue;
            }
            $invoiceLink = $this->exts->getElement('a[href*="ihre-rechnungen/rechnungen"], a[href*="rechnungen/ihre-rechnungen"], a[automation-id="meineRechnungen_Link"]', $contract);
            if ($invoiceLink != null) {
                $this->exts->click_element($invoiceLink);
                $this->processInvoices($contract_num);
            }

            $this->exts->openUrl('https://www.vodafone.de/meinvodafone/services/');
            $this->exts->waitTillPresent('li[ng-repeat*="sortedContracts"], li[automation-id*="contract"]');
            $this->exts->capture('3.2-open-services');
            if ($this->exts->exists('div:not(.ng-hide) > .alert-old .error h4, div.doc-inbox-container .error h4')) {
                $this->exts->refresh();
                $this->exts->waitTillPresent('li[ng-repeat*="sortedContracts"], li[automation-id*="contract"]');
                $this->exts->capture('3.2-re-open-services');
            }
        }
    }
}
function processInvoiceInbox($contract_num = '', $paging_count = 1)
{
    $this->exts->capture("4-processInvoiceInbox-page");
    $invoices = [];

    $rows_len = count($this->exts->getElements('ul.documents-inbox-container div.box'));
    for ($i = 0; $i < $rows_len; $i++) {
        $row = $this->exts->getElements('ul.documents-inbox-container div.box')[$i];
        if ($this->exts->getElement('button[automation-id="documentsInboxes_download_btn"]', $row) != null) {
            $download_button = $this->exts->getElement('button[automation-id="documentsInboxes_download_btn"]', $row);

            $invoiceDate = trim($this->exts->extract('span[automation-id="documentsInboxes_date_tv"]', $row, 'innerText'));
            $invoiceName = $contract_num . str_replace('.', '', $invoiceDate);
            $invoiceAmount = '';

            $this->totalInvoices += 1;

            $invoiceFileName = $invoiceName . '.pdf';
            $invoiceDate = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoiceDate);

            if ($this->exts->document_exists($invoiceFileName)) {
                continue;
            }

            try {
                $this->exts->log('Click download button');
                $download_button->click();
            } catch (\Exception $exception) {
                $this->exts->log('Click download button by javascript');
                $this->exts->execute_javascript("arguments[0].click()", [$download_button]);
            }
            sleep(2);
            $this->exts->wait_and_check_download('pdf', 10);
            $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->log('create new file');
                $this->exts->new_invoice($invoiceName, $invoiceDate, '', $downloaded_file);
                sleep(1);
            } else {
                try {
                    $this->exts->log('Click download button');
                    $download_button->click();
                } catch (\Exception $exception) {
                    $this->exts->log('Click download button by javascript');
                    $this->exts->execute_javascript("arguments[0].click()", [$download_button]);
                }
                $this->exts->wait_and_check_download('pdf', 10);
                $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->log('create new file');
                    $this->exts->new_invoice($invoiceName, $invoiceDate, '', $downloaded_file);
                    sleep(1);
                } else {
                    try {
                        $this->exts->log('Click download button');
                        $download_button->click();
                    } catch (\Exception $exception) {
                        $this->exts->log('Click download button by javascript');
                        $this->exts->execute_javascript("arguments[0].click()", [$download_button]);
                    }
                    $this->exts->wait_and_check_download('pdf', 10);
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->log('create new file');
                        $this->exts->new_invoice($invoiceName, $invoiceDate, '', $downloaded_file);
                        sleep(1);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceName);
                    }
                }
            }
        }
    }

    // next page
    $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
    if (
        $restrictPages == 0 && $paging_count < 50 &&
        $this->exts->getElement('div.pagination li.pagecounter + li a:not(.fm-inactive):nth-child(1)') != null
    ) {
        $paging_count++;
        $this->exts->moveToElementAndClick('div.pagination li.pagecounter + li a:not(.fm-inactive):nth-child(1)');
        sleep(5);
        $this->processInvoiceInbox($contract_num, $paging_count);
    }
}
function processInvoices($contractNum)
{
    sleep(15);
    $this->cookieConsent();
    $this->exts->capture("4-invoices-page");
    $invoices = [];
    $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
    if ($restrictPages == 0 && $this->exts->exists('automation-id="see_more_btn"')) {
        $this->exts->moveToElementAndClick('automation-id="see_more_btn"');
        sleep(10);
    }
    $rows = $this->exts->getElements('table > tbody > tr');
    $this->exts->log("==== Rows Count: " . count($rows));

    for ($i = 0; $i < count($rows); $i++) {
        $tags = $this->exts->getElements('td', $rows[$i]);
        if (count($tags) >= 4 && $this->exts->getElement('svg[automation-id="table_2_svg"]', $tags[3]) != null) {
            $invoiceSelector = $this->exts->getElement('svg[automation-id="table_2_svg"]', $tags[3]);
            $this->exts->execute_javascript("arguments[0].setAttribute('id', 'custom-pdf-button-" . $i . "');", [$invoiceSelector]);
            $invoiceDate = trim($tags[0]->getAttribute('innerText'));
            $this->exts->log('Date before parsed: ' . $invoiceDate);
            $invoiceDate = $this->exts->parse_date($invoiceDate, 'F Y', 'Y-m-01', 'de');
            $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[1]->getAttribute('innerText'))) . ' EUR';

            $this->totalInvoices++;
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoiceName);
            $this->exts->log('invoiceDate: ' . $invoiceDate);
            $this->exts->log('invoiceAmount: ' . $invoiceAmount);

            if ($this->exts->exists('.invitationDesignMain button[id*="DeclineButton"]')) {
                $this->exts->moveToElementAndClick('.invitationDesignMain button[id*="DeclineButton"]');
                sleep(3);
            }
            if ($this->exts->exists('.invitationDesignMain button[id*="DeclineButton"]')) {
                $this->exts->moveToElementAndClick('.invitationDesignMain button[id*="DeclineButton"]');
                sleep(3);
            }
            if ($this->exts->exists('.nsm-content button[class*=btn-close]')) {
                $this->exts->refresh();
                $i--;
                sleep(3);
                continue;
            }
            // click and download invoice
            $download_button_selector = 'svg#custom-pdf-button-' . $i;
            if ($this->exts->exists($download_button_selector)) {
                try {
                    $this->exts->log("---Click invoice download button ");
                    $this->exts->moveToElementAndClick($download_button_selector);
                } catch (\Exception $e) {
                    $this->exts->log("---Click invoice download button by javascript ");
                    $this->exts->execute_javascript("document.querySelector(arguments[0]).dispatchEvent(new Event('click'));", [$download_button_selector]);
                }
            }
            sleep(2);
            $this->exts->wait_and_check_download('pdf', 10);
            $downloaded_file = $this->exts->find_saved_file('pdf');
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $invoiceFileName = basename($downloaded_file);
                $invoiceName = explode('.pdf', $invoiceFileName)[0];
                $this->exts->log('Final invoice name: ' . $invoiceName);

                // Download invoice if it not exisited
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