<?php
// Server-Portal-ID: 9042 - Last modified: 22.01.2025 13:38:13 UTC - User: 1

public $baseUrl = 'https://www.bouyguestelecom.fr/forfaits-mobiles/sans-engagement';
public $loginUrl = 'https://www.bouyguestelecom.fr/forfaits-mobiles/sans-engagement';
public $invoicePageUrl = '';

public $username_selector = 'form[data-roles="inputForm"] input[name="username"]';
public $password_selector = 'form[data-roles="inputForm"] input[type="password"]';
public $remember_me_selector = 'form[data-roles="inputForm"] input[type="checkbox"], input[name="rememberMe"]';
public $submit_login_selector = 'form[data-roles="inputForm"] button[type="submit"]';

public $check_login_failed_selector = 'p.is-danger';
public $check_login_success_selector = '';

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

        $this->exts->waitTillPresent('button[id="popin_tc_privacy_button_3"]', 20);
        if ($this->exts->exists('button[id="popin_tc_privacy_button_3"]')) {
            $this->exts->click_element('button[id="popin_tc_privacy_button_3"]');
           
        }
        sleep(5);
        $this->exts->click_by_xdotool('a[id="headerUser"]');
        $this->fillForm(0);

        $this->exts->waitTillPresent('input[name="choice"]', 20);
        if ($this->exts->exists('input[name="choice"]')) {
            $choices = $this->exts->querySelectorAll('input[name="choice"]');
            // User can have two choices to select. SMS And Email. Select the first one.
            $this->exts->click_element($choices[0]);
            sleep(1);
            $this->exts->click_by_xdotool('div[data-picasso-front="OtpMethod"] button[type="submit"]');
        }

        $this->exts->waitTillPresent($this->username_selector, 10);
        $this->fillForm(0);

        $this->checkFillTwoFactor();
    }

    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");
        $this->exts->moveToElementAndClick('.is-active button.modal-close');
        sleep(5);
        
        // Open invoices url
        // $this->exts->openUrl($this->invoicePageUrl);
        $this->exts->moveToElementAndClick('.navbar-menu #user');
        sleep(5);
        $this->exts->moveToElementAndClick('a[href="/mon-compte/mes-factures"]');
        sleep(5);
        $this->processInvoices();
        // Final, check no invoice
        if ($this->isNoInvoice) {
            $this->exts->no_invoice();
        }
        $this->exts->success();
    } else {
        if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'passe') !== false) {
            $this->exts->log("Wrong credential !!!!");
            $this->exts->loginFailure(1);
        } else if ($this->exts->exists($this->username_selector)) {
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

            sleep(2);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            if ($this->exts->exists($this->remember_me_selector)) {
                $this->exts->click_by_xdotool($this->remember_me_selector);
                sleep(1);
            }
            $this->exts->capture("1-login-page-filled");
            sleep(5);
            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->click_by_xdotool($this->submit_login_selector);
            }
        }
    } catch (\Exception $exception) {

        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}

