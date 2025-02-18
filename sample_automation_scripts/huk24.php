<?php
// Server-Portal-ID: 8674 - Last modified: 05.02.2025 07:02:00 UTC - User: 1

public $loginUrl = 'https://www.huk24.de/login.do';
public $invoicePageUrl = 'https://www.huk24.de/meine-huk24/startseite/?contractview=1';
public $username_selector = 'input[name="username"], form#formZentralDataLogin input#TXT_B_KENNUNG ,#username-input';
public $password_selector = 'input[name="password"], form#formZentralDataLogin input#TXT_PIN, #password-input';
public $submit_login_selector = 'button[type="submit"], form#formZentralDataLogin a[name="weiter"] , button[title="Weiter"]:not([disabled])';

public $check_login_failed_selector = 'form#formZentralDataLogin .advice.error, .error__message, .message__message';
public $check_login_success_selector = 'huk-login-block .logout-button__header-desktop , .login-block__authenticated, button[id="user-menu-logged-in"]';

public $restrictPages = 3;
public $isNoInvoice = true;
/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count)
{

    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->loadCookiesFromFile();
    $this->exts->openUrl($this->loginUrl);
    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        $this->exts->waitTillPresent('.cookie-consent__button--primary');
        if ($this->exts->exists('.cookie-consent__button--primary')) {
            $this->exts->click_by_xdotool('.cookie-consent__button--primary');
            sleep(5);
        }
        if ($this->exts->exists('button[class="frc-button"]')) {
            $this->exts->click_by_xdotool('button[class="frc-button"]');
            sleep(5);
        }

        // If Click Captcha is failed
        $this->processWaitCaptcha();
        
        if ($this->exts->exists('.frc-container frc-success')) {
            $this->exts->log("I am a human being");
        }
        
        $this->fillForm(0);
        $this->checkFillTwoFactor();
    }

    
    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");
        sleep(2);
        $this->exts->openUrl($this->invoicePageUrl);
        $this->processInvoices();
        // Final, check no invoice
        if ($this->isNoInvoice) {
            $this->exts->no_invoice();
        }
        $this->exts->success();
    } else {
        if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'die anmeldedaten') !== false) {
            $this->exts->log("Wrong credential !!!!");
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
    $this->exts->waitTillPresent($this->username_selector, 5);

    try {
        if ($this->exts->querySelector($this->username_selector) != null) {

            $this->exts->capture("1-pre-login");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(5);

            $this->processWaitCaptcha();

            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->click_by_xdotool($this->submit_login_selector);
                sleep(5);
            }

            $this->exts->waitTillPresent($this->password_selector, 20);
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);
            
            $this->processWaitCaptcha();

            $this->exts->capture("1-login-page-filled");
            $this->exts->waitTillPresent($this->submit_login_selector, 200);
            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->click_by_xdotool($this->submit_login_selector);
            }
        }
    } catch (\Exception $exception) {

        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}

