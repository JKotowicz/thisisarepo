<?php
require("Bookstore.php");

// initial result of the api
$result = "An error has occurred";

// needed globals
$errorLogFile = "errors.txt";

$databaseFile = getcwd(). "/../Database/SWEN344DB.db";

// debug switch
$sqliteDebug = true; //SET TO FALSE BEFORE OFFICIAL RELEASE

//////////////////////
//General Functions///
//////////////////////

// Switchboard to General Functions
function general_switch($getFunctions)
{
	// Define the possible general function URLs which the page can be accessed from
	$possible_function_url = array("test", "login", "createUser", "getUsers", "getStudent", "postStudent", "getProfessor",
					"getAdmin", "getCourse", "postCourse");
				
	if ($getFunctions)
	{
		return $possible_function_url;
	}
	
	if (isset($_GET["function"]) && in_array($_GET["function"], $possible_function_url))
	{
		switch ($_GET["function"])
		{
			case "test":
				return APITest();
			case "login":
				if (isset($_POST["username"]) && isset($_POST["password"]))
				{
					return login($_POST["username"], $_POST["password"]);
				}
				else
				{
					logError("loginValid ~ Required parameters were not submit correctly.");
					return FALSE;
				}
			// returns: student object
			// params: studentID
			case "getStudent":
				if (isset($_GET["studentID"]) && $_GET["studentID"] != null)
				{
					return getStudent($_GET["studentID"]);
				}
				else 
				{
					return "Missing studentID parameter";
				}
			//returns: array of all users in database
			case "getUsers":
				return getUsers();
			// returns: Newly created student object
			// params: userID, yearLevel, gpa
			case "postStudent":
				if ((isset($_POST["yearLevel"]) && $_POST["yearLevel"] != null)
					&& (isset($_POST["gpa"]) && $_POST["gpa"] != null)
					&& (isset($_POST["userID"]) && $_POST["userID"] != null))
				{
					return postStudent($_POST["userID"], $_POST["yearLevel"], $_POST["gpa"]);
				}
				else {
					return "Missing parameter(s)";
				}
			// returns: professor object
			// params: professorID
			case "getProfessor":
				if (isset($_GET["professorID"]) && $_GET["professorID"] != null)
				{
					return getProfessor($_GET["professorID"]);
				}
				else {
					return "Missing professorID";
				}
			// returns: admin object
			// params: adminID
			case "getAdmin":
				if (isset($_GET["adminID"]) && $_GET["adminID"] != null)
				{
					return getAdmin($_GET["adminID"]);
				}
				else {
					return "Missing adminID parameter";
				}
			// returns: course object
			// params: courseID
			case "getCourse":
				if (isset($_GET["courseID"]) && $_GET["courseID"] != null)
				{
					return getCourse($_GET["courseID"]);
				}
				else {
					return "Missing courseID parameter";
				}
			// returns: newly created course object
			// params: courseCode, courseName, credits, minGPA
			case "postCourse":
				if ((isset($_POST["courseCode"]) && $_POST["courseCode"] != null)
					&& (isset($_POST["courseName"]) && $_POST["courseName"] != null)
					&& (isset($_POST["credits"]) && $_POST["credits"] != null)
					&& (isset($_POST["minGPA"]) && $_POST["minGPA"] != null))
				{
					return postCourse($_POST["courseCode"],
						$_POST["courseName"],
						$_POST["credits"],
						$_POST["minGPA"]
						);
				}
				else {
					return "Missing parameter(s)";
				}
				
			case "createUser":
				if (isset($_POST["username"]) &&
					isset($_POST["password"]) &&
					isset($_POST["fname"]) &&
					isset($_POST["lname"]) &&
					isset($_POST["email"]) &&
					isset($_POST["role"])
					)
					{
						return createUser($_POST["username"],
							$_POST["password"],
							$_POST["fname"],
							$_POST["lname"],
							$_POST["email"],
							$_POST["role"]
							);
					}
					else
					{
						logError("createUser ~ Required parameters were not submit correctly.");
						return ("createUser One or more parameters were not provided");
					}
		}
	}
	else
	{
		return "Function does not exist.";
	}
}

function APITest()
{
	return "API Connection Success!";
}

function logError($message)
{
	try
	{
		$myfile = fopen($GLOBALS ["errorLogFile"], "a");
		fwrite($myfile, ($message . "\n"));
		fclose($myfile);
	}
	catch (Exception $exception)
	{
		//what should happen if this fails???
	}
}

//to decrypt this hash you NEED to use password_verify($password, $hash)
function encrypt($string)
{
	return password_hash($string, PASSWORD_DEFAULT);
}

//to create prof or admin simply use this function with the correct flags
//This also checks if username is valid and encrypts the plain text password
//returns true if successful, else false
function createUser($username, $password, $fname, $lname, $email, $role)
{
	$success = FALSE;

	try
	{
		$sqlite = new SQLite3($GLOBALS ["databaseFile"]);
		$sqlite->enableExceptions(true);

		//first check if the username already exists
		$query = $sqlite->prepare("SELECT * FROM User WHERE USERNAME=:username");
		$query->bindParam(':username', $username);
		$result = $query->execute();

		if ($record = $result->fetchArray())
		{
			return "Username Already Exists";
		}

		//for varaible reuse
		$result->finalize();

		$query1 = $sqlite->prepare("INSERT INTO User (USERNAME, PASSWORD, FIRSTNAME, LASTNAME, EMAIL, ROLE) VALUES (:username, :password, :fname, :lname, :email, :role)");

		$query1->bindParam(':username', $username);
		$query1->bindParam(':password', encrypt($password));
		$query1->bindParam(':fname', $fname);
		$query1->bindParam(':lname', $lname);
		$query1->bindParam(':email', $email);
		$query1->bindParam(':role', $role);

		$query1->execute();

		// clean up any objects
		$sqlite->close();

		//if it gets here without throwing an error, assume success = true;
		$success = TRUE;
	}
	catch (Exception $exception)
	{
		if ($GLOBALS ["sqliteDebug"])
		{
			return $exception->getMessage();
		}
		logError($exception);
	}

	return $success;
}

function login($username, $password)
{
	if (loginValid($username, $password))
	{
		try 
		{
			$sqlite = new SQLite3($GLOBALS ["databaseFile"]);
			$sqlite->enableExceptions(true);
			
			//prepare query to protect from sql injection
			$query = $sqlite->prepare("SELECT * FROM User WHERE USERNAME=:username");
			$query->bindParam(':username', $username);		
			$result = $query->execute();
			
			
			//$sqliteResult = $sqlite->query($queryString);
			
			if ($record = $result->fetchArray(SQLITE3_ASSOC)) 
			{
				$result->finalize();
				$sqlite->close();
				
				return $record;
			}
		
		}
		catch (Exception $exception)
		{
			if ($GLOBALS ["sqliteDebug"]) 
			{
				return $exception->getMessage();
			}
			logError($exception);
		}
	}
	else 
	{
		return null;
	}
}

//username and PLAIN TEXT password
//must submit values via POST and not GET
function loginValid($username, $password)
{
	$valid = FALSE;
	//return $GLOBALS ["databaseFile"];
	try
	{
		$sqlite = new SQLite3($GLOBALS ["databaseFile"]);
		$sqlite->enableExceptions(true);

		//prepare query to protect from sql injection
		$query = $sqlite->prepare("SELECT * FROM User WHERE USERNAME=:username");
		$query->bindParam(':username', $username);
		$result = $query->execute();


		//$sqliteResult = $sqlite->query($queryString);

		if ($record = $result->fetchArray())
		{
			if (password_verify($password, $record['PASSWORD']))
			{
				$valid = TRUE;
			}
		}

		$result->finalize();

		// clean up any objects
		$sqlite->close();
	}
	catch (Exception $exception)
	{
		if ($GLOBALS ["sqliteDebug"])
		{
			return $exception->getMessage();
		}
		logError($exception);
	}

	return $valid;
}

function getUsers()
{
	try
	{
		$sqlite = new SQLite3($GLOBALS ["databaseFile"]);
		$sqlite->enableExceptions(true);
		
		//prepare query to protect from sql injection
		$query = $sqlite->prepare("SELECT ID, USERNAME, FIRSTNAME, LASTNAME, EMAIL, ROLE FROM User");		
		$result = $query->execute();
		
		$record = array();
		
		while($arr=$result->fetchArray(SQLITE3_ASSOC))
		{
			array_push($record, $arr);
		}
		$result->finalize();
		// clean up any objects
		$sqlite->close();
		return $record;
	}
	catch (Exception $exception)
	{
		if ($GLOBALS ["sqliteDebug"]) 
		{
			return $exception->getMessage();
		}
		logError($exception);
	}
}
function getStudent($studentID)
{
	try
	{
		$sqlite = new SQLite3($GLOBALS ["databaseFile"]);
		$sqlite->enableExceptions(true);
		
		//prepare query to protect from sql injection
		$query = $sqlite->prepare("SELECT * FROM Student WHERE USER_ID=:user_ID");
		$query->bindParam(':user_ID', $studentID);
		$result = $query->execute();
		
		//$sqliteResult = $sqlite->query($queryString);
		if ($record = $result->fetchArray(SQLITE3_ASSOC)) 
		{
			$result->finalize();
			$sqlite->close();
			
			return $record;
		}
	}
	catch (Exception $exception)
	{
		if ($GLOBALS ["sqliteDebug"]) 
		{
			return $exception->getMessage();
		}
		logError($exception);
	}
}

