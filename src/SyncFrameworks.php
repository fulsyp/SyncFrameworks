<?php
namespace SyncFrameworks;
define("SYNC_DS", strpos($_SERVER['SCRIPT_FILENAME'], '/') !== FALSE ? '/' : '\\');
define("SYNC_DEFAULT_INCLUDE_PATH", dirname($_SERVER['SCRIPT_FILENAME']).SYNC_DS);
define("SYNC_DEFAULT_FOLDER_PATH", dirname($_SERVER['SCRIPT_FILENAME']).SYNC_DS);
define("SYNC_REMOTE_URL_LINKER", "http://localhost/SyncFrameworks2/tester.php");
define("SYNC_REMOTE_URL_LINKER_PARAMETER", "sync_action");
define("SYNC_REMOTE_URL_LINKER_FILES_CHECKSUM", "files_checksum");
define("SYNC_REMOTE_URL_LINKER_UPDATE_FILES", "update_files");
define("SYNC_FILE_PARAMTER", "sync_file");
$SYNC_EXCEPTIONS = array();//files exception releative to the SYNC_DEFAULT_FOLDER_PATH const

class SyncFrameworks{
	
	public static function getFilesChecksum(){
		return array("result"=>static::getFilesChecksumPrivate(null));
	}
	
	private static function getFilesChecksumPrivate($folder = null){
		global $SYNC_EXCEPTION;
		$source_include_file = get_include_path();
		set_include_path(SYNC_DEFAULT_INCLUDE_PATH);
		$sourceFolder = $folder;
		if($folder == null){
			$folder = SYNC_DEFAULT_FOLDER_PATH;
		}
		$files = scandir ( $folder );
		$result = array ();
		foreach ( $files as $file ) {
			$flag = true;
			for($i=0;$i<count($SYNC_EXCEPTION);$i++){
				if(strcmp(SYNC_DEFAULT_FOLDER_PATH.($SYNC_EXCEPTION[$i]), $folder.$file) == 0){
					$flag = false;
				}
			}
			if($flag){
				if ($file {0} != ".") {
					if (is_dir ( $folder . SYNC_DS . $file )) {
						$resultContent = self::getFilesChecksumPrivate( $folder . $file . SYNC_DS);
						foreach ($resultContent as $file=>$checksum){
							$result[str_replace(SYNC_DEFAULT_FOLDER_PATH, "", $file)] = $checksum;
						}
					} else {
						$result[str_replace(SYNC_DEFAULT_FOLDER_PATH, "", $folder.$file)] = md5_file($folder . $file);
					}
				}
			}
		}
		set_include_path($source_include_file);
		return $result;
	}
	
	public static function updateFilesFromZip(){
		global $_FILES;
		$zip = new \ZipArchive();
		$zip->open($_FILES[SYNC_FILE_PARAMTER]['tmp_name']);
		$result = $zip->extractTo(SYNC_DEFAULT_FOLDER_PATH);
		$stream = $zip->getStatusString();
		$zip->close();
		return array("result"=>$result);
	}
	
	public static function syncFilesChecksum(array $additionalData=array()){
		$channel = curl_init();
		$link = SYNC_REMOTE_URL_LINKER."?".SYNC_REMOTE_URL_LINKER_PARAMETER."=".SYNC_REMOTE_URL_LINKER_FILES_CHECKSUM;
		curl_setopt_array($channel, array(
				CURLOPT_URL				=>	SYNC_REMOTE_URL_LINKER."?".SYNC_REMOTE_URL_LINKER_PARAMETER."=".SYNC_REMOTE_URL_LINKER_FILES_CHECKSUM,
				CURLOPT_POSTFIELDS		=>	http_build_query($additionalData),
				CURLOPT_POST			=>	1,
				CURLOPT_RETURNTRANSFER	=>	true
		));
		$test = curl_exec($channel);
		$remote = json_decode($test, true)['result'];
		curl_close($channel);
		$local = static::getFilesChecksum()['result'];
		$zip = new \ZipArchive();
		$filename = SYNC_DEFAULT_FOLDER_PATH."zip-".uniqid().".zip";
		$zip->open($filename, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
		$atLeastOne = false;
		$newRemote = [];
		foreach ($remote as $remote_file=>$remote_checksum){
			$newRemote[str_replace("\\",SYNC_DS, str_replace("/", SYNC_DS, $remote_file))] = $remote_checksum;
		}
		$files = [];
		foreach ($local as $file=>$checksum){
			if(isset($newRemote[str_replace("\\",SYNC_DS, str_replace("/", SYNC_DS, $file))])){
				if($checksum != $newRemote[str_replace("\\",SYNC_DS, str_replace("/", SYNC_DS, $file))]){
					$files[] = $file;
					$zip->addFile($file, dirname($file).SYNC_DS.basename($file));
					$atLeastOne = true;
				}
			}else{
				$files[] = $file;
				$zip->addFile($file, dirname($file).SYNC_DS.basename($file));
				$atLeastOne = true;
			}
		}
		$zip->close();
		if(!$atLeastOne){
			return array("synced"=>array());
		}
		$additionalData[SYNC_FILE_PARAMTER] = new \CURLFile($filename, "", "file.zip");
		$channel = curl_init();
		$link = SYNC_REMOTE_URL_LINKER."?".SYNC_REMOTE_URL_LINKER_PARAMETER."=".SYNC_REMOTE_URL_LINKER_UPDATE_FILES;
		curl_setopt_array($channel, array(
				CURLOPT_URL				=>	SYNC_REMOTE_URL_LINKER."?".SYNC_REMOTE_URL_LINKER_PARAMETER."=".SYNC_REMOTE_URL_LINKER_UPDATE_FILES,
				CURLOPT_POST			=>	1,
				CURLOPT_POSTFIELDS		=>	$additionalData,
				CURLOPT_RETURNTRANSFER	=>	true,
				CURLOPT_SSL_VERIFYHOST	=>	0,
				CURLOPT_SSL_VERIFYPEER	=>	0,
		));
		$result = curl_exec($channel);
		curl_close($channel);
		unlink($filename);
		return array("result"=>$result, "files"=>$files);
	}
}