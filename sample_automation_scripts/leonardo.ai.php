<?php // updated exists with isExist updated login code added cloudflare code and download code

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

    // Server-Portal-ID: 2175545 - Last modified: 05.07.2025 18:50:54 UTC - User: 1

    public $baseUrl = "https://app.leonardo.ai/";
    public $loginUrl = "https://app.leonardo.ai/auth/login";
    public $invoicePageUrl = 'https://app.leonardo.ai/buy';
    public $username_selector = 'input[type="email"]';
    public $password_selector = 'input[type="password"]';
    public $submit_button_selector = 'button[type="submit"]';
    public $check_login_failed_selector = 'div[class="chakra-stack css-q5helz"] > p';
    public $check_login_success_selector = 'a[href="/settings"]';
    public $login_tryout = 0;
    public $isNoInvoice = true;
    /**

     * Entry Method thats called for a portal

     * @param Integer $count Number of times portal is retried.

     */

    private function initPortal($count)
    {

        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->loadCookiesFromFile();
        $this->exts->openUrl($this->baseUrl);
        $this->acceptCookies();
        $this->solveCAPTCHA();
        if ($this->isExists('button.chakra-modal__close-btn')) {
            $this->exts->moveToElementAndClick('button.chakra-modal__close-btn');
        }
        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            $this->acceptCookies();
            $this->fillForm(0);
        }
        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            if ($this->isExists('button.chakra-modal__close-btn ')) {
                $this->exts->moveToElementAndClick('button.chakra-modal__close-btn');
            }
            $this->exts->openUrl($this->invoicePageUrl);
            $this->processAllInvoices();
            $this->exts->success();
        } else {
            // $this->exts->waitTillPresent($this->check_login_failed_selector, 20);
            if ($this->isExists($this->check_login_failed_selector)) {
                $this->exts->log("Wrong credential !!!!");
                $this->exts->loginFailure(1);
            } else {
                $this->exts->log("Failed due to unknown reasons");
                $this->exts->loginFailure();
            }
        }
    }

    public function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        // $this->exts->waitTillPresent($this->username_selector, 20);
        for ($i = 0; $i < 10; $i++) {
            if ($this->isExists($this->username_selector)) {
                break;
            }
            sleep(2);
        }
        try {
            if ($this->exts->querySelector($this->username_selector) != null) {

                $this->login_tryout = (int)$this->login_tryout + 1;
                $this->exts->capture("1-pre-login");
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                $this->solveCAPTCHA();
                sleep(5);
                $this->exts->click_element($this->submit_button_selector);
                sleep(7); // Portal itself has one second delay after showing toast
            }

            if ($this->exts->querySelector($this->username_selector) != null) {
                $this->check_solve_cloudflare_page();

                $this->exts->click_element($this->submit_button_selector);
                sleep(7);
            }

            // $this->exts->waitTillPresent($this->password_selector, 20);
            for ($i = 0; $i < 10; $i++) {
                if ($this->isExists($this->password_selector)) {
                    break;
                }
                sleep(2);
            }

            if ($this->exts->querySelector($this->password_selector) != null) {

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                $this->solveCAPTCHA();
                sleep(5);
                $this->exts->click_element($this->submit_button_selector);
                sleep(7); // Portal itself has one second delay after showing toast


                if ($this->exts->querySelector($this->password_selector) != null) {
                    $this->check_solve_cloudflare_page();

                    $this->exts->click_element($this->submit_button_selector);
                    sleep(7);
                }
            } else {
                $this->exts->log('Failed due to unknown reasons');
                $this->exts->loginFailure();
            }
        } catch (\Exception $exception) {

            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }

    private function isExists($selector = '')
    {
        $safeSelector = addslashes($selector);
        $this->exts->log('Element:: ' . $safeSelector);
        $isElement = $this->exts->execute_javascript('!!document.querySelector("' . $safeSelector . '")');
        if ($isElement) {
            $this->exts->log('Element Found');
            return true;
        } else {
            $this->exts->log('Element not Found');
            return false;
        }
    }

    private function check_solve_cloudflare_page()
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
            sleep(15);

            // $this->exts->waitTillAnyPresent([$this->check_login_success_selector,$this->username_selector], 15);
            for ($i = 0; $i < 10; $i++) {
                if ($this->isExists($this->username_selector) || $this->isExists($this->check_login_success_selector)) {
                    break;
                }
                sleep(2);
            }
            if ($this->isExists($this->check_login_success_selector)) {

                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

                $isLoggedIn = true;
            } else {
                if ($this->isExists('button[id*="menu-button"]')) {
                    $this->exts->moveToElementAndClick('button[id*="menu-button"]');
                    sleep(3);
                    if ($this->isExists('//p[contains(text(),"Logout")]')) {

                        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

                        $isLoggedIn = true;
                        $this->exts->moveToElementAndClick('button[id*="menu-button"]');
                        sleep(1);
                    }
                }
            }
        } catch (TypeError $e) {

            $this->exts->log("Exception checking loggedin " . $e);
            sleep(15);

            $this->exts->waitTillAnyPresent([$this->check_login_success_selector, $this->username_selector], 15);
            if ($this->isExists($this->check_login_success_selector)) {

                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

                $isLoggedIn = true;
            } else {
                if ($this->isExists('button[id*="menu-button"]')) {
                    $this->exts->moveToElementAndClick('button[id*="menu-button"]');
                    sleep(3);
                    if ($this->isExists('//p[contains(text(),"Logout")]')) {

                        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

                        $isLoggedIn = true;
                        $this->exts->moveToElementAndClick('button[id*="menu-button"]');
                        sleep(1);
                    }
                }
            }
        }

        return $isLoggedIn;
    }

    private function solveCAPTCHA()
    {
        $unsolved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) <= 0]';
        $solved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) > 0]';
        $this->exts->capture("cloudflare-checking");
        if (
            !$this->exts->oneExists([$solved_cloudflare_input_xpath, $unsolved_cloudflare_input_xpath]) &&
            $this->isExists('#cf-please-wait > p:not([style*="display: none"]):not([style*="display:none"])')
        ) {
            for ($waiting = 0; $waiting < 10; $waiting++) {
                sleep(2);
                if ($this->exts->oneExists([$solved_cloudflare_input_xpath, $unsolved_cloudflare_input_xpath])) {
                    sleep(3);
                    break;
                }
            }
        }

        if ($this->isExists($unsolved_cloudflare_input_xpath)) {
            $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
            sleep(5);
            $this->exts->capture("cloudflare-clicked-1", true);
            sleep(3);
            if ($this->isExists($unsolved_cloudflare_input_xpath)) {
                $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
                sleep(5);
                $this->exts->capture("cloudflare-clicked-2", true);
                sleep(15);
            }
            if ($this->isExists($unsolved_cloudflare_input_xpath)) {
                $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
                sleep(5);
                $this->exts->capture("cloudflare-clicked-3", true);
                sleep(15);
            }
        }
    }

    private function acceptCookies()
    {
        sleep(2);
        // $this->exts->waitTillPresent('button[title="Accept all cookies"]', 20);
        for ($i = 0; $i < 10; $i++) {
            if ($this->isExists('button[title="Accept all cookies"]')) {
                break;
            }
            sleep(2);
        }
        if ($this->exts->querySelector('button[title="Accept all cookies"]') != null) {
            $this->exts->click_element('button[title="Accept all cookies"]');
        }
    }

    private function processAllInvoices()
    {
        // $this->exts->waitTillPresent('button.chakra-modal__close-btn', 20);
        for ($i = 0; $i < 10; $i++) {
            if ($this->isExists('button.chakra-modal__close-btn')) {
                break;
            }
            sleep(2);
        }
        $this->exts->click_element('button.chakra-modal__close-btn');
        // $this->exts->waitTillPresent('button[data-tracking-id="tokens_manage_subscription_buypage_popover_link"]', 20);
        for ($i = 0; $i < 10; $i++) {
            if ($this->isExists('button[data-tracking-id="tokens_manage_subscription_buypage_popover_link"]')) {
                break;
            }
            sleep(2);
        }
        $this->exts->click_element('button[data-tracking-id="tokens_manage_subscription_buypage_popover_link"]');
        // $this->exts->waitTillPresent('div.chakra-modal__body > div > div > button:nth-child(3)', 20);
        for ($i = 0; $i < 10; $i++) {
            if ($this->isExists('div.chakra-modal__body > div > div > button:nth-child(3)')) {
                break;
            }
            sleep(2);
        }
        $this->exts->click_element('//button[normalize-space(text())="View Invoices"]');
        sleep(15);
        do {
            $button = $this->exts->querySelector('button[data-testid="view-more-button"]');

            if ($button != null) {
                $this->exts->execute_javascript("arguments[0].click();", [$button]);
                sleep(5);
            }
        } while ($button != null);

        $this->processInvoices();
        // Final, check no invoice
        if ($this->isNoInvoice) {
            $this->exts->no_invoice();
        }
    }
    private function processInvoices()
    {
        sleep(1);
        // $this->exts->waitTillPresent('a[href*="invoice.stripe.com"]', 20);
        for ($i = 0; $i < 10; $i++) {
            if ($this->isExists('a[href*="invoice.stripe.com"]')) {
                break;
            }
            sleep(2);
        }
        $this->exts->capture("1 invoice page");
        $invoices = [];

        $rows = $this->exts->getElements('a[href*="invoice.stripe.com"]');
        foreach ($rows as $row) {
            $tags = $this->exts->getElements('span', $row);
            if (count($tags) >= 3) {
                $invoiceUrl = $row->getAttribute("href");
                $invoiceName = '';
                $invoiceDate = trim($tags[0]->getAttribute('innerText'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[1]->getAttribute('innerText'))) . ' EUR';

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
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : '';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);
            $this->exts->openUrl($invoice['invoiceUrl']);
            sleep(10);
            $download_button = $this->exts->getElement('//button[contains(@class, "Button--primary")]//span[contains(text(),"Rechnung herunterladen") or contains(text(),"Download invoice")]');
            if ($download_button != null) {
                try {
                    $this->exts->log('Click download_button button');
                    $download_button->click();
                } catch (\Exception $exception) {
                    $this->exts->log('Click download_button button by javascript');
                    $this->exts->execute_javascript("arguments[0].click()", [$download_button]);
                }
                sleep(2);
                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf');
                $invoiceFileName = basename($downloaded_file);

                $invoice['invoiceName'] = substr($invoiceFileName, 0, strrpos($invoiceFileName, '.'));

                $this->exts->log('invoiceName: ' . $invoice['invoiceName']);

                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                    sleep(1);
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