function postStudent($yearLevel, $gpa)
{
	try
	{
		$sqlite = new SQLite3($GLOBALS ["databaseFile"]);
		$sqlite->enableExceptions(true);
		
		
		$query = $sqlite->prepare("INSERT INTO Student (YEAR_LEVEL, GPA) VALUES (:yearLevel, :gpa)");
		$query->bindParam(':yearLevel', $yearLevel);
		$query->bindParam(':gpa', $gpa);
		$result = $query->execute();
		
		if ($record = $result->fetchArray(SQLITE3_ASSOC)) 
		{
			$result->finalize();
			// clean up any objects
			$sqlite->close();
			return $record;
		}
	}
	catch (Exception $exception)
	{
		if ($GLOBALS ["sqliteDebug"]) 
		{
			return $exception->getMessage();
		}
		logError($exception);
	}
}

function getProfessor($professorID)
{
	try
	{
		$sqlite = new SQLite3($GLOBALS ["databaseFile"]);
		$sqlite->enableExceptions(true);
		
		$query = $sqlite->prepare("SELECT * FROM Professor WHERE USER_ID=:user_ID");
		$query->bindParam(':user_ID', $professorID);
		$result = $query->execute();
		
		if ($record = $result->fetchArray(SQLITE3_ASSOC)) 
		{
			$result->finalize();
			$sqlite->close();
			
			return $record;
		}
	}
	catch (Exception $exception)
	{
		if ($GLOBALS ["sqliteDebug"]) 
		{
			return $exception->getMessage();
		}
		logError($exception);
	}
}

function getAdmin($adminID)
{
	try
	{
		$sqlite = new SQLite3($GLOBALS ["databaseFile"]);
		$sqlite->enableExceptions(true);
		
		$query = $sqlite->prepare("SELECT * FROM Admin WHERE USER_ID=:user_ID");
		$query->bindParam(':user_ID', $adminID);
		$result = $query->execute();
		
		if ($record = $result->fetchArray(SQLITE3_ASSOC)) 
		{
			$result->finalize();
			$sqlite->close();
			
			return $record;
		}
	}
	catch (Exception $exception)
	{
		if ($GLOBALS ["sqliteDebug"]) 
		{
			return $exception->getMessage();
		}
		logError($exception);
	}
}

function getCourse($courseID)
{
	try
	{
		$sqlite = new SQLite3($GLOBALS ["databaseFile"]);
		$sqlite->enableExceptions(true);
		
		$query = $sqlite->prepare("SELECT * FROM Course WHERE ID=:ID");
		$query->bindParam(':ID', $courseID);
		$result = $query->execute();
		
		if ($record = $result->fetchArray(SQLITE3_ASSOC)) 
		{
			$result->finalize();
			$sqlite->close();
			
			return $record;
		}
	}
	catch (Exception $exception)
	{
		if ($GLOBALS ["sqliteDebug"]) 
		{
			return $exception->getMessage();
		}
		logError($exception);
	}
}

function postCourse($courseCode, $courseName, $credits, $gpa)
{
	try
	{
		$sqlite = new SQLite3($GLOBALS ["databaseFile"]);
		$sqlite->enableExceptions(true);
		
		
		$query = $sqlite->prepare("INSERT INTO Course (COURSE_CODE, NAME, CREDITS, MIN_GPA) VALUES (:code, :name, :credits, :gpa)");
		$query->bindParam(':code', $courseCode);
		$query->bindParam(':name', $courseName);
		$query->bindParam(':credits', $credits);
		$query->bindParam(':gpa', $gpa);
		$result = $query->execute();
		
		if ($record = $result->fetchArray(SQLITE3_ASSOC)) 
		{
			$result->finalize();
			// clean up any objects
			$sqlite->close();
			return $record;
		}
	}
	catch (Exception $exception)
	{
		if ($GLOBALS ["sqliteDebug"]) 
		{
			return $exception->getMessage();
		}
		logError($exception);
	}
}

////////////////////////
//Team Based Functions//
////////////////////////

//////////////
//Book Store//
//////////////

//Handeled in external file

///////////////////
//Human Resources//
///////////////////

// Switchboard to Human Resources Functions
function human_resources_switch($getFunctions)
{
	// Define the possible Human Resources function URLs which the page can be accessed from
	$possible_function_url = array("test", "updatePerson", "updateProf", "updateName", "updatePassword", "createProf", "getPersonalInfo", "getProfInfo", "getEmployees", "terminate", "removeEmployee");

	if ($getFunctions)
	{
		return $possible_function_url;
	}

	if (isset($_GET["function"]) && in_array($_GET["function"], $possible_function_url))
	{
		switch ($_GET["function"])
		{
            case "test":
                return testThis();

            case "updateProf":
    			if ((isset($_POST["id"]) && $_POST["id"] != null)
					&& (isset($_POST["salary"]) && $_POST["salary"] != null)
					&& (isset($_POST["title"]) && $_POST["title"] != null)
				){
                	return updateProfInfo($_POST["id"], $_POST["salary"], $_POST["title"]);
                }
                else
                {
                	return "Missing a parameter";
                }
                
            case "updatePerson":
            	if ((isset($_POST["username"]) && $_POST["username"] != null)
					&& (isset($_POST["fname"]) && $_POST["fname"] != null)
					&& (isset($_POST["lname"]) && $_POST["lname"] != null)
					&& (isset($_POST["email"]) && $_POST["email"] != null)
					&& (isset($_POST["address"]) && $_POST["address"] != null)
					&& (isset($_POST["phone"]) && $_POST["phone"] != null)
				){
            		return updatePersonalInfo($_POST["username"], $_POST["fname"], $_POST["lname"], $_POST["email"], $_POST["address"], $_POST["phone"]);
				}
				else
                {
                	return "Missing a parameter";
                }
               
            case "updatePassword":
            	if ((isset($_POST["username"]) && $_POST["username"] != null)
					&& (isset($_POST["password"]) && $_POST["password"] != null)
				){
            		return updatePassword($_POST["username"], $_POST["password"]);
				}
				else
                {
                	return "Missing a parameter";
                }
                
            case "updateName":
            	if ((isset($_POST["username"]) && $_POST["username"] != null)
					&& (isset($_POST["fname"]) && $_POST["fname"] != null)
					&& (isset($_POST["lname"]) && $_POST["lname"] != null)
				){
                	return updateFullName($_POST["username"], $_POST["fname"], $_POST["lname"]);
                }
                else
                {
                	return "Missing a parameter";
                }
			case "createProf":
				if ((isset($_POST["username"]) && $_POST["username"] != null)
					&& (isset($_POST["password"]) && $_POST["password"] != null)
					&& (isset($_POST["fname"]) && $_POST["fname"] != null)
					&& (isset($_POST["lname"]) && $_POST["lname"] != null)
					&& (isset($_POST["email"]) && $_POST["email"] != null)
					&& (isset($_POST["role"]) && $_POST["role"] != null)
					&& (isset($_POST["managerID"]) && $_POST["managerID"] != null)
					&& (isset($_POST["title"]) && $_POST["title"] != null)
					&& (isset($_POST["address"]) && $_POST["address"] != null)
					&& (isset($_POST["salary"]) && $_POST["salary"] != null)
					&& (isset($_POST["phone"]) && $_POST["phone"] != null)
				){
					return createProf($_POST["username"],
						$_POST["password"],
						$_POST["fname"],
						$_POST["lname"],
						$_POST["email"],
						$_POST["role"],
						$_POST["managerID"],
						$_POST["title"],
						$_POST["address"],
						$_POST["salary"],
						$_POST["phone"]
						);
						
                }
                else
                {
                    return "Missing a parameter";
                }
			case "getPersonalInfo":
				if ((isset($_POST["username"]) && $_POST["username"] != null)
				){
					return getPersonalInfo($_POST["username"]);
				}
				else
				{
					return "Missing a parameter";
				}
			case "getProfInfo":
				if ((isset($_POST["id"]) && $_POST["id"] != null)
				){
						return getProfessionalInfo($_POST["id"]);
                }
                else
                {
                    return "Missing a parameter";
                }
            case "getEmployees":
				if ((isset($_POST["id"]) && $_POST["id"] != null)
				){
						return getEmployees($_POST["id"]);
                }
                else
                {
                    return "Missing a parameter";
                }
            case "terminate":
				if ((isset($_POST["id"]) && $_POST["id"] != null)
				){
						return terminate($_POST["id"]);
                }
                else
                {
                    return "Missing a parameter";
                }
            case "removeEmployee":
				if ((isset($_POST["id"]) && $_POST["id"] != null)
				){
						return removeEmployee($_POST["id"]);
                }
                else
                {
                    return "Missing a parameter";
                }
		}
	}
	else
	{
		return "Function does not exist.";
	}
}

//Define Functions Here

// Test connection for Human Resource
function testThis()
{
    return "MOO";
}

// Update First and Last name with username
// Input Parameters:
//  First name, Last name
// Main Input Parameter:
//  Username
function updateFullName($username, $fname, $lname)
{
    $success = false;
    try
    {
        // Open a connection to database
        $sqlite = new SQLite3($GLOBALS ["databaseFile"]);
        $sqlite->enableExceptions(true);
        // Prevent SQL Injection
        $query = $sqlite->prepare("UPDATE User SET FIRSTNAME=:fname, LASTNAME=:lname WHERE USERNAME=:username");
        // Set variables to query
        $query->bindParam(':username',$username);
        $query->bindParam(':fname',$fname);
        $query->bindParam(':lname',$lname);
        $query->execute();
        // Clear up the connection
        $sqlite->close();
        $success = true;
    }
    catch (Exception $exception)
    {
        if ($GLOBALS ["sqliteDebug"])
        {
			return $exception->getMessage();
		}
		logError($exception);
	}
	
	return $success;
}

