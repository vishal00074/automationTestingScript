<?php // replace waitTillpresent to custom js waitFor and handle empty invoice name case added limit to download only 50 invoices

/**
 * Chrome Remote via Chrome devtool protocol script, for specific process/portal
 *sustainableag
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

	// Server-Portal-ID: 135352 - Last modified: 28.03.2025 14:27:58 UTC - User: 1

	//start script 

	public $baseUrl = 'https://subscribe.free.fr/login';
	public $loginUrl = 'https://subscribe.free.fr/login/';
	public $invoicePageUrl = '';
	public $username_selector = 'input#login_b';
	public $password_selector = 'input#pass_b';
	public $remember_me_selector = '';
	public $submit_login_selector = 'button[aria-label="Identifiant"] , input.login_button';
	public $check_login_failed_selector = 'div.loginalert strong';
	public $check_login_success_selector = 'div#content';
	public $isNoInvoice = true;

	/**

	 * Entry Method thats called for a portal

	 * @param Integer $count Number of times portal is retried.

	 */
	private function initPortal($count)
	{

		$this->exts->log('Begin initPortal ' . $count);
		$this->exts->loadCookiesFromFile();
		$this->exts->openUrl($this->loginUrl);
		sleep(5);

		if (!$this->checkLogin()) {
			$this->exts->log('NOT logged via cookie');
			$this->exts->clearCookies();
			$this->exts->openUrl($this->loginUrl);
			$this->fillForm(0);
		}

		if ($this->checkLogin()) {
			$this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
			$this->exts->capture("LoginSuccess");
			$this->exts->success();

			$this->waitFor('div.block_content', 30);

			if ($this->exts->exists('div.block_content')) {
				$this->processYears();
			}

			$this->waitFor('div#widget_mesfactures > div:nth-child(3)  > span > a:nth-child(1)', 30);

			if ($this->exts->exists('div#widget_mesfactures > div:nth-child(3)  > span > a:nth-child(1)')) {
				$this->exts->moveToElementAndClick('div#widget_mesfactures > div:nth-child(3)  > span > a:nth-child(1)');
				sleep(5);
				$this->processYears();
			}

			// Final, check no invoice
			if ($this->isNoInvoice) {
				$this->exts->no_invoice();
			}
			$this->exts->success();
		} else {
			if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), "Identifiant ou mot de passe incorrect") !== false) {
				$this->exts->log("Wrong credential !!!!");
				$this->exts->loginFailure(1);
			} else {
				$this->exts->loginFailure();
			}
		}
	}


	function fillForm($count)
	{
		$this->exts->log("Begin fillForm " . $count);
		$this->waitFor($this->username_selector, 7);
		try {
			if ($this->exts->querySelector($this->username_selector) != null) {

				$this->exts->capture("1-pre-login");
				$this->exts->log("Enter Username");
				$this->exts->moveToElementAndType($this->username_selector, $this->username);
				sleep(2);

				$this->exts->log("Enter Password");
				$this->exts->moveToElementAndType($this->password_selector, $this->password);
				sleep(1);

				if ($this->exts->exists($this->remember_me_selector)) {
					$this->exts->click_by_xdotool($this->remember_me_selector);
					sleep(1);
				}

				$this->exts->capture("1-login-page-filled");
				sleep(5);
				if ($this->exts->exists($this->submit_login_selector)) {
					$this->exts->click_by_xdotool($this->submit_login_selector);
				}
			}
		} catch (\Exception $exception) {

			$this->exts->log("Exception filling loginform " . $exception->getMessage());
		}
	}


	public function waitFor($selector, $seconds = 7)
	{
		for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
			$this->exts->log('Waiting for Selectors.....');
			sleep($seconds);
		}
	}


	/**

	 * Method to Check where user is logged in or not

	 * return boolean true/false

	 */
	function checkLogin()
	{
		$this->exts->log("Begin checkLogin ");
		$isLoggedIn = false;
		try {
			$this->waitFor($this->check_login_success_selector, 10);
			if ($this->exts->exists($this->check_login_success_selector)) {

				$this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

				$isLoggedIn = true;
			}
		} catch (Exception $exception) {

			$this->exts->log("Exception checking loggedin " . $exception);
		}

		return $isLoggedIn;
	}


	private function processYears()
	{
		$rows = $this->exts->querySelectorAll('div.block_content div[class*="listblock accordion"]');

		$this->exts->log('Total Years Count : ' . count($rows));

		for ($i = 1; $i <= count($rows); $i++) {
			$this->exts->click_element('div.block_content div[class*="listblock accordion"]:nth-child(' . $i . ')');
			sleep(10);
			$this->processInvoices($i);
			sleep(10);
			$this->exts->click_element('div.block_content div[class*="listblock accordion"]:nth-child(' . $i . ')');
		}
	}

	public $totalInvoices = 0;

	private function processInvoices($i = 1)
	{
		$this->waitFor('div.block_content div[class*="listblock accordion"]:nth-child(' . $i . ') ul.pane li', 15);
		$this->exts->capture("4-invoices-page");
		$invoices = [];
		$restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

		$rows = $this->exts->querySelectorAll('div.block_content div[class*="listblock accordion"]:nth-child(' . $i . ') ul.pane li');
		foreach ($rows as $row) {
			if ($this->exts->querySelector('span:nth-child(1) a', $row) != null) {
				$invoiceUrl = $this->exts->querySelector('span:nth-child(1) a', $row)->getAttribute('href');

				preg_match('/no_facture=([0-9]+)/', $invoiceUrl, $matches);
				$invoiceName = $matches[1] ?? null;

				$invoiceAmount = $this->exts->extract('span:nth-child(3)', $row);
				$invoiceDate =  $this->exts->extract('span:nth-child(2)', $row);

				$downloadBtn = $this->exts->querySelector('span:nth-child(1) a', $row);

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
			//totalInvoices
			if ($this->totalInvoices >= 50 && $restrictPages != 0) {
				return;
			}

			$this->exts->log('--------------------------');
			$this->exts->log('invoiceName: ' . $invoice['invoiceName']);
			$this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
			$this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
			$this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

			$invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : '';
			$invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd. F Y', 'Y-m-d');
			$this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

			// $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
			$downloaded_file = $this->exts->click_and_download($invoice['downloadBtn'], 'pdf', $invoiceFileName);

			if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
				$this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
				sleep(1);
				$this->totalInvoices++;
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
