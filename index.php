<?php
    /**
     * Git2Ftp
     * 
     * @author Daniel Frąk
     */

    set_time_limit(0);
    session_start();

    require_once 'config.php';
    require_once "Models/FTPClient.php";
    require_once "Models/MessageHandler.php";
    require_once "Models/DiffParser.php";

    //Create the Message Handler to handle local and session messages
    $msg_handler = new MessageHandler();
    
    //Download current commit info from server 
    if(isset($_GET['action']) && $_GET['action']=='get_commit'){
        if(isset($_GET['ftp'])){
            $ftpName = $_GET['ftp'];
            if(in_array($ftpName, array_keys($ftp))){                
                $ftp_client = new FTPClient($ftp[$ftpName]['host'], $ftp[$ftpName]['port'], $ftp[$ftpName]['usr'], $ftp[$ftpName]['pwd'], $remotePath);
                //Download commit info file from remote:
                try {
                    $ftp_client->connect();
                    $contents_on_server = $ftp_client->getFileList('git2ftp');
                    if(in_array($remotePath."git2ftp/commit_remote.txt", $contents_on_server)){
                        if($ftp_client->downloadFile("commit_production.txt", "git2ftp/commit_remote.txt")){
                            $msg_handler->addSuccessMessage("Successfuly downloaded info file from remote.");
                        } else {
                            $msg_handler->addErrorMessage("The info file could not be downloaded from the remote server.");
                        }
                    } else {
                        $msg_handler->addWarningMessage("The info file is not present on remote server.");
                    }
                }
                catch(FTPClientException $e){
                    $msg_handler->addErrorMessage($e->getMessage());
                }
                
            } else {
                $msg_handler->addErrorMessage("Selected FTP doesn't exist in config.");
            }
        } else {
            $msg_handler->addErrorMessage("FTP not chosen.");
        }
        
        $msg_handler->update(); //Update the SESSION messages variable
        header("location:" . $main_url . "index.php");
        exit;
    }

    //Try to parse the diff file (if one exists)
    $diff_parser = new DiffParser();
    try{
        $parse_result = $diff_parser->tryLoadFile($diffFile);
        if($parse_result !== false){
            if($parse_result === true){ //Diff file found and processed
                $message = "<strong>diff.txt file found. Contents:</strong><i>";
                foreach($diff_parser->getDiffFiles() as $line){
                    $message .= "<br>".$line;
                }
                $message .= "</i>";
                $msg_handler->addInfoMessage($message, true);
            } else if($parse_result === -1){ //Diff file found but empty
                $msg_handler->addWarningMessage("<strong>".$diffFile."</strong> file is empty. Run <strong>{$scriptName}</strong>.", true);
            } else { //Diff file found but already processed ($parse_result === 0)
                $msg_handler->addInfoMessage("<strong>".$diffFile."</strong> file found, but already processed.<br>", true);
            }
        } else {
            $msg_handler->addWarningMessage("<strong>".$diffFile."</strong> file not found. Run <strong>{$scriptName}</strong>.", true);
        }
    }
    catch(DiffParserException $e){
        $msg_handler->addErrorMessage($e->getMessage());
    }

    //Get local commit info
    if (file_exists("commit.txt")) {
        $commit = file_get_contents("commit.txt");
        $msg_handler->addInfoMessage("<strong>Local commit:</strong> ".$commit, true);
    }
    //Get production commit info
    if (file_exists("commit_production.txt")) {
        $masterCommit = file_get_contents("commit_production.txt");
        $msg_handler->addInfoMessage("<strong>Production commit:</strong> ".$masterCommit, true);
    }

    //Display commit comparison result
    if($commit == $masterCommit){
        if($commit==""){
            $msg_handler->addWarningMessage("<strong>DIFF</strong> and <strong>MASTER</strong> are empty. Prepare them by pasting the initial commit hash into commit.txt and commit_production.txt <strong>BEFORE</strong> running <strong>{$scriptName}</strong>.", true);
        } else {
            $msg_handler->addWarningMessage("<strong>DIFF</strong> and <strong>MASTER</strong> commits are the same! No need to reupload.", true);
        }
    } else {
        $msg_handler->addInfoMessage("<strong>DIFF</strong> and <strong>MASTER</strong> commits are different. Upload suggested.", true);
    }

    //Upload files to remote server
    if ( (isset($_POST["action"]) && $_POST["action"] == "process" ) || (isset($_GET['action']) && $_GET['action']=='upload') )
    {
        // misc internal vars
        $linesSubmited = 0;
        $files2Upload = 0;
        $filesUploaded = 0;

        $continue = true;

//        $diff_parser = new DiffParser();
        if(!isset($_POST['sendFromText'])){            
            try{
                $parse_result = $diff_parser->tryLoadFile($diffFile);
                if($parse_result !== false){
//                    if($parse_result === true){ //Diff file found and processed
//                        $listLines = $diff_parser->getDiffFiles(); //TEMPORARY!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
//                    } else
                    if($parse_result === -1){ //Diff file found but empty
                        $msg_handler->addWarningMessage("<strong>".$diffFile."</strong> file is empty.", true);
                        $continue = false;
                    } else { //Diff file found but already processed ($parse_result === 0)
                        $msg_handler->addInfoMessage("<strong>".$diffFile."</strong> file was already processed.<br>", true);
                        $continue = false;
                    }
                } else {
                    $msg_handler->addWarningMessage("<strong>".$diffFile."</strong> file not found.", true);
                    $continue = false;
                }
            }
            catch(DiffParserException $e){
                $msg_handler->addErrorMessage($e->getMessage());
            }
        } else {
            $diff_parser->setDiffFiles(explode("\n", $_POST["listOfFiles"]));
        }

        if($continue){
            if($diff_parser->trySanitize($linesSubmited)) //If there are files to upload
            {
                $ftpName = $_POST["server"] ? $_POST["server"] : $_GET['ftp'];
                
                $ftp_client = new FTPClient($ftp[$ftpName]['host'], $ftp[$ftpName]['port'], $ftp[$ftpName]['usr'], $ftp[$ftpName]['pwd'], $remotePath);
                
                try {
                    $ftp_client->connect();
                    //File upload
                    foreach ($diff_parser->getSanitizedLines() as $fileToUpload )
                    {
                        $fileToUploadLocalPath = $localPath . $fileToUpload;
                        $fileToUploadRemotePath = $remotePath . $fileToUpload;

                        $files2Upload++;
                        $upload = $ftp_client->uploadFile($fileToUploadRemotePath, $fileToUploadLocalPath); //Upload the file
                        if (!$upload ){ //Couldn't upload
                            $failedUploadFiles[] = $fileToUpload;
                        }
                        else {
                            $filesUploaded++;
                        }
                    }

                    $ftp_client->disconnect();

                    if (empty($failedUploadFiles) ){ //If every file uploaded successfully
                        $msg_handler->addSuccessMessage("Upload successful. Lines submitted: <b>$linesSubmited</b>. Files found: <b>$files2Upload</b>. Files uploaded: <b>$filesUploaded</b>.");

                        if($diff_parser->didFileLoad()){
                            $diff_parser->fileSaveAsProcessed();
                        }

                        if(isset($commit)){
                            file_put_contents("commit_production.txt", $commit);
                        }
                    }
                    else {
                        if (!empty($failedUploadFiles) )
                        {
                            $message = "FAILED UPLOAD FILES: <br><br>";
                            $message .= "<pre>";
                            $message .= print_r($failedUploadFiles, true);
                            $message .= "</pre>";
                            $msg_handler->addErrorMessage($message);
                        }
                    }
                }
                catch(FTPClientException $e){
                    $msg_handler->addErrorMessage($e->getMessage());
                }
            }
            else
            {
                $msg_handler->addSuccessMessage("No files to upload.");
            }
        }

        $msg_handler->update();
        header("location:".$main_url."index.php");
        exit;
    }