// Update password with username
// Input parameter:
//  Password
// Main Input Parameter to update specific user:
//  Username
function updatePassword($username, $password)
{
    $success = false;

    try
    {
        // Open a connection to database
        $sqlite = new SQLite3($GLOBALS ["databaseFile"]);
        $sqlite->enableExceptions(true);
        // Prevent SQL Injection
        $query = $sqlite->prepare("UPDATE User SET PASSWORD=:password WHERE USERNAME=:username");
        // Set variables to query
        $query->bindParam(':username',$username);
        $query->bindParam(':password',encrypt($password));
        $query->execute();
        // Clear up the connection
        $sqlite->close();
        $success = true;
    }
    catch (Exception $exception)
    {
        if ($GLOBALS ["sqliteDebug"])
        {
            return $exception->getMessage();
        }
		logError($exception);
    }
    return $success;
}

// Update personal information with username
// Input parameters:
//  First name, Last name, Email, Address Phone
// Main Input Parameter to update specific user:
//  Username
function updatePersonalInfo($username, $fname, $lname, $email, $address, $phone)
{
    $success = false;
	
    try
    {
		// Open a connection to database
        $sqlite = new SQLite3($GLOBALS ["databaseFile"]);
        $sqlite->enableExceptions(true);
		// Prevent SQL Injection
        $query = $sqlite->prepare("UPDATE User SET FIRSTNAME=:fname, LASTNAME=:lname, EMAIL=:email WHERE USERNAME=:username");
		// Set variables to query
        $query->bindParam(':username', $username);
        $query->bindParam(':fname', $fname);
        $query->bindParam(':lname', $lname);
        $query->bindParam(':email', $email);
		
        $result = $query->execute();
        $result->finalize();

        // Prevent SQL Injection
        $query_id = $sqlite->prepare("SELECT ID FROM User WHERE USERNAME=:username");
        // Set variables to query
        $query_id->bindParam(":username", $username);
        $result = $query_id->execute();

        if($record = $result->fetchArray(SQLITE3_ASSOC))
        {
            $result->finalize();
        }
        else
        {
            return "Something went wrong";
        }
        $userId = $record['ID'];

        // Prevent SQL Injection
        $query = $sqlite->prepare("UPDATE UniversityEmployee SET ADDRESS=:address, PHONE=:phone WHERE USER_ID=:userId");
        // Set variables to query
        $query->bindParam(":address", $address);
        $query->bindParam(":phone", $phone);
        $query->bindParam(":userId", $userId);
        $query->execute();
        // Clear up the connection
		$sqlite->close();
		
        $success = true;
    }
    catch (Exception $exception)
    {
        if($GLOBAL ["sqliteDebug"])
        {
            return $exception->getMessage();
        }
		logError($exception);
    }
	
    return $success;
}

// Update professional information with username
// Input parameters:
//  Salary, Title
// Main Input Parameter to update specific user:
//  USER_ID
function updateProfInfo($id, $salary, $title)
{
    $success = false;

    try
    {
		// Open a connection to database
        $sqlite = new SQLite3($GLOBALS ["databaseFile"]);
        $sqlite->enableExceptions(true);

		// Prevent SQL Injection
        $query = $sqlite->prepare("UPDATE UniversityEmployee SET SALARY=:salary, TITLE=:title WHERE USER_ID=:id");
		// Set variables to query
        $query->bindParam(':id', $id);
        $query->bindParam(':salary', floatval($salary));
        $query->bindParam(':title', $title);
        $query->execute();

		// Clear up the connection
        $sqlite->close();
        $success = true;
    } 
    catch(Exception $exception)
    {
        if ($GLOBALS ["sqliteDebug"])
        {
            return $exception->getMessage();
        }
		logError($exception);
    }
	
    return $success;
}

// Get personal information with username
// Input Parameters:
//  Username
function getPersonalInfo($username)
{
	$success = false;

	try
	{
		// Open a connection to database
		$sqlite = new SQLite3($GLOBALS ["databaseFile"]);
		$sqlite->enableExceptions(true);
		// Prevent SQL Injection
		$query = $sqlite->prepare("SELECT * FROM User WHERE USERNAME=:username");
		// Set variables to query
		$query->bindParam(':username', $username);
		$result = $query->execute();
		
		if($record = $result->fetchArray(SQLITE3_ASSOC))
		{
			$result->finalize();
			$sqlite->close();
		
			return $record;
		}
		
	}
	catch(Exception $exception)
	{
		if($GLOBALS ["sqliteDebug"])
		 {
			return $exception->getMessage();
		 }
		 
		 logError($exception);
	}
	
	return $success;
}

// Get professional information (such as salary, title, etc) with ID
// Input Parameters:
//  ID
function getProfessionalInfo($id)
{
	$success = false;

	try
	{
		// Open a connection to database
		$sqlite = new SQLite3($GLOBALS ["databaseFile"]);
		$sqlite-> enableExceptions(true);
		// Prevent SQL Injection
		$query = $sqlite->prepare("SELECT * FROM UniversityEmployee WHERE ID=:id");
		// Set variables to query
		$query->bindParam(':id', $id);
		$result = $query->execute();
		
		if($record = $result->fetchArray(SQLITE3_ASSOC))
		{
			$result->finalize();
			$sqlite->close();
		
			return $record;
		}

	}
	catch(Exception $exception)
	{
		if($GLOBALS ["sqliteDebug"])
		{
			return $exception->getMessage();
		}

		logError($exception);
	}

	return $success;
}

// Creates a new professional user
// Input Parameters:
//  Username, Password, First name, Last name, Email, Role, ManagerID, Title, Address, Salary
function createProf($username, $password, $fname, $lname, $email, $role, $managerId, $title, $address,
	$salary, $phone)
{
	$success = false;

	try
	{
		// Open a connection to database
		$sqlite = new SQLite3($GLOBALS ["databaseFile"]);
		$sqlite->enableExceptions(true);

		// Prevent SQL Injection
		// first check if the username already exists
		$query = $sqlite->prepare("SELECT * FROM User WHERE USERNAME=:username");
		// Set variables to query
		$query->bindParam(':username', $username);
		$result = $query->execute();

		if ($record = $result->fetchArray())
		{
			return "Username Already Exists";
		}

		// for varaible reuse
		$result->finalize();
		
		// Prevent SQL Injection
		$query1 = $sqlite->prepare("INSERT INTO User (USERNAME, PASSWORD, FIRSTNAME, LASTNAME, EMAIL,
			ROLE) VALUES (:username, :password, :fname, :lname, :email, :role)");
		// Set variables to query
		$query1->bindParam(':username', $username);
		$query1->bindParam(':password', encrypt($password));
		$query1->bindParam(':fname', $fname);
		$query1->bindParam(':lname', $lname);
		$query1->bindParam(':email', $email);
		$query1->bindParam(':role', $role);
        
		$result = $query1->execute();
        // Release variable
        $result->finalize();

        // Prevent SQL Injection
        $query_id = $sqlite->prepare("SELECT ID FROM User WHERE USERNAME=:username");
        // Set variables to query
        $query_id->bindParam(":username", $username);
        $result = $query_id->execute();

        if($record = $result->fetchArray(SQLITE3_ASSOC))
        {
            $result->finalize();
        }
        else
        {
            return "Something went wrong";
        }
        $userId = $record['ID'];

		// Prevent SQL Injection
		$query2 = $sqlite->prepare("INSERT INTO UniversityEmployee (USER_ID, MANAGER_ID, TITLE,
			ADDRESS, SALARY, PHONE) VALUES (:userId, :managerId, :title, :address, :salary, :phone)");
		// Set variables to query
		$query2->bindParam(':userId', $userId);
		$query2->bindParam(':managerId', $managerId);
		$query2->bindParam(':title', $title);
		$query2->bindParam(':address', $address);
		$query2->bindParam(':salary', floatval($salary));
		$query2->bindParam(':phone', $phone);

		$query2->execute();

		// clean up any objects
		$sqlite->close();

		// if it gets here without throwing an error, assume success = true;
		$success = true;
	}
	catch (Exception $exception)
	{
		if ($GLOBALS ["sqliteDebug"])
		{
			return $exception->getMessage();
		}
		logError($exception);
	}

	return $success;
}

// Get a list of employees where they are under managerID
// Input Parameters:
//  ID
function getEmployees($id) 
{
    try 
	{
		$sqlite = new SQLite3($GLOBALS ["databaseFile"]);
		$sqlite->enableExceptions(true);
		
		//prepare query to protect from sql injection
		$query = $sqlite->prepare("SELECT * FROM UniversityEmployee WHERE MANAGER_ID=:id");	
		$query->bindParam(":id", $id);
		$result = $query->execute();
		
		$record = array();
		//$sqliteResult = $sqlite->query($queryString);
		while($emp=$result->fetchArray(SQLITE3_ASSOC))
		{
			array_push($record, $emp);
		}
		$result->finalize();
		// clean up any objects
		$sqlite->close();
		return $record;
	}
	catch (Exception $exception)
	{
		if ($GLOBALS ["sqliteDebug"]) 
		{
			return $exception->getMessage();
		}
		logError($exception);
	}
	return "ManagerID is not found";
}

// Mark a University Employee as terminated
// Input Parameters: 
//  ID
function terminate($id)
{
	$success = false;
	try
	{
		// Open a connection to database
        $sqlite = new SQLite3($GLOBALS ["databaseFile"]);
        $sqlite->enableExceptions(true);

		// Prevent SQL Injection
		$terminated = 1;
        $query = $sqlite->prepare("UPDATE UniversityEmployee SET IS_TERMINATED=:terminated WHERE USER_ID=:id");
		// Set variables to query
        $query->bindParam(':id', $id);
        $query->bindParam(':terminated', $terminated);
        $query->execute();

		// Clear up the connection
        $sqlite->close();
        $success = true;
    } 
    catch(Exception $exception)
    {
        if ($GLOBALS ["sqliteDebug"])
        {
            return $exception->getMessage();
        }
		logError($exception);
    }
	
    return $success;
}

