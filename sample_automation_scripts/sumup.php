<?php // updated login code
// Server-Portal-ID: 36580 - Last modified: 15.01.2025 16:54:51 UTC - User: 1

/*Define constants used in script*/
public $baseUrl = 'https://me.sumup.com/de-de/overview';
public $loginUrl = 'https://me.sumup.com/de-de/login';
public $invoicePageUrl = 'https://me.sumup.com/de-de/sales';

public $username_selector = 'input#username, input[name="email"], input[name="username"]';
public $password_selector = 'input#password, input[name="password"]';
public $submit_login_selector = 'button[type="submit"]';

public $check_login_failed_selector = 'form[action*="/login"]';
public $check_login_success_selector = 'span[class*="merchantInfo"],a[data-selector="SALES_OVERVIEW.REPORTS_BUTTON"] , div[class*="CompanyName"] ,a[href*="/referrals"], a[href="/de-de/account"], a[href="/de-de/settings"],a[href="/en-us/settings"], button[data-selector="EXPORT_BUTTON"], button[data-selector="CALENDAR_BUTTON"]';

public $download_payouts = 0;
public $isNoInvoice = true;
public $MAX_INVOICE_LIMIT = 1500;
public $download_monthly_payouts = 0;
public $credit_notes = 0;
public $ar_invoices = 0;
/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    // $this->fake_user_agent('Mozilla\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome/108.0.0.0 Safari\/537.36');
    $this->download_payouts = isset($this->exts->config_array["download_payouts"]) ? (int)@$this->exts->config_array["download_payouts"] : 0;
    $this->download_monthly_payouts = isset($this->exts->config_array["download_monthly_payouts"]) ? (int)@$this->exts->config_array["download_monthly_payouts"] : 0;

    $this->ar_invoices = isset($this->exts->config_array["ar_invoices"]) ? (int)$this->exts->config_array["ar_invoices"] : 0;
    $this->credit_notes = isset($this->exts->config_array["credit_notes"]) ? (int)$this->exts->config_array["credit_notes"] : 0;


    // Load cookies
    $this->exts->openUrl($this->baseUrl);
    $this->exts->loadCookiesFromFile();
    $this->exts->openUrl($this->baseUrl);

    sleep(10);
    $this->check_solve_blocked_page();
    if ($this->exts->check_exist_by_chromedevtool('button#onetrust-accept-btn-handler')) {
        $this->exts->click_by_xdotool('button#onetrust-accept-btn-handler');
        sleep(5);
    }
    $this->exts->capture_by_chromedevtool('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if (!$this->exts->check_exist_by_chromedevtool($this->check_login_success_selector)) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        //bypass browser rejected
        $this->exts->getUrl($this->baseUrl);
        sleep(10);
        $this->check_solve_blocked_page();

        $this->checkFillLogin();
        sleep(10);
        $this->check_solve_blocked_page();
        sleep(5);
        if ($this->exts->exists('h1[class*="cui-headline-sagu"]') && stripos($this->exts->extract('h1[class*="cui-headline-sagu"]'), 'Passkey or security key') !== false) {
            $this->exts->type_key_by_xdotool('Escape');
            sleep(2);
            $this->exts->moveToElementAndClick('a[href="/flows/authentication"] > span[class*="content"]');
            sleep(2);
            $this->exts->moveToElementAndClick('ul[class="cui-listitemgroup-items-rktu"] a[href="/flows/authentication/totp"], ul[class="cui-listitemgroup-items-rktu"] li:nth-child(1)');
            sleep(2);
        }

        $this->checkFillTwoFactor();
    }
    sleep(5);
    if($this->exts->exists('button[class*="cui-button-ylou cui-button-secondary"]')){
         $this->exts->moveToElementAndClick('button[class*="cui-button-ylou cui-button-secondary"]');
    }
    sleep(5);

    if ($this->exts->exists('div[class*="styles_buttonGroup"] > div[class*="cui-buttongroup-axeq cui-buttongroup-right-pmp3"] > button[class*="cui-button-ylou cui-button-secondary"]')) {
             $this->exts->log('Click on Update Later Button');
            $this->exts->moveToElementAndClick('div[class*="styles_buttonGroup"] > div[class*="cui-buttongroup-axeq cui-buttongroup-right-pmp3"] > button[class*="cui-button-ylou cui-button-secondary"]');
            sleep(5);
    }

   

    if ($this->exts->getElement($this->check_login_success_selector) != null) {
        $this->exts->log(__FUNCTION__ . '::User logged in');

        
        
        if ($this->exts->exists('button#onetrust-accept-btn-handler')) {
            $this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler');
            sleep(5);
        }
        $this->exts->capture("3-login-success");

        if ($this->download_monthly_payouts == 1) {
            $this->downloadPayoutReport();
        } else {
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(30);
            if ($this->exts->exists('button[data-selector="EXPORT_BUTTON"]')) {
                $this->downloadFeeReport();
                $this->exts->openUrl($this->invoicePageUrl);
                sleep(30);
            }

            $this->processInvoices();
        }


        if ($this->download_payouts == 1) {
            if ($this->exts->exists('a[data-selector="SIDENAV.NAV_ITEMS.PAYOUTS"]')) {
                $this->exts->moveToElementAndClick('a[data-selector="SIDENAV.NAV_ITEMS.PAYOUTS"]');
            } else {
                $this->exts->openUrl('https://me.sumup.com/de-de/payouts');
            }
            sleep(30);

            $this->processPayouts();
        }
        if ($this->ar_invoices == 1) {
            $this->exts->openUrl('https://invoices.sumup.com/'); // Open sub page and get sso login
            sleep(10);
            $this->exts->openUrl('https://invoices.sumup.com/invoices');
            $this->download_ar_invoice();
        }
        if ($this->credit_notes == 1) {
            $this->exts->openUrl('https://invoices.sumup.com/'); // Open sub page and get sso login
            sleep(10);
            $this->exts->openUrl('https://invoices.sumup.com/creditnotes');
            $this->download_creditnote();
        }

        // Final, check no invoice
        if ($this->isNoInvoice) {
            $this->exts->no_invoice();
        }
        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        if (!filter_var($this->username, FILTER_VALIDATE_EMAIL)) {
            $this->exts->loginFailure(1);
        }

        $isTwoFAIncorrect = $this->exts->execute_javascript('document.body.innerHTML.includes("Please enter the correct code")');
        $this->exts->log('isTwoFAIncorrect: '. $isTwoFAIncorrect);
        if (
            stripos($this->exts->extract($this->check_login_failed_selector), 'incorrect email address or password') !== false ||
            stripos($this->exts->extract($this->check_login_failed_selector), 'passwort falsch') !== false ||
            stripos($this->exts->extract($this->check_login_failed_selector), 'passe incorrect') !== false ||
            stripos($this->exts->extract('div.cui-notificationinline-danger-eeh7 p'), 'email address and/or password') !== false ||
            stripos($this->exts->extract($this->check_login_failed_selector), 'email address and/or password') !== false || $this->exts->getElement('div.cui-notificationinline-danger-eeh7 p') != null

        ) {
            $this->exts->loginFailure(1);
        }elseif($isTwoFAIncorrect){
            $this->exts->log('Please enter the correct code');
            $this->exts->loginFailure(1);
        }
         else {
            $this->exts->loginFailure();
        }
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

        $this->exts->capture("2-login-page-filled");
        $this->exts->moveToElementAndClick($this->submit_login_selector);
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}

