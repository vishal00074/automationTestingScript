<?php // migrated
// Server-Portal-ID: 1496571 - Last modified: 02.11.2024 03:31:45 UTC - User: 1

/*Define constants used in script*/
public $baseUrl = 'https://thrivethemes.com/affiliates/account.php';
public $loginUrl = 'https://thrivethemes.com/affiliates/login.php';
public $invoicePageUrl = 'https://thrivethemes.com/affiliates/account.php?page=3';
public $username_selector = 'input[name="userid"]';
public $password_selector = 'input[name="password"]';
public $remember_me_selector = '';
public $submit_login_selector = 'form button[type="submit"]';

public $check_login_failed_selector = 'div.alert';
public $check_login_success_selector = 'a[href*="logout"]';

public $isNoInvoice = true; 
/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count) {
	$this->exts->log('Begin initPortal '.$count);	
	$this->exts->openUrl($this->baseUrl);
	sleep(10);
	// Load cookies
	$this->exts->loadCookiesFromFile();
	sleep(1);

	$this->exts->type_key_by_xdotool('Tab');
	sleep(1);
	$this->exts->type_key_by_xdotool("Space");
	sleep(10);

	$this->check_solve_blocked_page();
	$this->process_hcaptcha_by_clicking();
	$this->exts->capture('1-init-page');

	// If user hase not logged in from cookie, clear cookie, open the login url and do login
	if($this->exts->getElement($this->check_login_success_selector) == null) {
		$this->exts->log('NOT logged via cookie');
		$this->exts->clearCookies();
		$this->exts->openUrl($this->loginUrl);
		sleep(15);
        $this->check_solve_blocked_page();
		// for ($i=0; $i < 3 && $this->exts->exists('#challenge-stage iframe[src*="challenges.cloudflare"]') && !$this->exts->exists('#cf-hcaptcha-container div:not([style*="display: none"]) iframe[src*="hcaptcha.com/captcha/"]'); $i++) { 
		// 	$this->exts->moveToElementAndClick('#challenge-stage iframe[src*="challenges.cloudflare"]');
		// 	sleep(25);
		// }
		$this->process_hcaptcha_by_clicking();
		$this->process_hcaptcha_by_clicking();
		$this->process_hcaptcha_by_clicking();
		$this->checkFillLogin();
		sleep(15);
	}

	// then check user logged in or not
	if($this->exts->getElement($this->check_login_success_selector) != null) {
		sleep(3);
		$this->exts->log(__FUNCTION__.'::User logged in');
		$this->exts->capture("3-login-success");

		// Open invoices url and download invoice
		$this->exts->openUrl($this->invoicePageUrl);
		$this->processInvoices();
		
		// Final, check no invoice
		if($this->isNoInvoice){
			$this->exts->no_invoice();
		}
		$this->exts->success();
	} else {
		$this->exts->log(__FUNCTION__.'::Use login failed');
		if(strpos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'nvalid login credentials') !== false) {
			$this->exts->loginFailure(1);
		} else {
			$this->exts->loginFailure();
		}
	}
}

