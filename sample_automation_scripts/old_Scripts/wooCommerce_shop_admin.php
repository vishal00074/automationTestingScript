<?php  // migrated and updated download code  last 
// Server-Portal-ID: 844623 - Last modified: 09.08.2023 03:30:19 UTC - User: 1

/*Define constants used in script*/
public $base_url = '';
public $username_selector = 'form#loginform input#user_login, form.login input#username';
public $password_selector = 'form#loginform input#user_pass, form.login input#password';
public $remember_me_selector = 'form#loginform input#rememberme, form.login input#rememberme';
public $submit_login_selector = 'form#loginform input#wp-submit[type="submit"], form.login button[name="login"], form.login input[type="submit"]';

public $check_login_failed_selector = 'form div.has-error';
public $check_login_success_selector = 'li#wp-admin-bar-my-account a[href*="/wp-admin/profile.php"]';
public $restrictPages = 3;
public $isNoInvoice = true;
public $cancellation_refund = 0;

/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count) {
    
    $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
    
    $this->base_url = '';
    if(isset($this->exts->config_array["custom_url"]) && trim($this->exts->config_array["custom_url"]) != '') {
        $this->base_url = trim($this->exts->config_array["custom_url"]);
    } else if(isset($this->exts->config_array["customUrl"]) && trim($this->exts->config_array["customUrl"]) != ''){
        $this->base_url = trim($this->exts->config_array["customUrl"]);
    } else if(isset($this->exts->config_array["login_url"]) && trim($this->exts->config_array["login_url"]) != '') {
        $this->base_url = trim($this->exts->config_array["login_url"]);
    }

    $this->cancellation_refund = isset($this->exts->config_array["cancellation_refund"]) ? (int)@$this->exts->config_array["cancellation_refund"] : 0;
    
    //i have hard code base_url, pls remove it, thanks
    //$this->base_url = 'https://clavis-schule.de/wp-admin';
    $this->exts->log('Base url: '.$this->base_url);
    
    if(trim($this->base_url) != '' && strpos($this->base_url, 'https://') === false && strpos($this->base_url, 'http://') === false) {
        $this->base_url = 'https://' . $this->base_url;
    }

    // hardcoded added base url for testing on testing engine
    $this->base_url = 'https://www.shapingwaves.com/tuer/';

    if ($this->base_url == '') {
        $this->exts->loginFailure(1);
    }
    $this->exts->log($this->base_url);
    
    $this->exts->openUrl($this->base_url);
    sleep(1);
    
    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->base_url);
    sleep(10);
    if(strpos($this->exts->extract('body > h1'), 'DEVELOPMENT CENTER FOR BERLIN WEB AGENCY') !== false){
        $this->base_url = str_replace('https://', 'http://', $this->base_url);
        $this->exts->openUrl($this->base_url);
        sleep(10);
    }
    $this->exts->capture('1-init-page');
    
    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if(!$this->check_login()) {
        $this->exts->log('NOT logged via cookie');
        
        $this->exts->clearCookies();

        $this->exts->openUrl($this->base_url);
        sleep(15);
        if($this->exts->exists('a[href="/my-vetmeet/"]') && $this->exts->getElement($this->password_selector) == null){
            $this->exts->moveToElementAndClick('a[href="/my-vetmeet/"]');
            sleep(10);
        }
        if($this->exts->exists('button#wp-webauthn')){
            $this->exts->moveToElementAndClick('button#wp-webauthn');
            sleep(10);
        }
        if($this->exts->exists('a[href*="/mein-konto"]') && $this->exts->getElement($this->password_selector) == null){
            $this->exts->moveToElementAndClick('a[href*="/mein-konto"]');
            sleep(10);
        }
        $this->checkFillLogin();
        sleep(20);
        if($this->exts->exists('form#mo2f_inline_verifyphone_form input[name="verify"]')){
            $this->exts->moveToElementAndClick('form#mo2f_inline_verifyphone_form input[name="verify"]');
            sleep(10);
        }
        $this->checkFillTwoFactor();
        sleep(12);
        if($this->exts->exists('.admin-email__actions a[href*="remind_me_later"]') && !$this->check_login()){
            $this->exts->moveToElementAndClick('.admin-email__actions a[href*="remind_me_later"]');
            sleep(10);
        }
    }
    
    // then check user logged in or not
    if($this->check_login()) {
        sleep(3);
        $this->exts->log(__FUNCTION__.'::User logged in');
        $this->exts->capture("3-login-success");
        $url_after_login = $this->exts->getUrl();
        // Open invoices url and download invoice

        if ($this->exts->exists('li[class="wp-has-submenu wp-not-current-submenu menu-top toplevel_page_woocommerce"] > a[href*="admin.php"]')) {
            $this->exts->log('Click on order');
            $this->exts->click_by_xdotool('li[class="wp-has-submenu wp-not-current-submenu menu-top toplevel_page_woocommerce"] > a[href*="admin.php"]');
            sleep(10);
        }

        if ($this->exts->exists('#toplevel_page_woocommerce a[href="edit.php?post_type=shop_order"]')) {
            $this->exts->log('Click on order');
            $this->exts->click_by_xdotool('#toplevel_page_woocommerce a[href="edit.php?post_type=shop_order"]');
            sleep(10);
        }
        if ($this->exts->exists('#wpbody-content a[href="edit.php?post_status=wc-completed&post_type=shop_order"]')) {
            $this->exts->click_by_xdotool('#wpbody-content a[href="edit.php?post_status=wc-completed&post_type=shop_order"]');
            sleep(10);
        }
        if($this->exts->exists('.woocommerce-MyAccount-navigation-link a[href*="my-vetmeet/orders"]')){
            $this->exts->click_by_xdotool('.woocommerce-MyAccount-navigation-link a[href*="my-vetmeet/orders"]');
            sleep(10);
        }

        if($this->exts->exists('a[href*="mein-konto/orders"]')){
            $this->exts->click_by_xdotool('a[href*="mein-konto/orders"]');
            sleep(10);
        }
        
        if($this->exts->exists('table.wp-list-table.posts tr td.wc_actions a.invoice_pdf[href*="action=woocommerce_wp_wc_invoice_pdf_invoice_download"]')) {
            $this->processInvoices();
        } else {
            $this->processDetialPageInvoices();
        }

        if ($this->cancellation_refund == 1) {
            $this->exts->openUrl($url_after_login);
            sleep(15);
            $this->exts->moveToElementAndClick('a[href="admin.php?page=wc-admin"]');
            sleep(10);
            $this->exts->moveToElementAndClick('a[href="admin.php?page=wgm-refunds"]');
            sleep(10);
            $this->processRefundInvoices();
        }
        
        // Final, check no invoice
        if($this->isNoInvoice){
            $this->exts->no_invoice();
        }
    } else {
        if(strpos($this->exts->extract('.woocommerce-error li', null, 'innerText'), 'is not registered on this site') !== false || strpos(strtolower($this->exts->extract('.woocommerce-error li', null, 'innerText')), 'unknown email address') !== false){
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}


private function checkFillLogin() {
    if($this->exts->getElement($this->password_selector) != null) {
        sleep(3);
        $this->exts->capture("2-login-page");
        
        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(1);
        
        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(1);

        $this->checkFillRecaptcha();
        
        $this->exts->capture("2-login-page-filled");
        $this->exts->moveToElementAndClick($this->submit_login_selector);
        sleep(12);
        for ($i = 0; $i < 2; $i++) {
            if ($this->exts->exists('iframe[src*="/recaptcha/api2/anchor?"]')) {
                $this->checkFillRecaptcha();
            } else {
                break;
            }
        }
        
        
    } else {
        $this->exts->log(__FUNCTION__.'::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}

private function checkFillTwoFactor() {
    $two_factor_selector = 'input#wfls-token';
    $two_factor_message_selector = '';
    $two_factor_rememberme_selector = 'input#wfls-remember-device';
    $two_factor_submit_selector = 'input#wfls-token-submit';
    
    if($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3){
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");
        
        // if($this->exts->getElement($two_factor_message_selector) != null){
        //     $this->exts->two_factor_notif_msg_en = "";
        //     for ($i=0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
        //         $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en.$this->exts->getElements($two_factor_message_selector)[$i]->getText()."\n";
        //     }
        //     $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
        //     $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
        //     $this->exts->log("Message:\n".$this->exts->two_factor_notif_msg_en);
        // }

        $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . trim(explode('. ', $this->exts->extract('a[class*="2fa-code-help wfls-tooltip"]', null, 'innerText'))[0]);
        $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
        $this->exts->log("Message:\n".$this->exts->two_factor_notif_msg_en);

        if($this->exts->two_factor_attempts == 2) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en .' '. $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de .' '. $this->exts->two_factor_notif_msg_retry_de;
        }
        
        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if(!empty($two_factor_code) && trim($two_factor_code) != '') {
            $this->exts->log("checkFillTwoFactor: Entering two_factor_code.".$two_factor_code);
            $this->exts->sendKeys($two_factor_selector, $two_factor_code);
            
            $this->exts->log("checkFillTwoFactor: Clicking submit button.");
            sleep(3);
            $this->exts->moveToElementAndClick($two_factor_rememberme_selector);
            sleep(1);
            $this->exts->capture("2.2-two-factor-filled-".$this->exts->two_factor_attempts);
            
            $this->exts->moveToElementAndClick($two_factor_submit_selector);
            sleep(15);
            
            $this->exts->capture('after-submit-2fa');
            
            if($this->exts->getElement($two_factor_selector) == null){
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
    } else {
        $two_factor_selector = 'input[name="otp_token"]';
        $two_factor_message_selector = 'p.mo2fa_display_message_frontend';
        $two_factor_submit_selector = 'form#mo2f_inline_validateotp_form input[name="validate"]';

        if($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3){
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if($this->exts->getElement($two_factor_message_selector) != null){
                $this->exts->two_factor_notif_msg_en = "";
                for ($i=0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) { 
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en.$this->exts->getElements($two_factor_message_selector)[$i]->getText()."\n";
                }
                $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
                $this->exts->log("Message:\n".$this->exts->two_factor_notif_msg_en);
            }
            if($this->exts->two_factor_attempts == 2) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en .' '. $this->exts->two_factor_notif_msg_retry_en;
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de .' '. $this->exts->two_factor_notif_msg_retry_de;
            }

            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            if(!empty($two_factor_code) && trim($two_factor_code) != '') {
                $this->exts->log("checkFillTwoFactor: Entering two_factor_code.".$two_factor_code);
                $this->exts->getElement($two_factor_selector)->sendKeys($two_factor_code);
                
                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(3);
                $this->exts->capture("2.2-two-factor-filled-".$this->exts->two_factor_attempts);

                $this->exts->moveToElementAndClick($two_factor_submit_selector);
                sleep(15);

                if($this->exts->getElement($two_factor_selector) == null){
                    $this->exts->log("Two factor solved");
                } else if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->notification_uid = "";
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
}

private function checkFillRecaptcha() {
    $this->exts->log(__FUNCTION__);
    $recaptcha_iframe_selector = 'iframe[src*="/recaptcha/api2/anchor?"]';
    $recaptcha_textarea_selector = 'textarea[name="g-recaptcha-response"]';
    if($this->exts->exists($recaptcha_iframe_selector)) {
        $iframeUrl = $this->exts->extract($recaptcha_iframe_selector, null, 'src');
        $data_siteKey = explode('&', end(explode("&k=", $iframeUrl)))[0];
        $this->exts->log("iframe url  - " . $iframeUrl);
        $this->exts->log("SiteKey - " . $data_siteKey);
        
        $isCaptchaSolved = $this->exts->processRecaptcha($this->exts->getUrl(), $data_siteKey, false);
        $this->exts->log("isCaptchaSolved - " . $isCaptchaSolved);
        
        if($isCaptchaSolved) {
            // Step 1 fill answer to textarea
            $this->exts->log(__FUNCTION__."::filling reCaptcha response..");
            $recaptcha_textareas =  $this->exts->getElements($recaptcha_textarea_selector);
            for ($i=0; $i < count($recaptcha_textareas); $i++) { 
                $this->exts->execute_javascript("arguments[0].innerHTML = '" .$this->exts->recaptcha_answer. "';", [$recaptcha_textareas[$i]]);
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
            $this->exts->log('Callback function: '.$gcallbackFunction);
            if($gcallbackFunction != null){
                $this->exts->execute_javascript($gcallbackFunction.'("'.$this->exts->recaptcha_answer.'");');
                sleep(10);
            }
        }
    } else {
        $this->exts->log(__FUNCTION__.'::Not found reCaptcha');
    }
}

public function check_login() {
    if($this->exts->exists($this->check_login_success_selector)) {
        return true;
    }
    return false;
}

public function processInvoices($page_count = 1) {
    $this->exts->capture("4-invoices-page");
    
    $invoices = [];
    $rows = $this->exts->getElements('table.wp-list-table.posts tbody tr');
    foreach ($rows as $row) {
        $tags = $this->exts->getElements('td', $row);
        if($this->exts->getElement('a.invoice_pdf[href*="action=woocommerce_wp_wc_invoice_pdf_invoice_download"]', $tags[4]) != null) {
            $invoiceUrl = $this->exts->getElement('a.invoice_pdf[href*="action=woocommerce_wp_wc_invoice_pdf_invoice_download"]', $tags[4])->getAttribute("href");
            $invoiceName = '';
            $invoiceDate = trim($tags[2]->getAttribute('innerText'));
            $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[3]->getAttribute('innerText'))) . ' EUR';
            if (strpos($this->base_url, "kolaleipzig.de") !== false) {
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[8]->getAttribute('innerText'))) . ' EUR';
                $invoiceDate = explode("T", $this->exts->getElement('time', $tags[3])->getAttribute('datetime'))[0];
            }
            array_push($invoices, array(
                'invoiceName'=>$invoiceName,
                'invoiceDate'=>$invoiceDate,
                'invoiceAmount'=>$invoiceAmount,
                'invoiceUrl'=>$invoiceUrl
            ));
            $this->isNoInvoice = false;
        } else if($this->exts->getElement('a.invoice_pdf[href*="action=woocommerce_wp_wc_invoice_pdf_invoice_download"]', $tags[8]) != null) {
            $invoiceUrl = $this->exts->getElement('a.invoice_pdf[href*="action=woocommerce_wp_wc_invoice_pdf_invoice_download"]', $tags[8])->getAttribute("href");
            $invoiceName = '';
            $invoiceDate = trim($tags[2]->getAttribute('innerText'));
            $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[6]->getAttribute('innerText'))) . ' EUR';
            array_push($invoices, array(
                'invoiceName'=>$invoiceName,
                'invoiceDate'=>$invoiceDate,
                'invoiceAmount'=>$invoiceAmount,
                'invoiceUrl'=>$invoiceUrl
            ));
            $this->isNoInvoice = false;
        }
    }
    
    // Download all invoices
    $this->exts->log('Invoices found: '.count($invoices));
    foreach ($invoices as $invoice) {
        $this->exts->log('--------------------------');
        $this->exts->log('invoiceName: '.$invoice['invoiceName']);
        $this->exts->log('invoiceDate: '.$invoice['invoiceDate']);
        $this->exts->log('invoiceAmount: '.$invoice['invoiceAmount']);
        $this->exts->log('invoiceUrl: '.$invoice['invoiceUrl']);
        
        $invoiceFileName = '';
        if (strpos($this->base_url, "kolaleipzig.de") === false) {
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd. M. Y','Y-m-d');
        }
        $this->exts->log('Date parsed: '.$invoice['invoiceDate']);
        
        $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
        if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
            $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $downloaded_file);
            sleep(1);
        } else {
            $this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
        }
    }
    
    if($this->restrictPages == 0 && $this->exts->exists('.top .tablenav-pages .pagination-links a.next-page.button') && $page_count < 100)  {
        //This is needed because opening take lots of time.
        $this->exts->update_process_lock();
        $this->exts->moveToElementAndClick('.top .tablenav-pages .pagination-links a.next-page.button');
        sleep(15);
        $page_count++;
        $this->processInvoices($page_count);
    } else if($this->exts->exists('.top .tablenav-pages .pagination-links a.next-page.button') && $page_count < 15)  {
        //This is needed because opening take lots of time.
        $this->exts->update_process_lock();
        $this->exts->moveToElementAndClick('.top .tablenav-pages .pagination-links a.next-page.button');
        sleep(15);
        $page_count++;
        $this->processInvoices($page_count);
    }
}

