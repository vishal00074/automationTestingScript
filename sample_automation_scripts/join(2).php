<?php // handle empty invoice name in download code

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

    // Server-Portal-ID: 210991 - Last modified: 31.03.2025 14:07:48 UTC - User: 1

    public $baseUrl = 'https://join.com/dashboard';
    public $loginUrl = 'https://join.com/auth/login';
    public $invoicePageUrl = 'https://join.com/company/billing';
    public $username_selector = 'form input#email';
    public $password_selector = 'form input#password';
    public $remember_me_selector = '';
    public $submit_login_selector = 'form button[type="submit"]';
    public $check_login_failed_selector = 'small[data-testid="FormError"]';
    public $check_login_success_selector = 'div[data-testid="UserMenuRecruiterLabel"], a[href*="/user/profile"], a[href="/company/billing"], button[data-testid="UserMenuButton"]';
    public $isNoInvoice = true;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(3);
        $this->exts->waitTillAnyPresent([$this->username_selector, $this->check_login_success_selector], 150);

        if ($this->exts->exists('div#cookiescript_accept')) {
            $this->exts->moveToElementAndClick('div#cookiescript_accept');
            sleep(1);
        }
        $this->exts->capture('1-init-page');
        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->querySelector($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->checkFillLogin();
            $this->exts->waitTillPresent($this->check_login_success_selector, 100);
        }

        if ($this->exts->querySelector($this->check_login_success_selector) != null) {
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice
            $this->exts->openUrl($this->invoicePageUrl);
            $this->exts->log('Open invoices url and download invoice');

            $this->exts->waitTillPresent('div[data-testid="BillingSettingsCard"] button', 70);
            if ($this->exts->exists('div[data-testid="BillingSettingsCard"] button')) {
                $this->exts->click_element("div[data-testid='BillingSettingsCard'] button");
                sleep(3);
                $download_invoice_tab = $this->exts->findTabMatchedUrl(['stripe']);
                if ($download_invoice_tab != null) {
                    $this->exts->switchToTab($download_invoice_tab);
                }
                $this->processStripeInvoices();
            } else if ($this->exts->exists('[data-testid = "BillingHistory"] a[href*="in.xero.com"], [data-testid = "BillingHistory"] a[href*="invoice"]')) {
                $this->processInvoicesLatest();
                sleep(3);
            } else {
                $this->exts->openUrl("https://join.com/company/billing/subscriptions");
                $this->exts->log('Open subscription url');
                $this->processSubscriptions();
            }

            $this->processInvoices();
            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());
            if ($this->exts->getElementByText($this->check_login_failed_selector, ['passwort oder', 'invalid email', 'email and password', 'Mail und Passwort'], null, false) != null) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function checkFillLogin()
    {
        if ($this->exts->exists($this->password_selector) != null) {
            $this->exts->capture("2-login-page");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);
            $this->exts->capture("2-login-page-filled-password");
            $this->checkFillRecaptcha();
            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->moveToElementAndClick($this->submit_login_selector);
                sleep(5);
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    public $moreBtn = true;

    private function checkFillRecaptcha()
    {
        $this->exts->log(__FUNCTION__);
        $recaptcha_iframe_selector = 'iframe[src*="/recaptcha/api2/anchor?"]';
        $recaptcha_textarea_selector = 'textarea[name="g-recaptcha-response"]';
        if ($this->exts->exists($recaptcha_iframe_selector)) {
            $iframeUrl = $this->exts->extract($recaptcha_iframe_selector, null, 'src');
            $data_siteKey = explode('&', end(explode("&k=", $iframeUrl)))[0];
            $this->exts->log("iframe url  - " . $iframeUrl);
            $this->exts->log("SiteKey - " . $data_siteKey);

            $isCaptchaSolved = $this->exts->processRecaptcha($this->exts->getUrl(), $data_siteKey, false);
            $this->exts->log("isCaptchaSolved - " . $isCaptchaSolved);

            if ($isCaptchaSolved) {
                // Step 1 fill answer to textarea
                $this->exts->log(__FUNCTION__ . "::filling reCaptcha response..");
                $recaptcha_textareas =  $this->exts->querySelectorAll($recaptcha_textarea_selector);
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
                $this->exts->log('Callback function: ' . $gcallbackFunction);
                if ($gcallbackFunction != null) {
                    $this->exts->execute_javascript($gcallbackFunction . '("' . $this->exts->recaptcha_answer . '");');
                    sleep(10);
                }
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Not found reCaptcha');
        }
    }

    private function processInvoicesLatest($paging_count = 1)
    {
        sleep(3);
        $this->exts->waitTillPresent('[data-testid = "BillingHistory"] a[href*="in.xero.com"], [data-testid = "BillingHistory"] a[href*="invoice"]');
        $this->exts->capture("4-invoices-page-latest");

        for ($i = 0; $i < 20 && $this->exts->config_array["restrictPages"] == 0 && $this->exts->exists('button[data-testid="LoadMoreInvoices"]'); $i++) {
            $this->exts->moveToElementAndClick('button[data-testid="LoadMoreInvoices"]');
            sleep(3);
        }

        $rows = $this->exts->querySelectorAll('[data-testid = "BillingHistory"] a[href*="in.xero.com"], [data-testid = "BillingHistory"] a[href*="invoice"]');
        $this->exts->log('Total no of invoices : ' . count($rows));

        $count = 1;
        foreach ($rows as $row) {
            $invoiceName = $this->exts->extract('[data-testid="InvoiceName-' . $count . '"]');
            $parseDate = $this->exts->extract('[data-testid="InvoiceDate-' . $count . '"]');;
            $invoiceAmount = $this->exts->extract('[data-testid="InvoiceTotal-' . $count . '"]');;
            $invoiceDate = $this->exts->parse_date($parseDate, 'd F Y', 'Y-m-d');
            $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
            $count++;
            $invoiceUrl = $row->getAttribute("href");

            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoiceName);
            $this->exts->log('invoiceDate: ' . $invoiceDate);
            $this->exts->log('invoiceAmount: ' . $invoiceAmount);
            $this->exts->log('invoiceUrl: ' . $invoiceUrl);
            $this->exts->log('invoiceFileName: ' . $invoiceFileName);

            sleep(5);

            if ($this->exts->invoice_exists($invoiceName) || $this->exts->document_exists($invoiceFileName)) {
                $this->exts->log('Invoice existed ' . $invoiceFileName);
            } else {

                sleep(5); // Wait for the download to complete

                $downloaded_file = $this->exts->direct_download($invoiceUrl, 'pdf', $invoiceFileName);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                    sleep(1);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }

                $this->isNoInvoice = false;
            }
        }
    }

    private function processInvoices($paging_count = 1)
    {
        $this->exts->waitTillPresent('div[data-testid="DataTable"] div[data-testid="data-table-row"]');
        $this->exts->capture("4-invoices-page");
        $invoices = [];
        if ($this->exts->exists('div[data-testid="DataTable"] div[data-testid="data-table-row"]')) {
            $rows = $this->exts->querySelectorAll('div[data-testid="DataTable"] div[data-testid="data-table-row"]');
            foreach ($rows as $row) {
                $tags = $this->exts->querySelectorAll('div[data-testid*="data-table"]', $row);
                if (count($tags) >= 4 && $this->exts->querySelector('a[href*="in.xero.com"][data-testid="open-invoice-link"]', $tags[4]) != null) {

                    $invoiceUrl = $this->exts->querySelector('a[href*="in.xero.com"][data-testid="open-invoice-link"]', $tags[4])->getAttribute("href");

                    $invoiceName = end(explode("/", trim($invoiceUrl, '/')));
                    $invoiceUrl = $invoiceUrl . "/Invoice/DownloadPdf/";
                    $invoiceDate = trim($tags[0]->getAttribute('innerText'));
                    $invoiceAmount = '';
                    array_push($invoices, array(
                        'invoiceName' => $invoiceName,
                        'invoiceDate' => $invoiceDate,
                        'invoiceAmount' => $invoiceAmount,
                        'invoiceUrl' => $invoiceUrl
                    ));
                    $this->isNoInvoice = false;
                }
            }
        } else {
            $rows = $this->exts->querySelectorAll('div[data-testid="DataTable"] > div');
            foreach ($rows as $row) {
                if ($this->exts->querySelector('a[href*="in.xero.com"][data-testid="open-invoice-link"]', $row) != null) {
                    $invoiceUrl = $this->exts->querySelector('a[href*="in.xero.com"][data-testid="open-invoice-link"]', $row)->getAttribute("href");
                    $invoiceName = end(explode("/", trim($invoiceUrl, '/')));
                    $invoiceUrl = $invoiceUrl . "/Invoice/DownloadPdf/";
                    $invoiceDate = '';
                    $invoiceAmount = '';
                    array_push($invoices, array(
                        'invoiceName' => $invoiceName,
                        'invoiceDate' => $invoiceDate,
                        'invoiceAmount' => $invoiceAmount,
                        'invoiceUrl' => $invoiceUrl
                    ));
                    $this->isNoInvoice = false;
                }
            }
        }

        // Download all invoices
        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);
            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : '';
            $parseDate = $invoice['invoiceDate'];
            $parse_date = $this->exts->parse_date($parseDate, 'd F Y', 'Y-m-d');
            if ($parse_date == '') {
                $parse_date = $this->exts->parse_date($parseDate, 'M j, Y', 'Y-m-d');
            }
            $this->exts->log('Date parsed: ' . $parse_date);

            $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $parse_date, $invoice['invoiceAmount'], $downloaded_file);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }

        $rows = $this->exts->querySelectorAll('[data-testid = "BillingHistory"] a[href*="in.xero.com"], [data-testid = "BillingHistory"] a[href*="invoice"]');
        $this->exts->log('Subscriptions found: ' . count($rows));
        $count = 1;
        foreach ($rows as $row) {
            $invoiceName = $this->exts->extract('[data-testid="InvoiceName-' . $count . '"]');
            $parseDate = $this->exts->extract('[data-testid="InvoiceDate-' . $count . '"]');;
            $invoiceAmount = $this->exts->extract('[data-testid="InvoiceTotal-' . $count . '"]');;
            $invoiceDate = $this->exts->parse_date($parseDate, 'd F Y', 'Y-m-d');
            $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
            $count++;
            $invoiceUrl = $row->getAttribute("href");
            // $this->exts->execute_javascript('window.open()');
            // sleep(3);
            // $handles = $this->exts->webdriver->getWindowHandles();
            // $this->exts->webdriver->switchTo()->window(end($handles));
            // $this->exts->openUrl($invoiceUrl);
            // sleep(5);
            if ($this->exts->exists('button.download__button')) {
                $this->exts->moveToElementAndClick('button.download__button');
                $invoiceFileName = $this->exts->extract('ul[role=listbox] li span', null, 'innerText');
                $this->exts->log("---------------------------------");
                $this->exts->log("invoiceFileName" . $invoiceFileName);
                $this->exts->moveToElementAndClick('ul[role=listbox] li button');
                sleep(3);
                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                $this->isNoInvoice = false;
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $pdf_content = file_get_contents($downloaded_file);
                    if (stripos($pdf_content, "%PDF") !== false) {
                        $this->exts->new_invoice($invoiceFileName, '', '', $invoiceFileName);
                        sleep(1);
                    } else {
                        $this->exts->log(__FUNCTION__ . ":: Not Valid PDF - " . $downloaded_file);
                    }
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            } else {
                $downloaded_file = $this->exts->direct_download($invoiceUrl, 'pdf', $invoiceFileName);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->isNoInvoice = false;
                    $this->exts->new_invoice($invoiceFileName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                    sleep(1);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            }

            // $handles = $this->exts->webdriver->getWindowHandles();
            // if (count($handles) > 1) {
            //     $this->exts->webdriver->close();
            //     $handles = $this->exts->webdriver->getWindowHandles();
            //     $this->exts->webdriver->switchTo()->window(end($handles));
            //     sleep(2);
            // }
        }
        $paging_count++;
        if (
            $this->exts->config_array["restrictPages"] == '0' &&
            $paging_count < 50 &&
            $this->exts->querySelector('li.next a[aria-disabled="false"]') != null
        ) {
            $this->exts->moveToElementAndClick('li.next a[aria-disabled="false"]');
            sleep(5);
            $this->processInvoices($paging_count);
        }
    }

    private function processSubscriptions($paging_count = 1)
    {
        sleep(10);

        $this->exts->capture("5-Subscriptions-page");
        $invoices = [];

        $rows = $this->exts->querySelectorAll('div[data-testid="DataTable"] div[data-testid="data-table-row"]');
        if (count($rows) > 0) {
            foreach ($rows as $row) {
                $tags = $this->exts->querySelectorAll('div[data-testid*="data-table"]', $row);
                if (count($tags) >= 3 && $this->exts->querySelector('a[href*="in.xero.com"][data-testid="open-invoice-link"]', $tags[3]) != null) {

                    $invoiceUrl = $this->exts->querySelector('a[href*="in.xero.com"][data-testid="open-invoice-link"]', $tags[3])->getAttribute("href");
                    $invoiceName = end(explode("/", trim($invoiceUrl, '/')));

                    $invoiceUrl = $invoiceUrl . "/Invoice/DownloadPdf/";
                    $invoiceDate = trim($tags[1]->getAttribute('innerText'));
                    $invoiceAmount = '';
                    array_push($invoices, array(
                        'invoiceName' => $invoiceName,
                        'invoiceDate' => $invoiceDate,
                        'invoiceAmount' => $invoiceAmount,
                        'invoiceUrl' => $invoiceUrl
                    ));
                    $this->isNoInvoice = false;
                }
            }

            // Download all invoices
            $this->exts->log('Subscriptions found: ' . count($invoices));
            foreach ($invoices as $invoice) {
                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
                $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
                $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
                $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);
                $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : '';
                $parseDate = $invoice['invoiceDate'];
                $invoice['invoiceDate'] = $this->exts->parse_date($parseDate, 'd F Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

                $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                    sleep(1);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            }
        } else {
            $rows = $this->exts->querySelectorAll('[data-testid = "BillingHistory"] a[href*="in.xero.com"], [data-testid = "BillingHistory"] a[href*="invoice"]');
            $this->exts->log('Subscriptions found: ' . count($rows));
            $count = 1;
            foreach ($rows as $row) {
                $invoiceName = $this->exts->extract('[data-testid="InvoiceName-' . $count . '"]');
                $parseDate = $this->exts->extract('[data-testid="InvoiceDate-' . $count . '"]');;
                $invoiceAmount = $this->exts->extract('[data-testid="InvoiceTotal-' . $count . '"]');;
                $invoiceDate = $this->exts->parse_date($parseDate, 'd F Y', 'Y-m-d');
                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                $count++;
                $invoiceUrl = $row->getAttribute("href");
                // $this->exts->execute_javascript('window.open()');
                // sleep(3);
                // $handles = $this->exts->webdriver->getWindowHandles();
                // $this->exts->webdriver->switchTo()->window(end($handles));
                // $this->exts->openUrl($invoiceUrl);
                // sleep(5);
                if ($this->exts->exists('button.download__button')) {
                    $this->exts->moveToElementAndClick('button.download__button');
                    $invoiceFileName = $this->exts->extract('ul[role=listbox] li span', null, 'innerText');
                    $this->exts->log("---------------------------------");
                    $this->exts->log("invoiceFileName" . $invoiceFileName);
                    $this->exts->moveToElementAndClick('ul[role=listbox] li button');
                    sleep(3);
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                    $this->isNoInvoice = false;
                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $pdf_content = file_get_contents($downloaded_file);
                        if (stripos($pdf_content, "%PDF") !== false) {
                            $this->exts->new_invoice($invoiceFileName, '', '', $invoiceFileName);
                            sleep(1);
                        } else {
                            $this->exts->log(__FUNCTION__ . ":: Not Valid PDF - " . $downloaded_file);
                        }
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
                } else {
                    if (trim($invoiceName) != '') {
                        $filename = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                    }
                    $downloaded_file = $this->exts->direct_download($invoiceUrl, 'pdf', $invoiceFileName);
                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->isNoInvoice = false;
                        $this->exts->new_invoice($invoiceFileName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                        sleep(1);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
                }

                // $handles = $this->exts->webdriver->getWindowHandles();
                // if (count($handles) > 1) {
                //     $this->exts->webdriver->close();
                //     $handles = $this->exts->webdriver->getWindowHandles();
                //     $this->exts->webdriver->switchTo()->window(end($handles));
                //     sleep(2);
                // }
            }
        }

        $paging_count++;
        if (
            $this->exts->config_array["restrictPages"] == '0' &&
            $paging_count < 50 &&
            $this->exts->querySelector('li.next a[aria-disabled="false"]') != null
        ) {
            $this->exts->moveToElementAndClick('li.next a[aria-disabled="false"]');
            sleep(5);
            $this->processSubscriptions($paging_count);
        }
    }
    private function processStripeInvoices($paging_count = 1)
    {
        for ($wait = 0; $wait < 15 && $this->exts->executeSafeScript("return !!document.querySelector('a[href*=\"invoice.stripe.com\"]');") != 1; $wait++) {
            $this->exts->log('Waiting for login.....');
            sleep(10);
        }

        sleep(2);
        $this->exts->capture("4-invoices-page");
        // Keep clicking more but maximum upto 10 times
        $maxAttempts = 10;
        $attempt = 0;

        while ($attempt < $maxAttempts && $this->exts->exists('button[data-testid="view-more-button"]') && $this->exts->config_array["restrictPages"] == 0) {
            $this->exts->execute_javascript('
            var btn = document.querySelector("button[data-testid=\'view-more-button\']");
            if(btn){
                btn.click();
            }
        ');
            $attempt++;
            sleep(5);
        }
        $invoices = [];

        $rows = $this->exts->querySelectorAll('a[href*="invoice.stripe.com"]');
        foreach ($rows as $row) {
            array_push($invoices, array(
                'invoiceUrl' => $row->getAttribute('href')
            ));
        }
        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {
            $this->exts->openUrl($invoice['invoiceUrl']);
            sleep(3);
            $this->exts->waitTillPresent('div.InvoiceDetailsRow-Container > button:nth-child(1)', 50);
            if ($this->exts->querySelector('div.InvoiceDetailsRow-Container > button:nth-child(1)') != null) {
                $invoiceName = $this->exts->extract('table.InvoiceDetails-table tr:nth-child(1) > td:nth-child(2)');
                $invoiceDate = $this->exts->extract('table.InvoiceDetails-table tr:nth-child(2) > td:nth-child(2)');
                $invoiceAmount = $this->exts->extract('div[data-testid="invoice-summary-post-payment"] h1[data-testid="invoice-amount-post-payment"]');
                $downloadBtn = $this->exts->querySelector('div.InvoiceDetailsRow-Container > button:nth-child(1)');

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);
                $this->isNoInvoice = false;
            }
            $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoiceDate, 'm.d.y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoiceDate);

            // $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
            $downloaded_file = $this->exts->click_and_download($downloadBtn, 'pdf', $invoiceFileName);

            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
