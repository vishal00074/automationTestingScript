<?php
// Server-Portal-ID: 1353710 - Last modified: 30.01.2025 13:06:46 UTC - User: 15

public $baseUrl = 'https://www.mobilcom-debitel.de/online-service/';
//public $loginUrl = 'https://identity.freenet-mobilfunk.de/login';
public $loginUrl = 'https://www.mobilcom-debitel.de/online-service/';
public $invoicePageUrl = 'https://www.mobilcom-debitel.de/online-service/';

public $username_selector = 'input#username';
public $password_selector = 'input#password';
public $submit_login_selector = '[data-qa="submit-login-form-button"],button[type="submit"][name="action"]';

public $check_login_failed_selector = '.status-message.-error, span.form-error, span[class="ulp-input-error-message"]';

public $isNoInvoice = true;
/**
* Entry Method thats called for a portal
* @param Integer $count Number of times portal is retried.
*/
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);


    // Load cookies
    $this->exts->openUrl($this->loginUrl);
    sleep(5);
    // $this->accept_cookies();
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if (!$this->isLoggedIn()) {
        $this->exts->log('NOT logged via cookie');
        //$this->fake_user_agent();

        $this->exts->openUrl($this->loginUrl);
        sleep(15);
        if (strpos(strtolower($this->exts->extract('#cf-error-details h1')), 'rror code 522') !== false) {
            $this->clearChrome();
            $this->exts->openUrl($this->loginUrl);
            sleep(15);
        }
       
        
        $this->checkFillLogin();
        sleep(10);

    
        // $this->accept_cookies();
        sleep(15);
        if ($this->exts->exists($this->password_selector)) {
            $this->exts->type_key_by_xdotool("F5");
            sleep(15);
            $this->checkFillLogin();
            sleep(30);
        }
        if ($this->exts->getElement('div#powerlayer-overlayer') != null) {
            $this->exts->moveToElementAndClick('a.icon-link.close');
            sleep(5);
        }
        if (!$this->isLoggedIn() && !$this->exts->exists($this->check_login_failed_selector)) {
            $this->exts->moveToElementAndClick('a.md-header__logo-link');
            sleep(10);
        }
    }

    for ($i = 0; $i < 7; $i++) {
        $this->exts->type_key_by_xdotool('Tab');
        sleep(1);
    }
    $this->exts->type_key_by_xdotool('Return');
    sleep(5);
    $this->exts->openUrl($this->baseUrl);
    


    if ($this->exts->getElement('div#powerlayer-overlayer') != null) {
        $this->exts->moveToElementAndClick('a.icon-link.close');
        sleep(5);
    }
    if ($this->exts->getElement('iframe[src*="&button=Teilnehmen"]') != null) {
        $this->exts->execute_javascript('document.elementFromPoint(10,10).click()');
        sleep(1);
    }

    $err_msg1 = $this->exts->extract('app-login-error span[class="status-message__text"], #prompt-alert p');
    $lowercase_err_msg = strtolower($err_msg1);
    $substrings = array('fehlerhafter login', 'wartungsarbeiten', 'please try again later', 'blocked', 'login attempts', 'attempts');
    foreach ($substrings as $substring) {
        if (strpos($lowercase_err_msg, strtolower($substring)) !== false) {
            $this->exts->log($err_msg1);
            $this->exts->loginFailure(1);
            break;
        }
    }


    if ($this->isLoggedIn()) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        $this->exts->openUrl('https://www.freenet-mobilfunk.de/onlineservice/meine-rechnungen');
        sleep(25);

        if ($this->exts->exists('div[data-qa="selected-billing-account-option"]')) {
        $this->exts->moveToElementAndClick('div[data-qa="selected-billing-account-option"]');
        sleep(2);
        $accounts_count = count($this->exts->getElements('div.select-billing-account > div[data-qa*="billing-account-option"]'));
        for ($i = 1; $i <= $accounts_count; $i++) {
            $this->exts->moveToElementAndClick('div.select-billing-account > div[data-qa*="billing-account-option"]:nth-child(' . $i . ')');
            $this->processInvoicesNew();
            $this->exts->moveToElementAndClick('div[data-qa="selected-billing-account-option"]');
            sleep(2);
            }
        } else {
        $this->processInvoicesNew();
        }


        if ($this->isNoInvoice) {
        // Huy added this 2021-11 since it display blank for invoice page, unknow error
        $this->process_invoice_via_api();
        }
        // Final, check no invoice
        if ($this->isNoInvoice) {
            $this->exts->no_invoice();
        }
        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed ' . $this->exts->getUrl());
    if ($this->exts->exists('input#firstIdentifier')) {
        $this->exts->log('account need enter your customer or telephone number.');
        $this->exts->account_not_ready();
    }

    if (strpos($this->username, '@') === false) {
    $this->exts->loginFailure(1);
    }

    $this->checkError();

    // They can display Wrong credential even when Credential is correct, since reCaptcha failed, So don't return login failed confirm
        if ($this->exts->exists($this->check_login_failed_selector) && strpos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'passwor') !== false) {
        $this->exts->loginFailure(1);
        } else {
        $this->exts->loginFailure();
        }
    }
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
        sleep(1);
    }
    $this->exts->type_key_by_xdotool('Return');
    sleep(15);
    $this->exts->capture("after-clear");
}


