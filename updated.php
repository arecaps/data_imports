<?php

class Import_students {

  //save data to return to the user
  private $updated = [];
  private $rejected = [];
  private $flagged = [];

  function save_students() {

    $dates = [];
    global $updated,
           $rejected,
           $flagged;

    include_once 'database.php';
    $dbh = $database->create_dbh();

    $datacount = 0;
    //read data from file
    @$handle = fopen("tester2.csv", "r");
    while (($data = fgetcsv($handle, 0, ",")) !== FALSE) {
      if($datacount ==0){ //this is the header
        $this->verifyData($data);
        $datacount++;
        continue; //prevents column headers from being written to database
      }
      //convert date strings to dates
      if($data[10]){
        $dates[] = $dob = date("Y-m-d",strtotime($data[10]));
      } else {
        $dob = NULL;
      }
      if($data[12]){
        $dates[] = $dmarri = date("Y-m-d",strtotime($data[12]));
      } else {
        $dmarri = NULL;
      }
      if($data[13]){
        $dates[] = $ddate = date("Y-m-d",strtotime($data[13]));
      } else {
        $ddate = NULL;
      }
      if($data[16]){
        $dates[] = $dateleft = date("Y-m-d",strtotime($data[16]));
      } else {
        $dateleft = NULL;
      }
      //if there is an updated graduation date, use that instead
      if($data[22]){
        $dates[] = $graddate = $data[22];
      } else if($data[21]){
        $dates[] = $graddate = $data[21];
      }else {
      $graddate = NULL;
      }
      //check if dates are valid
      if($this->invalidDate($dates, $data)){
        $rejected[] = $data;
        unset($dates);
        continue;
      }
      unset($dates);

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
        $update = $this->checkUpdate($dbh, $adjstudentdata);
        if(!$update){
          //not an update, just insert the new student
          $sth2 =$dbh->prepare("INSERT INTO student (stdnt_ss, stdnt_last_name, stdnt_first_name, stdnt_heb_name, stdnt_address, stdnt_school, stdnt_phone, stdnt_dob, stdnt_marital_status, stdnt_date_married,  stdnt_degree_date, stdnt_withdrew, stdnt_branch, stdnt_comment, stdnt_graduation)
                           VALUES (?, ? ,?, ?, ? ,?, ?, ? ,?, ?, ?, ?, ?, ?, ?)");

          if (!$sth2->execute($adjstudentdata)) {
            print_r($sth->errorInfo());
          }
        } else if($update == 1){
          //Existing student with no changes, or error, do nothing
        } else {
          //there are changes, loop through them and update them
          foreach ($update as $fix) {
            //add the SSN to the array for update
            $fix[] = $data[0];
            $table = array_shift($fix);
            $column = array_shift($fix);
            $sth2 =$dbh->prepare("UPDATE $table SET $column = ? WHERE stdnt_ss = ?");
            if ($sth2->execute($fix)) {
              //save the update to show the user
              $updated[] = $data;
            } else {
              print_r($sth->errorInfo());
            }
          }
        }

        $dbh->commit();
      } catch (Exception $e) {
        $dbh->rollBack();
        echo 'Failed: ' . $e->getMessage();
      }
      unset($data);
      $datacount++;
    }
    fclose($handle);
    //dump all the waarnings
    echo 'Rejected for invalid data:';
    echo '<br>' . '<br>';
    foreach ($rejected as $rjct) {
      print_r($rjct);
      echo '<br>';
    }
    echo '<br> Updated:';
    echo '<br>' . '<br>';
    foreach ($updated as $updt) {
      print_r($updt);
      echo '<br>';
    }
    echo '<br> Flagged for suspicious data:';
    echo '<br>' . '<br>';
    foreach ($flagged as $flg) {
      print_r($flg);
      echo '<br>';
    }
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

  private function checkUpdate($dbh, $student){
    global $flagged;
    //check if we have this student already
    $sth = $dbh->prepare('SELECT * FROM student WHERE stdnt_ss = :stdnt_ss OR (stdnt_last_name = :stdnt_last_name AND stdnt_first_name = :stdnt_first_name)');
    $sth->bindParam(':stdnt_ss', $student[0]);
    $sth->bindParam(':stdnt_last_name', $student[1]);
    $sth->bindParam(':stdnt_first_name', $student[2]);
    $sth->execute();
    $results = $sth->fetchAll(PDO::FETCH_ASSOC);

    if (empty($results)){ // there is no match for full name or for SS
      return false;
    } else {
      //matched names or SSN, for each match, check if it is full or partial
      foreach ($results as $result) {
        if ($result['stdnt_last_name'] == $student[1] && $result['stdnt_ss'] == $student[0]){
          // there is a match for both, just update record
          $changed = $this->update($result, $student);
          return $changed;
        }
      }
      //If there is no exact match do furter checking
      foreach ($results as $result) {
        if ($result['stdnt_last_name'] == $student[1] && $result['stdnt_ss'] !== $student[0]){
          //there is a match for name, but not SS, confirm this to be a new student by checking DOB
          $sth = $dbh->prepare('SELECT stdnt_dob FROM student WHERE stdnt_last_name = :stdnt_last_name AND stdnt_first_name = :stdnt_first_name');
          $sth->bindParam(':stdnt_last_name', $student[1]);
          $sth->bindParam(':stdnt_first_name', $student[2]);
          $sth->execute();
          $checkDob = $sth->fetchColumn();
          if($checkDob === $student[7]){
            // if dob matches name, there is an error in the SSN, flag and do not insert
          } else {
            // this is a new student, not an update, just insert into DB
            return false;
          }
        } else if ($result['stdnt_last_name'] != $student[1] && $result['stdnt_ss'] == $student[0]){
          //there is a match for SS, but not name, this is an error, flag and do not insert
        }
      }
      //Flag all errors that fell thru till here
      $flagged[] = $results;
      return true;
    }
  }

  private function update($result, $student){
    global $flagged;
    //If everything matches, do nothing, otherwise send data to user for update
    $maritalStatus = ($student[8] == 1) ? 1 : 0;
    if( $student[3] == $result['stdnt_heb_name'] &&
        $student[5] == $result['stdnt_school'] &&
        $student[7] == $result['stdnt_dob'] &&
        $student[4] == $result['stdnt_address'] &&
        $student[6] == $result['stdnt_phone'] &&
        $student[14] == $result['stdnt_graduation'] &&
        $student[11] == $result['stdnt_withdrew'] &&
        $student[12] == $result['stdnt_branch'] &&
        $maritalStatus == $result['stdnt_marital_status'] &&
        $student[9] == $result['stdnt_date_married'] &&
        $student[10] == $result['stdnt_degree_date'] &&
        $student[13] == $result['stdnt_comment']){
      //Nothing changed, just continue
      return true;
    } else {
      //save changes
      $changed = [];
      if($student[3] != $result['stdnt_heb_name']){
        //add a flag for things that should not change!
        $flagged[] = $student;
        $changed[] = ['student', 'stdnt_heb_name', $student[3]];
      }
      if($student[5] != $result['stdnt_school']){
        $changed[] = ['student', 'stdnt_school', $student[5]];
      }
      if($student[7] != $result['stdnt_dob']){
        //add a flag for things that should not change!
        $flagged[] = $student;
        $changed[] = ['student', 'stdnt_dob', $student[7]];
      }
      if($student[4] != $result['stdnt_address']){
        $changed[] = ['student','stdnt_address', $student[4]];
      }
      if($student[6] != $result['stdnt_phone']){
        $changed[] = ['student', 'stdnt_phone', $student[6]];
      }
      if($student[14] != $result['stdnt_graduation']){
        $changed[] = ['student', 'stdnt_graduation', $student[14]];
      }
      if($student[11] != $result['stdnt_withdrew']){
        $changed[] = ['student', 'stdnt_withdrew', $student[11]];
      }
      if($student[12] != $result['stdnt_branch']){
        $changed[] = ['student', 'stdnt_branch', $student[12]];
      }
      if($maritalStatus != $result['stdnt_marital_status']){
        $changed[] = ['student', 'stdnt_marital_status', $maritalStatus];
      }
      if($student[9] != $result['stdnt_date_married']){
        $changed[] = ['student', 'stdnt_date_married', $student[9]];
      }
      if($student[10] != $result['stdnt_degree_date']){
        $changed[] = ['student', 'stdnt_degree_date', $student[10]];
      }
      if($student[13] != $result['stdnt_comment']){
        $changed[] = ['student', 'stdnt_comment', $student[13]];
      }
      return $changed;
    }
  }


  private function verifyData($data)  {
    // if the columns dont match what we think they are, send an error mesage
    $headers = Array ('SSN', 'lname', 'fname', 'hname', 'address', 'city', 'state', 'zip', 'country', 'tel', 'dob', 'mstatus', 'dmarri', 'ddate1', 'ddate2', 'ddate3', 'dateleft', 'Semester_Withdrew', 'MidSem_Withdrew', 'branch', 'notescomments', 'ExpectedCompletionDate', 'UpdatesforECD' );
    if($data != $headers) {
      trigger_error("Incompatable file layout", E_USER_ERROR);
    }
  }
  private function invalidDate($dates, $row){
    foreach($dates as $date){
      //confirm that date is numerical, and is not the default unix timestamp
      if((!preg_match('/^[0-9].+/', $date)) || ($date === 1970-01-01)){
        return true;
      }
    }
    return false;
  }

}
$save_student = new Import_students();
$save_student->save_students();
?>