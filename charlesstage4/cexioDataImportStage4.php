<?php
	
	
	include_once("./commonModel.php");
	require_once("./commonExchangeFunctions.php");
		

	
	$liUser																		= 1;

	$userObject																	= new UserInformationObject();
	$userObject	-> instantiateUserObjectByUserAccountID($liUser, $dbh, $sid);
	
	$userEncryptionKey															= $userObject -> getEncryptionKey();

	$responseObject																= array();
	
	$responseObject['userAccountID']											= $liUser;
	
	$transactionSourceID														= 41;
	
	$jsonObject																	= json_decode(($stream = fopen('php://input', 'r')) !== false ? stream_get_contents($stream) : "{}");
	
	$dataImportEventRecordID													= cleanJSONNumber($jsonObject, "dataImportEventRecordID");
	
	errorLog("dataImportEventRecordID $dataImportEventRecordID");
		
	$responseObject['dataImportEventRecordID']									= $dataImportEventRecordID;
	
	$assetStatusRecordResults													= getAssetStatusRecordsForDataImportWithLastCompletedStageIDAndBaseCurrencyOnly($liUser, $userEncryptionKey, $dataImportEventRecordID, $globalCurrentDate, $sid, $dbh);
	
	if ($assetStatusRecordResults['dataImportAssetStatusRecordFound'] == true)
	{
		$responseObject["calculationHistory"]['create']['ledger']				= createCommonTransactionsForCexioTradeTransactions($liUser, $userEncryptionKey, $globalCurrentDate, $sid, $dbh);
		
		$assetArray																= $assetStatusRecordResults['assets'];
		
		foreach ($assetArray as $baseCurrencyTypeID => $detailArray)
		{
			errorLog("beginning performFIFOTransactionCalculationsOnCommonTransactions for $baseCurrencyTypeID for user $liUser with last stage completed ".$detailArray['lastStageCompleted']);
			
			if ($detailArray['lastStageCompleted'] == 3)
			{
				$baseCurrencyTypeLabel											= $detailArray['assetTypeLabel'];
				
				$responseObject["calculationHistory"][$baseCurrencyTypeID]		= performFIFOTransactionCalculationsOnCEXIOTransactions($liUser, $userEncryptionKey, $baseCurrencyTypeID, $globalCurrentDate, $sid, $dbh);					
				updateDataImportStageCompletionDateForAssetTypeAllRecords($liUser, $dataImportEventRecordID, $baseCurrencyTypeID, 4, $globalCurrentDate, $sid, $dbh);
			}		
		}
		
		updateDataImportEventStatus($liUser, $liUser, $dataImportEventRecordID, 4, $globalCurrentDate, $sid, $dbh);
	}
		
	echo json_encode($responseObject);
	
?>