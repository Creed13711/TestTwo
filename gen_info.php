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
//number of entities to do per batch when using batched generation
$ENTRIES_PER_BATCH = 25000;
///POST
//submit for the amount of entries to be generated
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit'])) {
    $numRecords = $_POST["amount"];
    generateAndSaveCSV($numRecords);
}
///FUNCTIONS
//main function to generate and save CSV file
function generateAndSaveCSV($totalNumEntries){
    global $ENTRIES_PER_BATCH;
    //calculate how many batches are needed
    $numBatches = (int) ($totalNumEntries / $ENTRIES_PER_BATCH);
    //if less than or precisely one batch is needed, do everything in one batch
    if($numBatches <= 1){
        $entries = genEntries($totalNumEntries);
        saveToCSV($entries);
        return;
    }
    //if the code came this far, more than one batch is needed
    //create the array that will store the temporary CSV output files
    $outputFiles = array();
    //calculate how many years per batch
    $yearsPerBatch = (int) (300 / $numBatches);
    //get the current year
    $now = new DateTime();
    $currentYear = $now->format('Y');
    //generate batches of entries and save them to separate .csv files, starting with output_0.csv - specify the year range that is to be used and the starting index of ID
    for($i = 0; $i < $numBatches; $i++){
        $startYear = $currentYear - ($yearsPerBatch * ($i+1));
        $endYear = $currentYear - ($yearsPerBatch * $i);
        $entries = genEntriesForYears($ENTRIES_PER_BATCH, $startYear, $endYear, (($i * $ENTRIES_PER_BATCH)+1));
        $outputFiles[] = saveToCSVBatched($entries, $i);
        echo "Batch ".($i+1)." of ".($numBatches+1)." saved to CSV."."<br>";
    }
    //save the last batch of entries
    $lastBatchAmount = $totalNumEntries % $ENTRIES_PER_BATCH;
    $startYear = $currentYear - ($yearsPerBatch * ($numBatches+1));
    $endYear = $currentYear - ($yearsPerBatch * $numBatches);
    $entries = genEntriesForYears($lastBatchAmount, $startYear, $endYear, (($numBatches * $ENTRIES_PER_BATCH)+1));
    $outputFiles[] = saveToCSVBatched($entries, $numBatches);
    echo "Final batch of entries saved to CSV file."."<br>"."Combining temp CSV files into main output.csv file."."<br>";
    combineFiles($outputFiles);
    echo "Finished combining files. output.csv should be ready now."."<br>";
}
//generate specified amount of entries for a specified range of years and a specified starting index for ID
function genEntriesForYears($amount, $startYear, $endYear, $startIndex):array{
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
        $dob = genDOBForYears($startYear, $endYear);
        $age = calcAge($dob);
        $newId = $startIndex + $count;
        $entry = [
            "ID" => $newId,
            "name" => $name,
            "surname" => $surname,
            "initial" => $initial,
            "age" => $age,
            "dob" => $dob
        ];
        if(!in_array($entry, $entries)) $entries[$count++] = $entry;
    }
    return $entries;
}
//save entries to CSV file - this function appends at the bottom of the CSV file
function saveToCSVBatched($entries, $fileSuffix):string{
    //get the user's Downloads folder path and specify CSV file path
    $downloadsFolder = getenv("HOME")."/Downloads";
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $downloadsFolder = getenv("USERPROFILE")."\\Downloads";
    }
    $filePath = $downloadsFolder . DIRECTORY_SEPARATOR . "output_".$fileSuffix.".csv";
    try{
        //open the file and check that it is opened
        $file = fopen($filePath, 'w');  //'w' mode will overwrite any existing file || 'a' will open in append mode
        if ($file === false) {
            echo "Error: Unable to open file for appending."."<br>";
            return false;
        }
        //write the entries
        foreach($entries as $entry){
            $dobString = $entry["dob"]->format('d/m/Y');
            fputcsv($file, [$entry["ID"], $entry["name"], $entry["surname"], $entry["initial"], $entry["age"], $dobString]);
        }
        fclose($file);
        return $filePath;
    }catch (Exception $e){
        echo "Error: Unable to open file for appending."."<br>";
        return false;
    }
}
//combines all the temp CSV files into the main output.csv file using 'rb' - returns true on success, otherwise false
function combineFiles($tempFiles):bool{
    //get the user's Downloads folder path and specify CSV file path
    $downloadsFolder = getenv("HOME")."/Downloads";
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $downloadsFolder = getenv("USERPROFILE")."\\Downloads";
    }
    $outputPath = $downloadsFolder . DIRECTORY_SEPARATOR . "output.csv";
    if(file_exists($outputPath)) unlink($outputPath);
    $outputHandle = fopen($outputPath, 'w');
    if ($outputHandle === false) {
        echo "Failed to open output.csv for writing."."<br>";
        return false;
    }
    //set the header
    fputcsv($outputHandle, ["ID", "Name", "Surname", "Initial", "Age", "Date_Of_Birth"]);
    //open each file in read binary mode and copy its contents over to the main file
    foreach ($tempFiles as $file) {
        $inputHandle = fopen($file, 'rb');
        if ($inputHandle === false) {
            echo "Failed to open file for reading: ".$file."<br>";
            return false;
        }
        //append the content of the current file to the output file
        while (!feof($inputHandle)) {
            //read a chunk of data from the file
            $chunk = fread($inputHandle, 1024 * 1024); //can adjust chunk size as needed
            //write the chunk to the output file
            fwrite($outputHandle, $chunk);
        }
        //close the input file
        fclose($inputHandle);
    }
    //close the output file
    fclose($outputHandle);
    echo "Successfully combined CSV files into output.CSV"."<br>";
    //delete the temp files
    foreach ($tempFiles as $file) {
        if(file_exists($file)) unlink($file);
    }
    return true;
}
//generate a dob within the specified years
function genDOBForYears($startYear, $endYear):DateTime{
    $year = rand($startYear, $endYear);
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
//calculates the age of a person with the specified date of birth
function calcAge($dob):int{
    $now = new DateTime();
    $age = $now->diff($dob);
    return $age->y;
}
//generates specified amount of entries
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
            "dob" => $dob
        ];
        if(!in_array($entry, $entries)) $entries[$count++] = $entry;
    }
    return $entries;
}
//save the given entries to a CSV file and store it in the user's downloads folder - this function overwrites the current data in the CSV
function saveToCSV($entries){
    //get the user's Downloads folder path and specify CSV file path
    $downloadsFolder = getenv("HOME")."/Downloads";
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $downloadsFolder = getenv("USERPROFILE")."\\Downloads";
    }
    $filePath = $downloadsFolder . DIRECTORY_SEPARATOR . "output.csv";
    try{
        //open the file and check that it is opened
        $file = fopen($filePath, 'w');  //'w' mode will overwrite any existing file || 'a' will open in append mode
        if ($file === false) {
            echo "Error: Unable to open file for writing."."<br>";
            return;
        }
        //set the header
        fputcsv($file, ["ID", "Name", "Surname", "Initial", "Age", "Date_Of_Birth"]);
        //write the entries
        $counter = 1;
        foreach($entries as $entry){
            $dobString = $entry["dob"]->format('d/m/Y');
            fputcsv($file, [$counter++, $entry["name"], $entry["surname"], $entry["initial"], $entry["age"], $dobString]);
        }
        fclose($file);
        echo "File saved to ".$filePath."<br>";
    }catch (Exception $e){
        echo "Error: Unable to open file for writing."."<br>";
        return;
    }
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
        //delete the 'csv_import table if it exists
        $sql = "DROP TABLE IF EXISTS csv_import";
        $pdo->exec($sql);
        //create 'csv_import' table
        $sql = "CREATE TABLE IF NOT EXISTS csv_import (
                    id INTEGER PRIMARY KEY,
                    name TEXT NOT NULL,
                    surname TEXT NOT NULL,
                    initial TEXT NOT NULL,
                    age INTEGER NOT NULL,
                    date_of_birth TEXT NOT NULL
                )";
        $pdo->exec($sql);
        //clear the db just in case
        clearDB($pdo);
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
            $header = fgetcsv($csvFile, 100, ",");
            //create SQL insert statement
            $sql = "INSERT INTO csv_import (id, name, surname, initial, age, date_of_birth) VALUES (:id, :name, :surname, :initial, :age, :dob)";
            $stmt = $pdo->prepare($sql);
            //loop through each row of the CSV file
            while (($data = fgetcsv($csvFile, 100, ",")) !== false) {
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
                $stmt->bindParam(':date_of_birth', $dob);
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

