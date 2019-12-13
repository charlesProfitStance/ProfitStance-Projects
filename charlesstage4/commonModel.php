<?php
	
	setlocale(LC_MONETARY, 'en_US');
	date_default_timezone_set('America/Denver');
	$globalCurrentDate				= date("Y-m-d H:i:s");
	
	// error log constants
	$criticalErrors																						= 0;
	$projectDebugPriority																				= 1;
	$debugCoreFunctionality																				= 3;
	$generalSystemPerformance																			= 6;
	// end error log constants
	
	//errorLog($pageName, $generalSystemPerformance);
	
	$clientAddress															= "127.0.0.1";
	$clientHostname															= "localhost";
	
	if (isset($_SERVER['REMOTE_ADDR']))
	{
		$clientAddress														= $_SERVER['REMOTE_ADDR'];
		$clientHostname														= gethostbyaddr($_SERVER['REMOTE_ADDR']);
	}
	
	$sid 																	= session_id();
	
	$_SESSION['sid']														= $sid;

	$_SESSION['clientAddress']												= $clientAddress;
	$_SESSION['clientHostname']												= $clientHostname;
	
	$languageCode				 											= "EN";

	$returnValue															= 0;
	
	// third party classes
	//require_once 'vendor/twilio/sdk/Twilio/autoload.php';
	// require_once("../../qa/ExchangeClasses/class_GeminiTradeTransaction.php");

	 require_once '../vendor/twilio/sdk/Twilio/autoload.php';
	 require_once './KrakenAPIClient.php';
		

	// require_once("class_GeminiTradeTransaction.php");
			

	// Use the REST API Client to make requests to the Twilio REST API
	use Twilio\Rest\Client;
	
	// Declare Kraken API Namespace
	use Payward\KrakenAPI;
	
	// UTILITY FUNCTIONS
	
	function areSessionVariablesSet($logoutIfNeeded)
	{
		$returnValue														= 0;
		
		if (!empty($_SESSION['sid']) && !empty($_SESSION['clientAddress']) && !empty($_SESSION['clientHostname']))
		{	
			$returnValue													= 1;	
		}
		else
		{
			if ($logoutIfNeeded == 1)
			{
				errorLog("session variables are not set - $pageName");
				header('Location: /logout.php');
				exit();	
			}
		}
		
		return $returnValue;
	}
	
	function createProfitStanceTransactionIDValue($liuAccountID, $assetTypeID, $transactionSourceID, $nativeTransactionIDValue, $globalCurrentDate, $sid)
	{
		$profitStanceTransactionIDValue										= md5("$globalCurrentDate create $liuAccountID profitstance $assetTypeID transaction $transactionSourceID ID $nativeTransactionIDValue value".md5($sid));
		
		return $profitStanceTransactionIDValue;
	}
	
	function doesEmailAccountExist($emailAddress, $dbh)
	{
		$responseObject														= array();
		$responseObject['doesEmailAccountExist']							= false;
		$responseObject['resultMessage']									= "The supplied email address was not found";
		
		try
		{	
			$checkForExistingEmail											= $dbh->prepare("SELECT
		userAccountID
	FROM
		UserAccounts
	WHERE
		UserAccounts.encryptedEmailAddress = AES_ENCRYPT(:emailAddress, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512)))");
			
			$checkForExistingEmail -> bindValue(':emailAddress', $emailAddress);
		
			$numAccounts													= 0;
			
			if ($checkForExistingEmail -> execute() && $checkForExistingEmail -> rowCount() > 0)
			{
				$row 														= $checkForExistingEmail -> fetchObject();
				
				$userAccountID												= $row -> userAccountID;
				
				if ($userAccountID > 0)
				{
					$responseObject['doesEmailAccountExist']				= true;
					$responseObject['resultMessage']						= "The supplied email address was found.";
					$responseObject['userAccountID']						= $userAccountID;
				}
			}
		}
	    catch (PDOException $e) 
	    {
	    	$responseObject['resultMessage']								= "Error: Could not determine whether the supplied email address exists because of a database error: ".$e->getMessage();
			errorLog($e->getMessage());
		}
		
		return $responseObject;
	}
	
	function errorLog($errorMessage, $priority = 6, $maxPriority = 1)
	{
		if ($priority <= $maxPriority)
		{
			error_log($errorMessage);	
		}
	}
	
	function destroySession()
	{
		errorLog("destroySession");
		
		session_name("profitstance");
		session_start();
		
		// Unset all of the session variables.
		$_SESSION = array();
		
		// If it's desired to kill the session, also delete the session cookie.
		// Note: This will destroy the session, and not just the session data!
		if (ini_get("session.use_cookies")) {
		    $params = session_get_cookie_params();
		    setcookie(session_name(), '', time() - 42000,
		        $params["path"], $params["domain"],
		        $params["secure"], $params["httponly"]
		    );
		}
		
		// Finally, destroy the session.
		session_destroy();
		
		echo -1;
	}
	
	function doesStringContainSubstring($string, $substring, $caseInsensitive = true)
	{
		$returnValue														= 0;
		
		if ($caseInsensitive)
		{
			$string															= strtolower($string);
			$substring														= strtolower($substring);	
		}
			
		if (strpos($string, $substring) !== false) 
		{
			$returnValue													= 1;
		}
		
		return $returnValue;
	}
	
	function generateObfuscatedString($sourceString) 
	{
		$fillLength				= strlen($sourceString) - 2;
		$displayLength			= $fillLength - 2;
		
    	$characters 			= '*';
		$charactersLength 		= strlen($characters);
		$obfuscatedString		= '';
		
		for ($i = 0; $i < $fillLength; $i++) {
        	$obfuscatedString .= $characters[rand(0, $charactersLength - 1)];
    	}
    	
    	$obfuscatedString		= $obfuscatedString.substr($sourceString, $displayLength);
    	
		return $obfuscatedString;
	}
	
	function generateRandomString($length = 10) 
	{
   	 	$characters 															= '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
		$charactersLength 													= strlen($characters);
		$randomString 														= '';
		
		for ($i = 0; $i < $length; $i++) {
        		$randomString .= $characters[rand(0, $charactersLength - 1)];
    		}
    		
		return $randomString;
	}
	
	function getBooleanNumericValue($booleanValue)
	{
		if ($booleanValue == true)
		{
			return 1;
		}
		else if ($booleanValue == false)
		{
			return 0;
		}
	}
	
	function getDateOnly($dateTime)
	{
		$returnValue = "";
		
		if (strlen($dateTime) > 10)
		{
			$returnValue 			= substr($dateTime, 0, 10);	
		}
		
		selectiveErrorLog($returnValue);
		
		return $returnValue;
	}
	
	function getFormattedDateTime($dateString)
	{
		// 2017-09-27T05:00:00Z
		
		$date	= substr($dateString, 0, 10);
		$time	= substr($dateString, 11, 8);
		
		return "$date $time";
	}
	
	function getFormattedPhoneNumber($sourceNumber)
	{
		$returnValue	= "";
		
		if (strlen($sourceNumber) > 9)
		{
			$returnValue = '('.substr($sourceNumber, 0, 3).') '.substr($sourceNumber, 3, 3).'-'.substr($sourceNumber,6);
		}
		
		return $returnValue;
	}
	
	function getUSDFormattedCurrencyAmount($amount)
	{
		return money_format('%=*(.2n', $amount);
	}
	
	function isEmailAddress($testString)
    {
	    $returnIsEmailAddress = 0;
	    
	    if (strpos($testString, '@') !== false) {
			$returnIsEmailAddress = 1;
		}
		else
		{
			$v = "/[a-zA-Z0-9-.+]+@[a-zA-Z0-9-]+.[a-zA-Z]+/";

			if ((bool)preg_match($v, $testString)) {
				$returnIsEmailAddress = 1;	
			}
		}
		
		return $returnIsEmailAddress;
    }
    
    function isLoggedInUser($tfaMode = 0)
	{
		// $tfaMode == 1 means that the user's account requires TFA, but is pending TFA verification on login
		
		$returnValue														= 0;
		
		if (isset($_SESSION['loggedInUserID']))
		{
			if (empty($_SESSION['loggedInUserID']))
			{
				destroySession();
			}
			else
			{
				$returnValue												= $_SESSION['loggedInUserID'];
				
				errorLog("user $returnValue is logged in");
				
				if (isset($_SESSION['requireTFA']))
				{
					errorLog("requireTFA is set");

					if ($_SESSION['requireTFA'] == 1)
					{
						errorLog("requireTFA == 1");
						
						if (isset($_SESSION['tfaAuthenticated']))
						{
							errorLog("tfaAuthenticated is set");
						
							if ($_SESSION['tfaAuthenticated'] == 0)
							{
								errorLog("tfaAuthenticated = 0");
								
								if ($tfaMode == 0) 
								{
									destroySession();	
								}
								else if ($tfaMode == 1) 
								{
									errorLog("user $returnValue pending TFA validation");	
								}
							}
							else if ($_SESSION['tfaAuthenticated'] == 1)
							{
								errorLog("tfaAuthenticated = 1");	
							}
						}
					}
				}	
			}	
		}
		
		return $returnValue;
	}
	
	function isXPubAddress($address)
	{
		$returnValue									= false;
		
		if (!empty($address) && (strlen($address) == 78 || strlen($address) == 111) && startsWith ($address, "0x"))
		{
			$returnValue								= true;
		}	
		
		return $returnValue;
	}
	
	function printResults($res)
	{
		if (is_array($res))
		{
			errorLog(print_r($res));	
		}
		else
		{
			errorLog($res);
		}
	}
    
    function reformatDate($dateValue)
	{
		$returnValue	= null;
	
		if (!is_null($dateValue) && strlen($dateValue) > 0 && $dateValue != 0 && strcasecmp($dateValue, "0") != 0)
		{
			$month 										= strtok($dateValue, "/");
			$day										= strtok("/");
			$year										= strtok("/");
			
			if (strlen($month) == 1 && $month < 10)
			{
				$month									= "0$month";
			}
			
			if (strlen($day) == 1 && $day < 10)
			{
				$day									= "0$day";
			}

			$returnValue								= $year."-".$month."-".$day;	
		}

		return $returnValue;
	}
	
	function selectiveErrorLog($errorMessage)
	{
		$showDebug				= 1;
		
		if (isset($_SESSION['showDebug']))
		{
			$showDebug			= $_SESSION['showDebug'];	
		}

		if ($showDebug == 1)
		{
			error_log($_SESSION['serverRoot']." ".$errorMessage);
		}
	}
	
	function convertIntVersionOfDecimal($sourceValue)
	{
		$returnValue 														= 0;
		
		if (!empty($sourceValue))
		{
			$returnValue														= $sourceValue / 100000000;
		}
		
		return $returnValue;
	}
	
	function setWalletAssetsAndOwnership($assetTypeID, $assetTypeName, $cryptoWalletIDValue, $isDebit, $nativeCurrencyTypeID, $nativeCurrencyTypeName, $fromAddressHashIDValue, $fromAddressResourcePath, $toAddressHashIDValue, $toAddressResourcePath, $transactionTypeID)
	{
		$responseObject												= array();
		
		
		$fromAddressTransactionSourceID								= 16;
		$fromAddressTransactionSourceName							= "Unknown";
								
		$toAddressTransactionSourceID								= 16;
		$toAddressTransactionSourceName								= "Unknown";
		
		$fromAddressCryptoWalletIDValue								= "";
		$toAddressCryptoWalletIDValue								= "";
		
		$fromWalletBelongsToAccountID								= false;
		$toWalletBelongsToAccountID									= false;
		
		if (empty($toAddressHashIDValue))
		{
			$toAddressHashIDValue									= "";
		}
		
		if (empty($toAddressResourcePath))
		{
			$toAddressResourcePath									= "";
		}
		
		if (empty($fromAddressHashIDValue))
		{
			$fromAddressHashIDValue									= "";
		}
		
		if (empty($fromAddressResourcePath))
		{
			$fromAddressResourcePath								= "";
		}
		
		if ($isDebit)
		{
			$fromAddressCryptoWalletIDValue							= $cryptoWalletIDValue;
			
			if (empty($fromAddressCurrencyTypeID))
			{
				$fromAddressCurrencyTypeID							= $assetTypeID;
			}
			
			if (empty($fromAddressCurrencyTypeLabel))
			{
				if (empty($assetTypeName))
				{
					$fromAddressCurrencyTypeLabel					= getAssetTypeLabelFromEnumValue($fromAddressCurrencyTypeID, $dbh);		
				}
				else
				{
					$fromAddressCurrencyTypeLabel					= $assetTypeName;
				}
			}
			
			if (empty($toAddressCurrencyTypeID))
			{
				if (empty($nativeCurrencyTypeID))
				{
					$toAddressCurrencyTypeID						= 2; // USD is the default currency for Coinbase	
				}
				else
				{
					$toAddressCurrencyTypeID						= $nativeCurrencyTypeID;
				}
			}
			
			if (empty($toAddressCurrencyTypeLabel))
			{
				if (empty($nativeCurrencyTypeName))
				{
					$toAddressCurrencyTypeLabel						= "USD"; // USD is the default currency for Coinbase	
				}
				else
				{
					$toAddressCurrencyTypeLabel						= $nativeCurrencyTypeName;
				}	
			}
			
			$fromAddressTransactionSourceID							= 2;
			$fromAddressTransactionSourceName						= "Coinbase";
			
			$toAddressTransactionSourceID							= 16;
			$toAddressTransactionSourceName							= "Unknown";
			
			$fromWalletBelongsToAccountID							= true;
			$toWalletBelongsToAccountID								= false;
		}
		else
		{
			$toAddressCryptoWalletIDValue							= $cryptoWalletIDValue;
			
			if (empty($toAddressCurrencyTypeID))
			{
				$toAddressCurrencyTypeID							= $assetTypeID;
			}
			
			if (empty($toAddressCurrencyTypeLabel))
			{
				if (empty($assetTypeName))
				{
					$toAddressCurrencyTypeLabel						= getAssetTypeLabelFromEnumValue($toAddressCurrencyTypeID, $dbh);		
				}
				else
				{
					$toAddressCurrencyTypeLabel						= $assetTypeName;
				}
			}
			
			if (empty($fromAddressCurrencyTypeID))
			{
				if (empty($nativeCurrencyTypeID))
				{
					$fromAddressCurrencyTypeID						= 2; // USD is the default currency for Coinbase	
				}
				else
				{
					$fromAddressCurrencyTypeID						= $nativeCurrencyTypeID;
				}
			}
			
			if (empty($fromAddressCurrencyTypeLabel))
			{
				if (empty($nativeCurrencyTypeName))
				{
					$fromAddressCurrencyTypeLabel					= "USD"; // USD is the default currency for Coinbase	
				}
				else
				{
					$fromAddressCurrencyTypeLabel					= $nativeCurrencyTypeName;
				}	
			}
			
			$fromAddressTransactionSourceID							= 16;
			$fromAddressTransactionSourceName						= "Unknown";
			
			$toAddressTransactionSourceID							= 2;
			$toAddressTransactionSourceName							= "Coinbase";
			
			$fromWalletBelongsToAccountID							= false;
			$toWalletBelongsToAccountID								= true;
		}
		
		if ($transactionTypeID == 11)
		{
			$fromAddressTransactionSourceID							= 2;
			$fromAddressTransactionSourceName						= "Coinbase";
			
			$toAddressTransactionSourceID							= 2;
			$toAddressTransactionSourceName							= "Coinbase";
		}
		
		if ($transactionTypeID > 5)
		{
			$fromWalletBelongsToAccountID							= true;
			$toWalletBelongsToAccountID								= true;	
		}
		
		$responseObject['fromAddressTransactionSourceID']			= $fromAddressTransactionSourceID;
		$responseObject['fromAddressTransactionSourceName']			= $fromAddressTransactionSourceName;
		$responseObject['toAddressTransactionSourceID']				= $toAddressTransactionSourceID;
		$responseObject['toAddressTransactionSourceName']			= $toAddressTransactionSourceName;
		
		$responseObject['fromAddressCurrencyTypeID']				= $fromAddressCurrencyTypeID;
		$responseObject['fromAddressCurrencyTypeLabel']				= $fromAddressCurrencyTypeLabel;
		$responseObject['toAddressCurrencyTypeID']					= $toAddressCurrencyTypeID;
		$responseObject['toAddressCurrencyTypeLabel']				= $toAddressCurrencyTypeLabel;
		
		$responseObject['fromAddressCryptoWalletIDValue']			= $fromAddressCryptoWalletIDValue;
		$responseObject['toAddressCryptoWalletIDValue']				= $toAddressCryptoWalletIDValue;
		
		$responseObject['fromWalletBelongsToAccountID']				= $fromWalletBelongsToAccountID;
		$responseObject['toWalletBelongsToAccountID']				= $toWalletBelongsToAccountID;
		
		$responseObject['toAddressHashIDValue']						= $toAddressHashIDValue;
		$responseObject['toAddressResourcePath']					= $toAddressResourcePath;
		$responseObject['fromAddressHashIDValue']					= $fromAddressHashIDValue;
		$responseObject['fromAddressResourcePath']					= $fromAddressResourcePath;
		
		return $responseObject;	
	}
	
	function splitCommaSeparatedString($sourceString)
	{
		$responseObject = explode(',', $sourceString);
		return $responseObject;	
	}
	 
	function startsWith($string, $startString) 
	{ 
    	// Function to check string starting 
		// with given substring
    	
    	// from https://www.geeksforgeeks.org/php-startswith-and-endswith-functions/
    	
    	$len 											= strlen($startString); 
		return (substr($string, 0, $len) === $startString); 
	}

	function writeCoinGeckoAssetTypes($id, $symbol, $name, $dbh)
	{
		try
		{		
			$insertCoinGeckoAssetType										= $dbh -> prepare("INSERT INTO CoinGeckoAssets
(
	id,
	symbol,
	`name`
)
VALUES
(
	:id,
	:symbol,
	:name
)");

			$insertCoinGeckoAssetType -> bindValue(':id', $id);
			$insertCoinGeckoAssetType -> bindValue(':symbol', $symbol);
			$insertCoinGeckoAssetType -> bindValue(':name', $name);
				
			$insertCoinGeckoAssetType -> execute();
			
			errorLog("wrote $id, $symbol, $name");	
		}
	    catch (PDOException $e) 
	    {
	    	$returnValue 													= -1;	
			
			errorLog($e -> getMessage());
	
			die();
		}
	}
	
	function writeFormDebug($dbh, $pageName, $liu, $sid, $submitDate, $object=null ) 
	{
		ob_start();                    // start buffer capture
		var_dump( $object );           // dump the values
		$contents = ob_get_contents(); // put the buffer into a variable
		ob_end_clean();                // end capture
		errorLog( $contents );        // log contents of the result of 		var_dump( $object )
		
		try
		{		
			$insertDebugInfo		= $dbh -> prepare("INSERT INTO DebugFormText
(
	pageName,
	FK_LoggedInUser,
	sid,
	submitDate,
	encryptedPHPDumpText
)
VALUES
(
	:pageName,
	:FK_LoggedInUser,
	:sid,
	:submitDate,
	AES_ENCRYPT(:phpDumpText, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512)))
)");

			if (!is_null($object))
			{
				$insertDebugInfo -> bindValue(':phpDumpText', $contents);
				$insertDebugInfo -> bindValue(':pageName', $pageName);
				$insertDebugInfo -> bindValue(':FK_LoggedInUser', $liu);
				$insertDebugInfo -> bindValue(':sid', $sid);
				$insertDebugInfo -> bindValue(':submitDate', $submitDate);
				
				$insertDebugInfo -> execute();	
			}
		}
	    catch (PDOException $e) 
	    {
	    	$returnValue 				= -1;	
			
			errorLog($e -> getMessage());
	
			die();
		}
	}
	
	// END UTILITY FUNCTIONS
	
	// NON-ENUM CONSTANTS
	
	function getModeID($mode)
	{
		$returnValue		= -1;
		
		if (strcasecmp($mode, "development") == 0)
		{
			$returnValue	= 1;		
		}
		else if (strcasecmp($mode, "production") == 0)
		{
			$returnValue	= 2;		
		}
		
		return $returnValue;
	}
	
	// END NON-ENUM CONSTANTS
	
	// CLEAN INPUTS
	
    function cleanArrayInput($value)
	{
		$returnValue    = 0;
		
		$tempValue = strip_tags( trim( $value ) );    
		
		if ( is_numeric( $tempValue ) )
		{
			$returnValue    = $tempValue;   
		}
		
		return $returnValue;
    }
    
    function cleanArrayTextInput($value)
	{
    	$returnValue    = "";

		$tempValue = strip_tags( trim( $value ) );    

	   	$returnValue    = $tempValue;   
        
        return $returnValue;
    }
	
	function cleanBooleanInput($value)
	{
    	$returnValue    		= "";
    	
    	if (is_numeric($value))
    	{
	    	if ($value == 1)
	    	{
		    	$returnValue	= 1;
		    }
		    else
		    {
				$returnValue	= 0;    
		    }
    	}
    	else
    	{
	    	$tempValue			= strip_tags( trim( $value ) );
	    	
	    	if (strcasecmp($tempValue, "true") == 0)
	    	{
		    	$returnValue    = 1; 	
	    	}    
			else
		    {
				$returnValue	= 0;    
		    }
    	}

        return $returnValue;
    }
    
    function cleanBooleanRequestInput($name)
	{
    	$returnValue    		= "";
    	$value					= 0;
    	
    	if ( isset( $_REQUEST[ $name ] ) )
		{
       		$value = $_REQUEST[ $name ];
       		
       		selectiveErrorLog("$name: $value");
        }
    	
    	if (is_numeric($value))
    	{
	    	if ($value == 1)
	    	{
		    	$returnValue	= 1;
		    }
		    else
		    {
				$returnValue	= 0;    
		    }
    	}
    	else
    	{
	    	$tempValue			= strip_tags( trim( $value ) );
	    	
	    	if (strcasecmp($tempValue, "true") == 0)
	    	{
		    	$returnValue    = 1; 	
	    	} 
	    	else if (strcasecmp($tempValue, "on") == 0)
	    	{
		    	$returnValue    = 1; 	
	    	}   
	    	else if (strcasecmp($tempValue, "1") == 0)
	    	{
		    	$returnValue    = 1; 	
	    	} 
			else
		    {
				$returnValue	= 0;    
		    }
    	}

        return $returnValue;
    }
    
    function cleanInput($name)
	{
    	$returnValue = 0;

		if ( isset( $_REQUEST[ $name ] ) )
		{
       		$tempValue = strip_tags( trim( $_REQUEST[ $name ] ) );    

	   		if ( is_numeric( $tempValue ) )
            {
                $returnValue    = $tempValue;   
            }
            else if (strcasecmp($tempValue, "A") == 0)
            {
	            $returnValue 	= 10;
            }
            else if (strcasecmp($tempValue, "B") == 0)
            {
	            $returnValue 	= 11;
            }
            else if (strcasecmp($tempValue, "C") == 0)
            {
	            $returnValue 	= 12;
            }
            else if (strcasecmp($tempValue, "D") == 0)
            {
	            $returnValue 	= 13;
            }
            else if (strcasecmp($tempValue, "E") == 0)
            {
	            $returnValue 	= 14;
            }
        }
        
        return $returnValue;
    }
    
    function cleanPasswordTextInput($name)
	{
    	$returnValue    = "";

		if ( isset( $_REQUEST[ $name ] ) )
		{
       		$tempValue = strip_tags( $_REQUEST[ $name ] );    

	   		$returnValue    = $tempValue;   
        }
        
        return $returnValue;
    }
      
    function cleanTextInput($name)
	{
    	$returnValue    = "";

		if ( isset( $_REQUEST[ $name ] ) )
		{
       		$tempValue = strip_tags( trim( $_REQUEST[ $name ] ) );    

	   		$returnValue    = $tempValue;   
        }
        
        return $returnValue;
    } 
	
	// END CLEAN INPUTS
    
    // JSON PARSE
    
	function cleanCryptoIDReturnObject($jsonObject, $cryptoIDAddressReportRecordID, $cryptoIDAddressReportRecord, $accountID, $exchangeTileID, $addressValue, $asset, $assetTypeID, $cryptoIDAssetAbbreviation, $dataImportEventRecordID, $transactionSourceID, $transactionSourceName, $walletTypeID, $walletTypeName, $numberRequested, $numTransactions, $dataPullDate, $dataPullTimestamp, $providerAccountWallet, $providerAccountWalletID, $userEncryptionKey, $globalCurrentDate, $sid, $dbh)
	{
		$returnValue    													= array();
	
		try
		{
			$numberDownloaded												= 0;	
			$numberProcessed												= 0;
			$isComplete														= false;	
			
			$addresses														= "addresses";
			$transactions													= "txs";
		
			errorLog($jsonObject -> $addresses);
		
			if (!empty($jsonObject -> $addresses))
			{
				$jsonMDArray 												= $jsonObject -> $addresses; 
			
				errorLog(json_encode($jsonMDArray));
			
				foreach ($jsonMDArray as $arrayIndex => $genericAddressObject) 
				{
					errorLog($arrayIndex);
				
					$totalSend												= urldecode( strip_tags( trim( $genericAddressObject -> total_sent ) ) ); 
					
					$totalReceived											= urldecode( strip_tags( trim( $genericAddressObject -> total_received ) ) );
					
					$finalBalance											= urldecode( strip_tags( trim( $genericAddressObject -> final_balance ) ) );
					
					$numTransactions										= urldecode( strip_tags( trim( $genericAddressObject -> n_tx ) ) );																		
					$totalSend												= convertIntVersionOfDecimal($totalSend);
					$totalReceived											= convertIntVersionOfDecimal($totalReceived);
					$finalBalance											= convertIntVersionOfDecimal($finalBalance);
					
				
					$cryptoIDAddressReportRecord							= new CryptoIDAddressReportRecord();
					
					$cryptoIDAddressReportRecord -> setData($accountID, $exchangeTileID, $dataImportEventRecordID, $addressValue, $asset, $assetTypeID, $cryptoIDAssetAbbreviation, $transactionSourceID, $transactionSourceName, $dataPullDate, $dataPullTimestamp, $providerAccountWallet, $providerAccountWalletID, $numTransactions, $totalSend, $totalReceived, $finalBalance);
					
					$cryptoIDAddressReportRecord -> updateAddressReportRecord($accountID, $cryptoIDAddressReportRecordID, $userEncryptionKey, $dbh);	
				}
			}
			else
			{
				errorLog("ERROR: JSON object empty or $addresses not found");	
			}
		
			$transactionStatusID											= 1;
			$transactionStatusLabel											= "completed";
			
			$baseCurrencyWallet												= new CompleteCryptoWallet(); 
			$quoteCurrencyWallet											= new CompleteCryptoWallet();  
			
			$baseCurrencyResponseObject										= $baseCurrencyWallet -> instantiateWalletUsingCryptoWalletAttributes($accountID, $assetTypeID, $addressValue, $transactionSourceID, $userEncryptionKey, $dbh);
		
			if ($baseCurrencyResponseObject['instantiatedRecord'] == false)
			{
				// create new wallet, get ID
				$baseCurrencyWallet -> setData($accountID, $globalCurrentDate, $addressValue, $addressValue, $addressValue, $assetTypeID, $asset, $accountID, "", $addressValue, "", false, "https://chainz.cryptoid.info/$cryptoIDAssetAbbreviation/api.dws?q=multiaddr&active=$addressValue&key=keyvalue&n=1000", 5, "address", $transactionSourceID, $transactionSourceName, 1, $accountID, $walletTypeID, $walletTypeName, $sid, $globalCurrentDate);
			
				$baseCurrencyResponseObject									= $baseCurrencyWallet -> writeToDatabase($accountID, $userEncryptionKey, $globalCurrentDate, $sid, $dbh);
			
				if ($baseCurrencyResponseObject['wroteToDatabase'] == true)
				{
					$baseCurrencyWalletID									= $baseCurrencyWallet -> getWalletID();    
				}
			}
			else
			{
				$baseCurrencyWalletID										= $baseCurrencyWallet -> getWalletID();    
			}
		
			$quoteCurrencyResponseObject									= $quoteCurrencyWallet -> instantiateWalletUsingCryptoWalletAttributes($accountID, 2, $addressValue, $transactionSourceID, $userEncryptionKey, $dbh);
		
			if ($quoteCurrencyResponseObject['instantiatedRecord'] == false)
			{
				// create new wallet, get ID
				$quoteCurrencyWallet -> setData($accountID, $globalCurrentDate, $addressValue, $addressValue, $addressValue, 2, "USD", $accountID, "", $addressValue, "", true, "https://chainz.cryptoid.info/$cryptoIDAssetAbbreviation/api.dws?q=multiaddr&active=$addressValue&key=keyvalue&n=1000", 5, "address", $transactionSourceID, $transactionSourceName, 4, $accountID, $walletTypeID, $walletTypeName, $sid, $globalCurrentDate);
			
				$quoteCurrencyResponseObject								= $quoteCurrencyWallet -> writeToDatabase($accountID, $userEncryptionKey, $globalCurrentDate, $sid, $dbh);
			
				if ($quoteCurrencyResponseObject['wroteToDatabase'] == true)
				{
					$quoteCurrencyWalletID									= $quoteCurrencyWallet -> getWalletID();    
				}
			}
			else
			{
				$quoteCurrencyWalletID										= $quoteCurrencyWallet -> getWalletID();    
			}
		
			if (!empty($jsonObject -> $transactions))
			{
				$jsonMDArray 												= $jsonObject -> $transactions; 
			
				errorLog(json_encode($jsonMDArray));
			
				if ($numTransactions > 0)
				{
					createDataImportAssetStatusRecord($accountID, $userEncryptionKey, $dataImportEventRecordID, $assetTypeID, 2, $globalCurrentDate, $sid, $dbh);
				}
			
				foreach ($jsonMDArray as $arrayIndex => $genericTransactionObject) 
				{
					$numberDownloaded++;
				
					errorLog($arrayIndex);
				
					$hashValue												= urldecode( strip_tags( trim( $genericTransactionObject -> hash ) ) ); 
				
					$numConfirmations										= urldecode( strip_tags( trim( $genericTransactionObject -> confirmations ) ) );
				
					$change													= urldecode( strip_tags( trim( $genericTransactionObject -> change ) ) );
				
					$time_utc												= urldecode( strip_tags( trim( $genericTransactionObject -> time_utc ) ) );	
				
				
					$n														= 0;	
					/*
					
					
					try
					{
						$n													= 0; urldecode( strip_tags( trim( $genericTransactionObject -> n ) ) );
					}
					catch (Exception $e)
					{
						errorLog("n is not set for transaction $hashValue");	
					}
					*/
					$transactionDate										= new DateTime($time_utc);
					$transactionTimestamp									= $transactionDate -> getTimestamp();
					
					$changeAmount											= convertIntVersionOfDecimal($change);
					
					$profitstanceTransactionIDValue							= $addressValue.$hashValue.$changeAmount."-".$transactionTimestamp;												
					
					$globalTransactionRecordID								= 0;
				
					// $globalTransactionIDTestResults						= array();
					// $globalTransactionIDTestResults['foundGlobalTransactionIDForAccount'] = false;
				
					$globalTransactionIDTestResults							= getGlobalTransactionIdentificationRecordIDUsingProfitStanceID($accountID, $assetTypeID, $dataImportEventRecordID, $profitstanceTransactionIDValue, $transactionSourceID, $globalCurrentDate, $sid, $dbh);
				
					if ($globalTransactionIDTestResults['foundGlobalTransactionIDForAccount'] == false)
					{
						$globalTransactionCreationResults					= createGlobalTransactionIdentificationRecordWithProfitStanceTransactionIDValue($accountID, $exchangeTileID, $assetTypeID, $dataImportEventRecordID, $hashValue, $profitstanceTransactionIDValue, $transactionSourceID, $globalCurrentDate, $sid, $dbh);
					
						if ($globalTransactionCreationResults['createdGlobalTransactionIdentificationRecord'] == true)
						{
							$globalTransactionRecordID						= $globalTransactionCreationResults['globalTransactionIdentificationRecordID'];
						
							$btcSpotPriceAtTimeOfTransaction				= 0;
						
							$cascadeRetrieveSpotPriceResponseObject			= getSpotPriceForAssetPairUsingSourceCascade(1, 2, $transactionDate, 2, "Coinbase by date", $dbh);
						
							if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
							{
								$btcSpotPriceAtTimeOfTransaction			= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
							}
						
							$spotPriceExpressedInUSD						= 0;
						
							$cascadeRetrieveSpotPriceResponseObject			= getSpotPriceForAssetPairUsingSourceCascade($assetTypeID, 2, $transactionDate, 14, "CoinGecko by date", $dbh);
						
							// here - add crypto ID related spot price sources to cascade, and make that the primary for these coins
							if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
							{
								$spotPriceExpressedInUSD					= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
							}
					
							$isDebit										= false;
					
							$transactionTypeLabel							= "Buy";
							$transactionTypeID								= 1;
							
							if ($changeAmount < 0)
							{
								$isDebit									= true;
								$transactionTypeLabel						= "Sell";
								$transactionTypeID							= 4;
							}
					
							$cryptoIDTransactionRecords						= new CryptoIDTransactionRecords();
					
							$cryptoIDTransactionRecords -> setData(0, $cryptoIDAddressReportRecordID, $dataImportEventRecordID, $accountID, $exchangeTileID, $globalTransactionRecordID, $providerAccountWallet, $providerAccountWalletID, $asset, $assetTypeID, $cryptoIDAssetAbbreviation, $hashValue, $numConfirmations, $changeAmount, $transactionDate, $globalCurrentDate, $spotPriceExpressedInUSD, $btcSpotPriceAtTimeOfTransaction, $isDebit, $transactionSourceID, $transactionSourceName, $transactionTypeID, $transactionTypeLabel, $transactionStatusID, $transactionStatusLabel, $n, $baseCurrencyWallet, $baseCurrencyWalletID, $quoteCurrencyWallet, $quoteCurrencyWalletID);
					
							$createTransactionRecordResponseObject			= $cryptoIDTransactionRecords -> writeToDatabase($userEncryptionKey, $dbh);
					
							if ($createTransactionRecordResponseObject['wroteToDatabase'] == true)
							{
								$numberProcessed++;
							
								$profitStanceLedgerEntry					= new ProfitStanceLedgerEntry();
								
								$profitStanceLedgerEntry -> setData($accountID, $assetTypeID, $asset, $transactionSourceID, $transactionSourceName, $exchangeTileID, $globalTransactionRecordID, $cryptoIDTransactionRecords -> getTransactionDate(), $changeAmount, $dbh);
							
								$writeProfitStanceLedgerEntryResponseObject	= $profitStanceLedgerEntry -> writeToDatabase($dbh);
							
								if ($writeProfitStanceLedgerEntryResponseObject['wroteToDatabase'] == true)
								{
									errorLog("wrote profitStance ledger entry $accountID, $assetTypeID, $asset, $transactionSourceID, $transactionSourceName, $globalTransactionRecordID, $globalCurrentDate, $changeAmount to the database.", $GLOBALS['debugCoreFunctionality']);
								}
								else
								{
									errorLog("could not write profitStance ledger entry $accountID, $assetTypeID, $asset, $transactionSourceID, $transactionSourceName, $globalTransactionRecordID, $globalCurrentDate, $changeAmount to the database.", $GLOBALS['criticalErrors']);	
								}
							
								$cryptoIDTransactionRecordID				= $cryptoIDTransactionRecords -> getCryptoIDTransactionID(); 
							
								$setNativeTransactionRecordIDResult		 	= setNativeTransactionRecordIDForGlobalTransactionIndentificationRecordID($accountID, $cryptoIDTransactionRecordID, $globalTransactionRecordID, $globalCurrentDate, $sid, $dbh);	
							}
							else
							{
								errorLog($cryptoIDTransactionRecords -> getCreateStatement());	
							}	
						}
					}						
				}
				
				$cryptoIDAddressReportRecord -> updateAddressReportRecordNumDownloadedAndIsComplete($accountID, $cryptoIDAddressReportRecordID, $numberDownloaded, $numberProcessed, $userEncryptionKey, $dbh);
			}
			else
			{
				errorLog("ERROR: JSON object empty or $addresses not found");	
			}	
		}
		catch (Exception $e)
		{
			errorLog("ERROR: Could not parse JSON object");	
		}
		
		return $returnValue;
	}
    
    function cleanIPClientAccountTransaction($jsonObject, $name, $accountID, $userEncryptionKey, $providerWalletID, $walletTypeID, $globalCurrentDate, $sid, $dbh)
	{
    	$returnValue    = array();
    	
    	try
    	{
	    	if (!empty($jsonObject -> $name))
	    	{
		    	$jsonMDArray 	= $jsonObject -> $name; 
		    	
		    	foreach ($jsonMDArray as $arrayIndex => $genericTransationObject) 
		    	{
			    	errorLog($arrayIndex);
			    	
			    	$ipClientAccountTransaction											= new IPClientAccountTransaction();
			    	
			    	$nativeTransactionIDValue											= urldecode( strip_tags( trim( $genericTransationObject -> nativeTransactionIDValue ) ) );
			    	$transactionType													= urldecode( strip_tags( trim( $genericTransationObject -> transactionType ) ) );
				    $transactionStatus													= urldecode( strip_tags( trim( $genericTransationObject -> transactionStatus ) ) );
				    $transactionTimestamp												= urldecode( strip_tags( trim( $genericTransationObject -> transactionTimestamp ) ) );
				    $assetType															= urldecode( strip_tags( trim( $genericTransationObject -> assetType ) ) );
				    $nativeCurrency														= urldecode( strip_tags( trim( $genericTransationObject -> nativeCurrency ) ) );
				    $resourcePath														= urldecode( strip_tags( trim( $genericTransationObject -> resourcePath ) ) );
					$numberOfConfirmations												= urldecode( strip_tags( trim( $genericTransationObject -> numberOfConfirmations ) ) );
					$providerNotes														= urldecode( strip_tags( trim( $genericTransationObject -> providerNotes ) ) );
					
					$transactionTypeID 													= getEnumValueTransactionType($transactionType, $dbh);
					$transactionStatusID 												= getEnumValueTransactionStatus($transactionStatus, $dbh);
					$assetTypeID 														= getEnumValueAssetType($assetType, $dbh);
					$nativeCurrencyID 													= getEnumValueAssetType($nativeCurrency, $dbh);
					
					$globalTransactionIDTestResults										= getGlobalTransactionIdentificationRecordID($accountID, $assetTypeID, $nativeTransactionIDValue, 1, $globalCurrentDate, $sid, $dbh);
						
					if ($globalTransactionIDTestResults['foundNativeTransactionForAccount'] == false)
					{
						$returnValue[$nativeTransactionIDValue]["existingRecordFound"]	= false;
						
						$globalTransactionCreationResults								= createGlobalTransactionIdentificationRecord($accountID, $assetTypeID, $nativeTransactionIDValue, 1, $globalCurrentDate, $sid, $dbh);
			
						if ($globalTransactionCreationResults['createdGlobalTransactionIdentificationRecord'] == true)
						{
							$returnValue[$nativeTransactionIDValue]["createdGTIR"]		= true;
							
							$globalTransactionIdentifierRecordID						= $globalTransactionCreationResults['globalTransactionIdentificationRecordID'];
					
							$totalTransactionAmountGenericObject						= $genericTransationObject -> totalTransactionAmount;
							$transactionAmountWithoutFeesGenericObject					= $genericTransationObject -> transactionAmountWithoutFees;
							$feeAmountGenericObject										= $genericTransationObject -> feeAmount;
							$toAddressGenericObject										= $genericTransationObject -> toAddress;
							$fromAddressGenericObject									= $genericTransationObject -> fromAddress;
							$cryptoCurrencyPriceAtTimeOfTransactionGenericObject		= $genericTransationObject -> cryptoCurrencyPriceAtTimeOfTransaction;
							
							$totalTransactionAmount										= new IPTransactionAmountObject();
							
							$totalTransactionAmountCrypto 								= urldecode( strip_tags( trim( $totalTransactionAmountGenericObject -> amountInCryptoCurrency ) ) );
							$totalTransactionAmountNative 								= urldecode( strip_tags( trim( $totalTransactionAmountGenericObject -> amountInNativeCurrency ) ) );
							$totalTransactionAmountUSD 									= urldecode( strip_tags( trim( $totalTransactionAmountGenericObject -> amountInUSD ) ) );
							
							$totalTransactionAmount -> setData($accountID, $totalTransactionAmountCrypto, $totalTransactionAmountNative, $totalTransactionAmountUSD, $accountID, $globalCurrentDate, 0, 0, $sid, 1, "Total Transaction Amount");
							
							$transactionAmountWithoutFees								= new IPTransactionAmountObject();
							
							$totalTransactionAmountWFCrypto 							= urldecode( strip_tags( trim( $transactionAmountWithoutFeesGenericObject -> amountInCryptoCurrency ) ) );
							$totalTransactionAmountWFNative 							= urldecode( strip_tags( trim( $transactionAmountWithoutFeesGenericObject -> amountInNativeCurrency ) ) );
							$totalTransactionAmountWFUSD 								= urldecode( strip_tags( trim( $transactionAmountWithoutFeesGenericObject -> amountInUSD ) ) );
							
							$transactionAmountWithoutFees -> setData($accountID, $totalTransactionAmountWFCrypto, $totalTransactionAmountWFNative, $totalTransactionAmountWFUSD, $accountID, $globalCurrentDate, 0, 0, $sid, 2, "Transaction Amount Without Fees");
							
							$feeAmount													= new IPTransactionAmountObject();
							
							$feeAmountCrypto 											= urldecode( strip_tags( trim( $feeAmountGenericObject -> amountInCryptoCurrency ) ) );
							$feeAmountNative											= urldecode( strip_tags( trim( $feeAmountGenericObject -> amountInNativeCurrency ) ) );
							$feeAmountUSD 												= urldecode( strip_tags( trim( $feeAmountGenericObject -> amountInUSD ) ) );
							
							$feeAmount -> setData($accountID, $feeAmountCrypto, $feeAmountNative, $feeAmountUSD, $accountID, $globalCurrentDate, 0, 0, $sid, 3, "Fee Amount");
							
							$toAddress													= new IPTransactionAddressObject();
							
							$toAddressValue												= urldecode( strip_tags( trim( $toAddressGenericObject -> addressValue ) ) );
							$toAddressType												= urldecode( strip_tags( trim( $toAddressGenericObject -> addressType ) ) );
							$toAddressCallbackURL										= urldecode( strip_tags( trim( $toAddressGenericObject -> addressCallbackURL ) ) );
							$toAddressResourcePath										= urldecode( strip_tags( trim( $toAddressGenericObject -> addressResourcePath ) ) );
							$toAddressCurrencyType 										= urldecode( strip_tags( trim( $toAddressGenericObject -> addressCurrencyType ) ) );
							
							$toAddressTypeID											= getEnumValueResourceType($toAddressType, $dbh);
							$toAddressCurrencyTypeID									= getEnumValueAssetType($toAddressCurrencyType, $dbh);							
							
							$toAddress -> setData($toAddressCallbackURL, $toAddressCurrencyType, $toAddressCurrencyTypeID, $accountID, $toAddressResourcePath, $toAddressType, $toAddressTypeID, $toAddressValue, $accountID, $globalCurrentDate, 0, 0, $sid);
							
							errorLog("calling createTransactionAddressObject");
							
							$toAddress -> createTransactionAddressObject($dbh);
							
							$fromAddress												= new IPTransactionAddressObject();
							
							$fromAddressValue											= urldecode( strip_tags( trim( $fromAddressGenericObject -> addressValue ) ) );
							$fromAddressType											= urldecode( strip_tags( trim( $fromAddressGenericObject -> addressType ) ) );
							$fromAddressCallbackURL										= urldecode( strip_tags( trim( $fromAddressGenericObject -> addressCallbackURL ) ) );
							$fromAddressResourcePath									= urldecode( strip_tags( trim( $fromAddressGenericObject -> addressResourcePath ) ) );
							$fromAddressCurrencyType 									= urldecode( strip_tags( trim( $fromAddressGenericObject -> addressCurrencyType ) ) );
							
							$fromAddressTypeID											= getEnumValueResourceType($fromAddressType, $dbh);
							$fromAddressCurrencyTypeID									= getEnumValueAssetType($fromAddressCurrencyType, $dbh);							
							
							$fromAddress -> setData($fromAddressCallbackURL, $fromAddressCurrencyType, $fromAddressCurrencyTypeID, $accountID, $fromAddressResourcePath, $fromAddressType, $fromAddressTypeID, $fromAddressValue, $accountID, $globalCurrentDate, 0, 0, $sid);
							
							$fromAddress -> createTransactionAddressObject($dbh);
							
							$cryptoCurrencyPriceAtTimeOfTransaction						= new IPClientAccountCryptoCurrencyPriceObject();
							
							$spotPriceAtTimeOfTransaction								= urldecode( strip_tags( trim( $cryptoCurrencyPriceAtTimeOfTransactionGenericObject -> spotPriceUSD ) ) );
							$buyPriceAtTimeOfTransaction								= urldecode( strip_tags( trim( $cryptoCurrencyPriceAtTimeOfTransactionGenericObject -> buyPriceUSD ) ) );
							$sellPriceAtTimeOfTransaction								= urldecode( strip_tags( trim( $cryptoCurrencyPriceAtTimeOfTransactionGenericObject -> sellPriceUSD ) ) );
						    
							$cryptoCurrencyPriceAtTimeOfTransaction -> setData($spotPriceAtTimeOfTransaction, $buyPriceAtTimeOfTransaction, $sellPriceAtTimeOfTransaction);
							
							$transactionRecordID										= 0;
							
							$profitStanceTransactionIDValue								= createProfitStanceTransactionIDValue($accountID, $assetTypeID, $providerWalletID, $nativeTransactionIDValue, $globalCurrentDate, $sid); 
							
							$authorID												= $accountID;
							
							// add instantiation method by native ID - check to see if it exists alread - if not, set data with 0 as the record ID, and then commit
							// if so, compare values and look for differences then commit, or simply return that it already exists?
							
		
							$returnValue['transactions'][$nativeTransactionIDValue]['instantiation'] = $ipClientAccountTransaction -> instantiateByNativeTransactionIDValue($accountID, $userEncryptionKey, $nativeTransactionIDValue, $sid, $dbh);
			
							if ($returnValue['transactions'][$nativeTransactionIDValue]['instantiation']['ipTransactionFound'] == true)
							{
								// compare for update
								// @here
							}
							else
							{
								$ipClientAccountTransaction -> setData($accountID, $assetType, $assetTypeID, $authorID, $globalCurrentDate, $cryptoCurrencyPriceAtTimeOfTransaction, $feeAmount, $fromAddress, $globalCurrentDate, $nativeCurrency, $nativeCurrencyID, $nativeTransactionIDValue, $numberOfConfirmations, $profitStanceTransactionIDValue, $providerNotes, $providerWalletID, $resourcePath, $sid, $toAddress, $totalTransactionAmount, $transactionAmountWithoutFees, $transactionRecordID, $transactionStatus, $transactionStatusID, $transactionType, $transactionTypeID,  $transactionTimestamp, $walletTypeID, $globalTransactionIdentifierRecordID);
								
								$arrayTest																								= $ipClientAccountTransaction -> createTransactionRecord($userEncryptionKey, $dbh);
								
								foreach ($arrayTest AS $arrayKey => $arrayValue)
								{
									errorLog("arrayTestContent $arrayKey $arrayValue");
								}
								
								$returnValue['transactions'][$nativeTransactionIDValue]['creation'] 									= $arrayTest;
									
								if ($returnValue['transactions'][$nativeTransactionIDValue]['creation']['ipTransactionCreated'] == true)
								{
									$newTransactionID 																					= $ipClientAccountTransaction -> getTransactionRecordID();
									
									errorLog("created transaction $newTransactionID");
									
									$setNativeTransactionRecordIDResult																	= setNativeTransactionRecordIDForGlobalTransactionIndentificationRecordID($accountID, $newTransactionID, $globalTransactionIdentifierRecordID, $globalCurrentDate, $sid, $dbh);
									
									// populate amount records
									
									$totalTransactionAmount -> setIPTransactionRecordID($newTransactionID);
									$returnValue['transactions'][$nativeTransactionIDValue]['transactionAmounts'][1]['creation'] 		= $totalTransactionAmount -> createTransactionAmountObject($dbh);
									$returnValue['transactions'][$nativeTransactionIDValue]['transactionAmounts'][1]['updateObjectID'] 	= $ipClientAccountTransaction -> updateTransactionAmountObjectIDForType($totalTransactionAmount -> getIPTransactionAmountObjectID(), 1, $globalCurrentDate, $dbh);
									
									$ipClientAccountTransaction -> setTotalTransactionAmount($totalTransactionAmount);
									
									$transactionAmountWithoutFees -> setIPTransactionRecordID($newTransactionID);
									$returnValue['transactions'][$nativeTransactionIDValue]['transactionAmounts'][2]['creation']		= $transactionAmountWithoutFees -> createTransactionAmountObject($dbh);
									$returnValue['transactions'][$nativeTransactionIDValue]['transactionAmounts'][2]['updateObjectID'] 	= $ipClientAccountTransaction -> updateTransactionAmountObjectIDForType($transactionAmountWithoutFees -> getIPTransactionAmountObjectID(), 2, $globalCurrentDate, $dbh);
									
									$ipClientAccountTransaction -> setTransactionAmountWithoutFees($transactionAmountWithoutFees);
									
									$feeAmount -> setIPTransactionRecordID($newTransactionID);
									$returnValue['transactions'][$nativeTransactionIDValue]['transactionAmounts'][3]['creation']		= $feeAmount -> createTransactionAmountObject($dbh);
									$returnValue['transactions'][$nativeTransactionIDValue]['transactionAmounts'][3]['updateObjectID'] 	= $ipClientAccountTransaction -> updateTransactionAmountObjectIDForType($feeAmount -> getIPTransactionAmountObjectID(), 3, $globalCurrentDate, $dbh);
									
									$ipClientAccountTransaction -> setFeeAmount($feeAmount);
									
									
									// populate address records
									
									$fromAddress -> setIpTransactionRecordID($newTransactionID);
									$returnValue['transactions'][$nativeTransactionIDValue]['transactionAddresses']['from']['creation']			= $fromAddress -> createTransactionAddressObject($dbh);
									$returnValue['transactions'][$nativeTransactionIDValue]['transactionAddresses']['from']['updateObjectID']	= $ipClientAccountTransaction -> updateTransactionAddressObjectIDForType($fromAddress -> getIpTransactionAddressObjectID(), 1, $globalCurrentDate, $dbh);
									
									$ipClientAccountTransaction -> setFromAddress($fromAddress);
									
									$toAddress -> setIpTransactionRecordID($newTransactionID);
									$returnValue['transactions'][$nativeTransactionIDValue]['transactionAddresses']['to']['creation']			= $toAddress -> createTransactionAddressObject($dbh);
									$returnValue['transactions'][$nativeTransactionIDValue]['transactionAddresses']['to']['updateObjectID']		= $ipClientAccountTransaction -> updateTransactionAddressObjectIDForType($toAddress -> getIpTransactionAddressObjectID(), 2, $globalCurrentDate, $dbh);
									
									$ipClientAccountTransaction -> setToAddress($toAddress);
								}	
							}
						}
					}
			    }
	    	}
	    	else
	    	{
		    	errorLog("ERROR: JSON object empty or $name not found");	
	    	}	
    	}
    	catch (Exception $e)
    	{
	    	errorLog("ERROR: $name not found in JSON object");	
    	}
        
        return $returnValue;
    }
    
    function cleanJSONArray($jsonObject, $name)
	{
    	$returnValue    													= array();
    	
	    try
	    {
		   	if (!empty($jsonObject -> $name))
		   	{
		    	$jsonArray 													= $jsonObject -> $name; 
			    	
			    foreach ($jsonArray as $jsonItem) 
				{
					$returnValue[] 											= urldecode( strip_tags( trim( $jsonItem ) ) ); 	
				}
		    }
		    else
		    {
			   	errorLog("ERROR: JSON object empty or $name not found");	
		    }	
	    }
	    catch (Exception $e)
	    {
		   	errorLog("ERROR: $name not found in JSON object");	
	    }
        
        return $returnValue;
    }
	
	function cleanJSONAssociativeArray($jsonObject, $name)
	{
    	$returnValue    													= array();
    	
	    try
	    {
		   	if (!empty($jsonObject -> $name))
		   	{
		    	$jsonArray 													= $jsonObject -> $name; 
			    	
			    foreach ($jsonArray as $elementName => $elementValue) 
				{
					$returnValue[urldecode(strip_tags(trim($elementName)))]	= urldecode(strip_tags(trim($elementValue))); 	
				}
		    }
		    else
		    {
			   	errorLog("ERROR: JSON object empty or $name not found");	
		    }	
	    }
	    catch (Exception $e)
	    {
		   	errorLog("ERROR: $name not found in JSON object");	
	    }
        
        return $returnValue;
    }
    
    function cleanJSONBoolean($jsonObject, $name)
	{
    	$returnValue    = "";
    	
    	try
    	{
	    	if (!empty($jsonObject -> $name))
	    	{
		    	if ($jsonObject -> $name === true)
		    	{
			    	$returnValue	= 1;	
		    	}
		    	else
		    	{
			    	$returnValue	= 0;	
		    	}
	    	}
	    	else
	    	{
		    	errorLog("ERROR: JSON object empty or $name not found");	
	    	}	
    	}
    	catch (Exception $e)
    	{
	    	errorLog("ERROR: $name not found in JSON object");	
    	}
        
        return $returnValue;
    }

	function cleanJSONDailyPriceData($jsonObject, $name, $priceDate, $globalCurrentDate, $sid, $dbh)
	{
    	$returnValue    																				= array();
    	
    	try
    	{
			$createDailyCryptoPriceRecord																= $dbh->prepare("INSERT IGNORE DailyCryptoSpotPrices
			(
				FK_CryptoAssetID,
				FK_FiatCurrencyAssetID,
				priceDate,
				fiatCurrencySpotPrice
			)
			VALUES
			(
				:FK_CryptoAssetID,
				:FK_FiatCurrencyAssetID,
				:priceDate,
				:fiatCurrencySpotPrice
			)");

			if (!empty($jsonObject -> $name))
	    	{
		    	$jsonMDArray 																			= $jsonObject -> $name; 
		    	
		    	$base																					= "";
		    	$currency																				= "";
		    	$amount																					= 0.00;
			    	
		    	foreach ($jsonMDArray as $key => $value) 
			    {
				    errorLog($key." ".$value);
				    	
			    	if (strcasecmp($key, "base") == 0)
					{
				    	$base																			= urldecode( strip_tags( trim( $value ) ) );
				    }
				    else if (strcasecmp($key, "currency") == 0)
				    {
					    $currency																		= urldecode( strip_tags( trim( $value ) ) );
				    }
				    else if (strcasecmp($key, "amount") == 0)
				    {
				    	$amount																			= urldecode( strip_tags( trim( $value ) ) );
				    }								 
			    }
		    
		    	$cryptoCurrencyAssetTypeID																= getEnumValueAssetType($base, $dbh);
	    		$fiatCurrencyAssetTypeID																= getEnumValueAssetType($currency, $dbh);
		    
				errorLog("base: $base enum cc ID: $cryptoCurrencyAssetTypeID currency: $currency enum fiat currency ID: $fiatCurrencyAssetTypeID amount $amount ");
		    
			    $createDailyCryptoPriceRecord -> bindValue(':FK_CryptoAssetID', $cryptoCurrencyAssetTypeID);
				$createDailyCryptoPriceRecord -> bindValue(':FK_FiatCurrencyAssetID', $fiatCurrencyAssetTypeID);
				$createDailyCryptoPriceRecord -> bindValue(':priceDate', $priceDate);
				$createDailyCryptoPriceRecord -> bindValue(':fiatCurrencySpotPrice', $amount);
				
				if ($createDailyCryptoPriceRecord -> execute())
				{
					errorLog("INSERT IGNORE DailyCryptoSpotPrices
	(
	FK_CryptoAssetID,
	FK_FiatCurrencyAssetID,
	priceDate,
	fiatCurrencySpotPrice
	)
	VALUES
	(
	$cryptoCurrencyAssetTypeID,
	$fiatCurrencyAssetTypeID,
	'$priceDate',
	$amount
	)");
					$returnValue																		= 1;
			
				}	
			
				$dbh 																					= null;	
	    	}
	    	else
	    	{
		    	errorLog("ERROR: JSON object empty or $name not found");	
	    	}
    	}
    	catch (Exception $e)
    	{
	    	errorLog("ERROR: $name not found in JSON object");	
    	}
        
        return $returnValue;
    }
	
	function cleanJSONDailyPriceDataForVenderSpecificAbbreviations($jsonObject, $name, $priceDate, $vendorID, $globalCurrentDate, $sid, $dbh)
	{
		$returnValue    																				= array();
	
    	try
    	{
			$createDailyCryptoPriceRecord																= $dbh->prepare("INSERT IGNORE DailyCryptoSpotPrices
			(
				FK_CryptoAssetID,
				FK_FiatCurrencyAssetID,
				priceDate,
				fiatCurrencySpotPrice
			)
			VALUES
			(
				:FK_CryptoAssetID,
				:FK_FiatCurrencyAssetID,
				:priceDate,
				:fiatCurrencySpotPrice
			)");

			if (!empty($jsonObject -> $name))
	    	{
		    	$jsonMDArray 																			= $jsonObject -> $name; 
		    	
		    	$base																					= "";
		    	$currency																				= "";
		    	$amount																					= 0.00;
		    	
		    	errorLog(json_encode($jsonMDArray));
		    	
	    		foreach ($jsonMDArray as $key => $value) 
		    	{
			    	errorLog("key: ".gettype($key)." value: ".json_encode($key));
			    	errorLog("value: ".gettype($value)." value: ".json_encode($value));
			    	
			    	errorLog($key." ".$value);
			    	
		    		if (strcasecmp($key, "base") == 0)
					{
				    	$base																			= urldecode( strip_tags( trim( $value ) ) );
				    }
				    else if (strcasecmp($key, "currency") == 0)
				    {
						$currency																		= urldecode( strip_tags( trim( $value ) ) );
				    }
				    else if (strcasecmp($key, "amount") == 0)
			    	{
			    		$amount																			= urldecode( strip_tags( trim( $value ) ) );
			    	}								 
		    	}
		    
				$cryptoCurrencyAssetTypeID																= 0;
				$fiatCurrencyAssetTypeID																= 0;
		    
			    if ($vendorID == 2)
			    {
					$cryptoCurrencyAssetTypeID															= getEnumValueAssetTypeForCoinbase($base, $dbh);
					
					if (strcasecmp($currency, "USD") == 0)
					{
						$fiatCurrencyAssetTypeID														= 2;
					}
					else
					{
						$fiatCurrencyAssetTypeID														= getEnumValueAssetTypeForCoinbase($currency, $dbh);	
					}
			    }
			    else
			    {
					$cryptoCurrencyAssetTypeID															= getEnumValueAssetType($base, $dbh);
		    		$fiatCurrencyAssetTypeID															= getEnumValueAssetType($currency, $dbh);    
			    }
		    
		    	errorLog("base: $base enum cc ID: $cryptoCurrencyAssetTypeID currency: $currency enum fiat currency ID: $fiatCurrencyAssetTypeID amount $amount ");
		    
			    $createDailyCryptoPriceRecord -> bindValue(':FK_CryptoAssetID', $cryptoCurrencyAssetTypeID);
				$createDailyCryptoPriceRecord -> bindValue(':FK_FiatCurrencyAssetID', $fiatCurrencyAssetTypeID);
				$createDailyCryptoPriceRecord -> bindValue(':priceDate', $priceDate);
				$createDailyCryptoPriceRecord -> bindValue(':fiatCurrencySpotPrice', $amount);
				
				if ($createDailyCryptoPriceRecord -> execute())
				{
					errorLog("INSERT IGNORE DailyCryptoSpotPrices
	(
	FK_CryptoAssetID,
	FK_FiatCurrencyAssetID,
	priceDate,
	fiatCurrencySpotPrice
	)
	VALUES
	(
	$cryptoCurrencyAssetTypeID,
	$fiatCurrencyAssetTypeID,
	'$priceDate',
	$amount
	)");
					$returnValue																		= 1;
			
				}	
			
				$dbh 																					= null;	
	    	}
	    	else
	    	{
		    	errorLog("ERROR: JSON object empty or $name not found");	
	    	}
    	}
    	catch (Exception $e)
    	{
	    	errorLog("ERROR: $name not found in JSON object");	
    	}
        
        return $returnValue;
    }
	
	function cleanJSONDailyPriceDataForVenderSpecificAbbreviationsUsingNewDataFormat($jsonObject, $name, $priceDate, $vendorID, $globalCurrentDate, $sid, $dbh)
	{
		$returnValue    																				= array();
	
    	try
    	{
			$createDailyCryptoPriceRecord																= $dbh->prepare("INSERT IGNORE DailyCryptoSpotPrices
			(
				FK_CryptoAssetID,
				FK_FiatCurrencyAssetID,
				priceDate,
				fiatCurrencySpotPrice
			)
			VALUES
			(
				:FK_CryptoAssetID,
				:FK_FiatCurrencyAssetID,
				:priceDate,
				:fiatCurrencySpotPrice
			)");

			if (!empty($jsonObject -> $name))
	    	{
		    	$jsonMDArray 																			= $jsonObject -> $name; 
		    	
		    	$base																					= "";
		    	$currency																				= "";
		    	$amount																					= 0.00;
		    	
		    	errorLog(json_encode($jsonMDArray));
		    	
	    		foreach ($jsonMDArray as $key => $value) 
		    	{
			    	errorLog("key: ".gettype($key)." value: ".json_encode($key));
			    	errorLog("value: ".gettype($value)." value: ".json_encode($value));
			    	
			    	if (strcasecmp($key, "latest_price") != 0)
			    	{
				    	errorLog($key." ".$value);
			    	
			    		if (strcasecmp($key, "base") == 0)
						{
					    	$base																		= urldecode( strip_tags( trim( $value ) ) );
					    }
					    else if (strcasecmp($key, "currency") == 0)
					    {
							$currency																	= urldecode( strip_tags( trim( $value ) ) );
					    }
					    else if (strcasecmp($key, "amount") == 0)
				    	{
				    		$amount																		= urldecode( strip_tags( trim( $value ) ) );
				    	}	
			    	}						 
		    	}
		    
				$cryptoCurrencyAssetTypeID																= 0;
				$fiatCurrencyAssetTypeID																= 0;
		    
			    if ($vendorID == 2)
			    {
					$cryptoCurrencyAssetTypeID															= getEnumValueAssetTypeForCoinbase($base, $dbh);
					
					if (strcasecmp($currency, "USD") == 0)
					{
						$fiatCurrencyAssetTypeID														= 2;
					}
					else
					{
						$fiatCurrencyAssetTypeID														= getEnumValueAssetTypeForCoinbase($currency, $dbh);	
					}
			    }
			    else
			    {
					$cryptoCurrencyAssetTypeID															= getEnumValueAssetType($base, $dbh);
		    		$fiatCurrencyAssetTypeID															= getEnumValueAssetType($currency, $dbh);    
			    }
		    
		    	errorLog("base: $base enum cc ID: $cryptoCurrencyAssetTypeID currency: $currency enum fiat currency ID: $fiatCurrencyAssetTypeID amount $amount ");
		    
			    $createDailyCryptoPriceRecord -> bindValue(':FK_CryptoAssetID', $cryptoCurrencyAssetTypeID);
				$createDailyCryptoPriceRecord -> bindValue(':FK_FiatCurrencyAssetID', $fiatCurrencyAssetTypeID);
				$createDailyCryptoPriceRecord -> bindValue(':priceDate', $priceDate);
				$createDailyCryptoPriceRecord -> bindValue(':fiatCurrencySpotPrice', $amount);
				
				if ($createDailyCryptoPriceRecord -> execute())
				{
					errorLog("INSERT IGNORE DailyCryptoSpotPrices
	(
	FK_CryptoAssetID,
	FK_FiatCurrencyAssetID,
	priceDate,
	fiatCurrencySpotPrice
	)
	VALUES
	(
	$cryptoCurrencyAssetTypeID,
	$fiatCurrencyAssetTypeID,
	'$priceDate',
	$amount
	)");
					$returnValue																		= 1;
			
				}	
			
				$dbh 																					= null;	
	    	}
	    	else
	    	{
		    	errorLog("ERROR: JSON object empty or $name not found");	
	    	}
    	}
    	catch (Exception $e)
    	{
	    	errorLog("ERROR: $name not found in JSON object");	
    	}
        
        return $returnValue;
    }
    
    function cleanJSONDateWithSessionTestAndDefaults($jsonObject, $name, $sessionName, $default)
	{
		$responseObject    													= array();
		$responseObject['returnValue']										= $default;
		$responseObject['jsonObjectEqualsCleared']							= false;
    	$responseObject['jsonObjectFound']									= false;
    	$responseObject['jsonObjectIsNull']									= false;
    	$responseObject['jsonObjectIsDate']									= false;
    	$responseObject['sessionValueFound']							 	= false;
    	$responseObject['sessionValueUsed']							 		= false;
    	$responseObject['sessionValueCleared']							 	= false;
    	$responseObject['sessionValueSet']							 		= false;
    	
    	$returnValue														= null;
    	$returnValueAsDate													= null;
    	
    	try
    	{
	    	if (strcasecmp($returnValue, "cleared") == 0)
	    	{
		    	$responseObject['jsonObjectEqualsCleared']					= true;
				
				$returnValue												= null;
				
				if (isset($_SESSION[$sessionName]))
		    	{
					$responseObject['sessionValueFound']					= true;
					
					$_SESSION[$sessionName]									= null;
					unset($_SESSION[$sessionName]);
		    	
					$responseObject['sessionValueCleared']					= true; 	
		    	}
	    	}
			else if (!empty($jsonObject -> $name))
	    	{
		    	$responseObject['jsonObjectFound']							= true;
		    	
		    	$returnValue 												= urldecode( strip_tags( trim( $jsonObject -> $name ) ) ); 
		    	
		    	if (is_null($returnValue))
		    	{
			    	$responseObject['jsonObjectIsNull']						= true;	
			    	
			    	if (isset($_SESSION[$sessionName]))
			    	{
						$responseObject['sessionValueFound']				= true;
						
						unset($_SESSION[$sessionName]);
			    	
						$responseObject['sessionValueCleared']				= true; 	
			    	}
			    	
/*
			    	$returnValue											= $default;
			    	
			    	if (!empty($returnValue))
			    	{
				   		$returnValueAsDateObject							= new DateTime($returnValue);
				   		$returnValueAsDate									= date_format($returnValueAsDateObject, "Y-m-d"); // fix date format
			    	 	
			    	}
*/
		    	}
		    	else
		    	{
			    	$returnValueAsDateObject							 	= new DateTime($returnValue);
				
					$returnValueAsDate										= date_format($returnValueAsDateObject, "Y-m-d"); // fix date format
			    	
			    	$responseObject['returnValue']							= $returnValueAsDate;
			    	
			    	$responseObject['jsonObjectIsDate']						= true;
			    	
			    	if (isset($_SESSION[$sessionName]))
			    	{
						$responseObject['sessionValueFound']				= true; 	
			    	}
			    	
			    	$_SESSION[$sessionName]									= $returnValueAsDate;
			    	
					$responseObject['sessionValueSet']						= true;
		    	}
	    	}
	    	else
	    	{
		    	errorLog("ERROR: JSON object empty or $name not found");
		    	
		    	if (isset($_SESSION[$sessionName]))
		    	{
					$responseObject['sessionValueFound']					= true;
					
					$returnValue											= $_SESSION[$sessionName];
		    	
					$responseObject['sessionValueUsed']						= true; 
					
					errorLog("ERROR: Used Session Value for sort/filter option $name which was not found");	
		    	}
		    	else
		    	{
					$returnValue											= $default;
					$_SESSION[$sessionName]									= $returnValue;	
					$responseObject['defaultValueUsed']						= true;
					
					$_SESSION[$sessionName]									= $returnValue;
			    	
					$responseObject['sessionValueSet']						= true;	 	
		    	}	
	    	}
    	}
    	catch (Exception $e)
    	{
	    	errorLog("ERROR: Exception $e when processing $name in JSON object");
	    	
	    	if (isset($_SESSION[$sessionName]))
	    	{
				$responseObject['sessionValueFound']						= true;
				
				$returnValue												= $_SESSION[$sessionName];
	    	
				$responseObject['sessionValueUsed']							= true; 
				
				errorLog("ERROR: Used Session Value for sort/filter option $name which was not used due to an error: $e");	
	    	}
	    	else
	    	{
				$returnValue												= $default;
				$_SESSION[$sessionName]										= $returnValue;	
				$responseObject['defaultValueUsed']							= true;
				
				$_SESSION[$sessionName]										= $returnValue;
		    	
				$responseObject['sessionValueSet']							= true;	 	
	    	}	
    	}
        
        errorLog("$name: $returnValueAsDate");
        
        $responseObject['returnValue']										= $returnValueAsDate;
        
        return $responseObject;	
	}
    
    function cleanJSONNumber($jsonObject, $name)
	{
    	$returnValue    													= 0;
    	
    	try
    	{
	    	if (!empty($jsonObject -> $name))
	    	{
		    	$returnValue 												= urldecode( strip_tags( trim( $jsonObject -> $name ) ) ); 
		    	
		    	if (!is_numeric($returnValue))
		    	{
			    	$returnValue											= -1;	
		    	}
	    	}
	    	else
	    	{
		    	errorLog("ERROR: JSON object empty or $name not found");	
	    	}	
    	}
    	catch (Exception $e)
    	{
	    	errorLog("ERROR: $name not found in JSON object");	
    	}
        
        return $returnValue;
    }
    
    function cleanJSONNumberWithSessionTestAndDefaults($jsonObject, $name, $sessionName, $default)
	{
		$responseObject    													= array();
		$responseObject['returnValue']										= $default;
    	$responseObject['jsonObjectFound']									= false;
    	$responseObject['jsonObjectEqualsCleared']							= false;
    	$responseObject['jsonObjectIsNull']									= false;
    	$responseObject['jsonObjectIsNumberic']								= false;
    	$responseObject['sessionValueFound']							 	= false;
    	$responseObject['sessionValueUsed']							 		= false;
    	$responseObject['sessionValueCleared']							 	= false;
    	$responseObject['sessionValueSet']							 		= false;
    	
    	$returnValue														= null;
    	
    	try
    	{
	    	if (!empty($jsonObject -> $name))
	    	{
		    	$responseObject['jsonObjectFound']							= true;
		    	
		    	$returnValue 												= urldecode( strip_tags( trim( $jsonObject -> $name ) ) ); 
		    	
		    	if (strcasecmp($returnValue, "cleared") == 0)
		    	{
			    	$responseObject['jsonObjectEqualsCleared']				= true;
					
					$returnValue											= null;
					
					if (isset($_SESSION[$sessionName]))
			    	{
						$responseObject['sessionValueFound']				= true;
						
						$_SESSION[$sessionName]								= null;
						unset($_SESSION[$sessionName]);
			    	
						$responseObject['sessionValueCleared']				= true; 	
			    	}
		    	}
				else if (is_null($returnValue))
		    	{
			    	errorLog("name $name found but null.  Clear session.");
			    	
			    	$responseObject['jsonObjectIsNull']						= true;	
			    	
			    	if (isset($_SESSION[$sessionName]))
			    	{
						$responseObject['sessionValueFound']				= true;
						
						$_SESSION[$sessionName]								= null;
						unset($_SESSION[$sessionName]);
			    	
						$responseObject['sessionValueCleared']				= true; 	
			    	}
			    	
			    	if (!is_null($default))
			    	{
				  		$returnValue										= $default;  	
			    	}
		    	}
		    	else if (!is_numeric($returnValue))
		    	{
			    	if (isset($_SESSION[$sessionName]))
			    	{
						$responseObject['sessionValueFound']				= true;
						
						$returnValue										= $_SESSION[$sessionName];
			    	
						$responseObject['sessionValueUsed']					= true; 	
			    	}	
		    	}
		    	else
		    	{
			    	$responseObject['jsonObjectIsNumberic']					= true;
			    	$responseObject['returnValue']							= $returnValue;
			    	
			    	if (isset($_SESSION[$sessionName]))
			    	{
						$responseObject['sessionValueFound']				= true; 	
			    	}
			    	
			    	$_SESSION[$sessionName]									= $returnValue;
			    	
					$responseObject['sessionValueSet']						= true;
		    	}
	    	}
	    	else
	    	{
		    	errorLog("ERROR: JSON object empty or $name not found");
		    	
		    	if (isset($_SESSION[$sessionName]))
		    	{
					$responseObject['sessionValueFound']					= true;
					
					$returnValue											= $_SESSION[$sessionName];
		    	
					$responseObject['sessionValueUsed']						= true; 
					
					errorLog("ERROR: Used Session Value for sort/filter option $name which was not found");	
		    	}
/*
		    	else
		    	{
					$responseObject['defaultValueUsed']						= true;
					$_SESSION[$sessionName]									= $default;	
					$responseObject['sessionValueSet']						= true;	 	
		    	}
*/	
	    	}	
    	}
    	catch (Exception $e)
    	{
	    	errorLog("ERROR: Exception $e when processing $name in JSON object");
	    	
	    	if (isset($_SESSION[$sessionName]))
	    	{
				$responseObject['sessionValueFound']						= true;
				
				$returnValue												= $_SESSION[$sessionName];
	    	
				$responseObject['sessionValueUsed']							= true; 
				
				errorLog("ERROR: Used Session Value for sort/filter option $name which was not used due to an error: $e");	
	    	}
/*
	    	else
	    	{
				$returnValue												= $default;
				$_SESSION[$sessionName]										= $returnValue;	
				$responseObject['defaultValueUsed']							= true;
				$responseObject['sessionValueSet']							= true;	 	
	    	}
*/	
    	}
        
        errorLog("$name : $returnValue");
        
        $responseObject['returnValue']										= $returnValue;
        
        return $responseObject;	
	}
	
	function cleanJSONString($jsonObject, $name)
	{
    	$returnValue    = "";
    	
    	try
    	{
	    	if (!empty($jsonObject -> $name))
	    	{
		    	$returnValue 	= urldecode( strip_tags( trim( $jsonObject -> $name ) ) ); 
	    	}
	    	else
	    	{
		    	errorLog("ERROR: JSON object empty or $name not found");	
	    	}	
    	}
    	catch (Exception $e)
    	{
	    	errorLog("ERROR: $name not found in JSON object");	
    	}
        
        return $returnValue;
    }
    
    function cleanJSONStringWithSessionTestAndDefaults($jsonObject, $name, $sessionName, $default)
	{
		$responseObject    													= array();
		$responseObject['returnValue']										= $default;
    	$responseObject['jsonObjectFound']									= false;
    	$responseObject['jsonObjectEqualsCleared']							= false;
    	$responseObject['jsonObjectIsNull']									= false;
    	$responseObject['sessionValueFound']							 	= false;
    	$responseObject['sessionValueUsed']							 		= false;
    	$responseObject['sessionValueCleared']							 	= false;
    	$responseObject['sessionValueSet']							 		= false;
    	
    	$returnValue														= null;
    	
    	try
    	{
	    	if (!empty($jsonObject -> $name))
	    	{
		    	$responseObject['jsonObjectFound']							= true;
		    	
		    	$returnValue 												= urldecode( strip_tags( trim( $jsonObject -> $name ) ) ); 
		    	
		    	if (strcasecmp($returnValue, "cleared") == 0)
		    	{
			    	$responseObject['jsonObjectEqualsCleared']				= true;
					
					$returnValue											= null;
					
					if (isset($_SESSION[$sessionName]))
			    	{
						$responseObject['sessionValueFound']				= true;
						
						$_SESSION[$sessionName]								= null;
						unset($_SESSION[$sessionName]);
			    	
						$responseObject['sessionValueCleared']				= true; 	
			    	}
		    	}
				else if (is_null($returnValue))
		    	{
			    	$responseObject['jsonObjectIsNull']						= true;	
			    	
			    	if (isset($_SESSION[$sessionName]))
			    	{
						$responseObject['sessionValueFound']				= true;
						
						unset($_SESSION[$sessionName]);
			    	
						$responseObject['sessionValueCleared']				= true; 	
			    	}
		    	}
		    	else
		    	{
			    	$responseObject['returnValue']							= $returnValue;
			    	
			    	if (isset($_SESSION[$sessionName]))
			    	{
						$responseObject['sessionValueFound']				= true; 	
			    	}
			    	
			    	$_SESSION[$sessionName]								= $returnValue;
			    	
					$responseObject['sessionValueSet']						= true;
		    	}
	    	}
	    	else
	    	{
		    	errorLog("ERROR: JSON object empty or $name not found");
		    	
		    	if (isset($_SESSION[$sessionName]))
		    	{
					$responseObject['sessionValueFound']					= true;
					
					$returnValue											= $_SESSION[$sessionName];
		    	
					$responseObject['sessionValueUsed']						= true; 
					
					errorLog("ERROR: Used Session Value for sort/filter option $name which was not found");	
		    	}
		    	else
		    	{
					$returnValue											= $default;
					$_SESSION[$sessionName]									= $returnValue;	
					$responseObject['defaultValueUsed']						= true;
					
					$_SESSION[$sessionName]									= $returnValue;
			    	
					$responseObject['sessionValueSet']						= true;	 	
		    	}	
	    	}	
    	}
    	catch (Exception $e)
    	{
	    	errorLog("ERROR: Exception $e when processing $name in JSON object");
	    	
	    	if (isset($_SESSION[$sessionName]))
	    	{
				$responseObject['sessionValueFound']						= true;
				
				$returnValue												= $_SESSION[$sessionName];
	    	
				$responseObject['sessionValueUsed']							= true; 
				
				errorLog("ERROR: Used Session Value for sort/filter option $name which was not used due to an error: $e");	
	    	}
	    	else
	    	{
				$returnValue												= $default;
				$_SESSION[$sessionName]										= $returnValue;	
				$responseObject['defaultValueUsed']							= true;
				
				$_SESSION[$sessionName]										= $returnValue;
		    	
				$responseObject['sessionValueSet']							= true;	 	
	    	}	
    	}
    	
    	errorLog("$name: $returnValue");
        
        $responseObject['returnValue']										= $returnValue;
        
        return $responseObject;	
	}
    
    function cleanJSONAPIKeyData($jsonObject, $name)
	{
    	$returnValue    = "";
    	
    	try
    	{
	    	if (!empty($jsonObject -> $name))
	    	{
		    	$returnValue 	= strip_tags( trim( $jsonObject -> $name ) );  
	    	}
	    	else
	    	{
		    	errorLog("ERROR: JSON object empty or $name not found");	
	    	}	
    	}
    	catch (Exception $e)
    	{
	    	errorLog("ERROR: $name not found in JSON object");	
    	}
        
        return $returnValue;
    }
    
    function cleanJSONPasswordData($jsonObject, $name)
	{
    	$returnValue    = "";
    	
    	try
    	{
	    	if (!empty($jsonObject -> $name))
	    	{
		    	$returnValue 	= urldecode( strip_tags( $jsonObject -> $name ) );  
	    	}
	    	else
	    	{
		    	errorLog("ERROR: JSON object empty or $name not found");	
	    	}	
    	}
    	catch (Exception $e)
    	{
	    	errorLog("ERROR: $name not found in JSON object");	
    	}
        
        return $returnValue;
    }
	
	function cleanJSONTransactionHistoryCurrentFilterAndSortOptions($jsonObject, $name, $dbh)
	{
    	$returnValue    													= array();
    	
    	errorLog($jsonObject);
    	
    	$name																= "currentFilterAndSortOptions";
    	
		try
	    {
			if (!empty($jsonObject -> $name))
		    {
				$jsonMDArray 												= $jsonObject -> $name;
				
				errorLog(gettype($jsonMDArray));
			}
		}
		catch (Exception $e)
	    {
		   	errorLog("ERROR: $name not found in JSON object");	
	    }	
/*
				if (is_array($jsonMDArray))
				{
					errorLog("is array ".json_encode($jsonMDArray));	
				}
				else if (is_object($jsonMDArray))
				{
					
				}
*/
				
				/*
 
			    	
			    currentFilterAndSortOptions" : 
	{
		"dateFilterAndSortOptions" : 
		{
			"startDate" : "01 Mar 2019",
			"endDate" : "26 Mar 2019",
			"dateSortDirection" : "desc"
		},
		"typeFilterValue" : "Buy",
		"assetFilterAndSortOptions" : 
		{
			"assetFilterValue" : "",
			"assetSortDirection" : "desc"
		},
		"exchangeWalletFilterValue" : "",
		"cryptoAmountFilterValue" : 2.31,
		"fiatAmountFilterValue" : null,
		"feeAmountFilterValue" : 0
	}	
*/
			    	
			    	
			    	/*
			    	$base													= "";
			    	$currency												= "";
			    	$amount													= 0.00;
			    	
		    		foreach ($jsonMDArray as $key => $value) 
			    	{
				    	errorLog($key." ".$value);
				    	
			    		if (strcasecmp($key, "base") == 0)
					{
				    		$base							= urldecode( strip_tags( trim( $value ) ) );
				    }
				    else if (strcasecmp($key, "currency") == 0)
				    	{
					    	$currency						= urldecode( strip_tags( trim( $value ) ) );
				    }
				    else if (strcasecmp($key, "amount") == 0)
				    	{
				    		$amount							= urldecode( strip_tags( trim( $value ) ) );
				    }								 
			    }
			    
			    $cryptoCurrencyAssetTypeID				= 0;
			    $fiatCurrencyAssetTypeID					= 0;
			    
			    if ($vendorID == 2)
			    {
					$cryptoCurrencyAssetTypeID			= getEnumValueAssetTypeForCoinbase($base, $dbh);
					
					if (strcasecmp($currency, "USD") == 0)
					{
						$fiatCurrencyAssetTypeID			= 2;
					}
					else
					{
						$fiatCurrencyAssetTypeID			= getEnumValueAssetTypeForCoinbase($currency, $dbh);	
					}
			    }
			    else
			    {
					$cryptoCurrencyAssetTypeID			= getEnumValueAssetType($base, $dbh);
		    			$fiatCurrencyAssetTypeID				= getEnumValueAssetType($currency, $dbh);    
			    }
			    
			    errorLog("base: $base enum cc ID: $cryptoCurrencyAssetTypeID currency: $currency enum fiat currency ID: $fiatCurrencyAssetTypeID amount $amount ");
			    
			    $createDailyCryptoPriceRecord -> bindValue(':FK_CryptoAssetID', $cryptoCurrencyAssetTypeID);
				$createDailyCryptoPriceRecord -> bindValue(':FK_FiatCurrencyAssetID', $fiatCurrencyAssetTypeID);
				$createDailyCryptoPriceRecord -> bindValue(':priceDate', $priceDate);
				$createDailyCryptoPriceRecord -> bindValue(':fiatCurrencySpotPrice', $amount);
					
				if ($createDailyCryptoPriceRecord -> execute())
				{
					errorLog("INSERT IGNORE DailyCryptoSpotPrices
(
	FK_CryptoAssetID,
	FK_FiatCurrencyAssetID,
	priceDate,
	fiatCurrencySpotPrice
)
VALUES
(
	$cryptoCurrencyAssetTypeID,
	$fiatCurrencyAssetTypeID,
	'$priceDate',
	$amount
)");
					$returnValue							= 1;
				
				}	
				
				$dbh 									= null;	
		    	}
		    	else
		    	{
			    errorLog("ERROR: JSON object empty or $name not found");	
		    	}
	    	}
    	catch (Exception $e)
    	{
	    	errorLog("ERROR: $name not found in JSON object");	
    	}
*/
        
        return $returnValue;
    }
	
	function cleanString($stringValue)
	{
    	errorLog("sent $stringValue");
    	
    	$returnValue    													= "";
    	
    	try
    	{
	    	if (!empty($stringValue))
	    	{
				$returnValue												= urldecode( strip_tags( trim( $stringValue ) ) ); 
	    	}
	    	
	    	errorLog("cleaned $returnValue");	
    	}
    	catch (Exception $e)
    	{
	    	errorLog("ERROR: could not clean $stringValue");	
    	}
        
        return $returnValue;
    }
	
	function cleanUploadedTransactionsTransaction($jsonObject, $name, $accountID, $userEncryptionKey, $transactionSourceID, $exchangeTileID, $walletTypeID, $includeDetailReporting, $dataImportEventRecordID, $globalCurrentDate, $sid, $dbh)
	{
    	errorLog("reached cleanUploadedTransactionsTransaction $name, $accountID, $userEncryptionKey, $transactionSourceID, $walletTypeID, $includeDetailReporting, $dataImportEventRecordID, $globalCurrentDate, $sid");
    	
		$responseObject																					= array();
    	
		$cryptoCurrencyTypesImported																	= array();
    	
		$walletTypeName																					= "Private Ledger Based Wallet";
		
		$transactionSourceName																			= getTransactionSourceTypeLabelFromEnumValue($transactionSourceID, $dbh);
    	
    	try
    	{
	    	if (!empty($jsonObject -> $name))
	    	{
		    	$jsonMDArray 																			= $jsonObject -> $name; 
		    	
		    	errorLog("count ".count($jsonMDArray), $GLOBALS['debugCoreFunctionality']);
		    	
		    	$importRecordFunctionCallResult															= updateDataImportEventStatus($accountID, $accountID, $dataImportEventRecordID, 2, $globalCurrentDate, $sid, $dbh); // set data import event stage to Data Acquisition and Global Transaction Identity Record Processing
		    	
		    	foreach ($jsonMDArray as $arrayIndex => $genericTransationObject) 
		    	{
			    	errorLog("arrayIndex: ".$arrayIndex);
			    	errorLog("transaction at $arrayIndex: ".json_encode($genericTransationObject));
			    	
			    	$ipClientAccountTransaction															= new IPClientAccountTransaction();
			    	
			    	$nativeTransactionIDValue															= urldecode( strip_tags( trim( $genericTransationObject -> nativeTransactionIDValue ) ) );
			    	
			    	$transactionType																	= urldecode( strip_tags( trim( $genericTransationObject -> transactionType ) ) );
				
					$transactionStatus																	= urldecode( strip_tags( trim( $genericTransationObject -> transactionStatus ) ) );
				    
					$transactionTimestamp																= urldecode( strip_tags( trim( $genericTransationObject -> transactionTimestamp ) ) );
				    
					$assetType																			= urldecode( strip_tags( trim( $genericTransationObject -> assetType ) ) );
				    
					$nativeCurrency																		= urldecode( strip_tags( trim( $genericTransationObject -> nativeCurrency ) ) );
				    
					$resourcePath																		= urldecode( strip_tags( trim( $genericTransationObject -> resourcePath ) ) );
					
					$numberOfConfirmations																= urldecode( strip_tags( trim( $genericTransationObject -> numberOfConfirmations ) ) );
				
					$providerNotes																		= urldecode( strip_tags( trim( $genericTransationObject -> providerNotes ) ) );
					                        
					$totalTransactionAmountGenericObject												= $genericTransationObject -> totalTransactionAmount;
					
					$totalTransactionAmountCrypto 														= urldecode( strip_tags( trim( $totalTransactionAmountGenericObject -> amountInCryptoCurrency ) ) );
				
					$totalTransactionAmountNative 														= urldecode( strip_tags( trim( $totalTransactionAmountGenericObject -> amountInNativeCurrency ) ) );
				
					$totalTransactionAmountUSD 															= urldecode( strip_tags( trim( $totalTransactionAmountGenericObject -> amountInUSD ) ) );
					
					if (!empty($totalTransactionAmountUSD) && $totalTransactionAmountUSD >= 0 && (empty($totalTransactionAmountNative) || $totalTransactionAmountNative < 0))
					{
						$totalTransactionAmountNative													= $totalTransactionAmountUSD;
					}
				
					$spotPriceAtTimeOfTransaction														= 0;
					$btcSpotPriceAtTimeOfTransaction													= 0;
				
					$globalTransactionIdentifierRecordID												= 0;
			
					$transactionDateObject																= new DateTime($transactionTimestamp);
			
					$transactionDate																	= date_format($transactionDateObject, "Y-m-d h:i:s"); // fix date format
				
					$transactionTimestamp																= $transactionDateObject -> getTimestamp();
				
					if (empty($transactionStatus))
					{
						$transactionStatus																= "completed"; // assume that transactions with no status are complete
					}
				
					if (empty($nativeCurrency))
					{
						$nativeCurrency																	= "USD";
					}
					
					$transactionTypeID 																	= getEnumValueTransactionType($transactionType, $dbh);
					$transactionStatusID 																= getEnumValueTransactionStatus($transactionStatus, $dbh);
					$assetTypeID 																		= getEnumValueAssetType($assetType, $dbh);
					$nativeCurrencyID 																	= getEnumValueAssetType($nativeCurrency, $dbh);
					
					createDataImportAssetStatusRecord($accountID, $userEncryptionKey, $dataImportEventRecordID, $assetTypeID, $nativeCurrencyID, $globalCurrentDate, $sid, $dbh);
					
					if (isset($cryptoCurrencyTypesImported[$assetTypeID][$nativeCurrencyID]))
					{
						$currentCount																	= $cryptoCurrencyTypesImported[$assetTypeID][$nativeCurrencyID];
						$currentCount++;
						
						$cryptoCurrencyTypesImported[$assetTypeID][$nativeCurrencyID]					= $currentCount;
					}
					else
					{
						$cryptoCurrencyTypesImported[$assetTypeID][$nativeCurrencyID]					= 1;	
					}
				
					if (empty($nativeTransactionIDValue))
					{
						$nativeTransactionIDValue														= "pgx".md5($accountID.$transactionDate.$transactionType.$assetType.$nativeCurrency.$numberOfConfirmations.$providerNotes.$totalTransactionAmountCrypto);
					}
					
					$cascadeRetrieveSpotPriceResponseObject												= getSpotPriceForAssetPairUsingSourceCascade(1, 2, $transactionDate, 2, "Coinbase by date", $dbh);
					
					if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
					{
						$btcSpotPriceAtTimeOfTransaction												= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
					}
				
					$globalTransactionIDTestResults														= getGlobalTransactionIdentificationRecordID($accountID, $assetTypeID, $nativeTransactionIDValue, $transactionSourceID, $globalCurrentDate, $sid, $dbh);
						
					errorLog("getGlobalTransactionIdentificationRecordID for $arrayIndex $accountID, $assetTypeID, $nativeTransactionIDValue, $transactionSourceID, $globalCurrentDate, $sid", $GLOBALS['debugCoreFunctionality']);	
						
					if ($globalTransactionIDTestResults['foundNativeTransactionForAccount'] == false)
					{
						errorLog("not found $arrayIndex", $GLOBALS['debugCoreFunctionality']);
						
						$returnValue[$nativeTransactionIDValue]["existingRecordFound"]					= false;
						
						// @task 2019-01-02 createGlobalTransactionIdentificationRecord must include native currency ID as well
						$globalTransactionCreationResults												= createGlobalTransactionIdentificationRecord($accountID, $exchangeTileID, $assetTypeID, $nativeTransactionIDValue, $transactionSourceID, $globalCurrentDate, $sid, $dbh);
			
						if ($globalTransactionCreationResults['createdGlobalTransactionIdentificationRecord'] == true)
						{
							$returnValue[$nativeTransactionIDValue]["createdGTIR"]						= true;
							
							$globalTransactionIdentifierRecordID										= $globalTransactionCreationResults['globalTransactionIdentificationRecordID'];
					
							$providerAccountWallet														= new ProviderAccountWallet();
							$instantiationResult														= $providerAccountWallet -> instantiateAccountWalletObjectForAccountByAssetTypeIDAndTransactionSourceID($accountID, $assetTypeID, $transactionSourceID, $dbh);
							
							if ($instantiationResult['instantiatedWallet'] == false)
							{
								$providerAccountWallet -> createAccountWalletObject($accountID, $assetTypeID, $assetType, $accountID, "$accountID-$transactionSourceID-$assetType", $walletTypeID, "Private Ledger Based Wallet", $transactionSourceID, $transactionSourceName, $globalCurrentDate, $sid, $dbh);
							}
							
							$providerWalletID															= $providerAccountWallet -> getAccountWalletID();
							
							if ($providerWalletID > 0)
							{
								$transactionAmountWithoutFeesGenericObject								= $genericTransationObject -> transactionAmountWithoutFees;
								$feeAmountGenericObject													= $genericTransationObject -> feeAmount;
								$toAddressGenericObject													= $genericTransationObject -> toAddress;
								$fromAddressGenericObject												= $genericTransationObject -> fromAddress;
								$cryptoCurrencyPriceAtTimeOfTransactionGenericObject					= $genericTransationObject -> cryptoCurrencyPriceAtTimeOfTransaction;
								
								$cryptoCurrencyPriceAtTimeOfTransaction									= new IPClientAccountCryptoCurrencyPriceObject();
								
								$spotPriceAtTimeOfTransaction											= urldecode( strip_tags( trim( $cryptoCurrencyPriceAtTimeOfTransactionGenericObject -> spotPriceUSD ) ) );
								$buyPriceAtTimeOfTransaction											= urldecode( strip_tags( trim( $cryptoCurrencyPriceAtTimeOfTransactionGenericObject -> buyPriceUSD ) ) );
								$sellPriceAtTimeOfTransaction											= urldecode( strip_tags( trim( $cryptoCurrencyPriceAtTimeOfTransactionGenericObject -> sellPriceUSD ) ) );
								
								// @TASK - find out which price to use here if they are provided - buy, sell, or spot 
								
								$totalTransactionAmount													= new IPTransactionAmountObject();
								
	
								$calculatedValueOfAssetPurchased										= round($totalTransactionAmountCrypto * $spotPriceAtTimeOfTransaction, 2);
	
								$calculatedFee															= round($totalTransactionAmountUSD - $calculatedValueOfAssetPurchased, 2);
	
								
	/*
								$totalTransactionAmountCrypto 											= urldecode( strip_tags( trim( $totalTransactionAmountGenericObject -> amountInCryptoCurrency ) ) );
								$totalTransactionAmountNative 											= urldecode( strip_tags( trim( $totalTransactionAmountGenericObject -> amountInNativeCurrency ) ) );
								$totalTransactionAmountUSD 												= urldecode( strip_tags( trim( $totalTransactionAmountGenericObject -> amountInUSD ) ) );
	*/
								$isDebit																= false;
								
								if ($totalTransactionAmountCrypto < 0 || $transactionTypeID == 2 || $transactionTypeID == 4 || $transactionTypeID == 6 || $transactionTypeID == 8 || $transactionTypeID == 10 || $transactionTypeID == 11)
								{
									$isDebit															= true;
								}
								
								if ($transactionTypeID == 15)
								{
									$isDebit															= identifyCoinbaseTradeDirection($assetType, $providerNotes);
									
									if (is_null($isDebit))
									{
										errorLog("isDebit is null for transaction $accountID, $accountID, $dataImportEventRecordID, 2, $globalCurrentDate exiting");
										continue;
									}	
								}
	
								$totalTransactionAmount -> setData($accountID, $totalTransactionAmountCrypto, $totalTransactionAmountNative, $totalTransactionAmountUSD, $accountID, $globalCurrentDate, 0, 0, $sid, 1, "Total Transaction Amount");
								
								$transactionAmountWithoutFees											= new IPTransactionAmountObject();
								
								$totalTransactionAmountWFCrypto 										= urldecode( strip_tags( trim( $transactionAmountWithoutFeesGenericObject -> amountInCryptoCurrency ) ) );
								$totalTransactionAmountWFNative 										= urldecode( strip_tags( trim( $transactionAmountWithoutFeesGenericObject -> amountInNativeCurrency ) ) );
								$totalTransactionAmountWFUSD 											= urldecode( strip_tags( trim( $transactionAmountWithoutFeesGenericObject -> amountInUSD ) ) );
								
								if (empty($totalTransactionAmountWFUSD) || $totalTransactionAmountWFUSD < 0)
								{
									$totalTransactionAmountWFUSD										= $calculatedValueOfAssetPurchased;
									
									// @ TASK - if native currency is not USD, calculate amount in native currency
									
									$totalTransactionAmountWFNative										= $calculatedValueOfAssetPurchased;
									
									// @ TASK - stage 1 - calculate fee percentage and then amount in crypto and native currency stage 2 - use crypto exchange value and fee objects to handle these calculations	
								}
								
								
								$transactionAmountWithoutFees -> setData($accountID, $totalTransactionAmountWFCrypto, $totalTransactionAmountWFNative, $totalTransactionAmountWFUSD, $accountID, $globalCurrentDate, 0, 0, $sid, 2, "Transaction Amount Without Fees");
								
								$feeAmount																= new IPTransactionAmountObject();
								
								$feeAmountCrypto 														= urldecode( strip_tags( trim( $feeAmountGenericObject -> amountInCryptoCurrency ) ) );
								$feeAmountNative														= urldecode( strip_tags( trim( $feeAmountGenericObject -> amountInNativeCurrency ) ) );
								$feeAmountUSD 															= urldecode( strip_tags( trim( $feeAmountGenericObject -> amountInUSD ) ) );
								
								if (empty($feeAmountUSD) || $feeAmountUSD < 1)
								{
									$feeAmountUSD														= $calculatedFee;
									
									// @ TASK - if native currency is not USD, calculate amount in native currency
									
									$feeAmountNative													= $calculatedFee;
									
									// @ TASK - stage 1 - calculate fee percentage and then amount in crypto and native currency stage 2 - use crypto exchange value and fee objects to handle these calculations	
								}
								
								$feeAmount -> setData($accountID, $feeAmountCrypto, $feeAmountNative, $feeAmountUSD, $accountID, $globalCurrentDate, 0, 0, $sid, 3, "Fee Amount");
								
								$transactionFeeInNativeCurrency											= $feeAmountCrypto * $spotPriceAtTimeOfTransaction;
								
								$providerAccountWalletID												= $providerAccountWallet -> getAccountWalletID();
								
								$toAddressValue															= urldecode( strip_tags( trim( $toAddressGenericObject -> addressValue ) ) );
								$toAddressType															= urldecode( strip_tags( trim( $toAddressGenericObject -> addressType ) ) );
								$toAddressCallbackURL													= urldecode( strip_tags( trim( $toAddressGenericObject -> addressCallbackURL ) ) );
								$toAddressResourcePath													= urldecode( strip_tags( trim( $toAddressGenericObject -> addressResourcePath ) ) );
								$toAddressCurrencyType 													= urldecode( strip_tags( trim( $toAddressGenericObject -> addressCurrencyType ) ) );
								
								if (empty($toAddressCurrencyType) && ($transactionTypeID == 2 || $transactionTypeID == 4 || $transactionTypeID == 6 || $transactionTypeID == 8) || ($transactionTypeID == 15 && $isDebit == true))
								{
									$toAddressCurrencyType												= "USD";	
								}
								else if (empty($toAddressCurrencyType) && ($transactionTypeID == 1 || $transactionTypeID == 3 || $transactionTypeID == 5 || $transactionTypeID == 7 || $transactionTypeID == 9) || ($transactionTypeID == 15 && $isDebit == false))
								{
									$toAddressCurrencyType												= $assetType;	
								}
								
								$toAddressTypeID														= getEnumValueResourceType($toAddressType, $dbh);
								$toAddressCurrencyTypeID												= getEnumValueAssetType($toAddressCurrencyType, $dbh);	
								
								$toResourceTypeID														= $toAddressTypeID;
								$toResourceTypeLabel													= $toAddressType;						
								
								// $toAddress -> setData($toAddressCallbackURL, $toAddressCurrencyType, $toAddressCurrencyTypeID, $accountID, $toAddressResourcePath, $toAddressType, $toAddressTypeID, $toAddressValue, $accountID, $globalCurrentDate, 0, 0, $sid);
								
								$fromAddressValue														= urldecode( strip_tags( trim( $fromAddressGenericObject -> addressValue ) ) );
								$fromAddressType														= urldecode( strip_tags( trim( $fromAddressGenericObject -> addressType ) ) );
								$fromAddressCallbackURL													= urldecode( strip_tags( trim( $fromAddressGenericObject -> addressCallbackURL ) ) );
								$fromAddressResourcePath												= urldecode( strip_tags( trim( $fromAddressGenericObject -> addressResourcePath ) ) );
								$fromAddressCurrencyType 												= urldecode( strip_tags( trim( $fromAddressGenericObject -> addressCurrencyType ) ) );
								
								if (empty($fromAddressCurrencyType) && ($transactionTypeID == 1 || $transactionTypeID == 3 || $transactionTypeID == 5 || $transactionTypeID == 7 || $transactionTypeID == 9))
								{
									$fromAddressCurrencyType											= "USD";	
								}
								else if (empty($fromAddressCurrencyType) && ($transactionTypeID == 2 || $transactionTypeID == 4 || $transactionTypeID == 6 || $transactionTypeID == 8))
								{
									$fromAddressCurrencyType											= $assetType;	
								}
								
								$fromAddressTypeID														= getEnumValueResourceType($fromAddressType, $dbh);
								$fromAddressCurrencyTypeID												= getEnumValueAssetType($fromAddressCurrencyType, $dbh);	
								
								$fromResourceTypeID														= $fromAddressTypeID;
								$fromResourceTypeLabel													= $fromAddressType;	
								
								// $fromAddress -> setData($fromAddressCallbackURL, $fromAddressCurrencyType, $fromAddressCurrencyTypeID, $accountID, $fromAddressResourcePath, $fromAddressType, $fromAddressTypeID, $fromAddressValue, $accountID, $globalCurrentDate, 0, 0, $sid);
								
								$fromAddressHashIDValue													= "";
								$toAddressHashIDValue													= "";
								
								$fromAddressName														= "";
								$toAddressName															= "";
								
								$fromAddressCryptoWalletIDValue											= $fromAddressValue;
								$toAddressCryptoWalletIDValue											= $toAddressValue;
								
								$fromAddressEmailAddress												= "";
								$toAddressEmailAddress													= "";
								
								$fromAddressCreatedAtDate												= $globalCurrentDate;
								$toAddressCreatedAtDate													= $globalCurrentDate;
								
								if (isEmailAddress($fromAddressValue) == 1)
								{
									$fromAddressEmailAddress											= $fromAddressValue;	
								}
								
								if (isEmailAddress($toAddressValue) == 1)
								{
									$toAddressEmailAddress												= $toAddressValue;	
								}
								
								$cryptoWalletIDValue													= "";
								
								$profitStanceWalletIDValue												= $providerAccountWallet -> getProfitStanceWalletIdentifier();
								
								if ($isDebit == true)
								{
									if (!empty($fromAddressValue))
									{
										$cryptoWalletIDValue											= $fromAddressValue;
									}
								}
								else
								{
									if (!empty($toAddressValue))
									{
										$cryptoWalletIDValue											= $toAddressValue;
									}	
								}
								
	/*
								if (empty($cryptoWalletIDValue))
								{
									// @task - discuss with Corey - should I just leave this blank?
									$cryptoWalletIDValue						= $profitStanceWalletIDValue;
								}
	*/
								// @task 20181222 - include the provided to and from currency ID values from the CSV in this code, and use the determined currency ID value in the return object
								// @task 20181222 - to match accounts - generate profitstance ID using only information available in both pull and CSV for each given provider type - things which will always match - and compare them - but what about the weird discrepencies in coinbase?
								$walletAssetsAndOwnershipResponseObject									= setWalletAssetsAndOwnership($assetTypeID, $assetType, $cryptoWalletIDValue, $isDebit, $nativeCurrencyID, $nativeCurrency, $fromAddressHashIDValue, $fromAddressResourcePath, $toAddressHashIDValue, $toAddressResourcePath, $transactionTypeID);
						
								$sourceWallet															= new CompleteCryptoWallet();
								$destinationWallet														= new CompleteCryptoWallet();
								
								$fromAddressCryptoWalletIDValue											= $sourceWallet -> determineCryptoWalletValueUsingAttributes($walletAssetsAndOwnershipResponseObject['fromAddressHashIDValue'], $fromAddressName, $fromAddressValue, $walletAssetsAndOwnershipResponseObject['fromAddressCryptoWalletIDValue'], $fromAddressEmailAddress);
								
								$toAddressCryptoWalletIDValue											= $destinationWallet -> determineCryptoWalletValueUsingAttributes($walletAssetsAndOwnershipResponseObject['toAddressHashIDValue'], $toAddressName, $toAddressValue, $walletAssetsAndOwnershipResponseObject['toAddressCryptoWalletIDValue'], $toAddressEmailAddress);
								
								$sourceWalletResponseObject												= $sourceWallet -> instantiateWalletUsingCryptoWalletAttributes($accountID, $walletAssetsAndOwnershipResponseObject['fromAddressCurrencyTypeID'], $walletAssetsAndOwnershipResponseObject['fromAddressCryptoWalletIDValue'], $walletAssetsAndOwnershipResponseObject['fromAddressTransactionSourceID'], $userEncryptionKey, $dbh);
								
								if ($sourceWalletResponseObject['instantiatedRecord'] == false)
								{
									$sourceWallet -> setData($accountID, $fromAddressCreatedAtDate, $walletAssetsAndOwnershipResponseObject['fromAddressHashIDValue'], $fromAddressName, $fromAddressValue, $walletAssetsAndOwnershipResponseObject['fromAddressCurrencyTypeID'], $walletAssetsAndOwnershipResponseObject['fromAddressCurrencyTypeLabel'], $accountID, $fromAddressCallbackURL, $walletAssetsAndOwnershipResponseObject['fromAddressCryptoWalletIDValue'], $fromAddressEmailAddress, 1, $walletAssetsAndOwnershipResponseObject['fromAddressResourcePath'], $fromResourceTypeID, $fromResourceTypeLabel, $walletAssetsAndOwnershipResponseObject['fromAddressTransactionSourceID'], $walletAssetsAndOwnershipResponseObject['fromAddressTransactionSourceName'], $transactionTypeID, $walletAssetsAndOwnershipResponseObject['fromWalletBelongsToAccountID'], $walletTypeID, $walletTypeName, $sid, $globalCurrentDate);
									
									$sourceWallet -> setWalletOwnership(1, $transactionTypeID);
									
									$writeSourceWalletToDatabaseResult									= $sourceWallet -> writeToDatabase($accountID, $userEncryptionKey, $globalCurrentDate, $sid, $dbh);	
									
									if ($writeSourceWalletToDatabaseResult['wroteToDatabase'] == false)
									{
										errorLog("failed to create crypto wallet $accountID, $fromAddressCreatedAtDate, ". $walletAssetsAndOwnershipResponseObject['fromAddressHashIDValue'].", $fromAddressName, $fromAddressValue, ".$walletAssetsAndOwnershipResponseObject['fromAddressCurrencyTypeID'].", ".$walletAssetsAndOwnershipResponseObject['fromAddressCurrencyTypeLabel'].", $accountID, $fromAddressCallbackURL, ".$walletAssetsAndOwnershipResponseObject['fromAddressCryptoWalletIDValue'].", $fromAddressEmailAddress, 1, ".$walletAssetsAndOwnershipResponseObject['fromAddressResourcePath'].", $fromResourceTypeID, $fromResourceTypeLabel, ".$walletAssetsAndOwnershipResponseObject['fromAddressTransactionSourceID'].", ".$walletAssetsAndOwnershipResponseObject['fromAddressTransactionSourceName'].", $transactionTypeID, ".$walletAssetsAndOwnershipResponseObject['fromWalletBelongsToAccountID'].", $walletTypeID, $walletTypeName, $sid, $globalCurrentDate");
									}
								}
								
								$destinationWalletResponseObject										= $destinationWallet -> instantiateWalletUsingCryptoWalletAttributes($accountID, $walletAssetsAndOwnershipResponseObject['toAddressCurrencyTypeID'], $walletAssetsAndOwnershipResponseObject['toAddressCryptoWalletIDValue'], $walletAssetsAndOwnershipResponseObject['toAddressTransactionSourceID'], $userEncryptionKey, $dbh);
								
								if ($destinationWalletResponseObject['instantiatedRecord'] == false)
								{
									$destinationWallet -> setData($accountID, $toAddressCreatedAtDate, $walletAssetsAndOwnershipResponseObject['toAddressHashIDValue'], $toAddressName, $toAddressValue, $walletAssetsAndOwnershipResponseObject['toAddressCurrencyTypeID'], $walletAssetsAndOwnershipResponseObject['toAddressCurrencyTypeLabel'], $accountID, $toAddressCallbackURL, $walletAssetsAndOwnershipResponseObject['toAddressCryptoWalletIDValue'], $toAddressEmailAddress, 0, $walletAssetsAndOwnershipResponseObject['toAddressResourcePath'], $toResourceTypeID, $toResourceTypeLabel, $walletAssetsAndOwnershipResponseObject['toAddressTransactionSourceID'], $walletAssetsAndOwnershipResponseObject['toAddressTransactionSourceName'], $transactionTypeID, $walletAssetsAndOwnershipResponseObject['toWalletBelongsToAccountID'], $walletTypeID, $walletTypeName, $sid, $globalCurrentDate);
									
									$destinationWallet -> setWalletOwnership(0, $transactionTypeID);
									
									$writeDestinationWalletToDatabaseResult								= $destinationWallet -> writeToDatabase($accountID, $userEncryptionKey, $globalCurrentDate, $sid, $dbh);	
									
									if ($writeDestinationWalletToDatabaseResult['wroteToDatabase'] == false)
									{
										errorLog("failed to create crypto wallet $accountID, $toAddressCreatedAtDate, ".$walletAssetsAndOwnershipResponseObject['toAddressHashIDValue'].", $toAddressName, $toAddressValue, ".$walletAssetsAndOwnershipResponseObject['toAddressCurrencyTypeID'].", ".$walletAssetsAndOwnershipResponseObject['toAddressCurrencyTypeLabel'].", $accountID, $toAddressCallbackURL, ".$walletAssetsAndOwnershipResponseObject['toAddressCryptoWalletIDValue'].", $toAddressEmailAddress, 0, ".$walletAssetsAndOwnershipResponseObject['toAddressResourcePath'].", $toResourceTypeID, $toResourceTypeLabel, ".$walletAssetsAndOwnershipResponseObject['toAddressTransactionSourceID'].", ".$walletAssetsAndOwnershipResponseObject['toAddressTransactionSourceName'].", $transactionTypeID, ".$walletAssetsAndOwnershipResponseObject['toWalletBelongsToAccountID'].", $walletTypeID, $walletTypeName, $sid, $globalCurrentDate");
									}
								}
								
								if ($fromResourceTypeID	== 0)
								{
									$fromResourceTypeID													= $sourceWallet -> getResourceTypeID();
									$fromResourceTypeLabel												= $sourceWallet -> getResourceTypeName();		
								}
								
								if ($toResourceTypeID == 0)
								{
									$toResourceTypeID													= $destinationWallet -> getResourceTypeID();
									$toResourceTypeLabel												= $destinationWallet -> getResourceTypeName();		
								}
								
								$transactionRecordID													= 0;
								
								$profitStanceTransactionIDValue											= createProfitStanceTransactionIDValue($accountID, $assetTypeID, $providerWalletID, $nativeTransactionIDValue, $globalCurrentDate, $sid); 
								
								$authorID																= $accountID;
								
								// add instantiation method by native ID - check to see if it exists alread - if not, set data with 0 as the record ID, and then commit
								// if so, compare values and look for differences then commit, or simply return that it already exists?
								
			
								$returnValue['transactions'][$nativeTransactionIDValue]['instantiation'] = $ipClientAccountTransaction -> instantiateByNativeTransactionIDValue($accountID, $userEncryptionKey, $nativeTransactionIDValue, $sid, $dbh);
				
								if ($returnValue['transactions'][$nativeTransactionIDValue]['instantiation']['ipTransactionFound'] == true)
								{
									// compare for update
									// @here
									errorLog("duplicate transaction for $arrayIndex.   $transactionDate.$transactionType.$assetType.$nativeCurrency");
								}
								else
								{
									$ipClientAccountTransaction -> setData($accountID, $assetType, $assetTypeID, $exchangeTileID, $authorID, $globalCurrentDate, $cryptoCurrencyPriceAtTimeOfTransaction, $feeAmount, $sourceWallet, $globalCurrentDate, $nativeCurrency, $nativeCurrencyID, $nativeTransactionIDValue, $numberOfConfirmations, $profitStanceTransactionIDValue, $providerNotes, $providerWalletID, $totalTransactionAmountCrypto, $spotPriceAtTimeOfTransaction, $btcSpotPriceAtTimeOfTransaction, $resourcePath, $sid, $destinationWallet, $totalTransactionAmount, $transactionAmountWithoutFees, $transactionRecordID, $transactionStatus, $transactionStatusID, $transactionType, $transactionTypeID, $transactionDate, $transactionTimestamp, $transactionSourceName, $transactionSourceID, $walletTypeID, $globalTransactionIdentifierRecordID);
									
									$returnValue['transactions'][$nativeTransactionIDValue]['creation'] = $ipClientAccountTransaction -> createTransactionRecord($userEncryptionKey, $dbh);
									
									if ($returnValue['transactions'][$nativeTransactionIDValue]['creation']['ipTransactionCreated'] == true)
									{
										$returnValue[$nativeTransactionIDValue]["newTransactionCreated"]																													= true;
										
										$newTransactionID 																																									= $ipClientAccountTransaction -> getTransactionRecordID();
										
										
										
										errorLog("created new IPTansaction $newTransactionID");
										
										
										$setNativeTransactionRecordIDResult																																					= setNativeTransactionRecordIDForGlobalTransactionIndentificationRecordID($accountID, $newTransactionID, $globalTransactionIdentifierRecordID, $globalCurrentDate, $sid, $dbh);
										
										// populate amount records
										
										$totalTransactionAmount -> setIPTransactionRecordID($newTransactionID);
										
										$returnValue['transactions'][$nativeTransactionIDValue]['transactionAmounts'][1]['creation'] 																	= $totalTransactionAmount -> createTransactionAmountObject($dbh);
										$returnValue['transactions'][$nativeTransactionIDValue]['transactionAmounts'][1]['updateObjectID'] 															= $ipClientAccountTransaction -> updateTransactionAmountObjectIDForType($totalTransactionAmount -> getIPTransactionAmountObjectID(), 1, $globalCurrentDate, $dbh);
										
										$ipClientAccountTransaction -> setTotalTransactionAmount($totalTransactionAmount);
										
										$transactionAmountWithoutFees -> setIPTransactionRecordID($newTransactionID);
										$returnValue['transactions'][$nativeTransactionIDValue]['transactionAmounts'][2]['creation']																	= $transactionAmountWithoutFees -> createTransactionAmountObject($dbh);
										$returnValue['transactions'][$nativeTransactionIDValue]['transactionAmounts'][2]['updateObjectID'] 															= $ipClientAccountTransaction -> updateTransactionAmountObjectIDForType($transactionAmountWithoutFees -> getIPTransactionAmountObjectID(), 2, $globalCurrentDate, $dbh);
										
										$ipClientAccountTransaction -> setTransactionAmountWithoutFees($transactionAmountWithoutFees);
										
										$feeAmount -> setIPTransactionRecordID($newTransactionID);
										$returnValue['transactions'][$nativeTransactionIDValue]['transactionAmounts'][3]['creation']																	= $feeAmount -> createTransactionAmountObject($dbh);
										$returnValue['transactions'][$nativeTransactionIDValue]['transactionAmounts'][3]['updateObjectID'] 															= $ipClientAccountTransaction -> updateTransactionAmountObjectIDForType($feeAmount -> getIPTransactionAmountObjectID(), 3, $globalCurrentDate, $dbh);
										
										$ipClientAccountTransaction -> setFeeAmount($feeAmount);
	
										$cryptoCurrencyPriceAtTimeOfTransaction -> setData($accountID, $assetTypeID, $assetType, $authorID, $buyPriceAtTimeOfTransaction, $globalCurrentDate, $newTransactionID, $sellPriceAtTimeOfTransaction, $spotPriceAtTimeOfTransaction,  $transactionDate, $sid);
										
										$cryptoCurrencyPriceAtTimeOfTransaction -> writeToDatabase($dbh);
										
										$ledgerTransactionAmountCrypto									= ABS($totalTransactionAmountCrypto);
										
										if ($isDebit == true)
										{
											$ledgerTransactionAmountCrypto	= $ledgerTransactionAmountCrypto * -1;	
										}
										
										$profitStanceLedgerEntry										= new ProfitStanceLedgerEntry();
										$profitStanceLedgerEntry -> setData($accountID, $assetTypeID, $assetType, $transactionSourceID, $transactionSourceName, $exchangeTileID, $globalTransactionIdentifierRecordID, $transactionDateObject, $ledgerTransactionAmountCrypto, $dbh);
										
										$writeProfitStanceLedgerEntryRecordResponseObject 				= $profitStanceLedgerEntry -> writeToDatabase($dbh);
										
										if ($writeProfitStanceLedgerEntryRecordResponseObject['wroteToDatabase'] == true)
										{
											errorLog("wrote profitStance ledger entry $accountID, $assetTypeID, $assetType, $transactionSourceID, $transactionSourceName, $globalTransactionIdentifierRecordID, $transactionDate, $totalTransactionAmountCrypto to the database.", $GLOBALS['debugCoreFunctionality']);
										}
										else
										{
											errorLog("could not write profitStance ledger entry $accountID, $assetTypeID, $assetType, $transactionSourceID, $transactionSourceName, $globalTransactionIdentifierRecordID, $transactionDate, $totalTransactionAmountCrypto to the database.", $GLOBALS['criticalErrors']);	
										}
									}	
									else
									{
										errorLog("could not create IPClientAccountTransaction for $accountID, $assetType, $assetTypeID, $exchangeTileID, $authorID, $globalCurrentDate, $globalCurrentDate, $nativeCurrency, $nativeCurrencyID, $nativeTransactionIDValue, $numberOfConfirmations, $profitStanceTransactionIDValue, $providerNotes, $providerWalletID, $spotPriceAtTimeOfTransaction, $btcSpotPriceAtTimeOfTransaction, $resourcePath, $sid, $transactionRecordID, $transactionStatus, $transactionStatusID, $transactionType, $transactionTypeID, $transactionDate, $transactionTimestamp, $transactionSourceName, $transactionSourceID, $walletTypeID, $globalTransactionIdentifierRecordID");
									}
								}	
							}
						}	
					}
					else
					{
						errorLog("found $arrayIndex", $GLOBALS['debugCoreFunctionality']);
						
						$returnValue[$nativeTransactionIDValue]["existingRecordFound"]					= true;
						$returnValue[$nativeTransactionIDValue]["newTransactionCreated"]				= false;
					}
				
					errorLog("completed array index $arrayIndex");
				}
				
				if ($includeDetailReporting != true)
				{
					$returnValue																		= array();
				}
			
				$returnValue["csvDataImported"]															= "complete";
	    	}
	    	else
	    	{
		    	errorLog("ERROR: JSON object empty or $name not found");	
	    	}	
    	}
    	catch (Exception $e)
    	{
	    	errorLog("ERROR: $name not found in JSON object");	
    	}
		
		foreach ($cryptoCurrencyTypesImported AS $assetTypeID => $nativeCurrencyTypeIDArray)
		{
			foreach ($nativeCurrencyTypeIDArray AS $nativeCurrencyTypeID => $numberOfRecords)
			{
				updateDataImportStageCompletionDateForAssetType($accountID, $dataImportEventRecordID, $assetTypeID, $nativeCurrencyTypeID, 2, $globalCurrentDate, $sid, $dbh);
				updateDataImportStageCompletionDateForAssetType($accountID, $dataImportEventRecordID, $assetTypeID, $nativeCurrencyTypeID, 3, $globalCurrentDate, $sid, $dbh);	
			}	
		}
		
		updateDataImportEventStatus($accountID, $accountID, $dataImportEventRecordID, 3, $globalCurrentDate, $sid, $dbh);

        return $responseObject;
    }
	
	// END JSON PARSE
	
	// SERVICE KEYS
	
	function getMailChimpKeys()
    {
		$responseObject					= array();
		
		$apiKey							= get_cfg_var("mailchimp.cfg.APIKEY");
		$listID							= get_cfg_var("mailchimp.cfg.LISTID");
		
		$responseObject['apiKey']		= $apiKey;
		$responseObject['listID']		= $listID;
		
		return $responseObject;
    }
    
    function getStripeKeys($keyType)
    {
		$responseObject						= array();
		
		$publishable							= "";
		$secret								= "";
		
		if (strcasecmp($keyType, "TEST") == 0)
		{	
			$publishable						= get_cfg_var("profitstance.cfg.STRIPE_TEST_PUBLISHABLE");
			$secret							= get_cfg_var("profitstance.cfg.STRIPE_TEST_SECRET");	
		}
		if (strcasecmp($keyType, "LIVE") == 0)
		{
			$publishable						= get_cfg_var("profitstance.cfg.STRIPE_LIVE_PUBLISHABLE");
			$secret							= get_cfg_var("profitstance.cfg.STRIPE_LIVE_SECRET");	
		}
		
		$responseObject['publishable_key']	= $publishable;
		$responseObject['secret_key']		= $secret;
		
		return $responseObject;
    }
	
	// END SERVICE KEYS
	
	// USER API KEY MANAGEMENT
	
	function getAPIKeyPairForUserForTransactionSourceAndKeyType($accountID, $transactionSourceID, $keyTypeID, $decryptionKey, $dbh)
	{
		$responseObject															= array();
		$responseObject['foundAPIKeyPair']										= false;
		$responseObject['keyTypeID']											= $keyTypeID;
	
		try
		{		
			$getAPIKeyPairForUserForTransactionSourceAndKeyType					= $dbh -> prepare("SELECT
	AES_DECRYPT(encryptedAPIKey, UNHEX(SHA2(:decryptionKey,512))) AS decryptedAPIKey,
	AES_DECRYPT(encryptedAPISecret, UNHEX(SHA2(:decryptionKey,512))) AS decryptedAPISecret,
	UserAPIKeys.FK_ExchangeTileID
FROM
	UserAPIKeys
WHERE
	FK_AccountID = :accountID AND
	FK_TransactionSourceID = :transactionSourceID AND
	FK_APIKeyTypeID = :apiKeyTypeID");

			$getAPIKeyPairForUserForTransactionSourceAndKeyType -> bindValue(':decryptionKey', $decryptionKey);
			$getAPIKeyPairForUserForTransactionSourceAndKeyType -> bindValue(':accountID', $accountID);
			$getAPIKeyPairForUserForTransactionSourceAndKeyType -> bindValue(':transactionSourceID', $transactionSourceID);
			$getAPIKeyPairForUserForTransactionSourceAndKeyType -> bindValue(':apiKeyTypeID', $keyTypeID);
						
			if ($getAPIKeyPairForUserForTransactionSourceAndKeyType -> execute() && $getAPIKeyPairForUserForTransactionSourceAndKeyType -> rowCount() > 0)
			{
				$row 															= $getAPIKeyPairForUserForTransactionSourceAndKeyType -> fetchObject();
				$responseObject['foundAPIKeyPair']								= true;
				$responseObject['APIKey']										= $row ->decryptedAPIKey;
				$responseObject['APISecret']									= $row ->decryptedAPISecret;	
				$responseObject['ExchangeTileID']								= $row ->FK_ExchangeTileID;		
			}
		}
	    catch (PDOException $e) 
	    {
	    	$responseObject['resultMessage']									= "Error: Unable to retrieve the API key data for user because of a database error: ".$e -> getMessage();
		}
		
		return $responseObject;
	}
	
	function getAPIKeySecretAndExchangeTileIDForUserForTransactionSourceAndAPIKey($accountID, $transactionSourceID, $apiKey, $keyTypeID, $decryptionKey, $dbh)
	{
		$responseObject														= array();
		$responseObject['foundAPIKeyPair']									= false;
		$responseObject['keyTypeID']										= $keyTypeID;
	
		try
		{		
			$getAPIKeyPairForUserForTransactionSourceAndKeyType				= $dbh -> prepare("SELECT
	AES_DECRYPT(encryptedAPISecret, UNHEX(SHA2(:decryptionKey,512))) AS decryptedAPISecret,
	AES_DECRYPT(encryptedAPIUserID, UNHEX(SHA2(:decryptionKey,512))) AS decryptedAPIUserID,
	UserAPIKeys.FK_ExchangeTileID
FROM
	UserAPIKeys
WHERE
	FK_AccountID = :accountID AND
	FK_TransactionSourceID = :transactionSourceID AND
	FK_APIKeyTypeID = :apiKeyTypeID AND
	encryptedAPIKey = AES_ENCRYPT(:apikey, UNHEX(SHA2(:decryptionKey,512)))");

			$getAPIKeyPairForUserForTransactionSourceAndKeyType -> bindValue(':decryptionKey', $decryptionKey);
			$getAPIKeyPairForUserForTransactionSourceAndKeyType -> bindValue(':accountID', $accountID);
			$getAPIKeyPairForUserForTransactionSourceAndKeyType -> bindValue(':transactionSourceID', $transactionSourceID);
			$getAPIKeyPairForUserForTransactionSourceAndKeyType -> bindValue(':apiKeyTypeID', $keyTypeID);
			$getAPIKeyPairForUserForTransactionSourceAndKeyType -> bindValue(':apikey', $apiKey);
				
			$apiKeySubset													= substr($apiKey, 0, 5);	
						
			if ($getAPIKeyPairForUserForTransactionSourceAndKeyType -> execute() && $getAPIKeyPairForUserForTransactionSourceAndKeyType -> rowCount() > 0)
			{
				$row 														= $getAPIKeyPairForUserForTransactionSourceAndKeyType -> fetchObject();
				$responseObject['foundAPIKeyPair']							= true;
				$responseObject['APIKey']									= $apiKey;
				$responseObject['APISecret']								= $row ->decryptedAPISecret;
				$responseObject['APIUserID']								= $row ->decryptedAPIUserID;
				$responseObject['ExchangeTileID']							= $row ->FK_ExchangeTileID;	
				
				errorLog("getAPIKeySecretAndExchangeTileIDForUserForTransactionSourceAndAPIKey retrieved secret and exchange tile ID for API Key starting with $apiKeySubset");	
			}
			else
			{
				errorLog("getAPIKeySecretAndExchangeTileIDForUserForTransactionSourceAndAPIKey could not retrieve secret and exchange tile ID for API Key starting with $apiKeySubset");		
			}
		}
	    catch (PDOException $e) 
	    {
	    	$responseObject['resultMessage']								= "Error: Unable to retrieve the API key data for user because of a database error: ".$e -> getMessage();
		}
		
		return $responseObject;
	}
	
	function writeAPIKeyPairForUserForTransactionSourceAndKeyType($accountID, $authorID, $transactionSourceID, $exchangeTileID, $keyTypeID, $apiKey, $apiSecret, $apiUserID, $encryptionKey, $sid, $dbh)
	{
		errorLog("writeAPIKeyPairForUserForTransactionSourceAndKeyType $accountID, $authorID, $transactionSourceID, $exchangeTileID, $keyTypeID, $apiKey, $apiSecret, $apiUserID, $encryptionKey, $sid");
		
		$responseObject															= array();
		$responseObject['wroteAPIKeyPair']										= false;
		$responseObject['exchangeTileID']										= $exchangeTileID;
	
		errorLog("REPLACE UserAPIKeys
(
	FK_AccountID,
	FK_TransactionSourceID,
	FK_ExchangeTileID,
	FK_APIKeyTypeID,
	encryptedAPIKey,
	encryptedAPISecret,
	encryptedAPIUserID,
	FK_AuthorID,
	encryptedSid
)
VALUES
(
	$accountID,
	$transactionSourceID,
	$exchangeTileID,
	$keyTypeID,
	AES_ENCRYPT('$apiKey', UNHEX(SHA2('$encryptionKey',512))),
	AES_ENCRYPT('$apiSecret', UNHEX(SHA2('$encryptionKey',512))),
	AES_ENCRYPT('$apiUserID', UNHEX(SHA2('$encryptionKey',512))),
	$authorID,
	AES_ENCRYPT('$sid', UNHEX(SHA2('$encryptionKey',512)))
)");
	
		try
		{		
			$writeAPIKeyPairForUserForTransactionSourceAndKeyType				= $dbh -> prepare("INSERT INTO UserAPIKeys
(
	FK_AccountID,
	FK_TransactionSourceID,
	FK_ExchangeTileID,
	FK_APIKeyTypeID,
	encryptedAPIKey,
	encryptedAPISecret,
	encryptedAPIUserID,
	FK_AuthorID,
	encryptedSid
)
VALUES
(
	:FK_AccountID,
	:FK_TransactionSourceID,
	:FK_ExchangeTileID,
	:FK_APIKeyTypeID,
	AES_ENCRYPT(:APIKey, UNHEX(SHA2(:encryptionKey,512))),
	AES_ENCRYPT(:APISecret, UNHEX(SHA2(:encryptionKey,512))),
	AES_ENCRYPT(:APIUserID, UNHEX(SHA2(:encryptionKey,512))),
	:FK_AuthorID,
	AES_ENCRYPT(:sid, UNHEX(SHA2(:encryptionKey,512)))
)");

			$writeAPIKeyPairForUserForTransactionSourceAndKeyType -> bindValue(':FK_AccountID', $accountID);
			$writeAPIKeyPairForUserForTransactionSourceAndKeyType -> bindValue(':FK_TransactionSourceID', $transactionSourceID);
			$writeAPIKeyPairForUserForTransactionSourceAndKeyType -> bindValue(':FK_ExchangeTileID', $exchangeTileID);
			$writeAPIKeyPairForUserForTransactionSourceAndKeyType -> bindValue(':FK_APIKeyTypeID', $keyTypeID);
			$writeAPIKeyPairForUserForTransactionSourceAndKeyType -> bindValue(':APIKey', $apiKey);
			$writeAPIKeyPairForUserForTransactionSourceAndKeyType -> bindValue(':APISecret', $apiSecret);
			$writeAPIKeyPairForUserForTransactionSourceAndKeyType -> bindValue(':APIUserID', $apiUserID);
			$writeAPIKeyPairForUserForTransactionSourceAndKeyType -> bindValue(':FK_AuthorID', $authorID);
			$writeAPIKeyPairForUserForTransactionSourceAndKeyType -> bindValue(':sid', $sid);
			$writeAPIKeyPairForUserForTransactionSourceAndKeyType -> bindValue(':encryptionKey', $encryptionKey);
						
			if ($writeAPIKeyPairForUserForTransactionSourceAndKeyType -> execute())
			{
				$row 															= $writeAPIKeyPairForUserForTransactionSourceAndKeyType -> fetchObject();
				$responseObject['wroteAPIKeyPair']								= true;			
			}
		}
	    catch (PDOException $e) 
	    {
	    	$responseObject['resultMessage']									= "Error: Unable to write the API key data for user because of a database error: ".$e -> getMessage();
		}
		
		return $responseObject;
	}
	
	// END USER API KEY MANAGEMENT
	
	// FILE MANAGEMENT
	
	function updateGeneratedTaxFileStatus($accountID, $fileName, $filePath, $newStatusValue, $globalCurrentDate, $dbh)
	{
		$responseObject														= array();
		$responseObject['updatedFileStatus']								= false;
		$responseObject['setFileDeletionDate']								= false;
		
		errorLog("$accountID, $fileName, $filePath, $newStatusValue, $globalCurrentDate
		
		UPDATE
	GeneratedTaxFiles
SET
	FK_FileStatus = $newStatusValue
WHERE
	encryptedFileURL = AES_ENCRYPT('$filePath', UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))) AND
	encryptedOriginalFileName = AES_ENCRYPT('$fileName', UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))) AND
	FK_AccountID = $accountID");
		
		try 
		{
	
			$updateFileStatus												= $dbh -> prepare("UPDATE
	GeneratedTaxFiles
SET
	FK_FileStatus = :fileStatus
WHERE
	encryptedFileURL = AES_ENCRYPT(:fileURL, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))) AND
	encryptedOriginalFileName = AES_ENCRYPT(:fileName, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))) AND
	FK_AccountID = :accountID");
	
			$setDeletionDate												= $dbh -> prepare("UPDATE
	GeneratedTaxFiles
SET
	deletedDate = :deletionDate
WHERE
	encryptedFileURL = AES_ENCRYPT(:fileURL, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))) AND
	encryptedOriginalFileName = AES_ENCRYPT(:fileName, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))) AND
	FK_AccountID = :accountID");

			$updateFileStatus -> bindValue(':fileStatus', $newStatusValue);
			$updateFileStatus -> bindValue(':fileURL', $filePath);
			$updateFileStatus -> bindValue(':fileName', $fileName);
			$updateFileStatus -> bindValue(':accountID', $accountID);
						
			if ($updateFileStatus -> execute())
			{
				$responseObject['updatedFileStatus']						= true;
				
				if ($newStatusValue == 3)
				{
					$setDeletionDate -> bindValue(':deletionDate', $globalCurrentDate);
					$setDeletionDate -> bindValue(':fileURL', $filePath);
					$setDeletionDate -> bindValue(':fileName', $fileName);
					$setDeletionDate -> bindValue(':accountID', $accountID);
					
					$responseObject['setFileDeletionDate']					= true;	
				}					
			}
		} 
		catch (PDOException $e)
		{
	    	$responseObject['error']										= $e->getMessage();
		}
		
		return $responseObject;
	}
	
	function verifyFileOwnership($fileOwnerID, $fileName, $filePath, $dbh)
	{
		$responseObject														= array();
		$responseObject['verifiedFileNameAndURL']							= false;
		$responseObject['verifiedFileOwnership']							= false;
		$responseObject['fileID']											= 0;
		
		try 
		{
			$verifyFileOwnership											= $dbh -> prepare("SELECT
	fileID,
	FK_AccountID
FROM
	GeneratedTaxFiles
WHERE
	encryptedFileURL = AES_ENCRYPT(:fileURL, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))) AND
	encryptedOriginalFileName = AES_ENCRYPT(:fileName, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512)))");
	
			$verifyFileOwnership -> bindValue(':fileURL', $filePath);
			$verifyFileOwnership -> bindValue(':fileName', $fileName);
						
			if ($verifyFileOwnership -> execute() && $verifyFileOwnership -> rowCount() > 0)
			{
				$responseObject["verifiedFileNameAndURL"]					= true;
				
				$row 														= $verifyFileOwnership -> fetchObject();
				
				$fileID														= $row -> fileID;	
				$retrievedFileOwnerID										= $row -> FK_AccountID;	
				
				if ($fileOwnerID == $retrievedFileOwnerID)
				{
					$responseObject['verifiedFileOwnership']				= true;		
				}					
			}	
		} 
		catch (PDOException $e)
		{
	    	$responseObject['error']										= $e->getMessage();
		}
		
		return $responseObject;
	}
	
	// END FILE MANAGEMENT
	
	// PORTFOLIO FUNCTIONS
	
	function getExchangeAndWalletCountForUser($liuAccountID, $sid, $dbh)
	{
		$responseObject																					= array();
		$responseObject['retrievedExchangeList']														= false;
		$responseObject['numberOfConnectedExchangesOrWallets']											= 0;
		
		try
		{		
			$getDataForUser																				= $dbh -> prepare("SELECT
	COUNT(DISTINCT(FK_TransactionSourceID)) AS numExchangeServers
FROM
	Transactions
WHERE
	Transactions.FK_AccountID = :accountID");
	
			//@task - temp - start here
			
			$getFileUploadDataForUser																	= $dbh -> prepare("SELECT
	COUNT(fileID) AS numUploadedFiles
FROM
	FileUploads
WHERE
	FileUploads.FK_AccountID = :accountID");

			// end temp

			$getDataForUser -> bindValue(':accountID', $liuAccountID);
						
			if ($getDataForUser -> execute() && $getDataForUser -> rowCount() > 0)
			{
				$row 																					= $getDataForUser -> fetchObject();
				
				$responseObject['retrievedExchangeList']												= true;
				$responseObject['numberOfConnectedExchangesOrWallets']									= $row -> numExchangeServers;
			}
			else
			{
				$responseObject['resultMessage']														= "Unable to retrieve a source list for user $liuAccountID";
			}
			
			// temp
			$getFileUploadDataForUser -> bindValue(':accountID', $liuAccountID);
						
			if ($getFileUploadDataForUser -> execute() && $getFileUploadDataForUser -> rowCount() > 0)
			{
				$row 																					= $getFileUploadDataForUser -> fetchObject();
				
				$responseObject['retrievedExchangeList']												= true;
				$responseObject['numberOfConnectedExchangesOrWallets']									= $responseObject['numberOfConnectedExchangesOrWallets'] + $row -> numUploadedFiles;
			}
			else
			{
				$responseObject['resultMessage']														= "Unable to retrieve a source list for user $liuAccountID";
			}
			// end temp
		}
	    catch (PDOException $e) 
	    {
	    	errorLog($e -> getMessage(), $GLOBALS['criticalErrors']);
	    	
	    	$responseObject['resultMessage']															= "Error: Unable to retrieve a source list for user $liuAccountID due to a database error: ".$e -> getMessage();
	
			die();
		}
		
		return $responseObject;
	}
	
	function getNumberOfTransactionsForUser($liuAccountID, $sid, $dbh)
	{
		$responseObject														= array();
		$responseObject['retrievedNumberOfTransactions']						= false;
		$responseObject['numberOfTransactions']								= 0;
		
		try
		{		
			$getNumberOfTransactionsForUser									= $dbh -> prepare("SELECT
	COUNT(transactionID) AS numTransactions
FROM
	Transactions
WHERE
	Transactions.FK_AccountID = :accountID");
	
			$getNumberOfTransactionsForUser -> bindValue(':accountID', $liuAccountID);
						
			if ($getNumberOfTransactionsForUser -> execute() && $getNumberOfTransactionsForUser -> rowCount() > 0)
			{
				$row 														= $getNumberOfTransactionsForUser -> fetchObject();
				
				$responseObject['retrievedNumberOfTransactions']				= true;
				$responseObject['numberOfTransactions']						= $row -> numTransactions;
			}
			else
			{
				$responseObject['resultMessage']								= "Unable to retrieve the number of transactions for user $liuAccountID";
			}
		}
	    catch (PDOException $e) 
	    {
	    		errorLog($e -> getMessage());
	    	
			$responseObject['resultMessage']									= "Error: Unable to retrieve a source list for user $liuAccountID due to a database error: ".$e -> getMessage();
	
			die();
		}
		
		return $responseObject;
	}
	
	function getNumberOfUploadedFilesForUser($liuAccountID, $sid, $dbh)
	{
		$responseObject														= array();
		$responseObject['retrievedUploadedFileCount']						= false;
		$responseObject['numberOfUploadedFiles']							= 0;
		
		try
		{		
			$getDataForUser													= $dbh -> prepare("SELECT
	COUNT(fileID) AS numUploadedFiles
FROM
	FileUploads
WHERE
	FileUploads.FK_AccountID = :accountID AND
	FK_ImportStatus = 1");

			$getDataForUser -> bindValue(':accountID', $liuAccountID);
						
			if ($getDataForUser -> execute() && $getDataForUser -> rowCount() > 0)
			{
				$row 														= $getDataForUser -> fetchObject();
				
				$responseObject['retrievedUploadedFileCount']				= true;
				$responseObject['numberOfUploadedFiles']					= $row -> numUploadedFiles;
			}
			else
			{
				$responseObject['resultMessage']							= "Unable to retrieve the number of uploaded files for user $liuAccountID";
			}
		}
	    catch (PDOException $e) 
	    {
	    	errorLog($e -> getMessage());
	    	
	    	$responseObject['resultMessage']								= "Error: Unable to retrieve the number of uploaded files for user $liuAccountID due to a database error: ".$e -> getMessage();
	
			die();
		}
		
		return $responseObject;
	}
	
	// END PORTFOLIO FUNCTIONS
	
	// CRYPTO PRICING FUNCTIONS
	
	function getCryptoDailyPricesForCryptoAndFiatWithDateRangeWithAutoAmountForIdenticalPairMembers($cryptoCurrency, $cryptoCurrencyAssetTypeID, $fiatCurrency, $fiatCurrencyAssetTypeID, $testDate, $endingDate, $globalCurrentDate, $sid, $dbh)
	{
		errorLog("getCryptoDailyPricesForCryptoAndFiatWithDateRangeWithAutoAmountForIdenticalPairMembers($cryptoCurrency, $cryptoCurrencyAssetTypeID, $fiatCurrency, $fiatCurrencyAssetTypeID, $testDate, $endingDate, $globalCurrentDate, $sid", $GLOBALS['debugCoreFunctionality']);
		
		$responseObject									= array();
		$responseObject['pulledData']					= false;
		
/*
		if ($cryptoCurrencyAssetTypeID == 0 || $fiatCurrencyAssetTypeID == 0)
		{
			errorLog("ERROR: one or more assets is type 0: $cryptoCurrency, $cryptoCurrencyAssetTypeID, $fiatCurrency, $fiatCurrencyAssetTypeID");
			return $responseObject;	
		}
*/
		
		try
    	{
			$createDailyCryptoPriceRecord				= $dbh->prepare("REPLACE DailyCryptoSpotPrices
(
	FK_CryptoAssetID,
	FK_FiatCurrencyAssetID,
	priceDate,
	fiatCurrencySpotPrice
)
VALUES
(
	:FK_CryptoAssetID,
	:FK_FiatCurrencyAssetID,
	:priceDate,
	:fiatCurrencySpotPrice
)");

			while ($testDate < $endingDate)
			{
				$formattedTestDate						= date_format($testDate, "Y-m-d");
				
				errorLog($formattedTestDate);
				
				errorLog("https://api.coinbase.com/v2/prices/$cryptoCurrency-$fiatCurrency/spot?date=$formattedTestDate");
				
				if ($cryptoCurrencyAssetTypeID == $fiatCurrencyAssetTypeID)
				{
					$responseObject['pulledData']		= true;
					
					$createDailyCryptoPriceRecord -> bindValue(':FK_CryptoAssetID', $cryptoCurrencyAssetTypeID);
					$createDailyCryptoPriceRecord -> bindValue(':FK_FiatCurrencyAssetID', $fiatCurrencyAssetTypeID);
					$createDailyCryptoPriceRecord -> bindValue(':priceDate', $formattedTestDate);
					$createDailyCryptoPriceRecord -> bindValue(':fiatCurrencySpotPrice', 1);
						
					if ($createDailyCryptoPriceRecord -> execute())
					{
						errorLog("REPLACE DailyCryptoSpotPrices
	(
		FK_CryptoAssetID,
		FK_FiatCurrencyAssetID,
		priceDate,
		fiatCurrencySpotPrice
	)
	VALUES
	(
		$cryptoCurrencyAssetTypeID,
		$fiatCurrencyAssetTypeID,
		'$priceDate',
		$amount
	)");				
					}	
				}
				else
				{
					$ch 									= curl_init();
		
					curl_setopt($ch, CURLOPT_URL, "https://api.coinbase.com/v2/prices/$cryptoCurrency-$fiatCurrency/spot?date=$formattedTestDate");
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				
					$headers 							= array();
					$headers[] 							= "Content-Type: application/x-www-form-urlencoded";
					curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
				
					$result 								= curl_exec($ch);
					
					if (curl_errno($ch)) 
					{
						errorLog('Error:' . curl_error($ch));
					}
					else 
					{
						$responseObject['pulledData']	= true;
						errorLog($result);
					}
					
					curl_close ($ch);
					
					# Get JSON as a string, return empty JSON if nothing returned
					$jsonObject							= json_decode($result);	
					
					cleanJSONDailyPriceData($jsonObject, "data", $formattedTestDate, $globalCurrentDate, $sid, $dbh);
						
				}
				
				$testDate -> modify('+1 day');
			}
	
	    	}
	    	catch (Exception $e)
	    	{
		    errorLog("ERROR: $name not found in JSON object");	
	    	}
		
		return $responseObject;	
	}
	
	function getCryptoDailyPricesForCryptoAndFiatWithDateRange($cryptoCurrency, $fiatCurrency, $testDate, $endingDate, $globalCurrentDate, $sid, $dbh)
	{
		$responseObject					= array();
		$responseObject['pulledData']	= false;
		
		// $endingDate -> modify('-1 day'); 
		
		while ($testDate < $endingDate)
		{
			$formattedTestDate	= date_format($testDate, "Y-m-d");
			
			errorLog($formattedTestDate);
				
			$ch = curl_init();
	
			curl_setopt($ch, CURLOPT_URL, "https://api.coinbase.com/v2/prices/$cryptoCurrency-$fiatCurrency/spot?date=$formattedTestDate");
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		
			$headers = array();
			$headers[] = "Content-Type: application/x-www-form-urlencoded";
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		
			$result = curl_exec($ch);
			
			if (curl_errno($ch)) 
			{
				errorLog('Error:' . curl_error($ch));
			}
			else 
			{
				$responseObject['pulledData']	= true;
				errorLog($result);
			}
			
			curl_close ($ch);
			
			# Get JSON as a string, return empty JSON if nothing returned
			$jsonObject														= json_decode($result);	
			
			cleanJSONDailyPriceDataForVenderSpecificAbbreviationsUsingNewDataFormat($jsonObject, "data", $formattedTestDate, 2, $globalCurrentDate, $sid, $dbh);
			
			$testDate -> modify('+1 day');
			
			usleep(200000);
		}
		
		return $responseObject;	
	}
	
	function getLastPriceDateForAssetTypePair($cryptoCurrencyID, $fiatCurrencyID, $globalCurrentDate, $sid, $dbh)
	{
		errorLog("SELECT
	DailyCryptoSpotPrices.priceDate,
	DailyCryptoSpotPrices.FK_DataSource,
	DailyCryptoSpotPrices.fiatCurrencySpotPrice
FROM
	DailyCryptoSpotPrices
WHERE
	DailyCryptoSpotPrices.FK_CryptoAssetID = $cryptoCurrencyID AND
	DailyCryptoSpotPrices.FK_FiatCurrencyAssetID = $fiatCurrencyID
ORDER BY
	DailyCryptoSpotPrices.priceDate DESC
LIMIT 1");
		
		$responseObject												= array();
		$responseObject['foundAssetPair']							= false;
		$responseObject['cryptoCurrencyAssetTypeID']				= 0;
		$responseObject['fiatCurrencyAssetTypeID']					= 0;
		$responseObject['lastPriceDate']							= "";
		$responseObject['fiatCurrencySpotPrice']					= 0;
		$responseObject['dataSource']								= 0;
		
		try
    	{
			$getLastPriceDateForAssetTypePair						= $dbh->prepare("SELECT
	DailyCryptoSpotPrices.priceDate,
	DailyCryptoSpotPrices.FK_DataSource,
	DailyCryptoSpotPrices.fiatCurrencySpotPrice
FROM
	DailyCryptoSpotPrices
WHERE
	DailyCryptoSpotPrices.FK_CryptoAssetID = :cryptoCurrencyAssetTypeID AND
	DailyCryptoSpotPrices.FK_FiatCurrencyAssetID = :fiatCurrencyAssetTypeID
ORDER BY
	DailyCryptoSpotPrices.priceDate DESC
LIMIT 1");

			$getLastPriceDateForAssetTypePair -> bindValue(':cryptoCurrencyAssetTypeID', $cryptoCurrencyID);
			$getLastPriceDateForAssetTypePair -> bindValue(':fiatCurrencyAssetTypeID', $fiatCurrencyID);
					
			if ($getLastPriceDateForAssetTypePair -> execute() && $getLastPriceDateForAssetTypePair -> rowCount())
			{
				$row 												= $getLastPriceDateForAssetTypePair -> fetchObject();
				
				$responseObject['foundAssetPair']					= true;
				$responseObject['cryptoCurrencyAssetTypeID']		= $cryptoCurrencyID;
				$responseObject['fiatCurrencyAssetTypeID']			= $fiatCurrencyID;
				$responseObject['lastPriceDate']					= $row -> priceDate;
				$responseObject['fiatCurrencySpotPrice']			= $row -> fiatCurrencySpotPrice;
				$responseObject['dataSource']						= $row -> FK_DataSource;
			}	
    	}
    	catch (Exception $e)
    	{
	    	errorLog("error setting crypto price");	
    	}
		
		return $responseObject;	
	}
	
	function setDailyPriceData($cryptoCurrencyAssetTypeID, $fiatCurrencyAssetTypeID, $priceDate, $spotPriceInFiatCurrency, $dataSourceID, $globalCurrentDate, $sid, $dbh)
	{
	    $returnValue    														= 0;
	    
	    if (!empty($spotPriceInFiatCurrency))
	    {
			errorLog("REPLACE DailyCryptoSpotPrices
			(
				FK_CryptoAssetID,
				FK_FiatCurrencyAssetID,
				priceDate,
				fiatCurrencySpotPrice,
				FK_DataSource
			)
			VALUES
			(
				$cryptoCurrencyAssetTypeID,
				$fiatCurrencyAssetTypeID,
				'$priceDate',
				$spotPriceInFiatCurrency,
				$dataSourceID
			)");
			
			try
			{
				$createDailyCryptoPriceRecord								= $dbh->prepare("REPLACE DailyCryptoSpotPrices
				(
					FK_CryptoAssetID,
					FK_FiatCurrencyAssetID,
					priceDate,
					fiatCurrencySpotPrice,
					FK_DataSource
				)
				VALUES
				(
					:FK_CryptoAssetID,
					:FK_FiatCurrencyAssetID,
					:priceDate,
					:fiatCurrencySpotPrice,
					:FK_DataSource
				)");
	
				$createDailyCryptoPriceRecord -> bindValue(':FK_CryptoAssetID', $cryptoCurrencyAssetTypeID);
				$createDailyCryptoPriceRecord -> bindValue(':FK_FiatCurrencyAssetID', $fiatCurrencyAssetTypeID);
				$createDailyCryptoPriceRecord -> bindValue(':priceDate', $priceDate);
				$createDailyCryptoPriceRecord -> bindValue(':fiatCurrencySpotPrice', $spotPriceInFiatCurrency);
				$createDailyCryptoPriceRecord -> bindValue(':FK_DataSource', $dataSourceID);
						
				if ($createDailyCryptoPriceRecord -> execute())
				{
					$returnValue												= 1;
					
				}	
			}
		    	catch (Exception $e)
		    	{
			    	errorLog("error setting crypto price");	
		    	}    
	    }	
        
        return $returnValue;
    }
	
	// END CRYPTO PRICING FUNCTIONS
	
	// PAYMENT PROCESSING FUNCTIONS
	
	// NOTE: These functions are for processing payments using Stripe
	
	function createPaymentHistoryRecord($liuAccountID, $paymentToken, $paymentMethodName, $paymentAmount, $firstName, $lastName, $addressOne, $city, $state, $zipCode, $country, $expMonth, $expYear, $lastFourDigit, $wasPaid, $paymentEmailAddress, $planTypeID, $cardTypeID, $globalCurrentDate, $sid, $dbh)
	{
		$responseObject						= array();
		$responseObject['recordedPayment']	= false;
		
		$paymentAmount						= $paymentAmount / 100;
		
/*
		errorLog("INSERT INTO PaymentHistory
(
	FK_AccountID,
	encryptedPaymentToken,
	paymentMethod,
	paymentDateTime,
	paymentAmount,
	encryptedFirstName,
	encryptedLastName,
	encryptedAddressOne,
	FK_CityID,
	FK_StateID,
	encryptedZipCode,
	FK_CountryID,
	encryptedExpirationMonth,
	encryptedExpirationYear,
	encryptedLastFourDigits,
	wasPaid,
	encryptedPaymentEmailAddress,
	encryptedSid
)
VALUES
(
	$liuAccountID,
	AES_ENCRYPT('$paymentToken', UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	'$paymentMethodName',
	'$globalCurrentDate',
	$paymentAmount,
	AES_ENCRYPT('$firstName', UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	AES_ENCRYPT('$lastName', UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	AES_ENCRYPT('$addressOne', UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	$city,
	$state,
	AES_ENCRYPT('$zipCode', UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	$country,
	AES_ENCRYPT('$expMonth', UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	AES_ENCRYPT('$expYear', UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	AES_ENCRYPT('$lastFourDigit', UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	$wasPaid,
	AES_ENCRYPT('$paymentEmailAddress', UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	AES_ENCRYPT('$sid', UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512)))
)");
*/
		
		try
		{		
			$insertPaymentInfo					= $dbh -> prepare("INSERT INTO PaymentHistory
(
	FK_AccountID,
	encryptedPaymentToken,
	paymentMethod,
	paymentDateTime,
	paymentAmount,
	encryptedFirstName,
	encryptedLastName,
	encryptedAddressOne,
	FK_CityID,
	FK_StateID,
	encryptedZipCode,
	FK_CountryID,
	encryptedExpirationMonth,
	encryptedExpirationYear,
	encryptedLastFourDigits,
	wasPaid,
	encryptedPaymentEmailAddress,
	encryptedSid,
	FK_PlanTypeID,
	FK_CardTypeID
)
VALUES
(
	:FK_AccountID,
	AES_ENCRYPT(:encryptedPaymentToken, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	:paymentMethod,
	:paymentDateTime,
	:paymentAmount,
	AES_ENCRYPT(:encryptedFirstName, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	AES_ENCRYPT(:encryptedLastName, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	AES_ENCRYPT(:encryptedAddressOne, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	:FK_CityID,
	:FK_StateID,
	AES_ENCRYPT(:encryptedZipCode, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	:FK_CountryID,
	AES_ENCRYPT(:encryptedExpirationMonth, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	AES_ENCRYPT(:encryptedExpirationYear, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	AES_ENCRYPT(:encryptedLastFourDigits, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	:wasPaid,
	AES_ENCRYPT(:encryptedPaymentEmailAddress, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	AES_ENCRYPT(:encryptedSid, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	:FK_PlanTypeID,
	:FK_CardTypeID
)");

			$updatePaymentInfoInUserAccount		= $dbh -> prepare("UPDATE
	UserAccounts
SET
	FK_PlanTypeID = :FK_PlanTypeID,
	lastPaymentDate = :lastPaymentDate,
	modificationDate = :modificationDate
WHERE
	userAccountID = :accountID");

			$insertPaymentInfo -> bindValue(':FK_AccountID', $liuAccountID);
			$insertPaymentInfo -> bindValue(':encryptedPaymentToken', $paymentToken);
			$insertPaymentInfo -> bindValue(':paymentMethod', $paymentMethodName);
			$insertPaymentInfo -> bindValue(':paymentDateTime', $globalCurrentDate);
			$insertPaymentInfo -> bindValue(':paymentAmount', $paymentAmount);
			$insertPaymentInfo -> bindValue(':encryptedFirstName', $firstName);
			$insertPaymentInfo -> bindValue(':encryptedLastName', $lastName);
			$insertPaymentInfo -> bindValue(':encryptedAddressOne', $addressOne);
			$insertPaymentInfo -> bindValue(':FK_CityID', $city);
			$insertPaymentInfo -> bindValue(':FK_StateID', $state);
			$insertPaymentInfo -> bindValue(':encryptedZipCode', $zipCode);
			$insertPaymentInfo -> bindValue(':FK_CountryID', $country);
			$insertPaymentInfo -> bindValue(':encryptedExpirationMonth', $expMonth);
			$insertPaymentInfo -> bindValue(':encryptedExpirationYear', $expYear);
			$insertPaymentInfo -> bindValue(':encryptedLastFourDigits', $lastFourDigit);
			$insertPaymentInfo -> bindValue(':wasPaid', $wasPaid);
			$insertPaymentInfo -> bindValue(':encryptedPaymentEmailAddress', $paymentEmailAddress);
			$insertPaymentInfo -> bindValue(':encryptedSid', $sid);
			$insertPaymentInfo -> bindValue(':FK_PlanTypeID', $planTypeID);
			$insertPaymentInfo -> bindValue(':FK_CardTypeID', $cardTypeID);
				
			if ($insertPaymentInfo -> execute())
			{
				$responseObject['recordedPayment']			= true;
				$responseObject['resultMessage']			= "Successfully recorded payment information";
					
				$paymentID 									= $dbh -> lastInsertId();
				$responseObject['paymentID']				= $paymentID;
				
				$updatePaymentInfoInUserAccount -> bindValue(':accountID', $liuAccountID);
				$updatePaymentInfoInUserAccount -> bindValue(':FK_PlanTypeID', $planTypeID);
				$updatePaymentInfoInUserAccount -> bindValue(':lastPaymentDate', $globalCurrentDate);
				$updatePaymentInfoInUserAccount -> bindValue(':modificationDate', $globalCurrentDate);
				
				$updatePaymentInfoInUserAccount -> execute();
			}
			else
			{
				$responseObject['resultMessage']			= "Unable to record payment information";	
			}
		}
	    catch (PDOException $e) 
	    {
	    	$responseObject['resultMessage']				= "Error: Unable to record payment information due to a database error: ".$e -> getMessage();	
	    	
	    	errorLog($e -> getMessage());
	
			die();
		}
		
		return $responseObject;
	}
	
	// END PAYMENT PROCESSING FUNCTIONS
	
	// FRIEND INVITE FUNCTIONS
	
	function createFriendInviteCodesUsingEmailAddressAndSponserID($emailAddress, $sponserID, $invitationFromName, $globalCurrentDate, $sid, $dbh)
	{
		$responseObject									= array();
		
		try
		{	
			$createFriendInvite							= $dbh -> prepare("INSERT INTO FriendInvites
(
creationDate,
encryptedEmailAddress,
generatedFriendHash,
FK_ActivationStatus,
FK_SponserID,
encryptedSid
)
VALUES
(
:creationDate,
AES_ENCRYPT(:emailAddress, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
AES_ENCRYPT(:generatedFriendHash, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
:activationStatus,
:FK_SponserID,
AES_ENCRYPT(:sid, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512)))
)");

			$decrementRemainingFriendInviteCodeCount	= $dbh -> prepare("UPDATE
	UserAccounts
SET
	numRemainingInviteCodes = numRemainingInviteCodes - 1
WHERE
	userAccountID = :accountID
");
		
			$lengthOfEmailAddress						= strlen($emailAddress);
				
			$randomString								= generateRandomString($lengthOfEmailAddress);
				
			$referralCode								= substr($sid, 0, 8)."-".md5("createFriendInvite$emailAddress $globalCurrentDate $sponserID".md5("$emailAddress $globalCurrentDate $randomString beta1").$sid);
				
			$createFriendInvite -> bindValue(':creationDate', $globalCurrentDate);
			$createFriendInvite -> bindValue(':emailAddress', $emailAddress);
			$createFriendInvite -> bindValue(':generatedFriendHash', $referralCode);
			$createFriendInvite -> bindValue(':activationStatus', 0);
			$createFriendInvite -> bindValue(':FK_SponserID', $sponserID);
			$createFriendInvite -> bindValue(':sid', $sid);

				
			if ($createFriendInvite -> execute())
			{
				$responseObject							= $referralCode;
				
				$decrementRemainingFriendInviteCodeCount -> bindValue(':accountID', $sponserID);
				
				$decrementRemainingFriendInviteCodeCount -> execute();
				
				sendFriendInvitationEmail($emailAddress, $referralCode, $invitationFromName);	
			}
			
			errorLog($referralCode);	
		}
	    catch (PDOException $e) 
	    {
	    	errorLog($e->getMessage());
		}

		return $responseObject;
	}
	
	function getEmailAddressForInvitationCode($invitationCode, $dbh)
	{
		$responseObject										= array();
		$responseObject['foundInvitedEmailAddress']			= false;
		
		try
		{	
			$getEmailAddressForInvitationCode				= $dbh -> prepare("SELECT
	AES_DECRYPT(encryptedEmailAddress, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))) AS emailAddress,
	FK_ActivationStatus
FROM
	FriendInvites
WHERE
	generatedFriendHash = AES_ENCRYPT(:invitationCode, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512)))");
		
			$getEmailAddressForInvitationCode -> bindValue(':invitationCode', $invitationCode);
				
			if ($getEmailAddressForInvitationCode -> execute() && $getEmailAddressForInvitationCode -> rowCount())
			{
				$row 										= $getEmailAddressForInvitationCode -> fetchObject();
				
				$emailAddress								= $row -> emailAddress;
				$activationStatus							= $row -> FK_ActivationStatus;
				
				$responseObject['foundInvitedEmailAddress']	= true;
				$responseObject['emailAddress']				= $emailAddress;
				$responseObject['activationStatus']			= $activationStatus;
				
				$emailTestResult							= doesEmailAccountExist($emailAddress, $dbh);
				
				if ($emailTestResult['doesEmailAccountExist'] == true)
				{
					$responseObject['foundExistingAccount']	= true;	
				}
				else
				{
					$responseObject['foundExistingAccount']	= false;
				}	
			}	
		}
	    catch (PDOException $e) 
	    {
	    	errorLog($e->getMessage());
		}
	
		return $responseObject;
	}
	
	function getNumberOfPendingFriendInviteCodesForSponserID($sponserID, $sid, $dbh)
	{
		$responseObject										= array();
		$responseObject['foundNumInvites']					= false;
		$responseObject['numPendingInvites']				= 0;
		
		try
		{	
			$getNumberOfFriendInvitesForSponser				= $dbh -> prepare("SELECT
	COUNT(friendInviteRecordID) AS numPendingInvites
FROM
	FriendInvites
WHERE
	FK_SponserID = :sponserID AND
	FK_ActivationStatus = 0");
		
			$getNumberOfFriendInvitesForSponser -> bindValue(':sponserID', $sponserID);
				
			if ($getNumberOfFriendInvitesForSponser -> execute() && $getNumberOfFriendInvitesForSponser -> rowCount())
			{
				$row 										= $getNumberOfFriendInvitesForSponser -> fetchObject();
				
				$numAvailableInvites						= $row -> numPendingInvites;
				
				$responseObject['foundNumInvites']			= true;
				$responseObject['numPendingInvites']		= $numAvailableInvites;	
			}	
		}
	    catch (PDOException $e) 
	    {
	    	errorLog($e->getMessage());
		}

		return $responseObject;
	}
	
	function submitRequestForAdditionalInviteCodes($liUser, $reasonForRequestText, $globalCurrentDate, $sid, $dbh)
	{
		$responseObject									= array();
		$responseObject['submittedRequest']				= false;
		
		errorLog("$liUser, $reasonForRequestText, $globalCurrentDate, $sid");
		
		try
		{	
			$requestMoreInvites							= $dbh -> prepare("INSERT INTO RequestsForAdditionalFriendInviteCodes
(
	requestDate,
	FK_AccountID,
	FK_RequestStatus,
	encryptedRequestText,
	modificationDate
)
VALUES
(
	:requestDate,
	:FK_AccountID,
	:FK_RequestStatus,
	AES_ENCRYPT(:requestText, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	:modificationDate
)");

			$requestMoreInvites -> bindValue(':requestDate', $globalCurrentDate);
			$requestMoreInvites -> bindValue(':FK_AccountID', $liUser);
			$requestMoreInvites -> bindValue(':FK_RequestStatus', 0);
			$requestMoreInvites -> bindValue(':requestText', $reasonForRequestText);
			$requestMoreInvites -> bindValue(':modificationDate', $globalCurrentDate);
			
			if ($requestMoreInvites -> execute())
			{
				$responseObject['submittedRequest']		= true;	
			}
		}
	    catch (PDOException $e) 
	    {
	    	errorLog($e->getMessage());
		}

		return $responseObject;	
	}
	
	// END FRIEND INVITE FUNCTIONS
	
	// TRANSACTION AND ASSET IDENTIFICATION FUNCTIONS
	
	function getAllAssetTypes($dbh)
	{
		$assetTypes															= array();
		
		try
		{		
			$getAllAssetTypes												= $dbh -> prepare("SELECT DISTINCT
	assetTypeID,
	assetTypeLabel
FROM
	AssetTypes
WHERE
	assetTypeLabel != '1337' AND
	assetTypeID < 408
ORDER BY
	assetTypeLabel");

			if ($getAllAssetTypes -> execute() && $getAllAssetTypes -> rowCount() > 0)
			{
				while ($row = $getAllAssetTypes -> fetchObject())
				{
					$assetTypeID												= $row -> assetTypeID;
					$assetTypeLabel											= $row -> assetTypeLabel;
				
					$assetTypes[$assetTypeID]								= $assetTypeLabel;	
				}			
			}
		}
	    catch (PDOException $e) 
	    {
	    		errorLog($e -> getMessage());
	
			die();
		}
		
		return $assetTypes;
	}
	
	function getAllAssetTypesForUser($liAccountID, $dbh)
	{
		$assetTypes									= array();
		
		try
		{		
			$getAllAssetTypesForUser				= $dbh -> prepare("SELECT DISTINCT
	assetTypeID,
	assetTypeLabel
FROM
	Transactions
	INNER JOIN AssetTypes ON Transactions.FK_AssetTypeID = AssetTypes.assetTypeID
WHERE
	FK_AccountID = 2 AND
	assetTypeID != 2");

			$getAllAssetTypesForUser -> bindValue(':accountID', $liAccountID);
						
			if ($getAllAssetTypesForUser -> execute() && $getAllAssetTypesForUser -> rowCount() > 0)
			{
				while ($row = $getAllAssetTypesForUser -> fetchObject())
				{
					$assetTypeID						= $row -> assetTypeID;
					$assetTypeLabel						= $row -> assetTypeLabel;
				
					$assetTypes[$assetTypeID]			= $assetTypeLabel;	
				}			
			}
		}
	    catch (PDOException $e) 
	    {
	    	errorLog($e -> getMessage());
	
			die();
		}
		
		return $assetTypes;
	}
	
	function getAllTransactionSourcesForUser($liAccountID, $dbh)
	{
		$transactionSources							= array();
		
		try
		{		
			$getAllTransactionSourcesForUser		= $dbh -> prepare("SELECT DISTINCT
	FK_TransactionSourceID
FROM
	Transactions
WHERE
	FK_AccountID = :accountID");

			$getAllTransactionSourcesForUser -> bindValue(':accountID', $liAccountID);
						
			if ($getAllTransactionSourcesForUser -> execute() && $getAllTransactionSourcesForUser -> rowCount() > 0)
			{
				while ($row = $getAllTransactionSourcesForUser -> fetchObject())
				{
					$transactionSources[]			= $row -> FK_TransactionSourceID;	
				}
			}
		}
	    catch (PDOException $e) 
	    {
	    	errorLog($e -> getMessage());
	
			die();
		}
		
		return $transactionSources;
	}
	
	function getAllCryptoCompareAssetTypes($dbh)
	{
		$assetTypes															= array();
		
		try
		{		
			$getAllAssetTypes												= $dbh -> prepare("SELECT
	AssetTypes.assetTypeID,
	AssetTypes.assetTypeLabel
FROM
	AssetTypes
WHERE
	AssetTypes.allowCryptoComparePricingPull = 1 AND
	assetTypeLabel != '1337'
ORDER BY
	assetTypeLabel");

			if ($getAllAssetTypes -> execute() && $getAllAssetTypes -> rowCount() > 0)
			{
				while ($row = $getAllAssetTypes -> fetchObject())
				{
					$assetTypeID											= $row -> assetTypeID;
					$assetTypeLabel											= $row -> assetTypeLabel;
				
					$assetTypes[$assetTypeID]								= $assetTypeLabel;	
				}			
			}
		}
	    catch (PDOException $e) 
	    {
	    		errorLog($e -> getMessage());
	
			die();
		}
		
		return $assetTypes;
	}
	
	function getAllCoinbaseAssetTypes($dbh)
	{
		$assetTypes															= array();
		
		try
		{		
			$getAllAssetTypes												= $dbh -> prepare("SELECT DISTINCT
	assetTypeID,
	assetTypeLabel
FROM
	AssetTypes
WHERE
	allowCoinbasePricingPull = 1
ORDER BY
	assetTypeLabel");

			if ($getAllAssetTypes -> execute() && $getAllAssetTypes -> rowCount() > 0)
			{
				while ($row = $getAllAssetTypes -> fetchObject())
				{
					$assetTypeID												= $row -> assetTypeID;
					$assetTypeLabel											= $row -> assetTypeLabel;
				
					$assetTypes[$assetTypeID]								= $assetTypeLabel;	
				}			
			}
		}
	    catch (PDOException $e) 
	    {
	    		errorLog($e -> getMessage());
	
			die();
		}
		
		return $assetTypes;
	}
	
	function getAllCoinGeckoIDsWithGenesisDate($dbh)
	{
		$assetTypes																						= array();
		
		try
		{	
			$getAllCoinGeckoCoinIDsSQL																	= "SELECT
	assetTypeID,
	coinGeckoID,
	genesisDate
FROM
	AssetTypes
WHERE
	coinGeckoID IS NOT NULL AND
	assetTypeID != 173 AND
	priorityImports = 1
ORDER BY
	coinGeckoID";	
					
			$getAllCoinGeckoCoinIDs																		= $dbh -> prepare($getAllCoinGeckoCoinIDsSQL);

			if ($getAllCoinGeckoCoinIDs -> execute() && $getAllCoinGeckoCoinIDs -> rowCount() > 0)
			{
				while ($row = $getAllCoinGeckoCoinIDs -> fetchObject())
				{
					$assetTypeID																		= $row -> assetTypeID;
					$coinGeckoID																		= $row -> coinGeckoID;
					$genesisDate																		= $row -> genesisDate;
				
					$assetDetailArray																	= array();
					
					$assetDetailArray['coinGeckoID']													= $coinGeckoID;
					$assetDetailArray['genesisDate']													= $genesisDate;
				
					$assetTypes[$assetTypeID]															= $assetDetailArray;	
				}			
			}
		}
	    catch (PDOException $e) 
	    {
	    	errorLog($e -> getMessage());
	
			die();
		}
		
		return $assetTypes;
	}
	
	function getAllCoinGeckoIDs($highPriorityOnly, $dbh)
	{
		$assetTypes															= array();
		
		$highPriorityOnly													= false;
		
		try
		{	
			$getAllCoinGeckoCoinIDsSQL										= "SELECT
	assetTypeID,
	coinGeckoID
FROM
	AssetTypes
WHERE
	coinGeckoID IS NOT NULL 
ORDER BY
	coinGeckoID";

				
/*
			$getAllCoinGeckoCoinIDsSQL										= "SELECT
	assetTypeID,
	coinGeckoID
FROM
	AssetTypes
WHERE
	coinGeckoID IS NOT NULL AND
	priorityImports = 1 AND
	genesisDate IS NOT NULL
ORDER BY
	coinGeckoID";
*/
	
			if ($highPriorityOnly == true)
			{
				$getAllCoinGeckoCoinIDsSQL									= "SELECT
	assetTypeID,
	coinGeckoID
FROM
	AssetTypes
WHERE
	coinGeckoID IS NOT NULL AND
	assetTypeID != 173 AND
	priorityImports = 1
ORDER BY
	coinGeckoID";	
			}
	
			/*
				
				if ($highPriorityOnly == true)
			{
				$getAllCoinGeckoCoinIDsSQL									= "SELECT
	assetTypeID,
	coinGeckoID
FROM
	AssetTypes
WHERE
	coinGeckoID IS NOT NULL AND
	assetTypeID != 173 AND
	allowListing = 3
ORDER BY
	coinGeckoID";	
			}
				
if ($highPriorityOnly == true)
			{
				$getAllCoinGeckoCoinIDsSQL									= "SELECT
	assetTypeID,
	coinGeckoID
FROM
	AssetTypes
WHERE
	coinGeckoID = 'vechain' AND
	assetTypeID != 173 AND
	priorityImports = 1
ORDER BY
	coinGeckoID";	
			}
*/
			
			$getAllCoinGeckoCoinIDs											= $dbh -> prepare($getAllCoinGeckoCoinIDsSQL);

			if ($getAllCoinGeckoCoinIDs -> execute() && $getAllCoinGeckoCoinIDs -> rowCount() > 0)
			{
				while ($row = $getAllCoinGeckoCoinIDs -> fetchObject())
				{
					$assetTypeID											= $row -> assetTypeID;
					$coinGeckoID											= $row -> coinGeckoID;
				
					$assetTypes[$assetTypeID]								= $coinGeckoID;	
				}			
			}
		}
	    catch (PDOException $e) 
	    {
	    		errorLog($e -> getMessage());
	
			die();
		}
		
		return $assetTypes;
	}
	
	function getPriority5CoinGeckoIDs($dbh)
	{
		$assetTypes															= array();
		
		try
		{		
			$getAllCoinGeckoCoinIDsSQL										= "SELECT
	assetTypeID,
	coinGeckoID
FROM
	AssetTypes
WHERE
	coinGeckoID IS NOT NULL AND
	priorityImports = 5
ORDER BY
	coinGeckoID";
			
			$getAllCoinGeckoCoinIDs											= $dbh -> prepare($getAllCoinGeckoCoinIDsSQL);

			if ($getAllCoinGeckoCoinIDs -> execute() && $getAllCoinGeckoCoinIDs -> rowCount() > 0)
			{
				while ($row = $getAllCoinGeckoCoinIDs -> fetchObject())
				{
					$assetTypeID											= $row -> assetTypeID;
					$coinGeckoID											= $row -> coinGeckoID;
				
					$assetTypes[$assetTypeID]								= $coinGeckoID;	
				}			
			}
		}
	    catch (PDOException $e) 
	    {
	    		errorLog($e -> getMessage());
	
			die();
		}
		
		return $assetTypes;
	}
	
	function getLastPriceDataDateForCryptoAsset($assetID, $fiatCurrencyAssetTypeID, $dataSourceID, $dbh)
	{
		$priceDate															= '2009-01-01';
		
		try
		{		
			$getLastPriceDataDateForCryptoAsset								= $dbh -> prepare("SELECT
	DailyCryptoSpotPrices.priceDate
FROM
	DailyCryptoSpotPrices
WHERE
	DailyCryptoSpotPrices.FK_CryptoAssetID = :assetTypeID AND
	DailyCryptoSpotPrices.FK_FiatCurrencyAssetID = :fiatCurrencyAssetTypeID AND
	DailyCryptoSpotPrices.FK_DataSource = :dataSourceID AND 
	DailyCryptoSpotPrices.fiatCurrencySpotPrice > 0
ORDER BY
	DailyCryptoSpotPrices.priceDate DESC
LIMIT 1");

			$getLastPriceDataDateForCryptoAsset -> bindValue(':assetTypeID', $assetID);
			$getLastPriceDataDateForCryptoAsset -> bindValue(':fiatCurrencyAssetTypeID', $fiatCurrencyAssetTypeID);
			$getLastPriceDataDateForCryptoAsset -> bindValue(':dataSourceID', $dataSourceID);

			if ($getLastPriceDataDateForCryptoAsset -> execute() && $getLastPriceDataDateForCryptoAsset -> rowCount() > 0)
			{
				$row 														= $getLastPriceDataDateForCryptoAsset -> fetchObject();
				
				$priceDate													= $row -> priceDate;			
			}
		}
	    catch (PDOException $e) 
	    {
	    	errorLog($e -> getMessage());
	
			die();
		}
		
		return $priceDate;
	}
	
	function getFirstPriceDataDateForCryptoAsset($assetID, $fiatCurrencyAssetTypeID, $dataSourceID, $dbh)
	{
		$priceDate															= '2009-01-01';
		
		try
		{		
			$getFirstPriceDataDateForCryptoAsset							= $dbh -> prepare("SELECT
	DailyCryptoSpotPrices.priceDate
FROM
	DailyCryptoSpotPrices
WHERE
	DailyCryptoSpotPrices.FK_CryptoAssetID = :assetTypeID AND
	DailyCryptoSpotPrices.FK_FiatCurrencyAssetID = :fiatCurrencyAssetTypeID AND
	DailyCryptoSpotPrices.FK_DataSource = :dataSourceID AND 
	DailyCryptoSpotPrices.fiatCurrencySpotPrice > 0
ORDER BY
	DailyCryptoSpotPrices.priceDate
LIMIT 1");

			$getFirstPriceDataDateForCryptoAsset -> bindValue(':assetTypeID', $assetID);
			$getFirstPriceDataDateForCryptoAsset -> bindValue(':fiatCurrencyAssetTypeID', $fiatCurrencyAssetTypeID);
			$getFirstPriceDataDateForCryptoAsset -> bindValue(':dataSourceID', $dataSourceID);

			if ($getFirstPriceDataDateForCryptoAsset -> execute() && $getFirstPriceDataDateForCryptoAsset -> rowCount() > 0)
			{
				$row 														= $getFirstPriceDataDateForCryptoAsset -> fetchObject();
				
				$priceDate													= $row -> priceDate;			
			}
		}
	    catch (PDOException $e) 
	    {
	    	errorLog($e -> getMessage());
	
			die();
		}
		
		return $priceDate;
	}
	
	// END TRANSACTION AND ASSET IDENTIFICATION FUNCTIONS
	
	// EXCHANGE VIEW FUNCTIONS
	
	function getLastDailyBalanceCalculationDateForUserAccountAndExchange($accountID, $transactionSourceID, $sid, $dbh)
	{
		$responseObject														= array();
		$responseObject['retrievedLastBalanceCalculationDate']				= false;
		
		try
		{		
			$getCalculationDate												= $dbh -> prepare("SELECT
	balanceDate
FROM
	DailyPortfolioBalanceForUserAccount
WHERE
	FK_AccountID = :accountID AND
	FK_TransactionSource = :transactionSourceID AND
	FK_AssetTypeID = 173
ORDER BY
	balanceDate DESC
LIMIT 1");

			$getCalculationDate -> bindValue(':accountID', $accountID);
			$getCalculationDate -> bindValue(':transactionSourceID', $transactionSourceID);
						
			if ($getCalculationDate -> execute() && $getCalculationDate -> rowCount() > 0)
			{
				
				$responseObject['retrievedLastBalanceCalculationDate']		= true;
				
				$row 														= $getCalculationDate -> fetchObject();
				
				$responseObject['balanceDate']								= $row -> balanceDate;						
			}
			else
			{
				$responseObject['resultMessage']							= "No balance date found for $accountID";
			}
		}
	    catch (PDOException $e) 
	    {
	    	$responseObject['resultMessage']								= "Could not retrieve balance date for $accountID due to a database error: ".$e -> getMessage();
			
			errorLog($e -> getMessage());
	
			die();
		}
	
		return $responseObject;	
	}
	
	function getTotalPortfolioValueForUserAccountAndExchange($accountID, $transactionSourceID, $balanceDate, $sid, $dbh)
	{
		$responseObject														= array();
		$responseObject['retrievedPortfolioValue']							= false;
		$responseObject['totalPortfolioValue']								= 0;
		
		try
		{		
			$getCryptoTransactionTotals										= $dbh -> prepare("SELECT
	assetPriceInNativeCurrencyForDateAsInt
FROM
	DailyPortfolioBalanceForUserAccount
WHERE
	FK_AccountID = :accountID AND
	FK_TransactionSource = :transactionSourceID AND
	FK_AssetTypeID = 173 AND
	balanceDate = :balanceDate
");

			$getCryptoTransactionTotals -> bindValue(':accountID', $accountID);
			$getCryptoTransactionTotals -> bindValue(':transactionSourceID', $transactionSourceID);
			$getCryptoTransactionTotals -> bindValue(':balanceDate', $balanceDate);
						
			if ($getCryptoTransactionTotals -> execute() && $getCryptoTransactionTotals -> rowCount() > 0)
			{
				
				$responseObject['retrievedPortfolioValue']					= true;
				
				$row 														= $getCryptoTransactionTotals -> fetchObject();
				
				$responseObject['totalPortfolioValue']						= $row -> assetPriceInNativeCurrencyForDateAsInt;						
			}
			else
			{
				$responseObject['resultMessage']								= "No total balance records found for $accountID";
			}
		}
	    catch (PDOException $e) 
	    {
	    		$responseObject['resultMessage']									= "Could not retrieve total balance records for $accountID due to a database error: ".$e -> getMessage();
			
			errorLog($e -> getMessage());
	
			die();
		}
	
		return $responseObject;	
	}
	
	function populateAssetArrayForExchangeOrWallet($assetName, $assetBalance, $colorCode)
	{
		$assetArray																= array();
		
		$assetArray['currencyName']												= $assetName;
		$assetArray['amountInUSD']												= $assetBalance;
		$assetArray['color']														= $colorCode;
		
		return $assetArray;
	}
	
	function populateAssetArrayForExchangeTile($assetName, $assetBalance, $colorCode)
	{
		$assetArray															= array();
		
		$assetArray['currencyName']											= $assetName;
		$assetArray['amountInFiatCurrency']									= $assetBalance;
		$assetArray['color']												= $colorCode;
		
		return $assetArray;
	}
	
	// END EXCHANGE VIEW FUNCTIONS
	
	// HLOC CALCULATION FUNCTIONS
	
	function getAllUniqueHLOCDates($dbh)
	{
		$uniqueHLOCDates								= array();
		
		try
		{		
			$getAllUniqueHLOCDates					= $dbh -> prepare("SELECT DISTINCT
	LEFT(spotPriceDate, 10) AS spotPriceDate
FROM
	btcSpotPrice
ORDER BY
	spotPriceDate");

			if ($getAllUniqueHLOCDates -> execute() && $getAllUniqueHLOCDates -> rowCount() > 0)
			{
				while ($row = $getAllUniqueHLOCDates -> fetchObject())
				{
					$spotPriceDate					= $row -> spotPriceDate;
				
					$uniqueHLOCDates[]				= $spotPriceDate;	
				}			
			}
		}
	    catch (PDOException $e) 
	    {
	    	errorLog($e -> getMessage());
	
			die();
		}
		
		return $uniqueHLOCDates;
	}
	
	function calculateSpotPriceForHLOCDates($spotPriceDate, $baseCurrencyID, $quoteCurrencyID, $sourceID, $globalCurrentDate, $sid, $dbh)
	{
		try
		{		
			$calculateSpotPriceForHLOCDates			= $dbh -> prepare("SELECT
	MAX(`open`) AS maxOpen,
	MAX(`high`) AS maxHigh,
	MIN(low) AS minLow,
	MIN(`close`) AS minClose,
	MAX(weightedPrice) AS maxWeightedPrice,
	(MAX(`open`) + MAX(`high`) + MIN(low) + MIN(`close`) + MAX(weightedPrice)) / 5 AS adjustedAverage
FROM
	btcSpotPrice
WHERE
	LEFT(spotPriceDate, 10) = :spotPriceDate");

			$calculateSpotPriceForHLOCDates -> bindValue(':spotPriceDate', $spotPriceDate);

			if ($calculateSpotPriceForHLOCDates -> execute() && $calculateSpotPriceForHLOCDates -> rowCount() > 0)
			{
				$row 								= $calculateSpotPriceForHLOCDates -> fetchObject();
				
				$adjustedAverage						= $row -> adjustedAverage;

				setDailyPriceData($baseCurrencyID, $quoteCurrencyID, $spotPriceDate, $adjustedAverage, $sourceID, $globalCurrentDate, $sid, $dbh);			
			}
		}
	    catch (PDOException $e) 
	    {
	    		errorLog($e -> getMessage());
	
			die();
		}
	}
	
	// END HLOC CALCULATION FUNCTIONS
	
	// VALUE OF ASSET, FEE, and FEE PERCENTAGE CALCULATIONS
	
	function calculateBuyPriceOfAssetUnitUsingRealBuyPriceOfAssetAndTransactedAmount($realBuyPriceOfAsset, $transactedAmount)
	{
		/*
		Calculation process	- Patent Specifics - Determining Cost Basis Inline Calculations v2 p 4
			
		Calculated Buy Price for Asset Unit = Real Buy Price of Asset / Transacted Amount
		
		Assuming Real Buy Price of Asset = 4768.8822860148 and Transacted Amount = 0.7452
		
		Calculated Buy Price for Asset Unit = 4768.8822860148 / 0.7452 = 6399.466299
		
		Note - we have to ensure that this process does not run if the transacted amount is zero
		*/	
		
		$calculatedBuyPriceOfAssetUnit		= 0;
		
		if ($transactedAmount > 0)
		{
			$calculatedBuyPriceOfAssetUnit	= $realBuyPriceOfAsset / $transactedAmount;
		}

		return $calculatedBuyPriceOfAssetUnit;	
	}
	
	function calculateBuyPriceOfAssetUnitFromOutboundTransactionUsingSpotPriceAndTransactedAmountAndFeePercentageExtendedCalculation($spotPrice, $transactedAmount, $feePercentage)
	{
		/*
		Calculation process	- Patent Specifics - Determining Cost Basis Inline Calculations v2 p 4
		
		This is a combination of the calculations for calculating buy and sell values described in that document.
			
		Calculated Sell Price for Asset Unit = Real Sell Price of Asset / Transacted BTC Amount
		
		In an Outgoing transaction, we do not know the cost basis.  For us to calculate a buy price, we must know the cost basis.  Thus, we will use the following formula to calculate the cost basis:
		
		Cost Basis For Outgoing Transaction			= Value of Asset Sold + Fee on Sell
		
		Symbolic names for equation terms:
		
			Calculated Sell Price for Asset Unit 	= CSp
			Value of Asset Sold						= VAs
			
			Real Buy Price of Asset 				= Rb
			Transacted Amount 						= Ta
			Fee on Purchase	(1)						= FOp1
			Fee on Purchase	(2)						= FOp2
			Fee Percentage							= Fp
			Spot Price								= APsp
			Cost Basis								= Cb
		
		And simplify using the following series of equation definitions:
			
			Value of Asset Sold = Spot Price * Transacted Amount
				VAs = APsp * Ta
				
			Fee on Sell = Value of Asset Sold * Fee Percentage
				FOs = VAs * Fp = (APsp * Ta) * Fp = APsp * Ta * Fp
				
			Cost Basis = Value of Asset Sold + Fee on Sell
				Cb = VAs + FOs = (APsp * Ta) + (APsp * Ta * Fp)
				
			IF
				APsp 								= 6745.00
				Ta									= 0.7452
				Fp									= 0.0149
				
				Cb = (6745.00 * 0.7452) + (6745.00 * 0.7452 * 0.0149)
					= 5026.374 + 74.8929726
					= 5101.2669726
				
			Now that we have a cost basis value, we continue on in the process of calculating the buy price	from the spot price, with one exception.  The Real buy price in this context is Cost Basis, rather than Cost Basis - Fee on Purchase
			
			Value of Asset Purchased = Spot Price * Tranacted Amount (this is the same as the Value of Asset Sold)
			Fee on Purchase = Cost Basis - Value of Asset Purchased
				FOp = (APsp * Ta) + (APsp * Ta * Fp) - (APsp * Ta)
				FOp = (APsp * Ta * Fp)
			
			Real Buy Price of Asset	= Cost Basis
			
			Calculated Buy Price = Cost Basis / Transacted BTC Amount
			
			CBp = (APsp * Ta) + (APsp * Ta * Fp) / Ta
			
			CBp = ((6745.00 * 0.7452) + (6745.00 * 0.7452 * 0.0149)) / 0.7452
			
			CBp = (5026.374 + 74.8929726) / 0.7452
			
			CBp = 5101.2669726 / 0.7452
				
			CBp = 6845.5005
			
					
		Note - we have to ensure that this process does not run if the transacted amount is zero
		*/	
		
		$calculatedBuyPriceOfAssetUnit		= 0;
		
		if ($feePercentage >= 1)
		{
			$feePercentage 					= $feePercentage / 100;
		}
		
		if ($transactedAmount > 0)
		{
			$calculatedBuyPriceOfAssetUnit	= (($spotPrice * $transactedAmount) + ($spotPrice * $transactedAmount * $feePercentage)) / $transactedAmount;
		}

		return $calculatedBuyPriceOfAssetUnit;	
	}
	
	function calculateGainOrLossUsingAmountRealizedAndCostBasis($amountRealized, $costBasis)
	{
		/*
		Calculation process	- Patent Specifics - Determining Cost Basis Inline Calculations v2 p 3
			
		Gain or Loss Amount = Amount Realized - Cost Basis
		
		Assuming amount realized = $4,898.4572357604 and cost basis = 4839.2229428496, 
		
		Gain or Loss Amount = 4898.4572357604 - 4839.2229428496 = 59.234292910799
		*/	
		
		$gainOrLossAmount				= $amountRealized - $costBasis;

		return $gainOrLossAmount;	
	}
	
	function calculateRealBuyPriceOfAssetUsingCostBasisAndFeeOnPurchase($costBasis, $feeOnPurchase)
	{
		/*
		Calculation process	- Patent Specifics - Determining Cost Basis Inline Calculations v2 p 4
			
		Real Buy Price of Asset = Cost Basis - Fee on Purchase
		
		Assuming Cost Basis = 4839.2229428496 and Fee on Purchase = 70.3406568348
		
		Real Buy Price of Asset = 4839.2229428496 - 70.3406568348 = 4768.8822860148
		*/	
		
		$realBuyPriceOfAsset				= $costBasis - $feeOnPurchase;

		return $realBuyPriceOfAsset;	
	}
	
	function calculateRealSellPriceOfAssetUsingValueOfAssetSoldAndFeeOnSell($valueOfAssetSold, $feeOnSell)
	{
		/*
		Calculation process	- Patent Specifics - Determining Cost Basis Inline Calculations v2 p 4
			
		Real Sell Price of Asset = Value of Asset Sold - Fee on Sell
		
		Assuming Value of Asset Sold = 5026.374 and Fee on Sell = 74.8929726
		
		Real Sell Price of Asset = 5026.374 - 74.8929726 = 4951.4810274
		*/	
		
		$realSellPriceOfAsset				= $valueOfAssetSold - $feeOnSell;

		return $realSellPriceOfAsset;	
	}
	
	function calculateSellPriceOfAssetUnitUsingRealSellPriceOfAssetAndTransactedAmount($realSellPriceOfAsset, $transactedAmount)
	{
		/*
		Calculation process	- Patent Specifics - Determining Cost Basis Inline Calculations v2 p 4
			
		Calculated Sell Price for Asset Unit = Real Sell Price of Asset / Transacted Amount
		
		Assuming Real Sell Price of Asset = 4951.4810274 and Transacted Amount = 0.7452
		
		Calculated Sell Price for Asset Unit = 4951.4810274 / 0.7452 = 6644.4995
		
		Note - we have to ensure that this process does not run if the transacted amount is zero
		*/	
		
		$calculatedSellPriceOfAssetUnit		= 0;
		
		if ($transactedAmount > 0)
		{
			$calculatedSellPriceOfAssetUnit	= $realSellPriceOfAsset / $transactedAmount;
		}

		return $calculatedSellPriceOfAssetUnit;	
	}
	
	function calculateSellPriceOfAssetUnitUsingSpotPriceAndTransactedAmountAndFeePercentageExtendedCalculation($spotPrice, $transactedAmount, $feePercentage)
	{
		/*
		Calculation process	- Patent Specifics - Determining Cost Basis Inline Calculations v2 p 4
		
		This is a simplification of the calculation described in that document.  Take the equation for Calculated Sell Price for Asset Unit as described below:
			
		Calculated Sell Price for Asset Unit = Real Sell Price of Asset / Transacted Amount
		
		Symbolic names for equation terms:
		
			Calculated Sell Price for Asset Unit 	= CSp
			Value of Asset Sold						= VAs
			Real Sell Price of Asset 				= Rs
			Transacted Amount 						= Ta
			Fee on Sell								= FOs
			Fee Percentage							= Fp
			Spot Price								= APsp
		
		And simplify using the following series of equation definitions:
			
			Value of Asset Sold = Spot Price * Transacted Amount
				VAs = APsp * Ta
				
			Fee on Sell = Value of Asset Sold * Fee Percentage
				FOs = VAs * Fp = (APsp * Ta) * Fp = APsp * Ta * Fp 
			
			Real Sell Price of Asset = Value of Asset Sold - Fee on Sell
				Rs = VAs - FOs = (APsp * Ta) - (APsp * Ta * Fp)
			
			Calculated Sell Price for Asset Unit = Real Sell Price of Asset / Transacted Amount
				CSp = Rs / Ta = (VAs - FOs) / Ta = ((APsp * Ta) - (APsp * Ta * Fp)) / Ta
			
		Thus we can calculate the sell price for an asset unit using only the Spot Price, Transacted Amount, and the Fee Percentage
		
		IF
			Spot Price								= 6335.01000000
			Transacted Amount 						= 0.7452
			Fee Percentage							= 1.49
			
		Calculated Sell Price for Asset Unit 		= ((6335.01000000 * 0.7452) - (6335.01000000 * 0.7452 * 0.0149)) / 0.7452 
														= (4720.849452 - 70.3406568348) / 0.7452
														= 4650.5087951652 / 0.7452 
														= 6240.618351
														
		IF
			Spot Price								= 4951.4810274
			Transacted Amount 						= 0.7452
			Fee Percentage							= 1.49
			
		Calculated Sell Price for Asset Unit 		= ((6745.00 * 0.7452) - (6745.00 * 0.7452 * 0.0149)) / 0.7452 
														= (5026.374 - 74.8929726) / 0.7452
														= 4951.4810274 / 0.7452 
														= 6644.4995
		
		Note - we have to ensure that this process does not run if the transacted amount is zero
		*/	
		
		$calculatedSellPriceOfAssetUnit		= 0;
		
		if ($feePercentage >= 1)
		{
			$feePercentage 					= $feePercentage / 100;
		}
		
		if ($transactedAmount > 0)
		{
			$calculatedSellPriceOfAssetUnit	= (($spotPrice * $transactedAmount) - ($spotPrice * $transactedAmount * $feePercentage)) / $transactedAmount;
		}

		return $calculatedSellPriceOfAssetUnit;	
	}
	
	function calculateTaxObligationForTransactionUsingGainAndTaxPercentageForGainType($gainAmount, $taxPercentageForGainType)
	{
		/*
		Calculation process	- Patent Specifics - Determining Cost Basis Inline Calculations v2 p 3
			
		Tax obligation for transaction = Gain Amount * Tax Percentage for Gain Type
		
		Assuming Gain Amount = 59.234292910799 and Tax Percentage for Gain Type = 20%
		
		Gain Amount = 59.234292910799 * 0.2 = 11.8468585821598
		
		Note - the $taxPercentageForGainType should be expressed as a value which is less than 1.  Thus, a 20% fee should be represented as 0.2.  I need to include code to adjust the $taxPercentageForGainType accordingly, if it is greater than or equal to 1.
		*/	
		
		if ($taxPercentageForGainType >= 1)
		{
			$taxPercentageForGainType 	= $taxPercentageForGainType / 100;
		}
		
		$taxObligationForTransaction	= $gainAmount * $taxPercentageForGainType;

		return $taxObligationForTransaction;	
	}
	
	function calculateTotalDaysAssetHeld($outboundTransactionDateTime, $inboundTransactionDateTime)
	{
		/*
		Calculation process	- Patent Specifics - Determining Cost Basis Inline Calculations v2 p 3
			
		Total days asset held = Sale Transaction Date - Purchase Transaction Date
		
		Assuming that the assets were purchased on June 30, 2018, and sold on July 7, 2018, the Total days asset held would be 8 days
		
		Note that dates must be in DateTime object format for this code to work, and that the data returned is a DateTimeInterval object
		*/	
		
		$saleTransactionDateObject		= new DateTime($outboundTransactionDateTime);
		$purchaseTransactionDateObject	= new DateTime($inboundTransactionDateTime);
		
		$totalDaysAssetHeld				= $saleTransactionDateObject -> diff($purchaseTransactionDateObject);

		return $totalDaysAssetHeld;
	}
	
	function calculateValueOfAssetPurchasedUsingCryptoCurrencyAcquistionPriceAndTransactedAmount($cryptoCurrencyAcquisitionPrice, $transactedAmount)
	{
		/*
		Calculation process	- Patent Specifics - Determining Cost Basis Inline Calculations v2 p 3
			
		Value of Asset Purchased = Buy Price * Transacted Amount
		
		Assuming that the buy price in the native currency for Bitcoin is $6,398.52, and the user purchases 0.7452 BTC, the value of asset purchased = 6398.52 * 0.7452 = 4768.177104
			
		*/
		
		$valueOfAssetPurchased		= $cryptoCurrencyAcquisitionPrice * $transactedAmount;
		
		return $valueOfAssetPurchased; 	
	}
	
	function calculateValueOfAssetSoldUsingCryptoCurrencySellPriceAndTransactedAmount($cryptoCurrencySellPrice, $transactedAmount)
	{
		/*
		Calculation process	- Patent Specifics - Determining Cost Basis Inline Calculations v2 p 3
			
		Value of Asset Sold = Sell Price * Transacted Amount
		
		Assuming that the sell price in the native currency for Bitcoin is $6672.77000000, and the user sells 0.7452 BTC, the value of asset sold = 6672.77000000 * 0.7452 = 4972.548204
			
		*/
		
		$valueOfAssetSold			= $cryptoCurrencySellPrice * $transactedAmount;
		
		return $valueOfAssetSold;	
	}

	function isLossOrShortOrLongTermGainUsingGainOrLossAndNumberOfDaysInShortTermAndTotalDaysAssetHeld($gainOrLossAmount, $numberOfDaysInShortTerm, $totalDaysAssetHeldTimeInterval)
	{
		/*
		Calculation process	- Patent Specifics - Determining Cost Basis Inline Calculations v2 p 3
			
		to determine Loss, Short or long-term gain 
			The transaction is a loss if the $gainOrLossAmount < 0
			If the transaction is a gain
				The gain is short term if the $totalDaysAssetHeld <= $numberOfDaysInShortTerm
				The gain is long term if the $totalDaysAssetHeld > $numberOfDaysInShortTerm
		
		Enum values for $shortOrLongTermGain
			$shortOrLongTermGain = 1 == Loss
			$shortOrLongTermGain = 2 == Short-term gain
			$shortOrLongTermGain = 3 == Long-term gain
		
		Assuming gain or loss amount = 59.234292910799, the number of days in short term is 365, anbd the total days the asset was held is 8
			The transaction is a short-term gain ($shortOrLongTermGain == 2)
			
			the number of days in short term comes from the tax authority region ID
		*/	
		
		$totalDaysAssetHeld					= $totalDaysAssetHeldTimeInterval -> days;
		
		$shortOrLongTermGainTypeID			= -1;
		
		if ($gainOrLossAmount < 0)
		{
			$shortOrLongTermGainTypeID		= 1;	
		}
		else if ($totalDaysAssetHeld <= $numberOfDaysInShortTerm)
		{
			$shortOrLongTermGainTypeID		= 2;	
		}
		else if ($totalDaysAssetHeld > $numberOfDaysInShortTerm)
		{
			$shortOrLongTermGainTypeID		= 3;	
		}

		return $shortOrLongTermGainTypeID;		
	}
	
	function calculateAllFifoValuesAndFeesForTransaction($accountID, $authorID, $costBasis, $amountRealized, $inboundTransactionID, $inboundTransactionDateTime, $inboundCryptoCurrencyAssetTypeID, $inboundFiatCurrencyAssetTypeID, $inboundTransactedAmount, $inboundCryptoCurrencyBuyPrice, $inboundCryptoCurrencySellPrice, $inboundCryptoCurrencySpotPrice, $inboundExchangeID, $declaredInboundFeePercentage, $declaredInboundFeeAmountInFiatCurrency, $declaredInboundFeeAmountInCryptoCurrency, $outboundTransactionID, $outboundTransactionDateTime, $outboundCryptoCurrencyAssetTypeID, $outboundFiatCurrencyAssetTypeID, $outboundTransactedAmount, $outboundCryptoCurrencyBuyPrice, $outboundCryptoCurrencySellPrice, $outboundCryptoCurrencySpotPrice,  $outboundExchangeID, $declaredOutboundFeePercentage, $declaredOutboundFeeAmountInFiatCurrency, $declaredOutboundFeeAmountInCryptoCurrency, $numberOfDaysInShortTerm, $shortTermGainTaxPercentage, $longTermGainTaxPercentage, $globalCurrentDate, $sid, $dbh)
	{
		$totalDaysAssetHeldTimeInterval					= calculateTotalDaysAssetHeld($outboundTransactionDateTime, $inboundTransactionDateTime);
		
		$gainOrLossAmount								= calculateGainOrLossUsingAmountRealizedAndCostBasis($amountRealized, $costBasis);
		
		$shortOrLongTermGainTypeID						= isLossOrShortOrLongTermGainUsingGainOrLossAndNumberOfDaysInShortTermAndTotalDaysAssetHeld($gainOrLossAmount, $numberOfDaysInShortTerm, $totalDaysAssetHeldTimeInterval);
		
		$taxObligationForTransaction					= 0;
		
		if ($shortOrLongTermGainTypeID == 2)
		{
			// short term gain
			$taxObligationForTransaction				= calculateTaxObligationForTransactionUsingGainAndTaxPercentageForGainType($gainOrLossAmount, $shortTermGainTaxPercentage);
		}
		else if ($shortOrLongTermGainTypeID == 3)
		{
			// long term gain
			$taxObligationForTransaction				= calculateTaxObligationForTransactionUsingGainAndTaxPercentageForGainType($gainOrLossAmount, $longTermGainTaxPercentage);
		}
		
		// create and populate crypto exchange value class objects
		
		$inboundCryptoExchangeSpotPriceValue														= new CryptoExchangeValue();
		$inboundCryptoExchangeBuyPriceValue															= new CryptoExchangeValue();
		$inboundCryptoExchangeSellPriceValue														= new CryptoExchangeValue();
		
		$outboundCryptoExchangeSpotPriceValue														= new CryptoExchangeValue();
		$outboundCryptoExchangeBuyPriceValue														= new CryptoExchangeValue();
		$outboundCryptoExchangeSellPriceValue														= new CryptoExchangeValue();
		
		if ($inboundCryptoCurrencySpotPrice > 0)
		{
			$inboundCryptoExchangeSpotPriceValue -> setData($accountID, $inboundTransactionID, $inboundTransactionDateTime, $inboundCryptoCurrencyAssetTypeID, $inboundFiatCurrencyAssetTypeID, $inboundCryptoCurrencySpotPrice, $inboundExchangeID, 2, 1, $globalCurrentDate, $sid, $dbh);
		}
		
		if ($inboundCryptoCurrencyBuyPrice > 0)
		{
			$inboundCryptoExchangeBuyPriceValue -> setData($accountID, $inboundTransactionID, $inboundTransactionDateTime, $inboundCryptoCurrencyAssetTypeID, $inboundFiatCurrencyAssetTypeID, $inboundCryptoCurrencyBuyPrice, $inboundExchangeID, 1, 1, $globalCurrentDate, $sid, $dbh);
		}
		
		if ($inboundCryptoCurrencySellPrice > 0)
		{
			$inboundCryptoExchangeSellPriceValue -> setData($accountID, $inboundTransactionID, $inboundTransactionDateTime, $inboundCryptoCurrencyAssetTypeID, $inboundFiatCurrencyAssetTypeID, $inboundCryptoCurrencySellPrice, $inboundExchangeID, 3, 1, $globalCurrentDate, $sid, $dbh);
		}
		
		if ($outboundCryptoCurrencySpotPrice > 0)
		{
			$outboundCryptoExchangeSpotPriceValue -> setData($accountID, $outboundTransactionID, $outboundTransactionDateTime, $outboundCryptoCurrencyAssetTypeID, $outboundFiatCurrencyAssetTypeID, $outboundCryptoCurrencySpotPrice, $outboundExchangeID, 2, 1, $globalCurrentDate, $sid, $dbh);
		}
		
		if ($outboundCryptoCurrencyBuyPrice > 0)
		{
			$outboundCryptoExchangeBuyPriceValue -> setData($accountID, $outboundTransactionID, $outboundTransactionDateTime, $outboundCryptoCurrencyAssetTypeID, $outboundFiatCurrencyAssetTypeID, $outboundCryptoCurrencyBuyPrice, $outboundExchangeID, 1, 1, $globalCurrentDate, $sid, $dbh);
		}
		
		if ($outboundCryptoCurrencySellPrice > 0)
		{
			$outboundCryptoExchangeSellPriceValue -> setData($accountID, $outboundTransactionID, $outboundTransactionDateTime, $outboundCryptoCurrencyAssetTypeID, $outboundFiatCurrencyAssetTypeID, $outboundCryptoCurrencySellPrice, $outboundExchangeID, 3, 1, $globalCurrentDate, $sid, $dbh);
		}

		// we should always have the spot price for any transaction.  Do the calculations based on spot price first.
		
		$valueOfAssetPurchasedCalculationBasisID														= 2;	// 1 = buy price, 2 = spot price, 3 = sell price, 4 = calculated buy price
		
		$valueOfAssetPurchasedUsingBuyPrice																= 0;
		$valueOfAssetPurchasedUsingSpotPrice															= 0;
		$valueOfAssetPurchasedUsingSellPrice															= 0;
		
		$inboundFeeFromBuyPrice																		= new CryptoFeeObject();
		$inboundFeeFromSpotPrice																		= new CryptoFeeObject();
		$inboundFeeFromSellPrice																		= new CryptoFeeObject();
		
		$realBuyPriceOfAsset																			= 0;
		$calculatedBuyPriceOfAssetUnit																	= 0;
		
		if ($inboundCryptoExchangeBuyPriceValue -> getIsSet() == true)
		{
			$valueOfAssetPurchasedUsingBuyPrice															= calculateValueOfAssetPurchasedUsingCryptoCurrencyAcquistionPriceAndTransactedAmount($inboundCryptoExchangeBuyPriceValue -> getExchangePriceValue(), $inboundTransactedAmount);
			
			$valueOfAssetPurchasedCalculationBasisID													= 1;
			
			$inboundFeeFromBuyPrice -> setData($accountID, $inboundTransactionID, $valueOfAssetPurchasedUsingBuyPrice, $costBasis, 1, $declaredInboundFeeAmountInCryptoCurrency, $declaredInboundFeeAmountInFiatCurrency, $declaredInboundFeePercentage, $inboundCryptoCurrencyAssetTypeID, $inboundFiatCurrencyAssetTypeID, 1, $inboundCryptoExchangeBuyPriceValue, $dbh);
			
			$realBuyPriceOfAsset																		= calculateRealBuyPriceOfAssetUsingCostBasisAndFeeOnPurchase($costBasis, $inboundFeeFromBuyPrice -> getFeeAmountInFiatCurrency());
			
			$calculatedBuyPriceOfAssetUnit																= calculateBuyPriceOfAssetUnitUsingRealBuyPriceOfAssetAndTransactedAmount($realBuyPriceOfAsset, $inboundTransactedAmount);	
		}
		
		if ($inboundCryptoExchangeSellPriceValue -> getIsSet() == true)
		{
			$valueOfAssetPurchasedUsingSellPrice														= calculateValueOfAssetPurchasedUsingCryptoCurrencyAcquistionPriceAndTransactedAmount($inboundCryptoExchangeSellPriceValue -> getExchangePriceValue(), $inboundTransactedAmount);
			
			if ($valueOfAssetPurchasedCalculationBasisID == 0)
			{
				$valueOfAssetPurchasedCalculationBasisID												= 3;	
			}
			
			$inboundFeeFromSellPrice -> setData($accountID, $inboundTransactionID, $valueOfAssetPurchasedUsingSellPrice, $costBasis, 1, $declaredInboundFeeAmountInCryptoCurrency, $declaredInboundFeeAmountInFiatCurrency, $declaredInboundFeePercentage, $inboundCryptoCurrencyAssetTypeID, $inboundFiatCurrencyAssetTypeID, 3, $inboundCryptoExchangeSellPriceValue, $dbh);	
		}
		
		if ($inboundCryptoExchangeSpotPriceValue -> getIsSet() == true)
		{
			$valueOfAssetPurchasedUsingSpotPrice														= calculateValueOfAssetPurchasedUsingCryptoCurrencyAcquistionPriceAndTransactedAmount($inboundCryptoExchangeSpotPriceValue -> getExchangePriceValue(), $inboundTransactedAmount);	
			
			$inboundFeeFromSpotPrice -> setData($accountID, $inboundTransactionID, $valueOfAssetPurchasedUsingSpotPrice, $costBasis, 1, $declaredInboundFeeAmountInCryptoCurrency, $declaredInboundFeeAmountInFiatCurrency, $declaredInboundFeePercentage, $inboundCryptoCurrencyAssetTypeID, $inboundFiatCurrencyAssetTypeID, 2, $inboundCryptoExchangeSpotPriceValue, $dbh);
			
			if ($inboundCryptoExchangeBuyPriceValue -> getIsSet() == false)
			{
				$realBuyPriceOfAsset																	= calculateRealBuyPriceOfAssetUsingCostBasisAndFeeOnPurchase($costBasis, $inboundFeeFromSpotPrice -> getFeeAmountInFiatCurrency());	
				
				$calculatedBuyPriceOfAssetUnit															= calculateBuyPriceOfAssetUnitUsingRealBuyPriceOfAssetAndTransactedAmount($realBuyPriceOfAsset, $inboundTransactedAmount);
				
				$inboundCryptoExchangeBuyPriceValue -> setData($accountID, $inboundTransactionID, $inboundTransactionDateTime, $inboundCryptoCurrencyAssetTypeID, $inboundFiatCurrencyAssetTypeID, $calculatedBuyPriceOfAssetUnit, $inboundExchangeID, 1, 2, $globalCurrentDate, $sid, $dbh);
				
				$valueOfAssetPurchasedUsingBuyPrice														= calculateValueOfAssetPurchasedUsingCryptoCurrencyAcquistionPriceAndTransactedAmount($inboundCryptoExchangeBuyPriceValue -> getExchangePriceValue(), $inboundTransactedAmount);
				
				$valueOfAssetPurchasedCalculationBasisID												= 3;
				
				$inboundFeeFromBuyPrice -> setData($accountID, $inboundTransactionID, $valueOfAssetPurchasedUsingBuyPrice, $costBasis, 1, $declaredInboundFeeAmountInCryptoCurrency, $declaredInboundFeeAmountInFiatCurrency, $declaredInboundFeePercentage, $inboundCryptoCurrencyAssetTypeID, $inboundFiatCurrencyAssetTypeID, 1, $inboundCryptoExchangeBuyPriceValue, $dbh);
			}
			
			if ($inboundCryptoExchangeSellPriceValue -> getIsSet() == false)
			{
				$calculatedSellPriceOfAssetUnitForInboundTransaction									= calculateSellPriceOfAssetUnitUsingSpotPriceAndTransactedAmountAndFeePercentageExtendedCalculation($inboundCryptoExchangeSpotPriceValue -> getExchangePriceValue(), $inboundTransactedAmount, $inboundFeeFromBuyPrice -> getFeePercentage());
				
				$valueOfAssetPurchasedUsingSellPrice													= calculateValueOfAssetPurchasedUsingCryptoCurrencyAcquistionPriceAndTransactedAmount($calculatedSellPriceOfAssetUnitForInboundTransaction, $inboundTransactedAmount);
				
				$inboundCryptoExchangeSellPriceValue -> setData($accountID, $inboundTransactionID, $inboundTransactionDateTime, $inboundCryptoCurrencyAssetTypeID, $inboundFiatCurrencyAssetTypeID, $calculatedSellPriceOfAssetUnitForInboundTransaction, $inboundExchangeID, 3, 2, $globalCurrentDate, $sid, $dbh);
				
				$inboundFeeFromSellPrice -> setData($accountID, $inboundTransactionID, $valueOfAssetPurchasedUsingSellPrice, $costBasis, 1, $declaredInboundFeeAmountInCryptoCurrency, $declaredInboundFeeAmountInFiatCurrency, $declaredInboundFeePercentage, $inboundCryptoCurrencyAssetTypeID, $inboundFiatCurrencyAssetTypeID, 3, $inboundCryptoExchangeSellPriceValue, $dbh);
			}	
		}
				
		$valueOfAssetSoldCalculationBasisID																= 2;	// 1 = sell price, 2 = spot price, 3 = calculated sell price
		
		$valueOfAssetSoldUsingSellPrice																	= 0;
		$valueOfAssetSoldUsingSpotPrice																	= 0;
		
		$outboundFeeFromSellPrice																		= new CryptoFeeObject();
		$outboundFeeFromSpotPrice																		= new CryptoFeeObject();
		$outboundFeeFromBuyPrice																		= new CryptoFeeObject();
				
		$realSellPriceOfAsset																			= 0;
		$calculatedSellPriceOfAssetUnit																	= 0;
		
		if ($outboundCryptoExchangeSellPriceValue -> getIsSet() == true)
		{
			$valueOfAssetSoldUsingSellPrice																= calculateValueOfAssetSoldUsingCryptoCurrencySellPriceAndTransactedAmount($outboundCryptoExchangeSellPriceValue -> getExchangePriceValue(), $outboundTransactedAmount);
			
			$valueOfAssetSoldCalculationBasisID															= 1;
			
			$outboundFeeFromSellPrice -> setData($accountID, $outboundTransactionID, $valueOfAssetSoldUsingSellPrice, $amountRealized, 2, $declaredOutboundFeeAmountInCryptoCurrency, $declaredOutboundFeeAmountInFiatCurrency, $declaredOutboundFeePercentage, $outboundCryptoCurrencyAssetTypeID, $outboundFiatCurrencyAssetTypeID, 3, $outboundCryptoExchangeSellPriceValue, $dbh);
			
			$realSellPriceOfAsset																		= calculateRealSellPriceOfAssetUsingValueOfAssetSoldAndFeeOnSell($valueOfAssetSoldUsingSellPrice, $outboundFeeFromSellPrice -> getFeeAmountInFiatCurrency());
			
			$calculatedSellPriceOfAssetUnit																= calculateSellPriceOfAssetUnitUsingRealSellPriceOfAssetAndTransactedAmount($realSellPriceOfAsset, $outboundTransactedAmount);
		}
		
		if ($outboundCryptoExchangeBuyPriceValue -> getIsSet() == true)
		{
			$valueOfAssetSoldUsingBuyPrice																= calculateValueOfAssetSoldUsingCryptoCurrencySellPriceAndTransactedAmount($outboundCryptoExchangeBuyPriceValue -> getExchangePriceValue(), $outboundTransactedAmount);
			
			if ($valueOfAssetSoldCalculationBasisID == 0)
			{
				$valueOfAssetSoldCalculationBasisID														= 1;	
			}

			$outboundFeeFromBuyPrice -> setData($accountID, $outboundTransactionID, $valueOfAssetSoldUsingBuyPrice, $amountRealized, 2, $declaredOutboundFeeAmountInCryptoCurrency, $declaredOutboundFeeAmountInFiatCurrency, $declaredOutboundFeePercentage, $outboundCryptoCurrencyAssetTypeID, $outboundFiatCurrencyAssetTypeID, 1, $outboundCryptoExchangeBuyPriceValue, $dbh);
		}
		
		if ($outboundCryptoExchangeSpotPriceValue -> getIsSet() == true)
		{
			$valueOfAssetSoldUsingSpotPrice																= calculateValueOfAssetSoldUsingCryptoCurrencySellPriceAndTransactedAmount($outboundCryptoExchangeSpotPriceValue -> getExchangePriceValue(), $outboundTransactedAmount);
			
			$outboundFeeFromSpotPrice -> setData($accountID, $outboundTransactionID, $valueOfAssetSoldUsingSpotPrice, $amountRealized, 2, $declaredOutboundFeeAmountInCryptoCurrency, $declaredOutboundFeeAmountInFiatCurrency, $declaredOutboundFeePercentage, $outboundCryptoCurrencyAssetTypeID, $outboundFiatCurrencyAssetTypeID, 2, $outboundCryptoExchangeSpotPriceValue, $dbh);
			
			if ($outboundCryptoExchangeSellPriceValue -> getIsSet() == false)
			{
				$realSellPriceOfAsset																	= calculateRealSellPriceOfAssetUsingValueOfAssetSoldAndFeeOnSell($valueOfAssetSoldUsingSpotPrice, $outboundFeeFromSpotPrice -> getFeeAmountInFiatCurrency());
				
				$calculatedSellPriceOfAssetUnit															= calculateBuyPriceOfAssetUnitUsingRealBuyPriceOfAssetAndTransactedAmount($realSellPriceOfAsset, $outboundTransactedAmount);
				
				$outboundCryptoExchangeSellPriceValue -> setData($accountID, $outboundTransactionID, $outboundTransactionDateTime, $outboundCryptoCurrencyAssetTypeID, $outboundFiatCurrencyAssetTypeID, $calculatedSellPriceOfAssetUnit, $outboundExchangeID, 3, 2, $globalCurrentDate, $sid, $dbh);
				
				$valueOfAssetSoldUsingSellPrice															= calculateValueOfAssetSoldUsingCryptoCurrencySellPriceAndTransactedAmount($outboundCryptoExchangeSellPriceValue -> getExchangePriceValue(), $outboundTransactedAmount);
				
				$valueOfAssetSoldCalculationBasisID														= 3;
				
				$outboundFeeFromSellPrice -> setData($accountID, $outboundTransactionID, $valueOfAssetSoldUsingSellPrice, $amountRealized, 2, $declaredOutboundFeeAmountInCryptoCurrency, $declaredOutboundFeeAmountInFiatCurrency, $declaredOutboundFeePercentage, $outboundCryptoCurrencyAssetTypeID, $outboundFiatCurrencyAssetTypeID, 3, $outboundCryptoExchangeSellPriceValue, $dbh);
			}
			
			if ($outboundCryptoExchangeBuyPriceValue -> getIsSet() == false)
			{
				$calculatedBuyPriceOfAssetUnitForOutboundTransaction									= calculateBuyPriceOfAssetUnitFromOutboundTransactionUsingSpotPriceAndTransactedAmountAndFeePercentageExtendedCalculation($outboundCryptoExchangeSpotPriceValue -> getExchangePriceValue(), $outboundTransactedAmount, $outboundFeeFromSellPrice -> getFeePercentage());
				
				$valueOfAssetSoldUsingBuyPrice															= calculateValueOfAssetSoldUsingCryptoCurrencySellPriceAndTransactedAmount($calculatedBuyPriceOfAssetUnitForOutboundTransaction, $outboundTransactedAmount);
				
				$outboundCryptoExchangeBuyPriceValue -> setData($accountID, $outboundTransactionID, $outboundTransactionDateTime, $outboundCryptoCurrencyAssetTypeID, $outboundFiatCurrencyAssetTypeID, $calculatedBuyPriceOfAssetUnitForOutboundTransaction, $outboundExchangeID, 1, 2, $globalCurrentDate, $sid, $dbh);
				
				$outboundFeeFromBuyPrice -> setData($accountID, $outboundTransactionID, $valueOfAssetSoldUsingBuyPrice, $amountRealized, 2, $declaredOutboundFeeAmountInCryptoCurrency, $declaredOutboundFeeAmountInFiatCurrency, $declaredOutboundFeePercentage, $outboundCryptoCurrencyAssetTypeID, $outboundFiatCurrencyAssetTypeID, 1, $outboundCryptoExchangeBuyPriceValue, $dbh);
			}
		}
		
		errorLog("Scenario 1: Declared Information");
		errorLog("COST BASIS: $costBasis");	
		errorLog("AMOUNT REALIZED: $amountRealized");
		
		errorLog("Inbound transaction ID: $inboundTransactionID");	
		errorLog("Inbound transaction date time: $inboundTransactionDateTime");
		errorLog("Inbound Crypto Currency Asset Type ID: $inboundCryptoCurrencyAssetTypeID");	
		errorLog("Inbound Fiat Currency Asset Type ID: $inboundFiatCurrencyAssetTypeID");
		errorLog("Inbound Transacted Amount: $inboundTransactedAmount");	
		errorLog("Inbound Crypto Currency Buy Price: $inboundCryptoCurrencyBuyPrice");
		errorLog("Inbound Crypto Currency Sell Price: $inboundCryptoCurrencySellPrice");
		errorLog("Inbound Crypto Currency Spot Price: $inboundCryptoCurrencySpotPrice");
		errorLog("Inbound Declared Fee Percentage: $declaredInboundFeePercentage");
		errorLog("Inbound Declared Fee Amount In Fiat Currency: $declaredInboundFeeAmountInFiatCurrency");
		errorLog("Inbound Declared Fee Amount In Crypto Currency: $declaredInboundFeeAmountInCryptoCurrency");

		errorLog("Outbound transaction ID: $outboundTransactionID");	
		errorLog("Outbound transaction date time: $outboundTransactionDateTime");
		errorLog("Outbound Crypto Currency Asset Type ID: $outboundCryptoCurrencyAssetTypeID");	
		errorLog("Outbound Fiat Currency Asset Type ID: $outboundFiatCurrencyAssetTypeID");
		errorLog("Outbound Transacted Amount: $outboundTransactedAmount");	
		errorLog("Outbound Crypto Currency Buy Price: $outboundCryptoCurrencyBuyPrice");
		errorLog("Outbound Crypto Currency Sell Price: $outboundCryptoCurrencySellPrice");
		errorLog("Outbound Crypto Currency Spot Price: $outboundCryptoCurrencySpotPrice");
		errorLog("Outbound Declared Fee Amount In Fiat Currency: $declaredOutboundFeeAmountInFiatCurrency");
		errorLog("Outbound Declared Fee Amount In Crypto Currency: $declaredOutboundFeeAmountInCryptoCurrency");
		
		
		errorLog("Number of Days In Short Term: $numberOfDaysInShortTerm");
		errorLog("Short Term Gains Tax Percentage: $shortTermGainTaxPercentage");
		errorLog("Long Term Gains Tax Percentage: $longTermGainTaxPercentage");
		
		errorLog("Scenario 1: Calculated Information");
		
		errorLog("Total Days Asset Held: ".$totalDaysAssetHeldTimeInterval -> days);
		errorLog("Gain Or Loss Amount: $gainOrLossAmount");
		errorLog("Short or Long Term Gain Type ID: $shortOrLongTermGainTypeID");
		errorLog("Tax Obligation for Transaction: $taxObligationForTransaction");
		
		errorLog("Inbound Crypto Exchange Buy Price Value \n".$inboundCryptoExchangeBuyPriceValue -> printObjectContents());
		errorLog("Inbound Crypto Exchange Spot Price Value \n".$inboundCryptoExchangeSpotPriceValue -> printObjectContents());
		errorLog("Inbound Crypto Exchange Sell Price Value \n".$inboundCryptoExchangeSellPriceValue -> printObjectContents());
		
		errorLog("Value of Asset Purchased Calculation Basis ID ".$valueOfAssetPurchasedCalculationBasisID);
		errorLog("Value of Asset Purchased Using Buy Price ".$valueOfAssetPurchasedUsingBuyPrice);
		errorLog("Value of Asset Purchased Using Spot Price ".$valueOfAssetPurchasedUsingSpotPrice);
		errorLog("Real Buy Price Of Asset ".$realBuyPriceOfAsset);
		errorLog("Calculated Buy Price Of Asset Unit ".$calculatedBuyPriceOfAssetUnit);
		
		errorLog("Inbound Fee From Buy Price \n".$inboundFeeFromBuyPrice -> printObjectContents());
		errorLog("Inbound Fee From Spot Price \n".$inboundFeeFromSpotPrice -> printObjectContents());
		errorLog("Inbound Fee From Sell Price \n".$inboundFeeFromSellPrice -> printObjectContents());
		
		errorLog("Outbound Crypto Exchange Buy Price Value \n".$outboundCryptoExchangeBuyPriceValue -> printObjectContents());
		errorLog("Outbound Crypto Exchange Sell Price Value \n".$outboundCryptoExchangeSellPriceValue -> printObjectContents());
		errorLog("Outbound Crypto Exchange Spot Price Value \n".$outboundCryptoExchangeSpotPriceValue -> printObjectContents());
		
		errorLog("Value of Asset Sold Calculation Basis ID ".$valueOfAssetSoldCalculationBasisID);
		errorLog("Value of Asset Sold Using Sell Price ".$valueOfAssetSoldUsingSellPrice);
		errorLog("Value of Asset Sold Using Spot Price ".$valueOfAssetSoldUsingSpotPrice);
		errorLog("Real Sell Price Of Asset ".$realSellPriceOfAsset);
		errorLog("Calculated Sell Price Of Asset Unit ".$calculatedSellPriceOfAssetUnit);
		
		errorLog("Outbound Fee From Sell Price \n".$outboundFeeFromSellPrice -> printObjectContents());
		errorLog("Outbound Fee From Spot Price \n".$outboundFeeFromSpotPrice -> printObjectContents());
		errorLog("Outbound Fee From Buy Price \n".$outboundFeeFromBuyPrice -> printObjectContents());
		
		$inboundCryptoExchangeSpotPriceValue -> writeToDatabase($dbh);
		$inboundCryptoExchangeBuyPriceValue -> writeToDatabase($dbh);
		$inboundCryptoExchangeSellPriceValue -> writeToDatabase($dbh);
		
		$outboundCryptoExchangeSpotPriceValue -> writeToDatabase($dbh);
		$outboundCryptoExchangeBuyPriceValue -> writeToDatabase($dbh);
		$outboundCryptoExchangeSellPriceValue -> writeToDatabase($dbh);
		
		$inboundFeeFromBuyPrice -> writeToDatabase($dbh);
		$inboundFeeFromSellPrice -> writeToDatabase($dbh);
		$inboundFeeFromSpotPrice -> writeToDatabase($dbh);
		
		$outboundFeeFromBuyPrice -> writeToDatabase($dbh);
		$outboundFeeFromSellPrice -> writeToDatabase($dbh);
		$outboundFeeFromSpotPrice -> writeToDatabase($dbh);
		
		$inboundPriceValueFromBuyPriceResponse 			= $inboundCryptoExchangeBuyPriceValue -> instantiateFromDatabase($accountID, $inboundTransactionID, 1, $dbh);
		$inboundPriceValueFromSellPriceResponse			= $inboundCryptoExchangeSellPriceValue -> instantiateFromDatabase($accountID, $inboundTransactionID, 2, $dbh);
		$inboundPriceValueFromSpotPriceResponse			= $inboundCryptoExchangeSpotPriceValue -> instantiateFromDatabase($accountID, $inboundTransactionID, 3, $dbh);
		
		$outboundPriceValueFromBuyPriceResponse 		= $outboundCryptoExchangeBuyPriceValue -> instantiateFromDatabase($accountID, $outboundTransactionID, 1, $dbh);
		$outboundPriceValueFromSellPriceResponse 		= $outboundCryptoExchangeSellPriceValue -> instantiateFromDatabase($accountID, $outboundTransactionID, 2, $dbh);
		$outboundPriceValueFromSpotPriceResponse 		= $outboundCryptoExchangeSpotPriceValue -> instantiateFromDatabase($accountID, $outboundTransactionID, 3, $dbh);
		
		$inboundFeeFromBuyPriceResponse 				= $inboundFeeFromBuyPrice -> instantiateFromDatabase($accountID, $inboundTransactionID, 1, $dbh);
		$inboundFeeFromSellPriceResponse 				= $inboundFeeFromSellPrice -> instantiateFromDatabase($accountID, $inboundTransactionID, 2, $dbh);
		$inboundFeeFromSpotPriceResponse 				= $inboundFeeFromSpotPrice -> instantiateFromDatabase($accountID, $inboundTransactionID, 3, $dbh);
		
		$outboundFeeFromBuyPriceResponse 				= $outboundFeeFromBuyPrice -> instantiateFromDatabase($accountID, $outboundTransactionID, 1, $dbh);
		$outboundFeeFromSellPriceResponse 				= $outboundFeeFromSellPrice -> instantiateFromDatabase($accountID, $outboundTransactionID, 2, $dbh);
		$outboundFeeFromSpotPriceResponse 				= $outboundFeeFromSpotPrice -> instantiateFromDatabase($accountID, $outboundTransactionID, 3, $dbh);
		
		if ($inboundPriceValueFromBuyPriceResponse['retrievedExchangeValueObject'] == true)
		{
			errorLog("inboundPriceValueFromBuyPriceResponse retrieved");
			
			errorLog("Inbound Price Value From Buy Price after load \n".$inboundCryptoExchangeBuyPriceValue -> printObjectContents());	
		}
		
		if ($inboundPriceValueFromSellPriceResponse['retrievedExchangeValueObject'] == true)
		{
			errorLog("inboundPriceValueFromSpellPriceResponse retrieved");
			
			errorLog("Inbound Price Value From Sell Price after load \n".$inboundCryptoExchangeSellPriceValue -> printObjectContents());	
		}
		
		if ($inboundPriceValueFromSpotPriceResponse['retrievedExchangeValueObject'] == true)
		{
			errorLog("inboundPriceValueFromSpotPriceResponse retrieved");
			
			errorLog("Inbound Price Value From Spot Price after load \n".$inboundCryptoExchangeSpotPriceValue -> printObjectContents());	
		}

		if ($outboundPriceValueFromBuyPriceResponse['retrievedExchangeValueObject'] == true)
		{
			errorLog("outboundPriceValueFromBuyPriceResponse retrieved");
			
			errorLog("Outbound Price Value From Buy Price after load \n".$outboundCryptoExchangeBuyPriceValue -> printObjectContents());	
		}
		
		if ($outboundPriceValueFromSellPriceResponse['retrievedExchangeValueObject'] == true)
		{
			errorLog("outboundPriceValueFromSpellPriceResponse retrieved");
			
			errorLog("Outbound Price Value From Sell Price after load \n".$outboundCryptoExchangeSellPriceValue -> printObjectContents());	
		}
		
		if ($outboundPriceValueFromSpotPriceResponse['retrievedExchangeValueObject'] == true)
		{
			errorLog("outboundPriceValueFromSpotPriceResponse retrieved");
			
			errorLog("Outbound Price Value From Spot Price after load \n".$outboundCryptoExchangeSpotPriceValue -> printObjectContents());	
		}
		
		if ($inboundFeeFromBuyPriceResponse['retrievedFeeObject'] == true)
		{
			errorLog("inboundFeeFromBuyPriceResponse retrieved");
			
			errorLog("Inbound Fee From Buy Price after load \n".$inboundFeeFromBuyPrice -> printObjectContents());	
		}
		
		if ($inboundFeeFromSellPriceResponse['retrievedFeeObject'] == true)
		{
			errorLog("inboundFeeFromSpellPriceResponse retrieved");
			
			errorLog("Inbound Fee From Sell Price after load \n".$inboundFeeFromSellPrice -> printObjectContents());	
		}
		
		if ($inboundFeeFromSpotPriceResponse['retrievedFeeObject'] == true)
		{
			errorLog("inboundFeeFromSpotPriceResponse retrieved");
			
			errorLog("Inbound Fee From Spot Price after load \n".$inboundFeeFromSpotPrice -> printObjectContents());	
		}

		if ($inboundFeeFromBuyPriceResponse['retrievedFeeObject'] == true)
		{
			errorLog("outboundFeeFromBuyPriceResponse retrieved");
			
			errorLog("Outbound Fee From Buy Price after load \n".$outboundFeeFromBuyPrice -> printObjectContents());	
		}
		
		if ($inboundFeeFromSellPriceResponse['retrievedFeeObject'] == true)
		{
			errorLog("outboundFeeFromSpellPriceResponse retrieved");
			
			errorLog("Outbound Fee From Sell Price after load \n".$outboundFeeFromSellPrice -> printObjectContents());	
		}
		
		if ($inboundFeeFromSpotPriceResponse['retrievedFeeObject'] == true)
		{
			errorLog("outboundFeeFromSpotPriceResponse retrieved");
			
			errorLog("Outbound Fee From Spot Price after load \n".$outboundFeeFromSpotPrice -> printObjectContents());	
		}
	}
	
	
	// END VALUE OF ASSET, FEE, and FEE PERCENTAGE CALCULATIONS
	
	// TRANSACTION HISTORY FUNCTIONS
	
	function getTransactionHistoryRecordsForUser($userAccountID, $userEncryptionKey, $numItemsPerPage, $pageNumber, $globalCurrentDate, $sid, $dbh)
	{
		$transactionsForUser												= array();
			
		$offset																= 0;
			
		if ($pageNumber > 1)
		{
			$beginOffset													= $pageNumber - 1;
			
			$offset															= $numItemsPerPage * $beginOffset;	
		}
			
		$numItemsPerPage													= (int) $numItemsPerPage;
		$offset																= (int) $offset;
		
		try
		{		
			$getTransactionHistoryRecords									= $dbh -> prepare("SELECT 
		transactionID,
		transactionDate,
		formattedTransactionDate,
		action,
		cryptoAmount,
		unitPrice,
		fiatAmount,
		exchange,
		fees,
		feesInPercentage,
		capitolGains,
		taxDue,
		FK_AssetTypeID,
		assetTypeLabel
	FROM
	(
		SELECT
			Transactions.transactionID,
			Transactions.transactionDate,
			DATE_FORMAT(Transactions.transactionDate, '%e %b %Y') AS formattedTransactionDate,
			TransactionTypes.displayTransactionTypeLabel AS action,
			Transactions.btcQuantityTransacted AS cryptoAmount,
			Transactions.spotPriceAtTimeOfTransaction AS unitPrice,
			Transactions.usdQuantityTransacted AS fiatAmount,
			TransactionSources.transactionSourceLabel AS exchange,
			Transactions.usdFeeAmount AS fees,
			(Transactions.usdFeeAmount / Transactions.usdQuantityTransacted) * 100 AS feesInPercentage,
			outbound.profitOrLossAmountUSD AS capitolGains,
			CASE
				WHEN outbound.profitOrLossAmountUSD > 0 AND outbound.FK_GainTypeID = 2 THEN (outbound.profitOrLossAmountUSD * AccountingMethodForRegionProfile.shortTermGainTaxPercentage) / 100
				WHEN outbound.profitOrLossAmountUSD > 0 AND outbound.FK_GainTypeID = 3 THEN (outbound.profitOrLossAmountUSD * AccountingMethodForRegionProfile.longTermGainTaxPercentage) / 100
				ELSE 0
			END AS taxDue,
			Transactions.FK_AssetTypeID,
			assetTypes.assetTypeLabel
		FROM
			Transactions 
			INNER JOIN AssetTypes assetTypes ON Transactions.FK_AssetTypeID = assetTypes.assetTypeID AND assetTypes.languageCode = 'EN'
			INNER JOIN TransactionTypes ON Transactions.FK_TransactionTypeID = TransactionTypes.transactionTypeID AND TransactionTypes.languageCode = 'EN'
			INNER JOIN TransactionSources ON Transactions.FK_TransactionSourceID = TransactionSources.transactionSourceID AND TransactionSources.languageCode = 'EN'
			INNER JOIN OutboundTransactionSourceGrouping outbound ON Transactions.transactionID = outbound.FK_OutboundAssetTransactionID
			INNER JOIN UserAccounts ON Transactions.FK_AccountID = UserAccounts.userAccountID
			INNER JOIN CountryForRegionProfile ON UserAccounts.FK_CountryCode = CountryForRegionProfile.FK_CountryID
			INNER JOIN AccountingMethodForRegionProfile ON CountryForRegionProfile.FK_RegionProfileID = AccountingMethodForRegionProfile.FK_RegionProfileID
			INNER JOIN CryptoWallets toWallet ON Transactions.FK_DestinationAddressID = toWallet.walletID
			INNER JOIN CryptoWallets fromWallet ON Transactions.FK_SourceAddressID = fromWallet.walletID
			LEFT JOIN AssetTypes toWalletAssetType ON toWallet.FK_AssetTypeID = toWalletAssetType.assetTypeID
			LEFT JOIN AssetTypes fromWalletAssetType ON fromWallet.FK_AssetTypeID = fromWalletAssetType.assetTypeID
		WHERE
			Transactions.FK_AccountID = :accountID
	UNION
		SELECT
			Transactions.transactionID,
			Transactions.transactionDate,
			DATE_FORMAT(Transactions.transactionDate, '%e %b %Y') AS formattedTransactionDate,
			TransactionTypes.displayTransactionTypeLabel AS action,
			Transactions.btcQuantityTransacted AS cryptoAmount,
			Transactions.spotPriceAtTimeOfTransaction AS unitPrice,
			Transactions.usdQuantityTransacted AS fiatAmount,
			TransactionSources.transactionSourceLabel AS exchange,
			Transactions.usdFeeAmount AS fees,
			(Transactions.usdFeeAmount / Transactions.usdQuantityTransacted) * 100 AS feesInPercentage,
			inbound.profitOrLossAmountUSD AS capitolGains,
			CASE
				WHEN inbound.profitOrLossAmountUSD > 0 AND inbound.FK_GainTypeID = 2 THEN (inbound.profitOrLossAmountUSD * AccountingMethodForRegionProfile.shortTermGainTaxPercentage) / 100
				WHEN inbound.profitOrLossAmountUSD > 0 AND inbound.FK_GainTypeID = 3 THEN (inbound.profitOrLossAmountUSD * AccountingMethodForRegionProfile.longTermGainTaxPercentage) / 100
				ELSE 0
			END AS taxDue,
			Transactions.FK_AssetTypeID,
			assetTypes.assetTypeLabel
		FROM
			Transactions 
			INNER JOIN AssetTypes assetTypes ON Transactions.FK_AssetTypeID = assetTypes.assetTypeID AND assetTypes.languageCode = 'EN'
			INNER JOIN TransactionTypes ON Transactions.FK_TransactionTypeID = TransactionTypes.transactionTypeID AND TransactionTypes.languageCode = 'EN'
			INNER JOIN TransactionSources ON Transactions.FK_TransactionSourceID = TransactionSources.transactionSourceID AND TransactionSources.languageCode = 'EN'
			INNER JOIN OutboundTransactionSourceGrouping inbound ON Transactions.transactionID = inbound.FK_InboundAssetTransactionID
			INNER JOIN UserAccounts ON Transactions.FK_AccountID = UserAccounts.userAccountID
			INNER JOIN CountryForRegionProfile ON UserAccounts.FK_CountryCode = CountryForRegionProfile.FK_CountryID
			INNER JOIN AccountingMethodForRegionProfile ON CountryForRegionProfile.FK_RegionProfileID = AccountingMethodForRegionProfile.FK_RegionProfileID
			INNER JOIN CryptoWallets toWallet ON Transactions.FK_DestinationAddressID = toWallet.walletID
			INNER JOIN CryptoWallets fromWallet ON Transactions.FK_SourceAddressID = fromWallet.walletID
			LEFT JOIN AssetTypes toWalletAssetType ON toWallet.FK_AssetTypeID = toWalletAssetType.assetTypeID
			LEFT JOIN AssetTypes fromWalletAssetType ON fromWallet.FK_AssetTypeID = fromWalletAssetType.assetTypeID
		WHERE
			Transactions.FK_AccountID = :accountID
	UNION
		SELECT
			Transactions.transactionID,
			Transactions.transactionDate,
			DATE_FORMAT(Transactions.transactionDate, '%e %b %Y') AS formattedTransactionDate,
			TransactionTypes.displayTransactionTypeLabel AS action,
			Transactions.btcQuantityTransacted AS cryptoAmount,
			Transactions.spotPriceAtTimeOfTransaction AS unitPrice,
			Transactions.usdQuantityTransacted AS fiatAmount,
			TransactionSources.transactionSourceLabel AS exchange,
			Transactions.usdFeeAmount AS fees,
			(Transactions.usdFeeAmount / Transactions.usdQuantityTransacted) * 100 AS feesInPercentage,
			0 AS capitolGains,
			0 AS taxDue,
			Transactions.FK_AssetTypeID,
			assetTypes.assetTypeLabel
		FROM
			Transactions 
			INNER JOIN AssetTypes assetTypes ON Transactions.FK_AssetTypeID = assetTypes.assetTypeID AND assetTypes.languageCode = 'EN'
			INNER JOIN TransactionTypes ON Transactions.FK_TransactionTypeID = TransactionTypes.transactionTypeID AND TransactionTypes.languageCode = 'EN'
			INNER JOIN TransactionSources ON Transactions.FK_TransactionSourceID = TransactionSources.transactionSourceID AND TransactionSources.languageCode = 'EN'
			INNER JOIN UserAccounts ON Transactions.FK_AccountID = UserAccounts.userAccountID
			INNER JOIN CountryForRegionProfile ON UserAccounts.FK_CountryCode = CountryForRegionProfile.FK_CountryID
			INNER JOIN AccountingMethodForRegionProfile ON CountryForRegionProfile.FK_RegionProfileID = AccountingMethodForRegionProfile.FK_RegionProfileID
			INNER JOIN CryptoWallets toWallet ON Transactions.FK_DestinationAddressID = toWallet.walletID
			INNER JOIN CryptoWallets fromWallet ON Transactions.FK_SourceAddressID = fromWallet.walletID
			LEFT JOIN AssetTypes toWalletAssetType ON toWallet.FK_AssetTypeID = toWalletAssetType.assetTypeID
			LEFT JOIN AssetTypes fromWalletAssetType ON fromWallet.FK_AssetTypeID = fromWalletAssetType.assetTypeID
			LEFT JOIN OutboundTransactionSourceGrouping inbound ON Transactions.transactionID = inbound.FK_InboundAssetTransactionID
			
		WHERE
			Transactions.FK_AccountID = :accountID AND
			Transactions.isDebit = 0 AND
			inbound.FK_InboundAssetTransactionID IS NULL
	) a
	ORDER BY
		transactionDate, transactionID
	LIMIT
		:limit
	OFFSET
		:offset");
		
			$getTransactionHistoryRecords -> bindValue(':accountID', $userAccountID, PDO::PARAM_INT);
			$getTransactionHistoryRecords -> bindValue(':userEncryptionKey', $userEncryptionKey);
			$getTransactionHistoryRecords -> bindValue(':limit', $numItemsPerPage, PDO::PARAM_INT);
			$getTransactionHistoryRecords -> bindValue(':offset', $offset, PDO::PARAM_INT);
		
			if ($getTransactionHistoryRecords -> execute() && $getTransactionHistoryRecords -> rowCount() > 0)
			{
				while ($row = $getTransactionHistoryRecords -> fetchObject())
				{
					$transactionArray						= array();
					
					$transactionID							= $row->transactionID;
					$transactionDate						= $row->formattedTransactionDate;
					$action									= $row->action;
					$cryptoAmount							= $row->cryptoAmount;
					$unitPrice								= $row->unitPrice;
					$fiatAmount								= $row->fiatAmount;
					$exchange								= $row->exchange;
					$fees									= $row->fees;
					$feesInPercentage						= $row->feesInPercentage;
					$capitolGains							= $row->capitolGains;
					$taxDue									= $row->taxDue;	
					$assetTypeID							= $row->FK_AssetTypeID;
					$assetTypeLabel							= $row->assetTypeLabel;
					
					$transactionArray['transactionID']		= $transactionID;
					$transactionArray['transactionDate']	= $transactionDate;
					$transactionArray['action']				= $action;
					$transactionArray['cryptoAmount']		= $cryptoAmount;
					$transactionArray['unitPrice']			= $unitPrice;	
					$transactionArray['fiatAmount']			= $fiatAmount;
					$transactionArray['exchange']			= $exchange;
					$transactionArray['fees']				= $fees;
					$transactionArray['feesInPercentage']	= $feesInPercentage;
					$transactionArray['capitolGains']		= $capitolGains;
					$transactionArray['taxDue']				= $taxDue;
					$transactionArray['assetTypeLabel']		= $assetTypeLabel;

					$transactionsForUser[]					= $transactionArray;
				}		
			}
		}
	    catch (PDOException $e) 
	    {
	    	errorLog($e -> getMessage());
		}
		
		return $transactionsForUser;		
	}
		
	function getTotalNumberOfTransactions($userAccountID, $userEncryptionKey, $globalCurrentDate, $sid, $dbh)
	{
		$responseObject										= array();
		$responseObject['retrievedTransactionCount']			= false;
		$responseObject['transactionCount']					= 0;
		
		try
		{		
			$getTransactionHistoryRecords					= $dbh -> prepare("SELECT 
	COUNT(a.transactionID) as numberOfTransactions
FROM
(
	SELECT DISTINCT
		Transactions.transactionID,
		Transactions.transactionDate,
		DATE_FORMAT(Transactions.transactionDate, '%e %b %Y') AS formattedTransactionDate,
		TransactionTypes.transactionTypeLabel AS action,
		Transactions.btcQuantityTransacted AS cryptoAmount,
		Transactions.spotPriceAtTimeOfTransaction AS unitPrice,
		Transactions.usdQuantityTransacted AS fiatAmount,
		TransactionSources.transactionSourceLabel AS exchange,
		Transactions.usdFeeAmount AS fees,
		(Transactions.usdFeeAmount / Transactions.usdQuantityTransacted) * 100 AS feesInPercentage,
		outbound.profitOrLossAmountUSD AS capitolGains,
		CASE
			WHEN outbound.profitOrLossAmountUSD > 0 AND outbound.FK_GainTypeID = 2 THEN (outbound.profitOrLossAmountUSD * AccountingMethodForRegionProfile.shortTermGainTaxPercentage) / 100
			WHEN outbound.profitOrLossAmountUSD > 0 AND outbound.FK_GainTypeID = 3 THEN (outbound.profitOrLossAmountUSD * AccountingMethodForRegionProfile.longTermGainTaxPercentage) / 100
			ELSE 0
		END AS taxDue,
		Transactions.FK_AssetTypeID,
		assetTypes.assetTypeLabel
	FROM
		Transactions 
		INNER JOIN AssetTypes assetTypes ON Transactions.FK_AssetTypeID = assetTypes.assetTypeID AND assetTypes.languageCode = 'EN'
		INNER JOIN TransactionTypes ON Transactions.FK_TransactionTypeID = TransactionTypes.transactionTypeID AND TransactionTypes.languageCode = 'EN'
		INNER JOIN TransactionSources ON Transactions.FK_TransactionSourceID = TransactionSources.transactionSourceID AND TransactionSources.languageCode = 'EN'
		INNER JOIN OutboundTransactionSourceGrouping outbound ON Transactions.transactionID = outbound.FK_OutboundAssetTransactionID
		INNER JOIN UserAccounts ON Transactions.FK_AccountID = UserAccounts.userAccountID
		INNER JOIN CountryForRegionProfile ON UserAccounts.FK_CountryCode = CountryForRegionProfile.FK_CountryID
		INNER JOIN AccountingMethodForRegionProfile ON CountryForRegionProfile.FK_RegionProfileID = AccountingMethodForRegionProfile.FK_RegionProfileID
		INNER JOIN CryptoWallets toWallet ON Transactions.FK_DestinationAddressID = toWallet.walletID
		INNER JOIN CryptoWallets fromWallet ON Transactions.FK_SourceAddressID = fromWallet.walletID
		LEFT JOIN AssetTypes toWalletAssetType ON toWallet.FK_AssetTypeID = toWalletAssetType.assetTypeID
		LEFT JOIN AssetTypes fromWalletAssetType ON fromWallet.FK_AssetTypeID = fromWalletAssetType.assetTypeID
	WHERE
		Transactions.FK_AccountID = :accountID
UNION
	SELECT
		Transactions.transactionID,
		Transactions.transactionDate,
		DATE_FORMAT(Transactions.transactionDate, '%e %b %Y') AS formattedTransactionDate,
		TransactionTypes.transactionTypeLabel AS action,
		Transactions.btcQuantityTransacted AS cryptoAmount,
		Transactions.spotPriceAtTimeOfTransaction AS unitPrice,
		Transactions.usdQuantityTransacted AS fiatAmount,
		TransactionSources.transactionSourceLabel AS exchange,
		Transactions.usdFeeAmount AS fees,
		(Transactions.usdFeeAmount / Transactions.usdQuantityTransacted) * 100 AS feesInPercentage,
		inbound.profitOrLossAmountUSD AS capitolGains,
		CASE
			WHEN inbound.profitOrLossAmountUSD > 0 AND inbound.FK_GainTypeID = 2 THEN (inbound.profitOrLossAmountUSD * AccountingMethodForRegionProfile.shortTermGainTaxPercentage) / 100
			WHEN inbound.profitOrLossAmountUSD > 0 AND inbound.FK_GainTypeID = 3 THEN (inbound.profitOrLossAmountUSD * AccountingMethodForRegionProfile.longTermGainTaxPercentage) / 100
			ELSE 0
		END AS taxDue,
		Transactions.FK_AssetTypeID,
		assetTypes.assetTypeLabel
	FROM
		Transactions 
		INNER JOIN AssetTypes assetTypes ON Transactions.FK_AssetTypeID = assetTypes.assetTypeID AND assetTypes.languageCode = 'EN'
		INNER JOIN TransactionTypes ON Transactions.FK_TransactionTypeID = TransactionTypes.transactionTypeID AND TransactionTypes.languageCode = 'EN'
		INNER JOIN TransactionSources ON Transactions.FK_TransactionSourceID = TransactionSources.transactionSourceID AND TransactionSources.languageCode = 'EN'
		INNER JOIN OutboundTransactionSourceGrouping inbound ON Transactions.transactionID = inbound.FK_InboundAssetTransactionID
		INNER JOIN UserAccounts ON Transactions.FK_AccountID = UserAccounts.userAccountID
		INNER JOIN CountryForRegionProfile ON UserAccounts.FK_CountryCode = CountryForRegionProfile.FK_CountryID
		INNER JOIN AccountingMethodForRegionProfile ON CountryForRegionProfile.FK_RegionProfileID = AccountingMethodForRegionProfile.FK_RegionProfileID
		INNER JOIN CryptoWallets toWallet ON Transactions.FK_DestinationAddressID = toWallet.walletID
		INNER JOIN CryptoWallets fromWallet ON Transactions.FK_SourceAddressID = fromWallet.walletID
		LEFT JOIN AssetTypes toWalletAssetType ON toWallet.FK_AssetTypeID = toWalletAssetType.assetTypeID
		LEFT JOIN AssetTypes fromWalletAssetType ON fromWallet.FK_AssetTypeID = fromWalletAssetType.assetTypeID
	WHERE
		Transactions.FK_AccountID = :accountID
UNION
	SELECT
		Transactions.transactionID,
		Transactions.transactionDate,
		DATE_FORMAT(Transactions.transactionDate, '%e %b %Y') AS formattedTransactionDate,
		TransactionTypes.transactionTypeLabel AS action,
		Transactions.btcQuantityTransacted AS cryptoAmount,
		Transactions.spotPriceAtTimeOfTransaction AS unitPrice,
		Transactions.usdQuantityTransacted AS fiatAmount,
		TransactionSources.transactionSourceLabel AS exchange,
		Transactions.usdFeeAmount AS fees,
		(Transactions.usdFeeAmount / Transactions.usdQuantityTransacted) * 100 AS feesInPercentage,
		0 AS capitolGains,
		0 AS taxDue,
		Transactions.FK_AssetTypeID,
		assetTypes.assetTypeLabel
	FROM
		Transactions 
		INNER JOIN AssetTypes assetTypes ON Transactions.FK_AssetTypeID = assetTypes.assetTypeID AND assetTypes.languageCode = 'EN'
		INNER JOIN TransactionTypes ON Transactions.FK_TransactionTypeID = TransactionTypes.transactionTypeID AND TransactionTypes.languageCode = 'EN'
		INNER JOIN TransactionSources ON Transactions.FK_TransactionSourceID = TransactionSources.transactionSourceID AND TransactionSources.languageCode = 'EN'
		INNER JOIN UserAccounts ON Transactions.FK_AccountID = UserAccounts.userAccountID
		INNER JOIN CountryForRegionProfile ON UserAccounts.FK_CountryCode = CountryForRegionProfile.FK_CountryID
		INNER JOIN AccountingMethodForRegionProfile ON CountryForRegionProfile.FK_RegionProfileID = AccountingMethodForRegionProfile.FK_RegionProfileID
		INNER JOIN CryptoWallets toWallet ON Transactions.FK_DestinationAddressID = toWallet.walletID
		INNER JOIN CryptoWallets fromWallet ON Transactions.FK_SourceAddressID = fromWallet.walletID
		LEFT JOIN AssetTypes toWalletAssetType ON toWallet.FK_AssetTypeID = toWalletAssetType.assetTypeID
		LEFT JOIN AssetTypes fromWalletAssetType ON fromWallet.FK_AssetTypeID = fromWalletAssetType.assetTypeID
		LEFT JOIN OutboundTransactionSourceGrouping inbound ON Transactions.transactionID = inbound.FK_InboundAssetTransactionID
		
	WHERE
		Transactions.FK_AccountID = :accountID AND
		Transactions.isDebit = 0 AND
		inbound.FK_InboundAssetTransactionID IS NULL
) a");

			$getTransactionHistoryRecords -> bindValue(':accountID', $userAccountID);
			$getTransactionHistoryRecords -> bindValue(':userEncryptionKey', $userEncryptionKey);
						
			if ($getTransactionHistoryRecords -> execute() && $getTransactionHistoryRecords -> rowCount() > 0)
			{
				$row 										= $getTransactionHistoryRecords -> fetchObject();
				
				$responseObject['retrievedTransactionCount']= true;
				$responseObject['transactionCount']			= $row->numberOfTransactions;;		
			}
		}
	    catch (PDOException $e) 
	    {
	    	errorLog($e -> getMessage());
		}
		
		return $responseObject;		
	}
	
	function getNewTransactionHistoryRecordsForUser($userAccountID, $userEncryptionKey, $numItemsPerPage, $pageNumber, $globalCurrentDate, $sid, $dbh)
	{
		$transactionsForUser												= array();
		
		$offset																= 0;
		
		if ($pageNumber > 1)
		{
			$beginOffset													= $pageNumber - 1;
		
			$offset															= $numItemsPerPage * $beginOffset;	
		}
		
		$numItemsPerPage													= (int) $numItemsPerPage;
		$offset																= (int) $offset;
		
		try
		{		
			$getTransactionHistoryRecords									= $dbh -> prepare("SELECT
	Transactions.transactionID,
	DATE_FORMAT(Transactions.transactionDate, '%e %b %Y') AS formattedTransactionDate,
	TransactionTypes.displayTransactionTypeLabel AS action,
	assetTypes.assetTypeLabel,
	AES_DECRYPT(ExchangeTiles.encryptedTileLabel, UNHEX(SHA2(:userEncryptionKey,512))) AS exchange,
	Transactions.btcQuantityTransacted AS cryptoAmount,
	Transactions.usdQuantityTransacted AS fiatAmount,
	Transactions.usdFeeAmount AS fees
FROM
	Transactions 
	INNER JOIN AssetTypes assetTypes ON Transactions.FK_AssetTypeID = assetTypes.assetTypeID AND assetTypes.languageCode = 'EN'
	INNER JOIN TransactionTypes ON Transactions.FK_TransactionTypeID = TransactionTypes.transactionTypeID AND TransactionTypes.languageCode = 'EN'
	INNER JOIN ExchangeTiles ON Transactions.FK_ExchangeTileID = ExchangeTiles.exchangeTileID
	INNER JOIN UserAccounts ON Transactions.FK_AccountID = UserAccounts.userAccountID
WHERE
	Transactions.FK_AccountID = :accountID AND
	Transactions.FK_AssetTypeID != 2
ORDER BY
	transactionDate, transactionID
LIMIT
	:limit
OFFSET
	:offset");
	
			$getTransactionHistoryRecords -> bindValue(':accountID', $userAccountID, PDO::PARAM_INT);
			$getTransactionHistoryRecords -> bindValue(':userEncryptionKey', $userEncryptionKey);
			$getTransactionHistoryRecords -> bindValue(':limit', $numItemsPerPage, PDO::PARAM_INT);
			$getTransactionHistoryRecords -> bindValue(':offset', $offset, PDO::PARAM_INT);
		
			if ($getTransactionHistoryRecords -> execute() && $getTransactionHistoryRecords -> rowCount() > 0)
			{
				while ($row = $getTransactionHistoryRecords -> fetchObject())
				{
					$transactionArray						= array();
					
					$transactionID							= $row->transactionID;
					$transactionDate						= $row->formattedTransactionDate;
					$action									= $row->action;
					$assetTypeLabel							= $row->assetTypeLabel;
					$exchange								= $row->exchange;
					$cryptoAmount							= $row->cryptoAmount;
					$fiatAmount								= $row->fiatAmount;
					$fees									= $row->fees;
					
					$transactionArray['transactionID']		= $transactionID;
					$transactionArray['transactionDate']	= $transactionDate;
					$transactionArray['action']				= $action;
					$transactionArray['asset']				= $assetTypeLabel;
					$transactionArray['exchange']			= $exchange;
					$transactionArray['cryptoAmount']		= $cryptoAmount;
					$transactionArray['fiatAmount']			= $fiatAmount;
					$transactionArray['fees']				= $fees;
					
					$transactionsForUser[]					= $transactionArray;
				}		
			}
		}
	    catch (PDOException $e) 
	    {
	    	errorLog($e -> getMessage());
		}
		
		return $transactionsForUser;		
	}
	
	function getConstructedTransactionHistoryRecordsForUser($userAccountID, $userEncryptionKey, $numItemsPerPage, $pageNumber, $startDate, $endDate, $dateSortDirection, $typeFilterValue, $assetFilterValue, $assetSortDirection, $exchangeWalletFilterValue, $exchangeWalletSortDirection, $cryptoAmountFilterValue, $fiatAmountFilterValue, $feeAmountFilterValue, $globalCurrentDate, $sid, $dbh)
	{
		$responseObject														= array();
		
		$responseObject['retrievedTransactionsForUser']						= false;
		
		$responseObject['transactionsForUser']								= array();
		
		$transactionsForUser												= array();
		
		$selectClause													 	= "SELECT
	Transactions.transactionID,
	DATE_FORMAT(Transactions.transactionDate, '%e %b %Y') AS formattedTransactionDate,
	TransactionTypes.displayTransactionTypeLabel AS action,
	assetTypes.assetTypeLabel,
	AES_DECRYPT(ExchangeTiles.encryptedTileLabel, UNHEX(SHA2(:userEncryptionKey,512))) AS exchange,
	Transactions.btcQuantityTransacted AS cryptoAmount,
	Transactions.usdQuantityTransacted AS fiatAmount,
	Transactions.usdFeeAmount AS fees
";
	
		$whereClause														= "WHERE
	Transactions.FK_AccountID = :accountID";
		
		$orderByClause														= "ORDER BY
	transactionDate, transactionID
	";
		
		$offset																= 0;
		
		if ($pageNumber > 1)
		{
			$beginOffset													= $pageNumber - 1;
		
			$offset															= $numItemsPerPage * $beginOffset;	
		}
		
		$numItemsPerPage													= (int) $numItemsPerPage;
		$offset																= (int) $offset;
		
		$bindStartDate														= false;
		$bindEndDate														= false;
		$bindTypeFilter														= false;
		$bindAssetFilter													= false;
		$bindExchangeFilter													= false;
		$bindCryptoAmountFilter												= false;
		$bindFiatAmountFilter												= false;
		$bindFeeAmountFilter												= false;
		
		$dateSortDirection													= strtolower($dateSortDirection);
		$assetSortDirection													= strtolower($assetSortDirection);
		$exchangeWalletSortDirection										= strtolower($exchangeWalletSortDirection);
		
		if (!empty($startDate) && strlen($startDate) > 9)
		{
			$whereClause													= $whereClause." AND
	Transactions.transactionDate >= :startDate";
	
			$bindStartDate													= true;
		}
		
		if (!empty($endDate) && strlen($endDate) > 9)
		{
			$whereClause													= $whereClause." AND
	Transactions.transactionDate <= :endDate";
	
			$bindEndDate													= true;	
		}
		
		if (!empty($typeFilterValue) && $typeFilterValue > 0)
		{
			$whereClause													= $whereClause." AND
	Transactions.FK_TransactionTypeID = :transactionTypeID";	
	
			$bindTypeFilter													= true;
		}
		
		if (!empty($assetFilterValue) && $assetFilterValue > 0)
		{
			$whereClause													= $whereClause." AND
	Transactions.FK_AssetTypeID = :assetTypeID";
	
			$bindAssetFilter												= true;	
		}
		
		if (!empty($exchangeWalletFilterValue) && $exchangeWalletFilterValue > 0)
		{
			$whereClause													= $whereClause." AND
	Transactions.FK_ExchangeTileID = :exchangeTileID";
	
			$bindExchangeFilter												= true;	
		}
		
		if (!empty($cryptoAmountFilterValue) && $cryptoAmountFilterValue > 0)
		{
			$whereClause													= $whereClause." AND
	Transactions.btcQuantityTransacted LIKE CONCAT(:cryptoAmount, '%')";
	
			$bindCryptoAmountFilter											= true;	
		}
		
		if (!empty($fiatAmountFilterValue) && $fiatAmountFilterValue > 0)
		{
			$whereClause													= $whereClause." AND
	Transactions.usdQuantityTransacted LIKE CONCAT(:fiatAmount, '%')";
	
			$bindFiatAmountFilter											= true;	
		}
		
		if (!empty($feeAmountFilterValue) && $feeAmountFilterValue > 0)
		{
			$whereClause													= $whereClause." AND
	Transactions.usdFeeAmount LIKE LIKE CONCAT(:feeAmount, '%')";
	
			$bindFeeAmountFilter											= true;	
		}
		
		if (!empty($dateSortDirection) && (strcasecmp($dateSortDirection, "desc") == 0 || strcasecmp($dateSortDirection, "asc") == 0) && empty($assetSortDirection) && empty($exchangeWalletSortDirection))
		{
			$orderByClause													= " ORDER BY Transactions.transactionDate $dateSortDirection";
		}
		else if (empty($dateSortDirection) && (!empty($assetSortDirection) && (strcasecmp($assetSortDirection, "desc") == 0 || strcasecmp($assetSortDirection, "asc") == 0)) && empty($exchangeWalletSortDirection))
		{
			$orderByClause													= " ORDER BY assetTypes.assetTypeLabel $assetSortDirection";	
		}
		else if (empty($dateSortDirection) && empty($assetSortDirection) && (!empty($exchangeWalletSortDirection) && (strcasecmp($exchangeWalletSortDirection, "desc") == 0 || strcasecmp($exchangeWalletSortDirection, "asc") == 0)))
		{
			$orderByClause													= " ORDER BY exchange $exchangeWalletSortDirection";	
		}
		else if ((!empty($dateSortDirection) && (strcasecmp($dateSortDirection, "desc") == 0 || strcasecmp($dateSortDirection, "asc") == 0)) && (!empty($assetSortDirection) && (strcasecmp($assetSortDirection, "desc") == 0 || strcasecmp($assetSortDirection, "asc") == 0)) && empty($exchangeWalletSortDirection))
		{
			$orderByClause													= " ORDER BY Transactions.transactionDate $dateSortDirection, assetTypes.assetTypeLabel $assetSortDirection";	
		}
		else if ((!empty($dateSortDirection) && (strcasecmp($dateSortDirection, "desc") == 0 || strcasecmp($dateSortDirection, "asc") == 0)) && empty($assetSortDirection) && (!empty($exchangeWalletSortDirection) && (strcasecmp($exchangeWalletSortDirection, "desc") == 0 || strcasecmp($exchangeWalletSortDirection, "asc") == 0))
		)
		{
			$orderByClause													= " ORDER BY Transactions.transactionDate $dateSortDirection, exchange $exchangeWalletSortDirection";	
		}
		else if (empty($dateSortDirection) && (!empty($assetSortDirection) && (strcasecmp($assetSortDirection, "desc") == 0 || strcasecmp($assetSortDirection, "asc") == 0)) && (!empty($exchangeWalletSortDirection) && (strcasecmp($exchangeWalletSortDirection, "desc") == 0 || strcasecmp($exchangeWalletSortDirection, "asc") == 0)))
		{
			$orderByClause													= " ORDER BY assetTypes.assetTypeLabel $assetSortDirection, exchange $exchangeWalletSortDirection";	
		}
		else if ((!empty($dateSortDirection) && (strcasecmp($dateSortDirection, "desc") == 0 || strcasecmp($dateSortDirection, "asc") == 0)) && (!empty($assetSortDirection) && (strcasecmp($assetSortDirection, "desc") == 0 || strcasecmp($assetSortDirection, "asc") == 0)) && (!empty($exchangeWalletSortDirection) && (strcasecmp($exchangeWalletSortDirection, "desc") == 0 || strcasecmp($exchangeWalletSortDirection, "asc") == 0)))
		{
			$orderByClause													= " ORDER BY Transactions.transactionDate $dateSortDirection, assetTypes.assetTypeLabel $assetSortDirection, exchange $exchangeWalletSortDirection";	
		}
		
		try
		{		
			$getTransactionHistoryRecordsSQL								= "$selectClause
FROM
	Transactions 
	INNER JOIN AssetTypes assetTypes ON Transactions.FK_AssetTypeID = assetTypes.assetTypeID AND assetTypes.languageCode = 'EN'
	INNER JOIN TransactionTypes ON Transactions.FK_TransactionTypeID = TransactionTypes.transactionTypeID AND TransactionTypes.languageCode = 'EN'
	INNER JOIN ExchangeTiles ON Transactions.FK_ExchangeTileID = ExchangeTiles.exchangeTileID
	INNER JOIN UserAccounts ON Transactions.FK_AccountID = UserAccounts.userAccountID
$whereClause
$orderByClause
LIMIT
	:limit
OFFSET
	:offset";
	
			errorLog($getTransactionHistoryRecordsSQL);
	
			$getTransactionHistoryRecords									= $dbh -> prepare($getTransactionHistoryRecordsSQL);
	
			$getTransactionHistoryRecords -> bindValue(':accountID', $userAccountID, PDO::PARAM_INT);
			
			if ($bindStartDate == true)
			{
				$getTransactionHistoryRecords -> bindValue(':startDate', $startDate);	
			}
			
			if ($bindEndDate == true)
			{
				$getTransactionHistoryRecords -> bindValue(':endDate', $endDate);
			}
			
			if ($bindTypeFilter == true)
			{
				$getTransactionHistoryRecords -> bindValue(':transactionTypeID', $typeFilterValue, PDO::PARAM_INT);	
			}
			
			if ($bindAssetFilter == true)
			{
				$getTransactionHistoryRecords -> bindValue(':assetTypeID', $assetFilterValue, PDO::PARAM_INT);		
			}
			
			if ($bindExchangeFilter == true)
			{
				$getTransactionHistoryRecords -> bindValue(':exchangeTileID', $exchangeWalletFilterValue, PDO::PARAM_INT);
			}
			
			if ($bindCryptoAmountFilter == true)
			{
				$getTransactionHistoryRecords -> bindValue(':cryptoAmount', $cryptoAmountFilterValue);	
			}
			
			if ($bindFiatAmountFilter == true)
			{
				$getTransactionHistoryRecords -> bindValue(':fiatAmount', $fiatAmountFilterValue);	
			}
			
			if ($bindFeeAmountFilter == true)
			{
				$getTransactionHistoryRecords -> bindValue(':feeAmount', $feeAmountFilterValue);	
			}
			
			$getTransactionHistoryRecords -> bindValue(':userEncryptionKey', $userEncryptionKey);
			$getTransactionHistoryRecords -> bindValue(':limit', $numItemsPerPage, PDO::PARAM_INT);
			$getTransactionHistoryRecords -> bindValue(':offset', $offset, PDO::PARAM_INT);
		
			if ($getTransactionHistoryRecords -> execute() && $getTransactionHistoryRecords -> rowCount() > 0)
			{
				while ($row = $getTransactionHistoryRecords -> fetchObject())
				{
					$responseObject['retrievedTransactionsForUser']			= true;
					
					$transactionArray										= array();
					
					$transactionID											= $row->transactionID;
					$transactionDate										= $row->formattedTransactionDate;
					$action													= $row->action;
					$assetTypeLabel											= $row->assetTypeLabel;
					$exchange												= $row->exchange;
					$cryptoAmount											= $row->cryptoAmount;
					$fiatAmount												= $row->fiatAmount;
					$fees													= $row->fees;
					
					$transactionArray['transactionID']						= $transactionID;
					$transactionArray['transactionDate']					= $transactionDate;
					$transactionArray['action']								= $action;
					$transactionArray['asset']								= $assetTypeLabel;
					$transactionArray['exchange']							= $exchange;
					$transactionArray['cryptoAmount']						= $cryptoAmount;
					$transactionArray['fiatAmount']							= $fiatAmount;
					$transactionArray['fees']								= $fees;
					
					$transactionsForUser[]									= $transactionArray;
				}		
			}
		}
	    catch (PDOException $e) 
	    {
	    	errorLog($e -> getMessage());
		}
		
		$responseObject['transactionsForUser']								= $transactionsForUser;
		
		return $responseObject;		
	}
	
	function getNewTotalNumberOfTransactions($userAccountID, $startDate, $endDate, $typeFilterValue, $assetFilterValue, $exchangeWalletFilterValue, $cryptoAmountFilterValue, $fiatAmountFilterValue, $feeAmountFilterValue, $globalCurrentDate, $sid, $dbh)
	{
		$numberOfRecords													= 0;
		
		$responseObject														= array();
		
		$responseObject['transactionCount']									= 0;
		$responseObject['retrievedTransactionCount']						= false;
		
		$selectClause													 	= "SELECT
	COUNT(Transactions.transactionID) AS numTransactions";
	
		$whereClause														= "WHERE
	Transactions.FK_AccountID = :accountID";
		
		$bindStartDate														= false;
		$bindEndDate														= false;
		$bindTypeFilter														= false;
		$bindAssetFilter													= false;
		$bindExchangeFilter													= false;
		$bindCryptoAmountFilter												= false;
		$bindFiatAmountFilter												= false;
		$bindFeeAmountFilter												= false;
		
		if (!empty($startDate) && strlen($startDate) > 9)
		{
			$whereClause													= $whereClause." AND
	Transactions.transactionDate >= :startDate";
	
			$bindStartDate													= true;
		}
		
		if (!empty($endDate) && strlen($endDate) > 9)
		{
			$whereClause													= $whereClause." AND
	Transactions.transactionDate <= :endDate";
	
			$bindEndDate													= true;	
		}
		
		if (!empty($typeFilterValue) && $typeFilterValue > 0)
		{
			$whereClause													= $whereClause." AND
	Transactions.FK_TransactionTypeID = :transactionTypeID";	
	
			$bindTypeFilter													= true;
		}
		
		if (!empty($assetFilterValue) && $assetFilterValue > 0)
		{
			$whereClause													= $whereClause." AND
	Transactions.FK_AssetTypeID = :assetTypeID";
	
			$bindAssetFilter												= true;	
		}
		
		if (!empty($exchangeWalletFilterValue) && $exchangeWalletFilterValue > 0)
		{
			$whereClause													= $whereClause." AND
	Transactions.FK_ExchangeTileID = :exchangeTileID";
	
			$bindExchangeFilter												= true;	
		}
		
		if (!empty($cryptoAmountFilterValue) && $cryptoAmountFilterValue > 0)
		{
			$whereClause													= $whereClause." AND
	Transactions.btcQuantityTransacted LIKE CONCAT(:cryptoAmount, '%')";
	
			$bindCryptoAmountFilter											= true;	
		}
		
		if (!empty($fiatAmountFilterValue) && $fiatAmountFilterValue > 0)
		{
			$whereClause													= $whereClause." AND
	Transactions.usdQuantityTransacted LIKE CONCAT(:fiatAmount, '%')";
	
			$bindFiatAmountFilter											= true;	
		}
		
		if (!empty($feeAmountFilterValue) && $feeAmountFilterValue > 0)
		{
			$whereClause													= $whereClause." AND
	Transactions.usdFeeAmount LIKE LIKE CONCAT(:feeAmount, '%')";
	
			$bindFeeAmountFilter											= true;	
		}
		
		try
		{		
			$getTransactionHistoryRecordsSQL								= "$selectClause
FROM
	Transactions 
	INNER JOIN AssetTypes assetTypes ON Transactions.FK_AssetTypeID = assetTypes.assetTypeID AND assetTypes.languageCode = 'EN'
	INNER JOIN TransactionTypes ON Transactions.FK_TransactionTypeID = TransactionTypes.transactionTypeID AND TransactionTypes.languageCode = 'EN'
	INNER JOIN ExchangeTiles ON Transactions.FK_ExchangeTileID = ExchangeTiles.exchangeTileID
	INNER JOIN UserAccounts ON Transactions.FK_AccountID = UserAccounts.userAccountID
$whereClause";
	
			errorLog($getTransactionHistoryRecordsSQL);
	
			$getTransactionHistoryRecords									= $dbh -> prepare($getTransactionHistoryRecordsSQL);
	
			$getTransactionHistoryRecords -> bindValue(':accountID', $userAccountID, PDO::PARAM_INT);
			
			if ($bindStartDate == true)
			{
				$getTransactionHistoryRecords -> bindValue(':startDate', $startDate);	
			}
			
			if ($bindEndDate == true)
			{
				$getTransactionHistoryRecords -> bindValue(':endDate', $endDate);
			}
			
			if ($bindTypeFilter == true)
			{
				$getTransactionHistoryRecords -> bindValue(':transactionTypeID', $typeFilterValue, PDO::PARAM_INT);	
			}
			
			if ($bindAssetFilter == true)
			{
				$getTransactionHistoryRecords -> bindValue(':assetTypeID', $assetFilterValue, PDO::PARAM_INT);		
			}
			
			if ($bindExchangeFilter == true)
			{
				$getTransactionHistoryRecords -> bindValue(':exchangeTileID', $exchangeWalletFilterValue, PDO::PARAM_INT);
			}
			
			if ($bindCryptoAmountFilter == true)
			{
				$getTransactionHistoryRecords -> bindValue(':cryptoAmount', $cryptoAmountFilterValue);	
			}
			
			if ($bindFiatAmountFilter == true)
			{
				$getTransactionHistoryRecords -> bindValue(':fiatAmount', $fiatAmountFilterValue);	
			}
			
			if ($bindFeeAmountFilter == true)
			{
				$getTransactionHistoryRecords -> bindValue(':feeAmount', $feeAmountFilterValue);	
			}
						
			if ($getTransactionHistoryRecords -> execute() && $getTransactionHistoryRecords -> rowCount() > 0)
			{
				$row 														= $getTransactionHistoryRecords -> fetchObject();
				$numberOfRecords											= $row -> numTransactions;
					
				$responseObject['retrievedTransactionCount']				= true;		
			}
		}
	    catch (PDOException $e) 
	    {
	    	errorLog($e -> getMessage());
		}
		
		$responseObject['transactionCount']									= $numberOfRecords;
		
		return $responseObject;		
	}
	
	function getFilterOptionsForUserTransactionHistory($userAccountID, $userEncryptionKey, $globalCurrentDate, $sid, $dbh)
	{
		// Using Transaction History 2.1.oplx
		// 2.6.2 Filtering - provide filtering options to Benson's code as JSON
		
		/*
		"columnHeaderData" : 
		{
			"typeColumnValues" : 
			{
				"Buy" : 1,
				"Sell" : 4,
				"Send" : 2,
				"Receive" : 3,
				"Mining Income" : 16
			},
			"assetColumnValues" : 
			{
				"BunnyCoin" : 1093,
				"Etherium" : 5,
				"Bitcoin" : 1,
				"Litecoin Cash" : 2543,
				"Piggycoin" : 362,
				"Minerium" : 2716
			},
			"exchangeTileNameValues" : 
			{
				"Exodus" : 1,
				"Gemini" : 2,
				"Coinbase" : 3,
				"Binance" : 4,
				"On Blockchain" : 5,
				"Unknown" : 6
			}
		},
		*/
		
		
		
		$responseObject														= array();
		$responseObject['retrievedFilterOptions']							= false;
		$responseObject['columnHeaderData']									= array();
		
		try
		{		
			$getTransactionTypeFilterOptionsForUser							= $dbh -> prepare("SELECT DISTINCT
	TransactionTypes.displayTransactionTypeLabel,
	TransactionTypes.transactionTypeID
FROM
	Transactions
	INNER JOIN TransactionTypes ON Transactions.FK_TransactionTypeID = TransactionTypes.transactionTypeID AND TransactionTypes.languageCode = 'EN'
WHERE
	Transactions.FK_AccountID = :accountID
ORDER BY
	TransactionTypes.displayTransactionTypeLabel");
	
			$getAssetTypeFilterOptionsForUser								= $dbh -> prepare("SELECT DISTINCT
	AssetTypes.description,
	AssetTypes.assetTypeID
FROM
	Transactions
	INNER JOIN AssetTypes ON Transactions.FK_AssetTypeID = AssetTypes.assetTypeID AND AssetTypes.languageCode = 'EN'
WHERE
	Transactions.FK_AccountID = :accountID
ORDER BY
	AssetTypes.description");
	
			$getExchangeTileNameFilterOptionsForUser						= $dbh -> prepare("SELECT DISTINCT
	AES_DECRYPT(ExchangeTiles.encryptedTileLabel, UNHEX(SHA2(:decryptionKey,512))) AS decryptedTileLabel,
	ExchangeTiles.exchangeTileID
FROM
	ExchangeTiles
	INNER JOIN Transactions ON ExchangeTiles.exchangeTileID = Transactions.FK_ExchangeTileID
WHERE
	Transactions.FK_AccountID = :accountID
ORDER BY
	AES_DECRYPT(ExchangeTiles.encryptedTileLabel, UNHEX(SHA2(:decryptionKey,512)))");

			$getTransactionTypeFilterOptionsForUser -> bindValue(':accountID', $userAccountID);
						
			if ($getTransactionTypeFilterOptionsForUser -> execute() && $getTransactionTypeFilterOptionsForUser -> rowCount() > 0)
			{
				$responseObject['retrievedFilterOptions']					= true;
				$typeColumnValues											= array();
				
				while ($row = $getTransactionTypeFilterOptionsForUser -> fetchObject())
				{
					$typeColumnValues[$row -> displayTransactionTypeLabel]	= $row -> transactionTypeID;
				}
				
				$responseObject['columnHeaderData']['typeColumnValues']		= $typeColumnValues;		
			}
			
			$getAssetTypeFilterOptionsForUser -> bindValue(':accountID', $userAccountID);
						
			if ($getAssetTypeFilterOptionsForUser -> execute() && $getAssetTypeFilterOptionsForUser -> rowCount() > 0)
			{
				$responseObject['retrievedFilterOptions']					= true;
				$assetColumnValues											= array();
				
				while ($row = $getAssetTypeFilterOptionsForUser -> fetchObject())
				{
					$assetColumnValues[$row -> description]					= $row -> assetTypeID;
				}
				
				$responseObject['columnHeaderData']['assetColumnValues']	= $assetColumnValues;		
			}
			
			$getExchangeTileNameFilterOptionsForUser -> bindValue(':accountID', $userAccountID);
			$getExchangeTileNameFilterOptionsForUser -> bindValue(':decryptionKey', $userEncryptionKey);
						
			if ($getExchangeTileNameFilterOptionsForUser -> execute() && $getExchangeTileNameFilterOptionsForUser -> rowCount() > 0)
			{
				$responseObject['retrievedFilterOptions']					= true;
				$exchangeTileNameValues										= array();
				
				while ($row = $getExchangeTileNameFilterOptionsForUser -> fetchObject())
				{
					$exchangeTileNameValues[$row -> decryptedTileLabel]		= $row -> exchangeTileID;
				}
				
				$responseObject['columnHeaderData']['exchangeTileNameValues']	= $exchangeTileNameValues;		
			}
		}
	    catch (PDOException $e) 
	    {
	    	errorLog($e -> getMessage());
		}
		
		return $responseObject;		
	}
	
	// END TRANSACTION HISTORY FUNCTIONS
	
	// SETTINGS: PAYMENT PAGE FUNCTIONS
	
	function getLastPaymentDataForUser($liuAccountID, $dbh)
	{
		$responseObject								= array();
		$responseObject['paymentDataFound']			= false;
		
		try
		{	
			$getInfoFromLastPayment																= $dbh -> prepare("SELECT
	PaymentHistory.paymentHistoryEventID,
	PaymentHistory.FK_AccountID,
	PaymentHistory.paymentMethod,
	AES_DECRYPT(PaymentHistory.encryptedFirstName, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))) AS decryptedFirstName,
	AES_DECRYPT(PaymentHistory.encryptedLastName, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))) AS decryptedLastName,	
	AES_DECRYPT(PaymentHistory.encryptedAddressOne, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))) AS decryptedAddressOne,	
	PaymentHistory.FK_CityID,
	PaymentHistory.FK_StateID,
	AES_DECRYPT(PaymentHistory.encryptedZipCode, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))) AS decryptedZipCode,	
	PaymentHistory.FK_CountryID,
	Countries.isoAlpha3Code AS countryAbbreviation,
	Cities.cityName,
	States.stateName,
	AES_DECRYPT(PaymentHistory.encryptedExpirationMonth, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))) AS decryptedExpirationMonth,	
	AES_DECRYPT(PaymentHistory.encryptedExpirationYear, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))) AS decryptedExpirationYear,	
	AES_DECRYPT(PaymentHistory.encryptedLastFourDigits, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))) AS decryptedLastFourDigits,
	PaymentHistory.wasPaid,
	PaymentHistory.FK_PlanTypeID,
	PaymentPlanTypes.paymentPlanName,
	PaymentHistory.FK_CardTypeID,
	CardType.cardTypeLabel
FROM
	PaymentHistory
	INNER JOIN Countries ON PaymentHistory.FK_CountryID = Countries.countryID 
	INNER JOIN Cities ON PaymentHistory.FK_CityID = Cities.cityID 
	INNER JOIN States ON PaymentHistory.FK_StateID = States.stateID
	INNER JOIN PaymentPlanTypes ON PaymentHistory.FK_PlanTypeID = PaymentPlanTypes.paymentPlanTypeID AND PaymentPlanTypes.languageCode = 'EN'
	INNER JOIN CardType ON PaymentHistory.FK_CardTypeID = CardType.cardTypeID AND CardType.languageCode = 'EN'
WHERE
	PaymentHistory.FK_AccountID = :accountID
ORDER BY
	PaymentHistory.paymentDateTime DESC
LIMIT 1");
		
			$getInfoFromLastPayment -> bindValue(':accountID', $liuAccountID);
							
			if ($getInfoFromLastPayment -> execute() && $getInfoFromLastPayment -> rowCount() > 0)
			{
				$responseObject['paymentDataFound']												= true;
				$responseObject['resultMessage']												= "Found last payment method information for user $liuAccountID";
				
				$row 																			= $getInfoFromLastPayment -> fetchObject();
				
				$loadedAccountID																= $row -> FK_AccountID;
				$responseObject['accountID']													= $liuAccountID;
				
				if ($loadedAccountID == $liuAccountID)
				{
					$responseObject['paymentHistoryEventID']									= $row -> paymentHistoryEventID;
					$responseObject['paymentMethod']											= $row -> paymentMethod;
					$responseObject['firstNameOnCard']											= $row -> decryptedFirstName;
					$responseObject['lastNameOnCard']											= $row -> decryptedLastName;
					$responseObject['addressStreet']											= $row -> decryptedAddressOne;
					$responseObject['addressCityName']											= $row -> cityName;
					$responseObject['addressStateName']											= $row -> stateName;
					$responseObject['zipCode']													= $row -> decryptedZipCode;
					$responseObject['countryCode']												= $row -> FK_CountryID;
					$responseObject['countryAbbreviation']										= $row -> countryAbbreviation;
					$responseObject['ccExpirationMonth']										= $row -> decryptedExpirationMonth;
					$responseObject['ccExpirationYear']											= $row -> decryptedExpirationYear;
					$responseObject['ccLastFourDigits']											= $row -> decryptedLastFourDigits;
					$responseObject['wasPaid']													= $row -> wasPaid;	
					$responseObject['FK_PlanTypeID']											= $row -> FK_PlanTypeID;	
					$responseObject['paymentPlanName']											= $row -> paymentPlanName;
					$responseObject['FK_CardTypeID']											= $row -> FK_CardTypeID;	
					$responseObject['cardTypeName']												= $row -> cardTypeLabel;
				}											
			}
			else
			{
				$responseObject['resultMessage']												= "No payment method information found for user $liuAccountID";
			}	
		}
	    catch (PDOException $e) 
	    {
	    	$responseObject['resultMessage']													= "Error: Could not retrieve payment method information for account $liuAccountID due to a database error: ".$e->getMessage();	
			
			errorLog($e->getMessage());
		}
		
		return $responseObject;	
	}

	function getPaymentHistoryDataForUser($liuAccountID, $dbh)
	{
		$responseObject																						= array();
		$responseObject['paymentHistoryDataFound']															= false;
		
		try
		{	
			$getPaymentHistoryData																			= $dbh -> prepare("SELECT
	PaymentHistory.paymentHistoryEventID,
	PaymentHistory.paymentDateTime,
	PaymentHistory.paymentMethod,
	PaymentHistory.paymentAmount,
	PaymentHistory.wasPaid,
	PaymentHistory.FK_PlanTypeID,
	PaymentPlanTypes.paymentPlanName,
	PaymentHistory.FK_CardTypeID,
	CardType.cardTypeLabel
FROM
	PaymentHistory
	INNER JOIN PaymentPlanTypes ON PaymentHistory.FK_PlanTypeID = PaymentPlanTypes.paymentPlanTypeID AND PaymentPlanTypes.languageCode = 'EN'
	INNER JOIN CardType ON PaymentHistory.FK_CardTypeID = CardType.cardTypeID AND CardType.languageCode = 'EN'
WHERE
	PaymentHistory.FK_AccountID = :accountID
ORDER BY
	PaymentHistory.paymentDateTime DESC");
		
			$getPaymentHistoryData -> bindValue(':accountID', $liuAccountID);
							
			if ($getPaymentHistoryData -> execute() && $getPaymentHistoryData -> rowCount() > 0)
			{
				$responseObject['paymentHistoryDataFound']													= true;
				$responseObject['resultMessage']															= "Found payment history records for user $liuAccountID";
				
				while ($row = $getPaymentHistoryData -> fetchObject())
				{
					$paymentHistoryEventID																	= $row -> paymentHistoryEventID;
					
					$responseObject['paymentHistory'][$paymentHistoryEventID]['paymentHistoryEventID']		= $paymentHistoryEventID;
					$responseObject['paymentHistory'][$paymentHistoryEventID]['paymentDateTime']			= $row -> paymentDateTime;
					$responseObject['paymentHistory'][$paymentHistoryEventID]['paymentMethod']				= $row -> paymentMethod;
					$responseObject['paymentHistory'][$paymentHistoryEventID]['paymentAmount']				= $row -> paymentAmount;
					$responseObject['paymentHistory'][$paymentHistoryEventID]['planType']					= $row -> FK_PlanTypeID;
					$responseObject['paymentHistory'][$paymentHistoryEventID]['paymentPlanName']			= $row -> paymentPlanName;
					$responseObject['paymentHistory'][$paymentHistoryEventID]['cardType']					= $row -> FK_CardTypeID;
					$responseObject['paymentHistory'][$paymentHistoryEventID]['cardTypeName']				= $row -> cardTypeLabel;
					$responseObject['paymentHistory'][$paymentHistoryEventID]['formattedPaymentAmount']		= getUSDFormattedCurrencyAmount($row -> paymentAmount);
					$responseObject['paymentHistory'][$paymentHistoryEventID]['wasPaid']					= $row -> wasPaid;	
				}											
			}
			else
			{
				$responseObject['resultMessage']															= "No payment history data found for user $liuAccountID";
			}	
		}
	    catch (PDOException $e) 
	    {
	    	$responseObject['resultMessage']																= "Error: Could not retrieve payment history data for account $liuAccountID due to a database error: ".$e->getMessage();	
			
			errorLog($e->getMessage());
		}
		
		return $responseObject;	
	}
	
	// END SETTINGS: PAYMENT PAGE FUNCTIONS
	
	// DATA IMPORT STATUS AND HISTORY functions
	
	function checkForActiveDataImportEventForUserAccountAndExchange($accountID, $transactionSourceID, $sid, $dbh)
	{
		$responseObject												= array();
		$responseObject['dataImportRecordFound']					= false;
		$responseObject['activeDataImportRecordFound']				= false;
		$responseObject['dataImportEventRecordID']					= 0;
		$responseObject['transactionSourceID']						= 0;
		$responseObject['importStageID']							= 0;
		$responseObject['importStageLabel']							= "";
		$responseObject['importTypeID']								= 0;
		$responseObject['importTypeLabel']							= "";
		$responseObject['beginImportDate']							= "";
		$responseObject['completeImportDate']						= "";
		$responseObject['resultMessage']							= "";
		
		try
		{	
			$checkForActiveDataImportEventForUserAccountAndExchange	= $dbh->prepare("SELECT
	dataImportHistoryEventRecordID,
	CASE
		WHEN FK_ImportStageID = 6 AND completeImportDate IS NOT NULL THEN 7
		ELSE FK_ImportStageID
	END AS FK_ImportStageID,
	FK_ImportTypeID,
	beginImportDate,
	completeImportDate,
	DataImportStages.dataImportStageName,
	DataImportTypes.importTypeLabel
FROM
	DataImportHistory
	INNER JOIN DataImportStages ON DataImportHistory.FK_ImportStageID = DataImportStages.dataImportStageID AND DataImportStages.languageCode = 'EN'
	INNER JOIN DataImportTypes ON DataImportHistory.FK_ImportTypeID = DataImportTypes.dataImportTypeRecordID AND DataImportTypes.languageCode = 'EN'
WHERE
	FK_AccountID = :accountID AND
	DataImportHistory.FK_DeletedBy IS NULL AND
	FK_TransactionSourceID = :transactionSourceID AND
	isActive = 1");

			$checkForActiveDataImportEventForUserAccountAndExchange -> bindValue(':accountID', $accountID);
			$checkForActiveDataImportEventForUserAccountAndExchange -> bindValue(':transactionSourceID', $transactionSourceID);
			
			if ($checkForActiveDataImportEventForUserAccountAndExchange -> execute() && $checkForActiveDataImportEventForUserAccountAndExchange -> rowCount() > 0)
			{
				$responseObject['dataImportRecordFound']			= true;
				
				$row 												= $checkForActiveDataImportEventForUserAccountAndExchange -> fetchObject();
				
				$dataImportHistoryEventRecordID						= $row -> dataImportHistoryEventRecordID;
				$beginImportDate									= $row -> beginImportDate;
				$importStageID										= $row -> FK_ImportStageID;
				$dataImportStageName								= $row -> dataImportStageName;
				$importTypeID										= $row -> FK_ImportTypeID;
				$dataImportTypeLabel								= $row -> importTypeLabel;
				$completeImportDate									= $row -> completeImportDate;
				
				$responseObject['activeDataImportRecordFound']		= true;	
				$responseObject['dataImportEventRecordID']			= $dataImportHistoryEventRecordID;
				$responseObject['transactionSourceID']				= $transactionSourceID;
				$responseObject['importStageID']					= $importStageID;
				$responseObject['importStageLabel']					= $dataImportStageName;
				$responseObject['importTypeID']						= $importTypeID;
				$responseObject['importTypeLabel']					= $dataImportTypeLabel;
				$responseObject['beginImportDate']					= $beginImportDate;
				$responseObject['completeImportDate']				= $completeImportDate;
				$responseObject['resultMessage']					= "Found current import process stage information.";
			}			
			
			$dbh 													= null;	
		}
	    catch (PDOException $e) 
	    {
	    	$responseObject['resultMessage']						= "Error: A database error has occurred.  We were unable to load the import process stage information. ".$e -> getMessage();	
			
			errorLog($e -> getMessage());
	
			die();
		}
		
		return $responseObject;
	}
	
	function checkForActiveDataImportEventForUserAccountImportTypeAndExchange($accountID, $transactionSourceID, $importTypeID, $sid, $dbh)
	{
		$responseObject															= array();
		$responseObject['dataImportRecordFound']									= false;
		$responseObject['activeDataImportRecordFound']							= false;
		$responseObject['dataImportEventRecordID']								= 0;
		$responseObject['transactionSourceID']									= 0;
		$responseObject['importStageID']											= 0;
		$responseObject['importStageLabel']										= "";
		$responseObject['importTypeID']											= $importTypeID;
		$responseObject['importTypeLabel']										= "";
		$responseObject['beginImportDate']										= "";
		$responseObject['completeImportDate']									= "";
		$responseObject['resultMessage']											= "";
		
		try
		{	
			$checkForActiveDataImportEventForUserAccountImportTypeAndExchange	= $dbh->prepare("SELECT
	dataImportHistoryEventRecordID,
	CASE
		WHEN FK_ImportStageID = 6 AND completeImportDate IS NOT NULL THEN 7
		ELSE FK_ImportStageID
	END AS FK_ImportStageID,
	beginImportDate,
	completeImportDate,
	DataImportStages.dataImportStageName,
	DataImportTypes.importTypeLabel
FROM
	DataImportHistory
	INNER JOIN DataImportStages ON DataImportHistory.FK_ImportStageID = DataImportStages.dataImportStageID AND DataImportStages.languageCode = 'EN'
	INNER JOIN DataImportTypes ON DataImportHistory.FK_ImportTypeID = DataImportTypes.dataImportTypeRecordID AND DataImportTypes.languageCode = 'EN'
WHERE
	FK_AccountID = :accountID AND
	DataImportHistory.FK_DeletedBy IS NULL AND
	FK_ImportTypeID = :importTypeID AND
	FK_TransactionSourceID = :transactionSourceID AND
	isActive = 1");

			$checkForActiveDataImportEventForUserAccountImportTypeAndExchange -> bindValue(':accountID', $accountID);
			$checkForActiveDataImportEventForUserAccountImportTypeAndExchange -> bindValue(':importTypeID', $importTypeID);
			$checkForActiveDataImportEventForUserAccountImportTypeAndExchange -> bindValue(':transactionSourceID', $transactionSourceID);
			
			if ($checkForActiveDataImportEventForUserAccountImportTypeAndExchange -> execute() && $checkForActiveDataImportEventForUserAccountImportTypeAndExchange -> rowCount() > 0)
			{
				$responseObject['dataImportRecordFound']				= true;
				
				$row 												= $checkForActiveDataImportEventForUserAccountImportTypeAndExchange -> fetchObject();
				
				$dataImportHistoryEventRecordID						= $row -> dataImportHistoryEventRecordID;
				$beginImportDate										= $row -> beginImportDate;
				$importStageID										= $row -> FK_ImportStageID;
				$dataImportStageName									= $row -> dataImportStageName;
				$dataImportTypeLabel									= $row -> importTypeLabel;
				$completeImportDate									= $row -> completeImportDate;
				
				$responseObject['activeDataImportRecordFound']		= true;	
				$responseObject['dataImportEventRecordID']			= $dataImportHistoryEventRecordID;
				$responseObject['transactionSourceID']				= $transactionSourceID;
				$responseObject['importStageID']						= $importStageID;
				$responseObject['importStageLabel']					= $dataImportStageName;
				$responseObject['importTypeLabel']					= $dataImportTypeLabel;
				$responseObject['beginImportDate']					= $beginImportDate;
				$responseObject['completeImportDate']				= $completeImportDate;
				$responseObject['resultMessage']						= "Found current import process stage information.";
			}			
			
			$dbh 													= null;	
		}
	    catch (PDOException $e) 
	    {
	    		$responseObject['resultMessage']							= "Error: A database error has occurred.  We were unable to load the import process stage information. ".$e -> getMessage();	
			
			errorLog($e -> getMessage());
	
			die();
		}
		
		return $responseObject;
	}
	
	function createDataImportAssetStatusRecord($accountID, $userEncryptionKey, $dataImportEventRecordID, $assetTypeID, $nativeCurrencyAssetTypeID, $globalCurrentDate, $sid, $dbh)
	{
		errorLog("createDataImportAssetStatusRecord($accountID, $userEncryptionKey, $dataImportEventRecordID, $assetTypeID, $nativeCurrencyAssetTypeID, $globalCurrentDate, $sid");
		
		$responseObject														= array();
		$responseObject['dataImportAssetStatusRecordCreated']				= false;
		$responseObject['importRecordID']									= 0;
		$responseObject['assetTypeID']										= 0;
		$responseObject['nativeCurrencyAssetTypeID']						= 0;
		$responseObject['resultMessage']									= "";
		
		errorLog("INSERT IGNORE DataImportEventAssetsWithStatusValues
(
	FK_DataImportEventRecordID,
	FK_AssetTypeID,
	FK_NativeCurrencyAssetTypeID,
	FK_AccountID,
	stage1CompletionDate,
	encryptedSid
)
VALUES
(
	$dataImportEventRecordID,
	$assetTypeID,
	$nativeCurrencyAssetTypeID,
	$accountID,
	'$globalCurrentDate',
	AES_ENCRYPT('$sid', UNHEX(SHA2('$userEncryptionKey',512)))
)");
		
		try
		{	
			$createDataImportAssetStatusRecord								= $dbh->prepare("INSERT IGNORE DataImportEventAssetsWithStatusValues
(
	FK_DataImportEventRecordID,
	FK_AssetTypeID,
	FK_NativeCurrencyAssetTypeID,
	FK_AccountID,
	stage1CompletionDate,
	encryptedSid
)
VALUES
(
	:FK_DataImportEventRecordID,
	:FK_AssetTypeID,
	:FK_NativeCurrencyAssetTypeID,
	:FK_AccountID,
	:stage1CompletionDate,
	AES_ENCRYPT(:sid, UNHEX(SHA2(:userEncryptionKey,512)))
)");

			$createDataImportAssetStatusRecord -> bindValue(':FK_AccountID', $accountID);
			$createDataImportAssetStatusRecord -> bindValue(':FK_AssetTypeID', $assetTypeID);
			$createDataImportAssetStatusRecord -> bindValue(':FK_NativeCurrencyAssetTypeID', $nativeCurrencyAssetTypeID);
			$createDataImportAssetStatusRecord -> bindValue(':FK_DataImportEventRecordID', $dataImportEventRecordID);
			$createDataImportAssetStatusRecord -> bindValue(':stage1CompletionDate', $globalCurrentDate);
			$createDataImportAssetStatusRecord -> bindValue(':sid', $sid);
			$createDataImportAssetStatusRecord -> bindValue(':userEncryptionKey', $userEncryptionKey);
			
			if ($createDataImportAssetStatusRecord -> execute())
			{
				$responseObject['dataImportAssetStatusRecordCreated']		= false;
				$responseObject['importRecordID']							= 0;
				$responseObject['assetTypeID']								= 0;
				$responseObject['resultMessage']								= "";			
			}			
			
			$dbh 															= null;	
		}
	    catch (PDOException $e) 
	    {
	    		$responseObject['resultMessage']									= "Error: A database error has occurred.  We were unable to create a data import asset status record. ".$e -> getMessage();	
			
			errorLog($e -> getMessage());
	
			die();
		}
		
		return $responseObject;
	}
	
	function createDataImportEventRecord($accountID, $authorID, $userEncryptionKey, $transactionSourceID, $exchangeTileID, $importStageID, $importTypeID, $globalCurrentDate, $sid, $dbh)
	{
		$responseObject														= array();
		$responseObject['dataImportRecordCreated']							= false;
		$responseObject['importRecordID']									= 0;
		$responseObject['resultMessage']										= "";
		
		errorLog("INSERT INTO DataImportHistory
(
	FK_AccountID,
	FK_AuthorID,
	FK_ImportStageID,
	FK_TransactionSourceID,
	FK_ExchangeTileID,
	isActive,
	FK_ImportTypeID,
	beginImportDate,
	encryptedSid
)
VALUES
(
	$accountID,
	$authorID,
	$importStageID,
	$transactionSourceID,
	$exchangeTileID,
	1,
	$importTypeID,
	'$globalCurrentDate',
	AES_ENCRYPT('$sid', UNHEX(SHA2('$userEncryptionKey',512)))
)");
		
		try
		{	
			$createDataImportEventRecord										= $dbh->prepare("INSERT INTO DataImportHistory
(
	FK_AccountID,
	FK_AuthorID,
	FK_ImportStageID,
	FK_TransactionSourceID,
	FK_ExchangeTileID,
	isActive,
	FK_ImportTypeID,
	beginImportDate,
	encryptedSid
)
VALUES
(
	:FK_AccountID,
	:FK_AuthorID,
	:FK_ImportStageID,
	:FK_TransactionSourceID,
	:FK_ExchangeTileID,
	:isActive,
	:FK_ImportTypeID,
	:beginImportDate,
	AES_ENCRYPT(:sid, UNHEX(SHA2(:userEncryptionKey,512)))
)");

			$createDataImportEventRecord -> bindValue(':FK_AccountID', $accountID);
			$createDataImportEventRecord -> bindValue(':FK_AuthorID', $authorID);
			$createDataImportEventRecord -> bindValue(':FK_ImportStageID', $importStageID);
			$createDataImportEventRecord -> bindValue(':FK_TransactionSourceID', $transactionSourceID);
			$createDataImportEventRecord -> bindValue(':FK_ExchangeTileID', $exchangeTileID);
			$createDataImportEventRecord -> bindValue(':isActive', 1);
			$createDataImportEventRecord -> bindValue(':FK_ImportTypeID', $importTypeID);
			$createDataImportEventRecord -> bindValue(':beginImportDate', $globalCurrentDate);
			$createDataImportEventRecord -> bindValue(':sid', $sid);
			$createDataImportEventRecord -> bindValue(':userEncryptionKey', $userEncryptionKey);
		
			if ($createDataImportEventRecord -> execute())
			{
				$recordID													= $dbh -> lastInsertId();
				
				$responseObject['dataImportRecordCreated']					= true;
				$responseObject['importRecordID']							= $recordID;
				$responseObject['resultMessage']							= "Data import event record created";		
			}
			else
			{
				errorLog("could not createDataImportEventRecord for $accountID, $authorID, $transactionSourceID, $importStageID, $importTypeID, $globalCurrentDate, $sid");
			}			
			
			$dbh 															= null;	
		}
	    catch (PDOException $e) 
	    {
	   	 	$responseObject['resultMessage']									= "Error: A database error has occurred.  We were unable to create a data import event record. ".$e -> getMessage();	
			
			errorLog($e -> getMessage());
	
			die();
		}
		
		return $responseObject;
	}
	
	function getAssetStatusRecordsForDataImportWithLastCompletedStageIDAndBaseCurrencyOnly($accountID, $userEncryptionKey, $dataImportEventRecordID, $globalCurrentDate, $sid, $dbh)
	{
		errorLog("getAssetStatusRecordsForDataImportWithLastCompletedStageIDAndBaseCurrencyOnly($accountID, $userEncryptionKey, $dataImportEventRecordID, $globalCurrentDate, $sid");
		
		errorLog("SELECT DISTINCT
	DataImportEventAssetsWithStatusValues.FK_AssetTypeID,
	CASE
		WHEN DataImportEventAssetsWithStatusValues.stage6CompletionDate IS NOT NULL THEN 6
		WHEN DataImportEventAssetsWithStatusValues.stage5CompletionDate IS NOT NULL THEN 5
		WHEN DataImportEventAssetsWithStatusValues.stage4CompletionDate IS NOT NULL THEN 4
		WHEN DataImportEventAssetsWithStatusValues.stage3CompletionDate IS NOT NULL THEN 3
		WHEN DataImportEventAssetsWithStatusValues.stage2CompletionDate IS NOT NULL THEN 2
		ELSE 1
	END AS lastCompletedStageID,
	assetType.assetTypeLabel
FROM
	DataImportEventAssetsWithStatusValues
	INNER JOIN DataImportHistory ON DataImportEventAssetsWithStatusValues.FK_DataImportEventRecordID = DataImportHistory.dataImportHistoryEventRecordID
	INNER JOIN AssetTypes assetType ON DataImportEventAssetsWithStatusValues.FK_AssetTypeID = assetType.assetTypeID AND assetType.languageCode = 'EN'
WHERE
	DataImportEventAssetsWithStatusValues.FK_AccountID = $accountID AND
	DataImportHistory.FK_DeletedBy IS NULL AND
	DataImportEventAssetsWithStatusValues.FK_DataImportEventRecordID = $dataImportEventRecordID");
		
		$responseObject																= array();
		$responseObject['dataImportAssetStatusRecordFound']							= false;
		$responseObject['importRecordID']											= $dataImportEventRecordID;
		$responseObject['resultMessage']											= "";
		
		try
		{	
			$getAssetStatusRecordsForDataImportWithLastCompletedStageID				= $dbh->prepare("SELECT DISTINCT
	DataImportEventAssetsWithStatusValues.FK_AssetTypeID,
	CASE
		WHEN DataImportEventAssetsWithStatusValues.stage6CompletionDate IS NOT NULL THEN 6
		WHEN DataImportEventAssetsWithStatusValues.stage5CompletionDate IS NOT NULL THEN 5
		WHEN DataImportEventAssetsWithStatusValues.stage4CompletionDate IS NOT NULL THEN 4
		WHEN DataImportEventAssetsWithStatusValues.stage3CompletionDate IS NOT NULL THEN 3
		WHEN DataImportEventAssetsWithStatusValues.stage2CompletionDate IS NOT NULL THEN 2
		ELSE 1
	END AS lastCompletedStageID,
	assetType.assetTypeLabel
FROM
	DataImportEventAssetsWithStatusValues
	INNER JOIN DataImportHistory ON DataImportEventAssetsWithStatusValues.FK_DataImportEventRecordID = DataImportHistory.dataImportHistoryEventRecordID
	INNER JOIN AssetTypes assetType ON DataImportEventAssetsWithStatusValues.FK_AssetTypeID = assetType.assetTypeID AND assetType.languageCode = 'EN'
WHERE
	DataImportEventAssetsWithStatusValues.FK_AccountID = :accountID AND
	DataImportHistory.FK_DeletedBy IS NULL AND
	DataImportEventAssetsWithStatusValues.FK_DataImportEventRecordID = :dataImportEventRecordID");

			$getAssetStatusRecordsForDataImportWithLastCompletedStageID -> bindValue(':accountID', $accountID);
			$getAssetStatusRecordsForDataImportWithLastCompletedStageID -> bindValue(':dataImportEventRecordID', $dataImportEventRecordID);
			
			if ($getAssetStatusRecordsForDataImportWithLastCompletedStageID -> execute() && $getAssetStatusRecordsForDataImportWithLastCompletedStageID -> rowCount() > 0)
			{
				$responseObject['dataImportAssetStatusRecordFound']					= true;
				$responseObject['resultMessage']										= "Found asset status records for data import event record $dataImportEventRecordID";
				
				while ($row = $getAssetStatusRecordsForDataImportWithLastCompletedStageID -> fetchObject())
				{
					$assetTypeID														= $row -> FK_AssetTypeID;
					
					$returnArray														= array();
					
					$returnArray['lastStageCompleted']								= $row -> lastCompletedStageID;
					$returnArray['assetTypeLabel']									= $row -> assetTypeLabel;
					
					$responseObject['assets'][$assetTypeID]							= $returnArray;
				}			
			}			
			
			$dbh 																	= null;	
		}
	    catch (PDOException $e) 
	    {
	    		$responseObject['resultMessage']											= "Error: A database error has occurred.  We were unable to create a data import asset status record. ".$e -> getMessage();	
			
			errorLog($e -> getMessage());
	
			die();
		}
		
		return $responseObject;
	}
	
/*
	function getAssetTypesForAllCompletedDataImports($accountID, $userEncryptionKey, $globalCurrentDate, $sid, $dbh)
	{
		errorLog("getAssetStatusRecordsForAllDataImportsWithLastCompletedStageIDAndBaseCurrencyOnly($accountID, $userEncryptionKey, $globalCurrentDate, $sid");
		
		errorLog("SELECT DISTINCT
	DataImportHistory.dataImportHistoryEventRecordID,
	DataImportEventAssetsWithStatusValues.FK_AssetTypeID,
	assetType.assetTypeLabel
FROM
	DataImportEventAssetsWithStatusValues
	INNER JOIN DataImportHistory ON DataImportEventAssetsWithStatusValues.FK_DataImportEventRecordID = DataImportHistory.dataImportHistoryEventRecordID
	INNER JOIN AssetTypes assetType ON DataImportEventAssetsWithStatusValues.FK_AssetTypeID = assetType.assetTypeID AND assetType.languageCode = 'EN'
WHERE
	DataImportEventAssetsWithStatusValues.FK_AccountID = $accountID AND
	DataImportEventAssetsWithStatusValues.stage6CompletionDate IS NOT NULL AND
	DataImportHistory.FK_DeletedBy IS NULL");
		
		$responseObject														= array();
		$responseObject['dataImportAssetStatusRecordFound']					= false;
		$responseObject['resultMessage']									= "";
		
		try
		{	
			$getAssetStatusRecordsForDataImportWithLastCompletedStageID		= $dbh->prepare("SELECT DISTINCT
	DataImportHistory.dataImportHistoryEventRecordID,
	DataImportEventAssetsWithStatusValues.FK_AssetTypeID,
	assetType.assetTypeLabel
FROM
	DataImportEventAssetsWithStatusValues
	INNER JOIN DataImportHistory ON DataImportEventAssetsWithStatusValues.FK_DataImportEventRecordID = DataImportHistory.dataImportHistoryEventRecordID
	INNER JOIN AssetTypes assetType ON DataImportEventAssetsWithStatusValues.FK_AssetTypeID = assetType.assetTypeID AND assetType.languageCode = 'EN'
WHERE
	DataImportEventAssetsWithStatusValues.FK_AccountID = :accountID AND
	DataImportEventAssetsWithStatusValues.stage6CompletionDate IS NOT NULL AND
	DataImportHistory.FK_DeletedBy IS NULL");

			$getAssetStatusRecordsForDataImportWithLastCompletedStageID -> bindValue(':accountID', $accountID);
			
			if ($getAssetStatusRecordsForDataImportWithLastCompletedStageID -> execute() && $getAssetStatusRecordsForDataImportWithLastCompletedStageID -> rowCount() > 0)
			{
				$responseObject['dataImportAssetStatusRecordFound']			= true;
				$responseObject['resultMessage']							= "Found asset status records for data import event record $dataImportEventRecordID";
				
				while ($row = $getAssetStatusRecordsForDataImportWithLastCompletedStageID -> fetchObject())
				{
					$assetTypeID											= $row -> FK_AssetTypeID;
					
					$dataImportHistoryEventRecordID							= $row -> dataImportHistoryEventRecordID;
					
					$responseObject['assets'][$dataImportHistoryEventRecordID][$assetTypeID]	= $row -> assetTypeLabel;
				}			
			}			
			
			$dbh 															= null;	
		}
	    catch (PDOException $e) 
	    {
	    	$responseObject['resultMessage']								= "Error: A database error has occurred.  We were unable to create a data import asset status record. ".$e -> getMessage();	
			
			errorLog($e -> getMessage());
	
			die();
		}
		
		return $responseObject;
	}
*/
	
	function getAssetStatusRecordsForDataImportWithLastCompletedStageID($accountID, $userEncryptionKey, $dataImportEventRecordID, $globalCurrentDate, $sid, $dbh)
	{
		$responseObject																= array();
		$responseObject['dataImportAssetStatusRecordFound']							= false;
		$responseObject['importRecordID']											= $dataImportEventRecordID;
		$responseObject['resultMessage']											= "";
		
		try
		{	
			$getAssetStatusRecordsForDataImportWithLastCompletedStageID				= $dbh->prepare("SELECT
	DataImportEventAssetsWithStatusValues.FK_AssetTypeID,
	DataImportEventAssetsWithStatusValues.FK_NativeCurrencyAssetTypeID,
	CASE
		WHEN DataImportEventAssetsWithStatusValues.stage6CompletionDate IS NOT NULL THEN 6
		WHEN DataImportEventAssetsWithStatusValues.stage5CompletionDate IS NOT NULL THEN 5
		WHEN DataImportEventAssetsWithStatusValues.stage4CompletionDate IS NOT NULL THEN 4
		WHEN DataImportEventAssetsWithStatusValues.stage3CompletionDate IS NOT NULL THEN 3
		WHEN DataImportEventAssetsWithStatusValues.stage2CompletionDate IS NOT NULL THEN 2
		ELSE 1
	END AS lastCompletedStageID,
	assetType.assetTypeLabel,
	nativeCurrencyAssetType.assetTypeLabel AS nativeCurrencyAssetTypeLabel
FROM
	DataImportEventAssetsWithStatusValues
	INNER JOIN AssetTypes assetType ON DataImportEventAssetsWithStatusValues.FK_AssetTypeID = assetType.assetTypeID AND assetType.languageCode = 'EN'
	INNER JOIN AssetTypes nativeCurrencyAssetType ON DataImportEventAssetsWithStatusValues.FK_NativeCurrencyAssetTypeID = nativeCurrencyAssetType.assetTypeID AND assetType.languageCode = 'EN'
WHERE
	DataImportEventAssetsWithStatusValues.FK_AccountID = :accountID AND
	DataImportEventAssetsWithStatusValues.FK_DataImportEventRecordID = :dataImportEventRecordID");

			$getAssetStatusRecordsForDataImportWithLastCompletedStageID -> bindValue(':accountID', $accountID);
			$getAssetStatusRecordsForDataImportWithLastCompletedStageID -> bindValue(':dataImportEventRecordID', $dataImportEventRecordID);
			
			if ($getAssetStatusRecordsForDataImportWithLastCompletedStageID -> execute() && $getAssetStatusRecordsForDataImportWithLastCompletedStageID -> rowCount() > 0)
			{
				$responseObject['dataImportAssetStatusRecordFound']					= true;
				$responseObject['resultMessage']										= "Found asset status records for data import event record $dataImportEventRecordID";
				
				while ($row = $getAssetStatusRecordsForDataImportWithLastCompletedStageID -> fetchObject())
				{
					$assetTypeID														= $row -> FK_AssetTypeID;
					$nativeCurrencyTypeID								 			= $row -> FK_NativeCurrencyAssetTypeID;
					
					$returnArray														= array();
					
					$returnArray['lastStageCompleted']								= $row -> lastCompletedStageID;
					$returnArray['assetTypeLabel']									= $row -> assetTypeLabel;
					$returnArray['nativeCurrencyAssetTypeLabel']						= $row -> nativeCurrencyAssetTypeLabel;
					
					$responseObject['assets'][$assetTypeID][$nativeCurrencyTypeID]	= $returnArray;
				}			
			}			
			
			$dbh 																	= null;	
		}
	    catch (PDOException $e) 
	    {
	    		$responseObject['resultMessage']											= "Error: A database error has occurred.  We were unable to create a data import asset status record. ".$e -> getMessage();	
			
			errorLog($e -> getMessage());
	
			die();
		}
		
		return $responseObject;
	}
	
	function getCurrentDataImportEventStatusForUser($accountID, $isActive, $sid, $dbh)
	{
		$responseObject												= array();
		$responseObject['dataImportRecordFound']					= false;
		$responseObject['activeDataImportRecordFound']				= false;
		$responseObject['dataImportEventRecordID']					= 0;
		$responseObject['currentImportStageID']						= 0;
		$responseObject['currentImportStageLabel']					= "";
		$responseObject['currentImportTypeID']						= 0;
		$responseObject['currentImportTypeLabel']					= "";
		$responseObject['transactionSourceID']						= 0;
		$responseObject['beginImportDate']							= "";
		$responseObject['completeImportDate']						= "";
		$responseObject['resultMessage']							= "";
		
		try
		{	
			$getDataImportEvenStatusForUser							= $dbh->prepare("SELECT
	dataImportHistoryEventRecordID,
	CASE
		WHEN FK_ImportStageID = 6 AND completeImportDate IS NOT NULL THEN 7
		ELSE FK_ImportStageID
	END AS FK_ImportStageID,
	FK_TransactionSourceID,
	isActive,
	FK_ImportTypeID,
	beginImportDate,
	completeImportDate,
	DataImportStages.dataImportStageName,
	DataImportTypes.importTypeLabel
FROM
	DataImportHistory
	INNER JOIN DataImportStages ON DataImportHistory.FK_ImportStageID = DataImportStages.dataImportStageID AND DataImportStages.languageCode = 'EN'
	INNER JOIN DataImportTypes ON DataImportHistory.FK_ImportTypeID = DataImportTypes.dataImportTypeRecordID AND DataImportTypes.languageCode = 'EN'
WHERE
	FK_AccountID = :FK_AccountID AND
	DataImportHistory.FK_DeletedBy IS NULL
ORDER BY
	dataImportHistoryEventRecordID DESC
LIMIT 1");

			$getDataImportEvenStatusForUser -> bindValue(':FK_AccountID', $accountID);
			
			if ($getDataImportEvenStatusForUser -> execute() && $getDataImportEvenStatusForUser -> rowCount() > 0)
			{
				$responseObject['dataImportRecordFound']			= true;
				
				$row 												= $getDataImportEvenStatusForUser -> fetchObject();
				
				$dataImportHistoryEventRecordID						= $row -> dataImportHistoryEventRecordID;
				$transactionSourceID								= $row -> FK_TransactionSourceID;
				$isActiveRetrievedValue								= $row -> isActive;
				$beginImportDate									= $row -> beginImportDate;
				$importStageID										= $row -> FK_ImportStageID;
				$dataImportStageName								= $row -> dataImportStageName;
				$importTypeID										= $row -> FK_ImportTypeID;
				$dataImportTypeLabel								= $row -> importTypeLabel;
				$completeImportDate									= $row -> completeImportDate;
				
				if ($isActive == true || $isActive == 1 || $isActiveRetrievedValue == 1 || $isActiveRetrievedValue == true)
				{
					$responseObject['activeDataImportRecordFound']	= true;	
				}
				
				$responseObject['dataImportEventRecordID']			= $dataImportHistoryEventRecordID;
				$responseObject['currentImportStageID']				= $importStageID;
				$responseObject['currentImportStageLabel']			= $dataImportStageName;
				$responseObject['importTypeID']						= $importTypeID;
				$responseObject['importTypeLabel']					= $dataImportTypeLabel;
				$responseObject['transactionSourceID']				= $transactionSourceID;
				$responseObject['beginImportDate']					= $beginImportDate;
				$responseObject['completeImportDate']				= $completeImportDate;
				$responseObject['resultMessage']					= "Found current import process stage information.";
			}			
			
			$dbh 													= null;	
		}
	    catch (PDOException $e) 
	    {
	    	$responseObject['resultMessage']						= "Error: A database error has occurred.  We were unable to load the import process stage information. ".$e -> getMessage();	
			
			errorLog($e -> getMessage());
	
			die();
		}
		
		return $responseObject;
	}
	
	function getDataImportEventStatusForSpecifiedImportEvent($accountID, $dataImportEventRecordID, $sid, $dbh)
	{
		$responseObject												= array();
		$responseObject['dataImportRecordFound']					= false;
		$responseObject['activeDataImportRecordFound']				= false;
		$responseObject['dataImportEventRecordID']					= 0;
		$responseObject['transactionSourceID']						= 0;
		$responseObject['importStageID']							= 0;
		$responseObject['importStageLabel']							= "";
		$responseObject['importTypeID']								= 0;
		$responseObject['importTypeLabel']							= "";
		$responseObject['beginImportDate']							= "";
		$responseObject['completeImportDate']						= "";
		$responseObject['resultMessage']							= "";
		
		try
		{	
			$getDataImportEventStatusForSpecifiedImportEvent		= $dbh->prepare("SELECT
	FK_TransactionSourceID,
	CASE
		WHEN FK_ImportStageID = 6 AND completeImportDate IS NOT NULL THEN 7
		ELSE FK_ImportStageID
	END AS FK_ImportStageID,
	isActive,
	FK_ImportTypeID,
	beginImportDate,
	completeImportDate,
	DataImportStages.dataImportStageName,
	DataImportTypes.importTypeLabel
FROM
	DataImportHistory
	INNER JOIN DataImportStages ON DataImportHistory.FK_ImportStageID = DataImportStages.dataImportStageID AND DataImportStages.languageCode = 'EN'
	INNER JOIN DataImportTypes ON DataImportHistory.FK_ImportTypeID = DataImportTypes.dataImportTypeRecordID AND DataImportTypes.languageCode = 'EN'
WHERE
	FK_AccountID = :accountID AND
	DataImportHistory.FK_DeletedBy IS NULL AND
	dataImportHistoryEventRecordID = :dataImportHistoryEventRecordID");

			$getDataImportEventStatusForSpecifiedImportEvent -> bindValue(':accountID', $accountID);
			$getDataImportEventStatusForSpecifiedImportEvent -> bindValue(':dataImportHistoryEventRecordID', $dataImportEventRecordID);
			
			if ($getDataImportEventStatusForSpecifiedImportEvent -> execute() && $getDataImportEventStatusForSpecifiedImportEvent -> rowCount() > 0)
			{
				$responseObject['dataImportRecordFound']			= true;
				
				$row 												= $getDataImportEventStatusForSpecifiedImportEvent -> fetchObject();
				
				$transactionSourceID									= $row -> FK_TransactionSourceID;
				$isActiveRetrievedValue								= $row -> isActive;
				$beginImportDate										= $row -> beginImportDate;
				$importStageID										= $row -> FK_ImportStageID;
				$dataImportStageName									= $row -> dataImportStageName;
				$importTypeID										= $row -> FK_ImportTypeID;
				$dataImportTypeLabel									= $row -> importTypeLabel;
				$completeImportDate									= $row -> completeImportDate;				
				
				if ($isActiveRetrievedValue == 1 || $isActiveRetrievedValue == true)
				{
					$responseObject['activeDataImportRecordFound']	= true;	
				}
				
				$responseObject['dataImportEventRecordID']			= $dataImportEventRecordID;
				$responseObject['transactionSourceID']				= $transactionSourceID;
				$responseObject['importStageID']					= $importStageID;
				$responseObject['importStageLabel']					= $dataImportStageName;
				$responseObject['importTypeID']						= $importTypeID;
				$responseObject['importTypeLabel']					= $dataImportTypeLabel;
				$responseObject['beginImportDate']					= $beginImportDate;
				$responseObject['completeImportDate']				= $completeImportDate;
				$responseObject['resultMessage']					= "Found current import process stage information.";
			}			
			
			$dbh 													= null;	
		}
	    catch (PDOException $e) 
	    {
	    	$responseObject['resultMessage']						= "Error: A database error has occurred.  We were unable to load the import process stage information. ".$e -> getMessage();	
			
			errorLog($e -> getMessage());
	
			die();
		}
		
		return $responseObject;
	}
	
	function getLastDataImportDateForUserAccountAndExchange($accountID, $transactionSourceID, $sid, $dbh)
	{
		$responseObject														= array();
		$responseObject['retrievedLastDataImportDateExchange']				= false;
		
		try
		{		
			$getLastDataImportDateForUserAccountAndExchange					= $dbh -> prepare("SELECT
	completeImportDate,
	CASE
		WHEN completeImportDate IS NOT NULL THEN DATE_FORMAT(completeImportDate, '%b %d, %Y')
		ELSE ''
	END AS formattedCompleteImportDate
FROM
	DataImportHistory
WHERE
	FK_AccountID = :accountID AND
	DataImportHistory.FK_DeletedBy IS NULL AND
	FK_TransactionSourceID = :transactionSourceID
ORDER BY
	completeImportDate DESC
LIMIT 1");

			$getLastDataImportDateForUserAccountAndExchange -> bindValue(':accountID', $accountID);
			$getLastDataImportDateForUserAccountAndExchange -> bindValue(':transactionSourceID', $transactionSourceID);
						
			if ($getLastDataImportDateForUserAccountAndExchange -> execute() && $getLastDataImportDateForUserAccountAndExchange -> rowCount() > 0)
			{
				
				$responseObject['retrievedLastDataImportDateExchange']		= true;
				
				$row 														= $getLastDataImportDateForUserAccountAndExchange -> fetchObject();
				
				$responseObject['completeImportDate']						= $row -> completeImportDate;
				$responseObject['formattedCompleteImportDate']				= $row -> formattedCompleteImportDate;						
			}
			else
			{
				$responseObject['resultMessage']								= "No completion date found for $accountID";
			}
		}
	    catch (PDOException $e) 
	    {
	    	$responseObject['resultMessage']										= "Could not retrieve completion date for $accountID due to a database error: ".$e -> getMessage();
			
			errorLog($e -> getMessage());
	
			die();
		}
	
		return $responseObject;	
	}
	
	function getLastDataImportDateForUserAccountAndExchangeTile($accountID, $exchangeTileID, $sid, $dbh)
	{
		$responseObject														= array();
		$responseObject['retrievedLastDataImportDateExchange']				= false;
		
		try
		{		
			$getLastDataImportDateForUserAccountAndExchange					= $dbh -> prepare("SELECT
	completeImportDate,
	CASE
		WHEN completeImportDate IS NOT NULL THEN DATE_FORMAT(completeImportDate, '%b %d, %Y')
		ELSE ''
	END AS formattedCompleteImportDate
FROM
	DataImportHistory
WHERE
	FK_AccountID = :accountID AND
	DataImportHistory.FK_DeletedBy IS NULL AND
	FK_ExchangeTileID = :exchangeTileID
ORDER BY
	completeImportDate DESC
LIMIT 1");

			$getLastDataImportDateForUserAccountAndExchange -> bindValue(':accountID', $accountID);
			$getLastDataImportDateForUserAccountAndExchange -> bindValue(':exchangeTileID', $exchangeTileID);
						
			if ($getLastDataImportDateForUserAccountAndExchange -> execute() && $getLastDataImportDateForUserAccountAndExchange -> rowCount() > 0)
			{
				
				$responseObject['retrievedLastDataImportDateExchange']		= true;
				
				$row 														= $getLastDataImportDateForUserAccountAndExchange -> fetchObject();
				
				$responseObject['completeImportDate']						= $row -> completeImportDate;
				$responseObject['formattedCompleteImportDate']				= $row -> formattedCompleteImportDate;						
			}
			else
			{
				$responseObject['resultMessage']							= "No completion date found for $accountID";
			}
		}
	    catch (PDOException $e) 
	    {
	    	$responseObject['resultMessage']								= "Could not retrieve completion date for $accountID due to a database error: ".$e -> getMessage();
			
			errorLog($e -> getMessage());
	
			die();
		}
	
		return $responseObject;	
	}
	
	function updateDataImportStageCompletionDateForAssetType($accountID, $dataImportHistoryEventRecordID, $assetTypeID, $nativeCurrencyTypeID, $importStageID, $globalCurrentDate, $sid, $dbh)
	{
		errorLog("updateDataImportStageCompletionDateForAssetType($accountID, $dataImportHistoryEventRecordID, $assetTypeID, $nativeCurrencyTypeID, $importStageID, $globalCurrentDate, $sid");
		
		$responseObject														= array();
		$responseObject['dataImportAssetStatusRecordUpdated']				= false;
		$responseObject['importStageID']										= $importStageID;
		$responseObject['assetTypeID']										= $assetTypeID;
		$responseObject['nativeCurrencyAssetTypeID']							= $nativeCurrencyTypeID;
		$responseObject['resultMessage']										= "";
		
		errorLog("UPDATE 
		DataImportEventAssetsWithStatusValues
	SET
		stage".$importStageID."CompletionDate = $globalCurrentDate
	WHERE
		FK_DataImportEventRecordID = $dataImportHistoryEventRecordID AND
		FK_AccountID = $accountID AND
		FK_AssetTypeID = $assetTypeID AND
		FK_NativeCurrencyAssetTypeID = $nativeCurrencyTypeID");
		
		if (is_numeric($importStageID) && is_int($importStageID) && $importStageID > 1 && $importStageID < 7)
		{
			try
			{	
				$updateDataImportStageCompletionDateForAssetTypeSQL			= "UPDATE 
		DataImportEventAssetsWithStatusValues
	SET
		stage".$importStageID."CompletionDate = :globalCurrentDate
	WHERE
		FK_DataImportEventRecordID = :dataImportHistoryEventRecordID AND
		FK_AccountID = :accountID AND
		FK_AssetTypeID = :assetTypeID AND
		FK_NativeCurrencyAssetTypeID = :nativeCurrencyTypeID";
				
				$updateDataImportStageCompletionDateForAssetTypeSQL			= $dbh -> prepare($updateDataImportStageCompletionDateForAssetTypeSQL);
	
				$updateDataImportStageCompletionDateForAssetTypeSQL -> bindValue(':accountID', $accountID);
				$updateDataImportStageCompletionDateForAssetTypeSQL -> bindValue(':assetTypeID', $assetTypeID);
				$updateDataImportStageCompletionDateForAssetTypeSQL -> bindValue(':nativeCurrencyTypeID', $nativeCurrencyTypeID);
				$updateDataImportStageCompletionDateForAssetTypeSQL -> bindValue(':dataImportHistoryEventRecordID', $dataImportHistoryEventRecordID);
				$updateDataImportStageCompletionDateForAssetTypeSQL -> bindValue(':globalCurrentDate', $globalCurrentDate);
			
				if ($updateDataImportStageCompletionDateForAssetTypeSQL -> execute())
				{
					$responseObject['dataImportAssetStatusRecordUpdated']	= true;
					$responseObject['resultMessage']							= "Data import completion date for asset type $assetTypeID and $dataImportHistoryEventRecordID completed for $accountID";	
				}			
				
				$dbh 														= null;	
			}
		    catch (PDOException $e) 
		    {
		    		$responseObject['resultMessage']								= "Error: A database error has occurred.  We were unable to update the import stage completion date for asset type $assetTypeID and $dataImportHistoryEventRecordID. ".$e->getMessage();	
				
				errorLog($e->getMessage());
		
				die();
			}		
		}

		return $responseObject;
	}
	
	function updateDataImportStageCompletionDateForAssetTypeAllRecords($accountID, $dataImportHistoryEventRecordID, $assetTypeID, $importStageID, $globalCurrentDate, $sid, $dbh)
	{
		$responseObject														= array();
		$responseObject['dataImportAssetStatusRecordUpdated']				= false;
		$responseObject['importStageID']										= $importStageID;
		$responseObject['assetTypeID']										= $assetTypeID;
		$responseObject['resultMessage']										= "";
		
		errorLog("UPDATE 
		DataImportEventAssetsWithStatusValues
	SET
		stage".$importStageID."CompletionDate = $globalCurrentDate
	WHERE
		FK_DataImportEventRecordID = $dataImportHistoryEventRecordID AND
		FK_AccountID = $accountID AND
		FK_AssetTypeID = $assetTypeID");
		
		if (is_numeric($importStageID) && is_int($importStageID) && $importStageID > 1 && $importStageID < 7)
		{
			try
			{	
				$updateDataImportStageCompletionDateForAssetTypeSQL			= "UPDATE 
		DataImportEventAssetsWithStatusValues
	SET
		stage".$importStageID."CompletionDate = :globalCurrentDate
	WHERE
		FK_DataImportEventRecordID = :dataImportHistoryEventRecordID AND
		FK_AccountID = :accountID AND
		FK_AssetTypeID = :assetTypeID";
				
				$updateDataImportStageCompletionDateForAssetTypeSQL			= $dbh -> prepare($updateDataImportStageCompletionDateForAssetTypeSQL);
	
				$updateDataImportStageCompletionDateForAssetTypeSQL -> bindValue(':accountID', $accountID);
				$updateDataImportStageCompletionDateForAssetTypeSQL -> bindValue(':assetTypeID', $assetTypeID);
				$updateDataImportStageCompletionDateForAssetTypeSQL -> bindValue(':dataImportHistoryEventRecordID', $dataImportHistoryEventRecordID);
				$updateDataImportStageCompletionDateForAssetTypeSQL -> bindValue(':globalCurrentDate', $globalCurrentDate);
			
				if ($updateDataImportStageCompletionDateForAssetTypeSQL -> execute())
				{
					$responseObject['dataImportAssetStatusRecordUpdated']	= true;
					$responseObject['resultMessage']							= "Data import completion date for asset type $assetTypeID and $dataImportHistoryEventRecordID completed for $accountID";	
				}			
				
				$dbh 														= null;	
			}
		    catch (PDOException $e) 
		    {
		    		$responseObject['resultMessage']								= "Error: A database error has occurred.  We were unable to update the import stage completion date for asset type $assetTypeID and $dataImportHistoryEventRecordID. ".$e->getMessage();	
				
				errorLog($e->getMessage());
		
				die();
			}		
		}

		return $responseObject;
	}
	
	function updateDataImportEventStatus($accountID, $authorID, $dataImportHistoryEventRecordID, $importStageID, $globalCurrentDate, $sid, $dbh)
	{
		$responseObject												= array();
		$responseObject['dataImportRecordUpdated']					= false;
		$responseObject['currentImportStageID']						= 0;
		$responseObject['resultMessage']							= "";
		
		try
		{	
			$updateDataImportEventRecordStageSQL					= "UPDATE DataImportHistory
SET
	FK_ImportStageID = :importStageID
WHERE
	FK_AccountID = :accountID AND
	DataImportHistory.FK_DeletedBy IS NULL AND
	dataImportHistoryEventRecordID = :dataImportHistoryEventRecordID";
	
			if ($importStageID == 6)
			{
				$updateDataImportEventRecordStageSQL				= "UPDATE DataImportHistory
SET
	FK_ImportStageID = 6,
	completeImportDate = :completionDate,
	isActive = 0
WHERE
	FK_AccountID = :accountID AND
	DataImportHistory.FK_DeletedBy IS NULL AND
	dataImportHistoryEventRecordID = :dataImportHistoryEventRecordID";	
			}
	
			$updateDataImportEventRecordStage						= $dbh->prepare($updateDataImportEventRecordStageSQL);

			$updateDataImportEventRecordStage -> bindValue(':accountID', $accountID);
			$updateDataImportEventRecordStage -> bindValue(':dataImportHistoryEventRecordID', $dataImportHistoryEventRecordID);

			if ($importStageID == 6)
			{
				$updateDataImportEventRecordStage -> bindValue(':completionDate', $globalCurrentDate);		
			}
			else
			{
				$updateDataImportEventRecordStage -> bindValue(':importStageID', $importStageID);	
			}
		
			if ($updateDataImportEventRecordStage -> execute())
			{
				$responseObject['dataImportRecordUpdated']			= true;
				$responseObject['currentImportStageID']				= $importStageID;
				
				if ($importStageID == 6)
				{
					$responseObject['resultMessage']				= "Data import $dataImportHistoryEventRecordID completed for $accountID";	
				}
				else
				{
					$responseObject['resultMessage']				= "Data import $dataImportHistoryEventRecordID status set to $importStageID for $accountID";	
				}
							
			}			
			
			$dbh 													= null;	
		}
	    catch (PDOException $e) 
	    {
	    	$responseObject['resultMessage']						= "Error: A database error has occurred.  We were unable to update the import stage ID for $dataImportHistoryEventRecordID. ".$e->getMessage();	
			
			errorLog($e->getMessage());
	
			die();
		}
		
		return $responseObject;
	}
	
	function updateDataImportEventTypeAndStatus($accountID, $authorID, $dataImportHistoryEventRecordID, $importStageID, $importTypeID, $globalCurrentDate, $sid, $dbh)
	{
		$responseObject														= array();
		$responseObject['dataImportRecordUpdated']							= false;
		$responseObject['currentImportStageID']								= 0;
		$responseObject['currentImportTypeID']								= 0;
		$responseObject['resultMessage']									= "";
		
		try
		{	
			$updateDataImportEventRecordTypeAndStage						= $dbh->prepare("UPDATE DataImportHistory
SET
	FK_ImportStageID = :importStageID,
	FK_ImportTypeID = :importTypeID
WHERE
	FK_AccountID = :accountID AND
	DataImportHistory.FK_DeletedBy IS NULL AND
	dataImportHistoryEventRecordID = :dataImportHistoryEventRecordID");

			$updateDataImportEventRecordTypeAndStage -> bindValue(':accountID', $accountID);
			$updateDataImportEventRecordTypeAndStage -> bindValue(':dataImportHistoryEventRecordID', $dataImportHistoryEventRecordID);

			$updateDataImportEventRecordTypeAndStage -> bindValue(':importStageID', $importStageID);
			$updateDataImportEventRecordTypeAndStage -> bindValue(':importTypeID', $importTypeID);	
			
			if ($updateDataImportEventRecordTypeAndStage -> execute())
			{
				$responseObject['dataImportRecordUpdated']					= true;
				$responseObject['currentImportStageID']						= $importStageID;
				$responseObject['currentImportTypeID']						= $importTypeID;
				
				$responseObject['resultMessage']							= "Data import $dataImportHistoryEventRecordID status set to $importStageID and type set to $importTypeID for $accountID";						
			}			
			
			$dbh 															= null;	
		}
	    catch (PDOException $e) 
	    {
	    	$responseObject['resultMessage']								= "Error: A database error has occurred.  We were unable to update the import stage ID or type ID for $dataImportHistoryEventRecordID. ".$e->getMessage();	
			
			errorLog($e->getMessage());
	
			die();
		}
		
		return $responseObject;
	}
	
	// END DATA IMPORT STATUS AND HISTORY functions
	
	// ACCOUNT METHOD SPECIFIC CALCULATIONS USING GENERIC TRANSACTION TABLE
	
	function createCommonTransactionsForCoinbaseTransactions($accountID, $userEncryptionKey, $globalCurrentDate, $sid, $dbh)
	{
		$responseObject														= array();
		$responseObject['createCommonTransactionsForCoinbaseTransactions']	= false;
		
		$transactionSourceID												= 2;
		$transactionStatusID												= 1;
		$transactionStatusLabel												= "complete";
		$transactionSourceLabel												= "Coinbase";
		
		try
		{		
			$getCoinbaseTransactionRecords									= $dbh -> prepare("SELECT
		CoinbaseTransactions.transactionID,
		CoinbaseTransactions.FK_ExchangeTileID,
		CoinbaseTransactions.FK_GlobalTransactionIdentificationRecordID,
		CoinbaseTransactions.FK_AuthorID AS authorID,
		CoinbaseTransactions.FK_AccountID AS accountID,
		CoinbaseTransactions.FK_TransactionTypeID AS transactionTypeID,
		CoinbaseTransactions.FK_AssetTypeID AS baseCurrencyID,
		AssetTypes.assetTypeLabel AS baseCurrencyName,
		2 AS quoteSpotPriceCurrencyID,
		'USD' AS quoteSpotPriceCurrencyName,
		CoinbaseTransactions.creationDate,
		CoinbaseTransactions.creationDate AS transactionDate,
		CoinbaseTransactions.transactionTimestamp,
		AES_DECRYPT(encryptedCoinbaseTransactionIDValue, UNHEX(SHA2(:userEncryptionKey,512))) AS vendorTransactionID,
		ABS(CoinbaseTransactions.cryptoCurrencyAmount) AS btcQuantityTransacted,
		ABS(CoinbaseTransactions.nativeAmount) AS usdQuantityTransacted,
		CoinbaseTransactions.spotPrice AS spotPriceAtTimeOfTransaction,
		CoinbaseTransactions.spotPrice AS btcPriceAtTimeOfTransaction,
		ABS(CoinbaseTransactions.cryptoCurrencyAmount * CoinbaseTransactions.spotPrice) AS usdTransactionAmountWithFees,
		CoinbaseTransactions.networkTransactionFeeAmount,
		ABS(CoinbaseTransactions.networkTransactionFeeAmount * CoinbaseTransactions.spotPrice) AS usdFeeAmount,
		ABS((CoinbaseTransactions.cryptoCurrencyAmount * CoinbaseTransactions.spotPrice) - (CoinbaseTransactions.networkTransactionFeeAmount * CoinbaseTransactions.spotPrice)) AS transactionAmountMinusFeeInUSD,
		ABS((CoinbaseTransactions.cryptoCurrencyAmount * CoinbaseTransactions.spotPrice) + (CoinbaseTransactions.networkTransactionFeeAmount * CoinbaseTransactions.spotPrice)) AS transactionAmountPlusFeeInUSD,
		TRIM(
		CONCAT(
			AES_DECRYPT(encryptedDetailsTitle, UNHEX(SHA2(:userEncryptionKey,512))),
			' ',
			AES_DECRYPT(encryptedDetailsSubTitle, UNHEX(SHA2(:userEncryptionKey,512)))
		)
		) AS  providerNotes,
		CoinbaseTransactions.isDebit,
		CoinbaseTransactions.FK_SourceAddressID,
		CoinbaseTransactions.FK_DestinationAddressID,
		TransactionTypes.displayTransactionTypeLabel,
		TransactionTypes.transactionTypeLabel
	FROM
		CoinbaseTransactions
		INNER JOIN TransactionTypes ON CoinbaseTransactions.FK_TransactionTypeID = TransactionTypes.transactionTypeID AND TransactionTypes.languageCode = 'EN'
		INNER JOIN AssetTypes ON CoinbaseTransactions.FK_AssetTypeID = AssetTypes.assetTypeID AND AssetTypes.languageCode = 'EN'
	WHERE
		CoinbaseTransactions.FK_AccountID = :accountID
	ORDER BY
		CoinbaseTransactions.transactionTimestamp");
	
			$getCoinbaseTransactionRecords -> bindValue(':accountID', $accountID);
			$getCoinbaseTransactionRecords -> bindValue(':userEncryptionKey', $userEncryptionKey);
		
			if ($getCoinbaseTransactionRecords -> execute() && $getCoinbaseTransactionRecords -> rowCount() > 0)
			{
				errorLog("began get coinbase crypto transaction records ".$getCoinbaseTransactionRecords -> rowCount() > 0);
				
				while ($row = $getCoinbaseTransactionRecords -> fetchObject())
				{		
					$transactionID											= $row -> transactionID;
					$exchangeTileID											= $row -> FK_ExchangeTileID;
					$globalTransactionIdentificationRecordID				= $row -> FK_GlobalTransactionIdentificationRecordID;
					$accountID												= $row -> accountID;	
					$authorID												= $row -> authorID;
						
					$transactionTypeID										= $row -> transactionTypeID;
					$baseCurrencyID											= $row -> baseCurrencyID; // was assetTypeID - done
					$baseCurrencyName										= $row -> baseCurrencyName; // assetTypeName - not needed
							
					$quoteSpotPriceCurrencyID								= $row -> quoteSpotPriceCurrencyID; // was spotPriceCurrencyTypeID - done, needs verification
					$quoteSpotPriceCurrencyName								= $row -> quoteSpotPriceCurrencyName; // was spotPriceCurrencyType	
						
							
					$amount													= $row -> btcQuantityTransacted;
					$fee													= $row -> networkTransactionFeeAmount;
					$baseToUSDCurrencySpotPrice								= $row -> spotPriceAtTimeOfTransaction;
					$btcSpotPriceAtTimeOfTransaction						= $row -> btcPriceAtTimeOfTransaction;
					$creationDate											= $row -> creationDate;
					$transactionDate										= $row -> transactionDate;
					$transactionTimestamp									= $row -> transactionTimestamp;
					$vendorTransactionID									= $row -> vendorTransactionID;	
					$transactionAmountInUSD									= $row -> usdQuantityTransacted;
					$transactionAmountMinusFeeInUSD							= $row -> transactionAmountMinusFeeInUSD;
					$transactionAmountPlusFeeInUSD							= $row -> transactionAmountPlusFeeInUSD;
					$feeAmountInUSD											= $row -> usdFeeAmount;
					$usdTransactionAmountWithFees							= $row -> usdTransactionAmountWithFees;
					$providerNotes											= $row -> providerNotes;
					$transactionTypeLabel									= $row -> transactionTypeLabel;
					$displayTransactionTypeLabel							= $row -> displayTransactionTypeLabel;
					$isDebit												= $row -> isDebit;
						
					$sourceWalletID											= $row -> FK_SourceAddressID;
					$destinationWalletID									= $row -> FK_DestinationAddressID;
						
					$responseObject['processingTransaction'][]				= $vendorTransactionID;
					
					$getNativeAndCommonTransactionRecordIDsResult			= getNativeAndCommonTransactionRecordIDsForGlobalTransactionIdentificationRecordID($accountID, $baseCurrencyID, $vendorTransactionID, $transactionSourceID, $globalTransactionIdentificationRecordID, $globalCurrentDate, $sid, $dbh);
					
					errorLog("commonTransactionID: ". $getNativeAndCommonTransactionRecordIDsResult['commonTransactionRecordID']);
			
					$commonTransactionID									= $getNativeAndCommonTransactionRecordIDsResult['commonTransactionRecordID'];
			
					if (empty($commonTransactionID))
					{
						$unspentTransactionTotal							= 0;
						$unfundedSpendTotal									= 0;
						
						if ($isDebit == 0)
						{
							$unspentTransactionTotal  						= $amount;  // shouldn't this be the amount minus the fee amount
						}
						else if ($isDebit == 1)
						{
							$unfundedSpendTotal								= $amount; 	// shouldn't this be the amount minus the fee amount
						}	
						
						$sourceWallet										= new CompleteCryptoWallet();
						$destinationWallet									= new CompleteCryptoWallet();
				
						$sourceWalletResponseObject							= $sourceWallet -> instantiateWalletUsingCryptoWalletRecordID($accountID, $sourceWalletID, $userEncryptionKey, $dbh);
				
						if ($sourceWalletResponseObject['instantiatedRecord'] == false)
						{
							errorLog("Could not instantiate source Complete Crypto Wallet record $accountID");
						}
						
						$destinationWalletResponseObject					= $destinationWallet -> instantiateWalletUsingCryptoWalletRecordID($accountID, $destinationWalletID, $userEncryptionKey, $dbh);
				
						if ($destinationWalletResponseObject['instantiatedRecord'] == false)
						{
							errorLog("Could not instantiate destination Complete Crypto Wallet record $accountID, $destinationWalletID");
						}
						
						errorLog($vendorTransactionID."<BR>");
					
						$cryptoTransaction									= new CryptoTransaction();
					
						$cryptoTransaction -> setData(0, $accountID, $authorID, $exchangeTileID, $globalTransactionIdentificationRecordID, $transactionTypeID, $transactionTypeLabel, $transactionStatusID, $transactionStatusLabel, $transactionSourceID, $transactionSourceLabel, $baseCurrencyID, $baseCurrencyName, $quoteSpotPriceCurrencyID, $quoteSpotPriceCurrencyName, $sourceWalletID, $destinationWalletID, $creationDate, $transactionDate, $transactionTimestamp, $transactionID, $vendorTransactionID, $amount, $transactionAmountInUSD, $baseToUSDCurrencySpotPrice, $btcSpotPriceAtTimeOfTransaction, $transactionAmountMinusFeeInUSD, $fee, $feeAmountInUSD, $unspentTransactionTotal, $providerNotes, $isDebit, $sid);
					
						$writeToDatabaseResponse							= $cryptoTransaction -> writeToDatabase($userEncryptionKey, $dbh);
						
						if ($writeToDatabaseResponse['wroteToDatabase'] == true)
						{
							$transactionID									= $cryptoTransaction -> getTransactionID();
							
							errorLog("wrote transaction $transactionID for $accountID, $authorID, $globalTransactionIdentificationRecordID, $transactionTypeID, $transactionTypeLabel, $transactionStatusID, $transactionStatusLabel, $transactionSourceID, $transactionSourceLabel, $baseCurrencyID, $baseCurrencyName, $quoteSpotPriceCurrencyID, $quoteSpotPriceCurrencyName, $sourceWalletID, $destinationWalletID, $creationDate, $transactionDate, $transactionTimestamp, $transactionID, $vendorTransactionID, $amount, $transactionAmountInUSD, $baseToUSDCurrencySpotPrice, $btcSpotPriceAtTimeOfTransaction, $transactionAmountMinusFeeInUSD, $fee, $feeAmountInUSD, $unspentTransactionTotal, $providerNotes, $isDebit, $sid", $GLOBALS['debugCoreFunctionality']);
						}
						else
						{
							errorLog("could not create transaction for $accountID, $authorID, $globalTransactionIdentificationRecordID, $transactionTypeID, $transactionTypeLabel, $transactionStatusID, $transactionStatusLabel, $transactionSourceID, $transactionSourceLabel, $baseCurrencyID, $baseCurrencyName, $quoteSpotPriceCurrencyID, $quoteSpotPriceCurrencyName, $sourceWalletID, $destinationWalletID, $creationDate, $transactionDate, $transactionTimestamp, $transactionID, $vendorTransactionID, $amount, $transactionAmountInUSD, $baseToUSDCurrencySpotPrice, $btcSpotPriceAtTimeOfTransaction, $transactionAmountMinusFeeInUSD, $fee, $feeAmountInUSD, $unspentTransactionTotal, $providerNotes, $isDebit, $sid", $GLOBALS['debugCoreFunctionality']);	
						}
					}
					else
					{
						errorLog("found transaction $commonTransactionID for $accountID, $authorID, $globalTransactionIdentificationRecordID, $transactionTypeID, $transactionTypeLabel, $transactionStatusID, $transactionStatusLabel, $transactionSourceID, $transactionSourceLabel, $baseCurrencyID, $baseCurrencyName, $quoteSpotPriceCurrencyID, $quoteSpotPriceCurrencyName, $sourceWalletID, $destinationWalletID, $creationDate, $transactionDate, $transactionTimestamp, $transactionID, $vendorTransactionID, $amount, $transactionAmountInUSD, $baseToUSDCurrencySpotPrice, $btcSpotPriceAtTimeOfTransaction, $transactionAmountMinusFeeInUSD, $fee, $feeAmountInUSD, $unspentTransactionTotal, $providerNotes, $isDebit, $sid", $GLOBALS['debugCoreFunctionality']);	
					}
				}
			}
			else
			{
				errorLog("SELECT
		CoinbaseTransactions.transactionID,
		CoinbaseTransactions.FK_ExchangeTileID,
		CoinbaseTransactions.FK_GlobalTransactionIdentificationRecordID,
		CoinbaseTransactions.FK_AuthorID AS authorID,
		CoinbaseTransactions.FK_AccountID AS accountID,
		CoinbaseTransactions.FK_TransactionTypeID AS transactionTypeID,
		CoinbaseTransactions.FK_AssetTypeID AS baseCurrencyID,
		AssetTypes.assetTypeLabel AS baseCurrencyName,
		2 AS quoteSpotPriceCurrencyID,
		'USD' AS quoteSpotPriceCurrencyName,
		CoinbaseTransactions.creationDate,
		CoinbaseTransactions.creationDate AS transactionDate,
		CoinbaseTransactions.transactionTimestamp,
		AES_DECRYPT(encryptedCoinbaseTransactionIDValue, UNHEX(SHA2('$userEncryptionKey',512))) AS vendorTransactionID,
		ABS(CoinbaseTransactions.cryptoCurrencyAmount) AS btcQuantityTransacted,
		ABS(CoinbaseTransactions.nativeAmount) AS usdQuantityTransacted,
		CoinbaseTransactions.spotPrice AS spotPriceAtTimeOfTransaction,
		CoinbaseTransactions.spotPrice AS btcPriceAtTimeOfTransaction,
		ABS(CoinbaseTransactions.cryptoCurrencyAmount * CoinbaseTransactions.spotPrice) AS usdTransactionAmountWithFees,
		CoinbaseTransactions.networkTransactionFeeAmount,
		ABS(CoinbaseTransactions.networkTransactionFeeAmount * CoinbaseTransactions.spotPrice) AS usdFeeAmount,
		TRIM(
		CONCAT(
			AES_DECRYPT(encryptedDetailsTitle, UNHEX(SHA2('$userEncryptionKey',512))),
			' ',
			AES_DECRYPT(encryptedDetailsSubTitle, UNHEX(SHA2('$userEncryptionKey',512)))
		)
		) AS  providerNotes,
		CoinbaseTransactions.isDebit,
		CoinbaseTransactions.FK_SourceAddressID,
		CoinbaseTransactions.FK_DestinationAddressID,
		TransactionTypes.displayTransactionTypeLabel,
		TransactionTypes.transactionTypeLabel
	FROM
		CoinbaseTransactions
		INNER JOIN TransactionTypes ON CoinbaseTransactions.FK_TransactionTypeID = TransactionTypes.transactionTypeID AND TransactionTypes.languageCode = 'EN'
		INNER JOIN AssetTypes ON CoinbaseTransactions.FK_AssetTypeID = AssetTypes.assetTypeID AND AssetTypes.languageCode = 'EN'
	WHERE
		CoinbaseTransactions.FK_AccountID = $accountID
	ORDER BY
		CoinbaseTransactions.transactionTimestamp", $GLOBALS['debugCoreFunctionality']);	
			}
			
			$responseObject['importedTransactions']							= true;
		}
		catch (PDOException $e) 
		{
			$cryptoTransaction 												= null;	
			$responseObject['importedTransactions']							= false;
			
			errorLog($e -> getMessage(), $GLOBALS['criticalErrors']);
		
			die();
		}
		
		return $responseObject;
	}
	
	function createCommonTransactionsForCryptoIDTransactions($liuAccountID, $dataImportEventRecordID, $userEncryptionKey, $globalCurrentDate, $sid, $dbh)
	{
		$responseObject														= array();
		$responseObject['createCommonTransactionsForCryptoIDTransactions']	= false;
		
		$transactionSourceID												= 18;
		$transactionSourceLabel												= "CryptoID";
		
		$transactionStatusID												= 1;
		$transactionStatusLabel												= "complete";
		
		try
		{		
			$getCryptoIDTransactionRecords									= $dbh -> prepare("SELECT
		CryptoIDTransactionRecords.cryptoIDTransactionID,
		CryptoIDTransactionRecords.FK_AccountID AS accountID,
		CryptoIDTransactionRecords.FK_AccountID AS authorID,
		CryptoIDTransactionRecords.FK_CryptoIDAddressReportID,
		CryptoIDTransactionRecords.FK_ExchangeTileID,
		CryptoIDTransactionRecords.FK_GlobalTransactionRecordID,
		CryptoIDTransactionRecords.FK_ProviderAccountWalletID,
		CryptoIDTransactionRecords.FK_AssetTypeID AS baseCurrencyID,
		2 AS quoteSpotPriceCurrencyID,
		'USD' AS quoteSpotPriceCurrencyName,
		AES_DECRYPT(CryptoIDTransactionRecords.encryptedHashValue, UNHEX(SHA2(:userEncryptionKey,512))) AS vendorTransactionID,
		CryptoIDTransactionRecords.numConfirmations,
		ABS(CryptoIDTransactionRecords.changeAmount) AS amount,
		CryptoIDTransactionRecords.changeAmount AS transactionAmountInQuoteCurrency,
		
		ABS(CryptoIDTransactionRecords.changeAmount * CryptoIDTransactionRecords.spotPriceExpressedInUSD) AS transactionAmountInUSD,
		ABS(CryptoIDTransactionRecords.changeAmount) AS transactionAmountMinusFeeInUSD,
		CryptoIDTransactionRecords.transactionDate,
		CryptoIDTransactionRecords.transactionTimestamp,
		CryptoIDTransactionRecords.creationDate,
		CryptoIDTransactionRecords.spotPriceExpressedInUSD AS baseToQuoteCurrencySpotPrice,
		CryptoIDTransactionRecords.spotPriceExpressedInUSD AS baseToUSDCurrencySpotPrice,
		CryptoIDTransactionRecords.btcSpotPriceAtTimeOfTransaction,
		CryptoIDTransactionRecords.isDebit,
		CryptoIDTransactionRecords.FK_TransactionTypeID AS transactionTypeID,
		CryptoIDTransactionRecords.FK_TransactionStatusID,
		CryptoIDTransactionRecords.FK_TransactionSourceID,
		CryptoIDTransactionRecords.FK_BaseCurrencyWalletID,
		CryptoIDTransactionRecords.FK_QuoteCurrencyWalletID,
		0 AS fee,
		0 AS feeAmountInUSD,
		'' providerNotes,
		AssetTypes.assetTypeLabel AS baseCurrencyName,
		TransactionTypes.displayTransactionTypeLabel AS transactionTypeLabel,
		TransactionStatus.transactionStatusLabel
	FROM
		CryptoIDTransactionRecords
		JOIN AssetTypes ON CryptoIDTransactionRecords.FK_AssetTypeID = AssetTypes.assetTypeID 
		JOIN TransactionTypes ON CryptoIDTransactionRecords.FK_TransactionTypeID = TransactionTypes.transactionTypeID 
		JOIN TransactionStatus ON CryptoIDTransactionRecords.FK_TransactionStatusID = TransactionStatus.transactionStatusID
	WHERE
		CryptoIDTransactionRecords.FK_AccountID = :accountID AND
		CryptoIDTransactionRecords.FK_DataImportEventID = :FK_DataImportEventID AND
		CryptoIDTransactionRecords.FK_DeletedBy IS NULL
	ORDER BY
		CryptoIDTransactionRecords.transactionTimestamp");		
		
			$getCryptoIDTransactionRecords -> bindValue(':accountID', $liuAccountID);
			$getCryptoIDTransactionRecords -> bindValue(':FK_DataImportEventID', $dataImportEventRecordID);
			$getCryptoIDTransactionRecords -> bindValue(':userEncryptionKey', $userEncryptionKey);
		
			if ($getCryptoIDTransactionRecords -> execute() && $getCryptoIDTransactionRecords -> rowCount() > 0)
			{
				errorLog("began get kraken crypto transaction records ".$getCryptoIDTransactionRecords -> rowCount() > 0);
				
				while ($row = $getCryptoIDTransactionRecords -> fetchObject())
				{
					$ledgerRecordID											= $row -> cryptoIDTransactionID;
					$exchangeTileID											= $row -> FK_ExchangeTileID;
					$globalTransactionIdentificationRecordID				= $row -> FK_GlobalTransactionRecordID;
					$accountID												= $row -> accountID;	
					$authorID												= $row -> authorID;
					$providerAccountWalletID								= $row -> FK_ProviderAccountWalletID; // not needed for now
					$transactionTypeID										= $row -> transactionTypeID;
					$baseCurrencyID											= $row -> baseCurrencyID; // was assetTypeID - done
					$baseCurrencyName										= $row -> baseCurrencyName; // assetTypeName - not needed
					$quoteSpotPriceCurrencyID								= $row -> quoteSpotPriceCurrencyID; // was spotPriceCurrencyTypeID - done, needs verification
					$quoteSpotPriceCurrencyName								= $row -> quoteSpotPriceCurrencyName; // was spotPriceCurrencyType	
						
					$amount													= $row -> amount; // was btcQuantityTransacted - done	
					$fee													= $row -> fee;
					$balance												= $row -> balance;
					$baseToQuoteCurrencySpotPrice							= $row -> baseToQuoteCurrencySpotPrice;
					$baseToUSDCurrencySpotPrice								= $row -> baseToUSDCurrencySpotPrice; // was spotPriceAtTimeOfTransaction - done, needs verification
					$currencyPriceValueSourceID								= $row -> FK_CurrencyPriceValueSourceID;  // not needed
					$btcSpotPriceAtTimeOfTransaction						= $row -> btcSpotPriceAtTimeOfTransaction; // was btcPriceAtTimeOfTransaction - done, needs verification
					$creationDate											= $row -> creationDate;
					$transactionDate										= $row -> transactionDate;
					$transactionTimestamp									= $row -> transactionTimestamp; // not needed
					$vendorTransactionID									= $row -> vendorTransactionID;	
					$transactionAmountInQuoteCurrency						= $row -> transactionAmountInQuoteCurrency; // not needed
					$transactionAmountInUSD									= $row -> transactionAmountInUSD; // was usdQuantityTransacted - done
					$feeAmountInUSD											= $row -> feeAmountInUSD; // was usdFeeAmount - done
					$transactionAmountMinusFeeInUSD							= $row -> transactionAmountMinusFeeInUSD; // was usdTransactionAmountWithFees - this is the amount that changes the balance in their system - I may need to use this rather than the transaction amount in USD to get the right total amount
					$providerNotes											= $row -> providerNotes;
					$transactionTypeLabel									= $row -> transactionTypeLabel; // was displayTransactionTypeLabel - done
					$isDebit												= $row -> isDebit;
					
					$sourceWalletID											= $row -> FK_BaseCurrencyWalletID;
					$destinationWalletID									= $row -> FK_QuoteCurrencyWalletID;
					
					$responseObject['processingTransaction'][]				= $vendorTransactionID;
					
					$getNativeAndCommonTransactionRecordIDsResult			= getNativeAndCommonTransactionRecordIDsForGlobalTransactionIdentificationRecordID($accountID, $baseCurrencyID, $vendorTransactionID, $transactionSourceID, $globalTransactionIdentificationRecordID, $globalCurrentDate, $sid, $dbh);
					
					errorLog("commonTransactionID: ". $getNativeAndCommonTransactionRecordIDsResult['commonTransactionRecordID']);
			
					$commonTransactionID									= $getNativeAndCommonTransactionRecordIDsResult['commonTransactionRecordID'];
			
					if (empty($commonTransactionID))
					{
						$unspentTransactionTotal							= 0;
						$unfundedSpendTotal									= 0;
						
						if ($isDebit == 0)
						{
							$unspentTransactionTotal  						= $amount;  // shouldn't this be the amount minus the fee amount
						}
						else if ($isDebit == 1)
						{
							$unfundedSpendTotal								= $amount; 	// shouldn't this be the amount minus the fee amount
						}	
						
						$sourceWallet										= new CompleteCryptoWallet();
						$destinationWallet									= new CompleteCryptoWallet();
				
						$sourceWalletResponseObject							= $sourceWallet -> instantiateWalletUsingCryptoWalletRecordID($liuAccountID, $sourceWalletID, $userEncryptionKey, $dbh);
				
						if ($sourceWalletResponseObject['instantiatedRecord'] == false)
						{
							errorLog("Could not instantiate source Complete Crypto Wallet record $liuAccountID");
						}
						
						$destinationWalletResponseObject					= $destinationWallet -> instantiateWalletUsingCryptoWalletRecordID($liuAccountID, $destinationWalletID, $userEncryptionKey, $dbh);
				
						if ($destinationWalletResponseObject['instantiatedRecord'] == false)
						{
							errorLog("Could not instantiate destination Complete Crypto Wallet record $liuAccountID, $destinationWalletID");
						}
						
						errorLog($vendorTransactionID."<BR>");
					
						$cryptoTransaction									= new CryptoTransaction();
					
						$cryptoTransaction -> setData(0, $accountID, $authorID, $exchangeTileID, $globalTransactionIdentificationRecordID, $transactionTypeID, $transactionTypeLabel, $transactionStatusID, $transactionStatusLabel, $transactionSourceID, $transactionSourceLabel, $baseCurrencyID, $baseCurrencyName, $quoteSpotPriceCurrencyID, $quoteSpotPriceCurrencyName, $sourceWalletID, $destinationWalletID, $creationDate, $transactionDate, $transactionTimestamp, $ledgerRecordID, $vendorTransactionID, $amount, $transactionAmountInUSD, $baseToUSDCurrencySpotPrice, $btcSpotPriceAtTimeOfTransaction, $transactionAmountMinusFeeInUSD, $fee, $feeAmountInUSD, $unspentTransactionTotal, $providerNotes, $isDebit, $sid);
					
						$writeToDatabaseResponse							= $cryptoTransaction -> writeToDatabase($userEncryptionKey, $dbh);
						
						if ($writeToDatabaseResponse['wroteToDatabase'] == true)
						{
							$transactionID									= $cryptoTransaction -> getTransactionID();
							
							errorLog("wrote transaction $transactionID for $accountID, $authorID, $globalTransactionIdentificationRecordID, $transactionTypeID, $transactionTypeLabel, $transactionStatusID, $transactionStatusLabel, $transactionSourceID, $transactionSourceLabel, $baseCurrencyID, $baseCurrencyName, $quoteSpotPriceCurrencyID, $quoteSpotPriceCurrencyName, $sourceWalletID, $destinationWalletID, $creationDate, $transactionDate, $transactionTimestamp, $ledgerRecordID, $vendorTransactionID, $amount, $transactionAmountInUSD, $baseToUSDCurrencySpotPrice, $btcSpotPriceAtTimeOfTransaction, $transactionAmountMinusFeeInUSD, $fee, $feeAmountInUSD, $unspentTransactionTotal, $providerNotes, $isDebit, $sid", $GLOBALS['debugCoreFunctionality']);
						}
						else
						{
							errorLog("could not create transaction for $accountID, $authorID, $globalTransactionIdentificationRecordID, $transactionTypeID, $transactionTypeLabel, $transactionStatusID, $transactionStatusLabel, $transactionSourceID, $transactionSourceLabel, $baseCurrencyID, $baseCurrencyName, $quoteSpotPriceCurrencyID, $quoteSpotPriceCurrencyName, $sourceWalletID, $destinationWalletID, $creationDate, $transactionDate, $transactionTimestamp, $ledgerRecordID, $vendorTransactionID, $amount, $transactionAmountInUSD, $baseToUSDCurrencySpotPrice, $btcSpotPriceAtTimeOfTransaction, $transactionAmountMinusFeeInUSD, $fee, $feeAmountInUSD, $unspentTransactionTotal, $providerNotes, $isDebit, $sid", $GLOBALS['debugCoreFunctionality']);	
						}
					}
					else
					{
						errorLog("found transaction $commonTransactionID for $accountID, $authorID, $globalTransactionIdentificationRecordID, $transactionTypeID, $transactionTypeLabel, $transactionStatusID, $transactionStatusLabel, $transactionSourceID, $transactionSourceLabel, $baseCurrencyID, $baseCurrencyName, $quoteSpotPriceCurrencyID, $quoteSpotPriceCurrencyName, $sourceWalletID, $destinationWalletID, $creationDate, $transactionDate, $transactionTimestamp, $ledgerRecordID, $vendorTransactionID, $amount, $transactionAmountInUSD, $baseToUSDCurrencySpotPrice, $btcSpotPriceAtTimeOfTransaction, $transactionAmountMinusFeeInUSD, $fee, $feeAmountInUSD, $unspentTransactionTotal, $providerNotes, $isDebit, $sid", $GLOBALS['debugCoreFunctionality']);	
					}
				}
			}
			else
			{
				errorLog("SELECT
		CryptoIDTransactionRecords.cryptoIDTransactionID,
		CryptoIDTransactionRecords.FK_AccountID AS accountID,
		CryptoIDTransactionRecords.FK_AccountID AS authorID,
		CryptoIDTransactionRecords.FK_CryptoIDAddressReportID,
		CryptoIDTransactionRecords.FK_GlobalTransactionRecordID,
		CryptoIDTransactionRecords.FK_ProviderAccountWalletID,
		CryptoIDTransactionRecords.FK_AssetTypeID AS baseCurrencyID,
		2 AS quoteSpotPriceCurrencyID,
		'USD' AS quoteSpotPriceCurrencyName,
		AES_DECRYPT(CryptoIDTransactionRecords.encryptedHashValue, UNHEX(SHA2('$userEncryptionKey',512))) AS vendorTransactionID,
		CryptoIDTransactionRecords.numConfirmations,
		ABS(CryptoIDTransactionRecords.changeAmount) AS amount,
		CryptoIDTransactionRecords.changeAmount AS transactionAmountInQuoteCurrency,
		
		ABS(CryptoIDTransactionRecords.changeAmount * CryptoIDTransactionRecords.spotPriceExpressedInUSD) AS transactionAmountInUSD,
		ABS(CryptoIDTransactionRecords.changeAmount) AS transactionAmountMinusFeeInUSD,
		CryptoIDTransactionRecords.transactionDate,
		CryptoIDTransactionRecords.transactionTimestamp,
		CryptoIDTransactionRecords.creationDate,
		CryptoIDTransactionRecords.spotPriceExpressedInUSD AS baseToQuoteCurrencySpotPrice,
		CryptoIDTransactionRecords.spotPriceExpressedInUSD AS baseToUSDCurrencySpotPrice,
		CryptoIDTransactionRecords.btcSpotPriceAtTimeOfTransaction,
		CryptoIDTransactionRecords.isDebit,
		CryptoIDTransactionRecords.FK_TransactionTypeID AS transactionTypeID,
		CryptoIDTransactionRecords.FK_TransactionStatusID,
		CryptoIDTransactionRecords.FK_TransactionSourceID,
		CryptoIDTransactionRecords.FK_BaseCurrencyWalletID AS FK_SourceAddressID,
		CryptoIDTransactionRecords.FK_QuoteCurrencyWalletID AS FK_DestinationAddressID,
		0 AS fee,
		0 AS feeAmountInUSD,
		'' providerNotes,
		AssetTypes.assetTypeLabel AS baseCurrencyName,
		TransactionTypes.displayTransactionTypeLabel AS transactionTypeLabel,
		TransactionStatus.transactionStatusLabel
	FROM
		CryptoIDTransactionRecords
		JOIN AssetTypes ON CryptoIDTransactionRecords.FK_AssetTypeID = AssetTypes.assetTypeID 
		JOIN TransactionTypes ON CryptoIDTransactionRecords.FK_TransactionTypeID = TransactionTypes.transactionTypeID 
		JOIN TransactionStatus ON CryptoIDTransactionRecords.FK_TransactionStatusID = TransactionStatus.transactionStatusID
	WHERE
		CryptoIDTransactionRecords.FK_AccountID = $liuAccountID AND
		CryptoIDTransactionRecords.FK_DeletedBy IS NULL
	ORDER BY
		CryptoIDTransactionRecords.transactionTimestamp", $GLOBALS['debugCoreFunctionality']);	
			}
			
			$responseObject['importedTransactions']						= true;
		}
		catch (PDOException $e) 
		{
			$cryptoTransaction 											= null;	
			$responseObject['importedTransactions']						= false;
			
			errorLog($e -> getMessage(), $GLOBALS['criticalErrors']);
		
			die();
		}
		
		return $responseObject;
	}
	
	
	
	function createCommonTransactionsForKrakenTradeTransactions($liuAccountID, $userEncryptionKey, $globalCurrentDate, $sid, $dbh)
	{
		$responseObject														= array();
		$responseObject['createCommonTransactionsForKrakenTrades']			= false;
		
		$transactionSourceID												= 6;
		$transactionStatusID												= 1;
		$transactionStatusLabel												= "complete";
		$transactionSourceLabel												= "Kraken";
		
		try
		{		
			$getKrackenLedgerAndTradeRecords								= $dbh -> prepare("SELECT
	KrakenLedgerTransactions.ledgerRecordID,
	KrakenLedgerTransactions.FK_GlobalTransactionRecordID,
	KrakenLedgerTransactions.FK_AccountID AS authorID,
	KrakenLedgerTransactions.FK_AccountID AS accountID,
	KrakenLedgerTransactions.FK_ProviderAccountWalletID,
	CASE
		WHEN KrakenLedgerTransactions.FK_LedgerEntryTypeID = 1 THEN 7
		WHEN KrakenLedgerTransactions.FK_LedgerEntryTypeID = 2 THEN 8
		WHEN 
			KrakenLedgerTransactions.FK_LedgerEntryTypeID > 2 AND
			KrakenLedgerTransactions.isDebit = 0 THEN 1
		WHEN 
			KrakenLedgerTransactions.FK_LedgerEntryTypeID > 2 AND
			KrakenLedgerTransactions.isDebit = 1 THEN 4
		ELSE 14
	END AS transactionTypeID,
	KrakenLedgerTransactions.FK_AssetClassID,
	AES_DECRYPT(KrakenLedgerTransactions.encryptedPairName, UNHEX(SHA2(:userEncryptionKey,512))) AS pairName,
	KrakenLedgerTransactions.FK_KrakenCurrencyPairID,
	KrakenLedgerTransactions.FK_AssetTypeID AS baseCurrencyID,
	baseCurrency.assetTypeLabel AS baseCurrencyName,
	2 AS quoteSpotPriceCurrencyID,
	'USD' AS quoteSpotPriceCurrencyName,
	ABS(KrakenLedgerTransactions.amount) AS amount,
	KrakenLedgerTransactions.fee,
	KrakenLedgerTransactions.balance,
	KrakenLedgerTransactions.isDebit,
	KrakenLedgerTransactions.baseToQuoteCurrencySpotPrice,
	KrakenLedgerTransactions.baseToUSDCurrencySpotPrice,
	KrakenLedgerTransactions.FK_CurrencyPriceValueSourceID,
	KrakenLedgerTransactions.btcSpotPriceAtTimeOfTransaction,
	KrakenLedgerTransactions.FK_TransactionRecordID,
	KrakenLedgerTransactions.FK_BaseCurrencyWalletID,
	KrakenLedgerTransactions.FK_QuoteCurrencyWalletID,
	KrakenLedgerTransactions.transactionTime AS creationDate,
	KrakenLedgerTransactions.transactionTime AS transactionDate,
	KrakenLedgerTransactions.transactionTimestamp,
	AES_DECRYPT(KrakenLedgerTransactions.encryptedLedgerIDValue, UNHEX(SHA2(:userEncryptionKey,512))) AS vendorTransactionID,
	AES_DECRYPT(KrakenLedgerTransactions.encryptedRefIDValue, UNHEX(SHA2(:userEncryptionKey,512))) AS vendorRefID,
	KrakenLedgerTransactions.amount AS transactionAmountInQuoteCurrency,
	CASE
		WHEN KrakenLedgerTransactions.FK_AssetTypeID = 2 THEN ABS(KrakenLedgerTransactions.amount)
		ELSE ABS(KrakenLedgerTransactions.amount * KrakenLedgerTransactions.baseToUSDCurrencySpotPrice)
	END AS transactionAmountInUSD,
	ABS(KrakenLedgerTransactions.fee * KrakenLedgerTransactions.baseToUSDCurrencySpotPrice) AS feeAmountInUSD,
	ABS((KrakenLedgerTransactions.amount * KrakenLedgerTransactions.baseToUSDCurrencySpotPrice) + (KrakenLedgerTransactions.fee * KrakenLedgerTransactions.baseToUSDCurrencySpotPrice)) AS transactionAmountPlusFeeInUSD,
	ABS((KrakenLedgerTransactions.amount * KrakenLedgerTransactions.baseToUSDCurrencySpotPrice) - (KrakenLedgerTransactions.fee * KrakenLedgerTransactions.baseToUSDCurrencySpotPrice)) AS transactionAmountMinusFeeInUSD,
	'' AS  providerNotes,
	KrakenLedgerTransactions.FK_BaseCurrencyWalletID AS FK_SourceAddressID,
	KrakenLedgerTransactions.FK_QuoteCurrencyWalletID AS FK_DestinationAddressID,
	CASE
		WHEN KrakenLedgerTransactions.FK_LedgerEntryTypeID = 1 THEN 'Deposit'
		WHEN KrakenLedgerTransactions.FK_LedgerEntryTypeID = 2 THEN 'Withdrawal'
		WHEN 
			KrakenLedgerTransactions.FK_LedgerEntryTypeID > 2 AND
			KrakenLedgerTransactions.isDebit = 1 THEN 'Sell'
		WHEN 
			KrakenLedgerTransactions.FK_LedgerEntryTypeID > 2 AND
			KrakenLedgerTransactions.isDebit = 0 THEN 'Buy'
		ELSE 'Adjustment'
	END AS transactionTypeLabel
FROM
	KrakenLedgerTransactions
	INNER JOIN AssetTypes baseCurrency ON KrakenLedgerTransactions.FK_AssetTypeID = baseCurrency.assetTypeID AND baseCurrency.languageCode = 'EN'
WHERE
	KrakenLedgerTransactions.FK_AccountID = :accountID
ORDER BY
	KrakenLedgerTransactions.transactionTimestamp");
	
	
		
			$getKrackenLedgerAndTradeRecords -> bindValue(':accountID', $liuAccountID);
			$getKrackenLedgerAndTradeRecords -> bindValue(':userEncryptionKey', $userEncryptionKey);
		
			if ($getKrackenLedgerAndTradeRecords -> execute() && $getKrackenLedgerAndTradeRecords -> rowCount() > 0)
			{
				errorLog("began get kraken crypto transaction records ".$getKrackenLedgerAndTradeRecords -> rowCount() > 0);
				
				while ($row = $getKrackenLedgerAndTradeRecords -> fetchObject())
				{
					$ledgerRecordID											= $row -> ledgerRecordID;
					$globalTransactionIdentificationRecordID				= $row -> FK_GlobalTransactionRecordID;
					$accountID												= $row -> accountID;	
					$authorID												= $row -> authorID;
					$providerAccountWalletID								= $row -> FK_ProviderAccountWalletID; // not needed for now
					$transactionTypeID										= $row -> transactionTypeID;
					$assetClassID											= $row -> FK_AssetClassID;  // not needed
					$pairName												= $row -> pairName; // not needed
					$krakenCurrencyPairID									= $row -> FK_KrakenCurrencyPairID; // not needed
					$baseCurrencyID											= $row -> baseCurrencyID; // was assetTypeID - done
					$baseCurrencyName										= $row -> baseCurrencyName; // assetTypeName - not needed
						// $quoteSpotPriceCurrencyID						= $row -> quoteSpotPriceCurrencyID; // was spotPriceCurrencyTypeID
						// $quoteSpotPriceCurrencyName						= $row -> quoteSpotPriceCurrencyName; // was spotPriceCurrencyType
						
					$quoteSpotPriceCurrencyID								= $row -> quoteSpotPriceCurrencyID; // was spotPriceCurrencyTypeID - done, needs verification
					$quoteSpotPriceCurrencyName								= $row -> quoteSpotPriceCurrencyName; // was spotPriceCurrencyType	
						
					$amount													= $row -> amount; // was btcQuantityTransacted - done	
					$fee													= $row -> fee;
					$balance												= $row -> balance;
					$baseToQuoteCurrencySpotPrice							= $row -> baseToQuoteCurrencySpotPrice;
					$baseToUSDCurrencySpotPrice								= $row -> baseToUSDCurrencySpotPrice; // was spotPriceAtTimeOfTransaction - done, needs verification
					$currencyPriceValueSourceID								= $row -> FK_CurrencyPriceValueSourceID;  // not needed
					$btcSpotPriceAtTimeOfTransaction						= $row -> btcSpotPriceAtTimeOfTransaction; // was btcPriceAtTimeOfTransaction - done, needs verification
					$transactionRecordID									= $row -> FK_TransactionRecordID; // not needed
					$creationDate											= $row -> creationDate;
					$transactionDate										= $row -> transactionDate;
					$transactionTimestamp									= $row -> transactionTimestamp; // not needed
					$vendorTransactionID									= $row -> vendorTransactionID;	
					$vendorRefID											= $row -> vendorRefID; // not needed
					$transactionAmountInQuoteCurrency						= $row -> transactionAmountInQuoteCurrency; // not needed
					$transactionAmountInUSD									= $row -> transactionAmountInUSD; // was usdQuantityTransacted - done
					$feeAmountInUSD											= $row -> feeAmountInUSD; // was usdFeeAmount - done
					$transactionAmountPlusFeeInUSD							= $row -> transactionAmountPlusFeeInUSD; // not needed
					$transactionAmountMinusFeeInUSD							= $row -> transactionAmountMinusFeeInUSD; // was usdTransactionAmountWithFees - this is the amount that changes the balance in their system - I may need to use this rather than the transaction amount in USD to get the right total amount
					$providerNotes											= $row -> providerNotes;
					$transactionTypeLabel									= $row -> transactionTypeLabel; // was displayTransactionTypeLabel - done
					$ledgerRecordID											= $row -> ledgerRecordID; //
					$isDebit												= $row -> isDebit;
					
					$sourceWalletID											= $row -> FK_BaseCurrencyWalletID;
					$destinationWalletID									= $row -> FK_QuoteCurrencyWalletID;
					
					$responseObject['processingTransaction'][]				= $vendorTransactionID;
					
					$getNativeAndCommonTransactionRecordIDsResult			= getNativeAndCommonTransactionRecordIDsForGlobalTransactionIdentificationRecordID($accountID, $baseCurrencyID, $vendorTransactionID, $transactionSourceID, $globalTransactionIdentificationRecordID, $globalCurrentDate, $sid, $dbh);
					
					errorLog("commonTransactionID: ". $getNativeAndCommonTransactionRecordIDsResult['commonTransactionRecordID']);
			
					$commonTransactionID									= $getNativeAndCommonTransactionRecordIDsResult['commonTransactionRecordID'];
			
					if (empty($commonTransactionID))
					{
					
						$unspentTransactionTotal							= 0;
						$unfundedSpendTotal									= 0;
						
						if ($isDebit == 0)
						{
							$unspentTransactionTotal  						= $amount;  // shouldn't this be the amount minus the fee amount
						}
						else if ($isDebit == 1)
						{
							$unfundedSpendTotal								= $amount; 	// shouldn't this be the amount minus the fee amount
						}	
						
						$sourceWallet										= new CompleteCryptoWallet();
						$destinationWallet									= new CompleteCryptoWallet();
				
						$sourceWalletResponseObject							= $sourceWallet -> instantiateWalletUsingCryptoWalletRecordID($liuAccountID, $sourceWalletID, $userEncryptionKey, $dbh);
				
						if ($sourceWalletResponseObject['instantiatedRecord'] == false)
						{
							errorLog("Could not instantiate source Complete Crypto Wallet record $liuAccountID");
						}
						
						$destinationWalletResponseObject					= $destinationWallet -> instantiateWalletUsingCryptoWalletRecordID($liuAccountID, $destinationWalletID, $userEncryptionKey, $dbh);
				
						if ($destinationWalletResponseObject['instantiatedRecord'] == false)
						{
							errorLog("Could not instantiate destination Complete Crypto Wallet record $liuAccountID, $destinationWalletID");
						}
						
						errorLog($vendorTransactionID."<BR>");
					
						$cryptoTransaction									= new CryptoTransaction();
					
						$cryptoTransaction -> setData(0, $accountID, $authorID, $globalTransactionIdentificationRecordID, $transactionTypeID, $transactionTypeLabel, $transactionStatusID, $transactionStatusLabel, $transactionSourceID, $transactionSourceLabel, $baseCurrencyID, $baseCurrencyName, $quoteSpotPriceCurrencyID, $quoteSpotPriceCurrencyName, $sourceWalletID, $destinationWalletID, $creationDate, $transactionDate, $transactionTimestamp, $ledgerRecordID, $vendorTransactionID, $amount, $transactionAmountInUSD, $baseToUSDCurrencySpotPrice, $btcSpotPriceAtTimeOfTransaction, $transactionAmountMinusFeeInUSD, $fee, $feeAmountInUSD, $unspentTransactionTotal, $providerNotes, $isDebit, $sid);
					
						$writeToDatabaseResponse							= $cryptoTransaction -> writeToDatabase($userEncryptionKey, $dbh);
						
						if ($writeToDatabaseResponse['wroteToDatabase'] == true)
						{
							$transactionID									= $cryptoTransaction -> getTransactionID();
							
							errorLog("wrote transaction $transactionID for $accountID, $authorID, $globalTransactionIdentificationRecordID, $transactionTypeID, $transactionTypeLabel, $transactionStatusID, $transactionStatusLabel, $transactionSourceID, $transactionSourceLabel, $baseCurrencyID, $baseCurrencyName, $quoteSpotPriceCurrencyID, $quoteSpotPriceCurrencyName, $sourceWalletID, $destinationWalletID, $creationDate, $transactionDate, $transactionTimestamp, $ledgerRecordID, $vendorTransactionID, $amount, $transactionAmountInUSD, $baseToUSDCurrencySpotPrice, $btcSpotPriceAtTimeOfTransaction, $transactionAmountMinusFeeInUSD, $fee, $feeAmountInUSD, $unspentTransactionTotal, $providerNotes, $isDebit, $sid", $GLOBALS['debugCoreFunctionality']);
						}
						else
						{
							errorLog("could not create transaction for $accountID, $authorID, $globalTransactionIdentificationRecordID, $transactionTypeID, $transactionTypeLabel, $transactionStatusID, $transactionStatusLabel, $transactionSourceID, $transactionSourceLabel, $baseCurrencyID, $baseCurrencyName, $quoteSpotPriceCurrencyID, $quoteSpotPriceCurrencyName, $sourceWalletID, $destinationWalletID, $creationDate, $transactionDate, $transactionTimestamp, $ledgerRecordID, $vendorTransactionID, $amount, $transactionAmountInUSD, $baseToUSDCurrencySpotPrice, $btcSpotPriceAtTimeOfTransaction, $transactionAmountMinusFeeInUSD, $fee, $feeAmountInUSD, $unspentTransactionTotal, $providerNotes, $isDebit, $sid", $GLOBALS['debugCoreFunctionality']);	
						}
					}
					else
					{
						errorLog("found transaction $commonTransactionID for $accountID, $authorID, $globalTransactionIdentificationRecordID, $transactionTypeID, $transactionTypeLabel, $transactionStatusID, $transactionStatusLabel, $transactionSourceID, $transactionSourceLabel, $baseCurrencyID, $baseCurrencyName, $quoteSpotPriceCurrencyID, $quoteSpotPriceCurrencyName, $sourceWalletID, $destinationWalletID, $creationDate, $transactionDate, $transactionTimestamp, $ledgerRecordID, $vendorTransactionID, $amount, $transactionAmountInUSD, $baseToUSDCurrencySpotPrice, $btcSpotPriceAtTimeOfTransaction, $transactionAmountMinusFeeInUSD, $fee, $feeAmountInUSD, $unspentTransactionTotal, $providerNotes, $isDebit, $sid", $GLOBALS['debugCoreFunctionality']);	
					}
				}
			}
			else
			{
				errorLog("SELECT
	KrakenLedgerTransactions.ledgerRecordID,
	KrakenLedgerTransactions.FK_GlobalTransactionRecordID,
	KrakenLedgerTransactions.FK_AccountID AS authorID,
	KrakenLedgerTransactions.FK_AccountID AS accountID,
	KrakenLedgerTransactions.FK_ProviderAccountWalletID,
	CASE
		WHEN KrakenLedgerTransactions.FK_LedgerEntryTypeID = 1 THEN 7
		WHEN KrakenLedgerTransactions.FK_LedgerEntryTypeID = 2 THEN 8
		WHEN 
			KrakenLedgerTransactions.FK_LedgerEntryTypeID > 2 AND
			KrakenLedgerTransactions.isDebit = 0 THEN 1
		WHEN 
			KrakenLedgerTransactions.FK_LedgerEntryTypeID > 2 AND
			KrakenLedgerTransactions.isDebit = 1 THEN 4
		ELSE 14
	END AS transactionTypeID,
	KrakenLedgerTransactions.FK_AssetClassID,
	AES_DECRYPT(KrakenLedgerTransactions.encryptedPairName, UNHEX(SHA2('$userEncryptionKey',512))) AS pairName,
	KrakenLedgerTransactions.FK_KrakenCurrencyPairID,
	baseCurrency.assetTypeID AS baseCurrencyID,
	baseCurrency.assetTypeLabel AS baseCurrencyName,
	quoteCurrency.assetTypeID AS quoteSpotPriceCurrencyID,
	quoteCurrency.assetTypeLabel AS quoteSpotPriceCurrencyName,
	KrakenLedgerTransactions.amount,
	KrakenLedgerTransactions.fee,
	KrakenLedgerTransactions.balance,
	KrakenLedgerTransactions.isDebit,
	KrakenLedgerTransactions.baseToQuoteCurrencySpotPrice,
	KrakenLedgerTransactions.baseToUSDCurrencySpotPrice,
	KrakenLedgerTransactions.FK_CurrencyPriceValueSourceID,
	KrakenLedgerTransactions.FK_TransactionRecordID,
	KrakenLedgerTransactions.FK_BaseCurrencyWalletID,
	KrakenLedgerTransactions.FK_QuoteCurrencyWalletID,
	KrakenLedgerTransactions.transactionTime AS creationDate,
	KrakenLedgerTransactions.transactionTime AS transactionDate,
	KrakenLedgerTransactions.transactionTimestamp,
	AES_DECRYPT(KrakenLedgerTransactions.encryptedLedgerIDValue, UNHEX(SHA2('$userEncryptionKey',512))) AS vendorTransactionID,
	AES_DECRYPT(KrakenLedgerTransactions.encryptedRefIDValue, UNHEX(SHA2('$userEncryptionKey',512))) AS vendorRefID,
	KrakenLedgerTransactions.amount * KrakenLedgerTransactions.baseToQuoteCurrencySpotPrice AS transactionAmountInQuoteCurrency,
	KrakenLedgerTransactions.amount * KrakenLedgerTransactions.baseToUSDCurrencySpotPrice AS transactionAmountInUSD,
	KrakenLedgerTransactions.fee * KrakenLedgerTransactions.baseToUSDCurrencySpotPrice AS feeAmountInUSD,
	(KrakenLedgerTransactions.amount * KrakenLedgerTransactions.baseToUSDCurrencySpotPrice) + (KrakenLedgerTransactions.fee * KrakenLedgerTransactions.baseToUSDCurrencySpotPrice) AS transactionAmountPlusFeeInUSD,
	(KrakenLedgerTransactions.amount * KrakenLedgerTransactions.baseToUSDCurrencySpotPrice) - (KrakenLedgerTransactions.fee * KrakenLedgerTransactions.baseToUSDCurrencySpotPrice) AS transactionAmountMinusFeeInUSD,
	'' AS  providerNotes,
	KrakenLedgerTransactions.FK_BaseCurrencyWalletID AS FK_SourceAddressID,
	KrakenLedgerTransactions.FK_QuoteCurrencyWalletID AS FK_DestinationAddressID,
	CASE
		WHEN KrakenLedgerTransactions.FK_LedgerEntryTypeID = 1 THEN 'Deposit'
		WHEN KrakenLedgerTransactions.FK_LedgerEntryTypeID = 2 THEN 'Withdrawal'
		WHEN 
			KrakenLedgerTransactions.FK_LedgerEntryTypeID > 2 AND
			KrakenLedgerTransactions.isDebit = 1 THEN 'Sell'
		WHEN 
			KrakenLedgerTransactions.FK_LedgerEntryTypeID > 2 AND
			KrakenLedgerTransactions.isDebit = 0 THEN 'Buy'
		ELSE 'Adjustement'
	END AS transactionTypeLabel
FROM
	KrakenLedgerTransactions
	INNER JOIN CommonCurrencyPairs ON KrakenLedgerTransactions.FK_KrakenCurrencyPairID = CommonCurrencyPairs.pairID
	INNER JOIN AssetTypes baseCurrency ON CommonCurrencyPairs.FK_BaseCurrencyID = baseCurrency.assetTypeID AND baseCurrency.languageCode = 'EN'
	INNER JOIN AssetTypes quoteCurrency ON CommonCurrencyPairs.FK_QuoteCurrencyID = quoteCurrency.assetTypeID AND quoteCurrency.languageCode = 'EN'
WHERE
	KrakenLedgerTransactions.FK_AccountID = $liuAccountID AND
	baseCurrency.assetTypeID = $baseCurrencyTypeID
ORDER BY
	KrakenLedgerTransactions.transactionTimestamp", $GLOBALS['debugCoreFunctionality']);	
			}
			
			$responseObject['importedTransactions']							= true;
		}
		catch (PDOException $e) 
		{
			$cryptoTransaction 												= null;	
			$responseObject['importedTransactions']							= false;
			
			errorLog($e -> getMessage(), $GLOBALS['criticalErrors']);
		
			die();
		}
		
		return $responseObject;
	}
	
	function createCommonTransactionsForKrakenLedgerTransactions($liuAccountID, $userEncryptionKey, $globalCurrentDate, $sid, $dbh)
	{
		$responseObject														= array();
		$responseObject['createCommonTransactionsForKrakenLedger']			= false;
		
		$transactionSourceID												= 6;
		$transactionStatusID												= 1;
		$transactionStatusLabel												= "complete";
		$transactionSourceLabel												= "Kraken";
		
		try
		{		
			$getKrackenLedgerAndTradeRecords								= $dbh -> prepare("SELECT
	KrakenLedgerTransactions.ledgerRecordID,
	KrakenLedgerTransactions.FK_ExchangeTileID,
	KrakenLedgerTransactions.FK_GlobalTransactionRecordID,
	KrakenLedgerTransactions.FK_AccountID AS authorID,
	KrakenLedgerTransactions.FK_AccountID AS accountID,
	KrakenLedgerTransactions.FK_ProviderAccountWalletID,
	KrakenLedgerTransactions.FK_TransactionTypeID AS transactionTypeID,
	KrakenLedgerTransactions.FK_NativeTransactionTypeID AS nativeTransactionTypeID,
	KrakenLedgerTransactions.FK_AssetClassID,
	AES_DECRYPT(KrakenLedgerTransactions.encryptedPairName, UNHEX(SHA2(:userEncryptionKey,512))) AS pairName,
	KrakenLedgerTransactions.FK_KrakenCurrencyPairID,
	KrakenLedgerTransactions.FK_AssetTypeID AS baseCurrencyID,
	baseCurrency.assetTypeLabel AS baseCurrencyName,
	2 AS quoteSpotPriceCurrencyID,
	'USD' AS quoteSpotPriceCurrencyName,
	ABS(KrakenLedgerTransactions.amount) AS amount,
	KrakenLedgerTransactions.fee,
	KrakenLedgerTransactions.balance,
	KrakenLedgerTransactions.isDebit,
	KrakenLedgerTransactions.baseToQuoteCurrencySpotPrice,
	KrakenLedgerTransactions.baseToUSDCurrencySpotPrice,
	KrakenLedgerTransactions.FK_CurrencyPriceValueSourceID,
	KrakenLedgerTransactions.btcSpotPriceAtTimeOfTransaction,
	KrakenLedgerTransactions.FK_TransactionRecordID,
	KrakenLedgerTransactions.FK_BaseCurrencyWalletID,
	KrakenLedgerTransactions.FK_QuoteCurrencyWalletID,
	KrakenLedgerTransactions.transactionTime AS creationDate,
	KrakenLedgerTransactions.transactionTime AS transactionDate,
	KrakenLedgerTransactions.transactionTimestamp,
	AES_DECRYPT(KrakenLedgerTransactions.encryptedLedgerIDValue, UNHEX(SHA2(:userEncryptionKey,512))) AS vendorTransactionID,
	AES_DECRYPT(KrakenLedgerTransactions.encryptedRefIDValue, UNHEX(SHA2(:userEncryptionKey,512))) AS vendorRefID,
	KrakenLedgerTransactions.amount AS transactionAmountInQuoteCurrency,
	CASE
		WHEN KrakenLedgerTransactions.FK_AssetTypeID = 2 THEN ABS(KrakenLedgerTransactions.amount)
		ELSE ABS(KrakenLedgerTransactions.amount * KrakenLedgerTransactions.baseToUSDCurrencySpotPrice)
	END AS transactionAmountInUSD,
	ABS(KrakenLedgerTransactions.fee * KrakenLedgerTransactions.baseToUSDCurrencySpotPrice) AS feeAmountInUSD,
	ABS((KrakenLedgerTransactions.amount * KrakenLedgerTransactions.baseToUSDCurrencySpotPrice) + (KrakenLedgerTransactions.fee * KrakenLedgerTransactions.baseToUSDCurrencySpotPrice)) AS transactionAmountPlusFeeInUSD,
	ABS((KrakenLedgerTransactions.amount * KrakenLedgerTransactions.baseToUSDCurrencySpotPrice) - (KrakenLedgerTransactions.fee * KrakenLedgerTransactions.baseToUSDCurrencySpotPrice)) AS transactionAmountMinusFeeInUSD,
	'' AS  providerNotes,
	KrakenLedgerTransactions.FK_BaseCurrencyWalletID AS FK_SourceAddressID,
	KrakenLedgerTransactions.FK_QuoteCurrencyWalletID AS FK_DestinationAddressID,
	TransactionTypes.displayTransactionTypeLabel AS transactionTypeLabel,
	NativeTransactionTypes.nativeTransactionTypeLabel
FROM
	KrakenLedgerTransactions
	INNER JOIN AssetTypes baseCurrency ON KrakenLedgerTransactions.FK_AssetTypeID = baseCurrency.assetTypeID AND baseCurrency.languageCode = 'EN'
	INNER JOIN TransactionTypes ON KrakenLedgerTransactions.FK_TransactionTypeID = TransactionTypes.transactionTypeID AND TransactionTypes.languageCode = 'EN'
	LEFT JOIN NativeTransactionTypes ON FK_NativeTransactionTypeID = NativeTransactionTypes.nativeTransactionTypeID
WHERE
	KrakenLedgerTransactions.FK_AccountID = :accountID AND
	(
		KrakenLedgerTransactions.FK_TransactionTypeID = 7 OR
		KrakenLedgerTransactions.FK_TransactionTypeID = 8 OR
		(
			KrakenLedgerTransactions.FK_TransactionTypeID = 1 AND
			KrakenLedgerTransactions.amount > 0
		) OR
		(
			KrakenLedgerTransactions.FK_TransactionTypeID = 4 AND
			KrakenLedgerTransactions.amount < 0
		)
	)
ORDER BY
	KrakenLedgerTransactions.transactionTimestamp");
	
	
		
			$getKrackenLedgerAndTradeRecords -> bindValue(':accountID', $liuAccountID);
			$getKrackenLedgerAndTradeRecords -> bindValue(':userEncryptionKey', $userEncryptionKey);
		
			if ($getKrackenLedgerAndTradeRecords -> execute() && $getKrackenLedgerAndTradeRecords -> rowCount() > 0)
			{
				errorLog("began get kraken crypto transaction records ".$getKrackenLedgerAndTradeRecords -> rowCount() > 0);
				
				while ($row = $getKrackenLedgerAndTradeRecords -> fetchObject())
				{
					$ledgerRecordID											= $row -> ledgerRecordID;
					$exchangeTileID											= $row -> FK_ExchangeTileID;
					$globalTransactionIdentificationRecordID				= $row -> FK_GlobalTransactionRecordID;
					$accountID												= $row -> accountID;	
					$authorID												= $row -> authorID;
					$providerAccountWalletID								= $row -> FK_ProviderAccountWalletID; // not needed for now
					$transactionTypeID										= $row -> transactionTypeID;
					$nativeTransactionTypeID								= $row -> nativeTransactionTypeID;
					$assetClassID											= $row -> FK_AssetClassID;  // not needed
					$pairName												= $row -> pairName; // not needed
					$krakenCurrencyPairID									= $row -> FK_KrakenCurrencyPairID; // not needed
					$baseCurrencyID											= $row -> baseCurrencyID; // was assetTypeID - done
					$baseCurrencyName										= $row -> baseCurrencyName; // assetTypeName - not needed
					// $quoteSpotPriceCurrencyID							= $row -> quoteSpotPriceCurrencyID; // was spotPriceCurrencyTypeID
					// $quoteSpotPriceCurrencyName							= $row -> quoteSpotPriceCurrencyName; // was spotPriceCurrencyType
						
					$quoteSpotPriceCurrencyID								= $row -> quoteSpotPriceCurrencyID; // was spotPriceCurrencyTypeID - done, needs verification
					$quoteSpotPriceCurrencyName								= $row -> quoteSpotPriceCurrencyName; // was spotPriceCurrencyType	
						
					$amount													= $row -> amount; // was btcQuantityTransacted - done	
					$fee													= $row -> fee;
					$balance												= $row -> balance;
					$baseToQuoteCurrencySpotPrice							= $row -> baseToQuoteCurrencySpotPrice;
					$baseToUSDCurrencySpotPrice								= $row -> baseToUSDCurrencySpotPrice; // was spotPriceAtTimeOfTransaction - done, needs verification
					$currencyPriceValueSourceID								= $row -> FK_CurrencyPriceValueSourceID;  // not needed
					$btcSpotPriceAtTimeOfTransaction						= $row -> btcSpotPriceAtTimeOfTransaction; // was btcPriceAtTimeOfTransaction - done, needs verification
					$transactionRecordID									= $row -> FK_TransactionRecordID; // not needed
					$creationDate											= $row -> creationDate;
					$transactionDate										= $row -> transactionDate;
					$transactionTimestamp									= $row -> transactionTimestamp; // not needed
					$vendorTransactionID									= $row -> vendorTransactionID;	
					$vendorRefID											= $row -> vendorRefID; // not needed
					$transactionAmountInQuoteCurrency						= $row -> transactionAmountInQuoteCurrency; // not needed
					$transactionAmountInUSD									= $row -> transactionAmountInUSD; // was usdQuantityTransacted - done
					$feeAmountInUSD											= $row -> feeAmountInUSD; // was usdFeeAmount - done
					$transactionAmountPlusFeeInUSD							= $row -> transactionAmountPlusFeeInUSD; // not needed
					$transactionAmountMinusFeeInUSD							= $row -> transactionAmountMinusFeeInUSD; // was usdTransactionAmountWithFees - this is the amount that changes the balance in their system - I may need to use this rather than the transaction amount in USD to get the right total amount
					$providerNotes											= $row -> providerNotes;
					$transactionTypeLabel									= $row -> transactionTypeLabel; // was displayTransactionTypeLabel - done
					$nativeTransactionTypeLabel								= $row -> nativeTransactionTypeLabel;
					$ledgerRecordID											= $row -> ledgerRecordID; //
					$isDebit												= $row -> isDebit;
					
					$sourceWalletID											= $row -> FK_BaseCurrencyWalletID;
					$destinationWalletID									= $row -> FK_QuoteCurrencyWalletID;
					
					$responseObject['processingTransaction'][]				= $vendorTransactionID;
					
					$getNativeAndCommonTransactionRecordIDsResult			= getNativeAndCommonTransactionRecordIDsForGlobalTransactionIdentificationRecordID($accountID, $baseCurrencyID, $vendorTransactionID, $transactionSourceID, $globalTransactionIdentificationRecordID, $globalCurrentDate, $sid, $dbh);
					
					errorLog("commonTransactionID: ". $getNativeAndCommonTransactionRecordIDsResult['commonTransactionRecordID']);
			
					$commonTransactionID									= $getNativeAndCommonTransactionRecordIDsResult['commonTransactionRecordID'];
			
					if (empty($commonTransactionID))
					{
					
						$unspentTransactionTotal							= 0;
						$unfundedSpendTotal									= 0;
						
						if ($isDebit == 0)
						{
							$unspentTransactionTotal  						= $amount;  // shouldn't this be the amount minus the fee amount
						}
						else if ($isDebit == 1)
						{
							$unfundedSpendTotal								= $amount; 	// shouldn't this be the amount minus the fee amount
						}	
						
						$sourceWallet										= new CompleteCryptoWallet();
						$destinationWallet									= new CompleteCryptoWallet();
				
						$sourceWalletResponseObject							= $sourceWallet -> instantiateWalletUsingCryptoWalletRecordID($liuAccountID, $sourceWalletID, $userEncryptionKey, $dbh);
				
						if ($sourceWalletResponseObject['instantiatedRecord'] == false)
						{
							errorLog("Could not instantiate source Complete Crypto Wallet record $liuAccountID");
						}
						
						$destinationWalletResponseObject					= $destinationWallet -> instantiateWalletUsingCryptoWalletRecordID($liuAccountID, $destinationWalletID, $userEncryptionKey, $dbh);
				
						if ($destinationWalletResponseObject['instantiatedRecord'] == false)
						{
							errorLog("Could not instantiate destination Complete Crypto Wallet record $liuAccountID, $destinationWalletID");
						}
						
						errorLog($vendorTransactionID."<BR>");
					
						$cryptoTransaction									= new CryptoTransaction();
					
						$cryptoTransaction -> setData(0, $accountID, $authorID, $exchangeTileID, $globalTransactionIdentificationRecordID, $transactionTypeID, $transactionTypeLabel, $nativeTransactionTypeID, $nativeTransactionTypeLabel, $transactionStatusID, $transactionStatusLabel, $transactionSourceID, $transactionSourceLabel, $baseCurrencyID, $baseCurrencyName, $quoteSpotPriceCurrencyID, $quoteSpotPriceCurrencyName, $sourceWalletID, $destinationWalletID, $creationDate, $transactionDate, $transactionTimestamp, $ledgerRecordID, $vendorTransactionID, $amount, $transactionAmountInUSD, $baseToUSDCurrencySpotPrice, $btcSpotPriceAtTimeOfTransaction, $transactionAmountMinusFeeInUSD, $fee, $feeAmountInUSD, $unspentTransactionTotal, $providerNotes, $isDebit, $sid);
					
						$writeToDatabaseResponse							= $cryptoTransaction -> writeToDatabase($userEncryptionKey, $dbh);
						
						if ($writeToDatabaseResponse['wroteToDatabase'] == true)
						{
							$transactionID									= $cryptoTransaction -> getTransactionID();
							
							errorLog("wrote transaction $transactionID for $accountID, $authorID, $globalTransactionIdentificationRecordID, $transactionTypeID, $transactionTypeLabel, $transactionStatusID, $transactionStatusLabel, $transactionSourceID, $transactionSourceLabel, $baseCurrencyID, $baseCurrencyName, $quoteSpotPriceCurrencyID, $quoteSpotPriceCurrencyName, $sourceWalletID, $destinationWalletID, $creationDate, $transactionDate, $transactionTimestamp, $ledgerRecordID, $vendorTransactionID, $amount, $transactionAmountInUSD, $baseToUSDCurrencySpotPrice, $btcSpotPriceAtTimeOfTransaction, $transactionAmountMinusFeeInUSD, $fee, $feeAmountInUSD, $unspentTransactionTotal, $providerNotes, $isDebit, $sid", $GLOBALS['debugCoreFunctionality']);
						}
						else
						{
							errorLog("could not create transaction for $accountID, $authorID, $globalTransactionIdentificationRecordID, $transactionTypeID, $transactionTypeLabel, $transactionStatusID, $transactionStatusLabel, $transactionSourceID, $transactionSourceLabel, $baseCurrencyID, $baseCurrencyName, $quoteSpotPriceCurrencyID, $quoteSpotPriceCurrencyName, $sourceWalletID, $destinationWalletID, $creationDate, $transactionDate, $transactionTimestamp, $ledgerRecordID, $vendorTransactionID, $amount, $transactionAmountInUSD, $baseToUSDCurrencySpotPrice, $btcSpotPriceAtTimeOfTransaction, $transactionAmountMinusFeeInUSD, $fee, $feeAmountInUSD, $unspentTransactionTotal, $providerNotes, $isDebit, $sid", $GLOBALS['debugCoreFunctionality']);	
						}
					}
					else
					{
						errorLog("found transaction $commonTransactionID for $accountID, $authorID, $globalTransactionIdentificationRecordID, $transactionTypeID, $transactionTypeLabel, $transactionStatusID, $transactionStatusLabel, $transactionSourceID, $transactionSourceLabel, $baseCurrencyID, $baseCurrencyName, $quoteSpotPriceCurrencyID, $quoteSpotPriceCurrencyName, $sourceWalletID, $destinationWalletID, $creationDate, $transactionDate, $transactionTimestamp, $ledgerRecordID, $vendorTransactionID, $amount, $transactionAmountInUSD, $baseToUSDCurrencySpotPrice, $btcSpotPriceAtTimeOfTransaction, $transactionAmountMinusFeeInUSD, $fee, $feeAmountInUSD, $unspentTransactionTotal, $providerNotes, $isDebit, $sid", $GLOBALS['debugCoreFunctionality']);	
					}
				}
			}
			else
			{
				errorLog("SELECT
	KrakenLedgerTransactions.ledgerRecordID,
	KrakenLedgerTransactions.FK_GlobalTransactionRecordID,
	KrakenLedgerTransactions.FK_AccountID AS authorID,
	KrakenLedgerTransactions.FK_AccountID AS accountID,
	KrakenLedgerTransactions.FK_ProviderAccountWalletID,
	CASE
		WHEN KrakenLedgerTransactions.FK_LedgerEntryTypeID = 1 THEN 7
		WHEN KrakenLedgerTransactions.FK_LedgerEntryTypeID = 2 THEN 8
		WHEN 
			KrakenLedgerTransactions.FK_LedgerEntryTypeID > 2 AND
			KrakenLedgerTransactions.isDebit = 0 THEN 1
		WHEN 
			KrakenLedgerTransactions.FK_LedgerEntryTypeID > 2 AND
			KrakenLedgerTransactions.isDebit = 1 THEN 4
		ELSE 14
	END AS transactionTypeID,
	KrakenLedgerTransactions.FK_AssetClassID,
	AES_DECRYPT(KrakenLedgerTransactions.encryptedPairName, UNHEX(SHA2('$userEncryptionKey',512))) AS pairName,
	KrakenLedgerTransactions.FK_KrakenCurrencyPairID,
	baseCurrency.assetTypeID AS baseCurrencyID,
	baseCurrency.assetTypeLabel AS baseCurrencyName,
	quoteCurrency.assetTypeID AS quoteSpotPriceCurrencyID,
	quoteCurrency.assetTypeLabel AS quoteSpotPriceCurrencyName,
	KrakenLedgerTransactions.amount,
	KrakenLedgerTransactions.fee,
	KrakenLedgerTransactions.balance,
	KrakenLedgerTransactions.isDebit,
	KrakenLedgerTransactions.baseToQuoteCurrencySpotPrice,
	KrakenLedgerTransactions.baseToUSDCurrencySpotPrice,
	KrakenLedgerTransactions.FK_CurrencyPriceValueSourceID,
	KrakenLedgerTransactions.FK_TransactionRecordID,
	KrakenLedgerTransactions.FK_BaseCurrencyWalletID,
	KrakenLedgerTransactions.FK_QuoteCurrencyWalletID,
	KrakenLedgerTransactions.transactionTime AS creationDate,
	KrakenLedgerTransactions.transactionTime AS transactionDate,
	KrakenLedgerTransactions.transactionTimestamp,
	AES_DECRYPT(KrakenLedgerTransactions.encryptedLedgerIDValue, UNHEX(SHA2('$userEncryptionKey',512))) AS vendorTransactionID,
	AES_DECRYPT(KrakenLedgerTransactions.encryptedRefIDValue, UNHEX(SHA2('$userEncryptionKey',512))) AS vendorRefID,
	KrakenLedgerTransactions.amount * KrakenLedgerTransactions.baseToQuoteCurrencySpotPrice AS transactionAmountInQuoteCurrency,
	KrakenLedgerTransactions.amount * KrakenLedgerTransactions.baseToUSDCurrencySpotPrice AS transactionAmountInUSD,
	KrakenLedgerTransactions.fee * KrakenLedgerTransactions.baseToUSDCurrencySpotPrice AS feeAmountInUSD,
	(KrakenLedgerTransactions.amount * KrakenLedgerTransactions.baseToUSDCurrencySpotPrice) + (KrakenLedgerTransactions.fee * KrakenLedgerTransactions.baseToUSDCurrencySpotPrice) AS transactionAmountPlusFeeInUSD,
	(KrakenLedgerTransactions.amount * KrakenLedgerTransactions.baseToUSDCurrencySpotPrice) - (KrakenLedgerTransactions.fee * KrakenLedgerTransactions.baseToUSDCurrencySpotPrice) AS transactionAmountMinusFeeInUSD,
	'' AS  providerNotes,
	KrakenLedgerTransactions.FK_BaseCurrencyWalletID AS FK_SourceAddressID,
	KrakenLedgerTransactions.FK_QuoteCurrencyWalletID AS FK_DestinationAddressID,
	CASE
		WHEN KrakenLedgerTransactions.FK_LedgerEntryTypeID = 1 THEN 'Deposit'
		WHEN KrakenLedgerTransactions.FK_LedgerEntryTypeID = 2 THEN 'Withdrawal'
		WHEN 
			KrakenLedgerTransactions.FK_LedgerEntryTypeID > 2 AND
			KrakenLedgerTransactions.isDebit = 1 THEN 'Sell'
		WHEN 
			KrakenLedgerTransactions.FK_LedgerEntryTypeID > 2 AND
			KrakenLedgerTransactions.isDebit = 0 THEN 'Buy'
		ELSE 'Adjustement'
	END AS transactionTypeLabel
FROM
	KrakenLedgerTransactions
	INNER JOIN CommonCurrencyPairs ON KrakenLedgerTransactions.FK_KrakenCurrencyPairID = CommonCurrencyPairs.pairID
	INNER JOIN AssetTypes baseCurrency ON CommonCurrencyPairs.FK_BaseCurrencyID = baseCurrency.assetTypeID AND baseCurrency.languageCode = 'EN'
	INNER JOIN AssetTypes quoteCurrency ON CommonCurrencyPairs.FK_QuoteCurrencyID = quoteCurrency.assetTypeID AND quoteCurrency.languageCode = 'EN'
WHERE
	KrakenLedgerTransactions.FK_AccountID = $liuAccountID AND
	baseCurrency.assetTypeID = $baseCurrencyTypeID
ORDER BY
	KrakenLedgerTransactions.transactionTimestamp");	
			}
			
			$responseObject['importedTransactions']							= true;
		}
		catch (PDOException $e) 
		{
			$cryptoTransaction 												= null;	
			$responseObject['importedTransactions']							= false;
			
			errorLog($e -> getMessage());
		
			die();
		}
		
		return $responseObject;
	}

	function createCommonTransactionsForBinanceTradeTransactions($liuAccountID, $userEncryptionKey, $globalCurrentDate, $sid, $dbh)
	{
		errorLog("createCommonTransactionsForBinanceTradeTransactions($liuAccountID, $userEncryptionKey, $globalCurrentDate, $sid");
		
		$responseObject														= array();
		$responseObject['createCommonTransactionsForBinanceTransactions']	= false;
		
		$transactionSourceID												= 4;
		$transactionStatusID												= 1;
		$transactionStatusLabel												= "complete";
		$transactionSourceLabel												= "Binance";
		
		try
		{		
			$getBinanceTransactionRecords									= $dbh -> prepare("SELECT
		BinanceTradeTransactions.binanceTransactionRecordID AS transactionID,
		BinanceTradeTransactions.FK_ExchangeTileID,
		BinanceTradeTransactions.FK_GlobalTransactionRecordID AS FK_GlobalTransactionIdentificationRecordID,
		BinanceTradeTransactions.FK_AccountID AS authorID,
		BinanceTradeTransactions.FK_AccountID AS accountID,
		BinanceTradeTransactions.FK_TransactionTypeID AS transactionTypeID,
		BinanceTradeTransactions.FK_BaseCurrencyTypeID AS baseCurrencyID,		
		baseCurrencyAsset.assetTypeLabel AS baseCurrencyName,
		2 AS quoteSpotPriceCurrencyID,
		'USD' AS quoteSpotPriceCurrencyName,
		BinanceTradeTransactions.creationDate,
		BinanceTradeTransactions.transactionTime AS transactionDate,
		BinanceTradeTransactions.transactionTimestamp,
		AES_DECRYPT(BinanceTradeTransactions.encryptedTxIDValue, UNHEX(SHA2(:userEncryptionKey,512))) AS vendorTransactionID,
		ABS(BinanceTradeTransactions.volBaseCurrency) AS btcQuantityTransacted,
		ABS(BinanceTradeTransactions.usdAmount) AS usdQuantityTransacted,
		BinanceTradeTransactions.spotPriceAtTimeOfTransaction AS spotPriceAtTimeOfTransaction,
		BinanceTradeTransactions.btcPriceAtTimeOfTransaction AS btcPriceAtTimeOfTransaction,
		ABS(BinanceTradeTransactions.usdAmount) + ABS(BinanceTradeTransactions.feeAmountInUSD) AS usdTransactionAmountWithFees,
		BinanceTradeTransactions.feeAmountInUSD AS networkTransactionFeeAmount,
		ABS(BinanceTradeTransactions.feeAmountInUSD) AS usdFeeAmount,
		ABS(BinanceTradeTransactions.usdAmount) - ABS(BinanceTradeTransactions.feeAmountInUSD) AS transactionAmountMinusFeeInUSD,
		ABS(BinanceTradeTransactions.usdAmount) + ABS(BinanceTradeTransactions.feeAmountInUSD) AS transactionAmountPlusFeeInUSD,
		'' AS  providerNotes,
		BinanceTradeTransactions.isDebit,
		BinanceTradeTransactions.FK_BaseCurrencyWalletID AS FK_SourceAddressID,
		BinanceTradeTransactions.FK_QuoteCurrencyWalletID AS FK_DestinationAddressID,
		TransactionTypes.displayTransactionTypeLabel,
		TransactionTypes.transactionTypeLabel
	FROM
		BinanceTradeTransactions
		INNER JOIN TransactionTypes ON BinanceTradeTransactions.FK_TransactionTypeID = TransactionTypes.transactionTypeID AND TransactionTypes.languageCode = 'EN'
		INNER JOIN AssetTypes baseCurrencyAsset ON BinanceTradeTransactions.FK_BaseCurrencyTypeID = baseCurrencyAsset.assetTypeID AND baseCurrencyAsset.languageCode = 'EN'
	WHERE
		BinanceTradeTransactions.FK_AccountID = :accountID
	ORDER BY
		BinanceTradeTransactions.transactionTime");
	
			$getBinanceTransactionRecords -> bindValue(':accountID', $liuAccountID);
			$getBinanceTransactionRecords -> bindValue(':userEncryptionKey', $userEncryptionKey);
		
			if ($getBinanceTransactionRecords -> execute() && $getBinanceTransactionRecords -> rowCount() > 0)
			{
				errorLog("began get binance crypto transaction records ".$getBinanceTransactionRecords -> rowCount() > 0);
				
				while ($row = $getBinanceTransactionRecords -> fetchObject())
				{		
					$transactionID											= $row -> transactionID;
					$exchangeTileID											= $row -> FK_ExchangeTileID;
					$globalTransactionIdentificationRecordID				= $row -> FK_GlobalTransactionIdentificationRecordID;
					$accountID												= $row -> accountID;	
					$authorID												= $row -> authorID;
						
					$transactionTypeID										= $row -> transactionTypeID;
					$baseCurrencyID											= $row -> baseCurrencyID; // was assetTypeID - done
					$baseCurrencyName										= $row -> baseCurrencyName; // assetTypeName - not needed
							
					$quoteSpotPriceCurrencyID								= $row -> quoteSpotPriceCurrencyID; // was spotPriceCurrencyTypeID - done, needs verification
					$quoteSpotPriceCurrencyName								= $row -> quoteSpotPriceCurrencyName; // was spotPriceCurrencyType	
						
							
					$amount													= $row -> btcQuantityTransacted;
					$fee													= $row -> networkTransactionFeeAmount;
					$baseToUSDCurrencySpotPrice								= $row -> spotPriceAtTimeOfTransaction;
					$btcSpotPriceAtTimeOfTransaction						= $row -> btcPriceAtTimeOfTransaction;
					$creationDate											= $row -> creationDate;
					$transactionDate										= $row -> transactionDate;
					$transactionTimestamp									= $row -> transactionTimestamp;
					$vendorTransactionID									= $row -> vendorTransactionID;	
					$transactionAmountInUSD									= $row -> usdQuantityTransacted;
					$transactionAmountMinusFeeInUSD							= $row -> transactionAmountMinusFeeInUSD;
					$transactionAmountPlusFeeInUSD							= $row -> transactionAmountPlusFeeInUSD;
					$feeAmountInUSD											= $row -> usdFeeAmount;
					$usdTransactionAmountWithFees							= $row -> usdTransactionAmountWithFees;
					$providerNotes											= $row -> providerNotes;
					$transactionTypeLabel									= $row -> transactionTypeLabel;
					$displayTransactionTypeLabel							= $row -> displayTransactionTypeLabel;
					$isDebit												= $row -> isDebit;
						
					$sourceWalletID											= $row -> FK_SourceAddressID;
					$destinationWalletID									= $row -> FK_DestinationAddressID;
						
					$responseObject['processingTransaction'][]				= $vendorTransactionID;
					
					$getNativeAndCommonTransactionRecordIDsResult			= getNativeAndCommonTransactionRecordIDsForGlobalTransactionIdentificationRecordID($accountID, $baseCurrencyID, $vendorTransactionID, $transactionSourceID, $globalTransactionIdentificationRecordID, $globalCurrentDate, $sid, $dbh);
					
					errorLog("commonTransactionID: ". $getNativeAndCommonTransactionRecordIDsResult['commonTransactionRecordID']);
			
					$commonTransactionID									= $getNativeAndCommonTransactionRecordIDsResult['commonTransactionRecordID'];
			
					if (empty($commonTransactionID))
					{
						$unspentTransactionTotal							= 0;
						$unfundedSpendTotal									= 0;
						
						if ($isDebit == 0)
						{
							$unspentTransactionTotal  						= $amount;  // shouldn't this be the amount minus the fee amount
						}
						else if ($isDebit == 1)
						{
							$unfundedSpendTotal								= $amount; 	// shouldn't this be the amount minus the fee amount
						}	
						
						$sourceWallet										= new CompleteCryptoWallet();
						$destinationWallet									= new CompleteCryptoWallet();
				
						$sourceWalletResponseObject							= $sourceWallet -> instantiateWalletUsingCryptoWalletRecordID($accountID, $sourceWalletID, $userEncryptionKey, $dbh);
				
						if ($sourceWalletResponseObject['instantiatedRecord'] == false)
						{
							errorLog("Could not instantiate source Complete Crypto Wallet record $accountID");
						}
						
						$destinationWalletResponseObject					= $destinationWallet -> instantiateWalletUsingCryptoWalletRecordID($accountID, $destinationWalletID, $userEncryptionKey, $dbh);
				
						if ($destinationWalletResponseObject['instantiatedRecord'] == false)
						{
							errorLog("Could not instantiate destination Complete Crypto Wallet record $accountID, $destinationWalletID");
						}
						
						errorLog($vendorTransactionID."<BR>");
					
						$cryptoTransaction									= new CryptoTransaction();
					
						$cryptoTransaction -> setData(0, $accountID, $authorID, $exchangeTileID, $globalTransactionIdentificationRecordID, $transactionTypeID, $transactionTypeLabel, $transactionStatusID, $transactionStatusLabel, $transactionSourceID, $transactionSourceLabel, $baseCurrencyID, $baseCurrencyName, $quoteSpotPriceCurrencyID, $quoteSpotPriceCurrencyName, $sourceWalletID, $destinationWalletID, $creationDate, $transactionDate, $transactionTimestamp, $transactionID, $vendorTransactionID, $amount, $transactionAmountInUSD, $baseToUSDCurrencySpotPrice, $btcSpotPriceAtTimeOfTransaction, $transactionAmountMinusFeeInUSD, $fee, $feeAmountInUSD, $unspentTransactionTotal, $providerNotes, $isDebit, $sid);
					
						$writeToDatabaseResponse							= $cryptoTransaction -> writeToDatabase($userEncryptionKey, $dbh);
						
						if ($writeToDatabaseResponse['wroteToDatabase'] == true)
						{
							$transactionID									= $cryptoTransaction -> getTransactionID();
							
							errorLog("wrote transaction $transactionID for $liuAccountID, $liuAccountID, $globalTransactionIdentificationRecordID, $transactionTypeID, $transactionTypeLabel, $transactionStatusID, $transactionStatusLabel, $transactionSourceID, $transactionSourceLabel, $baseCurrencyID, $baseCurrencyName, $quoteSpotPriceCurrencyID, $quoteSpotPriceCurrencyName, $sourceWalletID, $destinationWalletID, $creationDate, $transactionDate, $transactionTimestamp, $transactionID, $vendorTransactionID, $amount, $transactionAmountInUSD, $baseToUSDCurrencySpotPrice, $btcSpotPriceAtTimeOfTransaction, $transactionAmountMinusFeeInUSD, $fee, $feeAmountInUSD, $unspentTransactionTotal, $providerNotes, $isDebit, $sid", $GLOBALS['debugCoreFunctionality']);
						}
						else
						{
							errorLog("could not create transaction for $liuAccountID, $liuAccountID, $globalTransactionIdentificationRecordID, $transactionTypeID, $transactionTypeLabel, $transactionStatusID, $transactionStatusLabel, $transactionSourceID, $transactionSourceLabel, $baseCurrencyID, $baseCurrencyName, $quoteSpotPriceCurrencyID, $quoteSpotPriceCurrencyName, $sourceWalletID, $destinationWalletID, $creationDate, $transactionDate, $transactionTimestamp, $transactionID, $vendorTransactionID, $amount, $transactionAmountInUSD, $baseToUSDCurrencySpotPrice, $btcSpotPriceAtTimeOfTransaction, $transactionAmountMinusFeeInUSD, $fee, $feeAmountInUSD, $unspentTransactionTotal, $providerNotes, $isDebit, $sid", $GLOBALS['debugCoreFunctionality']);	
						}
					}
					else
					{
						errorLog("found transaction $commonTransactionID for $liuAccountID, $liuAccountID, $globalTransactionIdentificationRecordID, $transactionTypeID, $transactionTypeLabel, $transactionStatusID, $transactionStatusLabel, $transactionSourceID, $transactionSourceLabel, $baseCurrencyID, $baseCurrencyName, $quoteSpotPriceCurrencyID, $quoteSpotPriceCurrencyName, $sourceWalletID, $destinationWalletID, $creationDate, $transactionDate, $transactionTimestamp, $transactionID, $vendorTransactionID, $amount, $transactionAmountInUSD, $baseToUSDCurrencySpotPrice, $btcSpotPriceAtTimeOfTransaction, $transactionAmountMinusFeeInUSD, $fee, $feeAmountInUSD, $unspentTransactionTotal, $providerNotes, $isDebit, $sid", $GLOBALS['debugCoreFunctionality']);	
					}
				}
			}
			else
			{
				errorLog("SELECT
		BinanceTradeTransactions.binanceTransactionRecordID AS transactionID,
		BinanceTradeTransactions.FK_ExchangeTileID,
		BinanceTradeTransactions.FK_GlobalTransactionRecordID AS FK_GlobalTransactionIdentificationRecordID,
		BinanceTradeTransactions.FK_AccountID AS authorID,
		BinanceTradeTransactions.FK_AccountID AS accountID,
		BinanceTradeTransactions.FK_TransactionTypeID AS transactionTypeID,
		BinanceTradeTransactions.FK_BaseCurrencyTypeID AS baseCurrencyID,		
		baseCurrencyAsset.assetTypeLabel AS baseCurrencyName,
		2 AS quoteSpotPriceCurrencyID,
		'USD' AS quoteSpotPriceCurrencyName,
		BinanceTradeTransactions.creationDate,
		BinanceTradeTransactions.transactionTime AS transactionDate,
		BinanceTradeTransactions.transactionTimestamp,
		AES_DECRYPT(BinanceTradeTransactions.encryptedTxIDValue, UNHEX(SHA2('$userEncryptionKey',512))) AS vendorTransactionID,
		ABS(BinanceTradeTransactions.volBaseCurrency) AS btcQuantityTransacted,
		ABS(BinanceTradeTransactions.usdAmount) AS usdQuantityTransacted,
		BinanceTradeTransactions.spotPriceAtTimeOfTransaction AS spotPriceAtTimeOfTransaction,
		BinanceTradeTransactions.btcPriceAtTimeOfTransaction AS btcPriceAtTimeOfTransaction,
		ABS(BinanceTradeTransactions.usdAmount) + ABS(BinanceTradeTransactions.feeAmountInUSD) AS usdTransactionAmountWithFees,
		BinanceTradeTransactions.feeAmountInUSD AS networkTransactionFeeAmount,
		ABS(BinanceTradeTransactions.feeAmountInUSD) AS usdFeeAmount,
		ABS(BinanceTradeTransactions.usdAmount) - ABS(BinanceTradeTransactions.feeAmountInUSD) AS transactionAmountMinusFeeInUSD,
		ABS(BinanceTradeTransactions.usdAmount) + ABS(BinanceTradeTransactions.feeAmountInUSD) AS transactionAmountPlusFeeInUSD,
		'' AS  providerNotes,
		BinanceTradeTransactions.isDebit,
		BinanceTradeTransactions.FK_BaseCurrencyWalletID AS FK_SourceAddressID,
		BinanceTradeTransactions.FK_QuoteCurrencyWalletID AS FK_DestinationAddressID,
		TransactionTypes.displayTransactionTypeLabel,
		TransactionTypes.transactionTypeLabel
	FROM
		BinanceTradeTransactions
		INNER JOIN TransactionTypes ON BinanceTradeTransactions.FK_TransactionTypeID = TransactionTypes.transactionTypeID AND TransactionTypes.languageCode = 'EN'
		INNER JOIN AssetTypes baseCurrencyAsset ON BinanceTradeTransactions.FK_BaseCurrencyTypeID = baseCurrencyAsset.assetTypeID AND baseCurrencyAsset.languageCode = 'EN'
	WHERE
		BinanceTradeTransactions.FK_AccountID = $liuAccountID
	ORDER BY
		BinanceTradeTransactions.transactionTime", $GLOBALS['debugCoreFunctionality']);	
			}
			
			$responseObject['importedTransactions']							= true;
		}
		catch (PDOException $e) 
		{
			$cryptoTransaction 												= null;	
			$responseObject['importedTransactions']							= false;
			
			errorLog($e -> getMessage(), $GLOBALS['criticalErrors']);
		
			die();
		}
		
		return $responseObject;
	}
	
	function createCommonTransactionsForPoloniexTradeTransactions($liuAccountID, $userEncryptionKey, $globalCurrentDate, $sid, $dbh)
	{
		errorLog("createCommonTransactionsForPoloniexTradeTransactions($liuAccountID, $userEncryptionKey, $globalCurrentDate, $sid");
		
		$responseObject														= array();
		$responseObject['createCommonTransactionsForPoloniexTradeTransactions']	= false;
		
		$transactionSourceID												= 15;
		$transactionStatusID												= 1;
		$transactionStatusLabel												= "complete";
		$transactionSourceLabel												= "Poloniex";
		
		try
		{		
			$getPoloniexTransactionRecords									= $dbh -> prepare("SELECT
		PoloniexTradeTransactions.poloniexTransactionRecordID AS transactionID,
		PoloniexTradeTransactions.FK_ExchangeTileID,
		PoloniexTradeTransactions.FK_GlobalTransactionRecordID AS FK_GlobalTransactionIdentificationRecordID,
		PoloniexTradeTransactions.FK_AccountID AS authorID,
		PoloniexTradeTransactions.FK_AccountID AS accountID,
		PoloniexTradeTransactions.FK_TransactionTypeID AS transactionTypeID,
		PoloniexTradeTransactions.FK_BaseCurrencyTypeID AS baseCurrencyID,		
		baseCurrencyAsset.assetTypeLabel AS baseCurrencyName,
		2 AS quoteSpotPriceCurrencyID,
		'USD' AS quoteSpotPriceCurrencyName,
		PoloniexTradeTransactions.creationDate,
		PoloniexTradeTransactions.transactionTime AS transactionDate,
		UNIX_TIMESTAMP(PoloniexTradeTransactions.transactionTime) AS transactionTimestamp,
		AES_DECRYPT(PoloniexTradeTransactions.encryptedTxIDValue, UNHEX(SHA2(:userEncryptionKey,512))) AS vendorTransactionID,
		ABS(PoloniexTradeTransactions.volBaseCurrency) AS btcQuantityTransacted,
		ABS(PoloniexTradeTransactions.usdAmount) AS usdQuantityTransacted,
		PoloniexTradeTransactions.spotPriceAtTimeOfTransaction AS spotPriceAtTimeOfTransaction,
		PoloniexTradeTransactions.btcPriceAtTimeOfTransaction AS btcPriceAtTimeOfTransaction,
		ABS(PoloniexTradeTransactions.usdAmount) + ABS(PoloniexTradeTransactions.feeAmountInUSD) AS usdTransactionAmountWithFees,
		PoloniexTradeTransactions.feeAmountInUSD AS networkTransactionFeeAmount,
		ABS(PoloniexTradeTransactions.feeAmountInUSD) AS usdFeeAmount,
		ABS(PoloniexTradeTransactions.usdAmount) - ABS(PoloniexTradeTransactions.feeAmountInUSD) AS transactionAmountMinusFeeInUSD,
		ABS(PoloniexTradeTransactions.usdAmount) + ABS(PoloniexTradeTransactions.feeAmountInUSD) AS transactionAmountPlusFeeInUSD,
		'' AS  providerNotes,
		PoloniexTradeTransactions.isDebit,
		PoloniexTradeTransactions.FK_BaseCurrencyWalletID AS FK_SourceAddressID,
		PoloniexTradeTransactions.FK_QuoteCurrencyWalletID AS FK_DestinationAddressID,
		TransactionTypes.displayTransactionTypeLabel,
		TransactionTypes.transactionTypeLabel
	FROM
		PoloniexTradeTransactions
		INNER JOIN TransactionTypes ON PoloniexTradeTransactions.FK_TransactionTypeID = TransactionTypes.transactionTypeID AND TransactionTypes.languageCode = 'EN'
		INNER JOIN AssetTypes baseCurrencyAsset ON PoloniexTradeTransactions.FK_BaseCurrencyTypeID = baseCurrencyAsset.assetTypeID AND baseCurrencyAsset.languageCode = 'EN'
	WHERE
		PoloniexTradeTransactions.FK_AccountID = :accountID
	ORDER BY
		PoloniexTradeTransactions.transactionTime");
	
			$getPoloniexTransactionRecords -> bindValue(':accountID', $liuAccountID);
			$getPoloniexTransactionRecords -> bindValue(':userEncryptionKey', $userEncryptionKey);
		
			if ($getPoloniexTransactionRecords -> execute() && $getPoloniexTransactionRecords -> rowCount() > 0)
			{
				errorLog("began get poloniex crypto transaction records ".$getPoloniexTransactionRecords -> rowCount() > 0);
				
				while ($row = $getPoloniexTransactionRecords -> fetchObject())
				{		
					$transactionID											= $row -> transactionID;
					$exchangeTileID											= $row -> FK_ExchangeTileID;
					$globalTransactionIdentificationRecordID				= $row -> FK_GlobalTransactionIdentificationRecordID;
					$accountID												= $row -> accountID;	
					$authorID												= $row -> authorID;
						
					$transactionTypeID										= $row -> transactionTypeID;
					$baseCurrencyID											= $row -> baseCurrencyID; // was assetTypeID - done
					$baseCurrencyName										= $row -> baseCurrencyName; // assetTypeName - not needed
							
					$quoteSpotPriceCurrencyID								= $row -> quoteSpotPriceCurrencyID; // was spotPriceCurrencyTypeID - done, needs verification
					$quoteSpotPriceCurrencyName								= $row -> quoteSpotPriceCurrencyName; // was spotPriceCurrencyType	
						
							
					$amount													= $row -> btcQuantityTransacted;
					$fee													= $row -> networkTransactionFeeAmount;
					$baseToUSDCurrencySpotPrice								= $row -> spotPriceAtTimeOfTransaction;
					$btcSpotPriceAtTimeOfTransaction						= $row -> btcPriceAtTimeOfTransaction;
					$creationDate											= $row -> creationDate;
					$transactionDate										= $row -> transactionDate;
					$transactionTimestamp									= $row -> transactionTimestamp;
					$vendorTransactionID									= $row -> vendorTransactionID;	
					$transactionAmountInUSD									= $row -> usdQuantityTransacted;
					$transactionAmountMinusFeeInUSD							= $row -> transactionAmountMinusFeeInUSD;
					$transactionAmountPlusFeeInUSD							= $row -> transactionAmountPlusFeeInUSD;
					$feeAmountInUSD											= $row -> usdFeeAmount;
					$usdTransactionAmountWithFees							= $row -> usdTransactionAmountWithFees;
					$providerNotes											= $row -> providerNotes;
					$transactionTypeLabel									= $row -> transactionTypeLabel;
					$displayTransactionTypeLabel							= $row -> displayTransactionTypeLabel;
					$isDebit												= $row -> isDebit;
						
					$sourceWalletID											= $row -> FK_SourceAddressID;
					$destinationWalletID									= $row -> FK_DestinationAddressID;
						
					$responseObject['processingTransaction'][]				= $vendorTransactionID;
					
					$getNativeAndCommonTransactionRecordIDsResult			= getNativeAndCommonTransactionRecordIDsForGlobalTransactionIdentificationRecordID($accountID, $baseCurrencyID, $vendorTransactionID, $transactionSourceID, $globalTransactionIdentificationRecordID, $globalCurrentDate, $sid, $dbh);
					
					errorLog("commonTransactionID: ". $getNativeAndCommonTransactionRecordIDsResult['commonTransactionRecordID']);
			
					$commonTransactionID									= $getNativeAndCommonTransactionRecordIDsResult['commonTransactionRecordID'];
			
					if (empty($commonTransactionID))
					{
						$unspentTransactionTotal							= 0;
						$unfundedSpendTotal									= 0;
						
						if ($isDebit == 0)
						{
							$unspentTransactionTotal  						= $amount;  // shouldn't this be the amount minus the fee amount
						}
						else if ($isDebit == 1)
						{
							$unfundedSpendTotal								= $amount; 	// shouldn't this be the amount minus the fee amount
						}	
						
						$sourceWallet										= new CompleteCryptoWallet();
						$destinationWallet									= new CompleteCryptoWallet();
				
						$sourceWalletResponseObject							= $sourceWallet -> instantiateWalletUsingCryptoWalletRecordID($accountID, $sourceWalletID, $userEncryptionKey, $dbh);
				
						if ($sourceWalletResponseObject['instantiatedRecord'] == false)
						{
							errorLog("Could not instantiate source Complete Crypto Wallet record $accountID");
						}
						
						$destinationWalletResponseObject					= $destinationWallet -> instantiateWalletUsingCryptoWalletRecordID($accountID, $destinationWalletID, $userEncryptionKey, $dbh);
				
						if ($destinationWalletResponseObject['instantiatedRecord'] == false)
						{
							errorLog("Could not instantiate destination Complete Crypto Wallet record $accountID, $destinationWalletID");
						}
						
						errorLog($vendorTransactionID."<BR>");
					
						$cryptoTransaction									= new CryptoTransaction();
					
						$cryptoTransaction -> setData(0, $accountID, $authorID, $exchangeTileID, $globalTransactionIdentificationRecordID, $transactionTypeID, $transactionTypeLabel, $transactionStatusID, $transactionStatusLabel, $transactionSourceID, $transactionSourceLabel, $baseCurrencyID, $baseCurrencyName, $quoteSpotPriceCurrencyID, $quoteSpotPriceCurrencyName, $sourceWalletID, $destinationWalletID, $creationDate, $transactionDate, $transactionTimestamp, $transactionID, $vendorTransactionID, $amount, $transactionAmountInUSD, $baseToUSDCurrencySpotPrice, $btcSpotPriceAtTimeOfTransaction, $transactionAmountMinusFeeInUSD, $fee, $feeAmountInUSD, $unspentTransactionTotal, $providerNotes, $isDebit, $sid);
					
						$writeToDatabaseResponse							= $cryptoTransaction -> writeToDatabase($userEncryptionKey, $dbh);
						
						if ($writeToDatabaseResponse['wroteToDatabase'] == true)
						{
							$transactionID									= $cryptoTransaction -> getTransactionID();
							
							errorLog("wrote transaction $transactionID for $liuAccountID, $liuAccountID, $globalTransactionIdentificationRecordID, $transactionTypeID, $transactionTypeLabel, $transactionStatusID, $transactionStatusLabel, $transactionSourceID, $transactionSourceLabel, $baseCurrencyID, $baseCurrencyName, $quoteSpotPriceCurrencyID, $quoteSpotPriceCurrencyName, $sourceWalletID, $destinationWalletID, $creationDate, $transactionDate, $transactionTimestamp, $transactionID, $vendorTransactionID, $amount, $transactionAmountInUSD, $baseToUSDCurrencySpotPrice, $btcSpotPriceAtTimeOfTransaction, $transactionAmountMinusFeeInUSD, $fee, $feeAmountInUSD, $unspentTransactionTotal, $providerNotes, $isDebit, $sid", $GLOBALS['debugCoreFunctionality']);
						}
						else
						{
							errorLog("could not create transaction for $liuAccountID, $liuAccountID, $globalTransactionIdentificationRecordID, $transactionTypeID, $transactionTypeLabel, $transactionStatusID, $transactionStatusLabel, $transactionSourceID, $transactionSourceLabel, $baseCurrencyID, $baseCurrencyName, $quoteSpotPriceCurrencyID, $quoteSpotPriceCurrencyName, $sourceWalletID, $destinationWalletID, $creationDate, $transactionDate, $transactionTimestamp, $transactionID, $vendorTransactionID, $amount, $transactionAmountInUSD, $baseToUSDCurrencySpotPrice, $btcSpotPriceAtTimeOfTransaction, $transactionAmountMinusFeeInUSD, $fee, $feeAmountInUSD, $unspentTransactionTotal, $providerNotes, $isDebit, $sid", $GLOBALS['debugCoreFunctionality']);	
						}
					}
					else
					{
						errorLog("found transaction $commonTransactionID for $liuAccountID, $liuAccountID, $globalTransactionIdentificationRecordID, $transactionTypeID, $transactionTypeLabel, $transactionStatusID, $transactionStatusLabel, $transactionSourceID, $transactionSourceLabel, $baseCurrencyID, $baseCurrencyName, $quoteSpotPriceCurrencyID, $quoteSpotPriceCurrencyName, $sourceWalletID, $destinationWalletID, $creationDate, $transactionDate, $transactionTimestamp, $transactionID, $vendorTransactionID, $amount, $transactionAmountInUSD, $baseToUSDCurrencySpotPrice, $btcSpotPriceAtTimeOfTransaction, $transactionAmountMinusFeeInUSD, $fee, $feeAmountInUSD, $unspentTransactionTotal, $providerNotes, $isDebit, $sid", $GLOBALS['debugCoreFunctionality']);	
					}
				}
			}
			else
			{
				errorLog("SELECT
		PoloniexTradeTransactions.poloniexTransactionRecordID AS transactionID,
		PoloniexTradeTransactions.FK_ExchangeTileID,
		PoloniexTradeTransactions.FK_GlobalTransactionRecordID AS FK_GlobalTransactionIdentificationRecordID,
		PoloniexTradeTransactions.FK_AccountID AS authorID,
		PoloniexTradeTransactions.FK_AccountID AS accountID,
		PoloniexTradeTransactions.FK_TransactionTypeID AS transactionTypeID,
		PoloniexTradeTransactions.FK_BaseCurrencyTypeID AS baseCurrencyID,		
		baseCurrencyAsset.assetTypeLabel AS baseCurrencyName,
		2 AS quoteSpotPriceCurrencyID,
		'USD' AS quoteSpotPriceCurrencyName,
		PoloniexTradeTransactions.creationDate,
		PoloniexTradeTransactions.transactionTime AS transactionDate,
		PoloniexTradeTransactions.transactionTimestamp,
		AES_DECRYPT(PoloniexTradeTransactions.encryptedTxIDValue, UNHEX(SHA2('$userEncryptionKey',512))) AS vendorTransactionID,
		ABS(PoloniexTradeTransactions.volBaseCurrency) AS btcQuantityTransacted,
		ABS(PoloniexTradeTransactions.usdAmount) AS usdQuantityTransacted,
		PoloniexTradeTransactions.spotPriceAtTimeOfTransaction AS spotPriceAtTimeOfTransaction,
		PoloniexTradeTransactions.btcPriceAtTimeOfTransaction AS btcPriceAtTimeOfTransaction,
		ABS(PoloniexTradeTransactions.usdAmount) + ABS(PoloniexTradeTransactions.feeAmountInUSD) AS usdTransactionAmountWithFees,
		PoloniexTradeTransactions.feeAmountInUSD AS networkTransactionFeeAmount,
		ABS(PoloniexTradeTransactions.feeAmountInUSD) AS usdFeeAmount,
		ABS(PoloniexTradeTransactions.usdAmount) - ABS(PoloniexTradeTransactions.feeAmountInUSD) AS transactionAmountMinusFeeInUSD,
		ABS(PoloniexTradeTransactions.usdAmount) + ABS(PoloniexTradeTransactions.feeAmountInUSD) AS transactionAmountPlusFeeInUSD,
		'' AS  providerNotes,
		PoloniexTradeTransactions.isDebit,
		PoloniexTradeTransactions.FK_BaseCurrencyWalletID AS FK_SourceAddressID,
		PoloniexTradeTransactions.FK_QuoteCurrencyWalletID AS FK_DestinationAddressID,
		TransactionTypes.displayTransactionTypeLabel,
		TransactionTypes.transactionTypeLabel
	FROM
		PoloniexTradeTransactions
		INNER JOIN TransactionTypes ON PoloniexTradeTransactions.FK_TransactionTypeID = TransactionTypes.transactionTypeID AND TransactionTypes.languageCode = 'EN'
		INNER JOIN AssetTypes baseCurrencyAsset ON PoloniexTradeTransactions.FK_BaseCurrencyTypeID = baseCurrencyAsset.assetTypeID AND baseCurrencyAsset.languageCode = 'EN'
	WHERE
		PoloniexTradeTransactions.FK_AccountID = $liuAccountID
	ORDER BY
		PoloniexTradeTransactions.transactionTime", $GLOBALS['debugCoreFunctionality']);	
			}
			
			$responseObject['importedTransactions']							= true;
		}
		catch (PDOException $e) 
		{
			$cryptoTransaction 												= null;	
			$responseObject['importedTransactions']							= false;
			
			errorLog($e -> getMessage(), $GLOBALS['criticalErrors']);
		
			die();
		}
		
		return $responseObject;
	}
	
/*
	function createCommonTransactionsForGeminiTradeTransactions($liuAccountID, $userEncryptionKey, $globalCurrentDate, $sid, $dbh)
	{
		errorLog("createCommonTransactionsForGeminiTradeTransactions($liuAccountID, $userEncryptionKey, $globalCurrentDate, $sid");
		
		$responseObject														= array();
		$responseObject['createCommonTransactionsForGeminiTradeTransactions']	= false;
		
		$transactionSourceID												= 40;
		$transactionStatusID												= 1;
		$transactionStatusLabel												= "complete";
		$transactionSourceLabel												= "Gemini";
		
		try
		{		
			$getGeminiTransactionRecords									= $dbh -> prepare("SELECT
		GeminiTradeTransactions.GeminiTransactionRecordID AS transactionID,
		GeminiTradeTransactions.FK_ExchangeTileID,
		GeminiTradeTransactions.FK_GlobalTransactionRecordID AS FK_GlobalTransactionIdentificationRecordID,
		GeminiTradeTransactions.FK_AccountID AS authorID,
		GeminiTradeTransactions.FK_AccountID AS accountID,
		GeminiTradeTransactions.FK_TransactionTypeID AS transactionTypeID,
		GeminiTradeTransactions.FK_BaseCurrencyTypeID AS baseCurrencyID,		
		baseCurrencyAsset.assetTypeLabel AS baseCurrencyName,
		2 AS quoteSpotPriceCurrencyID,
		'USD' AS quoteSpotPriceCurrencyName,
		GeminiTradeTransactions.creationDate,
		GeminiTradeTransactions.transactionTime AS transactionDate,
		UNIX_TIMESTAMP(GeminiTradeTransactions.transactionTime) AS transactionTimestamp,
		AES_DECRYPT(GeminiTradeTransactions.encryptedTxIDValue, UNHEX(SHA2(:userEncryptionKey,512))) AS vendorTransactionID,
		ABS(GeminiTradeTransactions.volBaseCurrency) AS btcQuantityTransacted,
		ABS(GeminiTradeTransactions.usdAmount) AS usdQuantityTransacted,
		GeminiTradeTransactions.spotPriceAtTimeOfTransaction AS spotPriceAtTimeOfTransaction,
		GeminiTradeTransactions.btcPriceAtTimeOfTransaction AS btcPriceAtTimeOfTransaction,
		ABS(GeminiTradeTransactions.usdAmount) + ABS(GeminiTradeTransactions.feeAmountInUSD) AS usdTransactionAmountWithFees,
		GeminiTradeTransactions.feeAmountInUSD AS networkTransactionFeeAmount,
		ABS(GeminiTradeTransactions.feeAmountInUSD) AS usdFeeAmount,
		ABS(GeminiTradeTransactions.usdAmount) - ABS(GeminiTradeTransactions.feeAmountInUSD) AS transactionAmountMinusFeeInUSD,
		ABS(GeminiTradeTransactions.usdAmount) + ABS(GeminiTradeTransactions.feeAmountInUSD) AS transactionAmountPlusFeeInUSD,
		'' AS  providerNotes,
		GeminiTradeTransactions.isDebit,
		GeminiTradeTransactions.FK_BaseCurrencyWalletID AS FK_SourceAddressID,
		GeminiTradeTransactions.FK_QuoteCurrencyWalletID AS FK_DestinationAddressID,
		TransactionTypes.displayTransactionTypeLabel,
		TransactionTypes.transactionTypeLabel
	FROM
		GeminiTradeTransactions
		INNER JOIN TransactionTypes ON GeminiTradeTransactions.FK_TransactionTypeID = TransactionTypes.transactionTypeID AND TransactionTypes.languageCode = 'EN'
		INNER JOIN AssetTypes baseCurrencyAsset ON GeminiTradeTransactions.FK_BaseCurrencyTypeID = baseCurrencyAsset.assetTypeID AND baseCurrencyAsset.languageCode = 'EN'
	WHERE
		GeminiTradeTransactions.FK_AccountID = :accountID
	ORDER BY
		GeminiTradeTransactions.transactionTime");
	
			$getGeminiTransactionRecords -> bindValue(':accountID', $liuAccountID);
			$getGeminiTransactionRecords -> bindValue(':userEncryptionKey', $userEncryptionKey);
		
			if ($getGeminiTransactionRecords -> execute() && $getGeminiTransactionRecords -> rowCount() > 0)
			{
				errorLog("began get Gemini crypto transaction records ".$getGeminiTransactionRecords -> rowCount() > 0);
				
				while ($row = $getGeminiTransactionRecords -> fetchObject())
				{		
					$transactionID											= $row -> transactionID;
					$exchangeTileID											= $row -> FK_ExchangeTileID;
					$globalTransactionIdentificationRecordID				= $row -> FK_GlobalTransactionIdentificationRecordID;
					$accountID												= $row -> accountID;	
					$authorID												= $row -> authorID;
						
					$transactionTypeID										= $row -> transactionTypeID;
					$baseCurrencyID											= $row -> baseCurrencyID; // was assetTypeID - done
					$baseCurrencyName										= $row -> baseCurrencyName; // assetTypeName - not needed
							
					$quoteSpotPriceCurrencyID								= $row -> quoteSpotPriceCurrencyID; // was spotPriceCurrencyTypeID - done, needs verification
					$quoteSpotPriceCurrencyName								= $row -> quoteSpotPriceCurrencyName; // was spotPriceCurrencyType	
						
							
					$amount													= $row -> btcQuantityTransacted;
					$fee													= $row -> networkTransactionFeeAmount;
					$baseToUSDCurrencySpotPrice								= $row -> spotPriceAtTimeOfTransaction;
					$btcSpotPriceAtTimeOfTransaction						= $row -> btcPriceAtTimeOfTransaction;
					$creationDate											= $row -> creationDate;
					$transactionDate										= $row -> transactionDate;
					$transactionTimestamp									= $row -> transactionTimestamp;
					$vendorTransactionID									= $row -> vendorTransactionID;	
					$transactionAmountInUSD									= $row -> usdQuantityTransacted;
					$transactionAmountMinusFeeInUSD							= $row -> transactionAmountMinusFeeInUSD;
					$transactionAmountPlusFeeInUSD							= $row -> transactionAmountPlusFeeInUSD;
					$feeAmountInUSD											= $row -> usdFeeAmount;
					$usdTransactionAmountWithFees							= $row -> usdTransactionAmountWithFees;
					$providerNotes											= $row -> providerNotes;
					$transactionTypeLabel									= $row -> transactionTypeLabel;
					$displayTransactionTypeLabel							= $row -> displayTransactionTypeLabel;
					$isDebit												= $row -> isDebit;
						
					$sourceWalletID											= $row -> FK_SourceAddressID;
					$destinationWalletID									= $row -> FK_DestinationAddressID;
						
					$responseObject['processingTransaction'][]				= $vendorTransactionID;
					
					$getNativeAndCommonTransactionRecordIDsResult			= getNativeAndCommonTransactionRecordIDsForGlobalTransactionIdentificationRecordID($accountID, $baseCurrencyID, $vendorTransactionID, $transactionSourceID, $globalTransactionIdentificationRecordID, $globalCurrentDate, $sid, $dbh);
					
					errorLog("commonTransactionID: ". $getNativeAndCommonTransactionRecordIDsResult['commonTransactionRecordID']);
			
					$commonTransactionID									= $getNativeAndCommonTransactionRecordIDsResult['commonTransactionRecordID'];
			
					if (empty($commonTransactionID))
					{
						$unspentTransactionTotal							= 0;
						$unfundedSpendTotal									= 0;
						
						if ($isDebit == 0)
						{
							$unspentTransactionTotal  						= $amount;  // shouldn't this be the amount minus the fee amount
						}
						else if ($isDebit == 1)
						{
							$unfundedSpendTotal								= $amount; 	// shouldn't this be the amount minus the fee amount
						}	
						
						$sourceWallet										= new CompleteCryptoWallet();
						$destinationWallet									= new CompleteCryptoWallet();
				
						$sourceWalletResponseObject							= $sourceWallet -> instantiateWalletUsingCryptoWalletRecordID($accountID, $sourceWalletID, $userEncryptionKey, $dbh);
				
						if ($sourceWalletResponseObject['instantiatedRecord'] == false)
						{
							errorLog("Could not instantiate source Complete Crypto Wallet record $accountID");
						}
						
						$destinationWalletResponseObject					= $destinationWallet -> instantiateWalletUsingCryptoWalletRecordID($accountID, $destinationWalletID, $userEncryptionKey, $dbh);
				
						if ($destinationWalletResponseObject['instantiatedRecord'] == false)
						{
							errorLog("Could not instantiate destination Complete Crypto Wallet record $accountID, $destinationWalletID");
						}
						
						errorLog($vendorTransactionID."<BR>");
					
						$cryptoTransaction									= new CryptoTransaction();
					
						$cryptoTransaction -> setData(0, $accountID, $authorID, $exchangeTileID, $globalTransactionIdentificationRecordID, $transactionTypeID, $transactionTypeLabel, $transactionStatusID, $transactionStatusLabel, $transactionSourceID, $transactionSourceLabel, $baseCurrencyID, $baseCurrencyName, $quoteSpotPriceCurrencyID, $quoteSpotPriceCurrencyName, $sourceWalletID, $destinationWalletID, $creationDate, $transactionDate, $transactionTimestamp, $transactionID, $vendorTransactionID, $amount, $transactionAmountInUSD, $baseToUSDCurrencySpotPrice, $btcSpotPriceAtTimeOfTransaction, $transactionAmountMinusFeeInUSD, $fee, $feeAmountInUSD, $unspentTransactionTotal, $providerNotes, $isDebit, $sid);
					
						$writeToDatabaseResponse							= $cryptoTransaction -> writeToDatabase($userEncryptionKey, $dbh);
						
						if ($writeToDatabaseResponse['wroteToDatabase'] == true)
						{
							$transactionID									= $cryptoTransaction -> getTransactionID();
							
							error_log("wrote transaction $transactionID for $liuAccountID, $liuAccountID, $globalTransactionIdentificationRecordID, $transactionTypeID, $transactionTypeLabel, $transactionStatusID, $transactionStatusLabel, $transactionSourceID, $transactionSourceLabel, $baseCurrencyID, $baseCurrencyName, $quoteSpotPriceCurrencyID, $quoteSpotPriceCurrencyName, $sourceWalletID, $destinationWalletID, $creationDate, $transactionDate, $transactionTimestamp, $transactionID, $vendorTransactionID, $amount, $transactionAmountInUSD, $baseToUSDCurrencySpotPrice, $btcSpotPriceAtTimeOfTransaction, $transactionAmountMinusFeeInUSD, $fee, $feeAmountInUSD, $unspentTransactionTotal, $providerNotes, $isDebit, $sid");
						}
						else
						{
							error_log("could not create transaction for $liuAccountID, $liuAccountID, $globalTransactionIdentificationRecordID, $transactionTypeID, $transactionTypeLabel, $transactionStatusID, $transactionStatusLabel, $transactionSourceID, $transactionSourceLabel, $baseCurrencyID, $baseCurrencyName, $quoteSpotPriceCurrencyID, $quoteSpotPriceCurrencyName, $sourceWalletID, $destinationWalletID, $creationDate, $transactionDate, $transactionTimestamp, $transactionID, $vendorTransactionID, $amount, $transactionAmountInUSD, $baseToUSDCurrencySpotPrice, $btcSpotPriceAtTimeOfTransaction, $transactionAmountMinusFeeInUSD, $fee, $feeAmountInUSD, $unspentTransactionTotal, $providerNotes, $isDebit, $sid");	
						}
					}
					else
					{
						error_log("found transaction $commonTransactionID for $liuAccountID, $liuAccountID, $globalTransactionIdentificationRecordID, $transactionTypeID, $transactionTypeLabel, $transactionStatusID, $transactionStatusLabel, $transactionSourceID, $transactionSourceLabel, $baseCurrencyID, $baseCurrencyName, $quoteSpotPriceCurrencyID, $quoteSpotPriceCurrencyName, $sourceWalletID, $destinationWalletID, $creationDate, $transactionDate, $transactionTimestamp, $transactionID, $vendorTransactionID, $amount, $transactionAmountInUSD, $baseToUSDCurrencySpotPrice, $btcSpotPriceAtTimeOfTransaction, $transactionAmountMinusFeeInUSD, $fee, $feeAmountInUSD, $unspentTransactionTotal, $providerNotes, $isDebit, $sid");	
					}
				}
			}
			else
			{
				errorLog("SELECT
		GeminiTradeTransactions.GeminiTransactionRecordID AS transactionID,
		GeminiTradeTransactions.FK_ExchangeTileID,
		GeminiTradeTransactions.FK_GlobalTransactionRecordID AS FK_GlobalTransactionIdentificationRecordID,
		GeminiTradeTransactions.FK_AccountID AS authorID,
		GeminiTradeTransactions.FK_AccountID AS accountID,
		GeminiTradeTransactions.FK_TransactionTypeID AS transactionTypeID,
		GeminiTradeTransactions.FK_BaseCurrencyTypeID AS baseCurrencyID,		
		baseCurrencyAsset.assetTypeLabel AS baseCurrencyName,
		2 AS quoteSpotPriceCurrencyID,
		'USD' AS quoteSpotPriceCurrencyName,
		GeminiTradeTransactions.creationDate,
		GeminiTradeTransactions.transactionTime AS transactionDate,
		GeminiTradeTransactions.transactionTimestamp,
		AES_DECRYPT(GeminiTradeTransactions.encryptedTxIDValue, UNHEX(SHA2('$userEncryptionKey',512))) AS vendorTransactionID,
		ABS(GeminiTradeTransactions.volBaseCurrency) AS btcQuantityTransacted,
		ABS(GeminiTradeTransactions.usdAmount) AS usdQuantityTransacted,
		GeminiTradeTransactions.spotPriceAtTimeOfTransaction AS spotPriceAtTimeOfTransaction,
		GeminiTradeTransactions.btcPriceAtTimeOfTransaction AS btcPriceAtTimeOfTransaction,
		ABS(GeminiTradeTransactions.usdAmount) + ABS(GeminiTradeTransactions.feeAmountInUSD) AS usdTransactionAmountWithFees,
		GeminiTradeTransactions.feeAmountInUSD AS networkTransactionFeeAmount,
		ABS(GeminiTradeTransactions.feeAmountInUSD) AS usdFeeAmount,
		ABS(GeminiTradeTransactions.usdAmount) - ABS(GeminiTradeTransactions.feeAmountInUSD) AS transactionAmountMinusFeeInUSD,
		ABS(GeminiTradeTransactions.usdAmount) + ABS(GeminiTradeTransactions.feeAmountInUSD) AS transactionAmountPlusFeeInUSD,
		'' AS  providerNotes,
		GeminiTradeTransactions.isDebit,
		GeminiTradeTransactions.FK_BaseCurrencyWalletID AS FK_SourceAddressID,
		GeminiTradeTransactions.FK_QuoteCurrencyWalletID AS FK_DestinationAddressID,
		TransactionTypes.displayTransactionTypeLabel,
		TransactionTypes.transactionTypeLabel
	FROM
		GeminiTradeTransactions
		INNER JOIN TransactionTypes ON GeminiTradeTransactions.FK_TransactionTypeID = TransactionTypes.transactionTypeID AND TransactionTypes.languageCode = 'EN'
		INNER JOIN AssetTypes baseCurrencyAsset ON GeminiTradeTransactions.FK_BaseCurrencyTypeID = baseCurrencyAsset.assetTypeID AND baseCurrencyAsset.languageCode = 'EN'
	WHERE
		GeminiTradeTransactions.FK_AccountID = $liuAccountID
	ORDER BY
		GeminiTradeTransactions.transactionTime");	
			}
			
			$responseObject['importedTransactions']							= true;
		}
		catch (PDOException $e) 
		{
			$cryptoTransaction 												= null;	
			$responseObject['importedTransactions']							= false;
			
			errorLog($e -> getMessage());
		
			die();
		}
		
		return $responseObject;
	}
*/

	function createCommonTransactionsForGeminiTradeTransactions($liuAccountID, $userEncryptionKey, $globalCurrentDate, $sid, $dbh)
	{
		errorLog("createCommonTransactionsForGeminiTradeTransactions($liuAccountID, $userEncryptionKey, $globalCurrentDate, $sid");
		
		$responseObject														= array();
		$responseObject['createCommonTransactionsForGeminiTradeTransactions']	= false;
		
		$transactionSourceID												= 40;
		$transactionStatusID												= 1;
		$transactionStatusLabel												= "complete";
		$transactionSourceLabel												= "Gemini";
		
		try
		{		
			$getGeminiTransactionRecords									= $dbh -> prepare("SELECT
		GeminiTradeTransactions.GeminiTransactionRecordID AS transactionID,
		GeminiTradeTransactions.FK_ExchangeTileID,
		GeminiTradeTransactions.FK_GlobalTransactionRecordID AS FK_GlobalTransactionIdentificationRecordID,
		GeminiTradeTransactions.FK_AccountID AS authorID,
		GeminiTradeTransactions.FK_AccountID AS accountID,
		GeminiTradeTransactions.FK_TransactionTypeID AS transactionTypeID,
		GeminiTradeTransactions.FK_BaseCurrencyTypeID AS baseCurrencyID,		
		baseCurrencyAsset.assetTypeLabel AS baseCurrencyName,
		2 AS quoteSpotPriceCurrencyID,
		'USD' AS quoteSpotPriceCurrencyName,
		GeminiTradeTransactions.creationDate,
		GeminiTradeTransactions.transactionTime AS transactionDate,
		UNIX_TIMESTAMP(GeminiTradeTransactions.transactionTime) AS transactionTimestamp,
		AES_DECRYPT(GeminiTradeTransactions.encryptedTxIDValue, UNHEX(SHA2(:userEncryptionKey,512))) AS vendorTransactionID,
		ABS(GeminiTradeTransactions.volBaseCurrency) AS btcQuantityTransacted,
		ABS(GeminiTradeTransactions.usdAmount) AS usdQuantityTransacted,
		GeminiTradeTransactions.spotPriceAtTimeOfTransaction AS spotPriceAtTimeOfTransaction,
		GeminiTradeTransactions.btcPriceAtTimeOfTransaction AS btcPriceAtTimeOfTransaction,
		ABS(GeminiTradeTransactions.usdAmount) + ABS(GeminiTradeTransactions.feeAmountInUSD) AS usdTransactionAmountWithFees,
		GeminiTradeTransactions.feeAmountInUSD AS networkTransactionFeeAmount,
		ABS(GeminiTradeTransactions.feeAmountInUSD) AS usdFeeAmount,
		ABS(GeminiTradeTransactions.usdAmount) - ABS(GeminiTradeTransactions.feeAmountInUSD) AS transactionAmountMinusFeeInUSD,
		ABS(GeminiTradeTransactions.usdAmount) + ABS(GeminiTradeTransactions.feeAmountInUSD) AS transactionAmountPlusFeeInUSD,
		'' AS  providerNotes,
		GeminiTradeTransactions.isDebit,
		GeminiTradeTransactions.FK_BaseCurrencyWalletID AS FK_SourceAddressID,
		GeminiTradeTransactions.FK_QuoteCurrencyWalletID AS FK_DestinationAddressID,
		TransactionTypes.displayTransactionTypeLabel,
		TransactionTypes.transactionTypeLabel
	FROM
		GeminiTradeTransactions
		INNER JOIN TransactionTypes ON GeminiTradeTransactions.FK_TransactionTypeID = TransactionTypes.transactionTypeID AND TransactionTypes.languageCode = 'EN'
		INNER JOIN AssetTypes baseCurrencyAsset ON GeminiTradeTransactions.FK_BaseCurrencyTypeID = baseCurrencyAsset.assetTypeID AND baseCurrencyAsset.languageCode = 'EN'
	WHERE
		GeminiTradeTransactions.FK_AccountID = :accountID
	ORDER BY
		GeminiTradeTransactions.transactionTime");
	
			$getGeminiTransactionRecords -> bindValue(':accountID', $liuAccountID);
			$getGeminiTransactionRecords -> bindValue(':userEncryptionKey', $userEncryptionKey);
		
			if ($getGeminiTransactionRecords -> execute() && $getGeminiTransactionRecords -> rowCount() > 0)
			{
				errorLog("began get Gemini crypto transaction records ".$getGeminiTransactionRecords -> rowCount() > 0);
				
				while ($row = $getGeminiTransactionRecords -> fetchObject())
				{		
					$transactionID											= $row -> transactionID;
					$exchangeTileID											= $row -> FK_ExchangeTileID;
					$globalTransactionIdentificationRecordID				= $row -> FK_GlobalTransactionIdentificationRecordID;
					$accountID												= $row -> accountID;	
					$authorID												= $row -> authorID;
						
					$transactionTypeID										= $row -> transactionTypeID;
					$baseCurrencyID											= $row -> baseCurrencyID; // was assetTypeID - done
					$baseCurrencyName										= $row -> baseCurrencyName; // assetTypeName - not needed
							
					$quoteSpotPriceCurrencyID								= $row -> quoteSpotPriceCurrencyID; // was spotPriceCurrencyTypeID - done, needs verification
					$quoteSpotPriceCurrencyName								= $row -> quoteSpotPriceCurrencyName; // was spotPriceCurrencyType	
						
							
					$amount													= $row -> btcQuantityTransacted;
					$fee													= $row -> networkTransactionFeeAmount;
					$baseToUSDCurrencySpotPrice								= $row -> spotPriceAtTimeOfTransaction;
					$btcSpotPriceAtTimeOfTransaction						= $row -> btcPriceAtTimeOfTransaction;
					$creationDate											= $row -> creationDate;
					$transactionDate										= $row -> transactionDate;
					$transactionTimestamp									= $row -> transactionTimestamp;
					$vendorTransactionID									= $row -> vendorTransactionID;	
					$transactionAmountInUSD									= $row -> usdQuantityTransacted;
					$transactionAmountMinusFeeInUSD							= $row -> transactionAmountMinusFeeInUSD;
					$transactionAmountPlusFeeInUSD							= $row -> transactionAmountPlusFeeInUSD;
					$feeAmountInUSD											= $row -> usdFeeAmount;
					$usdTransactionAmountWithFees							= $row -> usdTransactionAmountWithFees;
					$providerNotes											= $row -> providerNotes;
					$transactionTypeLabel									= $row -> transactionTypeLabel;
					$displayTransactionTypeLabel							= $row -> displayTransactionTypeLabel;
					$isDebit												= $row -> isDebit;
						
					$sourceWalletID											= $row -> FK_SourceAddressID;
					$destinationWalletID									= $row -> FK_DestinationAddressID;
						
					$responseObject['processingTransaction'][]				= $vendorTransactionID;
					
					$getNativeAndCommonTransactionRecordIDsResult			= getNativeAndCommonTransactionRecordIDsForGlobalTransactionIdentificationRecordID($accountID, $baseCurrencyID, $vendorTransactionID, $transactionSourceID, $globalTransactionIdentificationRecordID, $globalCurrentDate, $sid, $dbh);
					
					errorLog("commonTransactionID: ". $getNativeAndCommonTransactionRecordIDsResult['commonTransactionRecordID']);
			
					$commonTransactionID									= $getNativeAndCommonTransactionRecordIDsResult['commonTransactionRecordID'];
			
					if (empty($commonTransactionID))
					{
						$unspentTransactionTotal							= 0;
						$unfundedSpendTotal									= 0;
						
						if ($isDebit == 0)
						{
							$unspentTransactionTotal  						= $amount;  // shouldn't this be the amount minus the fee amount
						}
						else if ($isDebit == 1)
						{
							$unfundedSpendTotal								= $amount; 	// shouldn't this be the amount minus the fee amount
						}	
						
						$sourceWallet										= new CompleteCryptoWallet();
						$destinationWallet									= new CompleteCryptoWallet();
				
						$sourceWalletResponseObject							= $sourceWallet -> instantiateWalletUsingCryptoWalletRecordID($accountID, $sourceWalletID, $userEncryptionKey, $dbh);
				
						if ($sourceWalletResponseObject['instantiatedRecord'] == false)
						{
							errorLog("Could not instantiate source Complete Crypto Wallet record $accountID");
						}
						
						$destinationWalletResponseObject					= $destinationWallet -> instantiateWalletUsingCryptoWalletRecordID($accountID, $destinationWalletID, $userEncryptionKey, $dbh);
				
						if ($destinationWalletResponseObject['instantiatedRecord'] == false)
						{
							errorLog("Could not instantiate destination Complete Crypto Wallet record $accountID, $destinationWalletID");
						}
						
						errorLog($vendorTransactionID."<BR>");
					
						$cryptoTransaction									= new CryptoTransaction();
					
						$cryptoTransaction -> setData(0, $accountID, $authorID, $exchangeTileID, $globalTransactionIdentificationRecordID, $transactionTypeID, $transactionTypeLabel, $transactionStatusID, $transactionStatusLabel, $transactionSourceID, $transactionSourceLabel, $baseCurrencyID, $baseCurrencyName, $quoteSpotPriceCurrencyID, $quoteSpotPriceCurrencyName, $sourceWalletID, $destinationWalletID, $creationDate, $transactionDate, $transactionTimestamp, $transactionID, $vendorTransactionID, $amount, $transactionAmountInUSD, $baseToUSDCurrencySpotPrice, $btcSpotPriceAtTimeOfTransaction, $transactionAmountMinusFeeInUSD, $fee, $feeAmountInUSD, $unspentTransactionTotal, $providerNotes, $isDebit, $sid);
					
						$writeToDatabaseResponse							= $cryptoTransaction -> writeToDatabase($userEncryptionKey, $dbh);
						
						if ($writeToDatabaseResponse['wroteToDatabase'] == true)
						{
							$transactionID									= $cryptoTransaction -> getTransactionID();
							
							errorLog("wrote transaction $transactionID for $liuAccountID, $liuAccountID, $globalTransactionIdentificationRecordID, $transactionTypeID, $transactionTypeLabel, $transactionStatusID, $transactionStatusLabel, $transactionSourceID, $transactionSourceLabel, $baseCurrencyID, $baseCurrencyName, $quoteSpotPriceCurrencyID, $quoteSpotPriceCurrencyName, $sourceWalletID, $destinationWalletID, $creationDate, $transactionDate, $transactionTimestamp, $transactionID, $vendorTransactionID, $amount, $transactionAmountInUSD, $baseToUSDCurrencySpotPrice, $btcSpotPriceAtTimeOfTransaction, $transactionAmountMinusFeeInUSD, $fee, $feeAmountInUSD, $unspentTransactionTotal, $providerNotes, $isDebit, $sid", $GLOBALS['debugCoreFunctionality']);
						}
						else
						{
							errorLog("could not create transaction for $liuAccountID, $liuAccountID, $globalTransactionIdentificationRecordID, $transactionTypeID, $transactionTypeLabel, $transactionStatusID, $transactionStatusLabel, $transactionSourceID, $transactionSourceLabel, $baseCurrencyID, $baseCurrencyName, $quoteSpotPriceCurrencyID, $quoteSpotPriceCurrencyName, $sourceWalletID, $destinationWalletID, $creationDate, $transactionDate, $transactionTimestamp, $transactionID, $vendorTransactionID, $amount, $transactionAmountInUSD, $baseToUSDCurrencySpotPrice, $btcSpotPriceAtTimeOfTransaction, $transactionAmountMinusFeeInUSD, $fee, $feeAmountInUSD, $unspentTransactionTotal, $providerNotes, $isDebit, $sid", $GLOBALS['debugCoreFunctionality']);	
						}
					}
					else
					{
						errorLog("found transaction $commonTransactionID for $liuAccountID, $liuAccountID, $globalTransactionIdentificationRecordID, $transactionTypeID, $transactionTypeLabel, $transactionStatusID, $transactionStatusLabel, $transactionSourceID, $transactionSourceLabel, $baseCurrencyID, $baseCurrencyName, $quoteSpotPriceCurrencyID, $quoteSpotPriceCurrencyName, $sourceWalletID, $destinationWalletID, $creationDate, $transactionDate, $transactionTimestamp, $transactionID, $vendorTransactionID, $amount, $transactionAmountInUSD, $baseToUSDCurrencySpotPrice, $btcSpotPriceAtTimeOfTransaction, $transactionAmountMinusFeeInUSD, $fee, $feeAmountInUSD, $unspentTransactionTotal, $providerNotes, $isDebit, $sid", $GLOBALS['debugCoreFunctionality']);	
					}
				}
			}
			else
			{
				errorLog("SELECT
		GeminiTradeTransactions.GeminiTransactionRecordID AS transactionID,
		GeminiTradeTransactions.FK_ExchangeTileID,
		GeminiTradeTransactions.FK_GlobalTransactionRecordID AS FK_GlobalTransactionIdentificationRecordID,
		GeminiTradeTransactions.FK_AccountID AS authorID,
		GeminiTradeTransactions.FK_AccountID AS accountID,
		GeminiTradeTransactions.FK_TransactionTypeID AS transactionTypeID,
		GeminiTradeTransactions.FK_BaseCurrencyTypeID AS baseCurrencyID,		
		baseCurrencyAsset.assetTypeLabel AS baseCurrencyName,
		2 AS quoteSpotPriceCurrencyID,
		'USD' AS quoteSpotPriceCurrencyName,
		GeminiTradeTransactions.creationDate,
		GeminiTradeTransactions.transactionTime AS transactionDate,
		GeminiTradeTransactions.transactionTimestamp,
		AES_DECRYPT(GeminiTradeTransactions.encryptedTxIDValue, UNHEX(SHA2('$userEncryptionKey',512))) AS vendorTransactionID,
		ABS(GeminiTradeTransactions.volBaseCurrency) AS btcQuantityTransacted,
		ABS(GeminiTradeTransactions.usdAmount) AS usdQuantityTransacted,
		GeminiTradeTransactions.spotPriceAtTimeOfTransaction AS spotPriceAtTimeOfTransaction,
		GeminiTradeTransactions.btcPriceAtTimeOfTransaction AS btcPriceAtTimeOfTransaction,
		ABS(GeminiTradeTransactions.usdAmount) + ABS(GeminiTradeTransactions.feeAmountInUSD) AS usdTransactionAmountWithFees,
		GeminiTradeTransactions.feeAmountInUSD AS networkTransactionFeeAmount,
		ABS(GeminiTradeTransactions.feeAmountInUSD) AS usdFeeAmount,
		ABS(GeminiTradeTransactions.usdAmount) - ABS(GeminiTradeTransactions.feeAmountInUSD) AS transactionAmountMinusFeeInUSD,
		ABS(GeminiTradeTransactions.usdAmount) + ABS(GeminiTradeTransactions.feeAmountInUSD) AS transactionAmountPlusFeeInUSD,
		'' AS  providerNotes,
		GeminiTradeTransactions.isDebit,
		GeminiTradeTransactions.FK_BaseCurrencyWalletID AS FK_SourceAddressID,
		GeminiTradeTransactions.FK_QuoteCurrencyWalletID AS FK_DestinationAddressID,
		TransactionTypes.displayTransactionTypeLabel,
		TransactionTypes.transactionTypeLabel
	FROM
		GeminiTradeTransactions
		INNER JOIN TransactionTypes ON GeminiTradeTransactions.FK_TransactionTypeID = TransactionTypes.transactionTypeID AND TransactionTypes.languageCode = 'EN'
		INNER JOIN AssetTypes baseCurrencyAsset ON GeminiTradeTransactions.FK_BaseCurrencyTypeID = baseCurrencyAsset.assetTypeID AND baseCurrencyAsset.languageCode = 'EN'
	WHERE
		GeminiTradeTransactions.FK_AccountID = $liuAccountID
	ORDER BY
		GeminiTradeTransactions.transactionTime", $GLOBALS['debugCoreFunctionality']);	
			}
			
			$responseObject['importedTransactions']							= true;
		}
		catch (PDOException $e) 
		{
			$cryptoTransaction 												= null;	
			$responseObject['importedTransactions']							= false;
			
			errorLog($e -> getMessage(), $GLOBALS['criticalErrors']);
		
			die();
		}
		
		return $responseObject;
	}

	
	
	// END ACCOUNT METHOD SPECIFIC CALCULATIONS USING GENERIC TRANSACTION TABLE
	
	// DAILY PORTFOLIO AND TAX LIABILITY CALCULATIONS 
	
	function performDailyPortfolioAndTaxLiabilityCalculations($userObject, $globalCurrentDate, $sid, $dbh)
	{
		$transactionSourcesForUser													= getAllTransactionSourcesForUser($userObject -> getUserAccountID(), $dbh);
		
		$assetTypesForUser															= getAllAssetTypesForUser($userObject -> getUserAccountID(), $dbh);
		
		$oldestTransactionDateObject												= $userObject -> getOldestTransactionDate() -> modify('-1 day');
		$oldestTransactionDate														= $oldestTransactionDateObject -> format('Y-m-d');
		
		foreach ($transactionSourcesForUser as $transactionSourceID) 
		{
			foreach ($assetTypesForUser as $assetTypeID => $assetTypeLabel)
			{
				$responseObject["calculate".$assetTypeLabel]						= getCryptoDailyPortfolioBalanceForUserAccountAssetAndTransactionSourceID($userObject -> getUserAccountID(), $assetTypeID, $assetTypeLabel, 2, "USD", $transactionSourceID, $oldestTransactionDate, $globalCurrentDate, $sid, $dbh);	
			}
			
			$responseObject['calculateDailyPortfolioBalance_'.$transactionSourceID]	= setCryptoDailyPortfolioBalanceForUserAccountTransactionSourceAndAllAssets($userObject -> getUserAccountID(), $oldestTransactionDate, $transactionSourceID, $globalCurrentDate, $sid, $dbh);
		}
		
		$responseObject['calculateDailyPortfolioBalance']							= setCryptoDailyPortfolioBalanceForUserAccountAllTransactionSourcesAndAllAssets($userObject -> getUserAccountID(), $oldestTransactionDate, $globalCurrentDate, $sid, $dbh);	
		
		// @task - 20190209-7606 - should this call really be to the first day of each of these years instead?
		
		$responseObject																= performFIFOTaxCalculationsForUserAccountAndTaxYear($userObject, $oldestTransactionDate, $responseObject, 2017, $globalCurrentDate, $sid, $dbh);
		
		$responseObject																= performFIFOTaxCalculationsForUserAccountAndTaxYear($userObject, $oldestTransactionDate, $responseObject, 2018, $globalCurrentDate, $sid, $dbh);
		
		$responseObject																= performFIFOTaxCalculationsForUserAccountAndTaxYear($userObject, $oldestTransactionDate, $responseObject, 2019, $globalCurrentDate, $sid, $dbh);
		
		return $responseObject;
	}
	
	function performFIFOTaxCalculationsForUserAccountAndTaxYear($userObject, $oldestTransactionDate, $responseObject, $taxYear, $globalCurrentDate, $sid, $dbh)
	{
		$taxFormInstance														= new TaxFormInstance();
		$taxFormInstance -> setData($userObject -> getUserAccountID(), $userObject -> getUserAccountID(), $globalCurrentDate, "zDe76h654Gdsok93gtq99jneht0L", "8949", 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, $sid, 0, 3, $taxYear);
	
		$responseObject['create']													= $taxFormInstance -> createTaxFormEvent($dbh);
		$responseObject['generate']													= $taxFormInstance -> generateAllTaxFormDetailRecordsForUser($globalCurrentDate, $sid, $dbh);
	
		$responseObject['generateShortTermWorksheetRecords'] 						= $taxFormInstance -> generateWorksheetRecordsForTaxEventForm(1, $globalCurrentDate, $sid, $dbh);
		$responseObject['generateLongTermWorksheetRecords'] 						= $taxFormInstance -> generateWorksheetRecordsForTaxEventForm(0, $globalCurrentDate, $sid, $dbh);
	
		$accountingMethodForRegionProfile											= new AccountingMethodForRegionProfile();
			
		$accountingObjectInstantiationResult										= $accountingMethodForRegionProfile -> instantiateByCountryCode($userObject -> getAddressObject() -> getCountryCode(), $dbh);
		
		if ($accountingObjectInstantiationResult['instantiatedAccountingMethod'] == true)
		{
			$shortTermTaxPercent													= 0;
			$longTermTaxPercent														= 0;
		
			if ($accountingMethodForRegionProfile -> getGainTaxationMethod() == 1)
			{
				$shortTermTaxPercent												= 0;
				$longTermTaxPercent													= $accountingMethodForRegionProfile -> getLongTermGainTaxPercentage();	
			}
			else if ($accountingMethodForRegionProfile -> getGainTaxationMethod() == 2)
			{
				$shortTermTaxPercent												= $accountingMethodForRegionProfile -> getLongTermGainTaxPercentage();
				$longTermTaxPercent													= 0;	
			}
			else if ($accountingMethodForRegionProfile -> getGainTaxationMethod() == 3)
			{
				$shortTermTaxPercent												= $accountingMethodForRegionProfile -> getShortTermGainTaxPercentage();
				$longTermTaxPercent													= $accountingMethodForRegionProfile -> getLongTermGainTaxPercentage();	
			}
			else if ($accountingMethodForRegionProfile -> getGainTaxationMethod() == 4)
			{
				$shortTermTaxPercent												= $accountingMethodForRegionProfile -> getFlatTaxPercentage();
				$longTermTaxPercent													= $accountingMethodForRegionProfile -> getFlatTaxPercentage();	
			}
		
			$shortTermTaxPercent													= $shortTermTaxPercent / 100;
			$longTermTaxPercent														= $longTermTaxPercent / 100;
		
			$maxAllowedLoss															= $accountingMethodForRegionProfile -> getMaximumLossDeductionPerYear();
		
			// get most recent tax event form ID
			$taxFormInstanceData													= getMostRecentTaxFormInstanceEventDataForUserAccountForYear($userObject -> getUserAccountID(), 2018, 3, $sid, $dbh);
		
			if ($taxFormInstanceData['retrievedTaxFormInstanceEventData'] == true)
			{
				// get date from that form
				$lastTaxFormInstanceID												= $taxFormInstanceData['taxFormEventID'];
				$lastTaxFormInstanceCreationDate									= $taxFormInstanceData['creationDate'];
				// for that form ID, go from first date to newest date and get the total gain or loss for the day	
				
				errorLog("lastTaxFormInstanceID $lastTaxFormInstanceID lastTaxFormInstanceCreationDate $lastTaxFormInstanceCreationDate");
				
				$lastTaxFormInstanceCreationDateObject								= new DateTime($lastTaxFormInstanceCreationDate);
				
				$userObject -> setLastPortfolioAsOfDate(date_format($lastTaxFormInstanceCreationDateObject, "Y-m-d"), $globalCurrentDate, $dbh);
				
				// @task HERE - all liability values generated are 0
				// @task HERE - return final short gain, long gain, short loss, long loss, total loss, and adjusted loss
				
				if ($oldestTransactionDate < new DateTime('2017-01-01'))
				{
					setCryptoDailyGainOrLossForUserAccountAndAllAssets($userObject -> getUserAccountID(), $oldestTransactionDate, '2016-12-31', $lastTaxFormInstanceID, $shortTermTaxPercent, $longTermTaxPercent, $maxAllowedLoss, $globalCurrentDate, $sid, $dbh);	
				}
				
				setCryptoDailyGainOrLossForUserAccountAndAllAssets($userObject -> getUserAccountID(), '2017-01-01', '2017-12-31', $lastTaxFormInstanceID, $shortTermTaxPercent, $longTermTaxPercent, $maxAllowedLoss, $globalCurrentDate, $sid, $dbh);
				setCryptoDailyGainOrLossForUserAccountAndAllAssets($userObject -> getUserAccountID(), '2018-01-01', date_format($lastTaxFormInstanceCreationDateObject, "Y-m-d"), $lastTaxFormInstanceID, $shortTermTaxPercent, $longTermTaxPercent, $maxAllowedLoss, $globalCurrentDate, $sid, $dbh);
			}	
			else
			{
				errorLog("retrievedTaxFormInstanceEventData is false");
			}
		}
		else
		{
			errorLog("instantiatedAccountingMethod is false: ".$accountingObjectInstantiationResult['resultMessage']);
		}
		
		return $responseObject;
	}
	
	// END DAILY PORTFOLIO AND TAX LIABILITY CALCULATIONS
	
	// COINBASE SPECIFIC FUNCTIONS
	
	function identifyCoinbaseTradeDirection($currencyType, $providerNotes)
	{
		$isDebit																= null;
			
		$currencyTypeAsFromWallet											= $currencyType." to";
		$currencyTypeAsToWallet												= $currencyType.".";
		
		if (strpos($providerNotes, $currencyTypeAsFromWallet) !== false) 
		{
			$isDebit							= true;
		}
		else if (strpos($providerNotes, $currencyTypeAsToWallet) !== false) 
		{
			$isDebit							= false;
		}
		
		return $isDebit;
	}
	
	// END COINBASE SPECIFIC FUNCTIONS
	
	// ACCOUNT CREATION AND MODIFICATION FUNCTIONS
	
	function createPasswordResetRequest($accountID, $emailAddress, $globalCurrentDate, $sid, $dbh)
	{
		$responseObject									= array();
		$responseObject['resetRecordCreated']			= false;
		$responseObject['requestEmail']					= $emailAddress;
		$responseObject['resultMessage']					= "";
		
		try
		{	
			$createPasswordResetHash						= $dbh->prepare("INSERT INTO PasswordResetHash
(
	FK_UserAccountID,
	requestDate,
	requestStatus,
	sid,
	encryptedEmailAddress,
	encryptedResetHash
)
VALUES
(
	:FK_UserAccountID,
	:requestDate,
	:requestStatus,
	:sid,
	AES_ENCRYPT(:encryptedEmailAddress, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	AES_ENCRYPT(:encryptedResetHash, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512)))
)");

			$resetHash								= md5(md5("password reset hash $accountID $emailAddress $globalCurrentDate $globalCurrentDate $sid").generateRandomString(15));

		
			$createPasswordResetHash -> bindValue(':FK_UserAccountID', $accountID);
			$createPasswordResetHash -> bindValue(':requestDate', $globalCurrentDate);
			$createPasswordResetHash -> bindValue(':requestStatus', 0);
			$createPasswordResetHash -> bindValue(':sid', $sid);
			$createPasswordResetHash -> bindValue(':encryptedEmailAddress', $emailAddress);
			$createPasswordResetHash -> bindValue(':encryptedResetHash', $resetHash);
		
			if ($createPasswordResetHash -> execute())
			{
				$responseObject								= sendPasswordRestEmail($emailAddress, $resetHash);
				
				$responseObject['resetRecordCreated']		= true;
				$responseObject['resultMessage']			= "A password reset verification email has been sent to $emailAddress";
			}
			else
			{
				$responseObject['resultMessage']			= "We were unable to send a password reset verification email to $emailAddress";
			}			
			
			$dbh 											= null;	
		}
	    catch (PDOException $e) 
	    {
	    	$responseObject['resultMessage']				= "Error: A database error has occurred.  We were unable to create a password reset request. ".$e->getMessage();	
			
			errorLog($e->getMessage());
	
			die();
		}
		
		return $responseObject;
	}
	
	function createSimpleUserAccount($firstName, $lastName, $emailAddress, $password, $passwordStrengthLabel, $isMiner, $invitationCode, $requireInvitationCode, $globalCurrentDate, $sid, $dbh)
	{
		$responseObject														= array();
		$responseObject['accountCreated']									= false;
	
		$isActive															= 0;
	
		$allowAccountCreation												= true;
	
		$bucketName															= md5("$sid create $passwordStrengthLabel bucket $isMiner name $invitationCode for $globalCurrentDate user $firstName, $lastName with email address $emailAddress and password $password".md5($invitationCode))."-".substr(md5("$invitationCode $globalCurrentDate $sid"), 10);
	
		$passwordStrengthValueID												= getEnumValuePasswordStrength($passwordStrengthLabel, $dbh);
	
		if ($requireInvitationCode == true)
		{
			$allowAccountCreation											= false;
			
			$invitationEmailAddressResult									= getEmailAddressForInvitationCode($invitationCode, $dbh);
		
			if ($invitationEmailAddressResult['foundInvitedEmailAddress'] == false)
			{
				$responseObject['resultMessage']								= "We were not able to find the invitation code you provided.  You can not create an account without an invitation code.";	
			}
			else
			{
				if (strcasecmp($invitationEmailAddressResult['emailAddress'], $emailAddress) == 0)
				{
					if ($invitationEmailAddressResult['activationStatus'] == 3)
					{
						$responseObject['resultMessage']						= "The invitation code you provided has been cancelled.";	
					}
					else if ($invitationEmailAddressResult['activationStatus'] == 2)
					{
						$responseObject['resultMessage']						= "The invitation code you provided has expired.";	
					}
					else if ($invitationEmailAddressResult['activationStatus'] == 1)
					{
						$responseObject['resultMessage']						= "The invitation code you provided has been used.";	
					}
					else if ($invitationEmailAddressResult['activationStatus'] == 0)
					{
						$allowAccountCreation								= true;	
						$isActive											= 1;	
					}
				}		
			}
		}
		
		errorLog("INSERT INTO UserAccounts
(
	encryptedFirstName,
	encryptedMiddleName,
	encryptedLastName,
	encryptedStreetAddress,
	encryptedStreetAddress2,
	encryptedCity,
	encryptedStateID,
	encryptedZip,
	encryptedPhoneNumber,
	encryptedEmailAddress,
	encryptedPassword,
	isMiner,
	creationDate,
	modificationDate,
	sid,
	FK_PlanTypeID,
	lastPaymentDate,
	FK_PasswordStrengthID,
	isActive,
	encryptedBucketName
)
VALUES
(
	AES_ENCRYPT('$firstName', UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	AES_ENCRYPT('', UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	AES_ENCRYPT('$lastName', UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	AES_ENCRYPT('', UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	AES_ENCRYPT('', UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	AES_ENCRYPT('', UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	AES_ENCRYPT(0, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	AES_ENCRYPT('', UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	AES_ENCRYPT('', UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	AES_ENCRYPT('$emailAddress', UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	AES_ENCRYPT('$password', UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	$isMiner,
	NOW(),
	NOW(),
	'$sid',
	1,
	NOW(),
	$passwordStrengthValueID,
	$isActive,
	AES_ENCRYPT('$bucketName', UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512)))
)");
		
		if ($allowAccountCreation == true)
		{
			try
			{	
				$createAccount												= $dbh->prepare("INSERT INTO UserAccounts
(
	encryptedFirstName,
	encryptedMiddleName,
	encryptedLastName,
	encryptedStreetAddress,
	encryptedStreetAddress2,
	encryptedCity,
	encryptedStateID,
	encryptedZip,
	encryptedPhoneNumber,
	encryptedEmailAddress,
	encryptedPassword,
	FK_PasswordStrengthID,
	isMiner,
	isActive,
	creationDate,
	modificationDate,
	sid,
	encryptedBucketName
)
VALUES
(
	AES_ENCRYPT(:firstName, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	AES_ENCRYPT(:middleName, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	AES_ENCRYPT(:lastName, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	AES_ENCRYPT(:address, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	AES_ENCRYPT(:address2, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	AES_ENCRYPT(:city, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	AES_ENCRYPT(:FK_StateID, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	AES_ENCRYPT(:zip, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	AES_ENCRYPT(:phoneNumber, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	AES_ENCRYPT(:emailAddress, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	AES_ENCRYPT(:password, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	:passwordStrengthID,
	:isMiner,
	:isActive,
	NOW(),
	NOW(),
	:sid,
	AES_ENCRYPT(:bucketName, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512)))
)");
			
				$insertValidationRequest										= $dbh->prepare("INSERT INTO AccountCreationValidationHash
(
	FK_UserAccountID,
	requestDate,
	requestStatus,
	sid,
	encryptedUserAccountID,
	encryptedEmailAddress,
	encryptedMobileNumber,
	encryptedValidationHash,
	encryptedSMSValidationCode,
	originalRequestDate
)
VALUES
(
	:FK_UserAccountID,
	:requestDate,
	:requestStatus,
	:sid,
	AES_ENCRYPT(:FK_UserAccountID, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	AES_ENCRYPT(:emailAddress, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	AES_ENCRYPT(:mobilePhone, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	AES_ENCRYPT(:validationHash, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	AES_ENCRYPT(:smsValidationCode, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	:requestDate
)");

				$markInvitationCodeUsed										= $dbh->prepare("UPDATE
	FriendInvites
SET
	FK_ActivationStatus = 1
WHERE
	generatedFriendHash = AES_ENCRYPT(:inviteCode, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512)))");
			
				$emailTestResult												= doesEmailAccountExist($emailAddress, $dbh);
			
				if ($emailTestResult['doesEmailAccountExist'] == true)
				{
					$responseObject['responseMessage']						= "An account exists for the email address you have supplied.  Please try another email address, or, if this is the correct email address, please try resetting the password.";
				}
				else
				{
					$createAccount -> bindValue(':firstName', $firstName);
					$createAccount -> bindValue(':middleName', "");
					$createAccount -> bindValue(':lastName', $lastName);
					$createAccount -> bindValue(':address', "");
					$createAccount -> bindValue(':address2', "");
					$createAccount -> bindValue(':city', "");
					$createAccount -> bindValue(':FK_StateID', "");
					$createAccount -> bindValue(':zip', "");
					$createAccount -> bindValue(':phoneNumber', "");
					$createAccount -> bindValue(':emailAddress', $emailAddress);
					$createAccount -> bindValue(':password', $password);
					$createAccount -> bindValue(':passwordStrengthID', $passwordStrengthValueID);
					$createAccount -> bindValue(':isMiner', $isMiner);
					$createAccount -> bindValue(':isActive', $isActive);
					$createAccount -> bindValue(':sid', $sid);
					$createAccount -> bindValue(':bucketName', $bucketName);
					
					if ($createAccount -> execute())
					{
						$newAccountID 										= $dbh -> lastInsertId();
						$returnValue											= $newAccountID;
						
						$responseObject['accountCreated']					= true;
						$responseObject['userAccountNumber']					= $newAccountID;
						
						if ($isActive == 1)
						{
							$responseObject['responseMessage']				= "Your account has been created and activated.";
							
							$accountData										= array();
							$authResult										= array();
	
							$authResult['authenticated']						= "true";
							$authResult['result']							= "You have successfully logged in";
							
							$userObject										= new UserInformationObject();
							$userObject	 -> instantiateUserObjectByUserAccountID($newAccountID, $dbh, $sid);	
						
							$_SESSION['requireTFA']							= 0;
							$_SESSION['tfaAuthenticated']					= 0;
							$_SESSION['loggedInUserID']						= $newAccountID;
							$_SESSION['serverRoot']							= $serverURLRoot;
							$_SESSION['isLoggedIn']							= true;
							
							$accountData['requiresTwoFactorAuthentication']	= $userObject -> getRequireTwoFactorAuthentication();
							$accountData['sessionID']						= $userObject -> getEncodedSid();
							$accountData['userAccountNumber']				= $userObject -> getUserAccountID();
							$accountData['name']								= $userObject -> getNameObject() -> getNameAsArray();
							
							$responseObject['AccountData']					= $accountData;		
							$responseObject['AuthResult']					= $authResult;
						}
						else
						{
							$responseObject['responseMessage']				= "Your account has been created.  A confirmation email will be sent to you.  Please click on the activation link in the email.  You will be directed to a validation page.  Please log in with the email address and password you supplied.";
							
							$validationHash									= md5(md5("no firstName no middleName no lastName $password $emailAddress $isMiner $globalCurrentDate $sid"));
							
							errorLog($validationHash);	
							
							$smsValidationCode								= generateRandomString(6);
							
							$insertValidationRequest -> bindValue(':FK_UserAccountID', $newAccountID);
							$insertValidationRequest -> bindValue(':validationHash', $validationHash);
							$insertValidationRequest -> bindValue(':smsValidationCode', $smsValidationCode);
							$insertValidationRequest -> bindValue(':emailAddress', $emailAddress);
							$insertValidationRequest -> bindValue(':mobilePhone', "");
							$insertValidationRequest -> bindValue(':requestDate', $globalCurrentDate);
							$insertValidationRequest -> bindValue(':requestStatus', 0);
							$insertValidationRequest -> bindValue(':sid', $sid);
								
							$insertValidationRequest -> execute();
						
							errorLog("INSERT INTO AccountCreationValidationHash
			(
				FK_UserAccountID,
				requestDate,
				requestStatus,
				sid,
				encryptedUserAccountID,
				encryptedEmailAddress,
				encryptedMobileNumber,
				encryptedValidationHash,
				encryptedSMSValidationCode,
				originalRequestDate
			)
			VALUES
			(
				$newAccountID,
				'$globalCurrentDate',
				0,
				'$sid',
				AES_ENCRYPT($newAccountID, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
				AES_ENCRYPT('$emailAddress', UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
				AES_ENCRYPT('', UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
				AES_ENCRYPT('$validationHash', UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
				AES_ENCRYPT('$smsValidationCode', UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
				'$globalCurrentDate'
			)");
						
							$responseObject['emailVerificationResult']		= sendAccountVerificationEmail($newAccountID, $emailAddress, $validationHash);
						}
						
						selectiveErrorLog("new account $newAccountID");
						
						$_SESSION['participantID'] 							= $newAccountID;
							
						if ($requireInvitationCode == true)
						{
							$markInvitationCodeUsed -> bindValue(':inviteCode', $invitationCode);
							
							$markInvitationCodeUsed -> execute();
						}
					}	
				}
				
				$dbh 														= null;	
			}
		    catch (PDOException $e) 
		    {
		    		$responseObject['responseMessage']	= "A database error has occurred.  Your account could not be created: ".$e->getMessage();	
				
				errorLog($e->getMessage());
			}	
		}
		
		errorLog(json_encode($responseObject));
		
		return $responseObject;
	}
	
	function createUserAccount($firstName, $middleName, $lastName, $addressStreet, $addressCity, $addressStateID, $addressZip, $phoneNumber, $emailAddress, $globalCurrentDate, $sid, $validationType, $dbh)
	{
		try
		{	
			$checkForExistingEmail		= $dbh->prepare("SELECT
		COUNT(userAccountID) AS numAccounts
	FROM
		UserAccounts
	WHERE
		UserAccounts.encryptedEmailAddress = AES_ENCRYPT(:emailAddress, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512)))");
			
			$createAccount				= $dbh->prepare("INSERT INTO UserAccounts
	(
		encryptedFirstName,
		encryptedMiddleName,
		encryptedLastName,
		encryptedStreetAddress,
		encryptedCity,
		encryptedStateID,
		encryptedZip,
		encryptedPhoneNumber,
		encryptedEmailAddress,
		creationDate,
		modificationDate,
		sid
	)
	VALUES
	(
		AES_ENCRYPT(:firstName, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
		AES_ENCRYPT(:middleName, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
		AES_ENCRYPT(:lastName, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
		AES_ENCRYPT(:address, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
		AES_ENCRYPT(:city, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
		AES_ENCRYPT(:FK_StateID, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
		AES_ENCRYPT(:zip, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
		AES_ENCRYPT(returnNumericOnly(:phoneNumber), UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
		AES_ENCRYPT(:emailAddress, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
		NOW(),
		NOW(),
		:sid
	)");
		
			$insertValidationRequest	= $dbh->prepare("INSERT INTO AccountCreationValidationHash
	(
		FK_UserAccountID,
		requestDate,
		requestStatus,
		sid,
		encryptedUserAccountID,
		encryptedEmailAddress,
		encryptedMobileNumber,
		encryptedValidationHash,
		encryptedSMSValidationCode,
		originalRequestDate
	)
	VALUES
	(
		:FK_UserAccountID,
		:requestDate,
		:requestStatus,
		:sid,
		AES_ENCRYPT(:FK_UserAccountID, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
		AES_ENCRYPT(:emailAddress, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
		AES_ENCRYPT(returnNumericOnly(:mobilePhone), UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
		AES_ENCRYPT(:validationHash, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
		AES_ENCRYPT(:smsValidationCode, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
		:requestDate
	)");
		
			$checkForExistingEmail -> bindValue(':emailAddress', $emailAddress);
		
			$numAccounts						= 0;
			
			if ($checkForExistingEmail -> execute())
			{
				$row = $checkForExistingEmail->fetchObject();
				
				$numAccounts					= $row->numAccounts;
			}
		
			if ($numAccounts > 0)
			{
				$_SESSION['loginError'] 		= "There is an existing account with the $emailAddress email address.";
				$returnValue					= -3;
			}
			else
			{
				$createAccount -> bindValue(':firstName', $firstName);
				$createAccount -> bindValue(':middleName', $middleName);
				$createAccount -> bindValue(':lastName', $lastName);
				$createAccount -> bindValue(':address', $addressStreet);
				$createAccount -> bindValue(':city', $addressCity);
				$createAccount -> bindValue(':FK_StateID', $addressStateID);
				$createAccount -> bindValue(':zip', $addressZip);
				$createAccount -> bindValue(':phoneNumber', $phoneNumber);
				$createAccount -> bindValue(':emailAddress', $emailAddress);
				$createAccount -> bindValue(':sid', $sid);
				
				if ($createAccount -> execute())
				{
					$newAccountID 				= $dbh -> lastInsertId();
					$returnValue				= $newAccountID;
					
					selectiveErrorLog("new account $newAccountID");
					
					$_SESSION['participantID'] 				= $newAccountID;
						
					$validationHash							= md5(md5("$firstName $middleName $lastName $emailAddress $globalCurrentDate $sid"));
						
					$smsValidationCode						= generateRandomString(6);
						
					$_SESSION['smsVerificationCode'] 		= $smsValidationCode;
		
					$insertValidationRequest -> bindValue(':FK_UserAccountID', $newAccountID);
					$insertValidationRequest -> bindValue(':validationHash', $validationHash);
					$insertValidationRequest -> bindValue(':smsValidationCode', $smsValidationCode);
					$insertValidationRequest -> bindValue(':emailAddress', $emailAddress);
					$insertValidationRequest -> bindValue(':mobilePhone', $phoneCell);
					$insertValidationRequest -> bindValue(':requestDate', $globalCurrentDate);
					$insertValidationRequest -> bindValue(':requestStatus', 0);
					$insertValidationRequest -> bindValue(':sid', $sid);
						
					$insertValidationRequest -> execute();
						
					if ($validationType == 1 && !empty($phoneCell) && strlen($phoneCell) > 0)
					{
						$textBody = "Thank you for creating a ProfitStance account!  Your validation code is $smsValidationCode. Once you have validated your account, use your email address to log in.";
						
						$returnValue	= generateTextMessage($phoneCell, $newAccountID, $newAccountID, $textBody);
							
						// $returnValue2 	= sendValidationNotificationEmail($emailAddress, $participantName, $validationHash, $serverURLRoot);
							
						// if ($returnValue == 1 && $returnValue2 == 0)
						// {
						// 	$returnValue = -2;
						// }
					}
					else
					{
						// $returnValue = sendValidationEmail($study2MemberID, $emailAddress, $participantName, $validationHash, $serverURLRoot);	
					}
				}	
			}
			
			
			$dbh 				= null;	
		}
	    catch (PDOException $e) 
	    {
	    	$returnValue 		= -1;	
			
			errorLog($e->getMessage());
	
			die();
		}
		return $returnValue;
	}
	
	function updateSimplifiedUserAccount($userAccountID, $firstName, $lastName, $street, $street2, $city, $state, $zipCode, $filingStatus, $adjustedGrossIncomeFor2018, $globalCurrentDate, $userEncryptionKey, $sid, $dbh)
	{
		$countryID								= 840;
		
		$responseObject							= array();
		$responseObject['accountUpdated']		= false;
		
		if (!is_numeric($state))
		{
			$state								= getEnumValueStateID($state, $dbh);	
		}
		
		if (!is_numeric($city))
		{
			$city								= getEnumValueCityID($city, $state, $countryID, $dbh);
		}

		$adjustedGrossIncomeSelectValueID		= getEnumValueAgiRange($adjustedGrossIncomeFor2018, $dbh);

		try
		{	
			$updateAccount						= $dbh->prepare("UPDATE 
	UserAccounts
SET
	encryptedFirstName = AES_ENCRYPT(:firstName, UNHEX(SHA2(:userEncryptionKey,512))),
	encryptedLastName = AES_ENCRYPT(:lastName, UNHEX(SHA2(:userEncryptionKey,512))),
	encryptedStreetAddress = AES_ENCRYPT(:street, UNHEX(SHA2(:userEncryptionKey,512))),
	encryptedStreetAddress2 = AES_ENCRYPT(:street2, UNHEX(SHA2(:userEncryptionKey,512))),
	encryptedCity = AES_ENCRYPT(:city, UNHEX(SHA2(:userEncryptionKey,512))),
	encryptedStateID = AES_ENCRYPT(:FK_StateID, UNHEX(SHA2(:userEncryptionKey,512))),
	encryptedZip = AES_ENCRYPT(:zip, UNHEX(SHA2(:userEncryptionKey,512))),
	FK_2018FilingStatus = :FK_2018FilingStatus,
	FK_AdjustedGrossIncomeFor2018 = :FK_AdjustedGrossIncomeFor2018,
	encryptedAdjustedGrossIncome = AES_ENCRYPT(:adjustedGrossIncome, UNHEX(SHA2(:userEncryptionKey,512))),
	modificationDate = :modificationDate,
	sid = :sid
WHERE
	userAccountID = :accountID");
		
			$updateAccount -> bindValue(':firstName', $firstName);
			$updateAccount -> bindValue(':lastName', $lastName);
			$updateAccount -> bindValue(':street', $street);
			$updateAccount -> bindValue(':street2', $street2);
			$updateAccount -> bindValue(':city', $city);
			$updateAccount -> bindValue(':FK_StateID', $state);
			$updateAccount -> bindValue(':zip', $zipCode);
			$updateAccount -> bindValue(':FK_2018FilingStatus', $filingStatus);
			$updateAccount -> bindValue(':FK_AdjustedGrossIncomeFor2018', $adjustedGrossIncomeSelectValueID);
			$updateAccount -> bindValue(':adjustedGrossIncome', $adjustedGrossIncomeFor2018);
			$updateAccount -> bindValue(':modificationDate', $globalCurrentDate);
			$updateAccount -> bindValue(':sid', $sid);
			$updateAccount -> bindValue(':accountID', $userAccountID);
			$updateAccount -> bindValue(':userEncryptionKey', $userEncryptionKey);
				
			if ($updateAccount -> execute())
			{	
				$responseObject['accountUpdated']			= true;
				$responseObject['responseMessage']			= "Your account profile information has been updated.";
				
				setUserFilingStatusForTaxYear($userAccountID, $userAccountID, 2018, $filingStatus, $adjustedGrossIncomeFor2018, $sid, $globalCurrentDate, $dbh);
			}
			else
			{
				$responseObject['responseMessage']			= "Your account profile information could not be updated.";
			}
			
			$dbh 											= null;	
		}
	    catch (PDOException $e) 
	    {
	    	$responseObject['responseMessage']				= "A database error has occurred.  Your account could not be updated.";	
			errorLog($e->getMessage());
	
			die();
		}
		
		return $responseObject;
	}
	
	function updateFilingInformationForUser($liUser, $cpaFirmID, $taxYear, $filingStatusID, $adjustedGrossIncome, $userEncryptionKey, $globalCurrentDate, $sid, $dbh)
	{
		errorLog("updateFilingInformationForUser($liUser, $cpaFirmID, $taxYear, $filingStatusID, $adjustedGrossIncome, $globalCurrentDate, $sid");
		
		$responseObject																					= array();
		$responseObject['accountUpdated']																= false;
		$responseObject['cpaFirmID']																	= $cpaFirmID;
		$responseObject['instantiatedFirmObject']														= false;
		// get range value based on income, set range as well as actual number
		$agiSelectValue																					= getEnumValueAgiRange($adjustedGrossIncome, $dbh);
		
		errorLog("UPDATE 
	UserAccounts
SET
	FK_2018FilingStatus = $filingStatusID,
	FK_AdjustedGrossIncomeFor2018 = $agiSelectValue,
	encryptedAdjustedGrossIncome = AES_ENCRYPT($adjustedGrossIncome, UNHEX(SHA2('$userEncryptionKey',512))),
	modificationDate = '$globalCurrentDate',
	sid = $sid
WHERE
	userAccountID = $liUser");
		
		try
		{	
			$updateAccount																				= $dbh -> prepare("UPDATE 
	UserAccounts
SET
	FK_2018FilingStatus = :filingStatusID,
	FK_AdjustedGrossIncomeFor2018 = :agiSelectID,
	encryptedAdjustedGrossIncome = AES_ENCRYPT(:agi, UNHEX(SHA2(:userEncryptionKey,512))),
	modificationDate = :modificationDate,
	sid = :sid
WHERE
	userAccountID = :accountID");
		
			$updateAccount -> bindValue(':filingStatusID', $filingStatusID);
			$updateAccount -> bindValue(':agiSelectID', $agiSelectValue);
			$updateAccount -> bindValue(':agi', $adjustedGrossIncome);
			$updateAccount -> bindValue(':modificationDate', $globalCurrentDate);
			$updateAccount -> bindValue(':sid', $sid);
			$updateAccount -> bindValue(':accountID', $liUser);
			$updateAccount -> bindValue(':userEncryptionKey', $userEncryptionKey);
				
			if ($updateAccount -> execute())
			{	
				$responseObject['accountUpdated']														= true;
				
				setUserFilingStatusForTaxYear($liUser, $liUser, $taxYear, $filingStatusID, $adjustedGrossIncome, $sid, $globalCurrentDate, $dbh);
				
				if (!empty($cpaFirmID) && $cpaFirmID > 0)
				{
					$cpaFirm																			= new CPAFirm();
		
					$instantiationResult																= $cpaFirm -> instantiateCPAFirmUsingCPAFirmID($liUser, $cpaFirmID, $userEncryptionKey, $sid, $globalCurrentDate, $dbh);
			
					if ($instantiationResult['instantiatedRecord'] == true)
					{
						$responseObject['instantiatedFirmObject']										= true;
				
						$getCPAClientSummaryResult														= $cpaFirm -> getCPAFirmToCPAClientSummaryForTaxYearRecordForUser($liUser, $liUser, $taxYear, $userEncryptionKey, $sid, $globalCurrentDate, $dbh);
				
						errorLog(count($getCPAClientSummaryResult)." ".json_encode($getCPAClientSummaryResult));
				
						if (!empty($getCPAClientSummaryResult) && count($getCPAClientSummaryResult) > 0)
						{
							errorLog("updateCPAFirmToCPAClientSummaryForTaxYear");
							
							$secondaryCPAIDArray														= $cpaFirm -> getSecondaryCPAsForCPAClient($liUser, $liUser, true, $userEncryptionKey, $globalCurrentDate, $sid, $dbh);
							
							$updateCPAClientFirmSummaryResult											= $cpaFirm -> updateCPAFirmToCPAClientSummaryForTaxYear($liUser, $liUser, $getCPAClientSummaryResult[0]['clientNumber'], $filingStatusID, $adjustedGrossIncome, $getCPAClientSummaryResult[0]['FK_PrimaryCPAAccountID'], $secondaryCPAIDArray, $taxYear, $userEncryptionKey, $globalCurrentDate, $sid, $dbh);	
						}
					}
				}
			}
			
			$dbh 																						= null;	
		}
	    catch (PDOException $e) 
	    {
			errorLog($e->getMessage());
	
			die();
		}
		
		return $responseObject;
	}
	
	function updateUserAccount($userAccountID, $firstName, $middleName, $lastName, $street, $street2, $city, $state, $zipCode, $country, $phoneNumber, $governmentID, $globalCurrentDate, $sid, $dbh)
	{
		$responseObject							= array();
		
		if (!is_numeric($state))
		{
			$state								= getEnumValueStateID($state, $dbh);	
		}
		
		if (!is_numeric($country))
		{
			$country							= getEnumValueCountryID($country, $dbh);	
		}

		try
		{	
			$updateAccount						= $dbh->prepare("UPDATE 
	UserAccounts
SET
	encryptedFirstName = AES_ENCRYPT(:firstName, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	encryptedMiddleName = AES_ENCRYPT(:middleName, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	encryptedLastName = AES_ENCRYPT(:lastName, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	encryptedStreetAddress = AES_ENCRYPT(:street, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	encryptedStreetAddress2 = AES_ENCRYPT(:street2, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	encryptedCity = AES_ENCRYPT(:city, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	encryptedStateID = AES_ENCRYPT(:FK_StateID, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	encryptedZip = AES_ENCRYPT(:zip, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	FK_CountryCode = :countryID,
	encryptedPhoneNumber = AES_ENCRYPT(returnNumericOnly(:phoneNumber), UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	modificationDate = :modificationDate,
	sid = :sid
WHERE
	userAccountID = :accountID");
	
			$updateGovtID						= $dbh->prepare("UPDATE 
	UserAccounts
SET
	encryptedGovernmentID = AES_ENCRYPT(:governmentID, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	modificationDate = :modificationDate,
	sid = :sid
WHERE
	userAccountID = :accountID");
		
			$updateAccount -> bindValue(':firstName', $firstName);
			$updateAccount -> bindValue(':middleName', $middleName);
			$updateAccount -> bindValue(':lastName', $lastName);
			$updateAccount -> bindValue(':street', $street);
			$updateAccount -> bindValue(':street2', $street2);
			$updateAccount -> bindValue(':city', $city);
			$updateAccount -> bindValue(':FK_StateID', $state);
			$updateAccount -> bindValue(':zip', $zipCode);
			$updateAccount -> bindValue(':countryID', $country);
			$updateAccount -> bindValue(':phoneNumber', $phoneNumber);
			$updateAccount -> bindValue(':modificationDate', $globalCurrentDate);
			$updateAccount -> bindValue(':sid', $sid);
			$updateAccount -> bindValue(':accountID', $userAccountID);
				
			if ($updateAccount -> execute())
			{	
				$responseObject['accountUpdated']			= true;
				$responseObject['responseMessage']			= "Your account profile information has been updated.";
				
				if (!empty($governmentID) && strpos($governmentID, "*") === false)
				{
					$updateGovtID -> bindValue(':governmentID', $governmentID);
					$updateGovtID -> bindValue(':modificationDate', $globalCurrentDate);
					$updateGovtID -> bindValue(':sid', $sid);
					$updateGovtID -> bindValue(':accountID', $userAccountID);	
					
					if ($updateGovtID -> execute())
					{
						$responseObject['govtIDUpdated']	= true;	
					}
					else
					{
						$responseObject['govtIDUpdated']	= false;	
					}
				}
				else
				{
					$responseObject['govtIDUpdated']	= false;	
				}
			}
			else
			{	
				$responseObject['accountUpdated']			= false;
				$responseObject['govtIDUpdated']			= false;
				$responseObject['responseMessage']			= "Your account profile information could not be updated.";
			}
			
			$dbh 											= null;	
		}
	    catch (PDOException $e) 
	    {
	    	$responseObject['responseMessage']				= "A database error has occurred.  Your account could not be updated.";	
			$responseObject['accountUpdated']				= false;
			$responseObject['govtIDUpdated']				= false;
			errorLog($e->getMessage());
	
			die();
		}
		
		return $responseObject;
	}
	
	function updateUserPhoneNumber($userAccountID, $phoneNumber, $globalCurrentDate, $sid, $dbh)
	{
		$responseObject									= array();
		$responseObject['setPhoneNumber']				= false;
		
		try
		{	
			$updateAccount								= $dbh->prepare("UPDATE 
	UserAccounts
SET
	encryptedPhoneNumber = AES_ENCRYPT(returnNumericOnly(:phoneNumber), UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	modificationDate = :modificationDate,
	sid = :sid
WHERE
	userAccountID = :accountID");
		
			$updateAccount -> bindValue(':phoneNumber', $phoneNumber);
			$updateAccount -> bindValue(':modificationDate', $globalCurrentDate);
			$updateAccount -> bindValue(':sid', $sid);
			$updateAccount -> bindValue(':accountID', $userAccountID);
				
			if ($updateAccount -> execute())
			{	
				$responseObject['setPhoneNumber']		= true;
			}
		}
	    catch (PDOException $e) 
	    {
	    	errorLog($e->getMessage());
	
			die();
		}
		
		return $responseObject;
	}
	
	// END ACCOUNT CREATION AND MODIFICATION FUNCTIONS
	
	// USER AGREEMENT MANAGEMENT FUNCTIONS
	
	function writeUserAgreementSignatureEvent($userObject, $userAgreementVersion, $clientAddress, $clientHostname, $globalCurrentDate, $sid, $dbh)
	{
		$responseObject									= array();
		$responseObject['wroteUserAgreementSignature']	= false;
		$responseObject['resultMessage']				= "";
		
		$trialExpirationDate											= new DateTime($globalCurrentDate);
		$trialExpirationDate -> modify('+14 day');
		$trialExpirationDateString										= date_format($trialExpirationDate, "Y-m-d");
		
		try
		{	
			$updateUserAgreementInfoInUserAccount		= $dbh->prepare("UPDATE
	UserAccounts
SET
	userAgreementDate = :userAgreementDate,
	userAgreementVersion = :userAgreementVersion,
	modificationDate = :modificationDate,
	trialExpirationDate = :trialExpirationDate
WHERE
	userAccountID = :accountID");
			
			
			$addUserAgreementSignatureEvent				= $dbh->prepare("INSERT INTO UserAgreementSignatureHistory
(
	FK_AccountID,
	signatureDateTime,
	agreementVersionNumber,
	encryptedHostIP,
	encryptedHostName,
	encryptedSid
)
VALUES
(
	:FK_AccountID,
	:signatureDateTime,
	:agreementVersionNumber,
	AES_ENCRYPT(:encryptedHostIP, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	AES_ENCRYPT(:encryptedHostName, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	AES_ENCRYPT(:encryptedSid, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512)))
)");

			$addUserAgreementSignatureEvent -> bindValue(':FK_AccountID', $userObject -> getUserAccountID());
			$addUserAgreementSignatureEvent -> bindValue(':signatureDateTime', $globalCurrentDate);
			$addUserAgreementSignatureEvent -> bindValue(':agreementVersionNumber', $userAgreementVersion);
			$addUserAgreementSignatureEvent -> bindValue(':encryptedHostIP', $clientAddress);
			$addUserAgreementSignatureEvent -> bindValue(':encryptedHostName', $clientHostname);
			$addUserAgreementSignatureEvent -> bindValue(':encryptedSid', $sid);
		
			if ($addUserAgreementSignatureEvent -> execute())
			{
				$responseObject['wroteUserAgreementSignature']	= true;
				$responseObject['resultMessage']				= "Thank you for signing the user agreement.";
				
				$updateUserAgreementInfoInUserAccount -> bindValue(':accountID', $userObject -> getUserAccountID());
				$updateUserAgreementInfoInUserAccount -> bindValue(':userAgreementDate', $globalCurrentDate);
				$updateUserAgreementInfoInUserAccount -> bindValue(':userAgreementVersion', $userAgreementVersion);
				$updateUserAgreementInfoInUserAccount -> bindValue(':modificationDate', $globalCurrentDate);
				$updateUserAgreementInfoInUserAccount -> bindValue(':trialExpirationDate', $trialExpirationDateString);
				
				$updateUserAgreementInfoInUserAccount -> execute();
			}
			else
			{
				$responseObject['resultMessage']				= "We were unable to update the database to record your signature.";
			}			
		}
	    catch (PDOException $e) 
	    {
	    	$responseObject['resultMessage']					= "Error: We were unable to update the database to record your signature due to a database error: ".$e->getMessage();	
		}
		
		return $responseObject;
	}
	
	// END USER AGREEMENT MANAGEMENT FUNCTIONS
	
	// CRYPTO WALLET FUNCTIONS
	
	function checkForCryptoWallet($liuAccountID, $userAccountID, $cryptoWalletIDValue, $globalCurrentDate, $sid, $dbh)
	{
		// to replace
		
		errorLog("Wallet ID = $cryptoWalletIDValue");
		
		$cryptoWallet					= null;
		$walletID						= 0;
		$walletType						= 1;
		$walletTypeName					= "Coinbase";
		$assetType						= 1;
		$assetTypeName					= "BTC";
		
		try
		{		
			$getCryptoWalletRecord		= $dbh -> prepare("SELECT
	CryptoWallets.walletID,
	CryptoWallets.FK_AccountID,
	CryptoWallets.FK_AuthorID,
	CryptoWallets.FK_AssetTypeID,
	CryptoWallets.FK_WalletTypeID,
	CryptoWallets.creationDate,
	CryptoWallets.sid,
	AssetTypes.assetTypeLabel,
	AES_DECRYPT(WalletTypes.encryptedWalletTypeLabel, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))) AS walletTypeLabel
FROM
	CryptoWallets
	INNER JOIN AssetTypes ON CryptoWallets.FK_AssetTypeID = AssetTypes.assetTypeID AND AssetTypes.languageCode = 'EN'
	INNER JOIN WalletTypes ON CryptoWallets.FK_WalletTypeID = WalletTypes.walletTypeID AND WalletTypes.languageCode = 'EN'
WHERE
	CryptoWallets.encryptedCryptoWalletIDValue = AES_ENCRYPT(:cryptoWalletIDValue, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))) AND
	CryptoWallets.FK_AccountID = :accountID");

			$getCryptoWalletRecord -> bindValue(':cryptoWalletIDValue', $cryptoWalletIDValue);
			$getCryptoWalletRecord -> bindValue(':accountID', $userAccountID);
						
			if ($getCryptoWalletRecord -> execute() && $getCryptoWalletRecord -> rowCount() > 0)
			{
				$row 					= $getCryptoWalletRecord -> fetchObject();
				
				$walletID				= $row->walletID; 
				$accountID				= $row->FK_AccountID;
				$authorID				= $row->FK_AuthorID;
				$assetType				= $row->FK_AssetTypeID;
				$assetTypeName			= $row->assetTypeLabel;
				$walletType				= $row->FK_WalletTypeID;
				$walletTypeName			= $row->assetTypeLabel;
				$creationDate			= $row->walletTypeLabel;
				$sid					= $row->sid;
				
				$cryptoWallet			= new CryptoWallet($walletID, $accountID, $authorID, $cryptoWalletIDValue, $assetType, $assetTypeName, $walletType, $walletTypeName, $creationDate, $sid);	
				
				$createWallet			= 0;	
			}
			
			if ($walletID == 0)
			{
				$walletID				= createCryptoWalletRecord($userAccountID, $liuAccountID, $cryptoWalletIDValue, $assetType, 1, $globalCurrentDate, $sid, $dbh);
				
				if ($walletID > 0)
				{
					$cryptoWallet		= new CryptoWallet($walletID, $userAccountID, $liuAccountID, $cryptoWalletIDValue, $assetType, $assetTypeName, $walletType, $walletTypeName, $globalCurrentDate, $sid);	
				}
			}	
		}
	    catch (PDOException $e) 
	    {
	    	$cryptoWallet 				= null;	
			
			errorLog($e -> getMessage());
	
			die();
		}
		
		return $cryptoWallet;		
	}

	function checkForDetailedCryptoWallet($liuAccountID, $userAccountID, $cryptoWalletIDValue, $transactionSourceID, $transactionSourceName, $assetTypeID, $assetTypeName, $globalCurrentDate, $sid, $dbh)
	{
		// to replace
		
		errorLog("Wallet ID = $cryptoWalletIDValue");
		
		$cryptoWallet					= null;
		$walletID						= 0;
		$walletType						= $transactionSourceID;
		$walletTypeName					= $transactionSourceName;
		$assetType						= $assetTypeID;
		$assetTypeName					= $assetTypeName;
		
		try
		{		
			$getCryptoWalletRecord		= $dbh -> prepare("SELECT
	CryptoWallets.walletID,
	CryptoWallets.FK_AccountID,
	CryptoWallets.FK_AuthorID,
	CryptoWallets.FK_AssetTypeID,
	CryptoWallets.FK_WalletTypeID,
	CryptoWallets.creationDate,
	CryptoWallets.sid,
	AssetTypes.assetTypeLabel,
	AES_DECRYPT(WalletTypes.encryptedWalletTypeLabel, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))) AS walletTypeLabel
FROM
	CryptoWallets
	INNER JOIN AssetTypes ON CryptoWallets.FK_AssetTypeID = AssetTypes.assetTypeID AND AssetTypes.languageCode = 'EN'
	INNER JOIN WalletTypes ON CryptoWallets.FK_WalletTypeID = WalletTypes.walletTypeID AND WalletTypes.languageCode = 'EN'
WHERE
	CryptoWallets.encryptedCryptoWalletIDValue = AES_ENCRYPT(:cryptoWalletIDValue, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))) AND
	CryptoWallets.FK_AccountID = :accountID");

			$getCryptoWalletRecord -> bindValue(':cryptoWalletIDValue', $cryptoWalletIDValue);
			$getCryptoWalletRecord -> bindValue(':accountID', $userAccountID);
						
			if ($getCryptoWalletRecord -> execute() && $getCryptoWalletRecord -> rowCount() > 0)
			{
				$row 					= $getCryptoWalletRecord -> fetchObject();
				
				$walletID				= $row->walletID; 
				$accountID				= $row->FK_AccountID;
				$authorID				= $row->FK_AuthorID;
				$assetType				= $row->FK_AssetTypeID;
				$assetTypeName			= $row->assetTypeLabel;
				$walletType				= $row->FK_WalletTypeID;
				$walletTypeName			= $row->assetTypeLabel;
				$creationDate			= $row->walletTypeLabel;
				$sid					= $row->sid;
				
				$cryptoWallet			= new CryptoWallet($walletID, $accountID, $authorID, $cryptoWalletIDValue, $assetType, $assetTypeName, $walletType, $walletTypeName, $creationDate, $sid);	
				
				$createWallet			= 0;	
			}
			
			if ($walletID == 0)
			{
				$walletID				= createCryptoWalletRecord($userAccountID, $liuAccountID, $cryptoWalletIDValue, $assetType, 1, $globalCurrentDate, $sid, $dbh);
				
				if ($walletID > 0)
				{
					$cryptoWallet		= new CryptoWallet($walletID, $userAccountID, $liuAccountID, $cryptoWalletIDValue, $assetType, $assetTypeName, $walletType, $walletTypeName, $globalCurrentDate, $sid);	
				}
			}	
		}
	    catch (PDOException $e) 
	    {
	    	$cryptoWallet 				= null;	
			
			errorLog($e -> getMessage());
	
			die();
		}
		
		return $cryptoWallet;		
	}
	
	// END CRYTPO WALLET FUNCTIONS
	
	function getCryptoDailyPortfolioBalanceForUserAccountAssetAndTransactionSourceID($accountID, $assetTypeID, $assetTypeName, $nativeCurrencyTypeID, $nativeCurrencyTypeName, $transactionSourceID, $startDate, $globalCurrentDate, $sid, $dbh)
	{
		errorLog("getCryptoDailyPortfolioBalanceForUserAccountAssetAndTransactionSourceID($accountID, $assetTypeID, $assetTypeName, $nativeCurrencyTypeID, $nativeCurrencyTypeName, $transactionSourceID, $startDate, $globalCurrentDate");
		
		$responseObject														= array();
		$responseObject['retrievedTransactions']								= false;
		
		// change in structure
		$testDate															= $startDate;
		
		if (is_object($testDate) == false)
		{
			$testDate														= new DateTime($startDate); // start with date of first transaction	
		}
		
		$endingDate															= new DateTime('now');
		
		$endingDate -> modify('-1 day');
		
		$currentAssetAmountTotal												= 0;
		
		// begin while loop from that date to now
		
		while ($testDate < $endingDate)
		{
			// get sum of all transactions for each date
			
			$transactionDataForDate											= getCryptoTransactionsAssetTotalForUserAndAssetForDate($accountID, $assetTypeID, $transactionSourceID, $testDate, $dbh);
			
			// @task 20190221 - the purpose here is to get the daily balance for the user's portfolio in USD.  Shouldn't I just get the transaction amount in cc in whichever currency belongs to the user and multiply that amount by the spot price, in USD, of that currency?  This would basically be the base currency spot price in USD * total transaction amount, added or subtracted from the amount of the wallet.  
			
			$formattedTestDate												= date_format($testDate, "Y-m-d");
			
			// add that sum to the total existing balance
		
			$currentAssetAmountTotal											= $currentAssetAmountTotal + $transactionDataForDate['transactionTotal'];
		
			// get spot price for each date
		
			$spotPrice														= 0;
		
			// what I am looking for in the daily balance calculation is the value in USD of the asset on that date.  So, if the asset type is USD, and the native currency is NOT USD, I am going to switch the two and get the 
		
			$spotPriceResponseObject											= array();
			
			if ($assetTypeID == $nativeCurrencyTypeID || $assetTypeID == 2)
			{
				$spotPriceResponseObject['retrievedSpotPrice']				= true;
				$spotPriceResponseObject['spotPrice']						= 1;
			}
			else
			{
				$spotPriceResponseObject										= getSpotPriceForAssetForDate($assetTypeID, $testDate, 2, $dbh);
			}
			
			if ($spotPriceResponseObject['retrievedSpotPrice'] == true)
			{
				$spotPrice													= $spotPriceResponseObject['spotPrice'];
				
				$assetPrice													= $spotPrice * $currentAssetAmountTotal;
				
				$responseObject[$formattedTestDate]							= $currentAssetAmountTotal;
				
				// write record for each date
				writeDailyCryptoBalanceRecordForUserAccountAssetAndTransactionSource($accountID, $currentAssetAmountTotal, $assetPrice, $assetTypeID, $transactionSourceID, $nativeCurrencyTypeID, $testDate, $globalCurrentDate, $sid, $dbh);	
			}
			else
			{
				errorLog("could not writeDailyCryptoBalanceRecordForUserAccountAssetAndTransactionSource for $assetTypeID, $nativeCurrencyTypeID for $formattedTestDate");
				
				$doContinue													= true;
				
				$spotPriceBaseCurrency										= 0;
				$spotPriceQuoteCurrency										= 0;
				
				if ($assetTypeID == 2)
				{
					$spotPriceBaseCurrency									= 1;	
				}
				else
				{
					$baseSpotPriceResponseObject								= getSpotPriceForAssetForDate($assetTypeID, $testDate, 2, $dbh); // get spot price for this currency in USD
					
					if ($baseSpotPriceResponseObject['retrievedSpotPrice'] == true)
					{
						$spotPriceBaseCurrency								= $baseSpotPriceResponseObject['spotPrice'];
						
						if ($spotPriceBaseCurrency == 0)
						{
							errorLog("spot price for base currency $assetTypeID, 2 for $formattedTestDate is 0");
							$doContinue										= false;	
						}
					}
					else
					{
						errorLog("could not get spot price for base currency $assetTypeID, 2 for $formattedTestDate");
						$doContinue											= false;
					}
				}
				
				if ($nativeCurrencyTypeID == 2)
				{
					$spotPriceQuoteCurrency									= 1;	
				}
				else
				{
					$quoteSpotPriceResponseObject							= getSpotPriceForAssetForDate($nativeCurrencyTypeID, $testDate, 2, $dbh); // get spot price for this currency in USD
					
					if ($quoteSpotPriceResponseObject['retrievedSpotPrice'] == true)
					{
						$spotPriceQuoteCurrency								= $quoteSpotPriceResponseObject['spotPrice'];
						
						if ($spotPriceQuoteCurrency == 0)
						{
							errorLog("spot price for quote currency $assetTypeID, 2 for $formattedTestDate is 0");
							$doContinue										= false;	
						}
					}
					else
					{
						errorLog("could not get spot price for quote currency $nativeCurrencyTypeID, 2 for $formattedTestDate");
						$doContinue											= false;
					}
				}
				
				if ($doContinue == true)
				{
					$spotPrice												= $spotPriceBaseCurrency / $spotPriceQuoteCurrency;
					
					$assetPrice												= $spotPrice * $currentAssetAmountTotal;
				
					$responseObject[$formattedTestDate]						= $currentAssetAmountTotal;
					
					setDailyPriceData($assetTypeID, $nativeCurrencyTypeID, $formattedTestDate, $spotPrice, 9, $globalCurrentDate, $sid, $dbh);
					
					// write record for each date
					writeDailyCryptoBalanceRecordForUserAccountAssetAndTransactionSource($accountID, $currentAssetAmountTotal, $assetPrice, $assetTypeID, $transactionSourceID, $nativeCurrencyTypeID, $testDate, $globalCurrentDate, $sid, $dbh);
				}
			}
		
			$testDate -> modify('+1 day');
			
		
			// update 1ToM record for last date checked so that we can start there next time 	
		}
	
		return $responseObject;
	}
	
	function updateCryptoBalance($currentBalance, $cryptoTransaction)
	{
		$currentAssetAmountTotal		= $currentBalance;
		
		if ($cryptoTransaction -> getIsDebit() == 1)
		{
			$currentAssetAmountTotal	= $currentAssetAmountTotal - $cryptoTransaction -> getBtcQuantityTransacted();
		}
		else if ($cryptoTransaction -> getIsDebit() == 0)
		{
			$currentAssetAmountTotal	= $currentAssetAmountTotal + $cryptoTransaction -> getBtcQuantityTransacted();
		}
		
		return $currentAssetAmountTotal;	
	}
	
	function writeDailyCryptoBalanceRecordForUserAccountAssetAndTransactionSource($accountID, $assetAmount, $assetPrice, $assetTypeID, $transactionSourceID, $nativeCurrencyTypeID, $balanceDateTime, $globalCurrentDate, $sid, $dbh)
	{
		$responseObject											= array();
		$responseObject['createdCryptoBalanceRecord']			= false;
		
		$balanceDate												= date_format($balanceDateTime, "Y-m-d");
		
		try
		{		
			$insertCryptoBalanceRecord							= $dbh -> prepare("REPLACE DailyPortfolioBalanceForUserAccount
(
	FK_AccountID,
	FK_AssetTypeID,
	FK_NativeCurrencyTypeID,
	FK_TransactionSource,
	balanceDate,
	assetBalanceForDateAsDecimal,
	assetPriceInNativeCurrencyForDateAsDecimal,
	assetPriceInNativeCurrencyForDateAsInt,
	creationDate
)
VALUES
(
	:FK_AccountID,
	:FK_AssetTypeID,
	:FK_NativeCurrencyTypeID,
	:FK_TransactionSource,
	:balanceDate,
	:assetBalanceForDateAsDecimal,
	:assetPriceInNativeCurrencyForDateAsDecimal,
	:assetPriceInNativeCurrencyForDateAsInt,
	:creationDate
)");

			if ($assetPrice != 0 || $assetAmount != 0)
			{
				errorLog("REPLACE DailyPortfolioBalanceForUserAccount
(
	FK_AccountID,
	FK_AssetTypeID,
	FK_NativeCurrencyTypeID,
	FK_TransactionSource,
	balanceDate,
	assetBalanceForDateAsDecimal,
	assetPriceInNativeCurrencyForDateAsDecimal,
	assetPriceInNativeCurrencyForDateAsInt,
	creationDate
)
VALUES
(
	$accountID,
	$assetTypeID,
	$nativeCurrencyTypeID,
	$transactionSourceID,
	'$balanceDate',
	$assetAmount,
	$assetPrice,
	$assetPrice,
	'$globalCurrentDate'
)");	
			}

			$insertCryptoBalanceRecord -> bindValue(':FK_AccountID', $accountID);
			$insertCryptoBalanceRecord -> bindValue(':FK_AssetTypeID', $assetTypeID);
			$insertCryptoBalanceRecord -> bindValue(':FK_NativeCurrencyTypeID', $nativeCurrencyTypeID);
			$insertCryptoBalanceRecord -> bindValue(':FK_TransactionSource', $transactionSourceID);
			$insertCryptoBalanceRecord -> bindValue(':balanceDate', $balanceDate);
			$insertCryptoBalanceRecord -> bindValue(':assetBalanceForDateAsDecimal', $assetAmount);
			$insertCryptoBalanceRecord -> bindValue(':assetPriceInNativeCurrencyForDateAsDecimal', $assetPrice);
			$insertCryptoBalanceRecord -> bindValue(':assetPriceInNativeCurrencyForDateAsInt', $assetPrice);
			$insertCryptoBalanceRecord -> bindValue(':creationDate', $globalCurrentDate);
			
			if ($insertCryptoBalanceRecord -> execute())
			{
				$responseObject['balanceRecordID']				= $dbh -> lastInsertId();
				$responseObject['createdCryptoBalanceRecord']	= true;
			}
			else
			{
				$responseObject['resultMessage'] 				= "ERROR: could not create DailyPortfolioBalanceForUserAccount record for $accountID, $assetTypeID, $nativeCurrencyTypeID, $transactionSourceID, $balanceDate, $assetAmount, $globalCurrentDate";
			}	
		}
	    catch (PDOException $e) 
	    {
	    	$responseObject['resultMessage'] 					= "ERROR: could not create DailyPortfolioBalanceForUserAccount record for $accountID, $assetTypeID, $nativeCurrencyTypeID, $transactionSourceID, $balanceDate, $assetAmount, $globalCurrentDate due to a database error: ".$e -> getMessage();	
			
			errorLog($e -> getMessage());
	
			die();
		}
		
		return $responseObject;	
	}
	
	function writeDailyTaxLiabilityRecordForUserAccount($accountID, $liabilityDate, $nativeCurrencyTypeID, $dailyShortGainOrLoss, $dailyLongGainOrLoss, $totalShortGainsYTD, $totalLongGainsYTD, $totalShortLossesYTD, $totalLongLossesYTD, $totalLossAsOfDate, $adjustedTotalLoss, $shortGainOrLossAsOfDate, $longGainOrLossAsOfDate, $dailyShortTermTaxLiability, $dailyLongTermTaxLiability, $totalShortTermTaxLiabilityAsOfDate, $totalLongTermTaxLiabilityAsOfDate, $totalTaxLiabilityAsOfDate, $globalCurrentDate, $sid, $dbh)
	{	
		$responseObject											= array();
		$responseObject['createdTaxLiabilityRecord']			= false;
		
		try
		{		
			$insertTaxLiabilityRecord							= $dbh -> prepare("REPLACE DailyPortfolioTaxLiabilityForUserAccount
(
	FK_AccountID,
	liabilityDate,
	FK_NativeCurrencyTypeID,
	dailyShortGainOrLoss,
	dailyLongGainOrLoss,
	shortGainAsOfDate,
	longGainAsOfDate,
	shortLossAsOfDate,
	longLossAsOfDate,
	totalLossAsOfDate,
	adjustedTotalLossAsOfDate,
	shortGainOrLossAsOfDate,
	longGainOrLossAsOfDate,
	dailyShortTermTaxLiability,
	dailyLongTermTaxLiability,
	totalShortTermTaxLiabilityAsOfDate,
	totalLongTermTaxLiabilityAsOfDate,
	totalTaxLiabilityAsOfDate,
	creationDate
)
VALUES
(
	:FK_AccountID,
	:liabilityDate,
	:FK_NativeCurrencyTypeID,
	:dailyShortGainOrLoss,
	:dailyLongGainOrLoss,
	:shortGainAsOfDate,
	:longGainAsOfDate,
	:shortLossAsOfDate,
	:longLossAsOfDate,
	:totalLossAsOfDate,
	:adjustedTotalLossAsOfDate,
	:shortGainOrLossAsOfDate,
	:longGainOrLossAsOfDate,
	:dailyShortTermTaxLiability,
	:dailyLongTermTaxLiability,
	:totalShortTermTaxLiabilityAsOfDate,
	:totalLongTermTaxLiabilityAsOfDate,
	:totalTaxLiabilityAsOfDate,
	:creationDate
)");

			errorLog("REPLACE DailyPortfolioTaxLiabilityForUserAccount
(
	FK_AccountID,
	liabilityDate,
	FK_NativeCurrencyTypeID,
	dailyShortGainOrLoss,
	dailyLongGainOrLoss,
	shortGainAsOfDate,
	longGainAsOfDate,
	shortLossAsOfDate,
	longLossAsOfDate,
	totalLossAsOfDate,
	adjustedTotalLossAsOfDate,
	shortGainOrLossAsOfDate,
	longGainOrLossAsOfDate,
	dailyShortTermTaxLiability,
	dailyLongTermTaxLiability,
	totalShortTermTaxLiabilityAsOfDate,
	totalLongTermTaxLiabilityAsOfDate,
	totalTaxLiabilityAsOfDate,
	creationDate
)
VALUES
(
	$accountID,
	'$liabilityDate',
	$nativeCurrencyTypeID,
	$dailyShortGainOrLoss,
	$dailyLongGainOrLoss,
	$totalShortGainsYTD,
	$totalLongGainsYTD,
	$totalShortLossesYTD,
	$totalLongLossesYTD,
	$totalLossAsOfDate,
	$adjustedTotalLoss,
	$shortGainOrLossAsOfDate,
	$longGainOrLossAsOfDate,
	$dailyShortTermTaxLiability,
	$dailyLongTermTaxLiability,
	$totalShortTermTaxLiabilityAsOfDate,
	$totalLongTermTaxLiabilityAsOfDate,
	$totalTaxLiabilityAsOfDate,
	'$globalCurrentDate'
)");	
			$insertTaxLiabilityRecord -> bindValue(':FK_AccountID', $accountID);
			$insertTaxLiabilityRecord -> bindValue(':liabilityDate', $liabilityDate);
			$insertTaxLiabilityRecord -> bindValue(':FK_NativeCurrencyTypeID', $nativeCurrencyTypeID);
			$insertTaxLiabilityRecord -> bindValue(':dailyShortGainOrLoss', $dailyShortGainOrLoss);
			$insertTaxLiabilityRecord -> bindValue(':dailyLongGainOrLoss', $dailyLongGainOrLoss);
			$insertTaxLiabilityRecord -> bindValue(':shortGainAsOfDate', $totalShortGainsYTD);
			$insertTaxLiabilityRecord -> bindValue(':longGainAsOfDate', $totalLongGainsYTD);
			$insertTaxLiabilityRecord -> bindValue(':shortLossAsOfDate', $totalShortLossesYTD);
			$insertTaxLiabilityRecord -> bindValue(':longLossAsOfDate', $totalLongLossesYTD);
			$insertTaxLiabilityRecord -> bindValue(':totalLossAsOfDate', $totalLossAsOfDate);
			$insertTaxLiabilityRecord -> bindValue(':adjustedTotalLossAsOfDate', $adjustedTotalLoss);
			$insertTaxLiabilityRecord -> bindValue(':shortGainOrLossAsOfDate', $shortGainOrLossAsOfDate);
			$insertTaxLiabilityRecord -> bindValue(':longGainOrLossAsOfDate', $longGainOrLossAsOfDate);
			$insertTaxLiabilityRecord -> bindValue(':dailyShortTermTaxLiability', $dailyShortTermTaxLiability);
			$insertTaxLiabilityRecord -> bindValue(':dailyLongTermTaxLiability', $dailyLongTermTaxLiability);
			$insertTaxLiabilityRecord -> bindValue(':totalShortTermTaxLiabilityAsOfDate', $totalShortTermTaxLiabilityAsOfDate);
			$insertTaxLiabilityRecord -> bindValue(':totalLongTermTaxLiabilityAsOfDate', $totalLongTermTaxLiabilityAsOfDate);
			$insertTaxLiabilityRecord -> bindValue(':totalTaxLiabilityAsOfDate', $totalTaxLiabilityAsOfDate);			
			$insertTaxLiabilityRecord -> bindValue(':creationDate', $globalCurrentDate);
			
			if ($insertTaxLiabilityRecord -> execute())
			{
				$responseObject['taxLiabilityRecordID']			= $dbh -> lastInsertId();
				$responseObject['createdTaxLiabilityRecord']	= true;
				$responseObject['resultMessage'] 				= "Created DailyPortfolioTaxLiabilityForUserAccount record for $accountID, $liabilityDate, $nativeCurrencyTypeID, $dailyShortGainOrLoss, $dailyLongGainOrLoss, $totalShortGainsYTD, $totalLongGainsYTD, $totalShortLossesYTD, $totalLongLossesYTD, $totalLossAsOfDate, $adjustedTotalLoss, $shortGainOrLossAsOfDate, $longGainOrLossAsOfDate, $dailyShortTermTaxLiability, $dailyLongTermTaxLiability, $totalShortTermTaxLiabilityAsOfDate, $totalLongTermTaxLiabilityAsOfDate, $totalTaxLiabilityAsOfDate, $globalCurrentDate";
			}
			else
			{
				$responseObject['resultMessage'] 				= "ERROR: could not create DailyPortfolioTaxLiabilityForUserAccount record for $accountID, $liabilityDate, $nativeCurrencyTypeID, $dailyShortGainOrLoss, $dailyLongGainOrLoss, $totalShortGainsYTD, $totalLongGainsYTD, $totalShortLossesYTD, $totalLongLossesYTD, $totalLossAsOfDate, $adjustedTotalLoss, $shortGainOrLossAsOfDate, $longGainOrLossAsOfDate, $dailyShortTermTaxLiability, $dailyLongTermTaxLiability, $totalShortTermTaxLiabilityAsOfDate, $totalLongTermTaxLiabilityAsOfDate, $totalTaxLiabilityAsOfDate, $globalCurrentDate";
			}	
		}
	    catch (PDOException $e) 
	    {
	    	$responseObject['resultMessage'] 					= "ERROR: could not create DailyPortfolioTaxLiabilityForUserAccount record for $accountID, $liabilityDate, $nativeCurrencyTypeID, $dailyShortGainOrLoss, $dailyLongGainOrLoss, $totalShortGainsYTD, $totalLongGainsYTD, $totalShortLossesYTD, $totalLongLossesYTD, $totalLossAsOfDate, $adjustedTotalLoss, $shortGainOrLossAsOfDate, $longGainOrLossAsOfDate, $dailyShortTermTaxLiability, $dailyLongTermTaxLiability, $totalShortTermTaxLiabilityAsOfDate, $totalLongTermTaxLiabilityAsOfDate, $totalTaxLiabilityAsOfDate, $globalCurrentDate due to a database error: ".$e -> getMessage();	
			
			errorLog($e -> getMessage());
	
			die();
		}
		
		return $responseObject;	
	}
	
	function getMostRecentTaxFormInstanceEventDataForUserAccountForYear($accountID, $year, $taxFormTypeID, $sid, $dbh)
	{
		$responseObject														= array();
		$responseObject['retrievedTaxFormInstanceEventData']				= false;
		
		try
		{		
			$getTaxFormInstanceEventData									= $dbh -> prepare("SELECT
	TaxFormInstanceData.taxFormEventID,
	TaxFormInstanceData.creationDate
FROM
	TaxFormInstanceData
WHERE
	TaxFormInstanceData.FK_AccountID = :accountID AND
	TaxFormInstanceData.taxYear = :year AND
	TaxFormInstanceData.FK_TaxFormTypeID = :taxFormTypeID
ORDER BY
	TaxFormInstanceData.creationDate DESC
LIMIT 1");

			$getTaxFormInstanceEventData -> bindValue(':accountID', $accountID);
			$getTaxFormInstanceEventData -> bindValue(':year', $year);
			$getTaxFormInstanceEventData -> bindValue(':taxFormTypeID', $taxFormTypeID);
						
			if ($getTaxFormInstanceEventData -> execute() && $getTaxFormInstanceEventData -> rowCount() > 0)
			{
				
				$responseObject['retrievedTaxFormInstanceEventData']		= true;
				
				$row 														= $getTaxFormInstanceEventData -> fetchObject();
				
				$responseObject['taxFormEventID']							= $row -> taxFormEventID;
				$responseObject['creationDate']								= $row -> creationDate;	
				
				$responseObject['resultMessage']							= "Successfully retrieved tax form instance event data record for $accountID";					
			}
			else
			{
				$responseObject['resultMessage']							= "No tax form instance event data records found for $accountID";
			}
		}
	    catch (PDOException $e) 
	    {
	    	$responseObject['resultMessage']								= "Could not retrieve tax form instance event data records for $accountID due to a database error: ".$e -> getMessage();
			
			errorLog($e -> getMessage());
	
			die();
		}
	
		return $responseObject;	
	}
	
	function getTotalGainAndLossForUserAccountAndDateAndType($accountID, $taxFormEventID, $isShort, $dateSold, $sid, $dbh)
	{
		$responseObject														= array();
		$responseObject['retrievedSpentAndSold']							= false;
		
		$responseObject['spentToAcquireAmount']								= 0;
		$responseObject['realizedAmount']									= 0;
		
		try
		{		
			$getSpentAndReceived											= $dbh -> prepare("SELECT
	SUM(spentToAcquireAmount) as spentToAcquireAmount,
	SUM(realizedAmount) AS realizedAmount	
FROM
	TaxFormInstanceWorksheetDetailRecord
WHERE
	FK_AccountID = :accountID AND
	FK_TaxFormEventID = :taxFormEventID AND
	isShortTerm = :isShort AND
	LEFT(dateSold, 10) = :dateSold");

			$getSpentAndReceived -> bindValue(':accountID', $accountID);
			$getSpentAndReceived -> bindValue(':taxFormEventID', $taxFormEventID);
			$getSpentAndReceived -> bindValue(':isShort', $isShort);
			$getSpentAndReceived -> bindValue(':dateSold', $dateSold);
						
			if ($getSpentAndReceived -> execute() && $getSpentAndReceived -> rowCount() > 0)
			{
				
				$responseObject['retrievedSpentAndSold']					= true;
				
				$row 														= $getSpentAndReceived -> fetchObject();
				
				$spentToAcquireAmount										= $row -> spentToAcquireAmount;
				$realizedAmount												= $row -> realizedAmount;
				
				$responseObject['spentToAcquireAmount']						= $spentToAcquireAmount;
				$responseObject['realizedAmount']							= $realizedAmount;
				
				errorLog("accountID $accountID taxFormEventID $taxFormEventID isShort $isShort dateSold $dateSold spentToAcquireAmount $spentToAcquireAmount realizedAmount $realizedAmount");						
			}
			else
			{
				$responseObject['resultMessage']							= "No total balance records found for $accountID";
			}
		}
	    catch (PDOException $e) 
	    {
	    	$responseObject['resultMessage']								= "Could not retrieve total balance records for $accountID due to a database error: ".$e -> getMessage();
			
			errorLog($e -> getMessage());
	
			die();
		}
	
		return $responseObject;	
	}
	
	function getTotalTaxLiabilityValueForUserAccount($accountID, $sid, $dbh)
	{
		$responseObject																= array();
		$responseObject['retrievedTaxLiability']									= false;
		
		try
		{		
			$getTaxLiability														= $dbh -> prepare("SELECT
	totalTaxLiabilityAsOfDate,
	totalShortTermTaxLiabilityAsOfDate,
	totalLongTermTaxLiabilityAsOfDate,
	liabilityDate
FROM
	DailyPortfolioTaxLiabilityForUserAccount
WHERE
	FK_AccountID = :accountID
ORDER BY
	liabilityDate DESC
LIMIT 1");

			$getTaxLiability -> bindValue(':accountID', $accountID);
					
			if ($getTaxLiability -> execute() && $getTaxLiability -> rowCount() > 0)
			{
				
				$responseObject['retrievedTaxLiability']							= true;
				
				$row 																= $getTaxLiability -> fetchObject();
				
				$totalTaxLiabilityAsOfDate											= $row -> totalTaxLiabilityAsOfDate;
				$totalShortTermTaxLiabilityAsOfDate									= $row -> totalShortTermTaxLiabilityAsOfDate;
				$totalLongTermTaxLiabilityAsOfDate									= $row -> totalLongTermTaxLiabilityAsOfDate;
				
				$responseObject['totalTaxLiabilityYTD']								= $totalTaxLiabilityAsOfDate;
				$responseObject['asOfDate']											= $row -> liabilityDate;	
				
				$shortTermPercentage												= 0;
				$longTermPercentage													= 0;
				
				if ($totalTaxLiabilityAsOfDate > 0)
				{
					if ($totalShortTermTaxLiabilityAsOfDate > 0)
					{
						if ($totalLongTermTaxLiabilityAsOfDate > 0)
						{
							$shortTermPercentage									= ($totalShortTermTaxLiabilityAsOfDate / $totalTaxLiabilityAsOfDate) * 100;	
							$longTermPercentage										= 100.00 - $shortTermPercentage;
						}
						else
						{
							$shortTermPercentage									= 100;
							$longTermPercentage										= 0;	
						}
					}
					else if ($totalLongTermTaxLiabilityAsOfDate > 0)
					{
						$shortTermPercentage										= 0;	
						$longTermPercentage											= 100;	
					}	
				}

				
				$responseObject['shortTermPercentage']								= $shortTermPercentage;
				$responseObject['longTermPercentage']								= $longTermPercentage;			
			}
			else
			{
				$responseObject['resultMessage']									= "No total tax liability records were found for $accountID";
			}
		}
	    catch (PDOException $e) 
	    {
	    	$responseObject['resultMessage']										= "Could not retrieve total tax liability records for $accountID due to a database error: ".$e -> getMessage();
			
			errorLog($e -> getMessage());
	
			die();
		}
	
		return $responseObject;	
	}
	
	function getTotalPortfolioValueForUserAccount($accountID, $assetTypeID, $sid, $dbh)
	{
		$responseObject														= array();
		$responseObject['retrievedPortfolioValue']							= false;
		
		try
		{		
			$getCryptoTransactionTotals										= $dbh -> prepare("SELECT
	FK_NativeCurrencyTypeID,
	balanceDate,
	assetBalanceForDateAsDecimal,
	assetPriceInNativeCurrencyForDateAsDecimal,
	assetPriceInNativeCurrencyForDateAsInt,
	creationDate
FROM
	DailyPortfolioBalanceForUserAccount
WHERE
	FK_AccountID = :accountID AND
	FK_AssetTypeID = :assetTypeID
ORDER BY
	balanceDate DESC
LIMIT 1");

			$getCryptoTransactionTotals -> bindValue(':accountID', $accountID);
			$getCryptoTransactionTotals -> bindValue(':assetTypeID', $assetTypeID);
						
			if ($getCryptoTransactionTotals -> execute() && $getCryptoTransactionTotals -> rowCount() > 0)
			{
				
				$responseObject['retrievedPortfolioValue']					= true;
				
				$row 														= $getCryptoTransactionTotals -> fetchObject();
				
				$responseObject['totalPortfolioValue']						= $row -> assetPriceInNativeCurrencyForDateAsInt;
				$responseObject['asOfDate']									= $row -> balanceDate;						
			}
			else
			{
				$responseObject['resultMessage']							= "No total balance records found for $accountID";
			}
		}
	    catch (PDOException $e) 
	    {
	    	$responseObject['resultMessage']								= "Could not retrieve total balance records for $accountID due to a database error: ".$e -> getMessage();
			
			errorLog($e -> getMessage());
	
			die();
		}
	
		return $responseObject;	
	}
	
	function getPortfolioValueForUserAccountByCoin($accountID, $balanceDate, $totalPortfolioBalance, $sid, $dbh)
	{
		$responseObject														= array();
		
		// $balanceDate														= date_format($lastPortfolioValueDate, "Y-m-d");
		
		try
		{		
			$getTotalsForUser												= $dbh -> prepare("SELECT
	(assetPriceInNativeCurrencyForDateAsInt / :portfolioTotalBalance) * 100 AS totalAssetBalancePercentageForAsset,
	AssetTypes.assetTypeLabel,
	AssetTypes.description
FROM
	DailyPortfolioBalanceForUserAccount
	INNER JOIN AssetTypes ON DailyPortfolioBalanceForUserAccount.FK_AssetTypeID = AssetTypes.assetTypeID AND AssetTypes.languageCode = 'EN'
WHERE
	FK_AccountID = :accountID AND
	FK_AssetTypeID != 173 AND
	balanceDate = :balanceDate
ORDER BY
	assetPriceInNativeCurrencyForDateAsInt DESC");
	
			$getTotalsForUser -> bindValue(':accountID', $accountID);
			$getTotalsForUser -> bindValue(':portfolioTotalBalance', $totalPortfolioBalance);
			$getTotalsForUser -> bindValue(':balanceDate', $balanceDate);
		
			if ($getTotalsForUser -> execute() && $getTotalsForUser -> rowCount() > 0)
			{
				$countNumber												= 0;
				$otherTotal													= 0;
				
				while ($row = $getTotalsForUser -> fetchObject())
				{
					$totalAssetBalancePercentageForAsset					= $row -> totalAssetBalancePercentageForAsset;
					$assetTypeLabel											= $row -> description;
					
					if ($countNumber < 3 && $totalAssetBalancePercentageForAsset > 0)
					{
						$responseObject['currentBalance'][$assetTypeLabel]	= round($totalAssetBalancePercentageForAsset, 2);	
					}
					else
					{
						$otherTotal											= $otherTotal + $totalAssetBalancePercentageForAsset;	
					}
						
					$countNumber++;
				}
				
				$responseObject['currentBalance']['All Others']				= $otherTotal;		
			}
		}
	    catch (PDOException $e) 
	    {
	    	errorLog($e -> getMessage());
	
			die();
		}
			
		return $responseObject;	
	}
	
	function getPortfolioValueForUserAccountBySourceType($accountID, $balanceDate, $totalPortfolioBalance, $sid, $dbh)
	{
		$responseObject																= array();
		
		try
		{		
			$getTotalsForUser														= $dbh -> prepare("SELECT
	(SUM(assetPriceInNativeCurrencyForDateAsInt) / :portfolioTotalBalance) * 100 AS totalAssetBalancePercentageForSource,
	TransactionSources.transactionSourceLabel
FROM
	DailyPortfolioBalanceForUserAccount
	INNER JOIN TransactionSources ON DailyPortfolioBalanceForUserAccount.FK_TransactionSource = TransactionSources.transactionSourceID AND TransactionSources.languageCode = 'EN'
WHERE
	FK_AccountID = :accountID AND
	FK_AssetTypeID != 173 AND
	balanceDate = :balanceDate
GROUP BY
	TransactionSources.transactionSourceLabel
ORDER BY
	SUM(assetPriceInNativeCurrencyForDateAsInt) DESC");
	
			$getTotalsForUser -> bindValue(':accountID', $accountID);
			$getTotalsForUser -> bindValue(':portfolioTotalBalance', $totalPortfolioBalance);
			$getTotalsForUser -> bindValue(':balanceDate', $balanceDate);
		
			if ($getTotalsForUser -> execute() && $getTotalsForUser -> rowCount() > 0)
			{
				$countNumber															= 0;
				$otherTotal															= 0;
				
				while ($row = $getTotalsForUser -> fetchObject())
				{
					$totalAssetBalanceForSource										= $row -> totalAssetBalancePercentageForSource;
					$transactionSourceLabel											= $row -> transactionSourceLabel;
					
					if ($countNumber < 3 && $totalAssetBalanceForSource > 0)
					{
						$responseObject['currentBalance'][$transactionSourceLabel]	= round($totalAssetBalanceForSource, 2);	
					}
					else
					{
						$otherTotal													= $otherTotal + $totalAssetBalanceForSource;	
					}
						
					$countNumber++;
				}
				
				$responseObject['currentBalance']['All Others']						= round($otherTotal, 2);		
			}
		}
	    catch (PDOException $e) 
	    {
	    	errorLog($e -> getMessage());
	
			die();
		}
			
		return $responseObject;	
	}
	
	function generateDateArrayForYearForPortfolioView($year, $veiwType)
	{
		$responseObject														= array();
		
		$veiwType															= strtolower($veiwType);
		
		$startDate															= new DateTime("$year-01-01");
		$endDate																= new DateTime("$year-12-31");
		$testDate															= $startDate;
		
		$dateMod																= "";
		
		if (strcasecmp($veiwType, "day") == 0)
		{
			$dateMod															= "+1 day";	
		}
		else if (strcasecmp($veiwType, "week") == 0)
		{
			$dateMod															= "+7 day";	
		}
		else if (strcasecmp($veiwType, "month") == 0)
		{
			$dateMod															= "+1 month";	
		}
		
		while ($testDate <= $endDate)
		{
			// @datehere
			$responseObject[]												= date_format($testDate, "Y-m-d");
			$testDate -> modify($dateMod);
		}
		
		return $responseObject;
	}
	
	function getTotalPortfolioValueForUserAccountForDateArray($accountID, $dateArray, $sid, $dbh)
	{
		$responseObject														= array();
		
		try
		{		
			$getCryptoTransactionTotals										= $dbh -> prepare("SELECT
	balanceDate,
	assetBalanceForDateAsDecimal,
	assetPriceInNativeCurrencyForDateAsDecimal,
	assetPriceInNativeCurrencyForDateAsInt
FROM
	DailyPortfolioBalanceForUserAccount
WHERE
	FK_AccountID = :accountID AND
	FK_AssetTypeID = 173 AND
	balanceDate = :balanceDate");
	
			foreach ($dateArray as $balanceDate)
			{
				errorLog($balanceDate);
				
				$getCryptoTransactionTotals -> bindValue(':accountID', $accountID);
				$getCryptoTransactionTotals -> bindValue(':balanceDate', $balanceDate);
						
				if ($getCryptoTransactionTotals -> execute() && $getCryptoTransactionTotals -> rowCount() > 0)
				{
					$row 														= $getCryptoTransactionTotals -> fetchObject();
					
					$balanceForDateArray										= array();
					
					$dateObject													= new DateTime($balanceDate);
					$formattedBalanceDate										= date_format($dateObject, "Y/m/d");
					
					$balanceForDateArray['date']								= $formattedBalanceDate;
					$balanceForDateArray['value']								= $row -> assetPriceInNativeCurrencyForDateAsInt;	
					
					$responseObject[]											= $balanceForDateArray;				
				}	
			}
		}
	    catch (PDOException $e) 
	    {
	    	$responseObject['resultMessage']									= "Could not retrieve total balance records for $accountID due to a database error: ".$e -> getMessage();
			
			errorLog($e -> getMessage());
	
			die();
		}
	
		return $responseObject;	
	}
	
	function getTotalPortfolioTaxLiabilityForUserAccountForDateArray($accountID, $dateArray, $nativeCurrencyTypeID, $sid, $dbh)
	{
		$responseObject														= array();
		
		try
		{		
			$getCryptoTransactionTotals										= $dbh -> prepare("SELECT
	SUM(dailyShortGainOrLoss) AS dailyShortGainOrLoss,
	SUM(dailyLongGainOrLoss) AS dailyLongGainOrLoss
FROM
	DailyPortfolioTaxLiabilityForUserAccount
WHERE
	FK_AccountID = :accountID AND
	FK_NativeCurrencyTypeID = :nativeCurrencyTypeID AND
	LEFT(liabilityDate, 10) > '2018-12-31' AND
	LEFT(liabilityDate, 10) < LEFT(:liabilityDate, 10)");
	
			foreach ($dateArray as $liabilityDate)
			{
				errorLog($liabilityDate);
				
				$getCryptoTransactionTotals -> bindValue(':accountID', $accountID);
				$getCryptoTransactionTotals -> bindValue(':nativeCurrencyTypeID', $nativeCurrencyTypeID);
				$getCryptoTransactionTotals -> bindValue(':liabilityDate', $liabilityDate);
						
				if ($getCryptoTransactionTotals -> execute() && $getCryptoTransactionTotals -> rowCount() > 0)
				{
					$row 													= $getCryptoTransactionTotals -> fetchObject();
					
					$shortArray												= array();
					$longArray												= array();
					
					$dateObject												= new DateTime($liabilityDate);
					$formattedLiabilityDate									= date_format($dateObject, "Y/m/d");
					
					$shortArray['date']										= $formattedLiabilityDate;
					$shortArray['value']									= $row -> dailyShortGainOrLoss;
					
					$longArray['date']										= $formattedLiabilityDate;
					$longArray['value']										= $row -> dailyLongGainOrLoss;	
					
					$responseObject['short'][]								= $shortArray;	
					$responseObject['long'][]								= $longArray;		
				}	
			}
		}
	    catch (PDOException $e) 
	    {
	    	$responseObject['resultMessage']								= "Could not retrieve tax liability record for $accountID due to a database error: ".$e -> getMessage();
			
			errorLog($e -> getMessage());
	
			die();
		}
	
		return $responseObject;	
	}
	
	function setCryptoDailyGainOrLossForUserAccountAndAllAssets($accountID, $startDate, $endDate, $taxFormEventID, $shortTermGainTaxPercentage, $longTermGainTaxPercentage, $maxAllowedLoss, $globalCurrentDate, $sid, $dbh)
	{
		$responseObject														= array();
		$responseObject['retrievedTransactions']							= false;
		
		// change in structure
		
		$testDate															= new DateTime($startDate); // start with date of first transaction
		$endingDate															= new DateTime($endDate);
		
		$totalShortGainsYTD													= 0;
		$totalLongGainsYTD													= 0;
		
		$totalShortLossesYTD												= 0;
		$totalLongLossesYTD													= 0;
		
		$totalLossAsOfDate													= 0;
		$adjustedTotalLoss													= 0;
		
		$shortGainOrLossAsOfDate											= 0;
		$longGainOrLossAsOfDate												= 0;
		
		$dailyShortTermTaxLiability											= 0;
		$dailyLongTermTaxLiability											= 0;
		$totalShortTermTaxLiabilityAsOfDate									= 0;
		$totalLongTermTaxLiabilityAsOfDate									= 0;
		$totalTaxLiabilityAsOfDate											= 0;
		
		// begin while loop from that date to now
		
		while ($testDate < $endingDate)
		{
			$formattedTestDate												= date_format($testDate, "Y-m-d");
			
			$dailyLongGainOrLoss											= 0;
			$dailyShortGainOrLoss											= 0;
			
			$dailyShortTermTaxLiability										= 0;
			$dailyLongTermTaxLiability										= 0;
		
			
			// get sum of all transactions for each date
			
			$shortTransactionDataForDate									= getTotalGainAndLossForUserAccountAndDateAndType($accountID, $taxFormEventID, 1, $formattedTestDate, $sid, $dbh);
			$longTransactionDataForDate										= getTotalGainAndLossForUserAccountAndDateAndType($accountID, $taxFormEventID, 0, $formattedTestDate, $sid, $dbh);
			
			// add that sum to the total existing balance
			
			if ($shortTransactionDataForDate['retrievedSpentAndSold'] == true)
			{
				$dailyShortGainOrLoss										= $shortTransactionDataForDate['realizedAmount'] - $shortTransactionDataForDate['spentToAcquireAmount'];
				
				$shortGainOrLossAsOfDate									= $shortGainOrLossAsOfDate + $dailyShortGainOrLoss;
				
				if ($dailyShortGainOrLoss >= 0)
				{
					$totalShortGainsYTD										= $totalShortGainsYTD + $dailyShortGainOrLoss;
					$dailyShortTermTaxLiability								= $dailyShortGainOrLoss * $shortTermGainTaxPercentage;
					$totalShortTermTaxLiabilityAsOfDate						= $totalShortTermTaxLiabilityAsOfDate + $dailyShortTermTaxLiability;
					$totalTaxLiabilityAsOfDate								= $totalTaxLiabilityAsOfDate + $dailyShortTermTaxLiability;
				}
				else
				{
					$totalShortLossesYTD									= $totalShortLossesYTD + $dailyShortGainOrLoss;
					
					$totalLossAsOfDate										= $totalLossAsOfDate + $dailyShortGainOrLoss;
					
					if ($adjustedTotalLoss < $maxAllowedLoss)
					{
						$adjustedTotalLoss									= $adjustedTotalLoss + ($dailyShortGainOrLoss * -1);
					}	
				}
			}
			
			if ($longTransactionDataForDate['retrievedSpentAndSold'] == true)
			{
				$dailyLongGainOrLoss										= $longTransactionDataForDate['realizedAmount'] - $longTransactionDataForDate['spentToAcquireAmount'];
				
				$longGainOrLossAsOfDate										= $longGainOrLossAsOfDate + $dailyLongGainOrLoss;
				
				if ($dailyLongGainOrLoss >= 0)
				{
					$totalLongGainsYTD										= $totalLongGainsYTD + $dailyLongGainOrLoss;
					$dailyLongTermTaxLiability								= $dailyLongGainOrLoss * $longTermGainTaxPercentage;
					$totalLongTermTaxLiabilityAsOfDate						= $totalLongTermTaxLiabilityAsOfDate + $dailyLongTermTaxLiability;
					$totalTaxLiabilityAsOfDate								= $totalTaxLiabilityAsOfDate + $dailyLongTermTaxLiability;	
				}
				else
				{
					$totalLongLossesYTD										= $totalLongLossesYTD + $dailyLongGainOrLoss;
					
					$totalLossAsOfDate										= $totalLossAsOfDate + $dailyLongGainOrLoss;
					
					if ($adjustedTotalLoss < $maxAllowedLoss)
					{
						$adjustedTotalLoss									= $adjustedTotalLoss + ($dailyLongGainOrLoss * -1);
					}	
				}
			}
			
			
			if ($adjustedTotalLoss > $maxAllowedLoss)
			{
				$adjustedTotalLoss											= $maxAllowedLoss;
			}
			
			writeDailyTaxLiabilityRecordForUserAccount($accountID, $formattedTestDate, 2, $dailyShortGainOrLoss, $dailyLongGainOrLoss, $totalShortGainsYTD, $totalLongGainsYTD, $totalShortLossesYTD, $totalLongLossesYTD, $totalLossAsOfDate, $adjustedTotalLoss, $shortGainOrLossAsOfDate, $longGainOrLossAsOfDate, $dailyShortTermTaxLiability, $dailyLongTermTaxLiability, $totalShortTermTaxLiabilityAsOfDate, $totalLongTermTaxLiabilityAsOfDate, $totalTaxLiabilityAsOfDate, $globalCurrentDate, $sid, $dbh);	
		
			$testDate -> modify('+1 day');
			
			// update 1ToM record for last date checked so that we can start there next time 	
		}
	
		return $responseObject;
	}
	
	function setCryptoDailyPortfolioBalanceForUserAccountTransactionSourceAndAllAssets($accountID, $startDate, $transactionSourceID, $globalCurrentDate, $sid, $dbh)
	{
		$responseObject														= array();
		$responseObject['retrievedTransactions']								= false;
		
		// change in structure
		// change in structure
		$testDate															= $startDate;
		
		if (is_object($testDate) == false)
		{
			$testDate														= new DateTime($startDate); // start with date of first transaction	
		}
		
		$endingDate															= new DateTime('now');
		
		$endingDate -> modify('-1 day');
		
		$currentAssetAmountTotal												= 0;
		
		// begin while loop from that date to now
		
		while ($testDate < $endingDate)
		{
			// get sum of all transactions for each date
			
			$transactionDataForDate											= getCryptoTotalForAllAssetsForUserForTransactionSourceForDate($accountID, $transactionSourceID, $testDate, $dbh);
			
			// add that sum to the total existing balance
		
			$currentAssetAmountTotal											= $transactionDataForDate['assetPriceInNativeCurrencyForDateAsInt'];
		
			if ($currentAssetAmountTotal > 0)
			{
				$responseObject['retrievedTransactions']						= true;
			}
		
			writeDailyCryptoBalanceRecordForUserAccountAssetAndTransactionSource($accountID, 0, $currentAssetAmountTotal, 173, $transactionSourceID, 2, $testDate, $globalCurrentDate, $sid, $dbh);	
		
			$testDate -> modify('+1 day');
			
		
			// update 1ToM record for last date checked so that we can start there next time 	
		}
	
		return $responseObject;
	}
	
	function setCryptoDailyPortfolioBalanceForUserAccountAllTransactionSourcesAndAllAssets($accountID, $startDate, $globalCurrentDate, $sid, $dbh)
	{
		$responseObject														= array();
		$responseObject['retrievedTransactions']							= false;
		
		// change in structure
		
		$testDate															= new DateTime($startDate); // start with date of first transaction
		$endingDate															= new DateTime('now');
		
		$endingDate -> modify('-1 day');
		
		$currentAssetAmountTotal											= 0;
		
		// begin while loop from that date to now
		
		while ($testDate < $endingDate)
		{
			// get sum of all transactions for each date
			
			$transactionDataForDate											= getCryptoTotalForAllAssetsForUserForAllTransactionSourcesForDate($accountID, $testDate, $dbh);
			
			// add that sum to the total existing balance
		
			$currentAssetAmountTotal										= $transactionDataForDate['assetPriceInNativeCurrencyForDateAsInt'];
		
			if ($currentAssetAmountTotal > 0)
			{
				$responseObject['retrievedTransactions']					= true;
			}
		
			writeDailyCryptoBalanceRecordForUserAccountAssetAndTransactionSource($accountID, 0, $currentAssetAmountTotal, 173, 0, 2, $testDate, $globalCurrentDate, $sid, $dbh);	
		
			$testDate -> modify('+1 day');
			
		
			// update 1ToM record for last date checked so that we can start there next time 	
		}
	
		return $responseObject;
	}
	
	function createCoinbaseOauthValidationHash($userAccountID, $walletType, $globalCurrentDate, $sid, $dbh)
	{
		try
		{	
			$insertValidationRequest	= $dbh->prepare("INSERT INTO OAuthConnectionValidationHash
	(
		requestDate,
		requestStatus,
		sid,
		encryptedUserAccountID,
		encryptedEmailAddress,
		encryptedMobileNumber,
		encryptedValidationHash,
		encryptedSMSValidationCode,
		originalRequestDate,
		FK_WalletTypeID
	)
	VALUES
	(
		:requestDate,
		:requestStatus,
		:sid,
		AES_ENCRYPT(:FK_UserAccountID, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
		AES_ENCRYPT(:emailAddress, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
		AES_ENCRYPT(returnNumericOnly(:mobilePhone), UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
		AES_ENCRYPT(:validationHash, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
		AES_ENCRYPT(:smsValidationCode, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
		:requestDate,
		:walletTypeID
	)");
		
			$userObject							= new UserInformationObject();
			$responseObject						= $userObject -> instantiateUserObjectByUserAccountID($userAccountID, $dbh, $sid);
		
			$emailAddress					 	= $userObject -> getEmailAddress();
			$phoneNumber					 	= $userObject -> getPhoneNumber();
		
			$validationHash						= md5(md5($userObject -> getNameObject() -> getFirstName()." ".$userObject -> getNameObject() -> getMiddleName()." ".$userObject -> getNameObject() -> getLastName()." ".$emailAddress." ".$globalCurrentDate." ".$sid));
						
			$smsValidationCode					= generateRandomString(6);
						
			$_SESSION['smsVerificationCode'] 	= $smsValidationCode;
		
		
		
			$insertValidationRequest -> bindValue(':FK_UserAccountID', $userAccountID);
			$insertValidationRequest -> bindValue(':validationHash', $validationHash);
			$insertValidationRequest -> bindValue(':smsValidationCode', $smsValidationCode);
			$insertValidationRequest -> bindValue(':emailAddress', $emailAddress);
			$insertValidationRequest -> bindValue(':mobilePhone', $phoneNumber);
			$insertValidationRequest -> bindValue(':requestDate', $globalCurrentDate);
			$insertValidationRequest -> bindValue(':requestStatus', 1);
			$insertValidationRequest -> bindValue(':sid', $sid);
			$insertValidationRequest -> bindValue(':walletTypeID', $walletType);
			
			if ($insertValidationRequest -> execute())
			{
				errorLog("Success: createCoinbaseOauthValidationHash for $userAccountID, $walletType, $globalCurrentDate, $sid $validationHash");
			}
			else
			{
				errorLog("ERROR: could note createCoinbaseOauthValidationHash for $userAccountID, $walletType, $globalCurrentDate, $sid $validationHash.  INSERT INTO OAuthConnectionValidationHash
	(
		requestDate,
		requestStatus,
		sid,
		encryptedUserAccountID,
		encryptedEmailAddress,
		encryptedMobileNumber,
		encryptedValidationHash,
		encryptedSMSValidationCode,
		originalRequestDate,
		FK_WalletTypeID
	)
	VALUES
	(
		:requestDate,
		:requestStatus,
		:sid,
		AES_ENCRYPT($userAccountID, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
		AES_ENCRYPT('$emailAddress', UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
		AES_ENCRYPT(returnNumericOnly('$mobilePhone'), UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
		AES_ENCRYPT('$validationHash', UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
		AES_ENCRYPT('$smsValidationCode', UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
		'$globalCurrentDate',
		$walletType
	)");	
			}
						
			// if ($validationType == 1 && !empty($phoneCell) && strlen($phoneCell) > 0)
			// {
			// 	$textBody = "Thank you for creating a ProfitStance account!  Your validation code is $smsValidationCode. Once you have validated your account, use your email address to log in.";
				
			// 	$returnValue	= generateTextMessage($phoneCell, $newAccountID, $newAccountID, $textBody);
					
			// 	$returnValue2 	= sendValidationNotificationEmail($emailAddress, $participantName, $validationHash, $serverURLRoot);
					
			// 	if ($returnValue == 1 && $returnValue2 == 0)
			// 	{
					$returnValue = -2;
			// 	}
			// }
			// else
			// {
			// 	$returnValue = sendValidationEmail($study2MemberID, $emailAddress, $participantName, $validationHash, $serverURLRoot);	
			// }

			$dbh 				= null;	
		}
	    catch (PDOException $e) 
	    {
	    	$returnValue 		= -1;	
			
			errorLog($e->getMessage());
	
			die();
		}
		return $validationHash;
	}
	
	function createCryptoTransactionBalanceRecord($liuAccountID, $userAccountID, $balanceAsOfTransactionID, $receiveTransactionID, $remainingAssetAmount, $receiveSpotPriceUSD, $globalCurrentDate, $sid, $dbh)
	{
		errorLog("createCryptoTransactionBalanceRecord");
		
		$returnValue						= 0;
		
		try
		{		
			$insertCryptoBalanceRecord		= $dbh -> prepare("INSERT INTO CryptoBalanceRecords
(
	FK_AccountID,
	FK_AuthorID,
	FK_BalanceAsOfTransactionID,
	FK_ReceiveTransactionID,
	remainingAssetAmount,
	receiveSpotPriceUSD,
	sid
)
VALUES
(
	:FK_AccountID,
	:FK_AuthorID,
	:FK_BalanceAsOfTransactionID,
	:FK_ReceiveTransactionID,
	:remainingAssetAmount,
	:receiveSpotPriceUSD,
	:sid
)");

			$insertCryptoBalanceRecord -> bindValue(':FK_AccountID', $userAccountID);
			$insertCryptoBalanceRecord -> bindValue(':FK_AuthorID', $liuAccountID);
			$insertCryptoBalanceRecord -> bindValue(':FK_BalanceAsOfTransactionID', $balanceAsOfTransactionID);
			$insertCryptoBalanceRecord -> bindValue(':FK_ReceiveTransactionID', $receiveTransactionID);
			$insertCryptoBalanceRecord -> bindValue(':remainingAssetAmount', $remainingAssetAmount);
			$insertCryptoBalanceRecord -> bindValue(':receiveSpotPriceUSD', $receiveSpotPriceUSD);
			$insertCryptoBalanceRecord -> bindValue(':sid', $sid);
			
			if ($insertCryptoBalanceRecord -> execute())
			{
				$returnValue 				= $dbh -> lastInsertId();
			}
			else
			{
				errorLog("ERROR: could not insertCryptoBalanceRecord for $liuAccountID, $userAccountID, $balanceAsOfTransactionID, $receiveTransactionID, $remainingAssetAmount, $receiveSpotPriceUSD, $globalCurrentDate, $sid");
			}	
		}
	    catch (PDOException $e) 
	    {
	    	$returnValue 					= -1;	
			
			errorLog($e -> getMessage());
	
			die();
		}
		
		return $returnValue;		
	}
	
	function createCryptoTransactionGroupingRecord($userAccountID, $authorID, $inboundAssetTransactionID, $outboundAssetTransactionID, $spendTransactionType, $spendTransactionTypeLabel, $subTransactionAmount, $receiveTransactionDate, $spendTransactionDate, $receiveSpotPriceUSD, $sellSpotPriceUSD, $creationDate, $sid, $dbh)
	{
		$returnValue						= 0;
		
		try
		{		
			$insertCryptoGroupingRecord		= $dbh -> prepare("INSERT INTO OutboundTransactionSourceGrouping
(
	FK_InboundAssetTransactionID,
	FK_OutboundAssetTransactionID,
	FK_SpendTransactionType,
	subTransactionAmount,
	receiveTransactionDate,
	spendTransactionDate,
	receiveSpotPriceUSD,
	sellSpotPriceUSD,
	FK_GainTypeID,
	profitOrLossAmountUSD,
	spentToAcquireAmount,
	realizedAmount,
	creationDate,
	FK_AuthorID,
	FK_AccountID,
	sid
)
VALUES
(
	:FK_InboundAssetTransactionID,
	:FK_OutboundAssetTransactionID,
	:FK_SpendTransactionType,
	:subTransactionAmount,
	:receiveTransactionDate,
	:spendTransactionDate,
	:receiveSpotPriceUSD,
	:sellSpotPriceUSD,
	:FK_GainTypeID,
	:profitOrLossAmountUSD,
	:spentToAcquireAmount,
	:realizedAmount,
	:creationDate,
	:FK_AuthorID,
	:FK_AccountID,
	:sid
)");
			
			$cryptoGroupedSpendTransaction	= new CryptoGroupedSpendTransaction($inboundAssetTransactionID, $outboundAssetTransactionID, $userAccountID, $authorID, $spendTransactionType, $spendTransactionTypeLabel, $subTransactionAmount, $receiveTransactionDate, $spendTransactionDate, $receiveSpotPriceUSD, $sellSpotPriceUSD, $creationDate, $sid, $dbh);
			
			
/*
			errorLog("<BR><BR><B>createCryptoTransactionGroupingRecord</B><BR><BR>INSERT INTO OutboundTransactionSourceGrouping
(
	FK_InboundAssetTransactionID,
	FK_OutboundAssetTransactionID,
	FK_SpendTransactionType,
	subTransactionAmount,
	receiveTransactionDate,
	spendTransactionDate,
	receiveSpotPriceUSD,
	sellSpotPriceUSD,
	FK_GainTypeID,
	profitOrLossAmountUSD,
	spentToAcquireAmount,
	realizedAmount,
	creationDate,
	FK_AuthorID,
	FK_AccountID,
	sid
)
VALUES
(
	$inboundAssetTransactionID,
	$outboundAssetTransactionID,
	$spendTransactionType,
	$subTransactionAmount,
	'$receiveTransactionDate',
	'$spendTransactionDate',
	$receiveSpotPriceUSD,
	$sellSpotPriceUSD,
	".$cryptoGroupedSpendTransaction -> getGainTypeID().",
	".$cryptoGroupedSpendTransaction -> getProfitOrLossAmountUSD().",
	".$cryptoGroupedSpendTransaction -> getSpentToAcquireAmount().",
	".$cryptoGroupedSpendTransaction -> getRealizedAmount().",
	'$creationDate',
	$authorID,
	$userAccountID,
	'$sid'
)<BR><BR>");
*/

			$insertCryptoGroupingRecord -> bindValue(':FK_InboundAssetTransactionID', $inboundAssetTransactionID);
			$insertCryptoGroupingRecord -> bindValue(':FK_OutboundAssetTransactionID', $outboundAssetTransactionID);
			$insertCryptoGroupingRecord -> bindValue(':FK_SpendTransactionType', $spendTransactionType);
			$insertCryptoGroupingRecord -> bindValue(':subTransactionAmount', $subTransactionAmount);
			$insertCryptoGroupingRecord -> bindValue(':receiveTransactionDate', $receiveTransactionDate);
			$insertCryptoGroupingRecord -> bindValue(':spendTransactionDate', $spendTransactionDate);
			$insertCryptoGroupingRecord -> bindValue(':receiveSpotPriceUSD', $receiveSpotPriceUSD);
			$insertCryptoGroupingRecord -> bindValue(':sellSpotPriceUSD', $sellSpotPriceUSD);
			$insertCryptoGroupingRecord -> bindValue(':FK_GainTypeID', $cryptoGroupedSpendTransaction -> getGainTypeID());
			$insertCryptoGroupingRecord -> bindValue(':profitOrLossAmountUSD', $cryptoGroupedSpendTransaction -> getProfitOrLossAmountUSD());
			$insertCryptoGroupingRecord -> bindValue(':spentToAcquireAmount', $cryptoGroupedSpendTransaction -> getSpentToAcquireAmount());
			$insertCryptoGroupingRecord -> bindValue(':realizedAmount', $cryptoGroupedSpendTransaction -> getRealizedAmount());
			$insertCryptoGroupingRecord -> bindValue(':creationDate', $creationDate);
			$insertCryptoGroupingRecord -> bindValue(':FK_AccountID', $userAccountID);
			$insertCryptoGroupingRecord -> bindValue(':FK_AuthorID', $authorID);
			$insertCryptoGroupingRecord -> bindValue(':sid', $sid);
			
			if ($insertCryptoGroupingRecord -> execute())
			{
				$returnValue 				= 1; // there is no auto increment - return 1 to show it worked
			}
			else
			{
				errorLog("ERROR: could not insertCryptoGroupingRecord for $userAccountID, $authorID, $inboundAssetTransactionID, $outboundAssetTransactionID, $subTransactionAmount, $spendTransactionDate, $creationDate, $sid");
			}	
		}
	    catch (PDOException $e) 
	    {
	    		$returnValue 					= -1;	
			
			errorLog($e -> getMessage());
	
			die();
		}
		
		return $returnValue;		
	}
	
	function createCryptoTransactionRecord($userAccountID, $liuAccountID, $userEncryptionKey, $globalTransactionIdentificationRecordID, $transactionTypeID, $transactionSourceID, $assetTypeID, $spotPriceCurrencyTypeID, $sourceWalletID, $destinationWalletID, $creationDate, $transactionDate, $transactionTimestamp, $vendorTransactionID, $btcQuantityTransacted, $usdQuantityTransacted, $spotPriceAtTimeOfTransaction, $btcPriceAtTimeOfTransaction, $usdTransactionAmountWithFees, $usdFeeAmount, $unspentTransactionTotal, $providerNotes, $isDebit, $globalCurrentDate, $sid, $dbh)
	{
		errorLog("Beginning createCryptoTransactionRecord $userAccountID, $liuAccountID, $globalTransactionIdentificationRecordID, $transactionTypeID, $transactionSourceID, $assetTypeID, $spotPriceCurrencyTypeID, $sourceWalletID, $destinationWalletID, $creationDate, $transactionDate, $vendorTransactionID, $btcQuantityTransacted, $usdQuantityTransacted, $spotPriceAtTimeOfTransaction, $btcPriceAtTimeOfTransaction, $usdTransactionAmountWithFees, $usdFeeAmount, $unspentTransactionTotal, $providerNotes, $isDebit, $globalCurrentDate, $sid");
		
		// insert the transaction
		// if inbound - unspent transaction total matches the transaction amount minus fees - how do I determine fee amount?
		// if outbound - unspent transaction total = 0
		// if outbound - get ID of last inbound transaction with unspent amount - generate linking transaction
		
		$responseObject										= array();
		$responseObject['createdTransactionRecord']			= false;
		$responseObject['transactionRecordExisted']			= false;
		
		$getNativeAndCommonTransactionRecordIDsResult		= getNativeAndCommonTransactionRecordIDsForGlobalTransactionIdentificationRecordID($userAccountID, $assetTypeID, $vendorTransactionID, $transactionSourceID, $globalTransactionIdentificationRecordID, $globalCurrentDate, $sid, $dbh);
		
		errorLog("INSERT INTO Transactions
		(
			FK_GlobalTransactionIdentificationRecordID,
			FK_AuthorID,
			FK_AccountID,
			FK_TransactionTypeID,
			FK_TransactionSourceID,
			FK_AssetTypeID,
			FK_SourceAddressID,
			FK_DestinationAddressID,
			FK_SpotPriceCurrencyID,
			creationDate,
			transactionDate,
			transactionTimestamp,
			vendorTransactionID,
			btcQuantityTransacted,
			usdQuantityTransacted,
			spotPriceAtTimeOfTransaction,
			btcPriceAtTimeOfTransaction,
			usdTransactionAmountWithFees,
			usdFeeAmount,
			unspentTransactionTotal,
			encryptedProviderNotes,
			isDebit,
			encryptedSid
		)
		VALUES
		(
			$globalTransactionIdentificationRecordID,
			$liuAccountID,
			$userAccountID,
			$transactionTypeID,
			$transactionSourceID,
			$assetTypeID,
			$sourceWalletID,
			$destinationWalletID,
			$spotPriceCurrencyTypeID,
			'$creationDate',
			'$transactionDate',
			$transactionTimestamp,
			AES_ENCRYPT('$vendorTransactionID', UNHEX(SHA2($userEncryptionKey,512))),
			$btcQuantityTransacted,
			$usdQuantityTransacted,
			$spotPriceAtTimeOfTransaction,
			$btcPriceAtTimeOfTransaction,
			$usdTransactionAmountWithFees,
			$usdFeeAmount,
			$unspentTransactionTotal,
			AES_ENCRYPT('$providerNotes', UNHEX(SHA2($userEncryptionKey,512))),
			$isDebit,
			AES_ENCRYPT('$sid', UNHEX(SHA2($userEncryptionKey,512)))
		)");
		
/*
		$responseObject['foundNativeTransactionRecordIDForGlobalTransactionIdentifier']				= false;
		$responseObject['foundCommonTransactionRecordIDForGlobalTransactionIdentifier']				= false;
		$responseObject['foundGlobalTransactionIdentifier']
*/
		
		if ($getNativeAndCommonTransactionRecordIDsResult['foundGlobalTransactionIdentifier'] == true)
		{
			errorLog("commonTransactionID: ". $getNativeAndCommonTransactionRecordIDsResult['commonTransactionRecordID']);
			
			$commonTransactionID							= $getNativeAndCommonTransactionRecordIDsResult['commonTransactionRecordID'];
			
			if (empty($commonTransactionID))
			{
				errorLog("transactionRecord does not exist");
				
				try
				{	
					$createTransactionRecord				= $dbh->prepare("INSERT INTO Transactions
		(
			FK_GlobalTransactionIdentificationRecordID,
			FK_AuthorID,
			FK_AccountID,
			FK_TransactionTypeID,
			FK_TransactionSourceID,
			FK_AssetTypeID,
			FK_SourceAddressID,
			FK_DestinationAddressID,
			FK_SpotPriceCurrencyID,
			creationDate,
			transactionDate,
			transactionTimestamp,
			vendorTransactionID,
			btcQuantityTransacted,
			usdQuantityTransacted,
			spotPriceAtTimeOfTransaction,
			btcPriceAtTimeOfTransaction,
			usdTransactionAmountWithFees,
			usdFeeAmount,
			unspentTransactionTotal,
			encryptedProviderNotes,
			isDebit,
			encryptedSid
		)
		VALUES
		(
			:FK_GlobalTransactionIdentificationRecordID,
			:FK_AuthorID,
			:FK_AccountID,
			:FK_TransactionTypeID,
			:FK_TransactionSourceID,
			:FK_AssetTypeID,
			:FK_SourceAddressID,
			:FK_DestinationAddressID,
			:FK_SpotPriceCurrencyID,
			:creationDate,
			:transactionDate,
			:transactionTimestamp,
			AES_ENCRYPT(:vendorTransactionID, UNHEX(SHA2(:userEncryptionKey,512))),
			:btcQuantityTransacted,
			:usdQuantityTransacted,
			:spotPriceAtTimeOfTransaction,
			:btcPriceAtTimeOfTransaction,
			:usdTransactionAmountWithFees,
			:usdFeeAmount,
			:unspentTransactionTotal,
			AES_ENCRYPT(:providerNotes, UNHEX(SHA2(:userEncryptionKey,512))),	
			:isDebit,
			AES_ENCRYPT(:sid, UNHEX(SHA2(:userEncryptionKey,512)))
		)");
				
					$createTransactionRecord -> bindValue(':FK_GlobalTransactionIdentificationRecordID', $globalTransactionIdentificationRecordID);
					$createTransactionRecord -> bindValue(':FK_AuthorID', $liuAccountID);
					$createTransactionRecord -> bindValue(':FK_AccountID', $userAccountID);
					$createTransactionRecord -> bindValue(':FK_TransactionTypeID', $transactionTypeID);
					$createTransactionRecord -> bindValue(':FK_TransactionSourceID', $transactionSourceID);
					$createTransactionRecord -> bindValue(':FK_AssetTypeID', $assetTypeID);
					$createTransactionRecord -> bindValue(':FK_SourceAddressID', $sourceWalletID);
					$createTransactionRecord -> bindValue(':FK_DestinationAddressID', $destinationWalletID);
					$createTransactionRecord -> bindValue(':FK_SpotPriceCurrencyID', $spotPriceCurrencyTypeID);
					$createTransactionRecord -> bindValue(':creationDate', $creationDate);
					$createTransactionRecord -> bindValue(':transactionDate', $transactionDate);
					$createTransactionRecord -> bindValue(':transactionTimestamp', $transactionTimestamp);
					$createTransactionRecord -> bindValue(':vendorTransactionID', $vendorTransactionID);
					$createTransactionRecord -> bindValue(':btcQuantityTransacted', $btcQuantityTransacted);
					$createTransactionRecord -> bindValue(':usdQuantityTransacted', $usdQuantityTransacted);
					$createTransactionRecord -> bindValue(':spotPriceAtTimeOfTransaction', $spotPriceAtTimeOfTransaction);
					$createTransactionRecord -> bindValue(':btcPriceAtTimeOfTransaction', $btcPriceAtTimeOfTransaction);
					$createTransactionRecord -> bindValue(':usdTransactionAmountWithFees', $usdTransactionAmountWithFees);
					$createTransactionRecord -> bindValue(':usdFeeAmount', $usdFeeAmount);
					$createTransactionRecord -> bindValue(':unspentTransactionTotal', $unspentTransactionTotal);
					$createTransactionRecord -> bindValue(':providerNotes', $providerNotes);
					$createTransactionRecord -> bindValue(':isDebit', $isDebit);
					$createTransactionRecord -> bindValue(':sid', $sid);
					$createTransactionRecord -> bindValue(':userEncryptionKey', $userEncryptionKey);
						
					if ($createTransactionRecord -> execute())
					{
						$newTransactionID									= $dbh -> lastInsertId();
						
						$responseObject['createdTransactionRecord']			= true;
						$responseObject['commonTransactionRecordID']			= $newTransactionID;
						
						$setCommonTransactionRecordIDResult					= setCommonTransactionRecordIDForGlobalTransactionIndentificationRecordID($liuAccountID, $newTransactionID, $globalTransactionIdentificationRecordID, $globalCurrentDate, $sid, $dbh);
					
						if ($setCommonTransactionRecordIDResult['updatedCommonTransactionRecordID'] == true)
						{
							errorLog("Set common transaction record ID $newTransactionID for $globalTransactionIdentificationRecordID");
						}
							
						errorLog("<BR><BR>Created transaction record: createCryptoTransactionRecord<BR><BR>$userAccountID, $liuAccountID, $globalTransactionIdentificationRecordID, $transactionTypeID, $transactionSourceID, $assetTypeID, $spotPriceCurrencyTypeID, $sourceWalletID, $destinationWalletID, $creationDate, $transactionDate, $vendorTransactionID, $btcQuantityTransacted, $usdQuantityTransacted, $spotPriceAtTimeOfTransaction, $btcPriceAtTimeOfTransaction, $usdTransactionAmountWithFees, $usdFeeAmount, $unspentTransactionTotal, $providerNotes, $isDebit, $sid<BR><BR>");
					}	
					
					$dbh 				= null;	
				}
			    catch (PDOException $e) 
			    {
			    		$returnValue 		= -1;	
					
					errorLog($e->getMessage());
			
					die();
				}	
			}
			else if ($commonTransactionID > 0)
			{
				errorLog("transactionRecordExisted");
				
				$responseObject['transactionRecordExisted']	= true;	
			}	
		}
		else
		{
			errorLog("not found - no create");
		}
		
		return $responseObject;
	}
	
	function createCryptoWalletRecord($userAccountID, $authorID, $cryptoWalletIDValue, $assetType, $walletType, $creationDate, $sid, $dbh)
	{
		$returnValue						= 0;
		
		try
		{		
			$insertCryptoWalletRecord		= $dbh -> prepare("INSERT INTO CryptoWallets
(
	FK_AccountID,
	FK_AuthorID,
	encryptedCryptoWalletIDValue,
	FK_AssetTypeID,
	FK_WalletTypeID,
	creationDate,
	sid
)
VALUES
(
	:FK_AccountID,
	:FK_AuthorID,
	AES_ENCRYPT(:cryptoWalletIDValue, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	:FK_AssetTypeID,
	:FK_WalletTypeID,
	:creationDate,
	:sid
)");

			$insertCryptoWalletRecord -> bindValue(':FK_AccountID', $userAccountID);
			$insertCryptoWalletRecord -> bindValue(':FK_AuthorID', $authorID);
			$insertCryptoWalletRecord -> bindValue(':cryptoWalletIDValue', $cryptoWalletIDValue);
			$insertCryptoWalletRecord -> bindValue(':FK_AssetTypeID', $assetType);
			$insertCryptoWalletRecord -> bindValue(':FK_WalletTypeID', $walletType);
			$insertCryptoWalletRecord -> bindValue(':creationDate', $creationDate);
			$insertCryptoWalletRecord -> bindValue(':sid', $sid);
			
			if ($insertCryptoWalletRecord -> execute())
			{
				$returnValue 				= $dbh -> lastInsertId();		
			}	
		}
	    catch (PDOException $e) 
	    {
	    	$returnValue 					= -1;	
			
			errorLog($e -> getMessage());
	
			die();
		}
		
		return $returnValue;		
	}
	
	function createIntegratedClientUserAccount($ipPartnerID, $emailAddress, $nativeAccountIdentifier, $globalCurrentDate, $sid, $dbh)
	{
		$responseObject							= array();
		
		try
		{	
			$checkForExistingEmail				= $dbh->prepare("SELECT
		COUNT(userAccountID) AS numAccounts
	FROM
		UserAccounts
	WHERE
		UserAccounts.encryptedEmailAddress = AES_ENCRYPT(:emailAddress, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512)))");
			
			$createAccount						= $dbh->prepare("INSERT INTO UserAccounts
	(
		encryptedFirstName,
		encryptedMiddleName,
		encryptedLastName,
		encryptedStreetAddress,
		encryptedStreetAddress2,
		encryptedCity,
		encryptedStateID,
		encryptedZip,
		encryptedPhoneNumber,
		encryptedEmailAddress,
		encryptedPassword,
		primaryIPAccountID,
		creationDate,
		modificationDate,
		sid
	)
	VALUES
	(
		AES_ENCRYPT(:firstName, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
		AES_ENCRYPT(:middleName, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
		AES_ENCRYPT(:lastName, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
		AES_ENCRYPT(:address, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
		AES_ENCRYPT(:address2, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
		AES_ENCRYPT(:city, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
		AES_ENCRYPT(:FK_StateID, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
		AES_ENCRYPT(:zip, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
		AES_ENCRYPT(returnNumericOnly(:phoneNumber), UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
		AES_ENCRYPT(:emailAddress, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
		AES_ENCRYPT(:password, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
		:primaryIPAccountID,
		NOW(),
		NOW(),
		:sid
	)");
		
			$insertValidationRequest				= $dbh->prepare("INSERT INTO AccountCreationValidationHash
	(
		FK_UserAccountID,
		requestDate,
		requestStatus,
		sid,
		encryptedUserAccountID,
		encryptedEmailAddress,
		encryptedMobileNumber,
		encryptedValidationHash,
		encryptedSMSValidationCode,
		originalRequestDate
	)
	VALUES
	(
		:FK_UserAccountID,
		:requestDate,
		:requestStatus,
		:sid,
		AES_ENCRYPT(:FK_UserAccountID, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
		AES_ENCRYPT(:emailAddress, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
		AES_ENCRYPT(returnNumericOnly(:mobilePhone), UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
		AES_ENCRYPT(:validationHash, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
		AES_ENCRYPT(:smsValidationCode, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
		:requestDate
	)");
	
			$checkForExistingEmail -> bindValue(':emailAddress', $emailAddress);
		
			$numAccounts							= 0;
			
			if ($checkForExistingEmail -> execute())
			{
				$row 								= $checkForExistingEmail->fetchObject();
				
				$numAccounts						= $row->numAccounts;
			}
		
			if ($numAccounts > 0)
			{
				$responseObject['accountCreated']	= false;
				$responseObject['responseMessage']	= "An account exists for the email address you have supplied.  Please try another email address, or, if this is the correct email address, please try resetting the password.";
			}
			else
			{
				$temporaryPassword					= substr(md5(md5("creating ip client account for ipAccount $ipPartnerID with user $emailAddress and native identifier $nativeAccountIdentifier on $globalCurrentDate").$sid), 0, 10);
				
				$createAccount -> bindValue(':firstName', "");
				$createAccount -> bindValue(':middleName', "");
				$createAccount -> bindValue(':lastName', "");
				$createAccount -> bindValue(':address', "");
				$createAccount -> bindValue(':address2', "");
				$createAccount -> bindValue(':city', "");
				$createAccount -> bindValue(':FK_StateID', "");
				$createAccount -> bindValue(':zip', "");
				$createAccount -> bindValue(':phoneNumber', "");
				$createAccount -> bindValue(':emailAddress', $emailAddress);
				$createAccount -> bindValue(':password', $temporaryPassword);
				$createAccount -> bindValue(':primaryIPAccountID', $ipPartnerID);
				$createAccount -> bindValue(':sid', $sid);
				
				if ($createAccount -> execute())
				{
					$newAccountID 								= $dbh -> lastInsertId();
					$returnValue								= $newAccountID;
					
					$responseObject['accountCreated']			= true;
					$responseObject['userAccountNumber']		= $newAccountID;
					$responseObject['responseMessage']			= "Account Created, pending validation";
					
					selectiveErrorLog("new account $newAccountID");
					
					$validationHash								= md5(md5("no firstName no middleName no lastName $temporaryPassword $emailAddress $nativeAccountIdentifier $globalCurrentDate $sid"));
						
					$smsValidationCode							= generateRandomString(6);
						
					$insertValidationRequest -> bindValue(':FK_UserAccountID', $newAccountID);
					$insertValidationRequest -> bindValue(':validationHash', $validationHash);
					$insertValidationRequest -> bindValue(':smsValidationCode', $smsValidationCode);
					$insertValidationRequest -> bindValue(':emailAddress', $emailAddress);
					$insertValidationRequest -> bindValue(':mobilePhone', "");
					$insertValidationRequest -> bindValue(':requestDate', $globalCurrentDate);
					$insertValidationRequest -> bindValue(':requestStatus', 0);
					$insertValidationRequest -> bindValue(':sid', $sid);
						
					$insertValidationRequest -> execute();
					
					$responseObject['emailVerificationResult'] 	= sendAccountVerificationEmail($newAccountID, $emailAddress, $validationHash);
					
					$userObject									= new UserInformationObject();
					$responseObject['accountInstantiation']		= $userObject -> instantiateUserObjectByUserAccountID($newAccountID, $dbh, $sid);	
					
					$responseObject['associateWithIPAccount'] 	= $userObject -> associateUserAccountWithIntegrationPartner($ipPartnerID, 1, $nativeAccountIdentifier, $globalCurrentDate, $sid, $dbh);
				}	
			}
			
			
			$dbh 								= null;	
		}
	    catch (PDOException $e) 
	    {
	    	$responseObject['responseMessage']	= "A database error has occurred.  Your account could not be created.";	
			$responseObject['accountCreated']	= false;
			errorLog($e->getMessage());
	
			die();
		}
		
		return $responseObject;
	}
	
	function generateTextMessage($recipientPhoneNumber, $recipientID, $sentByID, $textMessage)
	{
		// Use the REST API Client to make requests to the Twilio REST API
		// the Twilio REST API Client is loaded at the top level of this PHP script, just after the return value is declared
		
		// Your Account SID and Auth Token from twilio.com/console
		$tsid = 'AC9c581d132e91a54a650ddde9e53624dd';
		$token = '6ff7d4b9fe0ef1a33185540d306840e1';
		$client = new Client($tsid, $token);
		
		// Use the client to do fun stuff like send text messages!
		$client -> messages -> create(
		    // the number you'd like to send the message to
		    $recipientPhoneNumber,
		    array(
		        // A Twilio phone number you purchased at twilio.com/console
		        'from' => '15592064522',
		        // the body of the text message you'd like to send
		        'body' => $textMessage
		    )
		);
		
		return 1; //@task - get result code and return it
	}
	
	function getAccountIDForEmailAddress($emailAddress, $globalCurrentDate, $sid, $dbh)
	{
		$responseObject									= array();
		$responseObject['requestEmail']					= $emailAddress;
		$responseObject['validEmailAddress']			= false;
		$responseObject['userAccountID']				= 0;
		
		try
		{	
			$getAccountIDForEmailAddress				= $dbh->prepare("SELECT
		userAccountID
	FROM
		UserAccounts
	WHERE
		UserAccounts.encryptedEmailAddress = AES_ENCRYPT(:emailAddress, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512)))");
			
			$getAccountIDForEmailAddress -> bindValue(':emailAddress', $emailAddress);
		
			$accountID									= 0;
			
			if ($getAccountIDForEmailAddress -> execute() && $getAccountIDForEmailAddress -> rowCount() > 0)
			{
				$row 									= $getAccountIDForEmailAddress -> fetchObject();
				
				$accountID								= $row -> userAccountID;
			}
		
			if ($accountID > 0)
			{
				$responseObject['validEmailAddress']	= true;
				$responseObject['userAccountID']		= $accountID;
				$responseObject['responseMessage']		= "User account ID retrieved for email address.";			
			}
			else
			{
				$responseObject['responseMessage']		= "Could not retrieve user account ID for email address.";
			}
			
			// @task - should I not set the dbh to null, since this connection will likely be used again in a few moments by the function that called this function?
			$dbh 										= null;	
		}
	    catch (PDOException $e) 
	    {
	    	$responseObject['responseMessage']	= "Error: A database error has occurred.  Unable to verify the email address: ".$e->getMessage();	
			errorLog($e->getMessage());
	
			die();
		}
		
		return $responseObject;
	}
	
	function getAccountIDForPasswordResetHash($resetHash, $globalCurrentDate, $sid, $dbh)
	{
		$responseObject									= array();
		$responseObject['resetHash']					= $resetHash;
		$responseObject['validResetHash']				= false;
		$responseObject['userAccountID']				= 0;
		
		try
		{	
			$getAccountIDForResetHash					= $dbh->prepare("SELECT
	resetID,
	FK_UserAccountID,
	requestDate,
	requestStatus,
	sid,
	AES_DECRYPT(encryptedEmailAddress, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))) AS decryptedEmailAddress
FROM
	PasswordResetHash
WHERE
	AES_DECRYPT(encryptedResetHash, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))) = :hashValue");
			
			$getAccountIDForResetHash -> bindValue(':hashValue', $resetHash);
		
			$accountID									= 0;
			
			if ($getAccountIDForResetHash -> execute() && $getAccountIDForResetHash -> rowCount() > 0)
			{
				$row 									= $getAccountIDForResetHash -> fetchObject();
				
				$accountID								= $row -> FK_UserAccountID;
				$requestStatus							= $row -> requestStatus;
			}
		
			if ($accountID > 0)
			{
				$responseObject['validResetHash']		= true;
				$responseObject['userAccountID']		= $accountID;
				$responseObject['responseMessage']		= "User account ID retrieved for reset hash.";			
			}
			else
			{
				$responseObject['responseMessage']		= "Could not retrieve user account ID for reset hash.";
			}
			
			// @task - should I not set the dbh to null, since this connection will likely be used again in a few moments by the function that called this function?
			$dbh 										= null;	
		}
	    catch (PDOException $e) 
	    {
	    	$responseObject['responseMessage']	= "Error: A database error has occurred.  Unable to verify the reset hash: ".$e->getMessage();	
			errorLog($e->getMessage());
	
			die();
		}
		
		return $responseObject;
	}
	
	function getCitiesForCountryListJSON($countryID, $dbh)
	{
		$arr												= array();
		
		try
		{		
			$getCitiesForCountry							= $dbh -> prepare("SELECT
	Cities.cityID,
	CASE
		WHEN States.stateAbbreviation IS NOT NULL THEN CONCAT(Cities.cityName, ', ', UPPER(States.stateAbbreviation))
		WHEN States.stateAbbreviation IS NULL THEN Cities.cityName
	END AS cityName,
	Cities.stateID
FROM
	Cities
	INNER JOIN States ON Cities.stateID = States.stateID
WHERE
	Cities.countryID = :countryID
ORDER BY
	Cities.cityName, States.stateAbbreviation");
	
			$getCitiesForCountry -> bindValue(':countryID', $countryID);
		
			if ($getCitiesForCountry -> execute() && $getCitiesForCountry -> rowCount() > 0)
			{
				while($row = $getCitiesForCountry -> fetch(PDO::FETCH_ASSOC))
				{
					$arr['data'][] 							= $row;
				}		
			}
		}
	    catch (PDOException $e) 
	    {
	    	errorLog($e -> getMessage());
	
			die();
		}
		
		$dbh 												= null;
		
		return $arr;	
	}
	
	function getCitiesForCountryAndStateListJSON($countryID, $stateID, $dbh)
	{
		$arr												= array();
		
		try
		{		
			$getCitiesForCountryAndState					= $dbh -> prepare("SELECT
	cityID,
	States.stateID,
	CASE
		WHEN States.stateAbbreviation IS NOT NULL THEN CONCAT(Cities.cityName, ', ', UPPER(States.stateAbbreviation))
		WHEN States.stateAbbreviation IS NULL THEN Cities.cityName
	END AS cityName
FROM
	Cities
	INNER JOIN States ON Cities.stateID = States.stateID
WHERE
	Cities.countryID = :countryID AND
	Cities.stateID = :stateID
ORDER BY
	Cities.cityName");
	
			$getCitiesForCountryAndState -> bindValue(':stateID', $stateID);
			$getCitiesForCountryAndState -> bindValue(':countryID', $countryID);
		
			if ($getCitiesForCountryAndState -> execute() && $getCitiesForCountryAndState -> rowCount() > 0)
			{
				while($row = $getCitiesForCountryAndState -> fetch(PDO::FETCH_ASSOC))
				{
					$arr['data'][] 							= $row;
				}		
			}
		}
	    catch (PDOException $e) 
	    {
	    	errorLog($e -> getMessage());
	
			die();
		}
		
		$dbh 												= null;
		
		return $arr;	
	}
	
	function getCountryListJSON($dbh)
	{
		$arr												= array();
		
		try
		{		
			$getCountries									= $dbh -> prepare("SELECT
		countryID,
		countryOrAreaName,
		isoAlpha2Code,
		isoAlpha3Code
	FROM
		Countries
	ORDER BY
		countryOrAreaName");
		
			if ($getCountries -> execute() && $getCountries -> rowCount() > 0)
			{
				while($row = $getCountries -> fetch(PDO::FETCH_ASSOC))
				{
					$arr['data'][] 							= $row;
				}		
			}
		}
	    catch (PDOException $e) 
	    {
	    	errorLog($e -> getMessage());
	
			die();
		}
		
		$dbh 												= null;
		
		return $arr;	
	}
	
	function getCryptoCurrencyBuyPrice($cryptoCurrencyType, $fiatCurrencyType, $buyDateTime, $buyPriceSource, $dbh)
	{
		$buyPriceObject		 							= null;
		
		try
		{	
			$getBuyPrice		= $dbh->prepare("SELECT
	FK_CryptocurrencyAssetTypeID,
	FK_FiatAssetTypeID,
	buyDateTime,
	buyValue,
	FK_BuyPriceSource,
	creationDate,
	sid
FROM
	BuyPricesByDateTime
WHERE
	FK_CryptocurrencyAssetTypeID = :FK_CryptocurrencyAssetTypeID AND
	FK_FiatAssetTypeID = :FK_FiatAssetTypeID AND
	buyDateTime = :buyDateTime AND
	FK_BuyPriceSource = :FK_BuyPriceSource");
			
			$getBuyPrice -> bindValue(':FK_CryptocurrencyAssetTypeID', $cryptoCurrencyType);
			$getBuyPrice -> bindValue(':FK_FiatAssetTypeID', $fiatCurrencyType);
			$getBuyPrice -> bindValue(':buyDateTime', $buyDateTime);
			$getBuyPrice -> bindValue(':FK_BuyPriceSource', $buyPriceSource);
			
			if ($getBuyPrice -> execute() && $getBuyPrice -> rowCount() > 0)
			{
				while ($row = $getBuyPrice -> fetchObject())
				{
					$FK_CryptocurrencyAssetTypeID	= $row -> FK_CryptocurrencyAssetTypeID;
					$FK_FiatAssetTypeID				= $row -> FK_FiatAssetTypeID;
					$buyDateTime					= $row -> buyDateTime;
					$buyValue						= $row -> buyValue;
					$FK_BuyPriceSource				= $row -> FK_BuyPriceSource;
					$creationDate					= $row -> creationDate;
					$sid							= $row -> sid;
					
					$buyPriceObject					= new CryptoPrice($FK_CryptocurrencyAssetTypeID, $FK_FiatAssetTypeID, $buyDateTime, $buyValue, $FK_BuyPriceSource, $creationDate, $sid);
				}
			}
			else
			{
				errorLog("No historical price found: getCryptoCurrencyBuyPrice for $cryptoCurrencyType, $fiatCurrencyType, $buyDateTime, $buyPriceSource<BR><BR>Storing new price<BR><BR>");
			}

			$dbh 				= null;	
		}
	    catch (PDOException $e) 
	    {
	    	errorLog($e->getMessage());
	
			die();
		}
		return $buyPriceObject;
	}
	
	function getCryptoCurrencySellPrice($cryptoCurrencyType, $fiatCurrencyType, $sellDateTime, $sellPriceSource, $dbh)
	{
		$sellPriceObject		 							= null;
		
		try
		{	
			$getSellPrice								= $dbh->prepare("SELECT
	FK_CryptocurrencyAssetTypeID,
	FK_FiatAssetTypeID,
	sellDateTime,
	sellValue,
	FK_SellPriceSourceID,
	creationDate,
	sid
FROM
	SellPricesByDateTime
WHERE
	FK_CryptocurrencyAssetTypeID = :FK_CryptocurrencyAssetTypeID AND
	FK_FiatAssetTypeID = :FK_FiatAssetTypeID AND
	sellDateTime = :sellDateTime AND
	FK_SellPriceSourceID = :FK_SellPriceSourceID");
			
			$getSellPrice -> bindValue(':FK_CryptocurrencyAssetTypeID', $cryptoCurrencyType);
			$getSellPrice -> bindValue(':FK_FiatAssetTypeID', $fiatCurrencyType);
			$getSellPrice -> bindValue(':sellDateTime', $sellDateTime);
			$getSellPrice -> bindValue(':FK_SellPriceSourceID', $sellPriceSource);
			
			if ($getSellPrice -> execute() && $getSellPrice -> rowCount() > 0)
			{
				while ($row = $getSellPrice -> fetchObject())
				{
					$FK_CryptocurrencyAssetTypeID	= $row -> FK_CryptocurrencyAssetTypeID;
					$FK_FiatAssetTypeID				= $row -> FK_FiatAssetTypeID;
					$sellDateTime					= $row -> sellDateTime;
					$sellValue						= $row -> sellValue;
					$FK_SellPriceSourceID			= $row -> FK_SellPriceSourceID;
					$creationDate					= $row -> creationDate;
					$sid								= $row -> sid;
					
					$sellPriceObject					= new CryptoPrice($FK_CryptocurrencyAssetTypeID, $FK_FiatAssetTypeID, $sellDateTime, $sellValue, $FK_SellPriceSourceID, $creationDate, $sid);
				}
			}
			else
			{
				errorLog("No historical price found: getCryptoCurrencySellPrice for $cryptoCurrencyType, $fiatCurrencyType, $sellDateTime, $sellPriceSource<BR><BR>Storing new price<BR><BR>");
			}

			$dbh 				= null;	
		}
	    catch (PDOException $e) 
	    {
	    	errorLog($e->getMessage());
	
			die();
		}
		return $sellPriceObject;
	}
	
	function getCryptoCurrencySpotPrice($cryptoCurrencyType, $fiatCurrencyType, $spotDateTime, $spotPriceSource, $dbh)
	{
		$spotPriceObject		 							= null;
		
		try
		{	
			$getSpotPrice								= $dbh->prepare("SELECT
	FK_CryptocurrencyAssetTypeID,
	FK_FiatAssetTypeID,
	spotDateTime,
	spotValue,
	FK_SpotPriceSourceID,
	creationDate,
	sid
FROM
	SpotPricesByDateTime
WHERE
	FK_CryptocurrencyAssetTypeID = :FK_CryptocurrencyAssetTypeID AND
	FK_FiatAssetTypeID = :FK_FiatAssetTypeID AND
	spotDateTime = :spotDateTime AND
	FK_SpotPriceSourceID = :FK_SpotPriceSourceID");
			
			$getSpotPrice -> bindValue(':FK_CryptocurrencyAssetTypeID', $cryptoCurrencyType);
			$getSpotPrice -> bindValue(':FK_FiatAssetTypeID', $fiatCurrencyType);
			$getSpotPrice -> bindValue(':spotDateTime', $spotDateTime);
			$getSpotPrice -> bindValue(':FK_SpotPriceSourceID', $spotPriceSource);
			
			if ($getSpotPrice -> execute() && $getSpotPrice -> rowCount() > 0)
			{
				while ($row = $getSpotPrice -> fetchObject())
				{
					$FK_CryptocurrencyAssetTypeID		= $row -> FK_CryptocurrencyAssetTypeID;
					$FK_FiatAssetTypeID					= $row -> FK_FiatAssetTypeID;
					$spotDateTime						= $row -> spotDateTime;
					$spotValue							= $row -> spotValue;
					$FK_SpotPriceSourceID				= $row -> FK_SpotPriceSourceID;
					$creationDate						= $row -> creationDate;
					$sid									= $row -> sid;
					
					$spotPriceObject						= new CryptoPrice($FK_CryptocurrencyAssetTypeID, $FK_FiatAssetTypeID, $spotDateTime, $spotValue, $FK_SpotPriceSourceID, $creationDate, $sid);
				}
			}
			else
			{
				errorLog("No historical price found: getCryptoCurrencySpotPrice for $cryptoCurrencyType, $fiatCurrencyType, $spotDateTime, $spotDateTime<BR><BR>Storing new price<BR><BR>");
			}

			$dbh 				= null;	
		}
	    catch (PDOException $e) 
	    {
	    	errorLog($e->getMessage());
	
			die();
		}
		return $spotPriceObject;
	}
	
	function getCryptoTotalForAllAssetsForUserForAllTransactionSourcesForDate($accountID, $transactionDate, $dbh)
	{
		$responseObject													= array();
		$responseObject['foundTransactions']							= false; 
		
		$assetBalanceForDateAsDecimal									= 0;
		$assetPriceInNativeCurrencyForDateAsDecimal						= 0;
		$assetPriceInNativeCurrencyForDateAsInt							= 0;
		
		$transactionDateOnly											= date_format($transactionDate, "Y-m-d");
		$responseObject['dateValue']									= $transactionDateOnly;
		
		try
		{		
			$getCryptoTransactionTotals									= $dbh -> prepare("SELECT
	SUM(assetBalanceForDateAsDecimal) AS assetBalanceForDateAsDecimal,
	SUM(assetPriceInNativeCurrencyForDateAsDecimal) AS assetPriceInNativeCurrencyForDateAsDecimal,
	SUM(assetPriceInNativeCurrencyForDateAsInt) AS assetPriceInNativeCurrencyForDateAsInt
FROM
	DailyPortfolioBalanceForUserAccount
WHERE
	FK_AccountID = :accountID AND
	FK_AssetTypeID != 173 AND
	FK_TransactionSource != 0 AND 
	LEFT(balanceDate, 10) = :transactionDateOnly");

			$getCryptoTransactionTotals -> bindValue(':accountID', $accountID);
			$getCryptoTransactionTotals -> bindValue(':transactionDateOnly', $transactionDateOnly);
						
			if ($getCryptoTransactionTotals -> execute() && $getCryptoTransactionTotals -> rowCount() > 0)
			{
				$responseObject['foundTransactions']					= true;
				
				while ($row = $getCryptoTransactionTotals -> fetchObject())
				{
					$assetBalanceForDateAsDecimal						= $assetBalanceForDateAsDecimal + $row -> assetBalanceForDateAsDecimal;
					$assetPriceInNativeCurrencyForDateAsDecimal			= $assetPriceInNativeCurrencyForDateAsDecimal + $row -> assetPriceInNativeCurrencyForDateAsDecimal;
					$assetPriceInNativeCurrencyForDateAsInt				= $assetPriceInNativeCurrencyForDateAsInt + $row -> assetPriceInNativeCurrencyForDateAsInt;
				}		
			}
			else
			{
				$responseObject['resultMessage']						= "No transactions found for $transactionDateOnly";
			}
		}
	    catch (PDOException $e) 
	    {
	    	$responseObject['resultMessage']							= "Error: Could not retrieve transaction totals for $transactionDateOnly due to a database error: ".$e -> getMessage();
			
			errorLog($e -> getMessage());
	
			die();
		}
		
		$responseObject['assetBalanceForDateAsDecimal']					= $assetBalanceForDateAsDecimal;
		$responseObject['assetPriceInNativeCurrencyForDateAsDecimal']	= $assetPriceInNativeCurrencyForDateAsDecimal;
		$responseObject['assetPriceInNativeCurrencyForDateAsInt']		= $assetPriceInNativeCurrencyForDateAsInt;
		
		if ($assetPriceInNativeCurrencyForDateAsInt != 0)
		{
			errorLog("Amount $assetPriceInNativeCurrencyForDateAsInt for date $transactionDateOnly\n");	
		}
		
		return $responseObject;		
	}
	
	function getCryptoTotalForAllAssetsForUserForTransactionSourceForDate($accountID, $transactionSourceID, $transactionDate, $dbh)
	{
		$responseObject													= array();
		$responseObject['foundTransactions']							= false; 
		
		$assetBalanceForDateAsDecimal									= 0;
		$assetPriceInNativeCurrencyForDateAsDecimal						= 0;
		$assetPriceInNativeCurrencyForDateAsInt							= 0;
		
		$transactionDateOnly											= date_format($transactionDate, "Y-m-d");
		$responseObject['dateValue']									= $transactionDateOnly;
		
		try
		{		
			$getCryptoTransactionTotals									= $dbh -> prepare("SELECT
	SUM(assetBalanceForDateAsDecimal) AS assetBalanceForDateAsDecimal,
	SUM(assetPriceInNativeCurrencyForDateAsDecimal) AS assetPriceInNativeCurrencyForDateAsDecimal,
	SUM(assetPriceInNativeCurrencyForDateAsInt) AS assetPriceInNativeCurrencyForDateAsInt
FROM
	DailyPortfolioBalanceForUserAccount
WHERE
	FK_AccountID = :accountID AND
	FK_AssetTypeID != 173 AND
	FK_TransactionSource = :transactionSource AND 
	LEFT(balanceDate, 10) = :transactionDateOnly");

			$getCryptoTransactionTotals -> bindValue(':accountID', $accountID);
			$getCryptoTransactionTotals -> bindValue(':transactionSource', $transactionSourceID);
			$getCryptoTransactionTotals -> bindValue(':transactionDateOnly', $transactionDateOnly);
						
			if ($getCryptoTransactionTotals -> execute() && $getCryptoTransactionTotals -> rowCount() > 0)
			{
				$responseObject['foundTransactions']					= true;
				
				while ($row = $getCryptoTransactionTotals -> fetchObject())
				{
					$assetBalanceForDateAsDecimal						= $assetBalanceForDateAsDecimal + $row -> assetBalanceForDateAsDecimal;
					$assetPriceInNativeCurrencyForDateAsDecimal			= $assetPriceInNativeCurrencyForDateAsDecimal + $row -> assetPriceInNativeCurrencyForDateAsDecimal;
					$assetPriceInNativeCurrencyForDateAsInt				= $assetPriceInNativeCurrencyForDateAsInt + $row -> assetPriceInNativeCurrencyForDateAsInt;
				}		
			}
			else
			{
				$responseObject['resultMessage']							= "No transactions found for $transactionDateOnly";
			}
		}
	    catch (PDOException $e) 
	    {
	    	$responseObject['resultMessage']									= "Error: Could not retrieve transaction totals for $transactionDateOnly due to a database error: ".$e -> getMessage();
			
			errorLog($e -> getMessage());
	
			die();
		}
		
		$responseObject['assetBalanceForDateAsDecimal']					= $assetBalanceForDateAsDecimal;
		$responseObject['assetPriceInNativeCurrencyForDateAsDecimal']	= $assetPriceInNativeCurrencyForDateAsDecimal;
		$responseObject['assetPriceInNativeCurrencyForDateAsInt']		= $assetPriceInNativeCurrencyForDateAsInt;
		
		if ($assetPriceInNativeCurrencyForDateAsInt != 0)
		{
			errorLog("Amount $assetPriceInNativeCurrencyForDateAsInt for date $transactionDateOnly\n");	
		}
		
		return $responseObject;		
	}
	
	function getCryptoTransactionsForUser($liuAccountID, $userAccountID, $assetType, $assetTypeName, $globalCurrentDate, $userEncryptionKey, $sid, $dbh)
	{
		errorLog("assetType $assetType");
		
		$transactionsForUser													= array();
		
		try
		{		
			$getCryptoTransactionRecords										= $dbh -> prepare("SELECT
	Transactions.transactionID	
FROM
	Transactions 
WHERE
	Transactions.FK_AccountID = :accountID AND
	Transactions.FK_AssetTypeID = :assetTypeID
ORDER BY
	Transactions.transactionTimestamp ASC, 
	Transactions.isDebit ASC, 
	Transactions.btcQuantityTransacted DESC");

			$getCryptoTransactionRecords -> bindValue(':accountID', $userAccountID);
			$getCryptoTransactionRecords -> bindValue(':assetTypeID', $assetType);
						
			if ($getCryptoTransactionRecords -> execute() && $getCryptoTransactionRecords -> rowCount() > 0)
			{
				while ($row = $getCryptoTransactionRecords -> fetchObject())
				{
					$transactionID											= $row -> transactionID;	
					
					$cryptoTransaction										= new CryptoTransaction();
					
					$instantiateTransactionResponse							= $cryptoTransaction -> instantiateCryptoTransaction($userAccountID, $transactionID, $userEncryptionKey, $dbh);
					
					if ($instantiateTransactionResponse['instantiatedCryptoTransaction'] == true)
					{
						if ($cryptoTransaction -> getIsDebit() == 1)
						{
							$cryptoTransaction								= getOutgoingCryptoTransactionsForUserSpendTransaction($cryptoTransaction -> getAuthorID(), $userAccountID, $cryptoTransaction, $assetType, $assetTypeName, $globalCurrentDate, $sid, $dbh);
						}
						
						$cryptoTransaction									= getRemainingCryptoTransactionBalanceForUser($cryptoTransaction -> getAuthorID(), $userAccountID, $cryptoTransaction, $assetType, $assetTypeName, $globalCurrentDate, $sid, $dbh);
						
						$transactionsForUser[] 								= $cryptoTransaction;	
					}
					else
					{
						errorLog("could not instantiate transaction with ID $transactionID", $GLOBALS['debugCoreFunctionality']);
					}	
				}		
			}
		}
	    catch (PDOException $e) 
	    {
	    		$cryptoTransaction 												= null;	
			
			errorLog($e -> getMessage());
	
			die();
		}
		
		return $transactionsForUser;		
	}
	
	function getCryptoTransactionsAssetTotalForUserAndAssetForDate($accountID, $assetTypeID, $transactionSourceID, $transactionDate, $dbh)
	{
		$responseObject									= array();
		$responseObject['foundTransactions']			= false; 
		
		$transactionTotalForDate						= 0;
		
		$transactionDateOnly							= date_format($transactionDate, "Y-m-d");
		$responseObject['dateValue']					= $transactionDateOnly;
		
		try
		{		
			$getCryptoTransactionTotals					= $dbh -> prepare("SELECT
	Transactions.btcQuantityTransacted,
	Transactions.isDebit
FROM
	Transactions 
WHERE
	Transactions.FK_AccountID = :accountID AND
	Transactions.FK_AssetTypeID = :assetTypeID AND
	Transactions.FK_TransactionSourceID = :transactionSourceID AND
	LEFT(Transactions.transactionDate, 10) = :transactionDateOnly");

			$getCryptoTransactionTotals -> bindValue(':accountID', $accountID);
			$getCryptoTransactionTotals -> bindValue(':assetTypeID', $assetTypeID);
			$getCryptoTransactionTotals -> bindValue(':transactionSourceID', $transactionSourceID);
			$getCryptoTransactionTotals -> bindValue(':transactionDateOnly', $transactionDateOnly);
						
			if ($getCryptoTransactionTotals -> execute() && $getCryptoTransactionTotals -> rowCount() > 0)
			{
				
				$responseObject['foundTransactions']	= true;
				
				while ($row = $getCryptoTransactionTotals -> fetchObject())
				{
					$ccQuantityTransacted				= $row -> btcQuantityTransacted;
					$isDebit							= $row -> isDebit;
					
					if ($isDebit == 1)
					{
						$transactionTotalForDate		= $transactionTotalForDate - $ccQuantityTransacted;	
					}
					else if ($isDebit == 0)
					{
						$transactionTotalForDate		= $transactionTotalForDate + $ccQuantityTransacted;	
					}
				}		
			}
			else
			{
				$responseObject['resultMessage']		= "No transactions found for $transactionDateOnly";
			}
		}
	    catch (PDOException $e) 
	    {
	    	$responseObject['resultMessage']			= "Error: Could not retrieve transaction totals for $transactionDateOnly due to a database error: ".$e -> getMessage();
			
			errorLog($e -> getMessage());
	
			die();
		}
		
		$responseObject['transactionTotal']				= $transactionTotalForDate;
		
		if ($transactionTotalForDate != 0)
		{
			errorLog("Amount $transactionTotalForDate for date $transactionDateOnly\n");	
		}
		
		return $responseObject;		
	}
	
	function getHistoricalCryptoCurrencyPrice($date, $cryptoCurrency, $fiatCurrency)
	{
		$constructURL						= "https://api.coinbase.com/v2/prices/$cryptoCurrency-$fiatCurrency/spot?date=$date";
		
		errorLog($constructURL."<BR><BR>");
		
		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $constructURL);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
	
		$headers = array();
		$headers[] = "Content-Type: application/x-www-form-urlencoded";
		$headers[] = "CB-VERSION: 2018-05-28";
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	
		$result = curl_exec($ch);
		
		if (curl_errno($ch)) {
			errorLog('Error:' . curl_error($ch));
		}
		else {
			errorLog($result);
		}
		
		curl_close ($ch);
		
		$spotPriceObject				= json_decode($result);
		
		$spotPrice						= 0;
		
		if (!empty($spotPriceObject))
		{
			$spotPrice					= $spotPriceObject -> data -> amount;
		}
		// https://api.coinbase.com/v2/prices/BTC-USD/spot?date=2018-06-23
		
		return $spotPrice;
	}
	// here
	function getOutgoingCryptoTransactionsForUserSpendTransaction($liuAccountID, $userAccountID, $spendTransaction, $assetType, $assetTypeName, $globalCurrentDate, $sid, $dbh)
	{
		$spendTransactionID													= $spendTransaction -> getTransactionID();
		
		try
		{		
			$getCryptoTransactionRecords										= $dbh -> prepare("SELECT
	OutboundTransactionSourceGrouping.FK_InboundAssetTransactionID,
	OutboundTransactionSourceGrouping.FK_SpendTransactionType,
	OutboundTransactionSourceGrouping.subTransactionAmount,
	OutboundTransactionSourceGrouping.receiveTransactionDate,
	OutboundTransactionSourceGrouping.spendTransactionDate,
	OutboundTransactionSourceGrouping.receiveSpotPriceUSD,
	OutboundTransactionSourceGrouping.sellSpotPriceUSD,
	OutboundTransactionSourceGrouping.FK_GainTypeID,
	OutboundTransactionSourceGrouping.profitOrLossAmountUSD,
	OutboundTransactionSourceGrouping.spentToAcquireAmount,
	OutboundTransactionSourceGrouping.realizedAmount,
	OutboundTransactionSourceGrouping.creationDate,
	OutboundTransactionSourceGrouping.FK_AuthorID,
	OutboundTransactionSourceGrouping.FK_AccountID,
	OutboundTransactionSourceGrouping.sid,
	GainTypeValues.gainTypeLabel,
	TransactionTypes.transactionTypeLabel AS spendTransactionTypeLabel
FROM
	OutboundTransactionSourceGrouping
	INNER JOIN GainTypeValues ON OutboundTransactionSourceGrouping.FK_GainTypeID = GainTypeValues.gainTypeID AND GainTypeValues.languageCode = 'EN'
	INNER JOIN TransactionTypes ON OutboundTransactionSourceGrouping.FK_SpendTransactionType = TransactionTypes.transactionTypeID AND TransactionTypes.languageCode = 'EN'
WHERE
	OutboundTransactionSourceGrouping.FK_OutboundAssetTransactionID = :spendTransactionID");

			$getCryptoTransactionRecords -> bindValue(':spendTransactionID', $spendTransactionID);
						
			if ($getCryptoTransactionRecords -> execute() && $getCryptoTransactionRecords -> rowCount() > 0)
			{
				while ($row = $getCryptoTransactionRecords -> fetchObject())
				{
					$inboundAssetTransactionID								= $row -> FK_InboundAssetTransactionID;
					$spendTransactionType									= $row -> FK_SpendTransactionType;
					$spendTransactionTypeLabel								= $row -> spendTransactionTypeLabel;
					$subTransactionAmount									= $row -> subTransactionAmount;
					$receiveTransactionDate									= $row -> receiveTransactionDate;
					$spendTransactionDate									= $row -> spendTransactionDate;
					$receiveSpotPriceUSD										= $row -> receiveSpotPriceUSD;
					$sellSpotPriceUSD										= $row -> sellSpotPriceUSD;
					$gainTypeID												= $row -> FK_GainTypeID;
					$gainTypeLabel											= $row -> gainTypeLabel;
					$profitOrLossAmountUSD									= $row -> profitOrLossAmountUSD;
					$spentToAcquireAmount									= $row -> spentToAcquireAmount;
					$realizedAmount											= $row -> realizedAmount;
					$creationDate											= $row -> creationDate;
					$authorID												= $row -> FK_AuthorID;
					$accountID												= $row -> FK_AccountID;
					$sid														= $row -> sid;
					
					$groupedCryptoSpendTransaction							= new CryptoGroupedSpendTransaction($inboundAssetTransactionID, $spendTransactionID, $accountID, $authorID, $spendTransactionType, $spendTransactionTypeLabel, $subTransactionAmount, $receiveTransactionDate, $spendTransactionDate, $receiveSpotPriceUSD, $sellSpotPriceUSD, $creationDate, $sid, $dbh);
					
					$spendTransaction -> addSpendTransactionGroupObject($groupedCryptoSpendTransaction);
				}		
			}
		}
	    catch (PDOException $e) 
	    {
	    		$cryptoTransaction 												= null;	
			
			errorLog($e -> getMessage());
	
			die();
		}
		
		return $spendTransaction;		
	}
	// here
	function getOutgoingCryptoTransactionsForUserTransaction($liuAccountID, $userAccountID, $receiveCryptoTransaction, $assetType, $assetTypeName, $globalCurrentDate, $sid, $dbh)
	{
		$receiveTransactionID						= $receiveCryptoTransaction -> getTransactionID();
				
		try
		{		
			$getCryptoTransactionRecords			= $dbh -> prepare("SELECT
	OutboundTransactionSourceGrouping.FK_OutboundAssetTransactionID,
	OutboundTransactionSourceGrouping.FK_SpendTransactionType,
	OutboundTransactionSourceGrouping.subTransactionAmount,
	OutboundTransactionSourceGrouping.receiveTransactionDate,
	OutboundTransactionSourceGrouping.spendTransactionDate,
	OutboundTransactionSourceGrouping.receiveSpotPriceUSD,
	OutboundTransactionSourceGrouping.sellSpotPriceUSD,
	OutboundTransactionSourceGrouping.FK_GainTypeID,
	OutboundTransactionSourceGrouping.profitOrLossAmountUSD,
	OutboundTransactionSourceGrouping.spentToAcquireAmount,
	OutboundTransactionSourceGrouping.realizedAmount,
	OutboundTransactionSourceGrouping.creationDate,
	OutboundTransactionSourceGrouping.FK_AuthorID,
	OutboundTransactionSourceGrouping.FK_AccountID,
	OutboundTransactionSourceGrouping.sid,
	GainTypeValues.gainTypeLabel,
	TransactionTypes.transactionTypeLabel AS spendTransactionTypeLabel
FROM
	OutboundTransactionSourceGrouping
	INNER JOIN GainTypeValues ON OutboundTransactionSourceGrouping.FK_GainTypeID = GainTypeValues.gainTypeID AND GainTypeValues.languageCode = 'EN'
	INNER JOIN TransactionTypes ON OutboundTransactionSourceGrouping.FK_SpendTransactionType = TransactionTypes.transactionTypeID AND TransactionTypes.languageCode = 'EN'
WHERE
	OutboundTransactionSourceGrouping.FK_InboundAssetTransactionID = :receiveTransactionID");

			$getCryptoTransactionRecords -> bindValue(':receiveTransactionID', $receiveTransactionID);
						
			if ($getCryptoTransactionRecords -> execute() && $getCryptoTransactionRecords -> rowCount() > 0)
			{
				while ($row = $getCryptoTransactionRecords -> fetchObject())
				{
					$outboundAssetTransactionID		= $row->FK_OutboundAssetTransactionID;
					$spendTransactionType			= $row->FK_SpendTransactionType;
					$spendTransactionTypeLabel		= $row->spendTransactionTypeLabel;
					$subTransactionAmount			= $row->subTransactionAmount;
					$receiveTransactionDate			= $row->receiveTransactionDate;
					$spendTransactionDate			= $row->spendTransactionDate;
					$receiveSpotPriceUSD				= $row->receiveSpotPriceUSD;
					$sellSpotPriceUSD				= $row->sellSpotPriceUSD;
					$gainTypeID						= $row->FK_GainTypeID;
					$gainTypeLabel					= $row->gainTypeLabel;
					$profitOrLossAmountUSD			= $row->profitOrLossAmountUSD;
					$spentToAcquireAmount			= $row->spentToAcquireAmount;
					$realizedAmount					= $row->realizedAmount;
					$creationDate					= $row->creationDate;
					$authorID						= $row->FK_AuthorID;
					$accountID						= $row->FK_AccountID;
					$sid								= $row->sid;
					
					$groupedCryptoSpendTransaction	= new CryptoGroupedSpendTransaction($receiveTransactionID, $outboundAssetTransactionID, $accountID, $authorID, $spendTransactionType, $spendTransactionTypeLabel, $subTransactionAmount, $receiveTransactionDate, $spendTransactionDate, $receiveSpotPriceUSD, $sellSpotPriceUSD, $creationDate, $sid, $dbh);
					
					$receiveCryptoTransaction -> addSpendTransactionGroupObject($groupedCryptoSpendTransaction);
				}		
			}
		}
	    catch (PDOException $e) 
	    {
	    	$cryptoTransaction 					= null;	
			
			errorLog($e -> getMessage());
	
			die();
		}
		
		return $receiveCryptoTransaction;		
	}
	
	function getReceivedCryptoTransactionsForUser($liuAccountID, $userAccountID, $assetType, $assetTypeName, $globalCurrentDate, $userEncryptionKey, $sid, $dbh)
	{
		$transactionsForUser						= array();
				
		try
		{		
			$getCryptoTransactionRecords			= $dbh -> prepare("SELECT
	Transactions.transactionID	
FROM
	Transactions 
	INNER JOIN CryptoWallets ON Transactions.FK_DestinationAddressID = CryptoWallets.walletID
	INNER JOIN AssetTypes assetTypes ON Transactions.FK_AssetTypeID = assetTypes.assetTypeID AND assetTypes.languageCode = 'EN'
	INNER JOIN AssetTypes spotPriceCurrencyType ON Transactions.FK_SpotPriceCurrencyID = spotPriceCurrencyType.assetTypeID AND spotPriceCurrencyType.languageCode = 'EN'
	INNER JOIN TransactionTypes ON Transactions.FK_TransactionTypeID = TransactionTypes.transactionTypeID AND TransactionTypes.languageCode = 'EN'
	INNER JOIN TransactionSources ON Transactions.FK_TransactionSourceID = TransactionSources.transactionSourceID AND TransactionSources.languageCode = 'EN'
WHERE
	CryptoWallets.FK_AccountID = :accountID AND
	Transactions.isDebit = 0 AND
	Transactions.FK_AssetTypeID = :assetTypeID
ORDER BY
	Transactions.transactionTimestamp");

			$getCryptoTransactionRecords -> bindValue(':accountID', $userAccountID);
			$getCryptoTransactionRecords -> bindValue(':assetTypeID', $assetType);
						
			if ($getCryptoTransactionRecords -> execute() && $getCryptoTransactionRecords -> rowCount() > 0)
			{
				while ($row = $getCryptoTransactionRecords -> fetchObject())
				{
					$transactionID											= $row -> transactionID;	
					
					$cryptoTransaction										= new CryptoTransaction();
					
					$instantiateTransactionResponse							= $cryptoTransaction -> instantiateCryptoTransaction($userAccountID, $transactionID, $userEncryptionKey, $dbh);
					
					if ($instantiateTransactionResponse['instantiatedCryptoTransaction'] == true)
					{										
						$cryptoTransaction									= getOutgoingCryptoTransactionsForUserTransaction($cryptoTransaction -> getAuthorID(), $userAccountID, $cryptoTransaction, $assetType, $assetTypeName, $globalCurrentDate, $sid, $dbh);
					
						$cryptoTransaction									= getRemainingCryptoTransactionBalanceForUser($cryptoTransaction -> getAuthorID(), $userAccountID, $cryptoTransaction, $assetType, $assetTypeName, $globalCurrentDate, $sid, $dbh);
					
						$transactionsForUser[] 								= $cryptoTransaction;
					}
						
				}		
			}
		}
	    catch (PDOException $e) 
	    {
	    	$cryptoTransaction 					= null;	
			
			errorLog($e -> getMessage());
	
			die();
		}
		
		return $transactionsForUser;		
	}
	
	function getRemainingCryptoTransactionBalanceForUser($liuAccountID, $userAccountID, $receiveCryptoTransaction, $assetType, $assetTypeName, $globalCurrentDate, $sid, $dbh)
	{
		errorLog("beginning getRemainingCryptoTransactionBalanceForUser: $liuAccountID, $userAccountID, $assetType, $assetTypeName, $globalCurrentDate, $sid");
		
		try
		{		
			$getRemainingCryptoTransactionBalance							= $dbh -> prepare("SELECT
	CryptoBalanceRecords.cryptoBalanceRecordID,
	CryptoBalanceRecords.FK_AccountID,
	CryptoBalanceRecords.FK_AuthorID,
	CryptoBalanceRecords.FK_ReceiveTransactionID,
	CryptoBalanceRecords.remainingAssetAmount,
	CryptoBalanceRecords.receiveSpotPriceUSD,
	CryptoBalanceRecords.sid
FROM
	CryptoBalanceRecords
WHERE
	CryptoBalanceRecords.FK_BalanceAsOfTransactionID = :transactionID");
	
			$getRemainingCryptoTransactionBalance -> bindValue(':transactionID', $receiveCryptoTransaction -> getTransactionID());
				
			if ($getRemainingCryptoTransactionBalance -> execute() && $getRemainingCryptoTransactionBalance -> rowCount() > 0)
			{
				while ($row = $getRemainingCryptoTransactionBalance -> fetchObject())
				{
					$cryptoBalanceRecordID										= $row -> cryptoBalanceRecordID;
					$accountID													= $row -> FK_AccountID;
					$authorID													= $row -> FK_AuthorID;
					$receiveTransactionID										= $row -> FK_ReceiveTransactionID;
					$remainingAssetAmount										= $row -> remainingAssetAmount;
					$receiveSpotPriceUSD										= $row -> receiveSpotPriceUSD;
					$sid														= $row -> sid;
					
					$cryptoBalanceObject										= new CryptoTransactionBalance($cryptoBalanceRecordID, $accountID, $authorID, $receiveCryptoTransaction -> getTransactionID(), $receiveTransactionID, $remainingAssetAmount, $receiveSpotPriceUSD, $sid);	
					
					$receiveCryptoTransaction -> addRemainingBalanceObject($cryptoBalanceObject);
				}			
			}			
		}
	    catch (PDOException $e) 
	    {
	    		errorLog($e -> getMessage());
	
			die();
		}
		
		return $receiveCryptoTransaction;		
	}
	
	function getSessionUsingOAuthTokenAndSessionID($oauthToken, $currentSessionID, $globalCurrentDate, $dbh)
	{
		$responseObject												= array();
		
		try
		{	
			$validationSQL											= "";
	
			if ($mode == 1)
			{
				$responseObject['mode']								= "production";
				$responseObject['modeID']							= 1;
				
				// production mode
				$validationSQL										= "SELECT
	IntegrationPartnerAccounts.ipID,
	IntegrationPartnerAccounts.encryptedCompanyProdSecretKey AS encryptedCompanySecretKey
FROM
	IntegrationPartnerAccounts
WHERE
	IntegrationPartnerAccounts.ipID = :ipID AND
	AES_DECRYPT(IntegrationPartnerAccounts.encryptedCompanyProdToken, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))) = :providedIPToken";
			}
			else if ($mode == 2)
			{
				$responseObject['mode']								= "development";
				$responseObject['modeID']							= 1;
				
				// test mode
				$validationSQL										= "SELECT
	IntegrationPartnerAccounts.ipID,
	IntegrationPartnerAccounts.encryptedCompanyDevSecretKey AS encryptedCompanySecretKey
FROM
	IntegrationPartnerAccounts
WHERE
	IntegrationPartnerAccounts.ipID = :ipID AND
	AES_DECRYPT(IntegrationPartnerAccounts.encryptedCompanyDevToken, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))) = :providedIPToken";
			}
			
			
			$getValidationRequestData							= $dbh->prepare($validationSQL);
		
			$getValidationRequestData -> bindValue(':providedIPToken', $ipSecret);
			$getValidationRequestData -> bindValue(':ipID', $ipAccountID);
	
			if ($getValidationRequestData -> execute() && $getValidationRequestData -> rowCount() > 0)
			{
				$row 											= $getValidationRequestData -> fetchObject();
				
				$ipID											= $row -> ipID;
				$encryptedCompanySecretKey						= $row -> encryptedCompanySecretKey;
				
				if ($ipID == $ipAccountID && !empty($encryptedCompanySecretKey))
				{
					$responseObject['ipTokenVerified']			= true;
					$responseObject['responseMessage']			= "Thank you.  Your token has been verified.";	
				}
				else
				{
					$responseObject['ipTokenVerified']			= false;
					$responseObject['responseMessage']			= "Your token could not be verified.";	
				}
			}
			else
			{
				$responseObject['ipTokenVerified']				= false;
				$responseObject['responseMessage']				= "Your token could not be verified.";	
			}
			
			$dbh 												= null;	
		}
	    catch (PDOException $e) 
	    {
	    	$responseObject['responseMessage']					= "A database error has occurred.  Your token could not be verified.";	
			$responseObject['ipTokenVerified']					= false;
			$responseObject['modeID']							= $mode;
			errorLog($e->getMessage());
	
			die();
		}
		
		return $responseObject;	
	}
	
	function getSpotPriceForAssetForDate($assetTypeID, $spotPriceDate, $nativeCurrencyTypeID, $dbh)
	{
		$responseObject									= array();
		$responseObject['retrievedSpotPrice']			= false;
		
		$priceDate										= date_format($spotPriceDate, "Y-m-d");
		
		try
		{	
			$getSpotPrice								= $dbh->prepare("SELECT
	fiatCurrencySpotPrice
FROM
	DailyCryptoSpotPrices
WHERE
	FK_CryptoAssetID = :assetTypeID AND
	FK_FiatCurrencyAssetID = :nativeCurrencyAssetTypeID AND
	priceDate = :priceDate
ORDER BY
	fiatCurrencySpotPrice DESC
LIMIT 1");
			
			$getSpotPrice -> bindValue(':assetTypeID', $assetTypeID);
			$getSpotPrice -> bindValue(':nativeCurrencyAssetTypeID', $nativeCurrencyTypeID);
			$getSpotPrice -> bindValue(':priceDate', $priceDate);
			
			if ($getSpotPrice -> execute() && $getSpotPrice -> rowCount() > 0)
			{
				$row 									= $getSpotPrice -> fetchObject();
				
				$responseObject['spotPrice']				= $row -> fiatCurrencySpotPrice;
				
				$responseObject['retrievedSpotPrice']	= true;
			}
			else
			{
				$responseObject['resultMessage']			= "No price data found for $assetTypeID, $nativeCurrencyTypeID, $priceDate";
			}

			$dbh 				= null;	
		}
	    catch (PDOException $e) 
	    {
	    	$responseObject['resultMessage']					= "Could not retrieve price data for $assetTypeID, $nativeCurrencyTypeID, $priceDate due to a database error: ".$e->getMessage();
	    	
	    	errorLog($e->getMessage());
	
			die();
		}
		return $responseObject;	
	}
	
	function getSpotPriceForAssetForDateForDataSource($assetTypeID, $spotPriceDate, $nativeCurrencyTypeID, $dataSource, $dbh)
	{
		$responseObject							= array();
		$responseObject['retrievedSpotPrice']	= false;
		
		if (is_object($spotPriceDate) == false)
		{
			$spotPriceDate						= new DateTime($spotPriceDate);	
		}
		
		$priceDate								= date_format($spotPriceDate, "Y-m-d");
		
		try
		{	
			$getSpotPrice						= $dbh->prepare("SELECT
	fiatCurrencySpotPrice
FROM
	DailyCryptoSpotPrices
WHERE
	FK_CryptoAssetID = :assetTypeID AND
	FK_FiatCurrencyAssetID = :nativeCurrencyAssetTypeID AND
	priceDate = :priceDate AND
	FK_DataSource = :dataSource");
			
			$getSpotPrice -> bindValue(':assetTypeID', $assetTypeID);
			$getSpotPrice -> bindValue(':nativeCurrencyAssetTypeID', $nativeCurrencyTypeID);
			$getSpotPrice -> bindValue(':priceDate', $priceDate);
			$getSpotPrice -> bindValue(':dataSource', $dataSource);
			
			if ($getSpotPrice -> execute() && $getSpotPrice -> rowCount() > 0)
			{
				$row 									= $getSpotPrice -> fetchObject();
				
				$responseObject['spotPrice']			= $row -> fiatCurrencySpotPrice;
				
				$responseObject['retrievedSpotPrice']	= true;
			}
			else
			{
				$responseObject['resultMessage']		= "No price data found for $assetTypeID, $nativeCurrencyTypeID, $priceDate, $dataSource";
			}

			$dbh 				= null;	
		}
	    catch (PDOException $e) 
	    {
	    	$responseObject['resultMessage']			= "Could not retrieve price data for $assetTypeID, $nativeCurrencyTypeID, $priceDate, $dataSource due to a database error: ".$e->getMessage();
	    	
	    	errorLog($e->getMessage());
	
			die();
		}
		return $responseObject;	
	}
	
	function getSpotPriceForAssetPairUsingSourceCascade($baseCurrencyAssetTypeID, $quoteCurrencyAssetTypeID, $transactionTimestamp, $exchangeSourceID, $exchangeSourceName, $dbh)
	{
		$spotPriceDate														= $transactionTimestamp;
		
		if (is_object($spotPriceDate) == false)
		{
			$spotPriceDate													= new DateTime($spotPriceDate);	
		}
		
		$priceDate															= date_format($spotPriceDate, "Y-m-d");
		
		errorLog("getSpotPriceForAssetPairUsingSourceCascade($baseCurrencyAssetTypeID, $quoteCurrencyAssetTypeID, $priceDate, $exchangeSourceID, $exchangeSourceName)");
		
		$responseObject														= array();
		$responseObject['foundSpotPrice']									= false;
		$responseObject['baseCurrencyID']									= $baseCurrencyAssetTypeID;
		$responseObject['quoteCurrencyID']									= $quoteCurrencyAssetTypeID;
		$responseObject['spotPriceDate']									= $priceDate;
		$responseObject['spotPrice']										= 0;
		$responseObject['spotPriceSourceLabel']								= "";
		$responseObject['spotPriceSourceID']								= 0;
		
		if ($baseCurrencyAssetTypeID == $quoteCurrencyAssetTypeID)
		{
			$responseObject['foundSpotPrice']								= true;
			$responseObject['spotPrice']									= 1;	
			$responseObject['spotPriceSourceLabel']							= "Currency Pair calculated values";
			$responseObject['spotPriceSourceID']							= 9;
		}
		else
		{
			$retrieveSpotPriceResponseObject								= getSpotPriceForAssetForDateForDataSource($baseCurrencyAssetTypeID, $priceDate, $quoteCurrencyAssetTypeID, $exchangeSourceID, $dbh); // get spot price for this base currency and quote currency from $exchangeSourceName for this date
				
			if ($retrieveSpotPriceResponseObject['retrievedSpotPrice'] == true && $retrieveSpotPriceResponseObject['spotPrice'] > 0)
			{
				$responseObject['spotPrice']								= $retrieveSpotPriceResponseObject['spotPrice'];
				$responseObject['spotPriceSourceLabel']						= $exchangeSourceName;	
				
				if ($exchangeSourceID == 2)
				{
					$responseObject['spotPriceSourceID']					= 1;	
				}
				else if ($exchangeSourceID == 21)
				{
					$responseObject['spotPriceSourceID']					= 4;	
				}
				else
				{
					$responseObject['spotPriceSourceID']					= $exchangeSourceID;
				}
				
				$responseObject['foundSpotPrice']							= true;	
			}
			else
			{
				$retrieveSpotPriceResponseObject							= getSpotPriceForAssetForDateForDataSource($baseCurrencyAssetTypeID, $priceDate, $quoteCurrencyAssetTypeID, 2, $dbh); // get spot price for this base currency and quote currency from Coinbase for this date
					
				if ($retrieveSpotPriceResponseObject['retrievedSpotPrice'] == true && $retrieveSpotPriceResponseObject['spotPrice'] > 0)
				{
					$responseObject['spotPrice']							= $retrieveSpotPriceResponseObject['spotPrice'];
					$responseObject['spotPriceSourceLabel']					= "Coinbase";	
					$responseObject['spotPriceSourceID']					= 1;	
					$responseObject['foundSpotPrice']						= true;
				}
				else
				{
					$retrieveSpotPriceResponseObject						= getSpotPriceForAssetForDateForDataSource($baseCurrencyAssetTypeID, $priceDate, $quoteCurrencyAssetTypeID, 14, $dbh); // get spot price for this base currency and quote currency from CoinGecko for this date
					
					if ($retrieveSpotPriceResponseObject['retrievedSpotPrice'] == true && $retrieveSpotPriceResponseObject['spotPrice'] > 0)
					{
						$responseObject['spotPrice']						= $retrieveSpotPriceResponseObject['spotPrice'];
						$responseObject['spotPriceSourceLabel']				= "CoinGecko";	
						$responseObject['spotPriceSourceID']				= 14;	
						$responseObject['foundSpotPrice']					= true;
					}
					else
					{
						$retrieveSpotPriceResponseObject					= getSpotPriceForAssetForDateForDataSource($baseCurrencyAssetTypeID, $priceDate, $quoteCurrencyAssetTypeID, 11, $dbh); // get spot price for this base currency and quote currency from CryptoCompare for this date
					
						if ($retrieveSpotPriceResponseObject['retrievedSpotPrice'] == true && $retrieveSpotPriceResponseObject['spotPrice'] > 0)
						{
							$responseObject['spotPrice']					= $retrieveSpotPriceResponseObject['spotPrice'];
							$responseObject['spotPriceSourceLabel']			= "CryptoCompare";	
							$responseObject['spotPriceSourceID']			= 11;	
							$responseObject['foundSpotPrice']				= true;
						}	
						else
						{
							$retrieveSpotPriceResponseObject				= getSpotPriceForAssetForDateForDataSource($baseCurrencyAssetTypeID, $priceDate, $quoteCurrencyAssetTypeID, 4, $dbh); // get spot price for this base currency and quote currency from Coinmetrics for this date
					
							if ($retrieveSpotPriceResponseObject['retrievedSpotPrice'] == true && $retrieveSpotPriceResponseObject['spotPrice'] > 0)
							{
								$responseObject['spotPrice']				= $retrieveSpotPriceResponseObject['spotPrice'];
								$responseObject['spotPriceSourceLabel']		= "Coinmetrics";	
								$responseObject['spotPriceSourceID']		= 4;	
								$responseObject['foundSpotPrice']			= true;
							}
							else
							{
								$retrieveSpotPriceResponseObject			= getSpotPriceForAssetForDateForDataSource($baseCurrencyAssetTypeID, $priceDate, $quoteCurrencyAssetTypeID, 8, $dbh); // get spot price for this currency and quote currency from Coinbase Comparison Values for this date
						
								if ($retrieveSpotPriceResponseObject['retrievedSpotPrice'] == true)
								{
									$responseObject['spotPrice']			= $retrieveSpotPriceResponseObject['spotPrice'];
									$responseObject['spotPriceSourceLabel']	= "Coinbase Comparison Values";	
									$responseObject['spotPriceSourceID']	= 8;
									$responseObject['foundSpotPrice']		= true;	
								}
								else
								{	
									errorLog("no USD spot price available for this asset $baseCurrencyAssetTypeID and date $priceDate");
								}
							}	
						}
					}
				}	
			}	
		}

		return $responseObject;
	}
	
	function getStatesForCountryListJSON($countryID, $dbh)
	{
		$arr												= array();
		
		try
		{		
			$getStatesForCountry							= $dbh -> prepare("SELECT
	stateID,
	stateName
FROM
	States
WHERE
	countryID = :countryID");
	
			$getStatesForCountry -> bindValue(':countryID', $countryID);
		
			if ($getStatesForCountry -> execute() && $getStatesForCountry -> rowCount() > 0)
			{
				while($row = $getStatesForCountry -> fetch(PDO::FETCH_ASSOC))
				{
					$arr['data'][] 							= $row;
				}		
			}
		}
	    catch (PDOException $e) 
	    {
	    	errorLog($e -> getMessage());
	
			die();
		}
		
		$dbh 												= null;
		
		return $arr;	
	}
	
	function getWalletsForUserJSON($accountID, $dbh)
	{
		$arr												= array();
		
		try
		{		
			$getWalletsForUser								= $dbh -> prepare("SELECT DISTINCT
	ProviderAccountWallets.FK_AssetTypeID,
	AssetTypes.assetTypeLabel,
	AssetTypes.description
FROM
	ProviderAccountWallets
	INNER JOIN AssetTypes ON ProviderAccountWallets.FK_AssetTypeID = AssetTypes.assetTypeID = AssetTypes.languageCode = 'EN'
WHERE
	ProviderAccountWallets.FK_AccountID = :accountID");
	
			$getWalletsForUser -> bindValue(':accountID', $accountID);
		
			if ($getWalletsForUser -> execute() && $getWalletsForUser -> rowCount() > 0)
			{
				while($row = $getWalletsForUser -> fetch(PDO::FETCH_ASSOC))
				{
					$arr['data'][] 							= $row;
				}		
			}
		}
	    catch (PDOException $e) 
	    {
	    	errorLog($e -> getMessage());
	
			die();
		}
		
		$dbh 												= null;
		
		return $arr;	
	}
	
	/*
function getWalletsForUserJSON($accountID, $dbh)
	{
		$arr												= array();
		
		try
		{		
			$getWalletsForUser								= $dbh -> prepare("SELECT
	ProviderAccountWallets.accountWalletID,
	ProviderAccountWallets.FK_AccountID,
	ProviderAccountWallets.FK_AuthorID,
	ProviderAccountWallets.FK_WalletTypeID,
	AES_DECRYPT(ProviderAccountWallets.encryptedNativeWalletIDValue, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))) AS decryptedNativeWalletID,
	ProviderAccountWallets.FK_AssetTypeID,
	ProviderAccountWallets.creationDate,
	ProviderAccountWallets.sid,
	AES_DECRYPT(WalletTypes.encryptedWalletTypeLabel, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))) AS walletTypeLabel,
	AES_DECRYPT(WalletTypes.encryptedWalletTypeDescription, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))) AS walletTypeDescription,
	AssetTypes.assetTypeLabel,
	AssetTypes.description
FROM
	ProviderAccountWallets
	INNER JOIN WalletTypes ON ProviderAccountWallets.FK_WalletTypeID = WalletTypes.walletTypeID AND WalletTypes.languageCode = 'EN'
	INNER JOIN AssetTypes ON ProviderAccountWallets.FK_AssetTypeID = AssetTypes.assetTypeID = AssetTypes.languageCode = 'EN'
WHERE
	ProviderAccountWallets.FK_AccountID = :accountID");
	
			$getWalletsForUser -> bindValue(':accountID', $accountID);
		
			if ($getWalletsForUser -> execute() && $getWalletsForUser -> rowCount() > 0)
			{
				while($row = $getWalletsForUser -> fetch(PDO::FETCH_ASSOC))
				{
					$arr['data'][] 							= $row;
				}		
			}
		}
	    catch (PDOException $e) 
	    {
	    	errorLog($e -> getMessage());
	
			die();
		}
		
		$dbh 												= null;
		
		return $arr;	
	}
*/

	function getWalletsForUserByUserID($liuAccountID, $userAccountID, $assetType, $walletType, $globalCurrentDate, $sid, $dbh)
	{
		// if $assetType == 0, do not filter by asset type
		// if $walletType == 0, do not filter by wallet type
		
		$walletsForUser						= array();
		
		try
		{	
			$getCryptoWalletRecordSQL		= "SELECT
	CryptoWallets.walletID,
	CryptoWallets.FK_AccountID,
	CryptoWallets.FK_AuthorID,
	CryptoWallets.FK_AssetTypeID,
	CryptoWallets.FK_WalletTypeID,
	AES_DECRYPT(encryptedCryptoWalletIDValue, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))) AS cryptoWalletIDValue,
	CryptoWallets.creationDate,
	CryptoWallets.sid,
	AssetTypes.assetTypeLabel,
	AES_DECRYPT(WalletTypes.encryptedWalletTypeLabel, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))) AS walletTypeLabel
FROM
	CryptoWallets
	INNER JOIN AssetTypes ON CryptoWallets.FK_AssetTypeID = AssetTypes.assetTypeID AND AssetTypes.languageCode = 'EN'
	INNER JOIN WalletTypes ON CryptoWallets.FK_WalletTypeID = WalletTypes.walletTypeID AND WalletTypes.languageCode = 'EN'
WHERE
	CryptoWallets.FK_AccountID = :accountID";
	
	
			if ($assetType > 0)
			{
				$getCryptoWalletRecordSQL	= $getCryptoWalletRecordSQL." AND
	CryptoWallets.FK_AssetTypeID = :assetType";		
			}
			
			if ($walletType > 0)
			{
				$getCryptoWalletRecordSQL	= $getCryptoWalletRecordSQL." AND
	CryptoWallets.FK_WalletTypeID = :walletType";	
			}

			$getCryptoWalletRecord			= $dbh -> prepare($getCryptoWalletRecordSQL);

			$getCryptoWalletRecord -> bindValue(':accountID', $userAccountID);
			
			if ($assetType > 0)
			{ 
				$getCryptoWalletRecord -> bindValue(':assetType', $assetType);
			}
			
			if ($walletType > 0)
			{ 
				$getCryptoWalletRecord -> bindValue(':walletType', $walletType);
			}
						
			if ($getCryptoWalletRecord -> execute() && $getCryptoWalletRecord -> rowCount() > 0)
			{
				$row 					= $getCryptoWalletRecord -> fetchObject();
				
				$walletID				= $row->walletID; 
				$accountID				= $row->FK_AccountID;
				$authorID				= $row->FK_AuthorID;
				$assetType				= $row->FK_AssetTypeID;
				$assetTypeName			= $row->assetTypeLabel;
				$cryptoWalletIDValue	= $row->cryptoWalletIDValue;
				$walletType				= $row->FK_WalletTypeID;
				$walletTypeName			= $row->assetTypeLabel;
				$creationDate			= $row->walletTypeLabel;
				$sid					= $row->sid;
				
				$cryptoWallet			= new CryptoWallet($walletID, $accountID, $authorID, $cryptoWalletIDValue, $assetType, $assetTypeName, $walletType, $walletTypeName, $creationDate, $sid);	

				$walletsForUser[]		= $cryptoWallet;

			}
		}
	    catch (PDOException $e) 
	    {
	    	$cryptoWallet 				= null;	
			
			errorLog($e -> getMessage());
	
			die();
		}
		
		return $walletsForUser;		
	}
	
	function sendAccountVerificationEmail($userAccount, $emailAddress, $validationHash)
	{
		$serverRoot							= "https://app.profitstance.com";
		
		if (isset($_SESSION['serverRoot']))
		{
			$serverRoot						= $_SESSION['serverRoot'];	
		}
		
		$responseObject						= array();
		
		$emailBody							= "<!doctype html>
<html lang=\"en\">
	<head>
		<meta charset=\"utf-8\">
		<meta name=\"viewport\" content=\"width=device-width\">
		<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">
		<link href=\"https://app.profitstance.com/assets/fonts/AvenirNextFont.css\" rel=\"stylesheet\" type=\"text/css\">
		<style>
			
			p {
				font-family: 'Avenir Next', 'Open Sans', Arial, sans-serif;
				padding: 0;
				margin: 0;
			}

			.action-button {
				height: 42px;	
				width: 230px;	
				background-color: #00D1B2;
				display: block;
				text-align: center;
				text-decoration: none;
				text-transform: uppercase;
				color: #FFFFFF;	font-family: \"Avenir Next\";	font-size: 14px;	font-weight: bold;	line-height: 19px;
			}
			
			.wrapper-inner {
				max-width: 600px;
				width: 100%;
				margin: 0 auto;
				background: #FFFFFF;
			}
			
			.outer-table {
				width: 100%;
			}
			
			.main-table {
				width: 100%;
			}
			
			.header-wrapper {
				background-color: #0389FF;
			}
			
			.header {
				height: 27px;
			}
			
			.footer-wrapper {
				background-color: #1C364D;
			}
			
			.tenPxHeight {
				height: 10px;
				width: 100%;
			}
			
			.noMargin {
				margin: 0;
			}
			
			.noPadding {
				padding: 0;
			}
			
			.left-Padding-to-outer {
				width: 50px;
			}
			
			.right-Padding-to-outer {
				width: 25px;
			}
			
			.link {
				color: #0389FF;
				text-decoration: none;
			}
			
			.wrapper {
				width: 100%;
				bgColor: #efefef;
				background-color: #efefef;
			}
			
			.hint {
				color: #fa0068;
			}
		</style>
		
		
	</head>
	<body class=\"noPadding noMargin\" style=\"margin: 0;padding: 0;\">
		<div class=\"wrapper\" style=\"width: 100%;bgcolor: #efefef;background-color: #efefef;\">
			<div class=\"wrapper-inner\" style=\"max-width: 600px;width: 100%;margin: 0 auto;background: #FFFFFF;\">
				
				<table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" class=\"outer-table header-wrapper\" style=\"width: 100%;background-color: #0389FF;\">
					<tr height=\"50\">
						<td class=\"left-Padding-to-outer\" style=\"width: 50px;\"></td>
						<td class=\"header\" style=\"height: 27px; min-width: 291px;\">
							<p class=\"noPadding noMargin\" style=\"font-family: 'Avenir Next', 'Open Sans', Arial, sans-serif;padding: 0;margin: 0;\">
								<a href=\"https://profitstance.com/\" target=\"_blank\" style=\"display: block; height: 20px;\">
									<img style=\"display: block; height: 20px; width: 180px;\" title=\"logo\"
									src=\"https://app.profitstance.com/assets/img/white-logo.png\" alt=\"logo\">
									
								</a>
							</p>
						</td>
						<td class=\"right-Padding-to-outer\" style=\"width: 25px;\"></td>
					</tr>
				</table> <!--End Main Table-->
				
				<table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" class=\"main-table\" style=\"width: 100%;\">
					<tr>
						<td class=\"left-Padding-to-outer\" style=\"width: 50px;\"></td>
						<td class=\"one-column\">
							<div class=\"section\">
								<table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\">
									<tr height=\"150\">
										<td>
											<p style=\"color: #4C5B63;font-size: 36px;font-weight: bold;line-height: 49px;font-family: 'Avenir Next', 'Open Sans', Arial, sans-serif;padding: 0;margin: 0;\">
												
												<!-- TITLE HERE -->
												Email Address Verification
											</p>
										</td>
									</tr>
								</table>
							</div>
							
							<div class=\"section\">
								<table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\">
									<tr>
										<td>
											<p style=\"color: #5D6D7C;font-size: 14px;font-weight: 500;line-height: 26px;font-family: 'Avenir Next', 'Open Sans', Arial, sans-serif;padding: 0;margin: 0;\">
												
												
												<!-- MESSAGE HERE -->
												
												
												In order to start using your ProfitStance account, you need to confirm your email address. Please click on the link below to verify your email address and complete your account creation process.

											</p>
										</td>
									</tr>
									<tr height=\"30\"></tr>
									<tr>
										<td>
											<p style=\"color: #5D6D7C;font-size: 14px;font-weight: 500;line-height: 26px;font-family: 'Avenir Next', 'Open Sans', Arial, sans-serif;padding: 0;margin: 0;\">
												
												<!-- MESSAGE HERE -->
																							
												To finish creating your account just click the button below.

											</p>
										</td>
									</tr>
								</table>
							</div>
							
							
							<div class=\"section\">
								<table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\">
									<tr height=\"50\"></tr>
									<tr>
										<td>
											<table border=\"0\" cellpadding=\"0\" cellspacing=\"0\">
												<tr>
													<td> 
														
														<!-- CALL TO ACTION BUTTON -->
														<!-- ADD LINK TO THE A TAG HREF ATTRIBUTE -->
														
														
														<a class=\"action-button\" href=\"$serverRoot/signin?vh=$validationHash\" target=\"_blank\" style=\"background-color: #00D1B2;display: block;text-align: center;text-decoration: none;text-transform: uppercase;color: #FFFFFF;font-family: &quot;Avenir Next&quot;;font-size: 14px;font-weight: bold;line-height: 19px;\">
															<table border=\"0\" cellpadding=\"0\" width=\"100%\" cellspacing=\"0\">
																<tr height=\"42\" style=\"text-align: center;\">
																	<td style=\"height: 42px;width: 230px;\">
																		
																		
																		<!-- CALL TO ACTION TEXT -->
																		
																		
																		Verify Email Address
																		
																		
																		
																	</td>
																</tr>
															</table>
														</a> 
														
														
														
														
														
													</td>
											    </tr>
											</table>
										</td>
									</tr>
									<tr height=\"56\"></tr>
								</table>
							</div>
							
							
							<div class=\"section\">
								<table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\">
									<tr>
										<td>
											<p style=\"color: #5D6D7C;font-size: 14px;font-weight: 500;line-height: 26px;font-family: 'Avenir Next', 'Open Sans', Arial, sans-serif;padding: 0;margin: 0;\">
												Or copy and paste this link in your browser:
											</p>
										</td>
									</tr>
									
									<tr>
										<td>
											<p style=\"color: #0389FF;font-size: 12px;font-weight: 500;line-height: 26px;font-family: 'Avenir Next', 'Open Sans', Arial, sans-serif;padding: 0;margin: 0;\">
											  
											  
											  
											  <!-- CHANGE HREF TO LINK USED IN THE CALL TO ACTION -->
											  <!-- CHANGE BOTH HREF AND LINK TEXT -->
											  
											  
											  <a class=\"link\" href=\"$serverRoot/signin?vh=$validationHash\" style=\"color: #0389FF;text-decoration: none;\">	
											  	$serverRoot/signin?vh=$validationHash
											  </a>
											  
											  
											  
											  
											</p>
										</td>
									</tr>
									<tr height=\"50\"></tr>
								</table>
							</div>
							
							
							<div class=\"section\">
								<table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\">
									<tr>
										<td>
											<p style=\"color: #5D6D7C;font-size: 14px;font-weight: 500;line-height: 26px;font-family: 'Avenir Next', 'Open Sans', Arial, sans-serif;padding: 0;margin: 0;\">
												Questions? Send us a <a class=\"link\" href=\"https://profitstance.com/contact/\" target=\"_blank\" style=\"color: #0389FF;text-decoration: none;\">message</a>.
											</p>
										</td>
									</tr>
									<tr height=\"20\"></tr>
								</table>
							</div>
														
						</td>
						<td class=\"right-Padding-to-outer\" style=\"width: 25px;\"></td>
					</tr>
				</table>
				
				<table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" class=\"outer-table footer-wrapper\" style=\"width: 100%;background-color: #1C364D;\">
					<tr height=\"50\">
						<td class=\"left-Padding-to-outer\" style=\"width: 50px;\"></td>
						<td class=\"footer\" style=\"min-width: 291px;\">
							<p class=\"noPadding noMargin\" style=\"color: #FFFFFF;font-size: 12px;font-weight: 500;line-height: 16px;font-family: 'Avenir Next', 'Open Sans', Arial, sans-serif;padding: 0;margin: 0;\">
								Disclaimer Text?							
							</p>
						</td>
						<td class=\"right-Padding-to-outer\" style=\"width: 25px;\"></td>
					</tr>
				</table> <!--End Main Table-->
				
			</div> <!--End Wrapper Inner-->
		</div>  <!--End Wrapper-->
	</body>
</html>";


		$emailFactory						= new EmailFactory($emailAddress, $emailBody, "ProfitStance Email Address Verification", "admin@profitstance.com");
		
		$result								= $emailFactory -> sendEmail();
		
		if ($result == 1)
		{
			$responseObject['sentEmail']	= true;	
			$responseObject['result']		= "An account verification message has been sent to the email address you provided.  Please click on the verification link, and your account will be active.";
		}
		else
		{
			$responseObject['sentEmail']	= false;
			$responseObject['result']		= "We were unable to send an email to the email address you provided.  Please verify your email account.";
		}
		
		return $responseObject;		
	}
	
	function sendFriendInvitationEmail($emailAddress, $inviteCode, $invitationFromName)
	{
		if (empty($invitationFromName))
		{
			$invitationFromName				= "a friend";
		}
		
		$serverRoot							= "https://app.profitstance.com";
		
		if (isset($_SESSION['serverRoot']))
		{
			$serverRoot						= $_SESSION['serverRoot'];	
		}
		
		$responseObject						= array();
		
		$emailBody							= "<!doctype html>
<html lang=\"en\">
	<head>
		<meta charset=\"utf-8\">
		<meta name=\"viewport\" content=\"width=device-width\">
		<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">
		<link href=\"https://app.profitstance.com/assets/fonts/AvenirNextFont.css\" rel=\"stylesheet\" type=\"text/css\">
		<style>
			
			p {
				font-family: 'Avenir Next', 'Open Sans', Arial, sans-serif;
				padding: 0;
				margin: 0;
			}

			.action-button {
				height: 42px;	
				width: 230px;	
				background-color: #00D1B2;
				display: block;
				text-align: center;
				text-decoration: none;
				text-transform: uppercase;
				color: #FFFFFF;	font-family: \"Avenir Next\";	font-size: 14px;	font-weight: bold;	line-height: 19px;
			}
			
			.wrapper-inner {
				max-width: 600px;
				width: 100%;
				margin: 0 auto;
				background: #FFFFFF;
			}
			
			.outer-table {
				width: 100%;
			}
			
			.main-table {
				width: 100%;
			}
			
			.header-wrapper {
				background-color: #0389FF;
			}
			
			.header {
				height: 27px;
			}
			
			.footer-wrapper {
				background-color: #1C364D;
			}
			
			.tenPxHeight {
				height: 10px;
				width: 100%;
			}
			
			.noMargin {
				margin: 0;
			}
			
			.noPadding {
				padding: 0;
			}
			
			.left-Padding-to-outer {
				width: 50px;
			}
			
			.right-Padding-to-outer {
				width: 25px;
			}
			
			.link {
				color: #0389FF;
				text-decoration: none;
			}
			
			.wrapper {
				width: 100%;
				bgColor: #efefef;
				background-color: #efefef;
			}
			
			.hint {
				color: #fa0068;
			}
		</style>
		
		
	</head>
	<body class=\"noPadding noMargin\" style=\"margin: 0;padding: 0;\">
		<div class=\"wrapper\" style=\"width: 100%;bgcolor: #efefef;background-color: #efefef;\">
			<div class=\"wrapper-inner\" style=\"max-width: 600px;width: 100%;margin: 0 auto;background: #FFFFFF;\">
				
				<table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" class=\"outer-table header-wrapper\" style=\"width: 100%;background-color: #0389FF;\">
					<tr height=\"50\">
						<td class=\"left-Padding-to-outer\" style=\"width: 50px;\"></td>
						<td class=\"header\" style=\"height: 27px; min-width: 291px;\">
							<p class=\"noPadding noMargin\" style=\"font-family: 'Avenir Next', 'Open Sans', Arial, sans-serif;padding: 0;margin: 0;\">
								<a href=\"https://profitstance.com/\" target=\"_blank\" style=\"display: block; height: 20px;\">
									<img style=\"display: block; height: 20px; width: 180px;\" title=\"logo\"
									src=\"https://app.profitstance.com/assets/img/white-logo.png\" alt=\"logo\">
									
								</a>
							</p>
						</td>
						<td class=\"right-Padding-to-outer\" style=\"width: 25px;\"></td>
					</tr>
				</table> <!--End Main Table-->
				
				<table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" class=\"main-table\" style=\"width: 100%;\">
					<tr>
						<td class=\"left-Padding-to-outer\" style=\"width: 50px;\"></td>
						<td class=\"one-column\">
							<div class=\"section\">
								<table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\">
									<tr height=\"150\">
										<td>
											<p style=\"color: #4C5B63;font-size: 36px;font-weight: bold;line-height: 49px;font-family: 'Avenir Next', 'Open Sans', Arial, sans-serif;padding: 0;margin: 0;\">
												
												<!-- TITLE HERE -->
												
												
												Friend Invitation
												
												
												
												
											</p>
										</td>
									</tr>
								</table>
							</div>
							
							<div class=\"section\">
								<table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\">
									<tr>
										<td>
											<p style=\"color: #5D6D7C;font-size: 14px;font-weight: 500;line-height: 26px;font-family: 'Avenir Next', 'Open Sans', Arial, sans-serif;padding: 0;margin: 0;\">
												
												
												<!-- MESSAGE HERE -->
												
												
												Welcome! You’ve been invited by $invitationFromName to join the exclusive ProfitStance early access beta. ProfitStance is the premiere tax and accounting platform for all crypto investors. With ProfitStance as your partner, you will have complete control over your crypto investments and tax obligations.
												
												
												
												
											</p>
										</td>
									</tr>
									<tr height=\"30\"></tr>
									<tr>
										<td>
											<p style=\"color: #5D6D7C;font-size: 14px;font-weight: 500;line-height: 26px;font-family: 'Avenir Next', 'Open Sans', Arial, sans-serif;padding: 0;margin: 0;\">
												
												
												<!-- MESSAGE HERE -->
												
												
												To get started, just create your account by clicking the button below.
												
												
												
												
											</p>
										</td>
									</tr>
								</table>
							</div>
							
							
							<div class=\"section\">
								<table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\">
									<tr height=\"50\"></tr>
									<tr>
										<td>
											<table border=\"0\" cellpadding=\"0\" cellspacing=\"0\">
												<tr>
													<td> 
														
														<!-- CALL TO ACTION BUTTON -->
														<!-- ADD LINK TO THE A TAG HREF ATTRIBUTE -->
														
														
														<a class=\"action-button\" href=\"$serverRoot/invite-signup?invite=$inviteCode\" target=\"_blank\" style=\"background-color: #00D1B2;display: block;text-align: center;text-decoration: none;text-transform: uppercase;color: #FFFFFF;font-family: &quot;Avenir Next&quot;;font-size: 14px;font-weight: bold;line-height: 19px;\">
															<table border=\"0\" cellpadding=\"0\" width=\"100%\" cellspacing=\"0\">
																<tr height=\"42\" style=\"text-align: center;\">
																	<td style=\"height: 42px;width: 230px;\">
																		
																		
																		<!-- CALL TO ACTION TEXT -->
																		
																		
																		Create Account
																		
																		
																		
																	</td>
																</tr>
															</table>
														</a> 
														
														
														
														
														
													</td>
											    </tr>
											</table>
										</td>
									</tr>
									<tr height=\"56\"></tr>
								</table>
							</div>
							
							
							<div class=\"section\">
								<table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\">
									<tr>
										<td>
											<p style=\"color: #5D6D7C;font-size: 14px;font-weight: 500;line-height: 26px;font-family: 'Avenir Next', 'Open Sans', Arial, sans-serif;padding: 0;margin: 0;\">
												Or copy and paste this link in your browser:
											</p>
										</td>
									</tr>
									
									<tr>
										<td>
											<p style=\"color: #0389FF;font-size: 12px;font-weight: 500;line-height: 26px;font-family: 'Avenir Next', 'Open Sans', Arial, sans-serif;padding: 0;margin: 0;\">
											  
											  
											  
											  <!-- CHANGE HREF TO LINK USED IN THE CALL TO ACTION -->
											  <!-- CHANGE BOTH HREF AND LINK TEXT -->
											  
											  
											  <a class=\"link\" href=\"$serverRoot/invite-signup?invite=$inviteCode\" style=\"color: #0389FF;text-decoration: none;\">	
											  	$serverRoot/invite-signup?invite=$inviteCode
											  </a>
											  
											  
											  
											  
											</p>
										</td>
									</tr>
									<tr height=\"50\"></tr>
								</table>
							</div>
							
							
							<div class=\"section\">
								<table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\">
									<tr>
										<td>
											<p style=\"color: #5D6D7C;font-size: 14px;font-weight: 500;line-height: 26px;font-family: 'Avenir Next', 'Open Sans', Arial, sans-serif;padding: 0;margin: 0;\">
												Questions? Send us a <a class=\"link\" href=\"https://profitstance.com/contact/\" target=\"_blank\" style=\"color: #0389FF;text-decoration: none;\">message</a>.
											</p>
										</td>
									</tr>
									<tr height=\"20\"></tr>
								</table>
							</div>
														
						</td>
						<td class=\"right-Padding-to-outer\" style=\"width: 25px;\"></td>
					</tr>
				</table>
				
				<table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" class=\"outer-table footer-wrapper\" style=\"width: 100%;background-color: #1C364D;\">
					<tr height=\"50\">
						<td class=\"left-Padding-to-outer\" style=\"width: 50px;\"></td>
						<td class=\"footer\" style=\"min-width: 291px;\">
							<p class=\"noPadding noMargin\" style=\"color: #FFFFFF;font-size: 12px;font-weight: 500;line-height: 16px;font-family: 'Avenir Next', 'Open Sans', Arial, sans-serif;padding: 0;margin: 0;\">
								Disclaimer Text?							
							</p>
						</td>
						<td class=\"right-Padding-to-outer\" style=\"width: 25px;\"></td>
					</tr>
				</table> <!--End Main Table-->
				
			</div> <!--End Wrapper Inner-->
		</div>  <!--End Wrapper-->
	</body>
</html>";


		$emailFactory						= new EmailFactory($emailAddress, $emailBody, "ProfitStance Friend Invitation", "admin@profitstance.com");
		
		$result								= $emailFactory -> sendEmail();
		
		if ($result == 1)
		{
			$responseObject['sentEmail']	= true;	
			$responseObject['result']		= "An email has been sent to the email address you provided, with an account creation link.";
		}
		else
		{
			$responseObject['sentEmail']	= false;
			$responseObject['result']		= "We were unable to send an email to the email address you provided.  Please verify your email account.";
		}
		
		return $responseObject;		
	}
	
	function sendPasswordRestEmail($emailAddress, $resetHash)
	{
		$serverRoot							= "https://app.profitstance.com";
		
		if (isset($_SESSION['serverRoot']))
		{
			$serverRoot						= $_SESSION['serverRoot'];	
		}
		
		$responseObject						= array();
		
		$emailBody							= "<!DOCTYPE html>
<html lang=\"en\">
<head>
      <meta http-equiv=\"Content-Type\" content=\"text/html;charset=UTF-8\"/>
      <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
      <meta http-equiv=\"X-UA-Compatible\" content=\"ie=edge\">
      <title>Password Reset Verification</title>

      <style>
            /* Gmail Fix */
            li a[href] {
                  text-decoration:none !important;
                  color: #001;
            }
      </style>



      <link rel=\"stylesheet\" href=\"https://fonts.googleapis.com/css?family=Lato:100,300,400,700,900\" type=\"text/css\">
</head>
<body style=\"margin:0; padding:0; background:#efefef; width: 100%;\">

      <table align=\"center\" style=\"background: #ffffff; max-width:600px; width: 100%; margin:0 auto; border-collapse:collapse; text-align:center; border-spacing: 0; border:none; box-shadow: 0px 0px 4px -2px black;\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\">
            <tr> 
                  <td width=\"90\">
                        <table style=\"max-width:580px; width: 100%; margin: 0 auto;\">
                              <tr style=\"border-spacing:0; padding:0; margin:0; border:0;\">
                                    <td style=\"border:0; margin:0; vertical-align: middle; padding-top: 50px; padding-bottom: 50px; \">

                                          <span style=\"border:0; margin:0; vertical-align: middle; color: #4c5a61;padding:0; border:none; text-align:center; font-size:30px; line-height:42px; font-weight:lighter; font-family: 'Lato', sans-serif;\">PROFIT</span>

                                          <span style=\"border:0; margin:0; vertical-align: middle; color: #4c5a61; padding:0; border:none; text-align:center; font-size:30px; line-height:42px; font-weight:bold; font-family: 'Lato', sans-serif;\"><b>STANCE</b></span>
                                    </td>
                              </tr>
                              <tr>
                                    <td style=\"border:0; margin:0; vertical-align: middle; padding:0; border:none; text-align:center; font-weight:400; font-family: 'Lato', sans-serif; font-size: 35px;\">
                                          Password Reset
                                    </td>
                              </tr>
                              <tr><td height=\"60\"> &nbsp; </td></tr>

                              <tr>
                                    <td style=\"border:0; margin:0; vertical-align: middle; padding:0; border:none; text-align:center; font-weight: 300; line-height: 25px; font-family: 'Lato', sans-serif;\">
                                          <table>
                                                <tr>
                                                      <td width=\"40\"></td>
                                                      <td>A password reset request has been submitted for $emailAddress.  In order to reset your password, please click on the link below.</td>
                                                      <td width=\"40\"></td>
                                                </tr>
                                          </table>
                                          
                                    </td>
                              </tr>
                              <tr><td height=\"60\"> &nbsp; </td></tr>
                              <tr>
                                    <td style=\"border:0; margin:0; vertical-align: middle; padding:0; border:none; text-align:center; font-weight:500; font-family: 'Lato', sans-serif;\">
                                          <a href=\"$serverRoot/update-password/$resetHash\" style=\"background: #4c5a61; color: #ffffff; padding: 15px; border-radius: 4px; text-decoration: none;\">
                                                Reset your password
                                          </a>
                                    </td>
                              </tr>
                              <tr><td height=\"50\"> &nbsp; </td></tr>
                              <tr>
                                    <td style=\"border:0; margin:0; vertical-align: middle; padding:0; border:none; text-align:center; font-weight:300; font-family: 'Lato', sans-serif;\">
                                          Or copy and paste this link in your browser
                                    </td>
                              </tr>
                              <tr>
                                    <td style=\"border:0; margin:0; vertical-align: middle; padding:0; border:none; text-align:center; font-weight: 300; line-height: 25px; font-family: 'Lato', sans-serif;\">
                                          <a href=\"$serverRoot/update-password/$resetHash\">$serverRoot/update-password/$resetHash</a>
                                    </td>
                              </tr>
                              <tr><td height=\"25\"> &nbsp; </td></tr>
                              <tr><td> <span style=\"max-width: 200px;  height: 2px;  background: #4b5960; display: block; margin: 0 auto;\"></span> </td></tr>
                              <tr><td height=\"25\"> &nbsp; </td></tr>
                              <tr>
                                    <td style=\"border:0; margin:0; vertical-align: middle; padding:0; border:none; text-align:center; font-weight:300; font-family: 'Lato', sans-serif; font-size: 12px; font-style: italic; color: #b7b7b7;\">
                                        If you did not submit this password reset request, please contact support.
                                    </td>
                              </tr>
                              <tr><td height=\"60\"></td></tr>
                        </table>
                  </td>
            </tr>
      </table>
      
</body>
</html>";

		$emailFactory										= new EmailFactory($emailAddress, $emailBody, "Reset ProfitStance Account Password", "admin@profitstance.com");
		
		$result												= $emailFactory -> sendEmail();
		
		if ($result == 1)
		{
			$responseObject['passwordResetEmailSent']		= true;	
			$responseObject['resultMessage']				= "A password reset email message has been sent to the email address that was entered.";
		}
		else
		{
			$responseObject['passwordResetEmailSent']		= false;
			$responseObject['resultMessage']				= "We were unable to send a password reset email to the email address you provided.  Please verify your email account.";
		}
		
		return $responseObject;		
	}
	
	function storeAssetExchangePriceRecord($assetTypeLabel, $globalCurrentDate, $languageCode, $sourceID, $exchangeValue, $sid, $dbh)
	{
		$assetInformationObject			= new AssetExchangeInfo(0, $assetTypeLabel, $globalCurrentDate, $languageCode, $exchangeValue, $sid, $sourceID);
		
		$recordID						= 0;
		
		try
		{	
			$checkForAssetInfo			= $dbh->prepare("SELECT
	assetExchangeEventID,
	assetTypeLabel,
	valuationDateTime,
	languageCode,
	exchangeValue,
	sid,
	FK_CurrencyPriceValueSources
FROM
	AssetExchangeValues
WHERE
	assetTypeLabel = :assetTypeLabel AND
	valuationDateTime = :valuationDateTime");
			
			$insertAssetInfo			= $dbh->prepare("INSERT INTO AssetExchangeValues
(
	assetTypeLabel,
	valuationDateTime,
	languageCode,
	exchangeValue,
	sid,
	FK_CurrencyPriceValueSources
)
VALUES
(
	:assetTypeLabel,
	:valuationDateTime,
	:languageCode,
	:exchangeValue,
	:sid,
	:FK_CurrencyPriceValueSources
)");
	
			$checkForAssetInfo -> bindValue(':assetTypeLabel', $assetTypeLabel);
			$checkForAssetInfo -> bindValue(':valuationDateTime', $globalCurrentDate);
				
			if ($checkForAssetInfo -> execute() && $checkForAssetInfo -> rowCount() > 0)
			{
				$row 							= $checkForAssetInfo -> fetchObject();
				
				$thisAssetExchangeEventID		= $row -> assetExchangeEventID;
				$thiAssetTypeLabel				= $row -> assetTypeLabel;
				$thisValuationDateTime			= $row -> valuationDateTime;
				$thisLanguageCode				= $row -> languageCode;
				$thisExchangeValue				= $row -> exchangeValue;
				$thisSid						= $row -> sid;
				$thisCurrencyPriceValueSources	= $row -> FK_CurrencyPriceValueSources;
				
				$assetInformationObject			= new AssetExchangeInfo($thisAssetExchangeEventID, $thiAssetTypeLabel, $thisValuationDateTime, $thisLanguageCode, $thisExchangeValue, $thisSid, $thisCurrencyPriceValueSources);
			}
			else
			{
				// calculate details
				
				$insertAssetInfo -> bindValue(':assetTypeLabel', $assetTypeLabel);
				$insertAssetInfo -> bindValue(':valuationDateTime', $globalCurrentDate);
				$insertAssetInfo -> bindValue(':languageCode', $languageCode);
				$insertAssetInfo -> bindValue(':exchangeValue', $exchangeValue);
				$insertAssetInfo -> bindValue(':sid', $sid);
				$insertAssetInfo -> bindValue(':FK_CurrencyPriceValueSources', $sourceID);
				
				if ($insertAssetInfo -> execute())
				{
					$recordID 					= $dbh -> lastInsertId();	
					
					$assetInformationObject -> setAssetExchangeEventID($recordID);
				}
			}

			$dbh 				= null;	
		}
	    catch (PDOException $e) 
	    {
	    	errorLog($e->getMessage());
	
			die();
		}
		return $assetInformationObject;
	}

	function storeAssetInformationRecord($assetTypeLabel, $assetDescription, $minSizePattern, $sourceID, $globalCurrentDate, $sid, $dbh)
	{
		if (empty($assetDescription))
		{
			$assetDescription			= "";
		}
		
		$assetInformationObject			= new AssetInfo();
		
		$responseObject					= $assetInformationObject -> instantiateAssetInfoUsingAssetTypeLabel($assetTypeLabel, $dbh);
		
		if ($responseObject['foundAssetID' == false] || $responseObject['instantiatedAssetByID'] == false)
		{
			$assetInformationObject -> setData(0, $assetTypeLabel, $assetDescription, $minSizePattern, "EN", $globalCurrentDate, $globalCurrentDate, $sourceID, 0, "", $sid, $dbh);
		
			$insertResult				= $assetInformationObject -> writeToDatabase($dbh);	
		}

		return $assetInformationObject;
	}
	
	function storeCryptoCurrencyBuyPrice($cryptoCurrencyType, $fiatCurrencyType, $buyDateTime, $buyValue, $buyPriceSource, $globalCurrentDate, $sid, $dbh)
	{
		$buyPriceObject		 	= null;
		
		try
		{	
			$insertBuyPrice		= $dbh->prepare("INSERT INTO BuyPricesByDateTime
(
	FK_CryptocurrencyAssetTypeID,
	FK_FiatAssetTypeID,
	buyDateTime,
	buyValue,
	FK_BuyPriceSource,
	creationDate,
	sid
)
VALUES
(
	:FK_CryptocurrencyAssetTypeID,
	:FK_FiatAssetTypeID,
	:buyDateTime,
	:buyValue,
	:FK_BuyPriceSource,
	:creationDate,
	:sid
)");


			$buyPriceObject		= getCryptoCurrencyBuyPrice($cryptoCurrencyType, $fiatCurrencyType, $buyDateTime, $buyPriceSource, $dbh);
			
			if (is_null($buyPriceObject))
			{
				$insertBuyPrice -> bindValue(':FK_CryptocurrencyAssetTypeID', $cryptoCurrencyType);
				$insertBuyPrice -> bindValue(':FK_FiatAssetTypeID', $fiatCurrencyType);
				$insertBuyPrice -> bindValue(':buyDateTime', $buyDateTime);
				$insertBuyPrice -> bindValue(':buyValue', $buyValue);
				$insertBuyPrice -> bindValue(':FK_BuyPriceSource', $buyPriceSource);
				$insertBuyPrice -> bindValue(':creationDate', $globalCurrentDate);
				$insertBuyPrice -> bindValue(':sid', $sid);
				
				if ($insertBuyPrice -> execute())
				{
					errorLog("Success: $cryptoCurrencyType, $fiatCurrencyType, $buyDateTime, $buyValue, $buyPriceSource, $globalCurrentDate, $sid");
					$buyPriceObject				= new CryptoPrice($cryptoCurrencyType, $fiatCurrencyType, $buyDateTime, $buyValue, $buyPriceSource, $globalCurrentDate, $sid);
				}
				else
				{
					errorLog("ERROR: could not storeCryptoCurrencyBuyPrice for $cryptoCurrencyType, $fiatCurrencyType, $buyDateTime, $buyValue, $buyPriceSource, $globalCurrentDate, $sid");
				}	
			}

			$dbh 				= null;	
		}
	    catch (PDOException $e) 
	    {
	    	errorLog($e->getMessage());
	
			die();
		}
		return $buyPriceObject;
	}
	
	function storeCryptoCurrencySellPrice($cryptoCurrencyType, $fiatCurrencyType, $sellDateTime, $sellValue, $sellPriceSource, $globalCurrentDate, $sid, $dbh)
	{
		$sellPriceObject		 	= null;
		
		try
		{	
			$insertSellPrice		= $dbh->prepare("INSERT INTO SellPricesByDateTime
(
	FK_CryptocurrencyAssetTypeID,
	FK_FiatAssetTypeID,
	sellDateTime,
	sellValue,
	FK_SellPriceSourceID,
	creationDate,
	sid
)
VALUES
(
	:FK_CryptocurrencyAssetTypeID,
	:FK_FiatAssetTypeID,
	:sellDateTime,
	:sellValue,
	:FK_SellPriceSourceID,
	:creationDate,
	:sid
)");


			$sellPriceObject		= getCryptoCurrencySellPrice($cryptoCurrencyType, $fiatCurrencyType, $sellDateTime, $sellPriceSource, $dbh);
			
			if (is_null($sellPriceObject))
			{
				$insertSellPrice -> bindValue(':FK_CryptocurrencyAssetTypeID', $cryptoCurrencyType);
				$insertSellPrice -> bindValue(':FK_FiatAssetTypeID', $fiatCurrencyType);
				$insertSellPrice -> bindValue(':sellDateTime', $sellDateTime);
				$insertSellPrice -> bindValue(':sellValue', $sellValue);
				$insertSellPrice -> bindValue(':FK_SellPriceSourceID', $sellPriceSource);
				$insertSellPrice -> bindValue(':creationDate', $globalCurrentDate);
				$insertSellPrice -> bindValue(':sid', $sid);
				
				if ($insertSellPrice -> execute())
				{
					errorLog("Success: $cryptoCurrencyType, $fiatCurrencyType, $sellDateTime, $sellValue, $sellPriceSource, $globalCurrentDate, $sid");
					$sellPriceObject				= new CryptoPrice($cryptoCurrencyType, $fiatCurrencyType, $sellDateTime, $sellValue, $sellPriceSource, $globalCurrentDate, $sid);
				}
				else
				{
					"ERROR: could not storeCryptoCurrencySellPrice for $cryptoCurrencyType, $fiatCurrencyType, $sellDateTime, $sellValue, $sellPriceSource, $globalCurrentDate, $sid";
				}	
			}

			$dbh 				= null;	
		}
	    catch (PDOException $e) 
	    {
	    	errorLog($e->getMessage());
	
			die();
		}
		return $sellPriceObject;
	}
	
	function storeCryptoCurrencySpotPrice($cryptoCurrencyType, $fiatCurrencyType, $spotDateTime, $spotValue, $spotPriceSource, $globalCurrentDate, $sid, $dbh)
	{
		$spotPriceObject		 	= null;
		
		try
		{	
			$insertSpotPrice		= $dbh->prepare("INSERT INTO SpotPricesByDateTime
(
	FK_CryptocurrencyAssetTypeID,
	FK_FiatAssetTypeID,
	spotDateTime,
	spotValue,
	FK_SpotPriceSourceID,
	creationDate,
	sid
)
VALUES
(
	:FK_CryptocurrencyAssetTypeID,
	:FK_FiatAssetTypeID,
	:spotDateTime,
	:spotValue,
	:FK_SpotPriceSourceID,
	:creationDate,
	:sid
)");


			$spotPriceObject		= getCryptoCurrencySpotPrice($cryptoCurrencyType, $fiatCurrencyType, $spotDateTime, $spotPriceSource, $dbh);
			
			if (is_null($spotPriceObject))
			{
				$insertSpotPrice -> bindValue(':FK_CryptocurrencyAssetTypeID', $cryptoCurrencyType);
				$insertSpotPrice -> bindValue(':FK_FiatAssetTypeID', $fiatCurrencyType);
				$insertSpotPrice -> bindValue(':spotDateTime', $spotDateTime);
				$insertSpotPrice -> bindValue(':spotValue', $spotValue);
				$insertSpotPrice -> bindValue(':FK_SpotPriceSourceID', $spotPriceSource);
				$insertSpotPrice -> bindValue(':creationDate', $globalCurrentDate);
				$insertSpotPrice -> bindValue(':sid', $sid);
				
				if ($insertSpotPrice -> execute())
				{
					errorLog("Success: $cryptoCurrencyType, $fiatCurrencyType, $spotDateTime, $spotValue, $spotPriceSource, $globalCurrentDate, $sid");
					$spotPriceObject				= new CryptoPrice($cryptoCurrencyType, $fiatCurrencyType, $spotDateTime, $spotValue, $spotPriceSource, $globalCurrentDate, $sid);
				}
				else
				{
					errorLog("ERROR: could not storeCryptoCurrencySpotPrice for $cryptoCurrencyType, $fiatCurrencyType, $spotDateTime, $spotValue, $spotPriceSource, $globalCurrentDate, $sid");
				}	
			}

			$dbh 				= null;	
		}
	    catch (PDOException $e) 
	    {
	    	errorLog($e->getMessage());
	
			die();
		}
		return $spotPriceObject;
	}

	function storeOAuthToken($userAccountID, $authorID, $walletType, $oauthCode, $oauthToken, $refreshToken, $validationHash, $globalCurrentDate, $sid, $dbh)
	{
		$returnValue		 	= 0;
		
		try
		{	
			$insertOauthCode	= $dbh->prepare("INSERT INTO OAuthTokens
(
	encryptedUserAccountID,
	encryptedAuthorAccountID,
	encryptedCodeValue,
	encryptedTokenValue,
	encryptedRefreshToken,
	creationDate,
	encryptedValidationHash,
	FK_WalletTypeID,
	sid,
	tokenStatusID
)
VALUES
(
	AES_ENCRYPT(:FK_AccountID, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	AES_ENCRYPT(:FK_AuthorID, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	AES_ENCRYPT(:oauthCode, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	AES_ENCRYPT(:oauthToken, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	AES_ENCRYPT(:refreshToken, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	:creationDate,
	AES_ENCRYPT(:validationHash, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	:FK_WalletTypeID,
	:sid,
	:tokenStatusID
)");
		
			$insertOauthCode -> bindValue(':FK_AccountID', $userAccountID);
			$insertOauthCode -> bindValue(':FK_AuthorID', $authorID);
			$insertOauthCode -> bindValue(':oauthCode', $oauthCode);
			$insertOauthCode -> bindValue(':oauthToken', $oauthToken);
			$insertOauthCode -> bindValue(':refreshToken', $refreshToken);
			$insertOauthCode -> bindValue(':creationDate', $globalCurrentDate);
			$insertOauthCode -> bindValue(':validationHash', $validationHash);
			$insertOauthCode -> bindValue(':FK_WalletTypeID', $walletType);
			$insertOauthCode -> bindValue(':sid', $sid);
			$insertOauthCode -> bindValue(':tokenStatusID', 1);
			
			if ($insertOauthCode -> execute())
			{
				errorLog("Success: storeOAuthToken for $userAccountID, $authorID, $walletType, $oauthCode, $validationHash, $globalCurrentDate, $sid");
				$returnValue 				= $dbh -> lastInsertId();
			}
			else
			{
				errorLog("ERROR: could not storeOAuthToken for $userAccountID, $authorID, $walletType, $oauthToken, $validationHash, $globalCurrentDate, $sid");
			}

			$dbh 				= null;	
		}
	    catch (PDOException $e) 
	    {
	    	$returnValue 		= -1;	
			
			errorLog($e->getMessage());
	
			die();
		}
		return $returnValue;
	}
	
	function updateOAuthRequestStatus($userAccountID, $authorID, $walletType, $validationHash, $globalCurrentDate, $sid, $dbh)
	{
		$returnValue		 												= 0;
		
		try
		{	
			$setOauthToken													= $dbh->prepare("UPDATE 
	OAuthConnectionValidationHash
SET
	OAuthConnectionValidationHash.requestStatus = 3
WHERE
	OAuthConnectionValidationHash.encryptedValidationHash = AES_ENCRYPT(:validationHash, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512)))");
		
			$setOauthToken -> bindValue(':validationHash', $validationHash);
			
			if ($setOauthToken -> execute())
			{
				$returnValue 												= 1;
				errorLog("successfully updated updateOAuthRequestStatus for $userAccountID, $authorID, $walletType, $validationHash, $globalCurrentDate, $sid");
			}
			else
			{
				errorLog("ERROR: could not updateOAuthRequestStatus for $userAccountID, $authorID, $walletType, $validationHash, $globalCurrentDate, $sid");
			}

			$dbh 															= null;	
		}
	    catch (PDOException $e) 
	    {
	    	$returnValue 													= -1;	
			
			errorLog($e->getMessage());
	
			die();
		}
		return $returnValue;
	}
	
	function updateOAuthToken($userAccountID, $retiredRefreshToken, $oauthToken, $refreshToken, $globalCurrentDate, $sid, $dbh)
	{
		$returnValue		 	= 0;
		
		try
		{	
			$updateOAuthToken	= $dbh->prepare("UPDATE
	OAuthTokens
SET
	encryptedTokenValue = AES_ENCRYPT(:oauthToken, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	encryptedRefreshToken = AES_ENCRYPT(:refreshToken, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512)))
WHERE
	AES_DECRYPT(OAuthTokens.encryptedUserAccountID, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))) = :accountID AND
	AES_DECRYPT(OAuthTokens.encryptedRefreshToken, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))) = :oldRefreshToken");
		
			$updateOAuthToken -> bindValue(':accountID', $userAccountID);
			$updateOAuthToken -> bindValue(':oauthToken', $oauthToken);
			$updateOAuthToken -> bindValue(':refreshToken', $refreshToken);
			$updateOAuthToken -> bindValue(':oldRefreshToken', $retiredRefreshToken);
			
			if ($updateOAuthToken -> execute())
			{
				errorLog("Success: updateOAuthToken for $userAccountID, $retiredRefreshToken, $oauthToken, $refreshToken, $globalCurrentDate, $sid");
				$returnValue 				= 1;
			}
			else
			{
				errorLog("ERROR: could not updateOAuthToken for $userAccountID, $retiredRefreshToken, $oauthToken, $refreshToken, $globalCurrentDate, $sid");
			}

			$dbh 				= null;	
		}
	    catch (PDOException $e) 
	    {
	    	$returnValue 		= -1;	
			
			errorLog($e->getMessage());
	
			die();
		}
		return $returnValue;
	}
	
	function updateCryptoTransactionUnspentBalance($userAccountID, $liuAccountID, $transactionID, $newUnspentAmount, $sid, $dbh)
	{
		$returnValue					= 0;
		
		try
		{	
			$updateTransactionRecord	= $dbh->prepare("UPDATE
	Transactions
SET
	unspentTransactionTotal = :newUnspentTransactionTotal
WHERE
	transactionID = :transactionID");
		
			$updateTransactionRecord -> bindValue(':newUnspentTransactionTotal', $newUnspentAmount);
			$updateTransactionRecord -> bindValue(':transactionID', $transactionID);
							
			if ($updateTransactionRecord -> execute())
			{
				$returnValue			= 1;
			}	
			
			$dbh 						= null;	
		}
	    catch (PDOException $e) 
	    {
	    	$returnValue 				= -1;	
			
			errorLog($e->getMessage());
	
			die();
		}
		return $returnValue;
	}
	
	function verifyAccountCreationValidationRequest($validationHash, $globalCurrentDate, $dbh)
	{
		$responseObject													= array();
		$responseObject['createAccountVerified']						= false;
		
		try
		{	
			$getValidationRequestData									= $dbh->prepare("SELECT
	FK_UserAccountID,
	requestDate,
	requestStatus,
	sid,
	AES_DECRYPT(encryptedEmailAddress, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))) AS decryptedEmailAddress
FROM
	AccountCreationValidationHash
WHERE
	encryptedValidationHash = AES_ENCRYPT(:validationHash, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512)))");
	
			$updateValidationRequestStatus								= $dbh->prepare("UPDATE 
	AccountCreationValidationHash
SET
	requestStatus = 1,
	verificationDate = :verificationDate
WHERE
	AES_DECRYPT(encryptedValidationHash, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))) = :validationHash AND
	FK_UserAccountID = :accountID");
	
			$updateAccountActiveStatus									= $dbh->prepare("UPDATE
	UserAccounts
SET
	isActive = 1
WHERE
	userAccountID = :accountID");
	
			$getValidationRequestData -> bindValue(':validationHash', $validationHash);
	
			if ($getValidationRequestData -> execute() && $getValidationRequestData -> rowCount() > 0)
			{
				$row 													= $getValidationRequestData -> fetchObject();
				
				$accountID												= $row -> FK_UserAccountID;
				$requestDate											= $row -> requestDate;
				$requestStatus											= $row -> requestStatus;
				$sid													= $row -> sid;
				$emailAddress											= $row -> decryptedEmailAddress;
				
				if ($requestStatus == 0)
				{
					$updateValidationRequestStatus -> bindValue(':verificationDate', $globalCurrentDate);
					$updateValidationRequestStatus -> bindValue(':validationHash', $validationHash);
					$updateValidationRequestStatus -> bindValue(':accountID', $accountID);
					
					$responseObject['emailAddress']						= $emailAddress;
					
					$responseObject['createAccountVerified']			= true;
					
					if ($updateValidationRequestStatus -> execute())
					{
						$updateAccountActiveStatus -> bindValue(':accountID', $accountID);
						
						if ($updateAccountActiveStatus -> execute())
						{
							$responseObject['resultMessage']			= "Your account has been verified.";	
						} 
						else
						{
							$responseObject['resultMessage']			= "Your account has been verified, but we could not set your account status to active.";	
						}	
					}
					else
					{
						$responseObject['resultMessage']				= "We were unable to verify your account.";		
					}
				}
				else if ($requestStatus == 1)
				{
					$responseObject['resultMessage']					= "Your account has already been verified.";
				}
			}
			
			$dbh 														= null;	
		}
	    catch (PDOException $e) 
	    {
	    	$responseObject['responseMessage']							= "Error: A database error has occurred.  Your account could not be verified. ".$e->getMessage();	
			$responseObject['createAccountVerified']					= false;
			errorLog($e->getMessage());
	
			die();
		}
		
		return $responseObject;	
	}
	
	function verifyIPOAuthSecret($liUser, $ipAccountID, $ipSecret, $mode, $globalCurrentDate, $dbh)
	{
		$responseObject												= array();
		
		try
		{	
			$validationSQL											= "";
	
			if ($mode == 1)
			{
				$responseObject['mode']								= "production";
				$responseObject['modeID']							= 1;
				
				// production mode
				$validationSQL										= "SELECT
	IntegrationPartnerAccounts.ipID,
	IntegrationPartnerAccounts.encryptedCompanyProdSecretKey AS encryptedCompanySecretKey
FROM
	IntegrationPartnerAccounts
WHERE
	IntegrationPartnerAccounts.ipID = :ipID AND
	AES_DECRYPT(IntegrationPartnerAccounts.encryptedCompanyProdToken, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))) = :providedIPToken";
			}
			else if ($mode == 2)
			{
				$responseObject['mode']								= "development";
				$responseObject['modeID']							= 1;
				
				// test mode
				$validationSQL										= "SELECT
	IntegrationPartnerAccounts.ipID,
	IntegrationPartnerAccounts.encryptedCompanyDevSecretKey AS encryptedCompanySecretKey
FROM
	IntegrationPartnerAccounts
WHERE
	IntegrationPartnerAccounts.ipID = :ipID AND
	AES_DECRYPT(IntegrationPartnerAccounts.encryptedCompanyDevToken, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))) = :providedIPToken";
			}
			
			
			$getValidationRequestData							= $dbh->prepare($validationSQL);
		
			$getValidationRequestData -> bindValue(':providedIPToken', $ipSecret);
			$getValidationRequestData -> bindValue(':ipID', $ipAccountID);
	
			if ($getValidationRequestData -> execute() && $getValidationRequestData -> rowCount() > 0)
			{
				$row 											= $getValidationRequestData -> fetchObject();
				
				$ipID											= $row -> ipID;
				$encryptedCompanySecretKey						= $row -> encryptedCompanySecretKey;
				
				if ($ipID == $ipAccountID && !empty($encryptedCompanySecretKey))
				{
					$responseObject['ipTokenVerified']			= true;
					$responseObject['responseMessage']			= "Thank you.  Your token has been verified.";	
				}
				else
				{
					$responseObject['ipTokenVerified']			= false;
					$responseObject['responseMessage']			= "Your token could not be verified.";	
				}
			}
			else
			{
				$responseObject['ipTokenVerified']				= false;
				$responseObject['responseMessage']				= "Your token could not be verified.";	
			}
			
			$dbh 												= null;	
		}
	    catch (PDOException $e) 
	    {
	    	$responseObject['responseMessage']					= "A database error has occurred.  Your token could not be verified.";	
			$responseObject['ipTokenVerified']					= false;
			$responseObject['modeID']							= $mode;
			errorLog($e->getMessage());
	
			die();
		}
		
		return $responseObject;	
	}

	function writeText($pdf, $text, $xPos, $yPos)
	{
		$pdf -> SetXY($xPos, $yPos);
		$pdf -> Write(0, $text);	
	}
	
	// BEGIN SECURITY QUESTION functions
	
	function getSecurityQuestionAnswersForUser($accountID, $dbh)
	{
		$responseObject					= array();
		
		try
		{	
			$getSecurityAnswers			= $dbh->prepare("SELECT
	SecurityQuestionAnswers.securityQuestionID,
	AES_DECRYPT(encryptedSecurityAnswer, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))) AS decryptedSecurityAnswer,
	SecurityQuestions.securityQuestionText
FROM
	SecurityQuestionAnswers
	INNER JOIN SecurityQuestions ON SecurityQuestionAnswers.securityQuestionID = SecurityQuestions.securityQuestionID AND SecurityQuestions.languageCode = 'EN'
WHERE
	FK_AccountID = :account");
	
			$getSecurityAnswers -> bindValue(':account', $accountID);
				
			if ($getSecurityAnswers -> execute() && $getSecurityAnswers -> rowCount() > 0)
			{
				while ($row = $getSecurityAnswers -> fetchObject())
				{
					$thisAnswer								= array();

					$securityQuestionID						= $row -> securityQuestionID;
					$securityQuestion						= $row -> securityQuestionText;
					$securityAnswerValue					= $row -> decryptedSecurityAnswer;
					
					$thisAnswer['securityAnswer']			= $securityAnswerValue;
					$thisAnswer['securityQuestion']			= $securityQuestion;
					$thisAnswer['securityQuestionID']		= $securityQuestionID;
					
					$responseObject[]						= $thisAnswer;
				}
			}

			$dbh 				= null;	
		}
	    catch (PDOException $e) 
	    {
		    errorLog($e->getMessage());
	
			die();
		}
		
		return $responseObject;
	}
	
	function setSecurityQuestionAnswer($questionID, $accountID, $authorID, $securityAnswer, $globalCurrentDate, $sid, $dbh)
	{
		$responseObject					= array();
		$responseObject['questionID']	= $questionID;
		
		try
		{	
			$setSecurityQuestionAnswer	= $dbh->prepare("REPLACE SecurityQuestionAnswers
(
	securityQuestionID,
	FK_AccountID,
	FK_AuthorID,
	creationDate,
	encryptedSecurityAnswer,
	sid
)
VALUES
(
	:securityQuestionID,
	:FK_AccountID,
	:FK_AuthorID,
	:creationDate,
	AES_ENCRYPT(:encryptedSecurityAnswer, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	:sid
)");
	
			$setSecurityQuestionAnswer -> bindValue(':securityQuestionID', $questionID);
			$setSecurityQuestionAnswer -> bindValue(':FK_AccountID', $accountID);
			$setSecurityQuestionAnswer -> bindValue(':FK_AuthorID', $authorID);
			$setSecurityQuestionAnswer -> bindValue(':creationDate', $globalCurrentDate);
			$setSecurityQuestionAnswer -> bindValue(':encryptedSecurityAnswer', $securityAnswer);
			$setSecurityQuestionAnswer -> bindValue(':sid', $sid);
				
			if ($setSecurityQuestionAnswer -> execute())
			{
				$responseObject['valueSet']			= true;
				$responseObject['resultMessage']	= "Successfully set the value of security question $questionID";	
			}
			else
			{
				$responseObject['valueSet']			= false;
				$responseObject['resultMessage']	= "Could not set the value of security question $questionID";
			}

			$dbh 				= null;	
		}
	    catch (PDOException $e) 
	    {
		    $responseObject['valueSet']				= false;
		    $responseObject['resultMessage']		= "Error: Could not set the value of security question $questionID. ".$e->getMessage();
	    	
	    	errorLog($e->getMessage());
	
			die();
		}
		
		return $responseObject;
	}
	
	function getSecurityQuestions($languageCode, $dbh)
	{
		$responseObject					= array();
		
		try
		{	
			$getSecurityQuestions		= $dbh->prepare("SELECT
	SecurityQuestions.securityQuestionID,
	SecurityQuestions.securityQuestionText
FROM
	SecurityQuestions
WHERE
	SecurityQuestions.languageCode = :languageCode");
	
			$getSecurityQuestions -> bindValue(':languageCode', $languageCode);
				
			if ($getSecurityQuestions -> execute() && $getSecurityQuestions -> rowCount() > 0)
			{
				while ($row = $getSecurityQuestions -> fetchObject())
				{
					$thisQuestion							= array();

					$securityQuestionID						= $row -> securityQuestionID;
					$securityQuestionText					= $row -> securityQuestionText;
					
					$thisQuestion['securityQuestions']		= $securityQuestionText;
					$thisQuestion['securityQuestionsID']	= $securityQuestionID;
					
					$responseObject[]						= $thisQuestion;
				}
			}

			$dbh 				= null;	
		}
	    catch (PDOException $e) 
	    {
		    errorLog($e->getMessage());
	
			die();
		}
		
		return $responseObject;
	}
	
	function unsetSecurityQuestionAnswers($accountID, $authorID, $globalCurrentDate, $sid, $dbh)
	{
		$responseObject						= array();
		
		try
		{	
			$unsetSecurityQuestionAnswer	= $dbh->prepare("DELETE FROM
	SecurityQuestionAnswers
WHERE
	FK_AccountID = :accountID");
	
			$unsetSecurityQuestionAnswer -> bindValue(':accountID', $accountID);
				
			if ($unsetSecurityQuestionAnswer -> execute())
			{
				$responseObject['unsetQuestions']	= true;
				$responseObject['resultMessage']	= "Successfully unset the security questions for $accountID";	
			}
			else
			{
				$responseObject['unsetQuestions']	= false;
				$responseObject['resultMessage']	= "Successfully unset the security questions for $accountID";	
			}

			$dbh 				= null;	
		}
	    catch (PDOException $e) 
	    {
		    $responseObject['unsetQuestions']		= false;
		    $responseObject['resultMessage']		= "Error: Could not unset the security questions for $accountID. ".$e->getMessage();
	    	
	    	errorLog($e->getMessage());
	
			die();
		}
		
		return $responseObject;
	}
	
	// END SECURITY QUESTION functions
	
	// BEGIN TFA functions
	
	function createTFAValidationRequest($userAccountID, $validationMethod, $emailAddress, $phoneNumber, $encodedSid, $globalCurrentDate, $sid, $dbh)
	{
		$responseObject													= array();
		$responseObject['previousTFACodesInvalidated']					= false;
		$responseObject['tfaVerificationMessageSent']					= false;
							
		try
		{	
			$updateValidationRequestStatus								= $dbh->prepare("UPDATE TwoFactorAuthenticationValidationHash
SET
	requestStatus = 2,
	verificationDate = :verificationDate
WHERE
	FK_UserAccountID = :accountID AND
	requestStatus = 0");
			
			$insertValidationRequest									= $dbh->prepare("INSERT INTO TwoFactorAuthenticationValidationHash
	(
		FK_UserAccountID,
		requestDate,
		requestStatus,
		sid,
		encryptedEmailAddress,
		encryptedMobileNumber,
		encryptedValidationCode,
		originalRequestDate
	)
	VALUES
	(
		:FK_UserAccountID,
		:requestDate,
		:requestStatus,
		:sid,
		AES_ENCRYPT(:emailAddress, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
		AES_ENCRYPT(returnNumericOnly(:mobilePhone), UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
		AES_ENCRYPT(:validationCode, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
		:requestDate
	)");
	
			$updateTWAMethodForUserAccount								= $dbh->prepare("UPDATE UserAccounts
SET
	tfaMethod = :tfaMethod,
	modificationDate = :modificationDate
WHERE
	userAccountID = :accountID");
		
			$updateValidationRequestStatus -> bindValue(':accountID', $userAccountID);
			$updateValidationRequestStatus -> bindValue(':verificationDate', $globalCurrentDate);
			
			if ($updateValidationRequestStatus -> execute())
			{
				$responseObject['previousTFACodesInvalidated']			= true;	
			}
		
			$validationCode												= generateRandomString(6);
						
			$insertValidationRequest -> bindValue(':FK_UserAccountID', $userAccountID);
			$insertValidationRequest -> bindValue(':validationCode', $validationCode);
			$insertValidationRequest -> bindValue(':emailAddress', $emailAddress);
			$insertValidationRequest -> bindValue(':mobilePhone', $phoneNumber);
			$insertValidationRequest -> bindValue(':requestDate', $globalCurrentDate);
			$insertValidationRequest -> bindValue(':requestStatus', 0);
			$insertValidationRequest -> bindValue(':sid', $sid);
				
			if ($insertValidationRequest -> execute())
			{
				if ($validationMethod == 1)
				{
					// send text
					
					$textBody = "Your ProfitStance verification code is $validationCode.";
							
					$returnValue										= generateTextMessage($phoneNumber, $userAccountID, $userAccountID, $textBody);
					
					$responseObject['tfaVerificationMessageSent']		= true;
						
					$responseObject['result']							= "We sent a two factor authentication code to the phone number associated with your account.  Please enter that value in the fields below.";
					
					$responseObject['tfaMethod']						= $validationMethod;
				}
				else
				{
					// send email
					$responseObject									 	= sendTwoFactorAuthenticationCodeEmail($userAccountID, $emailAddress, $validationCode, $encodedSid);		 
				}	
				
				// @task 2019-01-18 Why do we need to do this?  Why would generating a TFA request require me to update the method value?
				$updateTWAMethodForUserAccount -> bindValue(':tfaMethod', $validationMethod);
				$updateTWAMethodForUserAccount -> bindValue(':modificationDate', $globalCurrentDate);
				$updateTWAMethodForUserAccount -> bindValue(':accountID', $userAccountID);
					
				$updateTWAMethodForUserAccount -> execute();
			}
			else
			{
				$responseObject['result']								= "We were unable to generate your two factor authentication code due to a database error.";
					
				$responseObject['tfaMethod']							= $validationMethod;	
			}
			
			$dbh 														= null;	
		}
	    catch (PDOException $e) 
	    {
	    	$responseObject['responseMessage']	= "A database error has occurred.  Your account could not be created.";	
			$responseObject['accountCreated']							= false;
			errorLog($e->getMessage());
	
			die();
		}
		
		return $responseObject;
	}
	
	function disableTFAForUser($userAccountID, $globalCurrentDate, $dbh)
	{
		$responseObject										= array();

		try
		{	
			$updateAccount									= $dbh->prepare("UPDATE
	UserAccounts
SET
	modificationDate = :modificationDate,
	requireTwoFactorAuthentication = 0,
	tfaMethod = -1
WHERE
	userAccountID = :accountID");
			
			$updateAccount -> bindValue(':modificationDate', $globalCurrentDate);
			$updateAccount -> bindValue(':accountID', $userAccountID);
				
			if ($updateAccount -> execute())
			{	
				$responseObject['tfaDisabled']				= true;	
				$responseObject['result']					= "Two Factor Authentication was successfully disabled for this account.";
			}
			
			$dbh 											= null;	
		}
	    catch (PDOException $e) 
	    {
	    	$responseObject['tfaDisabled']					= false;	
			$responseObject['result']						= "A database error has occurred.  Two Factor Authentication could not be disabled for this account.";
			errorLog($e->getMessage());
	
			die();
		}
		
		return $responseObject;			
	}
	
	function resendTFACodeForUser($userName, $encodedSid, $globalCurrentDate, $dbh)
	{
		errorLog("resendTFACodeForUser($userName, $encodedSid, $globalCurrentDate");
		
		$responseObject																					= array();
		$responseObject["requireTFA"]																	= false;
		$responseObject["tfaCode"]																		= "";
		$responseObject["tfaMethod"]																	= -1;
		$responseObject["tfaCodeWasCanceled"]															= false;
		$responseObject["tfaCodeHasAlreadyBeenUsed"]													= false;
		$responseObject["resentTFACode"]																= false;
		
		$userEncryptionKey																				= "b9Gf98252c8!0aea1f(31c4c753d351";
							
		try
		{	
			$getTFAInformationForAccount																= $dbh -> prepare("SELECT
	UserAccounts.userAccountID,
	UserAccounts.requireTwoFactorAuthentication,
	UserAccounts.tfaMethod,
	TwoFactorAuthenticationValidationHash.requestDate,
	TwoFactorAuthenticationValidationHash.requestStatus,
	AES_DECRYPT(TwoFactorAuthenticationValidationHash.encryptedEmailAddress, UNHEX(SHA2(:userEncryptionKey,512))) AS decryptedEmailAddress,
	AES_DECRYPT(TwoFactorAuthenticationValidationHash.encryptedValidationCode, UNHEX(SHA2(:userEncryptionKey,512))) AS decryptedValidationCode,
	TwoFactorAuthenticationValidationHash.FK_CancelledBy,
	TwoFactorAuthenticationValidationHash.cancellationDate,
	TwoFactorAuthenticationValidationHash.FK_ResentBy,
	AES_DECRYPT(TwoFactorAuthenticationValidationHash.encryptedMobileNumber, UNHEX(SHA2(:userEncryptionKey,512))) AS decryptedMobileNumber,
	TwoFactorAuthenticationValidationHash.verificationDate
FROM
	UserAccounts
	INNER JOIN TwoFactorAuthenticationValidationHash ON UserAccounts.userAccountID = TwoFactorAuthenticationValidationHash.FK_UserAccountID
WHERE
	UserAccounts.encryptedEmailAddress = AES_ENCRYPT(:userEmailAccount, UNHEX(SHA2(:userEncryptionKey,512)))
ORDER BY
	TwoFactorAuthenticationValidationHash.requestDate DESC
LIMIT 1");

			$getTFAInformationForAccount -> bindValue(':userEmailAccount', $userName);
			$getTFAInformationForAccount -> bindValue(':userEncryptionKey', $userEncryptionKey);
			
			if ($getTFAInformationForAccount -> execute() && $getTFAInformationForAccount -> rowCount() > 0)
			{
				$row 																					= $getTFAInformationForAccount -> fetchObject();
				
				$userAccountID																			= $row -> userAccountID;
				$requireTwoFactorAuthentication															= $row -> requireTwoFactorAuthentication;
				$tfaMethod																				= $row -> tfaMethod;
				$requestDate																			= $row -> requestDate;
				$requestStatus																			= $row -> requestStatus;
				$decryptedEmailAddress																	= $row -> decryptedEmailAddress;
				$decryptedValidationCode																= $row -> decryptedValidationCode;
				$cancelledBy																			= $row -> FK_CancelledBy;
				$cancellationDate																		= $row -> cancellationDate;
				$resentBy																				= $row -> FK_ResentBy;
				$decryptedMobileNumber																	= $row -> decryptedMobileNumber;
				$verificationDate																		= $row -> verificationDate;
				
				$responseObject['userAccountID']														= $userAccountID;
				$responseObject["requireTFA"]															= $requireTwoFactorAuthentication;
				$responseObject["tfaCode"]																= $decryptedValidationCode;
				$responseObject["tfaMethod"]															= $tfaMethod;

				if ($requestStatus == 0 && $requireTwoFactorAuthentication == 1)
				{
					if ($tfaMethod == 1 && !empty($decryptedMobileNumber))
					{
						$textBody 																		= "Your ProfitStance verification code is $decryptedValidationCode.";
							
						$returnValue																	= generateTextMessage($decryptedMobileNumber, $userAccountID, $userAccountID, $textBody);
						
						if ($returnValue == 1)
						{
							$responseObject['resentTFACode']											= true;
							
							$responseObject['result']													= "We sent a two factor authentication code to the phone number associated with your account.  Please enter that value in the fields below.";	
						}	
					}
					else if ($tfaMethod == 2 && !empty($decryptedEmailAddress))
					{
						$sendEmailResult														 		= sendTwoFactorAuthenticationCodeEmail($userAccountID, $decryptedEmailAddress, $decryptedValidationCode, $encodedSid);
						
						errorLog("tfa type 2 return value: ".json_encode($sendEmailResult));
						
						$responseObject['resentTFACode']												= $sendEmailResult['tfaVerificationMessageSent'];	
						
						if (!empty($sendEmailResult['result']))
						{
							$responseObject['result']													= $sendEmailResult['result'];	
						}	
					}	
				}
				else if ($requestStatus == 1)
				{
					$responseObject["tfaCodeHasAlreadyBeenUsed"]										= true;	
				}
				else if ($requestStatus == 2)
				{
					$responseObject["tfaCodeWasCanceled"]												= true;
				}
			}
			
			$dbh 																						= null;	
		}
	    catch (PDOException $e) 
	    {
	    	errorLog($e->getMessage());
	
			die();
		}
		
		return $responseObject;	
	}
	
	function sendTwoFactorAuthenticationCodeEmail($userAccount, $emailAddress, $twoFactorAuthenticationCode, $encodedSid)
	{
		$responseObject										= array();
		
		$emailBody											= "<!DOCTYPE html>
<html lang=\"en\">
<head>
      <meta http-equiv=\"Content-Type\" content=\"text/html;charset=UTF-8\"/>
      <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
      <meta http-equiv=\"X-UA-Compatible\" content=\"ie=edge\">
      <title>Two Factor Authentication</title>

      <style>
            /* Gmail Fix */
            li a[href] {
                  text-decoration:none !important;
                  color: #001;
            }
      </style>



      <link rel=\"stylesheet\" href=\"https://fonts.googleapis.com/css?family=Lato:100,300,400,700,900\" type=\"text/css\">
</head>
<body style=\"margin:0; padding:0; background:#efefef; width: 100%;\">

      <table align=\"center\" style=\"background: #ffffff; max-width:600px; width: 100%; margin:0 auto; border-collapse:collapse; text-align:center; border-spacing: 0; border:none; box-shadow: 0px 0px 4px -2px black;\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\">
            <tr> 
                  <td width=\"90\">
                        <table style=\"max-width:580px; width: 100%; margin: 0 auto;\">
                              <tr style=\"border-spacing:0; padding:0; margin:0; border:0;\">
                                    <td style=\"border:0; margin:0; vertical-align: middle; padding-top: 50px; padding-bottom: 50px; \">

                                          <span style=\"border:0; margin:0; vertical-align: middle; color: #4c5a61;padding:0; border:none; text-align:center; font-size:30px; line-height:42px; font-weight:lighter; font-family: 'Lato', sans-serif;\">PROFIT</span>

                                          <span style=\"border:0; margin:0; vertical-align: middle; color: #4c5a61; padding:0; border:none; text-align:center; font-size:30px; line-height:42px; font-weight:bold; font-family: 'Lato', sans-serif;\"><b>STANCE</b></span>
                                    </td>
                              </tr>
                              <tr>
                                    <td style=\"border:0; margin:0; vertical-align: middle; padding:0; border:none; text-align:center; font-weight:400; font-family: 'Lato', sans-serif; font-size: 35px;\">
                                          Enable Two Factor Authentication
                                    </td>
                              </tr>
                              <tr><td height=\"60\"> &nbsp; </td></tr>

                              <tr>
                                    <td style=\"border:0; margin:0; vertical-align: middle; padding:0; border:none; text-align:center; font-weight: 300; line-height: 25px; font-family: 'Lato', sans-serif;\">
                                          <table>
                                                <tr>
                                                      <td width=\"40\"></td>
                                                      <td>In order to enable two factor authentication for your ProfitStance account, you must enter the authentication code provided below.  Once two factor authentication is enabled, you will be required to provide a two factor authentication code each time you log into your account.  This will protect your ProfitStance account.</td>
                                                      <td width=\"40\"></td>
                                                </tr>
                                          </table>
                                          
                                    </td>
                              </tr>
                              <tr><td height=\"60\"> &nbsp; </td></tr>
                              <tr>
                                    <td style=\"border:0; margin:0; vertical-align: middle; padding:0; border:none; text-align:center; font-weight:500; font-family: 'Lato', sans-serif;\">
                                          <a href=\"\" style=\"background: #4c5a61; color: #ffffff; padding: 15px; border-radius: 4px; text-decoration: none;\">
                                                Your two factor authentication code is $twoFactorAuthenticationCode.
                                          </a>
                                    </td>
                              </tr>
                              <tr><td height=\"25\"> &nbsp; </td></tr>
                              <tr><td> <span style=\"max-width: 200px;  height: 2px;  background: #4b5960; display: block; margin: 0 auto;\"></span> </td></tr>
                              <tr><td height=\"25\"> &nbsp; </td></tr>
                              <tr><td height=\"60\"></td></tr>
                        </table>
                  </td>
            </tr>
      </table>
      
</body>
</html>";


		$emailFactory										= new EmailFactory($emailAddress, $emailBody, "Enable ProfitStance Two Factor Authentication", "admin@profitstance.com");
		
		$result												= $emailFactory -> sendEmail();
		
		if ($result == 1)
		{
			$responseObject['tfaVerificationMessageSent']	= true;	
			$responseObject['result']						= "An email message has been sent to the email address associated with your account.  This email address contains the code you must enter to enable two factor authentication for your account.  The code is case sensitive.";
		}
		else
		{
			$responseObject['tfaVerificationMessageSent']	= false;
			$responseObject['result']						= "We were unable to send an email to the email address you provided.  Please verify your email account.";
		}
		
		$responseObject['tfaMethod']						= 2;
		
		return $responseObject;		
	}
	
	function verifyTFAValidationRequest($userAccountID, $validationCode, $globalCurrentDate, $dbh)
	{
		$responseObject											= array();
		$responseObject['tfaValueVerified']						= false;
		
		$validationCode											= strtoupper($validationCode);
		
		try
		{	
			$getValidationRequestData							= $dbh->prepare("SELECT
	requestDate,
	requestStatus,
	sid,
	originalRequestDate
FROM			
	TwoFactorAuthenticationValidationHash
WHERE
	encryptedValidationCode = AES_ENCRYPT(:encryptedValidationCode, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))) AND
	FK_UserAccountID = :accountID");
	
			$updateValidationRequestStatus						= $dbh->prepare("UPDATE TwoFactorAuthenticationValidationHash
SET
	requestStatus = 1,
	verificationDate = :verificationDate
WHERE
	encryptedValidationCode = AES_ENCRYPT(:encryptedValidationCode, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))) AND
	FK_UserAccountID = :accountID");
	
			$updateRequireTWAValueForUserAccount				= $dbh->prepare("UPDATE UserAccounts
SET
	requireTwoFactorAuthentication = 1,
	modificationDate = :modificationDate
WHERE
	userAccountID = :accountID");
	
			$getValidationRequestData -> bindValue(':encryptedValidationCode', $validationCode);
			$getValidationRequestData -> bindValue(':accountID', $userAccountID);
	
			if ($getValidationRequestData -> execute() && $getValidationRequestData -> rowCount() > 0)
			{
				$row 											= $getValidationRequestData -> fetchObject();
				
				$requestDate										= $row -> requestDate;
				$requestStatus									= $row -> requestStatus;
				$sid												= $row -> sid;
				$originalRequestDate								= $row -> originalRequestDate;
				
				if ($requestStatus == 0)
				{
					$updateRequireTWAValueForUserAccount -> bindValue(':accountID', $userAccountID);
					$updateRequireTWAValueForUserAccount -> bindValue(':modificationDate', $globalCurrentDate);
					
					if ($updateRequireTWAValueForUserAccount -> execute())
					{
						$updateValidationRequestStatus -> bindValue(':encryptedValidationCode', $validationCode);
						$updateValidationRequestStatus -> bindValue(':accountID', $userAccountID);
						$updateValidationRequestStatus -> bindValue(':verificationDate', $globalCurrentDate);
						
						if ($updateValidationRequestStatus -> execute())
						{
							$responseObject['tfaValueVerified']	= true;
							$responseObject['result']			= "Thank you.  Your two factor authentication code has been verified.";
							$_SESSION['tfaAuthenticated']		= 1;	
						}
						else
						{
							$responseObject['tfaValueVerified']	= false;
							$responseObject['result']			= "We were unable to verify your two factor authentication code due to a database error.";		
						}
					}
					else
					{
						$responseObject['tfaValueVerified']		= false;
						$responseObject['result']				= "We were unable to update your require two factor authentication status due to a database error.";		
					}
				}
				else if ($requestStatus == 2)
				{
					$responseObject['tfaValueVerified']			= false;
					$responseObject['result']					= "The two factor authentication code you entered has expired.";
				}
				else if ($requestStatus == 1)
				{
					$responseObject['tfaValueVerified']			= false;
					$responseObject['result']					= "The two factor authentication code you entered has already been verified.";
				}
			}
			
			$dbh 												= null;	
		}
	    catch (PDOException $e) 
	    {
	    		$responseObject['responseMessage']					= "A database error has occurred.  Your account could not be created.";	
			$responseObject['accountCreated']					= false;
			errorLog($e->getMessage());
	
			die();
		}
		
		return $responseObject;	
	}
	
	// END TFA functions
	
	// BEGIN MailChimp functions
	
	function writeMailChimpSubscriberInformation($httpResponse, $listID, $emailAddress, $firstName, $lastName, $memberID, $globalCurrentDate, $sid, $dbh) 
	{
		$responseObject										= array();
		$responseObject['writeSubscriptionInfo']			= false;
		
		$responseCode										= getEnumValueMailChimpSubscriptionResultID($httpResponse, $dbh);
		
		try
		{		
			$insertMailChimpSubscriptionInfo				= $dbh -> prepare("INSERT INTO MailChimpSubscriberEvents
(
	encryptedEmailAddress,
	encryptedFirstName,
	encryptedLastName,
	subscriptionDate,
	encryptedSid,
	FK_MailChimpSubscriptionEventResultID,
	FK_MailChimpListID,
	encryptedSubscriberID
)
VALUES
(
	AES_ENCRYPT(:emailAddress, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	AES_ENCRYPT(:firstName, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	AES_ENCRYPT(:lastName, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	:subscriptionDate,
	AES_ENCRYPT(:sid, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512))),
	:FK_MailChimpSubscriptionEventResultID,
	:FK_MailChimpListID,
	AES_ENCRYPT(:subscriberID, UNHEX(SHA2('b9Gf98252c8!0aea1f(31c4c753d351',512)))
)");

			$insertMailChimpSubscriptionInfo -> bindValue(':emailAddress', $emailAddress);
			$insertMailChimpSubscriptionInfo -> bindValue(':firstName', $firstName);
			$insertMailChimpSubscriptionInfo -> bindValue(':lastName', $lastName);
			$insertMailChimpSubscriptionInfo -> bindValue(':subscriptionDate', $globalCurrentDate);
			$insertMailChimpSubscriptionInfo -> bindValue(':sid', $sid);
			$insertMailChimpSubscriptionInfo -> bindValue(':FK_MailChimpSubscriptionEventResultID', $responseCode['statusID']);
			$insertMailChimpSubscriptionInfo -> bindValue(':FK_MailChimpListID', $listID);
			$insertMailChimpSubscriptionInfo -> bindValue(':subscriberID', $memberID);
				
			if ($insertMailChimpSubscriptionInfo -> execute())
			{
				$responseObject['writeSubscriptionInfo']	= true;		
			}
		}
	    catch (PDOException $e) 
	    {
	    	$returnValue 									= -1;	
			
			errorLog($e -> getMessage());
	
			die();
		}
		
		$responseObject['statusID']							= $responseCode['statusID'];
		$responseObject['resultMessage']					= $responseCode['statusName'];
		
		return $responseObject;

	}
	
	// END MailChimp functions
	
	// BEGIN CoinTracking functions
	
	function getCoinTrackingBuysForUser($accountID, $dataImportEventRecordID, $cryptoCurrencyTypesImported, $userEncryptionKey, $globalCurrentDate, $sid, $dbh)
	{
		$responseObject 													= array();
		
		$transactionTypeID													= 1; // These are all buy records for now
		$transactionTypeLabel												= "Buy";
		$displayTransactionTypeLabel										= "Bought";
		$transactionSourceID												= 20;
		$transactionSourceLabel												= "CoinTracking";
		$importTypeID														= 16;
		$authorID															= 2;
		$isDebit															= 0;
		$isDisabled															= 0;
		
		$feeAmountInUSD														= 0;
		$feeAmountInBaseCurrency											= 0;
		
		$realizedReturnInUSD												= 0;
		$sentCostBasisInUSD													= 0;
		$receivedCostBasisInUSD												= 0;
		
		$transactionStatusID												= 1;
		$transactionStatusLabel												= "Completed";
		
		$providerNotes														= "";
		
		$creationDate													 	= $globalCurrentDate;
		
		$quoteCurrencyID													= 2;
		$quoteCurrency														= "USD";
		
		try
		{		
			$getCoinTrackingRecords											= $dbh -> prepare("SELECT
	transactions_FIFO_Universal.transactionRecordID,
	transactions_FIFO_Universal.FK_ProviderAccountWalletID,
	transactions_FIFO_Universal.transactionDate AS transactionTime,
	UNIX_TIMESTAMP(transactions_FIFO_Universal.transactionDate) AS transactionTimestamp,
	transactions_FIFO_Universal.FK_LedgerEntryTypeID,
	transactions_FIFO_Universal.FK_TransactionTypeID AS FK_EffectiveTypeID,
	transactions_FIFO_Universal.FK_TransactionTypeID,
	transactions_FIFO_Universal.FK_ReceivedCurrencyID AS FK_AssetTypeID,
	transactions_FIFO_Universal.sentQuantity AS amount,
	transactions_FIFO_Universal.isDebit,
	(transactions_FIFO_Universal.sentQuantity / transactions_FIFO_Universal.receivedQuantity) AS baseToQuoteCurrencySpotPrice,
	(transactions_FIFO_Universal.sentQuantity / transactions_FIFO_Universal.receivedQuantity) AS baseToUSDCurrencySpotPrice,
	transactions_FIFO_Universal.FK_ReceivedWalletID AS FK_BaseCurrencyWalletID,
	transactions_FIFO_Universal.FK_SentWalletID AS FK_QuoteCurrencyWalletID,
	transactions_FIFO_Universal.receivedQuantity,
	transactions_FIFO_Universal.sentQuantity,
	transactions_FIFO_Universal.isDisabled,
	transactions_FIFO_Universal.FK_TransactionSourceID,
	20 AS FK_ExchangeID,
	transactions_FIFO_Universal.FK_ReceivedCurrencyID,
	transactions_FIFO_Universal.receivedCurrency,
	transactions_FIFO_Universal.FK_SentCurrencyID,
	transactions_FIFO_Universal.sentCurrency,
	transactions_FIFO_Universal.FK_ReceivedWalletID,
	transactions_FIFO_Universal.FK_SentWalletID,
	transactions_FIFO_Universal.realizedReturnInUSD,
	transactions_FIFO_Universal.sentCostBasisInUSD,
	transactions_FIFO_Universal.receivedCostBasisInUSD,
	transactions_FIFO_Universal.receivedWalletType,
	transactions_FIFO_Universal.receivedWallet,
	transactions_FIFO_Universal.sentWalletType,
	transactions_FIFO_Universal.sentWallet,
	transactions_FIFO_Universal.FK_ReceivedTransactionSourceID,	
	transactions_FIFO_Universal.FK_SentTransactionSourceID
FROM
	transactions_FIFO_Universal
WHERE
	transactions_FIFO_Universal.FK_TransactionTypeID = 1
ORDER BY
	UNIX_TIMESTAMP(transactions_FIFO_Universal.transactionDate)");
	
			$insertCoinTrackingRecords										= $dbh -> prepare("INSERT INTO CoinTrackingLedgerTransaction
(
	transactionRecordID,
	FK_GlobalTransactionRecordID,
	FK_AccountID,
	FK_ProviderAccountWalletID,
	transactionTime,
	transactionTimestamp,
	FK_EffectiveTypeID,
	FK_TransactionTypeID,
	FK_AssetTypeID,
	amount,
	isDebit,
	baseToQuoteCurrencySpotPrice,
	baseToUSDCurrencySpotPrice,
	btcSpotPriceAtTimeOfTransaction,
	FK_BaseCurrencyWalletID,
	FK_QuoteCurrencyWalletID,
	receivedQuantity,
	sentQuantity,
	FK_TransactionSourceID,
	FK_ExchangeID,
	FK_ReceivedCurrencyID,
	receivedCurrencyAbbreviation,
	FK_SentCurrencyID,
	sentCurrencyAbbreviation,
	FK_ReceivedWalletID,
	FK_SentWalletID,
	receivedWalletType,
	receivedWallet,
	sentWalletType,
	sentWallet
)
VALUES
(
	:transactionRecordID,
	:FK_GlobalTransactionRecordID,
	:FK_AccountID,
	:FK_ProviderAccountWalletID,
	:transactionTime,
	:transactionTimestamp,
	:FK_EffectiveTypeID,
	:FK_TransactionTypeID,
	:FK_AssetTypeID,
	:amount,
	:isDebit,
	:baseToQuoteCurrencySpotPrice,
	:baseToUSDCurrencySpotPrice,
	:btcSpotPriceAtTimeOfTransaction,
	:FK_BaseCurrencyWalletID,
	:FK_QuoteCurrencyWalletID,
	:receivedQuantity,
	:sentQuantity,
	:FK_TransactionSourceID,
	:FK_ExchangeID,
	:FK_ReceivedCurrencyID,
	:receivedCurrencyAbbreviation,
	:FK_SentCurrencyID,
	:sentCurrencyAbbreviation,
	:FK_ReceivedWalletID,
	:FK_SentWalletID,
	:receivedWalletType,
	:receivedWallet,
	:sentWalletType,
	:sentWallet
)");
	
			if ($getCoinTrackingRecords -> execute() && $getCoinTrackingRecords -> rowCount() > 0)
			{
				errorLog("began get cointracking buy transaction records ".$getCoinTrackingRecords -> rowCount() > 0);
				
				while ($row = $getCoinTrackingRecords -> fetchObject())
				{
					$transactionRecordID									= $row -> transactionRecordID;
					$FK_ProviderAccountWalletID								= $row -> FK_ProviderAccountWalletID;
					$transactionTime										= $row -> transactionTime;
					$transactionTimestamp									= $row -> transactionTimestamp;
					$FK_LedgerEntryTypeID									= $row -> FK_LedgerEntryTypeID;
					$FK_EffectiveTypeID										= $row -> FK_EffectiveTypeID;
					$FK_TransactionTypeID									= $row -> FK_TransactionTypeID;
					$FK_AssetTypeID											= $row -> FK_AssetTypeID;
					$amount													= $row -> amount;
					$baseToQuoteCurrencySpotPrice							= $row -> baseToQuoteCurrencySpotPrice;							
					$baseToUSDCurrencySpotPrice								= $row -> baseToUSDCurrencySpotPrice;
					
					$FK_BaseCurrencyWalletID								= $row -> FK_BaseCurrencyWalletID;
					$FK_QuoteCurrencyWalletID								= $row -> FK_QuoteCurrencyWalletID;
					$receivedQuantity										= $row -> receivedQuantity;
					$sentQuantity											= $row -> sentQuantity;
					$FK_TransactionSourceID									= $row -> FK_TransactionSourceID;
					$FK_ExchangeID											= $row -> FK_ExchangeID;
					$FK_ReceivedCurrencyID									= $row -> FK_ReceivedCurrencyID;
					$receivedCurrency										= $row -> receivedCurrency;
					$FK_SentCurrencyID										= $row -> FK_SentCurrencyID;
					$sentCurrency											= $row -> sentCurrency;
					$FK_ReceivedWalletID									= $row -> FK_ReceivedWalletID;
					$FK_SentWalletID										= $row -> FK_SentWalletID;
					$receivedWalletType										= $row -> receivedWalletType;
					$receivedWallet											= $row -> receivedWallet;
					$sentWalletType											= $row -> sentWalletType;
					$sentWallet 											= $row -> sentWallet;
					$FK_ReceivedTransactionSourceID							= $row -> FK_ReceivedTransactionSourceID;
					$FK_SentTransactionSourceID								= $row -> FK_SentTransactionSourceID;

					if (isset($cryptoCurrencyTypesImported[$FK_AssetTypeID][$quoteCurrencyID]))
					{
						$currentCount										= $cryptoCurrencyTypesImported[$FK_AssetTypeID][$quoteCurrencyID];
						$currentCount++;
						
						$cryptoCurrencyTypesImported[$FK_AssetTypeID][$quoteCurrencyID]	= $currentCount;
					}
					else
					{
						$cryptoCurrencyTypesImported[$FK_AssetTypeID][$quoteCurrencyID]	= 1;	
					}
					
					// create asset type status record for this data import event record ID
					createDataImportAssetStatusRecord($accountID, $userEncryptionKey, $dataImportEventRecordID, $FK_AssetTypeID, $quoteCurrencyID, $globalCurrentDate, $sid, $dbh);

					$transactionAmountInUSD									= $amount;
					$transactionAmountMinusFeeInUSD							= $amount;

					$globalTransactionIdentificationRecordID				= 0;

					$nativeTransactionIDValue								= md5("$transactionSourceID $transactionTimestamp $FK_TransactionTypeID $FK_ReceivedCurrencyID $FK_SentCurrencyID $receivedWalletType $sentWalletType $amount $sentQuantity $receivedCurrency".md5("$sentQuantity.$transactionTime.$accountID"));

					$profitStanceTransactionIDValue							= createProfitStanceTransactionIDValue($accountID, $FK_AssetTypeID, $transactionSourceID, $nativeTransactionIDValue, $globalCurrentDate, $sid);

					$globalTransactionCreationResults						= createGlobalTransactionIdentificationRecordWithProfitStanceTransactionIDValue($accountID, $FK_AssetTypeID, $dataImportEventRecordID, $nativeTransactionIDValue, $profitStanceTransactionIDValue, $transactionSourceID, $globalCurrentDate, $sid, $dbh);
					
					if ($globalTransactionCreationResults['createdGlobalTransactionIdentificationRecord'] == true)
					{
						$globalTransactionIdentificationRecordID			= $globalTransactionCreationResults['globalTransactionIdentificationRecordID'];
					}	
					
					$btcSpotPriceAtTimeOfTransaction						= 0;
					
					if ($FK_ReceivedCurrencyID == 1 && $FK_SentCurrencyID == 2)
					{
						$btcSpotPriceAtTimeOfTransaction					= $baseToUSDCurrencySpotPrice;
					}
					else
					{
						$cascadeRetrieveSpotPriceResponseObject				= getSpotPriceForAssetPairUsingSourceCascade(1, 2, $transactionTime, 14, "CoinGecko price by date", $dbh);
						
						if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
						{
							$btcSpotPriceAtTimeOfTransaction				= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
						}	
					}
					
					$unspentTransactionTotal								= 0;
					$unfundedSpendTotal										= 0;
					
					if ($isDebit == 0)
					{
						$unspentTransactionTotal  							= $amount;
					}
					else if ($isDebit == 1)
					{
						$unfundedSpendTotal									= $amount;	
					}
					
					$sourceWallet											= new CompleteCryptoWallet();
					$destinationWallet										= new CompleteCryptoWallet();
					
					$sourceWalletResponseObject								= $sourceWallet -> instantiateWalletUsingCryptoWalletRecordID($accountID, $FK_SentWalletID, $userEncryptionKey, $dbh);
			
					if ($sourceWalletResponseObject['instantiatedRecord'] == false)
					{
						errorLog("could not instantiate crypto wallet $accountID, $FK_SentWalletID, $userEncryptionKey");
						exit();
					}
					
					$destinationWalletResponseObject						= $destinationWallet -> instantiateWalletUsingCryptoWalletRecordID($accountID, $FK_ReceivedWalletID, $userEncryptionKey, $dbh);
			
					if ($destinationWalletResponseObject['instantiatedRecord'] == false)
					{
						errorLog("could not instantiate crypto wallet $accountID, $FK_ReceivedWalletID, $userEncryptionKey");
						exit();
					}
					
					errorLog("INSERT INTO CoinTrackingLedgerTransaction
(
	transactionRecordID,
	FK_GlobalTransactionRecordID,
	FK_AccountID,
	FK_ProviderAccountWalletID,
	transactionTime,
	transactionTimestamp,
	FK_EffectiveTypeID,
	FK_TransactionTypeID,
	FK_AssetTypeID,
	amount,
	isDebit,
	baseToQuoteCurrencySpotPrice,
	baseToUSDCurrencySpotPrice,
	btcSpotPriceAtTimeOfTransaction,
	FK_BaseCurrencyWalletID,
	FK_QuoteCurrencyWalletID,
	receivedQuantity,
	sentQuantity,
	FK_TransactionSourceID,
	FK_ExchangeID,
	FK_ReceivedCurrencyID,
	receivedCurrencyAbbreviation,
	FK_SentCurrencyID,
	sentCurrencyAbbreviation,
	FK_ReceivedWalletID,
	FK_SentWalletID,
	receivedWalletType,
	receivedWallet,
	sentWalletType,
	sentWallet
)
VALUES
(
	$transactionRecordID,
	$globalTransactionIdentificationRecordID,
	$accountID,
	$FK_ProviderAccountWalletID,
	'$transactionTime',
	$transactionTimestamp,
	$FK_EffectiveTypeID,
	$FK_TransactionTypeID,
	$FK_AssetTypeID,
	$amount,
	$isDebit,
	$baseToQuoteCurrencySpotPrice,
	$baseToUSDCurrencySpotPrice,
	$btcSpotPriceAtTimeOfTransaction,
	$FK_BaseCurrencyWalletID,
	$FK_QuoteCurrencyWalletID,
	$receivedQuantity,
	$sentQuantity,
	$transactionSourceID,
	$FK_ExchangeID,
	$FK_ReceivedCurrencyID,
	'$receivedCurrency',
	$FK_SentCurrencyID,
	'$sentCurrency',
	$FK_ReceivedWalletID,
	$FK_SentWalletID,
	'$receivedWalletType',
	'$receivedWallet',
	'$sentWalletType',
	'$sentWallet'
)");
					
					$insertCoinTrackingRecords -> bindValue(':transactionRecordID', $transactionRecordID);
					$insertCoinTrackingRecords -> bindValue(':FK_GlobalTransactionRecordID', $globalTransactionIdentificationRecordID);
					$insertCoinTrackingRecords -> bindValue(':FK_AccountID', $accountID);
					$insertCoinTrackingRecords -> bindValue(':FK_ProviderAccountWalletID', $FK_ProviderAccountWalletID);
					$insertCoinTrackingRecords -> bindValue(':transactionTime', $transactionTime);
					$insertCoinTrackingRecords -> bindValue(':transactionTimestamp', $transactionTimestamp);
					$insertCoinTrackingRecords -> bindValue(':FK_EffectiveTypeID', $FK_EffectiveTypeID);
					$insertCoinTrackingRecords -> bindValue(':FK_TransactionTypeID', $FK_TransactionTypeID);
					$insertCoinTrackingRecords -> bindValue(':FK_AssetTypeID', $FK_AssetTypeID);
					$insertCoinTrackingRecords -> bindValue(':amount', $amount);
					$insertCoinTrackingRecords -> bindValue(':isDebit', $isDebit);
					$insertCoinTrackingRecords -> bindValue(':baseToQuoteCurrencySpotPrice', $baseToQuoteCurrencySpotPrice);
					$insertCoinTrackingRecords -> bindValue(':baseToUSDCurrencySpotPrice', $baseToUSDCurrencySpotPrice);
					$insertCoinTrackingRecords -> bindValue(':btcSpotPriceAtTimeOfTransaction', $btcSpotPriceAtTimeOfTransaction);
					$insertCoinTrackingRecords -> bindValue(':FK_BaseCurrencyWalletID', $FK_BaseCurrencyWalletID);
					$insertCoinTrackingRecords -> bindValue(':FK_QuoteCurrencyWalletID', $FK_QuoteCurrencyWalletID);
					$insertCoinTrackingRecords -> bindValue(':receivedQuantity', $receivedQuantity);
					$insertCoinTrackingRecords -> bindValue(':sentQuantity', $sentQuantity);
					$insertCoinTrackingRecords -> bindValue(':FK_TransactionSourceID', $transactionSourceID);
					$insertCoinTrackingRecords -> bindValue(':FK_ExchangeID', $FK_ExchangeID);
					$insertCoinTrackingRecords -> bindValue(':FK_ReceivedCurrencyID', $FK_ReceivedCurrencyID);
					$insertCoinTrackingRecords -> bindValue(':receivedCurrencyAbbreviation', $receivedCurrency);
					$insertCoinTrackingRecords -> bindValue(':FK_SentCurrencyID', $FK_SentCurrencyID);
					$insertCoinTrackingRecords -> bindValue(':sentCurrencyAbbreviation', $sentCurrency);
					$insertCoinTrackingRecords -> bindValue(':FK_ReceivedWalletID', $FK_ReceivedWalletID);
					$insertCoinTrackingRecords -> bindValue(':FK_SentWalletID', $FK_SentWalletID);
					$insertCoinTrackingRecords -> bindValue(':receivedWalletType', $receivedWalletType);
					$insertCoinTrackingRecords -> bindValue(':receivedWallet', $receivedWallet);
					$insertCoinTrackingRecords -> bindValue(':sentWalletType', $sentWalletType);
					$insertCoinTrackingRecords -> bindValue(':sentWallet', $sentWallet);

					

					$nativeRecordID											= 0;

					if ($insertCoinTrackingRecords -> execute())
					{
						errorLog("insert coin tracking record worked", $GLOBALS['debugCoreFunctionality']);
						
						$nativeRecordID 									= $dbh -> lastInsertId();
					}
					
					$cryptoTransaction										= new CryptoTransaction();
					
					$cryptoTransaction -> setData(0, $accountID, $authorID, $globalTransactionIdentificationRecordID, $FK_TransactionTypeID, $transactionTypeLabel, $transactionStatusID, $transactionStatusLabel, $transactionSourceID, $transactionSourceLabel, $FK_AssetTypeID, $receivedCurrency, $quoteCurrencyID, $quoteCurrency, $FK_SentWalletID, $FK_ReceivedWalletID, $transactionTime, $transactionTime, $transactionTimestamp, $nativeRecordID, $nativeRecordID, $receivedQuantity, $amount, $baseToQuoteCurrencySpotPrice, $btcSpotPriceAtTimeOfTransaction, $transactionAmountInUSD, $feeAmountInBaseCurrency, $feeAmountInUSD, $unspentTransactionTotal, $providerNotes, $isDebit, $sid);
					
					$writeToDatabaseResponse								= $cryptoTransaction -> writeToDatabase($userEncryptionKey, $dbh);
						
					if ($writeToDatabaseResponse['wroteToDatabase'] == true)
					{
						$transactionID										= $cryptoTransaction -> getTransactionID();
					
						errorLog("transaction $transactionID created");
						
						
						$profitStanceLedgerEntry							= new ProfitStanceLedgerEntry();
						
						$profitStanceLedgerEntry -> setData($accountID, $FK_AssetTypeID, $receivedCurrency, $transactionSourceID, $transactionSourceLabel, $exchangeTileID, $globalTransactionIdentificationRecordID, $transactionTime, $receivedQuantity, $dbh);
														
						$writeProfitStanceLedgerEntryRecordResponseObject	= $profitStanceLedgerEntry -> writeToDatabase($dbh);
														
						if ($writeProfitStanceLedgerEntryRecordResponseObject['wroteToDatabase'] == true)
						{
							errorLog("wrote profitStance ledger entry $accountID, $FK_AssetTypeID, $receivedCurrency, $transactionSourceID, $transactionSourceLabel, $globalTransactionIdentificationRecordID, $receivedQuantity to the database.", $GLOBALS['debugCoreFunctionality']);
						}
						else
						{
							errorLog("could not write profitStance ledger entry $accountID, $FK_AssetTypeID, $receivedCurrency, $transactionSourceID, $transactionSourceLabel, $globalTransactionIdentificationRecordID, $receivedQuantity to the database.", $GLOBALS['criticalErrors']);	
						}
					}
				}
			}
			
			$responseObject['importedTransactions']							= true;
		}
		catch (PDOException $e) 
		{
			$cryptoTransaction 												= null;	
			$responseObject['importedTransactions']							= false;
			
			errorLog($e -> getMessage());
		
			die();
		}

        return $cryptoCurrencyTypesImported;
    }
	
	function getCoinTrackingReceivedForUser($accountID, $dataImportEventRecordID, $cryptoCurrencyTypesImported, $userEncryptionKey, $globalCurrentDate, $sid, $dbh)
	{
		$responseObject 													= array();
		
		$transactionTypeID													= 1; // These are all buy records for now
		$transactionTypeLabel												= "Received";
		$displayTransactionTypeLabel										= "Received";
		$transactionSourceID												= 20;
		$transactionSourceLabel												= "CoinTracking";
		$importTypeID														= 16;
		$authorID															= 2;
		$isDebit															= 0;
		$isDisabled															= 0;
		
		$feeAmountInUSD														= 0;
		$feeAmountInBaseCurrency											= 0;
		
		$realizedReturnInUSD												= 0;
		$sentCostBasisInUSD													= 0;
		$receivedCostBasisInUSD												= 0;
		
		$transactionStatusID												= 1;
		$transactionStatusLabel												= "Completed";
		
		$providerNotes														= "";
		
		$creationDate													 	= $globalCurrentDate;
		
		$quoteCurrencyID													= 2;
		$quoteCurrency														= "USD";
		
		try
		{		
			$getCoinTrackingRecords											= $dbh -> prepare("SELECT
	transactions_FIFO_Universal.transactionRecordID,
	transactions_FIFO_Universal.FK_ProviderAccountWalletID,
	transactions_FIFO_Universal.transactionDate AS transactionTime,
	UNIX_TIMESTAMP(transactions_FIFO_Universal.transactionDate) AS transactionTimestamp,
	transactions_FIFO_Universal.FK_LedgerEntryTypeID,
	transactions_FIFO_Universal.FK_TransactionTypeID AS FK_EffectiveTypeID,
	transactions_FIFO_Universal.FK_TransactionTypeID,
	transactions_FIFO_Universal.FK_ReceivedCurrencyID AS FK_AssetTypeID,
	transactions_FIFO_Universal.sentQuantity AS amount,
	transactions_FIFO_Universal.isDebit,
	transactions_FIFO_Universal.FK_ReceivedWalletID AS FK_BaseCurrencyWalletID,
	transactions_FIFO_Universal.FK_SentWalletID AS FK_QuoteCurrencyWalletID,
	transactions_FIFO_Universal.receivedQuantity,
	transactions_FIFO_Universal.sentQuantity,
	transactions_FIFO_Universal.isDisabled,
	transactions_FIFO_Universal.FK_TransactionSourceID,
	20 AS FK_ExchangeID,
	transactions_FIFO_Universal.FK_ReceivedCurrencyID,
	transactions_FIFO_Universal.receivedCurrency,
	transactions_FIFO_Universal.FK_SentCurrencyID,
	transactions_FIFO_Universal.sentCurrency,
	transactions_FIFO_Universal.FK_ReceivedWalletID,
	transactions_FIFO_Universal.FK_SentWalletID,
	transactions_FIFO_Universal.realizedReturnInUSD,
	transactions_FIFO_Universal.sentCostBasisInUSD,
	transactions_FIFO_Universal.receivedCostBasisInUSD,
	transactions_FIFO_Universal.receivedWalletType,
	transactions_FIFO_Universal.receivedWallet,
	transactions_FIFO_Universal.sentWalletType,
	transactions_FIFO_Universal.sentWallet,
	transactions_FIFO_Universal.FK_ReceivedTransactionSourceID,	
	transactions_FIFO_Universal.FK_SentTransactionSourceID
FROM
	transactions_FIFO_Universal
WHERE
	transactions_FIFO_Universal.FK_TransactionTypeID = 3
ORDER BY
	UNIX_TIMESTAMP(transactions_FIFO_Universal.transactionDate)");
	
			$insertCoinTrackingRecords										= $dbh -> prepare("INSERT INTO CoinTrackingLedgerTransaction
(
	transactionRecordID,
	FK_GlobalTransactionRecordID,
	FK_AccountID,
	FK_ProviderAccountWalletID,
	transactionTime,
	transactionTimestamp,
	FK_EffectiveTypeID,
	FK_TransactionTypeID,
	FK_AssetTypeID,
	amount,
	isDebit,
	baseToQuoteCurrencySpotPrice,
	baseToUSDCurrencySpotPrice,
	btcSpotPriceAtTimeOfTransaction,
	FK_BaseCurrencyWalletID,
	FK_QuoteCurrencyWalletID,
	receivedQuantity,
	sentQuantity,
	FK_TransactionSourceID,
	FK_ExchangeID,
	FK_ReceivedCurrencyID,
	receivedCurrencyAbbreviation,
	FK_SentCurrencyID,
	sentCurrencyAbbreviation,
	FK_ReceivedWalletID,
	FK_SentWalletID,
	receivedWalletType,
	receivedWallet,
	sentWalletType,
	sentWallet
)
VALUES
(
	:transactionRecordID,
	:FK_GlobalTransactionRecordID,
	:FK_AccountID,
	:FK_ProviderAccountWalletID,
	:transactionTime,
	:transactionTimestamp,
	:FK_EffectiveTypeID,
	:FK_TransactionTypeID,
	:FK_AssetTypeID,
	:amount,
	:isDebit,
	:baseToQuoteCurrencySpotPrice,
	:baseToUSDCurrencySpotPrice,
	:btcSpotPriceAtTimeOfTransaction,
	:FK_BaseCurrencyWalletID,
	:FK_QuoteCurrencyWalletID,
	:receivedQuantity,
	:sentQuantity,
	:FK_TransactionSourceID,
	:FK_ExchangeID,
	:FK_ReceivedCurrencyID,
	:receivedCurrencyAbbreviation,
	:FK_SentCurrencyID,
	:sentCurrencyAbbreviation,
	:FK_ReceivedWalletID,
	:FK_SentWalletID,
	:receivedWalletType,
	:receivedWallet,
	:sentWalletType,
	:sentWallet
)");
	
			if ($getCoinTrackingRecords -> execute() && $getCoinTrackingRecords -> rowCount() > 0)
			{
				errorLog("began get cointracking received transaction records ".$getCoinTrackingRecords -> rowCount() > 0);
				
				while ($row = $getCoinTrackingRecords -> fetchObject())
				{
					$transactionRecordID									= $row -> transactionRecordID;
					$FK_ProviderAccountWalletID								= $row -> FK_ProviderAccountWalletID;
					$transactionTime										= $row -> transactionTime;
					$transactionTimestamp									= $row -> transactionTimestamp;
					$FK_LedgerEntryTypeID									= $row -> FK_LedgerEntryTypeID;
					$FK_EffectiveTypeID										= $row -> FK_EffectiveTypeID;
					$FK_TransactionTypeID									= $row -> FK_TransactionTypeID;
					$FK_AssetTypeID											= $row -> FK_AssetTypeID;
					$amount													= $row -> amount;
					$FK_BaseCurrencyWalletID								= $row -> FK_BaseCurrencyWalletID;
					$FK_QuoteCurrencyWalletID								= $row -> FK_QuoteCurrencyWalletID;
					$receivedQuantity										= $row -> receivedQuantity;
					$sentQuantity											= $row -> sentQuantity;
					$FK_TransactionSourceID									= $row -> FK_TransactionSourceID;
					$FK_ExchangeID											= $row -> FK_ExchangeID;
					$FK_ReceivedCurrencyID									= $row -> FK_ReceivedCurrencyID;
					$receivedCurrency										= $row -> receivedCurrency;
					$FK_SentCurrencyID										= $row -> FK_SentCurrencyID;
					$sentCurrency											= $row -> sentCurrency;
					$FK_ReceivedWalletID									= $row -> FK_ReceivedWalletID;
					$FK_SentWalletID										= $row -> FK_SentWalletID;
					$receivedWalletType										= $row -> receivedWalletType;
					$receivedWallet											= $row -> receivedWallet;
					$sentWalletType											= $row -> sentWalletType;
					$sentWallet 											= $row -> sentWallet;
					$FK_ReceivedTransactionSourceID							= $row -> FK_ReceivedTransactionSourceID;
					$FK_SentTransactionSourceID								= $row -> FK_SentTransactionSourceID;

if (isset($cryptoCurrencyTypesImported[$FK_AssetTypeID][$quoteCurrencyID]))
					{
						$currentCount										= $cryptoCurrencyTypesImported[$FK_AssetTypeID][$quoteCurrencyID];
						$currentCount++;
						
						$cryptoCurrencyTypesImported[$FK_AssetTypeID][$quoteCurrencyID]	= $currentCount;
					}
					else
					{
						$cryptoCurrencyTypesImported[$FK_AssetTypeID][$quoteCurrencyID]	= 1;	
					}
					
					// create asset type status record for this data import event record ID
					createDataImportAssetStatusRecord($accountID, $userEncryptionKey, $dataImportEventRecordID, $FK_AssetTypeID, $quoteCurrencyID, $globalCurrentDate, $sid, $dbh);

					$globalTransactionIdentificationRecordID				= 0;

					$baseToQuoteCurrencySpotPrice							= 0;							
					$baseToUSDCurrencySpotPrice								= 0;
					$btcSpotPriceAtTimeOfTransaction						= 0;
					
					$cascadeRetrieveSpotPriceResponseObject					= getSpotPriceForAssetPairUsingSourceCascade($FK_ReceivedCurrencyID, 2, $transactionTime, 14, "CoinGecko price by date", $dbh);
						
					if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
					{
						$baseToQuoteCurrencySpotPrice						= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
						$baseToUSDCurrencySpotPrice							= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
					}
					
					if ($FK_ReceivedCurrencyID == 1)
					{
						$btcSpotPriceAtTimeOfTransaction					= $baseToUSDCurrencySpotPrice;	
					}
					else
					{
						$cascadeRetrieveSpotPriceResponseObject				= getSpotPriceForAssetPairUsingSourceCascade(1, 2, $transactionTime, 14, "CoinGecko price by date", $dbh);
						
						if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
						{
							$btcSpotPriceAtTimeOfTransaction				= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
						}	
					}
					
					$transactionAmountInUSD									= $baseToUSDCurrencySpotPrice * $receivedQuantity;
					$transactionAmountMinusFeeInUSD							= $transactionAmountInUSD;
					$sentQuantity											= $transactionAmountInUSD;
					$amount													= $transactionAmountInUSD;

					$nativeTransactionIDValue								= md5("$transactionSourceID $transactionTimestamp $FK_TransactionTypeID $FK_ReceivedCurrencyID $FK_SentCurrencyID $receivedWalletType $sentWalletType $amount $sentQuantity $receivedCurrency".md5("$sentQuantity.$transactionTime.$accountID"));

					$profitStanceTransactionIDValue							= createProfitStanceTransactionIDValue($accountID, $FK_AssetTypeID, $transactionSourceID, $nativeTransactionIDValue, $globalCurrentDate, $sid);

					$globalTransactionCreationResults						= createGlobalTransactionIdentificationRecordWithProfitStanceTransactionIDValue($accountID, $FK_AssetTypeID, $dataImportEventRecordID, $nativeTransactionIDValue, $profitStanceTransactionIDValue, $transactionSourceID, $globalCurrentDate, $sid, $dbh);
					
					if ($globalTransactionCreationResults['createdGlobalTransactionIdentificationRecord'] == true)
					{
						$globalTransactionIdentificationRecordID			= $globalTransactionCreationResults['globalTransactionIdentificationRecordID'];
					}	
					
					$unspentTransactionTotal								= 0;
					$unfundedSpendTotal										= 0;
					
					if ($isDebit == 0)
					{
						$unspentTransactionTotal  							= $amount;
					}
					else if ($isDebit == 1)
					{
						$unfundedSpendTotal									= $amount;	
					}
					
					$sourceWallet											= new CompleteCryptoWallet();
					$destinationWallet										= new CompleteCryptoWallet();
					
					$sourceWalletResponseObject								= $sourceWallet -> instantiateWalletUsingCryptoWalletRecordID($accountID, $FK_SentWalletID, $userEncryptionKey, $dbh);
			
					if ($sourceWalletResponseObject['instantiatedRecord'] == false)
					{
						errorLog("could not instantiate crypto wallet $accountID, $FK_SentWalletID, $userEncryptionKey");
						exit();
					}
					
					$destinationWalletResponseObject						= $destinationWallet -> instantiateWalletUsingCryptoWalletRecordID($accountID, $FK_ReceivedWalletID, $userEncryptionKey, $dbh);
			
					if ($destinationWalletResponseObject['instantiatedRecord'] == false)
					{
						errorLog("could not instantiate crypto wallet $accountID, $FK_ReceivedWalletID, $userEncryptionKey");
						exit();
					}
					
					errorLog("INSERT INTO CoinTrackingLedgerTransaction
(
	transactionRecordID,
	FK_GlobalTransactionRecordID,
	FK_AccountID,
	FK_ProviderAccountWalletID,
	transactionTime,
	transactionTimestamp,
	FK_EffectiveTypeID,
	FK_TransactionTypeID,
	FK_AssetTypeID,
	amount,
	isDebit,
	baseToQuoteCurrencySpotPrice,
	baseToUSDCurrencySpotPrice,
	btcSpotPriceAtTimeOfTransaction,
	FK_BaseCurrencyWalletID,
	FK_QuoteCurrencyWalletID,
	receivedQuantity,
	sentQuantity,
	FK_TransactionSourceID,
	FK_ExchangeID,
	FK_ReceivedCurrencyID,
	receivedCurrencyAbbreviation,
	FK_SentCurrencyID,
	sentCurrencyAbbreviation,
	FK_ReceivedWalletID,
	FK_SentWalletID,
	receivedWalletType,
	receivedWallet,
	sentWalletType,
	sentWallet
)
VALUES
(
	$transactionRecordID,
	$globalTransactionIdentificationRecordID,
	$accountID,
	$FK_ProviderAccountWalletID,
	'$transactionTime',
	$transactionTimestamp,
	$FK_EffectiveTypeID,
	$FK_TransactionTypeID,
	$FK_AssetTypeID,
	$amount,
	$isDebit,
	$baseToQuoteCurrencySpotPrice,
	$baseToUSDCurrencySpotPrice,
	$btcSpotPriceAtTimeOfTransaction,
	$FK_BaseCurrencyWalletID,
	$FK_QuoteCurrencyWalletID,
	$receivedQuantity,
	$sentQuantity,
	$transactionSourceID,
	$FK_ExchangeID,
	$FK_ReceivedCurrencyID,
	'$receivedCurrency',
	$FK_SentCurrencyID,
	'$sentCurrency',
	$FK_ReceivedWalletID,
	$FK_SentWalletID,
	'$receivedWalletType',
	'$receivedWallet',
	'$sentWalletType',
	'$sentWallet'
)");
					
					$insertCoinTrackingRecords -> bindValue(':transactionRecordID', $transactionRecordID);
					$insertCoinTrackingRecords -> bindValue(':FK_GlobalTransactionRecordID', $globalTransactionIdentificationRecordID);
					$insertCoinTrackingRecords -> bindValue(':FK_AccountID', $accountID);
					$insertCoinTrackingRecords -> bindValue(':FK_ProviderAccountWalletID', $FK_ProviderAccountWalletID);
					$insertCoinTrackingRecords -> bindValue(':transactionTime', $transactionTime);
					$insertCoinTrackingRecords -> bindValue(':transactionTimestamp', $transactionTimestamp);
					$insertCoinTrackingRecords -> bindValue(':FK_EffectiveTypeID', $FK_EffectiveTypeID);
					$insertCoinTrackingRecords -> bindValue(':FK_TransactionTypeID', $FK_TransactionTypeID);
					$insertCoinTrackingRecords -> bindValue(':FK_AssetTypeID', $FK_AssetTypeID);
					$insertCoinTrackingRecords -> bindValue(':amount', $amount);
					$insertCoinTrackingRecords -> bindValue(':isDebit', $isDebit);
					$insertCoinTrackingRecords -> bindValue(':baseToQuoteCurrencySpotPrice', $baseToQuoteCurrencySpotPrice);
					$insertCoinTrackingRecords -> bindValue(':baseToUSDCurrencySpotPrice', $baseToUSDCurrencySpotPrice);
					$insertCoinTrackingRecords -> bindValue(':btcSpotPriceAtTimeOfTransaction', $btcSpotPriceAtTimeOfTransaction);
					$insertCoinTrackingRecords -> bindValue(':FK_BaseCurrencyWalletID', $FK_BaseCurrencyWalletID);
					$insertCoinTrackingRecords -> bindValue(':FK_QuoteCurrencyWalletID', $FK_QuoteCurrencyWalletID);
					$insertCoinTrackingRecords -> bindValue(':receivedQuantity', $receivedQuantity);
					$insertCoinTrackingRecords -> bindValue(':sentQuantity', $sentQuantity);
					$insertCoinTrackingRecords -> bindValue(':FK_TransactionSourceID', $transactionSourceID);
					$insertCoinTrackingRecords -> bindValue(':FK_ExchangeID', $FK_ExchangeID);
					$insertCoinTrackingRecords -> bindValue(':FK_ReceivedCurrencyID', $FK_ReceivedCurrencyID);
					$insertCoinTrackingRecords -> bindValue(':receivedCurrencyAbbreviation', $receivedCurrency);
					$insertCoinTrackingRecords -> bindValue(':FK_SentCurrencyID', $FK_SentCurrencyID);
					$insertCoinTrackingRecords -> bindValue(':sentCurrencyAbbreviation', $sentCurrency);
					$insertCoinTrackingRecords -> bindValue(':FK_ReceivedWalletID', $FK_ReceivedWalletID);
					$insertCoinTrackingRecords -> bindValue(':FK_SentWalletID', $FK_SentWalletID);
					$insertCoinTrackingRecords -> bindValue(':receivedWalletType', $receivedWalletType);
					$insertCoinTrackingRecords -> bindValue(':receivedWallet', $receivedWallet);
					$insertCoinTrackingRecords -> bindValue(':sentWalletType', $sentWalletType);
					$insertCoinTrackingRecords -> bindValue(':sentWallet', $sentWallet);

					

					$nativeRecordID											= 0;

					if ($insertCoinTrackingRecords -> execute())
					{
						errorLog("insert coin tracking record worked");
						
						$nativeRecordID 									= $dbh -> lastInsertId();
					}
					
					$cryptoTransaction										= new CryptoTransaction();
					
					$cryptoTransaction -> setData(0, $accountID, $authorID, $globalTransactionIdentificationRecordID, $FK_TransactionTypeID, $transactionTypeLabel, $transactionStatusID, $transactionStatusLabel, $transactionSourceID, $transactionSourceLabel, $FK_AssetTypeID, $receivedCurrency, $quoteCurrencyID, $quoteCurrency, $FK_SentWalletID, $FK_ReceivedWalletID, $transactionTime, $transactionTime, $transactionTimestamp, $nativeRecordID, $nativeRecordID, $receivedQuantity, $sentQuantity, $baseToQuoteCurrencySpotPrice, $btcSpotPriceAtTimeOfTransaction, $transactionAmountInUSD, $feeAmountInBaseCurrency, $feeAmountInUSD, $unspentTransactionTotal, $providerNotes, $isDebit, $sid);
					
					$writeToDatabaseResponse								= $cryptoTransaction -> writeToDatabase($userEncryptionKey, $dbh);
						
					if ($writeToDatabaseResponse['wroteToDatabase'] == true)
					{
						$transactionID										= $cryptoTransaction -> getTransactionID();
					
						errorLog("transaction $transactionID created");
						
						$profitStanceLedgerEntry							= new ProfitStanceLedgerEntry();
												
						$profitStanceLedgerEntry -> setData($accountID, $FK_AssetTypeID, $receivedCurrency, $transactionSourceID, $transactionSourceLabel, $exchangeTileID, $globalTransactionIdentificationRecordID, $transactionTime, $receivedQuantity, $dbh);
														
						$writeProfitStanceLedgerEntryRecordResponseObject	= $profitStanceLedgerEntry -> writeToDatabase($dbh);
														
						if ($writeProfitStanceLedgerEntryRecordResponseObject['wroteToDatabase'] == true)
						{
							errorLog("wrote profitStance ledger entry $accountID, $FK_AssetTypeID, $receivedCurrency, $transactionSourceID, $transactionSourceLabel, $globalTransactionIdentificationRecordID, $receivedQuantity to the database.", $GLOBALS['debugCoreFunctionality']);
						}
						else
						{
							errorLog("could not write profitStance ledger entry $accountID, $FK_AssetTypeID, $receivedCurrency, $transactionSourceID, $transactionSourceLabel, $globalTransactionIdentificationRecordID, $receivedQuantity to the database.", $GLOBALS['criticalErrors']);	
						}
					}
				}
			}
			
			$responseObject['importedTransactions']							= true;
		}
		catch (PDOException $e) 
		{
			$cryptoTransaction 												= null;	
			$responseObject['importedTransactions']							= false;
			
			errorLog($e -> getMessage());
		
			die();
		}

        return $cryptoCurrencyTypesImported;
    }
	
	function getCoinTrackingSellsForUser($accountID, $dataImportEventRecordID, $cryptoCurrencyTypesImported, $userEncryptionKey, $globalCurrentDate, $sid, $dbh)
	{
		$responseObject 													= array();
		
		$transactionTypeID													= 4; // These are all sell records for now
		$transactionTypeLabel												= "Sell";
		$displayTransactionTypeLabel										= "Sold";
		$transactionSourceID												= 20;
		$transactionSourceLabel												= "CoinTracking";
		$importTypeID														= 16;
		$authorID															= 2;
		$isDebit															= 1;
		$isDisabled															= 0;
		
		$feeAmountInUSD														= 0;
		$feeAmountInBaseCurrency											= 0;
		
		$realizedReturnInUSD												= 0;
		$sentCostBasisInUSD													= 0;
		$receivedCostBasisInUSD												= 0;
		
		$transactionStatusID												= 1;
		$transactionStatusLabel												= "Completed";
		
		$providerNotes														= "";
		
		$creationDate													 	= $globalCurrentDate;
		
		$quoteCurrencyID													= 2;
		$quoteCurrency														= "USD";
		
		try
		{		
			$getCoinTrackingRecords											= $dbh -> prepare("SELECT
	transactions_FIFO_Universal.transactionRecordID,
	transactions_FIFO_Universal.FK_ProviderAccountWalletID,
	transactions_FIFO_Universal.transactionDate AS transactionTime,
	UNIX_TIMESTAMP(transactions_FIFO_Universal.transactionDate) AS transactionTimestamp,
	transactions_FIFO_Universal.FK_LedgerEntryTypeID,
	transactions_FIFO_Universal.FK_TransactionTypeID AS FK_EffectiveTypeID,
	transactions_FIFO_Universal.FK_TransactionTypeID,
	transactions_FIFO_Universal.FK_SentCurrencyID AS FK_AssetTypeID,
	transactions_FIFO_Universal.receivedQuantity AS amount,
	transactions_FIFO_Universal.isDebit,
	(transactions_FIFO_Universal.receivedQuantity / transactions_FIFO_Universal.sentQuantity) AS baseToQuoteCurrencySpotPrice,
	(transactions_FIFO_Universal.receivedQuantity / transactions_FIFO_Universal.sentQuantity) AS baseToUSDCurrencySpotPrice,
	transactions_FIFO_Universal.FK_SentWalletID AS FK_BaseCurrencyWalletID,
	transactions_FIFO_Universal.FK_ReceivedWalletID AS FK_QuoteCurrencyWalletID,
	transactions_FIFO_Universal.receivedQuantity,
	transactions_FIFO_Universal.sentQuantity,
	transactions_FIFO_Universal.isDisabled,
	transactions_FIFO_Universal.FK_TransactionSourceID,
	20 AS FK_ExchangeID,
	transactions_FIFO_Universal.FK_ReceivedCurrencyID,
	transactions_FIFO_Universal.receivedCurrency,
	transactions_FIFO_Universal.FK_SentCurrencyID,
	transactions_FIFO_Universal.sentCurrency,
	transactions_FIFO_Universal.FK_ReceivedWalletID,
	transactions_FIFO_Universal.FK_SentWalletID,
	transactions_FIFO_Universal.realizedReturnInUSD,
	transactions_FIFO_Universal.sentCostBasisInUSD,
	transactions_FIFO_Universal.receivedCostBasisInUSD,
	transactions_FIFO_Universal.receivedWalletType,
	transactions_FIFO_Universal.receivedWallet,
	transactions_FIFO_Universal.sentWalletType,
	transactions_FIFO_Universal.sentWallet,
	transactions_FIFO_Universal.FK_ReceivedTransactionSourceID,	
	transactions_FIFO_Universal.FK_SentTransactionSourceID
FROM
	transactions_FIFO_Universal
WHERE
	transactions_FIFO_Universal.FK_TransactionTypeID = 4
ORDER BY
	UNIX_TIMESTAMP(transactions_FIFO_Universal.transactionDate)");
	
			$insertCoinTrackingRecords										= $dbh -> prepare("INSERT INTO CoinTrackingLedgerTransaction
(
	transactionRecordID,
	FK_GlobalTransactionRecordID,
	FK_AccountID,
	FK_ProviderAccountWalletID,
	transactionTime,
	transactionTimestamp,
	FK_EffectiveTypeID,
	FK_TransactionTypeID,
	FK_AssetTypeID,
	amount,
	isDebit,
	baseToQuoteCurrencySpotPrice,
	baseToUSDCurrencySpotPrice,
	btcSpotPriceAtTimeOfTransaction,
	FK_BaseCurrencyWalletID,
	FK_QuoteCurrencyWalletID,
	receivedQuantity,
	sentQuantity,
	FK_TransactionSourceID,
	FK_ExchangeID,
	FK_ReceivedCurrencyID,
	receivedCurrencyAbbreviation,
	FK_SentCurrencyID,
	sentCurrencyAbbreviation,
	FK_ReceivedWalletID,
	FK_SentWalletID,
	receivedWalletType,
	receivedWallet,
	sentWalletType,
	sentWallet
)
VALUES
(
	:transactionRecordID,
	:FK_GlobalTransactionRecordID,
	:FK_AccountID,
	:FK_ProviderAccountWalletID,
	:transactionTime,
	:transactionTimestamp,
	:FK_EffectiveTypeID,
	:FK_TransactionTypeID,
	:FK_AssetTypeID,
	:amount,
	:isDebit,
	:baseToQuoteCurrencySpotPrice,
	:baseToUSDCurrencySpotPrice,
	:btcSpotPriceAtTimeOfTransaction,
	:FK_BaseCurrencyWalletID,
	:FK_QuoteCurrencyWalletID,
	:receivedQuantity,
	:sentQuantity,
	:FK_TransactionSourceID,
	:FK_ExchangeID,
	:FK_ReceivedCurrencyID,
	:receivedCurrencyAbbreviation,
	:FK_SentCurrencyID,
	:sentCurrencyAbbreviation,
	:FK_ReceivedWalletID,
	:FK_SentWalletID,
	:receivedWalletType,
	:receivedWallet,
	:sentWalletType,
	:sentWallet
)");
	
			if ($getCoinTrackingRecords -> execute() && $getCoinTrackingRecords -> rowCount() > 0)
			{
				errorLog("began get cointracking buy transaction records ".$getCoinTrackingRecords -> rowCount() > 0);
				
				while ($row = $getCoinTrackingRecords -> fetchObject())
				{
					$transactionRecordID									= $row -> transactionRecordID;
					$FK_ProviderAccountWalletID								= $row -> FK_ProviderAccountWalletID;
					$transactionTime										= $row -> transactionTime;
					$transactionTimestamp									= $row -> transactionTimestamp;
					$FK_LedgerEntryTypeID									= $row -> FK_LedgerEntryTypeID;
					$FK_EffectiveTypeID										= $row -> FK_EffectiveTypeID;
					$FK_TransactionTypeID									= $row -> FK_TransactionTypeID;
					$FK_AssetTypeID											= $row -> FK_AssetTypeID;
					$amount													= $row -> amount;
					$baseToQuoteCurrencySpotPrice							= $row -> baseToQuoteCurrencySpotPrice;							
					$baseToUSDCurrencySpotPrice								= $row -> baseToUSDCurrencySpotPrice;
					
					$FK_BaseCurrencyWalletID								= $row -> FK_BaseCurrencyWalletID;
					$FK_QuoteCurrencyWalletID								= $row -> FK_QuoteCurrencyWalletID;
					$receivedQuantity										= $row -> receivedQuantity;
					$sentQuantity											= $row -> sentQuantity;
					$FK_TransactionSourceID									= $row -> FK_TransactionSourceID;
					$FK_ExchangeID											= $row -> FK_ExchangeID;
					$FK_ReceivedCurrencyID									= $row -> FK_ReceivedCurrencyID;
					$receivedCurrency										= $row -> receivedCurrency;
					$FK_SentCurrencyID										= $row -> FK_SentCurrencyID;
					$sentCurrency											= $row -> sentCurrency;
					$FK_ReceivedWalletID									= $row -> FK_ReceivedWalletID;
					$FK_SentWalletID										= $row -> FK_SentWalletID;
					$receivedWalletType										= $row -> receivedWalletType;
					$receivedWallet											= $row -> receivedWallet;
					$sentWalletType											= $row -> sentWalletType;
					$sentWallet 											= $row -> sentWallet;
					$FK_ReceivedTransactionSourceID							= $row -> FK_ReceivedTransactionSourceID;
					$FK_SentTransactionSourceID								= $row -> FK_SentTransactionSourceID;

					if (isset($cryptoCurrencyTypesImported[$FK_AssetTypeID][$quoteCurrencyID]))
					{
						$currentCount										= $cryptoCurrencyTypesImported[$FK_AssetTypeID][$quoteCurrencyID];
						$currentCount++;
						
						$cryptoCurrencyTypesImported[$FK_AssetTypeID][$quoteCurrencyID]	= $currentCount;
					}
					else
					{
						$cryptoCurrencyTypesImported[$FK_AssetTypeID][$quoteCurrencyID]	= 1;	
					}
					
					// create asset type status record for this data import event record ID
					createDataImportAssetStatusRecord($accountID, $userEncryptionKey, $dataImportEventRecordID, $FK_AssetTypeID, $quoteCurrencyID, $globalCurrentDate, $sid, $dbh);

					$transactionAmountInUSD									= $amount;
					$transactionAmountMinusFeeInUSD							= $amount;

					$globalTransactionIdentificationRecordID				= 0;

					$nativeTransactionIDValue								= md5("$transactionSourceID $transactionTimestamp $FK_TransactionTypeID $FK_ReceivedCurrencyID $FK_SentCurrencyID $receivedWalletType $sentWalletType $amount $sentQuantity $receivedCurrency".md5("$sentQuantity.$transactionTime.$accountID"));

					$profitStanceTransactionIDValue							= createProfitStanceTransactionIDValue($accountID, $FK_AssetTypeID, $transactionSourceID, $nativeTransactionIDValue, $globalCurrentDate, $sid);

					$globalTransactionCreationResults						= createGlobalTransactionIdentificationRecordWithProfitStanceTransactionIDValue($accountID, $FK_AssetTypeID, $dataImportEventRecordID, $nativeTransactionIDValue, $profitStanceTransactionIDValue, $transactionSourceID, $globalCurrentDate, $sid, $dbh);
					
					if ($globalTransactionCreationResults['createdGlobalTransactionIdentificationRecord'] == true)
					{
						$globalTransactionIdentificationRecordID			= $globalTransactionCreationResults['globalTransactionIdentificationRecordID'];
					}	
					
					$btcSpotPriceAtTimeOfTransaction						= 0;
					
					if ($FK_ReceivedCurrencyID == 2 && $FK_SentCurrencyID == 1)
					{
						$btcSpotPriceAtTimeOfTransaction					= $baseToUSDCurrencySpotPrice;
					}
					else
					{
						$cascadeRetrieveSpotPriceResponseObject				= getSpotPriceForAssetPairUsingSourceCascade(1, 2, $transactionTime, 14, "CoinGecko price by date", $dbh);
						
						if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
						{
							$btcSpotPriceAtTimeOfTransaction				= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
						}	
					}
					
					$unspentTransactionTotal								= 0;
					$unfundedSpendTotal										= 0;
					
					if ($isDebit == 0)
					{
						$unspentTransactionTotal  							= $amount;
					}
					else if ($isDebit == 1)
					{
						$unfundedSpendTotal									= $amount;	
					}
					
					$sourceWallet											= new CompleteCryptoWallet();
					$destinationWallet										= new CompleteCryptoWallet();
					
					$sourceWalletResponseObject								= $sourceWallet -> instantiateWalletUsingCryptoWalletRecordID($accountID, $FK_SentWalletID, $userEncryptionKey, $dbh);
			
					if ($sourceWalletResponseObject['instantiatedRecord'] == false)
					{
						errorLog("could not instantiate crypto wallet $accountID, $FK_SentWalletID, $userEncryptionKey");
						exit();
					}
					
					$destinationWalletResponseObject						= $destinationWallet -> instantiateWalletUsingCryptoWalletRecordID($accountID, $FK_ReceivedWalletID, $userEncryptionKey, $dbh);
			
					if ($destinationWalletResponseObject['instantiatedRecord'] == false)
					{
						errorLog("could not instantiate crypto wallet $accountID, $FK_ReceivedWalletID, $userEncryptionKey");
						exit();
					}
					
					errorLog("INSERT INTO CoinTrackingLedgerTransaction
(
	transactionRecordID,
	FK_GlobalTransactionRecordID,
	FK_AccountID,
	FK_ProviderAccountWalletID,
	transactionTime,
	transactionTimestamp,
	FK_EffectiveTypeID,
	FK_TransactionTypeID,
	FK_AssetTypeID,
	amount,
	isDebit,
	baseToQuoteCurrencySpotPrice,
	baseToUSDCurrencySpotPrice,
	btcSpotPriceAtTimeOfTransaction,
	FK_BaseCurrencyWalletID,
	FK_QuoteCurrencyWalletID,
	receivedQuantity,
	sentQuantity,
	FK_TransactionSourceID,
	FK_ExchangeID,
	FK_ReceivedCurrencyID,
	receivedCurrencyAbbreviation,
	FK_SentCurrencyID,
	sentCurrencyAbbreviation,
	FK_ReceivedWalletID,
	FK_SentWalletID,
	receivedWalletType,
	receivedWallet,
	sentWalletType,
	sentWallet
)
VALUES
(
	$transactionRecordID,
	$globalTransactionIdentificationRecordID,
	$accountID,
	$FK_ProviderAccountWalletID,
	'$transactionTime',
	$transactionTimestamp,
	$FK_EffectiveTypeID,
	$FK_TransactionTypeID,
	$FK_AssetTypeID,
	$amount,
	$isDebit,
	$baseToQuoteCurrencySpotPrice,
	$baseToUSDCurrencySpotPrice,
	$btcSpotPriceAtTimeOfTransaction,
	$FK_BaseCurrencyWalletID,
	$FK_QuoteCurrencyWalletID,
	$receivedQuantity,
	$sentQuantity,
	$transactionSourceID,
	$FK_ExchangeID,
	$FK_ReceivedCurrencyID,
	'$receivedCurrency',
	$FK_SentCurrencyID,
	'$sentCurrency',
	$FK_ReceivedWalletID,
	$FK_SentWalletID,
	'$receivedWalletType',
	'$receivedWallet',
	'$sentWalletType',
	'$sentWallet'
)");
					
					$insertCoinTrackingRecords -> bindValue(':transactionRecordID', $transactionRecordID);
					$insertCoinTrackingRecords -> bindValue(':FK_GlobalTransactionRecordID', $globalTransactionIdentificationRecordID);
					$insertCoinTrackingRecords -> bindValue(':FK_AccountID', $accountID);
					$insertCoinTrackingRecords -> bindValue(':FK_ProviderAccountWalletID', $FK_ProviderAccountWalletID);
					$insertCoinTrackingRecords -> bindValue(':transactionTime', $transactionTime);
					$insertCoinTrackingRecords -> bindValue(':transactionTimestamp', $transactionTimestamp);
					$insertCoinTrackingRecords -> bindValue(':FK_EffectiveTypeID', $FK_EffectiveTypeID);
					$insertCoinTrackingRecords -> bindValue(':FK_TransactionTypeID', $FK_TransactionTypeID);
					$insertCoinTrackingRecords -> bindValue(':FK_AssetTypeID', $FK_AssetTypeID);
					$insertCoinTrackingRecords -> bindValue(':amount', $amount);
					$insertCoinTrackingRecords -> bindValue(':isDebit', $isDebit);
					$insertCoinTrackingRecords -> bindValue(':baseToQuoteCurrencySpotPrice', $baseToQuoteCurrencySpotPrice);
					$insertCoinTrackingRecords -> bindValue(':baseToUSDCurrencySpotPrice', $baseToUSDCurrencySpotPrice);
					$insertCoinTrackingRecords -> bindValue(':btcSpotPriceAtTimeOfTransaction', $btcSpotPriceAtTimeOfTransaction);
					$insertCoinTrackingRecords -> bindValue(':FK_BaseCurrencyWalletID', $FK_BaseCurrencyWalletID);
					$insertCoinTrackingRecords -> bindValue(':FK_QuoteCurrencyWalletID', $FK_QuoteCurrencyWalletID);
					$insertCoinTrackingRecords -> bindValue(':receivedQuantity', $receivedQuantity);
					$insertCoinTrackingRecords -> bindValue(':sentQuantity', $sentQuantity);
					$insertCoinTrackingRecords -> bindValue(':FK_TransactionSourceID', $transactionSourceID);
					$insertCoinTrackingRecords -> bindValue(':FK_ExchangeID', $FK_ExchangeID);
					$insertCoinTrackingRecords -> bindValue(':FK_ReceivedCurrencyID', $FK_ReceivedCurrencyID);
					$insertCoinTrackingRecords -> bindValue(':receivedCurrencyAbbreviation', $receivedCurrency);
					$insertCoinTrackingRecords -> bindValue(':FK_SentCurrencyID', $FK_SentCurrencyID);
					$insertCoinTrackingRecords -> bindValue(':sentCurrencyAbbreviation', $sentCurrency);
					$insertCoinTrackingRecords -> bindValue(':FK_ReceivedWalletID', $FK_ReceivedWalletID);
					$insertCoinTrackingRecords -> bindValue(':FK_SentWalletID', $FK_SentWalletID);
					$insertCoinTrackingRecords -> bindValue(':receivedWalletType', $receivedWalletType);
					$insertCoinTrackingRecords -> bindValue(':receivedWallet', $receivedWallet);
					$insertCoinTrackingRecords -> bindValue(':sentWalletType', $sentWalletType);
					$insertCoinTrackingRecords -> bindValue(':sentWallet', $sentWallet);

					

					$nativeRecordID											= 0;

					if ($insertCoinTrackingRecords -> execute())
					{
						errorLog("insert coin tracking record worked");
						
						$nativeRecordID 									= $dbh -> lastInsertId();
					}
					
					$cryptoTransaction										= new CryptoTransaction();
					
					$cryptoTransaction -> setData(0, $accountID, $authorID, $globalTransactionIdentificationRecordID, $FK_TransactionTypeID, $transactionTypeLabel, $transactionStatusID, $transactionStatusLabel, $transactionSourceID, $transactionSourceLabel, $FK_AssetTypeID, $sentCurrency, $quoteCurrencyID, $quoteCurrency, $FK_SentWalletID, $FK_ReceivedWalletID, $transactionTime, $transactionTime, $transactionTimestamp, $nativeRecordID, $nativeRecordID, $sentQuantity, $amount, $baseToQuoteCurrencySpotPrice, $btcSpotPriceAtTimeOfTransaction, $transactionAmountInUSD, $feeAmountInBaseCurrency, $feeAmountInUSD, $unspentTransactionTotal, $providerNotes, $isDebit, $sid);
					
					$writeToDatabaseResponse								= $cryptoTransaction -> writeToDatabase($userEncryptionKey, $dbh);
						
					if ($writeToDatabaseResponse['wroteToDatabase'] == true)
					{
						$transactionID										= $cryptoTransaction -> getTransactionID();
					
						errorLog("transaction $transactionID created");
						
						$ledgerAmount										= $sentQuantity * -1;
						
						$profitStanceLedgerEntry							= new ProfitStanceLedgerEntry();
												
						$profitStanceLedgerEntry -> setData($accountID, $FK_AssetTypeID, $sentCurrency, $transactionSourceID, $transactionSourceLabel, $exchangeTileID, $globalTransactionIdentificationRecordID, $transactionTime, $ledgerAmount, $dbh);
														
						$writeProfitStanceLedgerEntryRecordResponseObject	= $profitStanceLedgerEntry -> writeToDatabase($dbh);
														
						if ($writeProfitStanceLedgerEntryRecordResponseObject['wroteToDatabase'] == true)
						{
							errorLog("wrote profitStance ledger entry $accountID, $FK_AssetTypeID, $sentCurrency, $transactionSourceID, $transactionSourceLabel, $globalTransactionIdentificationRecordID, $ledgerAmount to the database.", $GLOBALS['debugCoreFunctionality']);
						}
						else
						{
							errorLog("could not write profitStance ledger entry $accountID, $FK_AssetTypeID, $sentCurrency, $transactionSourceID, $transactionSourceLabel, $globalTransactionIdentificationRecordID, $ledgerAmount to the database.", $GLOBALS['criticalErrors']);	
						}
					}
				}
			}
			
			$responseObject['importedTransactions']							= true;
		}
		catch (PDOException $e) 
		{
			$cryptoTransaction 												= null;	
			$responseObject['importedTransactions']							= false;
			
			errorLog($e -> getMessage());
		
			die();
		}

        return $cryptoCurrencyTypesImported;
    }
	
	function getCoinTrackingSendForUser($accountID, $dataImportEventRecordID, $cryptoCurrencyTypesImported, $userEncryptionKey, $globalCurrentDate, $sid, $dbh)
	{
	    $responseObject 													= array();
    		
    	$transactionTypeID													= 2; // These are all sell records for now
		$transactionTypeLabel												= "Send";
    	$displayTransactionTypeLabel										= "Send";
		$transactionSourceID												= 20;
		$transactionSourceLabel												= "CoinTracking";
		$importTypeID														= 16;
		$authorID															= 2;
		$isDebit															= 1;
		$isDisabled															= 0;
		
		$feeAmountInUSD														= 0;
		$feeAmountInBaseCurrency											= 0;
		
		$realizedReturnInUSD												= 0;
		$sentCostBasisInUSD													= 0;
		$receivedCostBasisInUSD												= 0;
		
		$transactionStatusID												= 1;
		$transactionStatusLabel												= "Completed";
		
		$providerNotes														= "";
		
		$creationDate													 	= $globalCurrentDate;
		
		$quoteCurrencyID													= 2;
		$quoteCurrency														= "USD";
		
		try
		{		
			$getCoinTrackingRecords											= $dbh -> prepare("SELECT
	transactions_FIFO_Universal.transactionRecordID,
	transactions_FIFO_Universal.FK_ProviderAccountWalletID,
	transactions_FIFO_Universal.transactionDate AS transactionTime,
	UNIX_TIMESTAMP(transactions_FIFO_Universal.transactionDate) AS transactionTimestamp,
	transactions_FIFO_Universal.FK_LedgerEntryTypeID,
	transactions_FIFO_Universal.FK_TransactionTypeID AS FK_EffectiveTypeID,
	transactions_FIFO_Universal.FK_TransactionTypeID,
	transactions_FIFO_Universal.FK_SentCurrencyID AS FK_AssetTypeID,
	transactions_FIFO_Universal.receivedQuantity AS amount,
	transactions_FIFO_Universal.isDebit,
	transactions_FIFO_Universal.FK_SentWalletID AS FK_BaseCurrencyWalletID,
	transactions_FIFO_Universal.FK_ReceivedWalletID AS FK_QuoteCurrencyWalletID,
	transactions_FIFO_Universal.receivedQuantity,
	transactions_FIFO_Universal.sentQuantity,
	transactions_FIFO_Universal.isDisabled,
	transactions_FIFO_Universal.FK_TransactionSourceID,
	20 AS FK_ExchangeID,
	transactions_FIFO_Universal.FK_ReceivedCurrencyID,
	transactions_FIFO_Universal.receivedCurrency,
	transactions_FIFO_Universal.FK_SentCurrencyID,
	transactions_FIFO_Universal.sentCurrency,
	transactions_FIFO_Universal.FK_ReceivedWalletID,
	transactions_FIFO_Universal.FK_SentWalletID,
	transactions_FIFO_Universal.realizedReturnInUSD,
	transactions_FIFO_Universal.sentCostBasisInUSD,
	transactions_FIFO_Universal.receivedCostBasisInUSD,
	transactions_FIFO_Universal.receivedWalletType,
	transactions_FIFO_Universal.receivedWallet,
	transactions_FIFO_Universal.sentWalletType,
	transactions_FIFO_Universal.sentWallet,
	transactions_FIFO_Universal.FK_ReceivedTransactionSourceID,	
	transactions_FIFO_Universal.FK_SentTransactionSourceID
FROM
	transactions_FIFO_Universal
WHERE
	transactions_FIFO_Universal.FK_TransactionTypeID = 2
ORDER BY
	UNIX_TIMESTAMP(transactions_FIFO_Universal.transactionDate)");
	
			$insertCoinTrackingRecords										= $dbh -> prepare("INSERT INTO CoinTrackingLedgerTransaction
(
	transactionRecordID,
	FK_GlobalTransactionRecordID,
	FK_AccountID,
	FK_ProviderAccountWalletID,
	transactionTime,
	transactionTimestamp,
	FK_EffectiveTypeID,
	FK_TransactionTypeID,
	FK_LedgerEntryTypeID,
	FK_AssetTypeID,
	amount,
	isDebit,
	baseToQuoteCurrencySpotPrice,
	baseToUSDCurrencySpotPrice,
	btcSpotPriceAtTimeOfTransaction,
	FK_BaseCurrencyWalletID,
	FK_QuoteCurrencyWalletID,
	receivedQuantity,
	sentQuantity,
	FK_TransactionSourceID,
	FK_ExchangeID,
	FK_ReceivedCurrencyID,
	receivedCurrencyAbbreviation,
	FK_SentCurrencyID,
	sentCurrencyAbbreviation,
	FK_ReceivedWalletID,
	FK_SentWalletID,
	receivedWalletType,
	receivedWallet,
	sentWalletType,
	sentWallet
)
VALUES
(
	:transactionRecordID,
	:FK_GlobalTransactionRecordID,
	:FK_AccountID,
	:FK_ProviderAccountWalletID,
	:transactionTime,
	:transactionTimestamp,
	:FK_EffectiveTypeID,
	:FK_TransactionTypeID,
	:FK_LedgerEntryTypeID,
	:FK_AssetTypeID,
	:amount,
	:isDebit,
	:baseToQuoteCurrencySpotPrice,
	:baseToUSDCurrencySpotPrice,
	:btcSpotPriceAtTimeOfTransaction,
	:FK_BaseCurrencyWalletID,
	:FK_QuoteCurrencyWalletID,
	:receivedQuantity,
	:sentQuantity,
	:FK_TransactionSourceID,
	:FK_ExchangeID,
	:FK_ReceivedCurrencyID,
	:receivedCurrencyAbbreviation,
	:FK_SentCurrencyID,
	:sentCurrencyAbbreviation,
	:FK_ReceivedWalletID,
	:FK_SentWalletID,
	:receivedWalletType,
	:receivedWallet,
	:sentWalletType,
	:sentWallet
)");
	
			if ($getCoinTrackingRecords -> execute() && $getCoinTrackingRecords -> rowCount() > 0)
			{
				errorLog("began get cointracking send transaction records ".$getCoinTrackingRecords -> rowCount() > 0);
				
				while ($row = $getCoinTrackingRecords -> fetchObject())
				{
					$transactionRecordID									= $row -> transactionRecordID;
					$FK_ProviderAccountWalletID								= $row -> FK_ProviderAccountWalletID;
					$transactionTime										= $row -> transactionTime;
					$transactionTimestamp									= $row -> transactionTimestamp;
					$FK_LedgerEntryTypeID									= $row -> FK_LedgerEntryTypeID;
					$FK_EffectiveTypeID										= $row -> FK_EffectiveTypeID;
					$FK_TransactionTypeID									= $row -> FK_TransactionTypeID;
					$FK_AssetTypeID											= $row -> FK_AssetTypeID;
					$amount													= $row -> amount;
					$FK_BaseCurrencyWalletID								= $row -> FK_BaseCurrencyWalletID;
					$FK_QuoteCurrencyWalletID								= $row -> FK_QuoteCurrencyWalletID;
					$receivedQuantity										= $row -> receivedQuantity;
					$sentQuantity											= $row -> sentQuantity;
					$FK_TransactionSourceID									= $row -> FK_TransactionSourceID;
					$FK_ExchangeID											= $row -> FK_ExchangeID;
					$FK_ReceivedCurrencyID									= $row -> FK_ReceivedCurrencyID;
					$receivedCurrency										= $row -> receivedCurrency;
					$FK_SentCurrencyID										= $row -> FK_SentCurrencyID;
					$sentCurrency											= $row -> sentCurrency;
					$FK_ReceivedWalletID									= $row -> FK_ReceivedWalletID;
					$FK_SentWalletID										= $row -> FK_SentWalletID;
					$receivedWalletType										= $row -> receivedWalletType;
					$receivedWallet											= $row -> receivedWallet;
					$sentWalletType											= $row -> sentWalletType;
					$sentWallet 											= $row -> sentWallet;
					$FK_ReceivedTransactionSourceID							= $row -> FK_ReceivedTransactionSourceID;
					$FK_SentTransactionSourceID								= $row -> FK_SentTransactionSourceID;

					if (isset($cryptoCurrencyTypesImported[$FK_AssetTypeID][$quoteCurrencyID]))
					{
						$currentCount										= $cryptoCurrencyTypesImported[$FK_AssetTypeID][$quoteCurrencyID];
						$currentCount++;
						
						$cryptoCurrencyTypesImported[$FK_AssetTypeID][$quoteCurrencyID]	= $currentCount;
					}
					else
					{
						$cryptoCurrencyTypesImported[$FK_AssetTypeID][$quoteCurrencyID]	= 1;	
					}
					
					// create asset type status record for this data import event record ID
					createDataImportAssetStatusRecord($accountID, $userEncryptionKey, $dataImportEventRecordID, $FK_AssetTypeID, $quoteCurrencyID, $globalCurrentDate, $sid, $dbh);

					$globalTransactionIdentificationRecordID				= 0;

					$baseToQuoteCurrencySpotPrice							= 0;							
					$baseToUSDCurrencySpotPrice								= 0;
					$btcSpotPriceAtTimeOfTransaction						= 0;

					$cascadeRetrieveSpotPriceResponseObject					= getSpotPriceForAssetPairUsingSourceCascade($FK_SentCurrencyID, 2, $transactionTime, 14, "CoinGecko price by date", $dbh);
						
					if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
					{
						$baseToQuoteCurrencySpotPrice						= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
						$baseToUSDCurrencySpotPrice							= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
					}
					
					if ($FK_SentCurrencyID == 1)
					{
						$btcSpotPriceAtTimeOfTransaction					= $baseToUSDCurrencySpotPrice;	
					}
					else
					{
						$cascadeRetrieveSpotPriceResponseObject				= getSpotPriceForAssetPairUsingSourceCascade(1, 2, $transactionTime, 14, "CoinGecko price by date", $dbh);
						
						if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
						{
							$btcSpotPriceAtTimeOfTransaction				= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
						}	
					}
					
					$transactionAmountInUSD									= $baseToUSDCurrencySpotPrice * $sentQuantity;
					$transactionAmountMinusFeeInUSD							= $transactionAmountInUSD;
					$receivedQuantity										= $transactionAmountInUSD;
					$amount													= $transactionAmountInUSD;

					$nativeTransactionIDValue								= md5("$transactionSourceID $transactionTimestamp $FK_TransactionTypeID $FK_ReceivedCurrencyID $FK_SentCurrencyID $receivedWalletType $sentWalletType $amount $sentQuantity $receivedCurrency".md5("$sentQuantity.$transactionTime.$accountID"));

					$profitStanceTransactionIDValue							= createProfitStanceTransactionIDValue($accountID, $FK_AssetTypeID, $transactionSourceID, $nativeTransactionIDValue, $globalCurrentDate, $sid);

					$globalTransactionCreationResults						= createGlobalTransactionIdentificationRecordWithProfitStanceTransactionIDValue($accountID, $FK_AssetTypeID, $dataImportEventRecordID, $nativeTransactionIDValue, $profitStanceTransactionIDValue, $transactionSourceID, $globalCurrentDate, $sid, $dbh);
					
					if ($globalTransactionCreationResults['createdGlobalTransactionIdentificationRecord'] == true)
					{
						$globalTransactionIdentificationRecordID			= $globalTransactionCreationResults['globalTransactionIdentificationRecordID'];
					}	
					
					$unspentTransactionTotal								= 0;
					$unfundedSpendTotal										= 0;
					
					if ($isDebit == 0)
					{
						$unspentTransactionTotal  							= $amount;
					}
					else if ($isDebit == 1)
					{
						$unfundedSpendTotal									= $amount;	
					}
					
					$sourceWallet											= new CompleteCryptoWallet();
					$destinationWallet										= new CompleteCryptoWallet();
					
					$sourceWalletResponseObject								= $sourceWallet -> instantiateWalletUsingCryptoWalletRecordID($accountID, $FK_SentWalletID, $userEncryptionKey, $dbh);
			
					if ($sourceWalletResponseObject['instantiatedRecord'] == false)
					{
						errorLog("could not instantiate crypto wallet $accountID, $FK_SentWalletID, $userEncryptionKey");
						exit();
					}
					
					$destinationWalletResponseObject						= $destinationWallet -> instantiateWalletUsingCryptoWalletRecordID($accountID, $FK_ReceivedWalletID, $userEncryptionKey, $dbh);
			
					if ($destinationWalletResponseObject['instantiatedRecord'] == false)
					{
						errorLog("could not instantiate crypto wallet $accountID, $FK_ReceivedWalletID, $userEncryptionKey");
						exit();
					}
					
					errorLog("INSERT INTO CoinTrackingLedgerTransaction
(
	transactionRecordID,
	FK_GlobalTransactionRecordID,
	FK_AccountID,
	FK_ProviderAccountWalletID,
	transactionTime,
	transactionTimestamp,
	FK_EffectiveTypeID,
	FK_TransactionTypeID,
	FK_LedgerEntryTypeID,
	FK_AssetTypeID,
	amount,
	isDebit,
	baseToQuoteCurrencySpotPrice,
	baseToUSDCurrencySpotPrice,
	btcSpotPriceAtTimeOfTransaction,
	FK_BaseCurrencyWalletID,
	FK_QuoteCurrencyWalletID,
	receivedQuantity,
	sentQuantity,
	FK_TransactionSourceID,
	FK_ExchangeID,
	FK_ReceivedCurrencyID,
	receivedCurrencyAbbreviation,
	FK_SentCurrencyID,
	sentCurrencyAbbreviation,
	FK_ReceivedWalletID,
	FK_SentWalletID,
	receivedWalletType,
	receivedWallet,
	sentWalletType,
	sentWallet
)
VALUES
(
	$transactionRecordID,
	$globalTransactionIdentificationRecordID,
	$accountID,
	$FK_ProviderAccountWalletID,
	'$transactionTime',
	$transactionTimestamp,
	$FK_EffectiveTypeID,
	$FK_TransactionTypeID,
	$FK_LedgerEntryTypeID,
	$FK_AssetTypeID,
	$amount,
	$isDebit,
	$baseToQuoteCurrencySpotPrice,
	$baseToUSDCurrencySpotPrice,
	$btcSpotPriceAtTimeOfTransaction,
	$FK_BaseCurrencyWalletID,
	$FK_QuoteCurrencyWalletID,
	$receivedQuantity,
	$sentQuantity,
	$transactionSourceID,
	$FK_ExchangeID,
	$FK_ReceivedCurrencyID,
	'$receivedCurrency',
	$FK_SentCurrencyID,
	'$sentCurrency',
	$FK_ReceivedWalletID,
	$FK_SentWalletID,
	'$receivedWalletType',
	'$receivedWallet',
	'$sentWalletType',
	'$sentWallet'
)");
					
					$insertCoinTrackingRecords -> bindValue(':transactionRecordID', $transactionRecordID);
					$insertCoinTrackingRecords -> bindValue(':FK_GlobalTransactionRecordID', $globalTransactionIdentificationRecordID);
					$insertCoinTrackingRecords -> bindValue(':FK_AccountID', $accountID);
					$insertCoinTrackingRecords -> bindValue(':FK_ProviderAccountWalletID', $FK_ProviderAccountWalletID);
					$insertCoinTrackingRecords -> bindValue(':transactionTime', $transactionTime);
					$insertCoinTrackingRecords -> bindValue(':transactionTimestamp', $transactionTimestamp);
					$insertCoinTrackingRecords -> bindValue(':FK_EffectiveTypeID', $FK_EffectiveTypeID);
					$insertCoinTrackingRecords -> bindValue(':FK_TransactionTypeID', $FK_TransactionTypeID);
					$insertCoinTrackingRecords -> bindValue(':FK_LedgerEntryTypeID', $FK_LedgerEntryTypeID);
					$insertCoinTrackingRecords -> bindValue(':FK_AssetTypeID', $FK_AssetTypeID);
					$insertCoinTrackingRecords -> bindValue(':amount', $amount);
					$insertCoinTrackingRecords -> bindValue(':isDebit', $isDebit);
					$insertCoinTrackingRecords -> bindValue(':baseToQuoteCurrencySpotPrice', $baseToQuoteCurrencySpotPrice);
					$insertCoinTrackingRecords -> bindValue(':baseToUSDCurrencySpotPrice', $baseToUSDCurrencySpotPrice);
					$insertCoinTrackingRecords -> bindValue(':btcSpotPriceAtTimeOfTransaction', $btcSpotPriceAtTimeOfTransaction);
					$insertCoinTrackingRecords -> bindValue(':FK_BaseCurrencyWalletID', $FK_BaseCurrencyWalletID);
					$insertCoinTrackingRecords -> bindValue(':FK_QuoteCurrencyWalletID', $FK_QuoteCurrencyWalletID);
					$insertCoinTrackingRecords -> bindValue(':receivedQuantity', $receivedQuantity);
					$insertCoinTrackingRecords -> bindValue(':sentQuantity', $sentQuantity);
					$insertCoinTrackingRecords -> bindValue(':FK_TransactionSourceID', $transactionSourceID);
					$insertCoinTrackingRecords -> bindValue(':FK_ExchangeID', $FK_ExchangeID);
					$insertCoinTrackingRecords -> bindValue(':FK_ReceivedCurrencyID', $FK_ReceivedCurrencyID);
					$insertCoinTrackingRecords -> bindValue(':receivedCurrencyAbbreviation', $receivedCurrency);
					$insertCoinTrackingRecords -> bindValue(':FK_SentCurrencyID', $FK_SentCurrencyID);
					$insertCoinTrackingRecords -> bindValue(':sentCurrencyAbbreviation', $sentCurrency);
					$insertCoinTrackingRecords -> bindValue(':FK_ReceivedWalletID', $FK_ReceivedWalletID);
					$insertCoinTrackingRecords -> bindValue(':FK_SentWalletID', $FK_SentWalletID);
					$insertCoinTrackingRecords -> bindValue(':receivedWalletType', $receivedWalletType);
					$insertCoinTrackingRecords -> bindValue(':receivedWallet', $receivedWallet);
					$insertCoinTrackingRecords -> bindValue(':sentWalletType', $sentWalletType);
					$insertCoinTrackingRecords -> bindValue(':sentWallet', $sentWallet);

					

					$nativeRecordID											= 0;

					if ($insertCoinTrackingRecords -> execute())
					{
						errorLog("insert coin tracking record worked");
						
						$nativeRecordID 									= $dbh -> lastInsertId();
					}
					
					$cryptoTransaction										= new CryptoTransaction();
					
					$cryptoTransaction -> setData(0, $accountID, $authorID, $globalTransactionIdentificationRecordID, $FK_TransactionTypeID, $transactionTypeLabel, $transactionStatusID, $transactionStatusLabel, $transactionSourceID, $transactionSourceLabel, $FK_AssetTypeID, $sentCurrency, $quoteCurrencyID, $quoteCurrency, $FK_SentWalletID, $FK_ReceivedWalletID, $transactionTime, $transactionTime, $transactionTimestamp, $nativeRecordID, $nativeRecordID, $sentQuantity, $amount, $baseToQuoteCurrencySpotPrice, $btcSpotPriceAtTimeOfTransaction, $transactionAmountInUSD, $feeAmountInBaseCurrency, $feeAmountInUSD, $unspentTransactionTotal, $providerNotes, $isDebit, $sid);
					
					$writeToDatabaseResponse								= $cryptoTransaction -> writeToDatabase($userEncryptionKey, $dbh);
						
					if ($writeToDatabaseResponse['wroteToDatabase'] == true)
					{
						$transactionID										= $cryptoTransaction -> getTransactionID();
					
						errorLog("transaction $transactionID created");
						
						$ledgerAmount										= $sentQuantity * -1;
						
						$profitStanceLedgerEntry							= new ProfitStanceLedgerEntry();
												
						$profitStanceLedgerEntry -> setData($accountID, $FK_AssetTypeID, $sentCurrency, $transactionSourceID, $transactionSourceLabel, $exchangeTileID, $globalTransactionIdentificationRecordID, $transactionTime, $ledgerAmount, $dbh);
														
						$writeProfitStanceLedgerEntryRecordResponseObject	= $profitStanceLedgerEntry -> writeToDatabase($dbh);
														
						if ($writeProfitStanceLedgerEntryRecordResponseObject['wroteToDatabase'] == true)
						{
							errorLog("wrote profitStance ledger entry $accountID, $FK_AssetTypeID, $sentCurrency, $transactionSourceID, $transactionSourceLabel, $globalTransactionIdentificationRecordID, $ledgerAmount to the database.", $GLOBALS['debugCoreFunctionality']);
						}
						else
						{
							errorLog("could not write profitStance ledger entry $accountID, $FK_AssetTypeID, $sentCurrency, $transactionSourceID, $transactionSourceLabel, $globalTransactionIdentificationRecordID, $ledgerAmount to the database.", $GLOBALS['criticalErrors']);	
						}
					}
				}
			}
			
			$responseObject['importedTransactions']							= true;
		}
		catch (PDOException $e) 
		{
			$cryptoTransaction 												= null;	
			$responseObject['importedTransactions']							= false;
			
			errorLog($e -> getMessage());
		
			die();
		}

        return $cryptoCurrencyTypesImported;
    }
	
	function getCoinTrackingTradesForUser($accountID, $dataImportEventRecordID, $cryptoCurrencyTypesImported, $userEncryptionKey, $globalCurrentDate, $sid, $dbh)
	{
		$responseObject 													= array();
		
		$transactionTypeID													= 15;
		$transactionTypeLabel												= "Trade";
		
		$transactionSourceID												= 20;
		$transactionSourceLabel												= "CoinTracking";
		$importTypeID														= 16;
		$authorID															= 2;
		$isDebit															= 0;
		$isDisabled															= 0;
		
		$feeAmountInUSD														= 0;
		$feeAmountInBaseCurrency											= 0;
		
		$realizedReturnInUSD												= 0;
		$sentCostBasisInUSD													= 0;
		$receivedCostBasisInUSD												= 0;
		
		$FK_ExchangeID														= 20;
		
		$transactionStatusID												= 1;
		$transactionStatusLabel												= "Completed";
		
		$providerNotes														= "";
		
		$creationDate													 	= $globalCurrentDate;
		
		$quoteCurrencyID													= 2;
		$quoteCurrency														= "USD";
		
		try
		{		
			$getCoinTrackingRecords											= $dbh -> prepare("SELECT
	transactions_FIFO_Universal.transactionRecordID,
	transactions_FIFO_Universal.FK_ProviderAccountWalletID,
	transactions_FIFO_Universal.transactionDate AS transactionTime,
	UNIX_TIMESTAMP(transactions_FIFO_Universal.transactionDate) AS transactionTimestamp,
	transactions_FIFO_Universal.FK_TransactionTypeID,
	transactions_FIFO_Universal.isDebit,
	transactions_FIFO_Universal.FK_ReceivedWalletID,
	transactions_FIFO_Universal.FK_SentWalletID,
	transactions_FIFO_Universal.receivedQuantity,
	transactions_FIFO_Universal.sentQuantity,
	transactions_FIFO_Universal.isDisabled,
	20 AS FK_ExchangeID,
	transactions_FIFO_Universal.FK_ReceivedCurrencyID,
	transactions_FIFO_Universal.receivedCurrency,
	transactions_FIFO_Universal.FK_SentCurrencyID,
	transactions_FIFO_Universal.sentCurrency,
	transactions_FIFO_Universal.FK_ReceivedWalletID,
	transactions_FIFO_Universal.FK_SentWalletID,
	transactions_FIFO_Universal.receivedWalletType,
	transactions_FIFO_Universal.receivedWallet,
	transactions_FIFO_Universal.sentWalletType,
	transactions_FIFO_Universal.sentWallet,
	transactions_FIFO_Universal.FK_ReceivedTransactionSourceID,	
	transactions_FIFO_Universal.FK_SentTransactionSourceID
FROM
	transactions_FIFO_Universal
WHERE
	transactions_FIFO_Universal.FK_TransactionTypeID = 15
ORDER BY
	UNIX_TIMESTAMP(transactions_FIFO_Universal.transactionDate)");
	
			$insertCoinTrackingRecords										= $dbh -> prepare("INSERT INTO CoinTrackingLedgerTransaction
(
	transactionRecordID,
	FK_GlobalTransactionRecordID,
	FK_AccountID,
	FK_ProviderAccountWalletID,
	transactionTime,
	transactionTimestamp,
	FK_EffectiveTypeID,
	FK_TransactionTypeID,
	FK_AssetTypeID,
	amount,
	isDebit,
	baseToQuoteCurrencySpotPrice,
	baseToUSDCurrencySpotPrice,
	btcSpotPriceAtTimeOfTransaction,
	FK_BaseCurrencyWalletID,
	FK_QuoteCurrencyWalletID,
	receivedQuantity,
	sentQuantity,
	FK_TransactionSourceID,
	FK_ExchangeID,
	FK_ReceivedCurrencyID,
	receivedCurrencyAbbreviation,
	FK_SentCurrencyID,
	sentCurrencyAbbreviation,
	FK_ReceivedWalletID,
	FK_SentWalletID,
	receivedWalletType,
	receivedWallet,
	sentWalletType,
	sentWallet
)
VALUES
(
	:transactionRecordID,
	:FK_GlobalTransactionRecordID,
	:FK_AccountID,
	:FK_ProviderAccountWalletID,
	:transactionTime,
	:transactionTimestamp,
	:FK_EffectiveTypeID,
	:FK_TransactionTypeID,
	:FK_AssetTypeID,
	:amount,
	:isDebit,
	:baseToQuoteCurrencySpotPrice,
	:baseToUSDCurrencySpotPrice,
	:btcSpotPriceAtTimeOfTransaction,
	:FK_BaseCurrencyWalletID,
	:FK_QuoteCurrencyWalletID,
	:receivedQuantity,
	:sentQuantity,
	:FK_TransactionSourceID,
	:FK_ExchangeID,
	:FK_ReceivedCurrencyID,
	:receivedCurrencyAbbreviation,
	:FK_SentCurrencyID,
	:sentCurrencyAbbreviation,
	:FK_ReceivedWalletID,
	:FK_SentWalletID,
	:receivedWalletType,
	:receivedWallet,
	:sentWalletType,
	:sentWallet
)");
	
			if ($getCoinTrackingRecords -> execute() && $getCoinTrackingRecords -> rowCount() > 0)
			{
				errorLog("began get cointracking buy transaction records ".$getCoinTrackingRecords -> rowCount() > 0);
				
				while ($row = $getCoinTrackingRecords -> fetchObject())
				{
					
					$transactionRecordID									= $row -> transactionRecordID;
					$FK_ProviderAccountWalletID								= $row -> FK_ProviderAccountWalletID;
					$transactionTime										= $row -> transactionTime;
					$transactionTimestamp									= $row -> transactionTimestamp;
					
					$FK_ReceivedWalletID									= $row -> FK_ReceivedWalletID;
					$FK_SentWalletID										= $row -> FK_SentWalletID;
					
					$receivedQuantity										= $row -> receivedQuantity;
					$sentQuantity											= $row -> sentQuantity;
					
					$FK_ReceivedCurrencyID									= $row -> FK_ReceivedCurrencyID;
					$FK_SentCurrencyID										= $row -> FK_SentCurrencyID;
					
					$receivedCurrency										= $row -> receivedCurrency;
					$sentCurrency											= $row -> sentCurrency;
					
					$FK_ReceivedWalletID									= $row -> FK_ReceivedWalletID;
					$FK_SentWalletID										= $row -> FK_SentWalletID;
					
					$receivedWalletType										= $row -> receivedWalletType;
					$sentWalletType											= $row -> sentWalletType;
					
					$receivedWallet											= $row -> receivedWallet;
					$sentWallet 											= $row -> sentWallet;
					
					$FK_ReceivedTransactionSourceID							= $row -> FK_ReceivedTransactionSourceID;
					$FK_SentTransactionSourceID								= $row -> FK_SentTransactionSourceID;
					
					$btcSpotPriceAtTimeOfTransaction						= 0;
					
					$cascadeRetrieveSpotPriceResponseObject					= getSpotPriceForAssetPairUsingSourceCascade(1, 2, $transactionTime, 14, "CoinGecko price by date", $dbh);
						
					if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
					{
						$btcSpotPriceAtTimeOfTransaction					= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
					}	
					
					$sourceWallet											= new CompleteCryptoWallet();
					$destinationWallet										= new CompleteCryptoWallet();
					
					$sourceWalletResponseObject								= $sourceWallet -> instantiateWalletUsingCryptoWalletRecordID($accountID, $FK_SentWalletID, $userEncryptionKey, $dbh);
					
					$destinationWalletResponseObject						= $destinationWallet -> instantiateWalletUsingCryptoWalletRecordID($accountID, $FK_ReceivedWalletID, $userEncryptionKey, $dbh);
					
					$sendTransactionAmountInUSD								= 0;
					
					// process send part of transaction
					
					$isDebit												= 1;
					
					$baseCurrencyID											= $FK_SentCurrencyID;
					$baseCurrency											= $sentCurrency;
					$baseCurrencyAmount										= $sentQuantity;
					
					if (isset($cryptoCurrencyTypesImported[$baseCurrencyID][$quoteCurrencyID]))
					{
						$currentCount										= $cryptoCurrencyTypesImported[$baseCurrencyID][$quoteCurrencyID];
						$currentCount++;
						
						$cryptoCurrencyTypesImported[$baseCurrencyID][$quoteCurrencyID]	= $currentCount;
					}
					else
					{
						$cryptoCurrencyTypesImported[$baseCurrencyID][$quoteCurrencyID]	= 1;	
					}
					
					// create asset type status record for this data import event record ID
					createDataImportAssetStatusRecord($accountID, $userEncryptionKey, $dataImportEventRecordID, $baseCurrencyID, $quoteCurrencyID, $globalCurrentDate, $sid, $dbh);
					
					$FK_BaseCurrencyWalletID								= $FK_SentWalletID;
					$FK_QuoteCurrencyWalletID								= 0;
					
					$quoteCurrencyWallet									= new CompleteCryptoWallet();
					
					$quoteCurrencyWalletInstatiationResult					= $quoteCurrencyWallet -> instantiateWalletUsingTransactionSourceAndAssetTypeForUser($accountID, 2, $FK_SentTransactionSourceID, $userEncryptionKey, $dbh);
					
					if ($quoteCurrencyWalletInstatiationResult['instantiatedRecord'] == true)
					{
						$FK_QuoteCurrencyWalletID							= $quoteCurrencyWallet -> getWalletID();	
					}
					else
					{
						errorLog("could not instantiate wallet for $accountID, 2, $FK_SentTransactionSourceID");
						exit();
					}
					
					$baseToQuoteCurrencySpotPrice							= $receivedQuantity / $sentQuantity;
					$baseToUSDCurrencySpotPrice							 	= 0;
					
					$cascadeRetrieveSpotPriceResponseObject					= getSpotPriceForAssetPairUsingSourceCascade($baseCurrencyID, 2, $transactionTime, 14, "CoinGecko price by date", $dbh);
						
					if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
					{
						$baseToUSDCurrencySpotPrice							= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
					}
					else
					{
						errorLog("could not find USD Spot price for $baseCurrencyID at $transactionTime");
					}

					$effectiveTransactionTypeID								= 4;	
					$effectiveTransactionLabel								= "Sell";
					$displayTransactionTypeLabel							= "Sell";
					
					$transactionAmountInUSD									= $sentQuantity * $baseToUSDCurrencySpotPrice;
					$transactionAmountMinusFeeInUSD							= $transactionAmountInUSD;				
					$sendTransactionAmountInUSD								= $transactionAmountInUSD;

					$globalTransactionIdentificationRecordID				= 0;

					$nativeTransactionIDValue								= md5("$FK_SentTransactionSourceID $transactionSourceID $transactionTimestamp $effectiveTransactionTypeID $FK_ReceivedCurrencyID $FK_SentCurrencyID $receivedWalletType $sentWalletType $transactionAmountInUSD $sentQuantity $receivedCurrency".md5("$sentQuantity.$transactionTime.$accountID"));

					$profitStanceTransactionIDValue							= createProfitStanceTransactionIDValue($accountID, $baseCurrencyID, $transactionSourceID, $nativeTransactionIDValue, $globalCurrentDate, $sid);

					$globalTransactionCreationResults						= createGlobalTransactionIdentificationRecordWithProfitStanceTransactionIDValue($accountID, $baseCurrencyID, $dataImportEventRecordID, $nativeTransactionIDValue, $profitStanceTransactionIDValue, $transactionSourceID, $globalCurrentDate, $sid, $dbh);
					
					if ($globalTransactionCreationResults['createdGlobalTransactionIdentificationRecord'] == true)
					{
						$globalTransactionIdentificationRecordID			= $globalTransactionCreationResults['globalTransactionIdentificationRecordID'];
					}	
										
					$unspentTransactionTotal								= 0;
					$unfundedSpendTotal										= 0;
					
					if ($isDebit == 0)
					{
						$unspentTransactionTotal  							= $sentQuantity;
					}
					else if ($isDebit == 1)
					{
						$unfundedSpendTotal									= $sentQuantity;	
					}
					
					if ($sourceWalletResponseObject['instantiatedRecord'] == false)
					{
						errorLog("could not instantiate crypto wallet $accountID, $FK_SentWalletID, $userEncryptionKey");
						exit();
					}
					
					errorLog("INSERT INTO CoinTrackingLedgerTransaction
(
	transactionRecordID,
	FK_GlobalTransactionRecordID,
	FK_AccountID,
	FK_ProviderAccountWalletID,
	transactionTime,
	transactionTimestamp,
	FK_EffectiveTypeID,
	FK_TransactionTypeID,
	FK_AssetTypeID,
	amount,
	isDebit,
	baseToQuoteCurrencySpotPrice,
	baseToUSDCurrencySpotPrice,
	btcSpotPriceAtTimeOfTransaction,
	FK_BaseCurrencyWalletID,
	FK_QuoteCurrencyWalletID,
	receivedQuantity,
	sentQuantity,
	FK_TransactionSourceID,
	FK_ExchangeID,
	FK_ReceivedCurrencyID,
	receivedCurrencyAbbreviation,
	FK_SentCurrencyID,
	sentCurrencyAbbreviation,
	FK_ReceivedWalletID,
	FK_SentWalletID,
	receivedWalletType,
	receivedWallet,
	sentWalletType,
	sentWallet
)
VALUES
(
	$transactionRecordID,
	$globalTransactionIdentificationRecordID,
	$accountID,
	$FK_ProviderAccountWalletID,
	'$transactionTime',
	$transactionTimestamp,
	$effectiveTransactionTypeID,
	$transactionTypeID,
	$baseCurrencyID,
	$transactionAmountInUSD,
	$isDebit,
	$baseToQuoteCurrencySpotPrice,
	$baseToUSDCurrencySpotPrice,
	$btcSpotPriceAtTimeOfTransaction,
	$FK_BaseCurrencyWalletID,
	$FK_QuoteCurrencyWalletID,
	$transactionAmountInUSD,
	$sentQuantity,
	$FK_SentTransactionSourceID,
	$FK_ExchangeID,
	2,
	'USD',
	$FK_SentCurrencyID,
	'$sentCurrency',
	$FK_QuoteCurrencyWalletID,
	$FK_SentWalletID,
	'$receivedWalletType',
	'$receivedWallet',
	'$sentWalletType',
	'$sentWallet'
)");
					
					$insertCoinTrackingRecords -> bindValue(':transactionRecordID', $transactionRecordID);
					$insertCoinTrackingRecords -> bindValue(':FK_GlobalTransactionRecordID', $globalTransactionIdentificationRecordID);
					$insertCoinTrackingRecords -> bindValue(':FK_AccountID', $accountID);
					$insertCoinTrackingRecords -> bindValue(':FK_ProviderAccountWalletID', $FK_ProviderAccountWalletID);
					$insertCoinTrackingRecords -> bindValue(':transactionTime', $transactionTime);
					$insertCoinTrackingRecords -> bindValue(':transactionTimestamp', $transactionTimestamp);
					$insertCoinTrackingRecords -> bindValue(':FK_EffectiveTypeID', $effectiveTransactionTypeID);
					$insertCoinTrackingRecords -> bindValue(':FK_TransactionTypeID', $transactionTypeID);
					$insertCoinTrackingRecords -> bindValue(':FK_AssetTypeID', $baseCurrencyID);
					$insertCoinTrackingRecords -> bindValue(':amount', $transactionAmountInUSD);
					$insertCoinTrackingRecords -> bindValue(':isDebit', $isDebit);
					$insertCoinTrackingRecords -> bindValue(':baseToQuoteCurrencySpotPrice', $baseToQuoteCurrencySpotPrice);
					$insertCoinTrackingRecords -> bindValue(':baseToUSDCurrencySpotPrice', $baseToUSDCurrencySpotPrice);
					$insertCoinTrackingRecords -> bindValue(':btcSpotPriceAtTimeOfTransaction', $btcSpotPriceAtTimeOfTransaction);
					$insertCoinTrackingRecords -> bindValue(':FK_BaseCurrencyWalletID', $FK_BaseCurrencyWalletID);
					$insertCoinTrackingRecords -> bindValue(':FK_QuoteCurrencyWalletID', $FK_QuoteCurrencyWalletID);
					$insertCoinTrackingRecords -> bindValue(':receivedQuantity', $transactionAmountInUSD);
					$insertCoinTrackingRecords -> bindValue(':sentQuantity', $sentQuantity);
					$insertCoinTrackingRecords -> bindValue(':FK_TransactionSourceID', $FK_SentTransactionSourceID);
					$insertCoinTrackingRecords -> bindValue(':FK_ExchangeID', $FK_ExchangeID);
					$insertCoinTrackingRecords -> bindValue(':FK_ReceivedCurrencyID', 2);
					$insertCoinTrackingRecords -> bindValue(':receivedCurrencyAbbreviation', "USD");
					$insertCoinTrackingRecords -> bindValue(':FK_SentCurrencyID', $FK_SentCurrencyID);
					$insertCoinTrackingRecords -> bindValue(':sentCurrencyAbbreviation', $sentCurrency);
					$insertCoinTrackingRecords -> bindValue(':FK_ReceivedWalletID', $FK_QuoteCurrencyWalletID);
					$insertCoinTrackingRecords -> bindValue(':FK_SentWalletID', $FK_SentWalletID);
					$insertCoinTrackingRecords -> bindValue(':receivedWalletType', $receivedWalletType);
					$insertCoinTrackingRecords -> bindValue(':receivedWallet', $receivedWallet);
					$insertCoinTrackingRecords -> bindValue(':sentWalletType', $sentWalletType);
					$insertCoinTrackingRecords -> bindValue(':sentWallet', $sentWallet);

					$nativeRecordID											= 0;

					if ($insertCoinTrackingRecords -> execute())
					{
						errorLog("insert coin tracking record worked");
						
						$nativeRecordID 									= $dbh -> lastInsertId();
					}
					
					$cryptoTransaction										= new CryptoTransaction();
					
					$cryptoTransaction -> setData(0, $accountID, $authorID, $globalTransactionIdentificationRecordID, $transactionTypeID, $transactionTypeLabel, $transactionStatusID, $transactionStatusLabel, $transactionSourceID, $transactionSourceLabel, $baseCurrencyID, $baseCurrency, $quoteCurrencyID, $quoteCurrency, $FK_SentWalletID, $FK_QuoteCurrencyWalletID, $transactionTime, $transactionTime, $transactionTimestamp, $nativeRecordID, $nativeRecordID, $sentQuantity, $transactionAmountInUSD, $baseToUSDCurrencySpotPrice, $btcSpotPriceAtTimeOfTransaction, $transactionAmountInUSD, $feeAmountInBaseCurrency, $feeAmountInUSD, $unspentTransactionTotal, $providerNotes, $isDebit, $sid);
					
					$writeToDatabaseResponse								= $cryptoTransaction -> writeToDatabase($userEncryptionKey, $dbh);
						
					if ($writeToDatabaseResponse['wroteToDatabase'] == true)
					{
						$transactionID										= $cryptoTransaction -> getTransactionID();
					
						errorLog("transaction $transactionID created");
						
						$ledgerAmount										= $sentQuantity * -1;
						
						$profitStanceLedgerEntry							= new ProfitStanceLedgerEntry();
						
						$profitStanceLedgerEntry -> setData($accountID, $baseCurrencyID, $baseCurrency, $FK_SentTransactionSourceID, $sentWalletType, $exchangeTileID, $globalTransactionIdentificationRecordID, $transactionTime, $ledgerAmount, $dbh);
														
						$writeProfitStanceLedgerEntryRecordResponseObject	= $profitStanceLedgerEntry -> writeToDatabase($dbh);
														
						if ($writeProfitStanceLedgerEntryRecordResponseObject['wroteToDatabase'] == true)
						{
							errorLog("wrote profitStance ledger entry $accountID, $baseCurrencyID, $baseCurrency, $FK_SentTransactionSourceID, $sentWalletType, $globalTransactionIdentificationRecordID, $ledgerAmount to the database.", $GLOBALS['debugCoreFunctionality']);
						}
						else
						{
							errorLog("could not write profitStance ledger entry $accountID, $baseCurrencyID, $baseCurrency, $FK_SentTransactionSourceID, $sentWalletType, $globalTransactionIdentificationRecordID, $ledgerAmount to the database.", $GLOBALS['criticalErrors']);	
						}
					}
					
					// process receive part of transaction
					
					$isDebit												= 0;
					
					$baseCurrencyID											= $FK_ReceivedCurrencyID;
					$baseCurrency											= $receivedCurrency;
					$baseCurrencyAmount										= $receivedQuantity;
					
					if (isset($cryptoCurrencyTypesImported[$baseCurrencyID][$quoteCurrencyID]))
					{
						$currentCount										= $cryptoCurrencyTypesImported[$baseCurrencyID][$quoteCurrencyID];
						$currentCount++;
						
						$cryptoCurrencyTypesImported[$baseCurrencyID][$quoteCurrencyID]	= $currentCount;
					}
					else
					{
						$cryptoCurrencyTypesImported[$baseCurrencyID][$quoteCurrencyID]	= 1;	
					}
					
					// create asset type status record for this data import event record ID
					createDataImportAssetStatusRecord($accountID, $userEncryptionKey, $dataImportEventRecordID, $baseCurrencyID, $quoteCurrencyID, $globalCurrentDate, $sid, $dbh);
					
					$FK_BaseCurrencyWalletID								= $FK_ReceivedWalletID;
					$FK_QuoteCurrencyWalletID								= 0;
					
					$quoteCurrencyWallet									= new CompleteCryptoWallet();
					
					$quoteCurrencyWalletInstatiationResult					= $quoteCurrencyWallet -> instantiateWalletUsingTransactionSourceAndAssetTypeForUser($accountID, 2, $FK_ReceivedTransactionSourceID, $userEncryptionKey, $dbh);
					
					if ($quoteCurrencyWalletInstatiationResult['instantiatedRecord'] == true)
					{
						$FK_QuoteCurrencyWalletID							= $quoteCurrencyWallet -> getWalletID();	
					}
					else
					{
						errorLog("could not instantiate wallet for $accountID, 2, $FK_ReceivedTransactionSourceID");
						exit();
					}
					
					$baseToQuoteCurrencySpotPrice							= 0;
					$baseToUSDCurrencySpotPrice							 	= 0;
					
					if ($receivedQuantity == 0)
					{
						$cascadeRetrieveSpotPriceResponseObject				= getSpotPriceForAssetPairUsingSourceCascade($baseCurrencyID, 2, $transactionTime, 14, "CoinGecko price by date", $dbh);
						
						if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
						{
							$baseToUSDCurrencySpotPrice						= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
							
							if (!empty($transactionAmountInUSD) && $transactionAmountInUSD > 0)
							{
								errorLog("receivedQuantity = 0, $transactionAmountInUSD $sendTransactionAmountInUSD");
							}
							
							if (empty($baseToUSDCurrencySpotPrice) || $baseToUSDCurrencySpotPrice == 0)
							{
								errorLog($baseToUSDCurrencySpotPrice." equals zero for transaction $transactionTimestamp $transactionAmountInUSD");
								exit();
							}

							$receivedQuantity								= $transactionAmountInUSD / $baseToUSDCurrencySpotPrice;
							
							errorLog("receivedQuantity = $receivedQuantity");
							
							$baseToQuoteCurrencySpotPrice					= $sentQuantity / $receivedQuantity;
						}
						else
						{
							errorLog("could not find USD Spot price for $baseCurrencyID at $transactionTime");
							exit();
						}		
					}
					else
					{
						$baseToQuoteCurrencySpotPrice						= $sentQuantity / $receivedQuantity;
						$baseToUSDCurrencySpotPrice							= $sendTransactionAmountInUSD / $receivedQuantity;	
					}
					
					errorLog("$sentQuantity $receivedQuantity $sendTransactionAmountInUSD $baseToUSDCurrencySpotPrice $baseToQuoteCurrencySpotPrice");	
					
					$effectiveTransactionTypeID								= 1;	
					$effectiveTransactionLabel								= "Buy";
					$displayTransactionTypeLabel							= "Buy";
					
					$transactionAmountInUSD									= $receivedQuantity * $baseToUSDCurrencySpotPrice;
					$transactionAmountMinusFeeInUSD							= $sendTransactionAmountInUSD;				

					$globalTransactionIdentificationRecordID				= 0;
					
					$nativeTransactionIDValue								= md5("$FK_ReceivedTransactionSourceID $transactionSourceID $transactionTimestamp $effectiveTransactionTypeID $FK_ReceivedCurrencyID $FK_SentCurrencyID $receivedWalletType $sentWalletType $transactionAmountInUSD $sentQuantity $receivedCurrency".md5("$receivedQuantity.$transactionTime.$accountID"));

					$profitStanceTransactionIDValue							= createProfitStanceTransactionIDValue($accountID, $baseCurrencyID, $transactionSourceID, $nativeTransactionIDValue, $globalCurrentDate, $sid);

					$globalTransactionCreationResults						= createGlobalTransactionIdentificationRecordWithProfitStanceTransactionIDValue($accountID, $baseCurrencyID, $dataImportEventRecordID, $nativeTransactionIDValue, $profitStanceTransactionIDValue, $transactionSourceID, $globalCurrentDate, $sid, $dbh);
					
					if ($globalTransactionCreationResults['createdGlobalTransactionIdentificationRecord'] == true)
					{
						$globalTransactionIdentificationRecordID			= $globalTransactionCreationResults['globalTransactionIdentificationRecordID'];
					}	
										
					$unspentTransactionTotal								= 0;
					$unfundedSpendTotal										= 0;
					
					if ($isDebit == 0)
					{
						$unspentTransactionTotal  							= $receivedQuantity;
					}
					else if ($isDebit == 1)
					{
						$unfundedSpendTotal									= $receivedQuantity;	
					}
					
					if ($destinationWalletResponseObject['instantiatedRecord'] == false)
					{
						errorLog("could not instantiate crypto wallet $accountID, $FK_ReceivedWalletID, $userEncryptionKey");
						exit();
					}
					
					errorLog("INSERT INTO CoinTrackingLedgerTransaction
(
	transactionRecordID,
	FK_GlobalTransactionRecordID,
	FK_AccountID,
	FK_ProviderAccountWalletID,
	transactionTime,
	transactionTimestamp,
	FK_EffectiveTypeID,
	FK_TransactionTypeID,
	FK_AssetTypeID,
	amount,
	isDebit,
	baseToQuoteCurrencySpotPrice,
	baseToUSDCurrencySpotPrice,
	btcSpotPriceAtTimeOfTransaction,
	FK_BaseCurrencyWalletID,
	FK_QuoteCurrencyWalletID,
	receivedQuantity,
	sentQuantity,
	FK_TransactionSourceID,
	FK_ExchangeID,
	FK_ReceivedCurrencyID,
	receivedCurrencyAbbreviation,
	FK_SentCurrencyID,
	sentCurrencyAbbreviation,
	FK_ReceivedWalletID,
	FK_SentWalletID,
	receivedWalletType,
	receivedWallet,
	sentWalletType,
	sentWallet
)
VALUES
(
	$transactionRecordID,
	$globalTransactionIdentificationRecordID,
	$accountID,
	$FK_ProviderAccountWalletID,
	'$transactionTime',
	$transactionTimestamp,
	$effectiveTransactionTypeID,
	$transactionTypeID,
	$baseCurrencyID,
	$transactionAmountInUSD,
	$isDebit,
	$baseToQuoteCurrencySpotPrice,
	$baseToUSDCurrencySpotPrice,
	$btcSpotPriceAtTimeOfTransaction,
	$FK_BaseCurrencyWalletID,
	$FK_QuoteCurrencyWalletID,
	$receivedQuantity,
	$transactionAmountInUSD,
	$FK_ReceivedTransactionSourceID,
	$FK_ExchangeID,
	$FK_ReceivedCurrencyID,
	'$receivedCurrency',
	2,
	'USD',
	$FK_ReceivedWalletID,
	$FK_QuoteCurrencyWalletID,
	'$receivedWalletType',
	'$receivedWallet',
	'$sentWalletType',
	'$sentWallet'
)");
					
					$insertCoinTrackingRecords -> bindValue(':transactionRecordID', $transactionRecordID);
					$insertCoinTrackingRecords -> bindValue(':FK_GlobalTransactionRecordID', $globalTransactionIdentificationRecordID);
					$insertCoinTrackingRecords -> bindValue(':FK_AccountID', $accountID);
					$insertCoinTrackingRecords -> bindValue(':FK_ProviderAccountWalletID', $FK_ProviderAccountWalletID);
					$insertCoinTrackingRecords -> bindValue(':transactionTime', $transactionTime);
					$insertCoinTrackingRecords -> bindValue(':transactionTimestamp', $transactionTimestamp);
					$insertCoinTrackingRecords -> bindValue(':FK_EffectiveTypeID', $effectiveTransactionTypeID);
					$insertCoinTrackingRecords -> bindValue(':FK_TransactionTypeID', $transactionTypeID);
					$insertCoinTrackingRecords -> bindValue(':FK_AssetTypeID', $baseCurrencyID);
					$insertCoinTrackingRecords -> bindValue(':amount', $transactionAmountInUSD);
					$insertCoinTrackingRecords -> bindValue(':isDebit', $isDebit);
					$insertCoinTrackingRecords -> bindValue(':baseToQuoteCurrencySpotPrice', $baseToQuoteCurrencySpotPrice);
					$insertCoinTrackingRecords -> bindValue(':baseToUSDCurrencySpotPrice', $baseToUSDCurrencySpotPrice);
					$insertCoinTrackingRecords -> bindValue(':btcSpotPriceAtTimeOfTransaction', $btcSpotPriceAtTimeOfTransaction);
					$insertCoinTrackingRecords -> bindValue(':FK_BaseCurrencyWalletID', $FK_BaseCurrencyWalletID);
					$insertCoinTrackingRecords -> bindValue(':FK_QuoteCurrencyWalletID', $FK_QuoteCurrencyWalletID);
					$insertCoinTrackingRecords -> bindValue(':receivedQuantity', $receivedQuantity);
					$insertCoinTrackingRecords -> bindValue(':sentQuantity', $transactionAmountInUSD);
					$insertCoinTrackingRecords -> bindValue(':FK_TransactionSourceID', $FK_ReceivedTransactionSourceID);
					$insertCoinTrackingRecords -> bindValue(':FK_ExchangeID', $FK_ExchangeID);
					$insertCoinTrackingRecords -> bindValue(':FK_ReceivedCurrencyID', $FK_ReceivedCurrencyID);
					$insertCoinTrackingRecords -> bindValue(':receivedCurrencyAbbreviation', $receivedCurrency);
					$insertCoinTrackingRecords -> bindValue(':FK_SentCurrencyID', 2);
					$insertCoinTrackingRecords -> bindValue(':sentCurrencyAbbreviation', "USD");
					$insertCoinTrackingRecords -> bindValue(':FK_ReceivedWalletID', $FK_ReceivedWalletID);
					$insertCoinTrackingRecords -> bindValue(':FK_SentWalletID', $FK_QuoteCurrencyWalletID);
					$insertCoinTrackingRecords -> bindValue(':receivedWalletType', $receivedWalletType);
					$insertCoinTrackingRecords -> bindValue(':receivedWallet', $receivedWallet);
					$insertCoinTrackingRecords -> bindValue(':sentWalletType', $sentWalletType);
					$insertCoinTrackingRecords -> bindValue(':sentWallet', $sentWallet);

					$nativeRecordID											= 0;

					if ($insertCoinTrackingRecords -> execute())
					{
						errorLog("insert coin tracking record worked");
						
						$nativeRecordID 									= $dbh -> lastInsertId();
					}
					
					$cryptoTransaction										= new CryptoTransaction();
					
					$cryptoTransaction -> setData(0, $accountID, $authorID, $globalTransactionIdentificationRecordID, $transactionTypeID, $transactionTypeLabel, $transactionStatusID, $transactionStatusLabel, $transactionSourceID, $transactionSourceLabel, $baseCurrencyID, $baseCurrency, $quoteCurrencyID, $quoteCurrency, $FK_ReceivedWalletID, $FK_QuoteCurrencyWalletID, $transactionTime, $transactionTime, $transactionTimestamp, $nativeRecordID, $nativeRecordID, $receivedQuantity, $transactionAmountInUSD, $baseToUSDCurrencySpotPrice, $btcSpotPriceAtTimeOfTransaction, $transactionAmountInUSD, $feeAmountInBaseCurrency, $feeAmountInUSD, $unspentTransactionTotal, $providerNotes, $isDebit, $sid);
					
					$writeToDatabaseResponse								= $cryptoTransaction -> writeToDatabase($userEncryptionKey, $dbh);
						
					if ($writeToDatabaseResponse['wroteToDatabase'] == true)
					{
						$transactionID										= $cryptoTransaction -> getTransactionID();
					
						errorLog("transaction $transactionID created");
						
						
						$profitStanceLedgerEntry							= new ProfitStanceLedgerEntry();
						
						$profitStanceLedgerEntry -> setData($accountID, $baseCurrencyID, $baseCurrency, $FK_ReceivedTransactionSourceID, $receivedWalletType, $exchangeTileID, $globalTransactionIdentificationRecordID, $transactionTime, $receivedQuantity, $dbh);
														
						$writeProfitStanceLedgerEntryRecordResponseObject	= $profitStanceLedgerEntry -> writeToDatabase($dbh);
														
						if ($writeProfitStanceLedgerEntryRecordResponseObject['wroteToDatabase'] == true)
						{
							errorLog("wrote profitStance ledger entry $accountID, $baseCurrencyID, $baseCurrency, $FK_ReceivedTransactionSourceID, $receivedWalletType, $globalTransactionIdentificationRecordID, $receivedQuantity to the database.", $GLOBALS['debugCoreFunctionality']);
						}
						else
						{
							errorLog("could not write profitStance ledger entry $accountID, $baseCurrencyID, $baseCurrency, $FK_ReceivedTransactionSourceID, $receivedWalletType, $globalTransactionIdentificationRecordID, $receivedQuantity to the database.", $GLOBALS['criticalErrors']);	
						}
					}
				}
			}
			
			$responseObject['importedTransactions']							= true;
		}
		catch (PDOException $e) 
		{
			$cryptoTransaction 												= null;	
			$responseObject['importedTransactions']							= false;
			
			errorLog($e -> getMessage());
		
			die();
		}

        return $cryptoCurrencyTypesImported;
    }
	
	// END CoinTracking functions
	
	// KRAKEN functions
	
	// Kraken fee conversion
	function calculateFeeInBaseCurrencyForFeePaidInQuoteCurrency($feeInQuoteCurrency, $costInQuoteCurrencyNotIncludingFees, $priceInQuoteCurrency, $volumeInBaseCurrency)
	{
		$feePercentageOfCost					= $feeInQuoteCurrency / ($priceInQuoteCurrency * $volumeInBaseCurrency);
		
		// verify that cost in quote currency matches price * volume
		
		if ($costInQuoteCurrencyNotIncludingFees != ($priceInQuoteCurrency * $volumeInBaseCurrency))
		{
			errorLog("calculateFeeInBaseCurrencyForFeePaidInQuoteCurrency error: cost value includes fees ");
		}
		
		$feeInBaseCurrency						= $volumeInBaseCurrency * $feePercentageOfCost;
		
		return $feeInBaseCurrency;
	}
	
	function getKrakenAPIConstants()
	{
		$responseObject														= array();
		
		$beta 																= false; 
		
		$responseObject['beta']												= $beta;
		$responseObject['url']												= $beta ? 'https://api.beta.kraken.com' : 'https://api.kraken.com';
		$responseObject['sslverify']										= $beta ? false : true;
		$responseObject['version'] 											= 0;
		$responseObject['transactionSourceID']								= 6;
		
		return $responseObject;	
	}
	
	function getKrakenTransactionHistoryForUserViaAPI($resultArray, $startPosition, $cryptoCurrencyTypesImported, $name, $accountID, $userEncryptionKey, $transactionSourceID, $exchangeTileID, $walletTypeID, $walletTypeName, $includeDetailReporting, $dataImportEventRecordID, $globalCurrentDate, $sid, $dbh)
	{
    	$returnValue   														= array();
    	
	    $transactionSourceName												= getTransactionSourceTypeLabelFromEnumValue($transactionSourceID, $dbh);
		
		$switchNonDebitPairs												= 0;
		
		$tradesArray														= $resultArray['result']['trades'];
    	$numberProcessed													= 0;
   
	    try
	    {
		    errorLog("count ".count($tradesArray));
			    
		    foreach ($tradesArray as $txIDValue => $genericTransationObject)
		    {
			    errorLog($txIDValue);
			    
			    $transactionType											= $genericTransationObject['type'];
			    $timestamp													= $genericTransationObject['time'];
			    $misc														= $genericTransationObject['misc'];
				$spotPriceInQuoteCurrency									= $genericTransationObject['price'];
				$transactionVolumeInBaseCurrency							= $genericTransationObject['vol'];
			    $transactionFeeInQuoteCurrency								= $genericTransationObject['fee'];
			    $transactionCostInQuoteCurrencyNotIncludingFees				= $genericTransationObject['cost'];
			    $pairName													= $genericTransationObject['pair'];
			    $orderTxID													= $genericTransationObject['ordertxid'];
			    $krakenOrderTypeLabel										= $genericTransationObject['ordertype'];
			    $margin														= $genericTransationObject['margin'];
			    
			    $effectivePairName											= $pairName;
			    
			    $ledgers													= "";
			    				    
				$posTxIDValue												= "";
				$posTxID													= 0; 
			    $posStatus													= "";
			    $posStatusID												= 0;
			    $cPrice														= 0.0;
			    $cCost														= 0.0;
			    $cFee														= 0.0;
			    $cVol														= 0.0;
			    $cMargin													= 0.0;
			    $net														= 0.0;
			    $closingTradesArray											= array();
			    
			    if (!empty($margin) && $margin > 0)
			    {
					// the following fields are only present in a transaction which opens a position
					// the trades array includes the IDs of the transactions that CLOSE the position
					// I have to make sure I do not include the gain calculations twice
						// typically, the gain/loss value is calculated using amount realized - cost basis.
						// I assume that the net value would be used to adjust the resulting gain/loss value.
						// IF there are more values than one for the array of transactions that close the position, I belive that there are two options
							// 1) use the net value as the gain/loss for the originating transaction for the position, and calculate the individual gain/loss positions for each of the transactions that close the position like I normally would.  The net change to the tax liability would be the same.
							// 2) calculate the individual gain/loss for each transaction that closed the position, but use the price, cost, etc of the transactions involved in closing the position to determine their gain/loss and then take their percentage of the total opening transction amount as the percentage of the net to apply to an adjusted gain/loss for each the transaction
							
							// concerns:
							// 1) how do I calculate a margin or short with component transactions that has not been closed?
							// 2) I would need to add a field that indicates that the gain/loss is adjusted, and have a reference to indicate which transaction initiated the adjustement.  I would probabaly want a TYPE value for the adjustment, such as gift, linked, margin, short, etc.  I should do this anyway.
							// 3) I need to update the information related to the changes in wallet type IDs for IPTransaction table entries, and to the GTID table
							// 4) make sure data pulls still work properly
							// 5) add another table with import types - CSV, API, or Integration Partner API, and set that for each import currently used
					
					if (array_key_exists("postxid", $genericTransationObject) == true)
					{
						$posTxIDValue										= $genericTransationObject['postxid'];	
					}
					
					if (array_key_exists("posstatus", $genericTransationObject) == true)
					{
						$posStatus											= $genericTransationObject['posstatus'];
						$posStatusID										= getEnumValuePositionStatus($posStatus, $dbh);
					}
					
					if (array_key_exists("cprice", $genericTransationObject) == true)
					{
						$cPrice												= $genericTransationObject['cprice'];
					}
							
					if (array_key_exists("ccost", $genericTransationObject) == true)
					{
						$cCost												= $genericTransationObject['ccost'];
					}
					
					if (array_key_exists("cfee", $genericTransationObject) == true)
					{
						$cFee												= $genericTransationObject['cfee'];
					}
					
					if (array_key_exists("cvol", $genericTransationObject) == true)
					{
						$cVol												= $genericTransationObject['cvol'];
					}
					
					if (array_key_exists("cmargin", $genericTransationObject) == true)
					{
						$cMargin											= $genericTransationObject['cmargin'];
					}
					
					if (array_key_exists("net", $genericTransationObject) == true)
					{
						$net												= $genericTransationObject['net'];
					}
					
					if (array_key_exists("trades", $genericTransationObject) == true)
					{
						// check to see if a transaction with that ID already exists - if so, set the transation ID
					
						// for each transation in the array, get the transactionID, write it to the assocation table
						
						// this initial array contains only the transaction ID values for the closing transactions.  I need to convert this array into one formatted using the transaction ID and processed date, which may initially both be empty (0 and null)
						$closingTradesRawArray								= $genericTransationObject['trades'];
						
						if (!empty($closingTradesRawArray))
						{
							foreach ($closingTradesRawArray AS $closingTradeIDValue)
							{
								// check for transaction ID for this transaction ID value
								
								$krakenClosingTradeObject					= new KrakenTradeTransaction();
								$getKrakenCloseObjectIDResponseObject		= $krakenClosingTradeObject -> instantiateKrakenTradeTransactionUsingTradeTransactionIDValue($accountID, $closingTradeIDValue, $userEncryptionKey, $dbh);
								
								$closingTradeID								= 0;
								
								if ($getKrakenCloseObjectIDResponseObject['instantiatedKrakenTradeTransactionObject'] == true)
								{
									$closingTradeID							= $krakenClosingTradeObject -> getKrakenTransactionRecordID();	
								}
								
								$closingTradesArray[$closingTradeIDValue]['closingTradeTransactionID']		= $closingTradeID;
								$closingTradesArray[$closingTradeIDValue]['processedDate']					= null;	
							}	
						}
					}
			    }
			    
			    $transactionTimestampObject									= new DateTime();
				$transactionTimestampObject -> setTimestamp($timestamp);	
							
				$transactionTimestamp										= date_format($transactionTimestampObject, "Y-m-d h:i:s");
				
				errorLog("getKrakenTransactionHistoryForUserViaAPI $txIDValue $transactionType $transactionTimestamp $timestamp $misc $spotPriceInQuoteCurrency $transactionVolumeInBaseCurrency $transactionFeeInQuoteCurrency $transactionCostInQuoteCurrencyNotIncludingFees $pairName $orderTxID $krakenOrderTypeLabel $margin", $GLOBALS['debugCoreFunctionality']);
				
			    // @Task - write code to convert currencies between USD and BTC and the current type - use amount objects
				    
			    $baseCurrency												= new AssetInfo();
			    $quoteCurrency												= new AssetInfo();
			   
			    $krakenCurrencyPair											= new CommonCurrencyPair();
			    
			    $responseObject												= $krakenCurrencyPair -> instantiateCommonCurrencyPairUsingPairName($pairName, $dbh);
			    
			    if ($responseObject['foundCommonCurrencyPairID'] == true && $responseObject['instantiatedCommonCurrencyPairObject'] == true)
			    {
					$baseCurrency											= $krakenCurrencyPair -> getBaseCurrency();
					$quoteCurrency											= $krakenCurrencyPair -> getQuoteCurrency();  
					
					if ($switchNonDebitPairs == 1 && strcasecmp("buy", strtolower($transactionType)) == 0)
					{
						$quoteCurrency										= $krakenCurrencyPair -> getBaseCurrency();
						$baseCurrency										= $krakenCurrencyPair -> getQuoteCurrency();	
						
						$responseObject										= $krakenCurrencyPair -> instantiateCommonCurrencyPairUsingAssetIDs($baseCurrency -> getAssetTypeID(), $quoteCurrency -> getAssetTypeID(), $dbh);
						
						if ($responseObject['foundCommonCurrencyPairID'] == true && $responseObject['instantiatedCommonCurrencyPairObject'] == true)
						{
							$effectivePairName								= $krakenCurrencyPair -> getPairName();
						}	
					}  
			    }
			    else
			    {
				    errorLog("Unrecognized Kraken Currency Pair: $pairName");
				    exit();
			    }
			    
			    // not provided
			    
				$baseCurrencyAssetTypeID									= $baseCurrency -> getAssetTypeID();
			    $quoteCurrencyAssetTypeID									= $quoteCurrency -> getAssetTypeID();
			    
			    $baseCurrencyAssetType										= $baseCurrency -> getAssetTypeLabel();
			    $quoteCurrencyAssetType										= $quoteCurrency -> getAssetTypeLabel();
			    
			    // Kraken - every time I read a spot price, write it to the daily spot price table
			    setDailyPriceData($baseCurrencyAssetTypeID, $quoteCurrencyAssetTypeID, $transactionTimestamp, $spotPriceInQuoteCurrency, $transactionSourceID, $globalCurrentDate, $sid, $dbh);
			    
			    // @Task - it may be better to compact the array rather than have index gaps - write a function that checks to see if a value already exists in array, and if not, adds it
			    
			    if (isset($cryptoCurrencyTypesImported[$baseCurrencyAssetTypeID][$quoteCurrencyAssetTypeID]))
				{
					$currentCount											= $cryptoCurrencyTypesImported[$baseCurrencyAssetTypeID][$quoteCurrencyAssetTypeID];
					$currentCount++;
					
					$cryptoCurrencyTypesImported[$baseCurrencyAssetTypeID][$quoteCurrencyAssetTypeID]			= $currentCount;
				}
				else
				{
					$cryptoCurrencyTypesImported[$baseCurrencyAssetTypeID][$quoteCurrencyAssetTypeID]			= 1;	
				}
				
				createDataImportAssetStatusRecord($accountID, $userEncryptionKey, $dataImportEventRecordID, $baseCurrencyAssetTypeID, $quoteCurrencyAssetTypeID, $globalCurrentDate, $sid, $dbh);
			    
			    $baseCurrencyLedgerIDValue										 							= "";
			    $quoteCurrencyLedgerIDValue										 							= "";

				// kraken API data pull does not include ledger data			    
				
				//	$ledgerValues												= splitCommaSeparatedString($ledgers);
				    
				//	if (count($ledgerValues) == 2)
				//	{
				//		$baseCurrencyLedgerIDValue								= trim($ledgerValues[0]);
				//		$quoteCurrencyLedgerIDValue								= trim($ledgerValues[1]);
				//	}
				
					
				$transactionTypeID											= getEnumValueTransactionType($transactionType, $dbh);
				$krakenOrderTypeID											= getEnumValueKrakenOrderType($krakenOrderTypeLabel, $dbh);
				
				$baseCurrencyWalletID										= 0;
				$quoteCurrencyWalletID										= 0;
			
				// check for global transaction ID
				
				errorLog("getGlobalTransactionIdentificationRecordID for $accountID, $baseCurrencyAssetTypeID, $txIDValue, $transactionSourceID, $globalCurrentDate, $sid", $GLOBALS['debugCoreFunctionality']);
				
			    $globalTransactionIDTestResults								= getGlobalTransactionIdentificationRecordID($accountID, $baseCurrencyAssetTypeID, $txIDValue, $transactionSourceID, $globalCurrentDate, $sid, $dbh);
			    
			    // here
			    
			    if ($globalTransactionIDTestResults['foundNativeTransactionForAccount'] == false)
				{
					errorLog("not found $txIDValue", $GLOBALS['debugCoreFunctionality']);
					
					$returnValue[$txIDValue]["existingRecordFound"]			= false;
					
					// create one if not found
					$globalTransactionCreationResults						= createGlobalTransactionIdentificationRecord($accountID, $exchangeTileID, $baseCurrencyAssetTypeID, $txIDValue, $transactionSourceID, $globalCurrentDate, $sid, $dbh);
			
					if ($globalTransactionCreationResults['createdGlobalTransactionIdentificationRecord'] == true)
					{
						$returnValue[$txIDValue]["createdGTIR"]				= true;
							
						$globalTransactionIdentifierRecordID				= $globalTransactionCreationResults['globalTransactionIdentificationRecordID'];
						$profitStanceTransactionIDValue						= $globalTransactionCreationResults['profitStanceTransactionIDValue'];
							
						// @Task - this is where I need to use the new provider account wallet idea of 
							
						$providerAccountWallet								= new ProviderAccountWallet();
							
						// check for provider account wallet							
						$instantiationResult								= $providerAccountWallet -> instantiateAccountWalletObjectForAccountByAssetTypeIDAndTransactionSourceID($accountID, $baseCurrencyAssetTypeID, $transactionSourceID, $dbh);
							
						if ($instantiationResult['instantiatedWallet'] == false)
						{
							// create wallet if not found
							$providerAccountWallet -> createAccountWalletObject($accountID, $baseCurrencyAssetTypeID, $baseCurrencyAssetType, $accountID, "$accountID-$transactionSourceID-$baseCurrencyAssetType", $walletTypeID, "Private Ledger Based Wallet", $transactionSourceID, $transactionSourceName, $globalCurrentDate, $sid, $dbh);
						}
							
						$providerWalletID									= $providerAccountWallet -> getAccountWalletID();
							
						if ($providerWalletID > 0)
						{
							// create kraken trade transaction object and write it
							
							$krakenTradeTransaction 						= new KrakenTradeTransaction();
							
							$krakenTradeTransaction -> setData($accountID, 0, $exchangeTileID, $globalTransactionIdentifierRecordID, $transactionSourceID, $providerWalletID, 0, 0, $txIDValue, $orderTxID, $krakenCurrencyPair, $krakenCurrencyPair -> getPairID(), $pairName, $effectivePairName, $transactionTimestamp, $transactionTypeID, $transactionType, $krakenOrderTypeID, $krakenOrderTypeLabel, $spotPriceInQuoteCurrency, $transactionCostInQuoteCurrencyNotIncludingFees, $transactionFeeInQuoteCurrency, $transactionVolumeInBaseCurrency, $margin, $misc, $baseCurrencyLedgerIDValue, $quoteCurrencyLedgerIDValue, $posTxIDValue, $posTxID, $posStatus, $posStatusID, $cPrice, $cCost, $cFee, $cVol, $cMargin, $net, $walletTypeID, $walletTypeName, $userEncryptionKey, $globalCurrentDate, $sid, $dbh);
							
								
							$writeKrakenRecordResponseObject				= $krakenTradeTransaction -> writeToDatabase($userEncryptionKey, $dbh);
							
							if ($writeKrakenRecordResponseObject['wroteToDatabase'] == true)
							{
								// now that the transaction has been created, create the association record for the closing array if size > 0
								
								// update native record ID in GTRID table
								$setNativeTransactionRecordIDResult			= setNativeTransactionRecordIDForGlobalTransactionIndentificationRecordID($accountID, $krakenTradeTransaction -> getKrakenTransactionRecordID(), $globalTransactionIdentifierRecordID, $globalCurrentDate, $sid, $dbh);
								
								if (count($closingTradesArray) > 0)
								{
									$positionTransactionID					= $krakenTradeTransaction -> getKrakenTransactionRecordID();
									
									foreach($closingTradesArray AS $closingTradeTransactionIDValue => $tradeDetailsArray)
									{
										$closingTradeTransactionID			= 0;
										
										if (array_key_exists("closingTradeTransactionID", $tradeDetailsArray) == true)
										{
											$closingTradeTransactionID		= $tradeDetailsArray['closingTradeTransactionID'];		
										}
										
										$writeKrakenAssociatePositionTransactionsWithClosingResponse			= writeKrakenAssociatePositionTransactionsWithClosingTransactionsRecord($accountID, $positionTransactionID, $closingTradeTransactionIDValue, $closingTradeTransactionID, $userEncryptionKey, $globalCurrentDate, $sid, $dbh);		
									}
								}
							}
						}
					}
					
					$numberProcessed++;	
				}
				else
				{
					errorLog("found $txIDValue", $GLOBALS['debugCoreFunctionality']);
					
					$returnValue[$txIDValue]["existingRecordFound"]			= true;
					$returnValue[$txIDValue]["newTransactionCreated"]		= false;
				}

				errorLog("completed array index $txIDValue");
		    }
		
			if ($includeDetailReporting != true)
			{
				$returnValue												= array();		
			}
			
			$returnValue["krakenDataImported"]								= "complete";
			$returnValue['numberProcessed']									= $numberProcessed;
			$returnValue['startPosition']									= $startPosition;
			$returnValue['cryptoCurrencyTypesImported']						= $cryptoCurrencyTypesImported;
	    }
	    catch (Exception $e)
	    {
		   	errorLog("ERROR: array parsing error");	
	    }
	    
	    errorLog(json_encode($returnValue));

        return $returnValue;
    }

	function getKrakenTransactionLedgerForUserViaAPI($resultArray, $startPosition, $cryptoCurrencyTypesImported, $name, $accountID, $userEncryptionKey, $transactionSourceID, $exchangeTileID, $walletTypeID, $walletTypeName, $includeDetailReporting, $dataImportEventRecordID, $globalCurrentDate, $sid, $dbh)
	{
    	errorLog("ledger import ".count($resultArray).", $startPosition, $cryptoCurrencyTypesImported, $name, $accountID, $userEncryptionKey, $transactionSourceID, $exchangeTileID, $walletTypeID, $walletTypeName, $includeDetailReporting, $dataImportEventRecordID, $globalCurrentDate, $sid");
    		
    	$returnValue   														= array();
    	
		$transactionSourceName												= getTransactionSourceTypeLabelFromEnumValue($transactionSourceID, $dbh);
		
		$switchNonDebitPairs												= 0;
		
		$ledgerArray														= $resultArray['result'][$name];
    	
    	$numberProcessed													= 0;
   
		try
	    {
		   	errorLog("count ".count($ledgerArray));
			    
			foreach ($ledgerArray as $ledgerEntryIDValue => $genericLedgerObject)
		    {
			    errorLog($ledgerEntryIDValue);
			    
				// print_r($genericLedgerObject);
			    
			    $refid														= $genericLedgerObject['refid']; // equal to $txIDValue from trade API
			    $timestamp													= $genericLedgerObject['time'];
			    $ledgerEntryType											= $genericLedgerObject['type'];
			    $assetClass													= $genericLedgerObject['aclass'];
			    $asset														= $genericLedgerObject['asset'];
			    $amount														= $genericLedgerObject['amount'];
			    $fee														= $genericLedgerObject['fee'];
			    $balance																						= $genericLedgerObject['balance'];
			    
				$baseToQuoteCurrencySpotPrice								= 0; // If this ledger object represents a transaction, this will be the price field from the transaction.  If not, this will be the spot price as provided from the database for this asset pair and date and time value.  Note that if the quote currency is NOT USD, and there is no spot price avaiable for this currency pair, then the spot price will be calculated using the USD spot price of the base currency divided by the USD spot price of the quote currency 
				$baseToUSDCurrencySpotPrice									= 0; // If this ledger object represents a transaction, and the quote currency is USD, this will be the price field from the transaction.  Otherwise, this will be the spot price as provided from the database for this asset and USD for this date and time value. 
				$currencyPriceValueSource									= ""; // If $baseToUSDCurrencySpotPrice is based on the spot price from the transaction, this value is "Kraken", otherwise, it is based on the source of the spot price used.  If $baseToQuoteCurrencySpotPrice is calculated, the value is "Currency Pair calculated values"
				$currencyPriceValueSourceID									= 0; // If $baseToUSDCurrencySpotPrice is based on the spot price from the transaction, this value is 6, otherwise, it is based on the source of the spot price used.  If $baseToQuoteCurrencySpotPrice is calculated, the value is 9
			    
			    $btcSpotPriceAtTimeOfTransaction							= 0;
			    
			    $transactionTimestampObject									= new DateTime();
				$transactionTimestampObject -> setTimestamp($timestamp);
				
				$transactionTimestamp										= date_format($transactionTimestampObject, "Y-m-d h:i:s");
				
				errorLog("getKrakenTransactionLedgerForUserViaAPI $ledgerEntryIDValue $refid $transactionTimestamp $timestamp $ledgerEntryType $assetClass $asset $amount $fee $balance", $GLOBALS['debugCoreFunctionality']);
				
				$ledgerEntryTypeID											= getEnumValueCommonLedgerEntryType($ledgerEntryType, $dbh);
				$assetClassTypeID											= getEnumValueKrakenAssetClassID($assetClass, $dbh);
				
				$transactionTypeID											= 0;
				$transactionTypeLabel										= "";
				
				$transactionRecordID										= 0;
				
				$assetTypeID												= 0;
				
				$assetInfoObject											= new AssetInfo();   
			    $assetTypeID												= getEnumValueKrakenAssetType($asset, $dbh);
			   
				if (empty($assetTypeID))
				{
					errorLog("Unable to get asset type for asset $asset");
					exit();	
				}
				else
				{
					$instantiateAssetResponseObject							= $assetInfoObject -> instantiateAssetInfoUsingAssetID($assetTypeID, $dbh);
					
					if ($instantiateAssetResponseObject['instantiatedAssetByID'] == false)
					{
						errorLog("Unable to instantiate asset: $asset assetTypeID: $assetTypeID");
						exit();
					}			
				}

				$isDebit													= -1;
				
				if ($ledgerEntryTypeID == 1 || ($ledgerEntryTypeID > 2 && $amount >= 0))
				{
					$isDebit												= 0;	
				}
				else if ($ledgerEntryTypeID == 2 || ($ledgerEntryTypeID > 2 && $amount < 0))
				{
					$isDebit												= 1;	
				}
				
				$baseCurrencyAssetType										= "";
				$baseCurrencyAssetTypeID									= 0;
						
				$quoteCurrencyAssetType										= "";
				$quoteCurrencyAssetTypeID									= 0;
				
				$pairName													= "";
				
				$assetPair													= null;
				
				if ($ledgerEntryTypeID > 2)
				{
					$krakenTradeTransaction									= new KrakenTradeTransaction();
					
					$tradeInstantiationResponse								= $krakenTradeTransaction -> instantiateKrakenTradeTransactionUsingTradeTransactionIDValue($accountID, $refid, $userEncryptionKey, $dbh);
					
					if ($tradeInstantiationResponse['instantiatedKrakenTradeTransactionObject'] == true)
					{
						$transactionRecordID								= $krakenTradeTransaction -> getKrakenTransactionRecordID();
						
						$transactionTypeID									= $krakenTradeTransaction -> getTransactionTypeID();
						$transactionTypeLabel								= $krakenTradeTransaction -> getTransactionTypeLabel();
						
						$assetPair											= $krakenTradeTransaction -> getKrakenCurrencyPair();
						
						$pairName											= $assetPair -> getPairName();
						
						$baseCurrencyAssetType								= $asset;
						$baseCurrencyAssetTypeID							= $assetTypeID;
						
						// there are two transactions for trades and settled, and, based on which transaction in the pair this is, the base and quote may be swapped.  While margin transactions only have one ledger entry, its pair order can be swapped based on whether it is a buy or a sell
						if ($baseCurrencyAssetType == $assetPair -> getBaseCurrency() -> getAssetTypeID())
						{
							$quoteCurrencyAssetType							= $assetPair -> getQuoteCurrency() -> getAssetTypeLabel();
							$quoteCurrencyAssetTypeID						= $assetPair -> getQuoteCurrency() -> getAssetTypeID();
						}
						else
						{
							$quoteCurrencyAssetType							= $assetPair -> getBaseCurrency() -> getAssetTypeLabel();
							$quoteCurrencyAssetTypeID						= $assetPair -> getBaseCurrency() -> getAssetTypeID();	
						}

						$baseToQuoteCurrencySpotPrice						= $krakenTradeTransaction -> getPriceInQuoteCurrency();
						
						if ($baseCurrencyAssetTypeID == 1 && $quoteCurrencyAssetTypeID == 2)
						{
							$btcSpotPriceAtTimeOfTransaction				= $baseToQuoteCurrencySpotPrice;
						}
						else
						{
							$cascadeRetrieveSpotPriceResponseObject			= getSpotPriceForAssetPairUsingSourceCascade(1, 2, $transactionTimestamp, 10, "Kraken price by date", $dbh);
							if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
							{
								$btcSpotPriceAtTimeOfTransaction			= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
							}	
						}
						
						if ($quoteCurrencyAssetTypeID == 2)
						{
							$baseToUSDCurrencySpotPrice						= $krakenTradeTransaction -> getPriceInQuoteCurrency();
							
							$currencyPriceValueSource						= "Kraken";
							
							$currencyPriceValueSourceID						= 6;
						}
						else
						{
							// get spot price for this currency and USD from the best available source
							$cascadeRetrieveSpotPriceResponseObject			= getSpotPriceForAssetPairUsingSourceCascade($baseCurrencyAssetTypeID, 2, $transactionTimestamp, 10, "Kraken price by date", $dbh);
							
							if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
							{
								$baseToUSDCurrencySpotPrice					= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
								$currencyPriceValueSource					= $cascadeRetrieveSpotPriceResponseObject['spotPriceSourceLabel'];	
								$currencyPriceValueSourceID					= $cascadeRetrieveSpotPriceResponseObject['spotPriceSourceID'];	
							}
						}
					}
					else
					{
						errorLog("Unable to instantiate trade for $refid");
						exit();
					}
				}
				else
				{
					// not a trade - this is a deposit or withdrawal, and has no provided spot price
					
					if ($ledgerEntryTypeID == 1)
					{
						$transactionTypeID									= 7;	
						$transactionTypeLabel								= "Fiat Deposit";
					}
					else if ($ledgerEntryTypeID == 2)
					{
						$transactionTypeID									= 8;	
						$transactionTypeLabel								= "Fiat Withdrawal";
					}
					
					if ($switchNonDebitPairs == 1)
					{
						if ($ledgerEntryTypeID == 1)
						{
							$baseCurrencyAssetType							= "ZUSD";
							$baseCurrencyAssetTypeID						= 2;
							
							$quoteCurrencyAssetType							= $asset;
							$quoteCurrencyAssetTypeID						= $assetTypeID;	
						}
						else if ($ledgerEntryTypeID == 2)
						{
							$baseCurrencyAssetType							= $asset;
							$baseCurrencyAssetTypeID						= $assetTypeID;
							
							$quoteCurrencyAssetType							= "ZUSD";
							$quoteCurrencyAssetTypeID						= 2;	
						}	
					}
					else
					{
						$baseCurrencyAssetType								= $asset;
						$baseCurrencyAssetTypeID							= $assetTypeID;
						
						$quoteCurrencyAssetType								= "ZUSD";
						$quoteCurrencyAssetTypeID							= 2;	
					}
					
					$assetPair												= new CommonCurrencyPair();
					$instantiateAssetResponseObject							= $assetPair -> instantiateCommonCurrencyPairUsingAssetIDs($baseCurrencyAssetTypeID, $quoteCurrencyAssetTypeID, $dbh);
					
					if ($instantiateAssetResponseObject['instantiatedCommonCurrencyPairObject'] == true)
					{
						$pairName											= $assetPair -> getPairName();	
					}
					else
					{
						$pairName											= $baseCurrencyAssetType.$quoteCurrencyAssetType;
						
						errorLog("Could not find currency pair for $pairName");
					}
					
					$foundBaseCurrencySpotPrice								= false;
					
					// get spot price for the base currency and USD from the best available source
					$cascadeRetrieveSpotPriceResponseObject					= getSpotPriceForAssetPairUsingSourceCascade($baseCurrencyAssetTypeID, 2, $transactionTimestamp, 10, "Kraken price by date", $dbh);
					
					if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
					{
						$baseToUSDCurrencySpotPrice							= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
						$currencyPriceValueSource							= $cascadeRetrieveSpotPriceResponseObject['spotPriceSourceLabel'];	
						$currencyPriceValueSourceID							= $cascadeRetrieveSpotPriceResponseObject['spotPriceSourceID'];
						$foundBaseCurrencySpotPrice							= true;
						
						if ($quoteCurrencyAssetTypeID == 2)
						{
							$baseToQuoteCurrencySpotPrice					= $baseToUSDCurrencySpotPrice;	
						}
					}
					else
					{
						// @task 2019-02-26 try inverted spot price pair, and if found, spot price is 1 / spot price
						$cascadeRetrieveSpotPriceResponseObject												= getSpotPriceForAssetPairUsingSourceCascade(2, $baseCurrencyAssetTypeID, $transactionTimestamp, 10, "Kraken price by date", $dbh);
						
						if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
						{
							$baseToUSDCurrencySpotPrice						= 1 / $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
						}
						else
						{
							errorLog("Trying to generate base to USD currency spot price.  Could not get base currency $baseCurrencyAssetTypeID spot price for date $transactionTimestamp");	
						}
					}
					
					if ($baseCurrencyAssetTypeID == 1 && $quoteCurrencyAssetTypeID == 2 && $foundBaseCurrencySpotPrice == true)
					{
						$btcSpotPriceAtTimeOfTransaction					= $baseToQuoteCurrencySpotPrice;
					}
					else
					{
						$cascadeRetrieveSpotPriceResponseObject				= getSpotPriceForAssetPairUsingSourceCascade(1, 2, $transactionTimestamp, 10, "Kraken price by date", $dbh);
						if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
						{
							$btcSpotPriceAtTimeOfTransaction				= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
						}	
					}
					
					if ($quoteCurrencyAssetTypeID != 2)
					{
						// get spot price for the base currency and quote currency from the best available source
						$cascadeRetrieveSpotPriceResponseObject				= getSpotPriceForAssetPairUsingSourceCascade($baseCurrencyAssetTypeID, $quoteCurrencyAssetTypeID, $transactionTimestamp, 10, "Kraken price by date", $dbh);
						
						if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
						{
							$baseToQuoteCurrencySpotPrice					= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
						}
						else if ($foundBaseCurrencySpotPrice == true)
						{
							// try to get quote currency USD spot price
							$cascadeRetrieveSpotPriceResponseObject			= getSpotPriceForAssetPairUsingSourceCascade($quoteCurrencyAssetTypeID, 2, $transactionTimestamp, 10, "Kraken price by date", $dbh);
						
							if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
							{
								$quoteCurrencySpotPrice						= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
								
								$baseToQuoteCurrencySpotPrice				= $baseToUSDCurrencySpotPrice / $quoteCurrencySpotPrice;
								
								$currencyPriceValueSource					= "Currency Pair calculated values";	
								$currencyPriceValueSourceID					= 9;
							}
							else
							{
								errorLog("Trying to generate base to quote currency spot price.  Could not get quote currency $quoteCurrencyAssetTypeID spot price for date $transactionTimestamp");
							}
						}
						else
						{
							// try getting spot price using inverted pair - if found, actual spot price is 1 / resulting spot price
							$cascadeRetrieveSpotPriceResponseObject			= getSpotPriceForAssetPairUsingSourceCascade($quoteCurrencyAssetTypeID, $baseCurrencyAssetTypeID, $transactionTimestamp, 10, "Kraken price by date", $dbh);
							if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
							{
								$baseToQuoteCurrencySpotPrice				= 1 / $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
							}
							else
							{
								errorLog("Trying to generate base to quote currency spot price.  Could not get base currency $baseCurrencyAssetTypeID spot price for date $transactionTimestamp");	
							}
						}	
					}
				}
				
				if (isset($cryptoCurrencyTypesImported[$baseCurrencyAssetTypeID][$quoteCurrencyAssetTypeID]))
				{
					$currentCount											= $cryptoCurrencyTypesImported[$baseCurrencyAssetTypeID][$quoteCurrencyAssetTypeID];
					$currentCount++;
					
					$cryptoCurrencyTypesImported[$baseCurrencyAssetTypeID][$quoteCurrencyAssetTypeID]			= $currentCount;
				}
				else
				{
					$cryptoCurrencyTypesImported[$baseCurrencyAssetTypeID][$quoteCurrencyAssetTypeID]			= 1;	
				}
				
				createDataImportAssetStatusRecord($accountID, $userEncryptionKey, $dataImportEventRecordID, $baseCurrencyAssetTypeID, $quoteCurrencyAssetTypeID, $globalCurrentDate, $sid, $dbh);
				
				// here 2019-01-16
				errorLog("getGlobalTransactionIdentificationRecordID for $accountID, $assetTypeID, $ledgerEntryIDValue, $transactionSourceID, $globalCurrentDate, $sid", $GLOBALS['debugCoreFunctionality']);
				
			    $globalTransactionIDTestResults								= getGlobalTransactionIdentificationRecordID($accountID, $assetTypeID, $ledgerEntryIDValue, $transactionSourceID, $globalCurrentDate, $sid, $dbh);
			    
			    errorLog(json_encode($globalTransactionIDTestResults));
			    
			    if ($globalTransactionIDTestResults['foundNativeTransactionForAccount'] == false)
				{
					errorLog("not found $ledgerEntryIDValue", $GLOBALS['debugCoreFunctionality']);
					
					$returnValue[$ledgerEntryIDValue]["existingRecordFound"]= false;
					
					// create one if not found
					$globalTransactionCreationResults						= createGlobalTransactionIdentificationRecord($accountID, $exchangeTileID, $assetTypeID, $ledgerEntryIDValue, $transactionSourceID, $globalCurrentDate, $sid, $dbh);
			
					if ($globalTransactionCreationResults['createdGlobalTransactionIdentificationRecord'] == true)
					{
						errorLog("createdGTIR");
						
						$returnValue[$ledgerEntryIDValue]["createdGTIR"]	= true;
							
						$globalTransactionIdentifierRecordID				= $globalTransactionCreationResults['globalTransactionIdentificationRecordID'];
						$profitStanceTransactionIDValue						= $globalTransactionCreationResults['profitStanceTransactionIDValue'];
							
						// @Task - this is where I need to use the new provider account wallet idea of 
							
						$providerAccountWallet								= new ProviderAccountWallet();
							
						// check for provider account wallet							
						$instantiationResult								= $providerAccountWallet -> instantiateAccountWalletObjectForAccountByAssetTypeIDAndTransactionSourceID($accountID, $assetTypeID, $transactionSourceID, $dbh);
							
						if ($instantiationResult['instantiatedWallet'] == false)
						{
							errorLog("could not instantiate provider wallet");
							// create wallet if not found
							$providerAccountWallet -> createAccountWalletObject($accountID, $assetTypeID, $asset, $accountID, "$accountID-$transactionSourceID-$asset", $walletTypeID, "Private Ledger Based Wallet", $transactionSourceID, $transactionSourceName, $globalCurrentDate, $sid, $dbh);
						}
						else
						{
							errorLog("instantiated provider wallet");
						}
						
						$providerWalletID									= $providerAccountWallet -> getAccountWalletID();
						
						errorLog("providerWalletID $providerWalletID");
							
						if ($providerWalletID > 0)
						{
							errorLog("create KrakenLedgerEntry setData($accountID, 0, $globalTransactionIdentifierRecordID, $transactionSourceID, $providerWalletID, 0, 0, $ledgerEntryIDValue, $refid, ".$assetPair -> getPairID().", $pairName, $timestamp, $ledgerEntryType, $ledgerEntryTypeID, $assetClass, $assetClassTypeID, $asset, $assetTypeID, $amount, $fee, $balance, $isDebit, $baseToQuoteCurrencySpotPrice, $baseToUSDCurrencySpotPrice, $currencyPriceValueSource, $currencyPriceValueSourceID, $transactionRecordID, $walletTypeID, $walletTypeName, $userEncryptionKey, $globalCurrentDate, $sid);");
							
							$krakenLedgerEntry 								= new KrakenLedgerTransaction();
							
							$krakenLedgerEntry -> setData($accountID, 0, $exchangeTileID, $globalTransactionIdentifierRecordID, $transactionSourceID, $providerAccountWallet, $providerWalletID, 0, 0, $ledgerEntryIDValue, $refid, $assetPair, $assetPair -> getPairID(), $pairName, $transactionTimestampObject, $timestamp, $ledgerEntryType, $ledgerEntryTypeID, $transactionTypeID, $transactionTypeLabel, $assetClass, $assetClassTypeID, $asset, $assetTypeID, $amount, $fee, $balance, $isDebit, $baseToQuoteCurrencySpotPrice, $baseToUSDCurrencySpotPrice, $currencyPriceValueSource, $currencyPriceValueSourceID, $btcSpotPriceAtTimeOfTransaction, $transactionRecordID, $walletTypeID, $walletTypeName, $userEncryptionKey, $globalCurrentDate, $sid, $dbh);
								
							$writeKrakenRecordResponseObject				= $krakenLedgerEntry -> writeToDatabase($userEncryptionKey, $dbh);
							
							if ($writeKrakenRecordResponseObject['wroteToDatabase'] == true)
							{
								errorLog("success: wrote Kraken Ledger $ledgerEntryIDValue ".$krakenLedgerEntry -> getKrakenLedgerRecordID());
								
								$cryptoAmountWithFees						= $amount - $fee;
								
								$profitStanceLedgerEntry					= new ProfitStanceLedgerEntry();
								$profitStanceLedgerEntry -> setData($accountID, $assetTypeID, $baseCurrencyAssetType, $transactionSourceID, $transactionSourceName, $exchangeTileID, $globalTransactionIdentifierRecordID, $transactionTimestampObject, $cryptoAmountWithFees, $dbh);
								
								$writeProfitStanceLedgerEntryRecordResponseObject							= $profitStanceLedgerEntry -> writeToDatabase($dbh);
								
								if ($writeProfitStanceLedgerEntryRecordResponseObject['wroteToDatabase'] == true)
								{
									errorLog("wrote profitStance ledger entry $accountID, $assetTypeID, $baseCurrencyAssetType, $transactionSourceID, $transactionSourceName, $globalTransactionIdentifierRecordID, ".$krakenLedgerEntry -> getFormattedTransactionTime().", $cryptoAmountWithFees to the database.", $GLOBALS['debugCoreFunctionality']);
								}
								else
								{
									errorLog("could not write profitStance ledger entry $accountID, $assetTypeID, $baseCurrencyAssetType, $transactionSourceID, $transactionSourceName, $globalTransactionIdentifierRecordID, ".$krakenLedgerEntry -> getFormattedTransactionTime().", $cryptoAmountWithFees to the database.", $GLOBALS['criticalErrors']);	
								}
								
								// update native record ID in GTRID table
								$setNativeTransactionRecordIDResult			= setNativeTransactionRecordIDForGlobalTransactionIndentificationRecordID($accountID, $krakenLedgerEntry -> getKrakenLedgerRecordID(), $globalTransactionIdentifierRecordID, $globalCurrentDate, $sid, $dbh);
							}
							else
							{
								errorLog("error: could not write Kraken Ledger $ledgerEntryIDValue ".$krakenLedgerEntry -> getKrakenLedgerRecordID(), $GLOBALS['criticalErrors']);	
							}
						}
					}
					
					$numberProcessed++;	
				}
				else
				{
					errorLog("found $ledgerEntryIDValue", $GLOBALS['debugCoreFunctionality']);
					
					$returnValue[$ledgerEntryIDValue]["existingRecordFound"]	= true;
					$returnValue[$ledgerEntryIDValue]["newTransactionCreated"]	= false;
				}

				errorLog("completed array index $ledgerEntryIDValue");
		    }
		
			if ($includeDetailReporting != true)
			{
				$returnValue												= array();		
			}
			
			$returnValue["krakenDataImported"]								= "complete";
			$returnValue['numberProcessed']									= $numberProcessed;
			$returnValue['startPosition']									= $startPosition;
			$returnValue['cryptoCurrencyTypesImported']						= $cryptoCurrencyTypesImported;
    	}
    	catch (Exception $e)
    	{
	    	errorLog("ERROR: array parsing error");	
    	}

        return $returnValue;
    }
	
	function importUploadedKrakenTradeTransactions($jsonObject, $name, $accountID, $userEncryptionKey, $transactionSourceID, $exchangeTileID, $walletTypeID, $includeDetailReporting, $dataImportEventRecordID, $globalCurrentDate, $sid, $dbh)
	{
    	$returnValue   																					= array();
    	
		$currenciesToProcess																			= array();
    	
		// IP Transaction creation constants
    	$numberOfConfirmations																			= 0; 
		$providerNotes																					= "";
		$resourcePath																					= "";
		$transactionStatus																				= "completed"; 
		$transactionStatusID																			= 1;
		
		$addressType																					= "ledgerEntryIDValue";
		$addressTypeID																					= 8;
		$addressCallbackURL																				= "";
    	
    	// values not present in CSV
    	
    	$walletTypeName																					= "Private Ledger Based Wallet";
    	
    	$posTxIDValue																					= "";
		$posTxID																						= 0; 
	    $posStatus																						= "";
	    $posStatusID																					= 0;
	    $cPrice																							= 0.0;
	    $cCost																							= 0.0;
	    $cFee																							= 0.0;
	    $cVol																							= 0.0;
	    $cMargin																						= 0.0;
	    $net																							= 0.0;
    	
    	try
    	{
	    	if (!empty($jsonObject -> $name))
	    	{
		    	$jsonMDArray 																			= $jsonObject -> $name; 
		    	
		    	errorLog("count ".count($jsonMDArray), $GLOBALS['debugCoreFunctionality']);
		    	
		    	foreach ($jsonMDArray as $arrayIndex => $genericTransationObject) 
		    	{
			    	errorLog("arrayIndex: ".$arrayIndex);
			    	
			    	$nativeTransactionIDValue															= urldecode( strip_tags( trim( $genericTransationObject -> txid ) ) );
			    	$transactionType																	= urldecode( strip_tags( trim( $genericTransationObject -> type ) ) );
				    $transactionTimestamp																= urldecode( strip_tags( trim( $genericTransationObject -> time ) ) );
				    $misc																				= urldecode( strip_tags( trim( $genericTransationObject -> misc ) ) );
					$spotPriceInQuoteCurrency															= urldecode( strip_tags( trim( $genericTransationObject -> price ) ) );
					$transactionVolumeInBaseCurrency													= urldecode( strip_tags( trim( $genericTransationObject -> vol ) ) );
				    $transactionFeeInQuoteCurrency														= urldecode( strip_tags( trim( $genericTransationObject -> fee ) ) );
				    $transactionCostInQuoteCurrencyNotIncludingFees										= urldecode( strip_tags( trim( $genericTransationObject -> cost ) ) );
				    $pairName																			= urldecode( strip_tags( trim( $genericTransationObject -> pair ) ) );
				    $ordertxid																			= urldecode( strip_tags( trim( $genericTransationObject -> ordertxid ) ) );
				    $krakenOrderTypeLabel																= urldecode( strip_tags( trim( $genericTransationObject -> ordertype ) ) );
				    $margin																				= urldecode( strip_tags( trim( $genericTransationObject -> margin ) ) );
				    $ledgers																			= urldecode( strip_tags( trim( $genericTransationObject -> ledgers ) ) );
				    
				    // @Task - write code to convert currencies between USD and BTC and the current type - use amount objects
				    
				    $baseCurrency																		= new AssetInfo();
				    $quoteCurrency																		= new AssetInfo();
				    
				    $krakenCurrencyPair																	= new KrakenCurrencyPair();
				    
				    $responseObject																		= $krakenCurrencyPair -> instantiateKrakenCurrencyPairUsingPairName($pairName, $dbh);
				    
				    if ($responseObject['foundKrakenCurrencyPairID'] == true && $responseObject['instantiatedKrakenCurrencyPairObject'] == true)
				    {
						$baseCurrency																	= $krakenCurrencyPair -> getBaseCurrency();
						$quoteCurrency																	= $krakenCurrencyPair -> getQuoteCurrency();    
				    }
				    else
				    {
					    errorLog("Unrecognized Kraken Currency Pair: $pairName");
					    exit();
				    }
				    
				    // not provided
				    
					$assetTypeID																		= $baseCurrency -> getAssetTypeID();
				    $nativeCurrencyID																	= $quoteCurrency -> getAssetTypeID();
				    
				    $assetType																			= $baseCurrency -> getAssetTypeLabel();
				    $nativeCurrency																		= $quoteCurrency -> getAssetTypeLabel();
				    
				    // @Task - it may be better to compact the array rather than have index gaps - write a function that checks to see if a value already exists in array, and if not, adds it
/*
				    $currenciesToProcess[$assetTypeID]													= $assetTypeID;
				    $currenciesToProcess[$nativeCurrencyID]												= $nativeCurrencyID;
*/
					$currenciesToProcess[$assetTypeID][$nativeCurrencyID]								= $nativeCurrency;

				    
				    $baseCurrencyLedgerIDValue															= "";
				    $quoteCurrencyLedgerIDValue															= "";
				    
				    $ledgerValues																		= splitCommaSeparatedString($ledgers);
				    
				    if (count($ledgerValues) == 2)
				    {
						$baseCurrencyLedgerIDValue														= trim($ledgerValues[0]);
						$quoteCurrencyLedgerIDValue														= trim($ledgerValues[1]);
				    }
				    
				    $transactionSourceName																= getTransactionSourceTypeLabelFromEnumValue($transactionSourceID, $dbh);
					
					$transactionTypeID																	= getEnumValueTransactionType($transactionType, $dbh);
					$krakenOrderTypeID																	= getEnumValueKrakenOrderType($krakenOrderTypeLabel, $dbh);
					
					// check for global transaction ID
					
					errorLog("getGlobalTransactionIdentificationRecordID for $arrayIndex $accountID, $assetTypeID, $nativeTransactionIDValue, $transactionSourceID, $globalCurrentDate, $sid", $GLOBALS['debugCoreFunctionality']);
					
				    $globalTransactionIDTestResults														= getGlobalTransactionIdentificationRecordID($accountID, $assetTypeID, $nativeTransactionIDValue, $transactionSourceID, $globalCurrentDate, $sid, $dbh);
				    
				    if ($globalTransactionIDTestResults['foundNativeTransactionForAccount'] == false)
					{
						errorLog("not found $arrayIndex", $GLOBALS['debugCoreFunctionality']);
						
						$returnValue[$nativeTransactionIDValue]["existingRecordFound"]					= false;
						// create one if not found
						$globalTransactionCreationResults												= createGlobalTransactionIdentificationRecord($accountID, $exchangeTileID, $assetTypeID, $nativeTransactionIDValue, $transactionSourceID, $globalCurrentDate, $sid, $dbh);
			
						if ($globalTransactionCreationResults['createdGlobalTransactionIdentificationRecord'] == true)
						{
							$returnValue[$nativeTransactionIDValue]["createdGTIR"]						= true;
							
							$globalTransactionIdentifierRecordID										= $globalTransactionCreationResults['globalTransactionIdentificationRecordID'];
							$profitStanceTransactionIDValue												= $globalTransactionCreationResults['profitStanceTransactionIDValue'];
							
							// @Task - this is where I need to use the new provider account wallet idea of 
							
							$providerAccountWallet														= new ProviderAccountWallet();
							
							// check for provider account wallet							
							$instantiationResult														= $providerAccountWallet -> instantiateAccountWalletObjectForAccountByAssetTypeIDAndTransactionSourceID($accountID, $assetTypeID, $transactionSourceID, $dbh);
							
							if ($instantiationResult['instantiatedWallet'] == false)
							{
								// create wallet if not found
								$providerAccountWallet -> createAccountWalletObject($accountID, $assetTypeID, $assetType, $accountID, "$accountID-$transactionSourceID-$assetType", $walletTypeID, "Private Ledger Based Wallet", $transactionSourceID, $transactionSourceName, $globalCurrentDate, $sid, $dbh);
							}
							
							$providerWalletID															= $providerAccountWallet -> getAccountWalletID();
							
							if ($providerWalletID > 0)
							{
								$krakenCurrencyPair														= new KrakenCurrencyPair();
								
								$krakenCurrencyPair -> instantiateKrakenCurrencyPairUsingPairName($pairName, $dbh);
								
								// create kraken trade transaction object and write it
								$krakenTradeTransaction 												= new KrakenTradeTransaction();
								
								// @task must work on the currency pair, effective pair name, etc.
								
								$krakenTradeTransaction -> setData($accountID, 0, $exchangeTileID, $globalTransactionIdentifierRecordID, $transactionSourceID, $providerWalletID, 0, 0, $nativeTransactionIDValue, $ordertxid, $krakenCurrencyPair, $krakenCurrencyPair -> getPairID(), $pairName, $pairName, $transactionTimestamp, $transactionTypeID, $transactionType, $krakenOrderTypeID, $krakenOrderTypeLabel, $spotPriceInQuoteCurrency, $transactionCostInQuoteCurrencyNotIncludingFees, $transactionFeeInQuoteCurrency, $transactionVolumeInBaseCurrency, $margin, $misc, $baseCurrencyLedgerIDValue, $quoteCurrencyLedgerIDValue, $posTxIDValue, $posTxID, $posStatus, $posStatusID, $cPrice, $cCost, $cFee, $cVol, $cMargin, $net, $walletTypeID, $walletTypeName, $userEncryptionKey, $globalCurrentDate, $sid, $dbh);
								
// 								setData($accountID, 0, $exchangeTileID, $globalTransactionRecordID, $transactionSourceID, $providerAccountWalletID, $baseCurrencyWalletID, $quoteCurrencyWalletID, $txIDValue, $orderTxIDValue, $krakenCurrencyPair, $krakenCurrencyPairID, $krakenCurrencyPairName, $effectivePairName, $transactionTime, $transactionTypeID, $transactionTypeLabel, $krakenOrderTypeID, $krakenOrderTypeLabel, $priceInQuoteCurrency, $costInQuoteCurrency, $feeInQuoteCurrency, $volBaseCurrency, $margin, $misc, $baseCurrencyLedgerEntryIDValue, $quoteCurrencyLedgerEntryIDValue, $posTxIDValue, $posTxID, $posStatus, $posStatusID, $cPrice, $cCost, $cFee, $cVol, $cMargin, $net, $walletTypeID, $walletTypeName, $userEncryptionKey, $globalCurrentDate, $sid, $dbh);
								
								$krakenTradeTransaction -> writeToDatabase($userEncryptionKey, $dbh);
								
								$ipClientAccountTransaction												= new IPClientAccountTransaction();
								
								$returnValue['transactions'][$nativeTransactionIDValue]['instantiation'] = $ipClientAccountTransaction -> instantiateByNativeTransactionIDValue($accountID, $userEncryptionKey, $nativeTransactionIDValue, $sid, $dbh);
								
								if ($returnValue['transactions'][$nativeTransactionIDValue]['instantiation']['ipTransactionFound'] == false)
								{
								
									$authorID															= $accountID; 
									
									$transactionRecordID												= $krakenTradeTransaction -> getKrakenTransactionRecordID();
									
									// @task - must be converted to base currency price and USD, plus calculate buy and sell prices
									
									$cryptoCurrencyPriceAtTimeOfTransaction								= new IPClientAccountCryptoCurrencyPriceObject();
									 
									$fromAddressValue													= "";
									$toAddressValue														= "";
									
									$fromAddressCurrencyType											= "";
									$toAddressCurrencyType												= "";
									
									$fromAddressCurrencyTypeID											= 0;
									$toAddressCurrencyTypeID											= 0;
									
									// Check to see if the quote currency is USD
									// if not, call the API to get the exchange price for USD
									
									$feeAmountCrypto													= 0;
									$feeAmountNative													= 0;
									$feeAmountUSD														= 0;
										
									$totalTransactionAmountWFCrypto										= 0;
									$totalTransactionAmountWFNative										= 0;
									$totalTransactionAmountWFUSD										= 0;
									
									$totalTransactionAmountCrypto										= 0;
									$totalTransactionAmountNative										= 0;
									$totalTransactionAmountUSD											= 0;
									
									if ($krakenCurrencyPair -> getQuoteCurrencyID() == 2)
									{
										$feeAmountNative												= $transactionFeeInQuoteCurrency;
										$feeAmountUSD													= $transactionFeeInQuoteCurrency;
										$feeAmountCrypto												= calculateFeeInBaseCurrencyForFeePaidInQuoteCurrency($transactionFeeInQuoteCurrency, $transactionCostInQuoteCurrencyNotIncludingFees, $spotPriceInQuoteCurrency, $transactionVolumeInBaseCurrency);
										
										$totalTransactionAmountWFCrypto									= $transactionVolumeInBaseCurrency;
										$totalTransactionAmountWFNative									= $transactionCostInQuoteCurrencyNotIncludingFees;
										$totalTransactionAmountWFUSD									= $transactionCostInQuoteCurrencyNotIncludingFees;
									}
									else
									{
										errorLog("quote currency is not USD - transaction calculation error for transaction $nativeTransactionIDValue and user $accountID: must implement API to calculate amount in USD");
										
										$feeAmountNative												= $transactionFeeInQuoteCurrency;
										$feeAmountUSD													= 0;
										$feeAmountCrypto												= $transactionFeeInQuoteCurrency;
										
										$totalTransactionAmountWFCrypto									= $transactionVolumeInBaseCurrency;
										$totalTransactionAmountWFNative									= $transactionCostInQuoteCurrencyNotIncludingFees;
										$totalTransactionAmountWFUSD									= 0;
									}

									
									// https://support.kraken.com/hc/en-us/articles/203053186-Currency-Exchange-Buying-Selling-and-Currency-Pair-Selection
									
									// If the "buy" button is selected and currency pair X/Y is selected, then currency X will be bought and currency Y sold.
									// If the "sell" button is selected and currency pair x/y is selected, then currency X will be sold and currency Y will be bought.
									
									
									if ($transactionTypeID == 1)
									{
										$toAddressValue													= $baseCurrencyLedgerIDValue;
										$fromAddressValue												= $quoteCurrencyLedgerIDValue;
										
										$toAddressCurrencyTypeID										= $krakenCurrencyPair -> getBaseCurrencyID();
										$fromAddressCurrencyTypeID										= $krakenCurrencyPair -> getQuoteCurrencyID();
										
										$toAddressCurrencyType											= $krakenCurrencyPair -> getBaseCurrency() -> getAssetTypeLabel();
										$fromAddressCurrencyType										= $krakenCurrencyPair -> getQuoteCurrency() -> getAssetTypeLabel();
										// THIS IS A BUY - THE TOTAL AMOUNT IS THE TRANSACTION AMOUNT WITHOUT FEE + FEE AMOUNT FOR EACH
										
										$totalTransactionAmountCrypto									= $totalTransactionAmountWFCrypto; // + $feeAmountCrypto;
										$totalTransactionAmountNative									= $totalTransactionAmountWFNative; // + $feeAmountNative;
										$totalTransactionAmountUSD										= $totalTransactionAmountWFUSD; // + $feeAmountUSD;
									}
									else
									{
										$fromAddressValue												= $baseCurrencyLedgerIDValue;
										$toAddressValue													= $quoteCurrencyLedgerIDValue;
										
										$fromAddressCurrencyTypeID										= $krakenCurrencyPair -> getBaseCurrencyID();
										$toAddressCurrencyTypeID										= $krakenCurrencyPair -> getQuoteCurrencyID();
										
										$fomAddressCurrencyType											= $krakenCurrencyPair -> getBaseCurrency() -> getAssetTypeLabel();
										$toAddressCurrencyType						    				= $krakenCurrencyPair -> getQuoteCurrency() -> getAssetTypeLabel();
										
										// THIS IS A SELL - THE TOTAL AMOUNT IS THE TRANSACTION AMOUNT WITHOUT FEE - FEE AMOUNT FOR EACH
										$totalTransactionAmountCrypto									= $totalTransactionAmountWFCrypto; // - $feeAmountCrypto;
										$totalTransactionAmountNative									= $totalTransactionAmountWFNative; // - $feeAmountNative;
										$totalTransactionAmountUSD										= $totalTransactionAmountWFUSD; // - $feeAmountUSD;
										
										$totalTransactionAmountWFCrypto									= $totalTransactionAmountWFCrypto * -1;
										$feeAmountCrypto												= $feeAmountCrypto * -1;
										
									}
									
									$fromAddress														= new IPTransactionAddressObject();
									$toAddress															= new IPTransactionAddressObject();
																		
									$fromAddress -> setData($addressCallbackURL, $fromAddressCurrencyType, $fromAddressCurrencyTypeID, $accountID, $resourcePath, $addressType, $addressTypeID, $fromAddressValue, $accountID, $globalCurrentDate, 0, 0, $sid);
									
									$toAddress -> setData($addressCallbackURL, $toAddressCurrencyType, $toAddressCurrencyTypeID, $accountID, $resourcePath, $addressType, $addressTypeID, $toAddressValue, $accountID, $globalCurrentDate, 0, 0, $sid);
									
									$totalTransactionAmount												= new IPTransactionAmountObject();
									$transactionAmountWithoutFees										= new IPTransactionAmountObject();
									$feeAmount															= new IPTransactionAmountObject();
									
									$totalTransactionAmount -> setData($accountID, $totalTransactionAmountCrypto, $totalTransactionAmountNative, $totalTransactionAmountUSD, $accountID, $globalCurrentDate, 0, 0, $sid, 1, "Total Transaction Amount");
									
									$transactionAmountWithoutFees -> setData($accountID, $totalTransactionAmountWFCrypto, $totalTransactionAmountWFNative, $totalTransactionAmountWFUSD, $accountID, $globalCurrentDate, 0, 0, $sid, 2, "Transaction Amount Without Fees");

									$feeAmount -> setData($accountID, $feeAmountCrypto, $feeAmountNative, $feeAmountUSD, $accountID, $globalCurrentDate, 0, 0, $sid, 3, "Fee Amount");
									
									errorLog("transactionTimestamp $transactionTimestamp");
									
									
									
									$transactionDate													= substr($transactionTimestamp, 0, 10); // new DateTime($transactionTimestamp);
									
									$quoteCurrencyID													= $krakenCurrencyPair -> getQuoteCurrencyID();
									
									
									$quoteCurrencySpotPriceAtTimeOfTransaction							= 0;	
									
									$cascadeRetrieveSpotPriceResponseObject								= getSpotPriceForAssetPairUsingSourceCascade($quoteCurrencyID, 2, $transactionDate, 2, "CoinBase price by date", $dbh);
					
									if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
									{
										$quoteCurrencySpotPriceAtTimeOfTransaction						= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
									}
									
									$btcSpotPriceAtTimeOfTransaction									= 0;	
									
									$cascadeRetrieveSpotPriceResponseObject								= getSpotPriceForAssetPairUsingSourceCascade(1, 2, $transactionDate, 2, "CoinBase price by date", $dbh);
					
									if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
									{
										$btcSpotPriceAtTimeOfTransaction								= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
									}
									
									$transactionAmountInBaseCurrency									= $totalTransactionAmountCrypto;
									
									errorLog("transactionAmountInBaseCurrency $transactionAmountInBaseCurrency assetTypeID $assetTypeID");
									
									/*
errorLog("debug ip client account transaction: 
									accountID: $accountID, 
									assetType: $assetType, 
									assetTypeID: $assetTypeID, 
									exchangeTileID: $exchangeTileID, 
									authorID: $authorID, 
									globalCurrentDate: $globalCurrentDate, 
									cryptoCurrencyPriceAtTimeOfTransaction: $cryptoCurrencyPriceAtTimeOfTransaction, 
									feeAmount: $feeAmount, 
									fromAddress: $fromAddress, 
									globalCurrentDate: $globalCurrentDate, 
									nativeCurrency: $nativeCurrency, 
									nativeCurrencyID: $nativeCurrencyID, 
									nativeTransactionIDValue: $nativeTransactionIDValue, 
									numberOfConfirmations: $numberOfConfirmations, 
									profitStanceTransactionIDValue: $profitStanceTransactionIDValue, 
									providerNotes: $providerNotes, 
									providerWalletID: $providerWalletID, 
									transactionAmountInBaseCurrency: $transactionAmountInBaseCurrency, 
									quoteCurrencySpotPriceAtTimeOfTransaction: $quoteCurrencySpotPriceAtTimeOfTransaction, 
									btcPriceAtTimeOfTransaction: $btcPriceAtTimeOfTransaction, 
									resourcePath: $resourcePath, 
									sid: $sid, 
									toAddress: $toAddress, 
									totalTransactionAmount: $totalTransactionAmount, 
									transactionAmountWithoutFees: $transactionAmountWithoutFees, 
									transactionRecordID: $transactionRecordID, 
									transactionStatus: $transactionStatus, 
									transactionStatusID: $transactionStatusID, 
									transactionType: $transactionType, 
									transactionTypeID: $transactionTypeID, 
									transactionDate: $transactionDate, 
									transactionTimestamp: $transactionTimestamp, 
									transactionSourceName: $transactionSourceName, 
									transactionSourceID: $transactionSourceID, 
									walletTypeID: $walletTypeID, 
									globalTransactionIdentifierRecordID: $globalTransactionIdentifierRecordID");
*/
									
									$ipClientAccountTransaction -> setData($accountID, $assetType, $assetTypeID, $exchangeTileID, $authorID, $globalCurrentDate, $cryptoCurrencyPriceAtTimeOfTransaction, $feeAmount, $fromAddress, $globalCurrentDate, $nativeCurrency, $nativeCurrencyID, $nativeTransactionIDValue, $numberOfConfirmations, $profitStanceTransactionIDValue, $providerNotes, $providerWalletID, $transactionAmountInBaseCurrency, $quoteCurrencySpotPriceAtTimeOfTransaction, $btcSpotPriceAtTimeOfTransaction, $resourcePath, $sid, $toAddress, $totalTransactionAmount, $transactionAmountWithoutFees, $transactionRecordID, $transactionStatus, $transactionStatusID, $transactionType, $transactionTypeID, $transactionDate, $transactionTimestamp, $transactionSourceName, $transactionSourceID, $walletTypeID, $globalTransactionIdentifierRecordID);
									
									$returnValue['transactions'][$nativeTransactionIDValue]['creation'] = $ipClientAccountTransaction -> createTransactionRecord($userEncryptionKey, $dbh);
									
									if ($returnValue['transactions'][$nativeTransactionIDValue]['creation']['ipTransactionCreated'] == true)
									{
										$returnValue[$nativeTransactionIDValue]["newTransactionCreated"]	= true;
										
										$newTransactionID 												= $ipClientAccountTransaction -> getTransactionRecordID();
										
										// populate amount records
										
										$totalTransactionAmount -> setIPTransactionRecordID($newTransactionID);
										$returnValue['transactions'][$nativeTransactionIDValue]['transactionAmounts'][1]['creation'] 		= $totalTransactionAmount -> createTransactionAmountObject($dbh);
										$returnValue['transactions'][$nativeTransactionIDValue]['transactionAmounts'][1]['updateObjectID'] 	= $ipClientAccountTransaction -> updateTransactionAmountObjectIDForType($totalTransactionAmount -> getIPTransactionAmountObjectID(), 1, $globalCurrentDate, $dbh);
										
										$ipClientAccountTransaction -> setTotalTransactionAmount($totalTransactionAmount);
										
										$transactionAmountWithoutFees -> setIPTransactionRecordID($newTransactionID);
										$returnValue['transactions'][$nativeTransactionIDValue]['transactionAmounts'][2]['creation']		= $transactionAmountWithoutFees -> createTransactionAmountObject($dbh);
										$returnValue['transactions'][$nativeTransactionIDValue]['transactionAmounts'][2]['updateObjectID'] 	= $ipClientAccountTransaction -> updateTransactionAmountObjectIDForType($transactionAmountWithoutFees -> getIPTransactionAmountObjectID(), 2, $globalCurrentDate, $dbh);
										
										$ipClientAccountTransaction -> setTransactionAmountWithoutFees($transactionAmountWithoutFees);
										
										$feeAmount -> setIPTransactionRecordID($newTransactionID);
										
										$returnValue['transactions'][$nativeTransactionIDValue]['transactionAmounts'][3]['creation']		= $feeAmount -> createTransactionAmountObject($dbh);
										
										$returnValue['transactions'][$nativeTransactionIDValue]['transactionAmounts'][3]['updateObjectID'] 	= $ipClientAccountTransaction -> updateTransactionAmountObjectIDForType($feeAmount -> getIPTransactionAmountObjectID(), 3, $globalCurrentDate, $dbh);
										
										$ipClientAccountTransaction -> setFeeAmount($feeAmount);
										
										
										// populate address records
										
										$fromAddress -> setIpTransactionRecordID($newTransactionID);
										
										$returnValue['transactions'][$nativeTransactionIDValue]['transactionAddresses']['from']['creation']	= $fromAddress -> createTransactionAddressObject($dbh);
										$returnValue['transactions'][$nativeTransactionIDValue]['transactionAddresses']['from']['updateObjectID']	= $ipClientAccountTransaction -> updateTransactionAddressObjectIDForType($fromAddress -> getIpTransactionAddressObjectID(), 1, $globalCurrentDate, $dbh);
										
										$ipClientAccountTransaction -> setFromAddress($fromAddress);
										
										$toAddress -> setIpTransactionRecordID($newTransactionID);
										$returnValue['transactions'][$nativeTransactionIDValue]['transactionAddresses']['to']['creation']	= $toAddress -> createTransactionAddressObject($dbh);
										$returnValue['transactions'][$nativeTransactionIDValue]['transactionAddresses']['to']['updateObjectID']		= $ipClientAccountTransaction -> updateTransactionAddressObjectIDForType($toAddress -> getIpTransactionAddressObjectID(), 2, $globalCurrentDate, $dbh);
										
										$ipClientAccountTransaction -> setToAddress($toAddress);
										
										$cryptoCurrencyPriceAtTimeOfTransaction -> setData($accountID, $assetTypeID, $assetType, $authorID, 0, $globalCurrentDate, $newTransactionID, 0, $spotPriceInQuoteCurrency, $transactionTimestamp, $sid);
										
										$cryptoCurrencyPriceAtTimeOfTransaction -> writeToDatabase($dbh);
									}
								}
							}
						}	
					}
					else
					{
						errorLog("found $arrayIndex", $GLOBALS['debugCoreFunctionality']);
						
						$returnValue[$nativeTransactionIDValue]["existingRecordFound"]					= true;
						$returnValue[$nativeTransactionIDValue]["newTransactionCreated"]				= false;
					}

					errorLog("completed array index $arrayIndex");
				}
				
				if ($includeDetailReporting != true)
				{
					$returnValue																		= array();	
				}
				
				$returnValue["csvDataImported"]															= "complete";
	    	}
	    	else
	    	{
		    	errorLog("ERROR: JSON object empty or $name not found");	
	    	}	
    	}
    	catch (Exception $e)
    	{
	    	errorLog("ERROR: $name not found in JSON object");	
    	}
    	
		foreach ($currenciesToProcess as $baseCurrencyAssetTypeID => $arrayOfNativeCurrencyIDs)
    	{
	    	foreach ($arrayOfNativeCurrencyIDs AS $nativeCurrencyID => $nativeCurrencyLabel)
	    	{
		    	performFIFOTransactionCalculationsOnIPTransactions($accountID, $userEncryptionKey, $baseCurrencyAssetTypeID, $nativeCurrencyID, $nativeCurrencyLabel, $transactionSourceID, $globalCurrentDate, $sid, $dbh);	
	    	}    		
    	}

        return $returnValue;
    }
	
/*
	function importUploadedKrakenTradeTransactions($jsonObject, $name, $accountID, $userEncryptionKey, $transactionSourceID, $walletTypeID, $includeDetailReporting, $dataImportEventRecordID, $globalCurrentDate, $sid, $dbh)
	{
    	$returnValue   														= array();
    	
		$currenciesToProcess												= array();
    	
		// IP Transaction creation constants
    	$numberOfConfirmations												= 0; 
		$providerNotes														= "";
		$resourcePath														= "";
		$transactionStatus													= "completed"; 
		$transactionStatusID												= 1;
		
		$addressType														= "ledgerEntryIDValue";
		$addressTypeID														= 8;
		$addressCallbackURL													= "";
    	
    	try
    	{
	    	if (!empty($jsonObject -> $name))
	    	{
		    	$jsonMDArray 												= $jsonObject -> $name; 
		    	
		    	error_log("count ".count($jsonMDArray));
		    	
		    	foreach ($jsonMDArray as $arrayIndex => $genericTransationObject) 
		    	{
			    	errorLog("arrayIndex: ".$arrayIndex);
			    	
			    	$nativeTransactionIDValue								= urldecode( strip_tags( trim( $genericTransationObject -> txid ) ) );
			    	$transactionType										= urldecode( strip_tags( trim( $genericTransationObject -> type ) ) );
				    $transactionTimestamp									= urldecode( strip_tags( trim( $genericTransationObject -> time ) ) );
				    $misc													= urldecode( strip_tags( trim( $genericTransationObject -> misc ) ) );
					$spotPriceInQuoteCurrency								= urldecode( strip_tags( trim( $genericTransationObject -> price ) ) );
					$transactionVolumeInBaseCurrency						= urldecode( strip_tags( trim( $genericTransationObject -> vol ) ) );
				    $transactionFeeInQuoteCurrency							= urldecode( strip_tags( trim( $genericTransationObject -> fee ) ) );
				    $transactionCostInQuoteCurrencyNotIncludingFees			= urldecode( strip_tags( trim( $genericTransationObject -> cost ) ) );
				    $pairName												= urldecode( strip_tags( trim( $genericTransationObject -> pair ) ) );
				    $ordertxid												= urldecode( strip_tags( trim( $genericTransationObject -> ordertxid ) ) );
				    $krakenOrderTypeLabel									= urldecode( strip_tags( trim( $genericTransationObject -> ordertype ) ) );
				    $margin													= urldecode( strip_tags( trim( $genericTransationObject -> margin ) ) );
				    $ledgers												= urldecode( strip_tags( trim( $genericTransationObject -> ledgers ) ) );
				    
				    // @Task - write code to convert currencies between USD and BTC and the current type - use amount objects
				    
				    $baseCurrency											= new AssetInfo();
				    $quoteCurrency											= new AssetInfo();
				    
				    $krakenCurrencyPair										= new KrakenCurrencyPair();
				    
				    $responseObject											= $krakenCurrencyPair -> instantiateKrakenCurrencyPairUsingPairName($pairName, $dbh);
				    
				    if ($responseObject['foundKrakenCurrencyPairID'] == true && $responseObject['instantiatedKrakenCurrencyPairObject'] == true)
				    {
						$baseCurrency										= $krakenCurrencyPair -> getBaseCurrency();
						$quoteCurrency										= $krakenCurrencyPair -> getQuoteCurrency();    
				    }
				    else
				    {
					    errorLog("Unrecognized Kraken Currency Pair: $pairName");
					    exit();
				    }
				    
				    // not provided
				    
					$assetTypeID											= $baseCurrency -> getAssetTypeID();
				    $nativeCurrencyID										= $quoteCurrency -> getAssetTypeID();
				    
				    $assetType												= $baseCurrency -> getAssetTypeLabel();
				    $nativeCurrency											= $quoteCurrency -> getAssetTypeLabel();
				    
				    // @Task - it may be better to compact the array rather than have index gaps - write a function that checks to see if a value already exists in array, and if not, adds it
				    $currenciesToProcess[$assetTypeID]						= $assetTypeID;
				    $currenciesToProcess[$nativeCurrencyID]					= $nativeCurrencyID;
				    
				    $baseCurrencyLedgerIDValue								= "";
				    $quoteCurrencyLedgerIDValue								= "";
				    
				    $ledgerValues											= splitCommaSeparatedString($ledgers);
				    
				    if (count($ledgerValues) == 2)
				    {
						$baseCurrencyLedgerIDValue							= trim($ledgerValues[0]);
						$quoteCurrencyLedgerIDValue							= trim($ledgerValues[1]);
				    }
				    
				    $transactionSourceName									= getTransactionSourceTypeLabelFromEnumValue($transactionSourceID, $dbh);
					
					$transactionTypeID										= getEnumValueTransactionType($transactionType, $dbh);
					$krakenOrderTypeID										= getEnumValueKrakenOrderType($krakenOrderTypeLabel, $dbh);
					
					// check for global transaction ID
					
					error_log("getGlobalTransactionIdentificationRecordID for $arrayIndex $accountID, $assetTypeID, $nativeTransactionIDValue, $transactionSourceID, $globalCurrentDate, $sid");
					
				    $globalTransactionIDTestResults							= getGlobalTransactionIdentificationRecordID($accountID, $assetTypeID, $nativeTransactionIDValue, $transactionSourceID, $globalCurrentDate, $sid, $dbh);
				    
				    if ($globalTransactionIDTestResults['foundNativeTransactionForAccount'] == false)
					{
						error_log("not found $arrayIndex");
						
						$returnValue[$nativeTransactionIDValue]["existingRecordFound"]	= false;
						// create one if not found
						$globalTransactionCreationResults					= createGlobalTransactionIdentificationRecord($accountID, $assetTypeID, $nativeTransactionIDValue, $transactionSourceID, $globalCurrentDate, $sid, $dbh);
			
						if ($globalTransactionCreationResults['createdGlobalTransactionIdentificationRecord'] == true)
						{
							$returnValue[$nativeTransactionIDValue]["createdGTIR"]		= true;
							
							$globalTransactionIdentifierRecordID			= $globalTransactionCreationResults['globalTransactionIdentificationRecordID'];
							$profitStanceTransactionIDValue					= $globalTransactionCreationResults['profitStanceTransactionIDValue'];
							
							// @Task - this is where I need to use the new provider account wallet idea of 
							
							$providerAccountWallet							= new ProviderAccountWallet();
							
							// check for provider account wallet							
							$instantiationResult							= $providerAccountWallet -> instantiateAccountWalletObjectForAccountByAssetTypeIDAndTransactionSourceID($accountID, $assetTypeID, $transactionSourceID, $dbh);
							
							if ($instantiationResult['instantiatedWallet'] == false)
							{
								// create wallet if not found
								$providerAccountWallet -> createAccountWalletObject($accountID, $assetTypeID, $assetType, $accountID, "$accountID-$transactionSourceID-$assetType", $walletTypeID, "Private Ledger Based Wallet", $transactionSourceID, $transactionSourceName, $globalCurrentDate, $sid, $dbh);
							}
							
							$providerWalletID								= $providerAccountWallet -> getAccountWalletID();
							
							if ($providerWalletID > 0)
							{
								$krakenCurrencyPair							= new KrakenCurrencyPair();
								
								$krakenCurrencyPair -> instantiateKrakenCurrencyPairUsingPairName($pairName, $dbh);
								
								// create kraken trade transaction object and write it
								$krakenTradeTransaction 					= new KrakenTradeTransaction();
								
								$krakenTradeTransaction -> setData(0, $globalTransactionIdentifierRecordID, $providerWalletID, 0, $nativeTransactionIDValue, $ordertxid, $krakenCurrencyPair -> getPairID(), $pairName, $transactionTimestamp, $transactionTypeID, $transactionType, $krakenOrderTypeID, $krakenOrderTypeLabel, $spotPriceInQuoteCurrency, $transactionCostInQuoteCurrencyNotIncludingFees, $transactionFeeInQuoteCurrency, $transactionVolumeInBaseCurrency, $margin, $misc, $baseCurrencyLedgerIDValue, $quoteCurrencyLedgerIDValue, $dbh);
								
								$krakenTradeTransaction -> writeToDatabase($dbh);
								
								$ipClientAccountTransaction					= new IPClientAccountTransaction();
								
								$returnValue['transactions'][$nativeTransactionIDValue]['instantiation'] = $ipClientAccountTransaction -> instantiateByNativeTransactionIDValue($accountID, $userEncryptionKey, $nativeTransactionIDValue, $sid, $dbh);
								
								if ($returnValue['transactions'][$nativeTransactionIDValue]['instantiation']['ipTransactionFound'] == false)
								{
								
									$authorID								= $accountID; 
									
									$transactionRecordID					= $krakenTradeTransaction -> getKrakenTransactionRecordID();
									
									// @task - must be converted to base currency price and USD, plus calculate buy and sell prices
									
									$cryptoCurrencyPriceAtTimeOfTransaction	= new IPClientAccountCryptoCurrencyPriceObject();
									 
									$fromAddressValue						= "";
									$toAddressValue							= "";
									
									$fromAddressCurrencyType				= "";
									$toAddressCurrencyType					= "";
									
									$fromAddressCurrencyTypeID				= 0;
									$toAddressCurrencyTypeID				= 0;
									
									// Check to see if the quote currency is USD
									// if not, call the API to get the exchange price for USD
									
									$feeAmountCrypto						= 0;
									$feeAmountNative						= 0;
									$feeAmountUSD							= 0;
										
									$totalTransactionAmountWFCrypto			= 0;
									$totalTransactionAmountWFNative			= 0;
									$totalTransactionAmountWFUSD			= 0;
									
									$totalTransactionAmountCrypto			= 0;
									$totalTransactionAmountNative			= 0;
									$totalTransactionAmountUSD				= 0;
									
									if ($krakenCurrencyPair -> getQuoteCurrencyID() == 2)
									{
										$feeAmountNative								= $transactionFeeInQuoteCurrency;
										$feeAmountUSD									= $transactionFeeInQuoteCurrency;
										$feeAmountCrypto								= calculateFeeInBaseCurrencyForFeePaidInQuoteCurrency($transactionFeeInQuoteCurrency, $transactionCostInQuoteCurrencyNotIncludingFees, $spotPriceInQuoteCurrency, $transactionVolumeInBaseCurrency);
										
										$totalTransactionAmountWFCrypto					= $transactionVolumeInBaseCurrency;
										$totalTransactionAmountWFNative					= $transactionCostInQuoteCurrencyNotIncludingFees;
										$totalTransactionAmountWFUSD					= $transactionCostInQuoteCurrencyNotIncludingFees;
									}
									else
									{
										errorLog("quote currency is not USD - transaction calculation error for transaction $nativeTransactionIDValue and user $accountID: must implement API to calculate amount in USD");
										
										$feeAmountNative								= $transactionFeeInQuoteCurrency;
										$feeAmountUSD									= 0;
										$feeAmountCrypto								= $transactionFeeInQuoteCurrency;
										
										$totalTransactionAmountWFCrypto					= $transactionVolumeInBaseCurrency;
										$totalTransactionAmountWFNative					= $transactionCostInQuoteCurrencyNotIncludingFees;
										$totalTransactionAmountWFUSD					= 0;
									}

									
									// https://support.kraken.com/hc/en-us/articles/203053186-Currency-Exchange-Buying-Selling-and-Currency-Pair-Selection
									
									// If the "buy" button is selected and currency pair X/Y is selected, then currency X will be bought and currency Y sold.
									// If the "sell" button is selected and currency pair x/y is selected, then currency X will be sold and currency Y will be bought.
									
									
									if ($transactionTypeID == 1)
									{
										$toAddressValue									= $baseCurrencyLedgerIDValue;
										$fromAddressValue								= $quoteCurrencyLedgerIDValue;
										
										$toAddressCurrencyTypeID						= $krakenCurrencyPair -> getBaseCurrencyID();
										$fromAddressCurrencyTypeID						= $krakenCurrencyPair -> getQuoteCurrencyID();
										
										$toAddressCurrencyType							= $krakenCurrencyPair -> getBaseCurrency() -> getAssetTypeLabel();
										$fromAddressCurrencyType						= $krakenCurrencyPair -> getQuoteCurrency() -> getAssetTypeLabel();
										// THIS IS A BUY - THE TOTAL AMOUNT IS THE TRANSACTION AMOUNT WITHOUT FEE + FEE AMOUNT FOR EACH
										
										$totalTransactionAmountCrypto					= $totalTransactionAmountWFCrypto; // + $feeAmountCrypto;
										$totalTransactionAmountNative					= $totalTransactionAmountWFNative; // + $feeAmountNative;
										$totalTransactionAmountUSD						= $totalTransactionAmountWFUSD; // + $feeAmountUSD;
									}
									else
									{
										$fromAddressValue								= $baseCurrencyLedgerIDValue;
										$toAddressValue									= $quoteCurrencyLedgerIDValue;
										
										$fromAddressCurrencyTypeID						= $krakenCurrencyPair -> getBaseCurrencyID();
										$toAddressCurrencyTypeID							= $krakenCurrencyPair -> getQuoteCurrencyID();
										
										$fomAddressCurrencyType							= $krakenCurrencyPair -> getBaseCurrency() -> getAssetTypeLabel();
										$toAddressCurrencyType						    = $krakenCurrencyPair -> getQuoteCurrency() -> getAssetTypeLabel();
										
										// THIS IS A SELL - THE TOTAL AMOUNT IS THE TRANSACTION AMOUNT WITHOUT FEE - FEE AMOUNT FOR EACH
										$totalTransactionAmountCrypto					= $totalTransactionAmountWFCrypto; // - $feeAmountCrypto;
										$totalTransactionAmountNative					= $totalTransactionAmountWFNative; // - $feeAmountNative;
										$totalTransactionAmountUSD						= $totalTransactionAmountWFUSD; // - $feeAmountUSD;
										
										$totalTransactionAmountWFCrypto					= $totalTransactionAmountWFCrypto * -1;
										$feeAmountCrypto									= $feeAmountCrypto * -1;
										
									}
									
									$fromAddress											= new IPTransactionAddressObject();
									$toAddress											= new IPTransactionAddressObject();
																		
									$fromAddress -> setData($addressCallbackURL, $fromAddressCurrencyType, $fromAddressCurrencyTypeID, $accountID, $resourcePath, $addressType, $addressTypeID, $fromAddressValue, $accountID, $globalCurrentDate, 0, 0, $sid);
									
									$toAddress -> setData($addressCallbackURL, $toAddressCurrencyType, $toAddressCurrencyTypeID, $accountID, $resourcePath, $addressType, $addressTypeID, $toAddressValue, $accountID, $globalCurrentDate, 0, 0, $sid);
									
									$totalTransactionAmount								= new IPTransactionAmountObject();
									$transactionAmountWithoutFees						= new IPTransactionAmountObject();
									$feeAmount											= new IPTransactionAmountObject();
									
									$totalTransactionAmount -> setData($accountID, $totalTransactionAmountCrypto, $totalTransactionAmountNative, $totalTransactionAmountUSD, $accountID, $globalCurrentDate, 0, 0, $sid, 1, "Total Transaction Amount");
									
									$transactionAmountWithoutFees -> setData($accountID, $totalTransactionAmountWFCrypto, $totalTransactionAmountWFNative, $totalTransactionAmountWFUSD, $accountID, $globalCurrentDate, 0, 0, $sid, 2, "Transaction Amount Without Fees");

									$feeAmount -> setData($accountID, $feeAmountCrypto, $feeAmountNative, $feeAmountUSD, $accountID, $globalCurrentDate, 0, 0, $sid, 3, "Fee Amount");
									
									
									$ipClientAccountTransaction -> setData($accountID, $assetType, $assetTypeID, $authorID, $globalCurrentDate, $cryptoCurrencyPriceAtTimeOfTransaction, $feeAmount, $fromAddress, $globalCurrentDate, $nativeCurrency, $nativeCurrencyID, $nativeTransactionIDValue, $numberOfConfirmations, $profitStanceTransactionIDValue, $providerNotes, $providerWalletID, $resourcePath, $sid, $toAddress, $totalTransactionAmount, $transactionAmountWithoutFees, $transactionRecordID, $transactionStatus, $transactionStatusID, $transactionType, $transactionTypeID, $transactionTimestamp, $transactionSourceName, $transactionSourceID, $walletTypeID, $globalTransactionIdentifierRecordID);
									
									$returnValue['transactions'][$nativeTransactionIDValue]['creation'] = $ipClientAccountTransaction -> createTransactionRecord($userEncryptionKey, $dbh);
									if ($returnValue['transactions'][$nativeTransactionIDValue]['creation']['ipTransactionCreated'] == true)
									{
										$returnValue[$nativeTransactionIDValue]["newTransactionCreated"]									= true;
										
										$newTransactionID 																					= $ipClientAccountTransaction -> getTransactionRecordID();
										
										// populate amount records
										
										$totalTransactionAmount -> setIPTransactionRecordID($newTransactionID);
										$returnValue['transactions'][$nativeTransactionIDValue]['transactionAmounts'][1]['creation'] 		= $totalTransactionAmount -> createTransactionAmountObject($dbh);
										$returnValue['transactions'][$nativeTransactionIDValue]['transactionAmounts'][1]['updateObjectID'] 	= $ipClientAccountTransaction -> updateTransactionAmountObjectIDForType($totalTransactionAmount -> getIPTransactionAmountObjectID(), 1, $globalCurrentDate, $dbh);
										
										$ipClientAccountTransaction -> setTotalTransactionAmount($totalTransactionAmount);
										
										$transactionAmountWithoutFees -> setIPTransactionRecordID($newTransactionID);
										$returnValue['transactions'][$nativeTransactionIDValue]['transactionAmounts'][2]['creation']		= $transactionAmountWithoutFees -> createTransactionAmountObject($dbh);
										$returnValue['transactions'][$nativeTransactionIDValue]['transactionAmounts'][2]['updateObjectID'] 	= $ipClientAccountTransaction -> updateTransactionAmountObjectIDForType($transactionAmountWithoutFees -> getIPTransactionAmountObjectID(), 2, $globalCurrentDate, $dbh);
										
										$ipClientAccountTransaction -> setTransactionAmountWithoutFees($transactionAmountWithoutFees);
										
										$feeAmount -> setIPTransactionRecordID($newTransactionID);
										$returnValue['transactions'][$nativeTransactionIDValue]['transactionAmounts'][3]['creation']		= $feeAmount -> createTransactionAmountObject($dbh);
										$returnValue['transactions'][$nativeTransactionIDValue]['transactionAmounts'][3]['updateObjectID'] 	= $ipClientAccountTransaction -> updateTransactionAmountObjectIDForType($feeAmount -> getIPTransactionAmountObjectID(), 3, $globalCurrentDate, $dbh);
										
										$ipClientAccountTransaction -> setFeeAmount($feeAmount);
										
										
										// populate address records
										
										$fromAddress -> setIpTransactionRecordID($newTransactionID);
										$returnValue['transactions'][$nativeTransactionIDValue]['transactionAddresses']['from']['creation']			= $fromAddress -> createTransactionAddressObject($dbh);
										$returnValue['transactions'][$nativeTransactionIDValue]['transactionAddresses']['from']['updateObjectID']	= $ipClientAccountTransaction -> updateTransactionAddressObjectIDForType($fromAddress -> getIpTransactionAddressObjectID(), 1, $globalCurrentDate, $dbh);
										
										$ipClientAccountTransaction -> setFromAddress($fromAddress);
										
										$toAddress -> setIpTransactionRecordID($newTransactionID);
										$returnValue['transactions'][$nativeTransactionIDValue]['transactionAddresses']['to']['creation']			= $toAddress -> createTransactionAddressObject($dbh);
										$returnValue['transactions'][$nativeTransactionIDValue]['transactionAddresses']['to']['updateObjectID']		= $ipClientAccountTransaction -> updateTransactionAddressObjectIDForType($toAddress -> getIpTransactionAddressObjectID(), 2, $globalCurrentDate, $dbh);
										
										$ipClientAccountTransaction -> setToAddress($toAddress);
										
										$cryptoCurrencyPriceAtTimeOfTransaction -> setData($accountID, $assetTypeID, $assetType, $authorID, 0, $globalCurrentDate, $newTransactionID, 0, $spotPriceInQuoteCurrency, $transactionTimestamp, $sid);
										
										$cryptoCurrencyPriceAtTimeOfTransaction -> writeToDatabase($dbh);
									}	
								}
							}
						}	
					}
					else
					{
						error_log("found $arrayIndex");
						
						$returnValue[$nativeTransactionIDValue]["existingRecordFound"]	= true;
						$returnValue[$nativeTransactionIDValue]["newTransactionCreated"]= false;
					}

					errorLog("completed array index $arrayIndex");
				}
				
				if ($includeDetailReporting != true)
				{
					$returnValue														= array();		
				}
				
				$returnValue["csvDataImported"]											= "complete";
	    	}
	    	else
	    	{
		    	errorLog("ERROR: JSON object empty or $name not found");	
	    	}	
    	}
    	catch (Exception $e)
    	{
	    	errorLog("ERROR: $name not found in JSON object");	
    	}
    	
		foreach ($currenciesToProcess as $processAssetTypeID => $processAssetTypeName)
    	{
	    	performFIFOTransactionCalculationsOnIPTransactions($accountID, $processAssetTypeID, $processAssetTypeID, $processAssetTypeName, $transactionSourceID, $globalCurrentDate, $sid, $dbh);	
    	}

        return $returnValue;
    }
*/
	
	function writeKrakenAssociatePositionTransactionsWithClosingTransactionsRecord($accountID, $positionTransactionID, $closingTradeTransactionIDValue, $closingTradeTransactionID, $userEncryptionKey, $globalCurrentDate, $sid, $dbh)
	{
		$responseObject																						= array();
		$responseObject['wroteKrakenAssociatePositionTransactionsWithClosingTransactionsRecord']				= false;
		
		errorLog("INSERT INTO KrakenAssociatePositionTransactionsWithClosingTransactions
(
	FK_AccountID,
	FK_PositionTransactionID,
	encryptedClosingTradeTransactionIDValue,
	FK_ClosingTradeTransactionID,
	encryptedSid
)
VALUES
(
	$accountID,
	$positionTransactionID,
	AES_ENCRYPT('$closingTradeTransactionIDValue', UNHEX(SHA2('$userEncryptionKey',512))),
	$closingTradeTransactionID,
	AES_ENCRYPT('$sid', UNHEX(SHA2('$userEncryptionKey',512)))
)");
		
		try
		{	
			$writeKrakenAssociatePositionTransactionsWithClosingTransactionsRecord							= $dbh->prepare("INSERT INTO KrakenAssociatePositionTransactionsWithClosingTransactions
(
	FK_AccountID,
	FK_PositionTransactionID,
	encryptedClosingTradeTransactionIDValue,
	FK_ClosingTradeTransactionID,
	encryptedSid
)
VALUES
(
	:accountID,
	:positionTransactionID,
	AES_ENCRYPT(:closingTradeTransactionIDValue, UNHEX(SHA2(:userEncryptionKey,512))),
	:closingTradeTransactionID,
	AES_ENCRYPT(:sid, UNHEX(SHA2(:userEncryptionKey,512)))
)");

			$writeKrakenAssociatePositionTransactionsWithClosingTransactionsRecord -> bindValue(':accountID', $accountID);
			$writeKrakenAssociatePositionTransactionsWithClosingTransactionsRecord -> bindValue(':positionTransactionID', $positionTransactionID);
			$writeKrakenAssociatePositionTransactionsWithClosingTransactionsRecord -> bindValue(':closingTradeTransactionIDValue', $closingTradeTransactionIDValue);
			$writeKrakenAssociatePositionTransactionsWithClosingTransactionsRecord -> bindValue(':closingTradeTransactionID', $closingTradeTransactionID);
			$writeKrakenAssociatePositionTransactionsWithClosingTransactionsRecord -> bindValue(':sid', $sid);
			$writeKrakenAssociatePositionTransactionsWithClosingTransactionsRecord -> bindValue(':userEncryptionKey', $userEncryptionKey);
		
			if ($writeKrakenAssociatePositionTransactionsWithClosingTransactionsRecord -> execute())
			{
				$responseObject['wroteKrakenAssociatePositionTransactionsWithClosingTransactionsRecord']		= true;
			}			
		}
	    catch (PDOException $e) 
	    {
	    		errorLog("Unable to writeKrakenAssociatePositionTransactionsWithClosingTransactionsRecord ".$e->getMessage());	
		}
		
		return $responseObject;
	}
	
	// END KRAKEN functions
	
	// ------ BINANCE FUNCTIONS ------
	
/*
	function getBinanceTransactionHistoryForUserViaAPI($resultArray, $exchangeTileID, $startPosition, $baseCurrency, $baseCurrencyAssetTypeID, $baseCurrencyAssetType, $quoteCurrency, $quoteCurrencyAssetTypeID, $quoteCurrencyAssetType, $baseCurrencyWalletID, $quoteCurrencyWalletID, $cryptoCurrencyTypesImported, $name, $accountID, $userEncryptionKey, $transactionSourceID, $transactionSourceName, $walletTypeID, $walletTypeName, $includeDetailReporting, $dataImportEventRecordID, $globalCurrentDate, $sid, $dbh)
	{
    	$returnValue   														= array();
  	
		$tradesArray														= $resultArray;
    	$numberProcessed													= 0;
    				
		//			create data import stage for currency pair
						
		//		start import loops
				
		//			in function, create response array which will track your results for this process
		//				set status values in array to false, number of transactions to 0, and lastTransactionID in response array to null
				
		//			for each transaction in loop
					
		//				parse data
						
						
		//				create transaction object
		//				set data
						
		//				check the global transaction ID database - has this already been imported?
						
		//				if not
		//					create global transaction ID record
						
		//					write transaction object to database
						
		//					get autoincrement ID for transaction 
							
		//					update global transaction ID record with native transaction ID
						
		//					write to response array [transactionID][importedSuccessfully] = true
						
		//					write to response array [lastTransactionID] = this object's transaction ID - thus when the last transaction in the set is processed, you have the last transaction's ID and can star the next interation
		//					increment the total number of transactions for loop
					
		//			when done with this loop - 
					
		//			set the total number of transactions in this set
					
		//			return array
					
		//		set number of transactions for symbol = number of transactions for symbol + number 
				
		//		use last transation ID value from completed loop to start the next loop
				
		//			repeat	

	    try
	    {
		    errorLog("count ".count($tradesArray));
			$lastTransactionID												= 0;    
		    
		    foreach ($tradesArray as $trade)
		    {
		    
				$transactionPairName										= $trade -> symbol;
				$txIDValue													= $trade -> id;
				// save lastTransactionID for loading next batch of 1000 transactions
				$lastTransactionID											= $txIDValue;
				$orderTxID													= $trade -> orderId;
				$transactionPrice											= $trade -> price;
			    $transactionQty												= $trade -> qty;
			    $transactionQuoteQty										= $trade -> quoteQty;
			    $transactionCommission										= $trade -> commission;
			    $transactionCommissionAsset									= $trade -> commissionAsset;
			    $transactionIsBuyer 										= $trade -> isBuyer;
			    $transactionIsMaker 										= $trade -> isMaker;
			    $transactionIsBestMatch 									= $trade -> isBestMatch;
			    
			    $transactionType											= "sell";
			    $isDebit													= 1;
			    
			    if ($transactionIsBuyer == 1 || empty($transactionIsMaker))
			    {
					$transactionType										= "buy";  
					$isDebit												= 0;
			    }

			    $transactionIsMaker											= $trade -> isMaker;
				$transactionIsBestMatch										= $trade -> isBestMatch;
			    $transactionTimestamp										= $trade -> time;	// transaction timestamp						 
				$transactionTime										    = date("Y-m-d h:i:s", ($transactionTimestamp/1000));
				$creationDate												= $globalCurrentDate; 
				
				if (empty($transactionIsBuyer))
				{
					$transactionIsBuyer										= 0;
				}
				
				if (empty($transactionIsMaker))
				{
					$transactionIsMaker										= 0;
				}
				
				if (empty($transactionIsBestMatch))
				{
					$transactionIsBestMatch									= 0;
				}
				
				$feeAssetTypeID											 	= getEnumValueAssetType($transactionCommissionAsset, $dbh);
				
				$baseToQuoteCurrencySpotPrice								= 0;	// quoteCurrencySpotPriceAtTimeOfTransaction					
				$baseToUSDCurrencySpotPrice									= 0;	// spotPriceAtTimeOfTransaction
				$btcSpotPriceAtTimeOfTransaction							= 0;	// btcPriceAtTimeOfTransaction
				$feeCurrencySpotPriceAtTimeOfTransaction					= 0;
				
				$cascadeRetrieveSpotPriceResponseObject						= getSpotPriceForAssetPairUsingSourceCascade($baseCurrencyAssetTypeID, 2, $transactionTime, 14, "CoinGecko price by date", $dbh);
					
				if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
				{
					$baseToQuoteCurrencySpotPrice							= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
					$baseToUSDCurrencySpotPrice								= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
				}
				
				if ($baseCurrencyAssetTypeID == 1)
				{
					$btcSpotPriceAtTimeOfTransaction						= $baseToUSDCurrencySpotPrice;	
				}
				else
				{
					$cascadeRetrieveSpotPriceResponseObject					= getSpotPriceForAssetPairUsingSourceCascade(1, 2, $transactionTime, 14, "CoinGecko price by date", $dbh);
					
					if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
					{
						$btcSpotPriceAtTimeOfTransaction					= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
					}	
				}
				
				// get spot price for fee currency in USD
				
				$cascadeRetrieveSpotPriceResponseObject						= getSpotPriceForAssetPairUsingSourceCascade($feeAssetTypeID, 2, $transactionTime, 14, "CoinGecko price by date", $dbh);
					
				if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
				{
					$feeCurrencySpotPriceAtTimeOfTransaction				= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
				}
				
				$feeAmountInUSD											 	= $feeCurrencySpotPriceAtTimeOfTransaction * $transactionCommission; // fee amount in USD
				
				$transactionAmountInUSD										= $baseToUSDCurrencySpotPrice * $transactionQty;	// usdAmount
				$transactionAmountMinusFeeInUSD								= $transactionAmountInUSD - $feeAmountInUSD;
				$sentQuantity												= $transactionAmountInUSD;
				$amount														= $transactionAmountInUSD;
				
				error_log("getBinanceTransactionHistoryForUserViaAPI $transactionTime $transactionPairName $transactionPrice $transactionQty $transactionQuoteQty $transactionCommission $transactionCommissionAsset $transactionIsBuyer $transactionIsMaker $transactionIsBestMatch");
				
			    // @Task - write code to convert currencies between USD and BTC and the current type - use amount objects
				    
			    
			    
			    // Binance - every time I read a spot price, write it to the daily spot price table
			    setDailyPriceData($baseCurrencyAssetTypeID, $quoteCurrencyAssetTypeID, $transactionTimestamp, $transactionPrice, $transactionSourceID, $globalCurrentDate, $sid, $dbh);
			    
			    // @Task - it may be better to compact the array rather than have index gaps - write a function that checks to see if a value already exists in array, and if not, adds it
			    
			    if (isset($cryptoCurrencyTypesImported[$baseCurrencyAssetTypeID][$quoteCurrencyAssetTypeID]))
				{
					$currentCount											= $cryptoCurrencyTypesImported[$baseCurrencyAssetTypeID][$quoteCurrencyAssetTypeID];
					$currentCount++;
					
					$cryptoCurrencyTypesImported[$baseCurrencyAssetTypeID][$quoteCurrencyAssetTypeID]			= $currentCount;
				}
				else
				{
					$cryptoCurrencyTypesImported[$baseCurrencyAssetTypeID][$quoteCurrencyAssetTypeID]			= 1;	
				}
				
				$transactionAssetTypeID										= getEnumValueAssetType($transactionCommissionAsset, $dbh);
				$transactionTypeID											= getEnumValueTransactionType($transactionType, $dbh);
				
				// check for global transaction ID
				
				error_log("getGlobalTransactionIdentificationRecordID for $accountID, $baseCurrencyAssetTypeID, $transactionSourceID, $globalCurrentDate, $sid");
				
			    $globalTransactionIDTestResults								= getGlobalTransactionIdentificationRecordID($accountID, $baseCurrencyAssetTypeID, $txIDValue, $transactionSourceID, $globalCurrentDate, $sid, $dbh);
			    
			    // here
			    
			    if ($globalTransactionIDTestResults['foundNativeTransactionForAccount'] == false)
				{
					error_log("not found $txIDValue");
					
					$returnValue[$txIDValue]["existingRecordFound"]		= false;
					
					// create one if not found
					$globalTransactionCreationResults						= createGlobalTransactionIdentificationRecord($accountID, $exchangeTileID, $baseCurrencyAssetTypeID, $txIDValue, $transactionSourceID, $globalCurrentDate, $sid, $dbh);
			
					if ($globalTransactionCreationResults['createdGlobalTransactionIdentificationRecord'] == true)
					{
						errorLog("createGlobalTransactionIdentificationRecord success");
						
						$returnValue[$txIDValue]["createdGTIR"]				= true;
							
						$globalTransactionIdentifierRecordID				= $globalTransactionCreationResults['globalTransactionIdentificationRecordID'];
						$profitStanceTransactionIDValue						= $globalTransactionCreationResults['profitStanceTransactionIDValue'];
							
						// @Task - this is where I need to use the new provider account wallet idea of 
							
						$providerAccountWallet								= new ProviderAccountWallet();
							
						// check for provider account wallet							
						$instantiationResult								= $providerAccountWallet -> instantiateAccountWalletObjectForAccountByAssetTypeIDAndTransactionSourceID($accountID, $baseCurrencyAssetTypeID, $transactionSourceID, $dbh);
							
						if ($instantiationResult['instantiatedWallet'] == false)
						{
							// create wallet if not found
							$providerAccountWallet -> createAccountWalletObject($accountID, $baseCurrencyAssetTypeID, $baseCurrencyAssetType, $accountID, "$accountID-$transactionSourceID-$baseCurrencyAssetType", $walletTypeID, "Private Ledger Based Wallet", $transactionSourceID, $transactionSourceName, $globalCurrentDate, $sid, $dbh);
						}
							
						$providerWalletID									= $providerAccountWallet -> getAccountWalletID();
						
						errorLog("providerWalletID $providerWalletID");
							
						if ($providerWalletID > 0)
						{
							// create binance trade transaction object and write it
							
							$binanceTradeTransaction 						= new BinanceTradeTransaction();
													
							$binanceTradeTransaction -> setData($accountID, $exchangeTileID, 0, $globalTransactionIdentifierRecordID, $transactionSourceID, $txIDValue, $orderTxID, $transactionPairName, $transactionTime, $transactionPrice, $transactionQty, $transactionQuoteQty, $transactionCommission, $transactionCommissionAsset, $transactionIsBuyer, $transactionIsMaker, $transactionIsBestMatch, $walletTypeID, $walletTypeName, $transactionTypeID, $providerWalletID, $baseCurrencyWalletID, $quoteCurrencyWalletID, $baseCurrencyAssetTypeID, $quoteCurrencyAssetTypeID, $transactionTimestamp, $creationDate, $baseToQuoteCurrencySpotPrice, $baseToUSDCurrencySpotPrice, $btcSpotPriceAtTimeOfTransaction, $transactionAmountInUSD, $feeAmountInUSD, $isDebit, $userEncryptionKey, $globalCurrentDate, $sid, $dbh);																						
							
							$writeBinanceRecordResponseObject				= $binanceTradeTransaction -> writeToDatabase($userEncryptionKey, $dbh);
							
							if ($writeBinanceRecordResponseObject['wroteToDatabase'] == true)
							{
								error_log("Transaction inserted into BinanceTradeTransactions table");
								// now that the transaction has been created, create the association record for the closing array if size > 0
								
								$profitStanceLedgerEntry					= new ProfitStanceLedgerEntry();
								$profitStanceLedgerEntry -> setData($accountID, $baseCurrencyAssetTypeID, $baseCurrencyAssetType, 4, "Binance", $exchangeTileID, $globalTransactionIdentifierRecordID, $transactionTime, $transactionQty, $dbh);
								
								$writeProfitStanceLedgerEntryRecordResponseObject		= $profitStanceLedgerEntry -> writeToDatabase($dbh);
								
								if ($writeProfitStanceLedgerEntryRecordResponseObject['wroteToDatabase'] == true)
								{
									error_log("wrote profitStance ledger entry $accountID, $baseCurrencyAssetTypeID, $baseCurrencyAssetType, 4, \"Binance\", $exchangeTileID, $globalTransactionIdentifierRecordID, $transactionTime, $transactionQty to the database.");
								}
								else
								{
									error_log("could not write profitStance ledger entry $accountID, $baseCurrencyAssetTypeID, $baseCurrencyAssetType, 4, \"Binance\", $exchangeTileID, $globalTransactionIdentifierRecordID, $transactionTime, $transactionQty to the database.");	
								}
								
								// update native record ID in GTRID table
								$setNativeTransactionRecordIDResult			= setNativeTransactionRecordIDForGlobalTransactionIndentificationRecordID($accountID, $binanceTradeTransaction -> getBinanceTransactionRecordID(), $globalTransactionIdentifierRecordID, $globalCurrentDate, $sid, $dbh);
															
							}
						}
					}
					
					$numberProcessed++;	
				}
				else
				{
					error_log("found $txIDValue");
					
					$returnValue[$txIDValue]["existingRecordFound"]			= true;
					$returnValue[$txIDValue]["newTransactionCreated"]		= false;
				}

				errorLog("completed array index $txIDValue");
		    }
		
			if ($includeDetailReporting != true)
			{
				$returnValue												= array();		
			}
			
			$returnValue["binanceDataImported"]								= "complete";
			$returnValue['numberProcessed']									= $numberProcessed;
			$returnValue['cryptoCurrencyTypesImported']						= $cryptoCurrencyTypesImported;
			$returnValue['lastTransactionID']								= $lastTransactionID;
			
	    }
	    catch (Exception $e)
	    {
		   	errorLog("ERROR: array parsing error");	
	    }
	    
	    errorLog(json_encode($returnValue));

        return $returnValue;
    }	
*/
	
	
    
	// END BINANCE FUNCTIONS
	
	// COMMON ACCOUNTING TYPE FUNCTIONS
	
	function performFIFOTransactionCalculationsCommonTransactions($liuAccountID, $userEncryptionKey, $baseCurrencyTypeID, $quoteCurrencyTypeID, $quoteCurrencyAssetTypeLabel, $transactionSourceID, $globalCurrentDate, $sid, $dbh)
	{
		$responseObject														= array();
		$responseObject['completedFIFOCalculations']						= false;
		
		try
		{		
			$getKrackenLedgerAndTradeRecords								= $dbh -> prepare("SELECT
	KrakenLedgerTransactions.ledgerRecordID,
	KrakenLedgerTransactions.FK_GlobalTransactionRecordID,
	KrakenLedgerTransactions.FK_AccountID AS authorID,
	KrakenLedgerTransactions.FK_AccountID AS accountID,
	KrakenLedgerTransactions.FK_ProviderAccountWalletID,
	CASE
		WHEN KrakenLedgerTransactions.FK_LedgerEntryTypeID = 1 THEN 7
		WHEN KrakenLedgerTransactions.FK_LedgerEntryTypeID = 2 THEN 8
		WHEN 
			KrakenLedgerTransactions.FK_LedgerEntryTypeID > 2 AND
			KrakenLedgerTransactions.isDebit = 0 THEN 1
		WHEN 
			KrakenLedgerTransactions.FK_LedgerEntryTypeID > 2 AND
			KrakenLedgerTransactions.isDebit = 1 THEN 4
		ELSE 14
	END AS transactionTypeID,
	KrakenLedgerTransactions.FK_AssetClassID,
	AES_DECRYPT(KrakenLedgerTransactions.encryptedPairName, UNHEX(SHA2(:userEncryptionKey,512))) AS pairName,
	KrakenLedgerTransactions.FK_KrakenCurrencyPairID,
	baseCurrency.assetTypeID AS baseCurrencyID,
	baseCurrency.assetTypeLabel AS baseCurrencyName,
	quoteCurrency.assetTypeID AS quoteSpotPriceCurrencyID,
	quoteCurrency.assetTypeLabel AS quoteSpotPriceCurrencyName,
	ABS(KrakenLedgerTransactions.amount) AS amount,
	KrakenLedgerTransactions.fee,
	KrakenLedgerTransactions.balance,
	KrakenLedgerTransactions.isDebit,
	KrakenLedgerTransactions.baseToQuoteCurrencySpotPrice,
	KrakenLedgerTransactions.baseToUSDCurrencySpotPrice,
	KrakenLedgerTransactions.FK_CurrencyPriceValueSourceID,
	KrakenLedgerTransactions.btcSpotPriceAtTimeOfTransaction,
	KrakenLedgerTransactions.FK_TransactionRecordID,
	KrakenLedgerTransactions.FK_BaseCurrencyWalletID,
	KrakenLedgerTransactions.FK_QuoteCurrencyWalletID,
	KrakenLedgerTransactions.transactionTime AS creationDate,
	KrakenLedgerTransactions.transactionTime AS transactionDate,
	KrakenLedgerTransactions.transactionTimestamp,
	AES_DECRYPT(KrakenLedgerTransactions.encryptedLedgerIDValue, UNHEX(SHA2(:userEncryptionKey,512))) AS vendorTransactionID,
	AES_DECRYPT(KrakenLedgerTransactions.encryptedRefIDValue, UNHEX(SHA2(:userEncryptionKey,512))) AS vendorRefID,
	KrakenLedgerTransactions.amount AS transactionAmountInQuoteCurrency,
	ABS(KrakenLedgerTransactions.amount * KrakenLedgerTransactions.baseToUSDCurrencySpotPrice) AS transactionAmountInUSD,
	ABS(KrakenLedgerTransactions.fee * KrakenLedgerTransactions.baseToUSDCurrencySpotPrice) AS feeAmountInUSD,
	ABS((KrakenLedgerTransactions.amount * KrakenLedgerTransactions.baseToUSDCurrencySpotPrice) + (KrakenLedgerTransactions.fee * KrakenLedgerTransactions.baseToUSDCurrencySpotPrice)) AS transactionAmountPlusFeeInUSD,
	ABS((KrakenLedgerTransactions.amount * KrakenLedgerTransactions.baseToUSDCurrencySpotPrice) - (KrakenLedgerTransactions.fee * KrakenLedgerTransactions.baseToUSDCurrencySpotPrice)) AS transactionAmountMinusFeeInUSD,
	'' AS  providerNotes,
	KrakenLedgerTransactions.FK_BaseCurrencyWalletID AS FK_SourceAddressID,
	KrakenLedgerTransactions.FK_QuoteCurrencyWalletID AS FK_DestinationAddressID,
	CASE
		WHEN KrakenLedgerTransactions.FK_LedgerEntryTypeID = 1 THEN 'Deposit'
		WHEN KrakenLedgerTransactions.FK_LedgerEntryTypeID = 2 THEN 'Withdrawal'
		WHEN 
			KrakenLedgerTransactions.FK_LedgerEntryTypeID > 2 AND
			KrakenLedgerTransactions.isDebit = 1 THEN 'Sell'
		WHEN 
			KrakenLedgerTransactions.FK_LedgerEntryTypeID > 2 AND
			KrakenLedgerTransactions.isDebit = 0 THEN 'Buy'
		ELSE 'Adjustment'
	END AS transactionTypeLabel
FROM
	KrakenLedgerTransactions
	INNER JOIN CommonCurrencyPairs ON KrakenLedgerTransactions.FK_KrakenCurrencyPairID = CommonCurrencyPairs.pairID
	INNER JOIN AssetTypes baseCurrency ON CommonCurrencyPairs.FK_BaseCurrencyID = baseCurrency.assetTypeID AND baseCurrency.languageCode = 'EN'
	INNER JOIN AssetTypes quoteCurrency ON CommonCurrencyPairs.FK_QuoteCurrencyID = quoteCurrency.assetTypeID AND quoteCurrency.languageCode = 'EN'
WHERE
	KrakenLedgerTransactions.FK_AccountID = :accountID AND
	baseCurrency.assetTypeID = :baseCurrencyID
ORDER BY
	KrakenLedgerTransactions.transactionTimestamp");
	
	// may have to use base currency, rather than currency pair, here
		
			$getFundedReceiveCryptoTransactionRecord						= $dbh -> prepare("SELECT
		Transactions.transactionID,
		Transactions.FK_AuthorID,
		Transactions.FK_AccountID,
		Transactions.FK_TransactionTypeID,
		Transactions.FK_AssetTypeID,
		Transactions.FK_SourceAddressID,
		Transactions.FK_DestinationAddressID,
		Transactions.FK_SpotPriceCurrencyID,
		Transactions.creationDate,
		Transactions.transactionDate,
		AES_DECRYPT(vendorTransactionID, UNHEX(SHA2(:userEncryptionKey,512))) AS vendorTransactionID,
		Transactions.btcQuantityTransacted,
		Transactions.usdQuantityTransacted,
		Transactions.spotPriceAtTimeOfTransaction,
		Transactions.btcPriceAtTimeOfTransaction,
		Transactions.usdTransactionAmountWithFees,
		Transactions.usdFeeAmount,
		AES_DECRYPT(encryptedProviderNotes, UNHEX(SHA2(:userEncryptionKey,512))) AS providerNotes,
		Transactions.unspentTransactionTotal,
		AES_DECRYPT(Transactions.encryptedSid, UNHEX(SHA2(:userEncryptionKey,512))) AS sid,
		assetTypes.assetTypeLabel,
		spotPriceCurrencyType.assetTypeLabel AS spotPriceCurrencyLabel
	FROM
		Transactions 
		INNER JOIN CryptoWallets ON Transactions.FK_DestinationAddressID = CryptoWallets.walletID
		INNER JOIN AssetTypes assetTypes ON Transactions.FK_AssetTypeID = assetTypes.assetTypeID AND assetTypes.languageCode = 'EN'
		INNER JOIN AssetTypes spotPriceCurrencyType ON Transactions.FK_SpotPriceCurrencyID = spotPriceCurrencyType.assetTypeID AND spotPriceCurrencyType.languageCode = 'EN'
		
	WHERE
		CryptoWallets.FK_AccountID = :accountID AND
		Transactions.isDebit = 0 AND
		Transactions.unspentTransactionTotal > 0  AND
		Transactions.FK_AssetTypeID = :assetType
	ORDER BY
		Transactions.transactionDate");
		
			$getKrackenLedgerAndTradeRecords -> bindValue(':accountID', $liuAccountID);
			$getKrackenLedgerAndTradeRecords -> bindValue(':baseCurrencyID', $baseCurrencyTypeID);
			$getKrackenLedgerAndTradeRecords -> bindValue(':userEncryptionKey', $userEncryptionKey);
		
			if ($getKrackenLedgerAndTradeRecords -> execute() && $getKrackenLedgerAndTradeRecords -> rowCount() > 0)
			{
				errorLog("began get kraken crypto transaction records ".$getKrackenLedgerAndTradeRecords -> rowCount() > 0);
				
				while ($row = $getKrackenLedgerAndTradeRecords -> fetchObject())
				{
					// here 2019-02-26 pm
					
					$ledgerRecordID											= $row -> ledgerRecordID;
					$globalTransactionIdentificationRecordID				= $row -> FK_GlobalTransactionRecordID;
					$accountID												= $row -> accountID;	
					$authorID												= $row -> authorID;
					$providerAccountWalletID								= $row -> FK_ProviderAccountWalletID; // not needed for now
					$transactionTypeID										= $row -> transactionTypeID;
					$assetClassID											= $row -> FK_AssetClassID;  // not needed
					$pairName												= $row -> pairName; // not needed
					$krakenCurrencyPairID									= $row -> FK_KrakenCurrencyPairID; // not needed
					$baseCurrencyID											= $row -> baseCurrencyID; // was assetTypeID - done
					$baseCurrencyName										= $row -> baseCurrencyName; // assetTypeName - not needed
						// $quoteSpotPriceCurrencyID						= $row -> quoteSpotPriceCurrencyID; // was spotPriceCurrencyTypeID
						// $quoteSpotPriceCurrencyName						= $row -> quoteSpotPriceCurrencyName; // was spotPriceCurrencyType
						
					$quoteSpotPriceCurrencyID								= 2; // $row -> quoteSpotPriceCurrencyID; // was spotPriceCurrencyTypeID - done, needs verification
					$quoteSpotPriceCurrencyName								= "USD"; // $row -> quoteSpotPriceCurrencyName; // was spotPriceCurrencyType	
						
					$amount													= $row -> amount; // was btcQuantityTransacted - done	
					$fee													= $row -> fee; // not needed
					$balance												= $row -> balance;
					$baseToQuoteCurrencySpotPrice							= $row -> baseToQuoteCurrencySpotPrice;
					$baseToUSDCurrencySpotPrice								= $row -> baseToUSDCurrencySpotPrice; // was spotPriceAtTimeOfTransaction - done, needs verification
					$currencyPriceValueSourceID								= $row -> FK_CurrencyPriceValueSourceID;  // not needed
					$btcSpotPriceAtTimeOfTransaction						= $row -> btcSpotPriceAtTimeOfTransaction; // was btcPriceAtTimeOfTransaction - done, needs verification
					$transactionRecordID									= $row -> FK_TransactionRecordID; // not needed
					$creationDate											= $row -> creationDate;
					$transactionDate										= $row -> transactionDate;
					$transactionTimestamp									= $row -> transactionTimestamp; // not needed
					$vendorTransactionID									= $row -> vendorTransactionID;	
					$vendorRefID											= $row -> vendorRefID; // not needed
					$transactionAmountInQuoteCurrency						= $row -> transactionAmountInQuoteCurrency; // not needed
					$transactionAmountInUSD									= $row -> transactionAmountInUSD; // was usdQuantityTransacted - done
					$feeAmountInUSD											= $row -> feeAmountInUSD; // was usdFeeAmount - done
					$transactionAmountPlusFeeInUSD							= $row -> transactionAmountPlusFeeInUSD; // not needed
					$transactionAmountMinusFeeInUSD							= $row -> transactionAmountMinusFeeInUSD; // was usdTransactionAmountWithFees - this is the amount that changes the balance in their system - I may need to use this rather than the transaction amount in USD to get the right total amount
					$providerNotes											= $row -> providerNotes;
					$transactionTypeLabel									= $row -> transactionTypeLabel; // was displayTransactionTypeLabel - done
					$ledgerRecordID											= $row -> ledgerRecordID; //
					$isDebit												= $row -> isDebit;
					
					$sourceWalletID											= $row -> FK_BaseCurrencyWalletID;
					$destinationWalletID									= $row -> FK_QuoteCurrencyWalletID;
				
					$responseObject['processingTransaction'][]				= $vendorTransactionID;
					
					$unspentTransactionTotal								= 0;
					$unfundedSpendTotal										= 0;
					
					if ($isDebit == 0)
					{
						$unspentTransactionTotal  							= $amount;
					}
					else if ($isDebit == 1)
					{
						$unfundedSpendTotal									= $amount;	
					}	
					
					$sourceWallet											= new CompleteCryptoWallet();
					$destinationWallet										= new CompleteCryptoWallet();
			
					$sourceWalletResponseObject								= $sourceWallet -> instantiateWalletUsingCryptoWalletRecordID($liuAccountID, $sourceWalletID, $userEncryptionKey, $dbh);
			
					if ($sourceWalletResponseObject['instantiatedRecord'] == false)
					{
						errorLog("Could not instantiate source Complete Crypto Wallet record $liuAccountID");
					}
					
					$destinationWalletResponseObject						= $destinationWallet -> instantiateWalletUsingCryptoWalletRecordID($liuAccountID, $destinationWalletID, $userEncryptionKey, $dbh);
			
					if ($destinationWalletResponseObject['instantiatedRecord'] == false)
					{
						errorLog("Could not instantiate destination Complete Crypto Wallet record $liuAccountID, $destinationWalletID");
					}
					
					errorLog($vendorTransactionID."<BR>");
					
					$responseObject											= createCryptoTransactionRecord($accountID, $authorID, $userEncryptionKey, $globalTransactionIdentificationRecordID, $transactionTypeID, $transactionSourceID, $baseCurrencyID, $quoteSpotPriceCurrencyID, $sourceWalletID, $destinationWalletID, $creationDate, $transactionDate, $transactionTimestamp, $vendorTransactionID, $amount, $transactionAmountInUSD, $baseToUSDCurrencySpotPrice, $btcSpotPriceAtTimeOfTransaction, $transactionAmountMinusFeeInUSD, $feeAmountInUSD, $unspentTransactionTotal, $providerNotes, $isDebit, $globalCurrentDate, $sid, $dbh);
					
					if ($responseObject['transactionRecordExisted'] == false && $responseObject['createdTransactionRecord'] == true)
					{
						$transactionID										= $responseObject['commonTransactionRecordID'];	
						
						errorLog("commonTransactionRecordID $transactionID");
						
						if ($transactionID > 0)
						{
							errorLog("created transaction<BR>");
								
							if ($isDebit == 1)
							{
								errorLog("is debit<BR><BR>account ID: $accountID assetType: $baseCurrencyID<BR><BR>");
								
								$unfundedSpendTotal							= $amount;
								$doContinue									= 1;
									
								while ($doContinue == 1)
								{
									$getFundedReceiveCryptoTransactionRecord -> bindValue(':accountID', $accountID);
									$getFundedReceiveCryptoTransactionRecord -> bindValue(':assetType', $baseCurrencyTypeID);
									$getFundedReceiveCryptoTransactionRecord -> bindValue(':userEncryptionKey', $userEncryptionKey);
									
									if ($getFundedReceiveCryptoTransactionRecord -> execute() && $getFundedReceiveCryptoTransactionRecord -> rowCount() > 0)
									{
										errorLog("executed getFundedReceiveCryptoTransactionRecord for transaction $transactionID<BR>");
										
										$row2 								= $getFundedReceiveCryptoTransactionRecord -> fetchObject();
										
										$remainingUnspent					= $row2->unspentTransactionTotal;
										$sourceTransactionID				= $row2->transactionID;
										$receivedTransactionDate			= $row2->transactionDate;
										$receiveSpotPriceAtTimeOfTransaction	= $row2->spotPriceAtTimeOfTransaction;
										
										errorLog("remainingUnspent: $remainingUnspent, sourceTransactionID: $sourceTransactionID, receivedTransactionDate: $receivedTransactionDate, receiveSpotPriceAtTimeOfTransaction: $receiveSpotPriceAtTimeOfTransaction");
										
										
										if ($remainingUnspent >= $unfundedSpendTotal)
										{
											errorLog("because the remaining unspent amount for the receive transaction is greater than the $unfundedSpendTotal, the $unfundedSpendTotal will be the amount spent by this spent transaction group item, and the receive transaction unspent transaction total will be updated to the different between the amount that was spent in the transaction, and the amount that was unspent");
											
											$unspentTransactionTotal		= $remainingUnspent - $unfundedSpendTotal;
											
											$groupingTransactionID			= createCryptoTransactionGroupingRecord($liuAccountID, $liuAccountID, $sourceTransactionID, $transactionID, $transactionTypeID, $transactionTypeLabel, $unfundedSpendTotal, $receivedTransactionDate, $transactionDate, $receiveSpotPriceAtTimeOfTransaction, $baseToUSDCurrencySpotPrice, $globalCurrentDate, $sid, $dbh);
											
											if ($groupingTransactionID > 0)
											{
												$unfundedSpendTotal			= 0;	
												
												// update source transaction remaining unspent - set to $unspentTransactionTotal
												if (updateCryptoTransactionUnspentBalance($liuAccountID, $liuAccountID, $sourceTransactionID, $unspentTransactionTotal, $sid, $dbh) > 0)
												{
													errorLog("SUCCESS: executed updateCryptoTransactionUnspentBalance for $liuAccountID, $liuAccountID, $sourceTransactionID, $unspentTransactionTotal, $sid");
												}
												else
												{
													errorLog("ERROR: unable to execute updateCryptoTransactionUnspentBalance for $liuAccountID, $liuAccountID, $sourceTransactionID, $unspentTransactionTotal, $sid");
												}
											}
											else
											{
												errorLog("ERROR: Unable to execute createCryptoTransactionGroupingRecord for $liuAccountID, $liuAccountID, $sourceTransactionID, $transactionID, $unfundedSpendTotal, $spendTransactionDate, $globalCurrentDate, $sid");
											}
											
											$doContinue						= 0;
										}
										else if ($remainingUnspent < $unfundedSpendTotal)
										{
											errorLog("there is not enough left in this receive transaction to pay for the entire spend - the amount that remained in this receive transaction will be the sub transaction amount, and another receive transaction with available funds will be found<BR><BR>");
											
											$unfundedSpendTotal				= $unfundedSpendTotal - $remainingUnspent;
											
											$groupingTransactionID			= createCryptoTransactionGroupingRecord($liuAccountID, $liuAccountID, $sourceTransactionID, $transactionID, $transactionTypeID, $transactionTypeLabel, $remainingUnspent, $receivedTransactionDate, $transactionDate, $receiveSpotPriceAtTimeOfTransaction, $baseToUSDCurrencySpotPrice, $globalCurrentDate, $sid, $dbh);
											$remainingUnspent				= 0;
											
											if ($groupingTransactionID > 0)
											{
												// @task update source transaction remaining unspent - set to $remainingUnspent, which is 0
												if (updateCryptoTransactionUnspentBalance($liuAccountID, $liuAccountID, $sourceTransactionID, $remainingUnspent, $sid, $dbh) > 0)
												{
													errorLog("SUCCESS: executed updateCryptoTransactionUnspentBalance for $accountID, $authorID, $sourceTransactionID, $remainingUnspent, $sid");
												}
												else
												{
													errorLog("ERROR: unable to execute updateCryptoTransactionUnspentBalance for $accountID, $authorID, $sourceTransactionID, $remainingUnspent, $sid");
													$doContinue				= 0;
												}
											}
											else
											{
												errorLog("ERROR: Unable to execute createCryptoTransactionGroupingRecord for $accountID, $authorID, $sourceTransactionID, $transactionID, $unfundedSpendTotal, $spendTransactionDate, $globalCurrentDate, $sid");
												$doContinue					= 0;
											}
										}
										else
										{
											errorLog("ERROR: Unknown error P337 occurred");	
											$doContinue						= 0;
										}
									}
									else
									{
										errorLog("no records found for getFundedReceiveCryptoTransactionRecord for transaction $transactionID<BR>");
										$doContinue 						= 0;
									}							
								}
							}
							
							$getFundedReceiveCryptoTransactionRecord -> bindValue(':accountID', $accountID);
							$getFundedReceiveCryptoTransactionRecord -> bindValue(':assetType', $baseCurrencyTypeID);
							$getFundedReceiveCryptoTransactionRecord -> bindValue(':userEncryptionKey', $userEncryptionKey);
							
							if ($getFundedReceiveCryptoTransactionRecord -> execute() && $getFundedReceiveCryptoTransactionRecord -> rowCount() > 0)
							{
								while ($row3 = $getFundedReceiveCryptoTransactionRecord -> fetchObject())
								{
									$remainingUnspent						= $row3->unspentTransactionTotal;
									$sourceTransactionID					= $row3->transactionID;
									$receiveSpotPriceAtTimeOfTransaction	= $row3->spotPriceAtTimeOfTransaction;
									
									createCryptoTransactionBalanceRecord($authorID, $accountID, $transactionID, $sourceTransactionID, $remainingUnspent, $receiveSpotPriceAtTimeOfTransaction, $globalCurrentDate, $sid, $dbh);
								}			
							}	
						}
					}
					else
					{
						errorLog("error creating transaction record for Kraken Ledger $accountID, $authorID, $userEncryptionKey, $globalTransactionIdentificationRecordID, $transactionTypeID, $transactionSourceID, $baseCurrencyID, $quoteSpotPriceCurrencyID, $sourceWalletID, $destinationWalletID, $creationDate, $transactionDate, $vendorTransactionID, $amount, $transactionAmountInUSD, $baseToUSDCurrencySpotPrice, $btcSpotPriceAtTimeOfTransaction, $transactionAmountMinusFeeInUSD, $feeAmountInUSD, $unspentTransactionTotal, $providerNotes, $isDebit, $globalCurrentDate, $sid");
					}
				}
			}
			else
			{
				errorLog("SELECT
	KrakenLedgerTransactions.ledgerRecordID,
	KrakenLedgerTransactions.FK_GlobalTransactionRecordID,
	KrakenLedgerTransactions.FK_AccountID AS authorID,
	KrakenLedgerTransactions.FK_AccountID AS accountID,
	KrakenLedgerTransactions.FK_ProviderAccountWalletID,
	CASE
		WHEN KrakenLedgerTransactions.FK_LedgerEntryTypeID = 1 THEN 7
		WHEN KrakenLedgerTransactions.FK_LedgerEntryTypeID = 2 THEN 8
		WHEN 
			KrakenLedgerTransactions.FK_LedgerEntryTypeID > 2 AND
			KrakenLedgerTransactions.isDebit = 0 THEN 1
		WHEN 
			KrakenLedgerTransactions.FK_LedgerEntryTypeID > 2 AND
			KrakenLedgerTransactions.isDebit = 1 THEN 4
		ELSE 14
	END AS transactionTypeID,
	KrakenLedgerTransactions.FK_AssetClassID,
	AES_DECRYPT(KrakenLedgerTransactions.encryptedPairName, UNHEX(SHA2('$userEncryptionKey',512))) AS pairName,
	KrakenLedgerTransactions.FK_KrakenCurrencyPairID,
	baseCurrency.assetTypeID AS baseCurrencyID,
	baseCurrency.assetTypeLabel AS baseCurrencyName,
	quoteCurrency.assetTypeID AS quoteSpotPriceCurrencyID,
	quoteCurrency.assetTypeLabel AS quoteSpotPriceCurrencyName,
	KrakenLedgerTransactions.amount,
	KrakenLedgerTransactions.fee,
	KrakenLedgerTransactions.balance,
	KrakenLedgerTransactions.isDebit,
	KrakenLedgerTransactions.baseToQuoteCurrencySpotPrice,
	KrakenLedgerTransactions.baseToUSDCurrencySpotPrice,
	KrakenLedgerTransactions.FK_CurrencyPriceValueSourceID,
	KrakenLedgerTransactions.FK_TransactionRecordID,
	KrakenLedgerTransactions.FK_BaseCurrencyWalletID,
	KrakenLedgerTransactions.FK_QuoteCurrencyWalletID,
	KrakenLedgerTransactions.transactionTime AS creationDate,
	KrakenLedgerTransactions.transactionTime AS transactionDate,
	KrakenLedgerTransactions.transactionTimestamp,
	AES_DECRYPT(KrakenLedgerTransactions.encryptedLedgerIDValue, UNHEX(SHA2('$userEncryptionKey',512))) AS vendorTransactionID,
	AES_DECRYPT(KrakenLedgerTransactions.encryptedRefIDValue, UNHEX(SHA2('$userEncryptionKey',512))) AS vendorRefID,
	KrakenLedgerTransactions.amount * KrakenLedgerTransactions.baseToQuoteCurrencySpotPrice AS transactionAmountInQuoteCurrency,
	KrakenLedgerTransactions.amount * KrakenLedgerTransactions.baseToUSDCurrencySpotPrice AS transactionAmountInUSD,
	KrakenLedgerTransactions.fee * KrakenLedgerTransactions.baseToUSDCurrencySpotPrice AS feeAmountInUSD,
	(KrakenLedgerTransactions.amount * KrakenLedgerTransactions.baseToUSDCurrencySpotPrice) + (KrakenLedgerTransactions.fee * KrakenLedgerTransactions.baseToUSDCurrencySpotPrice) AS transactionAmountPlusFeeInUSD,
	(KrakenLedgerTransactions.amount * KrakenLedgerTransactions.baseToUSDCurrencySpotPrice) - (KrakenLedgerTransactions.fee * KrakenLedgerTransactions.baseToUSDCurrencySpotPrice) AS transactionAmountMinusFeeInUSD,
	'' AS  providerNotes,
	KrakenLedgerTransactions.FK_BaseCurrencyWalletID AS FK_SourceAddressID,
	KrakenLedgerTransactions.FK_QuoteCurrencyWalletID AS FK_DestinationAddressID,
	CASE
		WHEN KrakenLedgerTransactions.FK_LedgerEntryTypeID = 1 THEN 'Deposit'
		WHEN KrakenLedgerTransactions.FK_LedgerEntryTypeID = 2 THEN 'Withdrawal'
		WHEN 
			KrakenLedgerTransactions.FK_LedgerEntryTypeID > 2 AND
			KrakenLedgerTransactions.isDebit = 1 THEN 'Sell'
		WHEN 
			KrakenLedgerTransactions.FK_LedgerEntryTypeID > 2 AND
			KrakenLedgerTransactions.isDebit = 0 THEN 'Buy'
		ELSE 'Adjustement'
	END AS transactionTypeLabel
FROM
	KrakenLedgerTransactions
	INNER JOIN CommonCurrencyPairs ON KrakenLedgerTransactions.FK_KrakenCurrencyPairID = CommonCurrencyPairs.pairID
	INNER JOIN AssetTypes baseCurrency ON CommonCurrencyPairs.FK_BaseCurrencyID = baseCurrency.assetTypeID AND baseCurrency.languageCode = 'EN'
	INNER JOIN AssetTypes quoteCurrency ON CommonCurrencyPairs.FK_QuoteCurrencyID = quoteCurrency.assetTypeID AND quoteCurrency.languageCode = 'EN'
WHERE
	KrakenLedgerTransactions.FK_AccountID = $liuAccountID AND
	baseCurrency.assetTypeID = $baseCurrencyTypeID
ORDER BY
	KrakenLedgerTransactions.transactionTimestamp");	
			}
			
			$responseObject['importedTransactions']							= true;
		}
		catch (PDOException $e) 
		{
			$cryptoTransaction 												= null;	
			$responseObject['importedTransactions']							= false;
			
			errorLog($e -> getMessage());
		
			die();
		}
		
		return $responseObject;
	}
	
	// END COMMON ACCOUNTING TYPE FUNCTIONS
	
	// New ProfitStance Ledger related code
	
	function getProfitStanceLedgerBasedExchangeAndWalletCountForUser($liuAccountID, $sid, $dbh)
	{
		$responseObject														= array();
		$responseObject['retrievedExchangeList']								= false;
		$responseObject['numberOfConnectedExchangesOrWallets']				= 0;
		
		try
		{		
			$getDataForUser													= $dbh -> prepare("SELECT
	COUNT(DISTINCT(FK_TransactionSourceID)) AS numExchangeServers
FROM
	ProfitStanceLedgerEntries
WHERE
	ProfitStanceLedgerEntries.FK_AccountID = :accountID");
	
			//@task - temp - start here
			
			$getFileUploadDataForUser										= $dbh -> prepare("SELECT
	COUNT(fileID) AS numUploadedFiles
FROM
	FileUploads
WHERE
	FileUploads.FK_AccountID = :accountID");

			// end temp

			$getDataForUser -> bindValue(':accountID', $liuAccountID);
						
			if ($getDataForUser -> execute() && $getDataForUser -> rowCount() > 0)
			{
				$row 														= $getDataForUser -> fetchObject();
				
				$responseObject['retrievedExchangeList']						= true;
				$responseObject['numberOfConnectedExchangesOrWallets']		= $row -> numExchangeServers;
			}
			else
			{
				$responseObject['resultMessage']								= "Unable to retrieve a source list for user $liuAccountID";
			}
			
/*
			// temp
			$getFileUploadDataForUser -> bindValue(':accountID', $liuAccountID);
						
			if ($getFileUploadDataForUser -> execute() && $getFileUploadDataForUser -> rowCount() > 0)
			{
				$row 														= $getFileUploadDataForUser -> fetchObject();
				
				$responseObject['retrievedExchangeList']						= true;
				$responseObject['numberOfConnectedExchangesOrWallets']		= $responseObject['numberOfConnectedExchangesOrWallets'] + $row -> numUploadedFiles;
			}
			else
			{
				$responseObject['resultMessage']								= "Unable to retrieve a source list for user $liuAccountID";
			}
*/
		}
	    catch (PDOException $e) 
	    {
	    		errorLog($e -> getMessage());
	    	
	    		$responseObject['resultMessage']									= "Error: Unable to retrieve a source list for user $liuAccountID due to a database error: ".$e -> getMessage();
	
			die();
		}
		
		return $responseObject;
	}
	
	function getProfitStanceExchangeTileListForUser($liuAccountID, $viewType, $globalCurrentDate, $sid, $dbh)
	{
		// get list of all exchanges and wallets the person is connected to
		// for each exchange - get all assets associated with that exchange
		// get current balance for each asset for that exchange
		
		$responseObject														= array();
		$responseObject['retrievedExchangeList']							= false;
		$responseObject['numberOfConnectedExchangesOrWallets']				= 0;
		
		$totalPortfolioValueArray											= array();
		$totalPortfolioValueArray['retrievedPortfolioValue']				= false;
		$totalPortfolioValueArray['totalPortfolioValue']					= 0;
		$totalPortfolioValueArray['asOfDate']								= "";
		
		$numberOfConnectedExchangesOrWallets								= 0;
		$totalPortfolioValue												= 0;
		
		try
		{		
			$getDataForUserSQL												= "SELECT DISTINCT
	ProfitStanceLedgerEntries.FK_TransactionSourceID,
	TransactionSources.transactionSourceLabel,
	TransactionSources.FK_DataSourceType,
	DataSourceType.typeName
FROM
	ProfitStanceLedgerEntries
	INNER JOIN TransactionSources ON ProfitStanceLedgerEntries.FK_TransactionSourceID = TransactionSources.transactionSourceID AND TransactionSources.languageCode = 'EN'
	INNER JOIN DataSourceType ON TransactionSources.FK_DataSourceType = DataSourceType.dataSourceType AND DataSourceType.languageCode = 'EN'
WHERE
	ProfitStanceLedgerEntries.FK_AccountID = :accountID";
	
			if (strcasecmp($viewType, "exchanges") == 0)
			{
				$getDataForUserSQL											= "SELECT DISTINCT
	ProfitStanceLedgerEntries.FK_TransactionSourceID,
	TransactionSources.transactionSourceLabel,
	TransactionSources.FK_DataSourceType,
	DataSourceType.typeName
FROM
	ProfitStanceLedgerEntries
	INNER JOIN TransactionSources ON ProfitStanceLedgerEntries.FK_TransactionSourceID = TransactionSources.transactionSourceID AND TransactionSources.languageCode = 'EN'
	INNER JOIN DataSourceType ON TransactionSources.FK_DataSourceType = DataSourceType.dataSourceType AND DataSourceType.languageCode = 'EN'
WHERE
	ProfitStanceLedgerEntries.FK_AccountID = :accountID AND
	TransactionSources.FK_DataSourceType = 2";	
			}
			else if (strcasecmp($viewType, "wallets") == 0)
			{
				$getDataForUserSQL											= "SELECT DISTINCT
	ProfitStanceLedgerEntries.FK_TransactionSourceID,
	TransactionSources.transactionSourceLabel,
	TransactionSources.FK_DataSourceType,
	DataSourceType.typeName
FROM
	ProfitStanceLedgerEntries
	INNER JOIN TransactionSources ON ProfitStanceLedgerEntries.FK_TransactionSourceID = TransactionSources.transactionSourceID AND TransactionSources.languageCode = 'EN'
	INNER JOIN DataSourceType ON TransactionSources.FK_DataSourceType = DataSourceType.dataSourceType AND DataSourceType.languageCode = 'EN'
WHERE
	ProfitStanceLedgerEntries.FK_AccountID = :accountID AND
	TransactionSources.FK_DataSourceType = 1";	
			}
			
			$getDataForUser													= $dbh -> prepare($getDataForUserSQL);

			$getDataForUser -> bindValue(':accountID', $liuAccountID);
						
			if ($getDataForUser -> execute() && $getDataForUser -> rowCount() > 0)
			{	
				$responseObject['retrievedExchangeList']					= true;
				
				while ($row = $getDataForUser -> fetchObject())
				{
					$numberOfConnectedExchangesOrWallets++;
					
					$exchangeArray											= array();
				
					$transactionSourceID									= $row -> FK_TransactionSourceID;
				
					// here 2019-03-01
					$lastDataImportDateObject								= getLastDataImportDateForUserAccountAndExchange($liuAccountID, $transactionSourceID, $sid, $dbh);
					
					if ($lastDataImportDateObject['retrievedLastDataImportDateExchange'] == true)
					{
						$lastDataImportDate									= $lastDataImportDateObject['completeImportDate'];
						$formattedLastDataImportDate						= $lastDataImportDateObject['formattedCompleteImportDate'];
						
						if (!empty($lastDataImportDate))
						{
							$exchangeArray['lastImportDate']				= $lastDataImportDate;
							$exchangeArray['formattedLastImportDate']		= $formattedLastDataImportDate;
						}
					}
					else
					{
						errorLog("could not retrieve last import date for $transactionSourceID", $GLOBALS['generalSystemPerformance']);
					}
					
					// getProfitStanceLedgerBasedBalanceForUserAccountAndExchange
					
					$assetArrayWithBalances									= getProfitStanceLedgerBasedBalanceForUserAccountAndExchangeWithoutGrouping($liuAccountID, $transactionSourceID, $globalCurrentDate, $sid, $dbh);
	
					if ($assetArrayWithBalances['retrievedCurrencyListWithBalanceForSource'] == true)
					{
						$totalPortfolioValueArray['retrievedPortfolioValue']= true;
						
						$balanceDate										= $assetArrayWithBalances['balanceDate'];
						$totalPortfolioValueArray['asOfDate']				= $balanceDate;
						
						$exchangeArray['accountName']						= $row -> transactionSourceLabel;
						$exchangeArray['accountType']						= $row -> typeName;
					
						$exchangeArray['totalAmountInUSD']					= $assetArrayWithBalances['totalPortfolioValue'];
					
						$exchangeArray['currencies']						= $assetArrayWithBalances;
					
						$responseObject['exchanges'][]						= $exchangeArray;
						
						$totalPortfolioValue								= $totalPortfolioValue + $assetArrayWithBalances['totalPortfolioValue'];
					}
					else
					{
						errorLog("No balance date found for liuAccountID $liuAccountID transactionSourceID $transactionSourceID", $GLOBALS['debugCoreFunctionality']);
					}
				}
				
				$responseObject['numberOfConnectedExchangesOrWallets']		= $numberOfConnectedExchangesOrWallets;
			}
			else
			{
				$responseObject['resultMessage']							= "Unable to retrieve a source list for user $liuAccountID";
			}
		}
	    catch (PDOException $e) 
	    {
	    	errorLog($e -> getMessage(), $GLOBALS['criticalErrors']);
	    	
	    	$responseObject['resultMessage']								= "Error: Unable to retrieve a source list for user $liuAccountID due to a database error: ".$e -> getMessage();
	
			die();
		}
		
		$totalPortfolioValueArray['totalPortfolioValue']					= $totalPortfolioValue;
		$responseObject['totalPortfolioValueArray']							= $totalPortfolioValueArray;
		
		errorLog(json_encode($responseObject), $GLOBALS['debugCoreFunctionality']);
		
		return $responseObject;
	}
	
	function getActiveDataImportForExchangeTile($liuAccountID, $exchangeTileID, $userEncryptionKey, $globalCurrentDate, $sid, $dbh)
	{
		// get active import record for exchangeTileID and user account
		// if active import found, get 
		
		$responseObject														= array();
		$responseObject['foundDataImportHistoryEventRecord']				= false;
		$responseObject['dataImportHistoryEventRecordID']					= 0;
		$responseObject['importStageID']									= 0;
		$responseObject['isActive']											= 0;
		$responseObject['importMethod']										= null;
				
		try
		{		
			$getActiveDataImportForExchangeTile								= $dbh -> prepare("SELECT
	DataImportHistory.dataImportHistoryEventRecordID,
	DataImportHistory.FK_ImportStageID,
	DataImportHistory.isActive,
	ImportCategories.importCategoryID,
	ImportCategories.importCategoryLabel
FROM
	DataImportHistory
	INNER JOIN DataImportTypes ON DataImportHistory.FK_ImportTypeID = DataImportTypes.dataImportTypeRecordID
	INNER JOIN ImportCategories ON DataImportTypes.FK_ImportCategoryID = ImportCategories.importCategoryID
WHERE
	DataImportHistory.FK_AccountID = :accountID AND
	DataImportHistory.FK_DeletedBy IS NULL AND
	DataImportHistory.FK_ExchangeTileID = :exchangeTileID
ORDER BY
	DataImportHistory.beginImportDate DESC
LIMIT 1");

			$getActiveDataImportForExchangeTile -> bindValue(':accountID', $liuAccountID);
			$getActiveDataImportForExchangeTile -> bindValue(':exchangeTileID', $exchangeTileID);
						
			if ($getActiveDataImportForExchangeTile -> execute() && $getActiveDataImportForExchangeTile -> rowCount() > 0)
			{	
				$responseObject['dataImportHistoryEventRecordID']			= true;
				
				if ($row = $getActiveDataImportForExchangeTile -> fetchObject())
				{
					$responseObject['foundDataImportHistoryEventRecord']	= true;
					
					$responseObject['dataImportHistoryEventRecordID']		= $row -> dataImportHistoryEventRecordID;
					$responseObject['importStageID']						= $row -> FK_ImportStageID;
					$responseObject['isActive']								= $row -> isActive;
					$responseObject['importMethod']							= $row -> importCategoryID;
				}
			}
		}
	    catch (PDOException $e) 
	    {
	    	errorLog($e -> getMessage());
	    	
	    	$responseObject['resultMessage']								= "Error: Unable to retrieve active data import information for $liuAccountID due to a database error: ".$e -> getMessage();
	
			die();
		}
		
		errorLog(json_encode($responseObject), 2);
		
		return $responseObject;
	}
	
	function getProfitStanceLedgerBasedExchangeTileListForUser($liuAccountID, $viewType, $userEncryptionKey, $globalCurrentDate, $sid, $dbh)
	{
		// get list of all exchanges and wallets the person is connected to
		// for each exchange - get all assets associated with that exchange
		// get current balance for each asset for that exchange
		
		errorLog("getProfitStanceLedgerBasedExchangeTileListForUser($liuAccountID, $viewType, $userEncryptionKey, $globalCurrentDate, $sid", $GLOBALS['debugCoreFunctionality']);
		
		$responseObject														= array();
		$responseObject['retrievedExchangeList']							= false;
		$responseObject['numberOfExchangeTiles']							= 0;
		$responseObject['fiatCurrencyID']									= 2;	// later, this value will be set by the user as the portfolio currency
		
		$totalPortfolioValueArray											= array();
		$totalPortfolioValueArray['retrievedPortfolioValue']				= false;
		$totalPortfolioValueArray['totalPortfolioValue']					= 0;
		$totalPortfolioValueArray['asOfDate']								= $globalCurrentDate;
		
		$numberOfExchangeTiles												= 0;
		$totalPortfolioValue												= 0;
		
		try
		{		
			$getDataForUserSQL												= "SELECT DISTINCT
	ExchangeTiles.exchangeTileID,
	AES_DECRYPT(ExchangeTiles.encryptedTileLabel, UNHEX(SHA2(:userEncryptionKey,512))) AS exchangeTileLabel,
	ExchangeTiles.displayOrder,
	ExchangeTiles.tileHeight,
	ExchangeTiles.FK_ExchangeTileCategoryID,
	ExchangeTileCategories.exchangeTileCategoryName,
	TransactionSources.transactionSourceID,
	TransactionSources.FK_WalletTypeID,
	TransactionSources.FK_LiveConnectionTypeID,
	TransactionSources.allowCSVUpload,
	ProviderCategories.categoryLabel
FROM
	ExchangeTiles
	INNER JOIN TransactionSources ON ExchangeTiles.FK_ExchangeTileTypeID = TransactionSources.FK_ExchangeTileTypeID
	INNER JOIN ProviderCategories ON TransactionSources.FK_ProviderCategoryID = ProviderCategories.providerCategoryID AND ProviderCategories.languageCode = 'EN'
	INNER JOIN ExchangeTileCategories ON ExchangeTiles.FK_ExchangeTileCategoryID = ExchangeTileCategories.exchangeTileCategoryID
WHERE
	ExchangeTiles.FK_AccountID = :accountID";
			
			$getDataForUser													= $dbh -> prepare($getDataForUserSQL);

			$getDataForUser -> bindValue(':accountID', $liuAccountID);
			$getDataForUser -> bindValue(':userEncryptionKey', $userEncryptionKey);
						
			if ($getDataForUser -> execute() && $getDataForUser -> rowCount() > 0)
			{	
				$responseObject['retrievedExchangeList']					= true;
				
				while ($row = $getDataForUser -> fetchObject())
				{
					$numberOfExchangeTiles++;
					
					$exchangeArray											= array();
				
					$exchangeTileID											= $row -> exchangeTileID;
					
					errorLog("iterate through exchanges - $exchangeTileID", $GLOBALS['debugCoreFunctionality']);
					
					$exchangeArray['uniqueID']								= $exchangeTileID;
					$exchangeArray['exchangeTileLabel']						= $row -> exchangeTileLabel;
					$exchangeArray['displayOrder']							= $row -> displayOrder;
					$exchangeArray['height']							 	= $row -> tileHeight;
					$exchangeArray['lastImportDate']						= null;
					$exchangeArray['formattedLastImportDate']				= null;
					
					$transactionSourceID									= $row -> transactionSourceID;
					
					$exchangeArray['providerID']							= $transactionSourceID;
					$exchangeArray['walletTypeID']							= $row -> FK_WalletTypeID;
					$exchangeArray['type']									= $row -> categoryLabel;
					$exchangeArray['liveConnectionTypeID']					= $row -> FK_LiveConnectionTypeID;
					$exchangeArray['importMethod']							= null;
					$exchangeArray['currentDataImportRecordID']				= null;
					$exchangeArray['currentImportStage']					= null;
					$exchangeArray['currentImportMethod']					= null;
					
					$exchangeArray['exchangeTileCategoryName']				= $row -> exchangeTileCategoryName;
					$exchangeArray['exchangeTileCategoryID']				= $row -> FK_ExchangeTileCategoryID;
					
					
					$allowCSVUpload											= $row -> allowCSVUpload;
					
					$dataImportMediaArray									= array();
					
					if ($exchangeArray['liveConnectionTypeID'] > 0 && $exchangeArray['liveConnectionTypeID'] < 4)
					{
						$dataImportMediaArray[]								= 1;	
					}
					
					if ($allowCSVUpload == 1)
					{
						$dataImportMediaArray[]								= 2;	
					}
					
					$exchangeArray['dataImportMedia']						= $dataImportMediaArray;
					
				
					// here 2019-05-23
					$lastDataImportDateObject								= getLastDataImportDateForUserAccountAndExchangeTile($liuAccountID, $exchangeTileID, $sid, $dbh);
					
					if ($lastDataImportDateObject['retrievedLastDataImportDateExchange'] == true)
					{
						$lastDataImportDate									= $lastDataImportDateObject['completeImportDate'];
						$formattedLastDataImportDate						= $lastDataImportDateObject['formattedCompleteImportDate'];
						
						if (!empty($lastDataImportDate))
						{
							$exchangeArray['lastImportDate']				= $lastDataImportDate;
							$exchangeArray['formattedLastImportDate']		= $formattedLastDataImportDate;
						}
					}
					else
					{
						errorLog("could not retrieve last import date for $transactionSourceID", $GLOBALS['debugCoreFunctionality']);
					}
					
					// getProfitStanceLedgerBasedBalanceForUserAccountAndExchange
					
					$activeDataImportForExchangeTile						= getActiveDataImportForExchangeTile($liuAccountID, $exchangeTileID, $userEncryptionKey, $globalCurrentDate, $sid, $dbh);
					
					if ($activeDataImportForExchangeTile['foundDataImportHistoryEventRecord'] == true)
					{
						$exchangeArray['importMethod']						= $activeDataImportForExchangeTile['importMethod'];
						
						if ($activeDataImportForExchangeTile['isActive'] == true)
						{
							$exchangeArray['currentDataImportRecordID']		= $activeDataImportForExchangeTile['dataImportHistoryEventRecordID'];
							$exchangeArray['currentImportStage']			= $activeDataImportForExchangeTile['importStageID'];
							$exchangeArray['currentImportMethod']			= $activeDataImportForExchangeTile['importMethod'];	
						}	
					}
					
					$assetArrayWithBalances									= getProfitStanceLedgerBasedBalanceForUserAccountAndExchangeTileWithoutGrouping($liuAccountID, $exchangeTileID, $globalCurrentDate, $sid, $dbh);
	
					if ($assetArrayWithBalances['retrievedCurrencyListWithBalanceForSource'] == true)
					{
						errorLog("in assetArrayWithBalances handler", $GLOBALS['debugCoreFunctionality']);
						
						$totalPortfolioValueArray['retrievedPortfolioValue']= true;
						
						$balanceDate										= $assetArrayWithBalances['balanceDate'];
						$totalPortfolioValueArray['asOfDate']				= $balanceDate;
						
						$exchangeArray['exchangeTileLabel']					= $row -> exchangeTileLabel;
					
						$exchangeArray['totalAmountInFiatCurrency']			= $assetArrayWithBalances['totalPortfolioValue'];
					
						$exchangeArray['currencies']						= $assetArrayWithBalances;
						
						$totalPortfolioValue								= $totalPortfolioValue + $assetArrayWithBalances['totalPortfolioValue'];
					}
					else
					{
						$exchangeArray['exchangeTileLabel']					= $row -> exchangeTileLabel;
					
						$exchangeArray['totalAmountInFiatCurrency']			= 0;
					
						$exchangeArray['currencies']						= null;
						
						errorLog("No balance date found for liuAccountID $liuAccountID exchangeTileID $exchangeTileID", $GLOBALS['debugCoreFunctionality']);
					}
					
					$responseObject['exchanges'][]							= $exchangeArray;
				}
				
				$responseObject['numberOfExchangeTiles']					= $numberOfExchangeTiles;
			}
			else
			{
				$responseObject['resultMessage']							= "Unable to retrieve a source list for user $liuAccountID";
			}
		}
	    catch (PDOException $e) 
	    {
	    	errorLog($e -> getMessage());
	    	
	    	$responseObject['resultMessage']								= "Error: Unable to retrieve a source list for user $liuAccountID due to a database error: ".$e -> getMessage();
	
			die();
		}
		
		$totalPortfolioValueArray['totalPortfolioValue']					= $totalPortfolioValue;
		$responseObject['totalPortfolioValueArray']							= $totalPortfolioValueArray;
		
		errorLog(json_encode($responseObject), $GLOBALS['debugCoreFunctionality']);
		
		return $responseObject;
	}
	
	function getProfitStanceLedgerBasedExchangeAndWalletListForUser($liuAccountID, $viewType, $globalCurrentDate, $sid, $dbh)
	{
		// get list of all exchanges and wallets the person is connected to
		// for each exchange - get all assets associated with that exchange
		// get current balance for each asset for that exchange
		
		$responseObject														= array();
		$responseObject['retrievedExchangeList']							= false;
		$responseObject['numberOfConnectedExchangesOrWallets']				= 0;
		$responseObject['fiatCurrencyID']									= 2;	// later, this value will be set by the user as the portfolio currency
		
		$totalPortfolioValueArray											= array();
		$totalPortfolioValueArray['retrievedPortfolioValue']				= false;
		$totalPortfolioValueArray['totalPortfolioValue']					= 0;
		$totalPortfolioValueArray['asOfDate']								= "";
		
		$numberOfConnectedExchangesOrWallets								= 0;
		$totalPortfolioValue												= 0;
		
		try
		{		
			$getDataForUserSQL												= "SELECT DISTINCT
	ProfitStanceLedgerEntries.FK_TransactionSourceID,
	TransactionSources.transactionSourceLabel,
	TransactionSources.FK_DataSourceType,
	DataSourceType.typeName
FROM
	ProfitStanceLedgerEntries
	INNER JOIN TransactionSources ON ProfitStanceLedgerEntries.FK_TransactionSourceID = TransactionSources.transactionSourceID AND TransactionSources.languageCode = 'EN'
	INNER JOIN DataSourceType ON TransactionSources.FK_DataSourceType = DataSourceType.dataSourceType AND DataSourceType.languageCode = 'EN'
WHERE
	ProfitStanceLedgerEntries.FK_AccountID = :accountID";
	
			if (strcasecmp($viewType, "exchanges") == 0)
			{
				$getDataForUserSQL											= "SELECT DISTINCT
	ProfitStanceLedgerEntries.FK_TransactionSourceID,
	TransactionSources.transactionSourceLabel,
	TransactionSources.FK_DataSourceType,
	DataSourceType.typeName
FROM
	ProfitStanceLedgerEntries
	INNER JOIN TransactionSources ON ProfitStanceLedgerEntries.FK_TransactionSourceID = TransactionSources.transactionSourceID AND TransactionSources.languageCode = 'EN'
	INNER JOIN DataSourceType ON TransactionSources.FK_DataSourceType = DataSourceType.dataSourceType AND DataSourceType.languageCode = 'EN'
WHERE
	ProfitStanceLedgerEntries.FK_AccountID = :accountID AND
	TransactionSources.FK_DataSourceType = 2";	
			}
			else if (strcasecmp($viewType, "wallets") == 0)
			{
				$getDataForUserSQL											= "SELECT DISTINCT
	ProfitStanceLedgerEntries.FK_TransactionSourceID,
	TransactionSources.transactionSourceLabel,
	TransactionSources.FK_DataSourceType,
	DataSourceType.typeName
FROM
	ProfitStanceLedgerEntries
	INNER JOIN TransactionSources ON ProfitStanceLedgerEntries.FK_TransactionSourceID = TransactionSources.transactionSourceID AND TransactionSources.languageCode = 'EN'
	INNER JOIN DataSourceType ON TransactionSources.FK_DataSourceType = DataSourceType.dataSourceType AND DataSourceType.languageCode = 'EN'
WHERE
	ProfitStanceLedgerEntries.FK_AccountID = :accountID AND
	TransactionSources.FK_DataSourceType = 1";	
			}
			
			$getDataForUser													= $dbh -> prepare($getDataForUserSQL);

			$getDataForUser -> bindValue(':accountID', $liuAccountID);
						
			if ($getDataForUser -> execute() && $getDataForUser -> rowCount() > 0)
			{	
				$responseObject['retrievedExchangeList']					= true;
				
				while ($row = $getDataForUser -> fetchObject())
				{
					$numberOfConnectedExchangesOrWallets++;
					
					$exchangeArray											= array();
				
					$transactionSourceID									= $row -> FK_TransactionSourceID;
				
					// here 2019-03-01
					$lastDataImportDateObject								= getLastDataImportDateForUserAccountAndExchange($liuAccountID, $transactionSourceID, $sid, $dbh);
					
					if ($lastDataImportDateObject['retrievedLastDataImportDateExchange'] == true)
					{
						$lastDataImportDate									= $lastDataImportDateObject['completeImportDate'];
						$formattedLastDataImportDate						= $lastDataImportDateObject['formattedCompleteImportDate'];
						
						if (!empty($lastDataImportDate))
						{
							$exchangeArray['lastImportDate']				= $lastDataImportDate;
							$exchangeArray['formattedLastImportDate']		= $formattedLastDataImportDate;
						}
					}
					else
					{
						errorLog("could not retrieve last import date for $transactionSourceID", $GLOBALS['generalSystemPerformance']);
					}
					
					// getProfitStanceLedgerBasedBalanceForUserAccountAndExchange
					
					$assetArrayWithBalances									= getProfitStanceLedgerBasedBalanceForUserAccountAndExchangeWithoutGrouping($liuAccountID, $transactionSourceID, $globalCurrentDate, $sid, $dbh);
	
					if ($assetArrayWithBalances['retrievedCurrencyListWithBalanceForSource'] == true)
					{
						$totalPortfolioValueArray['retrievedPortfolioValue']= true;
						
						$balanceDate										= $assetArrayWithBalances['balanceDate'];
						$totalPortfolioValueArray['asOfDate']				= $balanceDate;
						
						$exchangeArray['accountName']						= $row -> transactionSourceLabel;
						$exchangeArray['accountType']						= $row -> typeName;
					
						$exchangeArray['totalAmountInUSD']					= $assetArrayWithBalances['totalPortfolioValue'];
					
						$exchangeArray['currencies']						= $assetArrayWithBalances;
					
						$responseObject['exchanges'][]						= $exchangeArray;
						
						$totalPortfolioValue								= $totalPortfolioValue + $assetArrayWithBalances['totalPortfolioValue'];
					}
					else
					{
						errorLog("No balance date found for liuAccountID $liuAccountID transactionSourceID $transactionSourceID");
					}
				}
				
				$responseObject['numberOfConnectedExchangesOrWallets']		= $numberOfConnectedExchangesOrWallets;
			}
			else
			{
				$responseObject['resultMessage']							= "Unable to retrieve a source list for user $liuAccountID";
			}
		}
	    catch (PDOException $e) 
	    {
	    	errorLog($e -> getMessage());
	    	
	    	$responseObject['resultMessage']								= "Error: Unable to retrieve a source list for user $liuAccountID due to a database error: ".$e -> getMessage();
	
			die();
		}
		
		$totalPortfolioValueArray['totalPortfolioValue']					= $totalPortfolioValue;
		$responseObject['totalPortfolioValueArray']							= $totalPortfolioValueArray;
		
		errorLog(json_encode($responseObject), $GLOBALS['debugCoreFunctionality']);
		
		return $responseObject;
	}
	
	function getProfitStanceLedgerBasedBalanceForUserAccountAndExchange($accountID, $transactionSourceID, $globalCurrentDate, $sid, $dbh)
	{
		$balanceDateObject															= new DateTime();
		$balanceDateObject -> modify('-1 day');
		$balanceDate																	= date_format($balanceDateObject, "Y-m-d");
		
		errorLog("SELECT
	SUM(ProfitStanceLedgerEntries.cryptoCurrencyAmount) AS resultingBalance,
	ProfitStanceLedgerEntries.FK_AssetTypeID,
	AssetTypes.assetTypeLabel,
	AssetTypes.description,
	AssetTypes.colorCode,
	CURRENT_DATE() AS currentDate
FROM
	ProfitStanceLedgerEntries
	INNER JOIN AssetTypes ON ProfitStanceLedgerEntries.FK_AssetTypeID = AssetTypes.assetTypeID
WHERE
	ProfitStanceLedgerEntries.FK_TransactionSourceID = $transactionSourceID AND
	ProfitStanceLedgerEntries.FK_AccountID = $accountID
GROUP BY
	ProfitStanceLedgerEntries.FK_AssetTypeID", 2);
		
		$responseObject														= array();
		$responseObject['retrievedCurrencyListWithBalanceForSource']			= false;
		$responseObject['balanceDate']										= $globalCurrentDate;
		
		$totalBalanceForExchange												= 0;
		
		$otherBalance														= 0;
		
		$foundBTC															= false;
		$foundBCH															= false;
		$foundETH															= false;
		$foundLTC															= false;
		$foundZEC															= false;
		
		$foundOther															= false;
		
		try
		{		
			$getBalancesForAssetTypes										= $dbh -> prepare("SELECT
	SUM(ProfitStanceLedgerEntries.cryptoCurrencyAmount) AS resultingBalance,
	ProfitStanceLedgerEntries.FK_AssetTypeID,
	AssetTypes.assetTypeLabel,
	AssetTypes.description,
	AssetTypes.colorCode,
	CURRENT_DATE() AS currentDate
FROM
	ProfitStanceLedgerEntries
	INNER JOIN AssetTypes ON ProfitStanceLedgerEntries.FK_AssetTypeID = AssetTypes.assetTypeID
WHERE
	ProfitStanceLedgerEntries.FK_TransactionSourceID = :transactionSourceID AND
	ProfitStanceLedgerEntries.FK_AccountID = :accountID
GROUP BY
	ProfitStanceLedgerEntries.FK_AssetTypeID");

			$getBalancesForAssetTypes -> bindValue(':accountID', $accountID);
			$getBalancesForAssetTypes -> bindValue(':transactionSourceID', $transactionSourceID);
						
			if ($getBalancesForAssetTypes -> execute() && $getBalancesForAssetTypes -> rowCount() > 0)
			{
				$responseObject['retrievedCurrencyListWithBalanceForSource']	= true;
				
				while ($row 	= $getBalancesForAssetTypes -> fetchObject())
				{
					$dataForAssetTypeArray									= array();
					
					$FK_AssetTypeID											= $row -> FK_AssetTypeID;
					$assetTypeLabel											= $row -> assetTypeLabel;
					$resultingBalance										= $row -> resultingBalance;
					$currentDate												= $row -> currentDate;
					$description												= $row -> description;
					$colorCode												= $row -> colorCode;
					
					$usdBalanceForAsset										= 0;
					$spotPriceAtTimeOfCalculation							= 0;
					
					$cascadeRetrieveSpotPriceResponseObject					= getSpotPriceForAssetPairUsingSourceCascade($FK_AssetTypeID, 2, $balanceDate, 10, "Kraken price by date", $dbh);
					
					if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
					{
						$spotPriceAtTimeOfCalculation						= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
						
						$usdBalanceForAsset									= $resultingBalance * $spotPriceAtTimeOfCalculation;
						
						$totalBalanceForExchange								= $totalBalanceForExchange + $usdBalanceForAsset;
					}
					
					if ($FK_AssetTypeID == 1 || $FK_AssetTypeID == 3 || $FK_AssetTypeID == 4 || $FK_AssetTypeID == 5 || $FK_AssetTypeID == 174)
					{
						$position											= -1;
						
						if ($FK_AssetTypeID == 1)
						{
							$foundBTC										= true;
							$position										= 1;	
						}
						else if ($FK_AssetTypeID == 2)
						{
							$foundBTC										= true;
							$position										= 1;	
						}
						else if ($FK_AssetTypeID == 3)
						{
							$foundBCH										= true;
							$position										= 2;	
						}
						else if ($FK_AssetTypeID == 4)
						{
							$foundLTC										= true;
							$position										= 0;	
						}
						else if ($FK_AssetTypeID == 5)
						{
							$foundETH										= true;	
							$position										= 3;
						}
						else if ($FK_AssetTypeID == 174)
						{
							$foundZEC										= true;
							$position										= 4;	
						}
						
						$responseObject[$position]							= populateAssetArrayForExchangeOrWallet($description, $usdBalanceForAsset, $colorCode);		
					}
					else
					{
						$foundOther											= true;
						$otherBalance										= $otherBalance + $usdBalanceForAsset;
					}
				}
				
				if ($foundLTC == false)
				{
					$responseObject[0]										= populateAssetArrayForExchangeOrWallet("Litecoin", 0, "#4F3FC7");	
				}
				
				if ($foundBTC == false)
				{
					$responseObject[1]										= populateAssetArrayForExchangeOrWallet("Bitcoin", 0, "#E52A70");	
				}
				
				if ($foundBCH == false)
				{
					$responseObject[2]										= populateAssetArrayForExchangeOrWallet("Bitcoin Cash", 0, "#A900CC");	
				}
				
				if ($foundETH == false)
				{
					$responseObject[3]										= populateAssetArrayForExchangeOrWallet("Etherium", 0, "#DF7D00");	
				}
				
				if ($foundZEC == false)
				{
					$responseObject[4]										= populateAssetArrayForExchangeOrWallet("ZCash", 0, "#7BBD0E");	
				}
				
				$responseObject[5]											= populateAssetArrayForExchangeOrWallet("All Others", $otherBalance, "#07C2DD");
			}
			else
			{
				$responseObject[]											= populateAssetArrayForExchangeOrWallet("Litecoin", 0, "#4F3FC7");
				$responseObject[]											= populateAssetArrayForExchangeOrWallet("Bitcoin", 0, "#E52A70");
				$responseObject[]											= populateAssetArrayForExchangeOrWallet("Bitcoin Cash", 0, "#A900CC");
				$responseObject[]											= populateAssetArrayForExchangeOrWallet("Etherium", 0, "#DF7D00");
				$responseObject[]											= populateAssetArrayForExchangeOrWallet("ZCash", 0, "#7BBD0E");
				$responseObject[]											= populateAssetArrayForExchangeOrWallet("All Others", 0, "#07C2DD");
			}
		}
	    catch (PDOException $e) 
	    {
	    		$responseObject['resultMessage']							= "Could not retrieve balance for asset types for $accountID due to a database error: ".$e -> getMessage();
			
			errorLog($e -> getMessage());
	
			die();
		}
		
		$responseObject['totalPortfolioValue']								= $totalBalanceForExchange;
	
		return $responseObject;	
	}
	
	function getProfitStanceLedgerBasedBalanceForUserAccountAndExchangeTileWithoutGrouping($accountID, $exchangeTileID, $globalCurrentDate, $sid, $dbh)
	{
		$balanceDateObject													= new DateTime();
		$balanceDateObject -> modify('-1 day');
		$balanceDate														= date_format($balanceDateObject, "Y-m-d");
		
		errorLog("SELECT
	SUM(ProfitStanceLedgerEntries.cryptoCurrencyAmount) AS resultingBalance,
	ProfitStanceLedgerEntries.FK_AssetTypeID,
	AssetTypes.assetTypeLabel,
	AssetTypes.description,
	AssetTypes.colorCode,
	CURRENT_DATE() AS currentDate
FROM
	ProfitStanceLedgerEntries
	INNER JOIN AssetTypes ON ProfitStanceLedgerEntries.FK_AssetTypeID = AssetTypes.assetTypeID
WHERE
	ProfitStanceLedgerEntries.FK_ExchangeTileID = $exchangeTileID AND
	ProfitStanceLedgerEntries.FK_AccountID = $accountID
GROUP BY
	ProfitStanceLedgerEntries.FK_AssetTypeID", 2);
		
		$responseObject														= array();
		$responseObject['retrievedCurrencyListWithBalanceForSource']		= false;
		$responseObject['balanceDate']										= $globalCurrentDate;
		
		$totalBalanceForExchange											= 0;
		
		try
		{		
			$getBalancesForAssetTypes										= $dbh -> prepare("SELECT
	SUM(ProfitStanceLedgerEntries.cryptoCurrencyAmount) AS resultingBalance,
	ProfitStanceLedgerEntries.FK_AssetTypeID,
	AssetTypes.assetTypeLabel,
	AssetTypes.description,
	AssetTypes.colorCode,
	CURRENT_DATE() AS currentDate
FROM
	ProfitStanceLedgerEntries
	INNER JOIN AssetTypes ON ProfitStanceLedgerEntries.FK_AssetTypeID = AssetTypes.assetTypeID
WHERE
	ProfitStanceLedgerEntries.FK_ExchangeTileID = :exchangeTileID AND
	ProfitStanceLedgerEntries.FK_AccountID = :accountID
GROUP BY
	ProfitStanceLedgerEntries.FK_AssetTypeID");

			$getBalancesForAssetTypes -> bindValue(':accountID', $accountID);
			$getBalancesForAssetTypes -> bindValue(':exchangeTileID', $exchangeTileID);
						
			if ($getBalancesForAssetTypes -> execute() && $getBalancesForAssetTypes -> rowCount() > 0)
			{
				$responseObject['retrievedCurrencyListWithBalanceForSource']= true;
				
				$tempResultArray												= array();
				
				while ($row = $getBalancesForAssetTypes -> fetchObject())
				{
					$dataForAssetTypeArray									= array();
					
					$FK_AssetTypeID											= $row -> FK_AssetTypeID;
					$assetTypeLabel											= $row -> assetTypeLabel;
					$resultingBalance										= $row -> resultingBalance;
					$currentDate											= $row -> currentDate;
					$description											= $row -> description;
					$colorCode												= $row -> colorCode;
					
					$usdBalanceForAsset										= 0;
					$spotPriceAtTimeOfCalculation							= 0;
					
					$cascadeRetrieveSpotPriceResponseObject					= getSpotPriceForAssetPairUsingSourceCascade($FK_AssetTypeID, 2, $balanceDate, 14, "CoinGecko price by date", $dbh);
					
					if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
					{
						$spotPriceAtTimeOfCalculation						= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
						
						$usdBalanceForAsset									= $resultingBalance * $spotPriceAtTimeOfCalculation;
						
						$totalBalanceForExchange								= $totalBalanceForExchange + $usdBalanceForAsset;
					}
					
					errorLog("getProfitStanceLedgerBasedBalanceForUserAccountAndExchangeTileWithoutGrouping FK_AssetTypeID: $FK_AssetTypeID, assetTypeLabel: $assetTypeLabel, resultingBalance: $resultingBalance, currentDate: $currentDate, description: $description, colorCode: $colorCode, usdBalanceForAsset: $usdBalanceForAsset, spotPriceAtTimeOfCalculation: $spotPriceAtTimeOfCalculation, totalBalanceForExchange: $totalBalanceForExchange");
					
					$position												= (int) ceil($usdBalanceForAsset);
					
					if (!array_key_exists("$usdBalanceForAsset", $tempResultArray))
					{
						$tempResultArray[$usdBalanceForAsset]				= populateAssetArrayForExchangeTile($description, $usdBalanceForAsset, $colorCode);		
					}
					else
					{
						$position											= $usdBalanceForAsset + 0.01;
						
						$tempResultArray[$position]							= populateAssetArrayForExchangeTile($description, $usdBalanceForAsset, $colorCode);		
					}	
				}
				
				krsort($tempResultArray, 1);
				
				$arrayCount													= 0;
				
				$positionOfSix												= 1;
				
				foreach($tempResultArray as $amountAsPositionIndex => $contentArray)
				{
					$responseObject[$arrayCount]							= $contentArray;
					// $responseObject[$coinPosition]['currencyName']		= $tempResultArray[$i - 1]['currencyName'];
					// $responseObject[$coinPosition]['amountInUSD']		= $tempResultArray[$i - 1]['amountInUSD'];
					
					if ($positionOfSix == 1)
					{
						$responseObject[$arrayCount]['color']				= "#4f3fc7";		
					}
					else if ($positionOfSix == 2)
					{
						$responseObject[$arrayCount]['color']				= "#a900cc";		
					}
					else if ($positionOfSix == 3)
					{
						$responseObject[$arrayCount]['color']				= "#e52a70";		
					}
					else if ($positionOfSix == 4)
					{
						$responseObject[$arrayCount]['color']				= "#df7d00";		
					}
					else if ($positionOfSix == 5)
					{
						$responseObject[$arrayCount]['color']				= "#7bbd0e";		
					}
					else if ($positionOfSix == 6)
					{
						$responseObject[$arrayCount]['color']				= "#07c2dd";		
					}	
					
					$positionOfSix++	;
					
					if ($positionOfSix == 7)
					{
						$positionOfSix										= 1;
					}
					
					$arrayCount++;	
				}
			}
		}
	    catch (PDOException $e) 
	    {
	    	$responseObject['resultMessage']								= "Could not retrieve balance for asset types for $accountID due to a database error: ".$e -> getMessage();
			
			errorLog($e -> getMessage());
	
			die();
		}
		
		$responseObject['totalPortfolioValue']								= $totalBalanceForExchange;
	
		return $responseObject;	
	}
	
	function getProfitStanceLedgerBasedBalanceForUserAccountAndExchangeWithoutGrouping($accountID, $transactionSourceID, $globalCurrentDate, $sid, $dbh)
	{
		$balanceDateObject													= new DateTime();
		$balanceDateObject -> modify('-1 day');
		$balanceDate														= date_format($balanceDateObject, "Y-m-d");
		
		errorLog("SELECT
	SUM(ProfitStanceLedgerEntries.cryptoCurrencyAmount) AS resultingBalance,
	ProfitStanceLedgerEntries.FK_AssetTypeID,
	AssetTypes.assetTypeLabel,
	AssetTypes.description,
	AssetTypes.colorCode,
	CURRENT_DATE() AS currentDate
FROM
	ProfitStanceLedgerEntries
	INNER JOIN AssetTypes ON ProfitStanceLedgerEntries.FK_AssetTypeID = AssetTypes.assetTypeID
WHERE
	ProfitStanceLedgerEntries.FK_TransactionSourceID = $transactionSourceID AND
	ProfitStanceLedgerEntries.FK_AccountID = $accountID
GROUP BY
	ProfitStanceLedgerEntries.FK_AssetTypeID", 2);
		
		$responseObject														= array();
		$responseObject['retrievedCurrencyListWithBalanceForSource']		= false;
		$responseObject['balanceDate']										= $globalCurrentDate;
		
		$totalBalanceForExchange											= 0;
		
		try
		{		
			$getBalancesForAssetTypes										= $dbh -> prepare("SELECT
	SUM(ProfitStanceLedgerEntries.cryptoCurrencyAmount) AS resultingBalance,
	ProfitStanceLedgerEntries.FK_AssetTypeID,
	AssetTypes.assetTypeLabel,
	AssetTypes.description,
	AssetTypes.colorCode,
	CURRENT_DATE() AS currentDate
FROM
	ProfitStanceLedgerEntries
	INNER JOIN AssetTypes ON ProfitStanceLedgerEntries.FK_AssetTypeID = AssetTypes.assetTypeID
WHERE
	ProfitStanceLedgerEntries.FK_TransactionSourceID = :transactionSourceID AND
	ProfitStanceLedgerEntries.FK_AccountID = :accountID
GROUP BY
	ProfitStanceLedgerEntries.FK_AssetTypeID");

			$getBalancesForAssetTypes -> bindValue(':accountID', $accountID);
			$getBalancesForAssetTypes -> bindValue(':transactionSourceID', $transactionSourceID);
						
			if ($getBalancesForAssetTypes -> execute() && $getBalancesForAssetTypes -> rowCount() > 0)
			{
				$responseObject['retrievedCurrencyListWithBalanceForSource']= true;
				
				$tempResultArray											= array();
				
				while ($row = $getBalancesForAssetTypes -> fetchObject())
				{
					$dataForAssetTypeArray									= array();
					
					$FK_AssetTypeID											= $row -> FK_AssetTypeID;
					$assetTypeLabel											= $row -> assetTypeLabel;
					$resultingBalance										= $row -> resultingBalance;
					$currentDate											= $row -> currentDate;
					$description											= $row -> description;
					$colorCode												= $row -> colorCode;
					
					$usdBalanceForAsset										= 0;
					$spotPriceAtTimeOfCalculation							= 0;
					
					$cascadeRetrieveSpotPriceResponseObject					= getSpotPriceForAssetPairUsingSourceCascade($FK_AssetTypeID, 2, $balanceDate, 14, "CoinGecko price by date", $dbh);
					
					if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
					{
						$spotPriceAtTimeOfCalculation						= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
						
						$usdBalanceForAsset									= $resultingBalance * $spotPriceAtTimeOfCalculation;
						
						$totalBalanceForExchange							= $totalBalanceForExchange + $usdBalanceForAsset;
					}
					
					$position												= (int) ceil($usdBalanceForAsset);
					
					if (!array_key_exists("$usdBalanceForAsset", $tempResultArray))
					{
						$tempResultArray[$usdBalanceForAsset]				= populateAssetArrayForExchangeOrWallet($description, $usdBalanceForAsset, $colorCode);		
					}
					else
					{
						$position											= $usdBalanceForAsset + 0.01;
						
						$tempResultArray[$position]							= populateAssetArrayForExchangeOrWallet($description, $usdBalanceForAsset, $colorCode);		
					}	
				}
				
				krsort($tempResultArray, 1);
				
				$arrayCount													= 0;
				
				$positionOfSix												= 1;
				
				foreach($tempResultArray as $amountAsPositionIndex => $contentArray)
				{
					$responseObject[$arrayCount]							= $contentArray;
					// $responseObject[$coinPosition]['currencyName']		= $tempResultArray[$i - 1]['currencyName'];
					// $responseObject[$coinPosition]['amountInUSD']		= $tempResultArray[$i - 1]['amountInUSD'];
					
					if ($positionOfSix == 1)
					{
						$responseObject[$arrayCount]['color']				= "#4f3fc7";		
					}
					else if ($positionOfSix == 2)
					{
						$responseObject[$arrayCount]['color']				= "#a900cc";		
					}
					else if ($positionOfSix == 3)
					{
						$responseObject[$arrayCount]['color']				= "#e52a70";		
					}
					else if ($positionOfSix == 4)
					{
						$responseObject[$arrayCount]['color']				= "#df7d00";		
					}
					else if ($positionOfSix == 5)
					{
						$responseObject[$arrayCount]['color']				= "#7bbd0e";		
					}
					else if ($positionOfSix == 6)
					{
						$responseObject[$arrayCount]['color']				= "#07c2dd";		
					}	
					
					$positionOfSix++	;
					
					if ($positionOfSix == 7)
					{
						$positionOfSix										= 1;
					}
					
					$arrayCount++;	
				}
			}
		}
	    catch (PDOException $e) 
	    {
	    	$responseObject['resultMessage']								= "Could not retrieve balance for asset types for $accountID due to a database error: ".$e -> getMessage();
			
			errorLog($e -> getMessage());
	
			die();
		}
		
		$responseObject['totalPortfolioValue']								= $totalBalanceForExchange;
	
		return $responseObject;	
	}
	
	function getProfitStanceLedgerBasedTotalPortfolioValueForUserAccountForDateArrayAndQuoteCurrencyAndDataSource($accountID, $dateArray, $dataSource, $quoteCurrency, $sid, $dbh)
	{
		$responseObject														= array();
		
		try
		{		
			$getCryptoTransactionTotals										= $dbh -> prepare("SELECT
	SUM(ProfitStanceLedgerEntries.cryptoCurrencyAmount) AS resultingBalance,
	CASE
		WHEN ProfitStanceLedgerEntries.FK_AssetTypeID = 2 THEN SUM(ProfitStanceLedgerEntries.cryptoCurrencyAmount)
		ELSE SUM(ProfitStanceLedgerEntries.cryptoCurrencyAmount * DailyCryptoSpotPrices.fiatCurrencySpotPrice) 
	END AS resultingBalanceUSD,
	ProfitStanceLedgerEntries.FK_AssetTypeID
FROM
	ProfitStanceLedgerEntries
	LEFT JOIN DailyCryptoSpotPrices ON 
		ProfitStanceLedgerEntries.FK_AssetTypeID = DailyCryptoSpotPrices.FK_CryptoAssetID AND 
		DailyCryptoSpotPrices.FK_FiatCurrencyAssetID = :quoteCurrency AND
		DailyCryptoSpotPrices.FK_DataSource = :dataSource AND
		DailyCryptoSpotPrices.priceDate = LEFT(:balanceDate, 10)
WHERE
	ProfitStanceLedgerEntries.FK_AccountID = :accountID AND
	LEFT(ProfitStanceLedgerEntries.transactionDate, 10) <= LEFT(:balanceDate, 10)
GROUP BY
	ProfitStanceLedgerEntries.FK_AssetTypeID");
	
			foreach ($dateArray as $balanceDate)
			{
				errorLog($balanceDate);
				
				$getCryptoTransactionTotals -> bindValue(':accountID', $accountID);
				$getCryptoTransactionTotals -> bindValue(':balanceDate', $balanceDate);
				$getCryptoTransactionTotals -> bindValue(':dataSource', $dataSource);
				$getCryptoTransactionTotals -> bindValue(':quoteCurrency', $quoteCurrency);
						
				$balanceForDateArray											= array();
					
				$dateObject													= new DateTime($balanceDate);
				$formattedBalanceDate										= date_format($dateObject, "Y/m/d");
				
				$balanceForDateArray['date']									= $formattedBalanceDate;
				
				$totalBalanceForDate											= 0;
				
				if ($getCryptoTransactionTotals -> execute() && $getCryptoTransactionTotals -> rowCount() > 0)
				{
					while ($row 	= $getCryptoTransactionTotals -> fetchObject())
					{
						$totalBalanceForDate									= $totalBalanceForDate + $row -> resultingBalanceUSD;	
					}				
				}
				
				$balanceForDateArray['value']								= $totalBalanceForDate;	
					
				$responseObject[]											= $balanceForDateArray;	
			}
		}
	    catch (PDOException $e) 
	    {
	    		$responseObject['resultMessage']									= "Could not retrieve total balance records for $accountID due to a database error: ".$e -> getMessage();
			
			errorLog($responseObject['resultMessage']);
	
			die();
		}
	
		return $responseObject;	
	}
	
	function getProfitStanceLedgerBasedPortfolioValueForUserAccountBySourceType($accountID, $totalPortfolioBalance, $sid, $dbh)
	{
		$responseObject																= array();
		
		$balanceDateObject															= new DateTime();
		$balanceDateObject -> modify('-1 day');
		$balanceDate																	= date_format($balanceDateObject, "Y-m-d");
		
		try
		{		
			$getTotalsForUser														= $dbh -> prepare("SELECT
	(SUM(ProfitStanceLedgerEntries.cryptoCurrencyAmount * DailyCryptoSpotPrices.fiatCurrencySpotPrice) / :totalPortfolioBalance) * 100 AS totalAssetBalancePercentageForSource,
	ProfitStanceLedgerEntries.FK_TransactionSourceID,
	TransactionSources.transactionSourceLabel
FROM
	ProfitStanceLedgerEntries
	INNER JOIN TransactionSources ON ProfitStanceLedgerEntries.FK_TransactionSourceID = TransactionSources.transactionSourceID AND TransactionSources.languageCode = 'EN'
	LEFT JOIN DailyCryptoSpotPrices ON 
		ProfitStanceLedgerEntries.FK_AssetTypeID = DailyCryptoSpotPrices.FK_CryptoAssetID AND 
		DailyCryptoSpotPrices.FK_FiatCurrencyAssetID = 2 AND
		DailyCryptoSpotPrices.FK_DataSource = 2 AND
		DailyCryptoSpotPrices.priceDate = LEFT(:balanceDate, 10)
WHERE
	ProfitStanceLedgerEntries.FK_AccountID = :accountID AND
	LEFT(ProfitStanceLedgerEntries.transactionDate, 10) <= LEFT(:balanceDate, 10)
GROUP BY
	ProfitStanceLedgerEntries.FK_TransactionSourceID
ORDER BY
	totalAssetBalancePercentageForSource DESC");
	
			$getTotalsForUser -> bindValue(':accountID', $accountID);
			$getTotalsForUser -> bindValue(':totalPortfolioBalance', $totalPortfolioBalance);
			$getTotalsForUser -> bindValue(':balanceDate', $balanceDate);
		
			if ($getTotalsForUser -> execute() && $getTotalsForUser -> rowCount() > 0)
			{
				$countNumber															= 0;
				$otherTotal															= 0;
				
				$lastTransactionSourceLabel											= "";
				
				while ($row = $getTotalsForUser -> fetchObject())
				{
					$totalAssetBalanceForSource										= $row -> totalAssetBalancePercentageForSource;
					$transactionSourceLabel											= $row -> transactionSourceLabel;
					
					$lastTransactionSourceLabel										= $transactionSourceLabel;
					
					if ($countNumber < 4 && $totalAssetBalanceForSource > 0)
					{
						$responseObject['currentBalance'][$transactionSourceLabel]	= round($totalAssetBalanceForSource, 2);	
					}
					else
					{
						$otherTotal													= $otherTotal + $totalAssetBalanceForSource;	
					}
						
					$countNumber++;
				}
				
				if ($countNumber == 1)
				{
					$responseObject['currentBalance'][$lastTransactionSourceLabel]	= 100.00;	
				}
				
				$responseObject['currentBalance']['All Others']						= round($otherTotal, 2);		
			}
		}
	    catch (PDOException $e) 
	    {
	    		errorLog($e -> getMessage());
	
			die();
		}
			
		return $responseObject;	
	}
	
	function getProfitStanceLedgerBasedPortfolioValueForUserAccountByCoin($accountID, $totalPortfolioBalance, $sid, $dbh)
	{
		$responseObject														= array();
		
		$balanceDateObject													= new DateTime();
		$balanceDateObject -> modify('-1 day');
		$balanceDate														= date_format($balanceDateObject, "Y-m-d");					
		
		try
		{		
			$getTotalsForUser												= $dbh -> prepare("SELECT
	CASE
		WHEN ProfitStanceLedgerEntries.FK_AssetTypeID = 2 THEN SUM(ProfitStanceLedgerEntries.cryptoCurrencyAmount) / :totalPortfolioBalance
		ELSE SUM(ProfitStanceLedgerEntries.cryptoCurrencyAmount * DailyCryptoSpotPrices.fiatCurrencySpotPrice) / :totalPortfolioBalance 
	END AS totalAssetBalancePercentageForSource,
	ProfitStanceLedgerEntries.FK_AssetTypeID,
	AssetTypes.description
FROM
	ProfitStanceLedgerEntries
	INNER JOIN AssetTypes ON ProfitStanceLedgerEntries.FK_AssetTypeID = AssetTypes.assetTypeID
	LEFT JOIN DailyCryptoSpotPrices ON 
		ProfitStanceLedgerEntries.FK_AssetTypeID = DailyCryptoSpotPrices.FK_CryptoAssetID AND 
		DailyCryptoSpotPrices.FK_FiatCurrencyAssetID = 2 AND
		DailyCryptoSpotPrices.FK_DataSource = 2 AND
		DailyCryptoSpotPrices.priceDate = LEFT(:balanceDate, 10)
WHERE
	ProfitStanceLedgerEntries.FK_AccountID = :accountID AND
	LEFT(ProfitStanceLedgerEntries.transactionDate, 10) <= LEFT(:balanceDate, 10)
GROUP BY
	ProfitStanceLedgerEntries.FK_AssetTypeID
ORDER BY
	totalAssetBalancePercentageForSource DESC");
	
			$getTotalsForUser -> bindValue(':accountID', $accountID);
			$getTotalsForUser -> bindValue(':totalPortfolioBalance', $totalPortfolioBalance);
			$getTotalsForUser -> bindValue(':balanceDate', $balanceDate);
		
			if ($getTotalsForUser -> execute() && $getTotalsForUser -> rowCount() > 0)
			{
				$countNumber												= 0;
				$otherTotal													= 0;
				
				while ($row = $getTotalsForUser -> fetchObject())
				{
					$totalAssetBalancePercentageForAsset					= $row -> totalAssetBalancePercentageForSource;
					$assetTypeLabel											= $row -> description;
					
					if ($countNumber < 4 && $totalAssetBalancePercentageForAsset > 0)
					{
						$responseObject['currentBalance'][$assetTypeLabel]	= 100 * $totalAssetBalancePercentageForAsset;	
					}
					else
					{
						$otherTotal											= $otherTotal + $totalAssetBalancePercentageForAsset;	
					}
						
					$countNumber++;
				}
				
				$responseObject['currentBalance']['All Others']				= 100 * $otherTotal;		
			}
		}
	    catch (PDOException $e) 
	    {
	   	 	errorLog($e -> getMessage());
	
			die();
		}
			
		return $responseObject;	
	}
	
	function updateProfitStanceExchangeTileHeightAttribute($accountID, $exchangeTileID, $height, $sid, $dbh)
	{
		$responseObject														= array();
		$responseObject['updatedRecord']									= false;
		
		try
		{		
			$updateProfitStanceExchangeTileHeightAttribute					= $dbh -> prepare("UPDATE
		ExchangeTiles
	SET
		tileHeight = :tileHeight
	WHERE
		FK_AccountID = :FK_AccountID AND
		exchangeTileID = :exchangeTileID");
	
			$updateProfitStanceExchangeTileHeightAttribute -> bindValue(':tileHeight', $height);
			$updateProfitStanceExchangeTileHeightAttribute -> bindValue(':FK_AccountID', $accountID);
			$updateProfitStanceExchangeTileHeightAttribute -> bindValue(':exchangeTileID', $exchangeTileID);
			
		
			if ($updateProfitStanceExchangeTileHeightAttribute -> execute())
			{
				$responseObject['updatedRecord']							= true;		
			}
		}
	    catch (PDOException $e) 
	    {
	    	errorLog($e -> getMessage());
	
			die();
		}
			
		return $responseObject;	
	}
	
	function updateProfitStanceExchangeTileLabelAttribute($accountID, $exchangeTileID, $labelValue, $userEncryptionID, $sid, $dbh)
	{
		$responseObject														= array();
		$responseObject['updatedRecord']									= false;
		
		try
		{		
			$updateProfitStanceExchangeTileLabelAttribute					= $dbh -> prepare("UPDATE
		ExchangeTiles
	SET
		encryptedTileLabel = AES_ENCRYPT(:tileLabel, UNHEX(SHA2(:userEncryptionKey,512)))
	WHERE
		FK_AccountID = :FK_AccountID AND
		exchangeTileID = :exchangeTileID");
	
			$updateProfitStanceExchangeTileLabelAttribute -> bindValue(':tileLabel', $labelValue);
			$updateProfitStanceExchangeTileLabelAttribute -> bindValue(':FK_AccountID', $accountID);
			$updateProfitStanceExchangeTileLabelAttribute -> bindValue(':exchangeTileID', $exchangeTileID);
			$updateProfitStanceExchangeTileLabelAttribute -> bindValue(':userEncryptionKey', $userEncryptionID);
		
			if ($updateProfitStanceExchangeTileLabelAttribute -> execute())
			{
				$responseObject['updatedRecord']							= true;		
			}
		}
	    catch (PDOException $e) 
	    {
	    	errorLog($e -> getMessage());
	
			die();
		}
			
		return $responseObject;	
	}
	
	function updateProfitStanceExchangeNativeAccountIdentifierAttribute($accountID, $exchangeTileID, $nativeAccountIdentifier, $userEncryptionID, $sid, $dbh)
	{
		$responseObject														= array();
		$responseObject['updatedRecord']									= false;
		
		try
		{		
			$updateProfitStanceExchangeNativeAccountIdentifierAttribute		= $dbh -> prepare("UPDATE
		ExchangeTiles
	SET
		encryptedNativeAccountIDValue = AES_ENCRYPT(:nativeAccountIDValue, UNHEX(SHA2(:userEncryptionKey,512)))
	WHERE
		FK_AccountID = :FK_AccountID AND
		exchangeTileID = :exchangeTileID");
	
			$updateProfitStanceExchangeNativeAccountIdentifierAttribute -> bindValue(':nativeAccountIDValue', $nativeAccountIdentifier);
			$updateProfitStanceExchangeNativeAccountIdentifierAttribute -> bindValue(':FK_AccountID', $accountID);
			$updateProfitStanceExchangeNativeAccountIdentifierAttribute -> bindValue(':exchangeTileID', $exchangeTileID);
			$updateProfitStanceExchangeNativeAccountIdentifierAttribute -> bindValue(':userEncryptionKey', $userEncryptionID);
		
			if ($updateProfitStanceExchangeNativeAccountIdentifierAttribute -> execute())
			{
				$responseObject['updatedRecord']							= true;		
			}
		}
	    catch (PDOException $e) 
	    {
	    	errorLog($e -> getMessage());
	
			die();
		}
			
		return $responseObject;	
	}
	
	function updateProfitStanceExchangeTileOrderAttribute($accountID, $jsonObject, $sid, $dbh)
	{
		errorLog("calling updateProfitStanceExchangeTileOrderAttribute $accountID ".json_encode($jsonObject));
		
		$responseObject														= array();
		
		try
	    {
			$updateProfitStanceExchangeTileAttribute						= $dbh -> prepare("UPDATE
		ExchangeTiles
	SET
		displayOrder = :order
	WHERE
		FK_AccountID = :FK_AccountID AND
		exchangeTileID = :exchangeTileID");
		   
		    $name															= "exchangeAndWalletTileAttributes";
		    
	    	if (!empty($jsonObject -> $name))
	    	{
		    	$jsonMDArray 												= $jsonObject -> $name; 
		    	
		    	errorLog("tile order data ".json_encode($jsonMDArray));
		    	
		    	errorLog("count ".count($jsonMDArray), $GLOBALS['debugCoreFunctionality']);
		    	
		    	foreach ($jsonMDArray as $genericTransationArray) 
		    	{
			    	$exchangeTileID											= $genericTransationArray[0];
					$orderValue												= $genericTransationArray[1];
			    
			    	errorLog("exchangeTileID: ".$exchangeTileID);
			    	
					// $orderValue											= urldecode( strip_tags( trim( $genericTransationObject -> orderValue ) ) );
			    
					$updateProfitStanceExchangeTileAttribute -> bindValue(':order', $orderValue);
					$updateProfitStanceExchangeTileAttribute -> bindValue(':FK_AccountID', $accountID);
					$updateProfitStanceExchangeTileAttribute -> bindValue(':exchangeTileID', $exchangeTileID);
		
					if ($updateProfitStanceExchangeTileAttribute -> execute())
					{
						$responseObject[$exchangeTileID]					= true;		
					}
				}
	    	}
	    	else
	    	{
		    	errorLog("ERROR: JSON object empty or $name not found");	
	    	}	
    	}
    	catch (Exception $e)
    	{
	    	errorLog("ERROR: $name not found in JSON object");	
    	}
			
		return $responseObject;	
	}
	
	// END new ProfitStance Ledger related code
	
	// TAX RELATED FUNCTIONS
	
	function setUserFilingStatusForTaxYear($authorID, $accountID, $taxYear, $filingStatusID, $taxableIncomeForYear, $sid, $globalCurrentDate, $dbh) 
	{
		$responseObject																					= array();
		$responseObject['setUserFilingStatusForTaxYear']												= false;
		
		try
		{		
			$setUserFilingStatusForTaxYear																= $dbh -> prepare("REPLACE FilingStatusForUserAndTaxYear
(
	FK_UserAccountID,
	taxYear,
	FK_FilingStatusID,
	taxableIncomeForYear,
	FK_AuthorID,
	creationDate
)
VALUES
(
	:userAccountID,
	:taxYear,
	:filingStatusID,
	:taxableIncomeForYear,
	:authorID,
	:creationDate
)");

			$setUserFilingStatusForTaxYear -> bindValue(':userAccountID', $accountID);
			$setUserFilingStatusForTaxYear -> bindValue(':taxYear', $taxYear);
			$setUserFilingStatusForTaxYear -> bindValue(':filingStatusID', $filingStatusID);
			$setUserFilingStatusForTaxYear -> bindValue(':taxableIncomeForYear', $taxableIncomeForYear);
			$setUserFilingStatusForTaxYear -> bindValue(':authorID', $authorID);
			$setUserFilingStatusForTaxYear -> bindValue(':creationDate', $globalCurrentDate);

			if ($setUserFilingStatusForTaxYear -> execute())
			{
				$responseObject['setUserFilingStatusForTaxYear']										= true;	
			}
		}
	    catch (PDOException $e) 
	    {
	    	errorLog($e -> getMessage());
	
			die();
		}
		
		return $responseObject;
	}
	
	// END TAX RELATED FUNCTIONS
	
	// CPA Portal related functions
	
	function createCPAClientAccount($cpaClientInviteRecordID, $validationHash, $password, $passwordStrengthLabel, $globalCurrentDate, $sid, $dbh)
	{
		$userEncryptionKey																				= "b9Gf98252c8!0aea1f(31c4c753d351";
		
		// @task - create function that generates and stores encryption key for user based on validation hash - use that as encryption key for account
		
		$responseObject																					= array();
		$responseObject['validationHashMatchesInviteRecordID']											= false;
		$responseObject['createdCPAClientAccount']														= false;
		$responseObject['createdCPAFirmToClientRelationshipRecord']										= false;
		$responseObject['createdCPAToClientRoleRecordForPrimaryCPA']									= false;
		$responseObject['createdCPAClientSecondaryCPARecord']											= false;
		$responseObject['createdCPAToClientRoleRecordForSecondaryCPA']									= false;
		$responseObject['createdCPAFirmToCPAClientSummaryForTaxYear']									= false;
		$responseObject['updatedValidationStatus']														= false;
		$responseObject['disabledOtherInvitationsToInvitee']											= false;
		
		errorLog("SELECT
	CPAClientInvites.creationDate,
	AES_DECRYPT(CPAClientInvites.encryptedEmailAddress, UNHEX(SHA2('$userEncryptionKey',512))) AS emailAddress,
	CPAClientInvites.FK_ActivationStatus,
	CPAClientInvites.FK_PrimaryCPAUserAccountID,
	CPAClientInvites.FK_CPAFirmID,
	AES_DECRYPT(CPAClientInvites.encryptedSid, UNHEX(SHA2('$userEncryptionKey',512))) AS sid,
	AES_DECRYPT(CPAClientInvites.encryptedTaxYear, UNHEX(SHA2('$userEncryptionKey',512))) AS taxYear,
	AES_DECRYPT(CPAClientInvites.encryptedFirstName, UNHEX(SHA2('$userEncryptionKey',512))) AS firstName,
	AES_DECRYPT(CPAClientInvites.encryptedMiddleName, UNHEX(SHA2('$userEncryptionKey',512))) AS middleName,
	AES_DECRYPT(CPAClientInvites.encryptedLastName, UNHEX(SHA2('$userEncryptionKey',512))) AS lastName,
	AES_DECRYPT(CPAClientInvites.encryptedClientNumber, UNHEX(SHA2('$userEncryptionKey',512))) AS clientNumber,
	AES_DECRYPT(CPAClientInvites.encryptedGovernmentIssuedIDValue, UNHEX(SHA2('$userEncryptionKey',512))) AS governmentIssuedIDValue,
	AES_DECRYPT(CPAClientInvites.encryptedStreetAddress1, UNHEX(SHA2('$userEncryptionKey',512))) AS address1,
	AES_DECRYPT(CPAClientInvites.encryptedStreetAddress2, UNHEX(SHA2('$userEncryptionKey',512))) AS address2,
	CPAClientInvites.FK_CityID,
	CPAClientInvites.FK_StateID,
	CPAClientInvites.FK_CountryID,
	AES_DECRYPT(CPAClientInvites.encryptedPostalCode, UNHEX(SHA2('$userEncryptionKey',512))) AS postalCode,
	AES_DECRYPT(CPAClientInvites.encryptedPhoneNumber, UNHEX(SHA2('$userEncryptionKey',512))) AS phoneNumber,
	AES_DECRYPT(CPAClientInvites.encryptedPhoneNumberExtension, UNHEX(SHA2('$userEncryptionKey',512))) AS phoneNumberExtension,
	CPAClientInvites.FK_PhoneNumberTypeID,
	CPAClientInvites.FK_FilingStatus,
	AES_DECRYPT(CPAClientInvites.encryptedTaxableIncome, UNHEX(SHA2('$userEncryptionKey',512))) AS taxableIncomeForTaxYear,
	AES_DECRYPT(CPAClientInvites.encryptedDirectoryName, UNHEX(SHA2('$userEncryptionKey',512))) AS directoryName,	
	AES_DECRYPT(CPAFirm.encryptedFirmLogoURL, UNHEX(SHA2('$userEncryptionKey',512))) AS firmLogoURL,
	AES_DECRYPT(CPAFirm.encryptedFirmName, UNHEX(SHA2('$userEncryptionKey',512))) AS firmName,	
	CPAFirm.customColor1HexValue,
	CPAFirm.customColor2HexValue
FROM
	CPAClientInvites
	INNER JOIN CPAFirm ON CPAClientInvites.FK_CPAFirmID = CPAFirm.cpaFirmID
WHERE
	CPAClientInvites.generatedCPAClientInviteHash = AES_ENCRYPT('$validationHash', UNHEX(SHA2('$userEncryptionKey',512)))");
	
	errorLog("UPDATE 
	CPAClientInvites
SET
	FK_ActivationStatus = 4,
	creationDate = '$globalCurrentDate'
WHERE
	cpaClientInviteRecordID = $cpaClientInviteRecordID");
		
		try
		{	
			$getValidationRequestData										= $dbh -> prepare("SELECT
	CPAClientInvites.creationDate,
	AES_DECRYPT(CPAClientInvites.encryptedEmailAddress, UNHEX(SHA2(:userEncryptionkey,512))) AS emailAddress,
	CPAClientInvites.FK_ActivationStatus,
	CPAClientInvites.FK_PrimaryCPAUserAccountID,
	CPAClientInvites.FK_CPAFirmID,
	AES_DECRYPT(CPAClientInvites.encryptedSid, UNHEX(SHA2(:userEncryptionkey,512))) AS sid,
	AES_DECRYPT(CPAClientInvites.encryptedTaxYear, UNHEX(SHA2(:userEncryptionkey,512))) AS taxYear,
	AES_DECRYPT(CPAClientInvites.encryptedFirstName, UNHEX(SHA2(:userEncryptionkey,512))) AS firstName,
	AES_DECRYPT(CPAClientInvites.encryptedMiddleName, UNHEX(SHA2(:userEncryptionkey,512))) AS middleName,
	AES_DECRYPT(CPAClientInvites.encryptedLastName, UNHEX(SHA2(:userEncryptionkey,512))) AS lastName,
	AES_DECRYPT(CPAClientInvites.encryptedClientNumber, UNHEX(SHA2(:userEncryptionkey,512))) AS clientNumber,
	AES_DECRYPT(CPAClientInvites.encryptedGovernmentIssuedIDValue, UNHEX(SHA2(:userEncryptionkey,512))) AS governmentIssuedIDValue,
	AES_DECRYPT(CPAClientInvites.encryptedStreetAddress1, UNHEX(SHA2(:userEncryptionkey,512))) AS address1,
	AES_DECRYPT(CPAClientInvites.encryptedStreetAddress2, UNHEX(SHA2(:userEncryptionkey,512))) AS address2,
	CPAClientInvites.FK_CityID,
	CPAClientInvites.FK_StateID,
	CPAClientInvites.FK_CountryID,
	AES_DECRYPT(CPAClientInvites.encryptedPostalCode, UNHEX(SHA2(:userEncryptionkey,512))) AS postalCode,
	AES_DECRYPT(CPAClientInvites.encryptedPhoneNumber, UNHEX(SHA2(:userEncryptionkey,512))) AS phoneNumber,
	AES_DECRYPT(CPAClientInvites.encryptedPhoneNumberExtension, UNHEX(SHA2(:userEncryptionkey,512))) AS phoneNumberExtension,
	CPAClientInvites.FK_PhoneNumberTypeID,
	CPAClientInvites.FK_FilingStatus,
	AES_DECRYPT(CPAClientInvites.encryptedTaxableIncome, UNHEX(SHA2(:userEncryptionkey,512))) AS taxableIncomeForTaxYear,
	AES_DECRYPT(CPAClientInvites.encryptedDirectoryName, UNHEX(SHA2(:userEncryptionkey,512))) AS directoryName,	
	AES_DECRYPT(CPAFirm.encryptedFirmLogoURL, UNHEX(SHA2(:userEncryptionkey,512))) AS firmLogoURL,
	AES_DECRYPT(CPAFirm.encryptedFirmName, UNHEX(SHA2(:userEncryptionkey,512))) AS firmName,	
	CPAFirm.customColor1HexValue,
	CPAFirm.customColor2HexValue
FROM
	CPAClientInvites
	INNER JOIN CPAFirm ON CPAClientInvites.FK_CPAFirmID = CPAFirm.cpaFirmID
WHERE
	CPAClientInvites.generatedCPAClientInviteHash = AES_ENCRYPT(:validationHash, UNHEX(SHA2(:userEncryptionkey,512))) AND
	CPAClientInvites.cpaClientInviteRecordID = :cpaClientInviteRecordID");
	
			$updateValidationRequestStatus																= $dbh -> prepare("UPDATE 
	CPAClientInvites
SET
	FK_ActivationStatus = :validationStatus,
	creationDate = :verificationDate
WHERE
	cpaClientInviteRecordID = :cpaClientInviteRecordID");
	
			$updateStatusForOtherInvitationsToThisEmailAddressFromThisFirm								= $dbh -> prepare("UPDATE
	CPAClientInvites
SET	
	FK_ActivationStatus = 6
WHERE
	FK_CPAFirmID = :cpaFirmID AND
	encryptedEmailAddress = AES_ENCRYPT(:inviteeEmailAddress, UNHEX(SHA2(:userEncryptionKey,512))) AND
	FK_ActivationStatus = 1");
	
			$createCPAFirmToCPAClientRelationship														= $dbh -> prepare("INSERT INTO CPAFirmToCPAClientRelationship
(
	FK_CPAFirmID,
	FK_CPAClientID,
	encryptedClientNumber,
	startDate,
	FK_PrimaryCPAAccountID
)
VALUES
(
	:cpaFirmID,
	:cpaClientID,
	AES_ENCRYPT(:clientNumber, UNHEX(SHA2(:userEncryptionkey,512))),
	:startDate,
	:primaryCPAAccountID
)");

			$createCPAToClientRoles																		= $dbh -> prepare("INSERT INTO CPAToClientRoles
(
	FK_CPAClientUserAccountID,
	FK_CPAUserAccountID,
	FK_CPAUserRoleID,
	FK_CPAFirmID,
	startDate,
	isActive
)
VALUES
(
	:cpaClientUserAccountID,
	:cpaUserAccountID,
	:cpaUserRoleID,
	:cpaFirmID,
	:startDate,
	:isActive
)");

			$getSecondaryCPAListForInvite																= $dbh -> prepare("SELECT
	FK_SecondaryCPAUserAccountID
FROM
	CPAClientInviteToSecondaryCPA
WHERE
	FK_CPAFirmID = :cpaFirmID AND
	FK_CPAClientInviteID = :cpaClientInviteID");

			$createCPAClientSecondaryCPAs																= $dbh -> prepare("INSERT INTO CPAClientSecondaryCPAs
(
	FK_CPAFirmID,
	FK_CPAClientUserAccountID,
	FK_SecondaryCPAAccountID,
	startDate,
	isActive
)
VALUES
(
	:cpaFirmID,
	:cpaClientUserAccountID,
	:secondaryCPAAccountID,
	:startDate,
	:isActive
)");

			$createCPAFirmToCPAClientSummaryForTaxYear													= $dbh -> prepare("INSERT INTO CPAFirmToCPAClientSummaryForTaxYear
(
	FK_CPAFirmID,
	FK_CPAClientID,
	encryptedClientNumber,
	taxYear,
	startDate,
	FK_PrimaryCPAAccountID,
	FK_ClientStatusID,
	FK_FilingStatusID,
	taxableIncomeForYear,
	numberOfSecondaryCPAs,
	isActive
)
VALUES
(
	:cpaFirmID,
	:cpaClientID,
	AES_ENCRYPT(:clientNumber, UNHEX(SHA2(:userEncryptionkey,512))),
	:taxYear,
	:startDate,
	:primaryCPAAccountID,
	:clientStatusID,
	:filingStatusID,
	:taxableIncomeForYear,
	:numberOfSecondaryCPAs,
	:isActive
)");
	
			$getValidationRequestData -> bindValue(':validationHash', $validationHash);
			$getValidationRequestData -> bindValue(':cpaClientInviteRecordID', $cpaClientInviteRecordID);
			$getValidationRequestData -> bindValue(':userEncryptionkey', $userEncryptionKey);
	
			if ($getValidationRequestData -> execute() && $getValidationRequestData -> rowCount() > 0)
			{
				$responseObject['validationHashMatchesInviteRecordID']		= true;
				
				$row 														= $getValidationRequestData -> fetchObject();
				
				$newAccountID												= 0; // this will be the account ID created in this function.
				
				$creationDate												= $row -> creationDate;
				$emailAddress												= $row -> emailAddress;
				$activationStatus											= $row -> FK_ActivationStatus;
				$primaryCPAUserAccountID									= $row -> FK_PrimaryCPAUserAccountID;
				$cpaFirmID													= $row -> FK_CPAFirmID;
				$directoryName												= $row -> directoryName;
				$taxYear													= $row -> taxYear;
				
				$firstName													= $row -> firstName;
				$middleName													= $row -> middleName;
				$lastName													= $row -> lastName;
	
				$numberSecondaryCPAs										= 0;
							
				$clientNumber												= $row -> clientNumber;
				$governmentIssuedIDValue									= $row -> governmentIssuedIDValue;
				
				$address1													= $row -> address1;
				$address2													= $row -> address2;
				$cityID														= $row -> FK_CityID;
				$stateID													= $row -> FK_StateID;
				$countryID													= $row -> FK_CountryID;
				$postalCode													= $row -> postalCode;

				$phoneNumber												= $row -> phoneNumber;
				$phoneNumberExtension										= $row -> phoneNumberExtension;
				$phoneNumberTypeID											= $row -> FK_PhoneNumberTypeID;
				
				$filingStatus												= $row -> FK_FilingStatus;
				$taxableIncomeForTaxYear									= $row -> taxableIncomeForTaxYear;
				$firmLogoURL												= $row -> firmLogoURL;
				$firmName													= $row -> firmName;
				$customColor1HexValue										= $row -> customColor1HexValue;
				$customColor2HexValue										= $row -> customColor2HexValue;
				
				if ($activationStatus == 3 || $activationStatus == 1)
				{
					$isMiner												= 0;
					$accountRoleID											= 1; // FK_AccountRole
					$planTypeID												= 5; // FK_PlanTypeID
					$isActive												= 1;
					// create account
					
					$passwordStrengthValueID								= getEnumValuePasswordStrength($passwordStrengthLabel, $dbh);

		// create account
		// update invite status
		// set admin firm ID
		
		
					errorLog("INSERT INTO UserAccounts
(
	encryptedFirstName,
	encryptedMiddleName,
	encryptedLastName,
	encryptedStreetAddress,
	encryptedStreetAddress2,
	encryptedCity,
	encryptedStateID,
	FK_CountryCode,
	encryptedZip,
	encryptedPhoneNumber,
	encryptedPrimaryPhoneNumberExtension,
	FK_PrimaryPhoneNumberTypeID,
	encryptedEmailAddress,
	encryptedPassword,
	isMiner,
	encryptedGovernmentID,
	creationDate,
	modificationDate,
	sid,
	FK_PlanTypeID,
	FK_PasswordStrengthID,
	isActive,
	encryptedBucketName,
	FK_AccountRole,
	encryptedPhotoURL,
	FK_CPAFirmID,
	invitationDate
)
VALUES
(
	AES_ENCRYPT('$firstName', UNHEX(SHA2('$userEncryptionKey',512))),
	AES_ENCRYPT('$middleName', UNHEX(SHA2('$userEncryptionKey',512))),
	AES_ENCRYPT('$lastName', UNHEX(SHA2('$userEncryptionKey',512))),
	AES_ENCRYPT('$address1', UNHEX(SHA2('$userEncryptionKey',512))),
	AES_ENCRYPT('$address2', UNHEX(SHA2('$userEncryptionKey',512))),
	AES_ENCRYPT($cityID, UNHEX(SHA2('$userEncryptionKey',512))),
	AES_ENCRYPT($stateID, UNHEX(SHA2('$userEncryptionKey',512))),
	$countryID,
	AES_ENCRYPT('$postalCode', UNHEX(SHA2('$userEncryptionKey',512))),
	AES_ENCRYPT(returnNumericOnly('$phoneNumber'), UNHEX(SHA2('$userEncryptionKey',512))),
	AES_ENCRYPT('$phoneNumberExtension', UNHEX(SHA2('$userEncryptionKey',512))),
	$phoneNumberTypeID,
	AES_ENCRYPT('$emailAddress', UNHEX(SHA2('$userEncryptionKey',512))),
	AES_ENCRYPT('$password', UNHEX(SHA2('$userEncryptionKey',512))),
	$isMiner,
	AES_ENCRYPT('$governmentIssuedIDValue', UNHEX(SHA2('$userEncryptionKey',512))),
	'$creationDate',
	NOW(),
	'$sid',
	$planTypeID,
	$passwordStrengthValueID,
	$isActive,
	AES_ENCRYPT('$directoryName', UNHEX(SHA2('$userEncryptionKey',512))),
	$accountRoleID,
	AES_ENCRYPT('', UNHEX(SHA2('$userEncryptionKey',512))),
	$cpaFirmID,
	'$creationDate'
)");
		
					$createAccount											= $dbh->prepare("INSERT INTO UserAccounts
(
	encryptedFirstName,
	encryptedMiddleName,
	encryptedLastName,
	encryptedStreetAddress,
	encryptedStreetAddress2,
	encryptedCity,
	encryptedStateID,
	FK_CountryCode,
	encryptedZip,
	encryptedPhoneNumber,
	encryptedPrimaryPhoneNumberExtension,
	FK_PrimaryPhoneNumberTypeID,
	encryptedEmailAddress,
	encryptedPassword,
	isMiner,
	encryptedGovernmentID,
	creationDate,
	modificationDate,
	sid,
	FK_PlanTypeID,
	FK_PasswordStrengthID,
	isActive,
	encryptedBucketName,
	FK_AccountRole,
	encryptedPhotoURL,
	FK_CPAFirmID,
	invitationDate
)
VALUES
(
	AES_ENCRYPT(:firstName, UNHEX(SHA2(:userEncryptionKey,512))),
	AES_ENCRYPT(:middleName, UNHEX(SHA2(:userEncryptionKey,512))),
	AES_ENCRYPT(:lastName, UNHEX(SHA2(:userEncryptionKey,512))),
	AES_ENCRYPT(:address, UNHEX(SHA2(:userEncryptionKey,512))),
	AES_ENCRYPT(:address2, UNHEX(SHA2(:userEncryptionKey,512))),
	AES_ENCRYPT(:cityID, UNHEX(SHA2(:userEncryptionKey,512))),
	AES_ENCRYPT(:stateID, UNHEX(SHA2(:userEncryptionKey,512))),
	:countryID,
	AES_ENCRYPT(:postalCode, UNHEX(SHA2(:userEncryptionKey,512))),
	AES_ENCRYPT(returnNumericOnly(:phoneNumber), UNHEX(SHA2(:userEncryptionKey,512))),
	AES_ENCRYPT(:phoneNumberExtension, UNHEX(SHA2(:userEncryptionKey,512))),
	:phoneNumberTypeID,
	AES_ENCRYPT(:emailAddress, UNHEX(SHA2(:userEncryptionKey,512))),
	AES_ENCRYPT(:password, UNHEX(SHA2(:userEncryptionKey,512))),
	:isMiner,
	AES_ENCRYPT(:governmentIssuedIDValue, UNHEX(SHA2(:userEncryptionKey,512))),
	:creationDate,
	NOW(),
	:sid,
	:planTypeID,
	:passwordStrengthID,
	:isActive,
	AES_ENCRYPT(:directoryName, UNHEX(SHA2(:userEncryptionKey,512))),
	:accountRoleID,
	AES_ENCRYPT(:photoURL, UNHEX(SHA2(:userEncryptionKey,512))),
	:cpaFirmID,
	:invitationDate
)");
					
					$createAccount -> bindValue(':firstName', $firstName);
					$createAccount -> bindValue(':middleName', $middleName);
					$createAccount -> bindValue(':lastName', $lastName);
					$createAccount -> bindValue(':address', $address1);
					$createAccount -> bindValue(':address2', $address2);
					$createAccount -> bindValue(':cityID', $cityID);
					$createAccount -> bindValue(':stateID', $stateID);
					$createAccount -> bindValue(':countryID', $countryID);
					$createAccount -> bindValue(':postalCode', $postalCode);
					$createAccount -> bindValue(':phoneNumber', $phoneNumber);
					$createAccount -> bindValue(':phoneNumberExtension', $phoneNumberExtension);
					$createAccount -> bindValue(':phoneNumberTypeID', $phoneNumberTypeID);
					$createAccount -> bindValue(':emailAddress', $emailAddress);
					$createAccount -> bindValue(':password', $password);
					$createAccount -> bindValue(':isMiner', $isMiner);
					$createAccount -> bindValue(':governmentIssuedIDValue', $governmentIssuedIDValue);
					$createAccount -> bindValue(':creationDate', $creationDate);
					$createAccount -> bindValue(':sid', $sid);
					$createAccount -> bindValue(':planTypeID', $planTypeID);
					$createAccount -> bindValue(':passwordStrengthID', $passwordStrengthValueID);
					$createAccount -> bindValue(':isActive', $isActive);
					$createAccount -> bindValue(':directoryName', $directoryName);
					$createAccount -> bindValue(':accountRoleID', $accountRoleID);
					$createAccount -> bindValue(':photoURL', "");
					$createAccount -> bindValue(':cpaFirmID', $cpaFirmID);
					$createAccount -> bindValue(':invitationDate', $creationDate);
					$createAccount -> bindValue(':userEncryptionKey', $userEncryptionKey);
							
					if ($createAccount -> execute())
					{
						$responseObject['createdCPAClientAccount']			= true;
						
						$newAccountID 										= $dbh -> lastInsertId();
						$returnValue										= $newAccountID;
						
						$responseObject['userAccountNumber']				= $newAccountID;
						
						errorLog("createCPANotification($newAccountID, $newAccountID, $primaryCPAUserAccountID, 2, $globalCurrentDate, $sid");
						
						$createNotificationResult							= createCPANotification($newAccountID, $newAccountID, $primaryCPAUserAccountID, 2, $globalCurrentDate, $sid, $dbh);
						
						$responseObject['createdNotification']				= $createNotificationResult['createdNotification'];
						
						errorLog("update activation status - set to created account");
						
						$updateValidationRequestStatus -> bindValue(':verificationDate', $globalCurrentDate);
						$updateValidationRequestStatus -> bindValue(':cpaClientInviteRecordID', $cpaClientInviteRecordID);
						$updateValidationRequestStatus -> bindValue(':validationStatus', 4);
	
						if ($updateValidationRequestStatus -> execute())
						{
							$responseObject['updatedValidationStatus']									= false;
							
							errorLog("updateValidationRequestStatus for $cpaClientInviteRecordID");
							
							$updateStatusForOtherInvitationsToThisEmailAddressFromThisFirm -> bindValue(':cpaFirmID', $cpaFirmID);
							$updateStatusForOtherInvitationsToThisEmailAddressFromThisFirm -> bindValue(':inviteeEmailAddress', $emailAddress);
							$updateStatusForOtherInvitationsToThisEmailAddressFromThisFirm -> bindValue(':userEncryptionKey', $userEncryptionKey);
							
							if ($updateStatusForOtherInvitationsToThisEmailAddressFromThisFirm -> execute())
							{
								$responseObject['disabledOtherInvitationsToInvitee']					= true;
								errorLog("disabled other invitations to this invitee email address from this firm");
							}
						}
						
						$cpaFirm											= new CPAFirm();
						
						$instantiationResult								= $cpaFirm -> instantiateCPAFirmUsingCPAFirmID($newAccountID, $cpaFirmID, $userEncryptionKey, $sid, $globalCurrentDate, $dbh);
						
						if ($instantiationResult['instantiatedRecord'] == true)
						{
							// create CPA Firm to client relationship
							$createCPAFirmToCPAClientRelationship -> bindValue(':cpaFirmID', $cpaFirmID);
							$createCPAFirmToCPAClientRelationship -> bindValue(':cpaClientID', $newAccountID);
							$createCPAFirmToCPAClientRelationship -> bindValue(':clientNumber', $clientNumber);
							$createCPAFirmToCPAClientRelationship -> bindValue(':startDate', $creationDate);
							$createCPAFirmToCPAClientRelationship -> bindValue(':primaryCPAAccountID', $primaryCPAUserAccountID);
							$createCPAFirmToCPAClientRelationship -> bindValue(':userEncryptionkey', $userEncryptionKey);
							
							if ($createCPAFirmToCPAClientRelationship -> execute())
							{
								$responseObject['createdCPAFirmToClientRelationshipRecord']				= true;	
							}

							// create cpa to client role record for the primary cpa
							
							$createCPAToClientRoles -> bindValue(':cpaClientUserAccountID', $newAccountID);
							$createCPAToClientRoles -> bindValue(':cpaUserAccountID', $primaryCPAUserAccountID);
							$createCPAToClientRoles -> bindValue(':cpaUserRoleID', 88);
							$createCPAToClientRoles -> bindValue(':cpaFirmID', $cpaFirmID);
							$createCPAToClientRoles -> bindValue(':startDate', $creationDate);
							$createCPAToClientRoles -> bindValue(':isActive', 1);
							
							if ($createCPAToClientRoles -> execute())
							{
								$responseObject['createdCPAToClientRoleRecordForPrimaryCPA']			= true;		
							}
							
							// get the list of secondary CPAs
							
							$getSecondaryCPAListForInvite -> bindValue(':cpaFirmID', $cpaFirmID);
							$getSecondaryCPAListForInvite -> bindValue(':cpaClientInviteID', $cpaClientInviteRecordID);
							
							if ($getSecondaryCPAListForInvite -> execute() && $getSecondaryCPAListForInvite -> rowCount() > 0)
							{
								
								// as you iterate through the list, increment count of secondary CPAs
							
								// for each
							
								while ($row = $getSecondaryCPAListForInvite -> fetchObject())
								{
									$numberSecondaryCPAs++;
									$secondaryCPAUserAccountID											= $row -> FK_SecondaryCPAUserAccountID;	
									
									// insert the secondary CPA record
									$createCPAClientSecondaryCPAs -> bindValue(':cpaFirmID', $cpaFirmID);
									$createCPAClientSecondaryCPAs -> bindValue(':cpaClientUserAccountID', $newAccountID);
									$createCPAClientSecondaryCPAs -> bindValue(':secondaryCPAAccountID', $secondaryCPAUserAccountID);
									$createCPAClientSecondaryCPAs -> bindValue(':startDate', $creationDate);
									$createCPAClientSecondaryCPAs -> bindValue(':isActive', 1);
									
									if ($createCPAClientSecondaryCPAs -> execute())
									{
										$responseObject['createdCPAClientSecondaryCPARecord']			= true;		
									}
									
									// create cpa to client role record for the secondary cpa
									$createCPAToClientRoles -> bindValue(':cpaClientUserAccountID', $newAccountID);
									$createCPAToClientRoles -> bindValue(':cpaUserAccountID', $secondaryCPAUserAccountID);
									$createCPAToClientRoles -> bindValue(':cpaUserRoleID', 88);
									$createCPAToClientRoles -> bindValue(':cpaFirmID', $cpaFirmID);
									$createCPAToClientRoles -> bindValue(':startDate', $creationDate);
									$createCPAToClientRoles -> bindValue(':isActive', 1);
									
									if ($createCPAToClientRoles -> execute())
									{
										$responseObject['createdCPAToClientRoleRecordForSecondaryCPA']	= true;		
									}
								}
							}
							
							$createCPAFirmToCPAClientSummaryForTaxYear -> bindValue(':cpaFirmID', $cpaFirmID);
							$createCPAFirmToCPAClientSummaryForTaxYear -> bindValue(':cpaClientID', $newAccountID);
							$createCPAFirmToCPAClientSummaryForTaxYear -> bindValue(':clientNumber', $clientNumber);
							$createCPAFirmToCPAClientSummaryForTaxYear -> bindValue(':taxYear', $taxYear);
							$createCPAFirmToCPAClientSummaryForTaxYear -> bindValue(':startDate', $creationDate);
							$createCPAFirmToCPAClientSummaryForTaxYear -> bindValue(':primaryCPAAccountID', $primaryCPAUserAccountID);
							$createCPAFirmToCPAClientSummaryForTaxYear -> bindValue(':clientStatusID', 4); // created account
							$createCPAFirmToCPAClientSummaryForTaxYear -> bindValue(':filingStatusID', $filingStatus);
							$createCPAFirmToCPAClientSummaryForTaxYear -> bindValue(':taxableIncomeForYear', $taxableIncomeForTaxYear);
							$createCPAFirmToCPAClientSummaryForTaxYear -> bindValue(':numberOfSecondaryCPAs', $numberSecondaryCPAs);
							$createCPAFirmToCPAClientSummaryForTaxYear -> bindValue(':isActive', 1);
							$createCPAFirmToCPAClientSummaryForTaxYear -> bindValue(':userEncryptionkey', $userEncryptionKey);
							
							if ($createCPAFirmToCPAClientSummaryForTaxYear -> execute())
							{
								$responseObject['createdCPAFirmToCPAClientSummaryForTaxYear']			= true;	
							}
							
							setUserFilingStatusForTaxYear($newAccountID, $newAccountID, $taxYear, $filingStatus, $taxableIncomeForTaxYear, $sid, $globalCurrentDate, $dbh);
						}

						$responseObject['responseMessage']					= "Your account has been created and activated.";
							
						$accountData										= array();
						$authResult											= array();
	
						$authResult['authenticated']						= "true";
						$authResult['result']								= "You have successfully logged in";
							
						$userObject											= new UserInformationObject();
						$userObject	 -> instantiateUserObjectByUserAccountID($newAccountID, $dbh, $sid);	
						
						$_SESSION['requireTFA']								= 0;
						$_SESSION['tfaAuthenticated']						= 0;
						$_SESSION['loggedInUserID']							= $newAccountID;
						$_SESSION['serverRoot']								= getServerRoot();
						$_SESSION['isLoggedIn']								= true;
							
						$accountData['requiresTwoFactorAuthentication']		= $userObject -> getRequireTwoFactorAuthentication();
						$accountData['sessionID']							= $userObject -> getEncodedSid();
						$accountData['userAccountNumber']					= $userObject -> getUserAccountID();
						$accountData['name']								= $userObject -> getNameObject() -> getNameAsArray();
							
						$responseObject['AccountData']						= $accountData;		
						$responseObject['AuthResult']						= $authResult;
					}
					else
					{
						$responseObject['responseMessage']					= "An error occurred while creating this account.";
					}
				}			
			}
			else
			{
				$responseObject['responseMessage']							= "Unable to verify your invitation value and cpaInvite record ID";		
			}
			
			$dbh 															= null;	
		}
	    catch (PDOException $e) 
	    {
	    	$responseObject['responseMessage']								= "Error: A database error has occurred.  Your account could not be verified. ".$e -> getMessage();	
			errorLog($e -> getMessage());
	
			die();
		}
		
		return $responseObject;	
	}
	
	function createCPANotification($authorID, $cpaClientUserAccountID, $cpaUserAccountID, $cpaNotificationMessageID, $globalCurrentDate, $sid, $dbh)
	{
		$responseObject																					= array();
		$responseObject['createdNotification']															= false;
		
		errorLog("INSERT INTO CPANotifications
(
	FK_CPAClientUserAccountID,
	FK_CPAUserAccountID,
	FK_CPANotificationMessageID,
	FK_CPANotificationStatusID,
	createdDate
)
VALUES
(
	$cpaClientUserAccountID,
	$cpaUserAccountID,
	$cpaNotificationMessageID,
	1,
	'$globalCurrentDate'
)");
		
		try
		{	
			$createCPANotification																		= $dbh -> prepare("INSERT INTO CPANotifications
(
	FK_CPAClientUserAccountID,
	FK_CPAUserAccountID,
	FK_CPANotificationMessageID,
	FK_CPANotificationStatusID,
	createdDate
)
VALUES
(
	:cpaClientUserAccountID,
	:cpaUserAccountID,
	:cpaNotificationMessageID,
	:cpaNotificationStatusID,
	:createdDate
)");
	
			$createCPANotification -> bindValue(':cpaClientUserAccountID', $cpaClientUserAccountID);
			$createCPANotification -> bindValue(':cpaUserAccountID', $cpaUserAccountID);
			$createCPANotification -> bindValue(':cpaNotificationMessageID', $cpaNotificationMessageID);
			$createCPANotification -> bindValue(':cpaNotificationStatusID', 1);
			$createCPANotification -> bindValue(':createdDate', $globalCurrentDate);
	
			if ($createCPANotification -> execute())
			{
				$responseObject['createdNotification']													= true;
			}
		}
	    catch (PDOException $e) 
	    {
	    	errorLog($e -> getMessage());
	
			die();
		}
		
		return $responseObject;
	}
	
	function verifyCPAClientAccountCreationValidationRequest($validationHash, $globalCurrentDate, $dbh)
	{
		$userEncryptionKey													= "b9Gf98252c8!0aea1f(31c4c753d351";
		
		// @task - create function that generates and stores encryption key for user based on validation hash - use that as encryption key for account
		
		$responseObject														= array();
		$responseObject['verifiedValidationHash']							= false;
		
		// get logo, colors, firm name from CPA Firm and return those as well so that Benson can style the edit page
		
		errorLog("SELECT
	CPAClientInvites.cpaClientInviteRecordID,
	CPAClientInvites.creationDate,
	AES_DECRYPT(CPAClientInvites.encryptedEmailAddress, UNHEX(SHA2('$userEncryptionKey',512))) AS emailAddress,
	CPAClientInvites.FK_ActivationStatus
FROM
	CPAClientInvites
WHERE
	CPAClientInvites.generatedCPAClientInviteHash = AES_ENCRYPT('$validationHash', UNHEX(SHA2('$userEncryptionKey',512)))");
		
		try
		{	
			$getValidationRequestData										= $dbh -> prepare("SELECT
	CPAClientInvites.cpaClientInviteRecordID,
	CPAClientInvites.creationDate,
	AES_DECRYPT(CPAClientInvites.encryptedEmailAddress, UNHEX(SHA2(:userEncryptionkey,512))) AS emailAddress,
	CPAClientInvites.FK_ActivationStatus
FROM
	CPAClientInvites
WHERE
	CPAClientInvites.generatedCPAClientInviteHash = AES_ENCRYPT(:validationHash, UNHEX(SHA2(:userEncryptionkey,512)))");
	
			$updateValidationRequestStatus									= $dbh -> prepare("UPDATE 
	CPAClientInvites
SET
	FK_ActivationStatus = :validationStatus,
	validationDate = :verificationDate
WHERE
	cpaClientInviteRecordID = :cpaClientInviteRecordID");
	
			$getValidationRequestData -> bindValue(':validationHash', $validationHash);
			$getValidationRequestData -> bindValue(':userEncryptionkey', $userEncryptionKey);
	
			if ($getValidationRequestData -> execute() && $getValidationRequestData -> rowCount() > 0)
			{
				$row 														= $getValidationRequestData -> fetchObject();
				
				$cpaClientInviteRecordID									= $row -> cpaClientInviteRecordID;
				$creationDate												= $row -> creationDate;
				$emailAddress												= $row -> emailAddress;
				$activationStatus											= $row -> FK_ActivationStatus;
				
				if ($activationStatus == 1)
				{
					$updateValidationRequestStatus -> bindValue(':verificationDate', $globalCurrentDate);
					$updateValidationRequestStatus -> bindValue(':cpaClientInviteRecordID', $cpaClientInviteRecordID);
					$updateValidationRequestStatus -> bindValue(':validationStatus', 1);
					
					$responseObject['emailAddress']							= $emailAddress;
					
					$responseObject['verifiedValidationHash']				= true;
					
					if ($updateValidationRequestStatus -> execute())
					{
						// populate response object
						$responseObject['cpaClientInviteRecordID']			= $cpaClientInviteRecordID; // read only
						$responseObject['emailAddress']						= $emailAddress; // read only
					}
					else
					{
						$responseObject['resultMessage']					= "We were unable to verify your account.";		
					}
				}
				else if ($requestStatus == 1)
				{
					$responseObject['resultMessage']						= "Your account has already been verified.";
				}
			}
			
			$dbh 															= null;	
		}
	    catch (PDOException $e) 
	    {
	    	$responseObject['responseMessage']								= "Error: A database error has occurred.  Your account could not be verified. ".$e -> getMessage();	
			$responseObject['createAccountVerified']						= false;
			errorLog($e -> getMessage());
	
			die();
		}
		
		return $responseObject;	
	}
	// END CPA Portal related functions
	
	// Tax form generation functions
	
	function updateCurrentUploadedTaxDocumentsForUserTable($authorID, $accountID, $taxYear, $taxDocumentTypeID, $directoryName, $fileName, $url, $userEncryptionKey, $globalCurrentDate, $sid, $dbh)
	{
		errorLog("updateCurrentUploadedTaxDocumentsForUserTable($authorID, $accountID, $taxYear, $taxDocumentTypeID, $directoryName, $fileName, $url, $userEncryptionKey, $globalCurrentDate, $sid");
		
		$responseObject																					= array();
		$responseObject['wroteCurrentUploadedTaxDocumentRecord']										= false;
		
		errorLog("REPLACE CurrentUploadedTaxDocumentsForUser
(
	FK_UserAccountID,
	FK_TaxDocumentTypeID,
	taxYear,
	encryptedFileName,
	encryptedDirectoryName,
	encryptedDocumentURL,
	creationDate
)
VALUES
(
	$accountID,
	$taxDocumentTypeID,
	$taxYear,
	AES_ENCRYPT('$fileName', UNHEX(SHA2('$userEncryptionKey',512)))
	AES_ENCRYPT('$directoryName', UNHEX(SHA2('$userEncryptionKey',512)))
	AES_ENCRYPT('$url', UNHEX(SHA2('$userEncryptionKey',512))),
	'$globalCurrentDate'
)");
		
		try
		{	
			$updateCurrentUploadedTaxDocumentsForUserTable												= $dbh -> prepare("REPLACE CurrentUploadedTaxDocumentsForUser
(
	FK_UserAccountID,
	FK_TaxDocumentTypeID,
	taxYear,
	encryptedFileName,
	encryptedDirectoryName,
	encryptedDocumentURL,
	creationDate
)
VALUES
(
	:userAccountID,
	:taxDocumentTypeID,
	:taxYear,
	AES_ENCRYPT(:fileName, UNHEX(SHA2(:userEncryptionkey,512))),
	AES_ENCRYPT(:directoryName, UNHEX(SHA2(:userEncryptionkey,512))),
	AES_ENCRYPT(:url, UNHEX(SHA2(:userEncryptionkey,512))),
	:creationDate
)");
	
			$updateCurrentUploadedTaxDocumentsForUserTable -> bindValue(':userAccountID', $accountID);
			$updateCurrentUploadedTaxDocumentsForUserTable -> bindValue(':taxDocumentTypeID', $taxDocumentTypeID);
			$updateCurrentUploadedTaxDocumentsForUserTable -> bindValue(':taxYear', $taxYear);
			$updateCurrentUploadedTaxDocumentsForUserTable -> bindValue(':fileName', $fileName);
			$updateCurrentUploadedTaxDocumentsForUserTable -> bindValue(':directoryName', $directoryName);
			$updateCurrentUploadedTaxDocumentsForUserTable -> bindValue(':url', $url);
			$updateCurrentUploadedTaxDocumentsForUserTable -> bindValue(':creationDate', $globalCurrentDate);
			$updateCurrentUploadedTaxDocumentsForUserTable -> bindValue(':userEncryptionkey', $userEncryptionKey);
	
			if ($updateCurrentUploadedTaxDocumentsForUserTable -> execute())
			{
				$responseObject['wroteCurrentUploadedTaxDocumentRecord']								= true;
			}
			
			$dbh 																						= null;	
		}
	    catch (PDOException $e) 
	    {
	    	errorLog($e -> getMessage());
	
			die();
		}
		
		return $responseObject;		
	}
	
	// END Tax form generation functions
	
	// custom classes
	require_once("../../../dist/assets/data/class_AccessTokenReturnData.php");
	
	require_once("../../../dist/assets/data/class_AccountingMethodForRegionProfile.php");
	
	require_once("../../../dist/assets/data/class_AddressObject.php");
	
	require_once("../../../dist/assets/data/class_APIRest.php");
	
	require_once("../../../dist/assets/data/class_AssetExchangeInfo.php");
	
	require_once("../../../dist/assets/data/class_AssetInfo.php");
	
	require_once("../../../dist/assets/data/class_BinanceTradeInfo.php");	
	
	require_once("../../../dist/assets/data/class_BittrexDataPull.php");
	
	// require_once("class_BittrexDataPullV3.php");
	
	require_once("../../../dist/assets/data/class_BittrexLedgerImportGroup.php");
	
	require_once("../../../dist/assets/data/class_BittrexLedgerTransaction.php");
	
	require_once("../../../dist/assets/data/class_BittrexOrderTransaction.php");
	
	require_once("../../../dist/assets/data/class_BTCAddressValidator.php");
	
	require_once("../../../dist/assets/data/class_CalculatedTransactionExchangeValuesAndFees.php");
	
	require_once("../../../dist/assets/data/class_CoinbaseCurrency.php"); // coinbase object mapping
	
	require_once("../../../dist/assets/data/class_CoinbaseMoney.php"); // coinbase object mapping
	
	require_once("../../../dist/assets/data/class_CoinbaseExchange.php"); // coinbase pro exchange HMAC class
	
	require_once("../../../dist/assets/data/class_CoinbaseRate.php"); // coinbase object mapping
	
	require_once("../../../dist/assets/data/class_CoinbaseTime.php"); // coinbase object mapping
	
	require_once("../../../dist/assets/data/class_CoinbaseTransaction.php"); // coinbase object mapping
	
	require_once("../../../dist/assets/data/class_CoinbaseUserAccount.php");
	
	require_once("../../../dist/assets/data/class_CommonCurrencyPair.php"); // common currency pair - derived from Kraken currency pair
	
	require_once("../../../dist/assets/data/class_CommonTransactionValueCalculations.php");
	
	require_once("../../../dist/assets/data/class_CompleteCryptoWallet.php");
	
	require_once("../../../dist/assets/data/class_CorrelationEngineWorker.php");
	
	require_once("../../../dist/assets/data/class_CorrelationRecord.php");
	
	require_once("../../../dist/assets/data/class_CPAFirm.php");
	
	require_once("../../../dist/assets/data/class_CryptoExchangeValue.php");
	
	require_once("../../../dist/assets/data/class_CryptoFeeObject.php");
	
	require_once("../../../dist/assets/data/class_CryptoIDAddressReportRecord.php");
	
	require_once("../../../dist/assets/data/class_CryptoIDQueryHandler.php");
	
	require_once("../../../dist/assets/data/class_CryptoIDTransactionRecords.php");
	
	require_once("../../../dist/assets/data/class_CryptoGroupedSpendTransaction.php");
	
	require_once("../../../dist/assets/data/class_CryptoPrice.php");
	
	require_once("../../../dist/assets/data/class_CryptoTransaction.php");
	
	require_once("../../../dist/assets/data/class_CryptoTransactionBalance.php");
	
	require_once("../../../dist/assets/data/class_CryptoWallet.php");
	
	require_once("../../../dist/assets/data/class_EmailFactory.php");
	
	require_once("../../../dist/assets/data/class_ExchangeTile.php");
	
	require_once("../../../dist/assets/data/class_Form1040LineText.php");
	
	require_once("../../../dist/assets/data/class_GlobalTransactionIdentificationValue.php");
	
	require_once("../../../dist/assets/data/class_IntegrationPartnerAccountObject.php");
	
	require_once("../../../dist/assets/data/class_IntegrationPartnerSessionObject.php");
	
	require_once("../../../dist/assets/data/class_IntegrationPartnerWhiteListIPObject.php");
	
	require_once("../../../dist/assets/data/class_IPClientAccountCryptoCurrencyPriceObject.php");
	
	require_once("../../../dist/assets/data/class_IPClientAccountTransaction.php");
    
    require_once("../../../dist/assets/data/class_IPTransactionAddressObject.php");
    
	require_once("../../../dist/assets/data/class_IPTransactionAmountObject.php");

	require_once("../../../dist/assets/data/class_KrakenCurrencyPair.php");

	require_once("../../../dist/assets/data/class_KrakenLedgerTransaction.php");

	require_once("../../../dist/assets/data/class_KrakenTradeTransaction.php");
	
	require_once("../../../dist/assets/data/class_LineText.php");
	
	require_once("../../../dist/assets/data/class_NameObject.php");
	
	require_once("../../../dist/assets/data/class_NativeTransactionType.php");
	
	require_once("../../../dist/assets/data/class_ProfitStanceLedgerEntry.php");
	
	require_once("../../../dist/assets/data/class_ProviderAccountWallet.php");
	
	require_once("../../../dist/assets/data/class_SummaryLineText.php");
	
	require_once("../../../dist/assets/data/class_TaxFormInstance.php"); // 8949 or 1040 tax form

	require_once("../../../dist/assets/data/class_TaxFormInstanceDetailRecord.php"); // 8949 or 1040 tax form
	
	require_once("../../../dist/assets/data/class_TaxFormWorksheetDetailRecord.php"); // 8949 or 1040 tax form - worksheet detail - also used in calculations for portfolio view
	
	require_once("../../../dist/assets/data/class_UniversalCSVImporter.php");
	
	require_once("../../../dist/assets/data/class_UniversalCSVTransaction.php");
	
	require_once("../../../dist/assets/data/class_UserInformationObject.php");
	
	require_once("../../../dist/assets/data/class_WalletType.php");
	
	require_once("../../../dist/assets/data/class_WorksheetLineText.php");
	
	require_once("../../../dist/assets/data/constants_UniversalCSVErrors.php");
	
	require_once("../../../dist/assets/data/createCommonTransactionsFunctions.php");
	
	require_once("../../../dist/assets/data/fifoAccountingMethodFunctions.php");
	
	require_once("../../../dist/assets/data/lifoAccountingMethodFunctions.php");

	require_once("../../../dist/assets/data/dbConnection.php");
	
	// utility functions
	require_once("../../../dist/assets/data/utilityEnumFunctions.php");
	
	require_once("../../../dist/assets/data/utilityFunctions.php");
?>