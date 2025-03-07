<?php // migrated script and added custom function for switch to frame
// Server-Portal-ID: 18804 - Last modified: 02.08.2023 05:28:40 UTC - User: 1

public $baseUrl = 'https://app.convertkit.com/';
public $loginUrl = 'https://app.convertkit.com/';
public $invoicePageUrl = 'https://app.convertkit.com/';

public $username_selector = 'form#new_user input[name="user[email]"]';
public $password_selector = 'form#new_user input[name="user[password]"]';
public $remember_me_selector = 'form#new_user input#user_remember_me';
public $submit_login_selector = 'button#user_log_in, form#new_user button[type="submit"]';

public $check_login_failed_selector = 'div.alert:not([style*="display: none"])';
public $check_login_success_selector = 'a[href="/users/logout"]';

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
    // $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(10);
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->getElement($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->openUrl($this->loginUrl);
        sleep(5);
        $this->checkFillLogin();
        sleep(10);

        $this->checkFillTwoFactor();
        sleep(10);
    }

    if ($this->exts->exists('div[data-account*="openInvoices"] form')) {
        $this->exts->account_not_ready();
    }

    if ($this->exts->getElement($this->check_login_success_selector) != null) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        // Open invoices url and download invoice
        $this->exts->openUrl($this->invoicePageUrl);
        sleep(15);
        if ($this->exts->exists('a[href="/account/edit#billing_settings"]')) {
            $this->exts->moveToElementAndClick('a[href="/account/edit#billing_settings"]');
            sleep(14);

            $this->exts->moveToElementAndClick('a#billing');
            sleep(16);
        } else {
            $this->exts->moveToElementAndClick('[src*="/user/avatar"]');
            sleep(3);
            $this->exts->moveToElementAndClick('a[href="/account_settings/account_info"]');
            sleep(15);
            $this->exts->moveToElementAndClick('a[href="/account_settings/billing"]');
            sleep(15);
            if ($this->exts->exists('a#billing')) {
                $this->exts->moveToElementAndClick('a#billing');
                sleep(16);
            } else {
                $billing_button = $this->exts->getElement('.//button[contains(text(),"View your billing history")]', null, 'xpath');
                if ($billing_button != null) {
                    try {
                        $this->exts->log(__FUNCTION__ . ' trigger click.');
                        $billing_button->click();
                    } catch (\Exception $exception) {
                        $this->exts->log(__FUNCTION__ . ' by javascript' . $exception);
                        $this->exts->executeSafeScript("arguments[0].click()", [$billing_button]);
                    }
                }
            }
        }

        $this->processInvoicesPDF();

        // Final, check no invoice
        if ($this->isNoInvoice) {
            $this->exts->no_invoice();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        if ($this->exts->getElement($this->check_login_failed_selector) != null) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLogin()
{
    $this->exts->waitTillPresent($this->password_selector);
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
        if ($this->exts->exists($this->submit_login_selector)) {
            $this->exts->moveToElementAndClick($this->submit_login_selector);
        }

        if(strpos(strtolower($this->exts->waitTillPresent('div.Toaster__message')), 'wait done') !== false){
            $this->exts->capture("login-failed-confirm");
            $this->exts->loginFailure(1);
        }

    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}

private function checkFillTwoFactor()
{
    $two_factor_selector = 'form#devise_authy input#token, input#token_input';
    $two_factor_message_selector = 'form#devise_authy p.auth-box__content__intro';
    $two_factor_submit_selector = 'form#devise_authy input[name="commit"], input#submit_button';

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
            $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);

            $this->exts->log("checkFillTwoFactor: Clicking submit button.");
            sleep(3);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

            $this->exts->moveToElementAndClick($two_factor_submit_selector);
            sleep(15);

            if ($this->exts->getElement($two_factor_selector) == null) {
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
    } else if (
        strpos(strtolower($this->exts->extract('div[data-page="users/unknown-device"]')), "we don't recognize this device") !== false
    ) {
        $this->exts->capture("2-check-fill-2fa");
        $this->exts->log("Sending 2FA request to ask user click on confirm link");
        $message = trim($this->exts->extract('div[data-page="users/unknown-device"]'));
        $this->exts->two_factor_notif_msg_en = $message . "\n>>>Enter \"OK\" after confirmation on device";
        $this->exts->two_factor_notif_msg_de = $message . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        sleep(5);
        $this->exts->capture("2-after-2fa");
        if (!empty($two_factor_code) && trim($two_factor_code) != '') {
            $this->exts->log("User clicked on confirm link");
            $this->checkFillLogin();
            sleep(5);
        }
    }
}

