<?php
include_once('csv.php');

$display_table = '';
$display_students = '';

$servername = "localhost";
$username = "root";
$password = "root";
$dbname = "student_csv";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql_create_tables = "
    CREATE TABLE IF NOT EXISTS `address` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `state` VARCHAR(255),
        `city` VARCHAR(255),
        `street` VARCHAR(255),
        `zip` VARCHAR(10)
    );

    CREATE TABLE IF NOT EXISTS `hobbies` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `hobby_1` VARCHAR(255),
        `hobby_2` VARCHAR(255),
        `hobby_3` VARCHAR(255)
    );

    CREATE TABLE IF NOT EXISTS `students` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(255),
        `age` INT,
        `class` INT,
        `hobby_id` INT,
        `address_id` INT,
        FOREIGN KEY (`hobby_id`) REFERENCES `hobbies`(`id`),
        FOREIGN KEY (`address_id`) REFERENCES `address`(`id`)
    );
";

if ($conn->multi_query($sql_create_tables)) {
    do {
    } while ($conn->next_result());
} else {
    die("Error creating tables: " . $conn->error);
}

if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $student_id = $_GET['id'];
    deleteStudent($conn, $student_id);
}

if (isset($_POST['update']) && $_POST['update'] == 'Update Student') {
    $student_id = $_POST['student_id'];
    $name = $_POST['name'];
    $age = $_POST['age'];
    $class = $_POST['class'];
    $hobby_id = $_POST['hobby_id'];
    $address_id = $_POST['address_id'];

    updateStudent($conn, $student_id, $name, $age, $class, $hobby_id, $address_id);
}

$display_students = getAllStudents($conn);

if (isset($_POST['upload']) && $_POST['upload'] == 'Upload CSV') {
    $upload_dir = getcwd() . DIRECTORY_SEPARATOR . 'uploads';

    if ($_FILES['csv']['error'] == UPLOAD_ERR_OK) {
        $tmp_name = $_FILES['csv']['tmp_name'];
        $name = basename($_FILES['csv']['name']);
        $csvfile = $upload_dir . '/' . $name;
        move_uploaded_file($tmp_name, $csvfile);
        echo "File uploaded successfully.";

        processCSV($csvfile, $conn);
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();

        $display_table = get_html($csvfile);

        echo '<script>
                $(document).ready(function() {
                    $.ajax({
                        url: "update_table.php",
                        type: "GET",
                        success: function(data) {
                            $("#student_table").html(data);
                        }
                    });
                });
              </script>';
    }
}

function processCSV($csv_file, $conn)
{
    $file = fopen($csv_file, 'r');
    $header_arr = fgetcsv($file);

    while ($line = fgetcsv($file)) {
        $address_id = insertAddress($conn, $line[6], $line[7], $line[8], $line[9]);
        $hobby_id = insertHobby($conn, $line[3], $line[4], $line[5]);
        insertStudent($conn, $line[0], $line[1], $line[2], $hobby_id, $address_id);
    }

    fclose($file);
}

function insertAddress($conn, $state, $city, $street, $zip)
{
    $sql = "INSERT INTO address (state, city, street, zip) VALUES ('$state', '$city', '$street', '$zip')";
    $conn->query($sql);
    return $conn->insert_id;
}

function insertHobby($conn, $hobby_1, $hobby_2, $hobby_3)
{
    $sql = "INSERT INTO hobbies (hobby_1, hobby_2, hobby_3) VALUES ('$hobby_1', '$hobby_2', '$hobby_3')";
    $conn->query($sql);
    return $conn->insert_id;
}

function insertStudent($conn, $name, $age, $class, $hobby_id, $address_id)
{
    $sql = "INSERT INTO students (name, age, class, hobby_id, address_id) VALUES ('$name', $age, $class, $hobby_id, $address_id)";
    $conn->query($sql);
}

function deleteStudent($conn, $student_id)
{
    $sql = "DELETE FROM students WHERE id = $student_id";
    $conn->query($sql);
}