private function checkFillLogin() {
	$this->exts->type_key_by_xdotool("F5");
	sleep(7);
	$this->check_solve_blocked_page();
	$this->check_solve_blocked_page();
	$this->check_solve_blocked_page();
	if($this->exts->exists($this->password_selector)) {
		// $this->exts->capture_by_chromedevtool("2-login-page");
		$this->exts->log("Enter Username");
		$this->exts->type_text_by_xdotool($this->username_selector);
		$this->exts->type_key_by_xdotool("ctrl+a");
		$this->exts->type_key_by_xdotool("Delete");
		$this->exts->type_text_by_xdotool($this->username);
		sleep(1);
		$this->exts->log("Enter Password");
		$this->exts->type_text_by_xdotool($this->password_selector);
		$this->exts->type_key_by_xdotool("ctrl+a");
		$this->exts->type_key_by_xdotool("Delete");
		$this->exts->type_text_by_xdotool($this->password);
		sleep(3);
		// $this->exts->capture_by_chromedevtool("2-login-page-filled");
		$this->exts->click_by_xdotool($this->submit_login_selector);
		sleep(10);
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



private function process_hcaptcha_by_clicking() {
	$hcaptcha_iframe_selector = '#cf-hcaptcha-container div:not([style*="display: none"]) iframe[src*="hcaptcha.com/captcha/"]';
	$hcaptcha_challenger_wraper_selector = 'div:not([aria-hidden="true"]) > div >  iframe[src*="/hcaptcha.html#frame=challenge"]';
	if($this->exts->exists($hcaptcha_iframe_selector)) {
		$this->reload_iframe();
		sleep(10);
		$this->exts->capture("hcaptcha");
		// Check if challenge images hasn't showed yet, Click checkbox to show images challenge
		if(!$this->exts->exists($hcaptcha_challenger_wraper_selector)){
			$this->switchToFrame($hcaptcha_iframe_selector);
			$this->exts->click_element('#checkbox');
			sleep(3);
			$this->exts->switchToDefault();
			// Some time, because we wait too long, after clicking checkbox, it reload the page again, so click checkbox again
			if(!$this->exts->exists($hcaptcha_challenger_wraper_selector) && !$this->exts->exists('div:not([aria-hidden="true"]) > div >  iframe[src*="/hcaptcha.html#frame=challenge"]')){
				sleep(5);
				$this->switchToFrame($hcaptcha_iframe_selector);
				$this->exts->click_element('#checkbox');
				sleep(3);
				$this->exts->switchToDefault();
			}
		}
		$this->exts->switchToDefault();
		if($this->exts->exists($hcaptcha_challenger_wraper_selector)) {
			$this->exts->capture("hcaptcha");
			$language_code = $this->exts->extract('[lang]', null, "lang");
			$this->switchToFrame($hcaptcha_challenger_wraper_selector);
			// Change language to English
			$this->exts->click_element('div.display-language');
			sleep(1);
			$this->exts->click_element('//*[@role="option"]//*[text()="English"]');
			sleep(3);
			$captcha_instruction = $this->exts->extract('.challenge-header .prompt-text');
			$this->exts->log('language_code: '.$language_code.' Instruction: '. $captcha_instruction);

			// $this->exts->switchToDefault();
			$coordinates = $this->exts->processClickCaptcha('div.task-grid', $captcha_instruction, '', $json_result=true);// use $language_code and $captcha_instruction if they changed captcha content
			if($coordinates == ''){
				$coordinates = $this->exts->processClickCaptcha('div.task-grid', $captcha_instruction, '', $json_result=true);
			}
			if($coordinates != ''){
				$challenge_wraper = $this->exts->getElement('div.task-grid');
				foreach ($coordinates as $coordinate) {
					$actions = $this->exts->webdriver->action();
					$this->exts->log('Clicking X/Y: '.$coordinate['x'].'/'.$coordinate['y']);
					$actions->moveToElement($challenge_wraper, intval($coordinate['x']), intval($coordinate['y']))->click()->perform();
				}
				sleep(1);
				$this->switchToFrame($hcaptcha_challenger_wraper_selector);
				$this->exts->click_element('.button-submit');
				sleep(3);
				$this->exts->switchToDefault();
			}
			$this->exts->switchToDefault();
			return true;
		}
		$this->exts->switchToDefault();
		return true;
	}
	$this->exts->switchToDefault();
	return false;
}

private function reload_iframe(){
	$this->exts->log("funcaptcha without content - reload iframe");
	$this->exts->switchToDefault();
	// reload frame to get content
	$javascript_expression = '
		var captcha_iframe = document.querySelector(\'#cf-hcaptcha-container div:not([style*="display: none"]) iframe[src*="hcaptcha.com/captcha/"]\');
		captcha_iframe.src = captcha_iframe.src;
	';
    $this->exts->evaluate( $javascript_expression);
	sleep(10);
	$this->exts->log("funcaptcha without content - reload iframe");
	$this->exts->switchToDefault();
	$javascript_expression = '
		var captcha_iframe = document.querySelector(\'#cf-hcaptcha-container div:not([style*="display: none"]) iframe[src*="hcaptcha.com/captcha/"]\');
		captcha_iframe.src = captcha_iframe.src;
	';
	$this->exts->execute_javascript($javascript_expression);
	sleep(10);
}


private function check_solve_blocked_page()
{
$this->exts->capture_by_chromedevtool("blocked-page-checking");

for ($i = 0; $i < 10; $i++) {
    if ($this->exts->check_exist_by_chromedevtool('div[style="display: grid;"] > div > div')) {
        $this->exts->capture_by_chromedevtool("blocked-by-cloudflare");

        sleep(15); 

        if ($this->exts->check_exist_by_chromedevtool('div[style="display: grid;"] > div > div')) {
            $this->exts->refresh();
            sleep(10);
        }

        if ($this->exts->check_exist_by_chromedevtool('div[style="display: grid;"] > div > div')) {
            $this->exts->click_by_xdotool('div[style="display: grid;"] > div > div', 30, 28);
            sleep(15);
        }
        if (!$this->exts->check_exist_by_chromedevtool('div[style="display: grid;"] > div > div')) {
            break;
        }
    } else {
        break;
    }
}
}




private function check_solve_cloudflare_page() {
    $unsolved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) <= 0]';
    $solved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) > 0]';
    $this->exts->capture("cloudflare-checking");
    if(
        !$this->exts->oneExists([$solved_cloudflare_input_xpath, $unsolved_cloudflare_input_xpath]) && 
        $this->exts->exists('#cf-please-wait > p:not([style*="display: none"]):not([style*="display:none"])')
    ){
        for ($waiting=0; $waiting < 10; $waiting++) {
            sleep(2);
            if($this->exts->oneExists([$solved_cloudflare_input_xpath, $unsolved_cloudflare_input_xpath])){
                sleep(3);
                break;
            }
        }
    }

    if($this->exts->exists($unsolved_cloudflare_input_xpath)){
        $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
        sleep(5);
        $this->exts->capture("cloudflare-clicked-1", true);
        sleep(3);
        if($this->exts->exists($unsolved_cloudflare_input_xpath)){
            $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
            sleep(5);
            $this->exts->capture("cloudflare-clicked-2", true);
            sleep(15);
        }
        if($this->exts->exists($unsolved_cloudflare_input_xpath)){
            $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
            sleep(5);
            $this->exts->capture("cloudflare-clicked-3", true);
            sleep(15);
        }
    }
}