private function checkFillTwoFactor()
{
    $this->exts->waitTillPresent('input[name="choice"]', 20);
    if ($this->exts->exists('input[name="choice"]')) {
        $choices = $this->exts->querySelectorAll('input[name="choice"]');
        // User can have two choices to select. SMS And Email. Select the first one.
        $this->exts->click_element($choices[0]);
        sleep(1);
        $this->exts->click_by_xdotool('div[data-picasso-front="OtpMethod"] button[type="submit"]');
    }

    $two_factor_selector = 'input.otp';
    $two_factor_message_selector = 'p.is-loaded.is-level-1';
    $two_factor_submit_selector = '';
    $this->exts->waitTillPresent($two_factor_selector, 10);
    if ($this->exts->querySelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");
        if ($this->exts->querySelectorAll($two_factor_message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = "";
            for ($i = 0; $i < count($this->exts->querySelectorAll($two_factor_message_selector)); $i++) {
                $infoMessage = $this->exts->querySelectorAll($two_factor_message_selector)[$i]->getAttribute('innerText');
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $infoMessage . "\n";
            }
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
            $resultCodes = str_split($two_factor_code);
            $code_inputs = $this->exts->querySelectorAll($two_factor_selector);
            foreach ($code_inputs as $key => $code_input) {
                if (array_key_exists($key, $resultCodes)) {
                    $this->exts->log('"checkFillTwoFactor: Entering key ' . $resultCodes[$key] . 'to input #');
                    $this->exts->moveToElementAndType('input.otp:nth-child(' . ($key + 1) . ')', $resultCodes[$key]);
                    // $code_input->sendKeys($resultCodes[$key]);
                } else {
                    $this->exts->log('"checkFillTwoFactor: Have no char for input #');
                }
            }

            // $this->exts->log("checkFillTwoFactor: Clicking submit button.");
            // sleep(3);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

            // $this->exts->click_by_xdotool($two_factor_submit_selector);
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
    return $isLoggedIn;
}

private function processInvoices($count=1) {
    sleep(15);
    if($this->exts->exists('button#popin_tc_privacy_button_3')){
        $this->exts->moveToElementAndClick('button#popin_tc_privacy_button_3');
        sleep(5);
    }
    if($this->exts->getElement('tbody.factureLine tr.factureSection div.action-download, table.is-tri tbody tr a.text.link') != null) {
        $this->exts->log('Invoices found');
        $this->exts->capture("4-page-opened");
        $invoices = [];
        
        if($this->exts->exists('table.is-tri tbody tr a.text.link')) {
            $rows = $this->exts->getElements('table.is-tri tbody tr');
            foreach ($rows as $row) {
                $tags = $row->getElements('td');
                if(count($tags) >= 5 && $this->exts->getElement('a.text.link', $tags[5]) != null) {
                    $downloadLinks = $this->exts->getElements('a.text.link', $tags[5]);
                    $downloadBtn = $downloadLinks[0];
                    
                    $invoiceName = '';
                    $invoiceDate = trim($tags[0]->getText());
                    
                    $invoiceAmount = preg_replace("/[\s\n]/", '', $tags[3]->getText());
                    $invoiceAmount = urldecode(str_replace('%E2%82%AC', '.', urlencode($invoiceAmount)));
                    $invoiceAmount = preg_replace('/[^\d\.\,]/', '', $invoiceAmount) . ' EUR';
                    
                    $invoiceFileName = '';
                    
                    $this->exts->log('date before parse: '.$invoiceDate);
                    
                    $invoiceDate = $this->exts->parse_date($invoiceDate, 'd/m/Y','Y-m-d');
                    $this->exts->log('invoiceName: '.$invoiceName);
                    $this->exts->log('invoiceDate: '.$invoiceDate);
                    $this->exts->log('invoiceAmount: '.$invoiceAmount);
                    
                    try {
                        $downloadBtn->click();
                    } catch (\Exception $exception) {
                        $this->exts->execute_javascript('arguments[0].click();', [$downloadBtn]);
                    }
                    sleep(10);
                    if($this->exts->getElement('.modal.is-active input[value="pdf"]') != null) {
                        $this->exts->moveToElementAndClick('.modal.is-active input[value="pdf"]');
                        sleep(2);
                        $this->exts->moveToElementAndClick('.modal.is-active button.is-primary');
                        sleep(5);
                    }
                    $this->exts->wait_and_check_download('pdf');
                    
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                    if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
                        $invoiceName = basename($downloaded_file, '.pdf');
                        $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $downloaded_file);
                        sleep(5);
                    } else {
                        $this->exts->log('Timeout when download '.$invoiceFileName);
                    }
                    $this->isNoInvoice = false;
                }
            }
        } else {
            $rows = $this->exts->getElements('tbody.factureLine tr.factureSection');
            foreach ($rows as $row) {
                $tags = $row->getElements('td[data-key="date"], td[data-key="price"], td.table-item-overlay');
                if(count($tags) < 3){
                    continue;
                }
                $as = $tags[2]->getElements('div.action-download a[data-ui="download"]');
                if(count($as) == 0){
                    continue;
                }
                
                $invoiceSelector = 'tbody.factureLine tr.factureSection[data-spurid="'.$row->getAttribute("data-spurid").'"] td.table-item-overlay .action-download a[data-ui="download"]';
                $invoiceName = trim(str_replace('/', '', $tags[0]->getAttribute('innerText')));
                $invoiceDate = trim($tags[0]->getAttribute('innerText'));
                
                $invoiceAmount = preg_replace("/[\s\n]/", '', $tags[1]->getAttribute('innerText'));
                $invoiceAmount = urldecode(str_replace('%E2%82%AC', '.', urlencode($invoiceAmount)));
                $invoiceAmount = preg_replace('/[^\d\.\,]/', '', $invoiceAmount) . ' EUR';
                
                array_push($invoices, array(
                    'invoiceName'=>$invoiceName,
                    'invoiceDate'=>$invoiceDate,
                    'invoiceAmount'=>$invoiceAmount,
                    'invoiceSelector'=>$invoiceSelector
                ));
                $this->isNoInvoice = false;
            }
            
            // Download all invoices
            $this->exts->log('Invoices: '.count($invoices));
            
            foreach ($invoices as $invoice) {
                $invoiceFileName = $invoice['invoiceName'].'.pdf';
                
                $this->exts->log('date before parse: '.$invoice['invoiceDate']);
                
                $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd/m/Y','Y-m-d');
                $this->exts->log('invoiceName: '.$invoice['invoiceName']);
                $this->exts->log('invoiceDate: '.$invoice['invoiceDate']);
                $this->exts->log('invoiceAmount: '.$invoice['invoiceAmount']);
                $this->exts->log('invoiceSelector: '.$invoice['invoiceSelector']);
                
                
                $this->exts->moveToElementAndClick($invoice['invoiceSelector']);
                // Wait for completion of file download
                $this->exts->wait_and_check_download('pdf');
                // find new saved file and return its path
                $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
                    $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                    sleep(1);
                } else {
                    $this->exts->log('Timeout when download '.$invoiceFileName);
                }
            }
        }
        
    } else {
        if($count < 5){
            $count = $count + 1;
            $this->processInvoices($count);
        } else {
            $this->exts->log('Timeout processInvoices');
            $this->exts->capture('4-no-invoices');
            $this->exts->no_invoice();
        }
    }
}