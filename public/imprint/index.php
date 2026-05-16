<?php
    session_start();
?>
<!DOCTYPE html>
<html class="h-100" lang="en">
    <head>
        <title>The Second Directory</title>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="description" content="The Second Directory.">
        <link href="/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="/css/bootstrap-icons.min.css">
        <link href="/css/style.css" rel="stylesheet">
        <link rel="icon" href="/favicon.ico">
        <link rel="apple-touch-icon" href="/favicon.ico">
    </head>
    <body class="h-100">
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
        ?>
        <div class="h-100 d-flex flex-column">
            <div class="flex-soy">
                <?php eval("?> $nav <?php "); ?>
            </div>
            <div class="flex-chad">
                <div class="container my-3">
                    <h1 class="display-4">Imprint</h1>
                    <div class="rounded bg-dark p-1 my-3"></div>
                    <p>
                        David Kaufmann<br>
                        Sonnenstraße 7,<br>
                        3550 Langenlois,<br>
                        Austria<br>
                        david@kaufmann.dev<br>
                        +43 670 3585527
                    </p>
                </div>
            </div>
        </div>

        <script src="/js/jquery-3.7.1.min.js"></script>
        <script src="/js/bootstrap.bundle.min.js"></script>
    </body>
</html>