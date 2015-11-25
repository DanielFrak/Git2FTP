<?php
    /**
     * Git2Ftp
     * 
     * @author Daniel Frąk
     */

    set_time_limit(0);
    session_start();

    require_once 'config.php';

    //Get session messages and delete them from the session
    if(isset($_SESSION['messages'])){
        $messages_session = $_SESSION['messages'];
        $_SESSION['messages'] = null;
    } else {
        $messages_session=array();
    }
    //END session messages

    //Download current commit info from server 
    if(isset($_GET['action']) && $_GET['action']=='get_commit'){
        if(isset($_GET['ftp'])){
            $ftpName = $_GET['ftp'];
            if(in_array($ftpName, array_keys($ftp))){
                //Download commit info file from remote:
                if($conn_id = ftp_connect($ftp[$ftpName]['host'], $ftp[$ftpName]['port'])){ //Connect to FTP
                    if(ftp_login($conn_id, $ftp[$ftpName]['usr'], $ftp[$ftpName]['pwd'])){ //Log in
                        $contents_on_server = ftp_nlist($conn_id, $remotePath."git2ftp/");
                        var_dump($contents_on_server);
                        if(in_array($remotePath."git2ftp/commit_remote.txt", $contents_on_server)){
                            if(ftp_get($conn_id, "commit_production.txt", $remotePath."git2ftp/commit_remote.txt", FTP_ASCII)){ //Get file from FTP
                                $messages_session[] = ['successMsg', "Successfuly downloaded info file from remote."];
                            }
                        } else {
                            $messages_session[] = ['warningMsg', "The info file is not present on remote server."];
                        }
                    } else {
                        $messages_session[] = ['errorMsg', "Failed to log in to FTP."];
                    }
                } else {
                    $messages_session[] = ['errorMsg', "Could not establish FTP connection."];
                }
            } else {
                $messages_session[] = ['errorMsg', "Selected FTP doesn't exist in config."];
            }
        } else {
            $messages_session[] = ['errorMsg', "FTP not chosen."];
        }
        $_SESSION['messages'] = $messages_session;
        header("location:" . $main_url . "index.php");
        exit;
    }

    if (file_exists($diffFile)) {
        $fileContents = file_get_contents($diffFile);
        if($fileContents){
            $listLines = explode("\n", $fileContents);
            if($listLines[sizeof($listLines)-1]==constant('MSG_PROCESSED')){
                $messages_local[] = ['infoMsg', "<strong>diff.txt</strong> file found, but already processed.<br>"];
            } else {
                $message = "<strong>diff.txt file found. Contents:</strong><i>";
                foreach($listLines as $line){
                    $message .= "<br>".$line;
                }
                $message .= "</i>";
                $messages_local[] = ['infoMsg', $message];
            }
        } else {
            $messages_local[] = ['warningMsg', "<strong>diff.txt</strong> file is empty. Run <strong>{$scriptName}</strong>."];
        }
        $listLines = explode("\n", $fileContents);
    } else {
        $messages_local[] = ['warningMsg', "<strong>diff.txt</strong> file not found. Run <strong>{$scriptName}</strong>."];
    }

    if (file_exists("commit.txt")) {
        $commit = file_get_contents("commit.txt");
        $messages_local[] = ['infoMsg', "<strong>DIFF commit:</strong> ".$commit];
    }
    if (file_exists("commit_production.txt")) {
        $masterCommit = file_get_contents("commit_production.txt");
        $messages_local[] = ['infoMsg', "<strong>Production commit:</strong> ".$masterCommit];
    }

    if($commit == $masterCommit){
        if($commit==""){
            $messages_local[] = ['warningMsg', "<strong>DIFF</strong> and <strong>MASTER</strong> are empty. Prepare them by pasting the initial commit hash into commit.txt and commit_production.txt <strong>BEFORE</strong> running <strong>{$scriptName}</strong>."];
        } else {
            $messages_local[] = ['warningMsg', "<strong>DIFF</strong> and <strong>MASTER</strong> commits are the same! No need to reupload."];
        }
    } else {
        $messages_local[] = ['infoMsg', "<strong>DIFF</strong> and <strong>MASTER</strong> commits are different. Upload suggested."];
    }

    if ( (isset($_POST["action"]) && $_POST["action"] == "process" ) || (isset($_GET['action']) && $_GET['action']=='upload') )
    {
        // misc internal vars
        $linesSubmited = 0;
        $files2Upload = 0;
        $filesUploaded = 0;

        $continue = true;

        if(!isset($_POST['sendFromText'])){
            if(file_exists($diffFile)){
                if($listLines[sizeof($listLines)-1]==constant('MSG_PROCESSED')){
                    $messages_session[] = ['errorMsg', "The diff file was already processed."];
                    $continue = false;
                }
            } else {
                $messages_session[] = ['errorMsg', "The diff file is empty."];
                $continue = false;
            }
        } else {
            $listLines = explode("\n", $_POST["listOfFiles"] );
        }

        if($continue){
            if ( !empty($listLines) )
            {
                // remove and sanitize all the lines:
                foreach($listLines as $line )
                {
                    $line = preg_replace ( $sanitization_regex, '', trim($line) );

                    if (!$line ){
                        continue;
                    }

                    $linesSubmited++;

                    if (file_exists( $localPath . $line ) ){ //If file exists locally
                        $sanitizedLines[] = $line;
                    }
                }

            }

            if ( isset($sanitizedLines) && !empty($sanitizedLines) ) //If there are files to upload:
            {
                $ftpName = $_POST["server"] ? $_POST["server"] : $_GET['ftp'];
                if($conn_id = ftp_connect($ftp[$ftpName]['host'], $ftp[$ftpName]['port'])){ //Connect to FTP
                    if(ftp_login($conn_id, $ftp[$ftpName]['usr'], $ftp[$ftpName]['pwd'])){ //Log in
                        //File upload
                        foreach ($sanitizedLines as $fileToUpload )
                        {
                            $fileToUploadLocalPath = $localPath . $fileToUpload;
                            $fileToUploadRemotePath = $remotePath . $fileToUpload;

                            $files2Upload++;
                            $upload = ftp_put($conn_id, $fileToUploadRemotePath, $fileToUploadLocalPath, FTP_ASCII); //Upload the file
                            if (!$upload ){ //Couldn't upload
                                $failedUploadFiles[] = $fileToUpload;
                            }
                            else {
                                $filesUploaded++;
                            }
                        }

                        ftp_close($conn_id); //Close the FTP stream

                        if (empty($failedUploadFiles) ){ //If every file uploaded successfully
                            $messages_session[] = ['successMsg', "Upload successful. Lines submitted: <b>$linesSubmited</b>. Files found: <b>$files2Upload</b>. Files uploaded: <b>$filesUploaded</b>."];

                            if(isset($fileContents)){
                                $fileContents.="\n".constant('MSG_PROCESSED');
                                file_put_contents($diffFile, $fileContents);
                            }

                            if(isset($commit)){
                                file_put_contents("commit_production.txt", $commit);
                            }
                        }
                        else {
                            if (!empty($failedUploadFiles) )
                            {
                                echo "FAILED UPLOAD FILES: <br><br>";
                                echo "<pre>";
                                print_r($failedUploadFiles);
                                echo "</pre>";
                                echo "<br><br>";
                                $message = "FAILED UPLOAD FILES: <br><br>";
                                $message .= "<pre>";
                                $message .= print_r($failedUploadFiles, true);
                                $message .= "</pre>";
                                $messages_session[] = ['errorMsg', $message];
                            }
                        }
                    } else {
                         $messages_session[] = ['errorMsg', "Failed to log in to FTP."];
                    }
                } else {
                    $messages_session[] = ['errorMsg', "Could not establish FTP connection."];
                }
            }
            else
            {
                $messages_session[] = ['successMsg', "No files to upload."];
            }
        }

        $_SESSION['messages'] = $messages_session;
        header("location:".$main_url."index.php");
        exit;
    }

    $messages = array_merge($messages_session, $messages_local);
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