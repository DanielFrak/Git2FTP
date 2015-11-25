# Run this script from within your project directory.

# To run when in a /git2ftp folder within project:
# cd ${0%/*}
# cd ..

# COLORS:
NC='\033[0m' # No Color
grey='\033[1;30m'
red='\033[1;31m'
green='\033[1;32m'
orange='\033[0;33m'
yellow='\033[1;33m'

#DEFAULT VARS:
commit1_def=HEAD^1
commit2_def=HEAD
COMMIT1=${1:-$commit1_def}
COMMIT2=${2:-$commit2_def}

commit_file=git2ftp/commit.txt
prod_commit_file=git2ftp/commit_production.txt

#COMMIT INFO:
LASTCOMMIT=${1:-$(<$commit_file)}
#git rev-parse HEAD > $commit_file
#CURRCOMMIT=$(<$commit_file)
CURRCOMMIT=$(git rev-parse HEAD)

#PRINTING INFO:
echo -e "\n${grey}Remember to run this script from your project directory.${NC}"
echo -e "\n${grey}Last commit:\t$LASTCOMMIT"
echo -e "Current commit:\t$CURRCOMMIT${NC}"

#GIT DIFF LOGIC:
if [ "$LASTCOMMIT" == "$CURRCOMMIT" ]; then
	echo -e "\n${green}The DIFF file is up to date (according to commit.txt). Doing nothing.${NC}"

	PROD_COMMIT=$(<$prod_commit_file)
	if [ "$CURRCOMMIT" == "$PROD_COMMIT" ]; then
		echo -e "\n${green}Production environment is up to date.${NC}"
	else
		echo -e "\n${red}Production environment is not up to date.${NC}"
	fi
else
	COMMIT1=${1:-$LASTCOMMIT}
	COMMIT2=${2:-$CURRCOMMIT}

	echo -e "\n${grey}Commit1:\t$COMMIT1"
	echo -e "Commit2:\t$COMMIT2${NC}"

	echo -e "\n${yellow}Preparing file...${orange}\n"

	if git diff --name-status $COMMIT1 $COMMIT2 > git2ftp/diff.txt; then
		echo "$CURRCOMMIT" > "$commit_file"
		echo -e "${green}Done.${NC}"
	else
		echo -e "\n${red}*gasp* An error occured!${NC}"
	fi
fi
