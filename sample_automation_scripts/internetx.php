<?php // updated login code

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

	// Server-Portal-ID: 10069 - Last modified: 21.04.2025 13:59:27 UTC - User: 1

	public $baseUrl = 'https://www.internetx.com/';
	public $username_selector = '.collapse.in input[name="user"], form#lfrm input#txtUser, form[name="newLogin"] input[name="user"], form#ix-login-form input[name="userid"], form#login-autodns input[name="userid"]';
	public $password_selector = '.collapse.in input[name="password"], form#lfrm input#txtPassword, form[name="newLogin"] input[name="password"], form#ix-login-form input[name="password"], form#login-autodns input[name="password"]';
	public $remember_me_selector = '';
	public $submit_login_selector = '.collapse.in button[type="submit"], form#lfrm [type="submit"], form[name="newLogin"] button[type="submit"], form#ix-login-form button#ix-login-btn, form#login-autodns button[type="submit"]';

	public $check_login_failed_selector = '#ix-login-form div.errors';
	public $check_login_success_selector = '#btnManageAssignedUser, #ix-smenu-user-button';

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

		$custom_url = isset($this->exts->config_array["custom_url"]) ? $this->exts->config_array["custom_url"] : '';
		$this->exts->log('Config custom url: ' . $custom_url);
		if ($custom_url == null || trim($custom_url) == '') {
			$custom_url =  $this->baseUrl;
		}
		if (stripos($custom_url, 'https://') === false && stripos($custom_url, 'http://') === false) {
			$custom_url = 'https://' . $custom_url;
		}
		$this->exts->log('Final custom url: ' . $custom_url);
		// Load cookies
		$this->exts->loadCookiesFromFile();
		sleep(1);
		$this->exts->openUrl($custom_url);
		sleep(10);
		$this->exts->capture('1-init-page');

		$this->accept_cookies();

		// If user hase not logged in from cookie, clear cookie, open the login url and do login
		if ($this->exts->getElement($this->check_login_success_selector) == null) {
			$this->exts->log('NOT logged via cookie');
			//$this->exts->clearCookies();
			$this->exts->openUrl($custom_url);
			sleep(15);

			$this->accept_cookies();

			if ($this->exts->querySelector('nav ul li button') != null) {
				$this->exts->moveToElementAndClick('nav ul li button');
				sleep(5);
			}
			$this->checkFillLogin();
			sleep(20);
			$this->checkFillTwoFactor();
		}

		if ($this->exts->exists('div.modal-dialog .modal-footer button.btn-secondary')) {
			$this->exts->moveToElementAndClick('div.modal-dialog .modal-footer button.btn-secondary');
			sleep(5);
		}

		// then check user logged in or not
		if ($this->exts->getElement($this->check_login_success_selector) != null) {
			sleep(3);
			$this->exts->log(__FUNCTION__ . '::User logged in');
			$this->exts->capture("3-login-success");

			// Open invoices url and download invoice
			sleep(10);

			$this->accept_cookies();

			$this->exts->moveToElementAndClick('.modal-header button.close[type="button"]');
			sleep(2);

			$this->exts->moveToElementAndClick('div#preregConfirmReminderWindow button');
			sleep(1);

			if ($this->exts->exists('div.modal-dialog .modal-footer button.btn-secondary')) {
				$this->exts->moveToElementAndClick('div.modal-dialog .modal-footer button.btn-secondary');
				sleep(5);
			}

			if ($this->exts->exists('button#uc-btn-accept-banner.uc-btn-accept')) {
				$this->exts->moveToElementAndClick('button#uc-btn-accept-banner.uc-btn-accept');
				sleep(5);
			}

			if ($this->exts->exists('.iconInvoices')) {
				$this->exts->moveToElementAndClick('.iconInvoices');
				$this->processInvoices();
			} else {
				$invoiceUrl = $this->exts->getUrl() . 'accounting/invoices';

				$invoiceUrl = str_replace("com//", "com/", " https://cloud.autodns.com//accounting/invoices");

				$this->exts->openUrl($invoiceUrl);
				sleep(5);
				if ($this->exts->exists('div.modal-dialog .modal-footer button.btn-secondary')) {
					$this->exts->moveToElementAndClick('div.modal-dialog .modal-footer button.btn-secondary');
					sleep(5);
				}
				if ($this->restrictPages == '0') {
					$this->processInvoiceWithDateFilter();
				} else {
					$this->processInvoicesNew();
				}
			}

			// Final, check no invoice
			if ($this->isNoInvoice) {
				$this->exts->no_invoice();
			}
			$this->exts->success();
		} else {
			//Check if again login page of autodns has come if yes fill it again
			if ($this->exts->getElement($this->password_selector) != null) {
				$this->checkFillLogin();
				sleep(20);

				if ($this->exts->exists('div.modal-dialog .modal-footer button.btn-secondary')) {
					$this->exts->moveToElementAndClick('div.modal-dialog .modal-footer button.btn-secondary');
					sleep(5);
				}

				if ($this->exts->getElement($this->check_login_success_selector) != null) {
					sleep(3);
					$this->exts->log(__FUNCTION__ . '::User logged in');
					$this->exts->capture("3-login-success");

					// Open invoices url and download invoice
					sleep(10);

					$this->accept_cookies();

					$this->exts->moveToElementAndClick('.modal-header button.close[type="button"]');
					sleep(2);

					$this->exts->moveToElementAndClick('div#preregConfirmReminderWindow button');
					sleep(1);
					if ($this->exts->exists('.iconInvoices')) {
						$this->exts->moveToElementAndClick('.iconInvoices');
						$this->processInvoices();
					} else {
						$invoiceUrl = $this->exts->getUrl() . 'accounting/invoices';
						$this->exts->openUrl($invoiceUrl);
						sleep(5);
						if ($this->restrictPages == '0') {
							$this->processInvoiceWithDateFilter();
						} else {
							$this->processInvoicesNew();
						}
					}


					// Final, check no invoice
					if ($this->isNoInvoice) {
						$this->exts->no_invoice();
					}
				} else {
					$mes_login = $this->exts->extract('form#ix-login-form .errors', null, 'innerText');
					$this->exts->log('message login failed: ' . $mes_login);
					if (strpos(strtolower($mes_login), 'login failed. please check the data provided') !== false) {
						$this->exts->log(__FUNCTION__ . '::Use login failed even filling 2nd time also');
						$this->exts->loginFailure(1);
					} else if ($this->getElementByText('div', ['User does not exist or password incorrect.', 'Benutzer existiert nicht oder Passwort falsch.'])) {
						$this->exts->log(__FUNCTION__ . '::Use login failed even filling 2nd time also');
						$this->exts->loginFailure(1);
					} else {
						$this->exts->log(__FUNCTION__ . '::Use login failed even filling 2nd time also');
						$this->exts->loginFailure();
					}
				}
			} else {
				$this->exts->log(__FUNCTION__ . '::Use login failed');
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
		$two_factor_selector = '#token-modal.show input#token';
		$two_factor_message_selector = '#token-modal.show .modal-body > p';
		$two_factor_submit_selector = '#token-modal.show button#tokenLogin';

		if ($this->exts->exists($two_factor_selector) && $this->exts->two_factor_attempts < 3) {
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
				sleep(3);
				$this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

				$this->exts->moveToElementAndClick($two_factor_submit_selector);
				sleep(15);

				if ($this->exts->getElement($two_factor_selector) == null) {
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

	private function getElementByText($selector, $multi_language_texts, $parent_element = null, $is_absolutely_matched = true)
	{
		$this->exts->log(__FUNCTION__);
		if (is_array($multi_language_texts)) {
			$multi_language_texts = join('|', $multi_language_texts);
		}
		// Seaching matched element
		$object_elements = $this->exts->getElements($selector, $parent_element);
		foreach ($object_elements as $object_element) {
			$element_text = trim($object_element->getAttribute('innerText'));
			// First, search via text
			// If is_absolutely_matched = true, seach element matched EXACTLY input text, else search element contain the text
			if ($is_absolutely_matched) {
				$multi_language_texts = explode('|', $multi_language_texts);
				foreach ($multi_language_texts as $searching_text) {
					if (strtoupper($element_text) == strtoupper($searching_text)) {
						$this->exts->log('Matched element found');
						return $object_element;
					} else if (stripos(strtoupper($element_text), strtoupper($searching_text)) !== false) {
						$this->exts->log('Matched element found');
						return $object_element;
					} else if (stripos(strtoupper($element_text), 'PDF') !== false) {
						$this->exts->log('Matched element found');
						return $object_element;
					}
				}
				$multi_language_texts = join('|', $multi_language_texts);
			} else {
				if (preg_match('/' . $multi_language_texts . '/i', $element_text) === 1) {
					$this->exts->log('Matched element found');
					return $object_element;
				}
			}

			// Second, is search by text not found element, support searching by regular expression
			if (@preg_match($multi_language_texts, '') !== FALSE) {
				if (preg_match($multi_language_texts, $element_text) === 1) {
					$this->exts->log('Matched element found');
					return $object_element;
				}
			}
		}
		return null;
	}

	private function processInvoices()
	{
		sleep(25);
		$this->exts->capture("4-invoices-page");
		$invoices = [];

		$rows = $this->exts->getElements('table > tbody > tr');
		foreach ($rows as $row) {
			$tags = $this->exts->getElements('td', $row);
			if (count($tags) >= 5 && $this->exts->getElement('a[onclick*="billing_invoice"]', $tags[4]) != null && $this->exts->getElement('tr', $row) == null) {
				$invoice_link = $this->exts->getElement('a[onclick*="billing_invoice"]', $tags[4]);
				$invoiceUrl = explode(
					"')",
					array_pop(explode("('", $invoice_link->getAttribute('onclick')))
				)[0];
				$invoiceUrl = $invoice_link->getAttribute('origin') . '/' . trim($invoiceUrl, '/');
				$invoiceName = trim($tags[1]->getAttribute('innerText'));
				$invoiceDate = trim($tags[3]->getAttribute('innerText'));
				$invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[2]->getAttribute('innerText'))) . ' EUR';

				array_push($invoices, array(
					'invoiceName' => $invoiceName,
					'invoiceDate' => $invoiceDate,
					'invoiceAmount' => $invoiceAmount,
					'invoiceUrl' => $invoiceUrl
				));
				$this->isNoInvoice = false;
			} else if (count($tags) >= 8 && $this->exts->getElement('a[onclick*="billing_invoice_download_pdf"]', $tags[7]) != null && $this->exts->getElement('tr', $row) == null) {
				$invoice_link = $this->exts->getElement('a[onclick*="billing_invoice_download_pdf"]', $tags[7]);
				$invoiceUrl = explode(
					"')",
					array_pop(explode("('", $invoice_link->getAttribute('onclick')))
				)[0];
				$currentUrl = $this->exts->getUrl();
				$tempArr = explode("/", $currentUrl);
				$invoiceUrl = trim($tempArr[0]) . '//' . trim($tempArr[2]) . '/' . trim($invoiceUrl, '/');
				$invoiceName = trim($tags[0]->getAttribute('innerText'));
				$invoiceDate = trim($tags[6]->getAttribute('innerText'));
				$invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[1]->getAttribute('innerText'))) . ' EUR';

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
			$invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
			$this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

			$downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
			if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
				$this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
				sleep(5);
			} else {
				$this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
			}
		}
	}

	public function processInvoiceWithDateFilter()
	{
		if ($this->exts->exists('.ix-billing button.btn.ix-daterange')) {
			//Open date filter popup
			$this->exts->moveToElementAndClick('.ix-billing button.btn.ix-daterange');
			sleep(5);

			//Select year from popup
			$this->exts->moveToElementAndClick('.mj-daterange-picker .panels-choices .panel-button:last-child');
			sleep(2);

			$years = count($this->exts->getElements('.mj-daterange-picker .mj-calendar .calendar-months .month'));
			$this->exts->log('Total Years - ' . $years);
			for ($i = 0; $i < $years; $i++) {
				$yearSelectBtn = $this->exts->getElements('.mj-daterange-picker .mj-calendar .calendar-months .month')[$i];
				$this->click_element_object($yearSelectBtn);
				sleep(2);

				$this->exts->moveToElementAndClick('.mj-daterange-picker .mj-daterange-picker-controls .mj-daterange-picker-button.btn-primary');
				sleep(15);

				$this->processInvoicesNew();
				sleep(5);

				//Open date filter popup
				$this->exts->moveToElementAndClick('.ix-billing button.btn.ix-daterange');
				sleep(5);

				//Select year from popup
				$this->exts->moveToElementAndClick('.mj-daterange-picker .panels-choices .panel-button:last-child');
				sleep(2);
			}
		} else {
			$this->processInvoicesNew();
		}
	}

	public function click_element_object($element_object)
	{
		try {
			$element_object->click();
		} catch (\Exception $exception) {
			$this->exts->execute_javascript('arguments[0].click();', [$element_object]);
		}
	}

	private function processInvoicesNew($paging_count = 1)
	{
		sleep(25);

		$this->exts->capture("4-invoices-page");
		$invoices = [];

		$rows = count($this->exts->getElements('table > tbody > tr'));
		if ($this->exts->exists('a.row-more')) {
			$this->exts->execute_javascript('
				var rows = document.querySelectorAll("a.row-more");
				for(var i = 0; i < rows.length; i++){
					rows[i].classList.add("hovered");
				}
			');
		}
		sleep(1);
		for ($i = 0; $i < $rows; $i++) {
			$row = $this->exts->getElements('table > tbody > tr')[$i];
			$tags = $this->exts->getElements('td', $row);
			if (count($tags) >= 6 && ($this->exts->getElement('a.row-more', $tags[5]) != null || $this->exts->getElement('a.row-more', $tags[6]) != null || $this->exts->getElement('button.dropdown-toggle', $tags[7]) != null)) {
				$this->isNoInvoice = false;
				if ($this->exts->getElement('button.dropdown-toggle', $tags[7]) != null) {
					$download_button = $this->exts->getElement('button.dropdown-toggle', $tags[7]);

					$invoiceName = trim($tags[2]->getAttribute('innerText'));
					$invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
					$invoiceDate = trim($tags[1]->getAttribute('innerText'));
					$invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[6]->getAttribute('innerText'))) . ' EUR';
				} else if ($this->exts->getElement('a.row-more', $tags[6]) != null) {
					$download_button = $this->exts->getElement('a.row-more', $tags[6]);

					$invoiceName = trim($tags[2]->getAttribute('innerText'));
					$invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
					$invoiceDate = trim($tags[1]->getAttribute('innerText'));
					$invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[5]->getAttribute('innerText'))) . ' EUR';
				} else {
					$download_button = $this->exts->getElement('a.row-more', $tags[5]);

					$invoiceName = trim($tags[1]->getAttribute('innerText'));
					$invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
					$invoiceDate = trim($tags[0]->getAttribute('innerText'));
					$invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[4]->getAttribute('innerText'))) . ' EUR';
				}

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
					$billing_button = $this->getElementByText('ul.dropdown-menu li a', ['PDF', 'pdf'], $row);
					if ($billing_button != null) {
						try {
							$this->exts->log('Click download button');
							$billing_button->click();
						} catch (\Exception $exception) {
							$this->exts->log('Click download button by javascript');
							$this->exts->execute_javascript("arguments[0].click()", [$billing_button]);
						}
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

		if ($this->restrictPages == 0 && $paging_count < 50 && $this->exts->getElement('li[role="presentation"].active + li:not(.disabled)') != null) {
			$paging_count++;
			$this->exts->moveToElementAndClick('li[role="presentation"].active + li:not(.disabled)');
			sleep(5);
			$this->processInvoicesNew($paging_count);
		}
	}

	public function accept_cookies()
	{
		if ($this->exts->exists('form#tx_cookies_accept input.cc_btn_accept_all, #uc-btn-accept-banner') && $this->exts->exists('form#tx_cookies_accept input.cc_btn_accept_all, #uc-btn-accept-banner')) {
			$this->exts->moveToElementAndClick('form#tx_cookies_accept input.cc_btn_accept_all, #uc-btn-accept-banner');
			sleep(1);
		}
	}
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
