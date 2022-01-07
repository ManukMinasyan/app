# Test Application Documentation

## Steps for run test task
Customers.csv and errors.csv files stored in database/seeders directory

### Step #1
Go to the project directory and run the command below:
```
$ composer install
```

### Step #2
```
$ php artisan migrate
```

### Step #3
```
$ php artisan customers:sync
```