private function checkFillTwoFactor()
    {
        $two_factor_selector = 'form > div > div input[id*="otp_code_input"]';
        $two_factor_message_selector = 'h1 + p';
        $two_factor_submit_selector = 'form button[type="submit"]';
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
 
                $resultCodes = str_split($two_factor_code);
                $code_inputs = $this->exts->querySelectorAll($two_factor_selector);
                foreach ($code_inputs as $key => $code_input) {
                    if (array_key_exists($key, $resultCodes)) 
                    {
                        sleep(1);
                        $this->exts->log('"checkFillTwoFactor: Entering key ' . $resultCodes[$key] . 'to input #');

                        $this->exts->click_by_xdotool('form > div >div:nth-child(' . ($key + 1) . ') input[id*="otp_code_input"]');
                        sleep(1);
                        $this->exts->type_text_by_xdotool($resultCodes[$key]);
                        // $this->exts->moveToElementAndType('form > div >div:nth-child(' . ($key + 1) . ') input[id*="otp_code_input"]', $resultCodes[$key]);
                       
                    } else {
                        $this->exts->log('"checkFillTwoFactor: Have no char for input #');
                    }
                }
 
                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(3);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);
 
                if ($this->exts->exists('input[type="checkbox"]')) {
                    $this->exts->click_by_xdotool('input[type="checkbox"]');
                    sleep(1);
                }
 
                $this->exts->click_by_xdotool($two_factor_submit_selector);
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

