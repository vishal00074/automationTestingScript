<?php // updated download code handle empty invoice case 

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

	// Server-Portal-ID: 3179 - Last modified: 11.06.2025 14:28:42 UTC - User: 1

	public $baseUrl = "https://login.sipgate.com/";
	public $afterLoginUrl = "https://app.sipgate.com/";
	public $basicInvoiceUrl = "https://app.sipgatebasic.de/account";
	public $teamInvoiceUrl = "https://app.sipgate.com/w0/team/settings/invoices";
	public $teamInvoiceLatestUrl = "https://app.sipgate.com/administration/invoices/settings/invoices";
	public $loginUrl = "https://login.sipgate.com/";
	public $username_selector = "form.flex-container input[name=username],input#username";
	public $username_selector_1 = "div.login__body form input#username";
	public $password_selector = "input#password, form.flex-container input[name=password]";
	public $password_selector_1 = "div.login__body form input#password";
	public $submit_button_selector = "form.flex-container button[type=submit],button#kc-login";
	public $submit_button_selector_1 = "div.login__body form button[type=submit], button.g-recaptcha.login__submit[data-action=submit]";
	public $linq_username_selector = 'input#username';
	public $linq_password_selector = 'input#password';
	public $linq_submit_selector = 'button.login__submit';
	public $login_tryout = 0;
	public $restrictPages = 0;
	public $remember_me_selector = 'input#rememberMe';
	private function initPortal($count)
	{

		$this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

		$this->exts->openUrl($this->loginUrl);
		sleep(5);
		$this->exts->capture("Home-page-without-cookie");

		$isCookieLoginSuccess = false;
		if ($this->exts->loadCookiesFromFile()) {
			sleep(2);

			$this->exts->openUrl($this->afterLoginUrl);
			sleep(5);
			$this->exts->capture("Home-page-with-cookie");

			if ($this->checkLogin()) {
				$isCookieLoginSuccess = true;
			} else {
				$this->clearChrome();
				sleep(2);

				$this->exts->openUrl($this->loginUrl);
				sleep(5);
			}
		} else {
			$this->clearChrome();
			sleep(2);
			$this->exts->openUrl($this->loginUrl);
			sleep(5);
		}

		if (!$isCookieLoginSuccess) {
			if ($this->exts->exists('a[id="login"][href*="clinq"]')) {
				$this->exts->openUrl('https://www.clinq.app');
				sleep(10);
			}
			$this->exts->openUrl($this->loginUrl);
			sleep(10);
			$this->fillForm(0);
			sleep(10);
			if ($this->exts->exists('a[href="javascript:location.replace(location.href)"]') && strpos($this->exts->extract('.jsonly > h1 + p'), 'not correct') !== false && !$this->checkLogin()) {
				$this->exts->moveToElementAndClick('a[href="javascript:location.replace(location.href)"]');
				sleep(10);
				$this->fillForm(0);
			}


			$this->exts->waitTillAnyPresent(explode(',', 'input[name="emailCode"], form#loginform input#otp'), 10);
			if ($this->exts->exists('input[name="emailCode"], form#loginform input#otp')) {
				$this->checkFillTwoFactor();
			}

			if ($this->exts->exists('input[name="trust-device"].trust_device__submit')) {
				$this->exts->moveToElementAndClick('input[name="trust-device"].trust_device__submit');
				sleep(10);
			}

			$isErrorMessage = $this->exts->execute_javascript('document.body.innerHTML.includes("Invalid username or email.")');
			$isInvalidTwoFA = $this->exts->execute_javascript('document.body.innerHTML.includes("Invalid confirmation code.")');

			$this->exts->log('isErrorMessage:' . $isErrorMessage);

			if ($this->checkLogin()) {
				$this->exts->capture("LoginSuccess-if");
				$this->exts->log(__FUNCTION__ . '::User logged in');
				$this->exts->capture("3-login-success");

				if (stripos($this->exts->getUrl(), "secure.live.sipgate.de/settings/products/change") !== FALSE) {
					$this->exts->account_not_ready();
				} else if (strpos($this->exts->getUrl(), 'clinq.app') !== false) {
					$this->invoicePage();
				} else {
					$curent_w = explode(
						'/',
						end(explode('sipgate.com/', $this->exts->getUrl()))
					)[0];
					$this->afterLoginUrl = 'https://app.sipgate.com/' . $curent_w . '/connections/phonelines/p0';
					sleep(10);
					$this->exts->openUrl($this->afterLoginUrl);
					sleep(10);

					$this->processAfterLogin(0);
				}
				$this->exts->success();
			} else if (strpos($this->exts->extract('div.login div.alert--error'), 'passwor') !== false) {
				$this->exts->capture("LoginFailed");
				$this->exts->loginFailure(1);
			} else if ($isErrorMessage) {
				$this->exts->capture("LoginFailed");
				$this->exts->log("Invalid username or email.");
				$this->exts->loginFailure(1);
			} else if ($isInvalidTwoFA) {
				$this->exts->capture("LoginFailed");
				$this->exts->log("Invalid confirmation code.");
				$this->exts->loginFailure(1);
			} else {
				$this->exts->log('Login failed');
				$this->exts->capture("LoginFailed");
				$this->exts->loginFailure();
			}
		} else {
			$this->exts->capture("LoginSuccess-else");

			$this->exts->log(__FUNCTION__ . '::User logged in');
			$this->exts->capture("3-login-success");

			if (stripos($this->exts->getUrl(), "secure.live.sipgate.de/settings/products/change") !== FALSE) {
				$this->exts->account_not_ready();
			} else if (strpos($this->exts->getUrl(), 'clinq.app') !== false) {
				$this->invoicePage();
			} else {
				$curent_w = explode(
					'/',
					end(explode('sipgate.com/', $this->exts->getUrl()))
				)[0];
				$this->afterLoginUrl = 'https://app.sipgate.com/' . $curent_w . '/connections/phonelines/p0';
				sleep(10);
				$this->exts->openUrl($this->afterLoginUrl);
				sleep(5);

				$this->processAfterLogin(0);
			}
			$this->exts->success();
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
			sleep(1);
		}
		$this->exts->type_key_by_xdotool('Tab');
		sleep(1);
		$this->exts->type_key_by_xdotool('Return');
		sleep(1);
		$this->exts->type_key_by_xdotool('a');
		sleep(1);
		$this->exts->type_key_by_xdotool('Return');
		sleep(3);
		$this->exts->capture("clear-page");
		for ($i = 0; $i < 5; $i++) {
			$this->exts->type_key_by_xdotool('Tab');
			sleep(1);
		}
		$this->exts->type_key_by_xdotool('Return');
		sleep(15);
		$this->exts->capture("after-clear");
	}

	function fillForm($count)
	{
		$this->exts->log("Begin fillForm " . $count);
		$this->exts->waitTillPresent($this->username_selector, 5);
		try {
			if ($this->exts->querySelector($this->username_selector) != null) {

				$this->exts->capture("1-pre-login");
				$this->exts->log("Enter Username");
				$this->exts->moveToElementAndType($this->username_selector, $this->username);
				sleep(2);

				if ($this->exts->exists($this->remember_me_selector)) {
					$this->exts->click_element($this->remember_me_selector);
					sleep(2);
				}

				if ($this->exts->exists($this->submit_button_selector)) {
					$this->exts->click_by_xdotool($this->submit_button_selector);
					sleep(7);
				}

				$isErrorMessage = $this->exts->execute_javascript('document.body.innerHTML.includes("Invalid username or email.")');

				$this->exts->log('isErrorMessage:' . $isErrorMessage);

				if (!$isErrorMessage) {
					$this->exts->log("Enter Password");
					$this->exts->moveToElementAndType($this->password_selector, $this->password);
					sleep(2);
					$this->exts->capture("1-login-page-filled");
					sleep(5);
					if ($this->exts->exists($this->submit_button_selector)) {
						$this->exts->click_by_xdotool($this->submit_button_selector);
					}
					sleep(5);
					if ($this->exts->getElement($this->password_selector) != null) {
						$this->exts->moveToElementAndType($this->password_selector, $this->password);
						$this->checkFillRecaptcha();
						sleep(2);
						$this->exts->capture("1-login-page-filled");
						sleep(5);
						if ($this->exts->exists($this->submit_button_selector)) {
							$this->exts->click_by_xdotool($this->submit_button_selector);
						}
						sleep(5);
					}
					$isErrorPassMessage = $this->exts->execute_javascript('document.body.innerHTML.includes("Invalid password.")');
					$this->exts->log('isErrorPassMessage:' . $isErrorPassMessage);
					if (!$isErrorPassMessage && !$this->checkLogin()) {
						$this->findPasswordPage('input[name="emailCode"], form#loginform input#otp');
					}
				}
			}
		} catch (\Exception $exception) {

			$this->exts->log("Exception filling loginform " . $exception->getMessage());
		}
	}

	private function findPasswordPage($selector, $button = null)
	{
		$this->exts->waitTillAnyPresent(explode(',', $selector), 10);
		$timeout = 200; // Max wait time in seconds
		$interval = 5;  // Time to wait between checks (adjust as needed)
		$startTime = time();


		while (time() - $startTime < $timeout) {
			$this->exts->log("Finding selector " . time());
			if ($this->exts->exists($selector)) {
				$this->exts->log("selector Found");
				break;
			}
			if ($button != null) {
				$this->exts->click_by_xdotool($button);
			}

			$this->exts->waitTillAnyPresent(explode(',', $selector), 10);
			sleep($interval);
		}

		// Optional: Handle case where the element was not found within 200 seconds
		if (!$this->exts->exists($selector)) {
			$this->exts->log("selector not found within 200 seconds.");
		}
	}

	function checkLogin()
	{
		$this->exts->log("Begin checkLogin ");
		$isLoggedIn = false;

		if ($this->exts->getElement('a[href*="/logout"], a[href*="/channel/"], , a[href*="/users"], a[href*="/groups"]') != null && !$this->exts->exists($this->password_selector_1) && !$this->exts->exists($this->password_selector)) {
			$isLoggedIn = true;
		} else if ($this->exts->getElement("body[data-tracking-email*=\"@\"]") != null) {
			$isLoggedIn = true;
		} else if ($this->exts->getElement("[data-test-selector=\"app-web-authenticated\"]") != null) {
			$isLoggedIn = true;
		}

		return $isLoggedIn;
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
				$this->exts->log("Textarea count: " . count($recaptcha_textareas));
				for ($i = 0; $i < count($recaptcha_textareas); $i++) {
					$this->exts->execute_javascript("arguments[0].innerHTML = '" . $this->exts->recaptcha_answer . "';", [$recaptcha_textareas[$i]]);
				}
				sleep(2);
				$this->exts->capture('recaptcha-filled');

				// Step 2, check if callback function need executed
				$gcallbackFunction = "onSubmit";
				$this->exts->log('Callback function: ' . $gcallbackFunction);
				if ($gcallbackFunction != null) {
					$this->exts->execute_javascript($gcallbackFunction . '("' . $this->exts->recaptcha_answer . '");');
					sleep(10);
				}
			}
		} else {
			$this->exts->log(__FUNCTION__ . '::Not found reCaptcha');
		}
	}

	private function checkFillTwoFactor()
	{
		$two_factor_selector = 'input[name="emailCode"], form#loginform input#otp';
		$two_factor_message_selector = 'form#loginform div.otpMessage';
		$two_factor_submit_selector = 'input[type="submit"][class="login__submit"], form#loginform button#kc-login';
		$this->exts->waitTillPresent($two_factor_selector, 10);
		if ($this->exts->querySelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
			$this->exts->log("Two factor page found.");
			$this->exts->capture("2.1-two-factor");
			if ($this->exts->getElement($two_factor_message_selector) != null) {
				$this->exts->two_factor_notif_msg_en = $this->exts->extract($two_factor_message_selector);
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
				$this->exts->click_by_xdotool($two_factor_selector);
				sleep(2);
				$this->exts->type_text_by_xdotool($two_factor_code);

				$this->exts->log("checkFillTwoFactor: Clicking submit button.");
				sleep(3);
				$this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);


				$this->exts->click_by_xdotool($two_factor_submit_selector);
				sleep(15);
				if ($this->exts->querySelector($two_factor_selector) == null) {
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
		}
	}

	function processAfterLogin($count = 0)
	{
		try {
			if ($this->exts->getElement('a[href*="/team/settings"], [class*="HistoryOverview"]') != null) {
				$this->exts->log("Team Account");
				$this->teamInvoiceUrl = explode('/connections', $this->afterLoginUrl)[0] . '/team/settings/invoices';
				$this->exts->openUrl($this->teamInvoiceUrl);


				$this->exts->waitTillPresent('button[title="Close"]', 15);
				if ($this->exts->exists('button[title="Close"]')) {
					$this->exts->moveToElementAndClick('button[title="Close"]');
				}

				$this->exts->openUrl($this->teamInvoiceLatestUrl);
				$this->exts->waitTillPresent('tbody.hyphens', 30);
				if ($this->exts->exists('tbody.hyphens')) {
					$this->exts->capture("process-Invoices-Latest");
					$this->processInvoicesLatest();
				} else {
					$this->processTeamPage(0);
					if ($this->exts->exists('a[href="/administration/invoices/settings/invoices"]')) {
						$this->exts->moveToElementAndClick('a[href="/administration/invoices/settings/invoices"]');
						sleep(15);
						$optionSelectors = array();
						$selectElements = $this->exts->getElements('select[aria-label="Jahr"] option');
						$this->exts->log("selectYearElements " . count($selectElements));

						if (count($selectElements) > 0) {
							if ((int)@$this->restrictPages == 0) {
								foreach ($selectElements as $selectElement) {
									$elementValue = trim($selectElement->getAttribute('value'));
									$this->exts->log("elementValue - " . $elementValue);
									$optionSelectors[] = $elementValue;
								}
							} else {
								$elementValue = trim($selectElements[0]->getAttribute('value'));
								$optionSelectors[] = $elementValue;
							}

							if (!empty($optionSelectors)) {
								foreach ($optionSelectors as $optionSelector) {

									$this->exts->execute_javascript('let selectBox = document.querySelector(\'select[aria-label="Jahr"]\');
                                        selectBox.value = ' . $optionSelector . ';
                                        selectBox.dispatchEvent(new Event("change"));');
									sleep(10);

									$this->exts->capture("orders-" . $optionSelector);
									$this->processInvoices();
								}
							}
						}
					}
				}
			} else {
				$this->exts->log("Basic Account");

				//Click on button
				if ($this->exts->getElement("header section:nth-child(2) div[role=\"button\"] button") != null) {
					$this->exts->moveToElementAndClick("header section:nth-child(2) div[role=\"button\"] button");
					sleep(2);
				} else {
					$this->exts->log("Basic Account Menu Button not found");
					try {
						$menuButtons = $this->exts->querySelectorAll("header section div[role=\"button\"] button");
						if (count($menuButtons) > 0) {
							$menuButtons[count($menuButtons) - 1]->click();
							sleep(2);
						}
					} catch (\Exception $exception) {
						$this->exts->log("Exception getting menu button in basic " . $exception->getMessage());
					}
				}

				// https://secure.live.sipgate.de/settings/products/change
				if (stripos($this->exts->getUrl(), "secure.live.sipgate.de/settings/products/change") !== FALSE) {
					$this->exts->account_not_ready();
				} else {
					$menuItems = $this->exts->querySelectorAll("div[role=\"menu\"] span[role=\"menuitem\"]");
					if (count($menuItems) > 0) {
						$menuItems[1]->click();
						sleep(2);

						$this->exts->openUrl($this->basicInvoiceUrl);
						sleep(15);

						$this->getBasicInvoices();
					} else if ($this->exts->exists('section nav div[class*="AppNavigation"] a[href*="app.sipgatebasic.de%2Faccount"]')) {
						$this->exts->moveToElementAndClick('section nav div[class*="AppNavigation"] a[href*="app.sipgatebasic.de%2Faccount"]');
						sleep(5);

						$this->getBasicInvoices();
					} else if ($this->exts->exists('section nav a[href*="app.sipgatebasic.de%2Faccount"]')) {
						$this->exts->moveToElementAndClick('section nav a[href*="app.sipgatebasic.de%2Faccount"]');
						sleep(5);

						$this->getBasicInvoices();
					} else {
						$this->exts->log("Basic Account Menu Invoice Button not found");
					}
				}
			}


			// Final, check no invoice
			if ($this->isNoInvoice) {
				$this->exts->no_invoice();
			}
			$this->exts->success();

		} catch (\Exception $exception) {
			$this->exts->log("Exception checking account type " . $exception->getMessage());
		}
	}

	public function invoicePage()
	{
		$this->exts->log("Start invoice page");

		$this->exts->openUrl('https://www.clinq.app/payment/invoices');
		sleep(15);

		$this->downloadClinqInvoice();
	}

	public $totalFiles = 0;
	public function downloadClinqInvoice()
	{
		$this->exts->log("Begin download invoice");

		$this->exts->capture('4-List-Clinq-invoice');

		try {
			if ($this->exts->getElement('table[class*="InvoiceTable"] tbody tr') != null) {
				$receipts = $this->exts->getElements('table[class*="InvoiceTable"] tbody tr');
				$invoices = array();
				foreach ($receipts as $i => $receipt) {
					$tags = $this->exts->getElements('td', $receipt);
					if (count($tags) >= 3 && $this->exts->getElement('td span[class*="InvoiceTable_downloadLink"]', $receipt) != null) {
						$receiptDate = $tags[0]->getText();
						$rep_date = trim(explode(',', $receiptDate)[0]) . ',';
						$receiptDate = str_replace($rep_date, '', $receiptDate);
						$receiptDate = trim(preg_split('/\d+\:\d+/', $receiptDate)[0]);
						$receiptUrl = $this->exts->getElement('td span[class*="InvoiceTable_downloadLink"]', $receipt);
						$this->exts->execute_javascript(
							"arguments[0].setAttribute(\"id\", \"invoice\" + arguments[1]);",
							array($receiptUrl, $i)
						);

						$receiptUrl = 'td span#invoice' . $i;
						$receiptName = trim($tags[1]->getText());
						$receiptFileName = !empty($receiptName) ? $receiptName . '.pdf' : '';
						$parsed_date = $this->exts->parse_date($receiptDate, 'M j, Y', 'Y-m-d');
						$receiptAmount = '';

						$this->exts->log("Invoice Date: " . $receiptDate);
						$this->exts->log("Invoice URL: " . $receiptUrl);
						$this->exts->log("Invoice Name: " . $receiptName);
						$this->exts->log("Invoice FileName: " . $receiptFileName);
						$this->exts->log("Invoice parsed_date: " . $parsed_date);
						$this->exts->log("Invoice Amount: " . $receiptAmount);
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

				$this->exts->log("Invoice found: " . count($invoices));

				foreach ($invoices as $invoice) {
					$this->totalFiles += 1;
					if ($this->exts->getElement($invoice['receiptUrl']) != null) {

						if ($this->exts->document_exists($invoice['receiptFileName'])) {
							continue;
						}

						$this->exts->moveToElementAndClick($invoice['receiptUrl']);

						$this->exts->wait_and_check_download('pdf');

						$downloaded_file = $this->exts->find_saved_file('pdf', $invoice['receiptFileName']);
						sleep(1);

						if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
							$this->exts->log("create file");
							$this->exts->new_invoice($invoice['receiptName'], $invoice['parsed_date'], $invoice['receiptAmount'], $downloaded_file);
						}
					}
				}
			}
		} catch (\Exception $exception) {
			$this->exts->log("Exception downlaoding invoice " . $exception->getMessage());
		}
	}

	function processTeamPage($count = 0)
	{
		sleep(15);
		$this->exts->capture("processTeamPage");
		try {
			$invoice_list_frame = $this->exts->getElement('iframe[src*="/settings/invoices"], iframe[src*="%2Fsettings%2Finvoices"], iframe[src*="team-de.live.sipgate.com"]');

			$this->switchToFrame($invoice_list_frame);

			sleep(15);

			$optionSelectors = array();
			$selectElements = $this->exts->getElements("select[id=\"year-picker-for-invoices\"] option");
			$this->exts->log("selectYearElements " . count($selectElements));

			if (count($selectElements) > 0) {
				if ((int)@$this->restrictPages == 0) {
					foreach ($selectElements as $selectElement) {
						$elementValue = trim($selectElement->getAttribute('value'));
						$this->exts->log("elementValue - " . $elementValue);
						$optionSelectors[] = $elementValue;
					}
				} else {
					$elementValue = trim($selectElements[0]->getAttribute('value'));
					$optionSelectors[] = $elementValue;
				}

				if (!empty($optionSelectors)) {
					foreach ($optionSelectors as $optionSelector) {

						$this->exts->execute_javascript('
                        let selectBox = document.querySelector("#year-picker-for-invoices");
                        if (selectBox) {
                            selectBox.value = ' . $optionSelector . ';
                            selectBox.dispatchEvent(new Event("change"));
                        }
                    ');

						sleep(10);

						if ($this->exts->getElement($this->password_selector) != null || stripos($this->exts->getUrl(), "/ap/signin?") !== FALSE) {
							if ($this->login_tryout == 0) {
								$this->fillForm(0);
							} else {
								$this->exts->init_required();
							}
						}

						$this->exts->capture("orders-" . $optionSelector);
						$this->getTeamInvoices(0);
					}
				}
			} else {
				$this->exts->log("No Year selector found");

				$this->getTeamInvoices(0);
			}
		} catch (\Exception $exception) {
			$this->exts->log("Exception getting years selectors " . $exception->getMessage());
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

	function getTeamInvoices($count)
	{
		try {
			$invoiceElements = $this->exts->querySelectorAll("div#invoices_content_per_year div#invoices_list table tbody tr");
			$this->exts->log("Total Team Invoices - " . count($invoiceElements));

			if (count($invoiceElements) > 0) {
				$invoiceDataArr = array();
				foreach ($invoiceElements as $key => $invoiceElement) {
					$columns = $invoiceElement->getElements("td");
					$this->exts->log("Row - " . $key . " columns - " . count($columns));

					if (count($columns) > 0) {
						try {
							$linkElement = $columns[0]->getElement('a[href*=\"/download/\"]');
							$invoice_url = $linkElement->getAttribute("href");
							$this->exts->log("invoice url - " . $invoice_url);

							if (trim($invoice_url) != "") {
								$invoice_name = trim($linkElement->getText());
								$this->exts->log("invoice name - " . $invoice_name);

								$invoice_date = trim($columns[2]->getText());
								$this->exts->log("invoice date - " . $invoice_date);

								$invoice_date = $this->exts->parse_date($invoice_date);
								$this->exts->log("invoice date - " . $invoice_date);

								$invoice_amount = trim($columns[3]->getText());
								$this->exts->log("invoice amount - " . $invoice_amount);

								if (stripos($invoice_amount, "ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬") !== false && stripos($invoice_amount, "ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬") <= 1) {
									$invoice_amount = preg_replace('/[^\d\.,-]/', '', $invoice_amount);
									$invoice_amount = trim($invoice_amount) . ' EUR';
								} else if (stripos($invoice_amount, "ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬") !== false && stripos($invoice_amount, "ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬") > 1) {
									$invoice_amount = preg_replace('/[^\d\.,-]/', '', $invoice_amount);
									$invoice_amount = trim($invoice_amount) . ' EUR';
								} else if (stripos($invoice_amount, "$") !== false && stripos($invoice_amount, "$") <= 1) {
									$invoice_amount = preg_replace('/[^\d\.,-]/', '', $invoice_amount);
									$invoice_amount = trim($invoice_amount) . ' USD';
								} else if (stripos($invoice_amount, "$") !== false && stripos($invoice_amount, "$") > 1) {
									$invoice_amount = preg_replace('/[^\d\.,-]/', '', $invoice_amount);
									$invoice_amount = trim($invoice_amount) . ' USD';
								} else if (stripos($invoice_amount, "ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â£") !== false && stripos($invoice_amount, "ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â£") <= 1) {
									$invoice_amount = preg_replace('/[^\d\.,-]/', '', $invoice_amount);
									$invoice_amount = trim($invoice_amount) . ' GBP';
								} else if (stripos($invoice_amount, "ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â£") !== false && stripos($invoice_amount, "ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â£") > 1) {
									$invoice_amount = preg_replace('/[^\d\.,-]/', '', $invoice_amount);
									$invoice_amount = trim($invoice_amount) . ' GBP';
								}
								$this->exts->log("invoice amount - " . $invoice_amount);

								$invoiceDataArr[] = array(
									'invoice_name' => $invoice_name,
									'invoice_date' => $invoice_date,
									'invoice_amount' => $invoice_amount,
									'invoice_url' => $invoice_url
								);
							}
						} catch (\Exception $exception) {
							$this->exts->log("Exception traversing team invoice " . $exception->getMessage());
						}
					}
				}

				if (!empty($invoiceDataArr) && count($invoiceDataArr) > 0) {
					foreach ($invoiceDataArr as $invoiceData) {
						$filename = "";
						if (trim($invoiceData['invoice_name']) != "") {
							$filename = !empty($invoiceData['invoice_name']) ? $invoiceData['invoice_name'] . ".pdf" : '';
						}
						$this->exts->log("invoice file - " . $filename);

						$invoice_url = trim($invoiceData['invoice_url']);
						$this->exts->log("invoice url - " . $invoice_url);

						$downloaded_file = $this->exts->direct_download($invoice_url, "pdf", $filename);
						$this->exts->log("Invoice downloaded_file - " . $downloaded_file);
						if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
							$this->exts->new_invoice($invoiceData['invoice_name'], $invoiceData['invoice_date'], $invoiceData['invoice_amount'], $downloaded_file);
						}
					}
				}

				$this->exts->openUrl($this->teamInvoiceUrl);
				sleep(15);

				$invoice_list_frame = $this->exts->getElement('iframe[src*="/settings/invoices"], iframe[src*="%2Fsettings%2Finvoices"], iframe[src*="team-de.live.sipgate.com"]');

				$this->switchToFrame($invoice_list_frame);

				sleep(15);
			}
		} catch (\Exception $exception) {
			$this->exts->log("Exception getting team invoices " . $exception->getMessage());
		}
	}

	private function processInvoices()
	{

		$this->exts->waitTillPresent('table > tbody > tr', 30);
		$this->exts->capture("4-invoices-page");
		$invoices = [];
		$rows = count($this->exts->getElements('table > tbody > tr'));
		for ($i = 0; $i < $rows; $i++) {
			$row = $this->exts->getElements('table > tbody > tr')[$i];
			$tags = $this->exts->getElements('td', $row);
			if ($this->exts->getElement('button[aria-label="Rechnung herunterladen"]', $tags[5]) != null) {
				$this->isNoInvoice = false;
				$download_button = $this->exts->getElement('button[aria-label="Rechnung herunterladen"]', $tags[5]);
				$invoiceName = trim($tags[2]->getAttribute('innerText'));
				$invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
				$invoiceDate = trim($tags[0]->getAttribute('innerText'));
				$invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[4]->getAttribute('innerText'))) . ' USD';

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
					try {
						$this->exts->log('Click download button');
						$download_button->click();
					} catch (\Exception $exception) {
						$this->exts->log('Click download button by javascript');
						$this->exts->execute_javascript("arguments[0].click()", [$download_button]);
					}
					sleep(5);
					$this->exts->wait_and_check_download('pdf');
					$downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

					if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
						$this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
					} else {
						$this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
					}
				}
			}
		}
	}

	private function processInvoicesLatest($paging_count = 1)
	{
		$this->exts->waitTillPresent('tbody.hyphens tr', 30);
		$this->exts->capture("4-invoices-page");
		$invoices = [];

		$rows = $this->exts->querySelectorAll('tbody.hyphens tr');
		foreach ($rows as $row) {
			if ($this->exts->querySelector('td:nth-child(7) button', $row) != null) {
				$invoiceUrl = '';
				$invoiceName = 'sipgate-invoice-' . $this->exts->extract('td:nth-child(4)', $row);
				$invoiceAmount =  $this->exts->extract('td:nth-child(6)', $row);
				$invoiceDate =  $this->exts->extract('td:nth-child(2)', $row);


				$downloadBtn = $this->exts->querySelector('td:nth-child(7) button', $row);



				array_push($invoices, array(
					'invoiceName' => $invoiceName,
					'invoiceDate' => $invoiceDate,
					'invoiceAmount' => $invoiceAmount,
					'invoiceUrl' => $invoiceUrl,
					'downloadBtn' => $downloadBtn
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
			$invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd. F Y', 'Y-m-d');
			$this->exts->log('Date parsed: ' . $invoice['invoiceDate']);


			// Exception case in button
			$downloadedFileExcep = $this->exts->click_and_download($invoice['downloadBtn'], 'pdf', $invoiceFileName);
			sleep(7);
			if ($this->exts->exists('button[aria-label="Neu ausstellen"]')) {
				$this->exts->moveToElementAndClick('button[aria-label="Neu ausstellen"]');
				sleep(5);
			}

			if (trim($downloadedFileExcep) != '' && file_exists($downloadedFileExcep)) {
				$this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
				sleep(1);
			} else {
				$downloaded_file = $this->exts->click_and_download($invoice['downloadBtn'], 'pdf', $invoiceFileName);
				if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
					$this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
					sleep(1);
				} else {
					$this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
				}
			}
		}
	}

	function getBasicInvoices()
	{
		try {
			if ($this->exts->exists('.sumome-react-svg-image-container')) {
				$this->exts->moveToElementAndClick('.sumome-react-svg-image-container');
				sleep(2);
			}
			if ($this->exts->getElement("a#showItemizedBillLink") != null) {
				$this->exts->moveToElementAndClick("a#showItemizedBillLink");
				sleep(5);
			}

			$invoiceElements = $this->exts->querySelectorAll("a[href*=\"/app/de.sipgate.balance/singleinvoice?invoice=\"]");
			//$invoiceElements = $this->exts->querySelectorAll('a[href*="/app/de.sipgate.balance/singleinvoice?invoice="]');
			$this->exts->log("Total Basic Invoices - " . count($invoiceElements));

			if (count($invoiceElements) > 0) {
				foreach ($invoiceElements as $invoiceElement) {
					try {
						$invoice_url = $invoiceElement->getAttribute("href");
						$this->exts->log("invoice url - " . $invoice_url);

						$invoice_date = "";
						$invoice_amount = "";
						$invoice_name = "";
						$eleText = trim($invoiceElement->getText());
						$this->exts->log("invoice url text - " . $eleText);

						$tempArr = explode("-", $eleText);
						if (count($tempArr) > 1) {
							$invoice_date = trim($tempArr[1]);
							$invoice_name = trim($tempArr[0]);
						}
						$this->exts->log("invoice name - " . $invoice_name);
						$this->exts->log("invoice date - " . $invoice_date);

						if (trim($invoice_date) != "") {
							$invoice_date = $this->exts->parse_date($invoice_date);
						}
						$this->exts->log("invoice date - " . $invoice_date);

						if (trim($invoice_name) == "") {
							$tempArr = explode("&number=", $invoice_url);
							$invoice_name = trim($tempArr[count($tempArr) - 1]);
						}
						$this->exts->log("invoice name - " . $invoice_name);

						$filename = "";
						if (trim($invoice_name) != "") {
							$filename = !empty($invoice_name) ?  $invoice_name . ".pdf" : '';
						}
						$this->exts->log("invoice file - " . $filename);

						//click_and_download($selector, $file_ext, $filename='', $selector_type='CSS', $wait_time=30)
						if ($this->exts->getElement("a[href*=\"&number=" . $invoice_name . "\"]") != null) {
							$downloaded_file = $this->exts->click_and_download("a[href*=\"&number=" . $invoice_name . "\"]", "pdf", $filename, "CSS", 10);
							$this->exts->log("Invoice downloaded_file - " . $downloaded_file);
						} else {
							$downloaded_file = $this->exts->direct_download($invoice_url, "pdf", $filename);
							$this->exts->log("Invoice downloaded_file - " . $downloaded_file);
						}
						if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
							$this->exts->new_invoice($invoice_name, $invoice_date, $invoice_amount, $downloaded_file);
						}
					} catch (\Exception $exception) {
						$this->exts->log("Exception in traversing the each basic invoice " . $exception->getMessage());
					}
				}
			}
		} catch (\Exception $exception) {
			$this->exts->log("Exception in getting basic invoices " . $exception->getMessage());
		}
	}
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
