## Task-manager with pure php 8.3 and React + Antd (node version 23.1.0)
### Установка:
```
git clone https://github.com/reasonlive/task-manager.git
cd task-manager && composer install
cd ../frontend && npm install
cd ../
composer db:migrate
composer db:seed
```
### Запуск:
```
composer serve
cd frontend && npm run dev
```
Бекенд по ссылке [http://localhost:8000](http://localhost:8000) (Admin panel) <br>
Фронтенд по ссылке [http://localhost:3000](http://localhost:3000) (Client side)

### Данные для доступа:
```
Admin panel:
email: admin@example.com
password: admin
```
```
Client side:
email: user@example.com
password: user
```
