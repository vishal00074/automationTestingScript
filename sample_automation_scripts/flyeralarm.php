<?php // updated login success logic

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

    // Server-Portal-ID: 2239 - Last modified: 25.04.2025 13:19:53 UTC - User: 1

    public $baseUrl = 'https://www.flyeralarm.com/de/shop/customer/orders';
    public $username_selector = 'input[name="username"]';
    public $password_selector = 'input[name="password"]';
    public $remember_me_selector = 'input[name="rememberMe"]:not(:checked) + label';
    public $submit_login_selector = 'button[type="submit"]';
    public $check_login_success_selector = '.simplesubmenu a[href*="/index/logout"], a[href*="/logout"]';

    public $isNoInvoice = true;
    public $download_overview = 0;
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
        sleep(5);
        $this->exts->waitTillAnyPresent([$this->username_selector, $this->check_login_success_selector]);
        $this->exts->capture('1-init-page');

        $this->download_overview = isset($this->exts->config_array["download_overview"]) ? (int) $this->exts->config_array["download_overview"] : 0;

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if (count($this->exts->getElements($this->check_login_success_selector)) == 0) {
            $this->exts->log('NOT logged via cookie');
            $this->checkFillLogin();
            sleep(30);
        }
        if ($this->exts->exists('button.iubenda-cs-accept-btn')) {
            $this->exts->moveToElementAndClick('button.iubenda-cs-accept-btn');
            sleep(10);
        }

        // then check user logged in or not
        if (count($this->exts->getElements($this->check_login_success_selector)) > 0) {
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice
            if ($this->download_overview == 0) {
                $this->exts->openUrl($this->baseUrl);
                $this->processInvoices();
            }
            $this->exts->openUrl('https://www.flyeralarm.com/de/customer-account/orders');
            sleep(5);
            $this->processOrders();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());
            if (stripos($this->exts->extract('.callout.alert'), 'Passwor') !== false) {
                $this->exts->loginFailure(1);
            } else if ($this->exts->exists('#customerForm #submitCustomerData')) {
                $this->exts->account_not_ready();
            } else {
                $this->exts->loginFailure();
            }
        }
    }
    private function checkFillLogin()
    {
        if ($this->exts->querySelector($this->password_selector) != null) {
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(3);

            $this->exts->log("Enter Password");
            $this->exts->click_by_xdotool($this->password_selector);
            sleep(2);
            $this->exts->type_text_by_xdotool($this->password);
            // $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            if ($this->remember_me_selector != '')
                $this->exts->moveToElementAndClick($this->remember_me_selector);
            sleep(1);

            $this->exts->capture("2-login-page-filled");
            $this->exts->moveToElementAndClick($this->submit_login_selector);
            sleep(3);
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function processInvoices()
    {
        $this->exts->waitTillPresent('tr.orderRow.orderListHeader', 20);
        $this->exts->capture("4-order-page");
        for ($paging_count = 1; $paging_count < 100; $paging_count++) {
            $invoices = [];
            $rows = $this->exts->querySelectorAll('tr.orderRow.orderListHeader');
            foreach ($rows as $row) {
                $order_detail_link = $this->exts->querySelector('a[href*="/orderprint/orderId/"]', $row);
                if ($order_detail_link != null) {
                    $order_url = $order_detail_link->getAttribute("href");
                    if (stripos($order_url, 'https://www.flyeralarm.com') === false) {
                        $order_url = 'https://www.flyeralarm.com' . $order_url;
                    }
                    $invoiceName = explode(
                        '?',
                        end(explode('/orderId/', $order_url))
                    )[0];
                    $invoiceName = explode('/', $invoiceName)[0];
                    $invoiceDate = '';
                    $invoiceAmount = preg_replace('/[^\d\.\,]/', '', $this->exts->extract('td:nth-child(2)')) . ' EUR';

                    array_push($invoices, array(
                        'invoiceName' => $invoiceName,
                        'invoiceDate' => $invoiceDate,
                        'invoiceAmount' => $invoiceAmount,
                        'order_url' => $order_url
                    ));
                    $this->isNoInvoice = false;
                }
            }

            // Download all invoices
            $this->exts->log('Order found: ' . count($invoices));
            $newTab = $this->exts->openNewTab();
            foreach ($invoices as $invoice) {
                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
                $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
                $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
                $this->exts->log('order_url: ' . $invoice['order_url']);

                $this->exts->openUrl($invoice['order_url']);
                $this->exts->waitTillPresent('span[onclick*="window.print"]');
                sleep(1);
                $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf': '';
                $downloaded_file = $this->exts->click_and_print('span[onclick*="window.print"]', $invoiceFileName);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $downloaded_file);
                    sleep(1);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            }
            $this->exts->closeTab($newTab);

            if ($this->exts->config_array["restrictPages"] == '0' && $this->exts->exists('#shop-ordertable-paginator .shop-ordertable-paginator-currentPage + span a[href*="/pageCurrent"]')) {
                $this->exts->moveToElementAndClick('#shop-ordertable-paginator .shop-ordertable-paginator-currentPage + span a[href*="/pageCurrent"]');
                sleep(10);
            } else {
                break;
            }
        }
    }

    private function processOrders($paging_count = 1)
    {
        $this->exts->waitTillPresent('div.card a[href*="/invoice/"][href*="displayorderinvoice"], div.card a[href*="invoice/download?orderId="], div.card a[href*="orderprint/orderId/"]');
        $this->exts->capture("4-Orders-page");
        $invoices = [];
        $order_details_link = false;
        $rows = $this->exts->querySelectorAll('div.card');
        foreach ($rows as $row) {
            sleep(2);
            if ($this->exts->querySelector('a[href*="/invoice/"][href*="displayorderinvoice"]', $row) != null) {
                $invoiceUrl = $this->exts->querySelector('a[href*="/invoice/"][href*="displayorderinvoice"]', $row)->getAttribute("href");
                $temp = $this->exts->extract('a[href*="orderprint/orderId/"]', $row, 'href');
                $this->exts->log($temp);
                $invoiceName = explode(
                    '/',
                    array_pop(explode('/orderId/', $temp))
                )[0];
                $invoiceDate = '';
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->exts->extract('div.text-right p', $row, 'text'))) . ' EUR';

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl
                ));
                $this->isNoInvoice = false;
            } else if ($this->exts->querySelector('a[href*="invoice/download?orderId="]', $row) != null) {
                $invoiceUrl = $this->exts->querySelector('a[href*="invoice/download?orderId="]', $row)->getAttribute("href");
                $temp = $this->exts->extract('a[href*="invoice/download?orderId="]', $row, 'href');
                $this->exts->log($temp);
                $invoiceName = explode(
                    '%',
                    array_pop(explode('orderId=', $temp))
                )[0];
                $invoiceName = explode('/', $invoiceName)[0];
                $invoiceDate = '';
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->exts->extract('div.text-right p', $row, 'text'))) . ' EUR';

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl
                ));
                $this->isNoInvoice = false;
            } else if ($this->exts->querySelector('a[href*="orderprint/orderId/"]', $row) != null) {
                $temp = $this->exts->extract('a[href*="orderprint/orderId/"]', $row, 'href');
                $this->exts->log($temp);
                $invoiceName = explode(
                    '?',
                    array_pop(explode('orderId/', $temp))
                )[0];
                $modalBtn = $this->exts->querySelector('a[href*="invoices-modal"]', $row);
                if ($modalBtn) {
                    $this->exts->execute_javascript("arguments[0].click();", [$modalBtn]);
                    $this->exts->waitTillPresent('a[href*="api/invoice"]');
                    if ($this->exts->exists('a[href*="api/invoice"]')) {
                        sleep(2);
                        $invoiceUrl = $this->exts->querySelector('a[href*="api/invoice"]')->getAttribute('href');
                        // close modal
                        if ($this->exts->exists('div[class*=overlay] button.close-button')) {
                            $this->exts->log('Clicking on Modal Close button');
                            $this->exts->click_element('div[class*=overlay] button.close-button');
                        }
                        sleep(2);
                        $invoiceName = explode('/', $invoiceName)[0];
                        $invoiceDate = '';
                        $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->exts->extract('div.medium-shrink:not(.text-right) p.-text-sub span', $row))) . ' EUR';
                        $order_details_link = false;
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
        }

        // Download all invoices
        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf': '';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);
            if ($order_details_link) {
                $newTab = $this->exts->openNewTab();
                sleep(1);

                if ($this->exts->invoice_exists($invoice['invoiceName'])) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                } else {
                    $this->exts->openUrl($invoice['invoiceUrl']);
                    sleep(8);
                    $this->exts->moveToElementAndClick('span[onclick="window.print();"]');
                    sleep(5);
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
                }
                $this->exts->closeTab($newTab);
            } else {
                $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            }
        }

        // next page
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;
        if (
            $restrictPages == 0 &&
            $paging_count < 50 &&
            $this->exts->querySelector('li.pagination-next a:not(.disabled)') != null
        ) {
            $paging_count++;
            $this->exts->moveToElementAndClick('li.pagination-next a:not(.disabled)');
            sleep(5);
            $this->processOrders($paging_count);
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