private function check_solve_blocked_page()
{
    $this->exts->capture_by_chromedevtool("blocked-page-checking");
 
    for ($i = 0; $i < 5; $i++) {
        if ($this->exts->check_exist_by_chromedevtool('div[style="display: grid;"] > div > div')) {
            $this->exts->capture_by_chromedevtool("blocked-by-cloudflare");
            sleep(10);
 
            $this->exts->click_by_xdotool('div[style="display: grid;"] > div > div', 30, 28);
            sleep(15);
 
            if (!$this->exts->check_exist_by_chromedevtool('div[style="display: grid;"] > div > div')) {
                break;
            }
        } else {
            break;
        }
    }
}
// end function to bypass hcaptcha

private function downloadFeeReport()
{
    $months = array();
    $year = date('Y');

    $currentMonth = date('m') - 1;
    if ($currentMonth > 0) {
        for ($i = $currentMonth; $i > 0; $i--) {
            $months[] = $i - 1;
        }
    } else {
        $months[] = $currentMonth;
    }

    foreach ($months as $month) {
        $this->exts->moveToElementAndClick('button[data-selector="EXPORT_BUTTON"]');
        sleep(1);

        if ($this->exts->exists('[data-selector="EXPORT_MENU"] button[data-selector="FEE_INVOICE_REPORT"]')) {
            $this->exts->moveToElementAndClick('[data-selector="EXPORT_MENU"] button[data-selector="FEE_INVOICE_REPORT"]');
            sleep(2);

            $this->exts->changeSelectbox('select[data-selector="MONTH_SELECT"]', $month, 2);
            $this->exts->changeSelectbox('select[data-selector="YEAR_SELECT"]', $year, 2);

            if ($this->exts->exists('button[data-selector="FEE_INVOICE.CONFIRM"]')) {
                $this->exts->moveToElementAndClick('button[data-selector="FEE_INVOICE.CONFIRM"]');
            } else {
                $this->exts->moveToElementAndClick('.ReactModalPortal button[class*="primary-button"], [class*="modal"] footer button:last-child, [class*="modal"] footer button[class*="primary-button"]');
            }
            sleep(10);

            // Wait for completion of file download
            $this->exts->wait_and_check_download('pdf');

            // find new saved file and return its path
            $downloaded_file = $this->exts->find_saved_file('pdf', '');
            if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                $invoiceName = basename($downloaded_file, '.pdf');
                $this->exts->new_invoice($invoiceName, '', '', $downloaded_file);
                sleep(2);
            }

            if ($this->exts->exists('button[data-selector="FEE_INVOICE.CANCEL"]')) {
                $this->exts->moveToElementAndClick('button[data-selector="FEE_INVOICE.CANCEL"]');
                sleep(5);
            }
        }
        if ($this->exts->exists('button[data-testid="header-close"]')) {
            $this->exts->moveToElementAndClick('button[data-testid="header-close"]');
            sleep(5);
        }
    }

    $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
    if ($restrictPages == 0) {
        $months = array();
        $year = date('Y') - 1;
        for ($i = 0; $i < 12; $i++) {
            $months[] = $i;
        }
        foreach ($months as $month) {
            $this->exts->moveToElementAndClick('button[data-selector="EXPORT_BUTTON"]');
            sleep(1);

            if ($this->exts->exists('[data-selector="EXPORT_MENU"] button[data-selector="FEE_INVOICE_REPORT"]')) {
                $this->exts->moveToElementAndClick('[data-selector="EXPORT_MENU"] button[data-selector="FEE_INVOICE_REPORT"]');
                sleep(2);

                $this->exts->changeSelectbox('select[data-selector="MONTH_SELECT"]', $month, 2);
                $this->exts->changeSelectbox('select[data-selector="YEAR_SELECT"]', $year, 2);

                if ($this->exts->exists('button[data-selector="FEE_INVOICE.CONFIRM"]')) {
                    $this->exts->moveToElementAndClick('button[data-selector="FEE_INVOICE.CONFIRM"]');
                } else {
                    $this->exts->moveToElementAndClick('.ReactModalPortal button[class*="primary-button"], [class*="modal"] footer button:last-child, [class*="modal"] footer button[class*="primary-button"]');
                }
                sleep(10);

                // Wait for completion of file download
                $this->exts->wait_and_check_download('pdf');

                // find new saved file and return its path
                $downloaded_file = $this->exts->find_saved_file('pdf', '');
                if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                    $invoiceName = basename($downloaded_file, '.pdf');
                    $this->exts->new_invoice($invoiceName, '', '', $downloaded_file);
                    sleep(2);
                }

                if ($this->exts->exists('button[data-selector="FEE_INVOICE.CANCEL"]')) {
                    $this->exts->moveToElementAndClick('button[data-selector="FEE_INVOICE.CANCEL"]');
                    sleep(5);
                }
            }

            if ($this->exts->exists('button[data-testid="header-close"]')) {
                $this->exts->moveToElementAndClick('button[data-testid="header-close"]');
                sleep(5);
            }
        }
    }
}
private function processInvoices()
{
    /**
     * this addtional MAX_INVOICE_LIMIT was added as a bug fix
     */

    $invoice_rows  = 0;
    $clicks = 0;
    $latest_invoice_date = null;
    if ($this->exts->getElement('button[data-selector="TRANSACTION_LIST.LIST_ITEM"]') != null) {
        $invoice_rows = count($this->exts->getElements('button[data-selector="TRANSACTION_LIST.LIST_ITEM"]'));
        $this->exts->log('now clicks:' . $clicks . ' invoices: ' . $invoice_rows);
    }

    $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
    while ($restrictPages == 0 && $this->exts->exists('div[class*="LoadMoreWrapper"] button, div[class*="TransactionHistoryListColumn"] button') && $invoice_rows < $this->MAX_INVOICE_LIMIT && $clicks < 50) {
        try {
            $this->exts->moveToElementAndClick('div[class*="LoadMoreWrapper"] button');
            sleep(3);
            $clicks++;

            if ($this->exts->getElement('button[data-selector="TRANSACTION_LIST.LIST_ITEM"]') != null) {
                $invoice_rows = count($this->exts->getElements('button[data-selector="TRANSACTION_LIST.LIST_ITEM"]'));
            }
            $this->exts->log('now clicks:' . $clicks . ' invoices: ' . $invoice_rows);
        } catch (\Exception $e) {
            $this->exts->log('Exception :' . $e->getMessage());
        }
    }

    $this->exts->capture("4-invoices-page");

    $invoices = [];
    $rows = $this->exts->getElements('button[data-selector="TRANSACTION_LIST.LIST_ITEM"]');
    $this->exts->log("____________________________________");

    foreach ($rows as $row) {
        $this->exts->click_element($row);
        sleep(3);

        $current_receipts = $this->exts->getElements('div[data-selector="TRANSACTIONS_HISTORY.DETAILS"] button[data-selector="RECEIPT_MENU.RECEIPT"]');
        if (count($current_receipts) >= 1) {

            foreach ($current_receipts as $key => $show_receipt) {
                $this->exts->click_element($show_receipt);
                sleep(3);
                $invoiceUrl = trim($this->exts->extract('a[href*="format=pdf"]', null, 'href'));
                $this->exts->log($invoiceUrl);
                $invoiceName = trim(array_shift(explode('?', end(explode('/receipt/', $invoiceUrl)))));
                // $tx_event_id = trim(array_shift(explode('&', end(explode('tx_event_id=', $invoiceUrl)))));
                if (strpos($invoiceName, 'http') !== false) {
                    $invoiceName = trim(array_shift(explode('?', end(explode('/receipts/', $invoiceUrl)))));
                }
                $this->exts->log('invoiceName: ' . $invoiceName);
                // $this->exts->log('tx_event_id: '.$tx_event_id);
                // if ($tx_event_id != '' && strpos($tx_event_id, 'receipts/') === false) $invoiceName = $invoiceName. '_' . $tx_event_id;

                $invoiceAmount = trim($this->exts->extract('div[data-selector*="TRANSACTION"][data-selector*="AMOUNT"]', $row));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $invoiceAmount)) . ' EUR';
                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => '',
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl
                ));
            }
        } else if ($this->exts->exists('div[class*="CompanyName"]') && $row->getAttribute("data-transactionid") != '') {
            $mid = trim(array_pop(explode(" ", $this->exts->extract('div[class*="CompanyName"]', null, 'innerText'))));
            $invoiceName = trim($row->getAttribute("data-transactionid"));
            $invoiceUrl = 'https://receipts-ng.sumup.com/v0.1/receipts/' . $invoiceName . '?mid=' . $mid . '&format=pdf';
            $invoiceAmount = trim($this->exts->extract('div[data-selector*="TRANSACTION"][data-selector*="AMOUNT"]', $row));
            $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $invoiceAmount)) . ' EUR';
            array_push($invoices, array(
                'invoiceName' => $invoiceName,
                'invoiceDate' => '',
                'invoiceAmount' => $invoiceAmount,
                'invoiceUrl' => $invoiceUrl
            ));
        } else if ($this->exts->exists('a[href*="receipts"][href*="format=pdf"]')) {
            $invoiceUrlElement = $this->exts->getElement('a[href*="receipts"][href*="format=pdf"]');
            if($invoiceUrlElement){
                $invoiceUrl = $invoiceUrlElement->getAttribute('href');
                $invoiceName = explode(
                    '?format=pdf',
                    array_pop(explode('receipts/', $invoiceUrl))
                )[0];
                $invoiceAmount = '';
                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => '',
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl
                ));
            }
            
        } else if ($this->exts->exists('a[href*="receipt/"][href*="format=pdf"]')) {
            $invoiceUrlElement = $this->exts->getElement('a[href*="receipt/"][href*="format=pdf"]');
            if($invoiceUrlElement){
                $invoiceUrl = $invoiceUrlElement->getAttribute('href');
                $invoiceName = explode(
                    '?format=pdf',
                    array_pop(explode('receipt/', $invoiceUrl))
                )[0];
                $invoiceAmount = '';
                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => '',
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl
                ));
            }
        }

        $this->isNoInvoice = false;
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
        $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
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
private function processPayouts()
{
    if ($this->exts->config_array['restrictPages'] == '0') {
        for ($m = 0; $m < 10; $m++) {
            if ($this->exts->exists('[data-selector="PAYOUTS_LIST.LOAD_MORE_BTN"]')) {
                $this->exts->moveToElementAndClick('[data-selector="PAYOUTS_LIST.LOAD_MORE_BTN"]');
                sleep(7);
            } else {
                break;
            }
        }
        sleep(5);
    }
    $this->exts->capture('payout-page');

    $payout_count = count($this->exts->getElements('li button[data-selector="PAYOUTS_LIST.PAYOUTS_LIST_ITEM"]'));
    for ($p = 0; $p < $payout_count; $p++) {
        $payout_row = $this->exts->getElements('li button[data-selector="PAYOUTS_LIST.PAYOUTS_LIST_ITEM"]')[$p];
        $this->exts->click_element($payout_row);
        sleep(5);
        $payout_name_element = $this->exts->getElement('//*[contains(@class, "ReactModalPortal__SidePanel")]//*[@role="dialog"]/div/div[last()]/div/p[last()]', null, 'xpath');
        if ($payout_name_element != null) {
            $payout_name = trim($payout_name_element->getAttribute('innerText'));
            $this->exts->log('payout_name: ' . $payout_name);
            $this->isNoInvoice = false;
            if (!$this->exts->invoice_exists($payout_name)) {
                $this->exts->click_element('//*[@role="dialog"]// button//*[text()="Bericht runterladen" or contains(text(), "Download ")]/..');
                sleep(3);

                $this->exts->wait_and_check_download('pdf');
                // find new saved file and return its path
                $downloaded_file = $this->exts->find_saved_file('pdf', $payout_name . '.pdf');
                if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($payout_name, '', '', $downloaded_file);
                }
            } else {
                $this->exts->log('payout Existed: ' . $payout_name);
            }
        }
    }
}
private function downloadPayoutReport()
{
    $this->exts->openUrl('https://me.sumup.com/de-de/reports/overview');
    sleep(15);
    for ($month_offset = 1; $month_offset < 24; $month_offset++) {
        $selected_month = strtotime("-$month_offset months");
        $year = date('Y', $selected_month);
        $month = date('n', $selected_month);
        $this->exts->log("Selecting: $year - $month");
        $this->exts->moveToElementAndClick('button[data-selector="PAYOUTS_FEES_CARD.PAYOUT_REPORT_BUTTON"]');
        sleep(2);
        $this->exts->click_element('input[data-selector="MONTHLY"] + label');
        sleep(5);
        $this->exts->changeSelectbox('select[data-selector="MONTH_SELECT"]', (int)$month - 1, 2);
        $this->exts->changeSelectbox('select[data-selector="YEAR_SELECT"]', $year, 2);
        $this->exts->click_element('input[data-selector="EXPORT"] + label');
        sleep(5);
        $this->exts->capture('payout-page');
        if ($this->exts->exists(".ReactModalPortal button[aria-live='polite']")) {
            $this->exts->click_element('.ReactModalPortal button[aria-live="polite"]');
            sleep(5);
        }
        if ($this->exts->exists(".cui-modal-portal button[aria-live='polite']")) {
            $this->exts->click_element(".cui-modal-portal button[aria-live='polite']");
            sleep(5);
        }

        // Wait for completion of file download
        $this->exts->wait_and_check_download('pdf');
        // find new saved file and return its path
        $downloaded_file = $this->exts->find_saved_file('pdf', '');
        if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
            $invoiceName = basename($downloaded_file, '.pdf');
            $this->exts->new_invoice($invoiceName, '', '', $downloaded_file);
        }
        $this->isNoInvoice =  false;
        if ($this->exts->config_array['restrictPages'] != '0' && $month_offset >= 5) {
            break;
        }
    }
}

