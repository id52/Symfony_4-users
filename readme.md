# Deployment
## Install requirements 
```
composer install
```
## Create database

## Edit database settings
```
cp .env.dist .env
```

```
mcedit .env
```

```
DATABASE_URL=mysql://root:password@127.0.0.1:3306/craft
```

## Apply database migrations
```
php bin/console doctrine:migrations:migrate
```

## Load fixtures
```
php bin/console doctrine:fixtures:load
```

## Run the server
```
php -S 127.0.0.1:8000 -t public
```

## Open the browser
```
firefox 127.0.0.1:8000
```

### Demo user:

Login: admin1 <br>
Password: admin1 <br>

![List](images/list.png)
![Add](images/add.png)
![Login](images/login.png)