?>

<!doctype html>
<html>
<head>
  <meta http-equiv="content-type" content="text/html; charset=utf-8">

  <title>GIT2FTP</title>

  <link rel="stylesheet" href="css/style.css?v=1.0">
  <link href='http://fonts.googleapis.com/css?family=Open+Sans:400italic,700italic,400,700,300' rel='stylesheet' type='text/css'>  
  <link href='http://fonts.googleapis.com/css?family=Lato:300,400,700,900,400italic,700italic,900italic&subset=latin-ext' rel='stylesheet' type='text/css'>

  <!--[if IE]>
  <link href='http://fonts.googleapis.com/css?family=Open+Sans:400italic&subset=latin-ext,latin' rel='stylesheet' type='text/css'>
  <link href='http://fonts.googleapis.com/css?family=Open+Sans:700italic,400,700,300&subset=latin-ext,latin' rel='stylesheet' type='text/css'>
  <link href='http://fonts.googleapis.com/css?family=Open+Sans:400,700,300&subset=latin-ext,latin' rel='stylesheet' type='text/css'>
  <link href='http://fonts.googleapis.com/css?family=Open+Sans:700,300&subset=latin-ext,latin' rel='stylesheet' type='text/css'>
  <link href='http://fonts.googleapis.com/css?family=Open+Sans:300&subset=latin-ext,latin' rel='stylesheet' type='text/css'>
  <link href='http://fonts.googleapis.com/css?family=Open+Sans+Condensed:300&subset=latin-ext,latin' rel='stylesheet' type='text/css'>
  <link href='http://fonts.googleapis.com/css?family=Open+Sans+Condensed:700&subset=latin-ext,latin' rel='stylesheet' type='text/css'>
  
  <link href='http://fonts.googleapis.com/css?family=Lato:300&subset=latin-ext' rel='stylesheet' type='text/css'>
  <link href='http://fonts.googleapis.com/css?family=Lato:400&subset=latin-ext' rel='stylesheet' type='text/css'>
  <link href='http://fonts.googleapis.com/css?family=Lato:700&subset=latin-ext' rel='stylesheet' type='text/css'>
  <link href='http://fonts.googleapis.com/css?family=Lato:900&subset=latin-ext' rel='stylesheet' type='text/css'>
  <link href='http://fonts.googleapis.com/css?family=Lato:400italic&subset=latin-ext' rel='stylesheet' type='text/css'>
  <link href='http://fonts.googleapis.com/css?family=Lato:700italic&subset=latin-ext' rel='stylesheet' type='text/css'>
  <link href='http://fonts.googleapis.com/css?family=Lato:900italic&subset=latin-ext' rel='stylesheet' type='text/css'>
  <![endif]-->
  
  
  <!--[if lt IE 9]>
  <script src="http://html5shiv.googlecode.com/svn/trunk/html5.js"></script>
  <![endif]-->
  
  <script src="http://ajax.googleapis.com/ajax/libs/jquery/1.11.2/jquery.min.js"></script>
  <script src="js/script.js"></script>