// Remove University Employee from the data base
// Input Parameters: 
//  ID
function removeEmployee($id)
{
	$success = false;
	try
	{
		// Open a connection to database
        $sqlite = new SQLite3($GLOBALS ["databaseFile"]);
        $sqlite->enableExceptions(true);

        // Prevent SQL Injection
        $query = $sqlite->prepare("SELECT MANAGER_ID FROM UniversityEmployee WHERE USER_ID=:id");
        // Set variables to query
        $query->bindParam(':id', $id);
        $result = $query->execute();

        if($record = $result->fetchArray(SQLITE3_ASSOC))
        {
            $result->finalize();
        }
        $managerID = $record["MANAGER_ID"];

        // Prevent SQL Injection
        $query2 = $sqlite->prepare("UPDATE UniversityEmployee SET MANAGER_ID=:managerID WHERE MANAGER_ID=:id");
		// Set variables to query
		$query2->bindParam(':managerID', $managerID);
        $query2->bindParam(':id', $id);
        $query2->execute();

		// Prevent SQL Injection
        $query3 = $sqlite->prepare("DELETE FROM UniversityEmployee WHERE USER_ID=:id");
		// Set variables to query
        $query3->bindParam(':id', $id);
        $query3->execute();

        // Prevent SQL Injection
        $query4 = $sqlite->prepare("DELETE FROM User WHERE ID=:id");
        // Set variables to query
        $query4->bindParam(':id', $id);
        $query4->execute();

		// Clear up the connection
        $sqlite->close();
        $success = true;
    } 
    catch(Exception $exception)
    {
        if ($GLOBALS ["sqliteDebug"])
        {
            return $exception->getMessage();
        }
		logError($exception);
    }
	
    return $success;
}


/////////////////////////
//Facilities Management//
/////////////////////////

// Switchboard to Facilities Management Functions
function facility_management_switch($getFunctions)
{
	// Define the possible Facilities Management function URLs which the page can be accessed from
	$possible_function_url = array("getClassrooms", "addClassroom", "getClassroom", "updateClassroom", "deleteClassroom", "reserveClassroom", "searchClassrooms", "addDevice", "getDevices", "getDevice", "updateDevice", "deleteDevice");

	if ($getFunctions)
	{
		return $possible_function_url;
	}
	
	if (isset($_GET["function"]) && in_array($_GET["function"], $possible_function_url))
	{
		switch ($_GET["function"])
		{
			case "getClassrooms":
				return getClassrooms();
			case "addClassroom":
				if (isset($_POST["building"]) && isset($_POST["room"]) && isset($_POST["capacity"])) 
				{
					return addClassroom($_POST["building"], $_POST["room"], $_POST["capacity"]);
				}
				else 
				{
					logError("Missing parameters. addClassroom requires: building, room, capacity");
					return FALSE;
				}
			case "getClassroom":
				if (isset($_GET["id"])) 
				{
					return getClassroom($_GET["id"]);
				}
				else 
				{
					logError("Missing parameters. getClassroom requires: id");
					return FALSE;
				}
			case "updateClassroom":
				if (isset($_POST["id"]) && isset($_POST["building"]) && isset($_POST["room"]) && isset($_POST["capacity"])) 
				{
					return updateClassroom($_POST["id"], $_POST["building"], $_POST["room"], $_POST["capacity"]);
				}
				else 
				{
					logError("Missing parameters. updateClassroom requires: id");
					return FALSE;
				}
			case "deleteClassroom":
				if (isset($_POST["id"])) 
				{
					return deleteClassroom($_POST["id"]);
				}
				else 
				{
					logError("Missing parameters. deleteClassroom requires: id");
					return FALSE;
				}
			case "reserveClassroom":
				if (isset($_POST["id"]) && isset($_POST["day"]) && isset($_POST["section"]) && isset($_POST["timeslot"]) && isset($_POST["length"])) 
				{
					return reserveClassroom($_POST["id"], $_POST["day"], $_POST["section"], $_POST["timeslot"], $_POST["length"]);
				}
				else 
				{
					logError("Missing parameters. reserveClassroom requires: id, section, day, timeslot");
					return FALSE;
				}
			case "searchClassrooms":
				if (isset($_GET["size"]) && isset($_GET["semester"]) && isset($_GET["day"]) && isset($_GET["length"])) 
				{
					return searchClassrooms($_GET["size"], $_GET["semester"], $_GET["day"], $_GET["length"]);
				}
				else 
				{
					logError("Missing parameters. searchClassrooms requires: size, semester, day, length");
					return FALSE;
				}
			case "addDevice":
				if (isset($_POST["name"]) && isset($_POST["condition"])) 
				{
					return addDevice($_POST["name"], $_POST["condition"]);
				}
				else 
				{
					logError("Missing parameters. addDevice requires: name, condition");
					return FALSE;
				}
			case "getDevice":
				if (isset($_GET["id"])) 
				{
					return getDevice($_GET["id"]);
				}
				else 
				{
					logError("Missing parameters. getDevice requires: id");
					return FALSE;
				}
			case "getDevices":
				return getDevices();
			case "updateDevice":
				if (isset($_POST["id"]) && isset($_POST["condition"]) && isset($_POST["name"])) 
				{
					return updateDevice($_POST["id"], $_POST["condition"], $_POST["checkoutDate"], $_POST["name"], $_POST["userId"]);
				}
				else 
				{
					logError("Missing parameters. updateDevice requires: id, condition, name, userId,");
					return FALSE;
				}
			case "deleteDevice":
				if (isset($_POST["uid"])) 
				{
					return deleteDevice($_POST["uid"]);
				}
				else 
				{
					logError("Missing parameter. deleteDevice requires: uid");
					return FALSE;
				}
		}
	}
	else
	{
		return "Function does not exist.";
	}
}

//Define Functions Here

function getClassrooms(){
	$sqlite = new SQLite3($GLOBALS ["databaseFile"]);
	$sqlite->enableExceptions(true);
	$query = $sqlite->prepare("SELECT * FROM Classroom");
	$result = $query->execute();

	$records = array();
	
	while($row = $result->fetchArray(SQLITE3_ASSOC)) {	
		array_push($records, $row);
	}
	
	$result->finalize();
    $sqlite->close();

	return $records;
}

function addClassroom($building, $room, $capacity)
{
	$sqlite = new SQLite3($GLOBALS ["databaseFile"]);
	$sqlite->enableExceptions(true);
	
	$query = $sqlite->prepare("INSERT INTO Classroom (BUILDING_ID, ROOM_NUM, CAPACITY) VALUES (:building, :room, :capacity)");
	$query->bindParam(':building', $building);
	$query->bindParam(':room', $room);
	$query->bindParam(':capacity', $capacity);
	
	$result = $query->execute();
	
	$result->finalize();
	$sqlite->close();
	
	return $result;
}

function getClassroom($id)
{
	$sqlite = new SQLite3($GLOBALS ["databaseFile"]);
	$sqlite->enableExceptions(true);
	
	$query = $sqlite->prepare("SELECT * FROM Classroom WHERE ID=:id");
	$query->bindParam(':id', $id);		
	$result = $query->execute();
	
	if ($record = $result->fetchArray(SQLITE3_ASSOC)) 
    {
        $result->finalize();
        $sqlite->close();
        return $record;
    }
	
	return $result;
}

function updateClassroom($id, $capacity, $rmNumber, $bid)
{
	$sqlite = new SQLite3($GLOBALS ["databaseFile"]);
	$sqlite->enableExceptions(true);
	
	$query = $sqlite->prepare("UPDATE Classroom SET CAPACITY = :capacity, ROOM_NUM = :rmNumber, BUILDING_ID = :bid WHERE ID=:id");
	$query->bindParam(':id', $id);		
	$query->bindParam(':capacity', $capacity);		
	$query->bindParam(':rmNumber', $rmNumber);		
	$query->bindParam(':bid', $bid);		
	$result = $query->execute();
	
	$result->finalize();
	$sqlite->close();
	
	return $result;
}

function deleteClassroom($id)
{
	$sqlite = new SQLite3($GLOBALS ["databaseFile"]);
	$sqlite->enableExceptions(true);
	
	$query = $sqlite->prepare("DELETE FROM Classroom WHERE ID = :id");
	$query->bindParam(':id', $id);		
	$result = $query->execute();
	
	$result->finalize();
	$sqlite->close();
	
	return $result;
}

function reserveClassroom($id, $day, $section, $timeslot, $length)
{
	$sqlite = new SQLite3($GLOBALS ["databaseFile"]);
	$sqlite->enableExceptions(true);

	$query = $sqlite->prepare("INSERT INTO Reservation (CLASSROOM_ID, SECTION_ID, DAY_OF_WEEK, TIME_SLOT_START, DURATION) VALUES (:id, :sectionId, :day, :timeslot, :length)");
	$query->bindParam(':id', $id);
	$query->bindParam(':sectionId', $section);
	$query->bindParam(':day', $day);
	$query->bindParam(':timeslot', $timeslot);
	$query->bindParam(':length', $length);
	$result = $query->execute();
	
	$result->finalize();
	
	// clean up any objects
	$sqlite->close();
	
	return $result;
}

