<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Person Information CSV Generator</title>
    <link rel="stylesheet" href="mystyle.css">
</head>
<body>
<form method="POST" action="">
    <br>
    <h2>PERSONAL INFORMATION CSV GENERATOR</h2><br>
    <label for="amount">Number of entries to generate:</label>
    <input type="number" id="amount" name="amount" min="1" required><br>
    <br>
    <input type="submit" name="submit" value="Generate CSV file"><br>
    <input type="reset" name="cancel" value="Cancel"><br>
    <br>
</form>
</body>
</html>

<?php
//Set the time limit to infinite - needed when generating large amounts of data
set_time_limit(0);
///GLOBAL VARIABLES
//NAMES array
$NAMES = array(
    "Luke", "Layla", "Lucius", "Lucy", "Andre",
    "Kevin", "John", "Jack", "Jill", "Jackal",
    "Hyde", "Lector", "Hector", "Frank", "Susan",
    "Karen", "Winston", "Edgar", "Allan", "Michael"
);
//SURNAMES array
$SURNAMES = array(
    "McNeel", "Adams", "Brits", "Louw", "Ackermann",
    "Simpson", "Granger", "Thorn", "Hooman", "Delarey",
    "Smith", "Brown", "Rodriguez", "Lopez", "Johnson",
    "Miller", "Garcia", "Hernandez", "Wilson", "Gjoni"
);
///POST
//submit for the amount of entries to be generated
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit'])) {
    $numRecords = $_POST["amount"];
    //check if the amount needed is less than 50 000, if so, just generate that amount and save it to CSV file
    if($numRecords <= 50000){
        $entries = genEntries($numRecords);
        saveToCSV($entries);
        return;
    }
    $numCycles = (int) ($numRecords / 50000);
    for($i = 0; $i < $numRecords-50000; $i+=50000){
        $entries = genEntries(50000);
        saveToCSV($entries);
    }
    $remainder = $numRecords % 50000;
    $entries = genEntries($remainder);
    saveToCSV($entries);
}
///FUNCTIONS
//save the given entries to a CSV file and store it in the user's downloads folder
function saveToCSV($entries){
    //get the user's Downloads folder path
    $downloadsFolder = getenv("HOME")."/Downloads";
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $downloadsFolder = getenv("USERPROFILE")."\\Downloads";
    }
    //set the file path
    $filePath = $downloadsFolder . DIRECTORY_SEPARATOR . "output.csv";
    try{
        //open the file and check that it is opened
        $file = fopen($filePath, 'w');  //'w' mode will overwrite any existing file
        if ($file === false) {
            echo "Error: Unable to open file for writing."."<br>";
            return;
        }
        //set the header
        fputcsv($file, ["ID", "Name", "Surname", "Initial", "Age", "Date of Birth"]);
        //write the entries
        $counter = 1;
        foreach($entries as $entry){
            $dobString = $entry["dob"]->format("d/m/Y");
            fputcsv($file, [$counter++, $entry["name"], $entry["surname"], $entry["initial"], $entry["age"], $dobString]);
        }
        fclose($file);
        echo "File saved to ".$filePath."<br>";
    }catch (Exception $e){
        echo "Error: Unable to open file for writing."."<br>";
        return;
    }
}
//calculates the age of a person with the specified date of birth
function calcAge($dob):int{
    $now = new DateTime();
    $age = $now->diff($dob);
    return $age->y;
}
//generates a random date of birth within the last 151 years
function genDOB():DateTime{
    $now = new DateTime();
    $difference = rand(1, 150);
    $year = $now->Format('Y') - $difference;
    $month = rand(1, 12);
    $day = 0;
    switch ($month){
        case 12:
        case 10:
        case 8:
        case 7:
        case 5:
        case 3:
        case 1:
            $day = rand(1, 31);
            break;
        case 2:
            if($year % 400 == 0){
                $day = rand(1, 29);
            }else if($year % 100 != 0 && $year % 4 == 0){
                $day = rand(1, 29);
            }else{
                $day = rand(1, 28);
            }
            break;
        case 11:
        case 9:
        case 6:
        case 4:
            $day = rand(1, 30);
            break;
    }
    return new DateTime($day.'-'.$month.'-'.$year);
}
//generates an amount of entries, the amount being based on user input
function genEntries($amount):array{
    global $NAMES;
    global $SURNAMES;
    $entries = [];
    $count = 0;
    while($count < $amount){
        $randomIndex = array_rand($NAMES);
        $name = $NAMES[$randomIndex];
        $initial = substr($name, 0, 1);
        $randomIndex = array_rand($SURNAMES);
        $surname = $SURNAMES[$randomIndex];
        $dob = genDOB();
        $age = calcAge($dob);
        $entry = [
            "name" => $name,
            "surname" => $surname,
            "initial" => $initial,
            "age" => $age,
            "dob" => $dob->format('d/m/Y')
        ];
        if(!in_array($entry, $entries)) $entries[$count++] = $entry;
    }
    return $entries;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CSV File Saver</title>
    <link rel="stylesheet" href="mystyle.css">
</head>
<body>
<form method="POST" action="" enctype="multipart/form-data">
    <br>
    <h2>CSV FILE SAVER</h2><br>
    <label for="fileInput">Input CSV file:</label>
    <input type="file" id="fileInput" name="fileInput" accept=".csv" required><br>
    <br>
    <input type="submit" name="saveToSQLite" value="Submit CSV file to SQLite DB"><br>
    <br>
</form>
</body>
</html>

<?php
///POST
//submit for saving to SQLite DB
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['saveToSQLite'])) {
    //check uploaded file for errors
    if (isset($_FILES['fileInput']) && $_FILES['fileInput']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['fileInput']['tmp_name'];
        $fileName = $_FILES['fileInput']['name'];
        $fileSize = $_FILES['fileInput']['size'];
        $fileType = $_FILES['fileInput']['type'];
        $fileNameComps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameComps));
        //check if the uploaded file is a CSV
        if ($fileExtension === 'csv') {
            //set up the database
            $pdo = setupDB();
            //check how many files are in the DB
            $numFilesInDB = howManyFiles($pdo);
            echo "There are ".$numFilesInDB." files in the database"."<br>"."Deleting all records now"."<br>";
            //delete all the records in the DB
            clearDB($pdo);
            //check how many files are in the DB
            $numFilesInDB = howManyFiles($pdo);
            echo "There are ".$numFilesInDB." files in the database"."<br>";
            if ($pdo) {
                //save CSV to SQLite DB
                saveCSVToSQLite($fileTmpPath, $pdo);
            }
        } else echo "Error: Uploaded file is not a CSV";
    } else echo "Error: No file uploaded or upload error:"."<br>".$_FILES['fileInput']['error']['message'];
}
///FUNCTIONS
//set up the SQLite DB
function setupDB():PDO{
    try {
        //path to SQLite DB file
        $dbPath = __DIR__ . '/csv_storage.db';
        //connect to SQLite DB
        $pdo = new PDO('sqlite:' . $dbPath);
        //set error mode to exceptions
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        //create 'csv_import' table if it does not exist
        //should I auto increment, or should I rather just use the generated ID?
        $sql = "CREATE TABLE IF NOT EXISTS csv_import (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    surname TEXT NOT NULL,
                    initial TEXT NOT NULL,
                    age INTEGER NOT NULL,
                    dob TEXT NOT NULL
                )";
        $pdo->exec($sql);
        echo "Database setup successful and 'csv_import' table is ready"."<br>";
        return $pdo;
    } catch (PDOException $e) {
        echo "SQLite DB setup failed: " . $e->getMessage() . "<br>";
        return false;
    }
}
//save CSV file to SQLite DB
function saveCSVToSQLite($csvFilePath, $pdo){
    try {
        //display how many files there are in the db and store the amount
        $filesAtStart = howManyFiles($pdo);
        echo "Before attempting to insert new data, there are ".$filesAtStart." files in the database"."<br>";
        //open CSV file for reading
        if (($csvFile = fopen($csvFilePath, 'r')) !== false) {
            $header = fgetcsv($csvFile, 50, ",");
            //create SQL insert statement
            $sql = "INSERT INTO csv_import (id, name, surname, initial, age, dob) VALUES (:id, :name, :surname, :initial, :age, :dob)";
            $stmt = $pdo->prepare($sql);
            //loop through each row of the CSV file
            while (($data = fgetcsv($csvFile, 50, ",")) !== false) {
                //save CSV data to variables
                $id = (int)$data[0];
                $name = $data[1];
                $surname = $data[2];
                $initial = $data[3];
                $age = (int)$data[4];
                $dob = $data[5];
                //bind parameters and execute statement
                $stmt->bindParam(':id', $id);
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':surname', $surname);
                $stmt->bindParam(':initial', $initial);
                $stmt->bindParam(':age', $age);
                $stmt->bindParam(':dob', $dob);
                $stmt->execute();
                //display how many files have been uploaded every 10 000
                if($id % 10000 == 0) echo "Uploaded data entry: ".$id."<br>";
            }
            fclose($csvFile);
            echo "CSV file data has been imported into the SQLite DB"."<br>";
            //request the files storage in the db and return how many there are
            $filesAtEnd = howManyFiles($pdo);
            echo "After inserting new data, there are ".$filesAtEnd." files in the database"."<br>";
            //output how many files were uploaded
            echo "A total of ".($filesAtEnd-$filesAtStart)." files were uploaded"."<br>";
        } else {
            echo "Error: Unable to open the uploaded CSV file"."<br>";
        }
    } catch (PDOException $e) {
        echo "Data insertion failed: " . $e->getMessage() . "<br>";
    }
}
function howManyFiles($pdo):int{
    $sql = "SELECT COUNT(*) FROM csv_import;";
    $stmt = $pdo->query($sql);
    return (int)$stmt->fetchColumn();
}
function clearDB($pdo){
    $sql = "DELETE FROM csv_import;";
    $pdo -> exec($sql);
}
?>

