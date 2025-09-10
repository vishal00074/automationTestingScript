<?php // updated download code

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

    // Server-Portal-ID: 4298 - Last modified: 08.06.2025 04:52:28 UTC - User: 1

    public $baseUrl = 'https://account.buffer.com';
    public $loginUrl = 'https://login.buffer.com/login';

    public $username_selector = 'form#login-form input#email';
    public $password_selector = 'form#login-form input#password';
    public $submit_login_selector = 'button#login-form-submit';

    public $check_login_failed_selector = 'form#login-form input#password.error';
    public $check_login_success_selector = '#product-analyze, button picture>img[class*="avatar"]';

    public $isNoInvoice = true;
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
        $this->check_solve_blocked_page();
        $this->exts->capture('1-init-page');
        if ($this->exts->urlContains('/survey')) {
            $this->exts->capture('skipping-survey');
            $this->exts->openUrl($this->loginUrl);
            sleep(10);
            $this->check_solve_blocked_page();
        }

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->checkFillHcaptcha();
            sleep(2);
            for ($i = 0; $i < 3 && $this->exts->exists('div[class*="error__ErrorPageWrapper"] h1'); $i++) {
                if (stripos($this->exts->extract('div[class*="error__ErrorPageWrapper"] h1'), 'browser security check') !== false) {
                    sleep(20);
                }
            }
            $this->check_solve_blocked_page();
            $this->checkFillLogin();
            $this->check_solve_blocked_page();
            if ($this->exts->exists('a[href*="/login"]') && strpos(strtolower($this->exts->extract('a[href*="/login"]', null, 'innerText')), 'back to the page') !== false) {
                $this->exts->moveToElementAndClick('a[href*="/login"]');
                sleep(5);
                $this->checkFillHcaptcha();
                $this->check_solve_blocked_page();

                sleep(2);
                $this->checkFillLogin();
                $this->check_solve_blocked_page();
            }
            $this->checkFillLogin();
            $this->checkFillTwoFactor();
            $this->check_solve_blocked_page();
            $this->checkFillLogin();
            $this->check_solve_blocked_page();
            $this->checkFillTwoFactor();
        }

        $this->check_solve_blocked_page();

        $this->exts->waitTillPresent($this->check_login_success_selector, 20);

        // then check user logged in or not
        if ($this->exts->getElement($this->check_login_success_selector) != null || $this->exts->urlContains('/survey')) {
            if ($this->exts->urlContains('/survey')) {
                $this->exts->capture('skipping-survey');
                $this->check_solve_blocked_page();
                $this->exts->openUrl($this->baseUrl);
                $this->check_solve_blocked_page();

                sleep(10);
            }
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");
            $this->check_solve_blocked_page();

            $this->exts->waitTillPresent('div[role=dialog] button[class*=close]', 10);
            if ($this->exts->exists('div[role=dialog] button[class*=close]')) {
                $this->exts->click_element('div[role=dialog] button[class*=close]');
            }
            // Open invoices url and download invoice
            // new site React. commented out because download url get blocked 
            $url_after_login = $this->exts->getUrl();
            // open old site invoice page
            $this->exts->openUrl('https://publish.buffer.com/preferences/general');
            $this->check_solve_blocked_page();


            $this->exts->waitTillPresent('div[role=dialog] button[class*=close]', 10);
            if ($this->exts->exists('div[role=dialog] button[class*=close]')) {
                $this->exts->click_element('div[role=dialog] button[class*=close]');
            }

            $this->exts->waitTillPresent('a[href*="/account/receipts"]');
            if ($this->exts->exists('a[href*="/account/receipts"]')) {
                $this->exts->moveToElementAndClick('a[href*="/account/receipts"]');
            }
            sleep(1);

            $this->processInvoices();

            if ($this->isNoInvoice) {
                $this->exts->openUrl('https://account.buffer.com/');
                $this->check_solve_blocked_page();

                sleep(10);
                $this->exts->openUrl('https://account.buffer.com/billing');
                sleep(10);
                $this->exts->waitTillPresent('div[role=dialog] button[class*=close]', 10);
                if ($this->exts->exists('div[role=dialog] button[class*=close]')) {
                    $this->exts->click_element('div[role=dialog] button[class*=close]');
                    sleep(10);
                }

                if ($this->exts->exists('main > div:nth-child(6) button')) {
                    $this->exts->click_element('main > div:nth-child(6) button');
                    sleep(12);
                }

                $this->processInvoices31_3();
            }
            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());
            if (!filter_var($this->username, FILTER_VALIDATE_EMAIL)) {
                $this->exts->loginFailure(1);
            }
            if ($this->exts->getElement($this->check_login_failed_selector) != null) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function checkFillLogin()
    {
        $this->exts->waitTillPresent($this->password_selector, 30);
        if ($this->exts->getElement($this->password_selector) != null) {
            sleep(3);
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            $this->exts->capture("2-login-page-filled");
            $this->exts->moveToElementAndClick($this->submit_login_selector);
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function checkFillHcaptcha($count = 0)
    {
        $hcaptcha_iframe_selector = 'iframe[src*="hcaptcha"]';
        if ($this->exts->exists($hcaptcha_iframe_selector)) {
            $iframeUrl = $this->exts->extract($hcaptcha_iframe_selector, null, 'src');
            $data_siteKey =  end(explode("&sitekey=", $iframeUrl));
            $jsonRes = $this->exts->processHumanCaptcha("", $data_siteKey, $this->exts->getUrl(), false);

            if (!empty($jsonRes) && trim($jsonRes) != '') {
                $captchaScript = '
	            function submitToken(token) {
	                document.querySelector("[name=h-captcha-response]").innerText = token;
	                document.querySelector("form.challenge-form").submit();
	            }
	            submitToken(arguments[0]);
	        ';
                $params = array($jsonRes);
                $this->exts->execute_javascript($captchaScript, $params);
            }

            sleep(15);
            if ($this->exts->exists($hcaptcha_iframe_selector) && $count < 5) {
                $count++;
                $this->exts->refresh();
                sleep(15);
                $this->checkFillHcaptcha($count);
            }
        }
    }

    private function check_solve_blocked_page()
    {
        sleep(7);
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
            $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 180, 28);
            sleep(5);
            $this->exts->capture("cloudflare-clicked-1", true);
            sleep(3);
            if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
                $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 180, 28);
                sleep(5);
                $this->exts->capture("cloudflare-clicked-2", true);
                sleep(15);
            }
            if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
                $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 180, 28);
                sleep(5);
                $this->exts->capture("cloudflare-clicked-3", true);
                sleep(15);
            }
        }
    }

    private function checkFillTwoFactor()
    {
        $two_factor_selector = 'form#tfa-form input[id*="codeInput"]';
        $two_factor_message_selector = 'form#tfa-form div > p';
        $two_factor_submit_selector = 'button#tfa-form-submit';
        $this->exts->waitTillPresent($two_factor_selector, 20);
        if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if ($this->exts->getElement($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = "";
                for ($i = 0; $i < count($this->exts->querySelectorAll($two_factor_message_selector)); $i++) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->querySelectorAll($two_factor_message_selector)[$i]->getAttribute('innerText') . "\n";
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
                $resultCodes = str_split($two_factor_code);
                $code_inputs = $this->exts->querySelectorAll('form#tfa-form input[id*="codeInput"]');
                foreach ($code_inputs as $key => $code_input) {
                    if (array_key_exists($key, $resultCodes)) {
                        $this->exts->log('"checkFillTwoFactor: Entering key ' . $resultCodes[$key] . 'to input #' . $code_input->getAttribute('id'));
                        $this->exts->moveToElementAndType('input:nth-child(' . ($key + 1) . ')', $resultCodes[$key]);
                        sleep(1);
                    } else {
                        $this->exts->log('"checkFillTwoFactor: Have no char for input #' . $code_input->getAttribute('id'));
                    }
                }
                sleep(10);

                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(3);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                $this->exts->moveToElementAndClick($two_factor_submit_selector);
                $this->checkFillLogin();
                sleep(15);
                if ($this->exts->getElement($two_factor_selector) == null) {
                    $this->exts->log("Two factor solved");
                } else if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->two_factor_attempts++;
                    $this->checkFillTwoFactor();
                } else {
                    $this->exts->log("Two factor can not solved");
                }
            } else {
                $this->exts->log("Not received two factor code");
            }
        } else if ($this->exts->getElement('//*[contains(text(),"Please check for the email from")]') != null) {

            $two_factor_message_selector = '//*[contains(text(),"Please check for the email from")]';
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if ($this->exts->getElement($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = "";
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[0]->getAttribute('innerText');
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' Pls copy that link then paste here';
                $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
                $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
            }
            if ($this->exts->two_factor_attempts == 2) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
            }

            $this->exts->notification_uid = '';
            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                $this->exts->log("checkFillTwoFactor: Open url: ." . $two_factor_code);
                $this->exts->openUrl($two_factor_code);
                sleep(25);
                $this->exts->capture("after-open-url-two-factor");
            } else {
                $this->exts->log("Not received two factor code");
            }
        }
    }

    private function processInvoices()
    {
        $this->exts->waitTillPresent('a[href*="/invoices/"]', 20);
        $this->exts->capture("4-invoices-page");

        $invoices = [];
        foreach ($this->exts->querySelectorAll('a[href*="/invoices/"]') as $key => $row) {
            $invoiceAmount = '';
            $amount = $this->exts->getElement('../preceding-sibling::h4[contains(@class, "amount")]', $row, 'xpath');
            if ($amount != null) {
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $amount->getAttribute('innerText'))) . ' USD';
            }
            $dateSelector = $this->exts->getElement('../preceding-sibling::p[contains(@class, "date")]', $row, 'xpath');
            if ($dateSelector != null) {
                $invoiceDate = trim(end(explode('on', $dateSelector->getAttribute('innerText'))));
                $date_parsed = $this->exts->parse_date($invoiceDate, 'F j Y', 'Y-m-d');
            }

            $invoiceUrl = trim($row->getAttribute('href'));
            $invoiceName = trim(end(explode('invoices/', $invoiceUrl)));

            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoiceDate);
            $this->exts->log('Date parsed: ' . $date_parsed);
            $this->exts->log('invoiceName: ' . $invoiceName);
            $this->exts->log('invoiceAmount: ' . $invoiceAmount);
            $this->exts->log('invoiceUrl: ' . $invoiceUrl);

            $invoiceFileName = !empty($invoiceName) ?  $invoiceName . '.pdf' : '';


            $downloaded_file = $this->exts->direct_download($invoiceUrl, 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoiceName, $date_parsed, $invoiceAmount, $invoiceFileName);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }

            $this->isNoInvoice = false;
        }
    }

    private function processInvoices31_3($paging_count = 1)
    {
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        $i = 0;
        if ($restrictPages == 0) {
            while ($i < 50 && $this->exts->exists('button[data-testid="view-more-button"]')) {
                $this->exts->moveToElementAndClick('button[data-testid="view-more-button"]');
                sleep(4);
                $i++;
            }
        } else {
            while ($i < $restrictPages && $this->exts->exists('button[data-testid="view-more-button"]')) {
                $this->exts->moveToElementAndClick('button[data-testid="view-more-button"]');
                sleep(4);
                $i++;
            }
        }
        $this->exts->waitTillPresent('div.Box-root a[href*="invoice.stripe.com"]');
        $this->exts->capture("4-invoices-classic");

        $invoices = [];
        $rows = $this->exts->getElements('div.Box-root a[href*="invoice.stripe.com"]');
        foreach ($rows as $key => $row) {
            $invoiceUrl = $row->getAttribute("href");
            array_push($invoices, array(
                'invoiceUrl' => $invoiceUrl,
            ));
            $this->isNoInvoice = false;
        }

        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {
            $this->exts->openUrl($invoice['invoiceUrl']);
            sleep(7);
            $invoiceName = '';
            $invoiceDate = '';
            $invoiceAmount = $this->exts->extract('div.InvoiceSummaryPostPayment span.CurrencyAmount');
            $this->exts->waitTillPresent('table.InvoiceDetails-table tbody tr.LabeledTableRow--wide');
            $invoiceRows = $this->exts->getElements('table.InvoiceDetails-table tbody tr.LabeledTableRow--wide');
            if (count($invoiceRows) >= 3) {
                $invoiceName = $this->exts->extract('td[style*="right"] span', $invoiceRows[0]);
                $invoiceDate = $this->exts->extract('td[style*="right"] span', $invoiceRows[1]);
            }
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoiceName);
            $this->exts->log('invoiceDate: ' . $invoiceDate);
            $this->exts->log('invoiceAmount: ' . $invoiceAmount);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);
            $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
            $invoiceDate = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoiceDate);

            $downloaded_file = $this->exts->click_and_download('div.InvoiceDetailsRow-Container button[data-testid="download-invoice-receipt-pdf-button"]', 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceUrl'], $invoiceDate, $invoiceAmount, $downloaded_file);
                sleep(1);
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
