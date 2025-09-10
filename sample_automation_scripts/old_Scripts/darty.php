<?php // updated login success selector and download code. added date pagination logic

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

    // Server-Portal-ID: 27354 - Last modified: 27.03.2025 14:21:48 UTC - User: 1

    public $baseUrl = "https://www.darty.com/espace_client/connexion?storeId=10001&espaceclient=0&org=head";
    public $loginUrl = "https://www.darty.com/espace_client/connexion?storeId=10001&espaceclient=0&org=head";
    public $invoiceUrl = "https://www.darty.com/espace_client/mes-commandes";

    public $username_selector = '[name="email"], input[name="mail"]';
    public $password_selector = "[name=password]";
    public $rememberme_btn = "[name=rememberme]";
    public $submit_btn = "#form-identification [type=submit]";
    public $logout_btn = 'span[class="label user--logged-in"]';

    public $isNoInvoice = true;

    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->clearChrome();
        sleep(2);
        $this->exts->clearCookies();
        $isCookieLoaded = false;
        if ($this->exts->loadCookiesFromFile()) {
            sleep(1);
            $isCookieLoaded = true;
        }

        $param = ['name' => 'datadome', 'domain' => '.darty.com'];
        $response_text = $this->exts->send_websocket_request($this->exts->current_context->webSocketDebuggerUrl, 'Network.deleteCookies', $param);
        sleep(1);

        $this->exts->openUrl($this->baseUrl);
        sleep(10);

        if ($this->exts->exists("iframe[src*='/captcha/']")) {
            $param = ['name' => 'datadome', 'domain' => '.darty.com'];
            $response_text = $this->exts->send_websocket_request($this->exts->current_context->webSocketDebuggerUrl, 'Network.deleteCookies', $param);
            sleep(1);

            $this->exts->log($response_text);
            $this->exts->openUrl($this->baseUrl);
            sleep(10);
        }

        if ($isCookieLoaded) {
            $this->exts->capture("Home-page-with-cookie");
        } else {
            $this->exts->capture("Home-page-without-cookie");
        }

        if (!$this->checkLogin()) {
            if ($this->exts->exists('button#onetrust-accept-btn-handler')) {
                $this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler');
                sleep(5);
            }
            $this->exts->capture("after-login-clicked");
            $this->fillForm(0);
            $this->checkFillRecaptcha(0);
            sleep(5);

            if ($this->exts->exists($this->username_selector)) {
                $this->fillForm(0);
                sleep(5);
            }
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            sleep(10);
            if ($this->exts->exists('a[href="/espace_client/mes-commandes"]')) {
                $this->exts->click_element('a[href="/espace_client/mes-commandes"]');
                sleep(10);
            }
            $this->exts->openUrl($this->invoiceUrl);
            sleep(5);
            $this->downloadInvoice(0);

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $param = ['name' => 'datadome', 'domain' => '.darty.com'];
            $response_text = $this->exts->send_websocket_request($this->exts->current_context->webSocketDebuggerUrl, 'Network.deleteCookies', $param);
            sleep(1);
        } else {
            $this->exts->capture("LoginFailed");
            $param = ['name' => 'datadome', 'domain' => '.darty.com'];
            $response_text = $this->exts->send_websocket_request($this->exts->current_context->webSocketDebuggerUrl, 'Network.deleteCookies', $param);
            sleep(1);
            $this->exts->loginFailure();
        }
    }

    /**
     * Method to fill login form
     * @param Integer $count Number of times portal is retried.
     */
    private function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        try {

            if ($this->exts->exists($this->username_selector)) {
                sleep(2);
                $this->exts->capture("1-pre-login");

                $this->exts->log("Enter Username");
                $this->exts->click_by_xdotool($this->username_selector);
                $this->exts->type_key_by_xdotool('ctrl+a');
                $this->exts->type_key_by_xdotool('Delete');
                $this->exts->type_text_by_xdotool($this->username);
                sleep(1);

                if ($this->exts->exists('//button[text() = "Continuer"]')) {
                    $this->exts->click_element('//button[text() = "Continuer"]');
                    sleep(5);
                }

                $this->exts->log("Enter Password");
                $this->exts->click_by_xdotool($this->password_selector);
                $this->exts->type_key_by_xdotool('ctrl+a');
                $this->exts->type_key_by_xdotool('Delete');
                $this->exts->type_text_by_xdotool($this->password);
                sleep(1);

                $this->exts->click_by_xdotool($this->rememberme_btn);

                $this->exts->click_by_xdotool($this->submit_btn);
                if ($this->exts->exists('//button[text() = "Me connecter"]')) {
                    $this->exts->click_element('//button[text() = "Me connecter"]');
                }
                sleep(5);
            } else if ($this->exts->exists("iframe[src*=\"https://www.google.com/recaptcha/api2/anchor?\"]") && $this->exts->exists("textarea[name=\"g-recaptcha-response\"]")) {
                $this->checkFillRecaptcha(0);
                $this->fillForm($count + 1);
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }

    private function clearChrome()
    {
        $this->exts->log("Clearing browser history, cookie, cache");
        $this->exts->openUrl('chrome://settings/clearBrowserData');
        sleep(10);
        $this->exts->capture("clear-page");
        for ($i = 0; $i < 2; $i++) {
            $this->exts->type_key_by_xdotool('Tab');
        }
        $this->exts->type_key_by_xdotool('Tab');
        $this->exts->type_key_by_xdotool('Return');
        $this->exts->type_key_by_xdotool('a');
        sleep(1);
        $this->exts->type_key_by_xdotool('Return');
        sleep(3);
        $this->exts->capture("clear-page");
        for ($i = 0; $i < 6; $i++) {
            $this->exts->type_key_by_xdotool('Tab');
        }
        $this->exts->type_key_by_xdotool('Return');
        sleep(15);
        $this->exts->capture("after-clear");
    }

    private function checkFillRecaptcha($counter)
    {
        if ($this->exts->exists('iframe[src*="https://www.google.com/recaptcha/api2/anchor?"]') && $this->exts->exists('textarea[name="g-recaptcha-response"]')) {

            if ($this->exts->exists("div.g-recaptcha")) {
                $data_siteKey = trim($this->exts->getElement("div.g-recaptcha")->getAttribute("data-sitekey"));
            } else {
                $iframeUrl = $this->exts->getElement("iframe[src*=\"https://www.google.com/recaptcha/api2/anchor?\"]")->getAttribute("src");
                $tempArr = explode("&k=", $iframeUrl);
                $tempArr = explode("&", $tempArr[count($tempArr) - 1]);

                $data_siteKey = trim($tempArr[0]);
                $this->exts->log("iframe url  - " . $iframeUrl);
            }
            $this->exts->log("SiteKey - " . $data_siteKey);

            $isCaptchaSolved = $this->exts->processRecaptcha($this->loginUrl, $data_siteKey, false);
            $this->exts->log("isCaptchaSolved - " . $isCaptchaSolved);

            if ($isCaptchaSolved) {
                $this->exts->log("isCaptchaSolved");
                $this->exts->execute_javascript(
                    "document.querySelector(\"#g-recaptcha-response\").innerHTML = arguments[0];",
                    array($this->exts->recaptcha_answer)
                );
                sleep(25);
                $func =  trim($this->exts->getElement("div.g-recaptcha")->getAttribute("data-callback"));
                $this->exts->execute_javascript(
                    $func . "('" . $this->exts->recaptcha_answer . "');"
                );
                sleep(10);
            }
            sleep(20);
        }

        if ($this->exts->exists('iframe[src*="https://www.google.com/recaptcha/api2/anchor?"]') && $this->exts->exists('textarea[name="g-recaptcha-response"]')) {
            $counter++;
            sleep(5);
            if ($counter < 3) {
                $this->exts->log("Retry reCaptcha");
                $this->checkFillRecaptcha($counter);
            } else {
                $this->exts->capture("LoginFailed");
                $this->exts->loginFailure();
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
            sleep(10);
            if ($this->exts->exists($this->logout_btn)) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception checking loggedin " . $exception);
        }
        return $isLoggedIn;
    }

    private function downloadInvoice()
    {
        for ($i = 0; $i < 10 && $this->exts->exists('button[data-testid="displayMoreOrderButton"]') && $this->exts->config_array["restrictPages"] == '0'; $i++) {
            $this->exts->moveToElementAndClick('button[data-testid="displayMoreOrderButton"]');
            sleep(5);
        }
        $this->exts->log("Begin download invoice ");
        try {
            if ($this->exts->exists('div[data-testid="order"] a[href*="mes-commandes/"]')) {
                $this->exts->capture("2-download-invoice");
                $invoices = array();

                $orders = $this->exts->getElements('div[data-testid="order"] a[href*="mes-commandes/"]');
                foreach ($orders as $order) {
                    $receiptUrl = $order->getAttribute('href');
                    $receiptName = str_replace('/', '_', array_pop(explode('mes-commandes/', $receiptUrl)));
                    $receiptFileName = !empty($receiptName) ? $receiptName . '.pdf': '';
                    $invoice = array(
                        'receiptName' => $receiptName,
                        'parsed_date' => '',
                        'receiptAmount' => '',
                        'receiptFileName' => $receiptFileName,
                        'receiptUrl' => $receiptUrl,
                    );

                    array_push($invoices, $invoice);
                    $this->isNoInvoice = false;
                }
                foreach ($invoices as $invoice) {
                    $this->exts->openUrl($invoice['receiptUrl']);
                    sleep(3);
                    $this->exts->waitTillPresent('div[data-testid="downloadBillLink"]');
                    $this->exts->moveToElementAndClick('div[data-testid="downloadBillLink"]');
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoice['receiptFileName']);
                    if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoice['receiptName'], $invoice['parsed_date'], $invoice['receiptAmount'], $invoice['receiptFileName']);
                    }
                }
            } elseif ($this->exts->exists('div[data-testid="order"]')) {
                $this->processInvoices();
            } else {
                $this->exts->log("No invoice !!! ");
                $this->exts->no_invoice();
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception downloading invoice " . $exception->getMessage());
        }
    }

    private function processInvoices()
    {
        $this->exts->waitTillPresent('div[data-testid="order"]', 30);
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->querySelectorAll('div[data-testid="order"]');
        $this->exts->log('No of Rows : ' . count($rows));

        for ($i = 0; $i < count($rows); $i++) {
            $this->exts->waitTillPresent('div[data-testid="order"]', 20);

            $row = $this->exts->querySelectorAll('div[data-testid="order"]')[$i];
            if ($this->exts->querySelector('div[data-testid="product-list"] button', $row) != null) {
                $invoiceUrl = '';
                $invoiceName = $this->exts->extract('[class*="OrderHeader_number"]', $row);
                $parts = explode('/', $invoiceName);
                $invoiceName = isset($parts[1]) ? trim($parts[1]) : null;
                $invoiceAmount = '';
                $invoiceDate = $this->exts->extract('[class*="OrderHeader_date"]', $row);
                $parts = explode(' ', $invoiceDate);
                $invoiceDate = isset($parts[1]) ? trim($parts[1]) : null;
                $rowBtn = $this->exts->querySelector('div[data-testid="product-list"] button', $row);

                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf': '';
                $this->isNoInvoice = false;

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceFileName: ' . $invoiceFileName);

                $this->exts->execute_javascript("arguments[0].click();", [$rowBtn]);
                $this->exts->waitTillPresent('div[data-testid="downloadBillLink"]', 20);
                $downloadBtn = $this->exts->querySelector('div[data-testid="downloadBillLink"]');
                $this->exts->execute_javascript("arguments[0].click();", [$downloadBtn]);
                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                    sleep(1);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
                sleep(2);
                if ($this->exts->exists('a[href="/espace_client/mes-commandes"]')) {
                    $this->exts->click_element('a[href="/espace_client/mes-commandes"]');
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
