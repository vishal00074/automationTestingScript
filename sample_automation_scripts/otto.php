<?php // handle empty invoice name and remove unncessary commented code and defined credit_note property

/**
 * Chrome Remote via Chrome devtool protocol script, for specific process/portal
 *
 * @package uwa
 *
 * @copyright   GetMyInvoices
 */

define('KERNEL_ROOT', '/var/www/remote-chrome/utils/');

$gmi_browser_core = realpath('/var/www/remote-chrome/utils/GmiChromeManager.php');
require_once($gmi_browser_core);
class PortalScriptCDP
{

    private $exts;
    public $setupSuccess = false;
    private $chrome_manage;
    private $username;
    private $password;

    public function __construct($mode, $portal_name, $process_uid, $username, $password)
    {
        $this->username = $username;
        $this->password = $password;

        $this->exts = new GmiChromeManager();
        $this->exts->screen_capture_location = '/var/www/remote-chrome/screens/';
        $this->exts->init($mode, $portal_name, $process_uid, $username, $password);
        $this->setupSuccess = true;
    }

    /**
     * Method that called first for executing portal script, this method should not be altered by Users.
     */
    public function run()
    {
        if ($this->setupSuccess) {
            try {
                // Start portal script execution
                $this->initPortal(0);
            } catch (\Exception $exception) {
                $this->exts->log('Exception: ' . $exception->getMessage());
                $this->exts->capture("error");
                var_dump($exception);
            }


            $this->exts->log('Execution completed');

            $this->exts->process_completed();
            $this->exts->dump_session_files();
        } else {
            echo 'Script execution failed.. ' . "\n";
        }
    }

    // Server-Portal-ID: 672027 - Last modified: 12.02.2025 06:17:07 UTC - User: 1

    /*Define constants used in script*/
    public $baseUrl = 'https://portal.otto.market/';
    public $loginUrl = 'https://portal.otto.market/';
    public $invoicePageUrl = '';

    public $username_selector = 'input#username';
    public $password_selector = 'input#password';
    public $remember_me_selector = '';
    public $submit_login_selector = 'button[name="login"]';

    public $check_login_failed_selector = '.login form div.obc_alert--error div.obc_alert__text';
    public $check_login_success_selector = 'a[href="/oauth2/sign_out"],b2b-flyout-menu.hydrated.portal_header-nav-item';

    public $no_sales_invoice = 0;
    public $only_sales_invoices = 0;
    public $only_billing_invoice = 0;
    public $isNoInvoice = true;
    public $credit_note = 0;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        $this->no_sales_invoice = isset($this->exts->config_array["no_sales_invoice"]) ? (int) @$this->exts->config_array["no_sales_invoice"] : $this->no_sales_invoice;
        $this->only_billing_invoice = isset($this->exts->config_array["only_billing_invoice"]) ? (int) @$this->exts->config_array["only_billing_invoice"] : $this->only_billing_invoice;
        $this->credit_note = isset($this->exts->config_array["credit_note"]) ? (int) @$this->exts->config_array["credit_note"] : $this->credit_note;
        $this->only_sales_invoices = isset($this->exts->config_array["only_sales_invoices"]) ? (int) @$this->exts->config_array["only_sales_invoices"] : $this->only_sales_invoices;