private function processWaitCaptcha()  {
    $this->exts->waitTillPresent('button[class="frc-button"]', 10);
    for($i=0;$i<5;$i++){
        if($this->exts->exists('.frc-success')){
            break;
        }
        if ($this->exts->exists('button[class="frc-button"]')) {
            $this->exts->click_by_xdotool('button[class="frc-button"]');
            $this->exts->waitTillPresent('.frc-success', 200);
        }
       
    }
}
    private function checkFillTwoFactor()
    {
        $two_factor_selector = 'input#pin-input';
        $two_factor_message_selector = '.auth-header ~ * div form p.text--default';
        $two_factor_submit_selector = '.auth-header ~ * div form button.button__button';

        $this->exts->waitTillPresent($two_factor_selector, 10);
        if ($this->exts->querySelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");
            if ($this->exts->getElement($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = $this->exts->extract($two_factor_message_selector);
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
                sleep(2);
                $this->exts->type_text_by_xdotool($two_factor_code);
    
                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(3);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);
    
    
                $this->exts->click_by_xdotool($two_factor_submit_selector);
                $this->exts->waitTillPresent($two_factor_selector);
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


    /**

    * Method to Check where user is logged in or not

    * return boolean true/false

    */
function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        $this->exts->waitTillPresent($this->check_login_success_selector, 20);
        if ($this->exts->exists($this->check_login_success_selector)) {

            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

            $isLoggedIn = true;
        }
    } catch (Exception $exception) {

        $this->exts->log("Exception checking loggedin " . $exception);
    }



    if ($isLoggedIn) {

        if (!empty($this->exts->config_array['allow_login_success_request'])) {

            $this->exts->triggerLoginSuccess();
        }
    }

    return $isLoggedIn;
}




private function processInvoices() {
    $this->exts->waitTillPresent('div[class="contractitem__inner"]');
    $this->exts->capture("4-invoices-page");
    $invoices = [];
    $urls = [];
    $contractRows = $this->exts->queryElementAll('div[class="contractitem__inner"]');

    
    if(count($contractRows)){
        foreach ($contractRows as $cRow) {
            $contractPageUrl = $this->exts->querySelector('a', $cRow)->getAttribute("href");
            $urls[] = $contractPageUrl;

        }
        foreach ($urls as $url) {
            $invoices = [];
            $this->exts->openUrl($url);
            $this->exts->waitTillPresent('button[data-label="Dokumente"]');
            if($this->exts->querySelector('button[data-label="Dokumente"]') != null){
                $this->exts->click_element('button[data-label="Dokumente"]');
                
                $this->exts->waitTillPresent('huk-select[name="documents"]');
                if($this->exts->exists('huk-select[name="documents"]')){ 
                    $this->exts->click_element('huk-select[name="documents"]');
                    sleep(2);
                    
                    $this->exts->click_element("//div[@class='select__option__label' and text()='Rechnung']");
                }
                
                $this->exts->waitTillPresent('huk-content-block[data-testid="belege"]  div[class="content-block__body"] > div button');
                if($this->exts->exists('huk-content-block[data-testid="belege"]  div[class="content-block__body"] > div button')){
                    // Keep clicking more but maximum upto 10 times
                    $maxAttempts = 1;
                    $attempt = 0;

                    while ($attempt < $maxAttempts && $this->exts->exists('span[class="divider__clickarea"]')) {
                        $this->exts->execute_javascript('
                            var btn = document.querySelector("span[class=\'divider__clickarea\']");
                            if(btn){
                                btn.click();
                            }
                        ');
                        $attempt++;
                        sleep(5);
                    }
                    $rows = $this->exts->querySelectorAll('huk-content-block[data-testid="belege"]  div[class="content-block__body"] > div');
                    foreach ($rows as $row) {
                        $invoiceDate = trim($this->exts->extract(' .text--size-2:nth-child(2)', $row));
                        $invoiceName = '';
                        $invoiceAmount= '';
                        $invoiceUrl = '';
                        $downloadBtn = $this->exts->querySelector('button', $row);
                        array_push($invoices, array(
                            'invoiceName'=>$invoiceName,
                            'invoiceDate'=>$invoiceDate,
                            'invoiceAmount'=>$invoiceAmount,
                            'invoiceUrl'=>$invoiceUrl,
                            'downloadBtn' => $downloadBtn
                        ));
                        $this->isNoInvoice = false;
                    }

                    // Download all invoices
                    $this->exts->log('Invoices found: ' . count($invoices));
                    foreach ($invoices as $invoice) {
                        $this->exts->log('--------------------------');
                        $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
                        $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
                        $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);
                
                        $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'F j, Y"', 'Y-m-d');
                        $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);
                
                        $this->exts->execute_javascript("arguments[0].click();", [$invoice['downloadBtn']]);
                        $this->exts->wait_and_check_download('pdf');
                        $downloaded_file = $this->exts->find_saved_file('pdf');
                        $invoiceFileName = basename($downloaded_file);
                
                        $invoice['invoiceName'] = substr($invoiceFileName, 0, strrpos($invoiceFileName, '.'));
                
                        $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
                
                
                        if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                            $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                            sleep(1);
                        } else {
                            $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                        }
                    }
                }
            }

        }
    }
}