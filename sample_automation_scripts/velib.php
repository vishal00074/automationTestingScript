<?php // migrated updated login code and download code
// Server-Portal-ID: 26984 - Last modified: 20.11.2024 13:52:12 UTC - User: 1

/*Define constants used in script*/
public $baseUrl = 'https://www.velib-metropole.fr';
public $loginUrl = 'https://www.velib-metropole.fr/login';
public $invoicePageUrl = 'https://www.velib-metropole.fr/fr/private/account#/my-receipts';

public $username_selector = 'form#login_form input[name="_username"]';
public $password_selector = 'form#login_form input[name="_password"]';
public $remember_me_selector = 'form#login_form input[name="_remember_me"]';
public $submit_login_btn = 'form#login_form button[type="submit"]';

public $checkLoginFailedSelector = 'form#login_form .error-zone';
public $checkLoggedinSelector = 'a.disconnect[href*="/logout"]';
public $isNoInvoice = true;

/**
	* Entry Method thats called for a portal
	* @param Integer $count Number of times portal is retried.
	*/
private function initPortal($count) {
	$this->exts->log('Begin initPortal '.$count);
		
	$this->exts->openUrl($this->baseUrl);
	sleep(5);
	$this->exts->capture("Home-page-without-cookie");
	
	// Load cookies
	$this->exts->loadCookiesFromFile();
	sleep(1);
	$this->exts->openUrl($this->baseUrl);
	sleep(10);
	// after load cookies and open base url, check if user logged in
    if(!$this->checkLogin()){
        $this->exts->clearCookies();
		$this->exts->openUrl($this->loginUrl);
        $this->fillForm();
        sleep(10);
        if($this->checkLogin()){
            $this->exts->log('User logged in.');
            $this->exts->capture("2-post-login");
           
            $this->exts->moveToElementAndClick('a#dropdown-account.userConnected');
            sleep(2);
            $this->exts->moveToElementAndClick('div.dropdown-container a[href*="/private/account"]');
            sleep(10);
            $this->exts->moveToElementAndClick('app-my-dashboard a[href*="#/my-receipts"]');
            sleep(10);

            $this->processInvoices();

            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        }else{
            $this->exts->log(__FUNCTION__.'::Use login failed');
            $this->exts->log(__FUNCTION__.'::Last URL: '. $this->exts->getUrl());
            $this->exts->capture("LoginFailed");
            $this->exts->loginFailure();
        }
        
    }else{
        $this->exts->log('User logged in.');
        $this->exts->capture("2-post-login");
        
        $this->exts->moveToElementAndClick('a#dropdown-account.userConnected');
        sleep(2);
        $this->exts->moveToElementAndClick('div.dropdown-container a[href*="/private/account"]');
        sleep(10);
        $this->exts->moveToElementAndClick('app-my-dashboard a[href*="#/my-receipts"]');
        sleep(10);

        $this->processInvoices();

        if ($this->isNoInvoice) {
            $this->exts->no_invoice();
        }
        $this->exts->success();
    }

	
}

function checkLogin() {
	$this->exts->log("Begin checkLogin ");
	$isLoggedIn = false;
	try {
		if($this->exts->exists($this->checkLoggedinSelector)) {
			$this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
			$isLoggedIn = true;
			
		}
	} catch(\Exception $exception){
		$this->exts->log("Exception checking loggedin ".$exception);
	}
	return $isLoggedIn;
}

private function fillForm() {

    $this->exts->waitTillPresent($this->username_selector);

    if($this->exts->exists($this->username_selector)){
        $this->exts->capture("1-pre-login");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(4);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(4);

        $this->exts->moveToElementAndClick($this->remember_me_selector);
        
        sleep(2);

        $this->exts->capture("1-filled-login");
        $this->checkFillRecaptcha();
        $this->exts->moveToElementAndClick($this->submit_login_btn);
        sleep(10);
    }else{
        $this->exts->log("Login Page not found");
    }
    
		
	
}

