#!/bin/bash
gnome-terminal --tab -e "php -f VideoProcess_ExtractStillImages.php" --geometry 80x20+100+200
gnome-terminal --tab -e "php -f VideoProcess_Triangulization.php" --geometry 80x20+100+600
gnome-terminal --tab -e "php -f VideoProcess_Finalization.php" --geometry 80x20+800+600