function updateStudent($conn, $student_id, $name, $age, $class, $hobby_id, $address_id)
{
    $sql = "UPDATE students SET name = '$name', age = $age, class = $class, hobby_id = $hobby_id, address_id = $address_id WHERE id = $student_id";
    $conn->query($sql);
}

function getAllStudents($conn)
{
    $output = '<h2>List of Students</h2>';
    $sql = "SELECT students.id, students.name, students.age, students.class,
        hobbies.hobby_1, hobbies.hobby_2, hobbies.hobby_3,
        address.state, address.city, address.street, address.zip
        FROM students
        INNER JOIN hobbies ON students.hobby_id = hobbies.id
        INNER JOIN address ON students.address_id = address.id
        ORDER BY students.name"; 

    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $output .= '<table border="1" id="student_table">';
        $output .= '<tr><th>ID</th><th>Name</th><th>Age</th><th>Class</th><th>Hobby_1</th><th>Hobby_2</th><th>Hobby_3</th><th>State</th><th>City</th><th>Street</th><th>ZIP</th><th>Action</th></tr>';

        while ($row = $result->fetch_assoc()) {
            $output .= '<tr>';
            $output .= '<td>' . $row['id'] . '</td>';
            $output .= '<td>' . $row['name'] . '</td>';
            $output .= '<td>' . $row['age'] . '</td>';
            $output .= '<td>' . $row['class'] . '</td>';
            $output .= '<td>' . $row['hobby_1'] . '</td>';
            $output .= '<td>' . $row['hobby_2'] . '</td>';
            $output .= '<td>' . $row['hobby_3'] . '</td>';
            $output .= '<td>' . $row['state'] . '</td>';
            $output .= '<td>' . $row['city'] . '</td>';
            $output .= '<td>' . $row['street'] . '</td>';
            $output .= '<td>' . $row['zip'] . '</td>';
            $output .= '<td><a href="?action=update&id=' . $row['id'] . '">Edit</a></td>';
            $output .= '</tr>';
        }

        $output .= '</table>';
    } else {
        $output .= '<p>No students found</p>';
    }

    return $output;
}

if (isset($_GET['action']) && $_GET['action'] == 'update' && isset($_GET['id'])) {
    $student_id = $_GET['id'];
    $update_form = getUpdateForm($conn, $student_id);
    echo $update_form;
}

function getUpdateForm($conn, $student_id)
{
    $output = '<h2>Update Student</h2>';
    $sql = "SELECT * FROM students WHERE id = $student_id";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $output .= '<form action="' . $_SERVER['PHP_SELF'] . '" method="post">';
        $output .= '<input type="hidden" name="student_id" value="' . $student_id . '" />';
        $output .= '<label for="update_name">Name:</label>';
        $output .= '<input type="text" name="name" id="update_name" value="' . $row['name'] . '" required />';
        $output .= '<label for="update_age">Age:</label>';
        $output .= '<input type="text" name="age" id="update_age" value="' . $row['age'] . '" required />';
        $output .= '<label for="update_class">Class:</label>';
        $output .= '<input type="text" name="class" id="update_class" value="' . $row['class'] . '" required />';
        $output .= '<label for="update_hobby_id">Hobby ID:</label>';
        $output .= '<input type="text" name="hobby_id" id="update_hobby_id" value="' . $row['hobby_id'] . '" required />';
        $output .= '<label for="update_address_id">Address ID:</label>';
        $output .= '<input type="text" name="address_id" id="update_address_id" value="' . $row['address_id'] . '" required />';
        $output .= '<input type="submit" name="update" value="Update Student" />';
        $output .= '</form>';
    } else {
        $output .= '<p>Student not found</p>';
    }

    return $output;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSV Upload</title>
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
</head>
<body>

    <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post" enctype="multipart/form-data">
        Select CSV file to upload:
        <input type="file" name="csv" /><br>
        <input type="submit" name="upload" value="Upload CSV" />
    </form>

    <div>
        <?php
        echo $display_students;
        if (strlen($display_table) > 0) {
            echo $display_table;
        }
        ?>
    </div>
</body>
</html>