private function checkFillRecaptcha() {
	$this->exts->log(__FUNCTION__);
	$recaptcha_iframe_selector = 'iframe[src*="/recaptcha/api2/anchor?"]';
	$recaptcha_textarea_selector = 'textarea[name="g-recaptcha-response"]';
	if($this->exts->exists($recaptcha_iframe_selector)) {
		$iframeUrl = $this->exts->extract($recaptcha_iframe_selector, null, 'src');
		$data_siteKey = explode('&', end(explode("&k=", $iframeUrl)))[0];
		$this->exts->log("iframe url  - " . $iframeUrl);
		$this->exts->log("SiteKey - " . $data_siteKey);
		
		$isCaptchaSolved = $this->exts->processRecaptcha($this->exts->getUrl(), $data_siteKey, false);
		$this->exts->log("isCaptchaSolved - " . $isCaptchaSolved);
		
		if($isCaptchaSolved) {
			// Step 1 fill answer to textarea
			$this->exts->log(__FUNCTION__."::filling reCaptcha response..");
			$recaptcha_textareas =  $this->exts->getElements($recaptcha_textarea_selector);
			for ($i=0; $i < count($recaptcha_textareas); $i++) { 
				$this->exts->execute_javascript("arguments[0].innerHTML = '" .$this->exts->recaptcha_answer. "';", [$recaptcha_textareas[$i]]);
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
			$this->exts->log('Callback function: '.$gcallbackFunction);
			if($gcallbackFunction != null){
				$this->exts->execute_javascript($gcallbackFunction.'("'.$this->exts->recaptcha_answer.'");');
				sleep(10);
			}
		}
	} else {
		$this->exts->log(__FUNCTION__.'::Not found reCaptcha');
	}
}

private function processInvoices() {
	
		$this->exts->log('Invoices found');
		$this->exts->capture("4-page-opened");
		$invoices = [];

		$rows =   $this->exts->querySelectorAll('div.bills-tab table > tbody > tr'); 
		foreach ($rows as $key => $row) {
            $tags = $this->exts->getElements('td, th', $row);

			// $invoiceUrl = $as[0]->getAttribute("href");
			$invoiceDate = trim($tags[0]->getText());
			$invoiceName = trim(preg_replace('/\//', '_', $invoiceDate)) . '-' . $key;
			$invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[1]->getText())) . ' EUR';

			array_push($invoices, array(
				'invoiceName'=>$invoiceName,
				'invoiceDate'=>$invoiceDate,
				'invoiceAmount'=>$invoiceAmount,
				'invoiceSelector'=>'div.bills-tab table > tbody > tr:nth-child('.($key+1).') div [alt="downnload"]'
			));
            $this->isNoInvoice = false;
		}

		// Download all invoices
		$this->exts->log('Invoices: '.count($invoices));
		$count = 1;
		$totalFiles = count($invoices);

		foreach ($invoices as $invoice) {
			$invoiceFileName = $invoice['invoiceName'].'.pdf';

			$this->exts->log('date before parse: '.$invoice['invoiceDate']);

			$invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd/m/Y','Y-m-d');
			$this->exts->log('invoiceName: '.$invoice['invoiceName']);
			$this->exts->log('invoiceDate: '.$invoice['invoiceDate']);
			$this->exts->log('invoiceAmount: '.$invoice['invoiceAmount']);
			$this->exts->log('invoiceSelector: '.$invoice['invoiceSelector']);

			// Download invoice if it not exisited
			if($this->exts->invoice_exists($invoice['invoiceName'])){
				$this->exts->log('Invoice existed '.$invoiceFileName);
			} else {
				$this->exts->log('Dowloading invoice '.$count.'/'.$totalFiles);

				$this->exts->moveToElementAndClick($invoice['invoiceSelector']);
				$this->exts->wait_and_check_download('pdf');

				// find new saved file and return its path
				$downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

				if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
					$this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
					sleep(1);
					$count++;
				} else {
					$this->exts->log('Timeout when download '.$invoiceFileName);
				}
			}
			$this->exts->execute_javascript("window.scrollBy(0,50)");
			sleep(2);
		}
		$this->exts->success();
}

// helper function