private function download_ar_invoice()
{
    sleep(15);
    $this->exts->capture("ar-invoices-page");
    if ($this->exts->exists('a.List__row[href*="invoices/"]')) {
        // IMPORTANT: Row content in list of invoice is dynamic, row can be REMOVED or showed when scroll the list
        // Collecting invoices one by one, start from first invoice row
        $next_row = $this->exts->getElements('a.List__row[href*="invoices/"]')[0];
        //loop using $step_count to avoid infinity loop if somehow, the condition is wrong.
        for ($step_count = 1; $step_count < 1000 && $next_row != null; $step_count++) {
            $this->exts->log('--------------------------');
            $this->exts->log('Finding invoice in row: ' . $step_count);
            $current_row = $next_row;
            $current_row->getLocationOnScreenOnceScrolledIntoView();
            sleep(1);

            $is_paid = $this->exts->getElement('.ListRowStatus.paid', $current_row) != null;
            if ($is_paid) {
                $this->isNoInvoice = false;
                $invoiceName = $this->exts->extract('.List__cell:first-child', $current_row, 'innerText');
                $invoiceName = trim($invoiceName);
                $invoiceFileName = $invoiceName . '.pdf';
                $this->exts->log('invoiceName: ' . $invoiceName);

                if ($this->exts->invoice_exists($invoiceName)) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                } else {
                    $csrfToken = $this->exts->evaluate('(function() { return localStorage.csrfToken; })();');
                    $document_link = $current_row->getAttribute('href');
                    $document_link = trim($document_link, '/');

                    // got to a new tab and do the download
                    $handles = $this->exts->webdriver->getWindowHandles();
                    if (count($handles) < 2) {
                        $this->exts->type_key_by_xdotool("ctrl+t");
                        sleep(2);
                    }
                    $this->exts->webdriver->switchTo()->window(end($handles));
                    if ($csrfToken) {
                        $temps = explode('/', $document_link);
                        $document_id = end($temps);
                        $pdf_url  = "https://invoices.sumup.com/api/sales/invoices/$document_id/pdf/v1?filename=&csrfToken=$csrfToken";
                        $this->exts->log('pdf_url: ' . $pdf_url);
                        $downloaded_file = $this->exts->direct_download($pdf_url, 'pdf', $invoiceFileName);
                    } else {
                        $this->exts->openUrl($document_link);
                        sleep(5);
                        $this->exts->waitTillPresent('a[href*="/pdf/"]');
                        if ($this->exts->exists('a[href*="/pdf/"]')) {
                            $pdf_url  = $this->exts->extract('a[href*="/pdf/"]', null, 'href');
                            $this->exts->log('pdf_url from document detail: ' . $pdf_url);
                            $downloaded_file = $this->exts->direct_download($pdf_url, 'pdf', $invoiceFileName);
                        } else {
                            $this->exts->capture("ar_invoice-no-pdf");
                        }
                    }

                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, '', '', $downloaded_file);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }

                    $handles = $this->exts->webdriver->getWindowHandles();
                    if (count($handles) >= 2) {
                        $this->exts->webdriver->switchTo()->window($handles[0]);
                    }
                }
            }

            // check if have next invoice row
            $next_row = $this->exts->getElement('./following-sibling::a', $current_row, 'xpath');
            if ($next_row == null) {
                // If It don't have next row, try to scroll down with a height of 1 row, then it will load more row of the rest.
                $this->exts->execute_javascript('
            var scroll_offset = ' . $current_row->getAttribute('scrollHeight') . ';
            window.scrollBy(0, scroll_offset);
        ');
                sleep(2);
                $next_row = $this->exts->getElement('./following-sibling::a', $current_row, 'xpath');
            }
        }
    }
}
private function download_creditnote()
{
    sleep(15);
    $this->exts->capture("creditnote-page");
    if ($this->exts->exists('a.List__row[href*="creditnotes/"]')) {
        // IMPORTANT: Row content in list of invoice is dynamic, row can be REMOVED or showed when scroll the list
        // Collecting invoices one by one, start from first invoice row
        $next_row = $this->exts->getElements('a.List__row[href*="creditnotes/"]')[0];
        //loop using $step_count to avoid infinity loop if somehow, the condition is wrong.
        for ($step_count = 1; $step_count < 1000 && $next_row != null; $step_count++) {
            $this->exts->log('--------------------------');
            $this->exts->log('Finding creditnote in row: ' . $step_count);
            $current_row = $next_row;
            $current_row->getLocationOnScreenOnceScrolledIntoView();
            sleep(1);

            $is_paid = $this->exts->getElement('.ListRowStatus.paid', $current_row) != null;
            if ($is_paid) {
                $this->isNoInvoice = false;
                $invoiceName = $this->exts->extract('.List__cell:first-child', $current_row, 'innerText');
                $invoiceName = str_replace('/', '', $invoiceName);
                $invoiceFileName = $invoiceName . '.pdf';
                $this->exts->log('invoiceName: ' . $invoiceName);

                if ($this->exts->invoice_exists($invoiceName)) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                } else {
                    $csrfToken = $this->exts->evaluate('(function() { return localStorage.csrfToken; })();');
                    $document_link = $current_row->getAttribute('href');

                    // got to a new tab and do the download
                    $handles = $this->exts->webdriver->getWindowHandles();
                    if (count($handles) < 2) {
                        $this->exts->type_key_by_xdotool("ctrl+t");
                        sleep(2);
                    }
                    $this->exts->webdriver->switchTo()->window(end($handles));
                    if ($csrfToken) {
                        $temps = explode('creditnotes/', $document_link);
                        $document_id = end($temps);
                        $temps = explode('/', $document_id);
                        $document_id = $temps[0];
                        $pdf_url  = "https://invoices.sumup.com/api/sales/creditnotes/$document_id/pdf/v3?filename=&csrfToken=$csrfToken";
                        $this->exts->log('pdf_url: ' . $pdf_url);
                        $downloaded_file = $this->exts->direct_download($pdf_url, 'pdf', $invoiceFileName);
                    } else {
                        $this->exts->openUrl($document_link);
                        sleep(5);
                        $this->exts->waitTillPresent('a[href*="/pdf/"]');
                        if ($this->exts->exists('a[href*="/pdf/"]')) {
                            $pdf_url  = $this->exts->extract('a[href*="/pdf/"]', null, 'href');
                            $this->exts->log('pdf_url from document detail: ' . $pdf_url);
                            $downloaded_file = $this->exts->direct_download($pdf_url, 'pdf', $invoiceFileName);
                        } else {
                            $this->exts->capture("creditnote-no-pdf");
                        }
                    }

                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, '', '', $downloaded_file);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }

                   
                }
            }

            // check if have next invoice row
            $next_row = $this->exts->getElement('./following-sibling::a', $current_row, 'xpath');
            if ($next_row == null) {
                // If It don't have next row, try to scroll down with a height of 1 row, then it will load more row of the rest.
                $this->exts->execute_javascript('
            var scroll_offset = ' . $current_row->getAttribute('scrollHeight') . ';
            window.scrollBy(0, scroll_offset);
        ');
                sleep(2);
                $next_row = $this->exts->getElement('./following-sibling::a', $current_row, 'xpath');
            }
        }
    }
}