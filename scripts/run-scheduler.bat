@echo off
rem Lance le scheduler Laravel (appelé chaque minute par la tâche Windows « Fleet Energy Analytics - Laravel Scheduler »).
rem Dossier du projet = dossier parent de ce script : fonctionne quel que soit l'emplacement du clone.
rem PHP : variable FEA_PHP si définie, sinon le « php » du PATH.
cd /d "%~dp0.."
if not defined FEA_PHP set "FEA_PHP=php"
"%FEA_PHP%" artisan schedule:run >> storage\logs\scheduler_task.log 2>&1
echo ---- >> storage\logs\scheduler_task.log
