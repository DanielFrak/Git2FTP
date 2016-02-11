<?php
    
    class FTPClientException extends Exception {}

    /**
     * Handles FTP functionality
     */
    class FTPClient {
        protected $remotePath = "";
        protected $conn_id = NULL;
        protected $ftpHost = "";
        protected $ftpPort = 21;
        protected $ftpUsr = "";
        protected $ftpPass = "";
        
        function __construct($ftpHost, $ftpPort, $ftpUsr, $ftpPass, $remotePath = "") {
            $this->ftpHost = $ftpHost;
            $this->ftpPort = $ftpPort;
            $this->ftpUsr = $ftpUsr;
            $this->ftpPass = $ftpPass;
            $this->remotePath = $remotePath;
        }
        
        function setRemotePath($newRemotePath){
            $this->remotePath = $newRemotePath;
        }
        function getRemotePath(){
            return $remotePath;
        }
        
        function setFTPHost($newFtpHost){
            $this->ftpHost = $newFtpHost;
        }
        function getFTPHost(){
            return $this->ftpHost;
        }
        
        function setFTPPort($newFtpPort){
            $this->ftpPort = $newFtpPort;
        }
        function getFTPPort(){
            return $this->ftpPort;
        }
        
        function setFTPUsr($newFtpUsr){
            $this->ftpUsr = $newFtpUsr;
        }
        function getFTPUsr(){
            return $this->ftpUsr;
        }
        
        function setFTPPass($newFtpPass){
            $this->ftpPass = $newFtpPass;
        }
        function getFTPPass(){
            return $this->ftpPass;
        }
        
        /**
         * Connects and logs into an FTP server
         * @throws FTPClientException
         */
        function connect(){
            if($this->conn_id = ftp_connect($this->ftpHost, $this->ftpPort)){
                if(ftp_login($this->conn_id, $this->ftpUsr, $this->ftpPass)){ //Log in
                    
                } else {
                    throw new FTPClientException('Failed to log in to FTP.');
                }
            } else {
                throw new FTPClientException('Could not establish FTP connection.');
            }
        }
        
        /**
         * Returns a list of files in a given directory
         * @param string $directory
         * @return array
         */
        function getFileList($directory){
            return ftp_nlist($this->conn_id, $this->remotePath.$directory);
        }
        
        /**
         * Downloads a $remoteFile to $localFile
         * @param string $localFile
         * @param string $remoteFile
         * @return bool
         */
        function downloadFile($localFile, $remoteFile){
            return ftp_get($this->conn_id, $localFile, $this->remotePath.$remoteFile, FTP_ASCII); //Get file from FTP
        }
        
        function uploadFile($remote_file, $local_file){
            return ftp_put($this->conn_id, $remote_file, $local_file, FTP_ASCII); //Upload the file
        }
        
        /**
         * Close the FTP stream
         */
        function disconnect(){
            ftp_close($this->conn_id);
        }
    }