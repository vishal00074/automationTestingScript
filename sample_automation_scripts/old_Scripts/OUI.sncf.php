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

	// Server-Portal-ID: 35507 - Last modified: 22.01.2025 13:20:40 UTC - User: 1

	/*Define constants used in script*/
	public $baseUrl = "https://www.sncf-connect.com/app/account";
	public $loginUrl = "https://www.oui.sncf/espaceclient/identification";
	public $homePageUrl = "https://www.sncf-connect.com/app/account";
	public $login_page_selector = "#ccl-not-connected-link";
	public $username_selector = 'div[class*="menu-header-top"] input[name="login"], input#login';
	public $username_submit_selector = 'div[class*="menu-header-top"] button#edit-connect, button[data-rfrrlink="Connexion"]';
	public $password_selector = 'input#password, input#pass1[type="password"]';
	public $login_button_selector = 'input#password, button[title*="Connect"]';
	public $login_confirm_selector = 'button[data-test="account-disconnect-button"], button[title*="Connect"]';
	public $billingPageUrl = "https://www.oui.sncf/espaceclient/commandes-en-cours";
	public $order_selector = "#link-booking";
	public $submit_button_selector = 'form#formAuthent input[type="submit"], #wcc__password-asked button[type="submit"], input#validate, button#validate';
	public $dropdown_selector = "#img_DropDownIcon";
	public $dropdown_item_selector = "#di_billCycleDropDown";
	public $more_bill_selector = ".view-more-bills-btn";
	public $login_tryout = 0;
	public $totalFiles = 0;

	/**
	 * Entry Method thats called for a portal
	 * @param Integer $count Number of times portal is retried.
	 */
	private function initPortal($count)
	{
		$this->exts->log('Begin initPortal ' . $count);
		$this->disable_unexpected_extensions();

		$proxy_success = $this->install_proxy_inscript();
		if (!$proxy_success) {
			$this->exts->capture('failed-install-proxy-1');
			$proxy_success = $this->install_proxy_inscript();
		}
		// $this->exts->webdriver->get($this->homePageUrl);
		$this->exts->loadCookiesFromFile();
		$this->exts->openUrl($this->homePageUrl);
		sleep(10);
		$this->check_solve_blocked_page($this->homePageUrl);

		$this->exts->capture("Home-page-without-cookie");

		if (!$this->checkLogin()) {
			// $this->exts->openUrl($this->loginUrl);
			// sleep(7);
			// $this->check_solve_blocked_page();
			$this->exts->moveToElementAndClick('button#didomi-notice-agree-button');
			sleep(7);

			//$windowHandlesBefore = $this->exts->webdriver->getWindowHandles();
			$this->exts->waitTillPresent('a[href*="/app/account"]');
			if ($this->exts->exists('a[href*="/app/account"]')) {
				$this->exts->click_by_xdotool('a[href*="/app/account"]');
			}
			$this->exts->moveToElementAndClick('button[class*="containedPrimary "]');
			sleep(25);
			//$this->switch_to_login_window_w3c($windowHandlesBefore);
			$this->exts->switchToNewestActiveTab();
			if ($this->exts->check_exist_by_chromedevtool('iframe[src*="captcha-delivery.com/captcha"]')) {
				$this->check_solve_blocked_page();
			}

			$this->fillForm(0);
			sleep(15);

			$this->checkFillTwoFactor();
			sleep(13);

			//$this->close_all_tabs_w3c();
			$this->exts->switchToInitTab();

			$this->exts->moveToElementAndClick('button#didomi-notice-agree-button');
			sleep(7);
		}

		if ($this->checkLogin()) {
			$this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
			$this->exts->capture("LoginSuccess");
			$this->downloadInvoice();

			if ($this->totalFiles == 0) {
				$this->exts->log("No invoice !!! ");
				$this->exts->no_invoice();
			}
		} else {
			$this->exts->capture("LoginFailed");
			$this->exts->loginFailure();
		}
	}

	private function switch_to_login_window_w3c($windowHandlesBefore)
	{
		// Get current window handles first:


		//    $windowHandlesAfter = $this->exts->webdriver->getWindowHandles();
		//    if (count($windowHandlesAfter) > 1) {
		// 	   $newWindowHandle = array_diff($windowHandlesAfter, $windowHandlesBefore);
		// 	   print_r($newWindowHandle);
		// 	   foreach ($newWindowHandle as $handle_id) {
		// 		   $this->exts->webdriver->switchTo()->window($handle_id);
		// 		   if ($this->exts->exists($this->username_selector)) {
		// 			   break;
		// 		   }else{
		// 			   $this->exts->webdriver->close();
		// 		   }
		// 	   }
		//    }
	}
	function fillForm($count)
	{
		$this->exts->log("Begin fillForm " . $count);
		$this->exts->waitTillPresent($this->username_selector, 30);
		try {

			if ($this->exts->querySelector($this->username_selector) != null) {
				sleep(2);
				$this->exts->log("Enter Username");
				$this->exts->moveToElementAndType($this->username_selector, $this->username);
				$this->exts->capture("username-fill");
				//$this->exts->moveToElementAndClick('button#didomi-notice-agree-button');
				//sleep(7);
				//$this->exts->moveToElementAndClick($this->username_submit_selector);
				//sleep(10);
				//$this->exts->moveToElementAndClick('button#didomi-notice-agree-button');
				//sleep(7);
			}

			$this->exts->capture('login-page');
			if ($this->exts->querySelector($this->password_selector) != null) {
				$this->exts->log("Enter Password");
				//$this->exts->moveToElementAndType($this->username_selector, $this->username);
				//sleep(1);
				$this->exts->moveToElementAndType($this->password_selector, $this->password);
				$this->exts->capture("password-fill");
				$this->exts->moveToElementAndClick($this->submit_button_selector);
				sleep(10);
				$this->exts->capture("after-submit-login");
				$err_msg = trim($this->exts->extract('div.wcc__message--error'));
				if ($err_msg != "" && $err_msg != null) {
					$this->exts->log($err_msg);
					$this->exts->loginFailure(1);
				}
				if ($this->exts->exists('li[ng-if="connectionError"]')) {
					$this->exts->loginFailure(1);
				}

				if ($this->exts->exists('p#PwdValidationError, p#AuthValidationError, p#EmailValidationError')) {
					$this->exts->loginFailure(1);
				}
				// $handles = $this->exts->webdriver->getWindowHandles();
				// $this->exts->webdriver->switchTo()->window($handles[0]);

			}
		} catch (\Exception $exception) {
			$this->exts->log("Exception filling loginform " . $exception->getMessage());
		}
	}
	function checkLogin()
	{
		$this->exts->log("Begin checkLogin ");
		$isLoggedIn = false;

		try {
			if ($this->exts->extract($this->login_confirm_selector) != '') {
				$this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
				$isLoggedIn = true;
			}

			$account_text = ' ' . trim(strtolower($this->exts->extract('div.vsc-header__main-item a[href="/app/account"] span.vsc-header__label .vsc-header__txt', null, 'innerText'))) . ' ';
			if (strpos($account_text, ' compte') === false && strpos($account_text, ' account') === false && trim($account_text) != '') {
				$isLoggedIn = true;
			}
		} catch (\Exception $exception) {
			$this->exts->log("Exception checking loggedin " . $exception);
		}
		return $isLoggedIn;
	}
	// solve block

	private function install_proxy_inscript()
	{
		$vpn_user = 'brd-customer-hl_b0a51fd2-zone-residential_rotate_ip_1';
		$vpn_pwd = 'cq1mweo863oj';
		$proxy_loggedin_selector = '.settings .ip, .settings .detail,  .settings .switch';
		$node_name = !empty($this->exts->config_array['node_name']) ? $this->exts->config_array['node_name'] : "selenium-node-" . $this->exts->process_uid;
		$this->exts->openUrl("chrome-extension://efohiadmkaogdhibjbmeppjpebenaool/options.html");
		sleep(3);
		if (!$this->exts->exists($proxy_loggedin_selector) && !$this->exts->exists('.dialog_login')) {
			$this->exts->openUrl('https://chromewebstore.google.com/detail/bright-data/efohiadmkaogdhibjbmeppjpebenaool?hl=en');
			sleep(3);
			$this->exts->capture_by_chromedevtool("proxy-extension-webstore", false);
			if ($this->exts->getElement('//button/*[contains(text(), "Remove from Chrome")]', null, 'xpath')) {
				// sleep(5);[aria-label="alert"] button
				$enable_button = $this->exts->getElement('//span[text()="Enable this item" or text()="Enable now"]/..', null, 'xpath');
				if ($enable_button != null) {
					$enable_button->click();
					sleep(7);
				}
				$this->exts->capture_by_chromedevtool("proxy-extension-webstore-2", false);
				$this->exts->openUrl("chrome-extension://efohiadmkaogdhibjbmeppjpebenaool/popup.html");
				sleep(5);
				if ($this->exts->exists('.account_status.status_active') && !$this->exts->exists('.zone input[role="combobox"][value="residential_rotate_ip_1"]')) {
					$this->exts->moveToElementAndClick('.zone input[role="combobox"]');
					sleep(1);
					$this->exts->moveToElementAndClick('[role="listbox"] a[aria-label="residential_rotate_ip_1"]');
					sleep(2);
					$this->exts->capture_by_chromedevtool("proxy-zone-selected", false);
				}
			} else {
				$submit_button = $this->exts->getElement('//button/*[contains(text(), "Add to Chrome")]/..', null, 'xpath');
				$this->exts->execute_javascript('arguments[0].click();', [$submit_button]);
				sleep(3);
				// Accept confirm alert
				exec("sudo docker exec " . $node_name . " bash -c 'sudo xdotool key Tab'");
				exec("sudo docker exec " . $node_name . " bash -c 'sudo xdotool key Return'");
				// END Accept confirm alert
				sleep(10);
				$this->exts->capture('bright-data-added');
				// Close advertising tab
				$this->exts->switchToTab($this->exts->init_tab);
			}
		}

		// input proxy credential
		$this->exts->openUrl("chrome-extension://efohiadmkaogdhibjbmeppjpebenaool/options.html");
		sleep(2);
		if (!$this->exts->exists($proxy_loggedin_selector)) {
			if ($this->exts->exists('.dialog_login .bext_button + .bext_button') && !$this->exts->exists('.auth_input input[type="password"]')) {
				$this->exts->moveToElementAndClick('.dialog_login .bext_button + .bext_button'); //  Login using zone password
				sleep(1);
			}
			$this->exts->capture_by_chromedevtool("proxy-credential-form", false);
			$this->exts->moveToElementAndType('.auth_input input[type="text"]', $vpn_user);
			sleep(1);
			$this->exts->moveToElementAndType('.auth_input input[type="password"]', $vpn_pwd);
			sleep(1);
			$this->exts->capture_by_chromedevtool("proxy-credential-filled", false);
			$this->exts->moveToElementAndClick('.submit_buttons .bext_button + .bext_button');
			sleep(7);
			if ($this->exts->exists('.auth_input input[type="password"]')) {
				$this->exts->moveToElementAndType('.auth_input input[type="text"]', $vpn_user);
				sleep(1);
				$this->exts->moveToElementAndType('.auth_input input[type="password"]', $vpn_pwd);
				sleep(1);
				$this->exts->log('Unknow exception -submit proxy password again');
				$this->exts->moveToElementAndClick('.submit_buttons .bext_button + .bext_button');
				sleep(1);
				$this->exts->capture('re-submit-proxy-credential');
				sleep(7);
			}
		}

		if ($this->exts->exists('.settings .switch.off')) {
			$this->exts->moveToElementAndClick('.settings .switch.off'); // Start proxy
			sleep(5);
			// Close advertising tab
			$this->exts->switchToTab($this->exts->init_tab);
		}
		$this->exts->capture("proxy-installed");

		if ($this->exts->exists($proxy_loggedin_selector)) {
			return true;
		} else {
			return false;
		}
	}
	private function disable_unexpected_extensions()
	{
		$extension_links = [
			'chrome://extensions/?id=cjpalhdlnbpafiamejdnhcphjbkeiagm', // ublock origin
			'chrome://extensions/?id=mpbjkejclgfgadiemmefgebjfooflfhl',
			'chrome://extensions/?id=ifibfemgeogfhoebkmokieepdoobkbpo'

		];
		foreach ($extension_links as $key => $extension_link) {
			try {
				$this->exts->openUrl($extension_link);
			} catch (Exception $e) {
				$this->exts->log($e->getMessage());
			}
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
	}
	private function checkip()
	{
		$ip_detail = '';
		$ip_detail = $this->exts->executeSafeScript('
			var xhr = new XMLHttpRequest();
			xhr.open("GET", "https://lumtest.com/myip.json", false);
			xhr.send();
			return xhr.responseText;
		');
		return $ip_detail;
	}
	private function change_random_ip_inscript()
	{
		$this->exts->log('Change IP');
		$vpn_user = 'brd-customer-hl_b0a51fd2-zone-residential_rotate_ip_1';
		$vpn_pwd = 'cq1mweo863oj';
		$this->exts->log('Location before refresh - ' . $this->checkip());
		sleep(2);
		$this->exts->openUrl("chrome-extension://efohiadmkaogdhibjbmeppjpebenaool/options.html");
		sleep(3);
		$this->exts->capture("proxy-pre-refreshing");
		if ($this->exts->oneExists(['.dialog_login', '.auth_input input[type="password"]'])) {
			if ($this->exts->exists('.dialog_login .bext_button + .bext_button') && !$this->exts->exists('.auth_input input[type="password"]')) {
				$this->exts->moveToElementAndClick('.dialog_login .bext_button + .bext_button'); //  Login using zone password
				sleep(1);
			}
			$this->exts->capture("proxy-credential-form");
			$this->exts->moveToElementAndType('.auth_input input[type="text"]', $vpn_user);
			sleep(1);
			$this->exts->moveToElementAndType('.auth_input input[type="password"]', $vpn_pwd);
			sleep(1);
			$this->exts->capture("proxy-credential-filled");
			$this->exts->moveToElementAndClick('.submit_buttons .bext_button + .bext_button');
			sleep(7);
		}
		if ($this->exts->exists('.settings .switch.off')) {
			$this->exts->moveToElementAndClick('.settings .switch.off'); // Start proxy
			sleep(5);
		}
		//$this->exts->switchToTab($this->exts->init_tab);
		$this->exts->moveToElementAndClick('.icon.refresh');
		sleep(5);
		//$this->exts->switchToTab($this->exts->init_tab);
		$this->exts->capture("proxy-refreshed-ip");
		$current_ip = $this->checkip();
		$this->exts->log('Location after refresh - ' . $current_ip);
	}
	private function openUrl_undetected($url = '')
	{
		$this->exts->log('Navigating to URL : ' . $url);
		try {
			$this->exts->webdriver->get($url);
			$this->exts->capture_by_chromedevtool("current_page", false);
		} catch (\Exception $exception) {
			$err_msg = $exception->getMessage();
			$this->exts->log('Failed opening url - ' . $err_msg);
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

	private function capture_captcha_iframe($fileName, $selector = null)
	{
		$geetestcaptcha_iframe_selector = 'iframe[src*="captcha-delivery.com/captcha"]';
		$screenshot = $this->exts->screen_capture_location . time() . ".png";
		$devTools = new Chrome\ChromeDevToolsDriver($this->exts->webdriver);

		$base64_string = $devTools->execute(
			'Page.captureScreenshot',
			[]
		);
		$ifp = fopen($screenshot, 'wb');
		fwrite($ifp, base64_decode($base64_string["data"]));
		fclose($ifp);

		if (!file_exists($screenshot)) {
			$this->log("Could not save screenshot");
			return $screenshot;
		}

		if (!(bool)$selector) {
			return $screenshot;
		}

		$this->switchToFrame($geetestcaptcha_iframe_selector);
		$javascript_expression = '
        	var element = document.querySelector(atob("' . base64_encode($selector) . '"));
        	var bcr = element.getBoundingClientRect();
			return JSON.stringify(bcr);
		';
		$result_text = $this->exts->executeSafeScript($javascript_expression);
		$coodinate = json_decode($result_text, true);
		print_r($coodinate);

		// Copy
		$element_screenshot = $this->exts->screen_capture_location . $fileName . ".png";
		$src = imagecreatefrompng($screenshot);
		$dest = imagecreatetruecolor(round($coodinate['width']), round($coodinate['height']));
		imagecopy($dest, $src, 0, 0, round($coodinate['x']), round($coodinate['y']), round($coodinate['width']), round($coodinate['height']));
		imagepng($dest, $element_screenshot);

		if (!file_exists($element_screenshot)) {
			$this->exts->log("Could not save screenshot");

			return $screenshot;
		}

		return $element_screenshot;
	}
	private function processClickCaptcha(
		$captcha_image_selector,
		$instruction = '',
		$lang_code = '',
		$json_result = false,
		$image_dpi = 90
	) {
		$this->exts->log("--CAll CLICK CAPTCHA SERVICE-");
		$response = '';
		$image_path = $this->capture_captcha_iframe($this->exts->process_uid, $captcha_image_selector);
		$source_image = imagecreatefrompng($image_path);
		imagejpeg($source_image, $this->exts->screen_capture_location . $this->exts->process_uid . '.jpg', $image_dpi);

		$cmd = $this->exts->config_array['click_captcha_shell_script'] . " --PROCESS_UID::" . $this->exts->process_uid . " --CAPTCHA_INSTRUCTION::" . urlencode($instruction) . " --LANG_CODE::" . urlencode($lang_code) . " --JSON_RESULT::" . urlencode($json_result);
		$this->exts->log('Executing command : ' . $cmd);
		exec($cmd, $output, $return_var);
		$this->exts->log('Command Result : ' . print_r($output, true));

		if (!empty($output)) {
			$output = trim($output[0]);
			if ($json_result) {
				if (strpos($output, '"status":1') !== false) {
					$response = json_decode($output, true);
					$response = $response['request'];
				}
			} else {
				if (strpos($output, 'coordinates:') !== false) {
					$response = trim(end(explode("coordinates:", $output)));
				}
			}
		}
		if ($response == '') {
			$this->exts->log("Can not get result from API");
		}
		return $response;
	}
	public function process_geetest_by_clicking()
	{
		$geetestcaptcha_iframe_selector = 'iframe[src*="captcha-delivery.com/captcha"]';
		$geetestcaptcha_challenger_wraper_selector = '#captcha__puzzle';
		$this->exts->switchToDefault();
		if ($this->check_exist_by_chromedevtool($geetestcaptcha_iframe_selector)) {
			$captcha_frame = $this->exts->getElement($geetestcaptcha_iframe_selector);
			$this->exts->webdriver->switchTo()->frame($captcha_frame);
			sleep(5);
			if ($this->exts->exists('.retryLink')) {
				$this->exts->moveToElementAndClick('.retryLink');
				sleep(7);
			}
			$message = $this->exts->extract('.captcha__human__title', null, 'innerText');
			$this->exts->log('BLOCKED: ' . $message);
			$is_hard_blocked = stripos($message, 'bloqu') !== false || stripos($message, 'block') !== false || stripos($message, 'ie wurden gesperrt') !== false;
			if (!$is_hard_blocked) { // if not hard blocked the we solve the captcha
				$captcha_instruction = 'This is puzzle game, Click the center position of blank hole only';
				$this->exts->switchToDefault();
				$coordinates = $this->processClickCaptcha($geetestcaptcha_challenger_wraper_selector, $captcha_instruction, '', $json_result = true);
				if ($coordinates != '') {
					$drag_offset_x = (int)end($coordinates)['x'] - 26;
					$this->exts->log($drag_offset_x);
					if ($drag_offset_x < 0) {
						$drag_offset_x = (int)$coordinates[0]['x'] - 26;
						$this->exts->log($drag_offset_x);
					}
					if ($drag_offset_x < 0) {
						return;
					}

					$this->exts->switchToDefault();
					$this->switchToFrame($geetestcaptcha_iframe_selector);
					$slide_button_coo = $this->exts->executeSafeScript('
						window.lastMousePosition = null;
						window.addEventListener("mousemove", function(e){
							window.lastMousePosition = e.clientX +"|" + e.clientY;
						});
						var coo = document.querySelector(".slider").getBoundingClientRect();
						return Math.round(coo.x + coo.width/2) + "|" + Math.round(coo.y + coo.height/2);
					');
					sleep(3);
					$this->exts->log('X/Y: ' . $slide_button_coo);
					$slide_button_coo = explode('|', $slide_button_coo);
					$node_name = !empty($this->exts->config_array['node_name']) ? $this->exts->config_array['node_name'] : "selenium-node-" . $this->exts->process_uid;
					$this->exts->log(' Move: ');
					exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove 350 450'");
					$current_cursor = $this->exts->executeSafeScript('
						return window.lastMousePosition;
					');
					$this->exts->log('current_cursor: ' . $current_cursor);

					$current_cursor = explode('|', $current_cursor);
					$offset_x = (int)$slide_button_coo[0] - (int)$current_cursor[0];
					$offset_y = (int)$slide_button_coo[1] - (int)$current_cursor[1];
					exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove_relative " . $offset_x . " " . $offset_y . "; sleep 0.5'");
					$step_offset = 12;
					$steps = (int)($drag_offset_x / $step_offset);
					$surplus = $drag_offset_x % $step_offset;
					// exec("sudo docker exec ".$node_name." bash -c 'xdotool mousedown 1;for i in {1..".$steps."}; do xdotool mousemove_relative ".$step_offset." 0; sleep 0.5; done; xdotool mousemove_relative ".$surplus." 0; sleep 1.5; xdotool mouseup 1; xdotool mousemove_relative 50 50'");
					exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousedown 1; xdotool mousemove_relative " . $drag_offset_x . " 0; sleep 2; xdotool mouseup 1'");
					sleep(3);
				}
				return true;
			}
		}
		return false;
	}

	private function check_solve_blocked_page()
	{
		$current_url = $this->exts->getUrl();
		for ($i = 0; $i < 4 && $this->exts->exists('iframe[src*="captcha-delivery.com/captcha"]'); $i++) {
			$this->change_random_ip_inscript();
			$this->exts->openUrl($current_url);
			sleep(10);
		}
	}


	public function checkFillTwoFactor()
	{
		$two_factor_selector = 'input#otpCode';
		$two_factor_message_selector = 'p.message_otp, div.otp_message';
		$two_factor_submit_selector = 'input#sendOTP';

		if ($this->exts->querySelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
			$this->exts->log("Two factor page found.");
			$this->exts->capture("2.1-two-factor");

			if ($this->exts->querySelector($two_factor_message_selector) != null) {
				$this->exts->two_factor_notif_msg_en = "";
				for ($i = 0; $i < count($this->exts->querySelectorAll($two_factor_message_selector)); $i++) {
					$this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->querySelectorAll($two_factor_message_selector)[$i]->getText() . "\n";
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
				$this->exts->log("checkFillTwoFactor: Clicked submit button.");

				$this->exts->capture("after-submit-2fa");

				if ($this->exts->querySelector($two_factor_selector) == null) {
					$this->exts->log("Two factor solved");
					$this->exts->moveToElementAndClick('input#accessAccount');
				} else if ($this->exts->two_factor_attempts < 3) {
					$this->exts->two_factor_attempts++;
					$this->exts->notification_uid = '';
					$this->checkFillTwoFactor();
				} else {
					$this->exts->log("Two factor can not solved");
				}
			} else {
				$this->exts->log("Not received two factor code");
			}
		}
	}

	private function checkFillRecaptcha($count = 1)
	{
		$this->exts->log(__FUNCTION__);
		$recaptcha_iframe_selector = 'iframe[src*="/recaptcha/enterprise"], iframe[src*="https://www.google.com/recaptcha/api2/anchor?"]';
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
				$recaptcha_textareas =  $this->exts->querySelectorAll($recaptcha_textarea_selector);
				for ($i = 0; $i < count($recaptcha_textareas); $i++) {
					$this->exts->execute_javascript("arguments[0].innerHTML = '" . $this->exts->recaptcha_answer . "';", [$recaptcha_textareas[$i]]);
				}
				sleep(2);
				$this->exts->capture('recaptcha-filled');

				$gcallbackFunction = $this->exts->execute_javascript('
                    (function() { 
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
                    })();
				');
				$this->exts->log('Callback function: ' . $gcallbackFunction);
				$this->exts->log('Callback function: ' . $this->exts->recaptcha_answer);
				if ($gcallbackFunction != null) {
					$this->exts->execute_javascript($gcallbackFunction . '("' . $this->exts->recaptcha_answer . '");');
					sleep(10);
				}
			} else {
				// try again if recaptcha expired
				if ($count < 3) {
					$count++;
					$this->checkFillRecaptcha($count);
				}
			}
		} else {
			$this->exts->log(__FUNCTION__ . '::Not found reCaptcha');
		}
	}
	function downloadInvoice()
	{
		$this->exts->log("Begin downlaod invoice ");
		sleep(2);
		try {
			if ($this->exts->querySelector($this->order_selector) != null) {
				$this->exts->moveToElementAndClick($this->order_selector);
				sleep(15);
			}
			// $this->exts->querySelector("select#ordersType")->findElements(WebDriverBy::CssSelector('option'))[1]->click();
			if ($this->exts->exists('select#ordersType option[value="O"]')) {
				$this->exts->changeSelectbox('select#ordersType', 'O');
				sleep(10);
			}

			while ($this->exts->querySelector("div#ordersContent > div:nth-child(4) > button")) {
				if ($this->exts->querySelector("div#ordersContent > div:nth-child(4) > button")->getAttribute("style") == "") {
					$this->exts->moveToElementAndClick("div#ordersContent > div:nth-child(4) > button");
					sleep(5);
				} else {
					break;
				}
			}
			$this->exts->capture('4-List-invoices');
			if ($this->exts->querySelector("div#listOrders div.order") != null) {
				$invoices = array();
				$receipts = $this->exts->querySelectorAll("div#listOrders div.order");
				foreach ($receipts as $receipt) {
					$this->exts->log("each record");
					if ($this->exts->querySelector('div.order__details > div.order__detail a[href*="?pdfFileName="]', $receipt) != null) {
						$receiptDate = $this->exts->extract('div.order__details > div[class="row order__detail"] div.order__element span.texte--important', $receipt);
						$this->exts->log("Invoice date: " . $receiptDate);
						$receiptUrl = $this->exts->extract('div.order__details > div.order__detail a[href*="?pdfFileName="]', $receipt, 'href');
						$receiptName = $this->exts->extract('div.order__details > div[class="row order__detail"] > div > span[data-auto="ccl_orders_travel_number"]', $receipt);
						$receiptFileName = $receiptName . '.pdf';
						$this->exts->log("Invoice name: " . $receiptName);
						$this->exts->log("Invoice Filename: " . $receiptFileName);
						$this->exts->log("Inovice URL: " . $receiptUrl);
						$parsed_date = $this->exts->parse_date($receiptDate, 'd/m/Y', 'Y-m-d');
						$this->exts->log("Invoice Parsed date: " . $parsed_date);
						$receiptAmount = $this->exts->extract('div.order__details > div[class="row order__detail"] > div:nth-child(3) span.texte--important', $receipt);
						$receiptAmount = trim(preg_replace('/[^\d\.\,]/', '', $receiptAmount)) . ' EUR';
						$this->exts->log("Inovice amount: " . $receiptAmount);
						$invoice = array(
							'receiptName' => $receiptName,
							'parsed_date' => $parsed_date,
							'receiptAmount' => $receiptAmount,
							'receiptFileName' => $receiptFileName,
							'receiptUrl' => $receiptUrl,
						);
						array_push($invoices, $invoice);
					}
					$this->j += 1;
				}
				$this->exts->log("Invoice found: " . count($invoices));
				foreach ($invoices as $invoice) {
					$downloaded_file = $this->exts->direct_download($invoice['receiptUrl'], 'pdf', $invoice['receiptFileName']);
					if (trim($downloaded_file) == "" && !file_exists($downloaded_file)) {
						$this->checkFillRecaptcha();
						$this->checkFillRecaptcha();
						sleep(5);
						$downloaded_file = $this->exts->direct_download($invoice['receiptUrl'], 'pdf', $invoice['receiptFileName']);
					}
					$this->exts->log("downloaded file");
					if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
						$this->totalFiles += 1;
						$this->exts->log("create file");
						$this->exts->new_invoice($invoice['receiptName'], $invoice['parsed_date'], $invoice['receiptAmount'], $downloaded_file);
					}
				}
				if ($this->totalFiles == 0) {
					$this->exts->log("No invoice !!! ");
					$this->exts->no_invoice();
				}
			} else {
				$this->exts->log("No invoice !!! ");
				$this->exts->no_invoice();
			}
		} catch (\Exception $exception) {
			$this->exts->log("Exception downlaoding invoice " . $exception->getMessage());
		}
	}
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