public function processInvoicesNew()
{
    $this->exts->capture("invoices-page");
    try {
        $rows = $this->exts->getElements('ecare-ui-kit-accordion[dataqa="invoice-item"]');
        $this->exts->log("Number of Invoice Rows- " . count($rows));

        for ($i = 0; $i < count($rows); $i++) {
            $row=$this->exts->getElements('ecare-ui-kit-accordion[dataqa="invoice-item"]')[$i];
            $row->click();
            //$this->exts->moveToElementAndClick($row);
            $download_button = $this->exts->getElement('span[data-qa="invoice-download-pdf"]', $row);

            if ($download_button != null) {

                $invoiceName = $this->exts->extract('span[data-qa="title-text"] span[data-qa="invoice-number"]', $row, 'innerText');
                $invoiceFileName = $invoiceName . '.pdf';
                $invoiceDate = trim($this->exts->extract('span[data-qa="title-text"] span[class="title"]', $row, 'innerText'));
                $invoiceDate = trim(explode(' ', $invoiceDate)[0]);
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->exts->extract('td:nth-child(5)', $row, 'innerText'))) . ' EUR';

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $this->isNoInvoice = false;

                if ($this->exts->invoice_exists($invoiceName) || $this->exts->document_exists($invoiceFileName)) {
                $this->exts->log('Invoice existed ' . $invoiceFileName);
                } else {
                    // try {
                    // $this->exts->log('Click download button');
                    // $download_button->click();
                    // } catch (\Exception $exception) {
                    // $this->exts->log('Click download button by javascript');
                    // $this->exts->execute_javascript("arguments[0].click()", [$download_button]);
                    // }
                    $this->exts->execute_javascript("arguments[0].click()", [$download_button]);

                    sleep(2);
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
                }
                sleep(1);
            }
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception process processInvoicePage " . $exception->getMessage());
    }
}


private function checkFillLogin()
{
    $this->exts->waitTillPresent($this->username_selector, 20);

    if ($this->exts->exists($this->username_selector)) {
        sleep(3);
        $this->exts->capture("2-login-page");
        $this->solve_login_cloudflare();

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(2);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        $this->exts->click_by_xdotool($this->password_selector);
        sleep(2);
        $this->exts->capture("2-login-page-filled");
        $this->exts->click_by_xdotool($this->submit_login_selector);
    } else {
       
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");

        $login_page = $this->exts->findTabMatchedUrl(['login']);
        if ($login_page != null) {
           $this->checkFillLoginUndetected();
        }

        
    }
}


private function checkFillLoginUndetected()
{
	$this->exts->log('Fill form by using Tab');
    $this->exts->log("Enter Username");
    $this->exts->type_text_by_xdotool($this->username);
    $this->exts->type_key_by_xdotool("Tab");
    sleep(2);
    $this->exts->log("Enter Password");
    $this->exts->type_text_by_xdotool($this->password);
    sleep(4);

    for ($i = 0; $i < 4; $i++) {
        $this->exts->type_key_by_xdotool("Tab");
        sleep(1);
    }
    $this->exts->type_key_by_xdotool("Return");
    sleep(5);

}



