@echo off
REM Kraft Liner — ดึง ERP อัตโนมัติ (ตั้ง Task Scheduler รันไฟล์นี้ทุกวัน 02:00)
cd /d "D:\report_data_salescustomer\backend\scripts"
"C:\xampp\php\php.exe" "D:\report_data_salescustomer\backend\scripts\sync_daily.php" >> "D:\report_data_salescustomer\backend\logs\sync-task.log" 2>&1
exit /b %ERRORLEVEL%