        $this->exts->openUrl($this->baseUrl);
        sleep(1);

        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);

        $this->exts->waitTillAnyPresent('button.mkt-cct-button-accept-all');
        if ($this->exts->exists('button.mkt-cct-button-accept-all')) {
            $this->exts->click_element('button.mkt-cct-button-accept-all');
        }

        sleep(13);
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->openUrl($this->loginUrl);


            $this->checkFillLogin();


            $this->checkFillTwoFactor();
            sleep(12);


            $maxRetries = 3; // Define the maximum number of retries
            $retryDelay = 15; // Delay between retries in seconds

            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                $mesg = strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText'));
                $this->exts->log('Attempt ' . $attempt . ': error message - ' . $mesg);

                // Check for specific error messages
                if (
                    strpos($mesg, 'alse haben zu lange gebraucht, um sich anzumelden') !== false ||
                    strpos($mesg, 'die aktion ist nicht mehr gültig. bitte fahren sie nun mit der anmeldung fort.') !== false
                ) {

                    // Retry login
                    $this->exts->log('Retrying login... Attempt ' . $attempt);
                    $this->exts->openUrl($this->loginUrl);
                    sleep($retryDelay);

                    $this->checkFillLogin();


                    $this->checkFillTwoFactor();


                    // Check if login was successful after retry
                    $this->exts->waitTillPresent($this->check_login_success_selector, 20);
                    if ($this->exts->exists($this->check_login_success_selector)) {

                        break;
                    }
                }

                // Log failure if max retries exceeded
                if ($attempt === $maxRetries) {
                    $this->exts->log('Max retries reached. Unable to log in.');
                }
            }
        }


        $this->exts->waitTillPresent($this->check_login_success_selector, 20);
        // then check user logged in or not
        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            if ($this->exts->exists('#cookieBannerButtonAccept')) {
                $this->exts->moveToElementAndClick('#cookieBannerButtonAccept');
            }
            $this->exts->capture("3-login-success");


            if ((int) $this->only_sales_invoices == 1) {
                $this->exts->openUrl('https://portal.otto.market/receipts');
                $this->downloadReceipts();
            } else {
                if ((int) $this->no_sales_invoice == 0) {
                    $this->exts->openUrl('https://portal.otto.market/receipts');
                    $this->downloadReceipts();
                }

                if ($this->credit_note == 1) {
                    $this->exts->openUrl('https://portal.otto.market/receipts');
                    sleep(10);
                    if ($this->exts->exists('select#receiptTypeSelect')) {
                        $this->exts->execute_javascript("
                    var selectBox = document.querySelector('select#receiptTypeSelect');
                    selectBox.value = 'REFUND';
                    selectBox.dispatchEvent(new Event('change', { bubbles: true }));
                ");
                        sleep(3);
                        $this->downloadCreditNotes();
                    }
                } else {
                    if ($this->only_billing_invoice == 1) {
                        $this->exts->openUrl('https://portal.otto.market/financials/downloads');
                        $this->download_financial_report();
                    } else {
                        // Open invoices url and download invoice
                        $this->exts->moveToElementAndClick('div.submenu a[href="/financials/payouts"]');
                        $this->processInvoices();
                    }

                    $this->exts->openUrl('https://portal.otto.market/financials/marketplace-fees');
                    $this->download_marketplace_fee();
                }
            }


            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }

            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $mesg = strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText'));
            if (strpos($mesg, 'benutzername und passwort stimmen nicht') !== false || strpos($mesg, 'tige e-mail oder passwort') !== false) {
                $this->exts->loginFailure(1);
            } elseif ($this->exts->urlContains('login-actions/required-action?execution=CONFIGURE_TOTP')) {
                $this->exts->account_not_ready();
            } else {
                $this->exts->loginFailure();
            }
        }
    }
    private function checkFillLogin()
    {
        $this->exts->waitTillPresent($this->password_selector, 20);
        if ($this->exts->getElement($this->password_selector) != null) {
            sleep(3);
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            //$this->exts->moveToElementAndType($this->username_selector, $this->username);
            $this->exts->click_by_xdotool($this->username_selector);
            sleep(2);
            $this->exts->type_text_by_xdotool($this->username);
            sleep(1);

            $this->exts->log("Enter Password");
            $this->exts->click_by_xdotool($this->password_selector);
            $this->exts->type_text_by_xdotool($this->password);
            //$this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            if ($this->remember_me_selector != '')
                $this->exts->moveToElementAndClick($this->remember_me_selector);
            sleep(2);

            $this->exts->capture("2-login-page-filled");
            $this->exts->moveToElementAndClick($this->submit_login_selector);
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }
    private function checkFillTwoFactor()
    {
        $two_factor_selector = 'input[name="otp"]';
        $two_factor_message_selector = 'form#kc-otp-login-form > h1, form#kc-otp-login-form > p';
        $two_factor_submit_selector = 'form#kc-otp-login-form input[name="login"]';
        $this->exts->waitTillPresent($two_factor_selector, 20);
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
        }
    }

    private function download_financial_report()
    {
        sleep(15);
        if ($this->exts->exists('#cookieBannerButtonAccept')) {
            $this->exts->moveToElementAndClick('#cookieBannerButtonAccept');
        }
        $this->exts->capture("4-financial-report");

        $from_date = date('d.m.Y', strtotime('-2 months'));
        if ($this->exts->config_array['restrictPages'] == '0') {
            $from_date = date('d.m.Y', strtotime('-2 years'));
        }
        $this->exts->moveToElementAndClick('.obc_content-container > div:last-child .bca_filters__daterange > div > div:first-child  input[name="formattedDate"]');
        $this->exts->moveToElementAndType('.obc_content-container > div:last-child .bca_filters__daterange > div > div:first-child  input[name="formattedDate"]', '');
        $this->exts->moveToElementAndType('.obc_content-container > div:last-child .bca_filters__daterange > div > div:first-child  input[name="formattedDate"]', $from_date);

        $this->exts->moveToElementAndClick('h1'); // Click outsize input to reload grid
        sleep(10);
        $this->exts->capture("4-financial-report-filtered");

        for ($paging = 1; $paging < 30; $paging++) {
            $invoices = [];
            $invoice_links = $this->exts->getElements('.obc_table a.bca_link-download[href*=".pdf"]');
            foreach ($invoice_links as $invoice_link) {
                $invoiceUrl = $invoice_link->getAttribute("href");
                $invoiceName = explode(
                    '.pdf',
                    end(explode('/', $invoiceUrl))
                )[0];

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => '',
                    'invoiceAmount' => '',
                    'invoiceUrl' => $invoiceUrl
                ));
                $this->isNoInvoice = false;
            }

            // Download all invoices
            $this->exts->log('Financial-report found: ' . count($invoices));
            foreach ($invoices as $invoice) {
                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
                $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
                $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
                $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

                $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : '';
                $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $downloaded_file);
                    sleep(1);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            }

            // next page
            if ($this->exts->exists('.obc_content-container > div:last-child button.bca_btn--paging:last-child:not([disabled])')) {
                $this->exts->moveToElementAndClick('.obc_content-container > div:last-child button.bca_btn--paging:last-child:not([disabled])');
                sleep(10);
            }
        }
    }
    private function processInvoices()
    {
        sleep(10);
        $this->exts->capture("4-invoices-page");
        if ($this->exts->exists('.bca_table__row--payout .bca_table__cell--toggle-arrow')) {
            $invoices = [];
            $sections = $this->exts->getElements('.bca_table__body--payout .bca_table__row--payout');
            foreach ($sections as $key => $section) {
                $expand_icon = $this->exts->getElement('.bca_table__cell--toggle-arrow', $section);
                $this->exts->click_element($expand_icon);
                sleep(7);
                for ($page = 1; $page <= 20; $page++) {
                    $rows_len = count($this->exts->getElements('./following-sibling::tr[contains(@class, "bca_table__row--booking")]', $section, 'xpath'));
                    for ($i = 0; $i < $rows_len; $i++) {
                        $row = $this->exts->getElements('./following-sibling::tr[contains(@class, "bca_table__row--booking")]', $section, 'xpath')[$i];
                        $download_button = $this->exts->getElement('td[name] a', $row);
                        if ($download_button != null) {
                            $invoiceName = trim($download_button->getAttribute('innerText'));
                            $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                            $this->exts->log('invoiceName: ' . $invoiceName);
                            if ($this->exts->invoice_exists($invoiceName)) {
                                $this->exts->log('Invoice existed ' . $invoiceFileName);
                            } else {
                                $this->exts->click_element($download_button);
                                sleep(3);
                                if ($this->exts->exists('embed[src*=".pdf"]')) {
                                    // for some combination, pdf doesn't loaded on iframe
                                    $pdf_url = $this->exts->extract('embed[src*=".pdf"]', null, 'src');
                                    $downloaded_file = $this->exts->direct_download($pdf_url, 'pdf', $invoiceFileName);
                                } else {
                                    $this->exts->wait_and_check_download('pdf');
                                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                                }

                                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                                    $this->exts->new_invoice($invoiceName, '', '', $invoiceFileName);
                                    sleep(1);
                                } else {
                                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceName);
                                }
                                $this->exts->click_element('#finance-module-receipt-pop-up button.obc_modal__close');
                                sleep(1);
                            }
                        }
                    }


                    $next_page_button = $this->exts->getElement('./following-sibling::tr//*[contains(@class, "bca_pagination__paging")]/button[last()][not(@disabled)]', $section, 'xpath');
                    // If full download then download all, else download 20 first invoices
                    if ($this->exts->config_array['restrictPages'] == '0' && $next_page_button != null) {
                        $this->exts->click_element($next_page_button);
                        sleep(5);
                    } else if ($page < 2 && $next_page_button != null) {
                        $this->exts->click_element($next_page_button);
                        sleep(5);
                    } else {
                        break;
                    }
                }

                // Close current section
                $expand_icon = $this->exts->getElement('.bca_table__cell--toggle-arrow', $section);
                $this->exts->click_element($expand_icon);
                sleep(1);
            }
        } else {
            // Huy added this 01-2023, not sure this site changed payout or not, added this code for new layout
            $from_date = date('d.m.Y', strtotime('-2 months'));
            if ($this->exts->config_array['restrictPages'] == '0') {
                $from_date = date('d.m.Y', strtotime('-2 years'));
            }
            $this->exts->moveToElementAndClick('.bca_filters__daterange > div > div:first-child  input[name="formattedDate"]');
            $this->exts->moveToElementAndType('.bca_filters__daterange > div > div:first-child  input[name="formattedDate"]', '');
            $this->exts->moveToElementAndType('.bca_filters__daterange > div > div:first-child  input[name="formattedDate"]', $from_date);

            $this->exts->moveToElementAndClick('h1'); // Click outsize input to reload grid
            sleep(10);
            for ($page = 1; $page <= 50; $page++) {
                $rows_len = count($this->exts->getElements('tr.bca_table__row--payout'));
                for ($i = 0; $i < $rows_len; $i++) {
                    $row = $this->exts->getElements('tr.bca_table__row--payout')[$i];
                    $download_button = $this->exts->getElement('.//a/label[text()="PDF"]/..', $row, 'xpath');
                    if ($download_button != null) {
                        $this->isNoInvoice = false;
                        $invoiceName = $this->exts->extract('td:nth-child(2)', $row);
                        $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                        $this->exts->log('invoiceName: ' . $invoiceName);
                        if ($this->exts->invoice_exists($invoiceName)) {
                            $this->exts->log('Invoice existed ' . $invoiceName);
                        } else {
                            $this->exts->click_element($download_button);
                            sleep(5);
                            if ($this->exts->exists('embed[src*=".pdf"]')) {
                                // for some combination, pdf doesn't loaded on iframe
                                $pdf_url = $this->exts->extract('embed[src*=".pdf"]', null, 'src');
                                $downloaded_file = $this->exts->direct_download($pdf_url, 'pdf', $invoiceFileName);
                            } else {
                                $this->exts->wait_and_check_download('pdf');
                                $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                            }

                            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                                $this->exts->new_invoice($invoiceName, '', '', $downloaded_file);
                            } else {
                                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceName);
                            }
                        }
                    }
                }


                $next_page_button = $this->exts->getElement('.bca_pagination button:last-child:not([disabled="disabled"])');
                // If full download then download all, else download 20 first invoices
                if ($next_page_button != null) {
                    $this->exts->click_element($next_page_button);
                    sleep(10);
                } else {
                    break;
                }
            }
        }
    }
    private function downloadReceipts()
    {
        sleep(7);
        $this->exts->capture("4-receipt-page");
        $processed_month = 0;
        $years = count($this->exts->getElements('.receipts_tree .receipts_tree-column:nth-child(1) > .receipts_tree-item'));
        for ($y = 0; $y < $years; $y++) {
            $year = $this->exts->getElements('.receipts_tree .receipts_tree-column:nth-child(1) > .receipts_tree-item')[$y];
            $year_label = $year->getAttribute('innerText');
            $this->exts->log($year_label);
            $this->exts->click_element($year);
            sleep(3);
            $months = count($this->exts->getElements('.receipts_tree .receipts_tree-column:nth-child(2) > .receipts_tree-item'));
            for ($m = 0; $m < $months; $m++) {
                $processed_month++;
                $month = $this->exts->getElements('.receipts_tree .receipts_tree-column:nth-child(2) > .receipts_tree-item')[$m];
                $month_label = $month->getAttribute('innerText');
                $this->exts->log($month_label);
                $this->exts->click_element($month);
                sleep(3);

                $receipt_download_buttons = $this->exts->getElements('.receipts_tree .receipts_tree-column:nth-child(3) > .receipts_tree-item .receipts_tree-item-download');
                foreach ($receipt_download_buttons as $key => $receipt_download_button) {
                    $this->isNoInvoice = false;
                    // $receipt_download_button->getLocationOnScreenOnceScrolledIntoView();
                    $this->exts->click_element($receipt_download_button);
                    sleep(5);
                    $tempInvFileName = time() . '.zip';
                    $this->exts->wait_and_check_download('zip');
                    $downloaded_file = $this->exts->find_saved_file('zip', $tempInvFileName);
                    $this->extract_zip_send_invoice($downloaded_file);
                }

                // if restrictPage == 0 process all, else only back to 2 months
                if ($this->exts->config_array['restrictPages'] != '0' && $processed_month >= 2)
                    return;
            }
        }
    }
    private function downloadCreditNotes()
    {
        sleep(7);
        $this->exts->capture("4-credit-note-page");
        $processed_month = 0;
        $years = count($this->exts->getElements('.receipts_tree .receipts_tree-column:nth-child(1) > .receipts_tree-item'));
        for ($y = 0; $y < $years; $y++) {
            $year = $this->exts->getElements('.receipts_tree .receipts_tree-column:nth-child(1) > .receipts_tree-item')[$y];
            $year_label = $year->getAttribute('innerText');
            $this->exts->log($year_label);
            $this->exts->click_element($year);
            sleep(3);
            $months = count($this->exts->getElements('.receipts_tree .receipts_tree-column:nth-child(2) > .receipts_tree-item'));
            for ($m = 0; $m < $months; $m++) {
                $processed_month++;
                $month = $this->exts->getElements('.receipts_tree .receipts_tree-column:nth-child(2) > .receipts_tree-item')[$m];
                $month_label = $month->getAttribute('innerText');
                $this->exts->log($month_label);
                $this->exts->click_element($month);
                sleep(3);

                $receipt_download_buttons = $this->exts->getElements('.receipts_tree .receipts_tree-column:nth-child(3) > .receipts_tree-item .receipts_tree-item-download');
                foreach ($receipt_download_buttons as $key => $receipt_download_button) {
                    $this->isNoInvoice = false;
                    // $receipt_download_button->getLocationOnScreenOnceScrolledIntoView();
                    $this->exts->click_element($receipt_download_button);
                    sleep(5);
                    $tempInvFileName = time() . '.zip';
                    $this->exts->wait_and_check_download('zip');
                    $downloaded_file = $this->exts->find_saved_file('zip', $tempInvFileName);
                    $this->extract_zip_send_invoice($downloaded_file);
                }

                // if restrictPage == 0 process all, else only back to 2 months
                if ($this->exts->config_array['restrictPages'] != '0' && $processed_month >= 2)
                    return;
            }
        }
    }
    private function download_marketplace_fee()
    {
        sleep(7);
        $this->exts->capture("4-marketplace_fee-page");
        for ($page = 1; $page <= 50; $page++) {
            $rows_len = count($this->exts->getElements('tr.orka_table__row--marketplace'));
            for ($i = 0; $i < $rows_len; $i++) {
                $row = $this->exts->getElements('tr.orka_table__row--marketplace')[$i];
                $download_button = $this->exts->getElement('.//a/label[text()="PDF"]/..', $row, 'xpath');
                if ($download_button != null) {
                    $this->isNoInvoice = false;
                    $invoiceName = $this->exts->extract('td:nth-child(3)', $row);
                    $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                    $this->exts->log('invoiceName: ' . $invoiceName);
                    if ($this->exts->invoice_exists($invoiceName)) {
                        $this->exts->log('Invoice existed ' . $invoiceName);
                    } else {
                        $this->exts->click_element($download_button);
                        sleep(5);
                        $this->exts->wait_and_check_download('pdf');
                        $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                        if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                            $this->exts->new_invoice($invoiceName, '', '', $downloaded_file);
                        } else {
                            sleep(15);
                            $this->exts->wait_and_check_download('pdf');
                            $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                                $this->exts->new_invoice($invoiceName, '', '', $downloaded_file);
                            } else {
                                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceName);
                            }
                        }
                    }
                }
            }


            $next_page_button = $this->exts->getElement('input[aria-label="next"]:not([disabled="disabled"])');
            // If full download then download all, else download 20 first invoices
            if ($this->exts->config_array['restrictPages'] == '0' && $next_page_button != null) {
                $this->exts->click_element($next_page_button);
                sleep(5);
            } else if ($page < 2 && $next_page_button != null) {
                $this->exts->click_element($next_page_button);
                sleep(5);
            } else {
                break;
            }
        }
    }
    private function extract_zip_send_invoice($zipfile)
    {
        $this->exts->log('Extracting..: ' . $zipfile);
        $saved_file = '';
        $zip = new \ZipArchive;
        $res = $zip->open($zipfile);
        if ($res === TRUE) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $zipfilename = $zip->getNameIndex($i);
                $zipfileinfo = pathinfo($zipfilename);
                $extractedfilepath = $this->exts->config_array['download_folder'] . $zipfileinfo['basename'];
                copy("zip://" . $zipfile . "#" . $zipfilename, $extractedfilepath);

                if (file_exists($extractedfilepath)) {
                    $invoiceName = $zipfileinfo['filename'];
                    if ($this->exts->invoice_exists($invoiceName)) {
                        $this->exts->log('Invoice existed ' . $invoiceName);
                    } else {
                        $this->exts->new_invoice($invoiceName, '', '', $extractedfilepath);
                    }
                }
            }
            $zip->close();
            unlink($zipfile);
        } else {
            $this->exts->log(__FUNCTION__ . '::File extraction failed');
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
