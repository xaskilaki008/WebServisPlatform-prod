cd /d C:\xampp\htdocs\webservis\WebServisPlatform
set /p msg=Введите сообщение коммита:
git add .
git commit -m "%msg%"
git push