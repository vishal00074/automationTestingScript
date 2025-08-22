<?php // updated loginfailedCOnfirmed message and selector and remove unsed processInvoices function  

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
        $this->exts->screen_capture_location = '/var/www/vhosts/worker/httpdocs/fs/cdo/process/2/2673830/screens/';
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
                // load cookies from file for desktop app
                if (!empty($this->exts->config_array["without_password"])) {
                    $this->exts->loadCookiesFromFile();
                }
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

    // Server-Portal-ID: 87472 - Last modified: 17.06.2025 14:42:09 UTC - User: 1

    public $baseUrl = 'https://www.gastro-hero.de';
    public $loginUrl = 'https://www.gastro-hero.de/hilfe-service';
    public $invoicePageUrl = 'https://www.gastro-hero.de/sales/order/history/';

    public $username_selector = 'input[name="email"]';
    public $password_selector = 'input[name="password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'button[data-testid="loginSubmit"]';

    public $check_login_failed_selector = '[data-testid="notificationMessage"]';
    public $check_login_success_selector = 'div[data-testid="megaNavigationAccount"] .gh-user-avatar';

    public $isNoInvoice = true;
    public $restrictPages = 3;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->openUrl($this->baseUrl);
        sleep(1);
        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        $this->exts->capture('1-init-page');
        sleep(5);
        $this->exts->openUrl('https://www.gastro-hero.de/customer/account');
        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if (!$this->checkLoggedIn()) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();

            $this->exts->openUrl($this->loginUrl);
            sleep(15);
            $this->exts->moveToElementAndClick('div.account-menu.hidden-md > div:nth-child(3) > button, div.account-menu > div:nth-child(3) > button');
            sleep(10);
            $button_cookie = $this->exts->execute_javascript('document.querySelector("#usercentrics-root").shadowRoot.querySelector(\'button[data-testid="uc-accept-all-button"]\')');
            if ($button_cookie != null) {
                $this->exts->execute_javascript("arguments[0].click()", [$button_cookie]);
                sleep(5);
            }
            $this->exts->moveToElementAndClick('[data-testid="megaNavigationAccount"] .account-icon div');
            sleep(10);
            $this->checkFillLogin();
            sleep(20);
        }

        if ($this->checkLoggedIn()) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice
            // $this->exts->openUrl($this->invoicePageUrl);
            $this->exts->moveToElementAndClick('div[data-testid="megaNavigationAccount"] .account-icon');
            sleep(10);
            $this->exts->moveToElementAndClick('.account__menu a[href="/sales/order/history"]');
            sleep(15);
            $this->processInvoicesPage();

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
            for ($i = 0; $i < 10 && $this->exts->getElement('[data-testid="notificationMessage"], [class="notifications fixed"]') == null; $i++) {
                sleep(1);
            }
            if ($this->exts->exists('[data-testid="notificationMessage"], [class="notifications fixed"]')) {
                $error_message = $this->exts->extract('[data-testid="notificationMessage"], [class="notifications fixed"]');
                $this->exts->log("Login Failure : " . $error_message);
                if (
                    strpos(strtolower($error_message), 'passwor') !== false ||
                    strpos(strtolower($error_message), 'account ist vorübergehend nicht') !== false
                ) {
                    $this->exts->loginFailure(1);
                }
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function checkLoggedIn()
    {
        // $this->exts->openUrl('https://www.gastro-hero.de/customer/account');
        sleep(10);
        $LoggedIn = false;
        if ($this->exts->exists($this->check_login_success_selector)) {
            $LoggedIn = true;
        }
        return $LoggedIn;
    }

    private function processInvoicesPage()
    {
        $urls = [];
        $articles = $this->exts->getElements('article[data-tracking="order-history.order-details"]');
        foreach ($articles as $art) {
            $date = $this->exts->extract('[class*=order__order-date] span', $art, 'innerText');
            $parseDate = $this->exts->parse_date($date, 'd.m.Y', 'Y-m-d');

            $maxBackDate = date('Y-m-d', strtotime('-3 years'));
            if ($this->restrictPages == 3) {
                $maxBackDate = date('Y-m-d', strtotime('-2 year'));
            }
            $this->exts->log('Date parsed: ' . $parseDate);
            $this->exts->log('maxBackDate: ' . $maxBackDate);
            if ($parseDate >= $maxBackDate) {
                $this->exts->execute_javascript("arguments[0].setAttribute('article-selected','true');", [$art]);
            }
        }
        $url_invoices_page = $this->exts->getElements('article[article-selected] a[href*=order_id][data-tracking*="order-details"]');
        foreach ($url_invoices_page as $u) {
            $url = $u->getAttribute('href');
            array_push($urls, array(
                'url' => $url
            ));
        }
        $this->exts->log('URLs: ' . count($urls));
        foreach ($urls as $u) {
            $this->exts->openUrl($u['url']);
            sleep(3);
            $this->processInvoicesNew();
        }
    }

    private function processInvoicesNew()
    {
        sleep(15);
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = count($this->exts->getElements('#my-account-order-documents-invoices table > tbody > tr'));
        for ($i = 0; $i < $rows; $i++) {
            $row = $this->exts->getElements('#my-account-order-documents-invoices table > tbody > tr')[$i];
            $tags = $this->exts->getElements('td', $row);
            if (count($tags) >= 3 && $this->exts->getElement('td[data-label="Download"]', $row) != null) {
                $download_button = $this->exts->getElement('a', $tags[2]);
                $invoiceName = $tags[0]->getAttribute('innerText');
                $invoiceDate = $tags[1]->getAttribute('innerText');
                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf': '';
                $this->isNoInvoice = false;

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $parsed_date = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $parsed_date);

                // Download invoice if it not exisited
                if ($this->exts->invoice_exists($invoiceName)) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                } else {
                    // Click detail 
                    try {
                        $this->exts->log('Click download button');
                        $download_button->click();
                    } catch (\Exception $exception) {
                        $this->exts->log('Click download button by javascript');
                        $this->exts->execute_javascript("arguments[0].click()", [$download_button]);
                    }

                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, $parsed_date, '', $invoiceFileName);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
                }
                break;
            }
        }
    }
}

$portal = new PortalScriptCDP("optimized-chrome-v2", 'GastroHero', '2673830', 'cmVjaG51bmdlbkBsdXVjLWV2ZW50LmRl', 'I1R1ZXJrZW5zdHJhc3NlMTc=');
$portal->run();
