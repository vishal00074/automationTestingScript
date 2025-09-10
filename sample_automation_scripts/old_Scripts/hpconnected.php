<?php
// Server-Portal-ID: 115307 - Last modified: 28.01.2025 06:48:23 UTC - User: 1

public $baseUrl = "https://instantink.hpconnected.com";
public $loginUrl = "https://instantink.hpconnected.com/users/signin";
public $username_selector = 'input#username';
public $password_selector = 'input#password';
public $submit_button_selector = '#next_button, [type=submit]';

public $restrictPages = 3;
public $totalFiles = 0;
public $isNoInvoice = true;

/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count) {
    $this->exts->log('Begin initPortal '.$count);
    $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
    $this->exts->loadCookiesFromFile();
    sleep(1);

    //Check session is expired or not
    $this->exts->openUrl('https://instantink.hpconnected.com/api/internal/critical_scopes');
    sleep(10);
    $this->exts->capture("0-check-session-expired");
    if (stripos($this->exts->extract('body pre'), '{"error":{"code":"session_expired"}}') !== false) {
        $this->clearChrome();
        sleep(1);
    }
    
    $this->exts->openUrl($this->baseUrl);
    sleep(20);
    $this->exts->capture("Home-page-with-cookie");
    
    if($this->exts->exists('button#onetrust-button-group #onetrust-accept-btn-handler')) {
        $this->exts->moveToElementAndClick('button#onetrust-button-group #onetrust-accept-btn-handler');
        sleep(10);
    }
    if ($this->exts->exists('button[data-testid="clientos-react-tenant-selector-mfe__continue_button"]')) {
        $this->exts->moveToElementAndClick('button[data-testid="clientos-react-tenant-selector-mfe__continue_button"]');
        sleep(30);
    }
    $isCookieLoginSuccess = false;
    if($this->checkLogin()) {
        $isCookieLoginSuccess = true;
    } else {
        if($this->exts->exists('button[data-testid="sign-in-button"]')){
            $this->exts->moveToElementAndClick('button[data-testid="sign-in-button"]');
        } else {
            $this->exts->openUrl($this->loginUrl);
        }
    }

    if(!$isCookieLoginSuccess) {
        sleep(15);
        $this->fillForm();
        sleep(30);	

        if ($this->exts->exists('button[data-testid="clientos-react-tenant-selector-mfe__continue_button"]')) {
            $this->exts->moveToElementAndClick('button[data-testid="clientos-react-tenant-selector-mfe__continue_button"]');
            sleep(30);
        }

        if($this->exts->exists('#onetrust-accept-btn-handler')) {
            $this->exts->moveToElementAndClick('#onetrust-accept-btn-handler');
            sleep(30);
        }
        if($this->exts->exists('#full-screen-consent-form-footer-button-continue')) {
            $this->exts->moveToElementAndClick('#full-screen-consent-form-footer-button-continue');
            sleep(10);
        }
        
        if ($this->exts->exists('button[name="send-email"]')) {
            $this->exts->moveToElementAndClick('button[name="send-email"]');
            sleep(13);
        } else if ($this->exts->exists('button[name="send-phone"]')) {
            $this->exts->moveToElementAndClick('button[name="send-phone"]');
            sleep(13);
        }
        $this->checkFillTwoFactor();

        if($this->exts->exists('.onboarding-component button#full-screen-error-button')){
            $this->exts->capture("internal-session-error");
            $this->exts->refresh();
            sleep(10);
            $this->exts->refresh();
            sleep(10);
        }
        if($this->exts->exists('#full-screen-consent-form-footer-button-continue')) {
            $this->exts->moveToElementAndClick('#full-screen-consent-form-footer-button-continue');
            sleep(10);
        }
        if($this->exts->exists('#root[style*="display: block"] [role="progressbar"]') && $this->exts->urlContains('/org-selector')) {
            // Huy added this 07-2022
            $this->exts->openUrl($this->baseUrl);
            sleep(5);
        }
        sleep(10);
        if($this->exts->exists('[aria-describedby="org-selector-modal-desc"] #org-selector-modal-desc label')){
            $this->exts->moveToElementAndClick('[aria-describedby="org-selector-modal-desc"] #org-selector-modal-desc label');
            sleep(5);
            $this->exts->moveToElementAndClick('[aria-describedby="org-selector-modal-desc"] button[type="button"]');
            sleep(15);
        }

        if($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->invoicePage();
        } else {
            if (strpos(strtolower($this->exts->extract('.caption.text')), 'invalid username or password') !== false ||
                strpos(strtolower($this->exts->extract('.caption.text')), 'ltiger benutzername oder') !== false) {
                $this->exts->loginFailure(1);
            } else if($this->exts->exists('#username-helper-text a.error-link')){
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }	
        }
    } else {
        sleep(10);
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");
        $this->invoicePage();
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
function fillForm(){
    $this->exts->capture("1-pre-login");
    if($this->exts->exists($this->username_selector)){
        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(2);
        $this->exts->moveToElementAndClick('input#RememberMe, .remember-me label');
        $this->exts->capture("1-username-filled");
        $this->exts->moveToElementAndClick($this->submit_button_selector);
        sleep(6);
        $this->exts->capture("1-username-submitted");
        $this->exts->capture("1-password-page");
    } else {
        $this->exts->log(__FUNCTION__.'::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }

    if($this->exts->exists($this->password_selector)){
        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(2);
        $this->exts->capture("1-password-filled");
        $this->exts->moveToElementAndClick($this->submit_button_selector);
        sleep(6);
    }
}
function checkFillTwoFactor() {
    $two_factor_selector = 'input[name="code"], input#code';
    $two_factor_message_selector = 'div.email-header p, div.sms-header p, p';
    $two_factor_submit_selector = 'button#submit-code , button#submit-auth-code';

    if($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3){
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        if($this->exts->getElement($two_factor_message_selector) != null){
            $this->exts->two_factor_notif_msg_en = "";
            for ($i=0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) { 
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en.$this->exts->getElements($two_factor_message_selector)[$i]->getAttribute('innerText')."\n";
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
            $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
            
            $this->exts->log("checkFillTwoFactor: Clicking submit button.");
            sleep(3);
            $this->exts->capture("2.2-two-factor-filled-".$this->exts->two_factor_attempts);

            $this->exts->moveToElementAndClick($two_factor_submit_selector);
            sleep(15);

            if($this->exts->getElement($two_factor_selector) == null){
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
function checkLogin() {
    return $this->exts->exists('#desktop-header a[href="/users/logout"], [data-testid="sign-out-button"], [data-value="Sign out"], [data-value="Abmelden"], [data-testid="avatar-container"] [aria-haspopup="true"], #menu-avatar-container, div[data-testid*="avatar_menu"]');
}

function invoicePage() {
    $this->exts->log("Invoice page");
    $this->exts->refresh();
    sleep(30);
    if($this->exts->exists('#onetrust-accept-btn-handler')) {
        $this->exts->moveToElementAndClick('#onetrust-accept-btn-handler');
        sleep(2);
    }
    if($this->exts->exists('[data-testid="special-savings-modal"] div.vn-modal--content button')) {
        $this->exts->moveToElementAndClick('[data-testid="special-savings-modal"] div.vn-modal--content button');
        sleep(5);
    }
    if($this->exts->exists('div[aria-describedby="paper-new-plan-offer-main-div-desc"] button.vn-modal__close')) {
        $this->exts->moveToElementAndClick('div[aria-describedby="paper-new-plan-offer-main-div-desc"] button.vn-modal__close');
        sleep(5);
    }

    $this->exts->moveToElementAndClick('li[data-testid="print-plans-menu"]');
    sleep(5);

    $this->exts->moveToElementAndClick('[data-testid="plan-overview-submenu"]');
    sleep(25);
    
    $this->exts->waitTillPresent('#status-card, [data-testid="status-card"]', 30);
    
    
    $this->exts->moveToElementAndClick('[data-testid="printer-selector"]');
    sleep(2);
    $this->exts->capture('printers-checking');
    $printers = count($this->exts->getElements('[data-testid="printer-selector"] [class^="selector__selector-options"] [class^="selector__option-label-container-padding"]'));
    $this->exts->log('Number of Print: '.$printers);
    if($printers > 1){
        for ($p=0; $p < $printers; $p++) {
            if(!$this->exts->exists('[data-testid="printer-selector"] [class^="selector__selector-options"] [class^="selector__option-label-container-padding"]')){
                $this->exts->moveToElementAndClick('[data-testid="printer-selector"]');
                sleep(2);
            }

            $target_printer = $this->exts->getElements('[data-testid="printer-selector"] [class^="selector__selector-options"] [class^="selector__option-label-container-padding"]')[$p];
            try{
                $this->exts->log('Select target_printer');
                $target_printer->click();
            } catch(\Exception $exception){
                $this->exts->log('Select target_printer by javascript');
                $this->exts->execute_javascript("arguments[0].click()", [$target_printer]);
            }
            sleep(5);
            $this->exts->waitTillPresent('[data-testid="status-card"], #print-history-page', 30);
            $this->exts->moveToElementAndClick('.print-history-link a[href*="/print_history"], [data-testid="status-card"] a[data-testid="view-print-history"]');
            sleep(5);
            
            $this->downloadInvoice();

            $this->exts->moveToElementAndClick('div[data-testid="print-history-section"] #history-table-section');
            sleep(20);

            $this->processPaymentHistory();
        }
    } else {
        $this->exts->moveToElementAndClick('.print-history-link a[href*="/print_history"], [data-testid="status-card"] a[data-testid="view-print-history"]');
        sleep(5);
        $this->downloadInvoice();

        $this->exts->moveToElementAndClick('div[data-testid="print-history-section"] #history-table-section');
        sleep(20);

        $this->processPaymentHistory();
    }
    //page has changed 23.12.2022
    // changed other organisation
    $this->exts->moveToElementAndClick('div#menu-avatar-container');
    sleep(8);
    $organisations = $this->exts->getElements('div[data-testid="menu-modal"] button [aria-label="Chevron Right"]');
    if(count($organisations) > 0) {
        for($i=0; $i<count($organisations); $i++) {
            try {
                $organisations[$i]->click();
            } catch (\Exception $exception) {
                $this->exts->execute_javascript('arguments[0].click();', [$organisations[$i]]);
            }
            sleep(35);
            if($this->exts->exists('[data-testid="special-savings-modal"] div.vn-modal--content button')) {
                $this->exts->moveToElementAndClick('[data-testid="special-savings-modal"] div.vn-modal--content button');
                sleep(5);
            }
            $this->exts->moveToElementAndClick('li[data-testid="print-plans-menu"]');
            sleep(5);

            $this->exts->moveToElementAndClick('[data-testid="plan-overview-submenu"]');
            sleep(25);
            
            $this->exts->waitTillPresent('#status-card, [data-testid="status-card"], [data-testid="printer-selector"]', 30);
    
    
            $this->exts->moveToElementAndClick('[data-testid="printer-selector"]');
            sleep(2);
            $this->exts->capture('printers-checking');
            $printers = count($this->exts->getElements('[data-testid="printer-selector"] [class^="selector__selector-options"] [class^="selector__option-label-container-padding"]'));
            $this->exts->log('Number of Print: '.$printers);
            if($printers > 1){
                for ($p=0; $p < $printers; $p++) {
                    if(!$this->exts->exists('[data-testid="printer-selector"] [class^="selector__selector-options"] [class^="selector__option-label-container-padding"]')){
                        $this->exts->moveToElementAndClick('[data-testid="printer-selector"]');
                        sleep(2);
                    }

                    $target_printer = $this->exts->getElements('[data-testid="printer-selector"] [class^="selector__selector-options"] [class^="selector__option-label-container-padding"]')[$p];
                    try{
                        $this->exts->log('Select target_printer');
                        $target_printer->click();
                    } catch(\Exception $exception){
                        $this->exts->log('Select target_printer by javascript');
                        $this->exts->execute_javascript("arguments[0].click()", [$target_printer]);
                    }
                    sleep(5);
                    $this->exts->waitTillPresent('[data-testid="status-card"], #print-history-page', 30);
                    $this->exts->moveToElementAndClick('.print-history-link a[href*="/print_history"], [data-testid="status-card"] a[data-testid="view-print-history"]');
                    sleep(5);
                    
                    $this->downloadInvoice();
                    $this->exts->moveToElementAndClick('div[data-testid="print-history-section"] #history-table-section');
                    sleep(20);

                    $this->processPaymentHistory();
                }
            } else {
                $this->exts->moveToElementAndClick('.print-history-link a[href*="/print_history"], [data-testid="status-card"] a[data-testid="view-print-history"]');
                sleep(5);
                $this->downloadInvoice();
                $this->exts->moveToElementAndClick('div[data-testid="print-history-section"] #history-table-section');
                sleep(20);

                $this->processPaymentHistory();
            }
            
            $this->exts->moveToElementAndClick('div#menu-avatar-container');
            sleep(5);
        }
    }
    
    
    if($this->isNoInvoice){
        $this->exts->no_invoice();
    }
    $this->exts->success();
}
function downloadInvoice(){
    sleep(5);
    $this->exts->capture('4-account_history');
    $this->exts->moveToElementAndClick('[data-testid="select-billing-cycle-parity"] [aria-haspopup="listbox"][role="button"]');
    sleep(2);
    $this->exts->capture('4-billing-cycle');
    $cycle_dropdown_id = $this->exts->extract('[data-testid="select-billing-cycle-parity"] [aria-haspopup="listbox"][role="button"]', null, 'id');
    $bill_list_selector = '[role="listbox"]#'.$cycle_dropdown_id.'-listbox li[data-value]';
    $this->exts->log('cycle_dropdown_id ' .$cycle_dropdown_id);
    $this->exts->log('bill_list_selector ' .$bill_list_selector);

    $bill_values = $this->exts->getElementsAttribute($bill_list_selector, 'data-value');
    foreach ($bill_values as $bill_value) {
        $invoiceName = $bill_value;
        $this->isNoInvoice = false;
        if($this->exts->invoice_exists($invoiceName)){
            $this->exts->log('Invoice Existed: '.$invoiceName);
            continue;
        }
        if(!$this->exts->exists($bill_list_selector)){
            $this->exts->moveToElementAndClick('[data-testid="select-billing-cycle-parity"] [aria-haspopup="listbox"][role="button"]');
            sleep(2);
        }
        $this->exts->moveToElementAndClick('[role="listbox"]#'.$cycle_dropdown_id.'-listbox li[data-value="'.$bill_value.'"]');
        sleep(5);
        if ($this->exts->exists('[class*="printHistory__columnB"] a[data-testid="download_invoice_pdf"]')) {
            $invoiceFileName = $invoiceName . '.pdf';
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: '.$invoiceName);
            $this->exts->log('invoiceDate: ');
            $this->exts->log('invoiceAmount: ');

            $this->exts->moveToElementAndClick('[class*="printHistory__columnB"] a[data-testid="download_invoice_pdf"]');
            sleep(3);
            $this->exts->wait_and_check_download('pdf');
            $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

            if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
                $this->exts->new_invoice($invoiceName, '', '', $downloaded_file);
            } else {
                $this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
            }
            $this->totalFiles += 1;
        }
    }
}

private function processPaymentHistory($paging_count = 1) {
    $this->exts->capture("4-PaymentHistory-page");
    $invoices = [];

    $rows = $this->exts->getElements('table[data-testid*="-print-hitory-table"] tbody tr');
    foreach ($rows as $row) {
        $tags = $this->exts->getElements('td', $row);
        if(count($tags) >= 3 && $this->exts->getElement('div[class*="invoiceDownloadLink"] a', $row) != null) {
            $invoiceUrl = $this->exts->getElement('div[class*="invoiceDownloadLink"] a', $row);
            $invoiceDate = trim($tags[0]->getAttribute('innerText'));
            $parse_date = $this->exts->parse_date($invoiceDate, 'd/m/Y','Y-m-d');
            if($parse_date == ''){
                $parse_date = $this->exts->parse_date($invoiceDate, 'm/d/Y','Y-m-d');
            }
            $this->exts->log('Date parsed: '.$parse_date);
            $invoiceName = $parse_date;
            $this->exts->log('Invoice name: '.$invoiceName);
            $invoiceAmount = '';
            $invoiceFileName = $invoiceName.'.pdf';
            $this->exts->log('Invoice file name: '.$invoiceFileName);
            $this->isNoInvoice = false;

            if($this->exts->invoice_exists($invoiceName)) {
                $this->exts->log('Invoice already exists : '.$invoiceName);
            } else {
                try {
                    $invoiceUrl->click();
                } catch (\Exception $exception) {
                    $this->exts->execute_javascript('arguments[0].click();', [$invoiceUrl]);
                }
                sleep(5);
                $this->exts->wait_and_check_download('pdf');
                
                $downloaded_file = $this->exts->find_saved_file('pdf',$invoiceFileName);
                if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
                    $this->exts->new_invoice($invoiceName, $parse_date, $invoiceAmount, $invoiceFileName);
                    sleep(1);
                } else {
                    $this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
                }
                // Check if require login again
                if(stripos($this->exts->extract(".critical-scopes-modal .vn-modal--title", null, 'innerText'),'security session expired') !== false || stripos($this->exts->extract(".critical-scopes-modal .vn-modal--title", null, 'innerText'),'sitzung beendet') !== false){
                    $login_expire_button = $this->exts->getElement("//div[contains(@class, 'critical-scopes-modal')]//button[span[text()='Login'] or span[text()='Anmelden']]", null, 'xpath');
                    try {
                        $this->exts->log('Click login...');
                        $login_expire_button->click();
                    } catch (Exception $e) {
                        $this->exts->log("Click login by javascript ");
                        $this->exts->execute_javascript('arguments[0].click()', [$login_expire_button]);
                    }
                    sleep(3);
                    $this->fillForm();
                    sleep(25);
                    if($this->checkLogin()) {
                        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                        $this->exts->capture("LoginSuccess");
                        $this->invoicePage();
                    } else {
                        $mesg = strtolower($this->exts->extract('form p#password-helper-text, form p#username-helper-text', null, 'innerText'));
                        if (strpos($mesg, 'invalid username or password') !== false || strpos($mesg, 'benutzername oder ung') !== false || strpos($mesg, 'hp account not found') !== false || strpos($mesg, 'hp account nicht gefunden') !== false) {
                            $this->exts->log(__FUNCTION__.'::Use login failed confirmed'. $this->exts->getUrl());
                            $this->exts->loginFailure(1);
                        } else if (strpos(strtolower($this->exts->extract('p#username-helper-text .caption', null, 'innerText')), 'hp account not found') !== false) {
                            $this->exts->capture("loginFailedConfirmed");
                            $this->exts->loginFailure(1);

                        } else {
                            $this->exts->log(__FUNCTION__.'::Use login failed '. $this->exts->getUrl());
                            $this->exts->loginFailure();
                        }
                    }
                }
            }
        }
    }

    // Download all invoices
    $this->exts->log('Invoices found: '.count($invoices));
    
    // next page
    $restrictPages = $this->restrictPages;
    if($restrictPages == 0 &&
        $paging_count < 50 &&
        $this->exts->getElement('button.next:not([disabled])') != null
    ){
        $paging_count++;
        $this->exts->moveToElementAndClick('button.next:not([disabled])');
        sleep(5);
        $this->processPaymentHistory($paging_count);
    }
}