private function checkFillRecaptcha($count = 1)
{
$this->exts->log(__FUNCTION__);
$recaptcha_iframe_selector = 'iframe[src*="/recaptcha/api2/anchor?"]';
$recaptcha_textarea_selector = 'textarea[name="g-recaptcha-response"]';
if ($this->exts->exists($recaptcha_iframe_selector)) {
$iframeUrl = $this->exts->extract($recaptcha_iframe_selector, null, 'src');
$data_siteKey = explode('&', end(explode("&k=", $iframeUrl)))[0];
$this->exts->log("iframe url - " . $iframeUrl);
$this->exts->log("SiteKey - " . $data_siteKey);

$isCaptchaSolved = $this->exts->processRecaptcha($this->exts->getUrl(), $data_siteKey, false);
$this->exts->log("isCaptchaSolved - " . $isCaptchaSolved);

if ($isCaptchaSolved) {
// Step 1 fill answer to textarea
$this->exts->log(__FUNCTION__ . "::filling reCaptcha response..");
$recaptcha_textareas = $this->exts->getElements($recaptcha_textarea_selector);
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
    if(prop.indexOf(".callback") > -1){result = prop; found = true; return;
    } else { if(cur == undefined || cur == null || cur instanceof Element || Object(cur) !== cur || Array.isArray(cur)){ return;}deep++;
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
    }
    } else {
    if ($count < 3) {
        $count++;
        $this->checkFillRecaptcha($count);
        }
        }
        } else {
        $this->exts->log(__FUNCTION__ . '::Not found reCaptcha');
        }
}
private function isLoggedIn()
{
    if ($this->exts->urlContains('/dashboard') || $this->exts->urlContains('/meine-rechnungen') || $this->exts->exists('#loginTitel')) {
    return true;
    }

    //$this->exts->moveToElementAndClick('#loginBlock .md-header__top-bar-nav-icon,#loginBlock');
    $this->exts->moveToElementAndClick('nav[class="secondary-navigation"] ul li:nth-child(3)');
    sleep(2);
    if (($this->exts->exists('.ps-dashboard') || $this->exts->exists('button#btnLogoutBlock') || $this->exts->exists('a[href*="/online-service/#/erstanmeldung"]')) && !$this->exts->exists($this->password_selector) && !$this->exts->exists('iframe[src*="identity"]')) {
       return true;
    } else {
    $account_button = $this->exts->getElement('div#menuBtn');
    // $this->hoverOnElement($account_button);
        sleep(5);
        if ($this->exts->exists('button#btnLogoutBlock')) {
            return true;
        } else {
        return false;
        }
    }
}

// private function accept_cookies()
// {
//     if ($this->exts->exists('iframe[id*="sp_message_iframe"]')) {
//         $cookie_iframe = $this->exts->makeFrameExecutable('iframe[id*="sp_message_iframe"]');
//         sleep(1);
//         $cookie_iframe->moveToElementAndClick('button.message-button[aria-label*="Zustimmen"]');
//         sleep(3);
//     }

//     if ($this->exts->exists('iframe#sp_privacy_manager_iframe')) {
//         $cookie_iframe = $this->exts->makeFrameExecutable('iframe#sp_privacy_manager_iframe');
//         sleep(1);
//         $this->exts->moveToElementAndClick('a.priv-enable-btn.w-button');
//         sleep(2);
//     }

//     if ($this->exts->exists('iframe[id*="sp_message_iframe"]')) {
//         $cookie_iframe = $this->exts->makeFrameExecutable('iframe[id*="sp_message_iframe"]');
//         sleep(1);
//         $this->exts->moveToElementAndClick('button[class*="message-component message-button"]:nth-child(3)');
//         sleep(5);
//     }
//     if ($this->exts->exists('iframe[id*="message"]')) {
//         $cookie_iframe = $this->exts->makeFrameExecutable('iframe[id*="message"]');
//         sleep(1);
//         $this->exts->moveToElementAndClick('button.message-button.last-focusable-el');
//         sleep(5);
//     }
// }



