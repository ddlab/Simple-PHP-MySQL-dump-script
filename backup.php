<?php

/**
 * Script name : Simple PHP MySQL dump script
 * Based on script by: Mohammad M. AlBanna
 * Updated by: Dalibor Klobučarić
 * Website: dd-lab.net
 * Requrements: PHP vr 7.3 and up / MySQL 8.0 and up (MariaDB 10.0 and up) / Apache 2.4 and up
 * Tested on WAMP server 3.3.2. 
 * Tested on LAMP server
 * Please change only this info 
 * $dbuser = 'USERNAME_HERE';
 * $dbpass = 'PASSOWRD_HERE';
 * $dbname = 'DATABASE_HERE';
 * and runscript on your browser http://localhost/backup.php 
 * also try to import it using phpmyadmin (just dump tables before) 
 */

// MySQL server and database, Fill the empti fields between '' 
$dbhost = 'localhost'; // or 127.0.0.1
$dbuser = '';
$dbpass = '';
$dbname = '';
$tables = '*';

// Call the core function
backup_tables($dbhost, $dbuser, $dbpass, $dbname, $tables);

// Core function
function backup_tables($host, $user, $pass, $dbname, $tables = '*')
{
    $link = mysqli_connect($host, $user, $pass, $dbname);

    // Check connection
    if (mysqli_connect_errno()) {
        echo "Failed to connect to MySQL: " . mysqli_connect_error();
        exit;
    }

    mysqli_query($link, "SET NAMES 'utf8'");

    // Get all of the tables
    if ($tables == '*') {
        $tables = array();
        $result = mysqli_query($link, 'SHOW TABLES');
        if ($result === false) {
            die("Error fetching tables: " . mysqli_error($link));
        }

        while ($row = mysqli_fetch_row($result)) {
            $tables[] = $row[0];
        }
    } else {
        $tables = is_array($tables) ? $tables : explode(',', $tables);
    }

    $return = '';
    // Cycle through
    foreach ($tables as $table) {
        // Wrap table name in backticks
        $table = '`' . $table . '`';

        $result = mysqli_query($link, 'SELECT * FROM ' . $table);

        // Check if the query was successful
        if ($result === false) {
            die('Error fetching data from table ' . $table . ': ' . mysqli_error($link));
        }

        $num_fields = mysqli_num_fields($result);
        $num_rows = mysqli_num_rows($result);

        $return .= 'DROP TABLE IF EXISTS ' . $table . ';';

        // Fetch the row with table creation statement
        $showCreateTableResult = mysqli_query($link, 'SHOW CREATE TABLE ' . $table);

        // Check if the query was successful
        if ($showCreateTableResult === false) {
            die('Error fetching CREATE TABLE statement for ' . $table . ': ' . mysqli_error($link));
        }

        $row2 = mysqli_fetch_row($showCreateTableResult);
        $return .= "\n\n" . $row2[1] . ";\n\n";
        $counter = 1;

        // Over tables
        for ($i = 0; $i < $num_fields; $i++) {
            // Over rows
            while ($row = mysqli_fetch_row($result)) {
                if ($counter == 1) {
                    $return .= 'INSERT INTO ' . $table . ' VALUES(';
                } else {
                    $return .= '(';
                }

                // Over fields
                for ($j = 0; $j < $num_fields; $j++) {
                    $row[$j] = addslashes($row[$j]);
                    $row[$j] = str_replace("\n", "\\n", $row[$j]);
                    if (isset($row[$j])) {
                        $return .= '"' . $row[$j] . '"';
                    } else {
                        $return .= '""';
                    }
                    if ($j < ($num_fields - 1)) {
                        $return .= ',';
                    }
                }

                if ($num_rows == $counter) {
                    $return .= ");\n";
                } else {
                    $return .= "),\n";
                }
                ++$counter;
            }
        }
        $return .= "\n\n\n";
    }

    // Create a temporary file for the SQL dump
    $tempFile = tempnam(sys_get_temp_dir(), 'sql_dump_');
    file_put_contents($tempFile, $return);

    // Create a zip archive
    $zip = new ZipArchive();
    $zipFileName = 'db-backup-' . time() . '-' . (md5(implode(',', $tables))) . '.zip';

    if ($zip->open($zipFileName, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
        // Add the SQL dump file to the archive
        $zip->addFile($tempFile, basename($tempFile) . '.sql');
        $zip->close();

        // Set headers for download
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zipFileName . '"');

        // Output the zip file
        readfile($zipFileName);

        // Remove the temporary files
        unlink($tempFile);
        unlink($zipFileName);

        // Close the connection
        mysqli_close($link);

        // Stop the script execution
        exit;
    } else {
        echo "Failed to create the zip archive.";
        unlink($tempFile);
        exit;
    }
}
