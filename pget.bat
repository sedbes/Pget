@echo off
REM 检查参数中是否包含 --directory-prefix
setlocal enabledelayedexpansion
set hasprefix=0
for %%a in (%*) do (
    echo %%a | find "--directory-prefix" >nul
    if not errorlevel 1 set hasprefix=1
)
if %hasprefix%==0 (
    ::使用dos变量时不能待引号，但是其它参数的值字符串必须带引号。因为dos的传参方式问题导致
    php.exe C:\workspace\wwwcrawler\pget.php --directory-prefix=%cd% %*
) else (
    php.exe C:\workspace\wwwcrawler\pget.php %*
)
endlocal