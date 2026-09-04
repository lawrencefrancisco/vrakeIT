Here are the files for the VrakeIT project. To get it running on your machine, just follow these steps:

1. Extract the files:
Unzip the folder and place it into your local server's root directory (like htdocs if you are using XAMPP, or www if you are using WAMP/Laragon).

2. Set up the Database:

Start your local server (Apache and MySQL).

Open phpMyAdmin (usually http://localhost/phpmyadmin).

Create a new database named vrakeit.

Click on the new database, go to the Import tab at the top, select the database.sql file included in the folder, and click Go.

3. Check the Connection Credentials:
Open the includes/auth.php or includes/functions.php file in a code editor. Just double-check that the database connection settings match your local environment. Usually, for XAMPP/Laragon, it should look like this:

Host: localhost

Username: root

Password: '' (leave blank)

Database: vrakeit

Once that's done, you should be able to open http://localhost/vrakeIT in your browser and see the site. Let me know if you hit any errors!