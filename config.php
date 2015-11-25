<?php
    $project_name = "Project name"; //The project name
    $main_url = "http://localhost/git2ftp/"; //The location of git2ftp
    
    // FTP Definitions:
    $ftp = array (
        //Multiple upload destinations not yet supported, as right now commits are only compared for the production environment!
        "production" => array(
            "label" => "Production",
            "host" => 'somehost.com',
            "port" => 21, 
            "usr" => 'root',
            "pwd" => 'root' 
        )
    );

    // Misc Definitions:
    $localPath = "/usr/local/path"; //The local path of the project
    $remotePath = "/_public_html/"; //The path of the project within the remote server
    
    $diffFile = "diff.txt"; //Name of the diff text file

    $sanitization_regex = '/^(M|A)\s+/'; //REGEX used when sanitizing the lines of the diff text
    
    $scriptName = "git2ftp.sh"; //The name of the git2ftp shell script
    
    //constants
    const MSG_PROCESSED = "PROCESSED";