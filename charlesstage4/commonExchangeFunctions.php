<?php
	// Common
	function getNonce () 
	{
		return time() * 1000;
	}
	
	function generateHMAC ($str, $secret,$shaMode = 'sha512') 
	{
		$hashedValue                        									= hash_hmac($shaMode, $str, $secret);
		
		return $hashedValue;
	}
	
	function performCurl ($options) 
	{
	
		$curlObj                            									= curl_init();
		
		curl_setopt_array($curlObj, $options);
		
		$curlResponse                       									= curl_exec($curlObj);
		
		if (curl_errno($curlObj)) 
		{
			$curlResponse                 										= curl_error($curlObj);
		}
		
		curl_close($curlObj);
		
		return  $curlResponse;
	
	}
	
	// End Common
	
	// Binance
	
	function getBinanceTransactionHistoryForUserViaAPI($tradesList, $exchangeTileID, $startPosition, $baseCurrency, $baseCurrencyAssetTypeID, $baseCurrencyAssetType, $quoteCurrency, $quoteCurrencyAssetTypeID, $quoteCurrencyAssetType, $baseCurrencyWalletID, $quoteCurrencyWalletID, $cryptoCurrencyTypesImported, $name, $accountID, $userEncryptionKey, $transactionSourceID, $transactionSourceName, $walletTypeID, $walletTypeName, $includeDetailReporting, $dataImportEventRecordID, $globalCurrentDate, $sid, $dbh)
	{
    	$returnValue   															= array();
  	
		$tradesArray															= $tradesList;
    	$numberProcessed														= 0;
    				
	    try
	    {
		    errorLog("count ".count($tradesArray));
			$lastTransactionID													= 0;    
		    
		    foreach ($tradesArray as $trade)
		    {
		    
				$transactionPairName											= $trade -> symbol;
				$txIDValue														= $trade -> id;
				// save lastTransactionID for loading next batch of 1000 transactions
				$lastTransactionID												= $txIDValue;
				$orderTxID														= $trade -> orderId;
				$transactionPrice												= $trade -> price;
			    $transactionQty													= $trade -> qty;
			    $transactionQuoteQty											= $trade -> quoteQty;
			    $transactionCommission											= $trade -> commission;
			    $transactionCommissionAsset										= $trade -> commissionAsset;
			    $transactionIsBuyer 											= $trade -> isBuyer;
			    $transactionIsMaker 											= $trade -> isMaker;
			    $transactionIsBestMatch 										= $trade -> isBestMatch;
			    
			    $transactionType												= "sell";
			    $isDebit														= 1;
			    $transactionTimestamp											= $trade -> time;	// transaction timestamp						 
				$transactionTime										    	= date("Y-m-d h:i:s", ($transactionTimestamp/1000));
				$creationDate													= $globalCurrentDate; 
				$originalTransactionTypeID										= 1;
				
				if($transactionIsMaker == 1 || ($transactionIsMaker == null && $transactionIsBuyer == null)) 
				{
					$originalTransactionTypeID									= 4;
					$transactionType											= "sell";
				}
				elseif($transactionIsBuyer == 1)
				{
					$originalTransactionTypeID									= 1;
					$transactionType											= "buy";										
				}
				
				$feeAssetTypeID											 		= getEnumValueAssetType($transactionCommissionAsset, $dbh);
				
				$baseToQuoteCurrencySpotPrice									= 0;	// quoteCurrencySpotPriceAtTimeOfTransaction					
				$baseToUSDCurrencySpotPrice										= 0;	// spotPriceAtTimeOfTransaction
				$btcSpotPriceAtTimeOfTransaction								= 0;	// btcPriceAtTimeOfTransaction
				$feeCurrencySpotPriceAtTimeOfTransaction						= 0;
				
				$cascadeRetrieveSpotPriceResponseObject							= getSpotPriceForAssetPairUsingSourceCascade($baseCurrencyAssetTypeID, 2, $transactionTime, 14, "CoinGecko price by date", $dbh);
					
				if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
				{
					$baseToUSDCurrencySpotPrice									= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
				}

				$cascadeRetrieveSpotPriceResponseObject							= getSpotPriceForAssetPairUsingSourceCascade($quoteCurrencyAssetTypeID, 2, $transactionTime, 14, "CoinGecko price by date", $dbh);
				if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
				{
					$baseToQuoteCurrencySpotPrice								= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
				}				
				
				if ($baseCurrencyAssetTypeID == 1)
				{
					$btcSpotPriceAtTimeOfTransaction							= $baseToUSDCurrencySpotPrice;	
				}
				else
				{
					$cascadeRetrieveSpotPriceResponseObject						= getSpotPriceForAssetPairUsingSourceCascade(1, 2, $transactionTime, 14, "CoinGecko price by date", $dbh);
					
					if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
					{
						$btcSpotPriceAtTimeOfTransaction						= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
					}	
				}
				
				// get spot price for fee currency in USD
				
				$cascadeRetrieveSpotPriceResponseObject							= getSpotPriceForAssetPairUsingSourceCascade($feeAssetTypeID, 2, $transactionTime, 14, "CoinGecko price by date", $dbh);
					
				if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
				{
					$feeCurrencySpotPriceAtTimeOfTransaction					= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
				}
				
				$feeAmountInUSD											 		= $feeCurrencySpotPriceAtTimeOfTransaction * $transactionCommission; // fee amount in USD
				
				$transactionAmountInUSD											= $baseToUSDCurrencySpotPrice * $transactionQty;	// usdAmount
				$transactionAmountMinusFeeInUSD									= $transactionAmountInUSD - $feeAmountInUSD;
				$sentQuantity													= $transactionAmountInUSD;
				$amount															= $transactionAmountInUSD;
				
				error_log("getBinanceTransactionHistoryForUserViaAPI $transactionTime $transactionPairName $transactionPrice $transactionQty $transactionQuoteQty $transactionCommission $transactionCommissionAsset $transactionIsBuyer $transactionIsMaker $transactionIsBestMatch");
				
			    // @Task - write code to convert currencies between USD and BTC and the current type - use amount objects
				    
			    
			    
			    // Binance - every time I read a spot price, write it to the daily spot price table
			    setDailyPriceData($baseCurrencyAssetTypeID, $quoteCurrencyAssetTypeID, $transactionTimestamp, $transactionPrice, $transactionSourceID, $globalCurrentDate, $sid, $dbh);
			    
			    // @Task - it may be better to compact the array rather than have index gaps - write a function that checks to see if a value already exists in array, and if not, adds it
			    
			    if (isset($cryptoCurrencyTypesImported[$baseCurrencyAssetTypeID][$quoteCurrencyAssetTypeID]))
				{
					$currentCount												= $cryptoCurrencyTypesImported[$baseCurrencyAssetTypeID][$quoteCurrencyAssetTypeID];
					$currentCount++;
					
					$cryptoCurrencyTypesImported[$baseCurrencyAssetTypeID][$quoteCurrencyAssetTypeID]			= $currentCount;
				}
				else
				{
					$cryptoCurrencyTypesImported[$baseCurrencyAssetTypeID][$quoteCurrencyAssetTypeID]			= 1;	
				}
				
				$transactionAssetTypeID											= getEnumValueAssetType($transactionCommissionAsset, $dbh);
				$transactionTypeID												= $originalTransactionTypeID;
				
				// check for global transaction ID
				
				error_log("getGlobalTransactionIdentificationRecordID for $accountID, $baseCurrencyAssetTypeID, $transactionSourceID, $globalCurrentDate, $sid");
				
			    $globalTransactionIDTestResults									= getGlobalTransactionIdentificationRecordID($accountID, $baseCurrencyAssetTypeID, $txIDValue, $transactionSourceID, $globalCurrentDate, $sid, $dbh);
			    
			    // here
			    
			    if ($globalTransactionIDTestResults['foundNativeTransactionForAccount'] == false)
				{
					error_log("not found $txIDValue");
					
					$returnValue[$txIDValue]["existingRecordFound"]				= false;
					
					// create one if not found
					$globalTransactionCreationResults							= createGlobalTransactionIdentificationRecord($accountID, $exchangeTileID, $baseCurrencyAssetTypeID, $txIDValue, $transactionSourceID, $globalCurrentDate, $sid, $dbh);
			
					if ($globalTransactionCreationResults['createdGlobalTransactionIdentificationRecord'] == true)
					{
						errorLog("createGlobalTransactionIdentificationRecord success");
						
						$returnValue[$txIDValue]["createdGTIR"]					= true;
							
						$globalTransactionIdentifierRecordID					= $globalTransactionCreationResults['globalTransactionIdentificationRecordID'];
						$profitStanceTransactionIDValue							= $globalTransactionCreationResults['profitStanceTransactionIDValue'];
							
						// @Task - this is where I need to use the new provider account wallet idea of 
							
						$providerAccountWallet									= new ProviderAccountWallet();
							
						// check for provider account wallet							
						$instantiationResult									= $providerAccountWallet -> instantiateAccountWalletObjectForAccountByAssetTypeIDAndTransactionSourceID($accountID, $baseCurrencyAssetTypeID, $transactionSourceID, $dbh);
							
						if ($instantiationResult['instantiatedWallet'] == false)
						{
							// create wallet if not found
							$providerAccountWallet -> createAccountWalletObject($accountID, $baseCurrencyAssetTypeID, $baseCurrencyAssetType, $accountID, "$accountID-$transactionSourceID-$baseCurrencyAssetType", $walletTypeID, "Private Ledger Based Wallet", $transactionSourceID, $transactionSourceName, $globalCurrentDate, $sid, $dbh);
						}
							
						$providerWalletID										= $providerAccountWallet -> getAccountWalletID();
						
						errorLog("providerWalletID $providerWalletID");
							
						if ($providerWalletID > 0)
						{
							// Write order to database
							$binanceOrder										= new BinanceOrder();						
							$binanceOrder -> setData($accountID, $orderTxID);
			
							// write data to DB
							$response 											= $binanceOrder -> writeToDatabase($userEncryptionKey, $dbh);
			
							$fk_binanceOrderID									= 0;
			
							if ($response['wroteToDatabase'] == true)
							{
								$fk_binanceOrderID 								= $binanceOrder -> getBinanceOrderRecordID(); 
								error_log("\n Order: $fk_binanceOrderID inserted successfully into BinanceOrders table!\n");	
							}							
							
							// create binance trade transaction object and write it

							$binanceTradeTransaction 							= new BinanceTradeTransaction();
													
							$binanceTradeTransaction -> setData($fk_binanceOrderID, $accountID, $exchangeTileID, 0, $globalTransactionIdentifierRecordID, $transactionSourceID, $txIDValue, $orderTxID, $transactionPairName, $transactionTime, $transactionPrice, $transactionQty, $transactionQuoteQty, $transactionCommission, $transactionCommissionAsset, $transactionIsBuyer, $transactionIsMaker, $transactionIsBestMatch, $walletTypeID, $walletTypeName, $transactionTypeID, $providerWalletID, $baseCurrencyWalletID, $quoteCurrencyWalletID, $baseCurrencyAssetTypeID, $quoteCurrencyAssetTypeID, $transactionTimestamp, $creationDate, $baseToQuoteCurrencySpotPrice, $baseToUSDCurrencySpotPrice, $btcSpotPriceAtTimeOfTransaction, $transactionAmountInUSD, $feeAmountInUSD, $isDebit, $userEncryptionKey, $globalCurrentDate, $sid, $dbh);																						
							
							$writeBinanceRecordResponseObject					= $binanceTradeTransaction -> writeToDatabase($userEncryptionKey, $dbh);
							
							if ($writeBinanceRecordResponseObject['wroteToDatabase'] == true)
							{
								error_log("Transaction inserted into BinanceTradeTransactions table");
								// now that the transaction has been created, create the association record for the closing array if size > 0
								
								$profitStanceLedgerEntry						= new ProfitStanceLedgerEntry();
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
								$setNativeTransactionRecordIDResult				= setNativeTransactionRecordIDForGlobalTransactionIndentificationRecordID($accountID, $binanceTradeTransaction -> getBinanceTransactionRecordID(), $globalTransactionIdentifierRecordID, $globalCurrentDate, $sid, $dbh);

								// Writing ledger data
								errorLog("Writing ledger data");
								
								// --------------------Ledger variables
								$originalTransactionTypeID						= $transactionTypeID;
																							
								if($originalTransactionTypeID == 1)	// Buy transaction	 
								{
									// Credit Side
									errorlog("Writing Credit side of BUY transaction");
									$transactionTypeID							= 1;
									$isDebit								 	= 0;
									$fk_LedgerCurrencyAssetID					= $baseCurrencyAssetTypeID;
									$fk_OriginalQuoteCurrency					= $quoteCurrencyAssetTypeID;
									$binanceExchangeRate						= $transactionPrice;
									
									// spot prices
									$cascadeRetrieveSpotPriceResponseObject		= getSpotPriceForAssetPairUsingSourceCascade($baseCurrencyAssetTypeID, 2, $transactionTime, 14, "CoinGecko price by date", $dbh);
										
									if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
									{
										$baseToUSDCurrencySpotPrice				= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
									}
					
									$cascadeRetrieveSpotPriceResponseObject		= getSpotPriceForAssetPairUsingSourceCascade(2, 2, $transactionTime, 14, "CoinGecko price by date", $dbh);
									if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
									{
										$baseToQuoteCurrencySpotPrice			= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
									}				
									
									if ($baseCurrencyAssetTypeID == 1)
									{
										$btcSpotPriceAtTimeOfTransaction		= $baseToUSDCurrencySpotPrice;	
									}
									else
									{
										$cascadeRetrieveSpotPriceResponseObject	= getSpotPriceForAssetPairUsingSourceCascade(1, 2, $transactionTime, 14, "CoinGecko price by date", $dbh);
										
										if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
										{
											$btcSpotPriceAtTimeOfTransaction	= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
										}	
									}
									
									// get spot price for fee currency in USD
									
									$cascadeRetrieveSpotPriceResponseObject		= getSpotPriceForAssetPairUsingSourceCascade($feeAssetTypeID, 2, $transactionTime, 14, "CoinGecko price by date", $dbh);
										
									if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
									{
										$feeCurrencySpotPriceAtTimeOfTransaction = $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
									}
									
									// end spot prices
									
									$transactionAmountInUSD						= $baseToUSDCurrencySpotPrice * $transactionQty;	// usdAmount	
																								
									$costInQuoteCurrency						= $baseToUSDCurrencySpotPrice * $transactionQty;	
										
									$binanceTradeTransactionAsLedger 			= new BinanceTradeTransactionAsLedger();													
									
									$binanceTradeTransactionAsLedger -> setData($fk_binanceOrderID, $accountID, $exchangeTileID, 0, $globalTransactionIdentifierRecordID, $transactionSourceID, $txIDValue, $orderTxID, $transactionPairName, $transactionTime, $baseToUSDCurrencySpotPrice, $transactionQty, $costInQuoteCurrency, $transactionCommission, $transactionCommissionAsset, $transactionIsBuyer, $transactionIsMaker, $transactionIsBestMatch, $walletTypeID, $walletTypeName, 1, $originalTransactionTypeID, $providerWalletID, $baseCurrencyWalletID, $quoteCurrencyWalletID, $baseCurrencyAssetTypeID, 2, $transactionTimestamp, $creationDate, $baseToQuoteCurrencySpotPrice, $baseToUSDCurrencySpotPrice, $btcSpotPriceAtTimeOfTransaction, $costInQuoteCurrency, $feeAmountInUSD, $isDebit, $binanceExchangeRate, $fk_LedgerCurrencyAssetID, $fk_OriginalQuoteCurrency, $userEncryptionKey, $globalCurrentDate, $sid, $dbh);	
									
									$writeBinanceLedgerRecordResponseObject		= $binanceTradeTransactionAsLedger -> writeToDatabase($userEncryptionKey, $dbh);																	
									if ($writeBinanceLedgerRecordResponseObject['wroteToDatabase'] == true)
									{
										errorLog("Succeded: Credit side of BUY transaction insertion into BinanceTradeTransactionsAsLedger for TransactionID: $txIDValue");
									}										
									
									// Debit Side
									errorlog("Writing Debit side of BUY transaction");									
									$isDebit									= 1;
									$transactionTypeID							= 4;
									$fk_LedgerCurrencyAssetID					= $quoteCurrencyAssetTypeID;
									$fk_OriginalQuoteCurrency					= $baseCurrencyAssetTypeID;
									$transactionPrice							= $transactionPrice;
									$binanceExchangeRate						= 1/$transactionPrice;
									$priceInQuoteCurrency						= $transactionPrice;
									$globalTransactionIdentifierRecordID		= createGlobalTransactionRecord($accountID, $exchangeTileID, $quoteCurrencyAssetTypeID, $txIDValue, $transactionSourceID, $globalCurrentDate, $sid, $dbh);
									
									if($globalTransactionIdentifierRecordID != 0)
									{
									// spot prices
									$cascadeRetrieveSpotPriceResponseObject		= getSpotPriceForAssetPairUsingSourceCascade($quoteCurrencyAssetTypeID, 2, $transactionTime, 14, "CoinGecko price by date", $dbh);
										
									if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
									{
										$baseToUSDCurrencySpotPrice				= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
									}
					
									$cascadeRetrieveSpotPriceResponseObject		= getSpotPriceForAssetPairUsingSourceCascade(2, 2, $transactionTime, 14, "CoinGecko price by date", $dbh);
									if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
									{
										$baseToQuoteCurrencySpotPrice			= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
									}				
									
									if ($baseCurrencyAssetTypeID == 1)
									{
										$btcSpotPriceAtTimeOfTransaction		= $baseToUSDCurrencySpotPrice;	
									}
									else
									{
										$cascadeRetrieveSpotPriceResponseObject	= getSpotPriceForAssetPairUsingSourceCascade(1, 2, $transactionTime, 14, "CoinGecko price by date", $dbh);
										
										if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
										{
											$btcSpotPriceAtTimeOfTransaction	= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
										}	
									}
									
									// get spot price for fee currency in USD
									
									$cascadeRetrieveSpotPriceResponseObject		= getSpotPriceForAssetPairUsingSourceCascade($feeAssetTypeID, 2, $transactionTime, 14, "CoinGecko price by date", $dbh);
										
									if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
									{
										$feeCurrencySpotPriceAtTimeOfTransaction = $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
									}
									
									$transactionAmountInUSD						= $baseToUSDCurrencySpotPrice * $transactionQty;	// usdAmount									
									$newTransactionQty							= $transactionQty * $transactionPrice;	
									$costInQuoteCurrency						= $baseToUSDCurrencySpotPrice * $newTransactionQty;	
																	
									// end spot prices
										
										$binanceTradeTransactionAsLedger 		= new BinanceTradeTransactionAsLedger();							
										
										$binanceTradeTransactionAsLedger -> setData($fk_binanceOrderID, $accountID, $exchangeTileID, 0, $globalTransactionIdentifierRecordID, $transactionSourceID, $txIDValue, $orderTxID, $transactionPairName, $transactionTime, $baseToUSDCurrencySpotPrice, $newTransactionQty, $costInQuoteCurrency, $transactionCommission, $transactionCommissionAsset, $transactionIsBuyer, $transactionIsMaker, $transactionIsBestMatch, $walletTypeID, $walletTypeName, 4, $originalTransactionTypeID, $providerWalletID, $baseCurrencyWalletID, $quoteCurrencyWalletID, $baseCurrencyAssetTypeID, 2, $transactionTimestamp, $creationDate, $baseToQuoteCurrencySpotPrice, $baseToUSDCurrencySpotPrice, $btcSpotPriceAtTimeOfTransaction, $costInQuoteCurrency, $feeAmountInUSD, $isDebit, $binanceExchangeRate, $fk_LedgerCurrencyAssetID, $fk_OriginalQuoteCurrency, $userEncryptionKey, $globalCurrentDate, $sid, $dbh);	
										
										$writeBinanceLedgerRecordResponseObject	= $binanceTradeTransactionAsLedger -> writeToDatabase($userEncryptionKey, $dbh);																	
										if ($writeBinanceLedgerRecordResponseObject['wroteToDatabase'] == true)
										{
											errorLog("Succeeded: Debit side of BUY transaction insertion into BinanceTradeTransactionsAsLedger for TransactionID: $txIDValue");							}
									}									
									else
									{
										errorLog("Failed: Debit side of BUY transaction insertion into BinanceTradeTransactionsAsLedger for TransactionID: $txIDValue");										
									}	
									
								} 
								elseif($originalTransactionTypeID == 4) // sell transaction
								{
									errorlog("Writing Debit side of SELL transaction");									
									// Debit Side
									$transactionTypeID							= 4;
									$isDebit								 	= 1;
									$fk_LedgerCurrencyAssetID					= $baseCurrencyAssetTypeID;
									$fk_OriginalQuoteCurrency					= $quoteCurrencyAssetTypeID;
									$binanceExchangeRate						= $transactionPrice;
									// spot prices
									$cascadeRetrieveSpotPriceResponseObject		= getSpotPriceForAssetPairUsingSourceCascade($quoteCurrencyAssetTypeID, 2, $transactionTime, 14, "CoinGecko price by date", $dbh);
										
									if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
									{
										$baseToUSDCurrencySpotPrice				= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
									}
					
									$cascadeRetrieveSpotPriceResponseObject		= getSpotPriceForAssetPairUsingSourceCascade(2, 2, $transactionTime, 14, "CoinGecko price by date", $dbh);
									if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
									{
										$baseToQuoteCurrencySpotPrice			= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
									}				
									
									if ($baseCurrencyAssetTypeID == 1)
									{
										$btcSpotPriceAtTimeOfTransaction		= $baseToUSDCurrencySpotPrice;	
									}
									else
									{
										$cascadeRetrieveSpotPriceResponseObject	= getSpotPriceForAssetPairUsingSourceCascade(1, 2, $transactionTime, 14, "CoinGecko price by date", $dbh);
										
										if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
										{
											$btcSpotPriceAtTimeOfTransaction	= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
										}	
									}
									
									// get spot price for fee currency in USD
									
									$cascadeRetrieveSpotPriceResponseObject		= getSpotPriceForAssetPairUsingSourceCascade($feeAssetTypeID, 2, $transactionTime, 14, "CoinGecko price by date", $dbh);
										
									if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
									{
										$feeCurrencySpotPriceAtTimeOfTransaction = $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
									}
									
									$transactionAmountInUSD						= $baseToUSDCurrencySpotPrice * $transactionQty;	// usdAmount
									$newTransactionQty							= $transactionQty * $transactionPrice;																							$costInQuoteCurrency						= $baseToUSDCurrencySpotPrice * $newTransactionQty;
									// end spot prices									
									$binanceTradeTransactionAsLedger 			= new BinanceTradeTransactionAsLedger();								
									$binanceTradeTransactionAsLedger -> setData($fk_binanceOrderID, $accountID, $exchangeTileID, 0, $globalTransactionIdentifierRecordID, $transactionSourceID, $txIDValue, $orderTxID, $transactionPairName, $transactionTime, $baseToUSDCurrencySpotPrice, $newTransactionQty, $costInQuoteCurrency, $transactionCommission, $transactionCommissionAsset, $transactionIsBuyer, $transactionIsMaker, $transactionIsBestMatch, $walletTypeID, $walletTypeName, 1, $originalTransactionTypeID, $providerWalletID, $baseCurrencyWalletID, $quoteCurrencyWalletID, $baseCurrencyAssetTypeID, 2, $transactionTimestamp, $creationDate, $baseToQuoteCurrencySpotPrice, $baseToUSDCurrencySpotPrice, $btcSpotPriceAtTimeOfTransaction, $costInQuoteCurrency, $feeAmountInUSD, $isDebit, $binanceExchangeRate, $fk_LedgerCurrencyAssetID, $fk_OriginalQuoteCurrency, $userEncryptionKey, $globalCurrentDate, $sid, $dbh);	
									
									$writeBinanceLedgerRecordResponseObject		= $binanceTradeTransactionAsLedger -> writeToDatabase($userEncryptionKey, $dbh);																	
									if ($writeBinanceLedgerRecordResponseObject['wroteToDatabase'] == true)
									{
										errorLog("Succeded: Credit side of BUY transaction insertion into BinanceTradeTransactionsAsLedger for TransactionID: $txIDValue");
									}										
									
									// Credit Side
									errorlog("Writing Credit side of SELL transaction");													
									$transactionTypeID							= 1;
									$isDebit									= 0;
									$fk_LedgerCurrencyAssetID					= $quoteCurrencyAssetTypeID;
									$fk_OriginalQuoteCurrency					= $baseCurrencyAssetTypeID;
									$transactionPrice							= $transactionPrice;
									$binanceExchangeRate						= 1/$transactionPrice;
																		
									$globalTransactionIdentifierRecordID		= createGlobalTransactionRecord($accountID, $exchangeTileID, $quoteCurrencyAssetTypeID, $txIDValue, $transactionSourceID, $globalCurrentDate, $sid, $dbh);
									
									if($globalTransactionIdentifierRecordID != 0)
									{
									// spot prices
									$cascadeRetrieveSpotPriceResponseObject		= getSpotPriceForAssetPairUsingSourceCascade($baseCurrencyAssetTypeID, 2, $transactionTime, 14, "CoinGecko price by date", $dbh);
										
									if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
									{
										$baseToUSDCurrencySpotPrice				= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
									}
					
									$cascadeRetrieveSpotPriceResponseObject		= getSpotPriceForAssetPairUsingSourceCascade(2, 2, $transactionTime, 14, "CoinGecko price by date", $dbh);
									if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
									{
										$baseToQuoteCurrencySpotPrice			= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
									}				
									
									if ($baseCurrencyAssetTypeID == 1)
									{
										$btcSpotPriceAtTimeOfTransaction		= $baseToUSDCurrencySpotPrice;	
									}
									else
									{
										$cascadeRetrieveSpotPriceResponseObject	= getSpotPriceForAssetPairUsingSourceCascade(1, 2, $transactionTime, 14, "CoinGecko price by date", $dbh);
										
										if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
										{
											$btcSpotPriceAtTimeOfTransaction	= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
										}	
									}
									
									// get spot price for fee currency in USD
									
									$cascadeRetrieveSpotPriceResponseObject		= getSpotPriceForAssetPairUsingSourceCascade($feeAssetTypeID, 2, $transactionTime, 14, "CoinGecko price by date", $dbh);
										
									if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
									{
										$feeCurrencySpotPriceAtTimeOfTransaction = $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
									}
									$transactionAmountInUSD						= $baseToUSDCurrencySpotPrice * $transactionQty;	// usdAmount
																									
									$costInQuoteCurrency						= $baseToUSDCurrencySpotPrice * $transactionQty;
									
									// end spot prices										
										$binanceTradeTransactionAsLedger 		= new BinanceTradeTransactionAsLedger();								
										$binanceTradeTransactionAsLedger -> setData($fk_binanceOrderID, $accountID, $exchangeTileID, 0, $globalTransactionIdentifierRecordID, $transactionSourceID, $txIDValue, $orderTxID, $transactionPairName, $transactionTime, $baseToUSDCurrencySpotPrice, $transactionQty, $costInQuoteCurrency, $transactionCommission, $transactionCommissionAsset, $transactionIsBuyer, $transactionIsMaker, $transactionIsBestMatch, $walletTypeID, $walletTypeName, 4, $originalTransactionTypeID, $providerWalletID, $baseCurrencyWalletID, $quoteCurrencyWalletID, $baseCurrencyAssetTypeID, 2, $transactionTimestamp, $creationDate, $baseToQuoteCurrencySpotPrice, $baseToUSDCurrencySpotPrice, $btcSpotPriceAtTimeOfTransaction, $costInQuoteCurrency, $feeAmountInUSD, $isDebit, $binanceExchangeRate, $fk_LedgerCurrencyAssetID, $fk_OriginalQuoteCurrency, $userEncryptionKey, $globalCurrentDate, $sid, $dbh);	
										
										$writeBinanceLedgerRecordResponseObject	= $binanceTradeTransactionAsLedger -> writeToDatabase($userEncryptionKey, $dbh);																	
										if ($writeBinanceLedgerRecordResponseObject['wroteToDatabase'] == true)
										{
											errorLog("Succeeded: Credit side of SELL transaction insertion into BinanceTradeTransactionsAsLedger for TransactionID: $txIDValue");							}
									}									
									else
									{
										errorLog("Failed: Credit side of SELL transaction insertion into BinanceTradeTransactionsAsLedger for TransactionID: $txIDValue");										
									}	
									
								}

								//---------------------End Ledger variables
														
							}
						}
					}
					
					$numberProcessed++;	
				}
				else
				{
					error_log("found $txIDValue");
					
					$returnValue[$txIDValue]["existingRecordFound"]				= true;
					$returnValue[$txIDValue]["newTransactionCreated"]			= false;
				}

				errorLog("completed array index $txIDValue");
		    }
		
			if ($includeDetailReporting != true)
			{
				$returnValue													= array();		
			}
			
			$returnValue["binanceDataImported"]									= "complete";
			$returnValue['numberProcessed']										= $numberProcessed;
			$returnValue['cryptoCurrencyTypesImported']							= $cryptoCurrencyTypesImported;
			$returnValue['lastTransactionID']									= $lastTransactionID;
			
	    }
	    catch (Exception $e)
	    {
		   	errorLog("ERROR: array parsing error");	
	    }
	    
	    errorLog(json_encode($returnValue));

        return $returnValue;
    }	

	function getBinanceDepositHistoryForUserViaAPI($tradesList, $exchangeTileID, $startPosition, $baseCurrency, $baseCurrencyAssetTypeID, $baseCurrencyAssetType, $quoteCurrency, $quoteCurrencyAssetTypeID, $quoteCurrencyAssetType, $baseCurrencyWalletID, $quoteCurrencyWalletID, $cryptoCurrencyTypesImported, $name, $accountID, $userEncryptionKey, $transactionSourceID, $transactionSourceName, $walletTypeID, $walletTypeName, $includeDetailReporting, $dataImportEventRecordID, $globalCurrentDate, $sid, $dbh)
	{
    	$returnValue   														= array();
  	
    	$numberProcessed													= 0;

	    try
	    {
		    errorLog("count ".count($tradesList));
		    
		    foreach ($tradesList as $trade)
		    {
		    
				$txIDValue													= $trade -> txId;
				$orderTxID													= null;
				$transactionPrice											= null;
			    $transactionQty												= $trade -> amount;
			    $transactionQuoteQty										= null;
			    $transactionCommission										= null;
			    $transactionCommissionAsset									= null;
			    $transactionIsBuyer 										= 0;
			    $transactionIsMaker 										= 0;
			    $transactionIsBestMatch 									= 0;
			    
			    $isDebit													= 0;
			    
			   	$transactionTimestamp										= $trade -> insertTime;	// transaction timestamp						 
				$transactionTime										    = gmdate("Y-m-d H:i:s", $transactionTimestamp/1000);							
				$creationDate												= $globalCurrentDate; 
									
				$feeAssetTypeID											 	= getEnumValueAssetType("USD", $dbh);
				
				$baseToQuoteCurrencySpotPrice								= 0;	// quoteCurrencySpotPriceAtTimeOfTransaction					
				$baseToUSDCurrencySpotPrice									= 0;	// spotPriceAtTimeOfTransaction
				$btcSpotPriceAtTimeOfTransaction							= 0;	// btcPriceAtTimeOfTransaction
				$feeCurrencySpotPriceAtTimeOfTransaction					= 0;
				$transactionPairName										= $trade -> asset."USD";
				$pairName													= $transactionPairName;
                $binanceCurrencyPair										= new CommonCurrencyPair();
                $commonAssetPairID											= getEnumValueCommonAssetPair($pairName, $dbh);
                $baseCurrency												= null;
                $quoteCurrency												= null;

                $baseCurrencyAssetTypeID									= 0;
                $quoteCurrencyAssetTypeID									= 0;

                $baseCurrencyAssetType										= $trade -> asset;
                $quoteCurrencyAssetType										= "USD";       
                         
		        if (!empty($commonAssetPairID) && $commonAssetPairID > 0)
		        {
		            $instantiationResponseObject							= $binanceCurrencyPair -> instantiateCommonCurrencyPairUsingPairID($commonAssetPairID, $dbh);
		
		            if ($instantiationResponseObject['instantiatedCommonCurrencyPairObject'] == true)
		            {
	                    $baseCurrency										= $binanceCurrencyPair -> getBaseCurrency();
	                    $quoteCurrency										= $binanceCurrencyPair -> getQuoteCurrency();
	
	                    $baseCurrencyAssetTypeID							= $baseCurrency -> getAssetTypeID();
	                    $quoteCurrencyAssetTypeID							= $quoteCurrency -> getAssetTypeID();
	
	                    $baseCurrencyAssetType								= $baseCurrency -> getAssetTypeLabel();
	                    $quoteCurrencyAssetType								= $quoteCurrency -> getAssetTypeLabel();
	                    $baseCurrencyWalletID								= 0;
	                    $quoteCurrencyWalletID								= 0;
	
	                    $baseCurrencyWallet									= new CompleteCryptoWallet();
	
	                    $baseCurrencyResponseObject							= $baseCurrencyWallet -> instantiateWalletUsingCryptoWalletAttributes($accountID, $baseCurrencyAssetTypeID, "", $transactionSourceID, $userEncryptionKey, $dbh);
	
	                    if ($baseCurrencyResponseObject['instantiatedRecord'] == false)
	                    {
	                        // create new wallet, get ID
	                        $baseCurrencyWallet -> setData($accountID, $globalCurrentDate, "", "", "", $baseCurrencyAssetTypeID, $baseCurrencyAssetType, $accountID, "", "", "", false, "https://api.binance.com/api/v3/myTrades", 5, "address", $transactionSourceID, $transactionSourceName, 1, $accountID, $walletTypeID, $walletTypeLabel, $sid, $globalCurrentDate);
	
	                        $baseCurrencyResponseObject			= $baseCurrencyWallet -> writeToDatabase($liUser, $userEncryptionKey, $globalCurrentDate, $sid, $dbh);
	
	                        if ($baseCurrencyResponseObject['wroteToDatabase'] 	== true)
	                        {
	                            $baseCurrencyWalletID			= $baseCurrencyWallet -> getWalletID();
	                        }
	                    }
	                    else
	                    {
	                        errorLog("found base currency for $accountID, $globalCurrentDate, $baseCurrencyAssetTypeID, $baseCurrencyAssetType, $accountID");
	
	                        $baseCurrencyWalletID				= $baseCurrencyWallet -> getWalletID();
	                    }
	
	                    $quoteCurrencyWallet								= new CompleteCryptoWallet();
	
	                    $quoteCurrencyResponseObject						= $quoteCurrencyWallet -> instantiateWalletUsingCryptoWalletAttributes($accountID, $quoteCurrencyAssetTypeID, "", $transactionSourceID, $userEncryptionKey, $dbh);
	
	                    if ($quoteCurrencyResponseObject['instantiatedRecord'] == false)
	                    {
	                        // create new wallet, get ID
	                        $quoteCurrencyWallet -> setData($accountID, $globalCurrentDate, "", "", "", $quoteCurrencyAssetTypeID, $quoteCurrencyAssetType, $accountID, "", "", "", true, "https://api.binance.com/api/v3/myTrades", 5, "address", $transactionSourceID, $transactionSourceName, 4, $accountID, $walletTypeID,"", $sid, $globalCurrentDate);
	
	                        $quoteCurrencyResponseObject					= $quoteCurrencyWallet -> writeToDatabase($accountID, $userEncryptionKey, $globalCurrentDate, $sid, $dbh);
	
	                        if ($quoteCurrencyResponseObject['wroteToDatabase'] == true)
	                        {
	                            $quoteCurrencyWalletID						= $quoteCurrencyWallet -> getWalletID();
	                        }
	                    }
	                    else
	                    {
	                        errorLog("found quote currency for $accountID, $globalCurrentDate, $quoteCurrencyWalletID, $quoteCurrencyAssetType, $accountID");
	
	                        $quoteCurrencyWalletID							= $quoteCurrencyWallet -> getWalletID();
	                    }                   		                
		            }
		        }
                				
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
				
				$transactionAssetTypeID										= getEnumValueAssetType($trade -> asset, $dbh);
				$transactionTypeID											= 7;
				
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
													
							$binanceTradeTransaction -> setData(0, $accountID, $exchangeTileID, 0, $globalTransactionIdentifierRecordID, $transactionSourceID, $txIDValue, $orderTxID, $pairName, $transactionTime, $transactionPrice, $transactionQty, $transactionQuoteQty, $transactionCommission, $transactionCommissionAsset, $transactionIsBuyer, $transactionIsMaker, $transactionIsBestMatch, $walletTypeID, $walletTypeName, 7, $providerWalletID, $baseCurrencyWalletID, $quoteCurrencyWalletID, $baseCurrencyAssetTypeID, $feeAssetTypeID, $transactionTimestamp, $creationDate, $baseToQuoteCurrencySpotPrice, $baseToUSDCurrencySpotPrice, $btcSpotPriceAtTimeOfTransaction, $transactionAmountInUSD, $feeAmountInUSD, $isDebit, $userEncryptionKey, $globalCurrentDate, $sid, $dbh);																						
							
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
			
	    }
	    catch (Exception $e)
	    {
		   	errorLog("ERROR: array parsing error");	
	    }
	    
        return $returnValue;
    }	
    
	function getBinanceWithdrawalHistoryForUserViaAPI($tradesList, $exchangeTileID, $startPosition, $baseCurrency, $baseCurrencyAssetTypeID, $baseCurrencyAssetType, $quoteCurrency, $quoteCurrencyAssetTypeID, $quoteCurrencyAssetType, $baseCurrencyWalletID, $quoteCurrencyWalletID, $cryptoCurrencyTypesImported, $name, $accountID, $userEncryptionKey, $transactionSourceID, $transactionSourceName, $walletTypeID, $walletTypeName, $includeDetailReporting, $dataImportEventRecordID, $globalCurrentDate, $sid, $dbh)
	{
		
		errorLog("Writing withdrawal transactions: ". count($tradesList));
		
    	$returnValue   														= array();
  	
    	$numberProcessed													= 0;

	    try
	    {
		    errorLog("count ".count($tradesList));
		    
		    foreach ($tradesList as $trade)
		    {
		    
				$txIDValue													= $trade -> txId;
				$orderTxID													= null;
				$transactionPrice											= null;
			    $transactionQty												= $trade -> amount;
			    $transactionQuoteQty										= null;
			    $transactionCommission										= null;
			    $transactionCommissionAsset									= null;
			    $transactionIsBuyer 										= 0;
			    $transactionIsMaker 										= 0;
			    $transactionIsBestMatch 									= 0;
			    
			    $isDebit													= 0;
			    
			    $transactionTimestamp										= $trade -> applyTime;	// transaction timestamp						 
				$transactionTime										    = gmdate("Y-m-d H:i:s", $transactionTimestamp/1000);							
				$creationDate												= $globalCurrentDate; 
					
				$feeAssetTypeID											 	= getEnumValueAssetType("USD", $dbh);
				
				$baseToQuoteCurrencySpotPrice								= 0;	// quoteCurrencySpotPriceAtTimeOfTransaction					
				$baseToUSDCurrencySpotPrice									= 0;	// spotPriceAtTimeOfTransaction
				$btcSpotPriceAtTimeOfTransaction							= 0;	// btcPriceAtTimeOfTransaction
				$feeCurrencySpotPriceAtTimeOfTransaction					= 0;
				$transactionPairName										= $trade -> asset."USD";
				$pairName													= $transactionPairName;
                $binanceCurrencyPair										= new CommonCurrencyPair();
                $commonAssetPairID											= getEnumValueCommonAssetPair($pairName, $dbh);
                $baseCurrency												= null;
                $quoteCurrency												= null;

                $baseCurrencyAssetTypeID									= 0;
                $quoteCurrencyAssetTypeID									= 0;

                $baseCurrencyAssetType										= $trade -> asset;
                $quoteCurrencyAssetType										= "USD";       
                         
		        if (!empty($commonAssetPairID) && $commonAssetPairID > 0)
		        {
		            $instantiationResponseObject							= $binanceCurrencyPair -> instantiateCommonCurrencyPairUsingPairID($commonAssetPairID, $dbh);
		
		            if ($instantiationResponseObject['instantiatedCommonCurrencyPairObject'] == true)
		            {
	                    $baseCurrency										= $binanceCurrencyPair -> getBaseCurrency();
	                    $quoteCurrency										= $binanceCurrencyPair -> getQuoteCurrency();
	
	                    $baseCurrencyAssetTypeID							= $baseCurrency -> getAssetTypeID();
	                    $quoteCurrencyAssetTypeID							= $quoteCurrency -> getAssetTypeID();
	
	                    $baseCurrencyAssetType								= $baseCurrency -> getAssetTypeLabel();
	                    $quoteCurrencyAssetType								= $quoteCurrency -> getAssetTypeLabel();
	                    $baseCurrencyWalletID								= 0;
	                    $quoteCurrencyWalletID								= 0;
	
	                    $baseCurrencyWallet									= new CompleteCryptoWallet();
	
	                    $baseCurrencyResponseObject							= $baseCurrencyWallet -> instantiateWalletUsingCryptoWalletAttributes($accountID, $baseCurrencyAssetTypeID, "", $transactionSourceID, $userEncryptionKey, $dbh);
	
	                    if ($baseCurrencyResponseObject['instantiatedRecord'] == false)
	                    {
	                        // create new wallet, get ID
	                        $baseCurrencyWallet -> setData($accountID, $globalCurrentDate, "", "", "", $baseCurrencyAssetTypeID, $baseCurrencyAssetType, $accountID, "", "", "", false, "https://api.binance.com/api/v3/myTrades", 5, "address", $transactionSourceID, $transactionSourceName, 1, $accountID, $walletTypeID, $walletTypeLabel, $sid, $globalCurrentDate);
	
	                        $baseCurrencyResponseObject			= $baseCurrencyWallet -> writeToDatabase($liUser, $userEncryptionKey, $globalCurrentDate, $sid, $dbh);
	
	                        if ($baseCurrencyResponseObject['wroteToDatabase'] 	== true)
	                        {
	                            $baseCurrencyWalletID			= $baseCurrencyWallet -> getWalletID();
	                        }
	                    }
	                    else
	                    {
	                        errorLog("found base currency for $accountID, $globalCurrentDate, $baseCurrencyAssetTypeID, $baseCurrencyAssetType, $accountID");
	
	                        $baseCurrencyWalletID				= $baseCurrencyWallet -> getWalletID();
	                    }
	
	                    $quoteCurrencyWallet								= new CompleteCryptoWallet();
	
	                    $quoteCurrencyResponseObject						= $quoteCurrencyWallet -> instantiateWalletUsingCryptoWalletAttributes($accountID, $quoteCurrencyAssetTypeID, "", $transactionSourceID, $userEncryptionKey, $dbh);
	
	                    if ($quoteCurrencyResponseObject['instantiatedRecord'] == false)
	                    {
	                        // create new wallet, get ID
	                        $quoteCurrencyWallet -> setData($accountID, $globalCurrentDate, "", "", "", $quoteCurrencyAssetTypeID, $quoteCurrencyAssetType, $accountID, "", "", "", true, "https://api.binance.com/api/v3/myTrades", 5, "address", $transactionSourceID, $transactionSourceName, 4, $accountID, $walletTypeID,"", $sid, $globalCurrentDate);
	
	                        $quoteCurrencyResponseObject					= $quoteCurrencyWallet -> writeToDatabase($accountID, $userEncryptionKey, $globalCurrentDate, $sid, $dbh);
	
	                        if ($quoteCurrencyResponseObject['wroteToDatabase'] == true)
	                        {
	                            $quoteCurrencyWalletID						= $quoteCurrencyWallet -> getWalletID();
	                        }
	                    }
	                    else
	                    {
	                        errorLog("found quote currency for $accountID, $globalCurrentDate, $quoteCurrencyWalletID, $quoteCurrencyAssetType, $accountID");
	
	                        $quoteCurrencyWalletID							= $quoteCurrencyWallet -> getWalletID();
	                    }                   		                
		            }
		        }
                				
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
				
				$transactionAssetTypeID										= getEnumValueAssetType($trade -> asset, $dbh);
				$transactionTypeID											= 8;
				
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
													
							$binanceTradeTransaction -> setData(0, $accountID, $exchangeTileID, 0, $globalTransactionIdentifierRecordID, $transactionSourceID, $txIDValue, $orderTxID, $pairName, $transactionTime, $transactionPrice, $transactionQty, $transactionQuoteQty, $transactionCommission, $transactionCommissionAsset, $transactionIsBuyer, $transactionIsMaker, $transactionIsBestMatch, $walletTypeID, $walletTypeName, 8, $providerWalletID, $baseCurrencyWalletID, $quoteCurrencyWalletID, $baseCurrencyAssetTypeID, $feeAssetTypeID, $transactionTimestamp, $creationDate, $baseToQuoteCurrencySpotPrice, $baseToUSDCurrencySpotPrice, $btcSpotPriceAtTimeOfTransaction, $transactionAmountInUSD, $feeAmountInUSD, $isDebit, $userEncryptionKey, $globalCurrentDate, $sid, $dbh);																						
							
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
			
	    }
	    catch (Exception $e)
	    {
		   	errorLog("ERROR: array parsing error");	
	    }
	    
        return $returnValue;
    }		    	

	// End Binance
	
	// Cexio	
	function getCexioTransactionHistoryForUserViaAPI($depositOrderID, $cexioOrderID, $liUser, $pairName, $transactionsList, $exchangeTileID, $startPosition, $baseCurrency, $baseCurrencyAssetTypeID, $baseCurrencyAssetType, $quoteCurrency, $quoteCurrencyAssetTypeID, $quoteCurrencyAssetType, $baseCurrencyWalletID, $quoteCurrencyWalletID, $cryptoCurrencyTypesImported, $name, $accountID, $userEncryptionKey, $transactionSourceID, $transactionSourceName, $walletTypeID, $walletTypeName, $includeDetailReporting, $dataImportEventRecordID, $globalCurrentDate, $sid, $dbh)
	{
		errorLog("getCexioTransactionHistoryForUserViaAPI -- $depositOrderID, $cexioOrderID, $liUser, $pairName, $baseCurrencyAssetTypeID, $baseCurrencyAssetType, $quoteCurrencyAssetTypeID, $quoteCurrencyAssetType");
		
		$currencyDataSourceID														= 15;
		
		$btcCurrencyID																= 1;
		$usdCurrencyID																= 2;
		
    	$returnValue   																= array();
		errorLog("Transactions Count: ".count($transactionsList));
		$depositLedgerAdded															= false;
		$depositAmount																= 0;
		
		if (count($transactionsList) == 2)
		{
			$tradesArray															= $transactionsList;
	    	$numberProcessed														= 0;
	    				
		    try
		    {
			    
			    $firstTransaction													= $transactionsList[0];
			    $secondTransaction													= $transactionsList[1];
			    
			    foreach ($transactionsList as $trade)
			    {
				    errorLog("Transactions for each loop");
					$transactionPairName											= "";
					$txIDValue														= $trade -> id;
					$orderTxID														= $trade -> order;		
				    $transactionCommission											= $secondTransaction -> fee_amount;	

				    $transactionCommissionAsset										= $secondTransaction -> symbol;			    	    	
				    $cexioExchangeRate												= 0;
				    $ledgerCurrencyAssetID											= 0;
					$ledgerCurrencyAssetLabel			    						= "";
				    $originalQuoteCurrency											= 0;
	                $isDebit														= 0;
	                $transactionType												= $trade -> type;
	                $originalTransactionTypeID										= 0;
	                $transactionTime										    	= str_replace("T"," ", $trade -> time);
	                $transactionTime										    	= str_replace("Z","", $transactionTime);
	                $transactionTimestamp 											= strtotime($transactionTime);
	                $creationDate													= $globalCurrentDate;
	                $quoteCurrencyAssetTypeID				    					= 2;
					$priceInQuoteCurrency											= 0;
					$volBaseCurrency												= 0;
					$spotPriceArray													= array();
					
					if (strtoupper($transactionType) == "SELL")
	                {
		                errorLog("In Sell ");

	                    $originalTransactionTypeID									= 4;
	                    
	                    $baseCurrencyAssetTypeLabel									= $firstTransaction -> symbol;
	                    $baseCurrencyAssetTypeID									= getEnumValueAssetType($baseCurrencyAssetTypeLabel, $dbh);
	                    $quoteCurrencyAssetTypeLabel								= $secondTransaction -> symbol;
	                    $quoteCurrencyAssetTypeID									= getEnumValueAssetType($quoteCurrencyAssetTypeLabel, $dbh);
	                    $baseToQuoteCurrencyExchangeRate							= $secondTransaction -> price;
	                    $baseToQuoteCurrencyExchangeDateTime						= substr($transactionTime, 0, 10);
	                    
	                    setDailyPriceData($baseCurrencyAssetTypeID, $quoteCurrencyAssetTypeID, $transactionTime, $baseToQuoteCurrencyExchangeRate, $currencyDataSourceID, $globalCurrentDate, $sid, $dbh);
	                    
	                    setDailyPriceData($baseCurrencyAssetTypeID, $quoteCurrencyAssetTypeID, $baseToQuoteCurrencyExchangeDateTime, $baseToQuoteCurrencyExchangeRate, $currencyDataSourceID, $globalCurrentDate, $sid, $dbh);
	                    
	                    if (property_exists($trade, "price"))
	                    {
		                    errorLog("In Sell Positive");
		                    $transactionTypeID										= 1; 
	                    	$isDebit												= 0;	
	                        $transactionPairName									= $firstTransaction -> symbol.$secondTransaction -> symbol;
	                        $cexioExchangeRate										= $secondTransaction -> price;
	                        $ledgerCurrencyAssetID									= getEnumValueAssetType($secondTransaction -> symbol, $dbh);
                        
	                        $ledgerCurrencyAssetLabel								= $secondTransaction -> symbol;
	                        $originalQuoteCurrency									= getEnumValueAssetType($firstTransaction -> symbol, $dbh);
	
							if ($ledgerCurrencyAssetID == 2)
	                        {
	                            $priceInQuoteCurrency								= 1;
	                        }
	                        else
	                        {
	                            $spotPriceArray		 								= getSpotPriceForAssetPairUsingSourceCascade($ledgerCurrencyAssetID, 2, $baseToQuoteCurrencyExchangeDateTime, 15, "CEX.IO price by date", $dbh);
	                            $priceInQuoteCurrency								= $spotPriceArray["spotPrice"];
	                        }
	
	                        $volBaseCurrency										= abs($secondTransaction -> amount);
	                        $transactionCommission									= $secondTransaction -> fee_amount/$secondTransaction -> price;
	                        
	                        if ($secondTransaction -> symbol == "USD")
	                        {
								$feeAmountInUSD										= $secondTransaction -> fee_amount;                        
	                        }
	                        else
	                        {
		                        $feeAssetTypeID										= getEnumValueAssetType($firstTransaction -> symbol, $dbh);
	                            $spotPriceArray		 								= getSpotPriceForAssetPairUsingSourceCascade($feeAssetTypeID, 2, $baseToQuoteCurrencyExchangeDateTime, 15, "CEX.IO price by date", $dbh);
	                            	     
				                $quoteCurrenySpotPrice								= $spotPriceArray["spotPrice"];			                                   
								$feeAmountInUSD										= $secondTransaction -> fee_amount * $quoteCurrenySpotPrice; 	                        
	                        }	                        
	                    }
	                    else
	                    {
	                    	$transactionTypeID										= 4; 		                    
	                    	$isDebit												= 1;		                    
		                    
		                    errorLog("In Sell Negative");		                    
	                        
	                        $transactionPairName									= $firstTransaction -> symbol.$secondTransaction -> symbol;
	                        $cexioExchangeRate										= 1 / $secondTransaction -> price;
	                        $ledgerCurrencyAssetID									= getEnumValueAssetType($firstTransaction -> symbol, $dbh);
	                                                
	                        $ledgerCurrencyAssetLabel								= $firstTransaction -> symbol;                        
	                        $originalQuoteCurrency									= getEnumValueAssetType($secondTransaction -> symbol, $dbh);

							if ($ledgerCurrencyAssetID == 2)
	                        {
	                            $priceInQuoteCurrency								= 1;
	                        }
	                        elseif ($secondTransaction -> symbol == "USD")
	                        {
	                            $priceInQuoteCurrency								= $secondTransaction -> price;		                        
	                        }
	                        else
	                        {
	                            $spotPriceArray		 								= getSpotPriceForAssetPairUsingSourceCascade($ledgerCurrencyAssetID, 2, $baseToQuoteCurrencyExchangeDateTime, 15, "CEX.IO price by date", $dbh);
	                            
	                            $priceInQuoteCurrency								= $spotPriceArray["spotPrice"];
	                            
	                        }
	                        $volBaseCurrency										= abs($firstTransaction -> amount);
	                        $transactionCommission									= 0;
	                        $feeAmountInUSD											= 0;
	                    }
	                }
	                else
	                {
	                    $originalTransactionTypeID									= 1;		                
		                errorLog("In Buy ");		                
	                    
	                    $baseCurrencyAssetTypeLabel									= $secondTransaction -> symbol;
	                    $baseCurrencyAssetTypeID									= getEnumValueAssetType($baseCurrencyAssetTypeLabel, $dbh);
	                    $quoteCurrencyAssetTypeLabel								= $firstTransaction -> symbol;
	                    $quoteCurrencyAssetTypeID									= getEnumValueAssetType($quoteCurrencyAssetTypeLabel, $dbh);
	                    $baseToQuoteCurrencyExchangeRate							= $secondTransaction -> price;
	                    $baseToQuoteCurrencyExchangeDateTime						= substr($transactionTime, 0, 10);
	                    
	                    setDailyPriceData($baseCurrencyAssetTypeID, $quoteCurrencyAssetTypeID, $transactionTime, $baseToQuoteCurrencyExchangeRate, $currencyDataSourceID, $globalCurrentDate, $sid, $dbh);
	                    
	                    setDailyPriceData($baseCurrencyAssetTypeID, $quoteCurrencyAssetTypeID, $baseToQuoteCurrencyExchangeDateTime, $baseToQuoteCurrencyExchangeRate, $currencyDataSourceID, $globalCurrentDate, $sid, $dbh);
	                    
	                    if (property_exists($trade, "price"))
	                    {
		                    $transactionTypeID										= 1; 
		                    $isDebit												= 0;
		                    
		                    errorLog("In Buy Positive");		                    

	                        $transactionPairName									= $secondTransaction -> symbol.$firstTransaction -> symbol;
	                        $cexioExchangeRate										= $secondTransaction -> price;
	                        $ledgerCurrencyAssetID									= getEnumValueAssetType($secondTransaction -> symbol, $dbh);
	                        errorLog("ledgerCurrencyAssetID: $ledgerCurrencyAssetID");	                        
	                        $ledgerCurrencyAssetLabel								= $secondTransaction -> symbol;                        
	                        $originalQuoteCurrency									= getEnumValueAssetType($firstTransaction -> symbol, $dbh);
	                        
	                        if ($ledgerCurrencyAssetID == 2)
	                        {
	                            $priceInQuoteCurrency								= 1;
	                        }
	                        elseif ($firstTransaction -> symbol == "USD")
	                        {
		                        $priceInQuoteCurrency								= $secondTransaction -> price;
	                        }
	                        else
	                        {
	                            $spotPriceArray		 								= getSpotPriceForAssetPairUsingSourceCascade($ledgerCurrencyAssetID, 2, $baseToQuoteCurrencyExchangeDateTime, 15, "CEX.IO price by date", $dbh);
	                            
	                            $priceInQuoteCurrency								= $spotPriceArray["spotPrice"];
	                            
	                        }
	
	                        $volBaseCurrency										= abs($secondTransaction -> amount);
	                        $transactionCommission									= $secondTransaction -> fee_amount;
	                        
	                        if ($secondTransaction -> symbol2 == "USD")
	                        {
								$feeAmountInUSD										= $secondTransaction -> fee_amount;                        
	                        }
	                        else
	                        {
		                        $feeAssetTypeID										= getEnumValueAssetType($firstTransaction -> symbol, $dbh);
	                            $spotPriceArray		 								= getSpotPriceForAssetPairUsingSourceCascade($feeAssetTypeID, 2, $baseToQuoteCurrencyExchangeDateTime, 15, "CEX.IO price by date", $dbh);
	                            	     
				                $quoteCurrenySpotPrice								= $spotPriceArray["spotPrice"];			                                   
								$feeAmountInUSD										= $secondTransaction -> fee_amount * $quoteCurrenySpotPrice;  		                        
	                        }
	                    }
	                    else
	                    {
		                    errorLog("In Buy Negative");		                    
		                    $transactionTypeID										= 4; 
		                    $depositAmount											= abs($firstTransaction -> amount) + $firstTransaction -> balance;
	                    	$isDebit												= 1;		                    
		                    
	                        $transactionPairName									= $secondTransaction -> symbol.$firstTransaction -> symbol;
	                        $cexioExchangeRate										= 1 / $secondTransaction -> price;
	                        $ledgerCurrencyAssetID									= getEnumValueAssetType($firstTransaction -> symbol, $dbh);

	                        
	                        $ledgerCurrencyAssetLabel								= $firstTransaction -> symbol;                        
	                        $originalQuoteCurrency									= getEnumValueAssetType($secondTransaction -> symbol, $dbh);
	                        
	                        if ($ledgerCurrencyAssetID == 2)
	                        {
	                            $priceInQuoteCurrency								= 1;
	                        }
	                        else
	                        {
	                            $spotPriceArray		 								= getSpotPriceForAssetPairUsingSourceCascade($ledgerCurrencyAssetID, 2, $baseToQuoteCurrencyExchangeDateTime, 15, "CEX.IO price by date", $dbh);
	                            
	                            $priceInQuoteCurrency								= $spotPriceArray["spotPrice"];
	                        }
	                        $volBaseCurrency										= abs($firstTransaction -> amount);
	                        $transactionCommission 									= 0;
	                        $feeAmountInUSD											= 0;
	                    }
	                }
	                
	                $costInQuoteCurrency											= $priceInQuoteCurrency * $volBaseCurrency;
	                
	                $btcCurrencySpotPriceAtTimeOfTransactionArray		 								= getSpotPriceForAssetPairUsingSourceCascade(1, 2, $baseToQuoteCurrencyExchangeDateTime, 15, "CEX.IO price by date", $dbh); // quoteCurrencySpotPriceAtTimeOfTransaction		
	              
					$btcSpotPriceAtTimeOfTransaction								= $btcCurrencySpotPriceAtTimeOfTransactionArray['spotPrice']; // btcPriceAtTimeOfTransaction				                            
						                            
					$quoteCurrencySpotPriceAtTimeOfTransactionArray		 			= getSpotPriceForAssetPairUsingSourceCascade($ledgerCurrencyAssetID, 2, $baseToQuoteCurrencyExchangeDateTime, 15, "CEX.IO price by date", $dbh);
					
	                $quoteCurrencySpotPriceAtTimeOfTransaction						= $quoteCurrencySpotPriceAtTimeOfTransactionArray['spotPrice'];	// quoteCurrencySpotPriceAtTimeOfTransaction
					
					$spotPriceAtTimeOfTransaction									= $priceInQuoteCurrency; //$cexioExchangeRate;	// spotPriceAtTimeOfTransaction
	               
					$feeCurrencySpotPriceAtTimeOfTransaction						= 0;
					
					
					// get spot price for fee currency in USD
					$feeAssetTypeID													= getEnumValueAssetType($secondTransaction -> symbol, $dbh);
					$cascadeRetrieveSpotPriceResponseObject							= getSpotPriceForAssetPairUsingSourceCascade($feeAssetTypeID, $usdCurrencyID, $baseToQuoteCurrencyExchangeDateTime, 15, "CEX.IO price by date", $dbh);
						
					if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
					{
						$feeCurrencySpotPriceAtTimeOfTransaction					= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
					}
			
/*
					$feeAmountInUSD											 		= $feeCurrencySpotPriceAtTimeOfTransaction * $transactionCommission; // fee amount in USD							

					if ($feeAssetTypeID = 2)
					{
						$feeAmountInUSD											 	= $costInQuoteCurrency * $transactionCommission;						
					}
*/
	
					$transactionAmountInUSD											= $costInQuoteCurrency;	// usdAmount
					$transactionAmountMinusFeeInUSD									= $transactionAmountInUSD - $feeAmountInUSD;
					$sentQuantity													= $transactionAmountInUSD;
					$amount															= $transactionAmountInUSD;
	
					error_log("getCexioTransactionHistoryForUserViaAPI $transactionTime $transactionPairName $priceInQuoteCurrency $volBaseCurrency $costInQuoteCurrency $transactionCommission $transactionCommissionAsset");
					
				    // @Task - write code to convert currencies between USD and BTC and the current type - use amount objects
				    // Cexio - every time I read a spot price, write it to the daily spot price table
// 				    setDailyPriceData($ledgerCurrencyAssetID, 2, $transactionTimestamp, $priceInQuoteCurrency, $transactionSourceID, $globalCurrentDate, $sid, $dbh);
				    
				    // @Task - it may be better to compact the array rather than have index gaps - write a function that checks to see if a value already exists in array, and if not, adds it
				    
				    if (isset($cryptoCurrencyTypesImported[$ledgerCurrencyAssetID][2]))
					{
						$currentCount												= $cryptoCurrencyTypesImported[$ledgerCurrencyAssetID][2];
						$currentCount++;
						
						$cryptoCurrencyTypesImported[$ledgerCurrencyAssetID][2]	= $currentCount;
					}
					else
					{
						$cryptoCurrencyTypesImported[$ledgerCurrencyAssetID][2]	= 1;	
					}
					
					if ($originalQuoteCurrency != 2)
					{
						if (isset($cryptoCurrencyTypesImported[$ledgerCurrencyAssetID][$originalQuoteCurrency]))
						{
							$currentCount											= $cryptoCurrencyTypesImported[$ledgerCurrencyAssetID][$originalQuoteCurrency];
							$currentCount++;
							
							$cryptoCurrencyTypesImported[$ledgerCurrencyAssetID][$originalQuoteCurrency]	= $currentCount;
						}
						else
						{
							$cryptoCurrencyTypesImported[$ledgerCurrencyAssetID][$originalQuoteCurrency]	= 1;	
						}	
					}
					
					$transactionTypeID												= getEnumValueTransactionType($transactionType, $dbh);
					
					// check for global transaction ID
					
					error_log("getGlobalTransactionIdentificationRecordID for $liUser, $ledgerCurrencyAssetID, $transactionSourceID, $globalCurrentDate, $sid");
					
				    $globalTransactionIDTestResults									= getGlobalTransactionIdentificationRecordID($accountID, $ledgerCurrencyAssetID, $txIDValue, $transactionSourceID, $globalCurrentDate, $sid, $dbh);
				   			    
				    if ($globalTransactionIDTestResults['foundNativeTransactionForAccount'] == false)
					{
						error_log("not found $txIDValue");
						
						$returnValue[$txIDValue]["existingRecordFound"]				= false;
						
						// create one if not found
						$globalTransactionCreationResults							= createGlobalTransactionIdentificationRecord($accountID, $exchangeTileID, $ledgerCurrencyAssetID, $txIDValue, $transactionSourceID, $globalCurrentDate, $sid, $dbh);
									
						if ($globalTransactionCreationResults['createdGlobalTransactionIdentificationRecord'] == true)
						{
							error_log("createGlobalTransactionIdentificationRecord success");
							
							$returnValue[$txIDValue]["createdGTIR"]					= true;
								
							$globalTransactionIdentifierRecordID					= $globalTransactionCreationResults['globalTransactionIdentificationRecordID'];
							$profitStanceTransactionIDValue							= $globalTransactionCreationResults['profitStanceTransactionIDValue'];
								
							// @Task - this is where I need to use the new provider account wallet idea of 
								
							$providerAccountWallet									= new ProviderAccountWallet();
								
							// check for provider account wallet							
							$instantiationResult									= $providerAccountWallet -> instantiateAccountWalletObjectForAccountByAssetTypeIDAndTransactionSourceID($accountID, $ledgerCurrencyAssetID, $transactionSourceID, $dbh);
								
							if ($instantiationResult['instantiatedWallet'] == false)
							{
								// create wallet if not found
								$providerAccountWallet -> createAccountWalletObject($accountID, $ledgerCurrencyAssetID, $ledgerCurrencyAssetLabel, $accountID, "$accountID-$transactionSourceID-$ledgerCurrencyAssetLabel", $walletTypeID, "Private Ledger Based Wallet", $transactionSourceID, $transactionSourceName, $globalCurrentDate, $sid, $dbh);
							}
								
							$providerWalletID										= $providerAccountWallet -> getAccountWalletID();
								
							if ($providerWalletID > 0)
							{
								// create Cexio trade transaction object and write it
								
								$cexioTradeTransaction 								= new CexioTradeTransaction();
														
								$cexioTradeTransaction -> setData(0, $cexioOrderID, $accountID, $exchangeTileID, 0, $globalTransactionIdentifierRecordID, $transactionSourceID, $txIDValue, $orderTxID, $transactionPairName, $transactionTime, $transactionTimestamp, $priceInQuoteCurrency, $volBaseCurrency, $costInQuoteCurrency, $transactionCommission, $ledgerCurrencyAssetLabel, $walletTypeID, $walletTypeName, $transactionTypeID, $originalTransactionTypeID, $providerWalletID, $baseCurrencyWalletID, $quoteCurrencyWalletID, $ledgerCurrencyAssetID, 2, $creationDate, $quoteCurrencySpotPriceAtTimeOfTransaction, $btcSpotPriceAtTimeOfTransaction, $volBaseCurrency, $feeAmountInUSD, $isDebit, $cexioExchangeRate, $ledgerCurrencyAssetID, $originalQuoteCurrency, $userEncryptionKey, $globalCurrentDate, $sid, $dbh);
								
								$writeCexioRecordResponseObject						= $cexioTradeTransaction -> writeToDatabase($userEncryptionKey, $dbh);
								
								if ($writeCexioRecordResponseObject['wroteToDatabase'] == true)
								{
									error_log("Transaction inserted into CexioTradeTransactions table");
									// now that the transaction has been created, create the association record for the closing array if size > 0
									
									$ledgerAmount									= $volBaseCurrency;
									
									if ($isDebit == 1)
									{
										$ledgerAmount								= $ledgerAmount * -1;	
									}
									
									$profitStanceLedgerEntry						= new ProfitStanceLedgerEntry();
									$profitStanceLedgerEntry -> setData($accountID, $ledgerCurrencyAssetID, $ledgerCurrencyAssetLabel, 41, "Cexio", $exchangeTileID, $globalTransactionIdentifierRecordID, $transactionTime, $ledgerAmount, $dbh);
									
									$writeProfitStanceLedgerEntryRecordResponseObject	= $profitStanceLedgerEntry -> writeToDatabase($dbh);
									
									if ($writeProfitStanceLedgerEntryRecordResponseObject['wroteToDatabase'] == true)
									{
										error_log("wrote profitStance ledger entry $accountID, $baseCurrencyAssetTypeID, $baseCurrencyAssetType, 41, \"Cexio\", $exchangeTileID, $globalTransactionIdentifierRecordID, $transactionTime, $volBaseCurrency to the database.");
									}
									else
									{
										error_log("could not write profitStance ledger entry $accountID, $baseCurrencyAssetTypeID, $baseCurrencyAssetType, 41, \"Cexio\", $exchangeTileID, $globalTransactionIdentifierRecordID, $transactionTime, $volBaseCurrency to the database.");	
									}
									
									// update native record ID in GTRID table
									$setNativeTransactionRecordIDResult				= setNativeTransactionRecordIDForGlobalTransactionIndentificationRecordID($accountID, $cexioTradeTransaction -> getCexioTransactionRecordID(), $globalTransactionIdentifierRecordID, $globalCurrentDate, $sid, $dbh);
									
					
								}
							}
						}

						$numberProcessed++;	
						
						
						// Write Deposit Record to ProfitStanceLedgerEntries table
						errorLog("DepositOrderID: $depositOrderID, OrderTxID: $orderTxID, DepositLedgerAdded: ".$depositLedgerAdded.", Deposit Amount: $depositAmount");
						if ($depositOrderID == $orderTxID && $depositLedgerAdded == false && $depositAmount > 0)
						{
							$depositDateTime										= new DateTime($transactionTime);
							$depositDateTime										= $depositDateTime -> modify('-1 hour');
							
							$formattedDepositDateTime								= $depositDateTime -> format("Y-m-d H:i:s");
							$depositTimestamp										= $depositDateTime -> getTimestamp();
							
							$depositLedgerAdded 									= true;
							
							error_log("getGlobalTransactionIdentificationRecordID for $liUser, 2, $transactionSourceID, $globalCurrentDate, $sid");
							
						    $globalTransactionIDTestResults							= getGlobalTransactionIdentificationRecordID($accountID, 2, $orderTxID, $transactionSourceID, $globalCurrentDate, $sid, $dbh);
						   			    
						    if ($globalTransactionIDTestResults['foundNativeTransactionForAccount'] == false)
							{
								error_log("not found $orderTxID");
								
							
								// create one if not found
								$globalTransactionCreationResults					= createGlobalTransactionIdentificationRecord($accountID, $exchangeTileID, 2, $orderTxID, $transactionSourceID, $globalCurrentDate, $sid, $dbh);
											
								if ($globalTransactionCreationResults['createdGlobalTransactionIdentificationRecord'] == true)
								{
									error_log("createGlobalTransactionIdentificationRecord success");
									
									
									$globalTransactionIdentifierRecordID			= $globalTransactionCreationResults['globalTransactionIdentificationRecordID'];
									$profitStanceTransactionIDValue					= $globalTransactionCreationResults['profitStanceTransactionIDValue'];
																				
									$providerAccountWallet							= new ProviderAccountWallet();
										
									// check for provider account wallet							
									$instantiationResult							= $providerAccountWallet -> instantiateAccountWalletObjectForAccountByAssetTypeIDAndTransactionSourceID($accountID, 2, $transactionSourceID, $dbh);
										
									if ($instantiationResult['instantiatedWallet'] == false)
									{
										// create wallet if not found
										$providerAccountWallet -> createAccountWalletObject($accountID, 2, "USD", $accountID, "$accountID-$transactionSourceID-USD", $walletTypeID, "Private Ledger Based Wallet", $transactionSourceID, $transactionSourceName, $globalCurrentDate, $sid, $dbh);
									}
										
									$providerWalletID								= $providerAccountWallet -> getAccountWalletID();
										
									if ($providerWalletID > 0)
									{
										// create Cexio trade transaction object and write it
										
										$cexioTradeTransaction 						= new CexioTradeTransaction();
									
										// Deposit Transaction		
										errorLog("Inserting Deposit transaction");				
										$cexioTradeTransaction -> setData($depositOrderID, $cexioOrderID, $accountID, $exchangeTileID, 0, $globalTransactionIdentifierRecordID, $transactionSourceID, $txIDValue, $orderTxID, $transactionPairName, $formattedDepositDateTime, $depositTimestamp, $priceInQuoteCurrency, $depositAmount, $depositAmount, 0, $ledgerCurrencyAssetLabel, $walletTypeID, $walletTypeName, 7, 7, $providerWalletID, $baseCurrencyWalletID, $quoteCurrencyWalletID, 2, 2, $creationDate, 1, $btcSpotPriceAtTimeOfTransaction, $depositAmount, 0, 0, 1, $ledgerCurrencyAssetID, $originalQuoteCurrency, $userEncryptionKey, $globalCurrentDate, $sid, $dbh);
										
										$writeCexioRecordResponseObject				= $cexioTradeTransaction -> writeToDatabase($userEncryptionKey, $dbh);
										
										if ($writeCexioRecordResponseObject['wroteToDatabase'] == true)
										{
											error_log("Transaction inserted into CexioTradeTransactions table");
											// now that the transaction has been created, create the association record for the closing array if size > 0
											
											$ledgerAmount							= $depositAmount;
											
											
											$profitStanceLedgerEntry				= new ProfitStanceLedgerEntry();
											$profitStanceLedgerEntry -> setData($accountID, $ledgerCurrencyAssetID, "USD", 41, "Cexio", $exchangeTileID, $globalTransactionIdentifierRecordID, $transactionTime, $ledgerAmount, $dbh);
											
											$writeProfitStanceLedgerEntryRecordResponseObject	= $profitStanceLedgerEntry -> writeToDatabase($dbh);
											
											if ($writeProfitStanceLedgerEntryRecordResponseObject['wroteToDatabase'] == true)
											{
												error_log("wrote profitStance ledger entry $accountID, 2, $baseCurrencyAssetType, 41, \"Cexio\", $exchangeTileID, $globalTransactionIdentifierRecordID, $transactionTime, $depositAmount to the database.");
											}
											else
											{
												error_log("could not write profitStance ledger entry $accountID, 2, $baseCurrencyAssetType, 41, \"Cexio\", $exchangeTileID, $globalTransactionIdentifierRecordID, $transactionTime, $depositAmount to the database.");	
											}
											
											// update native record ID in GTRID table
											$setNativeTransactionRecordIDResult		= setNativeTransactionRecordIDForGlobalTransactionIndentificationRecordID($accountID, $cexioTradeTransaction -> getCexioTransactionRecordID(), $globalTransactionIdentifierRecordID, $globalCurrentDate, $sid, $dbh);

										}
									}
								}
								
								$numberProcessed++;	
							}
							else
							{
								error_log("found $txIDValue");
							}						
						}
					}
					else
					{
						error_log("found $txIDValue");
						
						$returnValue[$txIDValue]["existingRecordFound"]				= true;
						$returnValue[$txIDValue]["newTransactionCreated"]			= false;
					}
	
					error_log("completed array index $txIDValue");
			    }
			
				if ($includeDetailReporting != true)
				{
					$returnValue													= array();		
				}
				
				$returnValue["cexioDataImported"]							    	= "complete";
				$returnValue['numberProcessed']										= $numberProcessed;
				$returnValue['cryptoCurrencyTypesImported']							= $cryptoCurrencyTypesImported;
				
		    }
		    catch (Exception $e)
		    {
			   	error_log("ERROR: array parsing error");	
		    }
		    
		    error_log(json_encode($returnValue));		
	    }
		else
		{
			errorLog("Cexio order: $cexioOrderID does not have proper transactions");
		}

        return $returnValue;
    }	
    
 	function getOrderStatusByCode($statusCode)
	{
		switch ($statusCode) 
		{
		    case 'd':
		        return "done, fully executed";
		        break;
		    case 'c':
		        return "canceled, not executed";
		        break;
		    case 'cd':
		        return "cancel-done, partially executed";
		        break;
		    case 'a':
		        return "active, created";
		        break;			        
		}
	}    	
	
	function createCommonTransactionsForCexioTradeTransactions($liuAccountID, $userEncryptionKey, $globalCurrentDate, $sid, $dbh)
	{
		errorLog("createCommonTransactionsForCexioTradeTransactions($liuAccountID, $userEncryptionKey, $globalCurrentDate, $sid");
		
		$responseObject															= array();

		$responseObject['importedTransactions']									= false;
		
		$transactionSourceID													= 41;
		$transactionStatusID													= 1;
		$transactionStatusLabel													= "complete";
		$transactionSourceLabel													= "Cexio";
		
		try
		{	
					
			$getCexioTransactionRecords											= $dbh -> prepare("SELECT
	CexioTradeTransactions.cexioTransactionRecordID AS transactionID,
	CexioTradeTransactions.FK_ExchangeTileID,
	CexioTradeTransactions.FK_GlobalTransactionRecordID AS FK_GlobalTransactionIdentificationRecordID,
	CexioTradeTransactions.FK_AccountID AS authorID,
	CexioTradeTransactions.FK_AccountID AS accountID,
	CexioTradeTransactions.FK_TransactionTypeID AS transactionTypeID,
	CexioTradeTransactions.FK_BaseCurrencyTypeID AS baseCurrencyID,		
	baseCurrencyAsset.assetTypeLabel AS baseCurrencyName,
	2 AS quoteSpotPriceCurrencyID,
	'USD' AS quoteSpotPriceCurrencyName,
	CexioTradeTransactions.creationDate,
	CexioTradeTransactions.transactionTime AS transactionDate,
	CexioTradeTransactions.transactionTimestamp,
	AES_DECRYPT(CexioTradeTransactions.encryptedTxIDValue, UNHEX(SHA2(:userEncryptionKey,512))) AS vendorTransactionID,
    ABS(CexioTradeTransactions.volBaseCurrency) AS btcQuantityTransacted,
    ABS(CexioTradeTransactions.usdAmount) AS usdQuantityTransacted,
    CexioTradeTransactions.spotPriceAtTimeOfTransaction AS spotPriceAtTimeOfTransaction,
    CexioTradeTransactions.spotPriceAtTimeOfTransaction  AS btcPriceAtTimeOfTransaction,        
    ABS(CexioTradeTransactions.volBaseCurrency*CexioTradeTransactions.spotPriceAtTimeOfTransaction) AS usdTransactionAmountWithFees,
    CexioTradeTransactions.feeAmountInUSD AS TransactionFeeAmount,
    ABS(CexioTradeTransactions.feeAmountInUSD) AS usdFeeAmount,
	ABS(CexioTradeTransactions.volBaseCurrency*CexioTradeTransactions.spotPriceAtTimeOfTransaction) - (CexioTradeTransactions.feeAmountInUSD*CexioTradeTransactions.spotPriceAtTimeOfTransaction) AS transactionAmountMinusFeeInUSD,
    ABS(CexioTradeTransactions.volBaseCurrency*CexioTradeTransactions.spotPriceAtTimeOfTransaction) + (CexioTradeTransactions.feeAmountInUSD*CexioTradeTransactions.spotPriceAtTimeOfTransaction) AS transactionAmountPlusFeeInUSD,
	'' AS  providerNotes,
	CexioTradeTransactions.isDebit,
	CexioTradeTransactions.FK_BaseCurrencyWalletID AS FK_SourceAddressID,
	CexioTradeTransactions.FK_QuoteCurrencyTypeID AS FK_DestinationAddressID,
	TransactionTypes.displayTransactionTypeLabel,
	TransactionTypes.transactionTypeLabel
FROM
	CexioTradeTransactions
	INNER JOIN TransactionTypes ON CexioTradeTransactions.FK_TransactionTypeID = TransactionTypes.transactionTypeID AND TransactionTypes.languageCode = 'EN'
	INNER JOIN AssetTypes baseCurrencyAsset ON CexioTradeTransactions.FK_BaseCurrencyTypeID = baseCurrencyAsset.assetTypeID AND baseCurrencyAsset.languageCode = 'EN'
WHERE
	CexioTradeTransactions.FK_AccountID = :accountID
ORDER BY
	CexioTradeTransactions.transactionTimestamp");
	
			$getCexioTransactionRecords -> bindValue(':accountID', $liuAccountID);
			$getCexioTransactionRecords -> bindValue(':userEncryptionKey', $userEncryptionKey);
		
			if ($getCexioTransactionRecords -> execute() && $getCexioTransactionRecords -> rowCount() > 0)
			{

				errorLog("began get cexio crypto transaction records ".$getCexioTransactionRecords -> rowCount() > 0);
				
				while ($row = $getCexioTransactionRecords -> fetchObject())
				{		
					$transactionID												= $row ->transactionID;
					$exchangeTileID												= $row ->FK_ExchangeTileID;
					$globalTransactionIdentificationRecordID					= $row ->FK_GlobalTransactionIdentificationRecordID;
					$accountID													= $row ->accountID;	
					$authorID													= $row ->authorID;
						
					$transactionTypeID											= $row ->transactionTypeID;
					$baseCurrencyID												= $row ->baseCurrencyID; // was assetTypeID - done
					$baseCurrencyName											= $row ->baseCurrencyName; // assetTypeName - not needed
					$quoteSpotPriceCurrencyID									= $row ->quoteSpotPriceCurrencyID; // was spotPriceCurrencyTypeID - done, needs verification
                    $quoteSpotPriceCurrencyName									= $row ->quoteSpotPriceCurrencyName; // was spotPriceCurrencyType	
                    $creationDate												= $row ->creationDate;
                    $transactionDate											= $row ->transactionDate;
                    $transactionTimestamp										= $row ->transactionTimestamp;
                    $vendorTransactionID										= $row ->vendorTransactionID;	
                    $amount														= $row ->btcQuantityTransacted;
                    $transactionAmountInUSD										= $row ->usdQuantityTransacted;
                    $baseToUSDCurrencySpotPrice									= $row ->spotPriceAtTimeOfTransaction;
                    $btcSpotPriceAtTimeOfTransaction							= $row ->btcPriceAtTimeOfTransaction;
                    $fee														= $row ->TransactionFeeAmount;
                    $feeAmountInUSD												= $row ->usdFeeAmount;
                    $transactionAmountMinusFeeInUSD								= $row ->transactionAmountMinusFeeInUSD;
                    $transactionAmountPlusFeeInUSD								= $row ->transactionAmountPlusFeeInUSD;
                    $creationDate												= $row ->creationDate;
					
					$usdTransactionAmountWithFees								= $row ->usdTransactionAmountWithFees;
					$providerNotes												= $row ->providerNotes;
					$transactionTypeLabel										= $row ->transactionTypeLabel;
					$displayTransactionTypeLabel								= $row ->displayTransactionTypeLabel;
					$isDebit													= $row ->isDebit;
						
					$sourceWalletID												= $row ->FK_SourceAddressID;
					$destinationWalletID										= $row ->FK_DestinationAddressID;
						
					$responseObject['processingTransaction'][]					= $vendorTransactionID;
					
					$getNativeAndCommonTransactionRecordIDsResult				= getNativeAndCommonTransactionRecordIDsForGlobalTransactionIdentificationRecordID($accountID, $baseCurrencyID, $vendorTransactionID, $transactionSourceID, $globalTransactionIdentificationRecordID, $globalCurrentDate, $sid, $dbh);
					
					errorLog("commonTransactionID: ". $getNativeAndCommonTransactionRecordIDsResult['commonTransactionRecordID']);
			
					$commonTransactionID										= $getNativeAndCommonTransactionRecordIDsResult['commonTransactionRecordID'];
			
					if (empty($commonTransactionID))
					{
						$unspentTransactionTotal								= 0;
						$unfundedSpendTotal										= 0;
						
						if ($isDebit == 0)
						{
							$unspentTransactionTotal  							= $amount;  // shouldn't this be the amount minus the fee amount
						}
						else if ($isDebit == 1)
						{
							$unfundedSpendTotal									= $amount; 	// shouldn't this be the amount minus the fee amount
						}	
						
						$sourceWallet											= new CompleteCryptoWallet();
						$destinationWallet										= new CompleteCryptoWallet();
				
						$sourceWalletResponseObject								= $sourceWallet -> instantiateWalletUsingCryptoWalletRecordID($accountID, $sourceWalletID, $userEncryptionKey, $dbh);
				
						if ($sourceWalletResponseObject['instantiatedRecord'] == false)
						{
							errorLog("Could not instantiate source Complete Crypto Wallet record $accountID");
						}
						
						$destinationWalletResponseObject						= $destinationWallet -> instantiateWalletUsingCryptoWalletRecordID($accountID, $destinationWalletID, $userEncryptionKey, $dbh);
				
						if ($destinationWalletResponseObject['instantiatedRecord'] == false)
						{
							errorLog("Could not instantiate destination Complete Crypto Wallet record $accountID, $destinationWalletID");
						}
						
						errorLog($vendorTransactionID."<BR>");
					
						$cryptoTransaction										= new CryptoTransaction();
					
						$cryptoTransaction -> setData(0, $accountID, $authorID, $exchangeTileID, $globalTransactionIdentificationRecordID, $transactionTypeID, $transactionTypeLabel, $transactionStatusID, $transactionStatusLabel, $transactionSourceID, $transactionSourceLabel, $baseCurrencyID, $baseCurrencyName, $quoteSpotPriceCurrencyID, $quoteSpotPriceCurrencyName, $sourceWalletID, $destinationWalletID, $creationDate, $transactionDate, $transactionTimestamp, $transactionID, $vendorTransactionID, $amount, $transactionAmountInUSD, $baseToUSDCurrencySpotPrice, $btcSpotPriceAtTimeOfTransaction, $transactionAmountMinusFeeInUSD, $fee, $feeAmountInUSD, $unspentTransactionTotal, $providerNotes, $isDebit, $sid);
					
						$writeToDatabaseResponse								= $cryptoTransaction -> writeToDatabase($userEncryptionKey, $dbh);
						
						if ($writeToDatabaseResponse['wroteToDatabase'] == true)
						{
							$transactionID										= $cryptoTransaction -> getTransactionID();
							
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
                CexioTradeTransactions.cexioTransactionRecordID AS transactionID,
                CexioTradeTransactions.FK_ExchangeTileID,
                CexioTradeTransactions.FK_GlobalTransactionRecordID AS FK_GlobalTransactionIdentificationRecordID,
                CexioTradeTransactions.FK_AccountID AS authorID,
                CexioTradeTransactions.FK_AccountID AS accountID,
                CexioTradeTransactions.FK_TransactionTypeID AS transactionTypeID,
                CexioTradeTransactions.FK_BaseCurrencyTypeID AS baseCurrencyID,		
                baseCurrencyAsset.assetTypeLabel AS baseCurrencyName,
                2 AS quoteSpotPriceCurrencyID,
                'USD' AS quoteSpotPriceCurrencyName,
                CexioTradeTransactions.creationDate,
                CexioTradeTransactions.transactionTime AS transactionDate,
                CexioTradeTransactions.transactionTimestamp,
                AES_DECRYPT(CexioTradeTransactions.encryptedTxIDValue, UNHEX(SHA2(:userEncryptionKey,512))) AS vendorTransactionID,
                ABS(CexioTradeTransactions.volBaseCurrency) AS btcQuantityTransacted,
                ABS(CexioTradeTransactions.usdAmount) AS usdQuantityTransacted,
                CexioTradeTransactions.spotPriceAtTimeOfTransaction AS spotPriceAtTimeOfTransaction,
                CexioTradeTransactions.spotPriceAtTimeOfTransaction  AS btcPriceAtTimeOfTransaction,        
                ABS(CexioTradeTransactions.volBaseCurrency*CexioTradeTransactions.spotPriceAtTimeOfTransaction) AS usdTransactionAmountWithFees,
                CexioTradeTransactions.feeAmountInUSD AS TransactionFeeAmount,
                ABS(CexioTradeTransactions.feeAmountInUSD) AS usdFeeAmount,
                '' AS  providerNotes,
                CexioTradeTransactions.isDebit,
                CexioTradeTransactions.FK_BaseCurrencyWalletID AS FK_SourceAddressID,
                CexioTradeTransactions.FK_QuoteCurrencyTypeID AS FK_DestinationAddressID,
                TransactionTypes.displayTransactionTypeLabel,
                TransactionTypes.transactionTypeLabel
            FROM
                CexioTradeTransactions
                INNER JOIN TransactionTypes ON CexioTradeTransactions.FK_TransactionTypeID = TransactionTypes.transactionTypeID AND TransactionTypes.languageCode = 'EN'
                INNER JOIN AssetTypes baseCurrencyAsset ON CexioTradeTransactions.FK_BaseCurrencyTypeID = baseCurrencyAsset.assetTypeID AND baseCurrencyAsset.languageCode = 'EN'
            WHERE
                CexioTradeTransactions.FK_AccountID = :accountID
            ORDER BY
                CexioTradeTransactions.transactionTimestamp");	

			}
			
			$responseObject['importedTransactions']								= true;
		}
		catch (PDOException $e) 
		{
			$cryptoTransaction 													= null;	
			$responseObject['importedTransactions']								= false;
			
			errorLog($e -> getMessage());
		
			die();
		}
		
		return $responseObject;
	}	
	// End Cexio 
	
	// CoinBasePro
	
	function getCoinBaseProTransactionHistoryForUserViaAPI($symbol, $tradesArray, $exchangeTileID, $startPosition, $baseCurrency, $baseCurrencyAssetTypeID, $baseCurrencyAssetType, $quoteCurrency, $quoteCurrencyAssetTypeID, $quoteCurrencyAssetType, $baseCurrencyWalletID, $quoteCurrencyWalletID, $cryptoCurrencyTypesImported, $name, $accountID, $userEncryptionKey, $transactionSourceID, $transactionSourceName, $walletTypeID, $walletTypeName, $includeDetailReporting, $dataImportEventRecordID, $globalCurrentDate, $sid, $dbh)
	{
    	$returnValue   														= array();
  	
    	$numberProcessed													= 0;
    				
	    try
	    {
		    errorLog("count ".count($tradesArray));
			$transactionTimeStampIndex										= 0;    
		    
		    foreach ($tradesArray as $trade)
		    {
				    
				$transactionPairName											= $symbol;				
				$transactionID													= $trade -> trade_id;
				$transactionOrderID												= $trade -> order_id;
				$transactionPriceInQuoteCurrency								= $trade -> price;
			    $transactionQtyInBaseCurrency									= $trade -> size;
			    $costPriceInQuoteCurrency										= $trade -> price * $trade -> size;
			    $transactionFeeinQuoteCurrency									= $trade -> fee;  
			    $transactionType												= strtoupper($trade -> side);		    			    
			    $isDebit														= 1;
				$originalTransactionTypeID										= 1;

				if($transactionType == "SELL") 
				{
					$originalTransactionTypeID									= 4;
				}
				elseif($transactionType == "BUY")
				{
					$originalTransactionTypeID									= 1;
					$isDebit													= 0;
				}

				$transactionTime										    	= str_replace("T"," ", $trade -> created_at);
				$transactionTime										    	= str_replace("Z","", $transactionTime);
				$transactionTimestamp 											= strtotime($transactionTime);					
								
				$creationDate													= $globalCurrentDate; 		
				$transactionFeeCurrencyTypeID									= $quoteCurrencyAssetTypeID;
				
				$baseCurrencySpotPriceAtTransactionTime							= 0;	// spotPriceAtTimeOfTransaction
				$quoteCurrencySpotPriceAtTransactionTime						= 0;	// quoteCurrencySpotPriceAtTimeOfTransaction					
				$btcSpotPriceAtTransactionTime									= 0;	// btcPriceAtTimeOfTransaction
				$feeCurrencySpotPriceAtTransactionTime							= 0;
				
				$cascadeRetrieveSpotPriceResponseObject							= getSpotPriceForAssetPairUsingSourceCascade($baseCurrencyAssetTypeID, 2, $transactionTime, 14, "CoinGecko price by date", $dbh);
					
				if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
				{
					$baseCurrencySpotPriceAtTransactionTime						= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
				}

				$cascadeRetrieveSpotPriceResponseObject							= getSpotPriceForAssetPairUsingSourceCascade($quoteCurrencyAssetTypeID, 2, $transactionTime, 14, "CoinGecko price by date", $dbh);
				if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
				{
					$quoteCurrencySpotPriceAtTransactionTime					= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
				}				
				
				if ($baseCurrencyAssetTypeID == 1)
				{
					$btcSpotPriceAtTransactionTime								= $baseCurrencySpotPriceAtTransactionTime;	
				}
				else
				{
					$cascadeRetrieveSpotPriceResponseObject						= getSpotPriceForAssetPairUsingSourceCascade(1, 2, $transactionTime, 14, "CoinGecko price by date", $dbh);
					
					if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
					{
						$btcSpotPriceAtTransactionTime							= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
					}	
				}
				
				// get spot price for fee currency in USD
				
				$cascadeRetrieveSpotPriceResponseObject							= getSpotPriceForAssetPairUsingSourceCascade($transactionFeeCurrencyTypeID, 2, $transactionTime, 14, "CoinGecko price by date", $dbh);
					
				if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
				{
					$feeCurrencySpotPriceAtTransactionTime						= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
				}
				
				$feeAmountInUSD											 		= $feeCurrencySpotPriceAtTransactionTime * $transactionFeeinQuoteCurrency; // fee amount in USD
				
				$transactionAmountInUSD											= $baseCurrencySpotPriceAtTransactionTime * $transactionQtyInBaseCurrency;	// usdAmount
				$transactionAmountMinusFeeInUSD									= $transactionAmountInUSD - $feeAmountInUSD;
				$sentQuantity													= $transactionAmountInUSD;
				$amount															= $transactionAmountInUSD;

				
				error_log("getCoinBaseProTransactionHistoryForUserViaAPI $transactionTime $transactionPairName $transactionPriceInQuoteCurrency $transactionQtyInBaseCurrency $costPriceInQuoteCurrency $transactionFeeinQuoteCurrency $quoteCurrencyAssetType");
				
			    // @Task - write code to convert currencies between USD and BTC and the current type - use amount objects
			    // CoinBasePro - every time I read a spot price, write it to the daily spot price table
			    setDailyPriceData($baseCurrencyAssetTypeID, $quoteCurrencyAssetTypeID, $transactionTimestamp, $transactionPriceInQuoteCurrency, $transactionSourceID, $globalCurrentDate, $sid, $dbh);
			    
			    // @Task - it may be better to compact the array rather than have index gaps - write a function that checks to see if a value already exists in array, and if not, adds it
			    
			    if (isset($cryptoCurrencyTypesImported[$baseCurrencyAssetTypeID][$quoteCurrencyAssetTypeID]))
				{
					$currentCount												= $cryptoCurrencyTypesImported[$baseCurrencyAssetTypeID][$quoteCurrencyAssetTypeID];
					$currentCount++;
					
					$cryptoCurrencyTypesImported[$baseCurrencyAssetTypeID][$quoteCurrencyAssetTypeID]			= $currentCount;
				}
				else
				{
					$cryptoCurrencyTypesImported[$baseCurrencyAssetTypeID][$quoteCurrencyAssetTypeID]			= 1;	
				}
				
				$transactionAssetTypeID											= $quoteCurrencyAssetTypeID;
				$transactionTypeID												= getEnumValueTransactionType($transactionType, $dbh);
			
				// check for global transaction ID
				
				error_log("getGlobalTransactionIdentificationRecordID for $accountID, $baseCurrencyAssetTypeID, $transactionSourceID, $globalCurrentDate, $sid");
				
			    $globalTransactionIDTestResults									= getGlobalTransactionIdentificationRecordID($accountID, $baseCurrencyAssetTypeID, $transactionID, $transactionSourceID, $globalCurrentDate, $sid, $dbh);
			    
			    // here
			    
			    if ($globalTransactionIDTestResults['foundNativeTransactionForAccount'] == false)
				{
					error_log("not found $transactionID");
					
					$returnValue[$transactionID]["existingRecordFound"]			= false;
					
					// create one if not found
					$globalTransactionCreationResults							= createGlobalTransactionIdentificationRecord($accountID, $exchangeTileID, $baseCurrencyAssetTypeID, $transactionID, $transactionSourceID, $globalCurrentDate, $sid, $dbh);
			
					if ($globalTransactionCreationResults['createdGlobalTransactionIdentificationRecord'] == true)
					{
						errorLog("createGlobalTransactionIdentificationRecord success");
						
						$returnValue[$transactionID]["createdGTIR"]				= true;
							
						$globalTransactionIdentifierRecordID					= $globalTransactionCreationResults['globalTransactionIdentificationRecordID'];
						$profitStanceTransactionIDValue							= $globalTransactionCreationResults['profitStanceTransactionIDValue'];
							
						// @Task - this is where I need to use the new provider account wallet idea of 
							
						$providerAccountWallet									= new ProviderAccountWallet();
							
						// check for provider account wallet							
						$instantiationResult									= $providerAccountWallet -> instantiateAccountWalletObjectForAccountByAssetTypeIDAndTransactionSourceID($accountID, $baseCurrencyAssetTypeID, $transactionSourceID, $dbh);
							
						if ($instantiationResult['instantiatedWallet'] == false)
						{
							// create wallet if not found
							$providerAccountWallet -> createAccountWalletObject($accountID, $baseCurrencyAssetTypeID, $baseCurrencyAssetType, $accountID, "$accountID-$transactionSourceID-$baseCurrencyAssetType", $walletTypeID, "Private Ledger Based Wallet", $transactionSourceID, $transactionSourceName, $globalCurrentDate, $sid, $dbh);
						}
							
						$providerWalletID										= $providerAccountWallet -> getAccountWalletID();
						
						errorLog("providerWalletID $providerWalletID");
							
						if ($providerWalletID > 0)
						{
							// Write order to database
							$coinBaseProOrder									= new CoinBaseProOrder();						
							$coinBaseProOrder -> setData($accountID, $transactionOrderID);
			
							// write data to DB
							$response 											= $coinBaseProOrder -> writeToDatabase($userEncryptionKey, $dbh);
			
							$fk_CoinBaseProOrderID								= 0;
			
							if ($response['wroteToDatabase'] == true)
							{
								$fk_CoinBaseProOrderID 							= $coinBaseProOrder -> getCoinBaseProOrderRecordID(); 										error_log("\n Order: $fk_CoinBaseProOrderID inserted successfully into CoinBaseProOrders table!\n");					
							}
							
							// create CoinBasePro trade transaction object and write it
							
							$coinBaseProTradeTransaction 						= new CoinBaseProTradeTransaction();
													
							$coinBaseProTradeTransaction -> setData($fk_CoinBaseProOrderID, $accountID, $exchangeTileID, 0, $globalTransactionIdentifierRecordID, $transactionSourceID, $transactionID, $transactionOrderID, $transactionPairName, $transactionTime, $transactionPriceInQuoteCurrency, $transactionQtyInBaseCurrency, $costPriceInQuoteCurrency, $transactionFeeinQuoteCurrency, $quoteCurrency, $walletTypeID, $walletTypeName, $originalTransactionTypeID, $providerWalletID,    $baseCurrencyWalletID, $quoteCurrencyWalletID, $baseCurrencyAssetTypeID, $quoteCurrencyAssetTypeID, $transactionTimestamp, $creationDate, $quoteCurrencySpotPriceAtTransactionTime, $baseCurrencySpotPriceAtTransactionTime, $btcSpotPriceAtTransactionTime, $transactionAmountInUSD, $feeAmountInUSD, $isDebit, $userEncryptionKey, $globalCurrentDate, $sid, $dbh);
							
							$writeCoinBaseProRecordResponseObject				= $coinBaseProTradeTransaction -> writeToDatabase($userEncryptionKey, $dbh);

							if ($writeCoinBaseProRecordResponseObject['wroteToDatabase'] == true)
							{
								error_log("Transaction inserted into CoinBaseProTradeTransactions table");
								// now that the transaction has been created, create the association record for the closing array if size > 0
								
								$profitStanceLedgerEntry						= new ProfitStanceLedgerEntry();
								$profitStanceLedgerEntry -> setData($accountID, $baseCurrencyAssetTypeID, $baseCurrencyAssetType, 4, "CoinBasePro", $exchangeTileID, $globalTransactionIdentifierRecordID, $transactionTime, $transactionQtyInBaseCurrency, $dbh);
								
								$writeProfitStanceLedgerEntryRecordResponseObject		= $profitStanceLedgerEntry -> writeToDatabase($dbh);
								
								if ($writeProfitStanceLedgerEntryRecordResponseObject['wroteToDatabase'] == true)
								{
									error_log("wrote profitStance ledger entry $accountID, $baseCurrencyAssetTypeID, $baseCurrencyAssetType, 4, \"CoinBasePro\", $exchangeTileID, $globalTransactionIdentifierRecordID, $transactionTime, $transactionQtyInBaseCurrency to the database.");
								}
								else
								{
									error_log("could not write profitStance ledger entry $accountID, $baseCurrencyAssetTypeID, $baseCurrencyAssetType, 4, \"CoinBasePro\", $exchangeTileID, $globalTransactionIdentifierRecordID, $transactionTime, $transactionQtyInBaseCurrency to the database.");	
								}
								
								// update native record ID in GTRID table
								$setNativeTransactionRecordIDResult			= setNativeTransactionRecordIDForGlobalTransactionIndentificationRecordID($accountID, $coinBaseProTradeTransaction -> getPK_RecordID(), $globalTransactionIdentifierRecordID, $globalCurrentDate, $sid, $dbh);
								
								// CoinBasePro Ledger
								errorLog("Writing ledger data");
								
								// --------------------Ledger variables
								$originalTransactionTypeID						= $transactionTypeID;
								$coinbaseProExchangeRate						= $transactionPriceInQuoteCurrency;
																							
								if($originalTransactionTypeID == 1)	// Buy transaction	 
								{
									// Credit Side
									errorlog("Writing Credit side of BUY transaction");
									$transactionTypeID							= 1;
									$isDebit								 	= 0;
									$fk_LedgerCurrencyAssetID					= $baseCurrencyAssetTypeID;
									$fk_OriginalQuoteCurrency					= $quoteCurrencyAssetTypeID;
									$coinbaseProExchangeRate					= $transactionPriceInQuoteCurrency;
																	
									$coinBaseProTradeTransactionAsLedger 		= new CoinBaseProTradeTransactionAsLedger();													
									
									$coinBaseProTradeTransactionAsLedger -> setData($fk_CoinBaseProOrderID, $accountID, $exchangeTileID, 0, $globalTransactionIdentifierRecordID, $transactionSourceID, $transactionID, $transactionOrderID, $transactionPairName, $transactionTime, $baseCurrencySpotPriceAtTransactionTime, $transactionQtyInBaseCurrency, $transactionQtyInBaseCurrency, $transactionFeeinQuoteCurrency, $walletTypeID, $walletTypeName, 1,  $providerWalletID, $baseCurrencyWalletID, $quoteCurrencyWalletID, $baseCurrencyAssetTypeID, $quoteCurrencyAssetTypeID, $baseCurrencyAssetTypeID, 2, $transactionTimestamp, $creationDate, $baseCurrencySpotPriceAtTransactionTime, 1, $btcSpotPriceAtTransactionTime, $transactionAmountInUSD, $feeAmountInUSD, $isDebit, $coinbaseProExchangeRate, 1, $userEncryptionKey, $globalCurrentDate, $sid, $dbh);	
									
									$writeCoinBaseProLedgerRecordResponseObject	= $coinBaseProTradeTransactionAsLedger -> writeToDatabase($userEncryptionKey, $dbh);																	
									if ($writeCoinBaseProLedgerRecordResponseObject['wroteToDatabase'] == true)
									{
										errorLog("Succeded: Credit side of BUY transaction insertion into CoinBaseProTradeTransactionAsLedger for TransactionID: $transactionID");
									}										
									// Debit Side
									errorlog("Writing Debit side of BUY transaction");									
									$isDebit									= 1;
									$transactionTypeID							= 4;
									$fk_LedgerCurrencyAssetID					= $quoteCurrencyAssetTypeID;
									$fk_OriginalQuoteCurrency					= $baseCurrencyAssetTypeID;
									$coinbaseProExchangeRate					= 1/$transactionPriceInQuoteCurrency;
									
									$globalTransactionIdentifierRecordID		= createGlobalTransactionRecord($accountID, $exchangeTileID, $quoteCurrencyAssetTypeID, $transactionID, $transactionSourceID, $globalCurrentDate, $sid, $dbh);
									
									if($globalTransactionIdentifierRecordID != 0)
									{
										
									$coinBaseProTradeTransactionAsLedger 	= new CoinBaseProTradeTransactionAsLedger();							
													
									$coinBaseProTradeTransactionAsLedger -> setData($fk_CoinBaseProOrderID, $accountID, $exchangeTileID, 0, $globalTransactionIdentifierRecordID, $transactionSourceID, $transactionID, $transactionOrderID, $transactionPairName, $transactionTime, $quoteCurrencySpotPriceAtTransactionTime, $costPriceInQuoteCurrency, $costPriceInQuoteCurrency, $transactionFeeinQuoteCurrency, $walletTypeID, $walletTypeName, 1,  $providerWalletID, $baseCurrencyWalletID, $quoteCurrencyWalletID, $baseCurrencyAssetTypeID, $quoteCurrencyAssetTypeID,$quoteCurrencyAssetTypeID,2, $transactionTimestamp, $creationDate, $quoteCurrencySpotPriceAtTransactionTime, 1, $btcSpotPriceAtTransactionTime, $transactionAmountInUSD, $feeAmountInUSD, $isDebit, $coinbaseProExchangeRate, 4, $userEncryptionKey, $globalCurrentDate, $sid, $dbh);	
										
										$writeCoinBaseProLedgerRecordResponseObject	= $coinBaseProTradeTransactionAsLedger -> writeToDatabase($userEncryptionKey, $dbh);																	
										if ($writeCoinBaseProLedgerRecordResponseObject['wroteToDatabase'] == true)
										{
											errorLog("Succeeded: Debit side of BUY transaction insertion into CoinBaseProTradeTransactionAsLedger for TransactionID: $transactionID");						}
									}									
									else
									{
										errorLog("Failed: Debit side of BUY transaction insertion into CoinBaseProTradeTransactionAsLedger for TransactionID: $transactionID");										
									}	
								} 
								elseif($originalTransactionTypeID == 4) // sell transaction
								{
									errorlog("Writing Debit side of SELL transaction");									
									// Debit Side
									$transactionTypeID							= 4;
									$isDebit								 	= 1;
									$fk_LedgerCurrencyAssetID					= $baseCurrencyAssetTypeID;
									$fk_OriginalQuoteCurrency					= $quoteCurrencyAssetTypeID;
									$coinbaseProExchangeRate					= $transactionPriceInQuoteCurrency;

									$coinBaseProTradeTransactionAsLedger 		= new CoinBaseProTradeTransactionAsLedger();								

									$coinBaseProTradeTransactionAsLedger -> setData($fk_CoinBaseProOrderID, $accountID, $exchangeTileID, 0, $globalTransactionIdentifierRecordID, $transactionSourceID, $transactionID, $transactionOrderID, $transactionPairName, $transactionTime, $baseCurrencySpotPriceAtTransactionTime, $transactionQtyInBaseCurrency, $transactionQtyInBaseCurrency, $transactionFeeinQuoteCurrency, $walletTypeID, $walletTypeName, 4,  $providerWalletID, $baseCurrencyWalletID, $quoteCurrencyWalletID, $baseCurrencyAssetTypeID, $quoteCurrencyAssetTypeID,$baseCurrencyAssetTypeID, 2, $transactionTimestamp, $creationDate, $baseCurrencySpotPriceAtTransactionTime, 1, $btcSpotPriceAtTransactionTime, $transactionAmountInUSD, $feeAmountInUSD, $isDebit, $coinbaseProExchangeRate, 4, $userEncryptionKey, $globalCurrentDate, $sid, $dbh);
									
									$writeCoinBaseProLedgerRecordResponseObject	= $coinBaseProTradeTransactionAsLedger -> writeToDatabase($userEncryptionKey, $dbh);																	
									if ($writeCoinBaseProLedgerRecordResponseObject['wroteToDatabase'] == true)
									{
										errorLog("Succeded: Debit side of SELL transaction insertion into CoinBaseProTradeTransactionAsLedger for TransactionID: $transactionID");
									}										
									// Credit Side
									errorlog("Writing Credit side of SELL transaction");													
									$transactionTypeID							= 1;
									$isDebit									= 0;
									$fk_LedgerCurrencyAssetID					= $quoteCurrencyAssetTypeID;
									$fk_OriginalQuoteCurrency					= $baseCurrencyAssetTypeID;
									$coinbaseProExchangeRate					= 1 / $transactionPriceInQuoteCurrency;
																		
									$globalTransactionIdentifierRecordID		= createGlobalTransactionRecord($accountID, $exchangeTileID, $quoteCurrencyAssetTypeID, $transactionID, $transactionSourceID, $globalCurrentDate, $sid, $dbh);
									
									if($globalTransactionIdentifierRecordID != 0)
									{
								
										$coinBaseProTradeTransactionAsLedger 	= new CoinBaseProTradeTransactionAsLedger();								

									
										$coinBaseProTradeTransactionAsLedger -> setData($fk_CoinBaseProOrderID, $accountID, $exchangeTileID, 0, $globalTransactionIdentifierRecordID, $transactionSourceID, $transactionID, $transactionOrderID, $transactionPairName, $transactionTime, $quoteCurrencySpotPriceAtTransactionTime, $costPriceInQuoteCurrency, $costPriceInQuoteCurrency, $transactionFeeinQuoteCurrency, $walletTypeID, $walletTypeName, 4,  $providerWalletID, $baseCurrencyWalletID, $quoteCurrencyWalletID, $baseCurrencyAssetTypeID, $quoteCurrencyAssetTypeID,$quoteCurrencyAssetTypeID,2, $transactionTimestamp, $creationDate, $quoteCurrencySpotPriceAtTransactionTime, 1, $btcSpotPriceAtTransactionTime, $transactionAmountInUSD, $feeAmountInUSD, $isDebit, $coinbaseProExchangeRate, 1, $userEncryptionKey, $globalCurrentDate, $sid, $dbh);																			
									
										$writeCoinBaseProLedgerRecordResponseObject	= $coinBaseProTradeTransactionAsLedger -> writeToDatabase($userEncryptionKey, $dbh);																	
										if ($writeCoinBaseProLedgerRecordResponseObject['wroteToDatabase'] == true)
										{
											errorLog("Succeeded: Credit side of SELL transaction insertion into CoinBaseProTradeTransactionAsLedger for TransactionID: $transactionID");							}
									}									
									else
									{
										errorLog("Failed: Credit side of SELL transaction insertion into CoinBaseProTradeTransactionAsLedger for TransactionID: $transactionID");										
									}	
								}

								//---------------------End Ledger variables								
								
															
							}
						}
					}
					
					$numberProcessed++;	
				}
				else
				{
					error_log("found $transactionID");
					
					$returnValue[$transactionID]["existingRecordFound"]			= true;
					$returnValue[$transactionID]["newTransactionCreated"]		= false;
				}

				errorLog("completed array index $transactionID");
		    }
		
			if ($includeDetailReporting != true)
			{
				$returnValue													= array();		
			}
			
			$returnValue["coinBaseProDataImported"]								= "complete";
			$returnValue['numberProcessed']										= $numberProcessed;
			$returnValue['cryptoCurrencyTypesImported']							= $cryptoCurrencyTypesImported;
			$returnValue['transactionTimeStampIndex']							= $transactionTimeStampIndex;
			
	    }
	    catch (Exception $e)
	    {
		   	errorLog("ERROR: array parsing error");	
	    }
	    
	    errorLog(json_encode($returnValue));

        return $returnValue;
    }	
    
 	function createCommonTransactionsForCoinBaseProTradeTransactions($liuAccountID, $userEncryptionKey, $globalCurrentDate, $sid, $dbh)
	{
		errorLog("createCommonTransactionsForCoinBaseProTradeTransactions($liuAccountID, $userEncryptionKey, $globalCurrentDate, $sid");
		
		$responseObject														= array();

		$responseObject['importedTransactions']								= false;
		
		$transactionSourceID												= 19;
		$transactionStatusID												= 1;
		$transactionStatusLabel												= "complete";
		$transactionSourceLabel												= "CoinBasePro";
		
		try
		{		
			$getCoinBaseProTransactionRecords										= $dbh -> prepare("SELECT
	CoinBaseProTradeTransactions.PK_RecordID AS transactionID,
	CoinBaseProTradeTransactions.FK_ExchangeTileID,
	CoinBaseProTradeTransactions.FK_GlobalTransactionRecordID AS FK_GlobalTransactionIdentificationRecordID,
	CoinBaseProTradeTransactions.FK_AccountID AS authorID,
	CoinBaseProTradeTransactions.FK_AccountID AS accountID,
	CoinBaseProTradeTransactions.FK_TransactionTypeID AS transactionTypeID,
	CoinBaseProTradeTransactions.FK_BaseCurrencyTypeID AS baseCurrencyID,		
	baseCurrencyAsset.assetTypeLabel AS baseCurrencyName,
	2 AS quoteSpotPriceCurrencyID,
	'USD' AS quoteSpotPriceCurrencyName,
	CoinBaseProTradeTransactions.creationDate,
	CoinBaseProTradeTransactions.transactionTime AS transactionDate,
	CoinBaseProTradeTransactions.transactionTimestamp,
	AES_DECRYPT(CoinBaseProTradeTransactions.encryptedTransactionIDValue, UNHEX(SHA2(:userEncryptionKey,512))) AS vendorTransactionID,
	ABS(CoinBaseProTradeTransactions.volBaseCurrency) AS btcQuantityTransacted,
	ABS(CoinBaseProTradeTransactions.transactionAmountInUSD) AS usdQuantityTransacted,
	CoinBaseProTradeTransactions.baseCurrencySpotPriceAtTimeOfTransaction AS spotPriceAtTimeOfTransaction,
	CoinBaseProTradeTransactions.btcSpotPriceAtTimeOfTransaction AS btcPriceAtTimeOfTransaction,
	ABS(CoinBaseProTradeTransactions.transactionAmountInUSD) + ABS(CoinBaseProTradeTransactions.feeAmountInUSD) AS usdTransactionAmountWithFees,
	CoinBaseProTradeTransactions.feeAmountInUSD AS networkTransactionFeeAmount,
	ABS(CoinBaseProTradeTransactions.feeAmountInUSD) AS usdFeeAmount,
	ABS(CoinBaseProTradeTransactions.transactionAmountInUSD) - ABS(CoinBaseProTradeTransactions.feeAmountInUSD) AS transactionAmountMinusFeeInUSD,
	ABS(CoinBaseProTradeTransactions.transactionAmountInUSD) + ABS(CoinBaseProTradeTransactions.feeAmountInUSD) AS transactionAmountPlusFeeInUSD,
	'' AS  providerNotes,
	CoinBaseProTradeTransactions.isDebit,
	CoinBaseProTradeTransactions.FK_BaseCurrencyWalletID AS FK_SourceAddressID,
	CoinBaseProTradeTransactions.FK_QuoteCurrencyTypeID AS FK_DestinationAddressID,
	TransactionTypes.displayTransactionTypeLabel,
	TransactionTypes.transactionTypeLabel
FROM
	CoinBaseProTradeTransactions
	INNER JOIN TransactionTypes ON CoinBaseProTradeTransactions.FK_TransactionTypeID = TransactionTypes.transactionTypeID AND TransactionTypes.languageCode = 'EN'
	INNER JOIN AssetTypes baseCurrencyAsset ON CoinBaseProTradeTransactions.FK_BaseCurrencyTypeID = baseCurrencyAsset.assetTypeID AND baseCurrencyAsset.languageCode = 'EN'
WHERE
	CoinBaseProTradeTransactions.FK_AccountID = :accountID
ORDER BY
	CoinBaseProTradeTransactions.transactionTimestamp");
	
			$getCoinBaseProTransactionRecords -> bindValue(':accountID', $liuAccountID);
			$getCoinBaseProTransactionRecords -> bindValue(':userEncryptionKey', $userEncryptionKey);
		
			if ($getCoinBaseProTransactionRecords -> execute() && $getCoinBaseProTransactionRecords -> rowCount() > 0)
			{

				errorLog("began get CoinBasePro crypto transaction records ".$getCoinBaseProTransactionRecords -> rowCount() > 0);
				
				while ($row = $getCoinBaseProTransactionRecords -> fetchObject())
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
	CoinBaseProTradeTransactions.PK_RecordID AS transactionID,
	CoinBaseProTradeTransactions.FK_ExchangeTileID,
	CoinBaseProTradeTransactions.FK_GlobalTransactionRecordID AS FK_GlobalTransactionIdentificationRecordID,
	CoinBaseProTradeTransactions.FK_AccountID AS authorID,
	CoinBaseProTradeTransactions.FK_AccountID AS accountID,
	CoinBaseProTradeTransactions.FK_TransactionTypeID AS transactionTypeID,
	CoinBaseProTradeTransactions.FK_BaseCurrencyTypeID AS baseCurrencyID,		
	baseCurrencyAsset.assetTypeLabel AS baseCurrencyName,
	2 AS quoteSpotPriceCurrencyID,
	'USD' AS quoteSpotPriceCurrencyName,
	CoinBaseProTradeTransactions.creationDate,
	CoinBaseProTradeTransactions.transactionTime AS transactionDate,
	CoinBaseProTradeTransactions.transactionTimestamp,
	AES_DECRYPT(CoinBaseProTradeTransactions.encryptedTransactionIDValue, UNHEX(SHA2(:userEncryptionKey,512))) AS vendorTransactionID,
	ABS(CoinBaseProTradeTransactions.volBaseCurrency) AS btcQuantityTransacted,
	ABS(CoinBaseProTradeTransactions.transactionAmountInUSD) AS usdQuantityTransacted,
	CoinBaseProTradeTransactions.baseCurrencySpotPriceAtTimeOfTransaction AS spotPriceAtTimeOfTransaction,
	CoinBaseProTradeTransactions.btcSpotPriceAtTimeOfTransaction AS btcPriceAtTimeOfTransaction,
	ABS(CoinBaseProTradeTransactions.transactionAmountInUSD) + ABS(CoinBaseProTradeTransactions.feeAmountInUSD) AS usdTransactionAmountWithFees,
	CoinBaseProTradeTransactions.feeAmountInUSD AS networkTransactionFeeAmount,
	ABS(CoinBaseProTradeTransactions.feeAmountInUSD) AS usdFeeAmount,
	ABS(CoinBaseProTradeTransactions.transactionAmountInUSD) - ABS(CoinBaseProTradeTransactions.feeAmountInUSD) AS transactionAmountMinusFeeInUSD,
	ABS(CoinBaseProTradeTransactions.transactionAmountInUSD) + ABS(CoinBaseProTradeTransactions.feeAmountInUSD) AS transactionAmountPlusFeeInUSD,
	'' AS  providerNotes,
	CoinBaseProTradeTransactions.isDebit,
	CoinBaseProTradeTransactions.FK_BaseCurrencyWalletID AS FK_SourceAddressID,
	CoinBaseProTradeTransactions.FK_QuoteCurrencyTypeID AS FK_DestinationAddressID,
	TransactionTypes.displayTransactionTypeLabel,
	TransactionTypes.transactionTypeLabel
FROM
	CoinBaseProTradeTransactions
	INNER JOIN TransactionTypes ON CoinBaseProTradeTransactions.FK_TransactionTypeID = TransactionTypes.transactionTypeID AND TransactionTypes.languageCode = 'EN'
	INNER JOIN AssetTypes baseCurrencyAsset ON CoinBaseProTradeTransactions.FK_BaseCurrencyTypeID = baseCurrencyAsset.assetTypeID AND baseCurrencyAsset.languageCode = 'EN'
WHERE
	CoinBaseProTradeTransactions.FK_AccountID = :accountID
ORDER BY
	CoinBaseProTradeTransactions.transactionTimestamp");	

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
	// End CoinBasePro
	
	// Gemini
	function getGeminiTransactionHistoryForUserViaAPI($symbol, $resultArray, $exchangeTileID, $startPosition, $baseCurrency, $baseCurrencyAssetTypeID, $baseCurrencyAssetType, $quoteCurrency, $quoteCurrencyAssetTypeID, $quoteCurrencyAssetType, $baseCurrencyWalletID, $quoteCurrencyWalletID, $cryptoCurrencyTypesImported, $name, $accountID, $userEncryptionKey, $transactionSourceID, $transactionSourceName, $walletTypeID, $walletTypeName, $includeDetailReporting, $dataImportEventRecordID, $globalCurrentDate, $sid, $dbh)
	{
    	$returnValue   														= array();
  	
		$tradesArray														= $resultArray;
    	$numberProcessed													= 0;
    				
	    try
	    {
		    errorLog("count ".count($tradesArray));
			$transactionTimeStampIndex										= 0;    
		    
		    foreach ($tradesArray as $trade)
		    {
		    
				$transactionPairName										= $symbol;
				$txIDValue													= $trade -> tid;
				$orderTxID													= $trade -> order_id;
				$transactionPrice											= $trade -> price;
			    $transactionQty												= $trade -> amount;
			    $transactionQuoteQty										= $trade -> price * $trade -> amount;
			    $transactionCommission										= $trade -> fee_amount;
			    $transactionCommissionAsset									= $trade -> fee_currency;		    
			    $transactionType											= $trade -> type;
			    $isDebit													= 1;
			    
			    if ($transactionType != "Sell")
			    {
					$transactionType										= "buy";  
					$isDebit												= 0;
			    }

			    $transactionTimestampms										= $trade -> timestampms;	// transaction timestamp millisecs
			    $transactionTimeStampIndex									= $trade -> timestamp;						 
				$transactionTime										    = gmdate("Y-m-d h:i:s", $transactionTimeStampIndex);
				$creationDate												= $globalCurrentDate; 		
				$feeAssetTypeID											 	= getEnumValueAssetType($quoteCurrencyAssetTypeID, $dbh);
				
				$baseToQuoteCurrencySpotPrice								= 0;	// quoteCurrencySpotPriceAtTimeOfTransaction					
				$baseToUSDCurrencySpotPrice									= 0;	// spotPriceAtTimeOfTransaction
				$btcSpotPriceAtTimeOfTransaction							= 0;	// btcPriceAtTimeOfTransaction
				$feeCurrencySpotPriceAtTimeOfTransaction					= 0;
				
				$cascadeRetrieveSpotPriceResponseObject						= getSpotPriceForAssetPairUsingSourceCascade($baseCurrencyAssetTypeID, 2, $transactionTime, 2, "Coinbase price by date", $dbh);
					
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
					$cascadeRetrieveSpotPriceResponseObject					= getSpotPriceForAssetPairUsingSourceCascade(1, 2, $transactionTime, 2, "Coinbase price by date", $dbh);
					
					if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
					{
						$btcSpotPriceAtTimeOfTransaction					= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
					}	
				}
				
				// get spot price for fee currency in USD
				
				$cascadeRetrieveSpotPriceResponseObject						= getSpotPriceForAssetPairUsingSourceCascade($feeAssetTypeID, 2, $transactionTime, 2, "Coinbase price by date", $dbh);
					
				if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
				{
					$feeCurrencySpotPriceAtTimeOfTransaction				= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
				}
				
				$feeAmountInUSD											 	= $feeCurrencySpotPriceAtTimeOfTransaction * $transactionCommission; // fee amount in USD
				
				$transactionAmountInUSD										= $baseToUSDCurrencySpotPrice * $transactionQty;	// usdAmount
				$transactionAmountMinusFeeInUSD								= $transactionAmountInUSD - $feeAmountInUSD;
				$sentQuantity												= $transactionAmountInUSD;
				$amount														= $transactionAmountInUSD;
				
				error_log("getGeminiTransactionHistoryForUserViaAPI $transactionTime $transactionPairName $transactionPrice $transactionQty $transactionQuoteQty $transactionCommission $transactionCommissionAsset");
				
			    // @Task - write code to convert currencies between USD and BTC and the current type - use amount objects
			    // Gemini - every time I read a spot price, write it to the daily spot price table
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
					
					$returnValue[$txIDValue]["existingRecordFound"]			= false;
					
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
							// create gemini trade transaction object and write it
							
							$geminiTradeTransaction 						= new GeminiTradeTransaction();
													
							$geminiTradeTransaction -> setData($accountID, $exchangeTileID, 0, $globalTransactionIdentifierRecordID, $transactionSourceID, $txIDValue, $orderTxID, $transactionPairName, $transactionTime, $transactionPrice, $transactionQty, $transactionQuoteQty, $transactionCommission, $transactionCommissionAsset, $walletTypeID, $walletTypeName, $transactionTypeID, $providerWalletID,    $baseCurrencyWalletID, $quoteCurrencyWalletID, $baseCurrencyAssetTypeID, $quoteCurrencyAssetTypeID, $transactionTimeStampIndex, $transactionTimestampms, $creationDate, $baseToQuoteCurrencySpotPrice, $baseToUSDCurrencySpotPrice, $btcSpotPriceAtTimeOfTransaction, $transactionAmountInUSD, $feeAmountInUSD, $isDebit, $userEncryptionKey, $globalCurrentDate, $sid, $dbh);
							
							$writeGeminiRecordResponseObject				= $geminiTradeTransaction -> writeToDatabase($userEncryptionKey, $dbh);
							
							if ($writeGeminiRecordResponseObject['wroteToDatabase'] == true)
							{
								error_log("Transaction inserted into GeminiTradeTransactions table");
								// now that the transaction has been created, create the association record for the closing array if size > 0
								
								$profitStanceLedgerEntry					= new ProfitStanceLedgerEntry();
								$profitStanceLedgerEntry -> setData($accountID, $baseCurrencyAssetTypeID, $baseCurrencyAssetType, 4, "Gemini", $exchangeTileID, $globalTransactionIdentifierRecordID, $transactionTime, $transactionQty, $dbh);
								
								$writeProfitStanceLedgerEntryRecordResponseObject		= $profitStanceLedgerEntry -> writeToDatabase($dbh);
								
								if ($writeProfitStanceLedgerEntryRecordResponseObject['wroteToDatabase'] == true)
								{
									error_log("wrote profitStance ledger entry $accountID, $baseCurrencyAssetTypeID, $baseCurrencyAssetType, 4, \"Gemini\", $exchangeTileID, $globalTransactionIdentifierRecordID, $transactionTime, $transactionQty to the database.");
								}
								else
								{
									error_log("could not write profitStance ledger entry $accountID, $baseCurrencyAssetTypeID, $baseCurrencyAssetType, 4, \"Gemini\", $exchangeTileID, $globalTransactionIdentifierRecordID, $transactionTime, $transactionQty to the database.");	
								}
								
								// update native record ID in GTRID table
								$setNativeTransactionRecordIDResult			= setNativeTransactionRecordIDForGlobalTransactionIndentificationRecordID($accountID, $geminiTradeTransaction -> getGeminiTransactionRecordID(), $globalTransactionIdentifierRecordID, $globalCurrentDate, $sid, $dbh);
															
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
			
			$returnValue["geminiDataImported"]							    = "complete";
			$returnValue['numberProcessed']									= $numberProcessed;
			$returnValue['cryptoCurrencyTypesImported']						= $cryptoCurrencyTypesImported;
			$returnValue['transactionTimeStampIndex']						= $transactionTimeStampIndex;
			
	    }
	    catch (Exception $e)
	    {
		   	errorLog("ERROR: array parsing error");	
	    }
	    
	    errorLog(json_encode($returnValue));

        return $returnValue;
    }	
	// End Gemini
	
	// Poloniex
	function getPoloniexTransactionHistoryForUserViaAPI($symbol, $resultArray, $exchangeTileID, $startPosition, $baseCurrency, $baseCurrencyAssetTypeID, $baseCurrencyAssetType, $quoteCurrency, $quoteCurrencyAssetTypeID, $quoteCurrencyAssetType, $baseCurrencyWalletID, $quoteCurrencyWalletID, $cryptoCurrencyTypesImported, $name, $accountID, $userEncryptionKey, $transactionSourceID, $transactionSourceName, $walletTypeID, $walletTypeName, $includeDetailReporting, $dataImportEventRecordID, $globalCurrentDate, $sid, $dbh)
	{
    	$returnValue   															= array();
  	
		$tradesArray															= $resultArray;
    	$numberProcessed														= 0;
    				
	    try
	    {
 		    errorLog("count ".count($tradesArray));
			$transactionTimeStampIndex											= 0;    
		    
		    foreach ($tradesArray as $trade)
		    {
		    
				$transactionPairName											= $symbol;
				if (!empty($transactionPairName))
				{
					$currencyArray												= explode("_", $transactionPairName);
					$baseAssetCurrency  										= $currencyArray[0];
					$quoteAssetCurrency 										= $currencyArray[1];						
				}
						
				$txIDValue														= $trade -> tradeID;
				$orderTxID														= $trade -> orderNumber;
				$transactionPrice												= $trade -> rate;
			    $transactionQty													= $trade -> amount;
			    $transactionQuoteQty											= $trade -> total;
			    $transactionCommission											= $trade -> fee;
			    $transactionCommissionAsset										= $quoteAssetCurrency;		    
			    $isDebit														= 0;
			    $globalTradeID													= $trade -> globalTradeID;
			    $category														= $trade -> category;
			    $transactionType												= strtolower($trade -> type);
			    			    			    
			    if ($transactionType == "sell")
			    {
					$isDebit													= 1;
			    }

				$transactionTime										    	= $trade -> date;
				$creationDate													= $globalCurrentDate; 		
				$feeAssetTypeID											 		= getEnumValueAssetType($transactionCommissionAsset, $dbh);	
				
				$baseToQuoteCurrencySpotPrice									= 0;	// quoteCurrencySpotPriceAtTimeOfTransaction					
				$baseToUSDCurrencySpotPrice										= 0;	// spotPriceAtTimeOfTransaction
				$btcSpotPriceAtTimeOfTransaction								= 0;	// btcPriceAtTimeOfTransaction
				$feeCurrencySpotPriceAtTimeOfTransaction						= 0;
				
				$cascadeRetrieveSpotPriceResponseObject							= getSpotPriceForAssetPairUsingSourceCascade($baseCurrencyAssetTypeID, 2, $transactionTime, 2, "Coinbase price by date", $dbh);
					
				if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
				{
					$baseToQuoteCurrencySpotPrice								= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
					$baseToUSDCurrencySpotPrice									= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
				}
				
				if ($baseCurrencyAssetTypeID == 1)
				{
					$btcSpotPriceAtTimeOfTransaction							= $baseToUSDCurrencySpotPrice;	
				}
				else
				{
					$cascadeRetrieveSpotPriceResponseObject						= getSpotPriceForAssetPairUsingSourceCascade(1, 2, $transactionTime, 2, "Coinbase price by date", $dbh);
					
					if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
					{
						$btcSpotPriceAtTimeOfTransaction						= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
					}	
				}
				
				// get spot price for fee currency in USD
				
				$cascadeRetrieveSpotPriceResponseObject							= getSpotPriceForAssetPairUsingSourceCascade($feeAssetTypeID, 2, $transactionTime, 2, "Coinbase price by date", $dbh);
					
				if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
				{
					$feeCurrencySpotPriceAtTimeOfTransaction					= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
				}
				
				$feeAmountInUSD											 		= $feeCurrencySpotPriceAtTimeOfTransaction * $transactionCommission; // fee amount in USD
				
				$transactionAmountInUSD											= $baseToUSDCurrencySpotPrice * $transactionQty;	// usdAmount
				$transactionAmountMinusFeeInUSD									= $transactionAmountInUSD - $feeAmountInUSD;
				$sentQuantity													= $transactionAmountInUSD;
				$amount															= $transactionAmountInUSD;
				
				error_log("getPoloniexTransactionHistoryForUserViaAPI $transactionTime $transactionPairName $transactionPrice $transactionQty $transactionQuoteQty $transactionCommission $transactionCommissionAsset");
				
			    // @Task - write code to convert currencies between USD and BTC and the current type - use amount objects
			    // Poloniex - every time I read a spot price, write it to the daily spot price table
			    setDailyPriceData($baseCurrencyAssetTypeID, $quoteCurrencyAssetTypeID, $transactionTime, $transactionPrice, $transactionSourceID, $globalCurrentDate, $sid, $dbh);
			    
			    // @Task - it may be better to compact the array rather than have index gaps - write a function that checks to see if a value already exists in array, and if not, adds it
			    
			    if (isset($cryptoCurrencyTypesImported[$baseCurrencyAssetTypeID][$quoteCurrencyAssetTypeID]))
				{
					$currentCount												= $cryptoCurrencyTypesImported[$baseCurrencyAssetTypeID][$quoteCurrencyAssetTypeID];
					$currentCount++;
					
					$cryptoCurrencyTypesImported[$baseCurrencyAssetTypeID][$quoteCurrencyAssetTypeID]			= $currentCount;
				}
				else
				{
					$cryptoCurrencyTypesImported[$baseCurrencyAssetTypeID][$quoteCurrencyAssetTypeID]			= 1;	
				}
				
				$transactionAssetTypeID											= getEnumValueAssetType($transactionCommissionAsset, $dbh);
				$transactionTypeID												= getEnumValueTransactionType($transactionType, $dbh);
				
				// check for global transaction ID
				
				error_log("getGlobalTransactionIdentificationRecordID for $accountID, $baseCurrencyAssetTypeID, $transactionSourceID, $globalCurrentDate, $sid");
				
			    $globalTransactionIDTestResults									= getGlobalTransactionIdentificationRecordID($accountID, $baseCurrencyAssetTypeID, $txIDValue, $transactionSourceID, $globalCurrentDate, $sid, $dbh);
			    			    
			    if ($globalTransactionIDTestResults['foundNativeTransactionForAccount'] == false)
				{
					error_log("not found $txIDValue");
					
					$returnValue[$txIDValue]["existingRecordFound"]				= false;
					
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
							// create Poloniex trade transaction object and write it
							
							$poloniexTradeTransaction 						= new PoloniexTradeTransaction();
													
							$poloniexTradeTransaction -> setData($accountID, $exchangeTileID, 0, $globalTransactionIdentifierRecordID, $transactionSourceID, $txIDValue, $orderTxID, $transactionPairName, $transactionTime, $transactionPrice, $transactionQty, $transactionQuoteQty, $transactionCommission, $transactionCommissionAsset, $walletTypeID, $walletTypeName, $transactionTypeID, $providerWalletID, $baseCurrencyWalletID, $quoteCurrencyWalletID, $baseCurrencyAssetTypeID, $quoteCurrencyAssetTypeID, $category, $globalTradeID, $creationDate, $baseToQuoteCurrencySpotPrice, $baseToUSDCurrencySpotPrice, $btcSpotPriceAtTimeOfTransaction, $transactionAmountInUSD, $feeAmountInUSD, $isDebit, $userEncryptionKey, $globalCurrentDate, $sid, $dbh);
							
							$writePoloniexRecordResponseObject				= $poloniexTradeTransaction -> writeToDatabase($userEncryptionKey, $dbh);
							
							if ($writePoloniexRecordResponseObject['wroteToDatabase'] == true)
							{
								error_log("Transaction inserted into PoloniexTradeTransactions table");
								// now that the transaction has been created, create the association record for the closing array if size > 0
								
								$profitStanceLedgerEntry					= new ProfitStanceLedgerEntry();
								$profitStanceLedgerEntry -> setData($accountID, $baseCurrencyAssetTypeID, $baseCurrencyAssetType, 4, "Poloniex", $exchangeTileID, $globalTransactionIdentifierRecordID, $transactionTime, $transactionQty, $dbh);
								
								$writeProfitStanceLedgerEntryRecordResponseObject		= $profitStanceLedgerEntry -> writeToDatabase($dbh);
								
								if ($writeProfitStanceLedgerEntryRecordResponseObject['wroteToDatabase'] == true)
								{
									error_log("wrote profitStance ledger entry $accountID, $baseCurrencyAssetTypeID, $baseCurrencyAssetType, 4, \"Poloniex\", $exchangeTileID, $globalTransactionIdentifierRecordID, $transactionTime, $transactionQty to the database.");
								}
								else
								{
									error_log("could not write profitStance ledger entry $accountID, $baseCurrencyAssetTypeID, $baseCurrencyAssetType, 4, \"Poloniex\", $exchangeTileID, $globalTransactionIdentifierRecordID, $transactionTime, $transactionQty to the database.");	
								}
								
								// update native record ID in GTRID table
								$setNativeTransactionRecordIDResult			= setNativeTransactionRecordIDForGlobalTransactionIndentificationRecordID($accountID, $poloniexTradeTransaction -> getPoloniexTransactionRecordID(), $globalTransactionIdentifierRecordID, $globalCurrentDate, $sid, $dbh);
															
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
			
			$returnValue["PoloniexDataImported"]							= "complete";
			$returnValue['numberProcessed']									= $numberProcessed;
			$returnValue['cryptoCurrencyTypesImported']						= $cryptoCurrencyTypesImported;
			$returnValue['transactionTimeStampIndex']						= $transactionTimeStampIndex;
			
	    }
	    catch (Exception $e)
	    {
		   	errorLog("ERROR: array parsing error");	
	    }
	    
	    errorLog(json_encode($returnValue));

        return $returnValue;
    }

	function getEnumValueCommonPoloniexAssetPair($assetPairName, $dbh)
	{
		$assetPairID														= null;
		
		try
		{		
			$getEnumValue													= $dbh -> prepare("SELECT
	CommonCurrencyPairs.pairID
FROM
	CommonCurrencyPairs
WHERE
	CommonCurrencyPairs.poloniexPairName = :pairName");			
			
			$getEnumValue -> bindValue(':pairName', $assetPairName);
						
			if ($getEnumValue -> execute() && $getEnumValue -> rowCount() > 0)
			{
				$row 														= $getEnumValue -> fetchObject();
				
				$assetPairID												= $row -> pairID;	
			}
		}
	    catch (PDOException $e) 
	    {
	    		errorLog($e -> getMessage());
	
			die();
		}
		
		return $assetPairID;
	}	
	// End Poloniex
	
	// KuCoin
	function getKuCoinTransactionHistoryForUserViaAPI($symbol, $resultArray, $exchangeTileID, $startPosition, $baseCurrency, $baseCurrencyAssetTypeID, $baseCurrencyAssetType, $quoteCurrency, $quoteCurrencyAssetTypeID, $quoteCurrencyAssetType, $baseCurrencyWalletID, $quoteCurrencyWalletID, $cryptoCurrencyTypesImported, $name, $accountID, $userEncryptionKey, $transactionSourceID, $transactionSourceName, $walletTypeID, $walletTypeName, $includeDetailReporting, $dataImportEventRecordID, $globalCurrentDate, $sid, $dbh)
	{
    	$returnValue   															= array();
  	
		$tradesArray															= $resultArray;
    	$numberProcessed														= 0;
    				
	    try
	    {
		    errorLog("count ".count($tradesArray));
			$transactionTimeStampIndex											= 0;    
		    
		    foreach ($tradesArray as $trade)
		    {
		    
				$pairs															= explode("-",$symbol);
				$baseCurrency													= $pairs[0];
				$quoteCurrency													= $pairs[1];
				$transactionPairName											= $baseCurrency.$quoteCurrency;				
				$txIDValue														= $trade -> tradeId;
				$orderTxID														= $trade -> orderId;
				$transactionPrice												= $trade -> price;
			    $transactionQty													= $trade -> size;
			    $transactionQuoteQty											= $trade -> price * $trade -> size;
			    $transactionCommission											= $trade -> fee;  
			    $transactionCommissionAsset										= $trade -> feeCurrency;  
			    
			    $transactionType												= strtoupper($trade -> side);
			    
			    $transactionIsTaker												= (strtoupper($trade -> liquidity) == "TAKER") ? true: false;
			    $transactionIsMaker												= $transactionIsTaker == true? 0:1;			    			    
			    $isDebit														= 0;

			    if ($transactionType == "SELL")
			    {  
					$isDebit													= 1;
			    }

				$transactionTimestamp 											= $trade -> createdAt;
// 				print_r("\nTimestamp: ".$transactionTimestamp);					
				$transactionTime										    	= gmdate("Y-m-d H:i:s", $transactionTimestamp/1000);
// 				print_r("\nTime: ".$transactionTime);		
																
				$creationDate													= $globalCurrentDate; 		
				$feeAssetTypeID											 		= getEnumValueAssetType($transactionCommissionAsset, $dbh);
				
				$baseToQuoteCurrencySpotPrice									= 0;	// quoteCurrencySpotPriceAtTimeOfTransaction					
				$baseToUSDCurrencySpotPrice										= 0;	// spotPriceAtTimeOfTransaction
				$btcSpotPriceAtTimeOfTransaction								= 0;	// btcPriceAtTimeOfTransaction
				$feeCurrencySpotPriceAtTimeOfTransaction						= 0;
				
				$cascadeRetrieveSpotPriceResponseObject							= getSpotPriceForAssetPairUsingSourceCascade($baseCurrencyAssetTypeID, 2, $transactionTime, 2, "Coinbase price by date", $dbh);
					
				if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
				{
					$baseToQuoteCurrencySpotPrice								= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
					$baseToUSDCurrencySpotPrice									= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
				}
				
				if ($baseCurrencyAssetTypeID == 1)
				{
					$btcSpotPriceAtTimeOfTransaction							= $baseToUSDCurrencySpotPrice;	
				}
				else
				{
					$cascadeRetrieveSpotPriceResponseObject						= getSpotPriceForAssetPairUsingSourceCascade($baseCurrencyAssetTypeID, 2, $transactionTime, 2, "Coinbase price by date", $dbh);;
					
					if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
					{
						$btcSpotPriceAtTimeOfTransaction						= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
					}	
				}
				
				// get spot price for fee currency in USD
				
				$cascadeRetrieveSpotPriceResponseObject							= getSpotPriceForAssetPairUsingSourceCascade($feeAssetTypeID, 2, $transactionTime, 2, "Coinbase price by date", $dbh);
					
				if ($cascadeRetrieveSpotPriceResponseObject['foundSpotPrice'] == true)
				{
					$feeCurrencySpotPriceAtTimeOfTransaction					= $cascadeRetrieveSpotPriceResponseObject['spotPrice'];
				}
				
				$feeAmountInUSD											 		= $feeCurrencySpotPriceAtTimeOfTransaction * $transactionCommission; // fee amount in USD
				
				$transactionAmountInUSD											= $baseToUSDCurrencySpotPrice * $transactionQty;	// usdAmount
				$transactionAmountMinusFeeInUSD									= $transactionAmountInUSD - $feeAmountInUSD;
				$sentQuantity													= $transactionAmountInUSD;
				$amount															= $transactionAmountInUSD;
				
				error_log("getKuCoinTransactionHistoryForUserViaAPI $transactionTime $transactionPairName $transactionPrice $transactionQty $transactionQuoteQty $transactionCommission $quoteCurrency");
				
			    // @Task - write code to convert currencies between USD and BTC and the current type - use amount objects
			    // KuCoin - every time I read a spot price, write it to the daily spot price table
			    setDailyPriceData($baseCurrencyAssetTypeID, $quoteCurrencyAssetTypeID, $transactionTimestamp, $transactionPrice, $transactionSourceID, $globalCurrentDate, $sid, $dbh);
			    
			    // @Task - it may be better to compact the array rather than have index gaps - write a function that checks to see if a value already exists in array, and if not, adds it
			    
			    if (isset($cryptoCurrencyTypesImported[$baseCurrencyAssetTypeID][$quoteCurrencyAssetTypeID]))
				{
					$currentCount												= $cryptoCurrencyTypesImported[$baseCurrencyAssetTypeID][$quoteCurrencyAssetTypeID];
					$currentCount++;
					
					$cryptoCurrencyTypesImported[$baseCurrencyAssetTypeID][$quoteCurrencyAssetTypeID]			= $currentCount;
				}
				else
				{
					$cryptoCurrencyTypesImported[$baseCurrencyAssetTypeID][$quoteCurrencyAssetTypeID]			= 1;	
				}
				
				$transactionAssetTypeID											= getEnumValueAssetType($quoteCurrency, $dbh);
				$transactionTypeID												= getEnumValueTransactionType($transactionType, $dbh);
/* 				echo("\nTransactionType: $transactionType: TransactionTypeID: $transactionTypeID"); */
				
				// check for global transaction ID
				
				error_log("getGlobalTransactionIdentificationRecordID for $accountID, $baseCurrencyAssetTypeID, $transactionSourceID, $globalCurrentDate, $sid");
				
			    $globalTransactionIDTestResults									= getGlobalTransactionIdentificationRecordID($accountID, $baseCurrencyAssetTypeID, $txIDValue, $transactionSourceID, $globalCurrentDate, $sid, $dbh);
			    
			    // here
			    
			    if ($globalTransactionIDTestResults['foundNativeTransactionForAccount'] == false)
				{
					error_log("not found $txIDValue");
					
					$returnValue[$txIDValue]["existingRecordFound"]				= false;
					
					// create one if not found
					$globalTransactionCreationResults							= createGlobalTransactionIdentificationRecord($accountID, $exchangeTileID, $baseCurrencyAssetTypeID, $txIDValue, $transactionSourceID, $globalCurrentDate, $sid, $dbh);
			
					if ($globalTransactionCreationResults['createdGlobalTransactionIdentificationRecord'] == true)
					{
						errorLog("createGlobalTransactionIdentificationRecord success");
						
						$returnValue[$txIDValue]["createdGTIR"]					= true;
							
						$globalTransactionIdentifierRecordID					= $globalTransactionCreationResults['globalTransactionIdentificationRecordID'];
						$profitStanceTransactionIDValue							= $globalTransactionCreationResults['profitStanceTransactionIDValue'];
							
						// @Task - this is where I need to use the new provider account wallet idea of 
							
						$providerAccountWallet									= new ProviderAccountWallet();
							
						// check for provider account wallet							
						$instantiationResult									= $providerAccountWallet -> instantiateAccountWalletObjectForAccountByAssetTypeIDAndTransactionSourceID($accountID, $baseCurrencyAssetTypeID, $transactionSourceID, $dbh);
							
						if ($instantiationResult['instantiatedWallet'] == false)
						{
							// create wallet if not found
							$providerAccountWallet -> createAccountWalletObject($accountID, $baseCurrencyAssetTypeID, $baseCurrencyAssetType, $accountID, "$accountID-$transactionSourceID-$baseCurrencyAssetType", $walletTypeID, "Private Ledger Based Wallet", $transactionSourceID, $transactionSourceName, $globalCurrentDate, $sid, $dbh);
						}
							
						$providerWalletID										= $providerAccountWallet -> getAccountWalletID();
						
						errorLog("providerWalletID $providerWalletID");
							
						if ($providerWalletID > 0)
						{
							// create KuCoin trade transaction object and write it
							
							$kuCoinTradeTransaction 							= new KuCoinTradeTransaction();
													
							$kuCoinTradeTransaction -> setData($accountID, $exchangeTileID, 0, $globalTransactionIdentifierRecordID, $transactionSourceID, $txIDValue, $orderTxID, $transactionPairName, $transactionTime, $transactionPrice, $transactionQty, $transactionQuoteQty, $transactionCommission, $quoteCurrency, $walletTypeID, $walletTypeName, $transactionTypeID, $providerWalletID,    $baseCurrencyWalletID, $quoteCurrencyWalletID, $baseCurrencyAssetTypeID, $quoteCurrencyAssetTypeID, $transactionTimestamp, $creationDate, $baseToQuoteCurrencySpotPrice, $baseToUSDCurrencySpotPrice, $btcSpotPriceAtTimeOfTransaction, $transactionAmountInUSD, $feeAmountInUSD, $isDebit, $transactionIsTaker, $transactionIsMaker, $userEncryptionKey, $globalCurrentDate, $sid, $dbh);
							
							$writeKuCoinRecordResponseObject					= $kuCoinTradeTransaction -> writeToDatabase($userEncryptionKey, $dbh);
							
							if ($writeKuCoinRecordResponseObject['wroteToDatabase'] == true)
							{
								error_log("Transaction inserted into KuCoinTradeTransactions table");
								// now that the transaction has been created, create the association record for the closing array if size > 0
								
								$profitStanceLedgerEntry						= new ProfitStanceLedgerEntry();
								$profitStanceLedgerEntry -> setData($accountID, $baseCurrencyAssetTypeID, $baseCurrencyAssetType, 4, "KuCoin", $exchangeTileID, $globalTransactionIdentifierRecordID, $transactionTime, $transactionQty, $dbh);
								
								$writeProfitStanceLedgerEntryRecordResponseObject = $profitStanceLedgerEntry -> writeToDatabase($dbh);
								
								if ($writeProfitStanceLedgerEntryRecordResponseObject['wroteToDatabase'] == true)
								{
									error_log("wrote profitStance ledger entry $accountID, $baseCurrencyAssetTypeID, $baseCurrencyAssetType, 4, \"KuCoin\", $exchangeTileID, $globalTransactionIdentifierRecordID, $transactionTime, $transactionQty to the database.");
								}
								else
								{
									error_log("could not write profitStance ledger entry $accountID, $baseCurrencyAssetTypeID, $baseCurrencyAssetType, 4, \"KuCoin\", $exchangeTileID, $globalTransactionIdentifierRecordID, $transactionTime, $transactionQty to the database.");	
								}
								
								// update native record ID in GTRID table
								$setNativeTransactionRecordIDResult				= setNativeTransactionRecordIDForGlobalTransactionIndentificationRecordID($accountID, $kuCoinTradeTransaction -> getKuCoinTransactionRecordID(), $globalTransactionIdentifierRecordID, $globalCurrentDate, $sid, $dbh);
															
							}
						}
					}
					
					$numberProcessed++;	
				}
				else
				{
					error_log("found $txIDValue");
					
					$returnValue[$txIDValue]["existingRecordFound"]				= true;
					$returnValue[$txIDValue]["newTransactionCreated"]			= false;
				}

				errorLog("completed array index $txIDValue");
		    }
		
			if ($includeDetailReporting != true)
			{
				$returnValue													= array();		
			}
			
			$returnValue["kuCoinDataImported"]									= "complete";
			$returnValue['numberProcessed']										= $numberProcessed;
			$returnValue['cryptoCurrencyTypesImported']							= $cryptoCurrencyTypesImported;
			$returnValue['transactionTimeStampIndex']							= $transactionTimeStampIndex;
			
	    }
	    catch (Exception $e)
	    {
		   	errorLog("ERROR: array parsing error");	
	    }
	    
	    errorLog(json_encode($returnValue));

        return $returnValue;
    }	
    
	function createCommonTransactionsForKuCoinTradeTransactions($liuAccountID, $userEncryptionKey, $globalCurrentDate, $sid, $dbh)
	{
		errorLog("createCommonTransactionsForkuCoinTradeTransactions($liuAccountID, $userEncryptionKey, $globalCurrentDate, $sid");
		
		$responseObject															= array();

		$responseObject['importedTransactions']									= false;
		
		$transactionSourceID													= 9;
		$transactionStatusID													= 1;
		$transactionStatusLabel													= "complete";
		$transactionSourceLabel													= "KuCoin";
		
		try
		{		
			$getkuCoinTransactionRecords										= $dbh -> prepare("SELECT
	kuCoinTradeTransactions.kuCoinTransactionRecordID AS transactionID,
	kuCoinTradeTransactions.FK_ExchangeTileID,
	kuCoinTradeTransactions.FK_GlobalTransactionRecordID AS FK_GlobalTransactionIdentificationRecordID,
	kuCoinTradeTransactions.FK_AccountID AS authorID,
	kuCoinTradeTransactions.FK_AccountID AS accountID,
	kuCoinTradeTransactions.FK_TransactionTypeID AS transactionTypeID,
	kuCoinTradeTransactions.FK_BaseCurrencyTypeID AS baseCurrencyID,		
	baseCurrencyAsset.assetTypeLabel AS baseCurrencyName,
	2 AS quoteSpotPriceCurrencyID,
	'USD' AS quoteSpotPriceCurrencyName,
	kuCoinTradeTransactions.creationDate,
	kuCoinTradeTransactions.transactionTime AS transactionDate,
	kuCoinTradeTransactions.transactionTimestamp,
	AES_DECRYPT(kuCoinTradeTransactions.encryptedTxIDValue, UNHEX(SHA2(:userEncryptionKey,512))) AS vendorTransactionID,
	ABS(kuCoinTradeTransactions.volBaseCurrency) AS btcQuantityTransacted,
	ABS(kuCoinTradeTransactions.usdAmount) AS usdQuantityTransacted,
	kuCoinTradeTransactions.spotPriceAtTimeOfTransaction AS spotPriceAtTimeOfTransaction,
	kuCoinTradeTransactions.btcPriceAtTimeOfTransaction AS btcPriceAtTimeOfTransaction,
	ABS(kuCoinTradeTransactions.usdAmount) + ABS(kuCoinTradeTransactions.feeAmountInUSD) AS usdTransactionAmountWithFees,
	kuCoinTradeTransactions.feeAmountInUSD AS networkTransactionFeeAmount,
	ABS(kuCoinTradeTransactions.feeAmountInUSD) AS usdFeeAmount,
	ABS(kuCoinTradeTransactions.usdAmount) - ABS(kuCoinTradeTransactions.feeAmountInUSD) AS transactionAmountMinusFeeInUSD,
	ABS(kuCoinTradeTransactions.usdAmount) + ABS(kuCoinTradeTransactions.feeAmountInUSD) AS transactionAmountPlusFeeInUSD,
	'' AS  providerNotes,
	kuCoinTradeTransactions.isDebit,
	kuCoinTradeTransactions.FK_BaseCurrencyWalletID AS FK_SourceAddressID,
	kuCoinTradeTransactions.FK_QuoteCurrencyTypeID AS FK_DestinationAddressID,
	TransactionTypes.displayTransactionTypeLabel,
	TransactionTypes.transactionTypeLabel
FROM
	kuCoinTradeTransactions
	INNER JOIN TransactionTypes ON kuCoinTradeTransactions.FK_TransactionTypeID = TransactionTypes.transactionTypeID AND TransactionTypes.languageCode = 'EN'
	INNER JOIN AssetTypes baseCurrencyAsset ON kuCoinTradeTransactions.FK_BaseCurrencyTypeID = baseCurrencyAsset.assetTypeID AND baseCurrencyAsset.languageCode = 'EN'
WHERE
	kuCoinTradeTransactions.FK_AccountID = :accountID
ORDER BY
	kuCoinTradeTransactions.transactionTimestamp");
	
			$getkuCoinTransactionRecords -> bindValue(':accountID', $liuAccountID);
			$getkuCoinTransactionRecords -> bindValue(':userEncryptionKey', $userEncryptionKey);
		
			if ($getkuCoinTransactionRecords -> execute() && $getkuCoinTransactionRecords -> rowCount() > 0)
			{

				errorLog("began get kuCoin crypto transaction records ".$getkuCoinTransactionRecords -> rowCount() > 0);
				
				while ($row = $getkuCoinTransactionRecords -> fetchObject())
				{		
					$transactionID												= $row -> transactionID;
					$exchangeTileID												= $row -> FK_ExchangeTileID;
					$globalTransactionIdentificationRecordID					= $row -> FK_GlobalTransactionIdentificationRecordID;
					$accountID													= $row -> accountID;	
					$authorID													= $row -> authorID;						
					$transactionTypeID											= $row -> transactionTypeID;
					$baseCurrencyID												= $row -> baseCurrencyID; // was assetTypeID - done
					$baseCurrencyName											= $row -> baseCurrencyName; // assetTypeName - not needed							
					$quoteSpotPriceCurrencyID									= $row -> quoteSpotPriceCurrencyID; // was spotPriceCurrencyTypeID - done, needs verification
					$quoteSpotPriceCurrencyName									= $row -> quoteSpotPriceCurrencyName; // was spotPriceCurrencyType								
					$amount														= $row -> btcQuantityTransacted;
					$fee														= $row -> networkTransactionFeeAmount;
					$baseToUSDCurrencySpotPrice									= $row -> spotPriceAtTimeOfTransaction;
					$btcSpotPriceAtTimeOfTransaction							= $row -> btcPriceAtTimeOfTransaction;
					$creationDate												= $row -> creationDate;
					$transactionDate											= $row -> transactionDate;
					$transactionTimestamp										= $row -> transactionTimestamp;
					$vendorTransactionID										= $row -> vendorTransactionID;	
					$transactionAmountInUSD										= $row -> usdQuantityTransacted;
					$transactionAmountMinusFeeInUSD								= $row -> transactionAmountMinusFeeInUSD;
					$transactionAmountPlusFeeInUSD								= $row -> transactionAmountPlusFeeInUSD;
					$feeAmountInUSD												= $row -> usdFeeAmount;
					$usdTransactionAmountWithFees								= $row -> usdTransactionAmountWithFees;
					$providerNotes												= $row -> providerNotes;
					$transactionTypeLabel										= $row -> transactionTypeLabel;
					$displayTransactionTypeLabel								= $row -> displayTransactionTypeLabel;
					$isDebit													= $row -> isDebit;						
					$sourceWalletID												= $row -> FK_SourceAddressID;
					$destinationWalletID										= $row -> FK_DestinationAddressID;
						
					$responseObject['processingTransaction'][]					= $vendorTransactionID;
					
					$getNativeAndCommonTransactionRecordIDsResult				= getNativeAndCommonTransactionRecordIDsForGlobalTransactionIdentificationRecordID($accountID, $baseCurrencyID, $vendorTransactionID, $transactionSourceID, $globalTransactionIdentificationRecordID, $globalCurrentDate, $sid, $dbh);
					
					errorLog("commonTransactionID: ". $getNativeAndCommonTransactionRecordIDsResult['commonTransactionRecordID']);
			
					$commonTransactionID										= $getNativeAndCommonTransactionRecordIDsResult['commonTransactionRecordID'];
			
					if (empty($commonTransactionID))
					{
						$unspentTransactionTotal								= 0;
						$unfundedSpendTotal										= 0;
						
						if ($isDebit == 0)
						{
							$unspentTransactionTotal  							= $amount;  // shouldn't this be the amount minus the fee amount
						}
						else if ($isDebit == 1)
						{
							$unfundedSpendTotal									= $amount; 	// shouldn't this be the amount minus the fee amount
						}	
						
						$sourceWallet											= new CompleteCryptoWallet();
						$destinationWallet										= new CompleteCryptoWallet();
				
						$sourceWalletResponseObject								= $sourceWallet -> instantiateWalletUsingCryptoWalletRecordID($accountID, $sourceWalletID, $userEncryptionKey, $dbh);
				
						if ($sourceWalletResponseObject['instantiatedRecord'] == false)
						{
							errorLog("Could not instantiate source Complete Crypto Wallet record $accountID");
						}
						
						$destinationWalletResponseObject						= $destinationWallet -> instantiateWalletUsingCryptoWalletRecordID($accountID, $destinationWalletID, $userEncryptionKey, $dbh);
				
						if ($destinationWalletResponseObject['instantiatedRecord'] == false)
						{
							errorLog("Could not instantiate destination Complete Crypto Wallet record $accountID, $destinationWalletID");
						}
						
						errorLog($vendorTransactionID."<BR>");
					
						$cryptoTransaction										= new CryptoTransaction();
					
						$cryptoTransaction -> setData(0, $accountID, $authorID, $exchangeTileID, $globalTransactionIdentificationRecordID, $transactionTypeID, $transactionTypeLabel, $transactionStatusID, $transactionStatusLabel, $transactionSourceID, $transactionSourceLabel, $baseCurrencyID, $baseCurrencyName, $quoteSpotPriceCurrencyID, $quoteSpotPriceCurrencyName, $sourceWalletID, $destinationWalletID, $creationDate, $transactionDate, $transactionTimestamp, $transactionID, $vendorTransactionID, $amount, $transactionAmountInUSD, $baseToUSDCurrencySpotPrice, $btcSpotPriceAtTimeOfTransaction, $transactionAmountMinusFeeInUSD, $fee, $feeAmountInUSD, $unspentTransactionTotal, $providerNotes, $isDebit, $sid);
					
						$writeToDatabaseResponse								= $cryptoTransaction -> writeToDatabase($userEncryptionKey, $dbh);
						
						if ($writeToDatabaseResponse['wroteToDatabase'] == true)
						{
							$transactionID										= $cryptoTransaction -> getTransactionID();
							
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
	kuCoinTradeTransactions.kuCoinTransactionRecordID AS transactionID,
	kuCoinTradeTransactions.FK_ExchangeTileID,
	kuCoinTradeTransactions.FK_GlobalTransactionRecordID AS FK_GlobalTransactionIdentificationRecordID,
	kuCoinTradeTransactions.FK_AccountID AS authorID,
	kuCoinTradeTransactions.FK_AccountID AS accountID,
	kuCoinTradeTransactions.FK_TransactionTypeID AS transactionTypeID,
	kuCoinTradeTransactions.FK_BaseCurrencyTypeID AS baseCurrencyID,		
	baseCurrencyAsset.assetTypeLabel AS baseCurrencyName,
	2 AS quoteSpotPriceCurrencyID,
	'USD' AS quoteSpotPriceCurrencyName,
	kuCoinTradeTransactions.creationDate,
	kuCoinTradeTransactions.transactionTime AS transactionDate,
	kuCoinTradeTransactions.transactionTimestamp,
	AES_DECRYPT(kuCoinTradeTransactions.encryptedTxIDValue, UNHEX(SHA2(:userEncryptionKey,512))) AS vendorTransactionID,
	ABS(kuCoinTradeTransactions.volBaseCurrency) AS btcQuantityTransacted,
	ABS(kuCoinTradeTransactions.usdAmount) AS usdQuantityTransacted,
	kuCoinTradeTransactions.spotPriceAtTimeOfTransaction AS spotPriceAtTimeOfTransaction,
	kuCoinTradeTransactions.btcPriceAtTimeOfTransaction AS btcPriceAtTimeOfTransaction,
	ABS(kuCoinTradeTransactions.usdAmount) + ABS(kuCoinTradeTransactions.feeAmountInUSD) AS usdTransactionAmountWithFees,
	kuCoinTradeTransactions.feeAmountInUSD AS networkTransactionFeeAmount,
	ABS(kuCoinTradeTransactions.feeAmountInUSD) AS usdFeeAmount,
	ABS(kuCoinTradeTransactions.usdAmount) - ABS(kuCoinTradeTransactions.feeAmountInUSD) AS transactionAmountMinusFeeInUSD,
	ABS(kuCoinTradeTransactions.usdAmount) + ABS(kuCoinTradeTransactions.feeAmountInUSD) AS transactionAmountPlusFeeInUSD,
	'' AS  providerNotes,
	kuCoinTradeTransactions.isDebit,
	kuCoinTradeTransactions.FK_BaseCurrencyWalletID AS FK_SourceAddressID,
	kuCoinTradeTransactions.FK_QuoteCurrencyTypeID AS FK_DestinationAddressID,
	TransactionTypes.displayTransactionTypeLabel,
	TransactionTypes.transactionTypeLabel
FROM
	kuCoinTradeTransactions
	INNER JOIN TransactionTypes ON kuCoinTradeTransactions.FK_TransactionTypeID = TransactionTypes.transactionTypeID AND TransactionTypes.languageCode = 'EN'
	INNER JOIN AssetTypes baseCurrencyAsset ON kuCoinTradeTransactions.FK_BaseCurrencyTypeID = baseCurrencyAsset.assetTypeID AND baseCurrencyAsset.languageCode = 'EN'
WHERE
	kuCoinTradeTransactions.FK_AccountID = :accountID
ORDER BY
	kuCoinTradeTransactions.transactionTimestamp");	

			}
			
			$responseObject['importedTransactions']								= true;
		}
		catch (PDOException $e) 
		{
			$cryptoTransaction 													= null;	
			$responseObject['importedTransactions']								= false;
			
			errorLog($e -> getMessage());
		
			die();
		}
		
		return $responseObject;
	}    
	// End KuCoin
	
	// CEXIO FIFO
	function performFIFOTransactionCalculationsOnCEXIOTransactions($accountID, $userEncryptionKey, $assetTypeID, $globalCurrentDate, $sid, $dbh)
	{
		$responseObject														= array();
		$responseObject['completedFIFOCalculations']						= false;
		
		try
		{		
			$removeBalanceRecordsForAssetType								= $dbh -> prepare("DELETE c.* FROM 
	CryptoBalanceRecords c 
	INNER JOIN Transactions t ON c.FK_ReceiveTransactionID = t.transactionID
WHERE 
	c.FK_AccountID = :accountID AND t.FK_AssetTypeID = :assetTypeID;");
			
			$removeAllGroupingTransactionsForAssetType						= $dbh -> prepare("DELETE o.* FROM 
	OutboundTransactionSourceGrouping  o
	INNER JOIN Transactions t ON o.FK_InboundAssetTransactionID = t.transactionID
WHERE 
	o.FK_AccountID = :accountID AND
	t.FK_AssetTypeID = :assetTypeID;");
			
			$resetTransactionUnspentTotalsForAssetType						= $dbh -> prepare("UPDATE 
	Transactions
SET
	unspentTransactionTotal = btcQuantityTransacted
WHERE
	FK_AccountID = :accountID AND
	FK_AssetTypeID = :assetTypeID AND
	isDebit = 0");
	
			$getTransactionRecords											= $dbh -> prepare("SELECT
	Transactions.transactionID
FROM
	Transactions
	INNER JOIN AssetTypes ON Transactions.FK_AssetTypeID = AssetTypes.assetTypeID AND AssetTypes.languageCode = 'EN'
	INNER JOIN TransactionTypes ON Transactions.FK_TransactionTypeID = TransactionTypes.transactionTypeID AND TransactionTypes.languageCode = 'EN'
WHERE
	Transactions.FK_AccountID = :accountID AND
	Transactions.FK_AssetTypeID = :assetTypeID
ORDER BY
	Transactions.transactionTimestamp");
		
			$getFundedReceiveCryptoTransactionRecord	= $dbh -> prepare("SELECT
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
		Transactions.FK_AssetTypeID = :assetType AND
		Transactions.transactionTimestamp < :currentTransactionTimestamp
	ORDER BY
		Transactions.transactionDate
	LIMIT 10");
		
			$removeBalanceRecordsForAssetType -> bindValue(':accountID', $accountID);
			$removeBalanceRecordsForAssetType -> bindValue(':assetTypeID', $assetTypeID);
			
			$removeBalanceRecordsForAssetType -> execute();
			
			$removeAllGroupingTransactionsForAssetType -> bindValue(':accountID', $accountID);
			$removeAllGroupingTransactionsForAssetType -> bindValue(':assetTypeID', $assetTypeID);
			
			$removeAllGroupingTransactionsForAssetType -> execute();
			
			$resetTransactionUnspentTotalsForAssetType -> bindValue(':accountID', $accountID);
			$resetTransactionUnspentTotalsForAssetType -> bindValue(':assetTypeID', $assetTypeID);
			
			$resetTransactionUnspentTotalsForAssetType -> execute();
		
			$getTransactionRecords -> bindValue(':accountID', $accountID);
			$getTransactionRecords -> bindValue(':assetTypeID', $assetTypeID);
		
			if ($getTransactionRecords -> execute() && $getTransactionRecords -> rowCount() > 0)
			{
				errorLog("began get common transaction records ".$getTransactionRecords -> rowCount() > 0);
				
				while ($row = $getTransactionRecords -> fetchObject())
				{
					$transactionID											= $row -> transactionID;	
					
					$cryptoTransaction										= new CryptoTransaction();
					$instantiateTransactionResponse							= $cryptoTransaction -> instantiateCryptoTransaction($accountID, $transactionID, $userEncryptionKey, $dbh);
					
					if ($instantiateTransactionResponse['instantiatedCryptoTransaction'] == true)
					{
						$responseObject['processingTransaction'][]			= $cryptoTransaction -> getVendorTransactionID();
						
						$authorID											= $cryptoTransaction -> getAuthorID();
					
						$unspentTransactionTotal								= 0;
						$unfundedSpendTotal									= 0;
						
						if ($cryptoTransaction -> getIsDebit() == 0)
						{
							$unspentTransactionTotal  						= $cryptoTransaction -> getUnspentTransactionTotal();
						}
						else if ($cryptoTransaction -> getIsDebit() == 1)
						{
							errorLog("is debit<BR><BR>account ID: $accountID assetType: $assetTypeID<BR><BR>");
							
							$unfundedSpendTotal								= $cryptoTransaction -> getBtcQuantityTransacted();	
							$doContinue										= 1;
								
							while ($doContinue == 1)
							{
								while ($unfundedSpendTotal > 0 && $doContinue == 1)
								{
									$getFundedReceiveCryptoTransactionRecord -> bindValue(':accountID', $accountID);
									$getFundedReceiveCryptoTransactionRecord -> bindValue(':assetType', $assetTypeID);
									$getFundedReceiveCryptoTransactionRecord -> bindValue(':currentTransactionTimestamp', $cryptoTransaction -> getTransactionTimestamp());
									
									$getFundedReceiveCryptoTransactionRecord -> bindValue(':userEncryptionKey', $userEncryptionKey);
									
									if ($getFundedReceiveCryptoTransactionRecord -> execute() && $getFundedReceiveCryptoTransactionRecord -> rowCount() > 0)
									{
										errorLog("executed getFundedReceiveCryptoTransactionRecord for transaction $transactionID<BR>");
										
										$row2 								= $getFundedReceiveCryptoTransactionRecord -> fetchObject();
										
										$remainingUnspent					= $row2 -> unspentTransactionTotal;
										$sourceTransactionID					= $row2 -> transactionID;
										$receivedTransactionDate				= $row2 -> transactionDate;
										$receiveSpotPriceAtTimeOfTransaction	= $row2 -> spotPriceAtTimeOfTransaction;
										
										errorLog("remainingUnspent: $remainingUnspent, sourceTransactionID: $sourceTransactionID, receivedTransactionDate: $receivedTransactionDate, receiveSpotPriceAtTimeOfTransaction: $receiveSpotPriceAtTimeOfTransaction");
										
										if ($remainingUnspent >= $unfundedSpendTotal)
										{
											errorLog("because the remaining unspent amount for the receive transaction is greater than the $unfundedSpendTotal, the $unfundedSpendTotal will be the amount spent by this spent transaction group item, and the receive transaction unspent transaction total will be updated to the different between the amount that was spent in the transaction, and the amount that was unspent");
											
											$unspentTransactionTotal			= $remainingUnspent - $unfundedSpendTotal;
											
											$groupingTransactionID			= createCryptoTransactionGroupingRecord($accountID, $accountID, $sourceTransactionID, $transactionID, $cryptoTransaction -> getTransactionTypeID(), $cryptoTransaction -> getDisplayTransactionTypeLabel(), $unfundedSpendTotal, $receivedTransactionDate, $cryptoTransaction -> getTransactionDate(), $receiveSpotPriceAtTimeOfTransaction, $cryptoTransaction -> getSpotPriceAtTimeOfTransaction(), $globalCurrentDate, $sid, $dbh);
											
											if ($groupingTransactionID > 0)
											{
												
												// @task 2019-03-06 why is this set to 0? - because the remaining unspent is more than the unfunded spend total for this transaction, and so it was completely consumed.
												$unfundedSpendTotal			= 0;	
												
												// update source transaction remaining unspent - set to $unspentTransactionTotal
												if (updateCryptoTransactionUnspentBalance($accountID, $accountID, $sourceTransactionID, $unspentTransactionTotal, $sid, $dbh) > 0)
												{
													errorLog("SUCCESS: executed updateCryptoTransactionUnspentBalance for $accountID, $accountID, $sourceTransactionID, $unspentTransactionTotal, $sid");
												}
												else
												{
													errorLog("ERROR: unable to execute updateCryptoTransactionUnspentBalance for $accountID, $accountID, $sourceTransactionID, $unspentTransactionTotal, $sid");
												}
											}
											else
											{
												errorLog("ERROR: Unable to execute createCryptoTransactionGroupingRecord for $accountID, $accountID, $sourceTransactionID, $transactionID, $unfundedSpendTotal, $spendTransactionDate, $globalCurrentDate, $sid");
											}
											
											$doContinue						= 0;
										}
										else if ($remainingUnspent < $unfundedSpendTotal)
										{
											errorLog("there is not enough left in this receive transaction to pay for the entire spend - the amount that remained in this receive transaction will be the sub transaction amount, and another receive transaction with available funds will be found<BR><BR>");
											
											$unfundedSpendTotal				= $unfundedSpendTotal - $remainingUnspent;
											
											$groupingTransactionID			= createCryptoTransactionGroupingRecord($accountID, $accountID, $sourceTransactionID, $transactionID, $cryptoTransaction -> getTransactionTypeID(), $cryptoTransaction -> getDisplayTransactionTypeLabel(), $remainingUnspent, $receivedTransactionDate, $cryptoTransaction -> getTransactionDate(), $receiveSpotPriceAtTimeOfTransaction, $cryptoTransaction -> getSpotPriceAtTimeOfTransaction(), $globalCurrentDate, $sid, $dbh);
											
											$remainingUnspent				= 0;
											
											if ($groupingTransactionID > 0)
											{
												// @task update source transaction remaining unspent - set to $remainingUnspent, which is 0
												if (updateCryptoTransactionUnspentBalance($accountID, $accountID, $sourceTransactionID, $remainingUnspent, $sid, $dbh) > 0)
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
										$doContinue 							= 0;
									}	
								}								
							}
						}
						
						// done
						$getFundedReceiveCryptoTransactionRecord -> bindValue(':accountID', $accountID);
						$getFundedReceiveCryptoTransactionRecord -> bindValue(':assetType', $assetTypeID);
						$getFundedReceiveCryptoTransactionRecord -> bindValue(':currentTransactionTimestamp', $cryptoTransaction -> getTransactionTimestamp());
						$getFundedReceiveCryptoTransactionRecord -> bindValue(':userEncryptionKey', $userEncryptionKey);
						
						if ($getFundedReceiveCryptoTransactionRecord -> execute() && $getFundedReceiveCryptoTransactionRecord -> rowCount() > 0)
						{
							while ($row3 = $getFundedReceiveCryptoTransactionRecord -> fetchObject())
							{
								$remainingUnspent							= $row3 -> unspentTransactionTotal;
								$sourceTransactionID							= $row3 -> transactionID;
								$receiveSpotPriceAtTimeOfTransaction			= $row3 -> spotPriceAtTimeOfTransaction;
								
								createCryptoTransactionBalanceRecord($authorID, $accountID, $transactionID, $sourceTransactionID, $remainingUnspent, $receiveSpotPriceAtTimeOfTransaction, $globalCurrentDate, $sid, $dbh);
							}			
						}	
					}
					else
					{
						error_log("unable to instantiate transaction $transactionID");
					}
				}
			}
			else
			{
				errorLog("no transactions received");
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
	
	function createGlobalTransactionRecord($accountID, $exchangeTileID, $baseCurrencyAssetTypeID, $txIDValue, $transactionSourceID, $globalCurrentDate, $sid, $dbh)
	{			
		error_log("getGlobalTransactionIdentificationRecordID for $accountID, $baseCurrencyAssetTypeID, $transactionSourceID, $globalCurrentDate, $sid");
		
	    $globalTransactionIDTestResults								= getGlobalTransactionIdentificationRecordID($accountID, $baseCurrencyAssetTypeID, $txIDValue, $transactionSourceID, $globalCurrentDate, $sid, $dbh);
	    
	    // here
	    
	    if ($globalTransactionIDTestResults['foundNativeTransactionForAccount'] == false)
		{
			error_log("not found $txIDValue");
						
			// create one if not found
			$globalTransactionCreationResults						= createGlobalTransactionIdentificationRecord($accountID, $exchangeTileID, $baseCurrencyAssetTypeID, $txIDValue, $transactionSourceID, $globalCurrentDate, $sid, $dbh);
	
			if ($globalTransactionCreationResults['createdGlobalTransactionIdentificationRecord'] == true)
			{
				errorLog("createGlobalTransactionIdentificationRecord success");
				
				$returnValue[$txIDValue]["createdGTIR"]				= true;
					
				$globalTransactionIdentifierRecordID				= $globalTransactionCreationResults['globalTransactionIdentificationRecordID'];
				
				return $globalTransactionIdentifierRecordID;			
			}
		}
		
		return 0;
	}
	
	// END CEXIO FIFO
?>
