<?php

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

	// Server-Portal-ID: 52779 - Last modified: 30.01.2025 13:52:53 UTC - User: 1

	public $baseUrl = 'https://www.flaschenpost.de/';
	public $loginUrl = 'https://www.flaschenpost.de/login';

	public $username_selector = 'ion-input input[autocomplete="email"],input#emailLogin';
	public $password_selector = 'input[name="password"]';
	public $submit_login_selector = '.main_content_wrapper button[type="button"],form[data-validate="login"] button[type="submit"], button>ion-ripple-effect';

	public $check_login_failed_selector = 'div[class*="secondary-red"],div.alert-danger';
	public $check_login_success_selector = 'a[href="/account/logout/"], div[data-testid*="Liefer"]';

	public $isNoInvoice = true;
	public $zipCode = '';
	/**
	 * Entry Method thats called for a portal
	 * @param Integer $count Number of times portal is retried.
	 */
	private function initPortal($count)
	{
		$this->exts->log('Begin initPortal ' . $count);
		$this->zipCode = isset($this->exts->config_array["zip_code"]) ? $this->exts->config_array["zip_code"] : '';
		$this->zipCode = '04105'; // harcoded for testing
		// Load cookies
		$this->exts->loadCookiesFromFile();
		sleep(1);
		$this->exts->openUrl($this->baseUrl);
		sleep(10);
		$this->check_solve_blocked_page();
		$this->exts->capture('1-init-page');
		//click solve to login check
		if ($this->exts->exists('a[href="/Account/Overview"]')) {
			$this->exts->moveToElementAndClick('a[href="/Account/Overview"]');
			sleep(15);
		}
		if ($this->zipCode == null || $this->zipCode == '') {
			$this->exts->log('zip_code is empty');
			$this->exts->loginFailure(1);
		}
		// If user hase not logged in from cookie, clear cookie, open the login url and do login
		if ($this->exts->getElement($this->check_login_success_selector) == null) {
			$this->exts->clearCookies();
			$this->exts->log('NOT logged via cookie');
			$this->exts->openUrl($this->loginUrl);
			sleep(10);
			// $this->check_solve_blocked_page();
			$this->check_solve_cloudflare_login();

			if ($this->exts->exists('div.main_consent_modal button.fp_button_primary')) {
				$this->exts->moveToElementAndClick('div.main_consent_modal button.fp_button_primary');
				sleep(5);
			}
			if ($this->exts->exists('button.fp_footer_changeZipCode')) {
				$this->exts->moveToElementAndClick('button.fp_footer_changeZipCode');
				sleep(1);
			}
			$this->enteZipCode();
			sleep(7);
			$this->check_solve_cloudflare_login();
			sleep(8);
			for ($i = 0; $i < 8; $i++) {
				$this->exts->capture('site-notworking-page-' . $i);
				$err_msg1 = $this->exts->extract('div#main-frame-error h1 span');
				if ($this->exts->exists('div#main-frame-error h1 span')) {

					$this->exts->openUrl($this->loginUrl);
					sleep(20);
					$this->check_solve_cloudflare_login();
				} else {
					break;
				}
			}

			$this->checkFillLogin(1);
			sleep(10);
			$this->check_solve_cloudflare_login();
			if (
				strpos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'eingaben noch einmal') !== false ||
				strpos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'und versuche es nochmal') !== false
			) {
				$this->exts->loginFailure(1);
			}
			$this->exts->capture('2-after-login-submitted');
			if ($this->exts->getElement($this->username_selector) != null) {
				$this->checkFillLogin(2);
				sleep(15);
			}
			$this->check_solve_cloudflare_login();
			if ($this->exts->getElement($this->username_selector) != null) {
				$this->checkFillLogin(3);
				sleep(15);
			}

			//click solve to login check
			if (
				strpos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'eingaben noch einmal') !== false ||
				strpos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'und versuche es nochmal') !== false
			) {
				$this->exts->loginFailure(1);
			}
			if ($this->exts->exists('a[href*="account_overview"]')) {
				$this->exts->capture("after-login-submit");
				$this->exts->moveToElementAndClick('a[href*="account_overview"]');
				sleep(15);
			}

			// then check user logged in or not
			if ($this->exts->getElement($this->check_login_success_selector) != null) {
				sleep(3);
				$this->exts->log(__FUNCTION__ . '::User logged in');
				$this->exts->capture("3-login-success");
				// Final, check no invoice

				if ($this->exts->exists('div[data-testid*="Liefer"]')) {
					$this->exts->moveToElementAndClick('div[data-testid*="Liefer"]');
				}
				sleep(10);

				$this->downloadInvoice();

				if ($this->isNoInvoice) {
					$this->exts->no_invoice();
				}
				$this->exts->success();
			} else {
				$this->exts->log(__FUNCTION__ . '::Use login failed');
				if (
					strpos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'eingaben noch einmal') !== false ||
					strpos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'und versuche es nochmal') !== false
				) {
					$this->exts->loginFailure(1);
				} else if ($this->zipCode == null || $this->zipCode == '') {
					$this->exts->log('zip_code is empty');
					$this->exts->loginFailure(1);
				} else {
					$this->exts->loginFailure();
				}
			}
		}
	}

	public function waitFor($selector, $seconds = 10)
	{
		for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
			$this->exts->log('Waiting for Selectors.....');
			sleep($seconds);
		}
	}

	private function checkFillLogin($count = 0)
	{
		$this->exts->log("Fill Form " . $count);
		$this->waitFor($this->username_selector);

		if ($this->exts->getElement($this->username_selector) != null) {
			$this->exts->capture("2-login-page");

			$this->exts->log("Enter Username");
			$this->exts->moveToElementAndType($this->username_selector, $this->username);
			sleep(4);

			$this->exts->log("Enter Password");
			$this->exts->moveToElementAndType($this->password_selector, $this->password);
			sleep(4);

			$this->exts->capture("2-login-page-filled");
			$this->exts->moveToElementAndClick($this->submit_login_selector);
			sleep(7);

			$isLoginError = $this->exts->extract('div#password-error');
			$this->exts->log("isLoginError:: " . $isLoginError);

			if (strpos(strtolower($isLoginError), strtolower('Passwort sind nicht korrekt')) !== false) {
				$this->exts->loginFailure(1);
			}

			if ($this->exts->getElement($this->password_selector)) {
				sleep(4);
				$this->exts->moveToElementAndClick($this->submit_login_selector);
			}
			if ($this->exts->getElement($this->password_selector)) {
				sleep(4);
				$this->exts->moveToElementAndClick($this->submit_login_selector);
			}
		} else {
			$this->exts->log(__FUNCTION__ . '::Login page not found');
			$this->exts->capture("2-login-page-not-found");
		}
	}

	private function check_solve_blocked_page()
	{
		$this->exts->capture_by_chromedevtool("blocked-page-checking");

		for ($i = 0; $i < 5; $i++) {
			if ($this->exts->check_exist_by_chromedevtool('div[style="display: grid;"] > div > div')) {
				$this->exts->capture_by_chromedevtool("blocked-by-cloudflare");
				$this->exts->refresh();
				sleep(10);

				$this->exts->click_by_xdotool('div[style="display: grid;"] > div > div', 30, 28);
				sleep(15);

				if (!$this->exts->check_exist_by_chromedevtool('div[style="display: grid;"] > div > div')) {
					break;
				}
			} else {
				break;
			}
		}
	}

	private function check_solve_cloudflare_login($refresh_page = false)
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
			$this->exts->click_by_xdotool('div:has(>input[name^="cf"][name$="response"])', 30, 28);
			sleep(5);
			$this->exts->capture("cloudflare-clicked-1", true);
			sleep(3);
			if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
				$this->exts->click_by_xdotool('div:has(>input[name^="cf"][name$="response"])', 30, 28);
				sleep(5);
				$this->exts->capture("cloudflare-clicked-2", true);
				sleep(15);
			}
			if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
				$this->exts->click_by_xdotool('div:has(>input[name^="cf"][name$="response"])', 30, 28);
				sleep(5);
				$this->exts->capture("cloudflare-clicked-3", true);
				sleep(15);
			}
		}
	}


	private function enteZipCode()
	{
		$this->exts->log('isVisible Zip code: ' . $this->zipCode);
		if ($this->zipCode != null) {
			$this->exts->capture("2-login-zip-code");
			$this->exts->log('Enter Zip code: ' . $this->zipCode);
			$this->exts->moveToElementAndType('input.fp_input, [style="z-index: 20003;"], input[inputmode="numeric"]', $this->zipCode);
			sleep(2);
			// $this->type_text_by_xdotool($this->zipCode);
			// sleep(2);
			$this->exts->capture("2-login-zip-filled");
			if ($this->exts->exists('.ion-page button.fp_button.fp_button_large.fp_button_primary')) {
				$this->exts->moveToElementAndClick('.ion-page button.fp_button.fp_button_large.fp_button_primary');
			} else {
				$tab_button = $this->exts->getElements('.ion-page button.fp_button.fp_button_large.fp_button_primary');
				if ($tab_button != null) {
					try {
						$this->exts->log('Click tab_button...');
						$tab_button->click();
					} catch (Exception $e) {
						$this->exts->log("Click tab_button by javascript ");
						$this->exts->execute_javascript('arguments[0].click()', [$tab_button]);
					}
				}
			}
			sleep(10);
		}
	}

	public function downloadInvoice()
	{
		sleep(25);
		$this->exts->log("Begin download invoice");

		$this->exts->capture('4-List-invoice');

		// Get the current year
		$currentYear = date('Y');

		// Open the ion-select dropdown
		$this->exts->execute_javascript("
		let selectElement = document.querySelector('ion-select');
		selectElement.shadowRoot.querySelector('.select-text').click();
	");

		sleep(10);
		// Select the option inside the shadow DOM using JavaScript
		$this->exts->execute_javascript("
		let radioOptions = document.querySelectorAll('ion-select-popover ion-item ion-radio');
		
		// Loop through each ion-radio element
		radioOptions.forEach(option => {
			// Get the text content of the ion-radio element
			let optionText = option.textContent.trim();

			console.log(optionText); // Log the text content to the console

			// If the text matches the current year, click the option
			if (optionText === '$currentYear') {
				option.click();
			}
		});
	");


		sleep(15);

		try {
			if ($this->exts->getElement('div[class*="ion-activatable"]') != null) {
				$receipts = $this->exts->getElements('div[class*="ion-activatable"]');
				$invoices = array();

				for ($i = 0; $i < count($receipts); $i++) {
					// Get the tags under the current receipt
					$row = $this->exts->getElements('div[class*="ion-activatable"]')[$i];
					$tags = $this->exts->getElements('div', $row);

					// Check if there are any tags
					if (count($tags) >= 1) {
						// Scroll to the current element
						$this->exts->execute_javascript("arguments[0].scrollIntoView(true);", [$tags[0]]);

						// Locate the span elements within the current receipt
						$spanElements = $this->exts->getElements('span', $row);
						if (!empty($spanElements)) {
							$inviceName = $spanElements[0]->getAttribute('innerText');
							preg_match('/\d+/', $inviceName, $matches);
							$orderNumber = $matches[0];

							// Scroll to the download button (optional if already scrolled to the container)
							$this->exts->execute_javascript("arguments[0].scrollIntoView(true);", [$tags[0]]);

							try {
								$this->exts->log('Click download button');
								$tags[0]->click();
							} catch (\Exception $exception) {
								$this->exts->log('Click download button by JavaScript');
								$this->exts->execute_javascript("arguments[0].click();", [$tags[0]]);
							}

							// Handle file download
							$invoiceFileName = $orderNumber . '.pdf';
							$downloaded_file = $this->exts->download_current($invoiceFileName, 3);
							if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
								$this->exts->new_invoice($orderNumber, "", "", $invoiceFileName);
								sleep(1);
							} else {
								$this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
							}

							$this->isNoInvoice = false;

							// Optional: Check if a specific element exists and click it
							if ($this->exts->exists('div[class*="tw-ws-cursor-pointer"]')) {
								$this->exts->moveToElementAndClick('div[class*="tw-ws-cursor-pointer"]');
								sleep(8);
							}
						}
					}
				}
			}
		} catch (\Exception $exception) {
			$this->exts->log("Exception downloading invoice " . $exception->getMessage());
		}
	}

	private function download_current($filename, $delay_before_print = 0, $skip_check = false)
	{
		try {
			$file_ext = $this->exts->get_file_extension($filename);
			$this->exts->no_margin_pdf = 0;
			$filepath = '';
			// Put some delay if page rendering takes time
			// If page is not loaded by ajax, then such delay is not required
			if ($delay_before_print > 0) {
				sleep($delay_before_print);
			}
			// Trigger print
			// Set window title to print, as chrome use window title to save pdf file
			$this->exts->execute_javascript('document.title = "print"; window.print();');
			sleep(10);
			// Wait for completion of file download
			$this->exts->wait_and_check_download($file_ext);
			// find new saved file and return its path
			$filepath = $this->exts->find_saved_file($file_ext, $filename);
		} catch (\Exception $exception) {
			$this->exts->log('ERROR in download_capture.');
			$this->exts->log(print_r($exception, true));
		}
		// find new saved file and return its path
		return $filepath;
	}
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
