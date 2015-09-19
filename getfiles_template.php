<?php
// POST HANDLER -->
$telegramtk=""; // inserire il token

if(isset($_POST['submit'])){

    $file_id = $_POST['file_id'];


$rawData = file_get_contents("https://api.telegram.org/bot".$telegramtk."/getFile?file_id=".$file_id);
$obj=json_decode($rawData, true);
$path=$obj["result"]["file_path"];

$pathc="https://api.telegram.org/file/bot".$telegramtk."/".$path;
header("Location: ".$pathc);

    exit;
}else{
// POST HANDLER -->
?>

<!-- FORM GOES BELOW -->

<!-- <form action="<?php echo $_SERVER['PHP_SELF']?>" method="post" name="registerForm"> -->
<form action="" method="post" name="registerForm">

<table style="width: 100%">
    <tr>
        <td>Inserire File ID:</td>
        <td><input name="file_id" type="text" style="width: 500px" /><br /></td>
    </tr>
    <tr>
            <td></td>
            <td><input style="width: 130px; height: 30px" type="submit" name="submit" value="Invia" /><br /></td>
        </tr>
</table>

</form>
<?php } ?>
