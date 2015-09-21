<?php

class Import_registration {

//save data to return to the user
private $updated = [];
private $rejected = [];
private $flagged = [];

  function save_registrations() {

    $dates = [];
    global  $updated,
    $rejected,
    $flagged;

    include_once 'database.php';
    $dbh = $database->create_dbh();

    $datacount = 0;
    //read data from file
    @$handle = fopen("registration.csv", "r");
    $start = microtime(true);
    while (($data = fgetcsv($handle, 0, ",")) !== FALSE) {
      if($datacount ==0){ //this is the header
        $this->verifyData($data);
        $datacount++;
        continue; //prevents column headers from being written to database
      }
      //get the semester from the DB
      $semesterid = $this->getSemester($dbh, $data[4]);
      //if not found, send back a message
      if(!$semesterid){
        echo ' You need to enter the semester data first <br>';
      } else {
        $studentid = $this->getStudent($dbh, $data);
        if(!$studentid){
          echo ' No student match found for SSN or DOB <br>';
        } else {
          $registrationData = array($studentid['stdnt_idstudent'], $semesterid, $studentid['stdnt_address'], substr($data[4], 4, 2));
          $sth =$dbh->prepare("INSERT INTO registration (reg_student, reg_semester, reg_address, reg_registrationstatus)
                             VALUES (?, ? ,?, ?)");
          if ($sth->execute($registrationData)) {
            echo "Successfully inserted record # $datacount into registration table. <br/>";
          } else {
            echo 'Failed to insert record <br/>';
            print_r($sth->errorInfo());
          }
        }
      }
      $datacount++;
    }
    fclose($handle);
  }


  private function getSemester($dbh, $semester){
    //make sure  this semester is already in the database
    $school = 123;
    $smstr = substr ($semester , 0 , 4);
    $sth = $dbh->prepare('SELECT smstr_idsemester FROM semester WHERE smstr_school = :smstr_school AND smstr_code = :smstr_code');
    $sth->bindParam(':smstr_school', $school);
    $sth->bindParam(':smstr_code', $smstr);
    $sth->execute();
    //get the idaddress if it exists
    $id = $sth->fetchColumn();
    print_r('got it ' . $id);
    return $id;
  }

private function getStudent($dbh, $studentData){
    //make sure this student is already in the database
    $identifier = ($studentData[0]) ? $studentData[0] : $studentData[3];
    $sth = $dbh->prepare('SELECT stdnt_idstudent, stdnt_address FROM student WHERE stdnt_ss = :stdnt_ss OR stdnt_dob = :stdnt_dob');
    $sth->bindParam(':stdnt_ss', $identifier);
    $sth->bindParam(':stdnt_dob', $identifier);
    $sth->execute();
    //get the idaddress if it exists
    $info = $sth->fetch(PDO::FETCH_ASSOC);
    echo '<br> got student ';
    print_r($info);
    return $info;
  }

  private function verifyData($data)  {
    // if the columns dont match what we think they are, send an error mesage
    $headers = Array ('ssn', 'lname', 'fname', 'dob', 'progid');
    if($data != $headers) {
      trigger_error("Incompatable file layout", E_USER_ERROR);
    }
  }

}
$save_registration = new Import_registration();
$save_registration->save_registrations();