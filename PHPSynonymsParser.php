<?php

/*
 * Usage exemple :
 *
 * PHPSynonymsParser::dumpToDB(PHPSynonymsParser::parseFile('libreoffrice-dictionnary-file.oxt', 'fr'), $pdo_connection);
 *
 */
class PHPSynonymsParser {

  const TMP_PATH = '/tmp/php-synmonyms';

  private $_language = 'fr';
  private $_filepath = '';

  private $_synonyms = array();

  static public function parseFile($filepath, $language='fr') {
    if (file_exists($filepath)) {

      /* check file extension */
      $ext = pathinfo($filepath, PATHINFO_EXTENSION);
      if ($ext !== 'oxt') {
	throw new Exception('File extension is not valid');
      }

      /* create instance and parse file */
      $synonyms = new PHPSynonymsParser($filepath, $language);
      $synonyms->extractFile();
      $synonyms->parse();
      $synonyms->clean();
      return $synonyms;
    }
    throw new Exception('File does not exists');
  }

  static public function dumpToStdOut(PHPSynonymsParser $synonyms) {
    print_r($synonyms->_synonyms);
  }

  static public function dumpToDB(PHPSynonymsParser $synonyms, PDO $conn, $table='synonyms') {

    $query = 'CREATE TABLE IF NOT EXISTS `'.$table.'` '
      .'(`id` INT NOT NULL AUTO_INCREMENT, '
      .'`locale` VARCHAR(2) NOT NULL, '
      .'`word` VARCHAR(128) NOT NULL, '
      .'`synonyms` TEXT NOT NULL, '
      .'PRIMARY KEY (`id`))';
    $conn->prepare($query)->execute();

    $conn->beginTransaction();
    foreach ($synonyms->_synonyms as $word => $syms) {
      $conn->exec("INSERT INTO `".$table."` (locale, word, synonyms) values ('".$synonyms->_language."', '".$word."', '".join(',', $syms)."')");
    }
    $conn->commit();
  }

  private function __construct($filepath, $language='fr') {
    $this->_language = $language;
    $this->_filepath = $filepath;
  }

  private function parse() {
    $path = $this->getThesFilePath();
    $content = file_get_contents($path);

    $regexp = '#(([\wazéêèëîïöôùàáâãäåòóôõöøçùúûüÿñß\s ]+)\|1)+[\r\n\s]+\(.*\)((\|.+)+)#xi';
    if(preg_match_all($regexp, $content, $lines, PREG_SET_ORDER)){
      foreach($lines as $line){
	$word = $line[2];
	$synonyms = array_slice(explode('|', $line[3]), 1);
	$this->_synonyms[$word] = $synonyms;
      }
    }
  }

  private function getThesFilePath() {
    return self::TMP_PATH.'/dictionaries/thes_fr.dat';
  }


  private function extractFile() {
    $zip = new ZipArchive;
    $res = $zip->open($this->_filepath);
    if ($res === TRUE) {
      $zip->extractTo(self::TMP_PATH);
      $zip->close();
      return true;
    }
    throw new Exception('Failed to extract zip file. Maybe you don\'t have permission.');
  }

  private function clean() {
    $this->cleanDirectory(self::TMP_PATH);
  }

  private function cleanDirectory($dir) { 
    if (!file_exists($dir)) {
      return true;
    }
    if (!is_dir($dir) || is_link($dir)) {
      return unlink($dir);
    }
    foreach (scandir($dir) as $item) { 
      if ($item == '.' || $item == '..')
	continue; 
      if (!$this->cleanDirectory($dir . "/" . $item)) { 
	chmod($dir . "/" . $item, 0777); 
	if (!$this->cleanDirectory($dir . "/" . $item))
	  return false; 
      }
    }
    return rmdir($dir); 
  }

}

//PHPSynonymsParser::dumpToStdOut(PHPSynonymsParser::parseFile(dirname(__FILE__).'/lo-oo-ressources-linguistiques-fr-v4-11.oxt', 'fr'));