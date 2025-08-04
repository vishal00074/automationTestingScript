<?php // added message to trigger loginfailedconfirmed handle empty invoice name

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

    // Server-Portal-ID: 74288 - Last modified: 01.04.2025 09:11:21 UTC - User: 1

    /*Define constants used in script*/
    public $baseUrl = 'https://gruppenkonto.spiegel.de/';

    public $username_selector = 'input[name="email"], input#loginname, input#username';
    public $password_selector = 'input[name="password"], input#password';
    public $submit_login_selector = 'form button[type="submit"], button[id*="loginform:submit"], button#submit';

    public $check_login_failed_selector = 'form .Access-error, [data-sel="LOGIN_FAILED"]';
    public $check_login_success_selector = '.Navigation-login a[href*="/logout"], a.Navigation-mainbarContainerLink[href*="/access/account"], a[href*="abmelden"]';

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
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->querySelector($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->baseUrl);
            sleep(15);
            if ($this->exts->exists('.OffsetContainer a[href*="authenticate"]') && $this->exts->querySelector($this->password_selector) == null) {
                $this->exts->moveToElementAndClick('.OffsetContainer  a[href*="authenticate"]');
                sleep(15);
            }
            $this->checkFillLogin();
            sleep(20);
        }

        // then check user logged in or not
        if ($this->exts->querySelector($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice
            // $this->exts->openUrl($this->baseUrl);
            // sleep(15);

            //Click On each header and iind orders and download the invoice
            $this->exts->moveToElementAndClick('a[href*="meinkonto"]');
            sleep(10);
            $this->exts->moveToElementAndClick('a[href*="/abonnements"]');
            sleep(10);
            if ($this->exts->exists('a#toEdit_toEditForm_form , a#toEdit_linkForm_form')) {
                $detail_buttons = $this->exts->querySelectorAll('a#toEdit_toEditForm_form , a#toEdit_linkForm_form');
                for ($i = 0; $i < count($detail_buttons); $i++) {
                    $detail_button = $this->exts->querySelectorAll('a#toEdit_toEditForm_form , a#toEdit_linkForm_form')[$i];
                    try {
                        $this->exts->log('Click section button');
                        $detail_button->click();
                    } catch (\Exception $exception) {
                        $this->exts->log('Click section button by javascript');
                        $this->exts->executeSafeScript("arguments[0].click()", [$detail_button]);
                    }
                    sleep(10);
                    $this->exts->moveToElementAndClick('div#ORDER_accordionSection');
                    sleep(5);

                    if ($this->exts->exists('#ORDER_plenigoSnippet iframe')) {
                        $this->exts->switchToFrame('#ORDER_plenigoSnippet iframe');
                    }
                    $orders = count($this->exts->querySelectorAll('.footable-last-column a[href*="orders"]'));
                    $this->exts->log('num of oders :' . $orders);
                    for ($i = 0; $i < $orders; $i++) {

                        $order_button = $this->exts->querySelectorAll('.footable-last-column a[href*="orders"]')[$i];
                        try {
                            $this->exts->log('Click order button');
                            $order_button->click();
                        } catch (\Exception $exception) {
                            $this->exts->log('Click section button by javascript');
                            $this->exts->executeSafeScript("arguments[0].click()", [$order_button]);
                        }
                        sleep(10);
                        $this->processInvoices();

                        //clcik back button
                        if ($this->exts->exists('#ORDER_plenigoSnippet iframe')) {
                            $this->exts->switchToFrame('#ORDER_plenigoSnippet iframe');
                        }

                        $this->exts->moveToElementAndClick('.add-something a.btn-primary');
                        sleep(5);
                    }


                    sleep(5);
                    $this->exts->switchToDefault();
                    sleep(2);



                    ///////////////////////// start processing subscriptions

                    //Click subscriptions and download
                    $this->exts->moveToElementAndClick('div#SUBSCRIPTION_accordionSection');
                    if ($this->exts->exists('div#SUBSCRIPTION_plenigoSnippet iframe')) {
                        $this->exts->switchToFrame('div#SUBSCRIPTION_plenigoSnippet iframe');
                    }
                    sleep(2);
                    $subscriptions = count($this->exts->querySelectorAll('a[href*="subscriptions"],a[href*="bundles"]'));
                    for ($i = 0; $i < $subscriptions; $i++) {
                        $subscription_button = $this->exts->querySelectorAll('a[href*="subscriptions"],a[href*="bundles"]')[$i];
                        try {
                            $this->exts->log('Click section button');
                            $subscription_button->click();
                        } catch (\Exception $exception) {
                            $this->exts->log('Click section button by javascript');
                            $this->exts->executeSafeScript("arguments[0].click()", [$subscription_button]);
                        }
                        sleep(5);
                        if ($this->exts->exists('div#SUBSCRIPTION_plenigoSnippet iframe')) {
                            $this->exts->switchToFrame('div#SUBSCRIPTION_plenigoSnippet iframe');
                        }
                        sleep(2);
                        $this->exts->moveToElementAndClick('a[href="#tab-bills"]');
                        sleep(5);
                        $this->processSubscriptionInvoices();

                        //click back buton

                        if ($this->exts->exists('div#SUBSCRIPTION_plenigoSnippet iframe')) {
                            $this->exts->switchToFrame('div#SUBSCRIPTION_plenigoSnippet iframe');
                        }
                        $this->exts->moveToElementAndClick('.add-something a.btn-primary');
                        sleep(5);
                        $subscriptions = count($this->exts->querySelectorAll('a[href*="subscriptions"],a[href*="bundles"]'));
                        $this->exts->log('num of subs :' . $subscriptions);
                        sleep(5);
                    }
                    $this->exts->moveToElementAndClick('a#linkSubscriptionsselected');
                    sleep(10);
                }
            } else if ($this->exts->exists('.subscription-list a[href*="abonnements/rechnungen"]')) {
                $subscriptions = count($this->exts->querySelectorAll('.subscription-list a[href*="abonnements/rechnungen"]'));
                for ($i = 0; $i < $subscriptions; $i++) {
                    $subscription_button = $this->exts->querySelectorAll('.subscription-list a[href*="abonnements/rechnungen"]')[$i];
                    try {
                        $this->exts->log('Click section button');
                        $subscription_button->click();
                    } catch (\Exception $exception) {
                        $this->exts->log('Click section button by javascript');
                        $this->exts->executeSafeScript("arguments[0].click()", [$subscription_button]);
                    }
                    sleep(5);

                    $this->processSubscriptionInvoices();

                    //click back buton
                    $this->exts->moveToElementAndClick('a.black[href="anzeigen.html"]');
                    sleep(5);
                    $subscriptions = count($this->exts->querySelectorAll('.subscription-list a[href*="abonnements/rechnungen"]'));
                    $this->exts->log('num of subs :' . $subscriptions);
                    sleep(5);
                }
            } else {
                $this->exts->moveToElementAndClick('a[href*="rechnungen"]');
                $this->processInvoices();
            }



            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());

            $error_text = strtolower($this->exts->extract($this->check_login_failed_selector));

            $this->exts->log(__FUNCTION__ . '::Error text: ' . $error_text);
            if (stripos($error_text, strtolower('passwor')) !== false) {
                $this->exts->loginFailure(1);
            }  else {
                $this->exts->loginFailure();
            }
        }
    }

    private function checkFillLogin()
    {

        if ($this->exts->querySelector($this->username_selector) != null) {
            sleep(3);
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);
            $this->exts->moveToElementAndClick($this->submit_login_selector);
            sleep(5);
            $this->exts->waitTillPresent($this->password_selector, 15);
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(5);

            $this->exts->capture("2-login-page-filled");
            $this->exts->moveToElementAndClick($this->submit_login_selector);
            sleep(5);
            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->moveToElementAndClick($this->submit_login_selector);
                sleep(5);
            }
            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->moveToElementAndClick($this->submit_login_selector);
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function processInvoices()
    {
        $this->exts->waitTillPresent('a[href="#tab-bills"]');
        $this->exts->capture("4-invoices-page");
        $this->exts->moveToElementAndClick('a[href="#tab-bills"]');
        sleep(5);

        if ($this->exts->exists('#ORDER_plenigoSnippet iframe')) {
            $this->exts->switchToFrame('#ORDER_plenigoSnippet iframe');
        }

        $rows = count($this->exts->querySelectorAll('table > tbody > tr'));
        for ($i = 0; $i < $rows; $i++) {
            $row = $this->exts->querySelectorAll('table > tbody > tr')[$i];
            $tags = $this->exts->querySelectorAll('td', $row);
            if (count($tags) >= 5 && $this->exts->querySelector('a', $tags[4]) != null) {
                $this->isNoInvoice = false;
                $download_button = $this->exts->querySelector('a', $tags[4]);
                $invoiceDate = trim($this->getInnerTextByJS($tags[0]));
                $invoiceName = trim($this->getInnerTextByJS($tags[1]));
                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf': '';
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->getInnerTextByJS($tags[3]))) . ' EUR';

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $parsed_date = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $parsed_date);

                if ($this->exts->invoice_exists($invoiceName)) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                } else {
                    try {
                        $this->exts->log('Click download button');
                        $download_button->click();
                    } catch (\Exception $exception) {
                        $this->exts->log('Click download button by javascript');
                        $this->exts->executeSafeScript("arguments[0].click()", [$download_button]);
                    }
                    sleep(10);
                    // $this->exts->moveToElementAndClick('a[href="#tab-bills"]');
                    // sleep(5);
                    // $this->exts->moveToElementAndClick('div#tab-bills a[href*="download"]');
                    // sleep(5);
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
                    if ($this->exts->exists('#ORDER_plenigoSnippet iframe')) {
                        $this->exts->switchToFrame('#ORDER_plenigoSnippet iframe');
                    }
                    sleep(1);
                    $this->exts->moveToElementAndClick('div.add-something a[href*="order"]');
                    sleep(5);
                }
            }
        }
    }

    private function processSubscriptionInvoices()
    {
        $this->exts->waitTillPresent('table > tbody > tr');
        $this->exts->capture("4-subscription-page");
        $invoices = [];
        $paths = explode('/', $this->exts->getUrl());
        $currentDomainUrl = $paths[0] . '//' . $paths[2];
        $rows = $this->exts->querySelectorAll('table > tbody > tr');
        foreach ($rows as $row) {
            $tags = $this->exts->querySelectorAll('td', $row);
            if (count($tags) >= 5 && $this->exts->querySelector('a[href*="billings"]', $tags[4]) != null) {
                $invoiceUrl = $this->exts->querySelector('a[href*="billings"]', $tags[4])->getAttribute("href");
                if (strpos($invoiceUrl, $currentDomainUrl) === false && strpos($invoiceUrl, 'http') === false) {
                    $invoiceUrl = $currentDomainUrl . $invoiceUrl;
                }
                $invoiceName = trim($this->getInnerTextByJS($tags[1]));
                $invoiceDate = trim($this->getInnerTextByJS($tags[0]));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->getInnerTextByJS($tags[3]))) . ' EUR';

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl
                ));
                $this->isNoInvoice = false;
            } else if (count($tags) >= 5 && $this->exts->querySelector('a[href*="dnt_downloadInvoiceId"]', $tags[4]) != null) {
                $invoiceUrl = $this->exts->querySelector('a[href*="dnt_downloadInvoiceId"]', $tags[4])->getAttribute("href");
                if (strpos($invoiceUrl, $currentDomainUrl) === false && strpos($invoiceUrl, 'http') === false) {
                    $invoiceUrl = $currentDomainUrl . $invoiceUrl;
                }
                $invoiceName = trim($this->getInnerTextByJS($tags[1]));
                $invoiceDate = trim($this->getInnerTextByJS($tags[0]));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->getInnerTextByJS($tags[3]))) . ' EUR';

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
        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName']  . '.pdf': '';
            $date_parsed = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
            if ($date_parsed == '') {
                $date_parsed = $this->exts->parse_date($invoice['invoiceDate'], 'M d, Y, H:i:s A', 'Y-m-d');
            }

            $this->exts->log('Date parsed: ' . $date_parsed);


            $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $date_parsed, $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
    }

    function getInnerTextByJS($selector_or_object, $parent = null)
    {
        if ($selector_or_object == null) {
            $this->exts->log(__FUNCTION__ . ' Can not get innerText of null');
            return;
        }
        $element = $selector_or_object;
        if (is_string($selector_or_object)) {
            $element = $this->exts->getElement($selector_or_object, $parent);
            if ($element == null) {
                $element = $this->exts->getElement($selector_or_object, $parent, 'xpath');
            }
            if ($element == null) {
                $this->exts->log(__FUNCTION__ . ':: Can not found element with selector/xpath: ' . $selector_or_object);
            }
        }
        if ($element != null) {
            return $this->exts->executeSafeScript("return arguments[0].innerText", [$element]);
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