function getValidClassroomTimes($classrooms, $reservations, $length)
{
	$classroomTimes = array();
	$classroomStartTimes = array();
	
	foreach ($classrooms as $room) {
		$roomId = $room["ID"];
		// Initially all timeslots are available
		$classroomTimes[$roomId] = range(1, 13);
		$classroomStartTimes[$roomId] = array();
	}
	
	foreach($reservations as $res) {
		$roomId = $res["RES_CLASSROOM_ID"];
		$start_time = $res["TIME_SLOT_START"];
		$duration = $res["DURATION"];

		for($i = $start_time; $i < $start_time + $duration; $i++){
			$iArray = array($i);
			$classroomTimes[$roomId] = array_diff($classroomTimes[$roomId], $iArray);
		}
	}

	foreach ($classroomTimes as $roomId => $times) {
		foreach($times as $timeslot){
			$valid = true;
			
			for($i = $timeslot + 1; $i <= $timeslot + $length - 1; $i++){
				if(!in_array($i, $times)){
					$valid = false;
				}
			}

			if($valid){
				array_push($classroomStartTimes[$roomId], $timeslot);
			}
		}
	}

	return $classroomStartTimes; 
}

function searchClassrooms($capacity, $term, $day, $length)
{
	try
	{
		$sqlite = new SQLite3($GLOBALS ["databaseFile"]);
		$sqlite->enableExceptions(true);
		$query = $sqlite->prepare("SELECT * FROM Classroom WHERE CAPACITY >= :capacity");
		$query->bindParam(':capacity', $capacity);
		$result = $query->execute();
		$classrooms = array();

		while($row = $result->fetchArray(SQLITE3_ASSOC)) {	
			array_push($classrooms, $row);
		}
		$result->finalize();
		
		$query2 = $sqlite->prepare("SELECT Reservation.CLASSROOM_ID as RES_CLASSROOM_ID, * FROM Reservation INNER JOIN Section WHERE Section.TERM_ID=:term AND Reservation.DAY_OF_WEEK=:day");
		$query2->bindParam(':term', $term);
		$query2->bindParam(':day', $day);
		$result2 = $query2->execute();
		$reservations = array();
		
		while($row = $result2->fetchArray(SQLITE3_ASSOC)) {	
			array_push($reservations, $row);
		}

		$result2->finalize();
		$sqlite->close();

		return getValidClassroomTimes($classrooms, $reservations, $length);
	}
	catch (Exception $exception)
	{
		echo $exception;
		if ($GLOBALS ["sqliteDebug"])
		{
			return $exception->getMessage();
		}
		logError($exception);
	}
}


function addDevice($name, $condition)
{
	$sqlite = new SQLite3($GLOBALS ["databaseFile"]);
	$sqlite->enableExceptions(true);
	
	$query = $sqlite->prepare("INSERT INTO Device (NAME, CONDITION) VALUES (:name, :condition)");
	$query->bindParam(':name', $name);
	$query->bindParam(':condition', $condition);

	$result = $query->execute();
	
	$result->finalize();
	$sqlite->close();
	
	return $result;
}

function getDevices()
{
	$sqlite = new SQLite3($GLOBALS ["databaseFile"]);
	$sqlite->enableExceptions(true);
	$query = $sqlite->prepare("SELECT * FROM Device");
	$result = $query->execute();

	$records = array();
	
	while($row = $result->fetchArray(SQLITE3_ASSOC)) {	
		array_push($records, $row);
	}
	
	$result->finalize();
    $sqlite->close();

	return $records;
}

function getDevice($id)
{
	$sqlite = new SQLite3($GLOBALS ["databaseFile"]);
	$sqlite->enableExceptions(true);

	$query = $sqlite->prepare("SELECT * FROM Device WHERE ID=:id");
    	$query->bindParam(':id', $id);        

	$result = $query->execute();
	
    if ($record = $result->fetchArray(SQLITE3_ASSOC)) 
    {
        $result->finalize();
        $sqlite->close();
        return $record;
    }
}

function updateDevice($id, $condition, $checkoutDate, $name, $userId)
{
	$sqlite = new SQLite3($GLOBALS ["databaseFile"]);
	$sqlite->enableExceptions(true);
	
	$query = $sqlite->prepare("UPDATE Device SET CONDITION = :condition, CHECK_OUT_DATE = :checkoutDate, NAME = :name, USER_ID = :userId WHERE ID = :id");
	$query->bindParam(':condition', $condition);
	$query->bindParam(':id', $id);
	$query->bindParam(':name', $name);
	$query->bindParam(':userId', $userId);
	$query->bindParam(':checkoutDate', $checkoutDate);
	$result = $query->execute();
	
	$result->finalize();
	$sqlite->close();
	
	return $result;
}

function deleteDevice($uid)
{
	$sqlite = new SQLite3($GLOBALS ["databaseFile"]);
	$sqlite->enableExceptions(true);
	
	$query = $sqlite->prepare("DELETE FROM Device WHERE ID = :uid");
	$query->bindParam(':uid', $uid);
	$result = $query->execute();
	
	$result->finalize();
	$sqlite->close();
	
	return $result;
} 


//////////////////////
//Student Enrollment//
//////////////////////

// Switchboard to Student Enrollment Functions
function student_enrollment_switch($getFunctions)
{
	// Define the possible Student Enrollment function URLs which the page can be accessed from
	$possible_function_url = array("getCourseList", "toggleSection", "getSection", "getCourseSections",
					"postSection", "toggleCourse", "getStudentSections", "getProfessorSections",
					"getTerms", "getTerm", "postTerm", "enrollStudent", "getPreReqs",
					"waitlistStudent", "withdrawStudent", "getSectionEnrolled", "getSectionWaitlist",
					"getStudentUser");
				
	if ($getFunctions)
	{
		return $possible_function_url;
	}
	
	if (isset($_GET["function"]) && in_array($_GET["function"], $possible_function_url))
	{
		switch ($_GET["function"])
		{
			// returns list of all courses in database
			// params: none
			case "getCourseList":
				return getCourseList();
			// Calls function that toggles availability of section
			// returns: "Success" or Error Statement
			// params: sectionID
			case "toggleSection":
				if (isset($_POST["sectionID"]) && $_POST["sectionID"] != null)
				{
					return toggleSection($_POST["sectionID"]);
				}
				else
				{
					return "Missing sectionID";
				}
			// returns: information about desired course
			// params: sectionID
			case "getSection":
				if (isset($_GET["sectionID"]) && $_GET["sectionID"] != null)
				{
					return getSection($_GET["sectionID"]);
				}
				else
				{
					return "Missing sectionID parameter";
				}
			// returns: list of all sections of a course
			// params: courseID
			case "getCourseSections":
				if (isset($_GET["courseID"]) && $_GET["courseID"] != null)
				{
					return getCourseSections($_GET["courseID"]);
				}
				else
				{
					return "Missing courseID param";
				}
			// returns: "Success" or Error Statement
			// params: maxStudents, professorID, courseID, termID, classroomID
			case "postSection":
				if ((isset($_POST["maxStudents"]) && $_POST["maxStudents"] != null)
					&& (isset($_POST["professorID"]) && $_POST["professorID"] != null)
					&& (isset($_POST["courseID"]) && $_POST["courseID"] != null)
					&& (isset($_POST["termID"]) && $_POST["termID"] != null)
					&& (isset($_POST["classroomID"]) && $_POST["classroomID"] != null)
				){
					return postSection($_POST["maxStudents"],
							$_POST["professorID"],
							$_POST["courseID"],
							$_POST["termID"],
							$_POST["classroomID"]);
				}
				else
				{
					return "Missing a parameter";
				}	
			// Calls function that toggles availability of course
			// returns: "Success" or Error Statement
			// params: courseID
			case "toggleCourse":
				if (isset($_POST["courseID"]) && $_POST["courseID"] != null)
				{
					return toggleCourse($_POST["courseID"]);
				}
				else
				{
					return "Missing courseID";
				}
			// returns: object array of a student's enrolled and waitlisted sections
			// params: studentID
			case "getStudentSections":
				if (isset($_GET["studentID"]) && $_GET["studentID"] != null)
				{
					return getStudentSections($_GET["studentID"]);
				}
				else
				{
					return "Missing studentID param";
				}
			// returns: object array of a professor's sections
			// params: professorID
			case "getProfessorSections":
				if (isset($_GET["professorID"]) && $_GET["professorID"] != null)
				{
					return getProfessorSections($_GET["professorID"]);
				}
				else
				{
					return "Missing professorID param";
				}
			// returns: current term
			// params: none
			case "getTerms":
				return getTerms();
			// returns: term object
			// params: termCode
			case "getTerm":
				if (isset($_GET["termCode"]) && $_GET["termCode"] != null)
				{
					return getTerm($_GET["termCode"]);
				}
				else
				{
					return "Missing termCode param";
				}
			// returns: "Success" or Error Statement
			// params: termCode, startDate, endDate
			case "postTerm":
				if ((isset($_POST["termCode"]) && $_POST["termCode"] != null)
					&& (isset($_POST["startDate"]) && $_POST["startDate"] != null)
					&& (isset($_POST["endDate"]) && $_POST["endDate"] != null)
				){
					return postTerm($_POST["termCode"],
							$_POST["startDate"],
							$_POST["endDate"]);
				}
				else
				{
					return "Missing a parameter";
				}
			// returns: "Success" or Error Statement
			// params: studentID, sectionID
			case "enrollStudent":
				if ((isset($_POST["studentID"]) && $_POST["studentID"] != null)
					&& (isset($_POST["sectionID"]) && $_POST["sectionID"] != null)
				){
					return enrollStudent($_POST["studentID"], $_POST["sectionID"]);
				}
				else
				{
					return "Missing a parameter";
				}
			// returns: "Success" or Error Statement
			// params: studentID, sectionID
			case "waitlistStudent":
				if ((isset($_POST["studentID"]) && $_POST["studentID"] != null)
					&& (isset($_POST["sectionID"]) && $_POST["sectionID"] != null)
				){
					return waitlistStudent($_POST["studentID"], $_POST["sectionID"]);
				}
				else
				{
					return "Missing a parameter";
				}
			// returns: "Success" or Error Statement
			// params: studentID, sectionID
			case "withdrawStudent":
				if ((isset($_POST["studentID"]) && $_POST["studentID"] != null)
					&& (isset($_POST["sectionID"]) && $_POST["sectionID"] != null)
				){
					return withdrawStudent($_POST["studentID"], $_POST["sectionID"]);
				}
				else
				{
					return "Missing a parameter";
				}
			// returns: enrolled student_ids of a section
			// params: sectionID
			case "getSectionEnrolled":
				if (isset($_GET["sectionID"]) && $_GET["sectionID"] != null)
				{
					return getSectionEnrolled($_GET["sectionID"]);
				}
				else
				{
					return "Missing sectionID parameter";
				}
			// returns: waitlisted student_ids of a section
			// params: sectionID
			case "getSectionWaitlist":
				if (isset($_GET["sectionID"]) && $_GET["sectionID"] != null)
				{
					return getSectionWaitlist($_GET["sectionID"]);
				}
				else
				{
					return "Missing sectionID parameter";
				}
			// returns: both the user and student data of a Student User
			// params: userID
			case "getStudentUser":
				if (isset($_GET["userID"]) && $_GET["userID"] != null)
				{
					return getStudentUser($_GET["userID"]);
				}
				else
				{
					return "Missing userID parameter";
				}
			// returns: the prereqs of a course
			// params: courseID
			case "getPreReqs":
				if (isset($_GET["courseID"]) && $_GET["courseID"] != null)
				{
					return getPreReqs($_GET["courseID"]);
				}
				else
				{
					return "Missing courseID parameter";
				}
		}
	}
	else
	{
		return "Function does not exist.";
	}
}

