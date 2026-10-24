@echo off
rem Abre a previa do Cerveja-Clube no navegador.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0.previa\abrir-previa.ps1"
if errorlevel 1 pause
