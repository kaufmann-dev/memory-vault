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
            $users = json_decode(file_get_contents('../json/users.json'), true)['users'];
            $nav = file_get_contents('./nav');

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
                <div id="pe" class="container text-center h-100 position-relative mh220">
                    <img id="gl" class="position-absolute top-50 start-50 translate-middle rounded rounded-1" src="flower.webp" alt="The Second Directory logo">
                </div>
            </div>
        </div>

        <script src="/js/jquery-3.7.1.min.js"></script>
        <script src="/js/bootstrap.bundle.min.js"></script>
        <script src="/js/jquery.resize.min.js"></script>
        <script>
            $(document).ready(function() {
                function lol(target_element = document.querySelector('#pe img'), source_element = document.querySelector('#pe'), percents = 50) {
                    let h = Math.min(source_element.getBoundingClientRect().width, source_element.getBoundingClientRect().height);
                    target_element.style.width = (h * percents / 100) + 'px';
                    target_element.style.height = (h * percents / 100) + 'px';
                }
                $("#pe").resize(function(){
                    lol();
                });
                lol();
            });
        </script>
    </body>
</html>