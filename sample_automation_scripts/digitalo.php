<?php // updated button selector in process inovice code and uncommented loadCookiesFromFile

/**
 * Chrome Remote via Chrome devtool protocol script, for specific process/portal
 *
 * @package	uwa
 *
 * @copyright	GetMyInvoices
 */

define('KERNEL_ROOT', '/var/www/vhosts/worker/httpdocs/src/');

$gmi_selenium_core = realpath(KERNEL_ROOT . 'modules/cust_gmi_worker/includes/GmiChromeManager.php');
require_once($gmi_selenium_core);

class PortalScriptCDP
{

    private    $exts;
    public    $setupSuccess = false;
    private $chrome_manage;
    private    $username;
    private    $password;
    public $support_restart = true;
    public $portal_domain = '';

    public function __construct($mode, $portal_name, $process_uid, $username, $password)
    {

        $this->username = base64_decode($username);
        $this->password = base64_decode($password);

        $this->exts = new GmiChromeManager();
        $this->exts->screen_capture_location = '/var/www/vhosts/worker/httpdocs/fs/cdo/process/2/2673461/screens/';
        $this->exts->init($mode, $portal_name, $process_uid, $this->username, $this->password);
        $this->setupSuccess = true;
        if (!empty($this->exts->config_array['portal_domain'])) {
            $this->portal_domain = $this->exts->config_array['portal_domain'];
        }
    }

    /**
     * Method that called first for executing portal script, this method should not be altered by Users.
     */
    public function run()
    {
        if ($this->setupSuccess) {
            file_put_contents($this->exts->screen_capture_location . '.script_execution_started', time());
            try {
                // Start portal script execution
                $this->initPortal(0);

                $this->exts->dump_session_files();
            } catch (\Exception $exception) {
                $this->exts->log('Selenium Exception: ' . $exception->getMessage());
                $this->exts->capture("error");
                var_dump($exception);
            }

            $this->exts->log('Execution completed');

            $this->exts->process_completed();
        } else {
            echo 'Script execution failed.. ' . "\n";
        }
    }

    // Server-Portal-ID: 8666 - Last modified: 29.04.2025 14:39:07 UTC - User: 1

    /*Define constants used in script*/

    public $baseUrl = 'https://www.digitalo.de/my/orders.html';
    public $loginUrl = 'https://www.digitalo.de/my/orders.html';
    public $invoicePageUrl = 'https://www.digitalo.de/my/orders.html';

    public $username_selector = 'input#auth_login_email, form#login_form input[type="email"]';
    public $password_selector = 'input#auth_login_password, form#login_form input[name="password_login"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'button[name="auth_submit"], form#login_form button[name="btn_login"]';

    public $check_login_failed_selector = '.message_stack .callout--alert';
    public $check_login_success_selector = 'a[href*="/user/logoff.html"], a[href*="/auth/logout.html"]';

    public $isNoInvoice = true;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->openUrl($this->baseUrl);
        sleep(5);

