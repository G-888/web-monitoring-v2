@echo off
echo Installing Server Monitor Agent dependencies...
cd /d "%~dp0"
npm install

echo.
echo Starting Server Monitor Agent...
echo Press Ctrl+C to stop
echo.
npm start