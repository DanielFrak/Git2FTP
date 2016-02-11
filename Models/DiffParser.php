<?php

    class DiffParserException extends Exception {}

    /**
     * Handles parsing of the git diff result
     */
    class DiffParser {
        
        const MSG_PROCESSED = "PROCESSED";
        
        protected $diffFile = "";
        protected $listLines = array(); //List of diff files
        protected $sanitizedLines = array(); //List of diff files
        protected $fileContents;
        protected $processed = false;
                
        function tryLoadFile($diffFile){
            $this->diffFile = $diffFile;
            if (file_exists($diffFile)) {
                $this->fileContents = file_get_contents($diffFile);
                
                if($this->fileContents){ //If the diff file is not empty
                    $this->listLines = explode("\n", $this->fileContents);
                    if($this->listLines[sizeof($this->listLines)-1]==self::MSG_PROCESSED){
                        $this->processed = true;
                        return 0;
                    } else {
                        return true;
                    }
                } else {
                    return -1;
                }
            } else {
                throw DiffParserException("<strong>".$diffFile."</strong> file is empty. Run <strong>{$scriptName}</strong>.");
            }
            return false;
        }
        
        function getDiffFiles(){
            return $this->listLines;
        }
        function setDiffFiles($diffFiles){
            $this->listLines = $diffFiles;
        }
        function getSanitizedLines(){
            return $this->sanitizedLines;
        }
        
        function trySanitize(&$linesSubmited){
            if (!empty($this->listLines)) {
                // remove and sanitize all the lines:
                foreach ($this->listLines as $line) {
                    $line = preg_replace($sanitization_regex, '', trim($line));

                    if (!$line) {
                        continue;
                    }

                    $linesSubmited++;

                    if (file_exists($localPath . $line)) { //If file exists locally
                        $this->sanitizedLines[] = $line;
                    }
                }
            }
            
            if ( isset($this->sanitizedLines) && !empty($this->sanitizedLines) ){
                return true;
            }
            
            return false;
        }
        
        function didFileLoad(){
            return isset($this->fileContents);
        }
        
        function fileIsProcessed(){
            return $this->processed;
        }
        
        function fileSaveAsProcessed(){
            if(!$this->fileIsProcessed()){
                $this->fileContents.="\n".self::MSG_PROCESSED;
                file_put_contents($this->diffFile, $this->fileContents);
            }
        }
    }