public function processDetialPageInvoices($page_count=1) {
    $this->exts->capture("4-invoices-page");
    
    $invoices = [];
    $rows = $this->exts->getElements('table.wp-list-table.posts tr');
    foreach ($rows as $row) {
        $tags = $this->exts->getElements('td', $row);
        if(count($tags) >= 9 && $this->exts->getElement('a[href*="/wp-admin/post.php?post="][href*="&action=edit"]', $tags[0]) != null) {
            $invoiceUrl = $this->exts->getElement('a[href*="/wp-admin/post.php?post="][href*="&action=edit"]', $tags[0])->getAttribute("href");
            $invoiceName = '';
            $invoiceDate = trim($tags[2]->getAttribute('innerText'));
            $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[6]->getAttribute('innerText'))) . ' EUR';
            
            array_push($invoices, array(
                'invoiceName'=>$invoiceName,
                'invoiceDate'=>$invoiceDate,
                'invoiceAmount'=>$invoiceAmount,
                'invoiceUrl'=>$invoiceUrl
            ));
            $this->isNoInvoice = false;
        } else if(count($tags) >= 7 && $this->exts->getElement('a[href*="/wp-admin/post.php?post="][href*="&action=edit"]', $tags[0]) != null) {
            $invoiceUrl = $this->exts->getElement('a[href*="/wp-admin/post.php?post="][href*="&action=edit"]', $tags[0])->getAttribute("href");
            $invoiceName = '';
            $invoiceDate = trim($tags[1]->getAttribute('innerText'));
            $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[5]->getAttribute('innerText'))) . ' EUR';
            
            array_push($invoices, array(
                'invoiceName'=>$invoiceName,
                'invoiceDate'=>$invoiceDate,
                'invoiceAmount'=>$invoiceAmount,
                'invoiceUrl'=>$invoiceUrl
            ));
            $this->isNoInvoice = false;
        } else if($this->exts->getElement('a[href*="/wp-admin/post.php?post="][href*="&action=edit"]', $row) != null) {
            $invoiceUrl = $this->exts->getElement('a[href*="/wp-admin/post.php?post="][href*="&action=edit"]', $row)->getAttribute("href");
            $invoiceName = '';
            $invoiceDate = '';
            $invoiceAmount = '';
            
            array_push($invoices, array(
                'invoiceName'=>$invoiceName,
                'invoiceDate'=>$invoiceDate,
                'invoiceAmount'=>$invoiceAmount,
                'invoiceUrl'=>$invoiceUrl
            ));
            $this->isNoInvoice = false;
        }
    }
    
    // Download all invoices
    $this->exts->log('Invoices found: '.count($invoices));
    $newTab = $this->exts->openNewTab();
    foreach ($invoices as $invoice) {
        $this->exts->log('--------------------------');
        $this->exts->log('invoiceName: '.$invoice['invoiceName']);
        $this->exts->log('invoiceDate: '.$invoice['invoiceDate']);
        $this->exts->log('invoiceAmount: '.$invoice['invoiceAmount']);
        $this->exts->log('invoiceUrl: '.$invoice['invoiceUrl']);
        
        $invoiceFileName = '';
        $invoice['invoiceDate'] = (!empty($invoice['invoiceDate']) && trim($invoice['invoiceDate']) != '') ? $this->exts->parse_date($invoice['invoiceDate']) : '';
        $this->exts->log('Date parsed: '.$invoice['invoiceDate']);
        
        $this->exts->openUrl($invoice['invoiceUrl']);
        sleep(10);

        $downloadUrl = '';
        if($this->exts->exists('.postbox a[href*="wp-admin/admin-ajax.php?action=generate_wpo_wcpdf&document_type=invoice&"]')){
            $downloadUrl = $this->exts->getElement('.postbox a[href*="wp-admin/admin-ajax.php?action=generate_wpo_wcpdf&document_type=invoice&"]')->getAttribute("href");
        } else if($this->exts->exists('a.document-download[href*="document"]')){
            $downloadUrl = $this->exts->getElement('a.document-download[href*="document"]')->getAttribute("href");
        }
        $this->exts->log('Download URL - '. $downloadUrl);
        
        if(!empty($downloadUrl)) {
            $downloaded_file = $this->exts->direct_download($downloadUrl, 'pdf', $invoiceFileName);
            if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $downloaded_file);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
            }
        } else {
            $this->exts->log(__FUNCTION__.'::Empty Download URL download '.$invoiceFileName);
        }
    }
    $this->exts->closeTab($newTab);
    
    if($this->restrictPages == 0 && $this->exts->exists('.top .tablenav-pages .pagination-links a.next-page.button') && $page_count < 100)  {
        //This is needed because opening take lots of time.
        $this->exts->update_process_lock();
        $this->exts->moveToElementAndClick('.top .tablenav-pages .pagination-links a.next-page.button');
        sleep(15);
        $page_count++;
        $this->processDetialPageInvoices($page_count);
    } else if($this->exts->exists('.top .tablenav-pages .pagination-links a.next-page.button') && $page_count < 15)  {
        //This is needed because opening take lots of time.
        $this->exts->update_process_lock();
        $this->exts->moveToElementAndClick('.top .tablenav-pages .pagination-links a.next-page.button');
        sleep(15);
        $page_count++;
        $this->processDetialPageInvoices($page_count);
    }
}

