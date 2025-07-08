<?php // updated login code

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

    // Server-Portal-ID: 70688 - Last modified: 10.06.2025 09:16:19 UTC - User: 1

    public $baseUrl = "https://app.anstrex.com/login";
    public $loginUrl = "https://app.anstrex.com/login";
    public $homePageUrl = "https://native.anstrex.com/listing";
    public $username_selector = "input[name=email]";
    public $password_selector = "input[name=password], input.p-password-input";
    public $submit_button_selector = '#loginPage .btn-login, form[action="/login"] button[type="submit"]';
    public $login_confirm_selector = '.member-name, a[href*="logout"], li[aria-label="Log Out"]';
    public $billingPageUrl = "https://my.leadpages.net/#/my-pages";
    public $account_selector = "a[href=\"/my-account/\"]";
    public $billing_selector = "a[href=\"https://app.anstrex.com/subscription_info\"]";
    public $billing_history_selector = "a[href=\"/my-account/billing-history/\"]";
    public $dropdown_selector = "#img_DropDownIcon";
    public $dropdown_item_selector = "#di_billCycleDropDown";
    public $more_bill_selector = ".view-more-bills-btn";
    public $login_tryout = 0;
    public $isNoInvoice = true;
    public $numberInvoices = 0;


    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        $this->exts->loadCookiesFromFile();
        $this->check_solve_blocked_page();

        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();

            $this->exts->openUrl($this->baseUrl);
            $this->check_solve_blocked_page();
            $this->fillForm(0);
            sleep(15);
            if ($this->checkLogin()) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $this->exts->capture("LoginSuccess");
                $this->downloadInvoice();
                if ($this->isNoInvoice) {
                    $this->exts->no_invoice();
                }
                $this->exts->success();
            } else {
                $this->exts->log(__FUNCTION__ . '::Use login failed');
                $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());
                if ($this->exts->urlContains('subscription_info_new') && $this->exts->exists('#checkoutChargebeeForm button[name="submit"]')) {
                    $this->exts->account_not_ready();
                } else if ($this->exts->getElementByText('.alert-danger li', 'user does not exist', null, false) != null) {
                    $this->exts->loginFailure(1);
                } else if ($this->exts->getElementByText('.alert-danger li', 'credentials do not match our records', null, false) != null) {
                    $this->exts->loginFailure(1);
                } else {
                    $this->exts->capture("LoginFailed");
                    $this->exts->loginFailure();
                }
            }
        }
    }

    private function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        try {
            $this->exts->type_key_by_xdotool("F5");
            sleep(7);
            $this->check_solve_blocked_page();
            if ($this->exts->querySelector($this->password_selector) != null) {
                // $this->exts->capture_by_chromedevtool("2-login-page");
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(1);

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->username);
                sleep(5);
                // $this->exts->capture_by_chromedevtool("2-login-page-filled");
                $this->exts->log("Click submit button");
                $this->exts->click_by_xdotool($this->submit_button_selector);
                sleep(1);

                if ($this->exts->querySelector('div#swal2-html-container') != null) {
                    $this->exts->loginFailure(1);
                }
                sleep(1);
                if ($this->exts->querySelector('div#swal2-html-container') != null) {
                    $this->exts->loginFailure(1);
                }
                sleep(1);
                if ($this->exts->querySelector('div#swal2-html-container') != null) {
                    $this->exts->loginFailure(1);
                }
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }

    private function check_solve_blocked_page()
    {
        $unsolved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) <= 0]';
        $solved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) > 0]';
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

    /**
     * Method to Check where user is logged in or not
     * return boolean true/false
     */
    private function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            if ($this->exts->exists('div.p-avatar-clickable')) {
                $this->exts->moveToElementAndClick('div.p-avatar-clickable');
                sleep(2);
            }
            if ($this->exts->exists($this->login_confirm_selector)) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception checking loggedin " . $exception);
        }
        return $isLoggedIn;
    }

    /**
     *method to download incoice
     */

    private function downloadInvoice()
    {
        $this->exts->log("Begin download invoice ");
        sleep(2);
        $this->exts->openUrl('https://app.anstrex.com/subscription/main');
        sleep(10);
        if ($this->exts->exists('div#adroll_allow_all')) {
            $this->exts->moveToElementAndClick('div#adroll_allow_all');
            sleep(3);
        }
        if ($this->exts->exists('[aria-label="Manage Subscription"]')) {
            $this->exts->moveToElementAndClick('[aria-label="Manage Subscription"]');
            sleep(3);
        }
        if ($this->exts->exists('tbody.p-datatable-tbody')) {
            $this->processInvoicesNew();
        }
        if ($this->exts->exists('[aria-label="Manage Subscription"]')) {
            $this->exts->moveToElementAndClick('[aria-label="Manage Subscription"]');
            sleep(3);
        }
        if ($this->exts->exists('iframe#cb-frame')) {
            $iframe_bill = $this->exts->makeFrameExecutable('iframe#cb-frame');
            sleep(2);

            if ($iframe_bill->exists('[data-cb-id="portal_billing_history"]')) {
                $iframe_bill->moveToElementAndClick('[data-cb-id="portal_billing_history"]');
                sleep(10);

                if ((int)@$this->restrictPages == 0) {
                    if (!$iframe_bill->exists('.cb-history__more')) {
                        sleep(10);
                    }
                    if ($iframe_bill->exists('.cb-history__more')) {
                        for ($i = 0; $i < 5; $i++) {
                            if ($iframe_bill->exists('.cb-history__more')) {
                                $iframe_bill->moveToElementAndClick('.cb-history__more');
                                sleep(5);
                            } else {
                                break;
                            }
                        }
                    }
                }

                $this->processSubscriptionInvoice();
                // $this->exts->switchToDefault();
            }
        } else {
            if ($this->exts->exists('button[data-target="#viewInvoicesFormModal"]')) {
                $this->exts->moveToElementAndClick('button[data-target="#viewInvoicesFormModal"]');
                sleep(5);
            }

            $rows = count($this->exts->getElements('div#viewInvoicesFormModal table > tbody > tr'));
            for ($i = 0; $i < $rows; $i++) {
                $row = $this->exts->getElements('div#viewInvoicesFormModal table > tbody > tr')[$i];
                $tags = $this->exts->getElements('td', $row);
                if (count($tags) >= 6 && $this->exts->getElement('button.downloadInvoice', $tags[5]) != null) {
                    $this->isNoInvoice = false;
                    $download_button = $this->exts->getElement('button.downloadInvoice', $tags[5]);
                    $this->exts->execute_javascript("arguments[0].setAttribute('id', 'custom-pdf-download-button-" . $i . "');", [$download_button]);
                    $invoiceName = trim($tags[0]->getAttribute('innerText'));
                    $invoiceFileName = $invoiceName . '.pdf';
                    $invoiceDate = trim($tags[1]->getAttribute('innerText'));
                    $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[4]->getAttribute('innerText'))) . ' USD';

                    $this->exts->log('--------------------------');
                    $this->exts->log('invoiceName: ' . $invoiceName);
                    $this->exts->log('invoiceDate: ' . $invoiceDate);
                    $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                    $parsed_date = $this->exts->parse_date($invoiceDate, 'Y-m-d', 'Y-m-d');
                    $this->exts->log('Date parsed: ' . $parsed_date);
                    $this->isNoInvoice = false;
                    // Download invoice if it not exisited
                    if ($this->exts->invoice_exists($invoiceName)) {
                        $this->exts->log('Invoice existed ' . $invoiceFileName);
                    } else {
                        $this->exts->moveToElementAndClick('button#custom-pdf-download-button-' . $i);
                        $this->exts->wait_and_check_download('pdf');
                        $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                        if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                            $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
                        } else {
                            $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                        }
                    }
                }
            }
        }
    }

    private function processSubscriptionInvoice()
    {
        if ($this->exts->exists('iframe#cb-frame')) {
            $iframe_bill = $this->exts->makeFrameExecutable('iframe#cb-frame');
            sleep(2);
            try {
                if ($iframe_bill->getElement('.cb-history .cb-history__list .cb-invoice') != null) {
                    $receipts = $iframe_bill->getElements('.cb-history .cb-history__list .cb-invoice');
                    foreach ($receipts as $receipt) {
                        $invoice_amount = trim($iframe_bill->getElement('.cb-invoice__left .cb-invoice__details .cb-invoice__price', $receipt)->getAttribute('innerText'));
                        $invoiceAmount = preg_replace('/[^\d\.\,]/', '', $invoice_amount) . ' USD';

                        $invoice_date = trim($iframe_bill->getElement('.cb-invoice__left .cb-invoice__details .cb-invoice__text', $receipt)->getAttribute('innerText'));
                        $invoiceDate = $this->exts->parse_date($invoice_date);
                        if ($invoiceDate == '') {
                            $invoiceDate = $invoice_date;
                        }

                        $downloadPdfButton = $iframe_bill->getElement('.cb-invoice__right [data-cb-id="download_invoice"]', $receipt);
                        try {
                            $downloadPdfButton->click();
                        } catch (\Exception $exception) {
                            $this->exts->execute_javascript("arguments[0].click()", [$downloadPdfButton]);
                        }
                        sleep(5);
                        $this->exts->wait_and_check_download('pdf');

                        $downloaded_file = $this->exts->find_saved_file('pdf');

                        if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                            $invoiceFileName = basename($downloaded_file);
                            $invoiceName = explode('.pdf', $invoiceFileName)[0];
                            $invoiceName = explode('(', $invoiceName)[0];
                            $invoiceName = str_replace(' ', '', $invoiceName);
                            $this->exts->log('Final invoice name: ' . $invoiceName);
                            $invoiceFileName = $invoiceName . '.pdf';
                            @rename($downloaded_file, $this->exts->config_array['download_folder'] . $invoiceFileName);

                            if ($this->exts->invoice_exists($invoiceName)) {
                                $this->exts->log('Invoice existed ' . $invoiceFileName);
                            } else {
                                $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                                sleep(1);
                            }
                        } else {
                            $this->exts->log(__FUNCTION__ . '::No download ');
                        }

                        // $downloaded_file = $this->exts->find_saved_file('pdf', '');
                        // if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
                        //  $invoiceName = basename($downloaded_file,'.pdf');
                        //  $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $downloaded_file);
                        //  sleep(1);
                        // } else {
                        //  $this->exts->log(__FUNCTION__.'::No download '.$invoiceDate);
                        // }
                        $this->isNoInvoice = false;
                    }
                }
            } catch (\Exception $exception) {
                $this->exts->log("Exception processSubscriptionInvoice " . $exception->getMessage());
            }
        }
    }

    private function processInvoicesNew($paging_count = 1)
    {
        $total_invoices = 0;
        $this->exts->waitTillPresent('table tbody tr', 30);
        sleep(10);
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->querySelectorAll('table tbody tr');
        foreach ($rows as $row) {
            if ($this->exts->querySelector('td:nth-child(6) button', $row) != null) {
                $invoiceUrl = '';
                $invoiceName = '';
                $invoiceAmount = $this->exts->extract('td:nth-child(5)', $row);
                $invoiceDate = $this->exts->extract('td:nth-child(3)', $row);
                $downloadBtn = $this->exts->querySelector('td:nth-child(6) button', $row);

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl,
                    'downloadBtn' => $downloadBtn
                ));
                $this->isNoInvoice = false;
            }
        }

        // Download all invoices
        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {
            if ($total_invoices >= 100) break;
            $this->exts->log('--------------------------');
            // $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            // $invoiceFileName = $invoice['invoiceName'] . '.pdf';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'y-m-d', 'Y-m-d');
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
                $total_invoices++;
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
        // next page
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;
        if (
            $restrictPages == 0 && $paging_count < 50 &&
            $this->exts->querySelector('div[data-pc-name="paginator"] button:nth-child(4):not(:disabled)') != null
        ) {
            $paging_count++;
            $this->exts->click_element('div[data-pc-name="paginator"] button:nth-child(4):not(:disabled)');
            sleep(5);
            $this->processInvoicesNew($paging_count);
        } else if ($restrictPages > 0 && $paging_count < $restrictPages && $this->exts->querySelector('div[data-pc-name="paginator"] button:nth-child(4):not(:disabled)') != null) {
            $paging_count++;
            $this->exts->click_element('div[data-pc-name="paginator"] button:nth-child(4):not(:disabled)');
            sleep(5);
            $this->processInvoicesNew($paging_count);
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