        // Load cookies
        $this->exts->loadCookiesFromFile();
        // sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        $this->exts->capture('1-init-page');
        if ($this->exts->exists('a[href*="/myracloud-captcha"]')) {
            $this->exts->moveToElementAndClick('a[href*="/myracloud-captcha"]');
            sleep(10);
            if ($this->exts->exists('img#captcha_image')) {
                $this->exts->processCaptcha('img#captcha_image', 'input#captcha_code');
                sleep(8);
                $this->exts->moveToElementAndClick('input[name="submit"]');
                sleep(3);
            }
        }
        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->openUrl($this->loginUrl);
            sleep(15);
            if ($this->exts->exists('a[href*="/myracloud-captcha"]')) {
                $this->exts->moveToElementAndClick('a[href*="/myracloud-captcha"]');
                sleep(10);
                if ($this->exts->exists('img#captcha_image')) {
                    $this->exts->processCaptcha('img#captcha_image', 'input#captcha_code');
                    sleep(8);
                    $this->exts->moveToElementAndClick('input[name="submit"]');
                    sleep(10);
                }
            }
            if ($this->exts->exists('button.js_button_cookie_consent, span.btn.btn-cookie-consent')) {
                $this->exts->moveToElementAndClick('button.js_button_cookie_consent, span.btn.btn-cookie-consent');
                sleep(5);
            }
            if ($this->exts->exists('button[data-cookie_consent="1"]')) {
                $this->exts->moveToElementAndClick('button[data-cookie_consent="1"]');
                sleep(5);
            }
            if ($this->exts->exists('div[data-toggle="js_login_pane"], button[data-toggle="js_login_pane"]')) {
                $this->exts->moveToElementAndClick('div[data-toggle="js_login_pane"], button[data-toggle="js_login_pane"]');
                sleep(5);
            }
            $this->checkFillLogin();
            sleep(20);
        }

        if ($this->exts->exists('#js_button_security_score_dont_show_again')) {
            $this->exts->click_element('#js_button_security_score_dont_show_again');
            sleep(5);
        }

        if ($this->exts->exists('button[id="js_my_navigation"]')) {
            $this->exts->click_element('button[id="js_my_navigation"]');
            sleep(5);
        }

        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(15);
            if ($this->exts->exists('div.order-content .custom-accordion-divider')) {
                $this->processOrders();
            } else {
                $this->processInvoices();
            }
            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            if (strpos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'passwor') !== false) {
                $this->exts->loginFailure(1);
            } else {
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

            if ($this->remember_me_selector != '')
                $this->exts->moveToElementAndClick($this->remember_me_selector);
            sleep(2);

            $this->exts->capture("2-login-page-filled");
            $this->exts->moveToElementAndClick($this->submit_login_selector);
            sleep(5);
            if ($this->exts->exists($this->submit_login_selector) && strpos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'passwor') == false) {
                $submit_btn = $this->exts->getElement($this->submit_login_selector);
                try {
                    $this->exts->log('Click submit button');
                    $submit_btn->click();
                } catch (\Exception $exception) {
                    $this->exts->log('Click submit button by javascript');
                    $this->exts->execute_javascript("arguments[0].click()", [$submit_btn]);
                }
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function processOrders($paging_count = 1)
    {
        sleep(25);

        $this->exts->capture("4-orders-page");
        $orders = [];

        //other UI
        $rows = $this->exts->getElements('div .column a[href*="/my/orders_details.html?oid="][href*="#go_to_supporting_documents"]');
        foreach ($rows as $row) {

            $orderUrl = $row->getAttribute("href");

            array_push($orders, array(
                'orderUrl' => $orderUrl
            ));
        }
        $this->exts->log('Invoices found: ' . count($orders));
        foreach ($orders as $order) {
            $newTab = $this->exts->openNewTab();
            sleep(1);
            $this->exts->openUrl($order['orderUrl']);
            sleep(10);
            $this->processInvoices();
            sleep(1);
            $this->exts->closeTab($newTab);
            sleep(1);
        }
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        if (
            $restrictPages == 0 &&
            $paging_count < 50 &&
            $this->exts->getElement('span.current + a.paginate') != null
        ) {
            $paging_count++;
            $this->exts->moveToElementAndClick('span.current + a.paginate');
            sleep(5);
            $this->processOrders($paging_count);
        }
    }

    private function processInvoices($paging_count = 1)
    {
        sleep(25);

        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->getElements('div.js_order_box');
        foreach ($rows as $row) {
            try {
                $this->exts->log('Click row');
                $row->click();
            } catch (\Exception $exception) {
                $this->exts->log('Click row by javascript');
                $this->exts->execute_javascript("arguments[0].click()", [$row]);
            }
            sleep(5);
            $tags = $this->exts->getElements('.expandable_box__title__item__attribute--bold', $row);
            if (count($tags) >= 4 && $this->exts->getElement('a[href*="/invoice.html?id="]', $row) != null) {
                $invoiceUrl = $this->exts->getElement('a[href*="/invoice.html?id="]', $row)->getAttribute("href");
                $invoiceName = array_pop(explode('invoice.html?id=', $invoiceUrl));
                $invoiceDate = trim($tags[0]->getAttribute('innerText'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[3]->getAttribute('innerText'))) . ' EUR';

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl
                ));
                $this->isNoInvoice = false;
            }
        }

        $rows = $this->exts->getElements('.js_order_content div.expandable_box__content');
        foreach ($rows as $row) {
            if ($this->exts->getElement('a[href*="/invoice.html?id="]', $row) != null) {
                $invoiceUrl = $this->exts->getElement('a[href*="/invoice.html?id="]', $row)->getAttribute("href");
                $invoiceName = array_pop(explode('invoice.html?id=', $invoiceUrl));
                $invoiceDate = '';
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->exts->extract('.my_orders__order_sum__price--ot_total', $row, 'innerText'))) . ' EUR';

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl
                ));
                $this->isNoInvoice = false;
            }
        }

        $rows = $this->exts->getElements('.js_order_content');
        foreach ($rows as $row) {
            $dropdownBtn = $this->exts->querySelector('a[aria-expanded="false"]', $row);
            try {
                $this->exts->log('Click row');
                $dropdownBtn->click();
            } catch (\Exception $exception) {
                $this->exts->log('Click row by javascript');
                $this->exts->execute_javascript("arguments[0].click()", [$dropdownBtn]);
            }
            sleep(4);
            if ($this->exts->getElement('.my_orders__documents a[href*="/invoice.html"]', $row) != null) {
                $invoiceUrl = '';
                $invoiceName = $this->exts->extract('.my_orders__documents table tbody tr td:nth-child(1)', $row);
                $invoiceDate = $this->exts->extract('.my_orders__documents table tbody tr td:nth-child(2)', $row);
                $invoiceAmount = $this->exts->extract('.my_orders__documents table tbody tr td:nth-child(4)', $row);
                $downloadBtn = $this->exts->querySelector('.my_orders__documents a[href*="/invoice.html"]', $row);

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

        //other UI
        $rows = $this->exts->getElements('#go_to_supporting_documents table.full > tbody  > tr');
        foreach ($rows as $row) {
            $tags = $this->exts->getElements('td', $row);
            if (count($tags) >= 6 && $this->exts->getElement('a[href*="/invoice.html?id="]', $tabs[5]) != null) {
                $invoiceUrl = $this->exts->getElement('a[href*="/invoice.html?id="]', $tabs[5])->getAttribute("href");
                $invoiceName = trim($tags[0]->getAttribute('innerText'));
                $invoiceDate = trim($tags[1]->getAttribute('innerText'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[3]->getAttribute('innerText'))) . ' EUR';

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

            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : "";
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            if ($invoice['invoiceUrl'] != null) {
                $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
            } else {
                $downloaded_file = $this->exts->click_and_download($invoice['downloadBtn'], 'pdf', $invoiceFileName);
            }
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
        $nextPageBtn = $this->exts->querySelector('.pagination button svg.chevron-right');
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        if ($restrictPages == 0 && $paging_count < 50 && $nextPageBtn != null) {
            $paging_count++;
            $this->exts->click_element($nextPageBtn);
            sleep(5);
            $this->processInvoices($paging_count);
        } else if ($restrictPages > 0 && $paging_count < $restrictPages && $nextPageBtn != null) {
            $paging_count++;
            $this->exts->click_element($nextPageBtn);
            sleep(5);
            $this->processInvoices($paging_count);
        }
    }
}


$portal = new PortalScriptCDP("optimized-chrome-v2", 'digitalo', '2673629', 'ZWlua2F1ZkBteXBjLmRl', 'I25GcTBZVFZDdDUjRk8qUg==');
$portal->run();