//Student Enrollment Functions

function getCourseList()
{
	try
	{
		$sqlite = new SQLite3($GLOBALS ["databaseFile"]);
		$sqlite->enableExceptions(true);
		
		//prepare query to protect from sql injection
		$query = $sqlite->prepare("SELECT * FROM Course");		
		$result = $query->execute();
		
		$record = array();
		while($arr=$result->fetchArray(SQLITE3_ASSOC))
		{
			array_push($record, $arr);
		}
		$result->finalize();
		// clean up any objects
		$sqlite->close();
		return $record;
	}
	catch (Exception $exception)
	{
		if ($GLOBALS ["sqliteDebug"]) 
		{
			return $exception->getMessage();
		}
		logError($exception);
	}
}

function toggleSection($sectionID)
{
	try 
	{
		$sqlite = new SQLite3($GLOBALS ["databaseFile"]);
		$sqlite->enableExceptions(true);
		
		//prepare query to protect from sql injection
		$query = $sqlite->prepare("SELECT * FROM Section WHERE ID=:sectionID");
		$query->bindParam(':sectionID', $sectionID);		
		$result = $query->execute();
		
		

		if ($record = $result->fetchArray()) 
		{
			if ($record['AVAILABILITY'] == "0")
			{
				//prepare query to protect from sql injection
				$queryInner = $sqlite->prepare("UPDATE Section SET AVAILABILITY = '1' WHERE ID =:sectionID");
				$queryInner->bindParam(':sectionID', $sectionID);		
				$resultInner = $queryInner->execute();
				
				$result = $resultInner;
			}
			else
			{
				//prepare query to protect from sql injection
				$queryInner = $sqlite->prepare("UPDATE Section SET AVAILABILITY = '0' WHERE ID =:sectionID");
				$queryInner->bindParam(':sectionID', $sectionID);		
				$resultInner = $queryInner->execute();
				
				$result = $resultInner;
			}
		}
	
		$result->finalize();
		
		// clean up any objects
		$sqlite->close();
		return "Success";
	}
	catch (Exception $exception)
	{
		if ($GLOBALS ["sqliteDebug"]) 
		{
			return $exception->getMessage();
		}
		logError($exception);
	}
}

function toggleCourse($courseID)
{
	try 
	{
		$sqlite = new SQLite3($GLOBALS ["databaseFile"]);
		$sqlite->enableExceptions(true);
		
		//prepare query to protect from sql injection
		$query = $sqlite->prepare("SELECT * FROM Course WHERE ID=:courseID");
		$query->bindParam(':courseID', $courseID);		
		$result = $query->execute();
		
		

		if ($record = $result->fetchArray()) 
		{
			if ($record['AVAILABILITY'] == "0")
			{
				//prepare query to protect from sql injection
				$queryInner = $sqlite->prepare("UPDATE Course SET AVAILABILITY = '1' WHERE ID =:courseID");
				$queryInner->bindParam(':courseID', $courseID);		
				$resultInner = $queryInner->execute();
				
				$result = $resultInner;
			}
			else
			{
				//prepare query to protect from sql injection
				$queryInner = $sqlite->prepare("UPDATE Course SET AVAILABILITY = '0' WHERE ID =:courseID");
				$queryInner->bindParam(':courseID', $courseID);		
				$resultInner = $queryInner->execute();
				
				$result = $resultInner;
			}
		}
	
		$result->finalize();
		
		// clean up any objects
		$sqlite->close();
		return "Success";
	}
	catch (Exception $exception)
	{
		if ($GLOBALS ["sqliteDebug"]) 
		{
			return $exception->getMessage();
		}
		logError($exception);
	}
}

function getSection($sectionID)
{
	try
	{
		$sqlite = new SQLite3($GLOBALS ["databaseFile"]);
		$sqlite->enableExceptions(true);
		
		//prepare query to protect from sql injection
		$query = $sqlite->prepare("SELECT * FROM Section WHERE ID=:sectionID");
		$query->bindParam(':sectionID', $sectionID);
		$result = $query->execute();
		
		if ($record = $result->fetchArray(SQLITE3_ASSOC)) 
		{
			$result->finalize();
			// clean up any objects
			$sqlite->close();
			return $record;
		}
	}
	catch (Exception $exception)
	{
		if ($GLOBALS ["sqliteDebug"]) 
		{
			return $exception->getMessage();
		}
		logError($exception);
	}
}

function getSectionEnrolled($sectionID)
{
	try
	{
		$sqlite = new SQLite3($GLOBALS ["databaseFile"]);
		$sqlite->enableExceptions(true);
		
		//prepare query to protect from sql injection
		$query = $sqlite->prepare("SELECT STUDENT_ID FROM Student_Section WHERE SECTION_ID=:sectionID");
		$query->bindParam(':sectionID', $sectionID);
		$result = $query->execute();
		
		$record = array();
		while($arr=$result->fetchArray(SQLITE3_ASSOC)) 
		{
			array_push($record, $arr);
		}
		
		$result->finalize();
		// clean up any objects
		$sqlite->close();
		return $record;
	}
	catch (Exception $exception)
	{
		if ($GLOBALS ["sqliteDebug"]) 
		{
			return $exception->getMessage();
		}
		logError($exception);
	}
}

function getSectionWaitlist($sectionID)
{
	try
	{
		$sqlite = new SQLite3($GLOBALS ["databaseFile"]);
		$sqlite->enableExceptions(true);
		
		//prepare query to protect from sql injection
		$query = $sqlite->prepare("SELECT STUDENT_ID FROM Waitlist WHERE SECTION_ID=:sectionID");
		$query->bindParam(':sectionID', $sectionID);
		$result = $query->execute();
		
		$record = array();
		while($arr=$result->fetchArray(SQLITE3_ASSOC))
		{
			array_push($record, $arr);
		}
		$result->finalize();
		// clean up any objects
		$sqlite->close();
		return $record;
	}
	catch (Exception $exception)
	{
		if ($GLOBALS ["sqliteDebug"]) 
		{
			return $exception->getMessage();
		}
		logError($exception);
	}
}

function getCourseSections($courseID)
{
	try
	{
		$sqlite = new SQLite3($GLOBALS ["databaseFile"]);
		$sqlite->enableExceptions(true);
		
		//prepare query to protect from sql injection
		$query = $sqlite->prepare("SELECT * FROM Section WHERE COURSE_ID=:courseID AND AVAILABILITY=1");
		$query->bindParam(':courseID', $courseID);
		$result = $query->execute();
		
		$record = array();
		while($arr=$result->fetchArray(SQLITE3_ASSOC))
		{
			array_push($record, $arr);
		}
		$result->finalize();
		// clean up any objects
		$sqlite->close();
		return $record;
	}
	catch (Exception $exception)
	{
		if ($GLOBALS ["sqliteDebug"]) 
		{
			return $exception->getMessage();
		}
		logError($exception);
	}
}

function postSection($maxStudents, $professorID, $courseID, $termID, $classroomID)
{
	try
	{
		$sqlite = new SQLite3($GLOBALS ["databaseFile"]);
		$sqlite->enableExceptions(true);
		
		$query = $sqlite->prepare("INSERT INTO Section (MAX_STUDENTS, PROFESSOR_ID, COURSE_ID, TERM_ID, CLASSROOM_ID) VALUES (:maxStudents, :professorID, :courseID, :termID, :classroomID)");
		$query->bindParam(':maxStudents', $maxStudents);
		$query->bindParam(':professorID', $professorID);
		$query->bindParam(':courseID', $courseID);
		$query->bindParam(':termID', $termID);
		$query->bindParam(':classroomID', $classroomID);
		$result = $query->execute();
		
		$result->finalize();
		// clean up any objects
		$sqlite->close();
		return "Success";
	}
	catch (Exception $exception)
	{
		if ($GLOBALS ["sqliteDebug"]) 
		{
			return $exception->getMessage();
		}
		logError($exception);
	}
}

