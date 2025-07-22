<?php //  added 2fa code

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

    // Server-Portal-ID: 44088 - Last modified: 19.06.2025 14:47:42 UTC - User: 1

    public $baseUrl = 'https://app.hubstaff.com/organizations/';
    public $loginUrl = 'https://app.hubstaff.com/organizations/';
    public $invoicePageUrl = 'https://app.hubstaff.com/organizations/';

    public $username_selector = 'form[action="/login"] input#user_email';
    public $password_selector = 'form[action="/login"] input#user_password';
    public $remember_me_selector = '';
    public $submit_login_selector = 'form[action="/login"] button[type="submit"]';

    public $check_login_failed_selector = 'form[action="/login"] .list-group-item-danger';
    public $check_login_success_selector = 'a[href="/logout"]';

    public $freelancer_invoices = 0;
    public $isNoInvoice = true;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);


        $this->freelancer_invoices = isset($this->exts->config_array["freelancer_invoices"]) ? (int)@$this->exts->config_array["freelancer_invoices"] : $this->freelancer_invoices;

        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        $this->check_solve_blocked_page();
        $this->checkFillHcaptcha(0);
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->openUrl($this->loginUrl);
            sleep(15);
            $this->check_solve_blocked_page();
            $this->checkFillHcaptcha(0);
            $this->checkFillLogin();
            sleep(20);
            $this->check_solve_blocked_page();
            $this->checkFillHcaptcha(0);

            $this->checkFillTwoFactor();
        }

        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(15);

            $this->processAccount();

            //download reports
            if ($this->freelancer_invoices == 1) {
                $this->exts->openUrl('https://app.hubstaff.com/dashboard');
                sleep(15);

                $this->exts->moveToElementAndClick('div[class="main-wrapper"] div >  a[href*="/reports"]');
                sleep(10);

                $this->exts->moveToElementAndClick('li[data-submenu-id="report_payments"] > a');
                sleep(10);

                $this->processInvoicesReports();
            }
            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            if (
                strpos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'passwor') !== false ||
                strpos(strtolower($this->exts->extract('span.help-block', null, 'innerText')), 'invalid') !== false
            ) {
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
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }


    private function checkFillTwoFactor()
    {
        $two_factor_selector = 'input[id="user_otp"]';
        $two_factor_message_selector = 'span.help-block';
        $two_factor_submit_selector = 'button.submit-otp.confirm';

        if ($this->exts->querySelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            $this->exts->type_key_by_xdotool('Return');
            sleep(5);

            if ($this->exts->querySelector($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = "";
                for ($i = 0; $i < count($this->exts->querySelectorAll($two_factor_message_selector)); $i++) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->querySelectorAll($two_factor_message_selector)[$i]->getText() . "\n";
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
                $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);

                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(1);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                // $this->exts->moveToElementAndClick($two_factor_submit_selector); // auto submit twoFA
                sleep(15);

                if ($this->exts->querySelector($two_factor_selector) == null) {
                    $this->exts->log("Two factor solved");
                } else if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->two_factor_attempts++;
                    $this->exts->notification_uid = '';
                    $this->checkFillTwoFactor();
                } else {
                    $this->exts->log("Two factor can not solved");
                }
            } else {
                $this->exts->log("Not received two factor code");
            }
        }
    }

    private function checkFillHcaptcha($count = 0)
    {
        $hcaptcha_iframe_selector = 'div#cf-hcaptcha-container iframe[src*="hcaptcha"]';
        if ($this->exts->exists($hcaptcha_iframe_selector)) {
            $iframeUrl = $this->exts->extract($hcaptcha_iframe_selector, null, 'src');
            $data_siteKey =  end(explode("&sitekey=", $iframeUrl));
            $data_siteKey =  explode("&", $data_siteKey)[0];
            $jsonRes = $this->exts->processHumanCaptcha("", $data_siteKey, $this->exts->getUrl(), false);

            if (!empty($jsonRes) && trim($jsonRes) != '') {
                $captchaScript = "
		        function submitToken(token) {
		          document.querySelector('[name=\"h-captcha-response\"]').innerText = token;
		        }
		        submitToken(arguments[0]);
			";
                $params = array($jsonRes);
                $this->exts->executeSafeScript($captchaScript, $params);
                sleep(2);

                $captchaScript = '
		        function submitToken1(token) {
		          form1 = document.querySelector("form#challenge-form div#cf-hcaptcha-container div:not([style*=\"display: none\"]) iframe");
		          form1.removeAttribute("data-hcaptcha-response");
		          var att = document.createAttribute("data-hcaptcha-response");
		          att.value = token;
		          
		          form1.setAttributeNode(att);
		        }
		        submitToken1(arguments[0]);
			    ';
                $params = array($jsonRes);
                $this->exts->executeSafeScript($captchaScript, $params);

                $this->exts->log('-------------------------------');
                $this->exts->log($this->exts->extract('[name="h-captcha-response"]', null, 'innerText'));
                $this->exts->log('-------------------------------');
                $this->exts->log($this->exts->extract('form#challenge-form div#cf-hcaptcha-container div:not([style*="display: none"]) iframe', null, 'data-hcaptcha-response'));
                $this->exts->log('-------------------------------');
                $this->exts->log($this->exts->extract('form#challenge-form div#cf-hcaptcha-container div[style*="display: none"] iframe', null, 'data-hcaptcha-response'));
                $this->exts->log('-------------------------------');
                $this->exts->executeSafeScript('document.querySelector("form#challenge-form").submit();');
                sleep(15);
            }

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
        $this->exts->capture_by_chromedevtool("blocked-page-checking");
        if ($this->exts->exists('iframe[src*="challenges.cloudflare.com"]')) {
            $this->exts->capture_by_chromedevtool("blocked-by-cloudflare");
            // $this->exts->refresh();
            sleep(10);
            // $this->exts->click_by_xdotool('iframe[src*="challenges.cloudflare.com"]');
            $this->exts->click_by_xdotool('iframe[src*="challenges.cloudflare.com"]', 30, 28);
            sleep(15);
            if ($this->exts->exists('iframe[src*="challenges.cloudflare.com"]')) {
                $this->exts->click_by_xdotool('iframe[src*="challenges.cloudflare.com"]', 30, 28);
                sleep(15);
            }
            if ($this->exts->exists('iframe[src*="challenges.cloudflare.com"]')) {
                $this->exts->click_by_xdotool('iframe[src*="challenges.cloudflare.com"]', 30, 28);
                sleep(15);
            }
        }
    }



    private function processAccount()
    {
        if ($this->exts->exists('#organizations table.has-actions >tbody >tr a.is-block[href*="/organizations/"]')) {
            $organization_urls = [];
            $organizations = $this->exts->getElements('#organizations table.has-actions >tbody >tr a.is-block[href*="/organizations/"]');
            foreach ($organizations as $key => $organization) {
                $organization_url = $organization->getAttribute('href') . '/billing';

                array_push($organization_urls, array(
                    'organization_url' => $organization_url
                ));
            }
            foreach ($organization_urls as $key => $organization_url) {
                $this->exts->openUrl($organization_url['organization_url']);
                sleep(10);
                $this->exts->moveToElementAndClick('li:nth-child(4) a[href*="billing/invoices"]');
                $this->processInvoices();
            }
        }
    }

    private function processInvoices($count = 1)
    {
        sleep(7);
        $this->exts->waitTillPresent('table > tbody > tr');
        $this->exts->log(__FUNCTION__);
        $this->exts->capture("4-invoices-page");

        $rows = $this->exts->getElements('table > tbody > tr');
        $invoices = [];
        foreach ($rows as $key => $row) {
            $invoiceLink = $this->exts->getElement('td:nth-child(8)  > div > div  > div > div a[href*=".pdf?inline=true"]', $row);
            if ($invoiceLink != null) {
                $invoiceUrl = $invoiceLink->getAttribute("href");
                $invoiceName = $this->exts->extract('td:nth-child(3) a[href*="billing/invoices"]', $row);
                $invoiceDate = $this->exts->extract('td:nth-child(6)', $row);
                $invoiceAmount = $this->exts->extract('td:nth-child(1)', $row);

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl,
                ));
                $this->isNoInvoice = false;
            }
        }

        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoiceFileName = !empty($invoice['invoiceName']) ?  $invoice['invoiceName'] . '.pdf' : '';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }

        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        $count++;
        $pagiantionSelector = 'a[href*="page=' . $count . '"]:nth-child(' . $count . ')';
        if ($restrictPages == 0) {
            if ($count < 50 && $this->exts->querySelector($pagiantionSelector) != null) {
                $this->exts->moveToElementAndClick($pagiantionSelector);
                sleep(7);

                $this->processInvoices($count);
            }
        } else {
            if ($count < $restrictPages && $this->exts->querySelector($pagiantionSelector) != null) {
                $this->exts->moveToElementAndClick($pagiantionSelector);
                sleep(7);
                $this->processInvoices($count);
            }
        }
    }

    private function processInvoicesReports()
    {
        sleep(25);

        $this->exts->capture("4-invoices-pageReports");
        $invoices = [];

        $rows = $this->exts->getElements('table > tbody.tbody > tr');
        foreach ($rows as $row) {
            $tags = $this->exts->getElements('td', $row);
            if (count($tags) >= 6 && $this->exts->getElement('a[href*="/invoices/"]', $tags[1]) != null) {
                $invoiceUrl = $this->exts->getElement('a[href*="/invoices/"]', $tags[1])->getAttribute("href") . '/pdf';
                $invoiceName = trim($tags[1]->getAttribute('innerText'));
                $invoiceDate = trim($tags[0]->getAttribute('innerText'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[5]->getAttribute('innerText'))) . ' EUR';

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

            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : '';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'j/m/Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);


            $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $downloaded_file);
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
