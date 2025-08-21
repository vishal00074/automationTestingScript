<?php // adjust sleep time after click submit login form and use check_solve_cloudflare_page function two times after submit login form 
// added $this->exts->notification_uid variable in  two 2fa code
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

	// Server-Portal-ID: 1235036 - Last modified: 18.06.2025 14:58:29 UTC - User: 1

	public $baseUrl = 'https://app.apollo.io/#/login';
	public $loginUrl = 'https://app.apollo.io/#/login';
	public $invoicePageUrl = 'https://app.apollo.io/#/settings/plans/billing';
	public $username_selector = 'input[name="email"]';
	public $password_selector = 'input[name="password"]';
	public $remember_me_selector = '//label[.//div[@data-cy-status="unchecked"]]';
	public $submit_login_selector = 'button[type="submit"]';
	public $check_login_failed_selector = 'form span[id*="desc"]';
	public $check_login_success_selector = '[data-tour="user-profile-button"]';
	public $isNoInvoice = true;
	public $restrictPages = 3;

	/**

	 * Entry Method thats called for a portal

	 * @param Integer $count Number of times portal is retried.

	 */
	private function initPortal($count)
	{
		$this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;
		$this->exts->log('Begin initPortal ' . $count);
		$this->exts->loadCookiesFromFile();
		$this->exts->openUrl($this->loginUrl);
		sleep(3);

		$this->check_solve_cloudflare_page();
		if (!$this->checkLogin()) {
			$this->exts->log('NOT logged via cookie');
			$this->fillForm(0);
			sleep(5);
			$this->checkFillTwoFactor();
			$this->exts->waitTillPresent($this->check_login_success_selector);
		}

		if ($this->checkLogin()) {
			$this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
			$this->exts->capture("LoginSuccess");
			$this->exts->success();

			$this->exts->openUrl($this->invoicePageUrl);
			$this->processInvoices();
			// Final, check no invoice
			if ($this->isNoInvoice) {
				$this->exts->no_invoice();
			}
			$this->exts->success();
		} else {
			if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), " don't match with any") !== false) {
				$this->exts->log("Wrong credential !!!!");
				$this->exts->loginFailure(1);
			} else {
				$this->exts->loginFailure();
			}
		}
	}

	private function check_solve_cloudflare_page()
	{
		$unsolved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) <= 0]';
		$solved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) > 0]';
		$this->exts->capture("cloudflare-checking");
		if (
			!$this->exts->oneExists([$solved_cloudflare_input_xpath, $unsolved_cloudflare_input_xpath]) &&
			$this->exts->exists(selector_or_xpath: '#cf-please-wait > p:not([style*="display: none"]):not([style*="display:none"])')
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
			$this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
			sleep(5);
			$this->exts->capture("cloudflare-clicked-1", true);
			sleep(3);
			if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
				$this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
				sleep(5);
				$this->exts->capture("cloudflare-clicked-2", true);
				sleep(15);
			}
			if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
				$this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
				sleep(5);
				$this->exts->capture("cloudflare-clicked-3", true);
				sleep(15);
			}
		}
	}

	public function fillForm($count)
	{
		$this->exts->log("Begin fillForm " . $count);
		try {
			if ($this->exts->querySelector($this->username_selector) != null) {

				$this->exts->capture("1-pre-login");
				$this->exts->log("Enter Username");
				$this->exts->moveToElementAndType($this->username_selector, $this->username);

				$this->exts->log("Enter Password");
				$this->exts->moveToElementAndType($this->password_selector, $this->password);
				sleep(1);

				if ($this->exts->exists($this->remember_me_selector)) {
					$this->exts->click_element($this->remember_me_selector);
					sleep(1);
				}
				$this->exts->capture('2-login-page-filled');
				$this->exts->moveToElementAndClick($this->submit_login_selector);
				sleep(7); // Portal itself has one second delay after showing toast
				try {
					$this->check_solve_cloudflare_page();
					$this->check_solve_cloudflare_page();
				} catch (TypeError $e) {
					$this->exts->capture('2-script-error');
					$this->exts->log($e->getMessage());
					sleep(20);
				}
			}
		} catch (\Exception $exception) {

			$this->exts->log("Exception filling loginform " . $exception->getMessage());
		}
	}

	private function checkFillTwoFactor()
	{
		$two_factor_selector = 'input[name="otp"]';
		$two_factor_message_selector = 'h2 + p';
		$two_factor_submit_selector = 'button[type="submit"]';

		if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
			$this->exts->log("Two factor page found.");
			$this->exts->capture("2.1-two-factor");

			if ($this->exts->getElement($two_factor_message_selector) != null) {
				$this->exts->two_factor_notif_msg_en = "";
				for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
					$this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getAttribute('innerText') . "\n";
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

				$this->exts->moveToElementAndClick($two_factor_submit_selector);
				sleep(5);

				if ($this->exts->getElement($two_factor_selector) == null) {
					$this->exts->log("Two factor solved");
				} else if ($this->exts->two_factor_attempts < 3) {
					$this->exts->notification_uid = '';
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


	/**

	 * Method to Check where user is logged in or not

	 * return boolean true/false

	 */
	public function checkLogin()
	{
		$this->exts->log("Begin checkLogin ");
		$isLoggedIn = false;
		try {
			if ($this->exts->exists($this->check_login_success_selector)) {

				$this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

				$isLoggedIn = true;
			}
		} catch (Exception $exception) {

			$this->exts->log("Exception checking loggedin " . $exception);
		}

		return $isLoggedIn;
	}

	private function processInvoices($paging_count = 1)
	{
		$total_invoices = 0;
		sleep(3);
		$this->exts->execute_javascript('window.scrollBy(0, 500);');
		$this->exts->waitTillPresent('table tr', 20);
		$this->exts->capture("4-invoices-page");
		$invoices = [];

		$rows = $this->exts->querySelectorAll('table tr');
		foreach ($rows as $row) {
			if ($this->exts->querySelector('td:nth-child(7) a', $row) != null) {
				$invoiceUrl = '';
				$invoiceName = $this->exts->extract('td:nth-child(2)', $row);
				$invoiceAmount =  $this->exts->extract('td:nth-child(6)', $row);
				$invoiceDate =  $this->exts->extract('td:nth-child(1)', $row);

				$downloadBtn = $this->exts->querySelector('td:nth-child(7) a', $row);

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
			if ($this->restrictPages != 0 && $total_invoices >= 100) break;
			$this->exts->log('--------------------------');
			$this->exts->log('invoiceName: ' . $invoice['invoiceName']);
			$this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
			$this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
			$this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

			$invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : "";
			$invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd/m/y', 'Y-m-d');
			$this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

			// $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
			$downloaded_file = $this->exts->click_and_download($downloadBtn, 'pdf', $invoiceFileName);

			if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
				$this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
				sleep(1);
				$total_invoices++;
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
