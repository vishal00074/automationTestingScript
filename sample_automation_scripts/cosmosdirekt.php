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

    // Server-Portal-ID: 18489 - Last modified: 26.06.2024 13:04:26 UTC - User: 1

    public $baseUrl = "https://www.cosmosdirekt.de/services/mcd-info/";
    public $loginUrl = "https://www.cosmosdirekt.de/meincosmosdirekt/login/#/login";
    public $homePageUrl = "https://www.cosmosdirekt.de/meincosmosdirekt/";
    public $username_selector = 'form#loginFormService input[name="benutzername"], form#loginForm input#loginUsername';
    public $password_selector = 'form#loginFormService input[name="passwort"], form#loginForm input#loginPassword';
    public $submit_button_selector = 'form#loginFormService button#btnLoginService, form#loginForm button#loginButton';
    public $login_tryout = 0;
    public $restrictPages = 3;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        $this->exts->openUrl($this->baseUrl);
        sleep(2);
        $this->exts->capture("Home-page-without-cookie");

        $isCookieLoginSuccess = false;
        // Comment because impact to load login page
        // if($this->exts->loadCookiesFromFile()) {
        // 	$this->exts->openUrl($this->homePageUrl);
        // 	sleep(15);

        // 	if($this->checkLogin()) {
        // 		$isCookieLoginSuccess = true;
        // 	} else {
        // 		$this->exts->clearCookies();
        // 		$this->exts->openUrl($this->loginUrl);
        // 	}
        // }

        if (!$isCookieLoginSuccess) {
            $this->exts->capture("after-login-clicked");
            if ($this->exts->exists('button#popin_tc_privacy_button')) {
                $this->exts->moveToElementAndClick('button#popin_tc_privacy_button');
                sleep(5);
            }
            $this->exts->openUrl($this->loginUrl);
            sleep(15);
            for ($i = 0; $i < 17; $i++) {
                $this->exts->type_key_by_xdotool('Tab');
                sleep(1);
            }

            $this->fillForm(0);
            sleep(30);

            $err_msg = $this->exts->extract('form#loginFormService p.text-danger#allgTextError');

            if ($err_msg != "" && $err_msg != null) {
                $this->exts->log($err_msg);
                $this->exts->loginFailure(1);
            }

            if ($this->checkLogin()) {
                //Please confirm your email address once for us, return account not ready
                $this->switchToFrame('iframe#myCosmosIframeInner');
                if ($this->exts->exists('input#inp_aktivierungscode')) {
                    $this->exts->account_not_ready();
                } else {
                    $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                    $this->exts->capture("LoginSuccess");
                    $this->invoicePage();
                }
            } else {
                $this->exts->capture("LoginFailed");
                $this->exts->loginFailure();
            }
        } else {
            sleep(10);
            if ($this->exts->exists('button#popin_tc_privacy_button')) {
                $this->exts->moveToElementAndClick('button#popin_tc_privacy_button');
                sleep(5);
            }
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->invoicePage();
        }
    }

    function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);


        $this->exts->capture("1-pre-login");
        $this->exts->log("Enter Username");
        $this->exts->type_text_by_xdotool($this->username);
        $this->exts->type_key_by_xdotool('Tab');
        sleep(2);

        $this->exts->log("Enter Password");
        $this->exts->type_text_by_xdotool($this->password);
        $this->exts->type_key_by_xdotool('Tab');
        sleep(2);

        $this->exts->capture("1-login-page-filled");
        $this->exts->type_key_by_xdotool('Return');
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
            $this->switchToFrame('iframe#myCosmosIframeInner');
            if ($this->exts->exists('a.logout, a[href*=logout]') && !$this->exts->exists($this->password_selector)) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            }
        } catch (Exception $exception) {
            $this->exts->log("Exception checking loggedin " . $exception);
        }
        return $isLoggedIn;
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

    public function invoicePage()
    {
        $this->exts->log("Invoice page");

        $this->switchToFrame('iframe#myCosmosIframeInner');

        $this->exts->moveToElementAndClick('#naviSp > div > ul > li > ul > li:nth-child(2) > a');
        sleep(15);

        $this->switchToFrame('iframe#myCosmosIframeInner');
        $this->selectAccount();

        $this->exts->openUrl($this->homePageUrl);
        sleep(15);

        $iframeUrl = $this->exts->extract('iframe#myCosmosIframeInner', null, 'src');
        if ($iframeUrl != '') {
            $this->exts->log("iframeUrl: " . $iframeUrl);
            $this->switchToFrame('iframe#myCosmosIframeInner');
            sleep(15);
            $this->getDocUrls();
        } else {
            $this->exts->log('No Document URLs');
        }

        $this->exts->openUrl($this->exts->getUrl());
        sleep(15);

        $this->getAngebotsordnerDocUrls();

        $this->exts->openUrl('https://www.cosmosdirekt.de/meincosmosdirekt/#/schutz');
        sleep(10);
        $this->processNewInvoice();


        if ($this->totalFiles == 0) {
            $this->exts->log("No invoice !!! ");
            $this->exts->no_invoice();
        }

        $this->exts->success();
    }

    function getDocUrls()
    {
        $this->exts->log("Start getDocUrls");
        if ($this->exts->exists('div#naviSp ul.sub0 li ul.sub1 li a')) {
            $links = $this->exts->getElements('div#naviSp ul.sub0 li ul.sub1 li a');
            foreach ($links as $link) {
                $title = trim($link->getAttribute('title'));
                if (strpos($title, 'Postfach') !== false) {
                    $this->exts->executeSafeScript(
                        "arguments[0].setAttribute(\"id\", \"postfachDoc\");",
                        array($link)
                    );
                    break;
                }
            }

            $this->exts->moveToElementAndClick('a#postfachDoc');
            sleep(15);

            $this->downloadMails();
        } else {
            $this->exts->log('No Document URLs');
        }
    }

    function getAngebotsordnerDocUrls()
    {
        $this->exts->log("Start getAngebotsordnerDocUrls");
        if ($this->exts->exists('div#naviSp ul.sub0 li ul.sub1 li a')) {
            $links = $this->exts->getElements('div#naviSp ul.sub0 li ul.sub1 li a');
            foreach ($links as $link) {
                $title = trim($link->getAttribute('title'));
                if (strpos($title, 'Angebotsordner') !== false) {
                    $this->exts->executeSafeScript(
                        "arguments[0].setAttribute(\"id\", \"angebotsordnerDoc\");",
                        array($link)
                    );
                    break;
                }
            }

            // $this->exts->moveToElementAndClick('a#angebotsordnerDoc');
            // sleep(15);

            // $this->downloadMails();
        } else {
            $this->exts->log('No Document URLs');
        }
    }

    function downloadMails()
    {
        $this->exts->log('start downloadMails');
        $this->exts->capture('4-List-emails');
        if ($this->exts->exists('div.mycosmosPostfachBorder ul li')) {
            $emails = $this->exts->getElements('div.mycosmosPostfachBorder ul li:not(.alt)');
            $emails_array = array();
            foreach ($emails as $i => $email) {
                $this->exts->log($i);
                if ($this->exts->exists('a.mycosmosPostfachLink', $email)) {
                    $this->exts->log('record');
                    $this->exts->log($i);
                    $receiptDate = $this->exts->extract('a.mycosmosPostfachLink div.mycosmosPostfachDatum', $email);
                    $receiptUrl = $this->exts->getElement('a.mycosmosPostfachLink', $email);
                    $this->exts->executeSafeScript(
                        "arguments[0].setAttribute(\"id\", \"email\" + arguments[1]);",
                        array($receiptUrl, $i)
                    );

                    $receiptUrl = "div.mycosmosPostfachBorder ul li a#email" . $i;
                    $receiptName = trim(str_replace('.', '', $receiptDate));
                    $receiptFileName = $receiptName . '.pdf';
                    $parsed_date = $this->exts->parse_date($receiptDate, 'd.m.Y', 'Y-m-d');
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
                    array_push($emails_array, $invoice);
                }
            }

            foreach ($emails_array as $email) {
                $this->exts->capture('list-emails');
                $this->exts->log('email url: ' . $email['receiptUrl']);

                $t_emails = $this->exts->getElements('div.mycosmosPostfachBorder ul li:not(.alt) a.mycosmosPostfachLink');
                foreach ($t_emails as $j => $t_email) {
                    $t_email_url = $t_email;
                    $this->exts->executeSafeScript(
                        "arguments[0].setAttribute(\"id\", \"email\" + arguments[1]);",
                        array($t_email_url, $j)
                    );
                }

                $this->exts->moveToElementAndClick($email['receiptUrl']);
                sleep(15);

                $this->exts->executeSafeScript("document.querySelector(\"div#naviSp\").innerHTML = '';");
                $this->exts->executeSafeScript("document.querySelector(\"div#marginal\").innerHTML = '';");
                sleep(2);

                $this->totalFiles += 1;
                $downloaded_file = $this->exts->download_current($email['receiptFileName']);
                if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($email['receiptName'], $email['parsed_date'], $email['receiptAmount'], $downloaded_file);
                }

                $this->exts->moveToElementAndClick('input[name="zurueck"]');
                sleep(15);
            }
        }
    }

    public function selectAccount()
    {
        $this->exts->log('Start select account');
        if ($this->exts->exists('div.mycosmosAufklappen[style="display:block;"] table a[name="Vertragsdetails"]')) {
            $accounts = $this->exts->getElements('div.mycosmosAufklappen[style="display:block;"] table a[name="Vertragsdetails"]');
            $account_array = array();
            foreach ($accounts as $i => $account) {
                $acc_url = $account;
                $this->exts->executeSafeScript(
                    "arguments[0].setAttribute(\"id\", \"account\" + arguments[1]);",
                    array($acc_url, $i)
                );

                $acc_url = 'div.mycosmosAufklappen[style="display:block;"] table a#account' . $i;
                $acc_number = $account->getAttribute('href');
                $acc_number = trim(explode("'", end(explode("detailsLink('", $acc_number)))[0]);
                $this->exts->log("acc_url" . $acc_url);
                $this->exts->log("acc_number" . $acc_number);
                $acc = array(
                    'acc_url' => $acc_url,
                    'acc_number' => $acc_number
                );

                array_push($account_array, $acc);
            }

            foreach ($account_array as $account) {
                $this->exts->moveToElementAndClick('#naviSp > div > ul > li > ul > li:nth-child(2) > a');
                sleep(15);

                $this->exts->capture('list-account');

                $this->switchToFrame('iframe#myCosmosIframeInner');
                $t_accounts = $this->exts->getElements('div.mycosmosAufklappen[style="display:block;"] table a[name="Vertragsdetails"]');
                foreach ($t_accounts as $j => $t_account) {
                    $t_acc_url = $t_account;
                    $this->exts->executeSafeScript(
                        "arguments[0].setAttribute(\"id\", \"account\" + arguments[1]);",
                        array($t_acc_url, $j)
                    );
                }

                $this->exts->moveToElementAndClick($account['acc_url']);
                sleep(15);

                $this->switchToFrame('iframe#myCosmosIframeInner');
                $this->exts->moveToElementAndClick('a[name="Vertragsdetails"]');
                sleep(15);

                $this->switchToFrame('iframe#myCosmosIframeInner');
                $this->exts->moveToElementAndClick('a[href*="dokumente"]');
                sleep(15);

                // $this->exts->moveToElementAndClick('a[href*="mvdrisikodokumente"]');
                // sleep(15);

                $this->switchToFrame('iframe#myCosmosIframeInner');
                $this->downloadInvoice($account['acc_number']);
            }
        }
    }

    /**
     *method to download incoice
     */
    public $totalFiles = 0;
    public function downloadInvoice($acc_number)
    {
        $this->exts->log("Begin download invoice with account: " . $acc_number);

        $this->exts->capture('4-List-invoice');

        try {
            if ($this->exts->getElement('div.TabListeInhalt dd') != null) {
                $receipts = $this->exts->getElements('div.TabListeInhalt dd');
                $invoices = array();
                foreach ($receipts as $i => $receipt) {
                    $tags = $this->exts->getElements('ul li', $receipt);
                    if (count($tags) >= 3 && $this->exts->getElement('ul li a[href*=".pdf"], ul li a[onclick*=".pdf"]', $receipt) != null) {
                        $receiptDate = $tags[0]->getText();
                        $tempreceiptUrl = trim($this->exts->extract('ul li a[href*=".pdf"]:first-child', $receipt, 'href'));
                        if ($tempreceiptUrl == '') {
                            $tempreceiptUrl = $this->exts->getElement('ul li a[onclick*=".pdf"]', $receipt)->getAttribute('onclick');
                        }
                        $receiptUrl = $this->exts->getElement('ul li a[href*=".pdf"]:first-child', $receipt);
                        if ($receiptUrl == null) {
                            $receiptUrl = $this->exts->getElements('ul li a[onclick*=".pdf"]', $receipt)[0];
                        }
                        $this->exts->executeSafeScript(
                            "arguments[0].setAttribute(\"id\", \"invoice\" + arguments[1]);",
                            array($receiptUrl, $i)
                        );

                        $receiptUrl = "ul li a#invoice" . $i;
                        $receiptName = trim(explode('.pdf', end(explode(", '", $tempreceiptUrl)))[0]);
                        $receiptFileName = $receiptName . '.pdf';
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
                    if ($this->exts->document_exists($invoice['receiptFileName'])) {
                        continue;
                    }

                    if ($this->exts->getElement($invoice['receiptUrl']) != null) {
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


    public function processNewInvoice($key = 0)
    {
        $this->exts->waitTillPresent('mcd-root');
        $this->exts->log("Begin process invoices");
        $this->exts->capture('process-List-contracts');

        if ($this->exts->exists('mcd-root')) {
            $this->exts->log("switchToFrame mcd-root");
            $this->switchToFrame('mcd-root');
            sleep(2);
        }


        if ($this->exts->exists('ion-router-outlet')) {
            $this->exts->log("switchToFrame ion-router-outlet");
            $this->switchToFrame('ion-router-outlet');
            sleep(2);
        }

        if ($this->exts->exists('ion-content')) {
            $this->exts->log("switchToFrame ion-content");
            $this->switchToFrame('ion-content');
            sleep(2);
        }

        $rows =  $this->exts->getElements('mcd-product-card');
        $this->exts->log('Contract rows count:: ' . count($rows));

        try {
            $rows[$key]->click();
            sleep(10);
            $this->downloadInvoiceNew();
            $key++;
            $this->exts->openUrl('https://www.cosmosdirekt.de/meincosmosdirekt/#/schutz');
            sleep(10);
            if(count($rows) > $key){
                $this->processNewInvoice($key);
            }
            
        } catch (\Exception $e) {
            $this->exts->log('Contract Error:: ' . $e->getMessage());
        }
    }

    public function downloadInvoiceNew()
    {
        $this->exts->log("Begin download invoices");
        $this->exts->capture('4.1-List-contracts');


        $this->exts->waitTillAnyPresent('mcd-root');
        $this->exts->log("Begin process invoices");
        $this->exts->capture('process-List-contracts');

        if ($this->exts->exists('mcd-root')) {
            $this->exts->log("switchToFrame mcd-root");
            $this->switchToFrame('mcd-root');
            sleep(2);
        }


        if ($this->exts->exists('ion-router-outlet')) {
            $this->exts->log("switchToFrame ion-router-outlet");
            $this->switchToFrame('ion-router-outlet');
            sleep(2);
        }

        if ($this->exts->exists('ion-content')) {
            $this->exts->log("switchToFrame ion-content");
            $this->switchToFrame('ion-content');
            sleep(2);
        }

        $rows =  $this->exts->getElements('mcd-list-element[data-testid="dokument-element"]');
        $this->exts->log('Contract rows count:: ' . count($rows));

        foreach ($rows as $key => $row) {
            sleep(2);
            $invoiceUrl = '';
            $invoiceBtn = $this->exts->getElement('mcd-list-element div[aria-label="download"]', $row);
            $invoiceName = time();
            $invoiceDate = $this->exts->extract('div[data-testing-id="subtitle"]', $row);
            $invoiceAmount ='';

            $this->isNoInvoice = false;

            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' .  $invoiceName);
            $this->exts->log('invoiceDate: ' . $invoiceDate);
            $this->exts->log('invoiceAmount: ' . $invoiceAmount);
            $this->exts->log('invoiceUrl: ' .  $invoiceUrl);
            $invoiceFileName =  $invoiceName . '.pdf';
            $invoiceDate = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' .  $invoiceDate);

            $downloaded_file = $this->exts->click_and_download($invoiceBtn, 'pdf', $invoiceFileName);
            sleep(2);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoiceUrl,  $invoiceDate, $invoiceAmount, $downloaded_file);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
    }

    // function downloadInvoiceNew()
    // {
    //     $this->exts->log("Begin download invoices");
    //     $this->exts->capture('4.1-List-contracts');


    //     $listCardsArray = $this->exts->evaluate('document.querySelector("mcd-root").shadowRoot.querySelectorAll("mcd-product-card")');
    //     $listCardsDecode = json_decode($listCardsArray, true);
    //     $this->exts->log('listCardsDecode ' . print_r($listCardsDecode));
    //     $listCards = $listCardsDecode['result']['result']['value'];
    //     $countElements = count($listCards);
    //     $this->exts->log("---- Count Contracts: " . $countElements);
    //     if ($countElements > 0) {
    //         for ($i = 0; $i < $countElements; $i++) {

    //             $this->exts->executeSafeScript('document.querySelector("mcd-root").shadowRoot.querySelectorAll("mcd-product-card")[arguments[0]].click()', [$i]);
    //             sleep(5);
    //             $contractNum = '';
    //             $contractNumArray = $this->exts->evaluate('document.querySelector("mcd-root").shadowRoot.querySelector("mcd-list-element[label=Vertragsnummer]").innerText');
    //             $contractNumDecode = json_decode($contractNumArray, true);
    //             $contractNum = $contractNumDecode['result']['result']['value'];
    //             $contractNum = explode(PHP_EOL, $contractNum);
    //             $contractNum = end($contractNum);
    //             $this->exts->log("------------Contract Num: " . $contractNum);
    //             //find invoices

    //             $listInvoicesArray = $this->exts->evaluate('document.querySelector("mcd-root").shadowRoot.querySelectorAll("mcd-dokumente mcd-list-element")');
    //             $invoicesDecode = json_decode($listInvoicesArray, true);
    //             $listInvoices = $invoicesDecode['result']['result']['value'];

    //             $countInvoices = count($listInvoices);
    //             $this->exts->log("Invoice Found: " . $countInvoices);
    //             if ($countInvoices > 0) {
    //                 for ($index = 0; $index < $countInvoices; $index++) {
    //                     $this->exts->executeSafeScript('arguments[0].querySelector("mcd-svgicon[data-testid=right-icon]").click()', [$listInvoices[$index]]);
    //                     $invoiceDateArray = $this->exts->evaluate('return arguments[0].querySelector("[data-testing-id=subtitle]").innerText', [$listInvoices[$index]]);

    //                     $invoiceDateDecode = json_decode($invoiceDateArray, true);
    //                     $invoiceDate = $invoiceDateDecode['result']['result']['value'];

    //                     $invoiceDate = explode('|', $invoiceDate);
    //                     $invoiceDate = array_shift($invoiceDate);
    //                     $invoiceName = $contractNum . '_' . $invoiceDate;
    //                     $invoiceFileName = $invoiceName . '.pdf';
    //                     $this->exts->log('----------------------------------');
    //                     $this->exts->log('invoiceName: ' . $invoiceName);
    //                     $this->exts->wait_and_check_download('pdf');
    //                     $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
    //                     if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
    //                         $this->exts->new_invoice($invoiceName, '', '', $invoiceFileName);
    //                         sleep(1);
    //                         $this->totalFiles++;
    //                     } else {
    //                         $this->exts->log('Timeout when download ' . $invoiceFileName);
    //                     }
    //                 }
    //             }
    //             sleep(3);
    //             $this->exts->executeSafeScript('document.querySelector("mcd-root").shadowRoot.querySelector(".zurueck-button").click()');
    //             sleep(5);
    //         }
    //     }
    // }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