</head>

<body>
    <div id="topmenu">
        <div class="wrapper-top">
            <div class="menu-opt" style="margin-right: 20px">
                <span class="logo">GIT UPLOADER</span>
                <!--<span style="font-family: 'Lato', sans-serif; cursor: default; font-size: 12px; position: relative; top: 14px; font-style: italic;">BY DANIEL FRĄK</span>-->
            </div>
            <div class="menu-opt">
                <p><a href="?action=get_commit&ftp=production" class="button">DOWNLOAD COMMIT INFO FROM REMOTE</a></p>
            </div>
            <div class="menu-opt">
                <p><a href="?action=upload&ftp=production" class="button">UPLOAD TO PRODUCTION</a></p>
            </div>
        </div>
    </div>
    <div class="wrapper wrapper-round">
        <div style="cursor:default">
            <h1 style="text-align: center">GIT UPLOADER</h1>
            <h2 style="text-align: center">Project: <?= $project_name ?></h2>
        </div>
        <br>

        <?php
            $messages = $msg_handler->getMessages();
            if (isset($messages) )
            {
                foreach($messages as $msg):
                    if(isset($msg) && is_array($msg)):
        ?>
                        <p class="<?php echo $msg[0]; ?>"><?php echo $msg[1]; ?></p>
        <?php
                    endif;
                endforeach;
            }
        ?>
        <br>
        <p><strong>USAGE:</strong></p>
        <p>Run the <strong><i><?= $scriptName ?></i></strong> script to generate a new diff.txt file (or paste a git diff result) then click on "Upload".</p><br>
        
        <form id="form" action="index.php" method="POST">
            <input type="checkbox" value="1" name="sendFromText" id="sendFromText"><label for="sendFromText">Override diff text file</label>
            <textarea id="listOfFiles" name="listOfFiles" placeholder="Run 'git diff --name-status' then paste result here"></textarea>

            <br>
            <div style="text-align: center">
                <p>
                    <select name="server" style="width: 100%;">
                        <?php
                            foreach ($ftp as $ftpName=>$ftpData):
                        ?>
                        <option <?= (isset($_POST["server"]) && $_POST["server"] == $ftpName) ? 'selected="selected"' : '' ?> value="<?= $ftpName ?>">Upload to: <?= $ftpData['label'] ?></option>
                        <?php endforeach; ?>                
                    </select>
                </p>

                <p><a href="?action=get_commit&ftp=production" class="button button-big" style="width: 100%;">DOWNLOAD COMMIT INFO FROM REMOTE</a></p>
                
                <p><input class="button button-big" style="width: 100%;" type="submit" value="Upload"></p>
                <br>
            </div>
            <input type="hidden" name="action" value="process">
        </form>
        
        <div id="footer">
            Inspired by: <a href="https://code.google.com/p/upload-git-diff-with-ftp/">https://code.google.com/p/upload-git-diff-with-ftp/</a>
        </div>
    </div>
</body>
</html>