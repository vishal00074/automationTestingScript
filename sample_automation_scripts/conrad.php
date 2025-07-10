<?php //  updated login success selector

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

    // Server-Portal-ID: 432 - Last modified: 11.06.2025 14:25:25 UTC - User: 1

    /*Define constants used in script*/
    public $baseUrl = 'https://www.conrad.de/de/account.html';
    public $invoicePageUrl = 'https://www.conrad.de/de/account.html#/invoices';
    public $username_selector = 'app-login input#username';
    public $password_selector = 'app-login input#password';
    public $submit_login_selector = 'app-login [type="submit"]';

    public $check_login_failed_selector = 'app-login .error-label.text-center div';
    public $check_login_success_selector = 'form[action*="logout.html"], button.logoutButton, .cmsFlyout.myAccount a[data-logout="data-logout"], button[data-e2e="logout"]';

    public $restrictPages = 3;
    public $isNoInvoice = true;
    public $totalFiles = 0;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->disable_uBlock_extensions();
        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;

        $this->exts->openUrl($this->baseUrl);
        sleep(2);

        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(3);
        $this->exts->openUrl($this->baseUrl);
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        $this->exts->waitTillPresent($this->check_login_success_selector, 60);
        if ($this->exts->querySelector($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->clearChrome();
            $this->exts->openUrl($this->baseUrl);
            sleep(20);
            for ($i = 0; $i < 5 && $this->exts->exists('div.la-ball-clip-rotate-multiple'); $i++) {
                sleep(10);
            }
            $this->checkFillLogin();
            sleep(15);
            $this->exts->moveToElementAndClick('.cmsCookieNotification__button--reject span.cmsCookieNotification__button__label');
            sleep(3);
            if ($this->exts->querySelector($this->check_login_success_selector) == null && $this->exts->querySelector($this->check_login_failed_selector) == null) {
                $this->exts->openUrl($this->baseUrl);
                sleep(15);
                $this->checkFillLogin();
                sleep(15);
                if ($this->exts->exists('.cmsCookieNotification .cmsCookieNotification__body .cmsCookieNotification__button--accept')) {
                    $this->exts->moveToElementAndClick('.cmsCookieNotification .cmsCookieNotification__body .cmsCookieNotification__button--accept');
                    sleep(5);
                }
            }
        }

        // then check user logged in or not
        if ($this->exts->querySelector($this->check_login_success_selector) != null) {
            sleep(5);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");
            $this->exts->executeSafeScript('var shadow = document.querySelector("#usercentrics-root").shadowRoot; shadow.querySelector("button[data-testid=\'uc-accept-all-button\']").click();');
            sleep(5);

            $this->exts->openUrl($this->invoicePageUrl);
            sleep(5);
            $this->exts->waitTillPresent('select[name="timePeriodProperty"]');
            if ($this->exts->exists('select[name="timePeriodProperty"]')) {
                if ((int) @$this->restrictPages == 0) {
                    $this->changeSelectbox("select[name='timePeriodProperty']", '24');
                } else {
                    $this->changeSelectbox("select[name='timePeriodProperty']", '12');
                }
            }
            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            if (strpos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'passwor') !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function checkFillLogin()
    {
        $this->exts->waitTillPresent($this->password_selector, 20);
        if ($this->exts->querySelector($this->password_selector) != null) {
            sleep(3);
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            $this->exts->capture("2-login-page-filled");
            $this->checkFillRecaptcha();
            $this->exts->moveToElementAndClick($this->submit_login_selector);
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function clearChrome()
    {
        $this->exts->log("Clearing browser history, cookie, cache");
        $this->exts->openUrl('chrome://settings/clearBrowserData');
        sleep(3);
        $this->exts->type_key_by_xdotool('Return');
        sleep(15);
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
                $recaptcha_textareas = $this->exts->getElements($recaptcha_textarea_selector);
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

    private function disable_uBlock_extensions()
    {
        $this->exts->openUrl('chrome://extensions/?id=cjpalhdlnbpafiamejdnhcphjbkeiagm'); // disable Block origin extension
        sleep(2);
        $this->exts->executeSafeScript("
		if(document.querySelector('extensions-manager') != null) {
		    if(document.querySelector('extensions-manager').shadowRoot.querySelector('extensions-detail-view')  != null){
		        var disable_button = document.querySelector('extensions-manager').shadowRoot.querySelector('extensions-detail-view').shadowRoot.querySelector('#enableToggle[checked]');
		        if(disable_button != null){
		            disable_button.click();
		        }
		    }
		}
	");
        sleep(1);
    }

    private function changeSelectbox($select_box = '', $option_value = '')
    {
        $this->exts->waitTillPresent($select_box, 10);
        if ($this->exts->exists($select_box)) {
            $option = $option_value;
            $this->exts->click_by_xdotool($select_box);
            sleep(2);
            $optionIndex = $this->exts->executeSafeScript('
            const selectBox = document.querySelector("' . $select_box . '");
            const targetValue = "' . $option_value . '";
            const optionIndex = [...selectBox.options].findIndex(option => option.value === targetValue);
            return optionIndex;
        ');
            $this->exts->log($optionIndex);
            sleep(1);
            for ($i = 1; $i < $optionIndex; $i++) {
                $this->exts->log('>>>>>>>>>>>>>>>>>> Down');
                // Simulate pressing the down arrow key
                $this->exts->type_key_by_xdotool('Down');
                sleep(1);
            }
            $this->exts->type_key_by_xdotool('Return');
        } else {
            $this->exts->log('Select box does not exist');
        }
    }

    private function getInnerTextByJS($selector_or_object, $parent = null)
    {
        if ($selector_or_object == null) {
            $this->exts->log(__FUNCTION__ . ' Can not get innerText of null');
            return;
        }
        $element = $selector_or_object;
        if (is_string($selector_or_object)) {
            $element = $this->exts->getElement($selector_or_object, $parent);
            if ($element == null) {
                $element = $this->exts->getElement($selector_or_object, $parent, 'xpath');
            }
            if ($element == null) {
                $this->exts->log(__FUNCTION__ . ':: Can not found element with selector/xpath: ' . $selector_or_object);
            }
        }
        if ($element != null) {
            return $this->exts->executeSafeScript("return arguments[0].innerText", [$element]);
        }
    }

    private function processInvoices($pageCount = 1)
    {
        sleep(25);
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        if ($this->exts->exists('[data-e2e="invoiceList"] a[href*="/invoices/"]')) {
            $rows = count($this->exts->querySelectorAll('[data-e2e="invoiceList"] a[href*="/invoices/"]'));
            for ($i = 0; $i < $rows; $i++) {
                $row = $this->exts->querySelectorAll('[data-e2e="invoiceList"] a[href*="/invoices/"]')[$i];
                $tags = $this->exts->querySelectorAll('[data-e2e="invoiceListItem-invoiceNumber"], [data-e2e="invoiceListItem-title"], [data-e2e="invoiceListItem-amount"]', $row);
                sleep(5);
                if (count($tags) >= 3) {
                    $invoiceUrl = $row->getAttribute('href');
                    $invoiceName = trim($this->getInnerTextByJS($tags[1]));
                    $this->exts->log('===================');
                    $invoiceDate = trim($this->getInnerTextByJS($tags[0]));
                    $tempArr = explode(" ", $invoiceDate);
                    $invoiceDate = trim(end($tempArr));
                    $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->getInnerTextByJS($tags[2]))) . ' EUR';
                    $invoiceDownloadButton = $this->exts->querySelector('[data-e2e="invoiceListItem-invoiceDownload"]', $row);

                    array_push($invoices, array(
                        'invoiceName' => $invoiceName,
                        'invoiceDate' => $invoiceDate,
                        'invoiceAmount' => $invoiceAmount,
                        'invoiceUrl' => $invoiceUrl,
                        'invoiceDownloadButton' => $invoiceDownloadButton
                    ));
                    $this->isNoInvoice = false;
                }
            }
        } else {
            $rows = count($this->exts->querySelectorAll('.ce-table a[href*="/invoices/"]'));
            for ($i = 0; $i < $rows; $i++) {
                $row = $this->exts->querySelectorAll('.ce-table a[href*="/invoices/"]')[$i];
                $tags = $this->exts->querySelectorAll('[sort-name="INVOICE_DATE"], [sort-name="INVOICE_NUMBER"], [sort-name="INVOICE_TOTAL"]', $row);
                if (count($tags) >= 3) {
                    $invoiceUrl = $row->getAttribute('href');
                    $invoiceName = trim($this->getInnerTextByJS($tags[1]));
                    $invoiceDate = trim($this->getInnerTextByJS($tags[0]));
                    $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->getInnerTextByJS($tags[2]))) . ' EUR';

                    array_push($invoices, array(
                        'invoiceName' => $invoiceName,
                        'invoiceDate' => $invoiceDate,
                        'invoiceAmount' => $invoiceAmount,
                        'invoiceUrl' => $invoiceUrl,
                        'invoiceDownloadButton' => ''
                    ));
                    $this->isNoInvoice = false;
                }
            }
        }

        // Download all invoices
        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {
            if ((int) @$this->restrictPages != 0 && $this->totalFiles >= 100) {
                break;
            }
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoiceFileName = trim($invoice['invoiceName']) != "" ? $invoice['invoiceName'] . '.pdf' : "";
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            // Download invoice if it not exisited
            if ($this->exts->invoice_exists($invoice['invoiceName'])) {
                $this->exts->log('Invoice existed ' . $invoiceFileName);
            } else {
                $invoiceDownloaded = false;
                if (!empty($invoice['invoiceDownloadButton']) && $invoice['invoiceDownloadButton'] != null) {
                    try {
                        $invoice['invoiceDownloadButton']->click();
                    } catch (\Exception $ex) {
                        $this->exts->log('Click failed');
                        $this->exts->executeSafeScript('arguments[0].click();', [$invoice['invoiceDownloadButton']]);
                    }
                    sleep(10);
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $invoiceDownloaded = true;
                        $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                        sleep(1);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
                }

                //So if document don't get downloaded with above way then it will download by opening invoice page
                if (!$invoiceDownloaded) {
                    $this->exts->openNewTab();
                    sleep(1);
                    if (stripos($invoice['invoiceUrl'], "https://www.conrad.de") === false && stripos($invoice['invoiceUrl'], "https://") === false) {
                        $invoice['invoiceUrl'] = "https://www.conrad.de" . trim($invoice['invoiceUrl']);
                    }
                    $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);
                    $this->exts->openUrl($invoice['invoiceUrl']);
                    sleep(8);
                    //$this->exts->executeSafeScript('window.open("'.$invoice['invoiceUrl'].'","_self")');
                    //sleep(7);
                    if (!$this->exts->exists('.button-invoice-download button')) {
                        $this->exts->refresh();
                        sleep(8);
                    }
                    if (!$this->exts->exists('.button-invoice-download button')) {
                        $this->exts->refresh();
                        sleep(8);
                    }

                    if ($this->exts->exists('.button-invoice-download button')) {
                        $this->exts->moveToElementAndClick('.button-invoice-download button');
                        sleep(5);
                        $this->exts->wait_and_check_download('pdf');
                        $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                        if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                            $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                            sleep(1);
                            $this->totalFiles++;
                        } else if ($this->exts->exists('.button-print button')) {
                            $this->exts->moveToElementAndClick('.button-print button');
                            sleep(5);
                            $this->exts->wait_and_check_download('pdf');
                            $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                                sleep(1);
                                $this->totalFiles++;
                            } else {
                                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                            }
                        } else {
                            $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                        }
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
                    // close new tab too avoid too much tabs
                    $this->exts->switchToInitTab();
                    sleep(1);
                    $this->exts->closeAllTabsButThis();
                    sleep(1);
                }
            }
        }

        if (
            $this->restrictPages == 0 && $pageCount < 50 &&
            $this->exts->querySelector('.pagination__list .pagination__next:not(.disabled)') != null
        ) {
            $pageCount++;
            $this->exts->click_element('.pagination__list .pagination__next:not(.disabled)');
            sleep(5);
            $this->processInvoices($pageCount);
        } else if ($this->restrictPages > 0 && $pageCount < $this->restrictPages && $this->exts->querySelector('.pagination__list .pagination__next:not(.disabled)') != null && $this->totalFiles <= 100) {
            $pageCount++;
            $this->exts->click_element('.pagination__list .pagination__next:not(.disabled)');
            sleep(5);
            $this->processInvoices($pageCount);
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
