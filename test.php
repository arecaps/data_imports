<?php

class Import_school {
  function save_students() {

    include_once 'database.php';
    $dbh = $database->create_dbh();

    $datacount = 0;
    //read data from file
    @$handle = fopen("sample_info.csv", "r");
    while (($data = fgetcsv($handle, 0, ",")) !== FALSE) {
      if($datacount ==0){ //this is the header
        $this->verifyData($data);
        $datacount++;
        continue; //prevents column headers from being written to database
      } else {
        $data = $data;
      }
      //convert date strings to dates
      if($data[10]){
        $dob = date("Y-m-d",strtotime($data[10]));
      } else {
        $dob = NULL;
      }
      if($data[12]){
        $dmarri = date("Y-m-d",strtotime($data[12]));
      } else {
        $dmarri = NULL;
      }
      if($data[13]){
        $ddate = date("Y-m-d",strtotime($data[13]));
      } else {
        $ddate = NULL;
      }
      if($data[16]){
        $dateleft = date("Y-m-d",strtotime($data[16]));
      } else {
        $dateleft = NULL;
      }
      //if there is an updated graduation date, use that instead
      if($data[22]){
        $graddate = $data[22];
      } else if($data[21]){
        $graddate = $data[21];
      } else {
        $graddate = NULL;
      }

      try {
        //create transaction, so that it does not save address if student fails
        $dbh->beginTransaction();
        //new array with just the fields needed for address
        $adjaddressdata = array($data[4], $data[5], $data[6], $data[7], $data[8]);
        $id = $this->checkExists($dbh, $adjaddressdata);
        //if not found, write it to the db
        if(!$id){
          $sth1 =$dbh->prepare("INSERT INTO address (adrs_streetaddress, adrs_city, adrs_state, adrs_zip, adrs_country)
                             VALUES (?, ? ,?, ?, ?)");
          if (!$sth1->execute($adjaddressdata)) {
            print_r($sth->errorInfo());
          }
          //now grab the id of the address to add it to the student table
          $id = $dbh->lastInsertId();
        }
        //new array with just the fields needed for student
        $adjstudentdata = array($data[0], $data[1], $data[2], $data[3], $id, 1234, $data[9], $dob, $data[11], $dmarri, $ddate , $dateleft, $data[19], $data[20], $graddate);
        $sth2 =$dbh->prepare("INSERT INTO student (stdnt_ss, stdnt_last_name, stdnt_first_name, stdnt_heb_name, stdnt_address, stdnt_school, stdnt_phone, stdnt_dob, stdnt_marital_status, stdnt_date_married,  stdnt_degree_date, stdnt_withdrew, stdnt_branch, stdnt_comment, stdnt_graduation)
                           VALUES (?, ? ,?, ?, ? ,?, ?, ? ,?, ?, ?, ?, ?, ?, ?)");

        if (!$sth2->execute($adjstudentdata)) {
          print_r($sth->errorInfo());
        }

        $dbh->commit();
      } catch (Exception $e) {
        $dbh->rollBack();
        echo "Failed: " . $e->getMessage();
      }
      unset($data);
      $datacount++;
    }
    fclose($handle);
  }

  private function checkExists($dbh, $address){
    //check if this address is already in the database
    $sth = $dbh->prepare('SELECT adrs_idaddress FROM address WHERE adrs_streetaddress = :address AND adrs_zip = :zip');
    $sth->bindParam(':address', $address[0]);
    $sth->bindParam(':zip', $address[3]);
    $sth->execute();
    //get the idaddress if it exists
    $id = $sth->fetchColumn();
    return $id;
  }

  private function verifyData($data)  {
    // if the columns dont match what we think they are, send an error mesage
    $headers = Array ('SSN', 'lname', 'fname', 'hname', 'address', 'city', 'state', 'zip', 'country', 'tel', 'dob', 'mstatus', 'dmarri', 'ddate1', 'ddate2', 'ddate3', 'dateleft', 'Semester_Withdrew', 'MidSem_Withdrew', 'branch', 'notescomments', 'ExpectedCompletionDate', 'UpdatesforECD' );
    if($data != $headers) {
      trigger_error("Incompatable file layout", E_USER_ERROR);
    }
  }
}
$save_student = new Import_school();
$save_student->save_students();
?>