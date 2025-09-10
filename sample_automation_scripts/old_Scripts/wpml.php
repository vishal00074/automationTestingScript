<?php // migrated

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

    // Server-Portal-ID: 28612 - Last modified: 27.03.2024 08:46:00 UTC - User: 1

    /*Define constants used in script*/
    public $baseUrl = 'https://wpml.org/';
    public $loginUrl = 'https://wpml.org/';
    public $invoicePageUrl = 'https://wpml.org/account/view_order/';

    public $username_selector = 'input#username';
    public $password_selector = 'input#user_pass';
    public $remember_me_selector = '';
    public $submit_login_selector = 'input#Login';

    public $check_login_failed_selector = 'ul.woocommerce-error';
    public $check_login_success_selector = 'body.logged-in';

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
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            // $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            sleep(15);

            if (strpos(strtolower($this->exts->extract('body h1', null, 'innerText')), '403 forbidden') !== false) {
                $this->exts->refresh();
                sleep(13);
            }
            if (strpos(strtolower($this->exts->extract('body h1', null, 'innerText')), '403 forbidden') !== false) {
                $this->exts->openUrl($this->loginUrl);
                sleep(15);
            }
            if (strpos(strtolower($this->exts->extract('body h1', null, 'innerText')), '403 forbidden') !== false) {
                $this->exts->clearCookies();
                $this->exts->openUrl($this->loginUrl);
                sleep(15);
            }

            if ($this->exts->exists('#menu-account-menu-login a[href*="account"]')) {
                $this->exts->moveToElementAndClick('#menu-account-menu-login a[href*="account"]');
                sleep(15);
            }
            if ($this->exts->exists('div.login-select-box__exist a.login-select-box__btn')) {
                $this->exts->moveToElementAndClick('div.login-select-box__exist a.login-select-box__btn');
                sleep(5);
            }

            $this->checkFillLogin();
            sleep(20);
            // slove recaptcha has failed. try login
            if (strpos(strtolower($this->exts->extract('ul.woocommerce-error')), 'unable to verify captcha')) {
                $this->checkFillLogin();
                sleep(20);
            }
        }

        // then check user logged in or not
        // for ($wait_count = 1; $wait_count <= 10 && $this->exts->getElement($this->check_login_success_selector) == null; $wait_count++) {
        // 	$this->exts->log('Waiting for login...');
        // 	sleep(5);
        // }
        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice
            $this->exts->openUrl($this->invoicePageUrl);
            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }

            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $logged_in_failed_selector = $this->exts->getElementByText($this->check_login_failed_selector, ['Incorrect username or password', 'Falscher Benutzername oder falsches Passwort'], null, false);
            if ($logged_in_failed_selector != null) {
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
            // $this->checkFillRecaptcha();
            // $this->exts->capture("2-login-page-after-slove-captcha");
            $this->exts->moveToElementAndClick($this->submit_login_selector);
            sleep(15);
            $this->checkFillRecaptcha();
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function checkFillRecaptcha($count = 1)
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
                $recaptcha_textareas =  $this->exts->getElements($recaptcha_textarea_selector);
                for ($i = 0; $i < count($recaptcha_textareas); $i++) {
                    $this->exts->executeSafeScript("arguments[0].innerHTML = '" . $this->exts->recaptcha_answer . "';", [$recaptcha_textareas[$i]]);
                }
                sleep(2);
                $this->exts->capture('recaptcha-filled');

                // Step 2, check if callback function need executed
                $gcallbackFunction = $this->exts->executeSafeScript('
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
                    $this->exts->executeSafeScript($gcallbackFunction . '("' . $this->exts->recaptcha_answer . '");');
                    sleep(10);
                }
            } else {
                if ($count < 4) {
                    $count++;
                    $this->checkFillRecaptcha($count);
                }
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Not found reCaptcha');
        }
    }

    public function waitFor($selector, $seconds = 7)
    {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for Selectors.....');
            sleep($seconds);
        }
    }

    private function processInvoices()
    {
        sleep(25);
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->getElements('table tbody tr');
        foreach ($rows as $key => $row) {
            $invoiceLink = $this->exts->getElement('a[href*="account-settings/view-order"]', $row);
            if ($invoiceLink != null) {
                $invoiceUrl = $invoiceLink->getAttribute("href");
                $invoiceName = $this->exts->extract('td:nth-child(1) div:nth-child(1)', $row);
                $invoiceDate = $this->exts->extract('td:nth-child(1) div:nth-child(2)', $row);
                $invoiceAmount = $this->exts->extract('td:nth-child(3)', $row);

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl,
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

            $this->exts->openUrl($invoice['invoiceUrl']);

            $this->waitFor('a[class="button button-primary"]');

            $this->exts->moveToElementAndClick('a[class="button button-primary"]');

            $this->waitFor('div#print-icon a');

            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : '';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'F d, Y', 'Y-m-d', 'en');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            if ($this->exts->document_exists($invoiceFileName)) {
                continue;
            }

            $this->exts->no_margin_pdf = 1;
            if ($this->exts->exists('div#print-icon a')) {
                $this->exts->execute_javascript('window.print();');
                sleep(4);
                $file_ext = $this->exts->get_file_extension($invoiceFileName);

                $this->exts->wait_and_check_download($file_ext);

                $downloaded_file = $this->exts->find_saved_file($file_ext, $invoiceFileName);
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
