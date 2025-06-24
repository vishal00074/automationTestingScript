<?php // updated empty invoice name updated invoice url and download code.

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

    // Server-Portal-ID: 122324 - Last modified: 24.06.2025 01:21:20 UTC - User: 1

    public $baseUrl = 'http://client.monoprix.fr/monoprix-shopping/commandes';
    public $loginUrl = 'https://www.monoprix.fr/login';
    public $invoicePageUrl = 'http://client.monoprix.fr/monoprix-shopping/commandes';

    public $username_selector = '.login-form input[name="email"], input[name="email"]';
    public $password_selector = '.login-form input[name="password"], input[name="password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = '.login-form button[type="submit"], button[type="submit"]';

    public $check_login_failed_selector = 'span#password-error-description';
    public $check_login_success_selector = 'button.user-menu__logout-button, li.ProfileNavSide_nav-elem__iyH1l'; //code update

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
        if ($this->exts->exists('a[href="/mon-profil"],, a[href="/monoprix-shopping/home"]')) {
            $this->exts->moveToElementAndClick('a[href="/mon-profil"], a[href="/monoprix-shopping/home"]');
            sleep(15);
        }
        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            sleep(15);
            if ($this->exts->exists('button#onetrust-accept-btn-handler')) {
                $this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler');
                sleep(5);
            }
            $this->checkFillLogin();
            sleep(20);
            if ($this->exts->exists('a[href="/mon-profil"], a[href="/monoprix-shopping/home"]')) {
                $this->exts->moveToElementAndClick('a[href="/mon-profil"], a[href="/monoprix-shopping/home"]');
                sleep(15);
            }
            if ($this->exts->exists('.error.continue-shopping')) {
                $this->exts->moveToElementAndClick('.error.continue-shopping');
                sleep(15);
            }
            if ($this->exts->exists('a[href="/mon-profil"], a[href="/monoprix-shopping/home"]')) {
                $this->exts->moveToElementAndClick('a[href="/mon-profil"], a[href="/monoprix-shopping/home"]');
                sleep(15);
            }
        }
        if ($this->exts->getElement($this->check_login_success_selector) != null || $this->exts->getElement('a[href="/monoprix-shopping/commandes"]') != null) { //code update
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice - this is for shopping orders
            $this->exts->openUrl($this->invoicePageUrl);
            $this->processInvoices();

            //Download course orders also
            if ($this->exts->getElement('//button[contains(text(),"Courses")]', null, 'xpath') != null) {
                $this->click_element('//button[contains(text(),"Courses")]');
                $this->processInvoices('course');
            }
            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            if (strpos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'mot de passe invalide') !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function click_element($selector_or_object)
    {
        if ($selector_or_object == null) {
            $this->exts->log(__FUNCTION__ . ' Can not click null');
            return;
        }

        $element = $selector_or_object;
        if (is_string($selector_or_object)) {
            $this->exts->log(__FUNCTION__ . '::Click selector: ' . $selector_or_object);
            $element = $this->exts->getElement($selector_or_object);
            if ($element == null) {
                $element = $this->exts->getElement($selector_or_object, null, 'xpath');
            }
            if ($element == null) {
                $this->exts->log(__FUNCTION__ . ':: Can not found element with selector/xpath: ' . $selector_or_object);
            }
        }
        if ($element != null) {
            try {
                $this->exts->log(__FUNCTION__ . ' trigger click.');
                $element->click();
            } catch (\Exception $exception) {
                $this->exts->log(__FUNCTION__ . ' by javascript');
                $this->exts->execute_javascript("arguments[0].click()", [$element]);
            }
        }
    }

    private function checkFillLogin()
    {
        if ($this->exts->getElement($this->username_selector) != null) {
            sleep(3);
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);
            $this->exts->moveToElementAndClick($this->submit_login_selector);
            sleep(6);
            for ($i = 0; $i < 8 && !$this->exts->exists($this->password_selector); $i++) {
                $this->exts->moveToElementAndClick($this->submit_login_selector);
                sleep(6);
            }
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
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


    private function processInvoices($orderType = 'shopping')
    {
        sleep(15);

        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->getElements('section .p-4.relative');

        foreach ($rows as $key => $row) {
            $invoiceUrl = '';
            $string = $this->exts->extract('div > p:nth-child(2)', $row);
            $invoiceName = '';
            preg_match('/n°(\d+)/', $string, $matches);
            if (isset($matches[1])) {
                $invoiceName  = trim($matches[1]); // 1000215862110

            }
            $invoiceDate = $this->exts->extract('td:nth-child(1)', $row);
            $invoiceAmount = $this->exts->extract('td:nth-child(4)', $row);

            array_push($invoices, array(
                'invoiceName' => $invoiceName,
                'invoiceDate' => $invoiceDate,
                'invoiceAmount' => $invoiceAmount,
                'invoiceUrl' => $invoiceUrl,
            ));
            $this->isNoInvoice = false;
        }

        $this->exts->log('Invoices found: '. $orderType .' '. count($invoices));

        foreach ($invoices as $invoice) {
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            

            $invoiceFileName = !empty($invoice['invoiceName']) ?  $invoice['invoiceName'] . '.pdf' : '';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);
            if ($orderType == 'shopping') {
                $url = 'https://shopping.monoprix.fr/orders/' . $invoice['invoiceName'] . '/details';
            } else {
                $url = 'https://courses.monoprix.fr/orders/' . $invoice['invoiceName'] . '/details';
            }
            $this->exts->log('invoiceUrl: ' . $url);
            $this->exts->openUrl($url);
            sleep(10);
            if (!$this->exts->exists('a[data-test="order-receipt-button"]')) {
                sleep(5);
            }
            if ($this->exts->exists('a[data-test="order-receipt-button"]')) {
                $this->exts->moveToElementAndClick('a[data-test="order-receipt-button"]');
                sleep(5);
                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            }
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
