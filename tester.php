<?php
use SyncFrameworks\SyncFrameworks;
require_once 'src/SyncFrameworks.php';
if(isset($_GET[SYNC_REMOTE_URL_LINKER_PARAMETER])){
	$output = array();
	switch($_GET[SYNC_REMOTE_URL_LINKER_PARAMETER]){
		case SYNC_REMOTE_URL_LINKER_FILES_CHECKSUM:
			$output = SyncFrameworks::getFilesChecksum();
			break;
		case SYNC_REMOTE_URL_LINKER_UPDATE_FILES:
			if(isset($_FILES[SYNC_FILE_PARAMTER])){
				$output = SyncFrameworks::updateFilesFromZip();
			}
			break;
	}
	header('Content-Type: application/json');
	echo json_encode($output);
}
/*
else{
	if(isset($_GET['test'])){
		$result = SyncFrameworks::syncFilesChecksum();
		header('Content-Type: application/json');
		echo json_encode($result);
	}
tester
}*/
	