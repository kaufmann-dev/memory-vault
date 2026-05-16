<?php
    session_start();
    
    $users = json_decode(file_get_contents('../../../json/users.json'), true)['users'];
    $pdfs = json_decode(file_get_contents('../../../json/pdfs.json'), true)['pdfs'];
    $nav = file_get_contents('../../nav');
    function Authenticate(){
        global $users;
        foreach($users as $lola){
            if(isset($_SESSION["username"]) && $_SESSION["username"] == $lola['username'] && $_SESSION["password"] == $lola['password']){
                return true;
            }
        }
        return false;
    }
    if(Authenticate()){
        $file = "../../../pdf/pdfs/".$pdfs[$_GET["id"] - 1]["filename"]; 
        header("Content-type: application/pdf"); 
        header("Content-Length: " . filesize($file)); 
        readfile($file); 
    } else{
    ?>
        <!DOCTYPE html>
        <html lang="en">
            <head>
                <title>The Second Directory</title>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="description" content="The Second Directory.">
                <link href="/css/bootstrap.min.css" rel="stylesheet">
                <link rel="stylesheet" href="/css/bootstrap-icons.min.css">
                <link rel="icon" href="/favicon.ico">
                <link rel="apple-touch-icon" href="/favicon.ico">
            </head>
            <body>
                <?php
                    eval("?> $nav <?php ");
                ?>
                <div class="container my-3">
                    You need to be logged in to access this page.
                </div>
                <script src="/js/jquery-3.7.1.min.js"></script>
                <script src="/js/bootstrap.bundle.min.js"></script>
            </body>
        </html>
    <?php
    }
?>