private function processRefundInvoices($page_count = 0) {
    sleep(25);
    
    $this->exts->capture("4-invoices-page-refund");
    $invoices = [];

    $rows = $this->exts->getElements('table.woocommerce_page_wgm-refunds > tbody > tr');
    foreach ($rows as $row) {
        $tags = $this->exts->getElements('td', $row);
        if(count($tags) >= 6 && $this->exts->getElement('a.invoice_pdf[href*="invoice_pdf"]', $tags[5]) != null) {
            $invoiceUrl = $this->exts->getElement('a.invoice_pdf[href*="invoice_pdf"]', $tags[5])->getAttribute("href");
            $invoiceName = trim($tags[0]->getAttribute('innerText'));
            $invoiceDate = trim($tags[3]->getAttribute('innerText'));
            $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[4]->getAttribute('innerText'))) . ' EUR';

            array_push($invoices, array(
                'invoiceName'=>$invoiceName,
                'invoiceDate'=>$invoiceDate,
                'invoiceAmount'=>$invoiceAmount,
                'invoiceUrl'=>$invoiceUrl
            ));
            $this->isNoInvoice = false;
        }
    }

    // Download all invoices
    $this->exts->log('Invoices found: '.count($invoices));
    foreach ($invoices as $invoice) {
        $this->exts->log('--------------------------');
        $this->exts->log('invoiceName: '.$invoice['invoiceName']);
        $this->exts->log('invoiceDate: '.$invoice['invoiceDate']);
        $this->exts->log('invoiceAmount: '.$invoice['invoiceAmount']);
        $this->exts->log('invoiceUrl: '.$invoice['invoiceUrl']);

        $invoiceFileName = $invoice['invoiceName'].'.pdf';
        $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'j. F Y H:i','Y-m-d');
        $this->exts->log('Date parsed: '.$invoice['invoiceDate']);
        
        
        $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
        if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
            $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $downloaded_file);
            sleep(1);
        } else {
            $this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
        }
    }
    if($this->restrictPages == 0 && $this->exts->exists('.top .tablenav-pages .pagination-links a.next-page.button') && $page_count < 50)  {
        //This is needed because opening take lots of time.
        $this->exts->update_process_lock();
        $this->exts->moveToElementAndClick('.top .tablenav-pages .pagination-links a.next-page.button');
        $page_count++;
        $this->processRefundInvoices($page_count);
    }
}