public $download_by_zip_success = true;
public $totalFiles = 0;
// Download all file by download zipfile, download faster but error with some accounts
private function processInvoicesZIP()
{

    $this->exts->log('--- Download file ZIP --');
    $this->exts->capture("4-invoices-page");
    $invoices = [];
    $this->switchToFrame('iframe[src*="customer=cus_"]');
    sleep(3);
    $this->exts->moveToElementAndClick('input[ng-click="toggleSelectionAll()"]:not(:checked)');
    sleep(2);

    $this->exts->moveToElementAndClick('a[ng-click="bulk_zip()"]');
    $rows_len = count($this->exts->getElements('div#transactionsTab table tbody tr'));
    $sleep_time = $rows_len * 2;
    if ($sleep_time > 30) $sleep_time = 30;
    sleep($sleep_time);

    $this->exts->wait_and_check_download('zip');

    $downloaded_file = $this->exts->find_saved_file('zip');
    sleep(15);
    if (!empty($downloaded_file)) {
        $this->exts->log($downloaded_file);
        $this->exts->log('start------------------------------');
        sleep(15);
        $pdf_files = $this->extract_zip_save_pdf($downloaded_file);
        $this->exts->log('final extract --------------------');

        foreach ($pdf_files as $pdf_file) {
            $this->isNoInvoice = false;
            $invoiceFileName = basename($pdf_file);
            $invoiceName = explode('.pdf', $invoiceFileName)[0];
            $this->exts->log('-----------------------------------');
            $this->exts->log("invoiceName :" . $invoiceName);
            if ($this->exts->invoice_exists($invoiceName)) {
                $this->exts->log('Invoice existed ' . $invoiceFileName);
            } else {
                $this->exts->new_invoice($invoiceName, '', '', $invoiceFileName);
                sleep(1);
            }
        }
    } else {
        $this->exts->log('--- Download ZIP failed --');
        $this->download_by_zip_success = false;
    }
}

// Download single PDF file
private function processInvoicesPDF()
{

    $this->exts->log('--- Download file PDF --');
    $this->exts->capture("4-invoices-page");
    $invoices = [];
    $this->switchToFrame('iframe[src*="customer=cus_"]');
    sleep(3);
    $rows_len = count($this->exts->getElements('div#transactionsTab table tbody tr'));
    for ($i = 0; $i < $rows_len; $i++) {
        // $this->exts->refresh();
        // sleep(10);

        // $this->exts->moveToElementAndClick('a#billing');
        // sleep(13);

        // $this->switchToFrame('iframe[src*="customer=cus_"]');
        // sleep(3);
        // $this->exts->log(count($this->exts->getElements('div#transactionsTab table tbody tr')));
        $row = $this->exts->getElements('div#transactionsTab table tbody tr')[$i];
        $tags = $this->exts->getElements('td', $row);
        if (count($tags) >= 5 && $this->exts->getElement('a.fa-file-pdf-o.downloadTransactionReceipt[ng-if="t.show_action"]', $row) != null) {
            $download_button = $this->exts->getElement('a.fa-file-pdf-o.downloadTransactionReceipt[ng-if="t.show_action"]', $row);
            $invoiceName = '';
            $invoiceDate = trim($this->exts->extract('td.date span.date', $row, 'innerText'));
            $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[1]->getAttribute('innerText'))) . ' USD';

            $this->isNoInvoice = false;

            $invoiceFileName = $invoiceName . '.pdf';
            $invoiceDate = $this->exts->parse_date($invoiceDate, 'M. d, Y', 'Y-m-d');



            try {
                $this->exts->log('Click download button');
                $download_button->click();
            } catch (\Exception $exception) {
                $this->exts->log('Click download button by javascript');
                $this->exts->executeSafeScript("arguments[0].click()", [$download_button]);
            }
            sleep(5);
            $this->exts->wait_and_check_download('pdf');
            $downloaded_file = $this->exts->find_saved_file('pdf');

            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $invoiceFileName = basename($downloaded_file);
                $invoiceName = explode('.pdf', $invoiceFileName)[0];
                $invoiceName = explode('(', $invoiceName)[0];
                $invoiceName = str_replace(' ', '', $invoiceName);
                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceFileName);
                $this->exts->log('Date parsed: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $this->exts->log('Final invoice name: ' . $invoiceName);
                $invoiceFileName = $invoiceName . '.pdf';
                @rename($downloaded_file, $this->exts->config_array['download_folder'] . $invoiceFileName);

                if ($this->exts->invoice_exists($invoiceName)) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                } else {
                    $this->exts->new_invoice($invoiceName, '', '', $invoiceFileName);
                    sleep(1);
                }
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceName);
            }
        }
    }
}

private function extract_zip_save_pdf($zipfile)
{
    $saved_file = array();
    $zip = new \ZipArchive;
    $res = $zip->open($zipfile);
    if ($res === TRUE) {
        $temp_name = basename($zipfile, '.zip');
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $zipPdfFile = $zip->statIndex($i);
            $this->exts->log('extension: ' . $zipPdfFile['name']);
            if (strpos($zipPdfFile['name'], '.pdf') === false) continue;
            $zip->extractTo($this->exts->config_array['download_folder'], array(basename($zipPdfFile['name'])));
            @rename($this->exts->config_array['download_folder'] . basename($zipPdfFile['name']), $this->exts->config_array['download_folder'] . $temp_name . '-' . basename($zipPdfFile['name']));
            $saved_file[] = $this->exts->config_array['download_folder'] . $temp_name . '-' . basename($zipPdfFile['name']);
        }
        $zip->close();
        unlink($zipfile);
    } else {
        $this->exts->log(__FUNCTION__ . '::File extraction failed');
    }
    return $saved_file;
}

public function switchToFrame($query_string)
{
    $this->exts->log(__FUNCTION__ . " Begin with " . $query_string);
    $frame = null;
    if (is_string($query_string)) {
        $frame = $this->exts->queryElement($query_string);
    }

    if ($frame != null) {
        $frame_context = $this->exts->get_frame_excutable_context($frame);
        if ($frame_context != null) {
            $this->exts->current_context = $frame_context;
            return true;
        }
    } else {
        $this->exts->log(__FUNCTION__ . " Frame not found " . $query_string);
    }

    return false;
}