function getStudentSections($studentID)
{
	try
	{
		$sqlite = new SQLite3($GLOBALS ["databaseFile"]);
		$sqlite->enableExceptions(true);
		
		//prepare query to protect from sql injection
		$query = $sqlite->prepare("SELECT SECTION_ID FROM Student_Section WHERE STUDENT_ID=:studentID");
		$query->bindParam(':studentID', $studentID);
		$result = $query->execute();
		
		$record = array();
		while($arr=$result->fetchArray(SQLITE3_ASSOC))
		{
			array_push($record, $arr);
		}
		$result->finalize();
		// clean up any objects
		$sqlite->close();
		return $record;
	}
	catch (Exception $exception)
	{
		if ($GLOBALS ["sqliteDebug"]) 
		{
			return $exception->getMessage();
		}
		logError($exception);
	}
}

function getProfessorSections($professorID)
{
	try
	{
		$sqlite = new SQLite3($GLOBALS ["databaseFile"]);
		$sqlite->enableExceptions(true);
		
		//prepare query to protect from sql injection
		$query = $sqlite->prepare("SELECT * FROM Section WHERE PROFESSOR_ID=:professorID");
		$query->bindParam(':professorID', $professorID);
		$result = $query->execute();
		
		$record = array();
		while($arr=$result->fetchArray(SQLITE3_ASSOC)) 
		{
			array_push($record, $arr);
		}
		$result->finalize();
		// clean up any objects
		$sqlite->close();
		return $record;
	}
	catch (Exception $exception)
	{
		if ($GLOBALS ["sqliteDebug"]) 
		{
			return $exception->getMessage();
		}
		logError($exception);
	}
}

function getTerms()
{
	try
	{
		$sqlite = new SQLite3($GLOBALS ["databaseFile"]);
		$sqlite->enableExceptions(true);
		
		//prepare query to protect from sql injection
		$query = $sqlite->prepare("SELECT * FROM Term");
		$result = $query->execute();
		
		$record = array();
		while($arr=$result->fetchArray(SQLITE3_ASSOC))
		{
			array_push($record, $arr);
		}
		$result->finalize();
		// clean up any objects
		$sqlite->close();
		return $record;
	}
	catch (Exception $exception)
	{
		if ($GLOBALS ["sqliteDebug"]) 
		{
			return $exception->getMessage();
		}
		logError($exception);
	}
}

function getTerm($termCode)
{
	try
	{
		$sqlite = new SQLite3($GLOBALS ["databaseFile"]);
		$sqlite->enableExceptions(true);
		
		//prepare query to protect from sql injection
		$query = $sqlite->prepare("SELECT * FROM Term WHERE CODE=:code");
		$query->bindParam(':code', $termCode);
		$result = $query->execute();
		
		if ($record = $result->fetchArray(SQLITE3_ASSOC)) 
		{
			$result->finalize();
			// clean up any objects
			$sqlite->close();
			return $record;
		}
	}
	catch (Exception $exception)
	{
		if ($GLOBALS ["sqliteDebug"]) 
		{
			return $exception->getMessage();
		}
		logError($exception);
	}
}

function postTerm($termCode, $startDate, $endDate)
{
	try
	{
		$sqlite = new SQLite3($GLOBALS ["databaseFile"]);
		$sqlite->enableExceptions(true);
		
		$query = $sqlite->prepare("INSERT INTO Term (CODE, START_DATE, END_DATE) VALUES (:code, :start_date, :end_date)");
		$query->bindParam(':code', $termCode);
		$query->bindParam(':start_date', $startDate);
		$query->bindParam(':end_date', $endDate);
		$result = $query->execute();
		
		$result->finalize();
		// clean up any objects
		$sqlite->close();
		return "Success";
	}
	catch (Exception $exception)
	{
		if ($GLOBALS ["sqliteDebug"]) 
		{
			return $exception->getMessage();
		}
		logError($exception);
	}
}

function enrollStudent($studentID, $sectionID)
{
	try
	{
		$sqlite = new SQLite3($GLOBALS ["databaseFile"]);
		$sqlite->enableExceptions(true);
		
		$query = $sqlite->prepare("INSERT INTO Student_Section (STUDENT_ID, SECTION_ID) VALUES (:studentID, :sectionID)");
		$query->bindParam(':studentID', $studentID);
		$query->bindParam(':sectionID', $sectionID);
		$result = $query->execute();
		
		$result->finalize();
		// clean up any objects
		$sqlite->close();
		return "Success";
	}
	catch (Exception $exception)
	{
		if ($GLOBALS ["sqliteDebug"]) 
		{
			return $exception->getMessage();
		}
		logError($exception);
	}
}

function waitlistStudent($studentID, $sectionID)
{
	try
	{
		date_default_timezone_set('America/New_York');
		$addedDate = date('m-d-Y');
		$sqlite = new SQLite3($GLOBALS ["databaseFile"]);
		$sqlite->enableExceptions(true);
		
		$query = $sqlite->prepare("INSERT INTO Waitlist (SECTION_ID, STUDENT_ID, ADDED_DATE) VALUES (:sectionID, :studentID, :addedDate)");
		$query->bindParam(':sectionID', $sectionID);
		$query->bindParam(':studentID', $studentID);
		$query->bindParam(':addedDate', $addedDate);
		$result = $query->execute();
		
		$result->finalize();
		// clean up any objects
		$sqlite->close();
		return "Success";
	}
	catch (Exception $exception)
	{
		if ($GLOBALS ["sqliteDebug"]) 
		{
			return $exception->getMessage();
		}
		logError($exception);
	}
}

function withdrawStudent($studentID, $sectionID)
{
	try
	{
		$sqlite = new SQLite3($GLOBALS ["databaseFile"]);
		$sqlite->enableExceptions(true);
		
		$query = $sqlite->prepare("DELETE FROM Student_Section WHERE STUDENT_ID=:studentID AND SECTION_ID=:sectionID");
		$query->bindParam(':studentID', $studentID);
		$query->bindParam(':sectionID', $sectionID);
		$result = $query->execute();
		
		$result->finalize();
		// clean up any objects
		$sqlite->close();
		return "Success";
	}
	catch (Exception $exception)
	{
		if ($GLOBALS ["sqliteDebug"]) 
		{
			return $exception->getMessage();
		}
		logError($exception);
	}
}

function getStudentUser($userID)
{
	try
	{
		$sqlite = new SQLite3($GLOBALS ["databaseFile"]);
		$sqlite->enableExceptions(true);
		
		//prepare query to protect from sql injection
		$query = $sqlite->prepare("SELECT User.ID, User.FIRSTNAME, User.LASTNAME, User.EMAIL, Student.YEAR_LEVEL, Student.GPA FROM User JOIN Student ON Student.USER_ID = User.ID WHERE User.ID=:userID");
		$query->bindParam(':userID', $userID);
		$result = $query->execute();
		
		if ($record = $result->fetchArray(SQLITE3_ASSOC)) 
		{
			$result->finalize();
			// clean up any objects
			$sqlite->close();
			return $record;
		}
	}
	catch (Exception $exception)
	{
		if ($GLOBALS ["sqliteDebug"]) 
		{
			return $exception->getMessage();
		}
		logError($exception);
	}
}

function getPreReqs($courseID)
{
	try
	{
		$sqlite = new SQLite3($GLOBALS ["databaseFile"]);
		$sqlite->enableExceptions(true);
		
		//prepare query to protect from sql injection
		$query = $sqlite->prepare("SELECT PREREQ_COURSE_ID FROM Prerequisite WHERE COURSE_ID=:courseID");
		$query->bindParam(':courseID', $courseID);
		$result = $query->execute();
		
		$record = array();
		while($arr=$result->fetchArray(SQLITE3_ASSOC)) 
		{
			array_push($record, $arr);
		}
		
		$result->finalize();
		// clean up any objects
		$sqlite->close();
		return $record;
	}
	catch (Exception $exception)
	{
		if ($GLOBALS ["sqliteDebug"]) 
		{
			return $exception->getMessage();
		}
		logError($exception);
	}
}



////////////////////
//Co-op Evaluation//
////////////////////

