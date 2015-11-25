# Git2FTP

## Synopsis

Git2FTP is a tool for maintaining Git projects hosted on servers which don't provide SSH access (and thus the 'git push' command cannot be used).

The *git2ftp.sh* bash script compares the changes between the newest commit and the commit stored on the server's *commit_production.txt text* file (remember to first download the file via the web interface!).
The web app can then be used to upload only the modified files to the chosen server.
This way, the project can continue to be maintained using Git and the last commit uploaded to the server is always known.

## Motivation

I host my website on a cheap server which only provides FTP access. This meant that I had to upload all files by hand, even though I use Git for all my projects.
I needed a tool which would allow me to bypass this limitation and work as if I had Git configured on my production environment.

## Installation

A *git2ftp* folder should be created within the root directory of your project, containing all git2ftp files.

Within the *git2ftp* folder, the following files should contain the name of the commit which represents the state of your files on the remote server:
* commit.txt
* commit_production.txt

Furthermore, the *commit_production.txt* file should exist on the remote server for the "*Download commit info from remote*" function to work.

Once this is done, run *git2ftp.sh* from within your project directory. A *diff.txt* file will be created, which will be used by the Git2FTP web app to decide which files should be uploaded to the remote server.
