<?php //  added dateRange logic 

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

    // Server-Portal-ID: 53506 - Last modified: 17.07.2025 14:41:47 UTC - User: 1

    public $baseUrl = 'https://www.bueromarkt-ag.de/';
    public $loginUrl = 'https://www.bueromarkt-ag.de/anmeldung/anmelden_auswahl.php?reg=1';
    public $invoicePageUrl = 'https://www.bueromarkt-ag.de/mein-konto/bestellungen/rechnungen';

    public $username_selector = 'form[name="login"] input[name="Kundennummer_neu"], form.login input[name="Email"]';
    public $password_selector = 'form[name="login"] input[name="Passwort_neu"], input#passwort';
    public $remember_me_selector = '';
    public $submit_login_btn = 'form[name="login"] input[type="submit"], form.login .btn-login';

    public $checkLoginFailedSelector = 'div.message.error';
    public $checkLoggedinSelector = 'a[href*="/logout.php"], a[href*="/mein-konto/abmelden"]';

    public $isNoInvoice = true;
    public $restrictPages = 3;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

        $this->exts->openUrl($this->baseUrl);
        sleep(4);

        for ($i = 0; $i < 11; $i++) {
            $this->exts->type_key_by_xdotool('Tab');
            sleep(1);
        }
        $this->exts->type_key_by_xdotool('Return');
        sleep(4);
        $this->exts->openUrl($this->baseUrl);
        sleep(4);

        if ($this->exts->exists($this->checkLoggedinSelector)) {
            $this->exts->log('Logged in from initPortal');
            $this->exts->capture('0-init-portal-loggedin');
            $this->checkFillRecaptcha();
            $this->waitForLogin();
        } else {
            $this->exts->openUrl($this->loginUrl);
            sleep(4);
            $this->checkFillRecaptcha();
            if ($this->exts->exists('button#uc-btn-accept-banner')) {
                $this->exts->moveToElementAndClick('button#uc-btn-accept-banner');
                sleep(1);
            }
            // accept cookies button
            $this->exts->execute_javascript('
		var shadow = document.querySelector("#usercentrics-root").shadowRoot;
		var button = shadow.querySelector(\'button[data-testid="uc-accept-all-button"]\')
		if(button){
			button.click();
		}
	');

            $this->waitForLoginPage();
            sleep(20);
            // check if has shown 403 page
            if ($this->exts->exists('.error-code-truck .no-user-select')) {
                $this->exts->log('-- 403 page --');
                $this->exts->capture('1.1-blocked-page');
                $this->exts->openUrl($this->loginUrl);
                sleep(15);
                $this->checkFillRecaptcha();
                $this->waitForLoginPage();
            }

            $this->waitForLogin();
        }
    }

    private function waitForLoginPage()
    {
        sleep(5);
        $this->exts->capture("1-pre-login");

        if ($this->exts->exists($this->username_selector)) {
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(2);
        }

        if ($this->exts->exists($this->password_selector)) {
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);
        }

        $this->exts->capture("1-filled-login");
        $this->exts->moveToElementAndClick($this->submit_login_btn);
        sleep(10);
        $this->checkFillRecaptcha();
        if ($this->exts->exists($this->submit_login_btn)) {
            $this->exts->execute_javascript('document.querySelector(arguments[0]).click', [$this->submit_login_btn]);
            sleep(10);
        }
    }

    private function checkFillRecaptcha($count = 1)
    {
        $this->exts->log(__FUNCTION__);
        if ($this->exts->exists('iframe[name="recaptcha"]')) {
            $this->switchToFrame('iframe[name="recaptcha"]');
        }
        if ($this->exts->exists('iframe#grcv3enterpriseframe')) {
            $this->switchToFrame('iframe#grcv3enterpriseframe');
        }

        if ($this->exts->exists('iframe#main-iframe')) {
            $this->switchToFrame('iframe#main-iframe');
        }
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
                    $this->exts->execute_javascript("arguments[0].innerHTML = '" . $this->exts->recaptcha_answer . "';", [$recaptcha_textareas[$i]]);
                }
                sleep(2);
                $this->exts->capture('recaptcha-filled');

                // Step 2, check if callback function need executed
                $gcallbackFunction = $this->exts->execute_javascript('
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
                    $this->exts->execute_javascript($gcallbackFunction . '("' . $this->exts->recaptcha_answer . '");');
                    sleep(10);
                }
                $this->exts->switchToDefault();
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

    private function waitForLogin()
    {
        if ($this->exts->exists($this->checkLoggedinSelector)) {
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture('Login-success');

            $this->exts->openUrl($this->invoicePageUrl);
            $this->processInvoices();
            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());
            if ($this->exts->exists($this->checkLoginFailedSelector)) {
                $errorMsg = $this->exts->extract($this->checkLoginFailedSelector);
                $this->exts->log($errorMsg);
                if (stripos($errorMsg, 'Sie Ihren Benutzernamen und Ihr Passwort') !== false && stripos($errorMsg, 'Fehlerhafte Eingabe') !== false) {
                    $this->exts->loginFailure(1);
                } else if (strpos(strtolower($errorMsg), 'richtige e-mail-adresse / benutzer') !== false || strpos(strtolower($errorMsg), 'das richtige passwort') !== false) {
                    $this->exts->loginFailure(1);
                } else {
                    $this->exts->loginFailure();
                }
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    public function dateRange($count)
    {

        $selectDate = new DateTime();


        if ($this->restrictPages == 0) {
            $yearSelector = '-' . $count . ' years';
            $selectDate->modify($yearSelector);
            $formattedDate = strtoupper($selectDate->format('Y'));
            $this->exts->log('date-range-3-years');
            $this->exts->openUrl('https://www.bueromarkt-ag.de/mein-konto/bestellungen?filter=allOrders&year=' . $formattedDate);
            return true;
        } else {
            // current year invoices already download 
            return false;
        }
    }

    public $totalInvoices = 0;
    private function processInvoices($count = 0)
    {
        sleep(25);
        $this->exts->capture("4-invoices-page");
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

        $rows = count($this->exts->getElements('div.order-item-tablet'));
        for ($i = 0; $i < $rows; $i++) {

            if ($restrictPages != 0 && $this->totalInvoices >= 50) {
                break;
            }

            $row = $this->exts->getElements('div.order-item-tablet')[$i];
            if ($this->exts->getElement('.order-item-features button.invoice-copy-btn', $row) != null) {
                $this->isNoInvoice = false;
                $download_button = $this->exts->getElement('.order-item-features button.invoice-copy-btn', $row);
                $invoiceName = trim($this->exts->extract('.order-item-head-order_number span', $row, 'innerText'));
                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                $invoiceDate = trim($this->exts->extract('.order-date span', $row, 'innerText'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->exts->extract('.order-sum-total span', $row, 'innerText'))) . ' EUR';

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
                    // try {
                    //     $this->exts->log('Click download button');
                    //     $download_button->click();
                    // } catch (\Exception $exception) {
                    $this->exts->log('Click download button by javascript');
                    $this->exts->execute_javascript("arguments[0].click()", [$download_button]);
                    // }
                    sleep(5);
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
                        $this->totalInvoices++;
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
                }
            }
        }
        $count++;
        $status = $this->dateRange($count);
        if ($count < 3 && $status == true) {
            $this->processInvoices($count);
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