// Switchboard to Co-op Evaluation Functions
function coop_eval_switch($getFunctions)
{
	// Define the possible Co-op Evaluation function URLs which the page can be accessed from
	$possible_function_url = array(
		"getStudentEvaluation", "addStudentEvaluation", "updateStudentEvaluation", "addCompany", "updateCompany",
		"getCompanies", "getEmployers", "updateEmployer", "addEmployer", "getEmployerEvaluation",
		"updateEmployerEvaluation", "addEmployerEvaluation", "getCoopAdvisor", "getCoopInfo"
	);

	if ($getFunctions)
	{
		return $possible_function_url;
	}
	
	if (isset($_GET["function"]) && in_array($_GET["function"], $possible_function_url))
	{
		switch ($_GET["function"])
		{
			case "getStudentEvaluation":
				if (isset($_GET['STUDENTID']) && isset($_GET['COMPANYID']))
				{
					return getStudentEvaluation($_GET["STUDENTID"], $_GET["COMPANYID"]);
				}
				else
				{
					return NULL;
				}
			case "addStudentEvaluation":
				if (isset($_POST['STUDENTID']) && isset($_POST['COMPANYID']))
				{
					return addStudentEvaluation(array(
						'studentID'=>$_POST['StudentID'],
						'companyID'=>$_POST['CompanyID'],
						'name'=>$_POST['name'],
						'email'=>$_POST['email'],
						'ename'=>$_POST['ename'],
						'eemail'=>$_POST['eemail'],
						'position'=>$_POST['position'],
						'q1'=>$_POST['q1'],
						'q2'=>$_POST['q2'],
						'q3'=>$_POST['q3'],
						'q4'=>$_POST['q4'],
						'q5'=>$_POST['q5']			
					));
				}
				else 
				{
					return NULL;
				}
			case "updateStudentEvaluation":
				if (isset($_POST['STUDENTID']) && isset($_POST['COMPANYID']))
				{
					return updateStudentEvaluation(array(
						'studentID'=>$_POST['StudentID'],
						'companyID'=>$_POST['CompanyID'],
						'name'=>$_POST['name'],
						'email'=>$_POST['email'],
						'eemail'=>$_POST['eemail'],
						'position'=>$_POST['position'],
						'q1'=>$_POST['q1'],
						'q2'=>$_POST['q2'],
						'q3'=>$_POST['q3'],
						'q4'=>$_POST['q4'],
						'q5'=>$_POST['q5']
					));
				}
				else 
				{
					return NULL;
				}
			case "getCompanies":
				if ($_GET['StudentID'])
				{
					return getCompanies($_GET['StudentID']);
				}
				else
				{
					return NULL;
				}
			case "addCompany":
				if ($_POST['StudentID'] && $_POST['name'])
				{
					return addCompany($_POST['StudentID'], $_POST['name'], $_POST['address']);
				}
				else 
				{
					return NULL;
				}
				
			case "updateCompany":
				if (isset($_POST['StudentID']) && isset($_POST['name']))
				{
					return updateCompany($_POST['StudentID'], $_POST['name'], $_POST['address']);
				}
				else 
				{
					return NULL;
				}
				
			case "getEmployers":
				if (isset($_GET['CompanyID']))
				{
					return getEmployer($_GET['CompanyID']);
				}
				else
				{
					return NULL;
				}
			case "updateEmployer":
				if (isset($_POST['CompanyID']) && isset($_POST['ID']))
				{
					return updateEmployer(
					$_POST['ID'], 
					$_POST['CompanyID'],
					$_POST['fname'],
					$_POST['lname'],
					$_POST['email']
					);
				}
				else
				{
					return NULL;
				}
				// return "Missing " . $_GET["param-name"]
			case "addEmployer":
				if (isset($_POST['CompanyID']))
				{
					return addEmployer(
						$_POST['CompanyID'], 
						$_POST['fname'], 
						$_POST['lname'], 
						$_POST['email']
					);
				}
				else
				{
					return NULL;
				}
				// return "Missing " . $_GET["param-name"]
			case "getEmployerEvaluation":
				if (isset($_GET['EMPLOYEEID']) && isset($_GET['COMPANYID']))
				{
					return getEmployerEvaluation($_GET["EMPLOYEEID"], $_GET["COMPANYID"]);
				}
				else
				{
					return NULL;
				}
			case "updateEmployerEvaluation":
				if (isset($_POST['EMPLOYEEID']) && isset($_POST['COMPANYID']))
				{
					return updateEmployerEvaluation(array(
						'employeeID'=>$_POST['EmployeeID'],
						'companyID'=>$_POST['CompanyID'],
						'name'=>$_POST['name'],
						'email'=>$_POST['email'],
						'sname'=>$_POST['sname'],
						'semail'=>$_POST['semail'],
						'position'=>$_POST['position'],
						'q1'=>$_POST['q1'],
						'q2'=>$_POST['q2'],
						'q3'=>$_POST['q3'],
						'q4'=>$_POST['q4'],
						'q5'=>$_POST['q5']			
					));
				}
				else 
				{
					return NULL;
				}
			case "addEmployerEvaluation":
				if (isset($_POST['EMPLOYEEID']) && isset($_POST['COMPANYID']))
				{
					return updateEmployerEvaluation(array(
						'employeeID'=>$_POST['EmployeeID'],
						'companyID'=>$_POST['CompanyID'],
						'name'=>$_POST['name'],
						'email'=>$_POST['email'],
						'sname'=>$_POST['sname'],
						'semail'=>$_POST['semail'],
						'position'=>$_POST['position'],
						'q1'=>$_POST['q1'],
						'q2'=>$_POST['q2'],
						'q3'=>$_POST['q3'],
						'q4'=>$_POST['q4'],
						'q5'=>$_POST['q5']			
					));
				}
				else 
				{
					return NULL;
				}
			case "getCoopAdvisor":
				// if has params
				return getCoopAdvisor();
				// else
				// return "Missing " . $_GET["param-name"]
			case "getCoopInfo":
				// if has params
				return getCoopInfo();
				// else
				// return "Missing " . $_GET["param-name"]
		}
	}
	else
	{
		return "Function does not exist.";
	}
}

//Define Functions Here
function getStudentEvaluation($studentID, $comapanyID)
{
	return "TODO";
}

function addStudentEvaluation($array_params)
{
	return "TODO";
}

function updateStudentEvaluation($array_params)
{
	return "TODO";
}

//Gets all company objects and their asscoiated evaluations
function getCompanies($studentID)
{
	$queryString = "SELECT User.ID, User.USERNAME, CoopCompany.*, CoopEmployee.*, StudentEval.*, EmployeeEval.* FROM User JOIN CoopCompany ON User.ID = CoopCompany.STUDENTID JOIN CoopEmployee ON CoopCompany.ID = CoopEmployee.COMPANYID JOIN StudentEval ON StudentEval.COMPANYID = CoopCompany.ID  AND User.ID = StudentEval.STUDENTID JOIN EmployeeEval ON EmployeeEval.EMPLOYEEID = CoopEmployee.ID AND EmployeeEval.COMPANYID = CoopCompany.ID WHERE User.ID = :studentID";
	try 
		{
			$sqlite = new SQLite3($GLOBALS ["databaseFile"]);
			$sqlite->enableExceptions(true);
			
			//prepare query to protect from sql injection
			$query = $sqlite->prepare($queryString);
			$query->bindParam(':studentID', $studentID);		
			$result = $query->execute();
			
			if ($record = $result->fetchArray(SQLITE3_ASSOC)) 
			{
				$result->finalize();
				$sqlite->close();
				
				return $record;
			}
		
		}
		catch (Exception $exception)
		{
			if ($GLOBALS ["sqliteDebug"]) 
			{
				return $exception->getMessage();
			}
			logError($exception);
		}
}

function addCompany($studentID, $name, $address)
{
		return "TODO";
}

function updateCompany($studentID, $name, $address)
{
	return "TODO";
}

function getEmployers($companyID)
{
	return "TODO";
}

//need ID here because its the only unique identifier
//Maybe this will have to change later
function updateEmployer($ID, $companyID, $fname, $lname, $email)
{
	return "TODO";
}

function addEmployer($companyID, $fname, $lname, $email)
{
	return "TODO";
}

function getEmployerEvaluation($employeeID, $companyID)
{
	return "TODO";
}

function updateEmployerEvaluation($array_params)
{
	return "TODO";
}

function addEmployerEvaluation($array_params)
{
	return "TODO";
}

/* 
Currently these are not used
function getCoopAdvisor()
{
	return "TODO";
}

function getCoopInfo()
{
	return "TODO";
}
*/


///////////
//Grading//
///////////

// Switchboard to Grading Functions
function grading_switch($getFunctions)
{
	// Define the possible Grading function URLs which the page can be accessed from
	$possible_function_url = array(
		"getGradeForStudentSection"
		);

	if ($getFunctions)
	{
		return $possible_function_url;
	}
	
	if (isset($_GET["function"]) && in_array($_GET["function"], $possible_function_url))
	{
		switch ($_GET["function"])
		{
			case "getGradeForStudentSection":
				if (isset($_GET["student_section_id"]))
				{
					return getGradeForStudentSection($_GET["student_section_id"]);
				}
				else
				{
					return "Missing required query param: 'student_section_id'";
				}
		}
	}
	else
	{
		return "Function does not exist.";
	}
}

/**
 *	Retrives the row from the Grade table matching the student_section_id
 *	@param $studentSectionID - the ID matching the studentsection
 */
function getGradeForStudentSection($studentSectionID)
{
	try
	{
		$sqlite = new SQLite3($GLOBALS ["databaseFile"]);
		$sqlite->enableExceptions(true);
		
		//prepare query to protect from sql injection
		$query = $sqlite->prepare("SELECT * FROM Grade WHERE STUDENT_SECTION_ID=:studentSectionID");
		$query->bindParam(':studentSectionID', $studentSectionID);
		$result = $query->execute();
		
		//$sqliteResult = $sqlite->query($queryString);
		if ($record = $result->fetchArray(SQLITE3_ASSOC)) 
		{
			$result->finalize();
			// clean up any objects
			$sqlite->close();
			return $record;
		}
	}
	catch (Exception $exception)
	{
		if ($GLOBALS ["sqliteDebug"]) 
		{
			return $exception->getMessage();
		}
		logError($exception);
	}
}

/////////////////////
//API Master Switch//
/////////////////////

// Define the possible team URLs which the page can be accessed from
$possible_url = array("general", "book_store", "human_resources", "facility_management", "student_enrollment", "coop_eval", "grading");

if (isset($_GET["team"]) && in_array($_GET["team"], $possible_url))
{
	switch ($_GET["team"])
	{
		case "general":
			$result = general_switch(false);
			break;
		case "book_store":
			$result = book_store_switch(false);
			break;
		case "human_resources":
			$result = human_resources_switch(false);
			break;
		case "facility_management":
			$result = facility_management_switch(false);
			break;
		case "student_enrollment":
			$result = student_enrollment_switch(false);
			break;
		case "coop_eval":
			$result = coop_eval_switch(false);
			break;
		case "grading":
			$result = grading_switch(false);
			break;
	}
}


//A utility function to get a list of all availiable API functions
if (isset($_GET["getAllFunctions"]))
{
	$result = array(
		"Teams" => $possible_url,
		"General" => general_switch(true),
		"book_store" => book_store_switch(true),
		"human_resources" => human_resources_switch(true),
		"facility_management" => facility_management_switch(true),
		"student_enrollment" => student_enrollment_switch(true),
		"coop_eval" => coop_eval_switch(true),
		"grading" => grading_switch(true)
	);
}

//return JSON
header('Content-type:application/json;charset=utf-8');
echo json_encode($result);

?>
