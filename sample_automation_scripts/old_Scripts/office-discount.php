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

    // Server-Portal-ID: 10089 - Last modified: 24.06.2024 13:11:33 UTC - User: 1

    public $baseUrl = 'https://www.office-discount.at/bestellhistorie';
    public $loginUrl = 'https://www.office-discount.at/login';
    public $invoicePageUrl = 'https://www.office-discount.at/bestellhistorie';

    public $username_selector = 'div#login-page  input#email';
    public $password_selector = 'div#login-page  input#password';
    public $remember_me_selector = '';
    public $submit_login_selector = 'form[action="UgsUserLoginCmd"] button[type="submit"], button[name="login"]';

    public $check_login_failed_selector = '#err_logonPassword, p.error-row';
    public $check_login_success_selector = 'a[href="/UgsUserLogoutCmd"], button[type=submit].logout';

    public $isNoInvoice = true;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);


        $this->exts->openUrl($this->baseUrl);
        sleep(15);

        if ($this->exts->querySelector('iframe#main-iframe') != null) {
            $this->switchToFrame('iframe#main-iframe');
            sleep(4);
            $this->checkFillHcaptcha();
        }



        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        $this->exts->capture('1-init-page');



        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->openUrl($this->loginUrl);
            sleep(15);



            if ($this->exts->exists('iframe#main-iframe')) {
                $this->exts->switchToFrame('iframe#main-iframe');
                sleep(2);
            }
            $this->checkFillRecaptcha();
            if ($this->exts->exists('iframe#main-iframe')) {
                $this->exts->switchToFrame('iframe#main-iframe');
                sleep(2);
            }
            $this->checkFillRecaptcha();
            if ($this->exts->exists('#env_btn_stay_at')) {
                $this->exts->moveToElementAndClick('#env_btn_stay_at');
                sleep(5);
            }
            if ($this->exts->exists('button#uc-btn-accept-banner')) {
                $this->exts->moveToElementAndClick('button#uc-btn-accept-banner');
                sleep(5);
            }
            //some time after solve recaptcha, site redirect to home page
            if ($this->exts->getElement($this->password_selector) == null) {
                $this->exts->openUrl($this->loginUrl);
                sleep(15);
            }
            $this->checkFillLogin();
            sleep(20);
        }


        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");
            // Accept cookie
            if ($this->exts->exists('#usercentrics-root')) {
                $this->exts->executeSafeScript('
					var shadow = document.querySelector("#usercentrics-root").shadowRoot;
					var button = shadow.querySelector(\'button[data-testid="uc-accept-all-button"]\')
					if(button){
						button.click();
					}
				');
            }

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
            if ($this->exts->getElement($this->check_login_failed_selector) != null) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    public function switchToFrame($query_string)
    {
        $this->exts->log(__FUNCTION__ . " Begin with " . $query_string);
        $frame = null;
        if (is_string($query_string)) {
            $frame = $this->exts->queryElement($query_string);
        }

        if ($frame != null) {
            $frame_context = $this->exts->get_frame_excutable_context($frame);
            if ($frame_context != null) {
                $this->exts->current_context = $frame_context;
                return true;
            }
        } else {
            $this->exts->log(__FUNCTION__ . " Frame not found " . $query_string);
        }

        return false;
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

    private function checkFillRecaptcha()
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
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Not found reCaptcha');
        }
    }

    private function checkFillHcaptcha($count = 0)
    {
        $hcaptcha_iframe_selector = 'div.h-captcha iframe[src*="hcaptcha"]';
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
    private function processInvoices()
    {
        sleep(25);

        $this->exts->capture("4-invoices-page");
        $invoices = [];
        if ($this->exts->exists('#env_btn_stay_at')) {
            $this->exts->moveToElementAndClick('#env_btn_stay_at');
            sleep(5);
        }
        if ($this->exts->exists('button#uc-btn-accept-banner')) {
            $this->exts->moveToElementAndClick('button#uc-btn-accept-banner');
            sleep(5);
        }
        $rows = $this->exts->getElements('div[data-name="orderList"] a[href*="bestellhistorie?onr"]');
        foreach ($rows as $row) {
            $invoiceUrl = $row->getAttribute("href");
            $invoiceName = '';
            $invoiceDate = '';
            $invoiceAmount = '';

            array_push($invoices, array(
                'invoiceName' => $invoiceName,
                'invoiceDate' => $invoiceDate,
                'invoiceAmount' => $invoiceAmount,
                'invoiceUrl' => $invoiceUrl
            ));
            $this->isNoInvoice = false;
        }


        // Download all invoices
        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {
            $this->exts->openUrl($invoice['invoiceUrl']);
            sleep(8);
            $invoiceName = trim($this->exts->extract('div[data-name="infoBox"] span[data-name="orderNumber"]', null, 'innerText'));
            $invoiceDate = trim($this->exts->extract('div[data-name="infoBox"] h4', null, 'innerText'));
            $invoiceDate = trim(preg_replace('/[^\d\,\.]/', '', $invoiceDate));
            $invoiceAmount = '';
            $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';

            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoiceName);
            $this->exts->log('invoiceDate: ' . $invoiceDate);
            $this->exts->log('invoiceAmount: ' . $invoiceAmount);
            $parsed_date = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $parsed_date);

            // Download invoice if it not exisited
            if ($this->exts->invoice_exists($invoiceName)) {
                $this->exts->log('Invoice existed ' . $invoiceFileName);
            } else {
                $this->exts->executeSafeScript('document.querySelector("body").innerHTML = document.querySelector("main.acc-history").outerHTML;');
                sleep(1);
                $downloaded_file = $this->exts->download_current($invoiceFileName, 5);

                sleep(5);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                    sleep(1);
                } else {
                    $this->exts->log('Timeout when download ' . $invoiceFileName);
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