private function processInvoices() {
	sleep(25);
	$this->exts->capture("4-invoices-page");
	$this->exts->changeSelectbox('select[name="dyntable_payment_history_length"]', '100', 5);
	$invoices = [];

	$rows_len = count($this->exts->getElements('table#dyntable_payment_history > tbody > tr'));
	for ($i = 0; $i < $rows_len; $i++) {
		$row = $this->exts->getElements('table#dyntable_payment_history > tbody > tr')[$i];
		$tags = $this->exts->getElements('td', $row);
		if(count($tags) >= 4 && $this->exts->getElement('form[action="invoice.php"] input[type="submit"]', $tags[3]) != null) {
			$download_button = $this->exts->getElement('form[action="invoice.php"] input[type="submit"]', $tags[3]);
			$invoiceName = trim(str_replace('/', '', $tags[0]->getAttribute('innerText')));
			$invoiceDate = trim($tags[0]->getAttribute('innerText'));
			$invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[2]->getText())) . ' USD';

			$this->isNoInvoice = false;

			$invoiceFileName = $invoiceName.'.pdf';
			$invoiceDate = $this->exts->parse_date($invoiceDate, 'd/m/Y','Y-m-d');
			$this->exts->log('Date parsed: '.$invoiceDate);

			if ($this->exts->document_exists($invoiceFileName)) {
				continue;
			}

			try{
				$this->exts->log('Click download button');
				$download_button->click();
			} catch(\Exception $exception){
				$this->exts->log('Click download button by javascript');
				$this->exts->execute_javascript("arguments[0].click()", [$download_button]);
			}
			sleep(8);

			// $handles = $this->exts->webdriver->getWindowHandles();
			// if(count($handles) > 1){
			// 	$this->exts->webdriver->switchTo()->window(end($handles));
			// }

            $this->exts->switchToIfNewTabOpened();
            $newTab = $this->exts->getUrl();

			sleep(2);
			if($this->exts->exists('a[onclick*="print"]')){
				$downloaded_file = $this->exts->download_current($invoiceFileName, 5);
				if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
					$this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $downloaded_file);
					sleep(1);
				} else {
					$this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
				}
			} else {
				$this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
			}
            // sleep(5);
            // $this->exts->wait_and_check_download('pdf');
            // $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName); 
            // if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
            //     $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $downloaded_file);
            //     sleep(1);
            // } else {
            //     $this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
            // }
            // $this->exts->webdriver->close();
            // sleep(3);
            // $this->exts->webdriver->switchTo()->window($handles[0]);
            // sleep(2);
            $this->exts->closeTab($newTab);
		}
	}
}