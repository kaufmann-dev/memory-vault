<?php
    session_start();
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
            $users = json_decode(file_get_contents('../../json/users.json'), true)['users'];
            $nav = file_get_contents('../nav');

            function Authenticate(){
                global $users;
                foreach($users as $lola){
                    if(isset($_SESSION["username"]) && $_SESSION["username"] == $lola['username'] && $_SESSION["password"] == $lola['password']){
                        return true;
                    }
                }
                return false;
            }

            if(isset($_POST["loginUsername"])){
                $_SESSION["username"] = $_POST["loginUsername"];
            }
            if(isset($_POST["loginPassword"])){
                $_SESSION["password"] = $_POST["loginPassword"];
            }
            
            eval("?> $nav <?php ");
        ?>
        <div class="container my-3">
            <?php
            if(Authenticate()){
                echo"You are logged in.";
            } else {
            ?>
                <form method="POST">
                    <div class="mb-3">
                        <label for="inputUsername" class="form-label">Username</label>
                        <input name="loginUsername" type="text" class="form-control" id="inputUsername">
                        <div class="form-text">We'll always share your username with everyone.</div>
                    </div>
                    <div class="mb-3">
                        <label for="inputPassword" class="form-label">Password</label>
                        <input name="loginPassword" type="password" class="form-control" id="inputPassword">
                    </div>
                    <button type="submit" class="btn btn-primary">Login</button>
                </form>
            <?php
            }
            ?>
        </div>

        <script src="/js/jquery-3.7.1.min.js"></script>
        <script src="/js/bootstrap.bundle.min.js"></script>
        <script>
            $(document).ready( function () {
                
            } );
        </script>
    </body>
</html>