<?php
// Server-Portal-ID: 35507 - Last modified: 14.05.2024 11:06:41 UTC - User: 1

/*Define constants used in script*/
public $baseUrl = "https://www.sncf-connect.com/app/account";
public $loginUrl = "https://www.oui.sncf/espaceclient/identification";
public $homePageUrl = "https://www.sncf-connect.com/app/account";
public $login_page_selector = "#ccl-not-connected-link";
public $username_selector = 'div[class*="menu-header-top"] input[name="login"], input#login';
public $username_submit_selector = 'div[class*="menu-header-top"] button#edit-connect, button[data-rfrrlink="Connexion"]';
public $password_selector = 'input#password, input#pass1[type="password"]';
public $login_button_selector = "input#password";
public $login_confirm_selector = 'button[data-test="account-disconnect-button"]';
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
private function initPortal($count) {
	$this->exts->log('Begin initPortal '.$count);
	// $this->exts->webdriver->get($this->homePageUrl);
	$this->exts->loadCookiesFromFile();
	$this->exts->openUrl($this->homePageUrl);
	sleep(10);
	if($this->exts->exists('#didomi-notice-agree-button')){
		$this->exts->log('Accept Cookies');
		$this->exts->click_by_xdotool('#didomi-notice-agree-button');
		sleep(5);
	}


	$this->check_solve_blocked_page($this->homePageUrl);

	$this->exts->capture("Home-page-without-cookie");
	
	if(!$this->checkLogin()) {
		$this->exts->log('Check login if Not!');
		$this->exts->openUrl($this->loginUrl);
		sleep(7);
		$this->check_solve_blocked_page();
		// $this->exts->moveToElementAndClick('button#didomi-notice-agree-button');
		// sleep(7);

		// $windowHandlesBefore = $this->exts->webdriver->getWindowHandles();
		
		$this->exts->moveToElementAndClick('button[class*="containedPrimary "]');
		sleep(15);
		// $this->switchToTab($this->loginUrl);

		if ($this->exts->check_exist_by_chromedevtool('iframe[src*="captcha-delivery.com/captcha"]')) {
			$this->check_solve_blocked_page();
		}

		$this->fillForm(0);
		sleep(10);

		$this->checkFillTwoFactor();
		sleep(10);

		$this->exts->closeCurrentTab();

		$this->exts->moveToElementAndClick('button#didomi-notice-agree-button');
		sleep(7);
	} 

	if($this->checkLogin()) {
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
function fillForm($count){
	$this->exts->log("Begin fillForm ".$count);
	try {
		
		if( $this->exts->getElement($this->username_selector) != null && $this->exts->getElement($this->password_selector) == null) {
			sleep(2);
			$this->exts->log("Enter Username");
			$this->exts->sendKeys($this->username_selector, $this->username);
			$this->exts->capture("username-fill");
			$this->exts->moveToElementAndClick('button#didomi-notice-agree-button');
			sleep(7);
			$this->exts->moveToElementAndClick($this->username_submit_selector);
			sleep(10);
			$this->exts->moveToElementAndClick('button#didomi-notice-agree-button');
			sleep(7);
		}

		$this->exts->capture('login-page');
		if( $this->exts->getElement($this->password_selector) != null ) {
			$this->exts->log("Enter Password");
			$this->exts->sendKeys($this->username_selector, $this->username);
			$this->exts->moveToElementAndType($this->username_selector, $this->username);
			sleep(1);
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
			if($this->exts->isVisible('li[ng-if="connectionError"]')){
				$this->exts->loginFailure(1);
			}

			if ($this->exts->exists('p#PwdValidationError, p#AuthValidationError, p#EmailValidationError')) {
				$this->exts->loginFailure(1);
			}
			// $handles = $this->exts->webdriver->getWindowHandles();
			// $this->exts->webdriver->switchTo()->window($handles[0]);
			
		}
	} catch(\Exception $exception){
		$this->exts->log("Exception filling loginform ".$exception->getMessage());
	}
}
function checkLogin() {
	$this->exts->log("Begin checkLogin ");
	$isLoggedIn = false;

	try {
		if($this->exts->extract($this->login_confirm_selector) != '') {
			$this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
			$isLoggedIn = true;
			
		}

		$account_text = ' ' . trim(strtolower($this->exts->extract('div.vsc-header__main-item a[href="/app/account"] span.vsc-header__label .vsc-header__txt', null, 'innerText'))) . ' ';
		if (strpos($account_text, ' compte') === false && strpos($account_text, ' account') === false && trim($account_text) != '') {
			$isLoggedIn = true;
		}
	} catch(\Exception $exception){
		$this->exts->log("Exception checking loggedin ".$exception);
	}
	return $isLoggedIn;
}
private function checkip(){
	$ip_detail = '';
	$ip_detail = $this->exts->execute_javascript('
		var xhr = new XMLHttpRequest();
		xhr.open("GET", "https://lumtest.com/myip.json", false);
		xhr.send();
		return xhr.responseText;
	');
	return $ip_detail;
}
private function change_random_ip_inscript(){
	$current_tab_id = $this->exts->webdriver->getWindowHandle();
	$this->exts->log('Change IP');
	$vpn_user = 'brd-customer-hl_b0a51fd2-zone-residential_rotate_ip_1';
	$vpn_pwd = 'cq1mweo863oj';
	$this->exts->log('Location before refresh - '.$this->checkip());
	sleep(2);
	$this->exts->openUrl("chrome-extension://efohiadmkaogdhibjbmeppjpebenaool/options.html");
	sleep(3);
	$this->exts->capture_by_chromedevtool("proxy-pre-refreshing", false);
	if($this->exts->oneExists(['.dialog_login', '.auth_input input[type="password"]'])){
		if($this->exts->exists('.dialog_login .bext_button + .bext_button') && !$this->exts->exists('.auth_input input[type="password"]')){
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
	if($this->exts->exists('.settings .switch.off')){
		$this->exts->moveToElementAndClick('.settings .switch.off');// Start proxy
		sleep(5);
	}
	$this->exts->webdriver->switchTo()->window($current_tab_id);
	$this->exts->moveToElementAndClick('.icon.refresh');
	sleep(5);
	$this->exts->webdriver->switchTo()->window($current_tab_id);
	$this->exts->capture_by_chromedevtool("proxy-refreshed-ip", false);
	$current_ip = $this->checkip();
	$this->exts->log('Location after refresh - '.$current_ip);
}

public function process_geetest_by_clicking() {
	$geetestcaptcha_iframe_selector = 'iframe[src*="captcha-delivery.com/captcha"]';
	$geetestcaptcha_challenger_wraper_selector = '#captcha__puzzle';
	$this->exts->switchToDefault();
	if($this->exts->check_exist_by_chromedevtool($geetestcaptcha_iframe_selector)) {
		$captcha_frame = $this->exts->getElement($geetestcaptcha_iframe_selector);
		$this->exts->webdriver->switchTo()->frame($captcha_frame);
		sleep(5);
		if($this->exts->exists('.retryLink')){
			$this->exts->moveToElementAndClick('.retryLink');
			sleep(7);
		}
		$message = $this->exts->extract('.captcha__human__title', null, 'innerText');
		$this->exts->log('BLOCKED: '. $message);
		$is_hard_blocked = stripos($message, 'bloqu') !== false || stripos($message, 'block') !== false || stripos($message, 'ie wurden gesperrt') !== false;
		if(!$is_hard_blocked){// if not hard blocked the we solve the captcha
			$captcha_instruction = 'This is puzzle game, Click the center position of blank hole only';
			$this->exts->switchToDefault();
			$coordinates = $this->getCoordinates($geetestcaptcha_challenger_wraper_selector, $captcha_instruction, '', $json_result=true);
			if($coordinates != ''){
				$drag_offset_x = (int)end($coordinates)['x'] - 26;
				$this->exts->log($drag_offset_x);
				if($drag_offset_x < 0){
					$drag_offset_x = (int)$coordinates[0]['x'] - 26;
					$this->exts->log($drag_offset_x);
				}
				if($drag_offset_x < 0){
					return;
				}

				$this->exts->switchToDefault();
				$this->switchToFrame($geetestcaptcha_iframe_selector);
				$slide_button_coo = $this->exts->execute_javascript('
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
				$node_name = !empty($this->exts->config_array['node_name']) ? $this->exts->config_array['node_name'] : "selenium-node-".$this->exts->process_uid;
				$this->exts->log(' Move: ');
				exec("sudo docker exec ".$node_name." bash -c 'xdotool mousemove 350 450'");
				$current_cursor = $this->exts->execute_javascript('
					return window.lastMousePosition;
				');
				$this->exts->log('current_cursor: ' . $current_cursor);

				$current_cursor = explode('|', $current_cursor);
				$offset_x = (int)$slide_button_coo[0] - (int)$current_cursor[0];
				$offset_y = (int)$slide_button_coo[1] - (int)$current_cursor[1];
				exec("sudo docker exec ".$node_name." bash -c 'xdotool mousemove_relative ".$offset_x." ".$offset_y."; sleep 0.5'");
				$step_offset = 12;
				$steps = (int)($drag_offset_x/$step_offset);
				$surplus = $drag_offset_x%$step_offset;
				// exec("sudo docker exec ".$node_name." bash -c 'xdotool mousedown 1;for i in {1..".$steps."}; do xdotool mousemove_relative ".$step_offset." 0; sleep 0.5; done; xdotool mousemove_relative ".$surplus." 0; sleep 1.5; xdotool mouseup 1; xdotool mousemove_relative 50 50'");
				exec("sudo docker exec ".$node_name." bash -c 'xdotool mousedown 1; xdotool mousemove_relative ".$drag_offset_x." 0; sleep 2; xdotool mouseup 1'");
				sleep(3);
			}
			return true;
		}
	}
	return false;
}
public function refresh_if_hard_blocked($url_for_refresh=''){
	$captcha_iframe_selector = 'iframe[src*="captcha-delivery.com/captcha"]';
	$this->exts->switchToDefault();
	if($this->exts->check_exist_by_chromedevtool($captcha_iframe_selector)) {
		for ($i=1; $i < 3; $i++) {
			$this->exts->switchToDefault();
			$captcha_frame = $this->exts->getElement($captcha_iframe_selector);
			$this->switchToFrame($captcha_frame);
			$message = $this->exts->extract('.captcha__human__title', null, 'innerText');
			$this->exts->log('BLOCKED: '. $message);
			$is_hard_blocked = stripos($message, 'bloqu') !== false || stripos($message, 'block') !== false || stripos($message, 'ie wurden gesperrt') !== false;
			if($is_hard_blocked){
				$this->exts->capture_by_chromedevtool("hard-blocked-page");
				$current_browser_tab = $this->exts->webdriver->getWindowHandle();
				$this->exts->switchToNewestActiveTab();
				$this->change_random_ip_inscript();
				$this->exts->closeCurrentTab();// close new tab
				$this->switchToFrame($current_browser_tab);

				if($i == 2){
					$this->exts->log('Clear cookie');
					$this->exts->clearCookies(); // clear datadome cookie
				}
				if($url_for_refresh == ''){
					$this->exts->refresh();
				} else {
					$this->exts->openUrl($url_for_refresh);
				}
			} else {
				break;
			}
		}
	}
}
public function check_solve_blocked_page($url_for_refresh=''){
	$blocked_page = false;
	$captcha_iframe_selector = 'iframe[src*="captcha-delivery.com/captcha"]';
	$this->exts->switchToDefault();
	if($this->exts->check_exist_by_chromedevtool($captcha_iframe_selector)) {
		$blocked_page = true;
		$this->exts->switchToDefault();
		$this->refresh_if_hard_blocked($url_for_refresh);

		$this->process_geetest_by_clicking();
		$this->process_geetest_by_clicking();
		sleep(5);
	}

	if($this->exts->check_exist_by_chromedevtool($captcha_iframe_selector)) {
		$blocked_page = true;
		$this->exts->switchToDefault();
		$this->refresh_if_hard_blocked($url_for_refresh);

		$this->process_geetest_by_clicking();
		$this->process_geetest_by_clicking();
		sleep(5);
	}

	if($this->exts->check_exist_by_chromedevtool($captcha_iframe_selector)) {
		$blocked_page = true;
		$this->exts->switchToDefault();
		$this->refresh_if_hard_blocked($url_for_refresh);

		$this->process_geetest_by_clicking();
		$this->process_geetest_by_clicking();
		sleep(5);
	}
	return $blocked_page;
}

public function checkFillTwoFactor() {
	$two_factor_selector = 'input#otpCode';
	$two_factor_message_selector = 'p.message_otp, div.otp_message';
	$two_factor_submit_selector = 'input#sendOTP';

	if($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3){
		$this->exts->log("Two factor page found.");
		$this->exts->capture("2.1-two-factor");

		if($this->exts->getElement($two_factor_message_selector) != null){
			$this->exts->two_factor_notif_msg_en = "";
			for ($i=0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) { 
				$this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en.$this->exts->getElements($two_factor_message_selector)[$i]->getText()."\n";
			}
			$this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
			$this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
			$this->exts->log("Message:\n".$this->exts->two_factor_notif_msg_en);
		}
		if($this->exts->two_factor_attempts == 2) {
			$this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en .' '. $this->exts->two_factor_notif_msg_retry_en;
			$this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de .' '. $this->exts->two_factor_notif_msg_retry_de;
		}

		$two_factor_code = trim($this->exts->fetchTwoFactorCode());
		if(!empty($two_factor_code) && trim($two_factor_code) != '') {
			$this->exts->log("checkFillTwoFactor: Entering two_factor_code.".$two_factor_code);
			$this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
			
			$this->exts->log("checkFillTwoFactor: Clicking submit button.");
			sleep(3);
			$this->exts->capture("2.2-two-factor-filled-".$this->exts->two_factor_attempts);

			$this->exts->moveToElementAndClick($two_factor_submit_selector);
			sleep(15);
			$this->exts->log("checkFillTwoFactor: Clicked submit button.");

			$this->exts->capture("after-submit-2fa");

			if($this->exts->getElement($two_factor_selector) == null){
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

function downloadInvoice(){
	$this->exts->log("Begin downlaod invoice ");
	sleep(2);
	try{
		if($this->exts->getElement($this->order_selector) != null ) {
			$this->exts->moveToElementAndClick($this->order_selector);
			sleep(15);          
		}
		// $this->exts->getElement("select#ordersType")->findElements(WebDriverBy::CssSelector('option'))[1]->click();
		if ($this->exts->exists('select#ordersType option[value="O"]')) {
			$this->exts->changeSelectbox('select#ordersType', 'O');
			sleep(10);
		}
		
		while($this->exts->getElement("div#ordersContent > div:nth-child(4) > button")){
			if($this->exts->getElement("div#ordersContent > div:nth-child(4) > button")->getAttribute("style")==""){
				$this->exts->moveToElementAndClick("div#ordersContent > div:nth-child(4) > button");
				sleep(5);
			}else{
				break;
			}
		}
		$this->exts->capture('4-List-invoices');
		if($this->exts->getElement("div#listOrders div.order") != null) {
			$invoices = array();
			$receipts = $this->exts->getElements("div#listOrders div.order");
			foreach ($receipts as $receipt) {
				$this->exts->log("each record");
				if ($this->exts->getElement('div.order__details > div.order__detail a[href*="?pdfFileName="]', $receipt) != null) {
					$receiptDate = $this->exts->extract('div.order__details > div[class="row order__detail"] div.order__element span.texte--important', $receipt);
					$this->exts->log("Invoice date: " . $receiptDate);
					$receiptUrl = $this->exts->extract('div.order__details > div.order__detail a[href*="?pdfFileName="]', $receipt, 'href');
					$receiptName = $this->exts->extract('div.order__details > div[class="row order__detail"] > div > span[data-auto="ccl_orders_travel_number"]', $receipt);
					$receiptFileName = $receiptName . '.pdf';
					$this->exts->log("Invoice name: " . $receiptName);
					$this->exts->log("Invoice Filename: " . $receiptFileName);
					$this->exts->log("Inovice URL: " . $receiptUrl);
					$parsed_date = $this->exts->parse_date($receiptDate,'d/m/Y','Y-m-d');
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
				if(trim($downloaded_file) == "" && !file_exists($downloaded_file)) {
					$this->checkFillRecaptcha();
					$this->checkFillRecaptcha();
					sleep(5);
					$downloaded_file = $this->exts->direct_download($invoice['receiptUrl'], 'pdf', $invoice['receiptFileName']);
				}
				$this->exts->log("downloaded file");
				if(trim($downloaded_file) != "" && file_exists($downloaded_file)) {
					$this->totalFiles += 1;
					$this->exts->log("create file");
					$this->exts->new_invoice($invoice['receiptName'], $invoice['parsed_date'] , $invoice['receiptAmount'], $downloaded_file);
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
	}catch(\Exception $exception){
		$this->exts->log("Exception downlaoding invoice ".$exception->getMessage());
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