private function solve_login_cloudflare()
{
    $unsolved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) <= 0]';
    $solved_cloudflare_input_xpath='//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) > 0]' ;
    $this->exts->capture("cloudflare-checking");
    if (
        !$this->exts->oneExists([$solved_cloudflare_input_xpath, $unsolved_cloudflare_input_xpath]) &&
        $this->exts->exists('#cf-please-wait > p:not([style*="display: none"]):not([style*="display:none"])')
    ) {
        for ($waiting = 0; $waiting < 10; $waiting++) {
            sleep(2);
            if ($this->exts->oneExists([$solved_cloudflare_input_xpath, $unsolved_cloudflare_input_xpath])) {
                sleep(3);
                break;
            }
        }
    }

    if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
         $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
        sleep(5);
        $this->exts->capture("cloudflare-clicked-1", true);
        sleep(3);
    if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
        $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
         sleep(5);
        $this->exts->capture("cloudflare-clicked-2", true);
        sleep(15);
    }
    if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
        $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
        sleep(5);
        $this->exts->capture("cloudflare-clicked-3", true);
        sleep(15);
    }
  }
}
private function processInvoices()
{
    sleep(10);
    if ($this->exts->getElement('div.cookie-message') != null) {
        $cookie = $this->exts->getElement('div.cookie-message');
        $this->exts->execute_javascript('arguments[0].style.display = "none";', [$cookie]);
    }
         $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
    if ($restrictPages == 0 && $this->exts->getElement('section.further-items.show-items a') != null) {
        $this->exts->moveToElementAndClick('section.further-items.show-items a');
        sleep(5);
    }
        $this->exts->capture("4-invoices-page");
        $invoice_sections = $this->exts->getElements('[data-qa="invoice-table"]');
    foreach ($invoice_sections as $invoice_section) {
        $download_button = $this->exts->getElement('[data-qa="invoice-table-content"] [data-qa="invoice-download-pdf"]', $invoice_section);
        if ($download_button != null) {
        $this->isNoInvoice = false;
        $invoiceName = $this->exts->extract('.title-bar .subheading', $invoice_section, 'innerText');
        $invoiceName = end(explode(',', $invoiceName));
        $invoiceName = trim($invoiceName);
        $invoiceFileName = $invoiceName . '.pdf';
        $invoiceDate = '';
        $invoiceAmount = '';

        $this->exts->log('--------------------------');
        $this->exts->log('invoiceName: ' . $invoiceName);
        $this->exts->log('invoiceDate: ' . $invoiceDate);
        $this->exts->log('invoiceAmount: ' . $invoiceAmount);

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
                    sleep(10);
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                    sleep(1);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No pdf ' . $invoiceFileName);
                }
            }
        }
    }
}
private function checkError()
{

    if ($this->exts->exists('app-login-error div[class*="status-message"] span[data-qa="error-message"]')) {

    $err_msg1 = $this->exts->extract('app-login-error div[class*="status-message"] span[data-qa="error-message"]');
    $lowercase_err_msg = strtolower($err_msg1);
    $substrings = array('technische Störung vor', 'technische', 'leider konnte kein benutzer mit diesen zugangsdaten gefunden werden. bitte überprüfen sie ihre eingegebene e-mail-adresse und das passwort.', 'leider', 'Bitte überprüfen Sie Ihre eingegebene E-Mail-Adresse und das Passwort');
    foreach ($substrings as $substring) {
    if (strpos($lowercase_err_msg, strtolower($substring)) !== false) {
    $this->exts->log($err_msg1);
    $this->exts->loginFailure(1);
    break;
    }
    }
    }
}
private function process_invoice_via_api()
{
    $customerId = trim(array_pop(explode(': ', $this->exts->extract('h2#kdnr', null, 'innerText'))));
    $contracts = $this->exts->execute_javascript('
    var data = [];
    try{
    var xhr = new XMLHttpRequest();
    xhr.open("GET", "https://www.freenet-mobilfunk.de/api/ps/customers/' . $customerId . '/contracts", false);
    xhr.withCredentials = true;
    xhr.setRequestHeader("authorization", "Bearer " + document.cookie.split("accessToken=").pop().split(";")[0].trim());
    xhr.send();
    var jo = JSON.parse(xhr.responseText);
    var contracts = Object.keys(jo.contracts);
    data = contracts;
    } catch(ex){
    console.log(ex);
    }
    return data;
    ');
    $this->exts->log('contracts found: ' . json_encode($contracts));

    $invoices = [];
    foreach ($contracts as $contract) {
    $invoiceList = $this->exts->execute_javascript('
    var data = [];
    var invoice_names = [];
    try{
    var xhr = new XMLHttpRequest();
    xhr.open("GET", "https://www.freenet-mobilfunk.de/api/ps/contracts/' . $contract . '/invoices?invoiceStartDate=&invoiceEndDate=&monthsIntervall=50", false);
    xhr.withCredentials = true;
    xhr.setRequestHeader("authorization", "Bearer " + document.cookie.split("accessToken=").pop().split(";")[0].trim());
    xhr.send();
    var jo = JSON.parse(xhr.responseText);
    var invoices = jo.data;
    for (var i = 0; i < invoices.length; i ++) {
    var inv=invoices[i];
    if(invoice_names.indexOf(inv.invoiceNumber) < 0){
    var dt=new Date(inv.invoiceDate*1000);
    data.push({
    invoiceName: inv.invoiceNumber,
    invoiceDate: dt.toISOString().split("T")[0],
    invoiceAmount: inv.amountTotal + " EUR" ,
    invoiceUrl: "https://www.freenet-mobilfunk.de/api" + inv.uri
    });
    invoice_names.push(inv.invoiceNumber);
    }
    }

    } catch(ex){
    console.log(ex);
    }
    return data; ');

    $invoices = array_merge($invoices, $invoiceList);
    }
    // Download all invoices
    $this->exts->log(' Invoices found: ' . count($invoices));
    foreach ($invoices as $invoice) {
    $this->isNoInvoice = false;
    $this->exts->log(' --------------------------');
    $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
    $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
    $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
    $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);
    $invoiceFileName = $invoice['invoiceName'] . '.pdf';
    // Download invoice if it not exisited
    if ($this->exts->invoice_exists($invoice['invoiceName'])) {
    $this->exts->log('Invoice existed ' . $invoiceFileName);
    } else {
    // Get pdf from api
    $this->exts->execute_javascript('
    var xhr = new XMLHttpRequest();
    xhr.open("GET", "' . $invoice['invoiceUrl'] . '", false);
    xhr.setRequestHeader("Authorization", "Bearer " + document.cookie.split("accessToken=").pop().split(";")[0].trim());
    xhr.setRequestHeader("Accept", "application/pdf");
    xhr.overrideMimeType("text/plain; charset=x-user-defined");
    xhr.send();

    var byteCharacters = xhr.responseText;
    var byteArrays = [];
    for (var offset = 0; offset < byteCharacters.length; offset +=512) {
    var slice=byteCharacters.slice(offset, offset + 512);
    var byteNumbers=new Array(slice.length);
    for (var i=0; i < slice.length; i++) {
    byteNumbers[i]=slice.charCodeAt(i);
    }
    var byteArray=new Uint8Array(byteNumbers);
    byteArrays.push(byteArray);
    }
    var blob=new Blob(byteArrays, {type: "application/pdf" });
    window.open(window.URL.createObjectURL(blob), "_blank" ); ');
    sleep(1);
    $this->exts->wait_and_check_download(' pdf');
    $downloaded_file=$this->exts->find_saved_file('pdf', $invoiceFileName);
    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
    $pdf_content = file_get_contents($downloaded_file);
    if (stripos($pdf_content, "%PDF") !== false) {
    $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
    sleep(1);
    } else {
    $this->exts->log(__FUNCTION__ . ":: Not Valid PDF - " . $downloaded_file);
    }
    } else {
    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
    }
    }
    }
}