To run:
- Open command line and change directory to wherever "gen_info.php" is.
- Start the php server by running "php -S localhost:8080" (8080 can be changed)
- Open web browser and go to "http://localhost:8080/gen_info.php"
Pre-requisites:
- This project was coded in PHP v7.4.9.
- Have PHP installed and set up to be able to use SQLite3.
- Make sure that your maximum file upload size, in the php.ini file, is set to something big enough to accomodate 1 000 000 line csv file.
- It is a good idea to increase the "memory_limit" variable, in the php.ini file under "Resource Limits", to "256M" or "512M" to allow for more memory usage with large scripts.
