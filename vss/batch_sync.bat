@echo off
cd /d "d:\Downloads\XAMMP\htdocs\vss"
php sync_biometric.php
echo Biometric sync completed at %date% %time%
pause