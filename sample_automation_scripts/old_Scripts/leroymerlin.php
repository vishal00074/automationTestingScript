<?php
// Server-Portal-ID: 405662 - Last modified: 30.09.2024 13:09:27 UTC - User: 1

// Script here
public $baseUrl = "https://www.leroymerlin.fr/espace-client/accueil";
public $username_selector = '.js-login-form-container:not([class*="password"]) input[name="email"]';
public $password_selector = '.password-input-show-container input[name="password"]';
public $logout_btn = '.layer-compte-deconnecter, a[href*="/logout"], [data-cerberus="BTN_monCompteHeader"] span.header-compte-name';
public $isNoInvoice = true;
public $restrictPages = 3;


/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count) {
    $this->exts->log('Begin initPortal '.$count);
    $proxy_success = $this->install_proxy_inscript();
    if(!$proxy_success){
        $this->exts->capture('failed-install-proxy-1');
        $proxy_success = $this->install_proxy_inscript();
    }
    $this->exts->log('proxy installed - '.$this->checkip());

    $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
    $this->exts->openUrl($this->baseUrl);
    sleep(10);
    for ($i=0; $i < 4 && $this->exts->exists('iframe[src*="captcha-delivery.com/captcha"]'); $i++) { 
        $this->change_random_ip_inscript();
        $this->exts->openUrl($this->baseUrl);
        sleep(10);
    }
    $this->close_all_popup();

    if(!$this->checkLogin()) {
        $this->checkFillLogin();
        sleep(7);
        $this->check_solve_blocked_page();
        $this->exts->waitTillPresent('.layer-compte-deconnecter, a[href*="/logout"], [data-cerberus="BTN_monCompteHeader"] span.header-compte-name, button.dashboard-header__logout-button, a[href*="listedecourses/liste.do"]');
    }

    if($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->close_all_popup();
        $this->exts->capture("LoginSuccess");

        $this->downloadInvoiceFromAchats();

        if ($this->isNoInvoice) {
            $this->exts->log("No invoice!!");
            $this->exts->no_invoice();
        }

        $this->exts->success();
    } else {
        $err_msg = strtolower($this->exts->extract('form#js-login-form [name="email"] + span.mc-field__error-message', null, 'innerText'));
        $this->exts->log("LoginFailed: ".$this->exts->getUrl());
        if ($this->exts->exists('.having-account__not-found')) {
            $this->exts->loginFailure(1);
        } else if ($this->exts->exists('iframe#oauth-iframe')) {
            $this->exts->switchToFrame('iframe#oauth-iframe');
            if (!filter_var($this->username, FILTER_VALIDATE_EMAIL)) $this->exts->loginFailure(1);
            $this->exts->loginFailure();
        } else if (strpos($err_msg, 'le champ doit contenir @ et une extension comme .fr ou .com') !== false) {
            $this->exts->log($err_msg);
            $this->exts->loginFailure(1);
        } else if($this->exts->exists('input[name="password"].is-invalid')){
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }			
    }
}
function close_all_popup() {
    if ($this->exts->exists('#onetrust-accept-btn-handler')){
        $this->exts->click_by_xdotool('#onetrust-accept-btn-handler');
        sleep(1);
    }
    if ($this->exts->exists('#surveyWindowWrap #closeModalBtn, #surveyBody #closeModalBtn')){
        $this->exts->click_by_xdotool('#surveyWindowWrap #closeModalBtn, #surveyBody #closeModalBtn');
        sleep(1);
    }
    if ($this->exts->exists('#js-privacy_all_accept')){
        $this->exts->click_by_xdotool('#js-privacy_all_accept');
        sleep(1);
    }
    if ($this->exts->exists('.mc-modal-open #component-privacyModal button.js-modal-privacy-button-accept')){
        $this->exts->click_by_xdotool('.mc-modal-open #component-privacyModal button.js-modal-privacy-button-accept');
        sleep(1);
    }
    if ($this->exts->exists('button.js-modal-privacy-button-accept')) {
        $this->exts->moveToElementAndClick('button.js-modal-privacy-button-accept');
        sleep(1);
    }
}
function checkFillLogin() {
    $this->exts->capture("2-check-login-page");
    $this->close_all_popup();
    if($this->exts->getElement($this->username_selector) != null) {
        sleep(1);
        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(2);
        $this->exts->capture("2-username-filled");
        $this->exts->moveToElementAndClick('.js-login-form-container:not([class*="password"]) button.js-button-continue');
        sleep(5);
    }

    if($this->exts->getElement($this->password_selector) != null) {
        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(1);

        $this->exts->capture("2-password-filled");
        $this->exts->moveToElementAndClick('#js-password-form button[type="submit"]');
        
    } else {
        $this->exts->log(__FUNCTION__.'::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}
function checkLogin() {
    return ($this->exts->exists('.layer-compte-deconnecter, a[href*="/logout"], [data-cerberus="BTN_monCompteHeader"] span.header-compte-name, button.dashboard-header__logout-button') || $this->exts->exists('a[href*="listedecourses/liste.do"]')) && $this->exts->exists($this->username_selector) == false;
}

// solve bot detecting
private function checkip(){
    $ip_detail = '';
    $ip_detail = $this->exts->execute_javascript('
        var xhr = new XMLHttpRequest();
        xhr.open("GET", "https://lumtest.com/myip.json", false);
        xhr.send();
        return xhr.responseText;
    ');
    return $ip_detail;
}
private function install_proxy_inscript(){
    $vpn_user = 'brd-customer-hl_b0a51fd2-zone-residential_rotate_ip_1';
    $vpn_pwd = 'cq1mweo863oj';
    $proxy_loggedin_selector = '.settings .ip, .settings .detail,  .settings .switch';
    $node_name = !empty($this->exts->config_array['node_name']) ? $this->exts->config_array['node_name'] : "selenium-node-".$this->exts->process_uid;
    $this->exts->openUrl("chrome-extension://efohiadmkaogdhibjbmeppjpebenaool/options.html");
    sleep(3);
    if(!$this->exts->exists($proxy_loggedin_selector) && !$this->exts->exists('.dialog_login')){
        $this->exts->openUrl('https://chromewebstore.google.com/detail/bright-data/efohiadmkaogdhibjbmeppjpebenaool?hl=en');
        sleep(3);
        $this->exts->capture_by_chromedevtool("proxy-extension-webstore", false);
        if($this->exts->getElement('//button/*[contains(text(), "Remove from Chrome")]', null, 'xpath')){
            // sleep(5);[aria-label="alert"] button
            $enable_button = $this->exts->getElement('//span[text()="Enable this item" or text()="Enable now"]/..', null, 'xpath');
            if($enable_button != null){
                $enable_button->click();
                sleep(7);
            }
            $this->exts->capture_by_chromedevtool("proxy-extension-webstore-2", false);
            $this->exts->openUrl("chrome-extension://efohiadmkaogdhibjbmeppjpebenaool/popup.html");
            sleep(5);
            if($this->exts->exists('.account_status.status_active') && !$this->exts->exists('.zone input[role="combobox"][value="residential_rotate_ip_1"]')){
                $this->exts->moveToElementAndClick('.zone input[role="combobox"]');
                sleep(1);
                $this->exts->moveToElementAndClick('[role="listbox"] a[aria-label="residential_rotate_ip_1"]');
                sleep(2);
                $this->exts->capture_by_chromedevtool("proxy-zone-selected", false);
            }
        } else {
            $submit_button = $this->exts->getElement('//button/*[contains(text(), "Add to Chrome")]/..', null, 'xpath');
            $this->exts->execute_javascript('arguments[0].click();', [$submit_button]);
            sleep(3);
            // Accept confirm alert
            exec("sudo docker exec ".$node_name." bash -c 'sudo xdotool key Tab'");
            exec("sudo docker exec ".$node_name." bash -c 'sudo xdotool key Return'");
            // END Accept confirm alert
            sleep(10);
            $this->exts->capture('bright-data-added');
            // Close advertising tab
            $this->exts->switchToTab($this->exts->init_tab);
        }
    }

    // input proxy credential
    $this->exts->openUrl("chrome-extension://efohiadmkaogdhibjbmeppjpebenaool/options.html");
    sleep(2);
    if(!$this->exts->exists($proxy_loggedin_selector)){
        if($this->exts->exists('.dialog_login .bext_button + .bext_button') && !$this->exts->exists('.auth_input input[type="password"]')){
            $this->exts->moveToElementAndClick('.dialog_login .bext_button + .bext_button'); //  Login using zone password
            sleep(1);
        }
        $this->exts->capture_by_chromedevtool("proxy-credential-form", false);
        $this->exts->moveToElementAndType('.auth_input input[type="text"]', $vpn_user);
        sleep(1);
        $this->exts->moveToElementAndType('.auth_input input[type="password"]', $vpn_pwd);
        sleep(1);
        $this->exts->capture_by_chromedevtool("proxy-credential-filled", false);
        $this->exts->moveToElementAndClick('.submit_buttons .bext_button + .bext_button');
        sleep(7);
        if($this->exts->exists('.auth_input input[type="password"]')){
            $this->exts->moveToElementAndType('.auth_input input[type="text"]', $vpn_user);
            sleep(1);
            $this->exts->moveToElementAndType('.auth_input input[type="password"]', $vpn_pwd);
            sleep(1);
            $this->exts->log('Unknow exception -submit proxy password again');
            $this->exts->moveToElementAndClick('.submit_buttons .bext_button + .bext_button');
            sleep(1);
            $this->exts->capture('re-submit-proxy-credential');
            sleep(7);
        }
    }

    if($this->exts->exists('.settings .switch.off')){
        $this->exts->moveToElementAndClick('.settings .switch.off');// Start proxy
        sleep(5);
        // Close advertising tab
        $this->exts->switchToTab($this->exts->init_tab);
    }
    $this->exts->capture("proxy-installed");

    if($this->exts->exists($proxy_loggedin_selector)){
        return true;
    } else {
        return false;
    }
}
private function change_random_ip_inscript(){
    $this->exts->log('Change IP');
    $vpn_user = 'brd-customer-hl_b0a51fd2-zone-residential_rotate_ip_1';
    $vpn_pwd = 'cq1mweo863oj';
    $this->exts->log('Location before refresh - '.$this->checkip());
    sleep(2);
    $this->exts->openUrl("chrome-extension://efohiadmkaogdhibjbmeppjpebenaool/options.html");
    sleep(3);
    $this->exts->capture("proxy-pre-refreshing");
    if($this->exts->oneExists(['.dialog_login', '.auth_input input[type="password"]'])){
        if($this->exts->exists('.dialog_login .bext_button + .bext_button') && !$this->exts->exists('.auth_input input[type="password"]')){
            $this->exts->moveToElementAndClick('.dialog_login .bext_button + .bext_button'); //  Login using zone password
            sleep(1);
        }
        $this->exts->capture("proxy-credential-form");
        $this->exts->moveToElementAndType('.auth_input input[type="text"]', $vpn_user);
        sleep(1);
        $this->exts->moveToElementAndType('.auth_input input[type="password"]', $vpn_pwd);
        sleep(1);
        $this->exts->capture("proxy-credential-filled");
        $this->exts->moveToElementAndClick('.submit_buttons .bext_button + .bext_button');
        sleep(7);
    }
    if($this->exts->exists('.settings .switch.off')){
        $this->exts->moveToElementAndClick('.settings .switch.off');// Start proxy
        sleep(5);
    }
    //$this->exts->switchToTab($this->exts->init_tab);
    $this->exts->moveToElementAndClick('.icon.refresh');
    sleep(5);
    //$this->exts->switchToTab($this->exts->init_tab);
    $this->exts->capture("proxy-refreshed-ip");
    $current_ip = $this->checkip();
    $this->exts->log('Location after refresh - '.$current_ip);
}
private function check_solve_blocked_page(){
    $current_url = $this->exts->getUrl();
    for ($i=0; $i < 4 && $this->exts->exists('iframe[src*="captcha-delivery.com/captcha"]'); $i++) { 
        $this->change_random_ip_inscript();
        $this->exts->openUrl($current_url);
        sleep(10);
    }
}
// END solve bot detecting

function downloadInvoiceFromAchats() {
    if($this->exts->exists('.nav-account__submenu-item-link[href*="/achats"]')){
        $this->exts->moveToElementAndClick('.nav-account__submenu-item-link[href*="/achats"]');
        sleep(7);
    } else {
        $this->exts->moveToElementAndClick('#component-home-nav [href*="/achats"], a[href*="/mes-achats/historique"]');
        sleep(10);
    }
    $this->check_solve_blocked_page();
    $this->exts->waitTillPresent('a.information-box[href*="/mes-achats/historique/"], .information-box__link [href*="/mes-achats/historique/"]', 30);
    $this->exts->capture("4-invoices-page-achats");
    $invoices = [];
    $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
    for ($i=0; $i < 10 && $restrictPages == 0 &&  $this->exts->getElement('button#js-loadmore') != null; $i++) { 
        $this->exts->moveToElementAndClick('button#js-loadmore');
        sleep(10);
    }
    $rows = $this->exts->getElements('a.information-box[href*="/mes-achats/historique/"], .information-box__link [href*="/mes-achats/historique/"]');
    foreach ($rows as $row) {
        $order_url = $row->getAttribute("href");
        $tempArr = explode('/historique/', $order_url);
        $invoiceName = end($tempArr);
        if(!$this->exts->invoice_exists($invoiceName)){
            array_push($invoices, array(
                'order_url'=>$order_url,
                'invoice_name'=>$invoiceName
            ));
        }else{
            $this->exts->log(__FUNCTION__.'::Document is exists!!' . $invoiceName);
        }
        
    }

    // Download all invoices
    $this->exts->log('Orders found: '.count($invoices));
    foreach ($invoices as $invoice) {
        $this->exts->log('Order URL: '.$invoice['order_url']);
        $this->exts->openUrl($invoice['order_url']);
        sleep(5);

        if($this->exts->exists('//button//*[contains(text(), "charger ma facture")]')){
            $this->isNoInvoice = false;
            $invoiceName = $invoice['invoice_name'];
            $invoiceFileName = $invoiceName.'.pdf';

            $this->exts->log('invoiceName: '.$invoiceName);
            $this->exts->moveToElementAndClick('//button//*[contains(text(), "charger ma facture")]');
            $this->exts->wait_and_check_download('pdf');
            $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
            if(trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoiceName, '', '', $invoiceFileName);
            } else {
                $this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
            }
        }
    } 
}