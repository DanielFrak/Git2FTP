<?php
    
    /**
     * Handles local and session messages
     */
    class MessageHandler {
        private $messages_session = array();
        private $messages_local = array();
        
        const successMsg = 'successMsg';
        const infoMsg = 'infoMsg';
        const warningMsg = 'warningMsg';
        const errorMsg = 'errorMsg';
        
        function __construct(){
            //Get session messages and delete them from the session
            if(isset($_SESSION['messages'])){
                $this->messages_session = $_SESSION['messages'];
                $_SESSION['messages'] = null;
            } else {
                $this->messages_session=array();
            }
        }
        
        /**
         * Adds a message. Set $local as true to add a local message.
         * @param string $messageType
         * @param string $message
         * @param bool $local
         */
        function addMessage($messageType, $message, $local = false){
            if($local){
                $this->messages_local[] = [$messageType, $message];
            } else {
                $this->messages_session[] = [$messageType, $message];
            }
        }
        
        function addSuccessMessage($message, $local = false){
            $this->addMessage(self::successMsg, $message, $local);
        }
        function addInfoMessage($message, $local = false){
            $this->addMessage(self::infoMsg, $message, $local);
        }
        function addWarningMessage($message, $local = false){
            $this->addMessage(self::warningMsg, $message, $local);
        }
        function addErrorMessage($message, $local = false){
            $this->addMessage(self::errorMsg, $message, $local);
        }
        
        /**
         * Updates the SESSION 'messages' variable.
         * 
         * Must be called before redirecting.
         */
        function update(){
            $_SESSION['messages'] = $this->messages_session;
        }
        
        /**
         * Returns all messages (both local and session)
         * @return type
         */
        function getMessages(){
            return 
                $messages = array_merge(
                    $this->messages_session,
                    $this->messages_local
                )
            ;
        }
    }