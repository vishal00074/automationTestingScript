<?php // migrated udpated login and download code
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

    // Server-Portal-ID: 118478 - Last modified: 25.06.2024 09:25:49 UTC - User: 1

    /*Define constants used in script*/
    public $baseUrl = "https://admin.webex.com/login";
    public $username_selector = '[name="email"]';
    public $password_selector = '#IDToken2';
    public $submit_btn = "[name=loginForm] [type=submit], [name='Login.Submit']";
    public $logout_btn = 'div.avatar-icon, a[href="/admin/billing"], a[data-test="nav-tab--subscription"]';
    public $wrong_credential_selector = "#generic-error div";

    /**
     * check if a element is exists
     * @param String $sel Css selector
     */
    public function getCurrency($amountString)
    {
        if (strpos($amountString, '$') !== false) {
            return 'USD';
        } else if (strpos($amountString, 'Ã¢â€šÂ¬') !== false) {
            return 'EUR';
        } else if (strpos($amountString, 'Ã‚Â£') !== false) {
            return 'GBP';
        } else {
            return '';
        }
    }

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        $isCookieLoaded = false;
        if ($this->exts->loadCookiesFromFile()) {
            sleep(1);
            $isCookieLoaded = true;
        }

        $this->exts->openUrl($this->baseUrl);
        sleep(10);

        if ($isCookieLoaded) {
            $this->exts->capture("Home-page-with-cookie");
        } else {
            $this->exts->capture("Home-page-without-cookie");
        }

        if (!$this->checkLogin()) {
            $this->fillForm(0);
        }
        if (!$this->checkLogin() && !$this->exts->exists('.greeting')) {
            sleep(20);
        }
        //Check if user redirect to alternative billing page or not
        // if($this->exts->getElement('.greeting') != null) {
        // 	sleep(20);
        // }
        // if($this->exts->getElement('.greeting') != null) {
        // 	sleep(10);
        // }
        for ($i = 0; $i < 6 && $this->exts->getElement('.greeting') != null; $i++) {
            sleep(7);
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            if ($this->exts->getElement('a[href="/admin/billing"]') != null) {
                $this->exts->openUrl('https://web.webex.com/admin/billing');
                sleep(10);
                $this->processInvoices();
            } else {
                $this->exts->openUrl('https://admin.webex.com/my-company/orders');
                sleep(10);
                $this->downloadInvoice(0);
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());

            $error_text = strtolower($this->exts->extract($this->wrong_credential_selector));

            $this->exts->log(__FUNCTION__ . '::Error text: ' . $error_text);
            if (stripos($error_text, strtolower('incorrect')) !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    /**
     * Method to fill login form
     * @param Integer $count Number of times portal is retried.
     */
    function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        try {

            if ($this->exts->exists($this->username_selector)) {
                sleep(2);
                $this->exts->capture("1-pre-login");

                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(5);
                $this->exts->moveToElementAndClick('button[data-test-name="loginButton"]');
                sleep(7);

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);

                $this->exts->capture("1-pre-login-1");
                $this->checkFillRecaptcha(0);

                $this->exts->moveToElementAndClick('button[onclick*="LoginSubmit"]');
                sleep(10);
                $this->checkFillRecaptcha(0);
            } else if ($this->exts->exists("iframe[src*=\"https://www.google.com/recaptcha/api2/anchor?\"]") && $this->exts->exists("textarea[name=\"g-recaptcha-response\"]")) {
                $this->checkFillRecaptcha(0);
                if ($count < 5) {
                    $this->fillForm($count + 1);
                } else {
                    $this->exts->log(__FUNCTION__ . " :: too many recaptcha attempts " . $count);
                    $this->exts->loginFailure();
                }
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }

    private function checkFillRecaptcha($count = 1)
    {
        $this->exts->log(__FUNCTION__);
        $recaptcha_iframe_selector = 'iframe[src*="recaptcha/api2/anchor?ar"]';
        $recaptcha_textarea_selector = 'textarea[name="g-recaptcha-response"]';
        $this->exts->waitTillPresent($recaptcha_iframe_selector, 20);
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
            } else {
                if ($count < 3) {
                    $count++;
                    $this->checkFillRecaptcha($count);
                }
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Not found reCaptcha');
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
            if ($this->exts->exists($this->logout_btn)) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception checking loggedin " . $exception);
        }

        return $isLoggedIn;
    }

    private function downloadInvoice($count)
    {
        $this->exts->log("Begin download invoice - " . $count);
        $this->exts->capture("2-download-invoice-" . $count);
        try {
            if ($this->exts->exists('div.ui-grid-row')) {

                $invoices = array();

                $receipts = $this->exts->getElements('div.ui-grid-row');
                foreach ($receipts as $i => $receipt) {

                    try {
                        $receiptDate = trim($this->exts->extract('div[id*=uiGrid-0009-cell] > div', $receipt));
                    } catch (\Exception $exception) {
                        $receiptDate = null;
                    }

                    if ($receiptDate != null && $this->exts->extract('a[href*=DisplayInvoicePage]', $receipt, 'href') != null) {
                        $this->exts->log($receiptDate);

                        $receiptName = trim($this->exts->extract('div[id*=uiGrid-0007-cell] > div', $receipt));

                        $this->exts->log($receiptName);
                        $parsed_date = $this->exts->parse_date($receiptDate, 'd. M. Y', 'Y-m-d');
                        $this->exts->log($parsed_date);
                        $receiptFileName = !empty($receiptName) ? $receiptName . '.pdf' : '';
                        $this->exts->log($receiptFileName);

                        $receiptAmount = trim($this->exts->extract('div[id*=uiGrid-000B-cell] > div > span', $receipt));
                        $currency = $this->getCurrency($receiptAmount);
                        $receiptAmount = preg_replace('/[^\d\.,]/m', '', $receiptAmount);
                        $receiptAmount = trim($receiptAmount . ' ' . $currency);

                        $receiptUrl = $this->exts->extract('a[href*=DisplayInvoicePage]', $receipt, 'href');
                        $invoice = array(
                            'receiptName' => $receiptName,
                            'receiptUrl' => $receiptUrl,
                            'parsed_date' => $parsed_date,
                            'receiptAmount' => $receiptAmount,
                            'receiptFileName' => $receiptFileName
                        );
                        array_push($invoices, $invoice);
                    }
                }

                foreach ($invoices as $invoice) {
                    try {
                        $this->exts->openUrl($invoice['receiptUrl']);
                        sleep(5);
                        $downloaded_file = $this->exts->click_and_download('.printButton a', 'pdf', $invoice['receiptFileName']);
                        if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                            $this->exts->new_invoice($invoice['receiptName'], $invoice['parsed_date'], $invoice['receiptAmount'], $downloaded_file);
                        }
                    } catch (\Exception $exception) {
                        $this->exts->log("Exception downloading invoice - " . $exception->getMessage());
                    }
                };
            } else {
                $this->exts->log("No invoice !!! ");
                $this->exts->no_invoice();
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception downloading invoice " . $exception->getMessage());
        }
    }

    private function processInvoices($pageCount = 1)
    {
        sleep(25);
        $this->exts->capture("4-invoices-page");
        $invoices = [];
        $rows = count($this->exts->getElements('table > tbody > tr'));
        for ($i = 0; $i < $rows; $i++) {
            $row = $this->exts->getElements('table > tbody > tr')[$i];
            $tags = $this->exts->getElements('td', $row);
            if (count($tags) >= 4 && $this->exts->getElement('button i[aria-label*="receipt"]', $tags[3]) != null) {
                $this->isNoInvoice = false;
                $download_button = $this->exts->getElement('button i[aria-label*="receipt"]', $tags[3]);
                $invoiceName = str_replace(' ', '', str_replace(',', '', trim($tags[0]->getAttribute('innerText'))));
                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                $invoiceDate = trim($tags[0]->getAttribute('innerText'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[2]->getAttribute('innerText'))) . ' EUR';

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $parsed_date = $this->exts->parse_date($invoiceDate, 'M d# Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $parsed_date);

                // Download invoice if it not exisited
                if ($this->exts->invoice_exists($invoiceName)) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                } else {
                    try {
                        $this->exts->log('Click download button');
                        $download_button->click();
                    } catch (\Exception $exception) {
                        $this->exts->log('Click download button by javascript');
                        $this->exts->executeSafeScript("arguments[0].click()", [$download_button]);
                    }
                    sleep(5);
                    $handles = $this->exts->get_all_tabs();
                    if (count($handles) > 1) {
                        $this->exts->switchToTab(end($handles));
                    }
                    $this->exts->moveToElementAndClick('.printButton');
                    sleep(5);
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
                    $handles = $this->exts->get_all_tabs();
                    if (count($handles) > 1) {
                        $tab = $this->exts->switchToTab(end($handles));
                        $this->exts->closeTab($tab);
                        $handles = $this->exts->get_all_tabs();
                        $this->exts->switchToTab($handles[0]);
                    }
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
